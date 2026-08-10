<?php
/**
 * Schutzschicht für die öffentlichen /api-Endpunkte, die kostenpflichtige
 * Google-Aufrufe auslösen.
 *
 * Das Problem, das diese Datei löst: Hinter /api/gbp-check.php steht ein
 * Google-Places-Key, den Google pro Aufruf abrechnet. Der Endpunkt war ohne
 * jede Begrenzung erreichbar. Ein einzelnes Skript mit einer for-Schleife
 * hätte über Nacht eine vierstellige Rechnung erzeugen können — ganz ohne
 * Hacking, allein durch Aufrufen.
 *
 * Vier Sperren, absichtlich hintereinander geschaltet, damit jede einzelne
 * ausfallen darf:
 *
 *   1. HERKUNFT   — Anfragen ohne passenden Origin/Referer werden abgewiesen.
 *                   Hält Skripte fern, die den Endpunkt direkt ansprechen.
 *   2. MENGE/IP   — 5 Anfragen pro 10 Minuten pro IP-Adresse.
 *                   Hält den einzelnen Neugierigen im Rahmen.
 *   3. TAGESBUDGET— Gesamtzahl der Google-Aufrufe pro Tag ist gedeckelt.
 *                   Die eigentliche Kostenbremse: Sie greift auch dann,
 *                   wenn 1 und 2 umgangen werden (verteilte IPs, gefälschter
 *                   Referer). Ist das Budget aufgebraucht, wird KEIN Google-
 *                   Aufruf mehr gemacht — die Rechnung kann nicht weiter
 *                   wachsen, egal was passiert.
 *   4. CACHE      — Identische Abfragen werden 24 h lang aus einer Datei
 *                   beantwortet und kosten nichts.
 *
 * Grundregel bei Speicherproblemen: Lässt sich weder Zähler noch Budget
 * schreiben, wird der Google-Aufruf VERWEIGERT, nicht durchgewunken. Ein
 * kaputter Zähler darf nicht zur offenen Kasse werden.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

// ---------------------------------------------------------------------
// Stellschrauben. Alle über die .env oberhalb des Web-Roots änderbar,
// ohne den Code anzufassen.
// ---------------------------------------------------------------------

/** Anfragen pro IP im Zeitfenster. */
const GUARD_RATE_MAX = 5;

/** Länge des Zeitfensters in Sekunden (10 Minuten). */
const GUARD_RATE_WINDOW = 600;

/**
 * Google-Aufrufe pro Kalendertag (Europe/Berlin) über ALLE Besucher.
 *
 * Achtung beim Interpretieren: Ein vollständiger Check verbraucht bis zu
 * DREI Aufrufe (Textsuche + Detailabruf im Profil-Check, Textsuche im
 * Wettbewerbsvergleich). 200 Aufrufe entsprechen also rund 65 bis 70
 * vollständigen Checks pro Tag durch neue Besucher — Wiederholungen und
 * gleiche Suchen laufen über den Cache und zählen gar nicht mit.
 */
const GUARD_DAILY_BUDGET = 200;

/** Gültigkeitsdauer des Antwort-Caches in Sekunden (24 h). */
const GUARD_CACHE_TTL = 86400;

/** Maximale Größe des Request-Bodys in Bytes. */
const GUARD_MAX_BODY = 4096;

function guardInt(string $envName, int $default): int {
    $raw = envValue($envName);
    if ($raw === null || !preg_match('/^\d+$/', $raw)) return $default;
    $value = (int) $raw;
    return $value > 0 ? $value : $default;
}

function guardRateMax(): int     { return guardInt('GBP_RATE_MAX', GUARD_RATE_MAX); }
function guardRateWindow(): int  { return guardInt('GBP_RATE_WINDOW', GUARD_RATE_WINDOW); }
function guardDailyBudget(): int { return guardInt('GBP_DAILY_BUDGET', GUARD_DAILY_BUDGET); }
function guardCacheTtl(): int    { return guardInt('GBP_CACHE_TTL', GUARD_CACHE_TTL); }

// =====================================================================
// 1) HERKUNFT: Origin / Referer prüfen und CORS restriktiv setzen
// =====================================================================

/**
 * Die einzigen Herkünfte, von denen Anfragen angenommen werden.
 * Kein Wildcard — "*" würde jeder fremden Website erlauben, den Endpunkt
 * per JavaScript im Browser ihrer Besucher zu benutzen.
 */
function guardAllowedOrigins(): array {
    $origins = ['https://jungline.de', 'https://www.jungline.de'];

    // Zusätzliche Herkünfte für die lokale Entwicklung (Vite-Dev-Server).
    // Nur wirksam, wenn die Anfrage selbst nicht auf der Live-Domain
    // ankommt — auf jungline.de kann darüber nichts freigeschaltet werden.
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1')) {
        foreach ([5173, 4173, 8000, 8080, 3000] as $port) {
            $origins[] = 'http://localhost:' . $port;
            $origins[] = 'http://127.0.0.1:' . $port;
        }
    }

    $extra = envValue('GBP_EXTRA_ORIGIN');
    if ($extra !== null) $origins[] = rtrim($extra, '/');

    return $origins;
}

/** "https://jungline.de/pfad?x=1" → "https://jungline.de" */
function guardOriginOf(string $url): ?string {
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return null;
    $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
    if (!empty($parts['port'])) $origin .= ':' . $parts['port'];
    return $origin;
}

/**
 * Prüft die Herkunft, setzt die CORS-Header und beantwortet den
 * CORS-Vorabflug (OPTIONS). Bricht mit 403 ab, wenn die Anfrage nicht von
 * jungline.de stammt.
 *
 * Warum Origin UND Referer: Bei einem POST vom eigenen Formular schicken
 * alle aktuellen Browser einen Origin-Header. Ältere Browser und einzelne
 * Datenschutz-Erweiterungen tun das nicht, senden aber weiterhin einen
 * Referer. Einer von beiden muss passen — fehlen beide, ist es kein
 * Formular auf jungline.de, sondern ein Skript.
 */
function guardEnforceOrigin(): void {
    $allowed = guardAllowedOrigins();
    $origin  = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

    // Antworten dieser Route dürfen von keinem Cache und keinem anderen
    // Ursprung wiederverwendet werden.
    header('Vary: Origin');
    header('Cache-Control: no-store, max-age=0');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Robots-Tag: noindex, noai, noimageai');

    $matched = null;
    if ($origin !== '' && in_array($origin, $allowed, true)) {
        $matched = $origin;
    } elseif ($origin === '' && $referer !== '') {
        $refOrigin = guardOriginOf($referer);
        if ($refOrigin !== null && in_array($refOrigin, $allowed, true)) $matched = $refOrigin;
    }

    if ($matched !== null && $origin !== '') {
        header('Access-Control-Allow-Origin: ' . $matched);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 600');
    }

    // CORS-Vorabflug: beantworten, bevor irgendetwas geprüft oder gezählt wird.
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code($matched !== null ? 204 : 403);
        exit;
    }

    if ($matched === null) {
        respond(403, ['success' => false, 'error' => 'forbidden_origin']);
    }
}

// =====================================================================
// Ablage: bevorzugt oberhalb des Web-Roots
// =====================================================================

/**
 * Ordner für Zähler, Budget und Cache. Reihenfolge wie bei der
 * Terminbuchung: erst außerhalb des Web-Roots, dann innerhalb (dann aber
 * per .htaccess gesperrt), zuletzt das Temp-Verzeichnis des Servers.
 *
 * Gibt null zurück, wenn nichts beschreibbar ist. Der Aufrufer muss diesen
 * Fall als "kein Google-Aufruf" behandeln, nicht als "unbegrenzt".
 */
function guardDataDir(string $sub = 'gbp'): ?string {
    // Ein Cache pro Unterordner statt eines einzelnen Werts: Seit dem
    // PageSpeed-Check ruft dieselbe Funktion mit unterschiedlichen $sub-Werten
    // in derselben Anfrage auf. array_key_exists() statt isset(), weil ein
    // einmal ermitteltes "kein Ordner beschreibbar" (null) dauerhaft als
    // ermittelt gelten muss und nicht bei jedem Aufruf erneut versucht wird.
    static $dirs = [];
    if (array_key_exists($sub, $dirs)) return $dirs[$sub];

    $override = envValue('API_DATA_DIR');
    // Für den Standardfall 'gbp' bleibt der Override-Ordner UNVERÄNDERT ein
    // vollständiger Pfad, exakt wie vor dieser Erweiterung — sonst würde sich
    // bei einer bereits laufenden Installation mit gesetztem API_DATA_DIR
    // stillschweigend der Speicherort ändern. Jeder weitere Aufrufer (z. B.
    // 'pagespeed') bekommt seinen Unterordner an den Override gehängt, damit
    // sich zwei Funktionen nicht denselben Override-Ordner teilen.
    $overrideCandidate = $override === null ? null
        : ($sub === 'gbp' ? $override : rtrim($override, '/') . '/' . $sub);

    $candidates = array_filter([
        $overrideCandidate,
        __DIR__ . '/../../../.jungline-data/' . $sub,   // eine Ebene über public_html
        __DIR__ . '/../../.jungline-data/' . $sub,      // Notnagel: im Web-Root
        sys_get_temp_dir() . '/jungline-' . $sub,       // letzte Rettung
    ]);

    foreach ($candidates as $candidate) {
        if (!is_dir($candidate) && !@mkdir($candidate, 0770, true) && !is_dir($candidate)) continue;
        if (!is_writable($candidate)) continue;

        // Falls der Ordner im Web-Root gelandet ist: hart gegen Auslieferung
        // sperren. Die .htaccess im Web-Root tut das bereits über den
        // Punkt-Präfix; das hier ist der Gürtel zum Hosenträger.
        $htaccess = $candidate . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
        }
        return $dirs[$sub] = $candidate;
    }

    error_log('guard: kein beschreibbarer Datenordner gefunden (' . $sub . ') — Aufrufe werden blockiert');
    return $dirs[$sub] = null;
}

/**
 * Führt $fn unter exklusiver Dateisperre auf einer JSON-Datei aus.
 * $fn bekommt den aktuellen Inhalt und gibt den neuen zurück (oder null,
 * wenn nichts geschrieben werden soll). Rückgabewert von guardLocked ist
 * das, was $fn über den Referenzparameter $result meldet.
 */
function guardLocked(string $file, callable $fn): bool {
    $handle = @fopen($file, 'c+');
    if ($handle === false) return false;
    if (!flock($handle, LOCK_EX)) { fclose($handle); return false; }

    $raw = stream_get_contents($handle);
    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($data)) $data = [];

    $new = $fn($data);

    if (is_array($new)) {
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($handle);
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return true;
}

// =====================================================================
// 2) MENGE PRO IP
// =====================================================================

/**
 * IP des Anfragenden. Hostinger reicht die echte Adresse in REMOTE_ADDR
 * durch; steht davor ein Proxy/CDN, liefert dieser sie in einem eigenen
 * Header. Weitergehende Header werden NICHT ausgewertet, weil sie sich
 * frei fälschen lassen und damit jede Begrenzung aushebeln würden.
 */
function guardClientIp(): string {
    $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unbekannt';
}

/**
 * Gleitendes Zeitfenster: Es zählen alle Anfragen der letzten
 * guardRateWindow() Sekunden. Anders als ein Zähler, der zu jeder vollen
 * Stunde zurückgesetzt wird, lässt sich das nicht durch Warten auf die
 * nächste Runde umgehen.
 *
 * Bricht mit 429 ab, wenn das Limit erreicht ist.
 */
function guardRateLimit(string $bucket): void {
    $dir = guardDataDir();
    if ($dir === null) {
        respond(503, ['success' => false, 'error' => 'service_unavailable']);
    }

    $rlDir = $dir . '/rl';
    if (!is_dir($rlDir)) @mkdir($rlDir, 0770, true);

    $max    = guardRateMax();
    $window = guardRateWindow();
    $now    = time();
    $file   = $rlDir . '/' . sha1($bucket . '|' . guardClientIp()) . '.json';

    $blocked = false;
    $ok = guardLocked($file, function (array $data) use ($now, $window, $max, &$blocked): ?array {
        $hits = array_values(array_filter(
            is_array($data['hits'] ?? null) ? $data['hits'] : [],
            static fn($t) => is_int($t) && $t > $now - $window
        ));

        if (count($hits) >= $max) {
            $blocked = true;
            return null;   // nichts schreiben: ein abgewiesener Versuch
        }                  // darf das Fenster nicht weiter verlängern
        $hits[] = $now;
        return ['hits' => $hits];
    });

    if (!$ok) {
        error_log('gbp-guard: Zählerdatei nicht beschreibbar (' . $file . ')');
        respond(503, ['success' => false, 'error' => 'service_unavailable']);
    }
    if ($blocked) {
        header('Retry-After: ' . $window);
        respond(429, ['success' => false, 'error' => 'rate_limited', 'retry_after' => $window]);
    }

    guardCleanup($rlDir, $window * 4);
}

/** Löscht gelegentlich abgelaufene Dateien, damit der Ordner nicht wächst. */
function guardCleanup(string $dir, int $maxAge): void {
    if (random_int(1, 50) !== 1) return;   // ~2 % der Aufrufe
    $now = time();
    foreach (glob($dir . '/*.json') ?: [] as $path) {
        if (@filemtime($path) < $now - $maxAge) @unlink($path);
    }
}

// =====================================================================
// 3) TAGESBUDGET — die eigentliche Kostenbremse
// =====================================================================

/**
 * Bucht $calls Google-Aufrufe auf das Tagesbudget. Gibt false zurück, wenn
 * das Budget dafür nicht mehr reicht — dann darf KEIN Aufruf stattfinden.
 *
 * Gebucht wird VOR dem Aufruf. Ein fehlgeschlagener Aufruf verbraucht damit
 * ebenfalls Budget. Das ist Absicht: Wenn Google reihenweise Fehler liefert,
 * soll die Seite nicht in einer Endlosschleife weiter anfragen.
 */
function guardConsumeBudget(int $calls): bool {
    $dir = guardDataDir();
    if ($dir === null) return false;

    $budget = guardDailyBudget();
    $today  = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    $file   = $dir . '/budget-' . $today . '.json';

    $granted = false;
    $ok = guardLocked($file, function (array $data) use ($calls, $budget, &$granted): ?array {
        $used = isset($data['used']) && is_int($data['used']) ? $data['used'] : 0;
        if ($used + $calls > $budget) return null;
        $granted = true;
        return ['used' => $used + $calls];
    });

    if (!$ok) {
        error_log('gbp-guard: Budgetdatei nicht beschreibbar (' . $file . ')');
        return false;
    }
    if (!$granted) {
        error_log('gbp-guard: Tagesbudget von ' . $budget . ' Google-Aufrufen erreicht');
    }

    // Budgetdateien vergangener Tage aufräumen.
    guardCleanupBudget($dir, $today);

    return $granted;
}

function guardCleanupBudget(string $dir, string $today): void {
    if (random_int(1, 50) !== 1) return;
    foreach (glob($dir . '/budget-*.json') ?: [] as $path) {
        if (basename($path) !== 'budget-' . $today . '.json' && @filemtime($path) < time() - 7 * 86400) {
            @unlink($path);
        }
    }
}

/**
 * Wie guardConsumeBudget(), aber als eigenständiger, benannter Zähler für
 * Funktionen AUSSERHALB des GBP-Kostenschutzes — z. B. den PageSpeed-Check.
 * Eigener Datenordner (guardDataDir($bucket)) und eigene Budgetdatei: teilt
 * sich absichtlich NICHTS mit guardConsumeBudget()/GBP. Ein Ansturm auf den
 * einen Check darf das Tagesbudget des anderen nicht mit aufbrauchen — anders
 * als bei GBP geht es hier meist nicht um eine Google-Rechnung (PageSpeed
 * Insights ist kostenlos), sondern um Serverlast: Ein einzelner Durchlauf
 * belegt bis zu 30 Sekunden lang einen PHP-Prozess.
 */
function guardConsumeNamedBudget(string $bucket, int $max, int $calls = 1): bool {
    $dir = guardDataDir($bucket);
    if ($dir === null) return false;

    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    $file  = $dir . '/budget-' . $today . '.json';

    $granted = false;
    $ok = guardLocked($file, function (array $data) use ($calls, $max, &$granted): ?array {
        $used = isset($data['used']) && is_int($data['used']) ? $data['used'] : 0;
        if ($used + $calls > $max) return null;
        $granted = true;
        return ['used' => $used + $calls];
    });

    if (!$ok) {
        error_log('guard: Budgetdatei nicht beschreibbar (' . $file . ')');
        return false;
    }
    if (!$granted) {
        error_log('guard: Tagesbudget "' . $bucket . '" (' . $max . ') erreicht');
    }

    guardCleanupBudget($dir, $today);
    return $granted;
}

// =====================================================================
// 4) CACHE
// =====================================================================

function guardCacheDir(): ?string {
    $dir = guardDataDir();
    if ($dir === null) return null;
    $cache = $dir . '/cache';
    if (!is_dir($cache) && !@mkdir($cache, 0770, true) && !is_dir($cache)) return null;
    return $cache;
}

/** Gibt den gespeicherten Wert zurück oder null, wenn er fehlt/veraltet ist. */
function guardCacheGet(string $key): mixed {
    $dir = guardCacheDir();
    if ($dir === null) return null;

    $file = $dir . '/' . sha1($key) . '.json';
    if (!is_file($file)) return null;

    $raw = @file_get_contents($file);
    $data = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($data) || !isset($data['fetched_at'], $data['value'])) return null;
    if (time() - (int) $data['fetched_at'] >= guardCacheTtl()) return null;

    return $data['value'];
}

function guardCachePut(string $key, mixed $value): void {
    $dir = guardCacheDir();
    if ($dir === null) return;

    $file = $dir . '/' . sha1($key) . '.json';
    // Erst in eine Nachbardatei schreiben, dann umbenennen: Ein zweiter
    // Aufruf im selben Moment liest damit nie eine halb geschriebene Datei.
    $tmp = $file . '.' . getmypid() . '.tmp';
    $payload = json_encode(['fetched_at' => time(), 'value' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload !== false && @file_put_contents($tmp, $payload) !== false) {
        @rename($tmp, $file);
    }
    guardCleanup($dir, guardCacheTtl() * 2);
}

// =====================================================================
// Eingaben
// =====================================================================

/** Nur POST. Alles andere ist kein Formular auf jungline.de. */
function guardRequirePost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        respond(405, ['success' => false, 'error' => 'method_not_allowed']);
    }
}

/**
 * Liest den JSON-Body mit harter Größenbegrenzung. Ohne sie könnte jemand
 * 50 MB schicken und damit den PHP-Speicher füllen, bevor überhaupt eine
 * Prüfung greift.
 */
function guardReadJsonBody(): array {
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > GUARD_MAX_BODY) {
        respond(413, ['success' => false, 'error' => 'payload_too_large']);
    }

    $raw = file_get_contents('php://input', false, null, 0, GUARD_MAX_BODY + 1);
    if ($raw === false || strlen($raw) > GUARD_MAX_BODY) {
        respond(413, ['success' => false, 'error' => 'payload_too_large']);
    }

    $input = json_decode($raw, true);
    if (!is_array($input)) {
        respond(400, ['success' => false, 'error' => 'invalid_body']);
    }
    return $input;
}

/**
 * Strenge Feldprüfung für Firmenname, Stadt und Keyword.
 *
 * Erlaubt sind Buchstaben (inkl. Umlaute und Akzente), Ziffern, Leerzeichen
 * und die in Firmennamen üblichen Zeichen: . , - & + ' / ( ). Alles andere
 * — Steuerzeichen, Zeilenumbrüche, spitze Klammern, geschweifte Klammern,
 * Backslashes, Semikolons — wird ABGELEHNT statt stillschweigend entfernt.
 * Diese Werte wandern in eine Google-Suchanfrage; je enger die Eingabe,
 * desto weniger lässt sich damit anstellen.
 */
function guardCleanField(array $input, string $key, int $minLen = 2, int $maxLen = 120): ?string {
    if (!isset($input[$key]) || !is_string($input[$key])) return null;

    $value = trim($input[$key]);
    if ($value === '') return null;

    // Steuerzeichen (inkl. Zeilenumbruch und Tab) sind nie legitim.
    if (preg_match('/[\x00-\x1F\x7F]/u', $value)) return null;

    // Mehrfache Leerzeichen zusammenfassen.
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    $len = mb_strlen($value, 'UTF-8');
    if ($len < $minLen || $len > $maxLen) return null;

    if (!preg_match('/^[\p{L}\p{N}\s\.\,\-\&\+\'’´`\/\(\)ß]+$/u', $value)) return null;

    // Mindestens ein Buchstabe oder eine Ziffer — reine Satzzeichenketten
    // sind keine Firmennamen.
    if (!preg_match('/[\p{L}\p{N}]/u', $value)) return null;

    return $value;
}
