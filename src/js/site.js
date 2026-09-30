(function(){
  // Der Buchungskalender (src/js/booking.js) wird geladen, wenn er sich
  // nähert (anderthalb Bildschirmhöhen vorher) — so steht er fertig da,
  // bevor er ins Bild kommt. Auf allen Seiten ohne Widget — also überall außer
  // Startseite und /kontakt/ — passiert hier gar nichts.
  var widget = document.getElementById('bookingWidget');
  if(!widget) return;
  var loaded = false;
  var load = function(){
    if(loaded) return;
    loaded = true;
    import('./booking.js');
  };
  // Wer über den Verschieben-Link aus einer Mail kommt, landet direkt im
  // Buchungsvorgang — hier auf den Beobachter zu warten wäre unnötige Verzögerung.
  if(window.location.search.indexOf('verschieben=') > -1){
    load();
  } else if('IntersectionObserver' in window){
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){ if(entry.isIntersecting) load(); });
    }, {rootMargin:'150% 0px'});
    io.observe(widget);
  } else {
    load();
  }
})();

/* ============================================================
   BESUCHERSTATISTIK — ohne Cookies, ohne Kennung
   Zählt Seitenaufrufe und eine feste Liste von Ereignissen auf dem eigenen
   Server (/api/stats, Speicherregeln in public/api/_stats.php). Auf dem Gerät
   wird nichts gespeichert oder ausgelesen, deshalb braucht es keine
   Einwilligung. Wer "Do Not Track" oder "Global Privacy Control" gesetzt hat,
   wird trotzdem nicht gezählt.
   Die Messpunkte hängen bewusst NICHT in den einzelnen Modulen, sondern
   beobachten deren Zustände (data-state, data-step, Klassen) von außen: So
   bleibt die Zählung ein Anbau, der nichts an Check, Kalender oder Formular
   verändern kann.
   ============================================================ */
(function(){
  var nav = window.navigator || {};
  var aus = nav.doNotTrack === '1' || window.doNotTrack === '1' || nav.globalPrivacyControl === true
    || /localhost|127\.0\.0\.1/.test(location.hostname);
  var senden = function(daten){
    if(aus) return;
    var body = JSON.stringify(daten);
    try {
      if(nav.sendBeacon && nav.sendBeacon('/api/stats', new Blob([body], {type:'application/json'}))) return;
    } catch(e){}
    try { fetch('/api/stats', {method:'POST', body:body, keepalive:true, headers:{'Content-Type':'application/json'}}); } catch(e){}
  };
  var gezaehlt = {};
  // Ereignisse, die pro Seitenaufruf höchstens einmal zählen sollen.
  var einmalig = {chooser_shown:1, menu_open:1, gsp_switch:1};
  window.jlTrack = function(name){
    if(einmalig[name]){ if(gezaehlt[name]) return; gezaehlt[name] = 1; }
    senden({e: name});
  };

  // Seitenaufruf: Pfad ohne Abfrage und Anker, Herkunft nur als Hostname.
  var ref = '';
  try {
    var r = document.referrer ? new URL(document.referrer) : null;
    if(r && r.hostname !== location.hostname) ref = r.hostname;
  } catch(e){}
  senden({p: location.pathname, r: ref, m: window.matchMedia('(max-width: 767px)').matches ? 1 : 0});

  if(document.documentElement.classList.contains('zweig-wahl')) window.jlTrack('chooser_shown');

  // Klicks: Telefon, E-Mail, Termin-Knöpfe, Startscreen-Wahl.
  document.addEventListener('click', function(e){
    var a = e.target.closest && e.target.closest('a,button');
    if(!a) return;
    var href = a.getAttribute('href') || '';
    if(href.indexOf('tel:') === 0) return window.jlTrack('tel_click');
    if(href.indexOf('mailto:') === 0) return window.jlTrack('mail_click');
    var wahl = a.getAttribute('data-zweig-wahl');
    if(wahl) return window.jlTrack(wahl === 'webdesign' ? 'chooser_webdesign' : 'chooser_seo');
    if(a.hasAttribute('data-zweig-skip')) return window.jlTrack('chooser_skip');
    if(/#termin$|\/kontakt\/#termin$/.test(href) && a.classList.contains('btn')) window.jlTrack('cta_termin');
  }, {capture:true, passive:true});

  // Zustände der Werkzeuge beobachten.
  var beobachte = function(el, attr, fn){
    if(!el || !window.MutationObserver) return;
    var vorher = el.getAttribute(attr);
    new MutationObserver(function(){
      var jetzt = el.getAttribute(attr);
      if(jetzt !== vorher){ vorher = jetzt; fn(jetzt); }
    }).observe(el, {attributes:true, attributeFilter:[attr]});
  };
  beobachte(document.getElementById('gbp-badge'), 'data-state', function(z){
    if(z === 'loading') window.jlTrack('check_start');
    else if(z === 'result') window.jlTrack('check_result');
    else if(z === 'error') window.jlTrack('check_error');
  });
  beobachte(document.getElementById('pscWidget'), 'data-state', function(z){
    if(z === 'loading') window.jlTrack('speed_start');
  });
  Array.prototype.forEach.call(document.querySelectorAll('.bk'), function(bk){
    beobachte(bk, 'data-step', function(z){
      if(z === 'form') window.jlTrack('booking_day');
      else if(z === 'done') window.jlTrack('booking_done');
    });
  });
  beobachte(document.getElementById('formStatus'), 'class', function(z){
    if(/form-status--ok/.test(z || '')) window.jlTrack('contact_sent');
  });
})();


(function(){
  // Das PageSpeed-Check-Widget (src/js/pagespeed-check.js) wird erst
  // geladen, wenn es in Sichtweite kommt. Nur auf /webdesign/ vorhanden —
  // auf allen anderen Seiten passiert hier nichts.
  var widget = document.getElementById('pscWidget');
  if(!widget) return;
  var loaded = false;
  var load = function(){
    if(loaded) return;
    loaded = true;
    import('./pagespeed-check.js');
  };
  if('IntersectionObserver' in window){
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){ if(entry.isIntersecting) load(); });
    }, {rootMargin:'600px'});
    io.observe(widget);
  } else {
    load();
  }
})();

(function(){
  // GBP-Profil-Check-Badge: schickt Firmenname/Stadt/Keyword an die eigene
  // Backend-Route /api/gbp-check (kein Google-Key im Frontend) und zeigt
  // den Vollständigkeits-Score als Ring + Checkliste an.
  var badge = document.getElementById('gbp-badge');
  if(!badge) return;
  var form = document.getElementById('gbpForm');
  var ringValue = badge.querySelector('.gbp-ring__value');
  var ringNum = document.getElementById('gbpScoreNum');
  var nameEl = document.getElementById('gbpCompanyName');
  var checklist = document.getElementById('gbpChecklist');
  var errorText = document.getElementById('gbpErrorText');
  var stage = badge.closest ? badge.closest('.rank-check__stage') : null;

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var CIRC = 2 * Math.PI * 52;
  if(ringValue){
    ringValue.style.strokeDasharray = CIRC.toFixed(2);
    ringValue.style.strokeDashoffset = CIRC.toFixed(2);
  }

  // Der Radar-Scan hinter der Card gehört zum Wartezustand: er läuft, solange
  // das Formular offen ist und während geprüft wird. Sobald das Ergebnis steht,
  // wäre er nur noch ein Strich, der quer über die Card zieht — dann übernimmt
  // die ruhige "geprüft"-Aura (siehe .rank-check__verified im CSS).
  var setState = function(state){
    badge.setAttribute('data-state', state);
    if(stage) stage.setAttribute('data-scan', (state === 'form' || state === 'loading') ? 'on' : 'off');
  };

  var CHECK_LABELS = {categories:'Kategorien', photos:'Fotos', hours:'Öffnungszeiten', reviews:'Bewertungen', website:'Website verlinkt'};
  var CHECK_ORDER = ['categories', 'photos', 'hours', 'reviews', 'website'];
  // Bewusst KEIN Prozent-Score: "75%" oder "100%" vermittelt den falschen
  // Eindruck, das Profil sei schon (fast) fertig optimiert. Der Ring zeigt
  // deshalb "X von 25+" statt einer Fertigstellungs-Quote.
  var TOTAL_FACTORS = 25;
  var factorTotalEl = document.getElementById('gbpFactorTotal');
  if(factorTotalEl) factorTotalEl.textContent = String(TOTAL_FACTORS);

  // ---- Score = Profil-Basis + Google-Platzierung --------------------------
  // Die 5 Profil-Checks allein ergaben höchstens 5 von 25. Bei einem Betrieb,
  // der im Vergleich daneben auf Platz 3 steht, sah der Ring damit fast leer
  // aus — die beiden Cards widersprachen sich. Die Platzierung ist der
  // sichtbarste Beleg dafür, dass die Grundlagen greifen, und zählt deshalb
  // mit bis zu 10 Faktoren mit.
  //
  // Das Maximum bleibt bewusst 15 von 25: auch ein perfekt platziertes Profil
  // ist nie "fertig". Die restlichen Faktoren (Beschreibung, Leistungen &
  // Attribute, Bewertungsstrategie, NAP-Konsistenz, Monitoring) prüfen wir
  // manuell — genau die listet der CTA-Block unter den Cards auf. So bleibt
  // der Ring sichtbar offen, ohne einen gut rankenden Betrieb schlechtzureden.
  var BASE_MAX = CHECK_ORDER.length;        // 5
  var RANK_MAX = 10;
  var SCORE_MAX = BASE_MAX + RANK_MAX;      // 15 von 25 = 60% Ringfüllung

  var rankPoints = function(pos){
    if(!pos || pos < 1) return 0;
    if(pos <= 5) return RANK_MAX - (pos - 1);   // 1→10, 2→9, 3→8, 4→7, 5→6
    if(pos <= 10) return 4;
    if(pos <= 20) return 2;
    return 0;
  };

  // rank: null = noch unbekannt (Vergleich läuft/fehlgeschlagen) → keine Zeile,
  // 0 = nicht unter den ersten 20 Treffern, sonst die Platznummer.
  var scoreState = {completeness: {}, base: 0, rank: null};

  // Ampel-Farblogik für den Ring: die ersten 25% der Skala (0-25%) dunkelrot,
  // 25-50% helleres Rot, 50-75% Orange, erst ab 75% ein GRADUELLER Übergang
  // zu Grün (kein abruptes Umspringen auf Grün genau bei 75%).
  //
  // Wichtig: die Skala läuft über das ERREICHBARE Maximum (SCORE_MAX = 15),
  // nicht über die 25 Gesamtfaktoren. Sonst wäre selbst das bestmögliche
  // Ergebnis (Platz 1, alle Basis-Checks erfüllt) bei 60% noch orange. So
  // trennen sich die beiden Aussagen sauber: die FARBE bewertet, wie gut der
  // Betrieb in dem dasteht, was wir hier messen können — die FÜLLUNG zeigt,
  // wie viel vom Gesamtbild damit überhaupt abgedeckt ist. Ein Betrieb auf
  // Platz 1 sieht also einen grünen, aber nur zu 60% gefüllten Ring.
  var RING_DARK_RED = [122, 46, 40];
  var RING_LIGHT_RED = [196, 88, 74];
  var RING_ORANGE = [214, 138, 60];
  var RING_GREEN = [85, 211, 150];
  var mixRgb = function(a, b, t){
    return [
      Math.round(a[0] + (b[0] - a[0]) * t),
      Math.round(a[1] + (b[1] - a[1]) * t),
      Math.round(a[2] + (b[2] - a[2]) * t)
    ];
  };
  var ringColor = function(pct){
    pct = Math.max(0, Math.min(100, pct));
    var rgb;
    if(pct <= 25) rgb = RING_DARK_RED;
    else if(pct <= 50) rgb = RING_LIGHT_RED;
    else if(pct <= 75) rgb = RING_ORANGE;
    else rgb = mixRgb(RING_ORANGE, RING_GREEN, (pct - 75) / 25);
    return 'rgb(' + rgb[0] + ',' + rgb[1] + ',' + rgb[2] + ')';
  };

  // Die Zahl im Ring zählt hoch statt zu springen. Das ist nicht nur Deko: der
  // Wert kommt in zwei Schritten (erst die Profil-Basis, dann die Platzierung
  // aus dem Vergleich) — ohne Zählung sähe der zweite Schritt wie ein Glitch aus.
  var numFrame = null, numFailsafe = null;
  var setScoreNum = function(to){
    var from = parseInt(ringNum.textContent, 10) || 0;
    if(numFrame) cancelAnimationFrame(numFrame);
    if(numFailsafe) clearTimeout(numFailsafe);
    if(reduceMotion || from === to || !window.requestAnimationFrame){
      ringNum.textContent = String(to);
      return;
    }
    // Failsafe: requestAnimationFrame ruht in Hintergrund-Tabs. Ohne diesen
    // Timer bliebe im Ring eine 0 stehen, bis der Tab wieder aktiv wird.
    numFailsafe = setTimeout(function(){ ringNum.textContent = String(to); }, 1200);
    // Startzeit aus dem ersten Frame statt aus performance.now() davor: beide
    // Uhren müssen nicht dieselbe sein, und ein negatives Delta würde die Zahl
    // unter den Startwert ziehen. Zusätzlich hart auf 0..1 geklemmt.
    var start = null, dur = 700;
    var tick = function(now){
      if(start === null) start = now;
      var p = Math.max(0, Math.min(1, (now - start) / dur));
      var eased = 1 - Math.pow(1 - p, 3);
      ringNum.textContent = String(Math.round(from + (to - from) * eased));
      if(p < 1) numFrame = requestAnimationFrame(tick);
    };
    numFrame = requestAnimationFrame(tick);
  };

  var paintScore = function(){
    var score = scoreState.base + rankPoints(scoreState.rank);
    setScoreNum(score);
    if(ringValue){
      ringValue.style.strokeDashoffset = (CIRC - (CIRC * Math.min(score / TOTAL_FACTORS, 1))).toFixed(2);
      ringValue.style.stroke = ringColor((score / SCORE_MAX) * 100);
    }
  };

  var buildCheckRow = function(label, text, good, index){
    var li = document.createElement('li');
    li.style.setProperty('--i', index);
    var labelEl = document.createElement('span');
    labelEl.textContent = label;
    var chip = document.createElement('span');
    chip.className = 'bam__chip ' + (good ? 'bam__chip--top' : 'bam__chip--warn');
    chip.textContent = text;
    li.appendChild(labelEl);
    li.appendChild(chip);
    return li;
  };

  var renderResult = function(data){
    nameEl.textContent = data.company_name || '';

    scoreState.completeness = data.completeness || {};
    scoreState.base = CHECK_ORDER.reduce(function(n, key){ return n + (scoreState.completeness[key] ? 1 : 0); }, 0);
    scoreState.rank = null;

    // Bei 0 anfangen, damit Ring und Zahl sichtbar auf den Wert hochlaufen —
    // der Check soll wie eine Prüfung wirken, die gerade durchläuft.
    ringNum.textContent = '0';
    if(ringValue) ringValue.style.strokeDashoffset = CIRC.toFixed(2);

    checklist.innerHTML = '';
    CHECK_ORDER.forEach(function(key, i){
      var ok = !!scoreState.completeness[key];
      checklist.appendChild(buildCheckRow(CHECK_LABELS[key], ok ? 'Erfüllt' : 'Fehlt', ok, i));
    });

    paintScore();
  };

  // Wird nachgereicht, sobald der Wettbewerbsvergleich die eigene Position
  // kennt. pos = 0 bedeutet "nicht unter den ersten 20 Treffern".
  var applyRank = function(pos){
    scoreState.rank = pos;
    var existing = checklist.querySelector('[data-rank-row]');
    if(existing) checklist.removeChild(existing);
    var row = buildCheckRow(
      'Google-Platzierung',
      pos ? ('Platz ' + pos) : 'Nicht in Top 20',
      !!pos && pos <= 3,
      checklist.children.length
    );
    row.setAttribute('data-rank-row', '');
    checklist.appendChild(row);
    paintScore();
  };

  var showError = function(message){
    errorText.textContent = message;
    setState('error');
  };

  // Übersetzt die Fehlerkennung des Backends in einen Satz, den ein Kunde
  // versteht. Bewusst ohne technische Details: Statuscodes, Google-
  // Meldungen oder der Hinweis, WELCHE Sperre gegriffen hat, gehören nicht
  // in die Oberfläche — sie helfen nur dem, der die Sperre umgehen will.
  //
  // Die Meldung soll dem ehrlichen Besucher trotzdem sagen, was er tun
  // kann: kurz warten, es morgen erneut versuchen oder direkt anrufen.
  var ERROR_TEXTS = {
    not_found: 'Zu diesem Unternehmen konnten wir kein Google-Profil finden. Bitte prüfen Sie Firmenname, Stadt und Keyword.',
    missing_fields: 'Bitte füllen Sie alle drei Felder aus — Firmenname, Ort und Leistung.',
    invalid_body: 'Bitte füllen Sie alle drei Felder aus — Firmenname, Ort und Leistung.',
    rate_limited: 'Sie haben den Check gerade mehrfach hintereinander gestartet. Bitte warten Sie ein paar Minuten und versuchen Sie es dann noch einmal.',
    daily_limit_reached: 'Der kostenlose Check ist für heute ausgebucht. Morgen früh steht er wieder zur Verfügung — oder Sie schreiben mir kurz, dann prüfe ich Ihr Profil persönlich.',
    forbidden_origin: 'Der Check lässt sich nur direkt auf jungline.de starten. Bitte laden Sie die Seite neu.',
    service_unavailable: 'Der Check ist gerade nicht möglich. Bitte versuchen Sie es später erneut.'
  };
  var DEFAULT_ERROR = 'Der Check ist gerade nicht möglich. Bitte versuchen Sie es später erneut.';

  var errorTextFor = function(data){
    var key = data && typeof data.error === 'string' ? data.error : '';
    return Object.prototype.hasOwnProperty.call(ERROR_TEXTS, key) ? ERROR_TEXTS[key] : DEFAULT_ERROR;
  };

  // ---- Wettbewerbsvergleich: zweiter Block, startet automatisch sobald
  // der Profil-Check oben erfolgreich war. Eigene Backend-Route
  // /api/gbp-compare, die intern die Places API (New) Text Search nutzt. ----
  var compare = document.getElementById('gbp-compare');
  var compareList = document.getElementById('gbpCompareList');
  var compareGaps = document.getElementById('gbpCompareGaps');
  var compareSub = document.getElementById('gbpCompareSub');
  var rankSection = document.getElementById('rank-check');
  // Solange der Vergleich aktiv ist (loading/result/empty/error) ersetzt er
  // links den Intro-Text — dazu bekommt die Sektion eine Marker-Klasse.
  var setCompareState = function(state){
    if(compare) compare.setAttribute('data-state', state);
    if(rankSection) rankSection.classList.toggle('rank-check--comparing', state !== 'hidden');
    // Mit dieser Klasse wird der CTA-Block darunter erst sichtbar. Seine Icons
    // waren beim ersten Durchlauf display:none und damit nicht vermessbar —
    // jetzt können sie nachgezogen werden und zeichnen sich wie alle anderen.
    if(state !== 'hidden' && typeof CustomEvent === 'function'){
      document.dispatchEvent(new CustomEvent('lico:rescan'));
    }
  };

  var fmtRating = function(r){
    return (typeof r === 'number' && r > 0) ? (Math.round(r * 10) / 10).toFixed(1).replace('.', ',') : '–';
  };

  var buildRow = function(rank, name, rating, reviewCount, isYou){
    var row = document.createElement('div');
    row.className = 'bam__row ' + (isYou ? 'bam__row--you' : 'bam__row--comp');
    var rankEl = document.createElement('span');
    rankEl.className = 'bam__rank' + (isYou ? ' bam__rank--you' : '');
    rankEl.textContent = String(rank);
    var body = document.createElement('span');
    body.className = 'bam__body';
    var nameEl2 = document.createElement('span');
    nameEl2.className = 'bam__name';
    nameEl2.textContent = name;
    var meta = document.createElement('span');
    meta.className = 'bam__meta';
    var stars = document.createElement('span');
    stars.className = 'bam__stars';
    stars.textContent = '★';
    meta.appendChild(stars);
    meta.appendChild(document.createTextNode(' ' + fmtRating(rating) + ' · '));
    var count = document.createElement('span');
    count.className = 'bam__count';
    count.textContent = reviewCount + ' Bewertungen';
    meta.appendChild(count);
    body.appendChild(nameEl2);
    body.appendChild(meta);
    row.appendChild(rankEl);
    row.appendChild(body);
    if(isYou){
      var chip = document.createElement('span');
      chip.className = 'bam__chip bam__chip--top';
      chip.textContent = 'Ihre Firma';
      row.appendChild(chip);
    }
    return row;
  };

  var buildOwnBelowRow = function(own, company){
    var row = document.createElement('div');
    row.className = 'bam__row bam__row--you bam__row--own-below';
    var rankEl = document.createElement('span');
    rankEl.className = 'bam__rank bam__rank--you';
    rankEl.textContent = own.found ? String(own.position) : '?';
    var body = document.createElement('span');
    body.className = 'bam__body';
    var nameEl2 = document.createElement('span');
    nameEl2.className = 'bam__name';
    nameEl2.textContent = own.found ? own.name : company;
    var meta = document.createElement('span');
    meta.className = 'bam__meta';
    if(own.found){
      var stars = document.createElement('span');
      stars.className = 'bam__stars';
      stars.textContent = '★';
      meta.appendChild(stars);
      meta.appendChild(document.createTextNode(' ' + fmtRating(own.rating) + ' · '));
      var count = document.createElement('span');
      count.className = 'bam__count';
      count.textContent = own.review_count + ' Bewertungen';
      meta.appendChild(count);
    } else {
      meta.textContent = 'Nicht unter den ersten 20 Treffern';
    }
    body.appendChild(nameEl2);
    body.appendChild(meta);
    var chip = document.createElement('span');
    chip.className = 'bam__chip ' + (own.found ? 'bam__chip--top' : 'bam__chip--warn');
    chip.textContent = own.found ? ('Platz ' + own.position) : 'Außerhalb der Top 20';
    row.appendChild(rankEl);
    row.appendChild(body);
    row.appendChild(chip);
    return row;
  };

  var addGapRow = function(label, text, good){
    var li = document.createElement('li');
    // Gleiche gestaffelte Einblendung wie die Basis-Checkliste (teilt sich
    // .gbp-checklist): ohne --i liefen beide Zeilen gleichzeitig ein.
    li.style.setProperty('--i', compareGaps.children.length);
    var labelEl = document.createElement('span');
    labelEl.textContent = label;
    var chip = document.createElement('span');
    chip.className = 'bam__chip ' + (good ? 'bam__chip--top' : 'bam__chip--warn');
    chip.textContent = text;
    li.appendChild(labelEl);
    li.appendChild(chip);
    compareGaps.appendChild(li);
  };

  var renderCompare = function(data, ctx){
    if(!data.result_count){
      setCompareState('empty');
      return;
    }

    var top3 = data.top3 || [];
    var own = data.own || {found: false};
    var ownInTop3 = own.found && own.position <= 3;

    // Die Platzierung fließt in den Basis-Check nebenan ein — erst hier ist
    // sie bekannt. Schlägt der Vergleich fehl, bleibt der Ring bei der reinen
    // Profil-Basis stehen, statt eine erfundene Platzierung zu behaupten.
    applyRank(own.found ? own.position : 0);

    compareList.innerHTML = '';
    top3.forEach(function(p, i){
      compareList.appendChild(buildRow(i + 1, p.name, p.rating, p.review_count, ownInTop3 && i === own.position - 1));
    });
    if(!ownInTop3){
      compareList.appendChild(buildOwnBelowRow(own, ctx.company));
    }

    compareGaps.innerHTML = '';
    var top1 = top3[0];
    var ownRating = own.found ? own.rating : ctx.ownRating;
    var ownReviews = own.found ? own.review_count : ctx.ownReviews;
    if(top1){
      addGapRow('Rating', fmtRating(ownRating) + ' vs. ' + fmtRating(top1.rating) + ' bei Platz 1', ownRating >= top1.rating);
      addGapRow('Bewertungen', ownReviews + ' vs. ' + top1.review_count + ' bei Platz 1', ownReviews >= top1.review_count);
    }

    setCompareState('result');
  };

  var scrollToCompare = function(){
    // Zum Anfang der Sektion scrollen, damit beide Spalten (Vergleich links,
    // Basis-Check rechts) gemeinsam im Blick sind.
    var target = rankSection || compare;
    var y = target.getBoundingClientRect().top + window.scrollY - 72;
    window.scrollTo({top: y, behavior: reduceMotion ? 'auto' : 'smooth'});
  };

  // Der Vergleich läuft durch dieselben Sperren wie der Profil-Check und
  // kann deshalb dieselben Gründe haben — nur mit eigenem Wortlaut, weil
  // hier bereits ein Ergebnis auf dem Schirm steht.
  var compareErrorText = document.getElementById('gbpCompareErrorText');
  var COMPARE_ERROR_TEXTS = {
    rate_limited: 'Der Vergleich wurde gerade mehrfach hintereinander gestartet. Bitte in ein paar Minuten noch einmal versuchen.',
    daily_limit_reached: 'Der Wettbewerbsvergleich ist für heute ausgebucht. Morgen früh steht er wieder zur Verfügung.',
    forbidden_origin: 'Der Vergleich lässt sich nur direkt auf jungline.de starten. Bitte laden Sie die Seite neu.'
  };

  var showCompareError = function(data){
    if(compareErrorText){
      var key = data && typeof data.error === 'string' ? data.error : '';
      compareErrorText.textContent = Object.prototype.hasOwnProperty.call(COMPARE_ERROR_TEXTS, key)
        ? COMPARE_ERROR_TEXTS[key]
        : 'Der Wettbewerbsvergleich ist gerade nicht verfügbar.';
    }
    setCompareState('error');
  };

  var fetchCompare = function(ctx){
    if(!compare) return;
    if(compareSub) compareSub.textContent = 'Basierend auf echten Google-Daten für „' + ctx.keyword + '“ in ' + ctx.city + '.';
    setCompareState('loading');
    scrollToCompare();
    fetch('/api/gbp-compare', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({company: ctx.company, city: ctx.city, keyword: ctx.keyword, place_id: ctx.placeId || ''})
    })
      .then(function(res){
        return res.json().catch(function(){ return {}; }).then(function(data){ return {ok: res.ok, data: data}; });
      })
      .then(function(r){
        if(r.ok && r.data && r.data.success){
          renderCompare(r.data, ctx);
        } else {
          showCompareError(r.data);
        }
      })
      .catch(function(){ showCompareError(null); });
  };

  if(form) form.addEventListener('submit', function(ev){
    ev.preventDefault();
    var company = (document.getElementById('gbp-company').value || '').trim();
    var city = (document.getElementById('gbp-city').value || '').trim();
    var keyword = (document.getElementById('gbp-keyword').value || '').trim();
    if(!company || !city || !keyword){ form.reportValidity && form.reportValidity(); return; }

    setState('loading');
    fetch('/api/gbp-check', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({company: company, city: city, keyword: keyword})
    })
      .then(function(res){
        return res.json().catch(function(){ return {}; }).then(function(data){ return {ok: res.ok, data: data}; });
      })
      .then(function(r){
        if(r.ok && r.data && r.data.success){
          renderResult(r.data);
          setState('result');
          fetchCompare({
            company: company, city: city, keyword: keyword,
            placeId: r.data.place_id, ownRating: r.data.rating, ownReviews: r.data.reviews
          });
        } else {
          showError(errorTextFor(r.data));
        }
      })
      .catch(function(){
        showError(DEFAULT_ERROR);
      });
  });

  // ---- Ausführliche Auswertung anfordern --------------------------------
  // Nach dem Check: Name und E-Mail genügen, die Check-Daten kommen aus der
  // Seite. Verschickt wird über /api/contact (Versandkette mit Ausgangskorb,
  // Mengenbegrenzung, Eingangsbestätigung) — nichts Neues auf dem Server.
  var reportForm = document.getElementById('reportForm');
  var reportBtn = document.getElementById('rrSubmit');
  var reportStatus = document.getElementById('reportStatus');
  var reportMeldung = function(art, html){
    reportStatus.className = 'form-status show form-status--' + art;
    reportStatus.innerHTML = html;
  };
  if(reportForm) reportForm.addEventListener('submit', function(ev){
    ev.preventDefault();
    var name = (document.getElementById('rr-name').value || '').trim();
    var email = (document.getElementById('rr-mail').value || '').trim();
    if(!name || !email || !reportForm.checkValidity()){ reportForm.reportValidity && reportForm.reportValidity(); return; }

    var wert = function(id){ var el = document.getElementById(id); return el ? (el.value || '').trim() : ''; };
    var zeilen = Array.prototype.map.call(checklist ? checklist.querySelectorAll('li') : [], function(li){
      var teile = Array.prototype.map.call(li.children, function(c){ return c.textContent.replace(/\s+/g, ' ').trim(); }).filter(Boolean);
      return '- ' + (teile.length > 1 ? teile.join(': ') : li.textContent.replace(/\s+/g, ' ').trim());
    });
    var vergleich = document.getElementById('gbpCompareSub');
    var nachricht = 'Ausführliche Auswertung angefordert.\n\n'
      + 'Firma: ' + wert('gbp-company') + '\nStadt: ' + wert('gbp-city') + '\nKeyword: ' + wert('gbp-keyword') + '\n'
      + 'Basis-Check: ' + (ringNum ? ringNum.textContent : '?') + ' von ' + TOTAL_FACTORS + '\n'
      + (zeilen.length ? '\n' + zeilen.join('\n') + '\n' : '')
      + (vergleich && vergleich.textContent ? '\nVergleich: ' + vergleich.textContent.trim() + '\n' : '');

    reportBtn.disabled = true;
    reportBtn.textContent = 'Wird gesendet …';
    fetch('/api/contact', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      body: JSON.stringify({
        name: name, email: email, message: nachricht,
        _gotcha: reportForm.querySelector('[name="_gotcha"]').value,
        _subject: 'Profil-Check: Auswertung angefordert'
      })
    })
      .then(function(res){ return res.json().catch(function(){ return {}; }).then(function(d){ return {ok: res.ok, data: d}; }); })
      .then(function(r){
        if(r.ok && r.data && r.data.success){
          reportForm.hidden = true;
          reportMeldung('ok', 'Danke! Die Auswertung kommt persönlich von mir an ' + email.replace(/</g, '&lt;')
            + ' — meist am selben Werktag. Eine kurze Bestätigung liegt gleich in Ihrem Postfach.');
          if(window.jlTrack) window.jlTrack('report_request');
          return;
        }
        var feld = r.data && r.data.fields ? r.data.fields[Object.keys(r.data.fields)[0]] : '';
        reportMeldung('err', feld || 'Das Senden hat leider nicht geklappt. Rufen Sie mich gern an: <a href="tel:+4917655769680">+49 176 55769680</a>.');
      })
      .catch(function(){
        reportMeldung('err', 'Die Verbindung kam nicht zustande. Rufen Sie mich gern an: <a href="tel:+4917655769680">+49 176 55769680</a>.');
      })
      .then(function(){ reportBtn.disabled = false; reportBtn.textContent = 'Auswertung anfordern'; });
  });

  Array.prototype.forEach.call(badge.querySelectorAll('[data-gbp-reset]'), function(btn){
    btn.addEventListener('click', function(){
      if(form) form.reset();
      setState('form');
      setCompareState('hidden');
    });
  });
})();

(function(){
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // year
  document.getElementById('year').textContent = new Date().getFullYear();

  // nav scrolled state
  var nav = document.getElementById('nav');
  // Die Browserleiste (Chrome auf Android, Safari-Statusleiste) nimmt die Farbe
  // der Leiste an: dunkel über dem Hero, weiss, sobald die Leiste hell wird.
  // Sonst stand über der weissen Leiste ein dunkelblauer Streifen.
  var themeMeta = document.querySelector('meta[name="theme-color"]');
  var themeNow = '';
  var syncTheme = function(){
    if(!themeMeta) return;
    var hell = nav.classList.contains('scrolled') || nav.classList.contains('open');
    var farbe = hell ? '#FFFFFF' : '#0A0D1F';
    if(farbe !== themeNow){ themeNow = farbe; themeMeta.setAttribute('content', farbe); }
  };
  var onScroll = function(){ nav.classList.toggle('scrolled', window.scrollY > 24); syncTheme(); };
  onScroll(); window.addEventListener('scroll', onScroll, {passive:true});

  // mobile menu — ein Blatt über die volle Höhe; solange es offen ist, steht
  // die Seite dahinter still (html.menu-open).
  var toggle = document.getElementById('navToggle');
  var menu = document.getElementById('mobileMenu');
  var setMenu = function(open){
    nav.classList.toggle('open', open);
    menu.classList.toggle('show', open);
    document.documentElement.classList.toggle('menu-open', open);
    toggle.setAttribute('aria-expanded', open);
    toggle.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
    syncTheme();
    if(open && window.jlTrack) window.jlTrack('menu_open');
  };
  toggle.addEventListener('click', function(){ setMenu(!nav.classList.contains('open')); });
  menu.querySelectorAll('a').forEach(function(a){
    a.addEventListener('click', function(){ setMenu(false); });
  });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && nav.classList.contains('open')){ setMenu(false); toggle.focus(); }
  });
  // Dreht jemand das Tablet ins Querformat, verschwindet der Menüknopf — ein
  // offenes Blatt samt Scroll-Sperre bliebe sonst ohne Ausgang stehen.
  var breit = window.matchMedia('(min-width: 961px)');
  var aufBreit = function(){ if(breit.matches && nav.classList.contains('open')) setMenu(false); };
  if(breit.addEventListener) breit.addEventListener('change', aufBreit); else if(breit.addListener) breit.addListener(aufBreit);

  // smooth anchor scroll — nur seiteninterne Ziele; versteht "#id" und "/#id"
  // (Footer-Sektionslinks nutzen "/#id", damit sie auch von Unterseiten aus funktionieren).
  document.querySelectorAll('a[href*="#"]').forEach(function(a){
    a.addEventListener('click', function(e){
      var href = a.getAttribute('href');
      var hi = href.indexOf('#');
      var hash = hi === 0 ? href : (hi > -1 ? href.slice(hi) : '');
      if(hash.length < 2) return;
      var samePage = hi === 0 || a.pathname === window.location.pathname;
      if(!samePage) return; // z. B. "/#vorteile" auf einer Unterseite: normal zur Startseite navigieren
      var el = document.querySelector(hash);
      if(!el) return;
      e.preventDefault();
      var y = el.getBoundingClientRect().top + window.scrollY - 72;
      window.scrollTo({top:y, behavior: reduce ? 'auto' : 'smooth'});
      if(history.replaceState) history.replaceState(null, '', hash);
    });
  });

  // ---- reveal on scroll ----------------------------------------------------
  // Der Beobachter allein reicht nicht. Bei schnellem Wischen darf der Browser
  // Zwischenzustände auslassen: Ein Element, das zwischen zwei Messungen
  // komplett durchs Bild rauscht, bekommt nie einen Rückruf — und weil
  // ".js [data-reveal]" auf opacity:0 steht, bleibt dann ein ganzer Abschnitt
  // dauerhaft unsichtbar auf der Seite stehen. Genau das war reproduzierbar:
  // "Ein aktueller Kunde", der Vorher/Nachher-Kopf und der vierte Schritt
  // fehlten nach einem schnellen Durchscrollen komplett.
  //
  // Der vorhandene reveal-off-Failsafe (weiter unten) greift dagegen nicht: Er
  // prüft nur, ob ÜBERHAUPT etwas sichtbar wurde. Sobald ein Teil der Elemente
  // normal aufgetaucht ist, hält er die Lage für in Ordnung.
  //
  // Deshalb: Beobachter wie bisher als Auslöser für die Choreografie, plus ein
  // Nachlauf, der alles einsammelt, was die Auslöselinie schon überschritten
  // hat. Der Nachlauf kostet nichts pro Bild — er läuft nur, wenn ohnehin ein
  // Rückruf kommt, und einmal kurz nachdem das Scrollen zur Ruhe kommt.
  var offen = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));
  if(offen.length){
    var LINIE = 50;   // identisch zum rootMargin unten
    var zeigen = function(el){
      var i = offen.indexOf(el);
      if(i < 0) return;
      offen.splice(i, 1);
      el.classList.add('in');
    };
    var nachlauf = function(){
      var grenze = window.innerHeight - LINIE;
      for(var i = offen.length - 1; i >= 0; i--){
        if(offen[i].getBoundingClientRect().top < grenze) zeigen(offen[i]);
      }
      if(!offen.length){
        window.removeEventListener('scroll', angestossen);
        window.removeEventListener('resize', angestossen);
      }
    };
    var ruheTimer = null;
    var angestossen = function(){
      if(ruheTimer) clearTimeout(ruheTimer);
      ruheTimer = setTimeout(nachlauf, 140);
    };
    if('IntersectionObserver' in window){
      var io = new IntersectionObserver(function(entries){
        entries.forEach(function(e){ if(e.isIntersecting){ io.unobserve(e.target); zeigen(e.target); } });
        nachlauf();
      }, {threshold:.14, rootMargin:'0px 0px -' + LINIE + 'px 0px'});
      offen.slice().forEach(function(el){ io.observe(el); });
      window.addEventListener('scroll', angestossen, {passive:true});
      window.addEventListener('resize', angestossen, {passive:true});
      nachlauf();
    } else {
      offen.slice().forEach(zeigen);
    }
  }

  // Hero-Einstieg: laeuft vollstaendig ueber CSS-Keyframes (site.css,
  // "Hero-Einstieg") und startet mit dem ersten Bild. Frueher tippte hier ein
  // Schreibmaschinen-Effekt die Ueberschrift Zeichen fuer Zeichen — das
  // schrieb 30 Mal ins DOM, brach die Unterschneidung zwischen den Buchstaben
  // und liess die zweite Zeile beim Umschalten auf den Schimmer sichtbar
  // springen.

  // stat count-up — Progressive Enhancement: Der Endwert steht im HTML und
  // BLEIBT dort stehen, bis die Zahl wirklich ins Bild kommt. Erst im Moment
  // des Hochzählens springt sie kurz auf 0. Früher wurde schon beim Laden
  // genullt — wer nicht scrollte (Suchmaschinen, Analyse-Werkzeuge,
  // Vorschaubilder), sah dauerhaft "0 %" und "0,0 ×".
  // Bei "Bewegung reduzieren" läuft gar nichts, der Endwert bleibt stehen.
  var counts = document.querySelectorAll('.count');
  if(counts.length && !reduce && 'IntersectionObserver' in window){
    var fmtCount = function(v, dec){ return dec ? v.toFixed(dec).replace('.', ',') : String(Math.round(v)); };
    var runCount = function(el){
      // Guard: verhindert einen zweiten Lauf, falls derselbe Trigger (oder ein
      // künftiger zweiter Observer) das Element ein zweites Mal anstößt —
      // zwei parallele rAF-Loops auf demselben Element würden sich beim
      // Schreiben von textContent gegenseitig überschreiben.
      if(el.__countStarted) return;
      el.__countStarted = true;
      var target = parseFloat(el.getAttribute('data-count')) || 0;
      var dec = parseInt(el.getAttribute('data-dec'), 10) || 0;
      el.textContent = fmtCount(0, dec);
      var start = null, dur = 1400, done = false;
      var finish = function(){
        if(done) return;
        done = true;
        el.textContent = fmtCount(target, dec);
      };
      var tick = function(ts){
        if(done) return;
        if(!start) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        el.textContent = fmtCount((1 - Math.pow(1 - p, 3)) * target, dec);
        if(p < 1) requestAnimationFrame(tick);
        else finish();
      };
      requestAnimationFrame(tick);
      // Sicherheitsnetz: setzt den exakten Endwert unabhängig vom rAF-Timing
      // hart fest (z. B. falls Tab-Wechsel, Drosselung o. Ä. die Loop
      // unterbricht), statt dass die Zahl auf einem Zwischenwert einfriert.
      setTimeout(finish, dur + 400);
    };
    // threshold:.5 verlangte 50% Sichtbarkeit jedes einzelnen .count-Elements.
    // Auf Mobile stapelt .stats__grid einspaltig (mehr Gesamthöhe) — bei
    // normalen Scroll-Stopps blieb dadurch v. a. der letzte Wert oft unter
    // der Schwelle hängen und zählte nie hoch. Gleiche, bereits bewährte
    // Trigger-Logik wie beim allgemeinen Reveal-Observer: niedrigere
    // Schwelle + rootMargin statt harter Pixelwerte.
    //
    // Plus derselbe Nachlauf wie beim Reveal und den Live-Icons: Der Browser
    // darf Zwischenzustände bei schnellem Wischen auslassen. Ohne Nachlauf
    // bliebe eine übersprungene Kennzahl einfach unanimiert stehen (mit dem
    // richtigen Endwert, aber ohne das Hochzählen).
    var countsOffen = Array.prototype.slice.call(counts);
    var countsNachlauf = function(){
      var grenze = window.innerHeight - 50;
      for(var i = countsOffen.length - 1; i >= 0; i--){
        if(countsOffen[i].getBoundingClientRect().top < grenze){
          cio.unobserve(countsOffen[i]);
          runCount(countsOffen[i]);
          countsOffen.splice(i, 1);
        }
      }
      if(!countsOffen.length) window.removeEventListener('scroll', countsAngestossen);
    };
    var countsRuheTimer = null;
    var countsAngestossen = function(){
      if(countsRuheTimer) clearTimeout(countsRuheTimer);
      countsRuheTimer = setTimeout(countsNachlauf, 140);
    };
    var cio = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if(!e.isIntersecting) return;
        cio.unobserve(e.target);
        var i = countsOffen.indexOf(e.target);
        if(i > -1) countsOffen.splice(i, 1);
        runCount(e.target);
      });
      countsNachlauf();
    }, {threshold:.14, rootMargin:'0px 0px -50px 0px'});
    counts.forEach(function(el){
      // Breite des Endwerts festhalten, bevor auf 0 gesetzt wird: Beim
      // Hochzaehlen waechst die Zahl sonst von einer auf zwei Stellen und
      // schiebt die Einheit dahinter mit (Layout-Verschiebung).
      el.style.display = 'inline-block';
      el.style.textAlign = 'right';
      el.style.minWidth = Math.ceil(el.getBoundingClientRect().width) + 'px';
      cio.observe(el);
    });
    window.addEventListener('scroll', countsAngestossen, {passive:true});
    countsNachlauf();
  }

  // hero parallax + rankcard tilt (fine pointer only)
  var finePointer = window.matchMedia('(hover:hover) and (pointer:fine)').matches;
  if(finePointer && !reduce){
    var glowA = document.querySelector('.glow--a');
    var glowB = document.querySelector('.glow--b');
    var card = document.getElementById('rankcard');
    var tx=0,ty=0, raf=null;
    var apply = function(){
      if(glowA) glowA.style.transform = 'translate3d('+(tx*18).toFixed(1)+'px,'+(ty*18).toFixed(1)+'px,0)';
      if(glowB) glowB.style.transform = 'translate3d('+(tx*-12).toFixed(1)+'px,'+(ty*-12).toFixed(1)+'px,0)';
      if(card){ card.style.transform = 'rotateY('+(tx*5).toFixed(2)+'deg) rotateX('+(-ty*5).toFixed(2)+'deg)'; }
      raf=null;
    };
    window.addEventListener('mousemove', function(e){
      tx = (e.clientX/window.innerWidth - .5)*2;
      ty = (e.clientY/window.innerHeight - .5)*2;
      if(!raf) raf = requestAnimationFrame(apply);
    }, {passive:true});
  }

  // premium pointer micro-interactions (fine pointer + motion ok)
  if(finePointer && !reduce){
    // magnetic primary buttons — schwächerer Zug (0.22/0.30 -> 0.1/0.13) und
    // per Lerp sanft nachgeführt statt den Button beim ersten Mousemove
    // sofort auf den vollen Zielwert zu springen (das wirkte "hingezogen").
    document.querySelectorAll('.btn--primary').forEach(function(btn){
      var tx=0, ty=0, cx=0, cy=0, running=false;
      var loop = function(){
        cx += (tx-cx)*0.16; cy += (ty-cy)*0.16;
        btn.style.transform = 'translate('+cx.toFixed(2)+'px,'+(cy-2).toFixed(2)+'px)';
        if(Math.abs(tx-cx) > 0.05 || Math.abs(ty-cy) > 0.05){
          requestAnimationFrame(loop);
        } else {
          running = false;
        }
      };
      btn.addEventListener('mousemove', function(e){
        var r = btn.getBoundingClientRect();
        tx = (e.clientX-(r.left+r.width/2))*0.1;
        ty = (e.clientY-(r.top+r.height/2))*0.13;
        if(!running){ running = true; requestAnimationFrame(loop); }
      }, {passive:true});
      btn.addEventListener('mouseleave', function(){
        tx = 0; ty = 0;
        if(!running){ running = true; requestAnimationFrame(loop); }
      });
    });
  }

  // Aufstieg auf Platz 1, Platzziffern und Mitteilungen: reine CSS-Keyframes
  // mit festen Startzeiten (site.css, "Hero-Einstieg"). Kein Timer mehr, der
  // sich mit dem Einblenden der Karte ueberschneiden konnte.

  // Custom-Cursor: Punkt + nachlaufender Ring (lerp), Zustände je nach Ziel.
  // Nur auf Geräten mit feinem Zeiger und ohne reduced motion — auf Touch
  // existiert er gar nicht (keine DOM-Knoten, keine Listener).
  if(finePointer && !reduce){
    document.documentElement.classList.add('has-cursor');
    var curDot = document.createElement('div');
    curDot.className = 'cur-dot';
    var curRing = document.createElement('div');
    curRing.className = 'cur-ring';
    curRing.innerHTML = '<span class="cur-ring__c"></span><span class="cur-ring__label"></span>' +
      '<svg class="cur-ring__drag" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 7l-5 5 5 5M16 7l5 5-5 5"/></svg>';
    curDot.setAttribute('aria-hidden', 'true');
    curRing.setAttribute('aria-hidden', 'true');
    curRing.setAttribute('data-cursor', 'elastic');
    // Ring vor dem Punkt einhängen: das CSS blendet den Punkt über den
    // Folge-Geschwister-Selektor aus, wenn der Ring ein Label/Griff zeigt.
    document.body.appendChild(curRing);
    document.body.appendChild(curDot);
    var curLabel = curRing.querySelector('.cur-ring__label');
    var cx = -100, cy = -100, rx = -100, ry = -100, curSeen = false, curLoopRunning = false;
    // Elastic-Dehnung: der Ring hinkt der Zielposition per Lerp hinterher —
    // der dabei entstehende Rückstand (dx/dy) ist proportional zur
    // Bewegungsgeschwindigkeit und liefert Länge + Richtung der Dehnung.
    // Rotate → stretchen → zurückrotieren dehnt exakt entlang der
    // Bewegungsrichtung, unabhängig vom Winkel (klassischer Gummiband-Trick).
    var curLoop = function(){
      var dx = cx - rx, dy = cy - ry;
      rx += dx * 0.15; ry += dy * 0.15;
      curDot.style.transform = 'translate3d(' + cx + 'px,' + cy + 'px,0)';
      var state = curRing.getAttribute('data-state');
      var stretchOk = state !== 'pin' && state !== 'drag' && state !== 'view';
      var stretchTf = '';
      if(stretchOk){
        var dist = Math.sqrt(dx * dx + dy * dy);
        var stretch = Math.min(1 + dist * 0.012, 1.3);
        if(stretch > 1.01){
          var angle = Math.atan2(dy, dx) * 180 / Math.PI;
          var squeeze = 1 / Math.sqrt(stretch);
          stretchTf = ' rotate(' + angle.toFixed(1) + 'deg) scale(' + stretch.toFixed(3) + ',' + squeeze.toFixed(3) + ') rotate(' + (-angle).toFixed(1) + 'deg)';
        }
      }
      curRing.style.transform = 'translate3d(' + rx.toFixed(1) + 'px,' + ry.toFixed(1) + 'px,0)' + stretchTf;
      // Hat der Ring aufgeholt, ruht die Schleife bis zur nächsten Bewegung —
      // vorher lief sie nach der ersten Mausbewegung dauerhaft in jedem Bild.
      if(Math.abs(cx - rx) < 0.3 && Math.abs(cy - ry) < 0.3){ curLoopRunning = false; return; }
      requestAnimationFrame(curLoop);
    };
    // Loop erst starten, wenn sich die Maus tatsächlich bewegt hat — sonst
    // läuft die Animation (rAF + Style-Writes) schon während des Seitenladens
    // dauerhaft mit, ganz ohne dass ein Cursor je sichtbar ist.
    window.addEventListener('mousemove', function(e){
      cx = e.clientX; cy = e.clientY;
      if(!curSeen){
        curSeen = true; rx = cx; ry = cy;
        document.documentElement.classList.add('cursor-seen');
      }
      if(!curLoopRunning){ curLoopRunning = true; curLoop(); }
    }, {passive:true});
    var setCurState = function(state, labelText){
      document.documentElement.classList.toggle('cursor-off', state === 'off');
      curRing.setAttribute('data-state', state);
      curLabel.textContent = labelText || '';
    };
    document.addEventListener('mouseover', function(e){
      var t = e.target;
      if(!(t instanceof Element)) return;
      if(t.closest('input,textarea,select,iframe')){ setCurState('off'); return; }
      if(t.closest('.vnc__stage')){ setCurState('drag'); return; }
      if(t.closest('.related__list a')){ setCurState('view', 'Ansehen'); return; }
      var faqQ = t.closest('.faq__q');
      if(faqQ){ setCurState('view', faqQ.getAttribute('aria-expanded') === 'true' ? 'Schließen' : 'Öffnen'); return; }
      var crow = t.closest('.crow');
      if(crow){ setCurState('view', (crow.getAttribute('href') || '').indexOf('tel:') === 0 ? 'Anrufen' : 'Schreiben'); return; }
      if(t.closest('.btn--primary')){ setCurState('pin'); return; }
      if(t.closest('a,button,[role="button"]')){ setCurState('grow'); return; }
      setCurState('idle');
    });
    document.addEventListener('mouseleave', function(){ document.documentElement.classList.add('cursor-off'); });
    document.addEventListener('mouseenter', function(){ document.documentElement.classList.remove('cursor-off'); });
  }

  // Scroll-Parallax für die Kapitel: Ebenen mit data-pd bewegen sich beim
  // Scrollen unterschiedlich schnell. Gemessen wird der untransformierte
  // Kapitel-Container (kein Feedback über die eigene Transformation),
  // geschrieben wird nur transform, gedrosselt per requestAnimationFrame.
  // Nur auf Geräten mit feinem Zeiger (Desktop): auf Touch-Geräten wäre
  // der Scroll-Handler überflüssige Arbeit ohne sichtbaren Effekt.
  var chapterEls = Array.prototype.slice.call(document.querySelectorAll('.chapter'));
  if(chapterEls.length && !reduce && finePointer){
    var chapters = chapterEls.map(function(ch){
      return {root: ch, layers: Array.prototype.slice.call(ch.querySelectorAll('[data-pd]')).map(function(el){
        return {el: el, depth: parseFloat(el.getAttribute('data-pd')) || 0};
      })};
    });
    var pRaf = null;
    var applyParallax = function(){
      pRaf = null;
      var vh = window.innerHeight;
      chapters.forEach(function(ch){
        var r = ch.root.getBoundingClientRect();
        if(r.bottom < -160 || r.top > vh + 160) return;
        var c = r.top + r.height / 2 - vh / 2;
        ch.layers.forEach(function(l){
          var y = c * l.depth;
          l.el.style.transform = (l.el.classList.contains('chapter__glow') ? 'translateY(-50%) ' : '') +
            'translate3d(0,' + y.toFixed(1) + 'px,0)';
        });
      });
    };
    var queueParallax = function(){ if(!pRaf) pRaf = requestAnimationFrame(applyParallax); };
    window.addEventListener('scroll', queueParallax, {passive:true});
    window.addEventListener('resize', queueParallax, {passive:true});
    queueParallax();
  }

  // Sitewide Soft-Aurora-Hintergrund (partials/endbody.html): die weichen,
  // geblurrten Flächen atmen unabhängig per CSS-Keyframes (siehe site.css)
  // UND parallaxen zusätzlich beim Scrollen unterschiedlich schnell
  // (data-speed) — eigene Transform-Ebene pro Blob, damit sich beide
  // Bewegungen nicht gegenseitig überschreiben. Gleiches rAF-Drossel-Muster
  // wie der Kapitel-Parallax oben, unabhängig davon.
  // Parallax nur auf Desktop: auf Mobile ist Aurora per CSS ausgeblendet
  // (display:none), finePointer verhindert unnötige JS-Arbeit.
  var bgAurora = document.getElementById('bgAurora');
  if(bgAurora && !reduce && finePointer && document.body.getAttribute('data-bgfx') !== 'off'){
    var auroraLayers = Array.prototype.slice.call(bgAurora.querySelectorAll('[data-speed]')).map(function(el){
      return {el: el, speed: parseFloat(el.getAttribute('data-speed')) || 0};
    });
    var auroraRaf = null;
    var applyAurora = function(){
      auroraRaf = null;
      var y = window.scrollY;
      auroraLayers.forEach(function(l){ l.el.style.transform = 'translate3d(0,' + (y * l.speed).toFixed(1) + 'px,0)'; });
    };
    var queueAurora = function(){ if(!auroraRaf) auroraRaf = requestAnimationFrame(applyAurora); };
    window.addEventListener('scroll', queueAurora, {passive:true});
    queueAurora();
  }

  // Vorher-Nachher-Slider (Apple-Design): eine Pointer-Logik für Maus &
  // Touch, zusätzlich per Pfeiltasten bedienbar (role="slider"). Der Griff
  // bekommt zusätzlich einen kurzen Scale-Ausschlag waehrend des Ziehens.
  // Seit dem Webdesign-Zweig gibt es zwei dieser Slider (Startseite: Google-
  // Ergebnisse, /webdesign/: alte gegen neue Website). Deshalb pro .vnc__stage
  // eine eigene, in sich geschlossene Instanz statt der frueheren festen
  // Bindung an die IDs #vncStage/#vncGrip.
  Array.prototype.forEach.call(document.querySelectorAll('.vnc__stage'), function(vncStage){
    var vncGrip = vncStage.querySelector('.vnc__grip');
    var vncPos = 50;
    var vncSet = function(p){
      vncPos = Math.max(0, Math.min(100, p));
      vncStage.style.setProperty('--pos', vncPos + '%');
      vncStage.setAttribute('aria-valuenow', String(Math.round(vncPos)));
      vncStage.setAttribute('aria-valuetext', 'Regler bei ' + Math.round(vncPos) + ' %');
    };
    vncSet(50);
    var vncFromEvent = function(e){
      var r = vncStage.getBoundingClientRect();
      return ((e.clientX - r.left) / r.width) * 100;
    };
    var vncDrag = false, vncRaf = null, vncNext = 50;
    var vncQueue = function(p){
      vncNext = p;
      if(!vncRaf) vncRaf = requestAnimationFrame(function(){ vncSet(vncNext); vncRaf = null; });
    };
    vncStage.addEventListener('pointerdown', function(e){
      vncDrag = true;
      if(vncStage.setPointerCapture){ try{ vncStage.setPointerCapture(e.pointerId); }catch(_){} }
      if(vncGrip) vncGrip.classList.add('is-dragging');
      vncQueue(vncFromEvent(e));
      e.preventDefault();
    });
    vncStage.addEventListener('pointermove', function(e){ if(vncDrag) vncQueue(vncFromEvent(e)); });
    var vncEnd = function(){
      vncDrag = false;
      if(vncGrip) vncGrip.classList.remove('is-dragging');
    };
    vncStage.addEventListener('pointerup', vncEnd);
    vncStage.addEventListener('pointercancel', vncEnd);
    vncStage.addEventListener('keydown', function(e){
      if(e.key === 'ArrowLeft' || e.key === 'ArrowDown'){ vncSet(vncPos - 5); e.preventDefault(); }
      else if(e.key === 'ArrowRight' || e.key === 'ArrowUp'){ vncSet(vncPos + 5); e.preventDefault(); }
      else if(e.key === 'Home'){ vncSet(0); e.preventDefault(); }
      else if(e.key === 'End'){ vncSet(100); e.preventDefault(); }
    });
    // Beim ersten Sichtbarwerden schwingt der Griff einmal gedaempft aus,
    // damit klar ist, dass man ziehen kann. Danach hat der Nutzer die
    // Kontrolle.
    if(!reduce && 'IntersectionObserver' in window){
      var vncHinted = false;
      var vncIo = new IntersectionObserver(function(entries){
        entries.forEach(function(en){
          if(!en.isIntersecting || vncHinted) return;
          vncHinted = true; vncIo.disconnect();
          setTimeout(function(){
            var t0 = null, dur = 1700;
            var swing = function(ts){
              if(vncDrag) return;
              if(!t0) t0 = ts;
              var p = Math.min((ts - t0) / dur, 1);
              var e = 1 - Math.pow(1 - p, 3);
              vncSet(50 + Math.sin(e * Math.PI * 2) * 16 * (1 - e));
              if(p < 1) requestAnimationFrame(swing);
            };
            requestAnimationFrame(swing);
          }, 1100);
        });
      }, {threshold:.55});
      vncIo.observe(vncStage);
    }
  });

  // Punkt-Indikatoren für die swipebare Baustein-Reihe (nur Mobile sichtbar)
  var svcRow = document.querySelector('.services__grid');
  var svcDots = document.getElementById('svcDots');
  if(svcRow && svcDots){
    var svcCards = svcRow.querySelectorAll('.svc');
    svcCards.forEach(function(){ svcDots.appendChild(document.createElement('i')); });
    var dotEls = svcDots.children;
    var svcRaf = null;
    var svcUpdate = function(){
      svcRaf = null;
      var mid = svcRow.scrollLeft + svcRow.clientWidth / 2;
      var best = 0, bestDist = Infinity;
      svcCards.forEach(function(card, i){
        var d = Math.abs(card.offsetLeft + card.offsetWidth / 2 - mid);
        if(d < bestDist){ bestDist = d; best = i; }
      });
      for(var i = 0; i < dotEls.length; i++) dotEls[i].classList.toggle('on', i === best);
    };
    svcRow.addEventListener('scroll', function(){ if(!svcRaf) svcRaf = requestAnimationFrame(svcUpdate); }, {passive:true});
    svcUpdate();
  }

  // FAQ accordion — reines Klassen-Toggle, die Höhe übernimmt CSS
  // (grid-template-rows 0fr/1fr auf .faq__a, siehe site.css). Kein
  // scrollHeight-Messen mehr nötig, das vor der Animation ohnehin einen
  // synchronen Layout-Flush erzwungen hätte.
  document.querySelectorAll('.faq__item').forEach(function(item){
    var q = item.querySelector('.faq__q');
    q.addEventListener('click', function(){
      var open = item.classList.contains('open');
      // close siblings
      document.querySelectorAll('.faq__item.open').forEach(function(other){
        if(other!==item){ other.classList.remove('open'); other.querySelector('.faq__q').setAttribute('aria-expanded','false'); }
      });
      item.classList.toggle('open', !open);
      q.setAttribute('aria-expanded', String(!open));
    });
  });

  // Navigationsleiste: aktuelle Seite und gleitende Pillen.
  // Unterseiten werden über den Pfad erkannt ("Über mich", "Kontakt", mit
  // data-aktiv auch ganze Bereiche wie /branchen/). Auf der Startseite eines
  // Zweigs zeigen Einträge wie "/webdesign/#preise" auf Abschnitte derselben
  // Seite. Dort wandert die gefüllte Pille beim Scrollen mit dem Abschnitt,
  // in dem man gerade liest. Die blasse Pille folgt Maus und Tastaturfokus.
  var navLinks = document.querySelector('.nav__links');
  var navBrand = document.querySelector('.nav .brand');
  if(navLinks && navBrand){
    var eintraege = Array.prototype.slice.call(navLinks.querySelectorAll('a'));
    var mobilEintraege = menu ? Array.prototype.slice.call(menu.querySelectorAll(':scope > a:not(.btn)')) : [];
    var zweigStart = navBrand.getAttribute('href');
    var hier = window.location.pathname.replace(/index\.html$/, '');
    var seitenTreffer = -1;
    var abschnitte = [];
    eintraege.forEach(function(a, i){
      var url = new URL(a.getAttribute('href'), window.location.href);
      var basis = a.getAttribute('data-aktiv') || url.pathname;
      if(url.hash && url.pathname === hier){
        var el = document.getElementById(url.hash.slice(1));
        if(el) abschnitte.push({i:i, el:el});
      } else if(basis !== zweigStart && hier.indexOf(basis) === 0){
        seitenTreffer = i;
      }
    });

    var pille = function(art){
      var p = document.createElement('span');
      p.className = 'nav__glide nav__glide--' + art;
      p.setAttribute('aria-hidden', 'true');
      navLinks.insertBefore(p, navLinks.firstChild);
      return p;
    };
    // Reihenfolge im DOM: die blasse Pille zuerst, die gefüllte liegt darüber.
    var aktivPille = pille('active');
    var hoverPille = pille('hover');
    navLinks.classList.add('has-glide');

    var setze = function(p, a, sofort){
      if(!a || !a.offsetWidth){ p.classList.remove('is-on'); return; }
      // Taucht die Pille neu auf, springt sie an ihren Platz und blendet dort
      // ein, statt vom linken Rand herüberzufliegen.
      var springen = sofort || !p.classList.contains('is-on');
      if(springen) p.classList.add('no-anim');
      p.style.setProperty('--x', a.offsetLeft + 'px');
      p.style.setProperty('--w', a.offsetWidth + 'px');
      if(springen){ void p.offsetWidth; p.classList.remove('no-anim'); }
      p.classList.add('is-on');
    };

    var aktiv = -2;
    var markiere = function(i, sofort){
      if(i === aktiv && !sofort) return;
      aktiv = i;
      eintraege.forEach(function(a, k){
        if(k === i) a.setAttribute('aria-current', seitenTreffer === i ? 'page' : 'true');
        else a.removeAttribute('aria-current');
      });
      mobilEintraege.forEach(function(a, k){ a.classList.toggle('active', k === i); });
      setze(aktivPille, eintraege[i], sofort);
    };

    var abschnittJetzt = function(){
      var linie = window.innerHeight * 0.4, treffer = -1;
      abschnitte.forEach(function(s){
        var r = s.el.getBoundingClientRect();
        if(r.top <= linie && r.bottom > linie) treffer = s.i;
      });
      return treffer;
    };
    var aktualisiere = function(sofort){
      markiere(seitenTreffer > -1 ? seitenTreffer : abschnittJetzt(), sofort);
    };
    aktualisiere(true);

    if(seitenTreffer < 0 && abschnitte.length){
      var spyWartet = false;
      window.addEventListener('scroll', function(){
        if(spyWartet) return;
        spyWartet = true;
        requestAnimationFrame(function(){ spyWartet = false; aktualisiere(false); });
      }, {passive:true});
    }

    eintraege.forEach(function(a){
      a.addEventListener('mouseenter', function(){ setze(hoverPille, a); });
      a.addEventListener('focus', function(){ setze(hoverPille, a); });
    });
    navLinks.addEventListener('mouseleave', function(){ setze(hoverPille, null); });
    navLinks.addEventListener('focusout', function(e){
      if(!navLinks.contains(e.relatedTarget)) setze(hoverPille, null);
    });

    // Breite und Lage der Einträge ändern sich, wenn die Schrift nachlädt oder
    // das Fenster seine Größe ändert.
    var neuVermessen = function(){ aktualisiere(true); };
    window.addEventListener('resize', neuVermessen);
    if(document.fonts && document.fonts.ready) document.fonts.ready.then(neuVermessen);
  }

  // Sticky Mobile-CTA: nach dem Hero zeigen, im Kontaktbereich und im
  // Footer ausblenden (sonst verdeckt die fixe Leiste am Seitenende
  // dauerhaft die letzte Footer-Zeile, ohne dass man daran vorbeiscrollen kann).
  var mcta = document.getElementById('mcta');
  if(mcta){
    // Startseite: #kontakt, Webdesign-Seite: #termin. Dort steht der Kalender
    // bereits im Bild — die Leiste wäre eine zweite Aufforderung darüber.
    var kontakt = document.getElementById('kontakt') || document.getElementById('termin');
    var footerEl = document.querySelector('.footer');
    var kontaktVisible = false, footerVisible = false;
    var updateMcta = function(){
      mcta.classList.toggle('show', window.scrollY > 640 && !kontaktVisible && !footerVisible);
    };
    if(kontakt){
      var kio = new IntersectionObserver(function(entries){
        entries.forEach(function(e){ kontaktVisible = e.isIntersecting; updateMcta(); });
      }, {rootMargin:'0px 0px -20% 0px'});
      kio.observe(kontakt);
    }
    if(footerEl){
      var fio = new IntersectionObserver(function(entries){
        entries.forEach(function(e){ footerVisible = e.isIntersecting; updateMcta(); });
      }, {rootMargin:'0px'});
      fio.observe(footerEl);
    }
    window.addEventListener('scroll', updateMcta, {passive:true});
    updateMcta();
  }

  // ===================================================================
  // Kontaktformular — eigener Endpunkt statt eines fremden Dienstes.
  //
  // Vorher lief der Versand über Formspree. Er scheiterte, und zwar
  // unheilbar von hier aus: Formspree beantwortet ein nicht bestätigtes,
  // gesperrtes oder aufgebrauchtes Formular mit einem Fehler, und die
  // Website kann nicht unterscheiden, welcher Fall vorliegt. Der Besucher
  // sah nur die rote Zeile — jede Anfrage, die dort verloren ging, war ein
  // verlorener Kunde.
  //
  // /api/contact verschickt über denselben Weg wie die Terminbestätigungen,
  // mit Ausgangskorb und Wiederholung. Der Endpunkt sagt außerdem, WAS nicht
  // stimmte; deshalb kann diese Stelle jetzt das betroffene Feld markieren,
  // statt pauschal "hat nicht geklappt" zu melden.
  // ===================================================================
  var FORM_ENDPOINT = '/api/contact';
  var form = document.getElementById('contactForm');
  var submitBtn = document.getElementById('cfSubmit');
  var statusEl = document.getElementById('formStatus');

  // Der Weg, der immer bleibt, wenn der Versand klemmt. Steht an einer
  // Stelle, damit die Nummer nicht in drei Fehlermeldungen auseinanderläuft.
  var FALLBACK = 'Rufen Sie mich an: <a href="tel:+4917655769680">+49 176 55769680</a>'
    + ' — oder schreiben Sie an <a href="mailto:Info@jungline.de">Info@jungline.de</a>.';

  // Feldname im Formular → id des Eingabefeldes, damit eine Rückmeldung des
  // Servers am richtigen Feld landet.
  var FIELD_IDS = {name:'cf-name', email:'cf-mail', phone:'cf-phone', message:'cf-msg'};

  function showStatus(kind, html){
    if(!statusEl) return;
    statusEl.className = 'form-status show form-status--' + kind;
    statusEl.innerHTML = html;
  }

  function clearStatus(){
    if(!statusEl) return;
    statusEl.className = 'form-status';
    statusEl.innerHTML = '';
  }

  if(form) form.addEventListener('submit', function(ev){
    ev.preventDefault();

    // FormData statt einzelner getElementById-Aufrufe: So kommen Honigtopf
    // (_gotcha) und Herkunft (_subject) automatisch mit, und ein zusätzliches
    // Feld im Markup funktioniert ohne Änderung hier.
    var payload = {};
    new FormData(form).forEach(function(value, key){ payload[key] = value; });

    var pflicht = ['name', 'email', 'message'];
    for(var i = 0; i < pflicht.length; i++){
      if(!(payload[pflicht[i]] || '').trim()){
        if(form.reportValidity) form.reportValidity();
        return;
      }
    }

    clearStatus();
    submitBtn.disabled = true;
    submitBtn.textContent = 'Wird gesendet …';

    fetch(FORM_ENDPOINT, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      body: JSON.stringify(payload)
    })
      .then(function(res){
        // Auch eine Fehlerantwort trägt einen verwertbaren Grund im Rumpf;
        // sie darf deshalb nicht vor dem Auslesen zur Ausnahme werden.
        return res.json()
          .catch(function(){ return {}; })
          .then(function(data){ return {ok: res.ok, data: data}; });
      })
      .then(function(antwort){
        var data = antwort.data || {};

        if(antwort.ok && data.success){
          form.reset();
          showStatus('ok', 'Danke, Ihre Nachricht ist angekommen — ich antworte werktags innerhalb'
            + ' von 24 Stunden. Eine kurze Bestätigung liegt gleich in Ihrem Postfach.');
          return;
        }

        if(data.error === 'validation_failed' && data.fields){
          var namen = Object.keys(data.fields);
          showStatus('err', data.fields[namen[0]]);
          var feld = document.getElementById(FIELD_IDS[namen[0]]);
          if(feld) feld.focus();
          return;
        }

        if(data.error === 'rate_limited'){
          showStatus('err', 'Es sind gerade sehr viele Nachrichten von Ihrem Anschluss gekommen.'
            + ' Bitte versuchen Sie es in einer Stunde noch einmal — oder direkt: ' + FALLBACK);
          return;
        }

        showStatus('err', 'Das Senden hat leider nicht geklappt. ' + FALLBACK);
      })
      .catch(function(){
        // Hier landet nur, wer gar keine Verbindung bekommen hat.
        showStatus('err', 'Die Verbindung kam nicht zustande. ' + FALLBACK);
      })
      .then(function(){
        submitBtn.disabled = false;
        submitBtn.textContent = 'Nachricht senden';
      });
  });

  // Impressum & Datenschutz sind jetzt echte, crawlbare Seiten (/impressum, /datenschutz) —
  // die früheren Rechtstext-Modals entfallen ersatzlos.
})();

// Scroll-linked sequential sweep animation for step numbers 01–04
(function(){
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var nums = Array.prototype.slice.call(document.querySelectorAll('.chapter__num'));
  if(!nums.length || reduce) return;

  // Track scroll velocity (px/ms) to set animation duration
  var scrollVel = 0;
  var lastY = window.scrollY, lastT = Date.now();
  window.addEventListener('scroll', function(){
    var now = Date.now(), dt = now - lastT;
    if(dt > 0) scrollVel = Math.abs(window.scrollY - lastY) / dt;
    lastY = window.scrollY; lastT = now;
  }, {passive: true});

  // nextIdx: which number should animate next (ensures strict ordering)
  var nextIdx = 0;

  var lightUp = function(idx, vel){
    if(idx >= nums.length) return;
    var el = nums[idx];
    // Faster scroll → shorter sweep (clamped 2.5s – 6.0s)
    var dur = Math.max(2.5, Math.min(6.0, 3.0 / Math.max(vel, 0.04)));
    el.style.setProperty('--chnum-dur', dur.toFixed(2) + 's');
    el.classList.add('lit');
    nextIdx = idx + 1;
  };

  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(!e.isIntersecting) return;
      var idx = nums.indexOf(e.target);
      if(idx < 0 || idx < nextIdx) return;
      io.unobserve(e.target);

      // Any numbers that were skipped over (fast scroll) light up at minimum speed
      for(var i = nextIdx; i < idx; i++){
        nums[i].style.setProperty('--chnum-dur', '2.5s');
        nums[i].classList.add('lit');
        nextIdx = i + 1;
      }

      // Animate the visible number with scroll-speed-linked duration
      lightUp(idx, scrollVel);
    });
  }, {threshold: 0.2, rootMargin: '0px 0px -60px 0px'});

  nums.forEach(function(el){ io.observe(el); });
})();
/* ============================================================
   LIVE-ICON-ENGINE
   ============================================================
   Ein Durchlauf für alle Strich-Icons der Seite (alle nutzen dieselbe
   24er-Box): jedes Icon zeichnet sich beim ersten Sichtkontakt selbst und
   zeichnet erneut, wenn sein interaktiver Träger Hover oder Fokus bekommt.

   Warum hier und nicht als Attribut im Markup: die Icons stehen verteilt in
   23 HTML-Seiten, in vite.config.js (Branchen-Chips) und in
   src/data/bausteine.js. Eine zentrale Stelle, die sie zur Laufzeit
   einsammelt und vermisst, hält alle Seiten automatisch synchron — neue
   Icons machen ohne Zusatzarbeit mit.

   Sicherheitsnetz: versteckt wird ein Icon nur, wenn dieses Skript es aktiv
   markiert. Fehlt IntersectionObserver oder springt er nicht an, bleibt der
   sichtbare Grundzustand stehen (siehe Failsafe unten). */
(function(){
  if(matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if(!('IntersectionObserver' in window)) return;

  // Icons mit eigener, aufwändigerer Choreografie bleiben unberührt.
  var SKIP = '.logo-scene,.map,.bam,.rankcard,.vnc__stage,.gbp-ring,.manifest__ico,.cur-ring,.inote,[data-noanim]';
  var SHAPES = 'path,line,polyline,polygon,circle,ellipse,rect';
  // Träger, deren Hover/Fokus das Icon erneut zeichnen lässt.
  var HOSTS = 'a,button,.svc,.fact,.chapter,.way,.tcard,.pledge';

  var icons = [];

  var play = function(svg){
    svg.classList.remove('lico--pending');
    // Neustart der Animation erzwingen: Klasse ab, Layout antippen, Klasse dran.
    svg.classList.remove('lico--draw');
    void svg.getBoundingClientRect();
    svg.classList.add('lico--draw');
  };

  // Alle noch nicht gezeichneten Icons. Wie beim Reveal weiter oben gilt: Was
  // der Beobachter beim schnellen Wischen überspringt, bliebe sonst dauerhaft
  // als leere Fläche stehen — ein Icon in .lico--pending ist unsichtbar.
  var offen = [];
  var abhaken = function(svg){
    var i = offen.indexOf(svg);
    if(i > -1) offen.splice(i, 1);
  };

  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(!e.isIntersecting) return;
      io.unobserve(e.target);
      abhaken(e.target);
      play(e.target);
    });
    nachlauf();
  }, {threshold:.3, rootMargin:'0px 0px -40px 0px'});

  var nachlauf = function(){
    if(!offen.length) return;
    var grenze = window.innerHeight - 40;
    for(var i = offen.length - 1; i >= 0; i--){
      var svg = offen[i];
      if(svg.getBoundingClientRect().top >= grenze) continue;
      io.unobserve(svg);
      offen.splice(i, 1);
      play(svg);
    }
    if(!offen.length) window.removeEventListener('scroll', angestossen);
  };
  var ruheTimer = null;
  var angestossen = function(){
    if(ruheTimer) clearTimeout(ruheTimer);
    ruheTimer = setTimeout(nachlauf, 160);
  };
  window.addEventListener('scroll', angestossen, {passive:true});

  // Wird true, wenn der Failsafe unten feststellt, dass der Observer nicht
  // arbeitet. Danach dürfen auch nachgezogene Icons nicht mehr versteckt
  // werden — sie würden sonst nie wieder auftauchen.
  var observerDead = false;

  var collect = function(){
    Array.prototype.forEach.call(document.querySelectorAll('svg[viewBox="0 0 24 24"]'), function(svg){
      if(svg.classList.contains('lico')) return;   // schon vermessen
      if(svg.closest(SKIP)) return;
      // Nicht gerendert (display:none, z. B. die Mobil-CTA auf dem Desktop oder
      // eine eingeklappte FAQ-Antwort)? Dann gar nicht erst verstecken — der
      // Observer würde dort nie anspringen und das Icon bliebe unsichtbar,
      // sobald es später doch eingeblendet wird. Solche Icons holt der
      // lico:rescan nach, sobald ihr Block sichtbar wird.
      if(!svg.getClientRects().length) return;
      var parts = svg.querySelectorAll(SHAPES);
      if(!parts.length) return;

      var marked = 0;
      Array.prototype.forEach.call(parts, function(el, i){
        // Strich oder Fläche? Der Stroke wird vom <svg> geerbt, Flächen-Icons
        // (fill="currentColor" ohne stroke) liefern hier "none".
        var stroked = getComputedStyle(el).stroke !== 'none';
        el.style.setProperty('--i', i);
        if(stroked){
          var len = 0;
          // getTotalLength() gibt es auf allen Grundformen (SVGGeometryElement),
          // kann aber bei degenerierten Formen 0 oder NaN liefern.
          try { len = el.getTotalLength(); } catch(err){ len = 0; }
          if(!isFinite(len) || len <= 0) return;
          el.style.setProperty('--len', len.toFixed(2));
          el.setAttribute('data-draw', '');
        } else {
          el.setAttribute('data-pop', '');
        }
        marked++;
      });
      if(!marked) return;

      svg.classList.add('lico');
      if(!observerDead) svg.classList.add('lico--pending');
      icons.push(svg);
      offen.push(svg);
      io.observe(svg);

      var host = svg.closest(HOSTS);
      if(host) host.classList.add('lico-host');
    });
  };

  collect();

  // Blöcke, die erst nach einer Interaktion eingeblendet werden (der
  // CTA-Block unter dem Profil-Check), waren beim ersten Durchlauf noch
  // display:none und damit nicht vermessbar. Wer so einen Block sichtbar
  // macht, meldet sich hier — dann werden nur die neuen Icons nachgezogen.
  document.addEventListener('lico:rescan', collect);

  // Failsafe: Steht nach 1,8 s ein Icon sichtbar im Bild und ist trotzdem
  // noch nicht gezeichnet, arbeitet der Observer nicht (In-App-Browser, Bot,
  // Screenshot-Renderer). Dann alle Icons unverzüglich sichtbar machen statt
  // sie versteckt zu lassen. Nur "noch keins gezeichnet" reicht nicht: Auf
  // der Startseite steht im ersten Bildschirm gar keins.
  setTimeout(function(){
    if(document.querySelector('.lico--draw')) return;
    var vh = window.innerHeight, stuck = false;
    icons.forEach(function(svg){
      if(stuck || !svg.classList.contains('lico--pending')) return;
      var r = svg.getBoundingClientRect();
      if(r.height > 0 && r.top < vh - 60 && r.bottom > 60) stuck = true;
    });
    if(!stuck) return;
    observerDead = true;
    icons.forEach(function(svg){ svg.classList.remove('lico--pending'); });
  }, 1800);

  // Erneut zeichnen bei Hover/Fokus des Trägers. Delegiert statt pro Element
  // gebunden — auf der Startseite sind das über 60 Icons.
  var lastHost = null;
  document.addEventListener('pointerover', function(e){
    if(e.pointerType === 'touch') return;
    var t = e.target;
    if(!t || !t.closest) return;
    var host = t.closest('.lico-host');
    if(!host || host === lastHost) return;
    lastHost = host;
    Array.prototype.forEach.call(host.querySelectorAll('svg.lico'), play);
  }, {passive:true});
  document.addEventListener('pointerout', function(e){
    if(!lastHost) return;
    if(!e.relatedTarget || !lastHost.contains(e.relatedTarget)) lastHost = null;
  }, {passive:true});
  document.addEventListener('focusin', function(e){
    var t = e.target;
    if(!t || !t.closest) return;
    var host = t.closest('.lico-host');
    if(host) Array.prototype.forEach.call(host.querySelectorAll('svg.lico'), play);
  });
})();

/* ============================================================
   REVEAL-FAILSAFE + MOBILE-MENÜ-STAGGER
   ============================================================ */
(function(){
  // .js [data-reveal] setzt opacity:0 und verlässt sich darauf, dass der
  // IntersectionObserver oben .in nachliefert. Bleibt das aus, wäre die halbe
  // Seite unsichtbar — das ist der Fehler, der sich in In-App-Browsern
  // (Instagram, Google-App) und bei Vorschau-Renderern zeigt. Geprüft wird
  // nach 1,6 s, ob ein Reveal-Element SICHTBAR im Bild steht und trotzdem
  // noch versteckt ist — nur dann arbeitet der Beobachter nachweislich nicht,
  // und die Versteck-Regel wird global abgeschaltet. (Früher reichte "noch
  // keins aufgetaucht": Auf der Startseite steht im ersten Bildschirm aber
  // gar keins, der Failsafe schaltete dort also grundlos alles ab.)
  if(document.querySelector('[data-reveal]')){
    setTimeout(function(){
      var vh = window.innerHeight;
      var offen = document.querySelectorAll('[data-reveal]:not(.in)');
      for(var i = 0; i < offen.length; i++){
        var r = offen[i].getBoundingClientRect();
        if(r.height > 0 && r.top < vh - 80 && r.bottom > 80){
          document.documentElement.classList.add('reveal-off');
          return;
        }
      }
    }, 1600);
  }

  // Menüeinträge laufen gestaffelt ein (CSS liest --i).
  var items = document.querySelectorAll('.mobile-menu a');
  Array.prototype.forEach.call(items, function(a, i){ a.style.setProperty('--i', i); });
})();

/* ============================================================
   GBP-SHOWCASE — Skalierung der Mockups + Animations-Zyklus
   ============================================================ */
(function(){
  var stage = document.getElementById('gspStage');
  if(!stage) return;

  var screens = stage.querySelectorAll('[data-gsp-screen]');
  var count   = document.getElementById('gspCount');
  var FRAME_W = 414, FRAME_H = 868;
  // Die Geräte sollen frei auf der Bühne stehen, nicht den Bildschirm füllen:
  // höchstens 64 % der Fensterhöhe und nie größer als 0,68 (≈ 590 px hoch).
  // Unter 0,42 wird der Bildschirminhalt unleserlich — kleiner nur, wenn die
  // Spaltenbreite es erzwingt.
  var MAX_S = 0.68, MIN_S = 0.42, HOEHE = 0.64;
  var raf = null, timer = null, pending = false;
  // Die Fensterhöhe nur bei geänderter Breite neu lesen: Auf dem iPhone ändert
  // sie sich beim Scrollen (Adressleiste klappt ein) — die Geräte würden sonst
  // mitten im Lesen wachsen und schrumpfen.
  var hoeheBreite = -1, hoeheFaktor = MAX_S;

  // Die Mockups sind in festen Pixeln gebaut. Hier wird aus der wirklich
  // verfügbaren Spaltenbreite und der Fensterhöhe der exakte Faktor
  // berechnet; site.css hat dafür nur grobe Stufen als Fallback.
  function fit(){
    pending = false;
    if(window.innerWidth !== hoeheBreite){
      hoeheBreite = window.innerWidth;
      hoeheFaktor = Math.max(MIN_S, Math.min(MAX_S, window.innerHeight * HOEHE / FRAME_H));
    }
    Array.prototype.forEach.call(screens, function(el){
      var avail = el.parentNode.clientWidth;
      if(!avail) return;
      var s = String(Math.round(Math.min(hoeheFaktor, avail / FRAME_W) * 1000) / 1000);
      // Nur schreiben, wenn sich etwas ändert — sonst tickt der
      // ResizeObserver sich selbst an.
      if(el.style.getPropertyValue('--gsp-s') !== s) el.style.setProperty('--gsp-s', s);
    });
  }
  function schedule(){ if(pending) return; pending = true; requestAnimationFrame(fit); }

  // Bewertungszähler des Gewinner-Profils läuft von 9 auf 187 hoch.
  function countUp(){
    if(!count) return;
    var start = performance.now(), dur = 1400;
    (function step(t){
      var p = Math.min(1, (t - start) / dur), e = 1 - Math.pow(1 - p, 3);
      count.textContent = Math.round(9 + (187 - 9) * e);
      if(p < 1) raf = requestAnimationFrame(step);
    })(start);
  }

  // Ein Durchlauf: alle Teil-Animationen zurückspulen und gemeinsam starten.
  // Bewusst kein Dauerloop — die Erklärtexte blenden sich erst nach gut sechs
  // Sekunden ein, ein Neustart alle paar Sekunden würde sie immer wieder
  // wegnehmen. Die Choreografie läuft einmal, wenn der Block ins Bild kommt,
  // und bleibt danach im Endzustand stehen.
  var laeuft = false;
  function run(){
    laeuft = true;
    if(timer) clearTimeout(timer);
    if(raf) cancelAnimationFrame(raf);
    if(stage.getAnimations){
      stage.getAnimations({subtree:true}).forEach(function(a){
        try{ a.cancel(); a.play(); }catch(e){}
      });
    }
    stage.style.animationPlayState = 'running';
    if(count) count.textContent = '9';
    // Muss mit der Choreografie im HTML zusammenpassen: Der Zähler läuft in dem
    // Moment hoch, in dem die Bewertungszeile des Gewinner-Profils steht.
    timer = setTimeout(countUp, 3570);
  }

  fit();
  if(window.ResizeObserver) new ResizeObserver(schedule).observe(stage);
  window.addEventListener('resize', schedule, {passive:true});

  // Telefon: Umschalter zwischen den beiden Handys (sichtbar nur unter 760 px,
  // site.css). Das eingeblendete Handy spielt seine Choreografie von vorn —
  // ein Element mit display:none hat keine laufende Animation, beim
  // Einblenden startet sie neu. Nur der Bewertungszähler ist ein Skript und
  // wird hier eigens neu angestossen.
  var opts = stage.querySelectorAll('[data-gsp-show]');
  function zeige(welches){
    if(stage.getAttribute('data-show') === welches) return;
    stage.setAttribute('data-show', welches);
    Array.prototype.forEach.call(opts, function(b){
      var an = b.getAttribute('data-gsp-show') === welches;
      b.classList.toggle('is-active', an);
      b.setAttribute('aria-pressed', an ? 'true' : 'false');
    });
    schedule();
    if(welches === 'b' && window.jlTrack) window.jlTrack('gsp_switch');
    if(welches === 'a' && laeuft){
      if(timer) clearTimeout(timer);
      if(raf) cancelAnimationFrame(raf);
      if(count) count.textContent = '9';
      timer = setTimeout(countUp, 3570);
    }
  }
  Array.prototype.forEach.call(opts, function(b){
    b.addEventListener('click', function(){ zeige(b.getAttribute('data-gsp-show')); });
  });
  // Wischen nach links zeigt Platz 11, nach rechts Platz 1 — nur eindeutig
  // waagerechte Gesten, damit normales Scrollen nie umschaltet.
  var grid = stage.querySelector('.gsp__grid');
  if(grid && opts.length){
    var x0 = null, y0 = 0;
    grid.addEventListener('touchstart', function(e){
      if(e.touches.length !== 1){ x0 = null; return; }
      x0 = e.touches[0].clientX; y0 = e.touches[0].clientY;
    }, {passive:true});
    grid.addEventListener('touchend', function(e){
      if(x0 === null) return;
      var t = e.changedTouches[0], dx = t.clientX - x0, dy = t.clientY - y0;
      x0 = null;
      if(Math.abs(dx) < 50 || Math.abs(dx) < Math.abs(dy) * 1.5) return;
      if(getComputedStyle(opts[0].parentNode).display === 'none') return;
      zeige(dx < 0 ? 'b' : 'a');
    }, {passive:true});
  }

  // Bei prefers-reduced-motion gar nicht erst starten: site.css schaltet dort
  // global *{animation:none} und die Pausen-Regel unten greift nicht, der Block
  // steht also bereits vollständig und ruhig im Grundzustand da — inklusive der
  // 187 Bewertungen, die so im Markup stehen.
  if(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  // Ohne IntersectionObserver gäbe es keinen Startschuss — der Block bliebe
  // auf dem ersten Bild der Choreografie stehen, also leer.
  if(!window.IntersectionObserver){ run(); return; }

  // Läuft genau einmal pro Seitenaufruf: sobald die Choreografie gestartet
  // ist, wird der Observer abgehängt. Hoch- und Runterscrollen darf die
  // Erklärtexte, den Sternenaufbau und den Bewertungszähler nicht wieder auf
  // null setzen — beim zweiten Anschauen soll der fertige Endzustand stehen,
  // nicht wieder der leere Anfang.
  var gestartet = false;
  var starten = function(){
    if(gestartet) return;
    gestartet = true;
    io.disconnect();
    window.removeEventListener('scroll', angestossen);
    run();
  };
  // Wie beim Reveal und den Live-Icons: Der Beobachter ist der Auslöser, der
  // Nachlauf die Absicherung. Wird der Rückruf beim schnellen Wischen
  // ausgelassen, bliebe die ganze Bühne im Anfangsbild der Choreografie
  // stehen — und das ist opacity:0, also eine leere Fläche in voller
  // Sektionshöhe.
  var ruheTimer = null;
  var nachlauf = function(){
    var r = stage.getBoundingClientRect();
    if(r.top < window.innerHeight && r.bottom > 0) starten();
  };
  var angestossen = function(){
    if(ruheTimer) clearTimeout(ruheTimer);
    ruheTimer = setTimeout(nachlauf, 160);
  };
  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){ if(e.isIntersecting) starten(); });
  }, {threshold:.15});
  io.observe(stage);
  window.addEventListener('scroll', angestossen, {passive:true});
})();

/* ============================================================
   ZWEI ZWEIGE — STARTSCREEN UND UMSCHALTER

   Die Seite fuehrt zwei Angebote: Local SEO ("/") und Webdesign
   ("/webdesign/"). Dieser Block macht die Entscheidung zwischen beiden
   bedienbar und merkt sie sich. Ob der Startscreen ueberhaupt erscheint,
   entscheidet NICHT dieses Modul, sondern das Inline-Skript in
   partials/chooser.html — es laeuft vor dem ersten Bildaufbau und setzt die
   Klasse .zweig-wahl auf <html>. Hier geht es nur um das Verhalten danach.
   ============================================================ */
(function(){
  var SCHLUESSEL = 'jl.zweig';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var speicher = null;
  try { speicher = window.localStorage; } catch(e){ /* Speicher gesperrt */ }
  var merken = function(wert){ try { if(speicher) speicher.setItem(SCHLUESSEL, wert); } catch(e){} };

  var wurzel = document.documentElement;
  var chooserOffen = wurzel.classList.contains('zweig-wahl');

  // Jeder Seitenaufruf verraet den Zweig ueber die Navigationsleiste. Wer
  // direkt auf /webdesign/ landet, hat sich damit ebenfalls entschieden und
  // bekommt den Startscreen spaeter nicht mehr vorgesetzt.
  //
  // Zwei Ausnahmen, beide wichtig:
  //  * Waehrend der Startscreen offen steht, wird nichts gemerkt — sonst waere
  //    die Entscheidung gefallen, bevor der Besucher sie getroffen hat.
  //  * Gemeinsame Seiten (Kontakt, Über mich, Rechtliches — gekennzeichnet
  //    durch data-zweig-geteilt am body) gehoeren keinem Zweig. Sie tragen aus
  //    Konvention die SEO-Navigation; wuerden sie den Zweig mitschreiben,
  //    waere ein Webdesign-Interessent nach einem Blick ins Impressum wieder
  //    ein SEO-Interessent — und die Themen-Vorauswahl im Buchungskalender
  //    stuende auf dem falschen Wert.
  var nav = document.getElementById('nav');
  var seitenZweig = nav && nav.getAttribute('data-zweig');
  var geteilt = document.body.hasAttribute('data-zweig-geteilt');
  if(seitenZweig && !chooserOffen && !geteilt) merken(seitenZweig);

  // "?zweig=…" ist nur der Ruecktransportweg fuer Browser ohne JavaScript
  // (siehe renderChooser in vite.config.js). Gelesen wurde er im Inline-Skript,
  // in der Adresszeile hat er danach nichts mehr verloren. Andere Parameter
  // — etwa "?verschieben=" aus den Terminmails — bleiben unangetastet.
  if(/[?&]zweig=/.test(location.search) && window.history && history.replaceState){
    try {
      var adresse = new URL(location.href);
      adresse.searchParams.delete('zweig');
      history.replaceState(null, '', adresse.pathname + adresse.search + adresse.hash);
    } catch(e){}
  }

  /* ---------- Startscreen ---------- */
  var chooser = document.getElementById('chooser');
  if(chooser && chooserOffen){
    var seite = document.querySelector('.page');
    var zweigBtn = document.getElementById('zweigBtn');

    // Der Rest der Seite ist waehrenddessen weder vorlesbar noch bedienbar.
    // inert deckt Maus, Tastatur und Screenreader in einem Zug ab; das
    // aria-hidden daneben ist der Rueckfall fuer aeltere Browser.
    if(seite){
      seite.setAttribute('aria-hidden', 'true');
      if('inert' in HTMLElement.prototype) seite.inert = true;
    }

    /* ---------- Eingangsanimation ----------
       Ob sie ueberhaupt laeuft, hat das Inline-Skript in partials/chooser.html
       vor dem ersten Bildaufbau entschieden (.zweig-intro auf <html>). Hier
       geht es nur um ihr Ende: entweder sie laeuft aus, oder der Besucher
       bricht sie mit Klick, Tipp oder Escape ab.

       Der Abbruch setzt lediglich eine Klasse, die jedes beteiligte Element
       auf seinen Endzustand springen laesst — nichts laeuft neu an, und weil
       dieser Endzustand ohnehin der Ruhezustand des Layouts ist, verschiebt
       sich dabei kein Pixel. */
    var INTRO_DAUER = 1150;
    var introLaeuft = wurzel.classList.contains('zweig-intro');
    var introUhr = null;
    // Fokus auf den Dialog selbst, NICHT auf die erste Karte: Chrome zeigt bei
    // programmatischem Fokus direkt nach dem Laden den Fokusrahmen an, und ein
    // Rahmen um "SEO Optimierung" saehe aus, als waere die Wahl schon getroffen.
    // So liest ein Screenreader den Dialog vor, Tab beginnt trotzdem bei der
    // ersten Karte — und optisch ist nichts vorbelegt.
    var fokussieren = function(){
      if(chooser.focus) chooser.focus({preventScroll:true});
    };

    /* Steht die Szene, wird jede beteiligte Animation abgeschaltet. Das ist
       kein Kosmetikschritt: eine mit fill:both beendete Animation haelt ihren
       Endwert dauerhaft fest und uebersteuert damit jede spaetere Regel — das
       Anheben einer Karte beim Ueberfahren bliebe sonst wirkungslos. Der
       Endzustand der Animationen ist identisch mit dem Ruhezustand der
       Elemente, es springt dabei also nichts. */
    var bereit = function(){ wurzel.classList.add('zweig-wahl-bereit'); };

    var introEnde = function(abgebrochen){
      if(!introLaeuft) return;
      introLaeuft = false;
      if(introUhr) window.clearTimeout(introUhr);
      chooser.removeEventListener('pointerdown', aufTipp);
      wurzel.classList.add('zweig-intro-fertig');
      if(abgebrochen){
        wurzel.classList.add('zweig-intro-skip');
        fokussieren();
      }
      bereit();
    };

    // pointerdown statt click: der Abbruch soll auf die Beruehrung reagieren,
    // nicht erst auf das Loslassen. Trifft der Tipp eine Karte, laeuft der
    // Klick danach unveraendert weiter — das Intro ist dann eben schon vorbei.
    var aufTipp = function(){ introEnde(true); };
    if(introLaeuft){
      chooser.addEventListener('pointerdown', aufTipp);
      introUhr = window.setTimeout(function(){ introEnde(false); }, INTRO_DAUER);
    } else {
      // Ohne Intro ist der gewohnte Auftritt nach rund 900 ms durch.
      window.setTimeout(bereit, reduce ? 0 : 950);
    }

    var geschlossen = false;
    var schliessen = function(wert){
      if(geschlossen) return;
      geschlossen = true;
      merken(wert);
      chooser.classList.add('is-closing');
      document.removeEventListener('keydown', aufEscape);
      window.setTimeout(function(){
        wurzel.classList.remove('zweig-wahl');
        chooser.classList.remove('is-closing');
        if(seite){
          seite.removeAttribute('aria-hidden');
          if('inert' in HTMLElement.prototype) seite.inert = false;
        }
        // Der Fokus wandert auf den Umschalter in der Leiste: genau dort laesst
        // sich die eben getroffene Entscheidung jederzeit wieder aendern.
        if(zweigBtn && zweigBtn.focus) zweigBtn.focus({preventScroll:true});
      // Feste Dauer statt transitionend: unter prefers-reduced-motion laeuft
      // gar keine Animation, das Ereignis kaeme also nie.
      }, reduce ? 0 : 480);
    };

    var aufEscape = function(e){
      if(e.key !== 'Escape' && e.key !== 'Esc') return;
      // Solange das Intro laeuft, bricht Escape zuerst nur dieses ab. Ein
      // zweites Escape schliesst dann wie gewohnt den Startscreen: sonst
      // uebersaehe man die Wahl, die man gerade erst zu sehen bekommt.
      if(introLaeuft){ introEnde(true); return; }
      schliessen('uebersprungen');
    };
    document.addEventListener('keydown', aufEscape);

    var karten = chooser.querySelectorAll('.chooser__card');
    Array.prototype.forEach.call(karten, function(karte){
      karte.addEventListener('click', function(e){
        var wahl = karte.getAttribute('data-zweig-wahl');
        // Fuehrt die Karte auf eine andere Seite, uebernimmt der Browser: das
        // Overlay bleibt bis zum Seitenwechsel stehen, es blitzt nichts auf.
        if(karte.pathname !== location.pathname){ merken(wahl); return; }
        e.preventDefault();
        schliessen(wahl);
      });
    });

    var spaeter = chooser.querySelector('[data-zweig-skip]');
    if(spaeter){
      spaeter.addEventListener('click', function(e){
        e.preventDefault();
        schliessen('uebersprungen');
      });
    }

    // Fokusfalle: Tab laeuft im Startscreen im Kreis, statt hinter das Overlay
    // auf die verdeckte Seite zu springen.
    chooser.addEventListener('keydown', function(e){
      if(e.key !== 'Tab') return;
      var liste = chooser.querySelectorAll('a[href]');
      if(!liste.length) return;
      var erste = liste[0], letzte = liste[liste.length - 1];
      if(e.shiftKey && document.activeElement === erste){ letzte.focus(); e.preventDefault(); }
      else if(!e.shiftKey && document.activeElement === letzte){ erste.focus(); e.preventDefault(); }
    });

    // Der Fokus wartet, bis die Szene steht — waehrend des Intros waere die
    // Ansage des Dialogs der Bewegung voraus. Bricht der Besucher ab, holt
    // introEnde() den Fokus sofort nach.
    window.setTimeout(fokussieren, reduce ? 0 : (introLaeuft ? INTRO_DAUER : 420));
  }

  /* ---------- Umschalter in der Navigationsleiste ---------- */
  var box = document.querySelector('[data-zweig-switch]');
  if(!box) return;
  var btn = box.querySelector('.zweig__btn');
  var menu = box.querySelector('.zweig__menu');
  if(!btn || !menu) return;
  var eintraege = menu.querySelectorAll('.zweig__item');
  var offen = false;

  var setzen = function(auf){
    offen = auf;
    box.classList.toggle('open', auf);
    btn.setAttribute('aria-expanded', String(auf));
  };

  btn.addEventListener('click', function(e){
    e.stopPropagation();
    setzen(!offen);
  });

  document.addEventListener('click', function(e){
    if(offen && !box.contains(e.target)) setzen(false);
  });

  document.addEventListener('keydown', function(e){
    if(offen && (e.key === 'Escape' || e.key === 'Esc')){ setzen(false); btn.focus(); }
  });

  // Tastaturbedienung wie bei einem Systemmenue: Pfeil ab oeffnet und springt
  // auf den ersten Eintrag, Pfeil auf auf den letzten.
  btn.addEventListener('keydown', function(e){
    if(e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
    e.preventDefault();
    setzen(true);
    var ziel = e.key === 'ArrowDown' ? eintraege[0] : eintraege[eintraege.length - 1];
    // Ein Bild abwarten: solange das Menue noch visibility:hidden traegt,
    // laesst sich darin nichts fokussieren.
    requestAnimationFrame(function(){ if(ziel) ziel.focus(); });
  });

  menu.addEventListener('keydown', function(e){
    var i = Array.prototype.indexOf.call(eintraege, document.activeElement);
    if(e.key === 'ArrowDown'){ e.preventDefault(); eintraege[(i + 1) % eintraege.length].focus(); }
    else if(e.key === 'ArrowUp'){ e.preventDefault(); eintraege[(i - 1 + eintraege.length) % eintraege.length].focus(); }
    else if(e.key === 'Home'){ e.preventDefault(); eintraege[0].focus(); }
    else if(e.key === 'End'){ e.preventDefault(); eintraege[eintraege.length - 1].focus(); }
    else if(e.key === 'Tab'){ setzen(false); }
  });
})();

/* ============================================================
   THEMENWAHL ÜBER DEM BUCHUNGSKALENDER (nur /kontakt/)

   Die Kontaktseite steht in beiden Navigationen und gehoert damit keinem
   Zweig allein. Damit eine Webdesign-Anfrage nicht als Google-Profil-Termin
   bei Leandro ankommt, waehlt der Besucher hier sichtbar das Thema. Der Wert
   landet im data-topic des Widgets; booking.js liest ihn beim Absenden
   (currentTopic) und schickt ihn mit.

   Auf /webdesign/ und der Startseite gibt es diese Umschaltung bewusst NICHT:
   dort ist das Thema durch die Seite selbst schon beantwortet.
   ============================================================ */
(function(){
  var box = document.querySelector('[data-topic-switch]');
  var widget = document.getElementById('bookingWidget');
  if(!box || !widget) return;
  var knoepfe = box.querySelectorAll('[data-topic-set]');

  var setzen = function(thema){
    widget.dataset.topic = thema;
    Array.prototype.forEach.call(knoepfe, function(b){
      var an = b.getAttribute('data-topic-set') === thema;
      b.classList.toggle('is-active', an);
      b.setAttribute('aria-pressed', String(an));
    });
  };

  // Wer zuletzt im Webdesign-Zweig unterwegs war, findet das Thema bereits
  // vorausgewaehlt — sichtbar, nicht heimlich, und mit einem Klick zu aendern.
  try {
    if(window.localStorage && localStorage.getItem('jl.zweig') === 'webdesign') setzen('webdesign');
  } catch(e){ /* Speicher gesperrt — dann bleibt die Vorauswahl aus dem HTML */ }

  Array.prototype.forEach.call(knoepfe, function(b){
    b.addEventListener('click', function(){ setzen(b.getAttribute('data-topic-set')); });
  });
})();

/* ============================================================
   NÄCHSTER FREIER TERMIN (Hero der Startseite)

   Die Zeile über der Überschrift nennt den nächsten freien Termin — dieselbe
   Quelle (/api/booking/slots), aus der auch der Kalender unten liest. Die
   Antwort wird per <link rel="preload"> schon während des Seitenaufbaus
   geholt; fetch() unten bekommt dieselbe Antwort ohne zweiten Abruf.

   Getauscht wird NUR, solange der Hinweis noch unsichtbar auf seinen
   Einsatz wartet (CSS-Animation im Verzögerungsabschnitt). Kommt die Antwort
   später, bleibt der feste Satz stehen — es springt nie Text in eine schon
   sichtbare Zeile. Bewusst ohne localStorage/sessionStorage.

   Ein Klick übergibt Tag und Uhrzeit an den Kalender (window.jlPick plus
   Ereignis 'jl:pick'). booking.js öffnet genau diesen Tag — der Besucher
   landet nicht vor einem leeren Kalender, sondern vor dem Termin, den er
   gerade gesehen hat.
   ============================================================ */
(function(){
  var live = document.getElementById('heroLive');
  var tx = document.getElementById('heroLiveText');
  if(!live || !tx || !window.fetch) return;

  var WD = ['So.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.'];
  var MON = ['Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.'];

  var dayDiff = function(a, b){
    var pa = a.split('-').map(Number), pb = b.split('-').map(Number);
    return Math.round((Date.UTC(pb[0], pb[1]-1, pb[2]) - Date.UTC(pa[0], pa[1]-1, pa[2])) / 864e5);
  };
  var label = function(slot){
    var d = dayDiff(slot.today, slot.date);
    if(d === 0) return 'heute';
    if(d === 1) return 'morgen';
    var p = slot.date.split('-').map(Number);
    var wd = new Date(Date.UTC(p[0], p[1]-1, p[2])).getUTCDay();
    return WD[wd] + ' ' + p[2] + '. ' + MON[p[1]-1];
  };
  // Noch unsichtbar? Dann darf der Text wechseln. Ohne laufende Animation
  // (kein .js, "Bewegung reduzieren") ist die Zeile von Anfang an sichtbar.
  var canSwap = function(){
    if(!live.getAnimations) return false;
    var anims = live.getAnimations();
    for(var i = 0; i < anims.length; i++){
      var t = anims[i].effect && anims[i].effect.getComputedTiming ? anims[i].effect.getComputedTiming() : null;
      if(t && t.localTime !== null && t.localTime < t.delay - 30) return true;
    }
    return false;
  };
  // Nur plausible Daten zeigen: echtes Datum, echte Uhrzeit, nicht in der
  // Vergangenheit. Alles andere (Fehler, leere oder kaputte Antwort) lässt
  // den neutralen Satz aus dem HTML stehen — nie eine ausgedachte Zeit.
  var plausibel = function(slot){
    return !!slot && /^\d{4}-\d{2}-\d{2}$/.test(slot.date) && /^\d{2}:\d{2}$/.test(slot.time || '')
      && /^\d{4}-\d{2}-\d{2}$/.test(slot.today || '') && dayDiff(slot.today, slot.date) >= 0;
  };
  var show = function(slot){
    if(!plausibel(slot) || !canSwap()) return;
    var b = document.createElement('b');
    b.textContent = label(slot) + ', ' + slot.time + ' Uhr';
    tx.textContent = 'Nächster freier Termin: ';
    tx.appendChild(b);
    live.setAttribute('data-date', slot.date);
    live.setAttribute('data-time', slot.time);
    live.setAttribute('aria-label', 'Nächster freier Termin: ' + label(slot) + ', ' + slot.time + ' Uhr. Jetzt buchen.');
  };

  var firstSlot = function(data){
    var days = data && data.days ? data.days : {};
    var keys = Object.keys(days).filter(function(k){ return Array.isArray(days[k]) && days[k].length; }).sort();
    if(!keys.length) return null;
    return {date: keys[0], time: days[keys[0]][0], today: data.today};
  };
  var getMonth = function(m){
    return fetch('/api/booking/slots' + (m ? '?month=' + m : ''), {headers:{Accept:'application/json'}})
      .then(function(r){ return r.ok ? r.json() : null; })
      .catch(function(){ return null; });
  };
  var nextMonth = function(key){
    var p = key.split('-').map(Number);
    var d = new Date(Date.UTC(p[0], p[1], 1));
    return d.getUTCFullYear() + '-' + String(d.getUTCMonth() + 1).padStart(2, '0');
  };

  var load = function(){
    getMonth('').then(function(data){
      if(!data || !data.success) return null;
      var slot = firstSlot(data);
      if(slot || !data.month || data.month >= data.max_month) return slot;
      // Im laufenden Monat ist nichts mehr frei: einen Monat weiter schauen.
      return getMonth(nextMonth(data.month)).then(function(d2){ return d2 && d2.success ? firstSlot(d2) : null; });
    }).then(function(slot){
      if(slot) show(slot);
    });
  };

  live.addEventListener('click', function(){
    var date = live.getAttribute('data-date'), time = live.getAttribute('data-time');
    if(!date) return;
    var pick = {date: date, time: time};
    // Für den Fall, dass booking.js erst beim Hinscrollen geladen wird …
    window.jlPick = pick;
    // … und für den Fall, dass der Kalender schon läuft.
    try { window.dispatchEvent(new CustomEvent('jl:pick', {detail: pick})); } catch(e){}
  });

  // Sofort: die Antwort liegt dank Preload meist schon bereit.
  load();
})();

/* ============================================================
   WISCH-GALERIEN (Telefon) — Punkte unter .m-gallery
   Die Galerie selbst ist reines CSS (scroll-snap, site.css). Hier kommt nur
   die Anzeige dazu, welche Karte gerade vorne steht. Über 640 px sind die
   Punkte per CSS ausgeblendet; das Skript läuft trotzdem, das kostet nichts,
   weil dort nie ein Scroll-Ereignis der Reihe feuert.
   ============================================================ */
(function(){
  var reihen = document.querySelectorAll('.m-gallery');
  Array.prototype.forEach.call(reihen, function(reihe){
    var karten = reihe.children;
    if(karten.length < 2) return;
    var punkte = document.createElement('div');
    punkte.className = 'm-dots';
    punkte.setAttribute('aria-hidden', 'true');
    for(var i = 0; i < karten.length; i++) punkte.appendChild(document.createElement('i'));
    reihe.parentNode.insertBefore(punkte, reihe.nextSibling);

    var aktiv = -1, geplant = false;
    var setze = function(){
      geplant = false;
      var max = reihe.scrollWidth - reihe.clientWidth;
      var schritt = karten[1].offsetLeft - karten[0].offsetLeft;
      var n = !schritt || max < 4 ? 0 : (reihe.scrollLeft >= max - 4 ? karten.length - 1 : Math.round(reihe.scrollLeft / schritt));
      n = Math.max(0, Math.min(karten.length - 1, n));
      if(n === aktiv) return;
      if(aktiv > -1) punkte.children[aktiv].classList.remove('is-on');
      punkte.children[n].classList.add('is-on');
      aktiv = n;
    };
    reihe.addEventListener('scroll', function(){
      if(geplant) return;
      geplant = true;
      requestAnimationFrame(setze);
    }, {passive:true});
    setze();
  });
})();
