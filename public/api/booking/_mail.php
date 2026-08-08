<?php
/**
 * Terminbuchung — Mailversand.
 *
 * Eigener, abhängigkeitsfreier SMTP-Client: auf Shared-Hosting gibt es kein
 * composer-Deploy, und PHPMailer nur für zwei Mailtypen mitzuschleppen wäre
 * unverhältnismäßig. Versendet wird über das eigene Postfach
 * (SMTP_HOST/-USER/-PASS in der .env) — Absender und Domain passen dann
 * zusammen, was der wichtigste Hebel gegen den Spam-Ordner ist.
 *
 * Ohne SMTP-Konfiguration fällt der Versand auf PHP mail() zurück. Das
 * funktioniert, landet aber spürbar häufiger im Spam; die Bestätigung eines
 * Termins ist genau die Mail, bei der das am meisten weh tut.
 *
 * Alle Teile werden base64-kodiert. Das kostet ein Drittel mehr Bytes und
 * erspart im Gegenzug jedes Umlaut- und Zeilenlängenproblem — und das
 * Dot-Stuffing im SMTP-Datenstrom, weil base64-Zeilen nie mit "." beginnen.
 */

declare(strict_types=1);

require_once __DIR__ . '/_config.php';

function bkMailFrom(): string {
    return envValue('MAIL_FROM') ?? bkOwnerEmail();
}

function bkMailFromName(): string {
    return envValue('MAIL_FROM_NAME') ?? 'JunglineLocal';
}

/** RFC-2047-Kodierung für Kopfzeilen mit Umlauten (Betreff, Anzeigename). */
function bkMimeHeader(string $value): string {
    return preg_match('/[\x80-\xFF]/', $value)
        ? '=?UTF-8?B?' . base64_encode($value) . '?='
        : $value;
}

function bkAddress(string $email, string $name = ''): string {
    return $name === '' ? $email : bkMimeHeader($name) . ' <' . $email . '>';
}

/**
 * Baut die vollständige MIME-Nachricht.
 *
 * @param array|null $ics ['content' => string, 'method' => 'REQUEST'|'CANCEL']
 * @return array{headers: array<string,string>, body: string}
 */
function bkBuildMime(string $html, string $text, ?array $ics): array {
    $boundaryMixed = 'mix_' . bin2hex(random_bytes(12));
    $boundaryAlt   = 'alt_' . bin2hex(random_bytes(12));

    $alternative = "--$boundaryAlt\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text), 76, "\r\n")
        . "--$boundaryAlt\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html), 76, "\r\n")
        . "--$boundaryAlt--\r\n";

    if ($ics === null) {
        return [
            'headers' => ['Content-Type' => "multipart/alternative; boundary=\"$boundaryAlt\""],
            'body' => $alternative,
        ];
    }

    $method = $ics['method'] ?? 'REQUEST';
    $body = "--$boundaryMixed\r\n"
        . "Content-Type: multipart/alternative; boundary=\"$boundaryAlt\"\r\n\r\n"
        . $alternative
        . "--$boundaryMixed\r\n"
        . "Content-Type: text/calendar; charset=UTF-8; method=$method; name=\"termin.ics\"\r\n"
        . "Content-Transfer-Encoding: base64\r\n"
        . "Content-Disposition: attachment; filename=\"termin.ics\"\r\n\r\n"
        . chunk_split(base64_encode($ics['content']), 76, "\r\n")
        . "--$boundaryMixed--\r\n";

    return [
        'headers' => ['Content-Type' => "multipart/mixed; boundary=\"$boundaryMixed\""],
        'body' => $body,
    ];
}

// =====================================================================
// SMTP
// =====================================================================

function bkSmtpConfigured(): bool {
    return (envValue('SMTP_HOST') ?? '') !== '' && (envValue('SMTP_USER') ?? '') !== '';
}

/** Liest eine — auch mehrzeilige — Serverantwort und liefert den Statuscode. */
function bkSmtpRead($fp): int {
    $code = 0;
    while (($line = fgets($fp, 1024)) !== false) {
        $code = (int) substr($line, 0, 3);
        // "250-" heißt: es folgen weitere Zeilen, "250 " beendet die Antwort.
        if (strlen($line) < 4 || $line[3] !== '-') break;
    }
    return $code;
}

function bkSmtpCmd($fp, string $command, int $expected): bool {
    fwrite($fp, $command . "\r\n");
    $code = bkSmtpRead($fp);
    if ($code !== $expected) {
        // Passwörter dürfen nie ins Log — deshalb nur das Verb protokollieren.
        error_log('booking/smtp: "' . strtok($command, ' ') . '" ergab ' . $code . ', erwartet ' . $expected);
        return false;
    }
    return true;
}

function bkSmtpSend(string $toEmail, string $toName, string $subject, array $mime, ?string $replyTo): bool {
    $host   = (string) envValue('SMTP_HOST');
    $user   = (string) envValue('SMTP_USER');
    $pass   = (string) envValue('SMTP_PASS');
    $secure = strtolower(envValue('SMTP_SECURE') ?? 'ssl');
    $port   = (int) (envValue('SMTP_PORT') ?? ($secure === 'ssl' ? 465 : 587));

    $endpoint = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $fp = @stream_socket_client($endpoint, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        error_log('booking/smtp: Verbindung zu ' . $endpoint . ' fehlgeschlagen — ' . $errstr);
        return false;
    }
    stream_set_timeout($fp, 15);

    try {
        if (bkSmtpRead($fp) !== 220) return false;

        $helo = parse_url(bkSiteUrl(), PHP_URL_HOST) ?: 'jungline.de';
        if (!bkSmtpCmd($fp, 'EHLO ' . $helo, 250)) return false;

        if ($secure === 'tls') {
            if (!bkSmtpCmd($fp, 'STARTTLS', 220)) return false;
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log('booking/smtp: STARTTLS-Handshake fehlgeschlagen.');
                return false;
            }
            // Nach dem Wechsel auf TLS verlangt der Standard ein neues EHLO.
            if (!bkSmtpCmd($fp, 'EHLO ' . $helo, 250)) return false;
        }

        if (!bkSmtpCmd($fp, 'AUTH LOGIN', 334)) return false;
        if (!bkSmtpCmd($fp, base64_encode($user), 334)) return false;
        if (!bkSmtpCmd($fp, base64_encode($pass), 235)) return false;

        if (!bkSmtpCmd($fp, 'MAIL FROM:<' . bkMailFrom() . '>', 250)) return false;
        if (!bkSmtpCmd($fp, 'RCPT TO:<' . $toEmail . '>', 250)) return false;
        if (!bkSmtpCmd($fp, 'DATA', 354)) return false;

        $headers = [
            'Date' => date('r'),
            'From' => bkAddress(bkMailFrom(), bkMailFromName()),
            'To' => bkAddress($toEmail, $toName),
            'Subject' => bkMimeHeader($subject),
            'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . $helo . '>',
            'MIME-Version' => '1.0',
        ] + $mime['headers'];
        if ($replyTo !== null) $headers['Reply-To'] = $replyTo;

        $raw = '';
        foreach ($headers as $key => $value) $raw .= $key . ': ' . $value . "\r\n";
        $raw .= "\r\n" . $mime['body'];

        fwrite($fp, $raw . "\r\n.\r\n");
        if (bkSmtpRead($fp) !== 250) {
            error_log('booking/smtp: Server hat die Nachricht abgelehnt.');
            return false;
        }

        bkSmtpCmd($fp, 'QUIT', 221);
        return true;
    } finally {
        fclose($fp);
    }
}

// =====================================================================
// Öffentliche Schnittstelle
// =====================================================================

/**
 * Verschickt eine Mail. Rückgabe false heißt: nicht zugestellt — der
 * Aufrufer entscheidet, ob das die Buchung scheitern lässt (tut es nicht;
 * ein gebuchter Termin bleibt gebucht, auch wenn die Mail klemmt).
 */
function bkMail(string $toEmail, string $toName, string $subject, string $html, string $text, ?array $ics = null, ?string $replyTo = null): bool {
    $mime = bkBuildMime($html, $text, $ics);

    if (bkSmtpConfigured()) {
        return bkSmtpSend($toEmail, $toName, $subject, $mime, $replyTo);
    }

    $headers = [
        'From' => bkAddress(bkMailFrom(), bkMailFromName()),
        'MIME-Version' => '1.0',
    ] + $mime['headers'];
    if ($replyTo !== null) $headers['Reply-To'] = $replyTo;

    $raw = '';
    foreach ($headers as $key => $value) $raw .= $key . ': ' . $value . "\r\n";

    $sent = @mail($toEmail, bkMimeHeader($subject), $mime['body'], rtrim($raw), '-f' . bkMailFrom());
    if (!$sent) error_log('booking/mail: mail() an ' . $toEmail . ' fehlgeschlagen.');
    return $sent;
}
