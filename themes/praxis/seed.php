<?php
/*
 * Startinhalte – werden beim ersten Aufruf eingespielt.
 * Inhalte in [eckigen Klammern] sind Platzhalter und müssen von der Praxis geliefert werden.
 * (Platzhalter erscheinen NICHT im JSON-LD.)
 */

$p = fn(string ...$paras) => array_map(fn($t) => ['text' => '<p>' . $t . '</p>'], $paras);
// Sprechzeiten: Mo–Fr 7:30–11:00 · Mo, Di, Do zusätzlich 15:30–17:00 · Mi + Fr nachmittags geschlossen
$fullDay = fn(string $tag) => ['tag' => $tag, 'von' => '07:30', 'bis' => '17:00', 'pause_von' => '11:00', 'pause_bis' => '15:30', 'notiz' => ''];
$morning = fn(string $tag) => ['tag' => $tag, 'von' => '07:30', 'bis' => '11:00', 'pause_von' => '', 'pause_bis' => '', 'notiz' => 'nachmittags geschlossen'];

return [
    'settings' => [
        'praxis_name' => '[Praxisname]',
        'wortmarke_1' => 'Gemeinschaftspraxis',
        'wortmarke_2' => 'Moers',
        'strasse' => '',
        'plz' => '',
        'ort' => 'Moers',
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
                'text' => 'Schön, dass Sie da sind. Hier finden Sie alles Wichtige zu unserer Praxis – persönlich, verständlich und auf einen Blick.',
                'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => '', 'von_datum' => '', 'bis_datum' => ''],
            ['typ' => 'topic', 'aktiv' => true, 'eyebrow' => 'Aktuelles Thema', 'titel' => 'Jetzt an die Grippe­impfung denken',
                'text' => 'Die beste Zeit für die Impfung ist der Herbst. Wir beraten Sie gern, ob die Impfung für Sie empfohlen ist. [Aktuelles Thema – im Backend pflegbar]',
                'button_label' => 'Impftermin vereinbaren', 'button_link' => '#kontakt', 'button2_label' => '', 'button2_link' => '', 'von_datum' => '', 'bis_datum' => ''],
            ['typ' => 'main', 'aktiv' => true, 'eyebrow' => 'Hausärztliche Gemeinschaftspraxis in Moers', 'titel' => 'Gemeinsam für Ihre Gesundheit',
                'text' => 'Hausärztliche Medizin, die den ganzen Menschen im Blick behält.',
                'button_label' => 'Praxis kennenlernen', 'button_link' => '#praxis', 'button2_label' => 'Kontakt und Sprechzeiten', 'button2_link' => '#kontakt',
                'von_datum' => '', 'bis_datum' => ''],
        ],
        'oepnv_text' => '',
        'parken_text' => '',
        'barrierefreiheit_text' => '',
        'routenplaner_url' => '',
        'karte_aktiv' => true,
        'karte_geo' => '',
        'site_title' => 'Hausärztliche Gemeinschaftspraxis in Moers',
        'site_title_suffix' => '',
        'default_meta_description' => 'Persönliche hausärztliche Betreuung in Moers: Vorsorge, Diagnostik und Begleitung bei chronischen Erkrankungen – ganzheitlich und im Team.',
    ],

    'page_refs' => [
        'impressum_seite' => 'impressum',
        'datenschutz_seite' => 'datenschutz',
        'barrierefreiheit_seite' => 'barrierefreiheit',
    ],

    'pages' => [
        // ------------------------------------------------------------------ One-Pager
        [
            'slug' => 'home', 'title' => 'Startseite', 'is_home' => true,
            'meta_description' => 'Persönliche hausärztliche Betreuung in Moers: Vorsorge, Diagnostik und Begleitung bei chronischen Erkrankungen – ganzheitlich und im Team.',
            'blocks' => [
                ['type' => 'hero', 'data' => ['show_card' => true], 'tunes' => ['anchor' => 'top', 'background' => 'bordeaux']],
                ['type' => 'teaser_tiles', 'data' => [
                    'variant' => 'plain',
                    'intro' => 'In unserer Gemeinschaftspraxis verbinden wir medizinische Erfahrung mit persönlicher Betreuung. Wir nehmen uns Zeit, hören zu und betrachten Gesundheit nicht als einzelne Momentaufnahme. Gemeinsam mit unserem Praxisteam begleiten wir Sie verlässlich – bei akuten Beschwerden, in der Vorsorge und bei chronischen Erkrankungen.',
                    'items' => [
                        ['title' => 'Praxis', 'sub' => 'Medizin beginnt mit Zuhören', 'link' => '#praxis'],
                        ['title' => 'Leistungen', 'sub' => 'Vorsorge, Diagnostik, DMP', 'link' => '#leistungen'],
                        ['title' => 'Hinweise', 'sub' => 'Termin, Rezept, Vertretung', 'link' => '#hinweise'],
                        ['title' => 'Kontakt', 'sub' => 'Sprechzeiten & Anfahrt', 'link' => '#kontakt'],
                    ],
                ], 'tunes' => ['anchor' => 'ueberblick']],
                ['type' => 'text_columns', 'data' => [
                    'variant' => 'stacked', 'title_strong' => 'Praxis', 'title_light' => 'Medizin beginnt mit Zuhören.',
                    'columns' => $p(
                        'Eine gute hausärztliche Versorgung beginnt für uns mit einem offenen Gespräch. Beschwerden lassen sich häufig nicht isoliert betrachten. Deshalb beziehen wir Ihre persönliche Situation, Ihre Vorgeschichte und bereits bestehende Erkrankungen in die medizinische Beurteilung ein.',
                        'Unser Anspruch ist eine verständliche, sorgfältige und verlässliche Medizin. Wir erklären Befunde und Behandlungsmöglichkeiten nachvollziehbar und treffen die nächsten Entscheidungen gemeinsam mit Ihnen.'
                    ),
                ], 'tunes' => ['anchor' => 'praxis', 'showInNav' => true, 'navLabel' => 'Praxis', 'divider' => true, 'spaceBottom' => 'none']],
                ['type' => 'quote', 'data' => [
                    'variant' => 'inset', 'text' => 'Wir behandeln nicht nur Beschwerden.', 'highlight' => 'Wir begleiten Menschen.',
                ], 'tunes' => ['spaceTop' => 'none', 'spaceBottom' => 'none']],
                ['type' => 'text_columns', 'data' => [
                    'variant' => 'compact', 'title_strong' => 'Ganzheitlich heißt: im Team', 'title_light' => '',
                    'columns' => $p('Ganzheitliche Betreuung ist für uns zugleich Teamarbeit. Ärztinnen, Ärzte und medizinische Fachangestellte stimmen sich eng miteinander ab. So laufen Informationen zusammen, Abläufe bleiben verlässlich und Sie haben in unserer Praxis kompetente Ansprechpartnerinnen und Ansprechpartner.'),
                ], 'tunes' => ['spaceTop' => 'none']],
                ['type' => 'doctors', 'data' => [
                    'title_strong' => 'Ärztinnen und Ärzte', 'title_light' => 'Persönlich für Sie da.',
                    'intro' => 'In einer Gemeinschaftspraxis verbinden sich unterschiedliche Erfahrungen und fachliche Perspektiven. Zugleich bleiben der persönliche Kontakt und eine kontinuierliche hausärztliche Begleitung erhalten.',
                    'link_label' => 'Termin anfragen', 'link' => '#kontakt',
                    'items' => array_fill(0, 3, [
                        'fach' => '[Fachbezeichnung]', 'titel' => '[Akad. Titel]', 'name' => '[Name Ärztin/Arzt]',
                        'zusatz' => '[Zusatzqualifikationen]', 'text' => '[Persönlicher Vorstellungstext – zwei bis drei Sätze, max. ca. 400 Zeichen]',
                        'sprechzeiten' => '[optional]', 'foto' => null,
                    ]),
                ], 'tunes' => ['anchor' => 'aerzte', 'showInNav' => true, 'navLabel' => 'Ärztinnen und Ärzte', 'background' => 'gray']],
                ['type' => 'services', 'data' => [
                    'title_strong' => 'Leistungen', 'title_light' => 'Gut versorgt – in jeder Lebensphase.',
                    'intro' => 'Als hausärztliche Gemeinschaftspraxis sind wir für viele gesundheitliche Fragen die erste Anlaufstelle. Unser Angebot reicht von der Vorsorge über die moderne Diagnostik bis zur langfristigen Betreuung chronischer Erkrankungen.',
                    'items' => [
                        ['title' => 'Hausärztliche Versorgung', 'text' => 'Bei akuten Beschwerden, neuen gesundheitlichen Fragen oder länger bestehenden Erkrankungen sind wir persönlich für Sie da. Wir untersuchen sorgfältig, ordnen Beschwerden ein und koordinieren bei Bedarf die weitere fachärztliche Behandlung. Auch Hausbesuche können nach medizinischer Notwendigkeit und vorheriger Abstimmung erfolgen.'],
                        ['title' => 'Vorsorge und Prävention', 'text' => 'Viele Erkrankungen lassen sich frühzeitig erkennen oder durch gezielte Vorsorge vermeiden. Wir bieten Gesundheits-Check-ups, Krebsvorsorgeuntersuchungen sowie eine individuelle Impfberatung mit den empfohlenen Impfungen an. Auch Jugendarbeitsschutzuntersuchungen gehören zu unserem Angebot.'],
                        ['title' => 'Diagnostik', 'text' => 'Für eine fundierte Beurteilung stehen uns verschiedene diagnostische Möglichkeiten zur Verfügung. Dazu gehören Ruhe- und Belastungs-EKG, Langzeit-Blutdruckmessungen, Lungenfunktionsprüfungen, Ultraschalluntersuchungen und Laboruntersuchungen. Welche Untersuchung sinnvoll ist, entscheiden wir immer auf Grundlage Ihrer persönlichen Situation.'],
                        ['title' => 'Chronische Erkrankungen und DMP', 'text' => 'Chronische Erkrankungen benötigen eine kontinuierliche und gut abgestimmte Betreuung. Im Rahmen strukturierter Behandlungsprogramme, der sogenannten Disease-Management-Programme, begleiten wir unter anderem Menschen mit Diabetes mellitus, koronarer Herzkrankheit und weiteren chronischen Erkrankungen. Regelmäßige Kontrollen helfen dabei, Veränderungen frühzeitig zu erkennen und die Behandlung gemeinsam anzupassen.'],
                    ],
                ], 'tunes' => ['anchor' => 'leistungen', 'showInNav' => true, 'navLabel' => 'Leistungen']],
                ['type' => 'team_photo', 'data' => [
                    'title_strong' => 'Team', 'title_light' => 'Gute Medizin ist Teamarbeit.',
                    'text' => '<p>Unser Praxisteam sorgt dafür, dass medizinische Betreuung und organisatorische Abläufe zuverlässig ineinandergreifen. Die Mitarbeiterinnen und Mitarbeiter am Empfang, in der Diagnostik und in der Behandlungsassistenz sind wichtige Ansprechpartner für unsere Patientinnen und Patienten.</p><p>Ein freundlicher, respektvoller Umgang ist uns dabei ebenso wichtig wie eine gute Abstimmung im Team. Denn eine Praxis funktioniert besonders gut, wenn alle Beteiligten miteinander und nicht nur nebeneinander arbeiten.</p>',
                    'show_jobs_box' => true, 'jobs_title_strong' => 'Ausbildung', 'jobs_title_light' => 'Werden Sie Teil des Teams.',
                    'jobs_text' => '[Kurzer Text zu Ausbildung, Qualifikationen oder offenen Stellen, z. B. „Medizinische Fachangestellte (m/w/d)“]',
                    'jobs_button_label' => 'Stellenangebote ansehen', 'jobs_link' => '#kontakt',
                ], 'tunes' => ['anchor' => 'team', 'showInNav' => true, 'navLabel' => 'Team', 'spaceTop' => 'none']],
                ['type' => 'accordion', 'data' => [
                    'title_strong' => 'Hinweise', 'title_light' => 'Gut vorbereitet in die Praxis.',
                    'intro' => '<p>Damit wir Ihren Besuch gut vorbereiten können, vereinbaren Sie nach Möglichkeit vorab einen Termin. Bei akuten Beschwerden setzen Sie sich bitte zunächst telefonisch mit uns in Verbindung. So können wir die Dringlichkeit einschätzen und unnötige Wartezeiten vermeiden.</p>',
                    'show_emergency' => true,
                    'items' => [
                        ['q' => 'Terminvereinbarung', 'a' => '<p>Damit wir Ihren Besuch gut vorbereiten können, vereinbaren Sie nach Möglichkeit vorab einen Termin – telefonisch unter [Telefonnummer] oder [Terminlink, falls vorhanden].</p>'],
                        ['q' => 'Akutsprechstunde', 'a' => '<p>[Ablauf der Akutsprechstunde – noch zu bestätigen]. Bei akuten Beschwerden setzen Sie sich bitte zunächst telefonisch mit uns in Verbindung.</p>'],
                        ['q' => 'Rezeptbestellung', 'a' => '<p>[Verfahren zur Rezeptbestellung – noch zu bestätigen].</p>', 'service' => 'rezept'],
                        ['q' => 'Überweisungen', 'a' => '<p>[Verfahren für Überweisungen – noch zu bestätigen].</p>', 'service' => 'ueberweisung'],
                        ['q' => 'Bitte mitbringen', 'a' => '<p>Ihre Versichertenkarte sowie [weitere Unterlagen, z. B. Medikationsplan, Vorbefunde – noch zu bestätigen].</p>'],
                        ['q' => 'Vertretung und Urlaubszeiten', 'a' => '<p>[Aktuelle Urlaubszeiten und Vertretungspraxis mit Adresse und Telefonnummer].</p>'],
                        ['q' => 'Außerhalb der Sprechzeiten', 'a' => '<p>Ärztlicher Bereitschaftsdienst: 116 117. In lebensbedrohlichen Notfällen: 112. [Ggf. nächstgelegene Notdienstpraxis ergänzen].</p>'],
                    ],
                    'footer' => 'Formulare: <a href="/bausteine#formulare"><b>[Download, z. B. Anamnesebogen (PDF)]</b></a>',
                ], 'tunes' => ['anchor' => 'hinweise', 'background' => 'gray']],
                ['type' => 'contact', 'data' => [
                    'title_strong' => 'Kontakt', 'title_light' => 'Wir sind für Sie erreichbar.', 'show_map' => true,
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
                    'text' => '<p>Viele Erkrankungen lassen sich frühzeitig erkennen oder durch gezielte Vorsorge vermeiden. Wir bieten Gesundheits-Check-ups, Krebsvorsorgeuntersuchungen sowie eine individuelle Impfberatung an.</p>',
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
                    'variant' => 'full', 'text' => 'Wir behandeln nicht nur Beschwerden.', 'highlight' => 'Wir begleiten Menschen.',
                    'source' => '[optionale Quelle / Name]',
                ], 'tunes' => ['background' => 'bordeaux', 'spaceTop' => 'small', 'spaceBottom' => 'small']],
                ['type' => 'text_columns', 'data' => [
                    'variant' => 'columns', 'title_strong' => 'Praxis', 'title_light' => 'Medizin beginnt mit Zuhören.',
                    'columns' => $p(
                        'Eine gute hausärztliche Versorgung beginnt für uns mit einem offenen Gespräch. Beschwerden lassen sich häufig nicht isoliert betrachten. Deshalb beziehen wir Ihre persönliche Situation ein.',
                        'Unser Anspruch ist eine verständliche, sorgfältige und verlässliche Medizin. Wir erklären Befunde nachvollziehbar und treffen die nächsten Entscheidungen gemeinsam mit Ihnen.'
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
