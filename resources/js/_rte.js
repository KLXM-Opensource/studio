/*
 * Rich-Text-Formatierung (Core, Teil von admin.js) – eine Logik für alle Formatierungsleisten:
 * Formularfelder (.rte in Verwaltung, Block-Seitenleiste, Eintrags-Seitenleiste), schwebende Leiste beim direkten Bearbeiten
 * auf der Website (editor.js, _entry_edit.js – Leiste in der Shadow-DOM-Ebene, Text im Theme-Dokument).
 *
 * Leiste: „Stil ▾“ (Normal, Hervorgehoben, Klein, Hinweis-Box, H2–H4, Zitat) · Fett · Kursiv · Marker · „Farbe ▾“ · Link
 *         · Listen · „⋯“ (Hochgestellt, Tiefgestellt, Einrücken, Link entfernen, Formatierung entfernen, Markdown einfügen …) · KI.
 *         Schmale Bildschirme (≤ 560 px): Listen wandern ins Menü „⋯“ (Überlauf).
 * Tastatur: ⌘/Strg+B, +I, +K (Link), +⇧+H (Marker), Alt+F10 (zur Leiste), in Menüs ↑/↓/Pos1/Ende, Esc zurück in den Text.
 * Einfügen: immer als reiner Text – außer der Text sieht eindeutig nach Markdown aus (und die Zwischenablage hat keine echte
 * Formatierung): dann umgewandelt (_markdown.js), mit Hinweis „Als Text einfügen“; ⌘/Strg+Z macht es ebenfalls rückgängig.
 * Ergebnis (Whitelist Core\Sanitizer, keine style-Attribute): <p class="t-lead|t-small|t-note">, <span class="c-…">, <mark>, <sup>, <sub>.
 * Farben: auf der Website aus den Theme-Variablen --rt-accent … (Kontrast gegen den echten Hintergrund), in Formularen aus der
 * Palette <script id="cms-rich"> (Core\RichText). Zu geringer Kontrast wird im Farbmenü angezeigt.
 */
import { t } from './_i18n.js';
import { aiBarHtml, aiExec } from './_ai.js';
import { openLinkPicker, normalizeTel } from './_links.js';
import { layerBox } from './_shadow.js';
import { looksLike, htmlIsPlain, toHtml, openImport, pasteTip, hideTip } from './_markdown.js';

const d = document;
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const STYLES = [
  // [Wert, Name im Menü, Kurzname in der Leiste, Erläuterung]
  ['p', 'Normaler Text', 'Normal', ''],
  ['t-lead', 'Hervorgehoben', 'Hervorgeh.', 'Größerer Text, keine Überschrift'],
  ['t-small', 'Klein', 'Klein', 'Kleingedrucktes, Anmerkungen'],
  ['t-note', 'Hinweis-Box', 'Hinweis', 'Abgesetzter Kasten'],
  ['h2', 'Überschrift H2', 'H2', ''],
  ['h3', 'Zwischenüberschrift H3', 'H3', ''],
  ['h4', 'Unterüberschrift H4', 'H4', ''],
  ['blockquote', 'Zitat', 'Zitat', ''],
];
const P_STYLES = ['t-lead', 't-small', 't-note'];
const COLORS = ['accent', 'muted', 'success', 'warning', 'danger'];
const HEADINGS = ['h2', 'h3', 'h4'];
const MARK = '#010203';   // Hilfsfarbe: execCommand('foreColor') markiert die Auswahl, danach Klasse statt <font>
// E-Mail-, Web-Adressen und Telefonnummern erkennen: markiert + „Link“ → sofort Link; beim Tippen nach Leerzeichen/Satzzeichen/Enter automatisch
const EMAIL = /^[\w.+-]+@[\w-]+(?:\.[\w-]+)*\.[a-z]{2,}$/i;
const URL_RE = /^(?:https?:\/\/[^\s<>"]+|www\.[\w-]+(?:\.[\w-]+)+(?:[/?#][^\s<>"]*)?)$/i;
const DOMAIN = /^[\w-]+(?:\.[\w-]+)*\.[a-z]{2,}(?:[/?#][^\s<>"]*)?$/i;   // nur bei Markierung: klxm.de/kit
// Telefon: beginnt mit + oder 0 (auch „(0…)“), Ziffern mit Leerzeichen, / - . ( ) – 6 bis 15 Ziffern (keine Jahreszahlen, Preise o. Ä.)
const TEL = /^(?:\+|00|\(?0)[\d\s/().-]*\d$/;
const TEL_END = /(?:\+|\(?0)[\d\s/().-]*\d$/;
const telOk = v => { const n = v.replace(/\D/g, '').length; return n >= 6 && n <= 15 && !/\d{1,2}\.\d{1,2}\.\d{2,4}$/.test(v) && !/\s{2,}/.test(v); };
const END = /(?:[\w.+-]+@[\w-]+(?:\.[\w-]+)*\.[a-z]{2,}|https?:\/\/[^\s<>"]+|www\.[\w-]+(?:\.[\w-]+)+(?:[/?#][^\s<>"]*)?)$/i;
/** Link-Ziel zu einer Adresse im Text (E-Mail → mailto:, www./Domain → https://) – sonst null */
function hrefFor(text, domains = false) {
  const v = String(text || '').trim();
  if (EMAIL.test(v)) return 'mailto:' + v;
  if (URL_RE.test(v)) return /^https?:/i.test(v) ? v : 'https://' + v;
  if (TEL.test(v) && telOk(v)) { const r = normalizeTel(v); if (r.href) return r.href; }
  if (domains && DOMAIN.test(v) && !/^\d+(\.\d+)+$/.test(v)) return 'https://' + v;
  return null;
}
/** Text vor offset im Textknoten auf eine Adresse am Ende prüfen und als Link einpacken (nicht in vorhandenen Links) */
function linkifyBefore(area, node, offset, tel = false, punct = false) {
  if (!node || node.nodeType !== 3 || !area.contains(node)) return false;
  for (let n = node.parentNode; n && n !== area; n = n.parentNode) if (n.tagName === 'A') return false;
  const before = node.data.slice(0, offset).replace(/[\s.,;:!?)\]'"»“]+$/, '');
  let m = before.match(END);
  if (!m && tel) {
    // Längste passende Nummer am Ende (z. B. „Tel. 02841 35656“ → „02841 35656“)
    const t2 = before.match(TEL_END);
    if (t2) { let v = t2[0].replace(/^[\s/.-]+/, ''); while (v && !(TEL.test(v) && telOk(v))) v = v.replace(/^\S+\s*/, ''); if (v) m = [v]; }
  }
  if (!m || /[\w.+@/-]/.test(before.charAt(before.length - m[0].length - 1) || ' ')) return false;
  const href = hrefFor(m[0]); if (!href) return false;
  if (punct && /^https?:/i.test(href)) return false;   // ? : ; ! ) können in Web-Adressen stehen – dort erst bei Leerzeichen/Enter
  const r = d.createRange();
  r.setStart(node, before.length - m[0].length); r.setEnd(node, before.length);
  const a = d.createElement('a'); a.setAttribute('href', href);
  r.surroundContents(a);
  return true;
}
const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
const MOD = isMac ? '⌘' : 'Strg+';
const kbd = (k, shift) => `${MOD}${shift ? (isMac ? '⇧' : 'Umschalt+') : ''}${k}`;
const ariaKeys = (k, shift) => `Meta+${shift ? 'Shift+' : ''}${k} Control+${shift ? 'Shift+' : ''}${k}`;

// Auswahl im Dokument – oder im Shadow DOM des Feldes (Seitenleiste „Eintrag bearbeiten“ auf der Website)
const selOf = n => { const r = n?.getRootNode?.(); return r && r !== d && typeof r.getSelection === 'function' ? r.getSelection() : getSelection(); };
const rangeIn = area => { const s = selOf(area); if (!s.rangeCount) return null; const r = s.getRangeAt(0); return area.contains(r.commonAncestorContainer) ? r : null; };
const up = (area, test) => { let n = selOf(area).anchorNode; while (n && n !== area) { if (n.nodeType === 1 && test(n)) return n; n = n.parentNode; } return null; };
const unwrap = el => { const p = el.parentNode; if (!p) return; while (el.firstChild) p.insertBefore(el.firstChild, el); el.remove(); };
const colorOf = el => [...el.classList].find(c => /^c-/.test(c))?.slice(2) || '';
const isColor = el => el.tagName === 'SPAN' && !!colorOf(el);

/** node aus einem Vorfahren heben: Vorfahr wird davor/danach geteilt (Farbe/Marker nur für einen Teil entfernen) */
function lift(node, anc) {
  const r = d.createRange();
  r.setStart(anc, 0); r.setEndBefore(node);
  const pre = r.extractContents();
  if (pre.textContent) { const a = anc.cloneNode(false); a.append(pre); anc.before(a); }
  r.setStartAfter(node); r.setEnd(anc, anc.childNodes.length);
  const post = r.extractContents();
  if (post.textContent) { const b = anc.cloneNode(false); b.append(post); anc.after(b); }
  unwrap(anc);
}
/** Liegt jeder sichtbare Text der Auswahl in einem passenden Element? */
function allIn(area, r, test) {
  const w = d.createTreeWalker(r.commonAncestorContainer.nodeType === 1 ? r.commonAncestorContainer : r.commonAncestorContainer.parentNode, NodeFilter.SHOW_TEXT);
  let any = false;
  for (let n = w.nextNode(); n; n = w.nextNode()) {
    if (!r.intersectsNode(n) || !n.textContent.trim()) continue;
    any = true;
    let p = n.parentNode, ok = false;
    while (p && p !== area) { if (p.nodeType === 1 && test(p)) { ok = true; break; } p = p.parentNode; }
    if (!ok) return false;
  }
  return any;
}
/** Auswahl mit Hilfsfarbe markieren → Liste der <font>-Elemente (von execCommand exakt um die Auswahl gelegt) */
function markSelection(area) {
  $$('font', area).forEach(unwrap);
  d.execCommand('styleWithCSS', false, false);
  d.execCommand('foreColor', false, MARK);
  return $$('font', area);
}
function selectNodes(area, nodes) {
  if (!nodes.length) return;
  const r = d.createRange(); r.setStartBefore(nodes[0]); r.setEndAfter(nodes[nodes.length - 1]);
  const s = selOf(area); s.removeAllRanges(); s.addRange(r);
}

// ------------------------------------------------------------------ Palette und Kontrast
let PAL;
function palette() {
  if (PAL === undefined) { try { PAL = JSON.parse(d.getElementById('cms-rich')?.textContent || 'null'); } catch { PAL = null; } }
  return PAL || { colors: COLORS.map(n => ({ name: n, label: n, light: '#555555', dark: '#BBBBBB' })), bg: ['#FFFFFF', '#121212'] };
}
const colorLabel = n => palette().colors.find(c => c.name === n)?.label || n;
function parseColor(s) {
  s = String(s || '').trim();
  let m = s.match(/^#([0-9a-f]{6})$/i);
  if (m) return [0, 2, 4].map(i => parseInt(m[1].slice(i, i + 2), 16)).concat(1);
  m = s.match(/^rgba?\(([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+%?))?\)$/);
  if (m) return [+m[1], +m[2], +m[3], m[4] === undefined ? 1 : parseFloat(m[4]) / (m[4].endsWith('%') ? 100 : 1)];
  m = s.match(/^color\(srgb\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)(?:\s*\/\s*([\d.]+%?))?\)$/);
  if (m) return [m[1] * 255, m[2] * 255, m[3] * 255, m[4] === undefined ? 1 : parseFloat(m[4]) / (m[4].endsWith('%') ? 100 : 1)];
  return null;
}
const lum = c => { const f = v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]); };
const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
let probe = null;
/** Beliebigen CSS-Farbwert (auch var()/color-mix()) in RGB auflösen – Hilfselement in der CMS-Ebene, nicht im Text */
function resolve(css) {
  if (!css) return null;
  const direct = parseColor(css);
  if (direct) return direct;
  if (!probe) { probe = d.createElement('span'); probe.hidden = true; layerBox().append(probe); }
  probe.style.color = ''; probe.style.color = css;
  return parseColor(getComputedStyle(probe).color);
}
/** Hintergrund hinter dem Text (null: Bild dahinter – keine verlässliche Prüfung) */
function bgOf(el) {
  for (let n = el; n; n = n.parentElement || n.getRootNode?.().host || null) {
    if (n.nodeType !== 1) continue;
    const cs = getComputedStyle(n);
    if (cs.backgroundImage && cs.backgroundImage !== 'none' && /url\(/.test(cs.backgroundImage)) return null;
    const c = parseColor(cs.backgroundColor);
    if (c && c[3] > 0.5) return c;
  }
  return [255, 255, 255, 1];
}
const inForm = area => !!area.closest('.rte');
/** Dunkle Darstellung der Verwaltung (Formularfelder) */
function adminDark(area) {
  const c = parseColor(getComputedStyle(area).backgroundColor) || bgOf(area);
  return c ? lum(c) < 0.2 : false;
}
/** Farben + Kontrast für die Leiste: [{name, label, css, ratio, ok}] */
function colorsFor(area) {
  const pal = palette();
  if (inForm(area)) {
    const dark = adminDark(area);
    return pal.colors.map(c => {
      const rl = ratio(parseColor(c.light), parseColor(pal.bg[0])), rd = pal.dark === false ? 21 : ratio(parseColor(c.dark), parseColor(pal.bg[1]));
      const r = Math.min(rl, rd);
      return { name: c.name, label: c.label, css: dark ? c.dark : c.light, ratio: r, ok: r >= 4.5, where: rl < 4.5 ? t('auf hellem Hintergrund') : t('im dunklen Farbschema') };
    });
  }
  const cs = getComputedStyle(area.parentElement || area);
  const bg = bgOf(area);
  return pal.colors.map(c => {
    const v = cs.getPropertyValue('--rt-' + c.name).trim();
    const rgb = /^currentcolor$/i.test(v) ? parseColor(getComputedStyle(area).color) : resolve(v) || parseColor(c.light);
    const r = bg && rgb ? ratio(rgb, bg) : 21;
    return { name: c.name, label: c.label, css: rgb ? `rgb(${rgb.slice(0, 3).map(Math.round).join(' ')})` : c.light, ratio: r, ok: r >= 4.5, where: t('auf diesem Hintergrund') };
  });
}
/** Formularfelder: Palette als Variablen (CSSOM, CSP-konform) – admin.css färbt .c-* damit */
function paintArea(area) {
  if (!inForm(area) || area._rtePainted) return;
  area._rtePainted = true;
  const box = area.closest('.rte');   // am Container: .rte-area schaltet per CSS auf die Dunkel-Werte um
  for (const c of palette().colors) { box.style.setProperty('--rt-' + c.name + '-l', c.light); box.style.setProperty('--rt-' + c.name + '-d', c.dark); }
}

// ------------------------------------------------------------------ Leiste (HTML)
const btn = (cmd, label, title, o = {}) => `<button type="button" data-cmd="${cmd}" aria-label="${esc(t(title))}${o.keys ? ' (' + o.keys + ')' : ''}" title="${esc(t(title))}${o.keys ? ' (' + o.keys + ')' : ''}"`
  + `${o.pressed ? ' aria-pressed="false"' : ''}${o.aria ? ` aria-keyshortcuts="${o.aria}"` : ''}${o.cls ? ` class="${o.cls}"` : ''}>${label}</button>`;
const item = (cmd, label, o = {}) => `<button type="button" role="${o.radio ? 'menuitemradio' : 'menuitem'}" tabindex="-1" data-cmd="${cmd}"${o.radio ? ' aria-checked="false"' : ''}${o.cls ? ` class="${o.cls}"` : ''}>`
  + `${o.pre || ''}<span class="rte-mi__t">${esc(t(label))}</span>${o.hint ? `<small class="rte-mi__h">${esc(t(o.hint))}</small>` : ''}${o.post || ''}</button>`;
const menuBtn = (name, label, title, extra = '') => `<button type="button" class="rte-mbtn rte-mbtn--${name}" data-menu="${name}" aria-haspopup="menu" aria-expanded="false" aria-controls="" aria-label="${esc(t(title))}" title="${esc(t(title))}">${label}<span class="rte-caret" aria-hidden="true">▾</span></button>${extra}`;

let uid = 0;
function barHtml(mode) {
  const n = ++uid;
  const menu = (name, label, body) => `<div class="rte-menu rte-menu--${name}" role="menu" id="rte-m${n}-${name}" aria-label="${esc(t(label))}" hidden>${body}</div>`;
  const lists = [['insertUnorderedList', '– ' + t('Liste'), 'Aufzählung'], ['insertOrderedList', '1. ' + t('Liste'), 'Nummerierte Liste'], ['checklist', '✓ ' + t('Liste'), 'Häkchen-Liste']];
  const inl = `<span class="rte-group">${btn('bold', '<b>B</b>', 'Fett', { pressed: 1, keys: kbd('B'), aria: ariaKeys('B') })}${btn('italic', '<i>I</i>', 'Kursiv', { pressed: 1, keys: kbd('I'), aria: ariaKeys('I') })}`
    + btn('mark', '<span class="rte-ico-mark" aria-hidden="true">ab</span>', 'Markieren (Textmarker)', { pressed: 1, keys: kbd('H', 1), aria: ariaKeys('H', 1) })
    + `<span class="rte-mwrap">${menuBtn('color', '<span class="rte-ico-a" aria-hidden="true">A<span class="rte-cbar"></span></span>', 'Textfarbe')}`
    + menu('color', 'Textfarbe', COLORS.map(c => item('color:' + c, colorLabel(c), { radio: 1, pre: `<span class="rte-sw" data-sw="${c}" aria-hidden="true"></span>`, post: '<small class="rte-mi__warn" hidden></small>', cls: 'rte-mi rte-mi--c-' + c })).join('')
      + '<span class="rte-menu__sep" role="separator"></span>' + item('color:none', 'Keine Farbe', { pre: '<span class="rte-sw rte-sw--none" aria-hidden="true"></span>' })
      + `<p class="rte-menu__note" data-contrast-note hidden></p>`) + '</span></span>'
    + `<span class="rte-group">${btn('link', '<span aria-hidden="true">🔗</span><span class="rte-lbl"> ' + esc(t('Link')) + '</span>', 'Link einfügen oder bearbeiten', { pressed: 1, keys: kbd('K'), aria: ariaKeys('K') })}</span>`;
  const more = (extra) => `<span class="rte-group"><span class="rte-mwrap">${menuBtn('more', '<span aria-hidden="true">⋯</span>', 'Weitere Formatierungen')}`
    + menu('more', 'Weitere Formatierungen', extra
      + item('superscript', 'Hochgestellt', { radio: 1, pre: '<span class="rte-mi__i" aria-hidden="true">x²</span>' })
      + item('subscript', 'Tiefgestellt', { radio: 1, pre: '<span class="rte-mi__i" aria-hidden="true">x₂</span>' })
      + item('unlink', 'Link entfernen', { pre: '<span class="rte-mi__i" aria-hidden="true">⌫</span>' })
      + item('paragraph', 'Formatierung entfernen', { pre: '<span class="rte-mi__i" aria-hidden="true">¶</span>', hint: mode === 'inline' ? '' : 'Normaler Text ohne Überschrift, Liste, Farbe' })
      + '<span class="rte-menu__sep" role="separator"></span>'
      + item('markdown', 'Markdown einfügen …', { pre: '<span class="rte-mi__i" aria-hidden="true">M↓</span>', hint: 'Text mit # Überschriften, - Listen, **fett** umwandeln' }))
    + '</span></span>';
  if (mode === 'inline') return inl + more('') + aiBarHtml();
  const style = `<span class="rte-group"><span class="rte-mwrap">${menuBtn('style', `<span class="rte-style-l">${esc(t('Normal'))}</span>`, 'Absatzstil')}`
    + menu('style', 'Absatzstil', STYLES.map(([v, label, , hint]) => item('style:' + v, label, { radio: 1, hint, cls: 'rte-mi rte-mi--' + v })).join('')) + '</span></span>';
  const listGroup = `<span class="rte-group rte-wide">${lists.map(([c, l, ti]) => btn(c, esc(l), ti, { pressed: 1 })).join('')}</span>`;
  const listItems = `<span class="rte-narrow">${lists.map(([c, , ti]) => item(c, ti, { radio: 1, pre: `<span class="rte-mi__i" aria-hidden="true">${c === 'checklist' ? '✓' : c === 'insertOrderedList' ? '1.' : '–'}</span>` })).join('')}</span>`
    + item('indent', 'Einrücken (Tab)', { pre: '<span class="rte-mi__i" aria-hidden="true">⇥</span>' }) + item('outdent', 'Ausrücken (Umschalt+Tab)', { pre: '<span class="rte-mi__i" aria-hidden="true">⇤</span>' })
    + '<span class="rte-menu__sep" role="separator"></span>';
  return style + inl + listGroup + more(listItems) + aiBarHtml();
}

// ------------------------------------------------------------------ Menüs
function openMenu(bar, b, area, focus) {
  closeMenus(bar);
  const m = b.parentNode.querySelector('.rte-menu');
  if (!m) return;
  if (area) keepSel(area);
  b.setAttribute('aria-expanded', 'true'); b.setAttribute('aria-controls', m.id);
  m.hidden = false;
  if (b.dataset.menu === 'color' && area) paintColors(bar, area);
  if (area) Rich.state(area, bar);
  // Menü im Fenster halten (CSSOM, CSP-konform)
  m.style.left = ''; m.style.right = ''; m.style.maxHeight = ''; m.classList.remove('rte-menu--up');
  // Unten kein Platz (Leiste am Fensterrand): nach oben öffnen, sonst Höhe begrenzen und scrollen
  const br = b.getBoundingClientRect(), below = innerHeight - br.bottom - 12, above = br.top - 12;
  if (m.offsetHeight > below) {
    if (above > below) { m.classList.add('rte-menu--up'); if (m.offsetHeight > above) m.style.maxHeight = above + 'px'; }
    else m.style.maxHeight = Math.max(160, below) + 'px';
  }
  const r = m.getBoundingClientRect();
  if (r.right > innerWidth - 8) { m.style.left = 'auto'; m.style.right = '0'; }
  if (m.getBoundingClientRect().left < 8) { m.style.right = 'auto'; m.style.left = (8 - b.getBoundingClientRect().left) + 'px'; }
  if (focus) (m.querySelector('[aria-checked=true]:not([hidden])') || items(m)[0])?.focus();
}
function closeMenus(bar, focusBtn) {
  $$('.rte-menu:not([hidden])', bar).forEach(m => {
    m.hidden = true;
    const b = m.parentNode.querySelector('[data-menu]');
    b?.setAttribute('aria-expanded', 'false');
    if (focusBtn) b?.focus();
  });
}
const items = m => [...m.querySelectorAll('[role^=menuitem]')].filter(i => i.offsetParent !== null && i.getAttribute('aria-disabled') !== 'true');
function paintColors(bar, area) {
  const list = colorsFor(area);
  const bad = list.filter(c => !c.ok);
  for (const c of list) {
    bar.querySelectorAll(`[data-sw="${c.name}"]`).forEach(s => { s.style.background = c.css; });
    const it = bar.querySelector(`[data-cmd="color:${c.name}"]`);
    const w = it?.querySelector('.rte-mi__warn');
    if (w) { w.hidden = c.ok; w.textContent = c.ok ? '' : '⚠ ' + t('Kontrast {r} : 1', { r: c.ratio.toFixed(1).replace('.', ',') }); }
    if (it) it.setAttribute('aria-description', c.ok ? '' : t('Zu wenig Kontrast {where} – nicht für wichtige Informationen verwenden.', { where: c.where }));
  }
  const note = bar.querySelector('[data-contrast-note]');
  if (note) {
    note.hidden = !bad.length;
    note.textContent = bad.length ? t('⚠ Zu wenig Kontrast {where} (unter 4,5 : 1): {list}. Bitte andere Farbe wählen oder im Design anpassen.', { where: bad[0].where, list: bad.map(c => c.label).join(', ') }) : '';
  }
}

// ------------------------------------------------------------------ Auswahl merken (Tastatur in Menüs)
function keepSel(area) { const r = rangeIn(area); if (r) area._rteRange = r.cloneRange(); }
function restoreSel(area) {
  if (rangeIn(area) || !area._rteRange) return;
  const s = selOf(area); s.removeAllRanges(); s.addRange(area._rteRange);
}

// ------------------------------------------------------------------ Befehle
const Rich = {
  closestList(area) { return up(area, n => n.tagName === 'UL' || n.tagName === 'OL'); },
  blockTag(area) { const n = up(area, n => /^(H2|H3|H4|P|LI|BLOCKQUOTE|DIV)$/.test(n.tagName)); return n ? n.tagName.toLowerCase() : ''; },
  closestQuote(area) { return up(area, n => n.tagName === 'BLOCKQUOTE'); },
  /** Zitat aufheben: Inhalt bleibt, lose Textteile werden zum Absatz */
  unquote(q) {
    const frag = d.createDocumentFragment();
    let p = null;
    [...q.childNodes].forEach(c => {
      const block = c.nodeType === 1 && /^(P|H2|H3|H4|UL|OL|BLOCKQUOTE)$/.test(c.tagName);
      if (block) { p = null; frag.append(c); return; }
      if (!p) { if (c.nodeType === 3 && !c.textContent.trim()) return; p = d.createElement('p'); frag.append(p); }
      p.append(c);
    });
    const first = frag.firstChild;
    q.replaceWith(frag);
    if (first) { const r = d.createRange(); r.selectNodeContents(first); r.collapse(false); const sel = selOf(first); sel.removeAllRanges(); sel.addRange(r); }
  },
  setCheck(ul, on) { ul.classList.toggle('check', on); if (!ul.className) ul.removeAttribute('class'); },
  /** Absätze (p) in der Auswahl */
  paragraphs(area) {
    const r = rangeIn(area);
    let ps = r ? $$('p', area).filter(p => r.intersectsNode(p)) : [];
    const own = up(area, n => n.tagName === 'P');
    if (own && !ps.includes(own)) ps.push(own);
    return ps;
  },
  /** Absatzstil: p (normal), t-lead/t-small/t-note, h2–h4, blockquote – erneut wählen hebt auf */
  setStyle(area, s) {
    if (HEADINGS.includes(s)) { d.execCommand('formatBlock', false, Rich.blockTag(area) === s ? 'p' : s); return; }
    if (s === 'blockquote') { const q = Rich.closestQuote(area); if (q) Rich.unquote(q); else d.execCommand('formatBlock', false, 'blockquote'); return; }
    if (Rich.closestList(area)) return;   // Listen behalten ihr Aussehen
    const q = Rich.closestQuote(area);
    if (q) Rich.unquote(q);
    if (HEADINGS.includes(Rich.blockTag(area)) || !Rich.paragraphs(area).length) d.execCommand('formatBlock', false, 'p');
    const ps = Rich.paragraphs(area);
    const on = P_STYLES.includes(s) && !ps.every(p => p.classList.contains(s));
    ps.forEach(p => { P_STYLES.forEach(c => p.classList.remove(c)); if (on) p.classList.add(s); if (!p.className) p.removeAttribute('class'); });
  },
  /** Textfarbe (Klasse c-…) bzw. „none“ */
  color(area, name) {
    const r = rangeIn(area);
    if (!r) return;
    if (r.collapsed) {   // Cursor in gefärbtem Text: ganze Stelle umfärben bzw. Farbe entfernen
      const span = up(area, isColor);
      if (span) { if (name === 'none') unwrap(span); else span.className = 'c-' + name; }
      return;
    }
    const made = [];
    for (const f of markSelection(area)) {
      $$('span', f).filter(isColor).forEach(unwrap);
      for (let a = f.parentNode; a && a !== area; a = a.parentNode) if (a.nodeType === 1 && isColor(a)) { lift(f, a); break; }
      if (name === 'none') { made.push(...f.childNodes); unwrap(f); continue; }
      const s = d.createElement('span'); s.className = 'c-' + name;
      while (f.firstChild) s.append(f.firstChild);
      f.replaceWith(s); made.push(s);
    }
    selectNodes(area, made);
  },
  /** Textmarker an/aus (<mark>) */
  mark(area) {
    const r = rangeIn(area);
    if (!r) return;
    const isMark = n => n.tagName === 'MARK';
    if (r.collapsed) { const m = up(area, isMark); if (m) unwrap(m); return; }
    const remove = allIn(area, r, isMark);
    const made = [];
    for (const f of markSelection(area)) {
      $$('mark', f).forEach(unwrap);
      let anc = null;
      for (let a = f.parentNode; a && a !== area; a = a.parentNode) if (a.nodeType === 1 && isMark(a)) { anc = a; break; }
      if (remove) { if (anc) lift(f, anc); made.push(...f.childNodes); unwrap(f); continue; }
      if (anc) { made.push(...f.childNodes); unwrap(f); continue; }
      const m = d.createElement('mark');
      while (f.firstChild) m.append(f.firstChild);
      f.replaceWith(m); made.push(m);
    }
    selectNodes(area, made);
  },
  /** Link einfügen/bearbeiten/entfernen über die Linkauswahl (_links.js) */
  async link(area) {
    const sel = selOf(area), range = rangeIn(area)?.cloneRange() || null;
    const a = up(area, n => n.tagName === 'A');
    const cur = a ? { href: a.getAttribute('href') || '', ref: a.dataset.link || '', newTab: a.target === '_blank', title: a.title || '' } : null;
    const picked = range && !range.collapsed ? hrefFor(range.toString(), true) : null;
    if (!a && picked) {   // markierte E-Mail- oder Web-Adresse: gleich verlinken (Betreff, neuer Tab o. Ä. später über „Link“)
      Rich.insertLink(area, { href: picked, label: range.toString().trim() }, range);
      area.dispatchEvent(new Event('input', { bubbles: true }));
      return;
    }
    const res = await openLinkPicker({ mode: 'rich', current: cur, text: range ? range.toString() : '' });
    area.focus();
    if (range) { sel.removeAllRanges(); sel.addRange(range); }
    if (!res || res.keep && !a) return;
    if (res.remove) { if (a) unwrap(a); else d.execCommand('unlink'); return; }
    Rich.insertLink(area, res, range, a);
  },
  /**
   * Link in ein bearbeitbares Element setzen – gemeinsame Logik für die Linkauswahl und Werkzeuge (CMSAdmin.tools, ctx.insertLink).
   * res: {href, ref (z. B. entry:glossar:12), label (Linktext ohne Markierung), newTab, title}. range: Auswahl (sonst die aktuelle),
   * a: vorhandener Link zum Ändern. Markierter Text wird zum Link, sonst wird label als neuer Link an der Schreibmarke eingefügt.
   */
  insertLink(area, res, range = null, a = null) {
    if (range) { area.focus({ preventScroll: true }); const sel = selOf(area); sel.removeAllRanges(); sel.addRange(range); }
    else range = rangeIn(area)?.cloneRange() || null;
    a ??= up(area, n => n.tagName === 'A');
    const apply = el => {
      el.setAttribute('href', res.href);
      if (res.ref) el.dataset.link = res.ref; else el.removeAttribute('data-link');
      if (res.newTab) { el.target = '_blank'; el.rel = 'noopener'; } else { el.removeAttribute('target'); el.removeAttribute('rel'); }
      if (res.title) el.title = res.title; else el.removeAttribute('title');
    };
    if (a) { apply(a); return; }
    if (range && !range.collapsed) {
      d.execCommand('createLink', false, res.href);
      const r2 = rangeIn(area);
      const made = $$('a', area).filter(x => x.getAttribute('href') === res.href && (!r2 || r2.intersectsNode(x)));
      made.forEach(apply);
      return;
    }
    // Kein Text markiert: Linktext = Name des Ziels
    if (!range) { const r = d.createRange(); r.selectNodeContents(area); r.collapse(false); const s2 = selOf(area); s2.removeAllRanges(); s2.addRange(r); }
    d.execCommand('insertHTML', false, `<a href="${esc(res.href)}" data-rte-new="1">${esc(res.label || res.href)}</a>`);
    $$('a[data-rte-new]', area).forEach(x => { x.removeAttribute('data-rte-new'); apply(x); });
  },
  /**
   * Markdown an der Schreibmarke einfügen (_markdown.js → nur Tags der Whitelist). Über execCommand('insertHTML'), damit
   * ⌘/Strg+Z es rückgängig macht. mode: 'rich' | 'inline' (nur fett/kursiv/Link/Umbruch).
   */
  insertMarkdown(area, text, mode = area._rteMode || 'rich') {
    const { html } = toHtml(text, { mode });
    if (!html) return false;
    if (!rangeIn(area)) { const r = d.createRange(); r.selectNodeContents(area); r.collapse(false); const s = selOf(area); s.removeAllRanges(); s.addRange(r); }
    d.execCommand('insertHTML', false, html);
    // Browser übertragen beim Einfügen teils berechnete Stile (<span style>, <font>) – nie Teil des Rich-Texts
    $$('[style]', area).forEach(el => el.removeAttribute('style'));
    $$('span:not([class]),font', area).forEach(unwrap);
    area.dispatchEvent(new Event('input', { bubbles: true }));
    return true;
  },
  /** „⋯ → Markdown einfügen …“: Dialog mit Textfeld und Vorschau, danach Einfügen an der gemerkten Schreibmarke */
  async markdown(area) {
    restoreSel(area);
    const range = rangeIn(area)?.cloneRange() || null;
    const mode = area._rteMode || 'rich';
    const res = await openImport({ box: layerBox(), mode, title: t('Markdown einfügen'), returnFocus: area,
      intro: mode === 'inline' ? t('Dieses Feld kennt nur fett, kursiv, Links und Zeilenumbrüche – Überschriften und Listen werden zu Textzeilen.') : '' });
    area.focus();
    if (range) { const s = selOf(area); s.removeAllRanges(); s.addRange(range); }
    area._rteRange = null;
    if (res) Rich.insertMarkdown(area, res.text, mode);
  },
  /** Formatierungsbefehl auf ein bearbeitbares Element anwenden */
  async exec(area, cmd) {
    if (cmd === 'ai') { aiExec(area); return; }   // KI-Assistent (_ai.js)
    if (cmd === 'markdown') { await Rich.markdown(area); return; }
    restoreSel(area);
    area.focus();
    const list = Rich.closestList(area);
    if (cmd === 'link') await Rich.link(area);
    else if (cmd.startsWith('style:')) Rich.setStyle(area, cmd.slice(6));
    else if (cmd.startsWith('color:')) Rich.color(area, cmd.slice(6));
    else if (cmd === 'mark') Rich.mark(area);
    else if (HEADINGS.includes(cmd) || cmd === 'blockquote') Rich.setStyle(area, cmd);
    else if (cmd === 'paragraph') {
      let guard = 5;
      while (Rich.closestList(area) && guard--) d.execCommand(Rich.closestList(area).tagName === 'OL' ? 'insertOrderedList' : 'insertUnorderedList');
      while (Rich.closestQuote(area) && guard--) Rich.unquote(Rich.closestQuote(area));
      if (area.closest('[data-mode=inline],[data-edit-mode=inline]') === null) d.execCommand('formatBlock', false, 'p');
      d.execCommand('removeFormat');
      d.execCommand('unlink');
      Rich.color(area, 'none');
      const r = rangeIn(area);
      if (r) { $$('mark', area).filter(m => r.intersectsNode(m)).forEach(unwrap); Rich.paragraphs(area).forEach(p => p.removeAttribute('class')); }
    } else if (cmd === 'checklist' || cmd === 'insertUnorderedList') {
      const isCheck = list && list.tagName === 'UL' && list.classList.contains('check');
      if (isCheck) Rich.setCheck(list, false);                                   // Häkchen → Strich
      else if (cmd === 'checklist' && list && list.tagName === 'UL') Rich.setCheck(list, true);
      else {
        d.execCommand('insertUnorderedList');
        if (cmd === 'checklist' && Rich.closestList(area)) Rich.setCheck(Rich.closestList(area), true);
      }
    } else if (cmd === 'indent' || cmd === 'outdent') {
      if (list) d.execCommand(cmd);                                               // nur innerhalb von Listen
    } else d.execCommand(cmd);
    $$('font', area).forEach(unwrap);   // Sicherheitsnetz: Hilfsfarbe nie stehen lassen
    area._rteRange = null;
    area.dispatchEvent(new Event('input', { bubbles: true }));
  },
  /** Zustand der Knöpfe: aria-pressed, Stil-Beschriftung, aktuelle Farbe, Häkchen in Menüs */
  state(area, bar) {
    area._rteBar = bar;
    paintArea(area);
    const s = selOf(area);
    if (!area.contains(s.anchorNode)) return;
    const list = Rich.closestList(area), check = !!list && list.tagName === 'UL' && list.classList.contains('check');
    const tag = Rich.blockTag(area), q = Rich.closestQuote(area);
    const p = up(area, n => n.tagName === 'P');
    const style = q ? 'blockquote' : HEADINGS.includes(tag) ? tag : (p && P_STYLES.find(c => p.classList.contains(c))) || 'p';
    const color = colorOf(up(area, isColor) || d.createElement('i'));
    const on = c => c === 'insertUnorderedList' ? !!list && list.tagName === 'UL' && !check
      : c === 'insertOrderedList' ? !!list && list.tagName === 'OL'
      : c === 'checklist' ? check
      : c === 'mark' ? !!up(area, n => n.tagName === 'MARK')
      : c === 'link' ? !!up(area, n => n.tagName === 'A')
      : HEADINGS.includes(c) ? tag === c
      : c === 'blockquote' ? !!q
      : c.startsWith('style:') ? c.slice(6) === style
      : c.startsWith('color:') ? c.slice(6) === color
      : ['bold', 'italic', 'superscript', 'subscript'].includes(c) ? d.queryCommandState(c) : false;
    $$('[aria-pressed]', bar).forEach(b => b.setAttribute('aria-pressed', on(b.dataset.cmd) ? 'true' : 'false'));
    $$('[aria-checked]', bar).forEach(b => b.setAttribute('aria-checked', on(b.dataset.cmd) ? 'true' : 'false'));
    // Stile in Listen nicht möglich (Überschriften/Zitat schon)
    $$('[data-cmd^="style:t-"]', bar).forEach(b => b.setAttribute('aria-disabled', list ? 'true' : 'false'));
    const sb = bar.querySelector('[data-menu=style]');
    if (sb) {
      const st = STYLES.find(x => x[0] === style);
      const short = list ? t('Liste') : t(st[2]);
      sb.querySelector('.rte-style-l').textContent = short;
      sb.setAttribute('aria-label', t('Absatzstil') + ': ' + (list ? t('Liste') : t(st[1])));
    }
    const cb = bar.querySelector('[data-menu=color]');
    if (cb) {
      const cbar = cb.querySelector('.rte-cbar');
      if (color) { const c = colorsFor(area).find(x => x.name === color); cbar.style.background = c?.css || ''; } else cbar.style.background = '';
      cb.setAttribute('aria-label', t('Textfarbe') + (color ? ': ' + colorLabel(color) : ''));
    }
  },
  barHtml,
  /**
   * Leiste verdrahten (Klicks, Menüs, Tastatur, Zustand). getArea() liefert das aktuell bearbeitete Element,
   * after() läuft nach jedem Befehl (z. B. Leiste neu platzieren).
   */
  mount(bar, getArea, after = () => {}) {
    if (bar._rteMounted) return;
    bar._rteMounted = true;
    bar.addEventListener('mousedown', e => { if (e.target.closest('button')) e.preventDefault(); });   // Auswahl im Text behalten
    bar.addEventListener('click', async e => {
      const area = getArea();
      const mb = e.target.closest('[data-menu]');
      if (mb) {
        if (mb.getAttribute('aria-expanded') === 'true') closeMenus(bar); else openMenu(bar, mb, area, e.detail === 0);
        return;
      }
      const b = e.target.closest('[data-cmd]');
      if (!b || !area || b.getAttribute('aria-disabled') === 'true') return;
      closeMenus(bar);
      await Rich.exec(area, b.dataset.cmd);
      Rich.state(area, bar);
      after();
    });
    bar.addEventListener('keydown', e => {
      const area = getArea();
      const m = e.target.closest('.rte-menu');
      if (m) {
        const list = items(m), i = list.indexOf(e.target);
        const go = n => { e.preventDefault(); list[(n + list.length) % list.length]?.focus(); };
        if (e.key === 'ArrowDown') go(i + 1);
        else if (e.key === 'ArrowUp') go(i - 1);
        else if (e.key === 'Home') go(0);
        else if (e.key === 'End') go(list.length - 1);
        else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closeMenus(bar, true); }
        else if (e.key === 'Tab') closeMenus(bar);
        return;
      }
      const mb = e.target.closest('[data-menu]');
      if (mb && (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openMenu(bar, mb, area, true); return; }
      // Knöpfe der Leiste: ←/→ wandern, Esc zurück in den Text
      const btns = $$(':scope button:not(.rte-menu button)', bar).filter(b => b.offsetParent !== null);
      const i = btns.indexOf(e.target);
      if (i >= 0 && (e.key === 'ArrowRight' || e.key === 'ArrowLeft')) { e.preventDefault(); btns[(i + (e.key === 'ArrowRight' ? 1 : -1) + btns.length) % btns.length].focus(); }
      else if (e.key === 'Escape' && area) { e.preventDefault(); e.stopPropagation(); area.focus(); restoreSel(area); }
    });
    // Klick außerhalb schließt Menüs
    d.addEventListener('pointerdown', e => { if (!(e.composedPath?.() || []).includes(bar)) closeMenus(bar); }, true);
    d.addEventListener('selectionchange', () => { const a = getArea(); if (a && !bar.hidden && bar.isConnected && a.contains(selOf(a).anchorNode)) Rich.state(a, bar); });
  },
  /** Tastatur: Kürzel, Tab/Umschalt+Tab in Listen, Enter im Inline-Modus = Zeilenumbruch, Einfügen nur als Text */
  bindKeys(area, mode) {
    if (area._rteKeys) return;
    area._rteKeys = true;
    area._rteMode = mode;
    area.addEventListener('focus', () => {
      try { d.execCommand('defaultParagraphSeparator', false, 'p'); } catch {}
      // Leeres Feld: mit einem Absatz beginnen (sonst steht die erste Zeile ohne <p> und Stile greifen nicht)
      if (mode !== 'inline' && /^(\s|<br>)*$/.test(area.innerHTML)) {
        area.innerHTML = '<p><br></p>';
        const r = d.createRange(); r.setStart(area.firstChild, 0); r.collapse(true);
        const s = selOf(area); s.removeAllRanges(); s.addRange(r);
      }
    });
    area.addEventListener('keydown', e => {
      const mod = (e.metaKey || e.ctrlKey) && !e.altKey, k = e.key.toLowerCase();
      if (mod && !e.shiftKey && k === 'k') { e.preventDefault(); e.stopPropagation(); keepSel(area); Rich.exec(area, 'link').then(() => area._rteBar && Rich.state(area, area._rteBar)); }
      else if (mod && e.shiftKey && k === 'h') { e.preventDefault(); e.stopPropagation(); Rich.exec(area, 'mark').then(() => area._rteBar && Rich.state(area, area._rteBar)); }
      else if (e.altKey && e.key === 'F10' && area._rteBar) { e.preventDefault(); keepSel(area); area._rteBar.querySelector('button')?.focus(); }
      else if (e.key === 'Tab' && Rich.closestList(area)) { e.preventDefault(); e.stopPropagation(); Rich.exec(area, e.shiftKey ? 'outdent' : 'indent'); }
      else if (e.key === 'Enter' && mode === 'inline') { e.preventDefault(); d.execCommand('insertLineBreak'); }
      else if (e.key === 'Enter' && !e.shiftKey) {
        // „Hervorgehoben“ ist ein Einstieg: der nächste Absatz ist wieder normal
        setTimeout(() => { const p = up(area, n => n.tagName === 'P'); if (p && p.classList.contains('t-lead') && !p.textContent.trim()) { p.removeAttribute('class'); area.dispatchEvent(new Event('input', { bubbles: true })); } }, 0);
      }
    });
    // Getippte E-Mail- oder Web-Adresse verlinken, sobald danach Leerzeichen, Satzzeichen oder Enter folgt (Strg+Z macht es nicht rückgängig – Link entfernen über „Link“)
    area.addEventListener('input', e => {
      const ch = e.inputType === 'insertText' ? (e.data || '') : '';
      const enter = e.inputType === 'insertParagraph' || e.inputType === 'insertLineBreak';
      // Telefonnummern enthalten Leerzeichen: erst prüfen, wenn danach ein Wort beginnt („… 35656 o“), ein Satzzeichen oder Enter folgt
      const sel0 = selOf(area), n0 = sel0.anchorNode;
      const wordAfterSpace = /^[^\d\s+/().-]$/.test(ch) && n0?.nodeType === 3 && /\s$/.test(n0.data.slice(0, sel0.anchorOffset - ch.length));
      const tel = enter || /^[,;:!?]$/.test(ch) || wordAfterSpace;
      if (/^[\s,;:!?)]$/.test(ch) || enter || wordAfterSpace) {
        const sel = selOf(area); if (!sel.rangeCount) return;
        let node = sel.anchorNode, off = sel.anchorOffset;
        if (e.inputType === 'insertText') { if (node?.nodeType !== 3) return; off -= (e.data || '').length; }
        else {
          // Enter: Adresse steht am Ende des vorigen Blocks bzw. vor dem <br>
          const blk = e.inputType === 'insertParagraph' ? (up(area, n => /^(P|LI|H[1-6]|DIV)$/.test(n.tagName))?.previousElementSibling) : null;
          const scope = blk || (node.nodeType === 3 ? node.parentNode : node);
          const w = d.createTreeWalker(scope, NodeFilter.SHOW_TEXT); let last = null;
          if (blk) { while (w.nextNode()) last = w.currentNode; }
          else { const br = sel.anchorNode.nodeType === 3 ? sel.anchorNode.previousSibling : sel.anchorNode.childNodes[off - 1]; last = br?.previousSibling?.nodeType === 3 ? br.previousSibling : null; }
          if (!last) return; node = last; off = last.data.length;
        }
        const r = sel.getRangeAt(0).cloneRange();
        if (linkifyBefore(area, node, off, tel, /^[,;:!?)]$/.test(ch))) { sel.removeAllRanges(); sel.addRange(r); }
      }
    });
    area.addEventListener('paste', e => {
      e.preventDefault();
      const cd = e.clipboardData || window.clipboardData, text = cd.getData('text/plain');
      hideTip();
      // Eindeutiges Markdown ohne echte Formatierung in der Zwischenablage: umwandeln, Hinweis bietet „Als Text einfügen“
      if (looksLike(text) && htmlIsPlain(cd.getData('text/html')) && Rich.insertMarkdown(area, text, mode)) {
        pasteTip(layerBox(), () => { area.focus(); d.execCommand('undo'); d.execCommand('insertText', false, text); area.dispatchEvent(new Event('input', { bubbles: true })); });
        return;
      }
      d.execCommand('insertText', false, text);
    });
  },
};

export { Rich };
