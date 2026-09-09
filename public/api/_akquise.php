<?php
/**
 * Akquise-Konsole — gemeinsame Grundlage der beiden Endpunkte
 * /api/akquise (Speicher) und /api/akquise-ki (Textbausteine).
 *
 * Hier steht alles, was beide brauchen: der Ablageort der Daten, die
 * Prüfung der Webnummer und die Sperre gegen das Durchprobieren.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

/** SHA-256 der Webnummer. Der Klartext steht bewusst NICHT im Repository. */
const AKQ_NUMMER_HASH = 'e6f185efb4408ce465b242052a45c495c787645e56072f34cc39a3ada22510d9';

/** Fehlversuche pro IP, bis die Nummer eine Stunde lang gesperrt ist. */
const AKQ_VERSUCHE_MAX = 12;
const AKQ_VERSUCHE_FENSTER = 3600;

/** Obergrenze für den Datenstand. Schützt die Platte vor einem Endlos-Upload. */
const AKQ_MAX_BYTES = 4000000;

// ---------------------------------------------------------------------
// Ablageort — dieselbe Logik wie bei der Terminbuchung, damit beides im
// selben Datenordner oberhalb von public_html landet.
// ---------------------------------------------------------------------

function akqDataDir(): ?string {
    static $dir = false;
    if ($dir !== false) return $dir;

    $candidates = array_filter([
        envValue('AKQUISE_DATA_DIR'),
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
    return $dir = null;
}

function akqDatei(): ?string {
    $dir = akqDataDir();
    return $dir === null ? null : $dir . '/akquise.json';
}

// ---------------------------------------------------------------------
// Zugang
// ---------------------------------------------------------------------

function akqIp(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) && $ip !== '' ? $ip : 'unbekannt';
}

/** Zählt Fehlversuche pro IP. Liefert false, wenn gesperrt ist. */
function akqVersuchErlaubt(): bool {
    $dir = akqDataDir();
    if ($dir === null) return true; // ohne Speicher keine Sperre — die Nummer prüft trotzdem

    $datei = $dir . '/akquise-versuche.json';
    $jetzt = time();
    $roh = is_file($datei) ? (string) @file_get_contents($datei) : '';
    $alle = json_decode($roh, true);
    if (!is_array($alle)) $alle = [];

    // Abgelaufene Einträge wegräumen, damit die Datei nicht wächst.
    foreach ($alle as $ip => $eintrag) {
        if (!is_array($eintrag) || ($eintrag['bis'] ?? 0) < $jetzt) unset($alle[$ip]);
    }

    $ich = $alle[akqIp()] ?? null;
    return !is_array($ich) || (int) ($ich['n'] ?? 0) < AKQ_VERSUCHE_MAX;
}

function akqVersuchNotieren(): void {
    $dir = akqDataDir();
    if ($dir === null) return;

    $datei = $dir . '/akquise-versuche.json';
    $jetzt = time();
    $roh = is_file($datei) ? (string) @file_get_contents($datei) : '';
    $alle = json_decode($roh, true);
    if (!is_array($alle)) $alle = [];
    foreach ($alle as $ip => $eintrag) {
        if (!is_array($eintrag) || ($eintrag['bis'] ?? 0) < $jetzt) unset($alle[$ip]);
    }

    $ip = akqIp();
    $ich = is_array($alle[$ip] ?? null) ? $alle[$ip] : ['n' => 0, 'bis' => $jetzt + AKQ_VERSUCHE_FENSTER];
    $ich['n'] = (int) $ich['n'] + 1;
    $ich['bis'] = $jetzt + AKQ_VERSUCHE_FENSTER;
    $alle[$ip] = $ich;

    @file_put_contents($datei, json_encode($alle), LOCK_EX);
}

/**
 * Prüft die Webnummer. hash_equals statt == , damit die Antwortzeit nichts
 * über die richtige Nummer verrät.
 */
function akqNummerOk(string $nummer): bool {
    $nummer = trim($nummer);
    if ($nummer === '') return false;

    $klartext = envValue('AKQUISE_NUMMER');
    if ($klartext !== null) return hash_equals($klartext, $nummer);

    return hash_equals(AKQ_NUMMER_HASH, hash('sha256', $nummer));
}
