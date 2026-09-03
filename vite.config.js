import { defineConfig } from 'vite'
import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, statSync } from 'node:fs'
import { resolve, dirname, relative } from 'node:path'
import { fileURLToPath } from 'node:url'
import { bausteine } from './src/data/bausteine.js'
import { rankcardCompetitors, rankcardExampleNote } from './src/data/rankcard-example.js'
import { zweige, zweigListe } from './src/data/zweige.js'
import { copyrightMetadata } from './scripts/stamp-copyright.mjs'

const root = dirname(fileURLToPath(import.meta.url))

// Jedes Partial nur einmal pro Änderungsstand von der Platte lesen statt für
// jede der ~18 Seiten erneut. Der Cache invalidiert sich über mtime + Größe der
// Datei, damit im Dev-Server Änderungen an Partials sofort greifen.
const partialCache = new Map()

function partial(name) {
  const abs = resolve(root, 'partials', name)
  const { mtimeMs, size } = statSync(abs)
  const hit = partialCache.get(name)
  if (hit && hit.mtimeMs === mtimeMs && hit.size === size) return hit.html
  const html = readFileSync(abs, 'utf-8')
  partialCache.set(name, { mtimeMs, size, html })
  return html
}

// {{PLATZHALTER}} in einer Partial-Vorlage ersetzen. Funktions-Ersatz statt
// String-Ersatz, damit "$&"/"$1" in Texten nicht als Referenz interpretiert wird.
function fuellen(vorlage, werte) {
  return vorlage.replace(/\{\{([A-Z_]+)\}\}/g, (treffer, schluessel) =>
    Object.prototype.hasOwnProperty.call(werte, schluessel) ? werte[schluessel] : treffer,
  )
}

// ---------------------------------------------------------------------------
// Multi-Page-App: jede echte index.html im Projekt wird zu einer eigenen,
// statisch vorgerenderten Seite (dist/<pfad>/index.html). Deeplinks funktionieren
// dadurch auf Hostinger ohne Server-Rewrites — genau das Ziel dieser Umstellung.
// ---------------------------------------------------------------------------
const IGNORE = new Set(['node_modules', 'dist', 'public', 'src', 'partials', '.git'])

function findHtmlInputs(dir = root, acc = {}) {
  for (const entry of readdirSync(dir)) {
    if (IGNORE.has(entry)) continue
    const abs = resolve(dir, entry)
    if (statSync(abs).isDirectory()) {
      findHtmlInputs(abs, acc)
    } else if (entry.endsWith('.html')) {
      // Eindeutiger Rollup-Key aus dem relativen Pfad; die Ausgabestruktur
      // richtet sich ohnehin nach dem Quellpfad relativ zum Projekt-Root.
      const rel = relative(root, abs)
      const key = rel === 'index.html' ? 'index' : rel.replace(/\/index\.html$/, '').replace(/\.html$/, '')
      acc[key] = abs
    }
  }
  return acc
}

// ---------------------------------------------------------------------------
// Baut die gemeinsamen Bausteine (Head, Nav, Footer, Body-Ende) zur Build-Zeit
// in jede Seite ein. Läuft im Dev-Server UND im Production-Build.
// order:'pre', damit die eingefügten <link>/<script>-Tags von Vite anschließend
// noch verarbeitet und mit Hash versehen werden.
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------
// Rendert die 8 Bausteine (src/data/bausteine.js) für Startseite (Kurzfassung,
// nummerierte Kacheln) und Leistungen-Seite (Volldarstellung mit Icon + Langtext).
// Eine Datenquelle, zwei Ansichten — verhindert, dass beide Seiten unterschiedlich
// viele/andere Bausteine zeigen.
// ---------------------------------------------------------------------------
// Startseite zeigt nur einen kuratierten Ausschnitt (Teaser), nicht mehr alle
// 8 Bausteine — die vollständige Erklärung bleibt exklusiv auf der
// Leistungen-Seite (siehe renderBausteineLeistungen). Auswahl: jeder zweite
// Baustein (Index 0/2/4/6), damit die Original-Nummerierung (01/03/05/07)
// sichtbar macht, dass es sich um einen Ausschnitt handelt, nicht die
// komplette Liste.
const HOME_TEASER_INDICES = [0, 2, 4, 6]

function renderBausteineHome() {
  const items = HOME_TEASER_INDICES.map((idx) => bausteine[idx])
  const cards = items.map((b, i) => `
      <div class="svc">
        <span class="svc__idx" aria-hidden="true">${b.num}</span>
        <div>
          <h3>${b.title}</h3>
          <p>${b.teaser}</p>
        </div>
      </div>`).join('')
  return `<div class="services__grid">${cards}\n    </div>`
}

function renderBausteineLeistungen() {
  const facts = bausteine.map((b) => `
      <div class="fact"><h3><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${b.icon}</svg> ${b.title}</h3><p>${b.full}</p></div>`).join('')
  return `<div class="factgrid">${facts}\n    </div>`
}

// ---------------------------------------------------------------------------
// Rendert das kleine "3 Treffer im Local Pack"-Illustrationswidget (Branchen-
// und Ratgeber-Seiten sowie die Leistungen-Seite) aus dem einen festen
// Beispiel-Datensatz (src/data/rankcard-example.js). Parametrisiert über
// youLabel/ariaLabel, weil der Platzhalter für "Sie" je nach Branche variiert
// ("Ihr Betrieb", "Ihr Büro", …), die Wettbewerber-Namen aber überall identisch
// bleiben. Token-Syntax: <!--RANKCARD_ILLU youLabel="…" ariaLabel="…"-->
// ---------------------------------------------------------------------------
function renderRankcardIllu(youLabel, ariaLabel) {
  const [c1, c2] = rankcardCompetitors
  return `<div class="illu illu-localpack" role="img" aria-label="${ariaLabel}">
        <div class="map" aria-hidden="true">
          <svg viewBox="0 0 400 120" preserveAspectRatio="xMidYMid slice">
            <path class="road" d="M-10 30 Q 120 50 200 20 T 410 40"/>
            <path class="road thin" d="M-10 90 Q 150 70 250 95 T 410 80"/>
          </svg>
          <div class="pin">
            <span class="pulse"></span>
            <svg class="pin__marker" width="24" height="30" viewBox="0 0 28 36" fill="none"><path d="M14 0C6.27 0 0 6.27 0 14c0 10.5 14 22 14 22s14-11.5 14-22C28 6.27 21.73 0 14 0z" fill="#55D396"/><circle cx="14" cy="14" r="5.2" fill="#040605"/></svg>
          </div>
        </div>
        <div class="bam__rows" aria-hidden="true" style="margin-top:14px">
          <div class="bam__row bam__row--you"><span class="bam__rank bam__rank--you">1</span>
            <div class="bam__body"><span class="bam__name">${youLabel}</span><span class="bam__meta"><span class="bam__stars">★★★★★</span></span></div>
          </div>
          <div class="bam__row bam__row--comp bam__row--fade"><span class="bam__rank">2</span><div class="bam__body"><span class="bam__name">${c1.name}</span><span class="bam__meta"><span class="bam__stars">${c1.stars}</span></span></div></div>
          <div class="bam__row bam__row--comp bam__row--fade"><span class="bam__rank">3</span><div class="bam__body"><span class="bam__name">${c2.name}</span><span class="bam__meta"><span class="bam__stars">${c2.stars}</span></span></div></div>
        </div>
      </div>
      <p class="illu__caption illu__caption--example">${rankcardExampleNote}</p>`
}

// ---------------------------------------------------------------------------
// CTA-Band am Seitenende: Überschrift und Fließtext sind pro Seite
// unterschiedlich (bewusst, kein Redundanz-Problem), aber Buttons und die
// drei Trust-Bullets ("30 Min, unverbindlich" / "Keine Vorkasse" /
// "DSGVO-konform") waren 18× wortidentisch copy-pasted. Jetzt eine Quelle.
// Token-Syntax: <!--CTABAND h2="…" p="…"-->
// ---------------------------------------------------------------------------
function renderCtaband(h2, p, ziel) {
  // ziel ist optional: Seiten mit eigenem Buchungsbereich (z. B. /webdesign/)
  // geben "#termin" an und schicken damit niemanden mehr auf die Kontaktseite.
  return `<div class="ctaband">
      <h2>${h2}</h2>
      <p>${p}</p>
      <div class="cta-row">
        <a href="${ziel || '/kontakt/#termin'}" class="btn btn--primary btn--lg">Kostenloses Erstgespräch buchen</a>
        <a href="/kontakt/" class="btn btn--ghost btn--lg">Kontakt &amp; Anfahrt</a>
      </div>
      <ul class="ctaband__trust">
        <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg> 30 Min, unverbindlich</li>
        <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg> Keine Vorkasse</li>
        <li><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg> DSGVO-konform</li>
      </ul>
    </div>`
}

// ---------------------------------------------------------------------------
// Branchen-Grid: taucht auf Startseite und Leistungen-Seite auf. Die
// Leistungen-Seite fehlte bisher "Heizung & Sanitär" und nutzte generische
// Pfeil-Icons statt der branchenspezifischen Icons der Startseite — eine
// Datenquelle behebt beides und hält beide Vorkommen synchron.
// ---------------------------------------------------------------------------
const branchenList = [
  { href: '/branchen/handwerker/', label: 'Handwerker', icon: '<path d="M14.7 6.3a4 4 0 0 1-5 5L4 17v3h3l5.7-5.7a4 4 0 0 0 5-5l-2.6 2.6-2.1-.5-.5-2.1z"/>' },
  { href: '/branchen/heizung-sanitaer/', label: 'Heizung &amp; Sanitär', icon: '<path d="M8.5 14.5A4 4 0 0 0 16 13c0-3-4-5-4-9 0 0-6 3-6 8a4 4 0 0 0 2.5 2.5z"/>' },
  { href: '/branchen/aerzte-und-praxen/', label: 'Ärzte &amp; Praxen', icon: '<path d="M12 8v8M8 12h8"/><circle cx="12" cy="12" r="9"/>' },
  { href: '/branchen/anwaelte-und-kanzleien/', label: 'Anwälte &amp; Kanzleien', icon: '<path d="M12 3v18M5 7l7-4 7 4M4 21h16M6 11l-2 4h4zM18 11l-2 4h4z"/>' },
  { href: '/branchen/immobilienmakler/', label: 'Immobilienmakler', icon: '<path d="M3 11l9-7 9 7M5 10v10h14V10"/>' },
  { href: '/branchen/gastronomie/', label: 'Gastronomie', icon: '<path d="M6 3v7a3 3 0 0 0 6 0V3M9 3v18M17 3c-1.5 1-2 3-2 6s.5 4 2 4v8"/>' },
  { href: '/branchen/kosmetik-und-friseure/', label: 'Kosmetik &amp; Friseure', icon: '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M8.5 8.5 20 20M8.5 15.5 20 4"/>' },
  { href: '/branchen/autohaeuser/', label: 'Autohäuser', icon: '<path d="M5 13l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13v5h-2v-2H7v2H5z"/><circle cx="7.5" cy="15.5" r="1"/><circle cx="16.5" cy="15.5" r="1"/>' },
  { href: '/branchen/physiotherapie/', label: 'Physiotherapie', icon: '<path d="M20 6 9 17l-5-5"/>' },
]

const branchenGridExtras = [
  { href: '/branchen/', label: 'Weitere Branchen', icon: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>' },
  { href: '/leistungen/google-unternehmensprofil-optimierung/', label: 'Alle Leistungen', icon: '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>' },
  { href: '/ratgeber/', label: 'Ratgeber', icon: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V4a2 2 0 0 0-2-2H6.5A2.5 2.5 0 0 0 4 4.5z"/>' },
]

function renderBranchenLinks(items) {
  return items.map((b) => `<a href="${b.href}"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${b.icon}</svg>${b.label}</a>`).join('\n        ')
}

function renderBranchenGridHome() {
  return `<div class="related__list">\n        ${renderBranchenLinks([...branchenList, ...branchenGridExtras])}\n      </div>`
}

function renderBranchenGridLeistungen() {
  return `<div class="related__list">\n        ${renderBranchenLinks(branchenList)}\n      </div>`
}

// ---------------------------------------------------------------------------
// ZWEIG-UMSCHALTER (Local SEO <-> Webdesign)
//
// Seit dem Webdesign-Angebot hat die Seite zwei getrennte Zweige. Damit ein
// Besucher jederzeit wechseln kann — und nicht nur einmal auf dem Startscreen
// entscheidet —, steht der Umschalter in JEDER Navigationsleiste:
//   Desktop/Tablet: Aufklappmenü direkt neben dem Logo
//   Telefon:        Segment-Umschalter oben im Menü (ein Tipp statt zwei)
// Beide Varianten kommen aus derselben Datenquelle (src/data/zweige.js).
// ---------------------------------------------------------------------------
const SVG_CHECK = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>'
const SVG_CHEVRON = '<svg class="zweig__chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>'

function zweigIcon(z, size, stroke = 1.9) {
  return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="${stroke}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${z.icon}</svg>`
}

function renderZweigSwitch(aktivId) {
  const aktiv = zweige[aktivId]
  const items = zweigListe.map((z) => {
    const an = z.id === aktivId
    return `<a class="zweig__item${an ? ' is-active' : ''}" href="${z.start}" role="menuitem"${an ? ' aria-current="true"' : ''}>
          <span class="zweig__item-ic">${zweigIcon(z, 18)}</span>
          <span class="zweig__item-tx"><b>${z.titel}</b><small>${z.beschreibung}</small></span>
          <span class="zweig__item-ck" aria-hidden="true">${SVG_CHECK}</span>
        </a>`
  }).join('\n        ')
  return `<div class="zweig" data-zweig-switch>
        <button class="zweig__btn" id="zweigBtn" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="zweigMenu">
          <span class="zweig__btn-ic" aria-hidden="true">${zweigIcon(aktiv, 15, 2.1)}</span>
          <span class="zweig__btn-lb">${aktiv.kurz}</span>
          <span class="u-sr">— Bereich wechseln</span>
          ${SVG_CHEVRON}
        </button>
        <div class="zweig__menu" id="zweigMenu" role="menu" aria-labelledby="zweigBtn">
          <p class="zweig__menu-head">Wofür interessieren Sie sich?</p>
          ${items}
        </div>
      </div>`
}

function renderZweigSwitchMobil(aktivId) {
  const opts = zweigListe.map((z) => {
    const an = z.id === aktivId
    return `<a href="${z.start}" class="zweig-seg__opt${an ? ' is-active' : ''}"${an ? ' aria-current="true"' : ''}>${zweigIcon(z, 16, 2.1)}${z.kurz}</a>`
  }).join('\n    ')
  return `<div class="zweig-seg" role="group" aria-label="Bereich wählen">
    ${opts}
  </div>`
}

// Hinweiszeile im Footer, die auf den jeweils ANDEREN Zweig zeigt. Der
// Umschalter oben ist ein Bedienelement; hier steht der Wechsel noch einmal
// als ausformulierter Satz — für alle, die bis ans Seitenende gelesen haben.
function renderZweigWechsel(aktivId) {
  const andere = zweigListe.filter((z) => z.id !== aktivId)
  return andere.map((z) => `<a class="footer__zweig" href="${z.start}">
          <span class="footer__zweig-ic">${zweigIcon(z, 17)}</span>
          <span class="footer__zweig-tx"><small>Ich biete außerdem</small><b>${z.titel}</b></span>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M12 5.5 18.5 12 12 18.5"/></svg>
        </a>`).join('\n        ')
}

function renderNav(aktivId) {
  const aktiv = zweige[aktivId]
  const links = aktiv.links.map((l) => `<a href="${l.href}">${l.label}</a>`).join('\n      ')
  const mobil = aktiv.links.map((l) => `<a href="${l.href}">${l.label}</a>`).join('\n  ')
  return fuellen(partial('nav.html'), {
    ZWEIG: aktiv.id,
    ZWEIG_KURZ: aktiv.kurz,
    BRAND_HREF: aktiv.start,
    TERMIN_HREF: aktiv.terminHref,
    SWITCH: renderZweigSwitch(aktivId),
    SWITCH_MOBILE: renderZweigSwitchMobil(aktivId),
    LINKS: links,
    MOBILE_LINKS: mobil,
  })
}

function renderFooter(aktivId) {
  const aktiv = zweige[aktivId]
  const liste = (items) => items.map((l) => `<a href="${l.href}">${l.label}</a>`).join('\n          ')
  return fuellen(partial('footer.html'), {
    BRAND_HREF: aktiv.start,
    CLAIM: aktiv.claim,
    ZWEIG_WECHSEL: renderZweigWechsel(aktivId),
    FOOTER_TITEL: aktiv.footerTitel,
    FOOTER_LINKS: liste(aktiv.footerLinks),
    ANKER_TITEL: aktiv.footerAnkerTitel,
    ANKER_LINKS: liste(aktiv.footerAnker),
    FOOTER_CLAIM_KLEIN: aktiv.claimKlein,
  })
}

// ---------------------------------------------------------------------------
// STARTSCREEN ("Wofür interessieren Sie sich?")
//
// Liegt als Overlay ÜBER der Startseite, statt sie zu ersetzen: "/" trägt die
// Rankings und Backlinks des SEO-Zweigs und bleibt deshalb inhaltlich
// unverändert — der Seiteninhalt steht vollständig im HTML und ist für
// Suchmaschinen normal lesbar. Wann das Overlay erscheint, entscheidet allein
// das Skript in partials/chooser.html.
// ---------------------------------------------------------------------------
function renderChooser() {
  const karten = zweigListe.map((z, i) => {
    // Das Overlay liegt auf "/". Die Karte, die auf genau diese Seite zeigt,
    // bekommt "?zweig=…" ans Ziel: ohne JavaScript waere der Klick sonst ein
    // Neuladen derselben Seite und der Startscreen erschiene sofort wieder.
    // Mit JavaScript wird der Klick abgefangen, die Adresse bleibt sauber.
    const ziel = z.start === '/' ? `/?zweig=${z.id}` : z.start
    return `<a class="chooser__card" href="${ziel}" data-zweig-wahl="${z.id}" style="--i:${i}">
        <span class="chooser__card-ic" aria-hidden="true">${zweigIcon(z, 26, 1.7)}</span>
        <span class="chooser__card-tt">${z.startscreenTitel}</span>
        <span class="chooser__card-tx">${z.startscreen}</span>
        <span class="chooser__card-go">Ansehen<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M12 5.5 18.5 12 12 18.5"/></svg></span>
      </a>`
  }).join('\n      ')
  return fuellen(partial('chooser.html'), { KARTEN: karten })
}

// Die fixierte Aktionsleiste am unteren Rand (nur Telefon) fuehrt zum
// Buchungsbereich des jeweiligen Zweigs — sonst wirft sie einen
// Webdesign-Besucher auf die Kontaktseite des SEO-Zweigs.
function renderEndbody(aktivId) {
  return fuellen(partial('endbody.html'), { TERMIN_HREF: zweige[aktivId].terminHref })
}

function sharedShell() {
  const tokens = {
    '<!--HEAD-->': () => partial('head.html'),
    '<!--NAV-->': () => renderNav('seo'),
    '<!--NAV_WEBDESIGN-->': () => renderNav('webdesign'),
    '<!--FOOTER-->': () => renderFooter('seo'),
    '<!--FOOTER_WEBDESIGN-->': () => renderFooter('webdesign'),
    '<!--CHOOSER-->': renderChooser,
    '<!--ENDBODY-->': () => renderEndbody('seo'),
    '<!--ENDBODY_WEBDESIGN-->': () => renderEndbody('webdesign'),
    '<!--BAUSTEINE_HOME-->': renderBausteineHome,
    '<!--BAUSTEINE_LEISTUNGEN-->': renderBausteineLeistungen,
    '<!--BRANCHEN_GRID_HOME-->': renderBranchenGridHome,
    '<!--BRANCHEN_GRID_LEISTUNGEN-->': renderBranchenGridLeistungen,
  }
  return {
    name: 'jungline-shared-shell',
    transformIndexHtml: {
      order: 'pre',
      handler(html) {
        for (const [token, load] of Object.entries(tokens)) {
          // Funktions-Ersatz umgeht $-Sonderzeichen in CSS/JSON-LD
          html = html.split(token).join(load())
        }
        // Parametrisierte Tokens (Attribute statt fixer Textkonstante).
        html = html.replace(
          /<!--RANKCARD_ILLU\s+youLabel="([^"]*)"\s+ariaLabel="([^"]*)"-->/g,
          (_, youLabel, ariaLabel) => renderRankcardIllu(youLabel, ariaLabel),
        )
        html = html.replace(
          /<!--CTABAND\s+h2="([^"]*)"\s+p="([^"]*)"(?:\s+ziel="([^"]*)")?-->/g,
          (_, h2, p, ziel) => renderCtaband(h2, p, ziel),
        )
        return html
      },
    },
  }
}

// ---------------------------------------------------------------------------
// Vite hängt an jedes selbst eingefügte <link>/<script> ein crossorigin-
// Attribut. Für Dateien der eigenen Domain bringt das keinen Vorteil, macht
// aus einem gewöhnlichen Subresource-Request aber einen CORS-Request. In
// In-App-Browsern (Instagram, Google-App) und hinter Proxys ist das eine
// zusätzliche Fehlerquelle: Schlägt die CORS-Prüfung fehl, verwirft der
// Browser das komplette Stylesheet und die Seite erscheint roh und ungestylt.
// Entfernt wird das Attribut ausschließlich bei /assets/-Dateien; bei den
// Font-Preloads ist crossorigin zwingend und bleibt deshalb stehen.
// order:'post', weil Vite diese Tags erst nach den 'pre'-Hooks einfügt.
// ---------------------------------------------------------------------------
function dropSameOriginCrossorigin() {
  return {
    name: 'jungline-drop-crossorigin',
    transformIndexHtml: {
      order: 'post',
      handler(html) {
        return html.replace(/<(?:link|script)\b[^>]*>/g, (tag) =>
          /(?:href|src)="\/assets\//.test(tag)
            ? tag.replace(/\s+crossorigin(?:="[^"]*")?/g, '')
            : tag,
        )
      },
    },
  }
}

// ---------------------------------------------------------------------------
// Entfernt HTML-Kommentare aus dem fertigen Build.
//
// Warum: Mehrere Seiten tragen vorbereitete, bewusst auskommentierte Bausteine
// (die Case-Study-Sektion und das Zertifizierungs-Badge auf der Startseite)
// samt Arbeitsanweisungen. Die sollen im QUELLTEXT bleiben — dort sind sie
// eine fertige Vorlage, die nur noch eingeschaltet werden muss. Im AUSGE-
// LIEFERTEN HTML haben sie nichts verloren: Sie sind rund 23 KB über alle
// Seiten, die jeder Besucher mitlädt, und sie zeigen jedem, der "Seitenquell-
// text anzeigen" klickt, interne Notizen wie "PLATZHALTER" oder "KEIN
// Platzhalter-Badge ohne echte Zertifizierung anzeigen".
//
// Zwei Vorsichtsmaßnahmen:
//   1. Inhalte von <script>, <style>, <pre> und <textarea> werden vorher
//      herausgenommen und danach unverändert wieder eingesetzt. Stünde in
//      einem Skript je eine Zeichenkette mit "<!--" oder "-->" (in JavaScript
//      völlig zulässig), würde ein naives Muster mitten im Code schneiden und
//      die Seite zerstören. In <pre> und <textarea> sind zusätzlich die
//      Zeilenumbrüche bedeutungstragend — sie dürfen nicht zusammengefasst
//      werden. Beide sind hier aktuell leer; die Absicherung verhindert, dass
//      daraus ein Fehler wird, sobald dort einmal Text steht.
//   2. Bedingte Kommentare (<!--[if …]>) und ausdrücklich markierte
//      Kommentare (<!--! …>) bleiben stehen.
//
// apply:'build' — im Dev-Server bleiben die Kommentare sichtbar, dort sind sie
// beim Arbeiten hilfreich.
// ---------------------------------------------------------------------------
function stripHtmlComments() {
  return {
    name: 'jungline-strip-html-comments',
    apply: 'build',
    transformIndexHtml: {
      order: 'post',
      handler(html) {
        // Platzhalter mit NUL-Zeichen (\u0000): das kann in HTML-Text nicht
        // vorkommen, der Ruecktausch trifft also nie echten Seiteninhalt.
        const geschuetzt = []
        let out = html.replace(/<(script|style|pre|textarea)\b[^>]*>[\s\S]*?<\/\1>/gi, (treffer) => {
          geschuetzt.push(treffer)
          return `\u0000KEEP${geschuetzt.length - 1}\u0000`
        })
        out = out.replace(/<!--(?!\[if|!)[\s\S]*?-->/g, '')
        // Die durch das Entfernen entstandenen Leerzeilen zusammenfassen.
        out = out.replace(/\n[ \t]*(?:\n[ \t]*)+/g, '\n\n')
        return out.replace(/\u0000KEEP(\d+)\u0000/g, (_, i) => geschuetzt[Number(i)])
      },
    },
  }
}

// ---------------------------------------------------------------------------
// Das Admin-Dashboard (/admin/) muss jeden Deploy überleben.
//
// Vorgeschichte: Die Datei lag früher nur von Hand nach public_html/admin/
// hochgeladen auf dem Server. Der Deploy ersetzt public_html/ aber vollständig
// durch den Inhalt von dist/ — alles, was nicht aus der Vite-Quelle stammt,
// war danach weg. Die Dateien liegen deshalb jetzt in public/admin/ und sind
// damit regulärer Teil des Builds.
//
// Warum trotzdem dieser Plugin, obwohl Vite public/ ohnehin nach dist/
// kopiert: Vite kopiert dabei nachweislich AUCH Dateien, die mit einem Punkt
// beginnen (geprüft — .htaccess und .htpasswd landen ohne Zutun in dist/).
// Verlassen wollen wir uns darauf nicht. Fehlt eine der drei Dateien im
// Build, ist das Dashboard entweder offline (HTML fehlt) oder — deutlich
// schlimmer — ohne Passwortschutz öffentlich erreichbar (.htaccess/.htpasswd
// fehlen). Beides würde im Build-Log unter ~40 Zeilen Dateiliste niemandem
// auffallen.
//
// Der Plugin kopiert deshalb fehlende Dateien selbst nach und bricht den
// Build mit Klartext ab, falls danach immer noch etwas fehlt. Kein
// zusätzliches npm-Paket nötig (vite-plugin-static-copy wäre eine Abhängigkeit
// für Arbeit, die Vite bereits erledigt) — und ein stiller Regressionsfehler
// wird zum lauten Build-Abbruch.
// ---------------------------------------------------------------------------
const ADMIN_PFLICHTDATEIEN = ['dashboard-protected.html', '.htaccess', '.htpasswd']

function adminDateienSichern() {
  let outDir = 'dist'
  return {
    name: 'jungline-admin-dateien-sichern',
    apply: 'build',
    configResolved(config) {
      // outDir aus der aufgelösten Konfiguration statt hart verdrahtet —
      // damit der Plugin auch nach einer Umstellung von build.outDir greift.
      outDir = config.build.outDir
    },
    // closeBundle läuft nach dem Schreiben des Bundles UND nach dem
    // publicDir-Kopieren von Vite. Früher (z. B. writeBundle) wäre die
    // Prüfung wertlos, weil die Dateien dann noch gar nicht da sein können.
    closeBundle() {
      const quelle = resolve(root, 'public', 'admin')
      const ziel = resolve(root, outDir, 'admin')
      mkdirSync(ziel, { recursive: true })

      const fehlend = []
      for (const datei of ADMIN_PFLICHTDATEIEN) {
        const von = resolve(quelle, datei)
        const nach = resolve(ziel, datei)

        if (!existsSync(von)) {
          fehlend.push(`public/admin/${datei} — die QUELLE fehlt`)
          continue
        }
        if (!existsSync(nach)) copyFileSync(von, nach)
        if (!existsSync(nach)) fehlend.push(`${outDir}/admin/${datei} — Kopieren fehlgeschlagen`)
      }

      if (fehlend.length > 0) {
        this.error(
          'Das Admin-Dashboard ist im Build unvollständig:\n' +
            fehlend.map((z) => `  - ${z}`).join('\n') +
            '\n\nOhne .htaccess/.htpasswd wäre /admin/ nach dem Deploy OHNE ' +
            'Passwortschutz öffentlich erreichbar. Der Build bricht deshalb ab.',
        )
      }

      console.log(`[admin] ${ADMIN_PFLICHTDATEIEN.length} Dateien in ${outDir}/admin/ bestätigt`)
    },
  }
}

export default defineConfig({
  plugins: [
    sharedShell(),
    dropSameOriginCrossorigin(),
    copyrightMetadata(),
    stripHtmlComments(),
    adminDateienSichern(),
  ],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    // esbuild-Minifier (Standard in Vite) liefert sehr kompakten Output;
    // cssMinify:true explizit gesetzt, damit auch CSS-Dateien mit esbuild
    // optimiert werden (Kommentare entfernen, Whitespace kürzen).
    cssMinify: true,
    // reportCompressedSize: false beschleunigt den Build leicht (kein
    // gzip-Scan nach dem Bundle), ändert die Ausgabe nicht.
    reportCompressedSize: false,
    rollupOptions: {
      input: findHtmlInputs(),
      output: {
        // Kompaktes Output: kein unnötiges Whitespace in Rollup-Wrappern.
        compact: true,
        // Stabile Dateinamen ohne Content-Hash — bewusst gegen die übliche
        // Empfehlung, weil diese Seite per FTP auf Hostinger hochgeladen
        // wird und ein Upload damit nicht atomar ist.
        //
        // Mit Hash (site-CYi1rao6.css) verweist jede HTML-Datei auf genau
        // einen Dateinamen. Kommt die HTML auf dem Server an, die passende
        // CSS-Datei aber nicht — abgebrochener Upload, nur einzelne Dateien
        // manuell ersetzt, Hash-Wechsel durch einen neuen Build —, dann
        // läuft das Stylesheet in einen 404 und der Besucher sieht die
        // nackte HTML ohne jedes Styling. Genau dieser Zustand war live.
        //
        // Ohne Hash zeigt jede HTML auf /assets/site.css. Diese Datei
        // existiert nach dem ersten Upload immer; im schlimmsten Fall ist
        // sie eine Version alt, die Seite bleibt aber vollständig gestylt.
        // Die Cache-Steuerung übernimmt dafür .htaccess per Revalidierung
        // ("no-cache" + ETag) statt über den Dateinamen.
        assetFileNames: 'assets/[name][extname]',
        chunkFileNames: 'assets/[name].js',
        entryFileNames: 'assets/[name].js',
      },
    },
  },
})
