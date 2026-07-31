import { defineConfig } from 'vite'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { resolve, dirname, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

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
