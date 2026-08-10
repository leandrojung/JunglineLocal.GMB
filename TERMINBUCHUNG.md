# Terminbuchung einrichten

Der Kalender auf der Startseite und unter `/kontakt/` ist ein eigenes Tool
(kein Calendly mehr). Der Code ist fertig und läuft — er braucht aber vier
Angaben von dir, die aus Sicherheitsgründen **nicht im Repo stehen dürfen**.

Alle vier kommen in **eine einzige Datei**: `.env`, auf dem Server eine Ebene
**über** `public_html/`. Kopiere dafür `.env.example`, benenne sie `.env` und
trage die Werte ein. Diese Datei niemals in `public_html/` legen und niemals
committen.

Bis die Werte da sind, verhält sich das Tool so:

| Fehlt | Folge |
| --- | --- |
| Maildienst | Mails gehen über das Hoster-Postfach — **kommen bei vielen Empfängern nicht an** (siehe Abschnitt 1) |
| Google | Kalender zeigt nur die über die Website gebuchten Termine, dein privater Google-Kalender wird nicht berücksichtigt |
| `MEETING_URL` | Bestätigung und Kalendereintrag enthalten keinen Videolink |
| Cron | Keine Erinnerungsmails am Vortag, und liegengebliebene Mails werden seltener wiederholt |

Buchen, Bestätigen, Absagen und Verschieben funktionieren auch ohne alles davon.

---

## 1. Mailversand (wichtigster Punkt)

### Warum das Hoster-Postfach nicht reicht

Der Versand lief bisher über `Info@jungline.de` bei Hostinger. Dessen
Ausgangsfilter nimmt **jede** Mail an — die Antwort lautet immer
„250 Ok: queued" — und verwirft sie danach lautlos: keine Fehlermeldung,
kein Bounce, kein Spam-Ordner. Elf Diagnoserunden haben daraus Faustregeln
abgeleitet (kein Anhang, höchstens drei Adressen im Text, keine Markennamen
neben einem Link). Die stimmen alle, kurieren aber nur das Symptom: Sie
schieben die Mail knapp unter eine Schwelle, die niemand sehen kann und die
sich jederzeit verschieben darf.

Genau das ist dann auch passiert — dieselbe Bestätigung kam bei dem einen
Empfänger an und beim nächsten nicht.

Die Ursache liegt nicht im Text, sondern im Weg. Gmail und Yahoo verlangen
seit Februar 2024 von **jedem** Absender, dass er sich ausweist: SPF, DKIM
und DMARC müssen zusammenpassen. Tun sie das nicht zweifelsfrei, wird die
Mail je nach Anbieter einsortiert, in den Spam geschoben oder stillschweigend
weggeworfen. Deshalb war es „bei manchen ja, bei manchen nein".

### Was stattdessen zu tun ist

Ein Transaktionsmail-Dienst dazwischen. Der unterschreibt jede Mail mit DKIM
für jungline.de, verschickt über gepflegte IP-Adressen — und sagt zu jeder
einzelnen Mail, ob sie zugestellt, abgelehnt oder als Spam eingestuft wurde.
Genau diese Auskunft fehlte bisher.

**Empfehlung: Brevo.** Server in der EU (DSGVO), 300 Mails am Tag kostenlos,
deutschsprachige Oberfläche. Für den Bedarf hier ist das dauerhaft gratis.

1. Auf [brevo.com](https://www.brevo.com/de/) ein Konto anlegen.
2. Links **Senders, Domains & Dedicated IPs** → Reiter **Domains** →
   **Add a domain** → `jungline.de` eintragen.
3. Brevo zeigt jetzt **drei bis vier DNS-Einträge** an (zwei `_domainkey`,
   ein `dmarc`, einer zur Bestätigung). Diese Einträge müssen ins DNS von
   jungline.de: hPanel → **Domains** → *DNS-Zonenverwaltung* → für jeden
   Eintrag **Neuen Eintrag hinzufügen**, Typ und Werte genau abtippen.
4. Zurück bei Brevo auf **Authenticate** klicken. Bis alle Häkchen grün
   sind, können ein paar Minuten bis zwei Stunden vergehen — DNS braucht das.
5. Links **SMTP & API** → Reiter **API Keys** → **Generate a new API key**.
   Den Wert kopieren; er wird nur einmal angezeigt.
6. In die `.env`:

```
BREVO_API_KEY=xkeysib-....
MAIL_FROM=Info@jungline.de
MAIL_FROM_NAME=JunglineLocal
OWNER_EMAIL=Info@jungline.de
SITE_URL=https://jungline.de
```

Das ist alles. Der Rest passiert von selbst.

> **Schritt 3 ist der entscheidende.** Ohne die DNS-Einträge verschickt auch
> Brevo nur unsignierte Mails, und dann ist nichts gewonnen. Wer die
> Einträge setzt und wartet, bis Brevo grün meldet, hat das Problem an der
> Wurzel gelöst.

Statt Brevo gehen genauso **Resend** (`RESEND_API_KEY`, 3.000 Mails im
Monat frei), **Postmark** (`POSTMARK_TOKEN`, beste Zustellquote, ab 15 $) und
**Mailjet** (`MAILJET_API_KEY` + `MAILJET_SECRET_KEY`). Es genügt genau
einer. Die Einrichtung ist bei allen dieselbe: Domain eintragen, DNS-Einträge
setzen, Schlüssel kopieren.

### Das Auffangnetz dahinter

Die SMTP-Daten des Hoster-Postfachs dürfen in der `.env` stehen bleiben:

```
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=465
SMTP_SECURE=ssl
SMTP_USER=Info@jungline.de
SMTP_PASS=<das Passwort des Postfachs>
```

Sie sind ab jetzt aber nur noch die zweite Reihe. Fällt der Dienst einmal
aus, geht die Mail über SMTP hinaus — mit den bekannten Einschränkungen,
aber besser als gar nicht. Nutzt dein Anbieter Port 587 statt 465, dann
`SMTP_PORT=587` **und** `SMTP_SECURE=tls` setzen; die beiden gehören immer
zusammen.

### Nichts geht mehr verloren

Klappt kein einziger Weg, ist die Mail trotzdem nicht weg: Sie wandert in
einen Ausgangskorb und wird wiederholt — nach 5 Minuten, dann nach 20, nach
1, 3, 8 und 24 Stunden. Wiederholt wird sie vom stündlichen Cronjob
(Abschnitt 4) und nebenbei nach jeder weiteren Buchung. Erst nach dem
sechsten vergeblichen Versuch gilt sie als gescheitert — und steht dann als
solche im Protokoll, statt spurlos zu verschwinden.

### Prüfen

```
https://jungline.de/api/booking/mailtest?token=<BOOKING_CRON_TOKEN>
```

Die Seite zeigt auf einen Blick: welche Versandwege eingerichtet sind, ob
SPF, DKIM und DMARC für jungline.de stehen, und was mit den zuletzt
verschickten Mails passiert ist. Mit `&to=deine@adresse.de` verschickt sie
zusätzlich Bestätigung, Erinnerung und Absage — genau so, wie eine echte
Buchung sie verschickt.

## 2. Videoraum

```
MEETING_URL=https://us05web.zoom.us/j/1234567890
```

Ein fester Raum, den du dauerhaft behältst. Der Link erscheint in der
Bestätigung, in der Erinnerung, im Kalendereintrag und auf der
Bestätigungsseite nach dem Buchen.

## 3. Google-Kalender-Abgleich

Damit niemand eine Zeit buchen kann, in der du schon einen Termin hast, und
jede Buchung automatisch in deinem Kalender landet.

**a) Service-Account anlegen**

1. [console.cloud.google.com](https://console.cloud.google.com) öffnen, oben
   ein Projekt anlegen (Name egal, z. B. „Jungline Terminbuchung").
2. Links **APIs & Dienste → Bibliothek** → nach *Google Calendar API* suchen →
   **Aktivieren**.
3. Links **APIs & Dienste → Anmeldedaten** → **Anmeldedaten erstellen** →
   **Dienstkonto**. Name frei wählbar, Rollen kannst du überspringen.
4. Das erstellte Dienstkonto anklicken → Reiter **Schlüssel** → **Schlüssel
   hinzufügen → Neuen Schlüssel erstellen → JSON**. Die Datei wird
   heruntergeladen.
5. Notiere dir die E-Mail-Adresse des Dienstkontos — sie sieht aus wie
   `etwas@projektname.iam.gserviceaccount.com`.

**b) Kalender für das Dienstkonto freigeben**

[calendar.google.com](https://calendar.google.com) → bei deinem Kalender auf
die drei Punkte → **Einstellungen und Freigabe** → *Für bestimmte Personen
freigeben* → **Personen hinzufügen** → die Dienstkonto-Adresse aus Schritt 5
eintragen → Berechtigung **Änderungen an Terminen vornehmen**.

Ohne diesen Schritt funktioniert nichts — das Dienstkonto hat sonst einen
eigenen, leeren Kalender und sieht deinen gar nicht.

**c) Schlüsseldatei hochladen**

Die JSON-Datei per hPanel-Dateimanager **eine Ebene über `public_html/`**
ablegen (dort, wo auch die `.env` liegt). Dann in die `.env`:

```
GOOGLE_CALENDAR_ID=deine-google-adresse@gmail.com
GOOGLE_SA_KEY_FILE=/home/<dein-benutzer>/google-kalender.json
```

Die `GOOGLE_CALENDAR_ID` steht in den Kalendereinstellungen unter
*Kalender integrieren* → „Kalender-ID".

Den vollständigen Pfad zeigt dir der Dateimanager oben in der Adresszeile.

## 4. Erinnerungsmails (Cronjob)

Erst einen langen Zufallswert eintragen — **nicht** eines deiner Passwörter,
denn dieser Wert steht später im Cron-Befehl und in Server-Protokollen:

```
BOOKING_CRON_TOKEN=<32 zufällige Buchstaben und Ziffern>
```

Dieser Hosting-Tarif zeigt **keine Cronjobs im hPanel** — weder im Menü der
Website noch über die Suche. Eingerichtet wird der Lauf deshalb per SSH
(hPanel → **Erweitert** → *SSH-Zugang* nennt Adresse, Port und Benutzer):

```
ssh -p 65002 <benutzer>@<server-ip>
```

Dann in einem Zug, mit dem echten Token statt des Platzhalters:

```
printf '0 * * * * curl -s "https://jungline.de/api/booking/remind?token=<TOKEN>" >/dev/null 2>&1\n' | crontab -
crontab -l
```

`printf` statt des sonst üblichen `crontab -l | …`: Bei noch leerem Crontab
schreibt `crontab -l` die Zeile „no crontab for …" mit in die Eingabe, und
`crontab` verwirft das Ganze anschließend als ungültig — kommentarlos, mit
Rückgabewert 0. Ein anschließendes `crontab -l` ist deshalb Pflicht: Steht
die Zeile dort nicht, wurde nichts gespeichert.

Zum Prüfen die Adresse einmal selbst im Browser aufrufen; sie antwortet mit

```
{"success":true,"sent":0,"failed":0,
 "queue":{"sent":0,"still_queued":0,"failed":0},"purged":0}
```

`sent` zählt die verschickten Erinnerungen — 0 ist richtig, solange kein
Termin näher als 24 Stunden ist. `queue` zählt die liegengebliebenen Mails,
die dieser Lauf nachgereicht hat. Entscheidend ist `success:true`.

Der Lauf macht dreierlei:

* Er verschickt an jeden Termin **genau eine** Erinnerung, sobald er weniger
  als 24 Stunden entfernt ist (das Feld `reminded_at` verhindert, dass der
  stündliche Lauf sie wiederholt).
* Er **arbeitet den Ausgangskorb ab** — jede Bestätigung, Absage oder
  Erinnerung, die beim ersten Versuch nicht rausging, bekommt hier ihre
  Wiederholung.
* Er löscht Buchungen, deren Termin länger als sechs Monate zurückliegt —
  so wie es die Datenschutzerklärung zusagt — und kürzt das Mailprotokoll
  auf 90 Tage.

Ohne Cronjob funktioniert alles andere weiterhin. Es gibt dann keine
Erinnerung am Vortag, die Löschfrist wird nicht vollzogen, und
liegengebliebene Mails werden nur noch beiläufig wiederholt — nämlich beim
nächsten Buchungsvorgang, höchstens alle fünf Minuten und höchstens drei auf
einmal. **Der Cronjob ist deshalb keine Kür.**

---

## Zeiten ändern

Arbeitszeiten, Termindauer, Vorlauffrist und Urlaubstage stehen ganz oben in
`public/api/booking/_config.php` und sind auch ohne Programmierkenntnisse
lesbar:

```php
const BK_SLOT_MIN     = 30;      // Länge eines Termins in Minuten
const BK_LEAD_HOURS   = 12;      // frühestens so viele Stunden im Voraus buchbar
const BK_HORIZON_DAYS = 60;      // so weit reicht der Kalender in die Zukunft
const BK_WORKDAYS     = [1, 2, 3, 4, 5];   // 1 = Montag … 7 = Sonntag
const BK_DAY_START    = '09:00';
const BK_DAY_END      = '17:00';
const BK_BLOCKED_DAYS = [];      // z. B. ['2026-12-24', '2026-12-31']
```

Nach einer Änderung normal deployen (Push genügt).

## Wo die Buchungen liegen

In einer SQLite-Datei im Ordner `.jungline-data/` **über** `public_html/` —
kein Besucher kommt dort heran. Falls dein Hoster dort kein Schreibrecht
gibt, legt das Tool den Ordner innerhalb des Web-Roots an und sperrt ihn per
`.htaccess`; das passiert automatisch.

In derselben Datei liegt auch das Mailprotokoll (Tabelle `mail_queue`) —
jede verschickte Mail mit Weg, Vorgangsnummer und Status. Lesbar ist es
bequemer über die Diagnoseseite (siehe unten); herunterladen musst du dafür
nichts.

Zum Reinschauen: hPanel-Dateimanager, Datei `bookings.sqlite` herunterladen
und mit [sqlitebrowser.org](https://sqlitebrowser.org) öffnen. Im Alltag
brauchst du das nicht — jede Buchung und jede Absage kommt ohnehin per Mail.

## Wenn etwas klemmt

| Symptom | Ursache |
| --- | --- |
| „Die freien Termine lassen sich gerade nicht laden" | `/api/booking/slots` erreichbar? `.htaccess` mit hochgeladen? |
| Kalender zeigt gar keine Tage | Alle Slots durch Google-Termine belegt, oder `BK_LEAD_HOURS`/`BK_WORKDAYS` zu eng |
| Buchung klappt, keine Mail | Diagnoseseite aufrufen (siehe unten) — sie sagt genau, woran es lag |
| Termin fehlt im Google-Kalender | Freigabe aus Schritt 3b vergessen, oder Schlüsseldatei nicht lesbar; Protokoll nach `booking/google` durchsuchen |

### Wenn ein Kunde keine Mail bekommen hat

```
https://jungline.de/api/booking/mailtest?token=<BOOKING_CRON_TOKEN>
```

Diese eine Seite beantwortet vier Fragen:

1. **Welcher Weg?** Welche Versandwege eingerichtet sind und in welcher
   Reihenfolge sie probiert werden.
2. **Darf ich das?** Ob SPF, DKIM und DMARC für jungline.de im DNS stehen.
   Fehlt davon etwas, ist genau das der Grund, warum eine Mail bei dem einen
   ankommt und beim nächsten nicht.
3. **Was ist passiert?** Das Protokoll der zuletzt verschickten Mails — mit
   Empfänger, Weg, Vorgangsnummer und, im Fehlerfall, dem Grund im Klartext.
4. **Geht es jetzt?** Mit `&to=…` verschickt sie Bestätigung, Erinnerung und
   Absage über denselben Weg wie eine echte Buchung.

Weitere Schalter:

| Schalter | Wirkung |
| --- | --- |
| `&to=adresse@example.de` | die drei Kundenmails testweise verschicken |
| `&flush=1` | liegengebliebene Mails sofort wiederholen, statt auf den Cron zu warten |
| `&resend=<Nr>` | eine bestimmte Mail aus dem Protokoll noch einmal verschicken |
| `&resend=<Nr>&to=…` | dieselbe Mail an eine andere Adresse — etwa nach einem Tippfehler |
| `&dkim=1` | zusätzlich nach DKIM-Selektoren im DNS suchen (dauert einen Moment) |

Die DKIM-Suche ist bewusst nicht voreingestellt: DNS kennt keine Auflistung
der Selektoren einer Domain, geraten werden muss also einzeln, und eine
einzige hängende Abfrage würde die ganze Seite blockieren. Ob DKIM greift,
sagt ohnehin die Oberfläche des Maildienstes zuverlässiger.

Steht im Protokoll eine Vorgangsnummer, lässt sich der weitere Weg beim
Dienst selbst nachlesen: Brevo, Resend, Postmark und Mailjet zeigen zu jeder
Nummer, ob die Mail zugestellt, abgelehnt oder als Spam eingestuft wurde.
Genau diese Auskunft gab es beim Versand über das Hoster-Postfach nicht —
deshalb war eine verschwundene Bestätigung dort nicht aufklärbar.

### Die drei alten Zustellregeln

Aus elf Diagnoserunden gegen den Ausgangsfilter des Hosters stammen drei
Regeln, an die sich die Vorlagen in `_templates.php` bis heute halten:

1. **Kein Anhang.** Mit Anhang kam keine einzige Testmail durch, ohne Anhang
   jede.
2. **Höchstens drei Web-Adressen in der Textfassung.** Null bis drei kamen
   ausnahmslos an, vier und fünf ausnahmslos nicht.
3. **Keine Markennamen neben einem Link.** „Termin zum Kalender hinzufügen
   (Google, Apple oder Outlook)" — drei große Marken unmittelbar an einer
   Adresse sind eine geläufige Phishing-Signatur.

Über einen richtigen Maildienst gelten sie **nicht mehr**. Sie beschreiben
das Verhalten des Hoster-Postfachs, und das ist ab jetzt nur noch das
Auffangnetz. Die Vorlagen bleiben trotzdem so, wie sie sind: Sie lesen sich
gut, und solange das Auffangnetz gelegentlich greift, schadet die Rücksicht
nicht. Wer sie ändert, sollte hinterher einmal `&to=` aufrufen.

Der Kalendereintrag steckt deshalb als **ein** Link in der Mail, der auf
`/api/booking/calendar?token=…` führt. Erst dort stehen beide Wege zur
Auswahl (Google Kalender, Datei für Apple/Outlook). Auf dem Handy ist das
ohnehin der kürzere Weg als ein Anhang: ein Fingertipp statt Download und
Öffnen-mit.

Google-Ausfälle legen die Buchung nie lahm: Kann der Kalender nicht erreicht
werden, wird trotzdem gebucht und gemailt — du bekommst in deiner
Benachrichtigung dann einen Hinweis, den Termin von Hand einzutragen.
