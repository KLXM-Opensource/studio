/*
 * Grafik-Editor – Objektstile (Ausbaustufe 5, wie „Stile“ in Affinity bzw. Grafikstile in Illustrator): das Aussehen eines Objekts –
 * Füllung, Kontur (Farbe, Stärke, Strichmuster, Enden, Ecken), Deckkraft, Mischmodus und Ebeneneffekte – unter einem Namen merken und
 * auf andere Objekte anwenden. „Aus Auswahl aktualisieren“ ändert alle Objekte mit diesem Stil (wie Absatzvorlagen bei Text).
 * Gespeichert mit der Grafik: data-ge-ostyles an der Wurzel (Base64-JSON [{n, props, fx}], nur Arbeitsfassung), am Objekt data-ge-os = Name.
 * Effekte: das Modell der Ebeneneffekte (data-ge-fx) – beim Anwenden bekommt jedes Objekt seinen eigenen Filter. MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { ico } from '../_icons.js';
import { toast } from '../_toast.js';
import { NS } from './geom.js';
import { getProp, setProp, refOf, uid, defs, useCount } from './doc.js';
import { modal } from './ui.js';
import { fxModel, writeFilter, isFxFilter, FX } from './fx.js';

export const OBJ_PROPS = ['fill', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-dasharray', 'stroke-linecap', 'stroke-linejoin', 'opacity', 'mix-blend-mode'];

const enc = o => btoa(String.fromCharCode(...new TextEncoder().encode(JSON.stringify(o))));
const dec = s => { try { const o = JSON.parse(new TextDecoder().decode(Uint8Array.from(atob(s), c => c.charCodeAt(0)))); return Array.isArray(o) ? o : []; } catch { return []; } };
export const objStyles = root => dec(root?.getAttribute('data-ge-ostyles') || '').filter(s => s && typeof s.n === 'string');
export function setObjStyles(root, list) { list.length ? root.setAttribute('data-ge-ostyles', enc(list)) : root.removeAttribute('data-ge-ostyles'); }

/** Aussehen eines Objekts lesen */
export function readLook(ed, el) {
  const props = {};
  for (const p of OBJ_PROPS) { const v = getProp(el, p, false); if (v !== null && v !== undefined && v !== '') props[p] = v; }
  const fv = getProp(el, 'filter', false);
  const f = fv && fv !== 'none' ? refOf(ed.root, fv) : null;
  return { props, fx: f && isFxFilter(f) ? fxModel(f) : null };
}

/** Aussehen anwenden (Eigenschaften, die der Stil nicht kennt, werden zurückgesetzt – wie ein Stil in Grafikprogrammen) */
export function applyLook(ed, el, st) {
  for (const p of OBJ_PROPS) setProp(el, p, st.props?.[p] ?? null);
  // Effekte: alter eigener Filter weg (wenn ungeteilt), neuer eigener Filter aus dem Modell
  const fv = getProp(el, 'filter', false);
  const old = fv && fv !== 'none' ? refOf(ed.root, fv) : null;
  if (old && isFxFilter(old)) { setProp(el, 'filter', null); if (!useCount(ed.root, old.id)) old.remove(); }
  if (st.fx && FX.some(x => st.fx[x.id]?.on)) {
    const f = document.createElementNS(NS, 'filter');
    f.id = uid(ed.root, 'fx-');
    defs(ed.root).append(f);
    writeFilter(f, st.fx);
    setProp(el, 'filter', `url(#${f.id})`);
  }
  el.setAttribute('data-ge-os', st.n);
}

export function buildObjectStyles(ed, box, { sel, commit }) {
  box.innerHTML = `<h2 class="ge-h3">${esc(t('Objektstile'))}</h2><div class="ge-os" data-osbox></div>`;
  const B = box.querySelector('[data-osbox]');
  let pick = '';
  function render() {
    const list = objStyles(ed.root), el = sel()[0];
    const cur = el?.getAttribute('data-ge-os') || '';
    if (!list.some(s => s.n === pick)) pick = cur || list[0]?.n || '';
    B.innerHTML = list.length
      ? `<label class="ge-lbl">${esc(t('Stil'))} <select class="ge-in" data-os>${list.map(s => `<option value="${esc(s.n)}"${s.n === pick ? ' selected' : ''}>${esc(s.n === cur ? t('{name} (an der Auswahl)', { name: s.n }) : s.n)}</option>`).join('')}</select></label>
        <div class="ge-os__acts"><button type="button" class="adm-btn adm-btn--small" data-osact="apply"${el ? '' : ' disabled'}>${esc(t('Anwenden'))}</button>
          <button type="button" class="adm-btn adm-btn--small" data-osact="update"${el ? '' : ' disabled'} title="${esc(t('Stil aus dem gewählten Objekt aktualisieren – ändert alle Objekte mit diesem Stil'))}">${esc(t('Aus Auswahl aktualisieren'))}</button>
          <button type="button" class="ge-ib" data-osact="del" aria-label="${esc(t('Stil löschen'))}" title="${esc(t('Stil löschen'))}">${ico('trash')}</button></div>`
      : `<p class="ge-note">${esc(t('Noch keine Stile. Aussehen eines Objekts (Füllung, Kontur, Deckkraft, Effekte) als Stil merken und auf andere anwenden.'))}</p>`;
    B.insertAdjacentHTML('beforeend', `<button type="button" class="adm-btn adm-btn--small" data-osact="new"${el ? '' : ' disabled'}>${ico('plus')} ${esc(t('Stil aus Auswahl …'))}</button>`);
  }
  B.addEventListener('change', e => { if (e.target.dataset.os !== undefined) pick = e.target.value; });
  B.addEventListener('click', async e => {
    const b = e.target.closest('[data-osact]');
    if (!b) return;
    const act = b.dataset.osact, els = sel(), list = objStyles(ed.root);
    if (act === 'new') {
      if (!els[0]) return;
      const r = await modal(ed.el, { title: t('Objektstil anlegen'), ok: t('Anlegen'),
        body: `<label class="f">${esc(t('Name'))}<input name="n" maxlength="60" required value="${esc(t('Stil {n}', { n: list.length + 1 }))}"></label><p class="f-help">${esc(t('Übernimmt Füllung, Kontur, Deckkraft, Mischmodus und Ebeneneffekte des gewählten Objekts.'))}</p>` });
      if (!r) return;
      const n = String(r.n).trim().slice(0, 60);
      if (!n) return;
      const st = { n, ...readLook(ed, els[0]) };
      setObjStyles(ed.root, [...list.filter(s => s.n !== n), st]);
      els[0].setAttribute('data-ge-os', n);
      pick = n;
      commit(t('Objektstil „{name}“ angelegt', { name: n }));
      return;
    }
    const st = list.find(s => s.n === pick);
    if (!st) return;
    if (act === 'apply') {
      for (const el of els) applyLook(ed, el, st);
      commit(t('Objektstil „{name}“ angewandt', { name: st.n }));
    } else if (act === 'update') {
      if (!els[0]) return;
      const nst = { n: st.n, ...readLook(ed, els[0]) };
      setObjStyles(ed.root, list.map(s => s.n === st.n ? nst : s));
      const users = [...ed.root.querySelectorAll('[data-ge-os]')].filter(x => x.getAttribute('data-ge-os') === st.n && x !== els[0]);
      for (const x of users) applyLook(ed, x, nst);
      els[0].setAttribute('data-ge-os', st.n);
      commit(t('Objektstil „{name}“ aktualisiert', { name: st.n }));
      if (users.length) toast(users.length === 1 ? t('Ein weiteres Objekt mit diesem Stil angepasst.') : t('{n} weitere Objekte mit diesem Stil angepasst.', { n: users.length }));
    } else if (act === 'del') {
      setObjStyles(ed.root, list.filter(s => s.n !== st.n));
      for (const x of ed.root.querySelectorAll('[data-ge-os]')) if (x.getAttribute('data-ge-os') === st.n) x.removeAttribute('data-ge-os');
      commit(t('Objektstil „{name}“ gelöscht', { name: st.n }));
    }
  });
  return { render };
}
