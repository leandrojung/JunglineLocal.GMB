<?php
/**
 * POST /api/stats — nimmt einen Seitenaufruf oder ein Ereignis entgegen.
 * Was gespeichert wird und was nicht: siehe _stats.php.
 *
 * Antwortet immer mit 204 und leerem Inhalt, auch bei verworfenen
 * Aufrufen: Der Browser schickt per sendBeacon und wartet auf nichts, und
 * wer Unsinn schickt, soll über die Prüfregeln nichts erfahren.
 */

declare(strict_types=1);

require_once __DIR__ . '/_stats.php';

date_default_timezone_set('Europe/Berlin');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$fertig = static function (): never {
    http_response_code(204);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $fertig();

// Fremde Seiten dürfen nicht mitzählen.
$origin = strtolower((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
if ($origin !== '') {
    $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $eigen = strtolower((string) parse_url(bkSiteUrl(), PHP_URL_HOST));
    $lokal = $host === 'localhost' || $host === '127.0.0.1';
    if (!$lokal && $host !== $eigen && !str_ends_with($host, '.' . $eigen)) $fertig();
}

// Suchmaschinen-Crawler, Vorschau-Dienste und Messwerkzeuge zählen nicht.
$ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|preview|facebookexternalhit|whatsapp|curl|wget|python|java\//i', $ua)) {
    $fertig();
}

$roh = (string) file_get_contents('php://input', false, null, 0, 2048);
$in = json_decode($roh, true);
if (!is_array($in)) $fertig();

$event = isset($in['e']) ? (string) $in['e'] : '';
if ($event !== '') {
    if (!array_key_exists($event, STATS_EVENTS)) $fertig();
    statsRecord(['event' => $event]);
    $fertig();
}

// Seitenaufruf: nur Pfade, wie sie auf dieser Seite vorkommen — ohne
// Abfrage und Anker, damit keine Eingaben oder Kennungen mitgezählt werden.
$pfad = (string) ($in['p'] ?? '');
if (!preg_match('#^/[a-z0-9\-/]{0,100}$#', $pfad)) $fertig();

// Herkunft: nur der Hostname einer fremden Seite, nie die volle Adresse.
$ref = strtolower((string) ($in['r'] ?? ''));
if ($ref !== '' && !preg_match('/^[a-z0-9.\-]{3,80}$/', $ref)) $ref = '';

statsRecord(['path' => $pfad, 'ref' => $ref, 'mobile' => !empty($in['m'])]);
$fertig();
