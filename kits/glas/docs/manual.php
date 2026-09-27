<?php
/**
 * Handbuch des Kits „glas“ – ergänzt das Handbuch des Cores (app/Admin/views/help/manual.php):
 * eigene Kapitel in docs/manual/*.php, alle übrigen Kapitel kommen aus dem Core.
 */
$settingsTitle = app()->theme->settingsTitle();
return [
    'hero' => [
        'eyebrow' => 'Handbuch für die Redaktion · Kit „Glas“',
        'title' => 'Klar wie Glas<i>.</i><br>Auch beim Pflegen.',
        'lead' => 'Texte ändern, Seiten aus Bausteinen zusammensetzen, ein Farbfeld wählen – das Kit sorgt für Glas, Abstände und Lesbarkeit auf jedem Bildschirm.',
    ],
    'figures' => false,
    'chapters' => [
        'start' => ['mode' => 'after', 'file' => __DIR__ . '/manual/start.php'],
        'glas' => ['title' => 'Das Gestaltungsprinzip', 'after' => 'start', 'file' => __DIR__ . '/manual/glas.php'],
        'design' => ['title' => 'Design, Kopf & Fuß', 'after' => 'einstellungen', 'file' => __DIR__ . '/manual/design.php'],
    ],
    'vars' => [
        'settings_sub' => 'Name, Adresse, Telefon, Öffnungszeiten und Logo – einmal eintragen, überall aktuell.',
        'blocks_page' => '/labor',
    ],
    // Beschreibungen im Kapitel „Alle Blöcke“ (übrige Blöcke: Beschreibung aus der Blockdefinition)
    'blocks' => [
        'hero' => 'Hauptüberschrift der Seite (H1) in sieben Varianten: Aurora (Glaspaneel über einem sanft bewegten Farbfeld, daneben Glasplättchen mit Kennwerten, ein Bild im Glasrahmen oder ein gläsernes Prisma), Text und Bild, große Aussage mit Glas-Chips, Seitenkopf – und drei neue: Befehlssuche (großes gläsernes Suchfeld über der Aurora mit Vorschlägen beim Tippen und beliebten Begriffen als Chips; die Website-Suche muss eingeschaltet sein, ein Schalter hält die Bewegung an), Video im Hintergrund (eigenes stummes Loop-Video mit Abdunkelung, Text auf dichtem Glas, Pause-Schaltfläche, bei „Bewegung reduzieren“ nur das Standbild aus „Bild“) und Karten-Stapel (zwei bis drei Angebote als überlappende Glaskarten, die bei Zeiger oder Tastatur auffächern). Die Felder der neuen Varianten erscheinen nur, wenn die Variante gewählt ist. Ein Wort in *Sternchen* erhält die Akzentfarbe.',
        'richtext' => 'Freier Text mit Zwischenüberschriften (H2–H4), Zitaten, Listen und Links. „Artikel“: Lesebreite, Lesezeit und Inhaltsverzeichnis im Glaspaneel; „Auf einer Glasfläche“: der Text steht auf Glas.',
        'text_image' => 'Text mit Bild im Glasrahmen daneben. „Abwechselnd“: mehrere Blöcke hintereinander wechseln automatisch die Seite.',
        'features' => 'Leistungen oder Vorteile mit Symbol in einer Glasperle – als Glaskarten, ein gemeinsames Glaspaneel oder Liste. Statt Spaltenzahl wählen Sie die Mindestbreite je Eintrag.',
        'cards' => 'Produkte oder Leistungen: Glaskarte (Bild oben), Leistung (Symbol) oder Bild mit Glasleiste (Text schwebt auf Glas über dem Bild).',
        'stats' => 'Kennzahlen als Glasringe (optional mit Füllstand 0–100) oder große Zahlen auf Glas. Nur belegbare Zahlen verwenden.',
        'steps' => 'Ablauf als Glasperlen auf einer leuchtenden Linie (nebeneinander) oder als senkrechte Zeitleiste mit Phase/Datum.',
        'team' => 'Personen mit Rolle, kurzer Beschreibung und E-Mail – als Glaskarten oder kompakte Liste. Ohne Foto erscheinen Initialen in einer Glasperle.',
        'quote' => 'Ein Zitat auf einer großen Glasfläche oder mehrere Stimmen als Glaskarten.',
        'faq' => 'Aufklappbare Fragen als Glaszeilen – neben der Überschrift oder untereinander. Suchmaschinen erhalten sie als strukturierte Daten.',
        'cta' => 'Handlungsaufruf auf Glas vor einem kleinen Farbfeld, als Band (mit Hintergrund „Akzentfarbe“ oder „Nacht“) oder zurückhaltend zentriert.',
        'contact' => 'Adresse, Telefon, E-Mail und Öffnungszeiten auf Glas mit Anzeige „Jetzt geöffnet“, dazu Karte und optional ein öffentliches Formular – alles aus den ' . $settingsTitle . '.',
        'video' => 'YouTube, Vimeo (erst nach Klick bzw. Einwilligung im Cookie-Hinweis) oder eigenes MP4 mit Untertiteln und Transkript – im Glasrahmen.',
        'downloads' => 'Dateien zum Herunterladen als Glaszeilen; PDFs lassen sich zusätzlich im Browser ansehen.',
        'map' => 'Karte im Glasrahmen mit dem Standort aus den ' . $settingsTitle . ' oder einem eigenen Ort – ohne Cookies, ohne Einwilligung.',
        'gallery' => 'Bilder als Raster, Mosaik oder bündige Zeilen – mit Lichtkante und Lightbox.',
        'slideshow' => 'Folien mit Bild und Text – Pfeile, Punkte, Tastatur und Wischen.',
        'stack_cards' => 'Karten, die sich beim Scrollen übereinanderschieben – nur mit genug Platz und ohne „Bewegung reduzieren“.',
        'dials' => 'Kennzahlen mit Skala, Einheit und Höchstwert auf Glasflächen – zählen beim ersten Erscheinen einmal hoch.',
        'data_list' => 'Einträge einer Datentabelle als Glaskarten, Liste, kompakt oder Tabelle – mit Detailseite.',
        'data_fields' => 'Für Detailseiten-Vorlagen: Kopf, Fließtext, Datenblatt (Steckbrief) oder breites Bild des aufgerufenen Eintrags.',
        'data_form' => 'Öffentliches Formular zu einer Datentabelle – mit Pflichtfeldern, Bedingungen, Fehler- und Erfolgsmeldung und Spamschutz ohne Cookies.',
        'calendar' => 'Termine als Monatsübersicht (wird bei wenig Platz zur Liste) oder Terminliste – mit Filter und Abonnieren-Link.',
        'upcoming' => 'Die nächsten Termine ab heute.',
    ],
];
