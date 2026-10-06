/*
 * Frontend-JS „foto“ (Grundlage „fluid“; Vanilla, ohne Abhängigkeiten). Alles funktioniert auch ohne JavaScript:
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

// ------------------------------------------------------------ Kopf zurücknehmen (Design → hrc-hide | hrc-quiet): beim Herunterscrollen weg, beim Hochscrollen da
if (hdr && html.matches('.hrc-hide,.hrc-quiet')) {
  let y0 = scrollY, ticking = false;
  addEventListener('scroll', () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const y = scrollY;
      if (Math.abs(y - y0) > 8) {
        const away = y > y0 && y > hdr.offsetHeight * 2 && !hdr.contains(d.activeElement) && !hdr.querySelector('.hnav__sub[open]');
        hdr.classList.toggle('is-away', away);
        y0 = y;
      }
      ticking = false;
    });
  }, { passive: true });
  hdr.addEventListener('focusin', () => hdr.classList.remove('is-away'));
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
  const sub = e.target.closest('.hnav__sub');
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
