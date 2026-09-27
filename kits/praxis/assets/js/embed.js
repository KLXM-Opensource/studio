/*
 * Externe Videos (YouTube/Vimeo) mit Zwei-Klick-Lösung.
 * Bis zur Einwilligung wird nichts vom Anbieter geladen – das Vorschaubild liegt lokal.
 * „Künftig direkt laden“ speichert die Einwilligung je Anbieter im Browser (widerrufbar).
 */
const KEY = p => 'mycms-consent-' + p;
// Einwilligungs-Verwaltung (Erweiterung consent_kit): window.cmsConsent.embed(p) → true/false, null = nicht verwaltet (dann localStorage)
const cms = p => window.cmsConsent?.embed(p) ?? null;
const allowed = p => cms(p) ?? (() => { try { return localStorage.getItem(KEY(p)) === '1'; } catch { return false; } })();
const remember = (p, on) => { if (cms(p) !== null) { window.cmsConsent.allowEmbed(p, on); return; } try { on ? localStorage.setItem(KEY(p), '1') : localStorage.removeItem(KEY(p)); } catch {} };

function load(el, autoplay) {
  const p = el.dataset.embed;
  const f = document.createElement('iframe');
  f.src = autoplay ? el.dataset.src : el.dataset.src.replace('autoplay=1', 'autoplay=0');
  f.title = el.dataset.title;
  f.className = 'vembed__frame';
  f.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
  f.allowFullscreen = true;
  f.referrerPolicy = 'strict-origin-when-cross-origin';
  el.replaceChildren(f);
  el.classList.add('is-loaded');
  if (allowed(p)) {
    const note = document.createElement('p');
    note.className = 'vembed__revoke';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = el.dataset.tRevoke;
    btn.addEventListener('click', () => { remember(p, false); location.reload(); });
    note.append(el.dataset.tDirect + ' ', btn);
    el.after(note);
  }
  if (autoplay) f.focus();
}

document.querySelectorAll('[data-embed]').forEach(el => {
  if (allowed(el.dataset.embed)) { load(el, false); return; }
  el.querySelector('[data-embed-play]')?.addEventListener('click', () => {
    if (el.querySelector('[data-embed-remember]')?.checked) remember(el.dataset.embed, true);
    load(el, true);
  });
});
// Einwilligung über den Cookie-Hinweis (consent_kit) erteilt → wartende Videos laden (ohne Autoplay)
document.addEventListener('cms:consent', () => document.querySelectorAll('[data-embed]:not(.is-loaded)').forEach(el => { if (allowed(el.dataset.embed)) load(el, false); }));
