<?php
/**
 * Terminbuchung — Mailversand.
 *
 * Diese Datei ist die einzige Stelle, die der Rest des Buchungssystems
 * kennt: bkMail() rein, Wahrheit raus. Wie die Mail tatsächlich hinausgeht,
 * steht in _transport.php.
 *
 * DREI DINGE MACHEN DEN UNTERSCHIED ZU VORHER
 * -------------------------------------------
 * 1. MEHRERE WEGE. Scheitert der erste Versandweg, wird der nächste
 *    probiert. Eine Störung beim Dienstleister kostet keine Buchung mehr.
 *
 * 2. NICHTS GEHT VERLOREN. Klappt kein Weg, wandert die Mail in den
 *    Ausgangskorb und wird erneut versucht — stündlich, mit wachsendem
 *    Abstand, bis zu sechsmal. Vorher war eine gescheiterte Mail einfach
 *    weg: eine Zeile im Fehlerprotokoll, die niemand liest.
 *
 * 3. ES IST NACHPRÜFBAR. Jede Mail hinterlässt eine Zeile mit Weg,
 *    Vorgangsnummer und Grund. "Der Kunde hat nichts bekommen" ist damit
 *    keine Sackgasse mehr, sondern eine Frage mit Antwort — nachzulesen
 *    unter /api/booking/mailtest?token=…
 *
 * WICHTIG BLEIBT TROTZDEM: Ein "angenommen" ist keine Zustellung. Es sagt
 * nur, dass der Versandweg die Mail übernommen hat. Ob sie im Postfach
 * landet, entscheidet die Absenderreputation — dafür sorgen SPF, DKIM und
 * DMARC (Anleitung in TERMINBUCHUNG.md). Die Diagnoseseite prüft sie.
 */

declare(strict_types=1);

require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/_store.php';
require_once __DIR__ . '/_transport.php';

/**
 * Abstände zwischen den Wiederholungsversuchen, in Minuten.
 *
 * Der erste Versuch passiert sofort beim Buchen. Danach wartet der Cron —
 * kurz, falls es nur ein Schluckauf war, dann immer länger, damit eine
 * echte Störung nicht stündlich dieselbe Mail gegen die Wand fährt. Nach
 * dem letzten Eintrag gilt die Mail als endgültig gescheitert und bleibt
 * als solche im Protokoll stehen.
 */
const BK_MAIL_RETRIES = [5, 20, 60, 180, 480, 1440];

/** Höchstalter eines Eintrags im Protokoll, in Tagen. */
const BK_MAIL_LOG_DAYS = 90;

/**
 * Verschickt eine Mail über den ersten Weg, der sie annimmt.
 *
 * Rückgabe false heißt: kein Weg hat sie angenommen. Die Mail ist dann
 * NICHT verloren — sie liegt im Ausgangskorb und wird wiederholt. Der
 * Aufrufer entscheidet, ob er den Besucher darauf hinweist; eine Buchung
 * scheitert daran nie.
 *
 * @param string $kind Art der Mail für das Protokoll: 'bestaetigung',
 *                     'erinnerung', 'absage', 'intern' oder 'test'.
 */
function bkMail(
    string $toEmail,
    string $toName,
    string $subject,
    string $html,
    string $text,
    ?array $ics = null,
    ?string $replyTo = null,
    string $kind = 'mail'
): bool {
    $msg = [
        'to_email' => $toEmail,
        'to_name' => bkCleanName($toName),
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
        'reply_to' => $replyTo,
        'ics' => $ics,
        'kind' => $kind,
    ];

    $result = bkDeliver($msg);

    try {
        bkMailQueueAdd([
            'status' => $result['ok'] ? 'sent' : 'queued',
            'attempts' => 1,
            'next_try' => $result['ok'] ? '' : bkNextTry(1),
            'kind' => $kind,
            'to_email' => $toEmail,
            'to_name' => $msg['to_name'],
            'subject' => $subject,
            'payload' => json_encode($msg, JSON_UNESCAPED_UNICODE) ?: '{}',
            'transport' => $result['transport'],
            'provider_id' => $result['id'],
            'error' => $result['error'],
        ]);
    } catch (Throwable $e) {
        // Ein kaputtes Protokoll darf niemals den Versand verhindern.
        error_log('booking/mail: Protokolleintrag fehlgeschlagen — ' . $e->getMessage());
    }

    if (!$result['ok']) {
        error_log('booking/mail: an ' . $toEmail . ' über keinen Weg zugestellt — ' . $result['error']);
    }
    return $result['ok'];
}

/**
 * Probiert die Versandwege der Reihe nach durch.
 *
 * @return array{ok:bool, transport:string, id:string, error:string, log:array<int,string>}
 */
function bkDeliver(array $msg): array {
    $chain = bkTransportChain();
    if ($chain === []) {
        return [
            'ok' => false, 'transport' => '', 'id' => '',
            'error' => 'Kein Versandweg konfiguriert (weder API-Schlüssel noch SMTP noch mail()).',
            'log' => [],
        ];
    }

    $log = [];
    $errors = [];
    foreach ($chain as $id) {
        $log[] = '=== Versuch über ' . bkTransportLabel($id);
        try {
            $res = bkTransportSend($id, $msg);
        } catch (Throwable $e) {
            $res = ['ok' => false, 'id' => '', 'error' => 'Ausnahme: ' . $e->getMessage(), 'log' => []];
        }
        $log = array_merge($log, $res['log']);

        if ($res['ok']) {
            return ['ok' => true, 'transport' => $id, 'id' => $res['id'], 'error' => '', 'log' => $log];
        }
        $errors[] = $id . ': ' . $res['error'];
        $log[] = '!!! ' . $res['error'];
    }

    return [
        'ok' => false, 'transport' => '', 'id' => '',
        'error' => implode(' // ', $errors),
        'log' => $log,
    ];
}

/** Zeitpunkt des nächsten Versuchs nach $attempts bisherigen Versuchen. */
function bkNextTry(int $attempts): string {
    $index = min(max($attempts, 1), count(BK_MAIL_RETRIES)) - 1;
    return bkStamp(bkNow()->modify('+' . BK_MAIL_RETRIES[$index] . ' minutes'));
}

/**
 * Arbeitet den Ausgangskorb ab. Läuft im Cron zusammen mit den
 * Erinnerungen (remind.php) und beiläufig nach jeder Buchung
 * (bkScheduleQueueFlush).
 *
 * @return array{sent:int, still_queued:int, failed:int}
 */
function bkFlushMailQueue(int $limit = 25): array {
    $sent = 0;
    $stillQueued = 0;
    $failed = 0;

    foreach (bkMailQueueDue($limit) as $row) {
        $msg = json_decode((string) $row['payload'], true);
        $attempts = (int) $row['attempts'] + 1;

        if (!is_array($msg) || ($msg['to_email'] ?? '') === '') {
            bkMailQueueUpdate((int) $row['id'], [
                'status' => 'failed',
                'attempts' => $attempts,
                'next_try' => '',
                'error' => 'Der gespeicherte Inhalt der Mail ist unlesbar.',
            ]);
            $failed++;
            continue;
        }

        $result = bkDeliver($msg);

        if ($result['ok']) {
            bkMailQueueUpdate((int) $row['id'], [
                'status' => 'sent',
                'attempts' => $attempts,
                'next_try' => '',
                'transport' => $result['transport'],
                'provider_id' => $result['id'],
                'error' => '',
            ]);
            $sent++;
            continue;
        }

        $exhausted = $attempts >= count(BK_MAIL_RETRIES);
        bkMailQueueUpdate((int) $row['id'], [
            'status' => $exhausted ? 'failed' : 'queued',
            'attempts' => $attempts,
            'next_try' => $exhausted ? '' : bkNextTry($attempts),
            'error' => $result['error'],
        ]);
        $exhausted ? $failed++ : $stillQueued++;
    }

    return ['sent' => $sent, 'still_queued' => $stillQueued, 'failed' => $failed];
}

/**
 * Arbeitet ein paar liegengebliebene Mails ab, NACHDEM der Besucher seine
 * Antwort schon hat.
 *
 * Der Cronjob ist der eigentliche Motor des Ausgangskorbs. Auf diesem
 * Hosting-Tarif lässt er sich aber nur über SSH einrichten (siehe
 * TERMINBUCHUNG.md) — und was von Hand eingerichtet werden muss, ist
 * irgendwann nicht eingerichtet. Damit der Korb auch dann leerläuft, hängt
 * sich diese Funktion ans Ende eines ohnehin stattfindenden Aufrufs.
 *
 * Zwei Vorkehrungen, damit das nie jemanden warten lässt:
 *   • Gearbeitet wird erst, wenn die Antwort raus ist (finish_request).
 *     Kann der Server das nicht, passiert hier gar nichts — ein
 *     Buchungsformular, das wegen einer fremden Mail hängt, wäre der
 *     schlechtere Tausch.
 *   • Höchstens alle fünf Minuten, höchstens drei Mails.
 */
function bkScheduleQueueFlush(int $limit = 3): void {
    $finish = match (true) {
        function_exists('litespeed_finish_request') => 'litespeed_finish_request',
        function_exists('fastcgi_finish_request') => 'fastcgi_finish_request',
        default => null,
    };
    if ($finish === null) return;

    register_shutdown_function(static function () use ($finish, $limit): void {
        try {
            $finish();

            // Zeitsperre: eine Datei, deren Änderungszeit den letzten Lauf
            // festhält. Der exklusive Lock verhindert, dass zwei parallele
            // Aufrufe denselben Korb gleichzeitig abarbeiten.
            $marker = bkDataDir() . '/mailflush.lock';
            $fp = @fopen($marker, 'c+');
            if ($fp === false) return;
            try {
                if (!flock($fp, LOCK_EX | LOCK_NB)) return;
                $last = (int) (stream_get_contents($fp) ?: 0);
                if (time() - $last < 300) return;
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, (string) time());
                fflush($fp);
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            bkFlushMailQueue($limit);
        } catch (Throwable $e) {
            error_log('booking/mail: Nachlauf des Ausgangskorbs — ' . $e->getMessage());
        }
    });
}
