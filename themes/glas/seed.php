<?php
/*
 * Startinhalte des Kits „glas“ – werden beim ersten Aufruf einer neuen Website eingespielt.
 * Alle Inhalte sind frei erfunden und als Beispiel gekennzeichnet („Lumen Labs – App-Entwicklung & UX (fiktiv)“).
 * Texte in [eckigen Klammern] sind Platzhalter (erscheinen in der Übersicht der Verwaltung als offen).
 * Hauptseiten ohne Bilder – Farbfeld, Aurora und Glas brauchen keine Dateien.
 * Zusätzlich (after): Musterseiten „Labor“ mit allen Blöcken, Datentabellen (Projekte mit Detailseite, Termine,
 * öffentliches Formular) und erzeugten Bildern (tools/demo-content.php; entfernen mit tools/demo.php --remove).
 */

$workday = fn(string $tag) => ['tag' => $tag, 'von' => '09:00', 'bis' => '18:00', 'pause_von' => '', 'pause_bis' => '', 'notiz' => ''];

$hero = fn(string $title, string $text, string $eyebrow = '') => [
    'type' => 'hero', 'tunes' => [],
    'data' => ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text],
];

$cta = [
    'type' => 'cta', 'tunes' => [],
    'data' => [
        'variant' => 'aurora', 'eyebrow' => 'Nächster Schritt',
        'title' => 'Erzählen Sie uns von Ihrer *Idee*.',
        'text' => 'Ein kurzes Gespräch zeigt, ob wir passen – klar, freundlich und unverbindlich. (Demo: es meldet sich niemand.)',
        'button_label' => 'Projekt anfragen', 'button_link' => '/kontakt',
        'button2_label' => 'E-Mail schreiben', 'button2_link' => 'email',
    ],
];

return [
    'settings' => [
        'org_name' => 'Lumen Labs (fiktiv)',
        'short_name' => 'Lumen Labs',
        'tagline' => 'App-Entwicklung & UX – ein fiktives Beispielstudio.',
        'street' => '',
        'zip' => '',
        'city' => '',
        'country' => '',
        'phone' => '',
        'email' => 'hallo@example.com',
        'hours' => [$workday('1'), $workday('2'), $workday('3'), $workday('4'),
            ['tag' => '5', 'von' => '09:00', 'bis' => '15:00', 'pause_von' => '', 'pause_bis' => '', 'notiz' => '']],
        'hours_note' => 'Termine außerhalb dieser Zeiten nach Vereinbarung – auch per Video.',
        'geo' => '',
        'header_cta_label' => 'Projekt anfragen',
        'header_cta_link' => '/kontakt',
        'footer_statement' => 'Klare Apps. Klares Glas.',
        'footer_text' => 'Demo-Inhalte des Kits „Glas“: Lumen Labs, Namen, Projekte, Zahlen und Stimmen sind frei erfunden.',
        'notice_active' => false,
        'notice_text' => '',
        'social' => [],
        'site_title' => 'Lumen Labs – App-Entwicklung & UX (fiktiv)',
        'site_title_suffix' => '',
        'default_meta_description' => 'Demo-Website des fiktiven Studios Lumen Labs: App-Entwicklung, UX- und UI-Design, barrierefreie Web-Apps – klar, schnell und freundlich.',
        'schema_type' => 'ProfessionalService',
    ],

    // Musterseiten nach den Seiten anlegen (Core\Seeder ruft 'after' auf)
    'after' => function () {
        require_once __DIR__ . '/tools/demo-content.php';
        glas_demo_install();
    },

    'page_refs' => [
        'imprint_page' => 'impressum',
        'privacy_page' => 'datenschutz',
    ],

    'pages' => [
        // ------------------------------------------------------------------ Startseite
        [
            'slug' => 'start', 'title' => 'Start', 'is_home' => true, 'menu' => false,
            'meta_description' => 'Lumen Labs (fiktiv): App-Entwicklung und UX-Design – vom ersten Klick-Prototyp bis zur App im Store.',
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'variant' => 'aurora', 'eyebrow' => 'App-Entwicklung & UX · fiktiv',
                    'title' => 'Apps, die sich *klar* anfühlen.',
                    'text' => 'Wir gestalten und bauen Apps für iOS, Android und das Web – vom ersten Klick-Prototyp bis zur Veröffentlichung. Barrierearm, schnell und freundlich.',
                    'button_label' => 'Leistungen ansehen', 'button_link' => '/leistungen',
                    'button2_label' => 'Musterseiten', 'button2_link' => '/labor',
                    'points' => "Apps veröffentlicht: 42\nStore-Bewertung (von 5): 4,8\nZeit bis zum Prototyp: 10 Tage",
                    'image' => null, 'ratio' => '4:5',
                ]],
                ['type' => 'features', 'data' => [
                    'variant' => 'cards', 'eyebrow' => 'Leistungen', 'title' => 'Vier Dinge, die wir richtig gut können',
                    'intro' => 'Ein Team für Strategie, Gestaltung und Technik – ohne Übergaben, bei denen Wissen verloren geht.', 'size' => 's',
                    'items' => [
                        ['icon' => 'compass', 'title' => 'Produktstrategie', 'text' => 'Ziele, Zielgruppen und der kleinste sinnvolle Umfang – bevor eine Zeile Code entsteht.', 'link_label' => '', 'link' => '/leistungen'],
                        ['icon' => 'pencil-ruler', 'title' => 'UX & UI', 'text' => 'Klick-Prototypen, Tests mit echten Menschen und ein Gestaltungssystem, das mitwächst.', 'link_label' => '', 'link' => '/leistungen'],
                        ['icon' => 'device-mobile', 'title' => 'iOS, Android & Web', 'text' => 'Native Apps und Web-Apps aus einer Hand – mit Tests, Monitoring und sauberer Übergabe.', 'link_label' => '', 'link' => '/leistungen'],
                        ['icon' => 'wheelchair', 'title' => 'Barrierefreiheit', 'text' => 'Kontraste, Screenreader, Tastatur: geprüft nach WCAG 2.2 AA, nicht nachträglich angeklebt.', 'link_label' => '', 'link' => '/leistungen'],
                    ],
                ]],
                ['type' => 'stats', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'rings', 'eyebrow' => 'In Zahlen', 'title' => 'Messbar statt laut',
                    'intro' => 'Beispielwerte eines fiktiven Studios.',
                    'items' => [
                        ['value' => '42', 'label' => 'Apps veröffentlicht', 'level' => '', 'text' => 'seit 2016 (fiktiv)'],
                        ['value' => '96 %', 'label' => 'Absturzfreie Sitzungen', 'level' => '96', 'text' => 'Median aller Projekte'],
                        ['value' => '4,8', 'label' => 'Store-Bewertung', 'level' => '96', 'text' => 'Durchschnitt, Beispiel'],
                        ['value' => '0', 'label' => 'Tracking-Cookies', 'level' => '0', 'text' => 'auf dieser Website'],
                    ],
                ]],
                ['type' => 'steps', 'data' => [
                    'variant' => 'steps', 'eyebrow' => 'Ablauf', 'title' => 'Vom Einfall zur App', 'intro' => '',
                    'items' => [
                        ['meta' => 'Woche 1', 'title' => 'Verstehen', 'text' => 'Workshop, Zielbild, der kleinste sinnvolle Umfang.'],
                        ['meta' => 'Woche 2–3', 'title' => 'Prototyp', 'text' => 'Klickbar, getestet mit fünf echten Menschen.'],
                        ['meta' => 'Woche 4–10', 'title' => 'Bauen', 'text' => 'In zweiwöchigen Schritten, jederzeit ausprobierbar.'],
                        ['meta' => 'danach', 'title' => 'Wachsen', 'text' => 'Auswertung, Pflege, neue Funktionen.'],
                    ],
                ]],
                ['type' => 'quote', 'data' => [
                    'variant' => 'single', 'eyebrow' => '', 'title' => '',
                    'items' => [['text' => 'Gute Apps fühlen sich an wie klares Glas: Man sieht nicht die Technik, sondern das, was man erledigen will.', 'name' => 'Leitsatz von Lumen Labs', 'role' => 'Beispielzitat (fiktiv)', 'image' => null]],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Leistungen
        [
            'slug' => 'leistungen', 'title' => 'Leistungen', 'menu' => true,
            'meta_description' => 'Leistungen des fiktiven Studios Lumen Labs: Produktstrategie, UX & UI, App-Entwicklung, Barrierefreiheit.',
            'blocks' => [
                $hero('Leistungen', 'Drei Pakete, ein Maßstab: Sie wissen jederzeit, woran wir arbeiten und was es kostet.', 'Angebot'),
                ['type' => 'cards', 'data' => [
                    'variant' => 'service', 'eyebrow' => 'Pakete', 'title' => 'Drei Größen, klar abgegrenzt', 'intro' => 'Beispielpreise – frei erfunden.', 'size' => 'm', 'ratio' => '4:3',
                    'items' => [
                        ['icon' => 'lightbulb', 'eyebrow' => 'Paket S', 'title' => 'Klarheit', 'text' => 'Workshop, Zielbild und ein klickbarer Prototyp für die wichtigste Aufgabe.', 'meta' => 'ab 4.800 €', 'link_label' => 'Anfragen', 'link' => '/kontakt'],
                        ['icon' => 'squares-four', 'eyebrow' => 'Paket M', 'title' => 'Gestaltungssystem', 'text' => 'Farben, Schrift, Bausteine und Regeln – für App und Website gleichermaßen.', 'meta' => 'ab 12.000 €', 'link_label' => 'Anfragen', 'link' => '/kontakt'],
                        ['icon' => 'rocket', 'eyebrow' => 'Paket L', 'title' => 'App bis zum Store', 'text' => 'Konzept, Gestaltung, Entwicklung, Tests und Veröffentlichung.', 'meta' => 'ab 48.000 €', 'link_label' => 'Anfragen', 'link' => '/kontakt'],
                    ],
                ]],
                ['type' => 'steps', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'timeline', 'eyebrow' => 'Ablauf', 'title' => 'So arbeiten wir', 'intro' => '',
                    'items' => [
                        ['meta' => 'Tag 1', 'title' => 'Kennenlernen', 'text' => 'Gespräch, Ziele, Rahmen – und die Frage, ob eine App überhaupt die beste Lösung ist.'],
                        ['meta' => 'Woche 1–2', 'title' => 'Prototyp', 'text' => 'Klickbar, in einem echten Gerät, getestet mit Menschen aus der Zielgruppe.'],
                        ['meta' => 'Woche 3–10', 'title' => 'Entwicklung', 'text' => 'In kurzen Schritten mit Vorschau-Versionen auf Ihrem Telefon.'],
                        ['meta' => 'laufend', 'title' => 'Begleitung', 'text' => 'Updates, Auswertung, Pflege – so lange Sie möchten.'],
                    ],
                ]],
                ['type' => 'faq', 'data' => [
                    'variant' => 'split', 'eyebrow' => 'Fragen', 'title' => 'Häufige Fragen', 'intro' => 'Kurz beantwortet.',
                    'items' => [
                        ['q' => 'Native App oder Web-App?', 'a' => '<p>Das hängt von Ihren Nutzerinnen und Nutzern ab. Wir empfehlen, was am wenigsten Aufwand für das gleiche Ergebnis bedeutet – oft ist es eine gute Web-App.</p>'],
                        ['q' => 'Wie lange dauert ein Projekt?', 'a' => '<p>Ein Prototyp zwei Wochen, eine erste Version im Store meist acht bis zwölf Wochen.</p>'],
                        ['q' => 'Ist die App barrierefrei?', 'a' => '<p>Ja. Wir prüfen Kontraste, Schriftgrößen, Screenreader und Tastaturbedienung von Anfang an.</p>'],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Studio
        [
            'slug' => 'studio', 'title' => 'Studio', 'menu' => true,
            'meta_description' => 'Das fiktive Team von Lumen Labs und seine Arbeitsweise.',
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'variant' => 'statement', 'eyebrow' => 'Studio',
                    'title' => 'Klein genug zum Zuhören, groß genug zum *Bauen*.',
                    'text' => 'Sechs Menschen (fiktiv) aus Gestaltung, Entwicklung und Forschung – an einem Tisch, in einem Projekt.',
                    'button_label' => 'Kontakt', 'button_link' => '/kontakt', 'button2_label' => '', 'button2_link' => '',
                    'points' => "Gegründet: 2016\nTeam: 6\nSprachen: Deutsch, Englisch",
                    'image' => null, 'ratio' => '16:9',
                ]],
                ['type' => 'team', 'data' => [
                    'variant' => 'cards', 'eyebrow' => 'Team', 'title' => 'Die Menschen hinter den Apps', 'intro' => 'Alle Personen sind erfunden – ohne Fotos, mit Initialen.', 'size' => 's',
                    'items' => [
                        ['name' => 'Mara Lindqvist', 'role' => 'Produkt & Strategie', 'text' => 'Fragt so lange „wofür?“, bis der Umfang klein und klar ist.', 'image' => null, 'email' => ''],
                        ['name' => 'Jonas Adeyemi', 'role' => 'UX-Forschung', 'text' => 'Testet Prototypen mit echten Menschen – am liebsten früh.', 'image' => null, 'email' => ''],
                        ['name' => 'Ilka Brandt', 'role' => 'UI-Gestaltung', 'text' => 'Baut Gestaltungssysteme, die auch in zwei Jahren noch passen.', 'image' => null, 'email' => ''],
                        ['name' => 'Tomás Ferreira', 'role' => 'iOS & Android', 'text' => 'Schreibt Apps, die schnell starten und selten abstürzen.', 'image' => null, 'email' => ''],
                    ],
                ]],
                ['type' => 'quote', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'grid', 'eyebrow' => 'Stimmen (Beispiele)', 'title' => 'Was Kundinnen sagen',
                    'items' => [
                        ['text' => 'Nach zwei Wochen hatten wir einen Prototyp, den wir unseren Leuten in die Hand geben konnten.', 'name' => 'Kim Muster', 'role' => 'Verein (fiktiv)', 'image' => null],
                        ['text' => 'Endlich eine App, die auch meine Mutter ohne Anleitung bedient.', 'name' => 'Sam Beispiel', 'role' => 'Praxis (fiktiv)', 'image' => null],
                        ['text' => 'Klar im Prozess, freundlich im Ton, pünktlich im Store.', 'name' => 'Jo Probe', 'role' => 'Handwerksbetrieb (fiktiv)', 'image' => null],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Kontakt
        [
            'slug' => 'kontakt', 'title' => 'Kontakt', 'menu' => true,
            'meta_description' => 'So erreichen Sie das fiktive Studio Lumen Labs (Demo-Inhalt).',
            'blocks' => [
                $hero('Sprechen wir über Ihre App.', 'Wir antworten an Werktagen innerhalb von 24 Stunden.', 'Kontakt'),
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
                ['type' => 'richtext', 'data' => ['variant' => 'glass', 'title' => '', 'text' =>
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
                ['type' => 'richtext', 'data' => ['variant' => 'glass', 'title' => '', 'text' =>
                    '<p><strong>Hinweis für die Betreiber dieser Website:</strong> Bitte fügen Sie hier Ihre vollständige Datenschutzerklärung ein. Sie muss zu Ihrer tatsächlichen Nutzung passen (z. B. Hosting, E-Mail, eingebundene Dienste) und sollte rechtlich geprüft werden.</p>'
                    . '<h2>Verantwortliche Stelle</h2><p>[Name bzw. Firma, Anschrift, E-Mail-Adresse]</p>'
                    . '<h2>Technische Hinweise zu dieser Website</h2><p>Die Website setzt für Besucher keine Cookies, lädt Schriften vom eigenen Server und bindet keine Analyse- oder Tracking-Dienste ein. Karten werden über den eigenen Server geladen; Videos von YouTube oder Vimeo erst nach einem Klick. Die Einstellung „Videos künftig direkt laden“ wird nur im eigenen Browser gespeichert (lokaler Speicher, kein Cookie). [Bitte prüfen und an Ihre Nutzung anpassen.]</p>'
                    . '<h2>Ihre Rechte</h2><p>[Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit, Widerspruch, Beschwerde bei einer Aufsichtsbehörde]</p>']],
            ],
        ],
    ],
];
