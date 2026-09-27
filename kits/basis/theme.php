<?php
/*
 * Theme „basis“ – neutrales Start-Theme für KLXM Studio
 *
 * Branchenneutral (Unternehmen, Agentur, Verein, Handwerk, Studio) und von Anfang an mehrsprachig.
 * Diese Datei definiert alles Projektspezifische:
 *  - blocks     Blocktypen (= Editor.js-Tools) mit Feld-Schema
 *  - settings   zentrales Einstellungsformular („Website“)
 *  - project    sichtbare Begriffe, Karte, Öffnungszeiten, Hinweisbalken (Core bleibt neutral)
 *  - seo/jsonld strukturierte Daten
 * Eigenes Kundenprojekt: php bin/console theme:create kunde basis  (Präfix basis_* → kunde_*)
 */

require_once __DIR__ . '/functions.php';

// Wiederkehrende Felder: Dachzeile, Überschrift, Einleitung
$head = fn(bool $required = false, bool $intro = true) => array_merge([
    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
    ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'max' => 90, 'width' => 'half', 'required' => $required],
], $intro ? [['name' => 'intro', 'label' => 'Einleitung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 400]] : []);

$buttons = [
    ['name' => 'button_label', 'label' => 'Button 1 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button_link', 'label' => 'Button 1 – Link', 'type' => 'link', 'width' => 'half',
        'help' => 'Seite, #anker, https://… – Sonderwerte „phone“ und „email“ nutzen „Website“.'],
    ['name' => 'button2_label', 'label' => 'Button 2 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button2_link', 'label' => 'Button 2 – Link', 'type' => 'link', 'width' => 'half'],
];

$ratios = ['4:3' => 'Querformat 4:3', '3:2' => 'Querformat 3:2', '16:9' => 'Breitbild 16:9', '1:1' => 'Quadrat 1:1', '3:4' => 'Hochformat 3:4'];
$columns = ['2' => '2 Spalten', '3' => '3 Spalten', '4' => '4 Spalten'];
$days = ['1' => 'Montag', '2' => 'Dienstag', '3' => 'Mittwoch', '4' => 'Donnerstag', '5' => 'Freitag', '6' => 'Samstag', '0' => 'Sonntag'];

return [
    'label' => 'Basis (neutral)',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',
    'source_lang' => 'de',

    'backgrounds' => ['white' => 'Standard', 'muted' => 'Getönt', 'accent' => 'Akzentfarbe', 'dark' => 'Dunkel'],
    'dark_backgrounds' => ['accent', 'dark'],
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
    'jsonld' => 'basis_jsonld',

    // Link-Sonderwerte (aufgelöst in basis_link): nutzen „Website“
    'link_keywords' => ['phone', 'email'],

    'project' => [
        'map' => ['location' => 'geo', 'label' => 'org_name', 'address' => ['street', 'zip', 'city']],
        'terms' => [
            'org' => 'Organisation',
            'site_name' => 'Name der Website',
        ],
        'name_setting' => 'org_name',
        'brand' => ['short_name', 'tagline'],
        'setup_checks' => [
            ['setting' => 'org_name', 'label' => 'Name eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'email', 'label' => 'E-Mail-Adresse eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'street', 'label' => 'Adresse eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'imprint_page', 'label' => 'Impressum zugeordnet', 'link' => '/admin/settings#recht'],
        ],
        'dashboard_hint' => 'Name, Adresse, Telefon, E-Mail, Öffnungszeiten und Logo werden zentral gepflegt und erscheinen automatisch überall auf der Website. Farben, Schriften und Navigation: Verwaltung → Design.',
        'public_info' => 'basis_public_info',
        'hours' => ['setting' => 'hours', 'format' => 'basis_time_range', 'label' => 'Öffnungszeiten'],
        'notice' => ['text' => 'notice_text', 'active' => 'notice_active', 'example' => 'z. B. Betriebsferien, geänderte Öffnungszeiten'],
        'link_labels' => ['phone' => 'Telefon (aus „Website“)', 'email' => 'E-Mail (aus „Website“)'],
        'search_keywords' => 'kontakt adresse telefon e-mail öffnungszeiten logo farbe',
        'mcp' => [
            'instructions' => 'Die Website ist branchenneutral aufgebaut. Erfinde keine Fakten (Zahlen, Referenzen, Kundennamen, Rechtstexte); Platzhalter in [eckigen Klammern] nur mit Angaben der Website-Betreiber ersetzen.',
        ],
    ],

    // Schriften: Auswahl im Style-Editor (design.fonts); Vorladen übernimmt basis_font_preloads() je nach Auswahl
    'fonts' => [
        'icon' => 'fonts/Inter_700Bold.ttf',
    ],
    'app' => [
        'defaults' => ['icon_text' => 'M', 'icon_bg' => '#0F6E68', 'icon_fg' => '#FFFFFF', 'icon_dot' => '#5DCABE', 'icon_dot_enabled' => false],
        'info' => 'basis_app_info',
    ],
    'image_ratios' => [
        '16:9' => 'Breitbild 16:9', '3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', '1:1' => 'Quadrat 1:1', '3:4' => 'Hochformat 3:4',
    ],

    // Selten genutzte Blöcke: eigenes Stylesheet, nur auf Seiten mit diesen Blöcken
    // Kern-Fragmente (app/Views/fragments, Core\Fragments): Video-Zwei-Klick, Editor und Werkzeugleiste kommen nur aus dem Kern
    // (Skript resources/js/embed.js lädt das Fragment selbst); Marke, Sprachumschalter, Öffnungszeiten … ebenso, solange das Kit
    // keine eigene Datei in fragments/ bzw. templates/partials/ mitbringt.
    // Optionen je Fragment: 'fragments' => ['brand' => ['mark' => 'dot'], 'video-embed' => ['ratio_class' => 'ratio-']]
    'conditional_css' => [
        'css/blocks.css' => ['stats', 'quote', 'faq', 'logos', 'downloads', 'contact', 'pricing', 'steps', 'tabs', 'video'],
        'css/extra.css' => ['gallery', 'slideshow', 'stack_cards', 'contact', 'map'],
        'js/blocks.js' => ['tabs'],
        'css/rich.css' => ['@rich'],   // Rich-Text-Stile (t-lead, t-small, t-note, c-*, mark) – nur wenn die Seite sie ausgibt (Core\Sanitizer::styled)
        'css/hero-x.css' => ['hero:search', 'hero:form', 'hero:map'],   // Einstieg mit Werkzeug (Suche, Formular, Standort)
    ],

    // ------------------------------------------------------------ Design (Style-Editor: Verwaltung → Design)
    'design' => require __DIR__ . '/design.php',

    // Kopfbereich-Aktionen (header_actions()): Button-Klassen des Kits für die Stile „Gefüllt“, „Kontur“ und „Geteilt“
    'header_actions' => ['late' => true, 'base_css' => false, 'plain' => ['navitem'], 'kit_css' => 'css/header-actions-kit.css', 'classes' => ['solid' => 'btn btn--primary btn--small', 'outline' => 'btn btn--secondary btn--small', 'split' => 'btn btn--primary btn--small', 'navitem' => 'nav__link nav__link--cta']],

    // ------------------------------------------------------------ Website
    'settings' => [
        'title' => 'Website',
        'groups' => [
            ['id' => 'stammdaten', 'label' => 'Stammdaten', 'fields' => [
                ['name' => 'org_name', 'label' => 'Name (Unternehmen, Verein, Büro …)', 'type' => 'text', 'required' => true,
                    'help' => 'Vollständiger Name, z. B. für Impressum-Hinweise, Karte und Suchmaschinen.'],
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
                ['name' => 'hours_note', 'label' => 'Hinweis unter den Öffnungszeiten', 'type' => 'text'],
                ['type' => 'heading', 'label' => 'Karte'],
                ['name' => 'geo', 'label' => 'Standort auf der Karte', 'type' => 'geo', 'address_fields' => ['street', 'zip', 'city'], 'translate' => false,
                    'help' => 'Aus der Adresse ermitteln oder in die Karte klicken. Leer = keine Karte. Die Karte lädt über den eigenen Server – ohne Einwilligung, ohne Cookies.'],
            ]],
            ['id' => 'darstellung', 'label' => 'Darstellung', 'fields' => [
                ['name' => 'logo', 'label' => 'Logo (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'PNG oder WebP mit transparentem Hintergrund (SVG wird nicht unterstützt), ca. 40 px hoch dargestellt.'],
                ['name' => 'logo_dark', 'label' => 'Logo für dunkles Farbschema (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'Helle Fassung des Logos. Leer = normales Logo.'],
                ['name' => 'footer_text', 'label' => 'Text im Fußbereich', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
                // Kopfbereich-Aktionen (Core\HeaderActions): Handlungsaufruf, zweite Aktion, Status-Bezeichnung, Anmelden
                ...\Core\HeaderActions::settingsFields(),
            ]],
            ['id' => 'hinweis', 'label' => 'Hinweisbalken', 'fields' => [
                ['name' => 'notice_active', 'label' => 'Hinweis oben auf jeder Seite anzeigen', 'type' => 'bool', 'default' => false, 'translate' => false],
                ['name' => 'notice_text', 'label' => 'Hinweis', 'type' => 'inline', 'help' => 'Kurz halten, z. B. Betriebsferien oder geänderte Öffnungszeiten. Links sind erlaubt.'],
            ]],
            ['id' => 'social', 'label' => 'Social Media', 'fields' => [
                ['name' => 'social', 'label' => 'Profile', 'type' => 'repeater', 'item_label' => 'Profil', 'title_field' => 'label', 'translate' => false,
                    'help' => 'Erscheinen im Fußbereich als einfache Links – ohne eingebettete Inhalte oder Tracking.',
                    'fields' => [
                        ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'width' => 'half', 'placeholder' => 'z. B. LinkedIn'],
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
                ['name' => 'schema_type', 'label' => 'Art der Organisation (strukturierte Daten)', 'type' => 'select', 'required' => true, 'default' => 'Organization', 'translate' => false,
                    'options' => ['Organization' => 'Organisation allgemein', 'LocalBusiness' => 'Lokales Geschäft / Betrieb', 'ProfessionalService' => 'Dienstleister / Büro', 'NGO' => 'Verein / gemeinnützige Organisation']],
            ]],
        ],
    ],

    // ------------------------------------------------------------ Blöcke
    'blocks' => [
        'hero' => [
            'label' => 'Einstieg (Hero)', 'icon' => '★', 'group' => 'Kopf',
            'help' => 'Große Überschrift der Seite (H1). Pro Seite genau einmal verwenden – „Seitenkopf“ für Unterseiten. Such-Einstieg, Formular und Standort bringen ein Werkzeug direkt in den Einstieg.',
            'variants' => ['split' => 'Text und Bild nebeneinander', 'centered' => 'Zentriert, Bild darunter', 'compact' => 'Seitenkopf (ohne Bild)',
                'search' => 'Such-Einstieg (Suchfeld mit Vorschlägen)', 'form' => 'Mit Formular (z. B. Rückruf)', 'map' => 'Standort (Karte, Kontakt, Öffnungszeiten)'],
            // Wann welche Variante? (Handbuch → Alle Blöcke → Einstieg)
            'variant_help' => [
                'split' => 'Startseite mit einem aussagekräftigen Bild.',
                'centered' => 'Kurze, starke Botschaft; Bild in voller Breite darunter.',
                'compact' => 'Unterseiten: Seitentitel mit kurzer Einleitung.',
                'search' => 'Viele Inhalte, Besucher wissen, was sie suchen: Verwaltung, Verband, Verein, Service-Portal.',
                'form' => 'Ein Ziel steht im Vordergrund: Rückruf, Terminanfrage, Angebot – das Formular ist sofort sichtbar.',
                'map' => 'Besuch vor Ort ist wichtig: Geschäft, Praxis, Werkstatt – Anschrift, „jetzt geöffnet“ und Karte auf einen Blick.',
            ],
            // Stylesheets, die eine Variante zusätzlich braucht (Formular-Stile bei „Mit Formular“, Kartenstile bei „Standort“)
            'uses' => ['form' => ['data_form'], 'map' => ['map']],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift (H1)', 'type' => 'text', 'required' => true, 'max' => 90],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 320],
                ...$buttons,
                ['name' => 'image', 'label' => 'Bild (optional)', 'type' => 'media', 'width' => 'half', 'help' => 'Min. 1600 px breit. Alt-Text in der Mediathek pflegen.',
                    'variants' => ['split', 'centered']],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:3', 'width' => 'half', 'options' => $ratios,
                    'help' => 'Bei „Zentriert“ wird das Bild immer im Breitbild 16:9 gezeigt.', 'variants' => ['split', 'centered']],
                // Varianten mit Werkzeug: Felder erscheinen nur bei der passenden Variante (Core\Blocks\Hero)
                ...\Core\Blocks\Hero::fields('search', ['search']),
                ['name' => 'points', 'label' => 'Kurze Pluspunkte (eine pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3, 'variants' => ['form'],
                    'help' => 'Erscheinen mit Häkchen unter dem Text, z. B. „Rückruf am selben Tag“.'],
                ...\Core\Blocks\Hero::fields('form', ['form']),
                ...\Core\Blocks\Hero::fields('map', ['map']),
            ],
        ],
        'richtext' => [
            'label' => 'Fließtext', 'icon' => '¶', 'group' => 'Inhalt',
            'help' => 'Freier Text mit Zwischenüberschriften, Listen und Links – z. B. für Rechtstexte.',
            'fields' => [...$head(false, false), ['name' => 'text', 'label' => 'Text', 'type' => 'richtext']],
        ],
        'text_image' => [
            'label' => 'Text + Bild', 'icon' => '◧', 'group' => 'Inhalt',
            'variants' => ['right' => 'Bild rechts', 'left' => 'Bild links'],
            'fields' => [
                ...$head(true, false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'list', 'label' => 'Aufzählung mit Häkchen (ein Punkt pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3],
                ['name' => 'button_label', 'label' => 'Button – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'button_link', 'label' => 'Button – Link', 'type' => 'link', 'width' => 'half'],
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half', 'help' => 'Min. 1200 px breit.'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:3', 'width' => 'half', 'options' => $ratios],
            ],
        ],
        'features' => [
            'label' => 'Merkmale / Leistungen', 'icon' => '▦', 'group' => 'Inhalt',
            'help' => 'Karten mit Symbol oder Bild, Titel und kurzem Text – für Leistungen, Vorteile oder Angebote.',
            'fields' => [
                ...$head(),
                ['name' => 'columns', 'label' => 'Spalten', 'type' => 'select', 'required' => true, 'default' => '3', 'width' => 'half', 'options' => $columns],
                ['name' => 'style', 'label' => 'Darstellung', 'type' => 'select', 'required' => true, 'default' => 'grid', 'width' => 'half',
                    'options' => ['grid' => 'Raster mit Linien', 'cards' => 'Karten mit Rahmen', 'plain' => 'Schlicht (ohne Linien)']],
                ['name' => 'items', 'label' => 'Einträge', 'type' => 'repeater', 'item_label' => 'Eintrag', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 60],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 300],
                    ['name' => 'icon', 'label' => 'Symbol', 'type' => 'select', 'width' => 'half', 'options' => ['' => '– kein Symbol –'] + basis_icon_options()],
                    ['name' => 'image', 'label' => 'oder Bild (3:2)', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'link_label', 'label' => 'Link – Beschriftung (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                    ['name' => 'link', 'label' => 'Link', 'type' => 'link', 'width' => 'half'],
                ]],
            ],
        ],
        'stats' => [
            'label' => 'Kennzahlen', 'icon' => '#', 'group' => 'Inhalt',
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Kennzahlen', 'type' => 'repeater', 'item_label' => 'Kennzahl', 'title_field' => 'value', 'max_items' => 4, 'fields' => [
                    ['name' => 'value', 'label' => 'Wert', 'type' => 'text', 'required' => true, 'max' => 12, 'width' => 'half', 'placeholder' => 'z. B. 25+'],
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'max' => 40, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Erläuterung (optional)', 'type' => 'text', 'max' => 120],
                ]],
            ],
        ],
        'quote' => [
            'label' => 'Zitat / Stimmen', 'icon' => '❝', 'group' => 'Inhalt',
            'help' => 'Ein Eintrag = großes Zitat. Zwei oder drei Einträge = Karten nebeneinander. Nur echte, freigegebene Stimmen verwenden.',
            'fields' => [
                ...$head(false, false),
                ['name' => 'items', 'label' => 'Zitate', 'type' => 'repeater', 'item_label' => 'Zitat', 'title_field' => 'name', 'max_items' => 3, 'fields' => [
                    ['name' => 'text', 'label' => 'Zitat', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 400],
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'width' => 'half'],
                    ['name' => 'role', 'label' => 'Funktion / Firma', 'type' => 'text', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'Porträt (optional, 1:1)', 'type' => 'media'],
                ]],
            ],
        ],
        'faq' => [
            'label' => 'Fragen & Antworten', 'icon' => '?', 'group' => 'Inhalt',
            'jsonld' => ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a'],   // schema.org FAQPage
            'fields' => [
                ...$head(true),
                ['name' => 'items', 'label' => 'Fragen', 'type' => 'repeater', 'item_label' => 'Frage', 'title_field' => 'q', 'fields' => [
                    ['name' => 'q', 'label' => 'Frage', 'type' => 'text', 'required' => true, 'max' => 160],
                    ['name' => 'a', 'label' => 'Antwort', 'type' => 'richtext', 'required' => true],
                ]],
            ],
        ],
        'cta' => [
            'label' => 'Handlungsaufruf', 'icon' => '➜', 'group' => 'Inhalt', 'background' => 'accent',
            'variants' => ['band' => 'Band über die volle Breite', 'box' => 'Hervorgehobene Box'],
            'fields' => [
                ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true, 'max' => 90],
                ['name' => 'text', 'label' => 'Text (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ...$buttons,
            ],
        ],
        'pricing' => [
            'label' => 'Leistungspakete / Preise', 'icon' => '€', 'group' => 'Inhalt',
            'help' => 'Zwei bis vier Pakete nebeneinander – mit Preis, Leistungsliste und Button. Ein Paket lässt sich als „Empfohlen“ hervorheben. Nur verbindliche, aktuelle Preise angeben.',
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Pakete', 'type' => 'repeater', 'item_label' => 'Paket', 'title_field' => 'name', 'max_items' => 4, 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 40, 'width' => 'half'],
                    ['name' => 'badge', 'label' => 'Hinweis-Etikett (optional)', 'type' => 'text', 'max' => 24, 'width' => 'half', 'placeholder' => 'z. B. Empfohlen'],
                    ['name' => 'price', 'label' => 'Preis', 'type' => 'text', 'max' => 24, 'width' => 'half', 'placeholder' => 'z. B. ab 490 €'],
                    ['name' => 'period', 'label' => 'Zeitraum / Zusatz', 'type' => 'text', 'max' => 32, 'width' => 'half', 'placeholder' => 'z. B. pro Monat, einmalig'],
                    ['name' => 'text', 'label' => 'Kurzbeschreibung', 'type' => 'textarea', 'rows' => 2, 'max' => 220],
                    ['name' => 'features', 'label' => 'Leistungen (eine pro Zeile)', 'type' => 'textarea', 'rows' => 4],
                    ['name' => 'highlight', 'label' => 'Hervorheben', 'type' => 'bool', 'default' => false],
                    ['name' => 'button_label', 'label' => 'Button – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                    ['name' => 'button_link', 'label' => 'Button – Link', 'type' => 'link', 'width' => 'half'],
                ]],
                ['name' => 'note', 'label' => 'Hinweis unter den Paketen (optional)', 'type' => 'text', 'max' => 200, 'placeholder' => 'z. B. Alle Preise inkl. MwSt.'],
            ],
        ],
        'steps' => [
            'label' => 'Ablauf / Zeitleiste', 'icon' => '⋯', 'group' => 'Inhalt',
            'help' => 'Schritte eines Ablaufs (nummeriert nebeneinander) oder eine Zeitleiste mit Datum/Phase untereinander.',
            'variants' => ['numbers' => 'Nummerierte Schritte', 'timeline' => 'Zeitleiste'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Schritte', 'type' => 'repeater', 'item_label' => 'Schritt', 'title_field' => 'title', 'max_items' => 10, 'fields' => [
                    ['name' => 'meta', 'label' => 'Phase / Datum (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Woche 1'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 80, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 400],
                ]],
            ],
        ],
        'tabs' => [
            'label' => 'Reiter (Tabs)', 'icon' => '⊟', 'group' => 'Inhalt',
            'help' => 'Mehrere Inhalte auf engem Raum, umschaltbar über Reiter. Ohne JavaScript stehen alle Inhalte untereinander.',
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
            'label' => 'Video', 'icon' => '▶', 'group' => 'Medien',
            'help' => 'YouTube/Vimeo mit Zwei-Klick-Lösung: Vorschaubild vom eigenen Server, Player erst nach Klick (ohne Cookies bis dahin). Eigene MP4-Dateien laufen direkt.',
            'variants' => ['wide' => 'Breit', 'text' => 'Mit Text daneben'],
            'fields' => [
                ...$head(false),
                ['name' => 'video_url', 'label' => 'YouTube- oder Vimeo-Link', 'type' => 'url', 'width' => 'half'],
                ['name' => 'video_file', 'label' => 'oder eigene MP4-Datei', 'type' => 'file', 'width' => 'half'],
                ['name' => 'poster', 'label' => 'Eigenes Vorschaubild (optional)', 'type' => 'media', 'width' => 'half'],
                ['name' => 'ratio', 'label' => 'Format', 'type' => 'select', 'required' => true, 'default' => '16-9', 'width' => 'half', 'options' => ['16-9' => '16:9', '4-3' => '4:3']],
                ['name' => 'caption', 'label' => 'Bildunterschrift (optional)', 'type' => 'text', 'max' => 200],
                ['name' => 'text', 'label' => 'Text daneben (bei „Mit Text daneben“)', 'type' => 'richtext'],
            ],
        ],
        // Bildergalerie, Slider, Stapelkarten: Kern-Blöcke (app/Blocks) – Aussehen in css/extra.css
        'logos' => [
            'label' => 'Logos (Partner, Kunden)', 'icon' => '◎', 'group' => 'Medien',
            'help' => 'Ohne Bild erscheint der Name als Schriftzug. Nur Logos verwenden, für die eine Freigabe vorliegt.',
            'fields' => [
                ...$head(false, false),
                ['name' => 'items', 'label' => 'Logos', 'type' => 'repeater', 'item_label' => 'Logo', 'title_field' => 'name', 'max_items' => 12, 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ['name' => 'link', 'label' => 'Link (optional)', 'type' => 'link', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'Logo (PNG oder WebP, transparent)', 'type' => 'media'],
                ]],
            ],
        ],
        'contact' => [
            'label' => 'Kontakt', 'icon' => '✉', 'group' => 'Website',
            'central' => 'Adresse, Telefon, E-Mail, Öffnungszeiten und Karte kommen aus „Website“.',
            'fields' => [
                ...$head(),
                ['name' => 'show_hours', 'label' => 'Öffnungszeiten anzeigen (falls eingetragen)', 'type' => 'bool', 'default' => true],
                ['name' => 'show_map', 'label' => 'Karte anzeigen (falls Standort eingetragen)', 'type' => 'bool', 'default' => true],
                ['name' => 'note', 'label' => 'Hinweis unten (optional)', 'type' => 'text'],
            ],
        ],
        'downloads' => [
            'label' => 'Downloads', 'icon' => '↓', 'group' => 'Medien',
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
            'label' => 'Karte', 'icon' => '⌖', 'group' => 'Website',
            'help' => 'Interaktive Karte (OpenStreetMap-Daten über den eigenen Server – ohne Einwilligung, ohne Cookies).',
            'fields' => [...$head(false, false), ...\Core\Maps::blockFields()],
        ],
    ],
];
