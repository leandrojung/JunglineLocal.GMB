<?php
/**
 * Mail-Diagnose — /api/booking/mailtest?token=<BOOKING_CRON_TOKEN>
 *
 * Eine Seite, vier Antworten:
 *
 *   1. WELCHER WEG?  Welche Versandwege sind eingerichtet, in welcher
 *      Reihenfolge werden sie probiert.
 *   2. DARF ICH DAS? Ob die Absenderdomain sich gegenüber Gmail & Co.
 *      ausweisen kann — SPF, DKIM, DMARC. Fehlt davon etwas, ist das der
 *      Grund, warum eine Mail bei dem einen ankommt und beim nächsten nicht.
 *   3. IST SIE ANGEKOMMEN? Was der Maildienst selbst zu jeder Mail sagt —
 *      zugestellt, geblockt, im Spam, Adresse falsch. Das eigene Protokoll
 *      kann nur "angenommen" melden; ob sie im Postfach landet, weiß nur er.
 *   4. WAS IST PASSIERT? Das Protokoll der zuletzt verschickten Mails mit
 *      Weg, Vorgangsnummer und Fehlergrund.
 *   5. GEHT ES JETZT?  Mit &to=<adresse> werden Bestätigung, Erinnerung und
 *      Absage genau so verschickt, wie eine echte Buchung sie verschickt.
 *
 * Weitere Schalter:
 *   &pruefe=<adresse>  was Brevo zu genau dieser Adresse sagt, inklusive
 *                      der Frage, ob sie auf Brevos Sperrliste steht
 *   &resend=<Nr>  eine bestimmte Mail aus dem Protokoll erneut verschicken
 *   &flush=1      liegengebliebene Mails sofort erneut versuchen
 *
 * Geschützt mit demselben Token wie der Erinnerungs-Cron — ohne Schutz
 * könnte jeder Fremde über diese Route Mails auslösen.
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

function bkDiagLine(string $char = '-'): void {
    echo str_repeat($char, 72) . "\n";
}

function bkDiagHead(string $title): void {
    echo "\n";
    bkDiagLine('=');
    echo $title . "\n";
    bkDiagLine('=');
}

// =====================================================================
// 1) Versandwege
// =====================================================================

echo "Mail-Diagnose " . date('d.m.Y H:i:s') . "\n";

bkDiagHead('1) VERSANDWEGE');

$alle = ['brevo', 'resend', 'postmark', 'mailjet', 'smtp', 'mail'];
foreach ($alle as $id) {
    printf("  %-9s %-38s %s\n", $id, bkTransportLabel($id),
        bkTransportConfigured($id) ? 'eingerichtet' : '—');
}

$chain = bkTransportChain();
echo "\n  Reihenfolge: " . ($chain === [] ? 'KEINER — es kann nichts verschickt werden!' : implode(' → ', $chain)) . "\n";
echo "  Absender:    " . bkAddress(bkMailFrom(), bkMailFromName()) . "\n";

// ---- Funktioniert der Schlüssel von DIESEM Server aus? ----------------
//
// "eingerichtet" oben heißt nur: ein Schlüssel steht in der .env. Ob Brevo
// ihn annimmt, ist eine andere Frage — und genau daran hing wochenlang
// alles: Brevo wies jeden Aufruf mit "401 unrecognised IP address" ab, der
// Versand fiel still auf SMTP zurück, und die Bestätigungen verschwanden
// dort. Deshalb steht diese Prüfung jetzt ganz oben und nicht im Kleinen.
if (bkTransportConfigured('brevo')) {
    $konto = bkBrevoAccountCheck();
    echo "\n  Brevo-Schlüssel: ";
    if ($konto['ok']) {
        echo "FUNKTIONIERT" . ($konto['konto'] !== '' ? " (" . $konto['konto'] . ")" : '') . "\n";
    } else {
        echo "WIRD ABGELEHNT\n";
        echo "  " . $konto['error'] . "\n";
        echo "\n";
        echo "  ==> SOLANGE DAS HIER STEHT, GEHT KEINE EINZIGE MAIL ÜBER BREVO.\n";
        echo "      Alles fällt still auf SMTP zurück — also auf genau den Weg,\n";
        echo "      der Bestätigungen ohne Fehlermeldung verschluckt.\n";
        if (str_contains(strtolower($konto['error']), 'ip address')) {
            echo "\n";
            echo "      Das ist die IP-Freigabe in deinem Brevo-Konto. Die IP dieses\n";
            echo "      Servers muss dort eingetragen (oder die Sperre abgeschaltet)\n";
            echo "      werden: https://app.brevo.com/security/authorised_ips\n";
        }
    }
}

// Die Warnung haengt bewusst an bkHasVerifiedTransport() und nicht daran,
// ob ueberhaupt etwas eingerichtet ist. Genau das war die Luecke: Mit
// gesetzten SMTP-Daten sah die Zeile "Reihenfolge: smtp → mail" gesund aus,
// obwohl niemand sagen konnte, ob eine Mail ankommt — das Hoster-Postfach
// meldet zu jeder Nachricht Erfolg, auch zu denen, die es wegwirft.
if (!bkHasVerifiedTransport()) {
    echo "\n  ACHTUNG: Es ist kein Maildienst eingerichtet.\n";
    echo "  " . ($chain === [] || $chain === ['mail']
        ? "Es gibt derzeit gar keinen belastbaren Versandweg."
        : "Der Versand laeuft ueber das Postfach des Hosters.") . "\n";
    echo "  Dieses Postfach nimmt JEDE Mail an (\"250 Ok: queued\") und verwirft\n";
    echo "  danach lautlos, was ihm nicht passt — ohne Fehler, ohne Bounce. Ob\n";
    echo "  eine Bestätigung angekommen ist, ist damit nicht feststellbar.\n";
    echo "  Abhilfe: EIN Schlüssel eines Maildienstes in die .env. Anleitung in\n";
    echo "  TERMINBUCHUNG.md, Abschnitt 1 — etwa zehn Minuten Arbeit.\n";
}

// =====================================================================
// 2) Ausweis der Absenderdomain
// =====================================================================

bkDiagHead('2) SPF, DKIM UND DMARC DER ABSENDERDOMAIN');

$domain = strtolower(trim((string) strrchr(bkMailFrom(), '@'), '@'));

/**
 * Zeitbudget für den DNS-Abschnitt.
 *
 * dns_get_record() lässt sich nicht abbrechen: Antwortet für einen Namen
 * niemand, hängt der Aufruf, bis PHP das Zeitlimit reißt — und ausgerechnet
 * die Seite, die im Störungsfall helfen soll, wäre dann selbst nicht
 * erreichbar. Bremsen lässt sich deshalb nur die ANZAHL der Abfragen:
 * Zwischen zwei Abfragen wird geprüft, ob das Budget noch reicht.
 *
 * SPF und DMARC sind je genau eine Abfrage und bleiben deshalb immer drin.
 * Die DKIM-Suche braucht ein Dutzend und läuft nur auf Zuruf (&dkim=1).
 */
function bkDnsFrist(): bool {
    static $ende = null;
    $ende ??= microtime(true) + 12.0;
    return microtime(true) < $ende;
}

/**
 * Liest TXT- und CNAME-Einträge eines Namens. Lange TXT-Einträge kommen in
 * Häppchen von 255 Zeichen — 'entries' setzt sie wieder zusammen.
 *
 * @return array<int,string>
 */
function bkDiagDns(string $name): array {
    if (!function_exists('dns_get_record') || !bkDnsFrist()) return [];
    $records = @dns_get_record($name, DNS_TXT | DNS_CNAME);
    if (!is_array($records)) return [];

    $out = [];
    foreach ($records as $r) {
        if (isset($r['entries']) && is_array($r['entries'])) {
            $out[] = implode('', $r['entries']);
        } elseif (isset($r['txt'])) {
            $out[] = (string) $r['txt'];
        } elseif (isset($r['target'])) {
            $out[] = 'CNAME → ' . $r['target'];
        }
    }
    return $out;
}

if (!function_exists('dns_get_record')) {
    echo "  Die DNS-Prüfung ist auf diesem Server nicht möglich\n";
    echo "  (dns_get_record ist abgeschaltet). Ersatzweise prüfen unter\n";
    echo "  https://www.mail-tester.com — dort eine Testmail hinschicken.\n";
} else {
    echo "  Domain: " . $domain . "\n\n";

    // --- SPF: welche Server dürfen für diese Domain versenden? ---
    $spf = array_values(array_filter(bkDiagDns($domain),
        static fn (string $t): bool => str_starts_with(strtolower($t), 'v=spf1')));
    echo "  SPF    ";
    if ($spf === [] && !bkDnsFrist()) {
        echo "nicht prüfbar — der Namensserver antwortet nicht.\n";
    } elseif ($spf === []) {
        echo "FEHLT — ohne SPF stuft Gmail jede Mail als verdächtig ein.\n";
    } elseif (count($spf) > 1) {
        echo "FEHLERHAFT: " . count($spf) . " SPF-Einträge. Erlaubt ist genau EINER;\n";
        echo "         bei mehreren gilt die Prüfung als fehlgeschlagen. Zusammenführen.\n";
    } else {
        echo "vorhanden\n         " . $spf[0] . "\n";
        if (!str_contains($spf[0], '-all') && !str_contains($spf[0], '~all')) {
            echo "         Hinweis: Der Eintrag endet nicht auf ~all oder -all.\n";
        }

        // --- Nennt der SPF-Eintrag den Dienst, über den wir verschicken? ---
        //
        // Diese Prüfung kostet keine einzige zusätzliche DNS-Abfrage — sie
        // liest nur den Eintrag, der ohnehin schon da ist. Sie schließt aber
        // genau die Lücke, an der eine halb fertige Einrichtung hängen
        // bleibt: Wer bei Brevo die Domain anlegt, bekommt dort DKIM- und
        // DMARC-Einträge zum Eintragen — und übersieht, dass auch der
        // SPF-Eintrag um den Dienst ergänzt werden muss. Der Eintrag sieht
        // danach vollständig aus und nennt trotzdem nur den Hoster.
        $spfDienste = [
            'brevo' => ['spf.brevo.com', 'spf.sendinblue.com'],
            'resend' => ['amazonses.com', '_spf.resend.com'],
            'postmark' => ['spf.mtasv.net'],
            'mailjet' => ['spf.mailjet.com'],
        ];
        foreach ($spfDienste as $dienst => $eintraege) {
            if (!bkTransportConfigured($dienst)) continue;

            $genannt = false;
            foreach ($eintraege as $eintrag) {
                if (str_contains(strtolower($spf[0]), $eintrag)) { $genannt = true; break; }
            }
            if ($genannt) {
                echo "         " . $dienst . " ist im SPF-Eintrag genannt.\n";
            } else {
                echo "\n         ACHTUNG: Verschickt wird über " . $dienst . ", aber der\n";
                echo "         SPF-Eintrag nennt den Dienst nicht (erwartet: include:"
                    . $eintraege[0] . ").\n";
                echo "         Solange nur DKIM stimmt, geht das oft gut; fehlt beides,\n";
                echo "         fällt jede Mail durch DMARC. Ergänzen im hPanel unter\n";
                echo "         Domains -> DNS-Zonenverwaltung.\n";
            }
        }

        // Der Rückkanal eines DMARC-Eintrags verrät, mit welchem Dienst die
        // Einrichtung begonnen wurde. Steht dort ein Anbieter, für den hier
        // gar kein Schlüssel hinterlegt ist, wurde die Einrichtung im DNS
        // angefangen und in der .env nicht zu Ende gebracht — der Versand
        // läuft dann weiter über den Hoster.
    }

    // --- DMARC: was soll mit Mails passieren, die durchfallen? ---
    $dmarc = array_values(array_filter(bkDiagDns('_dmarc.' . $domain),
        static fn (string $t): bool => str_starts_with(strtolower($t), 'v=dmarc1')));
    echo "\n  DMARC  ";
    if ($dmarc === [] && !bkDnsFrist()) {
        echo "nicht prüfbar — der Namensserver antwortet nicht.\n";
    } elseif ($dmarc === []) {
        echo "FEHLT\n";
        echo "         Gmail und Yahoo verlangen seit Februar 2024 einen DMARC-Eintrag.\n";
        echo "         Ohne ihn landen Mails im Spam oder werden lautlos verworfen —\n";
        echo "         je nach Anbieter unterschiedlich. Genau so entsteht \"kommt bei\n";
        echo "         manchen an, bei manchen nicht\".\n";
        echo "         Anfangen mit:  v=DMARC1; p=none; rua=mailto:" . bkOwnerEmail() . "\n";
    } else {
        echo "vorhanden\n         " . $dmarc[0] . "\n";

        $angefangen = [
            'brevo' => 'brevo', 'resend' => 'resend',
            'postmark' => 'postmark', 'mailjet' => 'mailjet',
        ];
        foreach ($angefangen as $dienst => $spur) {
            if (!str_contains(strtolower($dmarc[0]), $spur)) continue;
            if (bkTransportConfigured($dienst)) continue;

            echo "\n         ACHTUNG: Dieser Eintrag wurde von " . $dienst . " angelegt, aber\n";
            echo "         in der .env steht kein Schlüssel für " . $dienst . ". Die Einrichtung\n";
            echo "         ist also im DNS begonnen und nicht zu Ende gebracht worden —\n";
            echo "         verschickt wird weiterhin über das Hoster-Postfach.\n";
            echo "         Es fehlt der API-Schlüssel; siehe TERMINBUCHUNG.md, Abschnitt 1.\n";
        }
    }

    // --- DKIM: die Unterschrift unter jeder einzelnen Mail ---
    //
    // DNS kennt keine Auflistung: Man kann eine Domain nicht fragen, welche
    // DKIM-Selektoren sie hat, man kann nur einzelne erraten. Ein Dutzend
    // Rateversuche sind zwölf Abfragen, und eine davon kann hängen bleiben.
    // Deshalb läuft die Suche nur auf ausdrückliche Anforderung — die
    // Grundauskunft dieser Seite darf daran nicht scheitern.
    echo "\n  DKIM   ";
    if (($_GET['dkim'] ?? '') === '') {
        echo "nicht geprüft — Suche mit &dkim=1 anhängen (dauert einen Moment)\n";
        echo "         Verlässlicher ist ohnehin die Oberfläche des Maildienstes: Bei\n";
        echo "         Brevo, Resend und Postmark steht dort, ob die DKIM-Einträge\n";
        echo "         greifen, und sie melden grün, sobald es so weit ist.\n";
    } else {
        $selektoren = [
            'mail' => 'Brevo', 'brevo' => 'Brevo', 'resend' => 'Resend',
            'pm' => 'Postmark', 'mailjet' => 'Mailjet',
            'hostingermail-a' => 'Hostinger', 'hostingermail-b' => 'Hostinger',
            'default' => 'allgemein', 'dkim' => 'allgemein',
            's1' => 'allgemein', 'google' => 'Google Workspace',
        ];
        $gefunden = [];
        $vollstaendig = true;
        foreach ($selektoren as $selektor => $dienst) {
            if (!bkDnsFrist()) { $vollstaendig = false; break; }
            if (bkDiagDns($selektor . '._domainkey.' . $domain) !== []) {
                $gefunden[] = $selektor . ' (' . $dienst . ')';
            }
        }

        if ($gefunden !== []) {
            echo "gefunden: " . implode(', ', $gefunden) . "\n";
        } elseif (!$vollstaendig) {
            echo "nicht vollständig prüfbar — der Namensserver antwortet zu langsam.\n";
        } else {
            echo "KEIN bekannter Selektor gefunden\n";
            echo "         Das muss nicht heißen, dass keiner existiert — geraten werden\n";
            echo "         konnte nur eine feste Liste. Sicher ist: Der Dienst, über den\n";
            echo "         verschickt wird, MUSS seine DKIM-Einträge im DNS haben.\n";
        }
    }

    // --- MX: nur als Einordnung, wo die Post dieser Domain ankommt ---
    $mx = [];
    if (bkDnsFrist() && @getmxrr($domain, $mx) && $mx !== []) {
        echo "\n  MX     " . implode(', ', array_slice($mx, 0, 3)) . "\n";
    }
}

$siteHost = parse_url(bkSiteUrl(), PHP_URL_HOST) ?: '';
if ($siteHost !== '' && $domain !== '' && !str_ends_with($siteHost, $domain)) {
    echo "\n  ACHTUNG: Absenderdomain (" . $domain . ") und Website (" . $siteHost . ")\n";
    echo "  gehören nicht zusammen. Das kostet bei jedem Filter Punkte.\n";
}

// =====================================================================
// 3) Ausgangskorb sofort abarbeiten (&flush=1)
// =====================================================================

if (($_GET['flush'] ?? '') !== '') {
    bkDiagHead('LIEGENGEBLIEBENE MAILS ERNEUT VERSUCHT');
    $flush = bkFlushMailQueue(50);
    echo "  nachgereicht:            " . $flush['sent'] . "\n";
    echo "  weiter offen:            " . $flush['still_queued'] . "\n";
    echo "  endgültig gescheitert:   " . $flush['failed'] . "\n";
}

// =====================================================================
// 4) Eine Mail aus dem Protokoll erneut verschicken (&resend=<Nr>)
// =====================================================================

$resendId = (int) ($_GET['resend'] ?? 0);
if ($resendId > 0) {
    bkDiagHead('MAIL NR. ' . $resendId . ' ERNEUT VERSCHICKEN');

    $treffer = null;
    foreach (bkMailQueueRecent(500) as $row) {
        if ((int) $row['id'] === $resendId) { $treffer = $row; break; }
    }

    if ($treffer === null) {
        echo "  Unter dieser Nummer steht nichts im Protokoll.\n";
    } else {
        $msg = json_decode((string) $treffer['payload'], true);
        if (!is_array($msg)) {
            echo "  Der gespeicherte Inhalt dieser Mail ist unlesbar.\n";
        } else {
            // Auf Wunsch an eine andere Adresse — nützlich, wenn die
            // ursprüngliche einen Tippfehler hatte.
            $ziel = trim((string) ($_GET['to'] ?? ''));
            if ($ziel !== '' && filter_var($ziel, FILTER_VALIDATE_EMAIL)) {
                $msg['to_email'] = $ziel;
            }
            echo "  An:      " . $msg['to_email'] . "\n";
            echo "  Betreff: " . ($msg['subject'] ?? '') . "\n\n";

            $res = bkDeliver($msg);
            foreach ($res['log'] as $zeile) echo '  ' . $zeile . "\n";
            echo "\n  ERGEBNIS: " . ($res['ok']
                ? 'angenommen über ' . bkTransportLabel($res['transport'])
                    . ($res['id'] !== '' ? ' — Vorgang ' . $res['id'] : '')
                : 'FEHLGESCHLAGEN — ' . $res['error']) . "\n";

            // Auch der zweite Fehlschlag gehört ins Protokoll — sonst steht
            // dort weiter der alte Grund, und der nächste Blick auf die
            // Seite führt in die Irre.
            bkMailQueueUpdate($resendId, $res['ok']
                ? ['status' => 'sent', 'transport' => $res['transport'],
                   'provider_id' => $res['id'], 'error' => '']
                : ['error' => $res['error']]);
        }
    }
}

// =====================================================================
// 5) Testmails (&to=<adresse>)
// =====================================================================

$to = trim((string) ($_GET['to'] ?? ''));
if ($to !== '' && $resendId === 0) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        bkDiagHead('TESTMAILS');
        echo "  Keine gültige Adresse: " . $to . "\n";
    } else {
        bkDiagHead('TESTMAILS AN ' . $to);

        $muster = [
            'token' => 'diagnose',
            'start_utc' => bkStamp(bkNow()->modify('+2 days')),
            'end_utc' => bkStamp(bkNow()->modify('+2 days')->modify('+30 minutes')),
            'name' => 'Diagnose',
            'email' => $to,
            'phone' => '',
            'company' => '',
            'message' => '',
            'topic' => BK_TOPIC_DEFAULT,
        ];

        // Genau die Mails, die ein Kunde je zu sehen bekommt — und zwar über
        // bkMail(), also über denselben Weg wie eine echte Buchung. Kein
        // Nachbau, keine Abweichung.
        $kontakt = [
            'name' => 'Diagnose',
            'email' => $to,
            'phone' => '',
            'message' => 'Testnachricht aus der Diagnose.',
            'quelle' => 'Mail-Diagnose',
            'warnung' => '',
        ];

        $proben = [
            'Bestätigung (nach dem Buchen)' => ['bestaetigung', bkMailConfirmation($muster)],
            'Erinnerung (am Vortag)' => ['erinnerung', bkMailReminder($muster)],
            'Absage (nach einer Stornierung)' => ['absage', bkMailCancelled($muster, false)],
            'Eingangsbestätigung (Kontaktformular)' => ['eingangsbestaetigung', bkMailContactReceipt($kontakt)],
            'Nachricht an Leandro (Kontaktformular)' => ['kontakt', bkMailContactNotice($kontakt)],
        ];

        foreach ($proben as $titel => [$art, $mail]) {
            bkDiagLine();
            echo $titel . "\n";
            bkDiagLine();

            $msg = [
                'to_email' => $to, 'to_name' => 'Diagnose',
                'subject' => $mail['subject'], 'html' => $mail['html'], 'text' => $mail['text'],
                'reply_to' => bkOwnerEmail(), 'ics' => null, 'kind' => 'test',
            ];

            try {
                $res = bkDeliver($msg);
            } catch (Throwable $e) {
                $res = ['ok' => false, 'transport' => '', 'id' => '', 'error' => $e->getMessage(), 'log' => []];
            }

            echo "  Betreff: " . $mail['subject'] . "\n";
            foreach ($res['log'] as $zeile) echo '  ' . $zeile . "\n";
            echo "\n  ERGEBNIS: " . ($res['ok']
                ? 'angenommen über ' . bkTransportLabel($res['transport'])
                    . ($res['id'] !== '' ? ' — Vorgang ' . $res['id'] : '')
                : 'FEHLGESCHLAGEN — ' . $res['error']) . "\n\n";

            bkMailQueueAdd([
                'status' => $res['ok'] ? 'sent' : 'queued',
                'attempts' => 1,
                'next_try' => $res['ok'] ? '' : bkNextTry(1),
                'kind' => 'test-' . $art,
                'to_email' => $to,
                'to_name' => 'Diagnose',
                'subject' => $mail['subject'],
                'payload' => json_encode($msg, JSON_UNESCAPED_UNICODE) ?: '{}',
                'transport' => $res['transport'],
                'provider_id' => $res['id'],
                'error' => $res['error'],
            ]);
        }
    }
}

// =====================================================================
// 6) Was Brevo zu den Mails sagt
//
// Der wichtigste Abschnitt dieser Seite, sobald ein Maildienst läuft. Das
// eigene Protokoll weiter unten kann nur sagen: "Brevo hat die Mail
// angenommen." Ob sie danach ZUGESTELLT wurde, weiß nur Brevo — und genau
// diese Auskunft holt dieser Abschnitt ab.
// =====================================================================

if (bkTransportConfigured('brevo')) {
    $suchadresse = trim((string) ($_GET['pruefe'] ?? ''));
    bkDiagHead('WAS BREVO ZU DEN MAILS SAGT'
        . ($suchadresse !== '' ? ' (' . $suchadresse . ')' : ''));

    // --- Steht die gesuchte Adresse auf der Sperrliste? ---
    //
    // Das ist der heimtückischste Fehlerfall überhaupt: Brevo nimmt die Mail
    // an und meldet Erfolg, verschickt sie aber nie, weil die Adresse nach
    // einem früheren Bounce oder Spam-Klick gesperrt ist. Im eigenen
    // Protokoll steht dann "sent", beim Empfänger kommt trotzdem nichts an.
    if ($suchadresse !== '' && filter_var($suchadresse, FILTER_VALIDATE_EMAIL)) {
        $sperre = bkBrevoBlocked($suchadresse);
        echo "  Sperrliste: ";
        if (!$sperre['ok']) {
            echo "nicht prüfbar — " . $sperre['error'] . "\n";
        } elseif ($sperre['blocked']) {
            echo "JA, DIESE ADRESSE IST BEI BREVO GESPERRT\n";
            echo "              Grund: " . $sperre['reason'] . "\n";
            echo "              Solange die Sperre steht, wird an diese Adresse NICHTS\n";
            echo "              verschickt — auch wenn unser Protokoll \"sent\" meldet.\n";
            echo "              Aufheben: app.brevo.com → Transaktional → Statistik →\n";
            echo "              Gesperrte Kontakte → Adresse suchen → entsperren.\n";
        } else {
            echo "nein, diese Adresse ist nicht gesperrt.\n";
        }
        echo "\n";
    }

    $ereignisse = bkBrevoEvents($suchadresse, 25);

    if (!$ereignisse['ok']) {
        echo "  Nicht abrufbar — " . $ereignisse['error'] . "\n";
    } elseif ($ereignisse['events'] === []) {
        echo "  Brevo kennt zu diesem Zeitraum keine Ereignisse.\n";
        if ($suchadresse !== '') {
            echo "  Das heißt: An diese Adresse wurde über Brevo noch nichts verschickt.\n";
        }
    } else {
        // Breiten in BYTES, nicht Zeichen — printf zählt so. Deshalb steht in
        // der Kopfzeile "Empfaenger" ohne Umlaut: sonst verrutscht die Spalte.
        printf("  %-19s %-16s %-32s %s\n", 'Zeitpunkt', 'Ereignis', 'Empfaenger', 'Grund');
        bkDiagLine();

        // Klartext statt Fachbegriff: "hard_bounce" sagt einem Betreiber
        // nichts, "Adresse existiert nicht" schon.
        $klartext = [
            'delivered'   => 'ZUGESTELLT',
            'requests'    => 'angenommen',
            'opened'      => 'geöffnet',
            'clicks'      => 'Link geklickt',
            'soft_bounce' => 'vorüb. abgel.',
            'hardBounces' => 'ADRESSE FALSCH',
            'hard_bounce' => 'ADRESSE FALSCH',
            'blocked'     => 'NICHT GESENDET',
            'spam'        => 'ALS SPAM MARK.',
            'deferred'    => 'wartet noch',
            'invalid'     => 'ADRESSE UNGÜLTIG',
            'error'       => 'FEHLER',
        ];

        foreach ($ereignisse['events'] as $e) {
            $art = (string) ($e['event'] ?? '?');
            printf("  %-19s %-16s %-32s %s\n",
                substr((string) ($e['date'] ?? ''), 0, 19),
                $klartext[$art] ?? $art,
                substr((string) ($e['email'] ?? ''), 0, 32),
                substr((string) ($e['reason'] ?? ''), 0, 60));
        }

        echo "\n";
        echo "  So liest sich das:\n";
        echo "    ZUGESTELLT      Die Mail ist im Postfach des Empfängers. Findet er\n";
        echo "                    sie nicht, liegt sie in seinem Spam-Ordner.\n";
        echo "    NICHT GESENDET  Adresse steht auf Brevos Sperrliste — dort entsperren.\n";
        echo "    ADRESSE FALSCH  Die Adresse existiert nicht (Tippfehler beim Kunden).\n";
        echo "    wartet noch     Noch in der Warteschlange, gleich nochmal nachsehen.\n";
    }

    echo "\n  Für eine bestimmte Adresse: &pruefe=adresse@example.de anhängen.\n";
}

// =====================================================================
// 7) Protokoll
// =====================================================================

bkDiagHead('PROTOKOLL DER LETZTEN MAILS');

$zeilen = bkMailQueueRecent(40);
if ($zeilen === []) {
    echo "  Noch nichts verschickt.\n";
} else {
    printf("  %-5s %-17s %-9s %-13s %-30s %s\n",
        'Nr', 'Zeitpunkt (UTC)', 'Status', 'Art', 'Empfänger', 'Weg');
    bkDiagLine();
    foreach ($zeilen as $r) {
        printf("  %-5s %-17s %-9s %-13s %-30s %s\n",
            $r['id'],
            substr((string) $r['created_at'], 0, 16),
            $r['status'],
            substr((string) $r['kind'], 0, 13),
            substr((string) $r['to_email'], 0, 30),
            $r['transport'] !== '' ? $r['transport'] : '—');
        if ((string) $r['error'] !== '') {
            echo "        └─ " . substr((string) $r['error'], 0, 200) . "\n";
        }
        if ((string) $r['provider_id'] !== '') {
            echo "        └─ Vorgang: " . substr((string) $r['provider_id'], 0, 120) . "\n";
        }
    }

    $offen = count(array_filter($zeilen, static fn (array $r): bool => $r['status'] === 'queued'));
    if ($offen > 0) {
        echo "\n  " . $offen . " Mail(s) liegen noch im Ausgangskorb. Der stündliche Cronjob\n";
        echo "  wiederholt sie; sofort geht es mit &flush=1 an dieser Adresse.\n";
    }
}

// =====================================================================
bkDiagHead('WAS DIESE SEITE SAGT — UND WAS NICHT');
echo "  \"Angenommen\" heißt: Der Versandweg hat die Mail übernommen. Ob sie\n";
echo "  im Postfach ankommt, entscheidet danach der Empfänger-Anbieter.\n\n";
echo "  Bei Brevo, Resend, Postmark und Mailjet lässt sich das nachsehen:\n";
echo "  Dort steht zu jeder Vorgangsnummer, ob die Mail zugestellt, abgelehnt\n";
echo "  oder als Spam eingestuft wurde. Genau diese Auskunft fehlt beim\n";
echo "  Versand über das Hoster-Postfach — deshalb war eine verschwundene\n";
echo "  Bestätigung dort nicht aufklärbar.\n\n";
echo "  Schalter dieser Seite:\n";
echo "    &to=adresse@example.de   Bestätigung, Erinnerung und Absage testen\n";
echo "    &flush=1                 liegengebliebene Mails sofort wiederholen\n";
echo "    &resend=<Nr>             Mail aus dem Protokoll erneut verschicken\n";
echo "    &resend=<Nr>&to=…        dieselbe Mail an eine andere Adresse\n";
echo "    &dkim=1                  zusätzlich nach DKIM-Selektoren suchen\n";
