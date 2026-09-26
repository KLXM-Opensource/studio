/**
 * Administration → Weiterleitungen (Core\Redirects): Ziel-Feld bei „410 – entfernt“ ausblenden.
 * Ohne JavaScript bleibt das Feld sichtbar; der Server ignoriert das Ziel bei 410.
 */
export function initRedirects() {
  const box = document.querySelector('[data-rd-target]');
  const radios = [...document.querySelectorAll('[data-rd-code]')];
  if (!box || !radios.length) return;
  const update = () => { box.hidden = radios.some(r => r.checked && r.value === '410'); };
  radios.forEach(r => r.addEventListener('change', update));
  update();
}
