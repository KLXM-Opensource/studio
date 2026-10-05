/*
 * „Neue Seite“ (Core\PageTool) – Werkzeug der Werkzeugleiste auf der Website, geladen erst beim ersten Öffnen
 * (CMSAdmin.tools, resources/js/_tools.js), dargestellt als Modal (panel size 'modal').
 *
 * Links der Seitenbaum der Sprache der aktuellen Seite (Radiogruppe – Pfeiltasten wählen, Filterfeld darüber), rechts die
 * Lage („Darunter“ als Unterseite, „Davor“ / „Danach“ auf derselben Ebene), Titel, Adresse, Vorlage und „Im Menü zeigen“.
 * Vorgewählt ist die aktuelle Seite (Startseite: „Danach“), die Vorlage folgt dem Vorschlag der Seitenvorlagen für den Ort,
 * solange sie nicht von Hand geändert wurde. Angelegt wird ein Entwurf; danach öffnet die neue Seite im Bearbeiten-Modus.
 * Endpunkte: tree (GET, PageController::apiTree), create (POST JSON, PageController::apiCreate) – Recht pages.manage.
 */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const ico = n => window.CMSAdmin?.ico?.(n) || '';
// Grobe Vorschau der Adresse (der Server bildet sie verbindlich)
const slugify = s => String(s).toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
  .normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

export default {
  async mount(ctx) {
    const P = ctx.panel.body, D = ctx.tool.data || {}, T = ctx.t;
    const st = { pages: [], templates: [], target: null, pos: 'inside', tplTouched: false, busy: false };
    P.innerHTML = `<p class="np-wait" role="status">${esc(T('loading'))}</p>`;
    try {
      const res = await ctx.fetch(ctx.tool.endpoints.tree, { query: { lang: D.lang || ctx.lang || '' } });
      st.pages = res.pages || []; st.templates = res.templates || [];
    } catch (e) {
      P.innerHTML = `<p class="np-err" role="alert">${esc(T('loadErr'))} ${esc(e.message || '')}</p>`;
      return;
    }
    const byId = new Map(st.pages.map(p => [p.id, p]));
    const cur = D.current && byId.get(D.current);
    st.target = cur ? cur.id : 0;
    st.pos = cur && !cur.home ? 'inside' : (cur ? 'after' : 'inside');

    P.innerHTML = `
    <form class="np" data-np novalidate>
      <fieldset class="np-tree">
        <legend class="np-h">${esc(T('where'))}</legend>
        <div class="np-filter">${ico('magnifying-glass')}<input type="search" data-np-q aria-label="${esc(T('filter'))}" placeholder="${esc(T('filter'))}" autocomplete="off"></div>
        <div class="np-list" data-np-list></div>
      </fieldset>
      <div class="np-form">
        <fieldset class="np-pos">
          <legend class="np-h">${esc(T('posHelp'))}</legend>
          <div class="np-seg">
            ${['before', 'inside', 'after'].map(k => `<label class="np-seg__o"><input type="radio" name="np-pos" value="${k}"${k === st.pos ? ' checked' : ''}><span>${esc(T(k))}</span></label>`).join('')}
          </div>
        </fieldset>
        <p class="np-path" data-np-path aria-live="polite"></p>
        <div class="f"><label for="np-title">${esc(T('title'))}</label><input id="np-title" name="title" required maxlength="120" autocomplete="off" autofocus aria-describedby="np-title-e"><p class="f-error" id="np-title-e" data-np-err="title" hidden></p></div>
        <div class="f"><label for="np-slug">${esc(T('slug'))}</label><input id="np-slug" name="slug" maxlength="120" autocomplete="off" spellcheck="false" aria-describedby="np-slug-h np-slug-e"><p class="f-help" id="np-slug-h">${esc(T('slugHelp'))}</p><p class="f-error" id="np-slug-e" data-np-err="slug" hidden></p></div>
        ${st.templates.length ? `<div class="f"><label for="np-tpl">${esc(T('template'))}</label><select id="np-tpl" name="template"><option value="">${esc(T('empty'))}</option>${st.templates.map(t => `<option value="${t.i}">${esc(t.label)}</option>`).join('')}</select></div>` : ''}
        <label class="f-check np-menu"><input type="checkbox" name="menu" checked> ${esc(T('menu'))}</label>
        <p class="np-note">${esc(T('draft'))}</p>
        <p class="f-error" data-np-err="_" role="alert" hidden></p>
        <p class="np-actions">
          <button type="button" class="adm-btn adm-btn--small" data-np-cancel>${esc(T('cancel'))}</button>
          <button type="submit" class="adm-btn adm-btn--small adm-btn--primary" data-np-go>${ico('file-plus')}<span>${esc(T('create'))}</span></button>
        </p>
        <p class="np-foot"><a href="${esc(D.admin || '#')}">${esc(T('manage'))} ${ico('arrow-square-out')}</a></p>
      </div>
    </form>`;

    const $ = s => P.querySelector(s);
    const form = $('[data-np]'), list = $('[data-np-list]'), q = $('[data-np-q]'), title = $('#np-title'), slug = $('#np-slug'), tpl = $('#np-tpl');

    const renderList = () => {
      const term = q.value.trim().toLowerCase();
      // Filter: Treffer und ihre Vorfahren bleiben sichtbar (Baum bleibt lesbar)
      let show = null;
      if (term) {
        show = new Set();
        for (const p of st.pages) if (p.title.toLowerCase().includes(term) || p.path.toLowerCase().includes(term)) {
          for (let x = p; x; x = x.parent ? byId.get(x.parent) : null) show.add(x.id);
        }
      }
      const rows = [{ id: 0, title: T('top'), depth: -1, path: '/', top: true }, ...st.pages.filter(p => !show || show.has(p.id))];
      list.innerHTML = rows.map(p => {
        const badges = (p.status && p.status !== 'published' ? `<span class="np-badge">${esc(T('draftBadge'))}</span>` : '')
          + (p.id && !p.menu && !p.home ? `<span class="np-badge np-badge--muted">${esc(T('hidden'))}</span>` : '')
          + (p.id && p.id === D.current ? `<span class="np-badge np-badge--cur">${esc(T('current'))}</span>` : '');
        return `<label class="np-row${p.top ? ' np-row--top' : ''}" data-d="${p.depth + 1}"><input type="radio" name="np-target" value="${p.id}"${p.id === st.target ? ' checked' : ''}>`
          + `<span class="np-row__ico" aria-hidden="true">${ico(p.top ? 'tree-structure' : p.home ? 'house' : 'file-text')}</span>`
          + `<span class="np-row__t">${esc(p.title)}</span>${badges}<span class="np-row__p">${esc(p.path)}</span></label>`;
      }).join('') || `<p class="np-none">${esc(T('none'))}</p>`;
      list.querySelectorAll('[data-d]').forEach(l => l.style.setProperty('--d', l.dataset.d));   // CSP: keine style-Attribute im HTML
      if (show && !show.has(st.target) && st.target) list.querySelector('input')?.click();
    };

    // Ort aus Auswahl + Lage: { parent, anchor, position }
    const place = () => {
      const t = st.target ? byId.get(st.target) : null;
      if (!t || st.pos === 'inside') return { parent: t ? t.id : null, anchor: null, position: 'end', parentPage: t };
      const parentPage = t.parent ? byId.get(t.parent) : null;
      return { parent: parentPage ? parentPage.id : null, anchor: t.id, position: st.pos, parentPage };
    };
    const update = () => {
      // Oberste Ebene: nur „Darunter“
      form.querySelectorAll('input[name="np-pos"]').forEach(r => { r.disabled = !st.target && r.value !== 'inside'; });
      if (!st.target && st.pos !== 'inside') { st.pos = 'inside'; form.querySelector('input[name="np-pos"][value="inside"]').checked = true; }
      const pl = place();
      const base = pl.parentPage && !pl.parentPage.home ? pl.parentPage.path.replace(/\/$/, '') : '';
      const s = slugify(slug.value || title.value) || '…';
      $('[data-np-path]').textContent = T('result', { path: base + '/' + s });
      // Vorlage vorschlagen (Seitenvorlagen „Vorschlagen unter“), solange nicht von Hand gewählt
      if (tpl && !st.tplTouched) tpl.value = pl.parentPage && pl.parentPage.suggest != null ? String(pl.parentPage.suggest) : '';
    };
    const err = (k, msg) => { const el = P.querySelector(`[data-np-err="${k}"]`); if (!el) return; el.textContent = msg || ''; el.hidden = !msg; };

    renderList();
    update();
    list.querySelector('input:checked')?.scrollIntoView?.({ block: 'center' });
    list.addEventListener('change', e => { if (e.target.name === 'np-target') { st.target = +e.target.value; update(); } });
    form.addEventListener('change', e => { if (e.target.name === 'np-pos') { st.pos = e.target.value; update(); } if (e.target === tpl) st.tplTouched = true; });
    q.addEventListener('input', renderList);
    title.addEventListener('input', () => { err('title'); update(); });
    slug.addEventListener('input', () => { err('slug'); update(); });
    $('[data-np-cancel]').addEventListener('click', () => ctx.close());

    form.addEventListener('submit', async e => {
      e.preventDefault();
      if (st.busy) return;
      ['title', 'slug', '_'].forEach(k => err(k));
      if (!title.value.trim()) { err('title', T('needTitle')); title.focus(); return; }
      const pl = place(), go = $('[data-np-go]');
      st.busy = true; go.disabled = true; go.querySelector('span').textContent = T('creating');
      try {
        const res = await ctx.fetch(ctx.tool.endpoints.create, { json: {
          title: title.value.trim(), slug: slug.value.trim(), parent: pl.parent, position: pl.position, anchor: pl.anchor,
          template: tpl ? tpl.value : '', menu: form.menu.checked, lang: D.lang || ctx.lang || '',
        } });
        location.href = res.url;
      } catch (ex) {
        const errs = ex.data?.errors || {};
        if (errs.title) err('title', errs.title);
        if (errs.slug) err('slug', errs.slug);
        const rest = Object.entries(errs).filter(([k]) => k !== 'title' && k !== 'slug').map(([, v]) => v);
        if (rest.length || !Object.keys(errs).length) err('_', rest.join(' ') || ex.message);
        (errs.title ? title : errs.slug ? slug : go).focus();
        st.busy = false; go.disabled = false; go.querySelector('span').textContent = T('create');
      }
    });
  },
  show(ctx) {
    ctx.panel.body.querySelector('#np-title')?.focus();
  },
};
