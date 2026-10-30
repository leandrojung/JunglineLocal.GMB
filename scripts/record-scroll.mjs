/* Nimmt die Startseite einer Kundenseite als Scroll-Video für die
   Arbeitsproben auf /webdesign/ auf (data-video in webdesign/index.html).

   Ablauf: Hero kurz stehen lassen (damit die Einstiegs-Animation zu sehen
   ist), dann gleichmäßig bis ganz unten scrollen und unten kurz halten.

   Aufgenommen wird Bild für Bild mit angehaltener Uhr, nicht in Echtzeit:
   Vor jedem Bild rückt eine künstliche Uhr in der Seite genau 1/fps Sekunde
   vor (Timer, requestAnimationFrame, Date, performance.now, alle CSS- und
   Web-Animationen, Videos). Erst dann entsteht das Bildschirmfoto. So
   ruckelt nichts, auch wenn der Rechner oder die Seite langsam ist; die
   Aufnahme dauert dann nur länger.

   Danach kodiert ffmpeg eine .mp4 (H.264). Die spielen alle aktuellen
   Browser ab und sie ist hier kleiner als eine VP9-WebM.

   Voraussetzungen im Projektordner:
     npm install --no-save playwright
     npx playwright install chromium
   und ffmpeg (Mac: brew install ffmpeg).

   Cookie-Banner: Vor der Aufnahme wartet das Skript kurz, klickt im Banner
   auf „Ablehnen“ (oder ähnlich, notfalls „Akzeptieren“) und lädt die Seite
   neu. Die Wahl ist dann gespeichert, der Banner bleibt weg und die
   Einstiegs-Animation läuft trotzdem von vorn. Findet es keinen passenden
   Knopf, blendet --verstecken=<CSS-Selektor> Elemente einfach aus.

   Aufruf aus dem Projektordner:
     node scripts/record-scroll.mjs <url> <name> [halteOben] [pxProSek] [halteUnten] [fps] [--verstecken=<selektor>]
   Beispiel:
     node scripts/record-scroll.mjs https://www.rowi-maschinenservice.de/ rowi
     node scripts/record-scroll.mjs https://example.de/ kunde --verstecken=".cookie-banner,#chat"
   Ergebnis: public/referenzen/<name>/<name>-scroll.mp4 */
import { mkdirSync, writeFileSync, rmSync, mkdtempSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createRequire } from 'node:module';

const flags = process.argv.slice(2).filter(a => a.startsWith('--'));
const [url, name, holdTop = '1800', speed = '800', holdEnd = '1200', fpsArg = '60'] = process.argv.slice(2).filter(a => !a.startsWith('--'));
const verstecken = (flags.find(f => f.startsWith('--verstecken=')) || '').slice('--verstecken='.length);
if(!url || !name){
  console.error('Aufruf: node scripts/record-scroll.mjs <url> <name> [halteOben] [pxProSek] [halteUnten] [fps] [--verstecken=<selektor>]');
  process.exit(1);
}
const fps = +fpsArg;

// Playwright aus dem Projekt oder global installiert
let chromium;
try { ({ chromium } = await import('playwright')); }
catch {
  const globalRoot = spawnSync('npm', ['root', '-g'], { encoding: 'utf8' }).stdout.trim();
  ({ chromium } = createRequire(import.meta.url)(join(globalRoot, 'playwright')));
}

/* Künstliche Uhr, läuft vor jedem Skript der Seite. Bis zum ersten step()
   steht die Zeit still, die Seite wartet also mit allen Animationen, bis
   die Aufnahme beginnt. */
const uhr = () => {
  const nativeRAF = window.requestAnimationFrame.bind(window);
  const nativeTimeout = window.setTimeout.bind(window);
  const NativeDate = Date;
  const basis = NativeDate.now();
  let jetzt = 0, naechsteId = 1;
  let timer = [], rafs = [];
  const bekannt = new WeakSet();

  performance.now = () => jetzt;
  window.Date = class extends NativeDate {
    constructor(...a){ a.length ? super(...a) : super(basis + jetzt); }
    static now(){ return basis + jetzt; }
  };
  window.setTimeout = (fn, ms = 0, ...args) => {
    const id = naechsteId++;
    timer.push({ id, faellig: jetzt + Math.max(0, +ms || 0), fn, args });
    return id;
  };
  window.setInterval = (fn, ms = 0, ...args) => {
    const id = naechsteId++;
    timer.push({ id, faellig: jetzt + Math.max(1, +ms || 0), fn, args, takt: Math.max(1, +ms || 0) });
    return id;
  };
  window.clearTimeout = window.clearInterval = id => { timer = timer.filter(t => t.id !== id); };
  window.requestAnimationFrame = fn => { const id = naechsteId++; rafs.push({ id, fn }); return id; };
  window.cancelAnimationFrame = id => { rafs = rafs.filter(r => r.id !== id); };

  const ausfuehren = fn => { try { typeof fn === 'function' ? fn() : (0, eval)(fn); } catch (e) { console.error(e); } };

  // CSS-Animationen, die die Seite selbst per animation-play-state anhält
  // (z. B. außerhalb des Bildes), bleiben stehen.
  const cssPausiert = a => {
    if(!a.animationName || !a.effect || !a.effect.target) return false;
    const cs = getComputedStyle(a.effect.target, a.effect.pseudoElement || null);
    const namen = cs.animationName.split(',').map(s => s.trim());
    const status = cs.animationPlayState.split(',').map(s => s.trim());
    const i = namen.indexOf(a.animationName);
    return i > -1 && status[i % status.length] === 'paused';
  };

  window.__uhr = {
    nativeRAF,
    async step(dt){
      const ziel = jetzt + dt;
      // Timer der Reihe nach, auch solche, die während des Schritts neu dazukommen
      for(let n = 0; n < 5000; n++){
        let t = null;
        for(const x of timer) if(x.faellig <= ziel && (!t || x.faellig < t.faellig)) t = x;
        if(!t) break;
        jetzt = Math.max(jetzt, t.faellig);
        if(t.takt) t.faellig += t.takt; else timer = timer.filter(x => x !== t);
        ausfuehren(() => typeof t.fn === 'function' ? t.fn(...t.args) : (0, eval)(t.fn));
      }
      jetzt = ziel;
      const faellig = rafs; rafs = [];
      for(const r of faellig) ausfuehren(() => r.fn(jetzt));
      // CSS-Übergänge, CSS-Animationen und element.animate(): anhalten und
      // von Hand vorspulen. Neue starten bei 0. Scroll-gesteuerte nicht.
      for(const a of document.getAnimations()){
        if(a.timeline !== document.timeline) continue;
        if(!bekannt.has(a)){ bekannt.add(a); a.pause(); a.currentTime = 0; continue; }
        if(a.playState !== 'paused' || cssPausiert(a)) continue;
        a.currentTime = (a.currentTime || 0) + dt * (a.playbackRate || 1);
      }
      // Videos (z. B. im Hero) mitspulen und warten, bis das Bild steht
      const gesucht = [];
      for(const v of document.querySelectorAll('video')){
        if(v.__uhr === undefined){ v.__uhr = v.autoplay || !v.paused; }
        if(!v.__uhr || !v.duration) continue;
        v.pause();
        let t = v.currentTime + dt / 1000;
        if(t >= v.duration) t = v.loop ? t % v.duration : v.duration;
        v.currentTime = t;
        gesucht.push(new Promise(r => v.addEventListener('seeked', r, { once: true })));
      }
      if(gesucht.length) await Promise.race([Promise.all(gesucht), new Promise(r => nativeTimeout(r, 300))]);
      // Zwei echte Bilder abwarten: Stil, Layout und IntersectionObserver
      await new Promise(r => nativeRAF(() => nativeRAF(r)));
    },
  };
};

const tmp = mkdtempSync(join(tmpdir(), 'scroll-'));
const browser = await chromium.launch({ args: ['--force-device-scale-factor=1', '--hide-scrollbars'] });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'no-preference' });
await context.addInitScript(uhr);
const page = await context.newPage();
page.on('pageerror', e => console.warn('Fehler in der Seite:', e.message));

// Künstliche Uhr ohne Aufnahme vorspulen (z. B. bis ein Banner erscheint)
const vorspulen = async ms => {
  for(let t = 0; t < ms; t += 50) await page.evaluate(() => window.__uhr.step(50));
};

// Cookie-Banner wegklicken: lieber ablehnen (dann lädt auch nichts Fremdes
// wie Google Maps nach), nur wenn es das nicht gibt, akzeptieren.
const ABLEHNEN = /^\s*((alle\s+)?ablehnen|nur\s+(technisch\s+)?(notwendige|erforderliche)(\s+cookies)?(\s+(akzeptieren|zulassen|erlauben))?|(reject|decline)(\s+all)?)\s*$/i;
const ANNEHMEN = /^\s*((alle\s+)?(akzeptieren|zustimmen|zulassen|annehmen)|alle\s+cookies\s+(akzeptieren|zulassen)|accept(\s+all)?|ok|verstanden)\s*$/i;
const bannerWeg = async () => {
  const knoepfe = page.locator('button, a, [role=button]');
  for(const muster of [ABLEHNEN, ANNEHMEN]){
    for(const k of await knoepfe.filter({ hasText: muster }).all()){
      if(!(await k.isVisible().catch(() => false))) continue;
      const text = (await k.innerText()).trim();
      await k.click({ timeout: 3000 }).catch(() => {});
      return text;
    }
  }
  return null;
};

await page.goto(url, { waitUntil: 'load' });
await vorspulen(3000);
const geklickt = await bannerWeg();
console.log(geklickt ? `Cookie-Banner: „${geklickt}“ geklickt` : 'Kein Cookie-Banner gefunden');
await vorspulen(1500);
// Neu laden: Uhr und Einstiegs-Animation starten von vorn, die Cookie-Wahl bleibt
await page.reload({ waitUntil: 'load' });
await page.addStyleTag({ content: 'html{scroll-behavior:auto!important}' + (verstecken ? `${verstecken}{display:none!important}` : '') });
// Alle Bilder vorab laden, damit beim Scrollen nichts nachploppt
await page.evaluate(async () => {
  document.querySelectorAll('img[loading=lazy]').forEach(i => { i.loading = 'eager'; });
  await Promise.all([...document.images].map(i => i.complete ? 0 : new Promise(r => { i.onload = i.onerror = r; })));
  await document.fonts.ready;
  window.scrollTo(0, 0);
});

const cdp = await context.newCDPSession(page);
const max = await page.evaluate(() => document.documentElement.scrollHeight - innerHeight);
const scrollMs = max / +speed * 1000;
const gesamt = +holdTop + scrollMs + +holdEnd;
const anzahl = Math.ceil(gesamt / 1000 * fps);
// Weich an- und auslaufen, dazwischen gleichmäßig
const ease = x => x < .5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2;
const pos = k => k < .08 ? ease(k / .16) * .08 : k > .92 ? 1 - ease((1 - k) / .16) * .08 : (k - .04) / .92;

for(let i = 0; i < anzahl; i++){
  const t = i * 1000 / fps;
  const k = Math.max(0, Math.min(1, (t - +holdTop) / scrollMs));
  const y = Math.round(Math.max(0, Math.min(1, pos(k))) * max);
  await page.evaluate(({ y, dt, erstes }) => {
    window.scrollTo(0, y);
    return window.__uhr.step(erstes ? 0 : dt);
  }, { y, dt: 1000 / fps, erstes: i === 0 });
  const { data } = await cdp.send('Page.captureScreenshot', { format: 'jpeg', quality: 92 });
  writeFileSync(join(tmp, `f${String(i).padStart(5, '0')}.jpg`), Buffer.from(data, 'base64'));
  if(i % fps === 0) process.stdout.write(`\r${name}: ${Math.round(i / anzahl * 100)} %`);
}
await browser.close();
process.stdout.write('\n');

const outDir = join('public', 'referenzen', name);
mkdirSync(outDir, { recursive: true });
const ffmpeg = args => {
  const r = spawnSync('ffmpeg', ['-loglevel', 'error', '-y', '-framerate', String(fps), '-i', join(tmp, 'f%05d.jpg'),
    '-vf', 'scale=1200:750:flags=lanczos:in_range=full:out_range=tv,format=yuv420p', '-color_range', 'tv', '-an', ...args], { stdio: 'inherit' });
  if(r.status !== 0) process.exit(r.status || 1);
};
ffmpeg(['-c:v', 'libx264', '-preset', 'slow', '-crf', '27', '-profile:v', 'high', '-movflags', '+faststart', join(outDir, `${name}-scroll.mp4`)]);
rmSync(tmp, { recursive: true, force: true });
console.log(`${name}: ${anzahl} Bilder, ${(gesamt / 1000).toFixed(1)} s bei ${fps} fps, gespeichert in ${outDir}`);
