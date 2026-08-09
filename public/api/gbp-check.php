<?php
/**
 * GBP-Profil-Check — server-seitige Route für das Startseiten-Badge.
 * Nimmt company/city/keyword entgegen, fragt die Google Places API (New)
 * ab und liefert einen Vollständigkeits-Score 0-100 zurück. Der API-Key
 * bleibt ausschließlich hier auf dem Server, nie im Response-Body.
 *
 * Diese Route kostet bei jedem Durchlauf echtes Geld (Google rechnet pro
 * Aufruf ab). Der komplette Schutz davor steckt in _guard.php; hier wird er
 * nur in der richtigen Reihenfolge angewendet:
 *
 *   Herkunft prüfen → nur POST → Menge pro IP → Cache → Tagesbudget → Google
 *
 * Der Cache steht bewusst VOR dem Budget: Eine Frage, die schon einmal
 * gestellt wurde, wird auch dann noch beantwortet, wenn das Tagesbudget
 * längst aufgebraucht ist. Sie kostet ja nichts.
 *
 * Diagnose (nur für den Betreiber): GET /api/gbp-check?diag=<GBP_DIAG_TOKEN>
 * führt einen echten Testaufruf gegen Google aus und zeigt die exakte
 * Fehlermeldung an — so lässt sich eine falsche Cloud-/Key-Konfiguration
 * ohne Programmierkenntnisse erkennen. Ist GBP_DIAG_TOKEN nicht gesetzt,
 * ist die Diagnose komplett deaktiviert.
 */

declare(strict_types=1);

// respond(), envValue(), placesRequest() — siehe _shared.php.
// guard*() — siehe _guard.php.
// API-Key nie im Frontend, nie im Repo: entweder echte PHP-Umgebungsvariable
// (z. B. im Hostinger hPanel gesetzt) oder .env-Datei OBERHALB des Web-Roots.
require __DIR__ . '/_guard.php';

// =====================================================================
// DIAGNOSE-MODUS (nur Betreiber): GET ?diag=<token>
//
// Bewusst ohne Herkunftsprüfung — der Betreiber ruft die Adresse direkt im
// Browser auf, dabei gibt es keinen Origin. Stattdessen: geheimes Token,
// eigene Mengenbegrenzung und dasselbe Tagesbudget wie der Normalbetrieb.
// Ohne diese Begrenzung wäre die Diagnose ein zweiter, ungeschützter Weg
// zu kostenpflichtigen Google-Aufrufen.
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Cache-Control: no-store, max-age=0');
    header('X-Robots-Tag: noindex, noai, noimageai');

    $diagToken = envValue('GBP_DIAG_TOKEN');
    $provided  = isset($_GET['diag']) && is_string($_GET['diag']) ? $_GET['diag'] : '';

    // Ohne konfiguriertes Token bzw. ohne passendes Token: keine Auskunft.
    // hash_equals vergleicht in konstanter Zeit — ein Angreifer kann das
    // Token nicht Zeichen für Zeichen über die Antwortzeit erraten.
    if ($diagToken === null || $provided === '' || !hash_equals($diagToken, $provided)) {
        respond(404, ['success' => false, 'error' => 'not_found']);
    }

    guardRateLimit('gbp-diag');

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

    if (!guardConsumeBudget(1)) {
        respond(200, [
            'diagnose' => true,
            'api_key_gefunden' => true,
            'testaufruf' => 'uebersprungen',
            'hinweis' => 'Das Tagesbudget von ' . guardDailyBudget() . ' Google-Aufrufen ist '
                . 'aufgebraucht (oder der Datenordner ist nicht beschreibbar). Es wurde '
                . 'bewusst KEIN Aufruf gemacht. Morgen früh steht das Budget wieder zur '
                . 'Verfügung; dauerhaft ändern lässt es sich über GBP_DAILY_BUDGET in der .env.',
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

// 1) Herkunft: nur jungline.de. Setzt zugleich die CORS-Header (ohne
//    Wildcard) und beantwortet den CORS-Vorabflug.
guardEnforceOrigin();

// 2) Nur POST.
guardRequirePost();

// 3) Menge pro IP: 5 Anfragen / 10 Minuten.
guardRateLimit('gbp-check');

// ---------------------------------------------------------------------
// Eingabe lesen & streng validieren
// ---------------------------------------------------------------------
$input = guardReadJsonBody();

$company = guardCleanField($input, 'company');
$city    = guardCleanField($input, 'city');
$keyword = guardCleanField($input, 'keyword');

if ($company === null || $city === null || $keyword === null) {
    respond(400, ['success' => false, 'error' => 'missing_fields']);
}

// ---------------------------------------------------------------------
// 4) Cache: dieselbe Frage kostet 24 h lang nichts mehr.
//
// Der Schlüssel ist bewusst normalisiert (Kleinschreibung, zusammen-
// gefasste Leerzeichen), damit "Bäckerei Müller" und "bäckerei  müller"
// denselben Eintrag treffen und nicht zweimal bezahlt werden.
// ---------------------------------------------------------------------
$queryKey = 'gbp-check|' . mb_strtolower($company . '|' . $city . '|' . $keyword, 'UTF-8');

$cachedPayload = guardCacheGet($queryKey);
if (is_array($cachedPayload)) {
    $cachedPayload['cached'] = true;
    respond(200, $cachedPayload);
}

$apiKey = envValue('GOOGLE_PLACES_API_KEY');
if ($apiKey === null) {
    error_log('gbp-check: GOOGLE_PLACES_API_KEY fehlt oder ist leer');
    respond(500, ['success' => false, 'error' => 'server_not_configured']);
}

// ---------------------------------------------------------------------
// 5) Tagesbudget für die Textsuche buchen. Reicht es nicht, wird Google
//    gar nicht erst gefragt — das ist der Punkt, an dem die Rechnung
//    aufhört zu wachsen.
// ---------------------------------------------------------------------
if (!guardConsumeBudget(1)) {
    respond(503, ['success' => false, 'error' => 'daily_limit_reached']);
}

// 5a) Text Search: erstes passendes Profil finden
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
    // Auch das Nicht-Ergebnis wird gecacht: Wer denselben Tippfehler noch
    // dreimal abschickt, löst dafür keinen weiteren Google-Aufruf aus.
    guardCachePut($queryKey, ['success' => false, 'error' => 'not_found']);
    respond(200, ['success' => false, 'error' => 'not_found']);
}

// ---------------------------------------------------------------------
// 5b) Place Details — mit eigenem Cache pro Place-ID.
//
// Zwei verschiedene Suchen können beim selben Profil landen (z. B. mit und
// ohne Rechtsform im Namen). Der Detail-Cache hängt deshalb an der
// Place-ID, nicht an der Sucheingabe: Der zweite Weg zum selben Profil ist
// dann kostenlos.
// ---------------------------------------------------------------------
$detailsKey = 'gbp-place|' . $placeId;
$details = guardCacheGet($detailsKey);

if (!is_array($details)) {
    if (!guardConsumeBudget(1)) {
        respond(503, ['success' => false, 'error' => 'daily_limit_reached']);
    }

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
    guardCachePut($detailsKey, $details);
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

$payload = [
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
];

guardCachePut($queryKey, $payload);

$payload['cached'] = false;
respond(200, $payload);
