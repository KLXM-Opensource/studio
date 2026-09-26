#!/usr/bin/env node
/**
 * Tutorial-Videos aufnehmen (Handbuch & Hilfe › Tutorials).
 *
 *   pnpm --dir tools tutorials                 alle Videos neu aufnehmen
 *   node tools/tutorials/record.mjs r-seite    nur einzelne (Kurzname, auch mehrere; Präfix genügt: „r-“ = ganze Redaktion)
 *   node tools/tutorials/record.mjs --list     Liste der Aufnahmen
 *   Optionen: --raw (Rohvideo behalten), --no-transcode, --headed,
 *             --voice (optional, nur Entwicklung: Sprecher per Piper/Chatterbox, siehe speech.mjs; Standard: ohne Ton),
 *             --en-webm (mit --voice: englische Sprecherfassung zusätzlich als WebM)
 *
 * Standard: OHNE TON. Jeder Schritt (t.step) ist ein Untertitel (Deutsch + Englisch); die Aufnahme wartet die Lesezeit ab
 * (etwa 15 Zeichen je Sekunde des längeren Textes, mindestens 2,2 s, höchstens 6 s), dann folgen die Aktionen des Schritts.
 * Untertitel-Zeitstempel = Zeitpunkt des Schritts in der Aufnahme – Bild und Untertitel sind damit deckungsgleich.
 * Die Videodateien haben keine Tonspur (-an); Englisch nutzt dieselbe Datei mit {slug}.en.vtt.
 * Mit --voice (Werkzeug für später): Piper TTS lokal (speech.mjs + narrate.py), Sprecher Deutsch/Englisch, {slug}.en.mp4.
 *
 * Voraussetzungen: Playwright (devDependency in tools/package.json, `pnpm --dir tools exec playwright install chromium`),
 * ffmpeg (FFMPEG=/pfad/ffmpeg, Standard: ffmpeg im PATH bzw. /opt/homebrew/bin/ffmpeg) und eine TESTKOPIE mit Beispielinhalten.
 * Zugänge stehen NICHT im Repository, sondern in tools/tutorials/accounts.local.json (Vorlage: accounts.example.json)
 * oder in der Datei aus TUT_ACCOUNTS. Die Aufnahmen legen Inhalte an und räumen sie danach wieder ab (cleanup je Ablauf).
 *
 * Ausgabe: NICHT ins CMS (die Videos werden nicht mit ausgeliefert), sondern in die Produkt-Website studio.klxm.de:
 *   TUT_OUT=/pfad/zur/website/site-tools/tutorials (Standard: ../klxm-studio-website/site-tools/tutorials neben dem Projektordner,
 *   also /Users/…/Desktop/klxm-studio-website/site-tools/tutorials). Danach im CMS `php bin/console tutorials:export` (schreibt
 *   tutorials.json in denselben Ordner) und in der Website `php bin/console klxm:seed` (Medien + Seiten /tutorials/{kurzname}).
 * Ergebnis je Kurzname in TUT_OUT:
 *   {slug}.mp4 (H.264, CRF 28, faststart, ohne Ton) · {slug}.webm (VP9, ohne Ton) · {slug}.jpg (Vorschaubild)
 *   {slug}.de.vtt / {slug}.en.vtt (Untertitel aus den Schritten, Zeitstempel aus der Aufnahme)
 *   videos.json (Dauer, Größe, Varianten; oberste Ebene "audio": false = alle ohne Ton – liest tutorials:export)
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { flows } from './flows.mjs';
const { Narrator, buildTrack, VOICES } = process.argv.includes('--voice') ? await import('./speech.mjs') : {};

const DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(DIR, '../..');
// Ausgabe: TUT_OUT oder die Website-Installation neben dem Projektordner (Videos gehören zu studio.klxm.de, nicht ins CMS)
const OUT = path.resolve(process.env.TUT_OUT || path.join(ROOT, '../../klxm-studio-website/site-tools/tutorials'));
const RAW = path.join(DIR, '.raw');
const args = process.argv.slice(2);
const flag = (f) => args.includes(f);
const wanted = args.filter((a) => !a.startsWith('--'));
const FFMPEG = process.env.FFMPEG || (fs.existsSync('/opt/homebrew/bin/ffmpeg') ? '/opt/homebrew/bin/ffmpeg' : 'ffmpeg');
const FFPROBE = FFMPEG.replace(/ffmpeg$/, 'ffprobe');

if (flag('--list')) {
  for (const f of flows) console.log(`${f.slug.padEnd(28)} ${f.account.padEnd(8)} ${f.mobile ? 'mobil ' : 'desktop'}  ${f.title}${f.disabled ? `  (ausgesetzt: ${f.disabled})` : ''}`);
  process.exit(0);
}

const accFile = process.env.TUT_ACCOUNTS || path.join(DIR, 'accounts.local.json');
if (!fs.existsSync(accFile)) {
  console.error(`Zugänge fehlen: ${path.relative(ROOT, accFile)} (Vorlage: tools/tutorials/accounts.example.json)`);
  process.exit(1);
}
const ACCOUNTS = JSON.parse(fs.readFileSync(accFile, 'utf8'));

// „disabled“: nur aufnehmen, wenn ausdrücklich mit vollem Kurznamen genannt
const selected = flows.filter((f) => wanted.length ? wanted.some((w) => f.slug === w || (!f.disabled && f.slug.startsWith(w))) : !f.disabled);
if (!selected.length) { console.error('Keine passende Aufnahme. --list zeigt alle.'); process.exit(1); }
fs.mkdirSync(OUT, { recursive: true });
fs.mkdirSync(RAW, { recursive: true });

// ---------------------------------------------------------------- Einblendungen (nur in der Aufnahme)
// Mauszeiger, Klick-Kreis, Tastenkürzel und Markierungsrahmen. Läuft nur im obersten Fenster; Position setzt das Skript.
const OVERLAY = () => {
  if (window !== window.top) return;
  // Passkey-Autofill (conditional mediation) aus: Headless-Chromium bricht die Anfrage ab, die Anmeldeseite zeigt dann einen Fehler
  try { if (window.PublicKeyCredential) PublicKeyCredential.isConditionalMediationAvailable = () => Promise.resolve(false); } catch { /* egal */ }
  const make = () => {
    if (document.getElementById('__tut-cursor') || !document.body) return;
    const s = (el, css) => { Object.assign(el.style, css); return el; };
    const layer = s(document.createElement('div'), { position: 'fixed', inset: '0', pointerEvents: 'none', zIndex: '2147483647' });
    layer.id = '__tut-layer';
    const touch = matchMedia('(pointer: coarse)').matches;
    const cur = s(document.createElement('div'), touch
      ? { position: 'absolute', width: '34px', height: '34px', marginLeft: '-17px', marginTop: '-17px', borderRadius: '50%', background: 'rgba(255,196,0,.45)', boxShadow: '0 0 0 2px rgba(20,20,20,.55)', transition: 'opacity .2s', opacity: '0' }
      : { position: 'absolute', width: '22px', height: '22px', marginLeft: '-3px', marginTop: '-2px', transition: 'opacity .2s' });
    cur.id = '__tut-cursor';
    if (!touch) cur.innerHTML = '<svg width="22" height="22" viewBox="0 0 22 22"><path d="M3 2l14 8.2-6.1 1.3 3.7 7.1-2.7 1.4-3.7-7.2L3 17.2z" fill="#111" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>';
    layer.append(cur);
    document.body.append(layer);
    const pos = window.__tutPos || [-100, -100];
    s(cur, { left: pos[0] + 'px', top: pos[1] + 'px' });
    window.__tutCursor = (x, y, show = true) => { window.__tutPos = [x, y]; s(cur, { left: x + 'px', top: y + 'px', opacity: touch && !show ? '0' : '1' }); };
    window.__tutClick = (x, y) => {
      const r = s(document.createElement('div'), { position: 'absolute', left: x + 'px', top: y + 'px', width: '44px', height: '44px', marginLeft: '-22px', marginTop: '-22px', borderRadius: '50%', border: '3px solid #ffb300', background: 'rgba(255,179,0,.18)', transform: 'scale(.3)', opacity: '1', transition: 'transform .45s ease-out, opacity .6s ease-out' });
      layer.append(r);
      requestAnimationFrame(() => requestAnimationFrame(() => s(r, { transform: 'scale(1.25)', opacity: '0' })));
      setTimeout(() => r.remove(), 800);
    };
    window.__tutKeys = (label) => {
      const k = s(document.createElement('div'), { position: 'absolute', left: '50%', bottom: '64px', transform: 'translateX(-50%)', padding: '10px 18px', borderRadius: '12px', background: 'rgba(17,17,17,.86)', color: '#fff', font: '600 22px/1.2 system-ui, sans-serif', letterSpacing: '.04em', transition: 'opacity .3s' });
      k.textContent = label;
      layer.append(k);
      setTimeout(() => s(k, { opacity: '0' }), 1300);
      setTimeout(() => k.remove(), 1700);
    };
    // Rückfragen (confirm) in der Aufnahme sichtbar machen und bestätigen – der Browser zeichnet echte Dialoge nicht auf
    window.confirm = (msg) => {
      const box = s(document.createElement('div'), { position: 'absolute', left: '50%', top: '22%', transform: 'translateX(-50%)', width: 'min(420px, 86vw)', padding: '18px 20px', borderRadius: '14px', background: '#fff', color: '#111', boxShadow: '0 20px 60px rgba(0,0,0,.35), 0 0 0 1px rgba(0,0,0,.1)', font: '15px/1.45 system-ui, sans-serif', transition: 'opacity .3s' });
      const txt = document.createElement('p'); txt.textContent = String(msg); s(txt, { margin: '0 0 14px', whiteSpace: 'pre-line' });
      const row = s(document.createElement('div'), { display: 'flex', justifyContent: 'flex-end', gap: '8px' });
      const b1 = s(document.createElement('span'), { padding: '6px 14px', borderRadius: '8px', background: '#eee' }); b1.textContent = 'Abbrechen';
      const b2 = s(document.createElement('span'), { padding: '6px 14px', borderRadius: '8px', background: '#1a5cd6', color: '#fff', boxShadow: '0 0 0 3px #ffb300' }); b2.textContent = 'OK';
      row.append(b1, b2); box.append(txt, row); layer.append(box);
      setTimeout(() => s(box, { opacity: '0' }), 1900);
      setTimeout(() => box.remove(), 2300);
      return true;
    };
    window.__tutRing = (x, y, w, h, ms) => {
      const r = s(document.createElement('div'), { position: 'absolute', left: x - 6 + 'px', top: y - 6 + 'px', width: w + 12 + 'px', height: h + 12 + 'px', borderRadius: '10px', boxShadow: '0 0 0 3px #ffb300, 0 0 0 9999px rgba(0,0,0,.18)', transition: 'opacity .3s' });
      layer.append(r);
      setTimeout(() => s(r, { opacity: '0' }), ms);
      setTimeout(() => r.remove(), ms + 400);
    };
  };
  // Sicherheitsnetz: sichtbare Tokens/Schlüssel (cms_…, lange Hex-Werte) unscharf zeichnen
  const SECRET = /\b(cms_[0-9a-f]{16,}|[0-9a-f]{24,}|[A-Za-z0-9+\/]{40,}={0,2})\b/;
  const scrub = (root) => {
    const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    for (let n = w.nextNode(); n; n = w.nextNode()) if (SECRET.test(n.nodeValue) && n.parentElement) n.parentElement.style.filter = 'blur(7px)';
    root.querySelectorAll?.('input:not([type=password]), textarea').forEach((i) => { if (SECRET.test(i.value)) i.style.filter = 'blur(7px)'; });
  };
  const watch = () => { scrub(document.body); new MutationObserver((ms) => ms.forEach((m) => m.target && scrub(m.target.nodeType === 1 ? m.target : m.target.parentElement || document.body))).observe(document.body, { subtree: true, childList: true, characterData: true }); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => { make(); watch(); }); else { make(); watch(); }
};

// ---------------------------------------------------------------- Werkzeuge für die Abläufe
class Tut {
  constructor(page, acc, mobile, narrator = null) {
    this.page = page; this.acc = acc; this.mobile = mobile; this.narrator = narrator;
    this.base = acc.url.replace(/\/$/, '');
    this.t0 = Date.now(); this.start = null; this.cues = []; this.cuts = []; this.posterAt = null;
    this.x = mobile ? 195 : 640; this.y = mobile ? 420 : 400;
    page.on('load', () => this.syncCursor().catch(() => {}));
  }
  now() { return (Date.now() - this.t0) / 1000; }
  url(p) { return /^https?:/.test(p) ? p : this.base + p; }
  async syncCursor(show = false) { await this.page.evaluate(([x, y, s]) => window.__tutCursor?.(x, y, s), [this.x, this.y, show]); }
  /** Aufnahme beginnt hier (alles davor wird abgeschnitten) */
  begin() { this.start = this.now(); }
  /** Neuer Schritt = neue Untertitelzeile (de, en). Wartet die Lesezeit ab – etwa 15 Zeichen je Sekunde des längeren
   *  Textes, mindestens 2,2 s, höchstens 6 s (pause = längere Mindestzeit) –, erst dann folgen die Aktionen des Schritts.
   *  Mit --voice: zusätzlich, bis beide Sprachen ausgesprochen hätten (+ 0,4 s). */
  async step(de, en, pause) {
    if (this.start === null) this.begin();
    en = en || de;
    const voice = this.narrator ? { de: await this.narrator.say('de', de), en: await this.narrator.say('en', en) } : null;
    this.cues.push({ t: this.now(), de, en, voice });
    const read = Math.min(6000, Math.max(2200, Math.max(de.length, en.length) / 15 * 1000, pause || 0));
    const speak = voice ? Math.max(voice.de.dur, voice.en.dur) * 1000 + 400 : 0;
    await this.wait(Math.max(read, speak));
  }
  poster() { this.posterAt = this.now(); }
  /** Lange Wartezeit (z. B. KI) im Video kürzen: fn läuft, der Mittelteil wird beim Umwandeln herausgeschnitten */
  async cut(fn, keep = 1.2) {
    const a = this.now();
    await fn();
    const b = this.now();
    if (b - a > keep * 2 + 1) {
      this.cuts.push([a + keep, b - keep * 0.5]);
      await this.page.evaluate(() => window.__tutKeys?.('⏩ Wartezeit gekürzt')).catch(() => {});
    }
    await this.wait(900);
  }
  async wait(ms) { await this.page.waitForTimeout(ms); }
  async goto(p, opts = {}) { await this.page.goto(this.url(p), { waitUntil: 'load', ...opts }); await this.syncCursor(); await this.wait(500); }
  loc(target) { return typeof target === 'string' ? this.page.locator(target).first() : target; }
  async moveTo(target, { dx = 0, dy = 0 } = {}) {
    const el = this.loc(target);
    await el.waitFor({ state: 'visible', timeout: 15000 });
    await el.scrollIntoViewIfNeeded().catch(() => {});
    await this.wait(150);
    const b = await el.boundingBox();
    if (!b) throw new Error('Kein Element für ' + target);
    const tx = Math.round(b.x + Math.min(b.width / 2, 60) + dx), ty = Math.round(b.y + b.height / 2 + dy);
    if (this.mobile) { this.x = tx; this.y = ty; await this.syncCursor(true); await this.wait(200); return; }
    const steps = Math.max(8, Math.min(30, Math.round(Math.hypot(tx - this.x, ty - this.y) / 25)));
    for (let i = 1; i <= steps; i++) {
      const k = i / steps, e = k < .5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2;
      const x = this.x + (tx - this.x) * e, y = this.y + (ty - this.y) * e;
      await this.page.mouse.move(x, y);
      await this.page.evaluate(([x, y]) => window.__tutCursor?.(x, y), [x, y]);
      await this.wait(14);
    }
    this.x = tx; this.y = ty;
  }
  async click(target, opts = {}) {
    await this.moveTo(target, opts);
    await this.wait(opts.before ?? 250);
    await this.page.evaluate(([x, y]) => window.__tutClick?.(x, y), [this.x, this.y]);
    if (this.mobile) await this.page.touchscreen.tap(this.x, this.y);
    else await this.page.mouse.click(this.x, this.y);
    await this.wait(opts.after ?? 600);
    if (this.mobile) await this.syncCursor(false).catch(() => {});
  }
  async hover(target, ms = 600) { await this.moveTo(target); await this.wait(ms); }
  /** Langsam tippen (sichtbar); Passwortfelder zeigen nur Punkte */
  async type(target, text, { delay = 55, clear = false } = {}) {
    await this.click(target, { after: 200 });
    if (clear) { await this.page.keyboard.press(process.platform === 'darwin' ? 'Meta+A' : 'Control+A'); await this.page.keyboard.press('Backspace'); }
    await this.page.keyboard.type(text, { delay });
    await this.wait(300);
  }
  async select(target, value) { await this.click(target, { after: 150 }); await this.loc(target).selectOption(value); await this.page.keyboard.press('Escape').catch(() => {}); await this.wait(500); }
  async keys(combo, label) {
    await this.page.evaluate((l) => window.__tutKeys?.(l), label || combo);
    await this.page.keyboard.press(combo);
    await this.wait(700);
  }
  async ring(target, ms = 1600) {
    const el = this.loc(target);
    await el.scrollIntoViewIfNeeded({ timeout: 3000 }).catch(() => {});
    const b = await el.boundingBox({ timeout: 3000 }).catch(() => null);
    if (b) await this.page.evaluate(([x, y, w, h, ms]) => window.__tutRing?.(x, y, w, h, ms), [b.x, b.y, b.width, b.height, ms]);
    await this.wait(ms);
  }
  /** Geheimes unkenntlich machen (Tokens, Schlüssel) – nur in der Aufnahme */
  async blur(selector) { await this.page.evaluate((s) => document.querySelectorAll(s).forEach((el) => { el.style.filter = 'blur(7px)'; }), selector); }
  /** POST mit CSRF-Token der geöffneten Verwaltungsseite (Vorbereiten/Aufräumen) – Formular- oder JSON-Daten */
  async post(p, data = {}, json = false) {
    if (!this.page.url().startsWith(this.base + '/admin')) await this.page.goto(this.base + '/admin');
    return this.page.evaluate(async ([u, data, json]) => {
      const tok = document.querySelector('#adm-csrf, input[name=_csrf]')?.value || '';
      const init = { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-Token': tok }, redirect: 'follow' };
      if (json) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(data); }
      else { const fd = new URLSearchParams({ _csrf: tok }); for (const [k, v] of Object.entries(data)) [].concat(v).forEach((x) => fd.append(k, x)); init.body = fd; }
      const r = await fetch(u, init);
      return { status: r.status, url: r.url, text: (await r.text()).slice(0, 4000) };
    }, [this.url(p), data, json]);
  }
  async getJson(p) {
    if (!this.page.url().startsWith(this.base + '/admin')) await this.page.goto(this.base + '/admin');
    return this.page.evaluate(async (u) => (await fetch(u, { headers: { Accept: 'application/json' } })).json(), this.url(p));
  }
  /** Beispielbild laden (Adresse aus accounts.*.samples bzw. SAMPLES) → { name, type, b64 } */
  async sample(i, name) {
    const list = this.acc.samples || [];
    const u = list[i % Math.max(1, list.length)];
    if (!u) throw new Error('Keine Beispielbilder („samples“) im Zugang ' + this.acc.url);
    const r = await fetch(/^https?:/.test(u) ? u : this.base + u);
    const buf = Buffer.from(await r.arrayBuffer());
    return { name: name || u.split('/').pop(), type: r.headers.get('content-type') || 'image/jpeg', b64: buf.toString('base64') };
  }
  /** In setup: zusätzlich bei einer anderen Website anmelden (Cookies je Host – die Aufnahme übernimmt beide Sitzungen) */
  async signIn(acc) { await login(this.page, acc); }
  /** Werkzeuge für eine andere Website im selben Fenster (post/upload/getJson gegen deren Adresse) – nur Vorbereiten/Aufräumen */
  as(acc) { return new Tut(this.page, acc, this.mobile); }
  /** Erzeugte Beispieldatei aus tools/tutorials/samples/ (keine echten Fotos in den Videos) → { name, type, b64 } */
  file(name) {
    const types = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', mp4: 'video/mp4', pdf: 'application/pdf' };
    return { name, type: types[name.split('.').pop()] || 'application/octet-stream', b64: fs.readFileSync(path.join(DIR, 'samples', name)).toString('base64') };
  }
  /** Datei in die Mediathek laden (Vorbereiten) → Antwort von /admin/media/upload ({ success, file: { id, … } }) */
  async upload(file, alt = '') {
    if (!this.page.url().startsWith(this.base + '/admin')) await this.page.goto(this.base + '/admin');
    return this.page.evaluate(async ([u, f, alt]) => {
      const tok = document.querySelector('#adm-csrf, input[name=_csrf]')?.value || '';
      const bin = Uint8Array.from(atob(f.b64), (c) => c.charCodeAt(0));
      const fd = new FormData(); fd.append('_csrf', tok); fd.append('alt', alt); fd.append('file', new File([bin], f.name, { type: f.type }));
      const r = await fetch(u, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-Token': tok }, body: fd });
      return r.json();
    }, [this.base + '/admin/media/upload', file, alt]);
  }
  /** Datei „vom Schreibtisch“ auf ein Ziel ziehen: sichtbare Dateikarte folgt dem Zeiger, dann echte Drag-&-Drop-Ereignisse */
  async dropFile(target, file) {
    const el = this.loc(target);
    const b = await el.boundingBox();
    this.x = Math.max(40, b.x + b.width - 60); this.y = b.y + b.height - 40;
    await this.page.evaluate(([x, y, name]) => {
      const c = document.createElement('div'); c.id = '__tut-file';
      Object.assign(c.style, { position: 'fixed', left: x + 'px', top: y + 'px', zIndex: 2147483646, padding: '10px 14px', borderRadius: '10px', background: '#fff', boxShadow: '0 10px 30px rgba(0,0,0,.3)', font: '600 14px system-ui, sans-serif', color: '#111', pointerEvents: 'none', transition: 'left .9s ease-in-out, top .9s ease-in-out' });
      c.textContent = '🖼 ' + name; document.body.append(c);
    }, [this.x + 400, this.y + 200, file.name]);
    await this.wait(120);
    const cx = b.x + b.width / 2, cy = b.y + b.height / 2;
    await this.page.evaluate(([x, y]) => { const c = document.getElementById('__tut-file'); Object.assign(c.style, { left: x + 14 + 'px', top: y + 10 + 'px' }); }, [cx, cy]);
    await this.moveTo(target);
    await this.page.evaluate(async ([sel, f]) => {
      const el = typeof sel === 'string' ? document.querySelector(sel) : null;
      const bin = Uint8Array.from(atob(f.b64), (c) => c.charCodeAt(0));
      const dt = new DataTransfer(); dt.items.add(new File([bin], f.name, { type: f.type }));
      const tgt = el || document.elementFromPoint(window.__tutPos[0], window.__tutPos[1]);
      for (const type of ['dragenter', 'dragover']) tgt.dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer: dt }));
      await new Promise((r) => setTimeout(r, 700));
      tgt.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: dt }));
      document.getElementById('__tut-file')?.remove();
    }, [typeof target === 'string' ? target : null, file]);
    await this.wait(900);
  }
  /** Bild aus der Zwischenablage einfügen (⌘V) */
  async pasteFile(file) {
    await this.page.evaluate((l) => window.__tutKeys?.(l), process.platform === 'darwin' ? '⌘ V' : 'Strg V');
    await this.page.evaluate((f) => {
      const bin = Uint8Array.from(atob(f.b64), (c) => c.charCodeAt(0));
      const dt = new DataTransfer(); dt.items.add(new File([bin], f.name, { type: f.type }));
      document.dispatchEvent(new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: dt }));
    }, file);
    await this.wait(1000);
  }
  async scroll(y, ms = 900) {
    await this.page.evaluate((y) => window.scrollBy({ top: y, behavior: 'smooth' }), y);
    await this.wait(ms);
  }
}

// ---------------------------------------------------------------- Anmeldung
async function login(page, acc) {
  const base = acc.url.replace(/\/$/, '');
  await page.goto(base + '/admin/login');
  await page.fill('#email', acc.email);
  await page.fill('#password', acc.password);
  await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 15000 }).catch(() => {}), page.click('button[type=submit]')]);
  if (page.url().includes('/login')) throw new Error('Anmeldung fehlgeschlagen: ' + acc.email);
}

const vtt = (cues, end, lang) => 'WEBVTT\n\n' + cues.map((c, i) => {
  const next = i + 1 < cues.length ? cues[i + 1].t - 0.05 : end;
  const to = Math.max(c.t + 1, Math.min(next, Math.max(c.t + 12, c.t + (c.voice?.[lang].dur ?? 0) + 0.3)));
  const f = (s) => { s = Math.max(0, s); const h = Math.floor(s / 3600), m = Math.floor(s / 60) % 60, sec = (s % 60).toFixed(3).padStart(6, '0'); return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${sec}`; };
  return `${i + 1}\n${f(c.t)} --> ${f(to)}\n${c[lang]}\n`;
}).join('\n');

const ff = (...a) => execFileSync(FFMPEG, ['-y', '-v', 'error', ...a], { stdio: 'inherit' });
const ffErr = (...a) => spawnSync(FFMPEG, ['-hide_banner', '-nostats', ...a], { maxBuffer: 64 << 20 }).stderr.toString();
/** Lautheit auf −18 LUFS (Sprache), Spitzen ≤ −3 dBFS – zweistufig (linear) plus Begrenzer, damit alle Videos gleich laut sind.
 *  Piper normalisiert jeden Satz auf Vollaussteuerung; ohne Begrenzer würde AAC/Opus übersteuern. */
function loudnorm(src) {
  const m = ffErr('-i', src, '-af', 'loudnorm=I=-18:TP=-1.5:LRA=11:print_format=json', '-f', 'null', '-');
  const j = JSON.parse(m.slice(m.lastIndexOf('{'), m.lastIndexOf('}') + 1));
  return `loudnorm=I=-18:TP=-1.5:LRA=11:measured_I=${j.input_i}:measured_TP=${j.input_tp}:measured_LRA=${j.input_lra}:measured_thresh=${j.input_thresh}:offset=${j.target_offset}:linear=true,`
    + 'aresample=48000,alimiter=limit=0.7:attack=5:release=50:level=false';
}
/** Sprechbeginne in einer Datei (Ende jeder Stille ≥ 0,25 s, Schwelle −45 dB) – zur Kontrolle gegen die Untertitel */
function speechStarts(file) {
  const out = ffErr('-i', file, '-map', '0:a:0', '-af', 'silencedetect=n=-45dB:d=0.25', '-f', 'null', '-');
  const starts = [...out.matchAll(/silence_end: ([\d.]+)/g)].map((m) => parseFloat(m[1]));
  if (!/silence_start: 0(\.0+)?\b/.test(out)) starts.unshift(0);
  return starts;
}
const probe = (f) => parseFloat(execFileSync(FFPROBE, ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', f]).toString());

// ---------------------------------------------------------------- Aufnahme
const manifestFile = path.join(OUT, 'videos.json');
const manifest = fs.existsSync(manifestFile) ? JSON.parse(fs.readFileSync(manifestFile, 'utf8')) : {};
let narrator = null;
if (flag('--voice')) {
  narrator = new Narrator();
  const t0 = Date.now();
  const n = await narrator.warm(fs.readFileSync(path.join(DIR, 'flows.mjs'), 'utf8'));
  console.log(`Sprecher: ${VOICES.de} / ${VOICES.en} – ${n} Texte bereit (${((Date.now() - t0) / 1000).toFixed(1)} s)`);
}
const browser = await chromium.launch({ headless: !flag('--headed'), args: ['--lang=de-DE'] });
let failed = 0;

for (const f of selected) {
  const acc = ACCOUNTS[f.account];
  if (!acc) { console.error(`✗ ${f.slug}: Zugang „${f.account}“ fehlt in ${path.basename(accFile)}`); failed++; continue; }
  const size = f.mobile ? { width: 390, height: 844 } : { width: 1280, height: 800 };
  const common = { viewport: size, locale: 'de-DE', colorScheme: 'light', bypassCSP: true, reducedMotion: 'no-preference', ...(f.mobile ? { isMobile: true, hasTouch: true, deviceScaleFactor: 1 } : {}) };
  console.log(`● ${f.slug} – ${f.title}`);
  // Anmeldung (ohne Aufnahme) und Vorbereitung
  const prep = await browser.newContext(common);
  const pp = await prep.newPage();
  const state = {};
  let ok = true;
  try {
    await login(pp, acc);
    // Darstellung der Verwaltung für die Aufnahme auf „Hell“ (Einstellung des Kontos wird danach wiederhergestellt)
    await pp.goto(acc.url.replace(/\/$/, '') + '/admin/account');
    const pref = await pp.evaluate(() => ({ appearance: document.querySelector('select[name=appearance]')?.value ?? '', locale: document.querySelector('select[name=locale]')?.value ?? '' }));
    if (pref.appearance === 'dark') { state._pref = pref; await new Tut(pp, acc, false).post('/admin/account/locale', { ...pref, appearance: 'light' }); }
    if (f.setup) await f.setup(new Tut(pp, acc, false), state, ACCOUNTS);
  } catch (e) { console.error(`✗ ${f.slug} (Vorbereitung): ${e.message}`); ok = false; }
  const storageState = await prep.storageState();

  const rawDir = path.join(RAW, f.slug);
  fs.rmSync(rawDir, { recursive: true, force: true });
  let t = null;
  if (ok) {
    const ctx = await browser.newContext({ ...common, storageState: f.noLogin ? undefined : storageState, recordVideo: { dir: rawDir, size } });
    await ctx.addInitScript(OVERLAY);
    // Cookie-Hinweis (Erweiterung Consent-Kit) in allen anderen Abläufen ausblenden – als hätten Besucher schon entschieden;
    // nur Abläufe mit consent: true zeigen ihn. So entsteht keine Einwilligung und kein Protokolleintrag.
    if (!f.consent) await ctx.addInitScript(() => {
      const css = () => { const st = document.createElement('style'); st.textContent = 'consent-kit{display:none!important}'; (document.head || document.documentElement).append(st); };
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', css); else css();
    });
    await ctx.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: acc.url }).catch(() => {});
    const page = await ctx.newPage();
    page.setDefaultTimeout(20000);
    // Virtueller Passkey-Authenticator (CDP): sonst meldet die Anmeldeseite in Headless-Chromium „unterstützt keine Passkeys“
    try {
      const cdp = await ctx.newCDPSession(page);
      await cdp.send('WebAuthn.enable');
      await cdp.send('WebAuthn.addVirtualAuthenticator', { options: { protocol: 'ctap2', transport: 'internal', hasResidentKey: true, hasUserVerification: true, isUserVerified: true } });
    } catch { /* ältere Chromium-Versionen: ohne */ }
    t = new Tut(page, acc, !!f.mobile, narrator);
    try {
      await f.run(t, state, ACCOUNTS);
      await t.wait(1500);
    } catch (e) {
      console.error(`✗ ${f.slug}: ${e.message}`);
      await page.screenshot({ path: path.join(RAW, f.slug + '-fehler.png') }).catch(() => {});
      ok = false;
    }
    t.end = t.now();
    await ctx.close();
  }
  // Aufräumen – immer, auch nach Fehlern
  if (f.cleanup) {
    try { await f.cleanup(new Tut(pp, acc, false), state, ACCOUNTS); } catch (e) { console.error(`! ${f.slug} (Aufräumen): ${e.message}`); }
  }
  if (state._pref) await new Tut(pp, acc, false).post('/admin/account/locale', state._pref).catch(() => {});
  await prep.close();
  if (!ok) { failed++; continue; }

  const raw = fs.readdirSync(rawDir).find((n) => n.endsWith('.webm'));
  const src = path.join(rawDir, raw);
  const cut = Math.max(0, (t.start ?? 0) - 0.3);
  // Zeit im fertigen Video: Vorlauf und gekürzte Wartezeiten abziehen
  const map = (x) => Math.max(0, x - cut - t.cuts.reduce((sum, [a, b]) => sum + (x > a ? Math.min(x, b) - a : 0), 0));
  const cues = t.cues.map((c) => ({ ...c, t: map(c.t) }));
  const end = map(t.end);
  const keepExpr = t.cuts.length ? `select='not(${t.cuts.map(([a, b]) => `between(t,${(a - cut).toFixed(2)},${(b - cut).toFixed(2)})`).join('+')})',setpts=N/FRAME_RATE/TB,` : '';
  const base = path.join(OUT, f.slug);
  fs.writeFileSync(base + '.de.vtt', vtt(cues, end, 'de'));
  fs.writeFileSync(base + '.en.vtt', vtt(cues, end, 'en'));
  // Tonspuren: gesprochene Schritte an den (umgerechneten) Zeitstempeln der Untertitel
  const voiced = narrator && cues.every((c) => c.voice);
  for (const [a, b] of t.cuts) for (const c of t.cues) if (c.voice && c.t < b && c.t + Math.max(c.voice.de.dur, c.voice.en.dur) > a) console.warn(`  ! gekürzte Wartezeit überschneidet Sprecher: „${c.de}“`);
  const tracks = {};
  if (voiced) for (const lang of ['de', 'en']) {
    tracks[lang] = path.join(rawDir, `voice.${lang}.wav`);
    buildTrack(cues.map((c) => ({ t: c.t, file: c.voice[lang].file })), end, tracks[lang]);
  }
  const outputs = [];
  if (!flag('--no-transcode')) {
    const vid = path.join(rawDir, 'video');
    const common = ['-ss', cut.toFixed(2), '-i', src, '-an', '-vf', `fps=25,${keepExpr}format=yuv420p`];
    ff(...common, '-c:v', 'libx264', '-preset', 'slow', '-crf', '28', '-profile:v', 'high', vid + '.mp4');
    if (!f.noWebm) ff(...common, '-c:v', 'libvpx-vp9', '-crf', '42', '-b:v', '0', '-row-mt', '1', '-deadline', 'good', '-cpu-used', '2', vid + '.webm');
    const lang3 = { de: 'deu', en: 'eng' };
    const mux = (video, lang, out) => {
      const mp4 = out.endsWith('.mp4');
      if (!lang) return ff('-i', video, '-c', 'copy', ...(mp4 ? ['-movflags', '+faststart'] : []), out);
      ff('-i', video, '-i', tracks[lang], '-map', '0:v', '-map', '1:a', '-c:v', 'copy', '-af', loudnorm(tracks[lang]), '-ac', '1',
        ...(mp4 ? ['-c:a', 'aac', '-b:a', '48k', '-movflags', '+faststart'] : ['-c:a', 'libopus', '-b:a', '48k', '-application', 'voip']),
        '-metadata:s:v:0', 'language=und', '-metadata:s:a:0', `language=${lang3[lang]}`, '-metadata:s:a:0', `title=${lang === 'de' ? 'Deutsch' : 'English'}`, out);
      outputs.push(out);
    };
    mux(vid + '.mp4', voiced ? 'de' : null, base + '.mp4');
    if (!f.noWebm) mux(vid + '.webm', voiced ? 'de' : null, base + '.webm'); else fs.rmSync(base + '.webm', { force: true });
    if (voiced) mux(vid + '.mp4', 'en', base + '.en.mp4'); else fs.rmSync(base + '.en.mp4', { force: true });
    if (voiced && !f.noWebm && flag('--en-webm')) mux(vid + '.webm', 'en', base + '.en.webm'); else fs.rmSync(base + '.en.webm', { force: true });
    const at = t.posterAt != null ? map(t.posterAt) : end * 0.6;
    ff('-ss', at.toFixed(2), '-i', vid + '.mp4', '-frames:v', '1', '-q:v', '5', base + '.jpg');
  }
  // Kontrolle: Sprechbeginne in der fertigen Datei gegen die Untertitel-Zeitstempel
  let align = null;
  if (voiced && outputs.length) {
    align = {};
    for (const [lang, file] of [['de', base + '.mp4'], ['en', base + '.en.mp4']]) {
      const starts = speechStarts(file);
      const d = cues.map((c) => Math.min(...starts.map((s) => Math.abs(s - c.t))));
      align[lang] = +Math.max(...d).toFixed(3);
    }
  }
  const sz = (e) => (fs.existsSync(base + e) ? fs.statSync(base + e).size : 0);
  manifest[f.slug] = {
    duration: Math.round(probe(base + '.mp4')), width: size.width, height: size.height,
    mp4: sz('.mp4'), webm: sz('.webm'), jpg: sz('.jpg'), cues: cues.length, recorded: new Date().toISOString().slice(0, 10),
    ...(voiced ? { audio: { de: VOICES.de, en: VOICES.en }, mp4En: sz('.en.mp4'), webmEn: sz('.en.webm'), align } : {}),
  };
  for (const k of ['webm', 'webmEn']) if (!manifest[f.slug][k]) delete manifest[f.slug][k];
  // Oberste Ebene: "audio": false, solange kein Video einen Sprecher hat (HelpController/Ansichten: „ohne Ton“)
  manifest.audio = Object.entries(manifest).some(([k, v]) => k !== 'audio' && v && v.audio) ? true : false;
  if (manifest.audio) delete manifest.audio;
  if (!flag('--raw')) fs.rmSync(rawDir, { recursive: true, force: true });
  console.log(`  ✓ ${manifest[f.slug].duration} s · MP4 ${(sz('.mp4') / 1024).toFixed(0)} KB · WebM ${(sz('.webm') / 1024).toFixed(0)} KB`
    + (voiced ? ` · EN-MP4 ${(sz('.en.mp4') / 1024).toFixed(0)} KB · Abweichung Ton/Untertitel ≤ ${Math.max(align?.de ?? 0, align?.en ?? 0).toFixed(2)} s` : '') + ` · ${cues.length} Schritte`);
  fs.writeFileSync(manifestFile, JSON.stringify(manifest, null, 1) + '\n');
}
narrator?.stop();
await browser.close();
const total = fs.readdirSync(OUT).reduce((s, n) => s + fs.statSync(path.join(OUT, n)).size, 0);
console.log(`Gesamt: ${(total / 1048576).toFixed(1)} MB in ${OUT}${failed ? ` · ${failed} fehlgeschlagen` : ''}`);
console.log('Weiter: php bin/console tutorials:export (Katalog → tutorials.json) und in der Website php bin/console klxm:seed');
process.exit(failed ? 1 : 0);
