/**
 * Hinweisbalken (Core\Notice): Zeitraum auch bei Treffern im Seiten-Cache einhalten (data-notice-from/-until, ms)
 * und die schwebende Bubble schließen (für die Sitzung gemerkt, je Text/Zeitraum).
 */
(() => {
  const key = (el) => 'cms-notice-closed:' + (el.dataset.noticeId || '');
  const closed = (el) => { try { return el.dataset.noticeId && sessionStorage.getItem(key(el)) === '1'; } catch { return false; } };
  const update = () => {
    const now = Date.now();
    let next = Infinity;
    document.querySelectorAll('[data-notice]').forEach((el) => {
      const from = +el.dataset.noticeFrom || 0, until = +el.dataset.noticeUntil || Infinity;
      el.hidden = now < from || now >= until || closed(el);
      if (from > now) next = Math.min(next, from);
      if (until > now) next = Math.min(next, until);
    });
    // Umschalten zum nächsten Zeitpunkt, solange die Seite offen ist (setTimeout höchstens ~24 Tage)
    if (next !== Infinity && next - now < 2 ** 31 - 1) setTimeout(update, next - now + 50);
  };
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-notice-close]');
    if (!btn) return;
    const el = btn.closest('[data-notice]');
    try { sessionStorage.setItem(key(el), '1'); } catch { /* privates Fenster */ }
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) { el.hidden = true; return; }
    el.classList.add('is-closing');
    el.addEventListener('animationend', () => { el.hidden = true; }, { once: true });
  });
  // Esc schließt eine sichtbare Bubble (die mittige verdeckt Inhalt)
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || e.defaultPrevented) return;
    const btn = document.querySelector('.cms-notice-bubble:not([hidden]) [data-notice-close]');
    if (btn && !document.querySelector('dialog[open]')) btn.click();
  });
  update();
})();
