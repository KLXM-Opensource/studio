<?php
/**
 * Handbuch des Kits „galerie“ – ergänzt das Handbuch des Cores (app/Admin/views/help/manual.php):
 * eigene Kapitel in docs/manual/*.php, alle übrigen Kapitel kommen aus dem Core.
 */
$settingsTitle = app()->theme->settingsTitle();
return [
    'hero' => [
        'eyebrow' => 'Handbuch für die Redaktion · Kit „Galerie“',
        'title' => 'Kunst zeigen<i>.</i><br>Ohne Technik im Weg.',
        'lead' => 'Ausstellungen, Künstlerinnen und Künstler, Werke und Öffnungszeiten einmal pflegen – Status, Archiv, Preise und Anfragen ergeben sich von selbst. Bilder und Videos ziehen Sie einfach auf die Seite.',
    ],
    'figures' => false,
    'chapters' => [
        'start' => ['mode' => 'after', 'file' => __DIR__ . '/manual/start.php'],
        'galerie' => ['title' => 'Galerie: Künstler, Ausstellungen, Werke', 'after' => 'start', 'file' => __DIR__ . '/manual/daten.php'],
        'medien-galerie' => ['title' => 'Bilder und Videos hinzufügen', 'after' => 'galerie', 'file' => __DIR__ . '/manual/medien.php'],
        'besuch' => ['title' => 'Besuch: Öffnungszeiten, Ausnahmen, Orte', 'after' => 'medien-galerie', 'file' => __DIR__ . '/manual/besuch.php'],
        'intrinsisch' => ['title' => 'Breakpointlos: So passt sich alles an', 'after' => 'besuch', 'file' => __DIR__ . '/manual/intrinsisch.php'],
        'design' => ['title' => 'Design, Kopf & Fuß', 'after' => 'einstellungen', 'file' => __DIR__ . '/manual/design.php'],
        'seiten' => ['mode' => 'after', 'file' => __DIR__ . '/manual/seiten.php'],
    ],
    'vars' => [
        'settings_sub' => 'Name, Adresse, Öffnungszeiten, Ausnahmen, weitere Orte und Anfragen – einmal eintragen, überall aktuell.',
        'blocks_page' => '/',
    ],
    // Beschreibungen im Kapitel „Alle Blöcke“ (übrige Blöcke: Beschreibung aus der Blockdefinition)
    'blocks' => [
        'exhibition_feature' => 'Die laufende Ausstellung groß – oder die nächste, wenn gerade keine läuft. „Jetzt“/„Demnächst“, Daten, Eröffnung, Ort und Künstler kommen aus der Tabelle „Ausstellungen“. Vollbild mit Titel auf dem Bild, Bild und Text nebeneinander oder typografisch.',
        'exhibitions_list' => 'Ausstellungen mit berechnetem Status: Reiter „Aktuell · Demnächst · Archiv“ oder Abschnitte, als Liste, Karten, Zeitleiste nach Jahren oder „Messen & Termine“. Auf Künstler-Detailseiten automatisch nur deren Ausstellungen.',
        'exhibition_detail' => 'Für die Detailseite einer Ausstellung: Titel, Status, Daten, Eröffnung, Ort, Künstler, Text, Pressetext, Ausstellungsansichten und Videos (vergrößerbar bzw. per Klick abspielbar) und die gezeigten Werke.',
        'artists_index' => 'Das Programm: typografische Liste mit Bild beim Zeigen, Raster mit Porträts oder einem Werk, A–Z-Register.',
        'artist_profile' => 'Für die Detailseite eines Künstlers: Name, Porträt, Geboren/Lebt, Biografie, Website, Anfrage, Bilder und Videos.',
        'artworks' => 'Werke mit Museumsschild, Verfügbarkeit (Punkt + Wort) und Preis bzw. „Preis auf Anfrage“: Raster, Mauerwerk, Salonhängung oder Viewing Room. Filter für Besucher, Anfrage-Button. Im Editor: „Neues Werk aus Foto“.',
        'artwork_detail' => 'Für die Detailseite eines Werks: Abbildung vergrößerbar, weitere Ansichten und Videos, alle Angaben, Verfügbarkeit, Preis, Anfrage, weitere Werke derselben Person.',
        'visit' => 'Öffnungszeiten mit „Heute geöffnet/geschlossen“, abweichende Zeiten (Feiertage), alle Orte mit Kartenlink, Eintritt, Termine nach Vereinbarung – alles aus ' . $settingsTitle . '.',
        'hero' => 'Hauptüberschrift der Seite (H1): Text + Bild, zentriert, Vollbild (auch stummes Video), mit Karten, großer Schriftzug, Seitenkopf, Collage. Ein Wort in *Sternchen* wird hervorgehoben.',
        'richtext' => 'Freier Text mit Zwischenüberschriften und Listen; „Artikel“ mit Inhaltsverzeichnis, „Mehrspaltig“ im Zeitungssatz.',
        'media_text' => 'Text mit Bild daneben – abwechselnd, Split-Screen oder Text-Karte über großem Bild.',
        'features' => 'Leistungen (Beratung, Rahmung, Leihgaben, Editionen) mit Symbol oder Bild.',
        'cards' => 'Teaser mit Bild – z. B. für Editionen oder Veranstaltungen.',
        'logos' => 'Partner, Leihgeber, Förderer als Raster oder Laufband.',
        'quote' => 'Zitat oder Pressestimmen – nur echte, freigegebene Stimmen verwenden.',
        'steps' => 'Geschichte der Galerie als Zeitleiste oder Ablauf (z. B. „So kaufen Sie ein Werk“).',
        'faq' => 'Fragen zu Besuch, Kauf, Versand, Leihgaben – aufklappbar, mit strukturierten Daten.',
        'cta' => 'Handlungsaufruf: Besuch planen, Newsletter, Vernissage.',
        'tabs' => 'Inhalte zum Umschalten, z. B. Leistungen.',
        'video' => 'YouTube, Vimeo (erst nach Klick) oder eigenes MP4 mit Untertiteln und Transkript.',
        'contact' => 'Adresse, Kontakt, Öffnungszeiten, Karte und Formular. Mit ?werk=… (Button „Anfrage zu diesem Werk“) ist das Feld „Werk“ vorausgefüllt.',
        'downloads' => 'Dateien zum Herunterladen, z. B. Preislisten oder Kataloge (PDF im Browser ansehen).',
        'map' => 'Karte mit dem Standort der Galerie oder einem eigenen Ort – ohne Cookies.',
        'gallery' => 'Bilder als Raster, Mosaik oder Zeilen – mit Lightbox.',
        'data_list' => 'Einträge einer Datentabelle als Karten, Liste, kompakt oder Tabelle.',
        'data_fields' => 'Für eigene Detailseiten-Vorlagen: Felder des aufgerufenen Eintrags.',
        'data_form' => 'Öffentliches Formular zu einer Datentabelle (z. B. Anfragen, Newsletter für Vernissagen).',
        'calendar' => 'Termine als Monatsübersicht oder Liste.',
        'upcoming' => 'Die nächsten Termine ab heute.',
    ],
];
