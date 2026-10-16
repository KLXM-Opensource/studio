/*
 * Frontend-JS „galerie“ (aus „fluid“) (Vanilla, ohne Abhängigkeiten). Alles funktioniert auch ohne JavaScript:
 * Menü per Container-Query + popover, Aufklappmenüs per <details>, Seitenblatt schließt per Escape/Klick daneben.
 * Hier nur Verfeinerungen:
 *  - Einblenden beim Scrollen (einmalig, IntersectionObserver)
 *  - Kopf: prüft, ob das Menü wirklich in die Leiste passt (sonst .is-overflow → Menü-Schaltfläche) – ResizeObserver
 *  - Seitenblatt: Fokus auf das erste Element, Fokusfalle (Tab), Rest der Seite inert, schließt bei Klick auf einen Link
 *  - Aufklappmenüs: nur eins offen, Klick daneben/Fokus verlassen schließt, Pfeiltasten, Pos1/Ende, Escape
 *  - Hintergrundvideo (Einstieg vollflächig): startet nur ohne „Bewegung reduzieren“, mit Pause-Schaltfläche
 *  - Website-Suche: Vorschläge (Kern-Skript search.js) erst beim ersten Fokus eines Suchfelds laden
 */
const d = document;
const html = d.documentElement;
html.classList.replace('no-js', 'js');
const reduce = matchMedia('(prefers-reduced-motion: reduce)');

// ------------------------------------------------------------ Einblenden beim Scrollen (einmalig; nur mit „Animationen“ und ohne „Bewegung reduzieren“ – CSS)
if (html.classList.contains('has-motion') && !reduce.matches && 'IntersectionObserver' in window) {
  const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } }), { rootMargin: '0px 0px -8% 0px' });
  d.querySelectorAll('[data-reveal]').forEach(el => io.observe(el));
} else d.querySelectorAll('[data-reveal]').forEach(el => el.classList.add('is-in'));

// ------------------------------------------------------------ Kopf: passt das Menü?
const hdr = d.querySelector('[data-header]');
const nav = hdr?.querySelector('.hdr__nav');
if (hdr && nav && 'ResizeObserver' in window) {
  let tooSmallAt = 0;
  const check = () => {
    const w = hdr.clientWidth;
    if (hdr.classList.contains('is-overflow')) {
      if (w > tooSmallAt + 24) { hdr.classList.remove('is-overflow'); requestAnimationFrame(check); }
      return;
    }
    if (getComputedStyle(nav).display === 'none' || hdr.querySelector('.hnav__sub[open]')) return;
    const bar = hdr.querySelector('.hdr__bar');
    const list = nav.querySelector('.hnav__list');
    if (bar.scrollWidth > bar.clientWidth + 1 || (list && list.scrollWidth > nav.clientWidth + 1 && getComputedStyle(nav).flexDirection !== 'column')) {
      tooSmallAt = w;
      hdr.classList.add('is-overflow');
    }
  };
  new ResizeObserver(check).observe(hdr);
  d.fonts?.ready.then(check);
}

// ------------------------------------------------------------ Seitenblatt (popover)
const sheet = d.querySelector('[data-mnav]');
if (sheet) {
  const btn = d.querySelector('.menu-btn');
  const focusables = () => [...sheet.querySelectorAll('a[href],button,input,summary,select,textarea')].filter(el => el.offsetParent !== null || el === d.activeElement);
  const setInert = on => { for (const el of d.body.children) if (el !== sheet && el.tagName !== 'SCRIPT' && !el.matches('.cms-bar-host,dialog')) el.inert = on; };
  // Beim Schließen den Rest der Seite vorher wieder freigeben – sonst kann der Fokus nicht zur Schaltfläche zurück
  sheet.addEventListener('beforetoggle', e => { if (e.newState === 'closed') setInert(false); });
  sheet.addEventListener('toggle', e => {
    const open = e.newState === 'open';
    btn?.setAttribute('aria-expanded', String(open));
    sheet.setAttribute('aria-modal', String(open));
    if (open) { setInert(true); (sheet.querySelector('.mnav__nav a[aria-current],.mnav__close') || focusables()[0])?.focus(); }
    else if (!d.activeElement || d.activeElement === d.body) btn?.focus();
  });
  sheet.addEventListener('keydown', e => {
    if (e.key !== 'Tab') return;
    const f = focusables();
    const first = f[0], last = f[f.length - 1];
    if (e.shiftKey ? d.activeElement === first : d.activeElement === last) { e.preventDefault(); (e.shiftKey ? last : first).focus(); }
  });
  // Anker-Links (z. B. #kontakt) schließen das Blatt, damit das Ziel sichtbar wird
  sheet.addEventListener('click', e => { if (e.target.closest('a[href]')) sheet.hidePopover?.(); });
}

// ------------------------------------------------------------ Aufklappmenüs in der Leiste
const subs = [...d.querySelectorAll('.hnav__sub')];
const inline = s => getComputedStyle(s.querySelector('.hnav__panel')).position === 'absolute';
const closeSubs = keep => subs.forEach(s => { if (s !== keep && inline(s)) s.open = false; });
subs.forEach(s => {
  s.addEventListener('toggle', () => { if (s.open) closeSubs(s); });
  s.addEventListener('focusout', e => { if (s.open && inline(s) && e.relatedTarget && !s.contains(e.relatedTarget)) s.open = false; });
});
d.addEventListener('click', e => subs.forEach(s => { if (s.open && inline(s) && !s.contains(e.target)) s.open = false; }));
const tops = () => nav ? [...nav.querySelectorAll('.hnav__list > .hnav__item > .hnav__link, .hnav__list > .hnav__item > .hnav__sub > .hnav__link')] : [];
d.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    const open = subs.find(s => s.open && inline(s));
    if (open) { open.open = false; open.querySelector('summary').focus(); }
    return;
  }
  if (!nav || !nav.contains(e.target) || e.altKey || e.ctrlKey || e.metaKey) return;
  const list = tops();
  const i = list.indexOf(e.target);
  const sub = e.target.closest('.hnav__sub') || (e.target.matches('.hnav__link--top') ? e.target.parentElement.querySelector('.hnav__sub') : null);
  const move = el => { if (el) { e.preventDefault(); el.focus(); } };
  const vertical = getComputedStyle(nav).flexDirection === 'column';
  const [next, prev] = vertical ? ['ArrowDown', 'ArrowUp'] : ['ArrowRight', 'ArrowLeft'];
  if (i > -1) {
    if (e.key === next || e.key === prev) { if (!vertical || !sub?.open) { closeSubs(null); move(list[(i + (e.key === next ? 1 : list.length - 1)) % list.length]); } }
    else if (e.key === 'Home' || e.key === 'End') move(list[e.key === 'Home' ? 0 : list.length - 1]);
    else if (e.key === 'ArrowDown' && sub && !vertical) { sub.open = true; move(sub.querySelector('a[href]')); }
  } else if (sub && !vertical) {
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

// Design „Link + Pfeil, öffnet auch beim Überfahren“ (np-hover): nur mit Maus, kurze Verzögerung beim Verlassen
if (html.classList.contains('np-hover') && matchMedia('(hover:hover) and (pointer:fine)').matches) d.querySelectorAll('.hnav__item--split').forEach(li => {
  const s = li.querySelector('.hnav__sub');
  let t = 0, o = 0;
  li.addEventListener('mouseenter', () => { clearTimeout(t); if (inline(s) && !s.open) s.open = o = 1; });
  // Der erste Klick auf den Pfeil nach dem Öffnen per Maus schließt nicht gleich wieder
  s.firstElementChild.addEventListener('click', e => { if (s.open && o) e.preventDefault(); o = 0; });
  li.addEventListener('mouseleave', () => { t = setTimeout(() => { if (inline(s) && !s.contains(d.activeElement)) s.open = false; }, 250); });
});
// Seitenblatt mit „Link + Pfeil“: Unterseiten per Schaltfläche (ohne JavaScript alles sichtbar)
d.querySelectorAll('.mnav__toggle').forEach(t => {
  const box = d.getElementById(t.getAttribute('aria-controls'));
  if (!box) return;
  const set = o => { t.setAttribute('aria-expanded', String(o)); box.hidden = !o; };
  set(t.getAttribute('aria-expanded') === 'true');
  t.addEventListener('click', () => set(t.getAttribute('aria-expanded') !== 'true'));
});

// ------------------------------------------------------------ Hintergrundvideo (nur ohne „Bewegung reduzieren“)
d.querySelectorAll('[data-bg-video]').forEach(v => {
  const box = v.closest('.hero-bleed');
  const t = box?.querySelector('[data-bg-video-toggle]');
  const label = t?.querySelector('span');
  const set = playing => {
    if (!t) return;
    t.setAttribute('aria-pressed', String(!playing));
    label.textContent = playing ? label.dataset.labelPause : label.dataset.labelPlay;
  };
  if (reduce.matches) return;
  v.play().then(() => { if (t) t.hidden = false; set(true); }).catch(() => {});
  t?.addEventListener('click', () => { if (v.paused) { v.play(); set(true); } else { v.pause(); set(false); } });
});

// ------------------------------------------------------------ Website-Suche: Vorschläge beim ersten Fokus laden (≈ 2 KB)
let suggestJs;
d.addEventListener('focusin', e => { const s = e.target.dataset?.suggestJs; s && !suggestJs && (suggestJs = d.head.append(Object.assign(d.createElement('script'), { src: s })) || 1); });

// ------------------------------------------------------------ Fließende Seitenwechsel (View Transitions, CSS @view-transition)
// Jeder Wechsel morpht: angeklicktes Bild → großes Bild der Zielseite („f-morph“), angeklickter Text → Überschrift („f-title“).
// Zurück: Überschrift und großes Bild der Seite gleiten an ihren Platz bzw. in das Bild, das zur vorigen Seite führt.
// Ohne „Animationen“ oder mit „Bewegung reduzieren“: keiner. Browser ohne Seitenübergänge: kurzes Ausblenden.
const vtImg = a => a?.querySelector('img') || a?.closest('li,article,figure,.tile,.mo__t')?.querySelector('img');
const vtSet = (el, name) => { if (el) el.style.viewTransitionName = name; };
const vtClear = () => { d.querySelectorAll('[style*="view-transition-name"]').forEach(el => el.style.viewTransitionName = ''); d.querySelectorAll('.vt-off').forEach(c => c.classList.remove('vt-off')); };
const vtOn = () => html.classList.contains('has-motion') && !reduce.matches && 'onpagereveal' in window;
const vtKey = 'f-vt-from';
const inView = el => { const r = el?.getBoundingClientRect(); return !!r && r.width > 0 && r.bottom > 0 && r.top < innerHeight; };
// Großes Bild der Seite: Titelbild der Serie, sonst das erste sichtbare Bild im Inhalt mit mindestens 30 % der Breite
const bigImg = () => d.querySelector('.sh__cover img') || [...d.querySelectorAll('main img')].find(i => inView(i) && i.getBoundingClientRect().width > innerWidth * .3);
const title = () => [...d.querySelectorAll('main h1, main h2')].find(h => inView(h) && h.getBoundingClientRect().width > 8) || null;
const own = (img, name) => { if (!img) return; if (!img.closest('.sh__cover')) d.querySelectorAll('.sh__cover').forEach(c => c.classList.add('vt-off')); vtSet(img.closest('.sh__cover') ? null : img, name); };
let vtClicked = false;
d.addEventListener('click', e => {
  const a = e.target.closest?.('a[href]');
  if (!vtOn() || !a || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank' || a.origin !== location.origin
    || a.hasAttribute('data-lb') || a.hasAttribute('download') || (a.hash && a.pathname === location.pathname)) return;
  vtClear();
  const img = vtImg(a);
  if (img && img.getBoundingClientRect().width > 8) own(img, 'f-morph');   // unsichtbare Vorschau (Serienliste): Text morphen
  else if (a.textContent.trim().length < 60) vtSet(a, 'f-title');
  vtClicked = true;
});
// Wechsel ohne Klick auf einen Link (Zurück, Vor, Adresse): Überschrift und großes Bild dieser Seite benennen
addEventListener('pageswap', e => {
  if (!e.viewTransition || !vtOn()) return;
  if (!vtClicked) { vtClear(); own(bigImg(), 'f-morph'); vtSet(title(), 'f-title'); }
  vtClicked = false;
});
// Ohne Seitenübergänge im Browser: Inhalt kurz ausblenden, dann wechseln (zurück aus dem Verlauf zeigt die Seite sofort)
if (!('onpagereveal' in window)) d.addEventListener('click', e => {
  const a = e.target.closest?.('a[href]');
  if (!html.classList.contains('has-motion') || reduce.matches || !a || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey
    || a.target === '_blank' || a.origin !== location.origin || a.hasAttribute('data-lb') || a.hasAttribute('download') || (a.hash && a.pathname === location.pathname)) return;
  e.preventDefault();
  html.classList.add('is-leaving');
  setTimeout(() => { location.href = a.href; }, 220);
});
addEventListener('pageshow', e => { html.classList.remove('is-leaving'); if (e.persisted) vtClear(); });
// Neue Seite (pagereveal): js/vt.js im <head> – muss vor dem ersten Bild laufen
