<?php
/**
 * GBP-Profil-Check — server-seitige Route für das Startseiten-Badge.
 * Nimmt company/city/keyword entgegen, fragt die Google Places API (New)
 * ab und liefert einen Vollständigkeits-Score 0-100 zurück. Der API-Key
 * bleibt ausschließlich hier auf dem Server, nie im Response-Body.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $body): never {
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
    $fromEnv = getenv('GOOGLE_PLACES_API_KEY');
    if ($fromEnv !== false && $fromEnv !== '') return $fromEnv;

    $candidates = [
        __DIR__ . '/../../.env',
        __DIR__ . '/../../../.env',
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
function placesRequest(string $method, string $url, string $apiKey, string $fieldMask, ?array $body = null): ?array {
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
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error !== '') {
        error_log('gbp-check: cURL-Fehler — ' . $error);
        return null;
    }
    $decoded = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
        error_log('gbp-check: Places API HTTP ' . $httpCode . ' — ' . substr((string) $response, 0, 300));
        return null;
    }
    return $decoded;
}

// 1) Text Search: erstes passendes Profil finden
$searchResult = placesRequest(
    'POST',
    'https://places.googleapis.com/v1/places:searchText',
    $apiKey,
    'places.id,places.displayName,places.formattedAddress',
    [
        'textQuery' => $company . ' ' . $keyword . ' ' . $city,
        'languageCode' => 'de',
        'maxResultCount' => 1,
    ]
);

$placeId = $searchResult['places'][0]['id'] ?? null;
if (!is_string($placeId) || $placeId === '') {
    respond(200, ['success' => false, 'error' => 'not_found']);
}

// 2) Place Details: die für den Score nötigen Felder abrufen
$details = placesRequest(
    'GET',
    'https://places.googleapis.com/v1/places/' . rawurlencode($placeId),
    $apiKey,
    'displayName,rating,userRatingCount,photos,currentOpeningHours,websiteUri,types'
);

if ($details === null) {
    respond(502, ['success' => false, 'error' => 'upstream_error']);
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
