<?php
/**
 * Handbuch des Kits „modern“ – ergänzt das Handbuch des Cores (app/Admin/views/help/manual.php):
 * eigene Kapitel in docs/manual/*.php, alle übrigen Kapitel kommen aus dem Core.
 */
$settingsTitle = app()->theme->settingsTitle();
return [
    'hero' => [
        'eyebrow' => 'Handbuch für die Redaktion · Kit „modern“',
        'title' => 'Die Website pflegen<i>.</i><br>Klar und selbstbewusst.',
        'lead' => 'Texte ändern, Seiten und Blöcke kombinieren, Design wählen: große Überschriften, kräftige Farbflächen und ein Bento-Raster – auf Handy, Tablet und Computer automatisch passend angeordnet.',
    ],
    'figures' => false,
    'chapters' => [
        'start' => ['mode' => 'after', 'file' => __DIR__ . '/manual/start.php'],
        'design' => ['title' => 'Design & Navigation', 'after' => 'einstellungen', 'file' => __DIR__ . '/manual/design.php'],
        'seiten' => ['mode' => 'after', 'file' => __DIR__ . '/manual/seiten.php'],
    ],
    'vars' => [
        'settings_sub' => 'Name, Adresse, Telefon, Öffnungszeiten und Logo – einmal eintragen, überall aktuell.',
        'blocks_page' => '/baukasten',
    ],
    // Beschreibungen im Kapitel „Alle Blöcke“ (übrige Blöcke: Beschreibung aus der Blockdefinition)
    'blocks' => [
        'hero' => 'Hauptüberschrift der Seite (H1) in sieben Varianten: „Geteilt“ (Text und Bild auf versetzter Farbfläche, optional mit Kennzahl-Karte), „Große Aussage“ (Schriftzug über die volle Breite), „Mosaik“ (Text neben einem Raster aus drei Bildern und einer Kennzahl), „Seitenkopf“ – dazu „Produkt“ (Produktbild mit rundem Preis-Sticker, dunkle Datenblatt-Karte aus Zeilen „Bezeichnung: Wert“ und zwei Detailbilder), „Typo“ (Riesenschrift ohne Bild und eine schräge Laufzeile mit Stichworten; sie hält per Schaltfläche, Maus oder Tastatur und steht bei „Bewegung reduzieren“ still) und „Kennzahlen“ (Aussage mit 3–4 belegbaren Zahlen als farbige Kacheln mit Rundinstrument). Ein Wort in *Sternchen* wird hervorgehoben. Pro Seite genau einmal verwenden. Alle Varianten nebeneinander: Baukasten → Hero-Varianten.',
        'richtext' => 'Freier Text mit Zwischenüberschriften, Listen und Zitaten. „Artikel“: Lesebreite, Lesezeit, optionale Initiale und automatisches Inhaltsverzeichnis. „Mehrspaltig“: Text in Spalten.',
        'media_text' => 'Text mit Bild daneben. „Abwechselnd“: mehrere Blöcke hintereinander wechseln automatisch die Seite. „Geteilt“: randlos halb und halb. „Überlappend“: Text-Karte über großem Bild.',
        'features' => 'Leistungen oder Vorteile mit Symbol oder Bild – als Karten, große Symbole, Liste oder nummeriert.',
        'bento' => 'Kacheln in verschiedenen Größen (normal, breit, hoch, groß, ganze Breite) und Flächen (Karte, Akzent hell, Blockfarbe, Akzent, dunkel, Bild). Eine kurze Zahl als Dachzeile wird groß dargestellt. Auf dem Handy stehen die Kacheln untereinander.',
        'cards' => 'Teaser mit Bild: Bild oben, Text auf dem Bild oder quer. Ohne Linktext ist die ganze Karte klickbar.',
        'team' => 'Personen mit Porträt, Funktion, Kurzvorstellung, E-Mail und Profil-Link – als Porträt-Raster oder kompakte Liste. Ohne Foto erscheinen die Initialen auf einer Farbfläche.',
        'logos' => 'Logos oder Namen als ruhiges Raster oder als Laufband. Das Laufband hält bei Maus und Tastaturfokus an und steht bei „Bewegung reduzieren“ still.',
        'stats' => 'Kennzahlen als große Zahlen mit Blockfarben-Unterstrich, als Karten oder neben der Überschrift. Rundinstrumente mit Skala: Kern-Block „Kennzahlen mit Skala“.',
        'quote' => 'Ein großes Zitat mit Anführungszeichen in Blockfarbe oder mehrere Stimmen im Raster – mit Name, Funktion und optionalem Porträt.',
        'steps' => 'Ablauf als nummerierte Schritte, Zeitleiste (Datum links, ab Tablet) oder Prozess mit Pfeilen.',
        'pricing' => 'Angebote als Karten mit Leistungsliste oder als Vergleichstabelle (ja/nein/Text je Angebot). Ein Angebot lässt sich hervorheben.',
        'faq' => 'Aufklappbare Fragen – neben der Überschrift oder zentriert untereinander. Suchmaschinen erhalten die Fragen als strukturierte Daten.',
        'cta' => 'Handlungsaufruf als Band, Farbfläche (Box), mit Bild oder als großer Schriftzug – mit bis zu zwei Buttons.',
        'tabs' => 'Inhalte zum Umschalten, Reiter oben oder links. Ohne JavaScript stehen alle Inhalte untereinander.',
        'video' => 'YouTube, Vimeo (erst nach Klick, ohne Cookies bis dahin; mit der Einwilligungs-Verwaltung abgestimmt) oder eigenes MP4 mit Untertiteln und Transkript – breit, mit Text daneben oder im Kino-Stil.',
        'contact' => 'Adresse, Telefon, E-Mail, Öffnungszeiten mit „Jetzt geöffnet“, Karte und optional ein öffentliches Formular – Angaben aus den ' . $settingsTitle . '.',
        'downloads' => 'Dateien zum Herunterladen; PDFs lassen sich zusätzlich im Browser ansehen.',
        'map' => 'Karte mit dem Standort aus den ' . $settingsTitle . ' oder einem eigenen Ort – ohne Cookies, ohne Einwilligung.',
        'gallery' => 'Bilder als Raster, Mosaik oder bündige Zeilen – mit Lightbox.',
        'slideshow' => 'Folien mit Bild und Text – Pfeile, Punkte, Tastatur und Wischen.',
        'stack_cards' => 'Karten, die sich beim Scrollen übereinanderschieben – nur mit genug Platz und ohne „Bewegung reduzieren“, sonst eine ruhige Liste.',
        'dials' => 'Kennzahlen als Rundinstrument mit Skala – auf dunklen Flächen mit Bogen in Blockfarbe.',
        'data_list' => 'Einträge einer Datentabelle als Karten, Liste, kompakt oder Tabelle – mit Detailseiten.',
        'data_fields' => 'Für Detailseiten-Vorlagen: Kopf, Fließtext, Steckbrief oder breites Bild des aufgerufenen Eintrags.',
        'data_form' => 'Öffentliches Formular zu einer Datentabelle – mit Pflichtfeldern, Bedingungen, Fehler- und Erfolgsmeldung und Spamschutz ohne Cookies.',
        'calendar' => 'Termine als Monatsübersicht oder Terminliste – mit Filter und Abonnieren-Link.',
        'upcoming' => 'Die nächsten Termine ab heute – als Liste mit Datum oder kompakt.',
    ],
];
