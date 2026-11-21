/*
 * Grafik-Editor – kleine Oberflächen-Helfer: Elemente bauen, Menüs (Tastatur), Reiter, Dialoge innerhalb des Editors. MIT
 */
import { esc } from '../_html.js';
import { ico } from '../_icons.js';
import { t } from '../_i18n.js';

export const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
export const MOD = isMac ? '⌘' : 'Strg+';
export const ALT = isMac ? '⌥' : 'Alt+';
export const SHIFT = isMac ? '⇧' : 'Umschalt+';

/** HTML → erstes Element */
export function h(html) {
  const tpl = document.createElement('template');
  tpl.innerHTML = html.trim();
  return tpl.content.firstElementChild;
}

/** Knopf mit Symbol; label = zugänglicher Name + Tooltip (mit Kürzel) */
export function iconBtn(name, label, attrs = '', key = '') {
  const tip = key ? `${label} (${key})` : label;
  return `<button type="button" class="ge-ib" aria-label="${esc(label)}" title="${esc(tip)}"${key ? ` aria-keyshortcuts="${esc(key)}"` : ''} ${attrs}>${ico(name)}</button>`;
}

/**
 * Menü an einem Knopf: items [{label, run, disabled, key, sep}] – Pfeiltasten, Enter, Esc; schließt bei Klick daneben.
 */
export function menu(btn, items) {
  closeMenus();
  const m = h(`<div class="ge-menu" role="menu"></div>`);
  for (const it of items) {
    if (it.sep) { m.append(h('<div class="ge-menu__sep" role="separator"></div>')); continue; }
    const chk = typeof it.checked === 'boolean';
    const b = h(`<button type="button" role="${chk ? 'menuitemcheckbox' : 'menuitem'}" class="ge-menu__it${chk ? ' ge-menu__it--chk' : ''}"${chk ? ` aria-checked="${it.checked}"` : ''}${it.disabled ? ' disabled' : ''}>${chk ? `<span class="ge-menu__tick" aria-hidden="true">${it.checked ? ico('check') : ''}</span>` : ''}<span>${esc(it.label)}</span>${it.key ? `<kbd>${esc(it.key)}</kbd>` : ''}</button>`);
    b.addEventListener('click', () => { closeMenus(); btn.focus(); it.run?.(); });
    m.append(b);
  }
  const root = btn.closest('.ge') || document.body;
  root.append(m);
  const r = btn.getBoundingClientRect(), rr = root.getBoundingClientRect();
  m.style.left = Math.max(4, Math.min(r.left - rr.left, rr.width - 240)) + 'px';
  m.style.top = (r.bottom - rr.top + 4) + 'px';
  // unten kein Platz (z. B. Baustein am unteren Rand der Bibliothek): über dem Knopf öffnen
  const mh = m.offsetHeight;
  if (r.bottom + 4 + mh > Math.min(rr.bottom, window.innerHeight) && r.top - rr.top - 4 - mh >= 0) m.style.top = (r.top - rr.top - 4 - mh) + 'px';
  btn.setAttribute('aria-expanded', 'true');
  const all = () => [...m.querySelectorAll('[role^=menuitem]:not(:disabled)')];
  all()[0]?.focus();
  m.addEventListener('keydown', e => {
    const list = all(), i = list.indexOf(document.activeElement);
    if (e.key === 'ArrowDown') { e.preventDefault(); list[(i + 1) % list.length]?.focus(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); list[(i - 1 + list.length) % list.length]?.focus(); }
    else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closeMenus(); btn.focus(); }
    else if (e.key === 'Tab') closeMenus();
    e.stopPropagation();
  });
  setTimeout(() => document.addEventListener('pointerdown', outside, true));
  function outside(e) { if (!m.contains(e.target) && e.target !== btn) closeMenus(); }
  m._close = () => { document.removeEventListener('pointerdown', outside, true); btn.setAttribute('aria-expanded', 'false'); };
  return m;
}
export function closeMenus() {
  document.querySelectorAll('.ge-menu').forEach(m => { m._close?.(); m.remove(); });
}

/**
 * Reiter-Gruppe: tabs [{id, label}] → { el, panel(id), select(id) }; Pfeiltasten wechseln (WAI-ARIA Tabs). Bereiche ohne
 * tabindex: sie enthalten selbst bedienbare Elemente (sonst verdecken diese den Bereich als Ziel, WCAG 2.5.8)
 */
export function tabGroup(uid, tabs, onSelect) {
  const el = h(`<section class="ge-pg">
    <div class="ge-pg__tabs"><div class="ge-pg__list" role="tablist">${tabs.map((tb, i) => `<button type="button" role="tab" id="${uid}-t-${tb.id}" aria-controls="${uid}-p-${tb.id}" aria-selected="${i ? 'false' : 'true'}" tabindex="${i ? -1 : 0}">${esc(tb.label)}</button>`).join('')}</div>
      <button type="button" class="ge-pg__fold" aria-expanded="true" aria-label="${esc(t('Bereich ein-/ausklappen'))}" title="${esc(t('Bereich ein-/ausklappen'))}">${ico('caret-down')}</button></div>
    ${tabs.map((tb, i) => `<div class="ge-pg__panel" role="tabpanel" id="${uid}-p-${tb.id}" aria-labelledby="${uid}-t-${tb.id}"${i ? ' hidden' : ''}></div>`).join('')}
  </section>`);
  const btns = [...el.querySelectorAll('[role=tab]')];
  const select = (id, focus = false) => {
    for (const b of btns) {
      const on = b.id === `${uid}-t-${id}`;
      b.setAttribute('aria-selected', on ? 'true' : 'false'); b.tabIndex = on ? 0 : -1;
      el.querySelector('#' + b.getAttribute('aria-controls')).hidden = !on;
      if (on && focus) b.focus();
    }
    onSelect?.(id);
  };
  btns.forEach((b, i) => {
    b.addEventListener('click', () => select(tabs[i].id));
    b.addEventListener('keydown', e => {
      const d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
      if (!d) return;
      e.preventDefault(); select(tabs[(i + d + tabs.length) % tabs.length].id, true);
    });
  });
  const fold = el.querySelector('.ge-pg__fold');
  fold.addEventListener('click', () => { const open = el.classList.toggle('is-folded'); fold.setAttribute('aria-expanded', open ? 'false' : 'true'); });
  return { el, panel: id => el.querySelector(`#${uid}-p-${id}`), select, current: () => btns.find(b => b.getAttribute('aria-selected') === 'true')?.id.split('-t-')[1] };
}

/** Modaler Dialog innerhalb des Editors (Formular). Rückgabe: Promise mit FormData-Objekt oder null */
export function modal(root, { title, body, ok = t('Übernehmen'), wide = false, onOpen }) {
  return new Promise(resolve => {
    const d = h(`<dialog class="ge-modal${wide ? ' ge-modal--wide' : ''}" aria-labelledby="ge-modal-h"><form method="dialog" class="ge-modal__form">
      <h2 id="ge-modal-h">${esc(title)}</h2><div class="ge-modal__body">${body}</div>
      <div class="ge-modal__acts"><button type="button" class="adm-btn" value="cancel" data-cancel>${esc(t('Abbrechen'))}</button>${ok ? `<button type="submit" class="adm-btn adm-btn--primary" value="ok">${esc(ok)}</button>` : ''}</div></form></dialog>`);
    root.append(d);
    const form = d.querySelector('form');
    let done = false;
    const finish = v => { if (done) return; done = true; d.close(); d.remove(); resolve(v); };
    d.querySelector('[data-cancel]').addEventListener('click', () => finish(null));
    d.addEventListener('cancel', e => { e.preventDefault(); e.stopPropagation(); finish(null); });
    d.addEventListener('keydown', e => e.stopPropagation());
    form.addEventListener('submit', e => {
      e.preventDefault();
      if (!form.reportValidity()) return;
      const o = {};
      new FormData(form).forEach((v, k) => { o[k] = v; });
      form.querySelectorAll('input[type=checkbox]').forEach(c => { o[c.name] = c.checked; });
      finish(o);
    });
    d.showModal();
    onOpen?.(d, finish);
    (d.querySelector('[autofocus]') || d.querySelector('input,select,textarea,button[type=submit]'))?.focus();
  });
}
