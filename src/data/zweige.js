// ---------------------------------------------------------------------------
// DIE ZWEI ZWEIGE DER SEITE
//
// jungline.de hat seit dem Webdesign-Angebot zwei getrennte Angebote, die sich
// an unterschiedliche Bedürfnisse richten:
//
//   seo        — Google-Unternehmensprofil / lokale Sichtbarkeit (der ältere
//                Zweig; die Startseite "/" gehört inhaltlich hierher und darf
//                deshalb NICHT umziehen, sie trägt die Rankings und Backlinks)
//   webdesign  — Relaunch, Erneuerung, Pflege von Websites ("/webdesign/")
//
// Diese Datei ist die EINE Quelle für alles, was den Zweig unterscheidet:
// Navigationslinks, Footer-Spalte, Beschriftung im Umschalter und der
// Auswahlkarte auf dem Startscreen. Nav und Footer werden daraus zur Build-Zeit
// pro Zweig gerendert (siehe vite.config.js). Wer hier einen Link ergänzt,
// ergänzt ihn damit automatisch in Desktop-Nav, Mobil-Menü, Footer und
// Umschalt-Menü — es gibt keine zweite Stelle zum Nachziehen.
//
// Reihenfolge der Einträge in `zweige` = Reihenfolge im Umschalt-Menü und auf
// dem Startscreen.
// ---------------------------------------------------------------------------

export const zweige = {
  seo: {
    id: 'seo',
    // Kurzform: steht im Umschalt-Knopf in der Navigationsleiste. Muss knapp
    // sein, der Knopf steht neben dem Logo.
    kurz: 'Local SEO',
    // Langform: Überschrift im Umschalt-Menü und auf der Startscreen-Karte.
    titel: 'Local SEO &amp; Google-Profil',
    // Ein Satz, der die Entscheidung abnimmt. Bewusst aus Kundensicht
    // formuliert ("gefunden werden"), nicht aus Leistungssicht ("Optimierung").
    beschreibung: 'Bei Google in Ihrer Region gefunden werden',
    // Startseite des Zweigs — Ziel von Umschalter und Startscreen-Karte.
    start: '/',
    // Ziel der "Termin buchen"-Schaltflaeche in der Navigationsleiste. Jeder
    // Zweig fuehrt zu SEINEM Buchungsbereich — sonst landet ein
    // Webdesign-Interessent im Kalender des SEO-Zweigs.
    terminHref: '/kontakt/#termin',
    // Beschriftung der Auswahlkarte auf dem Startscreen. Bewusst die Worte,
    // nach denen Kunden selbst fragen — nicht die Fachbegriffe von oben.
    startscreenTitel: 'SEO Optimierung',
    // Zusatzzeile nur auf dem Startscreen (etwas ausführlicher als oben).
    startscreen: 'Ihr Google-Unternehmensprofil so aufstellen, dass Kunden Sie im Kartenbereich zuerst sehen — statt den Wettbewerb.',
    icon: '<path d="M12 21s7-6.1 7-11.5A7 7 0 0 0 5 9.5C5 14.9 12 21 12 21Z"/><circle cx="12" cy="9.4" r="2.1"/>',
    links: [
      { href: '/leistungen/google-unternehmensprofil-optimierung/', label: 'Leistungen' },
      { href: '/#branchen', label: 'Branchen' },
      { href: '/ratgeber/', label: 'Ratgeber' },
      { href: '/ueber-mich/', label: 'Über mich' },
      { href: '/kontakt/', label: 'Kontakt' },
    ],
    // Fließtext unter dem Logo im Footer und die kleine Zeile ganz unten.
    claim: '<b>Mehr Sichtbarkeit. Mehr Kunden. Mehr Umsatz.</b> Ich bringe regionale Unternehmen bei Google nach oben: persönlich, transparent, messbar.',
    claimKlein: 'Google Unternehmensprofil Optimierung · bundesweit',
    // Erste Footer-Spalte: das Angebot des Zweigs.
    footerTitel: 'Local SEO',
    footerLinks: [
      { href: '/leistungen/google-unternehmensprofil-optimierung/', label: 'Leistungen' },
      { href: '/#branchen', label: 'Branchen' },
      { href: '/ratgeber/', label: 'Ratgeber' },
      { href: '/ueber-mich/', label: 'Über mich' },
      { href: '/kontakt/', label: 'Kontakt' },
    ],
    // Zweite Footer-Spalte: Sprungmarken innerhalb der Zweig-Startseite.
    // Absolute Pfade ("/#…"), damit sie auch von Unterseiten aus funktionieren.
    footerAnkerTitel: 'Startseite',
    footerAnker: [
      { href: '/#vorher-nachher', label: 'Vorher &amp; Nachher' },
      { href: '/#vorgehen', label: 'Vorgehen' },
      { href: '/#ergebnisse', label: 'Ergebnisse' },
      { href: '/#faq', label: 'FAQ' },
    ],
  },

  webdesign: {
    id: 'webdesign',
    kurz: 'Webdesign',
    titel: 'Webdesign &amp; Relaunch',
    beschreibung: 'Eine Website, die zu Ihrer Arbeit passt',
    start: '/webdesign/',
    terminHref: '/webdesign/#termin',
    startscreenTitel: 'Webdesign',
    startscreen: 'Ihre bestehende Website von Grund auf erneuern: schneller, klarer, auf dem Handy so gut wie am Rechner — und danach gepflegt.',
    icon: '<rect x="2.5" y="4" width="19" height="14.5" rx="2.4"/><path d="M2.5 8.6h19"/><path d="M5.6 6.3h.01M8.1 6.3h.01M10.6 6.3h.01"/>',
    links: [
      { href: '/webdesign/#leistungen', label: 'Leistungen' },
      { href: '/webdesign/#ablauf', label: 'Ablauf' },
      { href: '/webdesign/#preise', label: 'Preise' },
      { href: '/ueber-mich/', label: 'Über mich' },
      { href: '/kontakt/', label: 'Kontakt' },
    ],
    claim: '<b>Neu gebaut. Nicht neu gestrichen.</b> Ich erneuere Websites regionaler Unternehmen von Grund auf — und pflege sie danach weiter.',
    claimKlein: 'Webdesign, Relaunch &amp; Pflege · bundesweit',
    footerTitel: 'Webdesign',
    footerLinks: [
      { href: '/webdesign/#leistungen', label: 'Leistungen' },
      { href: '/webdesign/#ablauf', label: 'Ablauf' },
      { href: '/webdesign/#preise', label: 'Preise' },
      { href: '/ueber-mich/', label: 'Über mich' },
      { href: '/kontakt/', label: 'Kontakt' },
    ],
    footerAnkerTitel: 'Webdesign-Seite',
    footerAnker: [
      { href: '/webdesign/#vergleich', label: 'Vorher &amp; Nachher' },
      { href: '/webdesign/#technik', label: 'Technik' },
      { href: '/webdesign/#referenz', label: 'Arbeitsprobe' },
      { href: '/webdesign/#faq', label: 'FAQ' },
    ],
  },
}

export const zweigListe = [zweige.seo, zweige.webdesign]
