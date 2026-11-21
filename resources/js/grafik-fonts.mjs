/*
 * Grafik-Editor: fontkit (MIT, Devon Govett – public/assets/vendor/grafik/LICENSE-fontkit.txt) als eigenes Modul für
 * „Text in Kurven umwandeln“ (resources/js/grafik/outline.js lädt es per import()). Liest WOFF2 (Brotli) und variable
 * Schriften (Achse wght) direkt – so reichen die selbst gehosteten Schriftdateien des Schriften-Managers. MIT
 */
export { create } from 'fontkit';
