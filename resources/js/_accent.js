/*
 * Konto → Akzentfarbe: Live-Vorschau (Core\Accent). Setzt beim Auswählen data-accent/data-side an <html class="adm-ui">,
 * „Speichern“ übernimmt die Wahl dauerhaft; „Zurücksetzen“ bzw. Verlassen ohne Speichern stellt den gespeicherten Stand her.
 */
const d = document;

export function initAccent() {
  const form = d.querySelector('[data-accent-form]');
  if (!form) return;
  const html = d.documentElement;
  const saved = { accent: html.dataset.accent || '', side: html.dataset.side || '' };
  const apply = (accent, side) => {
    if (accent && accent !== 'navy') html.dataset.accent = accent; else delete html.dataset.accent;
    if (accent && accent !== 'navy' && side) html.dataset.side = 'tint'; else delete html.dataset.side;
  };
  const side = form.querySelector('[data-accent-side]');
  const sync = () => {
    const pick = form.querySelector('input[name=accent]:checked')?.value || 'navy';
    if (side) side.disabled = pick === 'navy';
    apply(pick, !!side?.checked);
  };
  form.addEventListener('change', sync);
  form.addEventListener('reset', () => setTimeout(() => { apply(saved.accent, saved.side); if (side) side.disabled = !saved.accent; }, 0));
  sync();
}
