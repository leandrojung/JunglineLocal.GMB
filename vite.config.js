import { defineConfig } from 'vite'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { resolve, dirname, relative } from 'node:path'
import { fileURLToPath } from 'node:url'
import { bausteine } from './src/data/bausteine.js'
import { rankcardCompetitors, rankcardExampleNote } from './src/data/rankcard-example.js'

const root = dirname(fileURLToPath(import.meta.url))

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
function renderBausteineHome() {
  const cards = bausteine.map((b, i) => `
      <div class="svc" data-reveal${i % 2 === 1 ? ' data-d="1"' : ''}>
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
  return `<div class="factgrid" data-reveal>${facts}\n    </div>`
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

function sharedShell() {
  // Jedes Partial nur einmal pro Änderungsstand lesen statt für jede der
  // ~17 Seiten erneut. Der Cache invalidiert sich über mtime + Größe der
  // Datei, damit im Dev-Server Änderungen an Partials sofort greifen.
  const cache = new Map()
  const partial = (name) => {
    const abs = resolve(root, 'partials', name)
    const { mtimeMs, size } = statSync(abs)
    const hit = cache.get(name)
    if (hit && hit.mtimeMs === mtimeMs && hit.size === size) return hit.html
    const html = readFileSync(abs, 'utf-8')
    cache.set(name, { mtimeMs, size, html })
    return html
  }
  const tokens = {
    '<!--HEAD-->': () => partial('head.html'),
    '<!--NAV-->': () => partial('nav.html'),
    '<!--FOOTER-->': () => partial('footer.html'),
    '<!--ENDBODY-->': () => partial('endbody.html'),
    '<!--BAUSTEINE_HOME-->': renderBausteineHome,
    '<!--BAUSTEINE_LEISTUNGEN-->': renderBausteineLeistungen,
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
        // Parametrisierter Token (Attribute statt fixer Textkonstante).
        html = html.replace(
          /<!--RANKCARD_ILLU\s+youLabel="([^"]*)"\s+ariaLabel="([^"]*)"-->/g,
          (_, youLabel, ariaLabel) => renderRankcardIllu(youLabel, ariaLabel),
        )
        return html
      },
    },
  }
}

export default defineConfig({
  plugins: [sharedShell()],
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
        // Chunks: CSS pro Entry isolieren (verhindert, dass Unterseiten
        // ungenutztes CSS aus anderen Seiten mitladen, sobald Vite das
        // Splitting unterstützt — ist bei shared-CSS im MPA-Modus heute
        // schon aktiv, compact hält die Datei klein).
        assetFileNames: 'assets/[name]-[hash][extname]',
        chunkFileNames: 'assets/[name]-[hash].js',
        entryFileNames: 'assets/[name]-[hash].js',
      },
    },
  },
})
