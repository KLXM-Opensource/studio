<?php
/*
 * Design-Tokens des Themes „essenz“ für den Style-Editor (Verwaltung → Design) – siehe Core\Design.
 *
 * Jeder Token wird eine CSS-Variable (--e-…) oder eine Klasse am <html> (hdr-bar, btn-key, depth-relief, mk-number …).
 * Farbprinzip: EIN Signal. „Signal“ ist die Füllfarbe (Tasten, Punkte, Skalen, Regler) und muss als Bedienelement
 * mindestens 3:1 zum Hintergrund haben; „Signal als Schrift“ ist die dunklere (bzw. im Dunkeln hellere) Fassung für
 * Links und Dachzeilen (≥ 4,5:1). Alles andere ist Graphit, Papier und Linie.
 * Voreinstellungen sind mit tools/contrast.php auf WCAG 2.2 AA geprüft (hell und dunkel):
 *   php kits/essenz/tools/contrast.php
 * Standardwerte müssen mit :root in assets/css/_tokens.css übereinstimmen (Voreinstellung „Signalorange“).
 */

// Reihenfolge der Farbwerte in den Voreinstellungen
$colorKeys = ['accent', 'accent_ink', 'on_accent', 'ink', 'text', 'muted', 'background', 'surface', 'panel', 'line', 'dark_section'];

/** Voreinstellung: Farben hell + dunkel (je 11 Werte in obiger Reihenfolge) und übrige Werte */
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
    'font_body' => 'inter', 'font_head' => 'inter', 'font_mono' => 'geist-mono', 'label_font' => 'mono',
    'fs_min' => 16, 'fs_max' => 18, 'ratio' => 1.25, 'heading_weight' => 600, 'heading_tracking' => -2.5,
    'radius' => 12, 'buttons' => 'key', 'depth' => 'relief', 'grid' => 'normal', 'space' => 'normal', 'wrap' => 76,
    'markers' => 'dot', 'header' => 'bar', 'header_sticky' => true, 'footer' => 'index', 'pagebg' => 'plain',
    'motifs' => true, 'motion' => true, 'dark' => true,
];

return [
    'groups' => [
        ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
            $color('accent', 'Signal (Füllfarbe)', '--e-a', '#D85B19', '#F0782F', ['with' => 'background', 'min' => 3],
                'Die eine Signalfarbe: Tasten, Punkte, Skalen, Regler. Als Bedienelement mindestens 3:1 zum Hintergrund.'),
            $color('accent_ink', 'Signal als Schrift', '--e-a-ink', '#A3400A', '#FF9C5C', ['with' => 'background', 'min' => 4.5],
                'Links, Dachzeilen und Nummern – eine lesbare Fassung der Signalfarbe (hell dunkler, dunkel heller).'),
            $color('on_accent', 'Schrift auf Signal', '--e-a-on', '#111111', '#141414', ['with' => 'accent', 'min' => 4.5],
                'Beschriftung der Tasten und des Abschnitts „Signalfarbe“.'),
            $color('ink', 'Überschriften', '--e-ink', '#1B1B19', '#F2F1EE', ['with' => 'background', 'min' => 7]),
            $color('text', 'Fließtext', '--e-text', '#393835', '#D3D1CC', ['with' => 'background', 'min' => 4.5]),
            $color('muted', 'Nebentext', '--e-muted', '#5E5C57', '#A19F99', ['with' => 'surface', 'min' => 4.5],
                'Einleitungen, Beschriftungen, Metadaten – auch auf getönten Flächen lesbar.'),
            $color('background', 'Hintergrund', '--e-bg', '#F2F1ED', '#1A1B1D'),
            $color('surface', 'Getönte Fläche', '--e-surface', '#E7E5E0', '#222326', ['with' => 'text', 'min' => 4.5],
                'Abschnitte „Getönt“ und Vertiefungen (Skalen, Regler-Bahnen).'),
            $color('panel', 'Paneel (Karten)', '--e-panel', '#FBFAF8', '#26272A', ['with' => 'text', 'min' => 4.5],
                'Die „Gehäuse“: Karten, Paneele, Menüs und Formulare.'),
            $color('line', 'Linien', '--e-line', '#D6D3CC', '#35363A', null, 'Trennlinien und Rahmen (dekorativ).'),
            $color('dark_section', 'Nachtpaneel (dunkle Abschnitte)', '--e-dark', '#232427', '#2A2B2F', ['with' => '#F2F1EE', 'min' => 7, 'dark_with' => '#F2F1EE'],
                'Hintergrund der Abschnitte „Dunkel“ (Schrift hell). Im dunklen Farbschema etwas heller als der Hintergrund.'),
        ]],
        ['id' => 'typo', 'label' => 'Typografie', 'tokens' => [
            ['name' => 'font_body', 'label' => 'Schrift Fließtext', 'type' => 'font', 'var' => '--e-font', 'default' => 'inter'],
            ['name' => 'font_head', 'label' => 'Schrift Überschriften', 'type' => 'font', 'var' => '--e-font-head', 'default' => 'inter'],
            ['name' => 'font_mono', 'label' => 'Schrift für Beschriftungen und Zahlen', 'type' => 'font', 'var' => '--e-font-mono', 'default' => 'geist-mono',
                'help' => 'Technische Beschriftungen, Nummern und Kennzahlen – wie die Skala an einem Gerät.'],
            ['name' => 'label_font', 'label' => 'Dachzeilen und Etiketten', 'type' => 'choice', 'var' => '--e-font-label', 'default' => 'mono',
                'options' => ['mono' => 'Beschriftungsschrift (technisch)', 'body' => 'Wie Fließtext', 'head' => 'Wie Überschriften'],
                'values' => ['mono' => 'var(--e-font-mono)', 'body' => 'var(--e-font)', 'head' => 'var(--e-font-head)']],
            ['name' => 'fs_min', 'label' => 'Grundschrift auf kleinen Bildschirmen (px)', 'type' => 'range', 'var' => '--e-fs-min', 'unit' => '',
                'min' => 15, 'max' => 19, 'step' => 0.5, 'default' => 16, 'help' => 'Bezogen auf die Standardgröße des Browsers (16 px); Besucher können weiter zoomen.'],
            ['name' => 'fs_max', 'label' => 'Grundschrift auf großen Bildschirmen (px)', 'type' => 'range', 'var' => '--e-fs-max', 'unit' => '',
                'min' => 15, 'max' => 22, 'step' => 0.5, 'default' => 18],
            ['name' => 'ratio', 'label' => 'Verhältnis der Schriftstufen', 'type' => 'range', 'var' => '--e-ratio', 'unit' => '',
                'min' => 1.125, 'max' => 1.5, 'step' => 0.025, 'default' => 1.25,
                'help' => '1,125 = zurückhaltend · 1,25 = ausgewogen · 1,333 = deutlich · 1,414+ = plakativ. Auf kleinen Bildschirmen automatisch flacher.'],
            ['name' => 'heading_weight', 'label' => 'Stärke der Überschriften', 'type' => 'range', 'var' => '--e-hw', 'unit' => '',
                'min' => 300, 'max' => 800, 'step' => 50, 'default' => 600],
            ['name' => 'heading_tracking', 'label' => 'Laufweite der Überschriften (1/100 em)', 'type' => 'range', 'var' => '--e-track', 'unit' => '',
                'min' => -5, 'max' => 2, 'step' => 0.5, 'default' => -2.5, 'help' => 'Negativ = enger, wie bei großen Grotesk-Überschriften üblich.'],
        ]],
        ['id' => 'form', 'label' => 'Form, Raster & Tiefe', 'tokens' => [
            ['name' => 'radius', 'label' => 'Eckenradius der Gehäuse', 'type' => 'range', 'var' => '--e-radius', 'unit' => 'px', 'min' => 0, 'max' => 24, 'step' => 1, 'default' => 12,
                'help' => 'Weiche Radien wie bei Geräten; 0 = streng rechtwinklig.'],
            ['name' => 'buttons', 'label' => 'Buttons', 'type' => 'choice', 'class' => 'btn-{value}', 'default' => 'key', 'preview' => 'radius',
                'options' => ['key' => 'Taste (gefüllt, mit Druckpunkt)', 'pill' => 'Pille (rund)', 'outline' => 'Kontur'],
                'values' => ['key' => '8px', 'pill' => '999px', 'outline' => '8px']],
            ['name' => 'depth', 'label' => 'Tiefe', 'type' => 'choice', 'class' => 'depth-{value}', 'default' => 'relief',
                'options' => ['flat' => 'Flach (nur Linien)', 'relief' => 'Relief (feine Kante, weicher Schatten)', 'deep' => 'Tief (deutlicher Schatten)'],
                'help' => 'Wie plastisch Karten, Paneele und Tasten wirken.'],
            ['name' => 'grid', 'label' => 'Rasterdichte (8-Punkt-Raster)', 'type' => 'choice', 'var' => '--e-u', 'default' => 'normal',
                'options' => ['compact' => 'Kompakt (7 px)', 'normal' => 'Normal (8 px)', 'wide' => 'Weit (9 px)'],
                'values' => ['compact' => '.4375rem', 'normal' => '.5rem', 'wide' => '.5625rem'],
                'help' => 'Alle Innen- und Zwischenabstände sind Vielfache dieser Einheit.'],
            ['name' => 'space', 'label' => 'Abstand zwischen Abschnitten', 'type' => 'choice', 'var' => '--e-space', 'default' => 'normal',
                'options' => ['compact' => 'Kompakt', 'normal' => 'Normal', 'airy' => 'Großzügig'], 'values' => ['compact' => '.75', 'normal' => '1', 'airy' => '1.3']],
            ['name' => 'wrap', 'label' => 'Maximale Inhaltsbreite (rem)', 'type' => 'range', 'var' => '--e-wrap', 'unit' => 'rem', 'min' => 60, 'max' => 96, 'step' => 2, 'default' => 76,
                'help' => '1 rem = 16 px.'],
            ['name' => 'markers', 'label' => 'Abschnittsmarken (vor Dachzeilen)', 'type' => 'choice', 'class' => 'mk-{value}', 'default' => 'dot',
                'options' => ['dot' => 'Signalpunkt', 'number' => 'Laufende Nummer (01, 02 …)', 'plain' => 'Ohne']],
            ['name' => 'motifs', 'label' => 'Bedienelement-Details (Skalen, Punktraster, Regler)', 'type' => 'bool', 'class' => 'has-motifs', 'default' => true,
                'help' => 'Sparsam eingesetzte Details wie Skalenstriche an Kennzahlen oder ein Punktraster im Einstieg. Aus = noch reduzierter.'],
        ]],
        ['id' => 'navigation', 'label' => 'Kopf & Fuß', 'tokens' => [
            ['name' => 'header', 'label' => 'Kopfbereich', 'type' => 'choice', 'class' => 'hdr-{value}', 'default' => 'bar', 'preview' => 'nav',
                'thumbs' => ['bar' => 'left', 'centered' => 'center', 'split' => 'split', 'index' => 'burger'],
                'options' => [
                    'bar' => 'Leiste – Marke links, Menü rechts',
                    'centered' => 'Zentriert – Marke über dem Menü',
                    'split' => 'Geteilt – Menü links, Marke in der Mitte',
                    'index' => 'Minimal – nur Marke und „Index“-Schaltfläche',
                ],
                'help' => 'Das Menü steht in der Leiste, solange es hineinpasst – sonst öffnet eine Schaltfläche das Menü als Seitenblatt.'],
            ['name' => 'header_sticky', 'label' => 'Kopfbereich beim Scrollen sichtbar halten', 'type' => 'bool', 'class' => 'hdr-sticky', 'default' => true],
            ['name' => 'footer', 'label' => 'Fußbereich', 'type' => 'choice', 'class' => 'ft-{value}', 'default' => 'index',
                'options' => ['index' => 'Index (nummerierte Seiten, Kontakt, Zeiten)', 'panel' => 'Paneel (Satz, Button, Index)', 'simple' => 'Schlicht in einer Zeile']],
            ['name' => 'pagebg', 'label' => 'Seitenhintergrund', 'type' => 'choice', 'class' => 'pagebg-{value}', 'default' => 'plain',
                'options' => ['plain' => 'Einfarbig', 'dots' => 'Feines Punktraster', 'lines' => 'Feine Rasterlinien']],
        ]],
        // Kopfbereich-Aktionen (Core\HeaderActions) – Standard des Kits: Kontakt-Menü (Symbol + Geräte-Paneel) + Telefon-Chip mit Statuspunkt (Rams-Anzeige)
        \Core\HeaderActions::designGroup(['ha_cta_style' => 'menu', 'ha_menu_icon' => 'address-book', 'ha_search' => 'popover', 'ha_contact' => 'phone']),
        ['id' => 'modus', 'label' => 'Bewegung & Farbschema', 'tokens' => [
            ['name' => 'motion', 'label' => 'Dezente Bewegung (Einblenden, Skalen füllen sich)', 'type' => 'bool', 'class' => 'has-motion', 'default' => true,
                'help' => 'Besucher mit „Bewegung reduzieren“ sehen nie Animationen – unabhängig von dieser Einstellung.'],
            ['name' => 'dark', 'label' => 'Nachtpaneel (dunkles Farbschema), wenn im Gerät der Besucher eingestellt', 'type' => 'bool', 'class' => 'has-dark', 'default' => true],
        ]],
    ],

    // Selbst gehostete Schriften (kits/essenz/build.mjs erzeugt css/font-*.css) – variable Schriften: ein Download für alle Stärken
    'fonts' => [
        'inter' => ['label' => 'Inter (Grotesk, neutral · variabel)', 'stack' => 'Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'css' => 'css/font-inter.css'],
        'inter-tight' => ['label' => 'Inter Tight (Grotesk, eng – für Überschriften · variabel)', 'stack' => '"Inter Tight",Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'css' => 'css/font-inter-tight.css'],
        'manrope' => ['label' => 'Manrope (Grotesk, geometrisch-warm · variabel)', 'stack' => 'Manrope,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'css' => 'css/font-manrope.css'],
        'geist' => ['label' => 'Geist (Grotesk, sachlich-technisch · variabel)', 'stack' => 'Geist,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'css' => 'css/font-geist.css'],
        'geist-mono' => ['label' => 'Geist Mono (Beschriftung, Monospace · variabel)', 'stack' => '"Geist Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace', 'css' => 'css/font-geist-mono.css'],
        'system' => ['label' => 'Systemschrift (ohne Download)', 'stack' => 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'],
        'system-mono' => ['label' => 'System-Monospace (ohne Download)', 'stack' => 'ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace'],
    ],

    // Sechs Vorlagen – alle hell und dunkel AA-geprüft (tools/contrast.php)
    'presets' => [
        'standard' => $preset('Signalorange (Standard)', 'Warmes Hellgrau, Graphit und ein orangefarbenes Signal – Relief, Tasten, Signalpunkte.',
            ['#D85B19', '#A3400A', '#111111', '#1B1B19', '#393835', '#5E5C57', '#F2F1ED', '#E7E5E0', '#FBFAF8', '#D6D3CC', '#232427'],
            ['#F0782F', '#FF9C5C', '#141414', '#F2F1EE', '#D3D1CC', '#A19F99', '#1A1B1D', '#222326', '#26272A', '#35363A', '#2A2B2F'],
            $base),
        'graphit' => $preset('Graphit', 'Kühles Grau und Anthrazit, Ocker als einziges Signal; Inter Tight, laufende Nummern, feines Linienraster.',
            ['#9A6300', '#7A4E00', '#FFFFFF', '#161718', '#34363A', '#595C61', '#EDEEEF', '#E0E2E4', '#F8F9F9', '#CDD0D4', '#1D1F22'],
            ['#E0A53A', '#EBB85A', '#161616', '#F1F2F3', '#D0D3D7', '#9EA2A8', '#17181A', '#1F2124', '#232528', '#34373B', '#27292D'],
            ['font_head' => 'inter-tight', 'heading_weight' => 650, 'heading_tracking' => -1.5, 'ratio' => 1.3, 'radius' => 8, 'markers' => 'number', 'pagebg' => 'lines', 'depth' => 'flat'] + $base),
        'gruen' => $preset('Signalgrün', 'Helles Papiergrau mit grünem Signal wie eine Einschalttaste; Manrope, runde Tasten, Punktraster.',
            ['#237F49', '#1D6B3E', '#FFFFFF', '#18201B', '#353C37', '#59605B', '#F1F2EE', '#E4E7E1', '#FAFBF8', '#D2D7CF', '#1F2621'],
            ['#46B873', '#6CCB91', '#0B1A11', '#EEF2EE', '#CFD6D0', '#9CA69E', '#181B19', '#1F2320', '#232824', '#333A35', '#28302B'],
            ['font_body' => 'manrope', 'font_head' => 'manrope', 'heading_weight' => 700, 'heading_tracking' => -2, 'radius' => 16, 'buttons' => 'pill', 'pagebg' => 'dots', 'header' => 'centered'] + $base),
        'blau' => $preset('Signalblau', 'Kühles Weißgrau mit blauem Signal und weißer Tastenschrift; Geist, geteilter Kopf, Paneel-Fuß.',
            ['#2563C9', '#1E54AD', '#FFFFFF', '#15181D', '#343941', '#586070', '#F1F3F5', '#E3E7EC', '#FBFCFD', '#D1D7DF', '#15181E'],
            ['#5B93F0', '#8CB4F7', '#0B1428', '#EEF2F7', '#CDD4DE', '#9AA4B2', '#16191E', '#1D2127', '#22262D', '#323843', '#262B34'],
            ['font_body' => 'geist', 'font_head' => 'geist', 'heading_weight' => 600, 'heading_tracking' => -3, 'radius' => 10, 'header' => 'split', 'footer' => 'panel'] + $base),
        'nacht' => $preset('Nachtpaneel', 'Von Anfang an dunkel: Anthrazit mit orangefarbenem Signal, tiefe Paneele, Minimal-Kopf mit Index.',
            ['#F0782F', '#FF9C5C', '#141414', '#F2F1EE', '#D3D1CC', '#A3A19B', '#1A1B1D', '#222326', '#26272A', '#38393D', '#2C2D31'],
            ['#F0782F', '#FF9C5C', '#141414', '#F2F1EE', '#D3D1CC', '#A3A19B', '#141517', '#1C1D20', '#212225', '#333438', '#27282C'],
            ['font_head' => 'inter-tight', 'heading_tracking' => -1.5, 'depth' => 'deep', 'header' => 'index', 'footer' => 'panel', 'markers' => 'number', 'pagebg' => 'dots'] + $base),
        'papier' => $preset('Papier', 'So wenig Gestaltung wie möglich: Weiß, Schwarz, ein rotes Signal; flach, eckig, ohne Details.',
            ['#C4261B', '#B42318', '#FFFFFF', '#111111', '#2E2E2E', '#5C5C5C', '#FFFFFF', '#F2F2F0', '#FFFFFF', '#DCDCD8', '#161616'],
            ['#F0625A', '#FF8A82', '#1A0503', '#F4F4F2', '#D6D6D3', '#A3A3A0', '#121212', '#1B1B1B', '#1E1E1E', '#333333', '#242424'],
            ['font_body' => 'geist', 'font_head' => 'geist', 'heading_weight' => 500, 'heading_tracking' => -3.5, 'ratio' => 1.333, 'radius' => 2,
             'buttons' => 'outline', 'depth' => 'flat', 'markers' => 'plain', 'motifs' => false, 'footer' => 'simple', 'space' => 'airy'] + $base),
    ],

    // Dunkle Werte bei „Nachtpaneel“ (Klasse has-dark) und Geräte-Einstellung; Vorschau im Editor setzt is-dark
    'dark' => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark', 'force' => 'is-dark'],

    // Musterseite für die Vorschau im Editor (siehe tools/demo.php)
    'sample' => 'werkstatt',
];
