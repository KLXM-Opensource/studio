<?php
/*
 * Startinhalte des Kits „galerie“ – werden beim ersten Aufruf einer neuen Website eingespielt.
 * Fiktive „Galerie Beispiel (Demo)“: Einstellungen und Rechtsseiten hier; Datentabellen (Künstler, Ausstellungen, Werke,
 * Anfragen), Platzhalter-Bilder (GD), alle Seiten und Detailseiten legt 'after' an (tools/demo-content.php) – erst dann
 * gibt es die Tabellen, auf die die Blöcke verweisen. Ausstellungsdaten relativ zum Tag der Anlage (Status stimmt).
 * Texte in [eckigen Klammern] sind Platzhalter. Entfernen: php kits/galerie/tools/demo.php --remove
 * Farben, Schriften, Präsentation der Werke: Verwaltung → Design (Standard = Vorlage „White Cube“).
 */

$hero = fn(string $title, string $text, string $eyebrow = '') => [
    'type' => 'hero', 'tunes' => ['spaceBottom' => 'small'],
    'data' => ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text],
];

return [
    'settings' => [
        'org_name' => 'Galerie Beispiel (Demo)',
        'short_name' => 'Galerie Beispiel',
        'tagline' => 'Zeitgenössische Kunst am Musterplatz.',
        'street' => '',
        'zip' => '',
        'city' => '',
        'country' => '',
        'phone' => '',
        'email' => 'galerie@example.com',
        'hours' => [],
        'hours_note' => '',
        'geo' => '',
        'header_cta_label' => 'Besuch planen',
        'header_cta_link' => '/besuch',
        'footer_statement' => 'Kunst sehen. Gespräche führen.',
        'footer_text' => 'Demo-Inhalte des Kits „Galerie“: Künstler, Werke, Preise und Texte sind frei erfunden, alle Abbildungen automatisch erzeugte Platzhalter.',
        'topbar_text' => 'Mi–Fr 12–18 Uhr · Sa 11–16 Uhr · Eintritt frei',
        'notice_active' => false,
        'notice_text' => '',
        'social' => [],
        'site_title' => 'Galerie Beispiel (Demo) – zeitgenössische Kunst',
        'site_title_suffix' => '',
        'default_meta_description' => 'Demo-Website der fiktiven Galerie Beispiel: aktuelle Ausstellungen, Künstlerinnen und Künstler, Werke, Viewing Room und Besuch.',
        'schema_type' => 'ArtGallery',
    ],

    // Tabellen, Bilder, Seiten und Detailseiten (Core\Seeder ruft 'after' nach den Seiten auf)
    'after' => function () {
        require_once __DIR__ . '/tools/demo-content.php';
        galerie_demo_install(true);
    },

    'page_refs' => [
        'imprint_page' => 'impressum',
        'privacy_page' => 'datenschutz',
    ],

    'pages' => [
        // ------------------------------------------------------------------ Rechtliches (nicht im Menü, verlinkt im Fußbereich)
        [
            'slug' => 'impressum', 'title' => 'Impressum', 'menu' => false,
            'blocks' => [
                $hero('Impressum', ''),
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'title' => '', 'text' =>
                    '<p><strong>Hinweis für die Betreiber dieser Website:</strong> Bitte ersetzen Sie diesen Platzhalter durch Ihr vollständiges Impressum (Anbieterkennzeichnung, z. B. nach § 5 DDG) und lassen Sie es im Zweifel rechtlich prüfen.</p>'
                    . '<h3>Angaben zum Anbieter</h3><p>[Name bzw. Firma]<br>[Straße und Hausnummer]<br>[PLZ Ort]</p>'
                    . '<h3>Vertreten durch</h3><p>[Vertretungsberechtigte Person]</p>'
                    . '<h3>Kontakt</h3><p>[Telefon]<br>[E-Mail-Adresse]</p>'
                    . '<h3>Registereintrag und Umsatzsteuer-ID</h3><p>[Registergericht, Registernummer, USt-IdNr. – falls vorhanden]</p>'
                    . '<h3>Verantwortlich für den Inhalt</h3><p>[Name, Anschrift]</p>']],
            ],
        ],
        [
            'slug' => 'datenschutz', 'title' => 'Datenschutz', 'menu' => false,
            'blocks' => [
                $hero('Datenschutzerklärung', ''),
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'title' => '', 'text' =>
                    '<p><strong>Hinweis für die Betreiber dieser Website:</strong> Bitte fügen Sie hier Ihre vollständige Datenschutzerklärung ein. Sie muss zu Ihrer tatsächlichen Nutzung passen (z. B. Hosting, E-Mail, eingebundene Dienste) und sollte rechtlich geprüft werden.</p>'
                    . '<h3>Verantwortliche Stelle</h3><p>[Name bzw. Firma, Anschrift, E-Mail-Adresse]</p>'
                    . '<h3>Technische Hinweise zu dieser Website</h3><p>Die Website setzt für Besucher keine Cookies, lädt Schriften vom eigenen Server und bindet keine Analyse- oder Tracking-Dienste ein. Links „Auf der Karte“ führen zu OpenStreetMap (erst beim Klick). Anfragen über das Formular werden in der Verwaltung gespeichert. [Bitte prüfen und an Ihre Nutzung anpassen.]</p>'
                    . '<h3>Ihre Rechte</h3><p>[Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit, Widerspruch, Beschwerde bei einer Aufsichtsbehörde]</p>']],
            ],
        ],
    ],
];
