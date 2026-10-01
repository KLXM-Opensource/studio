<?php
/**
 * Handbuch des Themes „praxis“ – ergänzt das Handbuch des Cores (app/Admin/views/help/manual.php):
 * eigene Kapitel in docs/manual/*.php, alle übrigen Kapitel kommen aus dem Core.
 */
$settingsTitle = app()->theme->settingsTitle();
return [
    'hero' => [
        'eyebrow' => 'Handbuch für das Praxisteam',
        'title' => 'Die Website pflegen<i>.</i><br>Einfach und sicher.',
        'lead' => 'Alles, was Sie für die tägliche Arbeit brauchen: Texte ändern, Sprechzeiten und Urlaub eintragen, Bilder hochladen und Online-Anfragen lesen – Schritt für Schritt erklärt.',
    ],
    'chapters' => [
        'einstellungen' => ['id' => 'praxisdaten', 'title' => $settingsTitle, 'file' => __DIR__ . '/manual/praxisdaten.php'],
        'bearbeiten' => ['mode' => 'after', 'file' => __DIR__ . '/manual/bearbeiten.php'],
        'anfragen' => ['file' => __DIR__ . '/manual/anfragen.php'],
        'aufgaben' => ['mode' => 'before', 'file' => __DIR__ . '/manual/aufgaben.php'],
        'regeln' => ['file' => __DIR__ . '/manual/regeln.php'],
    ],
    'vars' => [
        'settings_sub' => 'Telefon, Sprechzeiten, Urlaub, Doctolib – einmal eintragen, überall aktuell.',
        'blocks_page' => '/bausteine',
        'image_sizes' => [
            ['Porträt Ärztin/Arzt', '1:1 (quadratisch)', '1200 × 1200 px'],
            ['Teamfoto (Block „Bild breit“)', '16:9', '2400 px breit'],
            ['Bild neben Text', '4:3, 1:1, 3:4 oder 16:9', '1600 px breit'],
            ['Bild breit', '21:9 oder 16:9', '2400 px breit'],
            ['Downloads', 'PDF', 'max. ' . (int) app()->config->get('media.max_upload_mb', 50) . ' MB'],
        ],
        'mcp_examples' => [
            ['„Trag Urlaub vom 21.–25.10. ein, Vertretung Praxis Muster.“', 'setzt den Hinweisbalken'],
            ['„Mittwochs haben wir ab November bis 12 Uhr geöffnet.“', 'ändert die Sprechzeiten'],
            ['„Welche Platzhalter sind noch offen?“', 'erstellt eine Prüfliste'],
        ],
    ],
    // Beschreibungen im Kapitel „Alle Blöcke“ (Kern-Blöcke: Beschreibung aus der Blockdefinition)
    'blocks' => [
        'hero' => 'Großer Kopfbereich mit wechselnden Themen und der Kontaktkarte (Termin, Rezept, Überweisung). Inhalte kommen aus den ' . $settingsTitle . ' → Hero-Themen.',
        'quick_contact' => 'Die Kontaktkarte als eigener Abschnitt – z. B. auf Unterseiten.',
        'teaser_tiles' => 'Zwei bis vier verlinkte Kacheln, wahlweise mit Bild.',
        'text_image' => 'Text mit Bild daneben (Bild rechts oder links, 4:3 · 1:1 · 3:4 · 16:9), optional Aufzählung und Button.',
        'text_video' => 'Text mit Video. YouTube/Vimeo laden erst nach Klick (Datenschutz); eigene MP4-Videos mit Untertiteln.',
        'quote' => 'Großes Zitat – eingerückt oder als volle Fläche.',
        'text_columns' => 'Überschrift mit Fließtext – daneben, in Spalten oder kompakt.',
        'richtext' => 'Freier Fließtext mit Zwischenüberschriften (H2–H4), Listen und Links – z. B. für Stellenanzeigen, Erläuterungen und Rechtstexte. Breite wählbar: Textbreite oder breit.',
        'services' => 'Nummerierte Leistungsliste (01, 02 …).',
        'doctors' => 'Karten für Ärztinnen und Ärzte mit Foto, Fach, Qualifikationen.',
        'team_photo' => 'Veraltet – nicht mehr einfügbar. Stattdessen „Bild breit“ (Teamfoto), „Fließtext“ und daneben „Handlungsaufruf“ als Box (Abschnitt & Navigation → „Neben den vorigen Block stellen“, ⅓).',
        'people' => 'Kompakte Personenliste (z. B. Praxisteam, MFA).',
        'image_wide' => 'Breites Bild mit Bildunterschrift und Fotonachweis.',
        'steps' => 'Ablauf in 3–5 Schritten (z. B. „Ihr erster Besuch“).',
        'accordion' => 'Aufklappbare Fragen & Antworten, optional mit Notfall-Box.',
        'notice' => 'Hinweisboxen „Info“ oder „Wichtig“.',
        'cta' => 'Auffälliges Band mit bis zu zwei Buttons (z. B. „Termin buchen“) – oder als Box/Karte mit Titel, Text und Button, z. B. neben einem Text (Ausbildung, Stellen).',
        'downloads' => 'Liste mit PDF-Downloads – Größe und Typ erscheinen automatisch.',
        'job' => 'Stellenangebot mit Schlagworten und Bewerbungs-Button.',
        'contact' => 'Kontaktbereich mit Telefon, Sprechzeiten, Anfahrt und Karte – alles aus den ' . $settingsTitle . '.',
        'map' => 'Karte mit dem Standort der Praxis oder einem eigenen Ort – über den eigenen Server geladen, ohne Cookies und ohne Einwilligung.',
        'video' => 'YouTube-/Vimeo-Video in voller Breite – lädt erst nach Klick, Vorschaubild datenschutzfreundlich vom eigenen Server.',
    ],
];
