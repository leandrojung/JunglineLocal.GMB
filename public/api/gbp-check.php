<?php
/**
 * GBP-Profil-Check — server-seitige Route für das Startseiten-Badge.
 * Nimmt company/city/keyword entgegen, fragt die Google Places API (New)
 * ab und liefert einen Vollständigkeits-Score 0-100 zurück. Der API-Key
 * bleibt ausschließlich hier auf dem Server, nie im Response-Body.
 *
 * Diagnose (nur für den Betreiber): GET /api/gbp-check?diag=<GBP_DIAG_TOKEN>
 * führt einen echten Testaufruf gegen Google aus und zeigt die exakte
 * Fehlermeldung an — so lässt sich eine falsche Cloud-/Key-Konfiguration
 * ohne Programmierkenntnisse erkennen. Ist GBP_DIAG_TOKEN nicht gesetzt,
 * ist die Diagnose komplett deaktiviert.
 */

declare(strict_types=1);

// respond(), envValue(), placesRequest(), cleanField() — siehe _shared.php.
// API-Key nie im Frontend, nie im Repo: entweder echte PHP-Umgebungsvariable
// (z. B. im Hostinger hPanel gesetzt) oder .env-Datei OBERHALB des Web-Roots.
require __DIR__ . '/_shared.php';

// =====================================================================
// DIAGNOSE-MODUS (nur Betreiber): GET ?diag=<token>
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $diagToken = envValue('GBP_DIAG_TOKEN');
    $provided  = isset($_GET['diag']) ? (string) $_GET['diag'] : '';

    // Ohne konfiguriertes Token bzw. ohne passendes Token: keine Auskunft.
    if ($diagToken === null || $provided === '' || !hash_equals($diagToken, $provided)) {
        respond(404, ['success' => false, 'error' => 'not_found']);
    }

    $apiKey = envValue('GOOGLE_PLACES_API_KEY');
    if ($apiKey === null) {
        respond(200, [
            'diagnose' => true,
            'api_key_gefunden' => false,
            'hinweis' => 'GOOGLE_PLACES_API_KEY ist auf dem Server nicht gesetzt. '
                . 'Entweder im Hostinger hPanel als Umgebungsvariable eintragen oder '
                . 'eine .env-Datei oberhalb des Web-Roots (public_html/../.env) anlegen.',
        ]);
    }

    // Echter Testaufruf mit einem bekannten Ort.
    $test = placesRequest(
        'POST',
        'https://places.googleapis.com/v1/places:searchText',
        $apiKey,
        'places.id,places.displayName',
        ['textQuery' => 'Kölner Dom Köln', 'languageCode' => 'de', 'maxResultCount' => 1]
    );

    if ($test['ok']) {
        respond(200, [
            'diagnose' => true,
            'api_key_gefunden' => true,
            'google_erreichbar' => true,
            'testaufruf' => 'erfolgreich',
            'hinweis' => 'Alles korrekt konfiguriert. Das Badge sollte funktionieren.',
        ]);
    }

    respond(200, [
        'diagnose' => true,
        'api_key_gefunden' => true,
        'google_erreichbar' => $test['reason'] !== 'network_error',
        'testaufruf' => 'fehlgeschlagen',
        'http_status' => $test['status'],
        'google_fehler' => $test['message'],
        'haeufige_ursache' => diagHint($test),
    ]);
}

/**
 * Übersetzt die Google-Fehlermeldung in einen konkreten Handlungstipp.
 */
function diagHint(array $test): string {
    $msg = mb_strtolower($test['message']);
    if (str_contains($msg, 'referer') || str_contains($msg, 'referrer')) {
        return 'Der API-Key ist auf HTTP-Referrer (Websites) eingeschränkt. '
            . 'Serverseitige Aufrufe haben keinen Referrer. Im Google-Cloud-Console '
            . 'unter "Anmeldedaten" die Key-Einschränkung auf "Keine" oder auf '
            . '"IP-Adressen" (mit der Server-IP von Hostinger) umstellen.';
    }
    if (str_contains($msg, 'has not been used') || str_contains($msg, 'is disabled') || str_contains($msg, 'not been enabled')) {
        return 'Die "Places API (New)" ist im Projekt nicht aktiviert. In der '
            . 'Google-Cloud-Console unter "APIs & Dienste" > "Bibliothek" die '
            . '"Places API (New)" aktivieren (nicht nur die alte "Places API").';
    }
    if (str_contains($msg, 'billing')) {
        return 'Für das Cloud-Projekt ist keine aktive Abrechnung hinterlegt. '
            . 'Im Google-Cloud-Console unter "Abrechnung" ein Rechnungskonto mit '
            . 'dem Projekt verknüpfen.';
    }
    if (str_contains($msg, 'api key not valid') || str_contains($msg, 'api_key_invalid')) {
        return 'Der API-Key ist ungültig oder gehört zu einem anderen Projekt. '
            . 'Key in der Google-Cloud-Console prüfen und exakt übernehmen.';
    }
    if (str_contains($msg, 'permission')) {
        return 'Dem Key fehlt die Berechtigung für diese API. Prüfen: Ist die '
            . '"Places API (New)" aktiviert und ist der Key nicht auf eine andere '
            . 'API eingeschränkt?';
    }
    return 'Bitte die Google-Fehlermeldung oben prüfen. Meist liegt es an '
        . 'Key-Einschränkung, nicht aktivierter "Places API (New)" oder Abrechnung.';
}

// =====================================================================
// NORMALER BETRIEB: POST vom Badge
// =====================================================================
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

$company = cleanField($input, 'company');
$city    = cleanField($input, 'city');
$keyword = cleanField($input, 'keyword');

if ($company === null || $city === null || $keyword === null) {
    respond(400, ['success' => false, 'error' => 'missing_fields']);
}

$apiKey = envValue('GOOGLE_PLACES_API_KEY');
if ($apiKey === null) {
    error_log('gbp-check: GOOGLE_PLACES_API_KEY fehlt oder ist leer');
    respond(500, ['success' => false, 'error' => 'server_not_configured']);
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

// Aufruf selbst fehlgeschlagen (Konfig/Netz) — NICHT als "nicht gefunden"
// verschleiern, sonst bleibt eine falsche Google-Konfiguration unsichtbar.
if (!$searchResult['ok']) {
    respond(502, ['success' => false, 'error' => 'upstream_error']);
}

// Gültige Antwort, aber kein Treffer: echtes "not_found".
$placeId = $searchResult['data']['places'][0]['id'] ?? null;
if (!is_string($placeId) || $placeId === '') {
    respond(200, ['success' => false, 'error' => 'not_found']);
}

// 2) Place Details: die für den Score nötigen Felder abrufen
$detailsResult = placesRequest(
    'GET',
    'https://places.googleapis.com/v1/places/' . rawurlencode($placeId),
    $apiKey,
    'displayName,rating,userRatingCount,photos,currentOpeningHours,websiteUri,types'
);

if (!$detailsResult['ok']) {
    respond(502, ['success' => false, 'error' => 'upstream_error']);
}
$details = $detailsResult['data'];

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
    'place_id' => $placeId,
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
