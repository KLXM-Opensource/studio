<?php
/*
 * Startinhalte des Themes „essenz“ – werden beim ersten Aufruf einer neuen Website eingespielt.
 * Alle Inhalte sind frei erfunden und als Beispiel gekennzeichnet („Werkstatt Beispiel – Gestaltung mit Haltung“).
 * Texte in [eckigen Klammern] sind Platzhalter (erscheinen in der Übersicht der Verwaltung als offen).
 * Hauptseiten ohne Bilder – das Paneel im Einstieg zeigt ohne Bild ein Punktraster mit Regler (Inline-SVG, keine Datei).
 * Zusätzlich (after): Musterseiten „Werkstatt“ mit allen Blöcken, Datentabellen (Produkte mit Detailseite, Termine,
 * öffentliches Formular) und erzeugten geometrischen Bildern (tools/demo-content.php; entfernen mit tools/demo.php --remove).
 */

$workday = fn(string $tag) => ['tag' => $tag, 'von' => '09:00', 'bis' => '17:30', 'pause_von' => '12:30', 'pause_bis' => '13:30', 'notiz' => ''];

$hero = fn(string $title, string $text, string $eyebrow = '') => [
    'type' => 'hero', 'tunes' => [],
    'data' => ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text],
];

$cta = [
    'type' => 'cta', 'tunes' => [],
    'data' => [
        'variant' => 'panel', 'eyebrow' => 'Nächster Schritt',
        'title' => 'Erzählen Sie uns, was Sie *vorhaben*.',
        'text' => 'Ein kurzes Gespräch klärt, ob wir helfen können – sachlich und unverbindlich.',
        'button_label' => 'Kontakt aufnehmen', 'button_link' => '/kontakt',
        'button2_label' => 'E-Mail schreiben', 'button2_link' => 'email',
    ],
];

return [
    'settings' => [
        'org_name' => 'Werkstatt Beispiel',
        'short_name' => 'Werkstatt Beispiel',
        'tagline' => 'Gestaltung mit Haltung – ein fiktives Beispiel.',
        'street' => '',
        'zip' => '',
        'city' => '',
        'country' => '',
        'phone' => '',
        'email' => 'hallo@example.com',
        'hours' => [$workday('1'), $workday('2'), $workday('3'), $workday('4'),
            ['tag' => '5', 'von' => '09:00', 'bis' => '14:00', 'pause_von' => '', 'pause_bis' => '', 'notiz' => '']],
        'hours_note' => 'Termine außerhalb dieser Zeiten nach Vereinbarung.',
        'geo' => '',
        'header_cta_label' => 'Anfrage',
        'header_cta_link' => '/kontakt',
        'footer_statement' => 'Weniger, aber besser.',
        'footer_text' => 'Demo-Inhalte des Kits „Essenz“: Werkstatt, Namen, Zahlen und Stimmen sind frei erfunden.',
        'notice_active' => false,
        'notice_text' => '',
        'social' => [],
        'site_title' => 'Werkstatt Beispiel – Gestaltung mit Haltung',
        'site_title_suffix' => '',
        'default_meta_description' => 'Demo-Website der fiktiven Werkstatt Beispiel: funktionale Gestaltung für Dinge, Räume und Websites – ruhig, präzise, langlebig.',
        'schema_type' => 'ProfessionalService',
    ],

    // Musterseiten nach den Seiten anlegen (Core\Seeder ruft 'after' auf)
    'after' => function () {
        require_once __DIR__ . '/tools/demo-content.php';
        essenz_demo_install();
    },

    'page_refs' => [
        'imprint_page' => 'impressum',
        'privacy_page' => 'datenschutz',
    ],

    'pages' => [
        // ------------------------------------------------------------------ Startseite
        [
            'slug' => 'start', 'title' => 'Start', 'is_home' => true, 'menu' => false,
            'meta_description' => 'Werkstatt Beispiel (fiktiv): Gestaltung für Dinge, Räume und Websites – weniger, aber besser.',
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'variant' => 'panel', 'eyebrow' => 'Werkstatt Beispiel',
                    'title' => 'Gestaltung, die sich *zurücknimmt*.',
                    'text' => 'Wir entwerfen Dinge, Räume und Websites, die ihre Aufgabe erfüllen – ruhig, verständlich und für viele Jahre gemacht.',
                    'button_label' => 'Arbeitsweise', 'button_link' => '/haltung',
                    'button2_label' => 'Musterseiten', 'button2_link' => '/werkstatt',
                    'points' => "Gegründet: 2014 (fiktiv)\nTeam: 6 Personen\nProjekte: 140+\nAntwortzeit: 48 h",
                    'panel_label' => 'Werkstatt · Modell 01',
                ]],
                ['type' => 'principles', 'tunes' => ['background' => 'white'], 'data' => [
                    'variant' => 'list', 'eyebrow' => 'Haltung', 'title' => 'Fünf Grundsätze, an denen wir uns messen',
                    'intro' => 'Unsere eigenen Leitsätze – kurz genug, um sie im Alltag wirklich anzuwenden.',
                    'items' => [
                        ['title' => 'Nützlich vor auffällig', 'text' => 'Jede Entscheidung beginnt mit der Frage, wem sie hilft. Was nichts beiträgt, lassen wir weg.'],
                        ['title' => 'Verständlich ohne Anleitung', 'text' => 'Gute Dinge erklären sich selbst: durch Ordnung, klare Beschriftung und vertraute Muster.'],
                        ['title' => 'Ehrlich im Versprechen', 'text' => 'Wir zeigen, was etwas kann – nicht mehr. Keine Superlative, keine erfundenen Zahlen.'],
                        ['title' => 'Gebaut, um zu bleiben', 'text' => 'Zeitlose Formen, reparierbare Technik, Inhalte, die sich ohne uns pflegen lassen.'],
                        ['title' => 'Sorgfältig bis zum Rand', 'text' => 'Abstände, Kontraste, Fehlermeldungen: Die Details entscheiden, ob etwas sich gut anfühlt.'],
                    ],
                ]],
                ['type' => 'features', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'panel', 'eyebrow' => 'Leistungen', 'title' => 'Ein Bedienfeld, vier Tasten',
                    'intro' => 'Von der ersten Skizze bis zur laufenden Pflege – aus einer Hand.', 'size' => 'm',
                    'items' => [
                        ['icon' => 'compass', 'title' => 'Klären', 'text' => 'Ziele, Nutzung und Rahmen ordnen, bevor gestaltet wird.', 'link_label' => '', 'link' => '/leistungen'],
                        ['icon' => 'pencil-ruler', 'title' => 'Entwerfen', 'text' => 'Form, Farbe und Typografie als nachvollziehbares System.', 'link_label' => '', 'link' => '/leistungen'],
                        ['icon' => 'hammer', 'title' => 'Bauen', 'text' => 'Schnell, barrierearm und datensparsam umgesetzt.', 'link_label' => '', 'link' => '/leistungen'],
                        ['icon' => 'leaf', 'title' => 'Pflegen', 'text' => 'Schulung, Wartung und kleine Verbesserungen – Jahr für Jahr.', 'link_label' => '', 'link' => '/leistungen'],
                    ],
                ]],
                ['type' => 'stats', 'data' => [
                    'variant' => 'dials', 'eyebrow' => 'In Zahlen', 'title' => 'Messbar, nicht laut',
                    'intro' => 'Beispielwerte einer fiktiven Werkstatt.',
                    'items' => [
                        ['value' => '12', 'label' => 'Jahre Erfahrung', 'level' => '', 'text' => 'seit 2014 (fiktiv)'],
                        ['value' => '92 %', 'label' => 'Weiterempfehlung', 'level' => '92', 'text' => 'Beispielumfrage'],
                        ['value' => '0,4 s', 'label' => 'Ladezeit', 'level' => '20', 'text' => 'Startseite, Median'],
                        ['value' => '0', 'label' => 'Cookies', 'level' => '0', 'text' => 'für Besucher'],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Leistungen
        [
            'slug' => 'leistungen', 'title' => 'Leistungen', 'menu' => true,
            'meta_description' => 'Leistungen der fiktiven Werkstatt Beispiel: klären, entwerfen, bauen, pflegen.',
            'blocks' => [
                $hero('Leistungen', 'Vier Schritte, klar getrennt und sauber verbunden – Sie wissen jederzeit, woran wir arbeiten.', 'Angebot'),
                ['type' => 'steps', 'data' => [
                    'variant' => 'steps', 'eyebrow' => 'Ablauf', 'title' => 'So arbeiten wir', 'intro' => '',
                    'items' => [
                        ['meta' => 'Woche 1', 'title' => 'Klären', 'text' => 'Gespräch, Bestandsaufnahme, gemeinsames Ziel.'],
                        ['meta' => 'Woche 2–3', 'title' => 'Entwerfen', 'text' => 'Skizzen, Varianten, eine begründete Empfehlung.'],
                        ['meta' => 'Woche 4–6', 'title' => 'Bauen', 'text' => 'Umsetzung in kleinen, prüfbaren Schritten.'],
                        ['meta' => 'laufend', 'title' => 'Pflegen', 'text' => 'Schulung, Wartung, Verbesserungen.'],
                    ],
                ]],
                ['type' => 'cards', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'service', 'eyebrow' => 'Pakete', 'title' => 'Drei Größen, ein Maßstab', 'intro' => 'Beispielpreise – frei erfunden.', 'size' => 'm', 'ratio' => '4:3',
                    'items' => [
                        ['icon' => 'lightbulb', 'eyebrow' => 'Paket S', 'title' => 'Klarheit', 'text' => 'Bestandsaufnahme und Empfehlung in einem Dokument.', 'meta' => 'ab 900 €', 'link_label' => 'Anfragen', 'link' => '/kontakt'],
                        ['icon' => 'squares-four', 'eyebrow' => 'Paket M', 'title' => 'System', 'text' => 'Gestaltungssystem mit Farben, Schrift und Bausteinen.', 'meta' => 'ab 3.400 €', 'link_label' => 'Anfragen', 'link' => '/kontakt'],
                        ['icon' => 'rocket', 'eyebrow' => 'Paket L', 'title' => 'Website', 'text' => 'Konzept, Gestaltung und Umsetzung mit Schulung.', 'meta' => 'ab 8.200 €', 'link_label' => 'Anfragen', 'link' => '/kontakt'],
                    ],
                ]],
                ['type' => 'faq', 'data' => [
                    'variant' => 'split', 'eyebrow' => 'Fragen', 'title' => 'Häufige Fragen', 'intro' => 'Kurz beantwortet.',
                    'items' => [
                        ['q' => 'Wie lange dauert ein Projekt?', 'a' => '<p>Kleine Vorhaben zwei bis drei Wochen, eine Website meist sechs bis zehn Wochen.</p>'],
                        ['q' => 'Können wir die Website selbst pflegen?', 'a' => '<p>Ja. Wir schulen Ihr Team und dokumentieren alle Bausteine.</p>'],
                        ['q' => 'Arbeiten Sie auch aus der Ferne?', 'a' => '<p>Ja – Termine vor Ort sind möglich, aber nicht nötig.</p>'],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Haltung
        [
            'slug' => 'haltung', 'title' => 'Haltung', 'menu' => true,
            'meta_description' => 'Grundsätze und Arbeitsweise der fiktiven Werkstatt Beispiel.',
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'variant' => 'statement', 'eyebrow' => 'Haltung',
                    'title' => 'Weniger, aber *besser*.',
                    'text' => 'Wir reduzieren, bis nur noch das Wesentliche übrig ist – und machen dieses Wesentliche so gut wie möglich.',
                    'button_label' => 'Kontakt', 'button_link' => '/kontakt', 'button2_label' => '', 'button2_link' => '',
                    'points' => "Nützlich\nVerständlich\nUnaufdringlich\nLanglebig",
                ]],
                ['type' => 'quote', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'single', 'eyebrow' => '', 'title' => '',
                    'items' => [['text' => 'Ein Entwurf ist fertig, wenn man nichts mehr weglassen kann, ohne dass etwas fehlt.', 'name' => 'Leitsatz der Werkstatt', 'role' => 'Beispielzitat', 'image' => null]],
                ]],
                ['type' => 'principles', 'data' => [
                    'variant' => 'grid', 'eyebrow' => 'Grundsätze', 'title' => 'Woran wir unsere Arbeit prüfen', 'intro' => '',
                    'items' => [
                        ['title' => 'Nützlich', 'text' => 'Es erfüllt seinen Zweck – zuerst und vollständig.'],
                        ['title' => 'Verständlich', 'text' => 'Es erklärt sich durch Ordnung, nicht durch Anleitungen.'],
                        ['title' => 'Unaufdringlich', 'text' => 'Es lässt Raum für Inhalte und Menschen.'],
                        ['title' => 'Ehrlich', 'text' => 'Es verspricht nichts, was es nicht halten kann.'],
                        ['title' => 'Langlebig', 'text' => 'Es altert gut – in Form und Technik.'],
                        ['title' => 'Sparsam', 'text' => 'Es braucht wenig Daten, wenig Energie, wenig Aufmerksamkeit.'],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Kontakt
        [
            'slug' => 'kontakt', 'title' => 'Kontakt', 'menu' => true,
            'meta_description' => 'So erreichen Sie die fiktive Werkstatt Beispiel (Demo-Inhalt).',
            'blocks' => [
                $hero('Sprechen Sie uns an.', 'Wir antworten an Werktagen innerhalb von 48 Stunden.', 'Kontakt'),
                ['type' => 'contact', 'tunes' => ['anchor' => 'kontakt'], 'data' => [
                    'eyebrow' => '', 'title' => '', 'intro' => '', 'show_hours' => true, 'show_map' => true,
                    'form_table' => '', 'form_title' => '', 'submit_label' => '',
                    'note' => 'Bitte senden Sie uns keine vertraulichen Unterlagen per unverschlüsselter E-Mail.',
                ]],
            ],
        ],

        // ------------------------------------------------------------------ Rechtliches (nicht im Menü, verlinkt im Fußbereich)
        [
            'slug' => 'impressum', 'title' => 'Impressum', 'menu' => false,
            'blocks' => [
                $hero('Impressum', ''),
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'title' => '', 'text' =>
                    '<p><strong>Hinweis für die Betreiber dieser Website:</strong> Bitte ersetzen Sie diesen Platzhalter durch Ihr vollständiges Impressum (Anbieterkennzeichnung, z. B. nach § 5 DDG) und lassen Sie es im Zweifel rechtlich prüfen.</p>'
                    . '<h2>Angaben zum Anbieter</h2><p>[Name bzw. Firma]<br>[Straße und Hausnummer]<br>[PLZ Ort]</p>'
                    . '<h2>Vertreten durch</h2><p>[Vertretungsberechtigte Person]</p>'
                    . '<h2>Kontakt</h2><p>[Telefon]<br>[E-Mail-Adresse]</p>'
                    . '<h2>Registereintrag und Umsatzsteuer-ID</h2><p>[Registergericht, Registernummer, USt-IdNr. – falls vorhanden]</p>'
                    . '<h2>Verantwortlich für den Inhalt</h2><p>[Name, Anschrift]</p>']],
            ],
        ],
        [
            'slug' => 'datenschutz', 'title' => 'Datenschutz', 'menu' => false,
            'blocks' => [
                $hero('Datenschutzerklärung', ''),
                ['type' => 'richtext', 'data' => ['variant' => 'standard', 'title' => '', 'text' =>
                    '<p><strong>Hinweis für die Betreiber dieser Website:</strong> Bitte fügen Sie hier Ihre vollständige Datenschutzerklärung ein. Sie muss zu Ihrer tatsächlichen Nutzung passen (z. B. Hosting, E-Mail, eingebundene Dienste) und sollte rechtlich geprüft werden.</p>'
                    . '<h2>Verantwortliche Stelle</h2><p>[Name bzw. Firma, Anschrift, E-Mail-Adresse]</p>'
                    . '<h2>Technische Hinweise zu dieser Website</h2><p>Die Website setzt für Besucher keine Cookies, lädt Schriften vom eigenen Server und bindet keine Analyse- oder Tracking-Dienste ein. Karten werden über den eigenen Server geladen; Videos von YouTube oder Vimeo erst nach einem Klick. Die Einstellung „Videos künftig direkt laden“ wird nur im eigenen Browser gespeichert (lokaler Speicher, kein Cookie). [Bitte prüfen und an Ihre Nutzung anpassen.]</p>'
                    . '<h2>Ihre Rechte</h2><p>[Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit, Widerspruch, Beschwerde bei einer Aufsichtsbehörde]</p>']],
            ],
        ],
    ],
];
