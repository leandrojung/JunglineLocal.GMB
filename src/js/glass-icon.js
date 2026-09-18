// ---------------------------------------------------------------------------
// GLASS-INSEL ("Arbeitsprobe", /webdesign/)
//
// Bindet die unveraenderte Originkit-Komponente
// src/components/LiquidGlassCluster.tsx in die ansonsten vollstaendig
// vanilla gebaute Seite ein. Die Komponente selbst wird NICHT angefasst —
// alles Projektspezifische (wann sie laedt, womit sie gefuettert wird, wann
// sie wieder abgeraeumt wird) steht hier.
//
// Drei Dinge waren beim Einbau wichtig:
//
//   1. NICHTS KOSTET, WER SIE NICHT SIEHT.
//      Weder Preact noch die Komponente stehen im Haupt-Bundle. Der
//      dynamische import() legt sie in einen eigenen Chunk, der erst faellt,
//      wenn der Abschnitt in die Naehe des Sichtfensters kommt. Wer die Seite
//      oben wieder verlaesst, hat die Datei nie geladen.
//
//   2. SIE LAEUFT NUR, SOLANGE SIE ZU SEHEN IST.
//      Die Komponente haelt eine requestAnimationFrame-Schleife mit einem
//      Raymarching-Shader. Liefe die im Hintergrund weiter, waehrend der
//      Besucher drei Abschnitte tiefer liest, kostet das auf einem Telefon
//      spuerbar Akku. Verlaesst der Block das Sichtfeld, wird die Insel
//      abgeraeumt (render(null, ...) ruft die Aufraeum-Funktion der
//      Komponente auf und bricht die Schleife ab) und beim Zurueckscrollen
//      neu aufgebaut.
//
//   3. SIE IST SCHMUCK, KEIN INHALT.
//      Ohne WebGL, ohne JavaScript, bei "Bewegung reduzieren" oder im
//      Datensparmodus bleibt schlicht die statische Fassung stehen, die im
//      HTML steht. Es geht dabei keine Information verloren — die Aussage des
//      Abschnitts steht im Text daneben.
// ---------------------------------------------------------------------------

const HOST = document.querySelector('[data-glass-icon]')
// Der Zustandsschalter sitzt auf dem aeusseren Block: Der Hinweistext unter
// der Buehne haengt daran und ist kein Geschwister der Buehne selbst.
const BLOCK = HOST ? HOST.closest('.wd-glass') || HOST : null

// Vorab pruefen, ob sich der Aufwand ueberhaupt lohnt. Jede dieser Bremsen
// fuehrt zum selben Ergebnis: Die statische Fassung bleibt stehen, es wird
// nichts nachgeladen.
function darfLaufen() {
  if (!HOST) return false
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return false
  // Datensparmodus des Browsers respektieren.
  const verbindung = navigator.connection
  if (verbindung && verbindung.saveData) return false
  // Sehr schwache Geraete (unter vier Kernen) tragen einen Raymarching-Shader
  // nicht fluessig; ruckelnde Zierde ist schlechter als gar keine.
  if (typeof navigator.hardwareConcurrency === 'number' && navigator.hardwareConcurrency < 4) return false
  // WebGL ueberhaupt vorhanden? Der Testkontext wird sofort wieder freigegeben.
  try {
    const probe = document.createElement('canvas')
    const ctx = probe.getContext('webgl2') || probe.getContext('webgl')
    if (!ctx) return false
    const verlust = ctx.getExtension('WEBGL_lose_context')
    if (verlust) verlust.loseContext()
  } catch (e) {
    return false
  }
  return true
}

if (darfLaufen()) {
  let preact = null
  let Komponente = null
  let laedt = false
  let montiert = false

  // Die Schriftgroesse der Hintergrund-Schrift haengt an der Breite des
  // Blocks, nicht an einem festen Wert: Das laengste Wort ist "JUNGLINE" mit
  // acht Zeichen, bei Bricolage in 800 also rund fuenf Geviert breit. Ein
  // Sechstel der Blockbreite laesst links und rechts Luft, auf dem Telefon
  // wie am grossen Bildschirm.
  function schriftgroesse() {
    const breite = HOST.clientWidth || 640
    return Math.max(34, Math.min(150, Math.round(breite / 6.2)))
  }

  // Auf dem Telefon ist der Rahmen fast quadratisch, am Rechner breit. Die
  // Komponente bemisst ihre Groesse an der HOEHE des Bildausschnitts — bliebe
  // der Wert gleich, saehe der Koerper auf dem Telefon verloren klein aus.
  function koerpergroesse() {
    return (HOST.clientWidth || 640) < 700 ? 74 : 62
  }

  function eigenschaften() {
    return {
      // Die Form ist die Bildmarke der Seite selbst — dieselbe Geometrie wie
      // im Kopfbereich der SEO-Startseite, nur als Glaskoerper.
      shape: 'Logo',
      logo: '/logo-glass.png',
      background: '#061B33',
      size: koerpergroesse(),
      depth: 30,
      speed: 34,
      direction: 'Clockwise',
      // Die Schrift dahinter ist nicht Dekoration, sondern der Zweck: Ohne
      // etwas, das sich brechen kann, sieht Glas aus wie eine graue Flaeche.
      backdrop: {
        type: 'Text',
        text: 'JUNGLINE\nLOCAL',
        textColor: '#14477F',
        font: {
          fontFamily: "'Bricolage Grotesque', system-ui, sans-serif",
          fontSize: schriftgroesse(),
          fontWeight: 800,
          letterSpacing: -2,
          lineHeight: 1.02,
        },
      },
      glass: { tint: '#E8F1FF', chromatic: 30, frost: 16 },
      orient: { angleX: 0, angleY: 0, angleZ: 0, offsetX: 0, offsetY: 0 },
    }
  }

  function zeichnen() {
    if (!preact || !Komponente) return
    preact.render(preact.h(Komponente, eigenschaften()), HOST)
    montiert = true
    BLOCK.classList.add('is-live')
  }

  function abraeumen() {
    if (!montiert || !preact) return
    // render(null, …) ruft die Aufraeum-Funktion des useEffect auf: Die
    // rAF-Schleife wird abgebrochen, Zeiger-Ereignisse werden abgemeldet.
    preact.render(null, HOST)
    montiert = false
    BLOCK.classList.remove('is-live')
  }

  async function starten() {
    if (montiert || laedt) return
    laedt = true
    try {
      const [p, k] = await Promise.all([
        import('preact'),
        import('../components/LiquidGlassCluster.tsx'),
      ])
      preact = p
      Komponente = k.default
      zeichnen()
    } catch (e) {
      // Nachladen fehlgeschlagen (Netz weg, Datei fehlt): Die statische
      // Fassung steht weiterhin, es passiert sichtbar nichts.
      console.warn('Glass-Insel konnte nicht geladen werden:', e)
    } finally {
      laedt = false
    }
  }

  // Grosszuegiger Rand: laden, bevor der Block wirklich zu sehen ist —
  // abraeumen erst, wenn er deutlich weg ist. Ein schmaler Rand wuerde beim
  // langsamen Scrollen an der Kante staendig auf- und abbauen.
  let imSichtfeld = false
  if ('IntersectionObserver' in window) {
    const beobachter = new IntersectionObserver(
      (eintraege) => {
        for (const eintrag of eintraege) {
          imSichtfeld = eintrag.isIntersecting
          if (imSichtfeld) starten()
          else abraeumen()
        }
      },
      { rootMargin: '300px 0px' },
    )
    beobachter.observe(HOST)
  } else {
    imSichtfeld = true
    starten()
  }

  // Das Bild hinter dem Glas wird in der Geraeteaufloesung gebacken. Aendert
  // sich die Breite (Drehen des Telefons, Fenstergroesse), muss die
  // Schriftgroesse mit — die Komponente backt die Flaeche dann selbst neu,
  // weil sich ihr Schrift-Schluessel geaendert hat.
  let wartet = 0
  addEventListener('resize', () => {
    if (!montiert) return
    clearTimeout(wartet)
    wartet = setTimeout(zeichnen, 180)
  })

  // Im Hintergrundtab nicht weiterrechnen. requestAnimationFrame pausiert
  // dort zwar von sich aus, aber nicht in jedem Browser zuverlaessig. Kommt
  // der Tab zurueck und der Block steht noch im Sichtfeld, wird die Insel
  // wieder aufgebaut — ohne das bliebe nach dem Tabwechsel eine tote Flaeche
  // stehen, denn der Beobachter meldet sich nicht erneut.
  addEventListener('visibilitychange', () => {
    if (document.hidden) abraeumen()
    else if (imSichtfeld) starten()
  })
}
