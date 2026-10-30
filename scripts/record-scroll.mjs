/* Nimmt die Startseite einer Kundenseite als Scroll-Video für die
   Arbeitsproben auf /webdesign/ auf (data-video in webdesign/index.html).

   Ablauf: Seite laden, Hero kurz stehen lassen (damit die Einstiegs-
   Animation zu sehen ist), dann gleichmäßig bis ganz unten scrollen und
   unten kurz halten. Die Bilder kommen per CDP-Screencast aus Chromium, so
   laufen alle Animationen der Seite in echter Geschwindigkeit mit.
   Danach kodiert ffmpeg eine .webm (VP9) und eine .mp4 (H.264, für Safari).

   Voraussetzungen: Playwright mit Chromium (npx playwright install chromium)
   und ffmpeg im PATH. Die Kundenseite muss erreichbar sein, z. B. live oder
   lokal per "npm run dev" bzw. "npm run preview" im Kunden-Repo.

   Aufruf:
     node scripts/record-scroll.mjs <url> <name> [halteOben] [pxProSek] [halteUnten]
   Beispiel:
     node scripts/record-scroll.mjs http://localhost:4173/ gloria 1800 800 1200
   Ergebnis: public/referenzen/<name>/<name>-scroll.webm und .mp4 */
import { mkdirSync, writeFileSync, rmSync, mkdtempSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createRequire } from 'node:module';

const [,, url, name, holdTop = '1800', speed = '800', holdEnd = '1200'] = process.argv;
if(!url || !name){
  console.error('Aufruf: node scripts/record-scroll.mjs <url> <name> [halteOben] [pxProSek] [halteUnten]');
  process.exit(1);
}

// Playwright aus dem Projekt oder global installiert
let chromium;
try { ({ chromium } = await import('playwright')); }
catch {
  const globalRoot = spawnSync('npm', ['root', '-g'], { encoding: 'utf8' }).stdout.trim();
  ({ chromium } = createRequire(import.meta.url)(join(globalRoot, 'playwright')));
}

const tmp = mkdtempSync(join(tmpdir(), 'scroll-'));
const browser = await chromium.launch({ args: ['--force-device-scale-factor=1', '--hide-scrollbars'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, reducedMotion: 'no-preference' });
const vorbereiten = async () => {
  await page.addStyleTag({ content: 'html{scroll-behavior:auto!important}' });
  await page.evaluate(() => document.querySelectorAll('img[loading=lazy]').forEach(i => { i.loading = 'eager'; }));
};

// Erster Besuch füllt den Cache, damit beim Aufnehmen keine Bilder nachploppen
await page.goto(url, { waitUntil: 'load' });
await vorbereiten();
await page.evaluate(() => Promise.all([...document.images].map(i => i.complete ? 0 : new Promise(r => { i.onload = i.onerror = r; }))));

const cdp = await page.context().newCDPSession(page);
const frames = [];
cdp.on('Page.screencastFrame', f => {
  frames.push({ t: f.metadata.timestamp, d: f.data });
  cdp.send('Page.screencastFrameAck', { sessionId: f.sessionId }).catch(() => {});
});
await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: 1440, maxHeight: 900, everyNthFrame: 1 });
// Neu laden, damit die Hero-Animation von vorn läuft
await page.reload({ waitUntil: 'load' });
await vorbereiten();
await page.evaluate(async ({ holdTop, speed, holdEnd }) => {
  const wait = ms => new Promise(r => setTimeout(r, ms));
  await wait(holdTop);
  const max = document.documentElement.scrollHeight - innerHeight;
  const dur = max / speed * 1000, t0 = performance.now();
  // Weich an- und auslaufen, dazwischen gleichmäßig
  const ease = x => x < .5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2;
  const pos = k => k < .08 ? ease(k / .16) * .08 : k > .92 ? 1 - ease((1 - k) / .16) * .08 : (k - .04) / .92;
  await new Promise(r => {
    const step = now => {
      const k = Math.min(1, (now - t0) / dur);
      window.scrollTo(0, Math.round(Math.max(0, Math.min(1, pos(k))) * max));
      k < 1 ? requestAnimationFrame(step) : r();
    };
    requestAnimationFrame(step);
  });
  await wait(holdEnd);
}, { holdTop: +holdTop, speed: +speed, holdEnd: +holdEnd });
await cdp.send('Page.stopScreencast');
await browser.close();

// Bilder mit ihren echten Abständen an ffmpeg (concat-Liste mit duration)
let list = '';
frames.forEach((f, i) => {
  const n = `f${String(i).padStart(5, '0')}.jpg`;
  writeFileSync(join(tmp, n), Buffer.from(f.d, 'base64'));
  const dt = i < frames.length - 1 ? frames[i + 1].t - f.t : 1 / 30;
  list += `file '${n}'\nduration ${dt.toFixed(4)}\n`;
});
list += `file 'f${String(frames.length - 1).padStart(5, '0')}.jpg'\n`;
writeFileSync(join(tmp, 'list.txt'), list);

const outDir = join('public', 'referenzen', name);
mkdirSync(outDir, { recursive: true });
const vf = 'fps=30,scale=1200:750:flags=lanczos,format=yuv420p';
const ffmpeg = args => {
  const r = spawnSync('ffmpeg', ['-loglevel', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', join(tmp, 'list.txt'), '-vf', vf, '-an', ...args], { stdio: 'inherit' });
  if(r.status !== 0) process.exit(r.status || 1);
};
ffmpeg(['-c:v', 'libvpx-vp9', '-crf', '38', '-b:v', '0', '-row-mt', '1', '-deadline', 'good', '-cpu-used', '2', join(outDir, `${name}-scroll.webm`)]);
ffmpeg(['-c:v', 'libx264', '-preset', 'slow', '-crf', '27', '-profile:v', 'high', '-movflags', '+faststart', join(outDir, `${name}-scroll.mp4`)]);
rmSync(tmp, { recursive: true, force: true });
console.log(`${name}: ${frames.length} Bilder, ${(frames.at(-1).t - frames[0].t).toFixed(1)} s, gespeichert in ${outDir}`);
