(function(){
  // Der Buchungskalender (src/js/booking.js) wird erst geladen, wenn er in
  // Sichtweite kommt. Auf allen Seiten ohne Widget — also überall außer
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
    }, {rootMargin:'600px'});
    io.observe(widget);
  } else {
    load();
  }
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
    // Der Radar laeuft nur, solange tatsaechlich geprueft wird. Frueher lief
    // er auch im Formularzustand, also ab dem ersten Sichtkontakt dauerhaft —
    // eine Bewegung, die nichts anzeigt.
    if(stage) stage.setAttribute('data-scan', state === 'loading' ? 'on' : 'off');
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
  var onScroll = function(){ nav.classList.toggle('scrolled', window.scrollY > 24); };
  onScroll(); window.addEventListener('scroll', onScroll, {passive:true});

  // mobile menu
  var toggle = document.getElementById('navToggle');
  var menu = document.getElementById('mobileMenu');
  toggle.addEventListener('click', function(){
    var open = nav.classList.toggle('open');
    menu.classList.toggle('show', open);
    toggle.setAttribute('aria-expanded', open);
    toggle.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
  });
  menu.querySelectorAll('a').forEach(function(a){
    a.addEventListener('click', function(){ nav.classList.remove('open'); menu.classList.remove('show'); });
  });

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

  var finePointer = window.matchMedia('(hover:hover) and (pointer:fine)').matches;


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

  // Scrollspy: aktive Sektion in der Navigation markieren
  var spyLinks = document.querySelectorAll('.nav__links a[href^="#"]');
  if(spyLinks.length && 'IntersectionObserver' in window){
    var byId = {};
    spyLinks.forEach(function(a){ byId[a.getAttribute('href').slice(1)] = a; });
    var setActive = function(id){
      spyLinks.forEach(function(a){ a.classList.remove('active'); a.removeAttribute('aria-current'); });
      if(byId[id]){ byId[id].classList.add('active'); byId[id].setAttribute('aria-current','true'); }
    };
    var spy = new IntersectionObserver(function(entries){
      entries.forEach(function(e){ if(e.isIntersecting) setActive(e.target.id); });
    }, {rootMargin:'-35% 0px -55% 0px'});
    Object.keys(byId).forEach(function(id){
      var sec = document.getElementById(id);
      if(sec) spy.observe(sec);
    });
  }

  // Sticky Mobile-CTA: nach dem Hero zeigen, im Kontaktbereich und im
  // Footer ausblenden (sonst verdeckt die fixe Leiste am Seitenende
  // dauerhaft die letzte Footer-Zeile, ohne dass man daran vorbeiscrollen kann).
  var mcta = document.getElementById('mcta');
  if(mcta){
    var kontakt = document.getElementById('kontakt');
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

  // Kontaktformular – echter Versand über Formspree (AJAX, Besucher bleibt auf der Seite).
  var FORM_ENDPOINT = 'https://formspree.io/f/xlgyavbn';
  var form = document.getElementById('contactForm');
  var submitBtn = document.getElementById('cfSubmit');
  var statusEl = document.getElementById('formStatus');
  function showStatus(kind, html){
    statusEl.className = 'form-status show form-status--' + kind;
    statusEl.innerHTML = html;
  }
  if(form) form.addEventListener('submit', function(ev){
    ev.preventDefault();
    var name = (document.getElementById('cf-name').value||'').trim();
    var mail = (document.getElementById('cf-mail').value||'').trim();
    var msg = (document.getElementById('cf-msg').value||'').trim();
    if(!name || !mail || !msg){ form.reportValidity && form.reportValidity(); return; }
    submitBtn.disabled = true;
    submitBtn.textContent = 'Wird gesendet …';
    fetch(FORM_ENDPOINT, { method:'POST', headers:{'Accept':'application/json'}, body:new FormData(form) })
      .then(function(res){
        if(!res.ok) throw new Error('HTTP ' + res.status);
        form.reset();
        showStatus('ok', 'Danke, Ihre Nachricht ist angekommen — ich antworte werktags innerhalb von 24 Stunden.');
      })
      .catch(function(){
        showStatus('err', 'Das Senden hat leider nicht geklappt. Rufen Sie mich an: <a href="tel:+4917655769680">+49 176 55769680</a> — oder schreiben Sie an <a href="mailto:Info@jungline.de">Info@jungline.de</a>.');
      })
      .then(function(){
        submitBtn.disabled = false;
        submitBtn.textContent = 'Nachricht senden';
      });
  });

  // Impressum & Datenschutz sind jetzt echte, crawlbare Seiten (/impressum, /datenschutz) —
  // die früheren Rechtstext-Modals entfallen ersatzlos.
})();

/* ============================================================
   GBP-SHOWCASE — Skalierung der Mockups
   ============================================================ */
(function(){
  var stage = document.querySelector('.gsp__stage');
  if(!stage) return;

  var screens = stage.querySelectorAll('.gsp__screen');
  var FRAME_W = 414;
  var pending = false;

  // Die Mockups sind in festen Pixeln gebaut (414 x 868, wie ein Screenshot).
  // Hier wird aus der wirklich verfügbaren Spaltenbreite der exakte Faktor
  // berechnet; site.css hat dafür nur grobe Breakpoint-Stufen als Fallback.
  function fit(){
    pending = false;
    Array.prototype.forEach.call(screens, function(el){
      var avail = el.parentNode.clientWidth;
      if(!avail) return;
      var s = String(Math.round(Math.min(1, avail / FRAME_W) * 1000) / 1000);
      // Nur schreiben, wenn sich etwas ändert — sonst tickt der
      // ResizeObserver sich selbst an.
      if(el.style.getPropertyValue('--gsp-s') !== s) el.style.setProperty('--gsp-s', s);
    });
  }
  function schedule(){ if(pending) return; pending = true; requestAnimationFrame(fit); }

  fit();
  if(window.ResizeObserver) new ResizeObserver(schedule).observe(stage);
  window.addEventListener('resize', schedule, {passive:true});
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
      if(e.key === 'Escape' || e.key === 'Esc') schliessen('uebersprungen');
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

    // Fokus auf den Dialog selbst, NICHT auf die erste Karte: Chrome zeigt bei
    // programmatischem Fokus direkt nach dem Laden den Fokusrahmen an, und ein
    // Rahmen um "SEO Optimierung" saehe aus, als waere die Wahl schon getroffen.
    // So liest ein Screenreader den Dialog vor, Tab beginnt trotzdem bei der
    // ersten Karte — und optisch ist nichts vorbelegt.
    window.setTimeout(function(){
      if(chooser.focus) chooser.focus({preventScroll:true});
    }, reduce ? 0 : 420);
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
