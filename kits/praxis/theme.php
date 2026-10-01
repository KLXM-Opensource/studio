<?php
/*
 * Theme „praxis“ – Kit für Arzt- und Fachpraxen (Demo-Inhalte fiktiv: „Praxis Beispiel“, Musterstadt)
 *
 * Diese Datei definiert alles Projektspezifische:
 *  - blocks     Blocktypen (= Editor.js-Tools) mit Feld-Schema
 *  - settings   zentrales Einstellungsformular („Praxisdaten“)
 *  - forms      Vorlage für die verschlüsselten Eingänge Rezept / Überweisung (Core\Data\Inbox)
 *  - jsonld     strukturierte Daten
 * Für ein neues Projekt: Ordner kopieren, anpassen, in Admin → System aktivieren.
 */

require_once __DIR__ . '/functions.php';

$heading = [
    ['name' => 'title_strong', 'label' => 'Überschrift (fett, mit Punkt)', 'type' => 'text', 'required' => true, 'max' => 60, 'width' => 'half'],
    ['name' => 'title_light', 'label' => 'Zweite Zeile (leicht)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
];
$headingOptional = [
    ['name' => 'title_strong', 'label' => 'Überschrift (fett, mit Punkt)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
    ['name' => 'title_light', 'label' => 'Zweite Zeile (leicht)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
];

$videoFields = [
    ['name' => 'video_url', 'label' => 'YouTube- oder Vimeo-Link', 'type' => 'url',
        'help' => 'z. B. https://www.youtube.com/watch?v=… oder https://youtu.be/… – wird automatisch blockiert, bis Besucher zustimmen (Zwei-Klick).'],
    ['name' => 'video_file', 'label' => 'oder eigene MP4-Datei', 'type' => 'file', 'width' => 'half'],
    ['name' => 'poster', 'label' => 'Eigenes Vorschaubild (optional)', 'type' => 'media', 'width' => 'half',
        'help' => 'Leer = Vorschaubild des Videos (vom Server geholt, lokal gespeichert).'],
];

$slideFields = [
    ['name' => 'typ', 'label' => 'Typ', 'type' => 'select', 'required' => true, 'width' => 'half',
        'options' => ['main' => 'Hauptthema (H1)', 'topic' => 'Aktuelles Thema', 'greeting' => 'Begrüßung (Tageszeit)']],
    ['name' => 'aktiv', 'label' => 'Aktiv', 'type' => 'bool', 'default' => true, 'width' => 'half'],
    ['name' => 'eyebrow', 'label' => 'Dachzeile', 'type' => 'text', 'max' => 60],
    ['name' => 'titel', 'label' => 'Titel', 'type' => 'text', 'max' => 60,
        'help' => 'Bei „Begrüßung“ leer lassen – dann „Guten Morgen / Tag / Abend“ je nach Uhrzeit.'],
    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
    ['name' => 'button_label', 'label' => 'Button 1 – Beschriftung', 'type' => 'text', 'max' => 28, 'width' => 'half'],
    ['name' => 'button_link', 'label' => 'Button 1 – Link', 'type' => 'link', 'width' => 'half'],
    ['name' => 'button2_label', 'label' => 'Button 2 – Beschriftung', 'type' => 'text', 'max' => 28, 'width' => 'half'],
    ['name' => 'button2_link', 'label' => 'Button 2 – Link', 'type' => 'link', 'width' => 'half'],
    ['name' => 'bild', 'label' => 'Eigenes Hintergrundbild (optional)', 'type' => 'media', 'translate' => false,
        'help' => 'Nur bei der Hero-Variante „Hintergrundbild“: wechselt mit dem Thema. Leer = Bild des Hero-Blocks.'],
    ['name' => 'von_datum', 'label' => 'Sichtbar ab', 'type' => 'date', 'width' => 'half'],
    ['name' => 'bis_datum', 'label' => 'Sichtbar bis', 'type' => 'date', 'width' => 'half'],
];

$days = ['1' => 'Montag', '2' => 'Dienstag', '3' => 'Mittwoch', '4' => 'Donnerstag', '5' => 'Freitag', '6' => 'Samstag', '0' => 'Sonntag'];

return [
    'label' => 'Praxis – Arzt- und Fachpraxen',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',   // benötigte Core-Version

    'backgrounds' => ['white' => 'Weiß', 'gray' => 'Grau', 'bordeaux' => 'Bordeaux', 'dark' => 'Dunkel'],
    'dark_backgrounds' => ['bordeaux', 'dark'],

    // Einstellungen, deren Host für iframes (nach Einwilligung) erlaubt wird – Karten laufen über den Proxy, daher leer
    'frame_hosts' => [],
    // Karten im Zwei-Klick-Modus: site.js lädt das Karten-Modul (map.mjs) erst beim Klick – kein Skript vor dem Klick (JS-Budget)
    'map_loader' => 'kit',

    'seo' => [
        'home_title' => 'site_title',
        'title_suffix' => 'site_title_suffix',
        'title_suffix_fallback' => 'praxis_name',
        'description' => 'default_meta_description',
        'og_image' => 'og_default_image',
    ],

    'jsonld' => 'praxis_jsonld',

    // Link-Sonderwerte (aufgelöst in praxis_link): nutzen die Praxisdaten
    'link_keywords' => ['doctolib', 'telefon', 'rezept', 'ueberweisung'],
    // Projekt: sichtbare Begriffe und branchenspezifische Funktionen (der Core ist neutral)
    'project' => [
        // Karten (Core\Maps): welche Einstellungen Standort, Beschriftung, Adresse und Routenlink liefern
        // click: Karte erst nach Klick (Zwei-Klick, Handoff) – Grundeinstellungen → Karten kann das je Website ändern
        'map' => ['location' => 'karte_geo', 'label' => 'praxis_name', 'address' => ['strasse', 'plz', 'ort'], 'route' => 'routenplaner_url', 'click' => true, '3d' => true],
        'terms' => [
            'org' => 'Praxis',
            'key' => 'Praxisschlüssel',
            'requests' => 'Rezept- und Überweisungsanfragen',
            'requests_data' => 'Gesundheitsdaten (Art. 9 DSGVO)',
            'records_system' => 'Ihr Praxisverwaltungssystem',
            'site_name' => 'Praxisname',
        ],
        'name_setting' => 'praxis_name',
        'brand' => ['wortmarke_1', 'wortmarke_2'],
        // Einrichtungs-Checkliste auf der Übersichtsseite
        'setup_checks' => [
            ['setting' => 'praxis_name', 'label' => 'Praxisname eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'telefon', 'label' => 'Telefonnummer eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'strasse', 'label' => 'Adresse eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'doctolib_url', 'when' => 'doctolib_aktiv', 'label' => 'Doctolib-Link hinterlegt', 'link' => '/admin/settings#termine'],
        ],
        'dashboard_hint' => 'Telefon, Sprechzeiten, Doctolib, Hero-Themen usw. werden zentral gepflegt und erscheinen automatisch überall auf der Website.',
        // Öffentliche Basisdaten (GET /api/v1/public) – Funktion in functions.php
        'public_info' => 'praxis_public_info',
        // Öffnungszeiten: Einstellungsfeld (Repeater) + Formatierung → aktiviert API /hours und MCP-Tools
        'hours' => ['setting' => 'oeffnungszeiten', 'format' => 'praxis_time_range', 'label' => 'Sprechzeiten'],
        // Hinweisbalken → aktiviert API /notice und MCP-Tool set_notice
        'notice' => ['text' => 'aktueller_hinweis_text', 'active' => 'aktueller_hinweis_aktiv', 'example' => 'z. B. Urlaub, Vertretung'],
        // Beschriftung der Link-Sonderwerte im Editor
        'link_labels' => ['doctolib' => 'Doctolib (aus Praxisdaten)', 'telefon' => 'Telefon (aus Praxisdaten)', 'rezept' => 'Online-Rezept', 'ueberweisung' => 'Online-Überweisung'],
        'search_keywords' => 'sprechzeiten telefon adresse doctolib',
        // Zusätzliche Hinweise und Vorlagen für KI-Assistenten (MCP)
        'mcp' => [
            'instructions' => "Es handelt sich um eine hausärztliche Praxis. Erfinde keine medizinischen oder rechtlichen Angaben; Platzhalter in [eckigen Klammern] nur mit Angaben der Praxis ersetzen. Online-Anfragen enthalten Gesundheitsdaten.",
            'prompts' => [
                'urlaub_eintragen' => ['title' => 'Urlaub / Vertretung eintragen', 'description' => 'Setzt den Hinweisbalken für Urlaub mit Vertretungspraxis.',
                    'arguments' => [['name' => 'zeitraum', 'description' => 'z. B. 21.–25.10.', 'required' => true],
                        ['name' => 'vertretung', 'description' => 'Vertretungspraxis mit Telefon', 'required' => true]],
                    'text' => "Trage mit set_notice einen Urlaubshinweis ein: Die Praxis ist {zeitraum} geschlossen, Vertretung: {vertretung}. "
                        . "Formuliere knapp und freundlich (max. 160 Zeichen), zeige mir den Text vor dem Speichern und ergänze den FAQ-Eintrag „Vertretung und Urlaubszeiten“ auf der Startseite als Entwurf."],
                'sprechzeiten_aendern' => ['title' => 'Sprechzeiten ändern', 'description' => 'Sprechzeiten aus einer Beschreibung übernehmen.',
                    'arguments' => [['name' => 'beschreibung', 'description' => 'z. B. „Mo–Fr 7:30–11, Mo/Di/Do 15:30–17“', 'required' => true]],
                    'text' => "Lies mit get_opening_hours die aktuellen Sprechzeiten. Setze dann mit set_opening_hours: {beschreibung}. "
                        . "Nutze pause_von/pause_bis für die Mittagspause und notiz für Hinweise wie „nachmittags geschlossen“. Zeige mir vorher eine Tabelle zur Bestätigung."],
                'aktuelles_thema' => ['title' => 'Aktuelles Thema im Kopfbereich', 'description' => 'Neues Thema für die rotierenden Hero-Themen anlegen.',
                    'arguments' => [['name' => 'thema', 'description' => 'z. B. Grippeimpfung', 'required' => true], ['name' => 'bis', 'description' => 'sichtbar bis (JJJJ-MM-TT)', 'required' => false]],
                    'text' => "Lies get_settings (Gruppe hero). Ergänze in hero_slides ein Thema vom Typ „topic“ zu: {thema} (sichtbar bis {bis}). "
                        . "Titel ≤ 60 Zeichen, Text ≤ 300 Zeichen, keine medizinischen Versprechen. Behalte alle bestehenden Themen und speichere mit update_settings erst nach meiner Bestätigung."],
            ],
        ],
    ],
    // Besucher-Chat (Core\AI\VisitorChat): zusätzliche Regeln für die KI (den Knopf hebt --cms-chat-lift in css/site.css über die Schnellkontakt-Leiste)
    'ai' => ['chat' => [
        'system' => "Dies ist die Website einer hausärztlichen Praxis. Gib keine medizinischen Ratschläge, Diagnosen oder Einschätzungen von Beschwerden – auch nicht allgemein –, sondern nur Auskünfte, die auf der Website stehen (Sprechzeiten, Leistungen, Abläufe, Kontakt). "
            . "Bei Schmerzen, Beschwerden oder gesundheitlichen Fragen verweise auf die Sprechstunde bzw. den Kontakt. Bei einem Notfall (z. B. Brustschmerz, Atemnot, Bewusstlosigkeit, starke Blutung): sofort den Notruf 112 wählen; außerhalb der Sprechzeiten hilft der ärztliche Bereitschaftsdienst 116 117.",
    ]],
    // App-Icon & PWA: Vorgaben für den Icon-Generator (Grundeinstellungen → App-Icon & PWA) und Funktion für Name/Kurzbefehle
    // Hausschrift: kopiert kits/praxis/build.mjs (pnpm build). preload = wichtigste Schnitte (auch für die Offline-App),
    // icon = TTF für den App-Icon-Generator (liegt in kits/praxis/fonts, nicht öffentlich)
    'fonts' => [
        'preload' => ['fonts/hanken-grotesk-latin-700-normal.woff2', 'fonts/hanken-grotesk-latin-400-normal.woff2'],
        'icon' => 'fonts/HankenGrotesk_800ExtraBold.ttf',
    ],
    'app' => [
        'defaults' => ['icon_text' => 'G', 'icon_bg' => '#7A1F35', 'icon_fg' => '#FFFFFF', 'icon_dot' => '#F6C9A8', 'icon_dot_enabled' => true],
        'info' => 'praxis_app_info',
    ],
    // Bildformate, in denen Blöcke Bilder zeigen – je Format lässt sich ein eigener Zuschnitt festlegen
    'image_ratios' => [
        '16:9' => 'Querformat 16:9', '4:3' => 'Querformat 4:3', '16:10' => 'Karte 16:10', '2:1' => 'Panorama 2:1',
        '21:9' => 'Breitbild 21:9', '1:1' => 'Quadrat 1:1', '3:4' => 'Hochformat 3:4',
    ],

    // Zusätzliche Stylesheets/Skripte, die nur geladen werden, wenn die Seite einen der Blocktypen enthält
    // Kern-Fragmente (app/Views/fragments, Core\Fragments): Video-Zwei-Klick, Editor und Werkzeugleiste kommen nur aus dem Kern
    // (Skript resources/js/embed.js lädt das Fragment selbst); Marke, Sprachumschalter, Öffnungszeiten … ebenso, solange das Kit
    // keine eigene Datei in fragments/ bzw. templates/partials/ mitbringt.
    'fragments' => ['video-embed' => ['ratio_class' => 'ratio-', 'notice' => 'detailed', 'title' => true, 'empty' => 'placeholder', 'icon_size' => 26, 'poster_crop' => true]],
    'conditional_css' => [
        'css/blocks.css' => ['text_image', 'text_video', 'image_wide', 'steps', 'cta', 'downloads', 'people', 'job', 'richtext', 'data_form', 'notice',
            'teaser_tiles:image', 'quote:full', 'video', 'text_columns:columns', 'quick_contact'],
        'css/media-blocks.css' => ['gallery', 'slideshow', 'stack_cards'],   // Aussehen der Kern-Blöcke (Variablen + Feinschliff)   // „typ:variante“ = nur bei dieser Variante
        'css/hero-media.css' => ['hero'],   // auch Verlauf: eigene Bilder je Thema (hero__bg--slides)
        // Detailseiten der Datentabellen (Kern-Block „Datensatz-Felder“) – die Liste steckt in css/data.css
        'css/data-fields.css' => ['data_fields'],
        // Formulare: Formularseite /anfrage/…; die Rückseite der Flip-Kontaktkarte (Hero, Schnellkontakt) lädt site.js erst beim Umdrehen
        'css/form.css' => ['form'],
        // Rich-Text: H2/Zitat im Fließtext + Rich-Text-Stile (t-lead, t-small, t-note, c-*, mark) – „@rich“ = sobald die Seite
        // solche Formatierungen ausgibt (Core\Sanitizer::styled), unabhängig vom Block; die Startseite bleibt ohne sie schlank
        'css/prose.css' => ['@rich'],
    ],

    // Theme-Formulare = Vorlage für Eingangs-Tabellen (verschlüsselte Anfragen, Core\Data\Inbox): Beim ersten Start entsteht je
    // Formular eine Tabelle 'table' mit den Feldern aus 'fields' (Repeater der Einstellungen aus seed.php oder Liste von Felddefinitionen).
    // Danach werden Felder, Titel und Texte im Eingang gepflegt (Daten → Tabelle → Felder & Einstellungen).
    // enabled / mode / external_url bleiben Schalter des Themes (Service-Kacheln, /anfrage/{key}, externer Dienst).
    'forms' => [
        'rezept' => [
            'label' => 'Online-Rezept', 'enabled' => 'rezept_aktiv', 'mode' => 'rezept_modus',
            'external_url' => 'rezept_url', 'fields' => 'formfelder_rezept',
            'success' => 'Vielen Dank – Ihre Anfrage ist eingegangen.',
            'table' => ['handle' => 'rezeptanfragen', 'name' => 'Rezeptanfragen', 'singular' => 'Rezeptanfrage', 'icon' => 'prescription'],
            // Feld-Änderungen (neue Installationen direkt nach dem Anlegen, bestehende einmalig über Inbox::migrate – Merker
            // sys.inbox_field_updates; nacheinander angewendet). Alte Anfragen behalten ihre Werte im verschlüsselten payload und
            // erscheinen unter der gespeicherten Beschriftung (Einzelwert „Medikament und Stärke“ bzw. Tabelle „Medikamente“).
            //  1. 2026-09: Textfeld „medikament“ → wiederholbare Gruppe „Medikamente“ (Medikament, Stärke, Packungsgröße)
            //  2. 2026-09-29: Gruppe → ein mehrzeiliges Textfeld „Medikamente“ (Wunsch der Praxis: einfacher auszufüllen)
            'field_updates' => [[
                'id' => 'praxis-rezept-medikamente-2026-09',
                'replace' => 'medikament',
                'field' => [
                    'name' => 'medikamente', 'label' => 'Medikamente', 'labels' => ['en' => 'Medications'], 'type' => 'group', 'required' => 1, 'width' => '',
                    'min' => 1, 'max' => 10, 'item_label' => 'Medikament', 'add_label' => 'Weiteres Medikament',
                    'help' => 'Bitte je Medikament eine Zeile – Name wie auf der Packung.',
                    'fields' => [
                        ['name' => 'medikament', 'label' => 'Medikament', 'labels' => ['en' => 'Medication'], 'type' => 'text', 'required' => 1],
                        ['name' => 'staerke', 'label' => 'Stärke / Dosierung', 'labels' => ['en' => 'Strength / dosage'], 'type' => 'text', 'width' => 'half'],
                        ['name' => 'packung', 'label' => 'Packungsgröße', 'labels' => ['en' => 'Pack size'], 'type' => 'select', 'width' => 'half',
                            'options' => ['n1' => 'N1', 'n2' => 'N2', 'n3' => 'N3', 'weiss_nicht' => 'weiß nicht'],
                            'options_i18n' => ['en' => ['weiss_nicht' => "don't know"]]],
                    ],
                ],
            ], [
                'id' => 'praxis-rezept-medikamente-text-2026-09-29',
                'replace' => 'medikamente',
                'field' => [
                    'name' => 'medikamente_text', 'label' => 'Medikamente', 'labels' => ['en' => 'Medications'], 'type' => 'textarea', 'required' => 1, 'width' => '',
                    'help' => 'Bitte Name, Stärke und Packungsgröße je Medikament – eine Zeile pro Medikament.',
                ],
            ]],
        ],
        'ueberweisung' => [
            'label' => 'Online-Überweisung', 'enabled' => 'ueberweisung_aktiv', 'mode' => 'ueberweisung_modus',
            'external_url' => 'ueberweisung_url', 'fields' => 'formfelder_ueberweisung',
            'success' => 'Vielen Dank – Ihre Anfrage ist eingegangen.',
            'table' => ['handle' => 'ueberweisungen', 'name' => 'Überweisungen', 'singular' => 'Überweisung', 'icon' => 'arrow-up-right'],
        ],
    ],

    // Style-Editor (Verwaltung → Design): ändert die CSS-Variablen aus css/site.css (:root). Kein Dunkelmodus.
    'design' => [
        'groups' => [
            ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
                ['name' => 'accent', 'label' => 'Akzentfarbe', 'help' => 'Buttons, Links, Hervorhebungen', 'type' => 'color', 'var' => '--c-bordeaux', 'default' => '#7A1F35',
                    'contrast' => ['with' => '#FFFFFF', 'min' => 4.5]],
                ['name' => 'accent_dark', 'label' => 'Akzent dunkel', 'help' => 'Hover-Zustände', 'type' => 'color', 'var' => '--c-bordeaux-dark', 'default' => '#5E1628',
                    'contrast' => ['with' => '#FFFFFF', 'min' => 4.5]],
                ['name' => 'accent_hero', 'label' => 'Akzentfläche', 'help' => 'Große farbige Flächen (Kopfbereich, Abschnitte)', 'type' => 'color', 'var' => '--c-bordeaux-hero', 'default' => '#6A1530',
                    'contrast' => ['with' => '#FFFFFF', 'min' => 4.5]],
                ['name' => 'apricot', 'label' => 'Zweitfarbe', 'help' => 'Punkte, Flächen', 'type' => 'color', 'var' => '--c-apricot', 'default' => '#F6C9A8'],
                ['name' => 'apricot_2', 'label' => 'Zweitfarbe auf Akzent', 'help' => 'Kleine Überschriften auf farbigen Flächen', 'type' => 'color', 'var' => '--c-apricot-2', 'default' => '#F0C4A6',
                    'contrast' => ['with' => 'accent_hero', 'min' => 4.5]],
                ['name' => 'ink', 'label' => 'Text', 'type' => 'color', 'var' => '--c-ink', 'default' => '#16201E', 'contrast' => ['with' => '#FFFFFF', 'min' => 4.5]],
                ['name' => 'text_2', 'label' => 'Text gedämpft', 'type' => 'color', 'var' => '--c-text-2', 'default' => '#3F4A47', 'contrast' => ['with' => 'gray', 'min' => 4.5]],
                ['name' => 'gray', 'label' => 'Grauer Hintergrund', 'type' => 'color', 'var' => '--c-gray-50', 'default' => '#F2F2F3'],
            ]],
            ['id' => 'formen', 'label' => 'Formen', 'tokens' => [
                ['name' => 'buttons', 'label' => 'Buttons', 'type' => 'choice', 'var' => '--r-btn', 'preview' => 'radius', 'default' => 'pill',
                    'options' => ['pill' => 'Rund', 'soft' => 'Abgerundet', 'square' => 'Eckig'], 'values' => ['pill' => '999px', 'soft' => '12px', 'square' => '4px']],
                ['name' => 'radius', 'label' => 'Eckenradius Karten', 'type' => 'range', 'var' => '--r-card', 'default' => 24, 'min' => 0, 'max' => 40, 'step' => 2, 'unit' => 'px'],
            ]],
            ['id' => 'schrift', 'label' => 'Schrift', 'tokens' => [
                ['name' => 'font', 'label' => 'Schriftart', 'type' => 'font', 'var' => '--font', 'default' => 'hanken'],
            ]],
        ],
        // Hanken Grotesk steckt bereits in css/site.css (@font-face) – daher ohne eigene Schriftdatei
        'fonts' => [
            'hanken' => ['label' => 'Hanken Grotesk (Original)', 'stack' => '"Hanken Grotesk",system-ui,-apple-system,"Segoe UI",sans-serif'],
            'system' => ['label' => 'Systemschrift', 'stack' => 'system-ui,-apple-system,"Segoe UI",Roboto,sans-serif'],
            'humanist' => ['label' => 'Humanistisch', 'stack' => 'Seravek,"Gill Sans Nova",Ubuntu,Calibri,"DejaVu Sans",sans-serif'],
            'serif' => ['label' => 'Serifenschrift', 'stack' => 'Charter,"Bitstream Charter","Sitka Text",Cambria,Georgia,serif'],
        ],
        'presets' => [
            'bordeaux' => ['label' => 'Bordeaux (Original)', 'values' => ['accent' => '#7A1F35', 'accent_dark' => '#5E1628', 'accent_hero' => '#6A1530',
                'apricot' => '#F6C9A8', 'apricot_2' => '#F0C4A6', 'ink' => '#16201E', 'text_2' => '#3F4A47', 'gray' => '#F2F2F3']],
            'petrol' => ['label' => 'Petrol', 'values' => ['accent' => '#0F5E63', 'accent_dark' => '#0A4549', 'accent_hero' => '#0C5357',
                'apricot' => '#F3D9B1', 'apricot_2' => '#F3D9B1', 'ink' => '#14201F', 'text_2' => '#3D4A49', 'gray' => '#EFF3F2']],
            'nachtblau' => ['label' => 'Nachtblau', 'values' => ['accent' => '#1F3A68', 'accent_dark' => '#152A4E', 'accent_hero' => '#1A3160',
                'apricot' => '#F2C57C', 'apricot_2' => '#F2C57C', 'ink' => '#141B26', 'text_2' => '#3D4656', 'gray' => '#F0F2F6']],
        ],
    ],

    // ------------------------------------------------------------ Praxisdaten
    'settings' => [
        'title' => 'Praxisdaten',
        'groups' => [
            ['id' => 'stammdaten', 'label' => 'Stammdaten', 'fields' => [
                ['name' => 'praxis_name', 'label' => 'Name der Praxis', 'type' => 'text', 'required' => true],
                ['name' => 'wortmarke_1', 'label' => 'Wortmarke Zeile 1 (fett)', 'type' => 'text', 'width' => 'half', 'default' => 'Praxis Beispiel'],
                ['name' => 'wortmarke_2', 'label' => 'Wortmarke Zeile 2 (leicht)', 'type' => 'text', 'width' => 'half', 'default' => 'Musterstadt'],
                ['name' => 'strasse', 'translate' => false, 'label' => 'Straße und Hausnummer', 'type' => 'text'],
                ['name' => 'plz', 'translate' => false, 'label' => 'PLZ', 'type' => 'text', 'width' => 'half'],
                ['name' => 'ort', 'translate' => false, 'label' => 'Ort', 'type' => 'text', 'width' => 'half', 'default' => 'Musterstadt'],
                ['name' => 'telefon', 'label' => 'Telefon (für Wähl-Link)', 'type' => 'tel', 'width' => 'half', 'help' => 'Wird automatisch zu tel:+49… normalisiert.'],
                ['name' => 'telefon_anzeige', 'translate' => false, 'label' => 'Telefon (Anzeige)', 'type' => 'text', 'width' => 'half', 'help' => 'z. B. 01234 56 78 90'],
                ['name' => 'fax', 'translate' => false, 'label' => 'Fax', 'type' => 'text', 'width' => 'half'],
                ['name' => 'email', 'translate' => false, 'label' => 'E-Mail', 'type' => 'text', 'width' => 'half'],
            ]],
            ['id' => 'erreichbarkeit', 'label' => 'Erreichbarkeit', 'fields' => [
                ['name' => 'oeffnungszeiten', 'translate' => true, 'label' => 'Sprechzeiten', 'type' => 'repeater', 'item_label' => 'Sprechzeit', 'title_field' => 'tag',
                    'help' => 'Ein Eintrag je Wochentag. Pause optional. Ohne Uhrzeit wird die Notiz angezeigt.',
                    'fields' => [
                        ['name' => 'tag', 'label' => 'Wochentag', 'type' => 'select', 'required' => true, 'options' => $days, 'width' => 'half'],
                        ['name' => 'notiz', 'label' => 'Notiz', 'type' => 'text', 'width' => 'half'],
                        ['name' => 'von', 'label' => 'Von', 'type' => 'time', 'width' => 'half'],
                        ['name' => 'bis', 'label' => 'Bis', 'type' => 'time', 'width' => 'half'],
                        ['name' => 'pause_von', 'label' => 'Pause von', 'type' => 'time', 'width' => 'half'],
                        ['name' => 'pause_bis', 'label' => 'Pause bis', 'type' => 'time', 'width' => 'half'],
                    ]],
                ['name' => 'telefonische_erreichbarkeit', 'label' => 'Telefonische Erreichbarkeit', 'type' => 'text'],
                ['name' => 'sprechzeiten_hinweis', 'label' => 'Hinweis unter den Sprechzeiten', 'type' => 'text'],
            ]],
            ['id' => 'termine', 'label' => 'Termine', 'fields' => [
                ['name' => 'doctolib_aktiv', 'label' => 'Online-Terminbuchung (Doctolib) anzeigen', 'type' => 'bool', 'default' => true],
                ['name' => 'doctolib_url', 'label' => 'Doctolib-Link', 'type' => 'url', 'help' => 'Nur als externer Link – kein Widget, keine Daten an Doctolib ohne Klick.'],
                ['name' => 'termin_hinweis', 'label' => 'Hinweis zur Terminbuchung', 'type' => 'textarea', 'rows' => 2],
                ['name' => 'termin_button', 'label' => 'Beschriftung Termin-Button (Kontakt)', 'type' => 'text', 'default' => 'Online-Termin'],
            ]],
            ['id' => 'services', 'label' => 'Online-Services', 'fields' => [
                ['name' => 'rezept_aktiv', 'label' => 'Online-Rezept anbieten', 'type' => 'bool', 'default' => true],
                ['name' => 'rezept_modus', 'label' => 'Rezept über', 'type' => 'select', 'required' => true, 'default' => 'internal',
                    'options' => ['internal' => 'Internes Formular (verschlüsselt)', 'external' => 'Externen Dienst (Link)']],
                ['name' => 'rezept_url', 'label' => 'Link zum externen Rezept-Dienst', 'type' => 'url'],
                ['name' => 'ueberweisung_aktiv', 'label' => 'Online-Überweisung anbieten', 'type' => 'bool', 'default' => true],
                ['name' => 'ueberweisung_modus', 'label' => 'Überweisung über', 'type' => 'select', 'required' => true, 'default' => 'internal',
                    'options' => ['internal' => 'Internes Formular (verschlüsselt)', 'external' => 'Externen Dienst (Link)']],
                ['name' => 'ueberweisung_url', 'label' => 'Link zum externen Überweisungs-Dienst', 'type' => 'url'],
                ['name' => 'bearbeitungsfrist_text', 'label' => 'Bearbeitungsfrist (Bestätigungstext)', 'type' => 'text'],
                ['name' => 'formular_hinweis', 'label' => 'Hinweis unter dem Formular', 'type' => 'text'],
                // Felder der Formulare: seit den Eingangs-Tabellen im jeweiligen Eingang (die Repeater formfelder_* dienen nur noch als Startwerte)
                ['type' => 'heading', 'label' => 'Formularfelder',
                    'help' => 'Felder, Titel und Texte der Online-Formulare bearbeiten Sie im jeweiligen Eingang (Daten → Felder & Einstellungen). Die Checkbox „Datenschutzhinweise gelesen“ wird immer ergänzt. Bitte keine Felder für Beschwerden oder Diagnosen anlegen. Eingegangene Anfragen lesen Sie unter „Anfragen“.',
                    'links' => [['label' => 'Rezept-Formular bearbeiten', 'url' => '/admin/data/rezeptanfragen/schema'],
                        ['label' => 'Überweisungs-Formular bearbeiten', 'url' => '/admin/data/ueberweisungen/schema'],
                        ['label' => 'Anfragen lesen', 'url' => '/admin/requests']]],
            ]],
            ['id' => 'hinweise', 'label' => 'Hinweise', 'fields' => [
                ['name' => 'aktueller_hinweis_aktiv', 'label' => 'Aktuellen Hinweis oben anzeigen', 'type' => 'bool', 'default' => false],
                ['name' => 'aktueller_hinweis_text', 'label' => 'Aktueller Hinweis', 'type' => 'inline', 'help' => 'z. B. Urlaubszeiten oder Vertretung'],
                ['type' => 'heading', 'label' => 'Status „Jetzt geöffnet“', 'help' => 'Das Badge rechnet live mit den Sprechzeiten. Während des Praxisurlaubs zeigt es „Praxis geschlossen bis …“.'],
                ['name' => 'status_badge_aktiv', 'label' => 'Badge „Jetzt geöffnet / Geschlossen“ anzeigen', 'type' => 'bool', 'default' => true],
                ['name' => 'urlaub_von', 'label' => 'Praxis geschlossen von', 'type' => 'date', 'width' => 'half'],
                ['name' => 'urlaub_bis', 'label' => 'Praxis geschlossen bis (einschließlich)', 'type' => 'date', 'width' => 'half'],
                ['name' => 'notfall_titel', 'label' => 'Notfall – Titel', 'type' => 'text', 'default' => 'Im Notfall: 112'],
                ['name' => 'notfall_text', 'label' => 'Notfall – Text', 'type' => 'text', 'default' => 'Außerhalb der Sprechzeiten: ärztlicher Bereitschaftsdienst 116 117.'],
                ['name' => 'notfall_kurz', 'label' => 'Notfall – Kurzform (Kontaktkarte)', 'type' => 'text', 'default' => 'Notfall 112 · Bereitschaftsdienst 116 117'],
            ]],
            ['id' => 'hero', 'label' => 'Hero-Themen', 'fields' => [
                ['name' => 'hero_slides', 'translate' => true, 'preview' => true, 'label' => 'Rotierende Themen im Kopfbereich', 'type' => 'repeater', 'item_label' => 'Thema',
                    'title_field' => 'eyebrow', 'fields' => $slideFields,
                    'help' => 'Wechsel alle 7 Sekunden (pausierbar). Der Typ „Hauptthema“ ist die H1 der Seite.'],
            ]],
            ['id' => 'anfahrt', 'label' => 'Anfahrt', 'fields' => [
                ['name' => 'oepnv_text', 'label' => 'Bus und Bahn', 'type' => 'text'],
                ['name' => 'parken_text', 'label' => 'Parken', 'type' => 'text'],
                ['name' => 'barrierefreiheit_text', 'label' => 'Barrierefreiheit vor Ort', 'type' => 'text'],
                ['name' => 'routenplaner_url', 'label' => 'Routenplaner-Link', 'type' => 'url'],
                ['name' => 'karte_aktiv', 'label' => 'Karte im Kontaktbereich anzeigen', 'type' => 'bool', 'default' => true],
                ['name' => 'karte_geo', 'label' => 'Standort auf der Karte', 'type' => 'geo', 'address_fields' => ['strasse', 'plz', 'ort'],
                    'help' => 'Aus der Adresse ermitteln oder in die Karte klicken. Die Karte lädt über den eigenen Server – ohne Einwilligung, ohne Cookies.'],
            ]],
            ['id' => 'recht', 'label' => 'Recht', 'fields' => [
                ['name' => 'impressum_seite', 'label' => 'Impressum', 'type' => 'page', 'width' => 'half'],
                ['name' => 'datenschutz_seite', 'label' => 'Datenschutzerklärung', 'type' => 'page', 'width' => 'half'],
                ['name' => 'barrierefreiheit_seite', 'label' => 'Erklärung zur Barrierefreiheit', 'type' => 'page', 'width' => 'half'],
            ]],
            ['id' => 'seo', 'label' => 'SEO', 'fields' => [
                ['name' => 'site_title', 'label' => 'Seitentitel der Startseite', 'type' => 'text', 'default' => 'Hausärztliche Praxis in Musterstadt (Beispiel)'],
                ['name' => 'site_title_suffix', 'label' => 'Titel-Zusatz', 'type' => 'text', 'help' => 'Wird mit „|“ angehängt. Leer = Praxisname.'],
                ['name' => 'default_meta_description', 'label' => 'Standard-Beschreibung', 'type' => 'textarea', 'rows' => 3, 'max' => 160],
                ['name' => 'og_default_image', 'label' => 'Vorschaubild für soziale Netzwerke', 'type' => 'media'],
            ]],
        ],
    ],

    // ------------------------------------------------------------ Blöcke
    'blocks' => [
        'hero' => [
            'label' => 'Hero mit Themen', 'icon' => '★', 'group' => 'Kopf', 'raw' => true, 'background' => 'bordeaux',
            'central' => 'Themen, Sprechzeiten und Kontaktkarte kommen aus den Praxisdaten.',
            'variants' => ['silk' => 'Animierter Verlauf (Standard)', 'color' => 'Ruhige Farbfläche', 'image' => 'Hintergrundbild', 'video' => 'Hintergrundvideo'],
            'fields' => [
                ['name' => 'bg_image', 'label' => 'Hintergrundbild bzw. Standbild zum Video', 'type' => 'media',
                    'help' => 'Bei „Hintergrundbild“ und „Hintergrundvideo“. Das Standbild erscheint auch, wenn Besucher „Bewegung reduzieren“ eingestellt haben. Eigene Bilder je Thema: Praxisdaten → Hero-Themen.'],
                ['name' => 'bg_video', 'label' => 'Hintergrundvideo (MP4, stumm, Schleife)', 'type' => 'file',
                    'help' => 'Kurz (10–20 s) und klein (< 8 MB). Läuft ohne Ton; der Pause-Knopf der Themen hält auch das Video an.'],
                ['name' => 'bg_overlay', 'label' => 'Abdunklung für gute Lesbarkeit', 'type' => 'select', 'default' => 'strong', 'required' => true,
                    'options' => ['strong' => 'Stark (empfohlen)', 'medium' => 'Mittel', 'light' => 'Leicht (nur bei ruhigen, dunklen Bildern)']],
                ['name' => 'show_card', 'label' => 'Kontaktkarte (Termin / Rezept / Überweisung) anzeigen', 'type' => 'bool', 'default' => true],
                ['name' => 'use_override', 'label' => 'Eigene Themen statt der Praxisdaten verwenden', 'type' => 'bool', 'default' => false],
                ['name' => 'slides', 'label' => 'Eigene Themen', 'type' => 'repeater', 'item_label' => 'Thema', 'title_field' => 'eyebrow', 'fields' => $slideFields],
            ],
        ],
        'quick_contact' => [
            'label' => 'Schnellkontakt-Karte', 'icon' => '☎', 'group' => 'Praxisdaten', 'background' => 'bordeaux',
            'central' => 'Inhalte aus den Praxisdaten.',
            'fields' => [...$headingOptional, ['name' => 'intro', 'label' => 'Einleitung', 'type' => 'textarea', 'rows' => 2]],
        ],
        'teaser_tiles' => [
            'label' => 'Teaser-Kacheln', 'icon' => '▦', 'group' => 'Navigation',
            'variants' => ['plain' => 'Kacheln (Hero-Stil)', 'image' => 'Karten mit Bild (B06)'],
            'fields' => [
                ['name' => 'intro', 'label' => 'Einleitung', 'type' => 'textarea', 'rows' => 3, 'max' => 400],
                ['name' => 'items', 'label' => 'Kacheln', 'type' => 'repeater', 'item_label' => 'Kachel', 'max_items' => 4, 'fields' => [
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 24, 'width' => 'half'],
                    ['name' => 'sub', 'label' => 'Unterzeile', 'type' => 'text', 'max' => 40, 'width' => 'half'],
                    ['name' => 'link', 'label' => 'Link', 'type' => 'link', 'required' => true, 'width' => 'half'],
                    ['name' => 'image', 'label' => 'Bild (nur Variante „mit Bild“)', 'type' => 'media', 'width' => 'half'],
                ]],
            ],
        ],
        'text_image' => [
            'label' => 'Text + Bild', 'icon' => '◧', 'group' => 'Inhalt',
            'variants' => ['right' => 'Bild rechts (B01)', 'left' => 'Bild links (B02)'],
            'fields' => [
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4-3', 'width' => 'half',
                    'options' => ['4-3' => '4:3', '1-1' => '1:1', '3-4' => '3:4', '16-9' => '16:9']],
                ['name' => 'title_style', 'label' => 'Überschrift-Stil', 'type' => 'select', 'required' => true, 'default' => 'split', 'width' => 'half',
                    'options' => ['split' => 'Stichwort. + leichte Zeile', 'sentence' => 'Ein Satz (mit Dachzeile)']],
                ['name' => 'eyebrow', 'label' => 'Dachzeile', 'type' => 'text', 'max' => 40],
                ...$heading,
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'list', 'label' => 'Aufzählung (ein Punkt pro Zeile)', 'type' => 'textarea', 'rows' => 3, 'width' => 'half',
                    'help' => 'Für Unterpunkte die Zeile mit zwei Leerzeichen oder „- “ beginnen.'],
                ['name' => 'list_style', 'label' => 'Stil der Aufzählung', 'type' => 'select', 'required' => true, 'default' => 'dash', 'width' => 'half',
                    'options' => ['dash' => '— Striche', 'check' => '✓ Häkchen', 'number' => '1. Nummeriert']],
                ['name' => 'button_label', 'label' => 'Button – Beschriftung', 'type' => 'text', 'max' => 28, 'width' => 'half'],
                ['name' => 'button_link', 'label' => 'Button – Link', 'type' => 'link', 'width' => 'half'],
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'help' => 'Min. 1600 px breit. Alt-Text in der Mediathek pflegen.'],
            ],
        ],
        'text_video' => [
            'label' => 'Text + Video', 'icon' => '▶', 'group' => 'Inhalt',
            'jsonld' => ['type' => 'video', 'url' => 'video_url', 'file' => 'video_file', 'poster' => 'poster', 'name' => 'title_strong', 'description' => 'text'],
            'variants' => ['left' => 'Video links (B03)', 'right' => 'Video rechts'],
            'fields' => [
                ...$heading,
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext', 'max' => 400],
                ...$videoFields,
                ['name' => 'consent_text', 'label' => 'Eigener Hinweistext vor dem Laden (optional)', 'type' => 'text',
                    'help' => 'Leer = Standardtext mit Anbieter, Datenübertragung und Link zur Datenschutzerklärung.'],
            ],
        ],
        'video' => [
            'label' => 'Video (breit)', 'icon' => '▶', 'group' => 'Medien',
            'jsonld' => ['type' => 'video', 'url' => 'video_url', 'file' => 'video_file', 'poster' => 'poster', 'name' => 'title_strong', 'description' => 'caption'],
            'variants' => ['16-9' => 'Querformat 16:9', '4-3' => 'Format 4:3'],
            'help' => 'YouTube- und Vimeo-Videos werden blockiert, bis Besucher zustimmen. Das Vorschaubild wird datenschutzfreundlich vom Server geladen.',
            'fields' => [
                ['name' => 'title_strong', 'label' => 'Überschrift (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
                ['name' => 'title_light', 'label' => 'Zweite Zeile (leicht)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
                ...$videoFields,
                ['name' => 'caption', 'label' => 'Bildunterschrift', 'type' => 'text'],
            ],
        ],
        'quote' => [
            'label' => 'Zitat', 'icon' => '❝', 'group' => 'Inhalt',
            'variants' => ['inset' => 'Eingerückte Fläche (Praxis)', 'full' => 'Vollfläche (B04)'],
            'fields' => [
                ['name' => 'text', 'label' => 'Zitat', 'type' => 'text', 'required' => true, 'max' => 120],
                ['name' => 'highlight', 'label' => 'Hervorhebung (Akzentfarbe)', 'type' => 'text', 'max' => 80],
                ['name' => 'source', 'label' => 'Quelle / Name (optional)', 'type' => 'text'],
            ],
        ],
        'text_columns' => [
            'label' => 'Überschrift + Text', 'icon' => '☰', 'group' => 'Inhalt',
            'variants' => ['stacked' => 'Überschrift links, Text rechts', 'columns' => 'Überschrift + Spalten (B05)', 'compact' => 'Kleine Überschrift (H3) + Text'],
            'fields' => [
                ...$heading,
                ['name' => 'columns', 'label' => 'Textspalten / Absätze', 'type' => 'repeater', 'item_label' => 'Absatz', 'fields' => [
                    ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ]],
            ],
        ],
        'richtext' => [
            'label' => 'Fließtext', 'icon' => '¶', 'group' => 'Inhalt',
            'help' => 'Für normalen Fließtext mit Editor – z. B. Stellenanzeigen, Erläuterungen, Rechtstexte. Zwischenüberschriften (H2–H4), Listen, Links und Hinweis-Box über „Stil“ in der Formatierungsleiste. Die Überschrift oben ist optional.',
            'fields' => [
                ...$headingOptional,
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'width', 'label' => 'Breite', 'type' => 'select', 'required' => true, 'default' => 'text',
                    'options' => ['text' => 'Textbreite (gut lesbar)', 'wide' => 'Breit (ganzer Inhaltsbereich)']],
            ],
        ],
        'services' => [
            'label' => 'Leistungen (nummeriert)', 'icon' => '№', 'group' => 'Inhalt',
            'fields' => [
                ...$heading,
                ['name' => 'intro', 'label' => 'Einleitung', 'type' => 'textarea', 'rows' => 3, 'max' => 400],
                ['name' => 'items', 'label' => 'Leistungen', 'type' => 'repeater', 'item_label' => 'Leistung', 'fields' => [
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 60],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'required' => true, 'rows' => 4],
                ]],
            ],
        ],
        'doctors' => [
            'label' => 'Ärztinnen und Ärzte', 'icon' => '⚕', 'group' => 'Personen', 'background' => 'gray',
            'fields' => [
                ...$heading,
                ['name' => 'intro', 'label' => 'Einleitung', 'type' => 'textarea', 'rows' => 3, 'max' => 400],
                ['name' => 'link_label', 'label' => 'Link-Beschriftung', 'type' => 'text', 'default' => 'Termin anfragen', 'width' => 'half'],
                ['name' => 'link', 'label' => 'Link-Ziel', 'type' => 'link', 'default' => '#kontakt', 'width' => 'half'],
                ['name' => 'items', 'label' => 'Personen', 'type' => 'repeater', 'item_label' => 'Person', 'title_field' => 'name', 'fields' => [
                    ['name' => 'fach', 'label' => 'Fachbezeichnung', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ['name' => 'titel', 'label' => 'Akad. Titel', 'type' => 'text', 'width' => 'half'],
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ['name' => 'zusatz', 'label' => 'Zusatzqualifikationen', 'type' => 'text', 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Vorstellung (max. 400 Zeichen)', 'type' => 'textarea', 'rows' => 3, 'max' => 400],
                    ['name' => 'sprechzeiten', 'label' => 'Sprechzeiten (optional)', 'type' => 'text', 'width' => 'half'],
                    ['name' => 'foto', 'label' => 'Foto 1:1 (min. 1200 px)', 'type' => 'media', 'width' => 'half'],
                ]],
            ],
        ],
        // Veraltet (seit 01.10.2026): zu speziell – stattdessen „Bild breit“/„Text + Bild“ und „Handlungsaufruf“ als Box daneben
        // (Abschnitts-Option „Neben den vorigen Block stellen“). Bestehende Blöcke werden weiter dargestellt; nicht mehr einfügbar.
        // Umstellen: php bin/console praxis:migrate-team [--dry-run]
        'team_photo' => [
            'label' => 'Teamfoto + Text (veraltet)', 'icon' => '◉', 'group' => 'Personen', 'insertable' => false,
            'fields' => [
                ['name' => 'image', 'label' => 'Teamfoto 2:1 (min. 2400 px)', 'type' => 'media'],
                ...$heading,
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'show_jobs_box', 'label' => 'Ausbildungs-/Stellen-Box anzeigen', 'type' => 'bool', 'default' => true],
                ['name' => 'jobs_title_strong', 'label' => 'Box – Titel', 'type' => 'text', 'default' => 'Ausbildung', 'width' => 'half'],
                ['name' => 'jobs_title_light', 'label' => 'Box – Zusatz', 'type' => 'text', 'default' => 'Werden Sie Teil des Teams.', 'width' => 'half'],
                ['name' => 'jobs_text', 'label' => 'Box – Text', 'type' => 'textarea', 'rows' => 2],
                ['name' => 'jobs_button_label', 'label' => 'Box – Button', 'type' => 'text', 'default' => 'Stellenangebote ansehen', 'width' => 'half'],
                ['name' => 'jobs_link', 'label' => 'Box – Link', 'type' => 'link', 'default' => '#kontakt', 'width' => 'half'],
            ],
        ],
        'people' => [
            'label' => 'Personen kompakt (B13)', 'icon' => '☺', 'group' => 'Personen', 'background' => 'gray',
            'fields' => [
                ...$headingOptional,
                ['name' => 'items', 'label' => 'Personen', 'type' => 'repeater', 'item_label' => 'Person', 'title_field' => 'name', 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ['name' => 'rolle', 'label' => 'Rolle', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ['name' => 'foto', 'label' => 'Foto 1:1', 'type' => 'media'],
                ]],
            ],
        ],
        'image_wide' => [
            'label' => 'Bild breit (B07)', 'icon' => '▭', 'group' => 'Medien',
            'variants' => ['21-9' => '21:9', '16-9' => '16:9'],
            'fields' => [
                ['name' => 'image', 'label' => 'Bild (min. 2400 px)', 'type' => 'media'],
                ['name' => 'caption', 'label' => 'Bildunterschrift (fett)', 'type' => 'text', 'width' => 'half'],
                ['name' => 'credit', 'label' => 'Zusatz / Fotonachweis', 'type' => 'text', 'width' => 'half'],
            ],
        ],
        'steps' => [
            'label' => 'Ablauf in Schritten (B08)', 'icon' => '⇢', 'group' => 'Inhalt', 'background' => 'gray',
            'fields' => [
                ...$heading,
                ['name' => 'items', 'label' => 'Schritte', 'type' => 'repeater', 'item_label' => 'Schritt', 'max_items' => 5, 'fields' => [
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 40],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2],
                ]],
            ],
        ],
        'accordion' => [
            'label' => 'Akkordeon / FAQ (B09)', 'icon' => '≡', 'group' => 'Inhalt',
            'jsonld' => ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a'],   // schema.org FAQPage
            'fields' => [
                ...$heading,
                ['name' => 'intro', 'label' => 'Einleitung', 'type' => 'richtext'],
                ['name' => 'show_emergency', 'label' => 'Notfall-Box anzeigen (aus Praxisdaten)', 'type' => 'bool', 'default' => false],
                ['name' => 'items', 'label' => 'Einträge', 'type' => 'repeater', 'item_label' => 'Frage', 'title_field' => 'q', 'fields' => [
                    ['name' => 'q', 'label' => 'Frage / Titel', 'type' => 'text', 'required' => true],
                    ['name' => 'a', 'label' => 'Antwort', 'type' => 'richtext', 'required' => true],
                    ['name' => 'service', 'label' => 'Nur anzeigen, wenn Online-Service aktiv', 'type' => 'select',
                        'options' => ['rezept' => 'Rezept', 'ueberweisung' => 'Überweisung']],
                ]],
                ['name' => 'footer', 'label' => 'Zeile unter der Liste', 'type' => 'inline'],
            ],
        ],
        'notice' => [
            'label' => 'Hinweisbox (B10)', 'icon' => '!', 'group' => 'Inhalt',
            'fields' => [
                ['name' => 'items', 'label' => 'Hinweise', 'type' => 'repeater', 'item_label' => 'Hinweis', 'max_items' => 3, 'fields' => [
                    ['name' => 'style', 'label' => 'Stil', 'type' => 'select', 'required' => true, 'options' => ['info' => 'Info', 'important' => 'Wichtig']],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'inline', 'required' => true],
                ]],
            ],
        ],
        'cta' => [
            'label' => 'Handlungsaufruf (B11)', 'icon' => '➜', 'group' => 'Inhalt', 'background' => 'dark',
            // Box: kompakt (kleine Überschrift, Text, Button) – z. B. „Neben den vorigen Block stellen“ (⅓) neben einem Text;
            // in einer Reihe mit anderem Hintergrund als der Abschnitt wird sie zur farbigen Karte, sonst hell mit Rahmen
            'variants' => ['band' => 'Band (ganze Breite)', 'box' => 'Box / Karte (z. B. neben einem Text)'],
            'fields' => [
                ...$heading,
                ['name' => 'text', 'label' => 'Text (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ['name' => 'buttons', 'label' => 'Buttons', 'type' => 'repeater', 'item_label' => 'Button', 'max_items' => 2, 'fields' => [
                    ['name' => 'label', 'label' => 'Beschriftung', 'type' => 'text', 'required' => true, 'max' => 28, 'width' => 'half'],
                    ['name' => 'link', 'label' => 'Link', 'type' => 'link', 'required' => true, 'width' => 'half',
                        'help' => 'Sonderwerte: „doctolib“ und „telefon“ nutzen die Praxisdaten.'],
                ]],
            ],
        ],
        'downloads' => [
            'label' => 'Downloads (B12)', 'icon' => '↓', 'group' => 'Medien',
            'help' => 'PDFs lassen sich im Browser ansehen (Mozilla PDF.js) oder herunterladen. Größe, Typ und Seitenzahl erscheinen automatisch.',
            'fields' => [
                ...$heading,
                ['name' => 'intro', 'label' => 'Einleitung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ['name' => 'source', 'label' => 'Quelle', 'type' => 'select', 'required' => true, 'default' => 'manual', 'width' => 'half',
                    'options' => ['manual' => 'Einzelne Dateien auswählen', 'collection' => 'Alle Dateien einer Sammlung']],
                ['name' => 'collection', 'label' => 'Sammlung', 'type' => 'collection', 'width' => 'half',
                    'help' => 'Neue Dateien in der Sammlung erscheinen automatisch (Medien → Sammlungen).'],
                ['name' => 'files', 'label' => 'Dateien (bei „Einzelne Dateien“)', 'type' => 'repeater', 'item_label' => 'Datei', 'title_field' => 'label', 'fields' => [
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'width' => 'half', 'help' => 'Leer = Titel der Datei'],
                    ['name' => 'file', 'label' => 'Datei', 'type' => 'file', 'width' => 'half'],
                    ['name' => 'note', 'label' => 'Hinweis (optional)', 'type' => 'text', 'placeholder' => 'z. B. vor dem ersten Termin ausfüllen'],
                ]],
                ['name' => 'show_viewer', 'label' => 'PDFs zusätzlich im Browser ansehen lassen (PDF.js)', 'type' => 'bool', 'default' => true],
            ],
        ],
        'job' => [
            'label' => 'Stellenangebot (B14)', 'icon' => '✦', 'group' => 'Personen',
            'fields' => [
                ['name' => 'tags', 'label' => 'Schlagworte (mit Komma getrennt)', 'type' => 'text', 'help' => 'Das erste Schlagwort wird farbig hervorgehoben.'],
                ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 60],
                ['name' => 'text', 'label' => 'Beschreibung (max. 400 Zeichen)', 'type' => 'textarea', 'rows' => 3, 'max' => 400],
                ['name' => 'button_label', 'label' => 'Button', 'type' => 'text', 'default' => 'Jetzt bewerben', 'width' => 'half'],
                ['name' => 'link', 'label' => 'Link', 'type' => 'link', 'required' => true, 'width' => 'half'],
            ],
        ],
        'contact' => [
            'label' => 'Kontakt', 'icon' => '✉', 'group' => 'Praxisdaten',
            'central' => 'Telefon, Sprechzeiten und Anfahrt kommen aus den Praxisdaten.',
            'fields' => [
                ...$heading,
                ['name' => 'show_map', 'label' => 'Karte anzeigen', 'type' => 'bool', 'default' => true],
                ['name' => 'note', 'label' => 'Hinweis unten', 'type' => 'text',
                    'default' => 'Bitte senden Sie medizinische Anliegen, Befunde oder Beschwerden nicht per unverschlüsselter E-Mail. Rufen Sie uns in diesen Fällen an.'],
            ],
        ],
        'map' => [
            'label' => 'Karte', 'icon' => '⌖', 'group' => 'Praxisdaten',
            'help' => 'Interaktive Karte (OpenStreetMap-Daten über den eigenen Server – ohne Einwilligung, ohne Cookies).',
            'fields' => [...$headingOptional, ...\Core\Maps::blockFields()],
        ],
        // Kern-Block „404-Vorschläge“ mit Überschrift im Praxis-Stil und Telefonzeile aus den Praxisdaten (blocks/not_found.php)
        'not_found' => [
            'label' => '404-Vorschläge', 'icon' => '?', 'group' => 'Navigation',
            'help' => 'Für die Seite „Nicht gefunden (404)“ (Seiten → Sonderseiten): Überschrift, Telefonzeile aus den Praxisdaten, „Vielleicht meinten Sie …“ mit ähnlichen Seiten zur aufgerufenen Adresse, Suchfeld und Button zur Startseite.',
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60, 'placeholder' => 'z. B. Fehler 404'],
                ...$headingOptional,
                ['name' => 'phone', 'label' => 'Telefonzeile aus den Praxisdaten zeigen', 'type' => 'bool', 'default' => true],
                ['name' => 'phone_text', 'label' => 'Text der Telefonzeile', 'type' => 'text', 'max' => 120, 'default' => 'Telefonisch sind wir wie gewohnt erreichbar: {phone}',
                    'help' => '{phone} wird durch die Telefonnummer aus den Praxisdaten ersetzt (als Link).'],
                ...\Core\NotFound::blockFields(),
            ],
        ],
    ],

    // Startinhalt der Seite „Nicht gefunden (404)“ beim Anlegen (Core\NotFound) – wie die Fehlerseite templates/error.php
    'not_found_blocks' => fn(string $lang): array => [['type' => 'not_found', 'data' => [
        'eyebrow' => lt('Fehler {code}', ['code' => 404]), 'title_strong' => lt('Seite nicht gefunden'), 'title_light' => lt('Die Adresse existiert nicht (mehr).'),
        'phone' => true, 'phone_text' => lt('Telefonisch sind wir wie gewohnt erreichbar: {phone}'),
        'suggest' => true, 'suggest_title' => lt('Vielleicht meinten Sie:'), 'search' => false, 'home_label' => lt('Zur Startseite'),
    ]]],
];
