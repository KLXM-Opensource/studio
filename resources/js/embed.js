/*
 * Zwei-Klick-Lösung für externe Videos (Kern, Core\Embeds::script – lädt nur auf Seiten mit Video, nicht im Bearbeiten-Modus).
 * Markup: Kern-Fragment app/Views/fragments/video-embed.php (.vembed[data-embed]). Der Player (iframe) lädt erst nach Klick.
 * „Künftig direkt laden“ je Anbieter: Einwilligungs-Verwaltung (Erweiterung consent_kit, window.cmsConsent) – sonst im
 * Browser (localStorage, kein Cookie), jederzeit widerrufbar. Einwilligung über den Cookie-Hinweis → Ereignis cms:consent.
 * Ersetzt die früheren Kit-Skripte js/video.js, js/embed.js und den Video-Teil von js/blocks.js; deren Speicher-Schlüssel
 * ({kit}-embed-{anbieter}, mycms-consent-{anbieter}) gelten weiter.
 */
(() => {
  const d = document;
  const KEY = p => 'cms-embed-' + p;
  // Frühere Schlüssel der Kits (basis-embed-youtube, glas-embed-vimeo, mycms-consent-youtube …)
  const legacy = p => { try { return Object.keys(localStorage).filter(k => k !== KEY(p) && (k.endsWith('-embed-' + p) || k === 'mycms-consent-' + p)); } catch { return []; } };
  // Einwilligungs-Verwaltung: window.cmsConsent.embed(p) → true/false, null = nicht verwaltet (dann localStorage)
  const managed = p => window.cmsConsent?.embed(p) ?? null;
  const stored = p => { try { return localStorage.getItem(KEY(p)) === '1' || legacy(p).some(k => localStorage.getItem(k) === '1'); } catch { return false; } };
  const allowed = p => managed(p) ?? stored(p);
  const remember = (p, on) => {
    if (managed(p) !== null) { window.cmsConsent.allowEmbed(p, on); return; }
    try {
      if (on) localStorage.setItem(KEY(p), '1');
      else [KEY(p), ...legacy(p)].forEach(k => localStorage.removeItem(k));
    } catch { /* privater Modus */ }
  };

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
  d.addEventListener('cms:consent', () => d.querySelectorAll('[data-embed]:not(.is-loaded)').forEach(el => { if (allowed(el.dataset.embed)) load(el, false); }));
})();
