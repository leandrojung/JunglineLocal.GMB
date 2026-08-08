<?php
/**
 * Google-Diagnose — /api/booking/gtest?token=<BOOKING_CRON_TOKEN>
 *
 * Der Kalenderabgleich schlägt fehl, aber die eigentliche Fehlermeldung von
 * Google steht nur im Server-Log — und das ist auf diesem Hosting nicht
 * einsehbar. Diese Route führt jeden Schritt der Kette einzeln aus und zeigt
 * Googles Antwort im Klartext:
 *
 *   1. Schlüsseldatei finden und lesen
 *   2. JWT signieren und gegen ein Access-Token tauschen
 *   3. Kalender abrufen        — sieht das Dienstkonto den Kalender überhaupt?
 *   4. freeBusy                — darf es belegte Zeiten lesen?
 *   5. Testeintrag anlegen und sofort wieder löschen — darf es schreiben?
 *
 * Die typischen Ursachen unterscheiden sich genau in diesen Antworten:
 * "notFound" bei Schritt 3 heißt fehlende Kalender-Freigabe (Google versteckt
 * nicht freigegebene Kalender, statt "verboten" zu sagen), ein 403 mit
 * "accessNotConfigured" heißt, die Calendar API ist im falschen Projekt
 * aktiviert, und ein Fehler bei Schritt 2 liegt an der Schlüsseldatei selbst.
 *
 * Geheimnisse bleiben geheim: weder der private Schlüssel noch das Token
 * erscheinen in der Ausgabe. Zugriff nur mit demselben Token wie der Cron.
 */

declare(strict_types=1);

require_once __DIR__ . '/_google.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

$expected = envValue('BOOKING_CRON_TOKEN');
$provided = (string) ($_GET['token'] ?? '');
if ($expected === null || $provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(404);
    echo "not_found\n";
    exit;
}

/** Ein API-Aufruf mit sichtbarem Ergebnis: Status und Antwort-Auszug. */
function gtRequest(string $method, string $url, ?string $accessToken, $body = null): array {
    $headers = ['Content-Type: application/json'];
    if ($accessToken !== null) $headers[] = 'Authorization: Bearer ' . $accessToken;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            is_string($body) ? $body : (string) json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) return ['status' => 0, 'data' => null, 'raw' => 'Netzwerkfehler: ' . $curlErr];
    $data = json_decode((string) $response, true);
    return ['status' => $status, 'data' => is_array($data) ? $data : null, 'raw' => (string) $response];
}

function gtShow(array $r): string {
    $error = $r['data']['error'] ?? null;
    if (is_array($error)) {
        $reason = $error['errors'][0]['reason'] ?? ($error['status'] ?? '');
        return 'HTTP ' . $r['status'] . ' — ' . $reason . ': ' . ($error['message'] ?? '');
    }
    if (is_string($error)) {
        return 'HTTP ' . $r['status'] . ' — ' . $error . ': ' . ($r['data']['error_description'] ?? '');
    }
    return 'HTTP ' . $r['status'];
}

echo "Google-Kalender-Diagnose " . date('d.m.Y H:i:s') . "\n";
echo str_repeat('=', 68) . "\n\n";

// ---- 1) Schlüsseldatei --------------------------------------------------
echo "1) Schlüsseldatei\n";
echo "   konfiguriert: " . (envValue('GOOGLE_SA_KEY_FILE') ?? '(nicht gesetzt)') . "\n";
foreach (bkGoogleKeyCandidates() as $candidate) {
    echo "   " . (is_readable($candidate) ? 'LESBAR      ' : 'nicht lesbar') . "  " . $candidate . "\n";
}
$creds = bkGoogleCredentials();
$calendarId = envValue('GOOGLE_CALENDAR_ID') ?? '';
echo "   Kalender-ID:  " . ($calendarId !== '' ? $calendarId : '(nicht gesetzt)') . "\n";
if ($creds === null) {
    echo "\n   ERGEBNIS: Keine brauchbaren Zugangsdaten — hier ist Schluss.\n";
    echo "   Die JSON-Datei muss an einem der oben geprüften Orte liegen.\n";
    exit;
}
echo "   Dienstkonto:  " . $creds['email'] . "\n\n";

// ---- 2) Token-Austausch -------------------------------------------------
echo "2) Access-Token holen\n";
$now = time();
$jwtHeader = bkB64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
$jwtClaim  = bkB64Url((string) json_encode([
    'iss' => $creds['email'], 'scope' => BK_GOOGLE_SCOPE,
    'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
]));
$signature = '';
if (!openssl_sign($jwtHeader . '.' . $jwtClaim, $signature, $creds['key'], OPENSSL_ALGO_SHA256)) {
    echo "   ERGEBNIS: Signatur fehlgeschlagen — der private Schlüssel in der\n";
    echo "   Datei ist beschädigt. Datei neu von Google herunterladen.\n";
    exit;
}
$tokenResult = gtRequest('POST', 'https://oauth2.googleapis.com/token', null, http_build_query([
    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
    'assertion'  => $jwtHeader . '.' . $jwtClaim . '.' . bkB64Url($signature),
]));
$accessToken = $tokenResult['data']['access_token'] ?? null;
if (!is_string($accessToken) || $accessToken === '') {
    echo "   " . gtShow($tokenResult) . "\n";
    echo "   Auszug: " . substr($tokenResult['raw'], 0, 300) . "\n";
    echo "\n   ERGEBNIS: Google lehnt die Anmeldung ab — siehe Meldung oben.\n";
    exit;
}
echo "   OK — Token erhalten (" . strlen($accessToken) . " Zeichen, wird nicht angezeigt)\n\n";

$base = 'https://www.googleapis.com/calendar/v3';

// ---- 3) Kalender sichtbar? ---------------------------------------------
echo "3) Kalender abrufen\n";
$cal = gtRequest('GET', $base . '/calendars/' . rawurlencode($calendarId), $accessToken);
echo "   " . gtShow($cal) . "\n";
if ($cal['status'] === 404) {
    echo "\n   ERGEBNIS: Google meldet 'nicht gefunden'. Das heißt fast immer:\n";
    echo "   Der Kalender ist NICHT für das Dienstkonto freigegeben. In den\n";
    echo "   Kalender-Einstellungen unter 'Für bestimmte Personen freigeben'\n";
    echo "   muss stehen: " . $creds['email'] . "\n";
    echo "   mit Berechtigung 'Änderungen an Terminen vornehmen'.\n";
    exit;
}
if ($cal['status'] !== 200) {
    echo "   Auszug: " . substr($cal['raw'], 0, 400) . "\n";
    exit;
}
echo "   OK — Kalender: " . ($cal['data']['summary'] ?? '?') . "\n\n";

// ---- 4) Belegte Zeiten lesen -------------------------------------------
echo "4) freeBusy (nächste 7 Tage)\n";
$fb = gtRequest('POST', $base . '/freeBusy', $accessToken, [
    'timeMin' => gmdate('Y-m-d\TH:i:s\Z'),
    'timeMax' => gmdate('Y-m-d\TH:i:s\Z', time() + 7 * 86400),
    'timeZone' => 'UTC',
    'items' => [['id' => $calendarId]],
]);
$busy = $fb['data']['calendars'][$calendarId]['busy'] ?? null;
echo "   " . gtShow($fb) . ($busy !== null ? ' — ' . count($busy) . ' belegte Zeitfenster' : '') . "\n\n";

// ---- 5) Schreiben: Testeintrag anlegen und löschen ----------------------
echo "5) Testeintrag anlegen und wieder löschen\n";
$insert = gtRequest('POST', $base . '/calendars/' . rawurlencode($calendarId) . '/events', $accessToken, [
    'summary' => 'Diagnose jungline.de — bitte ignorieren',
    'start' => ['dateTime' => gmdate('Y-m-d\TH:i:s\Z', time() + 120), 'timeZone' => 'UTC'],
    'end'   => ['dateTime' => gmdate('Y-m-d\TH:i:s\Z', time() + 300), 'timeZone' => 'UTC'],
]);
echo "   Anlegen: " . gtShow($insert) . "\n";
$eventId = $insert['data']['id'] ?? null;
if (is_string($eventId) && $eventId !== '') {
    $delete = gtRequest('DELETE', $base . '/calendars/' . rawurlencode($calendarId)
        . '/events/' . rawurlencode($eventId), $accessToken);
    echo "   Löschen: HTTP " . $delete['status'] . "\n\n";
    echo str_repeat('=', 68) . "\n";
    echo "ERGEBNIS: Alles funktioniert. Buchungen landen ab jetzt im Kalender.\n";
} else {
    echo "   Auszug: " . substr($insert['raw'], 0, 400) . "\n\n";
    echo str_repeat('=', 68) . "\n";
    echo "ERGEBNIS: Lesen klappt, Schreiben nicht — die Freigabe steht\n";
    echo "vermutlich auf 'Termine anzeigen' statt 'Änderungen vornehmen'.\n";
}
