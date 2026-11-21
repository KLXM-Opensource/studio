/*
 * „Aus Vorlage erstellen“ (Persona „Bild“, Ausbaustufe 6): die Redaktion füllt eine Vorlage der Agentur aus – ohne Editor.
 * Formular links (Bild über den Medienwähler, Texte mit Höchstlänge, Design-Farben, Ein/Aus), Vorschau rechts je Format, Formate
 * wählen → fertige Pixelbilder in der Mediathek (Core\Graphics\Compose, mit Verweis auf Vorlage und Werte – „Felder ändern“ öffnet
 * das Formular wieder). Text passt sich an: Schrift schrumpft bis zur Mindestgröße, dann ein Hinweis am Feld.
 *   CMSGraphicEditor.fill({ id?: Bild (Komposition) zum Ändern, template?: 'site:1', returnFocus }) → Promise<{ saved: {outputs} | null }>
 * Alle Aufrufe an das CMS über host.js. MIT
 */
import { t } from '../_i18n.js';
import { esc, csrf } from '../_html.js';
import { ico } from '../_icons.js';
import { toast } from '../_toast.js';
import { studioHost } from './host.js';
import { Photos, toWork, renderImage, outScale, outType } from './photo.js';
import { fieldList, applyValues, valueOf } from './fields.js';
import { FORMATS, fmtLabel, baseBox, boards, addFormat } from './formats.js';
import { altFromTexts } from './bild.js';

const bootUrl = () => document.documentElement.dataset.grafikBoot || '/admin/api/grafik/boot';
const svgImg = svg => 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);

async function getJson(url) {
  const r = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() } });
  const j = await r.json().catch(() => ({ ok: false }));
  if (!r.ok || j.ok === false) throw new Error(j.error || t('Der Grafik-Editor konnte nicht geladen werden.'));
  return j;
}

/** Schriften der Website laden (wie der Editor) – Texte werden mit ihnen gemessen und eingepasst */
function loadFonts(files) {
  for (const f of Object.values(files || {})) {
    if (!f.css || document.querySelector(`link[data-ge-font="${CSS.escape(f.css)}"]`)) continue;
    const l = document.createElement('link');
    l.rel = 'stylesheet'; l.href = f.css; l.dataset.geFont = f.css;
    document.head.append(l);
  }
}

export async function fill(opts = {}) {
  const back = opts.returnFocus || document.activeElement;
  let boot, list;
  try {
    boot = await getJson(bootUrl() + (opts.id ? '?id=' + encodeURIComponent(opts.id) : '?format=info'));
    const host = opts.cms || studioHost(boot);
    list = await host.templates.list();
    boot.cms = host;
  } catch (e) { toast(e.message, 'error'); return { saved: null }; }
  boot.fontFiles = list.fontFiles || boot.fontFiles;
  loadFonts(boot.fontFiles);
  const host = boot.cms;
  const tokens = list.swatches || [];
  const templates = list.templates || [];
  return new Promise(resolve => {
    const dlg = document.createElement('dialog');
    dlg.className = 'ge-fill adm-ui';
    dlg.setAttribute('aria-labelledby', 'ge-fill-h');
    document.body.append(dlg);
    let saved = null;
    const close = () => { dlg.close(); dlg.remove(); document.documentElement.classList.remove('ge-open'); if (back?.isConnected) back.focus?.(); resolve({ saved }); };
    dlg.addEventListener('cancel', e => { e.preventDefault(); if (!busy) close(); });
    document.documentElement.classList.add('ge-open');
    let tpl = null, vals = {}, fmts = new Set(['main']), cur = 'main', busy = false, notes = [];
    const photos = new Photos({ root: null, info: { ...(list.photos || {}), ...(boot.photos || {}) }, host });
    let root = null, shadow = null, fullVb = '';

    // ------------------------------------------------------------- Schritt 1: Vorlage wählen
    const chooser = () => {
      dlg.innerHTML = `<div class="ge-fill__head"><h2 id="ge-fill-h">${esc(t('Aus Vorlage erstellen'))}</h2><button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-close>${esc(t('Abbrechen'))}</button></div>
        <div class="ge-fill__body">${templates.length ? `<p class="ge-fill__hint">${esc(t('Vorlage wählen – danach nur noch Bild und Texte eintragen.'))}</p>
          <ul class="ge-fill__tpls" aria-label="${esc(t('Vorlagen'))}">${templates.map(x => `<li><button type="button" class="ge-fill__tpl" data-tpl="${esc(x.ref)}"><img alt="" src="${esc(svgImg(x.svg))}"><span><strong>${esc(x.name)}</strong><span>${esc(`${x.width} × ${x.height}`)} · ${esc(x.scope === 'network' ? t('alle Websites') : t('diese Website'))} · ${esc(t('{n} Felder', { n: x.fields.length }))}</span></span></button></li>`).join('')}</ul>`
          : `<p class="ge-fill__hint">${esc(t('Noch keine Vorlagen. Vorlagen gestaltet die Agentur bzw. Administration im Grafik-Editor (Persona Bild → Vorlage).'))}</p>`}</div>`;
      dlg.querySelector('[data-close]').addEventListener('click', close);
      dlg.querySelectorAll('[data-tpl]').forEach(b => b.addEventListener('click', () => start(templates.find(x => x.ref === b.dataset.tpl))));
      dlg.querySelector('[data-tpl]')?.focus();
    };

    // ------------------------------------------------------------- Schritt 2: Formular + Vorschau
    const start = (x, preset = null) => {
      tpl = x;
      // Vorgaben aus der Vorlage (bzw. gespeicherte Werte beim Ändern)
      const doc = document.importNode(new DOMParser().parseFromString(x.svg, 'image/svg+xml').documentElement, true);
      const holder = document.createElement('div');
      holder.style.cssText = 'position:fixed;left:-9999px;top:0;width:800px;height:600px;visibility:hidden';
      holder.append(doc); document.body.append(holder);
      vals = {};
      for (const it of fieldList(doc)) vals[it.f.k] = it.f.t === 'toggle' ? it.f.on !== false : valueOf(it, tokens);
      holder.remove();
      if (preset) Object.assign(vals, preset);
      const tb = boards(doc).filter(b => b.id !== 'main');
      const base = baseBox(doc);
      const offer = [{ id: 'main', label: t('Format der Vorlage ({w} × {h})', { w: Math.round(base.w), h: Math.round(base.h) }) },
        ...tb.map(b => ({ id: b.id, label: b.n, board: true })),
        ...FORMATS.filter(f => !(f.w === Math.round(base.w) && f.h === Math.round(base.h)) && !tb.some(b => b.fmt === f.id)).map(f => ({ id: 'f-' + f.id, label: f.label(), f }))];
      dlg.innerHTML = `<div class="ge-fill__head"><h2 id="ge-fill-h">${esc(x.name)}</h2>
          ${opts.id ? '' : `<button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-backtpl>${ico('arrow-left')} ${esc(t('Andere Vorlage'))}</button>`}
          <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-close>${esc(t('Abbrechen'))}</button></div>
        <div class="ge-fill__grid">
          <form class="ge-fill__form" novalidate>
            ${fieldList(doc).map(({ f }) => fieldHtml(f)).join('')}
            <fieldset class="ge-fill__fmts"><legend>${esc(t('Formate'))}</legend>${offer.map(o => `<label class="f-check"><input type="checkbox" data-fmt="${esc(o.id)}"${fmts.has(o.id) ? ' checked' : ''}> ${esc(o.label)}</label>`).join('')}</fieldset>
            <fieldset class="ge-fill__meta"><legend>${esc(t('Mediathek'))}</legend>
              <label class="f">${esc(t('Dateiname'))}<input name="name" maxlength="120" required value="${esc(opts.name || x.name)}"></label>
              <label class="f">${esc(t('Alt-Text (Bildbeschreibung)'))}<textarea name="alt" rows="2" maxlength="250" data-alt></textarea><span class="f-help">${esc(t('Vorschlag aus den Texten – bitte ergänzen, was das Foto zeigt.'))}</span></label>
            </fieldset>
          </form>
          <div class="ge-fill__preview"><div class="ge-fill__tabs" role="radiogroup" aria-label="${esc(t('Vorschau'))}"></div><div class="ge-fill__stage" data-stage></div><p class="ge-fill__note" aria-live="polite" data-pnote></p></div>
        </div>
        <div class="ge-fill__foot"><span class="ge-fill__status" aria-live="polite" data-status></span><button type="button" class="adm-btn adm-btn--primary" data-create>${ico('image')} ${esc(opts.id ? t('Bilder aktualisieren') : t('Bilder erstellen'))}</button></div>`;
      dlg.offer = offer;
      dlg.querySelector('[data-close]').addEventListener('click', close);
      dlg.querySelector('[data-backtpl]')?.addEventListener('click', chooser);
      const stage = dlg.querySelector('[data-stage]');
      shadow = stage.attachShadow({ mode: 'open' });
      // Vorschau genau im Seitenverhältnis des Formats (sonst zeigte „meet“ die Nachbar-Formate am Rand)
      shadow.innerHTML = '<style>:host{all:initial;display:flex;align-items:center;justify-content:center;width:100%;height:100%;overflow:hidden}svg{display:block;flex:0 0 auto;box-shadow:0 1px 4px rgba(0,0,0,.25)}</style>';
      new ResizeObserver(() => requestAnimationFrame(() => root && show())).observe(stage);
      bindForm();
      rebuild(true);
      dlg.querySelector('.ge-fill__form input, .ge-fill__form textarea, .ge-fill__form button')?.focus();
    };

    const fieldHtml = f => {
      const id = 'gf-' + f.k, v = vals[f.k];
      if (f.t === 'text') return `<div class="f ge-fill__f"><label for="${id}">${esc(f.n)}</label>${f.ml ? `<textarea id="${id}" data-k="${esc(f.k)}" rows="${Math.min(5, f.lines || 3)}" maxlength="${f.max || 200}" aria-describedby="${id}-n">${esc(v || '')}</textarea>` : `<input id="${id}" data-k="${esc(f.k)}" maxlength="${f.max || 200}" value="${esc(v || '')}" aria-describedby="${id}-n">`}
        <span class="f-help" id="${id}-n" data-note="${esc(f.k)}">${esc(t('{n} von {max} Zeichen', { n: String(v || '').length, max: f.max || 200 }))}</span></div>`;
      if (f.t === 'image') {
        const info = photos.info[v];
        return `<div class="f ge-fill__f"><span class="ge-fill__l" id="${id}-l">${esc(f.n)}</span><div class="ge-fill__img">${info ? `<img alt="" src="${esc(info.url)}">` : ''}<span>${esc(info?.name || t('Kein Bild gewählt'))}</span>
          <button type="button" class="adm-btn adm-btn--small" data-pick="${esc(f.k)}" aria-describedby="${id}-l">${ico('image')} ${esc(t('Bild wählen …'))}</button></div></div>`;
      }
      if (f.t === 'color') return `<fieldset class="ge-fill__f ge-fill__colors"><legend>${esc(f.n)}</legend>${tokens.map(s => `<label class="ge-fill__sw"><input type="radio" name="${id}" data-k="${esc(f.k)}" value="${esc(s.token)}"${s.token === v ? ' checked' : ''}><span class="ge-fill__chip" style="background:${esc(s.color)}"></span>${esc(s.name)}</label>`).join('')}</fieldset>`;
      if (f.t === 'toggle') return `<label class="f-check ge-fill__f"><input type="checkbox" data-k="${esc(f.k)}"${v ? ' checked' : ''}> ${esc(f.n)}</label>`;
      return '';
    };

    let timer = 0;
    const soon = () => { clearTimeout(timer); timer = setTimeout(() => rebuild(), 160); };
    const bindForm = () => {
      const form = dlg.querySelector('.ge-fill__form');
      form.addEventListener('input', e => {
        const x = e.target, k = x.dataset.k;
        if (k) {
          const f = tpl.fields.find(z => z.k === k);
          vals[k] = x.type === 'checkbox' ? x.checked : x.type === 'radio' ? x.value : x.value;
          if (f?.t === 'text') { const n = dlg.querySelector(`[data-note="${CSS.escape(k)}"]`); if (n) n.textContent = t('{n} von {max} Zeichen', { n: x.value.length, max: f.max || 200 }); }
          soon();
        }
      });
      form.addEventListener('change', e => {
        const x = e.target;
        if (x.dataset.fmt) { x.checked ? fmts.add(x.dataset.fmt) : fmts.delete(x.dataset.fmt); if (!fmts.has(cur)) cur = [...fmts][0] || 'main'; rebuild(); }
        else if (x.dataset.k && (x.type === 'checkbox' || x.type === 'radio')) { vals[x.dataset.k] = x.type === 'checkbox' ? x.checked : x.value; rebuild(); }
      });
      form.addEventListener('click', async e => {
        const b = e.target.closest('[data-pick]');
        if (!b) return;
        const m = await host.media.pick();
        if (!m) { b.focus(); return; }
        await photos.need([+m.id]);
        if (!photos.info[m.id]) { toast(t('Bitte ein Foto wählen (JPG, PNG, WebP).'), 'warn'); b.focus(); return; }
        vals[b.dataset.pick] = +m.id;
        const wrap = b.closest('.ge-fill__img');
        const info = photos.info[m.id];
        wrap.querySelector('img')?.remove();
        wrap.insertAdjacentHTML('afterbegin', `<img alt="" src="${esc(info.url)}">`);
        wrap.querySelector('span').textContent = info.name;
        b.focus();
        rebuild();
      });
      dlg.querySelector('[data-create]').addEventListener('click', create);
    };

    /** Vorschau neu aufbauen: Vorlage → Werte → Formate; zeigt das gewählte Format */
    const rebuild = (first = false) => {
      const doc = document.importNode(new DOMParser().parseFromString(tpl.svg, 'image/svg+xml').documentElement, true);
      doc.removeAttribute('width'); doc.removeAttribute('height');
      doc.setAttribute('preserveAspectRatio', 'xMidYMid meet');
      if (root) root.replaceWith(doc); else shadow.append(doc);
      root = doc;
      photos.ctx.root = root;
      photos.adopt(root);
      notes = applyValues(root, vals, tokens);
      for (const o of dlg.offer) if (o.f && fmts.has(o.id)) addFormat(root, o.f, null, o.id);
      photos.refresh(root);
      fullVb = root.getAttribute('viewBox');
      // Hinweise an den Feldern (Text zu lang)
      for (const f of tpl.fields) {
        if (f.t !== 'text') continue;
        const n = dlg.querySelector(`[data-note="${CSS.escape(f.k)}"]`), el = dlg.querySelector(`[data-k="${CSS.escape(f.k)}"]`);
        const bad = notes.find(z => z.k === f.k);
        if (n) { n.textContent = bad ? bad.msg : t('{n} von {max} Zeichen', { n: String(vals[f.k] || '').length, max: f.max || 200 }); n.classList.toggle('is-warn', !!bad); }
        el?.setAttribute('aria-invalid', String(!!bad));
      }
      const alt = dlg.querySelector('[data-alt]');
      if (alt && (first || alt.dataset.auto === '1' || !alt.value)) { alt.value = altFromTexts(root); alt.dataset.auto = '1'; }
      alt?.addEventListener('input', () => { alt.dataset.auto = '0'; }, { once: true });
      tabs();
      show();
    };

    const boardOf = id => boards(root).find(b => b.id === id);
    const tabs = () => {
      const box = dlg.querySelector('.ge-fill__tabs');
      const ids = [...fmts].filter(id => boardOf(id));
      if (!ids.includes(cur)) cur = ids[0] || 'main';
      box.innerHTML = ids.map(id => { const b = boardOf(id); const o = dlg.offer.find(z => z.id === id); return `<button type="button" role="radio" aria-checked="${id === cur}" tabindex="${id === cur ? 0 : -1}" data-show="${esc(id)}">${esc(o?.f ? fmtLabel(o.f) : id === 'main' ? t('Vorlage') : b.n)}</button>`; }).join('');
      box.querySelectorAll('[data-show]').forEach((b, i, all) => {
        b.addEventListener('click', () => { cur = b.dataset.show; tabs(); show(); });
        b.addEventListener('keydown', e => { const d = ['ArrowRight', 'ArrowDown'].includes(e.key) ? 1 : ['ArrowLeft', 'ArrowUp'].includes(e.key) ? -1 : 0; if (!d) return; e.preventDefault(); const n = all[(i + d + all.length) % all.length]; cur = n.dataset.show; tabs(); show(); dlg.querySelector(`[data-show="${CSS.escape(cur)}"]`)?.focus(); });
      });
    };
    const show = () => {
      const b = boardOf(cur) || boardOf('main');
      root.setAttribute('viewBox', `${b.x} ${b.y} ${b.w} ${b.h}`);
      const st = dlg.querySelector('[data-stage]'), k = Math.min(st.clientWidth / b.w, st.clientHeight / b.h) || 0.3;
      root.style.width = Math.floor(b.w * k) + 'px'; root.style.height = Math.floor(b.h * k) + 'px';
      const pn = dlg.querySelector('[data-pnote]');
      pn.textContent = notes.length ? notes.map(n => (tpl.fields.find(f => f.k === n.k)?.n || '') + ': ' + n.msg).join(' ') : t('Vorschau {w} × {h} px', { w: Math.round(b.w), h: Math.round(b.h) });
    };
    const workSvg = () => { const c = root.cloneNode(true); c.setAttribute('viewBox', fullVb); toWork(c); c.removeAttribute('preserveAspectRatio'); c.removeAttribute('style'); return new XMLSerializer().serializeToString(c); };

    const create = async () => {
      if (busy) return;
      const form = dlg.querySelector('.ge-fill__form');
      const name = form.querySelector('[name=name]'), alt = form.querySelector('[name=alt]');
      if (!name.value.trim()) { name.focus(); name.reportValidity(); return; }
      if (alt.value.trim().length < 3) { toast(t('Bitte einen Alt-Text (Bildbeschreibung) angeben.'), 'warn'); alt.focus(); return; }
      if (notes.length) { toast(notes[0].msg, 'warn'); dlg.querySelector(`[data-k="${CSS.escape(notes[0].k)}"]`)?.focus(); return; }
      const use = [...fmts].map(boardOf).filter(Boolean);
      if (!use.length) { toast(t('Bitte mindestens ein Format wählen.'), 'info'); return; }
      busy = true;
      const btn = dlg.querySelector('[data-create]'), st = dlg.querySelector('[data-status]');
      btn.disabled = true;
      try {
        const svg = workSvg(), type = outType(root), base = name.value.trim();
        const outputs = [];
        let i = 0;
        for (const b of use) {
          st.textContent = t('Bild {i} von {n} wird erstellt …', { i: ++i, n: use.length });
          const blob = await renderImage(svg, b, outScale(b, boot.compose?.maxPx || 4000), { info: photos.info, boot: { fontFiles: boot.fontFiles, modules: boot.modules }, host: dlg }, { type });
          outputs.push({ board: b.id, upload: await host.upload(blob, base), name: b.id === 'main' ? base : `${base}-${Math.round(b.w)}x${Math.round(b.h)}` });
        }
        st.textContent = t('Wird in der Mediathek gespeichert …');
        const res = await host.saveImage({ svg, id: opts.id || null, name: base, title: tpl.name, alt: alt.value.trim(), outputs, tpl: tpl.ref, vals });
        saved = res;
        toast(outputs.length > 1 ? t('{n} Bilder in der Mediathek gespeichert', { n: outputs.length }) : t('Bild in der Mediathek gespeichert'));
        close();
      } catch (e) {
        console.error(e);
        toast(e.message || t('Speichern fehlgeschlagen.'), 'error');
        st.textContent = '';
      } finally { busy = false; if (btn.isConnected) btn.disabled = false; }
    };

    dlg.showModal();
    const tref = opts.template || boot.doc?.tpl;
    const preset = opts.id ? (boot.doc?.vals || {}) : null;
    if (opts.id && boot.doc?.outputs) fmts = new Set(Object.keys(boot.doc.outputs).map(k => (k === 'main' ? 'main' : k)));
    const tp = tref && templates.find(x => x.ref === tref);
    if (tp) start(tp, preset); else chooser();
  });
}
