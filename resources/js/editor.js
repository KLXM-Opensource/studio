/*
 * Inline-Editor (Core, Theme-unabhängig) auf Basis von Editor.js.
 *
 *  - Jeder Blocktyp des Themes wird automatisch ein Editor.js-Tool (aus dem Feld-Schema).
 *  - WYSIWYG: Blöcke zeigen die serverseitig gerenderte Vorschau mit den Frontend-Styles.
 *  - Texte mit [data-edit] sind direkt im Frontend editierbar.
 *  - Seitenleiste: alle Felder (Formular vom Server, gleicher Renderer wie im Admin).
 *  - Block-Tune „Abschnitt“: Hintergrund, Anker, Sichtbarkeit, Navigation, Abstände.
 *  - Drag & Drop über editorjs-drag-drop.
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
const TUNE_DEFAULTS = { background: 'white', anchor: '', visible: true, showInNav: false, navLabel: '', spaceTop: 'normal', spaceBottom: 'normal', divider: false, height: 'auto', bgImage: null, overlay: 'none', align: 'center' };
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
/** Pfeile am Anfang/Ende deaktivieren */
function refreshMoveButtons() {
  const els = blockEls();
  els.forEach((b, i) => {
    const sr = b.querySelector('.cms-block__bar')?.shadowRoot;
    const up = sr && $('[data-move="up"]', sr), down = sr && $('[data-move="down"]', sr);
    if (up) up.disabled = i === 0;
    if (down) down.disabled = i === els.length - 1;
  });
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
          <span class="cms-block__label"><span aria-hidden="true">${blockIcon(def)}</span> ${CMSAdmin.esc(def.label)}</span>
          <span class="cms-block__summary"></span>
          <span class="cms-block__flags"></span>
          <span class="cms-block__tools">
            <button type="button" class="cms-iconbtn" data-move="up" aria-label="Block nach oben" title="Nach oben (Alt+↑)">↑</button>
            <button type="button" class="cms-iconbtn" data-move="down" aria-label="Block nach unten" title="Nach unten (Alt+↓)">↓</button>
            <button type="button" class="cms-iconbtn" data-collapse aria-expanded="true" aria-label="Block einklappen" title="Einklappen / Ausklappen">▾</button>
          </span>
          <button type="button" class="cms-block__edit">Bearbeiten</button>`);
      this.el = el;
      sr.querySelector('.cms-block__edit').addEventListener('click', e => { e.stopPropagation(); this.openDrawer(); });
      sr.querySelector('[data-move="up"]').addEventListener('click', e => { e.stopPropagation(); this.move(-1); });
      sr.querySelector('[data-move="down"]').addEventListener('click', e => { e.stopPropagation(); this.move(1); });
      sr.querySelector('[data-collapse]').addEventListener('click', e => { e.stopPropagation(); this.toggleCollapse(); });
      // Eingeklappte Zeile: Klick auf den Titel klappt auf
      sr.querySelector('.cms-block__summary').addEventListener('click', () => this.toggleCollapse(false));
      // Editor.js soll Tasten in der Leiste (Enter/Leertaste auf Knöpfen) nicht als Texteingabe behandeln
      barEl.addEventListener('keydown', e => e.stopPropagation());
      if (collapsed.has(this.blockId)) this.toggleCollapse(true, false);
      const pv = el.querySelector('.cms-block__preview');
      // Links/Formulare in der Vorschau nicht auslösen; Klick öffnet die Felder
      pv.addEventListener('click', e => {
        if (e.target.closest('[data-edit]') || e.target.closest('summary')) return;
        e.preventDefault();
        if (e.target.closest('[data-central]')) { this.showCentral(); return; }
        this.openDrawer();
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
      ].filter(Boolean).join('');
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
            setPath(this.data, n.dataset.edit, n.textContent);
            markDirty();
            if (drawerFor === this) { this.syncDrawerField(n.dataset.edit, n.textContent); drawerTouched(); }
          });
          n.addEventListener('paste', e => { e.preventDefault(); d.execCommand('insertText', false, e.clipboardData.getData('text/plain')); });
          n.addEventListener('focus', () => InlineBar.hide());
        } else {
          n.contentEditable = 'true';
          n.classList.add('is-rich');
          if (!n.innerHTML.trim()) n.innerHTML = mode === 'rich' ? '<p><br></p>' : '';
          CMSAdmin.Rich.bindKeys(n, mode);
          n.addEventListener('input', () => {
            const html = n.innerHTML.replace(/^<p><br><\/p>$/, '');
            setPath(this.data, n.dataset.edit, html);
            markDirty();
            if (drawerFor === this) { this.syncDrawerField(n.dataset.edit, html, true); drawerTouched(); }
          });
          n.addEventListener('focus', () => InlineBar.show(n, mode));
          n.addEventListener('blur', () => InlineBar.hideSoon());
        }
      });
      $$('input,select,textarea,button:not(.cms-block__edit)', pv).forEach(i => { i.tabIndex = -1; });
    }

    async loadPreview() {
      try {
        const res = await api(cfg.endpoints.preview, { page: cfg.page.id, entry: cfg.entry, block: this.serialize() });
        if (!Object.keys(this.data).length) this.data = res.block.data; // neuer Block → Standardwerte übernehmen
        this.setPreview(res.html);
        const pv = this.el.querySelector('.cms-block__preview');
        if (!pv.textContent.trim()) pv.insertAdjacentHTML('beforeend', `<p class="cms-empty">${CMSAdmin.esc(def.label)} – noch leer. Klicken Sie hier, um Inhalte einzugeben.</p>`);
        if (this.isNew) { this.isNew = false; markDirty(); this.openDrawer(); }
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
      if (remember) { on ? collapsed.add(this.blockId) : collapsed.delete(this.blockId); store.set(COLLAPSE_KEY, [...collapsed]); }
    }

    serialize() { return { id: this.blockId, type: this.type, data: this.data, tunes: { section: this.tuneData } }; }

    save() { return this.data; }

    showCentral() {
      this.openDrawer();
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
        this.data = formToObject(form);
        markDirty(); drawerTouched(); this.refresh();
      };
      form.oninput = onChange; form.onchange = onChange;
      if (!focusSection) $('input:not([type=hidden]),select,textarea,[contenteditable]', form)?.focus({ preventScroll: true });
    }
  };
}

// ------------------------------------------------------------------ Abschnitt-Formular in der Seitenleiste
function renderTuneForm(tool) {
  const f = $('[data-drawer-tunes]', drawer);
  const t = tool.tuneData, bgs = cfg.backgrounds, E = CMSAdmin.esc;
  const opt = (o, v) => Object.entries(o).map(([k, l]) => `<option value="${E(k)}"${k === v ? ' selected' : ''}>${E(l)}</option>`).join('');
  const sp = { normal: 'Normal', small: 'Klein', none: 'Kein' };
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
    <div class="f"><label for="t-ov">Bild abdunkeln oder aufhellen</label><select id="t-ov" name="overlay">${opt({ none: 'Nein', dark: 'Abdunkeln (helle Schrift)', light: 'Aufhellen (dunkle Schrift)' }, t.overlay)}</select></div>`;
  f.classList.add('adm-fields');
  CMSAdmin.init(f);
  f.oninput = f.onchange = () => {
    const tune = tunes.get(tool.blockId);
    const data = {
      background: f.background.value, anchor: f.anchor.value.trim().toLowerCase().replace(/[^a-z0-9-]+/g, '-'),
      visible: f.visible.checked, divider: f.divider.checked, showInNav: f.showInNav.checked,
      navLabel: f.navLabel.value, spaceTop: f.spaceTop.value, spaceBottom: f.spaceBottom.value,
      height: f.height.value, align: f.align.value, bgImage: +f.bgImage.value || null, overlay: f.overlay.value,
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
  },
});

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
