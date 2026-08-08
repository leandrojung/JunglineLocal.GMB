<?php
/**
 * /api/booking/gcal?token=…
 *
 * Leitet vom "Zu Google Kalender"-Knopf in der Mail zum vorausgefüllten
 * Google-Kalender-Eintrag weiter.
 *
 * Warum der Umweg: Der direkte calendar.google.com/render-Link ist die
 * Signatur bekannter Kalender-Spam-Wellen — Mails, die ihn enthalten,
 * verwerfen manche Filter stillschweigend. In der Mail steht deshalb nur
 * die eigene Domain; die Weiterleitung passiert erst beim Klick.
 *
 * Derselbe Token wie beim Absagelink: Wer ihn hat, darf den Termin sehen.
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

// Abgesagte Termine gehören in keinen Kalender mehr — stattdessen zur
// Buchungsseite, falls jemand einen neuen Termin möchte.
if (($booking['status'] ?? '') !== 'confirmed') {
    header('Location: ' . bkSiteUrl() . '/kontakt/#termin', true, 302);
    exit;
}

header('Location: ' . bkGoogleCalendarUrl($booking), true, 302);
