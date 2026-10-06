/* ============================================================
   BILDMARKE AUS GLAS, DIE ZUR SUCHLEISTE WIRD (Hero der Startseite)
   ============================================================
   Die Bildmarke ist ein dunkler, glänzender Glaskörper: gewölbte Kanten,
   helle Glanzkanten, Spiegelung wie unter Studiolicht, ein weicher Schatten
   darunter. Mit der Maus wandert ein Lichtreflex über das Glas.

   Einstieg: Das Glas gießt sich auf (die Form wächst aus ihrer Mitte), das
   Licht gleitet einmal über die Kanten.

   Scrollen: Die Marke rutscht unter der Karte hervor, wird flacher und
   fließt in ein gläsernes Suchfeld. Darin tippt sich mit dem Scrollen
   "Dachdecker Dorsten". Zurückscrollen macht daraus wieder die Marke.
   Das ist die Geschichte von Jungline Local in einer Bewegung: aus der
   Marke wird die Suche, bei der Kunden den Betrieb finden.

   So funktioniert es:
     * Die Form der Marke wird einmal als Abstandsfeld berechnet (für jeden
       Punkt: wie weit bis zum Rand, innen negativ). Aus dem Abstand ergibt
       sich die Wölbung der Kante, aus deren Neigung das Licht.
     * Das Suchfeld ist ein abgerundetes Rechteck, dessen Abstandsfeld sich
       direkt ausrechnen lässt. Beide Felder werden gemischt: So geht die
       eine Form fließend in die andere über.
     * Ein Shader (WebGL 2) zeichnet daraus das Glas. Pro Pixel sechs
       Texturzugriffe, sonst nur Rechnung.

   Rechenzeit:
     * Gezeichnet wird nur, wenn sich etwas ändert: im Einstieg, wenn die
       Maus sich bewegt, wenn gescrollt wird. In Ruhe: nichts.
     * höchstens 1,5-fache Pixeldichte
     * das Abstandsfeld (rund 35 ms) wird in zwei Hälften auf zwei Bilder
       verteilt, damit der Seitenaufbau nicht hakt

   Rückfall: Ohne WebGL 2, bei einem Shader-Fehler oder wenn der Browser den
   Grafikkontext verliert, bleibt das SVG im HTML stehen (Linie plus blasse
   Fläche). "Bewegung reduzieren": ein stehendes Glas, kein Morph beim
   Scrollen. Auf dem Telefon wird die Datei gar nicht geladen (site.js).
   Das Suchfeld ist Schmuck: .hero__bg ist aria-hidden. */

const VIEW = 375
const SDF_GROESSE = 512
const SCHRIFT = '"Instrument Sans", "Instrument Fallback", system-ui, sans-serif'
const SUCHE = 'Dachdecker Dorsten'

// Einstieg in Sekunden.
const EINBLENDEN = [0.1, 0.7]
const AUFGIESSEN = [0.15, 1.35]
const LICHTGLEITEN = [0.25, 1.9]
const EINSTIEG_ENDE = 2.0

function glatt(a, b, x) {
  const t = Math.min(1, Math.max(0, (x - a) / (b - a)))
  return t * t * (3 - 2 * t)
}
function sanft(x) { const t = Math.min(1, Math.max(0, x)); return t * t * t * (t * (t * 6 - 15) + 10) }

/* ---- Abstandsfeld (Felzenszwalb, exakt, in zwei Durchgängen) ---- */
const INF = 1e20
function edt1d(f, n, d, v, z) {
  let k = 0
  v[0] = 0; z[0] = -INF; z[1] = INF
  for (let q = 1; q < n; q++) {
    let s = ((f[q] + q * q) - (f[v[k]] + v[k] * v[k])) / (2 * q - 2 * v[k])
    while (s <= z[k]) { k--; s = ((f[q] + q * q) - (f[v[k]] + v[k] * v[k])) / (2 * q - 2 * v[k]) }
    k++; v[k] = q; z[k] = s; z[k + 1] = INF
  }
  k = 0
  for (let q = 0; q < n; q++) { while (z[k + 1] < q) k++; d[q] = (q - v[k]) * (q - v[k]) + f[v[k]] }
}
function edt(raster, n) {
  const f = new Float64Array(n), d = new Float64Array(n), v = new Int32Array(n), z = new Float64Array(n + 1)
  for (let x = 0; x < n; x++) {
    for (let y = 0; y < n; y++) f[y] = raster[y * n + x]
    edt1d(f, n, d, v, z)
    for (let y = 0; y < n; y++) raster[y * n + x] = d[y]
  }
  for (let y = 0; y < n; y++) {
    for (let x = 0; x < n; x++) f[x] = raster[y * n + x]
    edt1d(f, n, d, v, z)
    for (let x = 0; x < n; x++) raster[y * n + x] = d[x]
  }
}
const naechstesBild = () => new Promise((r) => requestAnimationFrame(() => r()))

/* ---- Shader ---- */
const VS = `#version 300 es
in vec2 aPos;
void main() { gl_Position = vec4(aPos, 0.0, 1.0); }`

const FS = `#version 300 es
precision highp float;
uniform sampler2D uSdf;
uniform vec2 uRes;        // Canvas in CSS-Pixeln (die Marke steht rechts oben darin)
uniform float uDpr;
uniform float uLogoS;     // Kantenlänge der Marke in CSS-Pixeln
uniform vec2 uC;          // aktueller Mittelpunkt der Form
uniform float uScale;     // aktuelle Größe der Marke (1 = ganz)
uniform vec2 uPill;       // halbe Breite und Höhe des Suchfelds
uniform float uMorph;     // 0 = Marke, 1 = Suchfeld
uniform float uInflate;   // Einstieg: 0 = dünn, 1 = volle Form
uniform float uAlpha;
uniform vec3 uKey;        // Richtung des Hauptlichts
uniform vec3 uMaus;       // Lage des Mauslichts (x, y in CSS-Pixeln) und Stärke
out vec4 farbe;

float sdMarke(vec2 r) {
  vec2 q = r / uScale;
  vec2 uv = q / uLogoS + 0.5;
  float d = texture(uSdf, uv).r * uLogoS;
  vec2 aussen = max(abs(uv - 0.5) - 0.5, 0.0) * uLogoS;
  return (d + length(aussen)) * uScale;
}
float sdPille(vec2 r) {
  vec2 q = abs(r) - uPill + vec2(uPill.y);
  return length(max(q, 0.0)) + min(max(q.x, q.y), 0.0) - uPill.y;
}
float feld(vec2 p) {
  vec2 r = p - uC;
  float d = mix(sdMarke(r), sdPille(r), uMorph);
  return d + (1.0 - uInflate) * 22.0 * uScale;
}

// Studiolicht: oben eine helle Fläche, darunter dunkel, unten ein schwacher
// Gegenschein. Das macht den Apple-Glanz auf geneigten Flächen.
vec3 studio(vec3 r) {
  float oben = smoothstep(0.15, 0.85, -r.y);
  float band = exp(-pow((-r.y - 0.55) * 4.0, 2.0));
  float unten = smoothstep(0.3, 0.95, r.y) * 0.35;
  vec3 c = vec3(0.04, 0.08, 0.2) + vec3(0.55, 0.72, 1.0) * oben * 0.55 + vec3(1.0) * band * 0.6 + vec3(0.25, 0.45, 1.0) * unten;
  return c;
}

void main() {
  vec2 p = vec2(gl_FragCoord.x, uRes.y * uDpr - gl_FragCoord.y) / uDpr;
  float d = feld(p);

  // Schatten: dieselbe Form, etwas tiefer und weich.
  float ds = feld(p - vec2(0.0, 14.0 * uScale));
  float schatten = (1.0 - smoothstep(-6.0, 34.0 * uScale + 6.0, ds)) * 0.42;

  if (d > 1.5) {
    farbe = vec4(vec3(0.0), schatten * uAlpha);
    return;
  }

  // Neigung der Kante aus dem Abstandsfeld.
  float e = 1.6;
  vec2 g = vec2(feld(p + vec2(e, 0.0)) - feld(p - vec2(e, 0.0)), feld(p + vec2(0.0, e)) - feld(p - vec2(0.0, e))) / (2.0 * e);
  float B = mix(18.0, 16.0, uMorph) * max(uScale, 0.6);
  float s = clamp(-d / B, 0.0, 1.0);
  float h = sqrt(max(1.0 - (1.0 - s) * (1.0 - s), 0.0));
  float steil = min((1.0 - s) / max(h, 0.06), 6.0);
  vec3 n = normalize(vec3(g * steil, 1.0));

  vec3 v = vec3(0.0, 0.0, 1.0);
  vec3 L = normalize(uKey);
  vec3 H = normalize(L + v);
  float diffus = max(dot(n, L), 0.0);
  float glanz = pow(max(dot(n, H), 0.0), 120.0);
  float glanzWeit = pow(max(dot(n, H), 0.0), 16.0);
  // Gegenlicht unten rechts: Licht, das durch das Glas wieder austritt.
  float kaustik = pow(max(dot(n, normalize(normalize(vec3(0.55, 0.85, 0.45)) + v)), 0.0), 28.0);
  vec3 Lm = normalize(vec3(uMaus.xy - p, 260.0));
  float glanzMaus = pow(max(dot(n, normalize(Lm + v)), 0.0), 140.0) * uMaus.z;
  float fresnel = pow(1.0 - max(n.z, 0.0), 2.5);
  vec3 r = reflect(-v, n);

  // Glaskörper: oben ein helleres, unten ein tiefes Blau, durchscheinend.
  // Der Verlauf läuft über die Höhe der aktuellen Form (Marke oder Feld).
  float tiefe = clamp((p.y - uC.y) / mix(uLogoS * uScale, uPill.y * 3.2, uMorph) + 0.5, 0.0, 1.0);
  vec3 koerper = mix(vec3(0.17, 0.36, 0.88), vec3(0.03, 0.08, 0.27), tiefe);
  vec3 c = koerper * (0.55 + 0.45 * diffus);
  // Licht im Glas: ein Schein knapp innerhalb der Kante.
  float innen = smoothstep(0.12, 0.5, s) * (1.0 - smoothstep(0.5, 1.0, s));
  c += vec3(0.35, 0.56, 1.0) * innen * 0.28;
  // Spiegelung des Studios, an den Kanten stärker (Fresnel).
  c += studio(r) * (0.12 + fresnel * 1.0);
  // Feststehender, weicher Glanzstreif auf den flachen Flächen (Softbox).
  float diag = ((p.x - uRes.x + uLogoS) * 0.8 - p.y) / uLogoS;
  float schimmer = exp(-pow((diag + 0.05) * 5.0, 2.0)) * s * 0.16;
  c += vec3(0.8, 0.9, 1.0) * schimmer;
  c += vec3(1.0) * (glanz + glanzWeit * 0.12);
  c += vec3(0.55, 0.85, 1.0) * kaustik * 0.45;
  c += vec3(0.85, 0.93, 1.0) * glanzMaus * 1.2;
  // Feine helle Kante genau am Rand.
  float kante = (1.0 - smoothstep(0.0, 1.6, abs(d + 0.9))) * 0.6;
  c += vec3(0.78, 0.88, 1.0) * kante;

  float deckung = 1.0 - smoothstep(-0.75, 0.75, d);
  float a = clamp(0.5 + fresnel * 0.45 + glanz * 0.5 + glanzMaus * 0.4 + kaustik * 0.2 + kante * 0.3 + schimmer, 0.0, 1.0) * deckung;
  // Schatten unter dem Glas mischen (vormultipliziertes Alpha).
  float aGes = a + schatten * (1.0 - a);
  vec3 cGes = c * a;
  farbe = vec4(cGes * uAlpha, aGes * uAlpha);
}`

export async function start(scene) {
  const pfad = scene.querySelector('#logo-fill')
  if (!pfad || !window.Path2D) return
  const ruhig = matchMedia('(prefers-reduced-motion: reduce)').matches
  // Einspaltiger Hero (Tablet hochkant): Die Karte steht unter dem Text,
  // nicht vor der Marke. Dort bleibt die Marke ohne Morph.
  const einspaltig = matchMedia('(max-width: 960px)')
  const feinerZeiger = matchMedia('(hover:hover) and (pointer:fine)').matches
  const hero = scene.closest('.hero')
  if (!hero) return

  const canvas = document.createElement('canvas')
  canvas.className = 'logo-scene__glas'
  canvas.setAttribute('aria-hidden', 'true')
  let gl = null
  try { gl = canvas.getContext('webgl2', { premultipliedAlpha: true, antialias: false, alpha: true }) } catch (e) { gl = null }
  if (!gl) return

  // ---- Shader bauen; bei einem Fehler bleibt das SVG stehen ----
  function shader(typ, quelle) {
    const s = gl.createShader(typ)
    gl.shaderSource(s, quelle)
    gl.compileShader(s)
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(s) || 'Shader')
    return s
  }
  let prog
  try {
    prog = gl.createProgram()
    gl.attachShader(prog, shader(gl.VERTEX_SHADER, VS))
    gl.attachShader(prog, shader(gl.FRAGMENT_SHADER, FS))
    gl.linkProgram(prog)
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(prog) || 'Link')
  } catch (e) {
    return
  }
  gl.useProgram(prog)
  const puffer = gl.createBuffer()
  gl.bindBuffer(gl.ARRAY_BUFFER, puffer)
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW)
  const aPos = gl.getAttribLocation(prog, 'aPos')
  gl.enableVertexAttribArray(aPos)
  gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0)
  const U = {}
  for (const name of ['uSdf', 'uRes', 'uDpr', 'uLogoS', 'uC', 'uScale', 'uPill', 'uMorph', 'uInflate', 'uAlpha', 'uKey', 'uMaus']) U[name] = gl.getUniformLocation(prog, name)
  gl.disable(gl.DEPTH_TEST)
  gl.enable(gl.BLEND)
  gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA)

  // ---- Abstandsfeld der Marke, verteilt auf zwei Bilder ----
  const M = SDF_GROESSE
  const maske = document.createElement('canvas')
  maske.width = M; maske.height = M
  const mctx = maske.getContext('2d', { willReadFrequently: true })
  mctx.scale(M / VIEW, M / VIEW)
  mctx.fillStyle = '#fff'
  mctx.fill(new Path2D(pfad.getAttribute('d')))
  const daten = mctx.getImageData(0, 0, M, M).data
  const aussen = new Float64Array(M * M), innen = new Float64Array(M * M)
  for (let i = 0; i < M * M; i++) {
    const drin = daten[i * 4 + 3] > 127
    aussen[i] = drin ? 0 : INF
    innen[i] = drin ? INF : 0
  }
  await naechstesBild()
  edt(aussen, M)
  await naechstesBild()
  edt(innen, M)
  const sdf = new Float32Array(M * M)
  for (let i = 0; i < M * M; i++) {
    const d = aussen[i] > 0 ? Math.sqrt(aussen[i]) - 0.5 : -(Math.sqrt(innen[i]) - 0.5)
    sdf[i] = d / M
  }
  const tex = gl.createTexture()
  gl.bindTexture(gl.TEXTURE_2D, tex)
  gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1)
  gl.texImage2D(gl.TEXTURE_2D, 0, gl.R16F, M, M, 0, gl.RED, gl.FLOAT, sdf)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE)
  gl.uniform1i(U.uSdf, 0)
  if (gl.getError() !== gl.NO_ERROR) return

  // ---- Suchfeld-Beschriftung (HTML über dem Glas) ----
  const suche = document.createElement('div')
  suche.className = 'logo-suche'
  suche.setAttribute('aria-hidden', 'true')
  suche.innerHTML = '<svg class="logo-suche__lupe" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4.5 4.5"/></svg><span class="logo-suche__text"></span><span class="logo-suche__cursor"></span>'
  const sucheText = suche.querySelector('.logo-suche__text')

  // ---- Maße ----
  // groesse: Kantenlänge der Marke. Der Canvas reicht so weit nach unten
  // (hoehe) und nach links (links), dass das Suchfeld samt Schatten mittig
  // unter der Karte darauf passt; die Marke steht rechts oben darin.
  let groesse = 0, hoehe = 0, links = 0, dpr = 1
  let pille = { x: 0, y: 0, hb: 230, hh: 30 }
  let morphStart = 0, morphEnde = 400
  // Nur wenn das Suchfeld unter Karte und Mitteilungen frei Platz hat, bevor
  // der nächste Abschnitt (er schiebt sich mit runden Ecken über den Hero)
  // oder das Ende des Heros kommt. Bei schmalen oder sehr niedrigen Fenstern
  // reicht der Platz nicht; dann bleibt die Marke einfach stehen.
  let morphPasst = true
  function messen() {
    groesse = scene.clientWidth
    if (!groesse) return false
    dpr = Math.min(1.5, window.devicePixelRatio || 1)

    // Das Suchfeld liegt unter der Karte (und unter den Mitteilungen),
    // mittig zur Karte.
    const r = scene.getBoundingClientRect()
    const k = groesse / r.width
    const karte = hero.querySelector('.rankcard')
    let unten = groesse * 0.82, mitteX = groesse / 2, breite = groesse * 0.6
    if (karte) {
      const b = karte.getBoundingClientRect()
      unten = (b.bottom - r.top) * k
      mitteX = ((b.left + b.right) / 2 - r.left) * k
      breite = b.width * k
    }
    hero.querySelectorAll('.hero__notes .inote, .hero__caption').forEach((el) => {
      const b = el.getBoundingClientRect()
      if (b.height) unten = Math.max(unten, (b.bottom - r.top) * k)
    })
    let boden = (hero.getBoundingClientRect().bottom - r.top) * k
    const folgt = hero.nextElementSibling
    if (folgt) {
      const t = (folgt.getBoundingClientRect().top - r.top) * k
      if (t > 0) boden = Math.min(boden, t)
    }
    const hh = Math.round(Math.max(24, Math.min(28, groesse * 0.036)))
    const hb = Math.round(Math.min(breite * 0.5, groesse * 0.34))
    const y = unten + 16 + hh
    morphPasst = !ruhig && !einspaltig.matches && y + hh + 14 <= boden
    const x = Math.min(groesse - hb - 12, mitteX)
    pille = { x, y, hb, hh }

    hoehe = morphPasst ? Math.max(groesse, Math.ceil(y + hh + 40)) : groesse
    links = morphPasst ? Math.max(0, Math.ceil(hb + 12 - x)) : 0
    canvas.width = Math.round((groesse + links) * dpr)
    canvas.height = Math.round(hoehe * dpr)
    canvas.style.left = -links + 'px'
    canvas.style.width = (groesse + links) + 'px'
    canvas.style.height = hoehe + 'px'
    gl.viewport(0, 0, canvas.width, canvas.height)

    // Scrollweg: Der Morph ist fertig, wenn das Suchfeld bei gut 45 % der
    // Fensterhöhe steht.
    const pilleSeiteY = r.top + window.scrollY + y / k
    morphStart = 10
    morphEnde = Math.max(260, pilleSeiteY - window.innerHeight * 0.45)

    // Beschriftung auf das Suchfeld legen.
    suche.style.left = (x - hb) + 'px'
    suche.style.top = (y - hh) + 'px'
    suche.style.width = (2 * hb) + 'px'
    suche.style.height = (2 * hh) + 'px'
    suche.style.fontSize = Math.round(hh * 0.62) + 'px'
    return true
  }

  // ---- Zustand ----
  let startZeit = 0, raf = 0, laeuft = false, imBild = true
  let fortschritt = 0
  let mausX = -1e4, mausY = -1e4, mausZiel = 0, mausAmt = 0
  let verloren = false
  let nachmessenOffen = false

  function scrollFortschritt() {
    if (!morphPasst || einspaltig.matches) return 0
    return Math.min(1, Math.max(0, (window.scrollY - morphStart) / (morphEnde - morphStart)))
  }

  function zeichnen(jetzt) {
    if (verloren) return
    const t = (jetzt - startZeit) / 1000
    const e = sanft(fortschritt)
    const logoC = groesse / 2
    const cx = links + logoC + (pille.x - logoC) * e
    const cy = logoC + (pille.y - logoC) * e
    const skala = 1 - 0.45 * e
    const morph = glatt(0.12, 0.8, e)
    // Hauptlicht: gleitet im Einstieg von links oben in seine Ruhelage.
    const gleit = ruhig ? 1 : sanft((t - LICHTGLEITEN[0]) / (LICHTGLEITEN[1] - LICHTGLEITEN[0]))
    const kx = -1.6 + 1.05 * gleit
    gl.clearColor(0, 0, 0, 0)
    gl.clear(gl.COLOR_BUFFER_BIT)
    gl.uniform2f(U.uRes, groesse + links, hoehe)
    gl.uniform1f(U.uDpr, dpr)
    gl.uniform1f(U.uLogoS, groesse)
    gl.uniform2f(U.uC, cx, cy)
    gl.uniform1f(U.uScale, skala)
    gl.uniform2f(U.uPill, pille.hb, pille.hh)
    gl.uniform1f(U.uMorph, morph)
    gl.uniform1f(U.uInflate, ruhig ? 1 : sanft((t - AUFGIESSEN[0]) / (AUFGIESSEN[1] - AUFGIESSEN[0])))
    gl.uniform1f(U.uAlpha, ruhig ? 1 : glatt(EINBLENDEN[0], EINBLENDEN[1], t))
    gl.uniform3f(U.uKey, kx, -0.8, 0.9)
    gl.uniform3f(U.uMaus, mausX, mausY, mausAmt)
    gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4)

    // Suchfeld-Beschriftung: erscheint, wenn die Form schon ein Feld ist,
    // und wandert mit dem Glas, bis es an seinem Platz steht. Der Text
    // tippt sich mit dem Scrollen.
    const sicht = glatt(0.86, 0.97, e)
    suche.style.opacity = sicht.toFixed(3)
    suche.style.transform = sicht > 0 ? `translate(${(cx - links - pille.x).toFixed(1)}px,${(cy - pille.y).toFixed(1)}px)` : ''
    const zeichen = Math.round(glatt(0.84, 1, fortschritt) * SUCHE.length)
    if (sucheText.textContent.length !== zeichen) sucheText.textContent = SUCHE.slice(0, zeichen)
    suche.classList.toggle('is-fertig', zeichen === SUCHE.length)
  }

  // Nur zeichnen, wenn sich etwas ändert.
  let letzterStand = ''
  function bild(jetzt) {
    raf = 0
    if (!laeuft || verloren) return
    const t = (jetzt - startZeit) / 1000
    mausAmt += (mausZiel - mausAmt) * 0.12
    if (Math.abs(mausAmt - mausZiel) < 0.003) mausAmt = mausZiel
    const imEinstieg = !ruhig && t < EINSTIEG_ENDE
    const stand = `${fortschritt.toFixed(4)}|${Math.round(mausX)},${Math.round(mausY)},${mausAmt.toFixed(3)}`
    if (imEinstieg || stand !== letzterStand) {
      zeichnen(jetzt)
      letzterStand = stand
    }
    if (imEinstieg || mausAmt !== mausZiel) raf = requestAnimationFrame(bild)
  }
  function anstossen() { if (laeuft && !raf) raf = requestAnimationFrame(bild) }

  function weiter() {
    laeuft = imBild && !document.hidden
    anstossen()
  }

  // ---- Einhängen ----
  if (!messen()) return
  try { if (document.fonts && document.fonts.load) await document.fonts.load(`500 16px ${SCHRIFT}`) } catch (e) { /* Ersatzschrift */ }
  scene.insertBefore(canvas, scene.firstChild)
  scene.appendChild(suche)
  scene.classList.add('logo-scene--glas')
  startZeit = performance.now()
  fortschritt = scrollFortschritt()

  canvas.addEventListener('webglcontextlost', (e) => {
    // Grafikkontext verloren (z. B. Treiber neu gestartet): zurück zum SVG.
    e.preventDefault()
    verloren = true
    scene.classList.remove('logo-scene--glas')
    canvas.remove()
    suche.remove()
  })

  if ('IntersectionObserver' in window) {
    imBild = false
    new IntersectionObserver((e) => { imBild = e[0].isIntersecting; weiter() }).observe(scene)
  }
  document.addEventListener('visibilitychange', weiter)

  if (!ruhig) {
    const naechsterFortschritt = () => {
      const f = scrollFortschritt()
      if (nachmessenOffen && f < 0.02) { nachmessenOffen = false; neuMessen() }
      if (f !== fortschritt) { fortschritt = f; anstossen() }
    }
    if (window.jlScrollTakt) window.jlScrollTakt(naechsterFortschritt)
    else window.addEventListener('scroll', naechsterFortschritt, { passive: true })
  }

  if (feinerZeiger && !ruhig) {
    hero.addEventListener('pointermove', (e) => {
      const r = canvas.getBoundingClientRect()
      if (!r.width) return
      const k = (groesse + links) / r.width
      mausX = (e.clientX - r.left) * k
      mausY = (e.clientY - r.top) * k
      mausZiel = 1
      anstossen()
    }, { passive: true })
    hero.addEventListener('pointerleave', () => { mausZiel = 0; anstossen() }, { passive: true })
  }

  let neuUhr = 0
  const neuMessen = () => {
    clearTimeout(neuUhr)
    neuUhr = setTimeout(() => {
      if (!messen()) return
      fortschritt = scrollFortschritt()
      letzterStand = ''
      zeichnen(performance.now())
    }, 160)
  }
  if (window.ResizeObserver) new ResizeObserver(neuMessen).observe(scene)
  // Karte und Mitteilungen stehen erst nach ihrem Einstieg an ihrem Platz;
  // das Suchfeld richtet sich danach einmal neu aus, aber nie mitten im
  // Morph (es würde springen), sondern sobald die Seite wieder oben steht.
  setTimeout(() => { if (fortschritt < 0.02) neuMessen(); else nachmessenOffen = true }, 5200)

  zeichnen(performance.now())
  weiter()
}
