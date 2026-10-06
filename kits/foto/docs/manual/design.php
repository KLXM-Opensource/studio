<?php /** Handbuch „foto“ · Kapitel „Design: Vorlagen, Bilder, Kopf & Fuß“ · @var string $settingsTitle */ ?>
  <p class="lead">Unter <b>Verwaltung → Design</b> stellen Sie Farben, Schriften, Bilder &amp; Galerien, Kopf- und Fußbereich ein. Die Vorschau zeigt jede Änderung sofort – hell und dunkel, auf Computer und Handy. Online geht sie erst mit <b>Speichern</b>; frühere Stände holen Sie über <b>Verlauf</b> zurück.</p>
  <h3>Vorlagen für Fotografie</h3>
  <p>Jede Vorlage setzt alle Werte auf einmal; danach passen Sie Einzelnes an. Alle sind hell und dunkel auf Lesbarkeit (WCAG 2.2 AA) geprüft.</p>
  <table class="doc-table">
    <tr><th>Vorlage</th><th>Charakter</th></tr>
    <tr><td>Bildstrom</td><td>Bilder statt Worte: ganz leise Navigation (Menü links, Name in der Mitte, verschwindet beim Scrollen), Serifen für die wenigen Titel, Korallrot für Farbflächen, randlose Mosaike.</td></tr>
    <tr><td>Weiß &amp; still (Standard)</td><td>Weißer Grund, schwarze Schrift, Inter in leichter Stärke, eckige Bilder, viel Weißraum.</td></tr>
    <tr><td>Dunkelkammer</td><td>Fast schwarz von Anfang an, warmes Rotlicht als Akzent, minimale Navigation, Bildunterschriften auf dem Bild, randlose Galerien.</td></tr>
    <tr><td>Galerie-Grau</td><td>Warmes Grau wie im Ausstellungsraum, weiße Passepartouts, zentrierter Kopf, Bildunterschriften beim Zeigen.</td></tr>
    <tr><td>Editorial</td><td>Wie ein Bildband: Serifen-Überschriften, kursive Bildunterschriften, Tiefblau, geteilter Kopf.</td></tr>
    <tr><td>Reportage</td><td>Schmale Versalien, Signalrot, Schwarzweiß bis zum Zeigen, kleiner Bildabstand, randlose Strecken.</td></tr>
    <tr><td>Analog</td><td>Creme und Rost, Display-Serife, feine Bildrahmen, Kameradaten-Schrift, Menü als Seitenleiste.</td></tr>
  </table>
  <h3>Bilder &amp; Galerien</h3>
  <ul>
    <li><b>Abstand zwischen den Bildern:</b> kein, klein, mittel, groß – wächst fließend mit dem Fenster.</li>
    <li><b>Ecken der Bilder:</b> eckig, leicht oder deutlich gerundet.</li>
    <li><b>Bildunterschriften:</b> unter dem Bild, beim Zeigen auf dem Bild (auf Touch-Geräten darunter), immer auf dem Bild oder ausgeblendet (dann nur in der Lightbox und für Screenreader). Schrift: klein wie Fließtext, Monospace, kursiv oder Versalien.</li>
    <li><b>Hintergrund der Lightbox:</b> dunkel, hell oder verschwommene Seite – gilt auch für die Bildergalerie.</li>
    <li><b>Schwarzweiß:</b> aus, schwarzweiß bis zum Zeigen oder immer – nur in der Darstellung, die Originale bleiben farbig.</li>
    <li><b>Rahmen / Passepartout:</b> ohne, feine Linie oder weißes Passepartout.</li>
    <li><b>Breite der Galerien:</b> wie der Text, breiter oder randlos – jeder Block kann abweichen.</li>
    <li><b>Verlinkte Bilder beim Zeigen:</b> ruhig, leicht vergrößern oder leicht aufhellen.</li>
  </ul>
  <h3>Navigation und Marke</h3>
  <table class="doc-table">
    <tr><th>Navigation</th><th>So sieht sie aus</th></tr>
    <tr><td>Leiste oben</td><td>Name links, Menü rechts; Serien als Aufklappmenü unter „Arbeiten“.</td></tr>
    <tr><td>Minimal</td><td>Nur Name und Menü-Schaltfläche – das Menü öffnet als Seitenblatt. Am ruhigsten.</td></tr>
    <tr><td>Seitenleiste links</td><td>Menü senkrecht neben dem Inhalt, solange das Fenster breit genug ist – sonst oben.</td></tr>
    <tr><td>Zentriert, Geteilt, Schwebend</td><td>Name über dem Menü, Menü in der Mitte bzw. abgerundete Leiste.</td></tr>
  </table>
  <p><b>Marke oben links:</b> Wortmarke (Ihr Kurzname in der Schrift der Überschriften) oder Ihr Logo-Bild (unter <?= e($settingsTitle) ?> → Darstellung). Der Handlungsaufruf (z. B. „Anfragen“) steht standardmäßig als letzter Menüpunkt – ruhig statt Button.</p>
  <p>Weitere Einstellungen wie Schriften, Schriftgrößen, Buttons, Fußbereich und Bewegung entsprechen dem Grundsortiment. „Bewegung reduzieren“ der Besucher hat immer Vorrang.</p>
