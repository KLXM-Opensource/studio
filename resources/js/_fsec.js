/*
 * Aufklappbare Abschnitte mit Zählung (Core\Fields: Überschrift mit 'collapse' + 'count', z. B. Datentabellen-Abschnitt
 * „Eingeklappt“): hält „3 von 7 ausgefüllt“ im Kopf aktuell. Gezählt wird je Feld (Name f[feld]…); ausgeblendete Felder
 * (Bedingungen, show_if) zählen nicht. Den Anfangswert rendert der Server – ohne JavaScript fehlt nur die Aktualisierung.
 */
const fieldOf = n => (/^[^[]+\[([^\]]+)\]/.exec(n) || [])[1];

function count(sec) {
  const per = new Map();
  sec.querySelectorAll('.f-sec__body input[name], .f-sec__body select[name], .f-sec__body textarea[name]').forEach(e => {
    const n = fieldOf(e.name);
    if (!n || n.startsWith('_') || e.closest('[hidden]')) return;
    const r = per.get(n) || { box: false, on: false, val: false };
    if (e.type === 'checkbox' || e.type === 'radio') { r.box = true; r.on ||= e.checked; }
    else if (e.type !== 'button' && e.value.trim() !== '') r.val = true;
    per.set(n, r);
  });
  let filled = 0;
  per.forEach(r => { if (r.box ? r.on : r.val) filled++; });
  return [filled, per.size];
}

export function initSecCount(scope = document) {
  const list = [...(scope.querySelectorAll?.('details.f-sec[data-count]') || [])];
  if (scope.matches?.('details.f-sec[data-count]')) list.push(scope);
  list.forEach(sec => {
    if (sec._cnt) return;
    sec._cnt = true;
    const hint = sec.querySelector(':scope > summary [data-sec-count]');
    if (!hint) return;
    const run = () => {
      const [n, total] = count(sec);
      hint.hidden = !total;
      hint.textContent = sec.dataset.count.replace('{n}', n).replace('{total}', total);
    };
    // Bedingungen (Core\Data\Rules) blenden beim ersten Lauf bzw. nach Änderungen Felder aus – danach zählen. Auch Änderungen
    // in anderen Abschnitten können hier Felder ein-/ausblenden → auf das ganze Formular hören
    const later = () => requestAnimationFrame(run);
    const form = sec.closest('form') || sec.parentElement || sec;
    form.addEventListener('input', later);
    form.addEventListener('change', later);
    later();
  });
}
