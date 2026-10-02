/*
 * Seitenauswahl (Core, Teil von admin.js) – Feldtyp „pages“ (Core\PagePicker): mehrere Seiten wählen.
 *
 *  - Feld: gewählte Seiten als Chips mit Pfad im Seitenbaum („Eltern › Seite · /pfad“), „×“ entfernt, „Seiten auswählen …“
 *    öffnet den Dialog. Werte als versteckte Felder name[] – „12“ bzw. „12*“ (mit Unterseiten) oder Pfade „/x“, „/x/*“.
 *  - Dialog: Seitenbaum wie in der Linkauswahl (GET /admin/api/links?format=tree&lang=…, Core\Links::tree), mit Kästchen;
 *    Suche filtert den Baum (Treffer + Elternseiten), „Alle sichtbaren auswählen“, Sprachen, Anzahl, „Übernehmen“.
 *    „mit Unterseiten“ je Seite (Knopf in der Zeile, Umschalt+Leertaste) bzw. als Vorgabe für neu gewählte Seiten; Unterseiten
 *    einer so gewählten Seite erscheinen als „inbegriffen“. Pfad-Modus: zusätzlich eigene Pfade (z. B. /blog/*).
 *  - WAI-ARIA-Baum (role=tree, aria-multiselectable, treeitem mit aria-checked, aria-level, aria-expanded, aria-activedescendant).
 *    Tastatur: ↑/↓ bewegen, →/← auf- und zuklappen bzw. Eltern, Pos1/Ende, Leertaste wählt, Umschalt+Leertaste „mit Unterseiten“,
 *    * klappt Geschwister auf, Enter übernimmt, Buchstaben springen in die Suche, Esc bricht ab.
 * Website: Dialog in der Shadow-DOM-Ebene (_shadow.js layerBox()), Verwaltung: im Dokument.
 */
import { layerBox } from './_shadow.js';
import { t } from './_i18n.js';
import { ico } from './_icons.js';
import { linksUrl } from './_links.js';

const d = document;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const fold = s => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ß/g, 'ss');
const SUB_KEY = 'cms-pages-sub';   // Vorgabe „mit Unterseiten“ (nur Komfort)

let dlg = null, st = null;

function build() {
  const box = layerBox();
  dlg = box.querySelector('#cms-pgp');
  if (dlg) return dlg;
  box.insertAdjacentHTML('beforeend', `<dialog id="cms-pgp" class="adm-dialog lp pgp" aria-labelledby="cms-pgp-t">
    <form method="dialog" class="lp__form" novalidate>
      <div class="adm-dialog__head"><h2 id="cms-pgp-t">${esc(t('Seiten auswählen'))}</h2>
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-pgp-cancel>${esc(t('Abbrechen'))}</button></div>
      <div class="lp__bar">
        <input type="search" class="lp__q" data-pgp-q aria-controls="cms-pgp-tree" aria-label="${esc(t('Seiten suchen'))}" autocomplete="off" spellcheck="false" placeholder="${esc(t('Seite suchen …'))}">
        <span class="lp__langs" role="group" aria-label="${esc(t('Sprache'))}" data-pgp-langs></span>
      </div>
      <div class="pgp__tools">
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-pgp-all></button>
        <label class="lp__check pgp__subdef" data-pgp-subwrap><input type="checkbox" data-pgp-subdef> ${esc(t('Neu gewählte Seiten mit Unterseiten'))}</label>
      </div>
      <ul class="lp__list lp__tree pgp__tree" id="cms-pgp-tree" role="tree" aria-multiselectable="true" aria-label="${esc(t('Seitenbaum'))}" tabindex="0" data-pgp-tree></ul>
      <div class="pgp__custom" data-pgp-custom hidden>
        <p class="pgp__ch">${esc(t('Eigene Pfade'))}</p>
        <ul class="pgp__clist" data-pgp-clist></ul>
        <div class="pgp__cadd"><input type="text" class="lp__q" data-pgp-path aria-label="${esc(t('Eigener Pfad'))}" placeholder="/blog/*" spellcheck="false" autocomplete="off">
          <button type="button" class="adm-btn adm-btn--small" data-pgp-addpath>${esc(t('Hinzufügen'))}</button></div>
      </div>
      <p class="lp__error" data-pgp-error role="alert" hidden></p>
      <div class="adm-row lp__actions pgp__actions">
        <button type="submit" class="adm-btn adm-btn--primary" data-pgp-ok>${esc(t('Übernehmen'))}</button>
        <button type="button" class="adm-btn adm-btn--ghost" data-pgp-cancel>${esc(t('Abbrechen'))}</button>
        <span class="pgp__count" data-pgp-count aria-live="polite"></span>
      </div>
      <p class="lp__keys f-help">${esc(t('Tastatur: ↑/↓ bewegen · Leertaste wählen · Umschalt+Leertaste mit Unterseiten · →/← auf- und zuklappen · Enter übernehmen'))}</p>
    </form></dialog>`);
  dlg = box.querySelector('#cms-pgp');
  wire();
  return dlg;
}

const q = s => dlg.querySelector(s);
const qa = s => [...dlg.querySelectorAll(s)];

function wire() {
  qa('[data-pgp-cancel]').forEach(b => b.addEventListener('click', () => dlg.close('cancel')));
  dlg.addEventListener('cancel', () => {});
  dlg.addEventListener('close', () => {
    if (!st) return;
    const s = st; st = null;
    s.resolve(dlg.returnValue === 'ok' ? result(s) : null);
  });
  q('form').addEventListener('submit', e => { e.preventDefault(); dlg.close('ok'); });
  const tree = q('[data-pgp-tree]');
  tree.addEventListener('click', e => {
    const n = e.target.closest('[role=treeitem]');
    if (!n) return;
    tree.focus({ preventScroll: true });
    activate(n, false);
    if (e.target.closest('[data-pgp-twisty]')) { expand(n, n.getAttribute('aria-expanded') !== 'true'); return; }
    if (e.target.closest('[data-pgp-sub]')) { toggleSub(n); return; }
    toggle(n);
  });
  tree.addEventListener('mousedown', e => { if (e.target.closest('[data-pgp-twisty],[data-pgp-sub]')) e.preventDefault(); });
  tree.addEventListener('focus', () => { if (!tree.getAttribute('aria-activedescendant')) { const n = visible()[0]; if (n) activate(n); } });
  tree.addEventListener('keydown', treeKey);
  const inp = q('[data-pgp-q]');
  let timer;
  inp.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { st.q = inp.value.trim(); render(); }, 120); });
  inp.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') { e.preventDefault(); tree.focus(); const n = visible()[0]; if (n) activate(n); }
    else if (e.key === 'Enter') { e.preventDefault(); tree.focus(); const n = visible()[0]; if (n) activate(n); }
  });
  q('[data-pgp-langs]').addEventListener('click', e => { const b = e.target.closest('[data-lang]'); if (b) load(b.dataset.lang); });
  q('[data-pgp-all]').addEventListener('click', allVisible);
  q('[data-pgp-subdef]').addEventListener('change', e => { st.subDef = e.target.checked; try { localStorage.setItem(SUB_KEY, st.subDef ? '1' : '0'); } catch {} });
  q('[data-pgp-addpath]').addEventListener('click', addPath);
  q('[data-pgp-path]').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addPath(); } });
  q('[data-pgp-clist]').addEventListener('change', e => {
    const c = e.target.closest('[data-pgp-cpath]');
    if (!c) return;
    if (c.checked) st.sel.set(c.value, { custom: true, sub: !!st.known.get(c.value)?.sub }); else st.sel.delete(c.value);
    count();
  });
}

// ------------------------------------------------------------------ Werte
/** Wert → Schlüssel + Unterseiten (ids: „12*“ → 12; Pfade: „/x/*“ → /x) */
function parse(v, store) {
  v = String(v || '').trim();
  if (store === 'paths') {
    const sub = /\/\*$/.test(v) || v === '/*';
    const key = sub ? (v.replace(/\/?\*$/, '') || '/') : v;
    return { key, sub };
  }
  return { key: v.replace(/\*$/, ''), sub: v.endsWith('*') };
}
function valueOf(key, sub, store) {
  if (store === 'paths') return sub ? (key === '/' ? '/*' : key.replace(/\/$/, '') + '/*') : key;
  return key + (sub ? '*' : '');
}
const keyOf = nd => st.store === 'paths' ? (nd.path || '/').replace(/(.)\/$/, '$1') : String(nd.id);

// ------------------------------------------------------------------ Baum laden und zeichnen
async function load(lang) {
  const tree = q('[data-pgp-tree]'), n = ++st.seq;
  tree.setAttribute('aria-busy', 'true');
  let data = st.trees[lang || ''];
  if (!data) {
    try {
      const u = linksUrl();
      const r = await fetch(u + (u.includes('?') ? '&' : '?') + new URLSearchParams({ format: 'tree', lang: lang || '' }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      data = r.ok ? await r.json() : null;
    } catch { data = null; }
    if (!st || n !== st.seq) return;
    if (!data) { err(t('Seitenbaum konnte nicht geladen werden.')); data = { nodes: [], langs: {}, count: 0, lang: lang || '' }; }
    st.trees[data.lang || lang || ''] = data;
    if (!lang) st.trees[''] = data;
  }
  if (!st || n !== st.seq) return;
  tree.removeAttribute('aria-busy');
  st.lang = data.lang || '';
  // Knoten merken (Beschriftung für Chips, Kinder für „alle sichtbaren“)
  const index = (nodes, trail) => nodes.forEach(nd => { st.nodes.set(keyOf(nd), { nd, trail }); index(nd.children || [], [...trail, nd.label]); });
  index(data.nodes || [], []);
  const langs = Object.entries(data.langs || {});
  q('[data-pgp-langs]').innerHTML = langs.length > 1 ? langs.map(([c, l]) => `<button type="button" data-lang="${esc(c)}" aria-pressed="${c === st.lang ? 'true' : 'false'}" title="${esc(l)}">${esc(c.toUpperCase())}</button>`).join('') : '';
  if (!st.opened) {
    // Anfangs: oberste Ebene mit Unterseiten offen, dazu die Eltern gewählter Seiten
    (data.nodes || []).forEach(nd => { if (nd.children?.length) st.open.add(keyOf(nd)); });
    for (const k of st.sel.keys()) { const x = st.nodes.get(k); if (x) ancestorsOf(k).forEach(a => st.open.add(a)); }
    st.opened = true;
  }
  render();
}
function ancestorsOf(key) {
  const out = [];
  const walk = (nodes, trail) => nodes.some(nd => keyOf(nd) === key ? (out.push(...trail), true) : walk(nd.children || [], [...trail, keyOf(nd)]));
  walk(st.trees[st.lang]?.nodes || [], []);
  return out;
}
/** Treffer der Suche + ihre Elternseiten */
function filtered(nodes) {
  const f = fold(st.q);
  if (!f) return nodes;
  const walk = list => list.map(nd => {
    const kids = walk(nd.children || []);
    const hit = fold(nd.label).includes(f) || fold(nd.path || nd.meta).includes(f);
    return hit || kids.length ? { ...nd, children: kids, _hit: hit } : null;
  }).filter(Boolean);
  return walk(nodes);
}
function render() {
  const tree = q('[data-pgp-tree]'), data = st.trees[st.lang] || { nodes: [] };
  const nodes = filtered(data.nodes || []);
  const searching = !!st.q;
  let i = 0;
  st.rows = [];
  const walk = (list, level, implied) => list.map(nd => {
    const k = keyOf(nd), sel = st.sel.get(k), kids = nd.children?.length || 0, idx = i++;
    const open = searching || st.open.has(k);
    const checked = !!sel || implied;
    st.rows[idx] = nd;
    let h = `<li role="treeitem" id="cms-pgp-t${idx}" data-i="${idx}" data-k="${esc(k)}" aria-level="${level}" aria-checked="${checked ? 'true' : 'false'}"`
      + (kids ? ` aria-expanded="${open ? 'true' : 'false'}"` : '') + (implied ? ' aria-disabled="true"' : '')
      + ` aria-labelledby="cms-pgp-l${idx}" aria-describedby="cms-pgp-m${idx}" class="lp__ti pgp__ti${implied ? ' is-implied' : ''}${searching && !nd._hit ? ' is-context' : ''}">`
      + `<div class="lp__tr pgp__tr" data-lvl="${level - 1}">`
      + (kids ? '<button type="button" class="lp__twisty" data-pgp-twisty tabindex="-1" aria-hidden="true"></button>' : '<span class="lp__twisty lp__twisty--none"></span>')
      + `<span class="pgp__cb" aria-hidden="true"></span>`
      + `<span class="lp__ico" aria-hidden="true">${ico(nd.home ? 'house' : kids ? 'folder' : 'file-text')}</span>`
      + `<span class="lp__txt"><span class="lp__label" id="cms-pgp-l${idx}">${esc(nd.label)}</span><span class="lp__meta" id="cms-pgp-m${idx}">${esc(nd.path || nd.meta)}${implied ? ' · ' + esc(t('inbegriffen')) : ''}${sel?.sub ? ' · ' + esc(t('mit Unterseiten')) : ''}</span></span>`
      + (nd.draft ? `<span class="lp__badge lp__badge--draft">${esc(nd.state === 'offline' ? t('Offline') : t('Entwurf'))}</span>` : '')
      + (st.allowSub && kids && sel && !implied ? `<button type="button" class="pgp__subbtn" data-pgp-sub tabindex="-1" aria-pressed="${sel.sub ? 'true' : 'false'}" title="${esc(t('Unterseiten einbeziehen (Umschalt+Leertaste)'))}">${ico('tree-structure')}<span>${esc(sel.sub ? t('mit Unterseiten') : t('+ Unterseiten'))}</span></button>` : '')
      + '</div>';
    if (kids) h += '<ul role="group">' + walk(nd.children, level + 1, implied || !!sel?.sub) + '</ul>';
    return h + '</li>';
  }).join('');
  const act = tree.getAttribute('aria-activedescendant');
  const actKey = act ? tree.querySelector('#' + act)?.dataset.k : null;
  tree.innerHTML = walk(nodes, 1, false) || `<li class="lp__empty" role="none">${esc(st.q ? t('Nichts gefunden.') : t('Noch keine Seiten vorhanden.'))}</li>`;
  // Einrückung per CSSOM (keine style-Attribute – CSP der Website)
  tree.querySelectorAll('[data-lvl]').forEach(r => r.style.setProperty('--lvl', r.dataset.lvl));
  tree.removeAttribute('aria-activedescendant');
  const back = actKey !== null && actKey !== undefined ? [...tree.querySelectorAll('[role=treeitem]')].find(n => n.dataset.k === actKey) : null;
  if (back) activate(back, false);
  renderCustom();
  count();
}
function renderCustom() {
  if (st.store !== 'paths') return;
  q('[data-pgp-custom]').hidden = false;
  const custom = [...st.custom];
  q('[data-pgp-clist]').innerHTML = custom.map(p => `<li><label class="lp__check"><input type="checkbox" data-pgp-cpath value="${esc(p)}"${st.sel.has(p) ? ' checked' : ''}> <code>${esc(valueOf(p, !!st.sel.get(p)?.sub || !!st.known.get(p)?.sub, 'paths'))}</code></label></li>`).join('')
    || `<li class="f-help">${esc(t('Für Adressen ohne eigene Seite, z. B. Detailseiten von Einträgen: /blog/*'))}</li>`;
}
function count() {
  const n = st.sel.size;
  q('[data-pgp-count]').textContent = n === 1 ? t('1 Seite ausgewählt') : t('{n} Seiten ausgewählt', { n });
  const vis = visibleKeys();
  const all = vis.length && vis.every(k => st.sel.has(k));
  q('[data-pgp-all]').textContent = all ? t('Sichtbare abwählen') : (st.q ? t('Alle Treffer auswählen') : t('Alle sichtbaren auswählen'));
  q('[data-pgp-all]').disabled = !vis.length;
}
function err(msg) { const p = q('[data-pgp-error]'); p.textContent = msg || ''; p.hidden = !msg; }

// ------------------------------------------------------------------ Auswahl
function visible() {
  return qa('[data-pgp-tree] [role=treeitem]').filter(n => !n.parentElement.closest('[role=treeitem][aria-expanded=false]'));
}
/** Sichtbare, wählbare Seiten (bei Suche nur Treffer, keine inbegriffenen) */
function visibleKeys() {
  return visible().filter(n => !n.classList.contains('is-context') && n.getAttribute('aria-disabled') !== 'true').map(n => n.dataset.k);
}
function toggle(n) {
  if (n.getAttribute('aria-disabled') === 'true') return;
  const k = n.dataset.k;
  if (st.sel.has(k)) st.sel.delete(k);
  else st.sel.set(k, { sub: st.allowSub && st.subDef && !!st.rows[+n.dataset.i]?.children?.length });
  render();
}
function toggleSub(n) {
  if (!st.allowSub) return;
  const k = n.dataset.k;
  if (n.getAttribute('aria-disabled') === 'true') return;
  const cur = st.sel.get(k);
  st.sel.set(k, { ...(cur || {}), sub: !(cur?.sub) });
  render();
}
function allVisible() {
  const keys = visibleKeys();
  const all = keys.every(k => st.sel.has(k));
  keys.forEach(k => all ? st.sel.delete(k) : (st.sel.has(k) || st.sel.set(k, { sub: false })));
  render();
}
function addPath() {
  const inp = q('[data-pgp-path]');
  const v = inp.value.trim();
  if (!/^\/[^\s<>"]{0,300}$/.test(v)) { err(t('Bitte einen Pfad angeben, der mit / beginnt (z. B. /blog/*).')); inp.focus(); return; }
  err('');
  const { key, sub } = parse(v, 'paths');   // „/blog/*“ → /blog mit Unterseiten (wie gespeicherte Werte)
  st.custom.add(key);
  st.sel.set(key, { custom: true, sub });
  inp.value = '';
  renderCustom(); count();
}

// ------------------------------------------------------------------ Tastatur (wie Linkauswahl, plus Kästchen)
function activate(n, scroll = true) {
  const tree = q('[data-pgp-tree]');
  tree.querySelectorAll('.is-active').forEach(x => x.classList.remove('is-active'));
  n.classList.add('is-active');
  tree.setAttribute('aria-activedescendant', n.id);
  if (scroll) (n.querySelector(':scope > .lp__tr') || n).scrollIntoView({ block: 'nearest' });
}
function expand(n, open) {
  if (!n?.hasAttribute('aria-expanded') || st.q) return;
  n.setAttribute('aria-expanded', open ? 'true' : 'false');
  open ? st.open.add(n.dataset.k) : st.open.delete(n.dataset.k);
}
function treeKey(e) {
  const tree = e.currentTarget, nodes = visible();
  if (!nodes.length) return;
  const cur = tree.querySelector('#' + (tree.getAttribute('aria-activedescendant') || 'x'));
  const i = nodes.indexOf(cur);
  let n = null;
  if (e.key === 'ArrowDown') n = nodes[Math.min(nodes.length - 1, i + 1)];
  else if (e.key === 'ArrowUp') n = nodes[Math.max(0, i - 1)];
  else if (e.key === 'Home') n = nodes[0];
  else if (e.key === 'End') n = nodes[nodes.length - 1];
  else if (e.key === 'ArrowRight' && cur) {
    if (cur.getAttribute('aria-expanded') === 'false') expand(cur, true);
    else if (cur.getAttribute('aria-expanded') === 'true') n = cur.querySelector('[role=treeitem]');
  } else if (e.key === 'ArrowLeft' && cur) {
    if (cur.getAttribute('aria-expanded') === 'true') expand(cur, false);
    else n = cur.parentElement.closest('[role=treeitem]');
  } else if (e.key === ' ' && cur) {
    e.preventDefault();
    const id = cur.id;
    e.shiftKey ? toggleSub(cur) : toggle(cur);
    const again = tree.querySelector('#' + id);
    if (again) activate(again);
    return;
  } else if (e.key === 'Enter') { e.preventDefault(); dlg.close('ok'); return; }
  else if (e.key === '*' && cur) { [...cur.parentElement.children].forEach(x => expand(x, true)); }
  else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey && /\S/.test(e.key)) {
    e.preventDefault();
    const inp = q('[data-pgp-q]');
    inp.value += e.key; inp.focus();
    inp.dispatchEvent(new Event('input'));
    return;
  } else return;
  e.preventDefault();
  if (n) activate(n);
}

// ------------------------------------------------------------------ Ergebnis
function result(s) {
  const out = [];
  for (const [k, v] of s.sel) {
    const info = s.nodes.get(k), known = s.known.get(k);
    const nd = info?.nd;
    const sub = !!v.sub && s.allowSub;
    if (nd) {
      out.push({ value: valueOf(k, sub, s.store), label: nd.label, trail: info.trail.join(' › '), href: nd.path || nd.meta || '',
        sub, lang: nd.badge || '', missing: false, custom: false });
    } else if (known) {
      out.push({ ...known, value: valueOf(k, sub, s.store), sub });
    } else {
      out.push({ value: valueOf(k, sub, s.store), label: s.store === 'paths' ? valueOf(k, sub, s.store) : k, trail: '', href: k, sub, lang: '', missing: s.store !== 'paths', custom: s.store === 'paths' });
    }
  }
  return out;
}

/**
 * Dialog öffnen. opts: store 'ids'|'paths', subpages (bool), items (aktuelle Chips: {value, label, trail, href, sub, lang, missing, custom})
 * → Promise: Liste der Chips oder null (abgebrochen)
 */
export function openPagesPicker(opts = {}) {
  build();
  if (st) { const s = st; st = null; s.resolve(null); }
  return new Promise(resolve => {
    const store = opts.store === 'paths' ? 'paths' : 'ids';
    let subDef = false;
    try { subDef = localStorage.getItem(SUB_KEY) === '1'; } catch {}
    st = { resolve, store, allowSub: opts.subpages !== false, subDef, sel: new Map(), known: new Map(), custom: new Set(), nodes: new Map(),
      trees: {}, lang: '', open: new Set(), opened: false, seq: 0, q: '', rows: [] };
    for (const it of opts.items || []) {
      const { key, sub } = parse(it.value, store);
      if (!key) continue;
      st.sel.set(key, { sub, custom: !!it.custom });
      st.known.set(key, it);
      if (it.custom) st.custom.add(key);
    }
    q('[data-pgp-q]').value = '';
    q('[data-pgp-subwrap]').hidden = !st.allowSub;
    q('[data-pgp-subdef]').checked = subDef;
    q('[data-pgp-custom]').hidden = store !== 'paths';
    q('[data-pgp-tree]').innerHTML = '';
    err('');
    dlg.returnValue = '';
    dlg.showModal();
    q('[data-pgp-q]').focus();
    load(opts.lang || '');
  });
}

// ------------------------------------------------------------------ Feldtyp „pages“ (Core\PagePicker::render)
function chipHtml(it, name) {
  const trail = it.missing ? t('Seite nicht gefunden') : it.custom ? t('Eigener Pfad') : [it.trail, it.label].filter(Boolean).join(' › ') + (it.href ? ' · ' + it.href : '');
  return `<li class="pgf__chip${it.missing ? ' is-missing' : ''}" data-value="${esc(it.value)}"><span class="pgf__txt"><span class="pgf__label">${esc(it.label)}</span>`
    + (it.lang ? ` <span class="pgf__lang">${esc(it.lang)}</span>` : '') + (it.sub ? ` <span class="pgf__sub">${esc(t('+ Unterseiten'))}</span>` : '')
    + `<span class="pgf__trail">${esc(trail)}</span></span>`
    + `<button type="button" class="pgf__x" data-pages-remove aria-label="${esc(t('„{label}“ entfernen', { label: it.label }))}" title="${esc(t('Entfernen'))}">×</button>`
    + `<input type="hidden" name="${esc(name)}[]" value="${esc(it.value)}"></li>`;
}
/** Chips des Felds als Daten (für den Dialog) */
function chipsOf(box) {
  return [...box.querySelectorAll('[data-pages-chips] > li')].map(li => {
    const trail = li.querySelector('.pgf__trail')?.textContent || '';
    const href = trail.includes(' · ') ? trail.split(' · ').pop() : '';
    return { value: li.dataset.value, label: li.querySelector('.pgf__label')?.textContent || li.dataset.value, trail: trail.split(' · ')[0].split(' › ').slice(0, -1).join(' › '),
      href, sub: !!li.querySelector('.pgf__sub'), lang: li.querySelector('.pgf__lang')?.textContent || '', missing: li.classList.contains('is-missing'),
      custom: trail === t('Eigener Pfad') };
  });
}
function refresh(box) {
  const n = box.querySelectorAll('[data-pages-chips] > li').length;
  box.querySelector('[data-pages-empty]').hidden = n > 0;
  box.querySelector('[data-pages-count]').textContent = n ? t('{n} ausgewählt', { n }) : '';
}
function changed(box) {
  refresh(box);
  box.dispatchEvent(new Event('input', { bubbles: true }));
  box.dispatchEvent(new Event('change', { bubbles: true }));
}

export function initPagesFields(scope = d) {
  scope.querySelectorAll('[data-pages-field]').forEach(box => {
    if (box._init) return; box._init = true;
    box.addEventListener('click', async e => {
      const rm = e.target.closest('[data-pages-remove]');
      if (rm) {
        const li = rm.closest('li'), next = li.nextElementSibling?.querySelector('[data-pages-remove]') || li.previousElementSibling?.querySelector('[data-pages-remove]');
        li.remove();
        changed(box);
        (next || box.querySelector('[data-pages-pick]')).focus();
        return;
      }
      if (!e.target.closest('[data-pages-pick]')) return;
      const r = await openPagesPicker({ store: box.dataset.store, subpages: box.dataset.subpages !== '0', items: chipsOf(box) });
      box.querySelector('[data-pages-pick]').focus();
      if (!r) return;
      box.querySelector('[data-pages-chips]').innerHTML = r.map(it => chipHtml(it, box.dataset.name)).join('');
      changed(box);
    });
  });
}
