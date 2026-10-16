/** HTML maskieren – für Text und Attribute (auch in einfachen Anführungszeichen). Einzige Fassung für Verwaltung und Website. */
export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
