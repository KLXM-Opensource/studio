/*
 * Kern-Block „Partner & Logos“: Sprung-Links der Logos werden zu Schaltflächen (aria-expanded/aria-controls), die Details
 * erscheinen unter der Reihe des Logos (data-cms-partners="inline", immer nur eines offen) oder im Dialog ("dialog").
 * Esc und „Schließen“ schließen und geben den Fokus an das Logo zurück. Außerdem: zufällige Reihenfolge (data-shuffle)
 * und dunklen Hintergrund messen (data-invert → .is-dark-bg; die serverseitige Klasse --on-dark gilt nur ohne JavaScript).
 * Ohne JavaScript bleiben die Sprung-Links und die Liste mit allen Angaben unter den Logos.
 */
const d = document;
const x = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>';

d.querySelectorAll('[data-cms-partners]').forEach(root => {
  const mode = root.dataset.cmsPartners, store = root.querySelector('[data-partners-store]');
  if (root.hasAttribute('data-shuffle')) {
    root.querySelectorAll('.cms-partners__grid').forEach(ul => {
      const li = [...ul.children];
      for (let i = li.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [li[i], li[j]] = [li[j], li[i]]; }
      ul.append(...li);
    });
  }
  root.classList.add('is-ready');
  if (root.hasAttribute('data-invert')) darkWatch(root);
  if (!store || mode === 'off') return;
  const close = root.dataset.close || 'Schließen';
  root.classList.add('is-enhanced');

  // Sprung-Link → Schaltfläche (Name = Alternativtext des Logos)
  const buttons = [...root.querySelectorAll('a[data-partner]')].map(a => {
    const b = d.createElement('button');
    b.type = 'button'; b.className = a.className; b.dataset.partner = a.dataset.partner;
    b.setAttribute('aria-expanded', 'false'); b.setAttribute('aria-controls', a.dataset.partner);
    b.append(...a.childNodes); a.replaceWith(b);
    const p = d.getElementById(a.dataset.partner);
    if (p) {
      p.hidden = true; p.setAttribute('role', 'region'); p.setAttribute('aria-labelledby', p.id + '-name');
      const c = d.createElement('button');
      c.type = 'button'; c.className = 'cms-partners__close'; c.innerHTML = x; c.setAttribute('aria-label', close);
      c.addEventListener('click', () => hide(true));
      p.append(c);
    }
    return b;
  });
  let open = null, slot = null, dlg = null;

  const place = () => {
    // Details hinter das letzte Logo derselben Reihe setzen (Reihe = gleiche Oberkante)
    if (!open || mode !== 'inline') return;
    slot?.remove();
    const li = open.btn.closest('li'); let last = li;
    for (let n = li.nextElementSibling; n && n.offsetTop === li.offsetTop; n = n.nextElementSibling) last = n;
    slot ??= Object.assign(d.createElement('li'), { className: 'cms-partners__slot' });
    slot.setAttribute('role', 'none');
    slot.append(open.panel); last.after(slot);
  };
  const hide = focus => {
    if (!open) return;
    const { btn, panel } = open; open = null;
    btn.setAttribute('aria-expanded', 'false'); panel.hidden = true; store.append(panel);
    if (dlg?.open) dlg.close();
    slot?.remove();
    if (focus) btn.focus();
  };
  const show = btn => {
    const panel = d.getElementById(btn.dataset.partner);
    if (!panel) return;
    hide(false);
    open = { btn, panel }; btn.setAttribute('aria-expanded', 'true'); panel.hidden = false;
    if (mode === 'dialog') {
      if (!dlg) {
        dlg = d.createElement('dialog');
        dlg.className = 'cms-partners-dialog ' + [...root.classList].filter(c => /^cms-partners--(t-|tile-|invert)/.test(c)).join(' ');
        dlg.addEventListener('close', () => { const b = open?.btn; hide(false); b?.focus(); });
        dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });   // Klick auf den Hintergrund
        d.body.append(dlg);
      }
      dlg.setAttribute('aria-labelledby', panel.id + '-name');
      dlg.append(panel); dlg.showModal();
      dlg.classList.toggle('is-dark-bg', dark(getComputedStyle(dlg).backgroundColor));   // Dialog trägt die Seitenfarben
      panel.querySelector('.cms-partners__close')?.focus();
    } else {
      place();
      (matchMedia('(prefers-reduced-motion:reduce)').matches ? panel : slot).scrollIntoView?.({ block: 'nearest', behavior: 'smooth' });
    }
  };
  buttons.forEach(b => b.addEventListener('click', () => (open?.btn === b ? hide(false) : show(b))));
  root.addEventListener('keydown', e => { if (e.key === 'Escape' && open && mode === 'inline') { e.preventDefault(); hide(true); } });
  let raf = 0;
  addEventListener('resize', () => { cancelAnimationFrame(raf); raf = requestAnimationFrame(place); });
});

/** Helligkeit einer berechneten Farbe (rgb/rgba) → dunkel? */
function dark(c) {
  const [r, g, b] = (c.match(/[\d.]+/g) || [255, 255, 255]).map(Number);
  return .2126 * r + .7152 * g + .0722 * b < 110;
}

/** Dunkler Hintergrund hinter dem Block (Farbschema des Kits)? → .is-dark-bg */
function darkWatch(root) {
  const check = () => {
    let el = root, c = '';
    while (el) {
      c = getComputedStyle(el).backgroundColor;
      const m = c.match(/[\d.]+/g);
      if (m && (m.length < 4 || +m[3] > .5)) break;
      el = el.parentElement; c = '';
    }
    // gemessene Helligkeit gilt vor der Angabe des Kits (z. B. Akzent-Band, das im dunklen Farbschema hell wird)
    const isDark = dark(c);
    root.classList.toggle('is-dark-bg', isDark);
    root.classList.toggle('cms-partners--on-dark', isDark);
  };
  check();
  matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', () => requestAnimationFrame(check));
  new MutationObserver(() => requestAnimationFrame(check)).observe(d.documentElement, { attributes: true, attributeFilter: ['class', 'data-scheme'] });
}
