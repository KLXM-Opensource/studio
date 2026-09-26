<?php /** Handbuch „fluid“ · Kapitel „Design, Kopf & Fuß“ · @var string $settingsTitle */ ?>
  <p class="lead">Unter <b>Verwaltung → Design</b> stellen Sie Farben, Schriften, Formen, Kopf- und Fußbereich ein. Die Vorschau rechts zeigt jede Änderung sofort – hell und dunkel, auf dem Computer und dem Handy. Online geht sie erst mit <b>Speichern</b>; frühere Stände holen Sie über <b>Verlauf</b> zurück.</p>
  <h3>Neun Vorlagen</h3>
  <p>Jede Vorlage setzt alle Werte auf einmal; danach passen Sie Einzelnes an. Alle Vorlagen sind hell und dunkel auf Lesbarkeit (WCAG 2.2 AA) geprüft.</p>
  <table class="doc-table">
    <tr><th>Vorlage</th><th>Charakter</th></tr>
    <tr><td>Fluid Standard</td><td>Neutral: Indigo, Inter, Leiste mit Menü rechts.</td></tr>
    <tr><td>Kanzlei · Nachtblau &amp; Serife</td><td>Seriös: Serifen-Überschriften, eckig, zentrierter Kopf.</td></tr>
    <tr><td>Handwerk · Terrakotta</td><td>Kräftig: markante Grotesk, runde Buttons, schwebender Kopf, Punktraster, großer Schriftzug im Fuß.</td></tr>
    <tr><td>Praxis · Salbei</td><td>Beruhigend: weiche Karten mit Schatten, sanfter Farbverlauf, luftige Abstände.</td></tr>
    <tr><td>Agentur · Kontrast</td><td>Laut: sehr große Schriftstufen, Violett und Limette, Raster, Menü in der Mitte.</td></tr>
    <tr><td>Verein · Frisch</td><td>Fröhlich: Blau und Gelb, fette Schrift, Etiketten.</td></tr>
    <tr><td>Restaurant · Bordeaux &amp; Creme</td><td>Genussvoll: Fraunces, Cremetöne, Kontur-Buttons, zentrierter Kopf.</td></tr>
    <tr><td>Tech-Startup · Nacht</td><td>Dunkel von Anfang an: Glas-Karten, Monospace-Etiketten, Raster.</td></tr>
    <tr><td>Kultur · Museum</td><td>Plakativ: Instrument Serif in sehr großen Stufen, Signalrot, Menü als Seitenleiste.</td></tr>
  </table>
  <h3>Die Einstellungen</h3>
  <ul>
    <li><b>Farben:</b> Akzent, Akzent kräftig, Schrift auf Akzent, Zweitfarbe (Kacheln, Etiketten, Markierungen), Überschriften, Fließtext, Nebentext, Hintergrund, getönte Fläche, Linien, dunkle Abschnitte – je hell und dunkel, mit Kontrastprüfung.</li>
    <li><b>Typografie:</b> Schrift für Fließtext, Überschriften und Code (neun Schriften vom eigenen Server, die meisten variabel), Schrift der Dachzeilen, Grundschrift klein/groß, Verhältnis der Stufen (ruhig bis plakativ), Bereich, in dem die Schrift fließt, Stärke (stufenlos) und Laufweite der Überschriften.</li>
    <li><b>Form &amp; Raum:</b> Eckenradius, Buttons (gefüllt, Pille, Kontur, getönt, eckig mit Pfeil), Karten (Fläche, Rahmen, Schatten, Glas), Schatten, Dichte, Abstand zwischen Abschnitten, maximale Inhaltsbreite, Dachzeilen.</li>
    <li><b>Kopf &amp; Fuß:</b> Kopfbereich (siehe unten), beim Scrollen sichtbar, Button im Kopfbereich (Beschriftung und Link unter <?= e($settingsTitle) ?> → Darstellung), Fußbereich (Spalten, schlicht, großer Schriftzug), Seitenhintergrund (einfarbig, Raster, Punkte, Verlauf).</li>
    <li><b>Bewegung &amp; Farbschema:</b> dezente Animationen (Einblenden, Laufband) und dunkles Farbschema nach Geräte-Einstellung. „Bewegung reduzieren“ der Besucher hat immer Vorrang.</li>
  </ul>
  <h3>Fünf Kopfbereiche</h3>
  <table class="doc-table">
    <tr><th>Variante</th><th>So sieht sie aus</th></tr>
    <tr><td>Leiste</td><td>Logo links, Menü rechts – der Klassiker.</td></tr>
    <tr><td>Zentriert</td><td>Logo in der Mitte, Menü darunter; bei wenig Platz Logo mittig und Menü-Schaltfläche rechts.</td></tr>
    <tr><td>Geteilt</td><td>Menü in der Mitte zwischen Logo und Button.</td></tr>
    <tr><td>Schwebend</td><td>Abgerundete Leiste mit Abstand zum Rand und Schatten.</td></tr>
    <tr><td>Seitenleiste</td><td>Menü senkrecht links neben dem Inhalt, solange das Fenster breit genug ist – sonst wie „Leiste“ oben.</td></tr>
  </table>
  <p>Alle Varianten sind per Tastatur bedienbar (Tab, Pfeiltasten, Escape) und öffnen bei Platzmangel dasselbe Seitenblatt mit Menü, Suche, Kontakt und Sprache.</p>
