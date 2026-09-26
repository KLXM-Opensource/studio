/* Video – Zwei-Klick-Lösung (nur auf Seiten mit dem Block): Player erst nach Klick; „künftig direkt laden“ je Anbieter
   im Browser gespeichert (localStorage, kein Cookie) und jederzeit widerrufbar. */
const d = document;
const KEY = p => 'glas-embed-' + p;
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
