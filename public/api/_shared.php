<?php
/**
 * Gemeinsame Helfer für die /api-Endpunkte: JSON-Antworten, Laden des
 * Google-API-Keys (Server-Umgebungsvariable oder .env oberhalb des
 * Web-Roots) und der cURL-Aufruf gegen die Places API (New).
 */

declare(strict_types=1);

function respond(int $status, array $body): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function envValue(string $name): ?string {
    $fromEnv = getenv($name);
    if ($fromEnv !== false && trim($fromEnv) !== '') return trim($fromEnv);

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
            if (trim($key) === $name) {
                $value = trim(trim($value), "\"'");
                if ($value !== '') return $value;
            }
        }
    }
    return null;
}

/**
 * Liefert IMMER ein strukturiertes Ergebnis zurück, damit der Aufrufer
 * zwischen "Aufruf fehlgeschlagen" (Konfig/Netz) und "gültige Antwort
 * ohne Treffer" unterscheiden kann.
 */
function placesRequest(string $method, string $url, string $apiKey, string $fieldMask, ?array $body = null): array {
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
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        error_log('places-api: cURL-Fehler — ' . $curlError);
        return ['ok' => false, 'status' => 0, 'data' => null, 'reason' => 'network_error', 'message' => $curlError];
    }

    $decoded = json_decode((string) $response, true);

    if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
        $googleMessage = $decoded['error']['message'] ?? substr((string) $response, 0, 300);
        $googleStatus  = $decoded['error']['status'] ?? '';
        error_log('places-api: HTTP ' . $httpCode . ' — ' . $googleStatus . ' ' . $googleMessage);
        return [
            'ok' => false,
            'status' => $httpCode,
            'data' => null,
            'reason' => 'api_error',
            'message' => trim($googleStatus . ' ' . $googleMessage),
        ];
    }

    return ['ok' => true, 'status' => $httpCode, 'data' => $decoded, 'reason' => '', 'message' => ''];
}

function cleanField(array $input, string $key, int $maxLen = 150): ?string {
    $value = isset($input[$key]) ? trim((string) $input[$key]) : '';
    if ($value === '' || mb_strlen($value) > $maxLen) return null;
    return $value;
}
