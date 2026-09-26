/* Übersetzungen der Oberfläche: t('Speichern'), t('{n} Einträge', { n: 3 }). Quelle: <script type="application/json" id="cms-i18n"> */
let dict = null;
export function t(text, params = {}) {
  if (dict === null) {
    try { dict = JSON.parse(document.getElementById('cms-i18n')?.textContent || '{}'); } catch { dict = {}; }
  }
  let out = dict[text] ?? text;
  for (const [k, v] of Object.entries(params)) out = out.replaceAll('{' + k + '}', String(v));
  return out;
}
