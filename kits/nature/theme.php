<?php
/*
 * Kit „nature“ – organisch, warm, ruhig. Für KLXM Studio.
 *
 * Für Höfe, Gärtnereien, Naturschutz, Umweltbildung, Outdoor und nachhaltige Betriebe. Eigenständige Gestaltung –
 * keine Marken, Logos, Texte oder Bilder Dritter; alle Illustrationen entstehen im Kit (Inline-SVG bzw. tools/).
 *
 * Formensprache: erdige Palette (Moos, Farn, Sand, Ton, Borke, Himmel), Waldnacht als dunkles Schema; weiche
 * Serif (Fraunces, SOFT-Achse) + freundliche Grotesk (Nunito Sans); Blattmarken, Wellen- und Hügel-Übergänge,
 * Höhenlinien und Papierstruktur als erzeugte SVG; Karten mit weichem Radius, Buttons 8 px. Vier Jahreszeiten als
 * Vorlagen im Style-Editor; Bewegung nur als kaum merkliches Treiben (nie bei „Bewegung reduzieren“).
 *
 *  - blocks     Blocktypen (= Editor.js-Tools) mit Feld-Schema und Varianten – Feldnamen wie in „essenz“/„fluid“,
 *               damit Websites das Kit wechseln können (hero, richtext, media_text, features, cards, stats, steps,
 *               quote, faq, cta, contact, downloads, video, map) + eigene: principles (Grundsätze), specs (Steckbrief), team
 *  - settings   zentrales Einstellungsformular („Website“) – Schlüssel wie „essenz“/„fluid“ + Saisonzeiten und Anfahrt
 *  - design     design.php (Style-Editor, Vorlagen Moos, Frühling, Sommer, Herbst, Winter, Waldnacht)
 * Eigenes Kundenprojekt: php bin/console kit:create kunde nature  (Präfix nature_* → kunde_*)
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
$size = ['name' => 'size', 'label' => 'Breite je Eintrag (mindestens)', 'type' => 'select', 'required' => true, 'default' => 'm', 'width' => 'half',
    'options' => ['s' => 'Schmal (viele nebeneinander)', 'm' => 'Mittel', 'l' => 'Breit (wenige nebeneinander)'],
    'help' => 'Wie viele Einträge nebeneinander passen, ergibt sich aus dem verfügbaren Platz – auf jedem Gerät.'];
$days = ['1' => 'Montag', '2' => 'Dienstag', '3' => 'Mittwoch', '4' => 'Donnerstag', '5' => 'Freitag', '6' => 'Samstag', '0' => 'Sonntag'];
$link = fn(string $prefix = 'link', string $label = 'Link') => [
    ['name' => $prefix . '_label', 'label' => $label . ' – Beschriftung (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => $prefix, 'label' => $label, 'type' => 'link', 'width' => 'half'],
];

return [
    'label' => 'Nature – organisch & ruhig',
    'description' => 'Warm, erdig und ruhig für Höfe, Gärtnereien, Naturschutz und Outdoor: Moos, Sand und Ton, weiche Serif, Blattmarken, Wellen und Höhenlinien, vier Jahreszeiten als Vorlagen, Waldnacht als dunkles Schema.',
    'description_en' => 'Warm, earthy and calm for farms, garden centres, nature conservation and outdoor: moss, sand and clay, soft serif, generated landscapes per season.',
    'category' => 'branch',   // Willkommen-Bildschirm: general (Allgemein) | branch (Branchen & Themen) | dev (Entwickler)
    'version' => '1.0.0',
    'requires' => '>=1.0.0',
    'source_lang' => 'de',

    'backgrounds' => ['white' => 'Papier (Standard)', 'muted' => 'Sand', 'tint' => 'Moos hell', 'accent' => 'Akzentfarbe', 'dark' => 'Waldnacht (dunkel)'],
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
    'jsonld' => 'nature_jsonld',

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
        'dashboard_hint' => 'Name, Adresse, Telefon, E-Mail, Öffnungs- und Saisonzeiten, Anfahrt und Logo werden zentral gepflegt und erscheinen automatisch überall auf der Website. Farben, Jahreszeit, Schriften, Kopf- und Fußbereich: Verwaltung → Design.',
        'public_info' => 'nature_public_info',
        'hours' => ['setting' => 'hours', 'format' => 'nature_time_range', 'label' => 'Öffnungszeiten'],
        'notice' => ['text' => 'notice_text', 'active' => 'notice_active', 'example' => 'z. B. Erntepause, Hofladen wegen Hoffest geschlossen'],
        'link_labels' => ['phone' => 'Telefon (aus „Website“)', 'email' => 'E-Mail (aus „Website“)'],
        'search_keywords' => 'kontakt adresse telefon e-mail öffnungszeiten saison anfahrt logo farbe jahreszeit',
        'mcp' => [
            'instructions' => 'Das Kit ist warm, ruhig und bodenständig: anschauliche, einfache Sätze, keine Superlative, kein Öko-Kitsch. Erfinde keine Fakten (Zahlen, Siegel, Zertifikate, Kundennamen, Bewertungen, Rechtstexte); Platzhalter in [eckigen Klammern] nur mit Angaben der Website-Betreiber ersetzen.',
        ],
    ],

    // Schriften: Auswahl im Style-Editor (design.fonts); Vorladen übernimmt nature_font_preloads()
    'fonts' => [
        'icon' => 'fonts/Fraunces_600SemiBold.ttf',
    ],
    'app' => [
        'defaults' => ['icon_text' => 'N', 'icon_bg' => '#1F2B22', 'icon_fg' => '#F1EEE3', 'icon_dot' => '#9BC27A', 'icon_dot_enabled' => true],
        'info' => 'nature_app_info',
    ],
    'image_ratios' => $ratios,

    // Stylesheets und Skripte je Blocktyp (bzw. Typ:Variante) – nur auf Seiten, die sie brauchen
    // Kern-Fragmente (app/Views/fragments, Core\Fragments): Video-Zwei-Klick, Editor und Werkzeugleiste kommen nur aus dem Kern
    // (Skript resources/js/embed.js lädt das Fragment selbst); Marke, Sprachumschalter, Öffnungszeiten … ebenso, solange das Kit
    // keine eigene Datei in fragments/ bzw. templates/partials/ mitbringt.
    'fragments' => ['brand' => ['mark' => 'empty']],
    'conditional_css' => [
        'css/b-hero.css' => ['hero:panel', 'hero:statement'],
        'css/b-hero-season.css' => ['hero:season'],   // Einstieg „Jahreszeiten-Bühne“ (Panorama + Hofschild)
        'css/b-hero-dates.css' => ['hero:dates'],     // Einstieg „Termine am Ast“ (Anhänger)
        'css/b-hero-form.css' => ['hero:form'],       // Einstieg „Papierkarte mit Formular“
        'css/b-prose.css' => ['richtext', 'faq', 'media_text', 'video', '@rich'],   // @rich: Rich-Text-Stile in anderen Blöcken (Core\Sanitizer::styled)
        'css/b-media-text.css' => ['media_text'],
        'css/b-features.css' => ['features'],
        'css/b-principles.css' => ['principles'],
        'css/b-specs.css' => ['specs'],
        'css/b-stats.css' => ['stats'],
        'css/b-steps.css' => ['steps'],
        'css/b-cards.css' => ['cards'],
        'css/b-quote.css' => ['quote'],
        'css/b-faq.css' => ['faq'],
        'css/b-cta.css' => ['cta'],
        'css/b-contact.css' => ['contact', 'map'],
        'css/b-team.css' => ['team'],
        'css/b-downloads.css' => ['downloads'],
        'css/b-video.css' => ['video'],
        'css/dataform.css' => ['contact'],
    ],

    // ------------------------------------------------------------ Design (Style-Editor: Verwaltung → Design)
    'design' => require __DIR__ . '/design.php',

    // Kopfbereich-Aktionen (header_actions()): Button-Klassen des Kits für die Stile „Gefüllt“, „Kontur“ und „Geteilt“
    'header_actions' => ['late' => true, 'kit_css_late' => 'css/header-actions-late.css', 'kit_css' => 'css/header-actions-kit.css', 'classes' => ['solid' => 'btn btn--primary btn--small', 'outline' => 'btn btn--secondary btn--small', 'split' => 'btn btn--primary btn--small']],

    // ------------------------------------------------------------ Website
    'settings' => [
        'title' => 'Website',
        'groups' => [
            ['id' => 'stammdaten', 'label' => 'Stammdaten', 'fields' => [
                ['name' => 'org_name', 'label' => 'Name (Unternehmen, Verein, Büro …)', 'type' => 'text', 'required' => true,
                    'help' => 'Vollständiger Name, z. B. für Karte, Fußbereich und Suchmaschinen.'],
                ['name' => 'short_name', 'label' => 'Kurzname / Wortmarke', 'type' => 'text', 'max' => 32, 'width' => 'half',
                    'help' => 'Erscheint neben der Marke, solange kein Logo hinterlegt ist. Leer = Name.'],
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
                ['type' => 'heading', 'label' => 'Saison (optional)', 'help' => 'Für Betriebe mit Jahreszeiten: Hofladen im Winter, Führungen nur im Sommer, Erntezeiten …'],
                ['name' => 'seasons', 'label' => 'Saisonzeiten', 'type' => 'repeater', 'item_label' => 'Saison', 'title_field' => 'name', 'max_items' => 8,
                    'help' => 'Erscheinen im Kontakt-Block und im Fußbereich unter den Öffnungszeiten.',
                    'fields' => [
                        ['name' => 'name', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'max' => 60, 'width' => 'half', 'placeholder' => 'z. B. Hofführungen'],
                        ['name' => 'period', 'label' => 'Zeitraum', 'type' => 'text', 'max' => 60, 'width' => 'half', 'placeholder' => 'z. B. April bis Oktober'],
                        ['name' => 'text', 'label' => 'Zeiten / Hinweis', 'type' => 'text', 'max' => 140, 'placeholder' => 'z. B. samstags 11 und 15 Uhr'],
                    ]],
                ['name' => 'directions', 'label' => 'Anfahrt (optional)', 'type' => 'textarea', 'rows' => 3, 'max' => 400,
                    'help' => 'Z. B. Bus, Rad, Parken. Erscheint im Kontakt-Block unter der Adresse.'],
                ['type' => 'heading', 'label' => 'Karte'],
                ['name' => 'geo', 'label' => 'Standort auf der Karte', 'type' => 'geo', 'address_fields' => ['street', 'zip', 'city'], 'translate' => false,
                    'help' => 'Aus der Adresse ermitteln oder in die Karte klicken. Leer = keine Karte. Die Karte lädt über den eigenen Server – ohne Einwilligung, ohne Cookies.'],
            ]],
            ['id' => 'darstellung', 'label' => 'Darstellung', 'fields' => [
                ['name' => 'logo', 'label' => 'Logo (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'SVG oder PNG mit transparentem Hintergrund, ca. 40 px hoch dargestellt.'],
                ['name' => 'logo_dark', 'label' => 'Logo für dunkles Farbschema (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'Helle Fassung des Logos. Leer = normales Logo.'],
                ['name' => 'footer_statement', 'label' => 'Satz im Fußbereich „Mit Band“ (optional)', 'type' => 'text', 'max' => 90,
                    'help' => 'Z. B. „Kommen Sie vorbei.“ – leer = Kurzname. Der Button im Kopfbereich erscheint daneben.'],
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
                ['name' => 'schema_type', 'label' => 'Art der Organisation (strukturierte Daten)', 'type' => 'select', 'required' => true, 'default' => 'Organization', 'translate' => false,
                    'options' => ['Organization' => 'Organisation allgemein', 'LocalBusiness' => 'Lokales Geschäft / Betrieb', 'ProfessionalService' => 'Dienstleister / Büro / Kanzlei',
                        'NGO' => 'Verein / gemeinnützige Organisation', 'Restaurant' => 'Restaurant / Gastronomie', 'Museum' => 'Museum / Kultureinrichtung',
                        'Store' => 'Laden / Hofladen', 'TouristAttraction' => 'Ausflugsziel', 'Park' => 'Park / Naturgebiet', 'Campground' => 'Campingplatz']],
            ]],
        ],
    ],

    // ------------------------------------------------------------ Blöcke
    'blocks' => [
        'hero' => [
            'label' => 'Einstieg (Hero)', 'icon' => 'star', 'group' => 'Kopf',
            'help' => 'Große Überschrift der Seite (H1). Pro Seite genau einmal verwenden – „Seitenkopf“ für Unterseiten. Jahreszeiten-Bühne, Termine und Papierkarte bringen Öffnungszeiten, die nächsten Termine oder ein Formular direkt nach oben.',
            'variants' => [
                'panel' => 'Mit Bildtafel (Bild oder Landschafts-Illustration, Eckdaten)',
                'statement' => 'Große Aussage (typografisch)',
                'split' => 'Text und Bild nebeneinander',
                'compact' => 'Seitenkopf (ohne Bild)',
                'season' => 'Jahreszeiten-Bühne (Panorama, „Jetzt geöffnet“, Öffnungszeiten)',
                'dates' => 'Termine am Ast (die nächsten Termine als Anhänger)',
                'form' => 'Papierkarte mit Formular (Anfrage, Buchung)',
            ],
            // Wann welche Variante? (Handbuch → Alle Blöcke → Einstieg)
            'variant_help' => [
                'panel' => 'Startseite mit einem Bild oder – ohne Bild – der gezeichneten Landschaft; Eckdaten darunter.',
                'statement' => 'Eine starke Aussage in großer Schrift, optional mit Breitbild darunter.',
                'split' => 'Klassische Startseite oder Themenseite mit einem aussagekräftigen Bild.',
                'compact' => 'Unterseiten: Seitentitel mit kurzer Einleitung.',
                'season' => 'Hofladen, Café, Gärtnerei: Wer vorbeikommen will, sieht sofort, ob geöffnet ist – vor einem Panorama der aktuellen Jahreszeit (ohne Foto).',
                'dates' => 'Markttage, Führungen, Kurse: Die nächsten Termine aus einer Datentabelle hängen gleich im Einstieg.',
                'form' => 'Ein Ziel steht im Vordergrund – Gruppenanfrage, Buchung, Rückruf: Das Formular ist sofort sichtbar.',
            ],
            // Formular-Stile des Kits (css/dataform.css) auch auf Seiten mit „Papierkarte“
            'uses' => ['form' => ['data_form']],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift (H1)', 'type' => 'text', 'required' => true, 'max' => 110,
                    'help' => 'Ein Wort in *Sternchen* erscheint kursiv in der Akzentfarbe.'],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 360],
                ...$buttons,
                ['name' => 'points', 'label' => 'Kennwerte / Pluspunkte (eine pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3,
                    'variants' => ['panel', 'statement', 'split', 'form'],
                    'help' => '„Mit Bildtafel“: erscheinen unter dem Bild; „Große Aussage“: als Liste neben dem Text; „Papierkarte“: mit Blattmarke unter dem Text. Tipp: „Bezeichnung: Wert“ erzeugt eine zweispaltige Zeile.'],
                ['name' => 'panel_label', 'label' => 'Beschriftung der Bildtafel (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'variants' => ['panel'],
                    'help' => 'Kleine Aufschrift über dem Bild, z. B. „Hof seit 1911“ oder „Saison 2026“.'],
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half', 'variants' => ['panel', 'statement', 'split'],
                    'help' => 'Optional. Ohne Bild zeigt die Tafel eine Landschaft im Stil der gewählten Jahreszeit (keine Datei nötig).'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:5', 'width' => 'half', 'options' => $ratios,
                    'variants' => ['panel', 'split'], 'help' => 'Bei „Große Aussage“ immer 21:9.'],
                // Jahreszeiten-Bühne
                ['name' => 'season_scene', 'label' => 'Jahreszeit des Panoramas', 'type' => 'select', 'required' => true, 'default' => 'auto', 'width' => 'half', 'variants' => ['season'],
                    'options' => ['auto' => 'Automatisch nach Monat', 'fruehling' => 'Frühling (Blüten, Blumenwiese)', 'sommer' => 'Sommer (Sonne, Kornfeld, Heuballen)',
                        'herbst' => 'Herbst (buntes Laub, Kürbisse)', 'winter' => 'Winter (Schnee, kahle Bäume)'],
                    'help' => '„Automatisch“ wechselt mit dem Monat (März–Mai Frühling …) – wirksam nach dem nächsten Leeren des Seiten-Caches.'],
                ['name' => 'sign_label', 'label' => 'Aufschrift des Hofschilds (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'variants' => ['season'],
                    'placeholder' => 'z. B. Hofladen', 'help' => 'Leer = Kurzname der Website.'],
                ['name' => 'sign_hours', 'label' => 'Hofschild mit „Jetzt geöffnet“ und Öffnungszeiten zeigen', 'type' => 'bool', 'default' => true, 'variants' => ['season'],
                    'help' => 'Öffnungszeiten kommen aus „Website“. Ob gerade geöffnet ist, rechnet der Browser der Besucher aus – auch aus dem Seiten-Cache immer aktuell.'],
                // Termine am Ast, Papierkarte (Kern-Voreinstellungen, nur bei der passenden Variante sichtbar)
                ...\Core\Blocks\Hero::fields('dates', ['dates']),
                ...\Core\Blocks\Hero::fields('form', ['form']),
            ],
        ],
        'richtext' => [
            'label' => 'Fließtext / Artikel', 'icon' => 'paragraph', 'group' => 'Inhalt',
            'help' => 'Freier Text mit Zwischenüberschriften, Listen, Zitaten und Links. „Artikel“: Lesebreite mit automatischem Inhaltsverzeichnis.',
            'variants' => ['standard' => 'Standard', 'article' => 'Artikel mit Inhaltsverzeichnis'],
            'fields' => [
                ...$head(false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'meta', 'label' => 'Angaben über dem Text (optional, bei „Artikel“)', 'type' => 'text', 'max' => 120, 'placeholder' => 'z. B. Redaktion · 12. März 2026'],
                ['name' => 'toc', 'label' => 'Inhaltsverzeichnis anzeigen (bei „Artikel“)', 'type' => 'bool', 'default' => true],
            ],
        ],
        'media_text' => [
            'label' => 'Text + Bild', 'icon' => 'square-half', 'group' => 'Inhalt',
            'help' => '„Abwechselnd“: mehrere Blöcke nacheinander wechseln automatisch die Seite.',
            'variants' => ['auto' => 'Abwechselnd (automatisch)', 'right' => 'Bild rechts', 'left' => 'Bild links'],
            'fields' => [
                ...$head(true, false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'list', 'label' => 'Aufzählung (ein Punkt pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3],
                ...$buttons,
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half', 'help' => 'Min. 1200 px breit.'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:3', 'width' => 'half', 'options' => $ratios],
            ],
        ],
        'features' => [
            'label' => 'Merkmale / Angebote', 'icon' => 'squares-four', 'group' => 'Inhalt',
            'help' => 'Leistungen oder Vorteile mit Symbol. „Beet“: alle Einträge auf einer gemeinsamen Fläche, getrennt durch feine Linien.',
            'variants' => ['panel' => 'Beet (eine Fläche)', 'cards' => 'Einzelne Karten', 'list' => 'Liste mit Symbol'],
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
        'principles' => [
            'label' => 'Grundsätze / Versprechen', 'icon' => 'list-checks', 'group' => 'Inhalt',
            'help' => 'Nummerierte Grundsätze, Werte oder Versprechen (z. B. „So wirtschaften wir“) – als ruhige Liste, als Raster oder aufklappbar.',
            'variants' => ['list' => 'Liste (Nummer, Titel, Text)', 'grid' => 'Raster aus Karten', 'accordion' => 'Aufklappbar (kompakt)'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Grundsätze', 'type' => 'repeater', 'item_label' => 'Grundsatz', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'title', 'label' => 'Grundsatz', 'type' => 'text', 'required' => true, 'max' => 90],
                    ['name' => 'text', 'label' => 'Erläuterung', 'type' => 'textarea', 'rows' => 3, 'max' => 480],
                ]],
            ],
        ],
        'specs' => [
            'label' => 'Steckbrief (Eckdaten)', 'icon' => 'table', 'group' => 'Inhalt',
            'help' => 'Eckdaten als ruhige Tabelle – für Hof, Flächen, Produkte, Kurse oder Konditionen. Optional mit Bild daneben.',
            'variants' => ['table' => 'Tabelle', 'image' => 'Mit Bild daneben'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Kennwerte', 'type' => 'repeater', 'item_label' => 'Zeile', 'title_field' => 'label', 'max_items' => 30, 'fields' => [
                    ['name' => 'group', 'label' => 'Gruppe (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Flächen',
                        'help' => 'Zeilen mit gleicher Gruppe erscheinen unter einer Zwischenüberschrift.'],
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'max' => 60, 'width' => 'half'],
                    ['name' => 'value', 'label' => 'Wert', 'type' => 'text', 'required' => true, 'max' => 120],
                ]],
                ['name' => 'image', 'label' => 'Bild (bei „Mit Bild daneben“)', 'type' => 'media', 'width' => 'half'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:5', 'width' => 'half', 'options' => $ratios],
                ['name' => 'note', 'label' => 'Hinweis unter der Tabelle (optional)', 'type' => 'text', 'max' => 200, 'placeholder' => 'z. B. Stand: März 2026'],
            ],
        ],
        'stats' => [
            'label' => 'Kennzahlen', 'icon' => 'chart-bar', 'group' => 'Inhalt',
            'help' => 'Nur belegbare Zahlen verwenden. „Jahresringe“: ein Ring je Zahl; der Füllstand (0–100) ist optional.',
            'variants' => ['dials' => 'Jahresringe (Skalen)', 'row' => 'Große Zahlen in einer Reihe'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Kennzahlen', 'type' => 'repeater', 'item_label' => 'Kennzahl', 'title_field' => 'value', 'max_items' => 6, 'fields' => [
                    ['name' => 'value', 'label' => 'Wert', 'type' => 'text', 'required' => true, 'max' => 12, 'width' => 'half', 'placeholder' => 'z. B. 42 ha'],
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'max' => 50, 'width' => 'half'],
                    ['name' => 'level', 'label' => 'Füllstand der Skala in % (optional)', 'type' => 'number', 'width' => 'half', 'min' => 0, 'max' => 100,
                        'help' => 'Leer = nur Skalenstriche. Nur verwenden, wenn der Anteil eine echte Bedeutung hat (z. B. 92 % Weiterempfehlung).'],
                    ['name' => 'text', 'label' => 'Erläuterung (optional)', 'type' => 'text', 'max' => 140, 'width' => 'half'],
                ]],
            ],
        ],
        'steps' => [
            'label' => 'Ablauf / Zeitleiste', 'icon' => 'path', 'group' => 'Inhalt',
            'help' => 'Nummerierte Schritte auf einem Pfad oder eine senkrechte Zeitleiste mit Datum/Phase – z. B. Hofgeschichte oder Jahreslauf.',
            'variants' => ['steps' => 'Schritte (auf einem Pfad)', 'timeline' => 'Zeitleiste (untereinander)'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Schritte', 'type' => 'repeater', 'item_label' => 'Schritt', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'meta', 'label' => 'Phase / Datum (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. März'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 80, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 400],
                ]],
            ],
        ],
        'cards' => [
            'label' => 'Produkte / Angebote (Karten)', 'icon' => 'copy', 'group' => 'Inhalt',
            'help' => '„Produkt“: freigestelltes Bild auf ruhiger Fläche, darunter Preis und Pfeil. „Angebot“: ohne Bild, mit Symbol.',
            'variants' => ['product' => 'Produkt (Bild auf Fläche)', 'service' => 'Angebot (Symbol, ohne Bild)', 'image' => 'Bild randlos oben'],
            'fields' => [
                ...$head(),
                $size,
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:3', 'width' => 'half', 'options' => $ratios],
                ['name' => 'items', 'label' => 'Karten', 'type' => 'repeater', 'item_label' => 'Karte', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'icon', 'label' => 'Symbol (bei „Angebot“)', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. aus eigener Ernte'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 90, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
                    ['name' => 'meta', 'label' => 'Kennwert / Preis (optional)', 'type' => 'text', 'max' => 40, 'placeholder' => 'z. B. 4,20 € / kg'],
                    ...$link(),
                ]],
                ['name' => 'more_label', 'label' => 'Button unter den Karten (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'more_link', 'label' => 'Button-Link', 'type' => 'link', 'width' => 'half'],
            ],
        ],
        'team' => [
            'label' => 'Team / Menschen', 'icon' => 'users-three', 'group' => 'Inhalt',
            'help' => 'Menschen hinter dem Betrieb. Ohne Foto erscheint ein Monogramm in einer Kieselform. Nur mit Einverständnis der Personen veröffentlichen.',
            'variants' => ['grid' => 'Karten im Raster', 'list' => 'Liste (Bild links)'],
            'fields' => [
                ...$head(),
                $size,
                ['name' => 'items', 'label' => 'Personen', 'type' => 'repeater', 'item_label' => 'Person', 'title_field' => 'name', 'max_items' => 24, 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 80, 'width' => 'half'],
                    ['name' => 'role', 'label' => 'Aufgabe / Funktion', 'type' => 'text', 'max' => 80, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Kurztext (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 280],
                    ['name' => 'image', 'label' => 'Foto (optional, 1:1)', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'email', 'label' => 'E-Mail (optional)', 'type' => 'email', 'width' => 'half'],
                ]],
            ],
        ],
        'quote' => [
            'label' => 'Zitat / Stimmen', 'icon' => 'quotes', 'group' => 'Inhalt',
            'help' => 'Ein Zitat als ruhiger Blickfang oder mehrere Stimmen im Raster. Nur echte, freigegebene Stimmen verwenden.',
            'variants' => ['single' => 'Groß (ein Zitat)', 'grid' => 'Raster'],
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
        'faq' => [
            'label' => 'Fragen & Antworten', 'icon' => 'question', 'group' => 'Inhalt',
            'help' => 'Aufklappbare Fragen (ohne JavaScript). Suchmaschinen erhalten die Fragen als strukturierte Daten (FAQPage).',
            'variants' => ['split' => 'Überschrift links, Fragen rechts', 'stacked' => 'Untereinander'],
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
            'label' => 'Handlungsaufruf', 'icon' => 'megaphone', 'group' => 'Inhalt',
            'help' => '„Karte“: Text und Buttons auf einer Fläche mit Blattmotiv. „Band“ wirkt am stärksten mit dem Abschnitts-Hintergrund „Akzentfarbe“ oder „Waldnacht“.',
            'variants' => ['panel' => 'Karte (mit Blattmotiv)', 'band' => 'Band über die volle Breite', 'minimal' => 'Zurückhaltend (zentriert)'],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true, 'max' => 110],
                ['name' => 'text', 'label' => 'Text (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ...$buttons,
            ],
        ],
        'contact' => [
            'label' => 'Kontakt (Zeiten, Saison, Karte, Formular)', 'icon' => 'address-book', 'group' => 'Website',
            'central' => 'Adresse, Anfahrt, Telefon, E-Mail, Öffnungs- und Saisonzeiten und Karte kommen aus „Website“.',
            'help' => 'Kontaktangaben mit Anfahrt, Öffnungszeiten (mit Anzeige „Jetzt geöffnet“), Saisonzeiten, Karte und optional ein öffentliches Formular.',
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
        'video' => [
            'label' => 'Video', 'icon' => 'play-circle', 'group' => 'Medien',
            'help' => 'YouTube/Vimeo mit Zwei-Klick-Lösung (Vorschaubild vom eigenen Server, ohne Cookies bis zum Klick) oder eigene MP4-Datei mit Untertiteln und Transkript aus der Mediathek.',
            'variants' => ['wide' => 'Breit', 'text' => 'Mit Text daneben'],
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
        'map' => [
            'label' => 'Karte', 'icon' => 'map-trifold', 'group' => 'Website',
            'help' => 'Interaktive Karte (OpenStreetMap-Daten über den eigenen Server – ohne Einwilligung, ohne Cookies).',
            'fields' => [...$head(false, false), ...\Core\Maps::blockFields()],
        ],
    ],
];
