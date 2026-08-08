<?php
/**
 * POST /api/booking/book
 *
 * Nimmt eine Buchung entgegen. Reihenfolge ist Absicht:
 *
 *   1. Slot reservieren (Datenbank)   — scheitert das, ist nichts passiert.
 *   2. Google-Kalender eintragen      — scheitert das, bleibt die Buchung gültig.
 *   3. Mails verschicken              — scheitert das, bleibt die Buchung gültig.
 *
 * Der Slot wird zuerst gesichert, weil er die einzige knappe Ressource ist.
 * Alles danach ist Nacharbeit: Ein Termin, der im Speicher steht, aber dessen
 * Bestätigungsmail klemmt, ist ärgerlich — ein Termin, den zwei Leute
 * gleichzeitig bekommen haben, ist ein echter Schaden.
 *
 * Beim Verschieben (cancel_token) wird bewusst erst der NEUE Termin gebucht
 * und danach der alte abgesagt. Andersherum stünde der Kunde ohne Termin da,
 * falls der neue Slot in der Zwischenzeit weg ist.
 */

declare(strict_types=1);

require_once __DIR__ . '/_slots.php';
require_once __DIR__ . '/_mail.php';
require_once __DIR__ . '/_ics.php';
require_once __DIR__ . '/_templates.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'error' => 'method_not_allowed']);
}

header('Cache-Control: no-store, max-age=0');

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, ['success' => false, 'error' => 'invalid_body']);
}

// ---- Spam-Falle: ein für Menschen unsichtbares Feld. Ist es ausgefüllt,
// war ein Bot am Werk. Wir antworten bewusst mit "erfolgreich", damit der
// Bot nichts über die Erkennung lernt — gebucht wird aber nichts.
if (trim((string) ($input['website'] ?? '')) !== '') {
    respond(200, ['success' => true, 'spam' => true]);
}

$name    = trim((string) ($input['name'] ?? ''));
$email   = trim((string) ($input['email'] ?? ''));
$phone   = trim((string) ($input['phone'] ?? ''));
$company = trim((string) ($input['company'] ?? ''));
$message = trim((string) ($input['message'] ?? ''));
$date    = trim((string) ($input['date'] ?? ''));
$time    = trim((string) ($input['time'] ?? ''));
$rescheduleToken = trim((string) ($input['cancel_token'] ?? ''));

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 80) $errors['name'] = 'Bitte geben Sie Ihren Namen an.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) $errors['email'] = 'Bitte prüfen Sie Ihre E-Mail-Adresse.';
if (mb_strlen($phone) > 40) $errors['phone'] = 'Die Telefonnummer ist zu lang.';
if (mb_strlen($company) > 120) $errors['company'] = 'Der Eintrag ist zu lang.';
if (mb_strlen($message) > 2000) $errors['message'] = 'Bitte fassen Sie sich etwas kürzer (max. 2000 Zeichen).';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
    $errors['slot'] = 'Bitte wählen Sie einen Termin.';
}
if ($errors !== []) {
    respond(422, ['success' => false, 'error' => 'validation_failed', 'fields' => $errors]);
}

// Zeilenumbrüche in Kopfzeilen sind der klassische Header-Injection-Weg.
$name    = preg_replace('/[\r\n]+/', ' ', $name);
$company = preg_replace('/[\r\n]+/', ' ', $company);
$phone   = preg_replace('/[\r\n]+/', ' ', $phone);

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

try {
    // Wer einen gültigen Verschiebe-Token mitbringt, hat bereits einen
    // Termin — für ihn entsteht unterm Strich keine zusätzliche Buchung.
    // Das Ratelimit greift hier deshalb nicht: sonst könnte ein Kunde, der
    // seinen Termin zweimal verschiebt, den dritten Versuch nicht mehr
    // abschließen und stünde am Ende ganz ohne Termin da.
    $oldBooking = $rescheduleToken !== '' ? bkFindByToken($rescheduleToken) : null;
    $isReschedule = $oldBooking !== null && ($oldBooking['status'] ?? '') === 'confirmed';

    if (!$isReschedule && bkCountForIpToday($ip) >= BK_RATE_PER_IP_DAY) {
        respond(429, ['success' => false, 'error' => 'rate_limited']);
    }
} catch (Throwable $e) {
    error_log('booking/book: Ratelimit-Prüfung fehlgeschlagen — ' . $e->getMessage());
    respond(500, ['success' => false, 'error' => 'server_error']);
}

$startUtc = bkUtc($date, $time);
if ($startUtc === null) {
    respond(422, ['success' => false, 'error' => 'validation_failed', 'fields' => ['slot' => 'Bitte wählen Sie einen Termin.']]);
}
$endUtc = $startUtc->modify('+' . BK_SLOT_MIN . ' minutes');

try {
    if (!bkSlotIsFree($date, $time)) {
        respond(409, ['success' => false, 'error' => 'slot_taken']);
    }

    $booking = [
        'token' => bkToken(),
        'start_utc' => bkStamp($startUtc),
        'end_utc' => bkStamp($endUtc),
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'message' => $message,
        'created_at' => bkStamp(bkNow()),
        'ip' => $ip,
    ];

    // 1) Slot sichern. false = jemand war in den letzten Millisekunden schneller.
    if (!bkInsert($booking)) {
        respond(409, ['success' => false, 'error' => 'slot_taken']);
    }
} catch (Throwable $e) {
    error_log('booking/book: Speichern fehlgeschlagen — ' . $e->getMessage());
    respond(500, ['success' => false, 'error' => 'server_error']);
}

// ---- Ab hier ist der Termin verbindlich gebucht. Nichts, was jetzt noch
// schiefgeht, darf dem Besucher als Fehlschlag angezeigt werden.

$warnings = [];

// 2) Google-Kalender
try {
    $eventId = bkGoogleInsert($booking);
    if ($eventId !== '') {
        bkSetEventId($booking['token'], $eventId);
        $booking['gcal_event_id'] = $eventId;
    } elseif (bkGoogleEnabled()) {
        $warnings[] = 'Der Termin konnte nicht in den Google-Kalender eingetragen werden — bitte von Hand nachtragen.';
    }
} catch (Throwable $e) {
    error_log('booking/book: Google-Eintrag fehlgeschlagen — ' . $e->getMessage());
    $warnings[] = 'Der Termin konnte nicht in den Google-Kalender eingetragen werden — bitte von Hand nachtragen.';
}

// 3) Den alten Termin absagen — erst jetzt, wo der neue sicher steht.
if ($isReschedule) {
    try {
        bkCancel($rescheduleToken);
        if (($oldBooking['gcal_event_id'] ?? '') !== '') {
            bkGoogleDelete((string) $oldBooking['gcal_event_id']);
        }
    } catch (Throwable $e) {
        error_log('booking/book: Alten Termin absagen fehlgeschlagen — ' . $e->getMessage());
    }
}

// 4) Mails
//
// Bewusst ohne .ics-Anhang: Das Hoster-Postfach nimmt jede Mail an ("250
// queued") und verwirft danach jede mit Anhang, ohne Rückmeldung — belegt
// durch /api/booking/mailtest, wo anhanglose Testmails ankamen und dieselben
// Mails mit Anhang nicht, quer über mehrere Dateitypen. Der Kalendereintrag
// steckt deshalb als Link in der Mail (siehe bkEmailCalendarLinks) und wird
// über /api/booking/ics von unserem eigenen Server geholt.

try {
    $confirmation = bkMailConfirmation($booking);
    $sent = bkMail($booking['email'], $booking['name'], $confirmation['subject'],
                   $confirmation['html'], $confirmation['text'], null, bkOwnerEmail());
    if (!$sent) $warnings[] = 'Die Bestätigungsmail an ' . $booking['email'] . ' konnte nicht zugestellt werden.';
} catch (Throwable $e) {
    error_log('booking/book: Bestätigungsmail fehlgeschlagen — ' . $e->getMessage());
    $warnings[] = 'Die Bestätigungsmail an ' . $booking['email'] . ' konnte nicht zugestellt werden.';
}

try {
    $notice = bkMailOwnerNotice($booking, implode(' ', $warnings));
    bkMail(bkOwnerEmail(), bkOwnerName(), $notice['subject'], $notice['html'], $notice['text'], null, $booking['email']);
} catch (Throwable $e) {
    error_log('booking/book: Benachrichtigung an den Betreiber fehlgeschlagen — ' . $e->getMessage());
}

respond(200, [
    'success' => true,
    'booking' => [
        'date' => $date,
        'time' => $time,
        'date_label' => bkFormatDate($startUtc),
        'time_label' => bkFormatTime($startUtc, $endUtc),
        'timezone' => BK_TZ,
        'email' => $booking['email'],
        'manage_url' => bkManageUrl($booking['token']),
        'meeting_url' => bkMeetingUrl(),
    ],
]);
