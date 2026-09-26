/*
 * Linkauswahl (Core, Teil von admin.js) – ein Dialog für Rich-Text (Formatierungsleiste, ⌘K) und Felder vom Typ „link“.
 *
 *  - Suche mit Live-Ergebnissen, gruppiert: Zuletzt verwendet · Anker auf dieser Seite · Seiten (Pfad im Seitenbaum, Sprache)
 *    · Einträge je Inhaltstabelle mit Detailseite · Dateien & Medien (PDF im Viewer oder direkt) · Sonderziele des Themes (nur Felder)
 *    Quelle: GET /admin/api/links?format=groups&q=…&page=…&mode=rich|field (Core\Links::sources)
 *  - Reiter Web-Adresse (https-Prüfung, ergänzt https://, warnt bei http://), E-Mail (mailto, Betreff), Telefon (tel: wie tel_href())
 *  - Bearbeiten: zeigt das aktuelle Ziel, „Link entfernen“; Optionen „In neuem Tab öffnen“ und Linktitel (nur Rich-Text)
 *  - Tastatur: ↑/↓ in der Ergebnisliste, Enter übernimmt, Esc bricht ab, ←/→ zwischen den Reitern
 *  - Stabile Verweise: page:ID, page:ID#anker, entry:{tabelle}:{id}, media:{id}[:viewer] (Core\Links) – im Rich-Text als
 *    data-link am <a>, im Feld als Wert. Zuletzt verwendete Ziele: localStorage (nur Komfort, pro Browser).
 * Website: Dialog in der Shadow-DOM-Ebene (_shadow.js layerBox()), Verwaltung: im Dokument.
 */
import { layerBox, ui } from './_shadow.js';
import { t } from './_i18n.js';
import { ico } from './_icons.js';

const d = document;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const RECENT_KEY = 'cms-links-recent';
const REF = /^(page:\d+(#[\w-]{1,80})?|entry:[a-z][a-z0-9_]{0,40}:\d+|media:\d+(:viewer)?)$/;
const KIND = { page: 'Seite', anchor: 'Anker', entry: 'Eintrag', file: 'Datei', keyword: 'Sonderziel', url: 'Externe Adresse', mail: 'E-Mail', tel: 'Telefon', path: 'Interner Pfad' };

/** Adresse der Link-API (Verwaltung: <datalist data-endpoint>, Website: Editor- bzw. Eintrags-Konfiguration) */
export function linksUrl() {
  const dl = ui('#cms-links[data-endpoint]') || layerBox().querySelector('#cms-links[data-endpoint]');
  if (dl) return dl.dataset.endpoint;
  try { const c = JSON.parse(d.getElementById('cms-editor-config')?.textContent || 'null'); if (c?.endpoints?.links) return c.endpoints.links; } catch {}
  return '/admin/api/links';
}
/** ID der Seite, die gerade bearbeitet wird (Anker „auf dieser Seite“) */
function currentPage() {
  try { return JSON.parse(d.getElementById('cms-editor-config')?.textContent || 'null')?.page?.id || ''; } catch { return ''; }
}
function cfg() {
  try { return JSON.parse(d.getElementById('cms-rich')?.textContent || 'null') || {}; } catch { return {}; }
}

// ------------------------------------------------------------------ Zuletzt verwendet (nur Komfort, darf fehlen)
function recent() {
  try { const r = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); return Array.isArray(r) ? r.slice(0, 6) : []; } catch { return []; }
}
function remember(item) {
  if (!item?.value) return;
  try {
    const list = [item, ...recent().filter(r => r.value !== item.value)].slice(0, 6);
    localStorage.setItem(RECENT_KEY, JSON.stringify(list.map(({ value, href, label, meta, kind, badge, pdf, file, fileHref }) => ({ value, href, label, meta, kind, badge, pdf, file, fileHref }))));
  } catch {}
}

// ------------------------------------------------------------------ Eingaben prüfen
/** Web-Adresse: ergänzt https://, http → Hinweis. {href, msg, warn} oder {error} */
export function normalizeUrl(v) {
  v = String(v || '').trim();
  if (!v) return { error: t('Bitte eine Adresse eingeben.') };
  if (/^(#[\w-]*|\/(?!\/)\S*)$/.test(v)) return { href: v };
  if (/^(javascript|data|vbscript):/i.test(v)) return { error: t('Diese Adresse ist nicht erlaubt.') };
  let warn = '';
  if (/^http:\/\//i.test(v)) { v = 'https://' + v.slice(7); warn = t('Unverschlüsselte Adresse (http) – wird als https:// gespeichert. Bitte prüfen, ob die Seite so erreichbar ist.'); }
  else if (!/^https:\/\//i.test(v)) {
    if (/^[\w.-]+\.[a-z]{2,}([/?#:].*)?$/i.test(v)) v = 'https://' + v.replace(/^\/\//, '');
    else return { error: t('Bitte eine vollständige Adresse eingeben, z. B. https://beispiel.de') };
  }
  try { const u = new URL(v); if (!u.hostname.includes('.')) throw 0; } catch { return { error: t('Das sieht nicht wie eine gültige Web-Adresse aus.') }; }
  if (/\s/.test(v)) return { error: t('Die Adresse darf keine Leerzeichen enthalten.') };
  return { href: v, warn };
}
export function normalizeMail(v, subject = '') {
  v = String(v || '').trim().replace(/^mailto:/i, '');
  if (!/^[^\s@<>"]+@[^\s@<>"]+\.[^\s@<>"]{2,}$/.test(v)) return { error: t('Bitte eine gültige E-Mail-Adresse eingeben.') };
  return { href: 'mailto:' + v + (subject.trim() ? '?subject=' + encodeURIComponent(subject.trim()) : ''), label: v };
}
/** Telefon wie tel_href() in PHP: (0) weg, 00 → +, führende 0 → Landesvorwahl der Website */
export function normalizeTel(v) {
  const raw = String(v || '').trim().replace(/^tel:/i, '');
  let n = raw.replace(/\(0\)/g, '').replace(/[^\d+]/g, '');
  const cc = '+' + (String(cfg().country || '+49').replace(/\D/g, '') || '49');
  if (n.startsWith('00')) n = '+' + n.slice(2);
  else if (n.startsWith('0')) n = cc + n.slice(1);
  else if (n && !n.startsWith('+')) n = cc + n;
  if (n.replace(/\D/g, '').length < 5) return { error: t('Bitte eine gültige Telefonnummer eingeben.') };
  return { href: 'tel:' + n, label: raw };
}

/** Lesbare Beschreibung eines Werts ohne Server (Verweise: Server, siehe describe()) */
function localDescribe(v) {
  v = String(v || '').trim();
  if (v.startsWith('#')) return { kind: 'anchor', type: t('Anker'), label: v, href: v };
  if (/^mailto:/i.test(v)) return { kind: 'mail', type: t('E-Mail'), label: v.slice(7).replace(/\?.*$/, ''), href: v };
  if (/^tel:/i.test(v)) return { kind: 'tel', type: t('Telefon'), label: v.slice(4), href: v };
  if (/^https?:\/\//i.test(v)) { try { const u = new URL(v); return { kind: 'url', type: t('Externe Adresse'), label: u.hostname + (u.pathname !== '/' ? u.pathname : ''), href: v }; } catch {} }
  return null;
}
async function describe(v) {
  const loc = localDescribe(v);
  if (loc) return loc;
  try {
    const r = await fetch(linksUrl() + (linksUrl().includes('?') ? '&' : '?') + 'describe=' + encodeURIComponent(v), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (r.ok) return await r.json();
  } catch {}
  return { kind: 'url', type: t('Adresse'), label: v, href: v };
}

// ------------------------------------------------------------------ Dialog
let dlg = null, state = null;

function build() {
  const box = layerBox();
  dlg = box.querySelector('#cms-lp');
  if (dlg) return dlg;
  box.insertAdjacentHTML('beforeend', `<dialog id="cms-lp" class="adm-dialog lp" aria-labelledby="cms-lp-t">
    <form method="dialog" class="lp__form" novalidate>
      <div class="adm-dialog__head"><h2 id="cms-lp-t">${esc(t('Link einfügen'))}</h2>
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-lp-cancel>${esc(t('Abbrechen'))}</button></div>
      <p class="lp__current" data-lp-current hidden></p>
      <div class="lp__tabs" role="tablist" aria-label="${esc(t('Art des Linkziels'))}">
        <button type="button" role="tab" id="cms-lp-tab-search" aria-controls="cms-lp-p-search" data-lp-tab="search">${ico('magnifying-glass')} ${esc(t('Seiten & Inhalte'))}</button>
        <button type="button" role="tab" id="cms-lp-tab-url" aria-controls="cms-lp-p-url" data-lp-tab="url">${ico('globe')} ${esc(t('Web-Adresse'))}</button>
        <button type="button" role="tab" id="cms-lp-tab-mail" aria-controls="cms-lp-p-mail" data-lp-tab="mail">${ico('at')} ${esc(t('E-Mail'))}</button>
        <button type="button" role="tab" id="cms-lp-tab-tel" aria-controls="cms-lp-p-tel" data-lp-tab="tel">${ico('phone')} ${esc(t('Telefon'))}</button>
      </div>
      <div class="lp__panel" role="tabpanel" id="cms-lp-p-search" aria-labelledby="cms-lp-tab-search" data-lp-panel="search">
        <input type="search" class="lp__q" data-lp-q role="combobox" aria-expanded="true" aria-controls="cms-lp-list" aria-autocomplete="list"
          aria-label="${esc(t('Linkziel suchen'))}" autocomplete="off" spellcheck="false" placeholder="${esc(t('Seite, Eintrag, Datei oder Anker suchen …'))}">
        <div class="lp__list" id="cms-lp-list" role="listbox" aria-label="${esc(t('Linkziele'))}" data-lp-list></div>
      </div>
      <div class="lp__panel" role="tabpanel" id="cms-lp-p-url" aria-labelledby="cms-lp-tab-url" data-lp-panel="url" hidden>
        <label class="f"><span>${esc(t('Adresse'))}</span><input type="url" inputmode="url" data-lp-url autocomplete="off" spellcheck="false" placeholder="https://beispiel.de/seite" aria-describedby="cms-lp-url-msg"></label>
        <p class="f-help lp__msg" id="cms-lp-url-msg" data-lp-msg="url" aria-live="polite">${esc(t('Auch interne Pfade (/seite) und Anker (#abschnitt) sind möglich.'))}</p>
      </div>
      <div class="lp__panel" role="tabpanel" id="cms-lp-p-mail" aria-labelledby="cms-lp-tab-mail" data-lp-panel="mail" hidden>
        <label class="f"><span>${esc(t('E-Mail-Adresse'))}</span><input type="email" data-lp-mail autocomplete="off" spellcheck="false" placeholder="name@beispiel.de" aria-describedby="cms-lp-mail-msg"></label>
        <label class="f"><span>${esc(t('Betreff (optional)'))}</span><input type="text" data-lp-subject maxlength="120"></label>
        <p class="f-help lp__msg" id="cms-lp-mail-msg" data-lp-msg="mail" aria-live="polite"></p>
      </div>
      <div class="lp__panel" role="tabpanel" id="cms-lp-p-tel" aria-labelledby="cms-lp-tab-tel" data-lp-panel="tel" hidden>
        <label class="f"><span>${esc(t('Telefonnummer'))}</span><input type="tel" data-lp-tel autocomplete="off" placeholder="01234 56 78 90" aria-describedby="cms-lp-tel-msg"></label>
        <p class="f-help lp__msg" id="cms-lp-tel-msg" data-lp-msg="tel" aria-live="polite"></p>
      </div>
      <div class="lp__opts">
        <p class="lp__picked" data-lp-picked aria-live="polite" hidden></p>
        <label class="lp__check" data-lp-viewer-wrap hidden><input type="checkbox" data-lp-viewer checked> ${esc(t('PDF im Viewer öffnen (sonst Datei direkt)'))}</label>
        <label class="lp__check" data-lp-richonly><input type="checkbox" data-lp-blank> ${esc(t('In neuem Tab öffnen'))}</label>
        <label class="f lp__title" data-lp-richonly><span>${esc(t('Linktitel (optional, erscheint beim Überfahren)'))}</span><input type="text" data-lp-title maxlength="200"></label>
      </div>
      <p class="lp__error" data-lp-error role="alert" hidden></p>
      <div class="adm-row lp__actions">
        <button type="submit" class="adm-btn adm-btn--primary" data-lp-ok>${esc(t('Übernehmen'))}</button>
        <button type="button" class="adm-btn adm-btn--ghost lp__remove" data-lp-remove hidden>${esc(t('Link entfernen'))}</button>
        <button type="button" class="adm-btn adm-btn--ghost" data-lp-cancel>${esc(t('Abbrechen'))}</button>
      </div>
      <p class="lp__keys f-help">${esc(t('Tastatur: ↑/↓ Ergebnis wählen · Enter übernehmen · Esc abbrechen'))}</p>
    </form></dialog>`);
  dlg = box.querySelector('#cms-lp');
  wire();
  return dlg;
}

const q = s => dlg.querySelector(s);
const qa = s => [...dlg.querySelectorAll(s)];

function wire() {
  const input = q('[data-lp-q]'), list = q('[data-lp-list]');
  let timer;
  input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => search(input.value), 140); });
  input.addEventListener('keydown', e => {
    const opts = qa('[role=option]');
    if (!opts.length) return;
    const i = opts.findIndex(o => o.id === input.getAttribute('aria-activedescendant'));
    let n = null;
    if (e.key === 'ArrowDown') n = i < 0 ? 0 : Math.min(opts.length - 1, i + 1);
    else if (e.key === 'ArrowUp') n = i <= 0 ? 0 : i - 1;
    else if (e.key === 'Home' && e.ctrlKey) n = 0;
    else if (e.key === 'End' && e.ctrlKey) n = opts.length - 1;
    else if (e.key === 'Enter' && i >= 0) { e.preventDefault(); choose(opts[i], true); return; }
    if (n !== null) { e.preventDefault(); activate(opts[n]); }
  });
  list.addEventListener('click', e => { const o = e.target.closest('[role=option]'); if (o) choose(o, false); });
  list.addEventListener('dblclick', e => { const o = e.target.closest('[role=option]'); if (o) choose(o, true); });
  list.addEventListener('mousedown', e => e.preventDefault());   // Fokus bleibt im Suchfeld
  // Reiter
  const tabs = qa('[role=tab]');
  tabs.forEach((b, i) => {
    b.addEventListener('click', () => tab(b.dataset.lpTab, true));
    b.addEventListener('keydown', e => {
      const k = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
      if (k) { e.preventDefault(); const nb = tabs[(i + k + tabs.length) % tabs.length]; tab(nb.dataset.lpTab); nb.focus(); }
    });
  });
  // Live-Prüfung der Eingaben
  q('[data-lp-url]').addEventListener('input', () => hint('url'));
  q('[data-lp-mail]').addEventListener('input', () => hint('mail'));
  q('[data-lp-tel]').addEventListener('input', () => hint('tel'));
  q('[data-lp-viewer]').addEventListener('change', () => showPicked());
  qa('[data-lp-cancel]').forEach(b => b.addEventListener('click', () => dlg.close('cancel')));
  q('[data-lp-remove]').addEventListener('click', () => { state.result = { remove: true }; dlg.close('ok'); });
  q('form').addEventListener('submit', e => {
    e.preventDefault();
    const r = result();
    if (r.error) { err(r.error); return; }
    state.result = r; dlg.close('ok');
  });
  dlg.addEventListener('close', () => { const s = state; state = null; s?.resolve(dlg.returnValue === 'ok' ? s.result || null : null); });
}

function err(msg) { const p = q('[data-lp-error]'); p.textContent = msg || ''; p.hidden = !msg; }

function tab(name, focusField) {
  state.tab = name;
  qa('[role=tab]').forEach(b => { const on = b.dataset.lpTab === name; b.setAttribute('aria-selected', on); b.tabIndex = on ? 0 : -1; });
  qa('[data-lp-panel]').forEach(p => { p.hidden = p.dataset.lpPanel !== name; });
  err('');
  showPicked();
  if (focusField) (name === 'search' ? q('[data-lp-q]') : q(`[data-lp-${name}]`))?.focus();
}

function hint(kind) {
  const el = q(`[data-lp-msg="${kind}"]`), v = q(`[data-lp-${kind}]`).value;
  if (!v.trim()) { el.textContent = kind === 'url' ? t('Auch interne Pfade (/seite) und Anker (#abschnitt) sind möglich.') : ''; el.classList.remove('is-warn', 'is-error'); return; }
  const r = kind === 'url' ? normalizeUrl(v) : kind === 'mail' ? normalizeMail(v, q('[data-lp-subject]').value) : normalizeTel(v);
  el.classList.toggle('is-error', !!r.error); el.classList.toggle('is-warn', !r.error && !!r.warn);
  el.textContent = r.error || r.warn || (kind === 'tel' ? t('Wird gewählt als: {n}', { n: r.href.slice(4) }) : t('Linkziel: {h}', { h: r.href }));
  if (kind === 'url' && !r.error) q('[data-lp-blank]').checked = state.blankTouched ? q('[data-lp-blank]').checked : /^https:\/\//.test(r.href) && !sameHost(r.href);
}
const sameHost = h => { try { return new URL(h).host === location.host; } catch { return false; } };

// ------------------------------------------------------------------ Suche
let seq = 0;
async function search(qs) {
  const n = ++seq;
  const list = q('[data-lp-list]');
  list.setAttribute('aria-busy', 'true');
  let groups = [];
  try {
    const u = linksUrl();
    const r = await fetch(u + (u.includes('?') ? '&' : '?') + new URLSearchParams({ format: 'groups', q: qs.trim(), page: currentPage(), mode: state.mode }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    groups = (await r.json()).groups || [];
  } catch { groups = []; }
  if (n !== seq || !state) return;
  if (!qs.trim()) {
    const rec = recent().filter(r => state.mode === 'field' || r.kind !== 'keyword');
    if (rec.length) groups.unshift({ id: 'recent', label: t('Zuletzt verwendet'), icon: 'clock', items: rec });
  }
  // Eingabe sieht aus wie Adresse/E-Mail/Telefon → Vorschlag als erstes Ergebnis
  const guess = guessDirect(qs);
  if (guess) groups.unshift({ id: 'direct', label: t('Direkt verlinken'), icon: guess.kind === 'mail' ? 'at' : guess.kind === 'tel' ? 'phone' : 'globe', items: [guess] });
  state.items = [];
  let html = '', i = 0;
  for (const g of groups) {
    if (!g.items?.length) continue;
    const gid = 'cms-lp-g-' + esc(g.id).replace(/[^\w-]/g, '_');
    html += `<div class="lp__group" role="group" aria-labelledby="${gid}"><p class="lp__gh" id="${gid}" role="presentation">${g.icon ? ico(g.icon) : ''} ${esc(g.label)}${g.total > g.items.length ? ` <small>${esc(t('{n} von {m}', { n: g.items.length, m: g.total }))}</small>` : ''}</p>`;
    for (const it of g.items) {
      state.items.push(it);
      const sel = state.picked && state.picked.value === it.value;
      html += `<div class="lp__opt${it.draft ? ' is-draft' : ''}" role="option" id="cms-lp-o${i}" data-i="${i}" aria-selected="${sel ? 'true' : 'false'}">`
        + (it.thumb ? `<img class="lp__thumb" src="${esc(it.thumb)}" alt="" width="36" height="36" loading="lazy">` : `<span class="lp__ico" aria-hidden="true">${ico(kindIcon(it, g))}</span>`)
        + `<span class="lp__txt"><span class="lp__label">${esc(it.label)}</span><span class="lp__meta">${esc(it.meta || it.href || '')}</span></span>`
        + (it.badge ? `<span class="lp__badge" title="${esc(t('Sprache'))}">${esc(it.badge)}</span>` : '')
        + (it.draft ? `<span class="lp__badge lp__badge--draft">${esc(t('Entwurf'))}</span>` : '') + '</div>';
      i++;
    }
    html += '</div>';
  }
  list.innerHTML = html || `<p class="lp__empty">${esc(qs.trim() ? t('Nichts gefunden. Tipp: Web-Adressen, E-Mail und Telefon über die Reiter oben eingeben.') : t('Noch keine Inhalte vorhanden.'))}</p>`;
  list.removeAttribute('aria-busy');
  q('[data-lp-q]').removeAttribute('aria-activedescendant');
  const pre = list.querySelector('[aria-selected=true]');
  if (pre) activate(pre, false);
}
function kindIcon(it, g) {
  return { page: 'file-text', anchor: 'hash', entry: g.icon || 'database', file: it.pdf ? 'file-pdf' : 'file', keyword: 'star', url: 'globe', mail: 'at', tel: 'phone' }[it.kind] || 'link';
}
function guessDirect(qs) {
  const v = qs.trim();
  if (!v) return null;
  if (/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) { const r = normalizeMail(v); return r.error ? null : { kind: 'mail', value: r.href, href: r.href, label: v, meta: r.href }; }
  if (/^(\+|00|0)[\d\s/()-]{5,}$/.test(v)) { const r = normalizeTel(v); return r.error ? null : { kind: 'tel', value: r.href, href: r.href, label: v, meta: r.href }; }
  if (/^(https?:\/\/|www\.)\S+$/i.test(v) || /^[\w-]+(\.[\w-]+)+\.[a-z]{2,}(\/\S*)?$/i.test(v) || /^[\w-]+\.(de|com|org|net|eu|at|ch|info|io)(\/\S*)?$/i.test(v)) {
    const r = normalizeUrl(v); return r.error ? null : { kind: 'url', value: r.href, href: r.href, label: r.href, meta: r.warn || t('Externe Adresse') };
  }
  return null;
}
function activate(o, scroll = true) {
  qa('[role=option].is-active').forEach(x => x.classList.remove('is-active'));
  o.classList.add('is-active');
  q('[data-lp-q]').setAttribute('aria-activedescendant', o.id);
  if (scroll) o.scrollIntoView({ block: 'nearest' });
}
function choose(o, apply) {
  const it = state.items[+o.dataset.i];
  if (!it) return;
  qa('[role=option]').forEach(x => x.setAttribute('aria-selected', x === o ? 'true' : 'false'));
  activate(o, false);
  state.picked = it;
  if (it.kind === 'url' && !state.blankTouched) q('[data-lp-blank]').checked = !sameHost(it.href);
  showPicked();
  if (apply) q('form').requestSubmit();
}
function showPicked() {
  if (!state) return;
  const p = q('[data-lp-picked]'), it = state.tab === 'search' ? state.picked : null;
  q('[data-lp-viewer-wrap]').hidden = !(it && it.pdf);
  if (!it) { p.hidden = true; return; }
  const href = it.pdf && !q('[data-lp-viewer]').checked ? it.fileHref : it.href;
  p.hidden = false;
  p.innerHTML = `${esc(t('Ziel'))}: <b>${esc(it.label)}</b> <span class="lp__meta">${esc(href)}</span>`;
}

/** Ergebnis des Dialogs: {href, ref, value, label, kind, newTab, title} oder {error} */
function result() {
  const extra = { newTab: q('[data-lp-blank]').checked, title: q('[data-lp-title]').value.trim() };
  if (state.tab === 'search') {
    let it = state.picked;
    if (!it) {
      const guess = guessDirect(q('[data-lp-q]').value);
      if (guess) it = guess;
      else if (state.current && !q('[data-lp-q]').value.trim()) return { ...state.current, ...extra, keep: true };
      else return { error: t('Bitte ein Ziel aus der Liste wählen – oder die Adresse im Reiter „Web-Adresse“ eingeben.') };
    }
    remember(it);
    const direct = it.pdf && !q('[data-lp-viewer]').checked;
    const ref = direct ? it.file : it.value;
    const href = direct ? it.fileHref : it.href;
    return { href, ref: REF.test(ref || '') ? ref : '', value: ref || href, label: it.label, kind: it.kind, ...extra };
  }
  const kind = state.tab;
  const v = q(`[data-lp-${kind}]`).value;
  const r = kind === 'url' ? normalizeUrl(v) : kind === 'mail' ? normalizeMail(v, q('[data-lp-subject]').value) : normalizeTel(v);
  if (r.error) return r;
  if (kind === 'url') remember({ value: r.href, href: r.href, label: r.href, kind: 'url', meta: t('Externe Adresse') });
  return { href: r.href, ref: '', value: r.href, label: r.label || r.href, kind, ...extra };
}

/**
 * Linkauswahl öffnen.
 * opts: mode 'rich' | 'field', current {href, ref, newTab, title} (Rich-Text) bzw. value (Feld), text (markierter Text)
 * → Promise: {href, ref, value, label, kind, newTab, title} | {remove: true} | null (abgebrochen)
 */
export function openLinkPicker(opts = {}) {
  build();
  if (state) state.resolve(null);
  return new Promise(resolve => {
    const mode = opts.mode === 'field' ? 'field' : 'rich';
    const cur = opts.current || (opts.value ? { href: opts.value, ref: REF.test(opts.value) ? opts.value : '' } : null);
    state = { resolve, mode, current: cur && (cur.href || cur.ref) ? cur : null, picked: null, items: [], tab: 'search', blankTouched: false };
    q('#cms-lp-t').textContent = state.current ? t('Link bearbeiten') : t('Link einfügen');
    qa('[data-lp-richonly]').forEach(n => { n.hidden = mode !== 'rich'; });
    q('[data-lp-remove]').hidden = !state.current;
    q('[data-lp-remove]').textContent = mode === 'field' ? t('Ziel entfernen') : t('Link entfernen');
    q('[data-lp-blank]').checked = !!cur?.newTab;
    q('[data-lp-blank]').onchange = () => { if (state) state.blankTouched = true; };
    q('[data-lp-title]').value = cur?.title || '';
    for (const k of ['q', 'url', 'mail', 'subject', 'tel']) q(`[data-lp-${k}]`).value = '';
    ['url', 'mail', 'tel'].forEach(hint);
    err('');
    // Aktuelles Ziel anzeigen und passenden Reiter öffnen
    const curBox = q('[data-lp-current]');
    curBox.hidden = !state.current;
    let start = 'search';
    if (state.current) {
      const v = state.current.ref || state.current.href || '';
      curBox.innerHTML = `${esc(t('Aktuelles Ziel'))}: <span class="lp__cur">…</span>`;
      describe(v).then(x => { if (state && curBox.isConnected) curBox.querySelector('.lp__cur').innerHTML = `<b>${esc(x.type)}</b> · ${esc(x.label)}${x.missing ? ` <span class="lp__warn">${esc(t('Ziel nicht gefunden'))}</span>` : ''}`; });
      if (!state.current.ref) {
        const h = state.current.href;
        if (/^mailto:/i.test(h)) { start = 'mail'; const [addr, qs] = h.slice(7).split('?'); q('[data-lp-mail]').value = decodeURIComponent(addr); q('[data-lp-subject]').value = new URLSearchParams(qs || '').get('subject') || ''; }
        else if (/^tel:/i.test(h)) { start = 'tel'; q('[data-lp-tel]').value = h.slice(4); }
        else if (/^https?:\/\//i.test(h)) { start = 'url'; q('[data-lp-url]').value = h; state.blankTouched = true; }
      }
      ['url', 'mail', 'tel'].forEach(hint);
    } else if (opts.text && guessDirect(opts.text)) {
      q('[data-lp-q]').value = opts.text.trim();   // markierter Text ist schon eine Adresse
    }
    dlg.returnValue = '';
    dlg.showModal();
    tab(start, true);
    search(q('[data-lp-q]').value);
  });
}

/** Kompatibel: nur die Adresse (Zeichenkette) oder null */
export async function pickLink() {
  const r = await openLinkPicker({ mode: 'field' });
  return r && !r.remove ? r.value : null;
}

// ------------------------------------------------------------------ Feldtyp „link“ (Core\Fields::renderLink)
function chipHtml(x) {
  return `<span class="f-link__type">${esc(x.type || t(KIND[x.kind] || 'Adresse'))}</span> <span class="f-link__label">${esc(x.label)}</span>`
    + (x.href && x.href !== x.label && x.kind !== 'url' ? ` <span class="f-link__href">${esc(x.href)}</span>` : '')
    + (x.missing ? ` <span class="f-link__warn">${esc(t('Ziel nicht gefunden – bitte neu wählen'))}</span>` : '')
    + ` <button type="button" class="f-link__clear" data-link-clear aria-label="${esc(t('Link entfernen'))}" title="${esc(t('Link entfernen'))}">×</button>`;
}
function setChip(box, x) {
  const chip = box.querySelector('[data-link-chip]');
  if (!chip) return;
  if (!x) { chip.hidden = true; chip.innerHTML = ''; return; }
  chip.hidden = false;
  chip.classList.toggle('is-missing', !!x.missing);
  chip.dataset.kind = x.kind || '';
  chip.innerHTML = chipHtml(x);
}

export function initLinkFields(scope = d) {
  scope.querySelectorAll('[data-link-field]').forEach(box => {
    if (box._init) return; box._init = true;
    const input = box.querySelector('input');
    const fire = () => { input.dispatchEvent(new Event('input', { bubbles: true })); input.dispatchEvent(new Event('change', { bubbles: true })); };
    let timer;
    box.addEventListener('click', async e => {
      if (e.target.closest('[data-link-pick]')) {
        const r = await openLinkPicker({ mode: 'field', value: input.value.trim() });
        input.focus();
        if (!r) return;
        if (r.remove) { input.value = ''; setChip(box, null); fire(); return; }
        if (r.keep) return;
        input.value = r.value;
        setChip(box, { kind: r.kind, type: /:viewer$/.test(r.value) ? t('PDF im Viewer') : t(KIND[r.kind] || 'Adresse'), label: r.label, href: r.href });
        fire();
      } else if (e.target.closest('[data-link-clear]')) {
        input.value = ''; setChip(box, null); fire(); input.focus();
      }
    });
    input.addEventListener('input', e => {
      if (!e.isTrusted) return;   // von uns ausgelöst: Anzeige ist aktuell
      clearTimeout(timer);
      timer = setTimeout(async () => { const v = input.value.trim(); setChip(box, v ? await describe(v) : null); }, 400);
    });
  });
}
