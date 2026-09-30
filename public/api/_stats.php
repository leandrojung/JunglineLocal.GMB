<?php
/**
 * Besucherstatistik ohne Cookies und ohne personenbezogene Daten.
 *
 * WARUM SELBST GEBAUT
 * -------------------
 * Die Seite soll messen können, was wirkt (wird der Profil-Check genutzt,
 * wie viele öffnen den Kalender, kostet der Startscreen Besucher?) — ohne
 * Einwilligungsbanner. Das geht nur, wenn nichts auf dem Gerät gespeichert
 * oder ausgelesen wird (§ 25 TDDDG) und nichts einer Person zuzuordnen ist.
 * Fremde Dienste (Google Analytics & Co.) scheiden damit aus; ein eigener
 * Zähler auf dem eigenen Server ist die einfachste Lösung, die das erfüllt.
 *
 * WAS GESPEICHERT WIRD
 * --------------------
 * Ausschließlich Tageszähler: Seitenaufrufe je Pfad, Herkunft (Suchmaschine,
 * soziales Netz, andere Website — nur der Hostname), Gerätetyp (Telefon oder
 * größer) und benannte Ereignisse aus einer festen Liste. KEINE IP-Adresse,
 * keine Kennung, kein Zeitstempel einzelner Aufrufe, keine vollständigen
 * Adressen. Ein Aufruf ist im Nachhinein keinem Besucher zuzuordnen —
 * deshalb gibt es auch keine "eindeutigen Besucher".
 *
 * Ablage: ein JSON pro Monat im Datenordner der Terminbuchung (oberhalb des
 * Web-Roots), Schreibzugriffe mit Dateisperre.
 */

declare(strict_types=1);

require_once __DIR__ . '/booking/_config.php';

/** Erlaubte Ereignisse samt Klartext für die Auswertung. Alles andere wird verworfen. */
const STATS_EVENTS = [
    'chooser_shown'     => 'Startscreen gezeigt',
    'chooser_seo'       => 'Startscreen: Local SEO gewählt',
    'chooser_webdesign' => 'Startscreen: Webdesign gewählt',
    'chooser_skip'      => 'Startscreen: „Später entscheiden“',
    'check_start'       => 'Profil-Check gestartet',
    'check_result'      => 'Profil-Check: Ergebnis angezeigt',
    'check_error'       => 'Profil-Check: kein Profil gefunden / Fehler',
    'report_request'    => 'Profil-Check: Auswertung angefordert',
    'speed_start'       => 'Speed-Check gestartet',
    'booking_day'       => 'Kalender: Uhrzeit gewählt',
    'booking_done'      => 'Termin gebucht',
    'contact_sent'      => 'Kontaktformular gesendet',
    'tel_click'         => 'Telefonnummer angetippt',
    'mail_click'        => 'E-Mail-Adresse angetippt',
    'cta_termin'        => 'Knopf „Erstgespräch/Termin“ angetippt',
    'gsp_switch'        => 'Live-Handys: auf Platz 11 umgeschaltet',
    'menu_open'         => 'Menü geöffnet (Telefon)',
];

/** Obergrenzen gegen aufgeblähte Dateien durch Unfug von außen. */
const STATS_MAX_PATHS_PER_DAY = 200;
const STATS_MAX_HOSTS_PER_DAY = 60;

function statsDir(): ?string {
    try {
        $dir = bkDataDir() . '/stats';
    } catch (Throwable $e) {
        return null;
    }
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return null;
    return $dir;
}

function statsFile(string $monat): ?string {
    $dir = statsDir();
    return $dir === null ? null : $dir . '/stats-' . $monat . '.json';
}

/** Ordnet einen Hostnamen einer lesbaren Quelle zu. */
function statsSource(string $host): string {
    $host = strtolower(preg_replace('/^www\./', '', $host) ?? '');
    if ($host === '') return 'direkt';
    $gruppen = [
        'Google'     => '/(^|\.)google\.[a-z.]+$/',
        'Bing'       => '/(^|\.)bing\.com$/',
        'DuckDuckGo' => '/(^|\.)duckduckgo\.com$/',
        'Ecosia'     => '/(^|\.)ecosia\.org$/',
        'Instagram'  => '/(^|\.)instagram\.com$/',
        'Facebook'   => '/(^|\.)(facebook\.com|fb\.me|m\.facebook\.com)$/',
        'LinkedIn'   => '/(^|\.)(linkedin\.com|lnkd\.in)$/',
        'WhatsApp'   => '/(^|\.)whatsapp\.com$/',
        'ChatGPT'    => '/(^|\.)(chatgpt\.com|openai\.com)$/',
    ];
    foreach ($gruppen as $name => $muster) {
        if (preg_match($muster, $host)) return $name;
    }
    return $host;
}

/**
 * Zählt einen Treffer. $treffer = ['path' => ?string, 'ref' => ?string,
 * 'mobile' => bool, 'event' => ?string]. Gibt false zurück, wenn nicht
 * geschrieben werden konnte — der Aufrufer ignoriert das bewusst.
 */
function statsRecord(array $treffer): bool {
    $monat = date('Y-m');
    $tag = date('Y-m-d');
    $datei = statsFile($monat);
    if ($datei === null) return false;

    $fp = @fopen($datei, 'c+');
    if ($fp === false) return false;
    try {
        if (!flock($fp, LOCK_EX)) return false;
        $roh = stream_get_contents($fp);
        $daten = $roh === '' || $roh === false ? [] : (json_decode($roh, true) ?: []);
        $t = $daten['days'][$tag] ?? ['pv' => [], 'ref' => [], 'dev' => [], 'ev' => []];

        if (!empty($treffer['event'])) {
            $e = $treffer['event'];
            $t['ev'][$e] = ($t['ev'][$e] ?? 0) + 1;
        } elseif (!empty($treffer['path'])) {
            $p = $treffer['path'];
            if (isset($t['pv'][$p]) || count($t['pv']) < STATS_MAX_PATHS_PER_DAY) {
                $t['pv'][$p] = ($t['pv'][$p] ?? 0) + 1;
            }
            $q = statsSource((string) ($treffer['ref'] ?? ''));
            if (!isset($t['ref'][$q]) && count($t['ref']) >= STATS_MAX_HOSTS_PER_DAY) $q = 'andere';
            $t['ref'][$q] = ($t['ref'][$q] ?? 0) + 1;
            $g = !empty($treffer['mobile']) ? 'Telefon' : 'Tablet/Rechner';
            $t['dev'][$g] = ($t['dev'][$g] ?? 0) + 1;
        } else {
            return false;
        }

        $daten['days'][$tag] = $t;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        return true;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/** Liefert die Tageswerte der letzten $tage Tage (heute eingeschlossen), älteste zuerst. */
function statsRange(int $tage): array {
    $ergebnis = [];
    $monate = [];
    for ($i = $tage - 1; $i >= 0; $i--) {
        $ts = strtotime('-' . $i . ' days');
        $monat = date('Y-m', $ts);
        if (!array_key_exists($monat, $monate)) {
            $datei = statsFile($monat);
            $monate[$monat] = ($datei !== null && is_file($datei))
                ? (json_decode((string) file_get_contents($datei), true) ?: [])
                : [];
        }
        $tag = date('Y-m-d', $ts);
        $ergebnis[$tag] = $monate[$monat]['days'][$tag] ?? ['pv' => [], 'ref' => [], 'dev' => [], 'ev' => []];
    }
    return $ergebnis;
}
