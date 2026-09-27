<?php /** Handbuch „basis“ · Kapitel „Design & Navigation“ (neu, nach den zentralen Angaben) · @var string $settingsTitle */ ?>
  <p class="lead">Unter <b>Verwaltung → Design</b> stellen Sie Farben, Schriften, Formen und die Navigation ein. Die Vorschau rechts zeigt jede Änderung sofort – hell und dunkel, auf dem Computer und dem Handy. Online geht sie erst mit <b>Speichern</b>; frühere Stände lassen sich über <b>Verlauf</b> zurückholen.</p>
  <h3>Vorlagen</h3>
  <p>Sieben Vorlagen setzen alle Werte auf einmal – danach können Sie einzelne Werte anpassen. Alle Vorlagen sind hell und dunkel auf Lesbarkeit (WCAG 2.2 AA) geprüft.</p>
  <table class="doc-table">
    <tr><th>Vorlage</th><th>Charakter</th></tr>
    <tr><td>Petrol Business</td><td>Standard: ruhig und sachlich, Inter, Navigation „Klassisch“.</td></tr>
    <tr><td>Nachtblau Kanzlei</td><td>Seriös, Serifen-Überschriften, Infoleiste mit „jetzt geöffnet“.</td></tr>
    <tr><td>Warm Handwerk</td><td>Terrakotta, warme Flächen, markante Serifen, runde Buttons, Navigation „Modern“.</td></tr>
    <tr><td>Mint Tech</td><td>Frisch, runde Formen, Schatten-Karten, schwebende Leiste über dem Einstieg.</td></tr>
    <tr><td>Graphit Minimal</td><td>Schwarz-Weiß, eckig, viel Weißraum, Vollbild-Menü.</td></tr>
    <tr><td>Bordeaux Klassisch</td><td>Klassische Serifen, Mega-Menü mit Beschreibungen und Infoleiste.</td></tr>
    <tr><td>Violett Agentur</td><td>Kräftiger Akzent, technische Schrift, schwebende Leiste.</td></tr>
  </table>
  <h3>Die einzelnen Einstellungen</h3>
  <ul>
    <li><b>Farben:</b> Akzent (Buttons, Links, Symbole), Schrift auf Akzent, Überschriften, Fließtext, Nebentext, Hintergrund, getönte Fläche, Linien und dunkle Abschnitte – jeweils mit eigenem Wert für das dunkle Farbschema. Ein Hinweis erscheint, wenn eine Farbe zu wenig Kontrast hat.</li>
    <li><b>Typografie:</b> je eine Schrift für Fließtext und Überschriften (Inter, Manrope, IBM Plex Sans, Source Serif 4, Lora, Fraunces oder Systemschrift – alle vom eigenen Server), Grundschriftgröße, Größe, Stärke und Laufweite der Überschriften.</li>
    <li><b>Form &amp; Abstände:</b> Eckenradius, Buttons (gefüllt, Kontur, rund), Karten (Fläche, Rahmen, Schatten), Abstand zwischen Abschnitten.</li>
    <li><b>Navigation:</b> Variante (siehe unten), Kopfbereich beim Scrollen sichtbar halten, Button im Kopfbereich (Beschriftung und Link unter <?= e($settingsTitle) ?> → Darstellung), Infoleiste, Fußbereich (Spalten oder schlicht).</li>
    <li><b>Farbschema:</b> dunkles Farbschema für Besucher, deren Gerät auf „Dunkel“ steht.</li>
  </ul>
  <h3>Vier Navigationen</h3>
  <table class="doc-table">
    <tr><th>Variante</th><th>So sieht sie aus</th><th>Gut für</th></tr>
    <tr><td>Modern</td><td>Schwebende, abgerundete Leiste; optional transparent über dem ersten Abschnitt (am besten mit Hintergrundbild und Abdunkelung), beim Scrollen deckend.</td><td>Wenige Seiten, große Bilder</td></tr>
    <tr><td>Klassisch</td><td>Logo links, Menü rechts, Unterseiten als Aufklappmenü; optional Infoleiste mit Telefon, E-Mail, „jetzt geöffnet“ und Sprache.</td><td>Die meisten Websites</td></tr>
    <tr><td>Minimal</td><td>Nur Logo und Menü-Schaltfläche; das Menü öffnet sich bildschirmfüllend mit großer Schrift und Direktkontakt.</td><td>Portfolios, Studios, wenige Seiten</td></tr>
    <tr><td>Ausführlich</td><td>Wie klassisch, Unterseiten erscheinen in einem breiten Mega-Menü mit Beschreibung (Seiten-Beschreibung aus den SEO-Angaben), Vorschaubild der ersten Unterseite mit Vorschaubild oder Direktkontakt.</td><td>Viele Unterseiten</td></tr>
  </table>
  <div class="doc-note doc-note--tip"><strong>Mega-Menü füllen</strong><p>Tragen Sie bei Unterseiten eine kurze <b>Beschreibung</b> (Seiteneinstellungen → SEO) ein – sie erscheint im Mega-Menü. Ein <b>Vorschaubild</b> der Seite füllt die rechte Spalte.</p></div>
  <div class="doc-note doc-note--info"><strong>Baukasten</strong><p>Der Seitenbaum „Baukasten“ zeigt alle Blöcke mit Beispielinhalten und dient der Design-Vorschau als Musterseite. Er ist frei erfunden und kann gelöscht werden (Seiten und Tabellen „… (Demo)“).</p></div>
