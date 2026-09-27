<?php
/*
 * Startinhalte des Themes „fluid“ – werden beim ersten Aufruf einer neuen Website eingespielt.
 * Alle Inhalte sind frei erfunden und als Demo gekennzeichnet („Studio Beispiel (Demo)“, Beispielwerte, Beispielzitate).
 * Texte in [eckigen Klammern] sind Platzhalter (erscheinen in der Übersicht der Verwaltung als offen).
 * Hauptseiten ohne Bilder – alle Blöcke sehen auch ohne Bilder vollständig aus.
 * Zusätzlich (after): Seitenbaum „Showcase“ mit allen Blöcken und Varianten, Demo-Datentabellen und erzeugten Bildern
 * (tools/demo-content.php; entfernen mit php kits/fluid/tools/demo.php --remove).
 * Farben, Schriften, Kopf und Fuß: Verwaltung → Design (Standard = Vorlage „Fluid Standard“).
 */

$workday = fn(string $tag) => ['tag' => $tag, 'von' => '09:00', 'bis' => '17:00', 'pause_von' => '', 'pause_bis' => '', 'notiz' => ''];

$hero = fn(string $title, string $text, string $eyebrow = '') => [
    'type' => 'hero', 'tunes' => ['background' => 'muted'],
    'data' => ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text],
];

$cta = [
    'type' => 'cta', 'tunes' => ['background' => 'accent'],
    'data' => [
        'variant' => 'band', 'eyebrow' => '',
        'title' => 'Lassen Sie uns über Ihr Vorhaben sprechen.',
        'text' => 'Ein kurzes Gespräch genügt, um herauszufinden, ob und wie wir Sie unterstützen können – unverbindlich.',
        'button_label' => 'Kontakt aufnehmen', 'button_link' => '/kontakt',
        'button2_label' => 'E-Mail schreiben', 'button2_link' => 'email',
    ],
];

return [
    'settings' => [
        'org_name' => 'Studio Beispiel (Demo)',
        'short_name' => 'Studio Beispiel',
        'tagline' => 'Gestaltung und Umsetzung, die mit Ihnen mitwächst.',
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
        'header_cta_label' => 'Projekt besprechen',
        'header_cta_link' => '/kontakt',
        'footer_statement' => 'Lassen Sie uns etwas Gutes bauen.',
        'footer_text' => 'Demo-Inhalte des Kits „Fluid“: Namen, Zahlen und Zitate sind frei erfunden und dienen nur als Beispiel.',
        'notice_active' => false,
        'notice_text' => '',
        'social' => [],
        'site_title' => 'Studio Beispiel (Demo) – Gestaltung und Umsetzung',
        'site_title_suffix' => '',
        'default_meta_description' => 'Demo-Website des fiktiven Studio Beispiel: ein breakpointloses Kit für Unternehmen, Praxen, Vereine, Gastronomie und Kultur.',
        'schema_type' => 'Organization',
    ],

    // Showcase nach den Seiten anlegen (Core\Seeder ruft 'after' auf)
    'after' => function () {
        require_once __DIR__ . '/tools/demo-content.php';
        fluid_demo_install();
    },

    'page_refs' => [
        'imprint_page' => 'impressum',
        'privacy_page' => 'datenschutz',
    ],

    'pages' => [
        // ------------------------------------------------------------------ Startseite
        [
            'slug' => 'start', 'title' => 'Start', 'is_home' => true, 'menu' => false,
            'meta_description' => 'Demo-Website des fiktiven Studio Beispiel – Gestaltung und Umsetzung, die mitwächst.',
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'variant' => 'type', 'eyebrow' => 'Studio Beispiel (Demo)',
                    'title' => 'Websites, die *mitwachsen*.',
                    'text' => 'Wir planen, gestalten und pflegen Websites für Betriebe, Praxen, Vereine und Kultur – klar im Aufbau, schnell im Laden, auf jedem Bildschirm gut lesbar.',
                    'button_label' => 'Leistungen ansehen', 'button_link' => '/leistungen',
                    'button2_label' => 'Showcase ansehen', 'button2_link' => '/showcase',
                    'points' => "Barrierearm nach WCAG 2.2\nOhne Cookies und Tracking\nSelbst pflegbar",
                ]],
                ['type' => 'logos', 'tunes' => ['spaceTop' => 'none'], 'data' => ['variant' => 'marquee', 'eyebrow' => '', 'title' => 'Beispielhafte Auftraggeber (fiktiv)', 'items' => [
                    ['name' => 'Nordlicht Werkstatt', 'link' => '', 'image' => null], ['name' => 'Kanzlei Aster', 'link' => '', 'image' => null],
                    ['name' => 'Praxis am Park', 'link' => '', 'image' => null], ['name' => 'Verein Beispielstadt', 'link' => '', 'image' => null],
                    ['name' => 'Café Morgenrot', 'link' => '', 'image' => null], ['name' => 'Museum Muster', 'link' => '', 'image' => null],
                ]]],
                ['type' => 'features', 'data' => [
                    'variant' => 'cards', 'eyebrow' => 'Leistungen', 'title' => 'Was wir für Sie tun',
                    'intro' => 'Drei Bereiche, ein Ansprechpartner – von der ersten Idee bis zur laufenden Pflege.', 'size' => 'm',
                    'items' => [
                        ['icon' => 'compass', 'title' => 'Konzept', 'text' => 'Ziele, Zielgruppen und Inhalte ordnen – bevor gestaltet wird.', 'link_label' => 'Mehr erfahren', 'link' => '/leistungen'],
                        ['icon' => 'palette', 'title' => 'Gestaltung', 'text' => 'Ein Erscheinungsbild, das auf jedem Gerät funktioniert – ohne Sonderlösungen.', 'link_label' => 'Mehr erfahren', 'link' => '/leistungen'],
                        ['icon' => 'rocket', 'title' => 'Umsetzung', 'text' => 'Schnell, barrierearm und datensparsam – mit Schulung für Ihr Team.', 'link_label' => 'Mehr erfahren', 'link' => '/leistungen'],
                    ],
                ]],
                ['type' => 'bento', 'tunes' => ['background' => 'muted'], 'data' => [
                    'eyebrow' => 'Auf einen Blick', 'title' => 'Warum „fluid“?', 'intro' => 'Beispielwerte – frei erfunden.',
                    'items' => [
                        ['size' => 'big', 'tone' => 'dark', 'icon' => 'sparkle', 'eyebrow' => 'Ohne Breakpoints', 'title' => 'Jedes Element passt sich seinem Platz an – nicht der Bildschirmbreite.', 'text' => 'Container-Queries, fließende Schrift und Raster, die sich selbst füllen.', 'link' => '/showcase'],
                        ['size' => 's', 'tone' => 'highlight', 'eyebrow' => '9', 'title' => 'geprüfte Vorlagen', 'text' => 'Kanzlei, Handwerk, Praxis, Agentur …'],
                        ['size' => 's', 'tone' => 'card', 'icon' => 'shield-check', 'title' => 'WCAG 2.2 AA', 'text' => 'Kontraste hell und dunkel geprüft.'],
                        ['size' => 'wide', 'tone' => 'accent', 'eyebrow' => '0', 'title' => 'externe Anfragen und Cookies', 'text' => 'Schriften, Karten und Videos-Vorschauen kommen vom eigenen Server.'],
                        ['size' => 's', 'tone' => 'tint', 'icon' => 'lightning', 'title' => 'Schnell', 'text' => 'Nur das CSS, das die Seite braucht.'],
                        ['size' => 's', 'tone' => 'card', 'icon' => 'sun', 'title' => 'Hell und dunkel', 'text' => 'Folgt der Einstellung des Geräts.'],
                        ['size' => 'wide', 'tone' => 'highlight', 'icon' => 'palette', 'eyebrow' => '', 'title' => 'Neun Vorlagen, ein Klick', 'text' => 'Von der Kanzlei bis zum Museum – im Style-Editor anpassen.', 'link' => '/showcase'],
                    ],
                ]],
                ['type' => 'stats', 'data' => ['variant' => 'row', 'eyebrow' => 'In Zahlen', 'title' => 'Beispielwerte', 'intro' => '', 'items' => [
                    ['value' => '120+', 'label' => 'Projekte', 'text' => 'für Betriebe, Praxen und Vereine'],
                    ['value' => '< 30 KB', 'label' => 'CSS pro Seite', 'text' => 'Grundlage, ohne Blöcke'],
                    ['value' => '8', 'label' => 'Schriften', 'text' => 'selbst gehostet, variabel'],
                    ['value' => '100 %', 'label' => 'eigene Server', 'text' => 'keine Einbindungen Dritter'],
                ]]],
                ['type' => 'steps', 'tunes' => ['background' => 'tint'], 'data' => ['variant' => 'process', 'eyebrow' => 'Ablauf', 'title' => 'So arbeiten wir', 'intro' => '', 'items' => [
                    ['meta' => 'Woche 1', 'title' => 'Kennenlernen', 'text' => 'Ziele, Rahmen und Ansprechpersonen klären.', 'icon' => ''],
                    ['meta' => 'Woche 2–3', 'title' => 'Konzept', 'text' => 'Vorschlag mit Aufwand und Terminen.', 'icon' => ''],
                    ['meta' => 'Woche 4–8', 'title' => 'Umsetzung', 'text' => 'Kurze, regelmäßige Abstimmungen.', 'icon' => ''],
                    ['meta' => 'Danach', 'title' => 'Pflege', 'text' => 'Sie pflegen selbst – wir helfen bei Bedarf.', 'icon' => ''],
                ]]],
                ['type' => 'quote', 'data' => ['variant' => 'reel', 'eyebrow' => 'Stimmen', 'title' => 'Was Auftraggeber sagen', 'items' => [
                    ['text' => 'Beispielzitat: Endlich eine Website, die wir selbst pflegen können – und die auf dem Handy genauso gut aussieht.', 'name' => 'Kim Muster', 'role' => 'Verein Beispielstadt (fiktiv)', 'image' => null],
                    ['text' => 'Beispielzitat: Schnelle Rückmeldungen und ein klarer Plan – genau das hatten wir gesucht.', 'name' => 'Sam Beispiel', 'role' => 'Nordlicht Werkstatt (fiktiv)', 'image' => null],
                    ['text' => 'Beispielzitat: Die Übergabe war so gut dokumentiert, dass wir allein weitermachen konnten.', 'name' => 'Jo Probe', 'role' => 'Praxis am Park (fiktiv)', 'image' => null],
                    ['text' => 'Beispielzitat: Keine Cookie-Banner, keine Tracker – und trotzdem finden uns die Leute.', 'name' => 'Alex Test', 'role' => 'Café Morgenrot (fiktiv)', 'image' => null],
                ]]],
                ['type' => 'faq', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'split', 'eyebrow' => 'FAQ', 'title' => 'Häufige Fragen', 'intro' => 'Kurz beantwortet.', 'items' => [
                    ['q' => 'Was heißt „breakpointlos“?', 'a' => '<p>Die Gestaltung richtet sich nach dem Platz, den ein Element tatsächlich hat – nicht nach festen Bildschirmbreiten. So passt alles auch in schmale Spalten, auf Tablets im Querformat oder in sehr breite Fenster.</p>'],
                    ['q' => 'Kann ich Farben und Schriften selbst ändern?', 'a' => '<p>Ja – unter Verwaltung → Design. Neun Vorlagen sind hell und dunkel auf Lesbarkeit geprüft.</p>'],
                    ['q' => 'Sind die Inhalte echt?', 'a' => '<p>Nein. Alle Namen, Zahlen und Zitate dieser Demo sind frei erfunden.</p>'],
                ]]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Leistungen
        [
            'slug' => 'leistungen', 'title' => 'Leistungen', 'menu' => true,
            'meta_description' => 'Konzept, Gestaltung, Umsetzung und Pflege – die Leistungen des fiktiven Studio Beispiel (Demo).',
            'blocks' => [
                $hero('Leistungen', 'Von der ersten Idee bis zur laufenden Pflege – alles aus einer Hand.', 'Was wir tun'),
                ['type' => 'features', 'data' => ['variant' => 'list', 'eyebrow' => '', 'title' => 'Unsere Schwerpunkte', 'intro' => '', 'size' => 'l', 'items' => [
                    ['icon' => 'compass', 'title' => 'Konzept & Inhalte', 'text' => 'Wir ordnen Inhalte, schreiben verständliche Texte und planen die Seitenstruktur.', 'link_label' => '', 'link' => ''],
                    ['icon' => 'palette', 'title' => 'Gestaltung', 'text' => 'Farben, Schriften und Formen als Design-Tokens – einmal festgelegt, überall stimmig.', 'link_label' => '', 'link' => ''],
                    ['icon' => 'code', 'title' => 'Umsetzung', 'text' => 'Schnell, barrierearm und ohne externe Dienste.', 'link_label' => '', 'link' => ''],
                    ['icon' => 'hand-heart', 'title' => 'Pflege & Schulung', 'text' => 'Ihr Team pflegt selbst – wir stehen bei Fragen bereit.', 'link_label' => '', 'link' => ''],
                ]]],
                ['type' => 'pricing', 'tunes' => ['background' => 'muted'], 'data' => ['variant' => 'cards', 'eyebrow' => 'Pakete', 'title' => 'Beispielpakete', 'intro' => 'Preise frei erfunden – nur zur Ansicht.', 'note' => 'Alle Preise sind Beispielwerte (Demo).', 'rows' => [], 'items' => [
                    ['name' => 'Start', 'badge' => '', 'price' => '1.900 €', 'period' => 'einmalig', 'text' => 'Für den ersten Auftritt.', 'features' => "Bis 5 Seiten\nVorlage nach Wahl\nEinweisung (1 Stunde)", 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
                    ['name' => 'Wachstum', 'badge' => 'Beliebt', 'price' => '4.800 €', 'period' => 'einmalig', 'text' => 'Für Betriebe mit mehr Inhalten.', 'features' => "Bis 20 Seiten\nEigene Farben und Schriften\nTermine und Formulare\nSchulung (halber Tag)", 'highlight' => true, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
                    ['name' => 'Begleitung', 'badge' => '', 'price' => '190 €', 'period' => 'pro Monat', 'text' => 'Pflege und Beratung.', 'features' => "Updates und Sicherung\nKleine Änderungen\nFeste Ansprechperson", 'highlight' => false, 'button_label' => 'Anfragen', 'button_link' => '/kontakt'],
                ]]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Über uns
        [
            'slug' => 'ueber-uns', 'title' => 'Über uns', 'menu' => true,
            'meta_description' => 'Wer hinter dem fiktiven Studio Beispiel steht (Demo-Inhalt).',
            'blocks' => [
                $hero('Klein, erfahren, erreichbar.', 'Ein Team aus Gestaltung, Technik und Redaktion – mit kurzen Wegen.', 'Über uns'),
                ['type' => 'media_text', 'data' => ['variant' => 'auto', 'eyebrow' => 'Haltung', 'title' => 'Weniger, aber besser.',
                    'text' => '<p>Wir bauen Websites, die lange halten: klare Struktur, gut lesbare Texte, keine unnötigen Einbindungen. [Eigene Geschichte hier ergänzen.]</p>',
                    'list' => "Verständlich statt verspielt\nDatensparsam statt datenhungrig\nPflegbar statt kompliziert", 'button_label' => 'Kontakt', 'button_link' => '/kontakt', 'button2_label' => '', 'button2_link' => '', 'image' => null, 'ratio' => '4:3']],
                ['type' => 'quote', 'tunes' => ['background' => 'tint'], 'data' => ['variant' => 'single', 'eyebrow' => '', 'title' => '', 'items' => [
                    ['text' => 'Beispielzitat: Gute Gestaltung ist die, die man nicht bemerkt – weil einfach alles funktioniert.', 'name' => 'Studio Beispiel', 'role' => 'Leitgedanke (Demo)', 'image' => null],
                ]]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Kontakt
        [
            'slug' => 'kontakt', 'title' => 'Kontakt', 'menu' => true,
            'meta_description' => 'So erreichen Sie das fiktive Studio Beispiel (Demo-Inhalt).',
            'blocks' => [
                $hero('Sprechen Sie uns an.', 'Wir melden uns an Werktagen innerhalb von 48 Stunden.', 'Kontakt'),
                ['type' => 'contact', 'tunes' => ['anchor' => 'kontakt'], 'data' => [
                    'eyebrow' => '', 'title' => 'So erreichen Sie uns', 'intro' => '', 'show_hours' => true, 'show_map' => true,
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
                    . '<h3>Technische Hinweise zu dieser Website</h3><p>Die Website setzt für Besucher keine Cookies, lädt Schriften vom eigenen Server und bindet keine Analyse- oder Tracking-Dienste ein. Karten werden über den eigenen Server geladen; Videos von YouTube oder Vimeo erst nach einem Klick. Die Einstellung „Videos künftig direkt laden“ wird nur im eigenen Browser gespeichert (lokaler Speicher, kein Cookie). [Bitte prüfen und an Ihre Nutzung anpassen.]</p>'
                    . '<h3>Ihre Rechte</h3><p>[Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit, Widerspruch, Beschwerde bei einer Aufsichtsbehörde]</p>']],
            ],
        ],
    ],
];
