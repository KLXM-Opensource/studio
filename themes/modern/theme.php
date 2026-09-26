<?php
/*
 * Kit „modern“ – zeitgemäß, selbstbewusst, klar. Für KLXM Studio.
 *
 * Große Grotesk-Typografie (Space Grotesk + Plus Jakarta Sans, lokal), viel Weißraum, kräftige Farbflächen, Bento-Raster,
 * präzise Karten mit feiner Kante, Bedienelemente mit 8 px Radius (keine Pillen), Akzentsystem mit geprüftem Kontrast.
 * Responsiv in klaren Stufen (Media-Queries bei 40 em, 48 em und 64 em) – bewusst nicht breakpointlos wie „fluid“.
 *
 *  - blocks     Blocktypen (= Editor.js-Tools) mit Feld-Schema und Varianten – Feldnamen wie in „fluid“/„essenz“,
 *               damit Websites das Kit wechseln können; dazu bento, team, pricing („Angebote“), logos, tabs
 *  - settings   zentrales Einstellungsformular („Website“) – identische Schlüssel wie „fluid“/„essenz“
 *  - design     design.php (Style-Editor: Farben, Schriften, Formen, vier Navigationen, vier Vorlagen)
 * Eigenes Kundenprojekt: php bin/console kit:create kunde modern  (Präfix modern_* → kunde_*)
 */

require_once __DIR__ . '/functions.php';

// Wiederkehrende Felder: Dachzeile, Überschrift, Einleitung
$head = fn(bool $required = false, bool $intro = true) => array_merge([
    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
    ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'max' => 110, 'width' => 'half', 'required' => $required,
        'help' => 'Ein Wort in *Sternchen* wird hervorgehoben.'],
], $intro ? [['name' => 'intro', 'label' => 'Einleitung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 400]] : []);

$buttons = [
    ['name' => 'button_label', 'label' => 'Button 1 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button_link', 'label' => 'Button 1 – Link', 'type' => 'link', 'width' => 'half',
        'help' => 'Seite, #anker, https://… – Sonderwerte „phone“ und „email“ nutzen „Website“.'],
    ['name' => 'button2_label', 'label' => 'Button 2 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button2_link', 'label' => 'Button 2 – Link', 'type' => 'link', 'width' => 'half'],
];

$ratios = ['16:9' => 'Breitbild 16:9', '3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', '1:1' => 'Quadrat 1:1', '4:5' => 'Hochformat 4:5', '3:4' => 'Hochformat 3:4'];
// Statt fester Spaltenzahl: Mindestbreite je Eintrag – das Raster füllt die Zeile (auto-fit, minmax)
$size = ['name' => 'size', 'label' => 'Breite je Eintrag (mindestens)', 'type' => 'select', 'required' => true, 'default' => 'm', 'width' => 'half',
    'options' => ['s' => 'Schmal (viele nebeneinander)', 'm' => 'Mittel', 'l' => 'Breit (wenige nebeneinander)'],
    'help' => 'Wie viele Einträge nebeneinander passen, ergibt sich aus dem verfügbaren Platz.'];
$days = ['1' => 'Montag', '2' => 'Dienstag', '3' => 'Mittwoch', '4' => 'Donnerstag', '5' => 'Freitag', '6' => 'Samstag', '0' => 'Sonntag'];
$link = fn(string $prefix = 'link', string $label = 'Link') => [
    ['name' => $prefix . '_label', 'label' => $label . ' – Beschriftung (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => $prefix, 'label' => $label, 'type' => 'link', 'width' => 'half'],
];

return [
    'label' => 'Modern – klar und selbstbewusst',
    'description' => 'Zeitgemäßes Kit für Studios, Beratungen und Unternehmen: große Grotesk-Typografie, viel Weißraum, kräftige Farbflächen, Bento-Raster und präzise Karten. Vier Navigationen, vier Farbvorlagen, hell und dunkel, WCAG 2.2 AA.',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',
    'source_lang' => 'de',

    'backgrounds' => ['white' => 'Standard', 'muted' => 'Getönt', 'tint' => 'Akzent hell', 'pop' => 'Blockfarbe', 'accent' => 'Akzentfarbe', 'dark' => 'Dunkel'],
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
    'jsonld' => 'modern_jsonld',

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
        'dashboard_hint' => 'Name, Adresse, Telefon, E-Mail, Öffnungszeiten und Logo werden zentral gepflegt und erscheinen automatisch überall auf der Website. Farben, Schriften, Navigation und Fußbereich: Verwaltung → Design.',
        'public_info' => 'modern_public_info',
        'hours' => ['setting' => 'hours', 'format' => 'modern_time_range', 'label' => 'Öffnungszeiten'],
        'notice' => ['text' => 'notice_text', 'active' => 'notice_active', 'example' => 'z. B. Betriebsferien, geänderte Öffnungszeiten'],
        'link_labels' => ['phone' => 'Telefon (aus „Website“)', 'email' => 'E-Mail (aus „Website“)'],
        'search_keywords' => 'kontakt adresse telefon e-mail öffnungszeiten logo farbe',
        'mcp' => [
            'instructions' => 'Das Kit ist zeitgemäß und klar: kurze, selbstbewusste Sätze mit aktiven Verben, keine Floskeln. Große Überschriften vertragen wenige Wörter (max. 8). Erfinde keine Fakten (Zahlen, Referenzen, Kundennamen, Bewertungen, Rechtstexte); Platzhalter in [eckigen Klammern] nur mit Angaben der Website-Betreiber ersetzen.',
        ],
    ],

    // Schriften: Auswahl im Style-Editor (design.fonts); Vorladen übernimmt modern_font_preloads()
    'fonts' => [
        'icon' => 'fonts/SpaceGrotesk_700Bold.ttf',
    ],
    'app' => [
        'defaults' => ['icon_text' => 'M', 'icon_bg' => '#0A0D12', 'icon_fg' => '#FFFFFF', 'icon_dot' => '#C9F25E', 'icon_dot_enabled' => true],
        'info' => 'modern_app_info',
    ],
    'image_ratios' => $ratios,

    // Stylesheets und Skripte je Blocktyp (bzw. Typ:Variante) – nur auf Seiten, die sie brauchen
    'conditional_css' => [
        'css/hero-x.css' => ['hero:statement', 'hero:mosaic'],
        'css/hero-product.css' => ['hero:product'],     // Produktbühne mit Datenblatt
        'css/hero-marquee.css' => ['hero:marquee'],     // Typo-Einstieg mit Laufzeile
        'css/hero-figures.css' => ['hero:figures'],     // Kennzahlen-Kacheln (Kern-Stylesheet dials über 'uses')
        'css/prose.css' => ['richtext', 'tabs', 'faq', 'media_text', 'video', 'data_fields', '@rich'],   // @rich: Rich-Text-Stile in anderen Blöcken (Core\Sanitizer::styled)
        'css/b-article.css' => ['richtext:article', 'richtext:columns'],
        'css/b-features.css' => ['features'],
        'css/b-media-text.css' => ['media_text'],
        'css/b-cta.css' => ['cta'],
        'css/b-bento.css' => ['bento'],
        'css/b-cards.css' => ['cards'],
        'css/b-logos.css' => ['logos'],
        'css/b-stats.css' => ['stats'],
        'css/b-quote.css' => ['quote'],
        'css/b-steps.css' => ['steps'],
        'css/b-pricing.css' => ['pricing'],
        'css/b-team.css' => ['team'],
        'css/b-faq.css' => ['faq'],
        'css/b-tabs.css' => ['tabs'],
        'css/b-contact.css' => ['contact', 'map'],
        'css/b-video.css' => ['video'],
        'css/b-downloads.css' => ['downloads'],
        'css/dataform.css' => ['contact'],
        'js/tabs.js' => ['tabs'],
        'js/video.js' => ['video'],
    ],

    // ------------------------------------------------------------ Design (Style-Editor: Verwaltung → Design)
    'design' => require __DIR__ . '/design.php',

    // Kopfbereich-Aktionen (header_actions()): Button-Klassen des Kits für die Stile „Gefüllt“, „Kontur“ und „Geteilt“
    'header_actions' => ['late' => true, 'classes' => ['solid' => 'btn btn--primary btn--small', 'outline' => 'btn btn--secondary btn--small', 'split' => 'btn btn--primary btn--small']],

    // ------------------------------------------------------------ Website
    'settings' => [
        'title' => 'Website',
        'groups' => [
            ['id' => 'stammdaten', 'label' => 'Stammdaten', 'fields' => [
                ['name' => 'org_name', 'label' => 'Name (Unternehmen, Verein, Büro …)', 'type' => 'text', 'required' => true,
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
                ['name' => 'hours_note', 'label' => 'Hinweis unter den Öffnungszeiten', 'type' => 'text'],
                ['type' => 'heading', 'label' => 'Karte'],
                ['name' => 'geo', 'label' => 'Standort auf der Karte', 'type' => 'geo', 'address_fields' => ['street', 'zip', 'city'], 'translate' => false,
                    'help' => 'Aus der Adresse ermitteln oder in die Karte klicken. Leer = keine Karte. Die Karte lädt über den eigenen Server – ohne Einwilligung, ohne Cookies.'],
            ]],
            ['id' => 'darstellung', 'label' => 'Darstellung', 'fields' => [
                ['name' => 'logo', 'label' => 'Logo (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'SVG oder PNG mit transparentem Hintergrund, ca. 40 px hoch dargestellt.'],
                ['name' => 'logo_dark', 'label' => 'Logo für dunkles Farbschema (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'Helle Fassung des Logos. Leer = normales Logo.'],
                ['name' => 'footer_statement', 'label' => 'Satz im Fußbereich „Großer Schriftzug“ (optional)', 'type' => 'text', 'max' => 90,
                    'help' => 'Z. B. „Lassen Sie uns reden.“ – leer = Kurzname. Der Button im Kopfbereich erscheint daneben.'],
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
                        'NGO' => 'Verein / gemeinnützige Organisation', 'Restaurant' => 'Restaurant / Gastronomie', 'Museum' => 'Museum / Kultureinrichtung']],
            ]],
        ],
    ],

    // ------------------------------------------------------------ Blöcke
    'blocks' => [
        'hero' => [
            'label' => 'Einstieg (Hero)', 'icon' => 'star', 'group' => 'Kopf',
            'help' => 'Große Überschrift der Seite (H1). Pro Seite genau einmal verwenden – „Seitenkopf“ für Unterseiten.',
            'variants' => [
                'split' => 'Geteilt – Text und Bild auf Farbfläche',
                'statement' => 'Große Aussage – Schriftzug über die volle Breite',
                'mosaic' => 'Mosaik – Text mit Bildraster',
                'compact' => 'Seitenkopf (ohne Bild)',
                'product' => 'Produkt – Bildbühne mit Datenblatt',
                'marquee' => 'Typo – Riesenschrift mit Laufzeile',
                'figures' => 'Kennzahlen – Aussage mit Zahlen-Kacheln',
            ],
            // Wann welche Variante? (Handbuch → Alle Blöcke → Einstieg)
            'variant_help' => [
                'split' => 'Startseite mit einem starken Bild und optional einer belegbaren Kennzahl.',
                'statement' => 'Eine kurze, laute Botschaft zählt mehr als das Bild – Kampagne, Haltung, Neustart.',
                'mosaic' => 'Mehrere Arbeiten oder Stimmungen auf einen Blick – Portfolio, Studio, Agentur.',
                'compact' => 'Unterseiten: Seitentitel mit kurzer Einleitung.',
                'product' => 'Ein Produkt steht im Mittelpunkt: Launch, Produktseite, Vorbestellung – Bilder, Preis und technische Angaben auf einen Blick.',
                'marquee' => 'Ohne gutes Bild, aber mit klarer Haltung: Agentur, Kampagne, Event – Riesenschrift und eine ruhig laufende Zeile mit Stichworten.',
                'figures' => 'Die Zahlen sind das Argument: Jahresbericht, Wirkung, Förderung, Bilanz – 3–4 belegbare Kennzahlen als farbige Kacheln.',
            ],
            // Kern-Stylesheet der Rundinstrumente nur mit „Kennzahlen“
            'uses' => ['figures' => ['dials']],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift (H1)', 'type' => 'text', 'required' => true, 'max' => 110,
                    'help' => 'Kurz und kräftig (max. 8 Wörter). Ein Wort in *Sternchen* wird hervorgehoben.'],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 360],
                ...$buttons,
                ['name' => 'points', 'label' => 'Kurze Pluspunkte (eine pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3,
                    'help' => 'Erscheinen als Häkchen unter den Buttons.'],
                ['name' => 'stat_value', 'label' => 'Kennzahl auf dem Bild (optional)', 'type' => 'text', 'max' => 12, 'width' => 'half', 'placeholder' => 'z. B. 140+',
                    'help' => '„Geteilt“ und „Mosaik“: kleine Karte über dem Bild. Nur belegbare Zahlen.'],
                ['name' => 'stat_label', 'label' => 'Bezeichnung der Kennzahl', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Projekte seit 2016'],
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half', 'help' => 'Min. 1600 px breit. Alt-Text in der Mediathek pflegen.',
                    'variants' => ['split', 'statement', 'mosaic', 'product']],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:5', 'width' => 'half', 'options' => $ratios,
                    'help' => '„Große Aussage“: immer 21:9 unter dem Schriftzug. „Mosaik“: ergibt sich aus dem Raster.'],
                ['name' => 'image2', 'label' => 'Mosaik – Bild 2', 'type' => 'media', 'width' => 'half', 'variants' => ['mosaic', 'product'],
                    'help' => '„Produkt“: erstes Detailbild (quadratisch neben dem Datenblatt).'],
                ['name' => 'image3', 'label' => 'Mosaik – Bild 3', 'type' => 'media', 'width' => 'half', 'variants' => ['mosaic', 'product'],
                    'help' => '„Produkt“: zweites Detailbild.'],
                // „Produkt“: Datenblatt-Karte, Preis-Sticker, Kennzeichnung
                ['name' => 'spec_title', 'label' => 'Überschrift des Datenblatts', 'type' => 'text', 'max' => 60, 'width' => 'half', 'variants' => ['product'],
                    'placeholder' => 'z. B. Leuchte „Polar“', 'help' => 'Leer = „Datenblatt“.'],
                ['name' => 'badge', 'label' => 'Kennzeichnung auf dem Bild (optional)', 'type' => 'text', 'max' => 24, 'width' => 'half', 'variants' => ['product'],
                    'placeholder' => 'z. B. Neu, Prototyp'],
                ['name' => 'specs', 'label' => 'Technische Angaben (eine pro Zeile)', 'type' => 'textarea', 'rows' => 6, 'variants' => ['product'],
                    'placeholder' => "Material: Aluminium, recycelt\nGewicht: 1,2 kg", 'help' => '„Bezeichnung: Wert“ je Zeile, z. B. „Leistung: 8 W“. 4–8 Zeilen wirken am besten.'],
                ['name' => 'price', 'label' => 'Preis oder Etikett (optional)', 'type' => 'text', 'max' => 20, 'width' => 'half', 'variants' => ['product'],
                    'placeholder' => 'z. B. 249 € oder Bald', 'help' => 'Runder Sticker auf dem Bild. Nur verbindliche, aktuelle Preise.'],
                ['name' => 'price_note', 'label' => 'Zusatz zum Preis', 'type' => 'text', 'max' => 30, 'width' => 'half', 'variants' => ['product'],
                    'placeholder' => 'z. B. inkl. MwSt.'],
                ...\Core\Blocks\Hero::fields('marquee', ['marquee']),
                ...\Core\Blocks\Hero::fields('figures', ['figures']),
            ],
        ],
        'richtext' => [
            'label' => 'Fließtext / Artikel', 'icon' => 'paragraph', 'group' => 'Inhalt',
            'help' => 'Freier Text mit Zwischenüberschriften, Listen, Zitaten und Links. „Artikel“: angenehme Lesebreite mit automatischem Inhaltsverzeichnis.',
            'variants' => ['standard' => 'Standard', 'article' => 'Artikel mit Inhaltsverzeichnis', 'columns' => 'Mehrspaltig'],
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
            'help' => '„Abwechselnd“: mehrere Blöcke nacheinander wechseln automatisch die Seite. „Geteilt“: Bild und Text je halb, randlos.',
            'variants' => ['auto' => 'Abwechselnd (automatisch)', 'right' => 'Bild rechts', 'left' => 'Bild links', 'split' => 'Geteilt, randlos', 'overlap' => 'Text-Karte über großem Bild'],
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
            'help' => 'Leistungen, Vorteile oder Angebote mit Symbol oder Bild.',
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
        'bento' => [
            'label' => 'Bento-Raster', 'icon' => 'squares-four', 'group' => 'Inhalt',
            'help' => 'Kacheln unterschiedlicher Größe in einem dichten Raster (12 Spalten) – für Leistungen, Zahlen und Bilder. Auf dem Handy stehen die Kacheln untereinander.',
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Kacheln', 'type' => 'repeater', 'item_label' => 'Kachel', 'title_field' => 'title', 'max_items' => 10, 'fields' => [
                    ['name' => 'size', 'label' => 'Größe', 'type' => 'select', 'required' => true, 'default' => 's', 'width' => 'half',
                        'options' => ['s' => 'Normal (ein Drittel)', 'wide' => 'Breit (zwei Drittel)', 'tall' => 'Hoch', 'big' => 'Groß (zwei Drittel, hoch)', 'full' => 'Ganze Breite']],
                    ['name' => 'tone', 'label' => 'Fläche', 'type' => 'select', 'required' => true, 'default' => 'card', 'width' => 'half',
                        'options' => ['card' => 'Karte', 'tint' => 'Akzent hell', 'pop' => 'Blockfarbe', 'accent' => 'Akzentfarbe', 'dark' => 'Dunkel', 'image' => 'Bild mit Text darauf']],
                    ['name' => 'icon', 'label' => 'Symbol (optional)', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'Bild (optional)', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'eyebrow', 'label' => 'Dachzeile oder große Zahl (optional)', 'type' => 'text', 'max' => 30, 'width' => 'half',
                        'help' => 'Eine kurze Zahl (z. B. „140+“ oder „98 %“) erscheint groß.'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 80, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
                    ['name' => 'link', 'label' => 'Link (ganze Kachel, optional)', 'type' => 'link'],
                ]],
            ],
        ],
        'cards' => [
            'label' => 'Karten', 'icon' => 'copy', 'group' => 'Inhalt',
            'help' => 'Teaser mit Bild – klassisch, Text auf dem Bild oder quer. Ohne Linktext ist die ganze Karte klickbar.',
            'variants' => ['image' => 'Bild oben', 'overlay' => 'Text auf dem Bild', 'horizontal' => 'Quer (Bild links)'],
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
        'stats' => [
            'label' => 'Kennzahlen', 'icon' => 'chart-bar', 'group' => 'Inhalt',
            'help' => 'Nur belegbare Zahlen verwenden. Rundinstrumente mit Skala: Kern-Block „Kennzahlen mit Skala“.',
            'variants' => ['row' => 'Große Zahlen in einer Reihe', 'cards' => 'Karten', 'split' => 'Überschrift links, Zahlen rechts'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Kennzahlen', 'type' => 'repeater', 'item_label' => 'Kennzahl', 'title_field' => 'value', 'max_items' => 6, 'fields' => [
                    ['name' => 'value', 'label' => 'Wert', 'type' => 'text', 'required' => true, 'max' => 12, 'width' => 'half', 'placeholder' => 'z. B. 25+'],
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'max' => 50, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Erläuterung (optional)', 'type' => 'text', 'max' => 140],
                ]],
            ],
        ],
        'quote' => [
            'label' => 'Zitat / Stimmen', 'icon' => 'quotes', 'group' => 'Inhalt',
            'help' => '„Groß“: ein Zitat als Blickfang. „Raster“ für mehrere Stimmen. Nur echte, freigegebene Stimmen verwenden.',
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
        'steps' => [
            'label' => 'Ablauf / Zeitleiste', 'icon' => 'path', 'group' => 'Inhalt',
            'help' => 'Nummerierte Schritte, eine Zeitleiste mit Datum/Phase oder ein Prozess mit Pfeilen.',
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
        'team' => [
            'label' => 'Team', 'icon' => 'users-three', 'group' => 'Inhalt',
            'help' => 'Personen mit Porträt, Funktion und Kontakt. Ohne Foto erscheinen die Initialen auf einer Farbfläche. Nur mit Einverständnis der Personen veröffentlichen.',
            'variants' => ['grid' => 'Raster mit Porträts', 'list' => 'Liste (kompakt)'],
            'fields' => [
                ...$head(),
                $size,
                ['name' => 'items', 'label' => 'Personen', 'type' => 'repeater', 'item_label' => 'Person', 'title_field' => 'name', 'max_items' => 24, 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 60, 'width' => 'half'],
                    ['name' => 'role', 'label' => 'Funktion', 'type' => 'text', 'max' => 60, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Kurzvorstellung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 220],
                    ['name' => 'image', 'label' => 'Porträt (optional, 4:5)', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'email', 'label' => 'E-Mail (optional)', 'type' => 'email', 'width' => 'half'],
                    ...$link('link', 'Profil-Link'),
                ]],
            ],
        ],
        'pricing' => [
            'label' => 'Angebote / Pakete', 'icon' => 'currency-eur', 'group' => 'Inhalt',
            'help' => 'Angebote als Karten oder als Vergleichstabelle. Ein Angebot lässt sich hervorheben. Nur verbindliche, aktuelle Preise angeben.',
            'variants' => ['cards' => 'Karten', 'table' => 'Vergleichstabelle'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Angebote', 'type' => 'repeater', 'item_label' => 'Angebot', 'title_field' => 'name', 'max_items' => 4, 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 40, 'width' => 'half'],
                    ['name' => 'badge', 'label' => 'Etikett (optional)', 'type' => 'text', 'max' => 24, 'width' => 'half', 'placeholder' => 'z. B. Empfohlen'],
                    ['name' => 'price', 'label' => 'Preis', 'type' => 'text', 'max' => 24, 'width' => 'half', 'placeholder' => 'z. B. ab 490 €'],
                    ['name' => 'period', 'label' => 'Zeitraum / Zusatz', 'type' => 'text', 'max' => 32, 'width' => 'half', 'placeholder' => 'z. B. pro Monat'],
                    ['name' => 'text', 'label' => 'Kurzbeschreibung', 'type' => 'textarea', 'rows' => 2, 'max' => 220],
                    ['name' => 'features', 'label' => 'Leistungen (eine pro Zeile, bei „Karten“)', 'type' => 'textarea', 'rows' => 4],
                    ['name' => 'highlight', 'label' => 'Hervorheben', 'type' => 'bool', 'default' => false],
                    ['name' => 'button_label', 'label' => 'Button – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                    ['name' => 'button_link', 'label' => 'Button – Link', 'type' => 'link', 'width' => 'half'],
                ]],
                ['name' => 'rows', 'label' => 'Vergleichszeilen (bei „Vergleichstabelle“)', 'type' => 'repeater', 'item_label' => 'Zeile', 'title_field' => 'label', 'max_items' => 20, 'fields' => [
                    ['name' => 'label', 'label' => 'Merkmal', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'values', 'label' => 'Werte je Angebot (eine Zeile pro Angebot, in Reihenfolge)', 'type' => 'textarea', 'rows' => 3,
                        'help' => '„ja“ oder „✓“ = enthalten, „nein“ oder „–“ = nicht enthalten, sonst freier Text (z. B. „3 Termine“).'],
                ]],
                ['name' => 'note', 'label' => 'Hinweis unter den Angeboten (optional)', 'type' => 'text', 'max' => 200, 'placeholder' => 'z. B. Alle Preise zzgl. MwSt.'],
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
            'label' => 'Handlungsaufruf', 'icon' => 'megaphone', 'group' => 'Inhalt', 'background' => 'dark',
            'help' => '„Band“ wirkt am stärksten mit dem Abschnitts-Hintergrund „Dunkel“, „Akzentfarbe“ oder „Blockfarbe“.',
            'variants' => ['band' => 'Band über die volle Breite', 'box' => 'Farbfläche (Box)', 'split' => 'Mit Bild', 'big' => 'Großer Schriftzug'],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true, 'max' => 110, 'help' => 'Ein Wort in *Sternchen* wird hervorgehoben.'],
                ['name' => 'text', 'label' => 'Text (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ...$buttons,
                ['name' => 'image', 'label' => 'Bild (bei „Mit Bild“)', 'type' => 'media'],
            ],
        ],
        'tabs' => [
            'label' => 'Reiter (Tabs)', 'icon' => 'list-bullets', 'group' => 'Inhalt',
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
            'label' => 'Video', 'icon' => 'play-circle', 'group' => 'Medien',
            'help' => 'YouTube/Vimeo mit Zwei-Klick-Lösung (Vorschaubild vom eigenen Server, ohne Cookies bis zum Klick; mit Einwilligungs-Verwaltung abgestimmt) oder eigene MP4-Datei mit Untertiteln und Transkript aus der Mediathek.',
            'variants' => ['wide' => 'Breit', 'text' => 'Mit Text daneben', 'cinema' => 'Kino (randlos, dunkel)'],
            'jsonld' => ['type' => 'video', 'url' => 'video_url', 'poster' => 'poster', 'name' => 'title'],
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
            'help' => 'Kontaktangaben, Öffnungszeiten (mit Anzeige „Jetzt geöffnet“), Karte und optional ein öffentliches Formular (Datentabelle) in einem Abschnitt.',
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
