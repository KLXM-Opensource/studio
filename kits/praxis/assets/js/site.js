/*
 * Frontend-JS „praxis“ (Vanilla, ohne Abhängigkeiten, < 8 KB minifiziert)
 * Reveal · Hero-Themen · Flip-Kontaktkarte · Formulare (Proof-of-Work) · Menü · Scroll-Spy · Zwei-Klick-Embeds
 */

const d = document, root = d.documentElement;
// Wiederholbare Gruppen: eigenes Skript, erst bei Bedarf geladen
const groupSrc = d.currentScript?.src.replace('site.js', 'group.js');
let groupsLoaded = false;
const loadGroups = () => { if (groupsLoaded || !groupSrc) return; groupsLoaded = true; d.head.append(Object.assign(d.createElement('script'), { src: groupSrc })); };
root.classList.replace('no-js', 'js');
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
      s.setAttribute('aria-hidden', on ? 'false' : 'true');
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
  const flip = (key, from) => {
    const panel = $(`[data-panel="${key}"]`, card);
    if (!panel) return false;
    $$('[data-panel]', card).forEach(p => p.hidden = p !== panel);
    title.textContent = panel.dataset.title;
    opener = from;
    card.classList.add('is-flipped');
    front.inert = true; front.setAttribute('aria-hidden', 'true');
    back.inert = false; back.removeAttribute('aria-hidden');
    const form = $('form', panel);
    if (form) { prepareForm(form); if ($('[data-group]', form)) loadGroups(); }
    setTimeout(() => $('#flip-title').focus({ preventScroll: true }), still ? 0 : 450);
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

// ------------------------------------------------------------ Formulare mit Proof-of-Work
const enc = new TextEncoder();
async function solve(token, bits) {
  if (!bits || !crypto.subtle) return '';
  const full = bits >> 3, rest = bits & 7, mask = rest ? 0xff << (8 - rest) & 0xff : 0;
  for (let n = 0; ; n += 256) {
    const batch = [];
    for (let i = 0; i < 256; i++) batch.push(crypto.subtle.digest('SHA-256', enc.encode(token + ':' + (n + i))));
    const res = await Promise.all(batch);
    for (let i = 0; i < 256; i++) {
      const b = new Uint8Array(res[i]);
      let ok = true;
      for (let j = 0; j < full; j++) if (b[j]) { ok = false; break; }
      if (ok && (!rest || !(b[full] & mask))) return String(n + i);
    }
  }
}

function prepareForm(form) {
  if (form._ready) return form._ready;
  const key = form.dataset.form, tokenEl = form.elements._token, powEl = form.elements._pow;
  const inline = form.elements._difficulty;
  form._ready = (async () => {
    let token = tokenEl.value, diff = inline ? +inline.value : 0;
    if (!token) {
      const r = await fetch(form.action.replace(/\/anfrage\//, '/api/form/'), { headers: { Accept: 'application/json' }, credentials: 'omit' });
      const c = await r.json();
      token = tokenEl.value = c.token; diff = c.difficulty;
    }
    powEl.value = await solve(token, diff);
  })();
  return form._ready;
}

function setError(form, name, msg) {
  // Gruppen: „medikamente.1.medikament“ → Feld medikamente[1][medikament]; „medikamente“ → Fieldset der Gruppe
  const input = form.elements[name.replace(/\.(\w+)/g, '[$1]')] || $(`[data-cf="${name}"]`, form);
  const el = input && d.getElementById(input.id + '-e');
  if (!el) return;
  el.textContent = msg || ''; el.hidden = !msg;
  msg ? input.setAttribute('aria-invalid', 'true') : input.removeAttribute('aria-invalid');
}

$$('form[data-form]').forEach(form => {
  const msg = $('.pform__msg', form), btn = $('[type=submit]', form);
  // Gruppen: auf Formularseiten sofort, in der Flip-Karte erst beim Umdrehen (Startseite bleibt schlank)
  if ($('[data-group]', form)) form.closest('[data-flipcard]') ? form.addEventListener('focusin', loadGroups, { once: true }) : loadGroups();
  form.addEventListener('focusin', () => prepareForm(form), { once: true });
  form.addEventListener('submit', async e => {
    e.preventDefault();
    msg.hidden = true;
    // Clientseitige Prüfung: Fehlermeldung je Feld, Fokus auf erstes fehlerhaftes Feld
    let first = null;
    $$('[data-group]', form).forEach(g => setError(form, g.dataset.group, ''));
    $$('input,select', form).forEach(i => {
      if (!i.name || i.name[0] === '_' || i.closest('.hp')) return;
      const bad = !i.checkValidity();
      setError(form, i.name, bad ? (i.type === 'checkbox' ? L.confirm : L.fill) : '');
      if (bad && !first) first = i;
    });
    if (first) { first.focus(); return; }
    btn.setAttribute('aria-busy', 'true'); btn.disabled = true;
    try {
      await prepareForm(form);
      const r = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'omit' });
      const res = await r.json();
      if (res.ok) {
        form.hidden = true;
        const done = form.nextElementSibling;
        done.hidden = false; done.setAttribute('tabindex', '-1'); done.focus();
        return;
      }
      Object.entries(res.errors || {}).forEach(([n, m]) => setError(form, n, m));
      const bad = $('[aria-invalid=true]', form);
      msg.textContent = res.message || L.check; msg.hidden = false;
      (bad || msg).focus?.();
      if (!res.errors) { form._ready = null; form.elements._token.value = ''; } // Token verbraucht/abgelaufen → neues holen
    } catch {
      msg.textContent = L.failed;
      msg.hidden = false;
    } finally { btn.removeAttribute('aria-busy'); btn.disabled = false; }
  });
});

// ------------------------------------------------------------ Mobilmenü
// Modaler <dialog>, geöffnet per command/commandfor (ohne JS). Hier: Fallback für Browser ohne Invoker Commands,
// aria-expanded, Schließen bei Klick auf einen Link (Sprungmarken) und beim Wechsel zur Desktop-Breite.
const menu = $('#mobilmenu'), closeMenu = () => menu?.open && menu.close();
if (menu) {
  const btn = $('.menu-btn');
  if (!('commandForElement' in btn)) { btn.onclick = () => menu.showModal(); $('.mnav__close').onclick = () => menu.close(); }
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
