<?php
/**
 * GBP-Profil-Check — server-seitige Route für das Startseiten-Badge.
 * Nimmt company/city/keyword entgegen, fragt die Google Places API (New)
 * ab und liefert einen Vollständigkeits-Score 0-100 zurück. Der API-Key
 * bleibt ausschließlich hier auf dem Server, nie im Response-Body.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $body) {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'error' => 'method_not_allowed']);
}

// ---------------------------------------------------------------------
// Eingabe lesen & validieren
// ---------------------------------------------------------------------
$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    respond(400, ['success' => false, 'error' => 'invalid_body']);
}

function cleanField(array $input, string $key, int $maxLen = 150): ?string {
    $value = isset($input[$key]) ? trim((string) $input[$key]) : '';
    if ($value === '' || mb_strlen($value) > $maxLen) return null;
    return $value;
}

$company = cleanField($input, 'company');
$city    = cleanField($input, 'city');
$keyword = cleanField($input, 'keyword');

if ($company === null || $city === null || $keyword === null) {
    respond(400, ['success' => false, 'error' => 'missing_fields']);
}

// ---------------------------------------------------------------------
// API-Key laden — nie im Frontend, nie im Repo. Reihenfolge:
// 1) echte PHP-Umgebungsvariable (z. B. im Hostinger hPanel gesetzt)
// 2) .env-Datei OBERHALB des Web-Roots — public_html/../.env bzw. eine
//    Ebene weiter, je nach Deploy-Layout. Landet dadurch nie im per Vite
//    gebauten dist/-Ordner und ist so nie öffentlich abrufbar.
// ---------------------------------------------------------------------
function loadApiKey(): ?string {
    // Manche Hosting-Setups (PHP-FPM/LSAPI, Apache SetEnv) reichen
    // Env-Variablen nicht über getenv() durch, sondern nur über
    // $_SERVER/$_ENV (abhängig von php.ini variables_order). Zusätzlich
    // hängt Apache bei internen Redirects (unser mod_rewrite auf
    // api/gbp-check.php greift genau das) ein "REDIRECT_"-Präfix an
    // bereits gesetzte SetEnv-Variablen an — deshalb auch das prüfen.
    $names = ['GOOGLE_PLACES_API_KEY', 'REDIRECT_GOOGLE_PLACES_API_KEY'];
    foreach ($names as $name) {
        foreach ([getenv($name), $_SERVER[$name] ?? false, $_ENV[$name] ?? false] as $fromEnv) {
            if (is_string($fromEnv) && $fromEnv !== '') return $fromEnv;
        }
        if (function_exists('apache_getenv')) {
            $fromApache = apache_getenv($name);
            if (is_string($fromApache) && $fromApache !== '') return $fromApache;
        }
    }

    $candidates = [
        __DIR__ . '/../../.env',
        __DIR__ . '/../../../.env',
        __DIR__ . '/../.env',
    ];
    foreach ($candidates as $path) {
        if (!is_file($path)) continue;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            if (trim($key) === 'GOOGLE_PLACES_API_KEY') {
                $value = trim(trim($value), "\"'");
                if ($value !== '') return $value;
            }
        }
    }
    return null;
}

$apiKey = loadApiKey();
if (!$apiKey) {
    error_log('gbp-check: GOOGLE_PLACES_API_KEY fehlt oder ist leer');
    respond(500, ['success' => false, 'error' => 'server_not_configured']);
}

// ---------------------------------------------------------------------
// Google Places API (New) — kleiner cURL-Helper
// ---------------------------------------------------------------------
function placesRequest(string $method, string $url, string $apiKey, string $fieldMask, ?array $body = null, ?array &$failInfo = null): ?array {
    if (!function_exists('curl_init')) {
        $failInfo = ['reason' => 'curl_missing'];
        error_log('gbp-check: PHP-cURL-Extension ist nicht aktiviert');
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiKey,
            'X-Goog-FieldMask: ' . $fieldMask,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error !== '') {
        $failInfo = ['reason' => 'connection_failed', 'curl_errno' => $errno];
        error_log('gbp-check: cURL-Fehler (' . $errno . ') — ' . $error);
        return null;
    }
    $decoded = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
        $googleReason = is_array($decoded) ? ($decoded['error']['status'] ?? null) : null;
        $failInfo = ['reason' => 'google_http_error', 'status' => $httpCode, 'google_status' => $googleReason];
        error_log('gbp-check: Places API HTTP ' . $httpCode . ' — ' . substr((string) $response, 0, 500));
        return null;
    }
    return $decoded;
}

// 1) Text Search: erstes passendes Profil finden
$searchFail = null;
$searchResult = placesRequest(
    'POST',
    'https://places.googleapis.com/v1/places:searchText',
    $apiKey,
    'places.id,places.displayName,places.formattedAddress',
    [
        'textQuery' => $company . ' ' . $keyword . ' ' . $city,
        'languageCode' => 'de',
        'maxResultCount' => 1,
    ],
    $searchFail
);

// Ein fehlgeschlagener Request (Auth/API/Netzwerk) ist ein Server-Fehler,
// KEIN "nicht gefunden" — sonst verschleiern wir Konfigurationsfehler
// als vermeintlich fehlendes Google-Profil.
if ($searchResult === null) {
    respond(502, ['success' => false, 'error' => 'upstream_error', 'debug' => $searchFail]);
}

$placeId = $searchResult['places'][0]['id'] ?? null;
if (!is_string($placeId) || $placeId === '') {
    respond(200, ['success' => false, 'error' => 'not_found']);
}

// 2) Place Details: die für den Score nötigen Felder abrufen
$detailsFail = null;
$details = placesRequest(
    'GET',
    'https://places.googleapis.com/v1/places/' . rawurlencode($placeId),
    $apiKey,
    'displayName,rating,userRatingCount,photos,currentOpeningHours,websiteUri,types',
    null,
    $detailsFail
);

if ($details === null) {
    respond(502, ['success' => false, 'error' => 'upstream_error', 'debug' => $detailsFail]);
}

// ---------------------------------------------------------------------
// Score berechnen (0-100)
// ---------------------------------------------------------------------
$rating        = isset($details['rating']) ? (float) $details['rating'] : 0.0;
$reviewCount   = isset($details['userRatingCount']) ? (int) $details['userRatingCount'] : 0;
$photoCount    = is_array($details['photos'] ?? null) ? count($details['photos']) : 0;
$hasHours      = !empty($details['currentOpeningHours']);
$hasWebsite    = !empty($details['websiteUri']);
$hasCategories = !empty($details['types']);

$score = 0;
$score += $hasCategories ? 20 : 0;
$score += $hasHours ? 15 : 0;
$score += $hasWebsite ? 15 : 0;
$score += $photoCount >= 5 ? 20 : ($photoCount >= 3 ? 10 : 0);
$score += $reviewCount >= 10 ? 20 : ($reviewCount >= 5 ? 10 : ($reviewCount >= 1 ? 5 : 0));
$score += $rating >= 4.5 ? 10 : ($rating >= 4.0 ? 5 : 0);
$score = min(100, $score);

respond(200, [
    'success' => true,
    'company_name' => $details['displayName']['text'] ?? $company,
    'score' => $score,
    'rating' => $rating,
    'reviews' => $reviewCount,
    'photos' => $photoCount,
    'website' => $details['websiteUri'] ?? null,
    'completeness' => [
        'categories' => $hasCategories,
        'hours' => $hasHours,
        'website' => $hasWebsite,
        'photos' => $photoCount >= 5,
        'reviews' => $reviewCount >= 10,
    ],
]);
