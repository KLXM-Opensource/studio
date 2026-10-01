/*
 * Linkauswahl (Core, Teil von admin.js) – ein Dialog für Rich-Text (Formatierungsleiste, ⌘K) und Felder vom Typ „link“.
 *
 *  - Reiter „Seiten & Inhalte“ mit drei Ansichten (Umschalter Suche | Struktur | Daten, zuletzt gewählte pro Browser in localStorage):
 *    Suche: Live-Ergebnisse, gruppiert: Zuletzt verwendet · Anker auf dieser Seite · Seiten (Pfad im Seitenbaum, Sprache)
 *    · ohne Suchbegriff „Neueste Einträge“ (alle Inhaltstabellen mit Detailseite, zuletzt geändert zuerst), mit Suchbegriff
 *    Einträge je Tabelle · Dateien & Medien (PDF im Viewer oder direkt) · Sonderziele des Kits (nur Felder).
 *    Gruppen mit mehr Treffern enden mit „Weitere laden“ (selbst eine Option: per Tastatur erreichbar).
 *    Quelle: GET /admin/api/links?format=groups&q=…&page=…&mode=rich|field[&group=…&offset=…&limit=…] (Core\Links::sources)
 *    Struktur: echter Seitenbaum (Reihenfolge und Ebenen wie unter „Seiten“, eine Sprache, Status Offline/Entwurf), Anker als
 *    Unterpunkte; WAI-ARIA-Baum (role=tree/treeitem, aria-level, aria-expanded) mit aria-activedescendant.
 *    Quelle: GET /admin/api/links?format=tree&lang=…&page=… (Core\Links::tree, ganzer Baum auf einmal)
 *    Daten: Auswahl einer Quelle (jede Inhaltstabelle mit Detailseite, Glossar bei eingeschalteter Funktion; zuletzt gewählte pro
 *    Browser), darunter ihre Einträge neueste zuerst (Titel, kurzes Datum, Entwurf) mit Filter und „Weitere laden“.
 *    Quellen: GET …?format=sources (Core\Links::dataSources); Einträge: …?format=groups&group=entries:{tabelle}&q=…&literal=1&offset=…
 *  - Reiter Web-Adresse (https-Prüfung, ergänzt https://, warnt bei http://), E-Mail (mailto, Betreff), Telefon (tel: wie tel_href())
 *  - Bearbeiten: zeigt das aktuelle Ziel, „Link entfernen“; Optionen „In neuem Tab öffnen“ und Linktitel (nur Rich-Text)
 *  - Tastatur: ↑/↓ in der Ergebnisliste (Suche und Daten), Enter übernimmt, Esc bricht ab, ←/→ zwischen den Reitern;
 *    im Baum ↑/↓ bewegen, → aufklappen/erstes Kind, ← zuklappen/Elternseite, Pos1/Ende, Enter übernimmt, Buchstabe → Suche
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
const VIEW_KEY = 'cms-links-view';   // zuletzt gewählte Ansicht: search | tree | data (nur Komfort)
const SRC_KEY = 'cms-links-source';  // zuletzt gewählte Quelle der Ansicht „Daten“ (z. B. entries:news)
const VIEWS = ['search', 'tree', 'data'];
const MORE = 30;                     // „Weitere laden“: so viele auf einmal
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
function savedView() {
  try { const v = localStorage.getItem(VIEW_KEY); return VIEWS.includes(v) ? v : 'search'; } catch { return 'search'; }
}
function savedSource() {
  try { return localStorage.getItem(SRC_KEY) || ''; } catch { return ''; }
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
        <div class="lp__bar">
          <input type="search" class="lp__q" data-lp-q role="combobox" aria-expanded="true" aria-controls="cms-lp-list" aria-autocomplete="list"
            aria-label="${esc(t('Linkziel suchen'))}" autocomplete="off" spellcheck="false" placeholder="${esc(t('Seite, Eintrag, Datei oder Anker suchen …'))}">
          <div class="lp__datah" data-lp-datah hidden>
            <select class="lp__src" data-lp-src aria-label="${esc(t('Datenquelle'))}"></select>
            <input type="search" class="lp__q" data-lp-filter role="combobox" aria-expanded="true" aria-controls="cms-lp-dlist" aria-autocomplete="list"
              aria-label="${esc(t('Einträge filtern'))}" autocomplete="off" spellcheck="false" placeholder="${esc(t('Filtern …'))}">
          </div>
          <p class="lp__treeh" data-lp-treeh hidden><span data-lp-count></span><span class="lp__langs" role="group" aria-label="${esc(t('Sprache'))}" data-lp-langs></span></p>
          <div class="lp__seg" role="group" aria-label="${esc(t('Ansicht'))}">
            <button type="button" data-lp-view="search" aria-pressed="true" title="${esc(t('Suche'))}">${ico('magnifying-glass')}<span>${esc(t('Suche'))}</span></button>
            <button type="button" data-lp-view="tree" aria-pressed="false" title="${esc(t('Struktur (Seitenbaum)'))}">${ico('tree-structure')}<span>${esc(t('Struktur'))}</span></button>
            <button type="button" data-lp-view="data" aria-pressed="false" title="${esc(t('Daten (Tabellen und Glossar)'))}">${ico('database')}<span>${esc(t('Daten'))}</span></button>
          </div>
        </div>
        <div class="lp__list" id="cms-lp-list" role="listbox" aria-label="${esc(t('Linkziele'))}" data-lp-list></div>
        <div class="lp__list" id="cms-lp-dlist" role="listbox" aria-label="${esc(t('Einträge'))}" data-lp-dlist hidden></div>
        <ul class="lp__list lp__tree" id="cms-lp-tree" role="tree" aria-label="${esc(t('Seitenbaum'))}" tabindex="0" data-lp-tree hidden></ul>
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
      <p class="lp__keys f-help" data-lp-keys>${esc(t('Tastatur: ↑/↓ Ergebnis wählen · Enter übernehmen · Esc abbrechen'))}</p>
    </form></dialog>`);
  dlg = box.querySelector('#cms-lp');
  wire();
  return dlg;
}

const q = s => dlg.querySelector(s);
const qa = s => [...dlg.querySelectorAll(s)];

/** Combobox-Tastatur für ein Eingabefeld und seine Liste (Suche bzw. Filter der Ansicht „Daten“) */
function listKeys(input, list) {
  input.addEventListener('keydown', e => {
    const opts = [...list.querySelectorAll('[role=option]')];
    if (!opts.length) return;
    const i = opts.findIndex(o => o.id === input.getAttribute('aria-activedescendant'));
    let n = null;
    if (e.key === 'ArrowDown') n = i < 0 ? 0 : Math.min(opts.length - 1, i + 1);
    else if (e.key === 'ArrowUp') n = i <= 0 ? 0 : i - 1;
    else if (e.key === 'Home' && e.ctrlKey) n = 0;
    else if (e.key === 'End' && e.ctrlKey) n = opts.length - 1;
    else if (e.key === 'Enter' && i >= 0) { e.preventDefault(); choose(opts[i], !opts[i].dataset.more); return; }
    if (n !== null) { e.preventDefault(); activate(opts[n]); }
  });
  list.addEventListener('click', e => { const o = e.target.closest('[role=option]'); if (o) choose(o, false); });
  list.addEventListener('dblclick', e => { const o = e.target.closest('[role=option]'); if (o && !o.dataset.more) choose(o, true); });
  list.addEventListener('mousedown', e => e.preventDefault());   // Fokus bleibt im Eingabefeld
}

function wire() {
  const input = q('[data-lp-q]'), list = q('[data-lp-list]');
  let timer;
  input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => search(input.value), 140); });
  listKeys(input, list);
  // Daten: Quelle wählen, Einträge filtern
  const filter = q('[data-lp-filter]');
  let ftimer;
  filter.addEventListener('input', () => { clearTimeout(ftimer); ftimer = setTimeout(() => loadData(), 160); });
  listKeys(filter, q('[data-lp-dlist]'));
  q('[data-lp-src]').addEventListener('change', e => {
    try { localStorage.setItem(SRC_KEY, e.target.value); } catch {}
    state.data.src = e.target.value;
    loadData();
  });
  // Ansicht Suche | Struktur | Daten
  qa('[data-lp-view]').forEach(b => b.addEventListener('click', () => view(b.dataset.lpView, true)));
  // Seitenbaum
  const tree = q('[data-lp-tree]');
  tree.addEventListener('click', e => {
    const n = e.target.closest('[role=treeitem]');
    if (!n) return;
    tree.focus({ preventScroll: true });
    if (e.target.closest('[data-lp-twisty]')) { expand(n, n.getAttribute('aria-expanded') !== 'true'); activate(n, false); return; }
    choose(n, false);
  });
  tree.addEventListener('dblclick', e => { const n = e.target.closest('[role=treeitem]'); if (n && !e.target.closest('[data-lp-twisty]')) choose(n, true); });
  tree.addEventListener('mousedown', e => { if (e.target.closest('[data-lp-twisty]')) e.preventDefault(); });
  tree.addEventListener('focus', () => { if (!tree.getAttribute('aria-activedescendant')) { const n = tree.querySelector('[role=treeitem][aria-selected=true]') || visibleNodes()[0]; if (n) activate(n); } });
  tree.addEventListener('keydown', treeKey);
  q('[data-lp-langs]').addEventListener('click', e => { const b = e.target.closest('[data-lang]'); if (b) loadTree(b.dataset.lang, true); });
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
  if (focusField) (name === 'search' ? viewField() : q(`[data-lp-${name}]`))?.focus();
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

// ------------------------------------------------------------------ Ansicht Suche | Struktur | Daten
/** Element mit dem Fokus der aktuellen Ansicht: Suchfeld, Baum bzw. Filter (ohne Quellen: Auswahl) */
function viewField() {
  return state.view === 'tree' ? q('[data-lp-tree]') : state.view === 'data' ? q(state.data.sources?.length === 0 ? '[data-lp-src]' : '[data-lp-filter]') : q('[data-lp-q]');
}
/** Liste und zugehöriges Eingabefeld (aria-activedescendant) eines Elements in der Ansicht */
function boxOf(o) {
  if (o.closest('[data-lp-tree]')) return { box: q('[data-lp-tree]'), items: state.titems, owner: q('[data-lp-tree]'), sel: '[data-lp-tree] [role=treeitem]' };
  if (o.closest('[data-lp-dlist]')) return { box: q('[data-lp-dlist]'), items: state.data.items, owner: q('[data-lp-filter]'), sel: '[data-lp-dlist] [role=option]' };
  return { box: q('[data-lp-list]'), items: state.items, owner: q('[data-lp-q]'), sel: '[data-lp-list] [role=option]' };
}
function view(name, focus) {
  if (!state) return;
  state.view = name = VIEWS.includes(name) ? name : 'search';
  try { localStorage.setItem(VIEW_KEY, name); } catch {}
  qa('[data-lp-view]').forEach(b => b.setAttribute('aria-pressed', b.dataset.lpView === name ? 'true' : 'false'));
  const tree = name === 'tree', data = name === 'data', srch = name === 'search';
  q('[data-lp-q]').hidden = !srch;
  q('[data-lp-list]').hidden = !srch;
  q('[data-lp-treeh]').hidden = !tree;
  q('[data-lp-tree]').hidden = !tree;
  q('[data-lp-datah]').hidden = !data;
  q('[data-lp-dlist]').hidden = !data;
  q('[data-lp-keys]').textContent = tree ? t('Tastatur: ↑/↓ Seite wählen · →/← auf- und zuklappen · Enter übernehmen · Esc abbrechen')
    : t('Tastatur: ↑/↓ Ergebnis wählen · Enter übernehmen · Esc abbrechen');
  if (tree) {
    if (!state.tree) loadTree(state.treeLang, focus);
    else { syncSelected(q('[data-lp-tree]')); if (focus) q('[data-lp-tree]').focus(); }
  } else if (data) {
    if (!state.data.sources) loadSources(focus);
    else { syncSelected(q('[data-lp-dlist]')); if (focus) viewField().focus(); }
  } else {
    if (!state.groups) search(q('[data-lp-q]').value);
    else syncSelected(q('[data-lp-list]'));
    if (focus) q('[data-lp-q]').focus();
  }
}
/** Gewähltes Ziel, sonst der Verweis des aktuellen Links (alle Ansichten teilen sich state.picked) */
function targetValue() {
  return state.picked ? state.picked.value : state.current?.ref || '';
}
/** aria-selected nach dem gewählten bzw. aktuellen Ziel */
function syncSelected(box) {
  const tree = box.matches('[data-lp-tree]'), list = tree ? state.titems : box.matches('[data-lp-dlist]') ? state.data.items : state.items, v = targetValue();
  box.querySelectorAll(tree ? '[role=treeitem]' : '[role=option]').forEach(o => {
    const it = list[+o.dataset.i];
    o.setAttribute('aria-selected', v && it && it.value === v ? 'true' : 'false');
  });
}

// ------------------------------------------------------------------ Suche
let seq = 0;
async function fetchGroups(params) {
  const u = linksUrl();
  const r = await fetch(u + (u.includes('?') ? '&' : '?') + new URLSearchParams({ format: 'groups', page: currentPage(), mode: state.mode, ...params }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  return (await r.json()).groups || [];
}
async function search(qs) {
  const n = ++seq;
  const list = q('[data-lp-list]');
  list.setAttribute('aria-busy', 'true');
  let groups = [];
  try { groups = await fetchGroups({ q: qs.trim() }); } catch { groups = []; }
  if (n !== seq || !state) return;
  if (!qs.trim()) {
    const rec = recent().filter(r => state.mode === 'field' || r.kind !== 'keyword');
    if (rec.length) groups.unshift({ id: 'recent', label: t('Zuletzt verwendet'), icon: 'clock', items: rec });
  }
  // Eingabe sieht aus wie Adresse/E-Mail/Telefon → Vorschlag als erstes Ergebnis
  const guess = guessDirect(qs);
  if (guess) groups.unshift({ id: 'direct', label: t('Direkt verlinken'), icon: guess.kind === 'mail' ? 'at' : guess.kind === 'tel' ? 'phone' : 'globe', items: [guess] });
  state.q = qs.trim();
  state.groups = groups;
  render();
  list.removeAttribute('aria-busy');
  q('[data-lp-q]').removeAttribute('aria-activedescendant');
  const pre = list.querySelector('[aria-selected=true]');
  if (pre) activate(pre, false);
}
/** Zustand des Status für Seiten (online | offline | draft) bzw. Einträge (draft) → Kennzeichen */
function stateBadge(it) {
  if (!it.draft) return '';
  return `<span class="lp__badge lp__badge--draft">${esc(it.state === 'offline' ? t('Offline') : t('Entwurf'))}</span>`;
}
/** Eine Option der Ergebnisliste (Suche und Daten) */
function optHtml(it, id, i, icon, attrs = '') {
  const sel = !!it.value && it.value === targetValue();
  return `<div class="lp__opt${it.draft ? ' is-draft' : ''}" role="option" id="${id}" data-i="${i}"${attrs} aria-selected="${sel ? 'true' : 'false'}">`
    + (it.thumb ? `<img class="lp__thumb" src="${esc(it.thumb)}" alt="" width="36" height="36" loading="lazy">` : `<span class="lp__ico" aria-hidden="true">${ico(icon)}</span>`)
    + `<span class="lp__txt"><span class="lp__label">${esc(it.label)}</span><span class="lp__meta">${esc(it.meta || it.href || '')}</span></span>`
    + (it.badge ? `<span class="lp__badge" title="${esc(t('Sprache'))}">${esc(it.badge)}</span>` : '')
    + stateBadge(it) + '</div>';
}
/** „Weitere laden“ als Option */
function moreHtml(id, i, gid, meta) {
  return `<div class="lp__opt lp__more" role="option" id="${id}" data-i="${i}" data-more="${esc(gid)}" aria-selected="false">`
    + `<span class="lp__ico" aria-hidden="true">${ico('dots-three')}</span><span class="lp__txt"><span class="lp__label">${esc(t('Weitere laden'))}</span>`
    + `<span class="lp__meta">${esc(meta)}</span></span></div>`;
}
/** Ergebnisliste aus state.groups zeichnen (nach Suche und nach „Weitere laden“) */
function render() {
  const list = q('[data-lp-list]');
  state.items = [];
  let html = '', i = 0;
  for (const g of state.groups) {
    if (!g.items?.length) continue;
    const gid = 'cms-lp-g-' + esc(g.id).replace(/[^\w-]/g, '_');
    const more = g.total > g.items.length;
    html += `<div class="lp__group" role="group" aria-labelledby="${gid}"><p class="lp__gh" id="${gid}" role="presentation">${g.icon ? ico(g.icon) : ''} ${esc(g.label)}${more ? ` <small>${esc(t('{n} von {m}', { n: g.items.length, m: g.total }))}</small>` : ''}</p>`;
    g.items.forEach((it, gi) => {
      state.items.push(it);
      html += optHtml(it, 'cms-lp-o' + i, i, kindIcon(it, g), ` data-g="${esc(g.id)}" data-gi="${gi}"`);
      i++;
    });
    if (more) {   // „Weitere laden“ als Option: mit ↑/↓ erreichbar, Enter/Klick lädt nach
      state.items.push({ more: true, group: g.id });
      html += moreHtml('cms-lp-o' + i, i, g.id, t('{n} von {m} – {label}', { n: g.items.length, m: g.total, label: g.label }));
      i++;
    }
    html += '</div>';
  }
  list.innerHTML = html || `<p class="lp__empty">${esc(state.q ? t('Nichts gefunden. Tipp: Web-Adressen, E-Mail und Telefon über die Reiter oben eingeben.') : t('Noch keine Inhalte vorhanden.'))}</p>`;
}
/** Gruppe weiterblättern (offset = schon geladene Treffer), danach erstes neues Ergebnis aktiv */
async function loadMore(gid) {
  const g = state.groups?.find(x => x.id === gid);
  if (!g || state.loading) return;
  const n = seq, from = g.items.length;
  const opt = q(`[data-lp-list] [data-more="${gid}"]`);
  state.loading = true;
  opt?.setAttribute('aria-busy', 'true');
  opt?.querySelector('.lp__label')?.replaceChildren(t('Wird geladen …'));
  try {
    const res = (await fetchGroups({ q: state.q || '', group: gid, offset: from, limit: MORE }))[0];
    if (n !== seq || !state) return;
    if (res) { g.items.push(...res.items); g.total = res.total; } else g.total = g.items.length;
  } catch { if (state) g.total = g.items.length; }
  finally { if (state) state.loading = false; }
  render();
  const next = q(`[data-lp-list] [data-g="${gid}"][data-gi="${from}"]`) || q(`[data-lp-list] [data-g="${gid}"][data-gi="${from - 1}"]`);
  if (next) activate(next);
}

// ------------------------------------------------------------------ Daten (Tabellen mit Detailseite, Glossar)
async function loadSources(focus) {
  const sel = q('[data-lp-src]'), list = q('[data-lp-dlist]');
  list.setAttribute('aria-busy', 'true');
  let src = null;
  try {
    const u = linksUrl();
    const r = await fetch(u + (u.includes('?') ? '&' : '?') + new URLSearchParams({ format: 'sources' }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    src = (await r.json()).sources;
  } catch { src = null; }
  if (!state) return;
  list.removeAttribute('aria-busy');
  state.data.sources = Array.isArray(src) ? src : [];
  const all = state.data.sources;
  sel.innerHTML = all.map(x => `<option value="${esc(x.id)}">${esc(x.label)} (${esc(String(x.total))})</option>`).join('');
  sel.disabled = !all.length;
  q('[data-lp-filter]').disabled = !all.length;
  // Quelle: die des aktuellen Ziels (entry:{tabelle}:…), sonst die zuletzt gewählte, sonst die erste
  const ref = /^entry:([a-z][a-z0-9_]*):/.exec(state.current?.ref || '');
  const want = [ref && 'entries:' + ref[1], savedSource()].find(v => v && all.some(x => x.id === v));
  state.data.src = want || all[0]?.id || '';
  sel.value = state.data.src;
  if (!all.length) {
    list.innerHTML = `<p class="lp__empty">${esc(src ? t('Keine verlinkbaren Daten: Tabellen brauchen eine URL-Basis und eine Detailseite.') : t('Konnte nicht geladen werden.'))}</p>`;
    if (focus && state.view === 'data') sel.focus();
    return;
  }
  await loadData();
  if (focus && state?.view === 'data') viewField().focus();
}
/** Einträge der gewählten Quelle laden (more: weiterblättern, sonst neu ab dem ersten) */
async function loadData(more) {
  const dt = state?.data;
  if (!dt?.src || (more && dt.loading)) return;
  if (more && dt.items.at(-1)?.more) dt.items.pop();   // Platzhalter „Weitere laden“ (letzter Eintrag)
  const list = q('[data-lp-dlist]'), n = ++dt.seq, from = more ? dt.items.length : 0, filter = q('[data-lp-filter]').value.trim();
  dt.loading = true;
  if (more) {
    const opt = list.querySelector('[data-more]');
    opt?.setAttribute('aria-busy', 'true');
    opt?.querySelector('.lp__label')?.replaceChildren(t('Wird geladen …'));
  } else list.setAttribute('aria-busy', 'true');
  let g = null, failed = false;
  try { g = (await fetchGroups({ q: filter, group: dt.src, literal: '1', offset: from, limit: MORE }))[0] || null; } catch { failed = true; }
  if (!state || n !== dt.seq) return;
  dt.loading = false;
  list.removeAttribute('aria-busy');
  if (!more) dt.items = [];
  if (g) { dt.items.push(...g.items); dt.total = g.total; } else if (!more || failed) dt.total = dt.items.length;
  dt.failed = failed && !dt.items.length;
  dt.filter = filter;
  renderData();
  q('[data-lp-filter]').removeAttribute('aria-activedescendant');
  const next = more ? list.querySelector(`[data-i="${from}"]`) || list.querySelector(`[data-i="${from - 1}"]`) : list.querySelector('[aria-selected=true]');
  if (next) activate(next, !!more);
}
function renderData() {
  const dt = state.data, list = q('[data-lp-dlist]'), src = dt.sources.find(x => x.id === dt.src);
  const icon = src?.icon || 'database';
  let html = dt.items.map((it, i) => optHtml(it, 'cms-lp-d' + i, i, it.icon || icon)).join('');
  if (dt.total > dt.items.length) {
    const i = dt.items.length;
    dt.items.push({ more: true, data: true });
    html += moreHtml('cms-lp-d' + i, i, dt.src, t('{n} von {m} – {label}', { n: i, m: dt.total, label: src?.label || '' }));
  }
  list.innerHTML = html || `<p class="lp__empty">${esc(dt.failed ? t('Konnte nicht geladen werden.') : dt.filter ? t('Nichts gefunden.') : t('Noch keine Einträge.'))}</p>`;
}

// ------------------------------------------------------------------ Struktur (Seitenbaum)
const openNodes = new Set();   // aufgeklappte Seiten (bleibt, solange die Seite offen ist)
async function loadTree(lang, focus) {
  const tree = q('[data-lp-tree]'), n = ++state.treeSeq;
  tree.setAttribute('aria-busy', 'true');
  let data = null;
  try {
    const u = linksUrl();
    const r = await fetch(u + (u.includes('?') ? '&' : '?') + new URLSearchParams({ format: 'tree', lang: lang || '', page: currentPage() }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    data = await r.json();
  } catch { data = null; }
  if (!state || n !== state.treeSeq) return;
  tree.removeAttribute('aria-busy');
  state.tree = data || { nodes: [], langs: {}, count: 0 };
  state.treeLang = state.tree.lang || '';
  renderTree();
  if (focus) tree.focus();
}
function renderTree() {
  const tree = q('[data-lp-tree]'), data = state.tree, cur = String(currentPage() || '');
  // Sprache(n) und Anzahl
  const langs = Object.entries(data.langs || {});
  q('[data-lp-count]').textContent = t('{n} Seiten', { n: data.count || 0 });
  q('[data-lp-langs]').innerHTML = langs.length > 1 ? langs.map(([c, l]) => `<button type="button" data-lang="${esc(c)}" aria-pressed="${c === data.lang ? 'true' : 'false'}" title="${esc(l)}">${esc(c.toUpperCase())}</button>`).join('') : '';
  // Gewähltes bzw. aktuelles Ziel sichtbar machen: Vorfahren aufklappen
  const raw = state.picked?.value || state.current?.ref || '', target = raw.replace(/#.*$/, '');
  const path = [];
  const find = (nodes, trail) => nodes.some(nd => (nd.value === target ? (path.push(...trail), true) : find(nd.children || [], [...trail, nd.id])));
  if (/^page:\d+$/.test(target) && find(data.nodes || [], []) && raw.includes('#')) path.push(+target.slice(5));   // Anker: Seite aufklappen
  if (!openNodes.size) (data.nodes || []).forEach(nd => { if (nd.children?.length) openNodes.add(nd.id); });   // anfangs: oberste Ebene mit Unterseiten offen, Anker zu
  path.forEach(id => openNodes.add(id));
  state.titems = [];
  let i = 0;
  const row = (it, level, kids) => {
    const k = i++;
    state.titems.push(it);
    const sel = state.picked ? state.picked.value === it.value : state.current?.ref === it.value;
    const open = it.kind === 'page' && openNodes.has(it.id);
    const icon = it.kind === 'anchor' ? 'hash' : it.home ? 'house' : kids ? 'folder' : 'file-text';
    return `<li role="treeitem" id="cms-lp-t${k}" data-i="${k}"${it.kind === 'page' ? ` data-id="${it.id}"` : ''} aria-level="${level}" aria-labelledby="cms-lp-tl${k} cms-lp-tb${k}"`
      + (kids ? ` aria-expanded="${open ? 'true' : 'false'}"` : '') + ` aria-selected="${sel ? 'true' : 'false'}" class="lp__ti${it.draft ? ' is-draft' : ''}">`
      + `<div class="lp__tr" style="--lvl:${level - 1}">`
      + (kids ? `<button type="button" class="lp__twisty" data-lp-twisty tabindex="-1" aria-hidden="true"></button>` : '<span class="lp__twisty lp__twisty--none"></span>')
      + `<span class="lp__ico" aria-hidden="true">${ico(icon)}</span>`
      + `<span class="lp__txt"><span class="lp__label" id="cms-lp-tl${k}">${esc(it.label)}</span><span class="lp__meta">${esc(it.meta || it.href || '')}</span></span>`
      + `<span id="cms-lp-tb${k}">${stateBadge(it)}</span></div>`;
  };
  const walk = (nodes, level) => nodes.map(nd => {
    const anchors = (nd.anchors || []).map(a => String(nd.id) === cur ? { ...a, value: a.meta, href: a.meta } : a);   // aktuelle Seite: #anker
    const kids = anchors.length + (nd.children?.length || 0);
    let h = row(nd, level, kids);
    if (kids) h += '<ul role="group">' + anchors.map(a => row(a, level + 1, 0) + '</li>').join('') + walk(nd.children || [], level + 1) + '</ul>';
    return h + '</li>';
  }).join('');
  tree.innerHTML = walk(data.nodes || [], 1) || `<li class="lp__empty" role="none">${esc(t('Noch keine Seiten vorhanden.'))}</li>`;
  tree.removeAttribute('aria-activedescendant');
  const sel = tree.querySelector('[role=treeitem][aria-selected=true]');
  if (sel) activate(sel);
}
/** Sichtbare Knoten (kein zugeklappter Vorfahre) in Baumreihenfolge */
function visibleNodes() {
  return qa('[data-lp-tree] [role=treeitem]').filter(n => !n.parentElement.closest('[role=treeitem][aria-expanded=false]'));
}
function expand(n, open) {
  if (!n?.hasAttribute('aria-expanded')) return;
  n.setAttribute('aria-expanded', open ? 'true' : 'false');
  const id = +n.dataset.id;
  if (id) open ? openNodes.add(id) : openNodes.delete(id);
}
function treeKey(e) {
  const tree = e.currentTarget;
  const nodes = visibleNodes();
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
  } else if (e.key === 'Enter' && cur) { e.preventDefault(); choose(cur, true); return; }
  else if (e.key === ' ' && cur) { e.preventDefault(); choose(cur, false); return; }
  else if (e.key === '*' && cur) {   // alle Geschwister aufklappen (WAI-ARIA)
    [...cur.parentElement.children].forEach(x => expand(x, true));
  } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey && /\S/.test(e.key)) {
    // Tippen → zur Suche wechseln und weiterschreiben
    e.preventDefault();
    const inp = q('[data-lp-q]');
    inp.value = e.key;
    view('search', true);
    search(inp.value);
    return;
  } else return;
  e.preventDefault();
  if (n) activate(n);
}
function kindIcon(it, g) {
  return { page: 'file-text', anchor: 'hash', entry: it.icon || g.icon || 'database', file: it.pdf ? 'file-pdf' : 'file', keyword: 'star', url: 'globe', mail: 'at', tel: 'phone' }[it.kind] || 'link';
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
  const { box, owner } = boxOf(o);
  box.querySelectorAll('.is-active').forEach(x => x.classList.remove('is-active'));
  o.classList.add('is-active');
  owner.setAttribute('aria-activedescendant', o.id);
  if (scroll) (o.querySelector(':scope > .lp__tr') || o).scrollIntoView({ block: 'nearest' });
}
function choose(o, apply) {
  const { items, sel } = boxOf(o);
  const it = items[+o.dataset.i];
  if (!it) return;
  if (it.more) { it.data ? loadData(true) : loadMore(it.group); return; }
  qa(sel).forEach(x => x.setAttribute('aria-selected', x === o ? 'true' : 'false'));
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
    state = { resolve, mode, current: cur && (cur.href || cur.ref) ? cur : null, picked: null, items: [], titems: [], groups: null, q: '',
      tab: 'search', view: 'search', tree: null, treeLang: '', treeSeq: 0, blankTouched: false,
      data: { sources: null, src: '', items: [], total: 0, filter: '', seq: 0, loading: false } };
    q('#cms-lp-t').textContent = state.current ? t('Link bearbeiten') : t('Link einfügen');
    qa('[data-lp-richonly]').forEach(n => { n.hidden = mode !== 'rich'; });
    q('[data-lp-remove]').hidden = !state.current;
    q('[data-lp-remove]').textContent = mode === 'field' ? t('Ziel entfernen') : t('Link entfernen');
    q('[data-lp-blank]').checked = !!cur?.newTab;
    q('[data-lp-blank]').onchange = () => { if (state) state.blankTouched = true; };
    q('[data-lp-title]').value = cur?.title || '';
    for (const k of ['q', 'filter', 'url', 'mail', 'subject', 'tel']) q(`[data-lp-${k}]`).value = '';
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
    q('[data-lp-list]').innerHTML = '';
    q('[data-lp-tree]').innerHTML = '';
    q('[data-lp-dlist]').innerHTML = '';
    q('[data-lp-src]').innerHTML = '';
    dlg.showModal();
    tab(start, false);
    // Ansicht: wie zuletzt gewählt – außer der markierte Text ist schon eine Adresse (dann Suche mit Vorschlag)
    view(q('[data-lp-q]').value ? 'search' : savedView(), start === 'search');
    if (start !== 'search') tab(start, true);
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
