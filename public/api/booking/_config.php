<?php
/**
 * Terminbuchung — zentrale Konfiguration.
 *
 * Alles, was Leandro ohne Programmierkenntnisse ändern können soll, steht
 * hier oben als Konstante. Zugangsdaten stehen NICHT hier, sondern in der
 * .env oberhalb des Web-Roots (siehe .env.example) — dieselbe Mechanik, die
 * schon der GBP-Check nutzt.
 *
 * Zeitzonen-Regel für das ganze Modul: gespeichert und gerechnet wird
 * ausschließlich in UTC, angezeigt und eingegeben ausschließlich in
 * Europe/Berlin. Jede Umrechnung passiert an genau einer Stelle
 * (bkLocal()/bkUtc()), damit die Sommerzeit nicht an fünf Stellen einzeln
 * richtig implementiert sein muss.
 */

declare(strict_types=1);

// respond() und envValue() teilen sich alle /api-Routen.
require_once __DIR__ . '/../_shared.php';

// ---- Fachliche Eckdaten -------------------------------------------------
const BK_TZ           = 'Europe/Berlin';
const BK_SLOT_MIN     = 30;      // Länge eines Termins in Minuten
const BK_LEAD_HOURS   = 12;      // frühestens so viele Stunden im Voraus buchbar
const BK_HORIZON_DAYS = 60;      // so weit reicht der Kalender in die Zukunft
const BK_WORKDAYS     = [1, 2, 3, 4, 5];   // ISO-8601: 1 = Montag … 7 = Sonntag
const BK_DAY_START    = '09:00';
const BK_DAY_END      = '17:00'; // letzter Slot beginnt BK_SLOT_MIN davor
const BK_BUFFER_MIN   = 0;       // Puffer vor/nach einem Termin

// Schutz gegen automatisierte Massenbuchungen: mehr als so viele
// Buchungen pro IP und Tag werden abgelehnt.
const BK_RATE_PER_IP_DAY = 3;

const BK_TITLE    = 'Kostenloses Erstgespräch — JunglineLocal';
const BK_DURATION_LABEL = '30 Minuten';

/** Deutsche Beschriftungen — der Kalender rendert serverunabhängig deutsch. */
const BK_WEEKDAYS_SHORT = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
const BK_MONTHS = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                   'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

/**
 * Feste Sperrtage (Urlaub, Feiertage) im Format 'YYYY-MM-DD'.
 * Diese Liste darf gefahrlos von Hand erweitert werden.
 */
const BK_BLOCKED_DAYS = [];

// ---- Abgeleitete Helfer -------------------------------------------------

function bkTz(): DateTimeZone {
    static $tz = null;
    return $tz ??= new DateTimeZone(BK_TZ);
}

function bkUtcTz(): DateTimeZone {
    static $tz = null;
    return $tz ??= new DateTimeZone('UTC');
}

/** UTC-Zeitpunkt → lokale Darstellung (Europe/Berlin, inkl. Sommerzeit). */
function bkLocal(DateTimeImmutable $utc): DateTimeImmutable {
    return $utc->setTimezone(bkTz());
}

/** Lokale Eingabe ('2026-08-11', '09:30') → UTC-Zeitpunkt. */
function bkUtc(string $date, string $time): ?DateTimeImmutable {
    $local = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, bkTz());
    if ($local === false) return null;
    // createFromFormat akzeptiert auch Unsinn wie '2026-02-31' und rollt ihn
    // weiter — der Rückvergleich fängt das ab.
    if ($local->format('Y-m-d H:i') !== $date . ' ' . $time) return null;
    return $local->setTimezone(bkUtcTz());
}

/** Einheitliches Speicherformat für Zeitpunkte. */
function bkStamp(DateTimeImmutable $utc): string {
    return $utc->setTimezone(bkUtcTz())->format('Y-m-d H:i:s');
}

function bkNow(): DateTimeImmutable {
    return new DateTimeImmutable('now', bkUtcTz());
}

/**
 * Datenordner für die Buchungen. Bevorzugt OBERHALB des Web-Roots — dort
 * kommt kein Besucher heran, egal wie der Server konfiguriert ist. Klappt
 * das nicht (manche Hoster erlauben nur Schreibzugriff innerhalb von
 * public_html), wird ein Ordner im Web-Root angelegt und per .htaccess
 * gesperrt.
 */
function bkDataDir(): string {
    static $dir = null;
    if ($dir !== null) return $dir;

    $candidates = array_filter([
        envValue('BOOKING_DATA_DIR'),
        __DIR__ . '/../../../.jungline-data',  // eine Ebene über public_html
        __DIR__ . '/../../.jungline-data',     // Notnagel: innerhalb des Web-Roots
    ]);

    foreach ($candidates as $candidate) {
        if (!is_dir($candidate) && !@mkdir($candidate, 0770, true) && !is_dir($candidate)) continue;
        if (!is_writable($candidate)) continue;

        // Für den Notnagel-Fall: Ordner hart gegen Auslieferung sperren.
        $htaccess = $candidate . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
        }
        return $dir = $candidate;
    }

    throw new RuntimeException('Kein beschreibbarer Datenordner gefunden.');
}

/** Der feste Videoraum, der in Mails und Kalendereintrag landet. */
function bkMeetingUrl(): string {
    return envValue('MEETING_URL') ?? '';
}

function bkOwnerEmail(): string {
    return envValue('OWNER_EMAIL') ?? 'Info@jungline.de';
}

function bkOwnerName(): string {
    return envValue('OWNER_NAME') ?? 'Leandro Jung — JunglineLocal';
}

function bkSiteUrl(): string {
    return rtrim(envValue('SITE_URL') ?? 'https://jungline.de', '/');
}

/** Absolute URL zur Absage-/Verschiebeseite eines Termins. */
function bkManageUrl(string $token): string {
    return bkSiteUrl() . '/api/booking/cancel?token=' . rawurlencode($token);
}

/** Kryptografisch sicherer Token für Absage-/Verschiebelinks. */
function bkToken(): string {
    return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
}

/** Datum lokal formatiert, z. B. "Dienstag, 11. August 2026". */
function bkFormatDate(DateTimeImmutable $utc): string {
    $local = bkLocal($utc);
    $days = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    return $days[(int) $local->format('N') - 1]
        . ', ' . $local->format('j') . '. '
        . BK_MONTHS[(int) $local->format('n') - 1] . ' '
        . $local->format('Y');
}

/** Uhrzeit-Spanne lokal, z. B. "09:30 – 10:00 Uhr". */
function bkFormatTime(DateTimeImmutable $startUtc, DateTimeImmutable $endUtc): string {
    return bkLocal($startUtc)->format('H:i') . ' – ' . bkLocal($endUtc)->format('H:i') . ' Uhr';
}
