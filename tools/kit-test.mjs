#!/usr/bin/env node
// SPDX-License-Identifier: MIT
/**
 * Kit im Browser prüfen – aufgerufen von `php bin/console kit:test` (Core\KitTest erzeugt die Testseite).
 *
 *   node tools/kit-test.mjs --page=storage/kit-test/fluid/page.html --base=http://site.localhost:8099/ --out=storage/kit-test/fluid
 *
 * Die Testseite wird unter der Adresse der Website ausgeliefert (gleicher Ursprung: Schriften, Module, Bilder laden wie live),
 * dann hell und dunkel auf Desktop (1280 px) und Telefon (390 px) geprüft, mit reduzierter Bewegung (keine Einblend-Animationen):
 *   - Kontrast: sichtbarer Text auf einfarbigem Grund unter 4,5:1 (große Schrift ab 24 px bzw. 18,66 px fett: unter 3:1).
 *     Text auf Bild oder Verlauf wird nicht gemessen (Grund unbekannt) und nur gezählt.
 *   - Überlauf: Elemente ragen rechts über das Fenster hinaus (außer in eigenen Scroll-Bereichen wie Tabellen).
 *   - Fehler in der Konsole und Seitenfehler.
 * Ergebnis: report.html und report.json im Ausgabeordner, Bilder der betroffenen Abschnitte (Fund rot umrandet) in shots/.
 * Nur das gerenderte Ergebnis zählt – unabhängig davon, ob das Kit eigenes CSS, Tailwind, UIkit oder Bootstrap nutzt.
 * Exit-Code 1 bei Funden.
 */
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const arg = n => (process.argv.find(a => a.startsWith(`--${n}=`)) || '').slice(n.length + 3);
const pageFile = arg('page'), base = arg('base').replace(/\/?$/, '/'), out = arg('out'), kit = arg('kit') || '';
if (!pageFile || !base || !out) { console.error('Aufruf: node tools/kit-test.mjs --page=… --base=… --out=…'); process.exit(2); }
const html = fs.readFileSync(pageFile, 'utf8');
const shots = path.join(out, 'shots');
fs.mkdirSync(shots, { recursive: true });
for (const f of fs.readdirSync(shots)) if (f.endsWith('.png')) fs.unlinkSync(path.join(shots, f));   // nur eigene Bilder des letzten Laufs

const VIEWS = [['desktop', 1280, 900], ['telefon', 390, 844]];
const SCHEMES = ['light', 'dark'];
const MAX_SHOTS = 80;
const url = base + '__kit-test';

// Läuft im Browser: Funde je Abschnitt der Testseite
function inspect() {
  const canvas = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
  const rgba = s => { canvas.clearRect(0, 0, 1, 1); canvas.fillStyle = '#000'; canvas.fillStyle = s; canvas.fillRect(0, 0, 1, 1); const d = canvas.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; };
  const isClear = s => s === 'transparent' || /rgba\([^)]*,\s*0\)$/.test(s) || /\/\s*0\)$/.test(s);
  const lum = c => { const f = v => { v /= 255; return v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }; return .2126 * f(c[0]) + .7152 * f(c[1]) + .0722 * f(c[2]); };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
  // Grund unter einem Element: Hintergrundfarben der Vorfahren übereinanderlegen; Bild/Verlauf → unbekannt (null)
  const ground = el => {
    const layers = [];
    for (let e = el; e && e.nodeType === 1; e = e.parentElement) {
      const s = getComputedStyle(e);
      if (s.backgroundImage !== 'none') return null;
      if (!isClear(s.backgroundColor)) { const c = rgba(s.backgroundColor); layers.push(c); if (c[3] >= 1) break; }
    }
    let g = rgba(getComputedStyle(document.body).backgroundColor);
    if (g[3] < 1) g = [255, 255, 255, 1];
    for (const c of layers.reverse()) g = g.map((v, i) => i < 3 ? v * (1 - c[3]) + c[i] * c[3] : 1);
    return g;
  };
  const visible = el => {
    const r = el.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) return false;
    for (let e = el; e && e.nodeType === 1; e = e.parentElement) {
      const s = getComputedStyle(e);
      if (s.display === 'none' || s.visibility === 'hidden' || +s.opacity === 0) return false;
      if (s.clipPath && s.clipPath !== 'none' && /inset\(50%|circle\(0/.test(s.clipPath)) return false;
    }
    return !el.closest('[hidden],[aria-hidden="true"],template,noscript,svg');
  };
  const label = el => {
    const cls = [...el.classList].slice(0, 2).map(c => '.' + c).join('');
    const t = (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 50);
    return `${el.tagName.toLowerCase()}${cls} „${t}“`;
  };
  const scrolls = el => { for (let e = el.parentElement; e && e !== document.body; e = e.parentElement) { const o = getComputedStyle(e).overflowX; if (o !== 'visible') return true; } return false; };
  const out = [];
  let unmeasured = 0;
  const vw = document.documentElement.clientWidth;
  document.querySelectorAll('section[id^="b-kt-"], [id^="b-kt-"]').forEach(sec => {
    if (sec.parentElement?.closest('[id^="b-kt-"]')) return;
    const id = sec.id.slice(2);
    const seen = new Set();
    // Kontrast: Elemente mit eigenem Text
    sec.querySelectorAll('*').forEach(el => {
      if (![...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim())) return;
      if (!visible(el)) return;
      const s = getComputedStyle(el);
      const g = ground(el);
      if (!g) { unmeasured++; return; }
      const c = rgba(s.color);
      const fg = c.slice(0, 3).map((v, i) => v * c[3] + g[i] * (1 - c[3]));
      const size = parseFloat(s.fontSize), bold = +s.fontWeight >= 700;
      const min = size >= 24 || (size >= 18.66 && bold) ? 3 : 4.5;
      const r = ratio(fg, g);
      if (r < min - 0.01) {
        const key = 'k' + label(el);
        if (seen.has(key)) return; seen.add(key);
        el.setAttribute('data-kt-hit', '');
        out.push({ section: id, kind: 'kontrast', ratio: +r.toFixed(2), min, what: label(el) });
      }
    });
    // Überlauf in die Breite
    for (const el of sec.querySelectorAll('*')) {
      const r = el.getBoundingClientRect();
      if (r.width && r.right > vw + 1 && visible(el) && !scrolls(el)) {
        el.setAttribute('data-kt-hit', '');
        out.push({ section: id, kind: 'überlauf', px: Math.round(r.right - vw), what: label(el) });
        break;
      }
    }
  });
  return { findings: out, unmeasured, pageWidth: document.documentElement.scrollWidth, vw };
}

const browser = await chromium.launch();
const all = [];
let shotCount = 0, unmeasured = 0;
const errors = [];
for (const scheme of SCHEMES) {
  for (const [view, w, h] of VIEWS) {
    const ctx = await browser.newContext({ viewport: { width: w, height: h }, colorScheme: scheme, reducedMotion: 'reduce' });
    const p = await ctx.newPage();
    p.on('console', m => { if (m.type() === 'error' && !m.text().startsWith('Failed to load resource')) errors.push(`${scheme}/${view}: ${m.text().slice(0, 200)}`); });
    p.on('requestfailed', r => errors.push(`Laden fehlgeschlagen (${r.failure()?.errorText || 'Fehler'}): ${r.url().slice(0, 160)}`));
    p.on('pageerror', e => errors.push(`${scheme}/${view}: ${e.message.slice(0, 200)}`));
    await p.route(url, r => r.fulfill({ contentType: 'text/html; charset=utf-8', body: html }));
    await p.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    await p.addStyleTag({ content: '*,*::before,*::after{transition:none!important;animation:none!important}' });
    // Einmal durchscrollen: nachladende Bilder, Einblendungen in Sichtweite
    await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += innerHeight * .8) { scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); } scrollTo(0, 0); });
    await p.waitForTimeout(400);
    const res = await p.evaluate(inspect);
    unmeasured += res.unmeasured;
    if (res.pageWidth > res.vw + 1) all.push({ section: '(Seite)', kind: 'überlauf', px: res.pageWidth - res.vw, what: 'Seite breiter als das Fenster', scheme, view });
    await p.addStyleTag({ content: '[data-kt-hit]{outline:3px solid #E11D48!important;outline-offset:2px}' });
    const bySection = new Map();
    for (const f of res.findings) { f.scheme = scheme; f.view = view; all.push(f); bySection.set(f.section, true); }
    for (const id of bySection.keys()) {
      if (shotCount >= MAX_SHOTS) break;
      const file = `${id}-${scheme}-${view}.png`;
      try { await p.locator(`#b-${id}`).screenshot({ path: path.join(shots, file), timeout: 10000 }); shotCount++; all.filter(f => f.section === id && f.scheme === scheme && f.view === view).forEach(f => f.shot = 'shots/' + file); } catch { /* Abschnitt nicht abbildbar */ }
    }
    await ctx.close();
  }
}
await browser.close();

const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const sections = [...new Set(all.map(f => f.section))];
const report = { kit, created: new Date().toISOString(), findings: all, consoleErrors: [...new Set(errors)], unmeasured };
fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify(report, null, 2));
const rows = sections.map(s => {
  const fs_ = all.filter(f => f.section === s);
  const imgs = [...new Set(fs_.map(f => f.shot).filter(Boolean))];
  return `<section><h2>${esc(s)}</h2><ul>${fs_.map(f => `<li><b>${esc(f.scheme)} · ${esc(f.view)}</b> – ${f.kind === 'kontrast' ? `Kontrast ${f.ratio}:1 (mind. ${f.min}:1)` : `Überlauf ${f.px} px`}: ${esc(f.what)}</li>`).join('')}</ul>${imgs.map(i => `<img src="${esc(i)}" alt="">`).join('')}</section>`;
}).join('');
fs.writeFileSync(path.join(out, 'report.html'), `<!doctype html><meta charset="utf-8"><title>kit:test ${esc(kit)}</title>
<style>body{font:15px/1.5 system-ui,sans-serif;margin:2rem auto;max-width:72rem;padding:0 1rem;color:#1a1a1a}h2{font-size:1rem;margin:2rem 0 .5rem}img{display:block;max-width:100%;margin:.5rem 0;border:1px solid #ddd}li{margin:.2rem 0}.ok{color:#166534}</style>
<h1>kit:test – ${esc(kit)}</h1><p>${all.length ? `${all.length} Funde in ${sections.length} Abschnitten` : '<span class="ok">Keine Funde.</span>'} · hell/dunkel · Desktop 1280 px / Telefon 390 px · ${unmeasured} Texte auf Bild oder Verlauf nicht gemessen.</p>
${report.consoleErrors.length ? `<h2>Konsole</h2><ul>${report.consoleErrors.map(e => `<li>${esc(e)}</li>`).join('')}</ul>` : ''}${rows}`);

console.log(`  ${all.length} Funde in ${sections.length} Abschnitten, ${report.consoleErrors.length} Konsolenfehler, ${unmeasured} Texte auf Bild/Verlauf nicht gemessen`);
for (const f of all.slice(0, 40)) console.log(`  ${f.section} [${f.scheme}/${f.view}] ${f.kind === 'kontrast' ? `Kontrast ${f.ratio}:1 < ${f.min}` : `Überlauf ${f.px} px`} – ${f.what}`);
if (all.length > 40) console.log(`  … und ${all.length - 40} weitere`);
for (const e of report.consoleErrors.slice(0, 10)) console.log(`  Konsole: ${e}`);
console.log(`  Bericht: ${path.join(out, 'report.html')}`);
process.exit(all.length || report.consoleErrors.length ? 1 : 0);
