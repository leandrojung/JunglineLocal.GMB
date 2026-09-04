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
 * WARUM IN DER BESTAETIGUNG NUR NOCH EIN EINZIGER LINK STEHT
 * ---------------------------------------------------------
 * Die Bestaetigung war die einzige Kundenmail, die beim Empfaenger nicht
 * ankam — Absage und Erinnerung kamen an. Der Vergleich der drei Mails
 * zeigt genau einen Unterschied, und der ist messbar:
 *
 *   Absage (kommt an):      2 Adressen im Text, 5 im HTML, kein Token
 *   Bestaetigung (kam nie): 3 Adressen im Text, 6 im HTML, ZWEI Adressen
 *                           mit je 32 Zeichen Zufallstoken
 *
 * Beides zusammen ist die Signatur, auf die Ausgangsfilter anspringen: die
 * hoechste Linkzahl aller Mails des Systems, und als einzige mit langen
 * Zufallsketten in der Adresse. Die Mail wurde angenommen ("250 queued")
 * und danach verworfen — ohne Bounce, weshalb im Protokoll nichts stand.
 *
 * Die Bestaetigung fuehrt deshalb jetzt genau EINEN Link: die Terminseite.
 * Eintragen, verschieben und absagen liegen dort gemeinsam hinter einer
 * Adresse. Damit hat die Bestaetigung dasselbe Profil wie die Absage, die
 * nachweislich ankommt.
 *
 * Im HTML-Teil steht dieser Link als Knopf (bkEmailButton), in der
 * Textfassung als beschriftete Adresse — hier.
 */
function bkTextManageLink(array $booking, string $label): string {
    return $label . ":\n  " . bkManageUrl($booking['token']);
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
    $firstName = bkFirstName($booking);
    $thema = bkTopic($booking)['thema'];

    $content = '<p style="margin:0 0 4px;">Hallo ' . bkEsc($firstName) . ',</p>'
        . '<p style="margin:0;">Ihr Termin zum Thema <b>' . bkEsc($thema) . '</b> steht. '
        . 'Alles Wichtige steht in der Übersicht — den Videoraum finden Sie dort ebenfalls.</p>'
        . bkEmailFactBox($booking)
        // Als Knopf, nicht als Textlink: Es ist der einzige Link der Mail und
        // die einzige Handlung, die der Empfänger hier hat. Zusätzliche
        // Adressen entstehen dadurch nicht — es bleibt derselbe eine Link.
        . bkEmailButton(bkManageUrl($booking['token']), 'Termin in den Kalender eintragen')
        . '<p style="margin:10px 0 0;font-size:13px;">Auf derselben Seite können Sie den Termin '
        . 'verschieben oder absagen — ohne dass Sie mir schreiben müssen.</p>'
        . '<p style="margin:18px 0 0;">Am Tag vor unserem Gespräch bekommen Sie von mir noch eine kurze Erinnerung.</p>'
        . '<p style="margin:18px 0 0;">Bis dahin!<br>Leandro</p>';

    // Die Nachricht des Kunden wird hier bewusst NICHT wiederholt. Sie steht
    // in der Benachrichtigung an Leandro, wo sie gebraucht wird. In der
    // Bestätigung bringt sie dem Absender nichts — er hat sie selbst
    // geschrieben —, blaeht die Mail auf und traegt beliebigen Fremdtext in
    // eine ausgehende Nachricht. Genau das bewerten Ausgangsfilter.
    $text = "Hallo " . $firstName . ",\n\n"
        . "Ihr Termin zum Thema " . $thema . " steht.\n\n"
        . bkTextFacts($booking) . "\n\n"
        . bkTextManageLink($booking, 'Termin eintragen, verschieben oder absagen') . "\n\n"
        . "Am Tag vor unserem Gespräch bekommen Sie von mir noch eine kurze Erinnerung.\n\n"
        // Ohne Signaturblock: Die Web-Adresse darin waere die dritte im
        // Textteil. Absender und HTML-Fuss nennen die Domain ohnehin.
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
    // Zur Seite des gebuchten Themas, nicht pauschal in den SEO-Bereich:
    // ein Webdesign-Kunde soll seinen neuen Termin dort suchen, wo er den
    // alten gebucht hat.
    $bookingUrl = bkBookingPageUrl($booking);

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

// =====================================================================
// 5) Kontaktformular
//
// Diese beiden Vorlagen gehören zum Kontaktformular (/api/contact) und
// nicht zur Terminbuchung. Sie stehen trotzdem hier: Rahmen, Farben,
// Maskierung und Anrede liegen in dieser Datei, und zwei Sätze Mailtexte
// mit zwei Sätzen Design zu beantworten wäre der schlechtere Tausch. Wer
// das Aussehen der Mails ändert, ändert es hier für alle.
// =====================================================================

/**
 * Die Nachricht selbst — geht an Leandro, mit dem Absender als Reply-To.
 *
 * @param array $anfrage name, email, phone, message, quelle
 */
function bkMailContactNotice(array $anfrage): array {
    $zeilen = [
        ['Name', $anfrage['name']],
        ['E-Mail', $anfrage['email']],
        ['Telefon', $anfrage['phone'] !== '' ? $anfrage['phone'] : '—'],
        ['Kam von', $anfrage['quelle'] !== '' ? $anfrage['quelle'] : '—'],
    ];

    $content = '<p style="margin:0;">Neue Nachricht über das Kontaktformular.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 0;">';
    foreach ($zeilen as $i => [$label, $wert]) {
        $border = $i === 0 ? '' : 'border-top:1px solid ' . BK_MAIL_BORDER . ';';
        $content .= '<tr>'
            . '<td style="' . $border . 'padding:10px 0;width:34%;font-size:13px;color:' . BK_MAIL_DIM . ';">' . bkEsc($label) . '</td>'
            . '<td style="' . $border . 'padding:10px 0;font-size:15px;color:' . BK_MAIL_INK . ';">' . bkEsc($wert) . '</td>'
            . '</tr>';
    }
    $content .= '</table>'
        . '<p style="margin:22px 0 6px;"><b style="color:' . BK_MAIL_INK . ';">Nachricht:</b></p>'
        . '<p style="margin:0;padding:12px 16px;background:' . BK_MAIL_BG . ';border-radius:12px;font-size:14px;">'
        . nl2br(bkEsc($anfrage['message'])) . '</p>'
        . '<p style="margin:22px 0 0;font-size:13px;">Ein Klick auf „Antworten" geht direkt an '
        . bkEsc($anfrage['email']) . '.</p>';

    if (($anfrage['warnung'] ?? '') !== '') {
        $content .= '<p style="margin:20px 0 0;padding:12px 16px;background:#FFF4E5;border-radius:12px;font-size:14px;color:#7A4A10;">'
            . bkEsc($anfrage['warnung']) . '</p>';
    }

    $text = "Neue Nachricht über das Kontaktformular.\n\n"
        . "Name:    " . $anfrage['name'] . "\n"
        . "E-Mail:  " . $anfrage['email'] . "\n"
        . "Telefon: " . ($anfrage['phone'] !== '' ? $anfrage['phone'] : '—') . "\n"
        . "Kam von: " . ($anfrage['quelle'] !== '' ? $anfrage['quelle'] : '—') . "\n\n"
        . "Nachricht:\n" . $anfrage['message'] . "\n"
        . (($anfrage['warnung'] ?? '') !== '' ? "\nACHTUNG: " . $anfrage['warnung'] . "\n" : '');

    // Der Name steht im Betreff, damit die Mail in der Übersicht zuzuordnen
    // ist. Umlaute darin kodiert bkMimeHeader() beim Versand.
    return [
        'subject' => 'Nachricht über jungline.de: ' . $anfrage['name'],
        'html' => bkEmailShell('Neue Nachricht von ' . $anfrage['name'], 'Neue Nachricht', $content),
        'text' => $text,
    ];
}

/**
 * Die Eingangsbestätigung an den Absender.
 *
 * Bewusst kurz und OHNE die Nachricht des Absenders. Zwei Gründe: Erstens
 * kennt er sie — er hat sie gerade geschrieben. Zweitens ist eine
 * automatische Antwort, die beliebigen Fremdtext zurück ins Netz trägt, ein
 * Missbrauchsweg (jemand tippt Werbung ein und lässt sie von unserem Server
 * verschicken) und obendrein genau das, worauf Ausgangsfilter anspringen.
 *
 * Kein zusätzlicher Link im Text: Es bleibt bei den drei Adressen des
 * Fußzeilen-Rahmens, dem Profil, mit dem die Mails dieses Systems
 * nachweislich durchkommen.
 */
function bkMailContactReceipt(array $anfrage): array {
    $vorname = bkFirstName($anfrage);

    $content = '<p style="margin:0 0 4px;">Hallo ' . bkEsc($vorname) . ',</p>'
        . '<p style="margin:0;">Ihre Nachricht ist bei mir angekommen. Ich lese sie persönlich und '
        . 'antworte werktags innerhalb von 24 Stunden.</p>'
        . '<p style="margin:18px 0 0;">Wenn es eilig ist, rufen Sie mich gern direkt an: '
        . '<b style="color:' . BK_MAIL_INK . ';">+49 176 55769680</b>.</p>'
        . '<p style="margin:18px 0 0;">Viele Grüße<br>Leandro</p>';

    $text = "Hallo " . $vorname . ",\n\n"
        . "Ihre Nachricht ist bei mir angekommen. Ich lese sie persönlich und\n"
        . "antworte werktags innerhalb von 24 Stunden.\n\n"
        . "Wenn es eilig ist, rufen Sie mich gern direkt an: +49 176 55769680.\n\n"
        . "Viele Grüße\nLeandro\n";

    // Betreff ohne Umlaute: reines ASCII braucht keine RFC-2047-Kodierung.
    return [
        'subject' => 'Ihre Nachricht ist angekommen',
        'html' => bkEmailShell('Ihre Nachricht ist angekommen.', 'Danke für Ihre Nachricht', $content),
        'text' => $text,
    ];
}
