<?php
/**
 * Reine Diagnose-Route: prüft, ob vom Hosting-Panel gesetzte Umgebungs-
 * variablen bei PHP zur Laufzeit überhaupt ankommen. Das Zugangs-Passwort
 * steht bewusst FEST im Code (nicht aus getenv()) — sonst könnten wir
 * genau das Problem, das wir untersuchen wollen, nicht diagnostizieren.
 *
 * Danach wieder löschen bzw. den Zugangscode ändern.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const DIAG_SECRET = 'jl-env-diag-2026-xk9';

$provided = $_GET['secret'] ?? '';
if (!hash_equals(DIAG_SECRET, (string) $provided)) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

function mask(?string $v): string {
    if ($v === null || $v === '') return '(leer / nicht gesetzt)';
    $len = strlen($v);
    if ($len <= 6) return str_repeat('*', $len) . " (Länge $len)";
    return substr($v, 0, 3) . str_repeat('*', $len - 6) . substr($v, -3) . " (Länge $len)";
}

$envCandidates = [
    __DIR__ . '/../../.env',
    __DIR__ . '/../../../.env',
];
$envFiles = [];
foreach ($envCandidates as $path) {
    if (!is_file($path)) {
        $envFiles[$path] = 'nicht vorhanden';
    } else {
        $envFiles[$path] = is_readable($path) ? 'vorhanden & lesbar' : 'vorhanden, aber NICHT lesbar';
    }
}

// ---------------------------------------------------------------------
// Echter Testaufruf gegen die Places API (New) — mit dem Key, der laut
// getenv() beim Server ankommt. Zeigt die exakte Google-Fehlermeldung.
// ---------------------------------------------------------------------
$apiKey = getenv('GOOGLE_PLACES_API_KEY') ?: null;
$googleTest = null;

if ($apiKey !== null) {
    $ch = curl_init('https://places.googleapis.com/v1/places:searchText');
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiKey,
            'X-Goog-FieldMask: places.id,places.displayName',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'textQuery' => 'Kölner Dom Köln',
            'languageCode' => 'de',
            'maxResultCount' => 1,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        $googleTest = ['ergebnis' => 'netzwerkfehler', 'curl_fehler' => $curlError];
    } else {
        $decoded = json_decode((string) $response, true);
        if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
            $googleTest = [
                'ergebnis' => 'erfolgreich',
                'http_status' => $httpCode,
                'treffer' => $decoded['places'][0]['displayName']['text'] ?? '(kein Treffer, aber Aufruf war ok)',
            ];
        } else {
            $googleTest = [
                'ergebnis' => 'fehlgeschlagen',
                'http_status' => $httpCode,
                'google_fehler_status' => $decoded['error']['status'] ?? null,
                'google_fehler_message' => $decoded['error']['message'] ?? substr((string) $response, 0, 500),
            ];
        }
    }
}

echo json_encode([
    'php_version' => PHP_VERSION,
    'quelle_getenv' => [
        'GOOGLE_PLACES_API_KEY' => mask(getenv('GOOGLE_PLACES_API_KEY') ?: null),
        'GBP_DIAG_TOKEN' => mask(getenv('GBP_DIAG_TOKEN') ?: null),
    ],
    'quelle_dollar_SERVER' => [
        'GOOGLE_PLACES_API_KEY' => mask($_SERVER['GOOGLE_PLACES_API_KEY'] ?? null),
        'GBP_DIAG_TOKEN' => mask($_SERVER['GBP_DIAG_TOKEN'] ?? null),
    ],
    'quelle_dollar_ENV' => [
        'GOOGLE_PLACES_API_KEY' => mask($_ENV['GOOGLE_PLACES_API_KEY'] ?? null),
        'GBP_DIAG_TOKEN' => mask($_ENV['GBP_DIAG_TOKEN'] ?? null),
    ],
    'env_dateien_auf_platte' => $envFiles,
    'google_testaufruf' => $googleTest,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
