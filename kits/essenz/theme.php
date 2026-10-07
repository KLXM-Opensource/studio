<?php
/*
 * Theme „essenz“ – weniger, aber besser. Für KLXM Studio.
 *
 * Inspiriert von den zehn Thesen für gutes Design (Dieter Rams): funktional, ehrlich, unaufdringlich, langlebig,
 * konsequent bis ins Detail, umweltfreundlich, so wenig Design wie möglich. Eigenständige Gestaltung – keine Marken,
 * Logos, Texte oder Bilder Dritter.
 *
 * Formensprache: warmes Hellgrau, Graphit, EIN Signal (Standard Orange), 8-Punkt-Raster, Karten als Geräte-Paneele
 * (weicher Radius, feine Kante), Punktraster/Skalen/Regler als sparsame Details, Grotesk + Beschriftungsschrift mit
 * Tabellenziffern. Dunkles Schema als „Nachtpaneel“.
 *
 *  - blocks     Blocktypen (= Editor.js-Tools) mit Feld-Schema und Varianten – Feldnamen wie in „basis“/„fluid“,
 *               damit Websites das Theme wechseln können (hero, richtext, media_text, features, cards, stats, steps,
 *               quote, faq, cta, contact, downloads, video, map) + eigene: principles (Thesen), specs (Datenblatt)
 *  - settings   zentrales Einstellungsformular („Website“) – identische Schlüssel wie „fluid“
 *  - design     design.php (Style-Editor)
 * Eigenes Kundenprojekt: php bin/console theme:create kunde essenz  (Präfix essenz_* → kunde_*)
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
    'label' => 'Essenz – weniger, aber besser',
    'description' => 'Funktionaler Minimalismus, inspiriert von den zehn Thesen für gutes Design von Dieter Rams: warmes Hellgrau, Graphit, ein Signal, 8-Punkt-Raster, Karten wie Geräte-Paneele – ruhig, präzise, langlebig und leicht.',
    'description_en' => 'Functional minimalism inspired by Dieter Rams’ ten principles of good design: warm light grey, a single signal colour, cards like device panels.',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',
    'source_lang' => 'de',

    'backgrounds' => ['white' => 'Standard', 'muted' => 'Getönt', 'tint' => 'Signal hell', 'accent' => 'Signalfarbe', 'dark' => 'Nachtpaneel (dunkel)'],
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
    'jsonld' => 'essenz_jsonld',

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
        'dashboard_hint' => 'Name, Adresse, Telefon, E-Mail, Öffnungszeiten und Logo werden zentral gepflegt und erscheinen automatisch überall auf der Website. Signalfarbe, Schriften, Kopf- und Fußbereich: Verwaltung → Design.',
        'public_info' => 'essenz_public_info',
        'hours' => ['setting' => 'hours', 'format' => 'essenz_time_range', 'label' => 'Öffnungszeiten'],
        'notice' => ['text' => 'notice_text', 'active' => 'notice_active', 'example' => 'z. B. Betriebsferien, geänderte Öffnungszeiten'],
        'link_labels' => ['phone' => 'Telefon (aus „Website“)', 'email' => 'E-Mail (aus „Website“)'],
        'search_keywords' => 'kontakt adresse telefon e-mail öffnungszeiten logo farbe signal',
        'mcp' => [
            'instructions' => 'Das Kit ist sachlich und reduziert („weniger, aber besser“): kurze, klare Sätze, keine Superlative. Erfinde keine Fakten (Zahlen, Referenzen, Kundennamen, Bewertungen, Rechtstexte); Platzhalter in [eckigen Klammern] nur mit Angaben der Website-Betreiber ersetzen.',
        ],
    ],

    // Schriften: Auswahl im Style-Editor (design.fonts); Vorladen übernimmt essenz_font_preloads()
    'fonts' => [
        'icon' => 'fonts/Inter_700Bold.ttf',
    ],
    'app' => [
        'defaults' => ['icon_text' => 'E', 'icon_bg' => '#1B1B19', 'icon_fg' => '#F2F1ED', 'icon_dot' => '#D85B19', 'icon_dot_enabled' => true],
        'info' => 'essenz_app_info',
    ],
    'image_ratios' => $ratios,

    // Stylesheets und Skripte je Blocktyp (bzw. Typ:Variante) – nur auf Seiten, die sie brauchen
    // Kern-Fragmente (app/Views/fragments, Core\Fragments): Video-Zwei-Klick, Editor und Werkzeugleiste kommen nur aus dem Kern
    // (Skript resources/js/embed.js lädt das Fragment selbst); Marke, Sprachumschalter, Öffnungszeiten … ebenso, solange das Kit
    // keine eigene Datei in fragments/ bzw. templates/partials/ mitbringt.
    'fragments' => ['brand' => ['mark' => 'dot']],
    'conditional_css' => [
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
        'css/b-downloads.css' => ['downloads'],
        'css/b-video.css' => ['video'],
        'css/dataform.css' => ['contact'],
        'css/hero-x.css' => ['hero:console', 'hero:dials', 'hero:monitor'],   // Einstieg „Bedienfeld“, „Kennzahlen“, „Monitor“
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
                ['type' => 'heading', 'label' => 'Karte'],
                ['name' => 'geo', 'label' => 'Standort auf der Karte', 'type' => 'geo', 'address_fields' => ['street', 'zip', 'city'], 'translate' => false,
                    'help' => 'Aus der Adresse ermitteln oder in die Karte klicken. Leer = keine Karte. Die Karte lädt über den eigenen Server – ohne Einwilligung, ohne Cookies.'],
            ]],
            ['id' => 'darstellung', 'label' => 'Darstellung', 'fields' => [
                ['name' => 'logo', 'label' => 'Logo (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'SVG oder PNG mit transparentem Hintergrund, ca. 40 px hoch dargestellt.'],
                ['name' => 'logo_dark', 'label' => 'Logo für dunkles Farbschema (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'Helle Fassung des Logos. Leer = normales Logo.'],
                ['name' => 'footer_statement', 'label' => 'Satz im Fußbereich „Paneel“ (optional)', 'type' => 'text', 'max' => 90,
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
                'panel' => 'Mit Geräte-Paneel (Bild oder Punktraster, Kennwerte)',
                'statement' => 'Große Aussage (typografisch)',
                'split' => 'Text und Bild nebeneinander',
                'compact' => 'Seitenkopf (ohne Bild)',
                'console' => 'Bedienfeld (Anzeige, Kanäle mit Pegel)',
                'dials' => 'Kennzahlen (Rundinstrumente im Paneel)',
                'monitor' => 'Video im Geräterahmen (Monitor)',
            ],
            // Wann welche Variante? (Handbuch → Alle Blöcke → Einstieg)
            'variant_help' => [
                'panel' => 'Startseite: Aussage links, rechts ein Geräte-Paneel mit Bild (oder Punktraster) und nummerierten Kennwerten.',
                'statement' => 'Eine starke Aussage, typografisch groß – Kennwerte als Index daneben, optional ein Breitbild.',
                'split' => 'Klassischer Einstieg mit aussagekräftigem Bild und kurzen Pluspunkten.',
                'compact' => 'Unterseiten: Seitentitel mit kurzer Einleitung.',
                'console' => 'Technische Angebote, Werkstatt, Service: eine große Anzeige und bis zu sechs Kanäle „Bezeichnung: Wert“ – Prozentwerte zeigen einen Pegel.',
                'dials' => 'Wenn belegbare Zahlen überzeugen: Aussage oben, 3–4 Kennzahlen als Rundinstrumente in einem Paneel.',
                'monitor' => 'Bewegtbild ohne Anbieter: kurzes, stummes Loop-Video aus der Mediathek im Monitor-Rahmen – mit Pause-Taste, bei „Bewegung reduzieren“ Standbild.',
            ],
            // Stylesheets, die eine Variante zusätzlich braucht (Kern-Rundinstrumente bei „Kennzahlen“)
            'uses' => ['dials' => ['dials']],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift (H1)', 'type' => 'text', 'required' => true, 'max' => 110,
                    'help' => 'Ein Wort in *Sternchen* erhält die Signalfarbe.'],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 360],
                ...$buttons,
                ['name' => 'points', 'label' => 'Kennwerte / Pluspunkte (eine pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3,
                    'help' => '„Mit Geräte-Paneel“: erscheinen nummeriert im Paneel; „Große Aussage“: als Liste neben dem Text. Tipp: „Bezeichnung: Wert“ erzeugt eine Kennwert-Zeile.',
                    'variants' => ['panel', 'statement', 'split', 'console']],
                ['name' => 'display_value', 'label' => 'Anzeige – Wert', 'type' => 'text', 'max' => 10, 'width' => 'half', 'placeholder' => 'z. B. 24 h',
                    'help' => 'Große Anzeige oben im Bedienfeld (Mono-Ziffern). Leer = keine Anzeige. Die Kanäle kommen aus „Kennwerte“: je Zeile „Bezeichnung: Wert“, bis zu sechs; „87 %“ oder „4 / 5“ zeigen einen Pegel.',
                    'variants' => ['console']],
                ['name' => 'display_label', 'label' => 'Anzeige – Beschriftung', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Rückmeldung innerhalb',
                    'variants' => ['console']],
                ...\Core\Blocks\Hero::fields('figures', ['dials']),
                ...\Core\Blocks\Hero::fields('video', ['monitor']),
                ['name' => 'panel_label', 'label' => 'Beschriftung des Paneels (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half',
                    'help' => 'Kleine Aufschrift oben im Paneel, z. B. „Modell 01“ oder „Werkstatt“.', 'variants' => ['panel', 'console', 'dials', 'monitor']],
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half', 'help' => 'Optional. Ohne Bild zeigt das Paneel ein ruhiges Punktraster mit Regler (keine Datei nötig). Bei „Video im Geräterahmen“: Standbild.',
                    'variants' => ['panel', 'statement', 'split', 'monitor']],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:5', 'width' => 'half', 'options' => $ratios,
                    'help' => 'Bei „Große Aussage“ immer 21:9.', 'variants' => ['panel', 'split']],
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
            'label' => 'Merkmale / Bedienfeld', 'icon' => 'squares-four', 'group' => 'Inhalt',
            'help' => 'Leistungen oder Vorteile mit Symbol. „Bedienfeld“: alle Einträge in einem Paneel, getrennt durch feine Linien – wie Tasten auf einem Gerät.',
            'variants' => ['panel' => 'Bedienfeld (ein Paneel)', 'cards' => 'Einzelne Karten', 'list' => 'Liste mit Symbol'],
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
            'label' => 'Grundsätze / Thesen', 'icon' => 'list-checks', 'group' => 'Inhalt',
            'help' => 'Nummerierte Grundsätze, Werte oder Versprechen – als ruhige Liste mit großen Nummern oder als Raster.',
            'variants' => ['list' => 'Liste (Nummer, Titel, Text)', 'grid' => 'Raster aus Paneelen', 'accordion' => 'Aufklappbar (kompakt)'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Grundsätze', 'type' => 'repeater', 'item_label' => 'Grundsatz', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'title', 'label' => 'Grundsatz', 'type' => 'text', 'required' => true, 'max' => 90],
                    ['name' => 'text', 'label' => 'Erläuterung', 'type' => 'textarea', 'rows' => 3, 'max' => 480],
                ]],
            ],
        ],
        'specs' => [
            'label' => 'Datenblatt (technische Daten)', 'icon' => 'table', 'group' => 'Inhalt',
            'help' => 'Kennwerte als ruhige Tabelle – für Produkte, Räume, Leistungen oder Konditionen. Optional mit Bild daneben.',
            'variants' => ['table' => 'Tabelle', 'image' => 'Mit Bild daneben'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Kennwerte', 'type' => 'repeater', 'item_label' => 'Zeile', 'title_field' => 'label', 'max_items' => 30, 'fields' => [
                    ['name' => 'group', 'label' => 'Gruppe (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half',
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
            'help' => 'Nur belegbare Zahlen verwenden. „Skalen“: ein Rundinstrument je Zahl; der Füllstand (0–100) ist optional.',
            'variants' => ['dials' => 'Skalen (Rundinstrumente)', 'row' => 'Große Zahlen in einer Reihe'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Kennzahlen', 'type' => 'repeater', 'item_label' => 'Kennzahl', 'title_field' => 'value', 'max_items' => 6, 'fields' => [
                    ['name' => 'value', 'label' => 'Wert', 'type' => 'text', 'required' => true, 'max' => 12, 'width' => 'half', 'placeholder' => 'z. B. 25'],
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'max' => 50, 'width' => 'half'],
                    ['name' => 'level', 'label' => 'Füllstand der Skala in % (optional)', 'type' => 'number', 'width' => 'half', 'min' => 0, 'max' => 100,
                        'help' => 'Leer = nur Skalenstriche. Nur verwenden, wenn der Anteil eine echte Bedeutung hat (z. B. 92 % Weiterempfehlung).'],
                    ['name' => 'text', 'label' => 'Erläuterung (optional)', 'type' => 'text', 'max' => 140, 'width' => 'half'],
                ]],
            ],
        ],
        'steps' => [
            'label' => 'Ablauf / Zeitleiste', 'icon' => 'path', 'group' => 'Inhalt',
            'help' => 'Nummerierte Schritte mit Verbindungslinie oder eine senkrechte Zeitleiste mit Datum/Phase.',
            'variants' => ['steps' => 'Schritte (nebeneinander)', 'timeline' => 'Zeitleiste (untereinander)'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Schritte', 'type' => 'repeater', 'item_label' => 'Schritt', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'meta', 'label' => 'Phase / Datum (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Woche 1'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 80, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 400],
                ]],
            ],
        ],
        'cards' => [
            'label' => 'Produkte / Leistungen (Karten)', 'icon' => 'copy', 'group' => 'Inhalt',
            'help' => '„Produkt“: Bild auf ruhiger Fläche wie ein Gerät im Katalog, darunter Kennwert und Pfeil. „Leistung“: ohne Bild, mit Symbol.',
            'variants' => ['product' => 'Produkt (Bild auf Fläche)', 'service' => 'Leistung (Symbol, ohne Bild)', 'image' => 'Bild randlos oben'],
            'fields' => [
                ...$head(),
                $size,
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:3', 'width' => 'half', 'options' => $ratios],
                ['name' => 'items', 'label' => 'Karten', 'type' => 'repeater', 'item_label' => 'Karte', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'icon', 'label' => 'Symbol (bei „Leistung“)', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Modell 02'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 90, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
                    ['name' => 'meta', 'label' => 'Kennwert / Preis (optional)', 'type' => 'text', 'max' => 40, 'placeholder' => 'z. B. ab 480 € · 3 Wochen'],
                    ...$link(),
                ]],
                ['name' => 'more_label', 'label' => 'Button unter den Karten (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'more_link', 'label' => 'Button-Link', 'type' => 'link', 'width' => 'half'],
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
            'help' => '„Paneel“: Text und Tasten in einem ruhigen Gehäuse. „Band“ wirkt am stärksten mit dem Abschnitts-Hintergrund „Signalfarbe“ oder „Nachtpaneel“.',
            'variants' => ['panel' => 'Paneel', 'band' => 'Band über die volle Breite', 'minimal' => 'Zurückhaltend (zentriert)'],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true, 'max' => 110],
                ['name' => 'text', 'label' => 'Text (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ...$buttons,
            ],
        ],
        'contact' => [
            'label' => 'Kontakt (Zeiten, Karte, Formular)', 'icon' => 'address-book', 'group' => 'Website',
            'central' => 'Adresse, Telefon, E-Mail, Öffnungszeiten und Karte kommen aus „Website“.',
            'help' => 'Kontaktangaben, Öffnungszeiten als Paneel (mit Anzeige „Jetzt geöffnet“), Karte und optional ein öffentliches Formular.',
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
