/* Kit „fluid“ – fließende Seitenwechsel (View Transitions, design: transitions = morph). Im <head> geladen (nicht defer): pagereveal
   kommt vor dem ersten Bild der Seite. Das Bild einer Karte (Datenliste, Karten) gleitet zum Bild der Detailseite, die Überschrift
   zur Überschrift; zurück in umgekehrter Richtung. Ohne „Animationen“ oder mit „Bewegung reduzieren“: Wechsel übersprungen. */
{
const d = document, html = d.documentElement, key = 'f-vt-from';
const vis = el => { const r = el?.getBoundingClientRect(); return !!r && r.width > 8 && r.bottom > 0 && r.top < innerHeight; };
const set = (el, n) => { if (el) el.style.viewTransitionName = n; };
const imgOf = a => a?.querySelector('img') || a?.closest('li,article,figure,.dl-item,.card')?.querySelector('img');
const detailImg = () => { const i = d.querySelector('main :is(.df-head,.df-profile) .df-img img'); return vis(i) ? i : null; };
const title = () => [...d.querySelectorAll('main h1')].find(vis) || null;
const off = () => !html.classList.contains('has-motion') || matchMedia('(prefers-reduced-motion: reduce)').matches;
// Seite, die verlassen wird: Bild der Karte, die zum Ziel führt, bzw. das Bild der Detailseite
addEventListener('pageswap', e => {
  const t = e.viewTransition, to = e.activation?.entry?.url;
  try { sessionStorage.setItem(key, location.href); } catch {}
  if (!t || !to || off()) return;
  const img = detailImg() || imgOf([...d.querySelectorAll('main a[href]')].find(x => x.href === to && vis(imgOf(x))));
  set(img, 'f-morph');
  set(img && title(), 'f-title');
});
// Neue Seite: Gegenstück benennen
addEventListener('pagereveal', e => {
  const t = e.viewTransition;
  if (!t) return;
  t.ready.catch(() => {});   // abgebrochener Übergang (z. B. schneller Klick) ist kein Fehler
  if (off()) { t.skipTransition(); return; }
  let from = window.navigation?.activation?.from?.url;
  try { from ||= sessionStorage.getItem(key); } catch {}
  const img = detailImg() || (from ? imgOf([...d.querySelectorAll('main a[href]')].find(x => x.href === from && vis(imgOf(x)))) : null);
  const h = img ? title() : null;
  set(img, 'f-morph');
  set(h, 'f-title');
  t.finished.finally(() => { set(img, ''); set(h, ''); });
});
}
