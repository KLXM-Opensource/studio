/*
 * Block-Skripte „basis“ – nur auf Seiten mit Reitern oder Video (theme.php → conditional_css).
 *  - Reiter: role=tablist/tab/tabpanel, Pfeiltasten, Pos1/Ende (ohne JavaScript: Inhalte untereinander)
 *  - Video: Zwei-Klick-Lösung – Player erst nach Klick; „künftig direkt laden“ je Anbieter im Browser (widerrufbar)
 */
const d = document;

// ------------------------------------------------------------ Reiter
d.querySelectorAll('[data-tabs]').forEach(box => {
  const list = box.querySelector('[role=tablist]');
  const tabs = [...list.querySelectorAll('[role=tab]')];
  const panels = tabs.map(t => d.getElementById(t.getAttribute('aria-controls')));
  const select = (i, focus) => {
    tabs.forEach((t, k) => {
      const on = k === i;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      panels[k].hidden = !on;
    });
    if (focus) tabs[i].focus();
  };
  panels.forEach(p => { p.setAttribute('role', 'tabpanel'); p.tabIndex = 0; });
  list.hidden = false;
  box.classList.add('is-js');
  const start = Math.max(0, panels.findIndex(p => location.hash && p.id === location.hash.slice(1)));
  select(start, false);
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => select(i, false));
    t.addEventListener('keydown', e => {
      const n = tabs.length;
      const k = { ArrowRight: i + 1, ArrowDown: i + 1, ArrowLeft: i - 1 + n, ArrowUp: i - 1 + n, Home: 0, End: n - 1 }[e.key];
      if (k === undefined) return;
      e.preventDefault();
      select(k % n, true);
    });
  });
});

// ------------------------------------------------------------ Video (Zwei-Klick-Lösung)
const KEY = p => 'basis-embed-' + p;
// Einwilligungs-Verwaltung (Erweiterung consent_kit): window.cmsConsent.embed(p) → true/false, null = nicht verwaltet (dann localStorage)
const cms = p => window.cmsConsent?.embed(p) ?? null;
const allowed = p => cms(p) ?? (() => { try { return localStorage.getItem(KEY(p)) === '1'; } catch { return false; } })();
const remember = (p, on) => { if (cms(p) !== null) { window.cmsConsent.allowEmbed(p, on); return; } try { on ? localStorage.setItem(KEY(p), '1') : localStorage.removeItem(KEY(p)); } catch { /* privat */ } };

function load(el, autoplay) {
  const p = el.dataset.embed;
  const f = d.createElement('iframe');
  f.src = autoplay ? el.dataset.src : el.dataset.src.replace('autoplay=1', 'autoplay=0');
  f.title = el.dataset.title;
  f.className = 'vembed__frame';
  f.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
  f.allowFullscreen = true;
  f.referrerPolicy = 'strict-origin-when-cross-origin';
  el.replaceChildren(f);
  el.classList.add('is-loaded');
  if (allowed(p)) {
    const note = d.createElement('p');
    note.className = 'vembed__revoke';
    const btn = d.createElement('button');
    btn.type = 'button';
    btn.textContent = el.dataset.tRevoke;
    btn.addEventListener('click', () => { remember(p, false); location.reload(); });
    note.append(el.dataset.tDirect + ' ', btn);
    el.after(note);
  }
  if (autoplay) f.focus();
}
d.querySelectorAll('[data-embed]').forEach(el => {
  if (allowed(el.dataset.embed)) { load(el, false); return; }
  el.querySelector('[data-embed-play]')?.addEventListener('click', () => {
    if (el.querySelector('[data-embed-remember]')?.checked) remember(el.dataset.embed, true);
    load(el, true);
  });
});
// Einwilligung über den Cookie-Hinweis (consent_kit) erteilt → wartende Videos laden (ohne Autoplay)
document.addEventListener('cms:consent', () => document.querySelectorAll('[data-embed]:not(.is-loaded)').forEach(el => { if (allowed(el.dataset.embed)) load(el, false); }));
