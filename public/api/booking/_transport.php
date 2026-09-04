<?php
/**
 * Terminbuchung — Versandwege ("Transporte").
 *
 * WARUM ES DIESE DATEI GIBT
 * -------------------------
 * Bis hierher ging jede Mail über das Postfach des Webhosters. Dessen
 * Ausgangsfilter nimmt jede Mail an ("250 Ok: queued") und verwirft sie
 * danach lautlos — ohne Fehlermeldung, ohne Bounce, ohne Spam-Ordner. Elf
 * Diagnoserunden haben daraus Faustregeln abgeleitet (kein Anhang, höchstens
 * drei Adressen im Text, keine Markennamen neben einem Link). Alle drei sind
 * nachgewiesen, aber sie kurieren ein Symptom: Sie schieben die Mail knapp
 * unter eine Schwelle, die niemand sehen kann und die sich jederzeit
 * verschieben darf. Genau das ist passiert — dieselbe Mail kam bei dem einen
 * Empfänger an und beim nächsten nicht.
 *
 * Die Ursache liegt nicht im Text, sondern im Weg: Eine Mail von
 * Info@jungline.de, die über eine geteilte Hoster-IP hinausgeht, muss sich
 * gegenüber Gmail & Co. als echt ausweisen (SPF, DKIM, DMARC). Tut sie das
 * nicht zweifelsfrei, verschwindet sie — bei manchen Anbietern sofort, bei
 * anderen nie. Deshalb geht der Versand jetzt wahlweise über einen echten
 * Transaktionsmail-Dienst per HTTPS. Der signiert jede Mail mit DKIM für
 * jungline.de, verschickt über gepflegte IP-Adressen und sagt vor allem:
 * was mit jeder einzelnen Mail passiert ist.
 *
 * AUFBAU
 * ------
 * Ein Transport ist ein Versandweg mit immer derselben Antwort:
 *
 *     ['ok' => bool, 'id' => string, 'error' => string, 'log' => string[]]
 *
 *   'ok'    — der Weg hat die Mail angenommen
 *   'id'    — Vorgangsnummer des Dienstes, um sie dort nachzuverfolgen
 *   'error' — Klartextgrund, falls nicht
 *   'log'   — Gesprächsmitschrift für die Diagnoseseite
 *
 * bkMail() (in _mail.php) probiert die Transporte in der Reihenfolge aus,
 * die bkTransportChain() liefert, und nimmt den ersten, der annimmt.
 */

declare(strict_types=1);

require_once __DIR__ . '/_config.php';

// =====================================================================
// Adressen und Kopfzeilen
// =====================================================================

function bkMailFrom(): string {
    return envValue('MAIL_FROM') ?? bkOwnerEmail();
}

function bkMailFromName(): string {
    return envValue('MAIL_FROM_NAME') ?? 'JunglineLocal';
}

/**
 * Zeilenumbrüche aus einem Anzeigenamen entfernen. Ein "\r\n" in einem
 * Namen ist der klassische Weg, fremde Kopfzeilen in eine Mail zu
 * schmuggeln — und der Name kommt aus einem Formular im Internet.
 */
function bkCleanName(string $name): string {
    return trim((string) preg_replace('/[\r\n\t]+/', ' ', $name));
}

/**
 * RFC-2047-Kodierung für Kopfzeilen mit Umlauten (Betreff, Anzeigename).
 *
 * Ein kodiertes Wort darf höchstens 75 Zeichen lang sein — die Klammern
 * "=?UTF-8?B?" und "?=" zählen mit und belegen davon schon 12. Längere Texte
 * gehören auf mehrere kodierte Wörter verteilt, getrennt durch Zeilenumbruch
 * und Leerzeichen; der Empfänger fügt sie wieder zusammen.
 *
 * Ohne diese Aufteilung riss ausgerechnet der wichtigste Betreff des Systems
 * die Grenze um neun Zeichen — "Termin bestätigt: Freitag, 28. August 2026,
 * 11:00 Uhr" — und kostete die Bestätigungsmail die Zustellung: angenommen,
 * nie ausgeliefert, kein Bounce.
 *
 * Geteilt wird zwischen Zeichen, nicht zwischen Bytes: Ein Schnitt mitten
 * durch ein "ä" macht daraus beim Empfänger zwei Fragezeichen.
 */
function bkMimeHeader(string $value): string {
    $value = bkCleanName($value);
    if (!preg_match('/[\x80-\xFF]/', $value)) return $value;

    // 45 Byte Rohtext ergeben 60 Zeichen Base64, mit den Klammern 72 —
    // mit Sicherheitsabstand unter der Grenze von 75.
    $chunks = [];
    $current = '';
    foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
        if (strlen($current) + strlen($char) > 45) {
            $chunks[] = $current;
            $current = '';
        }
        $current .= $char;
    }
    if ($current !== '') $chunks[] = $current;

    return implode("\r\n ", array_map(
        static fn (string $chunk): string => '=?UTF-8?B?' . base64_encode($chunk) . '?=',
        $chunks
    ));
}

/**
 * "Name <adresse>" für eine Kopfzeile.
 *
 * Reine ASCII-Namen werden NICHT kodiert, brauchen aber Anführungszeichen,
 * sobald ein Sonderzeichen aus RFC 5322 darin vorkommt. Der häufigste Fall
 * ist das Komma: "Jung, Klaus <k@example.de>" liest ein Mailserver als ZWEI
 * Empfänger — "Jung" (ungültig) und "Klaus <k@example.de>". Manche Server
 * weisen die Mail deshalb ab, andere stellen sie nur an einen Teil zu.
 * Genau so entsteht "kommt bei manchen an, bei manchen nicht", ohne dass
 * irgendwo ein Fehler auftaucht.
 */
function bkAddress(string $email, string $name = ''): string {
    $name = bkCleanName($name);
    if ($name === '') return $email;

    $encoded = bkMimeHeader($name);
    // Kodierte Wörter dürfen nicht in Anführungszeichen stehen — sie werden
    // dann wörtlich angezeigt statt dekodiert.
    if ($encoded !== $name) return $encoded . ' <' . $email . '>';

    if (preg_match('/[()<>@,;:\\\\".\[\]]/', $name)) {
        $name = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $name) . '"';
    }
    return $name . ' <' . $email . '>';
}

// =====================================================================
// MIME-Aufbau (für SMTP und mail(); die HTTPS-Dienste bauen selbst)
// =====================================================================

/**
 * Baut die vollständige MIME-Nachricht.
 *
 * Alle Teile werden base64-kodiert. Das kostet ein Drittel mehr Bytes und
 * erspart im Gegenzug jedes Umlaut- und Zeilenlängenproblem.
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

/**
 * Punkt-Verdopplung am Zeilenanfang (RFC 5321, "dot stuffing").
 *
 * Eine Zeile, die im DATA-Abschnitt nur aus einem Punkt besteht, beendet die
 * Nachricht. Steht am Zeilenanfang ein Punkt, muss er verdoppelt werden.
 * Base64-Zeilen fangen nie mit "." an, Kopfzeilen auch nicht — die Absicherung
 * kostet aber nichts und schützt jeden künftigen Teil, der nicht kodiert ist.
 */
function bkDotStuff(string $data): string {
    if (str_starts_with($data, '.')) $data = '.' . $data;
    return str_replace("\r\n.", "\r\n..", $data);
}

// =====================================================================
// Transport-Kette
// =====================================================================

/** Menschenlesbarer Name eines Transports — für Protokoll und Diagnose. */
function bkTransportLabel(string $id): string {
    return [
        'brevo'    => 'Brevo (HTTPS-API)',
        'resend'   => 'Resend (HTTPS-API)',
        'postmark' => 'Postmark (HTTPS-API)',
        'mailjet'  => 'Mailjet (HTTPS-API)',
        'smtp'     => 'SMTP (' . (envValue('SMTP_HOST') ?? 'nicht gesetzt') . ')',
        // Ohne Gedankenstrich: Die Diagnoseseite richtet ihre Spalten mit
        // printf aus, und das zählt Bytes, nicht Zeichen.
        'mail'     => 'PHP mail() (Notnagel)',
    ][$id] ?? $id;
}

/** Ist dieser Transport vollständig konfiguriert? */
function bkTransportConfigured(string $id): bool {
    return match ($id) {
        'brevo'    => (envValue('BREVO_API_KEY') ?? '') !== '',
        'resend'   => (envValue('RESEND_API_KEY') ?? '') !== '',
        'postmark' => (envValue('POSTMARK_TOKEN') ?? '') !== '',
        'mailjet'  => (envValue('MAILJET_API_KEY') ?? '') !== '' && (envValue('MAILJET_SECRET_KEY') ?? '') !== '',
        'smtp'     => (envValue('SMTP_HOST') ?? '') !== '' && (envValue('SMTP_USER') ?? '') !== '',
        'mail'     => function_exists('mail'),
        default    => false,
    };
}

/**
 * Die Versandwege in der Reihenfolge, in der sie probiert werden.
 *
 * Ohne Zutun gewinnt der erste Dienst, für den ein Schlüssel hinterlegt ist;
 * SMTP und mail() hängen als Auffangnetz hinten dran. Wer die Reihenfolge
 * selbst bestimmen will, setzt MAIL_TRANSPORT, z. B.
 *
 *     MAIL_TRANSPORT=brevo,smtp
 *
 * Unbekannte oder unkonfigurierte Namen werden dabei still übersprungen —
 * eine Kette, die auf einen Tippfehler hin gar nichts mehr verschickt, wäre
 * schlimmer als die Voreinstellung.
 *
 * @return array<int,string>
 */
function bkTransportChain(): array {
    $wanted = envValue('MAIL_TRANSPORT');
    $order = $wanted !== null && trim($wanted) !== ''
        ? array_map('trim', explode(',', strtolower($wanted)))
        : ['brevo', 'resend', 'postmark', 'mailjet', 'smtp', 'mail'];

    $chain = array_values(array_filter($order, 'bkTransportConfigured'));

    // mail() bleibt immer als letzte Möglichkeit übrig: eine Mail, die über
    // einen wackligen Weg geht, ist besser als eine, die nie entsteht.
    if ($chain === [] && function_exists('mail')) $chain[] = 'mail';
    return $chain;
}

/**
 * Steht ein Transaktionsmail-Dienst bereit?
 *
 * Der Unterschied ist nicht Geschmackssache. Ein Dienst signiert jede Mail
 * mit DKIM für jungline.de und meldet zu jeder einzelnen zurück, ob sie
 * zugestellt, abgelehnt oder als Spam eingestuft wurde. Das Hoster-Postfach
 * tut beides nicht: Es antwortet auf ALLES mit "250 Ok: queued" und verwirft
 * danach lautlos, was ihm nicht passt — ohne Fehlermeldung, ohne Bounce.
 *
 * Genau deshalb muss diese Frage beantwortbar sein: Fehlt der Dienst,
 * verschickt das System weiterhin Mails, kann aber ueber keine einzige
 * sagen, ob sie angekommen ist. Das gehoert dem Betreiber gesagt, statt es
 * ihn beim Kunden merken zu lassen (siehe book.php und contact.php).
 */
function bkHasVerifiedTransport(): bool {
    foreach (['brevo', 'resend', 'postmark', 'mailjet'] as $id) {
        if (bkTransportConfigured($id)) return true;
    }
    return false;
}

/** Klartext-Warnung, wenn kein Maildienst eingerichtet ist — sonst ''. */
function bkTransportWarning(): string {
    if (bkHasVerifiedTransport()) return '';
    return 'Es ist kein Maildienst eingerichtet (Brevo, Resend, Postmark oder Mailjet). '
        . 'Mails gehen über das Hoster-Postfach hinaus, und das nimmt jede Nachricht an, '
        . 'ohne zu sagen, ob sie beim Empfänger ankommt. Einrichtung: TERMINBUCHUNG.md, Abschnitt 1.';
}

/**
 * Verschickt eine Nachricht über einen bestimmten Transport.
 *
 * @param array $msg  to_email, to_name, subject, html, text, reply_to, ics
 * @return array{ok: bool, id: string, error: string, log: array<int,string>}
 */
function bkTransportSend(string $id, array $msg): array {
    return match ($id) {
        'brevo'    => bkSendBrevo($msg),
        'resend'   => bkSendResend($msg),
        'postmark' => bkSendPostmark($msg),
        'mailjet'  => bkSendMailjet($msg),
        'smtp'     => bkSendSmtp($msg),
        'mail'     => bkSendPhpMail($msg),
        default    => ['ok' => false, 'id' => '', 'error' => 'Unbekannter Transport: ' . $id, 'log' => []],
    };
}

// =====================================================================
// HTTPS-Dienste
// =====================================================================

/**
 * Ein POST gegen eine JSON-API, mit Mitschrift für die Diagnose.
 *
 * @param array<int,string> $headers
 * @return array{status:int, json:?array, body:string, error:string}
 */
function bkApiPost(string $url, array $headers, array $payload, ?string $basicAuth = null): array {
    if (!function_exists('curl_init')) {
        return ['status' => 0, 'json' => null, 'body' => '', 'error' => 'cURL ist auf diesem Server nicht verfügbar.'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    if ($basicAuth !== null) {
        curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
    }

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false || $curlError !== '') {
        return ['status' => 0, 'json' => null, 'body' => '', 'error' => 'Verbindung fehlgeschlagen: ' . $curlError];
    }

    $json = json_decode((string) $body, true);
    return [
        'status' => $status,
        'json' => is_array($json) ? $json : null,
        'body' => (string) $body,
        'error' => '',
    ];
}

/**
 * Kurzfassung einer Fehlerantwort für Protokoll und Diagnoseseite.
 *
 * Jeder Dienst legt den Grund woanders ab; abgefragt werden deshalb der
 * Reihe nach die bekannten Stellen. Bleibt nichts übrig, steht der Anfang
 * der Rohantwort da — unschön, aber immer noch besser als ein leeres Feld
 * an genau der Stelle, an der man wissen will, was los war.
 */
function bkApiError(array $res): string {
    if ($res['error'] !== '') return $res['error'];
    $message = $res['json']['message']
        ?? $res['json']['Message']
        ?? $res['json']['error']['message']
        ?? $res['json']['Messages'][0]['Errors'][0]['ErrorMessage']   // Mailjet
        ?? $res['json']['errors'][0]['message']
        ?? substr(trim($res['body']), 0, 300);
    return 'HTTP ' . $res['status'] . ' — ' . (is_string($message) ? $message : json_encode($message));
}

/** Anhänge im Format, das alle vier Dienste verstehen: Name + Base64. */
function bkApiAttachments(array $msg): array {
    if (($msg['ics'] ?? null) === null) return [];
    return [['name' => 'termin.ics', 'content' => base64_encode($msg['ics']['content'])]];
}

/**
 * Brevo (ehemals Sendinblue) — Server in der EU, 300 Mails am Tag kostenlos,
 * deutschsprachiger Support. Für einen deutschen Betrieb die naheliegendste
 * Wahl, deshalb steht der Dienst in der Kette vorn.
 */
function bkSendBrevo(array $msg): array {
    $payload = [
        'sender' => ['email' => bkMailFrom(), 'name' => bkMailFromName()],
        'to' => [array_filter([
            'email' => $msg['to_email'],
            'name' => bkCleanName($msg['to_name']) ?: null,
        ])],
        'subject' => $msg['subject'],
        'htmlContent' => $msg['html'],
        'textContent' => $msg['text'],
    ];
    if (($msg['reply_to'] ?? null) !== null) $payload['replyTo'] = ['email' => $msg['reply_to']];
    foreach (bkApiAttachments($msg) as $a) {
        $payload['attachment'][] = ['name' => $a['name'], 'content' => $a['content']];
    }

    $res = bkApiPost('https://api.brevo.com/v3/smtp/email',
        ['api-key: ' . envValue('BREVO_API_KEY')], $payload);

    $log = ['>>> POST api.brevo.com/v3/smtp/email', '<<< HTTP ' . $res['status']];
    if ($res['status'] >= 200 && $res['status'] < 300) {
        return ['ok' => true, 'id' => (string) ($res['json']['messageId'] ?? ''), 'error' => '', 'log' => $log];
    }
    return ['ok' => false, 'id' => '', 'error' => bkApiError($res), 'log' => $log];
}

/** Resend — einfachste Einrichtung, 3.000 Mails im Monat kostenlos. */
function bkSendResend(array $msg): array {
    $payload = [
        'from' => bkAddress(bkMailFrom(), bkMailFromName()),
        'to' => [$msg['to_email']],
        'subject' => $msg['subject'],
        'html' => $msg['html'],
        'text' => $msg['text'],
    ];
    if (($msg['reply_to'] ?? null) !== null) $payload['reply_to'] = $msg['reply_to'];
    foreach (bkApiAttachments($msg) as $a) {
        $payload['attachments'][] = ['filename' => $a['name'], 'content' => $a['content']];
    }

    $res = bkApiPost('https://api.resend.com/emails',
        ['Authorization: Bearer ' . envValue('RESEND_API_KEY')], $payload);

    $log = ['>>> POST api.resend.com/emails', '<<< HTTP ' . $res['status']];
    if ($res['status'] >= 200 && $res['status'] < 300) {
        return ['ok' => true, 'id' => (string) ($res['json']['id'] ?? ''), 'error' => '', 'log' => $log];
    }
    return ['ok' => false, 'id' => '', 'error' => bkApiError($res), 'log' => $log];
}

/** Postmark — der strengste der vier, dafür die beste Zustellquote. */
function bkSendPostmark(array $msg): array {
    $payload = [
        'From' => bkAddress(bkMailFrom(), bkMailFromName()),
        'To' => bkAddress($msg['to_email'], $msg['to_name']),
        'Subject' => $msg['subject'],
        'HtmlBody' => $msg['html'],
        'TextBody' => $msg['text'],
        'MessageStream' => envValue('POSTMARK_STREAM') ?? 'outbound',
    ];
    if (($msg['reply_to'] ?? null) !== null) $payload['ReplyTo'] = $msg['reply_to'];
    foreach (bkApiAttachments($msg) as $a) {
        $payload['Attachments'][] = [
            'Name' => $a['name'], 'Content' => $a['content'], 'ContentType' => 'text/calendar',
        ];
    }

    $res = bkApiPost('https://api.postmarkapp.com/email',
        ['X-Postmark-Server-Token: ' . envValue('POSTMARK_TOKEN')], $payload);

    $log = ['>>> POST api.postmarkapp.com/email', '<<< HTTP ' . $res['status']];
    // Postmark meldet Anwendungsfehler mit HTTP 200 und ErrorCode != 0.
    $code = (int) ($res['json']['ErrorCode'] ?? 0);
    if ($res['status'] >= 200 && $res['status'] < 300 && $code === 0) {
        return ['ok' => true, 'id' => (string) ($res['json']['MessageID'] ?? ''), 'error' => '', 'log' => $log];
    }
    return ['ok' => false, 'id' => '', 'error' => bkApiError($res), 'log' => $log];
}

/** Mailjet — französischer Anbieter, 200 Mails am Tag kostenlos. */
function bkSendMailjet(array $msg): array {
    $message = [
        'From' => ['Email' => bkMailFrom(), 'Name' => bkMailFromName()],
        'To' => [array_filter([
            'Email' => $msg['to_email'],
            'Name' => bkCleanName($msg['to_name']) ?: null,
        ])],
        'Subject' => $msg['subject'],
        'TextPart' => $msg['text'],
        'HTMLPart' => $msg['html'],
    ];
    if (($msg['reply_to'] ?? null) !== null) $message['ReplyTo'] = ['Email' => $msg['reply_to']];
    foreach (bkApiAttachments($msg) as $a) {
        $message['Attachments'][] = [
            'ContentType' => 'text/calendar', 'Filename' => $a['name'], 'Base64Content' => $a['content'],
        ];
    }

    $res = bkApiPost('https://api.mailjet.com/v3.1/send', [], ['Messages' => [$message]],
        envValue('MAILJET_API_KEY') . ':' . envValue('MAILJET_SECRET_KEY'));

    $log = ['>>> POST api.mailjet.com/v3.1/send', '<<< HTTP ' . $res['status']];
    $status = $res['json']['Messages'][0]['Status'] ?? '';
    if ($res['status'] >= 200 && $res['status'] < 300 && $status === 'success') {
        return [
            'ok' => true,
            'id' => (string) ($res['json']['Messages'][0]['To'][0]['MessageUUID'] ?? ''),
            'error' => '', 'log' => $log,
        ];
    }
    return ['ok' => false, 'id' => '', 'error' => bkApiError($res), 'log' => $log];
}

// =====================================================================
// SMTP
// =====================================================================

/**
 * Schreibt den ganzen Puffer, auch wenn der Socket ihn nur häppchenweise
 * annimmt.
 *
 * fwrite() darf laut Handbuch weniger Bytes schreiben als übergeben — bei
 * TLS-Sockets passiert das ab etwa 8 KB regelmäßig. Der bisherige Code hat
 * den Rückgabewert ignoriert und in einem Rutsch geschrieben: Bei einer
 * abgeschnittenen Nachricht fehlte der Schlusspunkt, der Server wartete auf
 * mehr, und nach 15 Sekunden Zeitüberschreitung galt die Mail als
 * fehlgeschlagen — oder, schlimmer, der Server nahm ein Fragment an.
 * Terminmails liegen mit HTML-Rahmen und Base64 genau in dieser Größe.
 */
function bkStreamWrite($fp, string $data): bool {
    $length = strlen($data);
    $offset = 0;
    while ($offset < $length) {
        $written = @fwrite($fp, substr($data, $offset, 8192));
        if ($written === false || $written === 0) return false;
        $offset += $written;
    }
    return true;
}

/**
 * Liest eine — auch mehrzeilige — Serverantwort.
 *
 * @param array<int,string> $log
 * @return array{code:int, text:string}
 */
function bkSmtpRead($fp, array &$log): array {
    $code = 0;
    $lines = [];
    while (($line = fgets($fp, 2048)) !== false) {
        $line = rtrim($line, "\r\n");
        $lines[] = $line;
        $log[] = '<<< ' . $line;
        $code = (int) substr($line, 0, 3);
        // "250-" heißt: es folgen weitere Zeilen, "250 " beendet die Antwort.
        if (strlen($line) < 4 || $line[3] !== '-') break;
    }
    if ($lines === []) {
        $meta = stream_get_meta_data($fp);
        $log[] = '!!! keine Antwort' . (!empty($meta['timed_out']) ? ' (Zeitüberschreitung)' : '');
    }
    return ['code' => $code, 'text' => implode(' | ', $lines)];
}

/**
 * Verschickt über SMTP und schreibt das ganze Gespräch mit.
 *
 * Die Mitschrift ist kein Diagnose-Extra mehr, sondern fester Bestandteil:
 * Genau die Antwort auf den Schlusspunkt enthält die Vorgangsnummer, unter
 * der die Mail beim Anbieter weiterläuft. Ohne sie ist eine verschwundene
 * Mail nicht nachverfolgbar.
 */
function bkSendSmtp(array $msg): array {
    $log = [];
    $fail = static fn (string $why, array $log): array =>
        ['ok' => false, 'id' => '', 'error' => $why, 'log' => $log];

    $host   = (string) envValue('SMTP_HOST');
    $user   = (string) envValue('SMTP_USER');
    $pass   = (string) envValue('SMTP_PASS');
    $secure = strtolower(envValue('SMTP_SECURE') ?? 'ssl');
    $port   = (int) (envValue('SMTP_PORT') ?? ($secure === 'ssl' ? 465 : 587));

    $endpoint = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $log[] = '>>> verbinde mit ' . $endpoint;

    // Eigener Kontext mit peer_name: Ohne SNI liefert mancher Mailserver ein
    // Zertifikat für einen anderen Namen aus und der Handshake scheitert.
    $context = stream_context_create(['ssl' => [
        'SNI_enabled' => true,
        'peer_name' => $host,
        'verify_peer' => true,
        'verify_peer_name' => true,
    ]]);

    $fp = @stream_socket_client($endpoint, $errno, $errstr, 20,
        STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        return $fail('Verbindung zu ' . $endpoint . ' fehlgeschlagen: ' . $errstr . ' (' . $errno . ')', $log);
    }
    stream_set_timeout($fp, 20);

    // Schreibt einen Befehl mit; Zugangsdaten werden dabei unkenntlich gemacht.
    $cmd = static function (string $command, bool $secret = false) use ($fp, &$log): bool {
        $log[] = '>>> ' . ($secret ? '(Zugangsdaten, nicht protokolliert)' : $command);
        return bkStreamWrite($fp, $command . "\r\n");
    };

    try {
        $greet = bkSmtpRead($fp, $log);
        if ($greet['code'] !== 220) return $fail('Server meldet sich nicht mit 220: ' . $greet['text'], $log);

        $helo = parse_url(bkSiteUrl(), PHP_URL_HOST) ?: 'jungline.de';

        $cmd('EHLO ' . $helo);
        $ehlo = bkSmtpRead($fp, $log);
        if ($ehlo['code'] !== 250) {
            // Uralte oder eigenwillige Server können nur HELO.
            $cmd('HELO ' . $helo);
            $ehlo = bkSmtpRead($fp, $log);
            if ($ehlo['code'] !== 250) return $fail('EHLO/HELO abgelehnt: ' . $ehlo['text'], $log);
        }

        if ($secure === 'tls' || $secure === 'starttls') {
            $cmd('STARTTLS');
            $tls = bkSmtpRead($fp, $log);
            if ($tls['code'] !== 220) return $fail('STARTTLS abgelehnt: ' . $tls['text'], $log);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return $fail('TLS-Handshake fehlgeschlagen.', $log);
            }
            $log[] = '=== TLS aktiv';
            // Nach dem Wechsel auf TLS verlangt der Standard ein neues EHLO.
            $cmd('EHLO ' . $helo);
            $ehlo = bkSmtpRead($fp, $log);
            if ($ehlo['code'] !== 250) return $fail('EHLO nach TLS abgelehnt: ' . $ehlo['text'], $log);
        }

        if ($user !== '') {
            $cmd('AUTH LOGIN');
            $auth = bkSmtpRead($fp, $log);
            if ($auth['code'] !== 334) return $fail('AUTH LOGIN abgelehnt: ' . $auth['text'], $log);

            $cmd(base64_encode($user), true);
            $auth = bkSmtpRead($fp, $log);
            if ($auth['code'] !== 334) return $fail('Benutzername abgelehnt: ' . $auth['text'], $log);

            $cmd(base64_encode($pass), true);
            $auth = bkSmtpRead($fp, $log);
            if ($auth['code'] !== 235) return $fail('Passwort abgelehnt: ' . $auth['text'], $log);
        }

        $cmd('MAIL FROM:<' . bkMailFrom() . '>');
        $from = bkSmtpRead($fp, $log);
        if ($from['code'] !== 250) return $fail('Absender abgelehnt: ' . $from['text'], $log);

        $cmd('RCPT TO:<' . $msg['to_email'] . '>');
        $rcpt = bkSmtpRead($fp, $log);
        // 251 heißt "wird weitergeleitet" und ist ebenso ein Erfolg wie 250.
        if ($rcpt['code'] !== 250 && $rcpt['code'] !== 251) {
            return $fail('Empfänger abgelehnt: ' . $rcpt['text'], $log);
        }

        $cmd('DATA');
        $data = bkSmtpRead($fp, $log);
        if ($data['code'] !== 354) return $fail('DATA abgelehnt: ' . $data['text'], $log);

        $mime = bkBuildMime($msg['html'], $msg['text'], $msg['ics'] ?? null);
        $headers = [
            'Date' => date('r'),
            'From' => bkAddress(bkMailFrom(), bkMailFromName()),
            'To' => bkAddress($msg['to_email'], $msg['to_name']),
            'Subject' => bkMimeHeader($msg['subject']),
            'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . $helo . '>',
            'MIME-Version' => '1.0',
            // Sagt automatischen Antwortsystemen, dass sie hier nicht
            // antworten sollen — sonst schaukeln sich Urlaubsantworten auf.
            'Auto-Submitted' => 'auto-generated',
        ] + $mime['headers'];
        if (($msg['reply_to'] ?? null) !== null) $headers['Reply-To'] = $msg['reply_to'];

        $raw = '';
        foreach ($headers as $key => $value) $raw .= $key . ': ' . $value . "\r\n";
        $raw .= "\r\n" . $mime['body'];

        if (!bkStreamWrite($fp, bkDotStuff($raw) . "\r\n.\r\n")) {
            return $fail('Die Nachricht konnte nicht vollständig übertragen werden.', $log);
        }
        $log[] = '>>> [Nachricht, ' . strlen($raw) . ' Bytes] + Schlusspunkt';

        $done = bkSmtpRead($fp, $log);
        if ($done['code'] !== 250) return $fail('Nachricht abgelehnt: ' . $done['text'], $log);

        $cmd('QUIT');
        bkSmtpRead($fp, $log);

        return ['ok' => true, 'id' => $done['text'], 'error' => '', 'log' => $log];
    } finally {
        fclose($fp);
    }
}

// =====================================================================
// PHP mail() — der Notnagel
// =====================================================================

function bkSendPhpMail(array $msg): array {
    $mime = bkBuildMime($msg['html'], $msg['text'], $msg['ics'] ?? null);
    $host = parse_url(bkSiteUrl(), PHP_URL_HOST) ?: 'jungline.de';

    // Date und Message-ID setzt der SMTP-Weg längst; hier fehlten sie. Eine
    // Mail ohne Message-ID ist nach RFC 5322 zwar zulässig, gilt aber jedem
    // Spamfilter als Merkmal automatisch erzeugter Massenpost — und sie ist
    // hinterher nicht nachverfolgbar, weil in keinem Protokoll eine Nummer
    // steht, nach der man suchen könnte. Beides gehört in JEDEN Versandweg,
    // gerade in den Notnagel, der einspringt, wenn alles andere ausfällt.
    $headers = [
        'Date' => date('r'),
        'From' => bkAddress(bkMailFrom(), bkMailFromName()),
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . $host . '>',
        'MIME-Version' => '1.0',
        'Auto-Submitted' => 'auto-generated',
    ] + $mime['headers'];
    if (($msg['reply_to'] ?? null) !== null) $headers['Reply-To'] = $msg['reply_to'];

    $raw = '';
    foreach ($headers as $key => $value) $raw .= $key . ': ' . $value . "\r\n";

    $ok = @mail($msg['to_email'], bkMimeHeader($msg['subject']), $mime['body'],
                rtrim($raw), '-f' . bkMailFrom());

    return [
        'ok' => (bool) $ok,
        'id' => '',
        'error' => $ok ? '' : 'mail() hat die Übergabe an das Mailprogramm des Servers abgelehnt.',
        'log' => ['>>> PHP mail() an ' . $msg['to_email'], '<<< ' . ($ok ? 'übernommen' : 'abgelehnt')],
    ];
}
