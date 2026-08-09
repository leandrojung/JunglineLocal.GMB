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
 * ankommt und welche nicht, grenzt die Ursache ohne Raten ein: Die Varianten
 * unterscheiden sich immer nur in genau einer Sache.
 *
 * Elf Runden haben drei Regeln ergeben, an die sich jede Mail halten muss:
 * kein Anhang, höchstens drei Web-Adressen in der Textfassung, keine
 * Markennamen neben einem Link (Einzelheiten in TERMINBUCHUNG.md). Wer die
 * Vorlagen ändert und danach unsicher ist, ob sie noch zugestellt werden,
 * setzt hier zwei Varianten ein — alt und neu — und weiß es nach einem Lauf.
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
// Hier stehen die Mails, die geprueft werden sollen. Sie duerfen sich immer
// nur in genau einer Sache unterscheiden — sonst sagt das Ergebnis nicht,
// woran es lag. Zwei Zeilen genuegen fuer einen Vergleich: eine mit der
// alten, eine mit der neuen Fassung.
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

// Die echten Vorlagen und daneben eine Referenzmail, die sich ueber alle
// Diagnoserunden hinweg als zustellbar erwiesen hat. Sie ist als Vergleich
// nuetzlich: Kommt sie an und die Vorlage nicht, liegt es an der Vorlage;
// kommt beides nicht an, liegt es am Server oder am Versandweg.
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

// Die Vorlagen so, wie der Betrieb sie verschickt. Wer an ihnen arbeitet und
// danach unsicher ist, ob sie die drei Zustellregeln noch einhalten (kein
// Anhang, hoechstens drei Web-Adressen im Textteil, keine Markennamen neben
// einem Link — siehe TERMINBUCHUNG.md), setzt hier die alte und die neue
// Fassung nebeneinander und weiss es nach einem Lauf.
$erinnerung = bkMailReminder($sample);

$variants = [
    'AA — Bestätigung, wie sie bei einer echten Buchung verschickt wird' => [
        'subject' => 'AA: ' . $conf['subject'],
        'bkmail' => ['html' => $conf['html'], 'text' => $conf['text']],
    ],
    // Die Erinnerung ist die einzige Mailart, die im Betrieb noch nie
    // ausgelöst wurde — sie hängt am Cronjob und braucht einen Termin, der
    // weniger als 24 Stunden entfernt ist. Hier lässt sie sich sofort prüfen,
    // statt beim ersten Ernstfall auf gut Glück zu vertrauen.
    'CC — Erinnerungsmail (läuft sonst nur über den Cronjob)' => [
        'subject' => 'CC: ' . $erinnerung['subject'],
        'bkmail' => ['html' => $erinnerung['html'], 'text' => $erinnerung['text']],
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
echo "ankommen — auch im Spam-Ordner. Jede Variante laesst genau einen\n";
echo "Baustein weg; welche ankommt, benennt den Baustein, der die Mail\n";
echo "bisher die Zustellung gekostet hat.\n";
