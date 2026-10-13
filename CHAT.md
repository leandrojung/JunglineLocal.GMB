# Chat-Assistent (runder Knopf unten rechts)

Der Assistent beantwortet Fragen, trägt Termine ein und leitet Nachrichten an
Leandro weiter. Er ist bewusst als **Hybrid** gebaut: Alles, was stimmen muss
(Preise, Ablauf, Kontakt, Termine), kommt aus fester Wissensbasis und den
vorhandenen Endpunkten. Eine KI wird nur für offene Freitextfragen gefragt, und
ihre Antwort wird geprüft, bevor der Besucher sie sieht.

## So entscheidet der Chat

1. **Knopfdruck oder Begrüßung:** feste Antwort, ohne KI.
2. **Eindeutiger Treffer im Wortvergleich:** feste Antwort, ohne KI.
3. **Sonst die KI** (nur wenn ein Schlüssel eingetragen ist und das Tagesbudget
   reicht). Sie wählt bevorzugt eine feste Antwort aus oder formuliert kurz aus
   der Wissensbasis. Danach prüft der Server die Antwort: Preise, Zahlen,
   Adressen, Telefonnummern und Garantieversprechen, die nicht in der
   Wissensbasis stehen, führen zum Verwerfen.
4. **Alles andere** (KI nicht erreichbar, Limit leer, Antwort verworfen,
   Einzelfallfrage): Weiterleitung an Leandro mit Termin, Nachricht und Telefon.

Termin und Nachricht laufen im Browser direkt gegen `/api/booking/book` und
`/api/contact`. Name, E-Mail und Telefon gehen **nie** an die KI.

## Einrichtung (einmalig)

Ohne Schlüssel läuft der Chat schon, nur ohne KI für Freitext. Für die KI mit
dem kostenlosen Groq-Tarif:

1. Konto auf <https://console.groq.com> anlegen, unter **API Keys** einen
   Schlüssel erzeugen und sofort kopieren.
2. In der `.env` auf dem Server (im Ordner, in dem `public_html` liegt, nie im
   Repository) eintragen. Gibt es dort schon eine `.env`, die Zeilen unten
   anhängen:

   ```
   CHAT_PROVIDER=groq
   CHAT_API_KEY=<der Schlüssel>
   CHAT_DAILY_BUDGET=300
   CHAT_DIAG_TOKEN=<frei gewählter geheimer Wert>
   ```

3. Prüfen: `https://jungline.de/api/chat?diag=<CHAT_DIAG_TOKEN>` im Browser
   öffnen. Erwartet: `"schluessel_gefunden": true` und
   `"testaufruf": "erfolgreich"`. Bei einem Fehler zeigt `meldung_des_anbieters`
   den Grund (z. B. "model not found": dann in der Groq-Konsole einen aktuellen
   Modellnamen nachsehen und als `CHAT_MODEL=...` eintragen).

Der Schlüssel gehört nie in den Chat, in GitHub oder in eine Datei im
Repository. Wurde er irgendwo geteilt, in der Groq-Konsole löschen und einen
neuen erzeugen.

## Wichtig: Datenschutz

- Groq sitzt in den USA. Frei getippte Fragen gehen dorthin (Abschnitt 10 der
  Datenschutzerklärung). Der Chat warnt Besucher unter dem Eingabefeld, keine
  persönlichen Daten einzugeben.
- **Vor dem Livegang prüfen:** Die Aussage "kein Training mit Eingaben" in
  Abschnitt 10 stammt aus Angaben Dritter, nicht aus Groqs eigenen
  Bedingungen. Bitte in Groqs Datenschutzerklärung und den Nutzungsbedingungen
  gegenlesen und den Abschnitt rechtlich prüfen lassen, besonders die
  Übermittlung in die USA.
- **Zustimmung vor der ersten Frage:** In `src/js/chat.js` steht ganz oben
  `CONSENT_REQUIRED = false`. Auf `true` setzen und neu bauen, dann muss der
  Besucher vor der ersten frei getippten Frage ausdrücklich zustimmen. Wegen
  der Übermittlung in die USA ist das die rechtlich sicherere Variante. Feste
  Antworten, Termin und Nachricht sind davon nicht betroffen.
- Auf dem Server werden weder Fragen noch Antworten gespeichert, auch nicht im
  Fehlerprotokoll. Gezählt wird nur ein anonymes Tagesbudget und, wie bei den
  anderen Werkzeugen, die cookielose Statistik (`chat_open`, `chat_frage`,
  `chat_termin`, `chat_nachricht`).

## Wissensbasis pflegen (das ist der Hebel für Genauigkeit)

Alles, was der Bot sagt, steht in `public/api/chat/_wissen.php`. Ändert sich
etwas auf der Website (Preise, Ablauf, Adresse), dort mitändern, sonst
widerspricht der Bot der Seite. Jeder Eintrag hat:

- `text`: die freigegebene Antwort. Wird wörtlich ausgegeben.
- `kw`: Suchwörter für den Wortvergleich. Ein `!` davor macht ein Wort stark,
  ein `$` am Ende verlangt das ganze Wort.
- `links`, `aktion`, `thema`, `folge`: Verlinkung, Termin- oder
  Nachrichtenformular und Folgeknöpfe. Erklärung steht am Dateianfang.

Schreibregel der Seite gilt auch hier: keine Gedankenstriche.

**Neue Frage ergänzen:** Eintrag kopieren, Text und Suchwörter anpassen, bei
`chatKnopfBeschriftungen()` ggf. einen Knopftext ergänzen. Fertig. Die KI kennt
den neuen Eintrag automatisch.

**Testen ohne KI:** `php -r 'require "public/api/chat/_match.php";
var_dump(chatFindeFaq("Was kostet ein Relaunch?"));'`

## Anbieter wechseln

Nur die `.env` ändern:

| Anbieter | Einstellung |
| --- | --- |
| Groq (kostenlos) | `CHAT_PROVIDER=groq` |
| Mistral (EU, API-Zugang erst nach Upgrade) | `CHAT_PROVIDER=mistral` |
| Claude | `CHAT_PROVIDER=anthropic`, `CHAT_MODEL=claude-sonnet-5-5` (oder ein anderes Claude-Modell) |
| Groq, OpenRouter, OpenAI | `CHAT_PROVIDER=openai`, `CHAT_API_URL=<Adresse>`, `CHAT_MODEL=<Modell>` |

Bei kostenpflichtigen API-Tarifen werden Eingaben nach Angaben der Anbieter nicht zum Training genutzt (Bedingungen vor dem Wechsel prüfen),
das kostet bei dieser Seite nach grober Schätzung wenige bis niedrige
zweistellige Euro im Monat. Das Tagesbudget (`CHAT_DAILY_BUDGET`) begrenzt es
hart.

## Schutz vor Missbrauch und Kosten

- Nur Anfragen von jungline.de werden angenommen.
- Pro Besucher-IP: 60 feste Antworten und 12 KI-Fragen je 10 Minuten.
- Tagesbudget für KI-Aufrufe über alle Besucher (`CHAT_DAILY_BUDGET`, Standard
  300). Danach antwortet der Chat bis zum nächsten Tag nur noch mit festen
  Antworten.
- Honigtopf-Feld, Größenbegrenzung der Anfrage, Länge der Frage höchstens 500
  Zeichen.

## Wo was liegt

| Datei | Aufgabe |
| --- | --- |
| `public/api/chat.php` | Endpunkt, Entscheidungsweg, Limits, Diagnose |
| `public/api/chat/_wissen.php` | Wissensbasis, erlaubte Links |
| `public/api/chat/_match.php` | Wortvergleich ohne KI |
| `public/api/chat/_llm.php` | Anbieter, Prompt, Prüfung der KI-Antwort |
| `src/js/chat.js`, `src/css/chat.css` | Fenster, Termin, Nachricht (wird erst beim Antippen geladen) |
| `partials/endbody.html`, `src/css/site.css` | Der runde Knopf |
| `src/js/site.js` | Lader des Knopfs |
