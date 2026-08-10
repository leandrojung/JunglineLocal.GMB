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

      try {
        const res = await fetch('/api/pagespeed-check', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ url: raw }),
        });
        const data = await res.json().catch(() => ({}));

        if (res.ok && data.success) {
          paintResult(data);
          return;
        }
        showError(errorTextFor(data));
      } catch (_err) {
        showError('Die Verbindung ist unterbrochen. Bitte prüfen Sie Ihre Internetverbindung und versuchen Sie es erneut.');
      }
    });
  }
}
