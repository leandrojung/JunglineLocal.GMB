(function(){
  // load Calendly only once the booking widget is actually about to be seen
  var widget = document.querySelector('.calendly-inline-widget');
  if(!widget) return;
  var loaded = false;
  var load = function(){
    if(loaded) return;
    loaded = true;
    var s = document.createElement('script');
    s.src = 'https://assets.calendly.com/assets/external/widget.js';
    s.async = true;
    document.body.appendChild(s);
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
  // Localo-Ranking-Widget genau wie Calendly erst laden, wenn es in
  // Sichtweite kommt — spart auf der Startseite ein komplettes
  // Drittanbieter-Skript beim ersten Laden (Tool sitzt direkt unterm Hero).
  var tool = document.getElementById('free-tool');
  if(!tool) return;
  var loaded = false;
  var load = function(){
    if(loaded) return;
    loaded = true;
    var s = document.createElement('script');
    s.src = 'https://jstools.localo.app/scripts/freetool.js';
    s.async = true;
    document.body.appendChild(s);
  };
  if('IntersectionObserver' in window){
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){ if(entry.isIntersecting) load(); });
    }, {rootMargin:'600px'});
    io.observe(tool);
  } else {
    load();
  }
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

  // reveal on scroll
  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){ if(e.isIntersecting){ e.target.classList.add('in'); io.unobserve(e.target); } });
  }, {threshold:.14, rootMargin:'0px 0px -50px 0px'});
  document.querySelectorAll('[data-reveal]').forEach(function(el){ io.observe(el); });

  // hero entrance: Schreibmaschinen-Effekt (skipped bei reduced motion — dann
  // ist alles sofort als vollständiger Text sichtbar). Die .js-Klasse sitzt als
  // winziges Inline-Script in head.html (vor dem ersten Paint), damit Hero-Text
  // nie erst aufblitzt und dann durch die Reveal-Regeln verschwindet.
  var mkCursor = function(){
    var c = document.createElement('span');
    c.className = 'lead__cursor';
    c.setAttribute('aria-hidden', 'true');
    return c;
  };

  if(!reduce){
    // ---- Headline: einmaliger Schreibmaschinen-Effekt --------------------
    // Tippt erst den normalen Teil, dann den grün hervorgehobenen (.hl) und
    // hält danach an (kein Loop). Der volle Satz steht als aria-label, damit
    // Screenreader nicht Wort für Wort ein wachsendes Fragment vorgelesen
    // bekommen.
    var h1 = document.getElementById('heroTitle');
    var startLead, reserveHeadHeight;

    var runLead = function(){ if(startLead) startLead(); };

    if(h1){
      var hlEl = h1.querySelector('.hl');
      var hlText = hlEl ? hlEl.textContent : '';
      var plainStr = (h1.firstChild && h1.firstChild.nodeType === 3) ? h1.firstChild.textContent : h1.textContent;
      if(hlEl){
        // nur den reinen Textanteil vor dem .hl übernehmen
        plainStr = '';
        Array.prototype.slice.call(h1.childNodes).forEach(function(n){
          if(n === hlEl) return;
          if(n.nodeType === 3) plainStr += n.textContent;
        });
      }
      h1.textContent = '';
      var plainSpan = document.createElement('span');
      var hlSpan = document.createElement('span');
      hlSpan.className = 'hl';
      var hCursor = mkCursor();
      h1.appendChild(plainSpan);
      h1.appendChild(hlSpan);
      h1.appendChild(hCursor);

      // Headline-Höhe vorab auf den vollen (mehrzeiligen) Endzustand
      // reservieren, damit sie beim Eintippen nicht Zeile für Zeile wächst und
      // alles darunter (Untertitel, CTA) schiebt. Gemessen wird am echten
      // Element (exakte Schrift/Stile): die echten Span-Knoten werden kurz
      // abgehängt, der volle Text eingesetzt, die Höhe gelesen und die Knoten
      // wieder angehängt — passiert synchron, also unsichtbar. Passt sich per
      // Resize an (Zeilenzahl ändert sich mit der Breite).
      var h1Full = plainStr + hlText;
      reserveHeadHeight = function(){
        h1.style.minHeight = '0px';
        var kids = [];
        while(h1.firstChild){ kids.push(h1.firstChild); h1.removeChild(h1.firstChild); }
        h1.textContent = h1Full;
        var hh = h1.getBoundingClientRect().height;
        h1.textContent = '';
        kids.forEach(function(k){ h1.appendChild(k); });
        h1.style.minHeight = Math.ceil(hh) + 'px';
      };
      reserveHeadHeight();

      var segs = [{el: plainSpan, text: plainStr}, {el: hlSpan, text: hlText}];
      var si = 0, sc = 0;
      var typeHead = function(){
        if(si >= segs.length){ hCursor.classList.add('done'); runLead(); return; }
        var seg = segs[si];
        if(sc >= seg.text.length){ si++; sc = 0; typeHead(); return; }
        seg.el.textContent += seg.text.charAt(sc);
        sc++;
        setTimeout(typeHead, 42);
      };
      setTimeout(typeHead, 260);
    }

    // ---- Lead: rotierender Schreibmaschinen-Effekt (Endlos-Loop) ---------
    // Der GESAMTE Satz tippt sich von leer ein — der feste Anfang steht nicht
    // mehr schon vorab da. Erst nach der Headline: zuerst der feste Anfang,
    // dann die erste Aussage; danach wird nur noch der Satz-Abschluss gelöscht
    // und durch die nächste Aussage ersetzt (der Anfang bleibt dann stehen).
    // Die <p> hat eine fixierte Höhe (CSS min-height), damit beim Wechsel
    // zwischen kurzen und langen Aussagen nichts darunter springt. Der volle
    // Satz steht komplett als aria-label für Screenreader.
    var lead = document.getElementById('heroLead');
    if(lead){
      var prefix = 'Ich optimiere Ihr Profil bei Google, damit Sie ';
      var phrases = [
        'im Kartenbereich ganz oben stehen und somit mehr Kunden bekommen.',
        'mehr Umsatz generieren.',
        'mehr Aufmerksamkeit erregen.'
      ];
      lead.textContent = '';
      var staticEl = document.createElement('span');   // fester Anfang (wird einmal getippt)
      var rotateEl = document.createElement('span');   // wechselnder Abschluss
      var leadCursor = mkCursor();
      lead.appendChild(staticEl);
      lead.appendChild(rotateEl);
      // Cursor erst anhängen, wenn das Tippen startet — vorher bleibt der
      // reservierte Bereich leer (kein einsam blinkender Cursor).

      // Feste Höhe reservieren: die längste Aussage (Anfang + Abschluss) wird
      // am echten Element gemessen (exakte Stile), damit beim Wechsel zwischen
      // kurzen und langen Aussagen darunter nichts springt. Die echten Span-
      // Knoten werden dafür kurz abgehängt und wieder angehängt. Passt sich per
      // Resize an (auf Mobil/Übergangsbreiten mehr Zeilen als auf breitem
      // Desktop), ohne auf breiten Screens eine leere Zeile zu reservieren.
      var reserveLeadHeight = function(){
        lead.style.minHeight = '0px';
        var kids = [];
        while(lead.firstChild){ kids.push(lead.firstChild); lead.removeChild(lead.firstChild); }
        var maxH = 0;
        phrases.forEach(function(p){
          lead.textContent = prefix + p;
          maxH = Math.max(maxH, lead.getBoundingClientRect().height);
        });
        lead.textContent = '';
        kids.forEach(function(k){ lead.appendChild(k); });
        lead.style.minHeight = Math.ceil(maxH) + 'px';
      };
      reserveLeadHeight();
      var reserveRAF;
      window.addEventListener('resize', function(){
        cancelAnimationFrame(reserveRAF);
        reserveRAF = requestAnimationFrame(function(){
          reserveLeadHeight();
          if(reserveHeadHeight) reserveHeadHeight();
        });
      }, {passive:true});

      // Phase 2: Abschlüsse rotieren (Anfang bleibt stehen)
      var pi = 0, pc = 0, deleting = false;
      var tick = function(){
        var phrase = phrases[pi];
        if(!deleting){
          pc++;
          rotateEl.textContent = phrase.slice(0, pc);
          if(pc >= phrase.length){ deleting = true; setTimeout(tick, 1800); return; }
          setTimeout(tick, 30);
        } else {
          pc--;
          rotateEl.textContent = phrase.slice(0, pc);
          if(pc <= 0){ deleting = false; pi = (pi + 1) % phrases.length; setTimeout(tick, 300); return; }
          setTimeout(tick, 16);
        }
      };

      // Phase 1: festen Anfang einmalig von leer eintippen, dann rotieren
      var typePrefix = function(pos){
        if(pos > prefix.length){ setTimeout(tick, 120); return; }
        staticEl.textContent = prefix.slice(0, pos);
        setTimeout(function(){ typePrefix(pos + 1); }, 26);
      };

      startLead = function(){
        lead.appendChild(leadCursor);
        setTimeout(function(){ typePrefix(0); }, 300);
      };
    }

    // Falls es keine Headline zum Tippen gibt, den Lead-Loop sofort starten.
    if(!h1) runLead();

    var heroEl = document.querySelector('.hero');
    if(heroEl){
      requestAnimationFrame(function(){ requestAnimationFrame(function(){
        heroEl.classList.add('hero-live');
      }); });
    }
  }

  // stat count-up — der Endwert steht als Fallback im HTML (ohne JS/Animation
  // sieht der Besucher die echte Zahl); JS nullt nur, wenn es auch animiert.
  var counts = document.querySelectorAll('.count');
  if(counts.length && !reduce && 'IntersectionObserver' in window){
    var fmtCount = function(v, dec){ return dec ? v.toFixed(dec).replace('.', ',') : String(Math.round(v)); };
    var runCount = function(el){
      var target = parseFloat(el.getAttribute('data-count')) || 0;
      var dec = parseInt(el.getAttribute('data-dec'), 10) || 0;
      var start = null, dur = 1400;
      var tick = function(ts){
        if(!start) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        el.textContent = fmtCount((1 - Math.pow(1 - p, 3)) * target, dec);
        if(p < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    };
    var cio = new IntersectionObserver(function(entries){
      entries.forEach(function(e){ if(e.isIntersecting){ runCount(e.target); cio.unobserve(e.target); } });
    }, {threshold:.5});
    counts.forEach(function(el){
      el.textContent = fmtCount(0, parseInt(el.getAttribute('data-dec'), 10) || 0);
      cio.observe(el);
    });
  }

  // hero parallax + rankcard tilt (fine pointer only)
  var finePointer = window.matchMedia('(hover:hover) and (pointer:fine)').matches;
  if(finePointer && !reduce){
    var heroBg = document.getElementById('heroBg');
    var card = document.getElementById('rankcard');
    var tx=0,ty=0, raf=null;
    var apply = function(){
      if(heroBg){ heroBg.style.setProperty('--mx', tx.toFixed(3)); heroBg.style.setProperty('--my', ty.toFixed(3)); }
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
    // cursor-following spotlight on cards & panels
    document.querySelectorAll('.panel, .linkcard').forEach(function(el){
      el.addEventListener('mousemove', function(e){
        var r = el.getBoundingClientRect();
        el.style.setProperty('--sx', ((e.clientX-r.left)/r.width*100).toFixed(1)+'%');
        el.style.setProperty('--sy', ((e.clientY-r.top)/r.height*100).toFixed(1)+'%');
      }, {passive:true});
      el.addEventListener('mouseleave', function(){ el.style.setProperty('--sy','-40%'); });
    });
    // magnetic primary buttons
    document.querySelectorAll('.btn--primary').forEach(function(btn){
      var mraf=null,mx=0,my=0;
      btn.addEventListener('mousemove', function(e){
        var r = btn.getBoundingClientRect();
        mx = (e.clientX-(r.left+r.width/2))*0.22;
        my = (e.clientY-(r.top+r.height/2))*0.30;
        if(!mraf) mraf = requestAnimationFrame(function(){ btn.style.transform='translate('+mx.toFixed(1)+'px,'+(my-2).toFixed(1)+'px)'; mraf=null; });
      }, {passive:true});
      btn.addEventListener('mouseleave', function(){ btn.style.transform=''; });
    });
  }

  // ranking climb animation (nur Startseite — Unterseiten haben keine Rankcard)
  var you = document.getElementById('you');
  if(you){
    var rows = Array.prototype.slice.call(document.querySelectorAll('#results .result'));
    var youRank = document.getElementById('youRank');
    var getRow = function(){ return window.matchMedia('(max-width:560px)').matches ? 77 : 70; };
    var place = function(order){ var row = getRow(); order.forEach(function(idx, slot){ rows[idx].style.transform = 'translateY(' + (slot*row) + 'px)'; }); };
    var youIdx = rows.indexOf(you);
    var others = rows.map(function(_,i){return i;}).filter(function(i){return i!==youIdx;});
    var bottomOrder = others.concat([youIdx]);
    var topOrder = [youIdx].concat(others);

    // Der Betrieb klettert einmal von Platz 3 auf Platz 1 und bleibt dort —
    // ruhige, selbstbewusste Erzählung statt Endlosschleife.
    if(reduce){
      place(topOrder); you.classList.add('is-top'); youRank.textContent='1';
    } else {
      place(bottomOrder);
      setTimeout(function(){ place(topOrder); you.classList.add('is-top'); youRank.textContent='1'; }, 1600);
    }
  }

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
      if(t.closest('input,textarea,select,iframe,.cal-frame')){ setCurState('off'); return; }
      if(t.closest('.ba__stage')){ setCurState('drag'); return; }
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
  var chapterEls = Array.prototype.slice.call(document.querySelectorAll('.chapter'));
  if(chapterEls.length && !reduce){
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

  // Vorher-Nachher-Slider: eine Pointer-Logik für Maus & Touch,
  // zusätzlich per Pfeiltasten bedienbar (role="slider").
  var baStage = document.getElementById('baStage');
  if(baStage){
    var baPos = 50;
    var baSet = function(p){
      baPos = Math.max(0, Math.min(100, p));
      baStage.style.setProperty('--pos', baPos + '%');
      baStage.setAttribute('aria-valuenow', String(Math.round(baPos)));
      baStage.setAttribute('aria-valuetext', 'Regler bei ' + Math.round(baPos) + ' %');
    };
    baSet(50);
    var baFromEvent = function(e){
      var r = baStage.getBoundingClientRect();
      return ((e.clientX - r.left) / r.width) * 100;
    };
    var baDrag = false, baRaf = null, baNext = 50;
    var baQueue = function(p){
      baNext = p;
      if(!baRaf) baRaf = requestAnimationFrame(function(){ baSet(baNext); baRaf = null; });
    };
    baStage.addEventListener('pointerdown', function(e){
      baDrag = true;
      if(baStage.setPointerCapture){ try{ baStage.setPointerCapture(e.pointerId); }catch(_){} }
      baQueue(baFromEvent(e));
      e.preventDefault();
    });
    baStage.addEventListener('pointermove', function(e){ if(baDrag) baQueue(baFromEvent(e)); });
    var baEnd = function(){ baDrag = false; };
    baStage.addEventListener('pointerup', baEnd);
    baStage.addEventListener('pointercancel', baEnd);
    baStage.addEventListener('keydown', function(e){
      if(e.key === 'ArrowLeft' || e.key === 'ArrowDown'){ baSet(baPos - 5); e.preventDefault(); }
      else if(e.key === 'ArrowRight' || e.key === 'ArrowUp'){ baSet(baPos + 5); e.preventDefault(); }
      else if(e.key === 'Home'){ baSet(0); e.preventDefault(); }
      else if(e.key === 'End'){ baSet(100); e.preventDefault(); }
    });
    // Beim ersten Sichtbarwerden wippt der Griff einmal kurz, damit klar
    // ist, dass man ziehen kann. Danach hat der Nutzer die Kontrolle.
    if(!reduce && 'IntersectionObserver' in window){
      var baHinted = false;
      var baIo = new IntersectionObserver(function(entries){
        entries.forEach(function(en){
          if(!en.isIntersecting || baHinted) return;
          baHinted = true; baIo.disconnect();
          setTimeout(function(){
            var t0 = null, dur = 1500;
            var swing = function(ts){
              if(baDrag) return;
              if(!t0) t0 = ts;
              var p = Math.min((ts - t0) / dur, 1);
              baSet(50 + Math.sin(p * Math.PI * 2) * 12 * (1 - p));
              if(p < 1) requestAnimationFrame(swing);
            };
            requestAnimationFrame(swing);
          }, 600);
        });
      }, {threshold:.55});
      baIo.observe(baStage);
    }
  }

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

  // FAQ accordion
  document.querySelectorAll('.faq__item').forEach(function(item){
    var q = item.querySelector('.faq__q');
    var a = item.querySelector('.faq__a');
    q.addEventListener('click', function(){
      var open = item.classList.contains('open');
      // close siblings
      document.querySelectorAll('.faq__item.open').forEach(function(other){
        if(other!==item){ other.classList.remove('open'); other.querySelector('.faq__a').style.maxHeight=null; other.querySelector('.faq__q').setAttribute('aria-expanded','false'); }
      });
      if(open){ item.classList.remove('open'); a.style.maxHeight=null; q.setAttribute('aria-expanded','false'); }
      else{ item.classList.add('open'); a.style.maxHeight = a.scrollHeight + 'px'; q.setAttribute('aria-expanded','true'); }
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

  // Sticky Mobile-CTA: nach dem Hero zeigen, im Kontaktbereich ausblenden
  var mcta = document.getElementById('mcta');
  if(mcta){
    var kontakt = document.getElementById('kontakt');
    var kontaktVisible = false;
    if(kontakt){
      var kio = new IntersectionObserver(function(entries){
        entries.forEach(function(e){ kontaktVisible = e.isIntersecting; updateMcta(); });
      }, {rootMargin:'0px 0px -20% 0px'});
      kio.observe(kontakt);
    }
    var updateMcta = function(){
      mcta.classList.toggle('show', window.scrollY > 640 && !kontaktVisible);
    };
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