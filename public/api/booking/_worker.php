<?php
/**
 * Terminbuchung — der Hintergrundlauf.
 *
 * WARUM ES DIESE DATEI GIBT
 * -------------------------
 * Zwei Dinge müssen regelmäßig passieren, ohne dass jemand sie anstößt:
 *
 *   1. Erinnerungsmails am Tag vor dem Gespräch.
 *   2. Der Ausgangskorb — jede Mail, die beim ersten Versuch nicht rausging.
 *
 * Beides hing bisher am Cronjob (remind.php). Auf diesem Hosting-Tarif gibt
 * es im hPanel aber gar keine Cronjob-Verwaltung; der Lauf muss über SSH
 * eingerichtet werden. Was von Hand eingerichtet werden muss, ist irgendwann
 * nicht eingerichtet — und dann bekommt niemand mehr eine Erinnerung, ohne
 * dass es auffällt. Genau dieser stille Ausfall ist hier das Problem.
 *
 * Deshalb hängt sich der Lauf zusätzlich an Aufrufe, die ohnehin stattfinden:
 * an jede Kalenderabfrage (slots), jede Buchung und jede Absage. Der Cronjob
 * bleibt der zuverlässigere Motor — er läuft auch dann, wenn tagelang niemand
 * die Seite besucht —, aber ohne ihn steht das System nicht mehr still.
 *
 * DREI VORKEHRUNGEN, DAMIT DAS NIE JEMANDEN WARTEN LÄSST
 *   • Gearbeitet wird erst, NACHDEM die Antwort beim Besucher ist
 *     (fastcgi_finish_request / litespeed_finish_request). Kann der Server
 *     das nicht, passiert hier gar nichts: Eine Kalenderabfrage, die wegen
 *     einer fremden Mail hängt, wäre der schlechtere Tausch.
 *   • Höchstens alle BK_WORK_INTERVAL Sekunden, abgesichert über eine
 *     Sperrdatei — zwei gleichzeitige Aufrufe arbeiten nie denselben Korb ab.
 *   • Höchstens BK_WORK_LIMIT Mails pro Lauf.
 *
 * Die schweren Dateien (_mail, _templates, _transport) werden bewusst erst
 * IM Lauf geladen, nicht beim Einbinden dieser Datei. Die Kalenderabfrage
 * bindet sie damit nur alle paar Minuten ein statt bei jedem Seitenaufruf.
 */

declare(strict_types=1);

require_once __DIR__ . '/_config.php';

/** Mindestabstand zwischen zwei Hintergrundläufen, in Sekunden. */
const BK_WORK_INTERVAL = 300;

/** Höchstzahl Mails, die ein Hintergrundlauf verschickt. */
const BK_WORK_LIMIT = 5;

/**
 * Hängt den Hintergrundlauf an das Ende dieses Aufrufs.
 *
 * Muss VOR respond() aufgerufen werden — respond() beendet das Skript, und
 * eine danach stehende Zeile wird nie erreicht. Die hier registrierte
 * Funktion läuft trotzdem, denn Shutdown-Funktionen überleben exit().
 */
function bkScheduleBackgroundWork(int $limit = BK_WORK_LIMIT): void {
    $finish = match (true) {
        function_exists('litespeed_finish_request') => 'litespeed_finish_request',
        function_exists('fastcgi_finish_request') => 'fastcgi_finish_request',
        default => null,
    };
    if ($finish === null) return;

    register_shutdown_function(static function () use ($finish, $limit): void {
        try {
            $finish();
            if (!bkClaimWorkSlot()) return;
            bkRunBackgroundWork($limit);
        } catch (Throwable $e) {
            error_log('booking/worker: Hintergrundlauf — ' . $e->getMessage());
        }
    });
}

/**
 * Zeitsperre: eine Datei, deren Inhalt den letzten Lauf festhält. Der
 * exklusive, nicht blockierende Lock sorgt dafür, dass von zwei gleichzeitigen
 * Aufrufen genau einer arbeitet und der andere sofort weitergeht, statt zu
 * warten.
 *
 * @return bool true, wenn dieser Aufruf arbeiten darf.
 */
function bkClaimWorkSlot(): bool {
    $marker = bkDataDir() . '/worker.lock';
    $fp = @fopen($marker, 'c+');
    if ($fp === false) return false;

    try {
        if (!flock($fp, LOCK_EX | LOCK_NB)) return false;
        $last = (int) (stream_get_contents($fp) ?: 0);
        if (time() - $last < BK_WORK_INTERVAL) return false;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) time());
        fflush($fp);
        return true;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/**
 * Ein Durchlauf: fällige Erinnerungen, danach der Ausgangskorb.
 *
 * Reihenfolge ist Absicht. Eine Erinnerung, die zu spät kommt, ist wertlos —
 * eine liegengebliebene Bestätigung darf dagegen eine Runde später raus.
 *
 * @return array{reminded:int, queue:array{sent:int, still_queued:int, failed:int}}
 */
function bkRunBackgroundWork(int $limit = BK_WORK_LIMIT): array {
    bkLoadMailStack();
    $reminded = bkSendDueReminders($limit);
    $queue = bkFlushMailQueue($limit);
    return ['reminded' => $reminded['sent'] + $reminded['queued'], 'queue' => $queue];
}

/**
 * Lädt die Mail-Dateien nach. Absichtlich eine eigene Funktion: Jede Stelle,
 * die Mails verschickt, ruft sie auf, und keine verlässt sich darauf, dass
 * eine andere sie vorher aufgerufen hat. require_once macht Mehrfachaufrufe
 * kostenlos.
 */
function bkLoadMailStack(): void {
    require_once __DIR__ . '/_mail.php';
    require_once __DIR__ . '/_templates.php';
}

/**
 * Verschickt an jeden bestätigten Termin, der in den nächsten 24 Stunden
 * beginnt, GENAU EINE Erinnerung.
 *
 * "Genau eine" garantiert das Feld reminded_at. Es wird in jedem Fall
 * gesetzt — auch nach einem Fehlschlag. Der ist seit dem Ausgangskorb kein
 * Verlust mehr: Die Mail liegt dort und wird wiederholt. Bliebe die Markierung
 * dagegen aus, entstünde bei jedem Lauf eine NEUE Erinnerung, und der Kunde
 * bekäme nach einer behobenen Störung ein Dutzend davon auf einmal.
 *
 * @return array{sent:int, queued:int}
 */
function bkSendDueReminders(int $limit = BK_WORK_LIMIT): array {
    // Erst hier laden: siehe Kopf der Datei.
    bkLoadMailStack();

    $sent = 0;
    $queued = 0;

    foreach (bkDueReminders(24) as $booking) {
        if ($sent + $queued >= $limit) break;

        $mail = bkMailReminder($booking);
        // Ohne Anhang — siehe book.php: Mails mit Anhang nimmt das
        // Hoster-Postfach an und stellt sie nie zu.
        $ok = bkMail($booking['email'], $booking['name'], $mail['subject'],
                     $mail['html'], $mail['text'], null, bkOwnerEmail(), 'erinnerung');

        bkMarkReminded($booking['token']);
        $ok ? $sent++ : $queued++;
    }

    return ['sent' => $sent, 'queued' => $queued];
}
