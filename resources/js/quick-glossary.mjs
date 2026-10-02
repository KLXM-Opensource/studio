/*
 * Quick-Glossar (Core\Glossary\QuickTool) – Werkzeug beim Bearbeiten auf der Website, geladen erst beim ersten Öffnen
 * (CMSAdmin.tools, resources/js/_tools.js). Zugleich das Referenzbeispiel für Werkzeuge von Erweiterungen:
 *
 *   export default { mount(ctx) { … }, show(ctx) { … }, unmount(ctx) { … } }
 *
 * Reiter (WAI-ARIA tablist, ←/→/Pos1/Ende): „Suchen“ (Treffer mit Kurz-Erklärung, „Einfügen“ = Link entry:glossar:{id} an der
 * Schreibmarke bzw. um den markierten Text), „Neuer Begriff“ (vorbelegt mit dem markierten Text; Entwurf bzw. veröffentlicht je
 * nach Recht), „Auf dieser Seite“ (Begriffe, die die automatische Markierung kennzeichnen würde, „Zur Stelle“ markiert den Treffer
 * im Text – „Einfügen“ verlinkt ihn dann). Tastatur: ↓ aus dem Suchfeld in die Treffer, ↑/↓ zwischen den Treffern, Esc schließt,
 * ⌥G springt zwischen Text und Seitenleiste. Texte kommen übersetzt vom Server (ctx.t).
 *
 * Beim Ansehen (ctx.mode 'view', Werkzeug mit 'view' => true): jeder Text der Seite ist markierbar. Markierung + ⌥G (oder der
 * schwebende Knopf „Als Glossar-Begriff“) öffnet „Neuer Begriff“ vorbelegt und prüft „Gibt es schon?“. Nach dem Anlegen: Hinweis
 * auf die automatische Markierung und „Seite neu laden“. „Einfügen“ gibt es nur beim Bearbeiten – beim Ansehen „Öffnen“ (Verwaltung).
 */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const ico = n => window.CMSAdmin?.ico?.(n) || '';
const TABS = ['search', 'new', 'page'];

function view(ctx) {
  const T = ctx.t, D = ctx.tool.data || {};
  const pub = !!D.publish;
  return `
  <p class="qg-ctx" data-qg-ctx aria-live="polite"></p>
  <div class="qg-tabs" role="tablist" aria-label="${esc(ctx.tool.label)}">
    ${TABS.map((k, i) => `<button type="button" role="tab" class="qg-tab" id="qg-tab-${k}" aria-controls="qg-p-${k}" aria-selected="${i === 0}" tabindex="${i === 0 ? 0 : -1}" data-qg-tab="${k}">${esc(T({ search: 'tabSearch', new: 'tabNew', page: 'tabPage' }[k]))}</button>`).join('')}
  </div>
  <div class="qg-panel" role="tabpanel" id="qg-p-search" aria-labelledby="qg-tab-search">
    <div class="qg-search">${ico('magnifying-glass')}<input type="search" data-qg-q aria-label="${esc(T('search'))}" placeholder="${esc(T('searchPh'))}" autocomplete="off" spellcheck="false" autofocus></div>
    <p class="qg-status" data-qg-status role="status"></p>
    <ul class="qg-list" data-qg-list aria-label="${esc(T('tabSearch'))}"></ul>
    <p class="qg-viewnote" data-qg-viewnote hidden>${esc(T('viewLink'))}${D.editUrl ? ` <a href="${esc(D.editUrl)}">${esc(T('toEdit'))}</a>` : ''}</p>
    <p class="qg-foot"><a href="${esc(D.admin || '#')}" target="_blank" rel="noopener">${esc(T('manage'))} ${ico('arrow-square-out')}</a></p>
  </div>
  <div class="qg-panel" role="tabpanel" id="qg-p-new" aria-labelledby="qg-tab-new" hidden>
    <form class="qg-form" data-qg-form novalidate>
      <div class="f"><label for="qg-term">${esc(T('term'))}</label><input id="qg-term" name="term" required maxlength="120" autocomplete="off" aria-describedby="qg-term-e"><p class="f-error" id="qg-term-e" data-qg-err="term" hidden></p><p class="qg-dupe" data-qg-dupe aria-live="polite"></p></div>
      <div class="f"><label for="qg-short">${esc(T('short'))}</label><textarea id="qg-short" name="short" rows="3" required maxlength="${(D.shortMax || 240) + 40}" aria-describedby="qg-short-h qg-short-c qg-short-e"></textarea>
        <p class="f-help" id="qg-short-h">${esc(T('shortHelp', { n: D.shortMax || 240 }))}</p><p class="f-help qg-count" id="qg-short-c" data-qg-count aria-live="polite"></p><p class="f-error" id="qg-short-e" data-qg-err="short" hidden></p></div>
      <div class="f"><label for="qg-var">${esc(T('variants'))}</label><input id="qg-var" name="variants" maxlength="400" autocomplete="off" aria-describedby="qg-var-h"><p class="f-help" id="qg-var-h">${esc(T('variantsHelp'))}</p></div>
      <details class="qg-more"><summary>${esc(T('long'))}</summary><div class="f"><label class="sr-only" for="qg-long">${esc(T('long'))}</label><textarea id="qg-long" name="long" rows="4"></textarea></div></details>
      ${pub ? '' : `<p class="qg-note">${esc(T('draftOnly'))}</p>`}
      <p class="qg-auto" data-qg-auto hidden>${esc(D.mode === 'off' ? T('autoOff') : T('autoHint') + (D.workflow !== false ? ' ' + T('autoDraft') : ''))}</p>
      <p class="qg-actions">
        <button type="submit" class="adm-btn adm-btn--small${pub ? '' : ' adm-btn--primary'}" data-qg-save="draft">${esc(D.workflow === false ? T('saveOnly') : T('saveDraft'))}</button>
        ${pub ? `<button type="submit" class="adm-btn adm-btn--small adm-btn--primary" data-qg-save="publish">${esc(T('savePublish'))}</button>` : ''}
      </p>
      <p class="f-error" data-qg-err="_" role="alert" hidden></p>
      <div class="qg-done" data-qg-done hidden></div>
    </form>
  </div>
  <div class="qg-panel" role="tabpanel" id="qg-p-page" aria-labelledby="qg-tab-page" hidden>
    <p class="qg-intro">${esc(T('pageIntro'))}</p>
    <p class="qg-status" data-qg-pstatus role="status"></p>
    <ul class="qg-list" data-qg-plist aria-label="${esc(T('tabPage'))}"></ul>
    <p><button type="button" class="adm-btn adm-btn--small" data-qg-recheck>${ico('arrows-clockwise')} ${esc(T('pageCheck'))}</button></p>
  </div>`;
}

function itemHtml(ctx, it, i, kind) {
  const T = ctx.t;
  const badge = (it.draft ? ` <span class="qg-badge" title="${esc(T('draftLink'))}">${esc(T('draft'))}</span>` : '')
    // Geteiltes Glossar: Begriff einer anderen Website (nur dort änderbar)
    + (it.foreign && it.origin ? ` <span class="qg-badge" title="${esc(T('foreignHint'))}">${esc(T('from', { site: it.origin }))}</span>` : '');
  const variants = it.variants?.length ? `<span class="qg-var">${esc(it.variants.join(', '))}</span>` : '';
  const match = kind === 'page' && it.match && it.match.toLowerCase() !== it.term.toLowerCase() ? `<span class="qg-var">„${esc(it.match)}“</span>` : '';
  const btn = kind === 'page'
    ? `<button type="button" class="adm-btn adm-btn--small" data-qg-jump="${i}" aria-label="${esc(T('jumpAria', { term: it.term }))}">${ico('navigation-arrow')}<span>${esc(T('jump'))}</span></button>`
    // Ansehen: kein Einfügen (kein Textfeld) – Begriff in der Verwaltung öffnen
    : ctx.mode === 'view'
    ? `<a class="adm-btn adm-btn--small" href="${esc(it.edit)}" target="_blank" rel="noopener" data-qg-open aria-label="${esc(T('openAria', { term: it.term }))}">${ico('arrow-square-out')}<span>${esc(T('open'))}</span></a>`
    : `<button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-qg-ins="${i}" aria-label="${esc(T('insertAria', { term: it.term }))}">${ico('link')}<span>${esc(T('insert'))}</span></button>`;
  return `<li class="qg-item"><div class="qg-item__txt"><b class="qg-term">${esc(it.term)}</b>${badge}${variants}${match}<span class="qg-short">${esc(it.short)}</span></div>${btn}</li>`;
}

export default {
  mount(ctx) {
    const P = ctx.panel.body, D = ctx.tool.data || {}, T = ctx.t;
    const st = { items: [], pitems: [], seq: 0, timer: 0, tab: 'search' };
    ctx._qg = st;
    P.innerHTML = view(ctx);
    const $ = s => P.querySelector(s), $$ = s => [...P.querySelectorAll(s)];
    const q = $('[data-qg-q]'), list = $('[data-qg-list]'), status = $('[data-qg-status]'), form = $('[data-qg-form]');

    // ---------------- Reiter
    const tab = (k, focus = false) => {
      st.tab = k;
      $$('[data-qg-tab]').forEach(b => { const on = b.dataset.qgTab === k; b.setAttribute('aria-selected', String(on)); b.tabIndex = on ? 0 : -1; if (on && focus) b.focus(); });
      TABS.forEach(x => { $('#qg-p-' + x).hidden = x !== k; });
      if (k === 'new') prefill();
      if (k === 'page') checkPage();
      // Fokus beim (erneuten) Öffnen: Feld des sichtbaren Reiters (ctx.panel.focus() sucht [autofocus])
      $$('[autofocus]').forEach(x => x.removeAttribute('autofocus'));
      const af = k === 'search' ? q : k === 'new' ? ($('#qg-term').value.trim() ? $('#qg-short') : $('#qg-term')) : $('#qg-tab-page');
      af?.setAttribute('autofocus', '');
    };
    st.tabFn = tab;
    $('.qg-tabs').addEventListener('click', e => { const b = e.target.closest('[data-qg-tab]'); if (b) tab(b.dataset.qgTab); });
    $('.qg-tabs').addEventListener('keydown', e => {
      const i = TABS.indexOf(st.tab);
      const n = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: TABS.length - 1 }[e.key];
      if (n === undefined) return;
      e.preventDefault();
      tab(TABS[(n + TABS.length) % TABS.length], true);
    });

    // ---------------- Zielort (markierter Text / Schreibmarke) anzeigen
    st.ctxLine = () => {
      const s = ctx.selection(), el = $('[data-qg-ctx]');
      if (ctx.mode === 'view') {
        const txt = s?.text.trim().replace(/\s+/g, ' ') || '';
        el.className = 'qg-ctx';
        el.textContent = txt ? T('viewSelected', { text: txt.slice(0, 60) }) : T('viewCtx', { key: ctx.tool.shortcut?.label || '' });
        return;
      }
      if (!s) { el.textContent = T('noText'); el.className = 'qg-ctx is-warn'; return; }
      const field = s.editable.getAttribute('aria-label') || s.editable.dataset.entryLabel || '';
      el.className = 'qg-ctx';
      el.textContent = s.text.trim() ? T('selected', { text: s.text.trim().slice(0, 60) }) : (field ? T('cursor', { field }) : T('cursorAny'));
    };

    // ---------------- Suchen
    const render = res => {
      st.lastRes = res;
      st.items = res.items || [];
      list.innerHTML = st.items.map((it, i) => itemHtml(ctx, it, i, 'search')).join('');
      const qs = q.value.trim();
      if (!st.items.length) {
        status.innerHTML = esc(T('noResults')) + (qs ? ` <button type="button" class="qg-linkbtn" data-qg-newfrom>${esc(T('newFrom', { term: qs }))}</button>` : '');
      } else {
        status.textContent = (res.total === 1 ? T('result1') : T('results', { n: res.total })) + (res.total > st.items.length ? ' ' + T('more') : '');
      }
    };
    const search = async () => {
      const seq = ++st.seq;
      try {
        const res = await ctx.fetch(ctx.tool.endpoints.search, { query: { q: q.value.trim(), lang: D.lang || ctx.lang } });
        if (seq === st.seq) render(res);
      } catch (e) { if (seq === st.seq) status.textContent = e.message; }
    };
    st.search = search;
    q.addEventListener('input', () => { clearTimeout(st.timer); st.timer = setTimeout(search, 180); });
    q.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown') { const b = $('[data-qg-ins],[data-qg-open]'); if (b) { e.preventDefault(); b.focus(); } }
      else if (e.key === 'Enter') { e.preventDefault(); clearTimeout(st.timer); search().then(() => { if (st.items.length === 1 && ctx.mode !== 'view') insert(st.items[0]); }); }
    });
    // ↑/↓ zwischen den Knöpfen einer Liste; ↑ am Anfang zurück ins Suchfeld
    P.addEventListener('keydown', e => {
      const b = e.target.closest?.('[data-qg-ins],[data-qg-open],[data-qg-jump]');
      if (!b || !['ArrowDown', 'ArrowUp'].includes(e.key)) return;
      const all = [...b.closest('ul').querySelectorAll('[data-qg-ins],[data-qg-open],[data-qg-jump]')], i = all.indexOf(b);
      e.preventDefault();
      if (e.key === 'ArrowUp' && i === 0 && b.dataset.qgJump === undefined) q.focus();
      else all[Math.max(0, Math.min(all.length - 1, i + (e.key === 'ArrowDown' ? 1 : -1)))]?.focus();
    });

    // ---------------- Einfügen (Link entry:glossar:{id} – Rich.insertLink über ctx.insertLink)
    const insert = it => {
      if (ctx.mode === 'view') { ctx.toast(T('viewLink'), 'error'); return; }
      const r = ctx.insertLink({ href: it.href || '#', ref: it.ref, label: it.term });
      if (!r) { st.ctxLine(); ctx.toast(T('noText'), 'error'); return; }
      const msg = r === 'text' ? T('insertedText', { term: it.term }) : T('inserted', { term: it.term }) + (it.draft ? ' ' + T('draftLink') : '');
      ctx.toast(msg); ctx.announce(msg);
    };
    st.insert = insert;
    P.addEventListener('click', e => {
      const ins = e.target.closest('[data-qg-ins]');
      if (ins) { insert((ins.closest('[data-qg-plist]') ? st.pitems : st.items)[+ins.dataset.qgIns]); return; }
      const j = e.target.closest('[data-qg-jump]');
      if (j) { jump(st.pitems[+j.dataset.qgJump]); return; }
      if (e.target.closest('[data-qg-newfrom]')) { tab('new'); $('#qg-term').value = q.value.trim(); $('#qg-short').focus(); return; }
      if (e.target.closest('[data-qg-recheck]')) checkPage();
      const ex = e.target.closest('[data-qg-show]');
      if (ex) { tab('search', true); q.value = ex.dataset.qgShow; search(); }
      const now = e.target.closest('[data-qg-insnew]');
      if (now && st.created) insert(st.created);
      if (e.target.closest('[data-qg-reload]')) location.reload();
    });

    // ---------------- Neuer Begriff
    const counter = () => { const n = $('#qg-short').value.trim().length, max = D.shortMax || 240; const c = $('[data-qg-count]'); c.textContent = T('chars', { n, max }); c.classList.toggle('is-long', n > max); };
    $('#qg-short').addEventListener('input', counter);
    // Vorbelegen mit der Markierung: leeres Feld, oder das Feld enthält noch die letzte Vorbelegung (nicht von Hand geändert)
    const prefill = () => {
      const s = ctx.selection();
      const sel = s?.text.trim().replace(/\s+/g, ' ') || '';
      const term = $('#qg-term');
      if (sel && sel.length <= 120 && (!term.value || (term.value === st.prefilled && sel !== st.prefilled))) { term.value = sel; st.prefilled = sel; }
      $('[data-qg-done]').hidden = true;
      $('[data-qg-auto]').hidden = ctx.mode !== 'view';
      counter();
      dupe(0);
    };
    // „Gibt es schon?“ – Suche nach dem eingegebenen Begriff (gleich, Variante, ähnlich)
    const dupe = (wait = 220) => {
      clearTimeout(st.dt);
      st.dt = setTimeout(async () => {
        const v = $('#qg-term').value.trim(), box = $('[data-qg-dupe]');
        if (!v) { box.textContent = ''; return; }
        const seq = st.dseq = (st.dseq || 0) + 1;
        try {
          const res = await ctx.fetch(ctx.tool.endpoints.search, { query: { q: v, lang: D.lang || ctx.lang, limit: 4 } });
          if (seq !== st.dseq) return;
          const items = res.items || [], lv = v.toLowerCase();
          const exact = items.find(it => it.term.toLowerCase() === lv || (it.variants || []).some(x => x.toLowerCase() === lv));
          const show = it => `<button type="button" class="qg-linkbtn" data-qg-show="${esc(it.term)}">${esc(it.term)}</button>`;
          box.innerHTML = `<b>${esc(T('dupeQ'))}</b> ` + (exact ? `${esc(T('exists', { term: exact.term }))} ${show(exact)}`
            : items.length ? `${esc(T('dupeSome'))} ${items.slice(0, 3).map(show).join(', ')}` : esc(T('dupeNone')));
        } catch { box.textContent = ''; }
      }, wait);
    };
    $('#qg-term').addEventListener('input', () => dupe());
    // Ansehen mit neuer Markierung: gleich „Neuer Begriff“ (vorbelegt) – Suchfeld mit, „Gibt es schon?“ prüft der Reiter
    st.viewPick = () => {
      const txt = ctx.mode === 'view' ? (ctx.selection()?.text.trim().replace(/\s+/g, ' ') || '') : '';
      if (!txt || txt.length > 120 || txt === st.picked) return false;
      st.picked = txt;
      q.value = txt;
      search();
      tab('new');
      return true;
    };
    const err = (field, msg) => {
      $$('[data-qg-err]').forEach(p => { p.hidden = true; p.textContent = ''; });
      $$('#qg-term,#qg-short').forEach(i => i.removeAttribute('aria-invalid'));
      if (!msg) return;
      const p = $(`[data-qg-err="${field}"]`) || $('[data-qg-err="_"]');
      p.textContent = msg; p.hidden = false;
      const inp = field === 'term' ? $('#qg-term') : field === 'short' ? $('#qg-short') : null;
      if (inp) { inp.setAttribute('aria-invalid', 'true'); inp.focus(); }
    };
    let saveMode = 'draft';
    form.addEventListener('click', e => { const b = e.target.closest('[data-qg-save]'); if (b) saveMode = b.dataset.qgSave; });
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const f = new FormData(form), term = String(f.get('term') || '').trim(), short = String(f.get('short') || '').trim();
      if (!term) return err('term', T('requiredTerm'));
      if (!short) return err('short', T('requiredShort'));
      if (short.length > (D.shortMax || 240)) return err('short', T('tooLong', { n: D.shortMax || 240 }));
      err('', '');
      const btns = $$('[data-qg-save]');
      btns.forEach(b => b.setAttribute('aria-busy', 'true'));
      try {
        const res = await ctx.fetch(ctx.tool.endpoints.create, { json: { term, short, variants: f.get('variants') || '', long: f.get('long') || '', publish: saveMode === 'publish', lang: D.lang || ctx.lang } });
        st.created = res.item;
        const msg = (res.published ? T('createdPub') : T('createdDraft')).replace('{term}', res.item.term);
        ctx.toast(msg); ctx.announce(msg);
        form.reset(); counter();
        const done = $('[data-qg-done]');
        done.hidden = false;
        $('[data-qg-dupe]').textContent = '';
        st.prefilled = '';
        if (ctx.mode === 'view') {
          // Ansehen: die automatische Markierung zeigt den Begriff nach dem Neuladen (Entwurf: nur für die Redaktion)
          const auto = D.mode === 'off' ? T('autoOff') : res.published ? '' : T('autoHint') + (D.workflow !== false ? ' ' + T('autoDraft') : '');
          $('[data-qg-auto]').hidden = true;   // steht jetzt in der Bestätigung
          done.innerHTML = `<p role="status">${ico('check-circle')} ${esc(msg)}</p>${auto ? `<p class="qg-note">${esc(auto)}</p>` : ''}<p class="qg-actions"><button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-qg-reload>${ico('arrows-clockwise')} ${esc(T('reload'))}</button></p>`;
          done.querySelector('[data-qg-reload]').focus();
        } else {
          done.innerHTML = `<p role="status">${ico('check-circle')} ${esc(msg)}</p><button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-qg-insnew>${ico('link')} ${esc(T('insertNew'))}</button>`;
          done.querySelector('[data-qg-insnew]').focus();
        }
        search();
      } catch (e2) {
        const d = e2.data || {};
        err(d.field || '_', e2.message);
        if (d.exists) {
          const p = $(`[data-qg-err="${d.field || '_'}"]`);
          p.insertAdjacentHTML('beforeend', ` <button type="button" class="qg-linkbtn" data-qg-show="${esc(d.exists.term)}">${esc(T('showExisting'))}</button>`);
        }
      } finally {
        btns.forEach(b => b.removeAttribute('aria-busy'));
      }
    });

    // ---------------- Auf dieser Seite (Annotator auf dem Inhalt der Seite)
    const root = () => document.getElementById('cms-editor') || document.querySelector('main') || document.body;
    const pageHtml = () => {
      const c = root().cloneNode(true);
      // Ansehen: vorhandene Markierungen (.gl, Annotator) wieder zu Text – sonst zählten markierte Begriffe nicht
      c.querySelectorAll('.gl').forEach(n => n.replaceWith(n.querySelector('.gl-term')?.textContent || ''));
      c.querySelectorAll('script,style,template,noscript,.cms-block__bar,.cms-block__add,.cms-lay-bar,.cms-lay-add,.ce-toolbar,.ce-inline-toolbar,.ce-popover,[data-cms-note],.cms-bar-host,#cms-layer-host,#cms-epanel-host').forEach(n => n.remove());
      return c.innerHTML;
    };
    const checkPage = async () => {
      const ps = $('[data-qg-pstatus]'), pl = $('[data-qg-plist]');
      ps.textContent = T('loading');
      try {
        const res = await ctx.fetch(ctx.tool.endpoints.page, { json: { html: pageHtml(), lang: D.lang || ctx.lang, path: D.path || location.pathname, self: D.self || 0 } });
        st.pitems = res.items || [];
        pl.innerHTML = st.pitems.map((it, i) => itemHtml(ctx, it, i, 'page')).join('');
        const note = res.mode === 'off' ? ' ' + T('pageOff') : res.excluded ? ' ' + T('pageExcluded') : '';
        ps.textContent = (st.pitems.length ? (st.pitems.length === 1 ? T('result1') : T('results', { n: st.pitems.length })) : T('pageNone')) + note;
      } catch (e) { ps.textContent = e.message; }
    };
    // Erste Fundstelle im Text suchen (ohne Überschriften bis h{headings}, Links, Knöpfe, Code – wie die Markierung)
    const SKIP = 'a,button,code,pre,kbd,nav,label,summary,[data-glossary=off],[aria-hidden=true],.cms-note,.cms-block__bar,h1,h2,h3';
    const jump = it => {
      if (!it) return;
      const w = document.createTreeWalker(root(), NodeFilter.SHOW_TEXT);
      const needle = (it.match || it.term).toLowerCase();
      let range = null;
      for (let n = w.nextNode(); n; n = w.nextNode()) {
        const i = n.data.toLowerCase().indexOf(needle);
        const pe = n.parentElement;
        // Ansehen: bereits markierte Begriffe (.gl-term ist ein Knopf) zählen, das Hinweisfenster nicht
        if (i < 0 || !pe || pe.closest('.gl-pop,.cms-bar-host') || (!pe.closest('.gl-term') && pe.closest(SKIP)) || !pe.getClientRects().length) continue;
        range = document.createRange(); range.setStart(n, i); range.setEnd(n, i + needle.length);
        break;
      }
      if (!range) { ctx.toast(T('notFound'), 'error'); return; }
      const el = range.startContainer.parentElement;
      el.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      if (window.Highlight && CSS.highlights) {
        CSS.highlights.set('cms-gl-hit', new Highlight(range));
        clearTimeout(st.hl); st.hl = setTimeout(() => CSS.highlights.delete('cms-gl-hit'), 4000);
      }
      // In einem bearbeitbaren Text: Treffer auswählen – „Einfügen“ (Suchen) verlinkt ihn
      if (el.isContentEditable) { const s = getSelection(); s.removeAllRanges(); s.addRange(range.cloneRange()); }
      st.ctxLine();
    };

    // Zustand außerhalb: Auswahl im Text geändert → Zielzeile aktualisieren
    ctx.on('selectionchange', () => { if (!ctx.panel.el.hidden) { clearTimeout(st.sc); st.sc = setTimeout(st.ctxLine, 120); } });
    ctx.on('cms:saved', () => { if (st.tab === 'page' && !ctx.panel.el.hidden) checkPage(); });
    // Eintrag: Ansehen ↔ Bearbeiten ohne Neuladen → Knöpfe „Einfügen“/„Öffnen“ und Hinweise umstellen
    st.applyMode = () => {
      const view = ctx.mode === 'view';
      $('[data-qg-viewnote]').hidden = !view;
      $('[data-qg-auto]').hidden = !view;
      if (st.lastRes) render(st.lastRes);
      st.ctxLine();
    };
    ctx.on('cms:mode-change', () => st.applyMode());
    st.applyMode();
    if (!st.viewPick()) { tab('search'); search(); }
  },

  /** Erneut geöffnet: Zielzeile aktualisieren, Suchfeld bzw. Neuer Begriff vorbelegen */
  show(ctx) {
    const st = ctx._qg;
    if (!st) return;
    st.applyMode();
    if (st.viewPick()) return;
    st.tabFn(st.tab);
  },

  unmount(ctx) {
    const st = ctx._qg;
    if (st) { clearTimeout(st.timer); clearTimeout(st.hl); clearTimeout(st.sc); }
    if (window.CSS?.highlights) CSS.highlights.delete('cms-gl-hit');
    ctx.panel.body.innerHTML = '';
  },
};
