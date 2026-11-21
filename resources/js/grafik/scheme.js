/*
 * Grafik-Editor – Hell und Dunkel in einer Datei (Ausbaustufe 3b). Kein eigener Modus des Dokuments: das SVG bleibt die helle
 * Fassung, Dunkel-Angaben stehen als Editor-Daten am Element (nur Arbeitsfassung):
 *   data-ge-ftoken / -stoken / -ctoken = Design-Farbe (Token) für Füllung / Kontur / color (bei <stop>: stop-color)
 *   data-ge-fdark  / -sdark  / -cdark  = eigene Farbe im Dunkeln: "#RRGGBB", "t:token" (Design-Farbe nur dunkel) oder "="
 *                                        (gleich wie hell – auch bei Design-Farben)
 * Ohne Dunkel-Angabe gilt: Design-Farbe → ihr Dunkel-Wert, eigene Farbe → gleich in beiden.
 *
 * Ansicht (Umschalter oben: Hell | Dunkel | Nebeneinander) ändert das Dokument nicht: ein Stylesheet im Shadow Root setzt die
 * Dunkel-Farben über Attributselektoren (!important schlägt Attribut und style), die Fläche bekommt den Hintergrund des
 * gewählten Abschnitts. „Nebeneinander“ zeigt rechts eine nicht bearbeitbare Kopie in Dunkel.
 * Web-Fassung: export.js schreibt daraus Klassen (ge-tf-…, ge-df-…), Core\Graphics\Themed erzeugt die Dateien je Website. MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { getProp, setProp, isHidden, VIEW } from './doc.js';
import { parseColor, toHex, rgbToOklch, oklchToRgb, contrast, over } from './color.js';

export const WHICH = ['fill', 'stroke', 'color'];
export const TOKEN_ATTR = { fill: 'data-ge-ftoken', stroke: 'data-ge-stoken', color: 'data-ge-ctoken' };
export const DARK_ATTR = { fill: 'data-ge-fdark', stroke: 'data-ge-sdark', color: 'data-ge-cdark' };
const SHORT = { fill: 'f', stroke: 's', color: 'c' };

/** Eigenschaft eines Elements für „fill“ – bei Verlaufsstopps stop-color */
export const propOf = (el, which) => which === 'fill' && el.localName === 'stop' ? 'stop-color' : which;

/** Farbfelder des Designs als Map token → { light, dark, name } */
export function swatchMap(ed) {
  if (ed._swm && ed._swmSrc === ed.boot.swatches) return ed._swm;
  const m = new Map();
  for (const s of ed.boot.swatches || []) if (s.token) m.set(s.token, { light: s.color, dark: s.dark || s.color, name: s.name });
  ed._swm = m; ed._swmSrc = ed.boot.swatches;
  return m;
}

/** Eigene Angabe für Dunkel lesen → '#RRGGBB' | null (Token aufgelöst; "=" = gleich wie hell) */
function overrideOf(el, which, sw) {
  const o = el.getAttribute(DARK_ATTR[which]);
  if (!o) return undefined;
  if (o === '=') return null;
  if (o.startsWith('t:')) return sw.get(o.slice(2))?.dark ?? null;
  return /^#[0-9a-f]{6}$/i.test(o) ? o.toUpperCase() : null;
}

/**
 * Farbe eines Elements in einem Farbschema (wie sie gezeichnet wird): '#RRGGBB', 'none', 'url(#…)' oder '' (nichts).
 * Geerbtes (Gruppe, currentColor) wird im selben Schema aufgelöst.
 */
export function paintIn(ed, el, which, mode, depth = 0) {
  if (!el || el === ed.root.parentNode || depth > 40) return which === 'color' ? '#1F2430' : which === 'fill' ? '#000000' : 'none';
  const prop = propOf(el, which);
  const own = getProp(el, prop, false);
  if (!own || own === 'inherit') {
    if (el === ed.root) return which === 'color' ? '#1F2430' : which === 'fill' ? '#000000' : 'none';
    if (prop === 'stop-color') return '#000000';
    return paintIn(ed, el.parentNode, which, mode, depth + 1);
  }
  if (/^currentcolor$/i.test(own)) return paintIn(ed, el, 'color', mode, depth + 1) || '#1F2430';
  if (own === 'none' || own === 'transparent' || /^url/.test(own)) return own;
  if (mode === 'dark') {
    const sw = swatchMap(ed);
    const o = overrideOf(el, which, sw);
    if (o) return o;
    if (o === undefined) {
      const tk = el.getAttribute(TOKEN_ATTR[which]);
      if (tk && sw.has(tk)) return sw.get(tk).dark.toUpperCase();
    }
  }
  return toHex(parseColor(own)) || own;
}

/** Zustand im Dunkeln: 'design' (folgt Design), 'own' (eigene Farbe), 'same' (gleich in beiden) */
export function darkState(el, which) {
  const o = el.getAttribute(DARK_ATTR[which]);
  if (o && o !== '=') return 'own';
  if (!o && el.getAttribute(TOKEN_ATTR[which])) return 'design';
  return 'same';
}

/** Stylesheet der Dunkel-Ansicht für die Zeichenfläche (nur die vorkommenden Werte) */
export function darkCss(ed, root = ed.root) {
  const sw = swatchMap(ed);
  const rules = [];
  for (const which of WHICH) {
    const prop = which;
    for (const [tok, s] of sw) {
      const sel = `[${TOKEN_ATTR[which]}="${CSS.escape(tok)}"]:not([${DARK_ATTR[which]}])`;
      rules.push(`${sel}{${prop}:${s.dark}!important}`);
      if (which === 'fill') rules.push(`stop${sel}{stop-color:${s.dark}!important}`);
    }
    const seen = new Set();
    for (const el of root.querySelectorAll(`[${DARK_ATTR[which]}]`)) {
      const v = el.getAttribute(DARK_ATTR[which]);
      if (seen.has(v) || v === '=') continue;
      seen.add(v);
      const c = v.startsWith('t:') ? sw.get(v.slice(2))?.dark : /^#[0-9a-f]{6}$/i.test(v) ? v : null;
      if (!c) continue;
      const sel = `[${DARK_ATTR[which]}="${CSS.escape(v)}"]`;
      rules.push(`${sel}{${prop}:${c}!important}`);
      if (which === 'fill') rules.push(`stop${sel}{stop-color:${c}!important}`);
    }
  }
  return rules.join('\n');
}

/** Dunkel-Angabe setzen: value '#hex' | 't:token' | '=' | null (entfernen) – für eine Form */
export function setDark(el, which, value) {
  if (value === null || value === undefined || value === '') el.removeAttribute(DARK_ATTR[which]);
  else el.setAttribute(DARK_ATTR[which], value);
}

/**
 * Dunkel-Fassung einer eigenen Farbe vorschlagen: Helligkeit in OKLCH umkehren, Farbton und Buntheit bleiben (außerhalb
 * von sRGB wird die Buntheit verringert). Mittlere Töne bleiben fast gleich, Schwarz wird hell, Weiß dunkel.
 */
export function invertLightness(hex) {
  const c = parseColor(hex);
  if (!c) return hex;
  const o = rgbToOklch(c);
  const l = Math.max(0.16, Math.min(0.96, 1 - o.l));
  return toHex(oklchToRgb({ l, c: o.c, h: o.h }));
}

/**
 * „Dunkel-Fassung vorschlagen“: Design-Farben folgen ihrem Dunkel-Wert (eigene Angaben fallen weg), eigene Farben bekommen die
 * umgekehrte Helligkeit, wenn sie sich merklich ändert. Rückgabe: Anzahl geänderter Angaben.
 */
export function suggestDark(ed, els) {
  let n = 0;
  for (const el of els) {
    for (const which of WHICH) {
      const prop = propOf(el, which);
      const own = getProp(el, prop, false);
      if (!own || own === 'none' || /^url|^currentcolor$|^inherit$/i.test(own)) continue;
      const before = el.getAttribute(DARK_ATTR[which]);
      if (el.getAttribute(TOKEN_ATTR[which])) { if (before !== null) { el.removeAttribute(DARK_ATTR[which]); n++; } continue; }
      const light = toHex(parseColor(own));
      if (!light) continue;
      const d = invertLightness(light);
      const a = parseColor(light), b = parseColor(d);
      const moved = Math.abs(a.r - b.r) + Math.abs(a.g - b.g) + Math.abs(a.b - b.b) > 24;
      const want = moved ? d : null;
      if ((want || null) !== (before || null)) { setDark(el, which, want); n++; }
    }
  }
  return n;
}

/** Alle Elemente mit Farbangaben (für Vorschlag, Prüfung) */
export function paintedElements(root) {
  return [...root.querySelectorAll('[fill],[stroke],[color],[style],stop')].filter(el => !el.closest('title,desc,metadata'));
}

/** Design-Farben als var(--token, #hex) schreiben (Baustein speichern – die Ziel-Website setzt ihre Farbe ein) */
export function tokensToVars(frag) {
  for (const el of [frag, ...frag.querySelectorAll('*')]) {
    for (const which of WHICH) {
      const tok = el.getAttribute(TOKEN_ATTR[which]);
      if (!tok) continue;
      const prop = propOf(el, which);
      const v = getProp(el, prop, false);
      const hx = toHex(parseColor(v));
      if (!hx) continue;
      const css = `var(--${tok}, ${hx})`;
      if (el.style?.getPropertyValue(prop)) el.style.removeProperty(prop);
      if (el.getAttribute('style') === '') el.removeAttribute('style');
      el.setAttribute(prop, css);
    }
  }
}

/** Klassen der Web-Fassung (Core\Graphics\Themed liest sie): aus den Editor-Daten eines Elements */
export function themeClasses(el) {
  const out = [];
  for (const which of WHICH) {
    const tok = el.getAttribute(TOKEN_ATTR[which]);
    if (tok && /^[a-z0-9_]{1,40}$/.test(tok)) out.push(`ge-t${SHORT[which]}-${tok}`);
    const d = el.getAttribute(DARK_ATTR[which]);
    if (!d) continue;
    if (d === '=') out.push(`ge-d${SHORT[which]}-same`);
    else if (d.startsWith('t:') && /^[a-z0-9_]{1,40}$/.test(d.slice(2))) out.push(`ge-d${SHORT[which]}-t-${d.slice(2)}`);
    else if (/^#[0-9a-f]{6}$/i.test(d)) out.push(`ge-d${SHORT[which]}-${d.slice(1).toLowerCase()}`);
  }
  return out;
}

/**
 * Farben eines Schemas fest einsetzen (Export der Dunkel-Fassung für Desktop-Programme): Text der SVG mit den Dunkel-Farben in
 * Attribut bzw. style; die Editor-Daten bleiben (desktopSvg/optimizeSvg entfernen sie).
 */
export function bakeScheme(ed, svg, mode = 'dark') {
  if (mode !== 'dark') return svg;
  const doc = new DOMParser().parseFromString(svg, 'image/svg+xml');
  const root = doc.documentElement;
  const sw = swatchMap(ed);
  for (const el of root.querySelectorAll('*')) {
    for (const which of WHICH) {
      const o = overrideOf(el, which, sw);
      const tk = el.getAttribute(TOKEN_ATTR[which]);
      const v = o || (o === undefined && tk && sw.has(tk) ? sw.get(tk).dark : null);
      if (!v) continue;
      const prop = propOf(el, which);
      if (el.style?.getPropertyValue(prop)) el.style.setProperty(prop, v); else el.setAttribute(prop, v);
    }
  }
  return new XMLSerializer().serializeToString(root);
}

/** Hat die Grafik Dunkel-Angaben oder Design-Farben? */
export const hasScheme = root => !!root.querySelector('[data-ge-ftoken],[data-ge-stoken],[data-ge-ctoken],[data-ge-fdark],[data-ge-sdark],[data-ge-cdark]');

/** Hintergrund des gewählten Abschnitts in einem Schema */
export function sectionBg(ed, mode) {
  const list = ed.boot.sections || [];
  const s = list.find(x => x.id === ed.sectionId) || list[0];
  if (!s) return mode === 'dark' ? '#111318' : '#FFFFFF';
  return mode === 'dark' ? s.dark : s.light;
}

/**
 * Ansicht Hell | Dunkel | Nebeneinander. ed.scheme = 'light' | 'dark' | 'side' (bearbeitet wird links: hell, bei „Dunkel“ dunkel).
 */
export class SchemeView {
  constructor(ed) {
    this.ed = ed;
    this.mode = 'light';
    this.style = document.createElement('style');
    this.style.dataset.geDark = '';
    ed.shadow.append(this.style);
    this.twin = null;
    this.twinT = 0;
  }
  /** wird bearbeitet: hell oder dunkel */
  get editing() { return this.mode === 'dark' ? 'dark' : 'light'; }
  set(mode) {
    if (!['light', 'dark', 'side'].includes(mode) || mode === this.mode) return;
    this.mode = mode;
    const ed = this.ed;
    ed.el.dataset.scheme = mode;
    if (mode === 'side') this.mountTwin(); else this.unmountTwin();
    this.refresh();
    ed.renderScheme?.();
    ed.panels?.refresh();
    ed.announce(mode === 'dark' ? t('Ansicht: Dunkel – Farbänderungen fragen, ob sie nur für Dunkel gelten.') : mode === 'side' ? t('Ansicht: Hell und Dunkel nebeneinander.') : t('Ansicht: Hell'));
    requestAnimationFrame(() => { ed.fitted = false; ed.fit(); });
  }
  refresh() {
    const ed = this.ed;
    const dark = this.mode === 'dark';
    this.style.textContent = dark ? darkCss(ed) : '';
    VIEW.sheet = dark ? this.style : (VIEW.sheet === this.style ? null : VIEW.sheet);
    // Symbole behalten hell das Schachbrett (Transparenz), dunkel zeigen sie den dunklen Grund
    ed.paper.style.background = ed.format !== 'icon' || dark ? sectionBg(ed, dark ? 'dark' : 'light') : '';
    this.updateTwin(true);
  }
  /** Nur das Stylesheet der Dunkel-Ansicht (beim Ziehen eines Reglers) */
  live() {
    const css = darkCss(this.ed);
    if (this.style.textContent !== css) this.style.textContent = css;
  }
  mountTwin() {
    if (this.twin) return;
    const ed = this.ed;
    const box = document.createElement('div');
    box.className = 'ge-twin';
    box.setAttribute('role', 'img');
    box.innerHTML = `<span class="ge-twin__lbl" aria-hidden="true">${esc(t('Dunkel'))}</span><div class="ge-twin__paper"></div>`;
    const paper = box.querySelector('.ge-twin__paper');
    const sh = paper.attachShadow({ mode: 'open' });
    sh.innerHTML = '<style>:host{all:initial;display:block;width:100%;height:100%;color:#1F2430}svg{display:block;width:100%;height:100%;overflow:visible}</style><style data-dark></style>';
    ed.stage.after(box);
    this.twin = { box, paper, sh };
    this.updateTwin(true);
  }
  unmountTwin() { this.twin?.box.remove(); this.twin = null; }
  /** Kopie rechts in ihre Fläche einpassen (wie „Einpassen“ links) */
  fitTwin() {
    const tw = this.twin;
    if (!tw) return;
    const r = tw.box.getBoundingClientRect(), vb = this.ed.vb;
    if (!r.width || !r.height) return;
    const z = Math.max(0.02, Math.min((r.width - 80) / vb.w, (r.height - 120) / vb.h, this.ed.format === 'icon' ? 24 : 8));
    tw.paper.style.width = vb.w * z + 'px';
    tw.paper.style.height = vb.h * z + 'px';
  }
  /** Kopie rechts neu zeichnen (gedrosselt) */
  updateTwin(now = false) {
    if (!this.twin) return;
    clearTimeout(this.twinT);
    const run = () => {
      const ed = this.ed, tw = this.twin;
      if (!tw) return;
      const c = ed.root.cloneNode(true);
      c.removeAttribute('style'); c.removeAttribute('width'); c.removeAttribute('height');
      tw.sh.querySelector('svg')?.remove();
      tw.sh.append(c);
      tw.sh.querySelector('[data-dark]').textContent = darkCss(ed, c);
      tw.paper.style.background = ed.format === 'icon' ? '' : sectionBg(ed, 'dark');
      this.fitTwin();
      tw.box.setAttribute('aria-label', t('Vorschau Dunkel'));
    };
    if (now) run(); else this.twinT = setTimeout(run, 120);
  }
}

/** Kontrast zweier Farben (Hex) */
export const ratio = (a, b) => { const x = parseColor(a), y = parseColor(b); return x && y ? contrast(x, y) : 21; };

export { over, isHidden, setProp };
