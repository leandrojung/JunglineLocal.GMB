<?php
/**
 * Akquise-Konsole — Textbausteine über die Anthropic-API.
 *
 * Die Konsole hat zwei Stellen, an denen Text erzeugt wird: die
 * Nachfass-Mail nach einem Anruf und das Gesprächstraining. Im Claude-
 * Artefakt liefen beide über einen Aufruf direkt aus dem Browser gegen
 * api.anthropic.com — dort ohne Schlüssel, weil Claude ihn selbst
 * beigelegt hat. Auf der eigenen Website geht das nicht: Ein API-Schlüssel
 * im Browser ist ein öffentlicher API-Schlüssel, und die Rechnung schreibt
 * dann jemand anders. Deshalb steht der Schlüssel in der .env oberhalb des
 * Web-Roots, und der Browser spricht ausschließlich mit dieser Datei.
 *
 * Ohne ANTHROPIC_API_KEY antwortet der Endpunkt mit einer klaren Meldung
 * statt mit einem Fehler. Der Rest der Konsole — Skript, Einwände, Liste,
 * Auswertung — funktioniert davon völlig unabhängig.
 */

declare(strict_types=1);

require_once __DIR__ . '/_akquise.php';

/** Standardmodell. Über AKQUISE_KI_MODELL in der .env änderbar. */
const AKQ_KI_MODELL_STANDARD = 'claude-sonnet-5';

/**
 * Aufrufe pro Kalendertag über alle Geräte. Reine Kostenbremse: Sollte die
 * Webnummer je in falsche Hände geraten, ist der Schaden auf einen Tag
 * gedeckelt statt unbegrenzt.
 */
const AKQ_KI_BUDGET_TAG = 150;

const AKQ_KI_MAX_TOKENS = 1000;

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['ok' => false, 'grund' => 'methode', 'meldung' => 'Nur POST.']);
}

$roh = file_get_contents('php://input');
if ($roh === false || strlen($roh) > 200000) {
    respond(413, ['ok' => false, 'grund' => 'zu_gross', 'meldung' => 'Die Anfrage ist zu groß.']);
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

$schluessel = envValue('ANTHROPIC_API_KEY');
if ($schluessel === null) {
    respond(503, ['ok' => false, 'grund' => 'kein_schluessel',
        'meldung' => 'Mail-Vorschlag und Training brauchen einen Anthropic-Schlüssel. '
                   . 'Dafür ANTHROPIC_API_KEY in der .env oberhalb des Web-Roots eintragen. '
                   . 'Alles andere in der Konsole läuft auch ohne.']);
}

$system = trim((string) ($eingabe['system'] ?? ''));
$nachrichten = $eingabe['nachrichten'] ?? null;
if ($system === '' || !is_array($nachrichten) || $nachrichten === []) {
    respond(400, ['ok' => false, 'grund' => 'daten', 'meldung' => 'System-Anweisung oder Nachrichten fehlen.']);
}

// Nur die Felder durchlassen, die die API kennt — und nur die beiden
// erlaubten Rollen. Sonst wandert ungeprüfter Browser-Inhalt in den
// Anfragekörper eines bezahlten Dienstes.
$sauber = [];
foreach ($nachrichten as $n) {
    if (!is_array($n)) continue;
    $rolle = (string) ($n['role'] ?? '');
    $inhalt = (string) ($n['content'] ?? '');
    if (($rolle !== 'user' && $rolle !== 'assistant') || $inhalt === '') continue;
    $sauber[] = ['role' => $rolle, 'content' => mb_substr($inhalt, 0, 20000)];
}
if ($sauber === []) {
    respond(400, ['ok' => false, 'grund' => 'daten', 'meldung' => 'Keine verwertbaren Nachrichten.']);
}

// ---- Tagesbudget ----------------------------------------------------
$dir = akqDataDir();
if ($dir !== null) {
    $budgetDatei = $dir . '/akquise-ki-budget.json';
    $heute = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    $stand = json_decode((string) @file_get_contents($budgetDatei), true);
    if (!is_array($stand) || ($stand['tag'] ?? '') !== $heute) $stand = ['tag' => $heute, 'n' => 0];
    if ((int) $stand['n'] >= AKQ_KI_BUDGET_TAG) {
        respond(429, ['ok' => false, 'grund' => 'budget',
            'meldung' => 'Das Tagesbudget für KI-Texte ist aufgebraucht. Morgen geht es weiter.']);
    }
    $stand['n'] = (int) $stand['n'] + 1;
    @file_put_contents($budgetDatei, json_encode($stand), LOCK_EX);
}

// ---- Aufruf ---------------------------------------------------------
$modell = envValue('AKQUISE_KI_MODELL') ?? AKQ_KI_MODELL_STANDARD;

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $schluessel,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => $modell,
        'max_tokens' => AKQ_KI_MAX_TOKENS,
        'system' => mb_substr($system, 0, 20000),
        'messages' => $sauber,
    ], JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
$antwort = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$fehler = curl_error($ch);
curl_close($ch);

if ($antwort === false || $fehler !== '') {
    error_log('akquise-ki: cURL — ' . $fehler);
    respond(502, ['ok' => false, 'grund' => 'netz', 'meldung' => 'Die KI war nicht erreichbar. Bitte noch einmal versuchen.']);
}

$d = json_decode((string) $antwort, true);
if ($code < 200 || $code >= 300 || !is_array($d)) {
    $meldung = is_array($d) ? (string) ($d['error']['message'] ?? '') : '';
    error_log('akquise-ki: HTTP ' . $code . ' — ' . $meldung);
    respond(502, ['ok' => false, 'grund' => 'api',
        'meldung' => 'Die KI hat mit einem Fehler geantwortet (HTTP ' . $code . ').']);
}

$text = '';
foreach (($d['content'] ?? []) as $teil) {
    if (is_array($teil) && ($teil['type'] ?? '') === 'text') $text .= (string) $teil['text'] . "\n";
}
$text = trim($text);
if ($text === '') {
    respond(502, ['ok' => false, 'grund' => 'leer', 'meldung' => 'Die KI hat nichts zurückgegeben.']);
}

respond(200, ['ok' => true, 'text' => $text]);
