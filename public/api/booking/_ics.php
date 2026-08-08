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

/**
 * @param array  $booking  Datensatz aus dem Speicher
 * @param string $method   REQUEST (Einladung) oder CANCEL (Absage)
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
        'ORGANIZER;CN=' . bkIcsEscape(bkOwnerName()) . ':mailto:' . bkOwnerEmail(),
        'ATTENDEE;CN=' . bkIcsEscape($booking['name'])
            . ';ROLE=REQ-PARTICIPANT;PARTSTAT=' . ($cancelled ? 'DECLINED' : 'ACCEPTED')
            . ':mailto:' . $booking['email'],
    ];
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
