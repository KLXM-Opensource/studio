/*
 * „Seiteneinstellungen“ (Core\PageSettingsTool) – Werkzeug der Werkzeugleiste auf der Website (Menü „⋯“), geladen erst beim
 * ersten Öffnen (CMSAdmin.tools, resources/js/_tools.js), dargestellt als Modal wie „Neue Seite“ (panel size 'modal').
 *
 * Links die Seite (Titel, Adresse, Status, Menü, Suchmaschinen-Ausschluss), rechts Titel und Beschreibung für Suchmaschinen
 * mit Zeichenzähler, KI-Vorschlag (falls verfügbar) und Vorschaubild für soziale Netzwerke (Mediathek: CMSMedia.pick).
 * Gespeichert wird über PageController::apiSettingsSave (gleicher Weg wie das Formular der Verwaltung). Danach: <title>,
 * Meta-Angaben, Titel der Werkzeugleiste und Adresse (history.replaceState) ohne Neuladen nachführen; ändern sich Status oder
 * Menü, lädt die Seite neu – im Bearbeiten-Modus nur, wenn keine ungespeicherten Änderungen im Editor stehen.
 * Endpunkte: data.endpoint (GET/POST, Recht pages.manage), data.ai (KI-Vorschlag, Recht ai.use).
 */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const ico = n => window.CMSAdmin?.ico?.(n) || '';
const slugify = s => String(s).toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
  .normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

let S = null;   // zuletzt geladener bzw. gespeicherter Stand vom Server

async function load(ctx) {
  const P = ctx.panel.body, T = ctx.t;
  P.innerHTML = `<p class="ps-wait" role="status">${esc(T('loading'))}</p>`;
  try {
    S = await ctx.fetch(ctx.tool.data.endpoint);
  } catch (e) {
    P.innerHTML = `<p class="ps-err" role="alert">${esc(T('loadErr'))} ${esc(e.message || '')}</p>`;
    return false;
  }
  render(ctx);
  return true;
}

function render(ctx) {
  const P = ctx.panel.body, D = ctx.tool.data, T = ctx.t, pg = S.page;
  const fixedSlug = pg.home || pg.notFound;
  const st = { og: S.og, busy: false };
  const counter = (id, max) => `<p class="ps-count" id="${id}-c" data-ps-count="${id}" data-max="${max}" aria-live="polite"></p>`;
  P.innerHTML = `
  <form class="ps" data-ps novalidate>
    <fieldset class="ps-col">
      <legend class="ps-h">${esc(T('general'))}</legend>
      <div class="f"><label for="ps-title">${esc(T('title'))}</label><input id="ps-title" name="title" required maxlength="120" autocomplete="off" value="${esc(pg.title)}" aria-describedby="ps-title-e"><p class="f-error" id="ps-title-e" data-ps-err="title" hidden></p></div>
      ${fixedSlug ? `<p class="ps-note">${esc(T('slugFixed'))} <code>${esc(S.url)}</code></p>` : `
      <div class="f"><label for="ps-slug">${esc(T('slug'))}</label>
        <div class="ps-prefix"><span>${esc(S.prefix)}</span><input id="ps-slug" name="slug" maxlength="120" autocomplete="off" spellcheck="false" value="${esc(pg.slug)}" aria-describedby="ps-slug-h ps-slug-w ps-slug-e"></div>
        <p class="f-help" id="ps-slug-h">${esc(T('slugHelp'))}</p>
        <p class="f-warn" id="ps-slug-w" data-ps-slugwarn${S.reserved ? '' : ' hidden'}>${S.reserved ? esc(T('reserved', { slug: pg.slug })) : ''}</p>
        <p class="f-error" id="ps-slug-e" data-ps-err="slug" hidden></p></div>`}
      ${pg.home ? `<p class="ps-note">${esc(T('homeOnline'))}</p>` : S.canPublish ? `
      <div class="f"><label for="ps-status">${esc(T('status'))}</label><select id="ps-status" name="status">
        <option value="draft"${pg.status !== 'published' ? ' selected' : ''}>${esc(T('draft'))}</option>
        <option value="published"${pg.status === 'published' ? ' selected' : ''}>${esc(T('online'))}</option></select></div>` : ''}
      ${fixedSlug ? '' : `
      <label class="f-check f-check--switch ps-check"><input type="checkbox" role="switch" name="menu"${pg.menu ? ' checked' : ''}> <span>${esc(T('menu'))}</span></label>
      <div class="f" data-ps-nav${pg.menu ? '' : ' hidden'}><label for="ps-nav">${esc(T('navTitle'))}</label><input id="ps-nav" name="nav_title" maxlength="60" autocomplete="off" value="${esc(pg.nav_title)}" placeholder="${esc(pg.title)}"></div>`}
      ${pg.notFound ? '' : `<label class="f-check f-check--switch ps-check"><input type="checkbox" role="switch" name="noindex"${pg.noindex ? ' checked' : ''}> <span>${esc(T('noindex'))}</span></label>`}
    </fieldset>
    <fieldset class="ps-col">
      <legend class="ps-h">${esc(T('search'))}</legend>
      ${S.ai ? `<p class="ps-ai"><button type="button" class="adm-btn adm-btn--small kia-btn" data-ps-ai><span class="kia-spark" aria-hidden="true">✦</span> <span>${esc(T('ai'))}</span></button></p>` : ''}
      <div class="f"><label for="ps-mt">${esc(T('metaTitle'))}</label><input id="ps-mt" name="meta_title" maxlength="120" autocomplete="off" value="${esc(pg.meta_title)}" placeholder="${esc(pg.title)}" aria-describedby="ps-mt-h ps-mt-c">
        ${counter('ps-mt', S.titleMax)}<p class="f-help" id="ps-mt-h">${esc(T('metaTitleHelp', { suffix: S.suffix || '–' }))}</p></div>
      <div class="f"><label for="ps-md">${esc(T('metaDesc'))}</label><textarea id="ps-md" name="meta_description" rows="4" maxlength="300" aria-describedby="ps-md-h ps-md-c">${esc(pg.meta_description)}</textarea>
        ${counter('ps-md', S.descMax)}<p class="f-help" id="ps-md-h">${esc(T('metaDescHelp'))}</p></div>
      <div class="f ps-og" role="group" aria-labelledby="ps-og-l"><span class="ps-label" id="ps-og-l">${esc(T('og'))}</span>
        <div class="ps-og__prev" data-ps-og aria-live="polite"></div>
        <p class="ps-og__act">
          <button type="button" class="adm-btn adm-btn--small" data-ps-og-pick>${ico('image')}<span>${esc(T('ogPick'))}</span></button>
          <button type="button" class="adm-btn adm-btn--small" data-ps-og-up>${ico('upload-simple')}<span>${esc(T('ogUpload'))}</span></button>
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ps-og-clear>${ico('x')}<span>${esc(T('ogClear'))}</span></button>
        </p></div>
    </fieldset>
    <div class="ps-foot">
      <p class="f-error" data-ps-err="_" role="alert" hidden></p>
      <a class="ps-manage" href="${esc(D.admin)}">${esc(T('manage'))} ${ico('arrow-square-out')}</a>
      <p class="ps-actions">
        <button type="button" class="adm-btn adm-btn--small" data-ps-cancel>${esc(T('cancel'))}</button>
        <button type="submit" class="adm-btn adm-btn--small adm-btn--primary" data-ps-go>${ico('check')}<span>${esc(T('save'))}</span></button>
      </p>
    </div>
  </form>`;

  const $ = s => P.querySelector(s);
  const form = $('[data-ps]'), title = $('#ps-title'), slug = $('#ps-slug'), go = $('[data-ps-go]');
  const err = (k, msg) => { const el = P.querySelector(`[data-ps-err="${k}"]`); if (!el) return; el.textContent = msg || ''; el.hidden = !msg; };

  // Zeichenzähler (wie data-max in der Verwaltung): „n von max Zeichen“, darüber markiert
  const count = () => P.querySelectorAll('[data-ps-count]').forEach(c => {
    const inp = $('#' + c.dataset.psCount), max = +c.dataset.max, n = inp.value.length;
    c.textContent = T(n > max ? 'over' : 'count', { n, max });
    c.classList.toggle('is-over', n > max);
  });
  count();
  form.addEventListener('input', e => {
    if (e.target.matches('#ps-mt,#ps-md')) count();
    if (e.target === title) { err('title'); const nav = $('#ps-nav'); if (nav) nav.placeholder = title.value; $('#ps-mt').placeholder = title.value; }
    if (e.target === slug) { err('slug'); slugHint(); }
  });
  form.addEventListener('change', e => { if (e.target.name === 'menu') $('[data-ps-nav]').hidden = !e.target.checked; });

  // Adresse geändert: Hinweis auf Weiterleitung bzw. Warnung, wenn die Seite schon online war
  function slugHint() {
    const w = $('[data-ps-slugwarn]');
    if (!w) return;
    const now = slugify(slug.value || title.value);
    if (now === pg.slug) { w.hidden = !S.reserved; w.textContent = S.reserved ? T('reserved', { slug: pg.slug }) : ''; w.classList.remove('f-help'); return; }
    const was = pg.status === 'published' || pg.published;
    w.hidden = !was;
    w.textContent = was ? T(S.autoRedirect ? 'slugRedirect' : 'slugBreak') : '';
    w.classList.toggle('f-help', S.autoRedirect);   // Weiterleitung: Hinweis statt Warnung
  }

  // Vorschaubild
  const ogBox = $('[data-ps-og]');
  const showOg = () => {
    ogBox.innerHTML = st.og ? (st.og.thumb ? `<img src="${esc(st.og.thumb)}" alt="">` : '') + `<span>${esc(st.og.label || '')}</span>` : `<span class="ps-og__none">${esc(T('ogNone'))}</span>`;
    $('[data-ps-og-clear]').hidden = !st.og;
  };
  showOg();
  const pickOg = async upload => {
    if (!window.CMSMedia?.pick) return;
    const m = await window.CMSMedia.pick('image', upload ? { upload: true } : {});
    if (!m) return;
    st.og = { id: +m.id, thumb: m.thumb || m.url || '', label: m.alt || m.display || '' };
    showOg();
    ogBox.focus?.();
  };
  $('[data-ps-og-pick]').addEventListener('click', () => pickOg(false));
  $('[data-ps-og-up]').addEventListener('click', () => pickOg(true));
  $('[data-ps-og-clear]').addEventListener('click', () => { st.og = null; showOg(); $('[data-ps-og-pick]').focus(); });

  // KI-Vorschlag: Titel und Beschreibung ins Formular – wirksam erst mit „Speichern“
  $('[data-ps-ai]')?.addEventListener('click', async e => {
    const b = e.currentTarget, lbl = b.querySelector('span:last-child'), old = lbl.textContent;
    b.disabled = true; lbl.textContent = T('aiBusy'); err('_');
    try {
      const r = await ctx.fetch(D.ai, { json: { page: D.id } });
      const s = r.suggest || {};
      if (s.title) $('#ps-mt').value = s.title;
      if (s.description) $('#ps-md').value = s.description;
      count();
      ctx.toast(T('aiDone'), 'info');
      $('#ps-mt').focus();
    } catch (ex) { err('_', ex.message); }
    finally { b.disabled = false; lbl.textContent = old; }
  });

  $('[data-ps-cancel]').addEventListener('click', () => ctx.close());

  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (st.busy) return;
    ['title', 'slug', '_'].forEach(k => err(k));
    if (!title.value.trim()) { err('title', T('needTitle')); title.focus(); return; }
    const body = { title: title.value.trim(), meta_title: $('#ps-mt').value.trim(), meta_description: $('#ps-md').value.trim(), og_image: st.og ? st.og.id : null };
    if (slug) body.slug = slug.value.trim();
    if (form.status) body.status = form.status.value;
    if (form.menu) { body.menu = form.menu.checked; body.nav_title = $('#ps-nav').value.trim(); }
    if (form.noindex) body.noindex = form.noindex.checked;
    st.busy = true; go.disabled = true; go.querySelector('span').textContent = T('saving');
    try {
      const prev = S.page;
      const res = await ctx.fetch(D.endpoint, { json: body });
      S = res;
      applied(ctx, prev, res);
    } catch (ex) {
      const errs = ex.data?.errors || {};
      if (errs.title) err('title', errs.title);
      if (errs.slug) err('slug', errs.slug);
      const rest = Object.entries(errs).filter(([k]) => k !== 'title' && k !== 'slug').map(([, v]) => v);
      if (rest.length || !Object.keys(errs).length) err('_', rest.join(' ') || ex.message);
      (errs.title ? title : errs.slug ? slug : go).focus();
      st.busy = false; go.disabled = false; go.querySelector('span').textContent = T('save');
    }
  });
}

/** Nach dem Speichern: Seite nachführen (Kopf, Werkzeugleiste, Adresse) bzw. neu laden, wenn Menü oder Status betroffen sind */
function applied(ctx, prev, res) {
  const pg = res.page, d = document;
  const url = res.url + location.search + location.hash;
  const reload = prev.status !== pg.status || prev.menu !== pg.menu || (pg.menu && (prev.nav_title !== pg.nav_title || prev.title !== pg.title));
  const dirty = !!window.CMSEditor?.isDirty?.();
  if (reload && !dirty) {
    window.CMSAdmin?.toastNext?.(res.warning || res.message, res.warning ? 'error' : 'ok');
    location.replace(url);
    return;
  }
  if (res.moved) history.replaceState(history.state, '', url);
  const seo = res.seo;
  if (seo) {
    d.title = seo.title;
    const meta = (sel, v) => d.querySelectorAll(sel).forEach(m => { if (v) m.setAttribute('content', v); });
    meta('meta[name="description"],meta[property="og:description"],meta[name="twitter:description"]', seo.description);
    meta('meta[property="og:title"],meta[name="twitter:title"]', seo.title);
    meta('meta[property="og:image"],meta[name="twitter:image"]', seo.og_image);
    meta('meta[property="og:url"]', seo.canonical);
    d.querySelectorAll('link[rel="canonical"]').forEach(l => { if (seo.canonical) l.href = seo.canonical; });
  }
  const bar = d.querySelector('.cms-bar-host')?.shadowRoot?.querySelector('.cms-bar__title');
  if (bar) { bar.textContent = pg.title; bar.title = pg.title; }
  ctx.close();
  ctx.toast(res.warning || res.message, res.warning ? 'error' : 'ok');
  render(ctx);   // nächster Aufruf zeigt den gespeicherten Stand
}

export default {
  async mount(ctx) {
    await load(ctx);
  },
  // Erneut geöffnet: frischen Stand laden (z. B. nach Änderungen in der Verwaltung oder Abbrechen mit Änderungen)
  async show(ctx) {
    if (await load(ctx)) ctx.panel.focus();
  },
};
