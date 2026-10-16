/** HTML maskieren – für Text und Attribute (auch in einfachen Anführungszeichen). Einzige Fassung für Verwaltung und Website. */
export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/** CSRF-Token der Verwaltung (verstecktes Feld #adm-csrf im Layout; im Editor der Website window.CMS_CSRF) */
export const csrf = () => document.getElementById('adm-csrf')?.value || window.CMS_CSRF || '';
