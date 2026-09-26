<?php
/**
 * Handbuch des Themes „editorial“ – ergänzt das Handbuch des Cores (app/Admin/views/help/manual.php):
 * eigene Kapitel in docs/manual/*.php, alle übrigen Kapitel kommen aus dem Core.
 */
$settingsTitle = app()->theme->settingsTitle();
return [
    'hero' => [
        'eyebrow' => 'Handbuch für die Redaktion · Kit „Editorial“',
        'title' => 'Die Ausgabe gestalten<i>.</i><br>Nachricht für Nachricht.',
        'lead' => 'Nachrichten schreiben, Termine pflegen, Rubriken anlegen und die Startseite wie eine Titelseite zusammenstellen – Schritt für Schritt erklärt.',
    ],
    'figures' => false,
    'chapters' => [
        'start' => ['mode' => 'after', 'file' => __DIR__ . '/manual/start.php'],
        'design' => ['title' => 'Design, Kopf & Raster', 'after' => 'einstellungen', 'file' => __DIR__ . '/manual/design.php'],
        'redaktion' => ['title' => 'Redaktion: Nachrichten, Termine, Menschen', 'after' => 'design', 'file' => __DIR__ . '/manual/redaktion.php'],
    ],
    'vars' => [
        'settings_sub' => 'Name, Unterzeile, Ausgabe, Adresse und Logo – einmal eintragen, im Titelkopf und im Fußbereich aktuell.',
        'blocks_page' => '/',
    ],
    'blocks' => [
        'hero' => 'Große Schlagzeile der Seite (H1): Aufmacher mit Bild, Plakat, Titelbild (bildschirmfüllend), Ressortkopf mit Unterseiten als Reiter – oder neu: Titelgeschichte (Ausgabe-Zeile, Hochformat-Bild und „Außerdem in dieser Ausgabe“ mit zwei, drei Anrissen), Aktuell (die nächsten Termine oder neuesten Meldungen einer Datentabelle in Zeitungsspalten, aktualisiert sich selbst) und Stimme (großes Zitat mit Initialen statt Porträt, optional Bewertung – nur echte, freigegebene Stimmen). Pro Seite genau einmal.',
        'richtext' => 'Lesetext in Lesebreite – mit Initiale, Inhaltsverzeichnis aus den Zwischenüberschriften und Randnotiz.',
        'text_image' => 'Text mit Bild daneben (rechts oder links), optional Aufzählung und Link.',
        'figure' => 'Einzelnes Bild in Textbreite, breit, randlos oder mit Bildunterschrift in der Randspalte – Nachweis aus der Mediathek.',
        'features' => 'Rubriken, Angebote oder Arbeitsbereiche mit Symbol oder Bild – als Raster, Register mit großen Nummern, Kästen oder schlicht.',
        'teasers' => 'Von Hand zusammengestellte Anreißer: Raster, „erster groß“, nummerierte Liste („Meistgelesen“) oder „Kurz notiert“.',
        'stats' => 'Bis zu vier große Zahlen mit Beschriftung. Nur belegbare Zahlen verwenden.',
        'quote' => 'Ein großes Randzitat oder zwei bis drei Stimmen nebeneinander.',
        'faq' => 'Aufklappbare Fragen und Antworten (für Suchmaschinen als FAQ ausgezeichnet).',
        'cta' => 'Band oder Kasten mit Überschrift und Buttons – oder „Newsletter“ mit dem öffentlichen Formular einer Datentabelle direkt im Band.',
        'video' => 'YouTube, Vimeo (Zwei-Klick-Lösung) oder eigenes MP4 mit Untertiteln und Transkript.',
        'downloads' => 'Dateien zum Herunterladen; PDFs lassen sich im Browser ansehen.',
        'logos' => 'Partner, Förderer oder Mitglieder als Logos oder Schriftzüge.',
        'contact' => 'Adresse, Telefon, E-Mail, Geschäftszeiten und Karte – aus den ' . $settingsTitle . '.',
        'map' => 'Karte ohne Cookies und ohne Einwilligung.',
        'article' => 'Für Detailseiten-Vorlagen: setzt einen Eintrag als Artikel – mit Autor, Datum, Lesezeit, Inhaltsverzeichnis, Teilen-Links und Lesefortschritt; Termine mit Termin-Kasten und iCal.',
        'data_list' => 'Einträge einer Datentabelle: Aufmacher + Raster, Nachrichtenstrom, Kurz notiert, Archiv nach Monaten, Termine, Personen, Karten, Liste oder Tabelle – mit Rubrik-Leiste für Besucher.',
        'data_fields' => 'Für Detailseiten-Vorlagen: einzelne Felder des aufgerufenen Eintrags.',
        'data_form' => 'Öffentliches Formular zu einer Datentabelle – mit Bedingungen, IBAN-Prüfung, wiederholbaren Gruppen und Spamschutz ohne Cookies.',
        'calendar' => 'Termine als Monatsübersicht oder Liste, mit Filter nach Art und Abonnieren-Link.',
        'upcoming' => 'Die nächsten Termine mit großem Datum.',
        'gallery' => 'Bildstrecke als Raster, Mosaik oder Zeilen – mit Lightbox.',
        'slideshow' => 'Bilder oder Folien mit Text nacheinander.',
        'stack_cards' => 'Karten, die sich beim Scrollen übereinanderschieben.',
    ],
];
