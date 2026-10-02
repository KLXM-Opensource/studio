<?php
// SPDX-License-Identifier: MIT
/*
 * ============================================================================================================
 *  Kit „frameworks“ – Demo: ein Kit mit einem bekannten CSS-Framework, umschaltbar zwischen Tailwind CSS v4 und UIkit 3.
 * ============================================================================================================
 *
 *  Zweck: zeigen, dass Bearbeiten auf der Website, Werkzeugleiste, Glossar, Datenlisten, Formulare & Co. mit einem
 *  Framework-Kit funktionieren – und wie man es richtig aufsetzt (Hilfe → Technik → „Frameworks (Tailwind, UIkit, Bootstrap)“).
 *
 *  Umschalten:   Verwaltung → Design → „Framework“ (Tailwind | UIkit). Kurz zum Vergleichen: ?fw=uikit bzw. ?fw=tailwind
 *                (nur diese Anfrage; angemeldet für die Sitzung, ?fw=reset hebt das auf). Siehe functions.php → frameworks_fw().
 *  Aufbau:       blocks/{typ}.php wählt nur die Vorlage: views/tailwind/{typ}.php oder views/uikit/{typ}.php.
 *                Alles Framework-Spezifische liegt in views/{framework}/ – zum Start eines eigenen Kits einen Ordner behalten.
 *  CSS:          Tailwind: public/assets/kits/frameworks/css/tailwind.css – vorkompiliert (build.mjs, @tailwindcss/cli) aus
 *                tailwind/app.css; Quellen = views/tailwind, templates, blocks, functions.php. Kein Play-CDN (CSP, Produktion).
 *                UIkit: vendor/uikit/uikit.min.css + css/uikit.css (Brücke: Kontraste, Dunkelmodus, Rich-Text-Klassen).
 *                Beide: css/site.css – nur Design-Tokens und Variablen für Kern-Bausteine (Formular, Glossar, Karte …).
 *
 *  Abschnitte (§) wie im Start-Kit: § 1 Meta · § 2 Abschnitte · § 3 SEO · § 4 Projekt · § 5 Assets · § 6 Design · § 7 Website
 *  · § 8 Blöcke · § 9 Daten · § 10 Startinhalte · § 11 Sprachen
 */

require_once __DIR__ . '/functions.php';

$head = fn(bool $required = false, bool $intro = true) => array_merge([
    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
    ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'max' => 110, 'width' => 'half', 'required' => $required],
], $intro ? [['name' => 'intro', 'label' => 'Einleitung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 400]] : []);

$buttons = [
    ['name' => 'button_label', 'label' => 'Button 1 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button_link', 'label' => 'Button 1 – Link', 'type' => 'link', 'width' => 'half'],
    ['name' => 'button2_label', 'label' => 'Button 2 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button2_link', 'label' => 'Button 2 – Link', 'type' => 'link', 'width' => 'half'],
];

$ratios = ['16:9' => 'Breitbild 16:9', '3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', '1:1' => 'Quadrat 1:1'];

return [

    // ======================================================================================================== § 1 Meta
    'label' => 'Frameworks (Tailwind / UIkit)',
    'description' => 'Demo-Kit: dieselben Blöcke wahlweise mit Tailwind CSS v4 oder UIkit 3 – zeigt, wie ein Framework-Kit mit Bearbeiten auf der Website, Glossar, Datenlisten und Formularen zusammenspielt.',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',
    'source_lang' => 'de',

    // ======================================================================================================== § 2 Abschnitte
    // Schlüssel werden zur Klasse bg-{schlüssel}. Bewusst KEINE Namen, die Tailwind-Hilfsklassen ergeben könnten
    // (bg-white, bg-accent …) – sonst färbt eine Utility den Abschnitt mit. UIkit: section.php ordnet uk-section-* zu.
    'backgrounds' => ['plain' => 'Standard', 'tint' => 'Getönt', 'band' => 'Akzentfarbe', 'ink' => 'Dunkel'],
    'dark_backgrounds' => ['band', 'ink'],
    // Kern-Blöcke (Datenliste, Formular, Karte …) bekommen Klassen beider Frameworks – geladen ist immer nur eines
    'container_class' => 'wrap uk-container',
    'button_class' => 'btn btn-primary uk-button uk-button-primary',
    'button_secondary_class' => 'btn btn-secondary uk-button uk-button-default',
    'frame_hosts' => [],

    // ======================================================================================================== § 3 SEO & JSON-LD
    'seo' => [
        'home_title' => 'site_title',
        'title_suffix' => 'site_title_suffix',
        'title_suffix_fallback' => 'org_name',
        'description' => 'default_meta_description',
        'og_image' => 'og_default_image',
    ],
    'jsonld' => 'frameworks_jsonld',
    'link_keywords' => ['phone', 'email'],

    // ======================================================================================================== § 4 Projekt
    'project' => [
        'terms' => ['org' => 'Organisation', 'site_name' => 'Name der Website'],
        'name_setting' => 'org_name',
        'brand' => ['short_name', 'tagline'],
        'map' => ['location' => 'geo', 'label' => 'org_name', 'address' => ['street', 'zip', 'city']],
        'setup_checks' => [
            ['setting' => 'org_name', 'label' => 'Name eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'email', 'label' => 'E-Mail-Adresse eingetragen', 'link' => '/admin/settings#stammdaten'],
        ],
        'dashboard_hint' => 'Framework umschalten: Verwaltung → Design → „Framework“. Zum schnellen Vergleich ?fw=uikit bzw. ?fw=tailwind an die Adresse hängen.',
        'public_info' => 'frameworks_public_info',
        'notice' => ['text' => 'notice_text', 'active' => 'notice_active', 'example' => 'z. B. geänderte Erreichbarkeit'],
        'link_labels' => ['phone' => 'Telefon (aus „Website“)', 'email' => 'E-Mail (aus „Website“)'],
        'search_keywords' => 'kontakt adresse telefon e-mail framework tailwind uikit',
        'mcp' => ['instructions' => 'Demo-Website einer fiktiven Firma. Sachlich und kurz schreiben, keine Fakten erfinden.'],
    ],
    'app' => [
        'defaults' => ['icon_text' => 'F', 'icon_bg' => '#2747C4', 'icon_fg' => '#FFFFFF', 'icon_dot' => '#FFFFFF', 'icon_dot_enabled' => false],
        'info' => 'frameworks_app_info',
    ],

    // ======================================================================================================== § 5 Assets
    // Framework-CSS/-JS bindet templates/layout.php je nach frameworks_fw() ein (nicht hier – conditional_css kennt nur Blocktypen).
    'image_ratios' => $ratios,
    'fragments' => ['video-embed' => ['play_class' => 'btn btn-primary uk-button uk-button-primary', 'empty' => 'none']],
    'conditional_css' => [
        'css/video.css' => ['video'],
    ],

    // ======================================================================================================== § 6 Design
    // Standardwerte = assets/css/site.css (:root). „framework“ ist eine Klasse am <html> (fw-tailwind | fw-uikit);
    // welches CSS/JS lädt, entscheidet frameworks_fw() (berücksichtigt auch ?fw=…).
    'design' => [
        'groups' => [
            ['id' => 'framework', 'label' => 'Framework', 'tokens' => [
                ['name' => 'framework', 'label' => 'CSS-Framework', 'type' => 'choice', 'class' => 'fw-{value}', 'default' => 'tailwind',
                    'options' => ['tailwind' => 'Tailwind CSS 4', 'uikit' => 'UIkit 3'],
                    'help' => 'Dieselben Inhalte, anderes Framework. Zum Vergleich ohne Speichern: ?fw=uikit bzw. ?fw=tailwind an die Adresse hängen.'],
                ['name' => 'tw_preflight', 'label' => 'Tailwind: Preflight (Basis-Stile) laden', 'type' => 'bool', 'class' => 'tw-preflight', 'default' => true,
                    'help' => 'Nur Tailwind. Aus = Fassung ohne Preflight (zum Vergleich). Preflight liegt in @layer base und wirkt nicht auf die Oberfläche des CMS.'],
            ]],
            ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
                ['name' => 'accent', 'label' => 'Akzentfarbe', 'type' => 'color', 'var' => '--fw-accent', 'default' => '#2747C4', 'dark' => '#9DB4FF',
                    'contrast' => ['with' => '#FFFFFF', 'min' => 4.5, 'dark_with' => '#121418'], 'help' => 'Links, Buttons, Fokusrahmen, Abschnitt „Akzentfarbe“.'],
                ['name' => 'on_accent', 'label' => 'Schrift auf Akzentfarbe', 'type' => 'color', 'var' => '--fw-on-accent', 'default' => '#FFFFFF', 'dark' => '#0B1533',
                    'contrast' => ['with' => 'accent', 'min' => 4.5]],
            ]],
            ['id' => 'form', 'label' => 'Form & Kopf', 'tokens' => [
                ['name' => 'radius', 'label' => 'Eckenradius', 'type' => 'range', 'var' => '--fw-radius', 'unit' => 'px', 'min' => 0, 'max' => 24, 'step' => 2, 'default' => 12],
                ['name' => 'sticky', 'label' => 'Kopfbereich beim Scrollen sichtbar halten', 'type' => 'bool', 'class' => 'has-sticky', 'default' => true],
            ]],
            ['id' => 'modus', 'label' => 'Farbschema', 'tokens' => [
                ['name' => 'dark', 'label' => 'Dunkles Farbschema, wenn im Gerät der Besucher eingestellt', 'type' => 'bool', 'class' => 'has-dark', 'default' => true],
            ]],
        ],
        'fonts' => [],
        'presets' => [
            'blau' => ['label' => 'Blau (Standard)', 'values' => ['accent' => '#2747C4', 'accent@dark' => '#9DB4FF', 'on_accent' => '#FFFFFF', 'on_accent@dark' => '#0B1533']],
            'gruen' => ['label' => 'Grün', 'values' => ['accent' => '#0F6B4F', 'accent@dark' => '#6FD3AE', 'on_accent' => '#FFFFFF', 'on_accent@dark' => '#03241A']],
        ],
        'dark' => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark', 'force' => 'is-dark'],
    ],

    // ======================================================================================================== § 7 Website
    'settings' => [
        'title' => 'Website',
        'groups' => [
            ['id' => 'stammdaten', 'label' => 'Stammdaten', 'fields' => [
                ['name' => 'org_name', 'label' => 'Name (Unternehmen, Verein, Büro …)', 'type' => 'text', 'required' => true],
                ['name' => 'short_name', 'label' => 'Kurzname / Wortmarke', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'tagline', 'label' => 'Kurzbeschreibung', 'type' => 'text', 'max' => 90, 'width' => 'half'],
                ['type' => 'heading', 'label' => 'Adresse und Kontakt'],
                ['name' => 'street', 'label' => 'Straße und Hausnummer', 'type' => 'text', 'translate' => false],
                ['name' => 'zip', 'label' => 'PLZ', 'type' => 'text', 'width' => 'half', 'translate' => false],
                ['name' => 'city', 'label' => 'Ort', 'type' => 'text', 'width' => 'half'],
                ['name' => 'phone', 'label' => 'Telefon', 'type' => 'tel', 'width' => 'half', 'translate' => false],
                ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'width' => 'half', 'translate' => false],
                ['name' => 'geo', 'label' => 'Standort auf der Karte', 'type' => 'geo', 'address_fields' => ['street', 'zip', 'city'], 'translate' => false,
                    'help' => 'Für den Block „Karte“. Leer = keine Karte.'],
            ]],
            ['id' => 'darstellung', 'label' => 'Darstellung', 'fields' => [
                ['name' => 'logo', 'label' => 'Logo (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false],
                ['name' => 'footer_text', 'label' => 'Text im Fußbereich', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
            ]],
            ['id' => 'hinweis', 'label' => 'Hinweisbalken', 'fields' => [
                ['name' => 'notice_active', 'label' => 'Hinweis oben auf jeder Seite anzeigen', 'type' => 'bool', 'default' => false, 'translate' => false],
                ['name' => 'notice_text', 'label' => 'Hinweis', 'type' => 'inline'],
            ]],
            ['id' => 'recht', 'label' => 'Recht', 'fields' => [
                ['name' => 'imprint_page', 'label' => 'Impressum', 'type' => 'page', 'width' => 'half', 'translate' => false],
                ['name' => 'privacy_page', 'label' => 'Datenschutzerklärung', 'type' => 'page', 'width' => 'half', 'translate' => false],
            ]],
            ['id' => 'seo', 'label' => 'SEO', 'fields' => [
                ['name' => 'site_title', 'label' => 'Seitentitel der Startseite', 'type' => 'text', 'max' => 70],
                ['name' => 'site_title_suffix', 'label' => 'Titel-Zusatz', 'type' => 'text'],
                ['name' => 'default_meta_description', 'label' => 'Standard-Beschreibung', 'type' => 'textarea', 'rows' => 3, 'max' => 160],
                ['name' => 'og_default_image', 'label' => 'Vorschaubild für soziale Netzwerke', 'type' => 'media', 'translate' => false],
            ]],
        ],
    ],

    // ======================================================================================================== § 8 Blöcke
    // Allgemeine, kombinierbare Blöcke (Form statt Thema). Jede Vorlage gibt es zweimal: views/tailwind/ und views/uikit/.
    'blocks' => [
        'hero' => [
            'label' => 'Einstieg (Hero)', 'icon' => 'star', 'group' => 'Kopf', 'background' => 'plain',
            'help' => 'Große Überschrift der Seite (H1) – pro Seite genau einmal, als erster Block.',
            'variants' => ['center' => 'Zentriert', 'split' => 'Text und Bild nebeneinander'],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift (H1)', 'type' => 'text', 'required' => true, 'max' => 110],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 360],
                ...$buttons,
                ['name' => 'image', 'label' => 'Bild (bei „Text und Bild“)', 'type' => 'media', 'variants' => ['split']],
            ],
        ],
        'cards' => [
            'label' => 'Karten', 'icon' => 'squares-four', 'group' => 'Inhalt', 'background' => 'plain',
            'help' => 'Raster aus Karten mit Symbol oder Bild; verlinkte Karten zeigen der Redaktion „Bearbeiten“ für das Ziel.',
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Karten', 'type' => 'repeater', 'item_label' => 'Karte', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'icon', 'label' => 'Symbol', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'oder Bild (3:2)', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 300],
                    ['name' => 'link', 'label' => 'Link (optional)', 'type' => 'link'],
                ]],
            ],
        ],
        'text_image' => [
            'nestable' => true,
            'label' => 'Text + Bild', 'icon' => 'square-half', 'group' => 'Inhalt', 'background' => 'plain',
            'variants' => ['right' => 'Bild rechts', 'left' => 'Bild links'],
            'fields' => [
                ...$head(true, false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'button_label', 'label' => 'Button – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'button_link', 'label' => 'Button – Link', 'type' => 'link', 'width' => 'half'],
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:3', 'width' => 'half', 'options' => $ratios],
            ],
        ],
        'text' => [
            'nestable' => true,
            'label' => 'Text', 'icon' => 'paragraph', 'group' => 'Inhalt', 'background' => 'plain',
            'help' => 'Freier Text mit Zwischenüberschriften, Listen und Links (Tailwind: Typography-Plugin „prose“).',
            'fields' => [
                ...$head(false, false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
            ],
        ],
        'accordion' => [
            'nestable' => true,
            'label' => 'Aufklappliste (FAQ)', 'icon' => 'question', 'group' => 'Inhalt', 'background' => 'plain',
            'help' => 'Tailwind: <details> ohne Skript · UIkit: uk-accordion. Im Bearbeiten-Modus sind alle Einträge offen.',
            'jsonld' => ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a'],
            'fields' => [
                ...$head(true),
                ['name' => 'items', 'label' => 'Einträge', 'type' => 'repeater', 'item_label' => 'Eintrag', 'title_field' => 'q', 'fields' => [
                    ['name' => 'q', 'label' => 'Frage / Titel', 'type' => 'text', 'required' => true, 'max' => 160],
                    ['name' => 'a', 'label' => 'Antwort / Inhalt', 'type' => 'richtext', 'required' => true],
                ]],
            ],
        ],
        'tabs' => [
            'label' => 'Reiter', 'icon' => 'folders', 'group' => 'Inhalt', 'background' => 'plain',
            'help' => 'Inhalte in Reitern – UIkit: uk-tab + uk-switcher, Tailwind: kleines Skript (ohne Skript stehen alle untereinander).',
            'fields' => [
                ...$head(false),
                ['name' => 'items', 'label' => 'Reiter', 'type' => 'repeater', 'item_label' => 'Reiter', 'title_field' => 'label', 'max_items' => 6, 'fields' => [
                    ['name' => 'label', 'label' => 'Beschriftung', 'type' => 'text', 'required' => true, 'max' => 40],
                    ['name' => 'text', 'label' => 'Inhalt', 'type' => 'richtext'],
                ]],
            ],
        ],
        'cta' => [
            'label' => 'Handlungsaufruf', 'icon' => 'megaphone', 'group' => 'Inhalt',
            'background' => 'band',
            'help' => 'Optional mit Dialog („Mehr erfahren“): UIkit uk-modal, Tailwind <dialog>.',
            'fields' => [
                ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true, 'max' => 110],
                ['name' => 'text', 'label' => 'Text (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ...$buttons,
                ['type' => 'heading', 'label' => 'Dialog (optional)'],
                ['name' => 'modal_label', 'label' => 'Knopf für den Dialog', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'modal_title', 'label' => 'Titel im Dialog', 'type' => 'text', 'max' => 80, 'width' => 'half'],
                ['name' => 'modal_text', 'label' => 'Text im Dialog', 'type' => 'richtext'],
            ],
        ],
        'contact' => [
            'nestable' => true,
            'label' => 'Kontakt (aus „Website“)', 'icon' => 'address-book', 'group' => 'Inhalt', 'background' => 'plain',
            'central' => 'Adresse, Telefon und E-Mail kommen aus „Website“.',
            'help' => 'Adresse, Telefon und E-Mail kommen zentral aus „Website“ – im Editor als „zentral gepflegt“ markiert.',
            'fields' => [
                ...$head(false),
            ],
        ],
        'video' => [
            'nestable' => true,
            'label' => 'Video', 'icon' => 'play-circle', 'group' => 'Medien', 'background' => 'plain',
            'help' => 'YouTube/Vimeo mit Zwei-Klick-Lösung (Kern-Fragment) oder eigene MP4-Datei.',
            'jsonld' => ['type' => 'video', 'url' => 'video_url', 'file' => 'video_file', 'poster' => 'poster', 'name' => 'title'],
            'fields' => [
                ...$head(false, false),
                ['name' => 'video_url', 'label' => 'YouTube- oder Vimeo-Link', 'type' => 'url', 'width' => 'half'],
                ['name' => 'video_file', 'label' => 'oder eigene MP4-Datei', 'type' => 'file', 'width' => 'half'],
                ['name' => 'poster', 'label' => 'Eigenes Vorschaubild (optional)', 'type' => 'media', 'width' => 'half'],
            ],
        ],
        // Kern-Blöcke kommen automatisch dazu: data_list (hier überschrieben: blocks/data_list.php → views/{fw}/data_list.php),
        // data_fields, data_form, map, gallery … Formular: Kern-Markup, Button-Klassen aus button_class, Farben über --dff-* (site.css).
    ],

    // ======================================================================================================== § 9 Daten
    // Datenliste: eigene Ausgabe für „Karten“ und „Liste“ mit Framework-Klassen; „Kompakt“ und „Tabelle“ nutzen den Kern.

    // ======================================================================================================== § 10 Startinhalte
    // seed.php → tools/demo-content.php (Seiten) und 'after' (Bilder, Datentabelle mit Detailseite, Glossar, Formular).

    // ======================================================================================================== § 11 Sprachen
    // lang/en.php (Verwaltung) · lang/site/en.php (feste Website-Texte, lt())
];
