/*
 * Auswahl als Kacheln (Core\Fields::renderTiles, z. B. Raster des Layout-Blocks): Kachel setzt das ausgeblendete Auswahlfeld und
 * meldet die Änderung (input + change) – Seitenleiste und Vorschau reagieren wie bei einem normalen Auswahlfeld.
 * Ohne JavaScript bleibt das Auswahlfeld sichtbar (.f-tiles__select), die Kacheln sind dann ausgeblendet.
 */
export function initTiles(scope = document) {
  scope.querySelectorAll('[data-tiles]').forEach(box => {
    if (box.dataset.tilesInit) return;
    box.dataset.tilesInit = '1';
    const sel = box.parentElement.querySelector('#' + CSS.escape(box.dataset.tiles));
    if (!sel) return;
    box.classList.add('is-ready');
    box.nextElementSibling?.classList.add('is-hidden');
    const sync = () => box.querySelectorAll('.f-tile').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.value === sel.value)));
    box.addEventListener('click', e => {
      const b = e.target.closest('.f-tile');
      if (!b || sel.value === b.dataset.value) return;
      sel.value = b.dataset.value;
      sync();
      sel.dispatchEvent(new Event('input', { bubbles: true }));
      sel.dispatchEvent(new Event('change', { bubbles: true }));
    });
    sel.addEventListener('change', sync);
  });
}
