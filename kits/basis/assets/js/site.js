/*
 * Frontend-JS „basis“ (Vanilla, ohne Abhängigkeiten, ≈ 3 KB minifiziert). Alles funktioniert auch ohne JavaScript
 * (Menü dann sichtbar, Unterseiten per <details>, Öffnungszeiten als Text).
 *  - Menü-Schaltfläche: Mobilmenü bzw. Vollbild-Menü („Minimal“) mit aria-expanded, Escape, inertem Rest der Seite,
 *    Fokus bleibt im Menü (Tab), Klick auf den abgedunkelten Hintergrund schließt
 *  - Aufklapp- und Mega-Menüs: nur eins offen, außen klicken/Fokus verlassen schließt, Pfeiltasten, Pos1/Ende
 *  - „Modern“ transparent über dem ersten Abschnitt: beim Scrollen deckend (.is-stuck)
 *  - Infoleiste: „Jetzt geöffnet“ aus den Öffnungszeiten (Zeitzone der Website)
 *  - Website-Suche: Vorschläge (Kern-Skript search.js) erst beim ersten Fokus eines Suchfelds laden
 */
const d = document;
const html = d.documentElement;
html.classList.replace('no-js', 'js');

const header = d.querySelector('[data-header]');
const btn = header?.querySelector('.menu-btn');
const nav = header?.querySelector('.site-nav');
const minimal = !!header?.classList.contains('site-header--minimal');
const desktop = matchMedia('(min-width: 960px)');
const subs = [...d.querySelectorAll('.site-nav .nav__sub')];
const isOpen = () => !!header?.classList.contains('is-open');

// ------------------------------------------------------------ Menü-Schaltfläche
function setMenu(open, focusBtn = false) {
  if (!btn) return;
  header.style.setProperty('--b-hb', Math.round(header.getBoundingClientRect().bottom) + 'px');
  header.classList.toggle('is-open', open);
  btn.setAttribute('aria-expanded', String(open));
  btn.querySelector('.menu-btn__label').textContent = open ? btn.dataset.labelClose : btn.dataset.labelOpen;
  html.classList.toggle('menu-open', open);
  // Rest der Seite inert → Fokus bleibt in Kopfbereich und Menü
  for (const el of d.body.children) if (!el.contains(header) && el.tagName !== 'SCRIPT') el.inert = open;
  if (open && minimal) nav.querySelector('a[href]')?.focus();
  if (!open && focusBtn) btn.focus({ preventScroll: true });   // ohne Scrollen (Kopf ist „sticky“)
}
btn?.addEventListener('click', () => setMenu(!isOpen()));
nav?.addEventListener('click', e => { if (e.target.closest('a[href]') && isOpen()) setMenu(false); });
// Abgedunkelter Hintergrund (::after des Kopfbereichs) schließt
header?.addEventListener('click', e => { if (e.target === header && isOpen()) setMenu(false, true); });
// Tab bleibt im geöffneten Menü (Kopfzeile + Menü): vom letzten zum ersten Element und zurück
d.addEventListener('keydown', e => {
  if (e.key !== 'Tab' || !isOpen()) return;
  const f = [...header.querySelectorAll('a[href],button,input,summary')].filter(el => el.checkVisibility ? el.checkVisibility({ visibilityProperty: true }) : el.offsetParent);
  const first = f[0], last = f[f.length - 1];
  if (e.shiftKey ? d.activeElement === first : d.activeElement === last) { e.preventDefault(); (e.shiftKey ? last : first).focus(); }
});
// Vollbild-Menü („Minimal“): Unterseiten mobil als Akkordeon
nav?.querySelectorAll('.nav__toggle').forEach(t => t.addEventListener('click', () => t.setAttribute('aria-expanded', t.getAttribute('aria-expanded') !== 'true')));
desktop.addEventListener?.('change', () => { if (!minimal && isOpen()) setMenu(false); subs.forEach(s => { s.open = false; }); });

// ------------------------------------------------------------ Aufklapp-/Mega-Menüs (Desktop: nur eins offen)
const closeSubs = keep => subs.forEach(s => { if (s !== keep) s.open = false; });
subs.forEach(s => {
  s.addEventListener('toggle', () => { if (s.open && desktop.matches) closeSubs(s); });
  s.addEventListener('focusout', e => { if (desktop.matches && s.open && e.relatedTarget && !s.contains(e.relatedTarget)) s.open = false; });
});
d.addEventListener('click', e => { if (desktop.matches) subs.forEach(s => { if (s.open && !s.contains(e.target)) s.open = false; }); });

// Pfeiltasten: links/rechts zwischen Hauptpunkten, runter öffnet und springt ins Untermenü, hoch/runter darin
const tops = () => nav ? [...nav.querySelectorAll('.nav > .nav__item > .nav__link, .nav > .nav__item > .nav__sub > .nav__link')] : [];
d.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    const open = subs.find(s => s.open);
    if (open) { open.open = false; open.querySelector('summary').focus(); }
    else if (isOpen()) setMenu(false, true);
    return;
  }
  if (!nav || minimal || !desktop.matches || !nav.contains(e.target) || e.altKey || e.ctrlKey || e.metaKey) return;
  const list = tops();
  const i = list.indexOf(e.target);
  const sub = e.target.closest('.nav__sub') || (e.target.matches('.nav__link--top') ? e.target.parentElement.querySelector('.nav__sub') : null);
  const move = el => { if (el) { e.preventDefault(); el.focus(); } };
  if (i > -1) {
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
      closeSubs(null);
      move(list[(i + (e.key === 'ArrowRight' ? 1 : list.length - 1)) % list.length]);
    } else if (e.key === 'Home' || e.key === 'End') {
      move(list[e.key === 'Home' ? 0 : list.length - 1]);
    } else if (e.key === 'ArrowDown' && sub) {
      sub.open = true;
      move(sub.querySelector('a[href]'));
    }
  } else if (sub) {
    const links = [...sub.querySelectorAll('a[href]')];
    const j = links.indexOf(e.target);
    const top = sub.querySelector('summary');
    if (e.key === 'ArrowDown') move(links[j + 1] || links[0]);
    else if (e.key === 'ArrowUp') move(j > 0 ? links[j - 1] : top);
    else if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
      const k = list.indexOf(top);
      sub.open = false;
      move(list[(k + (e.key === 'ArrowRight' ? 1 : list.length - 1)) % list.length]);
    }
  }
});

// Design „Link + Pfeil, öffnet auch beim Überfahren“ (np-hover): nur mit Maus, kurze Verzögerung beim Verlassen;
// der erste Klick auf den Pfeil nach dem Öffnen per Maus schließt nicht gleich wieder
if (html.classList.contains('np-hover') && matchMedia('(hover:hover) and (pointer:fine)').matches) d.querySelectorAll('.nav__item--split').forEach(li => {
  const s = li.querySelector('.nav__sub');
  let t = 0, o = 0;
  li.addEventListener('mouseenter', () => { clearTimeout(t); if (desktop.matches && !s.open) s.open = o = 1; });
  s.firstElementChild.addEventListener('click', e => { if (s.open && o) e.preventDefault(); o = 0; });
  li.addEventListener('mouseleave', () => { t = setTimeout(() => { if (desktop.matches && !s.contains(d.activeElement)) s.open = false; }, 250); });
});

// ------------------------------------------------------------ „Modern“ über dem ersten Abschnitt: beim Scrollen deckend
if (header?.classList.contains('is-over')) {
  let ticking = false;
  const update = () => { ticking = false; header.classList.toggle('is-stuck', scrollY > header.offsetHeight + 48); };
  addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
  update();
}

// ------------------------------------------------------------ „Jetzt geöffnet“ (Infoleiste)
// Infoleiste und Mobilmenü (partials/openstate.php)
d.querySelectorAll('.openstate').forEach(state => {
  try {
    const t = state.dataset;
    const ranges = JSON.parse(t.hours);
    const days = JSON.parse(t.days);
    const p = {};
    new Intl.DateTimeFormat('en-GB', { timeZone: t.tz, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
      .formatToParts(new Date()).forEach(x => { p[x.type] = x.value; });
    const dow = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(p.weekday);
    const now = p.hour + ':' + p.minute;
    const clock = s => s.replace(/^0/, '');
    const cur = ranges.find(r => r[0] === dow && r[1] <= now && now < r[2]);
    let head = t.tClosed, rest = '';
    if (cur) {
      head = t.tOpen;
      rest = t.tUntil.replace('{zeit}', clock(cur[2]));
      state.classList.add('is-open');
    } else {
      for (let k = 0; k < 7 && !rest; k++) {
        const day = (dow + k) % 7;
        const next = ranges.filter(r => r[0] === day && (k > 0 || r[1] > now)).sort((a, b) => a[1] < b[1] ? -1 : 1)[0];
        if (next) rest = t.tOpens.replace('{tag}', k === 0 ? t.tToday : k === 1 ? t.tTomorrow : days[day]).replace('{zeit}', clock(next[1]));
      }
    }
    const b = d.createElement('b');
    b.textContent = head;
    state.querySelector('.openstate__text').replaceChildren(b, rest ? ' · ' + rest : '');
  } catch { /* Anzeige bleibt beim Text ohne JavaScript */ }
});

// Website-Suche: Vorschläge beim Tippen (/assets/js/search.js, ≈ 2 KB) erst laden, wenn ein Suchfeld den Fokus bekommt
let suggestJs;
d.addEventListener('focusin', e => { const s = e.target.dataset?.suggestJs; s && !suggestJs && (suggestJs = d.head.append(Object.assign(d.createElement('script'), { src: s })) || 1); });
