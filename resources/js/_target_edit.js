/**
 * „Ziel bearbeiten“ an Karten und Kacheln (Core\TargetEdit, edit_link() / $b->targetEdit()) – <a class="cms-target-edit" data-cms-target>.
 *
 *  - Eintrag (data-entry-edit): öffnet die Seitenleiste „Eintrag bearbeiten“ an Ort und Stelle – das erledigt _entry_edit.js
 *    (Capture-Phase); hier nur Strg/⌘/Umschalt-Klick → Detailseite bzw. Verwaltung in einem neuen Tab.
 *  - Seite: öffnet die Seite im Seiten-Editor. Im Bearbeiten-Modus mit ungespeicherten Änderungen fragt die Werkzeugleiste
 *    wie beim Verlassen (Bar.cancel: „Speichern & beenden“ · „Verwerfen“ · „Weiter bearbeiten“).
 * Die Vorschau des Seiten-Editors verhindert sonst jeden Link-Klick (editor.js) – darum hier in der Capture-Phase.
 * Enter/Leertaste auf der Aktion: nicht an Editor.js weiterreichen (neuer Block bzw. Texteingabe).
 */
import { pathClosest } from './_shadow.js';

const d = document;

export function initTargetEdit(bar) {
  d.addEventListener('click', e => {
    const a = pathClosest(e, 'a.cms-target-edit[data-cms-target]');
    if (!a || e.button > 0) return;
    const newTab = e.metaKey || e.ctrlKey || e.shiftKey;
    if (a.dataset.entryEdit && !newTab) return;   // Seitenleiste (_entry_edit.js)
    e.preventDefault();
    e.stopPropagation();
    if (newTab) { window.open(a.href, '_blank', 'noopener'); return; }
    if (bar?.cancel) bar.cancel(a.href);   // fragt nur bei ungespeicherten Änderungen
    else location.href = a.href;
  }, true);
  d.addEventListener('keydown', e => {
    if ((e.key === 'Enter' || e.key === ' ') && pathClosest(e, 'a.cms-target-edit[data-cms-target]')) {
      e.stopPropagation();
      if (e.key === ' ') { e.preventDefault(); pathClosest(e, 'a.cms-target-edit').click(); }
    }
  }, true);
}
