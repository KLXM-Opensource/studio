/*
 * Anfragen als Posteingang (app/Admin/views/requests/index.php): Liste links, gewählte Anfrage rechts – wie Feedback/Mail.
 * - Auswahl ohne Neuladen (alle Anfragen der Seite stehen im HTML; entschlüsselte Inhalte bleiben so erhalten), Adresse ?id=…
 * - Liste als Listbox: ↑/↓, Pos1/Ende, Eingabe öffnet; „/“ springt in die Suche; Suche filtert Liste (Nummer, Datum, Inhalte)
 * - Status, Zuweisen, Löschen per fetch (JSON) – kein Neuladen, damit die Ansicht entsperrt bleibt
 * Ohne JavaScript: Links (?id=) und normale Formulare.
 */
import { toast } from './_toast.js';

export function initRequests(root = document) {
  const box = root.querySelector('[data-rq]');
  if (!box) return;
  const list = box.querySelector('[data-rq-list]');
  const live = box.querySelector('[data-rq-live]');
  const txt = k => box.dataset['rqT' + k] || '';
  const rows = () => [...box.querySelectorAll('.rq-row')];
  const visible = () => rows().filter(r => !r.hidden);
  const say = msg => { if (live) { live.textContent = ''; setTimeout(() => { live.textContent = msg; }, 30); } };

  const select = (row, { focus = false, push = true } = {}) => {
    if (!row) return;
    const id = row.dataset.id;
    rows().forEach(r => {
      const on = r === row;
      r.classList.toggle('is-sel', on);
      const a = r.querySelector('.rq-row__a');
      if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
    });
    box.querySelectorAll('[data-rq-read]').forEach(p => { p.hidden = p.dataset.rqRead !== id; });
    box.classList.add('is-reading');
    box.querySelectorAll('[data-rq-selid]').forEach(i => { i.value = id; });
    if (push) {
      const u = new URL(location.href);
      u.searchParams.set('id', id);
      history.replaceState(null, '', u);
    }
    row.scrollIntoView({ block: 'nearest' });
    if (focus) box.querySelector(`[data-rq-read="${id}"] h2`)?.focus();
  };

  // Klick in der Liste: Auswahl statt Navigation
  list?.addEventListener('click', e => {
    const a = e.target.closest('.rq-row__a');
    if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    select(a.closest('.rq-row'), { focus: matchMedia('(max-width: 900px)').matches });
  });
  // Tastatur in der Liste
  list?.addEventListener('keydown', e => {
    const vis = visible();
    if (!vis.length) return;
    const cur = vis.findIndex(r => r.classList.contains('is-sel'));
    let next = null;
    if (e.key === 'ArrowDown') next = vis[Math.min(vis.length - 1, cur + 1)];
    else if (e.key === 'ArrowUp') next = vis[Math.max(0, cur - 1)];
    else if (e.key === 'Home') next = vis[0];
    else if (e.key === 'End') next = vis[vis.length - 1];
    else if (e.key === 'Enter' && cur >= 0) { e.preventDefault(); select(vis[cur], { focus: true }); return; }
    if (!next) return;
    e.preventDefault();
    select(next);
    next.querySelector('.rq-row__a').focus();
  });
  // „‹ Zurück“ (schmal): zurück zur Liste
  box.addEventListener('click', e => {
    const back = e.target.closest('[data-rq-back]');
    if (!back) return;
    e.preventDefault();
    box.classList.remove('is-reading');
    if (back.hasAttribute('data-rq-key')) box.querySelector('#secret')?.focus();
    else box.querySelector('.rq-row.is-sel .rq-row__a')?.focus();
  });

  // Suche
  const q = box.querySelector('[data-rq-q]');
  const nomatch = box.querySelector('[data-rq-nomatch]');
  const count = box.querySelector('[data-rq-count]');
  const countText = count?.textContent || '';
  q?.addEventListener('input', () => {
    const v = q.value.trim().toLowerCase();
    let n = 0;
    rows().forEach(r => { const hit = !v || (r.dataset.search || '').includes(v); r.hidden = !hit; if (hit) n++; });
    if (nomatch) { nomatch.hidden = n > 0 || !v; nomatch.textContent = txt('Nomatch').replace('{q}', q.value.trim()); }
    if (count) count.textContent = v ? (n === 1 ? txt('Count1') : txt('Count').replace('{n}', n)) : countText;
  });
  document.addEventListener('keydown', e => {
    if (e.key !== '/' || e.target.closest('input, textarea, select, [contenteditable]')) return;
    e.preventDefault();
    q?.focus();
  });

  // Aktionen per fetch
  box.addEventListener('submit', async e => {
    const form = e.target.closest('form[data-rq-act]');
    if (!form || e.defaultPrevented) return;
    if (form.dataset.confirm && form.dataset.confirmed !== '1' && !form.dataset.rqOk) return;   // Bestätigung übernimmt admin.js
    e.preventDefault();
    const read = form.closest('[data-rq-read]');
    const id = read?.dataset.rqRead;
    const row = id ? box.querySelector(`#rq-row-${id}`) : null;
    form.classList.add('is-busy');
    try {
      const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const j = await res.json().catch(() => ({}));
      if (!res.ok || !j.ok) throw new Error(j.message || j.error || txt('Error'));
      const kind = form.dataset.rqAct;
      if (kind === 'status') {
        [row, read].forEach(el => el?.querySelectorAll('[data-rq-pill]').forEach(p => { p.textContent = j.label; p.className = 'rq-pill rq-pill--' + (j.tone || ''); p.dataset.rqPill = ''; }));
        row?.classList.toggle('is-unread', j.status === 'neu');
        read?.querySelectorAll('form[data-rq-status]').forEach(f => { f.hidden = f.dataset.rqStatus === j.status; });
      } else if (kind === 'assign') {
        const who = j.assignee || '';
        row?.querySelectorAll('[data-rq-who]').forEach(w => { w.textContent = who; });
        row?.querySelectorAll('[data-rq-whowrap]').forEach(w => { w.hidden = !who; });
        read?.querySelectorAll('[data-rq-who]').forEach(w => { w.textContent = who || txt('Nobody'); });
      } else if (kind === 'delete') {
        const vis = visible();
        const i = vis.indexOf(row);
        row?.remove();
        read?.remove();
        const next = visible()[Math.min(i, visible().length - 1)];
        if (next) select(next); else box.classList.remove('is-reading');
      }
      toast(j.message, 'ok');
      say(j.message);
    } catch (err) {
      toast(err.message || txt('Error'), 'error');
    } finally {
      form.classList.remove('is-busy');
    }
  });
  // Zuweisen: sofort beim Wechsel der Auswahl
  box.querySelectorAll('form[data-rq-act="assign"]').forEach(f => {
    f.querySelector('[data-rq-nojs]')?.setAttribute('hidden', '');
    f.querySelector('select')?.addEventListener('change', () => f.requestSubmit());
  });

  // Start: gewählte Anfrage sichtbar, Liste fokussierbar
  if (list) list.tabIndex = -1;
  const sel = box.querySelector('.rq-row.is-sel');
  sel?.scrollIntoView({ block: 'nearest' });
}
