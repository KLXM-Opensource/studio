/*
 * Favoriten je Benutzer (Core\Favorites, Endpunkte /admin/api/favorites…)
 * - Stern neben der H1 jeder Verwaltungsseite bzw. in der Werkzeugleiste ganzseitiger Ansichten ([data-fav-slot], z. B. Mediathek;
 *   ohne beides: kleiner Stern oben rechts) – merkt Adresse inkl. #Hash
 *   (Reiter der Einstellungen, geöffnete Datei der Mediathek #m123, Kapitel im Handbuch)
 * - Abschnitt „Favoriten“ in der Seitenleiste (views/layout.php): aufklappbar (je Benutzer in localStorage gemerkt),
 *   „Bearbeiten“: umbenennen, entfernen, ziehen oder Alt+↑/↓ zum Sortieren
 * Hash-Änderungen ohne hashchange (history.replaceState) melden Module mit document.dispatchEvent(new Event('adm:location')).
 */
import { t } from './_i18n.js';
import { ico } from './_icons.js';

const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const STAR = '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.2l2.35 4.9 5.35.72-3.9 3.73.96 5.33L10 14.3l-4.76 2.58.96-5.33-3.9-3.73 5.35-.72z"/></svg>';

const store = {
  get(k) { try { return localStorage.getItem(k); } catch { return null; } },
  set(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch { /* privat/gesperrt */ } },
};

export function initFavorites(csrf) {
  const box = $('#adm-fav');
  if (!box) return;
  const ds = box.dataset, uid = ds.favUser, max = +ds.favMax || 30;
  const list = $('[data-fav-list]', box), empty = $('[data-fav-empty]', box), body = $('#adm-fav-body'), toggle = $('.adm-fav__toggle', box);
  const live = $('#adm-live');
  let favs = [];
  try { favs = JSON.parse($('#adm-fav-data')?.textContent || '[]'); } catch { favs = []; }
  let editing = false, busy = false, star = null;

  const announce = msg => { if (!live) return; live.textContent = ''; setTimeout(() => { live.textContent = msg; }, 40); };
  const hash = () => { let h = ''; try { h = decodeURIComponent(location.hash.slice(1)); } catch { /* ungültig */ } return /^[\w\-.:/]{1,80}$/u.test(h) ? h : ''; };
  const here = () => ds.favHere ? ds.favHere + (hash() ? '#' + hash() : '') : '';

  async function api(path, body) {
    if (busy) return null;
    busy = true;
    try {
      const r = await fetch(ds.favEndpoint + path, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify(body) });
      const res = await r.json().catch(() => ({ ok: false }));
      if (Array.isArray(res.favorites)) favs = res.favorites;
      if (!res.ok) announce(res.error || t('Speichern fehlgeschlagen.'));
      render();
      return res;
    } catch {
      announce(t('Speichern fehlgeschlagen.'));
      return null;
    } finally { busy = false; }
  }

  // ---------------------------------------------------------- Titel für den aktuellen Ort (Seite + Reiter/Datei/Kapitel)
  const clean = el => {
    const c = el.cloneNode(true);
    $$('.adm-dot,.no,.dot,.adm-sr,[aria-hidden=true]', c).forEach(x => x.remove());
    return c.textContent.replace(/\s+/g, ' ').trim().replace(/\.$/, '');
  };
  function hashLabel(h) {
    if (!h) return '';
    const tab = $$('[role=tab][data-tab]').find(x => x.dataset.tab === h);
    if (tab) return clean(tab);
    const m = h.match(/^m(\d+)$/);
    if (m) {
      const n = $('#fx-i-' + m[1] + ' .fx-name');
      return n ? n.textContent.trim() : t('Datei {id}', { id: m[1] });
    }
    const el = d.getElementById(h);
    if (!el) return '';
    const hd = el.matches('h1,h2,h3,h4') ? el : $('h2,h3', el);
    return hd ? clean(hd) : '';
  }
  function title() {
    const h1 = $$('#main h1')[0];
    let base = ds.favTitle || (h1 ? clean(h1) : '') || d.title.split(' · ')[0];
    const part = hashLabel(hash());
    if (part && part !== base) base += ' · ' + part;
    return base.slice(0, 80);
  }

  // ---------------------------------------------------------- Stern
  function placeStar() {
    if (!ds.favHere) return;
    star = d.createElement('button');
    star.type = 'button';
    star.className = 'adm-star';
    star.innerHTML = STAR;
    // Platz für den Stern: 1. [data-fav-slot] – Werkzeugleiste ganzseitiger App-Ansichten (Mediathek, Erweiterungen wie ein
    // Feedback-Eingang), Wert = zusätzliche Klassen für den Knopf, Kind [data-fav-before] = davor einsetzen; 2. neben der H1;
    // 3. sonst klein oben rechts – erscheint der Slot erst später (per Skript aufgebaut), wandert der Stern dorthin
    const toSlot = slot => {
      star.className = 'adm-star adm-star--slot' + (slot.dataset.favSlot ? ' ' + slot.dataset.favSlot : '');
      const before = $('[data-fav-before]', slot);
      before && before.parentNode === slot ? slot.insertBefore(star, before) : slot.append(star);
    };
    const slot = $('#main [data-fav-slot]');
    const h1 = slot ? null : $$('#main h1').find(h => !h.classList.contains('adm-sr') && h.getClientRects().length);
    if (slot) toSlot(slot);
    else if (h1) {
      const row = d.createElement('div');
      row.className = 'adm-titlerow';
      h1.before(row);
      row.append(h1, star);
    } else {
      star.classList.add('adm-star--float');
      const main = $('#main');
      main?.prepend(star);
      if (main && 'MutationObserver' in window) {
        const mo = new MutationObserver(() => { const s = $('[data-fav-slot]', main); if (s) { mo.disconnect(); toSlot(s); } });
        mo.observe(main, { childList: true, subtree: true });
        setTimeout(() => mo.disconnect(), 10000);
      }
    }
    star.addEventListener('click', async () => {
      const url = here(), on = favs.some(f => f.url === url);
      if (!on && favs.length >= max) { announce(t('Höchstens {n} Favoriten möglich – bitte zuerst einen entfernen.', { n: max })); return; }
      const res = on ? await api('/remove', { url }) : await api('', { url, title: title(), icon: ds.favIcon });
      if (res?.ok) announce(on ? t('Aus Favoriten entfernt') : t('Zu Favoriten hinzugefügt'));
      if (!on && res?.ok && box.classList.contains('is-collapsed')) setOpen(true);
    });
  }
  function syncStar() {
    if (!star) return;
    const on = favs.some(f => f.url === here());
    star.setAttribute('aria-pressed', String(on));
    const label = on ? t('Aus Favoriten entfernen') : t('Zu Favoriten hinzufügen');
    star.setAttribute('aria-label', label);
    star.title = label;
  }

  // ---------------------------------------------------------- Liste
  function current() {
    const url = here();
    return favs.find(f => f.url === url) || (hash() ? favs.find(f => f.url === ds.favHere) : null);
  }
  function itemLink(f, cur) {
    const a = d.createElement('a');
    a.href = f.href;
    // Symbol aus dem Sprite (ico: vom Server aufgelöst), sonst altes Zeichen
    if (f.ico) a.insertAdjacentHTML('beforeend', ico(f.ico, 'adm-fav__ico'));
    else if (f.icon) {
      const g = d.createElement('span');
      g.className = 'ico ico--glyph adm-fav__ico'; g.setAttribute('aria-hidden', 'true'); g.textContent = f.icon;
      a.append(g);
    } else a.insertAdjacentHTML('beforeend', ico('star', 'adm-fav__ico'));
    const s = d.createElement('span');
    s.textContent = f.title;
    a.append(s);
    a.title = f.title;
    if (f === cur) a.setAttribute('aria-current', 'page');
    return a;
  }
  function editRow(f, i) {
    const li = d.createElement('li');
    li.className = 'adm-fav__row';
    li.dataset.url = f.url;
    const grip = d.createElement('span');
    grip.className = 'adm-fav__grip'; grip.draggable = true; grip.setAttribute('aria-hidden', 'true'); grip.title = t('Ziehen zum Sortieren');
    const inp = d.createElement('input');
    inp.className = 'adm-fav__name'; inp.value = f.title; inp.maxLength = 80; inp.autocomplete = 'off';
    inp.setAttribute('aria-label', t('Name von Favorit {n} von {total}', { n: i + 1, total: favs.length }));
    inp.setAttribute('aria-describedby', 'adm-fav-kbd');
    const rm = d.createElement('button');
    rm.type = 'button'; rm.className = 'adm-fav__rm'; rm.textContent = '×';
    rm.setAttribute('aria-label', t('„{name}“ entfernen', { name: f.title })); rm.title = t('Entfernen');
    li.append(grip, inp, rm);
    return li;
  }
  function render() {
    const cur = current();
    list.replaceChildren(...favs.map((f, i) => {
      if (editing) return editRow(f, i);
      const li = d.createElement('li');
      li.dataset.url = f.url;
      li.append(itemLink(f, cur));
      return li;
    }));
    box.classList.toggle('is-empty', !favs.length);
    box.classList.toggle('is-editing', editing);
    const hintKey = 'adm.fav.hint.' + uid;
    const showHint = !favs.length && (editing || !store.get(hintKey));
    empty.hidden = !showHint;
    if (showHint && !editing) store.set(hintKey, '1');
    let kbd = $('#adm-fav-kbd');
    if (editing && favs.length > 1 && !kbd) {
      kbd = d.createElement('p');
      kbd.id = 'adm-fav-kbd'; kbd.className = 'adm-fav__kbd';
      kbd.textContent = t('Ziehen oder Alt+↑/↓ zum Sortieren · Enter speichert den Namen');
      list.after(kbd);
    } else if (kbd && !(editing && favs.length > 1)) kbd.remove();
    syncStar();
  }

  // ---------------------------------------------------------- Aufklappen (je Benutzer gemerkt)
  const openKey = 'adm.fav.collapsed.' + uid;
  function setOpen(open, remember = true) {
    toggle.setAttribute('aria-expanded', String(open));
    body.hidden = !open;
    box.classList.toggle('is-collapsed', !open);
    if (remember) store.set(openKey, open ? null : '1');
  }
  toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
  if (store.get(openKey) === '1') setOpen(false, false);

  // ---------------------------------------------------------- Bearbeiten
  const link = $('[data-fav-edit]', box);
  const editBtn = d.createElement('button');
  editBtn.type = 'button'; editBtn.className = link.className; editBtn.setAttribute('aria-pressed', 'false');
  editBtn.setAttribute('aria-controls', 'adm-fav-body');
  editBtn.textContent = t('Bearbeiten');
  link.replaceWith(editBtn);
  function setEditing(on, focus = true) {
    editing = on;
    editBtn.setAttribute('aria-pressed', String(on));
    editBtn.textContent = on ? t('Fertig') : t('Bearbeiten');
    if (on) setOpen(true, false);
    render();
    if (on && focus) { const inp = $('.adm-fav__name', list); inp?.focus(); inp?.select(); }
    if (!on && focus) editBtn.focus();
  }
  editBtn.addEventListener('click', () => setEditing(!editing));

  const order = () => $$('li', list).map(li => li.dataset.url);
  async function saveOrder(focusUrl) {
    const res = await api('/reorder', { order: order() });
    if (focusUrl) {
      const i = favs.findIndex(f => f.url === focusUrl);
      const inp = $$('.adm-fav__name', list)[i];
      inp?.focus();
      if (res?.ok) announce(t('An Position {n} von {total}', { n: i + 1, total: favs.length }));
    }
  }
  list.addEventListener('change', e => {
    const inp = e.target.closest('.adm-fav__name');
    if (!inp) return;
    const url = inp.closest('li').dataset.url, f = favs.find(x => x.url === url), v = inp.value.trim();
    if (!f) return;
    if (!v) { inp.value = f.title; return; }
    if (v !== f.title) api('/rename', { url, title: v }).then(res => {
      if (res?.ok) { announce(t('Favorit umbenannt')); $$('.adm-fav__name', list)[favs.findIndex(x => x.url === url)]?.focus(); }
    });
  });
  list.addEventListener('keydown', e => {
    const inp = e.target.closest('.adm-fav__name');
    if (!inp) return;
    const li = inp.closest('li');
    if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
      e.preventDefault();
      const sib = e.key === 'ArrowUp' ? li.previousElementSibling : li.nextElementSibling;
      if (!sib) return;
      const f = favs.find(x => x.url === li.dataset.url);
      if (f && inp.value.trim() && inp.value.trim() !== f.title) f.title = inp.value.trim();
      e.key === 'ArrowUp' ? sib.before(li) : sib.after(li);
      saveOrder(li.dataset.url);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      inp.dispatchEvent(new Event('change', { bubbles: true }));
    } else if (e.key === 'Escape') {
      const f = favs.find(x => x.url === li.dataset.url);
      if (f && inp.value !== f.title) { inp.value = f.title; } else setEditing(false);
    }
  });
  list.addEventListener('click', async e => {
    const rm = e.target.closest('.adm-fav__rm');
    if (!rm) return;
    const li = rm.closest('li'), idx = $$('li', list).indexOf(li);
    const res = await api('/remove', { url: li.dataset.url });
    if (res?.ok) {
      announce(t('Aus Favoriten entfernt'));
      ($$('.adm-fav__name', list)[Math.min(idx, favs.length - 1)] || editBtn).focus();
    }
  });
  // Ziehen (am Griff)
  let drag = null, before = '';
  list.addEventListener('dragstart', e => {
    const grip = e.target.closest?.('.adm-fav__grip');
    if (!grip) return;
    drag = grip.closest('li');
    before = order().join('\n');
    drag.classList.add('is-dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', drag.dataset.url);
    e.dataTransfer.setDragImage(drag, 12, 14);
  });
  list.addEventListener('dragover', e => {
    if (!drag) return;
    e.preventDefault();
    const li = e.target.closest?.('li');
    if (!li || li === drag || li.parentElement !== list) return;
    const r = li.getBoundingClientRect();
    e.clientY < r.top + r.height / 2 ? li.before(drag) : li.after(drag);
  });
  list.addEventListener('drop', e => { if (drag) e.preventDefault(); });
  list.addEventListener('dragend', () => {
    if (!drag) return;
    const url = drag.dataset.url;
    drag.classList.remove('is-dragging');
    drag = null;
    if (order().join('\n') !== before) saveOrder(url);
  });

  // ---------------------------------------------------------- Ort geändert (Hash, Reiter, Datei)
  const onLocation = () => { if (!editing) render(); else syncStar(); };
  addEventListener('hashchange', onLocation);
  d.addEventListener('adm:location', onLocation);

  placeStar();
  render();
}
