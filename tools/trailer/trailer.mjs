#!/usr/bin/env node
/**
 * Produkt-Trailer „KLXM Studio im Überblick“ (Produkt-Website, Startseite #trailer) – ohne Ton, Untertitel EN/DE/SL.
 *
 *   node tools/trailer/trailer.mjs                 alles aufnehmen und schneiden (Standard: ohne Ton, s. u. „Ton“)
 *   node tools/trailer/trailer.mjs --list          Einstellungen (Shots) mit Kurznamen und Sprechertext
 *   node tools/trailer/trailer.mjs --only=theme-,adm-media  nur diese Shots neu aufnehmen („theme-“ = Präfix), Rest aus dem
 *                                                    Zwischenstand; die edit-Shots bauen aufeinander auf → nur gemeinsam (--only=edit-)
 *   node tools/trailer/trailer.mjs --cut           nur neu schneiden (alle Shots aus tools/trailer/.work/), z. B. nach Stimmenwechsel
 *   Optionen: --voice (mit englischem Sprecher), --voice-timing (Sprecher nur für den Takt, Video ohne Ton),
 *             --silent (ohne Ton, Untertitel nach Lesezeit – auch wenn voice.json "audio": true), --headed, --keep (Einzelbilder behalten)
 *
 * Ton: Standard ist OHNE Ton ("audio": false in tools/trailer/voice.json). Ohne Tonspur richten sich Shot-Längen und Untertitel nach
 * der Lesezeit; --voice-timing übernimmt stattdessen die Zeiten des Sprechers (gleicher Schnitt wie die Fassung mit Ton, nur stumm).
 * Mit Ton: --voice oder "audio": true – der Sprecher-Werkzeugkasten (voice.mjs, voice.json, lexicon.en.json) bleibt dafür erhalten.
 *
 * Drehbuch: je Shot vo: [[en, de, sl], …] – ein Satz je Eintrag. Gesprochen wird en (tools/trailer/voice.mjs, Stimme = EINE Zeile in
 * tools/trailer/voice.json, Aussprache tools/trailer/lexicon.en.json, nur lokale TTS); en-Untertitel = gesprochener Text, de/sl =
 * Übersetzung (sl: muttersprachlich prüfen lassen). Wortwahl zeitlos – kein „neu“, „jetzt verfügbar“, „ist da“ (Produkt unveröffentlicht).
 * Die Mindestlänge eines Shots ist seine Sprechdauer (Sätze nacheinander, 0,3 s Pause); ein Untertitel je Satz, Zeiten = Sprechzeiten.
 * Lautheit −16 LUFS (zweistufiges loudnorm), keine Musik.
 *
 * Ablauf: Playwright (Chromium) nimmt jeden Shot einzeln auf – Bilder per CDP Page.captureScreenshot in 1920 × 1080
 * (Fenster 1280 × 720, Pixeldichte 1,5), Ladezeiten und KI-Wartezeiten werden beim Aufnehmen angehalten („keine
 * Wartezeit im Bild“). Titelkarten, die Kit-Montage und das Terminal (Content-Sync) sind HTML-Seiten („Bühne“, nur lokale
 * Schriften), die Websites erscheinen dort in iframes. ffmpeg setzt die Shots mit Überblendungen (xfade) zusammen.
 *
 * NUR gegen eine TESTKOPIE mit den Beispiel-Websites (basis/demo, fluid, editorial, essenz, modern, glas, nature, Landingpage) – Zugänge wie
 * bei den Tutorials (tools/tutorials/accounts.local.json oder TUT_ACCOUNTS: demo, fluid, network). Nichts wird
 * gespeichert: Die Aufnahme leitet alle schreibenden Anfragen (POST …) ins Leere und antwortet „ok“ – Veröffentlichen,
 * Speichern usw. sind im Video zu sehen, ändern aber nichts. Durchgelassen werden nur Anmeldung,
 * Vorschauen und KI-Vorschläge (die KI-Aufrufe erscheinen im KI-Verlauf der Website).
 *
 * Ergebnis in TRAILER_OUT (Standard: ../klxm-studio-website/site-tools/trailer neben dem Projektordner – der Trailer gehört zur
 * Produkt-Website studio.klxm.de und wird nicht mit dem CMS ausgeliefert; lokal einbinden mit php site-tools/trailer-replace.php
 * bzw. klxm:seed): klxm-studio-trailer.mp4 (1080p, H.264, faststart; AAC nur mit Ton), .webm (VP9; Opus nur mit Ton), -720.mp4, .jpg (Vorschaubild),
 * .en.vtt / .de.vtt / .sl.vtt, trailer.json (Dauer, Größen, Sprecher, Schnittliste).
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(DIR, '../..');
const OUT = path.resolve(process.env.TRAILER_OUT || path.join(ROOT, '../../klxm-studio-website/site-tools/trailer'));
const WORK = path.join(DIR, '.work');
const NAME = 'klxm-studio-trailer';
const args = process.argv.slice(2);
const flag = (f) => args.includes(f);
const only = (args.find((a) => a.startsWith('--only=')) || '').slice(7).split(',').filter(Boolean);
const FFMPEG = process.env.FFMPEG || (fs.existsSync('/opt/homebrew/bin/ffmpeg') ? '/opt/homebrew/bin/ffmpeg' : 'ffmpeg');
const FFPROBE = FFMPEG.replace(/ffmpeg$/, 'ffprobe');
const W = 1280, H = 720, DPR = 1.5, FPS = 30;                // Fenster (CSS-Pixel) × Pixeldichte = 1920 × 1080
const VOICE_AT = 0.35;                                         // Sprecher setzt so viele Sekunden nach Shot-Beginn ein
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
/** Shot abbrechen, wenn er hängt (Seite reagiert nicht, Element fehlt …) */
const watchdog = (p, id, s = 240) => Promise.race([p, sleep(s * 1000).then(() => { throw new Error(`${id}: nach ${s} s abgebrochen`); })]);
const DEBUG = !!process.env.TRAILER_DEBUG;

/* ============================================================ Zugänge und Websites */
const accFile = process.env.TUT_ACCOUNTS || path.join(ROOT, 'tools/tutorials/accounts.local.json');
const ACC = fs.existsSync(accFile) ? JSON.parse(fs.readFileSync(accFile, 'utf8')) : {};
const base = (u) => String(u || '').replace(/\/$/, '');
const host = (u, sub) => base(u).replace(/\/\/[^./:]+\./, `//${sub}.`);   // http://demo.localhost:8089 → http://fluid.localhost:8089
const SITES = {
  basis: base(ACC.demo?.url), fluid: base(ACC.fluid?.url),
  editorial: base(ACC.editorial?.url) || host(ACC.demo?.url, 'editorial'),
  essenz: base(ACC.essenz?.url) || host(ACC.demo?.url, 'essenz'),
  landing: base(ACC.landing?.url) || host(ACC.demo?.url, 'kampagne'),
  modern: base(ACC.modern?.url) || host(ACC.demo?.url, 'modern'),
  glas: base(ACC.glas?.url) || host(ACC.demo?.url, 'glas'),
  nature: base(ACC.nature?.url) || host(ACC.demo?.url, 'nature'),
  ...(process.env.TRAILER_SITES ? JSON.parse(process.env.TRAILER_SITES) : {}),
};

/* ============================================================ Gestaltung (Titelkarten, Bühne, Einblendungen) */
const LOGO = fs.readFileSync(path.join(ROOT, 'app/helpers.php'), 'utf8').match(/function cms_logo\(\)[\s\S]*?(<svg[\s\S]*?<\/svg>)/)[1]
  .replace(/ class="[^"]*"/, '').replace(/ aria-hidden="true" focusable="false"/, '');
const C = { navy: '#314164', ink: '#0F1626', petrol: '#5FE0CF', violett: '#C9A8FF', orange: '#FFB36B', side: '#1B2338' };
const SECTION = { intro: C.petrol, themes: C.petrol, edit: C.violett, admin: C.orange, outro: C.petrol };
const FONTS = `@font-face{font-family:Lato;font-weight:400;src:url(/fonts/lato-latin-400-normal.woff2) format("woff2")}
@font-face{font-family:Lato;font-weight:700;src:url(/fonts/lato-latin-700-normal.woff2) format("woff2")}
@font-face{font-family:Lato;font-weight:400;font-style:italic;src:url(/fonts/lato-latin-400-italic.woff2) format("woff2")}`;
const BG = `body{margin:0;width:${W}px;height:${H}px;overflow:hidden;background:#0F1626;font-family:Lato,system-ui,sans-serif;color:#fff;-webkit-font-smoothing:antialiased}
.bg{position:fixed;inset:-20%;background:radial-gradient(40% 45% at 22% 30%,rgba(14,100,112,.55),transparent 70%),radial-gradient(38% 42% at 78% 72%,rgba(91,63,149,.5),transparent 70%),radial-gradient(30% 30% at 70% 18%,rgba(163,74,11,.22),transparent 70%),linear-gradient(135deg,#111A2E,#1B2540);animation:drift 14s ease-in-out infinite alternate}
.grid{position:fixed;inset:0;background-image:linear-gradient(rgba(255,255,255,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.045) 1px,transparent 1px);background-size:64px 64px;mask-image:radial-gradient(70% 70% at 50% 50%,#000,transparent)}
@keyframes drift{from{transform:translate3d(0,0,0) scale(1)}to{transform:translate3d(-3%,2%,0) scale(1.06)}}
@keyframes up{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:none}}
@keyframes fade{from{opacity:0}to{opacity:1}}
@keyframes grow{from{transform:scaleX(0)}to{transform:scaleX(1)}}
@keyframes logo{from{opacity:0;transform:scale(.55) rotate(-40deg)}to{opacity:1;transform:none}}`;
const mark = (size) => `<span class="mark" style="font-size:${size}px"><b>KLXM</b> <span>Studio</span></span>`;
const MARK_CSS = `.mark{letter-spacing:-.02em;white-space:nowrap}.mark b{font-weight:700}.mark span{font-weight:400;opacity:.9}`;

/** Titelkarten (1280 × 720 CSS-Pixel) */
function cardHtml(kind, o = {}) {
  const acc = SECTION[o.section || kind] || C.petrol;
  let body = '';
  if (kind === 'intro') body = `
    <main class="c"><div class="logo">${LOGO}</div>${mark(88)}
      <p class="tag">Websites. Content. One system.</p>
      <p class="sub"><span class="bar"></span>The lean multi-site CMS</p></main>`;
  else if (kind === 'outro') body = `
    <main class="c"><div class="logo">${LOGO}</div>${mark(76)}
      <p class="lic">Open source · MIT licence</p>
      <ul class="chips">${['Multi-site', 'Accessibility in mind', 'Cookie-free by default', 'AI optional, even local'].map((t, i) => `<li style="animation-delay:${1.1 + i * .22}s">${t}</li>`).join('')}</ul>
      <p class="foot">PHP 8.4 · no framework · KLXM Crossmedia, Moers</p></main>`;
  else body = `
    <main class="s"><p class="no">${o.no}</p><h1>${o.title}</h1><span class="bar"></span><p class="subt">${o.sub}</p></main>`;
  return `<!doctype html><html lang="en"><meta charset="utf-8"><style>${FONTS}${BG}${MARK_CSS}
.c{position:relative;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}
.logo{width:112px;height:112px;margin-bottom:26px;color:#fff;animation:logo 1s cubic-bezier(.2,.9,.25,1.15) both;filter:drop-shadow(0 10px 30px rgba(95,224,207,.25))}
.logo svg{width:100%;height:100%;display:block}
.c .mark{animation:up .8s .35s cubic-bezier(.2,.8,.2,1) both}
.tag{margin:22px 0 0;font-size:34px;font-weight:400;letter-spacing:-.01em;animation:up .8s .9s cubic-bezier(.2,.8,.2,1) both}
.sub{display:flex;align-items:center;gap:14px;margin:26px 0 0;font-size:16px;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.72);animation:fade .8s 1.5s both}
.sub .bar,.s .bar{display:block;width:44px;height:3px;border-radius:2px;background:${acc};transform-origin:left;animation:grow .7s 1.5s cubic-bezier(.2,.8,.2,1) both}
.lic{margin:20px 0 0;font-size:28px;animation:up .8s .8s both}
.chips{display:flex;gap:12px;margin:34px 0 0;padding:0;list-style:none}
.chips li{padding:10px 18px;border-radius:999px;background:rgba(255,255,255,.08);box-shadow:inset 0 0 0 1px rgba(255,255,255,.16);font-size:18px;font-weight:700;animation:up .6s both}
.chips li::before{content:"";display:inline-block;width:8px;height:8px;margin-right:10px;border-radius:50%;background:${acc};vertical-align:2px}
.foot{margin:30px 0 0;font-size:15px;letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.55);animation:fade .8s 2.2s both}
.s{position:relative;height:100%;display:flex;flex-direction:column;justify-content:center;padding:0 130px}
.no{margin:0;font-size:22px;font-weight:700;letter-spacing:.24em;color:${acc};animation:up .6s .1s both}
.s h1{margin:8px 0 0;font-size:128px;line-height:1;font-weight:700;letter-spacing:-.035em;animation:up .7s .2s cubic-bezier(.2,.8,.2,1) both}
.s .bar{width:120px;margin:34px 0 0;animation-delay:.45s}
.subt{margin:22px 0 0;font-size:28px;color:rgba(255,255,255,.8);animation:up .7s .55s both}
</style><body><div class="bg"></div><div class="grid"></div>${body}</body></html>`;
}

/** Bühne für die Theme-Montage: Browserfenster / Smartphone mit iframe, Beschriftung unten links */
function stageHtml(items, label, section = 'themes') {
  const acc = SECTION[section];
  const el = items.map((it, i) => it.phone
    ? `<div class="phone" id="w${i}" style="${it.style || ''}"><div class="notch"></div><div class="scr" style="width:${Math.round((it.vw || 390) * (it.scale || 0.66))}px;height:${Math.round((it.vh || 844) * (it.scale || 0.66))}px"><iframe id="f${i}" src="${it.url}" style="width:${it.vw || 390}px;height:${it.vh || 844}px;transform:scale(${it.scale || 0.66})"></iframe></div></div>`
    : `<div class="win" id="w${i}" style="${it.style || ''}"><div class="bar"><i></i><i></i><i></i><span>${it.title || ''}</span></div><iframe id="f${i}" src="${it.url}"></iframe></div>`).join('');
  return `<!doctype html><html lang="de"><meta charset="utf-8"><style>${FONTS}${BG}
.win{position:absolute;display:flex;flex-direction:column;border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 40px 90px -30px rgba(0,0,0,.65),0 0 0 1px rgba(255,255,255,.12)}
.win .bar{flex:none;height:34px;display:flex;align-items:center;gap:7px;padding:0 14px;background:#EEF0F4;color:#3F4A57;font:600 13px Lato,system-ui}
.win .bar i{width:11px;height:11px;border-radius:50%;background:#D5D8DE}.win .bar i:first-child{background:#F26D5F}.win .bar i:nth-child(2){background:#F4BE4F}.win .bar i:nth-child(3){background:#5EC269}
.win .bar span{margin:0 auto;padding:4px 16px;border-radius:7px;background:#fff;box-shadow:0 0 0 1px rgba(0,0,0,.06);min-width:260px;text-align:center}
.win iframe{flex:1;width:100%;border:0;background:#fff}
.phone{position:absolute;padding:10px;border-radius:44px;background:#0B0E14;box-shadow:0 40px 90px -30px rgba(0,0,0,.7),0 0 0 1px rgba(255,255,255,.14),inset 0 0 0 2px #2A2F3A}
.phone .scr{position:relative;overflow:hidden;border-radius:34px;background:#fff}
.phone iframe{display:block;border:0;transform-origin:0 0;background:#fff}
.phone .notch{position:absolute;z-index:2;left:50%;top:18px;width:84px;height:22px;margin-left:-42px;border-radius:12px;background:#0B0E14}
.lbl{position:absolute;left:26px;bottom:24px;z-index:9;display:flex;align-items:center;gap:10px;padding:10px 18px 10px 14px;border-radius:999px;background:rgba(12,17,30,.88);color:#fff;font:700 17px Lato,system-ui;box-shadow:0 12px 30px -10px rgba(0,0,0,.6),inset 0 0 0 1px rgba(255,255,255,.12);opacity:0;transform:translateY(14px);transition:opacity .45s,transform .45s cubic-bezier(.2,.8,.2,1)}
.lbl.on{opacity:1;transform:none}.lbl::before{content:"";width:9px;height:9px;border-radius:50%;background:${acc}}
.lbl small{font-weight:400;font-size:15px;color:rgba(255,255,255,.75)}
</style><body><div class="bg"></div><div class="grid"></div>${el}<div class="lbl" id="lbl">${label}</div></body></html>`;
}

/** Bühne „Terminal“: Befehl und Ausgabe erscheinen Zeile für Zeile (Ausgabe-Texte wie in deploy/content-push.sh / Core\ContentSync) */
function terminalHtml(lines, label, section = 'admin') {
  const acc = SECTION[section];
  let t = 0.5;
  const rows = lines.map(([k, txt]) => {
    const d = t; t += k === '$' ? 1.1 : 0.32;
    const esc = txt.replace(/&/g, '&amp;').replace(/</g, '&lt;');
    return k === '$' ? `<p class="cmd" style="animation-delay:${d}s"><b>$</b> <span style="animation-delay:${d}s;--n:${txt.length}">${esc}</span></p>`
      : `<p class="${k}" style="animation-delay:${d}s">${esc}</p>`;
  }).join('');
  return `<!doctype html><html lang="de"><meta charset="utf-8"><style>${FONTS}${BG}
.term{position:absolute;left:110px;top:56px;width:1060px;height:560px;border-radius:14px;overflow:hidden;background:#0B111D;box-shadow:0 40px 90px -30px rgba(0,0,0,.7),0 0 0 1px rgba(255,255,255,.12)}
.term .bar{height:34px;display:flex;align-items:center;gap:7px;padding:0 14px;background:#1A2233;color:rgba(255,255,255,.6);font:600 13px Lato,system-ui}
.term .bar i{width:11px;height:11px;border-radius:50%;background:#3A4356}.term .bar i:first-child{background:#F26D5F}.term .bar i:nth-child(2){background:#F4BE4F}.term .bar i:nth-child(3){background:#5EC269}
.term .bar span{margin:0 auto}
.out{padding:22px 28px;font:17px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace;color:#D7DEEA}
.out p{margin:0;white-space:pre;opacity:0;animation:fade .15s both}
.out p.ok{color:#7EE2A8}.out p.cmd{color:#fff}.out p.cmd b{color:${acc}}
.out p.cmd span{display:inline-block;overflow:hidden;vertical-align:bottom;white-space:pre;width:0;animation:type .8s steps(var(--n)) both}
@keyframes type{to{width:calc(var(--n) * 1ch)}}
.lbl{position:absolute;left:26px;bottom:24px;z-index:9;display:flex;align-items:center;gap:10px;padding:10px 18px 10px 14px;border-radius:999px;background:rgba(12,17,30,.88);color:#fff;font:700 17px Lato,system-ui;box-shadow:0 12px 30px -10px rgba(0,0,0,.6),inset 0 0 0 1px rgba(255,255,255,.12);opacity:0;transform:translateY(14px);transition:opacity .45s,transform .45s cubic-bezier(.2,.8,.2,1)}
.lbl.on{opacity:1;transform:none}.lbl::before{content:"";width:9px;height:9px;border-radius:50%;background:${acc}}
.lbl small{font-weight:400;font-size:15px;color:rgba(255,255,255,.75)}
</style><body><div class="bg"></div><div class="grid"></div><div class="term"><div class="bar"><i></i><i></i><i></i><span>Terminal – KLXM Studio (lokal)</span></div><div class="out">${rows}</div></div><div class="lbl" id="lbl">${label}</div></body></html>`;
}

/** Einblendungen in der aufgenommenen Verwaltung/Website: Mauszeiger, Klick-Kreis, Markierung, Beschriftung (nur im Hauptfenster) */
const OVERLAY = () => {
  if (window !== window.top) return;
  try { if (window.PublicKeyCredential) PublicKeyCredential.isConditionalMediationAvailable = () => Promise.resolve(false); } catch { /* egal */ }
  const make = () => {
    if (document.getElementById('__tr-layer') || !document.body) return;
    const s = (el, css) => { Object.assign(el.style, css); return el; };
    const layer = s(document.createElement('div'), { position: 'fixed', inset: '0', pointerEvents: 'none', zIndex: '2147483647' });
    layer.id = '__tr-layer';
    const cur = s(document.createElement('div'), { position: 'absolute', width: '24px', height: '24px', marginLeft: '-3px', marginTop: '-2px', filter: 'drop-shadow(0 2px 3px rgba(0,0,0,.35))' });
    cur.innerHTML = '<svg width="24" height="24" viewBox="0 0 22 22"><path d="M3 2l14 8.2-6.1 1.3 3.7 7.1-2.7 1.4-3.7-7.2L3 17.2z" fill="#111" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>';
    const pos = window.__trPos || [-100, -100];
    s(cur, { left: pos[0] + 'px', top: pos[1] + 'px' });
    const lbl = s(document.createElement('div'), { position: 'absolute', left: '22px', bottom: '22px', display: 'flex', alignItems: 'center', gap: '10px', padding: '10px 18px 10px 14px', borderRadius: '999px', background: 'rgba(12,17,30,.88)', color: '#fff', font: '700 17px Lato, system-ui, sans-serif', boxShadow: '0 12px 30px -10px rgba(0,0,0,.6), inset 0 0 0 1px rgba(255,255,255,.12)', opacity: '0', transform: 'translateY(14px)', transition: 'opacity .45s, transform .45s cubic-bezier(.2,.8,.2,1)' });
    layer.append(lbl, cur);
    document.body.append(layer);
    window.__trCursor = (x, y) => { window.__trPos = [x, y]; s(cur, { left: x + 'px', top: y + 'px' }); };
    window.__trHide = (h) => s(cur, { opacity: h ? '0' : '1' });
    window.__trClick = (x, y) => {
      const r = s(document.createElement('div'), { position: 'absolute', left: x + 'px', top: y + 'px', width: '46px', height: '46px', marginLeft: '-23px', marginTop: '-23px', borderRadius: '50%', border: '3px solid #FFB300', background: 'rgba(255,179,0,.18)', transform: 'scale(.3)', transition: 'transform .45s ease-out, opacity .6s ease-out' });
      layer.append(r);
      requestAnimationFrame(() => requestAnimationFrame(() => s(r, { transform: 'scale(1.25)', opacity: '0' })));
      setTimeout(() => r.remove(), 800);
    };
    window.__trRing = (x, y, w, h, ms) => {
      const r = s(document.createElement('div'), { position: 'absolute', left: x - 6 + 'px', top: y - 6 + 'px', width: w + 12 + 'px', height: h + 12 + 'px', borderRadius: '12px', boxShadow: '0 0 0 3px #FFB300, 0 0 24px 4px rgba(255,179,0,.35)', opacity: '0', transition: 'opacity .3s' });
      layer.append(r);
      requestAnimationFrame(() => s(r, { opacity: '1' }));
      setTimeout(() => s(r, { opacity: '0' }), ms);
      setTimeout(() => r.remove(), ms + 400);
    };
    window.__trLabel = (html, color) => {
      if (!html) { s(lbl, { opacity: '0', transform: 'translateY(14px)' }); return; }
      lbl.innerHTML = `<span style="width:9px;height:9px;border-radius:50%;background:${color}"></span><span>${html}</span>`;
      requestAnimationFrame(() => s(lbl, { opacity: '1', transform: 'none' }));
    };
    window.__trBlur = (sel) => document.querySelectorAll(sel).forEach((el) => { el.style.filter = 'blur(7px)'; });
  };
  // Sicherheitsnetz wie bei den Tutorials: sichtbare Tokens/Schlüssel unscharf
  const SECRET = /\b(cms_[0-9a-f]{16,}|[0-9a-f]{24,}|[A-Za-z0-9+/]{40,}={0,2})\b/;
  const scrub = (root) => {
    const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    for (let n = w.nextNode(); n; n = w.nextNode()) if (SECRET.test(n.nodeValue) && n.parentElement) n.parentElement.style.filter = 'blur(7px)';
  };
  const go = () => { make(); scrub(document.body); new MutationObserver((ms) => ms.forEach((m) => m.target && m.target.nodeType === 1 && scrub(m.target))).observe(document.body, { subtree: true, childList: true }); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
};

/* ============================================================ Aufnahme (CDP-Bildschirmfotos, 1920 × 1080) */
class Rec {
  constructor(page) { this.page = page; }
  async attach() { this.cdp = await this.page.context().newCDPSession(this.page); }
  /** Ein Bild (1920 × 1080); hängt eine Aufnahme (z. B. während eines Seitenwechsels), neue CDP-Sitzung */
  async grab() {
    // clip gilt in Dokument-Koordinaten → aktuelle Scroll-Position dazurechnen (sonst Weiß nach dem Scrollen)
    const shot = this.cdp.send('Page.getLayoutMetrics').then(({ cssVisualViewport: v }) => this.cdp.send('Page.captureScreenshot',
      { format: 'jpeg', quality: 90, optimizeForSpeed: true, clip: { x: v.pageX, y: v.pageY, width: W, height: H, scale: DPR } }));
    const r = await Promise.race([shot, sleep(2500).then(() => null)]);
    if (!r) { const old = this.cdp; await this.attach(); old.detach().catch(() => {}); }
    return r;
  }
  start(dir) {
    fs.rmSync(dir, { recursive: true, force: true });
    fs.mkdirSync(dir, { recursive: true });
    Object.assign(this, { dir, frames: [], n: 0, on: true, paused: false, pausedMs: 0, t0: Date.now(), inflight: null });
    this.loop = (async () => {
      while (this.on) {
        if (this.paused) { await sleep(10); continue; }
        const a = Date.now();
        this.inflight = this.grab().catch(() => null);
        const r = await this.inflight;
        this.inflight = null;
        if (!r) { await sleep(20); continue; }
        if (this.paused || !this.on) continue;           // während der Pause entstanden → verwerfen
        const file = path.join(dir, String(this.n++).padStart(5, '0') + '.jpg');
        fs.writeFileSync(file, Buffer.from(r.data, 'base64'));
        this.frames.push({ t: ((a + Date.now()) / 2 - this.t0 - this.pausedMs) / 1000, file });
      }
    })();
  }
  /** Zeit im Shot (ohne Pausen), Sekunden */
  elapsed() { return (Date.now() - this.t0 - this.pausedMs - (this.paused ? Date.now() - this.pausedAt : 0)) / 1000; }
  /** Anhalten – wartet, bis ein laufendes Bild fertig ist (ein Bildschirmfoto während eines Seitenwechsels hängt sonst) */
  async pause() { if (!this.paused) { this.paused = true; this.pausedAt = Date.now(); } if (this.inflight) await this.inflight; }
  resume() { if (this.paused) { this.pausedMs += Date.now() - this.pausedAt; this.paused = false; } }
  async stop() { this.resume(); this.end = this.elapsed(); this.on = false; await this.loop; return this.end; }
}

const ff = (...a) => execFileSync(FFMPEG, ['-y', '-v', 'error', ...a], { stdio: 'inherit' });
const ffErr = (...a) => spawnSync(FFMPEG, ['-hide_banner', '-nostats', ...a], { maxBuffer: 64 << 20 }).stderr.toString();
const probe = (f) => parseFloat(execFileSync(FFPROBE, ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', f]).toString());

/** Einzelbilder mit Zeitstempeln → Clip mit fester Bildrate (Zwischenformat, fast verlustfrei) */
function encodeClip(frames, end, out, speed = 1) {
  if (!frames.length) throw new Error('Keine Bilder aufgenommen');
  frames[0].t = 0;
  if (speed !== 1) { frames = frames.map((f) => ({ ...f, t: f.t / speed })); end /= speed; }   // Zeitraffer (Mausbewegungen, Tippen)
  const list = frames.map((f, i) => `file '${f.file}'\nduration ${Math.max(0.001, (i + 1 < frames.length ? frames[i + 1].t : end) - f.t).toFixed(4)}`).join('\n');
  const lf = out + '.txt';
  fs.writeFileSync(lf, list + `\nfile '${frames.at(-1).file}'\n`);
  ff('-f', 'concat', '-safe', '0', '-i', lf, '-vf', `fps=${FPS},scale=1920:1080:flags=lanczos,format=yuv420p`, '-t', end.toFixed(3),
    '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '12', '-r', String(FPS), out);
  fs.rmSync(lf);
}

/* ============================================================ Werkzeuge für die Shots */
class Shot {
  constructor(page, rec, meta) { Object.assign(this, { page, rec, meta, x: 640, y: 420 }); }
  async wait(ms) { await this.page.waitForTimeout(ms); }
  async sync() { await this.page.evaluate(([x, y]) => window.__trCursor?.(x, y), [this.x, this.y]).catch(() => {}); }
  loc(t) { return typeof t === 'string' ? this.page.locator(t).first() : t; }
  /** Seite öffnen – Ladezeit wird nicht aufgenommen */
  async go(url) {
    await this.rec?.pause();
    await this.page.goto(url, { waitUntil: 'load' });
    await this.settle();
    this.rec?.resume();
  }
  async settle(ms = 450) {
    await this.page.waitForLoadState('load').catch(() => {});
    await this.page.evaluate(() => Promise.race([document.fonts?.ready, new Promise((r) => setTimeout(r, 3000))])).catch(() => {});
    await this.wait(ms); await this.sync();
  }
  /** Klick, der eine neue Seite lädt – Ladezeit wird nicht aufgenommen */
  async clickNav(target, opts = {}) {
    if (DEBUG) console.log('   nav', String(target).slice(0, 90));
    await this.moveTo(target, opts);
    await this.wait(200);
    await this.page.evaluate(([x, y]) => window.__trClick?.(x, y), [this.x, this.y]);
    await this.wait(220);
    await this.rec?.pause();
    await Promise.all([this.page.waitForNavigation({ waitUntil: 'load', timeout: 30000 }).catch(() => {}), this.page.mouse.click(this.x, this.y)]);
    await this.settle(opts.settle ?? 500);
    this.rec?.resume();
  }
  /** Langer Vorgang (KI) – wird herausgeschnitten */
  async cut(fn) { await this.rec?.pause(); try { await fn(); } finally { await this.wait(250); this.rec?.resume(); } }
  async moveTo(target, { dx = 0, dy = 0, center = false, ms } = {}) {
    const el = this.loc(target);
    await el.waitFor({ state: 'visible', timeout: 15000 });
    await el.scrollIntoViewIfNeeded().catch(() => {});
    const b = await el.boundingBox();
    if (!b) throw new Error('Kein Element: ' + target);
    return this.moveXY(Math.round(b.x + (center ? b.width / 2 : Math.min(b.width / 2, 60)) + dx), Math.round(b.y + b.height / 2 + dy), ms);
  }
  async moveXY(tx, ty, ms) {
    const dist = Math.hypot(tx - this.x, ty - this.y);
    const steps = Math.max(10, Math.round((ms ?? Math.min(700, 260 + dist * 0.6)) / 16));
    const x0 = this.x, y0 = this.y;
    for (let i = 1; i <= steps; i++) {
      const k = i / steps, e = k < .5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2;
      const x = x0 + (tx - x0) * e, y = y0 + (ty - y0) * e;
      await this.page.mouse.move(x, y);
      await this.page.evaluate(([x, y]) => window.__trCursor?.(x, y), [x, y]);
      await sleep(12);
    }
    this.x = tx; this.y = ty;
  }
  async click(target, opts = {}) {
    if (DEBUG) console.log('   click', String(target).slice(0, 90));
    await this.moveTo(target, opts);
    await this.wait(opts.before ?? 160);
    await this.page.evaluate(([x, y]) => window.__trClick?.(x, y), [this.x, this.y]);
    await this.page.mouse.click(this.x, this.y);
    await this.wait(opts.after ?? 450);
  }
  async hover(target, ms = 500, opts) { await this.moveTo(target, opts); await this.wait(ms); }
  async type(text, delay = 45) { await this.page.keyboard.type(text, { delay }); }
  /** Text in einem Element per Maus markieren (wie von Hand gezogen) */
  async selectText(target, phrase) {
    const pts = await this.loc(target).evaluate((el, phrase) => {
      const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
      for (let n = w.nextNode(); n; n = w.nextNode()) {
        const i = n.nodeValue.indexOf(phrase);
        if (i < 0) continue;
        const r = document.createRange(); r.setStart(n, i); r.setEnd(n, i + phrase.length);
        const rs = [...r.getClientRects()], a = rs[0], b = rs.at(-1);
        return [a.left + 1, a.top + a.height / 2, b.right - 1, b.top + b.height / 2];
      }
      return null;
    }, phrase);
    if (!pts) throw new Error('Text nicht gefunden: ' + phrase);
    await this.moveXY(Math.round(pts[0]), Math.round(pts[1]));
    await this.page.mouse.down();
    const steps = 14;
    for (let i = 1; i <= steps; i++) {
      const x = pts[0] + (pts[2] - pts[0]) * i / steps, y = pts[1] + (pts[3] - pts[1]) * i / steps;
      await this.page.mouse.move(x, y);
      await this.page.evaluate(([x, y]) => window.__trCursor?.(x, y), [x, y]);
      await sleep(22);
    }
    await this.page.mouse.up();
    this.x = Math.round(pts[2]); this.y = Math.round(pts[3]);
    await this.wait(450);
  }
  async ring(target, ms = 1400) {
    const b = await this.loc(target).boundingBox().catch(() => null);
    if (b) await this.page.evaluate(([x, y, w, h, ms]) => window.__trRing?.(x, y, w, h, ms), [b.x, b.y, b.width, b.height, ms]);
  }
  async label(text) { await this.page.evaluate(([t, c]) => window.__trLabel?.(t, c), [text, SECTION[this.meta.section] || C.petrol]).catch(() => {}); }
  /** Weich scrollen (Fenster oder Element), Dauer in ms */
  async scroll(dy, ms = 1200, sel = null) {
    await this.page.evaluate(([dy, ms, sel]) => new Promise((res) => {
      const el = sel ? document.querySelector(sel) : document.scrollingElement;
      el.style.scrollBehavior = 'auto';
      const y0 = el.scrollTop, t0 = performance.now(), ease = (t) => (t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
      const step = (now) => { const k = Math.min(1, (now - t0) / ms); el.scrollTop = y0 + dy * ease(k); if (k < 1) requestAnimationFrame(step); else res(); };
      requestAnimationFrame(step);
    }), [dy, ms, sel]);
  }
  /** Element per Scrollen an eine Höhe im Fenster bringen */
  async scrollTo(target, top = 110, ms = 1100) {
    const dy = await this.loc(target).evaluate((el, top) => Math.round(el.getBoundingClientRect().top - top), top);
    if (Math.abs(dy) > 8) await this.scroll(dy, ms);
  }
}

/** Weiches Scrollen in einem iframe der Bühne */
const frameScroll = (frame, dy, ms) => frame.evaluate(([dy, ms]) => new Promise((res) => {
  const el = document.scrollingElement; document.documentElement.style.scrollBehavior = 'auto';
  const y0 = el.scrollTop, t0 = performance.now(), ease = (t) => (t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
  const step = (now) => { const k = Math.min(1, (now - t0) / ms); el.scrollTop = y0 + dy * ease(k); if (k < 1) requestAnimationFrame(step); else res(); };
  requestAnimationFrame(step);
}), [dy, ms]);

/* ============================================================ Drehbuch */
// Sprecher Englisch, Untertitel en (= gesprochener Text), de, sl. vo: [[en, de, sl], …] – ein Eintrag je Satz (eigener Untertitel).
// Wortwahl zeitlos und beschreibend: KEIN „neu“, „jetzt verfügbar“, „ist da“, „Launch“ (Produkt noch nicht veröffentlicht).
const BAR = '[aria-label="Redaktion"]';
const blockOf = (s, label) => s.page.locator('.cms-block').filter({ has: s.page.locator('.cms-block__label', { hasText: label }) }).first();
const U = (site, p = '') => SITES[site] + p;
const A = (acc, p = '') => base(ACC[acc]?.url) + p;
const small = (t) => ` <small style="font-weight:400;opacity:.75">${t}</small>`;

const shots = [];
const add = (s) => shots.push(s);

// ---------- Intro
add({ id: 'intro', section: 'intro', kind: 'card', card: ['intro'], dur: 4.4, xfade: 0,
  vo: [['KLXM Studio: websites, content and data, in one open-source system.',
    'KLXM Studio: Websites, Inhalte und Daten in einem Open-Source-System.',
    'KLXM Studio: spletna mesta, vsebine in podatki v enem odprtokodnem sistemu.']] });

// ---------- Kits
add({ id: 'card-kits', section: 'themes', kind: 'card', card: ['section', { no: '01', title: 'Kits', sub: 'basis · fluid · editorial · essenz · modern · glas · nature · praxis · starter' }], dur: 2.0, xfade: 0.5 });
add({ id: 'theme-basis', section: 'themes', kind: 'stage', xfade: 0.5,
  vo: [['Kits give every website its own look.', 'Kits geben jeder Website ihr eigenes Gesicht.', 'Kompleti (Kits) dajo vsakemu spletnemu mestu lasten videz.']],
  stage: () => stageHtml([{ url: U('basis', '/'), title: 'Kit “basis” · Musterfirma (demo)', style: 'left:70px;top:44px;width:1140px;height:632px' }], 'Kit “basis” <small>neutral, for many sectors</small>'),
  async run(s, f) { await s.wait(150); await frameScroll(f[0], 1400, 2300); } });
add({ id: 'theme-editorial', section: 'themes', kind: 'stage', xfade: 0.5,
  vo: [['One suits a cultural magazine,', 'Eines passt zum Kulturmagazin,', 'Eden ustreza kulturni reviji,']],
  stage: () => stageHtml([{ url: U('editorial', '/'), title: 'Kit “editorial” · Kulturnetz (demo)', style: 'left:70px;top:44px;width:1140px;height:632px' }], 'Kit “editorial” <small>for magazines and associations</small>'),
  async run(s, f) { await s.wait(100); await frameScroll(f[0], 1200, 2100); } });
add({ id: 'theme-essenz', section: 'themes', kind: 'stage', xfade: 0.5,
  vo: [['another a small workshop.', 'ein anderes zur kleinen Werkstatt.', 'drugi majhni delavnici.']],
  stage: () => stageHtml([{ url: U('essenz', '/'), title: 'Kit “essenz” · Werkstatt Beispiel (demo)', style: 'left:70px;top:44px;width:1140px;height:632px' }], 'Kit “essenz” <small>less, but better</small>'),
  async run(s, f) {
    await s.wait(350);
    await f[0].evaluate(() => document.querySelector('details.ha-menu')?.setAttribute('open', '')).catch(() => {});
    await s.wait(1300);
    await f[0].evaluate(() => document.querySelector('details.ha-menu')?.removeAttribute('open')).catch(() => {});
    await frameScroll(f[0], 900, 1500);
  } });
add({ id: 'theme-trio', section: 'themes', kind: 'stage', xfade: 0.5,
  vo: [['Nine kits are included, from modern to nature.', 'Neun Kits sind dabei, von modern bis nature.', 'Priloženih je devet kompletov, od »modern« do »nature«.']],
  stage: () => stageHtml([
    { url: U('modern', '/'), title: 'Kit “modern” · Studio Nordlicht (fictional)', style: 'left:40px;top:40px;width:720px;height:470px;opacity:0;transform:translateY(40px);transition:transform .8s cubic-bezier(.2,.8,.2,1),opacity .5s' },
    { url: U('glas', '/'), title: 'Kit “glas” · Lumen Labs (fictional)', style: 'left:280px;top:130px;width:720px;height:470px;opacity:0;transform:translateY(40px);transition:transform .8s cubic-bezier(.2,.8,.2,1),opacity .5s' },
    { url: U('nature', '/'), title: 'Kit “nature” · Hofgut Wiesengrund (fictional)', style: 'left:520px;top:220px;width:720px;height:470px;opacity:0;transform:translateY(40px);transition:transform .8s cubic-bezier(.2,.8,.2,1),opacity .5s' },
  ], 'Kits <small>modern · glas · nature</small>'),
  async run(s, f) {
    for (let i = 0; i < 3; i++) {
      await s.page.evaluate((i) => Object.assign(document.getElementById('w' + i).style, { transform: 'none', opacity: '1' }), i);
      await s.wait(650);
    }
    await f[1].evaluate(() => document.querySelector('details.ha-menu')?.setAttribute('open', '')).catch(() => {});
    await s.wait(1200);
    await f[1].evaluate(() => document.querySelector('details.ha-menu')?.removeAttribute('open')).catch(() => {});
    await frameScroll(f[2], 500, 1200);
  } });
add({ id: 'theme-fluid', section: 'themes', kind: 'stage', xfade: 0.5,
  vo: [['Every kit adapts to any screen width,', 'Jedes Kit passt sich jeder Bildschirmbreite an,', 'Vsak komplet se prilagodi vsaki širini zaslona,']],
  stage: () => stageHtml([{ url: U('fluid', '/'), title: 'Kit “fluid” · Studio Beispiel (demo)', style: 'left:50%;top:44px;width:1140px;height:632px;transform:translateX(-50%);transition:width 1.6s cubic-bezier(.65,0,.35,1)' }], 'Kit “fluid” <small>no breakpoints</small>'),
  async run(s) {
    await s.wait(300);
    await s.page.evaluate(() => { document.getElementById('w0').style.width = '400px'; });
    await s.wait(1800);
    await s.page.evaluate(() => { document.getElementById('w0').style.width = '1140px'; });
    await s.wait(1600);
  } });
add({ id: 'theme-dark', section: 'themes', kind: 'stage', xfade: 0.5,
  vo: [['in light and in dark mode.', 'im hellen wie im dunklen Modus.', 'v svetlem in temnem načinu.']],
  stage: () => stageHtml([
    { url: U('basis', '/'), title: 'Musterfirma (demo) · desktop', style: 'left:56px;top:48px;width:860px;height:600px' },
    { phone: true, url: U('basis', '/leistungen'), style: 'left:950px;top:62px', scale: 0.66 },
  ], 'Light &amp; dark <small>desktop and phone</small>'),
  async run(s, f) {
    await s.wait(550);
    await s.page.emulateMedia({ colorScheme: 'dark' });
    await s.wait(400);
    await Promise.all([frameScroll(f[0], 700, 1700), frameScroll(f[1], 900, 1900)]);
  },
  async after(s) { await s.page.emulateMedia({ colorScheme: 'light' }); } });
add({ id: 'theme-landing', section: 'themes', kind: 'stage', xfade: 0.5,
  vo: [['Landing pages can run on a domain of their own.', 'Landingpages laufen auf Wunsch unter eigener Domain.', 'Ciljne strani lahko delujejo na lastni domeni.']],
  stage: () => stageHtml([
    { url: U('basis', '/'), title: new URL(SITES.basis).hostname, style: 'left:60px;top:40px;width:760px;height:520px;opacity:.9' },
    { url: U('landing', '/'), title: new URL(SITES.landing).hostname, style: 'left:430px;top:170px;width:790px;height:500px;transform:translateY(40px);opacity:0;transition:transform .9s cubic-bezier(.2,.8,.2,1),opacity .6s' },
  ], 'Landing page <small>own domain, own logo – one website</small>'),
  async run(s) { await s.wait(350); await s.page.evaluate(() => Object.assign(document.getElementById('w1').style, { transform: 'none', opacity: '1' })); await s.wait(2400); } });

// ---------- Bearbeiten (Website „fluid“, Seite „Über uns“ – nichts wird gespeichert)
add({ id: 'card-edit', section: 'edit', kind: 'card', card: ['section', { section: 'edit', no: '02', title: 'Editing', sub: 'Right on the page – with suggestions from KLXM Ai' }], dur: 2.0, xfade: 0.5 });
add({ id: 'edit-open', section: 'edit', kind: 'app', speed: 1.2, xfade: 0.5,
  vo: [['Editing happens right on the page.', 'Bearbeitet wird direkt auf der Seite.', 'Urejate neposredno na strani.']],
  async prep(s) { await s.go(A('fluid', '/ueber-uns')); s.x = 640; s.y = 300; await s.sync(); },
  async run(s) {
    await s.label('Toolbar' + small('view · edit · publish'));
    await s.wait(300);
    await s.clickNav(s.page.locator(`${BAR} [aria-label="Modus"] >> text="Bearbeiten"`).first(), { settle: 900 });
    const h1 = s.page.locator('main h1[data-edit]').first();
    await s.click(h1, { dx: 120, after: 250 });
    await s.page.keyboard.press(process.platform === 'darwin' ? 'Meta+A' : 'Control+A');
    await s.wait(250);
    await s.type('Klein, erfahren, nah.', 55);
    await s.wait(500);
  } });
add({ id: 'edit-format', section: 'edit', kind: 'app', speed: 1.6, xfade: 0.4, needs: 'edit-open',
  vo: [['Format text with highlights and colours.', 'Texte formatieren: mit Markierungen und Farben.', 'Oblikujte besedilo s poudarki in barvami.']],
  async run(s) {
    await s.page.evaluate(() => { document.activeElement?.blur(); getSelection().removeAllRanges(); });
    await s.label('Formatting' + small('highlight, colours, lists, links'));
    const blk = blockOf(s, 'Text + Bild');
    await s.scrollTo(blk, 58, 1200);
    const p = blk.locator('[data-edit="text"] p').first();
    await s.selectText(p, 'klare Struktur');
    await s.click('button[data-cmd="mark"]:visible', { after: 500 });
    await s.selectText(p, 'keine unnötigen Einbindungen');
    await s.click('button[data-menu="color"]:visible', { after: 500 });
    await s.click('button[data-cmd="color:accent"]:visible', { after: 500 });
    await s.page.evaluate(() => { document.activeElement?.blur(); getSelection().removeAllRanges(); });
    await s.moveXY(560, 600);
    await s.wait(400);
  } });
add({ id: 'edit-media', section: 'edit', kind: 'app', speed: 2.0, xfade: 0.4, needs: 'edit-format',
  vo: [['Images come from the media library.', 'Bilder kommen aus der Mediathek.', 'Slike izberete iz medijske knjižnice.']],
  async run(s) {
    await s.label('Media library' + small('pick an image'));
    const blk = blockOf(s, 'Text + Bild');
    await s.click(blk.locator('.cms-block__preview').first(), { dx: 820, dy: 30, after: 900 });
    const pick = s.page.locator('#cms-drawer button[data-media-pick]').first();
    await pick.evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'smooth' }));
    await s.wait(700);
    await s.click(pick, { after: 1000 });
    await s.click(s.page.locator('dialog[open] [role=option]:has-text("Netti2")').first(), { after: 700 });
    await s.click(s.page.locator('dialog[open] button:has-text("Diese Datei verwenden")').first(), { after: 1300 });
    await s.click(s.page.locator('#cms-drawer button:has-text("Fertig")').first(), { after: 900 });
    await s.moveXY(980, 470);
    await s.wait(600);
  } });
add({ id: 'edit-ai', section: 'edit', kind: 'app', speed: 1.8, xfade: 0.4, needs: 'edit-media',
  vo: [['KLXM Ai makes suggestions.', 'KLXM Ai macht Vorschläge.', 'KLXM Ai daje predloge.'],
    ['You compare them, then decide.', 'Sie vergleichen und entscheiden.', 'Vi jih primerjate in se odločite.']],
  async run(s) {
    await s.label('KLXM Ai' + small('review the suggestion, then apply'));
    const p = blockOf(s, 'Text + Bild').locator('[data-edit="text"] p').first();
    await s.click(p, { dx: 60, after: 500 });
    await s.click(s.page.locator('button[data-cmd="ai"]:visible').first(), { after: 900 });
    const resp = s.page.waitForResponse((r) => r.url().includes('/admin/api/ai/text'), { timeout: 180000 });
    await s.click(s.page.locator('dialog[open] button:visible:has-text("Erweitern")').first(), { after: 100 });
    await s.cut(async () => {
      await resp;
      await s.page.locator('dialog[open] [data-kia-apply]').first().waitFor({ state: 'visible', timeout: 30000 });
      await s.wait(500);
    });
    await s.wait(1300);
    await s.click('dialog[open] button[data-view="diff"]', { after: 1900 });
    await s.click('dialog[open] [data-kia-apply]', { after: 1100 });
  } });
add({ id: 'edit-block', section: 'edit', kind: 'app', speed: 2.1, xfade: 0.4, needs: 'edit-ai',
  vo: [['Pages are built from blocks, like these key figures.', 'Seiten entstehen aus Blöcken, etwa diesen Kennzahlen.', 'Strani so sestavljene iz blokov, na primer iz teh ključnih številk.']],
  async run(s) {
    await s.page.evaluate(() => { document.activeElement?.blur(); getSelection().removeAllRanges(); });
    await s.label('Add a block' + small('key figures with scale'));
    const quote = blockOf(s, 'Zitat');
    await s.scrollTo(quote, 70, 1300);
    await s.hover(quote.locator('.cms-block__preview').first(), 500, { dx: 300, dy: -40 });
    const plus = s.page.locator('.ce-toolbar__plus').first();
    await plus.waitFor({ state: 'visible', timeout: 5000 });
    await s.click(plus, { after: 700 });
    await s.type('Skala', 90);
    await s.wait(400);
    await s.click(s.page.locator('.ce-popover-item:visible:has-text("Kennzahlen mit Skala")').first(), { after: 1500 });
    const dr = s.page.locator('#cms-drawer');
    await dr.evaluate((el) => { [el, ...el.querySelectorAll('*')].forEach((n) => { if (n.scrollHeight > n.clientHeight + 20 && /(auto|scroll)/.test(getComputedStyle(n).overflowY)) n.scrollTop = 0; }); });
    await s.wait(300);
    await s.click(dr.locator('input[name="f[title]"]'), { after: 200 });
    await s.type('Zahlen, die zählen.', 50);
    await s.wait(300);
    await s.cut(async () => {
      for (let i = 0; i < 3; i++) { await dr.locator('button[data-rep="add"]').click(); await s.wait(250); }
      const rows = await dr.evaluate((el) => [...new Set([...el.querySelectorAll('input[name*="[items]"]')].map((x) => x.name.match(/\[items\]\[([^\]]+)\]/)[1]))]);
      const vals = [['92 %', '', 'Weiterempfehlung', 'Befragung (Beispiel)', ''], ['0,8', 's', 'Ladezeit', 'Startseite, mobil', '80'], ['24', '', 'Projekte im Jahr', 'von 30 möglichen', '']];
      for (let i = 0; i < 3; i++) {
        const [v, u, l, t, lev] = vals[i], k = rows[i];
        await dr.locator(`input[name="f[items][${k}][value]"]`).fill(v);
        if (u) await dr.locator(`input[name="f[items][${k}][unit]"]`).fill(u);
        await dr.locator(`input[name="f[items][${k}][label]"]`).fill(l);
        await dr.locator(`input[name="f[items][${k}][text]"]`).fill(t);
        if (lev) await dr.locator(`input[name="f[items][${k}][level]"]`).fill(lev);
      }
      await dr.locator(`input[name="f[items][${rows[2]}][max]"]`).fill('30');
      await s.wait(1600);
    });
    await s.click(dr.locator('button:has-text("Fertig")').first(), { after: 600 });
    const nb = blockOf(s, 'Kennzahlen mit Skala');
    await s.scrollTo(nb, 60, 1200);
    await s.moveXY(640, 560);
    await s.wait(1200);
  } });
add({ id: 'edit-publish', section: 'edit', kind: 'app', speed: 1.3, xfade: 0.4, needs: 'edit-block',
  vo: [['Publish when you’re ready.', 'Veröffentlichen, wenn alles passt.', 'Objavite, ko ste pripravljeni.']],
  async run(s) {
    await s.scrollTo(blockOf(s, 'Kennzahlen mit Skala'), 60, 600);
    await s.label('Publish');
    await s.click(`${BAR} button[data-editor-publish]:visible`, { after: 800 });
    const ok = s.page.locator('dialog.cms-confirm[open] [data-r="ok"]');
    if (await ok.waitFor({ timeout: 2500 }).then(() => true, () => false)) await s.click(ok, { after: 900 });
    await s.ring(`${BAR} [data-bar-chip]`, 1400);
    await s.hover(`${BAR} [data-bar-chip]`, 1100);
  } });

// ---------- Verwalten
add({ id: 'card-admin', section: 'admin', kind: 'card', card: ['section', { section: 'admin', no: '03', title: 'Managing', sub: 'Media, data, search, AI, extensions – and every website' }], dur: 2.0, xfade: 0.5 });
add({ id: 'adm-media', section: 'admin', kind: 'app', speed: 1.8, xfade: 0.5,
  vo: [['The media library has collections, tags and a quick preview.', 'Die Mediathek hat Sammlungen, Schlagworte und eine Schnellansicht.', 'Medijska knjižnica ima zbirke, oznake in hiter predogled.'],
    ['Shared pools serve several websites at once.', 'Geteilte Pools versorgen mehrere Websites zugleich.', 'Skupna skladišča medijev služijo več spletnim mestom hkrati.']],
  async prep(s) { await s.go(A('fluid', '/admin')); s.x = 700; s.y = 500; await s.sync(); },
  async run(s) {
    await s.clickNav('#adm-mainnav a[href$="/admin/media"]', { settle: 900 });
    await s.label('Media library' + small('collections, tags, quick look'));
    await s.click('.adm-side button[data-src="collection"]', { after: 900 });
    await s.click('.adm-side button[data-src="tag"][data-value="katze"]', { after: 800 });
    await s.click('[data-media-library] [role=option]:has-text("Netti")', { after: 400 });
    await s.page.keyboard.press('Space');
    await s.wait(1700);
    await s.page.keyboard.press('Escape');
    await s.wait(400);
    // Geteilter Pool: nur den Umschalter zeigen (Inhalt des Test-Pools nicht)
    await s.label('Shared media pools' + small('one library, several websites'));
    await s.ring('.fx-scope', 1800);
    await s.hover('.fx-scope button[data-scope]:not([data-scope=""])', 1500);
  } });
add({ id: 'adm-a11y', section: 'admin', kind: 'app', speed: 1.6, xfade: 0.4, needs: 'adm-media',
  vo: [['Alt texts are required, and videos without subtitles get flagged.', 'Alt-Texte sind Pflicht, Videos ohne Untertitel werden markiert.', 'Nadomestna besedila so obvezna, videoposnetki brez podnapisov pa so označeni.']],
  async run(s) {
    await s.label('Accessibility' + small('alt texts, subtitles, transcripts'));
    await s.click('.adm-side button[data-src="all"]', { after: 700 });
    await s.ring('.fx-checkgrp', 1500);
    await s.click('.adm-side button[data-value="nocaptions"]', { after: 900 });
    await s.click('[data-media-library] [role=option] >> nth=0', { after: 1200 });
    const panel = s.page.locator(':is(section,div,details,h3,h4):has-text("Untertitel & Transkript"):visible').last();
    if (await panel.count()) { await panel.scrollIntoViewIfNeeded().catch(() => {}); await s.ring(panel, 1600); await s.hover(panel, 1200); }
  } });
add({ id: 'adm-data', section: 'admin', kind: 'app', speed: 1.5, xfade: 0.4,
  vo: [['Build your own data tables and forms, without writing code.', 'Eigene Datentabellen und Formulare, ohne Programmieren.', 'Lastne podatkovne tabele in obrazci, brez programiranja.']],
  async prep(s) { await s.go(A('fluid', '/admin/data')); s.x = 700; s.y = 400; await s.sync(); },
  async run(s) {
    await s.label('Data' + small('tables, detail pages, public forms'));
    await s.hover('main :is(a,h2,h3):has-text("Projekte (Showcase)")', 500);
    await s.hover('main :is(a,h2,h3):has-text("Veranstaltungen (Showcase)")', 500);
    await s.hover('main :is(a,h2,h3):has-text("Rückrufwünsche (Showcase)")', 600);
    await s.go(A('fluid', '/showcase/formulare'));
    await s.label('Public form' + small('entries land in the table'));
    const form = s.page.locator('main form').first();
    await s.scrollTo(form, 90, 1400);
    const inp = form.locator('input[type=text], input:not([type])').first();
    await s.click(inp, { after: 200 });
    await s.type('Alex Beispiel', 60);
    await s.wait(900);
  } });
add({ id: 'adm-search', section: 'admin', kind: 'app', speed: 1.3, xfade: 0.4,
  vo: [['The site search tolerates typos.', 'Die Website-Suche verzeiht Tippfehler.', 'Iskanje po spletnem mestu oprosti tipkarske napake.']],
  async prep(s) { await s.go(A('fluid', '/')); s.x = 900; s.y = 300; await s.sync(); },
  async run(s) {
    await s.label('Site search' + small('typo-tolerant, with suggestions'));
    await s.click('#ha-q-bar', { after: 300 });
    await s.type('wrkstatt', 120);
    await s.wait(1400);
    await s.rec?.pause();
    await Promise.all([s.page.waitForNavigation({ waitUntil: 'load', timeout: 30000 }).catch(() => {}), s.page.keyboard.press('Enter')]);
    await s.settle(600);
    s.rec?.resume();
    await s.scroll(360, 1400);
    await s.wait(900);
  } });
add({ id: 'adm-blocks', section: 'admin', kind: 'app', speed: 1.5, xfade: 0.4,
  vo: [['Admins can build custom blocks, with a live preview.', 'Eigene Blöcke bauen Admins selbst, mit Live-Vorschau.', 'Skrbniki lahko sestavijo lastne bloke, s sprotnim predogledom.']],
  async prep(s) { await s.go(A('fluid', '/admin/blocks/beispiel_preistabelle')); },
  async run(s) {
    await s.label('Block designer' + small('fields, template, CSS'));
    await s.click('main [role=tab]:has-text("Vorlage"), main button:has-text("Vorlage")', { after: 800 });
    await s.click('main button:has-text("Vorschau")', { after: 1800 });
    await s.hover('main [data-cb] iframe, main iframe', 900, { dy: -60 }).catch(() => {});
  } });
add({ id: 'adm-ai', section: 'admin', kind: 'app', speed: 1.4, xfade: 0.4,
  vo: [['KLXM Ai is optional, and can run locally, on your own server.', 'KLXM Ai ist optional und läuft auf Wunsch lokal, auf dem eigenen Server.', 'Uporaba KLXM Ai je izbirna; lahko teče tudi lokalno, na vašem strežniku.']],
  async prep(s) { await s.go(A('fluid', '/admin/ai/einstellungen')); s.x = 700; s.y = 420; await s.sync(); },
  async run(s) {
    await s.label('KLXM Ai' + small('optional – here with Ollama, local'));
    await s.wait(400);
    const row = s.page.locator('main :is(tr,div,dl,li):has-text("Ollama"):visible').last();
    await s.ring(row, 1800);
    await s.hover(row, 900);
    await s.hover('main :text("bleibt auf dem Server") >> nth=0', 1300).catch(() => {});
  } });
add({ id: 'adm-extensions', section: 'admin', kind: 'app', speed: 1.5, xfade: 0.4,
  vo: [['Features and extensions are switched on for each website.', 'Funktionen und Erweiterungen werden je Website eingeschaltet.', 'Funkcije in razširitve vklopite za vsako spletno mesto posebej.']],
  async prep(s) { await s.go(A('fluid', '/admin/funktionen')); s.x = 700; s.y = 400; await s.sync(); },
  async run(s) {
    await s.label('Features &amp; extensions' + small('per website'));
    await s.hover('main :is(a,button):has-text("Inhalte") >> nth=0', 500);
    await s.hover('main :is(a,button):has-text("KI") >> nth=0', 500);
    await s.click('main :is(a,button):has-text("Erweiterungen") >> nth=0', { after: 1100 });
    await s.scroll(80, 600);
    await s.wait(900);
  } });
add({ id: 'adm-network', section: 'admin', kind: 'app', speed: 1.5, xfade: 0.4,
  vo: [['One installation runs many websites, each with its own domain and users.', 'Eine Installation betreibt viele Websites, jede mit eigener Domain und eigenen Benutzern.', 'Ena namestitev poganja več spletnih mest, vsako z lastno domeno in uporabniki.']],
  async prep(s) {
    await s.go(A('network', '/admin/network'));
    // Name der Hauptwebsite (Kundenprojekt in der Testkopie) nicht zeigen: Markenzeile unscharf, Liste vorab auf die Demo-Websites gefiltert
    await s.page.evaluate(() => window.__trBlur?.('.adm-brand'));
    await s.page.locator('main input[placeholder*="Domain"], main input[type=search]').first().fill('localhost');
    await s.wait(500);
    await s.page.locator('main article, main [class*=card]').filter({ hasText: /praxis|Hauptwebsite/i }).evaluateAll((els) => els.forEach((el) => { el.style.filter = 'blur(8px)'; }));
  },
  async run(s) {
    await s.label('Network' + small('all websites of one installation'));
    await s.moveXY(560, 180); await s.wait(300);
    await s.moveXY(980, 180); await s.wait(300);
    await s.scroll(330, 1300);
    await s.moveXY(700, 420);
    await s.wait(700);
  } });
add({ id: 'adm-sync', section: 'admin', kind: 'stage', xfade: 0.4,
  vo: [['Content sync brings changed pages back to the live site,', 'Der Content-Sync bringt geänderte Seiten zurück auf die Live-Website,', 'Sinhronizacija vsebin prenese spremenjene strani nazaj na živo spletno mesto,'],
    ['as a draft, and only when there is no conflict.', 'als Entwurf und nur, wenn es keinen Konflikt gibt.', 'kot osnutek in le, če ni konflikta.']],
  stage: () => terminalHtml([
    ['$', 'deploy/content-push.sh live default'],
    ['', '▸ Geänderte Seiten (lokal, default)'],
    ['', '  geändert: de:ueber-uns – Über uns'],
    ['', '  geändert: en:about – About us'],
    ['', '2 Seite(n) exportiert: /tmp/content-sync-default.json'],
    ['', '▸ Prüfen auf live'],
    ['ok', '  ✓ de:ueber-uns – Über uns'],
    ['ok', '  ✓ en:about – About us'],
    ['', '2 Seite(n) würden übernommen (als Entwurf). Probelauf – nichts geändert.'],
    ['', 'Übernehmen? [j/N] j'],
    ['', '▸ Übernehmen auf live'],
    ['', '2 Seite(n) übernommen – als Entwurf; in der Verwaltung prüfen und veröffentlichen.'],
    ['ok', '✓ Fertig.'],
  ], 'Content sync <small>local ⇄ live, conflict-safe, as a draft</small>'),
  async run(s) { await s.wait(5200); } });

// ---------- Outro
add({ id: 'outro', section: 'outro', kind: 'card', card: ['outro'], dur: 5.2, xfade: 0.6,
  vo: [['KLXM Studio. Open source, under the MIT licence.', 'KLXM Studio. Open Source, unter MIT-Lizenz.', 'KLXM Studio. Odprta koda, pod licenco MIT.'],
    ['By KLXM Crossmedia, in Moers.', 'Von KLXM Crossmedia aus Moers.', 'Izdelal KLXM Crossmedia iz Moersa.']] });

/* ============================================================ Ausführen */
if (flag('--list')) {
  for (const s of shots) console.log(`${s.id.padEnd(18)} ${s.kind.padEnd(6)} ${(s.vo || []).map((v) => v[0]).join(' ')}`);
  process.exit(0);
}
fs.mkdirSync(WORK, { recursive: true });
fs.mkdirSync(OUT, { recursive: true });
const clipsFile = path.join(WORK, 'clips.json');
const clips = fs.existsSync(clipsFile) ? JSON.parse(fs.readFileSync(clipsFile, 'utf8')) : {};
const failed = [];
const toRecord = flag('--cut') ? [] : shots.filter((s) => (only.length ? only.some((o) => s.id === o || (o.endsWith('-') && s.id.startsWith(o))) : true));

// Ton: Standard aus voice.json "audio" (false = ohne Ton); --voice = mit Sprecher, --silent = ohne. Mit Sprecher (oder --voice-timing)
// werden alle Sätze vorab erzeugt (tools/trailer/voice.mjs, Stimme aus voice.json), Mindestlänge je Shot = Sprechdauer.
// Sonst: Lesezeit der Untertitel (≈ 15 Zeichen/s, 2,2–6 s je Satz).
const AUDIO_DEFAULT = JSON.parse(fs.readFileSync(path.join(DIR, 'voice.json'), 'utf8')).audio === true;
const AUDIO = !flag('--silent') && (flag('--voice') || AUDIO_DEFAULT);
const SILENT = !AUDIO && !flag('--voice-timing');
const GAP = 0.3;                                               // Pause zwischen zwei Sätzen eines Shots
const voice = {};                                              // id → [{ file, dur }] je Satz
let VO = null;
const readTime = (txt) => Math.min(6, Math.max(2.2, txt.length / 15));
if (!SILENT) {
  const { Voice } = await import('./voice.mjs');
  VO = new Voice();
  let n = 0;
  for (const s of shots) if (s.vo) { voice[s.id] = []; for (const [en] of s.vo) { voice[s.id].push(await VO.best(en)); n++; } }
  VO.stop();
  console.log(`Sprecher: ${VO.name} – ${n} Sätze, ${Object.values(voice).flat().reduce((a, v) => a + v.dur, 0).toFixed(1)} s`);
}
/** Satz-Zeitpunkte relativ zum Shot-Beginn: [{ at, dur }] */
const sentences = (s) => {
  let t = VOICE_AT;
  return (s.vo || []).map(([en], i) => {
    const d = voice[s.id] ? voice[s.id][i].dur : readTime(en);
    const r = { at: t, dur: d };
    t += d + GAP;
    return r;
  });
};
const minDur = (s) => { const x = sentences(s).at(-1); return x ? x.at + x.dur + (voice[s.id] ? 0.55 : 0.45) : 0; };

if (toRecord.length) {
  if (toRecord.some((s) => s.kind === 'app') && !(ACC.fluid && ACC.demo && ACC.network)) {
    console.error(`Zugänge fehlen (fluid, demo, network): ${path.relative(ROOT, accFile)} bzw. TUT_ACCOUNTS`);
    process.exit(1);
  }
  const browser = await chromium.launch({ headless: !flag('--headed'), args: ['--lang=de-DE', '--hide-scrollbars'] });
  const common = { viewport: { width: W, height: H }, deviceScaleFactor: DPR, locale: 'de-DE', colorScheme: 'light', reducedMotion: 'no-preference' };
  const own = [];   // nur eigene Kontexte schließen
  try {
    // --- Bühne: Titelkarten und Theme-Montage (ohne Anmeldung)
    const stageCtx = await browser.newContext(common); own.push(stageCtx);
    let stageDoc = '';
    await stageCtx.route('http://trailer.stage/**', (route) => {
      const u = new URL(route.request().url());
      if (u.pathname.startsWith('/fonts/')) return route.fulfill({ path: path.join(ROOT, 'public/assets/fonts', path.basename(u.pathname)), contentType: 'font/woff2' });
      return route.fulfill({ contentType: 'text/html; charset=utf-8', body: stageDoc });
    });
    // Websites dürfen sich sonst nicht in fremde Seiten einbetten lassen (X-Frame-Options/CSP) – nur in dieser Aufnahme aufheben
    const stageHosts = Object.values(SITES).filter(Boolean).map((u) => new URL(u).host);
    await stageCtx.route((u) => stageHosts.includes(u.host), async (route) => {
      if (route.request().resourceType() !== 'document') return route.continue();
      const r = await route.fetch();
      const h = { ...r.headers() }; delete h['x-frame-options']; delete h['content-security-policy'];
      return route.fulfill({ response: r, headers: h });
    });
    await stageCtx.addInitScript(() => {
      if (window === window.top) return;
      // Scrollleisten und Cookie-Hinweis (Consent-Kit) in den Kit-Fenstern ausblenden – wie nach einer Entscheidung der Besucher
      const hide = () => { const st = document.createElement('style'); st.textContent = 'html{scrollbar-width:none}html::-webkit-scrollbar{display:none}consent-kit{display:none!important}'; document.head?.append(st); };
      document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', hide) : hide();
    });
    const stagePage = await stageCtx.newPage();
    const stageRec = new Rec(stagePage); await stageRec.attach();

    // --- Verwaltung/Website: angemeldet, schreibende Anfragen werden abgefangen
    const appCtx = await browser.newContext({ ...common, bypassCSP: true }); own.push(appCtx);   // bypassCSP: Stil-Einblendung (Cookie-Hinweis aus) trotz CSP
    const PASS = /\/admin\/(login|api\/(block-form|preview|blocks\/preview|blocks\/sample-form|design-preview|settings-preview|search|ai\/text|ai\/page-propose|ai\/table-propose))$/;
    const blocked = [];
    await appCtx.route('**/*', (route) => {
      const r = route.request();
      if (['GET', 'HEAD', 'OPTIONS'].includes(r.method())) return route.continue();
      const p = new URL(r.url()).pathname;
      if (PASS.test(p)) return route.continue();
      blocked.push(r.method() + ' ' + p);
      const now = new Date();
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, saved_at: `${now.getHours()}:${String(now.getMinutes()).padStart(2, '0')}` }) });
    });
    await appCtx.addInitScript(OVERLAY);
    await appCtx.addInitScript(() => { const css = () => { const st = document.createElement('style'); st.textContent = 'consent-kit{display:none!important}'; (document.head || document.documentElement).append(st); }; document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', css) : css(); });
    const appPage = await appCtx.newPage();
    appPage.setDefaultTimeout(20000);
    // Rückfragen: Seite verlassen trotz ungespeicherter Änderungen = ja (nichts wird gespeichert), alles andere ablehnen
    appPage.on('dialog', (d) => (d.type() === 'beforeunload' ? d.accept() : d.dismiss()).catch(() => {}));
    try {   // Passkey-Autofill in Headless-Chromium: virtueller Authenticator (wie bei den Tutorials)
      const cdp = await appCtx.newCDPSession(appPage);
      await cdp.send('WebAuthn.enable');
      await cdp.send('WebAuthn.addVirtualAuthenticator', { options: { protocol: 'ctap2', transport: 'internal', hasResidentKey: true, hasUserVerification: true, isUserVerified: true } });
    } catch { /* ohne */ }
    const appRec = new Rec(appPage); await appRec.attach();
    if (toRecord.some((s) => s.kind === 'app')) {
      for (const k of ['fluid', 'demo', 'network']) {
        const acc = ACC[k];
        await appPage.goto(base(acc.url) + '/admin/login');
        if (!appPage.url().includes('/login')) continue;
        await appPage.fill('#email', acc.email);
        await appPage.fill('#password', acc.password);
        await Promise.all([appPage.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 15000 }).catch(() => {}), appPage.click('button[type=submit]')]);
        if (appPage.url().includes('/login')) throw new Error('Anmeldung fehlgeschlagen: ' + k);
      }
    }

    for (const shot of shots) {
      if (!toRecord.includes(shot)) continue;
      if (shot.needs && failed.includes(shot.needs)) { console.log(`● ${shot.id.padEnd(18)} übersprungen (${shot.needs} fehlgeschlagen)`); failed.push(shot.id); continue; }
      const frameDir = path.join(WORK, 'frames', shot.id);
      const clip = path.join(WORK, shot.id + '.mp4');
      process.stdout.write(`● ${shot.id.padEnd(18)}`);
      let rec, end;
      try {
      if (shot.kind === 'card' || shot.kind === 'stage') {
        stageDoc = shot.kind === 'card' ? cardHtml(...shot.card) : shot.stage();
        await stagePage.goto('http://trailer.stage/' + shot.id, { waitUntil: 'load' });
        const frames = [];
        for (let i = 0; await stagePage.$('#f' + i); i++) frames.push(await (await stagePage.$('#f' + i)).contentFrame());
        await Promise.all(frames.map((f) => f.waitForLoadState('load').catch(() => {})));
        await Promise.all(frames.map((f) => f.evaluate(() => Promise.race([document.fonts?.ready, new Promise((r) => setTimeout(r, 3000))])).catch(() => {})));
        await stagePage.evaluate(() => Promise.race([document.fonts?.ready, new Promise((r) => setTimeout(r, 3000))]));
        await stagePage.waitForTimeout(shot.kind === 'stage' ? 900 : 150);
        rec = stageRec;
        const s = new Shot(stagePage, rec, shot);
        rec.start(frameDir);
        if (shot.kind === 'stage') {
          await stagePage.evaluate(() => setTimeout(() => document.getElementById('lbl')?.classList.add('on'), 350));
          await watchdog(shot.run(s, frames), shot.id);
        }
        const need = Math.max(shot.dur || 0, minDur(shot)) * (shot.speed || 1);
        while (rec.elapsed() < need) await sleep(20);
        end = await rec.stop();
        if (shot.after) await shot.after(s);
      } else {
        rec = appRec;
        const s = new Shot(appPage, null, shot);
        s.x = 640; s.y = 420;
        if (shot.prep) await shot.prep(s);
        await appPage.evaluate(() => window.__trLabel?.(''));
        await s.sync();
        s.rec = rec;
        rec.start(frameDir);
        await watchdog(shot.run(s), shot.id);
        const need = minDur(shot) * (shot.speed || 1);
        while (rec.elapsed() < need) await sleep(20);
        end = await rec.stop();
      }
      } catch (e) {
        failed.push(shot.id);
        console.log(` ✗ ${e.message.split('\n')[0]}`);
        await rec?.stop().catch(() => {});
        await (shot.kind === 'app' ? appPage : stagePage).screenshot({ path: path.join(WORK, shot.id + '-fehler.png') }).catch(() => {});
        continue;
      }
      encodeClip(rec.frames, end, clip, shot.speed || 1);
      clips[shot.id] = { dur: +probe(clip).toFixed(3), frames: rec.frames.length, recorded: new Date().toISOString() };
      fs.writeFileSync(clipsFile, JSON.stringify(clips, null, 1));
      if (!flag('--keep')) fs.rmSync(frameDir, { recursive: true, force: true });
      console.log(` ${clips[shot.id].dur.toFixed(2)} s · ${(rec.frames.length / end).toFixed(0)} B/s`);
    }
    if (blocked.length) console.log(`Abgefangen (nicht gespeichert): ${[...new Set(blocked)].join(', ')}`);
  } finally {
    for (const c of own) await c.close().catch(() => {});
    await browser.close();
  }
}

/* ============================================================ Schnitt: Überblendungen, Ton, Untertitel */
if (failed.length) { console.error('Fehlgeschlagen: ' + failed.join(', ') + ' – kein Schnitt (Bildschirmfotos in tools/trailer/.work/*-fehler.png)'); process.exit(1); }
const missing = shots.filter((s) => !clips[s.id] || !fs.existsSync(path.join(WORK, s.id + '.mp4')));
if (missing.length) { console.log('Noch nicht aufgenommen (kein Schnitt): ' + missing.map((s) => s.id).join(', ')); process.exit(only.length ? 0 : 1); }
// Sprecher länger als der aufgenommene Shot (z. B. andere Stimme, nur --cut): letztes Bild halten statt neu aufnehmen
const clipFile = {}, clipDur = {};
for (const s of shots) {
  const src = path.join(WORK, s.id + '.mp4'), need = minDur(s), have = clips[s.id].dur;
  clipFile[s.id] = src; clipDur[s.id] = have;
  if (need > have + 0.02) {
    const padded = path.join(WORK, s.id + '.pad.mp4');
    ff('-i', src, '-vf', `tpad=stop_mode=clone:stop_duration=${(need - have).toFixed(3)}`, '-an', '-c:v', 'libx264', '-preset', 'medium', '-crf', '14', '-r', String(FPS), padded);
    clipFile[s.id] = padded; clipDur[s.id] = +probe(padded).toFixed(3);
    console.log(`  ${s.id}: ${voice[s.id] ? 'Sprecher' : 'Lesezeit'} ${need.toFixed(2)} s → Shot ${have.toFixed(2)} → ${clipDur[s.id].toFixed(2)} s (letztes Bild gehalten)`);
  }
}
let t = 0;
const timeline = shots.map((s, i) => {
  const x = i ? s.xfade ?? 0.3 : 0;
  const start = i ? t - x : 0;
  t = start + clipDur[s.id];
  return { ...s, start, x, dur: clipDur[s.id] };
});
const total = t;
const master = path.join(WORK, 'master.mp4');
{
  const inputs = timeline.flatMap((c) => ['-i', clipFile[c.id]]);
  let chain = '', prev = '0:v';
  timeline.slice(1).forEach((c, k) => {
    const i = k + 1, lab = i === timeline.length - 1 ? 'vout' : 'v' + i;
    chain += `[${prev}][${i}:v]xfade=transition=fade:duration=${c.x.toFixed(3)}:offset=${c.start.toFixed(3)}[${lab}];`;
    prev = lab;
  });
  chain += `[vout]fade=t=in:st=0:d=0.5,fade=t=out:st=${(total - 0.7).toFixed(3)}:d=0.7,format=yuv420p[v]`;
  ff(...inputs, '-filter_complex', chain, '-map', '[v]', '-c:v', 'libx264', '-preset', 'medium', '-crf', '14', '-r', String(FPS), master);
}
// Untertitel je Satz: en = gesprochener Text, de/sl = Übersetzung; Zeiten = Sprechzeiten (ohne Ton: Lesezeit bzw. bis Shot-Ende)
const LANGS = { en: 'English', de: 'Deutsch', sl: 'Slovenščina' };
const cues = timeline.flatMap((c, ci) => {
  const ss = sentences(c), nextStart = timeline[ci + 1] ? timeline[ci + 1].start + VOICE_AT - 0.1 : c.start + c.dur;
  return ss.map((x, i) => {
    const t0 = c.start + x.at;
    const hardEnd = i + 1 < ss.length ? c.start + ss[i + 1].at - 0.05 : nextStart;
    const end = voice[c.id] ? Math.min(t0 + x.dur + 0.35, hardEnd) : (i + 1 < ss.length ? t0 + x.dur : Math.max(t0 + x.dur, Math.min(hardEnd, c.start + c.dur - 0.3)));
    const [en, de, sl] = c.vo[i];
    return { t: t0, end, en, de, sl, file: voice[c.id]?.[i].file };
  });
});
const ts = (s) => { const h = Math.floor(s / 3600), m = Math.floor(s / 60) % 60, sec = (s % 60).toFixed(3).padStart(6, '0'); return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${sec}`; };
/** Zeilen für Untertitel: höchstens ~42 Zeichen, an Wortgrenzen möglichst mittig umbrechen */
const wrap = (txt) => {
  if (txt.length <= 42) return txt;
  const mid = txt.length / 2; let best = -1;
  for (let i = 0; i < txt.length; i++) if (txt[i] === ' ' && (best < 0 || Math.abs(i - mid) < Math.abs(best - mid))) best = i;
  return best < 0 ? txt : txt.slice(0, best) + '\n' + txt.slice(best + 1);
};
for (const lang of Object.keys(LANGS)) fs.writeFileSync(path.join(OUT, `${NAME}.${lang}.vtt`), 'WEBVTT\n\n' + cues.map((c, i) => `${i + 1}\n${ts(c.t)} --> ${ts(c.end)}\n${wrap(c[lang])}\n`).join('\n'));

let audio = null;
if (AUDIO && VO && cues.every((c) => c.file)) {
  const { buildTrack } = await import('./voice.mjs');
  audio = path.join(WORK, 'voice.en.wav');
  buildTrack(cues.map((c) => ({ t: c.t, file: c.file })), total, audio);
}
/** Lautheit −16 LUFS (Sprache, keine Musik), Spitzen ≤ −1,5 dBTP – zweistufig (messen, dann linear anpassen) */
function loudnorm(src) {
  const m = ffErr('-i', src, '-af', 'loudnorm=I=-16:TP=-1.5:LRA=11:print_format=json', '-f', 'null', '-');
  const j = JSON.parse(m.slice(m.lastIndexOf('{'), m.lastIndexOf('}') + 1));
  return `highpass=f=70,loudnorm=I=-16:TP=-1.5:LRA=11:measured_I=${j.input_i}:measured_TP=${j.input_tp}:measured_LRA=${j.input_lra}:measured_thresh=${j.input_thresh}:offset=${j.target_offset}:linear=true,aresample=48000`;
}
const af = audio ? ['-i', audio, '-map', '0:v', '-map', '1:a', '-af', loudnorm(audio), '-ac', '1', '-metadata:s:a:0', 'language=eng', '-metadata:s:a:0', 'title=English'] : ['-map', '0:v'];
const meta = ['-metadata', 'title=KLXM Studio – Trailer', '-metadata', 'artist=KLXM Crossmedia', '-metadata', 'copyright=KLXM Crossmedia, Moers – MIT',
  '-metadata', `comment=KLXM Studio (MIT). ${audio ? 'Sprecher (Englisch): ' + VO.credit + '. ' : 'Ohne Ton. '}Untertitel: Deutsch, English, Slovenščina. Bildschirmaufnahmen der Testkopie (Demo-Inhalte, eigene Fotos/erzeugte Bilder).`];
const outBase = path.join(OUT, NAME);
console.log(`Schnitt: ${total.toFixed(1)} s, ${timeline.length} Shots – kodieren …`);
ff('-i', master, ...af, '-c:v', 'libx264', '-preset', 'slow', '-crf', '21', '-profile:v', 'high', '-pix_fmt', 'yuv420p', ...(audio ? ['-c:a', 'aac', '-b:a', '128k', '-ar', '48000'] : []), ...meta, '-movflags', '+faststart', outBase + '.mp4');
ff('-i', master, ...af, '-vf', 'scale=1280:720:flags=lanczos', '-c:v', 'libx264', '-preset', 'slow', '-crf', '23', '-profile:v', 'high', '-pix_fmt', 'yuv420p', ...(audio ? ['-c:a', 'aac', '-b:a', '96k', '-ar', '48000'] : []), ...meta, '-movflags', '+faststart', outBase + '-720.mp4');
ff('-i', master, ...af, '-c:v', 'libvpx-vp9', '-crf', '33', '-b:v', '0', '-row-mt', '1', '-deadline', 'good', '-cpu-used', '2', '-pix_fmt', 'yuv420p', ...(audio ? ['-c:a', 'libopus', '-b:a', '80k'] : []), ...meta, outBase + '.webm');
const posterAt = timeline.find((c) => c.id === 'intro').dur - 0.8;
ff('-ss', posterAt.toFixed(2), '-i', master, '-frames:v', '1', '-q:v', '3', outBase + '.jpg');
const sz = (e) => fs.statSync(outBase + e).size;
const info = {
  duration: +probe(outBase + '.mp4').toFixed(2), width: 1920, height: 1080, shots: timeline.length,
  mp4: sz('.mp4'), webm: sz('.webm'), mp4_720: sz('-720.mp4'), jpg: sz('.jpg'),
  audio: audio ? { en: VO.name, credit: VO.credit, lufs: -16 } : false, captions: Object.keys(LANGS), recorded: new Date().toISOString().slice(0, 10),
  timeline: timeline.map((c) => ({ id: c.id, start: +c.start.toFixed(2), dur: c.dur })),
};
fs.writeFileSync(path.join(OUT, 'trailer.json'), JSON.stringify(info, null, 1) + '\n');
console.log(`✓ ${info.duration} s · MP4 ${(info.mp4 / 1048576).toFixed(1)} MB · 720p ${(info.mp4_720 / 1048576).toFixed(1)} MB · WebM ${(info.webm / 1048576).toFixed(1)} MB → ${path.relative(ROOT, OUT)}`);
