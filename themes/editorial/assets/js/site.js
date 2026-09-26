/*
 * Frontend-JS „editorial“ (Vanilla, ohne Abhängigkeiten). Alles funktioniert auch ohne JavaScript
 * (Menü dann sichtbar, Unterseiten und „Ressorts“ per <details>, Datum vom Server).
 *  - Menü-Blatt (mobil): aria-expanded, Escape, Rest der Seite inert, Seite gesperrt, Fokus bleibt im Menü (Tab), Klick daneben schließt
 *  - Aufklappmenüs + „Ressorts“ (Desktop): nur eins offen, außen klicken/Fokus verlassen/Escape schließt, Pfeiltasten, Pos1/Ende
 *  - Datumszeile: heutiges Datum in der Sprache der Seite (der Seiten-Cache bleibt gültig)
 *  - Website-Suche: Vorschläge (Kern-Skript search.js) erst beim ersten Fokus eines Suchfelds laden
 */
const d = document;
const html = d.documentElement;
html.classList.replace('no-js', 'js');

const header = d.querySelector('[data-header]');
const btn = header?.querySelector('.menu-btn');
const nav = header?.querySelector('.site-nav');
const desktop = matchMedia('(min-width: 960px)');
const subs = [...d.querySelectorAll('[data-header] details')];
const isOpen = () => !!header?.classList.contains('is-open');

// ------------------------------------------------------------ Menü-Blatt
function setMenu(open, focusBtn = false) {
  if (!btn) return;
  header.style.setProperty('--e-hb', Math.max(0, Math.round(header.getBoundingClientRect().bottom)) + 'px');
  header.classList.toggle('is-open', open);
  btn.setAttribute('aria-expanded', String(open));
  btn.querySelector('.menu-btn__label').textContent = open ? btn.dataset.labelClose : btn.dataset.labelOpen;
  html.classList.toggle('menu-open', open);
  for (const el of d.body.children) if (!el.contains(header) && el.tagName !== 'SCRIPT') el.inert = open;
  if (!open && focusBtn) btn.focus();
}
btn?.addEventListener('click', () => setMenu(!isOpen()));
nav?.addEventListener('click', e => { if (e.target.closest('a[href]') && isOpen()) setMenu(false); });
header?.addEventListener('click', e => { if (e.target === header && isOpen()) setMenu(false, true); });   // Schleier (::after)
d.addEventListener('keydown', e => {
  if (e.key !== 'Tab' || !isOpen()) return;
  const f = [...header.querySelectorAll('a[href],button,input,summary')].filter(el => el.checkVisibility ? el.checkVisibility({ visibilityProperty: true }) : el.offsetParent);
  const first = f[0], last = f[f.length - 1];
  if (e.shiftKey ? d.activeElement === first : d.activeElement === last) { e.preventDefault(); (e.shiftKey ? last : first).focus(); }
});
desktop.addEventListener?.('change', () => { if (isOpen()) setMenu(false); subs.forEach(s => { s.open = false; }); });

// ------------------------------------------------------------ Aufklappmenüs (Desktop: nur eins offen)
const closeSubs = keep => subs.forEach(s => { if (s !== keep) s.open = false; });
subs.forEach(s => {
  s.addEventListener('toggle', () => { if (s.open && desktop.matches) closeSubs(s); });
  s.addEventListener('focusout', e => { if (desktop.matches && s.open && e.relatedTarget && !s.contains(e.relatedTarget)) s.open = false; });
});
d.addEventListener('click', e => { if (desktop.matches) subs.forEach(s => { if (s.open && !s.contains(e.target)) s.open = false; }); });

const tops = () => nav ? [...nav.querySelectorAll('.nav > .nav__item > .nav__link, .nav > .nav__item > .nav__sub > .nav__link')] : [];
d.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    const open = subs.find(s => s.open);
    if (open) { open.open = false; open.querySelector('summary').focus(); }
    else if (isOpen()) setMenu(false, true);
    return;
  }
  if (!nav || !desktop.matches || !nav.contains(e.target) || e.altKey || e.ctrlKey || e.metaKey) return;
  const list = tops();
  const i = list.indexOf(e.target);
  const sub = e.target.closest('.nav__sub');
  const move = el => { if (el) { e.preventDefault(); el.focus(); } };
  if (i > -1) {
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { closeSubs(null); move(list[(i + (e.key === 'ArrowRight' ? 1 : list.length - 1)) % list.length]); }
    else if (e.key === 'Home' || e.key === 'End') move(list[e.key === 'Home' ? 0 : list.length - 1]);
    else if (e.key === 'ArrowDown' && sub) { sub.open = true; move(sub.querySelector('a[href]')); }
  } else if (sub) {
    const links = [...sub.querySelectorAll('a[href]')];
    const j = links.indexOf(e.target);
    if (e.key === 'ArrowDown') move(links[j + 1] || links[0]);
    else if (e.key === 'ArrowUp') move(j > 0 ? links[j - 1] : sub.querySelector('summary'));
  }
});

// ------------------------------------------------------------ Datumszeile
d.querySelectorAll('[data-today]').forEach(t => {
  try {
    const now = new Date();
    t.textContent = new Intl.DateTimeFormat(html.lang || 'de', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now);
    t.dateTime = now.toISOString().slice(0, 10);
  } catch { /* Datum vom Server bleibt */ }
});

// ------------------------------------------------------------ Website-Suche: Vorschläge beim Tippen erst bei Bedarf laden
let suggestJs;
d.addEventListener('focusin', e => { const s = e.target.dataset?.suggestJs; s && !suggestJs && (suggestJs = d.head.append(Object.assign(d.createElement('script'), { src: s })) || 1); });
