/* ============================================================
   BILDMARKE ALS WORTBILD (Hero der Startseite)
   ============================================================
   Die Bildmarke ist aus echten lokalen Suchanfragen gesetzt: "Dachdecker
   Dorsten", "Zahnarzt Lembeck", "Physiotherapie Marl" und so weiter. Genau
   die Suchen, bei denen Jungline Local Betriebe nach oben bringt. Gesetzt
   wird wie im Buchsatz: ganze Wörter, Zeile für Zeile in die Form
   eingepasst und auf die Breite ausgetrieben, nichts abgeschnitten.

   Einstieg: erst grobe Fläche, dann feiner, dann Schrift (40 px, 20 px,
   10 px, Wörter). Jede Stufe breitet sich von der Mitte nach außen aus wie
   ein Suchradius. Keine Linie, kein Rauschen, kein Flackern.

   Ruhe: Alle gut drei Sekunden hebt sich eine einzelne Suchanfrage weiß
   heraus, als hätte gerade jemand genau danach gesucht. Nur Anfragen, die
   nicht hinter der Karte liegen. Mit der Maus werden Wörter in der Nähe
   etwas heller (nur Desktop).

   Technik:
     * ein Canvas, höchstens doppelte Pixeldichte
     * der fertige Satz liegt als Zwischenebene bereit; pro Bild werden nur
       die Wörter neu gezeichnet, die sich gerade ändern
     * ändert sich nichts, schläft die Schleife bis zum nächsten Ereignis
     * nichts läuft außerhalb des Bildes oder im Hintergrund-Tab
     * "Bewegung reduzieren": sofort der fertige Satz
   Die Form kommt aus dem SVG im HTML (#logo-fill). Linie und blasse Fläche
   bleiben stehen: als Rahmen, als Körper der Form hinter der Schrift und
   ohne Skript als Rückfall. Der Canvas ist rein
   dekorativ (.hero__bg ist aria-hidden). */

const VIEW = 375
const MITTE = 187.5

const BRANCHEN = [
  'Dachdecker', 'Zahnarzt', 'Physiotherapie', 'Friseur', 'Anwalt', 'Steuerberater',
  'Elektriker', 'Heizung & Sanitär', 'Kfz-Werkstatt', 'Immobilienmakler', 'Tierarzt',
  'Café', 'Malerbetrieb', 'Fahrschule', 'Optiker', 'Kosmetik', 'Bäckerei', 'Hausarzt',
  'Tischlerei', 'Fitnessstudio', 'Pflegedienst', 'Restaurant',
]
const ORTE = [
  'Dorsten', 'Lembeck', 'Wulfen', 'Holsterhausen', 'Haltern', 'Marl', 'Gladbeck',
  'Bottrop', 'Schermbeck', 'Raesfeld', 'Borken', 'Heiden', 'Reken', 'Dülmen',
  'Herten', 'Datteln', 'Recklinghausen', 'Coesfeld',
]

const SCHRIFT = '"Instrument Sans", "Instrument Fallback", system-ui, sans-serif'

// Zeitplan des Einstiegs in Sekunden. Passt zum Nachzeichnen der Linie im
// SVG (site.css: jlDraw ab 0,35 s, 2,2 s lang).
const STUFEN = [
  { ab: 0.15, zelle: 40 },
  { ab: 0.5, zelle: 20 },
  { ab: 0.85, zelle: 10 },
]
const WOERTER_AB = 1.2
const AUSBREITUNG = 0.7   // so lange braucht eine Stufe von der Mitte bis zum Rand
const EINBLENDEN = 0.32   // so lange braucht ein einzelnes Element
const RUHE_AB = WOERTER_AB + AUSBREITUNG + EINBLENDEN + 0.1

// Hervorhebung im Ruhezustand: einblenden, halten, ausblenden, Pause.
const TAKT = 3.4
const AN = 0.5, HALTEN = 1.3, AUS = 0.6

function glatt(a, b, x) {
  const t = Math.min(1, Math.max(0, (x - a) / (b - a)))
  return t * t * (3 - 2 * t)
}

// Fester Zufall: gleiche Eingabe, gleiches Ergebnis.
function zufall(i, salz = 0) {
  const x = Math.sin((i + 1) * 12.9898 + salz * 78.233) * 43758.5453
  return x - Math.floor(x)
}

// Suchanfragen: Branche plus Ort. Jede behält ihre Nummer, damit sie
// später als Ganzes hervorgehoben werden kann. Die Branche steht halbfett,
// der Ort normal; "Heizung & Sanitär" bleibt ein Begriff.
function anfragen() {
  const liste = []
  for (let k = 0; k < 500; k++) {
    const branche = BRANCHEN[Math.floor(zufall(k, 1) * BRANCHEN.length)]
    const ort = ORTE[Math.floor(zufall(k, 2) * ORTE.length)]
    liste.push({ nr: k, teile: [{ text: branche, fett: true }, { text: ort, fett: false }] })
  }
  return liste
}

export async function start(scene) {
  const pfad = scene.querySelector('#logo-fill')
  if (!pfad || !window.Path2D) return
  const form = new Path2D(pfad.getAttribute('d'))
  const ruhig = matchMedia('(prefers-reduced-motion: reduce)').matches
  const feinerZeiger = matchMedia('(hover:hover) and (pointer:fine)').matches

  const canvas = document.createElement('canvas')
  canvas.className = 'logo-scene__wort'
  canvas.setAttribute('aria-hidden', 'true')
  const ctx = canvas.getContext('2d')
  const satz = document.createElement('canvas')
  const sctx = satz.getContext('2d')

  let groesse = 0, dpr = 1, grad = 10, zeile = 12
  let woerter = [], bloecke = []
  let startZeit = 0, gestartet = false, laeuft = false, raf = 0, schlafUhr = 0
  let imBild = !('IntersectionObserver' in window)
  let zeigerX = -1e4, zeigerY = -1e4, zeigerZeit = -1e4
  let zyklus = -1, auswahl = null, letzterStand = ''
  // Wörter, die im letzten Bild anders als im fertigen Satz standen, und ob
  // die Ruhe schon einmal komplett gezeichnet wurde.
  let zuletzt = new Map(), ruheSteht = false

  const schrift = (c, fett = true) => {
    c.font = `${fett ? 600 : 400} ${grad}px ${SCHRIFT}`
    c.textBaseline = 'middle'
    c.textAlign = 'left'
  }
  const breiteVon = (teil) => { schrift(sctx, teil.fett); return sctx.measureText(teil.text).width }

  // Farbe entlang der Diagonale wie der Verlauf im SVG.
  function farbe(x, y, alpha) {
    const t = Math.min(1, Math.max(0, (x + y) / (2 * groesse)))
    return `rgba(${Math.round(183 - 106 * t)},${Math.round(208 - 67 * t)},255,${alpha.toFixed(3)})`
  }

  // Abstand zur Mitte der Marke, 0 bis 1: bestimmt die Reihenfolge.
  function radius(x, y) {
    return Math.min(1, Math.hypot(x - groesse / 2, y - groesse / 2) / (groesse * 0.46))
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

  function setzen() {
    groesse = scene.clientWidth
    if (!groesse) return false
    dpr = Math.min(2, window.devicePixelRatio || 1)
    grad = Math.max(8.5, Math.round(groesse / 80 * 10) / 10)
    zeile = Math.round(grad * 1.24 * 10) / 10
    for (const c of [canvas, satz]) { c.width = Math.round(groesse * dpr); c.height = Math.round(groesse * dpr) }
    canvas.style.width = canvas.style.height = groesse + 'px'

    // Satz: Zeile für Zeile die Strecken suchen, die innerhalb der Form
    // liegen (oben und unten im Schriftband geprüft), und sie mit ganzen
    // Wörtern füllen. Restplatz wird auf die Wortabstände verteilt.
    const n = Math.round(groesse)
    const daten = maske(n)
    const drin = (x, y) => {
      const xi = Math.round(x), yi = Math.round(y)
      return xi >= 0 && yi >= 0 && xi < n && yi < n && daten[(yi * n + xi) * 4 + 3] > 140
    }
    const liste = anfragen()
    schrift(sctx)
    const leer = sctx.measureText(' ').width
    // Teile der Reihe nach; eine Anfrage bleibt möglichst auf einer Zeile.
    const teile = []
    for (const a of liste) a.teile.forEach((t, j) => teile.push({ ...t, anfrage: a.nr, letzter: j === a.teile.length - 1, w: breiteVon(t) }))
    let pos = 0
    woerter = []
    for (let y = zeile / 2 + 1; y < groesse - zeile / 2; y += zeile) {
      const oben = y - grad * 0.45, unten = y + grad * 0.45
      let x = 0
      while (x < groesse) {
        while (x < groesse && !(drin(x, oben) && drin(x, unten))) x++
        const x0 = x
        while (x < groesse && drin(x, oben) && drin(x, unten)) x++
        const breite = x - x0 - 4
        if (breite < grad * 2.4) continue
        // Füllen: Passt die ganze Anfrage, kommt sie ganz; sonst nur ihr
        // nächster Teil. Ein zu langer Teil wird übersprungen, damit keine
        // leeren Strecken bleiben.
        const reihe = []
        let belegt = 0, versuche = 0
        while (versuche < 4) {
          const t = teile[pos % teile.length]
          const neu = belegt + (reihe.length ? leer : 0) + t.w
          if (neu <= breite) { reihe.push(t); belegt = neu; pos++; versuche = 0 }
          else { versuche++; if (!reihe.length) pos++; else break }
        }
        if (!reihe.length) continue
        const luecken = reihe.length - 1
        const rest = breite - belegt
        // Austreiben nur, solange die Wortabstände ruhig bleiben, sonst mittig.
        const extra = luecken && rest / luecken <= leer * 1.6 ? rest / luecken : 0
        let cx = x0 + 2 + (extra ? 0 : rest / 2)
        for (const t of reihe) {
          woerter.push({
            text: t.text, fett: t.fett, anfrage: t.anfrage, x: cx, y, w: t.w,
            farbe: farbe(cx + t.w / 2, y, t.fett ? 0.86 : 0.58),
            folge: radius(cx + t.w / 2, y) * 0.85 + zufall(woerter.length, 3) * 0.15,
          })
          cx += t.w + leer + extra
        }
      }
    }

    // Grobe Stufen des Einstiegs.
    bloecke = STUFEN.map((st) => {
      const m = Math.max(2, Math.round(groesse / st.zelle))
      const d = maske(m)
      const g = groesse / m
      const liste = []
      for (let r = 0; r < m; r++) for (let s = 0; s < m; s++) {
        const a = d[(r * m + s) * 4 + 3] / 255
        if (a < 0.3) continue
        const x = s * g, y = r * g
        liste.push({ x, y, g, a, folge: radius(x + g / 2, y + g / 2) * 0.85 + zufall(r * m + s, m) * 0.15 })
      }
      return liste
    })

    // Fertiger Satz als Zwischenebene (zwei Durchgänge: halbfett, normal).
    sctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    sctx.clearRect(0, 0, groesse, groesse)
    for (const fett of [true, false]) {
      schrift(sctx, fett)
      for (const w of woerter) if (w.fett === fett) { sctx.fillStyle = w.farbe; sctx.fillText(w.text, w.x, w.y) }
    }
    return true
  }

  // Anteil, zu dem ein Element mit gegebener Folge zur Zeit t sichtbar ist,
  // wenn seine Stufe bei "ab" beginnt.
  const auf = (folge, ab, t) => glatt(0, 1, (t - ab - folge * AUSBREITUNG) / EINBLENDEN)

  function einstieg(t) {
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    ctx.clearRect(0, 0, groesse, groesse)
    STUFEN.forEach((st, k) => {
      const naechste = k + 1 < STUFEN.length ? STUFEN[k + 1].ab : WOERTER_AB
      for (const b of bloecke[k]) {
        const sicht = auf(b.folge, st.ab, t) * (1 - auf(b.folge, naechste, t))
        if (sicht <= 0.01) continue
        const luft = Math.max(1, b.g * 0.12)
        ctx.fillStyle = farbe(b.x + b.g / 2, b.y + b.g / 2, 0.26 * b.a * sicht)
        ctx.fillRect(b.x + luft, b.y + luft, b.g - 2 * luft, b.g - 2 * luft)
      }
    })
    if (t < WOERTER_AB) return
    for (const fett of [true, false]) {
      schrift(ctx, fett)
      for (const w of woerter) {
        if (w.fett !== fett) continue
        const sicht = auf(w.folge, WOERTER_AB, t)
        if (sicht <= 0.01) continue
        ctx.globalAlpha = sicht
        ctx.fillStyle = w.farbe
        ctx.fillText(w.text, w.x, w.y)
      }
    }
    ctx.globalAlpha = 1
  }

  // Rechtecke, hinter denen nichts hervorgehoben werden soll (Karte und
  // Mitteilungen), in Canvas-Koordinaten.
  function verdeckt() {
    const r = canvas.getBoundingClientRect()
    if (!r.width) return []
    const k = groesse / r.width
    return Array.from(document.querySelectorAll('.hero .rankcard, .hero .inote, .hero .hero__caption')).map((el) => {
      const b = el.getBoundingClientRect()
      return { l: (b.left - r.left) * k - 6, r: (b.right - r.left) * k + 6, o: (b.top - r.top) * k - 6, u: (b.bottom - r.top) * k + 6 }
    })
  }

  function waehlen(nr) {
    const decken = verdeckt()
    const frei = (w) => !decken.some((d) => w.x + w.w > d.l && w.x < d.r && w.y + zeile / 2 > d.o && w.y - zeile / 2 < d.u)
    // Anfragen, deren Wörter alle zu sehen sind.
    const gruppen = new Map()
    for (const w of woerter) {
      const g = gruppen.get(w.anfrage) || { woerter: [], frei: true }
      g.woerter.push(w)
      if (!frei(w)) g.frei = false
      gruppen.set(w.anfrage, g)
    }
    const kandidaten = Array.from(gruppen.values()).filter((g) => g.frei && g.woerter.length > 1)
    if (!kandidaten.length) return null
    return kandidaten[Math.floor(zufall(nr, 9) * kandidaten.length)].woerter
  }

  function ruhephase(t) {
    const seit = t - RUHE_AB
    const nr = Math.floor(seit / TAKT)
    const phase = seit - nr * TAKT
    if (nr !== zyklus) { zyklus = nr; auswahl = waehlen(nr) }
    const hell = phase < AN ? glatt(0, AN, phase)
      : phase < AN + HALTEN ? 1
      : glatt(AN + HALTEN + AUS, AN + HALTEN, phase)
    const naeheAus = feinerZeiger ? 1 - glatt(0.6, 1.2, t - zeigerZeit) : 0

    // Nur zeichnen, wenn sich etwas sichtbar ändert.
    const stand = `${zyklus}:${Math.round(hell * 14)}:${naeheAus > 0 ? Math.round(zeigerX) + ',' + Math.round(zeigerY) + ',' + Math.round(naeheAus * 20) : ''}`
    if (stand === letzterStand) return
    letzterStand = stand

    const neu = new Map()
    if (auswahl && hell > 0) for (const w of auswahl) neu.set(w, hell)
    if (naeheAus > 0) {
      for (const w of woerter) {
        const d = Math.hypot(w.x + w.w / 2 - zeigerX, w.y - zeigerY)
        if (d < 90) neu.set(w, Math.max(neu.get(w) || 0, (1 - d / 90) * 0.55 * naeheAus))
      }
    }

    // Beim ersten Mal den ganzen Satz hinlegen, danach nur die Rechtecke der
    // Wörter erneuern, die sich ändern oder eben noch hell waren: Stück aus
    // dem fertigen Satz zurückholen, hell darüberschreiben.
    if (!ruheSteht) {
      ctx.setTransform(1, 0, 0, 1, 0, 0)
      ctx.clearRect(0, 0, canvas.width, canvas.height)
      ctx.drawImage(satz, 0, 0)
      ruheSteht = true
      zuletzt = new Map()
    }
    const erneuern = new Set([...zuletzt.keys(), ...neu.keys()])
    for (const w of erneuern) {
      const x = Math.floor((w.x - 1) * dpr), y = Math.floor((w.y - zeile / 2) * dpr)
      const b = Math.ceil((w.w + 2) * dpr), h = Math.ceil(zeile * dpr)
      ctx.setTransform(1, 0, 0, 1, 0, 0)
      ctx.clearRect(x, y, b, h)
      ctx.drawImage(satz, x, y, b, h, x, y, b, h)
      const s = neu.get(w)
      if (!s) continue
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
      schrift(ctx, w.fett)
      ctx.clearRect(w.x - 1, w.y - zeile / 2, w.w + 2, zeile)
      ctx.fillStyle = farbeHell(w, s)
      ctx.fillText(w.text, w.x, w.y)
    }
    zuletzt = neu
  }

  function farbeHell(w, s) {
    const t = Math.min(1, Math.max(0, (w.x + w.y) / (2 * groesse)))
    const r = 183 - 106 * t, g = 208 - 67 * t
    const basis = w.fett ? 0.86 : 0.58
    return `rgba(${Math.round(r + (255 - r) * s)},${Math.round(g + (255 - g) * s)},255,${(basis + (1 - basis) * s).toFixed(3)})`
  }

  // Wie lange bis sich wieder etwas ändert? Solange schläft die Schleife.
  function ruheBis(t) {
    if (feinerZeiger && t - zeigerZeit < 1.2) return 0
    const phase = (t - RUHE_AB) % TAKT
    if (phase >= AN && phase < AN + HALTEN) return AN + HALTEN - phase
    if (phase >= AN + HALTEN + AUS) return TAKT - phase
    return 0
  }

  function bild(jetzt) {
    raf = 0
    if (!laeuft) return
    const t = (jetzt - startZeit) / 1000
    if (t < RUHE_AB) einstieg(t)
    else {
      ruhephase(t)
      const pause = ruheBis(t)
      if (pause > 0.08) {
        schlafUhr = setTimeout(() => { schlafUhr = 0; wecken() }, pause * 1000)
        return
      }
    }
    raf = requestAnimationFrame(bild)
  }

  function wecken() {
    if (schlafUhr) { clearTimeout(schlafUhr); schlafUhr = 0 }
    if (laeuft && !raf) raf = requestAnimationFrame(bild)
  }

  // Der Einstieg beginnt, wenn die Marke zum ersten Mal wirklich im Bild ist.
  function weiter() {
    laeuft = imBild && !document.hidden && !ruhig
    if (laeuft && !gestartet) { gestartet = true; startZeit = performance.now() }
    if (laeuft) wecken()
    else if (schlafUhr) { clearTimeout(schlafUhr); schlafUhr = 0 }
  }

  // Erst setzen, wenn die Markenschrift da ist, sonst stimmen die Breiten nicht.
  try {
    if (document.fonts && document.fonts.load) await Promise.all([document.fonts.load(`600 12px ${SCHRIFT}`), document.fonts.load(`400 12px ${SCHRIFT}`)])
  } catch (e) { /* Ersatzschrift */ }
  scene.insertBefore(canvas, scene.firstChild)
  if (!setzen()) { canvas.remove(); return }
  scene.classList.add('logo-scene--wort')

  if (ruhig) {
    ctx.drawImage(satz, 0, 0)
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
      if (!r.width) return
      zeigerX = (e.clientX - r.left) * (groesse / r.width)
      zeigerY = (e.clientY - r.top) * (groesse / r.height)
      zeigerZeit = (performance.now() - startZeit) / 1000
      wecken()
    }, { passive: true })
  }

  let neuUhr = 0
  if (window.ResizeObserver) {
    new ResizeObserver(() => {
      clearTimeout(neuUhr)
      neuUhr = setTimeout(() => {
        if (scene.clientWidth && Math.abs(scene.clientWidth - groesse) > 2) {
          setzen(); letzterStand = ''; zyklus = -1; ruheSteht = false
          if (startZeit && (performance.now() - startZeit) / 1000 >= RUHE_AB) ruhephase((performance.now() - startZeit) / 1000)
          wecken()
        }
      }, 160)
    }).observe(scene)
  }

  weiter()
}
