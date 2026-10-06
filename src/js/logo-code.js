/* ============================================================
   BILDMARKE AUS CODE (Hero der Startseite)
   ============================================================
   "Vom Rauschen zur Sichtbarkeit": Die Bildmarke entsteht nicht als Linie,
   sondern aus Daten. Erst grobe Pixel, dann wird die Auflösung in drei
   Stufen schärfer (40, 20, 10 px), zuletzt entschlüsseln sich die Pixel zu
   Zeichen. Der Text ist das, womit Jungline Local arbeitet: Strukturdaten
   eines Unternehmensprofils, Suchanfragen, Platzierungen.

   Danach lebt sie leise weiter:
     * alle paar Sekunden fährt ein Scan darüber (Google indexiert neu)
     * mit der Maus lässt sich der Code freilegen (nur Desktop)
     * selten rendert die Marke kurz neu: ein Augenblick grob, dann scharf

   Technik, damit es auch auf schwachen Geräten und in Safari ruhig bleibt:
     * ein Canvas, höchstens doppelte Pixeldichte
     * der Ruhezustand liegt fertig in einer Zwischenebene; pro Bild werden
       nur die Zellen neu gezeichnet, die sich gerade bewegen. Bewegt sich
       nichts, wird gar nicht gezeichnet.
     * keine Bilder pro Sekunde, wenn die Marke nicht im Bild ist oder der
       Tab im Hintergrund liegt
     * "Bewegung reduzieren": sofort der fertige Zustand, keine Animation

   Die Form kommt aus dem SVG im HTML (#logo-fill), das als Rahmen und als
   Rückfall ohne Skript stehen bleibt. Der Canvas ist rein dekorativ
   (.hero__bg ist aria-hidden). */

const VIEW = 375
const MITTE = 187.5
// Innenradius des Sechsecks der Bildmarke (halbe Breite, aus dem Pfad).
const INNEN = 141.9

const QUELLE = [
  '{"@context":"https://schema.org","@type":"LocalBusiness",',
  '"name":"Ihr Unternehmen","address":{"addressLocality":"Dorsten"},',
  '"geo":{"latitude":51.66,"longitude":6.96},',
  '"openingHours":"Mo,Tu,We,Th,Fr 08:00 18:00",',
  '"aggregateRating":{"ratingValue":4.9,"reviewCount":187}}',
  'query("dachdecker dorsten") rank(15) → rank(1) ✓',
  'query("zahnarzt in der nähe") local_pack.top3 ✓',
  'query("anwalt dorsten") maps.visible = true',
  'profile.categories.primary ✓ profile.photos += 24',
  'reviews.reply(all) ✓ nap.consistent = true',
  'visibility++ calls++ routes++ ★★★★★',
].join(' ')

const RAUSCHEN = '01<>/{}[]#*+=$%&?;:ABCDEF0123456789'

const MONO = 'ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace'

// Zeitplan des Einstiegs in Sekunden ab Start. Passt zum Nachzeichnen der
// Linie im SVG (site.css: jlDraw ab 0,35 s, 2,2 s lang).
const STUFEN = [
  { ab: 0.25, bis: 0.7, zelle: 40 },
  { ab: 0.7, bis: 1.1, zelle: 20 },
  { ab: 1.1, bis: 1.5, zelle: 10 },
]
const ENTSCHLUESSELN_AB = 1.5
const ENTSCHLUESSELN_DAUER = 1.15
// Halbe Höhe der Zone um die Scanner-Linie, in der Zeichen noch rauschen.
const KANTE = 30
const RUHE_AB = ENTSCHLUESSELN_AB + ENTSCHLUESSELN_DAUER + 0.1

const SCAN_TAKT = 6.5
const SCAN_DAUER = 1.7
const SCAN_HOEHE = 64
const GLITCH_TAKT = 13
const GLITCH_DAUER = 0.22
const LINSE = 92

function glatt(a, b, x) {
  const t = Math.min(1, Math.max(0, (x - a) / (b - a)))
  return t * t * (3 - 2 * t)
}

// Fester Zufall je Zelle, damit das Bild beim Neuzeichnen nicht springt.
function zufall(i, salz = 0) {
  const x = Math.sin((i + 1) * 12.9898 + salz * 78.233) * 43758.5453
  return x - Math.floor(x)
}

export function start(scene) {
  const pfad = scene.querySelector('#logo-fill')
  if (!pfad || !window.Path2D) return
  const form = new Path2D(pfad.getAttribute('d'))
  const ruhig = matchMedia('(prefers-reduced-motion: reduce)').matches
  const feinerZeiger = matchMedia('(hover:hover) and (pointer:fine)').matches

  const canvas = document.createElement('canvas')
  canvas.className = 'logo-scene__code'
  canvas.setAttribute('aria-hidden', 'true')
  scene.insertBefore(canvas, scene.firstChild)
  const ctx = canvas.getContext('2d')
  // Zwei fertige Ebenen: nur das Codefeld (blendet im Einstieg als Ganzes
  // ein) und der komplette Ruhezustand (Feld plus Marke).
  const feld = document.createElement('canvas')
  const fctx = feld.getContext('2d')
  const ruhe = document.createElement('canvas')
  const rctx = ruhe.getContext('2d')

  let groesse = 0, dpr = 1, zelle = 10, spalten = 0
  let zellen = []          // Endraster: Zeichen-Zellen
  let grob = []            // je Auflösungsstufe die Logo-Blöcke
  let startZeit = 0, gestartet = false, laeuft = false, raf = 0
  // Ohne Beobachter gilt die Marke als sichtbar, sonst meldet er es.
  let imBild = !('IntersectionObserver' in window)
  let zeigerX = -1e4, zeigerY = -1e4, zeigerZeit = -1e4
  let warAktiv = true
  let schlafUhr = 0

  // Farbe entlang der Diagonale wie der Verlauf im SVG: hell oben links,
  // kräftig unten rechts.
  function logoFarbe(x, y, alpha) {
    const t = Math.min(1, Math.max(0, (x + y) / (2 * groesse)))
    const r = Math.round(169 + (26 - 169) * t * 0.85)
    const g = Math.round(200 + (107 - 200) * t * 0.85)
    return `rgba(${r},${g},255,${alpha.toFixed(3)})`
  }

  function maske(n) {
    const m = document.createElement('canvas')
    m.width = n; m.height = n
    const c = m.getContext('2d', { willReadFrequently: true })
    c.scale(n / VIEW, n / VIEW)
    c.fillStyle = '#fff'
    c.fill(form)
    return c.getImageData(0, 0, n, n).data
  }

  function aufbauen() {
    groesse = scene.clientWidth
    if (!groesse) return false
    dpr = Math.min(2, window.devicePixelRatio || 1)
    zelle = Math.max(8, Math.round(groesse / 76))
    spalten = Math.floor(groesse / zelle)
    for (const c of [canvas, feld, ruhe]) { c.width = Math.round(groesse * dpr); c.height = Math.round(groesse * dpr) }
    canvas.style.width = canvas.style.height = groesse + 'px'

    // Endraster: jede Zelle trägt ein Zeichen aus der Quelle, zeilenweise
    // gelesen wie ein Text. Logo-Zellen leuchten, die übrigen bilden ein
    // schwaches Codefeld in Form des Sechsecks.
    const daten = maske(spalten)
    const schritt = groesse / spalten
    zellen = []
    for (let r = 0; r < spalten; r++) {
      for (let s = 0; s < spalten; s++) {
        const i = r * spalten + s
        const deckung = daten[i * 4 + 3] / 255
        const x = (s + 0.5) * schritt, y = (r + 0.5) * schritt
        const vx = (x / groesse) * VIEW - MITTE, vy = (y / groesse) * VIEW - MITTE
        const hex = Math.max(Math.abs(vx), Math.abs(vx) * 0.5 + Math.abs(vy) * 0.866) / INNEN
        const feld = 0.065 * (1 - glatt(0.82, 1.2, hex))
        const logo = deckung > 0.35
        if (!logo && feld < 0.004) continue
        zellen.push({
          i, x, y, logo,
          zeichen: QUELLE[(i + 17) % QUELLE.length],
          alpha: logo ? 0.5 + 0.4 * deckung : feld,
          farbe: '',
          los: zufall(i),
          // Etwa jedes neunte Zeichen der Marke glüht: Treffer im Code.
          heiss: logo && zufall(i, 7) > 0.89,
        })
      }
    }
    for (const z of zellen) {
      z.farbe = z.heiss ? 'rgba(232,241,255,.96)' : z.logo ? logoFarbe(z.x, z.y, z.alpha) : `rgba(169,200,255,${z.alpha.toFixed(3)})`
    }

    grob = STUFEN.map((st) => {
      const n = Math.max(2, Math.round(groesse / st.zelle))
      const d = maske(n)
      const g = groesse / n
      const bloecke = []
      for (let r = 0; r < n; r++) for (let s = 0; s < n; s++) {
        const a = d[(r * n + s) * 4 + 3] / 255
        if (a > 0.3) bloecke.push({ x: s * g, y: r * g, g, a, los: zufall(r * n + s, n) })
      }
      return bloecke
    })

    // Beide Ebenen einmal fertig zeichnen.
    fctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    fctx.clearRect(0, 0, groesse, groesse)
    schrift(fctx)
    for (const z of zellen) if (!z.logo) { fctx.fillStyle = z.farbe; fctx.fillText(z.zeichen, z.x, z.y) }
    rctx.setTransform(1, 0, 0, 1, 0, 0)
    rctx.clearRect(0, 0, ruhe.width, ruhe.height)
    rctx.drawImage(feld, 0, 0)
    rctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    schrift(rctx, true)
    for (const z of zellen) if (z.logo) { rctx.fillStyle = z.farbe; rctx.fillText(z.zeichen, z.x, z.y) }
    return true
  }

  // Die Marke in kräftigem Schnitt, das Feld in normalem: So hebt sich die
  // Form ab, obwohl beides aus demselben Text besteht.
  function schrift(c, fett) {
    c.font = `${fett ? 700 : 400} ${Math.round(zelle * 1.08)}px ${MONO}`
    c.textAlign = 'center'
    c.textBaseline = 'middle'
  }

  function rauschen(z, t) {
    return RAUSCHEN[Math.floor(zufall(z.i, Math.floor(t * 16)) * RAUSCHEN.length)]
  }

  function bloeckeZeichnen(bloecke, t, ab, bis) {
    for (const b of bloecke) {
      const sicht = glatt(ab + b.los * 0.18, ab + b.los * 0.18 + 0.12, t) * (1 - glatt(bis - 0.04, bis + 0.06, t))
      if (sicht <= 0) continue
      const flackern = 0.75 + 0.25 * zufall(Math.floor(t * 20), b.los * 100)
      ctx.fillStyle = logoFarbe(b.x, b.y, 0.5 * b.a * sicht * flackern)
      const luft = Math.max(1, b.g * 0.08)
      ctx.fillRect(b.x + luft, b.y + luft, b.g - 2 * luft, b.g - 2 * luft)
    }
  }

  // Leuchtende Scanner-Kante: eine helle Linie über die Breite des
  // Sechsecks, dahinter (oben) ein weicher Schein.
  function scannerLinie(y, a) {
    const rand = groesse * 0.06, breite = groesse * 0.88
    ctx.save()
    ctx.translate(groesse / 2, y)
    ctx.scale(breite / 2, 46)
    const schein = ctx.createRadialGradient(0, 0, 0, 0, 0, 1)
    schein.addColorStop(0, `rgba(64,132,255,${(0.22 * a).toFixed(3)})`)
    schein.addColorStop(1, 'rgba(26,107,255,0)')
    ctx.fillStyle = schein
    ctx.fillRect(-1, -1, 2, 1)
    ctx.restore()
    const linie = ctx.createLinearGradient(rand, 0, rand + breite, 0)
    linie.addColorStop(0, 'rgba(127,174,255,0)')
    linie.addColorStop(0.5, `rgba(226,237,255,${(0.95 * a).toFixed(3)})`)
    linie.addColorStop(1, 'rgba(127,174,255,0)')
    ctx.fillStyle = linie
    ctx.fillRect(rand, y - 1, breite, 2)
  }

  // Einstieg: alles wird pro Bild neu gezeichnet (knapp drei Sekunden).
  // Erst drei Pixelstufen, dann fährt die Scanner-Linie von oben nach unten:
  // darüber steht schon Code, darunter noch die feinste Pixelstufe.
  function einstieg(t) {
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    ctx.clearRect(0, 0, groesse, groesse)
    const letzte = STUFEN.length - 1
    if (t < ENTSCHLUESSELN_AB) {
      STUFEN.forEach((st, k) => {
        if (t >= st.ab - 0.05 && t <= st.bis + 0.1) bloeckeZeichnen(grob[k], t, st.ab, k === letzte ? 99 : st.bis)
      })
      return
    }
    const p = Math.min(1, (t - ENTSCHLUESSELN_AB) / ENTSCHLUESSELN_DAUER)
    const e = p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2
    const linie = -KANTE + e * (groesse + 2 * KANTE)

    ctx.save()
    ctx.beginPath(); ctx.rect(0, linie, groesse, groesse); ctx.clip()
    bloeckeZeichnen(grob[letzte], 9, 0, 99)
    ctx.restore()

    if (linie > 0) {
      ctx.save()
      ctx.beginPath(); ctx.rect(0, 0, groesse, linie); ctx.clip()
      ctx.setTransform(1, 0, 0, 1, 0, 0)
      ctx.drawImage(feld, 0, 0)
      ctx.restore()
    }

    ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    schrift(ctx, true)
    for (const z of zellen) {
      if (!z.logo) continue
      const d = z.y - linie
      if (d > KANTE * 0.5) continue
      if (d < -KANTE) {
        ctx.fillStyle = z.farbe
        ctx.fillText(z.zeichen, z.x, z.y)
      } else {
        const nah = 1 - Math.abs(d) / KANTE
        ctx.fillStyle = `rgba(${Math.round(190 + 60 * nah)},${Math.round(214 + 36 * nah)},255,${(0.65 + 0.35 * nah).toFixed(3)})`
        ctx.fillText(rauschen(z, t), z.x, z.y)
      }
    }
    scannerLinie(linie, 1 - glatt(0.88, 1, p))
  }

  // Ruhe: Zwischenebene hinlegen, darauf nur die Zellen, die gerade
  // gescannt, freigelegt oder neu gerendert werden.
  function ruhephase(t) {
    const seitRuhe = t - RUHE_AB
    const scanP = ((seitRuhe + SCAN_TAKT - 0.8) % SCAN_TAKT) / SCAN_DAUER
    const scanY = scanP <= 1 ? -SCAN_HOEHE + scanP * (groesse + 2 * SCAN_HOEHE) : null
    const glitchP = (seitRuhe % GLITCH_TAKT) - (GLITCH_TAKT - GLITCH_DAUER)
    const glitch = glitchP > 0 && seitRuhe > GLITCH_TAKT * 0.5
    const linse = feinerZeiger && t - zeigerZeit < 1.4
    const aktiv = scanY !== null || glitch || linse
    if (!aktiv && !warAktiv) return
    warAktiv = aktiv

    ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    ctx.clearRect(0, 0, groesse, groesse)
    if (glitch) {
      // Ein Augenblick grob: dieselbe Stufe wie beim Einstieg, dann scharf.
      bloeckeZeichnen(grob[1], 9, 0, 99)
      return
    }
    ctx.setTransform(1, 0, 0, 1, 0, 0)
    ctx.drawImage(ruhe, 0, 0)
    if (!aktiv) return
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    const halb = zelle / 2
    const linseAus = linse ? 1 - glatt(0.9, 1.4, t - zeigerZeit) : 0
    // Zwei Durchgänge, damit die Schrift nur zweimal pro Bild wechselt:
    // erst das Feld (normal), dann die Marke (kräftig).
    for (const fett of [false, true]) {
      schrift(ctx, fett)
      for (const z of zellen) {
        if (z.logo !== fett) continue
        let staerke = 0
        if (scanY !== null) {
          // Hinter der Linie (oben) klingt das Leuchten langsam ab, vor ihr
          // (unten) beginnt es erst kurz vorher.
          const d = z.y - scanY
          if (d < 0 && d > -SCAN_HOEHE) staerke = 1 + d / SCAN_HOEHE
          else if (d >= 0 && d < SCAN_HOEHE * 0.25) staerke = 1 - d / (SCAN_HOEHE * 0.25)
        }
        if (linseAus > 0) {
          const d = Math.hypot(z.x - zeigerX, z.y - zeigerY)
          if (d < LINSE) staerke = Math.max(staerke, (1 - d / LINSE) * linseAus)
        }
        if (staerke <= 0.02) continue
        ctx.clearRect(z.x - halb, z.y - halb, zelle, zelle)
        const zeichen = staerke > 0.6 && zufall(z.i, Math.floor(t * 12)) > 0.5 ? rauschen(z, t) : z.zeichen
        ctx.fillStyle = z.logo
          ? `rgba(${Math.round(169 + 86 * staerke)},${Math.round(200 + 55 * staerke)},255,${Math.min(1, z.alpha + 0.5 * staerke).toFixed(3)})`
          : `rgba(127,174,255,${Math.min(0.55, z.alpha + 0.4 * staerke).toFixed(3)})`
        ctx.fillText(zeichen, z.x, z.y)
      }
    }
    if (scanY !== null) scannerLinie(scanY, 0.85)
  }

  // Zwischen zwei Scans passiert nichts: Dann schläft die Schleife bis kurz
  // vor dem nächsten Ereignis, statt leere Bilder anzufordern. Eine
  // Mausbewegung weckt sie sofort.
  function bisZumNaechsten(t) {
    const seitRuhe = t - RUHE_AB
    const bisScan = SCAN_TAKT - ((seitRuhe + SCAN_TAKT - 0.8) % SCAN_TAKT)
    const bisGlitch = GLITCH_TAKT - GLITCH_DAUER - (seitRuhe % GLITCH_TAKT)
    return Math.min(bisScan, bisGlitch > 0 ? bisGlitch : GLITCH_TAKT)
  }

  function bild(jetzt) {
    raf = 0
    if (!laeuft) return
    const t = (jetzt - startZeit) / 1000
    if (t < RUHE_AB) einstieg(t)
    else ruhephase(t)
    if (t >= RUHE_AB && !warAktiv) {
      const pause = bisZumNaechsten(t) - 0.05
      if (pause > 0.1) {
        schlafUhr = setTimeout(() => { schlafUhr = 0; if (laeuft && !raf) raf = requestAnimationFrame(bild) }, pause * 1000)
        return
      }
    }
    raf = requestAnimationFrame(bild)
  }

  function wecken() {
    if (schlafUhr) { clearTimeout(schlafUhr); schlafUhr = 0 }
    if (laeuft && !raf) raf = requestAnimationFrame(bild)
  }

  // Der Einstieg beginnt, wenn die Marke zum ersten Mal wirklich im Bild
  // ist, nicht schon beim Laden einer Seite, die weiter unten aufgeht.
  function weiter() {
    laeuft = imBild && !document.hidden && !ruhig
    if (laeuft && !gestartet) { gestartet = true; startZeit = performance.now() }
    if (laeuft) wecken()
    else if (schlafUhr) { clearTimeout(schlafUhr); schlafUhr = 0 }
  }

  if (!aufbauen()) { canvas.remove(); return }
  scene.classList.add('logo-scene--code')

  if (ruhig) {
    ctx.drawImage(ruhe, 0, 0)
    return
  }

  if ('IntersectionObserver' in window) {
    new IntersectionObserver((e) => { imBild = e[0].isIntersecting; weiter() }).observe(scene)
  }
  document.addEventListener('visibilitychange', weiter)

  if (feinerZeiger) {
    const hero = scene.closest('.hero') || document
    hero.addEventListener('pointermove', (e) => {
      const r = canvas.getBoundingClientRect()
      zeigerX = (e.clientX - r.left) * (groesse / r.width)
      zeigerY = (e.clientY - r.top) * (groesse / r.height)
      zeigerZeit = (performance.now() - startZeit) / 1000
      warAktiv = true
      wecken()
    }, { passive: true })
  }

  let neuUhr = 0
  if (window.ResizeObserver) {
    new ResizeObserver(() => {
      clearTimeout(neuUhr)
      neuUhr = setTimeout(() => {
        if (scene.clientWidth && Math.abs(scene.clientWidth - groesse) > 2) { aufbauen(); warAktiv = true; wecken() }
      }, 160)
    }).observe(scene)
  }

  weiter()
}
