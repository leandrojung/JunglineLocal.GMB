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
    foreach (bkDueReminders(24) as $booking) {
        $mail = bkMailReminder($booking);

        // Ohne Anhang — siehe book.php: Mails mit Anhang nimmt das
        // Hoster-Postfach an und stellt sie nie zu.
        $ok = bkMail($booking['email'], $booking['name'], $mail['subject'],
                     $mail['html'], $mail['text'], null, bkOwnerEmail(), 'erinnerung');

        // In JEDEM Fall markieren — auch nach einem Fehlschlag. Der ist
        // seit dem Ausgangskorb kein Verlust mehr: Die Mail liegt dort und
        // wird von diesem Lauf hier wiederholt. Würde stattdessen die
        // Markierung ausbleiben, entstünde bei jedem stündlichen Lauf eine
        // NEUE Erinnerung, und der Kunde bekäme nach einer behobenen
        // Störung ein Dutzend davon auf einmal.
        bkMarkReminded($booking['token']);
        $ok ? $sent++ : $queued++;
    }

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
