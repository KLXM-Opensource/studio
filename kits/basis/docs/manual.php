<?php
/**
 * Handbuch des Themes „basis“ – ergänzt das Handbuch des Cores (app/Admin/views/help/manual.php):
 * eigene Kapitel in docs/manual/*.php, alle übrigen Kapitel kommen aus dem Core.
 */
$settingsTitle = app()->theme->settingsTitle();
return [
    'hero' => [
        'eyebrow' => 'Handbuch für die Redaktion · Kit „Basis“',
        'title' => 'Die Website pflegen<i>.</i><br>Klar und sicher.',
        'lead' => 'Texte ändern, Seiten anlegen, Bilder hochladen und zentrale Angaben wie Adresse oder Öffnungszeiten pflegen – Schritt für Schritt erklärt.',
    ],
    // Bildschirmfotos des Kerns zeigen ein anderes Design – hier ausblenden
    'figures' => false,
    'chapters' => [
        'start' => ['mode' => 'after', 'file' => __DIR__ . '/manual/start.php'],
        'einstellungen' => ['file' => __DIR__ . '/manual/einstellungen.php'],
        'design' => ['title' => 'Design & Navigation', 'after' => 'einstellungen', 'file' => __DIR__ . '/manual/design.php'],
        'seiten' => ['mode' => 'after', 'file' => __DIR__ . '/manual/seiten.php'],
        'medien' => ['mode' => 'after', 'file' => __DIR__ . '/manual/medien.php'],
        'aufgaben' => ['mode' => 'before', 'file' => __DIR__ . '/manual/aufgaben.php'],
        'regeln' => ['file' => __DIR__ . '/manual/regeln.php'],
        'faq' => ['mode' => 'after', 'file' => __DIR__ . '/manual/faq.php'],
    ],
    'vars' => [
        'settings_sub' => 'Name, Adresse, Telefon, Öffnungszeiten und Logo – einmal eintragen, überall aktuell.',
        'blocks_page' => '/baukasten',
    ],
    // Beschreibungen im Kapitel „Alle Blöcke“ (übrige Blöcke: Beschreibung aus der Blockdefinition)
    'blocks' => [
        'hero' => 'Große Überschrift der Seite (H1) mit Text, bis zu zwei Buttons und optionalem Bild. Varianten: nebeneinander, zentriert, Seitenkopf – und mit Werkzeug: Such-Einstieg (Suchfeld mit Vorschlägen und häufigen Begriffen), mit Formular (z. B. Rückruf direkt im Einstieg) und Standort (Karte mit Anschrift, „jetzt geöffnet“ und Öffnungszeiten aus der Website). Pro Seite genau einmal verwenden; welche Variante wann passt, steht unter der Tabelle.',
        'richtext' => 'Freier Text mit Zwischenüberschriften, Listen und Links – z. B. für Impressum und Datenschutz.',
        'text_image' => 'Text mit Bild daneben (rechts oder links), optional Häkchen-Liste und Button. Ohne Bild steht der Text allein.',
        'features' => 'Leistungen, Vorteile oder Angebote mit Symbol oder Bild – als Raster mit Linien, Karten oder schlicht, 2–4 Spalten.',
        'stats' => 'Bis zu vier Kennzahlen mit Bezeichnung. Nur belegbare Zahlen verwenden.',
        'quote' => 'Ein Zitat groß oder zwei bis drei Stimmen als Karten – mit Name, Funktion und optionalem Porträt.',
        'faq' => 'Aufklappbare Fragen und Antworten, Überschrift links, Liste rechts.',
        'cta' => 'Auffälliges Band (Standard: Akzentfarbe) oder Box mit Überschrift, Text und bis zu zwei Buttons.',
        'logos' => 'Logos von Partnern oder Kunden; ohne Bild erscheint der Name als Schriftzug.',
        'contact' => 'Adresse, Telefon, E-Mail, Öffnungszeiten und Karte – alles automatisch aus den ' . $settingsTitle . '.',
        'downloads' => 'Dateien zum Herunterladen; PDFs lassen sich zusätzlich im Browser ansehen.',
        'pricing' => 'Zwei bis vier Leistungspakete mit Preis, Leistungsliste und Button; eines lässt sich als „Empfohlen“ hervorheben. Nur verbindliche Preise angeben.',
        'steps' => 'Ablauf in nummerierten Schritten nebeneinander oder als Zeitleiste untereinander (mit Phase oder Datum).',
        'tabs' => 'Mehrere Inhalte auf engem Raum zum Umschalten – Reiter oben oder links, optional mit Bild. Ohne JavaScript stehen die Inhalte untereinander.',
        'video' => 'YouTube-, Vimeo- oder eigenes MP4-Video, breit oder mit Text daneben. Externe Videos laden erst nach einem Klick (Zwei-Klick-Lösung, ohne Cookies bis dahin).',
        'gallery' => 'Bilder als Raster, Mosaik oder bündige Zeilen – einzeln ausgewählt oder aus einer Sammlung; ein Klick öffnet die Lightbox.',
        'slideshow' => 'Bilder oder Folien mit Text nacheinander – mit Pfeilen, Punkten, Tastatur und Wischen.',
        'stack_cards' => 'Karten mit Text und Bild, die sich beim Scrollen übereinanderschieben.',
        'calendar' => 'Termine einer Kalender-Tabelle als Monatsübersicht oder Terminliste, mit Filter und Abonnieren-Link.',
        'upcoming' => 'Die nächsten Termine ab heute – als Liste mit Datum oder kompakt.',
        'data_form' => 'Öffentliches Formular zu einer Datentabelle – mit Pflichtfeldern, Bedingungen, IBAN-Prüfung, wiederholbaren Gruppen und Spamschutz ohne Cookies.',
        'map' => 'Karte mit dem Standort aus den ' . $settingsTitle . ' oder einem eigenen Ort – ohne Cookies, ohne Einwilligung.',
        'data_list' => 'Einträge einer Datentabelle (z. B. Aktuelles, Team, Projekte) als Karten, Liste oder Tabelle.',
        'data_fields' => 'Für Detailseiten-Vorlagen: zeigt Felder des aufgerufenen Eintrags.',
    ],
];
