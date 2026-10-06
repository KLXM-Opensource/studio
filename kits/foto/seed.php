<?php
/*
 * Startinhalte des Kits „foto“ – werden beim ersten Aufruf einer neuen Website eingespielt.
 * Fiktive Fotografin „Mara Beispiel (Demo)“; alle Namen, Texte und Preise sind erfunden und als Demo gekennzeichnet.
 * Texte in [eckigen Klammern] sind Platzhalter (erscheinen in der Übersicht der Verwaltung als offen).
 * Hier nur Einstellungen und Rechtstexte; Startseite, „Arbeiten“ mit drei Serien (Unterseiten), „Über mich“ und „Kontakt“
 * mit erzeugten Platzhalterbildern legt 'after' an (tools/demo-content.php – der Seeder kennt weder Unterseiten noch Bilder).
 * Farben, Schriften, Bilder & Galerien, Kopf und Fuß: Verwaltung → Design (Standard = Vorlage „Weiß & still“).
 */

$hero = fn(string $title, string $text, string $eyebrow = '') => [
    'type' => 'hero', 'tunes' => ['spaceBottom' => 'small'],
    'data' => ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text],
];

return [
    'settings' => [
        'org_name' => 'Mara Beispiel Fotografie (Demo)',
        'short_name' => 'Mara Beispiel',
        'tagline' => 'Fotografie · Porträt, Reportage, Landschaft',
        'street' => '',
        'zip' => '',
        'city' => 'Musterstadt',
        'country' => '',
        'phone' => '',
        'email' => 'studio@example.com',
        'hours' => [],
        'hours_note' => '',
        'geo' => '',
        'header_cta_label' => 'Anfragen',
        'header_cta_link' => '/kontakt',
        'footer_statement' => 'Lassen Sie uns Bilder machen.',
        'footer_text' => 'Demo-Inhalte des Kits „foto“: Person, Texte und Preise sind frei erfunden, alle Bilder automatisch erzeugte Platzhalter.',
        'notice_active' => false,
        'notice_text' => '',
        'social' => [],
        'site_title' => 'Mara Beispiel Fotografie (Demo) – Porträt, Reportage, Landschaft',
        'site_title_suffix' => '',
        'default_meta_description' => 'Demo-Portfolio der fiktiven Fotografin Mara Beispiel: Serien aus Porträt, Reportage und Landschaft.',
        'schema_type' => 'ProfessionalService',
    ],

    // Demo-Seiten mit Platzhalterbildern nach den Rechtstexten anlegen (Core\Seeder ruft 'after' auf)
    'after' => function () {
        require_once __DIR__ . '/tools/demo-content.php';
        foto_demo_install();
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
                    . '<h3>Technische Hinweise zu dieser Website</h3><p>Die Website setzt für Besucher keine Cookies, lädt Schriften und Bilder vom eigenen Server und bindet keine Analyse- oder Tracking-Dienste ein. Videos von YouTube oder Vimeo laden erst nach einem Klick. Die Einstellung „Videos künftig direkt laden“ wird nur im eigenen Browser gespeichert (lokaler Speicher, kein Cookie). [Bitte prüfen und an Ihre Nutzung anpassen.]</p>'
                    . '<h3>Ihre Rechte</h3><p>[Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit, Widerspruch, Beschwerde bei einer Aufsichtsbehörde]</p>']],
            ],
        ],
    ],
];
