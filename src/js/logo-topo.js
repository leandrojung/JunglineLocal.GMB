/* ============================================================
   BILDMARKE ALS TOPOGRAFIE (Hero der Startseite)
   ============================================================
   "Oben bei Google" als Landkarte: Die Bildmarke ist ein Berg, gezeichnet
   mit Höhenlinien wie auf einer Wanderkarte. Ganz oben steht die
   Gipfelmarke "Platz 1", unten die Koordinaten von Dorsten.

   Einstieg: Eine flache Karte mit sanften Höhenlinien öffnet sich von der
   Mitte aus. Dann wächst die Marke aus dem Gelände; die Linien ziehen sich
   zu vielen versetzten Konturen um die Form zusammen.
   Ruhe: Das Gelände atmet sehr langsam. Kein Streifen, kein Flackern.
   Maus (nur Desktop): Unter dem Zeiger hebt sich ein kleiner Hügel, die
   Höhenlinien weichen ihm aus.

   So funktioniert es:
     * Ein Höhenfeld auf einem Raster (etwa alle 6 px ein Wert) aus drei
       Teilen: die weichgezeichnete Form der Marke, ein langsam wanderndes
       Rauschen für das Gelände, der Hügel unter dem Zeiger.
     * Daraus werden pro Bild die Höhenlinien berechnet (Marching Squares:
       pro Rasterzelle ein kurzes Linienstück je geschnittener Höhe) und je
       Höhe als ein Pfad gezeichnet.
     * Zum Rand hin blenden die Linien über einen radialen Verlauf aus.

   Rechenzeit, damit es auch in Safari und auf schwächeren Geräten ruhig
   bleibt (teuer ist nicht die Rechnung, rund 2 ms, sondern das Zeichnen
   tausender kurzer Linienstücke):
     * einfarbige Striche mit geraden Enden; das Ausblenden zum Rand ist
       eine einzige Maske am Ende, nicht ein Verlauf pro Strich
     * höchstens 1,5-fache Pixeldichte
     * in Ruhe ist der Berg selbst ein fertiges Bild: Innerhalb der Form
       wirkt das Gelände nicht (FEST_AB), dort ändert sich nichts. Pro Bild
       werden nur die Geländelinien drumherum neu gezeichnet. Komplett neu
       nur im Einstieg und solange die Maus einen Hügel formt.
     * höchstens 15 Bilder pro Sekunde in Ruhe, 36 mit Maus, 60 nur im
       Einstieg; braucht ein Bild länger als 12 ms, geht die Rate herunter
     * nichts läuft außerhalb des Bildes oder im Hintergrund-Tab
     * "Bewegung reduzieren": ein fertiges, stehendes Bild
     * auf dem Telefon wird die Datei gar nicht geladen (site.js)
   Die Form kommt aus dem SVG im HTML (#logo-fill). Ohne Skript oder wenn
   etwas schiefgeht, bleibt das SVG mit seiner Linie stehen. Der Canvas ist
   rein dekorativ (.hero__bg ist aria-hidden). */

const VIEW = 375
const SCHRIFT = '"Instrument Sans", "Instrument Fallback", system-ui, sans-serif'

// Höhenlinien: gleichmäßig verteilte Höhen zwischen UNTEN und OBEN.
const STUFEN = 20
const UNTEN = 0.05, OBEN = 0.97
const SCHRITT = (OBEN - UNTEN) / (STUFEN - 1)

// Anteile am Höhenfeld.
const MARKE = 0.74
const GELAENDE = 0.32
const HUEGEL = 0.2
// Ab diesem Wert der weichgezeichneten Form wirkt das Gelände nicht mehr:
// Dort ist die Höhe fest, und dieser Teil des Bildes muss in Ruhe nur ein
// einziges Mal gezeichnet werden.
const FEST_AB = 0.15

// Einstieg in Sekunden.
const OEFFNEN = [0, 1.1]      // Karte öffnet sich von der Mitte
const WACHSEN = [0.35, 2.15]  // Marke wächst aus dem Gelände
const BESCHRIFTEN = [1.9, 2.6]
const RUHE_AB = 2.6

const FPS_RUHE = 15, FPS_ZEIGER = 36, FPS_EINSTIEG = 60

function glatt(a, b, x) {
  const t = Math.min(1, Math.max(0, (x - a) / (b - a)))
  return t * t * (3 - 2 * t)
}
function wellig(x) { return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2 }

/* ---- Rauschen: Wertrauschen in drei Dimensionen (x, y, Zeit) ---- */
const PERM = new Uint8Array(512)
const WERTE = new Float32Array(256)
;(function () {
  let s = 1337
  const r = () => { s = (s * 16807) % 2147483647; return (s - 1) / 2147483646 }
  const p = Array.from({ length: 256 }, (_, i) => i)
  for (let i = 255; i > 0; i--) { const j = Math.floor(r() * (i + 1)); [p[i], p[j]] = [p[j], p[i]] }
  for (let i = 0; i < 512; i++) PERM[i] = p[i & 255]
  for (let i = 0; i < 256; i++) WERTE[i] = r()
})()
const sm = (t) => t * t * (3 - 2 * t)
function rauschen(x, y, z) {
  const xi = Math.floor(x), yi = Math.floor(y), zi = Math.floor(z)
  const xf = sm(x - xi), yf = sm(y - yi), zf = sm(z - zi)
  const X = xi & 255, Y = yi & 255, Z = zi & 255
  const w = (a, b, c) => WERTE[PERM[PERM[PERM[a] + b] + c]]
  const X1 = (X + 1) & 255, Y1 = (Y + 1) & 255, Z1 = (Z + 1) & 255
  const l = (a, b, t) => a + (b - a) * t
  return l(
    l(l(w(X, Y, Z), w(X1, Y, Z), xf), l(w(X, Y1, Z), w(X1, Y1, Z), xf), yf),
    l(l(w(X, Y, Z1), w(X1, Y, Z1), xf), l(w(X, Y1, Z1), w(X1, Y1, Z1), xf), yf),
    zf,
  )
}

export async function start(scene) {
  const pfad = scene.querySelector('#logo-fill')
  if (!pfad || !window.Path2D) return
  const form = new Path2D(pfad.getAttribute('d'))
  const ruhig = matchMedia('(prefers-reduced-motion: reduce)').matches
  const feinerZeiger = matchMedia('(hover:hover) and (pointer:fine)').matches

  const canvas = document.createElement('canvas')
  canvas.className = 'logo-scene__topo'
  canvas.setAttribute('aria-hidden', 'true')
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  let groesse = 0, dpr = 1, n = 0, zelle = 0
  let marke = null        // weichgezeichnete Form, Werte 0 bis 1
  let feld = null         // aktuelles Höhenfeld
  let farben = []         // Strichfarbe je Höhenlinie
  let randMaske = null    // radialer Verlauf zum Ausblenden am Rand
  let fest = null         // je Rasterzelle: 1, wenn ihre Höhe fest ist
  const berg = document.createElement('canvas')
  const bctx = berg.getContext('2d')
  let bergFertig = false
  let stuecke = []        // Linienstücke je Höhe, pro Bild neu gefüllt
  let zaehler = null
  let gipfel = null, fuss = null
  // Ob die Beschriftungen frei stehen (nichts liegt darüber); wird geprüft,
  // sobald Karte und Mitteilungen ihren Platz haben.
  let gipfelFrei = false, fussFrei = false, frageFrei = true
  let startZeit = 0, gestartet = false, laeuft = false, raf = 0, letztesBild = 0
  let imBild = !('IntersectionObserver' in window)
  // Obergrenze der Bildrate; sinkt, wenn ein Bild auf diesem Gerät zu teuer ist.
  let fpsGrenze = FPS_ZEIGER
  // Hügel unter dem Zeiger: Ziel, geglättete Lage, Stärke.
  let zielX = 0, zielY = 0, hX = 0, hY = 0, hStaerke = 0, zeigerZeit = -1e4, zeigerDrin = false

  function aufbauen() {
    groesse = scene.clientWidth
    if (!groesse) return false
    dpr = Math.min(1.5, window.devicePixelRatio || 1)
    for (const c of [canvas, berg]) { c.width = Math.round(groesse * dpr); c.height = Math.round(groesse * dpr) }
    canvas.style.width = canvas.style.height = groesse + 'px'
    bergFertig = false
    frageFrei = true

    // Raster: etwa alle 6 px ein Wert, höchstens 140 je Seite.
    n = Math.min(140, Math.max(60, Math.round(groesse / 6)))
    zelle = groesse / (n - 1)

    // Form der Marke auf das Raster bringen und weichzeichnen: So entsteht
    // am Rand der Form ein Hang, an dem sich die Höhenlinien drängen.
    const m = document.createElement('canvas')
    m.width = n; m.height = n
    const c = m.getContext('2d', { willReadFrequently: true })
    c.scale(n / VIEW, n / VIEW)
    c.fillStyle = '#fff'
    c.fill(form)
    const daten = c.getImageData(0, 0, n, n).data
    let a = new Float32Array(n * n)
    for (let i = 0; i < n * n; i++) a[i] = daten[i * 4 + 3] / 255
    const r = Math.max(1, Math.round(n / 70))
    for (let durchgang = 0; durchgang < 3; durchgang++) a = weich(a, n, r)
    marke = a
    feld = new Float32Array(n * n)
    fest = new Uint8Array((n - 1) * (n - 1))
    for (let j = 0; j < n - 1; j++) for (let i = 0; i < n - 1; i++) {
      const lo = Math.min(a[j * n + i], a[j * n + i + 1], a[(j + 1) * n + i], a[(j + 1) * n + i + 1])
      fest[j * (n - 1) + i] = lo >= FEST_AB ? 1 : 0
    }
    zaehler = new Int32Array(STUFEN)
    stuecke = Array.from({ length: STUFEN }, () => new Float32Array(4 * 4096))

    // Strichfarbe je Höhe: tiefe Linien blass, hohe hell.
    farben = []
    for (let k = 0; k < STUFEN; k++) {
      const h = k / (STUFEN - 1)
      const alpha = 0.2 + 0.78 * Math.pow(h, 0.9)
      farben.push(`rgba(${Math.round(120 + 105 * h)},${Math.round(165 + 72 * h)},255,${alpha.toFixed(3)})`)
    }
    // Zum Rand hin blenden alle Linien gemeinsam aus (eine Maske pro Bild).
    randMaske = ctx.createRadialGradient(groesse / 2, groesse / 2, groesse * 0.4, groesse / 2, groesse / 2, groesse * 0.52)
    randMaske.addColorStop(0, 'rgba(0,0,0,1)')
    randMaske.addColorStop(1, 'rgba(0,0,0,0)')

    // Beschriftung: Gipfel an der obersten Spitze der Marke, Koordinaten
    // unter der untersten. Beide Punkte aus dem SVG-Pfad (Ecken bei 23,6
    // und 351,4 im 375er Raster).
    gipfel = { x: groesse / 2, y: (23.6 / VIEW) * groesse }
    fuss = { x: groesse / 2, y: (351.4 / VIEW) * groesse }
    return true
  }

  // Waagerecht und senkrecht mitteln (Kastenfilter mit Radius r).
  function weich(q, n, r) {
    const tmp = new Float32Array(n * n), aus = new Float32Array(n * n)
    const breite = 2 * r + 1
    for (let y = 0; y < n; y++) {
      let summe = 0
      for (let x = -r; x <= r; x++) summe += q[y * n + Math.min(n - 1, Math.max(0, x))]
      for (let x = 0; x < n; x++) {
        tmp[y * n + x] = summe / breite
        summe += q[y * n + Math.min(n - 1, x + r + 1)] - q[y * n + Math.max(0, x - r)]
      }
    }
    for (let x = 0; x < n; x++) {
      let summe = 0
      for (let y = -r; y <= r; y++) summe += tmp[Math.min(n - 1, Math.max(0, y)) * n + x]
      for (let y = 0; y < n; y++) {
        aus[y * n + x] = summe / breite
        summe += tmp[Math.min(n - 1, y + r + 1) * n + x] - tmp[Math.max(0, y - r) * n + x]
      }
    }
    return aus
  }

  // Höhenfeld für Zeit t: Marke (wachsend), Gelände (wandernd), Hügel.
  function hoehen(t, wachsen) {
    const z = t * 0.045
    const skala = 3.2 / groesse
    const huegel = hStaerke > 0.001
    const hr2 = 2 * Math.pow(groesse * 0.065, 2)
    for (let j = 0; j < n; j++) {
      const py = j * zelle
      for (let i = 0; i < n; i++) {
        const px = i * zelle
        const idx = j * n + i
        const mk = marke[idx]
        // Gelände wirkt nur außerhalb der Form; ab FEST_AB ist die Höhe fest.
        const anteil = 1 - glatt(0, FEST_AB, mk * wachsen)
        let h = MARKE * wachsen * mk
        if (anteil > 0) {
          const g = rauschen(px * skala, py * skala, z) * 0.7 + rauschen(px * skala * 2.1 + 17, py * skala * 2.1 + 31, z * 1.6) * 0.3
          h += GELAENDE * g * anteil
        }
        if (huegel) {
          const dx = px - hX, dy = py - hY
          h += HUEGEL * hStaerke * Math.exp(-(dx * dx + dy * dy) / hr2)
        }
        feld[idx] = h
      }
    }
  }

  function stueck(k, x1, y1, x2, y2) {
    let buf = stuecke[k]
    const p = zaehler[k] * 4
    if (p + 4 > buf.length) {
      const neu = new Float32Array(buf.length * 2)
      neu.set(buf)
      stuecke[k] = buf = neu
    }
    buf[p] = x1; buf[p + 1] = y1; buf[p + 2] = x2; buf[p + 3] = y2
    zaehler[k]++
  }

  // Marching Squares, ein Durchgang über alle Zellen. Je Zelle nur die
  // Höhen, die sie wirklich schneidet.
  // auswahl: 0 alle Zellen, 1 nur bewegliche, 2 nur feste.
  function linien(auswahl) {
    zaehler.fill(0)
    const f = feld
    for (let j = 0; j < n - 1; j++) {
      const y0 = j * zelle, y1 = y0 + zelle
      for (let i = 0; i < n - 1; i++) {
        if (auswahl && (fest[j * (n - 1) + i] === 1) !== (auswahl === 2)) continue
        const a = f[j * n + i], b = f[j * n + i + 1], c = f[(j + 1) * n + i + 1], d = f[(j + 1) * n + i]
        const lo = Math.min(a, b, c, d), hi = Math.max(a, b, c, d)
        if (hi < UNTEN || lo > OBEN) continue
        let k0 = Math.ceil((lo - UNTEN) / SCHRITT), k1 = Math.floor((hi - UNTEN) / SCHRITT)
        if (k0 < 0) k0 = 0
        if (k1 > STUFEN - 1) k1 = STUFEN - 1
        const x0 = i * zelle, x1 = x0 + zelle
        for (let k = k0; k <= k1; k++) {
          const L = UNTEN + k * SCHRITT
          const fall = (a > L ? 8 : 0) | (b > L ? 4 : 0) | (c > L ? 2 : 0) | (d > L ? 1 : 0)
          if (fall === 0 || fall === 15) continue
          // Schnittpunkte auf den vier Kanten (oben, rechts, unten, links).
          const ox = x0 + (L - a) / (b - a) * zelle, oy = y0
          const rx = x1, ry = y0 + (L - b) / (c - b) * zelle
          const ux = x0 + (L - d) / (c - d) * zelle, uy = y1
          const lx = x0, ly = y0 + (L - a) / (d - a) * zelle
          switch (fall) {
            case 1: case 14: stueck(k, lx, ly, ux, uy); break
            case 2: case 13: stueck(k, ux, uy, rx, ry); break
            case 3: case 12: stueck(k, lx, ly, rx, ry); break
            case 4: case 11: stueck(k, ox, oy, rx, ry); break
            case 6: case 9: stueck(k, ox, oy, ux, uy); break
            case 7: case 8: stueck(k, lx, ly, ox, oy); break
            case 5: case 10: {
              // Sattel: über den Mittelwert der Zelle entscheiden.
              const mitte = (a + b + c + d) / 4 > L
              if ((fall === 5) === mitte) { stueck(k, lx, ly, ox, oy); stueck(k, ux, uy, rx, ry) }
              else { stueck(k, lx, ly, ux, uy); stueck(k, ox, oy, rx, ry) }
              break
            }
          }
        }
      }
    }
  }

  // Alle gesammelten Linienstücke in einen Kontext zeichnen, dann zum Rand
  // hin ausblenden.
  function striche(c) {
    c.lineCap = 'butt'
    for (let k = 0; k < STUFEN; k++) {
      const anzahl = zaehler[k]
      if (!anzahl) continue
      const buf = stuecke[k]
      c.beginPath()
      for (let s = 0; s < anzahl * 4; s += 4) { c.moveTo(buf[s], buf[s + 1]); c.lineTo(buf[s + 2], buf[s + 3]) }
      c.strokeStyle = farben[k]
      c.lineWidth = k > STUFEN * 0.7 ? 1.4 : 1.1
      c.stroke()
    }
    c.globalCompositeOperation = 'destination-in'
    c.fillStyle = randMaske
    c.fillRect(0, 0, groesse, groesse)
    c.globalCompositeOperation = 'source-over'
  }

  // Der feste Teil (die Form selbst) als fertiges Bild für die Ruhe.
  function bergZeichnen() {
    const merk = hStaerke
    hStaerke = 0
    hoehen(0, 1)
    hStaerke = merk
    linien(2)
    bctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    bctx.clearRect(0, 0, groesse, groesse)
    striche(bctx)
    bergFertig = true
  }

  function zeichnen(t) {
    // Beschriftung prüfen, kurz bevor sie erscheint, und einmal, wenn auch
    // die Mitteilungen im Hero stehen (die kommen erst nach gut 4 s).
    if (frageFrei && t >= BESCHRIFTEN[0] - 0.05) { freiPruefen(); frageFrei = t < 5 }
    const wachsen = wellig(glatt(WACHSEN[0], WACHSEN[1], t))
    const voll = t < RUHE_AB || hStaerke > 0
    if (!voll && !bergFertig) bergZeichnen()
    hoehen(t, wachsen)
    linien(voll ? 0 : 1)

    ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    ctx.clearRect(0, 0, groesse, groesse)
    const oeffnen = glatt(OEFFNEN[0], OEFFNEN[1], t)
    ctx.save()
    if (oeffnen < 1) {
      ctx.beginPath()
      ctx.arc(groesse / 2, groesse / 2, Math.max(1, oeffnen * groesse * 0.72), 0, Math.PI * 2)
      ctx.clip()
    }
    striche(ctx)
    ctx.restore()
    if (!voll) {
      ctx.setTransform(1, 0, 0, 1, 0, 0)
      ctx.drawImage(berg, 0, 0)
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    }
    beschriften(glatt(BESCHRIFTEN[0], BESCHRIFTEN[1], t))
  }

  // Gipfelmarke und Koordinaten, wie Beschriftungen auf einer Karte.
  // Lage in Canvas-Koordinaten: Rechteck je Beschriftung.
  function gipfelKasten() {
    const fs = Math.round(groesse / 62)
    return { l: gipfel.x - 7, o: gipfel.y - fs, r: gipfel.x + 14 + fs * 4.2, u: gipfel.y + fs * 0.6, fs }
  }
  function fussKasten() {
    const fs = Math.round(groesse / 72)
    return { l: fuss.x - fs * 6, o: fuss.y + 18 - fs, r: fuss.x + fs * 6, u: fuss.y + 18 + fs, fs }
  }

  // Liegt etwas über einer Beschriftung (Leiste, Karte, Mitteilungen,
  // Bildunterschrift), bleibt sie weg. Die Leiste wird so geprüft, wie sie
  // ganz oben auf der Seite steht.
  function freiPruefen() {
    const r = canvas.getBoundingClientRect()
    if (!r.width) return
    const k = groesse / r.width
    const decken = Array.from(document.querySelectorAll('.hero .rankcard, .hero .inote, .hero .hero__caption')).map((el) => {
      const b = el.getBoundingClientRect()
      return { l: (b.left - r.left) * k - 8, r: (b.right - r.left) * k + 8, o: (b.top - r.top) * k - 8, u: (b.bottom - r.top) * k + 8 }
    })
    const nav = document.querySelector('.nav__inner')
    if (nav) {
      const n = nav.getBoundingClientRect()
      // Leiste ist fest oben; ihre Unterkante in Canvas-Koordinaten bei
      // Scrollposition 0.
      const canvasObenSeite = r.top + window.scrollY
      decken.push({ l: -1e4, r: 1e4, o: -1e4, u: (n.bottom - canvasObenSeite) * k + 12 })
    }
    const frei = (q) => !decken.some((d) => q.r > d.l && q.l < d.r && q.u > d.o && q.o < d.u)
    gipfelFrei = frei(gipfelKasten())
    fussFrei = frei(fussKasten())
  }

  function beschriften(sicht) {
    if (sicht <= 0 || (!gipfelFrei && !fussFrei)) return
    ctx.save()
    ctx.globalAlpha = sicht
    ctx.textBaseline = 'middle'
    // Dunkler Schein hinter der Schrift, damit sie über den Linien lesbar ist.
    ctx.shadowColor = 'rgba(9,12,29,.95)'
    ctx.shadowBlur = 8
    if (gipfelFrei) {
      // Dreieck genau auf der Spitze der Marke, daneben "Platz 1".
      const g = gipfelKasten()
      ctx.fillStyle = '#E6EFFF'
      ctx.beginPath()
      ctx.moveTo(gipfel.x - 6, gipfel.y + 3); ctx.lineTo(gipfel.x, gipfel.y - 7); ctx.lineTo(gipfel.x + 6, gipfel.y + 3); ctx.closePath()
      ctx.fill()
      ctx.font = `600 ${g.fs}px ${SCHRIFT}`
      ctx.textAlign = 'left'
      ctx.fillText('Platz 1', gipfel.x + 12, gipfel.y - 1)
    }
    if (fussFrei) {
      // Koordinaten von Dorsten unter der unteren Spitze.
      const f = fussKasten()
      ctx.font = `500 ${f.fs}px ${SCHRIFT}`
      ctx.textAlign = 'center'
      ctx.fillStyle = 'rgba(169,200,255,.75)'
      ctx.fillText('51°39′ N   6°58′ O', fuss.x, fuss.y + 18)
    }
    ctx.restore()
  }

  function bild(jetzt) {
    raf = 0
    if (!laeuft) return
    const t = (jetzt - startZeit) / 1000
    // Hügel: gleitet dem Zeiger nach, blendet weich ein und aus.
    if (feinerZeiger) {
      const aktiv = zeigerDrin && t - zeigerZeit < 2.5
      hStaerke += ((aktiv ? 1 : 0) - hStaerke) * 0.08
      hX += (zielX - hX) * 0.18
      hY += (zielY - hY) * 0.18
      if (hStaerke < 0.002) hStaerke = 0
    }
    const rate = t < RUHE_AB ? FPS_EINSTIEG : Math.min(hStaerke > 0 ? FPS_ZEIGER : FPS_RUHE, fpsGrenze)
    if (jetzt - letztesBild >= 1000 / rate - 2) {
      const vorher = performance.now()
      zeichnen(t)
      const dauer = performance.now() - vorher
      letztesBild = jetzt
      // Zu teuer für dieses Gerät? Dann seltener zeichnen.
      if (t > RUHE_AB && dauer > 12) fpsGrenze = Math.max(12, Math.round(fpsGrenze * 0.75))
    }
    raf = requestAnimationFrame(bild)
  }

  function weiter() {
    laeuft = imBild && !document.hidden && !ruhig
    if (laeuft && !gestartet) { gestartet = true; startZeit = performance.now() }
    if (laeuft && !raf) raf = requestAnimationFrame(bild)
  }

  // Beschriftung braucht die Markenschrift.
  try {
    if (document.fonts && document.fonts.load) await Promise.all([document.fonts.load(`600 12px ${SCHRIFT}`), document.fonts.load(`500 12px ${SCHRIFT}`)])
  } catch (e) { /* Ersatzschrift */ }

  if (!aufbauen()) return
  scene.insertBefore(canvas, scene.firstChild)
  scene.classList.add('logo-scene--topo')

  if (ruhig) {
    zeichnen(RUHE_AB + 1)
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
      const x = (e.clientX - r.left) * (groesse / r.width)
      const y = (e.clientY - r.top) * (groesse / r.height)
      zeigerDrin = x > -40 && y > -40 && x < groesse + 40 && y < groesse + 40
      if (!zeigerDrin) return
      if (hStaerke === 0) { hX = x; hY = y }
      zielX = x; zielY = y
      zeigerZeit = (performance.now() - startZeit) / 1000
    }, { passive: true })
    hero.addEventListener('pointerleave', () => { zeigerDrin = false }, { passive: true })
  }

  let neuUhr = 0
  if (window.ResizeObserver) {
    new ResizeObserver(() => {
      clearTimeout(neuUhr)
      neuUhr = setTimeout(() => {
        if (!scene.clientWidth || Math.abs(scene.clientWidth - groesse) <= 2) return
        if (!aufbauen()) return
        if (!laeuft) zeichnen(gestartet ? (performance.now() - startZeit) / 1000 : RUHE_AB + 1)
      }, 160)
    }).observe(scene)
  }

  weiter()
}
