/*
 * „Seiten“ (Core\PagesTool) – Seitenstruktur auf der Website, Seitenleiste in voller Höhe (panel drawer), geladen erst beim ersten
 * Öffnen (CMSAdmin.tools, resources/js/_tools.js).
 *
 * Je Seite: Griff ⠿ (Ziehen: auf eine Seite = darunter einordnen, obere/untere Kante = davor/danach), Titel (Link), Art (Startseite,
 * Menü, Seite), ↑ ↓ ← →, Status (Online/Offline/Entwurf – Klick schaltet), Menü-Schalter, + Unterseite, × Löschen. Hinweise:
 * noindex, Zeitplan. Seiten mit Datentabelle (URL-Basis = Pfad): „N Einträge ▸“ mit den neuesten Einträgen.
 * Endpunkte der Seitenliste (gleiche Rechte und Prüfungen): tree?full=1, create, {base}/{id}/move|quick|publish|offline|delete.
 * Nach Änderungen: Baum neu laden; beim Schließen lädt die Seite neu (Menü, Adresse) – im Editor nur mit Hinweis.
 */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const ico = n => window.CMSAdmin?.ico?.(n) || '';

let S = { pages: [], open: new Set(), add: null, changed: false, moved: null, q: '' };
// Zugeklappte Seiten (mit Unterseiten) – im Browser gemerkt
const FOLD_KEY = 'cms-pp-fold';
const fold = new Set((() => { try { return JSON.parse(localStorage.getItem(FOLD_KEY) || '[]'); } catch { return []; } })());
const saveFold = () => { try { localStorage.setItem(FOLD_KEY, JSON.stringify([...fold])); } catch {} };
/** Liegt die Seite unter einer zugeklappten Seite? */
const hiddenByFold = p => { for (let x = S.pages.find(y => y.id === p.parent); x; x = S.pages.find(y => y.id === x.parent)) if (fold.has(x.id)) return true; return false; };

// Einrückung per Klasse (CSP der Website: keine style-Attribute)
const dc = n => 'pp-d' + Math.min(Math.max(n, 0), 6);
const kids = (pages, id) => pages.filter(p => p.parent === id);
const sibs = (pages, p) => pages.filter(x => x.parent === p.parent);

async function load(ctx) {
  const D = ctx.tool.data;
  const res = await ctx.fetch(ctx.tool.endpoints.tree, { query: { lang: D.lang || ctx.lang || '', full: 1 } });
  S.pages = res.pages || [];
}

function row(ctx, p, i) {
  const T = ctx.t, t = { title: p.title };
  const sib = sibs(S.pages, p), si = sib.indexOf(p), D = ctx.tool.data;
  const cur = p.id === D.current;
  // Nicht möglich (z. B. ↑ bei der ersten Seite): deaktivierter Knopf statt Lücke – gleiche Positionen in jeder Zeile
  const btn = (act, icon, label, on = true, extra = '') => `<button type="button" class="pp-b" data-pp="${act}" aria-label="${esc(label)}" title="${esc(label)}"${extra}${on ? '' : ' disabled'}>${icon}</button>`;
  const hasKids = S.pages.some(x => x.parent === p.id), folded = fold.has(p.id);
  const stateLbl = p.home ? T('homeOnline') : T(p.state === 'online' ? 'online' : p.state === 'offline' ? 'offline' : 'draft', t);
  const hints = (p.noindex ? `<span class="pp-hint" title="${esc(T('noindex'))}">${ico('magnifying-glass')}</span>` : '')
    + (p.plan ? `<span class="pp-hint pp-hint--plan" title="${esc(p.plan)}">${ico('clock')}</span>` : '');
  let h = `<li class="pp-row ${dc(p.depth)}${cur ? ' is-current' : ''}${p.home ? ' is-home' : ''}" data-id="${p.id}"${p.home ? '' : ' draggable="true"'}>`
    + `<span class="pp-grip" aria-hidden="true">${p.home ? '' : '⠿'}</span>`
    + (hasKids && !S.q ? `<button type="button" class="pp-fold" data-pp="fold" aria-expanded="${!folded}" aria-label="${esc(T(folded ? 'unfold' : 'foldIt', t))}" title="${esc(T(folded ? 'unfold' : 'foldIt', t))}">${folded ? '▸' : '▾'}</button>` : '<span class="pp-fold" aria-hidden="true"></span>')
    + `<span class="pp-name"><a class="pp-title" href="${esc(p.url)}"${cur ? ' aria-current="page"' : ''}>${p.home ? ico('house') + ' ' : ''}${esc(p.title)}</a>${hints}</span>`
    // Mitte: Art (Startseite/Menü/Seite); beim Überfahren bzw. mit Fokus in der Zeile stattdessen ↑ ↓ ← →
    + `<span class="pp-mid"><span class="pp-kind">${esc(p.home ? T('home') : p.menu ? T('inMenu') : T('page'))}</span>`
    + (p.home ? '</span>' : `<span class="pp-tools">`
      + btn('up', '↑', T('up', t), si > 0) + btn('down', '↓', T('down', t), si < sib.length - 1)
      + btn('left', '←', T('left', t), p.parent !== null) + btn('right', '→', T('right', t), si > 0 && !sib[si - 1].home)
      + `</span></span>`)
    + `<button type="button" class="pp-dot pp-dot--${esc(p.home ? 'online' : p.state)}" data-pp="status" aria-label="${esc(stateLbl)}" title="${esc(stateLbl)}"${p.home ? ' disabled' : ''}></button>`
    + btn('menu', '≡', T(p.menu ? 'menuOn' : 'menuOff', t), !p.home, ` aria-pressed="${p.menu}"`)
    + btn('add', '+', T('add', t)) + btn('del', '×', T('del', t), !p.home)
    + '</li>';
  for (const e of p.entries || []) {
    const k = p.id + ':' + e.admin, open = S.open.has(k);
    h += `<li class="pp-entries ${dc(p.depth + 1)}"><button type="button" class="pp-ent" data-pp-ent="${esc(k)}" aria-expanded="${open}">${esc(T('entries', { n: e.count }))} · ${esc(e.label)} <span aria-hidden="true">${open ? '▾' : '▸'}</span></button>`
      + (open ? `<ul class="pp-ent__list">${e.items.map(x => `<li><a href="${esc(x.url || '#')}">${esc(x.title)}</a>${x.draft ? ` <small>${esc(T('draftBadge'))}</small>` : ''}</li>`).join('')}<li><a href="${esc(e.admin)}">${esc(T('entriesAll'))} ↗</a></li></ul>` : '')
      + '</li>';
  }
  if (S.add === p.id) h += addForm(ctx, p.depth + 1);
  return h;
}

function addForm(ctx, depth) {
  const T = ctx.t;
  return `<li class="pp-add ${dc(depth)}"><form class="pp-addf" data-pp-addf novalidate>
    <input type="text" name="title" required maxlength="120" placeholder="${esc(T('newTitle'))}" aria-label="${esc(T('newTitle'))}" autocomplete="off">
    <label class="pp-check"><input type="checkbox" name="menu" checked> ${esc(T('newMenu'))}</label>
    <button class="adm-btn adm-btn--small adm-btn--primary">${esc(T('create'))}</button>
    <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-pp-addcancel>${esc(T('cancel'))}</button></form></li>`;
}

function render(ctx) {
  const P = ctx.panel.body, T = ctx.t;
  const q = S.q.trim().toLowerCase();
  const list = q ? S.pages.filter(p => p.title.toLowerCase().includes(q) || p.path.includes(q)) : S.pages.filter(p => !hiddenByFold(p));
  P.innerHTML = `<div class="pp">
    <p class="pp-help">${esc(T('help'))}</p>
    <p class="pp-bar"><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-pp="foldall">${esc(T(fold.size ? 'unfoldAll' : 'foldAll'))}</button><input type="search" class="pp-q" data-pp-q value="${esc(S.q)}" placeholder="${esc(T('filter'))}" aria-label="${esc(T('filter'))}">
      <button type="button" class="adm-btn adm-btn--small" data-pp="addtop">${ico('plus')} ${esc(T('addTop'))}</button></p>
    <ul class="pp-list" role="list">${list.length ? list.map((p, i) => row(ctx, p, i)).join('') : `<li class="pp-none">${esc(T('none'))}</li>`}${S.add === 0 ? addForm(ctx, 0) : ''}</ul>
    <p class="pp-foot">${S.changed ? `<span class="pp-note" role="status">${esc(T('reloadHint'))}</span>` : ''}<a href="${esc(ctx.tool.data.admin)}">${esc(T('manage'))} ↗</a></p></div>`;
  const qi = P.querySelector('[data-pp-q]');
  qi.addEventListener('input', () => { S.q = qi.value; const pos = qi.selectionStart; render(ctx); const n = P.querySelector('[data-pp-q]'); n.focus(); n.setSelectionRange(pos, pos); });
  P.querySelector('[data-pp-addf] input[name=title]')?.focus();
}

/** Seite verschieben: parent (ID|null), before/after = Nachbar auf der neuen Ebene */
async function move(ctx, p, parent, ref = {}) {
  const base = ctx.tool.endpoints.base;
  const res = await ctx.fetch(`${base}/${p.id}/move`, { json: { parent_id: parent ?? '', before_id: ref.before || '', after_id: ref.after || '', index: ref.index ?? 0 } });
  S.changed = true;
  if (p.id === ctx.tool.data.current && res.url) S.moved = res.url;
}

async function act(ctx, act, p, focusSel) {
  const T = ctx.t, base = ctx.tool.endpoints.base, t = { title: p?.title };
  try {
    if (act === 'up' || act === 'down') {
      const sib = sibs(S.pages, p), i = sib.indexOf(p), n = sib[i + (act === 'up' ? -1 : 1)];
      if (!n) return;
      await move(ctx, p, p.parent, act === 'up' ? { before: n.id } : { after: n.id });
    } else if (act === 'left') {
      const par = S.pages.find(x => x.id === p.parent); if (!par) return;
      await move(ctx, p, par.parent, { after: par.id });
    } else if (act === 'right') {
      const sib = sibs(S.pages, p), prev = sib[sib.indexOf(p) - 1]; if (!prev) return;
      const last = kids(S.pages, prev.id).pop();
      await move(ctx, p, prev.id, last ? { after: last.id } : { index: 0 });
    } else if (act === 'menu') {
      await ctx.fetch(`${base}/${p.id}/quick`, { json: { menu: !p.menu ? 1 : 0 } });
      S.changed = true;
    } else if (act === 'status') {
      if (p.state === 'online') {
        if (!(await window.CMSAdmin.bar.ask({ title: T('offTitle'), body: T('offBody'), ok: T('offOk'), danger: true }))) return;
        await ctx.fetch(`${base}/${p.id}/offline`, { method: 'POST' });
      } else {
        const r = await ctx.fetch(`${base}/${p.id}/publish`, { method: 'POST' });
        if (r.message) ctx.toast(r.message);
      }
      S.changed = true;
    } else if (act === 'del') {
      if (!(await window.CMSAdmin.bar.ask({ title: T('delTitle', t), body: T('delBody'), ok: T('delOk'), danger: true }))) return;
      await ctx.fetch(`${base}/${p.id}/delete`, { method: 'POST' });
      S.changed = true;
      if (p.id === ctx.tool.data.current) S.moved = '/';
      ctx.toast(T('deleted', t));
    } else if (act === 'foldall') {
      if (fold.size) fold.clear(); else S.pages.forEach(x => { if (S.pages.some(y => y.parent === x.id)) fold.add(x.id); });
      saveFold(); render(ctx); ctx.panel.body.querySelector('[data-pp="foldall"]')?.focus();
      return;
    } else if (act === 'fold') {
      fold.has(p.id) ? fold.delete(p.id) : fold.add(p.id);
      saveFold(); render(ctx);
      ctx.panel.body.querySelector(focusSel)?.focus();
      return;
    } else if (act === 'add' || act === 'addtop') {
      S.add = act === 'addtop' ? 0 : p.id;
      render(ctx);
      return;
    }
    await load(ctx);
    render(ctx);
    if (focusSel) ctx.panel.body.querySelector(focusSel)?.focus();
  } catch (e) {
    ctx.toast(e.message || T('loadErr'), 'error');
    await load(ctx).catch(() => {}); render(ctx);
  }
}

function wire(ctx) {
  const P = ctx.panel.body;
  P.addEventListener('click', e => {
    const ent = e.target.closest('[data-pp-ent]');
    if (ent) { const k = ent.dataset.ppEnt; S.open.has(k) ? S.open.delete(k) : S.open.add(k); render(ctx); P.querySelector(`[data-pp-ent="${CSS.escape(k)}"]`)?.focus(); return; }
    if (e.target.closest('[data-pp-addcancel]')) { S.add = null; render(ctx); return; }
    const b = e.target.closest('[data-pp]'); if (!b) return;
    const li = b.closest('[data-id]'), p = li ? S.pages.find(x => x.id === +li.dataset.id) : null;
    // Fokus nach dem Neuzeichnen: gleicher Knopf derselben Seite (Tastatur bleibt an der Stelle)
    act(ctx, b.dataset.pp, p, p ? `[data-id="${p.id}"] [data-pp="${b.dataset.pp}"]` : null);
  });
  P.addEventListener('submit', async e => {
    const f = e.target.closest('[data-pp-addf]'); if (!f) return;
    e.preventDefault();
    const title = f.title.value.trim(); if (!title) { f.title.focus(); return; }
    try {
      const res = await ctx.fetch(ctx.tool.endpoints.create, { json: { title, parent: S.add || '', menu: f.menu.checked, lang: ctx.tool.data.lang || '' } });
      S.add = null; S.changed = true;
      { const T = ctx.t; ctx.toast(T('created', { title: res.title })); }
      await load(ctx); render(ctx);
      P.querySelector(`[data-id="${res.id}"] .pp-title`)?.focus();
    } catch (ex) {
      ctx.toast(Object.values(ex.data?.errors || {}).join(' ') || ex.message, 'error');
    }
  });
  // Ziehen am Griff: obere/untere Kante = davor/danach, Mitte = als Unterseite
  let drag = null;
  P.addEventListener('dragstart', e => {
    const li = e.target.closest('.pp-row[draggable]'); if (!li) return;
    drag = S.pages.find(x => x.id === +li.dataset.id);
    li.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(drag.id));
  });
  const zone = (li, e) => { const r = li.getBoundingClientRect(), y = (e.clientY - r.top) / r.height; return y < .28 ? 'before' : y > .72 ? 'after' : 'inside'; };
  const clear = () => P.querySelectorAll('.pp-row').forEach(x => x.classList.remove('is-before', 'is-after', 'is-inside', 'is-drag'));
  P.addEventListener('dragover', e => {
    const li = e.target.closest('.pp-row'); if (!drag || !li || +li.dataset.id === drag.id) return;
    e.preventDefault();
    P.querySelectorAll('.pp-row').forEach(x => x.classList.remove('is-before', 'is-after', 'is-inside'));
    li.classList.add('is-' + zone(li, e));
  });
  P.addEventListener('dragend', () => { clear(); drag = null; });
  P.addEventListener('drop', async e => {
    const li = e.target.closest('.pp-row'); if (!drag || !li) return;
    e.preventDefault();
    const target = S.pages.find(x => x.id === +li.dataset.id), z = zone(li, e), p = drag;
    clear(); drag = null;
    if (!target || target.id === p.id) return;
    // nicht in die eigene Unterseite ziehen
    for (let x = target; x; x = S.pages.find(y => y.id === x.parent)) if (x.id === p.id) return;
    try {
      if (z === 'inside') { const last = kids(S.pages, target.id).filter(x => x.id !== p.id).pop(); await move(ctx, p, target.id, last ? { after: last.id } : { index: 0 }); }
      else await move(ctx, p, target.parent, z === 'before' ? { before: target.id } : { after: target.id });
      { const T = ctx.t; ctx.toast(T('moved')); }
      await load(ctx); render(ctx);
    } catch (ex) { ctx.toast(ex.message, 'error'); await load(ctx).catch(() => {}); render(ctx); }
  });
}

export default {
  async mount(ctx) {
    const T = ctx.t;
    ctx.panel.body.innerHTML = `<p class="pp-wait" role="status">${esc(T('loading'))}</p>`;
    wire(ctx);
    try { await load(ctx); render(ctx); } catch (e) { ctx.panel.body.innerHTML = `<p class="pp-wait" role="alert">${esc(T('loadErr'))}</p>`; }
  },
  async show(ctx) {
    try { await load(ctx); render(ctx); } catch {}
  },
  // Schließen: Änderungen am Baum → Seite neu laden (Menü/Navigation, neue Adresse); im Editor nicht (ungespeicherte Änderungen)
  hide() {
    if (!S.changed) return;
    S.changed = false;
    if (document.getElementById('cms-editor')) return;
    if (S.moved) { const u = S.moved; S.moved = null; location.href = u; } else location.reload();
  },
};
