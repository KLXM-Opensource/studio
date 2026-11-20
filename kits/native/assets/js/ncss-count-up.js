/*
 * Kennzahlen hochzählen (blocks/stats.php, .ncss-count-up von NativeCSS). Die Website-CSP erlaubt keine style-Attribute:
 * Zielwert, Präfix und Suffix stehen in data-nv-count / data-nv-prefix / data-nv-suffix – _count-vars.js macht daraus per
 * CSSOM die Variablen von NativeCSS, bevor js/count-up.js sie liest (Importe laufen in dieser Reihenfolge).
 */
import './_count-vars.js';
import '../../vendor/ncss/js/count-up.js';
