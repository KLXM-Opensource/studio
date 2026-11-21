/*
 * Grafik-Editor – Prüfen (Ausbaustufe 3b): Barrierefreiheit und Hell/Dunkel in einem Bereich, ohne zu blockieren.
 *   - Titel/Beschreibung fehlen, Alt-Text der Mediathek fehlt (Vorschlag aus Titel und Texten)
 *   - Kontrast von Text zur Fläche darunter (4,5:1, große Schrift 3:1) – hell und dunkel, auf dem gewählten Abschnitts-Hintergrund
 *   - wichtige Nicht-Text-Elemente (Linien, Konturen, Verbinder, kleine Formen) mindestens 3:1 (WCAG 1.4.11)
 *   - verschwindet im Dunkeln (z. B. schwarze Kontur, Schatten auf dunklem Grund)
 *   - Schrift kleiner als 12 px in der vorgesehenen Größe (Dokument → „Breite auf der Website“)
 *   - Lesereihenfolge: Diagramme ohne beschriebenen Ablauf, Texte in anderer Reihenfolge als sichtbar
 * Ergebnis: Liste mit Sprung zum Element; Markierungen auf der Fläche für die gerade gezeigte Fassung. MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { ico } from '../_icons.js';
import { docBounds, docMatrix } from './geom.js';
import { getProp, isHidden, nameOf, typeLabel } from './doc.js';
import { parseColor, toHex, contrast, over } from './color.js';
import { paintIn, sectionBg } from './scheme.js';
import { modal } from './ui.js';

const SKIP = 'defs,clipPath,mask,marker,symbol,pattern,title,desc,metadata';
const SHAPES = 'rect,circle,ellipse,path,polygon,polyline,line';
const MAX = 800;

const opa = (el, root) => { let a = 1; for (let n = el; n && n !== root.parentNode; n = n.parentNode) { const o = parseFloat(n.getAttribute?.('opacity') ?? ''); if (Number.isFinite(o)) a *= o; } return a; };
const hidden = (el, root) => { for (let n = el; n && n !== root; n = n.parentNode) if (isHidden(n)) return true; return false; };

/** Fläche hinter der Mitte eines Elements in einem Schema: Abschnitts-Hintergrund, darüber die Flächen davor (mit Deckkraft) */
export function backgroundIn(ed, el, mode, at = null) {
  let bg = parseColor(sectionBg(ed, mode)) || { r: 255, g: 255, b: 255 };
  const b = at || docBounds(el, ed.root);
  if (!b) return bg;
  const cx = b.x + b.w / 2, cy = b.y + b.h / 2;
  for (const s of ed.root.querySelectorAll(SHAPES)) {
    if (s === el || el.contains(s)) break;
    if (!(s.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING)) break;
    if (s.closest(SKIP) || hidden(s, ed.root)) continue;
    const sb = docBounds(s, ed.root);
    if (!sb || cx < sb.x || cx > sb.x + sb.w || cy < sb.y || cy > sb.y + sb.h) continue;
    const c = parseColor(paintIn(ed, s, 'fill', mode));
    if (!c) continue;
    const a = c.a * (parseFloat(getProp(s, 'fill-opacity') || '1') || 1) * opa(s, ed.root);
    bg = over(c, Math.max(0, Math.min(1, a)), bg);
  }
  return bg;
}

/** Maßstab Zeichenfläche → Pixel auf der Website (data-ge-dispw = vorgesehene Breite) */
export function displayScale(ed) {
  const vb = ed.vb;
  const w = parseFloat(ed.root.getAttribute('data-ge-dispw') || '') || vb.w;
  return w / vb.w;
}

const scaleOf = (el, root) => { const m = docMatrix(el, root); return Math.sqrt(Math.abs(m.a * m.d - m.b * m.c)) || 1; };

/** Vorschlag für den Alt-Text: Titel, sonst die Texte der Grafik in Lesereihenfolge (gekürzt) */
export function suggestAlt(ed) {
  const title = [...ed.root.children].find(c => c.localName === 'title')?.textContent.trim() || '';
  const texts = readingOrder(ed).map(x => x.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean);
  let s = title || texts.slice(0, 1).join('');
  const rest = texts.filter(x => x !== s).slice(0, 6).join(', ');
  if (rest) s = (s ? s + ': ' : '') + rest;
  return s.length > 240 ? s.slice(0, 237).replace(/\s+\S*$/, '') + ' …' : s;
}

/** Texte in sichtbarer Reihenfolge (Zeilen von oben, links nach rechts) */
export function readingOrder(ed) {
  const list = [...ed.root.querySelectorAll('text')].filter(x => !x.closest(SKIP) && !hidden(x, ed.root)).map(el => ({ el, b: docBounds(el, ed.root) })).filter(x => x.b);
  const tol = Math.max(4, ed.vb.h / 60);
  list.sort((a, b) => Math.abs(a.b.y - b.b.y) > tol ? a.b.y - b.b.y : a.b.x - b.b.x);
  return list.map(x => x.el);
}

/**
 * Alle Hinweise: [{ key, sev: 'error'|'warn'|'info', mode: 'light'|'dark'|null, el, title, detail, fix? }]
 */
export function analyze(ed) {
  const out = [];
  const root = ed.root, vb = ed.vb, f = ed.format;
  const add = (o) => out.push(o);
  const title = [...root.children].find(c => c.localName === 'title')?.textContent.trim() || '';
  const desc = [...root.children].find(c => c.localName === 'desc')?.textContent.trim() || '';
  const texts = [...root.querySelectorAll('text')].filter(x => !x.closest(SKIP) && !hidden(x, root));
  const conns = root.querySelectorAll('path[data-ge-conn]');
  if (f === 'info' && !title) add({ key: 'title', sev: 'warn', mode: null, title: t('Titel fehlt'), detail: t('Ein kurzer Titel (<title>) nennt Screenreadern, was die Grafik zeigt.'), fix: { label: t('Titel eingeben …'), run: () => ed.docSettings() } });
  if (f === 'info' && !desc && (texts.length >= 3 || conns.length)) add({ key: 'desc', sev: 'info', mode: null, title: t('Beschreibung fehlt'), detail: t('Infografiken mit mehreren Aussagen brauchen eine ausführliche Beschreibung (<desc>).'), fix: { label: t('Aus den Texten vorschlagen'), run: () => { ed.setA11y(title, readingOrder(ed).map(x => x.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean).join('\n')); ed.commit(t('Beschreibung vorgeschlagen')); } } });
  if (f === 'info' && ed.doc.target === 'media' && ed.doc.mediaId && !(ed.doc.alt || '').trim() && !ed.doc.decorative) add({ key: 'alt', sev: 'warn', mode: null, title: t('Alt-Text fehlt'), detail: t('Vorschlag: {s}', { s: suggestAlt(ed) || '–' }), fix: { label: t('Alt-Text eingeben …'), run: () => askAlt(ed) } });
  if (conns.length && !/ablauf|→|->|flow/i.test(desc)) add({ key: 'order', sev: 'warn', mode: null, title: t('Lesereihenfolge des Diagramms fehlt'), detail: t('Der Ablauf der Verbinder steht nicht in der Beschreibung – Screenreader kennen sonst nur einzelne Kästen.'), fix: { label: t('Ablauf als Beschreibung einfügen'), run: () => { ed.setA11y(title, (desc ? desc + '\n' : '') + t('Ablauf:') + '\n' + ed.conn.describe()); ed.commit(t('Ablauf als Beschreibung')); } } });
  // Texte in anderer Reihenfolge als sichtbar (Screenreader lesen in der Reihenfolge der Datei)
  if (texts.length >= 3 && !conns.length) {
    const vis = readingOrder(ed);
    const moved = vis.filter((el, i) => texts.indexOf(el) !== i).length;
    if (moved > Math.max(1, vis.length / 3)) add({ key: 'textorder', sev: 'info', mode: null, el: vis[0], title: t('Texte stehen in anderer Reihenfolge als sichtbar'), detail: t('Screenreader lesen die Texte in der Reihenfolge der Ebenen. Am besten Texte in den Ebenen von oben nach unten ordnen oder die Aussage in der Beschreibung zusammenfassen.') });
  }
  const scale = displayScale(ed);
  const modes = ['light', 'dark'];
  const name = el => nameOf(el) || typeLabel(el);
  // Texte: Kontrast und Größe
  let n = 0;
  for (const tx of texts) {
    if (++n > MAX) break;
    const fsz = parseFloat(getProp(tx, 'font-size')) || 16;
    const px = fsz * scaleOf(tx, root) * scale;
    const fw = parseInt(getProp(tx, 'font-weight'), 10) || (getProp(tx, 'font-weight') === 'bold' ? 700 : 400);
    const large = px >= 24 || (px >= 18.66 && fw >= 700);
    if (px < 12 - 0.05) add({ key: 'size', sev: 'warn', mode: null, el: tx, title: t('Schrift zu klein: {n} px', { n: px.toFixed(1).replace('.', ',') }), detail: t('„{name}“ ist in der vorgesehenen Größe kleiner als 12 px. Größer setzen oder unter Dokument die Breite auf der Website anpassen.', { name: name(tx) }) });
    const op = opa(tx, root) * (parseFloat(getProp(tx, 'fill-opacity') || '1') || 1);
    const ratios = {};
    for (const m of modes) {
      const fc = parseColor(paintIn(ed, tx, 'fill', m));
      if (!fc) continue;
      const bg = backgroundIn(ed, tx, m);
      ratios[m] = contrast(over(fc, op * fc.a, bg), bg);
      const need = large ? 3 : 4.5;
      if (ratios[m] < need) add({ key: 'tc-' + m, sev: ratios[m] < 3 ? 'error' : 'warn', mode: m, el: tx, title: t('Kontrast zu gering: {r}:1', { r: ratios[m].toFixed(2).replace('.', ',') }), detail: t('Text „{name}“ braucht mindestens {n}:1 ({mode}).', { name: name(tx).slice(0, 40), n: String(need).replace('.', ','), mode: m === 'dark' ? t('Dunkel') : t('Hell') }) });
    }
  }
  // Nicht-Text: Linien, Konturen, Verbinder, kleine Formen
  const area = vb.w * vb.h;
  n = 0;
  for (const s of root.querySelectorAll(SHAPES)) {
    if (s.closest(SKIP) || s.closest('text') || hidden(s, root)) continue;
    if (++n > MAX) break;
    const fill = paintIn(ed, s, 'fill', 'light'), stroke = paintIn(ed, s, 'stroke', 'light');
    const hasStroke = stroke && stroke !== 'none' && !/^url/.test(stroke) && (parseFloat(getProp(s, 'stroke-width')) || 1) * scaleOf(s, root) * scale >= 0.5;
    const hasFill = fill && fill !== 'none' && !/^url/.test(fill) && s.localName !== 'line';
    const b = docBounds(s, root);
    if (!b) continue;
    const small = b.w * b.h < area * 0.04;
    const isConn = s.hasAttribute('data-ge-conn');
    // Fläche mit Kontur: die Kontur zählt; große Flächen ohne Kontur sind Hintergrund/Schmuck
    const which = hasStroke && (!hasFill || isConn) ? 'stroke' : hasFill && small ? 'fill' : hasStroke ? 'stroke' : null;
    if (!which) continue;
    const r = {};
    for (const m of modes) {
      const c = parseColor(paintIn(ed, s, which, m));
      if (!c) continue;
      const bg = backgroundIn(ed, s, m);
      const op = opa(s, root) * (parseFloat(getProp(s, which + '-opacity') || '1') || 1);
      r[m] = contrast(over(c, op * c.a, bg), bg);
    }
    const label = which === 'stroke' ? t('Linie') : t('Form');
    if (r.light >= 3 && r.dark !== undefined && r.dark < 1.5) {
      add({ key: 'vanish', sev: 'error', mode: 'dark', el: s, title: t('Verschwindet im Dunkeln ({r}:1)', { r: r.dark.toFixed(2).replace('.', ',') }), detail: t('{what} „{name}“ ist auf dunklem Grund kaum zu sehen. „Dunkel-Fassung vorschlagen“ oder eine eigene Farbe für Dunkel wählen.', { what: label, name: name(s).slice(0, 40) }) });
      continue;
    }
    for (const m of modes) if (r[m] !== undefined && r[m] < 3 && (which === 'stroke' || isConn)) add({ key: 'nc-' + m, sev: 'warn', mode: m, el: s, title: t('{what}: Kontrast {r}:1', { what: label, r: r[m].toFixed(2).replace('.', ',') }), detail: t('Wichtige Linien und Symbole brauchen mindestens 3:1 zur Umgebung ({mode}). Rein schmückende Elemente dürfen schwächer sein.', { mode: m === 'dark' ? t('Dunkel') : t('Hell') }) });
  }
  // Schatten (feDropShadow mit dunkler Farbe) wirkt auf dunklem Grund nicht
  for (const fe of root.querySelectorAll('feDropShadow')) {
    const c = parseColor(fe.getAttribute('flood-color') || '#000');
    const user = root.querySelector(`[filter="url(#${CSS.escape(fe.parentNode.id || '')})"]`);
    if (c && user && (0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b) / 255 < 0.25) add({ key: 'shadow', sev: 'info', mode: 'dark', el: user, title: t('Schatten im Dunkeln kaum sichtbar'), detail: t('Dunkle Schatten verschwinden auf dunklem Grund – dort wirkt eher ein helles Leuchten (Ebeneneffekte).') });
  }
  // Bewegung und Interaktion (Persona Motion): endlose Schleifen, Links ohne Namen, Fokus- gegen Lesereihenfolge
  ed.motion?.checks(add);
  ed.dataP?.checks(add);
  return out;
}

/** Alt-Text der Mediathek eingeben (wird beim nächsten Speichern übernommen) */
async function askAlt(ed) {
  const r = await modal(ed.el, { title: t('Alt-Text'), ok: t('Übernehmen'), body: `<label class="f">${esc(t('Alt-Text (Bildbeschreibung)'))}<textarea name="alt" rows="3" maxlength="250" required>${esc(ed.doc.alt || suggestAlt(ed))}</textarea><span class="f-help">${esc(t('Wird beim nächsten Speichern in der Mediathek übernommen.'))}</span></label>` });
  if (!r) return;
  ed.doc.alt = r.alt.trim();
  ed.doc.altDirty = true;
  ed.savedAt = -1; ed.changed(false);
  ed.panels.showCheck(false);
}

/** Bereich „Prüfen“ (Reiter im dritten Bereich) */
export function buildCheckPanel(ed, panel) {
  let issues = [];
  let T = 0;
  const sevIco = { error: 'warning', warn: 'warning', info: 'info' };
  function render() {
    const by = m => issues.filter(i => i.mode === m).length;
    panel.innerHTML = `<div class="ge-check">
      <p class="ge-check__sum" aria-live="polite">${issues.length ? esc(t('{n} Hinweise · Hell {l} · Dunkel {d} · Allgemein {g}', { n: issues.length, l: by('light'), d: by('dark'), g: by(null) })) : `${ico('check-circle')} ${esc(t('Keine Hinweise – Kontrast, Größen und Beschreibung passen.'))}`}</p>
      <div class="ge-check__acts"><button type="button" class="adm-btn adm-btn--small" data-rerun>${ico('arrows-clockwise')} ${esc(t('Erneut prüfen'))}</button>
        <label class="ge-lbl ge-chk"><input type="checkbox" data-marks${ed.checkMarks !== false ? ' checked' : ''}> ${esc(t('Auf der Fläche markieren'))}</label></div>
      <ul class="ge-check__list">${issues.map((it, i) => `<li class="ge-check__it ge-check__it--${it.sev}">
        <button type="button" class="ge-check__jump" data-i="${i}"${it.el ? '' : ' data-noel'}>${ico(sevIco[it.sev])}<span><b>${esc(it.title)}</b>${it.mode ? `<span class="ge-check__mode">${esc(it.mode === 'dark' ? t('Dunkel') : t('Hell'))}</span>` : ''}<small>${esc(it.detail)}</small></span></button>
        ${it.fix ? `<button type="button" class="adm-btn adm-btn--small" data-fix="${i}">${esc(it.fix.label)}</button>` : ''}</li>`).join('')}</ul></div>`;
    panel.querySelector('[data-rerun]').addEventListener('click', () => run());
    panel.querySelector('[data-marks]').addEventListener('change', e => { ed.checkMarks = e.target.checked; ed.draw(); });
    panel.querySelectorAll('[data-i]').forEach(b => b.addEventListener('click', () => jump(issues[+b.dataset.i])));
    panel.querySelectorAll('[data-fix]').forEach(b => b.addEventListener('click', async () => { await issues[+b.dataset.fix].fix.run(); run(); }));
  }
  function jump(it) {
    if (!it?.el || !it.el.isConnected) return;
    // Fassung zeigen, in der das Problem auftritt
    if (it.mode === 'dark' && ed.sv.mode === 'light') ed.sv.set('dark');
    if (it.mode === 'light' && ed.sv.mode === 'dark') ed.sv.set('light');
    // in die Gruppe des Elements gehen (Auswahl wie per Doppelklick), dann auswählen und ins Bild holen
    let el = it.el;
    const parent = el.parentNode;
    ed.scope = parent && parent !== ed.root && ['g', 'switch'].includes(parent.localName) ? parent : ed.root;
    if (el.closest('text') && el.localName !== 'text') el = el.closest('text');
    ed.select([el]);
    const b = docBounds(el, ed.root), r = ed.stage.getBoundingClientRect();
    if (b) {
      const a = ed.toOv({ x: b.x, y: b.y }), z = ed.toOv({ x: b.x + b.w, y: b.y + b.h });
      if (a.x < 0 || a.y < 0 || z.x > r.width || z.y > r.height) { ed.panX += r.width / 2 - (a.x + z.x) / 2; ed.panY += r.height / 2 - (a.y + z.y) / 2; ed.layout(); }
    }
    ed.stage.focus();
    ed.announce(it.title);
  }
  function run() {
    try { issues = analyze(ed); } catch (e) { console.error(e); issues = []; }
    ed.checkIssues = issues;
    if (!panel.hidden) render();
    ed.draw();
    return issues;
  }
  /** nach Änderungen gedrosselt neu prüfen, solange der Bereich offen ist oder Markierungen gelten */
  function soon() { clearTimeout(T); T = setTimeout(run, 300); }
  return { run, soon, render, get issues() { return issues; } };
}

/** Markierungen der Hinweise auf der Fläche (für die gezeigte Fassung) – Teil der Überlagerung */
export function checkOverlay(ed) {
  if (ed.checkMarks === false || !ed.checkIssues?.length) return '';
  const mode = ed.sv.mode === 'dark' ? 'dark' : 'light';
  const seen = new Set();
  let s = '';
  for (const it of ed.checkIssues) {
    if (!it.el || !it.el.isConnected || (it.mode && it.mode !== mode && ed.sv.mode !== 'side') || seen.has(it.el)) continue;
    seen.add(it.el);
    const b = docBounds(it.el, ed.root);
    if (!b) continue;
    const p = ed.toOv({ x: b.x + b.w, y: b.y });
    s += `<g class="ge-ov__issue ge-ov__issue--${it.sev}" transform="translate(${p.x.toFixed(1)} ${p.y.toFixed(1)})"><title>${esc(it.title)}</title><circle r="7"/><path d="M0 -3.5V1M0 3.2v.3"/></g>`;
  }
  return s;
}

export { toHex };
