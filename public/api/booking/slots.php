<?php
/**
 * GET /api/booking/slots?month=YYYY-MM
 *
 * Liefert die freien Termine eines Monats. Der Kalender im Browser rendert
 * ausschließlich, was hier zurückkommt — er kennt weder Arbeitszeiten noch
 * Vorlauffristen. Damit gibt es keine zweite Wahrheit, die auseinanderlaufen
 * könnte, und niemand kann durch Manipulation im Browser einen gesperrten
 * Slot sichtbar machen.
 */

declare(strict_types=1);

require_once __DIR__ . '/_slots.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, ['success' => false, 'error' => 'method_not_allowed']);
}

// Terminlage ändert sich minütlich — Antwort nie zwischenspeichern.
header('Cache-Control: no-store, max-age=0');

$now = bkNow();
$todayLocal = bkLocal($now)->setTime(0, 0);
$horizonLocal = bkLocal($now->modify('+' . BK_HORIZON_DAYS . ' days'))->setTime(0, 0);

$monthParam = isset($_GET['month']) ? (string) $_GET['month'] : $todayLocal->format('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    respond(400, ['success' => false, 'error' => 'invalid_month']);
}

$monthStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $monthParam . '-01 00:00:00', bkTz());
if ($monthStart === false) {
    respond(400, ['success' => false, 'error' => 'invalid_month']);
}

// Vergangenheit und alles jenseits des Horizonts gar nicht erst durchrechnen.
if ($monthStart->format('Y-m') < $todayLocal->format('Y-m')
    || $monthStart->format('Y-m') > $horizonLocal->format('Y-m')) {
    respond(200, [
        'success' => true,
        'month' => $monthParam,
        'days' => (object) [],
        'timezone' => BK_TZ,
        'slot_minutes' => BK_SLOT_MIN,
        'today' => $todayLocal->format('Y-m-d'),
        'min_month' => $todayLocal->format('Y-m'),
        'max_month' => $horizonLocal->format('Y-m'),
    ]);
}

// Im laufenden Monat erst ab heute rechnen, sonst ab dem Ersten.
$from = $monthStart < $todayLocal ? $todayLocal : $monthStart;
$to = $monthStart->modify('last day of this month')->setTime(0, 0);
if ($to > $horizonLocal) $to = $horizonLocal;

try {
    $days = bkAvailability($from, $to);
} catch (Throwable $e) {
    error_log('booking/slots: ' . $e->getMessage());
    respond(500, ['success' => false, 'error' => 'server_error']);
}

respond(200, [
    'success' => true,
    'month' => $monthParam,
    // (object) statt [] — ein leeres PHP-Array würde als JSON-Liste kodiert,
    // und der Browser bekäme mal ein Objekt, mal ein Array.
    'days' => $days === [] ? (object) [] : $days,
    'timezone' => BK_TZ,
    'slot_minutes' => BK_SLOT_MIN,
    'duration_label' => BK_DURATION_LABEL,
    'today' => $todayLocal->format('Y-m-d'),
    'min_month' => $todayLocal->format('Y-m'),
    'max_month' => $horizonLocal->format('Y-m'),
]);
