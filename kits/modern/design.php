<?php
/*
 * Design-Tokens des Kits „modern“ für den Style-Editor (Verwaltung → Design) – siehe Core\Design.
 *
 * Jeder Token wird eine CSS-Variable (--m-…) oder eine Klasse am <html> (nav-modern, btn-outline, cards-flat, eb-index, has-dark …).
 * Typografie in festen Stufen je Bildschirmbreite (Media-Queries bei 48 em und 64 em): Grundgröße × Verhältnis^n;
 * kleine Bildschirme nutzen automatisch ein flacheres Verhältnis. Bedienelemente haben immer 8 px Radius (keine Pillen).
 * Voreinstellungen sind mit tools/contrast.php auf WCAG 2.2 AA geprüft (hell und dunkel):
 *   php kits/modern/tools/contrast.php
 * Standardwerte müssen mit :root in assets/css/_tokens.css übereinstimmen (Voreinstellung „Nordlicht“).
 */

// Reihenfolge der Farbwerte in den Voreinstellungen
$colorKeys = ['accent', 'accent_strong', 'on_accent', 'pop', 'ink', 'text', 'muted', 'background', 'surface', 'line', 'dark_section'];

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
    'font_body' => 'jakarta', 'font_head' => 'space-grotesk', 'base_size' => 17, 'ratio' => 1.3, 'heading_weight' => 600, 'heading_tracking' => -3.5,
    'display' => 'large', 'radius' => 14, 'buttons' => 'solid', 'cards' => 'outlined', 'space' => 'normal', 'wrap' => 80, 'eyebrow' => 'square',
    'nav' => 'modern', 'nav_sticky' => true, 'footer' => 'big', 'motion' => true, 'dark' => true,
];

return [
    'groups' => [
        ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
            $color('accent', 'Akzent', '--m-a', '#086B4B', '#4FD69C', ['with' => 'background', 'min' => 4.5],
                'Buttons, Links, Symbole und Abschnitte „Akzentfarbe“. Muss auf dem Hintergrund als Linkfarbe lesbar sein.'),
            $color('accent_strong', 'Akzent kräftig', '--m-a-strong', '#05533A', '#8AE6BD', ['with' => 'background', 'min' => 4.5],
                'Hover-Zustand; hell etwas dunkler als der Akzent, dunkel etwas heller.'),
            $color('on_accent', 'Schrift auf Akzent', '--m-a-on', '#FFFFFF', '#03281A', ['with' => 'accent', 'min' => 4.5],
                'Text auf Buttons und im Abschnitt „Akzentfarbe“.'),
            $color('pop', 'Blockfarbe (Flächen)', '--m-a2', '#C9F25E', '#1E3B2C', ['with' => 'ink', 'min' => 4.5],
                'Kräftige Farbfläche für Bento-Kacheln, Hinweisbalken und Markierungen – Überschriftenfarbe muss darauf lesbar sein.'),
            $color('ink', 'Überschriften', '--m-ink', '#0A0D12', '#F2F4F7', ['with' => 'background', 'min' => 7]),
            $color('text', 'Fließtext', '--m-text', '#2A303A', '#CDD3DB', ['with' => 'background', 'min' => 4.5]),
            $color('muted', 'Nebentext', '--m-muted', '#565E6B', '#98A1AD', ['with' => 'surface', 'min' => 4.5],
                'Einleitungen, Bildunterschriften, Metadaten – auch auf getönten Flächen lesbar.'),
            $color('background', 'Hintergrund', '--m-bg', '#FFFFFF', '#090C11'),
            $color('surface', 'Getönte Fläche', '--m-surface', '#F3F4F6', '#121720', ['with' => 'text', 'min' => 4.5],
                'Abschnitte „Getönt“, Fußbereich, Karten im Stil „Fläche“.'),
            $color('line', 'Linien', '--m-line', '#E2E5EA', '#252C37', null, 'Kartenrahmen und Trennlinien (dekorativ).'),
            $color('dark_section', 'Dunkle Abschnitte', '--m-dark', '#0B1320', '#152033', ['with' => '#FFFFFF', 'min' => 7, 'dark_with' => '#FFFFFF'],
                'Hintergrund der Abschnitte „Dunkel“ (Schrift weiß). Im dunklen Farbschema etwas heller als der Hintergrund, damit die Fläche sichtbar bleibt.'),
        ]],
        ['id' => 'typo', 'label' => 'Typografie', 'tokens' => [
            ['name' => 'font_body', 'label' => 'Schrift Fließtext', 'type' => 'font', 'var' => '--m-font', 'default' => 'jakarta'],
            ['name' => 'font_head', 'label' => 'Schrift Überschriften', 'type' => 'font', 'var' => '--m-font-head', 'default' => 'space-grotesk'],
            ['name' => 'base_size', 'label' => 'Grundschrift (px)', 'type' => 'range', 'var' => '--m-fs', 'unit' => '',
                'min' => 15, 'max' => 19, 'step' => 0.5, 'default' => 17, 'help' => 'Bezogen auf die Standardgröße des Browsers (16 px); Besucher können weiter zoomen.'],
            ['name' => 'ratio', 'label' => 'Verhältnis der Schriftstufen', 'type' => 'range', 'var' => '--m-ratio', 'unit' => '',
                'min' => 1.15, 'max' => 1.45, 'step' => 0.01, 'default' => 1.3,
                'help' => '1,2 = ruhig · 1,3 = kräftig (Standard) · 1,4 = plakativ. Auf kleinen Bildschirmen automatisch flacher.'],
            ['name' => 'display', 'label' => 'Größe der Einstiegs-Überschrift', 'type' => 'choice', 'var' => '--m-display', 'default' => 'large',
                'options' => ['normal' => 'Normal', 'large' => 'Groß', 'huge' => 'Sehr groß (Plakat)'], 'values' => ['normal' => '.86', 'large' => '1', 'huge' => '1.18']],
            ['name' => 'heading_weight', 'label' => 'Stärke der Überschriften', 'type' => 'range', 'var' => '--m-hw', 'unit' => '',
                'min' => 400, 'max' => 800, 'step' => 50, 'default' => 600, 'help' => 'Stufenlos bei variablen Schriften.'],
            ['name' => 'heading_tracking', 'label' => 'Laufweite der Überschriften (1/100 em)', 'type' => 'range', 'var' => '--m-track', 'unit' => '',
                'min' => -6, 'max' => 1, 'step' => 0.5, 'default' => -3.5, 'help' => 'Negativ = enger. Große Grotesk-Überschriften wirken mit −3 bis −4 am besten.'],
        ]],
        ['id' => 'form', 'label' => 'Form & Raum', 'tokens' => [
            ['name' => 'radius', 'label' => 'Eckenradius der Karten', 'type' => 'range', 'var' => '--m-radius', 'unit' => 'px', 'min' => 0, 'max' => 24, 'step' => 1, 'default' => 14,
                'help' => 'Buttons, Felder und Menüs haben immer 8 px – klar und ohne Pillenform.'],
            ['name' => 'buttons', 'label' => 'Buttons', 'type' => 'choice', 'class' => 'btn-{value}', 'default' => 'solid', 'preview' => 'radius',
                'options' => ['solid' => 'Gefüllt', 'outline' => 'Kontur', 'arrow' => 'Gefüllt mit Pfeil'],
                'values' => ['solid' => '8px', 'outline' => '8px', 'arrow' => '8px']],
            ['name' => 'cards', 'label' => 'Karten', 'type' => 'choice', 'class' => 'cards-{value}', 'default' => 'outlined',
                'options' => ['outlined' => 'Feine Kante', 'flat' => 'Fläche (ohne Rahmen)', 'elevated' => 'Kante mit Schatten']],
            ['name' => 'space', 'label' => 'Abstand zwischen Abschnitten', 'type' => 'choice', 'var' => '--m-space', 'default' => 'normal',
                'options' => ['compact' => 'Kompakt', 'normal' => 'Großzügig', 'airy' => 'Sehr großzügig'], 'values' => ['compact' => '.72', 'normal' => '1', 'airy' => '1.3']],
            ['name' => 'wrap', 'label' => 'Maximale Inhaltsbreite (rem)', 'type' => 'range', 'var' => '--m-wrap', 'unit' => 'rem', 'min' => 64, 'max' => 96, 'step' => 2, 'default' => 80,
                'help' => '1 rem = 16 px.'],
            ['name' => 'eyebrow', 'label' => 'Dachzeilen', 'type' => 'choice', 'class' => 'eb-{value}', 'default' => 'square',
                'options' => ['square' => 'Mit Farbquadrat', 'index' => 'Nummeriert (01, 02 …)', 'plain' => 'Schlicht']],
        ]],
        ['id' => 'navigation', 'label' => 'Navigation', 'tokens' => [
            ['name' => 'nav', 'label' => 'Navigation', 'type' => 'choice', 'class' => 'nav-{value}', 'default' => 'modern', 'preview' => 'nav',
                'thumbs' => ['modern' => 'floating', 'classic' => 'left', 'minimal' => 'burger', 'extended' => 'split'],
                'options' => [
                    'modern' => 'Modern – schwebende Leiste',
                    'classic' => 'Klassisch – Logo links, Menü rechts',
                    'minimal' => 'Minimal – nur Menü-Schaltfläche',
                    'extended' => 'Ausführlich – mit Kontaktzeile',
                ],
                'help' => 'Alle Varianten sind per Tastatur bedienbar; auf Mobilgeräten öffnet die Menü-Schaltfläche ein Seitenblatt.'],
            ['name' => 'nav_sticky', 'label' => 'Kopfbereich beim Scrollen sichtbar halten', 'type' => 'bool', 'class' => 'nav-sticky', 'default' => true],
            ['name' => 'footer', 'label' => 'Fußbereich', 'type' => 'choice', 'class' => 'ft-{value}', 'default' => 'big',
                'options' => ['big' => 'Großer Schriftzug mit Spalten', 'columns' => 'Spalten', 'simple' => 'Schlicht in einer Zeile']],
        ]],
        // Kopfbereich-Aktionen (Core\HeaderActions) – Standard des Kits: geteilte Aktion (Anfrage + Anruf) + aufziehende Suche
        \Core\HeaderActions::designGroup(['ha_cta_style' => 'split', 'ha_search' => 'expand']),
        ['id' => 'modus', 'label' => 'Bewegung & Farbschema', 'tokens' => [
            ['name' => 'motion', 'label' => 'Dezente Übergänge (Einblenden beim Scrollen, Hover)', 'type' => 'bool', 'class' => 'has-motion', 'default' => true,
                'help' => 'Besucher mit „Bewegung reduzieren“ sehen nie Animationen – unabhängig von dieser Einstellung.'],
            ['name' => 'dark', 'label' => 'Dunkles Farbschema, wenn im Gerät der Besucher eingestellt', 'type' => 'bool', 'class' => 'has-dark', 'default' => true],
        ]],
    ],

    // Schriften: 'fontsource' = installiert der Schriften-Manager (Core\Fonts, fonts:sync; selbst gehostet, ohne externe Anfragen) – variabel: eine Datei für alle Stärken
    'fonts' => [
        'jakarta' => ['label' => 'Plus Jakarta Sans (Grotesk, klar · variabel)', 'stack' => '"Plus Jakarta Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'fontsource' => 'plus-jakarta-sans'],
        'space-grotesk' => ['label' => 'Space Grotesk (markant, technisch · variabel)', 'stack' => '"Space Grotesk",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'space-grotesk'],
        'inter-tight' => ['label' => 'Inter Tight (neutral, kompakt · variabel)', 'stack' => '"Inter Tight",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'fontsource' => 'inter-tight'],
        'manrope' => ['label' => 'Manrope (geometrisch, rund · variabel)', 'stack' => 'Manrope,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'manrope'],
        'system' => ['label' => 'Systemschrift (ohne Download)', 'stack' => 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'],
    ],

    // Vier Vorlagen – alle hell und dunkel AA-geprüft (tools/contrast.php)
    'presets' => [
        'nordlicht' => $preset('Nordlicht', 'Der Standard: Nachtblau, Polarlicht-Grün und eine kräftige Limetten-Fläche. Space Grotesk + Plus Jakarta Sans.',
            ['#086B4B', '#05533A', '#FFFFFF', '#C9F25E', '#0A0D12', '#2A303A', '#565E6B', '#FFFFFF', '#F3F4F6', '#E2E5EA', '#0B1320'],
            ['#4FD69C', '#8AE6BD', '#03281A', '#1E3B2C', '#F2F4F7', '#CDD3DB', '#98A1AD', '#090C11', '#121720', '#252C37', '#152033'],
            $base),
        'kobalt' => $preset('Kobalt', 'Selbstbewusst und technisch: Kobaltblau mit Sonnengelb, Inter Tight, Kontur-Buttons, klassische Leiste.',
            ['#2544D8', '#1B34AE', '#FFFFFF', '#FFD53D', '#0A0C1A', '#282C3C', '#555A6D', '#FFFFFF', '#F2F3F8', '#E0E2EC', '#0D1033'],
            ['#93A6FF', '#B8C5FF', '#0A1240', '#3A3212', '#F2F3FA', '#CFD2E0', '#9BA0B5', '#0A0B14', '#131526', '#262A40', '#1A1E42'],
            ['font_body' => 'inter-tight', 'font_head' => 'inter-tight', 'ratio' => 1.28, 'heading_weight' => 650, 'heading_tracking' => -4,
             'radius' => 10, 'buttons' => 'outline', 'eyebrow' => 'index', 'nav' => 'classic', 'footer' => 'columns'] + $base),
        'koralle' => $preset('Koralle', 'Warm und nahbar: Korallenrot auf Creme, Manrope, weiche Karten mit Schatten, Kontaktzeile im Kopf.',
            ['#B8360F', '#962C0C', '#FFFFFF', '#FFD3C2', '#1A0F0B', '#3A2C26', '#6A5750', '#FFFCFA', '#F7F0EC', '#EADFD8', '#221410'],
            ['#FF9B78', '#FFBDA5', '#2E0E03', '#4A2418', '#FAF2EE', '#E4D6CF', '#B3A197', '#120C0A', '#1C1411', '#36271F', '#2A1B15'],
            ['font_body' => 'manrope', 'font_head' => 'manrope', 'ratio' => 1.26, 'heading_weight' => 700, 'heading_tracking' => -3,
             'radius' => 20, 'buttons' => 'arrow', 'cards' => 'elevated', 'space' => 'airy', 'nav' => 'extended', 'footer' => 'big'] + $base),
        'graphit' => $preset('Graphit', 'Reduziert und plakativ: Schwarz-Weiß mit hellgrauen Flächen, sehr große Überschriften, nur Menü-Schaltfläche.',
            ['#18181B', '#000000', '#FFFFFF', '#E4E4DF', '#09090B', '#27272A', '#5B5B63', '#FFFFFF', '#F4F4F2', '#E4E4E0', '#111113'],
            ['#F4F4F5', '#FFFFFF', '#111113', '#2C2C30', '#FAFAFA', '#D4D4D8', '#A1A1AA', '#0B0B0C', '#151517', '#2A2A2E', '#1C1C1F'],
            ['font_body' => 'inter-tight', 'font_head' => 'space-grotesk', 'ratio' => 1.38, 'display' => 'huge', 'heading_weight' => 500, 'heading_tracking' => -4.5,
             'radius' => 4, 'buttons' => 'solid', 'cards' => 'flat', 'eyebrow' => 'plain', 'nav' => 'minimal', 'footer' => 'simple'] + $base),
    ],

    // Dunkle Werte bei „Dunkles Farbschema“ (Klasse has-dark) und Geräte-Einstellung; Vorschau im Editor setzt is-dark
    'dark' => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark', 'force' => 'is-dark'],

    // Musterseite für die Vorschau im Editor (Musterseiten mit allen Blöcken, tools/demo-content.php); 'baukasten': frühere Installationen
    'sample' => ['musterseiten', 'baukasten'],
];
