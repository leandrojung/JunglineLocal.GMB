/* ============================================================
   TERMINBUCHUNG — Kalender, Slotauswahl, Formular, Bestätigung
   ============================================================
   Wird von site.js erst nachgeladen, wenn das Widget in Sichtweite kommt
   (siehe dort) — auf Seiten ohne Buchung kostet dieses Modul nichts.

   Der Ablauf ist bewusst derselbe wie bei Calendly, weil Besucher ihn
   kennen: Tag wählen → Uhrzeit wählen → Daten eintragen → Bestätigung.
   Jeder Schritt ersetzt den vorherigen im selben Rahmen, statt die Seite
   zu wechseln; der Kontext (welcher Termin gerade gewählt ist) bleibt
   dabei durchgehend sichtbar.

   Wichtig: Dieses Modul entscheidet NIE selbst, ob ein Termin frei ist.
   Es rendert ausschließlich, was /api/booking/slots liefert. Arbeitszeiten,
   Vorlauffristen und der Google-Abgleich stehen an genau einer Stelle —
   auf dem Server. */

const WEEKDAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
const WEEKDAYS_LONG = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
const MONTHS = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

const root = document.getElementById('bookingWidget');

// Ein Monat wird nur einmal geholt. Nach einer Buchung wird der Cache
// geleert, damit der gerade vergebene Slot nicht weiter angeboten wird.
const monthCache = new Map();

const state = {
  month: null,        // 'YYYY-MM'
  minMonth: null,
  maxMonth: null,
  days: {},           // '2026-08-11' → ['09:00', …]
  date: null,         // gewählter Tag
  time: null,         // gewählte Uhrzeit
  step: 'pick',       // pick | form | done
  loading: false,
  error: '',
  booking: null,      // Antwort der Buchung
  reschedule: null,   // {token, dateLabel, timeLabel, prefill}
};

// ---------------------------------------------------------------------
// Zeit-Helfer
// ---------------------------------------------------------------------

const pad = (n) => String(n).padStart(2, '0');

function monthKey(year, monthIndex) {
  return year + '-' + pad(monthIndex + 1);
}

function parseMonth(key) {
  const [y, m] = key.split('-').map(Number);
  return { year: y, monthIndex: m - 1 };
}

function shiftMonth(key, delta) {
  const { year, monthIndex } = parseMonth(key);
  const d = new Date(Date.UTC(year, monthIndex + delta, 1));
  return monthKey(d.getUTCFullYear(), d.getUTCMonth());
}

function dayLabel(dateStr) {
  const [y, m, d] = dateStr.split('-').map(Number);
  const weekday = new Date(Date.UTC(y, m - 1, d)).getUTCDay();
  // getUTCDay(): 0 = Sonntag. Unsere Liste beginnt bei Montag.
  return WEEKDAYS_LONG[(weekday + 6) % 7] + ', ' + d + '. ' + MONTHS[m - 1];
}

/**
 * Wandelt eine Berliner Wandzeit in einen echten Zeitpunkt um. Der Trick:
 * denselben Moment einmal als UTC und einmal als Berliner Zeit formatieren
 * — die Differenz ist der gesuchte Versatz, inklusive Sommerzeit.
 * An den beiden Umstellungsnächten wäre das ungenau; die liegen nachts um
 * 02:00 und damit außerhalb jeder buchbaren Zeit.
 */
function berlinToDate(dateStr, timeStr) {
  const asUtc = new Date(dateStr + 'T' + timeStr + ':00Z');
  if (isNaN(asUtc)) return null;
  try {
    const inBerlin = new Date(asUtc.toLocaleString('en-US', { timeZone: 'Europe/Berlin' }));
    const inUtc = new Date(asUtc.toLocaleString('en-US', { timeZone: 'UTC' }));
    return new Date(asUtc.getTime() - (inBerlin - inUtc));
  } catch (_) {
    return asUtc;
  }
}

/**
 * Sitzt der Besucher in einer anderen Zeitzone, zeigen wir zusätzlich seine
 * lokale Uhrzeit. Ein Kunde aus Mallorca oder der Schweiz soll nicht selbst
 * umrechnen müssen — und wer in Deutschland sitzt, sieht keinen Zusatz.
 */
function localHint(dateStr, timeStr) {
  let tz = '';
  try {
    tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
  } catch (_) {
    return '';
  }
  if (!tz || tz === 'Europe/Berlin') return '';

  const moment = berlinToDate(dateStr, timeStr);
  if (!moment) return '';
  const local = moment.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
  if (local === timeStr) return '';
  return 'bei Ihnen ' + local + ' Uhr';
}

// ---------------------------------------------------------------------
// Daten holen
// ---------------------------------------------------------------------

async function loadMonth(key) {
  if (monthCache.has(key)) {
    const cached = monthCache.get(key);
    state.days = cached.days;
    state.minMonth = cached.min;
    state.maxMonth = cached.max;
    return;
  }

  state.loading = true;
  state.error = '';
  render();

  try {
    const res = await fetch('/api/booking/slots?month=' + encodeURIComponent(key), {
      headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error('slots');

    const days = data.days || {};
    monthCache.set(key, { days, min: data.min_month, max: data.max_month });
    state.days = days;
    state.minMonth = data.min_month;
    state.maxMonth = data.max_month;
  } catch (_) {
    state.error = 'Die freien Termine lassen sich gerade nicht laden. Bitte versuchen Sie es in einem Moment noch einmal.';
  } finally {
    state.loading = false;
  }
}

/** Springt so lange vorwärts, bis ein Monat mit freien Tagen gefunden ist. */
async function loadFirstMonthWithSlots(startKey) {
  let key = startKey;
  for (let hop = 0; hop < 4; hop++) {
    await loadMonth(key);
    if (state.error) break;
    if (Object.keys(state.days).length > 0) break;
    if (state.maxMonth && key >= state.maxMonth) break;
    key = shiftMonth(key, 1);
  }
  state.month = key;
}

// ---------------------------------------------------------------------
// Rendern
// ---------------------------------------------------------------------

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text != null) node.textContent = text;
  return node;
}

function renderCalendar() {
  const wrap = el('div', 'bk__cal');
  const { year, monthIndex } = parseMonth(state.month);

  // ---- Kopfzeile mit Monatswechsel
  const head = el('div', 'bk__calhead');

  const prev = el('button', 'bk__nav');
  prev.type = 'button';
  prev.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>';
  prev.setAttribute('aria-label', 'Vorheriger Monat');
  prev.disabled = !!state.minMonth && state.month <= state.minMonth;
  prev.addEventListener('click', () => goMonth(-1));

  const next = el('button', 'bk__nav');
  next.type = 'button';
  next.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
  next.setAttribute('aria-label', 'Nächster Monat');
  next.disabled = !!state.maxMonth && state.month >= state.maxMonth;
  next.addEventListener('click', () => goMonth(1));

  head.appendChild(prev);
  head.appendChild(el('h5', 'bk__month', MONTHS[monthIndex] + ' ' + year));
  head.appendChild(next);
  wrap.appendChild(head);

  // ---- Wochentagsleiste
  const grid = el('div', 'bk__grid');
  WEEKDAYS.forEach((label) => {
    const cell = el('span', 'bk__wd', label);
    cell.setAttribute('aria-hidden', 'true');
    grid.appendChild(cell);
  });

  // ---- Leerfelder bis zum ersten Wochentag (Woche beginnt montags)
  const firstWeekday = (new Date(Date.UTC(year, monthIndex, 1)).getUTCDay() + 6) % 7;
  for (let i = 0; i < firstWeekday; i++) grid.appendChild(el('span', 'bk__pad'));

  const daysInMonth = new Date(Date.UTC(year, monthIndex + 1, 0)).getUTCDate();
  for (let day = 1; day <= daysInMonth; day++) {
    const dateStr = year + '-' + pad(monthIndex + 1) + '-' + pad(day);
    const slots = state.days[dateStr];
    const free = Array.isArray(slots) && slots.length > 0;

    const cell = el('button', 'bk__day', String(day));
    cell.type = 'button';
    if (!free) {
      cell.disabled = true;
      cell.setAttribute('aria-label', day + '. ' + MONTHS[monthIndex] + ' — keine freien Zeiten');
    } else {
      cell.setAttribute('aria-label', dayLabel(dateStr) + ' — ' + slots.length + ' freie Zeiten');
      if (state.date === dateStr) {
        cell.classList.add('is-active');
        cell.setAttribute('aria-current', 'date');
      }
      cell.addEventListener('click', () => {
        state.date = dateStr;
        state.time = null;
        render();
        // Auf schmalen Bildschirmen steht die Uhrzeitenliste unter dem
        // Kalender — ohne diesen Sprung sähe der Klick wirkungslos aus.
        if (window.matchMedia('(max-width:860px)').matches) {
          const times = root.querySelector('.bk__times');
          if (times) times.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    }
    grid.appendChild(cell);
  }

  wrap.appendChild(grid);

  const tz = el('p', 'bk__tz');
  tz.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Alle Zeiten in mitteleuropäischer Zeit (Berlin)';
  wrap.appendChild(tz);

  return wrap;
}

function renderTimes() {
  const wrap = el('div', 'bk__times');

  if (!state.date) {
    wrap.classList.add('bk__times--empty');
    wrap.appendChild(el('p', 'bk__hint', 'Wählen Sie links einen Tag — die freien Uhrzeiten erscheinen dann hier.'));
    return wrap;
  }

  // Eigener Innencontainer: auf dem Desktop wird er absolut in die Spalte
  // gelegt, damit die Liste die Höhe des Kalenders ÜBERNIMMT statt sie zu
  // bestimmen. Ohne ihn würden 16 Uhrzeiten die ganze Card in die Länge
  // ziehen und links neben dem Kalender eine leere Fläche hinterlassen.
  const inner = el('div', 'bk__timesinner');
  inner.appendChild(el('h5', 'bk__timeshead', dayLabel(state.date)));

  const list = el('div', 'bk__slots');
  (state.days[state.date] || []).forEach((time) => {
    const row = el('div', 'bk__slotrow');
    const selected = state.time === time;
    if (selected) row.classList.add('is-selected');

    const btn = el('button', 'bk__slot');
    btn.type = 'button';
    btn.setAttribute('aria-pressed', String(selected));

    const label = el('span', 'bk__slottime', time + ' Uhr');
    btn.appendChild(label);

    const hint = localHint(state.date, time);
    if (hint) btn.appendChild(el('span', 'bk__slotlocal', hint));

    btn.addEventListener('click', () => {
      state.time = selected ? null : time;
      render();
    });
    row.appendChild(btn);

    if (selected) {
      const go = el('button', 'bk__confirm', 'Weiter');
      go.type = 'button';
      go.addEventListener('click', () => {
        state.step = 'form';
        render();
      });
      row.appendChild(go);
    }

    list.appendChild(row);
  });

  inner.appendChild(list);
  wrap.appendChild(inner);
  return wrap;
}

function field(name, label, type, options) {
  const opts = options || {};
  const wrap = el('label', 'bk__field');

  const caption = el('span', 'bk__label', label);
  if (opts.optional) {
    const badge = el('span', 'bk__optional', ' (optional)');
    caption.appendChild(badge);
  }
  wrap.appendChild(caption);

  const input = type === 'textarea' ? el('textarea') : el('input');
  if (type !== 'textarea') input.type = type;
  input.name = name;
  input.id = 'bk-' + name;
  if (!opts.optional) input.required = true;
  if (opts.placeholder) input.placeholder = opts.placeholder;
  if (opts.autocomplete) input.autocomplete = opts.autocomplete;
  if (opts.maxlength) input.maxLength = opts.maxlength;
  if (type === 'textarea') input.rows = 3;
  if (opts.value) input.value = opts.value;

  wrap.appendChild(input);
  wrap.appendChild(el('span', 'bk__error'));
  return wrap;
}

function renderForm() {
  const wrap = el('div', 'bk__form');
  const prefill = (state.reschedule && state.reschedule.prefill) || {};

  const back = el('button', 'bk__back');
  back.type = 'button';
  back.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg> Anderer Termin';
  back.addEventListener('click', () => {
    state.step = 'pick';
    render();
  });
  wrap.appendChild(back);

  const summary = el('div', 'bk__summary');
  summary.innerHTML =
    '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M8 3v3M16 3v3M3 10h18"/></svg>';
  summary.appendChild(el('b', null, dayLabel(state.date)));
  summary.appendChild(el('span', null, state.time + ' – ' + addMinutes(state.time, 30) + ' Uhr'));
  wrap.appendChild(summary);

  const form = el('form', 'bk__fields');
  form.noValidate = true;
  form.appendChild(field('name', 'Ihr Name', 'text', { placeholder: 'Vor- und Nachname', autocomplete: 'name', maxlength: 80, value: prefill.name }));
  form.appendChild(field('email', 'Ihre E-Mail', 'email', { placeholder: 'name@firma.de', autocomplete: 'email', maxlength: 120, value: prefill.email }));
  form.appendChild(field('phone', 'Telefon', 'tel', { optional: true, placeholder: '+49 …', autocomplete: 'tel', maxlength: 40, value: prefill.phone }));
  form.appendChild(field('company', 'Firma & Ort', 'text', { optional: true, placeholder: 'Muster GmbH, Dorsten', maxlength: 120, value: prefill.company }));
  form.appendChild(field('message', 'Worum geht es?', 'textarea', { optional: true, placeholder: 'Ein, zwei Sätze genügen.', maxlength: 2000, value: prefill.message }));

  // Honigtopf: für Menschen unsichtbar, für einfache Bots verlockend.
  const honey = el('div', 'bk__honey');
  honey.setAttribute('aria-hidden', 'true');
  const honeyInput = el('input');
  honeyInput.type = 'text';
  honeyInput.name = 'website';
  honeyInput.tabIndex = -1;
  honeyInput.autocomplete = 'off';
  honey.appendChild(honeyInput);
  form.appendChild(honey);

  const submit = el('button', 'btn btn--primary bk__submit', state.reschedule ? 'Termin verschieben' : 'Termin verbindlich buchen');
  submit.type = 'submit';
  form.appendChild(submit);

  const status = el('p', 'bk__status');
  status.setAttribute('role', 'status');
  form.appendChild(status);

  const legal = el('p', 'bk__legal');
  legal.innerHTML = 'Mit dem Buchen stimmen Sie zu, dass ich Ihre Angaben zur Durchführung des Gesprächs '
    + 'verarbeite. Details in der <a href="/datenschutz/" target="_blank" rel="noopener">Datenschutzerklärung</a>.';
  form.appendChild(legal);

  form.addEventListener('submit', (ev) => {
    ev.preventDefault();
    submitBooking(form, submit, status);
  });

  wrap.appendChild(form);
  return wrap;
}

function addMinutes(time, minutes) {
  const [h, m] = time.split(':').map(Number);
  const total = h * 60 + m + minutes;
  return pad(Math.floor(total / 60) % 24) + ':' + pad(total % 60);
}

function renderDone() {
  const b = state.booking;
  const wrap = el('div', 'bk__done');

  const mark = el('div', 'bk__check');
  mark.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
  wrap.appendChild(mark);

  wrap.appendChild(el('h4', 'bk__donetitle', state.reschedule ? 'Termin verschoben' : 'Termin steht!'));
  wrap.appendChild(el('p', 'bk__donesub', 'Eine Bestätigung ist unterwegs an ' + b.email + ' — mit Kalendereintrag zum Anklicken.'));

  const facts = el('div', 'bk__facts');
  [['Termin', b.date_label], ['Uhrzeit', b.time_label]].forEach(([label, value]) => {
    const row = el('div', 'bk__factrow');
    row.appendChild(el('span', 'bk__factlabel', label));
    row.appendChild(el('span', 'bk__factvalue', value));
    facts.appendChild(row);
  });
  wrap.appendChild(facts);

  if (b.meeting_url) {
    const link = el('a', 'btn btn--primary bk__joinlink', 'Videoraum öffnen');
    link.href = b.meeting_url;
    link.target = '_blank';
    link.rel = 'noopener';
    wrap.appendChild(link);
    wrap.appendChild(el('p', 'bk__hint', 'Der Link steht auch in Ihrer Bestätigungsmail — Sie müssen ihn sich nicht merken.'));
  }

  const manage = el('a', 'bk__manage', 'Termin absagen oder verschieben');
  manage.href = b.manage_url;
  wrap.appendChild(manage);

  return wrap;
}

function render() {
  root.setAttribute('data-step', state.step);
  root.innerHTML = '';

  if (state.reschedule && state.step !== 'done') {
    const banner = el('div', 'bk__banner');
    banner.appendChild(el('b', null, 'Sie verschieben Ihren Termin'));
    banner.appendChild(el('span', null, state.reschedule.dateLabel + ', ' + state.reschedule.timeLabel
      + ' — der alte Termin wird abgesagt, sobald der neue steht.'));
    root.appendChild(banner);
  }

  if (state.step === 'done') {
    root.appendChild(renderDone());
    return;
  }

  if (state.step === 'form') {
    root.appendChild(renderForm());
    const first = root.querySelector('input[name="name"]');
    if (first) first.focus({ preventScroll: true });
    return;
  }

  if (state.error) {
    const err = el('div', 'bk__msg bk__msg--err');
    err.appendChild(el('p', null, state.error));
    const retry = el('button', 'btn btn--ghost');
    retry.type = 'button';
    retry.textContent = 'Erneut versuchen';
    retry.addEventListener('click', async () => {
      monthCache.delete(state.month);
      await loadMonth(state.month);
      render();
    });
    err.appendChild(retry);
    root.appendChild(err);
    return;
  }

  if (state.loading && !state.month) {
    root.appendChild(el('div', 'bk__msg', 'Freie Termine werden geladen …'));
    return;
  }

  const stage = el('div', 'bk__stage');
  if (state.date) stage.classList.add('bk__stage--split');
  stage.appendChild(renderCalendar());
  stage.appendChild(renderTimes());
  if (state.loading) stage.classList.add('is-loading');
  root.appendChild(stage);

  if (!state.loading && Object.keys(state.days).length === 0) {
    root.appendChild(el('p', 'bk__hint bk__hint--none',
      'In diesem Monat ist nichts mehr frei. Blättern Sie einen Monat weiter — oder rufen Sie einfach an: +49 176 55769680.'));
  }
}

// ---------------------------------------------------------------------
// Aktionen
// ---------------------------------------------------------------------

async function goMonth(delta) {
  const target = shiftMonth(state.month, delta);
  if (state.minMonth && target < state.minMonth) return;
  if (state.maxMonth && target > state.maxMonth) return;
  state.month = target;
  state.date = null;
  state.time = null;
  await loadMonth(target);
  render();
}

async function submitBooking(form, submit, status) {
  const values = {};
  ['name', 'email', 'phone', 'company', 'message', 'website'].forEach((key) => {
    const input = form.elements[key];
    values[key] = input ? input.value.trim() : '';
  });

  form.querySelectorAll('.bk__field').forEach((f) => f.classList.remove('has-error'));
  form.querySelectorAll('.bk__error').forEach((e) => { e.textContent = ''; });

  // Erst im Browser prüfen: spart einen Roundtrip und zeigt den Fehler
  // direkt am Feld statt als Sammelmeldung.
  const problems = {};
  if (values.name.length < 2) problems.name = 'Bitte geben Sie Ihren Namen an.';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(values.email)) problems.email = 'Bitte prüfen Sie Ihre E-Mail-Adresse.';
  if (Object.keys(problems).length > 0) {
    showFieldErrors(form, problems);
    return;
  }

  submit.disabled = true;
  submit.textContent = 'Wird gebucht …';
  status.className = 'bk__status';
  status.textContent = '';

  try {
    const res = await fetch('/api/booking/book', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        date: state.date,
        time: state.time,
        name: values.name,
        email: values.email,
        phone: values.phone,
        company: values.company,
        message: values.message,
        website: values.website,
        cancel_token: state.reschedule ? state.reschedule.token : '',
      }),
    });
    const data = await res.json().catch(() => ({}));

    if (res.ok && data.success && data.booking) {
      state.booking = data.booking;
      state.step = 'done';
      // Der Slot ist jetzt weg — gecachte Monate wären sofort veraltet.
      monthCache.clear();
      render();
      root.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    if (res.status === 409) {
      status.className = 'bk__status bk__status--err';
      status.textContent = 'Dieser Termin wurde gerade von jemand anderem gebucht. Bitte wählen Sie einen anderen.';
      monthCache.clear();
      state.time = null;
      setTimeout(async () => {
        state.step = 'pick';
        await loadMonth(state.month);
        render();
      }, 1800);
      return;
    }

    if (res.status === 422 && data.fields) {
      showFieldErrors(form, data.fields);
      return;
    }

    if (res.status === 429) {
      status.className = 'bk__status bk__status--err';
      status.textContent = 'Es liegen bereits mehrere Buchungen von diesem Anschluss vor. Bitte rufen Sie kurz an: +49 176 55769680.';
      return;
    }

    throw new Error('failed');
  } catch (_) {
    status.className = 'bk__status bk__status--err';
    status.innerHTML = 'Die Buchung hat leider nicht geklappt. Rufen Sie mich an: '
      + '<a href="tel:+4917655769680">+49 176 55769680</a> — oder schreiben Sie an '
      + '<a href="mailto:Info@jungline.de">Info@jungline.de</a>.';
  } finally {
    submit.disabled = false;
    submit.textContent = state.reschedule ? 'Termin verschieben' : 'Termin verbindlich buchen';
  }
}

function showFieldErrors(form, fields) {
  let firstBad = null;
  Object.keys(fields).forEach((key) => {
    const input = form.elements[key];
    if (!input) return;
    const wrap = input.closest('.bk__field');
    if (!wrap) return;
    wrap.classList.add('has-error');
    const msg = wrap.querySelector('.bk__error');
    if (msg) msg.textContent = fields[key];
    if (!firstBad) firstBad = input;
  });
  if (firstBad) firstBad.focus();
}

// ---------------------------------------------------------------------
// Start
// ---------------------------------------------------------------------

/**
 * Kommt der Besucher über den Verschieben-Link aus einer Mail, holen wir die
 * Daten des alten Termins — damit er seinen Namen nicht erneut eintippt und
 * sieht, welchen Termin er gerade ersetzt.
 */
async function loadReschedule(token) {
  try {
    const res = await fetch('/api/booking/cancel?format=json&token=' + encodeURIComponent(token), {
      headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    if (!res.ok || !data.success || data.status !== 'confirmed') return;
    state.reschedule = {
      token,
      dateLabel: data.date_label,
      timeLabel: data.time_label,
      prefill: data.prefill || {},
    };
  } catch (_) {
    // Kein Drama: dann wird eben ein ganz normaler neuer Termin gebucht.
  }
}

async function init() {
  const params = new URLSearchParams(window.location.search);
  const token = params.get('verschieben');
  if (token) await loadReschedule(token);

  const now = new Date();
  await loadFirstMonthWithSlots(monthKey(now.getFullYear(), now.getMonth()));
  render();
}

init();
