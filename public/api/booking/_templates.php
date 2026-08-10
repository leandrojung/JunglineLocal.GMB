<?php
/**
 * Terminbuchung — Mailtexte.
 *
 * Alle Mails teilen sich einen Rahmen (bkEmailShell). Bewusst Tabellen-
 * Layout und ausschließlich Inline-Styles: Outlook und einige Webmailer
 * werfen <style>-Blöcke weg, und ein zerfallenes Layout in der
 * Terminbestätigung wirkt unseriöser als gar kein Design.
 *
 * Zu jeder HTML-Mail gehört eine gleichwertige Textfassung. Sie ist nicht
 * nur Barrierefreiheit, sondern auch ein Spam-Kriterium: reine HTML-Mails
 * ohne Textteil werden schlechter bewertet.
 */

declare(strict_types=1);

require_once __DIR__ . '/_config.php';
// Die Kalender-Links in den Mails kommen von dort.
require_once __DIR__ . '/_ics.php';

const BK_MAIL_INK    = '#191C21';
const BK_MAIL_DIM    = '#59647F';
const BK_MAIL_ACCENT = '#3A4E9C';
const BK_MAIL_BG     = '#F4F5FB';
const BK_MAIL_BORDER = '#C9D3EE';

function bkEsc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Die Anrede aus dem eingetippten Namen.
 *
 * Das erste Wort genügt fast immer. Gibt jemand "Jung, Klaus" ein — die
 * Reihenfolge aus jedem Behördenformular —, hängt an diesem ersten Wort ein
 * Komma, und die Mail begrüßt ihn mit "Hallo Jung,,". Satzzeichen am Ende
 * fliegen deshalb weg. Bleibt danach nichts übrig, gilt der ganze Name.
 */
function bkFirstName(array $booking): string {
    $name = trim((string) ($booking['name'] ?? ''));
    $first = trim((string) (strtok($name, ' ') ?: ''), " \t,;.:-");
    return $first !== '' ? $first : $name;
}

/**
 * @param string $preheader Vorschautext in der Mailübersicht — ohne ihn zeigt
 *                          der Client die ersten Zeichen des Layouts.
 */
function bkEmailShell(string $preheader, string $heading, string $content): string {
    $accent = BK_MAIL_ACCENT;
    $ink = BK_MAIL_INK;
    $dim = BK_MAIL_DIM;
    $bg = BK_MAIL_BG;
    $border = BK_MAIL_BORDER;
    $site = bkSiteUrl();

    return '<!doctype html><html lang="de"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . bkEsc($heading) . '</title></head>'
        . '<body style="margin:0;padding:0;background:' . $bg . ';">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . bkEsc($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $bg . ';padding:32px 16px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:1px solid ' . $border . ';border-radius:18px;overflow:hidden;">'
        . '<tr><td style="padding:28px 32px 0;">'
        . '<div style="font:700 17px/1.2 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:' . $ink . ';letter-spacing:-.01em;">'
        . 'Jungline<span style="color:' . $accent . ';">Local</span></div>'
        . '</td></tr>'
        . '<tr><td style="padding:20px 32px 0;">'
        . '<h1 style="margin:0;font:700 24px/1.25 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:' . $ink . ';letter-spacing:-.02em;">'
        . bkEsc($heading) . '</h1>'
        . '</td></tr>'
        . '<tr><td style="padding:14px 32px 32px;font:400 15px/1.62 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:' . $dim . ';">'
        . $content
        . '</td></tr>'
        . '</table>'
        . '<div style="max-width:560px;margin:18px auto 0;font:400 12px/1.6 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#8A93AC;text-align:center;">'
        . 'JunglineLocal — Leandro Jung · <a href="' . $site . '" style="color:#8A93AC;">jungline.de</a><br>'
        . '<a href="' . $site . '/impressum/" style="color:#8A93AC;">Impressum</a> · '
        . '<a href="' . $site . '/datenschutz/" style="color:#8A93AC;">Datenschutz</a>'
        . '</div>'
        . '</td></tr></table></body></html>';
}

/** Die hervorgehobene Termin-Box, die in jeder Mail gleich aussieht. */
function bkEmailFactBox(array $booking): string {
    $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());
    $end   = new DateTimeImmutable($booking['end_utc'], bkUtcTz());

    $rows = [
        ['Termin', bkFormatDate($start)],
        ['Uhrzeit', bkFormatTime($start, $end) . ' (Zeitzone Berlin)'],
        ['Dauer', BK_DURATION_LABEL],
    ];
    if (bkMeetingUrl() !== '') {
        $rows[] = ['Videoraum', '<a href="' . bkEsc(bkMeetingUrl()) . '" style="color:' . BK_MAIL_ACCENT . ';font-weight:600;">Gespräch beitreten</a>'];
    }

    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . BK_MAIL_BG . ';border:1px solid ' . BK_MAIL_BORDER . ';border-radius:14px;margin:20px 0;">';
    foreach ($rows as $i => [$label, $value]) {
        $border = $i === 0 ? '' : 'border-top:1px solid ' . BK_MAIL_BORDER . ';';
        $html .= '<tr>'
            . '<td style="' . $border . 'padding:12px 18px;width:38%;font-size:13px;color:' . BK_MAIL_DIM . ';">' . bkEsc($label) . '</td>'
            . '<td style="' . $border . 'padding:12px 18px;font-size:15px;font-weight:600;color:' . BK_MAIL_INK . ';">' . $value . '</td>'
            . '</tr>';
    }
    return $html . '</table>';
}

/**
 * EIN Link zur Kalender-Auswahlseite statt zwei einzelner Links in der Mail.
 *
 * Die Mail-Diagnose (sechs Testrunden über /mailtest) hat eine harte Schwelle
 * beim Hoster nachgewiesen: Testmails mit vier Links kamen nach der Annahme
 * ("250 queued") nie an, egal welche vier Links es waren; mit drei oder
 * weniger kamen sie zuverlässig durch. Zwei einzelne, als Button gestaltete
 * Kalender-Links rissen diese Schwelle. Beide Wege (Google Kalender,
 * Datei für Apple/Outlook) stehen jetzt auf /api/booking/calendar — die Mail
 * selbst verlinkt nur noch dorthin und bleibt damit unter der Schwelle.
 */
function bkEmailCalendarLinks(array $booking): string {
    $url = bkSiteUrl() . '/api/booking/calendar?token=' . rawurlencode($booking['token']);
    // Ohne die Aufzählung "(Google, Apple oder Outlook)": Drei große
    // Markennamen unmittelbar neben einem Link sind eine der geläufigsten
    // Phishing-Signaturen, und genau dieser Absatz hat die Bestätigung die
    // Zustellung gekostet — nachgewiesen in Runde 10 der Diagnose, wo die
    // Mail ohne ihn als einzige ankam. Welche Kalender möglich sind, sagt
    // die verlinkte Seite; in der Mail steht es nüchtern.
    return '<p style="margin:18px 0 0;font-size:13px;">Termin eintragen: '
        . '<a href="' . bkEsc($url) . '" style="color:' . BK_MAIL_ACCENT . ';">Kalendereintrag öffnen</a></p>';
}

/** Derselbe eine Link für die Nur-Text-Fassung. */
function bkTextCalendarLinks(array $booking): string {
    return "Termin eintragen:\n"
        . '  ' . bkSiteUrl() . '/api/booking/calendar?token=' . rawurlencode($booking['token']);
}

function bkEmailButton(string $href, string $label): string {
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 4px;"><tr>'
        . '<td style="background:' . BK_MAIL_ACCENT . ';border-radius:999px;">'
        . '<a href="' . bkEsc($href) . '" style="display:inline-block;padding:13px 26px;font:600 15px/1 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#FFFFFF;text-decoration:none;">'
        . bkEsc($label) . '</a></td></tr></table>';
}

/** Gemeinsamer Textblock mit den Termindaten für die Nur-Text-Fassung. */
function bkTextFacts(array $booking): string {
    $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());
    $end   = new DateTimeImmutable($booking['end_utc'], bkUtcTz());
    $lines = [
        'Termin:   ' . bkFormatDate($start),
        'Uhrzeit:  ' . bkFormatTime($start, $end) . ' (Zeitzone Berlin)',
        'Dauer:    ' . BK_DURATION_LABEL,
    ];
    if (bkMeetingUrl() !== '') $lines[] = 'Videoraum: ' . bkMeetingUrl();
    return implode("\n", $lines);
}

// =====================================================================
// 1) Bestätigung an den Kunden
// =====================================================================

function bkMailConfirmation(array $booking): array {
    $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());
    $manage = bkManageUrl($booking['token']);
    $firstName = bkFirstName($booking);

    $thema = bkTopic($booking)['thema'];

    $content = '<p style="margin:0 0 4px;">Hallo ' . bkEsc($firstName) . ',</p>'
        . '<p style="margin:0;">Ihr Termin zum Thema <b>' . bkEsc($thema) . '</b> steht. Der Videoraum-Link steht unten in der Übersicht, der Kalendereintrag ist mit einem Klick erledigt.</p>'
        . bkEmailFactBox($booking)
        . bkEmailCalendarLinks($booking);

    if (trim($booking['message']) !== '') {
        $content .= '<p style="margin:22px 0 6px;"><b style="color:' . BK_MAIL_INK . ';">Ihr Anliegen:</b></p>'
            . '<p style="margin:0;padding:12px 16px;background:' . BK_MAIL_BG . ';border-radius:12px;font-size:14px;">'
            . nl2br(bkEsc($booking['message'])) . '</p>';
    }

    $content .= '<p style="margin:24px 0 0;font-size:13px;">Passt der Termin doch nicht? '
        . '<a href="' . bkEsc($manage) . '" style="color:' . BK_MAIL_ACCENT . ';">Hier absagen oder verschieben</a> — '
        . 'kein Problem, und ohne dass Sie mir schreiben müssen.</p>'
        . '<p style="margin:18px 0 0;">Bis dahin!<br>Leandro</p>';

    $text = "Hallo " . $firstName . ",\n\n"
        . "Ihr Termin zum Thema " . $thema . " steht.\n\n"
        . bkTextFacts($booking) . "\n\n"
        . bkTextCalendarLinks($booking) . "\n\n"
        . (trim($booking['message']) !== '' ? "Ihr Anliegen:\n" . $booking['message'] . "\n\n" : '')
        . "Absagen oder verschieben: " . $manage . "\n\n"
        // Ohne Signaturblock: Die Web-Adresse darin wäre die vierte im
        // Textteil, und ab vier verwirft der Ausgangsfilter des Hosters
        // nachweislich jede Mail (Diagnose /mailtest: 0–3 Adressen kamen
        // ausnahmslos an, 4–5 ausnahmslos nicht). Absender und HTML-Fuß
        // nennen die Domain ohnehin.
        . "Bis dahin!\nLeandro\n";

    // Betreff bewusst ohne Umlaute (reines ASCII braucht keine RFC-2047-
    // Kodierung) und ohne Datum-Uhrzeit-Muster: "… am 10.08.2026 um 19:30
    // Uhr" ist die Signatur einschlägiger Terminbestätigungs-Spamwellen und
    // kostet beim Ausgangsfilter Punkte, die diese Mail sich nicht leisten
    // kann. Datum und Uhrzeit stehen prominent in der Terminbox der Mail.
    // Der Vorschautext bleibt aus demselben Grund kurz und deckungsgleich
    // mit dem sichtbaren Inhalt — versteckter Text, der etwas anderes sagt
    // als die Mail, ist ein klassisches Filtersignal.
    return [
        'subject' => 'Ihr Termin bei JunglineLocal (' . bkTopic($booking)['kurz'] . ')',
        'html' => bkEmailShell('Ihr Termin steht.', 'Ihr Termin steht', $content),
        'text' => $text,
    ];
}

// =====================================================================
// 2) Benachrichtigung an Leandro
// =====================================================================

function bkMailOwnerNotice(array $booking, string $warning = ''): array {
    $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());

    $details = [
        // Zuerst das Thema: es entscheidet, womit man in das Gespraech geht.
        ['Thema', bkTopic($booking)['label']],
        ['Name', $booking['name']],
        ['E-Mail', $booking['email']],
        ['Telefon', $booking['phone'] !== '' ? $booking['phone'] : '—'],
        ['Firma / Ort', $booking['company'] !== '' ? $booking['company'] : '—'],
    ];

    $content = '<p style="margin:0;">Neue Buchung über die Website.</p>'
        . bkEmailFactBox($booking)
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
    foreach ($details as $i => [$label, $value]) {
        $border = $i === 0 ? '' : 'border-top:1px solid ' . BK_MAIL_BORDER . ';';
        $content .= '<tr>'
            . '<td style="' . $border . 'padding:10px 0;width:34%;font-size:13px;color:' . BK_MAIL_DIM . ';">' . bkEsc($label) . '</td>'
            . '<td style="' . $border . 'padding:10px 0;font-size:15px;color:' . BK_MAIL_INK . ';">' . bkEsc($value) . '</td>'
            . '</tr>';
    }
    $content .= '</table>';

    if (trim($booking['message']) !== '') {
        $content .= '<p style="margin:20px 0 6px;"><b style="color:' . BK_MAIL_INK . ';">Anliegen:</b></p>'
            . '<p style="margin:0;padding:12px 16px;background:' . BK_MAIL_BG . ';border-radius:12px;font-size:14px;">'
            . nl2br(bkEsc($booking['message'])) . '</p>';
    }

    if ($warning !== '') {
        $content .= '<p style="margin:20px 0 0;padding:12px 16px;background:#FFF4E5;border-radius:12px;font-size:14px;color:#7A4A10;">'
            . bkEsc($warning) . '</p>';
    }

    $content .= '<p style="margin:22px 0 0;font-size:13px;">'
        . '<a href="' . bkEsc(bkManageUrl($booking['token'])) . '" style="color:' . BK_MAIL_ACCENT . ';">Termin absagen</a></p>';

    $text = "Neue Buchung über die Website.\n\n"
        . bkTextFacts($booking) . "\n\n"
        . "Name:    " . $booking['name'] . "\n"
        . "E-Mail:  " . $booking['email'] . "\n"
        . "Telefon: " . ($booking['phone'] !== '' ? $booking['phone'] : '—') . "\n"
        . "Firma:   " . ($booking['company'] !== '' ? $booking['company'] : '—') . "\n"
        . "Thema:   " . bkTopic($booking)['label'] . "\n\n"
        . (trim($booking['message']) !== '' ? "Anliegen:\n" . $booking['message'] . "\n\n" : '')
        . ($warning !== '' ? "ACHTUNG: " . $warning . "\n\n" : '')
        . "Absagen: " . bkManageUrl($booking['token']) . "\n";

    return [
        'subject' => 'Neue Buchung (' . bkTopic($booking)['kurz'] . '): ' . $booking['name'] . ' - ' . bkLocal($start)->format('d.m.Y, H:i') . ' Uhr',
        'html' => bkEmailShell('Neue Buchung von ' . $booking['name'], 'Neue Buchung', $content),
        'text' => $text,
    ];
}

// =====================================================================
// 3) Erinnerung an den Kunden (siehe remind.php)
// =====================================================================

function bkMailReminder(array $booking): array {
    $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());
    $firstName = bkFirstName($booking);

    $content = '<p style="margin:0 0 4px;">Hallo ' . bkEsc($firstName) . ',</p>'
        . '<p style="margin:0;">kurze Erinnerung an unser Gespräch morgen. Der Videoraum-Link steht unten in der Übersicht.</p>'
        . bkEmailFactBox($booking);

    $content .= '<p style="margin:22px 0 0;font-size:13px;">Sollte etwas dazwischenkommen: '
        . '<a href="' . bkEsc(bkManageUrl($booking['token'])) . '" style="color:' . BK_MAIL_ACCENT . ';">absagen oder verschieben</a>.</p>'
        . '<p style="margin:18px 0 0;">Bis morgen!<br>Leandro</p>';

    $text = "Hallo " . $firstName . ",\n\n"
        . "kurze Erinnerung an unser Gespräch morgen.\n\n"
        . bkTextFacts($booking) . "\n\n"
        . "Absagen oder verschieben: " . bkManageUrl($booking['token']) . "\n\n"
        . "Bis morgen!\nLeandro\n";

    // Wie bei der Bestätigung: Betreff ohne Umlaute, keine Kodierung nötig.
    return [
        'subject' => 'Erinnerung: Ihr Termin morgen um ' . bkLocal($start)->format('H:i') . ' Uhr',
        'html' => bkEmailShell('Erinnerung an Ihren Termin', 'Morgen sprechen wir', $content),
        'text' => $text,
    ];
}

// =====================================================================
// 4) Absagebestätigung
// =====================================================================

function bkMailCancelled(array $booking, bool $toOwner): array {
    $start = new DateTimeImmutable($booking['start_utc'], bkUtcTz());
    $bookingUrl = bkSiteUrl() . '/kontakt/#termin';

    if ($toOwner) {
        $content = '<p style="margin:0;">Dieser Termin wurde abgesagt — der Slot ist wieder frei.</p>'
            . bkEmailFactBox($booking)
            . '<p style="margin:0;font-size:14px;">' . bkEsc($booking['name']) . ' · ' . bkEsc($booking['email']) . '</p>';
        $text = "Termin abgesagt — der Slot ist wieder frei.\n\n"
            . bkTextFacts($booking) . "\n\n"
            . $booking['name'] . " · " . $booking['email'] . "\n";
        return [
            'subject' => 'Absage (' . bkTopic($booking)['kurz'] . '): ' . $booking['name'] . ' - ' . bkLocal($start)->format('d.m.Y, H:i') . ' Uhr',
            'html' => bkEmailShell('Ein Termin wurde abgesagt', 'Termin abgesagt', $content),
            'text' => $text,
        ];
    }

    $firstName = bkFirstName($booking);
    $content = '<p style="margin:0 0 4px;">Hallo ' . bkEsc($firstName) . ',</p>'
        . '<p style="margin:0;">Ihr Termin ist abgesagt — Sie müssen nichts weiter tun. Bitte denken Sie daran, ihn auch in Ihrem eigenen Kalender zu löschen.</p>'
        . bkEmailFactBox($booking)
        . '<p style="margin:0;">Wenn Sie mögen, suchen Sie sich einfach einen neuen Termin aus:</p>'
        . bkEmailButton($bookingUrl, 'Neuen Termin wählen')
        . '<p style="margin:18px 0 0;">Viele Grüße<br>Leandro</p>';

    $text = "Hallo " . $firstName . ",\n\n"
        . "Ihr Termin ist abgesagt:\n\n"
        . bkTextFacts($booking) . "\n\n"
        . "Neuen Termin wählen: " . $bookingUrl . "\n\n"
        . "Viele Grüße\nLeandro\n";

    return [
        'subject' => 'Termin abgesagt: ' . bkLocal($start)->format('d.m.Y, H:i') . ' Uhr',
        'html' => bkEmailShell('Ihr Termin wurde abgesagt', 'Termin abgesagt', $content),
        'text' => $text,
    ];
}
