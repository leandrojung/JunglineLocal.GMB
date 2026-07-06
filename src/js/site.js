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

  // hero entrance: word-by-word rise (skipped bei reduced motion — dann ist alles sofort sichtbar)
  if(!reduce){
    document.documentElement.classList.add('js');
    var h1 = document.getElementById('heroTitle');
    if(h1){
      var wi = 0;
      var splitWords = function(node){
        Array.prototype.slice.call(node.childNodes).forEach(function(child){
          if(child.nodeType === 3){
            var frag = document.createDocumentFragment();
            child.textContent.split(/(\s+)/).forEach(function(part){
              if(!part) return;
              if(/^\s+$/.test(part)){ frag.appendChild(document.createTextNode(part)); return; }
              var w = document.createElement('span');
              w.className = 'w';
              w.style.setProperty('--wd', (wi++ * 0.05 + 0.1).toFixed(2) + 's');
              w.textContent = part;
              frag.appendChild(w);
            });
            node.replaceChild(frag, child);
          } else if(child.nodeType === 1){
            splitWords(child);
          }
        });
      };
      splitWords(h1);
    }
    requestAnimationFrame(function(){ requestAnimationFrame(function(){
      document.querySelector('.hero').classList.add('hero-live');
    }); });
  }

  // stat count-up
  var counts = document.querySelectorAll('.count');
  if(counts.length){
    var fmtCount = function(v, dec){ return dec ? v.toFixed(dec).replace('.', ',') : String(Math.round(v)); };
    var runCount = function(el){
      var target = parseFloat(el.getAttribute('data-count')) || 0;
      var dec = parseInt(el.getAttribute('data-dec'), 10) || 0;
      if(reduce){ el.textContent = fmtCount(target, dec); return; }
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
    counts.forEach(function(el){ cio.observe(el); });
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
    document.querySelectorAll('.ccard, .step, .panel').forEach(function(el){
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

  // ranking climb animation
  var rows = Array.prototype.slice.call(document.querySelectorAll('#results .result'));
  var you = document.getElementById('you');
  var youRank = document.getElementById('youRank');
  function getRow(){ return window.matchMedia('(max-width:560px)').matches ? 77 : 70; }
  function place(order){ var row = getRow(); order.forEach(function(idx, slot){ rows[idx].style.transform = 'translateY(' + (slot*row) + 'px)'; }); }
  var youIdx = rows.indexOf(you);
  var others = rows.map(function(_,i){return i;}).filter(function(i){return i!==youIdx;});
  var bottomOrder = others.concat([youIdx]);
  var topOrder = [youIdx].concat(others);

  if(reduce){
    place(topOrder); you.classList.add('is-top'); youRank.textContent='1';
  } else {
    place(bottomOrder);
    var cycle = function(){
      setTimeout(function(){ place(topOrder); you.classList.add('is-top'); youRank.textContent='1'; }, 1100);
      setTimeout(function(){ place(bottomOrder); you.classList.remove('is-top'); youRank.textContent='15'; }, 6200);
    };
    cycle();
    setInterval(cycle, 8400);
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
  form.addEventListener('submit', function(ev){
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