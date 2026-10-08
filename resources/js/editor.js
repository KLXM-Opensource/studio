/*
 * Inline-Editor (Core, Theme-unabhängig) auf Basis von Editor.js.
 *
 *  - Jeder Blocktyp des Themes wird automatisch ein Editor.js-Tool (aus dem Feld-Schema).
 *  - WYSIWYG: Blöcke zeigen die serverseitig gerenderte Vorschau mit den Frontend-Styles.
 *  - Texte mit [data-edit] sind direkt im Frontend editierbar.
 *  - Seitenleiste: alle Felder (Formular vom Server, gleicher Renderer wie im Admin) – nur über „Bearbeiten“ (Klick in den
 *    Block wählt ihn bloß aus, .is-selected), „Inhalte eingeben“, Block-Tune oder Sprung zum Block.
 *  - „+ Block einfügen“ unten mittig an jedem Block (BlockPicker): fügt nach diesem Block ein.
 *  - Block-Tune „Abschnitt“: Hintergrund, Anker, Sichtbarkeit, Navigation, Abstände.
 *  - Drag & Drop über editorjs-drag-drop.
 *  - Bild anpassen je Einbindung (Core\ImageFx): data._fx = {feldpfad: anpassung}; Knopf am Bild (_media.js) und je Bild-Feld
 *    in der Seitenleiste. Gespeichert wird mit dem normalen Entwurf.
 *  - Bild im Rahmen je Einbindung (Core\ImageFit): data._fit = {feldpfad: „contain blur“ | „original“ | …}, gleiche Stellen.
 *  - Block „Layout“ (Core\Layout): Spalten mit Blöcken – Leiste je Block in der Spalte (↑ ↓ ← →, Bearbeiten, Löschen),
 *    „+ Block in diese Spalte“ (nur verschachtelbare Blöcke), Direktbearbeitung über Pfade columns.{s}.blocks.{n}.data.{feld}.
 */
import { responsiveEditing } from './_cq.js';
const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const cfg = JSON.parse($('#cms-editor-config').textContent);
// Website reagiert im Editor auf ihre eigene Breite (Seitenleiste breit → Tablet-/Handy-Ansicht) – resources/js/_cq.js
responsiveEditing();
// Shadow DOM (resources/js/_shadow.js über window.CMSAdmin): Werkzeugleiste, Ebene für Seitenleiste/Leisten, Block-Leisten
const S = CMSAdmin.shadow;
// Blocksymbol: Symbol aus dem Sprite (def.ico, vom Server aufgelöst), sonst Zeichen aus theme.php
const blockIcon = def => (def.ico && CMSAdmin.ico ? CMSAdmin.ico(def.ico) : CMSAdmin.esc(def.icon || '▦'));
const layerBox = () => S.layerBox();
// Seitenleiste „Block“ (serverseitig im Dokument gerendert) in die Ebene verschieben – Theme-CSS wirkt dort nicht
const drawer = $('#cms-drawer');
layerBox().append(drawer);
// Breite der Seitenleiste ziehen (gemerkt); Standard: clamp(480px, 40vw, 720px) aus editor.css/editor.shadow.css
(() => {
  const root = d.documentElement, KEY = 'cms:drawer-w';
  const set = w => {
    if (!w) { root.style.removeProperty('--cms-drawer-w'); try { localStorage.removeItem(KEY); } catch { /* privat */ } return; }
    w = Math.round(Math.max(380, Math.min(w, innerWidth - 320, 1100)));
    root.style.setProperty('--cms-drawer-w', w + 'px');
    try { localStorage.setItem(KEY, String(w)); } catch { /* privat */ }
  };
  try { const w = +localStorage.getItem(KEY); if (w) set(w); } catch { /* privat */ }
  const grip = d.createElement('div');
  grip.className = 'cms-drawer__grip'; grip.tabIndex = 0;
  grip.setAttribute('role', 'separator'); grip.setAttribute('aria-orientation', 'vertical');
  const tr = (window.CMSAdmin && CMSAdmin.t) || (x => x);
  grip.setAttribute('aria-label', tr('Breite der Seitenleiste ändern (Pfeiltasten, Doppelklick = Standard)'));
  grip.title = tr('Breite ziehen · Doppelklick = Standard');
  drawer.prepend(grip);
  grip.addEventListener('pointerdown', e => {
    e.preventDefault(); grip.setPointerCapture(e.pointerId); drawer.classList.add('is-resizing');
    const move = ev => set(innerWidth - ev.clientX);
    const up = () => { drawer.classList.remove('is-resizing'); grip.removeEventListener('pointermove', move); grip.removeEventListener('pointerup', up); };
    grip.addEventListener('pointermove', move); grip.addEventListener('pointerup', up);
  });
  grip.addEventListener('keydown', e => {
    const k = { ArrowLeft: 40, ArrowRight: -40 }[e.key];
    if (k) { e.preventDefault(); set(drawer.getBoundingClientRect().width + k); }
  });
  grip.addEventListener('dblclick', () => set(0));
})();
const initial = JSON.parse($('#cms-editor-data').textContent);
const previews = initial.previews || {};
const tools = new Map();      // blockId → Tool-Instanz
const tunes = new Map();      // blockId → Tune-Instanz
const initialTunes = Object.fromEntries((initial.blocks || []).map(b => [b.id, b.tunes?.section || {}]));
const TUNE_DEFAULTS = { background: 'white', anchor: '', visible: true, showInNav: false, navLabel: '', spaceTop: 'normal', spaceBottom: 'normal', divider: false, height: 'auto', bgImage: null, overlay: 'none', align: 'center', row: '', noGlossary: false };
let editor, dirty = false, drawerFor = null, drawerSnap = null;
let History = null;   // Rückgängig/Wiederholen (unten)
const store = { get(k, d) { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch { return d; } }, set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch {} } };
const COLLAPSE_KEY = 'cms-collapsed-' + cfg.page.id;
const collapsed = new Set(store.get(COLLAPSE_KEY, []));
const holder = () => $('#cms-editor');
const LAYOUT = 'layout';   // Kern-Block „Layout“ (Core\Layout)
const blockEls = () => $$('.ce-block', holder());
/** Kurzbeschreibung eines Blocks für die eingeklappte Zeile */
const summarize = data => {
  // Layout: Kurzbeschreibungen der Blöcke in den Spalten
  if (Array.isArray(data?.columns) && data.columns.length && data.columns.every(c => c && Array.isArray(c.blocks))) {
    return data.columns.flatMap(c => c.blocks).map(b => summarize(b.data) || cfg.blocks[b.type]?.label || b.type).filter(Boolean).join(' · ').slice(0, 90);
  }
  for (const k of ['title_strong', 'title', 'caption', 'intro', 'text', 'q']) {
    const v = data?.[k];
    if (typeof v === 'string' && v.trim()) return v.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 90);
  }
  const first = ['items', 'files', 'columns', 'buttons'].map(k => data?.[k]?.[0]).find(Boolean);
  return first ? summarize(first) : '';
};
/**
 * Blöcke nebeneinander (Tune „row“, Core\Theme::renderRow): Im Editor stehen Blöcke einer Reihe auf breiten Bildschirmen
 * ebenfalls nebeneinander (Anteile wie auf der Website, editor.css → .cms-row-cell), schmal untereinander. Jeder Block der Reihe
 * trägt die Markierung „In einer Reihe mit dem vorigen Block (½)“; beim ersten Block der Seite: „wird ignoriert“.
 */
const ROW_W = { auto: null, '1-2': 6, '1-3': 4, '2-3': 8, '1-4': 3, '3-4': 9 };
const ROW_LABEL = { auto: 'automatisch', '1-2': '½', '1-3': '⅓', '2-3': '⅔', '1-4': '¼', '3-4': '¾' };
function rowSpans(rows) {
  let fixed = 0, autos = 0;
  rows.forEach((r, i) => { const n = i === 0 ? null : ROW_W[r] ?? null; n === null ? autos++ : fixed += n; });
  const share = autos ? Math.max(2, Math.floor(Math.max(0, 12 - fixed) / autos)) : 0;
  return rows.map((r, i) => (i === 0 ? share : ROW_W[r] ?? share));
}
addEventListener('resize', () => { clearTimeout(layoutRows.t); layoutRows.t = setTimeout(layoutRows, 150); });
function layoutRows() {
  if (!cfg.rows) return;
  const groups = [];
  blockEls().forEach((ce, i) => {
    const tool = tools.get(ce.dataset.id), row = tool?.tuneData?.row || '';
    const x = { ce, el: tool?.el, row, raw: !!tool?.def?.raw };
    const last = groups[groups.length - 1];
    if (row && i > 0 && !x.raw && !last[0].raw) last.push(x); else groups.push([x]);
  });
  // Inhaltsbereich des Kits (wie auf der Website): .wrap eines Blocks außerhalb einer Reihe – Reihen im Editor daran ausrichten
  const red = holder()?.querySelector('.codex-editor__redactor');
  const refWrap = groups.filter(g => g.length === 1).map(g => g[0].el?.querySelector(':scope>.cms-block__preview>section>.wrap')).find(Boolean);
  let inset = null;
  if (red && refWrap) {
    const rr = red.getBoundingClientRect(), wr = refWrap.getBoundingClientRect(), cs = getComputedStyle(refWrap);
    inset = { l: Math.max(0, wr.left + parseFloat(cs.paddingLeft) - rr.left), r: Math.max(0, rr.right - (wr.right - parseFloat(cs.paddingRight))) };
  }
  const GAP = 48;
  for (const g of groups) {
    const spans = rowSpans(g.map(x => x.row));
    g.forEach((x, i) => {
      // Zelle: linker Rand = Inhaltskante (erste Zelle) bzw. Abstand zur vorigen Zelle, rechter Rand = Inhaltskante (letzte Zelle)
      const on = g.length > 1 && inset;
      const pl = !on ? 0 : (i === 0 ? inset.l : GAP), pr = !on ? 0 : (i === g.length - 1 ? inset.r : 0);
      for (const [k, v] of [['--cms-row-pl', pl], ['--cms-row-pr', pr], ['--cms-row-basis', pl + pr]]) {
        if (on) x.ce.style.setProperty(k, v + 'px'); else x.ce.style.removeProperty(k);
      }
    });
    g.forEach((x, i) => {
      const inRow = g.length > 1;
      x.ce.classList.toggle('cms-row-cell', inRow);
      x.ce.classList.toggle('cms-row-lead', inRow && i === 0);
      if (inRow) x.ce.style.setProperty('--cms-row-g', String(spans[i])); else x.ce.style.removeProperty('--cms-row-g');
      if (!x.el) return;
      // Vorschau eines Blocks „neben dem vorigen“ (Theme::renderBlock → .sec--row-preview): Hintergrund und Abstände wie der
      // erste Block der Reihe; eigene Fläche (Karte) nur bei anderem Hintergrund – wie auf der Website (Theme::renderRow)
      const pv = x.el.querySelector(':scope>.cms-block__preview>.sec--row-preview');
      const leadSec = g[0].el?.querySelector(':scope>.cms-block__preview>section');
      if (pv && i > 0 && leadSec) {
        const own = pv.dataset.rowBg || '', lead = [...leadSec.classList].find(c => c.startsWith('bg-'))?.slice(3) || '';
        pv.className = ['sec', 'sec--row-preview', ...[...leadSec.classList].filter(c => /^(bg-|pt-|pb-)/.test(c))].join(' ');
        const cell = pv.querySelector('.sec-row__cell');
        if (cell) {
          cell.classList.toggle('sec-row__cell--card', own !== lead);
          cell.classList.toggle('sec', own !== lead);
          cell.classList.toggle('bg-' + own, own !== lead);
        }
      }
      const ignored = !!x.row && i === 0;
      x.el.classList.toggle('is-row', !!x.row);
      x.el.classList.toggle('is-row-ignored', ignored);
      if (x.row) x.el.dataset.rowLabel = ignored
        ? CMSAdmin.t('Neben den vorigen Block – wird ignoriert (kein Block davor)')
        : CMSAdmin.t('In einer Reihe mit dem vorigen Block ({w})', { w: CMSAdmin.t(ROW_LABEL[x.row] || x.row) });
      else delete x.el.dataset.rowLabel;
    });
  }
}

/** Pfeile am Anfang/Ende deaktivieren */
function refreshMoveButtons() {
  layoutRows();
  const els = blockEls();
  els.forEach((b, i) => {
    const sr = b.querySelector('.cms-block__bar')?.shadowRoot;
    const up = sr && $('[data-move="up"]', sr), down = sr && $('[data-move="down"]', sr);   // nur falls ein Kit eigene Pfeile ergänzt
    if (up) up.disabled = i === 0;
    if (down) down.disabled = i === els.length - 1;
  });
}

/**
 * Wert eines direkt bearbeitbaren Felds. Redaktionsnotizen erscheinen als Hinweis „Notiz: …“ (Core\EditorNotes::decorate,
 * .cms-note, nicht bearbeitbar) – beim Auslesen werden sie wieder zu „[# … #]“, damit sie gespeichert bleiben.
 */
function fieldValue(n, rich) {
  if (!n.querySelector('[data-cms-note]')) return rich ? n.innerHTML : n.textContent;
  const c = n.cloneNode(true);
  c.querySelectorAll('[data-cms-note]').forEach(x => x.replaceWith(d.createTextNode(`[# ${x.dataset.cmsNote} #]`)));
  return rich ? c.innerHTML : c.textContent;
}

// Werkzeugleiste (_bar.js): EIN Status-Chip, „Gespeichert ✓“, Live-Region, Abbrechen
const Bar = CMSAdmin.bar;
const BT = k => Bar?.texts?.[k] || { close: 'Schließen', cancel: 'Abbrechen', done: 'Fertig' }[k] || k;
const markDirty = () => { const was = dirty; dirty = true; if (!was) Bar?.state('dirty'); requestAnimationFrame(blankState); History?.touch(); };

/*
 * Leere Seite: Editor.js braucht immer einen Block und legt dafür einen leeren Standardblock (Text) an – auch nach dem
 * Löschen des letzten Blocks. Ist das der einzige Block und noch leer, zeigen wir ihn als Platzhalter „Leere Seite“,
 * ersetzen ihn beim ersten „+ Block einfügen“ und speichern ihn nicht mit.
 */
const DEFAULT_TYPE = cfg.blocks.richtext ? 'richtext' : Object.keys(cfg.blocks)[0];
const OPTION_TYPES = ['select', 'bool', 'number', 'color', 'heading', 'icon'];
const hasValue = v => Array.isArray(v) ? v.length > 0 : (v && typeof v === 'object' ? Object.values(v).some(hasValue)
  : String(v ?? '').replace(/<[^>]*>|&nbsp;/g, '').trim() !== '');
const hasContent = (def, data) => (def.fields || []).some(f => f.name && !OPTION_TYPES.includes(f.type) && hasValue(data?.[f.name]));
function blankState() {
  if (!editor?.blocks) return false;
  const els = blockEls();
  const only = els.length === 1 ? tools.get(editor.blocks.getBlockByIndex(0)?.id) : null;
  const blank = !!only && only.type === DEFAULT_TYPE && !hasContent(only.def, only.data);
  els.forEach(b => b.querySelector('.cms-block')?.classList.toggle('is-blank', blank));
  return blank;
}

async function api(url, body) {
  const r = await fetch(url, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': cfg.csrf },
    body: JSON.stringify(body),
  });
  if (r.status === 401) { alert('Ihre Sitzung ist abgelaufen. Bitte in einem neuen Tab anmelden und erneut speichern.'); throw new Error('401'); }
  const json = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(json.error || 'Fehler ' + r.status);
  return json;
}

const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

function setPath(obj, path, value) {
  const keys = path.split('.');
  let o = obj;
  keys.slice(0, -1).forEach((k, i) => {
    if (o[k] == null) o[k] = /^\d+$/.test(keys[i + 1]) ? [] : {};
    o = o[k];
  });
  o[keys.at(-1)] = value;
}

/** Formular → verschachteltes Objekt; Repeater-Einträge in DOM-Reihenfolge als Arrays */
function formToObject(form, prefix = 'f') {
  const root = new Map();
  for (const [name, value] of new FormData(form)) {
    if (!name.startsWith(prefix + '[')) continue;
    const keys = name.slice(prefix.length).match(/\[([^\]]*)\]/g).map(k => k.slice(1, -1));
    let node = root;
    keys.forEach((k, i) => {
      const last = i === keys.length - 1;
      // name[] = Mehrfachauswahl (Checkbox-Listen): alle Werte sammeln, leere Platzhalter ignorieren
      if (last && k === '') { const arr = node.get('\u0000list') || []; if (value !== '') arr.push(value); node.set('\u0000list', arr); return; }
      if (i === keys.length - 2 && keys[keys.length - 1] === '') {
        if (!(node.get(k) instanceof Map)) node.set(k, new Map());
        node = node.get(k); return;
      }
      if (last) node.set(k, value); // bei Checkbox gewinnt der letzte Wert (hidden 0 → 1)
      else { if (!(node.get(k) instanceof Map)) node.set(k, new Map()); node = node.get(k); }
    });
  }
  const conv = m => {
    if (m.has('\u0000list')) return m.get('\u0000list');
    const entries = [...m.entries()].map(([k, v]) => [k, v instanceof Map ? conv(v) : v]);
    const isList = entries.length > 0 && entries.every(([k]) => /^(\d+|n\d+)$/.test(k));
    return isList ? entries.map(([, v]) => v) : Object.fromEntries(entries);
  };
  const obj = conv(root);
  // leere Repeater (keine Einträge) erkennen
  $$('.rep', form).forEach(rep => {
    const m = rep.dataset.name.match(/^f\[([^\]]+)\]$/);
    if (m && !$('.rep-item', rep)) obj[m[1]] = [];
  });
  return obj;
}

// ------------------------------------------------------------------ Schwebende Formatierungsleiste (Inline-Bearbeitung)
const InlineBar = (() => {
  const el = d.createElement('div');
  el.className = 'cms-inline-bar rte-bar';
  el.setAttribute('role', 'toolbar');
  el.setAttribute('aria-label', 'Formatierung');
  el.hidden = true;
  layerBox().append(el);   // Shadow-DOM-Ebene (editor.shadow.css)
  let target = null, mode = '', timer;
  const place = () => {
    if (!target || el.hidden) return;
    const r = target.getBoundingClientRect(), h = el.offsetHeight, w = el.offsetWidth;
    const minTop = (d.querySelector('.cms-bar-host')?.getBoundingClientRect().bottom || 0) + 10;
    let top = r.top - h - 10;
    if (top < minTop) top = Math.min(r.bottom + 10, innerHeight - h - 10);   // unter dem Text, wenn oben kein Platz
    el.style.top = Math.max(minTop, top) + 'px';
    el.style.left = Math.max(8, Math.min(r.left, innerWidth - w - 8)) + 'px';
    BarPlace.soon();   // Block-Leiste weicht der Formatierungsleiste aus
  };
  // Klicks, Menüs (Stil, Farbe, ⋯), Tastatur und Zustand: CMSAdmin.Rich.mount (_rte.js)
  CMSAdmin.Rich.mount(el, () => target, () => place());
  el.addEventListener('focusout', () => api_.hideSoon());   // Tastatur: Leiste verlassen
  addEventListener('scroll', place, { passive: true });
  addEventListener('resize', place);
  const api_ = {
    show(n, m) {
      clearTimeout(timer);
      if (mode !== m) { el.innerHTML = CMSAdmin.Rich.barHtml(m); mode = m; }
      target = n; el.hidden = false; place();
      CMSAdmin.Rich.state(n, el);
    },
    hideSoon() {
      clearTimeout(timer);
      timer = setTimeout(() => { if (!S.openDialog() && !(target && target.contains(d.activeElement)) && !el.contains(S.deepActive())) this.hide(); }, 200);
    },
    hide() { el.hidden = true; target = null; },
  };
  return api_;
})();

// ------------------------------------------------------------------ Block auswählen (Klick/Tipp, Fokus)
/**
 * Ein Klick in einen Block wählt ihn nur aus (.is-selected): Leiste und „+ Block einfügen“ bleiben sichtbar – auf
 * Touch-Geräten ohne Hover der einzige Weg dorthin. Die Seitenleiste öffnet ausschließlich „Bearbeiten“.
 */
function selectBlock(el) {
  const was = el?.classList.contains('is-selected');
  $$('.cms-block.is-selected', holder()).forEach(b => { if (b !== el) b.classList.remove('is-selected'); });
  el?.classList.add('is-selected');
  if (el) BarPlace.soon();
  // Ereignis für Werkzeuge/Erweiterungen (CMSAdmin.events): cms:block-select { id, type, label, el }
  if (el && !was) {
    const tool = toolFor(el);
    CMSAdmin.events?.emit('cms:block-select', { kind: 'page', id: tool?.blockId || el.closest('.ce-block')?.dataset.id || '', type: tool?.type || '', label: tool?.def?.label || '', el });
  }
}
// Ereignisse aus Schatten-Bäumen (Block-Leiste, „+“) kommen hier mit dem Host als target an; Klicks in Werkzeugleiste,
// Ebene (Seitenleiste, Dialoge) und Eintrags-Seitenleiste lassen die Auswahl stehen
d.addEventListener('pointerdown', e => {
  const t = e.target;
  if (!(t instanceof Element) || t.closest('.cms-bar-host,#cms-layer-host,#cms-epanel-host')) return;
  selectBlock(t.closest('.cms-block'));
}, true);
d.addEventListener('focusin', e => { const b = e.target.closest?.('.cms-block'); if (b) selectBlock(b); });

// ------------------------------------------------------------------ Lage der Block-Leiste: nie über Inhalten
/**
 * Die Block-Leiste (Name, ↑ ↓ ▾, „Bearbeiten“) darf keine Inhalte verdecken – vor allem nicht die Zeile, in der gerade
 * geschrieben wird. Je sichtbarer Leiste (Hover, Auswahl, Fokus) werden Lagen der Reihe nach geprüft:
 *   oben rechts im Block → auf der Naht zum Block darüber → ganz über der Oberkante → dasselbe links →
 *   (Block oben aus dem Bild gescrollt) unter der Werkzeugleiste bzw. dem klebenden Kopf des Kits (topInset).
 * Jede Lage erst in voller Breite, dann schmal (nur Symbol), dann ganz knapp (Symbol + „Bearbeiten“).
 * Hindernisse: Texte, bearbeitbare Felder und Bedienelemente des Blocks und seiner Nachbarn, die Formatierungsleiste
 * (hat Vorrang), „+ Block einfügen“, Stift „Eintrag bearbeiten“, Knöpfe am Bild, Editor.js-Griff, Kopf des Kits.
 * Bilder sind weiche Hindernisse (lieber daneben, notfalls darüber). Ist nirgends Platz, weicht die Leiste beim
 * Schreiben ganz aus (.is-yield) und kommt bei Mausbewegung bzw. nach dem Verlassen des Felds zurück.
 */
const BarPlace = (() => {
  const LIVE = '.cms-block:is(:hover,.is-selected,.is-active,:focus-within)';
  const HARD = '[data-edit],input,select,textarea,button,.btn,[role=button],.cms-lay-bar,.cms-lay-add';
  const SOFT = 'img,video,iframe,canvas,picture,svg:not(button svg):not(a svg)';
  let raf = 0;
  const R = r => ({ l: r.left, t: r.top, r: r.right, b: r.bottom });
  const area = (a, b) => Math.max(0, Math.min(a.r, b.r) - Math.max(a.l, b.l)) * Math.max(0, Math.min(a.b, b.b) - Math.max(a.t, b.t));
  const less = (a, b) => { const i = a.findIndex((v, k) => v !== b[k]); return i >= 0 && a[i] < b[i]; };
  const off = el => !el || el.closest('.is-collapsed,.is-compact') || !el.isConnected;
  const visibleEl = el => { if (!el) return false; const s = getComputedStyle(el); return s.display !== 'none' && s.visibility !== 'hidden' && +s.opacity > 0; };

  /** Hindernisse im Band [top, bottom] (Viewport) – Text zeilenweise, Felder/Knöpfe als Kasten, Bilder weich */
  function obstacles(blk, band) {
    const hard = [], soft = [];
    const inBand = r => r.bottom > band.t && r.top < band.b && r.width > 0 && r.height > 0;
    const ce = blk.closest('.ce-block');
    const scopes = [ce?.previousElementSibling, ce, ce?.nextElementSibling].map(c => c?.querySelector('.cms-block__preview')).filter(Boolean);
    for (const pv of scopes) {
      const tw = d.createTreeWalker(pv, NodeFilter.SHOW_TEXT);
      const rg = d.createRange();
      for (let n; (n = tw.nextNode());) {
        if (!n.data.trim()) continue;
        const pe = n.parentElement;
        if (!pe || !inBand(pe.getBoundingClientRect())) continue;
        rg.selectNodeContents(n);
        for (const r of rg.getClientRects()) if (inBand(r)) hard.push(R(r));
      }
      for (const x of pv.querySelectorAll(HARD)) { const r = x.getBoundingClientRect(); if (inBand(r)) hard.push(R(r)); }
      for (const x of pv.querySelectorAll(SOFT)) { const r = x.getBoundingClientRect(); if (inBand(r) && r.width * r.height > 900) soft.push(R(r)); }
      for (const x of pv.querySelectorAll('.cms-entry-pencil,.cms-target-edit')) hard.push(R(x.getBoundingClientRect()));
    }
    // Bedienelemente: Formatierungsleiste (Vorrang), „+ Block einfügen“ dieses und des vorigen Blocks, Knöpfe am Bild,
    // Leisten der Nachbarblöcke, Griff von Editor.js, Kopf des Kits
    const layer = S.layerBox();
    const extra = [layer.querySelector('.cms-inline-bar:not([hidden])'), layer.querySelector('.cms-imgtools:not([hidden])'),
      ...[ce?.previousElementSibling, ce].map(c => c?.querySelector('.cms-block__add')?.shadowRoot?.querySelector('button')),
      ...[ce?.previousElementSibling, ce?.nextElementSibling].map(c => c?.querySelector(':scope .cms-block:is(:hover,.is-selected,.is-active,:focus-within)>.cms-block__bar')),
      holder().querySelector('.ce-toolbar--opened .ce-toolbar__actions'),
      ...d.querySelectorAll('[data-cms-header]')];
    for (const x of extra) if (x && visibleEl(x) && getComputedStyle(x).visibility !== 'hidden') { const r = x.getBoundingClientRect(); if (inBand(r)) hard.push(R(r)); }
    return { hard, soft };
  }

  function place(blk) {
    const host = blk.querySelector(':scope>.cms-block__bar');
    if (!host) return;
    if (off(blk)) { host.removeAttribute('style'); host.classList.remove('is-slim', 'is-mini', 'is-yield', 'is-crowded'); return; }
    const br = blk.getBoundingClientRect();
    const inset = CMSAdmin.shadow.topInset ? CMSAdmin.shadow.topInset() : 0;
    const vw = d.documentElement.clientWidth;
    const levels = [[], ['is-slim'], ['is-slim', 'is-mini']];
    const sizes = levels.map(cls => {
      host.classList.remove('is-slim', 'is-mini'); host.classList.add(...cls);
      return [host.offsetWidth, host.offsetHeight];
    });
    const hMax = Math.max(...sizes.map(s => s[1]));
    const band = { t: Math.min(br.top - hMax - 12, inset), b: Math.max(br.top + hMax + 16, inset + hMax + 16) };
    const { hard, soft } = obstacles(blk, band);
    const typing = blk.contains(d.activeElement) && !!d.activeElement.closest?.('[data-edit]');
    let best = null;
    levels.forEach((cls, li) => {
      const [w, h] = sizes[li];
      const tops = [8, -h / 2, -h - 6];
      if (br.top + 8 < inset + 4 && br.bottom > inset + h + 24) tops.push(inset + 8 - br.top);   // Block oben aus dem Bild
      const sides = [['right', br.right - 10 - w], ['left', br.left + 10]];
      sides.forEach(([side, x], si) => tops.forEach((top, ti) => {
        const c = { l: x, r: x + w, t: br.top + top, b: br.top + top + h };
        if (c.l < 4 || c.r > vw - 4 || c.t < inset + 2) return;
        const hit = hard.reduce((s, o) => s + area(c, o), 0);
        const img = soft.reduce((s, o) => s + area(c, o), 0);
        // Rangfolge: frei vor verdeckt, Stufe (voll → schmal → knapp), keine Bildfläche, Lage
        const score = [hit > 0 ? 1 : 0, hit, li, img > 0 ? 1 : 0, si * 10 + ti];
        if (!best || less(score, best.score)) best = { score, cls, side, top };
      }));
    });
    host.classList.remove('is-slim', 'is-mini');
    if (!best) { host.removeAttribute('style'); return; }
    host.classList.add(...best.cls);
    host.style.top = best.top + 'px';
    if (best.side === 'right') { host.style.right = '10px'; host.style.left = 'auto'; }
    else { host.style.left = '10px'; host.style.right = 'auto'; }
    const crowded = best.score[0] === 1;
    host.classList.toggle('is-crowded', crowded);
    // Kein freier Platz: beim Schreiben ausweichen, sonst (Maus) die am wenigsten störende Lage
    if (!crowded || !typing) host.classList.remove('is-yield');
    else if (!host.dataset.peek) host.classList.add('is-yield');
  }

  const run = () => { raf = 0; for (const b of $$(LIVE, holder())) place(b); };
  const soon = () => { if (!raf) raf = requestAnimationFrame(run); };
  addEventListener('scroll', soon, { passive: true });
  addEventListener('resize', soon);
  let hovered = null;
  d.addEventListener('pointerover', e => { const b = e.target.closest?.('.cms-block'); if (b && b !== hovered) { hovered = b; soon(); } }, true);
  d.addEventListener('focusout', soon);
  d.addEventListener('input', e => { if (e.target.closest?.('.cms-block')) soon(); });
  // Beim Schreiben (ohne freien Platz) weicht die Leiste aus; Mausbewegung holt sie zurück, Tippen blendet wieder aus
  d.addEventListener('keydown', e => {
    const b = e.target.closest?.('.cms-block');
    const host = b?.querySelector(':scope>.cms-block__bar');
    if (host && e.target.closest('[data-edit]') && host.classList.contains('is-crowded')) { delete host.dataset.peek; host.classList.add('is-yield'); }
  });
  d.addEventListener('pointermove', e => {
    for (const host of $$('.cms-block__bar.is-yield', holder())) { host.dataset.peek = '1'; host.classList.remove('is-yield'); }
  }, { passive: true });
  return { soon, place };
})();
// Breite der Seite ändert sich (Seitenleiste breiter/schmaler, Fenster): Leisten neu einpassen (schmal = nur Symbol, knapp = ohne Pfeile)
new ResizeObserver(() => BarPlace.soon()).observe(d.querySelector('.cms-cq-site') || d.body);

// ------------------------------------------------------------------ Blöcke kopieren / duplizieren
/*
 * „Kopieren“ legt Typ, Inhalt und Abschnitts-Einstellungen in localStorage ab (gilt für alle Seiten dieser Website, auch nach dem
 * Neuladen); „+ Block einfügen“ bietet ihn dann oben an. „Duplizieren“ setzt eine Kopie direkt darunter. Neue IDs – auch für
 * Blöcke in Spalten des Blocks „Layout“ –, die Abschnitts-Einstellungen gehen über initialTunes an den neuen Block.
 */
const CLIP_KEY = 'cms-block-clip';
const newBlockId = () => Math.random().toString(36).slice(2, 12).padEnd(10, '0');
const freshIds = data => {
  const c = structuredClone(data || {});
  (Array.isArray(c.columns) ? c.columns : []).forEach(col => (Array.isArray(col?.blocks) ? col.blocks : []).forEach(b => { b.id = newBlockId(); if (b.data) b.data = freshIds(b.data); }));
  return c;
};
const clipboard = () => { const c = store.get(CLIP_KEY, null); return c && cfg.blocks[c.type] ? c : null; };
function insertCopy(src, index) {
  if (!src || !cfg.blocks[src.type]) return false;
  const id = newBlockId();
  initialTunes[id] = structuredClone(src.tunes || {});
  editor.blocks.insert(src.type, freshIds(src.data), undefined, index, false, false, id);
  markDirty();
  requestAnimationFrame(refreshMoveButtons);
  return true;
}
const editorStatus = msg => { const st = S.ui('[data-editor-status]'); if (st) st.textContent = msg; };

// ------------------------------------------------------------------ „+ Block einfügen“ unter einem Block: Auswahl der Blocktypen
/**
 * Gleiche Blocktypen wie das „+“ von Editor.js (einfügbare Typen des Kits, gleiche Reihenfolge), eingefügt über
 * editor.blocks.insert() direkt nach dem Block. Tastatur: Tippen filtert, ↑/↓ wählen, Enter fügt ein, Esc schließt.
 */
const BlockPicker = (() => {
  const T = CMSAdmin.t;
  const el = d.createElement('div');
  el.className = 'cms-addpop'; el.hidden = true;
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-label', T('Block einfügen'));
  el.innerHTML = `<input type="search" class="cms-addpop__q" placeholder="${CMSAdmin.esc(T('Blocktyp suchen …'))}" aria-label="${CMSAdmin.esc(T('Blocktyp suchen'))}" autocomplete="off">
    <div class="cms-addpop__list" role="listbox" aria-label="${CMSAdmin.esc(T('Blocktypen'))}"></div>
    <p class="cms-addpop__none" hidden>${CMSAdmin.esc(T('Nichts gefunden.'))}</p>`;
  layerBox().append(el);
  const q = $('.cms-addpop__q', el), list = $('.cms-addpop__list', el), none = $('.cms-addpop__none', el);
  const types = Object.entries(cfg.blocks).filter(([, def]) => def.insertable !== false);
  list.innerHTML = types.map(([type, def]) => `<button type="button" class="cms-addpop__item" role="option" data-type="${CMSAdmin.esc(type)}" tabindex="-1"><span class="cms-addpop__ico" aria-hidden="true">${blockIcon(def)}</span><span>${CMSAdmin.esc(def.label)}</span></button>`).join('');
  // Kopierter Block (oben, nur außerhalb von Spalten)
  const pasteBtn = d.createElement('button');
  pasteBtn.type = 'button'; pasteBtn.className = 'cms-addpop__item cms-addpop__item--paste'; pasteBtn.setAttribute('role', 'option');
  pasteBtn.tabIndex = -1; pasteBtn.dataset.type = '__paste'; pasteBtn.hidden = true;
  list.prepend(pasteBtn);
  const syncPaste = () => {
    const c = column == null ? clipboard() : null;
    pasteBtn.dataset.off = c ? '' : '1';
    if (c) pasteBtn.innerHTML = `<span class="cms-addpop__ico" aria-hidden="true">${blockIcon(cfg.blocks[c.type])}</span><span>${CMSAdmin.esc(T('Kopierten Block einfügen: {label}', { label: cfg.blocks[c.type].label }))}</span>`;
  };
  const items = () => $$('.cms-addpop__item:not([hidden])', list);
  let tool = null, opener = null, only = null, column = null;   // only/column: „+ Block in diese Spalte“ (Layout)
  const place = () => {
    if (el.hidden || !opener) return;
    const r = opener.getBoundingClientRect(), h = el.offsetHeight, w = el.offsetWidth;
    const top = r.bottom + 8 + h <= innerHeight - 8 ? r.bottom + 8 : Math.max(8, r.top - 8 - h);
    el.style.top = top + 'px';
    el.style.left = Math.max(8, Math.min(r.left + r.width / 2 - w / 2, innerWidth - w - 8)) + 'px';
  };
  const mark = btn => items().forEach(b => b.setAttribute('aria-selected', b === btn ? 'true' : 'false'));
  const filter = () => {
    const v = q.value.trim().toLowerCase();
    $$('.cms-addpop__item', list).forEach(b => { b.hidden = b.dataset.off === '1' || (b === pasteBtn ? !!v : (only && !only.has(b.dataset.type)) || (!!v && !b.textContent.toLowerCase().includes(v) && !b.dataset.type.includes(v))); });
    none.hidden = items().length > 0;
    mark(items()[0]);
    place();
  };
  const close = (back = true) => {
    if (el.hidden) return;
    el.hidden = true;
    if (back) opener?.focus({ preventScroll: true });
    opener = null; tool = null; only = null; column = null;
  };
  const insert = type => {
    const t = tool, col = column;
    close(false);
    if (col != null && type && t?.addChild) { t.addChild(col, type); return; }
    const idx = blockEls().indexOf(t?.el.closest('.ce-block'));
    if (!type || idx < 0) return;
    const wasBlank = blankState();
    if (type === '__paste') {
      const c = clipboard();
      if (!c || !insertCopy(c, idx + 1)) return;
      if (wasBlank) editor.blocks.delete(idx);
      editorStatus(T('Block „{label}“ eingefügt – noch nicht gespeichert.', { label: cfg.blocks[c.type].label }));
      return;
    }
    editor.blocks.insert(type, {}, undefined, idx + 1, false);
    if (wasBlank) editor.blocks.delete(idx);   // Platzhalter „Leere Seite“ ersetzen
    markDirty();
    requestAnimationFrame(refreshMoveButtons);
    const st = S.ui('[data-editor-status]');
    if (st) st.textContent = T('Block „{label}“ eingefügt – noch nicht gespeichert.', { label: cfg.blocks[type]?.label || type });
  };
  list.addEventListener('click', e => { const b = e.target.closest('.cms-addpop__item'); if (b) insert(b.dataset.type); });
  q.addEventListener('input', filter);
  el.addEventListener('keydown', e => {
    e.stopPropagation();
    const all = items(), cur = all.findIndex(b => b.getAttribute('aria-selected') === 'true');
    if (e.key === 'Escape') { e.preventDefault(); close(); }
    else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      const n = all[(cur + (e.key === 'ArrowDown' ? 1 : -1) + all.length) % all.length];
      if (n) { mark(n); n.scrollIntoView({ block: 'nearest' }); }
    } else if (e.key === 'Enter') { e.preventDefault(); insert(all[Math.max(0, cur)]?.dataset.type); }
    else if (e.key === 'Tab') close(false);
  });
  // Klick außerhalb schließt
  d.addEventListener('pointerdown', e => { if (!el.hidden && !e.composedPath().includes(el) && !e.composedPath().includes(opener)) close(false); }, true);
  addEventListener('resize', place);
  addEventListener('scroll', place, { passive: true });
  return {
    /** opts.column: Spalte eines Layouts – nur verschachtelbare Blöcke (cfg.blocks[typ].nestable) */
    open(btn, t, opts = {}) {
      if (!el.hidden && opener === btn) { close(); return; }
      opener = btn; tool = t;
      column = opts.column ?? null;
      only = column != null ? new Set(types.filter(([, def]) => def.nestable).map(([type]) => type)) : null;
      el.setAttribute('aria-label', column != null ? T('Block in diese Spalte einfügen') : T('Block einfügen'));
      q.value = ''; syncPaste(); filter();
      el.hidden = false; place();
      q.focus({ preventScroll: true });
    },
    close,
  };
})();

// ------------------------------------------------------------------ Block-Tune „Abschnitt“
class SectionTune {
  static get isTune() { return true; }
  constructor({ data, block }) {
    this.blockId = block?.id;
    const bg = cfg.blocks[block?.name]?.background || 'white';
    this.data = { ...TUNE_DEFAULTS, background: bg, ...(initialTunes[this.blockId] || {}), ...(data || {}) };
    if (this.blockId) tunes.set(this.blockId, this);
  }
  render() {
    return {
      icon: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 10h18"/></svg>',
      title: 'Abschnitt & Navigation …',
      onActivate: () => tools.get(this.blockId)?.openDrawer(true),
    };
  }
  save() { return this.data; }
}

// ------------------------------------------------------------------ Block-Menü: alle Aktionen eines Blocks an einem Ort
/*
 * Geöffnet über „⋯“ in der Blockleiste oder einen Klick auf den Griff ⠿ (Ziehen am Griff verschiebt weiterhin).
 * Gleicher Stil wie „+ Block einfügen“. Reihenfolge: Verschieben · Duplizieren/Kopieren/Einfügen · Einklappen/Abschnitt · Löschen.
 */
const BlockMenu = (() => {
  const T = CMSAdmin.t;
  const el = d.createElement('div');
  el.className = 'cms-addpop cms-blockmenu'; el.hidden = true;
  el.setAttribute('role', 'menu');
  layerBox().append(el);
  let tool = null, opener = null, armed = false;
  const place = () => {
    if (el.hidden || !opener) return;
    const r = opener.getBoundingClientRect(), h = el.offsetHeight, w = el.offsetWidth;
    el.style.top = (r.bottom + 6 + h <= innerHeight - 8 ? r.bottom + 6 : Math.max(8, r.top - 6 - h)) + 'px';
    el.style.left = Math.max(8, Math.min(r.left, innerWidth - w - 8)) + 'px';
  };
  const close = (back = true) => { if (el.hidden) return; el.hidden = true; if (back) opener?.focus?.({ preventScroll: true }); tool = null; opener = null; armed = false; };
  const index = () => blockEls().indexOf(tool?.el.closest('.ce-block'));
  const item = (key, ico, label, opts = {}) => `<button type="button" class="cms-addpop__item${opts.danger ? ' is-danger' : ''}" role="menuitem" data-act="${key}"${opts.disabled ? ' disabled aria-disabled="true"' : ''}><span class="cms-addpop__ico" aria-hidden="true">${ico}</span><span>${CMSAdmin.esc(label)}</span>${opts.kbd ? `<kbd>${opts.kbd}</kbd>` : ''}</button>`;
  const render = () => {
    const i = index(), n = blockEls().length, c = clipboard(), folded = tool.el.classList.contains('is-collapsed');
    el.innerHTML = `<p class="cms-blockmenu__title">${CMSAdmin.esc(tool.def.label)}</p>`
      + item('up', '↑', T('Nach oben'), { disabled: i <= 0, kbd: 'Alt ↑' })
      + item('down', '↓', T('Nach unten'), { disabled: i < 0 || i >= n - 1, kbd: 'Alt ↓' })
      + '<hr>'
      + item('dup', '⧉', T('Duplizieren'))
      + item('copy', '⎘', T('Kopieren'))
      + (c ? item('paste', '↧', T('Einfügen darunter: {label}', { label: cfg.blocks[c.type].label })) : '')
      + '<hr>'
      + item('fold', folded ? '▸' : '▾', folded ? T('Ausklappen') : T('Einklappen'))
      + item('section', '▭', T('Abschnitt & Navigation …'))
      + '<hr>'
      + item('delete', '✕', armed ? T('Wirklich löschen?') : T('Löschen'), { danger: true });
  };
  const run = act => {
    const t = tool, i = index();
    if (!t) return;
    if (act === 'delete') {
      if (!armed) { armed = true; render(); el.querySelector('[data-act="delete"]')?.focus(); return; }
      close(false); editor.blocks.delete(i); markDirty(); requestAnimationFrame(refreshMoveButtons);
      editorStatus(T('Block gelöscht – noch nicht gespeichert. Wiederherstellen über „Versionen“.'));
      return;
    }
    close(act !== 'section');
    if (act === 'up') t.move(-1);
    else if (act === 'down') t.move(1);
    else if (act === 'dup') t.duplicate();
    else if (act === 'copy') t.copy();
    else if (act === 'paste') { const c = clipboard(); if (c && insertCopy(c, i + 1)) editorStatus(T('Block „{label}“ eingefügt – noch nicht gespeichert.', { label: cfg.blocks[c.type].label })); }
    else if (act === 'fold') t.toggleCollapse();
    else if (act === 'section') t.openDrawer(true);
  };
  el.addEventListener('click', e => { const b = e.target.closest('[data-act]'); if (b && !b.disabled) run(b.dataset.act); });
  el.addEventListener('keydown', e => {
    e.stopPropagation();
    const items = [...el.querySelectorAll('[data-act]:not([disabled])')], cur = items.indexOf(d.activeElement) >= 0 ? items.indexOf(d.activeElement) : items.indexOf(e.composedPath()[0]);
    if (e.key === 'Escape') { e.preventDefault(); close(); }
    else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); items[(cur + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length]?.focus(); }
    else if (e.key === 'Tab') close(false);
  });
  d.addEventListener('pointerdown', e => { if (!el.hidden && !e.composedPath().includes(el) && !e.composedPath().includes(opener)) close(false); }, true);
  addEventListener('resize', place);
  addEventListener('scroll', place, { passive: true });
  return {
    open(btn, t) {
      if (!el.hidden && tool === t) { close(); return; }
      opener = btn; tool = t; armed = false;
      render(); el.hidden = false; place();
      el.querySelector('[data-act]:not([disabled])')?.focus({ preventScroll: true });
    },
    close,
  };
})();

// Griff ⠿ von Editor.js: Klick öffnet unser Block-Menü (statt des Editor.js-Menüs), Ziehen verschiebt weiterhin
let hoveredBlockEl = null;
holder()?.addEventListener('mouseover', e => { const b = e.target.closest?.('.ce-block'); if (b) hoveredBlockEl = b; });
holder()?.addEventListener('focusin', e => { const b = e.target.closest?.('.ce-block'); if (b) hoveredBlockEl = b; });
holder()?.addEventListener('mousedown', e => { if (e.target.closest?.('.ce-toolbar__settings-btn')) e.stopPropagation(); }, true);
holder()?.addEventListener('click', e => {
  const grip = e.target.closest?.('.ce-toolbar__settings-btn');
  if (!grip) return;
  e.preventDefault(); e.stopPropagation();
  const t = hoveredBlockEl && tools.get(hoveredBlockEl.dataset.id);
  if (t) BlockMenu.open(grip, t);
}, true);

// ------------------------------------------------------------------ Generisches Block-Tool
function makeTool(type, def) {
  return class BlockTool {
    // Für dieses Projekt gesperrte Blocktypen: bestehende bleiben bearbeitbar, neue lassen sich nicht einfügen
    static get toolbox() { return def.insertable === false ? undefined : { title: def.label, icon: `<span class="cms-tool-icon">${blockIcon(def)}</span>` }; }
    static get enableLineBreaks() { return true; }
    static get isReadOnlySupported() { return true; }

    constructor({ data, block }) {
      this.type = type; this.def = def;
      this.blockId = block.id;
      this.data = data && Object.keys(data).length ? structuredClone(data) : {};
      this.el = null;
      this.refresh = debounce(() => this.loadPreview(), 350);
      tools.set(this.blockId, this);
    }

    get tuneData() { return tunes.get(this.blockId)?.data || { ...TUNE_DEFAULTS, background: def.background || 'white' }; }

    /** Kopie direkt unter diesem Block */
    duplicate() {
      const idx = blockEls().indexOf(this.el?.closest('.ce-block'));
      if (idx < 0) return;
      insertCopy({ type, data: this.data, tunes: this.tuneData }, idx + 1);
      editorStatus(CMSAdmin.t('Block „{label}“ dupliziert – noch nicht gespeichert.', { label: def.label }));
    }

    /** In die Block-Zwischenablage (localStorage) – einfügen über „+ Block einfügen“, auch auf anderen Seiten */
    copy() {
      store.set(CLIP_KEY, { type, data: structuredClone(this.data), tunes: structuredClone(this.tuneData), at: Date.now() });
      editorStatus(CMSAdmin.t('Block „{label}“ kopiert – über „+ Block einfügen“ auf dieser oder einer anderen Seite einsetzen.', { label: def.label }));
    }

    render() {
      const el = d.createElement('div');
      el.className = 'cms-block';
      // Block-Leiste: eigenes Shadow DOM (Knöpfe unabhängig von Theme-Regeln für button, font, line-height …)
      el.innerHTML = '<div class="cms-block__bar" contenteditable="false"></div><div class="cms-block__preview"></div>'
        + `<div class="cms-block__blank" contenteditable="false"><strong>${CMSAdmin.esc(CMSAdmin.t('Leere Seite'))}</strong> ${CMSAdmin.esc(CMSAdmin.t('Fügen Sie mit „+ Block einfügen“ den ersten Block hinzu.'))}</div>`;
      const barEl = el.firstElementChild;
      const sr = this.bar = S.shadowFor(barEl, `
          <span class="cms-block__swatch" aria-hidden="true"></span>
          <span class="cms-block__label" draggable="true" data-drag title="${CMSAdmin.esc(CMSAdmin.t('Ziehen zum Verschieben'))} – ${CMSAdmin.esc(def.label)}"><span class="cms-block__grip" aria-hidden="true">⠿</span><span aria-hidden="true">${blockIcon(def)}</span><span class="cms-block__name"> ${CMSAdmin.esc(def.label)}</span></span>
          <span class="cms-block__summary"></span>
          <span class="cms-block__flags"></span>
          <span class="cms-block__hint" hidden></span>
          ${def.formfields ? `<button type="button" class="cms-block__fields" hidden>${CMSAdmin.esc(CMSAdmin.t('Felder'))}<span class="cms-block__fields-more"> ${CMSAdmin.esc(CMSAdmin.t('bearbeiten'))}</span></button>` : ''}
          <span class="cms-block__tools">
            <button type="button" class="cms-iconbtn" data-move="up" aria-label="${CMSAdmin.esc(CMSAdmin.t('Block nach oben'))}" title="${CMSAdmin.esc(CMSAdmin.t('Nach oben'))} (Alt+↑)">↑</button>
            <button type="button" class="cms-iconbtn" data-move="down" aria-label="${CMSAdmin.esc(CMSAdmin.t('Block nach unten'))}" title="${CMSAdmin.esc(CMSAdmin.t('Nach unten'))} (Alt+↓)">↓</button>
          </span>
          <button type="button" class="cms-block__edit">Bearbeiten</button>
          <button type="button" class="cms-iconbtn cms-block__more" data-blockmenu aria-haspopup="menu" aria-label="${CMSAdmin.esc(CMSAdmin.t('Weitere Aktionen'))}" title="${CMSAdmin.esc(CMSAdmin.t('Weitere Aktionen'))}">⋯</button>`);
      this.el = el;
      // „+ Block einfügen“ an der Unterkante (eigenes Shadow DOM): fügt nach diesem Block ein – beim letzten am Seitenende
      const addEl = d.createElement('div');
      addEl.className = 'cms-block__add'; addEl.contentEditable = 'false';
      el.append(addEl);
      const addSr = S.shadowFor(addEl, `<button type="button" class="cms-addbtn" aria-haspopup="dialog" title="${CMSAdmin.esc(CMSAdmin.t('Neuen Block unter diesem Block einfügen'))}"><span aria-hidden="true">+</span> ${CMSAdmin.esc(CMSAdmin.t('Block einfügen'))}</button>`);
      const addBtn = addSr.querySelector('button');
      addBtn.setAttribute('aria-label', CMSAdmin.t('Block einfügen nach „{label}“', { label: def.label }));
      addBtn.addEventListener('click', e => { e.stopPropagation(); BlockPicker.open(addBtn, this); });
      addEl.addEventListener('keydown', e => e.stopPropagation());
      sr.querySelector('.cms-block__edit').addEventListener('click', e => { e.stopPropagation(); this.openDrawer(); });
      // Formular-Blöcke: Felder der gewählten Tabelle direkt bearbeiten (nur mit Recht „Tabellen und Felder ändern“, cfg.formFields)
      sr.querySelector('.cms-block__fields')?.addEventListener('click', e => { e.stopPropagation(); this.openFormFields(e.currentTarget); });
      // Eingeklappte Zeile: Klick auf den Titel klappt auf
      sr.querySelector('[data-move="up"]').addEventListener('click', e => { e.stopPropagation(); this.move(-1); });
      sr.querySelector('[data-move="down"]').addEventListener('click', e => { e.stopPropagation(); this.move(1); });
      sr.querySelector('[data-blockmenu]').addEventListener('click', e => { e.stopPropagation(); BlockMenu.open(e.currentTarget, this); });
      // Ziehen am Blocknamen verschiebt den Block (Maus/Trackpad; auf Touch: ↑ ↓ oder Block-Menü)
      const grip = sr.querySelector('[data-drag]');
      grip.addEventListener('dragstart', e => {
        e.stopPropagation();
        BlockDrag.from = this;
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/x-cms-block', this.blockId); } catch { /* */ }
        this.el.classList.add('is-dragging');
      });
      grip.addEventListener('dragend', () => { this.el.classList.remove('is-dragging'); BlockDrag.clear(); BlockDrag.from = null; });
      sr.querySelector('.cms-block__summary').addEventListener('click', () => this.toggleCollapse(false));
      // Editor.js soll Tasten in der Leiste (Enter/Leertaste auf Knöpfen) nicht als Texteingabe behandeln
      barEl.addEventListener('keydown', e => e.stopPropagation());
      if (collapsed.has(this.blockId)) this.toggleCollapse(true, false);
      const pv = el.querySelector('.cms-block__preview');
      // Links/Formulare in der Vorschau nicht auslösen. Ein Klick wählt den Block nur aus (Leiste und „+ Block einfügen“
      // erscheinen, Texte bleiben direkt bearbeitbar) – die Seitenleiste öffnet nur „Bearbeiten“ (bzw. „Inhalte eingeben“
      // in leeren Blöcken). Klick auf nicht direkt bearbeitbare Inhalte: Hinweis am Knopf „Bearbeiten“.
      pv.addEventListener('click', e => {
        if (e.target.closest('[data-edit]') || e.target.closest('summary')) return;
        e.preventDefault();
        // Blättern/Filter derselben Seite (?seite=2): im Bearbeiten-Modus bleiben (ungespeichert → Rückfrage des Browsers)
        const a = e.target.closest('a[href]');
        const u = a && new URL(a.href, location.href);
        const plain = s => { const p = new URLSearchParams(s); p.delete('edit'); p.sort(); return p.toString(); };
        if (u && u.origin === location.origin && u.pathname === location.pathname && plain(u.search) !== plain(location.search) && !e.target.closest('[data-cms-open]')) {
          u.searchParams.set('edit', '1');
          location.href = u.href;
          return;
        }
        if (e.target.closest('[data-cms-open]')) { this.openDrawer(); return; }
        // Block in einer Spalte (Layout): auf „Bearbeiten“ seiner eigenen Leiste hinweisen
        const item = e.target.closest('[data-lay-item]');
        const ib = item && $(':scope>.cms-lay-bar', item)?.shadowRoot?.querySelector('[data-act="edit"]');
        if (ib) { selectBlock(this.el); ib.classList.remove('is-hint'); void ib.offsetWidth; ib.classList.add('is-hint'); return; }
        this.hintEdit(!!e.target.closest('[data-central]'));
      });
      pv.addEventListener('submit', e => e.preventDefault());
      if (previews[this.blockId]) this.setPreview(previews[this.blockId]);
      else { this.isNew = !Object.keys(this.data).length; this.loadPreview(); }
      return el;
    }

    setPreview(html) {
      const pv = this.el.querySelector('.cms-block__preview');
      pv.innerHTML = html;
      const t = this.tuneData;
      this.el.classList.toggle('is-hidden', !t.visible);
      this.bar.querySelector('.cms-block__summary').textContent = summarize(this.data) || '(ohne Titel)';
      this.bar.querySelector('.cms-block__swatch').className = 'cms-block__swatch sw-' + (t.background || 'white');
      this.bar.querySelector('.cms-block__flags').innerHTML = [
        !t.visible && '<span class="cms-flag cms-flag--off">ausgeblendet</span>',
        t.anchor && `<span class="cms-flag">#${CMSAdmin.esc(t.anchor)}</span>`,
        t.showInNav && '<span class="cms-flag cms-flag--nav">Navigation</span>',
        t.height === 'screen' && '<span class="cms-flag">Vollbild</span>',
        t.bgImage && '<span class="cms-flag">Hintergrundbild</span>',
        t.row && `<span class="cms-flag cms-flag--row">${CMSAdmin.esc(CMSAdmin.t('Reihe: {w}', { w: CMSAdmin.t(ROW_LABEL[t.row] || t.row) }))}</span>`,
        cfg.glossary && t.noGlossary && `<span class="cms-flag">${CMSAdmin.esc(CMSAdmin.t('ohne Glossar'))}</span>`,
      ].filter(Boolean).join('');
      layoutRows();
      // Kits/Erweiterungen: Block ist (neu) gezeichnet – Skripte für Videos, Animationen usw. können ihn jetzt einrichten
      // (die Vorschau wird nach dem Laden der Seite eingesetzt, Kit-Skripte sind dann schon gelaufen)
      // Erste Vorschau entsteht, bevor der Block im Dokument hängt – Ereignis dann senden, sobald er eingehängt ist
      const fire = (n = 0) => {
        if (pv.isConnected) pv.dispatchEvent(new CustomEvent('cms:block-preview', { bubbles: true, detail: { type: this.type, id: this.blockId } }));
        else if (n < 120) requestAnimationFrame(() => fire(n + 1));
      };
      fire();
      // Direkt editierbare Texte: plain = nur Text, rich/inline = mit schwebender Formatierungsleiste
      $$('[data-edit]', pv).forEach(n => {
        const mode = n.dataset.editMode || 'plain';
        n.spellcheck = true;
        // Editor.js soll Tasten in unseren Feldern nicht abfangen (Enter, Backspace, Tab …)
        n.addEventListener('keydown', e => e.stopPropagation());
        if (mode === 'plain') {
          n.contentEditable = 'plaintext-only';
          if (n.contentEditable !== 'plaintext-only') n.contentEditable = 'true';
          n.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); n.blur(); } });
          n.addEventListener('input', () => {
            const v = fieldValue(n, false);
            setPath(this.data, n.dataset.edit, v);
            markDirty();
            this.syncInline(n.dataset.edit, v);
          });
          n.addEventListener('paste', e => { e.preventDefault(); d.execCommand('insertText', false, e.clipboardData.getData('text/plain')); });
          n.addEventListener('focus', () => InlineBar.hide());
        } else {
          n.contentEditable = 'true';
          n.classList.add('is-rich');
          if (!n.innerHTML.trim()) n.innerHTML = mode === 'rich' ? '<p><br></p>' : '';
          CMSAdmin.Rich.bindKeys(n, mode);
          n.addEventListener('input', () => {
            const html = fieldValue(n, true).replace(/^<p><br><\/p>$/, '');
            setPath(this.data, n.dataset.edit, html);
            markDirty();
            this.syncInline(n.dataset.edit, html, true);
          });
          n.addEventListener('focus', () => InlineBar.show(n, mode));
          n.addEventListener('blur', () => InlineBar.hideSoon());
        }
      });
      $$('input,select,textarea,button:not(.cms-block__edit)', pv).forEach(i => { i.tabIndex = -1; });
      if (type === LAYOUT) this.decorateLayout(pv);
      this.syncFormFields();
      BarPlace.soon();
    }

    /** Knopf „Felder bearbeiten“: nur mit gewählter Tabelle, deren Felder diese Rolle ändern darf */
    syncFormFields() {
      const b = this.bar.querySelector('.cms-block__fields');
      if (!b) return;
      const handle = String(this.data?.[def.formfields] || '');
      b.hidden = !handle || !(cfg.formFields || []).includes(handle);
      b.setAttribute('aria-label', CMSAdmin.t('Felder des Formulars bearbeiten ({label})', { label: def.label }));
    }

    openFormFields(btn) {
      const handle = String(this.data?.[def.formfields] || '');
      if (!handle || !CMSAdmin.formFields) return;
      selectBlock(this.el);
      CMSAdmin.formFields.open(cfg.endpoints.formfields + encodeURIComponent(handle), btn, {
        // Nach dem Speichern: alle Blöcke mit dieser Tabelle neu darstellen (ohne die Seite neu zu laden)
        onSaved: () => tools.forEach(t => { if (t.def.formfields && String(t.data?.[t.def.formfields] || '') === handle) t.loadPreview(); }),
      });
    }

    async loadPreview() {
      try {
        const res = await api(cfg.endpoints.preview, { page: cfg.page.id, entry: cfg.entry, block: this.serialize(), query: location.search });
        if (!Object.keys(this.data).length) this.data = res.block.data; // neuer Block → Standardwerte übernehmen
        if (type === LAYOUT) this.adoptColumns(res.block.data.columns || []);
        this.setPreview(res.html);
        const pv = this.el.querySelector('.cms-block__preview');
        if (!pv.textContent.trim()) pv.insertAdjacentHTML('beforeend', `<p class="cms-empty">${CMSAdmin.esc(CMSAdmin.t('{label} – noch leer.', { label: def.label }))} <button type="button" class="cms-empty__btn" data-cms-open>${CMSAdmin.esc(CMSAdmin.t('Inhalte eingeben'))}</button></p>`);
        if (this.isNew) {
          // Neuer Block: direkt in den ersten Text schreiben; ohne direkt bearbeitbaren Text (Bild, Liste …) die Seitenleiste
          this.isNew = false; markDirty(); selectBlock(this.el);
          const first = $('[data-edit]', pv);
          if (first) {
            this.el.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            first.focus({ preventScroll: true });
          } else this.openDrawer();
        }
      } catch (e) { this.el.querySelector('.cms-block__preview').innerHTML = `<p class="cms-error">Vorschau fehlgeschlagen: ${CMSAdmin.esc(e.message)}</p>`; }
    }

    /** Um eine Position verschieben (ohne Drag & Drop) */
    move(dir) {
      const els = blockEls(), from = els.indexOf(this.el.closest('.ce-block')), to = from + dir;
      if (from < 0 || to < 0 || to >= els.length) return;
      editor.blocks.move(to, from);
      markDirty();
      requestAnimationFrame(() => {
        refreshMoveButtons();
        this.el.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        const btn = this.bar.querySelector(`[data-move="${dir < 0 ? 'up' : 'down'}"]`);
        (btn && !btn.disabled ? btn : this.bar.querySelector('.cms-block__edit'))?.focus({ preventScroll: true });
        this.el.classList.remove('is-moved'); void this.el.offsetWidth; this.el.classList.add('is-moved');
      });
    }

    toggleCollapse(force, remember = true) {
      const on = force ?? !this.el.classList.contains('is-collapsed');
      this.el.classList.toggle('is-collapsed', on);
      this.bar.querySelector('.cms-block__summary')?.setAttribute('title', on ? CMSAdmin.t('Klicken zum Ausklappen') : '');
      BarPlace.place(this.el);
      if (remember) { on ? collapsed.add(this.blockId) : collapsed.delete(this.blockId); store.set(COLLAPSE_KEY, [...collapsed]); }
    }

    serialize() { return { id: this.blockId, type: this.type, data: this.data, tunes: { section: this.tuneData } }; }

    save() { return this.data; }

    /** Klick auf Inhalte ohne Direktbearbeitung: Block auswählen und kurz auf „Bearbeiten“ hinweisen (öffnet nichts) */
    hintEdit(central = false) {
      selectBlock(this.el);
      const hint = this.bar.querySelector('.cms-block__hint'), btn = this.bar.querySelector('.cms-block__edit');
      hint.textContent = central ? CMSAdmin.t('Zentral gepflegt – ändern unter „{title}“', { title: cfg.settingsTitle || CMSAdmin.t('Einstellungen') }) : CMSAdmin.t('Felder ändern: „Bearbeiten“');
      hint.hidden = false; this.bar.host.classList.add('is-hinting'); BarPlace.soon();
      btn.classList.remove('is-hint'); void btn.offsetWidth; btn.classList.add('is-hint');
      clearTimeout(this.hintT);
      this.hintT = setTimeout(() => { hint.hidden = true; this.bar.host.classList.remove('is-hinting'); btn.classList.remove('is-hint'); BarPlace.soon(); }, 2600);
    }

    syncDrawerField(path, value, rich = false) { syncDrawerField(path, value, rich); }

    openDrawer(focusSection = false) { return openDrawer(this, focusSection); }

    /** Direkt bearbeiteter Text → Seitenleiste angleichen (auch für einen Block in einer Spalte, dessen Felder gerade offen sind) */
    syncInline(path, value, rich = false) {
      if (drawerFor === this) { syncDrawerField(path, value, rich); drawerTouched(); return; }
      if (drawerFor?.parent === this) {
        const m = path.match(/^columns\.(\d+)\.blocks\.(\d+)\.data\.(.+)$/);
        if (m && this.data.columns?.[m[1]]?.blocks?.[m[2]]?.id === drawerFor.blockId) { syncDrawerField(m[3], value, rich); drawerTouched(); }
      }
    }

    // -------------------------------------------------------------- Layout (Spalten, Core\Layout)
    /** Spalten vom Server übernehmen, wenn sich die Aufteilung geändert hat (Raster mit weniger Spalten, abgelehnte Blöcke) */
    adoptColumns(srv) {
      const cur = this.data.columns || [];
      const shape = cols => JSON.stringify(cols.map(c => (c.blocks || []).map(b => b.id)));
      if (shape(srv) !== shape(cur)) { this.data.columns = structuredClone(srv); return; }
      // Neu eingefügte Blöcke (noch ohne Daten): Standardwerte des Servers übernehmen
      srv.forEach((c, ci) => c.blocks.forEach((b, bi) => {
        const mine = cur[ci].blocks[bi];
        if (!Object.keys(mine.data || {}).length) mine.data = structuredClone(b.data);
        if (!mine.tunes) mine.tunes = structuredClone(b.tunes || { section: {} });
      }));
    }

    /** Leiste je Block in einer Spalte und „+ Block in diese Spalte“ (eigene Schatten-Wurzeln, im Fluss – nie über Inhalten) */
    decorateLayout(pv) {
      const T = CMSAdmin.t, E = CMSAdmin.esc;
      const cols = this.data.columns || [];
      $$('[data-lay-col]', pv).forEach(colEl => {
        const ci = +colEl.dataset.layCol;
        const list = cols[ci]?.blocks || [];
        $$(':scope>[data-lay-item]', colEl).forEach(itemEl => {
          const bi = +itemEl.dataset.layItem.split('.')[1];
          const child = list[bi];
          if (!child) return;
          const cdef = cfg.blocks[child.type] || { label: child.type };
          const host = d.createElement('div');
          host.className = 'cms-lay-bar'; host.contentEditable = 'false';
          itemEl.prepend(host);
          const L = E(cdef.label);
          const sr = S.shadowFor(host, `<span class="cms-lay-bar__label" title="${L}"><span aria-hidden="true">${blockIcon(cdef)}</span> <span class="cms-lay-bar__name">${L}</span></span>
            <span class="cms-lay-bar__tools" role="group" aria-label="${E(T('Block „{label}“ in Spalte {n}', { label: cdef.label, n: ci + 1 }))}">
              <button type="button" class="cms-iconbtn" data-act="up" aria-label="${E(T('Nach oben'))}" title="${E(T('Nach oben'))}">↑</button>
              <button type="button" class="cms-iconbtn" data-act="down" aria-label="${E(T('Nach unten'))}" title="${E(T('Nach unten'))}">↓</button>
              <button type="button" class="cms-iconbtn" data-act="left" aria-label="${E(T('In die Spalte links'))}" title="${E(T('In die Spalte links'))}">←</button>
              <button type="button" class="cms-iconbtn" data-act="right" aria-label="${E(T('In die Spalte rechts'))}" title="${E(T('In die Spalte rechts'))}">→</button>
              <button type="button" class="cms-lay-bar__edit" data-act="edit" aria-label="${E(T('„{label}“ bearbeiten', { label: cdef.label }))}">${E(T('Bearbeiten'))}</button>
              <button type="button" class="cms-iconbtn cms-iconbtn--danger" data-act="del" aria-label="${E(T('„{label}“ löschen', { label: cdef.label }))}" title="${E(T('Löschen'))}">✕</button>
            </span>`);
          const dis = { up: bi === 0, down: bi === list.length - 1, left: ci === 0, right: ci === cols.length - 1 };
          $$('[data-act]', sr).forEach(b => {
            if (dis[b.dataset.act]) b.disabled = true;
            b.addEventListener('click', e => { e.stopPropagation(); this.childAction(child.id, b.dataset.act); });
          });
          host.addEventListener('keydown', e => e.stopPropagation());
          if (this.focusAfter?.id === child.id) {
            const want = $(`[data-act="${this.focusAfter.act}"]`, sr);
            (want && !want.disabled ? want : $('[data-act="edit"]', sr)).focus({ preventScroll: true });
            this.focusAfter = null;
          }
        });
        const addHost = d.createElement('div');
        addHost.className = 'cms-lay-add'; addHost.contentEditable = 'false';
        colEl.append(addHost);
        const asr = S.shadowFor(addHost, `<button type="button" class="cms-addbtn cms-addbtn--col" aria-haspopup="dialog"><span aria-hidden="true">+</span> ${E(T('Block in diese Spalte'))}</button>`);
        const ab = asr.querySelector('button');
        ab.setAttribute('aria-label', T('Block in Spalte {n} einfügen', { n: ci + 1 }));
        ab.addEventListener('click', e => { e.stopPropagation(); BlockPicker.open(ab, this, { column: ci }); });
        addHost.addEventListener('keydown', e => e.stopPropagation());
        if (this.focusAfter?.add === ci) { ab.focus({ preventScroll: true }); this.focusAfter = null; }
      });
      this.decorateResize(pv);
    }

    /**
     * Spaltenbreiten mit der Maus ziehen (Core\Layout::cleanWidths): Griff zwischen zwei Spalten, rastet auf Zwölftel ein
     * (½, ⅓, ¼ … werden angezeigt), Nachbarspalte gleicht aus. Pfeiltasten: ein Zwölftel; Doppelklick bzw. Entf: zurück zum Raster.
     * Gespeichert als data.widths = {preset, w} – ein anderes Raster verwirft die Breiten. Nur, solange die Spalten nebeneinander stehen.
     */
    decorateResize(pv) {
      const T = CMSAdmin.t, E = CMSAdmin.esc;
      const grid = pv.querySelector('.lay-grid[data-lay-w]');
      if (!grid) return;
      const cols = [...grid.children].filter(c => c.matches('[data-lay-col]'));
      const units = grid.dataset.layW.split(',').map(Number);
      if (cols.length < 2 || units.length !== cols.length) return;
      if (cols[1].getBoundingClientRect().top > cols[0].getBoundingClientRect().top + 8) return;   // untereinander (schmal)
      const U = 12, MIN = 2;
      const frac = n => ({ 2: '⅙', 3: '¼', 4: '⅓', 6: '½', 8: '⅔', 9: '¾', 10: '⅚' })[n] || n + '/12';
      const apply = w => cols.forEach((c, i) => c.style.setProperty('--lay-w', w[i]));
      const commit = async (w, msg) => {
        if (w) this.data.widths = { preset: this.data.preset || '1-1', w: [...w] }; else delete this.data.widths;
        markDirty();
        const st = S.ui('[data-editor-status]');
        if (st) st.textContent = msg;
        await this.loadPreview();
      };
      const gap = parseFloat(getComputedStyle(grid).columnGap) || 0;
      for (let i = 0; i < cols.length - 1; i++) {
        const col = cols[i];
        col.style.position = 'relative';
        const host = d.createElement('div');
        host.className = 'cms-lay-resize'; host.contentEditable = 'false';
        host.style.cssText = `position:absolute;top:0;bottom:0;right:${-(gap / 2) - 11}px;width:22px;z-index:6`;
        col.append(host);
        const sr = S.shadowFor(host, `<button type="button" class="cms-lay-resize__h" role="separator" aria-orientation="vertical"><span class="cms-lay-resize__tip"></span></button>`);
        const h = sr.querySelector('.cms-lay-resize__h'), tip = sr.querySelector('.cms-lay-resize__tip');
        const label = w => w.map(frac).join(' · ');
        const aria = w => {
          tip.textContent = label(w);
          h.setAttribute('aria-label', T('Breite von Spalte {a} und {b} ziehen – jetzt {w}. Pfeiltasten: schmaler/breiter, Entf: zurück zum Raster.', { a: i + 1, b: i + 2, w: label(w) }));
          h.setAttribute('aria-valuenow', String(w[i])); h.setAttribute('aria-valuemin', String(MIN)); h.setAttribute('aria-valuemax', String(w[i] + w[i + 1] - MIN));
        };
        aria(units);
        h.title = T('Ziehen: Spaltenbreite ändern · Doppelklick: zurück zum Raster');
        let drag = null;
        h.addEventListener('pointerdown', e => {
          e.preventDefault(); e.stopPropagation();
          h.setPointerCapture(e.pointerId); h.classList.add('is-drag');
          const total = grid.getBoundingClientRect().width - gap * (cols.length - 1);
          drag = { w: [...units], left: col.getBoundingClientRect().left, unit: total / U, pair: units[i] + units[i + 1], before: units.slice(0, i).reduce((a, b) => a + b, 0) };
          apply(drag.w);
        });
        h.addEventListener('pointermove', e => {
          if (!drag) return;
          const x = e.clientX - drag.left;
          const n = Math.max(MIN, Math.min(drag.pair - MIN, Math.round(x / drag.unit)));
          if (n === drag.w[i]) return;
          drag.w[i] = n; drag.w[i + 1] = drag.pair - n;
          apply(drag.w); aria(drag.w);
        });
        const end = async e => {
          if (!drag) return;
          const w = drag.w; drag = null; h.classList.remove('is-drag');
          if (h.hasPointerCapture?.(e.pointerId)) h.releasePointerCapture(e.pointerId);
          if (w.join() !== units.join()) await commit(w, T('Spaltenbreiten: {w} – noch nicht gespeichert.', { w: label(w) }));
        };
        h.addEventListener('pointerup', end);
        h.addEventListener('pointercancel', end);
        h.addEventListener('dblclick', e => { e.stopPropagation(); commit(null, T('Spaltenbreiten zurück zum Raster – noch nicht gespeichert.')); });
        h.addEventListener('click', e => e.stopPropagation());
        h.addEventListener('keydown', e => {
          e.stopPropagation();
          const step = e.key === 'ArrowLeft' ? -1 : e.key === 'ArrowRight' ? 1 : 0;
          if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); commit(null, T('Spaltenbreiten zurück zum Raster – noch nicht gespeichert.')); return; }
          if (!step) return;
          e.preventDefault();
          const w = [...units], pair = w[i] + w[i + 1], n = Math.max(MIN, Math.min(pair - MIN, w[i] + step));
          if (n === w[i]) return;
          w[i] = n; w[i + 1] = pair - n;
          this.focusAfter = { resize: i };
          commit(w, T('Spaltenbreiten: {w} – noch nicht gespeichert.', { w: label(w) }));
        });
        if (this.focusAfter?.resize === i) { h.focus({ preventScroll: true }); this.focusAfter = null; }
      }
    }

    /** Lage eines Blocks in den Spalten: [Spalte, Position] */
    childLoc(id) {
      const cols = this.data.columns || [];
      for (let ci = 0; ci < cols.length; ci++) {
        const bi = (cols[ci].blocks || []).findIndex(b => b.id === id);
        if (bi >= 0) return [ci, bi];
      }
      return null;
    }

    async childAction(id, act) {
      const loc = this.childLoc(id);
      if (!loc) return;
      const [ci, bi] = loc, cols = this.data.columns, list = cols[ci].blocks, child = list[bi];
      const T = CMSAdmin.t, label = cfg.blocks[child.type]?.label || child.type;
      selectBlock(this.el);
      if (act === 'edit') { openDrawer(new ChildRef(this, id)); return; }
      if (act === 'del') {
        if (!(await (Bar?.ask || (o => Promise.resolve(confirm(o.title + '\n' + o.body))))({ title: T('Block löschen?'), body: T('„{label}“ wird aus dieser Spalte entfernt. Bis zum Speichern lässt sich das mit „Abbrechen“ rückgängig machen; danach über Versionen.', { label }), ok: T('Löschen'), danger: true }))) return;
        list.splice(bi, 1);
        if (drawerFor?.blockId === id) closeDrawer();
        this.focusAfter = { add: ci };
      } else if (act === 'up' || act === 'down') {
        const to = bi + (act === 'up' ? -1 : 1);
        if (to < 0 || to >= list.length) return;
        [list[bi], list[to]] = [list[to], list[bi]];
        this.focusAfter = { id, act };
      } else if (act === 'left' || act === 'right') {
        const tc = ci + (act === 'left' ? -1 : 1);
        if (tc < 0 || tc >= cols.length) return;
        list.splice(bi, 1);
        const dest = cols[tc].blocks;
        dest.splice(Math.min(bi, dest.length), 0, child);
        this.focusAfter = { id, act };
      }
      markDirty();
      const st = S.ui('[data-editor-status]');
      if (st) st.textContent = { del: T('„{label}“ gelöscht – noch nicht gespeichert.', { label }), up: T('„{label}“ nach oben verschoben.', { label }),
        down: T('„{label}“ nach unten verschoben.', { label }), left: T('„{label}“ in die Spalte links verschoben.', { label }), right: T('„{label}“ in die Spalte rechts verschoben.', { label }) }[act] || '';
      await this.loadPreview();
    }

    /** Neuer Block in einer Spalte: danach in den ersten Text schreiben bzw. (ohne direkt bearbeitbaren Text) die Felder öffnen */
    async addChild(ci, type) {
      const id = Math.random().toString(16).slice(2, 12).padEnd(10, '0');
      this.data.columns ||= [];
      while (this.data.columns.length <= ci) this.data.columns.push({ blocks: [] });
      this.data.columns[ci].blocks.push({ id, type, data: {}, tunes: { section: {} } });
      markDirty();
      await this.loadPreview();
      const item = this.el.querySelector(`[data-lay-id="${CSS.escape(id)}"]`);
      const first = item && $('[data-edit]', item);
      item?.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      if (first) first.focus({ preventScroll: true }); else openDrawer(new ChildRef(this, id));
      const st = S.ui('[data-editor-status]');
      if (st) st.textContent = CMSAdmin.t('Block „{label}“ in Spalte {n} eingefügt – noch nicht gespeichert.', { label: cfg.blocks[type]?.label || type, n: ci + 1 });
    }
  };
}


// ------------------------------------------------------------------ Seitenleiste „Block“ (Felder eines Blocks oder eines Blocks in einer Spalte)
/**
 * Block in einer Spalte eines Layouts – gleiche Schnittstelle wie ein Block-Tool (type, def, data, tuneData, el, refresh,
 * loadPreview), damit Seitenleiste, Bild anpassen und Bild im Rahmen ihn wie einen eigenen Block behandeln. Die Daten liegen im
 * Layout (data.columns[s].blocks[n]); gefunden über die Block-ID, damit Verschieben die Verbindung nicht löst.
 */
class ChildRef {
  constructor(parent, id) { this.parent = parent; this.blockId = id; this.isChild = true; }
  get child() { for (const c of this.parent.data.columns || []) { const b = (c.blocks || []).find(x => x.id === this.blockId); if (b) return b; } return null; }
  get type() { return this.child?.type || ''; }
  get def() { return cfg.blocks[this.type] || { label: this.type, fields: [] }; }
  get data() { return this.child?.data || {}; }
  set data(v) { const c = this.child; if (c) c.data = v; }
  get tuneData() { return { anchor: '', visible: true, background: '', ...(this.child?.tunes?.section || {}) }; }
  setTunes(t) { const c = this.child; if (c) c.tunes = { section: { ...t } }; }
  get el() { return this.parent.el; }
  refresh() { this.parent.refresh(); }
  loadPreview() { return this.parent.loadPreview(); }
}
/** Werkzeug (Block oder Block in einer Spalte) zu einem Element der Vorschau */
function toolFor(node) {
  const el = node.closest('.cms-block');
  const tool = [...tools.values()].find(x => x.el === el);
  const item = node.closest('[data-lay-id]');
  return tool && item ? new ChildRef(tool, item.dataset.layId) : tool;
}
function syncDrawerField(path, value, rich = false) {
  const name = 'f[' + path.split('.').join('][') + ']';
  const input = drawer.querySelector(`[name="${CSS.escape(name)}"]`);
  if (!input) return;
  input.value = value;
  const area = rich && input.closest('.rte')?.querySelector('.rte-area');
  if (area && !area.contains(d.activeElement)) area.innerHTML = value;
}
async function openDrawer(tool, focusSection = false) {
  const def = tool.def;
  // Stand beim Öffnen merken: „Abbrechen“ in der Seitenleiste setzt nur diesen Block zurück
  if (drawerSnap?.tool?.blockId !== tool.blockId || drawer.hidden) {
    drawerSnap = { tool, data: structuredClone(tool.data), tunes: structuredClone(tool.tuneData), dirty, touched: false };
    drawerButtons();
  }
  drawerFor = tool;
  const form = $('[data-drawer-form]', drawer), central = $('[data-drawer-central]', drawer);
  $('#cms-drawer-title', drawer).textContent = tool.isChild ? CMSAdmin.t('{label} (in Spalte)', { label: def.label }) : def.label;
  central.hidden = !def.central;
  if (def.central) central.innerHTML = `${CMSAdmin.esc(def.central)} <a href="${cfg.endpoints.settings}" target="_blank" rel="noopener">Zentral gepflegt → ${CMSAdmin.esc(cfg.settingsTitle || 'Einstellungen')} ↗</a>`;
  form.innerHTML = '<p class="adm-muted">Lade Felder …</p>';
  drawer.hidden = false; d.body.classList.add('has-drawer');
  $$('.cms-block.is-active').forEach(b => b.classList.remove('is-active'));
  tool.el.classList.add('is-active');
  selectBlock(tool.el);
  renderTuneForm(tool);
  if (focusSection) { const s = $('.cms-drawer__section', drawer); s.open = true; s.scrollIntoView(); }
  const res = await api(cfg.endpoints.form, { type: tool.type, data: tool.data, entry: cfg.entry, nested: !!tool.isChild });
  if (drawerFor !== tool) return;
  form.innerHTML = res.html;
  CMSAdmin.init(form);
  // Felder nur für bestimmte Varianten (Core\Fields 'variants' → .f-vis[data-variants]) passend zur Auswahl zeigen
  const byVariant = () => {
    const v = form.querySelector('[name="f[variant]"]')?.value;
    if (v != null) $$('[data-variants]', form).forEach(el => { el.hidden = !el.dataset.variants.split(' ').includes(v); });
  };
  byVariant();
  const onChange = () => {
    byVariant();
    // Nicht im Formular: Bildanpassungen, Rahmen und – beim Layout – die Blöcke der Spalten
    const keep = Object.fromEntries(['_fx', '_fit', ...(tool.type === LAYOUT ? ['columns'] : [])].filter(k => tool.data[k] !== undefined).map(k => [k, tool.data[k]]));
    tool.data = { ...formToObject(form), ...keep };
    markDirty(); drawerTouched(); tool.refresh();
    fxFieldButtons(form, tool);
  };
  form.oninput = onChange; form.onchange = onChange;
  fxFieldButtons(form, tool);
  if (!focusSection) $('input:not([type=hidden]),select,textarea,[contenteditable]', form)?.focus({ preventScroll: true });
}

// ------------------------------------------------------------------ Bild anpassen je Einbindung (Core\ImageFx)
/** Feldpfade im Block, deren Bild-Feld (Typ „media“) die Medien-ID enthält – auch in Listen: „items.2.image“ */
function mediaPaths(fields, data, id, prefix = '') {
  const out = [];
  for (const f of fields || []) {
    const v = data?.[f.name], p = prefix + f.name;
    if (f.type === 'media' && v != null && v !== '' && +v === id) out.push(p);
    if ((f.type === 'repeater' || f.type === 'group') && Array.isArray(v)) v.forEach((item, i) => out.push(...mediaPaths(f.fields, item, id, `${p}.${i}.`)));
  }
  return out;
}
/** Anpassung an Pfaden setzen (null = wie in der Mediathek → Eintrag entfernen), Vorschau neu laden */
function setFx(tool, paths, v) {
  const fx = { ...(tool.data._fx || {}) };
  paths.forEach(p => { if (v == null || v === '') delete fx[p]; else fx[p] = v; });
  if (Object.keys(fx).length) tool.data._fx = fx; else delete tool.data._fx;
  markDirty();
  if (drawerFor === tool) drawerTouched();
  tool.loadPreview();
}
/** Dialog für eine Einbindung öffnen (gleicher Dialog wie in der Mediathek, window.CMSMedia.adjust aus _media.js) */
async function openFx(tool, paths, id) {
  const M = window.CMSMedia;
  if (!M?.adjust) return;
  const m = await M.api.detail(id);
  const cur = paths.map(p => tool.data._fx?.[p]).find(v => v != null) ?? null;
  return M.adjust({
    src: m.large || m.url, thumb: m.thumb, name: m.display, scope: 'place', value: cur, global: m.adjust,
    note: paths.length > 1 ? CMSAdmin.t('Das Bild kommt in diesem Block mehrfach vor – die Einstellung gilt für alle diese Stellen.') : '',
    onApply: async v => setFx(tool, paths, v),
  });
}
// Schnittstelle für den Knopf „Anpassen“ am Bild (_media.js): Block und Feldpfade zu einem <img data-media-id>
window.CMSEditor = Object.assign(window.CMSEditor || {}, {
  // Ungespeicherte Änderungen? (z. B. Werkzeug „Seiteneinstellungen“: nur dann neu laden, wenn nichts verloren geht)
  isDirty: () => dirty,
  fx: {
    target(img) {
      const tool = toolFor(img);
      const id = +img.dataset.mediaId;
      const paths = tool ? mediaPaths(tool.def.fields, tool.data, id) : [];
      if (!paths.length) return null;
      return { paths, value: paths.map(p => tool.data._fx?.[p]).find(v => v != null) ?? null, set: v => setFx(tool, paths, v) };
    },
  },
});
// ------------------------------------------------------------------ Bild im Rahmen je Einbindung (Core\ImageFit)
/** Einstellung an Pfaden setzen (null = wie in der Mediathek → Eintrag entfernen), Vorschau neu laden */
function setFit(tool, paths, v) {
  const fit = { ...(tool.data._fit || {}) };
  paths.forEach(p => { if (v == null || v === '') delete fit[p]; else fit[p] = v; });
  if (Object.keys(fit).length) tool.data._fit = fit; else delete tool.data._fit;
  markDirty();
  if (drawerFor === tool) drawerTouched();
  tool.loadPreview();
}
/** Seitenverhältnis des Rahmens an dieser Stelle: Bildformat des Kits (data-frame) oder gemessen */
function frameRatio(img) {
  if (!img) return 0;
  if (img.dataset.frame) return img.dataset.frame;
  const r = img.getBoundingClientRect();
  return r.width && r.height ? r.width / r.height : 0;
}
/** Dialog „Darstellung im Rahmen“ für eine Einbindung (window.CMSMedia.fit aus _media.js) */
async function openFit(tool, paths, id, img) {
  const M = window.CMSMedia;
  if (!M?.fit) return;
  const m = await M.api.detail(id);
  img ||= tool.el?.querySelector(`img[data-media-id="${id}"]`);
  return M.fit({
    src: m.large || m.url, thumb: m.thumb || m.url, name: m.display, scope: 'place', svg: m.svg,
    value: paths.map(p => tool.data._fit?.[p]).find(v => v != null) ?? null, global: m.fit, auto: m.fit_auto, ratio: frameRatio(img),
    note: paths.length > 1 ? CMSAdmin.t('Das Bild kommt in diesem Block mehrfach vor – die Einstellung gilt für alle diese Stellen.') : '',
    onApply: async v => setFit(tool, paths, v),
  });
}
// ------------------------------------------------------------------ Bild tauschen direkt am Bild (Knöpfe „Tauschen“/„Hochladen“, _media.js)
/** Bildfelder eines Blocks mit Pfad und Felddefinition (wie mediaPaths, aber mit Feld – für die Art der Auswahl) */
function mediaFields(fields, data, id, prefix = '') {
  const out = [];
  for (const f of fields || []) {
    const v = data?.[f.name], p = prefix + f.name;
    if (f.type === 'media' && v != null && v !== '' && +v === id) out.push({ path: p, field: f });
    if ((f.type === 'repeater' || f.type === 'group') && Array.isArray(v)) v.forEach((item, i) => out.push(...mediaFields(f.fields, item, id, `${p}.${i}.`)));
  }
  return out;
}
window.CMSEditor.swap = {
  /** Bild im Block → { kind ('image'|'visual'), set(media) } oder null (nicht zuordenbar, z. B. aus einer Datentabelle) */
  target(img) {
    const tool = toolFor(img);
    const id = +img.dataset.mediaId;
    const all = tool ? mediaFields(tool.def.fields, tool.data, id) : [];
    if (!all.length) return null;
    // Genau diese Stelle: Pfad am Bild oder n-tes Vorkommen; sonst alle Stellen mit diesem Bild
    let hit = all;
    const own = img.dataset.mediaPath;
    if (own && all.some(x => x.path === own)) hit = all.filter(x => x.path === own);
    else if (all.length > 1) {
      const el = img.closest('[data-lay-id]') || img.closest('.cms-block');
      const imgs = [...el.querySelectorAll(`img[data-media-id="${id}"]`)];
      if (imgs.length === all.length) hit = [all[imgs.indexOf(img)]];
    }
    const kind = hit.some(x => x.field.accept === 'visual') ? 'visual' : 'image';
    return {
      kind,
      set(m) {
        if (!m?.id) return;
        hit.forEach(({ path }) => {
          setPath(tool.data, path, m.id);
          // Anpassung und Rahmen galten dem alten Bild
          if (tool.data._fx) delete tool.data._fx[path];
          if (tool.data._fit) delete tool.data._fit[path];
        });
        markDirty();
        tool.loadPreview();
        if (drawerFor === tool) openDrawer(tool);   // Seitenleiste zeigt das neue Bild
      },
    };
  },
};
// Schnittstelle für Kit- und Erweiterungs-Skripte im Seiten-Editor (theme.php → 'editor_js'): Daten eines Blocks lesen und
// ändern – z. B. Fotos per Drag & Drop anhängen (Kit „foto“). set() übernimmt die Werte (Pfade mit Punkten), markiert die Seite
// als geändert, frischt die offene Seitenleiste auf und zeichnet die Vorschau neu (Ereignis cms:block-preview).
window.CMSEditor.block = node => {
  const tool = node instanceof Element ? toolFor(node) : null;
  if (!tool) return null;
  return {
    type: tool.type, id: tool.blockId, def: tool.def,
    get: path => structuredClone(path ? String(path).split('.').reduce((o, k) => o?.[k], tool.data) : tool.data),
    set(changes) {
      Object.entries(changes || {}).forEach(([p, v]) => setPath(tool.data, p, structuredClone(v)));
      markDirty();
      if (drawerFor === tool || (tool.isChild && drawerFor?.blockId === tool.blockId)) openDrawer(tool);
      return tool.loadPreview();
    },
  };
};
window.CMSEditor.fit = {
  target(img) {
    const tool = toolFor(img);
    const el = img.closest('[data-lay-id]') || img.closest('.cms-block');
    const id = +img.dataset.mediaId;
    const all = tool ? mediaPaths(tool.def.fields, tool.data, id) : [];
    // Bild im Rahmen gilt je Feldpfad (Core\ImageFit): Pfad am Bild (data-media-path) oder n-tes Vorkommen im Block
    let paths = all;
    const own = img.dataset.mediaPath;
    if (own && all.includes(own)) paths = [own];
    else if (all.length > 1) {
      const imgs = [...el.querySelectorAll(`img[data-media-id="${id}"]`)];
      if (imgs.length === all.length) paths = [all[imgs.indexOf(img)]];
    }
    return paths.length ? { paths, open: () => openFit(tool, paths, id, img) } : null;
  },
};

/** Seitenleiste: Knopf „Anpassen …“ an jedem Bild-Feld mit gewähltem Bild (Tastatur-Zugang, gleiche Einstellung wie am Bild) */
function fxFieldButtons(form, tool) {
  if (!window.CMSMedia?.adjust) return;
  $$('.media-field[data-accept="image"]', form).forEach(mf => {
    const inp = $('input[type=hidden]', mf);
    const m = inp?.name.match(/^f\[(.+)\]$/);
    if (!m) return;
    // Pfad aus dem Feldnamen; Listen-Positionen (auch neue „n123“) nach Reihenfolge im Formular
    const reps = []; for (let n = mf.parentElement; n && n !== form; n = n.parentElement) if (n.classList?.contains('rep-item')) reps.unshift(n);
    let ri = 0;
    const path = m[1].split('][').map(k => /^(\d+|n\d+)$/.test(k) ? (reps[ri] ? [...reps[ri].parentElement.children].filter(c => c.classList.contains('rep-item')).indexOf(reps[ri++]) : k) : k).join('.');
    let btn = $('[data-media-fx]', mf);
    if (!btn) {
      btn = d.createElement('button');
      btn.type = 'button'; btn.className = 'btn btn--small btn--ghost'; btn.dataset.mediaFx = '';
      mf.append(' ', btn);
      btn.addEventListener('click', () => {
        const id = +$('input[type=hidden]', mf).value;
        if (!id) return;
        // Wie am Bild: alle Stellen dieses Blocks mit demselben Bild (die Anzeige unterscheidet sie nicht)
        const all = mediaPaths(tool.def.fields, tool.data, id);
        openFx(tool, all.includes(btn.dataset.path) ? all : [btn.dataset.path], id).then(() => fxFieldButtons(form, tool));
      });
    }
    btn.dataset.path = path;
    btn.hidden = !inp.value;
    const cur = tool.data._fx?.[path];
    btn.textContent = cur ? CMSAdmin.t('Angepasst: {label}', { label: window.CMSMedia.fxLabel(cur) }) + ' …' : CMSAdmin.t('Anpassen …');
    btn.setAttribute('aria-label', CMSAdmin.t('Bild anpassen – nur an dieser Stelle'));
    // Darstellung im Rahmen (füllen, einpassen, Originalformat) – nur an dieser Stelle
    if (!window.CMSMedia.fit) return;
    let fb = $('[data-media-fit]', mf);
    if (!fb) {
      fb = d.createElement('button');
      fb.type = 'button'; fb.className = 'btn btn--small btn--ghost'; fb.dataset.mediaFit = '';
      btn.after(' ', fb);
      fb.addEventListener('click', () => {
        const id = +$('input[type=hidden]', mf).value;
        if (!id) return;
        // Nur diese Stelle – Bild im Rahmen unterscheidet Feldpfade (dasselbe Bild zweimal, verschieden eingepasst)
        openFit(tool, [fb.dataset.path], id).then(() => fxFieldButtons(form, tool));
      });
    }
    fb.dataset.path = path;
    fb.hidden = !inp.value;
    const fc = tool.data._fit?.[path];
    fb.textContent = fc ? CMSAdmin.t('Rahmen: {label}', { label: window.CMSMedia.fitLabel(fc) }) + ' …' : CMSAdmin.t('Rahmen …');
    fb.setAttribute('aria-label', CMSAdmin.t('Darstellung im Rahmen (füllen, einpassen, Originalformat) – nur an dieser Stelle'));
  });
}

// ------------------------------------------------------------------ Abschnitt-Formular in der Seitenleiste
function renderTuneForm(tool) {
  const f = $('[data-drawer-tunes]', drawer);
  const t = tool.tuneData, bgs = cfg.backgrounds, E = CMSAdmin.esc, T = CMSAdmin.t;
  const opt = (o, v) => Object.entries(o).map(([k, l]) => `<option value="${E(k)}"${k === v ? ' selected' : ''}>${E(l)}</option>`).join('');
  const sp = { normal: 'Normal', small: 'Klein', none: 'Kein' };
  const slug = v => v.trim().toLowerCase().replace(/[^a-z0-9-]+/g, '-');
  $('.cms-drawer__section>summary', drawer).textContent = tool.isChild ? T('In der Spalte') : T('Abschnitt & Navigation');
  // Block in einer Spalte: nur eigene Fläche (Karte), Sprungmarke, sichtbar – Abschnitt, Abstände, Navigation gelten fürs Layout
  if (tool.isChild) {
    f.innerHTML = `
      <p class="f-help">${E(T('Hintergrund, Abstände, Navigation und Hintergrundbild gelten für das ganze Layout – dort einstellen.'))}</p>
      <div class="f f--half"><label for="t-cbg">${E(T('Eigene Fläche (Karte)'))}</label><select id="t-cbg" name="background">${opt({ '': T('Keine – wie das Layout'), ...bgs }, t.background || '')}</select></div>
      <div class="f f--half"><label for="t-anchor">Sprungmarke (Anker)</label><input id="t-anchor" name="anchor" value="${E(t.anchor || '')}" placeholder="z. B. termine"></div>
      <div class="f"><label class="f-check"><input type="checkbox" name="visible"${t.visible !== false ? ' checked' : ''}> <span>Sichtbar</span></label></div>`;
    f.classList.add('adm-fields');
    CMSAdmin.init(f);
    f.oninput = f.onchange = () => { tool.setTunes({ background: f.background.value, anchor: slug(f.anchor.value), visible: f.visible.checked }); markDirty(); drawerTouched(); tool.refresh(); };
    return;
  }
  const bgImg = tool.el?.querySelector('.sec__bg img'), bgThumb = bgImg && (bgImg.currentSrc || bgImg.src);
  f.innerHTML = `
    <div class="f f--half"><label for="t-bg">Hintergrund</label><select id="t-bg" name="background">${opt(bgs, t.background)}</select></div>
    <div class="f f--half"><label for="t-anchor">Sprungmarke (Anker)</label><input id="t-anchor" name="anchor" value="${E(t.anchor)}" placeholder="z. B. ueber-uns"></div>
    <div class="f f--half"><label for="t-st">Abstand oben</label><select id="t-st" name="spaceTop">${opt(sp, t.spaceTop)}</select></div>
    <div class="f f--half"><label for="t-sb">Abstand unten</label><select id="t-sb" name="spaceBottom">${opt(sp, t.spaceBottom)}</select></div>
    <div class="f"><label class="f-check"><input type="checkbox" name="visible"${t.visible ? ' checked' : ''}> <span>Sichtbar</span></label></div>
    <div class="f"><label class="f-check"><input type="checkbox" name="divider"${t.divider ? ' checked' : ''}> <span>Trennlinie oben</span></label></div>
    <div class="f"><label class="f-check"><input type="checkbox" name="showInNav"${t.showInNav ? ' checked' : ''}> <span>In Hauptnavigation anzeigen (Anker erforderlich)</span></label></div>
    <div class="f"><label for="t-nav">Beschriftung in der Navigation</label><input id="t-nav" name="navLabel" value="${E(t.navLabel)}" maxlength="40"></div>
    <div class="f f--half"><label for="t-h">Höhe</label><select id="t-h" name="height">${opt({ auto: 'Automatisch', screen: 'Vollbild (Bildschirmhöhe)' }, t.height)}</select></div>
    <div class="f f--half"><label for="t-al">Inhalt vertikal</label><select id="t-al" name="align"${t.height === 'screen' ? '' : ' disabled'}>${opt({ top: 'Oben', center: 'Mittig', bottom: 'Unten' }, t.align)}</select></div>
    <div class="f" role="group" aria-labelledby="t-bgi-l"><span class="f-label" id="t-bgi-l">Hintergrundbild</span>
      <div class="media-field" data-accept="image"><input type="hidden" name="bgImage" value="${E(t.bgImage || '')}">
      <div class="media-field-preview">${t.bgImage ? (bgThumb ? `<img src="${E(bgThumb)}" alt="" width="120">` : '') + '<span>Bild gewählt</span>' : '<span class="media-empty">Kein Bild gewählt</span>'}</div>
      <button type="button" class="btn btn--small" data-media-pick>Auswählen …</button> <button type="button" class="btn btn--small btn--ghost" data-media-clear>Entfernen</button></div></div>
    <div class="f"><label for="t-ov">Bild abdunkeln oder aufhellen</label><select id="t-ov" name="overlay">${opt({ none: 'Nein', dark: 'Abdunkeln (helle Schrift)', light: 'Aufhellen (dunkle Schrift)' }, t.overlay)}</select></div>
    ${t.row ? `<div class="f"><input type="hidden" name="row" value="${E(t.row)}"><p class="f-help" id="t-row-h">${E(T('Dieser Block steht noch in einer alten Reihe neben dem vorigen Block. Für Spalten gibt es jetzt den Block „Layout“ – die Umstellung erledigt php bin/console layout:migrate-rows.'))}</p>
      <button type="button" class="btn btn--small" data-row-clear aria-describedby="t-row-h">${E(T('Aus der Reihe lösen (eigener Abschnitt)'))}</button></div>` : ''}
    ${cfg.glossary ? `<div class="f"><label class="f-check"><input type="checkbox" name="noGlossary"${t.noGlossary ? ' checked' : ''} aria-describedby="t-gl-h"> <span>${E(CMSAdmin.t('Glossar-Begriffe hier nicht markieren'))}</span></label>
      <p class="f-help" id="t-gl-h">${E(CMSAdmin.t('Begriffe aus dem Glossar bekommen in diesem Abschnitt keine Erklärung zum Aufklappen (z. B. in Zitaten oder Werbetexten).'))}</p></div>` : ''}`;
  f.classList.add('adm-fields');
  CMSAdmin.init(f);
  f.oninput = f.onchange = () => {
    const tune = tunes.get(tool.blockId);
    const data = {
      background: f.background.value, anchor: f.anchor.value.trim().toLowerCase().replace(/[^a-z0-9-]+/g, '-'),
      visible: f.visible.checked, divider: f.divider.checked, showInNav: f.showInNav.checked,
      navLabel: f.navLabel.value, spaceTop: f.spaceTop.value, spaceBottom: f.spaceBottom.value,
      height: f.height.value, align: f.align.value, bgImage: +f.bgImage.value || null, overlay: f.overlay.value,
      row: f.row ? f.row.value : '',
      noGlossary: f.noGlossary ? f.noGlossary.checked : !!tune?.data?.noGlossary,
    };
    f.align.disabled = data.height !== 'screen';
    if (tune) tune.data = data;
    markDirty(); drawerTouched(); tool.refresh();
  };
  // Alte Reihe (Tune row) auflösen – die Option selbst gibt es nicht mehr (Block „Layout“)
  $('[data-row-clear]', f)?.addEventListener('click', () => {
    const tune = tunes.get(tool.blockId);
    if (tune) tune.data = { ...tune.data, row: '' };
    markDirty(); drawerTouched(); tool.refresh(); renderTuneForm(tool);
  });
}

function closeDrawer() {
  drawer.hidden = true; d.body.classList.remove('has-drawer');
  $$('.cms-block.is-active').forEach(b => b.classList.remove('is-active'));
  drawerFor = null; drawerSnap = null;
}
// Kopf der Seitenleiste: ohne Änderungen „Schließen“, mit Änderungen „Abbrechen“ (setzt den Block zurück) + „Fertig“
const drawerClose = $('[data-drawer-close]', drawer);
const drawerDone = d.createElement('button');
drawerDone.type = 'button'; drawerDone.className = 'adm-btn adm-btn--small adm-btn--primary'; drawerDone.hidden = true;
drawerDone.textContent = BT('done');
drawerClose.after(drawerDone);
drawerClose.parentElement.classList.add('cms-drawer__head--2');
function drawerButtons() {
  const t = !!drawerSnap?.touched;
  drawerClose.textContent = t ? BT('cancel') : BT('close');
  drawerDone.hidden = !t;
}
function drawerTouched() {
  if (drawerSnap && !drawerSnap.touched) { drawerSnap.touched = true; drawerButtons(); }
}
drawerDone.addEventListener('click', closeDrawer);
drawerClose.addEventListener('click', async () => {
  const s = drawerSnap;
  if (!s?.touched) { closeDrawer(); return; }
  const r = await Bar?.confirm({ title: BT('blockTitle'), body: BT('blockBody'), note: '', save: '' });
  if (r !== 'discard') return;
  s.tool.data = s.data;
  const tn = tunes.get(s.tool.blockId);
  if (s.tool.setTunes) s.tool.setTunes(s.tunes); else if (tn) tn.data = s.tunes;
  s.tool.loadPreview();
  if (!s.dirty) { dirty = false; Bar?.state('clean'); }
  closeDrawer();
});
d.addEventListener('keydown', e => { if (e.key === 'Escape' && drawerFor && !S.openDialog()) closeDrawer(); });

// ------------------------------------------------------------------ Kein automatischer Textblock beim Klick unter den letzten Block
/*
 * Editor.js legt bei einem Klick in die freie Fläche unter dem letzten Block einen neuen Standardblock (Text) an
 * (UI.processBottomZoneClick) – bei einem Verklicker entsteht so ein leerer Block. Blöcke kommen nur noch über
 * „+ Block einfügen“: Klicks, die nicht in einem Block landen, erreichen den Redaktionsbereich nicht.
 */
holder()?.addEventListener('click', e => {
  if (e.target.closest?.('.ce-block, .ce-toolbar, .ce-popover, .ce-inline-toolbar, .ce-settings')) return;
  if (e.target.closest?.('.codex-editor__redactor') || e.target.classList?.contains('codex-editor')) e.stopPropagation();
}, true);

// ------------------------------------------------------------------ Blöcke ziehen (Griff am Blocknamen)
const BlockDrag = {
  from: null, target: null, after: false,
  clear() { $$('.ce-block.is-drop-before, .ce-block.is-drop-after', holder()).forEach(b => b.classList.remove('is-drop-before', 'is-drop-after')); this.target = null; },
};
holder()?.addEventListener('dragover', e => {
  if (!BlockDrag.from) return;
  const b = e.target.closest?.('.ce-block');
  e.preventDefault(); e.stopPropagation();
  e.dataTransfer.dropEffect = 'move';
  if (!b) return;
  const r = b.getBoundingClientRect(), after = e.clientY > r.top + r.height / 2;
  if (BlockDrag.target === b && BlockDrag.after === after) return;
  BlockDrag.clear(); BlockDrag.target = b; BlockDrag.after = after;
  b.classList.add(after ? 'is-drop-after' : 'is-drop-before');
}, true);
holder()?.addEventListener('drop', e => {
  if (!BlockDrag.from) return;
  e.preventDefault(); e.stopPropagation();   // Editor.js soll nichts als Text einfügen
  const els = blockEls(), from = els.indexOf(BlockDrag.from.el.closest('.ce-block')), ti = els.indexOf(BlockDrag.target);
  const after = BlockDrag.after;
  BlockDrag.clear();
  if (from < 0 || ti < 0) return;
  let to = after ? ti + 1 : ti;
  if (from < to) to -= 1;
  if (to === from) return;
  editor.blocks.move(to, from);
  markDirty();
  requestAnimationFrame(refreshMoveButtons);
  editorStatus(CMSAdmin.t('Block verschoben – noch nicht gespeichert.'));
}, true);

// ------------------------------------------------------------------ Editor starten
const toolsCfg = { section: SectionTune };
for (const [type, def] of Object.entries(cfg.blocks)) toolsCfg[type] = { class: makeTool(type, def) };

editor = new EditorJS({
  holder: 'cms-editor',
  data: { blocks: initial.blocks || [] },
  tools: toolsCfg,
  tunes: ['section'],   // Abschnitts-Daten; das Menü am Griff ⠿ ist unser eigenes (BlockMenu)
  defaultBlock: cfg.blocks.richtext ? 'richtext' : Object.keys(cfg.blocks)[0],
  minHeight: 80,   // freie Fläche unter dem letzten Block (Standard 300 px)
  i18n: {
    messages: {
      ui: {
        blockTunes: { toggler: { 'Click to tune': 'Klicken: Block-Menü', 'or drag to move': 'ziehen: verschieben' } },
        toolbar: { toolbox: { Add: 'Block hinzufügen', Filter: 'Suchen', 'Nothing found': 'Nichts gefunden' } },
        popover: { Filter: 'Suchen', 'Nothing found': 'Nichts gefunden', 'Convert to': 'Umwandeln in' },
      },
      blockTunes: {
        delete: { Delete: 'Löschen', 'Click to delete': 'Zum Löschen klicken' },
        moveUp: { 'Move up': 'Nach oben' }, moveDown: { 'Move down': 'Nach unten' },
      },
    },
  },
  // Nur strukturelle Änderungen zählen (Inhalte melden die Tools selbst)
  onChange: (_api, ev) => {
    const evs = Array.isArray(ev) ? ev : [ev];
    if (evs.some(x => ['block-added', 'block-removed', 'block-moved'].includes(x?.type))) { markDirty(); requestAnimationFrame(refreshMoveButtons); }
    requestAnimationFrame(blankState);
  },
  onReady: () => {
    if (window.DragDrop) new DragDrop(editor, '3px solid #314164');
    refreshMoveButtons();
    blankState();
    dirty = false;
    Bar?.state('clean');
    requestAnimationFrame(() => jumpToBlock());
    // Ereignis für Werkzeuge/Erweiterungen: Seiten-Editor ist bereit
    CMSAdmin.events?.emit('cms:editor-ready', { kind: 'page', page: cfg.page, template: !!cfg.entry, entry: cfg.entry || null });
  },
});

// ------------------------------------------------------------------ Griff „+ ⠿“: erst nach kurzem Verweilen umsetzen (Hover-Intent)
/*
 * Editor.js setzt den Griff bei jeder Mausbewegung sofort an den Block unter dem Zeiger (watchBlockHoveredEvents auf dem
 * Redaktor). Der Griff sitzt über der Blockkante – auf dem Weg dorthin kreuzt die Maus oft den Block darüber, und der Griff
 * springt weg („+“ nicht erreichbar). Darum halten wir Mausbewegungen über einem ANDEREN Block zurück, bis der Zeiger dort
 * INTENT_MS stillsteht; über dem Griff selbst und im Korridor zwischen Block und Griff bleibt er immer stehen.
 */
(() => {
  const INTENT_MS = 320;
  const host = holder();
  if (!host) return;
  let current = null, pending = null, timer = 0, replay = false;
  const actions = () => host.querySelector('.ce-toolbar__actions');
  const inCorridor = (e, blk) => {
    const a = actions(); if (!a || !blk) return false;
    const r = a.getBoundingClientRect(), b = blk.getBoundingClientRect();
    if (!r.width) return false;
    // Band vom Griff bis zur Oberkante des Blocks, über die ganze Blockbreite
    return e.clientX >= Math.min(r.left, b.left) - 40 && e.clientX <= b.right + 40 && e.clientY >= r.top - 12 && e.clientY <= b.top + 24;
  };
  host.addEventListener('mousemove', e => {
    if (replay) return;
    if (e.target.closest?.('.ce-toolbar')) { clearTimeout(timer); pending = null; return; }
    const blk = e.target.closest?.('.ce-block');
    if (!blk || blk === current || !current || !current.isConnected) { if (blk) current = blk; clearTimeout(timer); pending = null; return; }
    // Anderer Block: zurückhalten – im Korridor zum Griff ganz, sonst bis zum Verweilen
    e.stopPropagation();
    if (inCorridor(e, current)) { clearTimeout(timer); pending = null; return; }
    // Verweilen statt Durchqueren: jede weitere Bewegung startet die Wartezeit neu (große Blöcke wie ein Hero liegen
    // oft auf dem Weg zum Griff des Blocks darunter)
    {
      pending = blk; clearTimeout(timer);
      const { clientX, clientY } = e, target = e.target;
      timer = setTimeout(() => {
        if (pending !== blk || !target.isConnected) return;
        current = blk; pending = null; replay = true;
        target.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX, clientY }));
        replay = false;
      }, INTENT_MS);
    }
  }, true);
  host.addEventListener('mouseleave', () => { clearTimeout(timer); pending = null; });
})();

// ------------------------------------------------------------------ Markdown importieren (Menü „⋯“ der Werkzeugleiste, CMSAdmin.Markdown aus _markdown.js)
/**
 * Textblock des Kits für importierte Texte: „richtext“, sonst „text“, sonst ein einfügbarer Block mit Rich-Text-Feld und sonst
 * keinen Pflichtfeldern (der kleinste gewinnt). Ergebnis { type, field, label } oder null.
 */
function textBlock() {
  const rich = def => (def.fields || []).find(f => f.type === 'richtext' && f.name === 'text') || (def.fields || []).find(f => f.type === 'richtext');
  const ok = (type, def) => def && def.insertable !== false && rich(def);
  for (const type of ['richtext', 'text']) if (ok(type, cfg.blocks[type])) return { type, field: rich(cfg.blocks[type]).name, label: cfg.blocks[type].label };
  const cand = Object.entries(cfg.blocks).filter(([type, def]) => ok(type, def) && !(def.fields || []).some(f => f.required && f !== rich(def)))
    .sort((a, b) => a[1].fields.length - b[1].fields.length)[0];
  return cand ? { type: cand[0], field: rich(cand[1]).name, label: cand[1].label } : null;
}
S.ui('[data-editor-md]')?.addEventListener('click', async () => {
  const M = CMSAdmin.Markdown, tb = textBlock(), T = CMSAdmin.t;
  const els = blockEls();
  const blockAt = i => tools.get(editor.blocks.getBlockByIndex(i)?.id);
  // Position: am Anfang, nach jedem Block (Standard: nach dem Block in der Seitenleiste bzw. am Ende)
  const positions = [['0', T('Am Anfang der Seite')], ...els.map((el, i) => {
    const tool = blockAt(i), sum = tool ? summarize(tool.data) : '';
    return [String(i + 1), T('Nach Block {n}: {label}', { n: i + 1, label: (tool?.def.label || '') + (sum ? ' – ' + sum.slice(0, 40) : '') })];
  })];
  const cur = drawerFor ? els.indexOf(drawerFor.el.closest('.ce-block')) : -1;
  const res = await M.openImport({
    box: layerBox(), page: true, title: T('Markdown importieren'), ok: T('Importieren'), block: tb?.label || '', returnFocus: S.ui('[data-bar-more]'),   // Menüpunkt ist danach verborgen – zurück zum Knopf „⋯“
    intro: T('Text einfügen oder eine .md-Datei wählen. Es entstehen Textblöcke als ungespeicherte Änderung – danach wie gewohnt speichern oder veröffentlichen.'),
    disabled: tb ? '' : T('Dieses Kit hat keinen Textblock mit formatiertem Text – Markdown lässt sich hier nicht importieren.'),
    positions, position: String(cur >= 0 ? cur + 1 : els.length),
  });
  if (!res || !tb) return;
  const { parts } = M.sections(res.text, res.split);
  if (!parts.length) return;
  let at = Math.max(0, Math.min(+res.position || 0, blockEls().length));
  // Leere Seite: der leere Startblock (gleicher Typ, ohne Text) wird ersetzt statt stehen zu bleiben
  const only = blockEls().length === 1 && blockAt(0);
  const replace = !!only && only.type === tb.type && !summarize(only.data);
  if (replace) at = 0;
  const made = parts.map((p, i) => editor.blocks.insert(tb.type, { [tb.field]: p.html }, undefined, at + i, false, replace && i === 0));
  markDirty();
  requestAnimationFrame(() => {
    refreshMoveButtons();
    const first = tools.get(made[0]?.id);
    first?.el.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    first?.bar.querySelector('.cms-block__edit')?.focus({ preventScroll: true });
    const st = S.ui('[data-editor-status]');   // Live-Region der Werkzeugleiste
    if (st) st.textContent = parts.length === 1 ? T('1 Textblock eingefügt – noch nicht gespeichert.') : T('{n} Textblöcke eingefügt – noch nicht gespeichert.', { n: parts.length });
  });
});

// ------------------------------------------------------------------ Sprung zu einem Block (z. B. aus „Platzhalter ersetzen“ der Übersicht)
// ?edit=1#b-{blockId} (oder ?block={blockId}, auch die eigene Sprungmarke des Blocks): Block aufklappen, in die Mitte scrollen,
// kurz hervorheben, [Platzhalter] darin markieren und – wenn neben dem Block Platz ist – die Felder in der Seitenleiste öffnen
const PLACEHOLDER_RX = /\[\p{L}[^\][]{2,}\](?!\()/gu;   // wie Core\Dashboard\Metrics::PLACEHOLDER_RX
function jumpToBlock() {
  let hash = '';
  try { hash = decodeURIComponent(location.hash.slice(1)); } catch {}
  const id = new URLSearchParams(location.search).get('block') || (hash.startsWith('b-') ? hash.slice(2) : '');
  const tool = (id && tools.get(id)) || (hash && [...tools.values()].find(t => t.tuneData.anchor && t.tuneData.anchor === hash));
  if (!tool?.el) return;
  const el = tool.el, calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (el.classList.contains('is-collapsed')) tool.toggleCollapse(false, false);
  markPlaceholders(el);
  const go = () => el.scrollIntoView({ block: 'center', behavior: calm ? 'auto' : 'smooth' });
  go();
  // Bilder darüber verschieben die Lage noch – nach dem Laden erneut ausrichten, solange niemand selbst gescrollt hat
  if (d.readyState !== 'complete') {
    let moved = false;
    const stop = () => { moved = true; };
    addEventListener('wheel', stop, { once: true, passive: true }); addEventListener('touchmove', stop, { once: true, passive: true });
    addEventListener('load', () => { if (!moved) el.scrollIntoView({ block: 'center' }); }, { once: true });
  }
  el.classList.add('is-target');
  setTimeout(() => el.classList.remove('is-target'), calm ? 4000 : 2600);
  // Seitenleiste nur, wenn sie den Block nicht verdeckt (ab 1100 px rückt die Seite zur Seite, siehe editor.css)
  if (matchMedia('(min-width: 1101px)').matches) tool.openDrawer();
}
/** [Platzhalter] im Block farbig markieren (CSS Custom Highlight API, ändert das DOM nicht – bearbeitbare Texte bleiben unberührt) */
function markPlaceholders(el) {
  if (!window.Highlight || !CSS.highlights) return;
  const ranges = [];
  const walk = d.createTreeWalker(el.querySelector('.cms-block__preview') || el, NodeFilter.SHOW_TEXT);
  for (let n = walk.nextNode(); n; n = walk.nextNode()) {
    if (!n.data.includes('[')) continue;
    for (const m of n.data.matchAll(PLACEHOLDER_RX)) {
      const r = new Range();
      r.setStart(n, m.index); r.setEnd(n, m.index + m[0].length);
      ranges.push(r);
    }
  }
  if (ranges.length) CSS.highlights.set('cms-placeholder', new Highlight(...ranges));
}

async function save(publish = false) {
  Bar?.state(publish ? 'publishing' : 'saving');
  try {
    const out = await editor.save();
    const blocks = blankState() ? [] : out.blocks.map(b => ({ ...b, tunes: { section: tunes.get(b.id)?.data || b.tunes?.section || {} } }));
    // cms:before-save: Werkzeuge/Erweiterungen dürfen prüfen und abbrechen (auch asynchron, detail.waitUntil)
    const gate = await (CMSAdmin.events?.beforeSave({ kind: 'page', publish, page: cfg.page, blocks }) ?? { ok: true });
    if (!gate.ok) {
      Bar?.state('dirty', gate.reason || '');
      if (gate.reason) alert(gate.reason);
      return false;
    }
    const res = await api(cfg.endpoints.save, { blocks, publish });
    dirty = false;
    Bar?.state(publish ? 'published' : 'saved', (publish ? BT('published') : BT('saved')).replace('{time}', res.saved_at));
    const ev = { kind: 'page', page: cfg.page, publish, savedAt: res.saved_at || '' };
    CMSAdmin.events?.emit('cms:saved', ev);
    if (publish) { CMSAdmin.events?.emit('cms:published', ev); CMSAdmin.events?.emit('cms:status-changed', { kind: 'page', page: cfg.page, status: 'published' }); }
    return true;
  } catch (e) {
    Bar?.state('error');
    alert('Speichern fehlgeschlagen: ' + e.message);
    return false;
  }
}
// ------------------------------------------------------------------ Rückgängig / Wiederholen
/*
 * Verlauf der Seite im Browser (nicht gespeichert, max. 50 Schritte): nach jeder Änderung (markDirty, gebündelt nach 600 ms –
 * Tippen ergibt einen Schritt) ein Stand aller Blöcke (Daten + Abschnitts-Einstellungen). Rückgängig zeichnet den Editor aus dem
 * Stand neu (editor.render): Blöcke mit unveränderten Daten behalten ihre Vorschau, nur geänderte holt der Server neu.
 * Tasten: ⌘/Strg+Z, ⇧⌘Z bzw. Strg+Y – nicht in Textfeldern/direkt bearbeitetem Text (dort gilt das Rückgängig des Browsers).
 * Knöpfe in der Werkzeugleiste: [data-editor-undo], [data-editor-redo].
 */
History = (() => {
  const MAX = 50;
  let past = [], future = [], cur = null, timer = 0, busy = false, ready = false;
  const ub = S.uiAll('[data-editor-undo]'), rb = S.uiAll('[data-editor-redo]');
  const key = s => JSON.stringify(s.map(b => [b.id, b.type, b.data, b.tunes]));
  const snap = async () => {
    const out = await editor.save();
    return out.blocks.map(b => ({ id: b.id, type: b.type, data: structuredClone(b.data), tunes: { section: structuredClone(tunes.get(b.id)?.data || b.tunes?.section || {}) } }));
  };
  const buttons = () => {
    ub.forEach(b => b.setAttribute('aria-disabled', past.length ? 'false' : 'true'));
    rb.forEach(b => b.setAttribute('aria-disabled', future.length ? 'false' : 'true'));
  };
  const record = async () => {
    clearTimeout(timer); timer = 0;
    if (busy || !ready) return;
    const s = await snap();
    if (cur && key(s) === key(cur)) return;
    if (cur) { past.push(cur); if (past.length > MAX) past.shift(); }
    cur = s; future = [];
    buttons();
  };
  const apply = async (target) => {
    busy = true;
    try {
      if (drawerFor) closeDrawer();
      // Vorschau unveränderter Blöcke übernehmen, geänderte neu laden (kein Eintrag in previews → loadPreview)
      const now = new Map((cur || []).map(b => [b.id, b]));
      for (const b of target) {
        const was = now.get(b.id);
        const html = tools.get(b.id)?.el?.querySelector('.cms-block__preview')?.innerHTML;
        if (was && html != null && JSON.stringify(was.data) === JSON.stringify(b.data) && was.type === b.type) previews[b.id] = html;
        else delete previews[b.id];
        initialTunes[b.id] = structuredClone(b.tunes.section || {});
      }
      tools.clear(); tunes.clear();
      await editor.render({ blocks: target.map(b => ({ id: b.id, type: b.type, data: structuredClone(b.data), tunes: structuredClone(b.tunes) })) });
      cur = target;
      dirty = true; Bar?.state('dirty');
      requestAnimationFrame(() => { refreshMoveButtons(); blankState(); });
    } finally {
      setTimeout(() => { busy = false; }, 0);
      buttons();
    }
  };
  const undo = async () => {
    if (timer) await record();
    if (!past.length || busy) return;
    future.push(cur);
    await apply(past.pop());
    editorStatus(CMSAdmin.t('Rückgängig gemacht – noch nicht gespeichert.'));
  };
  const redo = async () => {
    if (!future.length || busy) return;
    past.push(cur);
    await apply(future.pop());
    editorStatus(CMSAdmin.t('Wiederhergestellt – noch nicht gespeichert.'));
  };
  ub.forEach(b => b.addEventListener('click', () => b.getAttribute('aria-disabled') !== 'true' && undo()));
  rb.forEach(b => b.addEventListener('click', () => b.getAttribute('aria-disabled') !== 'true' && redo()));
  S.uiAll('[data-editor-history]').forEach(g => { g.hidden = false; });
  d.addEventListener('keydown', e => {
    if (!(e.metaKey || e.ctrlKey) || e.altKey) return;
    const k = e.key.toLowerCase();
    const isUndo = k === 'z' && !e.shiftKey, isRedo = (k === 'z' && e.shiftKey) || (k === 'y' && e.ctrlKey && !e.metaKey);
    if (!isUndo && !isRedo) return;
    const tgt = e.composedPath()[0];
    if (tgt && (tgt.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(tgt.tagName || ''))) return;   // Text: Rückgängig des Browsers
    if (S.openDialog?.() || d.body.classList.contains('has-epanel')) return;
    e.preventDefault(); e.stopPropagation();
    isUndo ? undo() : redo();
  }, true);   // Erfassungsphase: Editor.js hält Tasten aus Blöcken sonst zurück
  // Ausgangsstand, sobald Editor.js bereit ist (danach zählt jede Änderung)
  editor.isReady.then(() => requestAnimationFrame(async () => { cur = await snap(); ready = true; buttons(); }));
  return {
    touch() { if (busy || !ready) return; clearTimeout(timer); timer = setTimeout(record, 600); },
  };
})();

// Vorschau: ungespeicherte Änderungen vorher als Entwurf sichern, damit die Vorschau sie zeigt
S.ui('[data-editor-preview]')?.addEventListener('click', async e => {
  e.preventDefault();
  const href = e.currentTarget.href;
  if (dirty && !(await save(false))) return;
  location.href = href;
});
// Kompaktansicht: alle Blöcke als schmale Zeilen – ideal zum Umsortieren
const compactBtn = S.ui('[data-editor-compact]');
const setCompact = on => {
  holder().classList.toggle('is-compact', on);
  compactBtn?.setAttribute(compactBtn.getAttribute('role') === 'menuitemcheckbox' ? 'aria-checked' : 'aria-pressed', on ? 'true' : 'false');
  store.set('cms-compact', on);
};
compactBtn?.addEventListener('click', () => setCompact(!holder().classList.contains('is-compact')));
setCompact(store.get('cms-compact', false));
// Alt/⌥ + ↑/↓ verschiebt den bearbeiteten Block (Seitenleiste offen) bzw. den Block unter der Maus / mit dem Fokus.
// In Textfeldern bleibt ⌥+Pfeil die Cursor-Bewegung (macOS), solange keine Seitenleiste offen ist.
d.addEventListener('keydown', e => {
  if (!e.altKey || e.metaKey || e.ctrlKey || !['ArrowUp', 'ArrowDown'].includes(e.key)) return;
  const tgt = e.composedPath()[0];
  const typing = tgt && (tgt.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(tgt.tagName || ''));
  let t = drawerFor?.move ? drawerFor : null;
  if (!t) {
    if (typing) return;
    const host = tgt?.getRootNode?.()?.host;   // Fokus in der Blockleiste (Shadow DOM)
    const b = (host || tgt)?.closest?.('.ce-block') || hoveredBlockEl;
    t = b && tools.get(b.dataset.id);
  }
  if (!t) return;
  e.preventDefault();
  t.move(e.key === 'ArrowUp' ? -1 : 1);
});
S.ui('[data-editor-save]')?.addEventListener('click', () => save(false));
S.ui('[data-editor-discard]')?.addEventListener('click', async () => {
  if (!(await Bar.ask({ title: BT('dropTitle'), body: BT('dropBody'), ok: BT('dropOk'), danger: true }))) return;
  try {
    await api(cfg.endpoints.discard, {});
    dirty = false;
    location.reload();
  } catch (e) { alert('Verwerfen nicht möglich: ' + e.message); }
});
// Alle „Veröffentlichen“-Knöpfe (Desktop-Leiste und Aktionsleiste unten auf Telefonen)
S.uiAll('[data-editor-publish]').forEach(b => b.addEventListener('click', async () => {
  if (await Bar.ask({ title: BT('publishTitle'), body: BT('publishBody'), ok: BT('publishOk'), danger: false })) save(true);
}));
// ⌘/Strg+S: Erfassungsphase – Textfelder im Block halten Tasten sonst von Editor.js (und damit auch von hier) fern
d.addEventListener('keydown', e => { if ((e.metaKey || e.ctrlKey) && !e.altKey && e.key.toLowerCase() === 's' && !d.body.classList.contains('has-epanel')) { e.preventDefault(); save(false); } }, true);
addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
// „Abbrechen“ (Werkzeugleiste, Esc, Modus „Ansehen“): Seite ohne Editor laden – der zuletzt gespeicherte Entwurf bleibt,
// ungespeicherte Änderungen gibt es nur im Browser und sie verschwinden damit
Bar?.use({
  dirty: () => dirty,
  save: () => save(false),
  discard: () => { dirty = false; },
  exit: href => { dirty = false; location.href = href || Bar.viewUrl || location.pathname; },
});
