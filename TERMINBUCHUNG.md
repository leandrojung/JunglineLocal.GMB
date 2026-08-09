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
| SMTP | Mails gehen über PHP `mail()` — kommen an, landen aber öfter im Spam |
| Google | Kalender zeigt nur die über die Website gebuchten Termine, dein privater Google-Kalender wird nicht berücksichtigt |
| `MEETING_URL` | Bestätigung und Kalendereintrag enthalten keinen Videolink |
| Cron | Keine Erinnerungsmails am Vortag |

Buchen, Bestätigen, Absagen und Verschieben funktionieren auch ohne alles davon.

---

## 1. Mailversand (wichtigster Punkt)

hPanel → **E-Mails** → **E-Mail-Konten** → bei `Info@jungline.de` auf
**Konfigurationseinstellungen** → *Manuelle Konfiguration*.

Dort stehen Server und Port. In die `.env`:

```
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=465
SMTP_SECURE=ssl
SMTP_USER=Info@jungline.de
SMTP_PASS=<das Passwort des Postfachs>
MAIL_FROM=Info@jungline.de
MAIL_FROM_NAME=JunglineLocal
OWNER_EMAIL=Info@jungline.de
SITE_URL=https://jungline.de
```

Nutzt dein Anbieter Port 587 statt 465, dann `SMTP_PORT=587` **und**
`SMTP_SECURE=tls` setzen — die beiden gehören immer zusammen.

Danach eine Testbuchung auf dich selbst machen. Kommt keine Mail an, steht
der Grund im Fehlerprotokoll (hPanel → **Erweitert** → *PHP-Konfiguration* →
Fehlerprotokoll), Suchwort `booking/smtp`.

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
{"success":true,"sent":0,"failed":0,"purged":0}
```

`sent` zählt die verschickten Erinnerungen — 0 ist richtig, solange kein
Termin näher als 24 Stunden ist. Entscheidend ist `success:true`.

Der Lauf verschickt an jeden Termin **genau eine** Erinnerung, sobald er
weniger als 24 Stunden entfernt ist (das Feld `reminded_at` verhindert, dass
der stündliche Lauf sie wiederholt), und löscht nebenbei Buchungen, deren
Termin länger als sechs Monate zurückliegt — so wie es die
Datenschutzerklärung zusagt. Ohne Cronjob funktioniert alles andere
weiterhin; es gibt dann nur keine Erinnerung am Vortag, und die Löschfrist
wird nicht vollzogen.

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

Zum Reinschauen: hPanel-Dateimanager, Datei `bookings.sqlite` herunterladen
und mit [sqlitebrowser.org](https://sqlitebrowser.org) öffnen. Im Alltag
brauchst du das nicht — jede Buchung und jede Absage kommt ohnehin per Mail.

## Wenn etwas klemmt

| Symptom | Ursache |
| --- | --- |
| „Die freien Termine lassen sich gerade nicht laden" | `/api/booking/slots` erreichbar? `.htaccess` mit hochgeladen? |
| Kalender zeigt gar keine Tage | Alle Slots durch Google-Termine belegt, oder `BK_LEAD_HOURS`/`BK_WORKDAYS` zu eng |
| Buchung klappt, keine Mail | SMTP-Daten prüfen, Fehlerprotokoll nach `booking/smtp` durchsuchen |
| Termin fehlt im Google-Kalender | Freigabe aus Schritt 3b vergessen, oder Schlüsseldatei nicht lesbar; Protokoll nach `booking/google` durchsuchen |

### Mails prüfen, wenn sie nicht ankommen

```
https://jungline.de/api/booking/mailtest?token=<BOOKING_CRON_TOKEN>&to=<zieladresse>
```

Die Seite verschickt Testmails und zeigt dabei das komplette Gespräch mit dem
Mailserver — jede Zeile, inklusive der Antwort auf den Schlusspunkt, in der
die Vorgangsnummer des Hosters steht. Damit lässt sich unterscheiden, ob eine
Mail schon beim Absenden scheitert oder erst danach unterwegs verschwindet.

### Drei Regeln, an die sich jede Mail halten muss

Der Ausgangsfilter bei Hostinger nimmt **jede** Mail an („250 Ok: queued")
und verwirft sie danach lautlos — ohne Fehlermeldung, ohne Bounce, auch nicht
im Spam. Elf Diagnoserunden mit jeweils fast identischen Testmails haben drei
Regeln ergeben. Wer die Vorlagen in `_templates.php` ändert, muss sie
einhalten, sonst kommt die Mail nicht mehr an:

1. **Kein Anhang.** Egal welcher Dateityp — mit Anhang kam keine einzige
   Testmail durch, ohne Anhang jede.
2. **Höchstens drei Web-Adressen in der Textfassung.** Null bis drei kamen
   ausnahmslos an, vier und fünf ausnahmslos nicht. Deshalb hat die
   Bestätigung keine Signaturzeile mit Domain und nur *einen*
   Kalender-Link statt zweier.
3. **Keine Markennamen neben einem Link.** Genau daran ist die Bestätigung
   zuletzt gescheitert: „Termin zum Kalender hinzufügen (Google, Apple oder
   Outlook)". Drei große Marken unmittelbar an einer Adresse sind eine
   geläufige Phishing-Signatur. Ohne die Klammer kommt dieselbe Mail an.

Nicht schuld waren — jeweils durch Gegenprobe ausgeschlossen — der
Versandweg, der Betreff, die Textfassung, der Zoom-Link und die verlinkte
Zielseite.

Der Kalendereintrag steckt deshalb als **ein** Link in der Mail, der auf
`/api/booking/calendar?token=…` führt. Erst dort stehen beide Wege zur
Auswahl (Google Kalender, Datei für Apple/Outlook) — auf einer eigenen Seite
sind Markennamen unbedenklich. Auf dem Handy ist das ohnehin der kürzere Weg
als ein Anhang: ein Fingertipp statt Download und Öffnen-mit.

Google-Ausfälle legen die Buchung nie lahm: Kann der Kalender nicht erreicht
werden, wird trotzdem gebucht und gemailt — du bekommst in deiner
Benachrichtigung dann einen Hinweis, den Termin von Hand einzutragen.
