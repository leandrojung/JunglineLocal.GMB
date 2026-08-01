// Einzige Datenquelle für die 8 Bausteine der Google-Unternehmensprofil-Optimierung.
// Wird von der Startseite (Kurzfassung) UND der Leistungen-Seite (Volldarstellung)
// zur Build-Zeit über vite.config.js gerendert. Inhalte hier ändern, nicht in den
// HTML-Dateien — sonst laufen Start- und Leistungen-Seite wieder auseinander.

export const bausteine = [
  {
    num: '01',
    title: 'Kategorien &amp; Suchbegriffe',
    teaser: 'Die richtigen Kategorien und lokalen Suchbegriffe. Recherchiert, nicht geraten.',
    full: 'Die passende Primär- und Sekundärkategorie sind der wichtigste Hebel überhaupt. Dazu recherchierte lokale Suchbegriffe, nach denen Ihre Kunden wirklich suchen — statt geraten.',
    icon: '<path d="M20.6 13.4 12 22l-9-9V4h9z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
  },
  {
    num: '02',
    title: 'Profilbeschreibung',
    teaser: 'Ein suchmaschinenoptimierter Text, der Ihre Leistungen und Ihre Stadt dort platziert, wo Google sie gewichtet.',
    full: 'Ein sauberer, suchmaschinenoptimierter Profiltext, der Ihre Leistungen und Ihre Stadt dort platziert, wo Google sie gewichtet — ohne Keyword-Stuffing.',
    icon: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
  },
  {
    num: '03',
    title: 'Bilder &amp; Erscheinungsbild',
    teaser: 'Fotos, die Ihren Betrieb bei der Arbeit zeigen. Einheitlich und professionell.',
    full: 'Titelbild, Logo und Fotos, die Ihren Betrieb bei der Arbeit zeigen — einheitlich und professionell. Bilder sind oft der erste Eindruck vor dem ersten Kontakt.',
    icon: '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
  },
  {
    num: '04',
    title: 'Leistungen &amp; Attribute',
    teaser: 'Jede Leistung einzeln angelegt. Google belohnt vollständige Daten.',
    full: 'Jede Dienstleistung einzeln angelegt, mit allen relevanten Attributen. Vollständige Daten sind der Punkt, an dem die meisten Profile Sichtbarkeit verschenken.',
    icon: '<path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>',
  },
  {
    num: '05',
    title: 'Bewertungsstrategie',
    teaser: 'Mehr echte Bewertungen, professionell beantwortet. Auch bei Kritik.',
    full: 'Ein realistischer, richtlinienkonformer Plan für mehr echte Bewertungen — plus professionelle Antworten, auch auf kritische Stimmen. Keine gekauften Rezensionen.',
    icon: '<path d="M12 2l3 6.5 7 .8-5 4.7 1.3 6.9L12 17.8 5.4 20.9 6.7 14l-5-4.7 7-.8z"/>',
  },
  {
    num: '06',
    title: 'Beiträge &amp; Q&amp;A',
    teaser: 'Regelmäßige Posts und vorbereitete Antworten halten Ihr Profil aktiv.',
    full: 'Regelmäßige Google-Beiträge zeigen Aktivität, vorbereitete Antworten im Frage-&amp;-Antwort-Bereich verhindern Falschinformationen durch Dritte. Beides zusammen hält Ihr Profil sichtbar lebendig.',
    icon: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
  },
  {
    num: '07',
    title: 'Branchenverzeichnisse',
    teaser: 'Identische Firmendaten in den wichtigen Verzeichnissen. Konsistenz stärkt Ihr Ranking.',
    full: 'Identische Firmendaten in den wichtigen deutschen Verzeichnissen (u.&thinsp;a. Gelbe Seiten, Das Örtliche, 11880). Konsistente Einträge stärken das lokale Vertrauen bei Google.',
    icon: '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
  },
  {
    num: '08',
    title: 'Monitoring &amp; Berichte',
    teaser: 'Sie sehen, was sich verändert. Nicht nur, dass gearbeitet wurde.',
    full: 'Sichtbarkeits-Tracking mit Vorher-nachher-Vergleich. Sie sehen schwarz auf weiß, was sich verändert — nicht nur, dass gearbeitet wurde.',
    icon: '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
  },
]
