# Masterprompt: Websites im Jungline-Stil

Grundlage: vollständige Analyse von jungline.de, und zwar am Quellcode in diesem
Repository (`src/css/site.css`, `src/js/site.js`, `index.html`, `partials/`,
`webdesign/`, Unterseiten) und an der gebauten Seite in Chromium bei 1440 px
(Desktop) und 390 px (iPhone) Breite. Stand: Oktober 2026.

## So benutzt du diesen Prompt (für Leandro, nicht mitkopieren)

1. **Kundenprofil ausfüllen** (Teil A, Abschnitt 2). Alles in `[ECKIGEN KLAMMERN]`
   ist ein Platzhalter. Was du nicht weißt, lässt du in Klammern stehen: Die KI
   soll dann nachfragen, statt zu erfinden.
2. **Bausteine wählen** (Teil A, Abschnitt 7): `[x]` für rein, `[ ]` für raus.
   Nicht jede Seite braucht alles. Zwölf Effekte auf der Seite einer Bäckerei
   wirken wie Angeberei, nicht wie Apple.
3. **Kopieren ab der Zeile `=== PROMPT BEGINNT ===` bis zum Ende.**
   - Claude Code, Claude-Projekte, ChatGPT mit Datei-Upload: alles, inklusive
     Teil B (Code-Referenz).
   - Werkzeuge mit Zeichenlimit (KI-Baukästen): Teil A als Text einfügen,
     Teil B als Datei anhängen. Geht das nicht, Teil B weglassen. Die Regeln in
     Teil A enthalten alle wichtigen Werte, nur eben ohne fertigen Code.
4. **Nach dem ersten Entwurf** mit der Abnahme-Checkliste (Teil A, Abschnitt 15)
   prüfen und gezielt nachsteuern („Punkt 7 ist nicht erfüllt: …“).

### Ehrliche Vorbemerkung

- **Eine 1:1-Kopie macht jede Kundenseite zu einer Jungline-Kopie.** Deshalb
  trennt der Prompt die *DNA* (fest: Ruhe, Typografie, Flächen,
  Bewegungsprinzipien) von der *Ausprägung* (variabel: Farbe, Beweis-Objekt,
  Mitteilungen, Bausteine).
- **Mehrere Bausteine sind auf Google-Profile zugeschnitten** (Local-Pack-Karte,
  Profil-Check, Live-Handys). Der Prompt übersetzt sie in ein allgemeines Muster,
  das „Beweis-Objekt“. Ohne diese Übersetzung baut die KI auch einem Friseur eine
  Google-Trefferliste in den Hero.
- **Der Stil lebt von echten Inhalten**: Foto des Inhabers, echte Bewertungen,
  echte Logos, echte Arbeitsproben. Fehlen die, wird auch dieser Stil generisch.
  Dann lieber weniger Bausteine als aufgefüllte.
- **Zwei Schwächen der eigenen Seite** habe ich bewusst *nicht* in die Regeln
  übernommen: Im Schlusssatz standen die drei Symbole am Desktop nicht in einer
  Spalte (zentrierte Zeilen unterschiedlicher Länge; auf jungline.de inzwischen
  behoben), und die 76-%-Statistik stammt von 2014. Der Prompt verlangt deshalb
  linksbündige Symbolspalten und Quellen, die nicht älter als drei Jahre sind.
- **Der Code in Teil B ist geprüft**: Alle CSS- und JS-Blöcke sind
  syntaktisch fehlerfrei (esbuild, Node). Zusätzlich habe ich nur aus den
  Snippets eine Demo-Seite für einen erfundenen Gartenbau-Kunden (Grün statt
  Indigo) gebaut und in Chromium angesehen. Drei Lücken, die dabei auffielen,
  sind behoben: graue Kacheln auf grauem Grund, zu lange Hero-Zeile auf dem
  Telefon, versetzte Kachelüberschriften bei ungleich langen Texten.

---

=== PROMPT BEGINNT ===

# Teil A: Auftrag und Regeln

## 1 Rolle, Ziel, Arbeitsweise

Du bist Senior-Webdesigner und Frontend-Entwickler mit dem Blick eines
Apple-Designers. Du baust für das unten beschriebene Unternehmen eine
Website im Designsystem **„Ruhige Bühne“** (abgeleitet von jungline.de).

Ziel: Ein Besucher versteht in fünf Sekunden, *was* das Unternehmen tut, *für
wen* und *was er als Nächstes tun soll*, und die Seite wirkt dabei hochwertig,
ruhig und von Hand gemacht, nicht nach Vorlage.

Arbeitsweise, in dieser Reihenfolge:

1. Lies den ganzen Prompt, bevor du anfängst. Teil B ist eine Code-Referenz:
   Muster mit erprobten Werten, keine Vorlage zum blinden Kopieren.
2. Fehlen im Kundenprofil Pflichtangaben, stelle **höchstens fünf gebündelte
   Rückfragen**. Erfinde **niemals** Bewertungen, Kundennamen, Zitate, Zahlen,
   Zertifikate, Auszeichnungen oder Preise.
3. Lege die **Tokens** fest (Farben nach Abschnitt 4, Schrift, Maße) und zeige
   die abgeleitete Palette mit den gemessenen Kontrastwerten.
4. Zeige die **Gliederung der Startseite**: Abschnitte in Reihenfolge, je Fläche
   (dunkel, hellgrau, weiß) und je ein Satz zum Zweck.
5. Baue die Seiten. Erst Desktop und Telefon gleichberechtigt, dann Tablet.
6. Prüfe vor der Abgabe die **Abnahme-Checkliste** (Abschnitt 15) Punkt für
   Punkt und melde das Ergebnis ehrlich, auch was nicht erfüllt ist.

## 2 Kundenprofil

```
Firmenname:              [NAME]
Markenname zweiteilig:   [z. B. "Müller" + "Bad", zweites Wort in Markenfarbe]
Branche:                 [BRANCHE]
Ort / Einzugsgebiet:     [STADT, REGION, bundesweit?]
Gesicht der Firma:       [NAME, ROLLE] Porträtfoto vorhanden: [ja/nein]
Zielgruppe:              [WER sucht WAS in welcher Situation]
Hauptangebot (1 Satz):   [ANGEBOT]
Leistungen:              [3 bis 9 Stichpunkte, je mit 1 Satz Nutzen]
Wichtigste Handlung:     [Anruf | Termin buchen | Anfrage | Reservierung | Kauf]
Zweite Handlung:         [z. B. Rechner testen, Arbeiten ansehen, Leistungen]
Belege:                  [Google-Sterne + Anzahl, Referenzen mit Logo,
                          Zahlen MIT Quelle und Jahr, Auszeichnungen]
Echte Zitate:            [wörtlich, mit Name und Firma, oder "keine"]
Markenfarbe(n):          [HEX aus dem Logo, oder "frei wählen"]
Logo:                    [Datei; Bildmarke einzeln vorhanden: ja/nein]
Ansprache:               [Sie | du]
Tonalität:               [z. B. ruhig, direkt, handwerklich, sachlich-ärztlich]
Seiten:                  [Start, Leistungen, Über uns, Kontakt, Impressum,
                          Datenschutz, ...]
Technik:                 [Vite + Vanilla JS (Standard) | Astro | Next.js |
                          WordPress-Theme | eine einzelne HTML-Datei]
Werberecht der Branche:  [z. B. HWG bei Heilberufen, BORA bei Anwälten, keins]
Besonderheiten:          [z. B. zwei getrennte Angebote, Notdienst, Mehrsprachig]
```

## 3 Design-DNA (fest, gilt für jeden Kunden)

**Leitsatz:** Ruhige Flächen, große Schrift, eine Akzentfarbe. Tiefe entsteht
durch den Wechsel der Flächen (Weiß, Hellgrau `#F5F5F7`, tiefes Dunkel) und
durch **Sheets**: Jede neue Fläche schiebt sich mit runder Oberkante über die
vorige, wie ein iOS-Blatt.

1. **Eine Akzentfarbe** für alles, was man anklicken kann: Buttons, aktive
   Zustände, Links, Symbole in Kacheln. Dazu höchstens **ein heller
   Lichtakzent** (bei Jungline Mint `#6FE8BD`) für winzige Signale: der
   Live-Punkt, Einheiten hinter großen Zahlen, das Schlüsselwort im Schlusssatz.
2. **Neutrale Grautöne von apple.com**: Text `#1D1D1F`, Sekundär `#4B4B50`
   und `#6E6E73`, Tertiär `#86868B`, Flächen `#FFFFFF` und `#F5F5F7`, Linien
   `#E3E3E8`.
3. **Dunkle Bühne** für Hero, Seitenkopf der Unterseiten, Showcase, Zahlen,
   Schlusssatz und Footer: fast schwarz mit einem Hauch der Markenfarbe, darauf
   zwei große, weiche, radiale Lichtflecken (Marke oben rechts, Lichtakzent
   unten links, je 10 bis 34 % Deckkraft). Keine Muster, kein Rauschen.
4. **Überschriften immer einfarbig.** Nie Verlaufstext. Zweiteilige
   Überschrift: Aussage in Textfarbe, Nachsatz in Grau (`.soft`), zentriert als
   zweite Zeile. Im Hero ist die zweite Zeile die helle Markenfarbe.
5. **Karten ohne Rahmen.** Tiefe über weiche, mehrlagige Schatten *oder* über
   den Flächenwechsel: graue Kachel auf Weiß, weiße Kachel auf Grau. Beim
   Überfahren wird die graue Kachel weiß, hebt sich 2 bis 4 px und bekommt
   Schatten.
6. **Große Radien**: 18, 26 und 34 px; Pillen 999 px; Sheets
   `clamp(26px, 4vw, 48px)`; App-Symbole als Squircle (38 px, Radius 9,5 px).
7. **Ein Beweis-Objekt statt Stockfoto.** Rechts im Hero steht ein Stück
   Oberfläche, das das Ergebnis für den Kunden zeigt (Abschnitt 8), umrahmt
   von iOS-Mitteilungen.
8. **Das Wiedererkennungsstück ist die iOS-Mitteilung**: Squircle-Symbol,
   fette Zeile, Zeitstempel rechts, darunter eine Zeile Text. Einsatz an drei
   bis fünf Stellen der Seite, nie öfter.
9. **Bewegung ist Material, kein Effekt**: nur `opacity` und `transform`,
   kurze Wege (14 bis 40 px), Ausklang mit `cubic-bezier(.32,.72,0,1)`, kein
   Überschwingen (Ausnahme: kleine „Pin-Drop“-Momente).
10. **Endzustand = Ruhezustand.** Ohne JavaScript, mit „Bewegung reduzieren“,
    beim Drucken und für Suchmaschinen steht alles sofort vollständig da.

**Verboten**, weil es nach Vorlage oder generierter Landingpage aussieht:

- Farbverläufe in Überschriften, durchlaufende Lichtstreifen über Text, Glitzer
- Körnung, Rasterlinien, schwebende Farbwolken über die ganze Seite
- Reihen bunter Icon-Kacheln als Hauptgestaltung, Wolken aus Pillen
- Schreibmaschinen-Effekt, Partikel, 3D-Drehungen ganzer Abschnitte
- Emojis in Überschriften, GROSSBUCHSTABEN-Kennzeilen über jeder Überschrift
- Stockfotos (Handschlag, Headset, Laptop am Strand), erfundene Testimonials,
  Fake-Zähler, Countdown-Druck, „Nur noch 2 Plätze frei“
- `backdrop-filter` auf bewegten oder bildschirmfüllenden Flächen,
  `filter: blur()` auf großen Elementen (kostet in jedem Bild Rechenzeit)
- mehr als zwei Buttons nebeneinander, mehr als eine Handlungsfarbe

## 4 Farbe: Tokens und Ableitung aus der Kundenfarbe

Alle Farben sind Tokens (CSS-Variablen, Teil B1). Dunkle Abschnitte bekommen
die Klasse `.theme-dark`, die dieselben Token-Namen dunkel umbelegt. Helle
Inseln darin (Karten, Formulare) bekommen `.theme-light`. Ergebnis: Jede
Komponente funktioniert ohne Sonderregeln auf hell und dunkel.

Ableitung aus der Kundenfarbe (Werte selbst berechnen und Kontraste ausgeben):

| Token | Regel | Jungline |
| --- | --- | --- |
| `--brand` | Kundenfarbe. Weißer Text darauf mindestens 4,5:1, sonst dunkler stufen | `#3D50C8` (6,6:1) |
| `--brand-hover` | 8 bis 12 % dunkler als `--brand` | `#3142AD` |
| `--brand-on-dark` | Markenton stark aufgehellt (HSL-Helligkeit 80 bis 86 %), auf `--navy-900` mindestens 7:1 | `#AEBBFF` |
| `--link` | Fließtext-Link auf Weiß, mindestens 4,5:1 (notfalls eine dunklere Stufe der Marke) | `= --brand` |
| `--navy-900` | Bühne: Farbton der Marke, Sättigung 40 bis 55 %, Helligkeit 6 bis 9 % | `#0A0D1F` |
| `--navy-800` | eine Stufe heller (Helligkeit 11 bis 13 %) | `#11152D` |
| `--spark` | Lichtakzent: hell, gesättigt, zur Marke kontrastierend, sparsam | `#6FE8BD` |

Beispiel zum Nachrechnen (Gartenbau, Logo-Grün `#1F7A4D`, Farbton 150°):
`--brand:#1F7A4D` (Weiß darauf etwa 5,3:1), `--brand-hover:#19663F`,
`--brand-on-dark:#9FE3BE`, `--navy-900:#08160F`, `--navy-800:#0F2219`,
`--spark` warm, z. B. `#F6D88A`.

Weitere Regeln:

- Schatten sind leicht in den Bühnenton getönt (`rgba(R,G,B,…)` von `--navy-900`
  statt reinem Schwarz).
- Hat der Kunde zwei Angebote, kann ein zweiter Bereich der Seite die Marke per
  Token-Wechsel umfärben (`body[data-bereich="b"]{--brand:…}`), wie jungline.de
  den Webdesign-Zweig in Apple-Blau `#0071E3` zeigt. Sonst nie zwei Marken.
- Feste Systemfarben für Mitteilungs-Symbole (Teil B5): Telefon grün, Stern
  orange, Kalender weiß mit rotem Symbol, Warnung rot, Erfolg grün, Karte in
  Markenfarbe. Das sind iOS-Zitate und bleiben bei jedem Kunden gleich.

## 5 Typografie

- **Schrift:** auf Apple-Geräten die Systemschrift SF Pro (über
  `-apple-system`, ohne Download, lizenzsauber), auf allen anderen eine
  **selbst gehostete, SF-nahe Grotesk** (Standard: Instrument Sans, variabel,
  woff2). `font-display: swap`, `preload` mit `fetchpriority="high"`, dazu eine
  Ersatzschrift mit angepassten Metriken (`size-adjust`, `ascent-override`),
  damit beim Nachladen nichts springt. Keine Google-Fonts-Einbindung, keine
  fremden CDNs (DSGVO).
- Hat der Kunde eine Hausschrift: nur für Überschriften (`--font-display`),
  Fließtext bleibt Systemschrift.
- **Skala** (alle Werte fließend):

| Element | Größe | Zeilenhöhe | Laufweite | Gewicht |
| --- | --- | --- | --- | --- |
| Hero-H1 | `clamp(2.8rem,6vw,5rem)` | .98 | -.03em | 700 |
| H1 Unterseite | `clamp(2.5rem,5.6vw,4.6rem)` | 1 | -.03em | 700 |
| H2 | `clamp(2rem,4.2vw,3.5rem)` | 1.07 | -.022em | 700 |
| H3 | `clamp(1.2rem,1.6vw,1.42rem)` | 1.07 bis 1.25 | -.025em | 700 |
| Lead | `clamp(1.12rem,1.45vw,1.32rem)` | 1.5 | -.011em | 400 |
| Untertitel unter H2 | `clamp(1.1rem,1.5vw,1.3rem)`, Farbe `--dim-2`, max. 52ch | 1.45 | -.014em | 400 |
| Fließtext | 1.0625rem (17 px) | 1.55 (Ratgeber 1.75) | -.011em | 400 |
| Große Zahl | `clamp(3.4rem,7vw,5.6rem)` | 1 | -.02em | 700 |
| Schlusssatz | `clamp(2.3rem,7.2vw,6rem)` | 1.02 | -.03em | 700 |
| Klein, Fußnoten | .76 bis .86rem, Farbe `--dim-2` | 1.5 | normal | 400 |

- Überschriften `text-wrap: balance`, Absätze `text-wrap: pretty`.
- **Deutsche Komposita**: kein `hyphens:auto` (trennt auch Wörter, die passen
  würden). Lange Wörter bekommen `&shy;` an der richtigen Silbe, dazu
  `overflow-wrap: break-word` als Netz. Unterseiten-H1 auf dem Telefon
  `clamp(2rem,9.2vw,2.6rem)`, damit „Unternehmensprofil“ ab 360 px ganz steht.
- **Hero-H1 auf dem Telefon**: jede Zeile `white-space: nowrap`, Größe
  `min(calc((100vw - 2 * var(--gutter)) / T), 3.5rem)`. T ist die Breite der
  längsten Zeile in Schriftgrößen, gemessen, plus Reserve (bei „Oben bei
  Google.“ gemessen 7,7, verwendet 8). Ergebnis: Die Zeile steht nie am Rand.
  Faustwert vor dem Messen: T ≈ Zeichenzahl der längsten Zeile × 0,5 plus 0,3.
  Eine Zeile mit mehr als rund 16 Zeichen wird auf dem Telefon zu klein:
  dann den Text kürzen, nicht die Schrift.
- Einheiten hinter Zahlen mit schmalem Leerzeichen (`&thinsp;`), halb so groß,
  im Lichtakzent: „76 %“, „2,7 ×“.

## 6 Layout, Raster, Rhythmus

- Container 1200 px, Seitenrand `clamp(20px,3.4vw,32px)`.
- Abschnittsluft `clamp(76px,9vw,140px)`; Abstand Kopf zu Inhalt
  `clamp(36px,4.4vw,60px)`.
- **Überschriften-Block** (`.head-block`): zentriert (max. 860 px), H2 plus ein
  Satz darunter. Variante „Split“ ab 900 px: H2 links, Satz rechts unten bündig.
- **Flächenfolge** der Startseite, so abwechseln:
  dunkel (Hero) → hellgrau Sheet → dunkel Sheet → hellgrau Sheet → weiß Sheet →
  weiß → dunkel Sheet → weiß Sheet → hellgrau → weiß Sheet → dunkel (Schluss)
  → Footer dunkel (verschmilzt ohne Kante mit dem Schluss).
  Regeln: Dunkel höchstens jeden zweiten bis dritten Abschnitt. Ein Sheet nur
  dort, wo die Fläche wechselt. Folgen zwei weiße Abschnitte ohne Kante, auf
  dem Telefon die doppelte Luft dazwischen auf 20 % kürzen.
- **Sheets**: `margin-top` negativ um genau den Radius, die vorige Fläche füllt
  die Ecken, steigender `z-index` (Teil B2). Die erste Fläche nach jedem
  dunklen Seitenkopf ist automatisch ein Sheet.
- **Raster**: Hero `1fr / 450px`; Über mich `.8fr / 1.2fr`; FAQ `.8fr / 1.2fr`
  mit klebendem Kopf links; Zahlen dreispaltig mit Trennlinien statt Kästen;
  Ablauf vierspaltig (Bento); Kacheln `repeat(auto-fill,minmax(230px,1fr))`.
- **Umbruchpunkte**: 1440, 1100, 1060, 960 (Menü wird Burger, Hero einspaltig),
  900, 860, 760, 700, **640 (eigene Telefon-Komposition)**, 560, 480, 400.

## 7 Seitenaufbau

### 7.1 Startseite (Bausteine wählen)

| Wahl | # | Abschnitt | Fläche | Inhalt |
| --- | --- | --- | --- | --- |
| [x] | 1 | Navigation | schwebend | Glasleiste, Logo-Kachel, Links mit gleitender Pille, Termin-Button |
| [x] | 2 | Hero | dunkel | Live-Pille, H1 in zwei Zeilen, Lead (ein Satz), Primär- und Glas-Button, Personenzeile; rechts Beweis-Objekt mit zwei Mitteilungen, Fußnote „Beispieldarstellung“ |
| [ ] | 3 | Belege | hellgrau Sheet | zwei Karten: Logo auf Weiß, Mitteilung „Ergebnis“, Sterne, Zitat, Link „Profil ansehen ↗“ |
| [ ] | 4 | Showcase | dunkel Sheet | zwei Geräte nebeneinander (gut gegen schwach) mit Befund-Mitteilungen links und rechts, ODER Vorher/Nachher-Regler |
| [ ] | 5 | Werkzeug | hellgrau Sheet | Rechner, Check oder Konfigurator in weißer Karte, dahinter Radar-Ringe; links H2 mit Akzentwort und Häkchen-Chips |
| [x] | 6 | Ablauf | weiß Sheet | Bento aus vier Kacheln (Symbol, „Schritt 1“, H3, ein Satz, „Mehr erfahren ›“), darunter Chips und Textlink |
| [x] | 7 | Leistungen oder Branchen | weiß | Kachelraster (Symbol, Name, Pfeil), darunter Laufband mit weiteren Begriffen |
| [ ] | 8 | Zahlen | dunkel Sheet | drei große Zahlen mit Einheit im Lichtakzent, Satz darunter, Quellen klein |
| [x] | 9 | Über mich / uns | weiß Sheet | Porträt 4:5 mit Glas-Namensschild, zweiteilige H2, ein Absatz, drei Chips, Signatur mit Markenlinie, Textlink |
| [ ] | 10 | Querverweis-Band | weiß | helles Band: Kennzeile, Titel, ein Satz, Button rechts |
| [x] | 11 | FAQ | hellgrau | links H2 klebend, rechts Akkordeon (5 bis 8 Fragen) |
| [x] | 12 | Kontakt | weiß Sheet | H2, Mitteilung „Erstgespräch, 30 Min.“, links Buchung oder Formular im grauen Panel, rechts Kontaktkacheln und Formular |
| [x] | 13 | Schlusssatz | dunkel Sheet | drei Zeilen „Mehr X.“ mit animierten Linien-Symbolen, Schlüsselwort im Lichtakzent |
| [x] | 14 | Footer | dunkel | Marke und Claim, vier Linkspalten, Querverweis-Kachel, Copyright |
| [x] | 15 | Mobile Aktionsleiste | schwebend unten | Primär-Button plus runder Anruf-Knopf, nur Telefon und Tablet |
| [ ] | 16 | Startscreen | Overlay | nur bei zwei klar getrennten Angeboten: „Wofür interessieren Sie sich?“ mit zwei Karten |

Einzelheiten je Abschnitt:

- **Hero**: `min-height: min(100svh, 1020px)`, Inhalt vertikal mittig. Links
  max. 660 px: Live-Pille (nächster freier Termin oder feste Zusage, Mint-Punkt
  mit Hof), H1 (Zeile 1 Ergebnis, Zeile 2 Bezug/Ort in `--brand-on-dark`),
  Lead max. 34ch, Buttons 36 px darunter, Personenzeile (Rundbild 40 px mit
  2-px-Hellring, „**Name** betreut Sie persönlich, aus ORT.“). Rechts das
  Beweis-Objekt (max. 450 px), leicht schwebend, folgt der Maus mit bis zu 5°
  Neigung. Hinter allem optional die Bildmarke des Kunden als feine Linie, die
  sich einmal selbst zeichnet und bei 5 % Füllung stehen bleibt.
- **Belege**: Karten Radius 34, Logo-Bereich 184 px hoch auf Weiß, darunter die
  Mitteilung um 50 px nach oben in den Logo-Bereich gezogen. Nur zeigen, was
  belegt ist: ohne Zitat kein Zitat, ohne Anzahl keine Anzahl.
- **Showcase**: Geräte frei im Raum, nicht bildschirmfüllend (höchstens 64 %
  der Fensterhöhe). Auf dem Telefon statt zwei Geräten untereinander ein
  iOS-Segment-Umschalter („● gut“ / „● schwach“), Wischen schaltet ebenfalls.
- **Ablauf**: Kacheln min. 260 px hoch, Hellgrau auf Weiß (auf grauem
  Abschnitt weiß), ganze Kachel ist der Link. Ab 1060 px zwei Spalten, ab
  560 px kompakt (Pfeil-Link entfällt). Die Sätze in den Kacheln gleich lang
  halten (gleiche Zeilenzahl), sonst stehen die Überschriften versetzt.
- **Leistungen/Branchen**: Auf dem Telefon wird das Raster zur **gruppierten
  iOS-Einstellungsliste** (eine graue Fläche, Zeilen 56 px, Trennlinien ab dem
  Text, Chevron rechts). Das Laufband: 90 s Endlosschleife, Ränder per Maske
  ausgeblendet, hält beim Überfahren an.
- **Zahlen**: nur mit Quelle und Jahr. Zahl zählt beim Sichtkontakt hoch, der
  Endwert steht aber von Anfang an im HTML. Telefon: Zahl links, Satz rechts.
- **Über mich**: Telefon quadratischer Bildausschnitt statt 4:5.
- **FAQ**: Plus im Kreis, wird beim Öffnen zum Minus im dunklen Kreis und dreht
  180°. Immer nur eine Antwort offen. Darunter „Frage stellen →“.
- **Kontakt**: Kalender/Formular in einer weißen Karte im grauen Panel. Drei
  nummerierte Zusagen („Sie gehen mit drei Dingen raus“). Kontaktkacheln
  (Symbol, Beschriftung klein, Wert fett). Jede Fehlermeldung nennt einen
  Rückfallweg (Telefon, E-Mail).
- **Schlusssatz**: Zeilen laufen nacheinander ein (180 ms Abstand), die Symbole
  bewegen sich in Schleife (Auge blinzelt, Person kommt dazu, Balken wachsen).
  Symbole bilden eine **linksbündige Spalte**, auch am Desktop.
- **Mobile Aktionsleiste**: erscheint nach 640 px Scrollweg, verschwindet,
  sobald Kontaktbereich oder Footer im Bild sind.

### 7.2 Unterseiten

Seitenkopf in derselben dunklen Bühne wie der Hero (Breadcrumb, H1 mit
Bezugswort in `--brand-on-dark`, Lead, ein bis zwei Buttons, wahlweise
zentriert). Darunter als Sheet: Fließtext (max. 720 px), „Text neben
Illustration“ (Mini-Version des Beweis-Objekts), Kacheln, Schrittfolge
(nebeneinander, Telefon senkrecht mit Verbindungslinie), Hinweiskasten,
„Weiterführend“-Links, und als Abschluss jeder Unterseite das **dunkle
CTA-Band** (Radius 34, Markenlicht oben rechts, H2 links, Buttons rechts).

## 8 Das Beweis-Objekt und die Mitteilungen, je Branche

**Bei jungline.de:** eine weiße Karte (Radius 28, sehr weicher großer Schatten)
mit Google-Suchzeile „Ihre Leistung in Ihrer Stadt“, Kartenausschnitt im Stil
von Apple Karten mit pulsierendem Pin, darunter drei Ergebniszeilen. „Ihr
Unternehmen“ klettert nach 1,55 s von Platz 15 auf Platz 1, die anderen
rutschen nach, ein Badge „↑ Platz 1“ erscheint. Danach laufen zwei
iOS-Mitteilungen ein: „Anruf über Google Maps“ (oben rechts, über die Ecke
ragend) und „Neue Bewertung ★★★★★“ (unten links, mit angedeutetem Stapel).

**Prinzip für jeden Kunden:** Zeige den *Moment, in dem der Kunde gewinnt*, als
Oberfläche. Aufbau: Kopfzeile (Suche, Kalenderkopf, Browserleiste), Bildteil,
darunter eine Liste oder ein Status, der sich **einmal** in den Gewinnzustand
sortiert. Zwei Mitteilungen ragen über die Ecken und erscheinen nacheinander.
Darunter rechtsbündig „Beispieldarstellung“, und keine Namen oder Zahlen, die
sich als echte Kundendaten lesen.

| Branche | Beweis-Objekt | Mitteilung 1 | Mitteilung 2 |
| --- | --- | --- | --- |
| Handwerk | Wochenplan, in den Aufträge einrutschen | „Neue Anfrage: Badsanierung“ (Telefon) | „Neue Bewertung ★★★★★“ |
| Gastronomie | Reservierungsbuch für heute Abend, Tische füllen sich | „Tisch für 4 reserviert, 19:30“ (Kalender) | „Neue Bewertung“ |
| Arzt, Praxis | Online-Kalender, freie Zeiten werden gebucht | „Termin bestätigt: Di, 10:00“ | „Erinnerung versendet“ (keine Heilversprechen, HWG) |
| Kanzlei | Liste der Rückrufe, eine Anfrage wird „Mandat“ | „Rückruf vereinbart: heute 16:00“ | „Unterlagen sicher übermittelt“ (sachlich, BORA) |
| Makler | Objektkarte, Status wechselt zu „Reserviert“ | „Besichtigung bestätigt: Sa, 11:00“ | „Neue Bewertungsanfrage“ |
| Kosmetik, Friseur | Tagesplan, Lücken füllen sich | „Termin gebucht: Balayage, Fr 14:00“ | „Neue Bewertung“ |
| Autohaus | Fahrzeugkarte mit Verfügbarkeit | „Probefahrt bestätigt“ | „Inzahlungnahme angefragt“ |
| Physiotherapie | Behandlungsplan mit freien Zeiten | „Termin bestätigt“ | „Neue Bewertung“ |
| Webdesign, Agentur | Browserfenster und Telefon mit echter Arbeitsprobe | „Anfrage über die neue Website“ | „Ladezeit: 0,9 s“ (nur wenn gemessen) |
| Lokales SEO | Google-Kartenbereich, Aufstieg auf Platz 1 | „Anruf über Google Maps“ | „Neue Bewertung“ |

Der Kartenausschnitt (falls verwendet) im Stil von Apple Karten: Land
`#F6F1E8`, Gebäude `rgba(96,80,56,.07)` mit feiner Kante, Einkaufsstraßen
`#FBEACB`, Grün `#D3EABF`, Wasser `#92D0F2`, Ringstraße `#DDDCE1` auf `#C7C6CB`,
Gassen weiß auf `#E1DACE`, Straßennamen 4,6 px, gesperrt, `#8B8478`.

## 9 Komponenten (Pflichtenheft, Code in Teil B)

- **Navigation (B7):** schwebend, 12 px vom oberen Rand, 60 px hoch, Radius 22,
  innen 10 bis 12 px Rand. Über dunklem Kopf: dunkles Glas
  (`rgba(255,255,255,.07)`, `blur(22px) saturate(180%)`, heller Hauch-Rand),
  weiße Schrift. Ab 24 px Scrollweg: helles Milchglas `rgba(255,255,255,.84)`.
  Logo als weiße Kachel 36 px (Radius 10), beim Überfahren `rotate(-6deg)
  scale(1.05)`. Markenname zweiteilig, zweites Wort in Marke (auf Dunkel
  `--brand-on-dark`). Links als 40-px-Pillen; die aktuelle Seite trägt eine
  gefüllte Pille in Marke, eine zweite, blasse Pille **gleitet** beim Überfahren
  von Eintrag zu Eintrag (0,5 s). Rechts der Primär-Button 42 px, der sich in
  der Leiste nicht anhebt. `meta theme-color` wechselt mit (dunkel/weiß).
  Ab 960 px abwärts: Burger aus drei 1,8-px-Strichen wird zum X. Das Menü ist
  ein **Vollbild-Blatt**: große Einträge (1,5rem, fett, iOS-Chevron), Einträge
  laufen gestaffelt ein (35 ms), unten in Daumenhöhe Primär-Button plus
  „Anrufen“ und „E-Mail“, ganz unten Name, Ort, Impressum. Seite dahinter
  gesperrt, Escape schließt.
- **Buttons (B3):** Pillen, 14/24 px Innenabstand (groß 16/28), Schrift 600.
  *Primär*: Marke, innere Lichtkante oben, farbiger weicher Schatten; beim
  Überfahren läuft **einmal** eine Lichtkante schräg durch (115°, 0,75 s), Knopf
  hebt sich 1 px und folgt der Maus magnetisch (10 % waagerecht, 13 %
  senkrecht, weich nachgeführt). *Glas* (auf Dunkel): `rgba(255,255,255,.1)`
  mit hellem Rand, ohne Blur. *Ghost* (auf Hell): nur Kontur. Beim Drücken
  `scale(.97)`. Auf dem Telefon im Hero volle Breite, untereinander.
- **Textlink mit Pfeil:** die ruhige Alternative zum zweiten Button; Pfeil
  rückt beim Überfahren 3 px nach rechts (externe Links: schräg nach oben).
- **Badges (B4):** Live-Pille (Punkt im Lichtakzent mit 3-px-Hof), „Neu“-Badge
  (gefüllte Mini-Pille links in Marke, Text, Pfeil), Ergebnis-Badge (gefüllt,
  „↑ Platz 1“), Status-Chip „Geöffnet“ (`#DCF2E3` auf `#12633A`), Warn-Chip
  (Rot 10 %), Rang-Quadrat (26 px, Radius 8), Glas-Namensschild auf dem
  Porträt, Sterne `#FBBC04`.
- **iOS-Mitteilung (B5):** Radius 22, Innenabstand 11/16/11/11, Symbol 38 px
  Squircle mit Verlauf, Titel .94rem 600 mit Auslassungspunkten, Zeit .78rem
  grau, Text .9rem. Varianten: schwebend (großer Schatten), flach (für Karten),
  dunkel (für dunkle Flächen), Stapel (zwei angedeutete Mitteilungen dahinter).
  Deckend, kein Milchglas.
- **Chips (B6):** Häkchen in Marke plus kurze Zusage, graue Pillen. Auf dem
  Telefon ohne Pille: nur Häkchen und Text in einer Zeile.
- **Kacheln (B10, B11, B23):** Bento-Kachel, Link-Karte, Inhalts-Kachel,
  gefüllte Verweis-Kachel in Marke mit großem Pfeil-Text unten.
- **Laufband (B12), Zahlen (B13), Porträt (B14), FAQ (B15),
  Vorher/Nachher-Regler (B16), Formularfelder (B17), Segment-Umschalter (B18),
  Kontaktkacheln (B19), Schlusssatz (B20), Footer und Aktionsleiste (B21),
  Wisch-Galerie (B22), Seitenkopf und CTA-Band (B23), Geräte-Attrappen (B24),
  Preiskarten (B25), Startscreen (B26), Mauszeiger-Pin (B28).**
- **Formularfelder:** Hintergrund `--surface-2`, kein sichtbarer Rand, Radius
  14, 14/16 px Innenabstand; beim Fokus weiß, Rand in Marke, 4-px-Hof in Marke
  (14 %). Beschriftung über dem Feld, .86rem, 600. „(optional)“ in Grau.
- **Preiskarten:** zwei Karten; die empfohlene ist dunkel (`--navy-900`) mit
  Aufzählungspunkten im Lichtakzent, die andere hellgrau. Betrag groß
  (`clamp(2.6rem,4.4vw,3.4rem)`), davor „ab“ klein, dahinter Einheit.

## 10 Bewegung und Animation

**Tokens:** `--ease:cubic-bezier(.2,.7,.2,1)`,
`--ease-out:cubic-bezier(.16,1,.3,1)`, `--ease-apple:cubic-bezier(.32,.72,0,1)`;
Dauer Tippen .16 s, UI .28 s, Bewegung .55 s.

**Regeln:**

1. Nur `opacity` und `transform` (bzw. `translate`) animieren. Keine
   Keyframes auf `width`, `height`, `top`, Farben in Endlosschleifen.
2. Hero-Einstieg rein per CSS-Keyframes mit festen Startzeiten, ohne auf
   JavaScript zu warten. Start bei `opacity:.01` statt 0, damit die Überschrift
   sofort als größtes sichtbares Element zählt (LCP).
3. `animation-fill-mode: both`, Endwert identisch mit dem Ruhezustand.
4. Animationen sind nur aktiv, wenn `<html class="js">` gesetzt ist. Das setzt
   ein Inline-Skript im `<head>`, und zwar *nicht* bei „Bewegung reduzieren“.
5. Alles, was beim Scrollen erscheint, braucht einen **Nachlauf** (sammelt
   übersprungene Elemente beim schnellen Wischen ein) und einen **Failsafe**
   (nach 1,6 s: steht ein verstecktes Element sichtbar im Bild, schalte alle
   frei). Sonst bleiben in In-App-Browsern ganze Abschnitte unsichtbar.
6. Hover nur in `@media (hover:hover)`. Auf Touch statt Hover: Eindrücken
   (`scale(.982)` bei Karten, `.97` bei Buttons), wie native iOS-Zellen.
7. Kein dauerhaftes `will-change` auf vielen Elementen.
8. Animationen, die außerhalb des Bildes liegen, pausieren
   (`animation-play-state`), bis ihr Block sichtbar wird.

**Hero-Choreografie (Sekunden ab dem ersten Bild):**

| Zeit | Ereignis | Bewegung |
| --- | --- | --- |
| 0,05 / 0,13 | H1 Zeile 1 / Zeile 2 | 22 px hoch, .95 s, ease-apple |
| 0,18 | Beweis-Objekt | 40 px hoch, scale .97 → 1, 1,1 s; danach Schweben ±10 px, 8 s |
| 0,24 / 0,33 / 0,42 / 0,5 | Lead / Buttons / Personenzeile / Live-Pille | wie H1 |
| 0,35 | Bildmarke zeichnet sich | 2,2 s, danach Füllung 5 % |
| 1,3 | Puls-Ringe um den Pin | `scale(.12)` → 1, 4,5 s, drei versetzt |
| 1,55 | Aufstieg in der Liste | .95 s, Plätze tauschen bei 1,85 / 1,95 |
| 2,35 | Ergebnis-Badge | `scale(.85)` → 1, .5 s |
| 2,75 / 3,5 | Mitteilung 1 / 2 | 12 px von oben, `scale(.96)`, .75 s |

**Weitere Effekte mit Werten:**

- Scroll-Einblenden: 34 px, .9 s ease-out, Staffel 80/160/240 ms,
  Beobachter `threshold .14`, `rootMargin 0 0 -50px 0`.
- Linien-Symbole zeichnen sich beim ersten Sichtkontakt selbst
  (`stroke-dasharray`, .62 s, 55 ms je Pfad) und erneut beim Überfahren ihres
  Links oder ihrer Kachel.
- Maus-Parallax im Hero: Lichtfleck A 18 px mit, B 12 px gegen die Maus;
  Beweis-Objekt neigt ±5° (`perspective:1300px`).
- Zahlen zählen in 1,4 s hoch (easeOutCubic); die Breite des Endwerts wird
  vorher festgehalten, damit nichts verrutscht.
- FAQ öffnet über `grid-template-rows: 0fr → 1fr` (.45 s), ohne Höhe zu messen.
- Vorher/Nachher-Griff schwingt beim ersten Sichtkontakt einmal gedämpft aus
  (±16 %, 1,7 s), damit klar ist, dass man ziehen kann.
- Arbeitsprobe im Browserfenster: Beim Überfahren fährt der Ausschnitt in 9 s
  die ganze Seite ab.
- Startscreen: Schleier in exakt der Seitenfarbe vergeht in .52 s, Elemente
  kommen aus 14 px und `scale(.985)`, Staffel 70 ms, nach 1,1 s steht alles.
  Schließen: Fläche gleitet 18 px nach oben weg.

## 11 Telefon (bis 640 px): eigene Komposition, kein geschrumpfter Desktop

- Leiste 8 px vom Rand, 56 px hoch, Radius 18. Bereichs-Umschalter nur im Menü.
- Hero: zwei kurze Zeilen, ein Satz, Buttons volle Breite untereinander,
  Beweis-Objekt unter dem Text, Mitteilungen ragen 4 px über die Ränder,
  Hintergrund-Bildmarke ausgeblendet. Auf Tablet und Telefon startet die
  Hero-Animation erst, wenn die Karte ins Bild kommt.
- Chips ohne Pillen, Kachelraster als iOS-Liste, Kartenreihen als
  **Wisch-Galerie** (84 % Kartenbreite, Einrasten, nächste Karte lugt herein,
  Punkte darunter), Vergleiche per Segment-Umschalter, Zahlen als Zeilen,
  Porträt quadratisch, Schlusssatz linksbündig.
- Tippflächen mindestens 44 px, auch im Footer.
- Mobile Aktionsleiste unten (Milchglas, Radius 24, `safe-area-inset-bottom`).
- Kein Element läuft über den Rand: `overflow-x:hidden` am `body` ist nur das
  Netz, nicht die Lösung.

## 12 Barrierefreiheit, Leistung, Datenschutz

- Skip-Link „Zum Inhalt springen“, sichtbarer Fokus (`2px solid`, Abstand 3 px),
  `aria-expanded` an Menü und FAQ, `aria-current="page"` in der Navigation,
  Regler mit `role="slider"` und Pfeiltasten, dekorative Grafik
  `aria-hidden="true"`, Inhaltsbilder mit beschreibendem Alt-Text.
- Kontrast: Fließtext mindestens 4,5:1, auch Grau auf Grau. Grautext auf Dunkel
  über Deckkraft (`rgba(232,235,248,.58 bis .76)`).
- `prefers-reduced-motion`: keine Animation, alle Endzustände fest gesetzt.
- Druck: nichts wartet auf Animation, Leisten ausgeblendet.
- Ziel mobil: Lighthouse Leistung ab 90, LCP unter 2,5 s, CLS unter 0,05.
- Bilder als WebP/AVIF mit Rückfall, `width`/`height` gesetzt, `loading="lazy"`
  außer im Hero, `decoding="async"`.
- Schriften lokal, keine Fremd-CDNs, keine Tracker ohne Einwilligung. Formulare
  über eigenen Endpunkt mit Honeypot.

## 13 Texte und Tonalität

- Kurze Sätze, Aussage zuerst. Überschriften als zwei Sätze: „Aussage.
  Nachsatz.“ Beispiele: „Vier Schritte. Ein Ansprechpartner.“, „Echte
  Ergebnisse. Nachprüfbar bei Google.“, „Für Ihre Branche. Nicht für
  irgendeine.“
- Hero-H1: zwei Zeilen zu je zwei bis drei Wörtern und höchstens rund 16
  Zeichen, Ergebnis plus Bezug („Oben bei Google. In Ihrer Stadt.“).
- Lead: ein Satz nach dem Muster „Ich [tue X], damit [Kunde Y], nicht [Z].“
- Buttons: Nutzen statt Floskel („Kostenloses Erstgespräch“, „Profil jetzt
  prüfen“). „Mehr erfahren“ nie als Hauptknopf.
- Ich-Form bei Ein-Personen-Betrieben, Wir nur bei echtem Team.
- **Keine Gedankenstriche** (— und –, auch nicht als `&mdash;`/`&ndash;`).
  Stattdessen Komma, Punkt, Doppelpunkt oder Klammern. Bereiche mit „bis“
  („3 bis 5 Tipps“, „10:00 bis 10:30 Uhr“). Titel mit Doppelpunkt, Marke nach
  „|“ („Kontakt | Firma“). Deutsche Anführungszeichen „ “.
- Keine Garantien und keine Superlative ohne Beleg (UWG). Zahlen nur mit Quelle
  und Jahr, nicht älter als drei Jahre. Grafiken mit Beispieldaten tragen
  „Beispieldarstellung“.

## 14 Technik und Ausgabe

- Standard (wenn im Profil nichts anderes steht): **Vite**, statisches HTML je
  Seite, wiederkehrende Teile (Head, Navigation, Footer) als Partials, die der
  Build einsetzt. **Eine** CSS-Datei, gegliedert in nummerierte Abschnitte
  (1 Schriften, Tokens, Grundlagen · 2 Layout, Flächen, Überschriften, Buttons ·
  3 Navigation · 4 Hero · 5 Abschnitte · 6 Footer, Leisten · 7 Unterseiten ·
  8 Sonderbereiche · 9 Overlays · 10 Bewegung, reduzierte Bewegung, Druck).
  Vanilla JavaScript in kleinen, in sich geschlossenen Modulen. Keine
  Bibliotheken für Effekte.
- Kommentare erklären das *Warum* (z. B. „deckend statt Milchglas, weil die
  Fläche über bewegtem Hintergrund liegt“), nicht das Was.
- SEO: Titel „Seite | Marke“, Beschreibung 140 bis 160 Zeichen, genau eine H1
  je Seite, JSON-LD (passender LocalBusiness-Untertyp, Breadcrumbs, FAQ),
  Open-Graph-Bild 1200 × 630, `sitemap.xml`, `robots.txt`, kanonische URLs.
- Ausgabe: zuerst der Dateibaum, dann jede Datei vollständig. Keine
  Auslassungen wie „restlicher Code wie oben“.

## 15 Abnahme-Checkliste

- [ ] Eine Handlungsfarbe; Lichtakzent an höchstens drei Arten von Stellen
- [ ] Alle Überschriften einfarbig; zweiteilige Überschriften mit grauem Nachsatz
- [ ] Hero-H1 höchstens zwei Zeilen; bei 390 px Breite jede Zeile ganz, rechts
      sichtbarer Rand
- [ ] Flächen wechseln (hell, grau, dunkel); Sheets ohne Lücken in den Ecken
- [ ] Beweis-Objekt passt zur Branche, trägt „Beispieldarstellung“
- [ ] iOS-Mitteilungen an drei bis fünf Stellen, nicht mehr
- [ ] Animationen nur `opacity`/`transform`; kein `backdrop-filter` auf
      bewegten Flächen; kein `filter:blur` auf großen Flächen
- [ ] Ohne JavaScript ist alles sichtbar
- [ ] „Bewegung reduzieren“: keine Bewegung, Endzustände korrekt
- [ ] Schnelles Durchwischen: kein Abschnitt bleibt unsichtbar
- [ ] Hover nur mit Zeiger; auf Touch Eindrück-Rückmeldung
- [ ] Tippflächen mindestens 44 px
- [ ] Aktionsleiste erscheint nach dem Hero, verschwindet bei Kontakt und Footer
- [ ] Kontraste geprüft und ausgegeben
- [ ] Fokus sichtbar, Skip-Link vorhanden, ARIA an Menü, FAQ, Regler
- [ ] Lighthouse mobil: Leistung ab 90, CLS unter 0,05
- [ ] Schriften lokal, keine Fremd-CDNs
- [ ] Keine erfundenen Zahlen, Bewertungen, Zitate
- [ ] Keine Gedankenstriche in sichtbaren Texten, Meta-Angaben, alt-Texten,
      JSON-LD (Suche nach `—`, `–`, `&mdash;`, `&ndash;`)
- [ ] Je Seite genau eine H1, Titel „Seite | Marke“, JSON-LD valide
- [ ] Drucken: nichts versteckt, Leisten ausgeblendet

# Teil B: Code-Referenz

Erprobte Muster aus jungline.de, bereinigt und verallgemeinert. Werte und
Prinzipien übernehmen, Inhalte ersetzen, Klassennamen dürfen bleiben.
Platzhalter stehen in `[ECKIGEN KLAMMERN]`. Pfeil-Symbol, das an vielen
Stellen vorkommt (im Folgenden `[PFEIL]`):

```html
<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
```

Häkchen (`[HAKEN]`):

```html
<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
```

Alle Linien-Symbole: `viewBox="0 0 24 24"`, `stroke-width` 1.8 bis 2.4,
runde Enden. Nur dann greift die Zeichen-Animation aus B27.

## B0 Head: Schalter für Animationen (inline, vor dem Stylesheet)

```html
<script>
/* .js schaltet die Einstiegs-Animationen ein, aber nicht bei "Bewegung
   reduzieren". Auf Tablet und Telefon liegt die Hero-Karte unter dem ersten
   Bildschirm: Ihre Szene wartet (.hero-wait), bis sie ins Bild kommt. Steht
   inline im Head, damit die Pause schon ab dem ersten Bild gilt. */
(function(d){var r=d.documentElement;if(matchMedia('(prefers-reduced-motion: reduce)').matches)return;r.classList.add('js');
if(!matchMedia('(max-width:960px)').matches||!('IntersectionObserver' in window))return;r.classList.add('hero-wait');
d.addEventListener('DOMContentLoaded',function(){var v=d.querySelector('.hero__visual');if(!v){r.classList.remove('hero-wait');return;}
var io=new IntersectionObserver(function(e){if(e[0].isIntersecting){v.classList.add('hero-in-view');io.disconnect();}},{threshold:.3});io.observe(v);});})(document);
</script>
<meta name="theme-color" content="#0A0D1F">
<link rel="preload" as="font" type="font/woff2" href="/fonts/instrument-sans-var.woff2" crossorigin fetchpriority="high">
```

## B1 Schriften, Tokens, Themen, Grundlagen

```css
/* ============ 1  SCHRIFTEN ============
   Apple-Geräte rendern in SF Pro (Systemschrift, kein Download). Alle
   anderen bekommen Instrument Sans, selbst gehostet. Die Ersatzschrift ist
   auf deren Maße eingestellt, damit der Wechsel nichts verschiebt. */
@font-face{
  font-family:'Instrument Sans';
  src:url('/fonts/instrument-sans-var.woff2') format('woff2-variations');
  font-weight:400 700;
  font-display:swap;
}
@font-face{
  font-family:'Instrument Fallback';
  src:local('Arial'),local('ArialMT'),local('Helvetica Neue'),local('HelveticaNeue'),local('Liberation Sans'),local('Roboto');
  size-adjust:102.3%;ascent-override:94.8%;descent-override:24.4%;line-gap-override:0%;
}

/* ============ TOKENS ============ */
:root{
  --font-display:-apple-system,BlinkMacSystemFont,'SF Pro Display','Instrument Sans','Instrument Fallback',system-ui,sans-serif;
  --font-text:-apple-system,BlinkMacSystemFont,'SF Pro Text','Instrument Sans','Instrument Fallback',system-ui,sans-serif;
  --font-sys:-apple-system,BlinkMacSystemFont,'SF Pro Text','Helvetica Neue',Helvetica,Arial,sans-serif;

  /* Marke: ANPASSEN (Regeln in Teil A, Abschnitt 4) */
  --brand:#3D50C8;
  --brand-rgb:61,80,200;
  --brand-hover:#3142AD;
  --brand-on-dark:#AEBBFF;
  --brand-on-dark-rgb:174,187,255;
  --navy-900:#0A0D1F;
  --navy-900-rgb:10,13,31;
  --navy-800:#11152D;
  --spark:#6FE8BD;
  --spark-rgb:111,232,189;

  /* Flächen hell */
  --bg:#FFFFFF;
  --bg-rgb:255,255,255;
  --bg-soft:#F5F5F7;
  --surface:#FFFFFF;
  --surface-2:#F2F2F5;
  --border:#E3E3E8;
  --border-strong:#C9C9CF;

  /* Text: die Grauwerte von apple.com */
  --ink:#1D1D1F;
  --dim:#4B4B50;
  --dim-2:#6E6E73;
  --soft:#86868B;
  --link:var(--brand);

  /* Maße */
  --maxw:1200px;
  --gutter:clamp(20px,3.4vw,32px);
  --nav-top:12px;              /* Abstand der schwebenden Leiste nach oben */
  --nav-bar:60px;              /* Höhe der Leiste */
  --nav-h:calc(var(--nav-top) + var(--nav-bar));
  --r:18px;
  --r-lg:26px;
  --r-xl:34px;
  --sheet-r:clamp(26px,4vw,48px);
  --sp:clamp(76px,9vw,140px);
  --sp-head:clamp(36px,4.4vw,60px);

  /* Schriftgrade */
  --fs-hero:clamp(2.8rem,6vw,5rem);
  --fs-h2:clamp(2rem,4.2vw,3.5rem);
  --fs-h3:clamp(1.2rem,1.6vw,1.42rem);
  --fs-lead:clamp(1.12rem,1.45vw,1.32rem);
  --fs-sub:clamp(1.1rem,1.5vw,1.3rem);

  /* Bewegung */
  --ease:cubic-bezier(.2,.7,.2,1);
  --ease-out:cubic-bezier(.16,1,.3,1);
  --ease-apple:cubic-bezier(.32,.72,0,1);
  --dur-tap:.16s;
  --dur-ui:.28s;
  --dur-move:.55s;

  /* Schatten: mehrlagig, weich, im Bühnenton statt in Schwarz */
  --elev-1:0 1px 2px rgba(14,17,34,.05),0 6px 16px -8px rgba(14,17,34,.12);
  --elev-2:0 2px 6px rgba(14,17,34,.06),0 18px 40px -18px rgba(14,17,34,.22);
  --elev-3:0 4px 10px rgba(14,17,34,.06),0 40px 80px -30px rgba(14,17,34,.32);

  /* Ebenen */
  --z-mcta:90;
  --z-menu:95;
  --z-nav:100;
  --z-chooser:200;
}

/* Zweiter Angebotsbereich (optional): dieselbe Seite, andere Marke.
   Ein Token-Wechsel färbt alles um, ohne eine einzige Komponentenregel. */
body[data-bereich="b"]{
  --brand:#0071E3;--brand-rgb:0,113,227;--brand-hover:#0062C4;
  --brand-on-dark:#6BB6FF;--brand-on-dark-rgb:107,182,255;--link:#0066CC;
}

/* Dunkle Flächen: dieselben Namen, dunkel belegt. Alles darin stellt sich
   automatisch um. */
.theme-dark{
  --bg:var(--navy-900);
  --bg-rgb:var(--navy-900-rgb);
  --bg-soft:var(--navy-800);
  --surface:rgba(255,255,255,.06);
  --surface-2:rgba(255,255,255,.09);
  --border:rgba(255,255,255,.11);
  --border-strong:rgba(255,255,255,.2);
  --ink:#F4F5FB;
  --dim:rgba(232,235,248,.76);
  --dim-2:rgba(232,235,248,.58);
  --soft:rgba(232,235,248,.5);
  --link:var(--brand-on-dark);
  background:var(--bg);
  color:var(--ink);
}
/* Helle Inseln auf dunkler Fläche holen die hellen Werte zurück. */
.theme-light{
  --bg:#FFFFFF;--bg-rgb:255,255,255;--bg-soft:#F5F5F7;
  --surface:#FFFFFF;--surface-2:#F1F2F6;
  --border:#E3E3E8;--border-strong:#C9C9CF;
  --ink:#1D1D1F;--dim:#4B4B50;--dim-2:#6E6E73;--soft:#86868B;
  --link:var(--brand);
  color:var(--ink);
}

/* ============ GRUNDLAGEN ============ */
*{box-sizing:border-box;margin:0;padding:0}
html{-webkit-text-size-adjust:100%;-webkit-tap-highlight-color:transparent}
body{
  background:var(--bg);color:var(--ink);
  font-family:var(--font-text);font-size:1.0625rem;font-weight:400;
  line-height:1.55;letter-spacing:-.011em;
  overflow-x:hidden;
  -webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;
  text-rendering:optimizeLegibility;
}
h1,h2,h3,h4{
  font-family:var(--font-display);font-weight:700;
  line-height:1.07;letter-spacing:-.022em;text-wrap:balance;
  overflow-wrap:break-word;   /* Netz für Wörter, die breiter als die Spalte sind */
}
p,li,blockquote{text-wrap:pretty}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
button{font:inherit;color:inherit}
::selection{background:rgba(var(--brand-rgb),.22);color:var(--ink)}
.theme-dark ::selection{background:rgba(var(--spark-rgb),.3);color:#fff}
section,[id]{scroll-margin-top:calc(var(--nav-h) + 16px)}
:focus-visible{outline:2px solid var(--link);outline-offset:3px;border-radius:6px}

/* Nur für Screenreader */
.u-sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;
  clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap;border:0}
/* Kurzfassung eines Textes nur auf dem Telefon (display, damit die jeweils
   andere Fassung auch aus dem Accessibility-Tree fällt). */
.m-only{display:none}
@media(max-width:640px){.m-hide{display:none}.m-only{display:inline}}
```

## B2 Layout, Sheets, Überschriften-Block

```css
.container{max-width:var(--maxw);margin:0 auto;padding:0 var(--gutter)}
.section{position:relative;padding:var(--sp) 0}
.section--tight{padding:calc(var(--sp)*.56) 0}
.section--soft{background:var(--bg-soft)}
.section.theme-dark{background:var(--bg)}

/* Sheet: Die Fläche schiebt sich mit runder Oberkante über die vorige. Der
   negative Abstand entspricht genau dem Radius: Die vorige Fläche füllt die
   Ecken, es entsteht keine Lücke. */
.sheet{
  position:relative;z-index:2;
  margin-top:calc(var(--sheet-r) * -1);
  border-radius:var(--sheet-r) var(--sheet-r) 0 0;
  background:var(--bg);
}
.sheet.section--soft{background:var(--bg-soft)}
.sheet + .sheet{z-index:3}
.sheet + .sheet + .sheet{z-index:4}
/* Telefon: zwei weiße Abschnitte ohne Kante hintereinander hätten doppelte
   Luft (rund 150 px leeres Weiß). Einmal Luft reicht. */
@media(max-width:640px){
  .section.sheet:not(.section--soft):not(.theme-dark) + .section:not(.sheet):not(.section--soft):not(.theme-dark){padding-top:calc(var(--sp) * .2)}
}

/* Überschriften-Block */
.head-block{max-width:780px}
.head-block h2{font-size:var(--fs-h2);color:var(--ink)}
.head-block p{margin-top:16px;max-width:52ch;color:var(--dim-2);font-size:var(--fs-sub);line-height:1.45;letter-spacing:-.014em}
.head-block.center{max-width:860px;margin-left:auto;margin-right:auto;text-align:center}
.head-block.center p{margin-left:auto;margin-right:auto}
.head-block.center h2 .soft{display:block}
.head-block .link-arrow{margin-top:22px}
@media(min-width:900px){
  .head-block--split{max-width:none;display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);gap:24px 72px;align-items:end}
  .head-block--split p{margin-top:0;padding-bottom:.35em}
}
/* Zweifarbige Überschrift: der zweite Satz tritt in Grau zurück. */
.soft{color:var(--soft)}
.lead{font-size:var(--fs-lead);line-height:1.5;color:var(--dim)}
/* Kennzeile über Überschriften: nur wo sie Orientierung gibt, ohne Symbol,
   NICHT in Großbuchstaben. */
.eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:.95rem;font-weight:600;letter-spacing:-.005em;color:var(--link)}
```

```html
<section class="section section--soft sheet" id="[id]">
  <div class="container">
    <div class="head-block center" data-reveal>
      <h2>[Aussage.] <span class="soft">[Nachsatz.]</span></h2>
      <p>[Ein Satz, der den Abschnitt erklärt.]</p>
    </div>
    <!-- Inhalt -->
  </div>
</section>
```

## B3 Buttons, Textlink, Magnet

```css
.cta-row{display:flex;flex-wrap:wrap;align-items:center;gap:12px}
.btn{
  position:relative;overflow:hidden;isolation:isolate;
  display:inline-flex;align-items:center;justify-content:center;gap:9px;
  font-family:var(--font-text);font-weight:600;font-size:1rem;letter-spacing:-.012em;line-height:1.2;
  padding:14px 24px;border-radius:999px;border:1px solid transparent;cursor:pointer;white-space:nowrap;
  transition:background-color var(--dur-ui),color var(--dur-ui),border-color var(--dur-ui),
             box-shadow .35s var(--ease),transform .35s var(--ease-out);
}
.btn svg{flex:none}
.btn--lg{padding:16px 28px;font-size:1.0625rem}
.btn--primary{
  background:var(--brand);color:#fff;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.2),0 10px 24px -12px rgba(var(--brand-rgb),.75);
}
/* Lichtkante, die beim Überfahren EINMAL durch den Knopf zieht. Die
   Transition sitzt nur am Hover-Zustand: Beim Verlassen springt die Kante
   unsichtbar zurück, statt rückwärts zu laufen. */
.btn--primary::after{content:"";position:absolute;inset:0;z-index:-1;
  background:linear-gradient(115deg,transparent 30%,rgba(255,255,255,.34) 48%,transparent 64%);
  transform:translateX(-130%)}
.btn--primary:hover{background:var(--brand-hover);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.22),0 16px 34px -14px rgba(var(--brand-rgb),.85)}
.btn--primary:hover::after{transform:translateX(130%);transition:transform .75s var(--ease)}
.btn--ghost{background:transparent;color:var(--ink);border-color:var(--border-strong)}
.btn--ghost:hover{border-color:var(--ink)}
/* Für dunkle Flächen: helle Glasfläche OHNE backdrop-filter. Die Knöpfe
   liegen über bewegtem Hintergrund, Unschärfe müsste in jedem Bild neu
   gerechnet werden; auf Dunkel sieht man den Unterschied nicht. */
.btn--glass{background:rgba(255,255,255,.1);color:#fff;border-color:rgba(255,255,255,.18)}
.btn--glass:hover{background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.3)}
.btn:disabled{opacity:.6;cursor:default;transform:none!important}
@media(hover:hover){
  .btn--primary:hover,.btn--ghost:hover,.btn--glass:hover{transform:translateY(-1px)}
}
.btn:active:not(:disabled){transform:scale(.97);transition-duration:var(--dur-tap)}

/* Textlink mit Pfeil: die ruhige Alternative zum zweiten Button. */
.link-arrow{display:inline-flex;align-items:center;gap:7px;color:var(--link);font-weight:600;letter-spacing:-.01em;
  transition:gap var(--dur-ui) var(--ease-apple),opacity var(--dur-ui)}
.link-arrow svg{flex:none;transition:transform var(--dur-ui) var(--ease-apple)}
@media(hover:hover){.link-arrow:hover svg{transform:translateX(3px)}}
a.link-arrow[target="_blank"]:hover svg{transform:translate(2px,-2px)}
```

```js
/* Magnetische Hauptknöpfe: Der Knopf folgt der Maus ein kleines Stück, per
   Lerp weich nachgeführt. Geschrieben wird die Eigenschaft translate, NICHT
   transform: So bleiben Anheben (:hover) und Eindrücken (:active) aus dem CSS
   erhalten, und es gibt keine doppelte Glättung durch die CSS-Transition.
   Der Knopf in der Navigationsleiste ist ausgenommen (zu wenig Luft). */
(function(){
  const fein = matchMedia('(hover:hover) and (pointer:fine)').matches;
  const ruhig = matchMedia('(prefers-reduced-motion: reduce)').matches;
  if(!fein || ruhig) return;
  document.querySelectorAll('.btn--primary:not(.nav__cta-desktop)').forEach(btn => {
    let tx = 0, ty = 0, cx = 0, cy = 0, laeuft = false;
    const schritt = () => {
      cx += (tx - cx) * 0.16; cy += (ty - cy) * 0.16;
      if(Math.abs(tx - cx) > 0.05 || Math.abs(ty - cy) > 0.05) requestAnimationFrame(schritt);
      else { cx = tx; cy = ty; laeuft = false; }
      btn.style.translate = (cx || cy) ? cx.toFixed(2) + 'px ' + cy.toFixed(2) + 'px' : '';
    };
    const start = () => { if(!laeuft){ laeuft = true; requestAnimationFrame(schritt); } };
    btn.addEventListener('mousemove', e => {
      if(btn.disabled) return;
      const r = btn.getBoundingClientRect();   // enthält translate schon, daher -cx/-cy
      tx = (e.clientX - (r.left - cx + r.width / 2)) * 0.1;
      ty = (e.clientY - (r.top - cy + r.height / 2)) * 0.13;
      start();
    }, {passive:true});
    btn.addEventListener('mouseleave', () => { tx = 0; ty = 0; start(); });
  });
})();
```

## B4 Badges und Pillen

```css
/* Live-Pille über der Hero-Überschrift: nächster freier Termin oder feste
   Zusage. Kein Milchglas (liegt über bewegtem Hintergrund). */
.hero__live{display:inline-flex;align-items:center;gap:10px;max-width:100%;
  padding:8px 14px 8px 12px;border-radius:999px;
  background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.13);color:rgba(255,255,255,.9);
  font-size:.92rem;font-weight:500;letter-spacing:-.01em;
  transition:background var(--dur-ui),border-color var(--dur-ui)}
.hero__live:hover{background:rgba(255,255,255,.13);border-color:rgba(255,255,255,.22)}
.hero__live svg{flex:none;opacity:.7;transition:transform var(--dur-ui) var(--ease-apple)}
.hero__live:hover svg{transform:translateX(3px)}
.hero__live-tx{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hero__live-tx b{font-weight:650;color:#fff}
.hero__live-dot{flex:none;width:8px;height:8px;border-radius:50%;background:var(--spark);
  box-shadow:0 0 0 3px rgba(var(--spark-rgb),.18)}

/* "Neu"-Badge: führt zu einer Arbeitsprobe oder Neuigkeit. */
.new-badge{display:inline-flex;align-items:center;gap:10px;max-width:100%;padding:6px 14px 6px 6px;border-radius:999px;
  background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);color:rgba(255,255,255,.92);
  font-size:.92rem;font-weight:500;letter-spacing:-.01em;transition:background var(--dur-ui),border-color var(--dur-ui)}
.new-badge:hover{background:rgba(255,255,255,.12);border-color:rgba(255,255,255,.24)}
.new-badge__tag{flex:none;padding:4px 10px;border-radius:999px;background:var(--brand);color:#fff;font-size:.78rem;font-weight:700;letter-spacing:.01em}
.new-badge__tx{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.new-badge svg{flex:none;opacity:.7;transition:transform var(--dur-ui) var(--ease-apple)}
.new-badge:hover svg{transform:translateX(3px)}

/* Ergebnis-Badge (gefüllt), Status-Chip, Warn-Chip, Rang-Quadrat, Sterne */
.climb-badge{display:flex;align-items:center;gap:5px;flex:none;background:var(--brand);color:#fff;
  font-size:.74rem;font-weight:650;padding:5px 10px;border-radius:999px;white-space:nowrap}
.climb-badge svg{width:1em;height:1em;flex:none}
.openchip{background:#DCF2E3;color:#12633A;border-radius:999px;padding:1px 8px;font-size:.7rem;font-weight:600;white-space:nowrap}
.chip-top{background:rgba(var(--brand-rgb),.1);color:var(--brand);font-size:.74rem;font-weight:650;padding:4px 10px;border-radius:999px}
.chip-warn{background:rgba(214,64,48,.09);color:#A03024;font-size:.74rem;font-weight:650;padding:4px 10px;border-radius:999px}
.rank{flex:none;display:grid;place-items:center;min-width:26px;height:26px;padding:0 6px;border-radius:8px;
  font-weight:600;font-size:.84rem;background:#F0F1F5;color:#5F6378}
.rank--you{background:var(--brand);color:#fff;font-weight:700}
.stars{letter-spacing:1px;color:#FBBC04;font-size:.78rem}

/* Markenkennung als Pille auf hellem Grund (z. B. "Seit 2012 in Dorsten") */
.tag{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:999px;
  background:rgba(var(--brand-rgb),.07);border:1px solid rgba(var(--brand-rgb),.16);color:var(--brand);font-size:.88rem;font-weight:550}
```

```html
<a class="hero__live" href="#termin">
  <span class="hero__live-dot" aria-hidden="true"></span>
  <span class="hero__live-tx">[Erstgespräch: 30 Minuten, kostenlos]</span>
  [PFEIL]
</a>

<a class="new-badge" href="#referenz">
  <span class="new-badge__tag">Neu</span>
  <span class="new-badge__tx">[Arbeitsprobe: Firmenname]</span>
  [PFEIL]
</a>
```

## B5 iOS-Mitteilung (das Wiedererkennungsstück)

```css
/* Eine Mitteilung wie auf dem Sperrbildschirm des iPhones: App-Symbol als
   Squircle, fette Zeile mit Zeitstempel, darunter eine Zeile Text.
   Deckend statt Milchglas, weil sie teils über bewegten Flächen liegt.
     .inote          hell, schwebend
     .inote--flat    hell, ruhig (in Karten)
     .inote--dark    für dunkle Flächen
     .inote--stack   zwei weitere Mitteilungen dahinter angedeutet */
.inote{display:flex;align-items:center;gap:12px;padding:11px 16px 11px 11px;border-radius:22px;
  font-family:var(--font-text);color:#000;text-align:left;letter-spacing:-.012em;
  background:rgba(250,250,252,.96);
  box-shadow:0 0 0 .5px rgba(0,0,0,.06),0 24px 48px -18px rgba(0,0,0,.5)}
.inote__ic{flex:none;width:38px;height:38px;border-radius:9.5px;display:flex;align-items:center;justify-content:center;color:#fff;
  box-shadow:inset 0 0 0 .5px rgba(0,0,0,.1),inset 0 1px 0 rgba(255,255,255,.28)}
.inote__ic--phone{background:linear-gradient(180deg,#65F081,#20BF3E)}
.inote__ic--star{background:linear-gradient(180deg,#FFD866,#FF9F0A)}
.inote__ic--maps{background:linear-gradient(180deg,color-mix(in srgb,var(--brand) 60%,#fff),var(--brand))}
.inote__ic--ok{background:linear-gradient(180deg,#52E07C,#1FA64A)}
.inote__ic--warn{background:linear-gradient(180deg,#FF7A70,#F2362B)}
.inote__ic--cal{background:#fff;color:#F2362B;box-shadow:inset 0 0 0 1px rgba(0,0,0,.08)}
.inote__tx{flex:1;min-width:0;display:flex;flex-direction:column;gap:1px;line-height:1.28}
.inote__top{display:flex;align-items:baseline;justify-content:space-between;gap:10px;min-width:0}
.inote__top b{min-width:0;font-size:.94rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.inote__time{flex:none;font-size:.78rem;color:rgba(60,60,67,.6);white-space:nowrap}
.inote small{font-size:.9rem;color:rgba(60,60,67,.86);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.inote--flat{background:#fff;box-shadow:0 0 0 1px rgba(0,0,0,.05),0 10px 28px -18px rgba(0,0,0,.3)}
.inote--flat small{white-space:normal}
.inote--dark{color:#fff;background:rgba(255,255,255,.075);box-shadow:inset 0 0 0 1px rgba(255,255,255,.1)}
.inote--dark .inote__time{color:rgba(235,235,245,.48)}
.inote--dark small{color:rgba(235,235,245,.66)}
.inote--stack{box-shadow:0 0 0 .5px rgba(0,0,0,.06),
  0 13px 0 -6px rgba(236,236,241,.78),0 25px 0 -12px rgba(222,222,230,.5),
  0 34px 50px -18px rgba(0,0,0,.55)}
```

```html
<div class="inote">
  <span class="inote__ic inote__ic--phone" aria-hidden="true">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 16.4v3.2a1.9 1.9 0 0 1-2.1 1.9 18.9 18.9 0 0 1-8.2-2.9 18.6 18.6 0 0 1-5.7-5.7A18.9 18.9 0 0 1 1.6 4.6 1.9 1.9 0 0 1 3.5 2.5h3.2a1.9 1.9 0 0 1 1.9 1.6c.1.9.4 1.8.7 2.6a1.9 1.9 0 0 1-.4 2L7.5 10a15.2 15.2 0 0 0 5.7 5.7l1.3-1.4a1.9 1.9 0 0 1 2-.4c.8.3 1.7.6 2.6.7a1.9 1.9 0 0 1 1.4 1.8z"/></svg>
  </span>
  <span class="inote__tx">
    <span class="inote__top"><b>[Anruf über Google Maps]</b><span class="inote__time">jetzt</span></span>
    <small>[Neuer Kunde aus Ihrer Stadt]</small>
  </span>
</div>
```

Stern-Symbol für `.inote__ic--star`:
`<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.6l2.8 5.9 6.4.8-4.7 4.5 1.2 6.4L12 17.1l-5.7 3.1 1.2-6.4-4.7-4.5 6.4-.8z"/></svg>`

## B6 Häkchen-Chips

```css
.chips{list-style:none;display:flex;flex-wrap:wrap;gap:8px;margin:0}
.chips li{display:inline-flex;align-items:center;gap:7px;padding:8px 14px 8px 11px;border-radius:999px;
  font-size:.92rem;font-weight:500;color:var(--ink);background:var(--surface-2);letter-spacing:-.012em}
.chips svg{flex:none;color:var(--brand)}
.section--soft .chips li{background:#fff}
.chips--stack{flex-direction:column;align-items:flex-start}
/* Telefon: ohne Pillen. Graue Kapseln untereinander wirken wie Baukasten;
   als ruhige Zeile mit Häkchen lesen sie sich als das, was sie sind. */
@media(max-width:640px){
  .chips{gap:8px 18px}
  .chips li,.section--soft .chips li{padding:0;border-radius:0;background:none;font-size:.95rem;color:var(--dim)}
  .chips svg{width:15px;height:15px}
  .chips--stack{gap:10px}
}
```

```html
<ul class="chips">
  <li>[HAKEN]Richtlinienkonform</li>
  <li>[HAKEN]Kein Knebelvertrag</li>
  <li>[HAKEN]Persönlich erreichbar</li>
</ul>
```

## B7 Navigation (schwebende Glasleiste, gleitende Pille, Telefon-Blatt)

```html
<a class="skip-link" href="#main">Zum Inhalt springen</a>
<nav class="nav" id="nav">
  <div class="container nav__wrap"><div class="nav__inner">
    <a href="/" class="brand" aria-label="[FIRMA], zur Startseite">
      <span class="brand__tile"><img src="/logo-mark.svg" alt="" width="21" height="22" decoding="async" fetchpriority="high"></span>
      <span class="brand__name">[Wort1] <b>[Wort2]</b></span>
    </a>
    <div class="nav__links">
      <a href="/leistungen/">Leistungen</a>
      <a href="/referenzen/">Referenzen</a>
      <a href="/ueber-uns/">Über uns</a>
      <a href="/kontakt/">Kontakt</a>
    </div>
    <div class="nav__right">
      <a href="/kontakt/#termin" class="btn btn--primary nav__cta-desktop">Termin buchen</a>
      <button class="nav__toggle" id="navToggle" aria-label="Menü öffnen" aria-expanded="false" aria-controls="mobileMenu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div></div>
</nav>
<!-- Telefon: ein Blatt über die volle Höhe. Oben die Wege durch die Seite,
     unten (wo der Daumen liegt) die Arten, Kontakt aufzunehmen. -->
<div class="mobile-menu" id="mobileMenu">
  <a href="/leistungen/">Leistungen</a>
  <a href="/referenzen/">Referenzen</a>
  <a href="/ueber-uns/">Über uns</a>
  <a href="/kontakt/">Kontakt</a>
  <div class="mobile-menu__foot">
    <a href="/kontakt/#termin" class="btn btn--primary btn--lg">Termin buchen</a>
    <div class="mobile-menu__quick">
      <a href="tel:[NUMMER]" class="mobile-menu__q">[TELEFON-SYMBOL]Anrufen</a>
      <a href="mailto:[MAIL]" class="mobile-menu__q">[MAIL-SYMBOL]E-Mail</a>
    </div>
    <p class="mobile-menu__fine">[Name] · [Ort] · <a href="/impressum/">Impressum</a></p>
  </div>
</div>
```

```css
.skip-link{position:fixed;top:-60px;left:12px;z-index:210;background:var(--brand);color:#fff;
  padding:12px 20px;border-radius:12px;font-weight:600;font-size:.94rem;transition:top .25s var(--ease-out)}
.skip-link:focus{top:12px}

/* Die Fläche um die Leiste lässt Klicks durch, nur die Leiste nimmt sie an.
   Über dem dunklen Kopf: dunkles Glas mit heller Schrift. Beim Scrollen:
   helles Milchglas mit kräftigem Blur und Sättigungs-Boost wie bei Apple.
   Seiten mit hellem Kopf setzen body[data-top="light"]. */
.nav{position:fixed;top:var(--nav-top);left:0;right:0;z-index:var(--z-nav);height:var(--nav-bar);
  color:#fff;pointer-events:none;transition:color .35s}
.nav__wrap{height:100%}
.nav__inner{pointer-events:auto;display:flex;align-items:center;justify-content:space-between;gap:20px;height:100%;
  padding:0 10px 0 12px;border-radius:22px;
  background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.13);
  -webkit-backdrop-filter:blur(22px) saturate(180%);backdrop-filter:blur(22px) saturate(180%);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.06),0 14px 34px -18px rgba(0,0,0,.55);
  transition:background-color .35s var(--ease),border-color .35s,box-shadow .35s}
.nav.scrolled,.nav.open,body[data-top="light"] .nav{color:var(--ink)}
.nav.scrolled .nav__inner,.nav.open .nav__inner,body[data-top="light"] .nav .nav__inner{
  background:rgba(255,255,255,.84);border-color:rgba(14,17,34,.07);
  box-shadow:0 1px 2px rgba(14,17,34,.05),0 16px 38px -18px rgba(14,17,34,.26)}
.nav.open .nav__inner{background:#fff}

.brand{display:flex;align-items:center;gap:10px;flex:none}
.brand__tile{width:36px;height:36px;border-radius:10px;flex:none;display:flex;align-items:center;justify-content:center;
  background:#fff;box-shadow:0 1px 2px rgba(14,17,34,.12),0 6px 14px -6px rgba(14,17,34,.35);
  transition:transform .45s var(--ease-out)}
.brand__tile img{width:21px;height:auto}
@media(hover:hover){.brand:hover .brand__tile{transform:rotate(-6deg) scale(1.05)}}
.brand__name{font-family:var(--font-display);font-weight:700;font-size:1.12rem;letter-spacing:-.025em;white-space:nowrap}
.brand__name b{font-weight:700;color:var(--brand-on-dark);transition:color .35s}
.nav.scrolled .brand__name b,.nav.open .brand__name b,body[data-top="light"] .nav .brand__name b{color:var(--brand)}

.nav__links{position:relative;display:flex;align-items:center;gap:2px}
.nav__links a{position:relative;z-index:1;display:inline-flex;align-items:center;height:40px;padding:0 16px;border-radius:999px;
  font-size:.93rem;font-weight:500;letter-spacing:-.01em;opacity:.8;
  transition:opacity .2s,color var(--dur-ui) var(--ease-apple)}
.nav__links a:hover{opacity:1}
/* Aktuelle Seite. Ohne Skript trägt der Eintrag die Pille selbst, mit Skript
   übernimmt das die gleitende Pille dahinter. */
.nav__links a[aria-current]{opacity:1;color:#fff;font-weight:600}
.nav__links:not(.has-glide) a[aria-current]{background:var(--brand)}
.nav__glide{position:absolute;top:50%;left:0;height:40px;margin-top:-20px;border-radius:999px;pointer-events:none;
  width:var(--w,0px);transform:translateX(var(--x,0px));opacity:0;
  transition:transform .5s var(--ease-apple),width .5s var(--ease-apple),opacity .25s,background-color .35s}
.nav__glide.is-on{opacity:1}
.nav__glide.no-anim{transition:none}
.nav__glide--hover{background:rgba(255,255,255,.1)}
.nav.scrolled .nav__glide--hover,body[data-top="light"] .nav .nav__glide--hover{background:rgba(14,17,34,.055)}
.nav__glide--active{background:var(--brand);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.22),0 8px 20px -10px rgba(var(--brand-rgb),.85)}
.nav__right{display:flex;align-items:center;gap:12px}
.nav__cta-desktop{height:42px;padding:0 20px;font-size:.92rem}
/* In der Leiste bleibt der Knopf stehen: knapp 10 px Luft, jede Bewegung
   wirkt dort wie ein Wackler. */
.nav__cta-desktop:hover{transform:none}

.nav__toggle{display:none;width:44px;height:44px;margin-right:-8px;background:none;border:0;cursor:pointer;color:inherit}
.nav__toggle span{display:block;width:20px;height:1.8px;margin:5px auto;border-radius:2px;background:currentColor;
  transition:transform .35s var(--ease),opacity .2s}
.nav.open .nav__toggle span:nth-child(1){transform:translateY(6.8px) rotate(45deg)}
.nav.open .nav__toggle span:nth-child(2){opacity:0}
.nav.open .nav__toggle span:nth-child(3){transform:translateY(-6.8px) rotate(-45deg)}

/* Menü auf dem Telefon: ein Blatt über die volle Höhe. Die Seite steht still
   (html.menu-open), unten in Daumenhöhe Termin, Anruf und E-Mail. */
.mobile-menu{position:fixed;inset:0;z-index:var(--z-menu);
  display:flex;flex-direction:column;overflow-y:auto;overscroll-behavior:contain;
  background:#fff;color:#0E1122;
  padding:calc(var(--nav-h) + 6px) var(--gutter) calc(20px + env(safe-area-inset-bottom,0px));
  visibility:hidden;transform:translateY(-10px);opacity:0;
  transition:opacity .3s var(--ease-apple),transform .4s var(--ease-apple),visibility 0s linear .4s}
.mobile-menu.show{visibility:visible;transform:none;opacity:1;transition-delay:0s}
.menu-open,.menu-open body{overflow:hidden}
.mobile-menu>a:not(.btn){display:flex;align-items:center;justify-content:space-between;flex:none;min-height:58px;
  font-family:var(--font-display);font-size:1.5rem;font-weight:700;letter-spacing:-.03em;
  border-bottom:1px solid rgba(14,17,34,.07);transition:color var(--dur-tap)}
.mobile-menu>a:not(.btn)::after{content:"";width:9px;height:9px;border-top:2px solid #B4B8C8;border-right:2px solid #B4B8C8;
  transform:rotate(45deg);margin-right:4px;transition:transform var(--dur-ui) var(--ease-apple)}
.mobile-menu>a:not(.btn):active{color:var(--brand)}
.mobile-menu>a:not(.btn):active::after{transform:translateX(3px) rotate(45deg)}
.mobile-menu>a.active{color:var(--brand)}
.mobile-menu .btn{display:flex;width:100%}
.mobile-menu__foot{margin-top:auto;padding-top:28px;display:flex;flex-direction:column;gap:10px}
.mobile-menu__quick{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.mobile-menu__q{display:flex;align-items:center;justify-content:center;gap:8px;min-height:50px;border-radius:999px;
  background:#F1F2F6;color:#0E1122;font-weight:600;font-size:.98rem;letter-spacing:-.012em;
  transition:background var(--dur-tap),transform var(--dur-tap) var(--ease-apple)}
.mobile-menu__q svg{color:var(--brand)}
.mobile-menu__q:active{background:#E6E8EF;transform:scale(.97)}
.mobile-menu__fine{margin-top:8px;text-align:center;font-size:.8rem;color:#8A8EA0}
.mobile-menu__fine a{color:inherit;text-decoration:underline;text-underline-offset:2px}
/* Einträge laufen gestaffelt ein (--i setzt das Skript). */
.js .mobile-menu>a:not(.btn),.js .mobile-menu__foot{opacity:0;transform:translateY(-6px)}
.js .mobile-menu.show>a:not(.btn),.js .mobile-menu.show .mobile-menu__foot{opacity:1;transform:none;
  transition:opacity var(--dur-ui) var(--ease-apple),transform var(--dur-ui) var(--ease-apple);
  transition-delay:calc(var(--i,0)*.035s + .06s)}
.js .mobile-menu.show .mobile-menu__foot{transition-delay:.26s}

@media(max-width:1060px){.nav__links a{padding:0 12px}}
@media(max-width:960px){
  .nav__links,.nav__cta-desktop{display:none}
  .nav__toggle{display:block}
}
@media(max-width:640px){
  :root{--nav-top:8px;--nav-bar:56px}
  .nav__wrap{padding:0 10px}
  .nav__inner{border-radius:18px;padding:0 6px 0 10px}
  .brand__tile{width:34px;height:34px}
}
@media(prefers-reduced-motion:reduce){.nav__glide{transition:opacity .2s}}
```

```js
/* Navigation: Glaszustand beim Scrollen, Farbe der Browserleiste,
   Telefon-Blatt, aktuelle Seite und gleitende Pillen. */
(function(){
  const nav = document.getElementById('nav');
  if(!nav) return;
  const toggle = document.getElementById('navToggle');
  const menu = document.getElementById('mobileMenu');

  // Die Browserleiste (Chrome Android, Safari) nimmt die Farbe der Leiste an.
  const meta = document.querySelector('meta[name="theme-color"]');
  let farbeJetzt = '';
  const syncTheme = () => {
    if(!meta) return;
    const hell = nav.classList.contains('scrolled') || nav.classList.contains('open') || document.body.dataset.top === 'light';
    const farbe = hell ? '#FFFFFF' : getComputedStyle(document.documentElement).getPropertyValue('--navy-900').trim();
    if(farbe !== farbeJetzt){ farbeJetzt = farbe; meta.setAttribute('content', farbe); }
  };
  const onScroll = () => { nav.classList.toggle('scrolled', scrollY > 24); syncTheme(); };
  onScroll();
  addEventListener('scroll', onScroll, {passive:true});

  // Telefon-Blatt
  if(toggle && menu){
    menu.querySelectorAll('a').forEach((a, i) => a.style.setProperty('--i', i));
    const setMenu = open => {
      nav.classList.toggle('open', open);
      menu.classList.toggle('show', open);
      document.documentElement.classList.toggle('menu-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
      syncTheme();
    };
    toggle.addEventListener('click', () => setMenu(!nav.classList.contains('open')));
    menu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setMenu(false)));
    addEventListener('keydown', e => { if(e.key === 'Escape' && nav.classList.contains('open')){ setMenu(false); toggle.focus(); } });
    // Wird das Tablet ins Querformat gedreht, verschwindet der Knopf: offenes
    // Blatt samt Scroll-Sperre bliebe sonst ohne Ausgang stehen.
    const breit = matchMedia('(min-width: 961px)');
    breit.addEventListener('change', () => { if(breit.matches) setMenu(false); });
  }

  // Aktuelle Seite und gleitende Pillen
  const links = nav.querySelector('.nav__links');
  if(!links) return;
  const eintraege = [...links.querySelectorAll('a')];
  const mobil = menu ? [...menu.querySelectorAll(':scope > a:not(.btn)')] : [];
  const pille = art => {
    const p = document.createElement('span');
    p.className = 'nav__glide nav__glide--' + art;
    p.setAttribute('aria-hidden', 'true');
    links.prepend(p);
    return p;
  };
  const aktivPille = pille('active');   // liegt über der blassen
  const hoverPille = pille('hover');
  links.classList.add('has-glide');

  const setze = (p, a, sofort) => {
    if(!a || !a.offsetWidth){ p.classList.remove('is-on'); return; }
    // Taucht die Pille neu auf, springt sie an ihren Platz, statt vom Rand
    // herüberzufliegen.
    const springen = sofort || !p.classList.contains('is-on');
    if(springen) p.classList.add('no-anim');
    p.style.setProperty('--x', a.offsetLeft + 'px');
    p.style.setProperty('--w', a.offsetWidth + 'px');
    if(springen){ void p.offsetWidth; p.classList.remove('no-anim'); }
    p.classList.add('is-on');
  };

  const hier = location.pathname.replace(/index\.html$/, '');
  const seite = eintraege.find(a => {
    const u = new URL(a.getAttribute('href'), location.href);
    return !u.hash && u.pathname !== '/' && hier.startsWith(u.pathname);
  });
  // Einseiter: Einträge mit #anker markieren den Abschnitt, in dem man liest.
  const abschnitte = eintraege.map(a => {
    const u = new URL(a.getAttribute('href'), location.href);
    const el = u.hash && u.pathname === hier ? document.getElementById(u.hash.slice(1)) : null;
    return el ? {a, el} : null;
  }).filter(Boolean);

  let aktiv = null;
  const markiere = (a, sofort) => {
    if(a === aktiv && !sofort) return;
    aktiv = a;
    eintraege.forEach(x => x === a ? x.setAttribute('aria-current', seite ? 'page' : 'true') : x.removeAttribute('aria-current'));
    mobil.forEach((x, i) => x.classList.toggle('active', eintraege[i] === a));
    setze(aktivPille, a, sofort);
  };
  const jetzt = () => {
    if(seite) return seite;
    const linie = innerHeight * .4;
    const t = abschnitte.find(s => { const r = s.el.getBoundingClientRect(); return r.top <= linie && r.bottom > linie; });
    return t ? t.a : null;
  };
  markiere(jetzt(), true);
  if(!seite && abschnitte.length){
    let wartet = false;
    addEventListener('scroll', () => {
      if(wartet) return;
      wartet = true;
      requestAnimationFrame(() => { wartet = false; markiere(jetzt(), false); });
    }, {passive:true});
  }
  eintraege.forEach(a => {
    a.addEventListener('mouseenter', () => setze(hoverPille, a));
    a.addEventListener('focus', () => setze(hoverPille, a));
  });
  links.addEventListener('mouseleave', () => setze(hoverPille, null));
  links.addEventListener('focusout', e => { if(!links.contains(e.relatedTarget)) setze(hoverPille, null); });
  // Breite und Lage ändern sich, wenn die Schrift nachlädt oder das Fenster
  // seine Größe ändert.
  const neu = () => markiere(jetzt(), true);
  addEventListener('resize', neu);
  if(document.fonts) document.fonts.ready.then(neu);
})();
```

## B8 Hero (dunkle Bühne, Choreografie, Maus-Parallax)

```html
<header class="hero theme-dark">
  <div class="hero__bg" aria-hidden="true">
    <div class="glow glow--a"></div>
    <div class="glow glow--b"></div>
    <!-- optional: Bildmarke als Linie, siehe unten "Logo zeichnet sich" -->
  </div>
  <div class="container hero__inner">
    <div class="hero__copy">
      <a class="hero__live" href="#termin">…siehe B4…</a>
      <h1><span class="hero-h1__line">[Ergebnis.]</span> <span class="hero-h1__line"><span class="hl">[Bezug.]</span></span></h1>
      <p class="lead">[Ich tue X, damit Kunde Y, nicht Z.]</p>
      <div class="cta-row">
        <a href="#termin" class="btn btn--primary btn--lg">[Kostenloses Erstgespräch]</a>
        <a href="#[werkzeug]" class="btn btn--glass btn--lg">[Zweite Handlung]</a>
      </div>
      <div class="hero__person">
        <img src="/inhaber-96.webp" alt="" width="40" height="40" decoding="async">
        <span><b>[Name]</b> [betreut Sie persönlich, aus ORT.]</span>
      </div>
    </div>
    <div class="hero__visual">
      <div class="proofcard" id="proofcard">…siehe B9…</div>
      <div class="hero__notes" aria-hidden="true">
        <div class="inote ios-note ios-note--a">…</div>
        <div class="inote inote--stack ios-note ios-note--b">…</div>
      </div>
      <p class="hero__caption">Beispieldarstellung</p>
    </div>
  </div>
</header>
```

```css
.hero{position:relative;isolation:isolate;overflow:hidden;
  display:flex;align-items:center;min-height:min(100svh,1020px);
  padding:calc(var(--nav-h) + clamp(36px,5vw,72px)) 0 calc(var(--sheet-r) + clamp(56px,7vw,104px));
  background:
    radial-gradient(1100px 680px at 80% 34%,rgba(var(--brand-rgb),.34),transparent 62%),
    radial-gradient(760px 520px at 6% 104%,rgba(var(--spark-rgb),.10),transparent 62%),
    linear-gradient(180deg,#090C1D 0%,#0C1027 100%);   /* = --navy-900, unten minimal heller */
}
.hero>.container{width:100%}
.hero__bg{position:absolute;inset:0;z-index:0;pointer-events:none}
/* Weiche Lichtflecken, die der Maus leicht folgen. Bewusst OHNE filter:blur:
   Der Verlauf ist von sich aus weich. Ein 80-px-Weichzeichner kostete
   gemessen mehr als die Hälfte der Bildrate beim Laden. */
.glow{position:absolute;border-radius:50%;pointer-events:none;will-change:transform}
.glow--a{width:720px;height:720px;right:calc(4% - 100px);top:calc(6% - 100px);opacity:.55;
  background:radial-gradient(circle closest-side,rgba(var(--brand-rgb),.5),rgba(var(--brand-rgb),.17) 48%,rgba(var(--brand-rgb),0))}
.glow--b{width:600px;height:600px;left:calc(-8% - 90px);bottom:calc(-12% - 90px);opacity:.42;
  background:radial-gradient(circle closest-side,rgba(var(--spark-rgb),.34),rgba(var(--spark-rgb),.1) 48%,rgba(var(--spark-rgb),0))}

/* Rechte Spalte genau so breit wie die Karte, die Überschrift bekommt den Rest. */
.hero__inner{position:relative;z-index:2;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,450px);
  gap:clamp(40px,4.4vw,72px);align-items:center}
.hero__copy{max-width:660px}
.hero h1{margin:24px 0 0;color:#fff;font-size:var(--fs-hero);font-weight:700;line-height:.98;letter-spacing:-.03em}
.hero-h1__line{display:block}
/* Zweite Zeile: EINE ruhige Vollfarbe. Ein Verlauf mit Lichtstreifen ist das
   bekannteste Erkennungszeichen generierter Landingpages. */
.hero h1 .hl,.pagehead h1 .hl{color:var(--brand-on-dark)}
.hero .lead{margin-top:26px;max-width:34ch;color:rgba(232,235,248,.74);font-size:clamp(1.12rem,1.5vw,1.34rem);line-height:1.5}
.hero .cta-row{margin-top:36px}
.hero__person{display:flex;align-items:center;gap:12px;margin-top:34px;color:rgba(232,235,248,.66);font-size:.95rem;line-height:1.4}
.hero__person img{width:40px;height:40px;border-radius:50%;object-fit:cover;object-position:50% 18%;box-shadow:0 0 0 2px rgba(255,255,255,.16)}
.hero__person b{color:#fff;font-weight:600}

.hero__visual{position:relative;width:100%;max-width:450px;justify-self:end;perspective:1300px}
/* Mitteilungen: Der Rahmen deckt nur die Karte ab (unten die Fußnote
   ausgespart), damit sich die Abstände auf die Karte beziehen. */
.hero__notes{position:absolute;left:0;right:0;top:0;bottom:34px;pointer-events:none;z-index:4}
.ios-note{position:absolute;width:min(310px,88%)}
.ios-note--a{top:-46px;right:-30px}
.ios-note--b{bottom:-40px;left:-46px}
.hero__caption{margin-top:16px;padding-right:6px;text-align:right;font-size:.78rem;line-height:18px;color:rgba(232,235,248,.42);letter-spacing:.01em}

/* ---- Einstieg ----
   Reine CSS-Keyframes mit festen Startzeiten, laufen mit dem ersten Bild an,
   bewegen nur opacity und transform. Jedes Element ist bis zu seinem Einsatz
   unsichtbar (fill-mode both) und endet genau im Ruhezustand. Ohne .js steht
   sofort alles da. Start bei 1 % Deckkraft: fürs Auge unsichtbar, für den
   Browser aber gemalt, die Überschrift zählt so sofort als LCP. */
@keyframes heroRise{from{opacity:.01;transform:translate3d(0,22px,0)}to{opacity:1;transform:none}}
@keyframes heroCard{from{opacity:0;transform:translate3d(0,40px,0) scale(.97)}to{opacity:1;transform:none}}
@keyframes floaty{0%,100%{translate:0 0}50%{translate:0 -10px}}
@keyframes noteIn{from{opacity:0;transform:translate3d(0,-12px,0) scale(.96)}to{opacity:1;transform:none}}
.js .hero-h1__line,.js .hero .lead,.js .hero .cta-row,.js .hero__person,.js .hero__live{
  animation:heroRise .95s var(--ease-apple) var(--hd,0s) both}
.js .hero-h1__line:first-child{--hd:.05s}
.js .hero-h1__line+.hero-h1__line{--hd:.13s}
.js .hero .lead{--hd:.24s}
.js .hero .cta-row{--hd:.33s}
.js .hero__person{--hd:.42s}
.js .hero__live{--hd:.5s}
/* Einblenden und Schweben am Eltern-Element, das Neigen (JS) an der Karte:
   So kommen sich die drei Bewegungen nie in die Quere. */
.js .hero__visual{animation:heroCard 1.1s var(--ease-apple) .18s both,floaty 8s ease-in-out 1.4s infinite}
.js .ios-note--a{animation:noteIn .75s var(--ease-apple) 2.75s both}
.js .ios-note--b{animation:noteIn .75s var(--ease-apple) 3.5s both}

/* Tablet: einspaltig. Die Karte liegt unter dem ersten Bildschirm, ihre Szene
   startet erst, wenn sie ins Bild kommt (Klassen aus dem Head-Skript B0). */
@media(max-width:960px){
  .hero{min-height:0;padding-bottom:calc(var(--sheet-r) + 72px)}
  .hero__inner{grid-template-columns:minmax(0,1fr);gap:64px}
  .hero__visual{justify-self:center;max-width:440px;margin-top:30px}
  .ios-note--a{right:-12px}
  .ios-note--b{left:-12px}
  .hero-wait .hero__visual:not(.hero-in-view),
  .hero-wait .hero__visual:not(.hero-in-view) *{animation-play-state:paused!important}
}
/* Telefon: eigene Komposition statt eines schmal gerechneten Desktops. */
@media(max-width:640px){
  .hero{padding:calc(var(--nav-h) + 26px) 0 calc(var(--sheet-r) + 56px)}
  .hero__live{font-size:.84rem;padding:7px 12px 7px 11px}
  /* Teiler 8 = Breite der längsten Zeile in Schriftgrößen (gemessen 7,7) plus
     Reserve. Für jeden Kunden NEU MESSEN (Faustwert: Zeichen × 0,5 + 0,3). */
  .hero h1{font-size:min(calc((100vw - 2 * var(--gutter)) / 8),3.5rem);line-height:1;margin-top:20px}
  .hero-h1__line{white-space:nowrap}
  .hero .lead{margin-top:18px;font-size:1.08rem;max-width:none}
  .hero .cta-row{flex-direction:column;align-items:stretch;margin-top:26px}
  .hero .cta-row .btn{width:100%}
  .hero__person{margin-top:24px;font-size:.88rem}
  .hero__visual{max-width:100%}
  .js .hero__visual{animation:heroCard 1.1s var(--ease-apple) .1s both}   /* ohne Schweben */
  .ios-note{width:min(286px,82%)}
  .ios-note--a{top:-48px;right:-4px}
  .ios-note--b{bottom:-44px;left:-4px}
  .hero__caption{margin-top:52px}
  .hero__notes{bottom:70px}
  .logo-scene{display:none}
}
```

```js
/* Maus-Parallax der Lichtflecken und Neigen der Karte, nur mit feinem Zeiger. */
(function(){
  if(!matchMedia('(hover:hover) and (pointer:fine)').matches) return;
  if(matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const a = document.querySelector('.glow--a');
  const b = document.querySelector('.glow--b');
  const karte = document.getElementById('proofcard');
  let tx = 0, ty = 0, raf = null;
  const apply = () => {
    if(a) a.style.transform = `translate3d(${(tx*18).toFixed(1)}px,${(ty*18).toFixed(1)}px,0)`;
    if(b) b.style.transform = `translate3d(${(tx*-12).toFixed(1)}px,${(ty*-12).toFixed(1)}px,0)`;
    if(karte) karte.style.transform = `rotateY(${(tx*5).toFixed(2)}deg) rotateX(${(-ty*5).toFixed(2)}deg)`;
    raf = null;
  };
  addEventListener('mousemove', e => {
    tx = (e.clientX / innerWidth - .5) * 2;
    ty = (e.clientY / innerHeight - .5) * 2;
    if(!raf) raf = requestAnimationFrame(apply);
  }, {passive:true});
})();
```

**Logo zeichnet sich (optional):** Die Bildmarke des Kunden als einzelner
SVG-Pfad mit `pathLength="1000"`, dreifach übereinander (Füllung, Glühen,
Linie), Verlauf von `--brand-on-dark` nach `--spark`.

```html
<div class="logo-scene">
  <svg viewBox="0 0 [W] [H]">
    <defs><linearGradient id="lineGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#9FB0FF"/><stop offset="100%" stop-color="#7CF0C4"/>
    </linearGradient></defs>
    <path id="logo-fill" d="[PFAD]"/>
    <path id="logo-glow" pathLength="1000" d="[PFAD]"/>
    <path id="logo-path" pathLength="1000" d="[PFAD]"/>
  </svg>
</div>
```

```css
/* Eigene Ebene plus Paint-Containment: Das Nachzeichnen malt nur diese
   Ebene neu. Endzustand = Ruhezustand (ohne JS, Bewegung reduziert). */
.logo-scene{position:absolute;top:50%;right:-4%;width:min(760px,60vw);aspect-ratio:1/1;
  transform:translateY(-52%);opacity:.9;contain:layout paint;will-change:transform}
.logo-scene svg{width:100%;height:100%;display:block}
#logo-glow{fill:none;stroke:url(#lineGrad);stroke-width:7;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:1000;stroke-dashoffset:0;opacity:0}
#logo-path{fill:none;stroke:url(#lineGrad);stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:1000;stroke-dashoffset:0;stroke-opacity:.28}
#logo-fill{fill:url(#lineGrad);fill-opacity:.05}
.js #logo-glow{animation:jlDraw 2.2s cubic-bezier(.65,0,.35,1) .35s both,jlGlowFade .8s ease 2.35s both}
.js #logo-path{animation:jlDraw 2.2s cubic-bezier(.65,0,.35,1) .35s both,jlStrokeSettle 1.2s ease 2.55s both}
.js #logo-fill{animation:jlFillIn 1.2s ease 2.45s both}
@keyframes jlDraw{from{stroke-dashoffset:1000}to{stroke-dashoffset:0}}
@keyframes jlGlowFade{from{opacity:.22}to{opacity:0}}
@keyframes jlStrokeSettle{from{stroke-opacity:1}to{stroke-opacity:.28}}
@keyframes jlFillIn{from{fill-opacity:0}to{fill-opacity:.05}}
@media(max-width:960px){.logo-scene{right:-24%;top:18%;width:92vw;opacity:.55}}
```

## B9 Beweis-Objekt: Karte mit Liste, die sich in den Gewinnzustand sortiert

Bei Lokal-SEO ist das eine Google-Trefferliste. Für andere Branchen dieselbe
Mechanik mit anderen Zeilen (Termine, Reservierungen, Anfragen; Teil A,
Abschnitt 8). Die Zeilen liegen absolut gestapelt (Schrittweite 70 px), der
Ruhezustand ist das Ende der Geschichte: die eigene Zeile oben.

```html
<div class="proofcard" id="proofcard">
  <div class="proofcard__head">
    <div class="gpill">[G-LOGO oder Symbol]<span class="gpill__label">[Ihre Leistung in Ihrer Stadt]</span></div>
  </div>
  <div class="map">[KARTEN-SVG oder Bild]<div class="pin"><span class="pulse"></span><span class="pulse d2"></span><span class="pulse d3"></span>[PIN-SVG]</div></div>
  <div class="results">
    <div class="result result--ghost result--r1">
      <span class="rank"><span class="rank__v rank__v--vor">1</span><span class="rank__v rank__v--nach">2</span></span>
      <div class="result__body"><div class="name">[Mitbewerber A]</div><div class="result__meta"><span class="stars">★★★★☆</span></div></div>
    </div>
    <div class="result result--ghost result--r2">
      <span class="rank"><span class="rank__v rank__v--vor">2</span><span class="rank__v rank__v--nach">3</span></span>
      <div class="result__body"><div class="name">[Mitbewerber B]</div><div class="result__meta"><span class="stars">★★★★☆</span></div></div>
    </div>
    <div class="result result--you">
      <span class="rank"><span class="rank__v rank__v--vor">15</span><span class="rank__v rank__v--nach">1</span></span>
      <div class="result__body"><div class="name">Ihr Unternehmen</div><div class="result__meta"><span class="stars">★★★★★</span><span class="openchip">Geöffnet</span></div></div>
      <span class="climb-badge">[PFEIL NACH OBEN]Platz 1</span>
    </div>
  </div>
</div>
```

```css
.proofcard{position:relative;color:#0E1122;background:#fff;border-radius:28px;padding:16px 16px 18px;
  box-shadow:0 60px 120px -40px rgba(0,0,0,.75),0 20px 40px -20px rgba(0,0,0,.4),0 0 0 1px rgba(255,255,255,.06);
  transition:transform .3s var(--ease-out)}
.proofcard__head{margin-bottom:12px}
.gpill{display:flex;align-items:center;gap:10px;height:44px;padding:0 16px;border-radius:999px;
  background:#F1F3F4;color:#3C4043;font-size:.92rem;font-family:var(--font-sys)}
.gpill__label{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.map{position:relative;height:132px;border-radius:18px;overflow:hidden;margin-bottom:12px;background:#F5F1EA;
  box-shadow:inset 0 0 0 1px rgba(0,0,0,.05)}
.map>svg{position:absolute;inset:0;width:100%;height:100%}
.pin{position:absolute;top:56%;left:52%;z-index:3}
/* Radar um die Nadel: skaliert statt Breite und Höhe zu ändern (kein
   Neu-Layout in jedem Bild). */
.pulse{position:absolute;left:-75px;top:-75px;width:150px;height:150px;border-radius:50%;border:2px solid var(--brand);
  opacity:0;transform:scale(.12);animation:pulse 4.5s var(--ease) 1.3s infinite}
.pulse.d2{animation-delay:2.8s}.pulse.d3{animation-delay:4.3s}
@keyframes pulse{0%{opacity:.7;transform:scale(.12)}100%{opacity:0;transform:scale(1)}}

.results{position:relative;height:calc(3 * 62px + 2 * 8px)}
.result{position:absolute;left:0;right:0;top:0;display:flex;align-items:center;gap:12px;height:62px;padding:0 14px;
  border-radius:16px;font-family:var(--font-sys)}
.result--you{z-index:2;transform:translate3d(0,0,0);background:#F4F5FC;border:1px solid rgba(var(--brand-rgb),.28);
  box-shadow:0 14px 30px -18px rgba(var(--brand-rgb),.6)}
.result--r1{transform:translate3d(0,70px,0)}
.result--r2{transform:translate3d(0,140px,0)}
.result--ghost{background:#fff;border:1px solid #ECEEF3}
.rank__v{grid-area:1/1}
.rank__v--vor{opacity:0}
.result--you .rank{background:var(--brand);color:#fff;font-weight:700}
.result__body{flex:1;min-width:0}
.result .name{font-size:.95rem;font-weight:600;color:#202124;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;letter-spacing:-.01em}
.result--ghost .name{color:#4A4F63;font-weight:500}
.result--you .name{font-weight:700;color:#0E1122}
.result__meta{display:flex;align-items:center;gap:7px;margin-top:3px;font-size:.78rem}

/* Aufstieg: eigene Zeile von unten nach oben, die anderen rutschen nach,
   die Platzziffern tauschen, dann erscheint das Badge. */
@keyframes climbYou{from{transform:translate3d(0,140px,0)}to{transform:translate3d(0,0,0)}}
@keyframes climbR1{from{transform:translate3d(0,0,0)}to{transform:translate3d(0,70px,0)}}
@keyframes climbR2{from{transform:translate3d(0,70px,0)}to{transform:translate3d(0,140px,0)}}
@keyframes rankOut{from{opacity:1}to{opacity:0}}
@keyframes rankIn{from{opacity:0}to{opacity:1}}
@keyframes badgeIn{from{opacity:0;transform:scale(.85)}to{opacity:1;transform:none}}
.js .result--you{animation:climbYou .95s var(--ease-apple) 1.55s both}
.js .result--r1{animation:climbR1 .95s var(--ease-apple) 1.55s both}
.js .result--r2{animation:climbR2 .95s var(--ease-apple) 1.55s both}
.js .rank__v--vor{animation:rankOut .3s ease 1.85s both}
.js .rank__v--nach{animation:rankIn .35s ease 1.95s both}
.js .climb-badge{animation:badgeIn .5s var(--ease-out) 2.35s both}
@media(max-width:640px){.proofcard{padding:12px 12px 14px;border-radius:24px}}
@media(max-width:400px){.result{padding:0 10px;gap:9px}.result .name{font-size:.88rem}.climb-badge{display:none}}
```

Hinweis zur Platzziffer: `.rank__v--vor` steht im Ruhezustand auf 0, damit
ohne JavaScript sofort der Endzustand („1“) sichtbar ist; mit `.js` blendet die
Animation erst „15“ aus und „1“ ein.

## B10 Ablauf als Bento-Raster

```html
<div class="bento">
  <a class="bento__tile" href="[LINK]" data-reveal>
    <span class="bento__top"><span class="bento__ic">[SYMBOL 20px]</span><span class="bento__step">Schritt 1</span></span>
    <h3>[Analyse]</h3>
    <p>[Ein Satz.]</p>
    <span class="bento__more">Mehr erfahren<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></span>
  </a>
  <!-- Kachel 2 bis 4 mit data-reveal data-d="1|2|3" -->
</div>
<div class="bento__foot" data-reveal>
  <ul class="chips">…B6…</ul>
  <a class="link-arrow" href="[LINK]">[Alle Leistungen ansehen][PFEIL]</a>
</div>
```

```css
.bento{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-top:var(--sp-head)}
.bento__tile{position:relative;display:flex;flex-direction:column;gap:10px;min-height:260px;padding:28px 26px 26px;
  border-radius:var(--r-xl);background:var(--bg-soft);color:var(--ink);
  transition:transform .5s var(--ease-out),box-shadow .5s var(--ease-out),background .3s}
/* Auf grauem Abschnitt wären graue Kacheln unsichtbar: dort weiß. */
.section--soft .bento__tile{background:#fff}
@media(hover:hover){
  .bento__tile:hover,.js .bento__tile.in:hover{transform:translateY(-4px);background:#fff;box-shadow:var(--elev-2)}
  .bento__tile:hover .bento__more svg{transform:translateX(3px)}
}
.bento__top{display:flex;align-items:center;justify-content:space-between;margin-bottom:auto;padding-bottom:26px}
.bento__ic{display:flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:13px;
  background:#fff;color:var(--brand);box-shadow:0 1px 2px rgba(0,0,0,.06),0 6px 16px -10px rgba(var(--brand-rgb),.5)}
.bento__step{font-size:.8rem;font-weight:600;color:var(--dim-2)}
.bento__tile h3{font-size:clamp(1.35rem,1.9vw,1.6rem);letter-spacing:-.022em}
.bento__tile p{color:var(--dim);font-size:.98rem;line-height:1.45}
.bento__more{display:inline-flex;align-items:center;gap:5px;margin-top:6px;color:var(--link);font-weight:600;font-size:.93rem}
.bento__more svg{transition:transform var(--dur-ui) var(--ease-apple)}
.bento__foot{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:18px 28px;margin-top:28px}
@media(max-width:1060px){.bento{grid-template-columns:repeat(2,minmax(0,1fr))}.bento__tile{min-height:220px}}
@media(max-width:560px){
  .bento{gap:10px}
  .bento__tile{min-height:0;padding:20px 18px;border-radius:var(--r-lg);gap:6px}
  .bento__top{padding-bottom:14px}
  .bento__ic{width:40px;height:40px;border-radius:11px}
  .bento__tile h3{font-size:1.18rem}
  .bento__tile p{font-size:.9rem}
  .bento__more{display:none}
  .bento__foot{flex-direction:column;align-items:center;text-align:center;gap:18px;margin-top:24px}
  .bento__foot .chips{justify-content:center}
}
```

## B11 Kachelraster, auf dem Telefon eine iOS-Einstellungsliste

```html
<div class="tilelist">
  <a class="tilelist__item" href="[LINK]" data-reveal>
    <span class="tilelist__ic">[SYMBOL 20px]</span>
    <span class="tilelist__tx">[Leistung oder Branche]</span>
    <svg class="tilelist__go" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
  </a>
</div>
```

```css
.tilelist{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px;margin-top:var(--sp-head)}
.tilelist__item{display:flex;align-items:center;gap:14px;padding:18px 18px 18px 16px;border-radius:20px;background:var(--bg-soft);
  font-weight:600;letter-spacing:-.015em;
  transition:background var(--dur-ui) var(--ease-apple),transform var(--dur-ui) var(--ease-apple),box-shadow var(--dur-ui)}
.tilelist__ic{flex:none;width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;
  background:#fff;color:var(--brand);box-shadow:0 1px 2px rgba(14,17,34,.06)}
.tilelist__tx{flex:1;min-width:0}
.tilelist__go{flex:none;color:var(--soft);transition:transform var(--dur-ui) var(--ease-apple),color var(--dur-ui)}
@media(hover:hover){
  .tilelist__item:hover{background:#fff;box-shadow:var(--elev-2);transform:translateY(-2px)}
  .tilelist__item:hover .tilelist__go{color:var(--brand);transform:translateX(3px)}
}
.section--soft .tilelist__item{background:#fff}
/* Telefon: eine gruppierte Liste wie in den iOS-Einstellungen statt vieler
   Kacheln im Zweierraster. Halb so hoch, jede Zeile eine Daumenbreite, der
   Pfeil wird zum Chevron. */
@media(max-width:560px){
  .tilelist{grid-template-columns:minmax(0,1fr);gap:0;padding:4px 0;border-radius:22px;background:var(--bg-soft);overflow:hidden}
  .section--soft .tilelist{background:#fff}
  .tilelist__item,.section--soft .tilelist__item{position:relative;min-height:56px;padding:9px 18px 9px 14px;border-radius:0;background:none;
    font-size:1rem;line-height:1.3;transition:background var(--dur-tap)}
  .tilelist__item:not(:last-child)::after{content:"";position:absolute;left:60px;right:0;bottom:0;height:1px;background:rgba(14,17,34,.08)}
  .tilelist__item::before{content:"";order:3;flex:none;width:8px;height:8px;margin-right:3px;
    border-top:2px solid #C4C5CE;border-right:2px solid #C4C5CE;transform:rotate(45deg)}
  .tilelist__ic{width:32px;height:32px;border-radius:9px;background:rgba(var(--brand-rgb),.1);box-shadow:none}
  .tilelist__ic svg{width:18px;height:18px}
  .tilelist__go{display:none}
  .tilelist__item:active{background:rgba(14,17,34,.05)}
}
```

## B12 Laufband

```html
<div class="ticker" aria-label="[Weitere Leistungen]">
  <div class="ticker__track">
    <div class="ticker__group"><span>[Begriff]</span><span>[Begriff]</span>…</div>
    <div class="ticker__group" aria-hidden="true"><!-- exakt dieselben Begriffe noch einmal --></div>
  </div>
</div>
```

```css
.ticker{position:relative;margin-top:clamp(48px,6vw,80px);overflow:hidden;
  -webkit-mask-image:linear-gradient(90deg,transparent,#000 10%,#000 90%,transparent);
          mask-image:linear-gradient(90deg,transparent,#000 10%,#000 90%,transparent)}
.ticker__track{display:flex;width:max-content;animation:ticker 90s linear infinite;will-change:transform}
.ticker:hover .ticker__track{animation-play-state:paused}
.ticker__group{display:flex;align-items:center;flex:none}
.ticker__group span{display:inline-flex;align-items:center;gap:28px;padding-right:28px;white-space:nowrap;
  font-family:var(--font-display);font-weight:700;font-size:clamp(1.5rem,2.4vw,2.1rem);letter-spacing:-.022em;color:#8E8E93}
.ticker__group span::after{content:"";width:6px;height:6px;border-radius:50%;background:#C7C7CC}
@keyframes ticker{to{transform:translateX(-50%)}}
```

## B13 Große Zahlen mit Hochzählen

```html
<div class="stats__grid">
  <div class="stat" data-reveal>
    <div class="stat__num"><span class="count" data-count="76">76</span><span class="unit">&thinsp;%</span></div>
    <p>[Satz, was die Zahl bedeutet.]</p>
  </div>
  <div class="stat" data-reveal data-d="1">
    <div class="stat__num"><span class="count" data-count="2.7" data-dec="1">2,7</span><span class="unit">&thinsp;×</span></div>
    <p>[…]</p>
  </div>
  <!-- drittes .stat mit data-d="2" -->
</div>
<p class="stats__src" data-reveal>Quellen: [Herausgeber, Titel, Jahr] · […]</p>
```

```css
.stats__grid{display:grid;grid-template-columns:repeat(3,1fr);margin-top:var(--sp-head);border-top:1px solid var(--border)}
.stat{padding:36px 32px 8px 0}
.stat+.stat{padding-left:32px;border-left:1px solid var(--border)}
.stat__num{font-family:var(--font-display);font-weight:700;font-size:clamp(3.4rem,7vw,5.6rem);letter-spacing:-.02em;line-height:1;margin-bottom:14px;color:var(--ink)}
.stat__num .unit{font-size:.5em;font-weight:700;color:var(--spark);margin-left:.04em}
.stat p{color:var(--dim);font-size:1.02rem;line-height:1.55;max-width:30ch}
.stats__src{font-size:.8rem;color:var(--dim-2);margin-top:30px;max-width:80ch}
@media(max-width:860px){
  .stats__grid{grid-template-columns:1fr}
  .stat,.stat+.stat{padding:28px 0;border-left:0}
  .stat+.stat{border-top:1px solid var(--border)}
}
/* Telefon: Zahl links, Satz rechts. Die Zahlenspalte ist so breit wie die
   breiteste Zahl, damit die Sätze bündig beginnen. */
@media(max-width:640px){
  .stat,.stat+.stat{display:grid;grid-template-columns:minmax(0,6.6rem) minmax(0,1fr);align-items:center;gap:18px;padding:22px 0}
  .stat__num{font-size:3rem;margin-bottom:0;white-space:nowrap}
}
```

```js
/* Hochzählen als Zusatz: Der Endwert steht im HTML und BLEIBT dort, bis die
   Zahl ins Bild kommt (Suchmaschinen, Vorschaubilder, ohne JS sehen ihn).
   Breite des Endwerts vorher festhalten, sonst schiebt die wachsende Zahl die
   Einheit mit. Mit Nachlauf für schnelles Wischen und hartem Endwert. */
(function(){
  const els = [...document.querySelectorAll('.count')];
  if(!els.length || matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;
  const fmt = (v, dec) => dec ? v.toFixed(dec).replace('.', ',') : String(Math.round(v));
  const run = el => {
    if(el.__lief) return; el.__lief = true;
    const ziel = parseFloat(el.dataset.count) || 0, dec = parseInt(el.dataset.dec, 10) || 0, dur = 1400;
    let t0 = null, fertig = false;
    const ende = () => { if(!fertig){ fertig = true; el.textContent = fmt(ziel, dec); } };
    el.textContent = fmt(0, dec);
    const tick = ts => {
      if(fertig) return;
      if(!t0) t0 = ts;
      const p = Math.min((ts - t0) / dur, 1);
      el.textContent = fmt((1 - Math.pow(1 - p, 3)) * ziel, dec);
      p < 1 ? requestAnimationFrame(tick) : ende();
    };
    requestAnimationFrame(tick);
    setTimeout(ende, dur + 400);
  };
  const offen = els.slice();
  const nachlauf = () => {
    for(let i = offen.length - 1; i >= 0; i--){
      if(offen[i].getBoundingClientRect().top < innerHeight - 50){ io.unobserve(offen[i]); run(offen[i]); offen.splice(i, 1); }
    }
  };
  const io = new IntersectionObserver(es => { es.forEach(e => { if(e.isIntersecting){ io.unobserve(e.target); const i = offen.indexOf(e.target); if(i > -1) offen.splice(i, 1); run(e.target); } }); nachlauf(); },
    {threshold:.14, rootMargin:'0px 0px -50px 0px'});
  els.forEach(el => {
    el.style.display = 'inline-block'; el.style.textAlign = 'right';
    el.style.minWidth = Math.ceil(el.getBoundingClientRect().width) + 'px';
    io.observe(el);
  });
  let ruhe = null;
  addEventListener('scroll', () => { clearTimeout(ruhe); ruhe = setTimeout(nachlauf, 140); }, {passive:true});
})();
```

## B14 Über mich: Porträt mit Glas-Namensschild, Signatur

```html
<div class="about__grid">
  <div class="portrait" data-reveal>
    <div class="portrait__frame">
      <img src="/portrait-800.webp" alt="[Name], [Rolle], [Ort]" width="800" height="1000" loading="lazy">
    </div>
  </div>
  <div class="about__text" data-reveal data-d="1">
    <h2>[Ein Ansprechpartner.] <span class="soft">[Kein Callcenter.]</span></h2>
    <p>Ich bin <b class="mark">[Name]</b> aus [Ort]. [Ein, zwei Sätze.]</p>
    <ul class="chips chips--stack">…B6…</ul>
    <div class="about__sign"><span class="about__name">[Name]</span><span class="about__role">[Rolle · Ort]</span></div>
    <a class="link-arrow" href="/ueber-uns/">Mehr über mich[PFEIL]</a>
  </div>
</div>
```

```css
.about__grid{display:grid;grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr);gap:clamp(40px,7vw,110px);align-items:center}
.portrait{position:relative}
.portrait__frame{position:relative;aspect-ratio:4/5;border-radius:var(--r-xl);overflow:hidden;background:var(--bg-soft);box-shadow:var(--elev-3)}
.portrait__frame img{width:100%;height:100%;object-fit:cover}
/* Namensschild im Bild (optional): Milchglas ist hier erlaubt, die Fläche
   bewegt sich nicht. */
.portrait__badge{position:absolute;left:14px;right:14px;bottom:14px;display:flex;flex-direction:column;gap:2px;padding:12px 16px;border-radius:16px;
  background:rgba(255,255,255,.82);-webkit-backdrop-filter:blur(16px);backdrop-filter:blur(16px)}
.about__text h2{font-size:var(--fs-h2);margin-bottom:26px}
.about__text p{color:var(--dim);font-size:1.1rem;line-height:1.65;margin-bottom:16px;max-width:52ch}
.about__text .mark{color:var(--ink);font-weight:600}
.about__sign{display:flex;flex-direction:column;gap:2px;margin:28px 0 22px;padding-left:16px;border-left:2px solid var(--brand)}
.about__name{font-family:var(--font-display);font-weight:700;font-size:1.2rem;letter-spacing:-.025em}
.about__role{font-size:.9rem;color:var(--dim-2)}
@media(max-width:860px){.about__grid{grid-template-columns:1fr}.portrait{max-width:380px}}
/* Telefon: quadratischer Ausschnitt. 4:5 füllte einen ganzen Bildschirm,
   bevor ein Wort zu lesen war. */
@media(max-width:640px){
  .about__grid{gap:30px}
  .portrait{max-width:none}
  .portrait__frame{aspect-ratio:1/1}
  .portrait__frame img{object-position:50% 16%}
}
```

## B15 FAQ: Kopf klebt links, Akkordeon rechts

```html
<section class="section section--soft" id="faq">
  <div class="container faq__layout">
    <div class="head-block">
      <h2>Häufige Fragen.</h2>
      <p>Alles Weitere klären wir im Gespräch.</p>
      <a class="link-arrow" href="#kontakt">Frage stellen[PFEIL]</a>
    </div>
    <div class="faq__list">
      <div class="faq__item">
        <button class="faq__q" aria-expanded="false" aria-controls="faq-a1">[Frage?]<span class="faq__icon" aria-hidden="true"></span></button>
        <div class="faq__a" id="faq-a1" role="region"><div class="faq__a-inner"><div class="faq__a-text">[Antwort.]</div></div></div>
      </div>
    </div>
  </div>
</section>
```

```css
.faq__layout{display:grid;grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr);gap:clamp(36px,6vw,96px);align-items:start}
.faq__layout .head-block{position:sticky;top:calc(var(--nav-h) + 32px)}
.faq__list{border-top:1px solid var(--border)}
.faq__item{border-bottom:1px solid var(--border)}
.faq__q{width:100%;display:flex;justify-content:space-between;align-items:center;gap:20px;padding:24px 0;
  background:none;border:0;cursor:pointer;text-align:left;color:var(--ink);
  font-family:var(--font-display);font-weight:650;font-size:clamp(1.08rem,1.4vw,1.22rem);letter-spacing:-.02em;line-height:1.35}
.faq__icon{flex:none;position:relative;width:30px;height:30px;border-radius:50%;background:rgba(14,17,34,.05);
  transition:transform .45s var(--ease-apple),background .3s}
.faq__icon::before,.faq__icon::after{content:"";position:absolute;top:50%;left:50%;width:12px;height:1.8px;border-radius:2px;
  background:var(--ink);transform:translate(-50%,-50%);transition:transform .45s var(--ease-apple),opacity .3s}
.faq__icon::after{transform:translate(-50%,-50%) rotate(90deg)}
@media(hover:hover){.faq__q:hover .faq__icon{background:rgba(14,17,34,.09)}}
.faq__item.open .faq__icon{transform:rotate(180deg);background:var(--ink)}
.faq__item.open .faq__icon::before{background:#fff}
.faq__item.open .faq__icon::after{opacity:0;transform:translate(-50%,-50%) rotate(0deg)}
/* Höhe ohne Messen: grid-template-rows 0fr -> 1fr */
.faq__a{display:grid;grid-template-rows:0fr;transition:grid-template-rows .45s var(--ease-apple)}
.faq__item.open .faq__a{grid-template-rows:1fr}
.faq__a-inner{overflow:hidden;min-height:0}
.faq__a-text{padding:0 52px 26px 0;color:var(--dim);font-size:1.03rem;line-height:1.7}
.faq__a-text a{color:var(--link);text-decoration:underline;text-underline-offset:3px}
.theme-dark .faq__icon{background:rgba(255,255,255,.08)}
.theme-dark .faq__icon::before,.theme-dark .faq__icon::after{background:#fff}
@media(max-width:900px){
  .faq__layout{grid-template-columns:1fr}
  .faq__layout .head-block{position:static;margin:0 auto;text-align:center}
  .faq__a-text{padding-right:0}
}
@media(max-width:640px){.faq__q{padding:20px 0;gap:16px}}
```

```js
/* Immer nur eine Antwort offen. Reines Klassen-Toggle, die Höhe macht CSS. */
document.querySelectorAll('.faq__item').forEach(item => {
  const q = item.querySelector('.faq__q');
  q.addEventListener('click', () => {
    const offen = item.classList.contains('open');
    document.querySelectorAll('.faq__item.open').forEach(o => {
      if(o !== item){ o.classList.remove('open'); o.querySelector('.faq__q').setAttribute('aria-expanded', 'false'); }
    });
    item.classList.toggle('open', !offen);
    q.setAttribute('aria-expanded', String(!offen));
  });
});
```

## B16 Vorher/Nachher-Regler

```html
<div class="vnc">
  <div class="vnc__stage" role="slider" tabindex="0" aria-label="Vorher und nachher vergleichen"
       aria-valuemin="0" aria-valuemax="100" aria-valuenow="50">
    <div class="vnc__side vnc__side--before">[Vorher-Inhalt]</div>
    <div class="vnc__side vnc__side--after">[Nachher-Inhalt]</div>
    <span class="vnc__label vnc__label--before">Vorher</span>
    <span class="vnc__label vnc__label--after">Nachher</span>
    <div class="vnc__handle" aria-hidden="true">
      <span class="vnc__line"></span>
      <span class="vnc__grip"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6-6 6 6 6M15 6l6 6-6 6"/></svg></span>
    </div>
  </div>
  <p class="vnc__caption">Ziehen, um zu vergleichen.</p>
</div>
```

```css
.vnc{max-width:960px;margin:var(--sp-head) auto 0;color:#1D1D1F;font-family:var(--font-sys)}
.vnc__stage{position:relative;width:100%;height:min(600px,72vh);min-height:480px;border-radius:var(--r-xl);overflow:hidden;
  background:#FFF;box-shadow:0 0 0 1px rgba(14,17,34,.06),0 30px 70px -30px rgba(14,17,34,.3);
  cursor:ew-resize;touch-action:pan-y;user-select:none;-webkit-user-select:none}
.vnc__stage:focus-visible{outline:2px solid var(--brand);outline-offset:3px}
.vnc__side{position:absolute;inset:0;padding:34px 26px;display:flex;align-items:center;justify-content:center}
.vnc__side--before{background:linear-gradient(180deg,#EEEFF3 0%,#F7F7F9 100%)}
.vnc__side--after{background:radial-gradient(700px circle at 74% 6%,rgba(var(--brand-rgb),.08),transparent 62%),linear-gradient(180deg,#FFFFFF 0%,#F7F8FC 100%);
  clip-path:inset(0 0 0 var(--pos,50%))}
.vnc__label{position:absolute;top:20px;font-size:.78rem;font-weight:600;letter-spacing:.01em;padding:8px 14px;border-radius:999px}
.vnc__label--before{left:20px;background:rgba(255,255,255,.8);color:#6E6E73;box-shadow:0 0 0 1px rgba(0,0,0,.06)}
.vnc__label--after{right:20px;background:var(--brand);color:#FFF;box-shadow:0 10px 24px -10px rgba(var(--brand-rgb),.8)}
.vnc__handle{position:absolute;top:0;bottom:0;left:var(--pos,50%);width:0;pointer-events:none;z-index:3}
.vnc__line{position:absolute;top:0;bottom:0;left:-1px;width:2px;
  background:linear-gradient(180deg,rgba(255,255,255,0),rgba(255,255,255,.95) 12%,rgba(255,255,255,.95) 88%,rgba(255,255,255,0));
  box-shadow:0 0 0 .5px rgba(0,0,0,.12),0 2px 14px rgba(0,0,0,.22)}
.vnc__grip{position:absolute;top:50%;left:0;transform:translate(-50%,-50%);width:58px;height:58px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;color:#1D1D1F;background:rgba(255,255,255,.94);
  border:1px solid rgba(0,0,0,.06);box-shadow:0 1px 1px rgba(255,255,255,.9) inset,0 10px 30px -8px rgba(0,0,0,.28);
  transition:transform .35s cubic-bezier(.16,1,.3,1)}
.vnc__grip.is-dragging{transform:translate(-50%,-50%) scale(1.12)}
.vnc__caption{text-align:center;color:var(--dim-2);font-size:.84rem;margin:16px auto 0;font-family:var(--font-text)}
@media(max-width:640px){
  .vnc__stage{height:440px;min-height:0;border-radius:24px}
  .vnc__side{padding:16px;padding-top:54px;align-items:flex-start}
  .vnc__grip{width:46px;height:46px}
  .vnc__label{top:12px;font-size:.7rem}
  .vnc__label--before{left:12px}.vnc__label--after{right:12px}
}
```

```js
/* Eine Pointer-Logik für Maus und Touch, Pfeiltasten, Pos1/Ende. Beim ersten
   Sichtkontakt schwingt der Griff einmal gedämpft aus. */
document.querySelectorAll('.vnc__stage').forEach(stage => {
  const grip = stage.querySelector('.vnc__grip');
  let pos = 50, drag = false, raf = null, next = 50;
  const set = p => {
    pos = Math.max(0, Math.min(100, p));
    stage.style.setProperty('--pos', pos + '%');
    stage.setAttribute('aria-valuenow', String(Math.round(pos)));
    stage.setAttribute('aria-valuetext', 'Regler bei ' + Math.round(pos) + ' %');
  };
  const queue = p => { next = p; if(!raf) raf = requestAnimationFrame(() => { set(next); raf = null; }); };
  const fromEvent = e => { const r = stage.getBoundingClientRect(); return (e.clientX - r.left) / r.width * 100; };
  set(50);
  stage.addEventListener('pointerdown', e => {
    drag = true;
    try{ stage.setPointerCapture(e.pointerId); }catch(_){}
    grip && grip.classList.add('is-dragging');
    queue(fromEvent(e)); e.preventDefault();
  });
  stage.addEventListener('pointermove', e => { if(drag) queue(fromEvent(e)); });
  const end = () => { drag = false; grip && grip.classList.remove('is-dragging'); };
  stage.addEventListener('pointerup', end);
  stage.addEventListener('pointercancel', end);
  stage.addEventListener('keydown', e => {
    const k = {ArrowLeft:-5, ArrowDown:-5, ArrowRight:5, ArrowUp:5}[e.key];
    if(k){ set(pos + k); e.preventDefault(); }
    else if(e.key === 'Home'){ set(0); e.preventDefault(); }
    else if(e.key === 'End'){ set(100); e.preventDefault(); }
  });
  if(matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;
  const io = new IntersectionObserver(es => {
    if(!es[0].isIntersecting) return;
    io.disconnect();
    setTimeout(() => {
      let t0 = null;
      const swing = ts => {
        if(drag) return;
        if(!t0) t0 = ts;
        const p = Math.min((ts - t0) / 1700, 1), e = 1 - Math.pow(1 - p, 3);
        set(50 + Math.sin(e * Math.PI * 2) * 16 * (1 - e));
        if(p < 1) requestAnimationFrame(swing);
      };
      requestAnimationFrame(swing);
    }, 1100);
  }, {threshold:.55});
  io.observe(stage);
});
```

## B17 Formularfelder und Statusmeldung

```css
.form .btn{width:100%;margin-top:6px}
.form__row{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
.field{margin-bottom:14px}
.field label{display:block;font-size:.86rem;font-weight:600;color:var(--dim);margin-bottom:7px}
.field__opt{color:var(--dim-2);font-weight:400}
.field input,.field textarea,.field select{width:100%;background:var(--surface-2);border:1px solid transparent;border-radius:14px;
  padding:14px 16px;color:var(--ink);font-family:var(--font-text);font-size:1rem;   /* 16px: kein Zoom auf iOS */
  transition:border-color .2s,background .2s,box-shadow .2s}
.field input::placeholder,.field textarea::placeholder{color:var(--soft)}
.field input:hover,.field textarea:hover{background:#ECEEF4}
.field input:focus,.field textarea:focus{outline:0;background:#fff;border-color:var(--brand);box-shadow:0 0 0 4px rgba(var(--brand-rgb),.14)}
.field textarea{resize:vertical;min-height:120px}
.field.has-error input,.field.has-error textarea{border-color:#C4584A}
.field__error{font-size:.8rem;color:#94373A;margin-top:6px}
.field:not(.has-error) .field__error{display:none}
.honey{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
@media(max-width:480px){.form__row{grid-template-columns:1fr}}
.form-status{display:none;align-items:flex-start;gap:10px;margin-top:14px;padding:14px 16px;border-radius:14px;font-size:.93rem;line-height:1.55}
.form-status.show{display:flex}
.form-status--ok{background:rgba(31,138,90,.09);color:#12613E}
.form-status--ok::before{content:"✓";flex:none;font-weight:700}
.form-status--err{background:rgba(214,64,48,.08);color:#A03024}
.form-status a{text-decoration:underline;text-underline-offset:3px}
```

## B18 Segment-Umschalter (iOS)

```html
<div class="seg" role="group" aria-label="[Ansicht wählen]">
  <button type="button" class="seg__opt is-active" aria-pressed="true">[Option A]</button>
  <button type="button" class="seg__opt" aria-pressed="false">[Option B]</button>
</div>
```

```css
/* Hell: weißer Daumen auf grauer Schiene. */
.seg{display:flex;gap:4px;padding:4px;border-radius:14px;background:#E9EAF0}
.seg__opt{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:8px 14px;border:0;border-radius:10px;
  background:none;cursor:pointer;font-size:.92rem;font-weight:600;color:var(--dim);white-space:nowrap;
  transition:background var(--dur-ui) var(--ease-apple),color var(--dur-ui),box-shadow var(--dur-ui)}
.seg__opt:hover{color:var(--ink)}
.seg__opt.is-active{background:#fff;color:var(--ink);box-shadow:var(--elev-1)}
/* Dunkel, mit gleitendem Daumen (data-show am Eltern-Element setzt das Skript). */
.seg--dark{position:relative;background:rgba(255,255,255,.07);box-shadow:inset 0 0 0 1px rgba(255,255,255,.08)}
.seg--dark::before{content:"";position:absolute;left:4px;top:4px;bottom:4px;width:calc(50% - 4px);border-radius:10px;
  background:rgba(255,255,255,.15);box-shadow:0 1px 2px rgba(0,0,0,.3),inset 0 .5px 0 rgba(255,255,255,.14);
  transition:transform .38s var(--ease-apple)}
.seg--dark[data-show="b"]::before{transform:translateX(100%)}
.seg--dark .seg__opt{position:relative;z-index:1;color:rgba(235,235,245,.6)}
.seg--dark .seg__opt.is-active{background:none;box-shadow:none;color:#fff}
```

## B19 Kontakt: Panel, Zusagen, Kontaktkacheln

```html
<div class="contact__grid">
  <div class="panel">
    <h3>Erstgespräch buchen</h3>
    <p class="panel__sub">Sie gehen mit drei Dingen raus:</p>
    <ol class="offer-list">
      <li><span class="offer-list__n">1</span><span><b>[3 bis 5 konkrete Tipps]</b> [zum Sofort-Umsetzen]</span></li>
      <li><span class="offer-list__n">2</span><span><b>[…]</b> […]</span></li>
      <li><span class="offer-list__n">3</span><span><b>[…]</b> […]</span></li>
    </ol>
    <div class="cal-frame">[Buchungskalender oder Formular]</div>
  </div>
  <div class="contact__side">
    <div class="contacts">
      <a class="crow" href="tel:[NUMMER]"><span class="crow__ic">[TELEFON]</span><span class="crow__lb">Anrufen</span><span class="crow__vl">[Nummer]</span></a>
      <a class="crow" href="mailto:[MAIL]"><span class="crow__ic">[MAIL]</span><span class="crow__lb">E-Mail</span><span class="crow__vl">[Adresse]</span></a>
    </div>
    <div class="panel"><h3>Oder schreiben Sie mir</h3><p class="panel__sub">Antwort werktags innerhalb von 24 Stunden.</p>[Formular B17]</div>
  </div>
</div>
```

```css
.contact__note{width:min(360px,100%);margin:22px auto 0}   /* .inote--flat unter der H2 */
.contact__grid{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(0,.92fr);gap:20px;align-items:start;margin-top:var(--sp-head)}
.contact__grid>*{min-width:0}
.contact__side{display:flex;flex-direction:column;gap:20px}
.panel{position:relative;background:var(--bg-soft);border-radius:var(--r-xl);padding:clamp(24px,3vw,34px)}
.panel :is(h2,h3){font-size:1.5rem;letter-spacing:-.03em;margin-bottom:6px}
.panel__sub{color:var(--dim);font-size:.98rem;margin-bottom:18px}
.offer-list{list-style:none;margin:0 0 22px;display:grid;gap:10px}
.offer-list li{display:flex;align-items:flex-start;gap:12px;font-size:.98rem;color:var(--dim);line-height:1.5}
.offer-list b{color:var(--ink);font-weight:620}
.offer-list__n{flex:none;display:flex;align-items:center;justify-content:center;width:24px;height:24px;margin-top:1px;border-radius:50%;
  background:#fff;color:var(--brand);font-size:.8rem;font-weight:700;box-shadow:0 1px 2px rgba(14,17,34,.08)}
.contacts{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.crow{display:flex;flex-direction:column;gap:2px;padding:20px 20px 18px;background:var(--bg-soft);border-radius:var(--r-lg);
  transition:background var(--dur-ui),transform var(--dur-ui) var(--ease-apple),box-shadow var(--dur-ui)}
@media(hover:hover){.crow:hover{background:#fff;box-shadow:var(--elev-2);transform:translateY(-2px)}}
.crow__ic{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#fff;color:var(--brand);
  margin-bottom:14px;box-shadow:0 1px 2px rgba(14,17,34,.06)}
.crow__lb{font-size:.84rem;color:var(--dim-2)}
.crow__vl{font-weight:650;color:var(--ink);font-size:1rem;overflow-wrap:anywhere;letter-spacing:-.01em}
.cal-frame{background:#fff;border-radius:var(--r-lg);padding:20px;box-shadow:var(--elev-1)}
@media(max-width:960px){.contact__grid{grid-template-columns:1fr}}
@media(max-width:480px){
  .contacts{grid-template-columns:1fr;gap:10px}
  .crow{display:grid;grid-template-columns:40px minmax(0,1fr);column-gap:14px;align-items:center;padding:14px 16px}
  .crow__ic{grid-row:span 2;margin-bottom:0}
  .cal-frame{padding:14px}
}
```

Kalender-Tage (falls ein eigener Buchungskalender gebaut wird): Kreise,
`aspect-ratio:1`; freie Tage `rgba(var(--brand-rgb),.09)` mit Markenschrift und
3-px-Punkt darunter; gewählter Tag gefüllt in Marke mit farbigem Schatten;
belegte Tage grau `#B3B7C6`. Zeiten als Liste weißer Felder (Radius 12); die
gewählte Zeit wird dunkel (`--ink`), daneben erscheint „Bestätigen“ in Marke.

## B20 Schlusssatz (Manifest) mit animierten Symbolen

```html
<section class="manifest theme-dark" aria-label="[Unser Versprechen]">
  <div class="container">
    <div class="manifest__inner" data-reveal>
      <p class="manifest__line"><span class="manifest__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><circle class="mi-ping" cx="12" cy="12" r="4" stroke-width="1.7"/><g class="mi-eye"><path d="M2 12s3.8-6.6 10-6.6S22 12 22 12s-3.8 6.6-10 6.6S2 12 2 12Z"/><circle class="mi-iris" cx="12" cy="12" r="3"/></g></svg></span>Mehr <span class="manifest__hl">[Sichtbarkeit.]</span></p>
      <p class="manifest__line"><span class="manifest__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M15.6 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="8.8" cy="7.2" r="3.9"/><g class="mi-join"><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.35a4 4 0 0 1 0 7.75"/></g></svg></span>Mehr <span class="manifest__hl">[Kunden.]</span></p>
      <p class="manifest__line"><span class="manifest__ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><g fill="currentColor"><rect class="mi-bar mi-bar--1" x="2.6" y="13.6" width="4.3" height="7.4" rx="1.5" fill-opacity=".38"/><rect class="mi-bar mi-bar--2" x="9.85" y="11.2" width="4.3" height="9.8" rx="1.5" fill-opacity=".64"/><rect class="mi-bar mi-bar--3" x="17.1" y="8.4" width="4.3" height="12.6" rx="1.5"/></g><path class="mi-rise" d="M19.25 7.2V3.2M17.15 5.3 19.25 3.15 21.35 5.3" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Mehr <span class="manifest__hl">[Umsatz.]</span></p>
    </div>
  </div>
</section>
```

```css
.manifest{position:relative;z-index:2;margin-top:calc(var(--sheet-r) * -1);border-radius:var(--sheet-r) var(--sheet-r) 0 0;
  padding:clamp(80px,10vw,150px) 0 clamp(40px,5vw,70px);text-align:center;
  background:radial-gradient(760px 300px at 50% 46%,rgba(var(--brand-rgb),.2),transparent 70%),var(--bg)}
/* Linksbündiger Block, mittig auf der Seite: So bilden die Symbole auch am
   Desktop eine saubere Spalte (bei zentrierten Zeilen springen sie). */
.manifest__inner{display:inline-flex;flex-direction:column;align-items:flex-start;gap:4px;margin:0 auto;text-align:left}
.manifest__line{display:flex;align-items:center;gap:.28em;
  font-family:var(--font-display);font-weight:700;letter-spacing:-.03em;line-height:1.02;
  font-size:clamp(2.3rem,7.2vw,6rem);color:var(--ink);white-space:nowrap}
.js .manifest__line{opacity:0;transform:translateY(24px)}
.js .manifest__inner.in .manifest__line{opacity:1;transform:none;transition:opacity .8s var(--ease-out),transform .8s var(--ease-out)}
.js .manifest__inner.in .manifest__line:nth-child(2){transition-delay:.18s}
.js .manifest__inner.in .manifest__line:nth-child(3){transition-delay:.36s}
.manifest__hl{color:var(--spark)}
.manifest__ico{flex:none;display:inline-flex;width:.66em;height:.66em;color:var(--spark);transform:translateY(-.02em)}
.manifest__ico svg{width:100%;height:100%;overflow:visible}
.manifest__ico [class*="mi-"]{transform-box:view-box;transform-origin:12px 12px}
.manifest__line:nth-child(2) .manifest__ico{--mi-d:.18s}
.manifest__line:nth-child(3) .manifest__ico{--mi-d:.36s}
.manifest__ico .mi-eye{animation:mi-blink 4.6s var(--ease) var(--mi-d,0s) infinite}
.manifest__ico .mi-iris{animation:mi-iris 4.6s var(--ease) var(--mi-d,0s) infinite}
.manifest__ico .mi-ping{opacity:0;animation:mi-ping 3.1s var(--ease-out) var(--mi-d,0s) infinite}
.manifest__ico .mi-join{transform-origin:18px 12px;animation:mi-join 4.2s var(--ease-out) var(--mi-d,0s) infinite}
.manifest__ico .mi-bar{transform-origin:12px 21px;animation:mi-grow 4.2s var(--ease-out) var(--mi-d,0s) infinite}
.manifest__ico .mi-bar--2{animation-delay:calc(var(--mi-d,0s) + .12s)}
.manifest__ico .mi-bar--3{animation-delay:calc(var(--mi-d,0s) + .24s)}
.manifest__ico .mi-rise{animation:mi-rise 4.2s var(--ease-out) var(--mi-d,0s) infinite}
@keyframes mi-blink{0%,86%,100%{transform:scaleY(1)}90%{transform:scaleY(.08)}94%{transform:scaleY(1)}}
@keyframes mi-iris{0%,100%{transform:scale(1)}50%{transform:scale(.8)}}
@keyframes mi-ping{0%{opacity:.5;transform:scale(.55)}70%,100%{opacity:0;transform:scale(2.1)}}
@keyframes mi-join{0%{opacity:0;transform:translateX(2.6px) scale(.82)}16%,74%{opacity:1;transform:none}88%{opacity:0;transform:none}89%,100%{opacity:0;transform:translateX(2.6px) scale(.82)}}
@keyframes mi-grow{0%{opacity:0;transform:scaleY(.14)}10%,74%{opacity:1}28%,74%{transform:scaleY(1)}88%{opacity:0;transform:scaleY(1)}89%,100%{opacity:0;transform:scaleY(.14)}}
@keyframes mi-rise{0%,16%{opacity:0;transform:translateY(2.5px)}38%,72%{opacity:1;transform:translateY(0)}92%,100%{opacity:0;transform:translateY(-1.8px)}}
/* Symbole laufen erst, wenn der Satz im Bild ist. */
.js .manifest__ico [class*="mi-"]{animation-play-state:paused}
.js .manifest__inner.in .manifest__ico [class*="mi-"]{animation-play-state:running}
/* Telefon: Größe aus der längsten Zeile samt Symbol gerechnet (bei Jungline
   rund 9,4 Schriftgrößen, Teiler 9,8). Für jeden Kunden NEU MESSEN. */
@media(max-width:640px){
  .manifest{text-align:left}
  .manifest__inner{display:flex;gap:6px}
  .manifest__line{gap:.3em;font-size:min(calc((100vw - 2 * var(--gutter)) / 9.8),2.7rem)}
}
```

## B21 Footer und mobile Aktionsleiste

```html
<footer class="footer theme-dark">
  <div class="container">
    <div class="footer__top">
      <div>
        <a href="/" class="brand">…wie Navigation…</a>
        <p class="footer__claim"><b>[Claim in einem Satz.]</b> [Ein Satz, was Sie tun.]</p>
        <!-- optional: Querverweis auf zweites Angebot -->
        <a class="footer__cross" href="[LINK]"><span class="footer__cross-ic">[SYMBOL]</span><span class="footer__cross-tx"><small>Ich biete außerdem</small><b>[Zweites Angebot]</b></span>[PFEIL]</a>
      </div>
      <div class="footer__cols">
        <div class="fcol"><h3>[Bereich]</h3><a href="…">…</a></div>
        <div class="fcol"><h3>Kontakt</h3><a href="tel:…">…</a><a href="mailto:…">…</a><span>[Straße]<br>[PLZ Ort]</span></div>
        <div class="fcol"><h3>Rechtliches</h3><a href="/impressum/">Impressum</a><a href="/datenschutz/">Datenschutz</a></div>
      </div>
    </div>
    <div class="footer__bottom"><span>© <span id="year">2026</span> [Firma]. Alle Rechte vorbehalten.</span><span>[Leistung · Region]</span></div>
  </div>
</footer>

<div class="mcta" id="mcta">
  <a href="#termin" class="btn btn--primary">[Kostenloses Erstgespräch]</a>
  <a href="tel:[NUMMER]" class="btn mcta__tel" aria-label="Anrufen">[TELEFON-SYMBOL]</a>
</div>
```

```css
/* Der Footer ist selbst ein Sheet. Endet die Seite mit dem dunklen
   Schlusssatz, gehen beide ohne Kante ineinander über. */
.footer{position:relative;z-index:5;margin-top:calc(var(--sheet-r) * -1);border-radius:var(--sheet-r) var(--sheet-r) 0 0;
  padding:clamp(64px,8vw,110px) 0 34px;background:var(--bg);color:var(--dim)}
main:has(> .manifest:last-child) + .footer{margin-top:0;border-radius:0;padding-top:clamp(40px,5vw,72px)}
.footer__top{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,2fr);gap:48px clamp(40px,6vw,96px);
  padding-bottom:clamp(40px,5vw,64px);border-bottom:1px solid var(--border)}
.footer .brand{color:var(--ink)}
.footer .brand__tile{box-shadow:none}
.footer__claim{margin-top:18px;max-width:36ch;font-size:.96rem;line-height:1.6;color:var(--dim)}
.footer__claim b{color:var(--ink);font-weight:600}
.footer__cols{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:32px}
.fcol h3{font-family:var(--font-text);font-size:.86rem;font-weight:650;color:var(--ink);margin-bottom:14px}
.fcol a,.fcol span{display:block;font-size:.93rem;line-height:1.45;color:var(--dim);padding:5px 0}
.fcol a{transition:color .2s}
.fcol a:hover{color:var(--ink)}
.footer__bottom{display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px 28px;padding-top:26px;font-size:.84rem;color:var(--dim-2)}
.footer__cross{display:inline-flex;align-items:center;gap:12px;margin-top:26px;padding:12px 16px 12px 12px;border-radius:16px;
  background:rgba(255,255,255,.06);border:1px solid var(--border);color:var(--ink);
  transition:background var(--dur-ui),transform var(--dur-ui) var(--ease-apple)}
.footer__cross:hover{background:rgba(255,255,255,.1);transform:translateY(-2px)}
.footer__cross-ic{flex:none;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;
  background:rgba(var(--brand-on-dark-rgb),.14);color:var(--brand-on-dark)}
.footer__cross-tx{display:flex;flex-direction:column;gap:1px}
.footer__cross-tx small{font-size:.78rem;color:var(--dim-2)}
.footer__cross-tx b{font-size:.96rem;font-weight:650;letter-spacing:-.015em}
@media(max-width:960px){
  .footer__top{grid-template-columns:1fr}
  .footer__cols{grid-template-columns:repeat(2,minmax(0,1fr))}
  .footer{padding-bottom:calc(34px + 84px + env(safe-area-inset-bottom,0px))}   /* Platz für die Aktionsleiste */
}
/* Telefon: Footer-Links mit echter Tippfläche (44 px). */
@media(max-width:640px){
  .fcol h3{margin-bottom:6px}
  .fcol a{padding:10px 0}
  .footer__cross{display:flex;width:100%}
  .footer__bottom{flex-direction:column;gap:6px}
}

/* Mobile Aktionsleiste: Milchglas ist hier erlaubt, die Leiste selbst
   bewegt sich nur beim Ein- und Ausblenden. */
.mcta{position:fixed;left:12px;right:12px;bottom:calc(12px + env(safe-area-inset-bottom,0px));z-index:var(--z-mcta);
  display:none;gap:8px;padding:7px;border-radius:24px;
  background:rgba(255,255,255,.8);-webkit-backdrop-filter:blur(24px) saturate(180%);backdrop-filter:blur(24px) saturate(180%);
  box-shadow:0 20px 44px -18px rgba(14,17,34,.45),0 0 0 1px rgba(14,17,34,.06);
  visibility:hidden;transform:translateY(140%);transition:transform .45s var(--ease-out),visibility 0s .45s}
.mcta.show{visibility:visible;transform:none;transition:transform .45s var(--ease-out)}
.mcta .btn--primary{flex:1;padding:14px 18px}
.mcta__tel{flex:none;width:52px;padding:0!important;background:#F1F2F6!important;color:#0E1122!important}
@media(max-width:960px){.mcta{display:flex}}
```

```js
/* Aktionsleiste: nach dem Hero zeigen, im Kontaktbereich und im Footer
   ausblenden (dort wäre sie eine zweite Aufforderung bzw. verdeckte die
   letzte Footer-Zeile dauerhaft). */
(function(){
  const bar = document.getElementById('mcta');
  if(!bar) return;
  const kontakt = document.getElementById('kontakt') || document.getElementById('termin');
  const footer = document.querySelector('.footer');
  let kSicht = false, fSicht = false;
  const update = () => bar.classList.toggle('show', scrollY > 640 && !kSicht && !fSicht);
  if(kontakt) new IntersectionObserver(es => { kSicht = es[0].isIntersecting; update(); }, {rootMargin:'0px 0px -20% 0px'}).observe(kontakt);
  if(footer) new IntersectionObserver(es => { fSicht = es[0].isIntersecting; update(); }).observe(footer);
  addEventListener('scroll', update, {passive:true});
  update();
})();
```

## B22 Wisch-Galerie auf dem Telefon

Jedes Kartenraster mit der Zusatzklasse `.m-gallery` wird auf dem Telefon zur
waagerechten Reihe wie auf apple.com: Eine Karte steht ganz im Bild, die
nächste lugt herein.

```css
.m-dots{display:none}
@media(max-width:640px){
  .m-gallery{display:flex;gap:12px;overflow-x:auto;overscroll-behavior-x:contain;scroll-snap-type:x mandatory;
    margin-left:calc(var(--gutter) * -1);margin-right:calc(var(--gutter) * -1);
    padding:6px var(--gutter) 26px;scroll-padding-inline:var(--gutter);
    scrollbar-width:none;-webkit-overflow-scrolling:touch}
  .m-gallery::-webkit-scrollbar{display:none}
  .m-gallery>*{flex:0 0 84%;min-width:0;scroll-snap-align:start;scroll-snap-stop:always}
  .m-dots{display:flex;justify-content:center;gap:8px;margin-top:-6px}
  .m-dots i{width:7px;height:7px;border-radius:50%;background:rgba(14,17,34,.16);
    transition:background var(--dur-ui) var(--ease-apple),transform var(--dur-ui) var(--ease-apple)}
  .m-dots i.is-on{background:var(--ink);transform:scale(1.1)}
  .theme-dark .m-dots i{background:rgba(255,255,255,.22)}
  .theme-dark .m-dots i.is-on{background:#fff}
}
```

```js
document.querySelectorAll('.m-gallery').forEach(reihe => {
  const karten = reihe.children;
  if(karten.length < 2) return;
  const punkte = document.createElement('div');
  punkte.className = 'm-dots';
  punkte.setAttribute('aria-hidden', 'true');
  for(let i = 0; i < karten.length; i++) punkte.appendChild(document.createElement('i'));
  reihe.after(punkte);
  let aktiv = -1, geplant = false;
  const setze = () => {
    geplant = false;
    const max = reihe.scrollWidth - reihe.clientWidth;
    const schritt = karten[1].offsetLeft - karten[0].offsetLeft;
    let n = !schritt || max < 4 ? 0 : (reihe.scrollLeft >= max - 4 ? karten.length - 1 : Math.round(reihe.scrollLeft / schritt));
    n = Math.max(0, Math.min(karten.length - 1, n));
    if(n === aktiv) return;
    if(aktiv > -1) punkte.children[aktiv].classList.remove('is-on');
    punkte.children[n].classList.add('is-on');
    aktiv = n;
  };
  reihe.addEventListener('scroll', () => { if(!geplant){ geplant = true; requestAnimationFrame(setze); } }, {passive:true});
  setze();
});
```

## B23 Unterseiten: Seitenkopf, Fließtext, Kacheln, Schritte, CTA-Band

```html
<header class="pagehead pagehead--center">
  <div class="container pagehead__inner">
    <nav class="breadcrumb" aria-label="Brotkrumen"><a href="/">Start</a><span class="sep">›</span><a href="/leistungen/">Leistungen</a><span class="sep">›</span><span aria-current="page">[Seite]</span></nav>
    <h1>[Thema] <span class="hl">[für Zielgruppe]</span></h1>
    <p class="lead">[Ein, zwei Sätze.]</p>
    <div class="cta-row"><a class="btn btn--primary btn--lg" href="#termin">[Primär]</a><a class="btn btn--ghost btn--lg" href="#kontakt">[Sekundär]</a></div>
  </div>
</header>
<section class="section">…erste Fläche, wird automatisch Sheet…</section>
```

```css
.pagehead{
  --bg:var(--navy-900);--surface:rgba(255,255,255,.06);--surface-2:rgba(255,255,255,.09);
  --border:rgba(255,255,255,.11);--border-strong:rgba(255,255,255,.2);
  --ink:#F4F5FB;--dim:rgba(232,235,248,.76);--dim-2:rgba(232,235,248,.58);--soft:rgba(232,235,248,.5);
  --link:var(--brand-on-dark);
  position:relative;isolation:isolate;overflow:hidden;color:var(--ink);
  padding:calc(var(--nav-h) + clamp(36px,5.4vw,84px)) 0 calc(var(--sheet-r) + clamp(52px,6vw,96px));
  background:
    radial-gradient(900px 520px at 88% -4%,rgba(var(--brand-rgb),.36),transparent 62%),
    radial-gradient(620px 420px at -4% 106%,rgba(var(--spark-rgb),.09),transparent 62%),
    linear-gradient(180deg,#090C1D 0%,#0C1027 100%);
}
.pagehead__inner{max-width:860px}
.pagehead h1{font-size:clamp(2.5rem,5.6vw,4.6rem);font-weight:700;letter-spacing:-.03em;line-height:1;margin:22px 0 0;color:#fff}
.pagehead .lead{margin-top:24px;max-width:58ch;color:var(--dim);font-size:clamp(1.08rem,1.4vw,1.26rem)}
.pagehead .cta-row{margin-top:34px}
.pagehead .btn--ghost{color:#fff;border-color:rgba(255,255,255,.26)}
.pagehead .btn--ghost:hover{border-color:#fff;background:rgba(255,255,255,.06)}
.pagehead--center .pagehead__inner{margin:0 auto;text-align:center}
.pagehead--center .breadcrumb,.pagehead--center .cta-row{justify-content:center}
.pagehead--center .lead{margin-left:auto;margin-right:auto}
/* Deutsche Komposita: "Unternehmensprofil" misst gut 9 Schriftgrößen. Diese
   Größe lässt es ab 360 px ganz stehen, Längeres trennt am &shy;. */
@media(max-width:640px){
  .pagehead h1{font-size:clamp(2rem,9.2vw,2.6rem);margin-top:18px}
  .pagehead .lead{margin-top:18px;font-size:1.06rem}
}
/* Die erste Fläche nach dem Seitenkopf ist automatisch ein Sheet. */
.pagehead + .section{position:relative;z-index:2;margin-top:calc(var(--sheet-r) * -1);border-radius:var(--sheet-r) var(--sheet-r) 0 0;background:var(--bg)}

.breadcrumb{display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px;font-size:.88rem;color:var(--dim-2)}
.breadcrumb a{display:inline-flex;align-items:center;min-height:24px;color:var(--dim);transition:color .2s}
.breadcrumb a:hover{color:var(--ink)}
.breadcrumb .sep{opacity:.5}
.breadcrumb [aria-current]{color:var(--ink);font-weight:500}

/* Fließtext */
.prose{max-width:720px;margin-left:auto;margin-right:auto}
.prose h2{font-size:clamp(1.7rem,3vw,2.4rem);letter-spacing:-.025em;margin:56px 0 16px}
.prose h2:first-child{margin-top:0}
.prose h3{font-size:1.28rem;letter-spacing:-.02em;margin:34px 0 10px}
.prose p{color:var(--dim);font-size:1.08rem;line-height:1.75;margin-bottom:18px}
.prose strong{color:var(--ink);font-weight:620}
.prose a{color:var(--link);text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:3px}
.prose a:hover{text-decoration-thickness:2px}
.prose ul,.prose ol{margin:0 0 20px;padding-left:22px;display:grid;gap:10px}
.prose li{color:var(--dim);font-size:1.06rem;line-height:1.7;padding-left:4px}
.prose li::marker{color:var(--brand)}
.prose blockquote{border-left:2px solid var(--brand);margin:24px 0;padding:4px 0 4px 20px;color:var(--ink);font-size:1.1rem}
.callout{display:flex;align-items:flex-start;gap:14px;margin:28px 0;padding:22px 24px;border-radius:var(--r-lg);
  background:var(--bg-soft);color:var(--dim);font-size:1rem;line-height:1.7}
.callout svg{flex:none;color:var(--brand);margin-top:3px}
.callout b{color:var(--ink);font-weight:620}

/* Text neben Illustration (Mini-Version des Beweis-Objekts) */
.prose-illu{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(280px,380px);gap:clamp(36px,5vw,72px);align-items:center;max-width:1060px;margin:0 auto}
.prose-illu.rev{grid-template-columns:minmax(280px,380px) minmax(0,1.15fr)}
.prose-illu.rev .illu{order:-1}
.illu{position:relative;border-radius:var(--r-xl);padding:18px;background:#fff;
  box-shadow:0 0 0 1px rgba(14,17,34,.05),0 30px 60px -34px rgba(14,17,34,.38)}
.illu__caption{margin-top:14px;font-size:.8rem;color:var(--dim-2);text-align:center;line-height:1.5}
@media(max-width:860px){.prose-illu,.prose-illu.rev{grid-template-columns:1fr;max-width:560px}.prose-illu.rev .illu{order:0}}

/* Inhalts-Kacheln: auf grauen Abschnitten weiß, auf weißen grau */
.tiles{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:var(--sp-head)}
.tiles--2{grid-template-columns:repeat(2,minmax(0,1fr))}
.tile{display:flex;flex-direction:column;gap:10px;padding:30px 28px 28px;border-radius:var(--r-xl);background:var(--bg-soft);color:var(--ink);
  transition:transform var(--dur-ui) var(--ease-apple),box-shadow var(--dur-ui),background-color var(--dur-ui)}
.section--soft .tile{background:#fff}
@media(hover:hover){.tile:hover{transform:translateY(-3px);background:#fff;box-shadow:var(--elev-2)}}
.tile__ic{display:flex;align-items:center;justify-content:center;flex:none;width:46px;height:46px;margin-bottom:8px;border-radius:13px;
  background:#fff;color:var(--brand);box-shadow:0 1px 2px rgba(0,0,0,.06),0 6px 16px -10px rgba(var(--brand-rgb),.5)}
.section--soft .tile__ic{background:rgba(var(--brand-rgb),.08);box-shadow:none}
.tile h3{font-size:1.22rem;letter-spacing:-.025em;line-height:1.25}
.tile p{color:var(--dim);font-size:.99rem;line-height:1.62}
/* Verweis-Kachel: gefüllt in der Marke, großer Pfeil-Text unten */
.tile--link,.section--soft .tile--link{background:var(--brand);color:#fff;justify-content:space-between}
.tile--link p{color:rgba(255,255,255,.84)}
.tile--link .tile__ic{background:rgba(255,255,255,.16);color:#fff;box-shadow:none}
.tile__go{display:inline-flex;align-items:center;gap:8px;margin-top:auto;padding-top:14px;font-family:var(--font-display);
  font-size:1.18rem;font-weight:700;letter-spacing:-.022em}
.tile__go svg{transition:transform var(--dur-ui) var(--ease-apple)}
@media(hover:hover){
  .tile--link:hover{background:var(--brand);box-shadow:0 22px 44px -22px rgba(var(--brand-rgb),.8)}
  .tile--link:hover .tile__go svg{transform:translateX(4px)}
}
@media(max-width:960px){.tiles{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.tiles,.tiles--2{grid-template-columns:1fr}}

/* Schrittfolge: nebeneinander, Telefon senkrecht mit Verbindungslinie */
.step-path{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:28px;max-width:1100px;margin:var(--sp-head) auto 0}
.step-path__item{position:relative;display:flex;flex-direction:column;gap:18px}
.step-path__item:not(:last-child)::after{content:"";position:absolute;top:26px;left:64px;right:-16px;height:1px;
  background:linear-gradient(90deg,var(--border-strong),transparent)}
.step-path__dot{position:relative;width:52px;height:52px;border-radius:16px;display:flex;align-items:center;justify-content:center;
  background:var(--bg-soft);color:var(--brand)}
.step-path__num{position:absolute;top:-8px;right:-8px;min-width:22px;height:22px;padding:0 5px;border-radius:999px;
  background:var(--ink);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.74rem;font-weight:700}
.step-path__body h3{font-size:1.2rem;letter-spacing:-.025em;margin-bottom:8px}
.step-path__body p{color:var(--dim);font-size:.99rem;line-height:1.6}
@media(max-width:700px){
  .step-path{grid-template-columns:1fr;gap:0}
  .step-path__item{flex-direction:row;gap:16px;padding:0 0 26px}
  .step-path__item:not(:last-child)::after{top:62px;bottom:6px;left:26px;right:auto;width:1px;height:auto;
    background:linear-gradient(180deg,var(--border-strong),transparent)}
}

/* Link-Karten (Ratgeber, Unterseiten) */
.linkgrid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:var(--sp-head)}
.linkcard{display:flex;flex-direction:column;gap:10px;padding:28px 26px 26px;border-radius:var(--r-xl);background:var(--bg-soft);
  transition:transform .45s var(--ease-out),box-shadow .45s var(--ease-out),background .3s}
@media(hover:hover){.linkcard:hover{transform:translateY(-4px);background:#fff;box-shadow:var(--elev-3)}}
.linkcard__tag{font-size:.84rem;font-weight:600;color:var(--brand)}
.linkcard :is(h2,h3){font-size:1.3rem;letter-spacing:-.025em;line-height:1.2}
.linkcard p{color:var(--dim);font-size:.98rem;line-height:1.6;flex:1}
.linkcard__more{margin-top:8px;color:var(--link);font-weight:600;font-size:.95rem;display:inline-flex;align-items:center;gap:7px}
.linkcard__more svg{transition:transform .3s var(--ease)}
.linkcard:hover .linkcard__more svg{transform:translateX(4px)}
@media(max-width:960px){.linkgrid{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.linkgrid{grid-template-columns:1fr}}

/* Weiterführende Links */
.related{max-width:900px;margin:var(--sp-head) auto 0;padding-top:28px;border-top:1px solid var(--border)}
.related__title{font-size:.95rem;font-weight:650;color:var(--ink);margin-bottom:14px}
.related__list{display:flex;flex-wrap:wrap;gap:10px 28px}
.related__list a{display:inline-flex;align-items:center;gap:8px;color:var(--link);font-weight:600;font-size:.98rem;transition:gap var(--dur-ui) var(--ease-apple)}
.related__list a:hover{gap:11px}

/* Abschlussband jeder Unterseite */
.ctaband{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:28px 48px;
  padding:clamp(34px,5vw,64px);border-radius:var(--r-xl);
  background:radial-gradient(700px 360px at 100% 0%,rgba(var(--brand-rgb),.4),transparent 65%),var(--navy-900)}
.ctaband h2{font-size:clamp(1.8rem,3.4vw,2.8rem);color:#fff;letter-spacing:-.025em}
.ctaband p{margin-top:14px;color:rgba(232,235,248,.72);max-width:52ch;font-size:1.06rem;line-height:1.6}
.ctaband .cta-row{justify-content:flex-end}
@media(max-width:860px){.ctaband{grid-template-columns:1fr}.ctaband .cta-row{justify-content:flex-start}}
@media(max-width:520px){.ctaband .cta-row .btn{width:100%}}
```

## B24 Geräte-Attrappen und Arbeitsprobe

```html
<div class="devices">
  <figure class="device-mac">
    <div class="device-mac__bar"><i></i><i></i><i></i><span>[SCHLOSS-SYMBOL] [domain.de]</span></div>
    <img src="/probe-desktop.webp" alt="[Startseite von Firma am Rechner]" width="1440" height="900">
  </figure>
  <figure class="device-phone"><img src="/probe-mobil.webp" alt="[dieselbe Seite auf dem Telefon]" width="390" height="844"></figure>
</div>
```

```css
.devices{position:relative;padding:0 6% 9% 0}
.device-mac{position:relative;margin:0;border-radius:16px;overflow:hidden;background:#1B1D24;
  box-shadow:0 0 0 1px rgba(255,255,255,.1),0 60px 120px -40px rgba(0,0,0,.9),0 24px 50px -24px rgba(0,0,0,.6)}
.device-mac__bar{display:flex;align-items:center;gap:7px;height:34px;padding:0 14px;background:#24262E;border-bottom:1px solid rgba(255,255,255,.06)}
.device-mac__bar i{width:11px;height:11px;border-radius:50%;background:#FF5F57}
.device-mac__bar i:nth-child(2){background:#FEBC2E}
.device-mac__bar i:nth-child(3){background:#28C840}
.device-mac__bar span{flex:1;text-align:center;margin-right:52px;font-family:var(--font-sys);font-size:.74rem;color:rgba(255,255,255,.55)}
.device-phone{position:absolute;right:0;bottom:0;width:27%;margin:0;padding:6px;border-radius:30px;background:#16171C;
  box-shadow:0 0 0 1px rgba(255,255,255,.14),0 40px 70px -24px rgba(0,0,0,.9);animation:float 9s ease-in-out 1.6s infinite}
.device-phone img{width:100%;height:auto;border-radius:24px}
@keyframes float{0%,100%{transform:translate3d(0,0,0)}50%{transform:translate3d(0,-10px,0)}}

/* Arbeitsprobe auf hellem Grund: Beim Überfahren fährt der Ausschnitt
   langsam die ganze Seite ab, dieselbe Geste wie beim Durchsehen. */
.case__browser{border-radius:22px;overflow:hidden;background:#fff;box-shadow:0 0 0 1px rgba(14,17,34,.08),0 50px 100px -50px rgba(14,17,34,.45)}
.case__bar{display:flex;align-items:center;gap:7px;height:38px;padding:0 16px;background:#F3F4F7;border-bottom:1px solid #E6E8EE}
.case__bar i{width:11px;height:11px;border-radius:50%;background:#D5D8E1}
.case__viewport{position:relative;height:clamp(320px,38vw,520px);overflow:hidden;cursor:ns-resize}
.case__scroll{width:100%;height:auto;transition:transform 9s cubic-bezier(.35,0,.25,1)}
@media(hover:hover){
  .case__viewport:hover .case__scroll,.case__viewport:focus .case__scroll{transform:translateY(calc(-100% + clamp(320px,38vw,520px)))}
}
@media(max-width:640px){
  .device-phone{border-radius:22px;padding:4px}.device-phone img{border-radius:18px}
  .device-mac{border-radius:12px}.device-mac__bar{height:26px}.device-mac__bar span{display:none}
}
```

## B25 Preiskarten

```html
<div class="prices">
  <div class="price price--top">
    <h3>[Paket A]</h3><p class="price__for">[Für wen, ein Satz.]</p>
    <div class="price__amount"><span class="price__ab">ab</span><span class="price__val">[1.000 €]</span></div>
    <p class="price__note">[einmalig, Festpreis nach Erstgespräch]</p>
    <ul><li>[Leistung]</li><li>[Leistung]</li></ul>
    <a class="btn btn--primary" href="#termin">[Erstgespräch buchen]</a>
  </div>
  <div class="price">…</div>
</div>
```

```css
.prices{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin:var(--sp-head) auto 0;max-width:880px}
.price{position:relative;display:flex;flex-direction:column;padding:36px 32px 32px;border-radius:var(--r-xl);background:var(--bg-soft)}
.price--top{background:var(--navy-900);color:#F4F5FB;--ink:#F4F5FB;--dim:rgba(232,235,248,.74);--dim-2:rgba(232,235,248,.56);--border:rgba(255,255,255,.12);
  box-shadow:0 40px 80px -40px rgba(var(--navy-900-rgb),.7)}
.price h3{font-size:1.45rem;letter-spacing:-.03em;color:var(--ink)}
.price__for{color:var(--dim);font-size:.96rem;margin-top:6px;line-height:1.5}
.price__amount{display:flex;align-items:baseline;gap:8px;margin:28px 0 4px}
.price__ab{color:var(--dim-2);font-size:.95rem;font-weight:500}
.price__val{font-family:var(--font-display);font-size:clamp(2.6rem,4.4vw,3.4rem);font-weight:700;letter-spacing:-.03em;line-height:1;color:var(--ink)}
.price__note{color:var(--dim-2);font-size:.86rem;line-height:1.5;margin-bottom:24px}
.price ul{list-style:none;display:grid;gap:10px;margin-bottom:30px;padding-top:22px;border-top:1px solid var(--border);flex:1;align-content:start}
.price li{position:relative;padding-left:18px;color:var(--dim);font-size:.96rem;line-height:1.5}
.price li::before{content:"";position:absolute;left:2px;top:.6em;width:6px;height:6px;border-radius:50%;background:var(--brand)}
.price--top li::before{background:var(--spark)}
.price .btn{width:100%}
@media(max-width:980px){.prices{grid-template-columns:1fr;max-width:520px}}
```

## B26 Startscreen (nur bei zwei getrennten Angeboten)

Liegt **über** der Startseite, nicht an ihrer Stelle: „/“ bleibt inhaltlich die
Hauptseite (Rankings bleiben). Ein Inline-Skript direkt nach `<body>` setzt
`html.zweig-wahl` nur, wenn: Pfad ist „/“, keine gespeicherte Wahl im
`localStorage`, kein `#anker` in der Adresse, Besucher kommt nicht aus einer
Suchmaschine (`document.referrer`), sonst nie. `?wahl=neu` erzwingt ihn zum
Vorführen. Ohne JavaScript erscheint er gar nicht.

```css
.chooser{display:none}
.zweig-wahl,.zweig-wahl body{overflow:hidden}
.zweig-wahl .chooser{display:flex;position:fixed;inset:0;z-index:var(--z-chooser);align-items:center;justify-content:center;
  padding:32px 20px;overflow-y:auto;background:#F5F5F7;   /* deckend statt Milchglas: spart gemessen 8 Bilder/s */
  animation:chooserIn var(--dur-ui) var(--ease-apple) both}
.zweig-wahl .chooser.is-closing{animation:chooserOut var(--dur-move) var(--ease-apple) both;pointer-events:none}
.chooser__inner{margin:auto;width:100%;max-width:820px;text-align:center;display:flex;flex-direction:column;align-items:center}
.chooser__title{margin-top:26px;font-size:clamp(1.9rem,5.2vw,3.05rem);letter-spacing:-.03em}
.chooser__sub{margin:15px auto 0;max-width:540px;color:var(--dim);font-size:1.02rem;line-height:1.6}
.chooser__grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;width:100%;margin-top:40px}
.chooser__card{display:flex;flex-direction:column;align-items:flex-start;gap:9px;text-align:left;padding:26px 24px 22px;border-radius:24px;
  background:#fff;box-shadow:var(--elev-2);
  transition:transform var(--dur-ui) var(--ease-apple),box-shadow var(--dur-ui) var(--ease-apple);
  animation:chIn .66s var(--ease-apple) both;animation-delay:calc(.34s + var(--i,0)*.07s)}
.chooser__card:hover{transform:translateY(-4px);box-shadow:var(--elev-3)}
.chooser__card-ic{width:52px;height:52px;border-radius:16px;display:flex;align-items:center;justify-content:center;
  background:rgba(var(--brand-rgb),.09);color:var(--brand);transition:background var(--dur-ui),color var(--dur-ui)}
.chooser__card:hover .chooser__card-ic{background:var(--brand);color:#fff}
.chooser__card-tt{font-family:var(--font-display);font-size:1.35rem;font-weight:700;letter-spacing:-.03em}
.chooser__card-tx{color:var(--dim);font-size:.94rem;line-height:1.6}
.chooser__skip{margin-top:30px;padding:10px 18px;border-radius:999px;color:var(--dim-2);font-size:.9rem}
@keyframes chooserIn{from{opacity:0}to{opacity:1}}
@keyframes chooserOut{from{opacity:1;transform:none}to{opacity:0;transform:translateY(-18px)}}
/* Winzige Wege sind der Punkt: Was aus "fast schon da" kommt, wirkt
   materiell; was von weit her einfliegt, wirkt wie ein Effekt. */
@keyframes chIn{from{opacity:0;transform:translate3d(0,14px,0) scale(.985)}to{opacity:1;transform:none}}
@media(max-width:700px){.chooser__grid{grid-template-columns:1fr;gap:12px;margin-top:26px}}
```

Solange der Startscreen offen ist, pausiert die Hero-Szene dahinter
(`.zweig-wahl .hero *{animation-play-state:paused!important}`) und die
Aktionsleiste ist ausgeblendet.

## B27 Bewegungssystem: Einblenden, Linien-Symbole, Touch, reduzierte Bewegung, Druck

```css
/* Nur mit html.js versteckt, ohne JS ist alles sofort sichtbar. Kein
   dauerhaftes will-change (hielte jedes Element als eigene Grafikebene). */
.js [data-reveal]{opacity:0;transform:translateY(34px);transition:opacity .9s var(--ease-out),transform .9s var(--ease-out)}
.js [data-reveal].in{opacity:1;transform:none}
.js [data-reveal][data-d="1"]{transition-delay:.08s}
.js [data-reveal][data-d="2"]{transition-delay:.16s}
.js [data-reveal][data-d="3"]{transition-delay:.24s}
.bento__tile[data-reveal]{transform:translateY(20px)}
/* Nach dem Einblenden gelten wieder die eigenen Übergänge der Kachel. */
.js .bento__tile.in{transition:opacity .9s var(--ease-out),transform .5s var(--ease-out),box-shadow .5s var(--ease-out),background .3s}
.reveal-off [data-reveal]{opacity:1!important;transform:none!important;transition:none!important}

/* Linien-Symbole zeichnen sich beim ersten Sichtkontakt selbst. Sichtbarer
   Grundzustand ist immer das fertige Symbol. */
.lico [data-draw]{stroke-dasharray:var(--len,1);stroke-dashoffset:0}
.lico--pending [data-draw]{stroke-dashoffset:var(--len,1)}
.lico--draw [data-draw]{animation:lico-draw .62s var(--ease-apple) both;animation-delay:calc(var(--i,0)*.055s)}
@keyframes lico-draw{from{stroke-dashoffset:var(--len,1)}to{stroke-dashoffset:0}}
.lico--pending [data-pop]{opacity:0}
.lico--draw [data-pop]{animation:lico-pop .5s var(--ease-apple) both;animation-delay:calc(var(--i,0)*.055s);transform-box:fill-box;transform-origin:center}
@keyframes lico-pop{from{opacity:0;transform:scale(.55)}to{opacity:1;transform:scale(1)}}
.lico{transition:transform var(--dur-ui) var(--ease-apple)}
@media(hover:hover){.lico-host:hover .lico{transform:scale(1.08)}}

/* Hover nur mit Zeiger, sonst "kleben" Zustände auf Touch. Dafür antworten
   Karten auf den Finger: kurzes Eindrücken wie bei nativen iOS-Zellen. */
@media(hover:none){
  .btn--primary:hover,.btn--ghost:hover,.btn--glass:hover{transform:none}
  :is(.bento__tile,.linkcard,.crow,.tile,.tilelist__item,.chooser__card):active,
  .js :is(.bento__tile,.linkcard,.tile).in:active{transform:scale(.982);transition:transform var(--dur-tap) var(--ease-apple)}
  .faq__q:active .faq__icon{transform:scale(.88)}
  .hero__live:active,.new-badge:active{transform:scale(.97)}
}

/* Wer Bewegung abbestellt hat, bekommt keine. Endzustände stehen fest. */
@media(prefers-reduced-motion:reduce){
  *,*::before,*::after{animation:none!important;scroll-behavior:auto!important}
  [data-reveal]{opacity:1!important;transform:none!important;transition:none!important}
  .lico [data-draw]{stroke-dashoffset:0!important}
  .lico [data-pop]{opacity:1!important;transform:none!important}
  .manifest__line{opacity:1!important;transform:none!important}
  .ticker__track{width:auto;flex-wrap:wrap;justify-content:center}
  .ticker__group{flex-wrap:wrap;justify-content:center;row-gap:8px}
  .ticker__group[aria-hidden="true"]{display:none}
  .case__scroll{transition:none}
}
/* Druck: nichts wartet auf eine Animation */
@media print{
  [data-reveal]{opacity:1!important;transform:none!important}
  .lico [data-draw]{stroke-dashoffset:0!important}
  .nav,.mcta,.mobile-menu,.chooser{display:none!important}
}
```

```js
/* Einblenden beim Scrollen. Der Beobachter allein reicht nicht: Beim
   schnellen Wischen darf der Browser Zwischenzustände auslassen, ein Element
   bekäme nie einen Rückruf und bliebe unsichtbar. Deshalb ein Nachlauf, der
   alles einsammelt, was die Auslöselinie schon überschritten hat, plus ein
   Failsafe für In-App-Browser und Vorschau-Renderer. */
(function(){
  const offen = [...document.querySelectorAll('[data-reveal]')];
  if(!offen.length) return;
  const LINIE = 50;
  const zeigen = el => { const i = offen.indexOf(el); if(i < 0) return; offen.splice(i, 1); el.classList.add('in'); };
  const nachlauf = () => {
    const grenze = innerHeight - LINIE;
    for(let i = offen.length - 1; i >= 0; i--) if(offen[i].getBoundingClientRect().top < grenze) zeigen(offen[i]);
  };
  if(!('IntersectionObserver' in window)){ offen.slice().forEach(zeigen); return; }
  const io = new IntersectionObserver(es => {
    es.forEach(e => { if(e.isIntersecting){ io.unobserve(e.target); zeigen(e.target); } });
    nachlauf();
  }, {threshold:.14, rootMargin:'0px 0px -' + LINIE + 'px 0px'});
  offen.forEach(el => io.observe(el));
  let ruhe = null;
  const angestossen = () => { clearTimeout(ruhe); ruhe = setTimeout(nachlauf, 140); };
  addEventListener('scroll', angestossen, {passive:true});
  addEventListener('resize', angestossen, {passive:true});
  nachlauf();
  // Failsafe: Steht nach 1,6 s ein verstecktes Element SICHTBAR im Bild,
  // arbeitet der Beobachter nicht. Dann alles freischalten.
  setTimeout(() => {
    const vh = innerHeight;
    for(const el of document.querySelectorAll('[data-reveal]:not(.in)')){
      const r = el.getBoundingClientRect();
      if(r.height > 0 && r.top < vh - 80 && r.bottom > 80){ document.documentElement.classList.add('reveal-off'); return; }
    }
  }, 1600);
})();

/* Linien-Symbole: misst jeden Pfad, legt die Länge als --len ab, zeichnet beim
   ersten Sichtkontakt und erneut beim Überfahren des Trägers (Link, Button,
   Kachel). Versteckt wird nur, was dieses Skript aktiv markiert. */
(function(){
  if(matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;
  const SKIP = '.proofcard,.vnc__stage,.manifest__ico,.inote,[data-noanim]';
  const SHAPES = 'path,line,polyline,polygon,circle,ellipse,rect';
  const HOSTS = 'a,button,.tile,.bento__tile,.crow';
  const icons = [], offen = [];
  const play = svg => {
    svg.classList.remove('lico--pending', 'lico--draw');
    void svg.getBoundingClientRect();          // Neustart erzwingen
    svg.classList.add('lico--draw');
  };
  const io = new IntersectionObserver(es => {
    es.forEach(e => { if(!e.isIntersecting) return; io.unobserve(e.target); const i = offen.indexOf(e.target); if(i > -1) offen.splice(i, 1); play(e.target); });
  }, {threshold:.3, rootMargin:'0px 0px -40px 0px'});
  document.querySelectorAll('svg[viewBox="0 0 24 24"]').forEach(svg => {
    if(svg.closest(SKIP) || !svg.getClientRects().length) return;   // unsichtbare später nicht verstecken
    let n = 0;
    svg.querySelectorAll(SHAPES).forEach((el, i) => {
      el.style.setProperty('--i', i);
      if(getComputedStyle(el).stroke !== 'none'){
        let len = 0; try{ len = el.getTotalLength(); }catch(_){}
        if(!isFinite(len) || len <= 0) return;
        el.style.setProperty('--len', len.toFixed(2)); el.setAttribute('data-draw', '');
      } else el.setAttribute('data-pop', '');
      n++;
    });
    if(!n) return;
    svg.classList.add('lico', 'lico--pending');
    icons.push(svg); offen.push(svg); io.observe(svg);
    const host = svg.closest(HOSTS); if(host) host.classList.add('lico-host');
  });
  let ruhe = null;
  addEventListener('scroll', () => { clearTimeout(ruhe); ruhe = setTimeout(() => {
    for(let i = offen.length - 1; i >= 0; i--) if(offen[i].getBoundingClientRect().top < innerHeight - 40){ io.unobserve(offen[i]); play(offen.splice(i, 1)[0]); }
  }, 160); }, {passive:true});
  setTimeout(() => {   // Failsafe wie beim Einblenden
    if(document.querySelector('.lico--draw')) return;
    const stuck = icons.some(s => { const r = s.getBoundingClientRect(); return s.classList.contains('lico--pending') && r.height > 0 && r.top < innerHeight - 60 && r.bottom > 60; });
    if(stuck) icons.forEach(s => s.classList.remove('lico--pending'));
  }, 1800);
  let letzter = null;
  document.addEventListener('pointerover', e => {
    if(e.pointerType === 'touch') return;
    const host = e.target.closest && e.target.closest('.lico-host');
    if(!host || host === letzter) return;
    letzter = host; host.querySelectorAll('svg.lico').forEach(play);
  }, {passive:true});
  document.addEventListener('pointerout', e => { if(letzter && (!e.relatedTarget || !letzter.contains(e.relatedTarget))) letzter = null; }, {passive:true});
})();
```

## B28 Mauszeiger mit Markenbezug (optional)

Über Handlungsknöpfen ein **echter Systemzeiger** als Bild (bei Jungline ein
Kartenpin), kein nachgebautes DIV, das hinter der Maus herläuft. Bild 24 × 32 px
plus Retina-Fassung, Hotspot an der Spitze. Chrome zeigt eigene Zeiger über
32 px nicht an, wenn sie über den Fensterrand ragen.

```css
@media (hover:hover) and (pointer:fine){
  .btn--primary:not(:disabled){
    cursor:url("/cursor.png") 12 29,pointer;
    cursor:image-set(url("/cursor.png") 1x,url("/cursor@2x.png") 2x) 12 29,pointer;
  }
}
```

=== PROMPT ENDET ===
