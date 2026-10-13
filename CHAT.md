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

Ohne Schlüssel läuft der Chat schon jetzt, nur ohne KI für Freitext. Für die KI:

1. Konto bei Mistral anlegen: <https://console.mistral.ai>. Im kostenlosen
   Tarif ("Experiment") ist eine Telefonverifizierung nötig. Dort unter
   **API Keys** einen Schlüssel erzeugen.
2. In der `.env` auf dem Server (oberhalb von `public_html`, nie ins
   Repository) eintragen:

   ```
   CHAT_PROVIDER=mistral
   CHAT_API_KEY=<der Schlüssel>
   CHAT_DAILY_BUDGET=300
   CHAT_DIAG_TOKEN=<frei gewählter geheimer Wert>
   ```

3. Prüfen: `https://jungline.de/api/chat?diag=<CHAT_DIAG_TOKEN>` im Browser
   öffnen. Erwartet: `"schluessel_gefunden": true` und
   `"testaufruf": "erfolgreich"`. Bei einem Fehler steht der Grund im
   PHP-Fehlerprotokoll (Zeile `chat-llm`).

## Wichtig: Datenschutz und Trainingsdaten

- Im **kostenlosen** Mistral-Tarif darf der Anbieter Ein- und Ausgaben nach
  eigenen Angaben zum Training verwenden (Opt-out in der Mistral-Konsole
  möglich). Der Chat warnt Besucher deshalb unter dem Eingabefeld und in der
  Datenschutzerklärung (Abschnitt 10), keine persönlichen Daten einzugeben.
- **Vor dem Livegang:** Abschnitt 10 der Datenschutzerklärung rechtlich prüfen
  lassen, den Opt-out in der Mistral-Konsole setzen (oder auf einen bezahlten
  Tarif wechseln) und die Angabe im Abschnitt anpassen, falls sich die
  Bedingungen ändern. Wird der Anbieter gewechselt, muss der Abschnitt
  ebenfalls geändert werden (z. B. Anthropic hat Sitz und Verarbeitung in den
  USA).
- **Zustimmung vor der ersten Frage:** In `src/js/chat.js` steht ganz oben
  `CONSENT_REQUIRED = false`. Auf `true` setzen und neu bauen, dann muss der
  Besucher vor der ersten frei getippten Frage ausdrücklich zustimmen. Das ist
  die rechtlich sicherere Variante. Feste Antworten, Termin und Nachricht sind
  davon nicht betroffen.
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
| Mistral (Standard) | `CHAT_PROVIDER=mistral` |
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
