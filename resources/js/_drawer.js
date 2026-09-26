import { t } from './_i18n.js';
/*
 * Seitenleiste als Schublade auf schmalen Bildschirmen (≤ 900 px, views/layout.php · admin.css „Mobil“).
 * Ohne JavaScript öffnet der Link „#adm-side“ die Leiste per :target. Mit JavaScript: echter Knopf mit
 * aria-expanded, Fokusfalle, Esc, Klick auf den Hintergrund, Rückgabe des Fokus, gesperrtes Scrollen
 * und `inert` für den Rest der Seite. Schließt beim Navigieren und beim Wechsel auf breite Bildschirme.
 *
 *   document.dispatchEvent(new CustomEvent('adm:drawer', { detail: 'open' | 'close' | 'toggle' }))
 */
const d = document, html = d.documentElement, body = d.body;
const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),summary,[tabindex]:not([tabindex="-1"])';

export function initDrawer() {
  const side = d.getElementById('adm-side'), top = d.querySelector('.adm-top');
  if (!side || !top) return;
  const mq = matchMedia('(max-width:900px)');
  // Link → Knopf (ohne JavaScript bleibt der Anker-Link mit :target)
  const upgrade = (a, label) => {
    if (!a) return null;
    const b = d.createElement('button');
    b.type = 'button';
    b.className = a.className;
    b.innerHTML = a.innerHTML;
    b.setAttribute('aria-label', label);
    a.replaceWith(b);
    return b;
  };
  const btn = upgrade(top.querySelector('[data-drawer-open]'), t('Menü'));
  const close = upgrade(side.querySelector('[data-drawer-close]'), t('Menü schließen'));
  btn.setAttribute('aria-controls', side.id);
  btn.setAttribute('aria-expanded', 'false');
  if (location.hash === '#adm-side') history.replaceState(null, '', location.pathname + location.search);

  const scrim = d.createElement('div');
  scrim.className = 'adm-scrim';
  scrim.hidden = true;
  side.after(scrim);

  let open = false, inerted = [];
  const set = (on, { focus = true } = {}) => {
    on = on && mq.matches;
    if (on === open) return;
    open = on;
    btn.setAttribute('aria-expanded', String(on));
    body.classList.toggle('is-nav-open', on);
    html.classList.toggle('adm-lock', on);
    scrim.hidden = !on;
    if (on) {
      inerted = [...body.children].filter(el => el !== side && el !== scrim && el.tagName !== 'SCRIPT' && !el.inert);
      inerted.forEach(el => { el.inert = true; });
      side.setAttribute('role', 'dialog');
      side.setAttribute('aria-modal', 'true');
      if (focus) close?.focus({ preventScroll: true });
    } else {
      inerted.forEach(el => { el.inert = false; });
      inerted = [];
      side.removeAttribute('role');
      side.removeAttribute('aria-modal');
      if (focus) btn.focus({ preventScroll: true });
    }
  };

  btn.addEventListener('click', () => set(!open));
  close?.addEventListener('click', () => set(false));
  scrim.addEventListener('click', () => set(false));
  d.addEventListener('adm:drawer', e => set(e.detail === 'toggle' ? !open : e.detail === 'open'));
  d.addEventListener('keydown', e => {
    if (!open) return;
    if (e.key === 'Escape' && !e.defaultPrevented && !d.querySelector('dialog[open]')) { e.preventDefault(); set(false); return; }
    if (e.key !== 'Tab') return;
    const f = [...side.querySelectorAll(FOCUSABLE)].filter(el => el.offsetParent !== null || el === d.activeElement);
    if (!f.length) return;
    const first = f[0], last = f[f.length - 1];
    if (e.shiftKey && (d.activeElement === first || !side.contains(d.activeElement))) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && (d.activeElement === last || !side.contains(d.activeElement))) { e.preventDefault(); first.focus(); }
  });
  // Navigieren schließt die Schublade (auch für die Rückkehr per „Zurück“ aus dem Cache)
  side.addEventListener('click', e => {
    const a = e.target.closest('a[href]');
    if (a && !a.target && !e.defaultPrevented && !a.hasAttribute('data-fav-edit') && a.getAttribute('href').charAt(0) !== '#') set(false, { focus: false });
  });
  side.addEventListener('submit', () => set(false, { focus: false }));
  addEventListener('pageshow', e => { if (e.persisted) set(false, { focus: false }); });
  mq.addEventListener('change', () => { if (!mq.matches) set(false, { focus: false }); });
}
