/*
 * Gemeinsame Kern-Module für die nachgeladenen Teile von admin.js (resources/js/admin-*.mjs, geladen mit _lazy.js).
 * Jeder Import, den ein solcher Teil auf eines der hier genannten Module macht, zeigt beim Bauen (tools/build.mjs →
 * Plugin „admin-shared“) auf window.CMSAdminModules['_x.js'] – dieselbe Instanz wie in admin.js (Ebene/Shadow DOM,
 * Rückfragen der Werkzeugleiste, Symbol-Index) statt einer zweiten Kopie. Die Liste unten ist die einzige Quelle:
 * das Plugin liest die Zeilen „from './_x.js'“ dieser Datei. Intern – keine Schnittstelle für Erweiterungen.
 */
import * as _shadow from './_shadow.js';
import * as _bar from './_bar.js';
import * as _icons from './_icons.js';
import * as _preview from './_preview.js';
import * as _i18n from './_i18n.js';
import * as _html from './_html.js';
import * as _ai from './_ai.js';

window.CMSAdminModules = Object.freeze({
  '_shadow.js': _shadow, '_bar.js': _bar, '_icons.js': _icons, '_preview.js': _preview, '_i18n.js': _i18n, '_html.js': _html, '_ai.js': _ai,
});
