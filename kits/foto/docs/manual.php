<?php
/**
 * Handbuch des Kits „foto“ – ergänzt das Handbuch des Cores (app/Admin/views/help/manual.php):
 * eigene Kapitel in docs/manual/*.php, alle übrigen Kapitel kommen aus dem Core.
 */
$settingsTitle = app()->theme->settingsTitle();
return [
    'hero' => [
        'eyebrow' => 'Handbuch für die Redaktion · Kit „Foto“',
        'title' => 'Bilder zeigen<i>.</i><br>Ruhig und auf jedem Bildschirm.',
        'lead' => 'Serien anlegen, Fotostrecken füllen, Bildunterschriften schreiben, Design wählen – das Kit ordnet jedes Bild nach dem Platz, den es hat. Sie müssen sich nie um Handy- oder Tablet-Ansichten kümmern.',
    ],
    'figures' => false,
    'chapters' => [
        'start' => ['mode' => 'after', 'file' => __DIR__ . '/manual/start.php'],
        'fotografie' => ['title' => 'Serien, Fotostrecken und Lightbox', 'after' => 'start', 'file' => __DIR__ . '/manual/fotografie.php'],
        'intrinsisch' => ['title' => 'Breakpointlos: So passt sich alles an', 'after' => 'fotografie', 'file' => __DIR__ . '/manual/intrinsisch.php'],
        'design' => ['title' => 'Design: Vorlagen, Bilder, Kopf & Fuß', 'after' => 'einstellungen', 'file' => __DIR__ . '/manual/design.php'],
        'seiten' => ['mode' => 'after', 'file' => __DIR__ . '/manual/seiten.php'],
    ],
    'vars' => [
        'settings_sub' => 'Name, Kontakt, Social Media und Logo – einmal eintragen, überall aktuell.',
        'blocks_page' => '/arbeiten',
    ],
    // Beschreibungen im Kapitel „Alle Blöcke“ (übrige Blöcke: Beschreibung aus der Blockdefinition)
    'blocks' => [
        'photo_hero' => 'Großes Bild oder ruhige Bildfolge mit Überblendung – bildschirmfüllend, hoch oder 21:9, randlos oder mit Seitenrand. Die Überschrift (H1) steht unten links/rechts, mittig, oben links oder unter dem Bild; ein Verlauf dahinter hält sie auf jedem Bild lesbar. Die Bildfolge hat Zurück, Pause und Weiter, hält bei Maus oder Tastaturfokus an und steht bei „Bewegung reduzieren“ still. Ein dezenter Hinweis führt nach unten.',
        'photo_grid' => 'Die Fotostrecke einer Serie: Mosaik (Originalformate in Spalten), bündige Zeilen (gleiche Höhe, ohne Zuschnitt), gleichmäßiges Raster (Format wählbar: 1:1, 3:2, 2:3, 4:5, 16:9 …), große Einzelbilder untereinander oder waagerechtes Band zum Wischen mit Pfeilen. Bilder einzeln mit Bildunterschrift oder eine ganze Sammlung. Spalten höchstens 2, 3, 4 oder automatisch; Breite wie im Design, Inhaltsbreite, breiter oder randlos. Lightbox mit Pfeiltasten, Wischen und Escape.',
        'series_index' => 'Übersicht der Serien – automatisch aus den Unterseiten (Titelbild, Jahr, Kategorie kommen aus „Serie (Kopf)“) oder von Hand. Als Raster der Titelbilder, als große Liste (beim Zeigen erscheint das Titelbild) oder als abwechselnde Reihen.',
        'series_head' => 'Kopf einer Serien-Seite: Titel (H1), Jahr, Ort, Kategorie, Auftrag und ein kurzes Statement – nur Text, mit breitem Titelbild darüber oder Text und Titelbild nebeneinander. Optional ein Link zurück zur Übersicht.',
        'photo_text' => 'Ein Bild mit Text daneben – links oder rechts, ein Drittel, halb oder zwei Drittel breit, Text oben, mittig oder unten. Optional mit Bildunterschrift und Lightbox.',
        'hero' => 'Klassischer Einstieg mit Überschrift (H1) – für Fotoseiten meist „Seitenkopf“ (ohne Bild), z. B. für „Arbeiten“ oder „Kontakt“. Weitere Varianten: Text + Bild, vollflächig, Collage, Vorher/Nachher …',
        'richtext' => 'Freier Text mit Zwischenüberschriften und Listen – z. B. für Ausstellungstexte. „Artikel“: Lesebreite mit Inhaltsverzeichnis.',
        'media_text' => 'Text mit Bild daneben (aus dem Grundsortiment). Für Fotografie meist „Bild & Text“ verwenden.',
        'features' => 'Leistungen mit Symbol oder Bild – als Liste, Karten oder nummeriert.',
        'cards' => 'Teaser mit Bild – z. B. für Ausstellungen, Workshops oder Publikationen.',
        'logos' => 'Kunden oder Publikationen als ruhiges Raster oder Laufband – ohne Bild als Schriftzug. Nur mit Freigabe.',
        'quote' => 'Ein Zitat als Blickfang oder mehrere Stimmen. Nur echte, freigegebene Stimmen verwenden.',
        'steps' => 'Ablauf eines Auftrags – nummeriert, als Zeitleiste oder Prozess.',
        'pricing' => 'Pakete und Preise als Karten oder Vergleichstabelle. Nur verbindliche, aktuelle Preise angeben.',
        'faq' => 'Häufige Fragen zum Aufklappen (mit strukturierten Daten für Suchmaschinen).',
        'cta' => 'Handlungsaufruf – z. B. „Ein Auftrag, eine Idee?“ mit Button zur Kontaktseite.',
        'scrolly' => 'Scrollytelling: Ein Bild bleibt stehen, Texte ziehen vorbei, das Bild wechselt mit – für Bildessays.',
        'video' => 'YouTube, Vimeo (erst nach Klick) oder eigenes MP4 mit Untertiteln – breit, mit Text oder im Kino-Stil.',
        'contact' => 'Kontaktangaben aus den ' . $settingsTitle . ', optional Formular, Öffnungszeiten und Karte.',
        'downloads' => 'Dateien zum Herunterladen, z. B. Pressemappe oder Preisliste.',
        'map' => 'Karte mit Standort – ohne Cookies, ohne Einwilligung.',
        'gallery' => 'Bildergalerie aus dem Grundsortiment (Raster, Mosaik, Zeilen) – die Lightbox folgt der Design-Einstellung. Für Serien meist „Fotostrecke“ verwenden.',
        'slideshow' => 'Folien mit Bild und Text – Pfeile, Punkte, Tastatur und Wischen.',
    ],
];
