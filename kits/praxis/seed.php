<?php
/*
 * Startinhalte – werden beim ersten Aufruf eingespielt.
 * Alle Angaben sind fiktiv („Praxis am Lindenplatz“, Musterstadt).
 * Hero-Foto (demo/beratung.jpg): erwinbosman, Pixabay – https://pixabay.com/de/photos/arzt-patient-beratung-alter-klinik-10350071/ (Pixabay-Inhaltslizenz).
 * Inhalte in [eckigen Klammern] sind Platzhalter und müssen von der Praxis geliefert werden.
 * (Platzhalter erscheinen NICHT im JSON-LD.)
 */

$p = fn(string ...$paras) => array_map(fn($t) => ['text' => '<p>' . $t . '</p>'], $paras);
// Sprechzeiten: Mo–Fr 7:30–11:00 · Mo, Di, Do zusätzlich 15:30–17:00 · Mi + Fr nachmittags geschlossen
$fullDay = fn(string $tag) => ['tag' => $tag, 'von' => '07:30', 'bis' => '17:00', 'pause_von' => '11:00', 'pause_bis' => '15:30', 'notiz' => ''];
$morning = fn(string $tag) => ['tag' => $tag, 'von' => '07:30', 'bis' => '11:00', 'pause_von' => '', 'pause_bis' => '', 'notiz' => 'nachmittags geschlossen'];

return [
    'settings' => [
        'praxis_name' => '[Praxis am Lindenplatz (fiktiv)]',
        'wortmarke_1' => 'Praxis am Lindenplatz',
        'wortmarke_2' => 'Hausärzte · Musterstadt',
        'strasse' => '',
        'plz' => '',
        'ort' => 'Musterstadt',
        'telefon' => '',
        'telefon_anzeige' => '[Telefonnummer]',
        'fax' => '',
        'email' => '',
        'oeffnungszeiten' => [$fullDay('1'), $fullDay('2'), $morning('3'), $fullDay('4'), $morning('5')],
        'telefonische_erreichbarkeit' => '',
        'sprechzeiten_hinweis' => '[Hinweis zu Akut- bzw. Terminsprechstunde]',
        'doctolib_aktiv' => true,
        'doctolib_url' => '',
        'termin_hinweis' => 'Wählen Sie Ärztin oder Arzt, Terminart und einen freien Termin – rund um die Uhr über Doctolib.',
        'termin_button' => 'Online-Termin',
        'rezept_aktiv' => true,
        'rezept_modus' => 'internal',
        'rezept_url' => '',
        'ueberweisung_aktiv' => true,
        'ueberweisung_modus' => 'internal',
        'ueberweisung_url' => '',
        'bearbeitungsfrist_text' => '[Frist – noch zu bestätigen]',
        'formular_hinweis' => 'Übermittlung verschlüsselt – Ihre Angaben sind nur in der Praxis lesbar.',
        'formfelder_rezept' => [
            ['label' => 'Vorname', 'name' => 'vorname', 'typ' => 'text', 'breite' => 'half', 'pflicht' => true, 'optionen' => ''],
            ['label' => 'Nachname', 'name' => 'nachname', 'typ' => 'text', 'breite' => 'half', 'pflicht' => true, 'optionen' => ''],
            ['label' => 'Geburtsdatum', 'name' => 'geburtsdatum', 'typ' => 'date', 'breite' => 'half', 'pflicht' => true, 'optionen' => ''],
            ['label' => 'Telefon', 'name' => 'telefon', 'typ' => 'tel', 'breite' => 'half', 'pflicht' => true, 'optionen' => ''],
            ['label' => 'Medikament und Stärke', 'name' => 'medikament', 'typ' => 'text', 'breite' => 'half', 'pflicht' => true, 'optionen' => ''],
            ['label' => 'Ausgabe', 'name' => 'ausgabe', 'typ' => 'select', 'breite' => 'half', 'pflicht' => true, 'optionen' => 'E-Rezept, Abholung'],
        ],
        'formfelder_ueberweisung' => [
            ['label' => 'Vorname', 'name' => 'vorname', 'typ' => 'text', 'breite' => 'half', 'pflicht' => true, 'optionen' => ''],
            ['label' => 'Nachname', 'name' => 'nachname', 'typ' => 'text', 'breite' => 'half', 'pflicht' => true, 'optionen' => ''],
            ['label' => 'Geburtsdatum', 'name' => 'geburtsdatum', 'typ' => 'date', 'breite' => 'half', 'pflicht' => true, 'optionen' => ''],
            ['label' => 'Telefon', 'name' => 'telefon', 'typ' => 'tel', 'breite' => 'half', 'pflicht' => true, 'optionen' => ''],
            ['label' => 'Fachrichtung', 'name' => 'fachrichtung', 'typ' => 'text', 'breite' => 'full', 'pflicht' => true, 'optionen' => ''],
        ],
        'aktueller_hinweis_aktiv' => false,
        'aktueller_hinweis_text' => '[Aktueller Hinweis, z. B. Urlaubszeiten oder Vertretung]',
        'notfall_titel' => 'Im Notfall: 112',
        'notfall_text' => 'Außerhalb der Sprechzeiten: ärztlicher Bereitschaftsdienst 116 117.',
        'notfall_kurz' => 'Notfall 112 · Bereitschaftsdienst 116 117',
        'hero_slides' => [
            ['typ' => 'greeting', 'aktiv' => true, 'eyebrow' => 'Willkommen', 'titel' => '',
                'text' => 'Schön, dass Sie vorbeischauen. Sprechzeiten, Online-Service und alles rund um Ihren Besuch finden Sie hier auf einen Blick.',
                'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => '', 'von_datum' => '', 'bis_datum' => ''],
            ['typ' => 'topic', 'aktiv' => true, 'eyebrow' => 'Aktuelles Thema', 'titel' => 'Grippeschutz für die kalte Jahreszeit',
                'text' => 'Oktober und November sind ideal für die Grippeimpfung. Wir prüfen gern, ob sie für Sie empfohlen ist. [Aktuelles Thema – im Backend pflegbar]',
                'button_label' => 'Impftermin anfragen', 'button_link' => '#kontakt', 'button2_label' => '', 'button2_link' => '', 'von_datum' => '', 'bis_datum' => ''],
            ['typ' => 'main', 'aktiv' => true, 'eyebrow' => 'Hausarztpraxis am Lindenplatz (Beispiel)', 'titel' => 'Gut begleitet durch jede Lebensphase',
                'text' => 'Wir nehmen uns Zeit für Ihre Fragen – von der Vorsorge bis zur langfristigen Betreuung.',
                'button_label' => 'Unsere Praxis', 'button_link' => '#praxis', 'button2_label' => 'Sprechzeiten & Kontakt', 'button2_link' => '#kontakt',
                'von_datum' => '', 'bis_datum' => ''],
        ],
        'oepnv_text' => '',
        'parken_text' => '',
        'barrierefreiheit_text' => '',
        'routenplaner_url' => '',
        'karte_aktiv' => true,
        'karte_geo' => '',
        'site_title' => 'Hausarztpraxis am Lindenplatz in Musterstadt (Beispiel)',
        'site_title_suffix' => '',
        'default_meta_description' => 'Hausärztliche Versorgung am Lindenplatz in Musterstadt: Vorsorge, Impfungen, Diagnostik und die Begleitung chronischer Erkrankungen – mit Zeit für Ihre Fragen.',
    ],

    // Hero-Foto der Startseite in die Mediathek übernehmen und im Hero eintragen (Core\Seeder ruft 'after' nur bei „mit Startinhalten“ auf)
    'after' => function () {
        require_once __DIR__ . '/tools/demo-photo.php';
        praxis_demo_photo();
    },

    'page_refs' => [
        'impressum_seite' => 'impressum',
        'datenschutz_seite' => 'datenschutz',
        'barrierefreiheit_seite' => 'barrierefreiheit',
    ],

    'pages' => [
        // ------------------------------------------------------------------ One-Pager
        [
            'slug' => 'home', 'title' => 'Startseite', 'is_home' => true,
            'meta_description' => 'Hausärztliche Versorgung am Lindenplatz in Musterstadt: Vorsorge, Impfungen, Diagnostik und die Begleitung chronischer Erkrankungen – mit Zeit für Ihre Fragen.',
            'blocks' => [
                ['type' => 'hero', 'data' => ['show_card' => true, 'variant' => 'image', 'bg_overlay' => 'medium'], 'tunes' => ['anchor' => 'top']],
                ['type' => 'teaser_tiles', 'data' => [
                    'variant' => 'plain',
                    'intro' => 'Am Lindenplatz kümmern wir uns als hausärztliches Team um Menschen jeden Alters. Ob akute Beschwerden, Vorsorge oder eine chronische Erkrankung: Bei uns finden Sie feste Ansprechpartner, kurze Wege und verständliche Erklärungen.',
                    'items' => [
                        ['title' => 'Praxis', 'sub' => 'Wer wir sind', 'link' => '#praxis'],
                        ['title' => 'Leistungen', 'sub' => 'Vorsorge bis Labor', 'link' => '#leistungen'],
                        ['title' => 'Hinweise', 'sub' => 'Termin, Rezept, Urlaub', 'link' => '#hinweise'],
                        ['title' => 'Kontakt', 'sub' => 'Zeiten & Anfahrt', 'link' => '#kontakt'],
                    ],
                ], 'tunes' => ['anchor' => 'ueberblick']],
                ['type' => 'text_columns', 'data' => [
                    'variant' => 'stacked', 'title_strong' => 'Unsere Praxis', 'title_light' => 'Zuhören ist der erste Befund.',
                    'columns' => $p(
                        'Wer zu uns kommt, bringt mehr mit als ein Symptom. Deshalb fragen wir nach – nach Ihrem Alltag, Ihrer Vorgeschichte und dem, was Ihnen wichtig ist. Daraus entsteht eine Behandlung, die zu Ihnen passt.',
                        'Befunde erklären wir in Ruhe und ohne Fachchinesisch. Welche Schritte als Nächstes sinnvoll sind, entscheiden wir gemeinsam mit Ihnen.'
                    ),
                ], 'tunes' => ['anchor' => 'praxis', 'showInNav' => true, 'navLabel' => 'Praxis', 'divider' => true, 'spaceBottom' => 'none']],
                ['type' => 'quote', 'data' => [
                    'variant' => 'inset', 'text' => 'Gute Medizin braucht Zeit.', 'highlight' => 'Wir nehmen sie uns.',
                ], 'tunes' => ['spaceTop' => 'none', 'spaceBottom' => 'none']],
                ['type' => 'text_columns', 'data' => [
                    'variant' => 'compact', 'title_strong' => 'Kurze Wege im Team', 'title_light' => '',
                    'columns' => $p('Ärztinnen, Ärzte und medizinische Fachangestellte arbeiten bei uns eng zusammen. Befunde, Termine und Rückfragen laufen an einer Stelle zusammen – so geht nichts verloren, und Sie wissen immer, an wen Sie sich wenden können.'),
                ], 'tunes' => ['spaceTop' => 'none']],
                ['type' => 'doctors', 'data' => [
                    'title_strong' => 'Ärztinnen und Ärzte', 'title_light' => 'Ihre Ansprechpartner am Lindenplatz.',
                    'intro' => 'Unser ärztliches Team bringt unterschiedliche Schwerpunkte mit – und begleitet Sie trotzdem aus einer Hand, oft über viele Jahre.',
                    'link_label' => 'Termin vereinbaren', 'link' => '#kontakt',
                    'items' => array_fill(0, 3, [
                        'fach' => '[Fachbezeichnung]', 'titel' => '[Akad. Titel]', 'name' => '[Name Ärztin/Arzt]',
                        'zusatz' => '[Zusatzqualifikationen]', 'text' => '[Persönlicher Vorstellungstext – zwei bis drei Sätze, max. ca. 400 Zeichen]',
                        'sprechzeiten' => '[optional]', 'foto' => null,
                    ]),
                ], 'tunes' => ['anchor' => 'aerzte', 'showInNav' => true, 'navLabel' => 'Ärztinnen und Ärzte', 'background' => 'gray']],
                ['type' => 'services', 'data' => [
                    'title_strong' => 'Leistungen', 'title_light' => 'Was wir für Sie tun.',
                    'intro' => 'Als Hausarztpraxis sind wir Ihre erste Anlaufstelle. Wir behandeln selbst, was wir behandeln können, und vermitteln gezielt weiter, wenn eine Fachärztin oder ein Facharzt gefragt ist.',
                    'items' => [
                        ['title' => 'Akute Beschwerden', 'text' => 'Infekt, Rückenschmerzen oder ein unklares Symptom: Wir untersuchen gründlich und besprechen mit Ihnen, was hilft. Bei Bedarf kümmern wir uns um Überweisungen und behalten die Befunde im Blick. Hausbesuche sind nach Absprache möglich, wenn der Weg in die Praxis nicht geht.'],
                        ['title' => 'Vorsorge und Impfungen', 'text' => 'Check-up ab 35, Krebsfrüherkennung, Hautkrebs-Screening und Impfungen nach den aktuellen Empfehlungen: Wir erinnern Sie gern an fällige Termine und sehen Ihren Impfpass durch.'],
                        ['title' => 'Diagnostik und Labor', 'text' => 'EKG in Ruhe und unter Belastung, Langzeit-Blutdruck, Lungenfunktion, Ultraschall und Laboruntersuchungen – vieles klären wir direkt bei uns, ohne zusätzliche Wege.'],
                        ['title' => 'Begleitung bei chronischen Erkrankungen', 'text' => 'Bei Diabetes, Bluthochdruck, Herz- oder Lungenerkrankungen begleiten wir Sie dauerhaft, auch in strukturierten Behandlungsprogrammen (DMP). Regelmäßige Kontrollen zeigen früh, ob die Behandlung angepasst werden sollte.'],
                    ],
                ], 'tunes' => ['anchor' => 'leistungen', 'showInNav' => true, 'navLabel' => 'Leistungen']],
                ['type' => 'team_photo', 'data' => [
                    'title_strong' => 'Team', 'title_light' => 'Freundlich, erfahren, gut erreichbar.',
                    'text' => '<p>Am Empfang, im Labor und in der Behandlung: Unser Praxisteam sorgt dafür, dass Ihr Besuch reibungslos läuft. Die medizinischen Fachangestellten sind oft Ihre ersten Ansprechpartner – am Telefon, bei Blutabnahmen und bei Fragen zu Rezepten.</p><p>Wir legen Wert auf einen ruhigen, respektvollen Umgang – miteinander und mit Ihnen. Viele von uns arbeiten seit Jahren zusammen, das merken Sie.</p>',
                    'show_jobs_box' => true, 'jobs_title_strong' => 'Ausbildung und Stellen', 'jobs_title_light' => 'Verstärken Sie unser Team.',
                    'jobs_text' => '[Kurzer Text zu Ausbildung, Qualifikationen oder offenen Stellen, z. B. „Medizinische Fachangestellte (m/w/d)“]',
                    'jobs_button_label' => 'Offene Stellen', 'jobs_link' => '#kontakt',
                ], 'tunes' => ['anchor' => 'team', 'showInNav' => true, 'navLabel' => 'Team', 'spaceTop' => 'none']],
                ['type' => 'accordion', 'data' => [
                    'title_strong' => 'Hinweise', 'title_light' => 'Gut vorbereitet zum Termin.',
                    'intro' => '<p>Mit einem Termin planen wir genug Zeit für Sie ein. Bei akuten Beschwerden rufen Sie bitte zuerst an – dann sagen wir Ihnen, wann Sie am besten kommen, und Sie warten kürzer.</p>',
                    'show_emergency' => true,
                    'items' => [
                        ['q' => 'Termine', 'a' => '<p>Termine vereinbaren Sie telefonisch unter [Telefonnummer] oder online [Terminlink, falls vorhanden].</p>'],
                        ['q' => 'Akutsprechstunde', 'a' => '<p>[Zeiten und Ablauf der Akutsprechstunde – noch zu bestätigen]. Bitte melden Sie sich vorher telefonisch.</p>'],
                        ['q' => 'Folgerezepte', 'a' => '<p>[Ablauf für Folgerezepte – noch zu bestätigen].</p>', 'service' => 'rezept'],
                        ['q' => 'Überweisungen', 'a' => '<p>[Ablauf für Überweisungen – noch zu bestätigen].</p>', 'service' => 'ueberweisung'],
                        ['q' => 'Was Sie mitbringen sollten', 'a' => '<p>Ihre Gesundheitskarte und [weitere Unterlagen, z. B. Medikationsplan, Arztbriefe – noch zu bestätigen].</p>'],
                        ['q' => 'Urlaub und Vertretung', 'a' => '<p>[Aktuelle Urlaubszeiten und Vertretungspraxis mit Adresse und Telefonnummer].</p>'],
                        ['q' => 'Nachts und am Wochenende', 'a' => '<p>Ärztlicher Bereitschaftsdienst: 116 117. In lebensbedrohlichen Notfällen: 112. [Ggf. nächstgelegene Notdienstpraxis ergänzen].</p>'],
                    ],
                    'footer' => 'Formulare: <a href="/bausteine#formulare"><b>[Download, z. B. Anamnesebogen (PDF)]</b></a>',
                ], 'tunes' => ['anchor' => 'hinweise', 'background' => 'gray']],
                ['type' => 'contact', 'data' => [
                    'title_strong' => 'Kontakt', 'title_light' => 'So erreichen Sie uns.', 'show_map' => true,
                ], 'tunes' => ['anchor' => 'kontakt', 'showInNav' => true, 'navLabel' => 'Kontakt']],
            ],
        ],

        // ------------------------------------------------------------------ Block-Bibliothek
        [
            'slug' => 'bausteine', 'title' => 'Bausteine', 'noindex' => true, 'status' => 'published',
            'meta_description' => 'Übersicht der verfügbaren Inhaltsblöcke.',
            'blocks' => [
                ['type' => 'text_image', 'data' => [
                    'variant' => 'right', 'ratio' => '4-3', 'title_style' => 'split',
                    'title_strong' => 'Vorsorge', 'title_light' => 'Früh erkennen, gezielt handeln.',
                    'text' => '<p>Check-up ab 35, Krebsfrüherkennung und Impfungen nach den aktuellen Empfehlungen – wir erinnern Sie gern an fällige Termine.</p>',
                    'button_label' => 'Termin vereinbaren', 'button_link' => '/#kontakt',
                ], 'tunes' => ['anchor' => 'vorsorge', 'divider' => false]],
                ['type' => 'text_image', 'data' => [
                    'variant' => 'left', 'ratio' => '1-1', 'title_style' => 'sentence', 'eyebrow' => 'Diagnostik',
                    'title_strong' => 'Fundiert beurteilen – mit moderner Ausstattung.',
                    'text' => '<p>Ruhe- und Belastungs-EKG, Langzeit-Blutdruckmessung, Lungenfunktion, Ultraschall und Labor. Welche Untersuchung sinnvoll ist, entscheiden wir gemeinsam mit Ihnen.</p>',
                    'list' => "Ruhe- und Belastungs-EKG\nUltraschalluntersuchungen\nLaboruntersuchungen",
                ], 'tunes' => ['background' => 'gray']],
                ['type' => 'text_video', 'data' => [
                    'variant' => 'left', 'title_strong' => 'Einblick', 'title_light' => 'Unsere Praxis in zwei Minuten.',
                    'text' => '<p>[Kurzer Begleittext zum Video, max. ca. 300 Zeichen.]</p>', 'video_typ' => 'youtube', 'video_url' => '',
                    'consent_text' => 'Beim Abspielen werden Daten an [Videoanbieter] übertragen. Selbst gehostete Videos laden ohne Zustimmung.',
                ], 'tunes' => ['divider' => true]],
                ['type' => 'quote', 'data' => [
                    'variant' => 'full', 'text' => 'Gute Medizin braucht Zeit.', 'highlight' => 'Wir nehmen sie uns.',
                    'source' => '[optionale Quelle / Name]',
                ], 'tunes' => ['background' => 'bordeaux', 'spaceTop' => 'small', 'spaceBottom' => 'small']],
                ['type' => 'text_columns', 'data' => [
                    'variant' => 'columns', 'title_strong' => 'Unsere Praxis', 'title_light' => 'Zuhören ist der erste Befund.',
                    'columns' => $p(
                        'Wer zu uns kommt, bringt mehr mit als ein Symptom. Deshalb fragen wir nach – nach Ihrem Alltag, Ihrer Vorgeschichte und dem, was Ihnen wichtig ist.',
                        'Befunde erklären wir in Ruhe und ohne Fachchinesisch. Welche Schritte als Nächstes sinnvoll sind, entscheiden wir gemeinsam mit Ihnen.'
                    ),
                ], 'tunes' => []],
                ['type' => 'teaser_tiles', 'data' => [
                    'variant' => 'image', 'intro' => '',
                    'items' => [
                        ['title' => 'Impfberatung', 'sub' => 'Vorsorge', 'link' => '/#leistungen'],
                        ['title' => 'Check-up 35', 'sub' => 'Gesundheit', 'link' => '/#leistungen'],
                        ['title' => 'Hausbesuche', 'sub' => 'Versorgung', 'link' => '/#leistungen'],
                    ],
                ], 'tunes' => ['divider' => true]],
                ['type' => 'image_wide', 'data' => [
                    'variant' => '21-9', 'caption' => 'Unser Praxisteam.', 'credit' => '[Bildunterschrift / Fotonachweis]',
                ], 'tunes' => ['divider' => true]],
                ['type' => 'steps', 'data' => [
                    'title_strong' => 'Ihr erster Besuch', 'title_light' => 'So läuft es ab.',
                    'items' => [
                        ['title' => 'Termin buchen', 'text' => 'Online über Doctolib oder telefonisch.'],
                        ['title' => 'Unterlagen mitbringen', 'text' => 'Versichertenkarte, Medikationsplan, Vorbefunde.'],
                        ['title' => 'Gespräch & Untersuchung', 'text' => 'Wir nehmen uns Zeit für Ihre Situation.'],
                        ['title' => 'Gemeinsam entscheiden', 'text' => 'Nächste Schritte verständlich erklärt.'],
                    ],
                ], 'tunes' => ['background' => 'gray']],
                ['type' => 'accordion', 'data' => [
                    'title_strong' => 'Häufige Fragen', 'title_light' => '',
                    'items' => [
                        ['q' => 'Nehmen Sie neue Patientinnen und Patienten auf?', 'a' => '<p>[Antwort – noch zu bestätigen]</p>'],
                        ['q' => 'Wie bestelle ich ein Folgerezept?', 'a' => '<p>[Verfahren – noch zu bestätigen]</p>'],
                        ['q' => 'Bieten Sie Hausbesuche an?', 'a' => '<p>Nach medizinischer Notwendigkeit und vorheriger Abstimmung.</p>'],
                    ],
                ], 'tunes' => ['anchor' => 'faq']],
                ['type' => 'notice', 'data' => [
                    'items' => [
                        ['style' => 'info', 'title' => 'Info: Urlaubszeit', 'text' => '[Zeitraum] – Vertretung: [Praxis, Telefon].'],
                        ['style' => 'important', 'title' => 'Wichtig: Im Notfall 112', 'text' => 'Außerhalb der Sprechzeiten: Bereitschaftsdienst 116 117.'],
                    ],
                ], 'tunes' => ['divider' => true]],
                ['type' => 'cta', 'data' => [
                    'title_strong' => 'Jetzt Termin buchen', 'title_light' => 'Rund um die Uhr online.',
                    'buttons' => [
                        ['label' => 'Doctolib öffnen', 'link' => 'doctolib'],
                        ['label' => '[Telefonnummer]', 'link' => 'telefon'],
                    ],
                ], 'tunes' => ['background' => 'dark', 'spaceTop' => 'small', 'spaceBottom' => 'small']],
                ['type' => 'downloads', 'data' => [
                    'title_strong' => 'Formulare', 'title_light' => 'Zum Ausfüllen vorab.',
                    'files' => [
                        ['label' => 'Anamnesebogen', 'file' => null],
                        ['label' => 'Einverständniserklärung', 'file' => null],
                        ['label' => 'Medikationsplan (Vorlage)', 'file' => null],
                    ],
                ], 'tunes' => ['anchor' => 'formulare']],
                ['type' => 'people', 'data' => [
                    'title_strong' => '', 'title_light' => '',
                    'items' => [
                        ['name' => '[Name]', 'rolle' => 'Praxismanagement'],
                        ['name' => '[Name]', 'rolle' => 'Medizinische Fachangestellte'],
                        ['name' => '[Name]', 'rolle' => 'Medizinische Fachangestellte'],
                        ['name' => '[Name]', 'rolle' => 'Auszubildende'],
                    ],
                ], 'tunes' => ['background' => 'gray']],
                ['type' => 'job', 'data' => [
                    'tags' => 'Ausbildung, Start [Datum]', 'title' => 'Medizinische Fachangestellte (m/w/d)',
                    'text' => '[Kurzbeschreibung der Stelle, Anforderungen, Benefits – max. ca. 400 Zeichen.]',
                    'button_label' => 'Jetzt bewerben', 'link' => '/#kontakt',
                ], 'tunes' => ['anchor' => 'stellen']],
            ],
        ],

        // ------------------------------------------------------------------ Rechtliches
        [
            'slug' => 'impressum', 'title' => 'Impressum', 'noindex' => false,
            'blocks' => [['type' => 'richtext', 'data' => [
                'title_strong' => 'Impressum', 'title_light' => 'Angaben gemäß § 5 DDG.',
                'text' => '<p>[Impressumstext der Praxis einfügen: Name der Praxis, verantwortliche Ärztinnen und Ärzte, Anschrift, Kontakt, zuständige Ärztekammer, Kassenärztliche Vereinigung, Berufsbezeichnung und Staat der Verleihung, berufsrechtliche Regelungen, Aufsichtsbehörde.]</p>',
            ]]],
        ],
        [
            'slug' => 'datenschutz', 'title' => 'Datenschutz',
            'blocks' => [['type' => 'richtext', 'data' => [
                'title_strong' => 'Datenschutz', 'title_light' => 'Informationen zur Verarbeitung Ihrer Daten.',
                'text' => '<p>[Datenschutzerklärung der Praxis einfügen.]</p><h3>Hinweise zur technischen Umsetzung dieser Website</h3><ul><li>Diese Website setzt für Besucherinnen und Besucher keine Cookies und verwendet keine Tracking- oder Analysedienste.</li><li>Schriftarten werden lokal ausgeliefert; es werden keine externen Dienste beim Seitenaufruf geladen.</li><li>Karten und Videos externer Anbieter werden erst nach Ihrem Klick geladen.</li><li>Anfragen über die Online-Formulare werden verschlüsselt gespeichert und sind nur in der Praxis lesbar.</li><li>Termine werden über einen externen Link zu Doctolib gebucht.</li></ul><p>[Rechtlich geprüfte Formulierungen, Verantwortliche Stelle, Rechtsgrundlagen, Speicherdauer, Betroffenenrechte, Hosting-Anbieter – noch zu ergänzen.]</p>',
            ]]],
        ],
        [
            'slug' => 'barrierefreiheit', 'title' => 'Barrierefreiheit',
            'blocks' => [['type' => 'richtext', 'data' => [
                'title_strong' => 'Barrierefreiheit', 'title_light' => 'Erklärung zur Barrierefreiheit.',
                'text' => '<p>[Erklärung zur Barrierefreiheit einfügen: Stand der Vereinbarkeit mit WCAG 2.2 AA, nicht barrierefreie Inhalte, Feedback-Kontakt, Durchsetzungsverfahren.]</p>',
            ]]],
        ],
    ],
];
