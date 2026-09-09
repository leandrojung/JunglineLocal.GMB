<?php
/**
 * Akquise-Konsole — Serverspeicher.
 *
 * Warum es diese Datei gibt: Die Konsole entstand als Claude-Artefakt und
 * legte ihre Daten über `window.storage` ab — eine Schnittstelle, die es
 * nur innerhalb von Claude gibt. Auf einer normalen Website existiert sie
 * nicht; die Konsole hätte dort bei jedem Neuladen bei null angefangen.
 * Genau das soll hier nicht passieren: Kontakte und Anrufe liegen ab jetzt
 * als Datei auf dem Server, eine Ebene ÜBER dem Web-Root, und sind damit
 * von jedem Gerät aus dieselben — Mac, iPhone, egal.
 *
 * Zugang: eine einzige geheime Webnummer. Sie steht nirgends auf der
 * Website verlinkt, wird bei jeder Anfrage mitgeschickt und hier gegen
 * einen Hash geprüft. Kein Login, keine Benutzerverwaltung — es gibt genau
 * einen Nutzer.
 *
 * Nummer, Ablageort und Zugangssperre stehen in _akquise.php — dieselbe
 * Grundlage nutzt auch /api/akquise-ki.
 */

declare(strict_types=1);

require_once __DIR__ . '/_akquise.php';

// ---------------------------------------------------------------------
// Anfrage
// ---------------------------------------------------------------------

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['ok' => false, 'grund' => 'methode', 'meldung' => 'Nur POST.']);
}

$roh = file_get_contents('php://input');
if ($roh === false || strlen($roh) > AKQ_MAX_BYTES) {
    respond(413, ['ok' => false, 'grund' => 'zu_gross', 'meldung' => 'Der Datenstand ist zu groß.']);
}
$eingabe = json_decode((string) $roh, true);
if (!is_array($eingabe)) {
    respond(400, ['ok' => false, 'grund' => 'format', 'meldung' => 'Ungültige Anfrage.']);
}

// Die Nummer wird ZUERST geprueft, die Sperre gilt nur fuer Fehlversuche.
// Andersherum haette ein Vertipper Leandro fuer eine Stunde aus seinem
// eigenen Werkzeug ausgesperrt — und gegen das Durchprobieren schuetzt es
// genauso, weil ein Ratender per Definition nie die richtige Nummer hat.
if (!akqNummerOk((string) ($eingabe['nummer'] ?? ''))) {
    akqVersuchNotieren();
    usleep(250000); // bremst schnelles Durchprobieren zusaetzlich aus
    if (!akqVersuchErlaubt()) {
        respond(429, ['ok' => false, 'grund' => 'gesperrt',
            'meldung' => 'Zu viele Fehlversuche. Bitte in einer Stunde erneut versuchen.']);
    }
    respond(403, ['ok' => false, 'grund' => 'nummer', 'meldung' => 'Die Webnummer stimmt nicht.']);
}

$datei = akqDatei();
if ($datei === null) {
    respond(500, ['ok' => false, 'grund' => 'kein_speicher',
        'meldung' => 'Auf dem Server ist kein beschreibbarer Datenordner vorhanden. '
                   . 'Bitte AKQUISE_DATA_DIR in der .env setzen.']);
}

$leer = ['kontakte' => [], 'anrufe' => []];

/**
 * Öffnet die Datei EINMAL exklusiv und erledigt Lesen und Schreiben in
 * derselben Sperre. Ohne das könnten zwei gleichzeitig offene Geräte sich
 * gegenseitig überschreiben, ohne dass es jemandem auffällt.
 */
$fh = @fopen($datei, 'c+');
if ($fh === false || !flock($fh, LOCK_EX)) {
    if ($fh !== false) fclose($fh);
    respond(500, ['ok' => false, 'grund' => 'sperre', 'meldung' => 'Die Datei ist gerade belegt.']);
}

$inhalt = stream_get_contents($fh);
$stand = json_decode((string) $inhalt, true);
if (!is_array($stand) || !isset($stand['daten']) || !is_array($stand['daten'])) {
    $stand = ['rev' => 0, 'geaendert' => '', 'daten' => $leer];
}
$stand['rev'] = (int) ($stand['rev'] ?? 0);
$stand['daten']['kontakte'] = array_values((array) ($stand['daten']['kontakte'] ?? []));
$stand['daten']['anrufe']   = array_values((array) ($stand['daten']['anrufe']   ?? []));

$aktion = (string) ($eingabe['aktion'] ?? 'laden');

if ($aktion === 'laden') {
    flock($fh, LOCK_UN); fclose($fh);
    respond(200, ['ok' => true, 'rev' => $stand['rev'], 'geaendert' => $stand['geaendert'], 'daten' => $stand['daten']]);
}

if ($aktion !== 'sichern') {
    flock($fh, LOCK_UN); fclose($fh);
    respond(400, ['ok' => false, 'grund' => 'aktion', 'meldung' => 'Unbekannte Aktion.']);
}

$neu = $eingabe['daten'] ?? null;
if (!is_array($neu) || !is_array($neu['kontakte'] ?? null) || !is_array($neu['anrufe'] ?? null)) {
    flock($fh, LOCK_UN); fclose($fh);
    respond(400, ['ok' => false, 'grund' => 'daten', 'meldung' => 'Der übergebene Datenstand ist unvollständig.']);
}

/**
 * Versionsprüfung. Sie ist der eigentliche Schutz gegen den Fall, den man
 * erst bemerkt, wenn es zu spät ist: Auf dem iPhone liegt ein alter Stand
 * offen, es wird dort etwas gespeichert — und die zwanzig Kontakte, die
 * inzwischen am Mac entstanden sind, wären weg. Passt die Version nicht,
 * wird NICHT geschrieben; der Browser bekommt den aktuellen Stand zurück
 * und lädt ihn neu.
 */
$erwartet = $eingabe['rev'] ?? null;
if ($erwartet !== null && (int) $erwartet !== $stand['rev']) {
    flock($fh, LOCK_UN); fclose($fh);
    respond(409, ['ok' => false, 'grund' => 'konflikt', 'rev' => $stand['rev'],
        'geaendert' => $stand['geaendert'], 'daten' => $stand['daten'],
        'meldung' => 'Auf einem anderen Gerät wurde inzwischen gespeichert.']);
}

$stand = [
    'rev' => $stand['rev'] + 1,
    'geaendert' => gmdate('c'),
    'daten' => ['kontakte' => array_values($neu['kontakte']), 'anrufe' => array_values($neu['anrufe'])],
];
$json = json_encode($stand, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if ($json === false) {
    flock($fh, LOCK_UN); fclose($fh);
    respond(500, ['ok' => false, 'grund' => 'kodierung', 'meldung' => 'Der Datenstand ließ sich nicht speichern.']);
}

// Erst nach erfolgreichem Schreiben kürzen wäre falsch herum — rewind +
// ftruncate + fwrite in dieser Reihenfolge, sonst bleibt bei kürzerem
// Inhalt ein Rest der alten Datei stehen und die JSON-Datei ist kaputt.
rewind($fh);
ftruncate($fh, 0);
$geschrieben = fwrite($fh, $json);
fflush($fh);
flock($fh, LOCK_UN);
fclose($fh);

if ($geschrieben === false || $geschrieben < strlen($json)) {
    respond(500, ['ok' => false, 'grund' => 'schreiben', 'meldung' => 'Der Datenstand wurde nur unvollständig geschrieben.']);
}

respond(200, ['ok' => true, 'rev' => $stand['rev'], 'geaendert' => $stand['geaendert']]);
