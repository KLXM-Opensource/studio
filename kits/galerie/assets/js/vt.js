/* Kit „galerie“ – neue Seite eines fließenden Wechsels (View Transitions). Im <head> geladen (nicht defer): pagereveal kommt vor dem
   ersten Bild der Seite, site.js wäre zu spät. Benennt Überschrift („f-title“) und großes Bild („f-morph“) bzw. – zurück zu einer
   Übersicht – das Bild, das zur vorigen Seite führt. Ohne „Animationen“ oder mit „Bewegung reduzieren“: Wechsel übersprungen. */
{
const d = document, html = d.documentElement, key = 'f-vt-from';
addEventListener('pagehide', () => { try { sessionStorage.setItem(key, location.href); } catch {} });
addEventListener('pagereveal', e => {
  const t = e.viewTransition;
  if (!t) return;
  t.ready.catch(() => {});   // abgebrochener Übergang (z. B. schneller Klick) ist kein Fehler
  if (!html.classList.contains('has-motion') || matchMedia('(prefers-reduced-motion: reduce)').matches) { t.skipTransition(); return; }
  const vis = el => { const r = el?.getBoundingClientRect(); return !!r && r.width > 8 && r.bottom > 0 && r.top < innerHeight; };
  const set = (el, n) => { if (el) el.style.viewTransitionName = n; };
  const imgOf = a => a?.querySelector('img') || a?.closest('li,article,figure,.tile,.mo__t')?.querySelector('img');
  let from = window.navigation?.activation?.from?.url;
  try { from ||= sessionStorage.getItem(key); } catch {}
  const cover = d.querySelector('.sh__cover');   // Titelbild der Serie: Name kommt aus dem CSS
  let img = null;
  if (from && !cover) img = imgOf([...d.querySelectorAll('a[href]')].find(x => x.href === from && vis(imgOf(x))));
  if (!img && !cover) img = [...d.querySelectorAll('main img')].find(i => vis(i) && i.getBoundingClientRect().width > innerWidth * .3);
  set(img, 'f-morph');
  const h = [...d.querySelectorAll('main h1, main h2')].find(vis) || null;   // erste sichtbare Überschrift (H1 kann nur für Screenreader sein)
  set(h, 'f-title');
  t.finished.finally(() => { set(img, ''); set(h, ''); });
});
}
