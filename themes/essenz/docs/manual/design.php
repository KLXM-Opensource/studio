<?php /** Handbuch „essenz“ · Kapitel „Design, Kopf & Fuß“ · @var string $settingsTitle */ ?>
  <p class="lead">Unter <b>Verwaltung → Design</b> stellen Sie Signalfarbe, Schriften, Tiefe, Raster, Kopf- und Fußbereich ein. Die Vorschau zeigt jede Änderung sofort – hell und dunkel. Online geht sie erst mit <b>Speichern</b>; frühere Stände holen Sie über <b>Verlauf</b> zurück.</p>
  <h3>Sechs Vorlagen</h3>
  <p>Jede Vorlage setzt alle Werte auf einmal; danach passen Sie Einzelnes an. Alle sind hell und dunkel auf Lesbarkeit (WCAG 2.2 AA) geprüft.</p>
  <table class="doc-table">
    <tr><th>Vorlage</th><th>Charakter</th></tr>
    <tr><td>Signalorange (Standard)</td><td>Warmes Hellgrau, Graphit, orangefarbenes Signal, Relief, Tasten, Signalpunkte.</td></tr>
    <tr><td>Graphit</td><td>Kühles Grau, Ocker als Signal, Inter Tight, laufende Nummern, feines Linienraster, flach.</td></tr>
    <tr><td>Signalgrün</td><td>Papiergrau, grünes Signal, Manrope, runde Tasten, Punktraster, zentrierter Kopf.</td></tr>
    <tr><td>Signalblau</td><td>Kühles Weißgrau, blaues Signal, Geist, geteilter Kopf, Paneel im Fuß.</td></tr>
    <tr><td>Nachtpaneel</td><td>Von Anfang an dunkel, orangefarbenes Signal, tiefe Paneele, Minimal-Kopf mit „Index“.</td></tr>
    <tr><td>Papier</td><td>So wenig wie möglich: Weiß, Schwarz, rotes Signal, flach, eckig, ohne Details.</td></tr>
  </table>
  <h3>Die Einstellungen</h3>
  <ul>
    <li><b>Farben:</b> Signal (Füllfarbe) und Signal als Schrift (für Links), Schrift auf Signal, Überschriften, Fließtext, Nebentext, Hintergrund, getönte Fläche, Paneel, Linien, Nachtpaneel – je hell und dunkel, mit Kontrastprüfung.</li>
    <li><b>Typografie:</b> Schriften für Fließtext, Überschriften und Beschriftungen (fünf Schriften vom eigenen Server plus Systemschrift), Grundschrift, Verhältnis der Stufen, Stärke und Laufweite der Überschriften.</li>
    <li><b>Form, Raster &amp; Tiefe:</b> Eckenradius, Buttons (Taste, Pille, Kontur), Tiefe (flach, Relief, tief), Rasterdichte, Abstand zwischen Abschnitten, Inhaltsbreite, Abschnittsmarken (Signalpunkt, laufende Nummer, ohne), Bedienelement-Details.</li>
    <li><b>Kopf &amp; Fuß:</b> Leiste, zentriert, geteilt oder minimal mit „Index“; mitlaufender Kopf; Button im Kopf (Text und Link in den <?= e($settingsTitle) ?>); Fußbereich Index, Paneel oder schlicht; Seitenhintergrund mit feinem Punkt- oder Linienraster.</li>
    <li><b>Bewegung &amp; Farbschema:</b> dezentes Einblenden, Skalen füllen sich einmal; Nachtpaneel für Besucher mit dunklem Farbschema. Wer im Gerät „Bewegung reduzieren“ eingestellt hat, sieht nie Animationen.</li>
  </ul>
  <h3>Menü auf kleinen Bildschirmen</h3>
  <p>Passt das Menü nicht in die Leiste, erscheint die Schaltfläche <b>Menü</b> (bzw. <b>Index</b>). Sie öffnet ein Seitenblatt mit nummerierten Seiten, Unterseiten zum Aufklappen, Suche, Direktkontakt und Sprache. Kurze Menüpunkte halten das Menü länger in der Leiste.</p>
