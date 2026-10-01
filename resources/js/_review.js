/**
 * Prüf-Ebene „Eingereicht“ (Core\Review): Sammelauswahl in der Liste (/admin/ai/eingereicht).
 * Ohne JavaScript funktionieren die Checkboxen und Schaltflächen ebenso (Server meldet „nichts ausgewählt“).
 */
import { t } from './_i18n.js';

export function initReview() {
  const form = document.querySelector('[data-rv-bulk]');
  if (!form) return;
  const all = form.querySelector('[data-rv-all]');
  const items = [...form.querySelectorAll('[data-rv-item]')];
  const need = [...form.querySelectorAll('[data-rv-need]')];
  const count = form.querySelector('[data-rv-count]');
  const update = () => {
    const n = items.filter(i => i.checked).length;
    need.forEach(b => { b.disabled = n === 0; });
    if (all) {
      const nb = bulk.filter(i => i.checked).length;
      all.checked = nb > 0 && nb === bulk.length;
      all.indeterminate = n > 0 && !all.checked;
    }
    if (count) count.textContent = n ? t('{n} ausgewählt', { n }) : '';
    items.forEach(i => i.closest('tr')?.classList.toggle('is-selected', i.checked));
  };
  // „Alle auswählen“ lässt offline gestellte Seiten aus (data-rv-noall) – sonst gingen sie beim Sammel-Veröffentlichen
  // ungewollt wieder online; einzeln anhaken geht weiter
  const bulk = items.filter(i => !i.hasAttribute('data-rv-noall'));
  all?.addEventListener('change', () => { bulk.forEach(i => { i.checked = all.checked; }); update(); });
  items.forEach(i => i.addEventListener('change', update));
  update();
}
