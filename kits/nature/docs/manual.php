<?php
/**
 * Handbuch des Kits „nature“ – ergänzt das Handbuch des Cores (app/Admin/views/help/manual.php):
 * eigene Kapitel in docs/manual/*.php, alle übrigen Kapitel kommen aus dem Core.
 */
$settingsTitle = app()->theme->settingsTitle();
return [
    'hero' => [
        'eyebrow' => 'Handbuch für die Redaktion · Kit „nature“',
        'title' => 'Draußen zu Hause<i>.</i><br>Auch beim Pflegen.',
        'lead' => 'Texte ändern, Seiten aus Bausteinen zusammensetzen, eine Jahreszeit wählen – das Kit sorgt für Ruhe, Abstände und Lesbarkeit auf jedem Bildschirm.',
    ],
    'figures' => false,
    'chapters' => [
        'start' => ['mode' => 'after', 'file' => __DIR__ . '/manual/start.php'],
        'nature' => ['title' => 'Das Gestaltungsprinzip', 'after' => 'start', 'file' => __DIR__ . '/manual/nature.php'],
        'design' => ['title' => 'Design, Kopf & Fuß', 'after' => 'einstellungen', 'file' => __DIR__ . '/manual/design.php'],
    ],
    'vars' => [
        'settings_sub' => 'Name, Adresse, Anfahrt, Telefon, Öffnungs- und Saisonzeiten und Logo – einmal eintragen, überall aktuell.',
        'blocks_page' => '/erleben',
    ],
    // Beschreibungen im Kapitel „Alle Blöcke“ (übrige Blöcke: Beschreibung aus der Blockdefinition)
    'blocks' => [
        'hero' => 'Hauptüberschrift der Seite (H1) in sieben Varianten: mit Bildtafel (Bild oder – ohne Bild – eine gezeichnete Landschaft im Stil der Jahreszeit, darunter Eckdaten), große Aussage, Text + Bild und Seitenkopf. Dazu drei Einstiege, die das Wichtigste nach oben holen: „Jahreszeiten-Bühne“ – ein gezeichnetes Panorama der Jahreszeit (automatisch nach Monat oder fest gewählt) mit einem Hofschild, das „Jetzt geöffnet“ bzw. „Zurzeit geschlossen“ und die Öffnungszeiten aus „Website“ zeigt; „Termine am Ast“ – die nächsten 2–4 Termine einer Datentabelle hängen als Anhänger an einem Ast; „Papierkarte“ – Text mit Pluspunkten neben einem kompakten öffentlichen Formular (z. B. Gruppenanfrage). Ein Wort in *Sternchen* erscheint kursiv in der Akzentfarbe. „Bezeichnung: Wert“ in den Eckdaten ergibt eine zweispaltige Zeile. Muster: Erleben → Hero-Varianten.',
        'richtext' => 'Freier Text mit Zwischenüberschriften (H2–H4), Zitaten, Listen und Links. „Artikel“: Lesebreite, Lesezeit und automatisches Inhaltsverzeichnis aus den Zwischenüberschriften.',
        'media_text' => 'Text mit Bild daneben. „Abwechselnd“: mehrere Blöcke hintereinander wechseln automatisch die Seite.',
        'features' => 'Leistungen oder Angebote mit Symbol im Kiesel – als Beet (eine gemeinsame Fläche mit feinen Linien), einzelne Karten oder Liste. Statt Spaltenzahl wählen Sie die Mindestbreite je Eintrag.',
        'principles' => 'Nummerierte Grundsätze oder Versprechen: als ruhige Liste, als Raster aus Karten oder aufklappbar.',
        'specs' => 'Steckbrief: Bezeichnung und Wert als ruhige Tabelle, optional in Gruppen und mit Bild daneben.',
        'stats' => 'Kennzahlen als Jahresringe (Samenpunkte als Skala, optional ein Füllstand 0–100) oder als große Zahlen in einer Reihe mit schmalem Pegel. Nur belegbare Zahlen verwenden.',
        'steps' => 'Ablauf als Schritte auf einem Pfad aus Samenpunkten (nebeneinander) oder als senkrechte Zeitleiste mit Phase/Datum – ideal für Hofgeschichte oder Jahreslauf.',
        'cards' => 'Produkte oder Angebote: „Produkt“ zeigt ein freigestelltes Bild auf ruhiger Fläche, darunter Preis und Pfeil. „Angebot“ kommt ohne Bild aus.',
        'quote' => 'Ein Zitat als große kursive Serif mit Blattzeichen oder mehrere Stimmen im Raster.',
        'faq' => 'Aufklappbare Fragen – neben der Überschrift oder untereinander. Suchmaschinen erhalten sie als strukturierte Daten.',
        'cta' => 'Handlungsaufruf als Karte mit Blattmotiv, als Band (mit Hintergrund „Akzentfarbe“ oder „Waldnacht“) oder zurückhaltend zentriert.',
        'contact' => 'Adresse mit Anfahrt, Telefon, E-Mail, Öffnungszeiten mit Anzeige „Jetzt geöffnet“ und Saisonzeiten, dazu Karte und optional ein öffentliches Formular – alles aus den ' . $settingsTitle . '.',
        'team' => 'Menschen hinter dem Betrieb: Foto oder Monogramm in einer Kieselform, Aufgabe, Kurztext und E-Mail – als Karten oder Liste.',
        'video' => 'YouTube, Vimeo (erst nach Klick, ohne Cookies bis dahin) oder eigenes MP4 mit Untertiteln und Transkript aus der Mediathek.',
        'downloads' => 'Dateien zum Herunterladen; PDFs lassen sich zusätzlich im Browser ansehen.',
        'map' => 'Karte mit dem Standort aus den ' . $settingsTitle . ' oder einem eigenen Ort – ohne Cookies, ohne Einwilligung.',
        'gallery' => 'Bilder als Raster, Mosaik oder bündige Zeilen – mit Lightbox.',
        'slideshow' => 'Folien mit Bild und Text – Pfeile, Punkte, Tastatur und Wischen.',
        'stack_cards' => 'Karten, die sich beim Scrollen übereinanderschieben – nur mit genug Platz und ohne „Bewegung reduzieren“.',
        'data_list' => 'Einträge einer Datentabelle als Karten, Liste, kompakt oder Tabelle – mit Detailseite.',
        'data_fields' => 'Für Detailseiten-Vorlagen: Kopf, Fließtext, Datenblatt (Steckbrief) oder breites Bild des aufgerufenen Eintrags.',
        'data_form' => 'Öffentliches Formular zu einer Datentabelle – mit Pflichtfeldern, Bedingungen, IBAN-Prüfung, Gruppen, Fehler- und Erfolgsmeldung und Spamschutz ohne Cookies.',
        'calendar' => 'Termine als Monatsübersicht (wird bei wenig Platz zur Liste) oder Terminliste – mit Filter und Abonnieren-Link.',
        'upcoming' => 'Die nächsten Termine ab heute – mit Datumsmarke, Treffpunkt und Abonnieren-Link.',
    ],
];
