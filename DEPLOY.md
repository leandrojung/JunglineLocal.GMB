# Deployment jungline.de

Die Seite im Repo ist **Quellcode, kein fertiges Produkt**. `index.html` &
Co. enthalten Platzhalter wie `<!--HEAD-->` und `<!--NAV-->`, die erst der
Build auflöst. Was auf den Server gehört, ist ausschließlich der Inhalt von
`dist/` nach `npm run build`.

## Der normale Weg: automatisch bei jedem Push

Jeder Push auf `claude/ecstatic-turing-2a206v` startet
`.github/workflows/deploy-hostinger.yml`. Der Workflow baut die Seite, lädt
sie per FTPS nach `public_html/` und prüft danach die Live-Seite.

Dafür müssen im Repository drei Secrets hinterlegt sein
(**Settings → Secrets and variables → Actions → New repository secret**):

| Secret | Wert (Hostinger hPanel → Dateien → FTP-Zugang) |
| --- | --- |
| `HOSTINGER_FTP_SERVER` | FTP-Hostname, z. B. `ftp.jungline.de` |
| `HOSTINGER_FTP_USERNAME` | FTP-Benutzername |
| `HOSTINGER_FTP_PASSWORD` | FTP-Passwort |
| `HOSTINGER_FTP_DIR` | *optional*, nur wenn die Seite nicht in `./public_html/` liegt |

Fehlt eines davon, bricht der Workflow mit einer Klartext-Meldung ab und
nennt den fehlenden Namen.

## Der Notweg: von Hand hochladen

1. Im GitHub-Tab **Actions** den letzten Lauf von „Deploy zu Hostinger“ öffnen.
2. Unten unter **Artifacts** `jungline-dist` herunterladen (ZIP).
3. Im Hostinger-Dateimanager nach `public_html/` hochladen und **vollständig**
   entpacken.

**Wichtig:** immer den kompletten Ordner ersetzen, nie einzelne Dateien.
Genau daran ist die Seite schon einmal gescheitert: Es lag eine neue
`index.html` auf dem Server, aber nicht die dazugehörige Datei unter
`/assets/`. Ergebnis war eine völlig ungestylte Seite — sichtbar überall
außer im eigenen Safari, der noch die alte Version im Cache hatte.

Die versteckte Datei `.htaccess` gehört mit ins Webroot. Sie steuert
Caching, Kompression, MIME-Typen und die `/api/`-URLs. Ohne sie kann der
Server das Stylesheet mit falschem Content-Type ausliefern, und `nosniff`
sorgt dann dafür, dass der Browser es kommentarlos verwirft.

## Nach dem Deploy selbst prüfen

Der Workflow macht das automatisch. Von Hand geht es so:

```bash
curl -sI https://jungline.de/assets/site.css | head -3
```

Erwartet: `HTTP/2 200` und `content-type: text/css`. Alles andere heißt,
dass die Seite ungestylt ausgeliefert wird.

Der eigene Safari ist zum Prüfen ungeeignet — er zeigt oft noch die alte,
funktionierende Version. Aussagekräftig sind ein privates Fenster, ein
anderer Browser oder der In-App-Browser von Instagram.

## Akquise-Konsole (/akquise/)

Das interne Telefon-Werkzeug liegt unter `public/akquise/index.html` und wird
wie jede andere Datei aus `public/` unverändert mit deployt. Es steht auf
keiner Seite verlinkt und trägt `noindex`; erreichbar ist es nur über die
Webnummer:

```
https://jungline.de/akquise/?n=<Webnummer>
```

Der erste Aufruf merkt sich die Nummer im Browser und nimmt sie aus der
Adresszeile. Danach genügt `https://jungline.de/akquise/`.

**Was sonst noch mit muss:** `public/api/akquise.php`, `akquise-ki.php`,
`_akquise.php` und die `.htaccess` (ohne sie fehlen die Routen
`/api/akquise…`). Bei einem vollständigen Upload von `dist/` ist das
automatisch der Fall.

Die Kontakte und Anrufe liegen als `akquise.json` im selben Datenordner wie
die Terminbuchung — eine Ebene **oberhalb** von `public_html`. Sie sind damit
nicht über eine URL abrufbar und überstehen jeden Deploy: Der FTP-Upload
fasst nur `public_html/` an.

Nichts davon braucht eine `.env`. Nur die beiden KI-Funktionen (Mailentwurf
und Übungspartner) wollen einen `ANTHROPIC_API_KEY` — siehe `.env.example`,
Abschnitt „Akquise-Konsole". Ohne ihn sagen genau diese beiden Knöpfe das im
Klartext, der Rest der Konsole läuft normal weiter.

## Terminbuchung

Der Buchungskalender braucht einmalig Zugangsdaten (SMTP, Google-Kalender,
Videoraum, Cronjob) in einer `.env` oberhalb des Web-Roots. Die vollständige
Klick-Anleitung steht in **[TERMINBUCHUNG.md](TERMINBUCHUNG.md)**.

Wichtig beim Deploy: Die PHP-Dateien unter `public/api/booking/` und die
`.htaccess` müssen mit hochgeladen werden — ohne die `.htaccess` fehlen die
Routen `/api/booking/…` und der Kalender bleibt leer.
