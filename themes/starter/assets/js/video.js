/* Video – Zwei-Klick-Lösung (nur auf Seiten mit dem Block „Video“, theme.php → conditional_css).
   Der Player (iframe) lädt erst nach Klick. „Künftig direkt laden“: Erweiterung consent_kit (window.cmsConsent), sonst
   localStorage je Anbieter – kein Cookie, jederzeit widerrufbar. */
const d = document;
const KEY = p => 'starter-embed-' + p;
const managed = p => window.cmsConsent?.embed(p) ?? null;   // null = keine Einwilligungs-Verwaltung aktiv
const allowed = p => managed(p) ?? (() => { try { return localStorage.getItem(KEY(p)) === '1'; } catch { return false; } })();
const remember = (p, on) => {
  if (managed(p) !== null) return window.cmsConsent.allowEmbed(p, on);
  try { on ? localStorage.setItem(KEY(p), '1') : localStorage.removeItem(KEY(p)); } catch { /* privater Modus */ }
};

function load(el, autoplay) {
  const f = Object.assign(d.createElement('iframe'), {
    src: autoplay ? el.dataset.src : el.dataset.src.replace('autoplay=1', 'autoplay=0'),
    title: el.dataset.title, className: 'vembed__frame', allowFullscreen: true,
    allow: 'autoplay; fullscreen; picture-in-picture; encrypted-media', referrerPolicy: 'strict-origin-when-cross-origin',
  });
  el.replaceChildren(f);
  el.classList.add('is-loaded');
  if (allowed(el.dataset.embed)) {
    const btn = Object.assign(d.createElement('button'), { type: 'button', textContent: el.dataset.tRevoke });
    btn.addEventListener('click', () => { remember(el.dataset.embed, false); location.reload(); });
    const note = Object.assign(d.createElement('p'), { className: 'vembed__revoke' });
    note.append(el.dataset.tDirect + ' ', btn);
    el.after(note);
  }
  if (autoplay) f.focus();
}

d.querySelectorAll('[data-embed]').forEach(el => {
  if (allowed(el.dataset.embed)) return load(el, false);
  el.querySelector('[data-embed-play]')?.addEventListener('click', () => {
    if (el.querySelector('[data-embed-remember]')?.checked) remember(el.dataset.embed, true);
    load(el, true);
  });
});
// Einwilligung über den Cookie-Hinweis erteilt → wartende Videos laden (ohne Autoplay)
d.addEventListener('cms:consent', () => d.querySelectorAll('[data-embed]:not(.is-loaded)').forEach(el => allowed(el.dataset.embed) && load(el, false)));
