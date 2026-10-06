<?php
/*
 * Kit „galerie“ – Kunstgalerie, Kunstverein, Projektraum. Abgeleitet vom breakpointlosen Kit „fluid“.
 *
 * Layouts passen sich ihrem Platz an, nicht der Bildschirmbreite: fließende Schrift- und Abstandsstufen (clamp),
 * Raster mit auto-fit/minmax, Flexbox-Umbrüche und Container-Queries. Keine Media-Queries für Breiten (siehe README).
 * Galerie: Datentabellen Künstler, Ausstellungen, Werke (seed.php → tools/demo-content.php), Blöcke für aktuelle Ausstellung,
 * Ausstellungsarchiv, Künstlerliste, Werke (Raster, Mauerwerk, Salonhängung, Viewing Room), Detailseiten und Besuch.
 * Status der Ausstellungen (jetzt / demnächst / Archiv) wird aus den Daten berechnet (functions.php → galerie_status()).
 *
 *  - blocks     Blocktypen (= Editor.js-Tools) mit Feld-Schema und Varianten
 *  - settings   zentrales Einstellungsformular („Website“) – Gruppe „Galerie“: Besuch, weitere Orte, Ausnahmen, Anfragen, Tabellen
 *  - project    sichtbare Begriffe, Karte, Öffnungszeiten, Hinweisbalken (Core bleibt neutral)
 * Eigenes Kundenprojekt: php bin/console kit:create kunde --from=galerie  (Präfix galerie_* → kunde_*)
 */

require_once __DIR__ . '/functions.php';

// Wiederkehrende Felder: Dachzeile, Überschrift, Einleitung
$head = fn(bool $required = false, bool $intro = true) => array_merge([
    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
    ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'max' => 110, 'width' => 'half', 'required' => $required],
], $intro ? [['name' => 'intro', 'label' => 'Einleitung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 400]] : []);

$buttons = [
    ['name' => 'button_label', 'label' => 'Button 1 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button_link', 'label' => 'Button 1 – Link', 'type' => 'link', 'width' => 'half',
        'help' => 'Seite, #anker, https://… – Sonderwerte „phone“ und „email“ nutzen „Website“.'],
    ['name' => 'button2_label', 'label' => 'Button 2 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button2_link', 'label' => 'Button 2 – Link', 'type' => 'link', 'width' => 'half'],
];

$ratios = ['16:9' => 'Breitbild 16:9', '3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', '1:1' => 'Quadrat 1:1', '4:5' => 'Hochformat 4:5', '3:4' => 'Hochformat 3:4'];
// Breakpointlos: statt „Spalten“ eine Mindestbreite je Eintrag – das Raster füllt die Zeile selbst (auto-fit, minmax)
$size = ['name' => 'size', 'label' => 'Breite je Eintrag (mindestens)', 'type' => 'select', 'required' => true, 'default' => 'm', 'width' => 'half',
    'options' => ['s' => 'Schmal (viele nebeneinander)', 'm' => 'Mittel', 'l' => 'Breit (wenige nebeneinander)'],
    'help' => 'Wie viele Einträge nebeneinander passen, ergibt sich aus dem verfügbaren Platz – auf jedem Gerät.'];
$days = ['1' => 'Montag', '2' => 'Dienstag', '3' => 'Mittwoch', '4' => 'Donnerstag', '5' => 'Freitag', '6' => 'Samstag', '0' => 'Sonntag'];
$link = fn(string $prefix = 'link', string $label = 'Link') => [
    ['name' => $prefix . '_label', 'label' => $label . ' – Beschriftung (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => $prefix, 'label' => $label, 'type' => 'link', 'width' => 'half'],
];

return [
    'label' => 'Galerie',
    'version' => '0.1.0',
    'requires' => '>=1.0.0',
    'source_lang' => 'de',

    'backgrounds' => ['white' => 'Standard', 'muted' => 'Getönt', 'tint' => 'Akzent hell', 'accent' => 'Akzentfarbe', 'secondary' => 'Zweite Markenfarbe', 'dark' => 'Dunkel'],
    'dark_backgrounds' => ['accent', 'secondary', 'dark'],
    'frame_hosts' => [],

    'container_class' => 'wrap',
    'button_class' => 'btn btn--primary',

    'seo' => [
        'home_title' => 'site_title',
        'title_suffix' => 'site_title_suffix',
        'title_suffix_fallback' => 'org_name',
        'description' => 'default_meta_description',
        'og_image' => 'og_default_image',
    ],
    'jsonld' => 'galerie_jsonld',

    'link_keywords' => ['phone', 'email'],

    'project' => [
        'map' => ['location' => 'geo', 'label' => 'org_name', 'address' => ['street', 'zip', 'city']],
        'terms' => [
            'org' => 'Galerie',
            'site_name' => 'Name der Website',
        ],
        'name_setting' => 'org_name',
        'brand' => ['short_name', 'tagline'],
        'setup_checks' => [
            ['setting' => 'org_name', 'label' => 'Name eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'email', 'label' => 'E-Mail-Adresse eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'street', 'label' => 'Adresse eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'imprint_page', 'label' => 'Impressum zugeordnet', 'link' => '/admin/settings#recht'],
            ['setting' => 'hours', 'label' => 'Öffnungszeiten eingetragen', 'link' => '/admin/settings#stammdaten'],
        ],
        'dashboard_hint' => 'Künstler, Ausstellungen und Werke pflegen Sie unter Daten – der Status „Jetzt“, „Demnächst“ oder „Archiv“ ergibt sich aus den Daten. Öffnungszeiten, Ausnahmen (Feiertage), weitere Orte und Anfragen: Website → Stammdaten und Galerie. Präsentation der Werke, Preise, Farben und Schriften: Verwaltung → Design.',
        'public_info' => 'galerie_public_info',
        'hours' => ['setting' => 'hours', 'format' => 'galerie_time_range', 'label' => 'Öffnungszeiten'],
        'notice' => ['text' => 'notice_text', 'active' => 'notice_active', 'example' => 'z. B. Sommerpause, Aufbau der neuen Ausstellung'],
        'link_labels' => ['phone' => 'Telefon (aus „Website“)', 'email' => 'E-Mail (aus „Website“)'],
        'search_keywords' => 'kontakt adresse telefon e-mail öffnungszeiten logo farbe galerie anfragen orte feiertage',
        'mcp' => [
            'instructions' => 'Kit für Kunstgalerien. Künstler, Ausstellungen und Werke stehen in Datentabellen (kuenstler, ausstellungen, werke); der Status einer Ausstellung ergibt sich aus Beginn und Ende – kein Statusfeld setzen. Erfinde keine Fakten (Künstlerbiografien, Preise, Maße, Leihgeber, Pressestimmen, Rechtstexte); Platzhalter in [eckigen Klammern] nur mit Angaben der Galerie ersetzen.',
        ],
    ],

    // Schriften: Auswahl im Style-Editor (design.fonts); Vorladen übernimmt galerie_font_preloads()
    'fonts' => [
        'icon' => 'fonts/Inter_700Bold.ttf',
    ],
    'app' => [
        'defaults' => ['icon_text' => 'G', 'icon_bg' => '#111111', 'icon_fg' => '#FFFFFF', 'icon_dot' => '#C62828', 'icon_dot_enabled' => true],
        'info' => 'galerie_app_info',
    ],
    'image_ratios' => [
        '16:9' => 'Breitbild 16:9', '3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', '1:1' => 'Quadrat 1:1', '4:5' => 'Hochformat 4:5', '3:4' => 'Hochformat 3:4',
    ],

    // Stylesheets und Skripte je Blocktyp (bzw. Typ:Variante) – nur auf Seiten, die sie brauchen
    // Kern-Fragmente (app/Views/fragments, Core\Fragments): Video-Zwei-Klick, Editor und Werkzeugleiste kommen nur aus dem Kern
    // (Skript resources/js/embed.js lädt das Fragment selbst); Marke, Sprachumschalter, Öffnungszeiten … ebenso, solange das Kit
    // keine eigene Datei in fragments/ bzw. templates/partials/ mitbringt.
    // Optionen je Fragment: 'fragments' => ['brand' => ['mark' => 'dot'], 'video-embed' => ['ratio_class' => 'ratio-']]
    'conditional_css' => [
        'css/hero-x.css' => ['hero:fullbleed', 'hero:cards', 'hero:type'],
        'css/hx-collage.css' => ['hero:collage'],
        'css/prose.css' => ['richtext', 'tabs', 'faq', 'media_text', '@rich'],   // @rich: Rich-Text-Stile in anderen Blöcken (Core\Sanitizer::styled)
        'css/b-article.css' => ['richtext:article', 'richtext:columns'],
        'css/b-features.css' => ['features'],
        'css/b-media-text.css' => ['media_text'],
        'css/b-cta.css' => ['cta'],
        'css/reel.css' => ['cards:reel', 'quote:reel'],
        'css/b-cards.css' => ['cards'],
        'css/b-logos.css' => ['logos'],
        'css/b-quote.css' => ['quote'],
        'css/b-steps.css' => ['steps'],
        'css/b-faq.css' => ['faq'],
        'css/b-tabs.css' => ['tabs'],
        'css/b-contact.css' => ['contact', 'map'],
        'css/b-downloads.css' => ['downloads'],
        'css/dataform.css' => ['contact'],
        // Galerie: gemeinsame Bausteine (Etiketten, Werkangaben, Präsentation, Punkte) + je Blockfamilie ein Stylesheet
        'css/b-gallery.css' => ['exhibition_feature', 'exhibitions_list', 'exhibition_detail', 'artists_index', 'artist_profile', 'artworks', 'artwork_detail', 'visit'],
        'css/b-exhibitions.css' => ['exhibition_feature', 'exhibitions_list', 'exhibition_detail'],
        'css/b-artists.css' => ['artists_index', 'artist_profile'],
        'css/b-artworks.css' => ['artworks', 'artwork_detail', 'exhibition_detail'],
        'css/b-visit.css' => ['visit'],
        'css/media.css' => ['artwork_detail', 'exhibition_detail'],   // Lightbox des Kerns (Bild vergrößern)
        'js/tabs.js' => ['tabs', 'exhibitions_list'],
        'js/gallery.js' => ['artworks'],
        'css/b-video.css' => ['video', 'exhibition_detail', 'artwork_detail', 'artist_profile'],   // Videos in gemischten Galerien
        'js/reel.js' => ['quote', 'cards'],
    ],

    // Skripte, die auch im Seiten-Editor laufen: „Neues Werk aus Foto“ im Block „Werke“ (Ereignis-Delegation, übersteht Neuzeichnen)
    'editor_js' => ['js/gallery-edit.js'],

    // ------------------------------------------------------------ Design (Style-Editor: Verwaltung → Design)
    'design' => require __DIR__ . '/design.php',

    // Kopfbereich-Aktionen (header_actions()): Button-Klassen des Kits für die Stile „Gefüllt“, „Kontur“ und „Geteilt“
    'header_actions' => ['late' => true, 'classes' => ['solid' => 'btn btn--primary btn--small', 'outline' => 'btn btn--secondary btn--small', 'split' => 'btn btn--primary btn--small']],

    // ------------------------------------------------------------ Website
    'settings' => [
        'title' => 'Website',
        'groups' => [
            ['id' => 'stammdaten', 'label' => 'Stammdaten', 'fields' => [
                ['name' => 'org_name', 'label' => 'Name der Galerie', 'type' => 'text', 'required' => true,
                    'help' => 'Vollständiger Name, z. B. für Karte, Fußbereich und Suchmaschinen.'],
                ['name' => 'short_name', 'label' => 'Kurzname / Wortmarke', 'type' => 'text', 'max' => 32, 'width' => 'half',
                    'help' => 'Erscheint oben links, solange kein Logo hinterlegt ist. Leer = Name.'],
                ['name' => 'tagline', 'label' => 'Kurzbeschreibung (Claim)', 'type' => 'text', 'max' => 90, 'width' => 'half'],
                ['type' => 'heading', 'label' => 'Adresse'],
                ['name' => 'street', 'label' => 'Straße und Hausnummer', 'type' => 'text', 'translate' => false],
                ['name' => 'zip', 'label' => 'PLZ', 'type' => 'text', 'width' => 'half', 'translate' => false],
                ['name' => 'city', 'label' => 'Ort', 'type' => 'text', 'width' => 'half'],
                ['name' => 'country', 'label' => 'Land (optional)', 'type' => 'text', 'width' => 'half'],
                ['type' => 'heading', 'label' => 'Kontakt'],
                ['name' => 'phone', 'label' => 'Telefon', 'type' => 'tel', 'width' => 'half', 'translate' => false,
                    'help' => 'So eingeben, wie es angezeigt werden soll – der Wähl-Link wird automatisch erzeugt.'],
                ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'width' => 'half', 'translate' => false],
                ['type' => 'heading', 'label' => 'Öffnungszeiten (optional)', 'help' => 'Leer lassen, wenn keine Öffnungszeiten angezeigt werden sollen.'],
                ['name' => 'hours', 'label' => 'Öffnungszeiten', 'type' => 'repeater', 'item_label' => 'Zeitraum', 'title_field' => 'tag', 'translate' => false,
                    'help' => 'Ein Eintrag je Wochentag; gleiche Zeiten an Folgetagen werden automatisch zusammengefasst (z. B. Montag–Freitag).',
                    'fields' => [
                        ['name' => 'tag', 'label' => 'Wochentag', 'type' => 'select', 'required' => true, 'options' => $days, 'width' => 'half'],
                        ['name' => 'notiz', 'label' => 'Notiz (z. B. „nach Vereinbarung“)', 'type' => 'text', 'width' => 'half'],
                        ['name' => 'von', 'label' => 'Von', 'type' => 'time', 'width' => 'half'],
                        ['name' => 'bis', 'label' => 'Bis', 'type' => 'time', 'width' => 'half'],
                        ['name' => 'pause_von', 'label' => 'Pause von', 'type' => 'time', 'width' => 'half'],
                        ['name' => 'pause_bis', 'label' => 'Pause bis', 'type' => 'time', 'width' => 'half'],
                    ]],
                ['name' => 'hours_note', 'label' => 'Hinweis unter den Öffnungszeiten', 'type' => 'text', 'placeholder' => 'z. B. und nach Vereinbarung',
                    'help' => 'Feiertage, Sommerpause und Aufbauzeiten tragen Sie unter „Galerie“ → „Abweichende Öffnungszeiten“ ein – sie erscheinen automatisch zur rechten Zeit.'],
                ['type' => 'heading', 'label' => 'Karte'],
                ['name' => 'geo', 'label' => 'Standort auf der Karte', 'type' => 'geo', 'address_fields' => ['street', 'zip', 'city'], 'translate' => false,
                    'help' => 'Aus der Adresse ermitteln oder in die Karte klicken. Leer = keine Karte. Die Karte lädt über den eigenen Server – ohne Einwilligung, ohne Cookies.'],
            ]],
            ['id' => 'darstellung', 'label' => 'Darstellung', 'fields' => [
                ['name' => 'logo', 'label' => 'Logo (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'SVG oder PNG mit transparentem Hintergrund, ca. 40 px hoch dargestellt.'],
                ['name' => 'logo_dark', 'label' => 'Logo für dunkles Farbschema (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'Helle Fassung des Logos. Leer = normales Logo.'],
                ['name' => 'topbar_text', 'label' => 'Text der Infoleiste (optional)', 'type' => 'text', 'max' => 90,
                    'help' => 'Kurzer Hinweis links in der Infoleiste, z. B. „Mi–Sa 12–18 Uhr · Eintritt frei“. Infoleiste einschalten: Design → Kopf & Fuß.'],
                ['name' => 'footer_statement', 'label' => 'Satz im Fußbereich „Großer Schriftzug“ (optional)', 'type' => 'text', 'max' => 90,
                    'help' => 'Z. B. „Kunst sehen. Gespräche führen.“ – leer = Kurzname. Der Button im Kopfbereich erscheint daneben.'],
                ['name' => 'footer_text', 'label' => 'Text im Fußbereich', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
                // Kopfbereich-Aktionen (Core\HeaderActions): Handlungsaufruf, zweite Aktion, Status-Bezeichnung, Anmelden
                ...\Core\HeaderActions::settingsFields(),
            ]],
            ['id' => 'galerie', 'label' => 'Galerie', 'fields' => [
                ['type' => 'heading', 'label' => 'Besuch', 'help' => 'Erscheint im Block „Besuch“ und im Fußbereich. Die regulären Öffnungszeiten stehen unter „Stammdaten“.'],
                ['name' => 'venue_name', 'label' => 'Bezeichnung des Hauptorts (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half', 'placeholder' => 'z. B. Galerie am Beispielplatz'],
                ['name' => 'admission', 'label' => 'Eintritt (optional)', 'type' => 'text', 'max' => 80, 'width' => 'half', 'placeholder' => 'z. B. Eintritt frei'],
                ['name' => 'appointment_note', 'label' => 'Termine nach Vereinbarung (optional)', 'type' => 'text', 'max' => 160,
                    'placeholder' => 'z. B. Besuche außerhalb der Öffnungszeiten und Atelierbesuche nach Vereinbarung.'],
                ['name' => 'hours_exceptions', 'label' => 'Abweichende Öffnungszeiten', 'type' => 'repeater', 'item_label' => 'Ausnahme', 'title_field' => 'text', 'translate' => false,
                    'help' => 'Feiertage, Sommerpause, Aufbau. Erscheinen 60 Tage vorher im Block „Besuch“ und verschwinden danach von selbst; am Tag selbst steht „Heute geschlossen“.',
                    'fields' => [
                        ['name' => 'von', 'label' => 'Von', 'type' => 'date', 'required' => true, 'width' => 'half'],
                        ['name' => 'bis', 'label' => 'Bis (optional)', 'type' => 'date', 'width' => 'half'],
                        ['name' => 'text', 'label' => 'Anlass', 'type' => 'text', 'max' => 80, 'width' => 'half', 'placeholder' => 'z. B. Ostern, Sommerpause, Aufbau'],
                        ['name' => 'zeiten', 'label' => 'Abweichende Zeiten (wenn geöffnet)', 'type' => 'text', 'max' => 60, 'width' => 'half', 'placeholder' => 'z. B. 12–16 Uhr'],
                        ['name' => 'geschlossen', 'label' => 'Geschlossen', 'type' => 'bool', 'default' => true],
                    ]],
                ['name' => 'venues', 'label' => 'Weitere Orte (Showroom, Lager, Projektraum)', 'type' => 'repeater', 'item_label' => 'Ort', 'title_field' => 'name',
                    'help' => 'Der Hauptort kommt aus „Stammdaten“. Weitere Orte erscheinen im Block „Besuch“ nebeneinander.',
                    'fields' => [
                        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 60, 'width' => 'half'],
                        ['name' => 'karte', 'label' => 'Link zur Karte (optional)', 'type' => 'url', 'width' => 'half', 'help' => 'Leer = Suche nach der Adresse bei OpenStreetMap.'],
                        ['name' => 'adresse', 'label' => 'Adresse (eine Zeile je Angabe)', 'type' => 'textarea', 'rows' => 3, 'width' => 'half'],
                        ['name' => 'zeiten', 'label' => 'Öffnungszeiten (eine Zeile je Angabe)', 'type' => 'textarea', 'rows' => 3, 'width' => 'half', 'placeholder' => "Do–Sa 14–18 Uhr\nund nach Vereinbarung"],
                        ['name' => 'hinweis', 'label' => 'Hinweis (optional)', 'type' => 'text', 'max' => 160],
                    ]],
                ['type' => 'heading', 'label' => 'Anfragen zu Werken', 'help' => 'Der Button „Anfrage zu diesem Werk“ in Werklisten, im Viewing Room und auf der Detailseite eines Werks.'],
                ['name' => 'enquiry_mode', 'label' => 'Anfragen gehen an', 'type' => 'select', 'required' => true, 'default' => 'page', 'width' => 'half', 'translate' => false,
                    'options' => ['page' => 'Seite mit Formular (Werk wird vorausgefüllt)', 'email' => 'E-Mail mit Werk im Betreff', 'off' => 'Kein Anfrage-Button']],
                ['name' => 'enquiry_page', 'label' => 'Seite mit dem Anfrageformular', 'type' => 'page', 'width' => 'half', 'translate' => false,
                    'help' => 'Eine Seite mit dem Block „Kontakt“ und einem Formular, dessen Tabelle ein Feld „werk“ hat (Demo: „Anfragen“). Abschnitt mit Anker „anfrage“.'],
                ['name' => 'enquiry_email', 'label' => 'E-Mail für Anfragen (optional)', 'type' => 'email', 'width' => 'half', 'translate' => false, 'help' => 'Leer = E-Mail aus „Stammdaten“.'],
                ['name' => 'enquiry_label', 'label' => 'Beschriftung des Buttons (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'Anfrage zu diesem Werk'],
                ['type' => 'heading', 'label' => 'Datentabellen', 'help' => 'Nur ändern, wenn Ihre Tabellen andere Kurznamen haben. Die Feldnamen der Tabellen beschreibt das Handbuch (Kapitel „Galerie: Daten“).', 'collapse' => true],
                ['name' => 'gallery_artists_table', 'label' => 'Künstlerinnen und Künstler', 'type' => 'datatable', 'width' => 'half', 'translate' => false, 'help' => 'Leer = „kuenstler“'],
                ['name' => 'gallery_exhibitions_table', 'label' => 'Ausstellungen', 'type' => 'datatable', 'width' => 'half', 'translate' => false, 'help' => 'Leer = „ausstellungen“'],
                ['name' => 'gallery_works_table', 'label' => 'Werke', 'type' => 'datatable', 'width' => 'half', 'translate' => false, 'help' => 'Leer = „werke“'],
            ]],
            ['id' => 'hinweis', 'label' => 'Hinweisbalken', 'fields' => [
                ['name' => 'notice_active', 'label' => 'Hinweis oben auf jeder Seite anzeigen', 'type' => 'bool', 'default' => false, 'translate' => false],
                ['name' => 'notice_text', 'label' => 'Hinweis', 'type' => 'inline', 'help' => 'Kurz halten, z. B. Betriebsferien oder geänderte Öffnungszeiten. Links sind erlaubt.'],
            ]],
            ['id' => 'social', 'label' => 'Social Media', 'fields' => [
                ['name' => 'social', 'label' => 'Profile', 'type' => 'repeater', 'item_label' => 'Profil', 'title_field' => 'label', 'translate' => false,
                    'help' => 'Erscheinen im Fußbereich als einfache Links – ohne eingebettete Inhalte oder Tracking.',
                    'fields' => [
                        ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'width' => 'half', 'placeholder' => 'z. B. Mastodon'],
                        ['name' => 'url', 'label' => 'Adresse', 'type' => 'url', 'required' => true, 'width' => 'half'],
                    ]],
            ]],
            ['id' => 'recht', 'label' => 'Recht', 'fields' => [
                ['name' => 'imprint_page', 'label' => 'Impressum', 'type' => 'page', 'width' => 'half', 'translate' => false],
                ['name' => 'privacy_page', 'label' => 'Datenschutzerklärung', 'type' => 'page', 'width' => 'half', 'translate' => false],
                ['name' => 'accessibility_page', 'label' => 'Erklärung zur Barrierefreiheit (optional)', 'type' => 'page', 'width' => 'half', 'translate' => false],
            ]],
            ['id' => 'seo', 'label' => 'SEO', 'fields' => [
                ['name' => 'site_title', 'label' => 'Seitentitel der Startseite', 'type' => 'text', 'max' => 70],
                ['name' => 'site_title_suffix', 'label' => 'Titel-Zusatz', 'type' => 'text', 'help' => 'Wird mit „|“ an Seitentitel angehängt. Leer = Name.'],
                ['name' => 'default_meta_description', 'label' => 'Standard-Beschreibung', 'type' => 'textarea', 'rows' => 3, 'max' => 160],
                ['name' => 'og_default_image', 'label' => 'Vorschaubild für soziale Netzwerke', 'type' => 'media', 'translate' => false],
                ['name' => 'schema_type', 'label' => 'Art der Organisation (strukturierte Daten)', 'type' => 'select', 'required' => true, 'default' => 'ArtGallery', 'translate' => false,
                    'options' => ['ArtGallery' => 'Kunstgalerie (ArtGallery)', 'Museum' => 'Museum / Kunsthalle', 'NGO' => 'Kunstverein / gemeinnützige Organisation',
                        'LocalBusiness' => 'Lokales Geschäft (Rahmung, Kunsthandel)', 'Organization' => 'Organisation allgemein']],
            ]],
        ],
    ],

    // ------------------------------------------------------------ Blöcke
    'blocks' => [
        'hero' => [
            'label' => 'Einstieg (Hero)', 'icon' => 'star', 'group' => 'Kopf',
            'help' => 'Große Überschrift der Seite (H1). Pro Seite genau einmal verwenden – „Seitenkopf“ für Unterseiten. Alle Varianten ordnen sich nach verfügbarem Platz an.',
            'variants' => [
                'split' => 'Text und Bild nebeneinander',
                'centered' => 'Zentriert, Bild darunter',
                'fullbleed' => 'Vollflächiges Bild oder Video mit Abdunkelung',
                'cards' => 'Mit gestapelten Karten',
                'type' => 'Großer Schriftzug (typografisch)',
                'compact' => 'Seitenkopf (ohne Bild)',
                'collage' => 'Collage aus 3–5 Bildern mit Textkarte',
            ],
            // Wann welche Variante? (Handbuch → Alle Blöcke → Einstieg)
            'variant_help' => [
                'split' => 'Seite mit einem aussagekräftigen Bild, z. B. dem Galerieraum.',
                'centered' => 'Kurze, starke Botschaft; Bild im Breitbild darunter.',
                'fullbleed' => 'Raumwirkung: Ausstellungsansicht oder kurzes stummes Video hinter dem Text.',
                'cards' => 'Drei Leistungen sollen sofort ins Auge fallen, z. B. Beratung, Rahmung, Leihgaben.',
                'type' => 'Ohne Bild oder für eine klare Haltung: die Überschrift ist das Bild.',
                'compact' => 'Unterseiten: Seitentitel mit kurzer Einleitung.',
                'collage' => 'Mehrere Ausstellungsansichten oder Werke als Bildwelt – z. B. für „Die Galerie“ oder einen Projektraum.',
            ],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift (H1)', 'type' => 'text', 'required' => true, 'max' => 110,
                    'help' => 'Ein Wort in *Sternchen* wird hervorgehoben (Akzentfarbe bzw. kursiv bei Serifen).'],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 360],
                ...$buttons,
                ['name' => 'points', 'label' => 'Kurze Pluspunkte (eine pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3,
                    'help' => 'Erscheinen als Häkchen unter den Buttons, z. B. „Termine auch samstags“.'],
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half', 'help' => 'Min. 1600 px breit. Alt-Text in der Mediathek pflegen.'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:5', 'width' => 'half', 'options' => $ratios,
                    'help' => 'Bei „Zentriert“ immer 16:9; bei „Vollflächig“ füllt das Bild den Abschnitt.'],
                ['name' => 'video', 'label' => 'Hintergrundvideo (MP4, stumm, nur „Vollflächig“)', 'type' => 'file', 'width' => 'half', 'variants' => ['fullbleed'],
                    'help' => 'Kurz und klein (< 8 MB). Startet nur ohne „Bewegung reduzieren“ und hat eine Pause-Schaltfläche. Das Bild dient als Standbild.'],
                ['name' => 'overlay', 'label' => 'Abdunkelung (nur „Vollflächig“)', 'type' => 'select', 'required' => true, 'default' => 'strong', 'width' => 'half', 'variants' => ['fullbleed'],
                    'options' => ['strong' => 'Stark (empfohlen)', 'medium' => 'Mittel', 'gradient' => 'Verlauf von unten']],
                ['name' => 'cards', 'label' => 'Karten (nur „Mit gestapelten Karten“)', 'type' => 'repeater', 'item_label' => 'Karte', 'title_field' => 'title', 'max_items' => 3, 'variants' => ['cards'], 'fields' => [
                    ['name' => 'icon', 'label' => 'Symbol', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 50, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'text', 'max' => 120],
                ]],
                // Neue Varianten: Felder erscheinen nur dort (Core\Fields 'variants')
                ...\Core\Blocks\Hero::fields('gallery', ['collage']),
            ],
        ],
        // ------------------------------------------------------------ Galerie (Daten aus den Tabellen Künstler, Ausstellungen, Werke)
        'exhibition_feature' => [
            'label' => 'Aktuelle Ausstellung', 'icon' => 'image', 'group' => 'Galerie',
            'help' => 'Die laufende Ausstellung groß – oder, wenn gerade keine läuft, die nächste. „Jetzt“ bzw. „Demnächst“ erscheint automatisch; Daten, Eröffnung, Ort und Künstler kommen aus der Tabelle „Ausstellungen“.',
            'variants' => ['bleed' => 'Vollbild – Titel auf dem Bild', 'split' => 'Bild und Text nebeneinander', 'type' => 'Typografisch – großer Titel, Bild daneben'],
            'variant_help' => [
                'bleed' => 'Startseite: die Ausstellungsansicht füllt den Bildschirm, der Titel steht unten links darauf.',
                'split' => 'Ruhig und klar: Bild und Angaben je zur Hälfte – stellt sich bei wenig Platz untereinander.',
                'type' => 'Wenn der Titel stark ist: sehr große Schrift, kleines Bild – gut für Kunstvereine und Projekträume.',
            ],
            'fields' => [
                ['name' => 'exhibition', 'label' => 'Ausstellung (optional)', 'type' => 'link',
                    'help' => 'Leer = automatisch: die laufende Ausstellung, sonst die nächste. Oder eine bestimmte Ausstellung aus „Daten“ wählen.'],
                ['name' => 'venue', 'label' => 'Automatisch nur an diesem Ort', 'type' => 'select', 'width' => 'half', 'default' => '',
                    'options' => ['' => 'Alle Orte', 'galerie' => 'Galerie', 'showroom' => 'Showroom', 'projektraum' => 'Projektraum', 'messe' => 'Kunstmesse']],
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half', 'help' => 'Leer = „Jetzt“ bzw. „Demnächst“.'],
                ['name' => 'h1', 'label' => 'Titel ist die Hauptüberschrift der Seite (H1)', 'type' => 'bool', 'default' => true, 'width' => 'half',
                    'help' => 'Auf der Startseite an, wenn dieser Block oben steht. Sonst aus (H2).'],
                ['name' => 'show_text', 'label' => 'Kurztext zeigen', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'button_label', 'label' => 'Beschriftung des Links (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half', 'placeholder' => 'Zur Ausstellung'],
                ['name' => 'ratio', 'label' => 'Bildformat (Bild und Text, Typografisch)', 'type' => 'select', 'required' => true, 'default' => '4:5', 'width' => 'half', 'variants' => ['split', 'type'],
                    'options' => ['4:5' => 'Hochformat 4:5', '3:4' => 'Hochformat 3:4', '1:1' => 'Quadrat 1:1', '4:3' => 'Querformat 4:3', '3:2' => 'Querformat 3:2']],
                ['name' => 'overlay', 'label' => 'Abdunkelung (Vollbild)', 'type' => 'select', 'required' => true, 'default' => 'gradient', 'width' => 'half', 'variants' => ['bleed'],
                    'options' => ['gradient' => 'Verlauf von unten (empfohlen)', 'strong' => 'Stark', 'none' => 'Keine – Titel unter dem Bild']],
                ['name' => 'height', 'label' => 'Höhe (Vollbild)', 'type' => 'select', 'required' => true, 'default' => 'screen', 'width' => 'half', 'variants' => ['bleed'],
                    'options' => ['screen' => 'Bildschirmhoch', 'large' => 'Groß (ca. 3/4)']],
            ],
        ],
        'exhibitions_list' => [
            'label' => 'Ausstellungen', 'icon' => 'calendar-dots', 'group' => 'Galerie',
            'help' => 'Laufende, kommende und vergangene Ausstellungen – der Status ergibt sich jeden Tag neu aus Beginn und Ende. Archiv nach Jahren. Auf der Detailseite einer Künstlerin bzw. eines Künstlers automatisch nur deren Ausstellungen.',
            'variants' => ['list' => 'Liste (Titel, Daten, Künstler)', 'cards' => 'Karten mit Bild', 'timeline' => 'Zeitleiste nach Jahren', 'fairs' => 'Messen & Termine (kompakt)'],
            'variant_help' => [
                'list' => 'Typografisch und ruhig – die klassische Ausstellungsübersicht.',
                'cards' => 'Mit Ausstellungsansicht – für kommende Ausstellungen auf der Startseite.',
                'timeline' => 'Archiv: Jahre als Zwischenüberschriften, Einträge kompakt.',
                'fairs' => 'Kunstmessen, Art Weeks, Gespräche: Ort, Stand, Datum in einer Zeile. Ort „Kunstmesse“ wählen.',
            ],
            'fields' => [
                ...$head(false),
                ['name' => 'show', 'label' => 'Welche Ausstellungen?', 'type' => 'select', 'required' => true, 'default' => 'tabs', 'width' => 'half',
                    'options' => ['tabs' => 'Alle – mit Reitern Aktuell · Demnächst · Archiv', 'sections' => 'Alle – untereinander mit Zwischenüberschriften',
                        'current_upcoming' => 'Laufende und kommende', 'current' => 'Nur laufende', 'upcoming' => 'Nur kommende', 'past' => 'Nur Archiv (vergangene)']],
                ['name' => 'venue', 'label' => 'Ort', 'type' => 'select', 'width' => 'half', 'default' => '',
                    'options' => ['' => 'Alle Orte', 'no_fairs' => 'Alle außer Kunstmessen', 'galerie' => 'Galerie', 'showroom' => 'Showroom', 'projektraum' => 'Projektraum', 'messe' => 'Kunstmessen']],
                ['name' => 'limit', 'label' => 'Höchstens (0 = alle)', 'type' => 'number', 'default' => 0, 'width' => 'half'],
                ['name' => 'auto_artist', 'label' => 'Auf Künstler-Detailseiten nur deren Ausstellungen', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'exclude_current', 'label' => 'Auf Ausstellungs-Detailseiten die aufgerufene weglassen', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'show_image', 'label' => 'Bilder zeigen (Liste, Zeitleiste)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                $size + ['variants' => ['cards']],
                ['name' => 'ratio', 'label' => 'Bildformat (Karten)', 'type' => 'select', 'required' => true, 'default' => '4:3', 'width' => 'half', 'variants' => ['cards'], 'options' => $ratios],
                ['name' => 'empty_text', 'label' => 'Text, wenn nichts ansteht (optional)', 'type' => 'text', 'max' => 160, 'placeholder' => 'z. B. Die nächste Ausstellung kündigen wir bald an.'],
                ['name' => 'more_label', 'label' => 'Button unter der Liste (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'more_link', 'label' => 'Button-Link', 'type' => 'link', 'width' => 'half'],
            ],
        ],
        'exhibition_detail' => [
            'label' => 'Ausstellung (Detailseite)', 'icon' => 'images', 'group' => 'Galerie',
            'help' => 'Für die Detailseite der Tabelle „Ausstellungen“: Titel, Status, Daten, Eröffnung, Ort, Künstler, Text, Ausstellungsansichten (vergrößerbar), Pressetext und die gezeigten Werke.',
            'variants' => ['bleed' => 'Vollbild-Kopf', 'split' => 'Bild und Angaben nebeneinander', 'text' => 'Typografisch (ohne großes Bild)'],
            'fields' => [
                ['name' => 'back_label', 'label' => 'Link zurück – Beschriftung', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'Alle Ausstellungen'],
                ['name' => 'back_link', 'label' => 'Link zurück', 'type' => 'link', 'width' => 'half'],
                ['name' => 'show_views', 'label' => 'Ausstellungsansichten zeigen', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'show_works', 'label' => 'Gezeigte Werke zeigen', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'works_title', 'label' => 'Überschrift über den Werken', 'type' => 'text', 'max' => 60, 'width' => 'half', 'placeholder' => 'Werke in der Ausstellung'],
                ['name' => 'press_label', 'label' => 'Beschriftung Pressetext', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'Pressetext (PDF)'],
            ],
        ],
        'artists_index' => [
            'label' => 'Künstlerinnen und Künstler', 'icon' => 'users-three', 'group' => 'Galerie',
            'help' => 'Das Programm der Galerie aus der Tabelle „Künstler“: als typografische Liste (beim Zeigen erscheint ein Bild), als Raster mit Porträt oder Werk, oder als A–Z-Register.',
            'variants' => ['list' => 'Typografische Liste mit Bild beim Zeigen', 'grid' => 'Raster mit Porträts', 'works' => 'Raster mit einem Werk je Künstler', 'az' => 'A–Z-Register'],
            'variant_help' => [
                'list' => 'Der Klassiker zeitgenössischer Galerien: große Namen untereinander, das Bild erscheint daneben, sobald man darauf zeigt oder mit der Tastatur hinkommt.',
                'grid' => 'Persönlich: Porträts (Feld „Porträt“) mit Name und Ort.',
                'works' => 'Kunst statt Gesichter: je Künstler das erste Werk aus der Tabelle „Werke“.',
                'az' => 'Lange Listen (Editionen, Gäste): Sprungmarken A–Z, Namen in Spalten.',
            ],
            'fields' => [
                ...$head(false),
                ['name' => 'selection', 'label' => 'Auswahl', 'type' => 'select', 'required' => true, 'default' => 'all', 'width' => 'half',
                    'options' => ['all' => 'Alle', 'featured' => 'Nur hervorgehobene („Programm“)']],
                ['name' => 'order', 'label' => 'Reihenfolge', 'type' => 'select', 'required' => true, 'default' => 'alpha', 'width' => 'half',
                    'options' => ['alpha' => 'Alphabetisch nach Nachname', 'manual' => 'Wie in der Tabelle sortiert']],
                ['name' => 'show_meta', 'label' => 'Geburtsjahr und Wohnort zeigen', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                $size + ['variants' => ['grid', 'works']],
                ['name' => 'ratio', 'label' => 'Bildformat (Raster)', 'type' => 'select', 'required' => true, 'default' => '3:4', 'width' => 'half', 'variants' => ['grid', 'works'], 'options' => $ratios],
            ],
        ],
        'artist_profile' => [
            'label' => 'Künstler (Detailseite)', 'icon' => 'user-circle', 'group' => 'Galerie',
            'help' => 'Für die Detailseite der Tabelle „Künstler“: Name, Porträt, Geburtsjahr und Wohnort, Biografie, Website und Anfrage. Darunter passen „Werke“ und „Ausstellungen“ – sie zeigen dort automatisch nur diese Person.',
            'variants' => ['split' => 'Porträt und Text nebeneinander', 'name' => 'Großer Name, Text darunter'],
            'fields' => [
                ['name' => 'back_label', 'label' => 'Link zurück – Beschriftung', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'Alle Künstler'],
                ['name' => 'back_link', 'label' => 'Link zurück', 'type' => 'link', 'width' => 'half'],
                ['name' => 'show_portrait', 'label' => 'Porträt zeigen', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'show_enquiry', 'label' => 'Button „Mappe anfragen“', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'enquiry_label', 'label' => 'Beschriftung des Buttons', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'Verfügbare Werke anfragen'],
            ],
        ],
        'artworks' => [
            'label' => 'Werke', 'icon' => 'paint-brush', 'group' => 'Galerie',
            'help' => 'Werke aus der Tabelle „Werke“ mit Werkangaben, Verfügbarkeit (Punkt + Wort) und Preis bzw. „Preis auf Anfrage“. Raster, Mauerwerk, Salonhängung oder Viewing Room (ein Werk je Bildschirm). Auf Detailseiten automatisch nur die Werke der Künstlerin bzw. der Ausstellung.',
            'variants' => ['grid' => 'Raster (gleiche Breite)', 'masonry' => 'Mauerwerk (Originalformate)', 'salon' => 'Salonhängung (verschiedene Größen)', 'room' => 'Viewing Room (ein Werk je Bildschirm)'],
            'variant_help' => [
                'grid' => 'Ruhige Reihen gleich breiter Werke – jedes im eigenen Format, unten bündig.',
                'masonry' => 'Spalten wie an einer Wand mit Petersburger Hängung light: Hoch- und Querformate ohne Lücken.',
                'salon' => 'Bewegte Hängung: das erste Werk groß, die anderen in wechselnden Größen.',
                'room' => 'Online-Ausstellung: ein Werk füllt den Bildschirm, Angaben und Anfrage daneben; Scrollen rastet je Werk ein.',
            ],
            'fields' => [
                ...$head(false),
                ['name' => 'source', 'label' => 'Welche Werke?', 'type' => 'select', 'required' => true, 'default' => 'auto', 'width' => 'half',
                    'options' => ['auto' => 'Automatisch (Detailseite: dieser Künstler bzw. diese Ausstellung, sonst alle)', 'all' => 'Alle Werke', 'artist' => 'Werke einer Künstlerin / eines Künstlers', 'exhibition' => 'Werke einer Ausstellung']],
                ['name' => 'ref', 'label' => 'Künstler bzw. Ausstellung (bei Auswahl)', 'type' => 'link', 'width' => 'half', 'help' => 'Eintrag aus „Daten“ wählen.'],
                ['name' => 'only_available', 'label' => 'Nur verfügbare Werke', 'type' => 'bool', 'default' => false, 'width' => 'half'],
                ['name' => 'limit', 'label' => 'Höchstens (0 = alle)', 'type' => 'number', 'default' => 0, 'width' => 'half'],
                ['name' => 'filters', 'label' => 'Filter für Besucher (Künstler, Verfügbarkeit)', 'type' => 'bool', 'default' => false, 'width' => 'half', 'variants' => ['grid', 'masonry', 'salon'],
                    'help' => 'Schaltflächen über den Werken; ohne JavaScript sind alle Werke sichtbar.'],
                ['name' => 'show_artist', 'label' => 'Künstlernamen zeigen', 'type' => 'bool', 'default' => true, 'width' => 'half', 'help' => 'Auf Künstler-Detailseiten automatisch aus.'],
                ['name' => 'enquiry', 'label' => 'Button „Anfrage zu diesem Werk“', 'type' => 'bool', 'default' => true, 'width' => 'half', 'help' => 'Nur bei nicht verkauften Werken. Ziel: Website → Galerie → „Anfragen“.'],
                ['name' => 'link_detail', 'label' => 'Werke verlinken (Detailseite)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                $size + ['variants' => ['grid', 'masonry']],
                ['name' => 'more_label', 'label' => 'Button unter den Werken (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'more_link', 'label' => 'Button-Link', 'type' => 'link', 'width' => 'half'],
            ],
        ],
        'artwork_detail' => [
            'label' => 'Werk (Detailseite)', 'icon' => 'palette', 'group' => 'Galerie',
            'help' => 'Für die Detailseite der Tabelle „Werke“: großes Bild (vergrößerbar, weitere Ansichten), vollständige Werkangaben, Verfügbarkeit, Preis, Anfrage und weitere Werke derselben Künstlerin bzw. desselben Künstlers.',
            'fields' => [
                ['name' => 'back_label', 'label' => 'Link zurück – Beschriftung', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'Alle Werke'],
                ['name' => 'back_link', 'label' => 'Link zurück', 'type' => 'link', 'width' => 'half'],
                ['name' => 'show_more', 'label' => 'Weitere Werke der Künstlerin / des Künstlers', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'more_limit', 'label' => 'Wie viele?', 'type' => 'number', 'default' => 4, 'width' => 'half'],
            ],
        ],
        'visit' => [
            'label' => 'Besuch', 'icon' => 'door-open', 'group' => 'Galerie', 'central' => 'Öffnungszeiten, Adresse und weitere Orte kommen aus „Website“ (Stammdaten und Galerie).',
            'help' => 'Öffnungszeiten (heute geöffnet/geschlossen), abweichende Zeiten zu Feiertagen, alle Orte mit Adresse und Kartenlink, Eintritt und Termine nach Vereinbarung.',
            'variants' => ['columns' => 'Orte nebeneinander', 'split' => 'Überschrift links, Angaben rechts'],
            'fields' => [
                ...$head(false),
                ['name' => 'show_today', 'label' => '„Heute geöffnet“ bzw. „Heute geschlossen“ zeigen', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'show_exceptions', 'label' => 'Abweichende Öffnungszeiten zeigen', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'show_venues', 'label' => 'Weitere Orte zeigen', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'show_map', 'label' => 'Link „Auf der Karte“ (OpenStreetMap)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'note', 'label' => 'Zusätzlicher Hinweis (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300, 'placeholder' => 'z. B. Barrierefreier Zugang über den Hof.'],
                ...$buttons,
            ],
        ],
        'richtext' => [
            'label' => 'Fließtext / Artikel', 'icon' => 'paragraph', 'group' => 'Inhalt',
            'help' => 'Freier Text mit Zwischenüberschriften, Listen, Zitaten und Links. „Artikel“: angenehme Lesebreite mit automatischem Inhaltsverzeichnis.',
            'variants' => ['standard' => 'Standard', 'article' => 'Artikel mit Inhaltsverzeichnis', 'columns' => 'Mehrspaltig (Zeitungssatz)'],
            'fields' => [
                ...$head(false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'meta', 'label' => 'Angaben über dem Text (optional, bei „Artikel“)', 'type' => 'text', 'max' => 120, 'placeholder' => 'z. B. Redaktion · 12. März 2026'],
                ['name' => 'dropcap', 'label' => 'Initiale (großer erster Buchstabe)', 'type' => 'bool', 'default' => false, 'width' => 'half'],
                ['name' => 'toc', 'label' => 'Inhaltsverzeichnis anzeigen (bei „Artikel“)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
            ],
        ],
        'media_text' => [
            'label' => 'Text + Bild', 'icon' => 'square-half', 'group' => 'Inhalt',
            'help' => '„Abwechselnd“: mehrere Blöcke nacheinander wechseln automatisch die Seite. „Geteilt“: Bild und Text je halb, randlos und auf Wunsch bildschirmhoch.',
            'variants' => ['auto' => 'Abwechselnd (automatisch)', 'right' => 'Bild rechts', 'left' => 'Bild links', 'split' => 'Geteilt, randlos (Split-Screen)', 'overlap' => 'Text-Karte über großem Bild'],
            'fields' => [
                ...$head(true, false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'list', 'label' => 'Aufzählung mit Häkchen (ein Punkt pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3],
                ...$buttons,
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half', 'help' => 'Min. 1200 px breit.'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:3', 'width' => 'half', 'options' => $ratios],
            ],
        ],
        'features' => [
            'label' => 'Merkmale / Leistungen', 'icon' => 'squares-four', 'group' => 'Inhalt',
            'help' => 'Leistungen, Vorteile oder Angebote mit Symbol oder Bild. Anzahl nebeneinander ergibt sich aus dem Platz.',
            'variants' => ['cards' => 'Karten', 'icons' => 'Große Symbole, ohne Rahmen', 'list' => 'Liste mit Symbol links', 'numbered' => 'Nummeriert'],
            'fields' => [
                ...$head(),
                $size,
                ['name' => 'items', 'label' => 'Einträge', 'type' => 'repeater', 'item_label' => 'Eintrag', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'icon', 'label' => 'Symbol', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'oder Bild (3:2)', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 70],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 320],
                    ...$link(),
                ]],
            ],
        ],
        'cards' => [
            'label' => 'Karten', 'icon' => 'copy', 'group' => 'Inhalt',
            'help' => 'Teaser mit Bild – klassisch, Text auf dem Bild, quer oder als wischbares Band (Reel). Karten stellen sich auf ihre eigene Breite ein.',
            'variants' => ['image' => 'Bild oben', 'overlay' => 'Text auf dem Bild', 'horizontal' => 'Quer (Bild links)', 'reel' => 'Wischbares Band (Reel)'],
            'fields' => [
                ...$head(),
                $size,
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '3:2', 'width' => 'half', 'options' => $ratios],
                ['name' => 'items', 'label' => 'Karten', 'type' => 'repeater', 'item_label' => 'Karte', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'image', 'label' => 'Bild', 'type' => 'media'],
                    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 90, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
                    ...$link(),
                ]],
                ['name' => 'more_label', 'label' => 'Button unter den Karten (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'more_link', 'label' => 'Button-Link', 'type' => 'link', 'width' => 'half'],
            ],
        ],
        'logos' => [
            'label' => 'Logos (Partner, Kunden)', 'icon' => 'handshake', 'group' => 'Medien',
            'help' => 'Als ruhiges Raster oder Laufband (ohne JavaScript; steht bei „Bewegung reduzieren“ still und hält bei Maus/Fokus an). Ohne Bild erscheint der Name als Schriftzug. Nur Logos mit Freigabe verwenden.',
            'variants' => ['grid' => 'Raster', 'marquee' => 'Laufband'],
            'fields' => [
                ...$head(false, false),
                ['name' => 'items', 'label' => 'Logos', 'type' => 'repeater', 'item_label' => 'Logo', 'title_field' => 'name', 'max_items' => 16, 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ['name' => 'link', 'label' => 'Link (optional)', 'type' => 'link', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'Logo (SVG oder PNG, transparent)', 'type' => 'media'],
                ]],
            ],
        ],
        'quote' => [
            'label' => 'Zitat / Stimmen', 'icon' => 'quotes', 'group' => 'Inhalt',
            'help' => '„Groß“: ein Zitat als Blickfang (Pull-Quote). „Raster“ und „Band“ für mehrere Stimmen. Nur echte, freigegebene Stimmen verwenden.',
            'variants' => ['single' => 'Groß (ein Zitat)', 'grid' => 'Raster (Mauerwerk)', 'reel' => 'Wischbares Band'],
            'fields' => [
                ...$head(false, false),
                ['name' => 'items', 'label' => 'Zitate', 'type' => 'repeater', 'item_label' => 'Zitat', 'title_field' => 'name', 'max_items' => 12, 'fields' => [
                    ['name' => 'text', 'label' => 'Zitat', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 480],
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'width' => 'half'],
                    ['name' => 'role', 'label' => 'Funktion / Organisation', 'type' => 'text', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'Porträt (optional, 1:1)', 'type' => 'media'],
                ]],
            ],
        ],
        'steps' => [
            'label' => 'Ablauf / Zeitleiste', 'icon' => 'path', 'group' => 'Inhalt',
            'help' => 'Nummerierte Schritte, eine Zeitleiste mit Datum/Phase oder ein Prozess mit Verbindungslinien.',
            'variants' => ['numbers' => 'Nummerierte Schritte', 'timeline' => 'Zeitleiste', 'process' => 'Prozess mit Pfeilen'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Schritte', 'type' => 'repeater', 'item_label' => 'Schritt', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'meta', 'label' => 'Phase / Datum (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Woche 1'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 80, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 400],
                    ['name' => 'icon', 'label' => 'Symbol statt Nummer (optional)', 'type' => 'icon'],
                ]],
            ],
        ],
        'faq' => [
            'label' => 'Fragen & Antworten', 'icon' => 'question', 'group' => 'Inhalt',
            'help' => 'Aufklappbare Fragen (ohne JavaScript). Suchmaschinen erhalten die Fragen als strukturierte Daten (FAQPage).',
            'variants' => ['split' => 'Überschrift links, Fragen rechts', 'stacked' => 'Untereinander, zentriert'],
            'jsonld' => ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a'],
            'fields' => [
                ...$head(true),
                ['name' => 'items', 'label' => 'Fragen', 'type' => 'repeater', 'item_label' => 'Frage', 'title_field' => 'q', 'fields' => [
                    ['name' => 'q', 'label' => 'Frage', 'type' => 'text', 'required' => true, 'max' => 160],
                    ['name' => 'a', 'label' => 'Antwort', 'type' => 'richtext', 'required' => true],
                ]],
            ],
        ],
        'cta' => [
            'label' => 'Handlungsaufruf', 'icon' => 'megaphone', 'group' => 'Inhalt', 'background' => 'accent',
            'variants' => ['band' => 'Band über die volle Breite', 'box' => 'Hervorgehobene Box', 'split' => 'Mit Bild', 'big' => 'Großer Schriftzug'],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true, 'max' => 110],
                ['name' => 'text', 'label' => 'Text (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ...$buttons,
                ['name' => 'image', 'label' => 'Bild (bei „Mit Bild“)', 'type' => 'media'],
            ],
        ],
        'tabs' => [
            'label' => 'Reiter (Tabs)', 'icon' => 'list-bullets', 'group' => 'Inhalt',
            'help' => 'Mehrere Inhalte auf engem Raum, umschaltbar über Reiter. „Reiter links“ stellt sich bei wenig Platz automatisch oben an. Ohne JavaScript stehen alle Inhalte untereinander.',
            'variants' => ['top' => 'Reiter oben', 'side' => 'Reiter links'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Reiter', 'type' => 'repeater', 'item_label' => 'Reiter', 'title_field' => 'label', 'max_items' => 8, 'fields' => [
                    ['name' => 'label', 'label' => 'Beschriftung des Reiters', 'type' => 'text', 'required' => true, 'max' => 40],
                    ['name' => 'text', 'label' => 'Inhalt', 'type' => 'richtext'],
                    ['name' => 'image', 'label' => 'Bild (optional, 4:3)', 'type' => 'media'],
                ]],
            ],
        ],
        'video' => [
            'label' => 'Video', 'icon' => 'play-circle', 'group' => 'Medien',
            'help' => 'YouTube/Vimeo mit Zwei-Klick-Lösung (Vorschaubild vom eigenen Server, ohne Cookies bis zum Klick) oder eigene MP4-Datei mit Untertiteln und Transkript aus der Mediathek.',
            'variants' => ['wide' => 'Breit', 'text' => 'Mit Text daneben', 'cinema' => 'Kino (randlos, dunkel)'],
            'fields' => [
                ...$head(false),
                ['name' => 'video_url', 'label' => 'YouTube- oder Vimeo-Link', 'type' => 'url', 'width' => 'half'],
                ['name' => 'video_file', 'label' => 'oder eigene MP4-Datei', 'type' => 'file', 'width' => 'half',
                    'help' => 'Untertitel und Transkript pflegen Sie in der Mediathek an der Datei.'],
                ['name' => 'poster', 'label' => 'Eigenes Vorschaubild (optional)', 'type' => 'media', 'width' => 'half'],
                ['name' => 'ratio', 'label' => 'Format', 'type' => 'select', 'required' => true, 'default' => '16-9', 'width' => 'half', 'options' => ['16-9' => '16:9', '4-3' => '4:3', '21-9' => '21:9']],
                ['name' => 'caption', 'label' => 'Bildunterschrift (optional)', 'type' => 'text', 'max' => 200],
                ['name' => 'text', 'label' => 'Text daneben (bei „Mit Text daneben“)', 'type' => 'richtext'],
            ],
        ],
        'contact' => [
            'label' => 'Kontakt (Karte, Formular, Zeiten)', 'icon' => 'address-book', 'group' => 'Website',
            'central' => 'Adresse, Telefon, E-Mail, Öffnungszeiten und Karte kommen aus „Website“.',
            'help' => 'Kontaktangaben, Öffnungszeiten, Karte und optional ein öffentliches Formular (Datentabelle) in einem Abschnitt.',
            'fields' => [
                ...$head(),
                ['name' => 'show_hours', 'label' => 'Öffnungszeiten anzeigen (falls eingetragen)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'show_map', 'label' => 'Karte anzeigen (falls Standort eingetragen)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'form_table', 'label' => 'Formular (optional)', 'type' => 'datatable', 'inbox' => true,
                    'help' => 'Tabelle mit eingeschaltetem „Öffentliches Formular“ – z. B. Rückrufwunsch oder Anfrage.'],
                ['name' => 'form_title', 'label' => 'Überschrift über dem Formular', 'type' => 'text', 'max' => 80, 'width' => 'half', 'placeholder' => 'z. B. Schreiben Sie uns'],
                ['name' => 'submit_label', 'label' => 'Beschriftung des Buttons (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'note', 'label' => 'Hinweis unten (optional)', 'type' => 'text'],
            ],
        ],
        'downloads' => [
            'label' => 'Downloads', 'icon' => 'download-simple', 'group' => 'Medien',
            'help' => 'PDFs lassen sich im Browser ansehen (Mozilla PDF.js) oder herunterladen. Größe, Typ und Seitenzahl erscheinen automatisch.',
            'fields' => [
                ...$head(),
                ['name' => 'source', 'label' => 'Quelle', 'type' => 'select', 'required' => true, 'default' => 'manual', 'width' => 'half',
                    'options' => ['manual' => 'Einzelne Dateien auswählen', 'collection' => 'Alle Dateien einer Sammlung']],
                ['name' => 'collection', 'label' => 'Sammlung', 'type' => 'collection', 'width' => 'half'],
                ['name' => 'files', 'label' => 'Dateien (bei „Einzelne Dateien“)', 'type' => 'repeater', 'item_label' => 'Datei', 'title_field' => 'label', 'fields' => [
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'width' => 'half', 'help' => 'Leer = Titel der Datei'],
                    ['name' => 'file', 'label' => 'Datei', 'type' => 'file', 'width' => 'half'],
                    ['name' => 'note', 'label' => 'Hinweis (optional)', 'type' => 'text'],
                ]],
                ['name' => 'show_viewer', 'label' => 'PDFs zusätzlich im Browser ansehen lassen (PDF.js)', 'type' => 'bool', 'default' => true],
            ],
        ],
        'map' => [
            'label' => 'Karte', 'icon' => 'map-trifold', 'group' => 'Website',
            'help' => 'Interaktive Karte (OpenStreetMap-Daten über den eigenen Server – ohne Einwilligung, ohne Cookies).',
            'fields' => [...$head(false, false), ...\Core\Maps::blockFields()],
        ],
    ],
];
