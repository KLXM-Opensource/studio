/*
 * Grafik-Editor: Silbentrennung Deutsch (neue Rechtschreibung) für Textrahmen – hyphen (ISC, Yevhen Tiurin) mit den
 * TeX-Trennmustern de-1996 (MIT, Deutschsprachige Trennmustermannschaft) – public/assets/vendor/grafik/LICENSE-hyphen.txt.
 * Nur auf Abruf geladen (Absatz → Silbentrennung), resources/js/grafik/typo.js. MIT
 */
import createHyphenator from 'hyphen';
import patterns from 'hyphen/patterns/de-1996.js';

/** Wort → Wort mit weichen Trennstellen (U+00AD) */
export default createHyphenator(patterns, { hyphenChar: '­', minWordLength: 5 });
