/* ============================================================
   CHAT-ASSISTENT: Fenster, Schnellauswahl, Terminbuchung, Nachricht
   ============================================================
   Wird von site.js erst geladen, wenn jemand den runden Knopf unten rechts
   antippt (oder mit der Maus darüber fährt). Auf allen anderen Seitenaufrufen
   kostet dieses Modul nichts.

   Was dieses Modul NIE tut: selbst entscheiden, was stimmt. Antworten kommen
   von /api/chat (feste Antworten aus der Wissensbasis, bei offenen Fragen ein
   Sprachmodell mit Prüfung). Freie Termine kommen von /api/booking/slots,
   gebucht wird über /api/booking/book, Nachrichten gehen an /api/contact.
   Dieselben Endpunkte wie Kalender und Kontaktformular der Seite, mit
   denselben Schutzmaßnahmen.

   DATENSCHUTZ-GRENZE: Name, E-Mail und Telefon aus den Formularen gehen
   ausschließlich an Buchung und Kontakt, nie an /api/chat und damit nie an
   den KI-Dienst. An /api/chat geht nur, was der Besucher als Frage tippt. */

import '../css/chat.css';

const PHONE = '+49 176 55769680';
const PHONE_HREF = 'tel:+4917655769680';
const MAIL = 'Info@jungline.de';

/* Auf true setzen und neu bauen, wenn der Besucher vor der ersten frei
   getippten Frage ausdrücklich zustimmen soll, dass sie an einen KI-Dienst
   geht. Feste Antworten, Terminbuchung und Nachricht sind davon nicht
   betroffen, sie verlassen den eigenen Server nicht. */
const CONSENT_REQUIRED = false;

const STORE_KEY = 'jlChat';
const MAX_QUESTION = 500;
const MAX_HISTORY = 8;
const DAYS_PER_PAGE = 6;

const WEEKDAYS_LONG = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
const WEEKDAYS_SHORT = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
const MONTHS_LONG = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
const MONTHS_SHORT = ['Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.'];

const TOPICS = {
  seo: 'Google-Profil',
  webdesign: 'Webdesign',
};

const START_CHIPS = [
  { label: 'Termin buchen', aktion: 'termin', primary: true },
  { label: 'Was kostet es?', faq: 'preise' },
  { label: 'Google-Profil optimieren', faq: 'seo_leistung' },
  { label: 'Webdesign & Relaunch', faq: 'webdesign_leistung' },
  { label: 'Mit Leandro sprechen', faq: 'mensch' },
];

const FALLBACK_GREETING = 'Guten Tag! Ich bin der KI-Assistent von Jungline Local. Ich beantworte Fragen zur Google-Profil-Optimierung und zum Webdesign, trage Ihnen auf Wunsch direkt einen Termin ein und leite Sie bei Bedarf an Leandro Jung weiter. Wobei kann ich helfen?';

const ICON = {
  send: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M12.5 5.5 19 12l-6.5 6.5"/></svg>',
  close: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
  check: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="m8 12.4 2.8 2.8L16.2 9.6"/></svg>',
  arrow: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>',
};

// ---------------------------------------------------------------------
// Kleine Helfer
// ---------------------------------------------------------------------

function el(tag, cls, text) {
  const n = document.createElement(tag);
  if (cls) n.className = cls;
  if (text != null) n.textContent = text;
  return n;
}

function withIcon(node, svg, before) {
  const s = document.createElement('span');
  s.innerHTML = svg;
  const icon = s.firstChild;
  if (before) node.insertBefore(icon, node.firstChild); else node.appendChild(icon);
  return node;
}

function pad(n) { return String(n).padStart(2, '0'); }

function parseDay(iso) {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d);
}

function dayShort(iso) {
  const d = parseDay(iso);
  return WEEKDAYS_SHORT[d.getDay()] + ', ' + d.getDate() + '. ' + MONTHS_SHORT[d.getMonth()];
}

function dayLong(iso) {
  const d = parseDay(iso);
  return WEEKDAYS_LONG[d.getDay()] + ', ' + d.getDate() + '. ' + MONTHS_LONG[d.getMonth()];
}

function track(name) {
  try { if (window.jlTrack) window.jlTrack(name); } catch (_) { /* Zählung ist Kür */ }
}

function isMobile() {
  return window.matchMedia('(max-width: 640px)').matches;
}

// ---------------------------------------------------------------------
// Zustand
// ---------------------------------------------------------------------

let panel, logEl, chipsEl, formEl, inputEl, sendEl;
let launcher = null;
let built = false;
let isOpen = false;
let busy = false;
let consented = !CONSENT_REQUIRED;
let typingEl = null;
let opened = false;          // Zählung "chat_open" höchstens einmal je Seitenaufruf

/** Verlauf für die KI: nur Text, nie Formulardaten. */
const history = [];
/** Verlauf zum Wiederherstellen nach einem Seitenwechsel. */
const transcript = [];

function save() {
  try {
    sessionStorage.setItem(STORE_KEY, JSON.stringify({ t: transcript.slice(-30), h: history.slice(-MAX_HISTORY), c: consented }));
  } catch (_) { /* Privates Fenster oder gesperrt: dann eben ohne Gedächtnis */ }
}

function restore() {
  try {
    const raw = sessionStorage.getItem(STORE_KEY);
    if (!raw) return false;
    const data = JSON.parse(raw);
    if (!data || !Array.isArray(data.t) || data.t.length === 0) return false;
    if (CONSENT_REQUIRED && data.c === true) consented = true;
    (data.h || []).forEach((m) => {
      if (m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string') history.push(m);
    });
    data.t.forEach((e) => {
      if (!e || typeof e.t !== 'string') return;
      transcript.push(e);
      renderMessage(e.r === 'u' ? 'user' : 'bot', e.t, e.l);
    });
    return true;
  } catch (_) {
    return false;
  }
}

// ---------------------------------------------------------------------
// Aufbau
// ---------------------------------------------------------------------

function build() {
  if (built) return;
  built = true;

  panel = el('section', 'chat');
  panel.id = 'chatPanel';
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-label', 'Chat mit dem Jungline-Assistenten');
  panel.hidden = true;

  const head = el('header', 'chat__head');
  const avatar = el('span', 'chat__avatar');
  const logo = document.createElement('img');
  logo.src = '/favicon.svg';
  logo.alt = '';
  logo.width = 30;
  logo.height = 30;
  avatar.appendChild(logo);
  const title = el('div', 'chat__title');
  title.appendChild(el('b', null, 'Jungline Assistent'));
  title.appendChild(el('small', null, 'KI-Assistent von Leandro Jung'));
  const closeBtn = el('button', 'chat__close');
  closeBtn.type = 'button';
  closeBtn.setAttribute('aria-label', 'Chat schließen');
  closeBtn.innerHTML = ICON.close;
  closeBtn.addEventListener('click', () => close(true));
  head.append(avatar, title, closeBtn);

  logEl = el('div', 'chat__log');
  logEl.setAttribute('role', 'log');
  logEl.setAttribute('aria-live', 'polite');
  logEl.setAttribute('aria-label', 'Gesprächsverlauf');

  chipsEl = el('div', 'chat__chips');
  chipsEl.setAttribute('role', 'group');
  chipsEl.setAttribute('aria-label', 'Vorschläge');

  formEl = el('form', 'chat__form');
  formEl.setAttribute('autocomplete', 'off');
  inputEl = el('textarea', 'chat__input');
  inputEl.rows = 1;
  inputEl.maxLength = MAX_QUESTION;
  inputEl.placeholder = 'Ihre Frage …';
  inputEl.setAttribute('aria-label', 'Ihre Frage');
  inputEl.enterKeyHint = 'send';
  sendEl = el('button', 'chat__send');
  sendEl.type = 'submit';
  sendEl.setAttribute('aria-label', 'Frage senden');
  sendEl.innerHTML = ICON.send;
  formEl.append(inputEl, sendEl);

  const legal = el('p', 'chat__legal');
  legal.append('KI-Assistent: Antworten können Fehler enthalten. Bitte geben Sie hier keine sensiblen Daten ein. Frei getippte Fragen werden zur Beantwortung an einen KI-Dienst übermittelt. ');
  const dsLink = el('a', null, 'Datenschutz');
  dsLink.href = '/datenschutz/#chat';
  legal.appendChild(dsLink);

  panel.append(head, logEl, chipsEl, formEl, legal);
  document.body.appendChild(panel);

  formEl.addEventListener('submit', (ev) => {
    ev.preventDefault();
    submitQuestion();
  });
  inputEl.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) {
      ev.preventDefault();
      submitQuestion();
    }
  });
  inputEl.addEventListener('input', () => {
    inputEl.style.height = 'auto';
    inputEl.style.height = Math.min(inputEl.scrollHeight, 112) + 'px';
  });
  panel.addEventListener('keydown', onPanelKey);

  const had = restore();
  if (had) {
    setChips(START_CHIPS);
  } else {
    greet();
  }
}

/** Begrüßung: Wortlaut kommt vom Server (eine Quelle), mit Notfalltext. */
async function greet() {
  showTyping();
  let text = FALLBACK_GREETING;
  try {
    const res = await post({ faq: 'begruessung' }, 6000);
    if (res.ok && res.data && res.data.text) text = res.data.text;
  } catch (_) { /* Notfalltext */ }
  hideTyping();
  addMessage('bot', text);
  setChips(START_CHIPS);
}

/** Esc schließt das Fenster, auch wenn der Fokus gerade nirgends im Fenster liegt. */
function onDocKey(ev) {
  if (ev.key === 'Escape' && isOpen) close(true);
}

function onPanelKey(ev) {
  // Auf dem Telefon füllt das Fenster den Bildschirm und ist damit modal:
  // Tab bleibt im Fenster.
  if (ev.key === 'Tab' && isMobile()) {
    const f = panel.querySelectorAll('button:not([disabled]), a[href], input:not([tabindex="-1"]), textarea');
    if (!f.length) return;
    const first = f[0];
    const last = f[f.length - 1];
    if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
    else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
  }
}

// ---------------------------------------------------------------------
// Öffnen und Schließen
// ---------------------------------------------------------------------

export function open(btn) {
  if (btn) launcher = btn;
  build();
  if (isOpen) return;
  isOpen = true;
  panel.hidden = false;
  panel.setAttribute('aria-modal', isMobile() ? 'true' : 'false');
  // Ein Bild später, damit der Übergang von "unsichtbar" aus startet.
  requestAnimationFrame(() => panel.classList.add('is-open'));
  document.documentElement.classList.add('chat-open');
  document.addEventListener('keydown', onDocKey);
  if (launcher) launcher.setAttribute('aria-expanded', 'true');
  if (!opened) { opened = true; track('chat_open'); }
  scrollLog(true);
  setTimeout(() => { if (isOpen) inputEl.focus({ preventScroll: true }); }, 60);
}

export function close(returnFocus) {
  if (!built || !isOpen) return;
  isOpen = false;
  panel.classList.remove('is-open');
  document.documentElement.classList.remove('chat-open');
  document.removeEventListener('keydown', onDocKey);
  if (launcher) {
    launcher.setAttribute('aria-expanded', 'false');
    if (returnFocus) launcher.focus({ preventScroll: true });
  }
  setTimeout(() => { if (!isOpen) panel.hidden = true; }, 320);
}

export function toggle(btn) {
  if (btn) launcher = btn;
  if (isOpen) close(false); else open();
}

// ---------------------------------------------------------------------
// Nachrichten
// ---------------------------------------------------------------------

function scrollLog(instant) {
  if (!logEl) return;
  if (instant) logEl.style.scrollBehavior = 'auto';
  logEl.scrollTop = logEl.scrollHeight;
  if (instant) requestAnimationFrame(() => { logEl.style.scrollBehavior = ''; });
}

function renderLinks(links) {
  if (!Array.isArray(links) || links.length === 0) return null;
  const row = el('div', 'chat__links');
  links.forEach((l) => {
    if (!l || typeof l.href !== 'string' || typeof l.label !== 'string') return;
    // Nur eigene Seiten, Telefon und Mail. Alles andere wird nicht gerendert.
    if (!/^(\/(?!\/)|tel:|mailto:)/.test(l.href)) return;
    const a = el('a', 'chat__link', l.label);
    a.href = l.href;
    if (l.href.charAt(0) === '/') {
      withIcon(a, ICON.arrow);
      // Auf dem Telefon verdeckt das Fenster die Seite: beim Wechsel zu einem
      // Anker auf derselben Seite würde man sonst nichts davon sehen.
      a.addEventListener('click', () => { if (isMobile()) close(false); });
    }
    row.appendChild(a);
  });
  return row.childNodes.length ? row : null;
}

function renderMessage(who, text, links) {
  const bubble = el('div', 'chat__msg chat__msg--' + who, text);
  logEl.appendChild(bubble);
  const row = who === 'bot' ? renderLinks(links) : null;
  if (row) logEl.appendChild(row);
  scrollLog(false);
  return bubble;
}

/** Zeigt eine Nachricht und merkt sie sich für KI-Verlauf und Wiederherstellung. */
function addMessage(who, text, links) {
  const bubble = renderMessage(who, text, links);
  transcript.push({ r: who === 'user' ? 'u' : 'b', t: text, l: Array.isArray(links) ? links : undefined });
  history.push({ role: who === 'user' ? 'user' : 'assistant', content: text });
  while (history.length > MAX_HISTORY) history.shift();
  save();
  return bubble;
}

/** Die gerade offene Karte (Termin oder Nachricht). Es gibt immer höchstens eine. */
let activeCard = null;

function openCard(card) {
  // Ein unfertiges Formular von vorhin würde sonst stehen bleiben und
  // gleichnamige Felder doppelt anbieten.
  if (activeCard && activeCard.parentNode && !activeCard.classList.contains('is-done')) activeCard.remove();
  activeCard = card;
  return addNode(card);
}

function addNode(node) {
  logEl.appendChild(node);
  scrollLog(false);
  return node;
}

function showTyping() {
  hideTyping();
  typingEl = el('div', 'chat__typing');
  typingEl.setAttribute('aria-hidden', 'true');
  typingEl.append(el('i'), el('i'), el('i'));
  logEl.appendChild(typingEl);
  scrollLog(false);
}

function hideTyping() {
  if (typingEl && typingEl.parentNode) typingEl.parentNode.removeChild(typingEl);
  typingEl = null;
}

function setBusy(on) {
  busy = on;
  sendEl.disabled = on;
  inputEl.disabled = on;
  if (!on && isOpen && !isMobile()) inputEl.focus({ preventScroll: true });
}

// ---------------------------------------------------------------------
// Schnellauswahl
// ---------------------------------------------------------------------

function setChips(list) {
  chipsEl.textContent = '';
  (list || []).forEach((c) => {
    const b = el('button', 'chat__chip' + (c.primary ? ' chat__chip--primary' : ''), c.label);
    b.type = 'button';
    b.addEventListener('click', () => onChip(c));
    chipsEl.appendChild(b);
  });
}

function onChip(c) {
  if (busy) return;
  chipsEl.textContent = '';
  if (c.faq) {
    addMessage('user', c.label);
    ask({ faq: c.faq });
    return;
  }
  addMessage('user', c.label);
  if (c.aktion === 'termin') {
    addMessage('bot', 'Sehr gerne. Worum soll es im Erstgespräch gehen?');
    startBooking(null);
  } else if (c.aktion === 'nachricht') {
    addMessage('bot', 'Gern. Schreiben Sie Leandro Jung hier eine Nachricht, er antwortet werktags innerhalb von 24 Stunden.');
    startContact('');
  }
}

// ---------------------------------------------------------------------
// Anfrage an /api/chat
// ---------------------------------------------------------------------

async function post(body, timeoutMs) {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), timeoutMs || 22000);
  try {
    const res = await fetch('/api/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
      signal: ctrl.signal,
    });
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok && data && data.success, status: res.status, data };
  } finally {
    clearTimeout(timer);
  }
}

function contactLinks() {
  return [{ label: 'Anrufen: ' + PHONE, href: PHONE_HREF }, { label: 'Kontaktseite', href: '/kontakt/' }];
}

function handoffChips() {
  return [{ label: 'Termin buchen', aktion: 'termin', primary: true }, { label: 'Nachricht senden', aktion: 'nachricht' }];
}

async function ask(body) {
  setBusy(true);
  showTyping();
  const t0 = Date.now();
  let result;
  try {
    result = await post(body);
  } catch (_) {
    result = null;
  }
  // Mindestens kurz "tippen", sonst wirkt eine sofortige Antwort wie ein Flackern.
  const rest = 350 - (Date.now() - t0);
  if (rest > 0) await new Promise((r) => setTimeout(r, rest));
  hideTyping();
  setBusy(false);

  if (!result) {
    addMessage('bot', 'Die Verbindung hat leider nicht geklappt. Sie erreichen Leandro Jung auch direkt.', contactLinks());
    setChips(handoffChips());
    return;
  }
  if (result.status === 429) {
    addMessage('bot', 'Sie haben in kurzer Zeit viele Fragen gestellt. Bitte versuchen Sie es in ein paar Minuten erneut oder erreichen Sie Leandro Jung direkt.', contactLinks());
    setChips(handoffChips());
    return;
  }
  if (!result.ok || typeof result.data.text !== 'string' || result.data.text === '') {
    addMessage('bot', 'Dazu kann ich gerade nichts Verlässliches sagen. Leandro Jung hilft Ihnen gern persönlich weiter.', contactLinks());
    setChips(handoffChips());
    return;
  }

  const d = result.data;
  addMessage('bot', d.text, d.links);
  setChips(Array.isArray(d.vorschlaege) ? d.vorschlaege : []);

  // Erst den Wunsch des Besuchers erfüllen, wenn er eindeutig ist: wer "Termin
  // buchen" meint (Antwort "termin" oder KI-Antwort mit Terminwunsch), sieht
  // direkt den Kalender. Reine Info-Antworten bieten nur einen Knopf an.
  if (d.aktion === 'termin' && (d.id === 'termin' || d.typ === 'ki')) {
    chipsEl.textContent = '';
    startBooking(d.thema === 'seo' || d.thema === 'webdesign' ? d.thema : null);
  } else if (d.aktion === 'nachricht' && (d.id === 'mensch' || d.typ === 'ki')) {
    chipsEl.textContent = '';
    startContact(lastQuestion());
  }
}

function lastQuestion() {
  for (let i = history.length - 1; i >= 0; i--) {
    if (history[i].role === 'user') return history[i].content;
  }
  return '';
}

function submitQuestion() {
  if (busy) return;
  const text = inputEl.value.trim();
  if (text === '') return;
  if (text.length > MAX_QUESTION) return;

  if (!consented) {
    askConsent(text);
    return;
  }
  sendQuestion(text);
}

function sendQuestion(text) {
  inputEl.value = '';
  inputEl.style.height = 'auto';
  chipsEl.textContent = '';
  addMessage('user', text);
  track('chat_frage');
  ask({
    messages: history.slice(-MAX_HISTORY).map((m) => ({ role: m.role, content: m.content })),
    seite: location.pathname,
  });
}

function askConsent(text) {
  const card = el('div', 'chat__card');
  card.appendChild(el('h4', null, 'Kurzer Hinweis zum Datenschutz'));
  card.appendChild(el('p', null, 'Ihre Frage wird zur Beantwortung an einen KI-Dienst übermittelt. Bitte schreiben Sie keine persönlichen oder vertraulichen Angaben. Terminbuchung und Nachricht an Leandro Jung sind davon nicht betroffen.'));
  const yes = el('button', 'chat__btn', 'Einverstanden, Frage senden');
  yes.type = 'button';
  const no = el('button', 'chat__btn chat__btn--ghost', 'Abbrechen');
  no.type = 'button';
  yes.addEventListener('click', () => { consented = true; save(); card.remove(); sendQuestion(text); });
  no.addEventListener('click', () => { card.remove(); inputEl.focus(); });
  card.append(yes, no);
  addNode(card);
  yes.focus({ preventScroll: true });
}

/** Beschriftetes Eingabefeld mit Platz für eine Fehlermeldung. */
function field(name, label, opts) {
  const wrap = el('label', 'chat__field');
  wrap.appendChild(el('span', null, label));
  const input = opts.area ? el('textarea') : el('input');
  input.name = name;
  if (!opts.area) input.type = opts.type || 'text';
  if (opts.autocomplete) input.autocomplete = opts.autocomplete;
  if (opts.max) input.maxLength = opts.max;
  if (opts.rows) input.rows = opts.rows;
  if (opts.value) input.value = opts.value;
  wrap.appendChild(input);
  wrap.appendChild(el('small', 'chat__err'));
  return wrap;
}

/** Setzt Fehlermeldungen an die Felder (Name der Eingabe => Text) und fokussiert das erste. */
function showFieldErrors(form, fields) {
  let first = null;
  Object.keys(fields).forEach((key) => {
    const input = form.elements[key];
    if (!input) return;
    const wrap = input.closest('.chat__field');
    wrap.classList.add('has-error');
    wrap.querySelector('.chat__err').textContent = fields[key];
    if (!first) first = input;
  });
  if (first) first.focus();
}

function clearFieldErrors(form) {
  form.querySelectorAll('.chat__field').forEach((f) => f.classList.remove('has-error'));
  form.querySelectorAll('.chat__err').forEach((e) => { e.textContent = ''; });
}

// ---------------------------------------------------------------------
// Termin buchen (geführt, nutzt /api/booking/slots und /api/booking/book)
// ---------------------------------------------------------------------

function startBooking(topic) {
  const card = el('div', 'chat__card');
  openCard(card);
  const st = { topic: topic, days: {}, order: [], page: 0, date: null, time: null, loaded: false };

  function pageOf(list) { return list.slice(st.page * DAYS_PER_PAGE, (st.page + 1) * DAYS_PER_PAGE); }

  function stepTopic() {
    card.textContent = '';
    card.appendChild(el('h4', null, 'Worum soll es gehen?'));
    const opts = el('div', 'chat__opts');
    Object.keys(TOPICS).forEach((key) => {
      const b = el('button', 'chat__opt', TOPICS[key]);
      b.type = 'button';
      b.addEventListener('click', () => { st.topic = key; stepDays(); });
      opts.appendChild(b);
    });
    card.appendChild(opts);
    scrollLog(false);
  }

  async function loadDays() {
    // Ohne Monat liefert der Server den laufenden Monat samt Grenzen. Danach
    // weiter, bis genug Tage zusammen sind oder der Kalender endet.
    let month = '';
    for (let i = 0; i < 4 && st.order.length < 18; i++) {
      const res = await fetch('/api/booking/slots' + (month ? '?month=' + encodeURIComponent(month) : ''), { headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (!res.ok || !data.success) throw new Error('slots');
      Object.keys(data.days || {}).forEach((d) => {
        if (Array.isArray(data.days[d]) && data.days[d].length > 0) st.days[d] = data.days[d];
      });
      st.order = Object.keys(st.days).sort();
      const current = month || data.month;
      if (!data.max_month || current >= data.max_month) break;
      const [y, m] = current.split('-').map(Number);
      month = m === 12 ? (y + 1) + '-01' : y + '-' + pad(m + 1);
    }
    st.loaded = true;
  }

  async function stepDays() {
    card.textContent = '';
    card.appendChild(el('h4', null, 'Termin für das Erstgespräch (' + TOPICS[st.topic] + ')'));
    if (!st.loaded) {
      card.appendChild(el('p', null, 'Freie Termine werden geladen …'));
      try {
        await loadDays();
      } catch (_) {
        card.textContent = '';
        card.appendChild(el('p', null, 'Die freien Termine lassen sich gerade nicht laden. Rufen Sie gern an oder schreiben Sie eine Nachricht.'));
        const call = el('a', 'chat__btn', PHONE);
        call.href = PHONE_HREF;
        const msg = el('button', 'chat__btn chat__btn--ghost', 'Nachricht senden');
        msg.type = 'button';
        msg.addEventListener('click', () => { card.remove(); startContact(''); });
        card.append(call, msg);
        return;
      }
      card.textContent = '';
      card.appendChild(el('h4', null, 'Termin für das Erstgespräch (' + TOPICS[st.topic] + ')'));
    }
    if (st.order.length === 0) {
      card.appendChild(el('p', null, 'Aktuell sind keine freien Termine online buchbar. Rufen Sie gern an oder schreiben Sie eine Nachricht.'));
      const call = el('a', 'chat__btn', PHONE);
      call.href = PHONE_HREF;
      card.appendChild(call);
      return;
    }
    card.appendChild(el('p', null, 'Wählen Sie einen Tag (30 Minuten, Zeiten in Berliner Zeit):'));
    const opts = el('div', 'chat__opts');
    pageOf(st.order).forEach((d) => {
      const b = el('button', 'chat__opt', dayShort(d));
      b.type = 'button';
      b.setAttribute('aria-label', dayLong(d) + ', ' + st.days[d].length + ' freie Zeiten');
      b.addEventListener('click', () => { st.date = d; stepTimes(); });
      opts.appendChild(b);
    });
    if ((st.page + 1) * DAYS_PER_PAGE < st.order.length) {
      const more = el('button', 'chat__opt chat__opt--more', 'Weitere Tage');
      more.type = 'button';
      more.addEventListener('click', () => { st.page += 1; stepDays(); });
      opts.appendChild(more);
    }
    card.appendChild(opts);
    scrollLog(false);
  }

  function stepTimes() {
    card.textContent = '';
    card.appendChild(el('h4', null, dayLong(st.date)));
    card.appendChild(el('p', null, 'Welche Uhrzeit passt Ihnen?'));
    const opts = el('div', 'chat__opts');
    st.days[st.date].forEach((t) => {
      const b = el('button', 'chat__opt chat__opt--time', t);
      b.type = 'button';
      b.setAttribute('aria-label', t + ' Uhr');
      b.addEventListener('click', () => { st.time = t; stepForm(); });
      opts.appendChild(b);
    });
    card.appendChild(opts);
    const back = el('button', 'chat__back', 'Anderen Tag wählen');
    back.type = 'button';
    back.addEventListener('click', () => { st.date = null; stepDays(); });
    card.appendChild(back);
    scrollLog(false);
  }

  function stepForm() {
    card.textContent = '';
    card.appendChild(el('h4', null, 'Ihre Angaben'));
    const facts = el('div', 'chat__facts');
    const b = el('b', null, dayLong(st.date) + ', ' + st.time + ' Uhr');
    facts.append(b, document.createElement('br'), 'Kostenloses Erstgespräch (' + TOPICS[st.topic] + '), 30 Minuten');
    card.appendChild(facts);

    const form = el('form');
    form.noValidate = true;
    form.appendChild(field('name', 'Name', { autocomplete: 'name', max: 80 }));
    form.appendChild(field('email', 'E-Mail', { type: 'email', autocomplete: 'email', max: 120 }));
    form.appendChild(field('phone', 'Telefon (optional)', { type: 'tel', autocomplete: 'tel', max: 40 }));
    form.appendChild(field('message', 'Worum geht es? (optional)', { area: true, rows: 3, max: 2000 }));
    // Honigtopf: für Menschen unsichtbar, Bots füllen ihn aus.
    const hp = el('input', 'chat__hp');
    hp.name = 'website';
    hp.tabIndex = -1;
    hp.autocomplete = 'off';
    hp.setAttribute('aria-hidden', 'true');
    form.appendChild(hp);

    const note = el('p', 'chat__note');
    note.append('Ihre Angaben dienen nur der Terminvereinbarung (');
    const ds = el('a', null, 'Datenschutz');
    ds.href = '/datenschutz/#termin';
    ds.target = '_blank';
    ds.rel = 'noopener';
    note.append(ds, '). Sie erhalten eine Bestätigung per E-Mail.');
    form.appendChild(note);

    const submit = el('button', 'chat__btn', 'Termin verbindlich buchen');
    submit.type = 'submit';
    form.appendChild(submit);
    const status = el('p', 'chat__status');
    status.setAttribute('role', 'status');
    form.appendChild(status);
    const back = el('button', 'chat__back', 'Andere Uhrzeit wählen');
    back.type = 'button';
    back.addEventListener('click', stepTimes);
    form.appendChild(back);

    form.addEventListener('submit', (ev) => { ev.preventDefault(); book(form, submit, status); });
    card.appendChild(form);
    scrollLog(false);
    form.elements.name.focus({ preventScroll: true });
  }

  async function book(form, submit, status) {
    const v = {};
    ['name', 'email', 'phone', 'message', 'website'].forEach((k) => { v[k] = form.elements[k].value.trim(); });
    clearFieldErrors(form);
    status.className = 'chat__status';
    status.textContent = '';

    const problems = {};
    if (v.name.length < 2) problems.name = 'Bitte geben Sie Ihren Namen an.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.email)) problems.email = 'Bitte prüfen Sie Ihre E-Mail-Adresse.';
    if (Object.keys(problems).length) { showFieldErrors(form, problems); return; }

    submit.disabled = true;
    submit.textContent = 'Wird gebucht …';
    try {
      const res = await fetch('/api/booking/book', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          date: st.date, time: st.time,
          name: v.name, email: v.email, phone: v.phone, company: '', message: v.message,
          website: v.website, topic: st.topic,
        }),
      });
      const data = await res.json().catch(() => ({}));

      if (res.ok && data.success && data.booking) {
        track('chat_termin');
        done(data.booking);
        return;
      }
      if (res.status === 409) {
        status.className = 'chat__status chat__status--err';
        status.textContent = 'Dieser Termin wurde gerade von jemand anderem gebucht. Bitte wählen Sie einen anderen.';
        // Frische Termine holen, damit der vergebene nicht weiter angeboten wird.
        st.days = {}; st.order = []; st.loaded = false; st.page = 0; st.date = null; st.time = null;
        setTimeout(stepDays, 1600);
        return;
      }
      if (res.status === 422 && data.fields) { showFieldErrors(form, data.fields); return; }
      if (res.status === 429) {
        status.className = 'chat__status chat__status--err';
        status.textContent = 'Es liegen bereits mehrere Buchungen von diesem Anschluss vor. Bitte rufen Sie kurz an: ' + PHONE + '.';
        return;
      }
      throw new Error('failed');
    } catch (_) {
      status.className = 'chat__status chat__status--err';
      status.textContent = '';
      status.append('Die Buchung hat leider nicht geklappt. Rufen Sie an: ');
      const a = el('a', null, PHONE);
      a.href = PHONE_HREF;
      status.append(a, ' oder schreiben Sie an ');
      const m = el('a', null, MAIL);
      m.href = 'mailto:' + MAIL;
      status.append(m, '.');
    } finally {
      submit.disabled = false;
      submit.textContent = 'Termin verbindlich buchen';
    }
  }

  function done(b) {
    card.classList.add('is-done');
    card.textContent = '';
    const ok = el('p', 'chat__ok', 'Ihr Termin ist gebucht');
    withIcon(ok, ICON.check, true);
    card.appendChild(ok);
    const facts = el('div', 'chat__facts');
    facts.append(el('b', null, b.date_label + ', ' + b.time_label), document.createElement('br'),
      'Kostenloses Erstgespräch (' + TOPICS[st.topic] + ')');
    card.appendChild(facts);
    card.appendChild(el('p', null, 'Die Bestätigung geht an ' + b.email + '.'));
    if (b.meeting_url) {
      const join = el('a', 'chat__btn', 'Videoraum öffnen');
      join.href = b.meeting_url;
      join.target = '_blank';
      join.rel = 'noopener';
      card.appendChild(join);
    }
    if (b.manage_url) {
      const manage = el('a', 'chat__btn chat__btn--ghost', 'Termin verschieben oder absagen');
      manage.href = b.manage_url;
      manage.target = '_blank';
      manage.rel = 'noopener';
      card.appendChild(manage);
    }
    // Ins Gedächtnis des Gesprächs, aber ohne persönliche Daten.
    transcript.push({ r: 'b', t: 'Ihr Termin ist gebucht: ' + b.date_label + ', ' + b.time_label + '.' });
    history.push({ role: 'assistant', content: 'Der Termin wurde gebucht.' });
    save();
    setChips([{ label: 'Was kostet es?', faq: 'preise' }, { label: 'Das Erstgespräch', faq: 'erstgespraech' }]);
    scrollLog(false);
  }

  if (st.topic) stepDays(); else stepTopic();
}

// ---------------------------------------------------------------------
// Nachricht an Leandro (nutzt /api/contact)
// ---------------------------------------------------------------------

function startContact(prefill) {
  const card = el('div', 'chat__card');
  card.appendChild(el('h4', null, 'Nachricht an Leandro Jung'));
  const form = el('form');
  form.noValidate = true;

  form.appendChild(field('name', 'Name', { autocomplete: 'name', max: 80 }));
  form.appendChild(field('email', 'E-Mail', { type: 'email', autocomplete: 'email', max: 120 }));
  form.appendChild(field('phone', 'Telefon (optional)', { type: 'tel', autocomplete: 'tel', max: 40 }));
  form.appendChild(field('message', 'Ihre Nachricht', { area: true, rows: 4, max: 2000, value: prefill || '' }));
  const hp = el('input', 'chat__hp');
  hp.name = '_gotcha';
  hp.tabIndex = -1;
  hp.autocomplete = 'off';
  hp.setAttribute('aria-hidden', 'true');
  form.appendChild(hp);

  const note = el('p', 'chat__note');
  note.append('Ihre Angaben gehen nur an Leandro Jung und dienen der Beantwortung Ihrer Anfrage (');
  const ds = el('a', null, 'Datenschutz');
  ds.href = '/datenschutz/#kontaktformular';
  ds.target = '_blank';
  ds.rel = 'noopener';
  note.append(ds, ').');
  form.appendChild(note);

  const submit = el('button', 'chat__btn', 'Nachricht senden');
  submit.type = 'submit';
  form.appendChild(submit);
  const status = el('p', 'chat__status');
  status.setAttribute('role', 'status');
  form.appendChild(status);

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const v = {};
    ['name', 'email', 'phone', 'message', '_gotcha'].forEach((k) => { v[k] = form.elements[k].value.trim(); });
    clearFieldErrors(form);
    status.className = 'chat__status';
    status.textContent = '';

    const problems = {};
    if (v.name.length < 2) problems.name = 'Bitte geben Sie Ihren Namen an.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.email)) problems.email = 'Bitte prüfen Sie Ihre E-Mail-Adresse.';
    if (v.message.length < 5) problems.message = 'Bitte schreiben Sie kurz, worum es geht.';
    if (Object.keys(problems).length) { showFieldErrors(form, problems); return; }

    submit.disabled = true;
    submit.textContent = 'Wird gesendet …';
    try {
      const res = await fetch('/api/contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          name: v.name, email: v.email, phone: v.phone, message: v.message,
          _gotcha: v._gotcha, _subject: 'Chat-Assistent',
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok && data.success) {
        track('chat_nachricht');
        card.classList.add('is-done');
        card.textContent = '';
        const ok = el('p', 'chat__ok', 'Danke, Ihre Nachricht ist angekommen');
        withIcon(ok, ICON.check, true);
        card.appendChild(ok);
        card.appendChild(el('p', null, 'Leandro Jung antwortet werktags innerhalb von 24 Stunden. Eine kurze Bestätigung liegt gleich in Ihrem Postfach.'));
        transcript.push({ r: 'b', t: 'Ihre Nachricht wurde an Leandro Jung gesendet.' });
        history.push({ role: 'assistant', content: 'Die Nachricht wurde gesendet.' });
        save();
        setChips([{ label: 'Termin buchen', aktion: 'termin', primary: true }, { label: 'Was kostet es?', faq: 'preise' }]);
        scrollLog(false);
        return;
      }
      if (res.status === 422 && data.fields) { showFieldErrors(form, data.fields); return; }
      if (res.status === 429) {
        status.className = 'chat__status chat__status--err';
        status.textContent = 'Es wurden schon mehrere Nachrichten gesendet. Bitte rufen Sie kurz an: ' + PHONE + '.';
        return;
      }
      throw new Error('failed');
    } catch (_) {
      status.className = 'chat__status chat__status--err';
      status.textContent = '';
      status.append('Das Senden hat leider nicht geklappt. Rufen Sie an: ');
      const a = el('a', null, PHONE);
      a.href = PHONE_HREF;
      status.append(a, ' oder schreiben Sie an ');
      const m = el('a', null, MAIL);
      m.href = 'mailto:' + MAIL;
      status.append(m, '.');
    } finally {
      submit.disabled = false;
      submit.textContent = 'Nachricht senden';
    }
  });

  card.appendChild(form);
  openCard(card);
  form.elements.name.focus({ preventScroll: true });
}
