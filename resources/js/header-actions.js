/*
 * Kopfbereich-Aktionen (Core\HeaderActions) – nur geladen, wenn die Suche im Kopf steht, nie im Bearbeitungsmodus (≈ 1 KB).
 *   „/“ oder ⌘K / Strg+K: ins sichtbare Suchfeld springen bzw. das Such-Popover öffnen (nicht beim Tippen in Feldern/Editoren)
 *   Escape: schließt das Such-Popover sofort (Fokus zurück auf den Auslöser); im Feld leert Escape den Text, dann verlässt
 *   es das Feld (Aufziehen klappt wieder zu). Eine offene Vorschlagsliste schließt vorher search.js.
 *   Tastenhinweis im Befehlsfeld: ⌘K auf Apple-Geräten, sonst „Strg K“.
 * Weitere Teile (nur bei Bedarf in dasselbe Bündel public/assets/ha/{hash}.js gepackt, Core\HeaderActions::head()):
 *   header-actions-status.js  Öffnungsstatus „jetzt geöffnet“ aus den Öffnungszeiten (Zeitzone der Website, Seiten-Cache bleibt gültig)
 *   header-actions-menu.js    Sprachen als Aufklappliste: Escape und Klick daneben schließen
 * Vorschläge beim Tippen: /assets/js/search.js (lädt das Kit beim ersten Fokus, data-suggest-js; Ansage per aria-live).
 */
(() => {
  const d = document, $$ = s => [...d.querySelectorAll(s)], shown = el => el?.getClientRects().length > 0;
  if (!/Mac|iP/.test(navigator.platform)) $$('[data-ha-kbd]').forEach(k => { k.textContent = k.dataset.ctrl + ' K'; });

  const focusSearch = () => {
    const f = $$('[data-ha-field]').find(shown), b = f ? 0 : $$('[data-ha-search] [popovertarget]').find(shown);
    if (f) { f.focus(); f.select(); } else if (b) { const p = d.getElementById(b.getAttribute('popovertarget')); p.matches(':popover-open') || b.click(); p.querySelector('input')?.focus(); }
    return f || b;
  };

  d.addEventListener('keydown', e => {
    const k = e.key, t = e.target, p = t.closest?.('.hsearch__panel');
    if (e.defaultPrevented || e.isComposing || e.altKey) return;
    if (((k === 'k' || k === 'K') && (e.metaKey || e.ctrlKey) && !e.shiftKey && !t.closest?.('[contenteditable]'))
      || (k === '/' && !e.metaKey && !e.ctrlKey && !t.isContentEditable && !/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) {
      focusSearch() && e.preventDefault();
    } else if (k === 'Escape' && p?.matches(':popover-open')) {
      e.preventDefault();
      p.hidePopover();
      $$('[popovertarget="' + p.id + '"]').find(shown)?.focus();
    } else if (k === 'Escape' && t.matches('[data-ha-field]')) {
      t.value ? (t.value = '') : t.blur();
    }
  });
})();
