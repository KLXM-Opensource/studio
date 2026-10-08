import { t } from './_i18n.js';
/*
 * Drill-down-Navigation (Mediathek, Daten, Support): Die Hauptnavigation der Seitenleiste wird zur
 * Bereichsnavigation – „‹ Hauptmenü“ blendet das normale Menü ein und wieder aus. Auf schmalen Bildschirmen
 * (≤ 900 px) gilt dasselbe in der Schublade (_drawer.js); zusätzlich stehen die Ansichten der aktuellen
 * Tabelle (Liste, Kalender, Felder …) als Reiterleiste über dem Inhalt (nur schmal sichtbar, admin.css „Mobil“).
 *
 *   drill({ panel, title, label, back, home, box })
 *   panel  Element der Bereichsnavigation
 *   title  Bereichsname über der Navigation („Medien“, „Daten“)
 *   label  aria-label für panel in der Seitenleiste (optional)
 *   back   Beschriftung des Knopfs bei geöffnetem Hauptmenü („Zurück zu Medien“)
 *   home   Container, in dem die Reiterleiste (vorne) steht
 *   box    vorhandenes, serverseitig gerendertes .adm-drill (Layout) – sonst wird es erzeugt
 *   area   Bereichskennung (media, data, support, requests, push, ai) → eigener Farbton der Seitenleiste (admin.css data-area)
 */
export function drill({ panel, title, label = '', back = '', home, box = null, area = '' }) {
  const d = document, body = d.body, side = d.querySelector('.adm-side'), nav = side?.querySelector(':scope > nav');
  if (!nav || !panel || !home) return;
  if (!nav.id) nav.id = 'adm-mainnav';
  if (!box) {
    box = d.createElement('div');
    box.className = 'adm-drill';
    const h = d.createElement('p');
    h.className = 'adm-drill__title';
    h.setAttribute('aria-hidden', 'true');
    h.textContent = title;
    box.append(h);
  }
  // Knopf statt Link (ohne JavaScript führt „‹ Hauptmenü“ zur Übersicht)
  const btn = d.createElement('button');
  btn.type = 'button';
  btn.className = 'adm-drill__back';
  btn.setAttribute('aria-controls', nav.id);
  btn.setAttribute('aria-expanded', 'false');
  const arrow = d.createElement('span');
  arrow.setAttribute('aria-hidden', 'true');
  btn.append(arrow, d.createTextNode(''));
  const label0 = t('Hauptmenü'), label1 = back || t('Hauptmenü');
  const set = open => {
    body.classList.toggle('is-drill-nav', open);
    btn.setAttribute('aria-expanded', String(open));
    btn.lastChild.textContent = open ? label1 + ' ' : ' ' + label0;
    arrow.textContent = open ? '→' : '←';
  };
  set(false);
  const old = box.querySelector('.adm-drill__back');
  old ? old.replaceWith(btn) : box.prepend(btn);
  btn.addEventListener('click', () => set(!body.classList.contains('is-drill-nav')));
  if (box.nextElementSibling !== nav) nav.before(box);
  if (panel.parentElement !== box) box.append(panel);
  if (label) panel.setAttribute('aria-label', label);
  body.classList.add('is-drill');
  if (area) side.dataset.area = area;
  // Bereichsmodus erkennbar machen: Seitenleiste unterhalb der Favoriten leicht getönt (admin.css „--drill-top“)
  const fav = side.querySelector(':scope > .adm-fav');
  if (fav && 'ResizeObserver' in window) {
    const mark = () => side.style.setProperty('--drill-top', Math.round(fav.offsetTop + fav.offsetHeight + (parseFloat(getComputedStyle(side).rowGap) || 0) / 2) + 'px');
    // auch alles darüber beobachten (z. B. die aufgeklappte Website-Auswahl im Netzwerk) – sonst blieb die Linie mitten im Menü stehen
    const ro = new ResizeObserver(mark);
    for (let el = side.firstElementChild; el; el = el.nextElementSibling) { ro.observe(el); if (el === fav) break; }
    side.querySelectorAll(':scope > details').forEach(dt => dt.addEventListener('toggle', mark));
    mark();
  }
  // Schmal: Ansichten der aktuellen Tabelle zusätzlich über dem Inhalt (Kopie der Links, ohne Formulare)
  const links = [...panel.querySelectorAll('.is-open > .dt-nav__sub > li > a')];
  if (links.length > 1 && home) {
    const tabs = d.createElement('nav');
    tabs.className = 'adm-subtabs';
    tabs.setAttribute('aria-label', panel.querySelector('.is-open > .dt-nav__sub')?.getAttribute('aria-label') || title);
    const ul = d.createElement('ul');
    links.forEach(a => { const li = d.createElement('li'); li.append(a.cloneNode(true)); ul.append(li); });
    tabs.append(ul);
    home.prepend(tabs);
  }
}
