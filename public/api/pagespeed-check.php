<?php
/**
 * PageSpeed-Check — server-seitige Route für das Live-Check-Widget auf
 * /webdesign/. Nimmt eine Internetadresse entgegen, lässt Google
 * PageSpeed Insights (Lighthouse, Strategie "mobile") die Seite wirklich
 * laden und liefert Performance-Score (0-100) sowie die Ladezeit bis zum
 * größten sichtbaren Inhalt (LCP) zurück. Der API-Key bleibt ausschließlich
 * hier auf dem Server, nie im Response-Body.
 *
 * PageSpeed Insights selbst ist KOSTENLOS (25.000 Anfragen/Tag im
 * Standardkontingent) — anders als beim GBP-Check in _guard.php geht es
 * hier also nicht um eine Google-Rechnung, sondern um Serverlast: Ein
 * einzelner Durchlauf dauert 10 bis 30 Sekunden und belegt so lange einen
 * PHP-Prozess. Deshalb ein EIGENES, von GBP komplett unabhängiges
 * Tagesbudget (guardConsumeNamedBudget) statt der geteilten Kostenbremse.
 *
 * Reihenfolge wie beim GBP-Check: Herkunft prüfen → nur POST → Menge pro
 * IP → Adresse validieren → Cache → Tagesbudget → Google.
 *
 * Diagnose (nur für den Betreiber): GET /api/pagespeed-check?diag=<Token>
 * führt einen echten Testaufruf gegen Google aus und zeigt die exakte
 * Fehlermeldung — siehe gbp-check.php für dasselbe Muster.
 */

declare(strict_types=1);

require __DIR__ . '/_guard.php';

// Ein realer Lighthouse-Durchlauf kann länger dauern als das übliche
// PHP-Zeitlimit auf Shared Hosting (oft 30s). set_time_limit() wirkt nur,
// wenn der Hoster es nicht ausdrücklich sperrt — best effort, kostet aber
// nichts, es zu versuchen. Der curl-Timeout unten ist die zweite,
// verlässlichere Grenze.
@set_time_limit(55);

// =====================================================================
// Stellschrauben — alle über .env änderbar (siehe .env.example).
// =====================================================================
function pscRateMax(): int      { return guardInt('PAGESPEED_RATE_MAX', 3); }
function pscRateWindow(): int   { return guardInt('PAGESPEED_RATE_WINDOW', 600); }
function pscDailyBudget(): int  { return guardInt('PAGESPEED_DAILY_BUDGET', 300); }
function pscCacheTtl(): int     { return guardInt('PAGESPEED_CACHE_TTL', 86400); }

// =====================================================================
// DIAGNOSE-MODUS (nur Betreiber): GET ?diag=<token>
// Bewusst ohne Herkunftsprüfung — siehe gbp-check.php für die Begründung.
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Cache-Control: no-store, max-age=0');
    header('X-Robots-Tag: noindex, noai, noimageai');

    $diagToken = envValue('PAGESPEED_DIAG_TOKEN');
    $provided  = isset($_GET['diag']) && is_string($_GET['diag']) ? $_GET['diag'] : '';

    if ($diagToken === null || $provided === '' || !hash_equals($diagToken, $provided)) {
        respond(404, ['success' => false, 'error' => 'not_found']);
    }

    guardRateLimit('pagespeed-diag');

    $apiKey = envValue('GOOGLE_PAGESPEED_API_KEY');
    if ($apiKey === null) {
        respond(200, [
            'diagnose' => true,
            'api_key_gefunden' => false,
            'hinweis' => 'GOOGLE_PAGESPEED_API_KEY ist auf dem Server nicht gesetzt. '
                . 'Entweder im Hostinger hPanel als Umgebungsvariable eintragen oder '
                . 'in die .env-Datei oberhalb des Web-Roots eintragen (dieselbe Datei '
                . 'wie GOOGLE_PLACES_API_KEY).',
        ]);
    }

    if (!guardConsumeNamedBudget('pagespeed', pscDailyBudget())) {
        respond(200, [
            'diagnose' => true,
            'api_key_gefunden' => true,
            'testaufruf' => 'uebersprungen',
            'hinweis' => 'Das Tagesbudget von ' . pscDailyBudget() . ' PageSpeed-Prüfungen ist '
                . 'aufgebraucht (oder der Datenordner ist nicht beschreibbar). Es wurde bewusst '
                . 'KEIN Aufruf gemacht. Morgen früh steht das Budget wieder zur Verfügung; '
                . 'dauerhaft ändern lässt es sich über PAGESPEED_DAILY_BUDGET in der .env.',
        ]);
    }

    // Echter Testaufruf gegen eine bekannte, garantiert erreichbare Adresse.
    $test = pscRunPagespeed('https://jungline.de/', $apiKey);

    if ($test['ok']) {
        respond(200, [
            'diagnose' => true,
            'api_key_gefunden' => true,
            'google_erreichbar' => true,
            'testaufruf' => 'erfolgreich',
            'score' => $test['score'],
            'hinweis' => 'Alles korrekt konfiguriert. Das Check-Widget sollte funktionieren.',
        ]);
    }

    respond(200, [
        'diagnose' => true,
        'api_key_gefunden' => true,
        'google_erreichbar' => $test['reason'] !== 'network_error',
        'testaufruf' => 'fehlgeschlagen',
        'http_status' => $test['status'],
        'google_fehler' => $test['message'],
        'haeufige_ursache' => pscDiagHint($test),
    ]);
}

/** Übersetzt die Google-Fehlermeldung in einen konkreten Handlungstipp. */
function pscDiagHint(array $test): string {
    $msg = mb_strtolower($test['message']);
    if (str_contains($msg, 'referer') || str_contains($msg, 'referrer')) {
        return 'Der API-Key ist auf HTTP-Referrer (Websites) eingeschränkt. Serverseitige '
            . 'Aufrufe haben keinen Referrer. Im Google-Cloud-Console unter "Anmeldedaten" '
            . 'die Key-Einschränkung auf "Keine" oder auf "IP-Adressen" (mit der Server-IP '
            . 'von Hostinger) umstellen.';
    }
    if (str_contains($msg, 'has not been used') || str_contains($msg, 'is disabled') || str_contains($msg, 'not been enabled')) {
        return 'Die "PageSpeed Insights API" ist im Projekt nicht aktiviert. In der '
            . 'Google-Cloud-Console unter "APIs & Dienste" > "Bibliothek" die '
            . '"PageSpeed Insights API" aktivieren.';
    }
    if (str_contains($msg, 'quota') || str_contains($msg, 'rate limit')) {
        return 'Das tägliche Kontingent von Google für diesen Key ist erschöpft (Standard: '
            . '25.000 Anfragen/Tag). Das ist bei normaler Nutzung praktisch ausgeschlossen — '
            . 'prüfen Sie, ob derselbe Key noch für etwas anderes verwendet wird.';
    }
    if (str_contains($msg, 'api key not valid') || str_contains($msg, 'api_key_invalid')) {
        return 'Der API-Key ist ungültig oder gehört zu einem anderen Projekt. Key in der '
            . 'Google-Cloud-Console prüfen und exakt übernehmen.';
    }
    if (str_contains($msg, 'permission')) {
        return 'Dem Key fehlt die Berechtigung für diese API. Prüfen: Ist die "PageSpeed '
            . 'Insights API" aktiviert und ist der Key nicht auf eine andere API eingeschränkt?';
    }
    return 'Bitte die Google-Fehlermeldung oben prüfen. Meist liegt es an Key-Einschränkung '
        . 'oder nicht aktivierter "PageSpeed Insights API".';
}

// =====================================================================
// NORMALER BETRIEB: POST vom Widget
// =====================================================================

// 1) Herkunft: nur jungline.de.
guardEnforceOrigin();

// 2) Nur POST.
guardRequirePost();

// 3) Menge pro IP: 3 Anfragen / 10 Minuten (niedriger als beim GBP-Check —
//    ein Durchlauf belegt bis zu 30 Sekunden lang einen PHP-Prozess).
guardRateLimit('pagespeed-check');

// ---------------------------------------------------------------------
// Eingabe lesen & streng validieren
// ---------------------------------------------------------------------
$input = guardReadJsonBody();
$normalized = pscNormalizeUrl(is_string($input['url'] ?? null) ? $input['url'] : '');

if ($normalized === null) {
    respond(400, ['success' => false, 'error' => 'invalid_url']);
}

// ---------------------------------------------------------------------
// 4) Cache: dieselbe Adresse kostet 24 h lang keinen neuen Durchlauf —
//    und der Besucher muss beim zweiten Mal nicht wieder 20 Sekunden warten.
// ---------------------------------------------------------------------
$cacheKey = 'pagespeed|' . $normalized;
$cached = guardCacheGet($cacheKey);
if (is_array($cached)) {
    $cached['cached'] = true;
    respond(200, $cached);
}

$apiKey = envValue('GOOGLE_PAGESPEED_API_KEY');
if ($apiKey === null) {
    error_log('pagespeed-check: GOOGLE_PAGESPEED_API_KEY fehlt oder ist leer');
    respond(500, ['success' => false, 'error' => 'server_not_configured']);
}

// ---------------------------------------------------------------------
// 5) Eigenes Tagesbudget buchen — unabhängig vom GBP-Kostenschutz.
// ---------------------------------------------------------------------
if (!guardConsumeNamedBudget('pagespeed', pscDailyBudget())) {
    respond(503, ['success' => false, 'error' => 'daily_limit_reached']);
}

// 6) Google fragen.
$result = pscRunPagespeed($normalized, $apiKey);

if (!$result['ok']) {
    respond(502, ['success' => false, 'error' => 'upstream_error']);
}

if ($result['score'] === null) {
    // Google hat geantwortet, konnte die Seite aber nicht prüfen (z. B.
    // DNS-Fehler, die Seite blockiert automatisierte Aufrufe). Kein
    // Serverfehler unsererseits — echtes "not_found"-Äquivalent, wird
    // ebenfalls gecacht, damit ein erneuter Versuch mit derselben kaputten
    // Adresse keinen weiteren Aufruf auslöst.
    $payload = ['success' => false, 'error' => 'could_not_check'];
    guardCachePut($cacheKey, $payload);
    respond(200, $payload);
}

$payload = [
    'success' => true,
    'url' => $normalized,
    'score' => $result['score'],
    'band' => $result['score'] >= 90 ? 'gut' : ($result['score'] >= 50 ? 'mittel' : 'schlecht'),
    'lcp_s' => $result['lcp_s'],
];

guardCachePut($cacheKey, $payload);

$payload['cached'] = false;
respond(200, $payload);

// =====================================================================
// Helfer
// =====================================================================

/**
 * Macht aus einer Besucher-Eingabe eine gültige, normalisierte URL oder
 * gibt null zurück. Ergänzt fehlendes "https://", damit auch die übliche
 * Eingabe "ihre-firma.de" ohne Protokoll funktioniert. Normalisierung
 * (Kleinschreibung bei Schema/Host, kein Fragment) sorgt dafür, dass
 * "Ihre-Firma.de/" und "ihre-firma.de" denselben Cache-Eintrag treffen.
 */
function pscNormalizeUrl(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '' || mb_strlen($raw) > 300) return null;
    if (preg_match('/[\x00-\x1F\x7F\s]/u', $raw)) return null;

    if (!preg_match('#^https?://#i', $raw)) $raw = 'https://' . $raw;

    $parts = parse_url($raw);
    if (!is_array($parts) || empty($parts['host'])) return null;

    $scheme = strtolower($parts['scheme'] ?? 'https');
    if (!in_array($scheme, ['http', 'https'], true)) return null;

    $host = strtolower($parts['host']);
    // Ein Punkt im Host trennt echte Domains von "localhost" & Co.
    if (!str_contains($host, '.') || str_contains($host, '..')) return null;

    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '';
    if ($path === '' ) $path = '/';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';

    return $scheme . '://' . $host . $port . $path . $query;
}

/**
 * Ruft PageSpeed Insights (Strategie "mobile", Kategorie "performance") auf
 * und liefert Score (0-100) plus LCP in Sekunden. score === null bedeutet
 * "Google hat geantwortet, konnte die Seite aber nicht auswerten" — vom
 * Aufrufer wie ein "kein Treffer" zu behandeln, nicht wie ein Fehler.
 *
 * Bewusst nur EINE Kategorie (Performance): Jede weitere Kategorie
 * verlängert die Laufzeit des Lighthouse-Durchlaufs zusätzlich — auf
 * Shared Hosting mit begrenztem PHP-Zeitlimit ist "zuverlässig eine Zahl"
 * mehr wert als "vier Zahlen, die manchmal an der Zeitgrenze scheitern".
 */
function pscRunPagespeed(string $url, string $apiKey): array {
    $query = http_build_query([
        'url' => $url,
        'key' => $apiKey,
        'category' => 'performance',
        'strategy' => 'mobile',
    ]);
    $ch = curl_init('https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . $query);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 50,
        CURLOPT_CONNECTTIMEOUT => 6,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        error_log('pagespeed-api: cURL-Fehler — ' . $curlError);
        return ['ok' => false, 'status' => 0, 'reason' => 'network_error', 'message' => $curlError, 'score' => null, 'lcp_s' => null];
    }

    $decoded = json_decode((string) $response, true);

    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
        $googleMessage = $decoded['error']['message'] ?? substr((string) $response, 0, 300);
        $googleStatus  = $decoded['error']['status'] ?? '';
        error_log('pagespeed-api: HTTP ' . $httpCode . ' — ' . $googleStatus . ' ' . $googleMessage);
        return ['ok' => false, 'status' => $httpCode, 'reason' => 'api_error', 'message' => trim($googleStatus . ' ' . $googleMessage), 'score' => null, 'lcp_s' => null];
    }

    // HTTP 200, aber Lighthouse konnte die Seite selbst nicht laden (z. B.
    // DNS-Fehler, Bot-Sperre). Kein Übertragungsfehler — ein gültiges
    // "nicht prüfbar".
    $runtimeError = $decoded['lighthouseResult']['runtimeError']['code'] ?? null;
    $scoreRaw = $decoded['lighthouseResult']['categories']['performance']['score'] ?? null;
    if ($runtimeError !== null || !is_numeric($scoreRaw)) {
        return ['ok' => true, 'status' => $httpCode, 'reason' => '', 'message' => '', 'score' => null, 'lcp_s' => null];
    }

    $lcpMs = $decoded['lighthouseResult']['audits']['largest-contentful-paint']['numericValue'] ?? null;

    return [
        'ok' => true,
        'status' => $httpCode,
        'reason' => '',
        'message' => '',
        'score' => (int) round(((float) $scoreRaw) * 100),
        'lcp_s' => is_numeric($lcpMs) ? round(((float) $lcpMs) / 1000, 1) : null,
    ];
}
