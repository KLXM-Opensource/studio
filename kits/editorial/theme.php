<?php
/*
 * Theme „editorial“ – typografisches Theme für inhaltsstarke Websites von KLXM Studio
 * (Verband mit Vereinen, Kulturhaus, Stadtmagazin, Bildungsträger, Stiftung).
 *
 * Starke Redaktions-Typografie (Serife für Schlagzeilen, humanistische Grotesk für Text, Mono für Auszeichnungen),
 * Titelkopf mit Datumszeile, Aufmacher, Nachrichtenstrom, „Kurz notiert“, Artikel mit Inhaltsverzeichnis und Lesefortschritt,
 * Nachtausgabe (dunkles Schema). Vollständig über Design-Tokens gesteuert (Verwaltung → Design, design.php).
 *
 * Blocktypen und Feldnamen sind mit dem Theme „basis“ verträglich (hero, richtext, text_image, features, stats, quote, faq,
 * cta, video, downloads, logos, contact, map): Websites können ohne Inhaltsverlust zwischen beiden Themes wechseln.
 * Eigenes Kundenprojekt: php bin/console theme:create kunde editorial  (Präfix editorial_* → kunde_*)
 */

require_once __DIR__ . '/functions.php';

// Wiederkehrende Felder: Dachzeile, Überschrift, Einleitung
$head = fn(bool $required = false, bool $intro = true) => array_merge([
    ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
    ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'max' => 120, 'width' => 'half', 'required' => $required],
], $intro ? [['name' => 'intro', 'label' => 'Einleitung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 400]] : []);

$buttons = [
    ['name' => 'button_label', 'label' => 'Button 1 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button_link', 'label' => 'Button 1 – Link', 'type' => 'link', 'width' => 'half',
        'help' => 'Seite, #anker, https://… – Sonderwerte „phone“ und „email“ nutzen „Website“.'],
    ['name' => 'button2_label', 'label' => 'Button 2 – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
    ['name' => 'button2_link', 'label' => 'Button 2 – Link', 'type' => 'link', 'width' => 'half'],
];

$ratios = ['3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', '16:9' => 'Breitbild 16:9', '21:9' => 'Kino 21:9', '1:1' => 'Quadrat 1:1', '4:5' => 'Hochformat 4:5', '3:4' => 'Hochformat 3:4'];
$columns = ['2' => '2 Spalten', '3' => '3 Spalten', '4' => '4 Spalten'];
$days = ['1' => 'Montag', '2' => 'Dienstag', '3' => 'Mittwoch', '4' => 'Donnerstag', '5' => 'Freitag', '6' => 'Samstag', '0' => 'Sonntag'];

// Kern-Block „Datenliste“ mit redaktionellen Darstellungen (Renderer: blocks/data_list.php; Stil: css/data.css)
$core = require ROOT . '/app/Blocks/blocks.php';
$dataList = $core['data_list'];
$dataList['help'] = 'Einträge einer Datentabelle (z. B. Aktuelles, Termine, Personen) – als Aufmacher mit Raster, Nachrichtenstrom, „Kurz notiert“, Terminliste, Personen, Karten oder Tabelle. Besucher können nach Rubrik filtern.';
foreach ($dataList['fields'] as $i => $f) {
    if (($f['name'] ?? '') === 'layout') {
        $dataList['fields'][$i]['default'] = 'lead';
        $dataList['fields'][$i]['options'] = [
            'lead' => 'Aufmacher + Raster (erster Eintrag groß)', 'river' => 'Nachrichtenstrom (Datum, Titel, Vorspann, Bild)',
            'brief' => 'Kurz notiert (kompakt, ohne Bilder)', 'archive' => 'Archiv (nach Monaten gegliedert)',
            'agenda' => 'Termine (großes Datum)', 'people' => 'Personen (Porträts)',
            'cards' => 'Karten', 'list' => 'Liste mit Bild', 'compact' => 'Kompakte Liste', 'table' => 'Tabelle',
        ];
    }
    if (($f['name'] ?? '') === 'ratio') $dataList['fields'][$i]['options'] += ['3:2' => '3:2', '4:5' => '4:5'];
}
array_splice($dataList['fields'], (int) array_search('link_detail', array_column($dataList['fields'], 'name'), true) - 1, 0, [
    ['type' => 'heading', 'label' => 'Redaktion'],
    ['name' => 'filter_nav', 'label' => 'Besucher filtern nach …', 'type' => 'datafield', 'width' => 'half', 'empty_label' => '– keine Rubrik-Leiste –',
        'help' => 'Auswahlfeld der Tabelle (z. B. Rubrik): zeigt Links je Rubrik über der Liste – funktioniert ohne JavaScript.'],
    ['name' => 'show_origin', 'label' => 'Herkunft zeigen (geteilte Tabellen: „von Verein …“)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
    ['name' => 'skip', 'label' => 'Die ersten … Einträge überspringen', 'type' => 'number', 'default' => 0, 'width' => 'half',
        'help' => 'Z. B. 4 für „Weitere Meldungen“ unter einem Aufmacher mit vier Einträgen derselben Tabelle.'],
]);

return [
    'label' => 'Editorial (Magazin & Verband)',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',
    'source_lang' => 'de',

    // Schlüssel wie im Theme „basis“ (Wechsel ohne Verlust), Beschriftung redaktionell
    'backgrounds' => ['white' => 'Papier', 'muted' => 'Getönt', 'accent' => 'Akzentfarbe', 'dark' => 'Nachtausgabe (dunkel)'],
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
    'jsonld' => 'editorial_jsonld',

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
        'dashboard_hint' => 'Name, Adresse, Ausgabe und Logo werden zentral gepflegt und erscheinen automatisch im Titelkopf, im Fußbereich und in Suchmaschinen. Schriften, Farben, Kopfbereich und Raster: Verwaltung → Design.',
        'public_info' => 'editorial_public_info',
        'hours' => ['setting' => 'hours', 'format' => 'editorial_time_range', 'label' => 'Geschäftszeiten'],
        'notice' => ['text' => 'notice_text', 'active' => 'notice_active', 'example' => 'z. B. Eilmeldung, geänderte Geschäftszeiten, Veranstaltung abgesagt'],
        'link_labels' => ['phone' => 'Telefon (aus „Website“)', 'email' => 'E-Mail (aus „Website“)'],
        'search_keywords' => 'kontakt adresse ausgabe titelkopf logo farbe schrift',
        'mcp' => [
            'instructions' => 'Redaktionelle Website (Nachrichten, Termine, Personen, Magazin). Erfinde keine Fakten, Zitate, Namen oder Zahlen; Dachzeilen kurz (1–3 Wörter), Schlagzeilen konkret, Vorspann 1–2 Sätze. Platzhalter in [eckigen Klammern] nur mit Angaben der Redaktion ersetzen.',
        ],
    ],

    // Schriften: Auswahl im Style-Editor (design.fonts); Vorladen übernimmt editorial_font_preloads() je nach Auswahl
    'fonts' => [
        'icon' => 'fonts/PlayfairDisplay_900Black.ttf',
    ],
    'app' => [
        'defaults' => ['icon_text' => 'E', 'icon_bg' => '#111111', 'icon_fg' => '#FBFAF7', 'icon_dot' => '#B42318', 'icon_dot_enabled' => true],
        'info' => 'editorial_app_info',
    ],
    'image_ratios' => [
        '3:2' => 'Querformat 3:2', '16:9' => 'Breitbild 16:9', '21:9' => 'Kino 21:9', '4:3' => 'Querformat 4:3', '1:1' => 'Quadrat 1:1', '4:5' => 'Hochformat 4:5', '3:4' => 'Hochformat 3:4',
    ],

    // Nur geladen, wo die Blöcke stehen (Kern ergänzt data.css, media.css, calendar.css, dataform.css, sections.css).
    // „typ:variante“ ist erlaubt (z. B. Handlungsaufruf als Newsletter mit Formular).
    // Kern-Fragmente (app/Views/fragments, Core\Fragments): Video-Zwei-Klick, Editor und Werkzeugleiste kommen nur aus dem Kern
    // (Skript resources/js/embed.js lädt das Fragment selbst); Marke, Sprachumschalter, Öffnungszeiten … ebenso, solange das Kit
    // keine eigene Datei in fragments/ bzw. templates/partials/ mitbringt.
    'fragments' => ['brand' => ['mark' => 'none', 'logo_width' => '320px'], 'langswitch' => ['role_list' => false]],
    'conditional_css' => [
        'css/hero.css' => ['hero'],
        'css/hero-x.css' => ['hero:issue', 'hero:agenda', 'hero:voice'],   // Titelgeschichte, Aktuell/Termine, Stimme
        'css/rich.css' => ['@rich'],   // Rich-Text-Stile (t-lead, t-small, t-note, c-*, mark) – nur wenn die Seite sie ausgibt (Core\Sanitizer::styled)
        'css/story.css' => ['richtext', 'figure', 'quote', 'text_image', 'article', 'faq', 'video', 'data_fields'],
        'css/blocks.css' => ['features', 'stats', 'faq', 'cta', 'logos', 'downloads', 'contact', 'video', 'map'],
        'css/data.css' => ['teasers'],
        'css/dataform.css' => ['cta:newsletter'],
        'js/blocks.js' => ['article', 'richtext'],
    ],

    // ------------------------------------------------------------ Design (Style-Editor: Verwaltung → Design)
    'design' => require __DIR__ . '/design.php',

    // Kopfbereich-Aktionen (header_actions()): Button-Klassen des Kits für die Stile „Gefüllt“, „Kontur“ und „Geteilt“
    'header_actions' => ['late' => true, 'classes' => ['solid' => 'btn btn--primary btn--small', 'outline' => 'btn btn--ghost btn--small', 'split' => 'btn btn--primary btn--small']],

    // ------------------------------------------------------------ Website
    'settings' => [
        'title' => 'Website',
        'groups' => [
            ['id' => 'stammdaten', 'label' => 'Stammdaten', 'fields' => [
                ['name' => 'org_name', 'label' => 'Name (Verband, Verein, Haus, Redaktion …)', 'type' => 'text', 'required' => true,
                    'help' => 'Vollständiger Name – erscheint im Titelkopf, im Fußbereich und für Suchmaschinen.'],
                ['name' => 'short_name', 'label' => 'Kurzname / Wortmarke', 'type' => 'text', 'max' => 32, 'width' => 'half',
                    'help' => 'Für die Wortmarke, solange kein Logo hinterlegt ist. Leer = Name.'],
                ['name' => 'tagline', 'label' => 'Unterzeile (Claim)', 'type' => 'text', 'max' => 90, 'width' => 'half',
                    'help' => 'Steht unter dem Titelkopf, z. B. „Nachrichten aus 42 Vereinen“.'],
                ['type' => 'heading', 'label' => 'Adresse'],
                ['name' => 'street', 'label' => 'Straße und Hausnummer', 'type' => 'text', 'translate' => false],
                ['name' => 'zip', 'label' => 'PLZ', 'type' => 'text', 'width' => 'half', 'translate' => false],
                ['name' => 'city', 'label' => 'Ort', 'type' => 'text', 'width' => 'half'],
                ['name' => 'country', 'label' => 'Land (optional)', 'type' => 'text', 'width' => 'half'],
                ['type' => 'heading', 'label' => 'Kontakt'],
                ['name' => 'phone', 'label' => 'Telefon', 'type' => 'tel', 'width' => 'half', 'translate' => false,
                    'help' => 'So eingeben, wie es angezeigt werden soll – der Wähl-Link wird automatisch erzeugt.'],
                ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'width' => 'half', 'translate' => false],
                ['type' => 'heading', 'label' => 'Geschäftszeiten (optional)', 'help' => 'Leer lassen, wenn keine Zeiten angezeigt werden sollen.'],
                ['name' => 'hours', 'label' => 'Geschäftszeiten', 'type' => 'repeater', 'item_label' => 'Zeitraum', 'title_field' => 'tag', 'translate' => false,
                    'help' => 'Ein Eintrag je Wochentag; gleiche Zeiten an Folgetagen werden zusammengefasst.',
                    'fields' => [
                        ['name' => 'tag', 'label' => 'Wochentag', 'type' => 'select', 'required' => true, 'options' => $days, 'width' => 'half'],
                        ['name' => 'notiz', 'label' => 'Notiz (z. B. „nach Vereinbarung“)', 'type' => 'text', 'width' => 'half'],
                        ['name' => 'von', 'label' => 'Von', 'type' => 'time', 'width' => 'half'],
                        ['name' => 'bis', 'label' => 'Bis', 'type' => 'time', 'width' => 'half'],
                        ['name' => 'pause_von', 'label' => 'Pause von', 'type' => 'time', 'width' => 'half'],
                        ['name' => 'pause_bis', 'label' => 'Pause bis', 'type' => 'time', 'width' => 'half'],
                    ]],
                ['name' => 'hours_note', 'label' => 'Hinweis unter den Zeiten', 'type' => 'text'],
                ['type' => 'heading', 'label' => 'Karte'],
                ['name' => 'geo', 'label' => 'Standort auf der Karte', 'type' => 'geo', 'address_fields' => ['street', 'zip', 'city'], 'translate' => false,
                    'help' => 'Aus der Adresse ermitteln oder in die Karte klicken. Leer = keine Karte. Die Karte lädt über den eigenen Server – ohne Einwilligung, ohne Cookies.'],
            ]],
            ['id' => 'darstellung', 'label' => 'Darstellung', 'fields' => [
                ['name' => 'edition', 'label' => 'Ausgabe (Datumszeile)', 'type' => 'text', 'max' => 48, 'width' => 'half',
                    'help' => 'Z. B. „Ausgabe Herbst 2026“ oder „Nr. 38“. Erscheint neben dem Datum (Design → Datumszeile).'],
                ['name' => 'footer_text', 'label' => 'Text im Fußbereich', 'type' => 'textarea', 'rows' => 2, 'max' => 240, 'width' => 'half'],
                ['name' => 'logo', 'label' => 'Logo (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'Ohne Logo wird der Name als Wortmarke in der Überschriftenschrift gesetzt – das passt zu diesem Kit meist am besten.'],
                ['name' => 'logo_dark', 'label' => 'Logo für die Nachtausgabe (optional)', 'type' => 'media', 'width' => 'half', 'translate' => false,
                    'help' => 'Helle Fassung des Logos. Leer = normales Logo.'],
                // Kopfbereich-Aktionen (Core\HeaderActions): Handlungsaufruf, zweite Aktion, Status-Bezeichnung, Anmelden
                ...\Core\HeaderActions::settingsFields(),
            ]],
            ['id' => 'hinweis', 'label' => 'Hinweisbalken', 'fields' => [
                ['name' => 'notice_active', 'label' => 'Hinweis oben auf jeder Seite anzeigen', 'type' => 'bool', 'default' => false, 'translate' => false],
                ['name' => 'notice_text', 'label' => 'Hinweis', 'type' => 'inline', 'help' => 'Kurz halten, z. B. Eilmeldung oder abgesagte Veranstaltung. Links sind erlaubt.'],
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
                ['name' => 'schema_type', 'label' => 'Art der Organisation (strukturierte Daten)', 'type' => 'select', 'required' => true, 'default' => 'NGO', 'translate' => false,
                    'options' => ['NGO' => 'Verband / Verein / gemeinnützig', 'Organization' => 'Organisation allgemein', 'NewsMediaOrganization' => 'Redaktion / Magazin',
                        'EducationalOrganization' => 'Bildungsträger / Hochschule', 'PerformingArtsTheater' => 'Kulturhaus / Bühne', 'LocalBusiness' => 'Lokales Geschäft']],
            ]],
        ],
    ],

    // ------------------------------------------------------------ Blöcke
    'blocks' => [
        'hero' => [
            'label' => 'Aufmacher / Seitenkopf', 'icon' => 'newspaper', 'group' => 'Kopf',
            'help' => 'Große Schlagzeile der Seite (H1). Pro Seite genau einmal – „Ressortkopf“ für Rubrikseiten (zeigt Unterseiten als Reiter).',
            'variants' => ['split' => 'Aufmacher – Text und Bild (Raster aus dem Design)', 'centered' => 'Plakat – Schlagzeile zentriert, Bild breit darunter',
                'cover' => 'Titelbild – Bild vollflächig, Text darauf', 'compact' => 'Ressortkopf – ohne Bild, Unterseiten als Reiter',
                'issue' => 'Titelgeschichte – Ausgabe, Hochformat-Bild, „Außerdem in dieser Ausgabe“', 'agenda' => 'Aktuell – Schlagzeile und die nächsten Termine in Spalten',
                'voice' => 'Stimme – großes Zitat mit Initialen, optional Bewertung'],
            // Wann welche Variante? (Handbuch → Alle Blöcke → Aufmacher)
            'variant_help' => [
                'split' => 'Startseite oder Artikel mit einem starken Bild – Text und Bild im Seitenraster aus dem Design.',
                'centered' => 'Kurze, starke Schlagzeile wie auf einem Plakat; das Bild steht breit darunter.',
                'cover' => 'Große Geschichte mit ausdrucksstarkem Bild, das die ganze Breite füllt – Text auf dunkler Verlaufsfläche.',
                'compact' => 'Rubrik- und Unterseiten: große Ressort-Überschrift, Unterseiten als Reiter.',
                'issue' => 'Magazin-Startseite oder neue Ausgabe: Titelgeschichte mit Ausgabe-Zeile und zwei, drei Anrissen weiterer Beiträge.',
                'agenda' => 'Verband, Kulturhaus, Verein mit vielen Terminen oder Meldungen: Das Nächste steht sofort oben, aktuell aus der Datentabelle.',
                'voice' => 'Mitglieder, Publikum oder Partner kommen zu Wort: ein Zitat als Einstieg, z. B. für Mitmachen, Spenden oder Ehrenamt.',
            ],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (Rubrik)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
                ['name' => 'byline', 'label' => 'Autorzeile (optional)', 'type' => 'text', 'max' => 90, 'width' => 'half', 'placeholder' => 'z. B. Text: Kim Beispiel · 6 Min.',
                    'variants' => ['split', 'centered', 'cover', 'issue', 'agenda']],
                ['name' => 'title', 'label' => 'Schlagzeile (H1)', 'type' => 'text', 'required' => true, 'max' => 120],
                ['name' => 'text', 'label' => 'Vorspann', 'type' => 'textarea', 'rows' => 3, 'max' => 360],
                ...$buttons,
                ['name' => 'image', 'label' => 'Bild (optional)', 'type' => 'media', 'width' => 'half', 'help' => 'Min. 1600 px breit. Alt-Text in der Mediathek pflegen.',
                    'variants' => ['split', 'centered', 'cover', 'issue']],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '3:2', 'width' => 'half', 'options' => $ratios,
                    'help' => '„Plakat“ zeigt das Bild immer im Kinoformat 21:9, „Titelbild“ bildschirmfüllend.', 'variants' => ['split']],
                ['name' => 'caption', 'label' => 'Bildunterschrift (optional)', 'type' => 'text', 'max' => 200,
                    'help' => 'Der Bildnachweis kommt automatisch aus der Mediathek („Urheber / Nachweis“).', 'variants' => ['split', 'centered', 'cover', 'issue']],
                ['name' => 'subnav', 'label' => 'Unterseiten als Reiter zeigen (Ressortkopf)', 'type' => 'bool', 'default' => true, 'variants' => ['compact']],
                // Titelgeschichte: Ausgabe-Zeile und „Außerdem in dieser Ausgabe“ (Bild immer im Hochformat 4:5)
                ['name' => 'issue', 'label' => 'Ausgabe-Zeile', 'type' => 'text', 'max' => 60, 'width' => 'half', 'placeholder' => 'z. B. Ausgabe 12 · Herbst 2026',
                    'help' => 'Steht über dem Bild, rechts daneben die Dachzeile. Das Bild erscheint im Hochformat 4:5.', 'variants' => ['issue']],
                ['name' => 'issue_more_title', 'label' => 'Überschrift der Anrisse (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half',
                    'placeholder' => 'Außerdem in dieser Ausgabe', 'variants' => ['issue']],
                ['name' => 'issue_more', 'label' => 'Außerdem in dieser Ausgabe (2–3 Anrisse)', 'type' => 'repeater', 'item_label' => 'Anriss', 'title_field' => 'title', 'max_items' => 3,
                    'variants' => ['issue'], 'fields' => [
                    ['name' => 'kicker', 'label' => 'Rubrik', 'type' => 'text', 'max' => 30, 'width' => 'half', 'placeholder' => 'z. B. Porträt'],
                    ['name' => 'meta', 'label' => 'Zusatz (optional)', 'type' => 'text', 'max' => 30, 'width' => 'half', 'placeholder' => 'z. B. 5 Min. Lesezeit'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 90],
                    ['name' => 'link', 'label' => 'Link', 'type' => 'link'],
                ]],
                // Aktuell: Termine bzw. neueste Meldungen einer Datentabelle (Core\Blocks\Hero::dates)
                ...\Core\Blocks\Hero::fields('dates', ['agenda']),
                // Stimme: Zitat mit Initialen, optional Bewertung
                ...\Core\Blocks\Hero::fields('quote', ['voice']),
            ],
        ],
        'richtext' => [
            'label' => 'Text (Artikel)', 'icon' => 'article', 'group' => 'Inhalt',
            'help' => 'Lesetext mit Zwischenüberschriften, Listen, Zitaten und Links – in Lesebreite, optional mit Initiale, Inhaltsverzeichnis und Randnotiz.',
            'fields' => [
                ...$head(false, false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext',
                    'help' => 'Ein Zitat im Text (Zitat-Format) erscheint als hervorgehobenes Randzitat.'],
                ['name' => 'aside', 'label' => 'Randnotiz (optional)', 'type' => 'textarea', 'rows' => 3, 'max' => 400,
                    'help' => 'Kurzer Hinweis in der Randspalte, z. B. „Zur Person“ oder „Hintergrund“.'],
                ['name' => 'aside_title', 'label' => 'Titel der Randnotiz', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Hintergrund'],
                ['name' => 'dropcap', 'label' => 'Initiale (großer Anfangsbuchstabe)', 'type' => 'bool', 'default' => false, 'width' => 'half',
                    'help' => 'Nur wenn unter Design → Typografie „Initiale“ eingeschaltet ist.'],
                ['name' => 'toc', 'label' => 'Inhaltsverzeichnis aus den Zwischenüberschriften', 'type' => 'bool', 'default' => false],
            ],
        ],
        'text_image' => [
            'label' => 'Text + Bild', 'icon' => 'square-half', 'group' => 'Inhalt',
            'variants' => ['right' => 'Bild rechts', 'left' => 'Bild links'],
            'fields' => [
                ...$head(true, false),
                ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'list', 'label' => 'Aufzählung (ein Punkt pro Zeile, optional)', 'type' => 'textarea', 'rows' => 3],
                ['name' => 'button_label', 'label' => 'Button – Beschriftung', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                ['name' => 'button_link', 'label' => 'Button – Link', 'type' => 'link', 'width' => 'half'],
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half', 'help' => 'Min. 1200 px breit.'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '4:5', 'width' => 'half', 'options' => $ratios],
                ['name' => 'caption', 'label' => 'Bildunterschrift (optional)', 'type' => 'text', 'max' => 200],
            ],
        ],
        'figure' => [
            'label' => 'Bild (mit Unterschrift)', 'icon' => 'image', 'group' => 'Medien',
            'help' => 'Einzelnes Bild in Textbreite, breit oder randlos über die ganze Seite – mit Bildunterschrift und Nachweis.',
            'variants' => ['wide' => 'Breit (Inhaltsbreite)', 'text' => 'Textbreite', 'bleed' => 'Randlos (volle Breite)', 'margin' => 'Mit Unterschrift in der Randspalte'],
            'fields' => [
                ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'required' => true, 'width' => 'half'],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'default' => '', 'width' => 'half', 'options' => ['' => 'Originalformat'] + $ratios],
                ['name' => 'caption', 'label' => 'Bildunterschrift', 'type' => 'text', 'max' => 240],
                ['name' => 'credit', 'label' => 'Nachweis (optional)', 'type' => 'text', 'max' => 120, 'help' => 'Leer = Nachweis aus der Mediathek.'],
            ],
        ],
        'features' => [
            'label' => 'Rubriken / Themen', 'icon' => 'squares-four', 'group' => 'Inhalt',
            'help' => 'Übersicht mit Symbol oder Bild, Titel und kurzem Text – für Rubriken, Angebote, Arbeitsbereiche oder ein nummeriertes Register.',
            'fields' => [
                ...$head(),
                ['name' => 'columns', 'label' => 'Spalten', 'type' => 'select', 'required' => true, 'default' => '3', 'width' => 'half', 'options' => $columns],
                ['name' => 'style', 'label' => 'Darstellung', 'type' => 'select', 'required' => true, 'default' => 'grid', 'width' => 'half',
                    'options' => ['grid' => 'Raster mit Spaltenlinien', 'index' => 'Register (große Nummern)', 'cards' => 'Kästen (getönt)', 'plain' => 'Schlicht']],
                ['name' => 'items', 'label' => 'Einträge', 'type' => 'repeater', 'item_label' => 'Eintrag', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 70],
                    ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3, 'max' => 300],
                    ['name' => 'icon', 'label' => 'Symbol', 'type' => 'icon', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'oder Bild (3:2)', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'link_label', 'label' => 'Link – Beschriftung (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half'],
                    ['name' => 'link', 'label' => 'Link', 'type' => 'link', 'width' => 'half'],
                ]],
            ],
        ],
        'teasers' => [
            'label' => 'Anreißer (von Hand)', 'icon' => 'list-bullets', 'group' => 'Inhalt',
            'help' => 'Verweise auf Seiten, Artikel oder externe Beiträge – von Hand zusammengestellt. Für Einträge aus Datentabellen: Block „Datenliste“.',
            'variants' => ['grid' => 'Raster', 'lead' => 'Erster groß, Rest im Raster', 'list' => 'Nummerierte Liste (z. B. „Meistgelesen“)', 'brief' => 'Kurz notiert'],
            'fields' => [
                ...$head(),
                ['name' => 'columns', 'label' => 'Spalten (Raster)', 'type' => 'select', 'required' => true, 'default' => '3', 'width' => 'half', 'options' => $columns],
                ['name' => 'ratio', 'label' => 'Bildformat', 'type' => 'select', 'required' => true, 'default' => '3:2', 'width' => 'half', 'options' => $ratios],
                ['name' => 'items', 'label' => 'Anreißer', 'type' => 'repeater', 'item_label' => 'Anreißer', 'title_field' => 'title', 'max_items' => 12, 'fields' => [
                    ['name' => 'kicker', 'label' => 'Dachzeile', 'type' => 'text', 'max' => 40, 'width' => 'half'],
                    ['name' => 'meta', 'label' => 'Datum / Autor (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
                    ['name' => 'title', 'label' => 'Titel', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'text', 'label' => 'Vorspann', 'type' => 'textarea', 'rows' => 2, 'max' => 260],
                    ['name' => 'image', 'label' => 'Bild', 'type' => 'media', 'width' => 'half'],
                    ['name' => 'link', 'label' => 'Link', 'type' => 'link', 'width' => 'half'],
                ]],
                ['name' => 'more_label', 'label' => 'Link unter der Liste', 'type' => 'text', 'max' => 40, 'width' => 'half', 'placeholder' => 'z. B. Alle Beiträge'],
                ['name' => 'more_link', 'label' => 'Link', 'type' => 'link', 'width' => 'half'],
            ],
        ],
        'stats' => [
            'label' => 'Zahlen & Fakten', 'icon' => 'hash', 'group' => 'Inhalt',
            'help' => 'Bis zu vier große Zahlen mit Beschriftung. Nur belegbare Zahlen verwenden.',
            'fields' => [
                ...$head(),
                ['name' => 'items', 'label' => 'Zahlen', 'type' => 'repeater', 'item_label' => 'Zahl', 'title_field' => 'value', 'max_items' => 4, 'fields' => [
                    ['name' => 'value', 'label' => 'Wert', 'type' => 'text', 'required' => true, 'max' => 12, 'width' => 'half', 'placeholder' => 'z. B. 42'],
                    ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'max' => 48, 'width' => 'half'],
                    ['name' => 'text', 'label' => 'Erläuterung (optional)', 'type' => 'text', 'max' => 140],
                ]],
            ],
        ],
        'quote' => [
            'label' => 'Zitat', 'icon' => 'quotes', 'group' => 'Inhalt',
            'help' => 'Ein Eintrag = großes Zitat (Randzitat im Magazinstil). Zwei oder drei Einträge = Stimmen nebeneinander. Nur echte, freigegebene Zitate.',
            'fields' => [
                ...$head(false, false),
                ['name' => 'items', 'label' => 'Zitate', 'type' => 'repeater', 'item_label' => 'Zitat', 'title_field' => 'name', 'max_items' => 3, 'fields' => [
                    ['name' => 'text', 'label' => 'Zitat', 'type' => 'textarea', 'rows' => 3, 'required' => true, 'max' => 400],
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'width' => 'half'],
                    ['name' => 'role', 'label' => 'Funktion', 'type' => 'text', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'Porträt (optional, 1:1)', 'type' => 'media'],
                ]],
            ],
        ],
        'faq' => [
            'label' => 'Fragen & Antworten', 'icon' => 'question', 'group' => 'Inhalt',
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
            'label' => 'Handlungsaufruf / Newsletter', 'icon' => 'megaphone', 'group' => 'Inhalt', 'background' => 'accent',
            'help' => '„Newsletter“ zeigt das öffentliche Formular einer Datentabelle direkt im Band (z. B. Anmeldung mit E-Mail) – ohne Dienste Dritter.',
            'variants' => ['band' => 'Band über die volle Breite', 'box' => 'Hervorgehobener Kasten', 'newsletter' => 'Newsletter / Formular im Band'],
            'fields' => [
                ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60],
                ['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true, 'max' => 110],
                ['name' => 'text', 'label' => 'Text (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 300],
                ...$buttons,
                ['name' => 'form_table', 'label' => 'Formular (bei „Newsletter“)', 'type' => 'datatable', 'inbox' => true,
                    'help' => 'Tabelle mit eingeschaltetem „Öffentliches Formular“ (Daten → Tabelle → Felder).'],
                ['name' => 'submit_label', 'label' => 'Beschriftung des Buttons (optional)', 'type' => 'text', 'max' => 32, 'width' => 'half', 'placeholder' => 'Anmelden'],
                ['name' => 'success_text', 'label' => 'Text nach dem Absenden (optional)', 'type' => 'text', 'width' => 'half'],
            ],
        ],
        'video' => [
            'label' => 'Video', 'icon' => 'play-circle', 'group' => 'Medien',
            'help' => 'YouTube/Vimeo mit Zwei-Klick-Lösung (Vorschaubild vom eigenen Server, Player erst nach Klick). Eigene MP4-Dateien laufen direkt – mit Untertiteln und Transkript aus der Mediathek.',
            'jsonld' => ['type' => 'video', 'url' => 'video_url', 'file' => 'video_file', 'poster' => 'poster', 'name' => 'title', 'description' => 'caption'],
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
        'logos' => [
            'label' => 'Partner & Förderer', 'icon' => 'handshake', 'group' => 'Medien',
            'help' => 'Logos von Partnern, Förderern oder Mitgliedern; ohne Bild erscheint der Name als Schriftzug. Nur Logos mit Freigabe verwenden.',
            'fields' => [
                ...$head(false, false),
                ['name' => 'items', 'label' => 'Logos', 'type' => 'repeater', 'item_label' => 'Logo', 'title_field' => 'name', 'max_items' => 18, 'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'width' => 'half'],
                    ['name' => 'link', 'label' => 'Link (optional)', 'type' => 'link', 'width' => 'half'],
                    ['name' => 'image', 'label' => 'Logo (SVG oder PNG, transparent)', 'type' => 'media'],
                ]],
            ],
        ],
        'contact' => [
            'label' => 'Kontakt', 'icon' => 'envelope-simple', 'group' => 'Website',
            'central' => 'Adresse, Telefon, E-Mail, Geschäftszeiten und Karte kommen aus „Website“.',
            'fields' => [
                ...$head(),
                ['name' => 'show_hours', 'label' => 'Geschäftszeiten anzeigen (falls eingetragen)', 'type' => 'bool', 'default' => true],
                ['name' => 'show_map', 'label' => 'Karte anzeigen (falls Standort eingetragen)', 'type' => 'bool', 'default' => true],
                ['name' => 'note', 'label' => 'Hinweis unten (optional)', 'type' => 'text'],
            ],
        ],
        'map' => [
            'label' => 'Karte', 'icon' => 'map-trifold', 'group' => 'Website',
            'help' => 'Interaktive Karte (OpenStreetMap-Daten über den eigenen Server – ohne Einwilligung, ohne Cookies).',
            'fields' => [...$head(false, false), ...\Core\Maps::blockFields()],
        ],
        'article' => [
            'label' => 'Artikel (Detailseite)', 'icon' => 'file-text', 'group' => 'Daten',
            'help' => 'Für Detailseiten-Vorlagen: setzt den aufgerufenen Eintrag als Artikel – Dachzeile, Schlagzeile, Vorspann, Autor, Datum, Herkunft, Aufmacherbild, Text mit Inhaltsverzeichnis, Lesefortschritt und Teilen-Links. Termine erhalten einen Termin-Kasten mit iCal.',
            'fields' => [
                ['name' => 'table', 'label' => 'Tabelle', 'type' => 'datatable', 'required' => true],
                ['name' => 'kicker_field', 'label' => 'Dachzeile aus Feld', 'type' => 'datafield', 'width' => 'half', 'empty_label' => '– automatisch (erstes Auswahlfeld) –'],
                ['name' => 'dek_field', 'label' => 'Vorspann aus Feld', 'type' => 'datafield', 'width' => 'half', 'empty_label' => '– Beschreibungsfeld der Tabelle –'],
                ['name' => 'body_field', 'label' => 'Artikeltext aus Feld', 'type' => 'datafield', 'width' => 'half', 'empty_label' => '– automatisch (erstes Feld „Formatierter Text“) –'],
                ['name' => 'author_field', 'label' => 'Autor aus Feld', 'type' => 'datafield', 'width' => 'half', 'empty_label' => '– automatisch (Feld „autor“) –'],
                ['name' => 'image_ratio', 'label' => 'Format des Aufmacherbilds', 'type' => 'select', 'default' => '3:2', 'width' => 'half', 'options' => $ratios],
                ['name' => 'image_width', 'label' => 'Breite des Aufmacherbilds', 'type' => 'select', 'default' => 'wide', 'width' => 'half',
                    'options' => ['wide' => 'Breit', 'bleed' => 'Randlos', 'text' => 'Textbreite', 'none' => 'Kein Bild']],
                ['name' => 'show_toc', 'label' => 'Inhaltsverzeichnis (ab zwei Zwischenüberschriften)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'show_share', 'label' => 'Teilen-Links (ohne Tracker)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'show_progress', 'label' => 'Lesefortschritt oben (ohne JavaScript)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'show_facts', 'label' => 'Termin-Kasten (Kalender-Tabellen)', 'type' => 'bool', 'default' => true, 'width' => 'half'],
                ['name' => 'back_label', 'label' => 'Zurück-Link (optional)', 'type' => 'text', 'width' => 'half', 'placeholder' => 'z. B. Alle Nachrichten'],
                ['name' => 'back_link', 'label' => 'Zurück zu', 'type' => 'link', 'width' => 'half'],
            ],
        ],
        'data_list' => $dataList,
    ],
];
