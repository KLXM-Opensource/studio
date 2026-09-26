<?php
/**
 * Handbuch des Themes „essenz“ – ergänzt das Handbuch des Cores (app/Admin/views/help/manual.php):
 * eigene Kapitel in docs/manual/*.php, alle übrigen Kapitel kommen aus dem Core.
 */
$settingsTitle = app()->theme->settingsTitle();
return [
    'hero' => [
        'eyebrow' => 'Handbuch für die Redaktion · Kit „Essenz“',
        'title' => 'Weniger, aber besser<i>.</i><br>Auch beim Pflegen.',
        'lead' => 'Texte ändern, Seiten aus Bausteinen zusammensetzen, eine Signalfarbe wählen – das Kit sorgt für Ordnung, Abstände und Lesbarkeit auf jedem Bildschirm.',
    ],
    'figures' => false,
    'chapters' => [
        'start' => ['mode' => 'after', 'file' => __DIR__ . '/manual/start.php'],
        'essenz' => ['title' => 'Das Gestaltungsprinzip', 'after' => 'start', 'file' => __DIR__ . '/manual/essenz.php'],
        'design' => ['title' => 'Design, Kopf & Fuß', 'after' => 'einstellungen', 'file' => __DIR__ . '/manual/design.php'],
    ],
    'vars' => [
        'settings_sub' => 'Name, Adresse, Telefon, Öffnungszeiten und Logo – einmal eintragen, überall aktuell.',
        'blocks_page' => '/werkstatt',
    ],
    // Beschreibungen im Kapitel „Alle Blöcke“ (übrige Blöcke: Beschreibung aus der Blockdefinition)
    'blocks' => [
        'hero' => 'Hauptüberschrift der Seite (H1) in sieben Varianten: mit Geräte-Paneel (Bild oder – ohne Bild – ein Punktraster mit Regler, darunter nummerierte Kennwerte), große Aussage, Text + Bild, Seitenkopf – und drei Geräte-Einstiege: „Bedienfeld“ (große Anzeige plus bis zu sechs Kanäle aus den Kennwerten; „87 %“ oder „4 / 5“ zeigen einen Pegel, andere Werte einen Drehregler), „Kennzahlen“ (Aussage oben, 3–4 Rundinstrumente in einem Paneel) und „Video im Geräterahmen“ (kurzes, stummes Loop-Video aus der Mediathek im Monitor, mit Abdunkelung und Pause-Taste; bei „Bewegung reduzieren“ steht das Standbild). Beispiele: Werkstatt → Hero-Varianten. Ein Wort in *Sternchen* erhält die Signalfarbe. „Bezeichnung: Wert“ in den Kennwerten ergibt eine zweispaltige Zeile.',
        'richtext' => 'Freier Text mit Zwischenüberschriften (H2–H4), Zitaten, Listen und Links. „Artikel“: Lesebreite, Lesezeit und automatisches Inhaltsverzeichnis aus den Zwischenüberschriften.',
        'media_text' => 'Text mit Bild daneben. „Abwechselnd“: mehrere Blöcke hintereinander wechseln automatisch die Seite.',
        'features' => 'Leistungen oder Vorteile mit Symbol – als Bedienfeld (ein Paneel, feine Trennlinien, Nummern), einzelne Karten oder Liste. Statt Spaltenzahl wählen Sie die Mindestbreite je Eintrag.',
        'principles' => 'Nummerierte Grundsätze oder Thesen: als ruhige Liste (Nummer, Titel, Erläuterung), als Raster aus Paneelen oder aufklappbar.',
        'specs' => 'Datenblatt: Bezeichnung und Wert wie auf einem Typenschild, optional in Gruppen und mit Bild daneben.',
        'stats' => 'Kennzahlen als Rundinstrumente (Skalenstriche, optional ein Füllstand 0–100) oder als große Zahlen in einer Reihe mit schmalem Pegel. Nur belegbare Zahlen verwenden.',
        'steps' => 'Ablauf als Schritte auf einer Schiene (nebeneinander) oder als senkrechte Zeitleiste mit Phase/Datum.',
        'cards' => 'Produkte oder Leistungen: „Produkt“ zeigt das Bild vertieft auf ruhiger Fläche (ideal: freigestellte PNG), darunter Kennwert oder Preis und eine Pfeil-Taste. „Leistung“ kommt ohne Bild aus.',
        'quote' => 'Ein Zitat als ruhiger Blickfang mit Signal-Linie oder mehrere Stimmen im Raster.',
        'faq' => 'Aufklappbare, nummerierte Fragen – neben der Überschrift oder untereinander. Suchmaschinen erhalten sie als strukturierte Daten.',
        'cta' => 'Handlungsaufruf als Paneel, als Band (mit Hintergrund „Signalfarbe“ oder „Nachtpaneel“) oder zurückhaltend zentriert.',
        'contact' => 'Adresse, Telefon, E-Mail und ein Öffnungszeiten-Paneel mit Anzeige „Jetzt geöffnet“, dazu Karte und optional ein öffentliches Formular – alles aus den ' . $settingsTitle . '.',
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
        'upcoming' => 'Die nächsten Termine ab heute – mit Datumsanzeige wie auf einem Display.',
    ],
];
