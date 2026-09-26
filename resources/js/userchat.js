/*
 * Chat zwischen Benutzern der Verwaltung (Core\Chat) – eigenes Bundle, nur geladen, wenn die Person den Chat nutzen darf
 * (Chat::head() in views/layout.php). Konfiguration: <script type="application/json" id="uc-config">.
 *
 * - Schublade von rechts (schmale Bildschirme: Vollbild), Vollbild-Seite /admin/chat ([data-chat-page])
 * - Liste der Kanäle, Direktnachrichten und Netzwerk-Unterhaltungen mit Zählern; ältere Nachrichten nachladen
 * - Eingabe: Enter sendet, Umschalt+Enter neue Zeile, Bilder einfügen/auswählen, @Erwähnungen, Verweise (Suche, Favoriten, diese Seite)
 * - Live: Server-Sent Events (EventSource, Server schließt nach ≤ 25 s, Neuverbindung mit Last-Event-ID) – auf dem
 *   Entwicklungsserver Abfrage alle 3 s. Chat geschlossen: nur der Zähler, Abfrage alle 60 s.
 * - Zähler in der Seitenleiste und im Fenstertitel; Hinweise im Browser nur nach ausdrücklicher Zustimmung (Glocke)
 */
import { t } from './_i18n.js';
import ICON_MAP from '../../public/assets/icons/icons-map.json';

const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const store = {
  get(k) { try { return localStorage.getItem(k); } catch { return null; } },
  set(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch { /* privat/gesperrt */ } },
};

// Symbole aus dem Sprite (wie _icons.js, ohne dessen Abhängigkeiten)
// Je Symbol das kleine Sprite seines Themas (Index icons-map.json, Core\Icons) – sonst das vollständige icons.svg
const sprite = d.documentElement.dataset.icons || '/assets/icons/icons.svg';
const spriteOf = name => (ICON_MAP[name] ? sprite.replace(/icons\.svg(?=\?|$)/, ICON_MAP[name] + '.svg') : sprite);
const ico = (name, cls = '') => `<svg class="ico${cls ? ' ' + esc(cls) : ''}" aria-hidden="true" focusable="false" width="1em" height="1em" fill="currentColor"><use href="${esc(spriteOf(name))}#i-${esc(name)}"/></svg>`;

let cfg;
try { cfg = JSON.parse($('#uc-config')?.textContent || 'null'); } catch { cfg = null; }
if (cfg) init();

function init() {
  const csrf = () => $('#adm-csrf')?.value || '';
  const live = $('#adm-live');
  const announce = msg => { if (!live) return; live.textContent = ''; setTimeout(() => { live.textContent = msg; }, 60); };
  const pageHost = $('[data-chat-page]');
  const baseTitle = d.title;
  const mqSmall = matchMedia('(max-width:700px)');
  const fmtTime = new Intl.DateTimeFormat(d.documentElement.lang || 'de', { hour: '2-digit', minute: '2-digit' });
  const fmtDay = new Intl.DateTimeFormat(d.documentElement.lang || 'de', { weekday: 'long', day: 'numeric', month: 'long' });

  const S = {
    rooms: [], active: 0, msgs: new Map(), more: new Map(), lastEventId: 0, open: false, loaded: false,
    unread: 0, mentions: 0, es: null, poll: null, badge: null, readTimer: null, stateTimer: null,
    transport: cfg.transport, pending: [], editing: 0, me: cfg.me,
  };

  // ------------------------------------------------------------------ Netz
  async function api(path, { method = 'GET', body = null, form = null } = {}) {
    const opt = { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() } };
    if (form) opt.body = form;
    else if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
    const r = await fetch(cfg.base + path, opt);
    const data = await r.json().catch(() => ({ ok: false, error: t('Unerwartete Antwort vom Server.') }));
    if (!r.ok && data.ok !== false) data.ok = false;
    data.status = r.status;
    return data;
  }

  // ------------------------------------------------------------------ Zähler (Seitenleiste, Titel)
  const navLink = $('a[data-nav="chat"], a[data-ico="chat"]');
  function setTotals(unread, mentions) {
    S.unread = Math.max(0, unread | 0); S.mentions = Math.max(0, mentions | 0);
    const badge = $('[data-chat-badge]');
    if (badge) {
      badge.hidden = S.unread === 0;
      badge.classList.toggle('uc-count--at', S.mentions > 0);
      badge.innerHTML = `${S.mentions ? '@ ' : ''}${S.unread > 99 ? '99+' : S.unread}<span class="sr-only"> ${esc(t('ungelesen'))}${S.mentions ? ', ' + esc(t('{n} Erwähnungen', { n: S.mentions })) : ''}</span>`;
    }
    d.title = (S.unread ? `(${S.unread > 99 ? '99+' : S.unread}) ` : '') + baseTitle;
    const tb = $('.uc-launch__count');
    if (tb) { tb.hidden = S.unread === 0; tb.textContent = S.unread > 99 ? '99+' : String(S.unread); }
  }
  function recount() {
    let u = 0, m = 0;
    S.rooms.forEach(r => { u += r.unread; m += r.mentions; });
    setTotals(u, m);
  }

  // ------------------------------------------------------------------ Hinweise im Browser (nur nach Zustimmung)
  const notifyOn = () => store.get('uc-notify') === '1' && 'Notification' in window && Notification.permission === 'granted';
  function notify(title, body, roomId) {
    if (!notifyOn() || (d.visibilityState === 'visible' && S.open && S.active === roomId)) return;
    try {
      const n = new Notification(title, { body, tag: 'uc-' + roomId, silent: false });
      n.onclick = () => { window.focus(); openPanel(roomId); n.close(); };
    } catch { /* nicht unterstützt */ }
  }

  // ------------------------------------------------------------------ Aufbau
  const root = d.createElement('section');
  root.className = 'uc' + (pageHost ? ' uc--page' : ' uc--drawer');
  root.id = 'uc';
  root.setAttribute('aria-labelledby', 'uc-title');
  if (!pageHost) { root.setAttribute('role', 'dialog'); root.hidden = true; }
  root.innerHTML = `
    <header class="uc-head">
      <button type="button" class="uc-iconbtn uc-back" data-uc-back hidden aria-label="${esc(t('Zurück zur Liste'))}">${ico('arrow-left')}</button>
      <h2 class="uc-title" id="uc-title">${esc(t('Chat'))}</h2>
      <button type="button" class="uc-iconbtn" data-uc-bell aria-pressed="false" aria-label="${esc(t('Hinweise im Browser'))}" title="${esc(t('Hinweise im Browser'))}">${ico('bell')}</button>
      ${cfg.settings ? `<a class="uc-iconbtn" href="${esc(cfg.settings)}" aria-label="${esc(t('Chat-Einstellungen'))}" title="${esc(t('Chat-Einstellungen'))}">${ico('gear-six')}</a>` : ''}
      ${pageHost ? '' : `<a class="uc-iconbtn" href="${esc(cfg.page)}" data-uc-full aria-label="${esc(t('Als ganze Seite öffnen'))}" title="${esc(t('Als ganze Seite öffnen'))}">${ico('arrow-square-out')}</a>
      <button type="button" class="uc-iconbtn" data-uc-close aria-label="${esc(t('Chat schließen'))}" title="${esc(t('Chat schließen'))} (Esc)">${ico('x')}</button>`}
    </header>
    <div class="uc-body">
      <nav class="uc-rooms" aria-label="${esc(t('Unterhaltungen'))}">
        <div class="uc-rooms__list" data-uc-rooms><p class="uc-empty">${esc(t('Lädt …'))}</p></div>
        <div class="uc-new">
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost uc-new__btn" data-uc-new aria-expanded="false" aria-controls="uc-newbox">${ico('plus')} ${esc(t('Neue Direktnachricht'))}</button>
          <div class="uc-newbox" id="uc-newbox" hidden>
            ${cfg.site && cfg.network ? `<div class="uc-seg" role="radiogroup" aria-label="${esc(t('Bereich'))}">
              <label><input type="radio" name="uc-scope" value="site" checked> ${esc(t('Diese Website'))}</label>
              <label><input type="radio" name="uc-scope" value="network"> ${esc(t('Netzwerk'))}</label></div>` : ''}
            <label class="uc-sr-label" for="uc-people-q">${esc(t('Person suchen'))}</label>
            <input id="uc-people-q" type="search" autocomplete="off" placeholder="${esc(t('Name oder E-Mail'))}" role="combobox" aria-expanded="false" aria-controls="uc-people" aria-autocomplete="list">
            <ul class="uc-people" id="uc-people" role="listbox" aria-label="${esc(t('Personen'))}"></ul>
          </div>
        </div>
      </nav>
      <section class="uc-conv" aria-labelledby="uc-conv-title" data-uc-conv hidden>
        <header class="uc-conv__head"><h3 id="uc-conv-title" class="uc-conv__title"></h3><p class="uc-conv__topic" data-uc-topic></p></header>
        <div class="uc-log" data-uc-log role="log" aria-live="polite" aria-relevant="additions" aria-label="${esc(t('Nachrichten'))}" tabindex="0">
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost uc-older" data-uc-older hidden>${esc(t('Ältere Nachrichten laden'))}</button>
          <ol class="uc-msgs" data-uc-msgs></ol>
        </div>
        <p class="uc-jump" data-uc-jump hidden><button type="button" class="adm-btn adm-btn--small">${esc(t('Neue Nachrichten'))} ↓</button></p>
        <form class="uc-compose" data-uc-form novalidate>
          <ul class="uc-attach" data-uc-attach aria-label="${esc(t('Angehängte Bilder'))}"></ul>
          <p class="uc-error" data-uc-error role="alert" hidden></p>
          <div class="uc-compose__row">
            <label class="uc-sr-label" for="uc-text" data-uc-text-label>${esc(t('Nachricht'))}</label>
            <textarea id="uc-text" rows="1" maxlength="4000" data-uc-text aria-describedby="uc-hint" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="uc-mention"></textarea>
            <ul class="uc-mention" id="uc-mention" role="listbox" aria-label="${esc(t('Personen erwähnen'))}" hidden></ul>
            <div class="uc-tools">
              <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" multiple hidden data-uc-file id="uc-file">
              <button type="button" class="uc-iconbtn" data-uc-pick aria-label="${esc(t('Bild anhängen'))}" title="${esc(t('Bild anhängen'))}">${ico('image')}</button>
              <button type="button" class="uc-iconbtn" data-uc-link aria-expanded="false" aria-controls="uc-linkbox" aria-label="${esc(t('Verweis einfügen'))}" title="${esc(t('Verweis einfügen'))}">${ico('link')}</button>
              <button type="submit" class="uc-send" aria-label="${esc(t('Senden'))}" title="${esc(t('Senden'))} (Enter)">${ico('paper-plane-tilt')}</button>
            </div>
          </div>
          <div class="uc-linkbox" id="uc-linkbox" hidden>
            <label class="uc-sr-label" for="uc-link-q">${esc(t('Seite, Eintrag oder Datei suchen'))}</label>
            <input id="uc-link-q" type="search" autocomplete="off" placeholder="${esc(t('Seite, Eintrag oder Datei suchen'))}" role="combobox" aria-expanded="true" aria-controls="uc-links" aria-autocomplete="list">
            <ul class="uc-links" id="uc-links" role="listbox" aria-label="${esc(t('Verweise'))}"></ul>
          </div>
          <p class="uc-hint" id="uc-hint">${esc(t('Enter sendet, Umschalt+Enter neue Zeile. @ erwähnt Personen, Bilder mit Strg/⌘+V einfügen.'))}</p>
        </form>
      </section>
      <div class="uc-placeholder" data-uc-placeholder><p>${esc(t('Wählen Sie links eine Unterhaltung.'))}</p></div>
    </div>`;
  (pageHost || d.body).appendChild(root);

  const el = {
    rooms: $('[data-uc-rooms]', root), conv: $('[data-uc-conv]', root), title: $('#uc-conv-title', root), topic: $('[data-uc-topic]', root),
    log: $('[data-uc-log]', root), msgs: $('[data-uc-msgs]', root), older: $('[data-uc-older]', root), form: $('[data-uc-form]', root),
    text: $('[data-uc-text]', root), attach: $('[data-uc-attach]', root), error: $('[data-uc-error]', root), file: $('[data-uc-file]', root),
    mention: $('#uc-mention', root), linkbox: $('#uc-linkbox', root), linkq: $('#uc-link-q', root), links: $('#uc-links', root),
    back: $('[data-uc-back]', root), bell: $('[data-uc-bell]', root), placeholder: $('[data-uc-placeholder]', root), jump: $('[data-uc-jump]', root),
    newBtn: $('[data-uc-new]', root), newBox: $('#uc-newbox', root), peopleQ: $('#uc-people-q', root), people: $('#uc-people', root),
  };

  // ------------------------------------------------------------------ Schublade öffnen/schließen
  let returnFocus = null, inerted = [];
  function setModal(on) {
    inerted.forEach(x => { x.inert = false; });
    inerted = [];
    if (on && mqSmall.matches && !pageHost) {
      inerted = [...d.body.children].filter(x => x !== root && x.tagName !== 'SCRIPT' && !x.inert);
      inerted.forEach(x => { x.inert = true; });
      root.setAttribute('aria-modal', 'true');
      d.documentElement.classList.add('adm-lock');
    } else {
      root.removeAttribute('aria-modal');
      d.documentElement.classList.remove('adm-lock');
    }
  }
  async function openPanel(roomId = 0) {
    if (!pageHost) {
      if (!S.open) returnFocus = d.activeElement;
      d.dispatchEvent(new CustomEvent('adm:drawer', { detail: 'close' }));
      root.hidden = false;
      d.body.classList.add('uc-is-open');
      navLink?.setAttribute('aria-expanded', 'true');
    }
    S.open = true;
    setModal(true);
    stopBadge();
    await loadState();
    startLive();
    if (roomId) await selectRoom(roomId);
    else if (S.active) { await selectRoom(S.active); }
    else (($('.uc-room[aria-current]', root) || $('.uc-room', root) || el.newBtn))?.focus();
    if (!pageHost) store.set('uc-open', '1');
  }
  function closePanel() {
    if (pageHost || !S.open) return;
    S.open = false;
    root.hidden = true;
    d.body.classList.remove('uc-is-open');
    navLink?.setAttribute('aria-expanded', 'false');
    setModal(false);
    stopLive();
    startBadge();
    store.set('uc-open', null);
    (returnFocus && d.contains(returnFocus) ? returnFocus : navLink)?.focus({ preventScroll: true });
  }
  mqSmall.addEventListener?.('change', () => { if (S.open) setModal(true); root.classList.toggle('is-small', mqSmall.matches); });
  root.classList.toggle('is-small', mqSmall.matches);

  if (navLink && !pageHost) {
    navLink.setAttribute('aria-controls', 'uc');
    navLink.setAttribute('aria-expanded', 'false');
    navLink.addEventListener('click', e => {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
      e.preventDefault();
      S.open ? closePanel() : openPanel();
    });
  }
  $('[data-uc-close]', root)?.addEventListener('click', closePanel);
  root.addEventListener('keydown', e => {
    if (e.key !== 'Escape' || e.defaultPrevented) return;
    if (!el.mention.hidden) { hideMention(); e.preventDefault(); return; }
    if (!el.linkbox.hidden) { toggleLinkbox(false); el.text.focus(); e.preventDefault(); return; }
    if (!el.newBox.hidden) { toggleNew(false); e.preventDefault(); return; }
    if (S.editing) return;
    if (mqSmall.matches && root.classList.contains('is-room-open')) { showList(); e.preventDefault(); return; }
    if (!pageHost) { closePanel(); e.preventDefault(); }
  });
  el.back.addEventListener('click', showList);
  function showList() {
    root.classList.remove('is-room-open');
    el.back.hidden = true;
    $(`.uc-room[data-room="${S.active}"]`, root)?.focus();
  }

  // ------------------------------------------------------------------ Hinweise (Glocke)
  const syncBell = () => {
    const on = notifyOn();
    el.bell.setAttribute('aria-pressed', String(on));
    el.bell.classList.toggle('is-on', on);
    el.bell.hidden = !('Notification' in window);
  };
  syncBell();
  el.bell.addEventListener('click', async () => {
    if (notifyOn()) { store.set('uc-notify', null); syncBell(); announce(t('Hinweise im Browser ausgeschaltet.')); return; }
    if (Notification.permission === 'denied') { announce(t('Hinweise sind im Browser gesperrt – bitte in den Website-Einstellungen des Browsers erlauben.')); showError(t('Hinweise sind im Browser gesperrt – bitte in den Website-Einstellungen des Browsers erlauben.')); return; }
    const p = await Notification.requestPermission();
    if (p === 'granted') { store.set('uc-notify', '1'); announce(t('Hinweise im Browser eingeschaltet.')); }
    syncBell();
  });

  // ------------------------------------------------------------------ Räume
  async function loadState() {
    const res = await api('/state');
    if (!res.ok) { el.rooms.innerHTML = `<p class="uc-empty">${esc(res.error || t('Der Chat ist gerade nicht erreichbar.'))}</p>`; return false; }
    S.rooms = res.rooms;
    S.transport = res.transport;
    if (!S.loaded || !S.lastEventId) S.lastEventId = res.lastEventId;
    S.loaded = true;
    renderRooms();
    recount();
    return true;
  }
  function roomById(id) { return S.rooms.find(r => r.id === id); }
  function renderRooms() {
    const groups = [
      [t('Kanäle'), S.rooms.filter(r => r.kind === 'channel' && r.scope === 'site')],
      [t('Direktnachrichten'), S.rooms.filter(r => r.kind === 'dm' && r.scope === 'site')],
      [t('Netzwerk'), S.rooms.filter(r => r.scope === 'network')],
    ].filter(([, list]) => list.length);
    if (!groups.length) {
      el.rooms.innerHTML = `<p class="uc-empty">${esc(t('Noch keine Unterhaltungen. Starten Sie eine Direktnachricht.'))}</p>`;
      return;
    }
    el.rooms.innerHTML = groups.map(([label, list], gi) => `
      <h3 class="uc-rooms__h" id="uc-rg${gi}">${esc(label)}</h3>
      <ul class="uc-rooms__ul" aria-labelledby="uc-rg${gi}">${list.sort((a, b) => a.kind === 'dm' ? (b.lastId - a.lastId) : a.name.localeCompare(b.name)).map(roomItem).join('')}</ul>`).join('');
  }
  function roomItem(r) {
    const icon = r.kind === 'channel' ? ico('hash', 'uc-room__ico') : `<span class="uc-avatar uc-avatar--s" aria-hidden="true">${esc(initials(r.name))}</span>`;
    const name = r.kind === 'channel' ? r.name.replace(/^#/, '') : r.name;
    const count = r.unread ? `<span class="uc-room__count${r.mentions ? ' is-at' : ''}">${r.mentions ? '@ ' : ''}${r.unread > 99 ? '99+' : r.unread}<span class="sr-only"> ${esc(t('ungelesen'))}${r.mentions ? ', ' + esc(t('{n} Erwähnungen', { n: r.mentions })) : ''}</span></span>` : '';
    return `<li><button type="button" class="uc-room${r.unread ? ' is-unread' : ''}" data-room="${r.id}"${S.active === r.id ? ' aria-current="true"' : ''}>
      ${icon}<span class="uc-room__name">${r.kind === 'channel' ? '<span class="sr-only">' + esc(t('Kanal')) + ' </span>' : ''}${esc(name)}${r.site ? ` <small>${esc(r.site)}</small>` : ''}</span>${count}</button></li>`;
  }
  el.rooms.addEventListener('click', e => {
    const b = e.target.closest('.uc-room');
    if (b) selectRoom(+b.dataset.room, true);
  });
  // Pfeiltasten in der Liste
  el.rooms.addEventListener('keydown', e => {
    if (!['ArrowDown', 'ArrowUp'].includes(e.key)) return;
    const all = $$('.uc-room', el.rooms), i = all.indexOf(d.activeElement);
    if (i < 0) return;
    e.preventDefault();
    all[(i + (e.key === 'ArrowDown' ? 1 : all.length - 1)) % all.length].focus();
  });

  async function selectRoom(id, focusComposer = false) {
    const r = roomById(id);
    if (!r) return;
    S.active = id;
    $$('.uc-room', root).forEach(b => b.toggleAttribute('aria-current', +b.dataset.room === id));
    $$('.uc-room[aria-current]', root).forEach(b => b.setAttribute('aria-current', 'true'));
    el.conv.hidden = false;
    el.placeholder.hidden = true;
    root.classList.add('is-room-open');
    el.back.hidden = !mqSmall.matches;
    el.title.textContent = r.kind === 'channel' ? r.name : r.name + (r.site ? ' · ' + r.site : '');
    el.topic.textContent = r.topic || (r.kind === 'dm' ? t('Direktnachricht') : '');
    $('[data-uc-text-label]', root).textContent = t('Nachricht an {name}', { name: r.name });
    el.text.placeholder = t('Nachricht an {name}', { name: r.name });
    hideError();
    clearPending();
    if (!S.msgs.has(id)) {
      el.msgs.innerHTML = `<li class="uc-empty">${esc(t('Lädt …'))}</li>`;
      const res = await api(`/rooms/${id}/messages`);
      if (S.active !== id) return;
      if (!res.ok) { el.msgs.innerHTML = `<li class="uc-empty">${esc(res.error || t('Nachrichten konnten nicht geladen werden.'))}</li>`; return; }
      S.msgs.set(id, res.messages);
      S.more.set(id, res.more);
    }
    renderMessages(true);
    markRead();
    if (focusComposer || !mqSmall.matches) el.text.focus({ preventScroll: true });
    if (pageHost) history.replaceState(null, '', cfg.page + '?raum=' + id);
  }

  // ------------------------------------------------------------------ Nachrichten
  const initials = n => String(n || '?').split(/[\s.@_-]+/).filter(Boolean).slice(0, 2).map(w => w[0].toUpperCase()).join('') || '?';
  const dayKey = iso => (iso || '').slice(0, 10);
  function dayLabel(iso) {
    const dt = new Date(iso), today = new Date(), y = new Date(Date.now() - 864e5);
    if (dt.toDateString() === today.toDateString()) return t('Heute');
    if (dt.toDateString() === y.toDateString()) return t('Gestern');
    return fmtDay.format(dt);
  }
  function msgHtml(m, prev) {
    const sameAuthor = prev && !prev.deleted && prev.author.key === m.author.key && dayKey(prev.at) === dayKey(m.at) && (new Date(m.at) - new Date(prev.at)) < 5 * 60e3;
    const sep = !prev || dayKey(prev.at) !== dayKey(m.at) ? `<li class="uc-day" role="presentation"><span>${esc(dayLabel(m.at))}</span></li>` : '';
    const time = fmtTime.format(new Date(m.at));
    const head = sameAuthor ? '' : `<p class="uc-msg__head"><span class="uc-avatar" aria-hidden="true">${esc(initials(m.author.name))}</span><strong>${esc(m.author.name)}</strong>${m.author.site ? ` <small class="uc-msg__site">${esc(m.author.site)}</small>` : ''}</p>`;
    if (m.deleted) {
      return `${sep}<li class="uc-msg is-deleted${sameAuthor ? ' is-cont' : ''}" data-id="${m.id}">${head}<p class="uc-msg__body uc-msg__gone"><span class="sr-only">${esc(m.author.name)}: </span>${esc(t('Nachricht gelöscht'))} <time datetime="${esc(m.at)}">${esc(time)}</time></p></li>`;
    }
    const files = m.files.length ? `<ul class="uc-files">${m.files.map(f => `<li><a href="${esc(f.url)}" target="_blank" rel="noopener"><img src="${esc(f.url)}" alt="${esc(t('Bild: {name}', { name: f.name }))}" loading="lazy"${f.w ? ` width="${f.w}" height="${f.h}"` : ''}><span class="sr-only"> (${esc(t('öffnet in neuem Tab'))})</span></a></li>`).join('')}</ul>` : '';
    const reacts = m.reactions.length ? `<ul class="uc-reacts" aria-label="${esc(t('Reaktionen'))}">${m.reactions.map(r => `<li><button type="button" class="uc-react${r.mine ? ' is-mine' : ''}" data-react="${esc(r.emoji)}" aria-pressed="${r.mine}" title="${esc(r.names.join(', '))}"><span aria-hidden="true">${esc(r.emoji)}</span> ${r.count}<span class="sr-only"> ${esc(t('Reaktion {emoji} von {names}', { emoji: r.emoji, names: r.names.join(', ') }))}</span></button></li>`).join('')}</ul>` : '';
    return `${sep}<li class="uc-msg${m.mine ? ' is-mine' : ''}${m.mentionsMe ? ' is-mention' : ''}${sameAuthor ? ' is-cont' : ''}" data-id="${m.id}">
      ${head}<div class="uc-msg__body">${sameAuthor ? `<span class="sr-only">${esc(m.author.name)}: </span>` : ''}${m.html}</div>${files}
      <p class="uc-msg__meta"><time datetime="${esc(m.at)}">${esc(time)}</time>${m.edited ? ` · ${esc(t('bearbeitet'))}` : ''}</p>${reacts}
      <div class="uc-msg__tools"><button type="button" class="uc-iconbtn uc-iconbtn--s" data-uc-menu aria-expanded="false" aria-haspopup="true" aria-label="${esc(t('Aktionen für Nachricht von {name}, {time}', { name: m.author.name, time }))}">${ico('dots-three')}</button></div>
    </li>`;
  }
  function renderMessages(toBottom = false) {
    const list = S.msgs.get(S.active) || [];
    el.older.hidden = !S.more.get(S.active);
    el.msgs.innerHTML = list.length ? list.map((m, i) => msgHtml(m, list[i - 1])).join('') : `<li class="uc-empty">${esc(t('Noch keine Nachrichten. Schreiben Sie die erste!'))}</li>`;
    if (toBottom) scrollBottom();
  }
  const atBottom = () => el.log.scrollHeight - el.log.scrollTop - el.log.clientHeight < 60;
  const scrollBottom = () => { el.log.scrollTop = el.log.scrollHeight; el.jump.hidden = true; };
  el.log.addEventListener('scroll', () => { if (atBottom()) { el.jump.hidden = true; markRead(); } }, { passive: true });
  el.jump.addEventListener('click', () => { scrollBottom(); el.text.focus(); });

  el.older.addEventListener('click', async () => {
    const list = S.msgs.get(S.active) || [];
    if (!list.length) return;
    el.older.disabled = true;
    const id = S.active, h = el.log.scrollHeight;
    const res = await api(`/rooms/${id}/messages?before=${list[0].id}`);
    el.older.disabled = false;
    if (!res.ok || S.active !== id) return;
    S.msgs.set(id, [...res.messages, ...list]);
    S.more.set(id, res.more);
    renderMessages();
    el.log.scrollTop = el.log.scrollHeight - h;
    announce(t('{n} ältere Nachrichten geladen.', { n: res.messages.length }));
    ($(`.uc-msg[data-id="${list[0].id}"]`, el.msgs)?.previousElementSibling?.querySelector('[data-uc-menu]') || el.older).focus({ preventScroll: true });
  });

  function upsert(m) {
    const list = S.msgs.get(m.room);
    if (!list) return false;
    const i = list.findIndex(x => x.id === m.id);
    if (i >= 0) list[i] = m; else { list.push(m); list.sort((a, b) => a.id - b.id); }
    return i < 0;
  }
  function refreshOne(m) {
    if (m.room !== S.active) return;
    const li = $(`.uc-msg[data-id="${m.id}"]`, el.msgs);
    if (!li) { renderMessages(atBottom()); return; }
    const list = S.msgs.get(S.active), i = list.findIndex(x => x.id === m.id);
    const hadFocus = li.contains(d.activeElement);
    const tmp = d.createElement('ol');
    tmp.innerHTML = msgHtml(m, list[i - 1]);
    li.replaceWith(tmp.lastElementChild);
    if (hadFocus) $(`.uc-msg[data-id="${m.id}"] [data-uc-menu]`, el.msgs)?.focus();
  }

  let readBusy = false;
  function markRead() {
    clearTimeout(S.readTimer);
    S.readTimer = setTimeout(async () => {
      const r = roomById(S.active), list = S.msgs.get(S.active);
      if (!r || !list?.length || !S.open || d.visibilityState !== 'visible' || !atBottom() || readBusy) return;
      const last = list[list.length - 1].id;
      if (!r.unread && r.readUpTo >= last) return;
      readBusy = true;
      const res = await api(`/rooms/${r.id}/read`, { method: 'POST', body: { last_id: last } });
      readBusy = false;
      if (!res.ok) return;
      r.unread = 0; r.mentions = 0; r.readUpTo = last;
      const btn = $(`.uc-room[data-room="${r.id}"]`, root);
      if (btn) { btn.classList.remove('is-unread'); $('.uc-room__count', btn)?.remove(); }
      setTotals(res.unread, res.mentions);
    }, 400);
  }
  d.addEventListener('visibilitychange', () => {
    if (d.visibilityState === 'visible') { markRead(); if (!S.open) pollBadge(); }
  });

  // ------------------------------------------------------------------ Aktionen an Nachrichten (Menü)
  let menu = null;
  function closeMenu(focus = true) {
    if (!menu) return;
    const btn = menu.btn;
    menu.box.remove();
    btn.setAttribute('aria-expanded', 'false');
    menu = null;
    if (focus) btn.focus();
  }
  el.msgs.addEventListener('click', e => {
    const menuBtn = e.target.closest('[data-uc-menu]');
    const reactBtn = e.target.closest('[data-react]');
    const li = e.target.closest('.uc-msg');
    if (!li) return;
    const m = (S.msgs.get(S.active) || []).find(x => x.id === +li.dataset.id);
    if (!m) return;
    if (reactBtn && !reactBtn.closest('.uc-menu')) { react(m, reactBtn.dataset.react); return; }
    if (menuBtn) {
      if (menu && menu.btn === menuBtn) { closeMenu(); return; }
      closeMenu(false);
      const box = d.createElement('div');
      box.className = 'uc-menu';
      box.setAttribute('role', 'menu');
      box.setAttribute('aria-label', t('Aktionen'));
      box.innerHTML = `<div class="uc-menu__reacts" role="group" aria-label="${esc(t('Reagieren'))}">${cfg.reactions.map(r => `<button type="button" role="menuitem" data-menu-react="${esc(r)}" aria-label="${esc(t('Reagieren mit {emoji}', { emoji: r }))}">${esc(r)}</button>`).join('')}</div>`
        + (m.mine ? `<button type="button" role="menuitem" data-menu="edit">${ico('pencil-simple')} ${esc(t('Bearbeiten'))}</button>` : '')
        + (m.mine || (cfg.canManage && roomById(S.active)?.kind === 'channel') ? `<button type="button" role="menuitem" data-menu="delete" class="is-danger">${ico('trash')} ${esc(t('Löschen'))}</button>` : '');
      menuBtn.parentElement.appendChild(box);
      menuBtn.setAttribute('aria-expanded', 'true');
      menu = { box, btn: menuBtn, m };
      $('[role=menuitem]', box)?.focus();
      return;
    }
    const act = e.target.closest('[data-menu-react],[data-menu]');
    if (act && menu) {
      const mm = menu.m;
      closeMenu(false);
      if (act.dataset.menuReact) react(mm, act.dataset.menuReact);
      else if (act.dataset.menu === 'edit') startEdit(mm);
      else if (act.dataset.menu === 'delete') removeMsg(mm);
    }
  });
  el.msgs.addEventListener('keydown', e => {
    if (!menu || !menu.box.contains(e.target)) return;
    const items = $$('[role=menuitem]', menu.box), i = items.indexOf(e.target);
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closeMenu(); }
    else if (['ArrowDown', 'ArrowRight'].includes(e.key)) { e.preventDefault(); items[(i + 1) % items.length].focus(); }
    else if (['ArrowUp', 'ArrowLeft'].includes(e.key)) { e.preventDefault(); items[(i - 1 + items.length) % items.length].focus(); }
    else if (e.key === 'Tab') closeMenu(false);
  });
  d.addEventListener('click', e => { if (menu && !menu.box.contains(e.target) && e.target.closest('[data-uc-menu]') !== menu.btn) closeMenu(false); });

  async function react(m, emoji) {
    const res = await api(`/messages/${m.id}/react`, { method: 'POST', body: { emoji } });
    if (!res.ok) { showError(res.error); return; }
    upsert(res.message); refreshOne(res.message);
  }
  async function removeMsg(m) {
    if (!confirm(t('Nachricht löschen? Das lässt sich nicht rückgängig machen.'))) { $(`.uc-msg[data-id="${m.id}"] [data-uc-menu]`, el.msgs)?.focus(); return; }
    const res = await api(`/messages/${m.id}/delete`, { method: 'POST', body: {} });
    if (!res.ok) { showError(res.error); return; }
    upsert(res.message); refreshOne(res.message);
    announce(t('Nachricht gelöscht.'));
    el.text.focus();
  }
  function startEdit(m) {
    const li = $(`.uc-msg[data-id="${m.id}"]`, el.msgs);
    if (!li) return;
    S.editing = m.id;
    const body = $('.uc-msg__body', li);
    const f = d.createElement('form');
    f.className = 'uc-edit';
    f.innerHTML = `<label class="uc-sr-label" for="uc-edit-${m.id}">${esc(t('Nachricht bearbeiten'))}</label>
      <textarea id="uc-edit-${m.id}" rows="2" maxlength="4000">${esc(m.body || '')}</textarea>
      <div class="adm-row"><button class="adm-btn adm-btn--small adm-btn--primary" type="submit">${esc(t('Speichern'))}</button>
      <button class="adm-btn adm-btn--small adm-btn--ghost" type="button" data-cancel>${esc(t('Abbrechen'))}</button>
      <span class="uc-hint">${esc(t('Enter speichert, Esc bricht ab.'))}</span></div>`;
    body.hidden = true;
    body.after(f);
    const ta = $('textarea', f);
    grow(ta); ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length);
    const done = (focusMenu = true) => { S.editing = 0; f.remove(); body.hidden = false; if (focusMenu) $('[data-uc-menu]', li)?.focus(); };
    ta.addEventListener('input', () => grow(ta));
    ta.addEventListener('keydown', e => {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); done(); }
      else if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); f.requestSubmit(); }
    });
    $('[data-cancel]', f).addEventListener('click', () => done());
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const res = await api(`/messages/${m.id}/edit`, { method: 'POST', body: { body: ta.value } });
      if (!res.ok) { showError(res.error); return; }
      done(false);
      upsert(res.message); refreshOne(res.message);
      announce(t('Nachricht gespeichert.'));
      $(`.uc-msg[data-id="${m.id}"] [data-uc-menu]`, el.msgs)?.focus();
    });
  }

  // ------------------------------------------------------------------ Eingabe
  function grow(ta) { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight + 2, 200) + 'px'; }
  const showError = msg => { el.error.textContent = msg || t('Das hat nicht geklappt.'); el.error.hidden = false; };
  const hideError = () => { el.error.hidden = true; el.error.textContent = ''; };
  el.text.addEventListener('input', () => { grow(el.text); hideError(); mentionLookup(); });
  el.text.addEventListener('keydown', e => {
    if (!el.mention.hidden && ['ArrowDown', 'ArrowUp', 'Enter', 'Tab'].includes(e.key)) { mentionKey(e); return; }
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); el.form.requestSubmit(); }
  });
  el.form.addEventListener('submit', async e => {
    e.preventDefault();
    const id = S.active, body = el.text.value;
    if (!id || (!body.trim() && !S.pending.length)) return;
    if (S.pending.some(p => p.busy)) { showError(t('Bitte warten, bis die Bilder hochgeladen sind.')); return; }
    const sendBtn = $('.uc-send', el.form);
    sendBtn.disabled = true;
    const res = await api(`/rooms/${id}/messages`, { method: 'POST', body: { body, files: S.pending.map(p => p.id) } });
    sendBtn.disabled = false;
    if (!res.ok) { showError(res.status === 403 ? t('Keine Berechtigung.') : res.error); return; }
    el.text.value = ''; grow(el.text); clearPending(); hideMention();
    if (upsert(res.message) && S.active === id) {
      const list = S.msgs.get(id);
      if (list.length === 1) renderMessages(true);
      else { el.msgs.insertAdjacentHTML('beforeend', msgHtml(res.message, list[list.length - 2])); scrollBottom(); }
    }
    const r = roomById(id);
    if (r) { r.lastId = res.message.id; r.readUpTo = res.message.id; }
    el.text.focus();
  });

  // Bilder: auswählen, einfügen, ziehen
  $('[data-uc-pick]', root).addEventListener('click', () => el.file.click());
  el.file.addEventListener('change', () => { [...el.file.files].forEach(uploadFile); el.file.value = ''; });
  el.text.addEventListener('paste', e => {
    const files = [...(e.clipboardData?.files || [])].filter(f => f.type.startsWith('image/'));
    if (!files.length) return;
    e.preventDefault();
    files.forEach(uploadFile);
  });
  el.form.addEventListener('dragover', e => { if ([...(e.dataTransfer?.types || [])].includes('Files')) { e.preventDefault(); el.form.classList.add('is-drop'); } });
  el.form.addEventListener('dragleave', () => el.form.classList.remove('is-drop'));
  el.form.addEventListener('drop', e => {
    el.form.classList.remove('is-drop');
    const files = [...(e.dataTransfer?.files || [])].filter(f => f.type.startsWith('image/'));
    if (!files.length) return;
    e.preventDefault();
    files.forEach(uploadFile);
  });
  async function uploadFile(file) {
    if (!S.active) return;
    if (S.pending.length >= cfg.maxFiles) { showError(t('Höchstens {n} Bilder je Nachricht.', { n: cfg.maxFiles })); return; }
    if (!/^image\/(png|jpeg|webp|gif)$/.test(file.type)) { showError(t('Nur Bilder (JPEG, PNG, WebP, GIF).')); return; }
    if (file.size > cfg.maxBytes) { showError(t('„{name}“ ist größer als {mb} MB.', { name: file.name || 'Bild', mb: Math.round(cfg.maxBytes / 1048576) })); return; }
    const p = { id: 0, name: file.name || t('Bild'), url: URL.createObjectURL(file), busy: true };
    S.pending.push(p);
    renderPending();
    const fd = new FormData();
    fd.append('file', file, file.name || 'bild.png');
    const res = await api(`/rooms/${S.active}/upload`, { method: 'POST', form: fd });
    p.busy = false;
    if (!res.ok) { S.pending = S.pending.filter(x => x !== p); URL.revokeObjectURL(p.url); showError(res.error); renderPending(); return; }
    p.id = res.file.id;
    renderPending();
    announce(t('Bild angehängt: {name}', { name: p.name }));
  }
  function renderPending() {
    el.attach.innerHTML = S.pending.map((p, i) => `<li${p.busy ? ' class="is-busy"' : ''}><img src="${esc(p.url)}" alt=""><span>${esc(p.name)}${p.busy ? ' – ' + esc(t('lädt …')) : ''}</span>
      <button type="button" class="uc-iconbtn uc-iconbtn--s" data-unattach="${i}" aria-label="${esc(t('Bild entfernen: {name}', { name: p.name }))}">${ico('x')}</button></li>`).join('');
  }
  el.attach.addEventListener('click', e => {
    const b = e.target.closest('[data-unattach]');
    if (!b) return;
    const [p] = S.pending.splice(+b.dataset.unattach, 1);
    if (p) URL.revokeObjectURL(p.url);
    renderPending();
    el.text.focus();
  });
  function clearPending() { S.pending.forEach(p => URL.revokeObjectURL(p.url)); S.pending = []; renderPending(); }

  // ------------------------------------------------------------------ @Erwähnungen
  let mentionState = null, peopleCache = new Map();
  async function roomPeople(id) {
    if (!peopleCache.has(id)) {
      const res = await api(`/people?room=${id}`);
      peopleCache.set(id, res.ok ? res.people : []);
    }
    return peopleCache.get(id);
  }
  async function mentionLookup() {
    const pos = el.text.selectionStart, before = el.text.value.slice(0, pos);
    const m = before.match(/(^|\s)@([\p{L}\p{N}. -]{0,30})$/u);
    if (!m || m[2].includes('  ')) { hideMention(); return; }
    const q = m[2].toLowerCase(), start = pos - m[2].length - 1;
    const people = (await roomPeople(S.active)).filter(p => p.name.toLowerCase().includes(q)).slice(0, 8);
    if (!people.length) { hideMention(); return; }
    mentionState = { start, pos, people, i: 0 };
    el.mention.innerHTML = people.map((p, i) => `<li role="option" id="uc-mo-${i}"${i === 0 ? ' aria-selected="true"' : ''} data-i="${i}"><span class="uc-avatar uc-avatar--s" aria-hidden="true">${esc(initials(p.name))}</span>${esc(p.name)}${p.site ? ` <small>${esc(p.site)}</small>` : ''}</li>`).join('');
    el.mention.hidden = false;
    el.text.setAttribute('aria-expanded', 'true');
    el.text.setAttribute('aria-activedescendant', 'uc-mo-0');
  }
  function hideMention() {
    mentionState = null;
    el.mention.hidden = true;
    el.text.setAttribute('aria-expanded', 'false');
    el.text.removeAttribute('aria-activedescendant');
  }
  function mentionKey(e) {
    const s = mentionState;
    if (!s) return;
    e.preventDefault();
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      s.i = (s.i + (e.key === 'ArrowDown' ? 1 : s.people.length - 1)) % s.people.length;
      $$('[role=option]', el.mention).forEach((o, i) => o.setAttribute('aria-selected', String(i === s.i)));
      el.text.setAttribute('aria-activedescendant', 'uc-mo-' + s.i);
      return;
    }
    pickMention(s.i);
  }
  function pickMention(i) {
    const s = mentionState, p = s?.people[i];
    if (!p) return;
    const v = el.text.value, ins = '@' + p.name + ' ';
    el.text.value = v.slice(0, s.start) + ins + v.slice(s.pos);
    const c = s.start + ins.length;
    el.text.setSelectionRange(c, c);
    hideMention();
    el.text.focus();
  }
  el.mention.addEventListener('mousedown', e => { const o = e.target.closest('[role=option]'); if (o) { e.preventDefault(); pickMention(+o.dataset.i); } });

  // ------------------------------------------------------------------ Verweise (Suche der Verwaltung, Favoriten, diese Seite)
  const searchUrl = $('.adm-brand[data-search-endpoint]')?.dataset.searchEndpoint || '';
  const linkBtn = $('[data-uc-link]', root);
  let linkItems = [], linkI = 0, linkTimer, linkCtrl;
  function toggleLinkbox(on) {
    el.linkbox.hidden = !on;
    linkBtn.setAttribute('aria-expanded', String(on));
    if (on) { el.linkq.value = ''; linkSearch(); el.linkq.focus(); }
  }
  linkBtn.addEventListener('click', () => toggleLinkbox(el.linkbox.hidden));
  function favs() { try { return JSON.parse($('#adm-fav-data')?.textContent || '[]'); } catch { return []; } }
  async function linkSearch() {
    const q = el.linkq.value.trim();
    const here = location.pathname.includes('/admin/chat') ? [] : [{ title: t('Diese Seite') + ': ' + (d.querySelector('.adm-main h1')?.textContent?.trim() || baseTitle), url: location.pathname + location.search + location.hash, icon: 'arrow-right' }];
    let items = [...here, ...favs().filter(f => !q || f.title.toLowerCase().includes(q.toLowerCase())).map(f => ({ title: f.title, sub: t('Favorit'), url: f.href || f.url, icon: 'star' }))];
    if (q.length >= 2 && searchUrl) {
      linkCtrl?.abort(); linkCtrl = new AbortController();
      try {
        const r = await fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: linkCtrl.signal });
        const data = await r.json();
        (data.groups || []).forEach(g => (g.items || []).forEach(it => {
          // Seiten: Verwaltungsadresse (alt) statt Bearbeiten auf der Website; nur Adressen der Verwaltung werden zu Chips
          const url = it.alt && /\/admin\//.test(it.alt) ? it.alt : it.url;
          if (url && /\/admin\/(?!api\/)/.test(url) && !/[?&]q=/.test(url)) items.push({ title: it.title, sub: it.sub || g.label, url, icon: 'link' });
        }));
      } catch { /* abgebrochen */ }
    }
    const seen = new Set();
    linkItems = items.filter(it => it.url && !seen.has(it.url) && seen.add(it.url)).slice(0, 12);
    linkI = 0;
    el.links.innerHTML = linkItems.length ? linkItems.map((it, i) => `<li role="option" id="uc-lo-${i}" data-i="${i}"${i === 0 ? ' aria-selected="true"' : ''}>${ico(it.icon || 'link')}<span>${esc(it.title)}${it.sub ? ` <small>${esc(it.sub)}</small>` : ''}</span></li>`).join('')
      : `<li class="uc-empty" role="presentation">${esc(t('Keine Treffer.'))}</li>`;
    if (linkItems.length) el.linkq.setAttribute('aria-activedescendant', 'uc-lo-0'); else el.linkq.removeAttribute('aria-activedescendant');
  }
  el.linkq.addEventListener('input', () => { clearTimeout(linkTimer); linkTimer = setTimeout(linkSearch, 250); });
  el.linkq.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!linkItems.length) return;
      linkI = (linkI + (e.key === 'ArrowDown' ? 1 : linkItems.length - 1)) % linkItems.length;
      $$('[role=option]', el.links).forEach((o, i) => o.setAttribute('aria-selected', String(i === linkI)));
      el.linkq.setAttribute('aria-activedescendant', 'uc-lo-' + linkI);
    } else if (e.key === 'Enter') { e.preventDefault(); pickLink(linkI); }
  });
  el.links.addEventListener('mousedown', e => { const o = e.target.closest('[role=option]'); if (o) { e.preventDefault(); pickLink(+o.dataset.i); } });
  function pickLink(i) {
    const it = linkItems[i];
    if (!it) return;
    let path = it.url;
    try { const u = new URL(it.url, location.href); if (u.origin === location.origin) path = u.pathname + u.search + u.hash; } catch { /* bleibt */ }
    const ta = el.text, s = ta.selectionStart, v = ta.value;
    const pre = s > 0 && !/\s$/.test(v.slice(0, s)) ? ' ' : '';
    ta.value = v.slice(0, s) + pre + path + ' ' + v.slice(ta.selectionEnd);
    const c = s + pre.length + path.length + 1;
    toggleLinkbox(false);
    ta.focus(); ta.setSelectionRange(c, c); grow(ta);
    announce(t('Verweis eingefügt: {title}', { title: it.title }));
  }

  // ------------------------------------------------------------------ Neue Direktnachricht
  let peopleList = [], peopleI = 0, peopleTimer;
  function toggleNew(on) {
    el.newBox.hidden = !on;
    el.newBtn.setAttribute('aria-expanded', String(on));
    if (on) { el.peopleQ.value = ''; searchPeople(); el.peopleQ.focus(); } else el.newBtn.focus();
  }
  el.newBtn.addEventListener('click', () => toggleNew(el.newBox.hidden));
  const scope = () => $('input[name=uc-scope]:checked', root)?.value || (cfg.site ? 'site' : 'network');
  $$('input[name=uc-scope]', root).forEach(r => r.addEventListener('change', searchPeople));
  async function searchPeople() {
    const res = await api(`/people?scope=${scope()}&q=${encodeURIComponent(el.peopleQ.value.trim())}`);
    peopleList = res.ok ? res.people : [];
    peopleI = 0;
    el.people.innerHTML = peopleList.length ? peopleList.map((p, i) => `<li role="option" id="uc-po-${i}" data-i="${i}"${i === 0 ? ' aria-selected="true"' : ''}><span class="uc-avatar uc-avatar--s" aria-hidden="true">${esc(initials(p.name))}</span><span>${esc(p.name)}${p.site ? ` <small>${esc(p.site)}</small>` : ''}</span></li>`).join('')
      : `<li class="uc-empty" role="presentation">${esc(t('Niemand gefunden.'))}</li>`;
    el.peopleQ.setAttribute('aria-expanded', String(!!peopleList.length));
    if (peopleList.length) el.peopleQ.setAttribute('aria-activedescendant', 'uc-po-0'); else el.peopleQ.removeAttribute('aria-activedescendant');
  }
  el.peopleQ.addEventListener('input', () => { clearTimeout(peopleTimer); peopleTimer = setTimeout(searchPeople, 200); });
  el.peopleQ.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!peopleList.length) return;
      peopleI = (peopleI + (e.key === 'ArrowDown' ? 1 : peopleList.length - 1)) % peopleList.length;
      $$('[role=option]', el.people).forEach((o, i) => o.setAttribute('aria-selected', String(i === peopleI)));
      el.peopleQ.setAttribute('aria-activedescendant', 'uc-po-' + peopleI);
    } else if (e.key === 'Enter') { e.preventDefault(); startDm(peopleI); }
  });
  el.people.addEventListener('mousedown', e => { const o = e.target.closest('[role=option]'); if (o) { e.preventDefault(); startDm(+o.dataset.i); } });
  async function startDm(i) {
    const p = peopleList[i];
    if (!p) return;
    const res = await api('/dm', { method: 'POST', body: { user: p.key, scope: scope() } });
    if (!res.ok) { announce(res.error || t('Das hat nicht geklappt.')); return; }
    el.newBox.hidden = true; el.newBtn.setAttribute('aria-expanded', 'false');
    await loadState();
    await selectRoom(res.room, true);
  }

  // ------------------------------------------------------------------ Live: Ereignisse
  function handle(ev) {
    if (!ev || !ev.type) return;
    if (ev.id && ev.id <= S.lastEventId) return;   // schon verarbeitet (z. B. Neuverbindung ohne Last-Event-ID)
    if (ev.id) S.lastEventId = ev.id;
    if (ev.type === 'room') { clearTimeout(S.stateTimer); S.stateTimer = setTimeout(async () => { await loadState(); if (S.active && !roomById(S.active)) { S.active = 0; el.conv.hidden = true; el.placeholder.hidden = false; } }, 300); return; }
    const m = ev.message;
    if (!m) return;
    const r = roomById(m.room);
    if (!r) { clearTimeout(S.stateTimer); S.stateTimer = setTimeout(loadState, 300); return; }
    const isNew = ev.type === 'message';
    const known = upsert(m);
    if (isNew && known === false && !S.msgs.has(m.room)) { /* Raum nicht geladen */ }
    if (m.room === S.active && S.open) {
      if (isNew) {
        const stick = atBottom();
        const list = S.msgs.get(S.active);
        if (!$(`.uc-msg[data-id="${m.id}"]`, el.msgs)) {
          if (list.length === 1) renderMessages(true);
          else el.msgs.insertAdjacentHTML('beforeend', msgHtml(m, list[list.length - 2]));
        }
        if (stick) scrollBottom(); else if (!m.mine) el.jump.hidden = false;
      } else refreshOne(m);
    }
    if (isNew) {
      r.lastId = Math.max(r.lastId || 0, m.id);
      if (!m.mine) {
        const visible = S.open && S.active === m.room && d.visibilityState === 'visible' && atBottom();
        if (visible) markRead();
        else {
          r.unread++; if (m.mentionsMe) r.mentions++;
          recount();
          if (S.open) renderRooms();
          if (S.open && S.active !== m.room) announce(t('Neue Nachricht von {name} in {room}', { name: m.author.name, room: r.name }));
          const txt = (new DOMParser().parseFromString(m.html, 'text/html').body.textContent || '').trim().slice(0, 140) || (m.files.length ? t('Bild') : '');
          notify(m.mentionsMe ? t('{name} hat Sie erwähnt', { name: m.author.name }) : `${m.author.name} · ${r.name}`, txt, m.room);
        }
      }
    }
  }

  function startLive() {
    stopLive();
    if (S.transport === 'sse' && 'EventSource' in window) {
      const es = new EventSource(cfg.base + '/stream?last_id=' + S.lastEventId, { withCredentials: true });
      S.es = es;
      let opened = false;
      es.addEventListener('hello', () => { opened = true; });
      es.addEventListener('chat', e => { try { handle(JSON.parse(e.data)); } catch { /* ungültig */ } });
      es.addEventListener('skip', e => { try { S.lastEventId = Math.max(S.lastEventId, JSON.parse(e.data).lastEventId | 0); } catch { /* */ } });
      es.onerror = () => {
        // Server schließt planmäßig nach ≤ 25 s → EventSource verbindet sich selbst neu (retry 2000, Last-Event-ID).
        // Geschlossen (z. B. 403/409 oder nie geöffnet): auf Abfragen umschalten
        if (es.readyState === EventSource.CLOSED || !opened) { es.close(); if (S.es === es) { S.es = null; S.transport = 'poll'; startPoll(); } }
      };
    } else startPoll();
  }
  function startPoll() {
    clearTimeout(S.poll);
    const tick = async () => {
      if (!S.open) return;
      if (d.visibilityState === 'visible' || S.transport === 'poll') {
        const res = await api('/poll?last_id=' + S.lastEventId).catch(() => null);
        if (res?.ok) { res.events.forEach(handle); S.lastEventId = Math.max(S.lastEventId, res.lastEventId | 0); }
      }
      S.poll = setTimeout(tick, d.visibilityState === 'visible' ? cfg.pollOpen : cfg.pollOpen * 5);
    };
    S.poll = setTimeout(tick, cfg.pollOpen);
  }
  function stopLive() {
    S.es?.close(); S.es = null;
    clearTimeout(S.poll); S.poll = null;
  }

  // ------------------------------------------------------------------ Zähler bei geschlossenem Chat (langsam)
  let lastUnread = null;
  async function pollBadge() {
    const res = await api('/unread').catch(() => null);
    if (!res?.ok) return;
    if (lastUnread !== null && res.unread > lastUnread) notify(t('Chat'), t('{n} ungelesene Nachrichten', { n: res.unread }), 0);
    lastUnread = res.unread;
    setTotals(res.unread, res.mentions);
  }
  function startBadge() {
    stopBadge();
    S.badge = setInterval(() => { if (d.visibilityState === 'visible' || notifyOn()) pollBadge(); }, cfg.pollClosed);
  }
  function stopBadge() { clearInterval(S.badge); S.badge = null; }

  // ------------------------------------------------------------------ Start
  const b0 = $('[data-chat-badge]');
  if (b0 && !b0.hidden) lastUnread = parseInt(b0.textContent.replace(/\D+/g, ''), 10) || 0;
  if (pageHost) openPanel(+pageHost.dataset.room || 0);
  else if (store.get('uc-open') === '1' && !mqSmall.matches) openPanel();
  else startBadge();
  window.addEventListener('pagehide', stopLive);
}
