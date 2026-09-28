<?php
/*
 * Startinhalte des Themes „basis“ – werden beim ersten Aufruf einer neuen Website eingespielt.
 * Alle Inhalte sind frei erfunden und als Demo gekennzeichnet („Musterfirma (Demo)“, Beispielwerte).
 * Texte in [eckigen Klammern] sind Platzhalter: Sie erscheinen nicht für Besucher bzw. werden
 * in der Übersicht der Verwaltung als offen gemeldet.
 * Bilder: keine auf den Hauptseiten – alle Blöcke sehen auch ohne Bilder vollständig aus.
 * Zusätzlich (after): Seitenbaum „Musterseiten“ mit allen Blöcken, Demo-Datentabellen und erzeugten Beispielbildern
 * (tools/demo-content.php; entfernen mit php kits/basis/tools/demo.php --remove oder in der Verwaltung).
 * Farben, Schriften und Navigation: Verwaltung → Design (Standard = Voreinstellung „Petrol Business“).
 */

$workday = fn(string $tag) => ['tag' => $tag, 'von' => '09:00', 'bis' => '17:00', 'pause_von' => '', 'pause_bis' => '', 'notiz' => ''];

$hero = fn(string $title, string $text, string $eyebrow = '') => [
    'type' => 'hero', 'tunes' => ['background' => 'muted'],
    'data' => ['variant' => 'compact', 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text],
];

$cta = [
    'type' => 'cta', 'tunes' => ['background' => 'accent'],
    'data' => [
        'variant' => 'band',
        'title' => 'Lassen Sie uns über Ihr Vorhaben sprechen.',
        'text' => 'Ein kurzes Gespräch genügt, um herauszufinden, ob und wie wir Sie unterstützen können – unverbindlich.',
        'button_label' => 'Kontakt aufnehmen', 'button_link' => '/kontakt',
        'button2_label' => 'E-Mail schreiben', 'button2_link' => 'email',
    ],
];

return [
    'settings' => [
        'org_name' => 'Musterfirma (Demo)',
        'short_name' => 'Musterfirma',
        'tagline' => 'Beratung, Planung und Umsetzung aus einer Hand.',
        'street' => '',
        'zip' => '',
        'city' => '',
        'country' => '',
        'phone' => '',
        'email' => 'kontakt@example.com',
        'hours' => [$workday('1'), $workday('2'), $workday('3'), $workday('4'),
            ['tag' => '5', 'von' => '09:00', 'bis' => '14:00', 'pause_von' => '', 'pause_bis' => '', 'notiz' => '']],
        'hours_note' => 'Termine außerhalb dieser Zeiten nach Vereinbarung.',
        'geo' => '',
        'header_cta_label' => 'Projekt anfragen',
        'header_cta_link' => '/kontakt',
        'footer_text' => 'Demo-Inhalte des Kits „Basis“: Namen, Zahlen und Zitate sind frei erfunden und dienen nur als Beispiel.',
        'notice_active' => false,
        'notice_text' => '',
        'social' => [],
        'site_title' => 'Musterfirma (Demo) – Beratung, Planung und Umsetzung',
        'site_title_suffix' => '',
        'default_meta_description' => 'Demo-Website der fiktiven Musterfirma: ein neutrales Beispiel für Unternehmen, Agenturen, Vereine und Betriebe.',
        'schema_type' => 'Organization',
    ],

    // Musterseiten nach den Seiten anlegen (Core\Seeder ruft 'after' auf)
    'after' => function () {
        require_once __DIR__ . '/tools/demo-content.php';
        basis_demo_install();
    },

    'page_refs' => [
        'imprint_page' => 'impressum',
        'privacy_page' => 'datenschutz',
    ],

    'pages' => [
        // ------------------------------------------------------------------ Startseite
        [
            'slug' => 'start', 'title' => 'Start', 'is_home' => true, 'menu' => false,
            'meta_description' => 'Demo-Website der fiktiven Musterfirma – Beratung, Planung und Umsetzung aus einer Hand.',
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'variant' => 'split', 'eyebrow' => 'Musterfirma (Demo)',
                    'title' => 'Klare Lösungen für Vorhaben, die gelingen sollen.',
                    'text' => 'Wir begleiten Unternehmen, Vereine und öffentliche Einrichtungen von der ersten Idee bis zum fertigen Ergebnis – strukturiert, verlässlich und verständlich.',
                    'button_label' => 'Leistungen ansehen', 'button_link' => '/leistungen',
                    'button2_label' => 'Kontakt aufnehmen', 'button2_link' => '/kontakt',
                    'ratio' => '4:3',
                ]],
                ['type' => 'features', 'tunes' => ['anchor' => 'leistungen'], 'data' => [
                    'eyebrow' => 'Leistungen', 'title' => 'Was wir für Sie tun',
                    'intro' => 'Drei Schwerpunkte, ein Ansprechpartner: Sie erhalten alles aus einer Hand – oder genau den Baustein, den Sie brauchen.',
                    'columns' => '3', 'style' => 'grid',
                    'items' => [
                        ['icon' => 'chat', 'title' => 'Beratung', 'text' => 'Wir hören zu, ordnen Anforderungen und zeigen Wege auf – mit ehrlicher Einschätzung von Aufwand und Nutzen.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'layers', 'title' => 'Planung', 'text' => 'Aus Zielen wird ein Plan mit klaren Schritten, Zuständigkeiten und Terminen, den alle Beteiligten verstehen.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'check', 'title' => 'Umsetzung', 'text' => 'Wir setzen um, stimmen uns regelmäßig ab und übergeben ein Ergebnis, das Sie selbst weiterführen können.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'shield', 'title' => 'Verlässlich', 'text' => 'Feste Ansprechpartner, nachvollziehbare Angebote und Absprachen, auf die Sie sich verlassen können.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'clock', 'title' => 'Termintreu', 'text' => 'Realistische Zeitpläne und frühzeitige Rückmeldung, falls sich etwas ändert.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'users', 'title' => 'Persönlich', 'text' => 'Kurze Wege und direkte Kommunikation – ohne Warteschleifen und wechselnde Zuständigkeiten.', 'link_label' => '', 'link' => ''],
                    ],
                ]],
                ['type' => 'stats', 'tunes' => ['background' => 'muted'], 'data' => [
                    'eyebrow' => 'In Zahlen', 'title' => 'Erfahrung, die man messen kann',
                    'intro' => 'Beispielwerte der Demo – bitte durch eigene, belegbare Zahlen ersetzen.',
                    'items' => [
                        ['value' => '15+', 'label' => 'Jahre Erfahrung', 'text' => 'in Beratung und Projektarbeit'],
                        ['value' => '240', 'label' => 'abgeschlossene Projekte', 'text' => 'für kleine und mittlere Organisationen'],
                        ['value' => '12', 'label' => 'Fachleute im Team', 'text' => 'mit unterschiedlichen Schwerpunkten'],
                        ['value' => '48 h', 'label' => 'bis zur Rückmeldung', 'text' => 'auf jede Anfrage an Werktagen'],
                    ],
                ]],
                ['type' => 'text_image', 'data' => [
                    'variant' => 'right', 'eyebrow' => 'Arbeitsweise',
                    'title' => 'Strukturiert vom ersten Gespräch bis zum Ergebnis.',
                    'text' => '<p>Jedes Vorhaben beginnt mit einem Gespräch über Ziele, Rahmenbedingungen und Erwartungen. Daraus entsteht ein Vorschlag, der Aufwand und nächste Schritte offenlegt.</p><p>Während der Umsetzung bleiben Sie auf dem Laufenden – mit kurzen Abstimmungen statt langer Berichte.</p>',
                    'list' => "Unverbindliches Erstgespräch\nNachvollziehbares Angebot\nFeste Ansprechperson\nSaubere Übergabe und Dokumentation",
                    'button_label' => 'Mehr über uns', 'button_link' => '/ueber-uns',
                    'ratio' => '4:3',
                ]],
                ['type' => 'quote', 'tunes' => ['divider' => true], 'data' => [
                    'items' => [[
                        'text' => 'Beispielzitat: Die Zusammenarbeit war klar organisiert, jederzeit transparent und hat unser Projekt spürbar vorangebracht.',
                        'name' => 'Alex Beispiel', 'role' => 'Geschäftsführung, Beispiel GmbH (fiktiv)',
                    ]],
                ]],
                ['type' => 'faq', 'tunes' => ['background' => 'muted', 'anchor' => 'faq'], 'data' => [
                    'eyebrow' => 'FAQ', 'title' => 'Häufige Fragen',
                    'intro' => 'Kurze Antworten auf das, was uns am häufigsten gefragt wird. Ihre Frage fehlt? Schreiben Sie uns.',
                    'items' => [
                        ['q' => 'Für wen arbeiten Sie?', 'a' => '<p>Für Unternehmen, Vereine, Verbände und öffentliche Einrichtungen – vom Einzelbetrieb bis zur Organisation mit mehreren Standorten.</p>'],
                        ['q' => 'Wie läuft ein Erstgespräch ab?', 'a' => '<p>Wir sprechen etwa 30 Minuten über Ihr Vorhaben – telefonisch, per Video oder vor Ort. Danach erhalten Sie eine kurze Einschätzung und, wenn gewünscht, ein Angebot.</p>'],
                        ['q' => 'Was kostet die Zusammenarbeit?', 'a' => '<p>Das hängt vom Umfang ab. Sie erhalten vorab ein schriftliches Angebot mit klar beschriebenen Leistungen – ohne versteckte Kosten.</p>'],
                        ['q' => 'Können wir auch einzelne Leistungen buchen?', 'a' => '<p>Ja. Sie können uns für eine einzelne Phase beauftragen, etwa nur für die Planung, oder das gesamte Vorhaben an uns übergeben.</p>'],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Über uns
        [
            'slug' => 'ueber-uns', 'title' => 'Über uns', 'menu' => true,
            'meta_description' => 'Wer hinter der fiktiven Musterfirma steht: Haltung, Arbeitsweise und Werte (Demo-Inhalt).',
            'blocks' => [
                $hero('Menschen, die Vorhaben voranbringen.', 'Ein Team mit unterschiedlichen Stärken und einem gemeinsamen Anspruch: Ergebnisse, die im Alltag funktionieren.', 'Über uns'),
                ['type' => 'text_image', 'data' => [
                    'variant' => 'left', 'eyebrow' => 'Geschichte', 'title' => 'Klein angefangen, mit Sorgfalt gewachsen.',
                    'text' => '<p>Die Musterfirma ist ein frei erfundenes Beispiel. Hier erzählen Sie in wenigen Absätzen, wie Ihre Organisation entstanden ist, wofür sie steht und was Sie von anderen unterscheidet.</p><p>Tipp: Ein gutes Foto Ihres Teams oder Ihrer Räume macht diesen Abschnitt persönlich. Bild in der Seitenleiste wählen.</p>',
                    'list' => '', 'button_label' => '', 'button_link' => '', 'ratio' => '4:3',
                ]],
                ['type' => 'features', 'tunes' => ['background' => 'muted'], 'data' => [
                    'eyebrow' => 'Werte', 'title' => 'Worauf Sie sich verlassen können',
                    'intro' => '', 'columns' => '3', 'style' => 'plain',
                    'items' => [
                        ['icon' => 'target', 'title' => 'Klarheit', 'text' => 'Wir sagen, was wir tun – und tun, was wir sagen.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'heart', 'title' => 'Sorgfalt', 'text' => 'Gründlich statt hastig: Qualität entsteht im Detail.', 'link_label' => '', 'link' => ''],
                        ['icon' => 'leaf', 'title' => 'Nachhaltigkeit', 'text' => 'Lösungen, die auch in einigen Jahren noch tragen.', 'link_label' => '', 'link' => ''],
                    ],
                ]],
                ['type' => 'logos', 'data' => [
                    'eyebrow' => 'Referenzen', 'title' => 'Beispielhafte Auftraggeber',
                    'items' => [
                        ['name' => 'Beispiel AG', 'link' => '', 'image' => null],
                        ['name' => 'Muster & Partner', 'link' => '', 'image' => null],
                        ['name' => 'Verein Beispielstadt', 'link' => '', 'image' => null],
                        ['name' => 'Studio Nord', 'link' => '', 'image' => null],
                        ['name' => 'Werkstatt Süd', 'link' => '', 'image' => null],
                        ['name' => 'Stiftung Muster', 'link' => '', 'image' => null],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Leistungen
        [
            'slug' => 'leistungen', 'title' => 'Leistungen', 'menu' => true,
            'meta_description' => 'Beratung, Planung und Umsetzung – die Leistungen der fiktiven Musterfirma im Überblick (Demo-Inhalt).',
            'blocks' => [
                $hero('Leistungen, die ineinandergreifen.', 'Einzeln buchbar oder als Gesamtpaket – immer mit einer festen Ansprechperson.', 'Leistungen'),
                ['type' => 'features', 'data' => [
                    'eyebrow' => '', 'title' => 'Unser Angebot', 'intro' => '',
                    'columns' => '2', 'style' => 'cards',
                    'items' => [
                        ['icon' => 'chat', 'title' => 'Beratung & Analyse', 'text' => 'Bestandsaufnahme, Zieldefinition und eine ehrliche Einschätzung, was sinnvoll ist – und was nicht.', 'link_label' => 'Kontakt aufnehmen', 'link' => '/kontakt'],
                        ['icon' => 'layers', 'title' => 'Konzept & Planung', 'text' => 'Ein belastbarer Plan mit Meilensteinen, Budgetrahmen und klaren Zuständigkeiten.', 'link_label' => 'Kontakt aufnehmen', 'link' => '/kontakt'],
                        ['icon' => 'tool', 'title' => 'Umsetzung & Begleitung', 'text' => 'Wir setzen um oder begleiten Ihr Team – mit regelmäßigen, kurzen Abstimmungen.', 'link_label' => 'Kontakt aufnehmen', 'link' => '/kontakt'],
                        ['icon' => 'spark', 'title' => 'Betreuung & Weiterentwicklung', 'text' => 'Auch nach dem Abschluss sind wir da: für Fragen, Anpassungen und die nächsten Schritte.', 'link_label' => 'Kontakt aufnehmen', 'link' => '/kontakt'],
                    ],
                ]],
                ['type' => 'steps', 'tunes' => ['background' => 'muted'], 'data' => [
                    'variant' => 'numbers', 'eyebrow' => 'Ablauf', 'title' => 'In vier Schritten zum Ergebnis.',
                    'intro' => 'Jedes Vorhaben ist anders – der Ablauf bleibt verlässlich. So wissen Sie jederzeit, wo Ihr Projekt steht.',
                    'items' => [
                        ['meta' => '', 'title' => 'Kennenlernen', 'text' => 'Ziele, Rahmen und Ansprechpartner klären – unverbindlich.'],
                        ['meta' => '', 'title' => 'Angebot', 'text' => 'Fester Leistungsumfang, klare Termine, keine versteckten Kosten.'],
                        ['meta' => '', 'title' => 'Umsetzung', 'text' => 'Regelmäßige, kurze Abstimmungen statt langer Berichte.'],
                        ['meta' => '', 'title' => 'Übergabe', 'text' => 'Dokumentation, Einweisung und Nachbetreuung.'],
                    ],
                ]],
                $cta,
            ],
        ],

        // ------------------------------------------------------------------ Kontakt
        [
            'slug' => 'kontakt', 'title' => 'Kontakt', 'menu' => true,
            'meta_description' => 'So erreichen Sie die fiktive Musterfirma (Demo-Inhalt).',
            'blocks' => [
                $hero('Sprechen Sie uns an.', 'Wir melden uns an Werktagen innerhalb von 48 Stunden – versprochen.', 'Kontakt'),
                ['type' => 'contact', 'tunes' => ['anchor' => 'kontakt'], 'data' => [
                    'eyebrow' => '', 'title' => 'So erreichen Sie uns', 'intro' => '',
                    'show_hours' => true, 'show_map' => true,
                    'note' => 'Bitte senden Sie uns keine vertraulichen Unterlagen per unverschlüsselter E-Mail. Sprechen Sie uns an – wir finden einen sicheren Weg.',
                ]],
            ],
        ],

        // ------------------------------------------------------------------ Rechtliches (nicht im Menü, verlinkt im Fußbereich)
        [
            'slug' => 'impressum', 'title' => 'Impressum', 'menu' => false,
            'blocks' => [
                $hero('Impressum', ''),
                ['type' => 'richtext', 'data' => ['title' => '', 'text' =>
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
                ['type' => 'richtext', 'data' => ['title' => '', 'text' =>
                    '<p><strong>Hinweis für die Betreiber dieser Website:</strong> Bitte fügen Sie hier Ihre vollständige Datenschutzerklärung ein. Sie muss zu Ihrer tatsächlichen Nutzung passen (z. B. Hosting, E-Mail, eingebundene Dienste) und sollte rechtlich geprüft werden.</p>'
                    . '<h2>Verantwortliche Stelle</h2><p>[Name bzw. Firma, Anschrift, E-Mail-Adresse]</p>'
                    . '<h2>Technische Hinweise zu dieser Website</h2><p>Die Website setzt für Besucher keine Cookies, lädt Schriften vom eigenen Server und bindet keine Analyse- oder Tracking-Dienste ein. Karten werden über den eigenen Server geladen, sodass beim Anzeigen keine Daten an Kartenanbieter übertragen werden. [Bitte prüfen und an Ihre Nutzung anpassen.]</p>'
                    . '<h2>Ihre Rechte</h2><p>[Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit, Widerspruch, Beschwerde bei einer Aufsichtsbehörde]</p>']],
            ],
        ],
    ],
];
