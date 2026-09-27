<?php
/**
 * Handbuch des Themes „fluid“ – ergänzt das Handbuch des Cores (app/Admin/views/help/manual.php):
 * eigene Kapitel in docs/manual/*.php, alle übrigen Kapitel kommen aus dem Core.
 */
$settingsTitle = app()->theme->settingsTitle();
return [
    'hero' => [
        'eyebrow' => 'Handbuch für die Redaktion · Kit „Fluid“',
        'title' => 'Die Website pflegen<i>.</i><br>Auf jedem Bildschirm.',
        'lead' => 'Texte ändern, Seiten und Blöcke kombinieren, Design wählen – und sich nie um Handy- oder Tablet-Ansichten kümmern müssen: Jeder Block ordnet sich selbst nach dem Platz, den er hat.',
    ],
    'figures' => false,
    'chapters' => [
        'start' => ['mode' => 'after', 'file' => __DIR__ . '/manual/start.php'],
        'fluid' => ['title' => 'Breakpointlos: So passt sich alles an', 'after' => 'start', 'file' => __DIR__ . '/manual/fluid.php'],
        'design' => ['title' => 'Design, Kopf & Fuß', 'after' => 'einstellungen', 'file' => __DIR__ . '/manual/design.php'],
        'seiten' => ['mode' => 'after', 'file' => __DIR__ . '/manual/seiten.php'],
    ],
    'vars' => [
        'settings_sub' => 'Name, Adresse, Telefon, Öffnungszeiten und Logo – einmal eintragen, überall aktuell.',
        'blocks_page' => '/showcase',
    ],
    // Beschreibungen im Kapitel „Alle Blöcke“ (übrige Blöcke: Beschreibung aus der Blockdefinition)
    'blocks' => [
        'hero' => 'Hauptüberschrift der Seite (H1) in neun Varianten: Text + Bild, zentriert, vollflächiges Bild oder Video (mit Pause-Schaltfläche), mit gestapelten Karten, großer Schriftzug, Seitenkopf – dazu „Fließende Skala“ (die Überschrift füllt ihre Spalte; ist genug Platz, stehen Text und Buttons daneben, darunter bis zu vier Kennzahlen oder Schlagworte, jede Stufe eine Schriftgröße kleiner), „Collage“ (Bild plus bis zu vier weitere Bilder mit schwebender Textkarte; bei wenig Platz Kacheln) und „Vorher/Nachher“ (zwei Bilder mit Schieberegler, per Maus, Finger oder Pfeiltasten). Ein Wort in *Sternchen* wird hervorgehoben. Pro Seite genau einmal verwenden.',
        'richtext' => 'Freier Text mit Zwischenüberschriften und Listen. „Artikel“: Lesebreite, Lesezeit, optionale Initiale und automatisches Inhaltsverzeichnis aus den Zwischenüberschriften (steht neben dem Text, wenn Platz ist). „Mehrspaltig“: Zeitungssatz mit so vielen Spalten, wie passen.',
        'media_text' => 'Text mit Bild daneben. „Abwechselnd“: mehrere Blöcke hintereinander wechseln automatisch die Seite. „Geteilt“: randloser Split-Screen, mit Abschnitts-Option „Bildschirmhöhe“ bildschirmfüllend. „Überlappend“: Text-Karte über großem Bild.',
        'features' => 'Leistungen oder Vorteile mit Symbol (Symbolauswahl) oder Bild – als Karten, große Symbole, Liste oder nummeriert. Statt Spaltenzahl wählen Sie die Mindestbreite je Eintrag; wie viele nebeneinander passen, ergibt sich aus dem Platz.',
        'bento' => 'Kacheln in verschiedenen Größen (normal, breit, hoch, groß) und Flächen (Karte, Akzent hell, Zweitfarbe, Akzent, dunkel, Bild). Eine kurze Zahl als Dachzeile wird groß dargestellt. Kacheln rücken bei wenig Platz untereinander.',
        'cards' => 'Teaser mit Bild: Bild oben, Text auf dem Bild, quer (erst ab genug Kartenbreite) oder als wischbares Band mit Pfeilen. Ohne Linktext ist die ganze Karte klickbar.',
        'logos' => 'Logos oder Namen als ruhiges Raster oder als Laufband. Das Laufband hält bei Maus und Tastaturfokus an und steht bei „Bewegung reduzieren“ still.',
        'stats' => 'Kennzahlen als große Zahlen in einer Reihe, als Karten oder neben der Überschrift. Nur belegbare Zahlen verwenden.',
        'quote' => 'Ein großes Zitat (Pull-Quote), mehrere Stimmen als Mauerwerk oder als wischbares Band – mit Name, Funktion und optionalem Porträt.',
        'steps' => 'Ablauf als nummerierte Schritte, Zeitleiste (Datum links, sobald Platz ist) oder Prozess mit Pfeilen (bei einer Spalte zeigen sie nach unten).',
        'pricing' => 'Pakete als Karten mit Leistungsliste oder als Vergleichstabelle (ja/nein/Text je Paket; die erste Spalte bleibt beim Scrollen stehen). Ein Paket lässt sich hervorheben.',
        'faq' => 'Aufklappbare Fragen – neben der Überschrift oder zentriert untereinander. Suchmaschinen erhalten die Fragen als strukturierte Daten.',
        'cta' => 'Handlungsaufruf als Band, Box, mit Bild oder als großer Schriftzug – mit bis zu zwei Buttons.',
        'tabs' => 'Inhalte zum Umschalten, Reiter oben oder links (rutschen bei wenig Platz nach oben). Ohne JavaScript stehen alle Inhalte untereinander.',
        'scrolly' => 'Scrollytelling: Das Bild bleibt stehen, die Abschnitte ziehen vorbei, das Bild wechselt mit. Auf schmalen Flächen steht jedes Bild bei seinem Text.',
        'video' => 'YouTube, Vimeo (erst nach Klick, ohne Cookies bis dahin) oder eigenes MP4 mit Untertiteln und Transkript aus der Mediathek – breit, mit Text daneben oder im Kino-Stil.',
        'contact' => 'Adresse, Telefon, E-Mail, Öffnungszeiten, Karte und optional ein öffentliches Formular – alles in einem Abschnitt, Angaben aus den ' . $settingsTitle . '.',
        'downloads' => 'Dateien zum Herunterladen; PDFs lassen sich zusätzlich im Browser ansehen.',
        'map' => 'Karte mit dem Standort aus den ' . $settingsTitle . ' oder einem eigenen Ort – ohne Cookies, ohne Einwilligung.',
        'gallery' => 'Bilder als Raster, Mosaik (so viele Spalten, wie passen) oder bündige Zeilen – mit Lightbox.',
        'slideshow' => 'Folien mit Bild und Text – Pfeile, Punkte, Tastatur und Wischen.',
        'stack_cards' => 'Karten, die sich beim Scrollen übereinanderschieben – nur mit genug Platz und ohne „Bewegung reduzieren“, sonst eine ruhige Liste.',
        'data_list' => 'Einträge einer Datentabelle als Karten, Liste, kompakt oder Tabelle. Tipp für Teams: Bildformat 1:1 oder 3:4 wählen – in der Liste werden daraus runde Porträts.',
        'data_fields' => 'Für Detailseiten-Vorlagen: Kopf (Titel groß, Datum, Bild), Fließtext, Steckbrief oder breites Bild des aufgerufenen Eintrags.',
        'data_form' => 'Öffentliches Formular zu einer Datentabelle – mit Pflichtfeldern, Bedingungen, IBAN-Prüfung, wiederholbaren Gruppen, Fehler- und Erfolgsmeldung und Spamschutz ohne Cookies.',
        'calendar' => 'Termine als Monatsübersicht (wird bei wenig Platz zur Liste der Tage mit Terminen) oder Terminliste – mit Filter und Abonnieren-Link.',
        'upcoming' => 'Die nächsten Termine ab heute – als Liste mit Datum oder kompakt.',
    ],
];
