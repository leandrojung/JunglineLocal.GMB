<?php
/**
 * GBP-Wettbewerbsvergleich — server-seitige Route für den zweiten Block
 * unter dem Vollständigkeits-Badge. Sucht per Places API (New) Text Search
 * bis zu 20 Treffer für Keyword+Stadt (Standard-Relevanzsortierung, kein
 * rankPreference gesetzt), identifiziert die eigene Firma darin (zuerst per
 * placeId aus der vorherigen Detailsuche, sonst per Namens-Fuzzy-Match) und
 * liefert Top-3 + eigene Position + Rohdaten für die Gap-Analyse zurück.
 *
 * Kein Scraping von Google-Suchergebnissen/Maps — ausschließlich die
 * offizielle Places API (New).
 *
 * Ergebnisse werden 24h pro Keyword+Stadt-Kombination gecacht (nicht pro
 * Firma), da die Trefferliste für alle Suchenden mit demselben
 * Keyword+Stadt identisch ist — das begrenzt die API-Kosten unabhängig
 * von der Anzahl der Aufrufer.
 */

declare(strict_types=1);

require __DIR__ . '/_shared.php';

const CACHE_TTL_SECONDS = 24 * 60 * 60;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'error' => 'method_not_allowed']);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    respond(400, ['success' => false, 'error' => 'invalid_body']);
}

$company = cleanField($input, 'company');
$city    = cleanField($input, 'city');
$keyword = cleanField($input, 'keyword');
$placeId = isset($input['place_id']) ? trim((string) $input['place_id']) : '';

if ($company === null || $city === null || $keyword === null) {
    respond(400, ['success' => false, 'error' => 'missing_fields']);
}

// ---------------------------------------------------------------------
// Normalisierung für den Namens-Fuzzy-Match: Kleinschreibung, deutsche
// Umlaute transliteriert, gängige Rechtsform-Zusätze entfernt (die sich
// zwischen dem eingegebenen Firmennamen und Googles displayName oft
// unterscheiden), Sonderzeichen entfernt.
// ---------------------------------------------------------------------
function normalizeCompanyName(string $name): string {
    $n = mb_strtolower($name, 'UTF-8');
    $n = strtr($n, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $legalForms = [
        'gmbh & co kg', 'gmbh und co kg', 'gmbh & co kgaa', 'ug haftungsbeschraenkt',
        'gmbh', 'ug', 'ohg', 'kgaa', 'kg', 'ag', 'e k', 'ek', 'e v', 'ev', 'gbr',
        'inhaber', 'inh', 'co kg', '& co', 'co',
    ];
    $n = preg_replace('/[^a-z0-9\s]/', ' ', $n) ?? $n;
    foreach ($legalForms as $form) {
        $n = preg_replace('/\b' . preg_quote($form, '/') . '\b/', ' ', $n) ?? $n;
    }
    $n = preg_replace('/\s+/', ' ', $n) ?? $n;
    return trim($n);
}

function nameTokens(string $normalized): array {
    return array_values(array_filter(explode(' ', $normalized), fn($t) => $t !== ''));
}

/**
 * Wort-Mengen-Containment statt Zeichen-Ähnlichkeit: gibt true zurück, wenn
 * ALLE Wörter des einen Namens im anderen vorkommen (in irgendeiner
 * Richtung). Ein reiner Zeichen-Ähnlichkeitswert (z. B. similar_text)
 * täuscht bei kurzen Namen mit gemeinsamem Gattungsbegriff — "Autohaus
 * Weber" vs. "Autohaus Meyer" landet dabei über 85 %, obwohl es zwei
 * verschiedene Firmen sind. Containment verlangt, dass auch das
 * unterscheidende Wort (Weber/Meyer) übereinstimmt, und lässt trotzdem
 * "Mustermann" in "Mustermann Dachdecker GmbH" zuverlässig durch.
 */
function nameTokensMatch(array $a, array $b): bool {
    if (empty($a) || empty($b)) return false;
    $setA = array_unique($a);
    $setB = array_unique($b);
    return count(array_diff($setA, $setB)) === 0 || count(array_diff($setB, $setA)) === 0;
}

$apiKey = envValue('GOOGLE_PLACES_API_KEY');
if ($apiKey === null) {
    error_log('gbp-compare: GOOGLE_PLACES_API_KEY fehlt oder ist leer');
    respond(500, ['success' => false, 'error' => 'server_not_configured']);
}

// ---------------------------------------------------------------------
// Cache: ein JSON-File pro Keyword+Stadt-Kombination, 24h gültig.
// ---------------------------------------------------------------------
$cacheKey = sha1(mb_strtolower(trim($keyword), 'UTF-8') . '|' . mb_strtolower(trim($city), 'UTF-8'));
$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';

$places = null;
$cached = false;

if (is_file($cacheFile)) {
    $raw = file_get_contents($cacheFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded) && isset($decoded['fetched_at'], $decoded['places'])
        && (time() - (int) $decoded['fetched_at']) < CACHE_TTL_SECONDS) {
        $places = $decoded['places'];
        $cached = true;
    }
}

if ($places === null) {
    // rankPreference bewusst NICHT gesetzt — Default RELEVANCE entspricht am
    // ehesten Googles eigener Relevanz-/Prominenz-Sortierung im Local Pack.
    $searchResult = placesRequest(
        'POST',
        'https://places.googleapis.com/v1/places:searchText',
        $apiKey,
        'places.id,places.displayName,places.rating,places.userRatingCount',
        [
            'textQuery' => $keyword . ' ' . $city,
            'languageCode' => 'de',
            'maxResultCount' => 20,
        ]
    );

    if (!$searchResult['ok']) {
        respond(502, ['success' => false, 'error' => 'upstream_error']);
    }

    $rawPlaces = $searchResult['data']['places'] ?? [];
    $places = [];
    foreach ($rawPlaces as $p) {
        $id = $p['id'] ?? null;
        $name = $p['displayName']['text'] ?? null;
        if (!is_string($id) || $id === '' || !is_string($name) || $name === '') continue;
        $places[] = [
            'id' => $id,
            'name' => $name,
            'rating' => isset($p['rating']) ? (float) $p['rating'] : 0.0,
            'review_count' => isset($p['userRatingCount']) ? (int) $p['userRatingCount'] : 0,
        ];
    }

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }
    @file_put_contents($cacheFile, json_encode([
        'fetched_at' => time(),
        'places' => $places,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

// ---------------------------------------------------------------------
// Eigene Firma in der Liste identifizieren.
// ---------------------------------------------------------------------
$ownIndex = null;
$matchedBy = null;

if ($placeId !== '') {
    foreach ($places as $i => $p) {
        if ($p['id'] === $placeId) { $ownIndex = $i; $matchedBy = 'place_id'; break; }
    }
}

if ($ownIndex === null) {
    $companyTokens = nameTokens(normalizeCompanyName($company));
    foreach ($places as $i => $p) {
        if (nameTokensMatch($companyTokens, nameTokens(normalizeCompanyName($p['name'])))) {
            $ownIndex = $i;
            $matchedBy = 'name_match';
            break;
        }
    }
}

$top3 = array_map(
    fn(array $p) => ['name' => $p['name'], 'rating' => $p['rating'], 'review_count' => $p['review_count']],
    array_slice($places, 0, 3)
);

$own = ['found' => false, 'matched_by' => null, 'position' => null, 'outside_top20' => count($places) > 0, 'name' => null, 'rating' => null, 'review_count' => null];
if ($ownIndex !== null) {
    $own = [
        'found' => true,
        'matched_by' => $matchedBy,
        'position' => $ownIndex + 1,
        'outside_top20' => false,
        'name' => $places[$ownIndex]['name'],
        'rating' => $places[$ownIndex]['rating'],
        'review_count' => $places[$ownIndex]['review_count'],
    ];
}

respond(200, [
    'success' => true,
    'cached' => $cached,
    'keyword' => $keyword,
    'city' => $city,
    'result_count' => count($places),
    'top3' => $top3,
    'own' => $own,
]);
