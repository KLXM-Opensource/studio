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
  // → [Klasse, Text des Status-Chips, „Wir öffnen wieder …“ (nur geschlossen)]
  const status = () => {
    const n = now(), today = cfg.days[n.dow] || [];
    const hol = closedOn(n.iso);
    if (!hol) for (const [a, b] of today) {
      if (n.min >= toMin(a) && n.min < toMin(b)) {
        return toMin(b) - n.min <= 30 ? ['is-soon', T('soon', clock(b))] : ['is-open', T('open', clock(b))];
      }
    }
    const later = !hol && today.find(([a]) => toMin(a) > n.min);
    if (later) return ['is-closed', T('later', clock(later[0])), T('reopen', clock(later[0]))];
    for (let i = 1; i <= 42; i++) {
      const iso = addDays(n.iso, i), dow = (n.dow + i) % 7, seg = cfg.days[dow];
      if (seg && seg.length && !closedOn(iso)) {
        const day = i === 1 ? L.tomorrow : i < 7 ? DAYS[dow] : T('dayDate', { day: DAYS[dow], date: dm(iso) }), t = clock(seg[0][0]);
        return [hol ? 'is-holiday' : 'is-closed', hol ? T('holiday', { date: dm(cfg.closedTo) }) : T('next', { day, ...t }),
          T('reopenDay', { day: i === 1 ? day : T('onDay', { day }), ...t })];
      }
    }
    return [hol ? 'is-holiday' : 'is-closed', hol ? T('holiday', { date: dm(cfg.closedTo) }) : L.closed, ''];
  };
  const render = () => {
    const [cls, text, again] = status(), shut = cls == 'is-closed' || cls == 'is-holiday';
    badges.forEach(b => {
      b.className = b.className.replace(/\bis-\w+/g, '').trim() + ' ' + cls;
      // In der Kontaktkarte steht „Wir öffnen wieder …“ groß darunter – der Chip sagt dann nur „Geschlossen“
      $('.openb__text', b).textContent = cls == 'is-closed' && b.closest('[data-flipcard]') ? L.shut : text;
      b.hidden = false;
    });
    // Kontaktkarte: geschlossen → „Wir öffnen wieder …“ + Bereitschaftsdienst/Notruf statt der heutigen Zeiten
    // (beide Varianten stehen im Markup im selben Rasterfeld – kein Sprung, die Karte bleibt gleich hoch)
    $$('[data-flipcard]').forEach(c => {
      c.dataset.now = shut ? 'closed' : 'open';
      $$('[data-now-open]', c).forEach(x => x.hidden = shut);
      $$('[data-now-closed]', c).forEach(x => x.hidden = !shut);
      $$('[data-reopen]', c).forEach(x => { x.textContent = again; x.parentNode.classList.toggle('is-empty', !again); });
    });
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
// 3D-Drehung (CSS, 600 ms). Die Karte behält im Seitenfluss die Höhe der Vorderseite (nichts verschiebt sich); eine längere
// Rückseite (Formular) liegt als eigene Ebene darüber, ragt nach unten über den folgenden Inhalt und zieht synchron zur
// Drehung weich auf (Höhe der Rückseite: Vorderseite → eigene Höhe, beim Zurückdrehen umgekehrt).
// Fokus: nach dem Seitenwechsel (Mitte der Drehung) auf die Überschrift der Rückseite, beim Zurückdrehen auf die auslösende Kachel.
// Externe Dienste (Doctolib …): Die Rückseite fragt „Sie verlassen unsere Website …“ – „Weiter“ öffnet den Link, „Abbrechen“/Esc dreht zurück.
const card = $('[data-flipcard]');
if (card) {
  const front = $('.card--front', card), back = $('.card--back', card), title = $('[data-flip-title]', card);
  back.hidden = false; back.inert = true; back.setAttribute('aria-hidden', 'true');
  let opener = null, timer;
  // Fokus, sobald die Seite sichtbar ist (Mitte der Drehung) – vorher ist sie nicht fokussierbar, daher kurz nachfassen
  const focusSoon = el => {
    let n = 0;
    const go = () => { el?.focus({ preventScroll: true }); if (el && d.activeElement !== el && n++ < 12) timer = setTimeout(go, 50); };
    clearTimeout(timer);
    timer = setTimeout(go, still ? 0 : 300);
  };
  const hgt = el => el.getBoundingClientRect().height;   // genau (Bruchteile), sonst springt die Rückseite am Ende um < 1 px
  // Höhe der Rückseite animieren (px → px), danach wieder „auto“ (Fehlermeldungen, Danke-Text passen sich an)
  const size = (from, to) => {
    if (still || Math.abs(from - to) < 2) return;
    card.classList.add('is-turning');
    back.style.height = from + 'px'; void back.offsetHeight;
    back.style.height = to + 'px';
  };
  card.addEventListener('transitionend', e => {
    if (e.target == back && e.propertyName == 'height') { back.style.height = ''; card.classList.remove('is-turning'); }
  });
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
      back.style.height = '';
      const fh = hgt(front), bh = hgt(back);
      card.classList.add('is-flipped');
      size(fh, bh);
      front.inert = true; front.setAttribute('aria-hidden', 'true');
      back.inert = false; back.removeAttribute('aria-hidden');
      const form = $('form', panel);
      if (form) window.praxisForm?.(form, true);
      focusSoon($('#flip-title'));
    });
    return true;
  };
  const unflip = () => {
    if (!card.classList.contains('is-flipped')) return;
    size(hgt(back), hgt(front));
    card.classList.remove('is-flipped');
    back.inert = true; back.setAttribute('aria-hidden', 'true');
    front.inert = false; front.removeAttribute('aria-hidden');
    focusSoon(opener && front.contains(opener) ? opener : $('.svc', front));
  };
  d.addEventListener('click', e => {
    const t = e.target.closest('[data-flip]');
    // Strg/Umschalt/Cmd-Klick: Link wie gewohnt (neuer Tab), keine Drehung
    if (t && !(e.metaKey || e.ctrlKey || e.shiftKey) && flip(t.dataset.flip, t)) {
      e.preventDefault();
      closeMenu();
      if (!front.contains(t)) card.scrollIntoView({ behavior: still ? 'auto' : 'smooth', block: 'start' });
    }
    if (e.target.closest('[data-flip-back]')) unflip();
    if (e.target.closest('[data-leave]')) setTimeout(unflip, 400);   // externer Dienst öffnet im neuen Tab – Karte zurückdrehen
  });
  // Esc: auch wenn der Fokus (noch) nicht in der Karte liegt, z. B. direkt nach dem Umdrehen
  d.addEventListener('keydown', e => { if (e.key === 'Escape' && card.classList.contains('is-flipped') && (card.contains(d.activeElement) || d.activeElement === d.body)) unflip(); });
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
