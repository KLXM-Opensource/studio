<?php
/*
 * Startinhalte des Themes „editorial“ – werden beim ersten Aufruf einer neuen Website eingespielt.
 * Alle Inhalte sind frei erfunden und als Demo gekennzeichnet („Kulturnetz Musterland (Demo)“, Beispielnamen, Beispielzahlen).
 * Texte in [eckigen Klammern] sind Platzhalter: Sie erscheinen nicht für Besucher bzw. werden in der Übersicht als offen gemeldet.
 * Zusätzlich (after): Ausgabe mit Rubriken, Datentabellen (Nachrichten, Termine, Personen, zwei Formulare), Detailvorlagen
 * und erzeugten Platzhalterbildern (tools/demo-content.php; entfernen mit php kits/editorial/tools/demo.php --remove).
 * Farben, Schriften, Kopfbereich und Raster: Verwaltung → Design (Standard = Voreinstellung „Tageszeitung“).
 */

$workday = fn(string $tag, string $bis = '16:00') => ['tag' => $tag, 'von' => '09:00', 'bis' => $bis, 'pause_von' => '', 'pause_bis' => '', 'notiz' => ''];

$legal = fn(string $title, string $text) => [
    ['type' => 'hero', 'data' => ['variant' => 'compact', 'eyebrow' => 'Rechtliches', 'title' => $title, 'text' => '', 'subnav' => false]],
    ['type' => 'richtext', 'data' => ['text' => $text, 'dropcap' => false, 'toc' => false]],
];

return [
    'settings' => [
        'org_name' => 'Kulturnetz Musterland (Demo)',
        'short_name' => 'Kulturnetz',
        'tagline' => 'Nachrichten, Termine und Menschen aus 42 Kulturvereinen',
        'edition' => 'Ausgabe Herbst 2026',
        'street' => '',
        'zip' => '',
        'city' => '',
        'country' => '',
        'phone' => '',
        'email' => 'redaktion@example.org',
        'hours' => [$workday('1'), $workday('2'), $workday('3'), $workday('4'), $workday('5', '13:00')],
        'hours_note' => 'Termine außerhalb dieser Zeiten nach Vereinbarung.',
        'geo' => '',
        'header_cta_label' => 'Newsletter',
        'header_cta_link' => '/mitmachen#newsletter',
        'footer_text' => 'Demo-Inhalte des Kits „Editorial“: Namen, Vereine, Orte, Zahlen und Zitate sind frei erfunden und dienen nur als Beispiel.',
        'notice_active' => false,
        'notice_text' => '',
        'social' => [],
        'site_title' => 'Kulturnetz Musterland (Demo) – Nachrichten aus der Vereinskultur',
        'site_title_suffix' => '',
        'default_meta_description' => 'Demo-Website des fiktiven Kulturnetzes Musterland: Nachrichten, Termine, Menschen und Magazin – ein Beispiel für Verbände, Kulturhäuser und Bildungsträger.',
        'schema_type' => 'NGO',
    ],

    // Ausgabe, Datentabellen und Bilder nach den Seiten anlegen (Core\Seeder ruft 'after' auf)
    'after' => function () {
        require_once __DIR__ . '/tools/demo-content.php';
        editorial_demo_install();
    },

    'page_refs' => [
        'imprint_page' => 'impressum',
        'privacy_page' => 'datenschutz',
    ],

    'pages' => [
        [
            'slug' => 'start', 'title' => 'Start', 'is_home' => true, 'menu' => false,
            'meta_description' => 'Demo-Website des fiktiven Kulturnetzes Musterland – Nachrichten, Termine und Menschen aus der Vereinskultur.',
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'variant' => 'centered', 'eyebrow' => 'Kulturnetz Musterland (Demo)', 'title' => 'Die Ausgabe wird gerade gesetzt.',
                    'text' => 'Diese Startseite füllt sich mit Nachrichten, Terminen und Menschen, sobald die Beispielinhalte angelegt sind (php kits/editorial/tools/demo.php).',
                    'ratio' => '21:9',
                ]],
            ],
        ],
        [
            'slug' => 'impressum', 'title' => 'Impressum', 'menu' => false,
            'blocks' => $legal('Impressum', '<p>[Angaben nach § 5 DDG: Name und Anschrift, Vertretungsberechtigte, Kontakt, Registereintrag, Umsatzsteuer-ID – bitte von den Betreibern eintragen lassen.]</p><h2>Verantwortlich für den Inhalt</h2><p>[Name, Anschrift]</p>'),
        ],
        [
            'slug' => 'datenschutz', 'title' => 'Datenschutz', 'menu' => false,
            'blocks' => $legal('Datenschutzerklärung', '<p>[Datenschutzerklärung der Betreiber einfügen.]</p><h2>Ohne Cookies und ohne Tracking</h2><p>Diese Website setzt keine Cookies und bindet keine Dienste Dritter ein. Schriften und Karten kommen vom eigenen Server; Videos von YouTube oder Vimeo laden erst nach einem Klick.</p><h2>Formulare</h2><p>[Zweck, Rechtsgrundlage und Speicherdauer der Angaben aus Newsletter- und Mitgliedsformular.]</p>'),
        ],
    ],
];
