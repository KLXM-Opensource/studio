/*
 * Aufklappbare Gruppen im Abschnitt „Administration“ der Seitenleiste (views/layout.php, Core\AdminPages):
 * „Einstellungen“ und „Werkzeuge“. Knopf mit aria-expanded/aria-controls, Liste mit hidden.
 * Der Server öffnet die Gruppe der aktuellen Seite (.is-current) – die bleibt offen. Alle anderen öffnet bzw. schließt
 * der zuletzt gewählte Zustand (nur dieser Browser, localStorage „klxm-studio-navgroups“: {key: 1|0}).
 * Tastatur: Knopf mit Enter/Leertaste; Esc in einer offenen Gruppe schließt sie und setzt den Fokus auf den Knopf.
 */
const KEY = 'klxm-studio-navgroups';

const load = () => { try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch { return {}; } };
const save = st => { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch { /* privat/gesperrt */ } };

export function initNavGroups(root = document) {
  const groups = root.querySelectorAll('[data-navgroup]');
  if (!groups.length) return;
  const st = load();
  groups.forEach(g => {
    const btn = g.querySelector(':scope > .adm-navgrp__btn'), list = g.querySelector(':scope > .adm-navgrp__list'), k = g.dataset.navgroup;
    if (!btn || !list) return;
    const set = open => {
      btn.setAttribute('aria-expanded', String(open));
      list.hidden = !open;
      g.classList.toggle('is-open', open);
    };
    if (!g.classList.contains('is-current') && k in st) set(st[k] === 1);
    btn.addEventListener('click', () => {
      g.classList.add('is-anim');
      const open = btn.getAttribute('aria-expanded') !== 'true';
      set(open);
      st[k] = open ? 1 : 0;
      save(st);
    });
    list.addEventListener('keydown', e => {
      if (e.key !== 'Escape') return;
      g.classList.add('is-anim');
      e.preventDefault();
      e.stopPropagation();   // nicht gleich die Schublade schließen (_drawer.js)
      set(false);
      st[k] = 0;
      save(st);
      btn.focus();
    });
  });
}
