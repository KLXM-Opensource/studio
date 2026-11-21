/*
 * Grafik-Editor – Text: Modell, Satz und Messen. Ein Text ist eine Folge von Absätzen, jeder Absatz eine Folge von Läufen
 * { t: Zeichen, s: { 'font-weight', 'font-style', fill, 'text-decoration', … } } (Formatierung einzelner Wörter).
 *
 * Ausgabe (nur wenn der Text bearbeitet wird – unveränderte Texte bleiben Zeichen für Zeichen erhalten):
 *   eine Zeile, ein Lauf            <text>Inhalt</text>
 *   mehrere Zeilen                  <tspan x=… dy="1.2em">… je Zeile, Läufe darin als <tspan font-weight=…>
 *   Textrahmen (data-ge-frame=Breite) Zeilen automatisch umbrochen, umbrochene Zeilen tragen data-ge-soft="1"
 *   Text auf Pfad                   <textPath href="#pfad" startOffset=…> mit den Läufen
 * Kein foreignObject (SVG als Bild kennt kein HTML): Umbrüche sind echte Zeilen, Großbuchstaben echte Zeichen.
 *
 * Abbildung für Schreibmarke/Auswahl: Index im Klartext (Absätze mit \n) ↔ Zeichennummer des SVG (getStartPositionOfChar). MIT
 */
import { NS, num } from './geom.js';
import { paraOf, hyphenator, hyphenPoints } from './typo.js';

/** Stil-Eigenschaften eines Laufs (Formatierung einzelner Wörter) */
export const RUN_PROPS = ['font-weight', 'font-style', 'fill', 'text-decoration', 'font-family', 'font-size', 'letter-spacing', 'font-variation-settings',
  'font-feature-settings', 'baseline-shift', 'data-ge-cs'];
/** nur als CSS (style) gültig – siehe doc.js CSS_ONLY */
const CSS_RUN = ['font-variation-settings', 'font-feature-settings', 'font-variant-ligatures', 'font-variant-numeric', 'font-variant-caps', 'font-variant-position', 'font-kerning'];
const sameStyle = (a, b) => RUN_PROPS.every(k => (a[k] || '') === (b[k] || ''));
const styleOf = el => { const s = {}; for (const k of RUN_PROPS) { const v = el.getAttribute(k) || el.style?.getPropertyValue(k); if (v) s[k] = v.trim(); } return s; };

/** Zeilen-tspan (eigene Position) oder Lauf? */
const isLine = n => n.localName === 'tspan' && (n.hasAttribute('x') || n.hasAttribute('y') || n.hasAttribute('dy'));

/** Läufe eines Containers (Textknoten = Grundstil, tspan ohne Position = Lauf mit eigenem Stil) */
function runsOf(node, base = {}) {
  const out = [];
  for (const c of node.childNodes) {
    if (c.nodeType === 3) out.push({ t: c.data.replace(/[\r\n\t]+/g, ' '), s: { ...base } });
    else if (c.nodeType === 1 && (c.localName === 'tspan' || c.localName === 'a')) out.push(...runsOf(c, { ...base, ...styleOf(c) }));
  }
  return merge(out);
}

/** Benachbarte Läufe mit gleichem Stil zusammenfassen, leere entfernen */
export function merge(runs) {
  const out = [];
  for (const r of runs) {
    if (!r.t) continue;
    const l = out[out.length - 1];
    if (l && sameStyle(l.s, r.s)) l.t += r.t; else out.push({ t: r.t, s: { ...r.s } });
  }
  return out;
}

/** Text-Element → Modell { paras: [[lauf]], path: textPath|null } */
export function readText(el) {
  const tp = [...el.children].find(c => c.localName === 'textPath');
  if (tp) return { paras: [runsOf(tp)], path: tp };
  const kids = [...el.childNodes].filter(c => c.nodeType === 1 || c.data.trim());
  if (kids.length && kids.every(c => c.nodeType === 1 && isLine(c))) {
    const paras = [];
    kids.forEach((ln, i) => {
      let runs = runsOf(ln);
      if (runs.length === 1 && runs[0].t === ' ' && !ln.getAttribute('data-ge-soft')) runs = [];   // Leerzeile
      const soft = i && paras.length ? ln.getAttribute('data-ge-soft') : null;
      if (soft === '1') { const p = paras[paras.length - 1]; paras[paras.length - 1] = merge([...p, { t: ' ', s: { ...(p[p.length - 1]?.s || {}) } }, ...runs]); }
      else if (soft === 'h') {
        // Silbentrennung: der eingefügte Trennstrich am Ende der Zeile davor gehört nicht zum Text
        const p = paras[paras.length - 1].map(r => ({ ...r }));
        const last = p[p.length - 1];
        if (last) last.t = last.t.replace(/-$/, '');
        paras[paras.length - 1] = merge([...p, ...runs]);
      } else paras.push(runs);
    });
    return { paras, path: null };
  }
  return { paras: [runsOf(el)], path: null };
}

export const plainOf = m => m.paras.map(p => p.map(r => r.t).join('')).join('\n');

/** Modell → Liste von Zeichen [{c, s}] (Absätze getrennt durch {c:'\n'}) und zurück – für Bearbeiten und Formatieren */
export function toChars(m) {
  const out = [];
  m.paras.forEach((p, i) => { if (i) out.push({ c: '\n', s: {} }); for (const r of p) for (const c of r.t) out.push({ c, s: r.s }); });
  return out;
}
export function fromChars(chars, path = null) {
  const paras = [[]];
  for (const ch of chars) {
    if (ch.c === '\n' && !path) { paras.push([]); continue; }
    paras[paras.length - 1].push({ t: ch.c === '\n' ? ' ' : ch.c, s: ch.s });
  }
  return { paras: paras.map(merge), path };
}

/**
 * Neuer Klartext nach einer Eingabe: gemeinsamer Anfang/Ende bleibt mit Stil, Eingefügtes bekommt den Stil davor
 * (bzw. pending, z. B. nach ⌘B ohne Auswahl).
 */
export function applyInput(m, text, pending = null) {
  const chars = toChars(m), old = chars.map(c => c.c).join('');
  let a = 0;
  while (a < old.length && a < text.length && old[a] === text[a]) a++;
  let b = 0;
  while (b < old.length - a && b < text.length - a && old[old.length - 1 - b] === text[text.length - 1 - b]) b++;
  const ins = text.slice(a, text.length - b);
  const s = pending || (a > 0 ? chars[a - 1].s : chars[a]?.s) || {};
  const mid = [...ins].map(c => ({ c, s: { ...s } }));
  return fromChars([...chars.slice(0, a), ...mid, ...chars.slice(old.length - b)], m.path);
}

/** Stil auf einen Bereich [a, b) anwenden (patch: Eigenschaft → Wert, null = entfernen) */
export function styleRange(m, a, b, patch) {
  const chars = toChars(m);
  for (let i = a; i < b && i < chars.length; i++) {
    if (chars[i].c === '\n') continue;
    const s = { ...chars[i].s };
    for (const [k, v] of Object.entries(patch)) { if (v === null || v === '') delete s[k]; else s[k] = v; }
    chars[i] = { c: chars[i].c, s };
  }
  return fromChars(chars, m.path);
}

/** Stil je Zeichen im Bereich [a, b) umrechnen: fn(stil) → neuer Stil (z. B. OpenType-Merkmale zusammenführen) */
export function mapRange(m, a, b, fn) {
  const chars = toChars(m);
  for (let i = a; i < b && i < chars.length; i++) {
    if (chars[i].c === '\n') continue;
    chars[i] = { c: chars[i].c, s: fn({ ...chars[i].s }) };
  }
  return fromChars(chars, m.path);
}

/** Hat der ganze Bereich die Eigenschaft k = v? (für ⌘B als Umschalter) */
export function rangeHas(m, a, b, k, test) {
  const chars = toChars(m).slice(a, b).filter(c => c.c !== '\n');
  return chars.length > 0 && chars.every(c => test(c.s[k]));
}

/** Großbuchstaben, Kleinbuchstaben, Wortanfänge groß – als echte Zeichen (SVG als Bild kennt kein text-transform) */
export function transformCase(m, mode, a = 0, b = Infinity) {
  const chars = toChars(m);
  const end = Math.min(b, chars.length);
  for (let i = a; i < end; i++) {
    const c = chars[i].c;
    if (mode === 'upper') chars[i] = { ...chars[i], c: c.toLocaleUpperCase() };
    else if (mode === 'lower') chars[i] = { ...chars[i], c: c.toLocaleLowerCase() };
    else if (mode === 'title') { const prev = i ? chars[i - 1].c : ' '; chars[i] = { ...chars[i], c: /[\s\-–(„"]/.test(prev) ? c.toLocaleUpperCase() : c.toLocaleLowerCase() }; }
  }
  // ß → SS u. ä. werden länger: je Ergebniszeichen ein Eintrag (die Eingabe wird danach neu abgeglichen)
  return fromChars(chars.flatMap(ch => [...ch.c].map(c => ({ c, s: ch.s }))), m.path);
}

// ================================================================= Satz

/** Zeilenabstand (Vielfaches der Schriftgröße) aus vorhandenen Zeilen, sonst 1,2 */
export function lineHeightOf(el) {
  const v = el.getAttribute('data-ge-lh');
  if (v) return parseFloat(v) || 1.2;
  const ln = [...el.children].filter(isLine)[1];
  const dy = ln?.getAttribute('dy') || '';
  if (/em$/.test(dy)) return parseFloat(dy) || 1.2;
  return 1.2;
}

/** Breite einer Folge von Läufen im Stil von el (gemessen vom Browser – gleiche Schrift, Laufweite, Unterschneidung) */
function measurer(el) {
  const probe = el.cloneNode(false);
  probe.removeAttribute('id'); probe.removeAttribute('transform');
  probe.setAttribute('x', '0'); probe.setAttribute('y', '0');
  probe.setAttribute('visibility', 'hidden');
  probe.removeAttribute('text-anchor');
  el.parentNode.append(probe);
  return {
    width(runs) {
      while (probe.firstChild) probe.firstChild.remove();
      for (const r of runs) probe.append(runNode(r));
      try { return probe.getComputedTextLength(); } catch { return 0; }
    },
    done() { probe.remove(); },
  };
}

function runNode(r) {
  if (!Object.keys(r.s).length) return document.createTextNode(r.t);
  const ts = document.createElementNS(NS, 'tspan');
  for (const [k, v] of Object.entries(r.s)) { if (CSS_RUN.includes(k)) ts.style.setProperty(k, v); else ts.setAttribute(k, v); }
  ts.textContent = r.t;
  return ts;
}

/** Läufe eines Absatzes in Wörter (Trennstellen nach Leerzeichen) */
function words(runs) {
  const out = [];
  let cur = [];
  for (const r of runs) {
    const parts = r.t.split(/( +)/);
    for (const p of parts) {
      if (!p) continue;
      if (/^ +$/.test(p)) { cur.push({ t: p, s: r.s }); out.push(cur); cur = []; } else cur.push({ t: p, s: r.s });
    }
  }
  if (cur.length) out.push(cur);
  return out;
}

/** Läufe von Zeichen a bis b (Klartext-Indizes innerhalb der Läufe) */
function sliceRuns(runs, a, b = Infinity) {
  const out = [];
  let i = 0;
  for (const r of runs) {
    const s0 = Math.max(a, i), s1 = Math.min(b, i + r.t.length);
    if (s1 > s0) out.push({ t: r.t.slice(s0 - i, s1 - i), s: r.s });
    i += r.t.length;
  }
  return out;
}
const textOf = runs => runs.map(r => r.t).join('');
const trimRuns = rs => { const c = rs.map(r => ({ ...r })); while (c.length && /^ *$/.test(c[c.length - 1].t)) c.pop(); if (c.length) c[c.length - 1].t = c[c.length - 1].t.replace(/ +$/, ''); return merge(c); };

/**
 * Absatz umbrechen → Zeilen [{runs, soft, hy}]. width(k) = verfügbare Breite der k-ten Zeile des Absatzes (Einzüge),
 * hy = Trennfunktion (Silbentrennung) oder null. Passt ein Wort nicht mehr, wird es – wenn möglich – an der letzten
 * passenden Trennstelle geteilt; die Zeile endet dann mit einem echten Trennstrich (hy: true), der nicht zum Text gehört.
 */
function wrap(runs, width, M, hy) {
  if (!width || !runs.length) return [{ runs, soft: false, hy: false }];
  const lines = [];
  let line = [];
  const fits = rs => M.width(trimRuns(rs)) <= width(lines.length) + 0.01;
  /** Wort an einer Trennstelle teilen, sodass der Anfang (mit Trennstrich) noch in die Zeile passt */
  const split = wd => {
    if (!hy) return null;
    const word = textOf(wd).replace(/ +$/, '');
    // Trennstellen nur innerhalb von Buchstabenfolgen (Satzzeichen davor/danach bleiben am Wort)
    const m = word.match(/^([^\p{L}]*)([\p{L}\u00AD-]+)/u);
    if (!m) return null;
    const pts = hyphenPoints(hy, m[2]).map(x => x + m[1].length).filter(x => !/-/.test(word[x - 1]));
    for (let k = pts.length - 1; k >= 0; k--) {
      const head = sliceRuns(wd, 0, pts[k]);
      const lastS = head[head.length - 1]?.s || {};
      const withHy = merge([...head, { t: '-', s: lastS }]);
      if (fits([...line, ...withHy])) return { head: withHy, tail: sliceRuns(wd, pts[k]) };
    }
    return null;
  };
  for (const wd of words(runs)) {
    let pending = wd;
    for (let guard = 0; pending && guard < 200; guard++) {
      if (fits([...line, ...pending])) { line = [...line, ...pending]; pending = null; break; }
      const sp = split(pending);
      if (sp) { lines.push({ runs: trimRuns([...line, ...sp.head]), hy: true }); line = []; pending = sp.tail; continue; }
      if (line.length) { lines.push({ runs: trimRuns(line), hy: false }); line = []; continue; }
      line = pending; pending = null;   // ein Wort länger als die Zeile, ohne Trennstelle: ragt hinaus
    }
  }
  lines.push({ runs: trimRuns(line), hy: false });
  // soft = Fortsetzung der vorigen Zeile ('1' nach Leerzeichen, 'h' nach Trennstrich)
  return lines.map((l, i) => ({ ...l, soft: i > 0 ? (lines[i - 1].hy ? 'h' : '1') : false }));
}

/**
 * Modell in das Text-Element schreiben. Rückgabe: Abbildung { lines: [{p0, p1, s0, n, empty}] } – p = Klartext-Index,
 * s = SVG-Zeichennummer, n = Zeichen der Zeile im SVG (mit eingefügtem Trennstrich), für Schreibmarke und Auswahl.
 * Absatz-Einstellungen (typo.js paraOf): Einzüge (li, ri, fi), Abstand vor/nach Absätzen (pb, pa), Blocksatz (al),
 * Silbentrennung (hy) – Blocksatz und Trennung nur im Textrahmen.
 */
export function layoutText(el, m, opts = {}) {
  const map = { lines: [] };
  const plainLen = p => p.reduce((n, r) => n + r.t.length, 0);
  const keep = [...el.childNodes].filter(c => c.nodeType === 1 && ['title', 'desc'].includes(c.localName));
  const needPre = m.paras.some(p => /  |^ | $/.test(p.map(r => r.t).join('')));
  if (needPre) el.setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
  else el.removeAttributeNS('http://www.w3.org/XML/1998/namespace', 'space');
  if (m.path) {
    const tp = m.path;
    while (el.firstChild) el.firstChild.remove();
    el.append(...keep);
    while (tp.firstChild) tp.firstChild.remove();
    const runs = m.paras[0] || [];
    for (const r of runs) tp.append(runNode(r));
    el.append(tp);
    map.lines.push({ p0: 0, p1: plainLen(runs), s0: 0, n: plainLen(runs), empty: !runs.length });
    return map;
  }
  const frame = parseFloat(el.getAttribute('data-ge-frame')) || 0;
  const lh = lineHeightOf(el);
  const pa = paraOf(el);
  const x = parseFloat((el.getAttribute('x') || '0').trim().split(/[\s,]+/)[0]) || 0;
  const anchor = (el.getAttribute('text-anchor') || el.style?.getPropertyValue('text-anchor') || 'start').trim();
  const justify = frame && pa.al === 'justify';
  const hy = frame && pa.hy ? hyphenator(pa.hy, opts.onHyphens) : null;
  const M = frame ? measurer(el) : null;
  // Einzüge: Breite je Zeile (erste Zeile mit Erstzeileneinzug)
  const width = frame ? k => Math.max(1, frame - pa.li - pa.ri - (k === 0 ? pa.fi : 0)) : null;
  const lines = [];
  let p = 0;
  m.paras.forEach((runs, pi) => {
    const wrapped = wrap(runs, width, M, hy);
    let q = p;
    wrapped.forEach((ln, k) => {
      const svgLen = plainLen(ln.runs);
      const len = svgLen - (ln.hy ? 1 : 0);
      // umbrochene Zeile: das Leerzeichen davor gehört zum Klartext, nicht zum SVG (nach Trennstrich: kein Zeichen)
      if (ln.soft === '1') q += 1;
      lines.push({ ...ln, p0: q, p1: q + len, n: svgLen, first: k === 0, last: k === wrapped.length - 1, para: pi });
      q += len;
    });
    p += plainLen(runs) + 1;
  });
  // Blocksatz: Wortabstand je Zeile (nicht die letzte Zeile eines Absatzes)
  if (justify) {
    for (const ln of lines) {
      if (ln.last || !ln.runs.length) continue;
      const spaces = (textOf(ln.runs).match(/ /g) || []).length;
      if (!spaces) continue;
      const extra = (width(ln.first ? 0 : 1) - M.width(ln.runs)) / spaces;
      if (extra > 0.001) ln.ws = extra;
    }
  }
  M?.done();
  while (el.firstChild) el.firstChild.remove();
  el.append(...keep);
  let s = 0;
  const indents = pa.li || pa.ri || pa.fi;
  if (lines.length === 1 && !frame && !indents) {
    const ln = lines[0];
    for (const r of ln.runs) el.append(runNode(r));
    map.lines.push({ p0: ln.p0, p1: ln.p1, s0: 0, n: ln.n, empty: !ln.runs.length });
    if (!ln.runs.length) el.textContent = '';
    return map;
  }
  // Abstand vor/nach Absätzen: dy in Einheiten der Zeichenfläche (sonst em)
  const fs = parseFloat(el.getAttribute('font-size') || el.style?.getPropertyValue('font-size') || (el.isConnected ? getComputedStyle(el).fontSize : '') || '16') || 16;
  const spacing = pa.pb || pa.pa;
  if (spacing) el.setAttribute('data-ge-lh', num(lh, 3));
  const baseWs = parseFloat(el.getAttribute('word-spacing') || el.style?.getPropertyValue('word-spacing') || '0') || 0;
  const lineX = ln => {
    const ind = pa.li + (ln.first ? pa.fi : 0);
    if (anchor === 'middle') return x + (ind - pa.ri) / 2;
    if (anchor === 'end') return x - pa.ri;
    return x + ind;
  };
  lines.forEach((ln, i) => {
    const ts = document.createElementNS(NS, 'tspan');
    ts.setAttribute('x', num(lineX(ln)));
    if (i) ts.setAttribute('dy', spacing && ln.first ? num(fs * lh + pa.pa + pa.pb, 3) : num(lh, 3) + 'em');
    if (ln.soft) ts.setAttribute('data-ge-soft', ln.soft);
    if (ln.ws) ts.setAttribute('word-spacing', num(baseWs + ln.ws, 3));
    if (ln.runs.length) for (const r of ln.runs) ts.append(runNode(r)); else ts.textContent = ' ';
    el.append(ts);
    map.lines.push({ p0: ln.p0, p1: ln.p1, s0: s, n: ln.runs.length ? ln.n : 1, empty: !ln.runs.length });
    s += ln.runs.length ? ln.n : 1;
  });
  return map;
}

/** Zeile zur Klartext-Position i */
export function lineAt(map, i) {
  let best = map.lines[0];
  for (const l of map.lines) if (i >= l.p0 && i <= l.p1) { best = l; if (i < l.p1) break; }
  return best;
}

/** SVG-Zeichennummer → Klartext-Index (nach Treffer mit getCharNumAtPosition) */
export function plainIndex(map, j) {
  for (const l of map.lines) {
    const n = l.empty ? 1 : l.n ?? l.p1 - l.p0;
    if (j >= l.s0 && j < l.s0 + n) return l.empty ? l.p0 : Math.min(l.p1, l.p0 + j - l.s0);
  }
  const last = map.lines[map.lines.length - 1];
  return last ? last.p1 : 0;
}
