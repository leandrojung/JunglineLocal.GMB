<?php
/**
 * Erinnerungsmails — wird von einem Cronjob aufgerufen, nicht von Besuchern.
 *
 *   Aufruf per Cron (stündlich):
 *     curl -s "https://jungline.de/api/booking/remind?token=<BOOKING_CRON_TOKEN>"
 *   oder direkt auf dem Server:
 *     php /home/<user>/public_html/api/booking/remind.php
 *
 * Verschickt an jeden bestätigten Termin, der in den nächsten 24 Stunden
 * beginnt, genau eine Erinnerung. "Genau eine" garantiert das Feld
 * reminded_at: es wird gesetzt, sobald die Mail raus ist. Läuft der Cron
 * stündlich, bekommt derselbe Termin die Erinnerung trotzdem nur einmal.
 *
 * Ohne gesetztes BOOKING_CRON_TOKEN ist die Route über das Web gesperrt —
 * sonst könnte jeder durch wiederholtes Aufrufen Mails auslösen.
 */

declare(strict_types=1);

require_once __DIR__ . '/_store.php';
require_once __DIR__ . '/_mail.php';
require_once __DIR__ . '/_ics.php';
require_once __DIR__ . '/_templates.php';
// Der Erinnerungslauf selbst steht in _worker.php — dieselbe Funktion, die
// auch beilaeufig nach jeder Kalenderabfrage laeuft. Eine Implementierung
// statt zwei: sonst haetten Cron und Hintergrundlauf getrennte Regeln
// dafuer, wann eine Erinnerung als verschickt gilt.
require_once __DIR__ . '/_worker.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Cache-Control: no-store, max-age=0');
    $expected = envValue('BOOKING_CRON_TOKEN');
    $provided = (string) ($_GET['token'] ?? '');
    if ($expected === null || $provided === '' || !hash_equals($expected, $provided)) {
        respond(404, ['success' => false, 'error' => 'not_found']);
    }
}

$sent = 0;
$queued = 0;

try {
    // Der Cron darf mehr auf einmal als der Hintergrundlauf: Er blockiert
    // keinen wartenden Besucher, sondern laeuft fuer sich.
    $reminders = bkSendDueReminders(100);
    $sent = $reminders['sent'];
    $queued = $reminders['queued'];

    // Der eigentliche Motor des Ausgangskorbs: alles, was beim ersten
    // Versuch nicht rausging — Bestätigungen, Absagen, Erinnerungen —,
    // bekommt hier seine Wiederholung.
    $flush = bkFlushMailQueue(50);

    // Aufräumen im selben Lauf: alte Buchungen löschen, wie es die
    // Datenschutzerklärung zusagt (sechs Monate nach dem Termin), und das
    // Mailprotokoll auf BK_MAIL_LOG_DAYS begrenzen.
    $purged = bkPurgeOlderThan(180);
    $purged += bkMailQueuePurge(BK_MAIL_LOG_DAYS);
} catch (Throwable $e) {
    error_log('booking/remind: ' . $e->getMessage());
    if ($isCli) {
        fwrite(STDERR, 'Fehler: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    respond(500, ['success' => false, 'error' => 'server_error']);
}

if ($isCli) {
    echo 'Erinnerungen verschickt: ' . $sent . ', eingereiht: ' . $queued
        . ' | Ausgangskorb: ' . $flush['sent'] . ' nachgereicht, '
        . $flush['still_queued'] . ' weiter offen, ' . $flush['failed'] . ' endgültig gescheitert'
        . ' | aufgeräumt: ' . $purged . PHP_EOL;
    exit(0);
}

respond(200, [
    'success' => true,
    'sent' => $sent,
    // "failed" bleibt aus Gewohnheit im Ergebnis: Wer den Aufruf im Browser
    // prüft, sucht danach. Es zählt jetzt die eingereihten Mails — die sind
    // nicht verloren, nur noch nicht draußen.
    'failed' => $queued,
    'queue' => $flush,
    'purged' => $purged,
]);
