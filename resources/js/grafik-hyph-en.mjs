/*
 * Grafik-Editor: Silbentrennung Englisch (US) für Textrahmen – hyphen (ISC, Yevhen Tiurin) mit den TeX-Trennmustern
 * en-us (Gerard D.C. Kuiken, freie Weitergabe mit Hinweis) – public/assets/vendor/grafik/LICENSE-hyphen.txt.
 * Nur auf Abruf geladen (Absatz → Silbentrennung), resources/js/grafik/typo.js. MIT
 */
import createHyphenator from 'hyphen';
import patterns from 'hyphen/patterns/en-us.js';

/** Wort → Wort mit weichen Trennstellen (U+00AD) */
export default createHyphenator(patterns, { hyphenChar: '­', minWordLength: 5 });
