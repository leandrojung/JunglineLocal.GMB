/* ============================================================
   PAGESPEED-CHECK — Live-Geschwindigkeitsmessung auf /webdesign/
   ============================================================
   Wird von site.js erst nachgeladen, wenn das Widget in Sichtweite kommt
   (siehe dort) — auf allen anderen Seiten passiert hier nichts.

   Bewusst dieselbe Formsprache wie der GBP-Check auf der Startseite:
   ein Zustands-Attribut (data-state) am Wrapper, alle vier Zustände
   (form/loading/result/error) stehen fertig im HTML, CSS zeigt nur den
   passenden. Der Server entscheidet NIE über Layout — er liefert Zahlen,
   dieses Modul liefert Sätze. */

const root = document.getElementById('pscWidget');
if (root) {
  const form = document.getElementById('pscForm');
  const input = document.getElementById('psc-url');
  const errorText = document.getElementById('pscErrorText');
  const resultUrl = document.getElementById('pscResultUrl');
  const scoreNum = document.getElementById('pscScoreNum');
  const ringValue = document.getElementById('pscRingValue');
  const lcpEl = document.getElementById('pscLcp');
  const verdictEl = document.getElementById('pscVerdict');

  const CIRC = 2 * Math.PI * 52;
  if (ringValue) {
    ringValue.style.strokeDasharray = CIRC.toFixed(2);
    ringValue.style.strokeDashoffset = CIRC.toFixed(2);
  }

  const setState = (state) => root.setAttribute('data-state', state);

  // Übersetzt die Fehlerkennung des Backends in einen Satz, den ein Kunde
  // versteht — ohne technische Details (Statuscodes, Google-Meldungen),
  // die nur dem helfen, der die Sperre umgehen will. Dieselbe Zurückhaltung
  // wie bei ERROR_TEXTS im GBP-Check.
  const ERROR_TEXTS = {
    invalid_url: 'Das sieht nicht nach einer gültigen Internetadresse aus. Bitte prüfen Sie die Schreibweise, z. B. „ihre-firma.de".',
    could_not_check: 'Ihre Seite konnte automatisch nicht geprüft werden — manche Seiten blockieren automatisierte Aufrufe. Rufen Sie mich gern direkt an, dann schaue ich manuell nach.',
    rate_limited: 'Sie haben den Check gerade mehrfach hintereinander gestartet. Bitte warten Sie ein paar Minuten und versuchen Sie es dann noch einmal.',
    daily_limit_reached: 'Der kostenlose Check ist für heute ausgebucht. Morgen früh steht er wieder zur Verfügung — oder Sie schreiben mir kurz, dann prüfe ich Ihre Seite persönlich.',
    forbidden_origin: 'Der Check lässt sich nur direkt auf jungline.de starten. Bitte laden Sie die Seite neu.',
    server_not_configured: 'Der Check ist gerade nicht verfügbar. Bitte versuchen Sie es später erneut.',
    upstream_error: 'Google hat gerade nicht geantwortet. Bitte versuchen Sie es in ein paar Minuten erneut.',
  };
  const DEFAULT_ERROR = 'Der Check ist gerade nicht möglich. Bitte versuchen Sie es später erneut.';
  const errorTextFor = (data) => {
    const key = data && typeof data.error === 'string' ? data.error : '';
    return Object.prototype.hasOwnProperty.call(ERROR_TEXTS, key) ? ERROR_TEXTS[key] : DEFAULT_ERROR;
  };

  const showError = (message) => {
    if (errorText) errorText.textContent = message;
    setState('error');
  };

  // Farbe wandert mit dem Wert statt umzuspringen — dieselbe Bandbreite wie
  // Googles eigene Lighthouse-Farbskala (Rot/Orange/Grün), nur mit den
  // Blau-Tönen des Webdesign-Zweigs statt Googles Originalfarben, damit der
  // Ring zur Seite passt statt wie ein Fremdkörper zu wirken.
  const ringColor = (score) => {
    if (score >= 90) return '#0071E3';
    if (score >= 50) return '#0A4FA8';
    return '#B0261E';
  };

  const VERDICTS = {
    gut: 'Solide Werte — hier ist eher Feinschliff möglich als ein kompletter Neubau nötig.',
    mittel: 'Ausbaufähig. Das kostet vermutlich den einen oder anderen Besucher, der vorher wieder weg ist.',
    schlecht: 'Deutlich unter dem, was Besucher heute erwarten — ein guter Anlass für ein Erstgespräch.',
  };

  // "4.8" (Punkt, englisches JSON) → "4,8" (deutsches Komma) fürs Auge.
  const fmtSeconds = (s) => (Math.round(s * 10) / 10).toFixed(1).replace('.', ',');

  const paintResult = (data) => {
    if (resultUrl) resultUrl.textContent = data.url.replace(/^https?:\/\//, '').replace(/\/$/, '');
    if (scoreNum) scoreNum.textContent = String(data.score);
    if (ringValue) {
      ringValue.style.strokeDashoffset = (CIRC - CIRC * Math.min(data.score / 100, 1)).toFixed(2);
      ringValue.style.stroke = ringColor(data.score);
    }
    if (lcpEl) {
      lcpEl.textContent = typeof data.lcp_s === 'number'
        ? 'Der wichtigste Inhalt Ihrer Seite braucht ' + fmtSeconds(data.lcp_s) + ' Sekunden, bis er sichtbar ist.'
        : '';
      lcpEl.hidden = typeof data.lcp_s !== 'number';
    }
    if (verdictEl) verdictEl.textContent = VERDICTS[data.band] || '';
    setState('result');
  };

  const resetToForm = () => {
    setState('form');
    if (input) { input.value = ''; input.focus(); }
  };
  root.querySelectorAll('[data-psc-reset]').forEach((btn) => {
    btn.addEventListener('click', resetToForm);
  });

  // Fortschritt während der Messung. Google braucht 10 bis 30 Sekunden —
  // ein stehender Satz wirkt nach zehn Sekunden wie ein Absturz. Die Stufen
  // beschreiben, was tatsächlich passiert, in der Reihenfolge, in der es passiert.
  const stepEl = document.getElementById('pscStep');
  const barEl = document.getElementById('pscBar');
  const STEPS = [
    'Ihre Seite wird bei Google geöffnet …',
    'Ladezeit auf einem Mittelklasse-Handy wird gemessen …',
    'Bilder, Skripte und Schriften werden ausgewertet …',
    'Fast fertig — das Ergebnis wird zusammengestellt …',
  ];
  let stepTimer = null;
  const startProgress = () => {
    let i = 0;
    const t0 = performance.now();
    if (stepEl) stepEl.textContent = STEPS[0];
    if (barEl) barEl.style.transform = 'scaleX(0)';
    clearInterval(stepTimer);
    stepTimer = setInterval(() => {
      const s = (performance.now() - t0) / 1000;
      // Der Balken nähert sich 92 % an, erreicht ihn aber nie von selbst:
      // fertig ist die Messung erst, wenn die Antwort da ist.
      if (barEl) barEl.style.transform = 'scaleX(' + (0.92 * (1 - Math.exp(-s / 11))).toFixed(3) + ')';
      const next = Math.min(STEPS.length - 1, Math.floor(s / 7));
      if (next !== i && stepEl) { i = next; stepEl.textContent = STEPS[i]; }
    }, 250);
  };
  const stopProgress = () => { clearInterval(stepTimer); stepTimer = null; };

  // Zwei Adressen, derselbe Endpunkt: /api/pagespeed-check braucht die
  // Weiterleitungsregel in der .htaccess. Fehlt sie auf dem Server (genau das
  // war der Fehler, an dem der Check monatelang scheiterte), antwortet er mit
  // einer 404-HTML-Seite — dann direkt die PHP-Datei ansprechen.
  const ENDPOINTS = ['/api/pagespeed-check', '/api/pagespeed-check.php'];
  const TIMEOUT_MS = 75000;

  const request = async (url) => {
    const ctrl = typeof AbortController === 'function' ? new AbortController() : null;
    const timer = ctrl ? setTimeout(() => ctrl.abort(), TIMEOUT_MS) : null;
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ url: (input && input.value || '').trim() }),
        signal: ctrl ? ctrl.signal : undefined,
      });
      const isJson = (res.headers.get('content-type') || '').includes('application/json');
      const data = isJson ? await res.json().catch(() => ({})) : null;
      return { res, data };
    } finally {
      if (timer) clearTimeout(timer);
    }
  };

  if (form) {
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const raw = (input && input.value || '').trim();
      // Leichte, lokale Vorprüfung fürs schnelle Feedback — die eigentliche,
      // maßgebliche Prüfung passiert serverseitig (pscNormalizeUrl).
      if (!/^(https?:\/\/)?[^\s]+\.[a-z]{2,}([/?#].*)?$/i.test(raw)) {
        showError(ERROR_TEXTS.invalid_url);
        return;
      }

      setState('loading');
      startProgress();

      try {
        let out = await request(ENDPOINTS[0]);
        // Keine JSON-Antwort = die Route existiert nicht (404/HTML). Nur
        // dann der zweite Weg; eine echte Fehlermeldung des Checks wird
        // nicht durch einen doppelten Durchlauf verlängert.
        if (!out.data) out = await request(ENDPOINTS[1]);
        stopProgress();

        const data = out.data || {};
        if (out.res.ok && data.success) {
          if (barEl) barEl.style.transform = 'scaleX(1)';
          paintResult(data);
          return;
        }
        showError(errorTextFor(data));
      } catch (err) {
        stopProgress();
        if (err && err.name === 'AbortError') {
          showError('Google braucht für Ihre Seite gerade ungewöhnlich lange. Versuchen Sie es in ein paar Minuten noch einmal — oder rufen Sie mich an, dann schaue ich persönlich nach.');
          return;
        }
        showError('Die Verbindung ist unterbrochen. Bitte prüfen Sie Ihre Internetverbindung und versuchen Sie es erneut.');
      }
    });
  }
}
