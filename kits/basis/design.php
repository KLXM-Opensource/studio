<?php
/*
 * Design-Tokens des Themes „basis“ für den Style-Editor (Verwaltung → Design) – siehe Core\Design.
 *
 * Jeder Token landet als CSS-Variable (--b-…) oder Klasse am <html> (nav-modern, btn-pill, cards-elevated, has-dark …).
 * Das Theme-CSS nutzt ausschließlich diese Variablen; hell und dunkel haben je eigene Farbwerte.
 * Voreinstellungen (presets) sind mit tools/contrast.php auf WCAG 2.2 AA geprüft (hell und dunkel):
 *   php kits/basis/tools/contrast.php
 */

// Reihenfolge der Farbwerte in den Voreinstellungen
$colorKeys = ['accent', 'accent_strong', 'on_accent', 'ink', 'text', 'muted', 'background', 'surface', 'line', 'dark_section'];

/** Voreinstellung: Farben hell + dunkel (je 10 Werte in obiger Reihenfolge) und übrige Werte */
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

return [
    'groups' => [
        ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
            $color('accent', 'Akzent', '--b-a', '#0F6E68', '#5DCABE', ['with' => 'background', 'min' => 4.5],
                'Buttons, Links, Symbole und Abschnitte „Akzentfarbe“. Muss auf dem Hintergrund als Linkfarbe lesbar sein.'),
            $color('accent_strong', 'Akzent kräftig', '--b-a-strong', '#0A5752', '#8ADBD2', ['with' => 'background', 'min' => 4.5],
                'Hover-Zustand von Buttons; hell: etwas dunkler als der Akzent, dunkel: etwas heller.'),
            $color('on_accent', 'Schrift auf Akzent', '--b-a-on', '#FFFFFF', '#031F1C', ['with' => 'accent', 'min' => 4.5],
                'Text auf Buttons und im Abschnitt „Akzentfarbe“.'),
            $color('ink', 'Überschriften', '--b-ink', '#0F1419', '#F2F4F6', ['with' => 'background', 'min' => 7]),
            $color('text', 'Fließtext', '--b-text', '#2A3037', '#D3D9DF', ['with' => 'background', 'min' => 4.5]),
            $color('muted', 'Nebentext', '--b-muted', '#59616B', '#9BA4AE', ['with' => 'surface', 'min' => 4.5],
                'Einleitungen, Bildunterschriften, Metadaten – auch auf getönten Flächen lesbar.'),
            $color('background', 'Hintergrund', '--b-bg', '#FFFFFF', '#0E1115'),
            $color('surface', 'Getönte Fläche', '--b-surface', '#F5F6F7', '#151A20', ['with' => 'text', 'min' => 4.5],
                'Abschnitte „Getönt“, Fußbereich, Karten im Stil „Fläche“.'),
            $color('line', 'Linien', '--b-line', '#E3E6EA', '#262E36', null, 'Trennlinien und Rahmen (dekorativ).'),
            $color('dark_section', 'Dunkle Abschnitte', '--b-dark-sec', '#12161B', '#060809', ['with' => '#FFFFFF', 'min' => 7, 'dark_with' => 'text'],
                'Hintergrund der Abschnitte „Dunkel“ (Schrift hell).'),
        ]],
        ['id' => 'typo', 'label' => 'Typografie', 'tokens' => [
            ['name' => 'font_body', 'label' => 'Schrift Fließtext', 'type' => 'font', 'var' => '--b-font', 'default' => 'inter'],
            ['name' => 'font_head', 'label' => 'Schrift Überschriften', 'type' => 'font', 'var' => '--b-font-head', 'default' => 'inter'],
            ['name' => 'base_size', 'label' => 'Grundschriftgröße Fließtext (px)', 'type' => 'range', 'var' => '--b-fs', 'unit' => '',
                'min' => 15, 'max' => 19, 'step' => 0.5, 'default' => 17, 'help' => 'Bezogen auf die Standardgröße des Browsers (16 px); Besucher können weiter zoomen.'],
            ['name' => 'heading_scale', 'label' => 'Größe der Überschriften', 'type' => 'choice', 'var' => '--b-hs', 'default' => 'normal',
                'options' => ['small' => 'Zurückhaltend', 'normal' => 'Ausgewogen', 'large' => 'Groß'],
                'values' => ['small' => '.88', 'normal' => '1', 'large' => '1.12']],
            ['name' => 'heading_weight', 'label' => 'Stärke der Überschriften', 'type' => 'choice', 'var' => '--b-hw', 'default' => '600',
                'options' => ['400' => 'Normal', '600' => 'Halbfett', '700' => 'Fett'],
                'values' => ['400' => '400', '600' => '600', '700' => '700']],
            ['name' => 'heading_tracking', 'label' => 'Laufweite der Überschriften', 'type' => 'choice', 'var' => '--b-track', 'default' => 'tight',
                'options' => ['tight' => 'Eng (Grotesk)', 'normal' => 'Normal (Serifen)'],
                'values' => ['tight' => '1', 'normal' => '.3']],
        ]],
        ['id' => 'form', 'label' => 'Form & Abstände', 'tokens' => [
            ['name' => 'radius', 'label' => 'Eckenradius', 'type' => 'range', 'var' => '--b-radius', 'unit' => 'px', 'min' => 0, 'max' => 20, 'step' => 1, 'default' => 6],
            ['name' => 'buttons', 'label' => 'Buttons', 'type' => 'choice', 'class' => 'btn-{value}', 'default' => 'solid', 'preview' => 'radius',
                'options' => ['solid' => 'Gefüllt', 'outline' => 'Kontur', 'pill' => 'Gefüllt, rund (Pille)'],
                'values' => ['solid' => '6px', 'outline' => '6px', 'pill' => '999px']],
            ['name' => 'cards', 'label' => 'Karten', 'type' => 'choice', 'class' => 'cards-{value}', 'default' => 'outlined',
                'options' => ['flat' => 'Fläche (ohne Rahmen)', 'outlined' => 'Mit Rahmen', 'elevated' => 'Mit Schatten']],
            ['name' => 'spacing', 'label' => 'Abstand zwischen Abschnitten', 'type' => 'choice', 'var' => '--b-sec-y', 'default' => 'normal',
                'options' => ['compact' => 'Kompakt', 'normal' => 'Normal', 'airy' => 'Großzügig'],
                'values' => ['compact' => 'clamp(48px,6.5vw,88px)', 'normal' => 'clamp(64px,9vw,128px)', 'airy' => 'clamp(80px,11.5vw,168px)']],
        ]],
        ['id' => 'navigation', 'label' => 'Navigation', 'tokens' => [
            ['name' => 'nav', 'label' => 'Navigation', 'type' => 'choice', 'class' => 'nav-{value}', 'default' => 'classic', 'preview' => 'nav',
                'thumbs' => ['modern' => 'floating', 'classic' => 'left', 'minimal' => 'burger', 'extended' => 'split'],
                'options' => [
                    'modern' => 'Modern – schwebende Leiste',
                    'classic' => 'Klassisch – Logo links, Menü rechts',
                    'minimal' => 'Minimal – nur Menü-Schaltfläche',
                    'extended' => 'Ausführlich – Mega-Menü mit Beschreibungen',
                ],
                'help' => 'Alle Varianten sind per Tastatur bedienbar und haben ein eigenes Mobilmenü.'],
            ['name' => 'nav_sticky', 'label' => 'Kopfbereich beim Scrollen sichtbar halten', 'type' => 'bool', 'class' => 'nav-sticky', 'default' => true],
            ['name' => 'nav_transparent', 'label' => 'Transparent über dem ersten Abschnitt (nur „Modern“)', 'type' => 'bool', 'class' => 'nav-over', 'default' => false,
                'help' => 'Die Leiste liegt über dem Einstieg und wird beim Scrollen deckend.'],
            ['name' => 'topbar', 'label' => 'Infoleiste oben (Telefon, E-Mail, „jetzt geöffnet“, Sprache)', 'type' => 'bool', 'class' => 'has-topbar', 'default' => false,
                'help' => 'Bei „Klassisch“ und „Ausführlich“.'],
            ['name' => 'footer', 'label' => 'Fußbereich', 'type' => 'choice', 'class' => 'footer-{value}', 'default' => 'columns',
                'options' => ['columns' => 'Ausführlich mit Spalten', 'simple' => 'Schlicht in einer Zeile']],
        ]],
        // Kopfbereich-Aktionen (Core\HeaderActions): Standard des Kits = abgesetzter Menüpunkt „Kontakt“ + Lupe
        \Core\HeaderActions::designGroup(['ha_cta_style' => 'navitem', 'ha_cta' => 'contact', 'ha_search' => 'popover']),
        ['id' => 'modus', 'label' => 'Farbschema', 'tokens' => [
            ['name' => 'dark', 'label' => 'Dunkles Farbschema, wenn im Gerät der Besucher eingestellt', 'type' => 'bool', 'class' => 'has-dark', 'default' => true],
        ]],
    ],

    // Selbst gehostete Schriften (kits/basis/build.mjs erzeugt css/font-*.css); 400/600/700 + latin-ext
    'fonts' => [
        'inter' => ['label' => 'Inter (Grotesk, neutral)', 'stack' => 'Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'css' => 'css/font-inter.css'],
        'manrope' => ['label' => 'Manrope (geometrisch, modern)', 'stack' => 'Manrope,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'css' => 'css/font-manrope.css'],
        'plex' => ['label' => 'IBM Plex Sans (technisch)', 'stack' => '"IBM Plex Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'css' => 'css/font-plex.css'],
        'source-serif' => ['label' => 'Source Serif 4 (Serifen, sachlich)', 'stack' => '"Source Serif 4",ui-serif,Georgia,Cambria,serif', 'css' => 'css/font-source-serif.css'],
        'lora' => ['label' => 'Lora (Serifen, warm)', 'stack' => 'Lora,ui-serif,Georgia,Cambria,serif', 'css' => 'css/font-lora.css'],
        'fraunces' => ['label' => 'Fraunces (Serifen, markant)', 'stack' => 'Fraunces,ui-serif,Georgia,Cambria,serif', 'css' => 'css/font-fraunces.css'],
        'system' => ['label' => 'Systemschrift (ohne Download)', 'stack' => 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'],
    ],

    'presets' => [
        'petrol' => $preset('Petrol Business', 'Ruhig und sachlich – der Standard des Kits.',
            ['#0F6E68', '#0A5752', '#FFFFFF', '#0F1419', '#2A3037', '#59616B', '#FFFFFF', '#F5F6F7', '#E3E6EA', '#12161B'],
            ['#5DCABE', '#8ADBD2', '#031F1C', '#F2F4F6', '#D3D9DF', '#9BA4AE', '#0E1115', '#151A20', '#262E36', '#060809'],
            ['font_body' => 'inter', 'font_head' => 'inter', 'base_size' => 17, 'heading_scale' => 'normal', 'heading_weight' => '600', 'heading_tracking' => 'tight',
             'radius' => 6, 'buttons' => 'solid', 'cards' => 'outlined', 'spacing' => 'normal',
             'nav' => 'classic', 'nav_sticky' => true, 'nav_transparent' => false, 'topbar' => false, 'footer' => 'columns', 'dark' => true,
             'ha_cta_style' => 'navitem', 'ha_cta' => 'contact', 'ha_search' => 'popover', 'ha_layout' => 'right', 'ha_contact' => 'none']),
        'nachtblau' => $preset('Nachtblau Kanzlei', 'Seriös mit Serifen-Überschriften, Infoleiste und Spalten-Fußbereich.',
            ['#1E3A8A', '#172E6E', '#FFFFFF', '#0B1220', '#283143', '#566173', '#FFFFFF', '#F4F6FA', '#E1E6EF', '#0C1836'],
            ['#93B4FF', '#B9CEFF', '#0A1633', '#EEF2FA', '#CDD5E3', '#98A4B8', '#0B101C', '#121A2A', '#243049', '#05080F'],
            ['font_body' => 'inter', 'font_head' => 'source-serif', 'base_size' => 17, 'heading_scale' => 'normal', 'heading_weight' => '600', 'heading_tracking' => 'normal',
             'radius' => 2, 'buttons' => 'solid', 'cards' => 'outlined', 'spacing' => 'normal',
             'nav' => 'classic', 'nav_sticky' => true, 'nav_transparent' => false, 'topbar' => true, 'footer' => 'columns', 'dark' => true,
             'ha_cta_style' => 'outline', 'ha_search' => 'command', 'ha_layout' => 'right', 'ha_contact' => 'none']),
        'handwerk' => $preset('Warm Handwerk', 'Terrakotta, warme Flächen und markante Serifen – für Betriebe und Manufakturen.',
            ['#A63D15', '#85300F', '#FFFFFF', '#21170F', '#3B2F26', '#665648', '#FFFCF7', '#F6EEE3', '#E9DCCB', '#2A1D14'],
            ['#F2A27C', '#F7C1A5', '#2A1206', '#F7F0E9', '#E2D5C8', '#B0A08F', '#16110D', '#201812', '#382C22', '#0C0806'],
            ['font_body' => 'inter', 'font_head' => 'fraunces', 'base_size' => 17.5, 'heading_scale' => 'large', 'heading_weight' => '600', 'heading_tracking' => 'normal',
             'radius' => 10, 'buttons' => 'pill', 'cards' => 'flat', 'spacing' => 'normal',
             'nav' => 'modern', 'nav_sticky' => true, 'nav_transparent' => false, 'topbar' => false, 'footer' => 'columns', 'dark' => true,
             'ha_cta_style' => 'menu', 'ha_search' => 'popover', 'ha_layout' => 'right', 'ha_contact' => 'none']),
        'mint' => $preset('Mint Tech', 'Frisch und technisch: runde Formen, Schatten, schwebende Navigation.',
            ['#047857', '#065F46', '#FFFFFF', '#0B1512', '#25302C', '#52605A', '#FFFFFF', '#F1F7F4', '#DCE8E2', '#0A1F19'],
            ['#34D399', '#6EE7B7', '#03281C', '#ECFDF5', '#C9DBD3', '#90A79D', '#07110E', '#0E1B17', '#1E322B', '#030806'],
            ['font_body' => 'manrope', 'font_head' => 'manrope', 'base_size' => 17, 'heading_scale' => 'large', 'heading_weight' => '700', 'heading_tracking' => 'tight',
             'radius' => 14, 'buttons' => 'pill', 'cards' => 'elevated', 'spacing' => 'airy',
             'nav' => 'modern', 'nav_sticky' => true, 'nav_transparent' => true, 'topbar' => false, 'footer' => 'simple', 'dark' => true,
             'ha_cta_style' => 'icon', 'ha_search' => 'expand', 'ha_layout' => 'right', 'ha_contact' => 'none']),
        'graphit' => $preset('Graphit Minimal', 'Schwarz-Weiß, eckig, viel Weißraum – mit Vollbild-Menü.',
            ['#23272E', '#0B0D10', '#FFFFFF', '#0B0D10', '#2E3339', '#5C636B', '#FFFFFF', '#F5F5F5', '#E4E4E4', '#141619'],
            ['#E6E8EB', '#FFFFFF', '#101215', '#F4F5F6', '#D0D3D7', '#9DA2A9', '#0E0F11', '#17181B', '#2A2C30', '#070809'],
            ['font_body' => 'inter', 'font_head' => 'inter', 'base_size' => 17, 'heading_scale' => 'large', 'heading_weight' => '400', 'heading_tracking' => 'tight',
             'radius' => 0, 'buttons' => 'outline', 'cards' => 'flat', 'spacing' => 'airy',
             'nav' => 'minimal', 'nav_sticky' => true, 'nav_transparent' => false, 'topbar' => false, 'footer' => 'simple', 'dark' => true,
             'ha_cta_style' => 'link', 'ha_search' => 'popover', 'ha_layout' => 'right', 'ha_contact' => 'none']),
        'bordeaux' => $preset('Bordeaux Klassisch', 'Klassische Serifen, Mega-Menü mit Beschreibungen und Infoleiste.',
            ['#8E1B3A', '#6F142D', '#FFFFFF', '#1C1215', '#3A2E31', '#665A5E', '#FFFFFF', '#FAF5F3', '#ECE1DE', '#2A0F17'],
            ['#F29BB0', '#F7BDCB', '#2E0612', '#F8EEF0', '#E0CFD3', '#AF9BA1', '#140C0E', '#1E1417', '#36262B', '#0A0406'],
            ['font_body' => 'source-serif', 'font_head' => 'lora', 'base_size' => 18, 'heading_scale' => 'normal', 'heading_weight' => '600', 'heading_tracking' => 'normal',
             'radius' => 4, 'buttons' => 'solid', 'cards' => 'outlined', 'spacing' => 'normal',
             'nav' => 'extended', 'nav_sticky' => true, 'nav_transparent' => false, 'topbar' => true, 'footer' => 'columns', 'dark' => true,
             'ha_cta_style' => 'solid', 'ha_search' => 'bar', 'ha_layout' => 'right', 'ha_contact' => 'none']),
        'violett' => $preset('Violett Agentur', 'Kräftiger Akzent, technische Schrift, schwebende Leiste mit Schatten-Karten.',
            ['#6D28D9', '#5B21B6', '#FFFFFF', '#140F1F', '#2F2A3A', '#5D5769', '#FFFFFF', '#F6F4FB', '#E6E1F0', '#1A1033'],
            ['#C4B5FD', '#DDD6FE', '#1E0B4B', '#F3F0FA', '#D6D0E3', '#A39CB3', '#0F0C16', '#17131F', '#2B2538', '#07050C'],
            ['font_body' => 'plex', 'font_head' => 'plex', 'base_size' => 17, 'heading_scale' => 'large', 'heading_weight' => '600', 'heading_tracking' => 'tight',
             'radius' => 12, 'buttons' => 'pill', 'cards' => 'elevated', 'spacing' => 'normal',
             'nav' => 'modern', 'nav_sticky' => true, 'nav_transparent' => false, 'topbar' => false, 'footer' => 'simple', 'dark' => true,
             'ha_cta_style' => 'solid', 'ha_search' => 'inline', 'ha_layout' => 'right', 'ha_contact' => 'none']),
    ],

    // Dunkle Werte gelten bei „Dunkles Farbschema“ (Klasse has-dark) und Geräte-Einstellung; die Vorschau im Editor setzt is-dark
    'dark' => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark', 'force' => 'is-dark'],

    // Musterseite für die Vorschau im Editor (Musterseiten, siehe tools/demo.php); 'baukasten': frühere Installationen
    'sample' => ['musterseiten', 'baukasten'],
];
