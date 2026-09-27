/*
 * Frontend-JS „praxis“ (Vanilla, ohne Abhängigkeiten, < 8 KB minifiziert)
 * Reveal · Hero-Themen · Flip-Kontaktkarte · Menü · Scroll-Spy · Karte nach Klick
 * Nachgeladen erst bei Bedarf: Formulare (form.js + css/form.css + Datenschutz-Dialog) beim Umdrehen der Kontaktkarte,
 * Mobilmenü-Stile (css/mnav.css) beim ersten Öffnen, Karten-Modul (map.mjs) beim Klick auf „Karte anzeigen“.
 */

const d = document, root = d.documentElement;
root.classList.replace('no-js', 'js');
// Stylesheet bzw. Skript einmalig nachladen → Promise (löst auch bei Fehlern/Zeitüberschreitung, damit nichts hängt)
const got = {};
const load = u => got[u] ||= new Promise(r => {
  const css = /\.css(\?|$)/.test(u), el = d.createElement(css ? 'link' : 'script');
  css ? (el.rel = 'stylesheet', el.href = u) : el.src = u;
  el.onload = el.onerror = r; setTimeout(r, 2500);
  d.head.append(el);
});
const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
// Übersetzte Texte aus PHP (layout.php → data-l10n), Platzhalter {name}
const L = JSON.parse(root.dataset.l10n || '{}'), T = (k, v) => L[k].replace(/\{(\w+)\}/g, (_, x) => v[x]);

// ------------------------------------------------------------ Reveal beim Scrollen
if (!still && 'IntersectionObserver' in window) {
  const io = new IntersectionObserver(es => es.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
  }), { rootMargin: '0px 0px -8% 0px', threshold: .08 });
  $$('[data-reveal]').forEach(el => {
    const delay = +el.dataset.delay || 0;
    if (delay) el.style.transitionDelay = delay + 'ms';
    io.observe(el);
  });
} else $$('[data-reveal]').forEach(el => el.classList.add('is-in'));

// ------------------------------------------------------------ Begrüßung nach Uhrzeit (Client)
const h = new Date().getHours();
$$('[data-greeting]').forEach(el => el.textContent = el.dataset.greeting.split('|')[h < 11 ? 0 : h < 18 ? 1 : 2]);
const DAYS = L.days;
$$('[data-today]').forEach(el => el.textContent = DAYS[new Date().getDay()]);

// ------------------------------------------------------------ Live-Badge „Jetzt geöffnet“ (Ortszeit der Praxis)
const badges = $$('[data-openb]');
if (badges.length) {
  const cfg = JSON.parse(badges[0].dataset.openb);
  const toMin = t => +t.slice(0, 2) * 60 + +t.slice(3, 5);
  const clock = t => ({ time: t.replace(/^0/, '') });
  const dm = iso => T('date', { dd: iso.slice(8, 10), mm: iso.slice(5, 7) });
  const addDays = (iso, n) => { const x = new Date(iso + 'T12:00:00Z'); x.setUTCDate(x.getUTCDate() + n); return x.toISOString().slice(0, 10); };
  const closedOn = iso => cfg.closedFrom && cfg.closedTo && iso >= cfg.closedFrom && iso <= cfg.closedTo;
  const now = () => {
    const p = {};
    new Intl.DateTimeFormat('en-GB', { timeZone: cfg.tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
      .formatToParts(new Date()).forEach(x => p[x.type] = x.value);
    const iso = `${p.year}-${p.month}-${p.day}`;
    return { iso, dow: new Date(iso + 'T12:00:00Z').getUTCDay(), min: +p.hour * 60 + +p.minute };
  };
  const status = () => {
    const n = now(), today = cfg.days[n.dow] || [];
    if (closedOn(n.iso)) return ['is-holiday', T('holiday', { date: dm(cfg.closedTo) })];
    for (const [a, b] of today) {
      if (n.min >= toMin(a) && n.min < toMin(b)) {
        return toMin(b) - n.min <= 30 ? ['is-soon', T('soon', clock(b))] : ['is-open', T('open', clock(b))];
      }
    }
    const later = today.find(([a]) => toMin(a) > n.min);
    if (later) return ['is-closed', T('later', clock(later[0]))];
    for (let i = 1; i <= 21; i++) {
      const iso = addDays(n.iso, i), dow = (n.dow + i) % 7, seg = cfg.days[dow];
      if (seg && seg.length && !closedOn(iso)) {
        const day = i === 1 ? L.tomorrow : i < 7 ? DAYS[dow] : T('dayDate', { day: DAYS[dow], date: dm(iso) });
        return ['is-closed', T('next', { day, ...clock(seg[0][0]) })];
      }
    }
    return ['is-closed', L.closed];
  };
  const render = () => {
    const [cls, text] = status();
    badges.forEach(b => { b.className = b.className.replace(/\bis-\w+/g, '').trim() + ' ' + cls; b.querySelector('.openb__text').textContent = text; b.hidden = false; });
  };
  render();
  setInterval(render, 60000);
}

// ------------------------------------------------------------ Hero-Themen
$$('[data-hero]').forEach(hero => {
  const slides = $$('.slide', hero), btns = $$('.dots__btn', hero), dots = $('.dots', hero), pause = $('[data-pause]', hero);
  const stage = hero.closest('.hero'), bgs = $$('[data-bg]', stage), video = $('[data-hero-video]', stage);
  // Hintergrundvideo: nur ohne „Bewegung reduzieren“
  if (video && !still) video.play().catch(() => {});
  if (slides.length < 2) return;
  let idx = slides.findIndex(s => s.classList.contains('is-active')), paused = still, hover = false, timer;
  const show = i => {
    slides.forEach((s, n) => {
      const on = n === i;
      s.classList.toggle('is-active', on);
      s.classList.toggle('is-before', !on && (n < i || (i === 0 && n === slides.length - 1)));
      // Das Hauptthema (H1) bleibt immer lesbar – nur die übrigen Themen werden für Screenreader aus-/eingeblendet
      if (!('main' in s.dataset)) s.setAttribute('aria-hidden', on ? 'false' : 'true');
    });
    btns.forEach((b, n) => n === i ? b.setAttribute('aria-current', 'true') : b.removeAttribute('aria-current'));
    bgs.forEach(g => g.classList.toggle('is-active', +g.dataset.bg === i));
    idx = i; restartBar();
  };
  const restartBar = () => {
    dots.classList.remove('is-running');
    if (!paused && !hover) { void dots.offsetWidth; dots.classList.add('is-running'); }
  };
  const start = () => {
    clearInterval(timer);
    timer = setInterval(() => { if (!paused && !hover) show((idx + 1) % slides.length); }, 7000);
    restartBar();
  };
  const setPaused = p => {
    paused = p;
    if (video) p ? video.pause() : still || video.play().catch(() => {});
    pause.textContent = p ? '▶ ' + L.play : '❚❚ ' + L.pause;
    pause.setAttribute('aria-label', p ? L.start : L.stop);
    start();
  };
  btns.forEach((b, n) => b.addEventListener('click', () => { show(n); start(); }));
  pause.addEventListener('click', () => setPaused(!paused));
  const on = () => { hover = true; restartBar(); }, off = () => { hover = false; start(); };
  hero.addEventListener('mouseenter', on); hero.addEventListener('mouseleave', off);
  hero.addEventListener('focusin', on); hero.addEventListener('focusout', off);
  if (still) setPaused(true); else start();
});

// ------------------------------------------------------------ Flip-Kontaktkarte
const card = $('[data-flipcard]');
if (card) {
  const front = $('.card--front', card), back = $('.card--back', card), title = $('[data-flip-title]', card);
  back.hidden = false; back.inert = true; back.setAttribute('aria-hidden', 'true');
  let opener = null;
  // Formular-Stile/-Skripte der Rückseite: vorladen, sobald jemand auf die Karte zeigt oder hineintabbt
  const assets = () => Promise.all(JSON.parse(card.dataset.assets || '[]').map(load));
  card.addEventListener('pointerover', assets, { once: true });
  card.addEventListener('focusin', assets, { once: true });
  const flip = (key, from) => {
    const panel = $(`[data-panel="${key}"]`, card);
    if (!panel) return false;
    assets().then(() => {
      $$('[data-panel]', card).forEach(p => p.hidden = p !== panel);
      title.textContent = panel.dataset.title;
      opener = from;
      card.classList.add('is-flipped');
      front.inert = true; front.setAttribute('aria-hidden', 'true');
      back.inert = false; back.removeAttribute('aria-hidden');
      const form = $('form', panel);
      if (form) window.praxisForm?.(form, true);
      setTimeout(() => $('#flip-title').focus({ preventScroll: true }), still ? 0 : 450);
    });
    return true;
  };
  const unflip = () => {
    card.classList.remove('is-flipped');
    back.inert = true; back.setAttribute('aria-hidden', 'true');
    front.inert = false; front.removeAttribute('aria-hidden');
    (opener && front.contains(opener) ? opener : $('.svc', front))?.focus({ preventScroll: true });
  };
  d.addEventListener('click', e => {
    const t = e.target.closest('[data-flip]');
    if (t && !t.target) {
      if (flip(t.dataset.flip, t)) {
        e.preventDefault();
        closeMenu();
        if (!front.contains(t)) card.scrollIntoView({ behavior: still ? 'auto' : 'smooth', block: 'center' });
      }
    }
    if (e.target.closest('[data-flip-back]')) unflip();
  });
  card.addEventListener('keydown', e => { if (e.key === 'Escape' && card.classList.contains('is-flipped')) unflip(); });
}

// Popover „Alle Öffnungszeiten“ bei Klick außerhalb schließen
d.addEventListener('click', e => $$('details.popover[open]').forEach(p => { if (!p.contains(e.target)) p.open = false; }));

// ------------------------------------------------------------ Mobilmenü
// Modaler <dialog>, geöffnet per command/commandfor (ohne JS). Hier: Fallback für Browser ohne Invoker Commands,
// aria-expanded, Schließen bei Klick auf einen Link (Sprungmarken) und beim Wechsel zur Desktop-Breite.
// Stile (css/mnav.css, data-css) erst beim ersten Öffnen – vorgeladen beim Zeigen/Fokussieren; ohne JS: <noscript>-Link.
const menu = $('#mobilmenu'), closeMenu = () => menu?.open && menu.close();
if (menu) {
  const btn = $('.menu-btn'), css = () => menu.dataset.css ? load(menu.dataset.css) : Promise.resolve();
  btn.addEventListener('pointerover', css, { once: true });
  btn.addEventListener('focus', css, { once: true });
  btn.addEventListener('click', e => { e.preventDefault(); css().then(() => menu.open || menu.showModal()); });
  if (!('commandForElement' in btn)) $('.mnav__close').onclick = () => menu.close();
  menu.ontoggle = e => btn.setAttribute('aria-expanded', e.newState == 'open');
  menu.onclick = e => e.target.closest('a') && menu.close();
  matchMedia('(min-width:1080px)').addEventListener('change', closeMenu);
}

// ------------------------------------------------------------ Scroll-Spy
const spy = $$('[data-spy]');
if (spy.length) {
  const all = $$('main > section[id]');
  let cur = null;
  const onScroll = () => {
    let act = null;
    for (const s of all) if (s.getBoundingClientRect().top < 160) act = s.id;
    // Abschnitte ohne Menüpunkt dem vorherigen Menüpunkt zuordnen
    if (act && !spy.some(a => a.dataset.spy === act)) {
      const i = all.findIndex(s => s.id === act);
      act = null;
      for (let j = i; j >= 0 && !act; j--) if (spy.some(a => a.dataset.spy === all[j].id)) act = all[j].id;
    }
    if (act === cur) return;
    cur = act;
    spy.forEach(a => a.dataset.spy === act ? a.setAttribute('aria-current', 'true') : a.removeAttribute('aria-current'));
  };
  addEventListener('scroll', onScroll, { passive: true }); onScroll();
}


// ------------------------------------------------------------ Website-Suche: Vorschläge (search.js) erst beim ersten Fokus laden
let suggestJs;
d.addEventListener('focusin', e => { const s = e.target.dataset?.suggestJs; s && !suggestJs && (suggestJs = d.head.append(Object.assign(d.createElement('script'), { src: s })) || 1); });

// ------------------------------------------------------------ Karte erst nach Klick (Core\Maps, Zwei-Klick mit Kit-Lader)
d.addEventListener('click', e => {
  const b = e.target.closest('[data-cms-map-load]'), m = b?.closest('[data-cms-map-js]');
  if (m && !b.dataset.bound) { b.dataset.bound = m.dataset.go = 1; m.classList.add('is-started'); import(m.dataset.cmsMapJs); }
});
