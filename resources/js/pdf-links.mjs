/*
 * PDF-Links auf der Website (Core\PdfLinks): Klick öffnet eine Karte mit Vorschau der ersten Seite, Titel, „PDF · Größe“ und
 * den Knöpfen „Ansehen“ (PDF-Viewer) und „Herunterladen“ (Datei) – im Stil des Kits (Klassen aus data-btn / data-btn2).
 * „Ansehen“ öffnet den Viewer in einem Modal (<dialog>, nicht fensterfüllend, damit klar ist: die Seite liegt dahinter).
 * In Slidern ([data-cms-slider]) und Bereichen mit [data-pdf-direct] öffnet der Klick gleich das Modal – ohne Karte.
 * Ohne Skript, mit Strg/⌘/Umschalt oder mittlerer Maustaste gilt das href des Links (Wahl im Linkdialog).
 * Tastatur: Fokus auf „Ansehen“, Tab bleibt in der Karte, Esc schließt und kehrt zum Link zurück.
 */
const d = document;
const cfg = d.querySelector('script[data-cms-pdf-links]')?.dataset || {};
if (cfg.css && !d.querySelector('link[data-cms-pdf-css]')) {
  const l = d.createElement('link'); l.rel = 'stylesheet'; l.href = cfg.css; l.dataset.cmsPdfCss = ''; d.head.append(l);
}
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
let pop = null, opener = null, dlg = null;
const DIRECT = '[data-cms-slider],[data-pdf-direct]';

/** Viewer im Modal (eigene Seite /pdf/ID?embed=1 im iframe); Esc, Klick daneben oder × schließen, Fokus zurück zum Link */
function viewer(a) {
  const x = a.dataset, back = a;
  close(false);
  dlg?.remove();
  dlg = d.createElement('dialog');
  dlg.className = 'cms-pdfdlg';
  dlg.setAttribute('aria-label', x.pdfTitle || a.textContent.trim());
  const src = x.pdfView + (x.pdfView.includes('?') ? '&' : '?') + 'embed=modal';   // Viewer bringt Schließen + Neuer Tab mit
  dlg.innerHTML = `<iframe class="cms-pdfdlg__frame" src="${esc(src)}" title="${esc(x.pdfTitle || 'PDF')}"></iframe>`;
  d.body.append(dlg);
  const shut = () => dlg?.close();
  const onMsg = e => { if (e.origin === location.origin && e.data?.cmsPdf === 'close') shut(); };
  addEventListener('message', onMsg);
  dlg.addEventListener('click', e => { if (e.target === dlg) shut(); });   // Klick auf den abgedunkelten Rand
  dlg.addEventListener('close', () => { removeEventListener('message', onMsg); dlg?.remove(); dlg = null; d.documentElement.classList.remove('cms-pdfdlg-open'); back.focus({ preventScroll: true }); });
  d.documentElement.classList.add('cms-pdfdlg-open');
  dlg.showModal();
  dlg.querySelector('iframe').focus();
}

function close(back) {
  if (!pop) return;
  pop.remove(); pop = null;
  opener?.setAttribute('aria-expanded', 'false');
  if (back) opener?.focus();
  opener = null;
}

function place(a) {
  const r = a.getBoundingClientRect(), w = pop.offsetWidth, h = pop.offsetHeight;
  const below = r.bottom + 10 + h <= innerHeight || r.top - 10 - h < 0;
  pop.style.top = (scrollY + (below ? r.bottom + 10 : r.top - 10 - h)) + 'px';
  pop.style.left = (scrollX + Math.max(12, Math.min(r.left, innerWidth - w - 12))) + 'px';
}

function open(a) {
  close(false);
  opener = a;
  const x = a.dataset, id = 'cms-pdfpop-' + Date.now();
  pop = d.createElement('div');
  pop.className = 'cms-pdfpop'; pop.id = id;
  pop.setAttribute('role', 'dialog');
  pop.setAttribute('aria-label', x.pdfTitle || a.textContent.trim());
  const blank = a.target === '_blank' ? ' target="_blank" rel="noopener"' : '';
  pop.innerHTML = (x.pdfThumb ? `<img class="cms-pdfpop__thumb" src="${esc(x.pdfThumb)}" alt="" width="120" height="160">` : '<span class="cms-pdfpop__thumb cms-pdfpop__thumb--none" aria-hidden="true">PDF</span>')
    + `<div class="cms-pdfpop__body"><p class="cms-pdfpop__title">${esc(x.pdfTitle || a.textContent.trim())}</p>`
    + `<p class="cms-pdfpop__meta"><span class="cms-pdfpop__badge">PDF</span> ${esc(cfg.type || '')}${x.pdfSize ? ' · ' + esc(x.pdfSize) : ''}</p>`
    + `<p class="cms-pdfpop__act"><a class="${esc(cfg.btn || 'btn btn--primary')}" href="${esc(x.pdfView)}"${blank}>${esc(cfg.view || 'Ansehen')}</a>`
    + `<a class="${esc(cfg.btn2 || 'btn btn--secondary')}" href="${esc(x.pdfFile)}" download>${esc(cfg.download || 'Herunterladen')}</a></p></div>`
    + `<button type="button" class="cms-pdfpop__x" aria-label="${esc(cfg.close || 'Schließen')}">×</button>`;
  d.body.append(pop);
  a.setAttribute('aria-expanded', 'true');
  a.setAttribute('aria-controls', id);
  place(a);
  pop.querySelector('a')?.focus({ preventScroll: true });
  pop.querySelector('.cms-pdfpop__x').addEventListener('click', () => close(true));
  pop.querySelector('a[download]').addEventListener('click', () => setTimeout(() => close(false), 0));
  const view = pop.querySelector('a:not([download])');
  view.addEventListener('click', e => { if (e.button || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return; e.preventDefault(); viewer(a); });
  pop.addEventListener('keydown', e => {
    if (e.key === 'Escape') { e.preventDefault(); close(true); }
    else if (e.key === 'Tab') {   // Fokus in der Karte halten
      const f = [...pop.querySelectorAll('a,button')], first = f[0], last = f[f.length - 1];
      if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
}

d.querySelectorAll('a[data-pdf-view]').forEach(a => a.setAttribute('aria-haspopup', 'dialog'));
d.addEventListener('click', e => {
  const a = e.target.closest?.('a[data-pdf-view]');
  if (pop && !pop.contains(e.target) && a !== opener) close(false);
  if (!a || e.button || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || a.closest('[contenteditable="true"]')) return;
  e.preventDefault();
  if (a.closest(DIRECT)) { viewer(a); return; }   // Slider u. Ä.: gleich der Viewer, keine Karte über laufender Bewegung
  if (a === opener) { close(true); return; }
  open(a);
});
addEventListener('resize', () => close(false));
// Inhalt ausgetauscht (Bearbeiten auf der Website, Live-Ansicht): neue Links kennzeichnen
d.addEventListener('cms:content-replaced', () => d.querySelectorAll('a[data-pdf-view]').forEach(a => a.setAttribute('aria-haspopup', 'dialog')));
