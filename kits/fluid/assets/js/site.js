/*
 * Frontend-JS „fluid“ (Vanilla, ohne Abhängigkeiten). Alles funktioniert auch ohne JavaScript:
 * Menü per Container-Query + popover, Aufklappmenüs per <details>, Seitenblatt schließt per Escape/Klick daneben.
 * Hier nur Verfeinerungen:
 *  - Einblenden beim Scrollen (einmalig, IntersectionObserver)
 *  - Kopf: prüft, ob das Menü wirklich in die Leiste passt (sonst .is-overflow → Menü-Schaltfläche) – ResizeObserver
 *  - Seitenblatt: Fokus auf das erste Element, Fokusfalle (Tab), Rest der Seite inert, schließt bei Klick auf einen Link
 *  - Aufklappmenüs: nur eins offen, Klick daneben/Fokus verlassen schließt, Pfeiltasten, Pos1/Ende, Escape
 *  - Seitenleiste mit Menübaum: Zweige außerhalb der aktuellen Seite zuklappen, Schaltflächen mit aria-expanded
 *  - Hintergrundvideo (Einstieg vollflächig): startet nur ohne „Bewegung reduzieren“, mit Pause-Schaltfläche
 *  - Website-Suche: Vorschläge (Kern-Skript search.js) erst beim ersten Fokus eines Suchfelds laden
 */
const d = document;
const html = d.documentElement;
html.classList.replace('no-js', 'js');
const reduce = matchMedia('(prefers-reduced-motion: reduce)');

// Fließtext „Absätze nacheinander“ (Feld reveal): jeder Absatz, jede Liste, jedes Bild einzeln einblenden
d.querySelectorAll('[data-reveal-children]').forEach(p => { for (const c of p.children) c.setAttribute('data-reveal', ''); });
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
    // nur sichtbare Einträge (Akkordeon/Slide: zugeklappte Ebenen auslassen)
    const links = [...sub.querySelectorAll('a[href]')].filter(a => a.checkVisibility ? a.checkVisibility() : a.getClientRects().length);
    const j = links.indexOf(e.target);
    const top = sub.querySelector('summary');
    // Akkordeon/Slide (<details class="hnav__det">): → öffnet die Unterebene, ← schließt sie und springt zum Elterneintrag
    const li = e.target.closest('li'), det = li?.querySelector(':scope>.hnav__det'), up = e.target.closest('.hnav__det');
    if (e.key === 'ArrowRight' && det) { det.open = true; move(det.querySelector('a[href]')); return; }
    if (e.key === 'ArrowLeft' && up) { up.open = false; move(up.parentElement.querySelector('a[href]')); return; }
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

// ------------------------------------------------------------ Seitenleiste mit Menübaum: Zweige auf-/zuklappen
// Ohne JavaScript ist der ganze Baum sichtbar; hier klappen die Zweige außerhalb der aktuellen Seite zu (nicht bei st-open)
if (!html.classList.contains('st-open')) d.querySelectorAll('.snav__toggle').forEach(t => {
  const list = d.getElementById(t.getAttribute('aria-controls'));
  if (!list) return;
  const set = open => { t.setAttribute('aria-expanded', String(open)); list.hidden = !open; };
  set(t.getAttribute('aria-expanded') === 'true');
  t.addEventListener('click', () => set(t.getAttribute('aria-expanded') !== 'true'));
  t.addEventListener('keydown', e => { if (e.key === 'Escape' && t.getAttribute('aria-expanded') === 'true') { set(false); e.stopPropagation(); } });
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

// Kopfbereich „hoch“ (design: header_height): beim Scrollen html.is-scrolled → Kopf und Logo wieder normal (Übergang in _options.css)
if (/\bhh-(x?tall)\b/.test(html.className)) {
  let on = null;
  // Schwelle mit Abstand (ein ab 80 px, aus unter 16 px), damit der schrumpfende Kopf nicht flackert
  const upd = () => { const s = on ? scrollY > 16 : scrollY > 80; if (s !== on) { on = s; html.classList.toggle('is-scrolled', s); } };
  addEventListener('scroll', upd, { passive: true });
  upd();
}

// „Nach oben“ (design: totop): sichtbar ab 600 px Scrollweg; Klick scrollt sanft nach oben und setzt den Fokus auf „Zum Inhalt springen“
const totop = d.querySelector('[data-totop]');
if (totop) {
  const vis = () => totop.classList.toggle('is-on', scrollY > 600);
  addEventListener('scroll', vis, { passive: true });
  vis();
  totop.addEventListener('click', e => {
    e.preventDefault();
    scrollTo({ top: 0, behavior: reduce.matches ? 'auto' : 'smooth' });
    d.getElementById('top')?.focus({ preventScroll: true });
  });
}

// Scrollspy im Inhaltsverzeichnis (Fließtext „Artikel“, Feld scrollspy): aktueller Abschnitt → aria-current="location", Markierung
// gleitet dorthin (CSS-Variablen per JS – keine Inline-Styles im HTML), „progress“: Lesefortschritt als Linie. Ein Abschnitt gilt als
// aktuell, sobald seine Überschrift das obere Drittel des Fensters erreicht hat.
d.querySelectorAll('[data-scrollspy]').forEach(toc => {
  const list = toc.querySelector('.toc__list');
  const pairs = [...toc.querySelectorAll('.toc__item a[href^="#"]')].map(a => [a, d.getElementById(decodeURIComponent(a.hash.slice(1)))]).filter(p => p[1]);
  const links = pairs.map(p => p[0]), heads = pairs.map(p => p[1]);
  const body = toc.closest('.article__body')?.querySelector('.prose');
  if (!list || !heads.length) return;
  const marker = d.createElement('span');
  marker.className = 'toc__marker'; marker.setAttribute('aria-hidden', 'true');
  list.prepend(marker);
  let cur = -1, raf = 0;
  const update = () => {
    raf = 0;
    const line = innerHeight / 3;
    let i = -1;
    heads.forEach((h, k) => { if (h.getBoundingClientRect().top <= line) i = k; });
    if (body && toc.classList.contains('toc--spy-progress')) {
      const r = body.getBoundingClientRect();
      const p = Math.min(1, Math.max(0, (line - r.top) / Math.max(1, r.height - line)));
      list.style.setProperty('--toc-p', p.toFixed(3));
    }
    if (i === cur) return;
    cur = i;
    links.forEach((a, k) => { if (k === i) a.setAttribute('aria-current', 'location'); else a.removeAttribute('aria-current'); });
    const a = links[i];
    if (!a) { marker.classList.remove('is-on'); return; }
    const li = a.closest('.toc__item');
    marker.style.setProperty('--toc-y', li.offsetTop + 'px');
    marker.style.height = li.offsetHeight + 'px';
    marker.classList.add('is-on');
    // langes Inhaltsverzeichnis: aktuellen Eintrag sichtbar halten
    if (toc.scrollHeight > toc.clientHeight) {
      const top = li.offsetTop - toc.clientHeight / 3;
      toc.scrollTo({ top, behavior: reduce.matches ? 'auto' : 'smooth' });
    }
  };
  addEventListener('scroll', () => { raf ||= requestAnimationFrame(update); }, { passive: true });
  addEventListener('resize', () => { cur = -1; raf ||= requestAnimationFrame(update); }, { passive: true });
  update();
});
