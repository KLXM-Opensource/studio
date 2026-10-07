<?php
/*
 * Kit „glas“ – Mattglas über Farbfeldern. Für KLXM Studio.
 *
 * Glassmorphism mit Lesbarkeit zuerst: durchscheinende, matte Flächen (backdrop-filter: Unschärfe + Sättigung) über
 * einem weichen, gemalten Farbfeld aus Verläufen – ohne Bilddateien, ohne externe Anfragen. Lichtkanten (1-px-Innenkante
 * mit Alpha), weiche Tiefe, ein schwebender gläserner Kopf, Glaskarten und -dialoge und eine „Aurora“ im Einstieg.
 * Jede Glasfläche hält ≥ 4,5:1 für Text (Deckkraft je Farbschema und Glasdichte, tools/contrast.php); ohne
 * backdrop-filter, bei „Transparenz reduzieren“ und „Mehr Kontrast“ werden die Flächen deckend.
 *
 *  - blocks     Blocktypen (= Editor.js-Tools) mit Feld-Schema und Varianten – Feldnamen wie in „basis“/„fluid“/„essenz“,
 *               damit Websites das Kit wechseln können (hero, richtext, text_image, features, cards, stats, steps, team,
 *               quote, faq, cta, contact, downloads, video, map) + Kern-Blöcke (Galerie, Slider, Skalen, Daten, Kalender)
 *  - settings   zentrales Einstellungsformular („Website“) – identische Schlüssel wie „essenz“/„fluid“
 *  - design     design.php (Style-Editor: Farben, Glas & Farbfeld, Typografie, Form, Kopf & Fuß, Bewegung)
 * Eigenes Kundenprojekt: php bin/console kit:create kunde glas  (Präfix glas_* → kunde_*)
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
    'label' => 'Glas – Mattglas über Farbfeldern',
    'description' => 'Glassmorphism mit Lesbarkeit zuerst: matte, durchscheinende Glasflächen über einem weichen Farbfeld, Lichtkanten, eine ruhige Aurora im Einstieg – geprüfte Kontraste hell und dunkel, deckend bei „Transparenz reduzieren“.',
    'description_en' => 'Glassmorphism with readability first: matte, translucent glass panels over a soft colour field – opaque when “reduce transparency” is on.',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',
    'source_lang' => 'de',

    'backgrounds' => ['white' => 'Farbfeld (Standard)', 'muted' => 'Milchglas-Band', 'tint' => 'Akzent-Schimmer', 'accent' => 'Akzentfarbe', 'dark' => 'Nacht (dunkel)'],
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
    'jsonld' => 'glas_jsonld',

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
        'dashboard_hint' => 'Name, Adresse, Telefon, E-Mail, Öffnungszeiten und Logo werden zentral gepflegt und erscheinen automatisch überall auf der Website. Farbfeld, Glasdichte, Schriften, Kopf- und Fußbereich: Verwaltung → Design.',
        'public_info' => 'glas_public_info',
        'hours' => ['setting' => 'hours', 'format' => 'glas_time_range', 'label' => 'Öffnungszeiten'],
        'notice' => ['text' => 'notice_text', 'active' => 'notice_active', 'example' => 'z. B. Betriebsferien, geänderte Öffnungszeiten'],
        'link_labels' => ['phone' => 'Telefon (aus „Website“)', 'email' => 'E-Mail (aus „Website“)'],
        'search_keywords' => 'kontakt adresse telefon e-mail öffnungszeiten logo farbe glas farbfeld',
        'mcp' => [
            'instructions' => 'Das Kit ist hell, klar und freundlich-technisch: kurze, konkrete Sätze, keine Superlative. Erfinde keine Fakten (Zahlen, Referenzen, Kundennamen, Bewertungen, Rechtstexte); Platzhalter in [eckigen Klammern] nur mit Angaben der Website-Betreiber ersetzen.',
        ],
    ],

    // Schriften: Auswahl im Style-Editor (design.fonts); Vorladen übernimmt glas_font_preloads()
    'fonts' => [
        'icon' => 'fonts/Outfit_600SemiBold.ttf',
    ],
    'app' => [
        'defaults' => ['icon_text' => 'G', 'icon_bg' => '#6D28D9', 'icon_fg' => '#FFFFFF', 'icon_dot' => '#8FE3F0', 'icon_dot_enabled' => true],
        'info' => 'glas_app_info',
    ],
    'image_ratios' => $ratios,

    // Stylesheets und Skripte je Blocktyp (bzw. Typ:Variante) – nur auf Seiten, die sie brauchen
    // Kern-Fragmente (app/Views/fragments, Core\Fragments): Video-Zwei-Klick, Editor und Werkzeugleiste kommen nur aus dem Kern
    // (Skript resources/js/embed.js lädt das Fragment selbst); Marke, Sprachumschalter, Öffnungszeiten … ebenso, solange das Kit
    // keine eigene Datei in fragments/ bzw. templates/partials/ mitbringt.
    'fragments' => ['brand' => ['mark' => 'dot']],
    'conditional_css' => [
        'css/h-aurora.css' => ['hero:aurora', 'hero:', 'hero:command'],         // Einstieg „Aurora“ (auch ohne gespeicherte Variante = Standard); Befehlssuche nutzt die Aurora mit
        'css/h-command.css' => ['hero:command'],                                // Befehlssuche (Suchfeld als Glasfenster, Chips, Pause-Schalter)
        'css/h-video.css' => ['hero:video'],                                    // Video im Hintergrund (Glaspaneel, Abdunkelung, Pause)
        'css/h-stack.css' => ['hero:stack'],                                    // Karten-Stapel
        'css/h-statement.css' => ['hero:statement'],
        'css/h-split.css' => ['hero:split'],
        'css/orb.css' => ['features', 'cards'],                                 // Glasperle für Symbole
        'css/b-prose.css' => ['richtext', 'faq', 'text_image', 'video', '@rich'],   // @rich: Rich-Text-Stile in anderen Blöcken (Core\Sanitizer::styled)
        'css/b-text-image.css' => ['text_image'],
        'css/b-features.css' => ['features'],
        'css/b-stats.css' => ['stats', 'dials'],
        'css/b-steps.css' => ['steps'],
        'css/b-cards.css' => ['cards'],
        'css/b-team.css' => ['team'],
        'css/b-quote.css' => ['quote'],
        'css/b-faq.css' => ['faq'],
        'css/b-cta.css' => ['cta'],
        'css/b-contact.css' => ['contact', 'map'],
        'css/b-downloads.css' => ['downloads'],
        'css/b-video.css' => ['video'],
        'css/dataform.css' => ['contact'],
    ],

    // ------------------------------------------------------------ Design (Style-Editor: Verwaltung → Design)
    'design' => require __DIR__ . '/design.php',

    // Kopfbereich-Aktionen (header_actions()): Kit-Klassen je Stil – „Symbol + Beschriftung“ wird im Glas-Dock zur Glastaste
    'header_actions' => ['late' => true, 'kit_css_late' => 'css/header-actions-late.css', 'kit_css' => 'css/header-actions-kit.css', 'classes' => ['solid' => 'btn btn--primary btn--small', 'outline' => 'btn btn--secondary btn--small', 'split' => 'btn btn--primary btn--small', 'icon' => 'dock__cta']],

    // ------------------------------------------------------------ Website
    'settings' => [
        'title' => 'Website',
        'groups' => [
            ['id' => 'stammdaten', 'label' => 'Stammdaten', 'fields' => [
                ['name' => 'org_name', 'label' => 'Name (Unternehmen, Verein, Büro …)', 'type' => 'text', 'required' => true,
                    'help' => 'Vollständiger Name, z. B. für Karte, Fußbereich und Suchmaschinen.'],
                ['name' => 'short_name', 'label' => 'Kurzname / Wortmarke', 'type' => 'text', 'max' => 32, 'width' => 'half',
                    'help' => 'Erscheint neben der Glasmarke, solange kein Logo hinterlegt ist. Leer = Name.'],
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
                ['name' => 'footer_statement', 'label' => 'Satz im Fußbereich „Aussage“ (optional)', 'type' => 'text', 'max' => 90,
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
                'aurora' => 'Aurora (Glaspaneel über bewegtem Farbfeld)',
                'split' => 'Text und Bild im Glasrahmen',
                'statement' => 'Große Aussage mit Glas-Chips',
                'compact' => 'Seitenkopf (ohne Bild)',
                'command' => 'Befehlssuche (Glas-Suchfeld über der Aurora)',
                'video' => 'Video im Hintergrund (Glaspaneel)',
                'stack' => 'Karten-Stapel (Angebote auf Glas)',
            ],
            // Wann welche Variante? (Handbuch → Alle Blöcke → Einstieg)
            'variant_help' => [
                'aurora' => 'Startseite: Glaspaneel über der bewegten Aurora, daneben Kennwerte, ein Bild oder das Prisma.',
                'split' => 'Startseite oder Angebotsseite mit einem aussagekräftigen Bild und kurzen Pluspunkten.',
                'statement' => 'Eine starke Aussage in großer Schrift, Kennwerte als Glas-Chips, optional ein breites Bild.',
                'compact' => 'Unterseiten: Seitentitel mit kurzer Einleitung.',
                'command' => 'Viele Inhalte, Besucher wissen, was sie suchen: Wissensbasis, Dokumentation, Service-Portal. Große Suche mit Vorschlägen beim Tippen und beliebten Begriffen als Chips.',
                'video' => 'Atmosphäre zählt: Studio, Veranstaltung, Ort. Kurzes, stummes Loop-Video (eigene Datei) mit Abdunkelung; der Text steht auf Glas, eine Schaltfläche hält das Video an.',
                'stack' => 'Zwei bis drei Angebote gleich oben zeigen: Pakete, Leistungen, Einstiege. Die Karten liegen als Glasstapel übereinander und fächern bei Zeiger oder Tastatur auf.',
            ],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift (H1)', 'type' => 'text', 'required' => true, 'max' => 110,
                    'help' => 'Ein Wort in *Sternchen* erhält die Akzentfarbe.'],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 360],
                ...$buttons,
                ['name' => 'points', 'label' => 'Kennwerte / Pluspunkte (eine pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3,
                    'help' => '„Aurora“: schwebende Glasplättchen neben dem Paneel; „Große Aussage“: Glas-Chips; „Text und Bild“: Liste. Tipp: „Bezeichnung: Wert“ erzeugt ein Plättchen mit großer Zahl.'],
                ['name' => 'image', 'label' => 'Bild (optional)', 'type' => 'media', 'width' => 'half',
                    'help' => '„Aurora“: erscheint im Glasrahmen statt der Plättchen. Ohne Bild genügt das Farbfeld – keine Datei nötig. „Video“: Standbild (bei „Bewegung reduzieren“ und bis das Video lädt).',
                    'variants' => ['aurora', 'split', 'statement', 'video']],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:5', 'width' => 'half', 'options' => $ratios,
                    'variants' => ['aurora', 'split']],
                // Neue Varianten: Felder erscheinen nur bei der passenden Variante (Core\Blocks\Hero)
                ...\Core\Blocks\Hero::fields('search', ['command']),
                ...\Core\Blocks\Hero::fields('video', ['video']),
                ...\Core\Blocks\Hero::fields('cards', ['stack']),
            ],
        ],
        'richtext' => [
            'label' => 'Fließtext / Artikel', 'icon' => 'paragraph', 'group' => 'Inhalt',
            'help' => 'Freier Text mit Zwischenüberschriften, Listen, Zitaten und Links. „Artikel“: Lesebreite mit automatischem Inhaltsverzeichnis im Glaspaneel.',
            'variants' => ['standard' => 'Standard', 'article' => 'Artikel mit Inhaltsverzeichnis', 'glass' => 'Auf einer Glasfläche'],
            'fields' => [
                ...$head(false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'meta', 'label' => 'Angaben über dem Text (optional, bei „Artikel“)', 'type' => 'text', 'max' => 120, 'placeholder' => 'z. B. Redaktion · 12. März 2026'],
                ['name' => 'toc', 'label' => 'Inhaltsverzeichnis anzeigen (bei „Artikel“)', 'type' => 'bool', 'default' => true],
            ],
        ],
        'text_image' => [
            'label' => 'Text + Bild', 'icon' => 'square-half', 'group' => 'Inhalt',
            'help' => 'Bild im Glasrahmen neben dem Text. „Abwechselnd“: mehrere Blöcke nacheinander wechseln automatisch die Seite.',
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
            'label' => 'Merkmale / Vorteile', 'icon' => 'squares-four', 'group' => 'Inhalt',
            'help' => 'Leistungen oder Vorteile mit Symbol in einer Glasperle. „Glaskarten“: je Eintrag eine Karte; „Ein Glaspaneel“: alle Einträge auf einer Fläche.',
            'variants' => ['cards' => 'Glaskarten', 'panel' => 'Ein Glaspaneel', 'list' => 'Liste mit Symbol'],
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
            'label' => 'Produkte / Leistungen (Karten)', 'icon' => 'copy', 'group' => 'Inhalt',
            'help' => '„Glaskarte“: Bild oben, darunter Text auf Glas. „Leistung“: ohne Bild, mit Symbol. „Bild mit Glasleiste“: Text schwebt auf einer Glasleiste über dem Bild.',
            'variants' => ['glass' => 'Glaskarte (Bild oben)', 'service' => 'Leistung (Symbol, ohne Bild)', 'overlay' => 'Bild mit Glasleiste'],
            'fields' => [
                ...$head(),
                $size,
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:3', 'width' => 'half', 'options' => $ratios],
                ['name' => 'items', 'label' => 'Karten', 'type' => 'repeater', 'item_label' => 'Karte', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'icon', 'label' => 'Symbol (bei „Leistung“)', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Paket S'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 90, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
                    ['name' => 'meta', 'label' => 'Kennwert / Preis (optional)', 'type' => 'text', 'max' => 40, 'placeholder' => 'z. B. ab 4.800 € · 3 Wochen'],
                    ...$link(),
                ]],
                ['name' => 'more_label', 'label' => 'Button unter den Karten (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'more_link', 'label' => 'Button-Link', 'type' => 'link', 'width' => 'half'],
            ],
        ],
        'stats' => [
            'label' => 'Kennzahlen', 'icon' => 'chart-bar', 'group' => 'Inhalt',
            'help' => 'Nur belegbare Zahlen verwenden. „Glasringe“: ein Ring je Zahl; der Füllstand (0–100) ist optional. Für Skalen mit Einheit und Höchstwert: Kern-Block „Kennzahlen mit Skala“.',
            'variants' => ['rings' => 'Glasringe', 'row' => 'Große Zahlen auf Glas'],
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Kennzahlen', 'type' => 'repeater', 'item_label' => 'Kennzahl', 'title_field' => 'value', 'max_items' => 6, 'fields' => [
                    ['name' => 'value', 'label' => 'Wert', 'type' => 'text', 'required' => true, 'max' => 12, 'width' => 'half', 'placeholder' => 'z. B. 25'],
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'max' => 50, 'width' => 'half'],
                    ['name' => 'level', 'label' => 'Füllstand in % (optional)', 'type' => 'number', 'width' => 'half', 'min' => 0, 'max' => 100,
                        'help' => 'Leer = nur der Ring. Nur verwenden, wenn der Anteil eine echte Bedeutung hat (z. B. 92 % Weiterempfehlung).'],
                    ['name' => 'text', 'label' => 'Erläuterung (optional)', 'type' => 'text', 'max' => 140, 'width' => 'half'],
                ]],
            ],
        ],
        'steps' => [
            'label' => 'Ablauf / Zeitleiste', 'icon' => 'path', 'group' => 'Inhalt',
            'help' => 'Nummerierte Schritte auf Glas mit leuchtender Verbindungslinie oder eine senkrechte Zeitleiste mit Datum/Phase.',
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
        'team' => [
            'label' => 'Team / Personen', 'icon' => 'users-three', 'group' => 'Inhalt',
            'help' => 'Personen mit Rolle und kurzer Beschreibung. Ohne Foto erscheinen die Initialen in einer Glasperle – kein Bild nötig.',
            'variants' => ['cards' => 'Glaskarten', 'compact' => 'Kompakte Liste'],
            'fields' => [
                ...$head(),
                $size,
                ['name' => 'items', 'label' => 'Personen', 'type' => 'repeater', 'item_label' => 'Person', 'title_field' => 'name', 'max_items' => 24, 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 80, 'width' => 'half'],
                    ['name' => 'role', 'label' => 'Rolle / Funktion', 'type' => 'text', 'max' => 80, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Kurzbeschreibung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
                    ['name' => 'image', 'label' => 'Foto (optional, 1:1)', 'type' => 'media', 'width' => 'half',
                        'help' => 'Nur mit Einwilligung der Person. Leer = Initialen.'],
                    ['name' => 'email', 'label' => 'E-Mail (optional)', 'type' => 'email', 'width' => 'half', 'translate' => false],
                ]],
            ],
        ],
        'quote' => [
            'label' => 'Zitat / Stimmen', 'icon' => 'quotes', 'group' => 'Inhalt',
            'help' => 'Ein Zitat auf einer großen Glasfläche oder mehrere Stimmen im Raster. Nur echte, freigegebene Stimmen verwenden.',
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
            'help' => 'Aufklappbare Fragen auf Glas (ohne JavaScript). Suchmaschinen erhalten die Fragen als strukturierte Daten (FAQPage).',
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
            'help' => '„Glas über Aurora“: Text und Buttons auf Glas vor einem kleinen Farbfeld. „Band“ wirkt am stärksten mit dem Abschnitts-Hintergrund „Akzentfarbe“ oder „Nacht“.',
            'variants' => ['aurora' => 'Glas über Aurora', 'band' => 'Band über die volle Breite', 'minimal' => 'Zurückhaltend (zentriert)'],
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
            'help' => 'Kontaktangaben und Öffnungszeiten auf Glas (mit Anzeige „Jetzt geöffnet“), Karte und optional ein öffentliches Formular.',
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
            'help' => 'YouTube/Vimeo mit Zwei-Klick-Lösung (Vorschaubild vom eigenen Server, ohne Cookies bis zum Klick; mit Cookie-Hinweis: dessen Einwilligung) oder eigene MP4-Datei mit Untertiteln und Transkript aus der Mediathek.',
            'variants' => ['wide' => 'Breit', 'text' => 'Mit Text daneben'],
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
        'map' => [
            'label' => 'Karte', 'icon' => 'map-trifold', 'group' => 'Website',
            'help' => 'Interaktive Karte im Glasrahmen (OpenStreetMap-Daten über den eigenen Server – ohne Einwilligung, ohne Cookies).',
            'fields' => [...$head(false, false), ...\Core\Maps::blockFields()],
        ],
    ],
];
