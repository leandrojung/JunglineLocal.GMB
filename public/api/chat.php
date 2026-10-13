<?php
/**
 * POST /api/chat: Endpunkt des Chat-Assistenten (runder Knopf unten rechts).
 *
 * ENTSCHEIDUNGSWEG (von sicher nach unsicher)
 *   1. Knopfdruck oder Begrüßung    → feste Antwort, ohne KI.
 *   2. Eindeutiger Wortvergleich    → feste Antwort, ohne KI.
 *   3. Sprachmodell                 → wählt eine feste Antwort oder formuliert
 *                                     kurz aus der Wissensbasis, danach geprüft.
 *   4. Modell nicht erreichbar, Limit leer, Antwort verworfen
 *                                   → schwächerer Wortvergleich, sonst
 *                                     Weiterleitung an Leandro.
 * Der Chat bricht damit nie ab: Im schlimmsten Fall bekommt der Besucher
 * Telefon, Nachricht und Terminbuchung angeboten.
 *
 * DATENSCHUTZ
 *   • Nur Schritt 3 verlässt den Server, und dort geht nur der getippte Text
 *     (die letzten Nachrichten) an den KI-Anbieter. Terminbuchung und
 *     Nachrichtenformular laufen im Browser direkt gegen /api/booking/book und
 *     /api/contact; Name und E-Mail erreichen dieses Skript nie.
 *   • Es wird nichts protokolliert, weder Fragen noch Antworten. Gezählt wird
 *     nur das Tagesbudget.
 *
 * KOSTENBREMSE (wie bei GBP-Check und PageSpeed)
 *   • Herkunft: nur jungline.de.
 *   • Menge pro IP: 60 feste Antworten und 12 KI-Fragen je 10 Minuten.
 *   • Tagesbudget für KI-Aufrufe über alle Besucher (CHAT_DAILY_BUDGET, 300).
 *   • Ist das Budget leer, läuft der Chat mit festen Antworten weiter.
 *
 * DIAGNOSE: /api/chat?diag=<CHAT_DIAG_TOKEN> zeigt, ob Schlüssel, Anbieter und
 * Modell stimmen, und macht einen Testaufruf.
 */

declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/chat/_wissen.php';
require_once __DIR__ . '/chat/_match.php';
require_once __DIR__ . '/chat/_llm.php';

/** Höchstgröße des Request-Bodys in Bytes (Verlauf mit bis zu 8 Nachrichten). */
const CHAT_MAX_BODY = 12288;

/** Zeichen pro Besuchernachricht. */
const CHAT_MAX_FRAGE = 500;

/** Nachrichten, die als Verlauf mitgeschickt werden dürfen. */
const CHAT_MAX_VERLAUF = 8;

header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, noai, noimageai');

// =====================================================================
// Diagnose (GET)
// =====================================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $token = envValue('CHAT_DIAG_TOKEN');
    $gegeben = isset($_GET['diag']) && is_string($_GET['diag']) ? $_GET['diag'] : '';
    if ($token === null || $gegeben === '' || !hash_equals($token, $gegeben)) {
        respond(404, ['success' => false, 'error' => 'not_found']);
    }
    guardRateLimit('chat-diag');

    $k = chatLlmKonfig();
    $bericht = [
        'diagnose' => true,
        'anbieter' => $k['anbieter'],
        'modell' => $k['modell'],
        'schluessel_gefunden' => $k['schluessel'] !== null,
        'tagesbudget' => chatTagesbudget(),
        'eintraege_wissen' => count(chatWissen()),
    ];
    if ($k['schluessel'] === null) {
        $bericht['hinweis'] = 'CHAT_API_KEY fehlt. Der Chat läuft ohne KI, nur mit festen Antworten.';
        respond(200, $bericht);
    }
    $t = chatLlmFrage('Antworte ausschließlich mit diesem JSON: {"typ":"weiterleiten"}', [['role' => 'user', 'content' => 'Test']]);
    $bericht['testaufruf'] = $t['ok'] ? 'erfolgreich' : 'fehlgeschlagen';
    if (!$t['ok']) {
        $bericht['grund'] = $t['grund'];
        if (!empty($t['meldung'])) $bericht['meldung_des_anbieters'] = $t['meldung'];
        $bericht['hinweis'] = 'Die genaue Meldung des Anbieters steht im PHP-Fehlerprotokoll des Servers (Zeile "chat-llm").';
    }
    respond(200, $bericht);
}

guardEnforceOrigin();   // beantwortet auch den CORS-Vorabflug (OPTIONS)
guardRequirePost();

function chatTagesbudget(): int {
    $roh = envValue('CHAT_DAILY_BUDGET');
    return ($roh !== null && preg_match('/^\d+$/', $roh) && (int) $roh > 0) ? (int) $roh : 300;
}

// =====================================================================
// Eingabe lesen und säubern
// =====================================================================

function chatBereinige(mixed $roh, int $max): string {
    if (!is_string($roh)) return '';
    $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $roh);
    $s = (string) preg_replace('/[ \t]+/u', ' ', $s);
    $s = (string) preg_replace('/\n{3,}/u', "\n\n", $s);
    return mb_substr(trim($s), 0, $max);
}

$laenge = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($laenge > CHAT_MAX_BODY) respond(413, ['success' => false, 'error' => 'payload_too_large']);
$rohBody = file_get_contents('php://input', false, null, 0, CHAT_MAX_BODY + 1);
if ($rohBody === false || strlen($rohBody) > CHAT_MAX_BODY) respond(413, ['success' => false, 'error' => 'payload_too_large']);
$eingabe = json_decode($rohBody, true);
if (!is_array($eingabe)) respond(400, ['success' => false, 'error' => 'invalid_body']);

// Honigtopf wie bei Kontakt und Buchung: Bots füllen unsichtbare Felder.
if (trim((string) ($eingabe['website'] ?? '')) !== '') {
    respond(200, ['success' => true, 'typ' => 'faq', 'text' => '', 'links' => [], 'vorschlaege' => []]);
}

// Seite, auf der der Besucher gerade ist (nur zur Orientierung des Modells).
$seite = chatBereinige($eingabe['seite'] ?? '', 120);
if (!preg_match('~^/[a-z0-9/_\-]*$~i', $seite)) $seite = '/';

// Dem Besucher ersparen wir keine Rate-Prüfung, auch nicht für feste Antworten:
// Sie sind billig, aber nicht kostenlos für den Server.
guardRateLimit('chat-faq', 60, 600);

// =====================================================================
// Antwort bauen
// =====================================================================

/** Folgeknöpfe aus Antwort-IDs. */
function chatVorschlaege(array $ids): array {
    $namen = chatKnopfBeschriftungen();
    $aus = [];
    foreach ($ids as $id) {
        if (!isset($namen[$id])) continue;
        if ($id === 'termin' || $id === 'nachricht') {
            $aus[] = ['label' => $namen[$id], 'aktion' => $id];
        } elseif (isset(chatWissen()[$id])) {
            $aus[] = ['label' => $namen[$id], 'faq' => $id];
        }
        if (count($aus) >= 4) break;
    }
    return $aus;
}

function chatLinks(array $ids): array {
    $seiten = chatSeiten();
    $aus = [];
    foreach ($ids as $id) {
        if (isset($seiten[$id])) $aus[] = ['label' => $seiten[$id][1], 'href' => $seiten[$id][0]];
    }
    return $aus;
}

function chatSendeFaq(string $id, string $vorspann = ''): never {
    $e = chatWissen()[$id];
    respond(200, [
        'success' => true,
        'typ' => 'faq',
        'id' => $id,
        'text' => $vorspann . $e['text'],
        'links' => chatLinks($e['links']),
        'aktion' => $e['aktion'],
        'thema' => $e['thema'] ?? null,
        'vorschlaege' => chatVorschlaege($e['folge']),
    ]);
}

function chatSendeWeiterleitung(string $text = ''): never {
    if ($text === '') {
        $text = 'Dazu kann ich Ihnen keine verlässliche Auskunft geben, bevor ich etwas Falsches sage. '
            . 'Das klärt Leandro Jung am besten persönlich: im kostenlosen Erstgespräch, per Nachricht oder telefonisch unter ' . CHAT_TELEFON . '.';
    }
    respond(200, [
        'success' => true,
        'typ' => 'weiterleiten',
        'text' => $text,
        'links' => [['label' => 'Anrufen: ' . CHAT_TELEFON, 'href' => 'tel:' . preg_replace('/\s+/', '', CHAT_TELEFON)]],
        'aktion' => null,
        'thema' => null,
        'vorschlaege' => chatVorschlaege(['termin', 'nachricht']),
    ]);
}

// =====================================================================
// 1) Knopfdruck: feste Antwort per ID
// =====================================================================
$faqId = $eingabe['faq'] ?? null;
if (is_string($faqId) && $faqId !== '') {
    $wissen = chatWissen();
    if (!isset($wissen[$faqId])) respond(400, ['success' => false, 'error' => 'unknown_faq']);
    chatSendeFaq($faqId);
}

// =====================================================================
// Verlauf prüfen: letzte Nachricht muss vom Besucher stammen
// =====================================================================
$verlaufRoh = is_array($eingabe['messages'] ?? null) ? $eingabe['messages'] : [];
$verlaufRoh = array_slice($verlaufRoh, -CHAT_MAX_VERLAUF);
$verlauf = [];
foreach ($verlaufRoh as $m) {
    if (!is_array($m)) continue;
    $rolle = ($m['role'] ?? '') === 'assistant' ? 'assistant' : (($m['role'] ?? '') === 'user' ? 'user' : '');
    if ($rolle === '') continue;
    $inhalt = chatBereinige($m['content'] ?? '', $rolle === 'user' ? CHAT_MAX_FRAGE : 700);
    if ($inhalt === '') continue;
    $verlauf[] = [
        'role' => $rolle,
        'content' => $inhalt,
        'zu_lang' => $rolle === 'user' && is_string($m['content']) && mb_strlen(trim($m['content'])) > CHAT_MAX_FRAGE,
    ];
}
$letzte = $verlauf === [] ? null : $verlauf[count($verlauf) - 1];
if ($letzte === null || $letzte['role'] !== 'user') {
    respond(422, ['success' => false, 'error' => 'no_question']);
}
$frage = $letzte['content'];

// Überlange Nachrichten werden nicht gekürzt beantwortet: Wer 500+ Zeichen
// tippt, hat meist einen Einzelfall, der zu Leandro gehört.
if ($letzte['zu_lang']) {
    chatSendeWeiterleitung('Das ist eine ausführliche Frage, die ich lieber nicht verkürzt beantworten möchte. Schreiben Sie sie gern direkt an Leandro Jung oder buchen Sie ein kostenloses Erstgespräch.');
}

// =====================================================================
// 2) Begrüßung und eindeutiger Wortvergleich
// =====================================================================
if (chatIstBegruessung($frage)) chatSendeFaq('begruessung');

$treffer = chatFindeFaq($frage);
if ($treffer !== null) chatSendeFaq($treffer);

// =====================================================================
// 3) Sprachmodell
// =====================================================================
$notfall = static function () use ($frage): never {
    $id = chatFindeFaqNotfall($frage);
    if ($id !== null) {
        chatSendeFaq($id, 'Dazu habe ich diese Auskunft: ');
    }
    chatSendeWeiterleitung();
};

if (!chatLlmVerfuegbar()) $notfall();

// Eigene, strengere Menge für die Aufrufe, die Geld oder Kontingent kosten.
guardRateLimit('chat-llm', 12, 600);

if (!guardConsumeNamedBudget('chat', chatTagesbudget())) $notfall();

$modell = chatLlmFrage(chatSystemPrompt($seite), $verlauf);
if (!$modell['ok']) $notfall();

$urteil = chatModellAntwortAuswerten($modell['text'], $frage);

if ($urteil['typ'] === 'faq') chatSendeFaq($urteil['id']);

// Das Modell hat selbst entschieden, dass die Auskunft nicht aus dem Wissen
// kommen kann (Einzelfall). Dann keine "ähnliche" feste Antwort anbieten.
if ($urteil['typ'] === 'weiterleiten' && !empty($urteil['explizit'])) chatSendeWeiterleitung();

if ($urteil['typ'] === 'antwort') {
    $aktion = $urteil['aktion'];
    $folge = $aktion === 'termin' ? ['nachricht', 'preise'] : ['termin', 'nachricht'];
    respond(200, [
        'success' => true,
        'typ' => 'ki',
        'text' => $urteil['text'],
        'links' => chatLinks($urteil['links']),
        'aktion' => $aktion,
        'thema' => $urteil['thema'],
        'vorschlaege' => chatVorschlaege($folge),
    ]);
}

// Verworfen oder bewusst weitergeleitet: erst eine feste Antwort versuchen,
// dann an Leandro verweisen.
$notfall();
