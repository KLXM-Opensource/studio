<?php
/*
 * Startinhalte des Kits „modern“ – werden beim ersten Aufruf einer neuen Website eingespielt.
 * Beispiel: „Studio Nordlicht – Produktdesign & Beratung (fiktiv)“. Alle Namen, Zahlen, Stimmen und Auftraggeber sind
 * frei erfunden; Bilder werden erzeugt (keine Fotos, keine Personen, keine Marken).
 * Texte in [eckigen Klammern] sind Platzhalter (erscheinen in der Übersicht der Verwaltung als offen).
 * Hier nur zentrale Angaben und Rechtsseiten; Startseite, Leistungen, Projekte (Datentabelle mit Detailseite), Studio,
 * Journal, Kontakt und Baukasten legt 'after' an (tools/demo-content.php – mit erzeugten Bildern; entfernen: tools/demo.php --remove).
 */

$workday = fn(string $tag) => ['tag' => $tag, 'von' => '09:00', 'bis' => '18:00', 'pause_von' => '', 'pause_bis' => '', 'notiz' => ''];

return [
    'settings' => [
        'org_name' => 'Studio Nordlicht (fiktiv)',
        'short_name' => 'Nordlicht',
        'tagline' => 'Produktdesign & Beratung – ein fiktives Beispiel.',
        'street' => '',
        'zip' => '',
        'city' => '',
        'country' => '',
        'phone' => '',
        'email' => 'hallo@example.com',
        'hours' => [$workday('1'), $workday('2'), $workday('3'), $workday('4'),
            ['tag' => '5', 'von' => '09:00', 'bis' => '15:00', 'pause_von' => '', 'pause_bis' => '', 'notiz' => '']],
        'hours_note' => 'Werkstattbesuche nach Vereinbarung.',
        'geo' => '',
        'header_cta_label' => 'Beratung buchen',
        'header_cta_link' => '/kontakt',
        'footer_statement' => 'Lassen Sie uns etwas Gutes bauen.',
        'footer_text' => 'Demo-Inhalte des Kits „modern“: Studio, Personen, Auftraggeber, Zahlen und Stimmen sind frei erfunden.',
        'notice_active' => false,
        'notice_text' => '',
        'social' => [],
        'site_title' => 'Studio Nordlicht – Produktdesign & Beratung (fiktiv)',
        'site_title_suffix' => 'Studio Nordlicht',
        'default_meta_description' => 'Demo-Website des fiktiven Studio Nordlicht: Produktdesign und Beratung für Dinge mit wenigen Bedienelementen, ehrlichen Materialien und langer Lebensdauer.',
        'schema_type' => 'ProfessionalService',
    ],

    // Startseite und alle übrigen Seiten mit erzeugten Bildern (Core\Seeder ruft 'after' nach den Seiten auf)
    'after' => function () {
        require_once __DIR__ . '/tools/demo-content.php';
        modern_demo_install();
    },

    'page_refs' => [
        'imprint_page' => 'impressum',
        'privacy_page' => 'datenschutz',
        'accessibility_page' => 'barrierefreiheit',
    ],

    'pages' => [
        [
            'slug' => 'impressum', 'title' => 'Impressum', 'menu' => false, 'noindex' => true,
            'blocks' => [
                ['type' => 'hero', 'data' => ['variant' => 'compact', 'eyebrow' => 'Rechtliches', 'title' => 'Impressum', 'text' => '']],
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => '', 'intro' => '', 'text' =>
                    '<p><strong>Hinweis:</strong> Dies ist eine Demo-Website mit einem frei erfundenen Studio. Bitte ersetzen Sie alle Angaben in [eckigen Klammern].</p>'
                    . '<h2>Angaben gemäß § 5 DDG</h2><p>[Name des Unternehmens]<br>[Straße und Hausnummer]<br>[PLZ Ort]</p>'
                    . '<h2>Kontakt</h2><p>Telefon: [Telefonnummer]<br>E-Mail: [E-Mail-Adresse]</p>'
                    . '<h2>Vertreten durch</h2><p>[Name der vertretungsberechtigten Person]</p>'
                    . '<h2>Umsatzsteuer-ID</h2><p>[Umsatzsteuer-Identifikationsnummer]</p>']],
            ],
        ],
        [
            'slug' => 'datenschutz', 'title' => 'Datenschutz', 'menu' => false, 'noindex' => true,
            'blocks' => [
                ['type' => 'hero', 'data' => ['variant' => 'compact', 'eyebrow' => 'Rechtliches', 'title' => 'Datenschutzerklärung', 'text' => '']],
                ['type' => 'richtext', 'data' => ['variant' => 'article', 'eyebrow' => '', 'title' => '', 'intro' => '', 'meta' => '', 'toc' => true, 'dropcap' => false, 'text' =>
                    '<p>[Diese Vorlage ersetzt keine Rechtsberatung. Bitte lassen Sie Ihre Datenschutzerklärung prüfen.]</p>'
                    . '<h2>Verantwortliche Stelle</h2><p>[Name, Anschrift, Kontakt]</p>'
                    . '<h2>Keine Cookies, keine Tracker</h2><p>Diese Website setzt für Besucherinnen und Besucher keine Cookies und bindet keine Analyse-Werkzeuge ein. Schriften und Bilder werden vom eigenen Server geladen.</p>'
                    . '<h2>Kartenansicht</h2><p>Kartenkacheln werden über den eigenen Server geladen (Proxy); dabei wird Ihre IP-Adresse nicht an Dritte übermittelt.</p>'
                    . '<h2>Videos</h2><p>Eingebettete Videos von YouTube oder Vimeo werden erst nach Ihrem Klick geladen. Erst dann werden Daten an den Anbieter übertragen.</p>'
                    . '<h2>Kontaktformular</h2><p>Ihre Angaben werden zur Bearbeitung der Anfrage gespeichert und nach [Frist] gelöscht.</p>'
                    . '<h2>Ihre Rechte</h2><p>Sie haben das Recht auf Auskunft, Berichtigung, Löschung und Einschränkung der Verarbeitung sowie auf Beschwerde bei einer Aufsichtsbehörde.</p>']],
            ],
        ],
        [
            'slug' => 'barrierefreiheit', 'title' => 'Barrierefreiheit', 'menu' => false,
            'blocks' => [
                ['type' => 'hero', 'data' => ['variant' => 'compact', 'eyebrow' => 'Rechtliches', 'title' => 'Erklärung zur Barrierefreiheit', 'text' => '']],
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'eyebrow' => '', 'title' => '', 'intro' => '', 'text' =>
                    '<p>Diese Website ist nach den Anforderungen der WCAG 2.2 (Stufe AA) gestaltet: ausreichende Kontraste in hellem und dunklem Farbschema, vollständige Tastaturbedienung, sichtbarer Fokus, reduzierte Bewegung auf Wunsch und Texte, die sich auf 200 % vergrößern lassen.</p>'
                    . '<h2>Feedback</h2><p>Sind Ihnen Barrieren aufgefallen? Schreiben Sie uns: [E-Mail-Adresse].</p>']],
            ],
        ],
    ],
];
