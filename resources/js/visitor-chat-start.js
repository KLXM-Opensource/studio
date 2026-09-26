/*
 * Besucher-Chat – Starter (≤ 1 KB, Core\AI\VisitorChat::launcher): zeichnet den Knopf in einen Shadow-Host (Stil:
 * visitor-chat-start.css, < 1 KB) und lädt das eigentliche Chat-Fenster (visitor-chat.mjs + visitor-chat.css) erst beim
 * ersten Klick. Keine Cookies, keine fremden Anfragen, keine Inline-Styles (CSP).
 * Themes heben den Knopf über eigene feste Leisten mit der CSS-Variable --cms-chat-lift (z. B. 76px auf Telefonen).
 */
(() => {
  const d = document, h = d.querySelector('[data-cms-chat]');
  if (!h || h.shadowRoot) return;
  const o = h.dataset, r = h.attachShadow({ mode: 'open' });
  r.innerHTML = '<link rel="stylesheet" href="' + o.start + '"><button type="button" class="l" aria-haspopup="dialog"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg><span></span></button>';
  const b = r.lastChild;
  b.title = o.title;
  b.lastChild.textContent = o.label;
  r.firstChild.onload = () => { h.hidden = false; };
  let w;
  b.addEventListener('click', () => (w ||= import(o.src)).then(x => x.open(h, r, b)));
})();
