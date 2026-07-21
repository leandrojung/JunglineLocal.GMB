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
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
