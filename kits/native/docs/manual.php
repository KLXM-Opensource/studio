<?php
/**
 * Handbuch des Kits „NativeCSS“ – das Handbuch von fluid (kits/fluid/docs/manual.php) mit eigenem Kopf, dem Kapitel
 * „NativeCSS: Effekte und Bausteine“ (docs/manual/native.php) und den Beschreibungen der Blöcke, die hier anders aussehen.
 */
$m = require \Core\Kit::dir('fluid') . '/docs/manual.php';
$m['hero'] = [
    'eyebrow' => 'Handbuch für die Redaktion · Kit „NativeCSS“',
    'title' => 'Die Website pflegen<i>.</i><br>Mit nativen Bausteinen.',
    'lead' => 'Texte ändern, Seiten und Blöcke kombinieren, Design wählen – dazu Einblend-Effekte, Morphing, Video-Player und Vorher/Nachher. Alles funktioniert mit Tastatur, auf jedem Bildschirm und bei „Bewegung reduzieren“.',
];
$m['chapters']['native'] = ['title' => 'NativeCSS: Effekte und Bausteine', 'after' => 'fluid', 'file' => __DIR__ . '/manual/native.php'];
$m['vars']['blocks_page'] = '/bausteine';
$m['blocks'] = [
    'hero' => 'Hauptüberschrift der Seite (H1): Text + Bild, zentriert, Seitenkopf als getönte Kachel, großer Schriftzug (Betonung im Farbverlauf), vollflächiges Bild oder Hintergrundvideo (mit Pause-Schaltfläche, lädt erst in der Nähe, nie bei „Bewegung reduzieren“) – dazu Karten, Fließende Skala, Collage und Vorher/Nachher wie im Kit Fluid. Ein Wort in *Sternchen* wird hervorgehoben.',
    'media_text' => 'Text mit Bild daneben (abwechselnd, rechts, links) – und „Morphing beim Scrollen“: Das Bild startet groß und rückt beim Scrollen zur Seite, der Text erscheint daneben. „Geteilt“ und „Überlappend“ wie im Kit Fluid.',
    'cards' => 'Karten mit Rahmen und Schatten, die ganze Karte ist klickbar; Bild oben, Text auf dem Bild, quer oder als wischbares Band.',
    'logos' => 'Logos oder Namen als Raster oder Laufband – das Laufband hat eine Schaltfläche „Laufband anhalten“, hält bei Maus und Fokus an und steht bei „Bewegung reduzieren“ still.',
    'stats' => 'Kennzahlen, die beim Hereinscrollen hochzählen („25+“, „98 %“, „12.500“) – Jahreszahlen und Werte wie „24/7“ bleiben stehen. Nur belegbare Zahlen verwenden.',
    'quote' => '„Groß“ als hervorgehobene Kundenstimme, „Raster“ als gleich hohe Karten mit Name und Funktion; Band und Laufband wie im Kit Fluid.',
    'steps' => '„Zeitleiste“ mit Datum, Nummer oder Symbol, ab genug Platz abwechselnd links und rechts – Option „Linie wächst beim Scrollen“. Nummerierte Schritte und Prozess wie im Kit Fluid.',
    'pricing' => 'Pakete als Preistabelle (gleich hohe Karten, Zeilen bündig; eine Leistung mit „–“ am Anfang gilt als nicht enthalten) oder als Vergleichstabelle.',
    'faq' => 'Aufklappbare Fragen – neben der Überschrift oder zentriert untereinander. Suchmaschinen erhalten die Fragen als strukturierte Daten.',
    'cta' => 'Handlungsaufruf als Band im Farbverlauf oder als Box; mit Bild, großer Schriftzug und Foto wie im Kit Fluid.',
    'scrolly' => 'Scrollytelling: Die Abschnitte ziehen als Karten vorbei, das Bild daneben wechselt mit. Ohne JavaScript stehen Bilder und Texte untereinander.',
    'video' => 'YouTube, Vimeo (erst nach Klick) oder eigenes Video im Player mit Kapiteln, Untertitel-Menü, Tastatur und Vollbild – Untertitel und Kapitel pflegen Sie in der Mediathek. „Video mit Morphing beim Scrollen“: kurze, stumme Schleife wie ein Hintergrundvideo.',
    'compare' => 'Vorher/Nachher: zwei Bilder übereinander, ein Regler zeigt mehr vom einen oder anderen (Maus, Finger, Tab und Pfeiltasten). Beide Bilder im selben Ausschnitt aufnehmen.',
] + $m['blocks'];
return $m;
