/*
 * Grafik-Editor – Dokument: das SVG selbst ist das Modell (keine eigene Szenenbeschreibung). Unveränderte Elemente bleiben
 * Zeichen für Zeichen erhalten; geändert wird nur, was bearbeitet wird. Hier: Eigenschaften lesen/setzen (Attribut oder
 * Stil – Klassenregeln aus <style> schlagen Attribute), Transformation anwenden (wenn möglich in die Geometrie einrechnen),
 * Namen, Sperren, Definitionen (Verläufe, Pfeilspitzen, Schatten). MIT
 */
import { NS, num, localMatrix, parentMatrix, fmtMatrix, parsePath, pathToString, transformPath } from './geom.js';
import { parseColor, toHex } from './color.js';
import { t } from '../_i18n.js';

/** Elemente, die in der Ebenenliste erscheinen und auswählbar sind */
export const DRAWN = ['g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'use', 'switch', 'svg'];
export const SHAPES = ['path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'use'];

export function isDrawn(el) {
  return el && el.nodeType === 1 && el.namespaceURI === NS && DRAWN.includes(el.localName);
}

/** Kinder, die gezeichnet werden (ohne defs, style, title, desc, metadata) */
export const drawnChildren = el => [...el.children].filter(isDrawn);

/** Bezeichnung eines Elements für Ebenen und Kontextleiste */
export function typeLabel(el) {
  return {
    g: t('Gruppe'), path: t('Kurve'), rect: t('Rechteck'), circle: t('Kreis'), ellipse: t('Ellipse'), line: t('Linie'),
    polyline: t('Linienzug'), polygon: t('Vieleck'), text: t('Text'), use: t('Verweis'), switch: t('Gruppe'), svg: t('Grafik'),
  }[el.localName] || el.localName;
}

export function nameOf(el) {
  const n = el.getAttribute('data-name');
  if (n) return n;
  if (el.localName === 'text') {
    // Zeilen mit eingefügtem Trennstrich (Silbentrennung, data-ge-soft=h) wieder zusammensetzen
    const s = !el.querySelector('tspan[data-ge-soft="h"]') ? el.textContent.trim().replace(/\s+/g, ' ') : [...el.childNodes].map(n => n.nodeType === 1 && n.getAttribute?.('data-ge-soft') === 'h' ? '\u0000' + n.textContent : n.textContent + ' ').join('')
      .replace(/-\s*\u0000/g, '').replace(/\u0000/g, '').trim().replace(/\s+/g, ' ');
    if (s) return s.slice(0, 40);
  }
  const id = el.getAttribute('id');
  if (id && !/^(svg_|path|rect|g|ge-)\d*/i.test(id)) return id.replace(/_x([0-9A-F]{2})_/g, (m, c) => String.fromCharCode(parseInt(c, 16))).replace(/_/g, ' ');
  return typeLabel(el);
}

export const isLocked = el => el.getAttribute('data-locked') === '1';
export const isHidden = el => el.getAttribute('display') === 'none' || el.style?.display === 'none';

/** Wert einer Darstellungseigenschaft: Stil, Attribut, sonst berechnet */
export function getProp(el, name, computed = true) {
  const s = el.style?.getPropertyValue(name);
  if (s) return s.trim();
  const a = el.getAttribute(name);
  if (a !== null) return a.trim();
  if (!computed) return '';
  try { return getComputedStyle(el).getPropertyValue(name).trim(); } catch { return ''; }
}

const norm = (name, v) => {
  if (v === null || v === undefined) return '';
  v = String(v).trim();
  if (/^url\(/.test(v)) return v.replace(/["']/g, "").replace(/\s/g, "");
  if (["fill", "stroke", "stop-color", "flood-color", "color"].includes(name)) {
    if (/^url\(/.test(v)) return v.replace(/["']/g, '').replace(/\s/g, '');
    const c = parseColor(v);
    return c ? toHex(c) + (c.a < 1 ? '/' + c.a.toFixed(2) : '') : v;
  }
  const n = parseFloat(v);
  return Number.isFinite(n) && /^[-+.\d]/.test(v) ? String(Math.round(n * 1000) / 1000) : v;
};

/**
 * Eigenschaft setzen: als Attribut; steht sie schon im style-Attribut oder gewinnt eine Klassenregel, als Stil.
 * value null/'' = entfernen (erbt wieder).
 */
/** CSS-Eigenschaften ohne Darstellungsattribut (nur im style-Attribut gültig – Core\Svg erlaubt sie dort) */
export const CSS_ONLY = ['font-variation-settings', 'font-feature-settings', 'font-variant-ligatures', 'font-variant-numeric', 'font-variant-caps',
  'font-variant-position', 'font-kerning', 'font-optical-sizing'];

/** Stylesheet der Dunkel-Ansicht (scheme.js setzt es, solange „Dunkel“ gezeigt wird) */
export const VIEW = { sheet: null };
const PAINTS = ['fill', 'stroke', 'stop-color', 'color'];

export function setProp(el, name, value) {
  if (value === null || value === undefined || value === '') {
    el.removeAttribute(name);
    el.style?.removeProperty(name);
    if (el.getAttribute('style') === '') el.removeAttribute('style');
    return;
  }
  value = String(value);
  if (CSS_ONLY.includes(name)) { el.removeAttribute(name); el.style.setProperty(name, value); return; }
  if (el.style?.getPropertyValue(name)) { el.style.setProperty(name, value); return; }
  el.setAttribute(name, value);
  let comp = '';
  // Dunkel-Ansicht (scheme.js): deren Stylesheet überdeckt Farben mit !important – für die Prüfung „gewinnt eine Klassenregel?“
  // kurz abschalten, sonst landete jede Farbe einer Design-Farbe zusätzlich im style
  const sheet = VIEW.sheet?.sheet && PAINTS.includes(name) && el.getRootNode() === VIEW.sheet.getRootNode() ? VIEW.sheet.sheet : null;
  if (sheet) sheet.disabled = true;
  try { comp = getComputedStyle(el).getPropertyValue(name); } catch { return; } finally { if (sheet) sheet.disabled = false; }
  if (comp && norm(name, comp) !== norm(name, value) && !/currentcolor/i.test(value)) el.style.setProperty(name, value);
}

/** Auf Formen anwenden – bei Gruppen auf alle enthaltenen Formen */
export function eachShape(el, fn) {
  if (['g', 'switch', 'svg'].includes(el.localName)) { for (const c of el.querySelectorAll(SHAPES.join(','))) fn(c); }
  else fn(el);
}

// ================================================================= Transformation

/** Füllung/Kontur hängen am Benutzerraum (userSpaceOnUse-Verlauf, Muster) oder Effekte am Element → nicht einrechnen */
function simplePaint(el, root) {
  for (const p of ['clip-path', 'mask']) {
    const v = getProp(el, p, false);
    if (v && v !== 'none') return false;
  }
  // Filter (z. B. Schatten) nur, wenn sein Bereich am Objekt hängt (filterUnits objectBoundingBox, der Standard)
  const fv = getProp(el, 'filter', false);
  if (fv && fv !== 'none') {
    const f = refOf(root, fv);
    if (!f || f.getAttribute('filterUnits') === 'userSpaceOnUse') return false;
  }
  for (const p of ['fill', 'stroke']) {
    const v = getProp(el, p);
    const m = /url\(\s*['"]?#([^'")\s]+)/.exec(v || '');
    if (!m) continue;
    const ref = root.querySelector('#' + CSS.escape(m[1]));
    if (!ref || ref.localName === 'pattern' || ref.getAttribute('gradientUnits') === 'userSpaceOnUse') return false;
  }
  return true;
}

const near = (a, b) => Math.abs(a - b) < 1e-9;

/** Gespeicherter Zustand eines Elements (alle Attribute) – Ausgangspunkt für Ziehen, Skalieren, Drehen */
export const snapshot = el => [...el.attributes].map(a => [a.name, a.value]);
export function restore(el, snap) {
  for (const a of [...el.attributes]) el.removeAttribute(a.name);
  for (const [n, v] of snap) el.setAttribute(n, v);
}

/**
 * Wendet die Dokument-Transformation T auf ein Element an. Wo möglich wird sie in die Geometrie eingerechnet (Rechteck,
 * Ellipse, Linie, Vieleck, Pfad, Text-Position) – Konturbreite bleibt, die Ausgabe bleibt lesbar. Sonst transform-Attribut.
 */
export function applyTransform(el, T, root) {
  const L = localMatrix(el), P = parentMatrix(el, root);
  const Lp = P.inverse().multiply(T).multiply(P).multiply(L);
  const plain = !el.hasAttribute('transform') || L.isIdentity;
  const axis = near(Lp.b, 0) && near(Lp.c, 0);
  const n = el.localName;
  const g = a => parseFloat(el.getAttribute(a)) || 0;
  if (plain && axis && simplePaint(el, root)) {
    const a = Lp.a, d = Lp.d, e = Lp.e, f = Lp.f;
    const X = v => a * v + e, Y = v => d * v + f;
    if (n === 'rect') {
      const x = X(g('x')), y = Y(g('y')), w = a * g('width'), hh = d * g('height');
      el.setAttribute('x', num(Math.min(x, x + w))); el.setAttribute('y', num(Math.min(y, y + hh)));
      el.setAttribute('width', num(Math.abs(w))); el.setAttribute('height', num(Math.abs(hh)));
      el.removeAttribute('transform'); return;
    }
    if (n === 'ellipse' || (n === 'circle' && near(Math.abs(a), Math.abs(d)))) {
      el.setAttribute('cx', num(X(g('cx')))); el.setAttribute('cy', num(Y(g('cy'))));
      if (n === 'circle') el.setAttribute('r', num(g('r') * Math.abs(a)));
      else { el.setAttribute('rx', num(g('rx') * Math.abs(a))); el.setAttribute('ry', num(g('ry') * Math.abs(d))); }
      el.removeAttribute('transform'); return;
    }
    if (n === 'line') {
      el.setAttribute('x1', num(X(g('x1')))); el.setAttribute('y1', num(Y(g('y1'))));
      el.setAttribute('x2', num(X(g('x2')))); el.setAttribute('y2', num(Y(g('y2'))));
      el.removeAttribute('transform'); return;
    }
    if (n === 'polyline' || n === 'polygon') {
      const v = (el.getAttribute('points') || '').trim().split(/[\s,]+/).map(Number);
      const out = [];
      for (let i = 0; i + 1 < v.length; i += 2) out.push(num(X(v[i])) + ',' + num(Y(v[i + 1])));
      el.setAttribute('points', out.join(' ')); el.removeAttribute('transform'); return;
    }
    if (n === 'path') {
      el.setAttribute('d', pathToString(transformPath(parsePath(el.getAttribute('d')), Lp)));
      el.removeAttribute('transform'); return;
    }
    if (n === 'text' && near(a, 1) && near(d, 1)) {
      moveText(el, e, f);
      el.removeAttribute('transform'); return;
    }
  }
  const s = fmtMatrix(Lp);
  if (s) el.setAttribute('transform', s); else el.removeAttribute('transform');
}

/** Text und seine tspans mit eigener Position verschieben */
function moveText(el, dx, dy) {
  for (const t of [el, ...el.querySelectorAll('tspan,textPath')]) {
    for (const [a, d] of [['x', dx], ['y', dy]]) {
      const v = t.getAttribute(a);
      if (v === null) { if (t === el) t.setAttribute(a, num(d)); continue; }
      t.setAttribute(a, v.trim().split(/[\s,]+/).map(x => num((parseFloat(x) || 0) + d)).join(' '));
    }
  }
}

// ================================================================= Definitionen (Verläufe, Pfeilspitzen, Schatten)

export function defs(root) {
  let d = [...root.children].find(c => c.localName === 'defs');
  if (!d) { d = document.createElementNS(NS, 'defs'); root.insertBefore(d, [...root.children].find(c => !['title', 'desc', 'style'].includes(c.localName)) || null); }
  return d;
}

export function uid(root, prefix) {
  let i = 1;
  while (root.querySelector('#' + CSS.escape(prefix + i))) i++;
  return prefix + i;
}

/**
 * ids eines eingefügten Teils (Kopie, Baustein, Einfügen) eindeutig machen – Verweise im Teil (url(#…), href) folgen.
 * Verweise auf Definitionen außerhalb des Teils bleiben.
 */
export function remapIds(frag, root) {
  const map = {}, used = new Set();
  const clash = (id, n) => { const o = root.querySelector('#' + CSS.escape(id)); return (o && o !== n) || used.has(id); };
  for (const n of [frag, ...frag.querySelectorAll('[id]')]) {
    if (!n.id) continue;
    const base = n.id.replace(/-\d+$/, '');
    let i = 1, id = n.id;
    while (clash(id, n)) id = base + '-' + (++i);
    used.add(id);
    if (id !== n.id) { map[n.id] = id; n.id = id; }
  }
  if (!Object.keys(map).length) return;
  const re = /url\(\s*(['"]?)#([^'")\s]+)\1\s*\)/g;
  for (const n of [frag, ...frag.querySelectorAll('*')]) {
    for (const a of [...n.attributes]) {
      if ((a.localName === 'href') && a.value.startsWith('#') && map[a.value.slice(1)]) n.setAttributeNS(a.namespaceURI, a.name, '#' + map[a.value.slice(1)]);
      else if (a.value.includes('url(')) n.setAttribute(a.name, a.value.replace(re, (m, q, id) => map[id] ? `url(#${map[id]})` : m));
    }
    if (n.localName === 'style') n.textContent = n.textContent.replace(re, (m, q, id) => map[id] ? `url(#${map[id]})` : m);
  }
}

/**
 * Klassen eines eingefügten Stücks mit eigenem <style> eindeutig machen: Illustrator & Co. nennen sie immer .cls-1, .st0 …
 * – ohne Umbenennen würde die Regel der neuen Grafik die vorhandenen Formen umfärben (ein <style> wirkt im ganzen Dokument).
 */
export function remapClasses(frag, root) {
  const styles = frag.querySelectorAll('style');
  if (!styles.length) return;
  const names = new Set();
  for (const n of [frag, ...frag.querySelectorAll('[class]')]) for (const c of (n.getAttribute('class') || '').split(/\s+/)) if (c) names.add(c);
  const map = {}, css0 = [...root.querySelectorAll('style')].map(s => s.textContent).join('\n');
  for (const c of names) {
    if (!root.querySelector('.' + CSS.escape(c)) && !css0.includes('.' + c)) continue;
    let i = 2, nc = c + '-' + i;
    while (root.querySelector('.' + CSS.escape(nc)) || names.has(nc)) nc = c + '-' + (++i);
    map[c] = nc;
  }
  if (!Object.keys(map).length) return;
  for (const n of [frag, ...frag.querySelectorAll('[class]')]) {
    const v = n.getAttribute('class');
    if (v) n.setAttribute('class', v.split(/\s+/).map(c => map[c] || c).join(' '));
  }
  const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  for (const st of styles) {
    let css = st.textContent;
    for (const [a, b] of Object.entries(map)) css = css.replace(new RegExp('\\.' + esc(a) + '(?![\\w-])', 'g'), '.' + b);
    st.textContent = css;
  }
}

/** Element hinter url(#id) */
export function refOf(root, v) {
  const m = /url\(\s*['"]?#([^'")\s]+)/.exec(v || '');
  return m ? root.querySelector('#' + CSS.escape(m[1])) : null;
}

/** Wie oft wird #id verwendet (Attribute und Stile)? */
export function useCount(root, id) {
  let n = 0;
  const re = new RegExp('url\\(\\s*[\'"]?#' + id.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '[\'")\\s]');
  for (const el of root.querySelectorAll('*')) for (const a of el.attributes) if (re.test(a.value) || (a.name.endsWith('href') && a.value === '#' + id)) n++;
  return n;
}

/** Stopps eines Verlaufs (auch über href geerbt) */
export function gradientStops(root, g) {
  let cur = g, guard = 0;
  while (cur && !cur.querySelector('stop') && guard++ < 8) {
    const href = cur.getAttribute('href') || cur.getAttributeNS('http://www.w3.org/1999/xlink', 'href');
    cur = href && href.startsWith('#') ? root.querySelector('#' + CSS.escape(href.slice(1))) : null;
  }
  return cur ? [...cur.querySelectorAll('stop')].map(s => {
    const c = parseColor(getProp(s, 'stop-color') || '#000') || { r: 0, g: 0, b: 0, a: 1 };
    const op = parseFloat(getProp(s, 'stop-opacity') || '1');
    let off = s.getAttribute('offset') || '0';
    off = off.endsWith('%') ? parseFloat(off) / 100 : parseFloat(off);
    return { offset: Math.max(0, Math.min(1, off || 0)), color: toHex(c), opacity: (Number.isFinite(op) ? op : 1) * c.a };
  }) : [];
}

export function writeStops(g, stops) {
  for (const s of [...g.querySelectorAll('stop')]) s.remove();
  for (const s of [...stops].sort((a, b) => a.offset - b.offset)) {
    const st = document.createElementNS(NS, 'stop');
    st.setAttribute('offset', num(s.offset, 4));
    st.setAttribute('stop-color', s.color);
    if (s.opacity < 1) st.setAttribute('stop-opacity', num(s.opacity, 3));
    g.append(st);
  }
}

/** Winkel eines linearen Verlaufs (objectBoundingBox) → x1 y1 x2 y2 */
export function setAngle(g, deg) {
  const r = deg * Math.PI / 180, dx = Math.cos(r) / 2, dy = Math.sin(r) / 2;
  g.setAttribute('x1', num(0.5 - dx, 4)); g.setAttribute('y1', num(0.5 - dy, 4));
  g.setAttribute('x2', num(0.5 + dx, 4)); g.setAttribute('y2', num(0.5 + dy, 4));
}
export function getAngle(g) {
  const f = (a, d) => { const v = g.getAttribute(a); return v === null ? d : (v.endsWith('%') ? parseFloat(v) / 100 : parseFloat(v)); };
  return Math.round(Math.atan2(f('y2', 0) - f('y1', 0), f('x2', 1) - f('x1', 0)) * 180 / Math.PI);
}

/** Text setzen: eine Zeile als Text, mehrere als tspans (x wie der Text, Abstand 1,2 em) */
export function setText(el, value) {
  const lines = String(value).split('\n');
  while (el.firstChild) el.firstChild.remove();
  if (lines.length === 1) { el.textContent = lines[0]; return; }
  const x = el.getAttribute('x') || '0';
  lines.forEach((ln, i) => {
    const ts = document.createElementNS(NS, 'tspan');
    ts.setAttribute('x', x.split(/[\s,]+/)[0]);
    if (i) ts.setAttribute('dy', '1.2em');
    ts.textContent = ln || ' ';
    el.append(ts);
  });
}
export function getText(el) {
  const ts = [...el.children].filter(c => c.localName === 'tspan');
  if (ts.length > 1 && ts.slice(1).every(s => s.hasAttribute('dy') || s.hasAttribute('y'))) return ts.map(s => s.textContent).join('\n');
  return el.textContent;
}

/** Element, dessen Füllung/Kontur für eine Auswahl steht (bei Gruppen die erste Form darin) */
export function paintEl(el) {
  if (el && ['g', 'switch', 'svg'].includes(el.localName)) return el.querySelector(SHAPES.join(',')) || el;
  return el;
}
