/*
 * Assistent-Chat der Redaktion (Core\AI\Assistant) – wird von _assistant.js erst beim ersten Öffnen geladen.
 *
 *  - Seitenfenster rechts (nicht modal: die Verwaltung bleibt bedienbar; Escape schließt, Fokus zurück zum Auslöser),
 *    auf schmalen Bildschirmen Vollbild; im KI-Bereich (/admin/ai/assistent) im Hauptbereich.
 *  - Frage → Server-Sent Events (POST /admin/api/assistant/ask): Quellen, Textstücke, Aktionen (Karten mit Vorschau).
 *    „Ausführen“ sendet die signierte Aktion (POST …/execute) – ohne Klick passiert nichts.
 *  - Kontext: Adresse und Titel des aktuellen Bildschirms (abschaltbar), Bild aus der Mediathek (#m123).
 *  - Verlauf dieses Tabs in sessionStorage (übersteht Seitenwechsel); auf dem Server nur Metadaten, außer „Speichern“.
 *  - Farben: Akzent der Verwaltung (persönliche Akzentfarbe, Core\Accent), hell/dunkel wie die Verwaltung.
 */
import { sseFetch } from './_sse.js';
import { renderMd, esc } from './_chatmd.js';
import { t } from './_i18n.js';

const d = document;
let cfg = null, ui = null, busy = null, opener = null, page = false;
let st = { conv: '', msgs: [], saved: false, pinned: false, ctx: true };

const KEY = () => `kas:${cfg.site}:${cfg.user}`;
const OPEN = () => `kas-open:${cfg.site}:${cfg.user}`;
const ss = {
  get(k) { try { return sessionStorage.getItem(k); } catch { return null; } },
  set(k, v) { try { v === null ? sessionStorage.removeItem(k) : sessionStorage.setItem(k, v); } catch { /* aus */ } },
};
const save = () => ss.set(KEY(), JSON.stringify({ ...st, msgs: st.msgs.filter(m => !m.pending).slice(-40) }));
const csrf = () => d.getElementById('adm-csrf')?.value || '';
const svg = p => `<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">${p}</svg>`;
const I = {
  logo: '<svg class="kas__logo" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path fill="currentColor" fill-rule="evenodd" d="M10.002,18.997C5.031,18.998 1.001,14.971 1,10.002C0.999,5.033 5.028,1.003 9.998,1.003C14.969,1.001 18.999,5.029 19,9.998C19.001,14.967 14.972,18.996 10.002,18.997ZM10.005,7.175L7.183,4.353L4.361,7.175L7.183,9.997L4.361,12.819L7.183,15.641L10.005,12.819L12.827,15.641L15.649,12.819L12.827,9.997L15.649,7.175L12.827,4.353L10.005,7.175Z"/></svg>',
  x: svg('<path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'),
  send: svg('<path fill="currentColor" d="M3 20.5 21 12 3 3.5l2.6 7.1L14 12l-8.4 1.4z"/>'),
  full: svg('<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'),
  hist: svg('<path d="M12 7v5l3 2M3.5 12a8.5 8.5 0 1 0 2.5-6L3.5 8.5M3.5 4v4.5H8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'),
  plus: svg('<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'),
  pin: svg('<path d="M9 3h6l-1 6 4 4H6l4-4zM12 13v8" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>'),
};

export function init(c) {
  cfg = c;
  if (!d.querySelector('link[data-kas-css]')) {
    const l = d.createElement('link');
    l.rel = 'stylesheet'; l.href = cfg.css; l.dataset.kasCss = '';
    d.head.append(l);
  }
  try { const s = JSON.parse(ss.get(KEY()) || 'null'); if (s && Array.isArray(s.msgs)) st = { ...st, ...s }; } catch { /* leer */ }
}

/** Nach Seitenwechsel: im KI-Bereich eingebettet, sonst wieder als Fenster (ohne Fokus zu stehlen) */
export function restore() {
  const host = d.querySelector('[data-assistant-page]');
  if (host) {
    page = true;
    mount(host);
    const q = host.dataset.q || '';
    if (q) { history.replaceState(null, '', location.pathname); ask(q); }
    initSavedList();
    return;
  }
  mount(null);
  show(false);
}

export function open(o = {}) {
  if (!ui) mount(d.querySelector('[data-assistant-page]'));
  if (!page) show(true);
  if (o.q) ask(o.q); else ui.q.focus();
}

export function toggle(o) {
  if (page) { ui?.q.focus(); return; }
  opener = o || d.activeElement;
  if (ui && !ui.root.hidden) close(); else open();
}

function show(focus) {
  ui.root.hidden = false;
  d.documentElement.classList.add('kas-is-open');
  ss.set(OPEN(), '1');
  d.querySelectorAll('[data-assistant]').forEach(b => b.setAttribute('aria-expanded', 'true'));
  renderCtx();
  if (focus) ui.q.focus();
}

function close() {
  if (page || !ui) return;
  ui.root.hidden = true;
  d.documentElement.classList.remove('kas-is-open');
  ss.set(OPEN(), null);
  d.querySelectorAll('[data-assistant]').forEach(b => b.setAttribute('aria-expanded', 'false'));
  (opener && d.contains(opener) ? opener : d.querySelector('[data-assistant]'))?.focus();
}

// ------------------------------------------------------------------ Aufbau

function mount(host) {
  if (ui) return;
  page = !!host;
  const root = d.createElement(page ? 'div' : 'aside');
  root.className = 'kas ' + (page ? 'kas--page' : 'kas--drawer');
  if (!page) { root.setAttribute('role', 'dialog'); root.setAttribute('aria-modal', 'false'); root.hidden = true; }
  root.setAttribute('aria-labelledby', 'kas-t');
  root.innerHTML = `
    <header class="kas__h">
      ${I.logo}<h2 id="kas-t">${esc(cfg.brand)} <span>${esc(t('Assistent'))}</span></h2>
      <button type="button" class="kas__ib" data-k="hist" aria-expanded="false" aria-controls="kas-hist" title="${esc(t('Gespeicherte Unterhaltungen'))}" aria-label="${esc(t('Gespeicherte Unterhaltungen'))}">${I.hist}</button>
      <button type="button" class="kas__ib" data-k="save" aria-pressed="false" title="${esc(t('Unterhaltung speichern'))}" aria-label="${esc(t('Unterhaltung speichern'))}">${I.pin}</button>
      <button type="button" class="kas__ib" data-k="new" title="${esc(t('Neue Unterhaltung'))}" aria-label="${esc(t('Neue Unterhaltung'))}">${I.plus}</button>
      ${page ? '' : `<a class="kas__ib" href="${esc(cfg.page)}" title="${esc(t('Als ganze Seite öffnen'))}" aria-label="${esc(t('Als ganze Seite öffnen'))}">${I.full}</a>
      <button type="button" class="kas__ib" data-k="close" title="${esc(t('Schließen'))} (Esc)" aria-label="${esc(t('Assistent schließen'))}">${I.x}</button>`}
    </header>
    <div class="kas__hist" id="kas-hist" hidden></div>
    <div class="kas__log" role="log" aria-live="off" aria-label="${esc(t('Unterhaltung'))}" tabindex="0"></div>
    <form class="kas__f">
      <label class="kas__ctx"><input type="checkbox" data-k="ctx"${st.ctx ? ' checked' : ''}> <span>${esc(t('Aktuellen Bildschirm mitsenden'))}</span> <small data-k="ctxl"></small></label>
      <div class="kas__row">
        <label class="sr-only" for="kas-q">${esc(t('Frage oder Auftrag'))}</label>
        <textarea id="kas-q" rows="2" maxlength="2000" placeholder="${esc(t('Wie geht …? Oder: Was soll ich erledigen?'))}" aria-describedby="kas-foot"></textarea>
        <button type="submit" class="kas__send" aria-label="${esc(t('Senden'))}">${I.send}</button>
      </div>
      <p class="kas__foot" id="kas-foot"></p>
    </form>
    <p class="sr-only" role="status" aria-live="polite" data-k="live"></p>`;
  (page ? host : d.body).append(root);
  if (page) host.querySelector('p.adm-muted')?.remove();
  const $ = s => root.querySelector(s);
  ui = { root, log: $('.kas__log'), q: $('#kas-q'), f: $('.kas__f'), send: $('.kas__send'), hist: $('#kas-hist'), foot: $('#kas-foot'),
    live: $('[data-k="live"]'), ctx: $('[data-k="ctx"]'), ctxl: $('[data-k="ctxl"]'), saveBtn: $('[data-k="save"]'), histBtn: $('[data-k="hist"]') };
  root.addEventListener('click', onClick);
  root.addEventListener('keydown', e => { if (e.key === 'Escape' && !page) { e.preventDefault(); close(); } });
  ui.f.addEventListener('submit', e => { e.preventDefault(); if (busy) { busy.abort(); return; } ask(ui.q.value); });
  ui.q.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); ui.f.requestSubmit(); } });
  ui.ctx.addEventListener('change', () => { st.ctx = ui.ctx.checked; save(); renderCtx(); });
  d.addEventListener('adm:location', renderCtx);
  render();
  renderCtx();
}

function context() {
  const media = /^#m(\d+)$/.exec(location.hash);
  return { route: location.pathname + location.search, title: d.querySelector('.adm-main h1')?.textContent?.trim() || d.title, media: media ? +media[1] : 0 };
}

function renderCtx() {
  if (!ui) return;
  const c = context();
  ui.ctxl.textContent = st.ctx ? '· ' + (c.title || c.route).slice(0, 60) : '';
}

function foot(quota) {
  if (quota !== undefined && quota !== null) cfg.quota = quota;
  ui.foot.textContent = t('Antworten stammen aus der Hilfe und können Fehler enthalten. Aktionen laufen erst nach „Ausführen“.')
    + (cfg.review ? ' ' + t('Aktionen gehen zur Freigabe („Eingereicht“).') : '')
    + (cfg.quota !== null && cfg.quota !== undefined ? ' ' + t('Heute noch {n} KI-Aufrufe.', { n: cfg.quota }) : '');
}

function render() {
  const intro = `<div class="kas-m kas-m--bot"><div class="kas-m__b"><p>${esc(t('Hallo! Ich beantworte Fragen zur Bedienung aus Handbuch, Tutorials und Wissensdatenbank – mit Links zu den Stellen. Ich kann auch Entwürfe vorschlagen; Sie prüfen und führen sie aus.'))}</p></div></div>`;
  const ex = [t('Wie lege ich eine neue Seite an?'), t('Wie setze ich einen Alt-Text für ein Bild?'), t('Was bedeutet „Eingereicht“?')];
  if (/^\/admin\/pages\/\d+/.test(location.pathname.replace(/^.*?(\/admin)/, '$1'))) ex.unshift(t('Schlage eine Beschreibung für Suchmaschinen für diese Seite vor.'));
  const chips = st.msgs.length ? '' : `<div class="kas-sg" role="group" aria-label="${esc(t('Beispiele'))}">` + ex.map(x => `<button type="button" class="kas-sg__b" data-ask>${esc(x)}</button>`).join('') + '</div>';
  ui.log.innerHTML = intro + chips + st.msgs.map(msgHtml).join('');
  ui.saveBtn.setAttribute('aria-pressed', String(!!st.saved));
  ui.saveBtn.classList.toggle('is-on', !!st.saved);
  foot();
  scroll(true);
}

function msgHtml(m, i) {
  if (m.role === 'user') return `<div class="kas-m kas-m--me" data-i="${i}"><span class="sr-only">${esc(t('Sie'))}: </span><div class="kas-m__b"><p>${esc(m.content).replace(/\n/g, '<br>')}</p></div></div>`;
  let body = m.pending && !m.content ? `<p class="kas-wait"><span></span><span></span><span></span><span class="sr-only">${esc(t('Antwort wird erstellt …'))}</span></p>`
    : renderMd(m.content, m.sources, (n, title) => t('Quelle {n}: {title}', { n, title }));
  if (m.status && m.pending) body += `<p class="kas-m__status">${esc(m.status)}</p>`;
  if (m.error) body += `<p class="kas-m__err" role="alert">${esc(m.error)}</p>`;
  const cited = !m.pending && (m.cited || []).length ? m.cited : [];
  if (cited.length) body += `<div class="kas-src"><p>${esc(t('Quellen'))}</p><ul>` + cited.map(s => `<li><a href="${esc(s.url)}"><span aria-hidden="true">${esc(s.n)}</span> ${esc(s.title)}</a></li>`).join('') + '</ul></div>';
  body += (m.actions || []).map((c, k) => cardHtml(c, i, k)).join('');
  return `<div class="kas-m kas-m--bot" data-i="${i}"><span class="sr-only">${esc(cfg.brand)}: </span><div class="kas-m__b">${body}</div></div>`;
}

function cardHtml(c, i, k) {
  const val = (v, html) => v === null || v === undefined || v === '' ? `<em>${esc(t('leer'))}</em>` : (html ? v : esc(v));
  const rows = (c.fields || []).map(f => `<div class="kas-card__f"><p class="kas-card__l">${esc(f.label)}</p>
    ${f.before !== null && f.before !== undefined ? `<div class="kas-card__v kas-card__v--old"><span class="sr-only">${esc(t('Bisher'))}: </span>${val(stripHtml(f.before), false)}</div>` : ''}
    <div class="kas-card__v kas-card__v--new"><span class="sr-only">${esc(t('Neu'))}: </span>${val(f.after, f.html)}</div></div>`).join('');
  const notes = (c.notes || []).map(n => `<li>${esc(n)}</li>`).join('');
  let foot = '';
  if (c.state === 'done') foot = `<p class="kas-card__ok" role="status">✓ ${esc(c.message || t('Erledigt'))}${c.url ? ` <a href="${esc(c.url)}">${esc(c.review ? t('Zur Freigabe') : t('Öffnen'))}</a>` : ''}</p>`;
  else if (c.state === 'dismissed') foot = `<p class="kas-card__muted">${esc(t('Verworfen'))}</p>`;
  else if (!c.allowed) foot = `<p class="kas-card__no">${esc(c.reason || t('Nicht möglich'))}</p>`;
  else foot = `${c.error ? `<p class="kas-m__err" role="alert">${esc(c.error)}</p>` : ''}<div class="kas-card__btns">
      <button type="button" class="adm-btn adm-btn--primary adm-btn--small" data-run="${i}:${k}"${c.state === 'running' ? ' disabled aria-busy="true"' : ''}>${esc(c.review ? t('Zur Freigabe einreichen') : t('Ausführen'))}</button>
      <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-drop="${i}:${k}">${esc(t('Verwerfen'))}</button></div>`;
  return `<article class="kas-card${c.allowed ? '' : ' is-denied'}" aria-label="${esc(c.label)}">
    <header><p class="kas-card__t">${esc(c.label)}</p><p class="kas-card__g">${esc(c.target || '')}</p></header>
    ${rows}${notes ? `<ul class="kas-card__n">${notes}</ul>` : ''}${foot}</article>`;
}

function stripHtml(s) {
  if (s === null || s === undefined) return s;
  const x = d.createElement('div');
  x.innerHTML = String(s).replace(/<(script|style)[\s\S]*?<\/\1>/gi, '');
  return x.textContent.trim();
}

function update(i) {
  const el = ui.log.querySelector(`.kas-m[data-i="${i}"]`);
  const html = msgHtml(st.msgs[i], i);
  if (el) el.outerHTML = html; else ui.log.insertAdjacentHTML('beforeend', html);
  scroll();
}

function scroll(force = false) {
  const l = ui.log;
  if (force || l.scrollHeight - l.scrollTop - l.clientHeight < 140) l.scrollTop = l.scrollHeight;
}

// ------------------------------------------------------------------ Fragen

async function ask(text) {
  const q = String(text || '').trim();
  if (q.length < 2) return;
  if (busy) busy.abort();
  if (ui.root.hidden && !page) show(false);
  const history = st.msgs.filter(m => !m.pending && m.content).slice(-8).map(m => ({ role: m.role, content: m.content }));
  st.msgs.push({ role: 'user', content: q });
  const i = st.msgs.push({ role: 'assistant', content: '', sources: [], actions: [], pending: true }) - 1;
  const m = st.msgs[i];
  ui.q.value = '';
  render();
  ui.live.textContent = t('Antwort wird erstellt …');
  busy = new AbortController();
  ui.send.classList.add('is-stop');
  ui.send.setAttribute('aria-label', t('Antwort abbrechen'));
  const done = data => {
    m.content = data.text || m.content;
    m.cited = data.sources || [];
    m.actions = (data.actions || []).map(c => ({ ...c, state: '' }));
    if (data.conv) st.conv = data.conv;
    foot(data.quota);
  };
  try {
    await sseFetch(cfg.base + '/ask', {
      signal: busy.signal, headers: { 'X-CSRF-Token': csrf() },
      body: { q, history, conv: st.conv, use_context: st.ctx ? 1 : 0, context: st.ctx ? context() : { route: location.pathname } },
      onEvent: (ev, data, status) => {
        if (ev === 'sources') m.sources = data.items || [];
        else if (ev === 'delta') { m.content += data.t || ''; update(i); }
        else if (ev === 'status') { m.status = data.text; update(i); }
        else if (ev === 'done') done(data);
        else if (ev === 'error') { m.error = data.error || t('Die KI-Anfrage ist fehlgeschlagen. Bitte später erneut versuchen.'); foot(data.quota); }
        else if (ev === 'json') { if (data.ok) done(data); else m.error = data.error || t('Die KI-Anfrage ist fehlgeschlagen. Bitte später erneut versuchen.') + (status ? ` (${status})` : ''); }
      },
    });
  } catch (e) {
    if (e.name !== 'AbortError') m.error = t('Die KI-Anfrage ist fehlgeschlagen. Bitte später erneut versuchen.');
  }
  m.pending = false;
  m.status = '';
  busy = null;
  ui.send.classList.remove('is-stop');
  ui.send.setAttribute('aria-label', t('Senden'));
  update(i);
  save();
  if (st.saved) persist();
  ui.live.textContent = stripHtml(msgHtml(m, i)).replace(/\s+/g, ' ').slice(0, 600);
  ui.q.focus();
}

// ------------------------------------------------------------------ Aktionen, Verlauf

async function post(path, body) {
  const r = await fetch(cfg.base + path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify(body) });
  let j = {};
  try { j = await r.json(); } catch { j = { ok: false, error: t('Unerwartete Antwort vom Server.') }; }
  return j;
}

async function run(i, k) {
  const c = st.msgs[i]?.actions?.[k];
  if (!c || c.state === 'running' || c.state === 'done') return;
  c.state = 'running'; c.error = '';
  update(i);
  const j = await post('/execute', { action: c.action, args: c.args, sig: c.sig });
  if (j.ok) { c.state = 'done'; c.message = j.message; c.url = j.url; c.review = !!j.review; foot(j.quota); }
  else { c.state = ''; c.error = j.error || t('Das hat nicht geklappt.'); }
  update(i);
  save();
  if (st.saved) persist();
  ui.live.textContent = c.state === 'done' ? c.message : c.error;
  ui.log.querySelector(`.kas-m[data-i="${i}"] .kas-card:nth-of-type(${k + 1}) a, .kas-m[data-i="${i}"] .kas-card:nth-of-type(${k + 1}) button`)?.focus();
}

async function persist() {
  const j = await post('/save', { id: st.conv, messages: st.msgs.filter(m => !m.pending).map(m => ({ role: m.role, content: m.content, sources: m.cited || [] })), pinned: st.pinned ? 1 : 0 });
  if (j.ok) { st.conv = j.id; st.saved = true; save(); }
  return j;
}

async function toggleSave() {
  if (st.saved) {
    if (!confirm(t('Gespeicherte Inhalte dieser Unterhaltung löschen? Die Unterhaltung bleibt in diesem Fenster, bis Sie sie schließen.'))) return;
    await post('/history/' + encodeURIComponent(st.conv) + '/delete', {});
    st.saved = false; st.pinned = false; st.conv = '';
    save(); render();
    ui.live.textContent = t('Gespeicherte Inhalte gelöscht.');
    return;
  }
  if (!st.msgs.length) { ui.live.textContent = t('Nichts zu speichern.'); return; }
  st.pinned = true;
  const j = await persist();
  render();
  ui.live.textContent = j.ok ? t('Unterhaltung gespeichert.') : (j.error || t('Das hat nicht geklappt.'));
}

async function showHistory() {
  const open = ui.hist.hidden;
  ui.hist.hidden = !open;
  ui.histBtn.setAttribute('aria-expanded', String(open));
  if (!open) return;
  ui.hist.innerHTML = `<p class="kas-muted">${esc(t('Lädt …'))}</p>`;
  const r = await fetch(cfg.base + '/history', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  const j = await r.json().catch(() => ({ ok: false }));
  const list = j.saved || [];
  ui.hist.innerHTML = `<p class="kas-hist__h">${esc(t('Gespeicherte Unterhaltungen'))}</p>` + (list.length
    ? '<ul>' + list.map(c => `<li><button type="button" class="kas-hist__o" data-conv="${esc(c.id)}">${esc(c.title || t('Unterhaltung'))}</button> <small>${esc(new Date(c.updated.replace(' ', 'T')).toLocaleString())}</small></li>`).join('') + '</ul>'
    : `<p class="kas-muted">${esc(t('Noch keine gespeicherten Unterhaltungen. Mit der Stecknadel speichern Sie die aktuelle.'))}</p>`);
  ui.hist.querySelector('button')?.focus();
}

async function openConv(id) {
  const r = await fetch(cfg.base + '/history/' + encodeURIComponent(id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
  const j = await r.json().catch(() => ({ ok: false }));
  if (!j.ok) { ui.live.textContent = j.error || t('Unterhaltung nicht gefunden.'); return; }
  busy?.abort();
  st = { ...st, conv: j.conversation.id, saved: true, pinned: j.conversation.pinned, msgs: (j.conversation.messages || []).map(m => ({ ...m, cited: m.sources || [], sources: m.sources || [] })) };
  ui.hist.hidden = true;
  ui.histBtn.setAttribute('aria-expanded', 'false');
  save(); render();
  if (!page) show(false);
  ui.q.focus();
}

function onClick(e) {
  const b = e.target.closest('button, a');
  if (!b || !ui.root.contains(b)) return;
  const k = b.dataset.k;
  if (k === 'close') close();
  else if (k === 'new') { busy?.abort(); st = { conv: '', msgs: [], saved: false, pinned: false, ctx: st.ctx }; save(); render(); ui.q.focus(); }
  else if (k === 'save') toggleSave();
  else if (k === 'hist') showHistory();
  else if (b.dataset.conv) openConv(b.dataset.conv);
  else if (b.hasAttribute('data-ask')) ask(b.textContent);
  else if (b.dataset.run) { const [i, n] = b.dataset.run.split(':').map(Number); run(i, n); }
  else if (b.dataset.drop) { const [i, n] = b.dataset.drop.split(':').map(Number); const c = st.msgs[i]?.actions?.[n]; if (c) { c.state = 'dismissed'; update(i); save(); } }
}

/** Seite im KI-Bereich: gespeicherte Unterhaltungen öffnen/löschen */
function initSavedList() {
  d.addEventListener('click', async e => {
    const o = e.target.closest?.('[data-kas-open]');
    if (o) { e.preventDefault(); openConv(o.dataset.kasOpen); ui.q.scrollIntoView({ block: 'center' }); return; }
    const del = e.target.closest?.('[data-kas-delete]');
    if (!del) return;
    e.preventDefault();
    if (!confirm(t('Diese Unterhaltung endgültig löschen?'))) return;
    const j = await post('/history/' + encodeURIComponent(del.dataset.kasDelete) + '/delete', {});
    if (j.ok) {
      del.closest('li, tr')?.remove();
      if (st.conv === del.dataset.kasDelete) { st.saved = false; st.conv = ''; save(); render(); }
      const live = d.getElementById('adm-live');
      if (live) live.textContent = t('Gelöscht.');
    }
  });
}
