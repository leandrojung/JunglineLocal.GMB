<?php
/**
 * /api/booking/ics?token=…
 *
 * Liefert die Kalenderdatei zu einem Termin zum Herunterladen aus.
 *
 * Warum als Adresse und nicht als Anhang: Der Mailversand über das
 * Hoster-Postfach nimmt jede Nachricht an ("250 queued"), verwirft danach
 * aber jede mit Anhang — belegt durch die Diagnose unter /mailtest, bei der
 * anhanglose Testmails ankamen und dieselben Mails mit Anhang nicht, quer
 * über mehrere Dateitypen. Über eine Adresse holt der Kunde die Datei direkt
 * bei uns; der Weg, der sie bisher verschluckt hat, entfällt damit ganz.
 *
 * Der Token ist derselbe wie beim Absagelink: Wer ihn hat, darf den Termin
 * sehen. Etwas Schwächeres wäre falsch (Fremde könnten Termine auslesen),
 * etwas Stärkeres unzumutbar — für einen Kalendereintrag ist ein Login keine
 * verhältnismäßige Hürde.
 */

declare(strict_types=1);

require_once __DIR__ . '/_store.php';
require_once __DIR__ . '/_ics.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

$token   = trim((string) ($_GET['token'] ?? ''));
$booking = $token === '' ? null : bkFindByToken($token);

if ($booking === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Zu diesem Link gibt es keinen Termin.\n";
    exit;
}

// Abgesagte Termine als CANCEL ausliefern: Wer die Datei erneut öffnet,
// bekommt den Eintrag aus dem Kalender entfernt statt ein zweites Mal
// hineingelegt.
$method = ($booking['status'] ?? '') === 'confirmed' ? 'PUBLISH' : 'CANCEL';

header('Content-Type: text/calendar; charset=utf-8; method=' . $method);
header('Content-Disposition: attachment; filename="termin-jungline.ics"');

echo bkIcs($booking, $method);
