<?php
/**
 * Terminbuchung — .ics-Erzeugung.
 *
 * Der Anhang ist der Grund, warum der Termin beim Kunden mit einem Klick im
 * eigenen Kalender landet — genau das, was Calendly mitschickt. METHOD
 * unterscheidet Einladung (REQUEST) von Absage (CANCEL); bei der Absage
 * zählt die SEQUENCE hoch und die UID bleibt gleich, sonst erkennt kein
 * Kalenderprogramm, dass es sich um denselben Termin handelt.
 */

declare(strict_types=1);

require_once __DIR__ . '/_config.php';

/**
 * Nach RFC 5545 darf eine Zeile höchstens 75 Oktette lang sein; längere
 * werden umgebrochen und mit einem Leerzeichen fortgesetzt. Ohne das
 * verwerfen strenge Clients (Outlook) die Datei kommentarlos.
 *
 * Gezählt wird in Oktetten, umgebrochen aber nur zwischen Zeichen: "ä"
 * belegt zwei Bytes, und ein Umbruch mitten hinein macht aus dem Umlaut in
 * jedem Kalenderprogramm zwei Fragezeichen. Genau deshalb hier ein
 * zeichenweiser Durchlauf statt eines einfachen substr().
 */
function bkIcsFold(string $line): string {
    if (strlen($line) <= 73) return $line;

    $out = '';
    $current = '';
    $limit = 73;   // erste Zeile; Folgezeilen beginnen mit einem Leerzeichen
    foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
        if (strlen($current) + strlen($char) > $limit) {
            $out .= ($out === '' ? '' : "\r\n ") . $current;
            $current = '';
            $limit = 72;
        }
        $current .= $char;
    }
    if ($current !== '') $out .= ($out === '' ? '' : "\r\n ") . $current;
    return $out;
}

/** Sonderzeichen maskieren, wie es der Standard für TEXT-Werte verlangt. */
function bkIcsEscape(string $value): string {
    return str_replace(
        ['\\', ';', ',', "\r\n", "\n", "\r"],
        ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
        $value
    );
}

function bkIcsUid(string $token): string {
    return $token . '@jungline.de';
}

/** Adresse, unter der ein Kunde die Kalenderdatei zu seinem Termin abholt. */
function bkIcsUrl(string $token): string {
    return bkSiteUrl() . '/api/booking/ics?token=' . rawurlencode($token);
}

/**
 * Fertiger "Eintragen"-Link für Google Kalender. Der Weg über eine Adresse
 * statt über eine Datei ist auf dem Handy der kürzere: ein Fingertipp, und
 * der Termin steht — kein Download, kein Öffnen-mit, keine App-Auswahl.
 */
function bkGoogleCalendarUrl(array $booking): string {
    $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());
    $end   = new DateTimeImmutable($booking['end_utc'], bkUtcTz());

    $details = ['Kostenloses Erstgespräch zur Optimierung Ihres Google-Unternehmensprofils.'];
    if (bkMeetingUrl() !== '') $details[] = 'Videoraum: ' . bkMeetingUrl();
    $details[] = 'Absagen oder verschieben: ' . bkManageUrl($booking['token']);

    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE',
        'text' => BK_TITLE,
        'dates' => $start->format('Ymd\THis\Z') . '/' . $end->format('Ymd\THis\Z'),
        'details' => implode("\n\n", $details),
        'location' => bkMeetingUrl(),
    ], '', '&', PHP_QUERY_RFC3986);
}

/**
 * @param array  $booking  Datensatz aus dem Speicher
 * @param string $method   PUBLISH (Kalenderdatei zum Ablegen), REQUEST
 *                         (förmliche Einladung) oder CANCEL (Absage)
 *
 * PUBLISH ist der Normalfall für den Download: Der Kunde legt einen Termin
 * in seinen eigenen Kalender, er wird nicht um Zu- oder Absage gebeten.
 * ORGANIZER und ATTENDEE entfallen dabei — mit ihnen macht mancher Client
 * aus der Datei eine Einladung samt Antwortknöpfen, die bei uns ins Leere
 * liefe, weil wir Antworten gar nicht auswerten.
 */
function bkIcs(array $booking, string $method = 'REQUEST'): string {
    $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());
    $end   = new DateTimeImmutable($booking['end_utc'], bkUtcTz());
    $stamp = bkNow()->format('Ymd\THis\Z');

    $cancelled = $method === 'CANCEL';

    $description = [
        'Kostenloses Erstgespräch zur Optimierung Ihres Google-Unternehmensprofils.',
    ];
    if (bkMeetingUrl() !== '') {
        $description[] = '';
        $description[] = 'Videoraum: ' . bkMeetingUrl();
    }
    $description[] = '';
    $description[] = 'Termin absagen oder verschieben: ' . bkManageUrl($booking['token']);

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//JunglineLocal//Terminbuchung//DE',
        'CALSCALE:GREGORIAN',
        'METHOD:' . $method,
        'BEGIN:VEVENT',
        'UID:' . bkIcsUid($booking['token']),
        'SEQUENCE:' . ($cancelled ? 1 : 0),
        'DTSTAMP:' . $stamp,
        'DTSTART:' . $start->format('Ymd\THis\Z'),
        'DTEND:' . $end->format('Ymd\THis\Z'),
        'SUMMARY:' . bkIcsEscape(BK_TITLE),
        'DESCRIPTION:' . bkIcsEscape(implode("\n", $description)),
        'STATUS:' . ($cancelled ? 'CANCELLED' : 'CONFIRMED'),
    ];
    if ($method !== 'PUBLISH') {
        $lines[] = 'ORGANIZER;CN=' . bkIcsEscape(bkOwnerName()) . ':mailto:' . bkOwnerEmail();
        $lines[] = 'ATTENDEE;CN=' . bkIcsEscape($booking['name'])
            . ';ROLE=REQ-PARTICIPANT;PARTSTAT=' . ($cancelled ? 'DECLINED' : 'ACCEPTED')
            . ':mailto:' . $booking['email'];
    }
    if (bkMeetingUrl() !== '') {
        $lines[] = 'LOCATION:' . bkIcsEscape(bkMeetingUrl());
        $lines[] = 'URL:' . bkMeetingUrl();
    }
    if (!$cancelled) {
        // Erinnerung 30 Minuten vorher direkt im Kalender des Kunden —
        // unabhängig von unserer eigenen Erinnerungsmail.
        $lines[] = 'BEGIN:VALARM';
        $lines[] = 'TRIGGER:-PT30M';
        $lines[] = 'ACTION:DISPLAY';
        $lines[] = 'DESCRIPTION:' . bkIcsEscape(BK_TITLE);
        $lines[] = 'END:VALARM';
    }
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", array_map('bkIcsFold', $lines)) . "\r\n";
}
