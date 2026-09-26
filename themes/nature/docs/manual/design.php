<?php /** Handbuch „nature“ · Kapitel „Design, Kopf & Fuß“ · @var string $settingsTitle */ ?>
  <p class="lead">Unter <b>Verwaltung → Design</b> stellen Sie Farben, Jahreszeit, Schriften, Formen, Kopf- und Fußbereich ein. Die Vorschau zeigt jede Änderung sofort – hell und dunkel. Online geht sie erst mit <b>Speichern</b>; frühere Stände holen Sie über <b>Verlauf</b> zurück.</p>
  <h3>Sechs Vorlagen – vier davon Jahreszeiten</h3>
  <p>Jede Vorlage setzt alle Werte auf einmal; danach passen Sie Einzelnes an. Alle sind hell und dunkel auf Lesbarkeit (WCAG 2.2 AA) geprüft.</p>
  <table class="doc-table">
    <tr><th>Vorlage</th><th>Charakter</th></tr>
    <tr><td>Moos (Standard)</td><td>Papier und Sand, Moosgrün, Ton als Schmuckfarbe; Fraunces + Nunito Sans, Wellen, Papierstruktur.</td></tr>
    <tr><td>Frühling</td><td>Blattgrün und Kirschblüte; runde Buttons, Kiesel-Bilder, Blattadern im Hintergrund.</td></tr>
    <tr><td>Sommer</td><td>See-Blau und Weizengold; Young Serif, Hügel-Übergänge, zentrierter Kopf.</td></tr>
    <tr><td>Herbst</td><td>Rost und Ocker; Source Sans 3, Höhenlinien, Band im Fußbereich.</td></tr>
    <tr><td>Winter</td><td>Kiefer, Reif und Beere; Young Serif + Source Sans 3, Kontur-Buttons, kleine Versalien.</td></tr>
    <tr><td>Waldnacht</td><td>Von Anfang an dunkel: tiefes Waldgrün, Moos, Glut; Höhenlinien, Minimal-Kopf.</td></tr>
  </table>
  <h3>Die Einstellungen</h3>
  <ul>
    <li><b>Farben:</b> Akzent und Akzent als Schrift, Schrift auf Akzent, Überschriften, Fließtext, Nebentext, Papier, Sand, Karten, Linien, Waldnacht, Erde – je hell und dunkel, mit Kontrastprüfung.</li>
    <li><b>Typografie:</b> Schriften für Fließtext und Überschriften, Dachzeilen kursiv oder in kleinen Versalien, Grundschrift, Stufen, Stärke, Weichheit der Serifen, Laufweite.</li>
    <li><b>Formen &amp; Natur:</b> Radius der Karten, Buttons (weich, rund, Kontur), Bildform (abgerundet, Blatt, Kiesel), Tiefe, Dichte, Abstand, Inhaltsbreite, Marken (Blatt, Samenkorn, ohne), Illustrationen, Jahreszeit (automatisch nach Datum oder fest).</li>
    <li><b>Kopf &amp; Fuß:</b> Leiste, zentriert, geteilt oder minimal; mitlaufender Kopf; Button im Kopf (Text und Link in den <?= e($settingsTitle) ?>); Fußbereich Spalten, mit Band oder schlicht; Seitenhintergrund (einfarbig, Papierstruktur, Höhenlinien, Blattadern); Übergänge (Welle, Hügel, gerade).</li>
    <li><b>Bewegung &amp; Farbschema:</b> sanftes Einblenden, kaum merkliches Treiben, Waldnacht für Besucher mit dunklem Farbschema. Wer im Gerät „Bewegung reduzieren“ eingestellt hat, sieht nie Animationen.</li>
  </ul>
  <h3>Menü auf kleinen Bildschirmen</h3>
  <p>Passt das Menü nicht in die Leiste, erscheint die Schaltfläche <b>Menü</b>. Sie öffnet ein Seitenblatt mit den Seiten, Unterseiten zum Aufklappen, Suche, Direktkontakt und Sprache.</p>
