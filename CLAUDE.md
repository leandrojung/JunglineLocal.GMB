# Jungline Local: Hinweise für Claude

## Schreibregel: keine Gedankenstriche

Verbindlicher Wunsch von Leandro. Gilt für jeden Text, den Besucher oder
Kunden zu sehen bekommen: Seiten und Überschriften, Meta-Titel und
-Beschreibungen, Open-Graph-Angaben, JSON-LD, alt-Texte, aria-Labels,
Formular- und Fehlermeldungen in `src/js/`, Texte in `src/data/`,
E-Mails und Kalendereinträge der Terminbuchung (`public/api/booking/`).

- Kein „—“ (Geviertstrich) und kein „–“ (Halbgeviertstrich), auch nicht als
  `&mdash;` oder `&ndash;`.
- Stattdessen je nach Satz: Komma (Nachsatz), Punkt (neuer Satz),
  Doppelpunkt (Aufzählung oder Erklärung folgt) oder Klammern (Einschub,
  der selbst Kommas enthält).
- Zahlen- und Zeitbereiche mit „bis“: „3 bis 5 Tipps“, „10:00 bis 10:30 Uhr“.
- In Titeln ein Doppelpunkt („Kontakt: Webdesign &amp; Relaunch“), vor dem
  Markennamen „|“ oder „·“ („Impressum | Jungline Local“).
- Auch in neuen Code-Kommentaren, Commit-Nachrichten und Antworten an
  Leandro keine Gedankenstriche. Ältere Kommentare enthalten noch welche,
  die bitte nicht als Vorbild nehmen.
- Einzige Ausnahmen: wörtliche Kundenzitate und fremde Firmennamen, z. B.
  „Sicitalia – So schmeckt der Süden“ und das Zitat in
  `src/data/referenzen.js`. Die bleiben so, wie der Kunde sie geschrieben hat.

Prüfen nach Änderungen (findet auch Text, der erst beim Build entsteht):

```bash
npm run build && rg -n '—|–|&mdash;|&ndash;' dist --glob '*.html' --glob '!dist/admin/**'
```

Treffer in Kommentaren (HTML, eingebettetes CSS und JavaScript) und die
beiden Ausnahmen oben sind unkritisch, alles andere ist zu beheben.
