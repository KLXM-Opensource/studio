/*
 * Grafik-Editor – zwei Hüllen um dieselbe Komponente:
 *   Overlay  CMSGraphicEditor.open({ format, mediaId, id, block, w, h, svg, onSave }) → Promise<{ saved, svg }>
 *            Modaler Dialog (Fokus bleibt darin), Esc fragt bei ungespeicherten Änderungen, Fokus kehrt zum Auslöser zurück.
 *            Vollbild per Knopf oder ⇧⌘F, Zustand merkt sich der Browser.
 *   Seite    /admin/grafik/editor (Daten im <script type="application/json" id="ge-boot">), Warnung beim Verlassen.
 * MIT
 */
import { t } from '../_i18n.js';
import { esc, csrf } from '../_html.js';
import { ico } from '../_icons.js';
import { toast } from '../_toast.js';
import { GraphicEditor } from './editor.js';
import { modal, MOD, SHIFT } from './ui.js';

const KEY = 'klxm-studio-grafik-max';
const pref = { get() { try { return localStorage.getItem(KEY) === '1'; } catch { return false; } }, set(v) { try { localStorage.setItem(KEY, v ? '1' : '0'); } catch { /* privat */ } } };

/** Adresse der Editor-Daten (vom Layout: data-grafik-boot an <html>) */
const bootUrl = () => document.documentElement.dataset.grafikBoot || '/admin/api/grafik/boot';

async function json(url, opts = {}) {
  const r = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() }, ...opts });
  const j = await r.json().catch(() => ({ ok: false, error: t('Unerwartete Antwort vom Server.') }));
  if (!r.ok || j.ok === false) throw new Error(j.error || t('Der Grafik-Editor konnte nicht geladen werden.'));
  return j;
}

/** Editor als Overlay öffnen. Ergebnis: { saved: letzte Speicherung oder null, svg: Stand beim Schließen } */
export async function open(opts = {}) {
  const q = new URLSearchParams();
  for (const k of ['format', 'block', 'w', 'h', 'template', 'out']) if (opts[k] != null && opts[k] !== '') q.set(k, opts[k]);
  if (opts.mediaId) q.set('media', opts.mediaId);
  if (opts.id) q.set('id', opts.id);
  const back = document.activeElement;
  let boot;
  try {
    boot = await json(bootUrl() + '?' + q);
    if (opts.svg) {
      // fremdes SVG des Aufrufers: erst bereinigen lassen, bevor es in die Verwaltung geladen wird
      const c = await json(bootUrl().replace(/boot$/, 'clean'), { method: 'POST', body: JSON.stringify({ svg: opts.svg }) });
      boot.doc.svg = c.svg;
    }
  } catch (e) { toast(e.message, 'error'); return { saved: null, svg: null }; }
  if (opts.target === 'none') boot.doc.target = 'none';

  return new Promise(resolve => {
    const dlg = document.createElement('dialog');
    dlg.className = 'ge-dlg adm-ui' + (pref.get() ? ' is-max' : '');
    dlg.setAttribute('aria-label', t('Grafik-Editor'));
    document.body.append(dlg);
    let saved = null, ed = null;
    const close = async (force = false) => {
      if (!force && ed.dirty) {
        const ok = await modal(ed.el, { title: t('Ungespeicherte Änderungen verwerfen?'), ok: t('Verwerfen'), body: `<p>${esc(t('Die Grafik wurde seit dem letzten Speichern geändert.'))}</p>` });
        if (!ok) return;
      }
      const svg = ed.getSvg();
      ed.destroy();
      dlg.close(); dlg.remove();
      document.documentElement.classList.remove('ge-open');
      const ret = opts.returnFocus?.isConnected ? opts.returnFocus : back;
      if (ret && ret.isConnected) ret.focus?.();
      opts.onClose?.({ saved, svg });
      resolve({ saved, svg });
    };
    const shell = {
      toggleMax() { const on = dlg.classList.toggle('is-max'); pref.set(on); dlg.querySelector('[data-max]')?.setAttribute('aria-pressed', String(on)); requestAnimationFrame(() => ed.fit()); },
      close,
    };
    dlg.addEventListener('cancel', e => { e.preventDefault(); if (!dlg.querySelector('dialog.ge-modal[open]') && !document.querySelector('.ge-menu')) close(); });
    document.documentElement.classList.add('ge-open');
    dlg.showModal();
    ed = new GraphicEditor(dlg, boot, {
      shell,
      onSave: r => { saved = r; opts.onSave?.(r); },
    });
    const slot = ed.el.querySelector('[data-shellbtns]');
    slot.innerHTML = `<button type="button" class="ge-ib" data-max aria-pressed="${dlg.classList.contains('is-max')}" aria-label="${esc(t('Vollbild'))}" title="${esc(t('Vollbild'))} (${SHIFT}${MOD}F)">${ico('corners-out')}</button>
      <button type="button" class="ge-ib" data-close aria-label="${esc(t('Schließen'))}" title="${esc(t('Schließen'))} (Esc)">${ico('x')}</button>`;
    slot.querySelector('[data-max]').addEventListener('click', () => shell.toggleMax());
    slot.querySelector('[data-close]').addEventListener('click', () => close());
    // Persona beim Öffnen (z. B. „bild“ für Neu → Bild, Vorlage gestalten)
    if (opts.persona || ['compose', 'template'].includes(boot.doc.target)) ed.setPersona(opts.persona || 'bild');
    ed.stage.focus({ preventScroll: true });
  });
}

/** Seite /admin/grafik/editor */
export function mountPage() {
  const host = document.querySelector('[data-ge-page]');
  const data = document.getElementById('ge-boot');
  if (!host || !data) return;
  const boot = JSON.parse(data.textContent);
  host.innerHTML = '';
  document.documentElement.classList.add('ge-full');
  // Eingebettet (Blockauswahl der Website → „Vorschau gestalten“): Nachrichten an das Fenster darüber, gleiche Herkunft
  const embed = new URLSearchParams(location.search).get('embed') === '1' && window.parent !== window;
  const tell = msg => { try { window.parent.postMessage({ source: 'klxm-grafik', ...msg }, location.origin); } catch { /* ohne Eltern */ } };
  const ed = new GraphicEditor(host, boot, {
    onSave: r => {
      if (embed) tell({ type: 'saved', block: r.item?.block || boot.doc.block, url: r.item?.url || null, active: !!r.item?.active });
      // neue Datei: Adresse anpassen, damit „Neu laden“ dieselbe Grafik öffnet
      const u = new URL(location.href);
      if (r.compose) { u.search = '?id=' + r.compose; } else if (r.target === 'template') { u.search = '?template=' + r.item.ref; } else if (r.target === 'media') { u.search = '?media=' + r.item.id; } else { u.search = '?id=' + r.item.id; }
      history.replaceState(null, '', u);
    },
  });
  window.CMSGraphicEditor.page = ed;   // Instanz der eigenen Seite (Erweiterungen, Tests)
  const per = new URLSearchParams(location.search).get('persona');
  if (per || boot.doc.target === 'compose' || boot.doc.target === 'template') ed.setPersona(per || 'bild');
  const back = boot.doc.target === 'graphics' || boot.doc.target === 'template' ? boot.urls.store : boot.urls.media;
  ed.el.querySelector('[data-shellbtns]').innerHTML = `<a class="adm-btn adm-btn--small adm-btn--ghost" href="${esc(back)}" data-back>${ico('x')} ${esc(t('Schließen'))}</a>`;
  ed.el.querySelector('[data-back]').addEventListener('click', async e => {
    if (embed) {
      e.preventDefault();
      if (ed.dirty && !(await modal(ed.el, { title: t('Ungespeicherte Änderungen verwerfen?'), ok: t('Verwerfen'), body: `<p>${esc(t('Die Grafik wurde seit dem letzten Speichern geändert.'))}</p>` }))) return;
      tell({ type: 'close' });
      return;
    }
    if (!ed.dirty) return;
    e.preventDefault();
    const ok = await modal(ed.el, { title: t('Ungespeicherte Änderungen verwerfen?'), ok: t('Verwerfen'), body: `<p>${esc(t('Die Grafik wurde seit dem letzten Speichern geändert.'))}</p>` });
    if (ok) { ed.savedAt = ed.hi; location.href = back; }
  });
  if (embed) {
    // Esc ohne offene Dialoge/Bearbeitung: schließen wie der Knopf
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !e.defaultPrevented && !ed.sel.length && !ed.textEd && !document.querySelector('dialog[open]')) ed.el.querySelector('[data-back]').click(); });
  } else window.addEventListener('beforeunload', e => { if (ed.dirty) { e.preventDefault(); e.returnValue = ''; } });
  ed.stage.focus({ preventScroll: true });
  window.CMSGraphicEditor.current = ed;
}
