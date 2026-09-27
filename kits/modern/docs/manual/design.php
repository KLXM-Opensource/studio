<?php /** Handbuch „modern“ · Kapitel „Design & Navigation“ · @var string $settingsTitle */ ?>
  <p class="lead">Unter <b>Verwaltung → Design</b> stellen Sie Farben, Schriften, Formen, Navigation und Fußbereich ein. Die Vorschau rechts zeigt jede Änderung sofort – hell und dunkel, auf dem Computer und dem Handy. Online geht sie erst mit <b>Speichern</b>; frühere Stände holen Sie über <b>Verlauf</b> zurück.</p>
  <h3>Vier Vorlagen</h3>
  <p>Jede Vorlage setzt alle Werte auf einmal; danach passen Sie Einzelnes an. Alle Vorlagen sind hell und dunkel auf Lesbarkeit (WCAG 2.2 AA) geprüft.</p>
  <table class="doc-table">
    <tr><th>Vorlage</th><th>Charakter</th></tr>
    <tr><td>Nordlicht</td><td>Standard: Nachtblau, Polarlicht-Grün, Limetten-Fläche; Space Grotesk + Plus Jakarta Sans; schwebende Leiste, großer Schriftzug im Fuß.</td></tr>
    <tr><td>Kobalt</td><td>Technisch: Kobaltblau und Sonnengelb, Inter Tight, Kontur-Buttons, nummerierte Dachzeilen, klassische Leiste.</td></tr>
    <tr><td>Koralle</td><td>Nahbar: Korallenrot auf Creme, Manrope, weiche Karten mit Schatten, Kontaktzeile im Kopf.</td></tr>
    <tr><td>Graphit</td><td>Plakativ: Schwarz-Weiß, sehr große Überschriften, nur Menü-Schaltfläche, schlichter Fuß.</td></tr>
  </table>
  <h3>Die Einstellungen</h3>
  <ul>
    <li><b>Farben:</b> Akzent, Akzent kräftig, Schrift auf Akzent, Blockfarbe (kräftige Flächen für Kacheln, Zitate, Handlungsaufrufe), Überschriften, Fließtext, Nebentext, Hintergrund, getönte Fläche, Linien, dunkle Abschnitte – je hell und dunkel, mit Kontrastprüfung.</li>
    <li><b>Typografie:</b> Schrift für Fließtext und Überschriften (vier Schriften vom eigenen Server, alle variabel, plus Systemschrift), Grundschrift, Verhältnis der Stufen, Größe der Einstiegs-Überschrift, Stärke und Laufweite der Überschriften.</li>
    <li><b>Form &amp; Raum:</b> Eckenradius der Karten (Buttons und Felder haben immer 8 px), Buttons (gefüllt, Kontur, mit Pfeil), Karten (feine Kante, Fläche, Schatten), Abstand zwischen Abschnitten, Inhaltsbreite, Dachzeilen (Farbquadrat, nummeriert, schlicht).</li>
    <li><b>Navigation:</b> vier Varianten (siehe unten), Kopf beim Scrollen sichtbar, Button im Kopfbereich (Beschriftung und Link unter <?= e($settingsTitle) ?> → Darstellung), Fußbereich (großer Schriftzug, Spalten, schlicht).</li>
    <li><b>Bewegung &amp; Farbschema:</b> dezente Übergänge und dunkles Farbschema nach Geräte-Einstellung. „Bewegung reduzieren“ der Besucher hat immer Vorrang.</li>
  </ul>
  <h3>Vier Navigationen</h3>
  <table class="doc-table">
    <tr><th>Variante</th><th>So sieht sie aus</th></tr>
    <tr><td>Modern</td><td>Schwebende, abgerundete Leiste mit feiner Kante.</td></tr>
    <tr><td>Klassisch</td><td>Volle Breite, Logo links, Menü rechts, Linie unten.</td></tr>
    <tr><td>Minimal</td><td>Nur Marke, Button und Menü-Schaltfläche – das Menü öffnet als Seitenblatt.</td></tr>
    <tr><td>Ausführlich</td><td>Zusätzliche Kontaktzeile oben mit Telefon, E-Mail, „Jetzt geöffnet“ und Sprache.</td></tr>
  </table>
  <p>Alle Varianten sind per Tastatur bedienbar (Tab, Pfeiltasten, Escape). Auf Handy und Tablet öffnet die Menü-Schaltfläche dasselbe Seitenblatt mit Menü, Suche, Kontakt und Sprache.</p>
