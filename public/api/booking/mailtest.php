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

/**
 * Baut eine Mail mit frei wählbarem Anhangstyp. bkBuildMime kann das nicht —
 * es schreibt fest text/calendar — und soll es auch nicht können: der
 * Produktivcode bleibt für die Diagnose unangetastet.
 */
function bkTestMime(string $html, string $text, ?string $attachment, string $contentType, string $filename): array {
    $mixed = 'mix_' . bin2hex(random_bytes(12));
    $alt   = 'alt_' . bin2hex(random_bytes(12));

    $alternative = "--$alt\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text), 76, "\r\n")
        . "--$alt\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html), 76, "\r\n")
        . "--$alt--\r\n";

    if ($attachment === null) {
        return ['headers' => ['Content-Type' => "multipart/alternative; boundary=\"$alt\""], 'body' => $alternative];
    }

    $body = "--$mixed\r\n"
        . "Content-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n"
        . $alternative
        . "--$mixed\r\n"
        . "Content-Type: $contentType; name=\"$filename\"\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n"
        . chunk_split(base64_encode($attachment), 76, "\r\n")
        . "--$mixed--\r\n";

    return ['headers' => ['Content-Type' => "multipart/mixed; boundary=\"$mixed\""], 'body' => $body];
}

/**
 * Eine Kalenderdatei zum Ablegen statt einer Einladung zum Beantworten:
 * METHOD:PUBLISH, ohne ORGANIZER und ohne ATTENDEE. Genau so verschickt
 * auch Calendly seine Anhänge.
 */
function bkTestIcsPublish(array $booking): string {
    $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());
    $end   = new DateTimeImmutable($booking['end_utc'], bkUtcTz());

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//JunglineLocal//Terminbuchung//DE',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . bkIcsUid($booking['token']),
        'SEQUENCE:0',
        'DTSTAMP:' . bkNow()->format('Ymd\THis\Z'),
        'DTSTART:' . $start->format('Ymd\THis\Z'),
        'DTEND:' . $end->format('Ymd\THis\Z'),
        'SUMMARY:' . bkIcsEscape(BK_TITLE),
        'STATUS:CONFIRMED',
        'END:VEVENT',
        'END:VCALENDAR',
    ];
    return implode("\r\n", array_map('bkIcsFold', $lines)) . "\r\n";
}

// Runde 4: Die echte Bestätigungsmail selbst — nach Betreff-Fix und ohne
// Anhang kam sie weiterhin nicht an, während die inhaltlich schlichtere
// Absage zugestellt wird. Verdächtig ist der calendar.google.com/render-
// Link, den nur die Bestätigung enthielt (bekannte Kalender-Spam-Signatur).
// Die Vorlage verlinkt inzwischen über die eigene Domain (/api/booking/gcal);
// K stellt zum Beweis das alte Verhalten mit dem Direktlink nach.
$conf = bkMailConfirmation($sample);
$directGcal = bkGoogleCalendarUrl($sample);
$redirGcal  = bkGcalLinkUrl($sample['token']);

$variants = [
    'J — echte Bestätigung, aktueller Stand (Google-Link über eigene Domain)' => [
        'subject' => 'J: ' . $conf['subject'],
        'mime' => bkBuildMime($conf['html'], $conf['text'], null),
        'from' => $from,
    ],
    'K — dieselbe Mail, aber mit direktem calendar.google.com-Link (alt)' => [
        'subject' => 'K: ' . $conf['subject'],
        'mime' => bkBuildMime(
            str_replace(bkEsc($redirGcal), bkEsc($directGcal), $conf['html']),
            str_replace($redirGcal, $directGcal, $conf['text']),
            null
        ),
        'from' => $from,
    ],
    'L — Minimaltext, nur der direkte calendar.google.com-Link' => [
        'subject' => 'L: Test ' . $stamp,
        'mime' => bkBuildMime(
            '<p>Test L: <a href="' . bkEsc($directGcal) . '">Kalenderlink</a></p>',
            "Test L\n" . $directGcal,
            null
        ),
        'from' => $from,
    ],
    // Auch nach dem Ausdünnen (kein Werbetext mehr, Zoom-Link nur einmal) blieb
    // J im Rückstand zu L. Zwei Verdächtige bleiben, und P/Q trennen sie:
    //
    //   P — der volle Rahmen (Vorschautext-Trick, Branding, Fußzeile mit
    //       Impressum/Datenschutz — DER TEIL, DEN JEDE MAIL NUTZT), aber ohne
    //       die beiden Kalender-Knöpfe. Kommt P an, waren die Knöpfe/die
    //       Linkzahl der Auslöser, nicht der Rahmen.
    //   Q — L's nackter Inhalt, aber im vollen Rahmen verpackt. Kommt Q NICHT
    //       an, ist der Rahmen selbst der Auslöser — am ehesten der
    //       unsichtbare Vorschautext (display:none/opacity:0), eine klassische
    //       Spam-Filter-Signatur.
    'P — voller Rahmen, aber ohne die zwei Kalender-Knöpfe' => [
        'subject' => 'P: ' . $conf['subject'],
        'mime' => bkBuildMime(
            bkEmailShell('Test P', 'Ihr Termin steht',
                '<p style="margin:0 0 4px;">Hallo,</p>'
                . '<p style="margin:0;">Test P — Terminbox ohne Kalender-Knöpfe.</p>'
                . bkEmailFactBox($sample)
                . '<p style="margin:22px 0 0;font-size:13px;">Absagen: '
                . '<a href="' . bkEsc(bkManageUrl($sample['token'])) . '">Link</a></p>'),
            "Test P\n" . bkTextFacts($sample) . "\nAbsagen: " . bkManageUrl($sample['token']),
            null
        ),
        'from' => $from,
    ],
    'Q — L\'s nackter Inhalt, aber im vollen Mail-Rahmen verpackt' => [
        'subject' => 'Q: Test ' . $stamp,
        'mime' => bkBuildMime(
            bkEmailShell('Test Q', 'Test Q',
                '<p>Test Q: <a href="' . bkEsc($directGcal) . '">Kalenderlink</a></p>'),
            "Test Q\n" . $directGcal,
            null
        ),
        'from' => $from,
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
        $ok = bkTestSmtp($to, $variant['subject'], $variant['mime'], $variant['from'], $log,
                         $variant['subject_header'] ?? null);
    } catch (Throwable $e) {
        $log[] = '!!! Ausnahme: ' . $e->getMessage();
    }

    foreach ($log as $line) echo '  ' . $line . "\n";
    echo "\n  ERGEBNIS: " . ($ok ? 'vom Server angenommen' : 'FEHLGESCHLAGEN') . "\n\n";
}

echo str_repeat('=', 68) . "\n";
echo "Fertig. Bitte im Postfach " . $to . " nachsehen, welche der drei\n";
echo "Testmails (J, K, L, P, Q) ankommt — auch im Spam-Ordner.\n";
