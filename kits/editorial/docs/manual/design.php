<?php /** Handbuch „editorial“ · Kapitel „Design, Kopf & Raster“ · @var string $settingsTitle */ ?>
  <p class="lead">Unter <b>Verwaltung → Design</b> legen Sie fest, wie Ihre Ausgabe aussieht: Farben, drei Schriften, Skala, Raster, Bilder, Linien und der Kopfbereich. Die Vorschau zeigt jede Änderung sofort – hell und als Nachtausgabe, auf dem Computer und dem Handy. Online geht sie erst mit <b>Speichern</b>.</p>
  <h3>Vorlagen</h3>
  <p>Sieben Vorlagen setzen alle Werte auf einmal. Alle sind hell und dunkel auf Lesbarkeit (WCAG 2.2 AA, Fließtext AAA) geprüft.</p>
  <table class="doc-table">
    <tr><th>Vorlage</th><th>Charakter</th></tr>
    <tr><td>Tageszeitung</td><td>Standard: Druckschwarz auf Papierton, Zeitungsrot, Newsreader + Source Sans, Titelkopf mit Datumszeile, Doppellinien.</td></tr>
    <tr><td>Kulturhaus</td><td>Magenta, Fraunces + Work Sans, Duplex-Bilder, asymmetrisches Raster, Rubriken als Reiter.</td></tr>
    <tr><td>Verband</td><td>Blau, Literata + Public Sans, ruhige Skala, Ressort-Menü mit allen Rubriken.</td></tr>
    <tr><td>Magazin Bold</td><td>Plakative Playfair-Schlagzeilen, Ultramarin, Schwarzweiß-Bilder, kräftige Balken.</td></tr>
    <tr><td>Stiftung</td><td>Grün, Instrument Serif, zentrierte Lesespalte, viel Weißraum.</td></tr>
    <tr><td>Hochschule</td><td>Petrol, Source Serif + IBM Plex, Mono-Auszeichnungen, breite Lesespalte.</td></tr>
    <tr><td>Stadtmagazin</td><td>Orange, DM Serif Display, Duplex-Bilder, Titelkopf.</td></tr>
  </table>
  <h3>Die Einstellungen</h3>
  <ul>
    <li><b>Farben:</b> Akzent (Dachzeilen, Links), Überschriften, Fließtext, Nebentext, Papier, getönte Fläche, Linien, Nachtausgabe – je hell und dunkel, mit Kontrastprüfung.</li>
    <li><b>Typografie:</b> Schrift für Schlagzeilen (Serifen wie Newsreader, Fraunces, Playfair, Literata …), für Fließtext (Source Sans, Public Sans, Work Sans …) und für Auszeichnungen (Mono). Dazu Grundschriftgröße, <b>Skala</b> (Abstand der Überschriften-Stufen), Stärke, Lesebreite, Stil der Dachzeilen und Initiale.</li>
    <li><b>Raster, Bilder & Linien:</b> klassisch (Randspalte rechts), asymmetrisch (Randspalte links, versetzt) oder zentriert; Bilder farbig, als Duplex in der Akzentfarbe oder schwarzweiß (farbig beim Überfahren); Haarlinie, Doppellinie oder Balken.</li>
    <li><b>Kopf & Navigation:</b> Titelkopf, Kompakt, Geteilt (Rubriken als Reiter, mobil wischbar) oder Ressorts (großes Menü mit allen Rubriken); Datumszeile mit Ausgabe (<?= e($settingsTitle) ?> → Darstellung), Button im Kopf, Fußbereich als Sitemap oder schlicht.</li>
    <li><b>Farbschema:</b> Nachtausgabe für Besucher, deren Gerät auf „Dunkel“ steht.</li>
  </ul>
  <div class="doc-note doc-note--info"><strong>Seitenwechsel</strong><p>In aktuellen Browsern blendet die Website beim Wechsel zwischen Seiten weich über, der Kopfbereich bleibt stehen. Wer „Bewegung reduzieren“ eingestellt hat, sieht keine Animation.</p></div>
