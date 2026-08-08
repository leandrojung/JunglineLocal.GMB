<?php
/**
 * Mail-Diagnose — /api/booking/mailtest?token=<BOOKING_CRON_TOKEN>&to=<adresse>
 *
 * Warum es diese Datei gibt: Beim Buchen meldet der Mailserver "250 OK", die
 * Bestätigung kommt beim Kunden aber nie an. Zwischen "angenommen" und
 * "zugestellt" liegt der Weg nach außen, und genau der ist von außen unsichtbar
 * — der normale Versand merkt sich nur ja/nein, nicht das Gespräch dahinter.
 *
 * Diese Route schreibt jede Zeile mit, die zwischen uns und dem Server hin und
 * her geht, und verschickt dabei mehrere Varianten derselben Mail. Welche
 * ankommt und welche nicht, grenzt die Ursache ohne Raten ein.
 *
 * Runde 1 (A ohne/mit Anhang, B und C ohne) hat ergeben: ohne .ics kommt die
 * Mail an, mit .ics verschwindet sie — obwohl der Server sie in beiden Fällen
 * mit "250 queued" annimmt. Die Varianten unten trennen jetzt auf, welcher
 * Teil des Anhangs dafür verantwortlich ist.
 *
 * Die Route ist mit demselben Token geschützt wie der Erinnerungs-Cron. Ohne
 * Schutz könnte jeder Fremde über sie beliebig Mails auslösen.
 */

declare(strict_types=1);

require_once __DIR__ . '/_mail.php';
require_once __DIR__ . '/_ics.php';
require_once __DIR__ . '/_templates.php';

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

$to = trim((string) ($_GET['to'] ?? ''));
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "Bitte eine Zieladresse angeben: &to=name@example.com\n";
    exit;
}

// ---------------------------------------------------------------------
// SMTP mit Mitschrift
// ---------------------------------------------------------------------

/**
 * Wie bkSmtpSend, aber jede gesendete und empfangene Zeile landet in $log.
 * Bewusst eine eigene Kopie statt einer Erweiterung des Produktivcodes: der
 * Versandweg, der die echten Buchungen trägt, soll sich für eine Diagnose
 * nicht ändern.
 *
 * @param array<int,string> $log
 */
function bkTestSmtp(string $toEmail, string $subject, array $mime, string $envelopeFrom, array &$log, ?string $subjectHeader = null): bool {
    $host   = (string) envValue('SMTP_HOST');
    $user   = (string) envValue('SMTP_USER');
    $pass   = (string) envValue('SMTP_PASS');
    $secure = strtolower(envValue('SMTP_SECURE') ?? 'ssl');
    $port   = (int) (envValue('SMTP_PORT') ?? ($secure === 'ssl' ? 465 : 587));

    $endpoint = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $log[] = '>>> verbinde mit ' . $endpoint;

    $fp = @stream_socket_client($endpoint, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        $log[] = '!!! Verbindung fehlgeschlagen: ' . $errstr . ' (' . $errno . ')';
        return false;
    }
    stream_set_timeout($fp, 20);

    // Liest eine — auch mehrzeilige — Antwort und schreibt sie mit.
    $read = function () use ($fp, &$log): int {
        $code = 0;
        while (($line = fgets($fp, 2048)) !== false) {
            $log[] = '<<< ' . rtrim($line, "\r\n");
            $code = (int) substr($line, 0, 3);
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        return $code;
    };

    // Schreibt einen Befehl mit; Passwörter werden dabei unkenntlich gemacht.
    $cmd = function (string $command, bool $secret = false) use ($fp, &$log): void {
        $log[] = '>>> ' . ($secret ? '(Zugangsdaten, nicht protokolliert)' : $command);
        fwrite($fp, $command . "\r\n");
    };

    try {
        if ($read() !== 220) { $log[] = '!!! Begrüßung nicht 220'; return false; }

        $helo = parse_url(bkSiteUrl(), PHP_URL_HOST) ?: 'jungline.de';
        $cmd('EHLO ' . $helo);
        if ($read() !== 250) { $log[] = '!!! EHLO abgelehnt'; return false; }

        if ($secure === 'tls') {
            $cmd('STARTTLS');
            if ($read() !== 220) { $log[] = '!!! STARTTLS abgelehnt'; return false; }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $log[] = '!!! TLS-Handshake fehlgeschlagen';
                return false;
            }
            $log[] = '=== TLS aktiv';
            $cmd('EHLO ' . $helo);
            if ($read() !== 250) { $log[] = '!!! EHLO nach TLS abgelehnt'; return false; }
        }

        $cmd('AUTH LOGIN');
        if ($read() !== 334) { $log[] = '!!! AUTH LOGIN abgelehnt'; return false; }
        $cmd(base64_encode($user), true);
        if ($read() !== 334) { $log[] = '!!! Benutzername abgelehnt'; return false; }
        $cmd(base64_encode($pass), true);
        if ($read() !== 235) { $log[] = '!!! Passwort abgelehnt'; return false; }

        $cmd('MAIL FROM:<' . $envelopeFrom . '>');
        if ($read() !== 250) { $log[] = '!!! Absender abgelehnt'; return false; }

        $cmd('RCPT TO:<' . $toEmail . '>');
        if ($read() !== 250) { $log[] = '!!! Empfänger abgelehnt'; return false; }

        $cmd('DATA');
        if ($read() !== 354) { $log[] = '!!! DATA abgelehnt'; return false; }

        $headers = [
            'Date' => date('r'),
            'From' => bkAddress($envelopeFrom, bkMailFromName()),
            'To' => $toEmail,
            'Subject' => $subjectHeader ?? bkMimeHeader($subject),
            'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . $helo . '>',
            'MIME-Version' => '1.0',
        ] + $mime['headers'];

        $raw = '';
        foreach ($headers as $key => $value) $raw .= $key . ': ' . $value . "\r\n";
        $raw .= "\r\n" . $mime['body'];

        fwrite($fp, $raw . "\r\n.\r\n");
        $log[] = '>>> [Nachricht, ' . strlen($raw) . ' Bytes] + Endpunkt';

        // Die Antwort auf den Schlusspunkt ist die wichtigste Zeile überhaupt:
        // Hier steht oft die Queue-ID, unter der die Mail beim Hoster weiter-
        // läuft — und im Fehlerfall der eigentliche Ablehnungsgrund.
        if ($read() !== 250) { $log[] = '!!! Nachricht abgelehnt'; return false; }

        $cmd('QUIT');
        $read();
        return true;
    } finally {
        fclose($fp);
    }
}

// ---------------------------------------------------------------------
// Varianten
//
// Runde 1 hat gezeigt: ohne Anhang kommt die Mail an, mit .ics nicht.
// Die Datei selbst ist einwandfrei (alle Zeilen unter 75 Bytes, Struktur
// vom MIME-Parser ohne Beanstandung). Bleibt die Bedeutung des Anhangs:
// METHOD:REQUEST ist keine Kalenderdatei, sondern eine förmliche Einladung
// mit Zusage-Aufforderung. Damit lassen sich fremde Kalender befüllen —
// entsprechend streng gehen Filter damit um.
//
// Runde 2 trennt deshalb die drei Dinge, die daran hängen können:
// der MIME-Typ, die Einladungs-Semantik und die Teilnehmerzeile.
// ---------------------------------------------------------------------

$stamp = date('H:i:s');
$from  = bkMailFrom();

$sample = [
    'token' => 'diagnose',
    'start_utc' => bkStamp(bkNow()->modify('+2 days')),
    'end_utc' => bkStamp(bkNow()->modify('+2 days')->modify('+30 minutes')),
    'name' => 'Diagnose',
    'email' => $to,
    'phone' => '',
    'company' => '',
    'message' => '',
];

// Runde 7 — die Abschlussrunde. Die Regel aus sechs Runden: Der Filter des
// Hosters verwirft (a) jede Mail mit Anhang und (b) jede Mail ab VIER
// Web-Adressen im Textteil. 0–3 Adressen kamen ausnahmslos an (L=1, P=2,
// R=3, Absage=2), 4–5 ausnahmslos nicht (alte Bestätigung). Die Vorlage
// wurde daraufhin auf drei Adressen gebracht (eine Kalender-Landeseite
// statt zwei Kalender-Links, Signatur ohne Web-Adresse).
//
// Diese Runde schließt die letzte Lücke zwischen Test und Wirklichkeit:
// S verschickt die ECHTE Vorlage über den ECHTEN Versandweg bkMail() —
// exakt der Aufruf aus book.php, samt Reply-To und Empfängername, die der
// Testversand hier nie gesetzt hat. R bleibt als bewährte Referenz, T
// schickt R-Inhalt über den echten Weg. Damit trennt eine einzige Runde
// Vorlage und Versandweg sauber auf, egal wie sie ausgeht.
$conf = bkMailConfirmation($sample);

$rHtml = bkEmailShell('Test R', 'Ihr Termin steht',
    '<p style="margin:0 0 4px;">Hallo,</p>'
    . '<p style="margin:0;">Test R — bewährte Referenz mit drei Adressen.</p>'
    . bkEmailFactBox($sample)
    . '<p style="margin:18px 0 0;font-size:13px;">Termin eintragen: '
    . '<a href="' . bkEsc(bkIcsUrl($sample['token'])) . '">Kalenderdatei herunterladen</a></p>'
    . '<p style="margin:14px 0 0;font-size:13px;">Absagen: '
    . '<a href="' . bkEsc(bkManageUrl($sample['token'])) . '">Link</a></p>');
$rText = "Test R\n" . bkTextFacts($sample)
    . "\nKalenderdatei: " . bkIcsUrl($sample['token'])
    . "\nAbsagen: " . bkManageUrl($sample['token']);

// Runde 8: Runde 7 hat den Versandweg entlastet (T über bkMail kam an) und
// die Vorlage belastet (S über denselben Weg nicht). Der Byte-Vergleich
// S↔T ließ vor allem zwei Gewichte übrig: den Betreff im Datum-Uhrzeit-
// Muster ("Ihr Termin am 10.08.2026 um 19:30 Uhr" — Signatur einschlägiger
// Terminbestätigungs-Spamwellen) und den versteckten Vorschautext, der vom
// sichtbaren Inhalt abwich. Beides ist aus der Vorlage entfernt; U prüft
// die neue Vorlage, V dieselbe Vorlage mit dem alten Betreff — kommt U an
// und V nicht, war der Betreff das entscheidende Gewicht.
$variants = [
    'T — Referenzinhalt über bkMail (Kontrolle, kam in Runde 7 an)' => [
        'subject' => 'T: Referenz ' . $stamp,
        'bkmail' => ['html' => $rHtml, 'text' => $rText],
    ],
    'U — NEUE echte Bestätigung über bkMail (neutraler Betreff, Vorschau kurz)' => [
        'subject' => 'U: ' . $conf['subject'],
        'bkmail' => ['html' => $conf['html'], 'text' => $conf['text']],
    ],
    'V — dieselbe neue Vorlage, aber alter Datum-Uhrzeit-Betreff' => [
        'subject' => 'V: Ihr Termin am ' . bkLocal(new DateTimeImmutable($sample['start_utc'], bkUtcTz()))->format('d.m.Y') . ' um '
            . bkLocal(new DateTimeImmutable($sample['start_utc'], bkUtcTz()))->format('H:i') . ' Uhr',
        'bkmail' => ['html' => $conf['html'], 'text' => $conf['text']],
    ],
    // Die Gegenprobe zu U: Referenzinhalt (kommt sicher an) mit dem NEUEN
    // Betreff. Kommt W an und U nicht, ist der Betreff entlastet und der
    // Inhalt überführt; kommt W nicht an, ist es der Betreff. Zusammen mit
    // T und V deckt die Runde damit jede Kombination ab.
    'W — Referenzinhalt, aber mit dem neuen Betreff' => [
        'subject' => 'W: ' . $conf['subject'],
        'bkmail' => ['html' => $rHtml, 'text' => $rText],
    ],
];

echo "Mail-Diagnose " . date('d.m.Y H:i:s') . "\n";
echo str_repeat('=', 68) . "\n\n";

echo "Konfiguration\n";
echo "  SMTP_HOST    " . (envValue('SMTP_HOST') ?? '(nicht gesetzt)') . "\n";
echo "  SMTP_PORT    " . (envValue('SMTP_PORT') ?? '(nicht gesetzt)') . "\n";
echo "  SMTP_SECURE  " . (envValue('SMTP_SECURE') ?? '(nicht gesetzt)') . "\n";
echo "  SMTP_USER    " . (envValue('SMTP_USER') ?? '(nicht gesetzt)') . "\n";
echo "  SMTP_PASS    " . ((envValue('SMTP_PASS') ?? '') !== '' ? '(gesetzt, ' . strlen((string) envValue('SMTP_PASS')) . ' Zeichen)' : '(NICHT GESETZT)') . "\n";
echo "  MAIL_FROM    " . bkMailFrom() . "\n";
echo "  Ziel         " . $to . "\n\n";

foreach ($variants as $label => $variant) {
    echo str_repeat('-', 68) . "\n";
    echo $label . "\n";
    echo str_repeat('-', 68) . "\n";

    $log = [];
    $ok = false;
    try {
        if (isset($variant['bkmail'])) {
            // Der echte Versandweg — exakt der Aufruf aus book.php, inklusive
            // Reply-To und Empfängername. Kein Wort-für-Wort-Protokoll, dafür
            // null Abweichung von der Wirklichkeit.
            $log[] = '>>> über bkMail(), den Versandweg der echten Buchungen';
            $ok = bkMail($to, 'Diagnose', $variant['subject'],
                         $variant['bkmail']['html'], $variant['bkmail']['text'],
                         null, bkOwnerEmail());
        } else {
            $ok = bkTestSmtp($to, $variant['subject'], $variant['mime'], $variant['from'], $log,
                             $variant['subject_header'] ?? null);
        }
    } catch (Throwable $e) {
        $log[] = '!!! Ausnahme: ' . $e->getMessage();
    }

    foreach ($log as $line) echo '  ' . $line . "\n";
    echo "\n  ERGEBNIS: " . ($ok ? 'vom Server angenommen' : 'FEHLGESCHLAGEN') . "\n\n";
}

echo str_repeat('=', 68) . "\n";
echo "Fertig. Bitte im Postfach " . $to . " nachsehen, welche der Testmails\n";
echo "(R, S, T) ankommen — auch im Spam-Ordner. Entscheidend ist S: das ist\n";
echo "die echte Bestätigung über den echten Versandweg.\n";
