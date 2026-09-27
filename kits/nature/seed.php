<?php
/*
 * Startinhalte des Kits „nature“ – werden beim ersten Aufruf einer neuen Website eingespielt.
 * Alle Inhalte sind frei erfunden und als Beispiel gekennzeichnet („Hofgut Wiesengrund – fiktiv“).
 * Texte in [eckigen Klammern] sind Platzhalter (erscheinen in der Übersicht der Verwaltung als offen).
 * Der Einstieg der Startseite zeigt ohne Bild eine Landschaft im Stil der Jahreszeit (Inline-SVG, keine Datei).
 * Zusätzlich (after): Hofladen, Termine (Führungen, Markttage – Liste und Kalender), Gruppen-Formular, „Erleben“ mit
 * allen Blöcken, erzeugte Illustrationen (tools/demo-content.php; entfernen mit tools/demo.php --remove).
 */

$day = fn(string $tag, string $von, string $bis, string $notiz = '') => ['tag' => $tag, 'von' => $von, 'bis' => $bis, 'pause_von' => '', 'pause_bis' => '', 'notiz' => $notiz];

$hero = fn(string $title, string $text, string $eyebrow = '') => [
    'type' => 'hero', 'tunes' => [],
    'data' => ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text],
];

$cta = [
    'type' => 'cta', 'tunes' => [],
    'data' => [
        'variant' => 'panel', 'eyebrow' => 'Besuch planen',
        'title' => 'Kommen Sie *vorbei* – mit Korb oder mit Fragen.',
        'text' => 'Hofladen, Führungen und Markttage: Die nächsten Termine stehen im Kalender.',
        'button_label' => 'Termine ansehen', 'button_link' => '/termine',
        'button2_label' => 'Anfahrt', 'button2_link' => '/kontakt',
    ],
];

return [
    'settings' => [
        'org_name' => 'Hofgut Wiesengrund',
        'short_name' => 'Hofgut Wiesengrund',
        'tagline' => 'Bio-Hof, Hofladen & Naturerlebnis – ein fiktives Beispiel.',
        'street' => 'Am Wiesengrund 1',
        'zip' => '12345',
        'city' => 'Musterfeld',
        'country' => '',
        'phone' => '0123 456789-0',
        'email' => 'hallo@example.com',
        'hours' => [$day('2', '09:00', '18:00'), $day('3', '09:00', '18:00'), $day('4', '09:00', '18:00'), $day('5', '09:00', '18:00'), $day('6', '08:00', '14:00')],
        'hours_note' => 'Hofladen. Montags und sonntags geschlossen.',
        'seasons' => [
            ['name' => 'Hofführungen', 'period' => 'April bis Oktober', 'text' => 'samstags 11 Uhr, Treffpunkt Hofladen'],
            ['name' => 'Hofcafé', 'period' => 'Mai bis September', 'text' => 'Sa und So 13–18 Uhr'],
            ['name' => 'Selbstpflücke', 'period' => 'Juni bis August', 'text' => 'Erdbeeren und Blumen, je nach Wetter'],
        ],
        'directions' => "Bus 7 bis „Wiesengrund“, dann 5 Minuten zu Fuß.\nDer Radweg führt direkt am Hof vorbei; Parkplätze am Hofladen.",
        'geo' => '51.1634,10.4477',
        'header_cta_label' => 'Termine',
        'header_cta_link' => '/termine',
        'header_status_label' => 'Hofladen',   // Kopfbereich: „Hofladen geöffnet“ am Kontakt-Chip (Core\HeaderActions)
        'footer_statement' => 'Kommen Sie vorbei.',
        'footer_text' => 'Demo-Website des Kits „nature“: Hofgut Wiesengrund, alle Namen, Preise, Termine und Stimmen sind frei erfunden.',
        'notice_active' => true,
        'notice_text' => 'Beispiel-Website: Das Hofgut Wiesengrund gibt es nicht – alle Inhalte sind frei erfunden.',
        'social' => [],
        'site_title' => 'Hofgut Wiesengrund – Bio-Hof, Hofladen & Naturerlebnis (fiktiv)',
        'site_title_suffix' => '',
        'default_meta_description' => 'Demo-Website des fiktiven Hofguts Wiesengrund: Bio-Hofladen, Hofführungen, Markttage und Naturerlebnis für Familien und Gruppen.',
        'schema_type' => 'LocalBusiness',
    ],

    // Hofladen, Termine, Formular, Musterseiten und Illustrationen nach den Seiten anlegen (Core\Seeder ruft 'after' auf)
    'after' => function () {
        require_once __DIR__ . '/tools/demo-content.php';
        nature_demo_install();
    },

    'page_refs' => [
        'imprint_page' => 'impressum',
        'privacy_page' => 'datenschutz',
    ],

    'pages' => [
        // ------------------------------------------------------------------ Startseite
        [
            'slug' => 'start', 'title' => 'Start', 'is_home' => true, 'menu' => false,
            'meta_description' => 'Hofgut Wiesengrund (fiktiv): Bio-Hofladen, Hofführungen, Markttage und Naturerlebnis.',
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'variant' => 'panel', 'eyebrow' => 'Bio-Hof seit 1911 (fiktiv)',
                    'title' => 'Zwischen Wiese, Acker und *Hofladen*.',
                    'text' => 'Wir bauen Gemüse und Getreide an, pflegen alte Obstsorten und halten Bienen – und zeigen gern, wie das alles zusammenhängt.',
                    'button_label' => 'Zum Hofladen', 'button_link' => '/hofladen',
                    'button2_label' => 'Führungen & Markttage', 'button2_link' => '/termine',
                    'points' => "Fläche: 42 ha\nObstbäume: 180\nBienenvölker: 12\nHofladen: Di–Sa",
                    'panel_label' => 'Hofgut Wiesengrund',
                ]],
                ['type' => 'features', 'tunes' => ['background' => 'white'], 'data' => [
                    'variant' => 'panel', 'eyebrow' => 'Auf dem Hof', 'title' => 'Vier Wege, uns kennenzulernen',
                    'intro' => 'Einkaufen, zuschauen, mitmachen, einkehren – vom Frühjahr bis in den Winter.', 'size' => 'm',
                    'items' => [
                        ['icon' => 'storefront', 'title' => 'Hofladen', 'text' => 'Gemüse, Obst, Brot, Eier und Honig – vieles aus eigener Ernte.', 'link_label' => '', 'link' => '/hofladen'],
                        ['icon' => 'path', 'title' => 'Führungen', 'text' => 'Samstags über Acker, Weide und Obstwiese – für alle Generationen.', 'link_label' => '', 'link' => '/termine'],
                        ['icon' => 'plant', 'title' => 'Mitmachen', 'text' => 'Kurse für Obstbaumschnitt, Kräuter und Brot, Tage für Schulklassen.', 'link_label' => '', 'link' => '/termine/gruppen'],
                        ['icon' => 'coffee', 'title' => 'Hofcafé', 'text' => 'Kuchen aus dem Holzofen unter dem Nussbaum – am Wochenende im Sommer.', 'link_label' => '', 'link' => '/kontakt'],
                    ],
                ]],
                ['type' => 'stats', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'dials', 'eyebrow' => 'Der Hof in Zahlen', 'title' => 'Klein genug, um alles selbst zu machen',
                    'intro' => 'Beispielwerte eines fiktiven Hofs.',
                    'items' => [
                        ['value' => '42 ha', 'label' => 'Acker, Weide, Wald', 'level' => '', 'text' => 'davon 6 ha Blühstreifen'],
                        ['value' => '64 %', 'label' => 'Eigene Ernte im Laden', 'level' => '64', 'text' => 'Rest von Nachbarhöfen'],
                        ['value' => '31', 'label' => 'Alte Apfelsorten', 'level' => '', 'text' => 'auf der Streuobstwiese'],
                        ['value' => '12', 'label' => 'Bienenvölker', 'level' => '', 'text' => 'am Waldrand'],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Hof (Über uns)
        [
            'slug' => 'hof', 'title' => 'Der Hof', 'menu' => true,
            'meta_description' => 'Geschichte, Grundsätze und Menschen des fiktiven Hofguts Wiesengrund.',
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'variant' => 'statement', 'eyebrow' => 'Der Hof',
                    'title' => 'Wirtschaften mit dem, was *wächst*.',
                    'text' => 'Seit vier Generationen bewirtschaften wir die Flächen am Wiesengrund – heute ökologisch, mit Fruchtfolge, Hecken und viel Handarbeit.',
                    'button_label' => 'Zum Hofladen', 'button_link' => '/hofladen', 'button2_label' => 'Kontakt', 'button2_link' => '/kontakt',
                    'points' => "Gegründet: 1911 (fiktiv)\nUmstellung auf Bio: 1994\nMitarbeitende: 9\nAzubis: 2",
                ]],
                ['type' => 'principles', 'data' => [
                    'variant' => 'grid', 'eyebrow' => 'So wirtschaften wir', 'title' => 'Fünf Grundsätze für Boden, Tier und Mensch', 'intro' => '',
                    'items' => [
                        ['title' => 'Boden zuerst', 'text' => 'Weite Fruchtfolgen, Zwischenfrüchte und Kompost halten den Boden lebendig.'],
                        ['title' => 'Vielfalt statt Einfalt', 'text' => 'Hecken, Blühstreifen und alte Sorten geben Insekten und Vögeln Raum.'],
                        ['title' => 'Kurze Wege', 'text' => 'Was wir ernten, verkaufen wir im eigenen Laden oder in der Nachbarschaft.'],
                        ['title' => 'Offene Stalltüren', 'text' => 'Wer wissen will, wie Lebensmittel entstehen, darf zuschauen und fragen.'],
                        ['title' => 'Faire Arbeit', 'text' => 'Verlässliche Arbeitszeiten und ein Lohn, von dem man leben kann.'],
                    ],
                ]],
                ['type' => 'steps', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'timeline', 'eyebrow' => 'Hofgeschichte', 'title' => 'Vier Generationen am Wiesengrund', 'intro' => 'Eine erfundene Chronik.',
                    'items' => [
                        ['meta' => '1911', 'title' => 'Der erste Stall', 'text' => 'Ein kleiner Hof mit Kühen, Kartoffeln und einer Obstwiese entsteht.'],
                        ['meta' => '1962', 'title' => 'Neue Scheune', 'text' => 'Die Scheune aus Lärchenholz steht bis heute – jetzt als Hofcafé.'],
                        ['meta' => '1994', 'title' => 'Umstellung auf Bio', 'text' => 'Ohne chemischen Pflanzenschutz, mit Kleegras und Mist als Dünger.'],
                        ['meta' => '2012', 'title' => 'Hofladen und Führungen', 'text' => 'Der Laden öffnet, Schulklassen kommen regelmäßig zu Besuch.'],
                        ['meta' => 'heute', 'title' => 'Hecken und Blühstreifen', 'text' => 'Zwei Kilometer neue Hecken säumen die Felder.'],
                    ],
                ]],
                ['type' => 'team', 'data' => [
                    'variant' => 'grid', 'eyebrow' => 'Menschen', 'title' => 'Wer hier arbeitet', 'intro' => 'Alle Personen sind frei erfunden.', 'size' => 's',
                    'items' => [
                        ['name' => 'Mara Lindner', 'role' => 'Hofleitung, Ackerbau', 'text' => 'Plant die Fruchtfolge und fährt am liebsten Hacke statt Spritze.', 'image' => null, 'email' => ''],
                        ['name' => 'Jonas Albers', 'role' => 'Tiere und Weide', 'text' => 'Kümmert sich um Rinder, Hühner und die Weidezäune.', 'image' => null, 'email' => ''],
                        ['name' => 'Ida Brandt', 'role' => 'Hofladen und Café', 'text' => 'Backt den Kuchen und kennt jede Apfelsorte beim Namen.', 'image' => null, 'email' => ''],
                        ['name' => 'Emil Kaiser', 'role' => 'Obstwiese und Bienen', 'text' => 'Schneidet 180 Bäume im Jahr und betreut zwölf Völker.', 'image' => null, 'email' => ''],
                        ['name' => 'Frieda Lorenz', 'role' => 'Führungen, Umweltbildung', 'text' => 'Zeigt Kindern, wo das Brot herkommt – mit Mühle und Ofen.', 'image' => null, 'email' => 'fuehrungen@example.com'],
                    ],
                ]],
                ['type' => 'quote', 'tunes' => ['background' => 'tint'], 'data' => [
                    'variant' => 'single', 'eyebrow' => '', 'title' => '',
                    'items' => [['text' => 'Ein Acker ist kein Werkstück. Wir arbeiten mit ihm, nicht gegen ihn.', 'name' => 'Leitsatz des Hofs', 'role' => 'Beispielzitat', 'image' => null]],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Kontakt
        [
            'slug' => 'kontakt', 'title' => 'Kontakt', 'menu' => true,
            'meta_description' => 'Anfahrt, Öffnungs- und Saisonzeiten des fiktiven Hofguts Wiesengrund (Demo-Inhalt).',
            'blocks' => [
                $hero('Besuchen Sie uns.', 'Der Hofladen ist dienstags bis samstags geöffnet; Führungen und Hofcafé gibt es in der Saison.', 'Kontakt & Anfahrt'),
                ['type' => 'contact', 'tunes' => ['anchor' => 'kontakt'], 'data' => [
                    'eyebrow' => '', 'title' => '', 'intro' => '', 'show_hours' => true, 'show_map' => true,
                    'form_table' => '', 'form_title' => '', 'submit_label' => '',
                    'note' => 'Beispieladresse – frei erfunden. Der Kartenpunkt liegt auf einer Wiese.',
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
                    . '<h2>Kontrollstelle (Öko-Kontrolle) und Umsatzsteuer-ID</h2><p>[Code der Öko-Kontrollstelle, Registereintrag, USt-IdNr. – falls vorhanden]</p>'
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
