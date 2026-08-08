<?php
/**
 * Terminbuchung — Google-Kalender-Abgleich über einen Service-Account.
 *
 * Zwei Aufgaben:
 *   1. freeBusy — belegte Zeiten aus Leandros Kalender holen, damit über die
 *      Website nichts gebucht werden kann, was dort schon steht.
 *   2. events.insert/delete — jede Buchung landet als echter Kalendereintrag
 *      und verschwindet bei einer Absage wieder.
 *
 * Bewusst OHNE composer/google-api-client: auf Shared-Hosting gibt es kein
 * Deploy mit vendor/-Ordner. Das JWT wird deshalb direkt mit openssl_sign
 * (RS256) signiert — das sind die 40 Zeilen unten, statt 12 MB Abhängigkeiten.
 *
 * WICHTIG — keine attendees: Ein Service-Account darf ohne
 * domainweite Delegation keine Gäste einladen; Google antwortet sonst mit
 * "Service accounts cannot invite attendees". Die Kontaktdaten des Kunden
 * stehen deshalb in der Beschreibung des Termins, nicht im Gästefeld. Die
 * Einladung an den Kunden verschickt ohnehin unsere eigene Bestätigungsmail
 * samt .ics-Anhang.
 *
 * Ausfallverhalten: Google ist nirgends kritischer Pfad. Fällt der Abgleich
 * aus, wird gebucht und gemailt wie sonst auch — der Fehler landet im
 * Server-Log und als Warnhinweis in Leandros Benachrichtigung.
 */

declare(strict_types=1);

require_once __DIR__ . '/_config.php';

const BK_GOOGLE_SCOPE = 'https://www.googleapis.com/auth/calendar';

/** Ist der Abgleich konfiguriert? Ohne Zugangsdaten läuft alles ohne Google. */
function bkGoogleEnabled(): bool {
    return bkGoogleCredentials() !== null && (envValue('GOOGLE_CALENDAR_ID') ?? '') !== '';
}

/**
 * Zugangsdaten des Service-Accounts: entweder als Pfad zur heruntergeladenen
 * JSON-Datei (empfohlen, liegt oberhalb des Web-Roots) oder als zwei
 * einzelne .env-Werte.
 */
function bkGoogleCredentials(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;

    $file = envValue('GOOGLE_SA_KEY_FILE');
    if ($file !== null && is_readable($file)) {
        $json = json_decode((string) file_get_contents($file), true);
        if (is_array($json) && !empty($json['client_email']) && !empty($json['private_key'])) {
            return $cache = ['email' => $json['client_email'], 'key' => $json['private_key']];
        }
        error_log('booking/google: Schlüsseldatei ' . $file . ' ist unbrauchbar.');
    }

    $email = envValue('GOOGLE_SA_EMAIL');
    $key   = envValue('GOOGLE_SA_PRIVATE_KEY');
    if ($email !== null && $key !== null) {
        // In .env steht der Schlüssel einzeilig mit \n als Text — hier zurück
        // in echte Zeilenumbrüche wandeln, sonst kann OpenSSL ihn nicht lesen.
        return $cache = ['email' => $email, 'key' => str_replace('\n', "\n", $key)];
    }

    return $cache = null;
}

function bkB64Url(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/**
 * Access-Token holen (und eine Stunde lang wiederverwenden). Ohne Cache
 * würde jede Slot-Abfrage einen zusätzlichen Google-Roundtrip kosten.
 */
function bkGoogleToken(): ?string {
    $creds = bkGoogleCredentials();
    if ($creds === null) return null;

    $cacheFile = bkDataDir() . '/google-token.json';
    if (is_readable($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && ($cached['expires'] ?? 0) > time() + 60) {
            return (string) $cached['token'];
        }
    }

    $now = time();
    $header = bkB64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim  = bkB64Url((string) json_encode([
        'iss'   => $creds['email'],
        'scope' => BK_GOOGLE_SCOPE,
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    $signature = '';
    if (!openssl_sign($header . '.' . $claim, $signature, $creds['key'], OPENSSL_ALGO_SHA256)) {
        error_log('booking/google: JWT-Signatur fehlgeschlagen — privater Schlüssel prüfen.');
        return null;
    }
    $jwt = $header . '.' . $claim . '.' . bkB64Url($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode((string) $response, true);
    if ($status !== 200 || !is_array($data) || empty($data['access_token'])) {
        error_log('booking/google: Token-Abruf HTTP ' . $status . ' — ' . substr((string) $response, 0, 300));
        return null;
    }

    @file_put_contents($cacheFile, (string) json_encode([
        'token'   => $data['access_token'],
        'expires' => $now + (int) ($data['expires_in'] ?? 3600),
    ]));
    @chmod($cacheFile, 0600);

    return (string) $data['access_token'];
}

/** Ein Aufruf gegen die Calendar-API. Rückgabe: [ok, status, data]. */
function bkGoogleRequest(string $method, string $url, ?array $body = null): array {
    $token = bkGoogleToken();
    if ($token === null) return ['ok' => false, 'status' => 0, 'data' => null];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('booking/google: Netzwerkfehler — ' . $error);
        return ['ok' => false, 'status' => 0, 'data' => null];
    }
    $data = json_decode((string) $response, true);
    if ($status < 200 || $status >= 300) {
        error_log('booking/google: ' . $method . ' HTTP ' . $status . ' — ' . substr((string) $response, 0, 300));
        return ['ok' => false, 'status' => $status, 'data' => is_array($data) ? $data : null];
    }
    return ['ok' => true, 'status' => $status, 'data' => is_array($data) ? $data : []];
}

/**
 * Belegte Zeitfenster aus dem Google-Kalender, im selben Format wie bkBusy().
 * Bei einem Fehler bewusst eine leere Liste: der Kalender zeigt dann die
 * eigenen freien Zeiten statt gar nichts. Ein stiller Ausfall ist hier das
 * kleinere Übel gegenüber einer Buchungsseite, die keine Termine mehr anbietet.
 */
function bkGoogleBusy(DateTimeImmutable $fromUtc, DateTimeImmutable $toUtc): array {
    if (!bkGoogleEnabled()) return [];

    $calendarId = (string) envValue('GOOGLE_CALENDAR_ID');
    $result = bkGoogleRequest('POST', 'https://www.googleapis.com/calendar/v3/freeBusy', [
        'timeMin' => $fromUtc->format(DateTimeInterface::RFC3339),
        'timeMax' => $toUtc->format(DateTimeInterface::RFC3339),
        'timeZone' => 'UTC',
        'items' => [['id' => $calendarId]],
    ]);
    if (!$result['ok']) return [];

    $periods = $result['data']['calendars'][$calendarId]['busy'] ?? [];
    $busy = [];
    foreach ($periods as $p) {
        if (empty($p['start']) || empty($p['end'])) continue;
        try {
            $busy[] = [
                'start' => bkStamp(new DateTimeImmutable($p['start'])),
                'end'   => bkStamp(new DateTimeImmutable($p['end'])),
            ];
        } catch (Exception) {
            continue;
        }
    }
    return $busy;
}

/** Legt den Termin im Kalender an. Rückgabe: Event-ID oder '' bei Fehler. */
function bkGoogleInsert(array $booking): string {
    if (!bkGoogleEnabled()) return '';

    $calendarId = (string) envValue('GOOGLE_CALENDAR_ID');
    $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());
    $end   = new DateTimeImmutable($booking['end_utc'], bkUtcTz());

    $lines = [
        'Gebucht über jungline.de',
        '',
        'Name:     ' . $booking['name'],
        'E-Mail:   ' . $booking['email'],
        'Telefon:  ' . ($booking['phone'] !== '' ? $booking['phone'] : '—'),
        'Firma:    ' . ($booking['company'] !== '' ? $booking['company'] : '—'),
        '',
        'Anliegen:',
        $booking['message'] !== '' ? $booking['message'] : '—',
        '',
        'Termin absagen/verschieben: ' . bkManageUrl($booking['token']),
    ];

    $event = [
        'summary' => 'Erstgespräch: ' . $booking['name'] . ($booking['company'] !== '' ? ' (' . $booking['company'] . ')' : ''),
        'description' => implode("\n", $lines),
        'start' => ['dateTime' => $start->format(DateTimeInterface::RFC3339), 'timeZone' => 'UTC'],
        'end'   => ['dateTime' => $end->format(DateTimeInterface::RFC3339), 'timeZone' => 'UTC'],
    ];
    if (bkMeetingUrl() !== '') {
        $event['location'] = bkMeetingUrl();
    }

    $result = bkGoogleRequest(
        'POST',
        'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendarId) . '/events',
        $event
    );
    return $result['ok'] ? (string) ($result['data']['id'] ?? '') : '';
}

/** Entfernt den Kalendereintrag wieder (Absage). */
function bkGoogleDelete(string $eventId): bool {
    if ($eventId === '' || !bkGoogleEnabled()) return false;
    $calendarId = (string) envValue('GOOGLE_CALENDAR_ID');
    $result = bkGoogleRequest(
        'DELETE',
        'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendarId)
            . '/events/' . rawurlencode($eventId)
    );
    // 410 = war schon gelöscht; für uns dasselbe Ergebnis.
    return $result['ok'] || $result['status'] === 410;
}
