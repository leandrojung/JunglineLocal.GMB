<?php
/**
 * Wissensbasis des Chat-Assistenten.
 *
 * DAS IST DIE EINZIGE QUELLE DER WAHRHEIT FÜR ALLES, WAS DER BOT SAGT.
 * Er beantwortet Fragen nur aus diesen Einträgen, erfindet nichts und
 * nennt nur Preise, die hier stehen. Ändert sich etwas auf der Website
 * (Preise, Ablauf, Kontaktdaten), muss es auch hier geändert werden,
 * sonst widerspricht der Bot der Seite.
 *
 * Drei Stellen nutzen diese Datei:
 *   1. Der Wortvergleich (chatFindeFaq): feste Antworten ohne KI.
 *      Kosten null, Wortlaut garantiert, nichts kann erfunden werden.
 *   2. Die Schnellauswahl im Chat (Knöpfe): schicken nur eine Antwort-ID.
 *   3. Der Prompt für das Sprachmodell (chatWissenText): bei offenen
 *      Fragen darf es nur aus diesem Text antworten und kann auf feste
 *      Antworten verweisen.
 *
 * SCHREIBREGEL (CLAUDE.md): keine Gedankenstriche. Komma, Punkt,
 * Doppelpunkt oder Klammern, Zahlenbereiche mit "bis".
 *
 * AUFBAU EINES EINTRAGS
 *   titel   Kurzbeschreibung (für das Sprachmodell und die Pflege)
 *   kw      Suchwörter. Normalisiert verglichen (Kleinbuchstaben, ä = ae).
 *           Ein Wort zählt 1, ein "!" davor macht es stark (2). Mehrwort-
 *           Ausdrücke zählen ebenfalls 2. Treffer am Wortanfang genügen
 *           ("preis" trifft "Preise").
 *   text    Die freigegebene Antwort. Wird wörtlich ausgegeben.
 *   links   IDs aus chatSeiten(), die unter der Antwort erscheinen.
 *   aktion  null | 'termin' | 'nachricht': öffnet im Chat die Terminbuchung
 *           oder das Nachrichtenformular.
 *   thema   'seo' | 'webdesign': Vorauswahl beim Termin.
 *   folge   Vorschläge für die nächsten Knöpfe (Antwort-IDs oder 'termin',
 *           'nachricht').
 */

declare(strict_types=1);

const CHAT_TELEFON = '+49 176 55769680';
const CHAT_MAIL    = 'Info@jungline.de';

/**
 * Seiten, auf die der Bot verlinken darf. Das Sprachmodell kennt nur die
 * IDs, nie die Adressen: Es kann also keine Seite erfinden.
 */
function chatSeiten(): array {
    return [
        'start'            => ['/', 'Startseite'],
        'termin_seo'       => ['/kontakt/#termin', 'Termin buchen (Google-Profil)'],
        'termin_webdesign' => ['/webdesign/kontakt/#termin', 'Termin buchen (Webdesign)'],
        'kontakt'          => ['/kontakt/', 'Kontakt'],
        'leistungen'       => ['/leistungen/google-unternehmensprofil-optimierung/', 'Leistungen im Detail'],
        'profilcheck'      => ['/#rank-check', 'Kostenloser Profil-Check'],
        'ergebnisse'       => ['/#ergebnisse', 'Kundenbeispiele'],
        'faq'              => ['/#faq', 'Häufige Fragen'],
        'ueber_mich'       => ['/ueber-mich/', 'Über mich'],
        'dorsten'          => ['/local-seo-dorsten/', 'Local SEO in Dorsten'],
        'webdesign'        => ['/webdesign/', 'Webdesign & Relaunch'],
        'webdesign_preise' => ['/webdesign/#preise', 'Preise Webdesign'],
        'webdesign_ablauf' => ['/webdesign/#ablauf', 'Ablauf Webdesign'],
        'speedcheck'       => ['/webdesign/#check', 'Geschwindigkeits-Check'],
        'branchen'         => ['/branchen/', 'Alle Branchen'],
        'b_handwerker'     => ['/branchen/handwerker/', 'Handwerker'],
        'b_shk'            => ['/branchen/heizung-sanitaer/', 'Heizung & Sanitär'],
        'b_aerzte'         => ['/branchen/aerzte-und-praxen/', 'Ärzte & Praxen'],
        'b_anwaelte'       => ['/branchen/anwaelte-und-kanzleien/', 'Anwälte & Kanzleien'],
        'b_makler'         => ['/branchen/immobilienmakler/', 'Immobilienmakler'],
        'b_gastro'         => ['/branchen/gastronomie/', 'Gastronomie'],
        'b_kosmetik'       => ['/branchen/kosmetik-und-friseure/', 'Kosmetik & Friseure'],
        'b_auto'           => ['/branchen/autohaeuser/', 'Autohäuser'],
        'b_physio'         => ['/branchen/physiotherapie/', 'Physiotherapie'],
        'ratgeber'         => ['/ratgeber/', 'Ratgeber'],
        'r_checkliste'     => ['/ratgeber/checkliste-profil-optimieren/', 'Checkliste: Profil optimieren'],
        'r_bewertungen'    => ['/ratgeber/mehr-google-bewertungen-bekommen/', 'Mehr Google-Bewertungen bekommen'],
        'r_antworten'      => ['/ratgeber/auf-google-bewertungen-antworten/', 'Auf Bewertungen antworten'],
        'r_ranking'        => ['/ratgeber/google-maps-ranking-verbessern/', 'Google-Maps-Ranking verbessern'],
        'r_kategorien'     => ['/ratgeber/google-kategorien-richtig-waehlen/', 'Kategorien richtig wählen'],
        'r_beitraege'      => ['/ratgeber/google-beitraege/', 'Google-Beiträge'],
        'r_erstellen'      => ['/ratgeber/google-unternehmensprofil-erstellen/', 'Unternehmensprofil erstellen'],
        'r_grundlagen'     => ['/ratgeber/local-seo-grundlagen/', 'Local SEO: Grundlagen'],
        'datenschutz'      => ['/datenschutz/', 'Datenschutzerklärung'],
        'impressum'        => ['/impressum/', 'Impressum'],
    ];
}

function chatWissen(): array {
    static $w = null;
    if ($w !== null) return $w;

    $w = [

        'begruessung' => [
            'titel' => 'Begrüßung',
            'kw'    => [],
            'text'  => 'Guten Tag! Ich bin der KI-Assistent von Jungline Local. Ich beantworte Fragen zur Google-Profil-Optimierung und zum Webdesign, trage Ihnen auf Wunsch direkt einen Termin ein und leite Sie bei Bedarf an Leandro Jung weiter. Wobei kann ich helfen?',
            'links' => [],
            'aktion' => null,
            'folge' => ['termin', 'preise', 'seo_leistung', 'webdesign_leistung'],
        ],

        'ki_hinweis' => [
            'titel' => 'Bist du ein Mensch oder eine KI? Wie verlässlich sind die Antworten?',
            'kw'    => ['!bist du ein mensch', '!bist du eine ki', '!bist du ein bot', '!bot oder mensch', '!echte person', '!kuenstliche intelligenz', '!ki assistent', 'chatbot', 'roboter', 'echter mensch', 'ki oder mensch'],
            'text'  => 'Ich bin ein KI-Assistent, kein Mensch. Meine Auskünfte stammen aus den Inhalten dieser Website und können unvollständig sein. Verbindlich sind nur Aussagen von Leandro Jung im persönlichen Gespräch. Bitte geben Sie hier keine sensiblen Daten ein.',
            'links' => ['kontakt'],
            'aktion' => null,
            'folge' => ['mensch', 'termin'],
        ],

        'wer' => [
            'titel' => 'Wer steckt hinter Jungline Local?',
            'kw'    => ['!wer ist leandro', '!wer bist du', '!wer steckt dahinter', '!wer ist jungline', '!was ist jungline', 'leandro', 'jungline', 'ueber euch', 'ueber sie', 'ueber mich', 'inhaber', 'gruender', 'ansprechpartner', 'wer betreut'],
            'text'  => 'Jungline Local ist das Angebot von Leandro Jung, selbständiger Dienstleister aus Dorsten (NRW). Er optimiert Google-Unternehmensprofile für regionale Betriebe und baut Websites neu auf. Sie sprechen von der ersten Analyse bis zur laufenden Betreuung immer direkt mit ihm, ohne Callcenter und ohne Agentur-Überbau. Gearbeitet wird bundesweit.',
            'links' => ['ueber_mich', 'leistungen', 'webdesign'],
            'aktion' => null,
            'folge' => ['seo_leistung', 'webdesign_leistung', 'termin'],
        ],

        'seo_leistung' => [
            'titel' => 'Was beinhaltet die Optimierung des Google-Unternehmensprofils?',
            'kw'    => ['!google profil', '!google unternehmensprofil', '!unternehmensprofil', '!google business', '!gbp', '!local seo', '!google maps', '!kartenbereich', '!local pack', 'optimierung', 'optimieren', 'sichtbarkeit', 'sichtbar', 'gefunden werden', 'leistungen', 'was bieten sie', 'was machen sie', 'was machst du', 'was beinhaltet', 'google'],
            'text'  => 'Bei der Google-Unternehmensprofil-Optimierung bringt Leandro Ihr Profil vollständig und richtlinienkonform in Form: passende Kategorien und recherchierte Suchbegriffe, eine saubere Profilbeschreibung, Bilder, Leistungen und Attribute, eine Bewertungsstrategie, Google-Beiträge und Fragen und Antworten, einheitliche Einträge in den deutschen Branchenverzeichnissen sowie Monitoring mit Berichten. Auf Wunsch gibt es die laufende Betreuung dazu. Ziel ist mehr Sichtbarkeit im Google-Kartenbereich und damit mehr Anrufe und Anfragen aus Ihrer Region.',
            'links' => ['leistungen', 'profilcheck', 'ratgeber'],
            'aktion' => null,
            'thema' => 'seo',
            'folge' => ['seo_ablauf', 'preise', 'profilcheck', 'termin'],
        ],

        'seo_ablauf' => [
            'titel' => 'Wie läuft die Zusammenarbeit bei der Google-Profil-Optimierung ab?',
            'kw'    => ['!ablauf', '!wie laeuft', '!wie funktioniert', '!vorgehen', '!schritte', '!zusammenarbeit', 'prozess', 'wie geht es los', 'wie starte', 'wie fange'],
            'text'  => 'Bei der Google-Profil-Optimierung läuft die Zusammenarbeit in vier Schritten. 1. Analyse: Ihr Profil, Ihre Sichtbarkeit und die Konkurrenz werden geprüft. 2. Optimierung: Kategorien, Suchbegriffe, Beschreibung, Bilder, Leistungen und Attribute werden vollständig aufgesetzt. 3. Bewertungen und Einträge: Aufbau einer Bewertungsstrategie und einheitliche Firmendaten in den Verzeichnissen. 4. Betreuung: Pflege, Google-Beiträge und Monitoring, auf Wunsch dauerhaft. Sie haben dabei einen festen Ansprechpartner: Leandro Jung.',
            'links' => ['leistungen', 'termin_seo'],
            'aktion' => null,
            'thema' => 'seo',
            'folge' => ['seo_dauer', 'webdesign_ablauf', 'preise', 'termin'],
        ],

        'seo_dauer' => [
            'titel' => 'Wie schnell zeigen sich Ergebnisse bei Google?',
            'kw'    => ['!wie schnell', '!wie lange dauert', '!wie lange dauert die optimierung', '!ergebnisse', '!ergebnis', '!wann sehe ich', 'dauer', 'wochen', 'monate', 'zeitraum', 'bringt das was', 'lohnt'],
            'text'  => 'Die grundlegende Optimierung ist meist innerhalb weniger Tage bis Wochen umgesetzt, je nachdem, wie vollständig Ihr Profil heute ist und wie schnell Informationen und Bilder vorliegen. Erste Verbesserungen zeigen sich häufig nach wenigen Wochen. Wie schnell es konkret geht, hängt von Branche und Wettbewerb ab und lässt sich nicht verlässlich vorhersagen. Eine bestimmte Platzierung kann niemand seriös garantieren.',
            'links' => ['leistungen', 'ergebnisse'],
            'aktion' => null,
            'thema' => 'seo',
            'folge' => ['garantie', 'referenzen', 'termin'],
        ],

        'garantie' => [
            'titel' => 'Können Sie Platz 1 bei Google garantieren?',
            'kw'    => ['!garantie', '!garantiert', '!garantieren', '!platz 1', '!platz eins', '!erste platz', '!ganz oben', '!ranking garantie', '!versprechen', 'sicher nach oben', 'top 3'],
            'text'  => 'Nein, und niemand kann das seriös. Google entscheidet über die Platzierung anhand vieler Faktoren. Zugesagt wird die vollständige, richtlinienkonforme Arbeit an Ihrem Profil und volle Transparenz über die Entwicklung, in nachvollziehbaren Berichten. Wer Platz 1 verspricht, ist unseriös.',
            'links' => ['ergebnisse', 'leistungen'],
            'aktion' => null,
            'folge' => ['referenzen', 'bewertungen', 'termin'],
        ],

        'preise' => [
            'titel' => 'Was kostet es?',
            'kw'    => ['!was kostet', '!kosten$', '!kostet', '!preis', '!preise', '!wie teuer', '!teuer', '!angebot', '!honorar', '!bezahlen', '!tarif', '!paket', '!pakete', '!budget', 'euro', 'monatlich', 'abo'],
            'text'  => 'Das hängt vom Angebot ab. Webdesign: Ein Relaunch kostet ab 1.000 € (einmalig, Festpreis nach dem Erstgespräch), die optionale Pflege ab 50 € pro Monat, monatlich kündbar. Alle Beträge sind Endpreise, nach § 19 UStG wird keine Umsatzsteuer ausgewiesen. Google-Profil-Optimierung: Hier gibt es keinen Pauschalpreis von der Stange, weil er von Ausgangslage und Zielen abhängt. Im kostenlosen Erstgespräch bekommen Sie eine klare, ehrliche Einschätzung, bevor irgendetwas beauftragt wird. Das Erstgespräch selbst ist kostenlos. Keine Vorkasse, kein Knebelvertrag.',
            'links' => ['webdesign_preise', 'leistungen'],
            'aktion' => null,
            'folge' => ['vertrag', 'erstgespraech', 'termin'],
        ],

        'vertrag' => [
            'titel' => 'Gibt es Vorkasse, Vertragslaufzeit oder Kündigungsfristen?',
            'kw'    => ['!vorkasse', '!knebelvertrag', '!vertragslaufzeit', '!mindestlaufzeit', '!kuendigen', '!kuendigung', '!kuendbar', '!laufzeit', 'vertrag', 'bindung', 'verpflichtung', 'unverbindlich'],
            'text'  => 'Es gibt keine Vorkasse und keinen Knebelvertrag. Beim Webdesign steht der Festpreis fest, bevor die Arbeit beginnt, und die optionale Pflege ist monatlich kündbar, ohne Mindestlaufzeit. Das Erstgespräch ist kostenlos und verpflichtet zu nichts. Domain, Hosting, Zugänge und Inhalte laufen auf Ihren Namen.',
            'links' => ['webdesign_preise', 'faq'],
            'aktion' => null,
            'folge' => ['preise', 'termin'],
        ],

        'erstgespraech' => [
            'titel' => 'Was ist das kostenlose Erstgespräch?',
            'kw'    => ['!erstgespraech', '!beratung', '!kennenlernen', '!kennenlerngespraech', '!was bringt das gespraech', 'gespraech', 'telefonat', 'video'],
            'text'  => 'Das Erstgespräch dauert 30 Minuten, ist kostenlos und unverbindlich, per Telefon oder Video. Beim Google-Profil nehmen Sie 3 bis 5 konkrete Tipps zum Sofort-Umsetzen mit, eine kurze Einschätzung, wie Sie gegenüber der Konkurrenz im Kartenbereich dastehen, und eine ehrliche Einschätzung, was für Ihren Betrieb realistisch möglich ist. Beim Webdesign bekommen Sie eine ehrliche Einschätzung Ihrer Seite, eine Größenordnung zum Preis und Tipps, die auch ohne Relaunch helfen. Auf Wunsch trifft sich Leandro in der Nähe von Dorsten auch persönlich mit Ihnen.',
            'links' => ['termin_seo', 'termin_webdesign'],
            'aktion' => 'termin',
            'folge' => ['termin', 'preise'],
        ],

        'termin' => [
            'titel' => 'Termin buchen',
            'kw'    => ['!termin', '!buchen', '!buchung', '!vereinbaren', '!verfuegbar', '!freie zeiten', '!wann haben sie zeit', '!rueckruf', 'sprechen', 'kalender', 'uhrzeit', 'naechste woche', 'zeit haben'],
            'text'  => 'Sehr gerne. Ich kann Ihnen direkt hier einen Termin für das kostenlose Erstgespräch eintragen (30 Minuten, per Telefon oder Video). Worum soll es gehen?',
            'links' => [],
            'aktion' => 'termin',
            'folge' => [],
        ],

        'kontakt' => [
            'titel' => 'Kontaktdaten, Telefon, E-Mail, Adresse, Erreichbarkeit',
            'kw'    => ['!telefon', '!telefonnummer', '!anrufen', '!mail', '!e mail', '!email', '!adresse', '!anschrift', '!wo sitzen', '!wo sind sie', '!standort', '!erreichen', '!erreichbar', '!kontakt', '!kontaktieren', 'handynummer', 'whatsapp', 'impressum'],
            'text'  => 'So erreichen Sie Leandro Jung: Telefon ' . CHAT_TELEFON . ', E-Mail ' . CHAT_MAIL . '. Die Adresse lautet Jungline Local, Rhader Straße 132, 46286 Dorsten (NRW). Er ist werktags erreichbar und antwortet auf Anfragen innerhalb von 24 Stunden. Wenn Sie möchten, nehme ich Ihre Nachricht auch direkt hier entgegen und leite sie weiter.',
            'links' => ['kontakt', 'impressum'],
            'aktion' => 'nachricht',
            'folge' => ['nachricht', 'termin'],
        ],

        'mensch' => [
            'titel' => 'Ich möchte mit einem Menschen sprechen / Nachricht an Leandro',
            'kw'    => ['!mit leandro sprechen', '!mit einem menschen', '!menschen sprechen', '!echten menschen', '!nachricht schreiben', '!nachricht senden', '!nachricht hinterlassen', '!mitarbeiter', 'persoenlich sprechen', 'persoenlich', 'ruft mich', 'zurueckrufen', 'beschwerde', 'reklamation', 'unzufrieden'],
            'text'  => 'Selbstverständlich. Leandro Jung erreichen Sie unter ' . CHAT_TELEFON . ' oder per E-Mail an ' . CHAT_MAIL . '. Alternativ nehme ich Ihre Nachricht direkt hier auf und leite sie an ihn weiter. Er antwortet werktags innerhalb von 24 Stunden.',
            'links' => ['kontakt'],
            'aktion' => 'nachricht',
            'folge' => ['termin'],
        ],

        'region' => [
            'titel' => 'Arbeiten Sie nur regional oder bundesweit?',
            'kw'    => ['!bundesweit', '!nur regional', '!nur dorsten', '!aus der ferne', '!remote', '!ausserhalb', 'region', 'dorsten', 'nrw', 'ruhrgebiet', 'umgebung', 'in der naehe', 'vor ort'],
            'text'  => 'Der Sitz ist in Dorsten-Lembeck (NRW), betreut werden aber Betriebe aus ganz Deutschland. Ein Google-Unternehmensprofil lässt sich vollständig aus der Ferne bearbeiten, der Austausch läuft per Telefon oder Video. Wer in der Nähe von Dorsten sitzt, bekommt das Gespräch auf Wunsch gern persönlich.',
            'links' => ['dorsten', 'ueber_mich'],
            'aktion' => null,
            'folge' => ['erstgespraech', 'termin'],
        ],

        'branchen' => [
            'titel' => 'Für welche Branchen gibt es die Optimierung?',
            'kw'    => ['!branche', '!branchen', '!fuer wen', '!zielgruppe', 'handwerk', 'handwerker', 'arzt', 'aerzte', 'praxis', 'anwalt', 'kanzlei', 'makler', 'immobilien', 'gastronomie', 'restaurant', 'friseur', 'kosmetik', 'autohaus', 'physio', 'heizung', 'sanitaer', 'dachdecker', 'zahnarzt', 'steuerberater', 'elektriker'],
            'text'  => 'Neun Branchen haben eine eigene Seite, auf der steht, worauf es jeweils ankommt: Handwerker, Heizung & Sanitär, Ärzte & Praxen, Anwälte & Kanzleien, Immobilienmakler, Gastronomie, Kosmetik & Friseure, Autohäuser und Physiotherapie. Jede Branche wird bei Google anders gesucht. Ist Ihre Branche nicht dabei, klären wir im Erstgespräch, was für Ihren Betrieb gilt.',
            'links' => ['branchen', 'b_handwerker', 'b_aerzte', 'b_anwaelte'],
            'aktion' => null,
            'folge' => ['seo_leistung', 'termin'],
        ],

        'bewertungen' => [
            'titel' => 'Bewertungen: kaufen, sammeln, beantworten',
            'kw'    => ['!bewertung', '!bewertungen', '!rezension', '!rezensionen', '!sterne', '!gekaufte bewertungen', '!fake bewertungen', 'schlechte bewertung', 'negative bewertung', 'mehr bewertungen', 'antworten auf bewertungen'],
            'text'  => 'Echte Bewertungen sind ein starker Faktor bei Google. Leandro erarbeitet einen realistischen, richtlinienkonformen Plan, wie Sie mehr echte Bewertungen bekommen, und formuliert professionelle Antworten, auch auf kritische Stimmen. Gekaufte Bewertungen, Keyword-Tricks im Firmennamen und Fake-Standorte gibt es nicht: Sie riskieren die Sperrung Ihres Profils.',
            'links' => ['r_bewertungen', 'r_antworten', 'leistungen'],
            'aktion' => null,
            'folge' => ['seo_leistung', 'termin'],
        ],

        'selbst_machen' => [
            'titel' => 'Kann ich das nicht einfach selbst machen? Ich habe schon ein Profil.',
            'kw'    => ['!selbst machen', '!selber machen', '!selbst optimieren', '!schon ein profil', '!habe schon ein profil', '!bereits ein profil', '!lohnt sich das', '!brauche ich das', 'do it yourself', 'anleitung', 'tipps'],
            'text'  => 'Grundsätzlich ja. Der Unterschied liegt in den Details: richtige Kategorien, Suchbegriffe mit echtem Suchvolumen, einheitliche Verzeichniseinträge und laufende Pflege. Gerade bei bestehenden Profilen lohnt sich ein Blick, weil sie meist unvollständig sind und Sichtbarkeit verschenken. Im Erstgespräch bekommen Sie sogar konkrete Tipps zum Selbermachen. Im Ratgeber finden Sie außerdem Anleitungen und eine Checkliste.',
            'links' => ['r_checkliste', 'r_erstellen', 'profilcheck'],
            'aktion' => null,
            'folge' => ['profilcheck', 'termin'],
        ],

        'profilcheck' => [
            'titel' => 'Kostenloser Profil-Check',
            'kw'    => ['!profil check', '!profilcheck', '!profil pruefen', '!profil testen', '!kostenlos pruefen', '!analyse', '!wie steht mein profil', '!wo stehe ich', 'check', 'pruefen', 'vergleich', 'konkurrenz'],
            'text'  => 'Auf der Startseite gibt es einen kostenlosen Profil-Check: Sie geben Firmenname, Stadt und Suchbegriff ein und sehen in etwa 30 Sekunden, wie Ihr Profil im Vergleich mit den Top 3 abschneidet. Er nutzt echte Google-Daten und läuft ohne Anmeldung. Die ausführliche Auswertung schickt Leandro Ihnen auf Wunsch persönlich per E-Mail.',
            'links' => ['profilcheck'],
            'aktion' => null,
            'folge' => ['termin', 'seo_leistung'],
        ],

        'referenzen' => [
            'titel' => 'Referenzen und Kundenbeispiele',
            'kw'    => ['!referenz', '!referenzen', '!kundenbeispiel', '!kundenbeispiele', '!beispiele', '!erfahrung', '!erfahrungen', '!kunden', '!arbeitsprobe', '!arbeitsproben', 'bewiesen', 'nachweis', 'belegen', 'rowi', 'gloria', 'sicitalia'],
            'text'  => 'Auf der Startseite stehen zwei Kundenprofile, die bei Google auf Platz 1 stehen und sich selbst nachprüfen lassen: Energieberatung Nordbayern und Sicitalia. Beim Webdesign sind Rowi Maschinenservice (Heiden) und die Mobile Pflegepraxis Gloria (Dorsten, geht in Kürze online) als Arbeitsproben zu sehen. Für Ihr Ergebnis lässt sich daraus nichts zusagen, es hängt von Branche, Wettbewerb und Standort ab.',
            'links' => ['ergebnisse', 'webdesign'],
            'aktion' => null,
            'folge' => ['garantie', 'termin'],
        ],

        'webdesign_leistung' => [
            'titel' => 'Webdesign und Relaunch: was wird angeboten?',
            'kw'    => ['!webdesign', '!website', '!webseite', '!homepage', '!relaunch', '!redesign', '!neue website', '!neue webseite', '!internetseite', '!internetauftritt', 'seite neu', 'alte website', 'veraltet', 'baukasten', 'wordpress', 'shop'],
            'text'  => 'Leandro baut Websites für regionale Betriebe von Grund auf neu: schnell, klar und auf dem Handy so gut wie am Rechner, ohne Baukasten und nicht aus einer Vorlage. Dazu gehören Relaunch und Redesign (Ihre Inhalte und Ihr Name bleiben, die Struktur wird neu), eine technische Erneuerung mit Ladezeit als Vorgabe, eine kundenfreundliche Gestaltung und auf Wunsch die Pflege danach. Domain, Hosting und Zugänge laufen auf Ihren Namen.',
            'links' => ['webdesign', 'webdesign_preise', 'speedcheck'],
            'aktion' => null,
            'thema' => 'webdesign',
            'folge' => ['webdesign_ablauf', 'preise', 'speedcheck', 'termin'],
        ],

        'webdesign_ablauf' => [
            'titel' => 'Ablauf und Dauer eines Website-Relaunchs',
            'kw'    => ['!wie lange dauert ein relaunch', '!dauer relaunch', '!relaunch ablauf', '!ablauf webdesign', '!entwurf', '!uebergabe', 'zwei bis vier wochen', 'wie lange dauert die website', 'wie lange dauert eine website'],
            'text'  => 'Ein Relaunch läuft in vier Schritten. 1. Gespräch (30 Minuten, kostenlos): Sie erfahren, was bleibt, was wegkann und was fehlt. 2. Entwurf: Sie sehen Ihre neue Startseite, bevor gebaut wird, und der Festpreis steht. 3. Umsetzung: Die Seite wird fertig gebaut, Inhalte werden übertragen, Weiterleitungen eingerichtet. 4. Übergabe: Die Seite geht live, Sie bekommen alle Zugänge. Für eine überschaubare Firmenwebsite dauert es meist zwei bis vier Wochen ab dem Moment, in dem Texte und Bilder vorliegen.',
            'links' => ['webdesign_ablauf', 'termin_webdesign'],
            'aktion' => null,
            'thema' => 'webdesign',
            'folge' => ['webdesign_pflege', 'preise', 'termin'],
        ],

        'webdesign_pflege' => [
            'titel' => 'Wartung und Pflege der Website, spätere Änderungen',
            'kw'    => ['!pflege', '!wartung', '!pflegepaket', '!updates', '!backup', '!backups', '!aenderungen', '!selbst aendern', '!spaeter aendern', 'sicherheit', 'oeffnungszeiten aendern'],
            'text'  => 'Die Pflege ist optional: ab 50 € pro Monat, monatlich kündbar, ohne Mindestlaufzeit. Sie umfasst Updates, Sicherheitsprüfungen und Backups, kleine Inhaltsänderungen (meist am selben Werktag), eine Erreichbarkeitsprüfung und einen festen Ansprechpartner. Die Seite gehört Ihnen und läuft auch ohne Pflege weiter. Wer lieber selbst bearbeiten möchte, sagt das bitte vor dem Start, dann wird die Seite von Anfang an so aufgebaut.',
            'links' => ['webdesign_preise'],
            'aktion' => null,
            'thema' => 'webdesign',
            'folge' => ['preise', 'termin'],
        ],

        'webdesign_inhalte' => [
            'titel' => 'Texte, Bilder und die alte Website beim Relaunch',
            'kw'    => ['!texte', '!bilder', '!fotos', '!inhalte', '!alte seite', '!alte website', '!alte adresse', '!domain', '!weiterleitung', '!weiterleitungen', 'meine adresse', 'internetadresse', 'google treffer', 'verlieren'],
            'text'  => 'Sie müssen nicht selbst texten: Üblich ist, dass Sie erzählen und Leandro formuliert. Vorhandene Texte werden übernommen, wo sie gut sind. Fotos aus Ihrem Betrieb sollten Sie liefern, Bilder aus der Konserve erkennt jeder. Ihre Domain bleibt Ihre Domain, die alte Seite bleibt online, bis die neue fertig ist. Danach leiten alte Unterseiten auf die passenden neuen weiter, damit bestehende Links und Google-Treffer nicht ins Leere laufen.',
            'links' => ['webdesign'],
            'aktion' => null,
            'thema' => 'webdesign',
            'folge' => ['webdesign_ablauf', 'termin'],
        ],

        'speedcheck' => [
            'titel' => 'Kostenloser Geschwindigkeits-Check der Website',
            'kw'    => ['!geschwindigkeit', '!ladezeit', '!pagespeed', '!speed', '!wie schnell ist meine website', '!website testen', '!website pruefen', 'langsam', 'tempo', 'lighthouse'],
            'text'  => 'Auf der Webdesign-Seite können Sie die Ladegeschwindigkeit Ihrer Website kostenlos messen. Sie geben Ihre Internetadresse ein, Google öffnet die Seite auf einem simulierten Mittelklasse-Handy und misst, wie schnell sie lädt (mit PageSpeed Insights, dem Messwerkzeug von Google). Das dauert 10 bis 30 Sekunden. Ihre Adresse geht nur an diese Google-Schnittstelle und wird nicht gespeichert.',
            'links' => ['speedcheck'],
            'aktion' => null,
            'thema' => 'webdesign',
            'folge' => ['webdesign_leistung', 'termin'],
        ],

        'ratgeber' => [
            'titel' => 'Ratgeber und Anleitungen',
            'kw'    => ['!ratgeber', '!anleitung', '!checkliste', '!tutorial', '!wie erstelle ich', '!google beitraege', '!kategorien', 'lernen', 'artikel', 'blog', 'infos', 'informationen'],
            'text'  => 'Im Ratgeber stehen kostenlose Anleitungen: Local-SEO-Grundlagen, Unternehmensprofil erstellen, Kategorien richtig wählen, Google-Maps-Ranking verbessern, mehr Bewertungen bekommen, auf Bewertungen antworten, Google-Beiträge und eine Checkliste zur Profil-Optimierung.',
            'links' => ['ratgeber', 'r_checkliste', 'r_grundlagen', 'r_ranking'],
            'aktion' => null,
            'folge' => ['selbst_machen', 'termin'],
        ],

        'richtlinien' => [
            'titel' => 'Ist das richtlinienkonform und seriös?',
            'kw'    => ['!richtlinien', '!richtlinienkonform', '!seriös', '!erlaubt', '!sperrung', '!gesperrt', '!abmahnung', '!uwg', 'tricks', 'sicher', 'legal'],
            'text'  => 'Ja. Gearbeitet wird ausschließlich mit Methoden, die Googles Richtlinien und dem UWG entsprechen, nachhaltig statt kurzfristig: keine gekauften Bewertungen, keine Keyword-Tricks im Firmennamen, keine Fake-Standorte. Solche Abkürzungen riskieren die Sperrung des Profils.',
            'links' => ['leistungen', 'ueber_mich'],
            'aktion' => null,
            'folge' => ['garantie', 'termin'],
        ],

        'umsatzsteuer' => [
            'titel' => 'Umsatzsteuer, Rechnung, Kleinunternehmer',
            'kw'    => ['!umsatzsteuer', '!mehrwertsteuer', '!mwst', '!ust', '!netto', '!brutto', '!kleinunternehmer', '!rechnung', 'steuer', 'endpreis'],
            'text'  => 'Leandro Jung ist Kleinunternehmer nach § 19 UStG. Alle genannten Beträge sind Endpreise, es wird keine Umsatzsteuer berechnet und ausgewiesen.',
            'links' => ['impressum'],
            'aktion' => null,
            'folge' => ['preise'],
        ],

        'datenschutz_bot' => [
            'titel' => 'Datenschutz: Was passiert mit meinen Eingaben im Chat?',
            'kw'    => ['!datenschutz', '!dsgvo', '!meine daten', '!daten gespeichert', '!gespeichert', '!cookies', '!tracking', '!privatsphaere', 'speichern', 'weitergegeben', 'verschluesselt'],
            'text'  => 'Der Chat setzt keine Cookies. Feste Antworten, Terminbuchung und Nachrichtenformular laufen ausschließlich auf dem Server von Jungline Local, Name und E-Mail gehen dabei nie an die KI. Nur frei getippte Fragen, die nicht aus den festen Antworten beantwortet werden können, werden zur Beantwortung an einen KI-Dienst übermittelt. Bitte schreiben Sie dort deshalb keine persönlichen oder vertraulichen Angaben. Details stehen in der Datenschutzerklärung.',
            'links' => ['datenschutz'],
            'aktion' => null,
            'folge' => ['mensch', 'termin'],
        ],
    ];

    return $w;
}

/** Text für die Folgeknöpfe: Beschriftung je Antwort-ID. */
function chatKnopfBeschriftungen(): array {
    return [
        'termin'             => 'Termin buchen',
        'nachricht'          => 'Nachricht senden',
        'preise'             => 'Was kostet es?',
        'seo_leistung'       => 'Google-Profil optimieren',
        'seo_ablauf'         => 'Wie läuft es ab?',
        'seo_dauer'          => 'Wie schnell wirkt es?',
        'webdesign_leistung' => 'Webdesign & Relaunch',
        'webdesign_ablauf'   => 'Ablauf Relaunch',
        'webdesign_pflege'   => 'Wartung & Pflege',
        'profilcheck'        => 'Profil kostenlos prüfen',
        'speedcheck'         => 'Website-Tempo messen',
        'referenzen'         => 'Kundenbeispiele',
        'garantie'           => 'Gibt es Garantien?',
        'vertrag'            => 'Vertrag & Vorkasse',
        'erstgespraech'      => 'Das Erstgespräch',
        'bewertungen'        => 'Bewertungen',
        'mensch'             => 'Mit Leandro sprechen',
        'selbst_machen'      => 'Selbst machen?',
    ];
}
