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
 */
const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const cfg = JSON.parse($('#cms-editor-config').textContent);
// Shadow DOM (resources/js/_shadow.js über window.CMSAdmin): Werkzeugleiste, Ebene für Seitenleiste/Leisten, Block-Leisten
const S = CMSAdmin.shadow;
// Blocksymbol: Symbol aus dem Sprite (def.ico, vom Server aufgelöst), sonst Zeichen aus theme.php
const blockIcon = def => (def.ico && CMSAdmin.ico ? CMSAdmin.ico(def.ico) : CMSAdmin.esc(def.icon || '▦'));
const layerBox = () => S.layerBox();
// Seitenleiste „Block“ (serverseitig im Dokument gerendert) in die Ebene verschieben – Theme-CSS wirkt dort nicht
const drawer = $('#cms-drawer');
layerBox().append(drawer);
const initial = JSON.parse($('#cms-editor-data').textContent);
const previews = initial.previews || {};
const tools = new Map();      // blockId → Tool-Instanz
const tunes = new Map();      // blockId → Tune-Instanz
const initialTunes = Object.fromEntries((initial.blocks || []).map(b => [b.id, b.tunes?.section || {}]));
const TUNE_DEFAULTS = { background: 'white', anchor: '', visible: true, showInNav: false, navLabel: '', spaceTop: 'normal', spaceBottom: 'normal', divider: false, height: 'auto', bgImage: null, overlay: 'none', align: 'center', row: '', noGlossary: false };
let editor, dirty = false, drawerFor = null, drawerSnap = null;
const store = { get(k, d) { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch { return d; } }, set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch {} } };
const COLLAPSE_KEY = 'cms-collapsed-' + cfg.page.id;
const collapsed = new Set(store.get(COLLAPSE_KEY, []));
const holder = () => $('#cms-editor');
const blockEls = () => $$('.ce-block', holder());
/** Kurzbeschreibung eines Blocks für die eingeklappte Zeile */
const summarize = data => {
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
function layoutRows() {
  if (!cfg.rows) return;
  const groups = [];
  blockEls().forEach((ce, i) => {
    const tool = tools.get(ce.dataset.id), row = tool?.tuneData?.row || '';
    const x = { ce, el: tool?.el, row, raw: !!tool?.def?.raw };
    const last = groups[groups.length - 1];
    if (row && i > 0 && !x.raw && !last[0].raw) last.push(x); else groups.push([x]);
  });
  for (const g of groups) {
    const spans = rowSpans(g.map(x => x.row));
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
    const up = sr && $('[data-move="up"]', sr), down = sr && $('[data-move="down"]', sr);
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
const markDirty = () => { const was = dirty; dirty = true; if (!was) Bar?.state('dirty'); };

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
  $$('.cms-block.is-selected', holder()).forEach(b => { if (b !== el) b.classList.remove('is-selected'); });
  el?.classList.add('is-selected');
  if (el) BarPlace.soon();
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
  const HARD = '[data-edit],input,select,textarea,button,.btn,[role=button]';
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
      for (const x of pv.querySelectorAll('.cms-entry-pencil')) hard.push(R(x.getBoundingClientRect()));
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
  const items = () => $$('.cms-addpop__item:not([hidden])', list);
  let tool = null, opener = null;
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
    $$('.cms-addpop__item', list).forEach(b => { b.hidden = !!v && !b.textContent.toLowerCase().includes(v) && !b.dataset.type.includes(v); });
    none.hidden = items().length > 0;
    mark(items()[0]);
    place();
  };
  const close = (back = true) => {
    if (el.hidden) return;
    el.hidden = true;
    if (back) opener?.focus({ preventScroll: true });
    opener = null; tool = null;
  };
  const insert = type => {
    const t = tool;
    close(false);
    const idx = blockEls().indexOf(t?.el.closest('.ce-block'));
    if (!type || idx < 0) return;
    editor.blocks.insert(type, {}, undefined, idx + 1, false);
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
    open(btn, t) {
      if (!el.hidden && opener === btn) { close(); return; }
      opener = btn; tool = t;
      q.value = ''; filter();
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

    render() {
      const el = d.createElement('div');
      el.className = 'cms-block';
      // Block-Leiste: eigenes Shadow DOM (Knöpfe unabhängig von Theme-Regeln für button, font, line-height …)
      el.innerHTML = '<div class="cms-block__bar" contenteditable="false"></div><div class="cms-block__preview"></div>';
      const barEl = el.firstElementChild;
      const sr = this.bar = S.shadowFor(barEl, `
          <span class="cms-block__swatch" aria-hidden="true"></span>
          <span class="cms-block__label" title="${CMSAdmin.esc(def.label)}"><span aria-hidden="true">${blockIcon(def)}</span><span class="cms-block__name"> ${CMSAdmin.esc(def.label)}</span></span>
          <span class="cms-block__summary"></span>
          <span class="cms-block__flags"></span>
          <span class="cms-block__tools">
            <button type="button" class="cms-iconbtn" data-move="up" aria-label="Block nach oben" title="Nach oben (Alt+↑)">↑</button>
            <button type="button" class="cms-iconbtn" data-move="down" aria-label="Block nach unten" title="Nach unten (Alt+↓)">↓</button>
            <button type="button" class="cms-iconbtn" data-collapse aria-expanded="true" aria-label="Block einklappen" title="Einklappen / Ausklappen">▾</button>
          </span>
          <span class="cms-block__hint" hidden></span>
          ${def.formfields ? `<button type="button" class="cms-block__fields" hidden>${CMSAdmin.esc(CMSAdmin.t('Felder'))}<span class="cms-block__fields-more"> ${CMSAdmin.esc(CMSAdmin.t('bearbeiten'))}</span></button>` : ''}
          <button type="button" class="cms-block__edit">Bearbeiten</button>`);
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
      sr.querySelector('[data-move="up"]').addEventListener('click', e => { e.stopPropagation(); this.move(-1); });
      sr.querySelector('[data-move="down"]').addEventListener('click', e => { e.stopPropagation(); this.move(1); });
      sr.querySelector('[data-collapse]').addEventListener('click', e => { e.stopPropagation(); this.toggleCollapse(); });
      // Eingeklappte Zeile: Klick auf den Titel klappt auf
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
        if (e.target.closest('[data-cms-open]')) { this.openDrawer(); return; }
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
            if (drawerFor === this) { this.syncDrawerField(n.dataset.edit, v); drawerTouched(); }
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
            if (drawerFor === this) { this.syncDrawerField(n.dataset.edit, html, true); drawerTouched(); }
          });
          n.addEventListener('focus', () => InlineBar.show(n, mode));
          n.addEventListener('blur', () => InlineBar.hideSoon());
        }
      });
      $$('input,select,textarea,button:not(.cms-block__edit)', pv).forEach(i => { i.tabIndex = -1; });
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
        const res = await api(cfg.endpoints.preview, { page: cfg.page.id, entry: cfg.entry, block: this.serialize() });
        if (!Object.keys(this.data).length) this.data = res.block.data; // neuer Block → Standardwerte übernehmen
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
        (btn && !btn.disabled ? btn : this.bar.querySelector('[data-collapse]')).focus({ preventScroll: true });
        this.el.classList.remove('is-moved'); void this.el.offsetWidth; this.el.classList.add('is-moved');
      });
    }

    toggleCollapse(force, remember = true) {
      const on = force ?? !this.el.classList.contains('is-collapsed');
      this.el.classList.toggle('is-collapsed', on);
      const b = this.bar.querySelector('[data-collapse]');
      b.setAttribute('aria-expanded', on ? 'false' : 'true');
      b.setAttribute('aria-label', on ? 'Block ausklappen' : 'Block einklappen');
      b.textContent = on ? '▸' : '▾';
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

    syncDrawerField(path, value, rich = false) {
      const name = 'f[' + path.split('.').join('][') + ']';
      const input = drawer.querySelector(`[name="${CSS.escape(name)}"]`);
      if (!input) return;
      input.value = value;
      const area = rich && input.closest('.rte')?.querySelector('.rte-area');
      if (area && !area.contains(d.activeElement)) area.innerHTML = value;
    }

    async openDrawer(focusSection = false) {
      // Stand beim Öffnen merken: „Abbrechen“ in der Seitenleiste setzt nur diesen Block zurück
      if (drawerSnap?.tool !== this || drawer.hidden) {
        drawerSnap = { tool: this, data: structuredClone(this.data), tunes: structuredClone(this.tuneData), dirty, touched: false };
        drawerButtons();
      }
      drawerFor = this;
      const form = $('[data-drawer-form]', drawer), central = $('[data-drawer-central]', drawer);
      $('#cms-drawer-title', drawer).textContent = def.label;
      central.hidden = !def.central;
      if (def.central) central.innerHTML = `${CMSAdmin.esc(def.central)} <a href="${cfg.endpoints.settings}" target="_blank" rel="noopener">Zentral gepflegt → ${CMSAdmin.esc(cfg.settingsTitle || 'Einstellungen')} ↗</a>`;
      form.innerHTML = '<p class="adm-muted">Lade Felder …</p>';
      drawer.hidden = false; d.body.classList.add('has-drawer');
      $$('.cms-block.is-active').forEach(b => b.classList.remove('is-active'));
      this.el.classList.add('is-active');
      selectBlock(this.el);
      renderTuneForm(this);
      if (focusSection) { const s = $('.cms-drawer__section', drawer); s.open = true; s.scrollIntoView(); }
      const res = await api(cfg.endpoints.form, { type: this.type, data: this.data, entry: cfg.entry });
      if (drawerFor !== this) return;
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
        const fx = this.data._fx, fit = this.data._fit;   // Bildanpassungen und Rahmen stehen nicht im Formular
        this.data = formToObject(form);
        if (fx) this.data._fx = fx;
        if (fit) this.data._fit = fit;
        markDirty(); drawerTouched(); this.refresh();
        fxFieldButtons(form, this);
      };
      form.oninput = onChange; form.onchange = onChange;
      fxFieldButtons(form, this);
      if (!focusSection) $('input:not([type=hidden]),select,textarea,[contenteditable]', form)?.focus({ preventScroll: true });
    }
  };
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
  fx: {
    target(img) {
      const el = img.closest('.cms-block');
      const tool = [...tools.values()].find(x => x.el === el);
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
window.CMSEditor.fit = {
  target(img) {
    const el = img.closest('.cms-block');
    const tool = [...tools.values()].find(x => x.el === el);
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
  const t = tool.tuneData, bgs = cfg.backgrounds, E = CMSAdmin.esc;
  const opt = (o, v) => Object.entries(o).map(([k, l]) => `<option value="${E(k)}"${k === v ? ' selected' : ''}>${E(l)}</option>`).join('');
  const sp = { normal: 'Normal', small: 'Klein', none: 'Kein' };
  const rowOpts = { '': CMSAdmin.t('Nein – eigener Abschnitt'), auto: CMSAdmin.t('Ja – Breite automatisch'),
    ...Object.fromEntries(['1-2', '1-3', '2-3', '1-4', '3-4'].map(k => [k, CMSAdmin.t('Ja – {w} Breite', { w: ROW_LABEL[k] })])) };
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
    ${cfg.rows && !tool.def?.raw ? `<div class="f"><label for="t-row">${E(CMSAdmin.t('Neben den vorigen Block stellen'))}</label><select id="t-row" name="row" aria-describedby="t-row-h">${opt(rowOpts, t.row || '')}</select>
      <p class="f-help" id="t-row-h">${E(CMSAdmin.t('Breite dieses Blocks; der vorige Block bekommt den Rest. Hintergrund, Abstände, Trennlinie und Hintergrundbild kommen vom ersten Block der Reihe – ein anderer Hintergrund macht diesen Block zur Karte. Auf schmalen Bildschirmen stehen die Blöcke untereinander.'))}</p></div>` : ''}
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
      row: f.row ? f.row.value : (tune?.data?.row || ''),
      noGlossary: f.noGlossary ? f.noGlossary.checked : !!tune?.data?.noGlossary,
    };
    f.align.disabled = data.height !== 'screen';
    if (tune) tune.data = data;
    markDirty(); drawerTouched(); tool.refresh();
  };
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
  if (tn) tn.data = s.tunes;
  s.tool.loadPreview();
  if (!s.dirty) { dirty = false; Bar?.state('clean'); }
  closeDrawer();
});
d.addEventListener('keydown', e => { if (e.key === 'Escape' && drawerFor && !S.openDialog()) closeDrawer(); });

// ------------------------------------------------------------------ Editor starten
const toolsCfg = { section: SectionTune };
for (const [type, def] of Object.entries(cfg.blocks)) toolsCfg[type] = { class: makeTool(type, def) };

editor = new EditorJS({
  holder: 'cms-editor',
  data: { blocks: initial.blocks || [] },
  tools: toolsCfg,
  tunes: ['section'],
  defaultBlock: cfg.blocks.richtext ? 'richtext' : Object.keys(cfg.blocks)[0],
  i18n: {
    messages: {
      ui: {
        blockTunes: { toggler: { 'Click to tune': 'Klicken für Optionen', 'or drag to move': 'oder ziehen zum Verschieben' } },
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
  },
  onReady: () => {
    if (window.DragDrop) new DragDrop(editor, '3px solid #314164');
    refreshMoveButtons();
    dirty = false;
    Bar?.state('clean');
    requestAnimationFrame(() => jumpToBlock());
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
    const blocks = out.blocks.map(b => ({ ...b, tunes: { section: tunes.get(b.id)?.data || b.tunes?.section || {} } }));
    const res = await api(cfg.endpoints.save, { blocks, publish });
    dirty = false;
    Bar?.state(publish ? 'published' : 'saved', (publish ? BT('published') : BT('saved')).replace('{time}', res.saved_at));
    return true;
  } catch (e) {
    Bar?.state('error');
    alert('Speichern fehlgeschlagen: ' + e.message);
    return false;
  }
}
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
// Alt + ↑/↓ verschiebt den gerade bearbeiteten Block
d.addEventListener('keydown', e => {
  if (!e.altKey || !drawerFor || !['ArrowUp', 'ArrowDown'].includes(e.key)) return;
  e.preventDefault();
  drawerFor.move(e.key === 'ArrowUp' ? -1 : 1);
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
