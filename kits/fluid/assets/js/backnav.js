/* Kit „fluid“ – Zurück zur Ausgangsseite (design: back_nav = smart). Merkt sich die Titel besuchter Seiten (sessionStorage) und
   ergänzt auf Seiten, die man von einer anderen Seite der Website aus erreicht hat:
   - Detailseiten (.df-back): oben „← Zurück zu {Seite}“; der Link der Vorlage („Alle News“) bleibt daneben ohne Pfeil
     (zusätzliche Funktion). Kam man von genau dieser Liste, bleibt ein Link „← Alle News“.
   - am Ende jeder solchen Seite noch einmal „← Zurück zu {Seite}“.
   „Zurück“ geht im Verlauf zurück: alte Scrollposition, Filter und – mit fließenden Seitenwechseln – das gleitende Bild. */
{
const d = document, key = 'f-nav-titles', here = location.href.split('#')[0];
const clean = u => { try { const x = new URL(u, location.href); return x.origin + x.pathname.replace(/\/$/, '') + x.search; } catch { return ''; } };
let titles = {};
try { titles = JSON.parse(sessionStorage.getItem(key) || '{}'); } catch {}
const own = (d.querySelector('main h1')?.textContent || d.title.split(' | ')[0] || '').trim();
titles[clean(here)] = own;
try { sessionStorage.setItem(key, JSON.stringify(Object.fromEntries(Object.entries(titles).slice(-60)))); } catch {}
let ref = '';
try { const r = new URL(d.referrer); if (r.origin === location.origin && clean(r.href) !== clean(here)) ref = r.href; } catch {}
if (ref) {
  const home = /^\/(en|fr|it|es|nl)?\/?$/.test(new URL(ref).pathname);
  const en = d.documentElement.lang === 'en', name = titles[clean(ref)] || '';
  const label = home ? (en ? 'Back to home' : 'Zurück zur Startseite') : name ? (en ? 'Back to ' : 'Zurück zu ') + name : (en ? 'Back' : 'Zurück');
  const back = e => { if (history.length > 1) { e.preventDefault(); history.back(); } };
  const link = cls => Object.assign(d.createElement('a'), { href: ref, className: cls, textContent: '← ' + label });
  const top = d.querySelector('main .df-back');
  const topA = top?.querySelector('a');
  if (topA && clean(topA.href) === clean(ref)) {
    topA.addEventListener('click', back);   // von genau dieser Liste: Link der Vorlage geht im Verlauf zurück
  } else if (top) {
    if (topA) { topA.textContent = topA.textContent.replace(/^\s*←\s*/, ''); topA.classList.add('df-back__list'); }
    top.classList.add('df-backbar');
    top.prepend(link('df-back__ref'));
  }
  // Am Ende der Seite (vor dem Fußbereich)
  const main = d.querySelector('main');
  if (main) {
    const bar = d.createElement('div');
    bar.className = 'backnav-end wrap';
    bar.append(link('df-back__ref'));
    if (topA && !topA.classList.contains('df-back__list') && clean(topA.href) !== clean(ref)) bar.append(topA.cloneNode(true));
    main.append(bar);
  }
  d.querySelectorAll('a.df-back__ref').forEach(a => a.addEventListener('click', back));
}
}
