// ---------------------------------------------------------------------------
// KUNDENREFERENZEN — eine Quelle für den Abschnitt "Echte Ergebnisse" auf der
// Startseite (gerendert von renderReferenzen() in vite.config.js, Token
// <!--REFERENZEN-->).
//
// REGELN (UWG): Nur echte, belegbare Angaben. Nichts schätzen, nichts runden,
// keine erfundenen Zitate. Was nicht belegt ist, bleibt null — dann wird es
// einfach nicht angezeigt.
//
// Felder
//   name, googleUrl        Pflicht
//   logo                   { webp, png, width, height, alt } — webp nur angeben,
//                          wenn die Datei wirklich existiert (kein Rückfall bei 404)
//   suchbegriff            Suche, für die das Profil auf Platz 1 steht
//   platzStand             'MM/JJJJ' — wann die Platzierung zuletzt geprüft wurde
//   sterne                 Bewertungsschnitt, z. B. '5,0'
//   bewertungen            { anzahl, stand: 'MM/JJJJ' } oder null. Anzahl bewusst
//                          weggelassen, solange sie niemand regelmäßig pflegt —
//                          eine veraltete Zahl fällt jedem auf, der nachprüft.
//   zitat                  { text, quelle } oder null — nur wörtlich aus Google
//   kennzahlen             { zeitraum, quelle, werte: [{ label, vorher, nachher }] }
//                          oder null. Beispiel-Labels: 'Anrufe', 'Routenanfragen',
//                          'Website-Klicks'. Quelle z. B. 'Google-Unternehmensprofil,
//                          Leistungsbericht'. Vorher/Nachher als echte Zahlen
//                          aus dem Bericht, gleich lange Zeiträume.
//
// Neue Fallbeispiele: einfach ein weiteres Objekt anhängen. Ab drei Einträgen
// bleibt das Raster zweispaltig und bricht sauber um.
// ---------------------------------------------------------------------------

export const referenzen = [
  {
    name: 'Energieberatung Nordbayern',
    googleUrl: 'https://maps.google.com/maps/search/Energieberatung+Nordbayern/',
    logo: { webp: '/clients/lll.webp', png: '/clients/lll.png', width: 200, height: 200, alt: 'Logo Energieberatung Nordbayern' },
    suchbegriff: 'Energieberatung Nürnberg',
    // TODO(Leandro): Platzierung prüfen und Monat eintragen, z. B. '09/2026'.
    platzStand: null,
    sterne: '5,0',
    bewertungen: null,
    // TODO(Leandro): Bitte bestätigen, dass dieses Zitat wörtlich aus einer
    // Google-Bewertung stammt. Falls nicht: zitat auf null setzen.
    zitat: { text: '„Sehr empfehlenswert – fünf Sterne!“', quelle: 'Energieberatung Nordbayern' },
    kennzahlen: null,
  },
  {
    name: 'Sicitalia – So schmeckt der Süden',
    googleUrl: 'https://www.google.com/maps/place/Sicitalia+-+So+schmeckt+der+S%C3%BCden/@51.3555518,6.7756117,17z/data=!3m1!4b1!4m6!3m5!1s0x47b8e9dd1f73f467:0xd67128ca58cc4146!8m2!3d51.3555485!4d6.7781866!16s%2Fg%2F1v5fq_kw',
    logo: { webp: '/clients/sicitalia.webp', png: '/clients/sicitalia.png', width: 420, height: 172, alt: 'Logo Sicitalia – So schmeckt der Süden' },
    suchbegriff: 'Großhandel Italienischer Backwaren Duisburg',
    // TODO(Leandro): Platzierung prüfen und Monat eintragen.
    platzStand: null,
    sterne: '5,0',
    bewertungen: null,
    // Bewusst kein Zitat: Der Kunde hat fünf Sterne ohne Text vergeben.
    zitat: null,
    kennzahlen: null,
  },
  // TODO(Leandro): 2–3 weitere Fallbeispiele mit echten Kennzahlen aus dem
  // Leistungsbericht des Google-Profils ergänzen (Anrufe, Routenanfragen,
  // Website-Klicks, gleicher Zeitraum vorher/nachher).
]
