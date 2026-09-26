/*
 * Besucher-Chat – Chat-Fenster (Core\AI\VisitorChat). Wird von visitor-chat-start.js erst nach dem ersten Klick geladen.
 *
 *  - Shadow DOM am Starter-Host (Theme-CSS wirkt nicht hinein), Farben aus den Design-Tokens des Themes (Akzent) und
 *    aus Hintergrund/Schrift der Seite (hell/dunkel automatisch).
 *  - Modaler <dialog> (Fokus bleibt im Chat, Escape schließt, Fokus zurück zum Knopf); auf Telefonen als Vollbild.
 *  - Antworten als Server-Sent Events (POST /api/chat); Belege [n] werden zu Links auf die Quellen.
 *  - Keine Cookies. Der Verlauf bleibt nur in diesem Tab (sessionStorage) und lässt sich mit „Neuer Chat“ löschen.
 */
import { sseFetch } from './_sse.js';
import { renderMd, esc } from './_chatmd.js';

const d = document;
const KEY = 'cms-chat:v1';
let ui = null, cfg = null, busy = null;
let st = { accepted: false, msgs: [] };

const load = () => { try { const s = JSON.parse(sessionStorage.getItem(KEY) || 'null'); if (s && Array.isArray(s.msgs)) st = s; } catch { /* privat/aus */ } };
const save = () => { try { sessionStorage.setItem(KEY, JSON.stringify({ accepted: st.accepted, msgs: st.msgs.filter(m => !m.pending).slice(-30) })); } catch { /* voll/aus */ } };
const L = k => cfg?.l10n?.[k] || k;

/** Öffnen (vom Starter aufgerufen): beim ersten Mal Texte laden und Oberfläche bauen */
export async function open(host, root, btn) {
  if (!ui) {
    load();
    try {
      const r = await fetch(host.dataset.api + '/config?lang=' + encodeURIComponent(host.dataset.lang || ''), { headers: { Accept: 'application/json' }, credentials: 'omit' });
      cfg = await r.json();
      if (!cfg.ok) throw new Error('off');
    } catch { btn.disabled = true; return; }
    await build(host, root, btn);
  }
  if (!ui.dlg.open) {
    colors(host);
    ui.dlg.showModal();
    btn.setAttribute('aria-expanded', 'true');
    render();
    (st.accepted ? ui.q : ui.accept).focus();
  }
}

/** Farben der Seite übernehmen (Hintergrund, Schrift) → hell/dunkel */
function colors(host) {
  const cs = el => getComputedStyle(el);
  const solid = c => c && !/rgba?\(0, 0, 0, 0\)|transparent/.test(c);
  let bg = cs(d.body).backgroundColor;
  if (!solid(bg)) bg = cs(d.documentElement).backgroundColor;
  if (!solid(bg)) bg = 'rgb(255, 255, 255)';
  const fg = cs(d.body).color || '#1a1a1a';
  const [r, g, b] = (bg.match(/\d+(\.\d+)?/g) || [255, 255, 255]).map(Number);
  const lum = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
  host.style.setProperty('--bg', bg);
  host.style.setProperty('--fg', fg);
  ui.dlg.classList.toggle('is-dark', lum < 0.45);
}

async function build(host, root, btn) {
  const link = d.createElement('link');
  link.rel = 'stylesheet';
  link.href = host.dataset.css;
  const loaded = new Promise(res => { link.onload = link.onerror = res; setTimeout(res, 3000); });
  root.append(link);
  const box = d.createElement('div');
  box.innerHTML = `<dialog class="w" aria-labelledby="cw-t">
    <header class="w__h">
      <h2 id="cw-t">${esc(cfg.title)}</h2>
      <button type="button" class="w__b w__new">${esc(L('new'))}</button>
      <button type="button" class="w__b w__x" aria-label="${esc(L('close'))}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
    </header>
    <div class="w__log" role="log" aria-live="off" aria-label="${esc(L('log'))}" tabindex="0"></div>
    <div class="w__p" hidden>
      <p class="w__pt"></p>
      <button type="button" class="w__go"></button>
    </div>
    <form class="w__f" hidden>
      <label class="sr" for="cw-q">${esc(L('placeholder'))}</label>
      <textarea id="cw-q" rows="1" maxlength="${Number(cfg.max) || 500}" placeholder="${esc(L('placeholder'))}" aria-describedby="cw-n cw-c" enterkeyhint="send"></textarea>
      <button type="submit" class="w__send" aria-label="${esc(L('send'))}"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 20.5 21 12 3 3.5l2.6 7.1L14 12l-8.4 1.4z"/></svg></button>
      <span id="cw-c" class="w__c" aria-live="polite"></span>
    </form>
    <p class="w__n" id="cw-n">${esc(L('note'))}</p>
    <p class="sr" role="status" aria-live="polite"></p>
  </dialog>`;
  root.append(box.firstElementChild);
  const q = s => root.querySelector(s);
  ui = { host, btn, dlg: q('.w'), log: q('.w__log'), p: q('.w__p'), pt: q('.w__pt'), accept: q('.w__go'), f: q('.w__f'), q: q('#cw-q'),
    send: q('.w__send'), count: q('.w__c'), live: q('[role=status]') };
  ui.pt.innerHTML = esc(cfg.privacy) + (cfg.privacy_url ? ` <a href="${esc(cfg.privacy_url)}">${esc(L('privacy_link'))}</a>` : '');
  ui.accept.textContent = L('start');
  ui.accept.addEventListener('click', () => { st.accepted = true; save(); render(); ui.q.focus(); });
  q('.w__x').addEventListener('click', () => ui.dlg.close());
  q('.w__new').addEventListener('click', () => { busy?.abort(); st.msgs = []; save(); render(); (st.accepted ? ui.q : ui.accept).focus(); });
  ui.dlg.addEventListener('close', () => { busy?.abort(); btn.setAttribute('aria-expanded', 'false'); btn.focus(); });
  // Klick auf den abgedunkelten Hintergrund schließt (nur Desktop – auf Telefonen füllt der Chat den Bildschirm)
  ui.dlg.addEventListener('click', e => { if (e.target === ui.dlg) { const r = ui.dlg.getBoundingClientRect(); if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) ui.dlg.close(); } });
  ui.f.addEventListener('submit', e => { e.preventDefault(); if (busy) { busy.abort(); return; } ask(ui.q.value); });
  ui.q.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); ui.f.requestSubmit(); } });
  ui.q.addEventListener('input', () => {
    ui.q.style.height = 'auto';
    ui.q.style.height = Math.min(ui.q.scrollHeight, 140) + 'px';
    const left = (Number(cfg.max) || 500) - ui.q.value.length;
    ui.count.textContent = left < 80 ? L('chars').replace('{n}', left) : '';
  });
  ui.log.addEventListener('click', e => {
    const s = e.target.closest?.('[data-suggest]');
    if (s) ask(s.textContent);
  });
  await loaded;
}

/** Ganze Oberfläche neu zeichnen (Datenschutz-Hinweis bzw. Eingabe, Begrüßung, Vorschläge, Verlauf) */
function render() {
  ui.p.hidden = st.accepted;
  ui.f.hidden = !st.accepted;
  let html = `<div class="m m--bot"><div class="m__b">${renderMd(cfg.greeting)}</div></div>`;
  if (st.accepted && !st.msgs.length && cfg.suggestions?.length) {
    html += `<div class="sg" role="group" aria-label="${esc(L('suggest'))}">` + cfg.suggestions.map(s => `<button type="button" class="sg__b" data-suggest>${esc(s)}</button>`).join('') + '</div>';
  }
  ui.log.innerHTML = html + st.msgs.map(msgHtml).join('');
  scroll(true);
}

function msgHtml(m, i) {
  if (m.role === 'user') return `<div class="m m--me"><span class="sr">${esc(L('you'))}: </span><div class="m__b"><p>${esc(m.content)}</p></div></div>`;
  let body = m.pending && !m.content ? `<p class="m__wait"><span></span><span></span><span></span><span class="sr">${esc(L('thinking'))}</span></p>` : renderMd(m.content, m.sources, (n, t) => `${L('sources')} ${n}: ${t}`);
  if (m.error) body = `<p class="m__err">${esc(m.error)}</p>`;
  if (m.contact) {
    const c = m.contact, a = [];
    if (c.url) a.push(`<a class="btn" href="${esc(c.url)}">${esc(c.label)}</a>`);
    if (c.phone) a.push(`<a class="btn btn--ghost" href="${esc(c.phone.href)}">${esc(L('call'))}: ${esc(c.phone.label)}</a>`);
    if (c.email) a.push(`<a class="btn btn--ghost" href="mailto:${esc(c.email)}">${esc(L('mail'))}</a>`);
    if (a.length) body += `<p class="m__act">${a.join('')}</p>`;
  }
  const cited = !m.pending && !m.contact && (m.cited || []).length ? m.cited : [];
  if (cited.length) body += `<div class="m__src"><p>${esc(L('sources'))}</p><ul>` + cited.map(s => `<li><a href="${esc(s.url)}"><span aria-hidden="true">${esc(s.n)}</span> ${esc(s.title)}</a></li>`).join('') + '</ul></div>';
  return `<div class="m m--bot" data-i="${i}"><span class="sr">${esc(L('bot'))}: </span><div class="m__b">${body}</div></div>`;
}

/** Nur die letzte Antwort neu zeichnen (während des Streams) */
function update(m) {
  const i = st.msgs.indexOf(m);
  const el = ui.log.querySelector(`[data-i="${i}"]`);
  if (el) el.outerHTML = msgHtml(m, i); else ui.log.insertAdjacentHTML('beforeend', msgHtml(m, i));
  scroll();
}

function scroll(force = false) {
  const l = ui.log;
  if (force || l.scrollHeight - l.scrollTop - l.clientHeight < 120) l.scrollTop = l.scrollHeight;
}

async function ask(text) {
  const q = String(text || '').trim();
  if (q.length < 2 || busy || !st.accepted) return;
  const history = st.msgs.filter(m => !m.pending && !m.error && m.content).slice(-6).map(m => ({ role: m.role, content: m.content }));
  st.msgs.push({ role: 'user', content: q });
  const m = { role: 'assistant', content: '', sources: [], pending: true };
  st.msgs.push(m);
  ui.q.value = '';
  ui.q.style.height = 'auto';
  ui.count.textContent = '';
  render();
  ui.live.textContent = L('thinking');
  busy = new AbortController();
  ui.send.setAttribute('aria-label', L('stop'));
  ui.send.classList.add('is-stop');
  const finish = (data) => {
    m.content = data.text || '';
    m.cited = data.sources || [];
    if (data.sources?.length) m.sources = [...(m.sources || []), ...data.sources.filter(s => !(m.sources || []).some(x => x.n === s.n))];
    m.contact = data.unknown ? data.contact : null;
  };
  try {
    await sseFetch(ui.host.dataset.api, {
      body: { q, history, lang: ui.host.dataset.lang || '' }, signal: busy.signal,
      onEvent: (ev, data, status) => {
        if (ev === 'sources') m.sources = data.items || [];
        else if (ev === 'delta') { m.content += data.t || ''; update(m); }
        else if (ev === 'done') finish(data);
        else if (ev === 'error') m.error = data.error || L('error');
        else if (ev === 'json') { if (data.ok) finish(data); else m.error = data.error || L('error') + (status ? ` (${status})` : ''); }
      },
    });
  } catch (e) {
    if (e.name !== 'AbortError') m.error = L('error');
  }
  m.pending = false;
  if (!m.content && !m.error && !m.contact) m.error = L('error');
  busy = null;
  ui.send.setAttribute('aria-label', L('send'));
  ui.send.classList.remove('is-stop');
  update(m);
  save();
  // Vorlesen: fertige Antwort als Ganzes (nicht jedes Textstück)
  const tmp = d.createElement('div');
  tmp.innerHTML = msgHtml(m, 0);
  ui.live.textContent = tmp.textContent.replace(/\s+/g, ' ').trim();
  ui.q.focus();
}
