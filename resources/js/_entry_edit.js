/*
 * Einträge direkt auf der Website bearbeiten (Core\Data\EntryEdit, nur für angemeldete Redaktion – Teil von admin.js).
 *
 *  - [data-entry-edit="<endpoint>"]  Stift in Datenlisten, „Eintrag bearbeiten“, „+ Neuer Eintrag“ → Seitenleiste mit
 *    allen Feldern (Formular vom Server, gleiche Widgets wie in der Verwaltung), Speichern/Veröffentlichen/Als Entwurf/Verwerfen.
 *  - [data-entry-field][data-entry-mode]  Felder des Eintrags der Detailseite direkt im Text (plain | lines | rich),
 *    gespeichert über die Werkzeugleiste (#cms-entry-config) – ausdrücklich, wie im Seiten-Editor (Einträge haben keinen Entwurfsstand).
 *  - Nach dem Speichern: Seite neu laden, Scroll-Position bleibt.
 */
import { conditions } from './_conditions.js';
import { ui, uiAll, layerBox, barHost, openDialog, addRoot, pathClosest, setUiCss, deepActive } from './_shadow.js';
import { ico } from './_icons.js';
import { bar_ as Bar, barState, confirmDiscard, ask } from './_bar.js';

const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const SCROLL_KEY = 'cms-entry-scroll';

// Texte kommen vom Server (Sprache der Verwaltung); Rückfall bis zur ersten Antwort
const T = {
  dirty: 'Ungespeicherte Änderungen', saving: 'Speichere …', saved: 'Gespeichert {time}', published: 'Veröffentlicht {time}',
  drafted: 'Als Entwurf gespeichert {time}', error: 'Fehler beim Speichern', loading: 'Lade Felder …', failed: 'Laden fehlgeschlagen: {error}',
  close: 'Schließen', panel: 'Eintrag bearbeiten', format: 'Formatierung',
};
const tx = (k, p = {}) => Object.entries(p).reduce((s, [a, b]) => s.replaceAll('{' + a + '}', String(b)), T[k] ?? k);
const now = () => new Date().toTimeString().slice(0, 5);

let cfg = null;               // Detailseite: {table, id, status, endpoint, workflow, publish, csrf, texts}
let csrfToken = '';
const csrf = () => csrfToken || $('#adm-csrf')?.value || cfg?.csrf || '';

async function request(url, { method = 'GET', json, form } = {}) {
  const headers = { Accept: 'application/json', 'X-CSRF-Token': csrf() };
  if (json) headers['Content-Type'] = 'application/json';
  const r = await fetch(url, { method, credentials: 'same-origin', headers, body: json ? JSON.stringify(json) : form });
  if (r.redirected && /\/admin\/login/.test(r.url)) { alert(T.session || 'Session expired'); throw new Error('401'); }
  const res = await r.json().catch(() => ({ ok: false, error: 'Fehler ' + r.status }));
  if (res.texts) Object.assign(T, res.texts);
  if (res.csrf) csrfToken = res.csrf;
  res.status_code = r.status;
  return res;
}

// ------------------------------------------------------------------ Neu laden mit gleicher Scroll-Position + Meldung
function reloadKeepScroll(message, to = null) {
  try { sessionStorage.setItem(SCROLL_KEY, JSON.stringify({ path: to ? new URL(to, location.href).pathname : location.pathname, y: to ? 0 : scrollY, msg: message })); } catch {}
  try { history.scrollRestoration = 'manual'; } catch {}   // sonst stellt der Browser die alte Position nach dem Laden wieder her
  if (to) location.href = to; else location.reload();
}
function restoreScroll() {
  let s = null;
  try { s = JSON.parse(sessionStorage.getItem(SCROLL_KEY) || 'null'); sessionStorage.removeItem(SCROLL_KEY); } catch {}
  if (!s || s.path !== location.pathname) return;
  const go = () => scrollTo({ top: s.y, behavior: 'instant' });
  go(); addEventListener('load', () => { go(); requestAnimationFrame(go); try { history.scrollRestoration = 'auto'; } catch {} }, { once: true });
  if (s.msg) toast(s.msg);
}
function toast(msg, kind = 'ok') {
  const st = ui('[data-entry-status]');
  if (st) { setStatus(msg, 'is-' + kind); return; }
  const el = d.createElement('div');
  el.className = 'cms-toast cms-toast--' + kind; el.setAttribute('role', 'status'); el.textContent = msg;
  layerBox().append(el);   // Shadow-DOM-Ebene (editor.shadow.css)
  setTimeout(() => el.remove(), 4000);
}
function setStatus(t, cls = '') {
  // Werkzeugleiste (_bar.js): Chip, „Gespeichert ✓“, Live-Region
  barState({ 'is-dirty': 'dirty', 'is-busy': 'saving', 'is-ok': 'saved', 'is-error': 'error' }[cls] || 'clean', t);
}
/** Rückfrage vor dem Verwerfen (Dialog der Werkzeugleiste): 'keep' | 'save' | 'discard' */
// Rückfragen „Veröffentlichen?“ / „Als Entwurf?“ – gestalteter Dialog der Werkzeugleiste (_bar.js ask), kein window.confirm()
const askPublish = () => ask({ title: T.confirmPublish || 'Eintrag jetzt veröffentlichen?', ok: T.publishOk || 'Veröffentlichen', cancel: T.cancel, danger: false });
const askDraft = () => ask({ title: T.confirmDraft || 'Eintrag auf Entwurf setzen?', ok: T.draftOk || 'Als Entwurf', cancel: T.cancel, danger: false });
const askDiscard = (body, withSave = true) => confirmDiscard({
  title: T.discardTitle || T.discard, body, note: '', keep: T.keep, discard: T.discardBtn, save: withSave ? T.saveExit : '',
});

// ------------------------------------------------------------------ Seitenleiste „Eintrag bearbeiten“
const Panel = (() => {
  // Eigenes Shadow DOM: Theme-CSS wirkt nicht hinein; Stylesheets per <link> (CSP: keine Inline-Styles)
  let host = null, root = null, el = null, trigger = null, url = '', dirty = false, busy = false, linksUrl = '';
  const q = s => root.querySelector(s);

  function build(res) {
    host = d.createElement('div');
    host.id = 'cms-epanel-host';
    host.className = 'adm-ui';
    host.hidden = true;
    root = addRoot(host.attachShadow({ mode: 'open' }));   // change/submit-Handler aus admin.js auch hier
    const links = (res.shadowCss || []).map(h => `<link rel="stylesheet" href="${window.CMSAdmin.esc(h)}">`).join('');
    root.innerHTML = `${links}<aside class="ep" role="dialog" aria-labelledby="ep-title" tabindex="-1">
      <div class="ep__head"><div class="ep__titles"><p class="ep__eyebrow"></p><h2 id="ep-title" tabindex="-1"></h2></div>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-panel-close></button></div>
      <div class="ep__content"></div></aside><datalist id="cms-links"></datalist>`;
    el = q('.ep');
    d.body.append(host);
    el.addEventListener('click', e => {
      const b = e.target.closest('button');
      if (!b || busy) return;
      if (b.matches('[data-panel-close]')) close();
      else if (b.matches('[data-panel-discard]')) close();
      else if (b.hasAttribute('data-panel-save')) save(b.dataset.panelSave);
    });
    el.addEventListener('focusin', e => { if (e.target.matches('[list=cms-links]')) loadLinks(); });
    el.addEventListener('input', () => touch());
    el.addEventListener('change', () => touch());
    el.addEventListener('keydown', e => {
      if (e.key === 'Escape' && !openDialog()) { e.preventDefault(); close(); }
      if ((e.metaKey || e.ctrlKey) && e.key === 's') { e.preventDefault(); e.stopPropagation(); q('[data-panel-save]')?.click(); }
    });
    // Stylesheets im Shadow DOM abwarten, bevor die Leiste erscheint
    return Promise.all([...root.querySelectorAll('link')].map(l => new Promise(r => { l.onload = l.onerror = r; })));
  }

  let linksLoaded = false;
  function loadLinks() {
    if (linksLoaded || !linksUrl) return;
    linksLoaded = true;
    fetch(linksUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(r => r.json()).then(list => {
      q('#cms-links').innerHTML = list.map(l => `<option value="${window.CMSAdmin.esc(l.value)}">${window.CMSAdmin.esc(l.label)}</option>`).join('');
    }).catch(() => {});
  }

  function place() {
    const bar = barHost();
    host.style.top = (bar ? Math.max(0, Math.round(bar.getBoundingClientRect().bottom)) : 0) + 'px';
  }

  function head(res) {
    q('.ep__eyebrow').innerHTML = (res.ico ? ico(res.ico) + ' ' : '') + window.CMSAdmin.esc(res.table || '');
    q('#ep-title').textContent = res.title || T.panel;
    closeLabel();
  }
  // Kopf: „Schließen“ – mit ungespeicherten Änderungen „Abbrechen“ (wie in der Werkzeugleiste)
  function closeLabel() { const b = q('.ep__head [data-panel-close]'); if (b) b.textContent = dirty ? (T.cancel || T.close) : T.close; }
  function touch() { if (!dirty) { dirty = true; closeLabel(); } }

  function mount(res) {
    const box = q('.ep__content');
    box.innerHTML = res.html;
    // CSRF für Mediathek & Verknüpfungen (admin.js liest #adm-csrf im Dokument)
    let c = $('#adm-csrf');
    if (!c) { c = d.createElement('input'); c.type = 'hidden'; c.id = 'adm-csrf'; d.body.append(c); }
    if (res.csrf) c.value = res.csrf;
    window.CMSAdmin?.init(box);
    // Karten (Feldtyp Ort): MapLibre-Stylesheet auch im Shadow DOM
    box.querySelectorAll('[data-geo]').forEach(g => {
      try {
        const css = JSON.parse(g.dataset.geo).css;
        if (css && !root.querySelector(`link[href="${css}"]`)) root.prepend(Object.assign(d.createElement('link'), { rel: 'stylesheet', href: css }));
      } catch {}
    });
    const form = box.querySelector('[data-entry-form]');
    if (form && res.conditions) conditions(form, res.conditions, 'f');
    // Direkt im Text geänderte, noch nicht gespeicherte Felder übernehmen
    if (form && cfg && url === cfg.endpoint) Inline.pending().forEach(([name, value, mode]) => {
      const input = form.elements[`f[${name}]`];
      if (!input) return;
      input.value = value;
      const area = mode === 'rich' && input.closest('.rte')?.querySelector('.rte-area');
      if (area) area.innerHTML = value;
      touch();
    });
    return form;
  }

  async function open(endpoint, from) {
    if (host && !host.hidden && dirty && url !== endpoint && (await askDiscard(T.discardPanel || T.leave, false)) !== 'discard') return;
    // Aus einem Menü der Werkzeugleiste: Fokus kehrt später zum Menü-Knopf zurück (der Eintrag ist dann ausgeblendet)
    const menu = from?.closest?.('[role=menu]');
    trigger = menu ? (menu.getRootNode().querySelector(`[aria-controls="${menu.id}"]`) || from) : from;
    url = endpoint; dirty = false;
    from?.setAttribute('aria-busy', 'true');
    let res;
    try { res = await request(endpoint); } catch (e) { res = { ok: false, error: e.message }; }
    from?.removeAttribute('aria-busy');
    if (url !== endpoint) return;
    if (!res.ok) { alert(tx('failed', { error: res.error || '' })); url = ''; return; }
    linksUrl = res.links || '';
    // Mediathek, Link-Dialog: gemeinsame Shadow-DOM-Ebene (_shadow.js) – Stylesheets vom Server, falls keine Werkzeugleiste
    if (res.uiCss) setUiCss(res.uiCss);
    const dl = layerBox().querySelector('#cms-links');
    if (dl && !dl.dataset.endpoint && linksUrl) dl.dataset.endpoint = linksUrl;
    if (!host) await build(res);
    if (res.appearance) host.dataset.theme = res.appearance; else delete host.dataset.theme;
    for (const [k, v] of Object.entries({ accent: res.accent?.accent, side: res.accent?.side })) { if (v) host.dataset[k] = v; else delete host.dataset[k]; }   // Akzentfarbe (Core\Accent)
    head(res);
    const form = mount(res);
    host.hidden = false; place();
    d.body.classList.add('has-epanel');
    const first = form && form.querySelector('input:not([type=hidden]):not(:disabled),select:not(:disabled),textarea:not(:disabled),[contenteditable=true]');
    (first || q('#ep-title')).focus({ preventScroll: true });
  }

  async function close() {
    if (!host || host.hidden) return;
    if (dirty) {
      const r = await askDiscard(T.discardPanel || T.leave);
      if (r === 'save') { save(q('[data-panel-save=""]') ? '' : 'draft'); return; }
      if (r !== 'discard') return;
    }
    host.hidden = true; dirty = false; url = '';
    q('.ep__content').innerHTML = '';
    d.body.classList.remove('has-epanel');
    if (trigger?.isConnected) trigger.focus({ preventScroll: true });   // auch in der Werkzeugleiste (Shadow DOM)
  }

  async function save(status) {
    const form = q('[data-entry-form]');
    if (!form) return;
    if (status === 'published' && !(await askPublish())) return;
    if (status === 'draft' && cfg?.status === 'published' && url === cfg.endpoint && !(await askDraft())) return;
    const fd = new FormData(form);
    if (status) fd.set('status', status);
    const btns = [...root.querySelectorAll('.cms-epanel__foot button')];
    busy = true; btns.forEach(b => { b.disabled = true; });
    el.setAttribute('aria-busy', 'true');
    let res;
    try { res = await request(url, { method: 'POST', form: fd }); } catch (e) { res = { ok: false, error: e.message }; }
    busy = false; el.removeAttribute('aria-busy');
    btns.forEach(b => { b.disabled = false; });
    if (!res.ok) {
      if (res.html) {
        dirty = true; head(res); const f = mount(res);
        const err = f?.querySelector('.f--error input:not([type=hidden]),.f--error select,.f--error textarea,.f--error .rte-area,[aria-invalid=true]') || q('.adm-flash--error');
        err?.focus?.(); err?.scrollIntoView({ block: 'center' });
      } else alert(T.error + ': ' + (res.error || ''));
      return;
    }
    dirty = false;
    Inline.reset();
    const msg = tx(status === 'published' ? 'published' : status === 'draft' ? 'drafted' : 'saved', { time: res.saved_at || now() });
    // Seiten-Editor (Vorlage) mit ungespeicherten Änderungen: nicht neu laden
    if ($('#cms-editor') && ui('[data-editor-status].is-dirty')) { close(); alert(T.pending); return; }
    // Neuer Eintrag mit Detailseite: dorthin (Entwürfe erscheinen in keiner Liste)
    if (/\/new$/.test(url) && res.url && !$('#cms-editor')) { reloadKeepScroll(msg, res.url); return; }
    const onThis = cfg && url === cfg.endpoint;
    if (onThis && res.url && new URL(res.url, location.href).pathname !== location.pathname) { reloadKeepScroll(msg, res.url); return; }
    reloadKeepScroll(msg);
  }

  return { open, close, get open_() { return !!host && !host.hidden; } };
})();

// ------------------------------------------------------------------ Felder direkt im Text
const Inline = (() => {
  const nodes = [];
  let dirty = false, bar = null, barTarget = null, barTimer;

  // Redaktionsnotizen: Hinweise „Notiz: …“ (Core\EditorNotes::decorate, .cms-note) wieder als „[# … #]“ auslesen
  const unnote = n => {
    if (!n.querySelector('[data-cms-note]')) return n;
    const c = n.cloneNode(true);
    c.querySelectorAll('[data-cms-note]').forEach(x => x.replaceWith(document.createTextNode(`[# ${x.dataset.cmsNote} #]`)));
    return c;
  };
  const read = n => n.dataset.entryMode === 'plain' ? unnote(n).textContent.replace(/\s+/g, ' ').trim()
    : n.dataset.entryMode === 'lines' ? (n.querySelector('[data-cms-note]') ? unnote(n).textContent : n.innerText).replace(/\r/g, '').replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim()
    : unnote(n).innerHTML.trim().replace(/^<p><br><\/p>$/, '');

  function changed() { return nodes.filter(n => read(n) !== n._orig); }

  function refresh() {
    const was = dirty;
    dirty = changed().length > 0;
    nodes.forEach(n => n.classList.toggle('is-changed', read(n) !== n._orig));
    if (dirty && !was) setStatus(T.dirty, 'is-dirty');
    else if (!dirty && was) barState('clean');
  }

  // Bearbeiten ein/aus (Modus der Werkzeugleiste): nur im Modus „Bearbeiten“ sind die Felder editierbar
  let editing = false;
  function editable(n, on) {
    const mode = n.dataset.entryMode;
    if (!on) {
      ['contenteditable', 'role', 'aria-label', 'aria-multiline'].forEach(a => n.removeAttribute(a));
      if (n._title) n.title = n._title; else n.removeAttribute('title');
      clearError(n);
      return;
    }
    n.spellcheck = true;
    n.setAttribute('role', 'textbox');
    n.setAttribute('aria-label', n.dataset.entryLabel || '');
    if (mode !== 'plain') n.setAttribute('aria-multiline', 'true');
    n.title = tx('editHint', { label: n.dataset.entryLabel || '' });
    if (mode === 'plain') {
      n.contentEditable = 'plaintext-only';
      if (n.contentEditable !== 'plaintext-only') n.contentEditable = 'true';
    } else {
      n.contentEditable = 'true';
    }
  }
  const inView = n => { const r = n.getBoundingClientRect(); return r.bottom > 0 && r.top < innerHeight; };
  function start() {
    if (editing) return;
    editing = true;
    nodes.forEach(n => editable(n, true));
    d.documentElement.classList.add('cms-entry-editing');
    Bar.setMode('edit');
    // Fokus: erstes sichtbares Feld, sonst „Abbrechen“ (der Knopf „Bearbeiten“ ist jetzt ausgeblendet)
    const first = nodes.find(inView);
    if (first) first.focus({ preventScroll: true }); else ui('[data-bar-cancel]')?.focus();
  }
  function stop() {
    if (!editing) return;
    editing = false;
    const a = d.activeElement;
    nodes.forEach(n => editable(n, false));
    if (bar && !bar.hidden) { bar.hidden = true; barTarget = null; }
    d.documentElement.classList.remove('cms-entry-editing');
    Bar.setMode('view');
    // Fokus nicht verlieren: aus Feld, Leiste oder Rückfrage-Dialog zurück auf „Bearbeiten“
    if (!a || a === d.body || nodes.includes(a) || a === barHost() || a.id === 'cms-layer-host') ui('.cms-bar__grp [data-bar-mode=edit]')?.focus();
  }

  // Schwebende Formatierungsleiste (gleiche Befehle wie im Seiten-Editor)
  function showBar(n) {
    const R = window.CMSAdmin?.Rich;
    if (!R) return;
    clearTimeout(barTimer);
    if (!bar) {
      bar = d.createElement('div');
      bar.className = 'cms-inline-bar rte-bar'; bar.setAttribute('role', 'toolbar'); bar.setAttribute('aria-label', T.format);
      bar.innerHTML = R.barHtml('rich');
      R.mount(bar, () => barTarget, () => placeBar());   // Klicks, Menüs, Tastatur, Zustand (_rte.js)
      bar.addEventListener('focusout', hideBarSoon);   // Tastatur: Leiste verlassen
      addEventListener('scroll', placeBar, { passive: true });
      addEventListener('resize', placeBar);
      layerBox().append(bar);   // Shadow-DOM-Ebene: Kit-Regeln für button wirken nicht
    }
    barTarget = n; bar.hidden = false; placeBar(); R.state(n, bar);
  }
  function placeBar() {
    if (!bar || bar.hidden || !barTarget) return;
    const r = barTarget.getBoundingClientRect(), h = bar.offsetHeight, w = bar.offsetWidth;
    const minTop = (barHost()?.getBoundingClientRect().bottom || 0) + 8;
    let top = r.top - h - 10;
    if (top < minTop) top = Math.min(r.bottom + 10, innerHeight - h - 10);
    bar.style.top = Math.max(minTop, top) + 'px';
    bar.style.left = Math.max(8, Math.min(r.left, innerWidth - w - 8)) + 'px';
  }
  function hideBarSoon() {
    clearTimeout(barTimer);
    barTimer = setTimeout(() => { if (bar && !openDialog() && !(barTarget && barTarget.contains(d.activeElement)) && !bar.contains(deepActive())) { bar.hidden = true; barTarget = null; } }, 200);
  }

  function clearError(n) {
    n.removeAttribute('aria-invalid');
    n._err?.remove(); n._err = null;
  }
  function showError(n, msg) {
    clearError(n);
    n.setAttribute('aria-invalid', 'true');
    const tip = d.createElement('div');
    tip.className = 'cms-entry-errtip'; tip.setAttribute('role', 'alert'); tip.id = 'cms-err-' + n.dataset.entryField;
    tip.textContent = msg;
    layerBox().append(tip);
    const r = n.getBoundingClientRect();
    tip.style.top = (scrollY + r.bottom + 6) + 'px';
    tip.style.left = Math.max(8, scrollX + r.left) + 'px';
    n.setAttribute('aria-describedby', tip.id);
    n._err = tip;
  }

  function bind(n) {
    const mode = n.dataset.entryMode;
    n._orig = read(n); n._html = n.innerHTML; n._title = n.getAttribute('title') || '';
    n.addEventListener('keydown', e => {
      if (e.key === 'Escape') { e.preventDefault(); n.blur(); return; }
      if (mode === 'plain' && e.key === 'Enter') { e.preventDefault(); n.blur(); return; }
      if (mode === 'lines' && e.key === 'Enter') { e.preventDefault(); d.execCommand('insertLineBreak'); return; }
      // Nur formatierter Text kennt Fett/Kursiv
      if (mode !== 'rich' && (e.metaKey || e.ctrlKey) && /^[biu]$/i.test(e.key)) e.preventDefault();
    });
    if (mode !== 'rich') {
      n.addEventListener('paste', e => { e.preventDefault(); d.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text/plain')); });
    } else {
      n.classList.add('is-rich');
      window.CMSAdmin?.Rich.bindKeys(n, 'rich');
      n.addEventListener('focus', () => { if (editing) showBar(n); });
      n.addEventListener('blur', hideBarSoon);
    }
    n.addEventListener('input', () => { clearError(n); refresh(); });
    // Links in bearbeitbaren Feldern nicht auslösen
    n.addEventListener('click', e => { if (editing && e.target.closest('a')) e.preventDefault(); });
    nodes.push(n);
  }

  async function save(status = '') {
    const list = changed();
    if (!list.length && !status) return;
    if (status === 'published' && !(await askPublish())) return;
    if (status === 'draft' && !(await askDraft())) return;
    const f = {};
    list.forEach(n => { f[n.dataset.entryField] = read(n); });
    setStatus(T.saving, 'is-busy');
    let res;
    try { res = await request(cfg.endpoint, { method: 'POST', json: { f, partial: 1, ...(status ? { status } : {}) } }); }
    catch (e) { res = { ok: false, error: e.message }; }
    if (!res.ok) {
      setStatus(T.error, 'is-error');
      const errs = res.errors || {}, rest = [];
      Object.entries(errs).forEach(([path, msg]) => {
        const n = nodes.find(x => x.dataset.entryField === path.split('.')[0]);
        if (n) showError(n, msg); else rest.push(msg);
      });
      const firstBad = nodes.find(n => n.getAttribute('aria-invalid') === 'true');
      firstBad?.focus();
      if (rest.length || !Object.keys(errs).length) alert((res.error || T.error) + (rest.length ? '\n\n' + rest.join('\n') : ''));
      return;
    }
    const msg = tx(status === 'published' ? 'published' : status === 'draft' ? 'drafted' : 'saved', { time: res.saved_at || now() });
    if (status || (res.url && new URL(res.url, location.href).pathname !== location.pathname)) { dirty = false; reloadKeepScroll(msg, res.url && new URL(res.url, location.href).pathname !== location.pathname ? res.url : null); return; }
    nodes.forEach(n => { n._orig = read(n); n._html = n.innerHTML; clearError(n); });
    refresh();
    setStatus(msg, 'is-ok');
  }

  /** Ungespeicherte Änderungen im Text zurücksetzen (Abbrechen → Verwerfen) */
  function restore() {
    nodes.forEach(n => { n.innerHTML = n._html; clearError(n); });
    refresh();
  }

  return {
    init() {
      $$('[data-entry-field][data-entry-mode]').forEach(bind);
      ui('[data-entry-save]')?.addEventListener('click', () => save());
      ui('[data-entry-publish]')?.addEventListener('click', () => save('published'));
      ui('[data-entry-draft]')?.addEventListener('click', () => save('draft'));
      d.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key === 's' && editing && !Panel.open_) { e.preventDefault(); save(); }
      });
      addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
      // Werkzeugleiste: Bearbeiten / Abbrechen. Ohne Felder im Text öffnet „Bearbeiten“ die Seitenleiste mit allen Feldern.
      Bar.use({
        dirty: () => dirty,
        save: async () => { await save(); return !dirty; },
        discard: restore,
        exit: href => { stop(); if (href) location.href = href; },
        start: () => (nodes.length ? start() : Panel.open(cfg.endpoint, ui('.cms-bar__grp [data-bar-mode=edit]'))),
      });
      // Aus dem Vorlagen-Editor „Bearbeiten“ gewählt: …#cms-bearbeiten
      if (location.hash === '#cms-bearbeiten') {
        history.replaceState(null, '', location.pathname + location.search);
        if (nodes.length) start(); else Panel.open(cfg.endpoint, null);
      }
    },
    pending: () => changed().map(n => [n.dataset.entryField, read(n), n.dataset.entryMode]),
    reset() { dirty = false; nodes.forEach(n => { n._orig = read(n); }); },
  };
})();

export function initEntryEdit() {
  const c = ui('#cms-entry-config');
  if (c) {
    try { cfg = JSON.parse(c.textContent); Object.assign(T, cfg.texts || {}); } catch { cfg = null; }
  }
  // Stift, „Eintrag bearbeiten“, „+ Neuer Eintrag“ – auch in Block-Vorschauen des Seiten-Editors (daher Capture-Phase)
  d.addEventListener('click', e => {
    const a = pathClosest(e, '[data-entry-edit]');   // auch in der Werkzeugleiste (Shadow DOM)
    if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) return;
    e.preventDefault(); e.stopPropagation();
    Panel.open(a.dataset.entryEdit, a);
  }, true);
  if (cfg) Inline.init();
  // Höhe der Werkzeugleiste (--cms-bar-h) für klebende Theme-Köpfe: _shadow.js initBar()
  restoreScroll();
}
