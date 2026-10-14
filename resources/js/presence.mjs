/**
 * Präsenz der Redaktion – eigenständiges Modul (keine Abhängigkeit vom übrigen Admin-JS; übertragbar, z. B. nach fluid).
 * Gekoppelt nur über data-Attribute und Ereignisse:
 *
 *   [data-presence-here]   Leiste: wer bearbeitet dieses Objekt gerade, wer hat zuletzt gespeichert, Konflikt-Warnung.
 *       data-presence-endpoint  POST-Adresse (Schema siehe Entwicklerhandbuch → Präsenz)
 *       data-presence-type / -id / -label / -url / -csrf   Objekt und CSRF-Token
 *       data-presence-live      Adresse des Live-Streams (Core\Live, /api/live) – schnelle Aktualisierung beim Kommen/Gehen
 *       data-presence-texts     JSON mit den Texten (siehe T unten)
 *     Meldet sich nur im Bearbeiten-Modus (html.cms-editing bzw. data-presence-editing="1"), sonst nur nachsehen (peek).
 *   [data-presence-list]   Übersicht (Seitenbaum, Eintragsliste): Elemente [data-id] bekommen ein Kürzel in [data-presence-slot].
 *       data-presence-endpoint (GET), data-presence-type, data-presence-prefix (z. B. „news:“ für Einträge)
 *   [data-presence-board]  Liste „Gerade aktiv“ (Übersicht/Dashboard): Personen mit Link zum Ort.
 *
 * Konflikt: hat jemand anderes seit dem Öffnen gespeichert, fragt das Modul beim Ereignis „cms:before-save“ nach
 * (detail.waitUntil) – „Trotzdem speichern“ ist erlaubt. Ereignis „cms:presence“ am document: {here, last, conflict, lock}.
 * Bearbeitungssperre (optional, lock.on): fremder Inhaber → Hinweis „nur Ansehen“ (+ „Übernehmen“ für die Administration),
 * frei geworden → „Jetzt bearbeiten“, übernommen während der eigenen Bearbeitung → einmalige Meldung. Der Server setzt durch.
 * Wurzeln: document und offene Schatten-Wurzeln (z. B. die Werkzeugleiste).
 */
const d = document;
const BEAT = 15000, LIST = 30000;

const roots = () => [d, ...[...d.querySelectorAll('*')].filter((x) => x.shadowRoot).map((x) => x.shadowRoot)];
const all = (sel) => roots().flatMap((r) => [...r.querySelectorAll(sel)]);
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const fill = (s, v) => String(s || '').replace(/\{(\w+)\}/g, (_, k) => v[k] ?? '');
const T0 = {
  editing1: '{names} bearbeitet gerade', editingN: '{names} bearbeiten gerade', last: 'Zuletzt gespeichert: {name}, {ago}',
  now: 'gerade eben', min: 'vor {n} Min.', hour: 'vor {n} Std.', day: 'am {date}',
  conflict: '{name} hat inzwischen gespeichert', conflictShort: 'Inzwischen gespeichert', conflictTitle: 'Inzwischen von {name} gespeichert',
  conflictBody: 'Seit Sie diese Seite geöffnet haben, hat {name} gespeichert ({ago}). Wenn Sie jetzt speichern, kann das diese Änderungen überschreiben – der vorige Stand bleibt als Version erhalten.',
  conflictOk: 'Trotzdem speichern', cancel: 'Abbrechen', board: '{name} · {label}',
};
const ago = (t, T) => {
  const s = Math.max(0, Date.now() / 1000 - t);
  if (s < 60) return T.now;
  if (s < 3600) return fill(T.min, { n: Math.round(s / 60) });
  if (s < 86400) return fill(T.hour, { n: Math.round(s / 3600) });
  return fill(T.day, { date: new Date(t * 1000).toLocaleDateString() });
};
const badge = (p) => p.avatar
  ? `<img class="cms-presence__ava" src="${esc(p.avatar)}" alt="" width="24" height="24">`
  : `<span class="cms-presence__ava cms-presence__ava--ini" aria-hidden="true">${esc(p.initials || '?')}</span>`;
const names = (ps) => ps.map((p) => p.name).join(', ');
// Symbole aus dem Icon-Satz der Verwaltung (Sprite an der Werkzeugleiste: data-icons) – keine Emojis
const ico = (n) => { const sp = d.querySelector('.cms-bar-host')?.dataset.icons; return sp ? `<svg class="cms-presence__ico" width="14" height="14" aria-hidden="true" focusable="false"><use href="${esc(sp)}#i-${n}"></use></svg>` : ''; };

// ------------------------------------------------------------------ Live (Core\Live): bei neuer Version sofort nachfragen
function live(url, token, onChange) {
  if (!url || !token || !('EventSource' in window)) return;
  let es = null, v = 0;
  const open = () => {
    es = new EventSource(url + '?t=' + encodeURIComponent(token));
    es.addEventListener('v', (e) => { let j = {}; try { j = JSON.parse(e.data); } catch { return; } const n = +j[token] || 0; if (n > v) { if (v) onChange(); v = n; } });
  };
  d.addEventListener('visibilitychange', () => { if (d.hidden) { es?.close(); es = null; } else if (!es) open(); });
  open();
}

// ------------------------------------------------------------------ Leiste: dieses Objekt
function here(el) {
  if (el._presence) return;
  el._presence = true;
  const T = { ...T0, ...JSON.parse(el.dataset.presenceTexts || '{}') };
  const body = (extra = {}) => JSON.stringify({ type: el.dataset.presenceType, id: el.dataset.presenceId, label: el.dataset.presenceLabel || '', url: el.dataset.presenceUrl || '', ...extra });
  // Gesperrt (Bearbeitungssperre, data-presence-locked): ?edit in der Adresse zählt nicht – der Server zeigt nur die Ansicht
  const editing = () => el.dataset.presenceEditing === '1' || d.documentElement.classList.contains('cms-editing') || (!el.dataset.presenceLocked && new URLSearchParams(location.search).has('edit'));
  let base = null, last = null, conflict = false, liveOn = false, timer = 0, ack = 0, lock = null, wasMine = false, toldTaken = false;
  const ask = (o) => (window.CMSAdmin?.bar?.ask ? window.CMSAdmin.bar.ask(o) : Promise.resolve(window.confirm(o.title + '\n\n' + (o.body || ''))));
  const editUrl = () => { const u = new URL(location.href); u.searchParams.set('edit', '1'); return u.toString(); };

  const render = (hereList) => {
    const others = hereList.filter((p) => !p.self);
    let h = '';
    const lockedHere = lock?.on && lock.by && !editing();
    if (lockedHere) {
      // Bearbeitungssperre: in der Leiste nur Avatar + Schloss (Text und „Übernehmen“ in der Hinweiszeile darunter)
      h = `<span class="cms-presence__avas">${badge(lock.by)}</span><span class="cms-presence__lock">${ico('lock')}<span class="cms-presence__sr">${esc(fill(T.locked, { name: lock.by.name }))}</span></span>`;
    } else if (others.length) {
      h += `<span class="cms-presence__avas">${others.slice(0, 4).map(badge).join('')}</span>`
        + `<span class="cms-presence__t">${esc(fill(others.length === 1 ? T.editing1 : T.editingN, { names: names(others) }))}</span>`;
    } else if (last && !last.self && last.name) {
      // Zuletzt gespeichert: in der Leiste nur ein Uhr-Symbol, Text im Tooltip
      h += `<span class="cms-presence__last">${ico('clock-counter-clockwise')}<span class="cms-presence__t cms-presence__t--muted">${esc(fill(T.last, { name: last.name, ago: ago(last.at, T) }))}</span></span>`;
    }
    if (conflict) h = `<span class="cms-presence__warn" title="${esc(fill(T.conflict, { name: last.name || '?' }))}">${ico('warning')}<span class="cms-presence__sr">${esc(fill(T.conflict, { name: last.name || '?' }))}</span></span>` + h;
    el.innerHTML = h;
    el.hidden = !h;
    el.classList.toggle('is-conflict', conflict);
    el.classList.toggle('is-locked', !!lockedHere);
    if (lockedHere) el.title = fill(T.locked, { name: lock.by.name });
    // Hinweiszeile unter der Leiste: gesperrt (mit „Übernehmen“ für die Administration) bzw. wieder frei
    if (lockedHere) note(`${ico('lock')}<span>${esc(fill(el.dataset.presenceType === 'entry' ? T.lockedNoteEntry : T.lockedNotePage, { name: lock.by.name }))}</span>`
      + (lock.canTake ? `<button type="button" class="cms-lock-note__btn" data-presence-take>${esc(T.take)}</button>` : ''));
    else if (lock?.on && !lock.by && el.dataset.presenceLocked && !editing()) note(`<span>${esc(T.freeNote)}</span><a class="cms-lock-note__btn" href="${esc(editUrl())}">${esc(T.editNow)}</a>`, true);
    else note(null);
    el.title = [conflict ? fill(T.conflict, { name: last.name || '?' }) : '',
      others.length ? fill(others.length === 1 ? T.editing1 : T.editingN, { names: names(others) }) : (last && !last.self && last.name ? fill(T.last, { name: last.name, ago: ago(last.at, T) }) : ''),
      ...others.map((p) => p.name + ' – ' + ago(p.since, T))].filter(Boolean).join('\n');
  };

  const tick = async (peek = !editing()) => {
    clearTimeout(timer);
    try {
      const r = await fetch(el.dataset.presenceEndpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': el.dataset.presenceCsrf || '' }, body: body(peek ? { peek: 1 } : {}) });
      const j = r.ok ? await r.json() : null;
      if (!j || j.on === false) { el.hidden = true; return; }   // abgeschaltet – keine weiteren Meldungen
      last = j.last || null;
      if (last) {
        if (base === null) base = last.at;                       // Stand beim Öffnen
        if (last.self) base = Math.max(base, last.at);           // eigene Speicherung ist kein Konflikt
        conflict = !last.self && last.at > Math.max(base, ack);
      }
      lock = j.lock || null;
      // Sperre verloren (Administration hat übernommen), während hier noch bearbeitet wird: einmal deutlich sagen
      if (lock?.on && editing()) {
        if (lock.mine) wasMine = true;
        else if (lock.by && wasMine && !toldTaken) {
          toldTaken = true;
          ask({ title: fill(T.taken, { name: lock.by.name }), body: T.takenBody, ok: T.ok, cancel: false });
        }
      }
      render(j.here || []);
      d.dispatchEvent(new CustomEvent('cms:presence', { detail: { here: j.here || [], last, conflict, lock } }));
      if (!liveOn && j.live) { liveOn = true; live(el.dataset.presenceLive, j.live, () => tick()); }
    } catch { /* nächster Versuch */ }
    timer = setTimeout(() => tick(), BEAT);
  };

  // Hinweiszeile unter der Werkzeugleiste (im Dokument, Stil wie cms-entry-note in editor.css)
  let noteEl = null;
  const note = (html, free = false) => {
    if (!html) { if (noteEl) noteEl.hidden = true; return; }
    if (!noteEl) {
      noteEl = d.createElement('div');
      noteEl.className = 'cms-entry-note cms-lock-note';
      noteEl.setAttribute('role', 'status');
      noteEl.addEventListener('click', takeClick);
      const host = d.querySelector('.cms-bar-host');
      host ? host.after(noteEl) : d.body.prepend(noteEl);
    }
    noteEl.classList.toggle('cms-lock-note--free', free);
    noteEl.innerHTML = html;
    noteEl.hidden = false;
  };
  // Übernehmen (nur Administration – der Server prüft das Recht): nachfragen, Sperre übernehmen, in den Bearbeiten-Modus
  const takeClick = async (e) => {
    if (!e.target.closest('[data-presence-take]') || !lock?.by) return;
    const by = lock.by.name;
    if (!(await ask({ title: fill(T.takeAsk, { name: by }), body: fill(T.takeBody, { name: by }), ok: T.take, cancel: T.cancel, danger: true }))) return;
    try {
      await fetch(el.dataset.presenceEndpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': el.dataset.presenceCsrf || '' }, body: body({ take: 1 }) });
    } catch { /* Seite neu laden zeigt den Stand */ }
    location.href = editUrl();
  };
  el.addEventListener('click', takeClick);

  // Konflikt: vor dem Speichern nachfragen (keine Sperre)
  d.addEventListener('cms:before-save', (e) => {
    if (!conflict || !last) return;
    const ask = window.CMSAdmin?.bar?.ask;
    const o = { title: fill(T.conflictTitle, { name: last.name }), body: fill(T.conflictBody, { name: last.name, ago: ago(last.at, T) }), ok: T.conflictOk, cancel: T.cancel, danger: true };
    e.detail?.waitUntil?.((ask ? ask(o) : Promise.resolve(window.confirm(o.title + '\n\n' + o.body))).then((ok) => { if (ok) { ack = last.at; conflict = false; } return ok; }));
  });
  // Abmelden beim Verlassen (sendBeacon: Formulardaten mit _csrf)
  addEventListener('pagehide', () => {
    if (!editing() || !navigator.sendBeacon) return;
    const f = new FormData();
    f.append('type', el.dataset.presenceType); f.append('id', el.dataset.presenceId); f.append('leave', '1'); f.append('_csrf', el.dataset.presenceCsrf || '');
    navigator.sendBeacon(el.dataset.presenceEndpoint, f);
  });
  // Moduswechsel (Ansehen ↔ Bearbeiten ohne Neuladen): sofort melden
  d.addEventListener('cms:mode-change', () => tick());
  tick();
  setTimeout(() => tick(), 2000);   // Leiste/Modus sind beim Start evtl. noch nicht gesetzt
}

// ------------------------------------------------------------------ Übersichten: Kürzel an den Einträgen, „Gerade aktiv“
async function lists() {
  const lists = all('[data-presence-list]'), boards = all('[data-presence-board]');
  if (!lists.length && !boards.length) return;
  const src = (lists[0] || boards[0]).dataset.presenceEndpoint;
  let liveOn = false;
  const run = async () => {
    let j = null;
    try { const r = await fetch(src, { credentials: 'same-origin', headers: { Accept: 'application/json' } }); j = r.ok ? await r.json() : null; } catch { /* später */ }
    if (!j || j.on === false) return;
    const items = (j.items || []).filter((p) => !p.self);
    lists.forEach((box) => {
      const T = { ...T0, ...JSON.parse(box.dataset.presenceTexts || '{}') };
      const type = box.dataset.presenceType, pre = box.dataset.presencePrefix || '';
      box.querySelectorAll('.cms-presence-mark').forEach((x) => x.remove());
      const by = new Map();
      items.filter((p) => p.type === type && p.id.startsWith(pre)).forEach((p) => { const k = p.id.slice(pre.length); by.set(k, [...(by.get(k) || []), p]); });
      by.forEach((ps, id) => {
        const it = box.querySelector(`[data-id="${CSS.escape(id)}"]`);
        if (!it) return;
        const slot = it.querySelector('[data-presence-slot]') || it;
        const m = d.createElement('span');
        m.className = 'cms-presence-mark';
        m.title = fill(ps.length === 1 ? T.editing1 : T.editingN, { names: names(ps) });
        m.innerHTML = ps.slice(0, 3).map(badge).join('') + `<span class="cms-presence-sr">${esc(m.title)}</span>`;
        slot.append(m);
      });
    });
    boards.forEach((b) => {
      const T = { ...T0, ...JSON.parse(b.dataset.presenceTexts || '{}') };
      b.innerHTML = items.map((p) => `<li class="cms-presence-board__i">${badge(p)}<span><b>${esc(p.name)}</b> · ${p.url ? `<a href="${esc(p.url)}">${esc(p.label || p.id)}</a>` : esc(p.label || p.id)} <small>${esc(ago(p.since, T))}</small></span></li>`).join('');
      b.closest('[data-presence-board-box]')?.toggleAttribute('hidden', !items.length);
    });
    if (!liveOn && j.live) { liveOn = true; live((lists[0] || boards[0]).dataset.presenceLive, j.live, run); }
  };
  run();
  setInterval(run, LIST);
}

const start = () => { all('[data-presence-here]').forEach(here); lists(); };
if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', start); else start();
