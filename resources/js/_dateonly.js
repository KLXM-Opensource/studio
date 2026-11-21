/*
 * Datum und Uhrzeit mit „ganztägig“ (Core\Fields 'date_only_if', z. B. Beginn/Ende einer Kalender-Tabelle): ist das Ja/Nein-Feld
 * gesetzt, wird die Eingabe zum reinen Datum (gespeichert als 00:00 Uhr), sonst wieder Datum + Uhrzeit. Die zuletzt
 * eingegebene Uhrzeit bleibt gemerkt (data-time) und kommt beim Zurückschalten wieder.
 */
export function initDateOnly(scope = document) {
  const list = [...(scope.querySelectorAll?.('input[data-date-only]') || [])];
  list.forEach(inp => {
    if (inp._dateOnly) return;
    inp._dateOnly = true;
    const form = inp.form || inp.closest('form') || inp.getRootNode();
    const sw = () => [...form.querySelectorAll(`input[name="${CSS.escape(inp.dataset.dateOnly)}"]`)].find(c => c.type === 'checkbox');
    const apply = () => {
      const box = sw();
      if (!box) return;
      const only = box.checked && !box.disabled;
      if (only === (inp.type === 'date')) return;
      const v = inp.value;
      if (only) {
        if (v.length >= 16 && v.slice(11, 16) !== '00:00') inp.dataset.time = v.slice(11, 16);
        inp.removeAttribute('step');
        inp.type = 'date';
        inp.value = v.slice(0, 10);
      } else {
        inp.type = 'datetime-local';
        inp.step = '60';
        inp.value = v ? v.slice(0, 10) + 'T' + (inp.dataset.time || '00:00') : '';
      }
    };
    form.addEventListener('change', e => { if (e.target.name === inp.dataset.dateOnly) apply(); });
    apply();
  });
}
