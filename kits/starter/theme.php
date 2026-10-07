<?php
// SPDX-License-Identifier: MIT
/*
 * ============================================================================================================
 *  Start-Kit „starter“ für KLXM Studio – das kleinste vollständige Kit als Ausgangspunkt für eigene Kits.
 * ============================================================================================================
 *
 *  „Kit“ ist der Begriff in der Oberfläche; technisch ist ein Kit ein Theme (kits/{name}/theme.php, Klasse
 *  Core\Theme, Einstellung sys.theme). Diese Datei gibt EIN Array zurück – die Definition (definition) des Kits.
 *  Der Core liest sie bei jedem Aufruf; alles hier ist daher reine Konfiguration ohne Seiteneffekte.
 *
 *  Eigenes Kit anlegen (copy + rename):
 *      php bin/console kit:create meinkit                 # Kopie dieses Start-Kits, Präfix starter_* → meinkit_*
 *      cd tools && pnpm run build                         # assets/ → public/assets/kits/meinkit/
 *
 *  Abschnitte dieser Datei (suchen nach „§“):
 *      § 1  Meta          label, description, version, requires (Core-Version), source_lang
 *      § 2  Abschnitte    backgrounds, dark_backgrounds, container_class, button_class
 *      § 3  SEO & JSON-LD seo, jsonld (Organisation), link_keywords
 *      § 4  Projekt       project (Begriffe, Karte, Einrichtungs-Checks, MCP/KI-Hinweise), app (Icon/Manifest)
 *      § 5  Assets        fonts, image_ratios, conditional_css (CSS/JS nur auf Seiten, die sie brauchen)
 *      § 6  Design        design (Design-Tokens für den Style-Editor, Voreinstellungen, Navigationsvarianten, hell/dunkel)
 *      § 7  Website       settings (zentrales Formular „Website“: Name, Adresse, Kontakt, Recht, SEO …)
 *      § 8  Blöcke        blocks (Beispielblöcke: Felder, Varianten, JSON-LD) + Kern-Blöcke (Datenliste, Formular, Karte …)
 *      § 9  Daten         Datenlisten und Detailseiten (Kern-Blöcke data_list/data_fields, eigene Ausgabe möglich)
 *      § 10 Startinhalte  seed.php → tools/demo-content.php (eine Startseite, eine Unterseite)
 *      § 11 Sprachen      lang/en.php (Verwaltung) · lang/site/en.php (feste Website-Texte lt())
 *
 *  Regeln, die jedes Kit einhalten sollte (siehe README.md → Prüfliste):
 *      CSP-sicher (keine Inline-Skripte/-Styles), WCAG 2.2 AA, keine externen Anfragen, keine Cookies,
 *      Budget: CSS der Startseite < 30 KB, JS < 8 KB (minifiziert). Dieses Kit: siehe README.md → Budgets.
 */

require_once __DIR__ . '/functions.php';   // Helfer mit Präfix starter_* (kit:create benennt sie um)

// ------------------------------------------------------------------------------------------------------------
// Wiederverwendbare Feldgruppen (fields) – Feldtypen: text, textarea, richtext, inline, link, media, file, select,
// bool, number, url, email, tel, time, page, icon, repeater, geo, datatable, collection, heading (Zwischenüberschrift).
// Gemeinsame Optionen: name, label, type, required, default, max, width ('half'), help, placeholder, translate (false =
// gilt für alle Sprachen gleich), options (select), fields/item_label/title_field/max_items (repeater).
// ------------------------------------------------------------------------------------------------------------

/** Dachzeile + Überschrift (+ Einleitung) – steht in fast jedem Block, daher als Funktion */
$head = fn(bool $required = false, bool $intro = true) => array_merge([
    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
    ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'max' => 110, 'width' => 'half', 'required' => $required],
], $intro ? [['name' => 'intro', 'label' => 'Einleitung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 400]] : []);

/** Zwei Buttons – Link-Felder nehmen Seiten, #anker, https://… und die Sonderwerte aus 'link_keywords' (§ 3) */
$buttons = [
    ['name' => 'button_label', 'label' => 'Button 1 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button_link', 'label' => 'Button 1 – Link', 'type' => 'link', 'width' => 'half',
        'help' => 'Seite, #anker, https://… – „phone“ und „email“ nutzen die Angaben unter „Website“.'],
    ['name' => 'button2_label', 'label' => 'Button 2 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button2_link', 'label' => 'Button 2 – Link', 'type' => 'link', 'width' => 'half'],
];

$ratios = ['16:9' => 'Breitbild 16:9', '3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', '1:1' => 'Quadrat 1:1', '4:5' => 'Hochformat 4:5'];

return [

    // ======================================================================================================== § 1 Meta
    // label: Name in der Kit-Auswahl (Grundeinstellungen). Muss genau so eingerückt sein (4 Leerzeichen) – Core\Theme::available()
    // und kit:create lesen die Zeile per Muster, ohne die Datei auszuführen.
    'label' => 'Start-Kit',
    'description' => 'Das kleinste vollständige Kit: sechs kommentierte Beispielblöcke, Design-Tokens, hell und dunkel, mehrsprachig – der Ausgangspunkt für eigene Kits.',
    'description_en' => 'The smallest complete kit for your own development: six commented sample blocks, design tokens, light and dark, multilingual.',
    'category' => 'dev',   // Willkommen-Bildschirm: general (Allgemein) | branch (Branchen & Themen) | dev (Entwickler)
    'version' => '1.0.0',
    'requires' => '>=1.0.0',   // Core-Version (CMS_VERSION); passt sie nicht, warnt Verwaltung → System
    'source_lang' => 'de',     // Sprache der Quelltexte in lt() und __() – Übersetzungen in lang/ (§ 11)

    // ======================================================================================================== § 2 Abschnitte
    // Hintergründe, die Redakteure je Abschnitt wählen (Abschnitts-Optionen im Editor). Der Schlüssel wird zur Klasse
    // bg-{schlüssel} an <section> (Core\Block::sectionClass). Dunkle Hintergründe: helle Schrift, Rollen in site.css.
    'backgrounds' => ['white' => 'Standard', 'muted' => 'Getönt', 'accent' => 'Akzentfarbe', 'dark' => 'Dunkel'],
    'dark_backgrounds' => ['accent', 'dark'],
    // CSS-Klassen, die Kern-Blöcke (Datenliste, Formular, Karte, Kalender …) für Inhaltsbreite und Buttons verwenden
    'container_class' => 'wrap',
    'button_class' => 'btn btn--primary',
    // Einstellungsfelder mit Adressen, die in <iframe> geladen werden dürfen (CSP frame-src) – hier keine
    'frame_hosts' => [],

    // ======================================================================================================== § 3 SEO & JSON-LD
    // Welche Einstellungsfelder (§ 7) Titel, Beschreibung und Vorschaubild liefern (Core\Seo)
    'seo' => [
        'home_title' => 'site_title',
        'title_suffix' => 'site_title_suffix',
        'title_suffix_fallback' => 'org_name',
        'description' => 'default_meta_description',
        'og_image' => 'og_default_image',
    ],
    // schema.org: Der Core baut je Seite einen @graph (WebSite, WebPage, BreadcrumbList …). Die Organisation liefert
    // diese Funktion (functions.php). Blöcke ergänzen eigene Daten über 'jsonld' in der Blockdefinition (faq, video unten).
    'jsonld' => 'starter_jsonld',
    // Sonderwerte für Link-Felder: „phone“ → tel:…, „email“ → mailto:… (aufgelöst in starter_link())
    'link_keywords' => ['phone', 'email'],

    // ======================================================================================================== § 4 Projekt
    // Branchenspezifisches, damit der Core neutral bleibt. Helfer: project('map.location'), term('org'), site_name().
    'project' => [
        'terms' => ['org' => 'Organisation', 'site_name' => 'Name der Website'],   // sichtbare Begriffe in der Verwaltung
        'name_setting' => 'org_name',                                                 // Feld mit dem Namen (site_name())
        'brand' => ['short_name', 'tagline'],                                         // Zeile 1/2 oben in der Seitenleiste der Verwaltung
        // Karte (Kern-Block „Karte“, Maps): Standort, Beschriftung und Adresse kommen aus diesen Feldern
        'map' => ['location' => 'geo', 'label' => 'org_name', 'address' => ['street', 'zip', 'city']],
        // Einrichtungs-Checkliste im Dashboard
        'setup_checks' => [
            ['setting' => 'org_name', 'label' => 'Name eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'email', 'label' => 'E-Mail-Adresse eingetragen', 'link' => '/admin/settings#stammdaten'],
            ['setting' => 'imprint_page', 'label' => 'Impressum zugeordnet', 'link' => '/admin/settings#recht'],
        ],
        'dashboard_hint' => 'Name, Adresse, Kontakt und Logo pflegen Sie zentral unter „Website“. Farben, Schrift und Navigation: Verwaltung → Design.',
        'public_info' => 'starter_public_info',        // öffentliche Angaben für REST-API und MCP (Funktion)
        'notice' => ['text' => 'notice_text', 'active' => 'notice_active', 'example' => 'z. B. geänderte Erreichbarkeit'],
        'link_labels' => ['phone' => 'Telefon (aus „Website“)', 'email' => 'E-Mail (aus „Website“)'],
        'search_keywords' => 'kontakt adresse telefon e-mail logo farbe',
        // Hinweise für KI-Werkzeuge (MCP, Redaktions-Assistent). Optional auch 'mcp' => ['prompts' => […]] und
        // 'ai' => ['chat' => ['system' => '…']] für den Besucher-Chat.
        'mcp' => ['instructions' => 'Sachlich und kurz schreiben. Keine Fakten erfinden; Platzhalter in [eckigen Klammern] nur mit Angaben der Betreiber ersetzen.'],
        // Öffnungszeiten aktivieren (API/MCP, Kontakt): 'hours' => ['setting' => 'hours', 'format' => 'meinkit_time_range', 'label' => 'Öffnungszeiten']
    ],
    // App-Icon-Generator und Web-App-Manifest (Grundeinstellungen → App)
    'app' => [
        'defaults' => ['icon_text' => 'S', 'icon_bg' => '#1F5BC4', 'icon_fg' => '#FFFFFF', 'icon_dot' => '#FFFFFF', 'icon_dot_enabled' => false],
        'info' => 'starter_app_info',
    ],

    // ======================================================================================================== § 5 Assets
    // Schriften: Dieses Kit nutzt die Systemschrift (keine Datei, keine Anfrage). Eigene Schrift: in design.fonts (§ Design)
    // mit 'fontsource' => 'inter' erklären – der Schriften-Manager (Core\Fonts) installiert sie bei Kit-Wahl, Speichern im
    // Style-Editor und mit php bin/console fonts:sync; das Kit liefert keine Webfont-Dateien mit.
    // Nur Schriften, die es dort nicht gibt: selbst ausliefern (build.mjs + package.json, design.fonts → 'css') und hier
    // 'preload' => ['fonts/…woff2'] (relativ zu public/assets/kits/{kit}/).
    'fonts' => [
        // 'icon' => 'fonts/Inter_700Bold.ttf',                    // TTF für den App-Icon-Generator (serverseitig)
    ],
    // Bildformate mit eigenem Zuschnitt in der Mediathek; Blöcke übergeben das Format: img($id, $sizes, ['ratio' => '4:3'])
    'image_ratios' => $ratios,
    // Stylesheets/Skripte nur auf Seiten, die einen dieser Blocktypen enthalten (Typ oder „Typ:Variante“).
    // „@rich“ = die Seite gibt Rich-Text-Formatierungen aus (Core\Sanitizer::styled). site.css + site.js laden immer (layout.php).
    // Kern-Fragmente (app/Views/fragments, Core\Fragments): Video-Zwei-Klick, Editor und Werkzeugleiste kommen nur aus dem Kern
    // (Skript resources/js/embed.js lädt das Fragment selbst); Marke, Sprachumschalter, Öffnungszeiten … ebenso, solange das Kit
    // keine eigene Datei in fragments/ bzw. templates/partials/ mitbringt.
    'fragments' => ['video-embed' => ['play_class' => 'btn btn--primary', 'play_icon' => 'play-circle', 'empty' => 'none']],
    'conditional_css' => [
        'css/blocks.css' => ['text_image', 'cards', 'cta', 'faq', 'video', 'downloads'],
        // Kern-Blöcke: nur Variablen setzen, die neutralen Kern-Stylesheets bleiben (siehe css/core.css)
        'css/core.css' => ['data_list', 'data_fields', 'data_form', 'calendar', 'upcoming', 'dials', 'map', 'gallery', 'slideshow', 'stack_cards'],
        'css/hero-search.css' => ['hero:search'],   // nur die Variante „Such-Einstieg“ des Blocks hero (Typ:Variante)
    ],
    // Weitere Möglichkeiten (hier nicht genutzt):
    //   'core_blocks' => false       alle Kern-Blöcke abschalten (Datenliste, Formular, Karte, Galerie …)
    //   'rich_text' => [...]         Farbpalette der Formatierungsleiste (siehe Hilfe → Kits & Design → Rich-Text-Stile)
    //   'proxy' => [...]             weitere externe Quellen über den zentralen Proxy (Hilfe → Karten & Proxy)
    //   'forms' => [...]             Vorlagen für Eingangs-Tabellen (Anfrage-Formulare)
    //   docs/manual.php              eigene Kapitel im Handbuch der Redaktion

    // ======================================================================================================== § 6 Design
    // Design-Tokens für den Style-Editor (Verwaltung → Design). Jeder Token wird eine CSS-Variable ('var') oder eine
    // Klasse am <html> ('class'). Die Standardwerte ('default', 'dark') MÜSSEN mit assets/css/_tokens.css übereinstimmen:
    // Solange nichts geändert ist, liefert der Core keine zusätzliche Datei aus – dann gilt allein das Kit-CSS.
    // Im Layout: design_classes() am <html>, design_head() nach dem Kit-CSS. Einzelwert lesen: design('nav').
    // Kontrast: 'contrast' => ['with' => Token oder #Farbe, 'min' => 4.5] zeigt im Editor ein AA/AAA-Abzeichen;
    // alle Voreinstellungen prüft php kits/starter/tools/contrast.php.
    'design' => [
        'groups' => [
            ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
                ['name' => 'accent', 'label' => 'Akzentfarbe', 'type' => 'color', 'var' => '--s-a', 'default' => '#1F5BC4', 'dark' => '#8AB0F5',
                    'contrast' => ['with' => 'background', 'min' => 4.5], 'help' => 'Links, Buttons, Fokusrahmen – die eine Farbe des Kits.'],
                ['name' => 'on_accent', 'label' => 'Schrift auf Akzentfarbe', 'type' => 'color', 'var' => '--s-a-on', 'default' => '#FFFFFF', 'dark' => '#0B1428',
                    'contrast' => ['with' => 'accent', 'min' => 4.5]],
                ['name' => 'ink', 'label' => 'Überschriften', 'type' => 'color', 'var' => '--s-ink', 'default' => '#111418', 'dark' => '#F2F4F7',
                    'contrast' => ['with' => 'background', 'min' => 7]],
                ['name' => 'text', 'label' => 'Fließtext', 'type' => 'color', 'var' => '--s-text', 'default' => '#2B3038', 'dark' => '#D3D8E0',
                    'contrast' => ['with' => 'background', 'min' => 4.5]],
                ['name' => 'muted', 'label' => 'Nebentext', 'type' => 'color', 'var' => '--s-muted', 'default' => '#525A66', 'dark' => '#A3ACB8',
                    'contrast' => ['with' => 'surface', 'min' => 4.5]],
                ['name' => 'background', 'label' => 'Hintergrund', 'type' => 'color', 'var' => '--s-bg', 'default' => '#FFFFFF', 'dark' => '#121418'],
                ['name' => 'surface', 'label' => 'Getönte Fläche', 'type' => 'color', 'var' => '--s-surface', 'default' => '#F2F4F7', 'dark' => '#1B1E23',
                    'contrast' => ['with' => 'text', 'min' => 4.5], 'help' => 'Abschnitt „Getönt“, Karten, Formulare.'],
                ['name' => 'line', 'label' => 'Linien', 'type' => 'color', 'var' => '--s-line', 'default' => '#D9DEE5', 'dark' => '#2D323A'],
                ['name' => 'dark_section', 'label' => 'Abschnitt „Dunkel“', 'type' => 'color', 'var' => '--s-dark', 'default' => '#15181D', 'dark' => '#20242B',
                    'contrast' => ['with' => '#F2F4F7', 'min' => 7, 'dark_with' => '#F2F4F7']],
            ]],
            ['id' => 'typo', 'label' => 'Schrift', 'tokens' => [
                // Typ „font“: Auswahl aus 'fonts' unten + installierte Schriften (fonts:install); Ausgabe = Schrift-Stack
                ['name' => 'font_body', 'label' => 'Fließtext', 'type' => 'font', 'var' => '--s-font', 'default' => 'system'],
                ['name' => 'font_head', 'label' => 'Überschriften', 'type' => 'font', 'var' => '--s-font-head', 'default' => 'system'],
                ['name' => 'base_size', 'label' => 'Grundgröße (px)', 'type' => 'range', 'var' => '--s-fs', 'unit' => '', 'min' => 15, 'max' => 20, 'step' => 0.5, 'default' => 17,
                    'help' => 'Bezogen auf 16 px Browser-Standard – Besucher können weiter zoomen.'],
                ['name' => 'heading_weight', 'label' => 'Stärke der Überschriften', 'type' => 'range', 'var' => '--s-hw', 'unit' => '', 'min' => 400, 'max' => 800, 'step' => 100, 'default' => 700],
            ]],
            ['id' => 'form', 'label' => 'Form', 'tokens' => [
                ['name' => 'radius', 'label' => 'Eckenradius', 'type' => 'range', 'var' => '--s-radius', 'unit' => 'px', 'min' => 0, 'max' => 24, 'step' => 2, 'default' => 10],
                // Typ „choice“ mit 'values' → Variable; 'preview' => 'radius' zeigt Karten mit Ecke im Editor
                ['name' => 'buttons', 'label' => 'Buttons', 'type' => 'choice', 'var' => '--s-btn-r', 'preview' => 'radius', 'default' => 'rounded',
                    'options' => ['rounded' => 'Abgerundet', 'pill' => 'Rund', 'square' => 'Eckig'], 'values' => ['rounded' => '8px', 'pill' => '999px', 'square' => '0']],
                ['name' => 'width', 'label' => 'Maximale Inhaltsbreite (rem)', 'type' => 'range', 'var' => '--s-wrap', 'unit' => 'rem', 'min' => 56, 'max' => 88, 'step' => 4, 'default' => 72],
            ]],
            // Navigationsvarianten: Typ „choice“ mit 'class' → Klasse nav-{wert} am <html>; das CSS (site.css) ordnet den Kopf an.
            // 'preview' => 'nav' + 'thumbs' zeigt im Editor Skizzen (Schemata: left, center, split, burger …).
            ['id' => 'navigation', 'label' => 'Navigation', 'tokens' => [
                ['name' => 'nav', 'label' => 'Kopfbereich', 'type' => 'choice', 'class' => 'nav-{value}', 'preview' => 'nav', 'default' => 'bar',
                    'options' => ['bar' => 'Leiste – Marke links, Menü rechts', 'center' => 'Zentriert – Marke über dem Menü'],
                    'thumbs' => ['bar' => 'left', 'center' => 'center']],
                ['name' => 'sticky', 'label' => 'Kopfbereich beim Scrollen sichtbar halten', 'type' => 'bool', 'class' => 'has-sticky', 'default' => true],
            ]],
            // Kopfbereich-Aktionen (Core\HeaderActions): eine fertige Gruppe des Cores – Handlungsaufruf (gefüllt, Kontur, Textlink,
            // Symbol, geteilt, Chip), Suche (Lupe, Feld, Aufziehen, Befehlsfeld, Suchleiste), Kontakt-Chip, Sprachen, Social, Anmelden.
            // Das Kit nennt nur seine Standards; 'exclude' blendet Optionen aus, die es nicht braucht (hier: keine Öffnungszeiten).
            // Ausgabe im Kopf-Template: header_actions() – siehe templates/partials/header.php.
            \Core\HeaderActions::designGroup(['ha_cta_style' => 'solid', 'ha_cta' => 'contact', 'ha_search' => 'popover'], ['exclude' => ['ha_status']]),
            ['id' => 'modus', 'label' => 'Farbschema', 'tokens' => [
                ['name' => 'dark', 'label' => 'Dunkles Farbschema, wenn im Gerät der Besucher eingestellt', 'type' => 'bool', 'class' => 'has-dark', 'default' => true],
            ]],
        ],
        // Schriften für Tokens vom Typ „font“ – nur die gewählten werden eingebunden. Schrift aus dem Google-Fonts-Katalog
        // (installiert der Schriften-Manager, Fontsource-ID): 'inter' => ['label' => 'Inter', 'stack' => 'Inter,system-ui,sans-serif',
        //   'fontsource' => 'inter', 'styles' => ['normal', 'italic'], 'preload' => true]   (variabel; feste Stärken: 'variable' => false, 'weights' => [400, 700])
        // Nicht im Katalog: Datei mit dem Kit ausliefern, 'css' => 'css/font-x.css' (relativ zu public/assets/kits/{kit}/).
        'fonts' => [
            'system' => ['label' => 'Systemschrift (ohne Download)', 'stack' => 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'],
            'serif' => ['label' => 'System-Serifenschrift (ohne Download)', 'stack' => 'ui-serif,Georgia,Cambria,"Times New Roman",serif'],
        ],
        // Voreinstellungen (Karten im Style-Editor, füllen nur das Formular). „name@dark“ = Wert im dunklen Schema.
        'presets' => [
            'blau' => ['label' => 'Blau (Standard)', 'values' => [
                'accent' => '#1F5BC4', 'accent@dark' => '#8AB0F5', 'on_accent' => '#FFFFFF', 'on_accent@dark' => '#0B1428']],
            'petrol' => ['label' => 'Petrol', 'values' => [
                'accent' => '#0B6B63', 'accent@dark' => '#5CCFC3', 'on_accent' => '#FFFFFF', 'on_accent@dark' => '#04211E', 'buttons' => 'pill']],
            'terrakotta' => ['label' => 'Terrakotta, Serifen', 'values' => [
                'accent' => '#A8431C', 'accent@dark' => '#F29A72', 'on_accent' => '#FFFFFF', 'on_accent@dark' => '#2A0E03',
                'font_head' => 'serif', 'heading_weight' => 600, 'radius' => 2, 'buttons' => 'square', 'nav' => 'center']],
        ],
        // Wo die Dunkel-Werte gelten: Media-Query + Selektor. 'force' setzt die Vorschau im Editor („Dunkel“) an <html>.
        'dark' => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark', 'force' => 'is-dark'],
        // 'sample' => 'muster',   // optional: Pfad einer Musterseite für die Vorschau im Style-Editor
    ],

    // Kopfbereich-Aktionen: Klassen der Kit-Buttons für die Stile „Gefüllt“, „Kontur“, „Geteilt“ (sonst gestaltet der Core-Stil
    // /assets/css/header-actions*.css mit den Variablen --ha-* aus css/site.css). 'base_css' => false: bei schlichter Auswahl
    // (Kit-Button + Lupe) lädt keine Kern-Datei – .ha{display:flex} und 8-px-Ecken stehen in css/site.css. Eigenes Markup:
    // templates/partials/header-actions.php.
    'header_actions' => ['late' => true, 'base_css' => false, 'classes' => ['solid' => 'btn btn--primary', 'outline' => 'btn btn--secondary', 'split' => 'btn btn--primary']],

    // ======================================================================================================== § 7 Website
    // Das zentrale Formular „Website“ (Verwaltung → Website): Werte liest das Kit mit setting('name'). Gruppen werden
    // Reiter; die 'id' ist der Anker (/admin/settings#stammdaten). Standardwerte registriert der Core automatisch.
    'settings' => [
        'title' => 'Website',
        'groups' => [
            ['id' => 'stammdaten', 'label' => 'Stammdaten', 'fields' => [
                ['name' => 'org_name', 'label' => 'Name (Unternehmen, Verein, Büro …)', 'type' => 'text', 'required' => true],
                ['name' => 'short_name', 'label' => 'Kurzname / Wortmarke', 'type' => 'text', 'max' => 32, 'width' => 'half',
                    'help' => 'Steht im Kopf, solange kein Logo hinterlegt ist. Leer = Name.'],
                ['name' => 'tagline', 'label' => 'Kurzbeschreibung', 'type' => 'text', 'max' => 90, 'width' => 'half'],
                ['type' => 'heading', 'label' => 'Adresse und Kontakt'],
                ['name' => 'street', 'label' => 'Straße und Hausnummer', 'type' => 'text', 'translate' => false],
                ['name' => 'zip', 'label' => 'PLZ', 'type' => 'text', 'width' => 'half', 'translate' => false],
                ['name' => 'city', 'label' => 'Ort', 'type' => 'text', 'width' => 'half'],
                ['name' => 'phone', 'label' => 'Telefon', 'type' => 'tel', 'width' => 'half', 'translate' => false],
                ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'width' => 'half', 'translate' => false],
                ['name' => 'geo', 'label' => 'Standort auf der Karte', 'type' => 'geo', 'address_fields' => ['street', 'zip', 'city'], 'translate' => false,
                    'help' => 'Für den Block „Karte“. Leer = keine Karte. Die Karte lädt über den eigenen Server – ohne Cookies.'],
            ]],
            ['id' => 'darstellung', 'label' => 'Darstellung', 'fields' => [
                ['name' => 'logo', 'label' => 'Logo (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'SVG oder PNG mit transparentem Hintergrund, ca. 40 px hoch dargestellt.'],
                ['name' => 'logo_dark', 'label' => 'Logo für dunkles Farbschema (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false],
                ['name' => 'footer_text', 'label' => 'Text im Fußbereich', 'type' => 'textarea', 'rows' => 2, 'max' => 240],
                ['name' => 'social', 'label' => 'Social-Media-Profile', 'type' => 'repeater', 'item_label' => 'Profil', 'title_field' => 'label', 'translate' => false,
                    'help' => 'Erscheinen im Fußbereich als einfache Links – ohne eingebettete Inhalte oder Tracking.',
                    'fields' => [
                        ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'width' => 'half', 'placeholder' => 'z. B. Mastodon'],
                        ['name' => 'url', 'label' => 'Adresse', 'type' => 'url', 'required' => true, 'width' => 'half'],
                    ]],
                // Kopfbereich-Aktionen: Beschriftungen und Links (Handlungsaufruf „Eigene“, zweite Aktion, Anmelden)
                ...\Core\HeaderActions::settingsFields(),
            ]],
            ['id' => 'hinweis', 'label' => 'Hinweisbalken', 'fields' => [
                ['name' => 'notice_active', 'label' => 'Hinweis oben auf jeder Seite anzeigen', 'type' => 'bool', 'default' => false, 'translate' => false],
                ['name' => 'notice_text', 'label' => 'Hinweis', 'type' => 'inline', 'help' => 'Kurz halten. Links sind erlaubt.'],
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
                    'options' => ['Organization' => 'Organisation allgemein', 'LocalBusiness' => 'Lokales Geschäft / Betrieb', 'NGO' => 'Verein / gemeinnützige Organisation']],
            ]],
        ],
    ],

    // ======================================================================================================== § 8 Blöcke
    // Blocktyp => Definition. Renderer: blocks/{typ}.php mit $b (Core\Block) und $d (Daten inkl. Standardwerten).
    // Der Block erscheint automatisch im Editor (Formular, Live-Vorschau, Abschnitts-Optionen), in REST-API und MCP.
    //   label, icon (Phosphor-Name, siehe Verwaltung → Symbole), group (Einfügen-Menü), help (Hinweis im Editor),
    //   variants (erste = Standard; Klasse v-{variante} am Abschnitt, $b->variant()), background (Standard-Hintergrund),
    //   central (Hinweis „kommt aus Website“), jsonld (schema.org), fields (siehe oben).
    // Tipp: Wer Websites zwischen Kits wechseln lassen will, verwendet dieselben Typ- und Feldnamen wie das Ziel-Kit.
    'blocks' => [
        'hero' => [
            'label' => 'Einstieg (Hero)', 'icon' => 'star', 'group' => 'Kopf',
            'help' => 'Große Überschrift der Seite (H1) – pro Seite genau einmal, als erster Block.',
            // Beispiel: eine Variante hinzufügen (hier „search“) – vier Stellen:
            //   1. Schlüssel + Bezeichnung in 'variants' (erscheint als Auswahl „Variante“ in der Seitenleiste)
            //   2. Felder nur für diese Variante: 'variants' => ['search'] am Feld (Seitenleiste blendet sie sonst aus);
            //      fertige Feldgruppen liefert Core\Blocks\Hero::fields() – search, video, figures, dates, form, map, compare …
            //   3. Ausgabe in blocks/hero.php (if ($b->variant() === 'search') …)
            //   4. CSS nur für diese Variante: conditional_css → 'css/hero-search.css' => ['hero:search'] (§ 5)
            // Bestehende Varianten und Inhalte bleiben unverändert – neue Felder sind optional.
            'variants' => ['center' => 'Zentriert', 'split' => 'Text und Bild nebeneinander', 'search' => 'Such-Einstieg (Suchfeld mit Vorschlägen)'],
            // Wann welche Variante? Erscheint im Handbuch der Redaktion (Alle Blöcke → Einstieg).
            'variant_help' => [
                'center' => 'Kurze Botschaft ohne Bild, z. B. für Unterseiten.',
                'split' => 'Startseite mit einem aussagekräftigen Bild.',
                'search' => 'Viele Inhalte, Besucher wissen, was sie suchen (Verwaltung, Verein, Service).',
            ],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift (H1)', 'type' => 'text', 'required' => true, 'max' => 110],
                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 360],
                ...$buttons,
                ['name' => 'image', 'label' => 'Bild (bei „Text und Bild“)', 'type' => 'media', 'width' => 'half', 'variants' => ['split']],
                // Felder der Variante „search“: Beschriftung, Platzhalter, Vorschläge (je mit 'variants' => ['search'])
                ...\Core\Blocks\Hero::fields('search', ['search']),
            ],
        ],
        'text' => [
            'nestable' => true,   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Text', 'icon' => 'paragraph', 'group' => 'Inhalt',
            'help' => 'Freier Text mit Zwischenüberschriften, Listen, Zitaten und Links.',
            'fields' => [
                ...$head(false, false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
            ],
        ],
        'text_image' => [
            'label' => 'Text + Bild', 'icon' => 'square-half', 'group' => 'Inhalt',
            'variants' => ['right' => 'Bild rechts', 'left' => 'Bild links'],
            'fields' => [
                ...$head(true, false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'button_label', 'label' => 'Button – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'button_link', 'label' => 'Button – Link', 'type' => 'link', 'width' => 'half'],
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half', 'help' => 'Mindestens 1200 px breit.'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:3', 'width' => 'half', 'options' => $ratios],
            ],
        ],
        'cards' => [
            'label' => 'Karten', 'icon' => 'squares-four', 'group' => 'Inhalt',
            'help' => 'Leistungen, Angebote oder Themen als Raster – so viele Spalten, wie Platz ist.',
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Karten', 'type' => 'repeater', 'item_label' => 'Eintrag', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'icon', 'label' => 'Symbol', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'oder Bild (3:2)', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 300],
                    ['name' => 'link', 'label' => 'Link (optional)', 'type' => 'link'],
                ]],
            ],
        ],
        'cta' => [
            'label' => 'Handlungsaufruf', 'icon' => 'megaphone', 'group' => 'Inhalt',
            'background' => 'accent',   // Standard-Hintergrund beim Einfügen
            'fields' => [
                ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true, 'max' => 110],
                ['name' => 'text', 'label' => 'Text (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ...$buttons,
            ],
        ],
        'faq' => [
            'nestable' => true,   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Fragen & Antworten', 'icon' => 'question', 'group' => 'Inhalt',
            'help' => 'Aufklappbare Fragen (ohne JavaScript). Suchmaschinen erhalten sie als strukturierte Daten (FAQPage).',
            // JSON-LD je Block: Der Core (Core\StructuredData) baut daraus FAQPage – ohne eigenen Code
            'jsonld' => ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a'],
            'fields' => [
                ...$head(true),
                ['name' => 'items', 'label' => 'Fragen', 'type' => 'repeater', 'item_label' => 'Frage', 'title_field' => 'q', 'fields' => [
                    ['name' => 'q', 'label' => 'Frage', 'type' => 'text', 'required' => true, 'max' => 160],
                    ['name' => 'a', 'label' => 'Antwort', 'type' => 'richtext', 'required' => true],
                ]],
            ],
        ],
        // --- Medien: dünne Hüllen um Kern-Helfer (Core\Embeds, Core\MediaTracks, Core\Media) ---------------------------
        'video' => [
            'nestable' => true,   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Video', 'icon' => 'play-circle', 'group' => 'Medien',
            'help' => 'YouTube/Vimeo mit Zwei-Klick-Lösung (Vorschaubild vom eigenen Server, keine Daten an Dritte bis zum Klick) oder eigene MP4-Datei mit Untertiteln.',
            'jsonld' => ['type' => 'video', 'url' => 'video_url', 'file' => 'video_file', 'poster' => 'poster', 'name' => 'title'],   // VideoObject (nur mit Vorschaubild)
            'fields' => [
                ...$head(false, false),
                ['name' => 'video_url', 'label' => 'YouTube- oder Vimeo-Link', 'type' => 'url', 'width' => 'half'],
                ['name' => 'video_file', 'label' => 'oder eigene MP4-Datei', 'type' => 'file', 'width' => 'half',
                    'help' => 'Untertitel und Transkript pflegen Sie in der Mediathek an der Datei.'],
                ['name' => 'poster', 'label' => 'Eigenes Vorschaubild (optional)', 'type' => 'media', 'width' => 'half'],
            ],
        ],
        'downloads' => [
            'nestable' => true,   // Block „Layout“: in einer Spalte erlaubt
            'label' => 'Downloads', 'icon' => 'download-simple', 'group' => 'Medien',
            'help' => 'Dateien zum Herunterladen; PDFs lassen sich zusätzlich im Browser ansehen (PDF.js vom eigenen Server).',
            'fields' => [
                ...$head(false, false),
                ['name' => 'files', 'label' => 'Dateien', 'type' => 'repeater', 'item_label' => 'Datei', 'title_field' => 'label', 'fields' => [
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'width' => 'half', 'help' => 'Leer = Titel der Datei'],
                    ['name' => 'file', 'label' => 'Datei', 'type' => 'file', 'width' => 'half'],
                ]],
            ],
        ],
        // Kern-Blöcke kommen automatisch dazu (app/Blocks/blocks.php): data_list, data_fields, data_form, calendar, upcoming,
        // map, gallery, slideshow, stack_cards, dials. Eigene Ausgabe: gleichen Typ hier definieren bzw. blocks/{typ}.php anlegen.
    ],

    // ======================================================================================================== § 9 Daten
    // Datenlisten und Detailseiten sind Kern-Funktionen – das Kit muss dafür nichts definieren:
    //   • Block „Datenliste“ (data_list) zeigt Einträge einer Datentabelle als Karten/Liste/Tabelle.
    //   • Detailseite: In der Tabelle „Detailseite“ einschalten → der Core legt eine Vorlagen-Seite an; darin Blöcke wie
    //     „Datensatz-Felder“ (data_fields) oder eigene Blöcke mit Platzhaltern {{feld}} bzw. „@feld“ in Bildfeldern.
    //   • Aussehen: neutral aus dem Kern (public/assets/css/data.css); dieses Kit setzt nur Variablen (css/core.css).
    //     Komplett eigenes CSS: assets/css/data.css anlegen (ersetzt die Kern-Datei; @import "../../../../resources/css/data.css"
    //     übernimmt sie als Grundlage). Eigenes Markup: blocks/data_list.php (Vorlage: app/Blocks/data_list.php).

    // ======================================================================================================== § 10 Startinhalte
    // seed.php wird beim ersten Aufruf einer neuen Website eingespielt (Core\Seeder): 'settings', 'pages', 'page_refs'
    // und optional 'after' => callable für weitere Inhalte (Datentabellen, Medien). Hier: tools/demo-content.php.

    // ======================================================================================================== § 11 Sprachen
    // Quelltexte sind Deutsch (source_lang). Englisch: lang/en.php (Beschriftungen aus dieser Datei, Verwaltung) und
    // lang/site/en.php (feste Website-Texte in Templates: lt('Zum Inhalt springen')). Fehlende finden:
    //   php bin/console i18n:missing en --site-texts --site=<website>
];
