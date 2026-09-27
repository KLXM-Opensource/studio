/*
 * Frontend-JS „glas“ (Vanilla, ohne Abhängigkeiten, < 6 KB). Alles funktioniert auch ohne JavaScript:
 * Menü per Container-Query + popover, Aufklappmenüs per <details>, Glasblatt schließt per Escape/Klick daneben.
 * Hier nur Verfeinerungen:
 *  - Einblenden beim Scrollen (einmalig, IntersectionObserver)
 *  - Aurora im Einstieg: pausiert, solange sie nicht im Bild ist (spart Rechenzeit für die Unschärfe darüber)
 *  - Kopf: prüft, ob das Menü wirklich in die Leiste passt (sonst .is-overflow → Menü-Schaltfläche) – ResizeObserver
 *  - Glas-Dock: gleitende Glaslinse im Menü (Zeiger + Tastaturfokus), Verdichten beim Scrollen (IntersectionObserver),
 *    Tab-Leiste auf Telefonen: beim Scrollen nach unten aus-, nach oben einblenden (nie bei „Bewegung reduzieren“)
 *  - Glasblatt: Fokus auf das erste Element, Fokusfalle (Tab), Rest der Seite inert, schließt bei Klick auf einen Link
 *  - Aufklappmenüs: nur eins offen, Klick daneben/Fokus verlassen schließt, Pfeiltasten, Pos1/Ende, Escape
 *  - Such-Popover: an der Lupe ausrichten, wo CSS-Ankerpositionierung fehlt (CSSOM – CSP-fest)
 *  - Öffnungszeiten: heutigen Tag markieren, „Jetzt geöffnet / Zurzeit geschlossen“ (im Browser, nicht im Seiten-Cache)
 *  - Website-Suche: Vorschläge (Kern-Skript search.js) erst beim ersten Fokus eines Suchfelds laden
 */
const d = document;
const html = d.documentElement;
html.classList.replace('no-js', 'js');
const reduce = matchMedia('(prefers-reduced-motion: reduce)');

// ------------------------------------------------------------ Einblenden beim Scrollen (einmalig; nur mit „Animationen“ und ohne „Bewegung reduzieren“ – CSS)
if (html.classList.contains('has-motion') && !reduce.matches && 'IntersectionObserver' in window) {
  const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } }), { rootMargin: '0px 0px -6% 0px' });
  d.querySelectorAll('[data-reveal]').forEach(el => io.observe(el));
} else d.querySelectorAll('[data-reveal]').forEach(el => el.classList.add('is-in'));

// ------------------------------------------------------------ Aurora: nur bewegen, solange sichtbar
if ('IntersectionObserver' in window) {
  const ao = new IntersectionObserver(es => es.forEach(e => e.target.classList.toggle('is-paused', !e.isIntersecting)));
  d.querySelectorAll('[data-aurora]').forEach(el => ao.observe(el));
}

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

// ------------------------------------------------------------ Glasblatt (popover) – geöffnet über „Menü“ oder „Mehr“ (Tab-Leiste)
const sheet = d.querySelector('[data-mnav]');
if (sheet) {
  const btns = [...d.querySelectorAll('[popovertarget="mnav"]:not([popovertargetaction=hide])')];
  let opener = null;
  btns.forEach(b => b.addEventListener('click', () => { opener = b; }));
  const focusables = () => [...sheet.querySelectorAll('a[href],button,input,summary,select,textarea')].filter(el => el.offsetParent !== null || el === d.activeElement);
  const setInert = on => { for (const el of d.body.children) if (el !== sheet && el.tagName !== 'SCRIPT' && !el.matches('.cms-bar-host,dialog')) el.inert = on; };
  // Beim Schließen den Rest der Seite vorher wieder freigeben – sonst kann der Fokus nicht zur Schaltfläche zurück
  sheet.addEventListener('beforetoggle', e => { if (e.newState === 'closed') setInert(false); });
  sheet.addEventListener('toggle', e => {
    const open = e.newState === 'open';
    btns.forEach(b => b.setAttribute('aria-expanded', String(open)));
    sheet.setAttribute('aria-modal', String(open));
    if (open) { setInert(true); (sheet.querySelector('.mnav__nav a[aria-current],.mnav__close') || focusables()[0])?.focus(); }
    else if (!d.activeElement || d.activeElement === d.body) (opener && opener.offsetParent !== null ? opener : btns.find(b => b.offsetParent !== null))?.focus();
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

// ------------------------------------------------------------ Glas-Dock: gleitende Linse, Verdichten, Tab-Leiste
const lensNav = d.querySelector('[data-lens]');
const lens = lensNav?.querySelector('.dnav__lens');
if (lens) {
  const tops = '.hnav__list > .hnav__item > .hnav__link, .hnav__list > .hnav__item > .hnav__sub > .hnav__link';
  const home = () => lensNav.querySelector('.hnav__list > .hnav__item > [aria-current], .hnav__list > .hnav__item > [data-active], .hnav__sub[data-active] > .hnav__link');
  let target = null;
  const place = el => {
    target = el;
    if (!el || !el.offsetWidth) { lens.classList.remove('is-on'); return; }
    const r = el.getBoundingClientRect(), n = lensNav.getBoundingClientRect();
    lens.style.setProperty('--lx', Math.round(r.left - n.left) + 'px');   // CSSOM – CSP-fest (keine style-Attribute im HTML)
    lens.style.setProperty('--lw', Math.round(r.width) + 'px');
    lens.classList.add('is-on');
  };
  lensNav.classList.add('has-lens');
  place(home());
  requestAnimationFrame(() => requestAnimationFrame(() => lens.classList.add('is-ready')));
  lensNav.addEventListener('pointerover', e => { const l = e.target.closest(tops); if (l) place(l); });
  lensNav.addEventListener('pointerleave', () => { if (!lensNav.contains(d.activeElement) || !d.activeElement.matches(':focus-visible')) place(home()); });
  lensNav.addEventListener('focusin', e => { const l = e.target.closest(tops) || e.target.closest('.hnav__sub')?.querySelector('.hnav__link'); if (l) place(l); });
  lensNav.addEventListener('focusout', e => { if (!lensNav.contains(e.relatedTarget)) place(home()); });
  if ('ResizeObserver' in window) new ResizeObserver(() => place(target)).observe(lensNav);
  d.fonts?.ready.then(() => place(target));
}
const dock = d.querySelector('[data-dock]');
if (dock && 'IntersectionObserver' in window) {
  const s = d.createElement('div');
  s.className = 'dock-sentinel';
  s.setAttribute('aria-hidden', 'true');
  d.body.prepend(s);
  new IntersectionObserver(([e]) => dock.classList.toggle('is-condensed', !e.isIntersecting)).observe(s);
}
const tabbar = d.querySelector('[data-tabbar]');
if (tabbar) {
  let lastY = scrollY, ticking = false;
  addEventListener('scroll', () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const y = scrollY;
      const keep = reduce.matches || tabbar.contains(d.activeElement) || sheet?.matches(':popover-open');
      if (keep || y < lastY - 6 || y < 80) tabbar.classList.remove('is-hidden');
      else if (y > lastY + 6) tabbar.classList.add('is-hidden');
      lastY = y;
      ticking = false;
    });
  }, { passive: true });
  tabbar.addEventListener('focusin', () => tabbar.classList.remove('is-hidden'));
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

// ------------------------------------------------------------ Such-Popover an der Lupe (Fallback ohne anchor-name)
if (!(window.CSS && CSS.supports('anchor-name', '--a'))) {
  d.querySelectorAll('.hsearch__panel').forEach(p => {
    const btn = d.querySelector(`[popovertarget="${p.id}"]`);
    const place = () => {
      const r = btn.getBoundingClientRect();
      p.classList.add('is-anchored');
      p.style.top = Math.round(r.bottom + 8) + 'px';
      p.style.right = Math.max(8, Math.round(innerWidth - r.right)) + 'px';
    };
    p.addEventListener('toggle', e => { if (e.newState === 'open') { place(); addEventListener('resize', place); } else removeEventListener('resize', place); });
  });
}

// ------------------------------------------------------------ Öffnungszeiten: heute + Betriebsanzeige
const hours = d.querySelectorAll('[data-hours]');
if (hours.length) {
  const now = new Date();
  const dow = String(now.getDay());
  const min = now.getHours() * 60 + now.getMinutes();
  const m = t => { const [h, i] = t.split(':'); return +h * 60 + +i; };
  let open = false;
  hours.forEach(dl => dl.querySelectorAll('[data-dows]').forEach(row => {
    if (!row.dataset.dows.split(',').includes(dow)) return;
    row.classList.add('is-today');
    open ||= row.dataset.slots.split(',').some(s => { const [a, b] = s.split('-'); return a && b && min >= m(a) && min < m(b); });
  }));
  d.querySelectorAll('[data-openstate]').forEach(el => {
    el.classList.toggle('is-open', open);
    el.querySelector('[data-openstate-text]').textContent = open ? el.dataset.open : el.dataset.closed;
    el.hidden = false;
  });
}

// ------------------------------------------------------------ Website-Suche: Vorschläge beim ersten Fokus laden (≈ 2 KB)
let suggestJs;
d.addEventListener('focusin', e => { const s = e.target.dataset?.suggestJs; s && !suggestJs && (suggestJs = d.head.append(Object.assign(d.createElement('script'), { src: s })) || 1); });
