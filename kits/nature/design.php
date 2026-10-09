<?php
/*
 * Design-Tokens des Kits „nature“ für den Style-Editor (Verwaltung → Design) – siehe Core\Design.
 *
 * Jeder Token wird eine CSS-Variable (--n-…) oder eine Klasse am <html> (hdr-bar, btn-soft, img-leaf, mk-leaf, sz-herbst …).
 * Farbprinzip: erdige Palette. „Akzent“ ist die Füllfarbe (Buttons, Skalen, Marken) und muss als Bedienelement
 * mindestens 3:1 zum Hintergrund haben; „Akzent als Schrift“ ist die lesbare Fassung für Links und Dachzeilen (≥ 4,5:1).
 * „Erde“ ist die zweite, rein schmückende Farbe (Blattmarken, Sonne, Beeren) – als Markierung ≥ 3:1.
 * Voreinstellungen sind mit tools/contrast.php auf WCAG 2.2 AA geprüft (hell und dunkel):
 *   php kits/nature/tools/contrast.php
 * Standardwerte müssen mit :root in assets/css/_tokens.css übereinstimmen (Voreinstellung „Moos“).
 */

// Reihenfolge der Farbwerte in den Voreinstellungen
$colorKeys = ['accent', 'accent_ink', 'on_accent', 'ink', 'text', 'muted', 'background', 'surface', 'panel', 'line', 'dark_section', 'earth'];

/** Voreinstellung: Farben hell + dunkel (je 12 Werte in obiger Reihenfolge) und übrige Werte */
$preset = function (string $label, string $desc, array $light, array $dark, array $rest) use ($colorKeys): array {
    $values = [];
    foreach ($colorKeys as $i => $k) {
        $values[$k] = $light[$i];
        $values[$k . '@dark'] = $dark[$i];
    }
    return ['label' => $label, 'description' => $desc, 'values' => $values + $rest];
};

$color = fn(string $name, string $label, string $var, string $light, string $dark, ?array $contrast = null, string $help = '') => array_filter([
    'name' => $name, 'label' => $label, 'type' => 'color', 'var' => $var, 'default' => $light, 'dark' => $dark,
    'contrast' => $contrast, 'help' => $help,
], fn($v) => $v !== null && $v !== '');

// Gemeinsame Werte der Voreinstellungen (werden je Vorlage überschrieben)
$base = [
    'font_body' => 'nunito-sans', 'font_head' => 'fraunces', 'label_style' => 'italic',
    'fs_min' => 16, 'fs_max' => 18.5, 'ratio' => 1.25, 'heading_weight' => 560, 'soft' => 100, 'heading_tracking' => -1.5,
    'radius' => 16, 'buttons' => 'soft', 'images' => 'leaf', 'depth' => 'relief', 'grid' => 'normal', 'space' => 'normal', 'wrap' => 74,
    'markers' => 'leaf', 'motifs' => true, 'season' => 'auto',
    'header' => 'bar', 'header_sticky' => true, 'nav_parent' => 'split', 'nav_levels' => 'indent', 'footer' => 'index', 'pagebg' => 'grain', 'dividers' => 'wave',
    'motion' => true, 'drift' => true, 'dark' => true,
];

return [
    'groups' => [
        ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
            $color('accent', 'Akzent (Füllfarbe)', '--n-a', '#52703A', '#9BC27A', ['with' => 'background', 'min' => 3],
                'Buttons, Skalen, aktive Marken. Als Bedienelement mindestens 3:1 zum Hintergrund.'),
            $color('accent_ink', 'Akzent als Schrift', '--n-a-ink', '#3F5B28', '#B3D493', ['with' => 'background', 'min' => 4.5],
                'Links, Dachzeilen und Hervorhebungen – eine lesbare Fassung des Akzents (hell dunkler, dunkel heller).'),
            $color('on_accent', 'Schrift auf Akzent', '--n-a-on', '#FFFFFF', '#122009', ['with' => 'accent', 'min' => 4.5],
                'Beschriftung der Buttons und des Abschnitts „Akzentfarbe“.'),
            $color('ink', 'Überschriften', '--n-ink', '#1F281D', '#EFF1E7', ['with' => 'background', 'min' => 7]),
            $color('text', 'Fließtext', '--n-text', '#3A4034', '#D0D5C6', ['with' => 'background', 'min' => 4.5]),
            $color('muted', 'Nebentext', '--n-muted', '#5A5F4F', '#A4AC9A', ['with' => 'surface', 'min' => 4.5],
                'Einleitungen, Beschriftungen, Metadaten – auch auf getönten Flächen lesbar.'),
            $color('background', 'Hintergrund (Papier)', '--n-bg', '#F6F2E8', '#111913'),
            $color('surface', 'Getönte Fläche (Sand)', '--n-surface', '#ECE5D3', '#172019', ['with' => 'text', 'min' => 4.5],
                'Abschnitte „Sand“, Fußbereich, Vertiefungen.'),
            $color('panel', 'Karten', '--n-panel', '#FCFAF4', '#1C261F', ['with' => 'text', 'min' => 4.5],
                'Karten, Menüs und Formulare.'),
            $color('line', 'Linien', '--n-line', '#DCD3BE', '#2D3A30', null, 'Trennlinien und Rahmen (dekorativ).'),
            $color('dark_section', 'Waldnacht (dunkle Abschnitte)', '--n-dark', '#1F2B22', '#1A241D', ['with' => '#F1EEE3', 'min' => 7, 'dark_with' => '#F1EEE3'],
                'Hintergrund der Abschnitte „Waldnacht“ (Schrift hell). Im dunklen Farbschema etwas heller als der Hintergrund.'),
            $color('earth', 'Erde (Schmuckfarbe)', '--n-earth', '#A85A32', '#E0976A', ['with' => 'background', 'min' => 3],
                'Zweite Farbe für Blattmarken, Sonne, Beeren und die Markierung „heute“ – nie für Fließtext.'),
        ]],
        ['id' => 'typo', 'label' => 'Typografie', 'tokens' => [
            ['name' => 'font_body', 'label' => 'Schrift Fließtext', 'type' => 'font', 'var' => '--n-font', 'default' => 'nunito-sans'],
            ['name' => 'font_head', 'label' => 'Schrift Überschriften', 'type' => 'font', 'var' => '--n-font-head', 'default' => 'fraunces'],
            ['name' => 'label_style', 'label' => 'Dachzeilen und Etiketten', 'type' => 'choice', 'class' => 'lb-{value}', 'default' => 'italic',
                'options' => ['italic' => 'Kursiv in der Überschriftenschrift (erzählend)', 'caps' => 'Kleine Versalien (sachlich)']],
            ['name' => 'fs_min', 'label' => 'Grundschrift auf kleinen Bildschirmen (px)', 'type' => 'range', 'var' => '--n-fs-min', 'unit' => '',
                'min' => 15, 'max' => 19, 'step' => 0.5, 'default' => 16, 'help' => 'Bezogen auf die Standardgröße des Browsers (16 px); Besucher können weiter zoomen.'],
            ['name' => 'fs_max', 'label' => 'Grundschrift auf großen Bildschirmen (px)', 'type' => 'range', 'var' => '--n-fs-max', 'unit' => '',
                'min' => 15, 'max' => 22, 'step' => 0.5, 'default' => 18.5],
            ['name' => 'ratio', 'label' => 'Verhältnis der Schriftstufen', 'type' => 'range', 'var' => '--n-ratio', 'unit' => '',
                'min' => 1.125, 'max' => 1.5, 'step' => 0.025, 'default' => 1.25,
                'help' => '1,125 = zurückhaltend · 1,25 = ausgewogen · 1,333 = deutlich · 1,414+ = plakativ. Auf kleinen Bildschirmen automatisch flacher.'],
            ['name' => 'heading_weight', 'label' => 'Stärke der Überschriften', 'type' => 'range', 'var' => '--n-hw', 'unit' => '',
                'min' => 300, 'max' => 800, 'step' => 20, 'default' => 560, 'help' => 'Young Serif gibt es nur in einer Stärke – der Wert wirkt dann nicht.'],
            ['name' => 'soft', 'label' => 'Weichheit der Serifen (Fraunces)', 'type' => 'range', 'var' => '--n-soft', 'unit' => '',
                'min' => 0, 'max' => 100, 'step' => 10, 'default' => 100, 'help' => '0 = scharf geschnitten, 100 = weich gerundet. Nur bei der Schrift Fraunces.'],
            ['name' => 'heading_tracking', 'label' => 'Laufweite der Überschriften (1/100 em)', 'type' => 'range', 'var' => '--n-track', 'unit' => '',
                'min' => -4, 'max' => 2, 'step' => 0.5, 'default' => -1.5],
        ]],
        ['id' => 'form', 'label' => 'Formen & Natur', 'tokens' => [
            ['name' => 'radius', 'label' => 'Eckenradius der Karten', 'type' => 'range', 'var' => '--n-radius', 'unit' => 'px', 'min' => 4, 'max' => 32, 'step' => 2, 'default' => 16,
                'help' => 'Weich wie Kiesel. Buttons und Eingabefelder behalten 8 px.'],
            ['name' => 'buttons', 'label' => 'Buttons', 'type' => 'choice', 'class' => 'btn-{value}', 'default' => 'soft', 'preview' => 'radius',
                'options' => ['soft' => 'Weich (8 px, gefüllt)', 'pill' => 'Rund (Kiesel)', 'outline' => 'Kontur'],
                'values' => ['soft' => '8px', 'pill' => '999px', 'outline' => '8px']],
            ['name' => 'images', 'label' => 'Bildform', 'type' => 'choice', 'class' => 'img-{value}', 'default' => 'leaf', 'preview' => 'radius',
                'options' => ['soft' => 'Abgerundet', 'leaf' => 'Blatt (zwei weite Ecken)', 'pebble' => 'Kiesel (organisch)'],
                'values' => ['soft' => '16px', 'leaf' => '48px', 'pebble' => '999px'],
                'help' => 'Form großer Bilder in Einstieg, Text + Bild und Karten.'],
            ['name' => 'depth', 'label' => 'Tiefe', 'type' => 'choice', 'class' => 'depth-{value}', 'default' => 'relief',
                'options' => ['flat' => 'Flach (nur Linien)', 'relief' => 'Sanft (weicher Schatten)', 'deep' => 'Erhaben (deutlicher Schatten)']],
            ['name' => 'grid', 'label' => 'Dichte', 'type' => 'choice', 'var' => '--n-u', 'default' => 'normal',
                'options' => ['compact' => 'Kompakt', 'normal' => 'Normal', 'wide' => 'Luftig'],
                'values' => ['compact' => '.4375rem', 'normal' => '.5rem', 'wide' => '.5625rem'],
                'help' => 'Innen- und Zwischenabstände.'],
            ['name' => 'space', 'label' => 'Abstand zwischen Abschnitten', 'type' => 'choice', 'var' => '--n-space', 'default' => 'normal',
                'options' => ['compact' => 'Kompakt', 'normal' => 'Normal', 'airy' => 'Großzügig'], 'values' => ['compact' => '.75', 'normal' => '1', 'airy' => '1.3']],
            ['name' => 'wrap', 'label' => 'Maximale Inhaltsbreite (rem)', 'type' => 'range', 'var' => '--n-wrap', 'unit' => 'rem', 'min' => 60, 'max' => 96, 'step' => 2, 'default' => 74,
                'help' => '1 rem = 16 px.'],
            ['name' => 'markers', 'label' => 'Marken vor Dachzeilen und Listen', 'type' => 'choice', 'class' => 'mk-{value}', 'default' => 'leaf',
                'options' => ['leaf' => 'Blatt', 'dot' => 'Samenkorn (Punkt)', 'plain' => 'Ohne']],
            ['name' => 'motifs', 'label' => 'Illustrationen und Ornamente', 'type' => 'bool', 'class' => 'has-motifs', 'default' => true,
                'help' => 'Landschaft im Einstieg ohne Bild, Höhenlinien, Jahresringe an Kennzahlen. Aus = ruhiger.'],
            ['name' => 'season', 'label' => 'Jahreszeit der Illustrationen', 'type' => 'choice', 'class' => 'sz-{value}', 'default' => 'auto',
                'options' => ['auto' => 'Automatisch nach Datum', 'fruehling' => 'Frühling (Blüten)', 'sommer' => 'Sommer (Sonne)', 'herbst' => 'Herbst (Blätter)', 'winter' => 'Winter (Schnee)', 'none' => 'Ohne'],
                'help' => 'Kleine Details in der Landschaft des Einstiegs. „Automatisch“ wechselt mit den Jahreszeiten (nach dem nächsten Leeren des Seiten-Caches).'],
        ]],
        ['id' => 'navigation', 'label' => 'Kopf & Fuß', 'tokens' => [
            ['name' => 'header', 'label' => 'Kopfbereich', 'type' => 'choice', 'class' => 'hdr-{value}', 'default' => 'bar', 'preview' => 'nav',
                'thumbs' => ['bar' => 'left', 'centered' => 'center', 'split' => 'split', 'index' => 'burger'],
                'options' => [
                    'bar' => 'Leiste – Marke links, Menü rechts',
                    'centered' => 'Zentriert – Marke über dem Menü',
                    'split' => 'Geteilt – Menü links, Marke in der Mitte',
                    'index' => 'Minimal – Marke und Menü-Schaltfläche',
                ],
                'help' => 'Das Menü steht in der Leiste, solange es hineinpasst – sonst öffnet eine Schaltfläche das Menü als Seitenblatt.'],
            ['name' => 'header_sticky', 'label' => 'Kopfbereich beim Scrollen sichtbar halten', 'type' => 'bool', 'class' => 'hdr-sticky', 'default' => true],
            ['name' => 'nav_parent', 'label' => 'Menüpunkte mit Unterseiten', 'type' => 'choice', 'class' => 'np-{value}', 'default' => 'split',
                'options' => ['split' => 'Link + Pfeil – der Menüpunkt öffnet seine Seite, der Pfeil die Unterseiten', 'hover' => 'Link + Pfeil, Unterseiten öffnen auch beim Überfahren mit der Maus', 'overview' => 'Klick öffnet die Unterseiten, Eintrag „Übersicht“ führt zur Seite'],
                'help' => 'Gilt für das Menü in der Leiste und im Seitenblatt (Telefon). „Link + Pfeil“ kommt ohne zusätzlichen Eintrag „Übersicht“ aus.'],
            ['name' => 'nav_levels', 'label' => 'Dritte Menüebene im Aufklappmenü', 'type' => 'choice', 'class' => 'nv-{value}', 'default' => 'indent',
                'options' => ['indent' => 'Eingerückt – mit Linie, etwas kleiner', 'groups' => 'Gruppiert – zweite Ebene als Zwischenüberschrift, dritte darunter'],
                'help' => 'Wie Unterseiten von Unterseiten im Aufklappmenü der Leiste erscheinen.'],
            ['name' => 'footer', 'label' => 'Fußbereich', 'type' => 'choice', 'class' => 'ft-{value}', 'default' => 'index',
                'options' => ['index' => 'Spalten (Seiten, Kontakt, Zeiten)', 'panel' => 'Mit Band (Satz, Button, Spalten)', 'simple' => 'Schlicht in einer Zeile']],
            ['name' => 'pagebg', 'label' => 'Seitenhintergrund', 'type' => 'choice', 'class' => 'pagebg-{value}', 'default' => 'grain',
                'options' => ['plain' => 'Einfarbig', 'grain' => 'Papierstruktur', 'contours' => 'Höhenlinien', 'leaves' => 'Blattadern']],
            ['name' => 'dividers', 'label' => 'Übergänge zwischen farbigen Abschnitten', 'type' => 'choice', 'class' => 'dv-{value}', 'default' => 'wave',
                'options' => ['wave' => 'Welle', 'hills' => 'Hügel', 'straight' => 'Gerade']],
        ]],
        // Kopfbereich-Aktionen (Core\HeaderActions) – Standard des Kits: Kontakt-Chip mit Öffnungsstatus („Hofladen geöffnet“) + Textlink
        \Core\HeaderActions::designGroup(['ha_cta_style' => 'link', 'ha_search' => 'popover', 'ha_contact' => 'phone']),
        ['id' => 'modus', 'label' => 'Bewegung & Farbschema', 'tokens' => [
            ['name' => 'motion', 'label' => 'Sanftes Einblenden, Skalen füllen sich', 'type' => 'bool', 'class' => 'has-motion', 'default' => true,
                'help' => 'Besucher mit „Bewegung reduzieren“ sehen nie Animationen – unabhängig von dieser Einstellung.'],
            ['name' => 'drift', 'label' => 'Blätter und Höhenlinien bewegen sich kaum merklich', 'type' => 'bool', 'class' => 'has-drift', 'default' => true,
                'help' => 'Sehr langsam, nie bei „Bewegung reduzieren“.'],
            ['name' => 'dark', 'label' => 'Waldnacht (dunkles Farbschema), wenn im Gerät der Besucher eingestellt', 'type' => 'bool', 'class' => 'has-dark', 'default' => true],
        ]],
    ],

    // Schriften: 'fontsource' = installiert der Schriften-Manager (Core\Fonts, fonts:sync; selbst gehostet, ohne externe Anfragen) – ohne externe Anfragen
    'fonts' => [
        'fraunces' => ['label' => 'Fraunces (weiche Serif · variabel)', 'stack' => 'Fraunces,"Iowan Old Style",Georgia,ui-serif,serif', 'fontsource' => 'fraunces', 'styles' => ['normal', 'italic'], 'axis' => ['normal' => 'soft', 'italic' => 'wght']],
        'young-serif' => ['label' => 'Young Serif (freundliche Serif · eine Stärke)', 'stack' => '"Young Serif",Georgia,ui-serif,serif', 'fontsource' => 'young-serif'],
        'nunito-sans' => ['label' => 'Nunito Sans (freundliche Grotesk · variabel)', 'stack' => '"Nunito Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'fontsource' => 'nunito-sans'],
        'source-sans-3' => ['label' => 'Source Sans 3 (klare Grotesk · variabel)', 'stack' => '"Source Sans 3",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'fontsource' => 'source-sans-3'],
        'system' => ['label' => 'Systemschrift (ohne Download)', 'stack' => 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'],
        'system-serif' => ['label' => 'System-Serif (ohne Download)', 'stack' => '"Iowan Old Style","Palatino Linotype",Georgia,ui-serif,serif'],
    ],

    // Sechs Vorlagen – alle hell und dunkel AA-geprüft (tools/contrast.php)
    'presets' => [
        'moos' => $preset('Moos (Standard)', 'Papier und Sand, Moosgrün als Akzent, Ton als Schmuckfarbe; Fraunces + Nunito Sans, Wellen, Papierstruktur.',
            ['#52703A', '#3F5B28', '#FFFFFF', '#1F281D', '#3A4034', '#5A5F4F', '#F6F2E8', '#ECE5D3', '#FCFAF4', '#DCD3BE', '#1F2B22', '#A85A32'],
            ['#9BC27A', '#B3D493', '#122009', '#EFF1E7', '#D0D5C6', '#A4AC9A', '#111913', '#172019', '#1C261F', '#2D3A30', '#1A241D', '#E0976A'],
            $base),
        'fruehling' => $preset('Frühling', 'Frisches Blattgrün und Kirschblüte auf hellem Grund; runde Buttons, Kiesel-Bilder, Blattadern im Hintergrund.',
            ['#3F7A3D', '#2F6230', '#FFFFFF', '#1C2A1F', '#37433A', '#56615A', '#F5F6EE', '#E7EDDC', '#FCFDF8', '#D3DCC6', '#1C2C22', '#B8456E'],
            ['#86C97F', '#A3DA9C', '#0E1F0D', '#EEF3EA', '#CDD7CB', '#A0AE9F', '#101A13', '#162219', '#1B281E', '#2B3B2F', '#19271D', '#EE8FB1'],
            ['season' => 'fruehling', 'buttons' => 'pill', 'images' => 'pebble', 'pagebg' => 'leaves', 'soft' => 100, 'heading_weight' => 520] + $base),
        'sommer' => $preset('Sommer', 'See-Blau und Weizengold auf warmem Papier; Young Serif, Hügel-Übergänge, zentrierter Kopf.',
            ['#2B6A7F', '#225768', '#FFFFFF', '#1B2624', '#36413E', '#56605B', '#F8F4E6', '#EFE6CC', '#FDFBF3', '#DFD4B6', '#183038', '#A0700F'],
            ['#6FB6CC', '#93CADB', '#06171D', '#EEF2EF', '#CFD7D4', '#A1ADA9', '#0F181A', '#152124', '#1A272A', '#2A3A3E', '#172629', '#E5B04D'],
            ['season' => 'sommer', 'font_head' => 'young-serif', 'heading_tracking' => -1, 'dividers' => 'hills', 'header' => 'centered', 'images' => 'soft', 'space' => 'airy'] + $base),
        'herbst' => $preset('Herbst', 'Rost und Ocker, Borke als Schrift; Fraunces + Source Sans 3, Höhenlinien, Band im Fußbereich.',
            ['#9A4B24', '#843F1C', '#FFFFFF', '#2A1F17', '#45382E', '#64564A', '#F5EEE3', '#EBDFCC', '#FCF8F1', '#DDCDB4', '#2E2019', '#8F6A12'],
            ['#E0885A', '#EFA57D', '#1F0D04', '#F4EEE6', '#DDD2C6', '#B0A395', '#1A130F', '#211914', '#271E18', '#3A2E26', '#241A14', '#D9AE4E'],
            ['season' => 'herbst', 'font_body' => 'source-sans-3', 'soft' => 60, 'heading_weight' => 600, 'pagebg' => 'contours', 'footer' => 'panel', 'markers' => 'leaf'] + $base),
        'winter' => $preset('Winter', 'Kiefer, Reif und Beere – kühl und klar; Young Serif + Source Sans 3, Kontur-Buttons, gerade Übergänge.',
            ['#2F5D52', '#274F45', '#FFFFFF', '#1A2426', '#354144', '#535F62', '#F2F4F2', '#E3E9E7', '#FBFCFC', '#CFD8D6', '#182528', '#A2383F'],
            ['#7FB5A6', '#9ECABD', '#0A1A16', '#EEF3F3', '#CCD6D6', '#9FACAD', '#0F1618', '#151E20', '#1A2427', '#2A373A', '#172124', '#E6828A'],
            ['season' => 'winter', 'font_head' => 'young-serif', 'font_body' => 'source-sans-3', 'label_style' => 'caps', 'buttons' => 'outline', 'dividers' => 'straight',
             'markers' => 'dot', 'images' => 'soft', 'depth' => 'flat', 'pagebg' => 'plain', 'heading_tracking' => -0.5] + $base),
        'waldnacht' => $preset('Waldnacht', 'Von Anfang an dunkel: tiefes Waldgrün, Moos als Akzent, Glut als Schmuckfarbe; Höhenlinien, Minimal-Kopf.',
            ['#9BC27A', '#B3D493', '#122009', '#EFF1E7', '#D0D5C6', '#A4AC9A', '#131C16', '#19231C', '#1E2922', '#303D33', '#1C2820', '#E0976A'],
            ['#9BC27A', '#B3D493', '#122009', '#EFF1E7', '#D0D5C6', '#A4AC9A', '#0E1510', '#141D17', '#19231C', '#2A362D', '#18221B', '#E0976A'],
            ['header' => 'index', 'footer' => 'panel', 'pagebg' => 'contours', 'depth' => 'deep', 'season' => 'none'] + $base),
    ],

    // Dunkle Werte bei „Waldnacht“ (Klasse has-dark) und Geräte-Einstellung; Vorschau im Editor setzt is-dark
    'dark' => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark', 'force' => 'is-dark'],

    // Musterseite für die Vorschau im Editor (siehe tools/demo-content.php)
    'sample' => 'erleben',
];
