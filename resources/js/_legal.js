/*
 * Rechtstexte im Dialog (Core\LegalDialog): Links mit data-legal-src öffnen den Text über der Seite statt in einem
 * neuen Tab – Eingaben im Formular bleiben erhalten. Ohne <dialog>, mit Strg/⌘-Klick oder bei Fehlern: normaler Link.
 */
export function legalDialogs() {
  if (window.__klxmLegal) return;
  window.__klxmLegal = true;
  // Mit Skript öffnet der Link einen Dialog statt eines neuen Tabs – Hinweis für Screenreader anpassen
  document.querySelectorAll('a[data-legal-src]').forEach(a => {
    a.setAttribute('aria-haspopup', 'dialog');
    a.querySelector('.sr-only, .sf-sr')?.remove();
  });
  document.addEventListener('click', async e => {
    const a = e.target.closest?.('a[data-legal-src]');
    if (!a || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || !window.HTMLDialogElement) return;
    e.preventDefault();
    if (a.dataset.legalCss && !document.querySelector('link[data-legal-css]')) {
      document.head.append(Object.assign(document.createElement('link'), { rel: 'stylesheet', href: a.dataset.legalCss }));
      document.head.lastElementChild.dataset.legalCss = '';
    }
    let d;
    try {
      const r = await fetch(a.dataset.legalSrc, { headers: { Accept: 'application/json' } });
      d = r.ok ? await r.json() : null;
    } catch { d = null; }
    if (!d?.ok) { window.open(a.href, a.target || '_self', 'noopener'); return; }
    const dlg = document.createElement('dialog');
    dlg.className = 'ldlg';
    dlg.setAttribute('aria-labelledby', 'ldlg-title');
    dlg.innerHTML = '<div class="ldlg__bar"><h2 class="ldlg__title" id="ldlg-title"></h2><button type="button" class="ldlg__close"><span aria-hidden="true">×</span></button></div>'
      + '<div class="ldlg__body"></div><p class="ldlg__foot"><a target="_blank" rel="noopener"></a></p>';
    dlg.querySelector('.ldlg__title').textContent = d.title;
    dlg.querySelector('.ldlg__close').setAttribute('aria-label', d.close);
    dlg.querySelector('.ldlg__body').innerHTML = d.html;   // serverseitig bereinigt (Core\Sanitizer), gleiche Herkunft
    const link = dlg.querySelector('.ldlg__foot a');
    link.href = d.url;
    link.textContent = d.open;
    dlg.querySelector('.ldlg__close').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', ev => { if (ev.target === dlg) dlg.close(); });   // Klick neben den Dialog
    dlg.addEventListener('close', () => { dlg.remove(); a.focus(); });
    document.body.append(dlg);
    dlg.showModal();
    dlg.querySelector('.ldlg__body').focus({ preventScroll: true });
  });
}
