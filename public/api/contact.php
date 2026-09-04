<?php
/**
 * POST /api/contact — das Kontaktformular ("Oder schreiben Sie mir").
 *
 * WARUM ES DIESEN ENDPUNKT GIBT
 * -----------------------------
 * Das Formular lief bisher über Formspree, einen fremden Dienst in den USA.
 * Der Versand scheiterte still: Der Besucher füllte aus, klickte, und
 * bekam die rote Zeile "Das Senden hat leider nicht geklappt". Warum, war
 * von hier aus nicht feststellbar — Formspree beantwortet ein nicht
 * bestätigtes, gesperrtes oder aufgebrauchtes Formular mit einem Fehler,
 * und die Website kann nur raten, welcher der Fälle vorliegt. Jede Anfrage,
 * die dort verloren ging, war ein verlorener Kunde.
 *
 * Der Versand läuft deshalb jetzt über denselben Weg wie die
 * Terminbestätigungen: bkMail() mit der ganzen Transportkette dahinter
 * (Maildienst → SMTP → mail()), mit Ausgangskorb, Wiederholung und
 * Protokoll. Was hier scheitert, ist im Protokoll nachlesbar
 * (/api/booking/mailtest) und wird automatisch wiederholt — statt spurlos
 * bei einem Dritten zu verschwinden.
 *
 * Nebeneffekt, der zählt: Es verlässt kein personenbezogenes Datum mehr die
 * EU, nur damit ein Formular ankommt.
 *
 * WAS DER BESUCHER BEKOMMT
 *   1. Leandro bekommt die Nachricht, mit dem Absender als Antwortadresse.
 *   2. Der Absender bekommt eine kurze Eingangsbestätigung.
 *
 * Punkt 2 ist der Grund, warum es überhaupt eine Antwortmail gibt: Wer
 * schreibt, soll schwarz auf weiß sehen, dass die Nachricht angekommen ist.
 *
 * SPAMSCHUTZ
 *   • Honigtopf: ein für Menschen unsichtbares Feld (_gotcha). Ist es
 *     ausgefüllt, war ein Bot am Werk. Die Antwort lautet trotzdem
 *     "erfolgreich" — der Bot soll über die Erkennung nichts lernen.
 *   • Mengenbegrenzung pro IP, gleitendes Zeitfenster.
 *   • Feste, kurze Bestätigungsmail ohne den Text des Absenders. Eine
 *     automatische Antwort, die Fremdtext zurück ins Netz trägt, ist ein
 *     Werkzeug für fremde Werbung, nicht für Kunden.
 */

declare(strict_types=1);

require_once __DIR__ . '/booking/_mail.php';
require_once __DIR__ . '/booking/_templates.php';
require_once __DIR__ . '/booking/_worker.php';

// ---- Grenzwerte ---------------------------------------------------------

/** Nachrichten pro IP im Zeitfenster CONTACT_RATE_WINDOW. */
const CONTACT_RATE_MAX = 5;

/** Länge des Zeitfensters in Sekunden. */
const CONTACT_RATE_WINDOW = 3600;

/** Höchstlänge des Nachrichtenfeldes in Zeichen. */
const CONTACT_MSG_MAX = 4000;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['success' => false, 'error' => 'method_not_allowed']);
}

header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

// ---------------------------------------------------------------------
// Herkunft — bewusst nur ablehnend, nie fordernd.
//
// Schickt der Browser einen Origin-Header und gehört der zu einer fremden
// Seite, ist das Formular nicht unseres: abweisen. Fehlt der Header ganz
// (ältere Browser, Datenschutz-Erweiterungen, ein Aufruf ohne JavaScript),
// wird NICHT abgewiesen. Eine Prüfung, die einen Header voraussetzt, den
// nicht jeder Browser schickt, verwandelt genau das in einen stillen
// Ausfall, den dieser Endpunkt beseitigen soll.
// ---------------------------------------------------------------------
$origin = strtolower((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
if ($origin !== '') {
    $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $eigen = strtolower((string) parse_url(bkSiteUrl(), PHP_URL_HOST));
    $lokal = $host === 'localhost' || $host === '127.0.0.1';
    if (!$lokal && $host !== $eigen && !str_ends_with($host, '.' . $eigen)) {
        respond(403, ['success' => false, 'error' => 'forbidden_origin']);
    }
}

// ---------------------------------------------------------------------
// Eingaben lesen — JSON wie Formularfelder
//
// Das Skript im Browser schickt JSON. Ein abgeschicktes Formular ohne
// JavaScript käme als application/x-www-form-urlencoded an. Beides
// anzunehmen kostet drei Zeilen und nimmt der Seite eine stille
// Fehlerquelle.
// ---------------------------------------------------------------------
$roh = (string) file_get_contents('php://input');
$input = json_decode($roh, true);
if (!is_array($input)) $input = $_POST;

$feld = static fn (string $name): string => trim((string) ($input[$name] ?? ''));

// ---- Honigtopf. "_gotcha" heißt das Feld im Markup, "website" nutzt die
// Terminbuchung — beide werden geprüft, damit ein umbenanntes Feld die
// Falle nicht aushebelt.
if ($feld('_gotcha') !== '' || $feld('website') !== '') {
    respond(200, ['success' => true, 'spam' => true]);
}

$name    = $feld('name');
$email   = $feld('email');
$phone   = $feld('phone');
$message = $feld('message');
// Aus welchem Formular die Nachricht kam (Startseite, Kontaktseite,
// Webdesign). Steckt als verstecktes Feld im Markup und ist reine
// Information für Leandro — deshalb hart gekürzt statt geprüft.
$quelle  = mb_substr($feld('_subject'), 0, 120);

$fehler = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
    $fehler['name'] = 'Bitte geben Sie Ihren Namen an.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
    $fehler['email'] = 'Bitte prüfen Sie Ihre E-Mail-Adresse.';
}
if (mb_strlen($phone) > 40) {
    $fehler['phone'] = 'Die Telefonnummer ist zu lang.';
}
if (mb_strlen($message) < 5) {
    $fehler['message'] = 'Bitte schreiben Sie kurz, worum es geht.';
} elseif (mb_strlen($message) > CONTACT_MSG_MAX) {
    $fehler['message'] = 'Bitte fassen Sie sich etwas kürzer (max. ' . CONTACT_MSG_MAX . ' Zeichen).';
}
if ($fehler !== []) {
    respond(422, ['success' => false, 'error' => 'validation_failed', 'fields' => $fehler]);
}

// Zeilenumbrüche in Werten, die in Kopfzeilen landen, sind der klassische
// Header-Injection-Weg. Die Adresse hat filter_var bereits abgesichert.
$name   = (string) preg_replace('/[\r\n]+/', ' ', $name);
$phone  = (string) preg_replace('/[\r\n]+/', ' ', $phone);
$quelle = (string) preg_replace('/[\r\n]+/', ' ', $quelle);

// ---------------------------------------------------------------------
// Mengenbegrenzung
//
// Gleitendes Zeitfenster statt eines Zählers, der zur vollen Stunde
// zurückspringt: Letzteres ließe sich durch kurzes Warten umgehen.
// Scheitert die Zählung (kein Schreibrecht), wird die Nachricht trotzdem
// verschickt — ein stummes Kontaktformular wäre der größere Schaden als
// eine ungezählte Anfrage.
// ---------------------------------------------------------------------
function contactRateLimited(string $ip): bool {
    if ($ip === '') return false;

    try {
        $datei = bkDataDir() . '/contact-rl.json';
    } catch (Throwable $e) {
        error_log('contact: kein Datenordner — ' . $e->getMessage());
        return false;
    }

    $fp = @fopen($datei, 'c+');
    if ($fp === false) return false;

    try {
        if (!flock($fp, LOCK_EX)) return false;

        $inhalt = stream_get_contents($fp);
        $alle = $inhalt === '' || $inhalt === false ? [] : (json_decode($inhalt, true) ?: []);

        $jetzt = time();
        $schluessel = sha1($ip);
        $treffer = array_values(array_filter(
            is_array($alle[$schluessel] ?? null) ? $alle[$schluessel] : [],
            static fn ($t): bool => is_int($t) && $t > $jetzt - CONTACT_RATE_WINDOW
        ));

        if (count($treffer) >= CONTACT_RATE_MAX) return true;

        $treffer[] = $jetzt;
        $alle[$schluessel] = $treffer;

        // Abgelaufene Einträge fremder IPs mit aufräumen, sonst wächst die
        // Datei mit jedem Besucher, der je geschrieben hat.
        foreach ($alle as $k => $liste) {
            $offen = array_filter((array) $liste, static fn ($t): bool => is_int($t) && $t > $jetzt - CONTACT_RATE_WINDOW);
            if ($offen === []) unset($alle[$k]); else $alle[$k] = array_values($offen);
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode($alle));
        fflush($fp);
        return false;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

$ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
if (filter_var($ip, FILTER_VALIDATE_IP) === false) $ip = '';

if (contactRateLimited($ip)) {
    respond(429, ['success' => false, 'error' => 'rate_limited']);
}

// ---------------------------------------------------------------------
// Verschicken
// ---------------------------------------------------------------------
$anfrage = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'message' => $message,
    'quelle' => $quelle,
    'warnung' => bkTransportWarning(),
];

try {
    $anLeandro = bkMailContactNotice($anfrage);
    // Antwortadresse ist der Absender: Ein Klick auf "Antworten" geht direkt
    // an den Interessenten, nicht an das eigene Postfach zurück.
    $raus = bkMail(bkOwnerEmail(), bkOwnerName(), $anLeandro['subject'],
                   $anLeandro['html'], $anLeandro['text'], null, $email, 'kontakt');
} catch (Throwable $e) {
    // Hier zu landen heißt: Es gibt weder einen Versandweg noch einen
    // Ausgangskorb. Nur dann darf der Besucher einen Fehler sehen — mit
    // Telefonnummer, damit er nicht vor einer Sackgasse steht.
    error_log('contact: Versand komplett gescheitert — ' . $e->getMessage());
    respond(503, ['success' => false, 'error' => 'mail_unavailable']);
}

// $raus === false heißt NICHT verloren: bkMail() hat die Nachricht in den
// Ausgangskorb gelegt, und der Hintergrundlauf wiederholt sie. Für den
// Besucher ist die Nachricht damit angekommen — sie liegt bei uns.
if (!$raus) {
    error_log('contact: Nachricht von ' . $email . ' liegt im Ausgangskorb.');
}

// Die Eingangsbestätigung darf die Anfrage nie gefährden: Sie ist Kür,
// die Nachricht an Leandro ist Pflicht.
try {
    $anBesucher = bkMailContactReceipt($anfrage);
    bkMail($email, $name, $anBesucher['subject'], $anBesucher['html'],
           $anBesucher['text'], null, bkOwnerEmail(), 'eingangsbestaetigung');
} catch (Throwable $e) {
    error_log('contact: Eingangsbestätigung fehlgeschlagen — ' . $e->getMessage());
}

// Liegengebliebenes nachreichen, sobald der Besucher seine Antwort hat.
bkScheduleBackgroundWork();

respond(200, ['success' => true, 'queued' => !$raus]);
