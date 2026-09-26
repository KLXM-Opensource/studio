<?php
// SPDX-License-Identifier: MIT
/**
 * Demo-Inhalte des Start-Kits: eine Startseite und eine Unterseite – jede Beispielblock-Art kommt einmal vor.
 * Wird von seed.php eingebunden und gibt die Liste der Seiten zurück. Feldnamen = theme.php § 8.
 * Texte in [eckigen Klammern] gelten als Platzhalter (Dashboard zeigt sie als offen; filled() liefert false).
 */
return [
    [
        'slug' => 'start', 'title' => 'Start', 'is_home' => true, 'menu' => false,
        'meta_description' => 'Startseite des Start-Kits für KLXM Studio.',
        'blocks' => [
            ['type' => 'hero', 'data' => [
                'variant' => 'center', 'eyebrow' => 'Start-Kit',
                'title' => 'Ein ruhiger Ausgangspunkt für Ihr eigenes Kit.',
                'text' => 'Systemschrift, eine Akzentfarbe, hell und dunkel – alles über Design-Tokens steuerbar und in wenigen Dateien erklärt.',
                'button_label' => 'Mehr erfahren', 'button_link' => '#leistungen',
                'button2_label' => 'Über uns', 'button2_link' => '/ueber-uns',
            ]],
            ['type' => 'cards', 'tunes' => ['background' => 'muted', 'anchor' => 'leistungen', 'showInNav' => true, 'navLabel' => 'Leistungen'], 'data' => [
                'eyebrow' => 'Leistungen', 'title' => 'Was wir anbieten',
                'intro' => 'Drei Beispielkarten mit Symbol – das Raster passt sich der Breite an.',
                'items' => [
                    ['icon' => 'compass', 'title' => 'Beratung', 'text' => 'Ziele klären, Inhalte ordnen, den richtigen Umfang finden.', 'link' => '/ueber-uns'],
                    ['icon' => 'pencil-simple', 'title' => 'Gestaltung', 'text' => 'Farben, Schrift und Abstände als nachvollziehbares System.', 'link' => ''],
                    ['icon' => 'rocket', 'title' => 'Umsetzung', 'text' => 'Schnell, barrierearm und ohne Tracking – direkt pflegbar.', 'link' => ''],
                ],
            ]],
            ['type' => 'text_image', 'data' => [
                'variant' => 'right', 'eyebrow' => 'Arbeitsweise', 'title' => 'Klein anfangen, sauber erweitern',
                'text' => '<p>Jeder Block besteht aus einer Definition in <code>theme.php</code> und einer kurzen Vorlage in <code>blocks/</code>. Ohne Bild nimmt der Text die volle Breite ein.</p><p class="t-note">Tipp: In der Seitenleiste des Editors lässt sich ein Bild wählen und zuschneiden.</p>',
                'button_label' => 'Über uns', 'button_link' => '/ueber-uns', 'image' => null, 'ratio' => '4:3',
            ]],
            ['type' => 'faq', 'data' => [
                'eyebrow' => 'Fragen', 'title' => 'Häufige Fragen', 'intro' => '',
                'items' => [
                    ['q' => 'Braucht das Kit JavaScript?', 'a' => '<p>Nein. Menü, Suche und diese Fragen funktionieren ohne; ein kleines Skript ergänzt nur Komfort.</p>'],
                    ['q' => 'Lädt die Website externe Inhalte?', 'a' => '<p>Nein – keine externen Schriften, keine Cookies, kein Tracking. Videos von YouTube oder Vimeo laden erst nach einem Klick.</p>'],
                    ['q' => 'Wie ändere ich Farben und Schrift?', 'a' => '<p>Unter <strong>Verwaltung → Design</strong>. Drei geprüfte Voreinstellungen sind enthalten.</p>'],
                ],
            ]],
            ['type' => 'cta', 'tunes' => ['background' => 'accent'], 'data' => [
                'title' => 'Bereit für das eigene Kit?',
                'text' => 'php bin/console kit:create meinkit – und los.',
                'button_label' => 'E-Mail schreiben', 'button_link' => 'email', 'button2_label' => '', 'button2_link' => '',
            ]],
        ],
    ],
    [
        'slug' => 'ueber-uns', 'title' => 'Über uns', 'menu' => true,
        'meta_description' => 'Beispiel-Unterseite des Start-Kits.',
        'blocks' => [
            ['type' => 'hero', 'data' => [
                'variant' => 'center', 'eyebrow' => 'Über uns', 'title' => 'Eine Unterseite mit Text',
                'text' => 'Der Block „Text“ zeigt die Stile der Formatierungsleiste.',
                'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => '',
            ]],
            ['type' => 'text', 'data' => [
                'eyebrow' => '', 'title' => 'Wer wir sind',
                'text' => '<p class="t-lead">Die Beispiel GmbH ist frei erfunden – sie zeigt nur, wie Inhalte im Start-Kit aussehen.</p>'
                    . '<p>Fließtext mit <a href="/">Link</a>, <mark>Markierung</mark> und <span class="c-accent">Akzentfarbe</span>.</p>'
                    . '<h3>Zwischenüberschrift</h3><ul><li>Listen mit Aufzählungszeichen</li><li>ruhige Abstände</li></ul>'
                    . '<blockquote><p>Weniger Dateien, mehr Klarheit.</p></blockquote>'
                    . '<p class="t-small">Kleiner Hinweistext, z. B. für Anmerkungen.</p>',
            ]],
            ['type' => 'cta', 'tunes' => ['background' => 'dark'], 'data' => [
                'title' => 'Fragen?', 'text' => 'Schreiben Sie uns – wir antworten in der Regel am selben Tag.',
                'button_label' => 'Kontakt', 'button_link' => 'email', 'button2_label' => 'Zur Startseite', 'button2_link' => '/',
            ]],
        ],
    ],
    [
        // Musterseite „Hero-Varianten“: jede Variante des Blocks hero einmal (Beispiel „search“ = eigene Variante, siehe theme.php § 8)
        'slug' => 'hero-varianten', 'title' => 'Hero-Varianten', 'menu' => false,
        'meta_description' => 'Die Varianten des Einstiegs im Start-Kit: zentriert, Text und Bild, Such-Einstieg.',
        'blocks' => [
            ['type' => 'hero', 'data' => [
                'variant' => 'search', 'eyebrow' => 'Variante „Such-Einstieg“', 'title' => 'Wie können wir helfen?',
                'text' => 'Suchfeld mit Vorschlägen beim Tippen – darunter häufige Suchbegriffe.',
                'search_label' => 'Was suchen Sie?', 'search_placeholder' => 'z. B. Beratung, Kontakt …',
                'search_chips' => "Beratung\nGestaltung\nÜber uns | /ueber-uns",
                'button_label' => '', 'button_link' => '', 'button2_label' => '', 'button2_link' => '',
            ]],
            ['type' => 'hero', 'tunes' => ['background' => 'muted', 'divider' => true], 'data' => [
                'variant' => 'center', 'eyebrow' => 'Variante „Zentriert“', 'title' => 'Kurze Botschaft ohne Bild',
                'text' => 'Für Unterseiten und klare Aussagen.',
                'button_label' => 'Über uns', 'button_link' => '/ueber-uns', 'button2_label' => '', 'button2_link' => '',
            ]],
        ],
    ],
];
