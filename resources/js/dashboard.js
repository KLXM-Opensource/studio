/*
 * Übersicht der Verwaltung (/admin, app/Admin/views/dashboard.php):
 *  - schwere Karten nachladen (GET /admin/api/dashboard/{karte} → {html}), Ergebnis in einer Live-Region melden
 *  - Karten auf-/zuklappen, im Modus „Übersicht anpassen“ verschieben (Pfeile, Ziehen am Griff), aus-/einblenden
 *  - Speichern je Konto: POST /admin/api/dashboard/prefs (users.ui_prefs → dash). Ohne JavaScript tun dieselben Knöpfe
 *    das per Formular (Seite lädt neu), nachgeladene Karten zeigt /admin?alle=1.
 *  - „Fragen Sie KLXM AI“: öffnet das Assistent-Fenster mit der Frage (window.cmsAssistant), sonst die Assistent-Seite.
 */
import { t } from './_i18n.js';

(() => {
  const d = document;
  const grid = d.querySelector('[data-dash]');
  if (!grid) return;
  const base = grid.dataset.endpoint;
  const live = d.querySelector('[data-dash-live]');
  const csrf = () => d.getElementById('adm-csrf')?.value || '';
  const cards = () => [...grid.querySelectorAll(':scope > .dash-card')];
  const title = card => card.querySelector('.dash-card__toggle')?.textContent.trim() || '';
  const say = msg => { if (!live) return; live.textContent = ''; setTimeout(() => { live.textContent = msg; }, 60); };

  const save = (body) => fetch(base + '/prefs', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() },
    body: JSON.stringify(body),
  }).then(r => { if (!r.ok) throw new Error(r.status); return r.json(); }).catch(() => say(t('Speichern fehlgeschlagen – bitte Seite neu laden.')));

  // ------------------------------------------------------------ Nachladen
  let pending = 0;
  const load = body => {
    if (!body || body.dataset.loaded) return;
    body.dataset.loaded = '1';
    pending++;
    fetch(base + '/' + encodeURIComponent(body.dataset.lazy), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(j => { body.innerHTML = j.html || ''; })
      .catch(() => { body.innerHTML = '<p class="adm-muted">' + t('Konnte nicht geladen werden.') + '</p>'; delete body.dataset.loaded; })
      .finally(() => {
        body.removeAttribute('aria-busy');
        if (--pending === 0) say(t('Statistiken geladen'));
      });
  };
  const loadVisible = () => grid.querySelectorAll('.dash-card:not(.is-hidden):not(.is-closed) [data-lazy]').forEach(load);
  loadVisible();

  // ------------------------------------------------------------ Knöpfe (ersetzen die Formulare)
  const syncMoves = () => {
    const list = cards();
    list.forEach((c, i) => {
      c.querySelector('[data-dash-move=up]').disabled = i === 0;
      c.querySelector('[data-dash-move=down]').disabled = i === list.length - 1;
    });
  };

  grid.addEventListener('click', e => {
    const btn = e.target.closest('button[form]');
    if (!btn || !grid.contains(btn)) return;
    const card = btn.closest('.dash-card');
    const key = card.dataset.card;
    e.preventDefault();
    if (btn.hasAttribute('data-dash-toggle')) {
      const open = btn.getAttribute('aria-expanded') !== 'true';
      btn.setAttribute('aria-expanded', String(open));
      btn.value = open ? 'close' : 'open';
      card.classList.toggle('is-closed', !open);
      const body = d.getElementById(btn.getAttribute('aria-controls'));
      body.hidden = !open;
      if (open) load(body.matches('[data-lazy]') ? body : null);
      save({ do: open ? 'open' : 'close', card: key });
      return;
    }
    if (btn.dataset.dashMove) {
      const up = btn.dataset.dashMove === 'up';
      const sib = up ? card.previousElementSibling : card.nextElementSibling;
      if (!sib?.classList.contains('dash-card')) return;
      up ? grid.insertBefore(card, sib) : grid.insertBefore(sib, card);
      syncMoves();
      (btn.disabled ? card.querySelector('[data-dash-move=' + (up ? 'down' : 'up') + ']') : btn).focus();
      card.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      say(t(up ? '„{name}“ nach oben verschoben' : '„{name}“ nach unten verschoben', { name: title(card) }));
      save({ do: 'order', order: cards().map(c => c.dataset.card) });
      return;
    }
    if (btn.hasAttribute('data-dash-hide')) {
      const hide = btn.getAttribute('aria-pressed') !== 'true';
      btn.setAttribute('aria-pressed', String(hide));
      btn.value = hide ? 'show' : 'hide';
      card.classList.toggle('is-hidden', hide);
      const lab = btn.querySelector('[data-dash-hide-label]');
      if (lab) lab.textContent = t(hide ? 'Einblenden: {name}' : 'Ausblenden: {name}', { name: title(card) });
      say(t(hide ? '„{name}“ ausgeblendet' : '„{name}“ eingeblendet', { name: title(card) }));
      if (!hide) loadVisible();
      save({ do: hide ? 'hide' : 'show', card: key });
    }
  });

  // ------------------------------------------------------------ Anpassen-Modus
  const toggleBtn = d.querySelector('[data-dash-customize]');
  const setMode = on => {
    grid.classList.toggle('is-customizing', on);
    toggleBtn.setAttribute('aria-pressed', String(on));
    toggleBtn.querySelector('[data-dash-customize-label]').textContent = on ? t('Fertig') : t('Übersicht anpassen');
    d.querySelectorAll('.dash-bar__reset,[data-dash-hint]').forEach(el => { el.hidden = !on; });
    cards().forEach(c => { if (c.classList.contains('is-hidden')) c.hidden = !on; });
    history.replaceState(null, '', location.pathname + (on ? '?anpassen=1' : ''));
  };
  toggleBtn?.addEventListener('click', e => {
    e.preventDefault();
    setMode(toggleBtn.getAttribute('aria-pressed') !== 'true');
  });

  // Ziehen am Griff (Maus/Touch); Tastatur: Pfeil-Knöpfe
  let drag = null;
  grid.addEventListener('pointerdown', e => {
    const grip = e.target.closest('[data-dash-grip]');
    if (!grip || !grid.classList.contains('is-customizing')) return;
    drag = grip.closest('.dash-card');
    drag.draggable = true;
  });
  grid.addEventListener('dragstart', e => {
    if (!drag) return e.preventDefault();
    drag.classList.add('is-dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', drag.dataset.card);
  });
  grid.addEventListener('dragover', e => {
    const over = e.target.closest('.dash-card');
    if (!drag || !over || over === drag) return;
    e.preventDefault();
    cards().forEach(c => c.classList.toggle('is-drop', c === over));
  });
  grid.addEventListener('drop', e => {
    const over = e.target.closest('.dash-card');
    if (!drag || !over || over === drag) return;
    e.preventDefault();
    const list = cards();
    grid.insertBefore(drag, list.indexOf(drag) < list.indexOf(over) ? over.nextElementSibling : over);
    syncMoves();
    say(t('„{name}“ verschoben', { name: title(drag) }));
    save({ do: 'order', order: cards().map(c => c.dataset.card) });
  });
  grid.addEventListener('dragend', () => {
    cards().forEach(c => c.classList.remove('is-drop', 'is-dragging'));
    if (drag) drag.draggable = false;
    drag = null;
  });
  d.addEventListener('pointerup', () => { if (drag && !drag.classList.contains('is-dragging')) { drag.draggable = false; drag = null; } });

  // ------------------------------------------------------------ Assistent fragen
  d.querySelector('[data-dash-ask]')?.addEventListener('submit', e => {
    const q = e.target.q.value.trim();
    if (!window.cmsAssistant) return;   // ohne Chat-Fenster: Assistent-Seite mit ?q=
    e.preventDefault();
    if (!q) { e.target.q.focus(); return; }
    window.cmsAssistant.open({ q });
    e.target.q.value = '';
  });
})();

// ------------------------------------------------------------ Mauerwerk: Karten rücken lückenlos nach oben
// Raster in 4-px-Zeilen; jede Karte belegt so viele Zeilen, wie sie hoch ist (+ Abstand). Spalten und Spannen legt allein das
// CSS fest (Container-Abfrage, dashboard.css) – hier ändern sich nur die Zeilen, daher kein seitliches Springen. DOM- und
// Tab-Reihenfolge bleiben unverändert. Ohne JavaScript bzw. bei einer Spalte gilt das normale Raster (Zeilenhöhe = Inhalt).
// Das Skript läuft ohne defer direkt nach dem Raster, damit schon das erste Bild gepackt ist.
(() => {
  const grid = document.querySelector('[data-dash]');
  if (!grid || !('ResizeObserver' in window)) return;
  const ROW = 4;
  const cards = () => [...grid.children].filter(c => c.matches('.dash-card'));
  let on = false;
  const layout = () => {
    const cs = getComputedStyle(grid);
    const multi = cs.gridTemplateColumns.split(' ').length > 1;
    if (multi !== on) { grid.classList.toggle('is-packed', multi); on = multi; }
    const gap = parseFloat(cs.columnGap) || 20;
    const list = cards();
    // erst alle Höhen lesen, dann schreiben (kein Layout-Flattern)
    const spans = list.map(c => {
      const h = multi && !c.hidden ? c.getBoundingClientRect().height : 0;
      return h ? 'span ' + Math.ceil((h + gap) / ROW) : '';
    });
    list.forEach((c, i) => { if (c.style.gridRowEnd !== spans[i]) c.style.gridRowEnd = spans[i]; });
  };
  // Größe einer Karte (Nachladen, Auf-/Zuklappen, Fundstellen, Schriften) oder des Rasters (Fenster, Seitenleiste) ändert sich
  const ro = new ResizeObserver(layout);
  ro.observe(grid);
  cards().forEach(c => ro.observe(c));
  // neu eingefügte/verschobene Karten (Anpassen-Modus) mitnehmen
  new MutationObserver(() => { cards().forEach(c => ro.observe(c)); layout(); }).observe(grid, { childList: true });
  layout();
})();
