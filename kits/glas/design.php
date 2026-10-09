<?php
/*
 * Design-Tokens des Kits „glas“ für den Style-Editor (Verwaltung → Design) – siehe Core\Design.
 *
 * Jeder Token wird eine CSS-Variable (--g-…) oder eine Klasse am <html> (hdr-bar, gd-standard, ff-soft, has-grain …).
 * Farbprinzip: Mattglas über einem Farbfeld. Drei Feldfarben (field_1–3) bilden das weiche Farbfeld hinter der Seite
 * und die „Aurora“ im Einstieg; Glasflächen sind die Tönung „glass“ mit einer Deckkraft je Farbschema (Glasdichte).
 * Lesbarkeit zuerst: Schrift steht entweder auf Glas oder auf dem abgeschwächten Farbfeld – beides ist für jede Vorlage,
 * jede Glasdichte, hell und dunkel mit tools/contrast.php geprüft (Glas über der ungünstigsten Feldfarbe, inkl. Sättigung
 * des Hintergrundfilters):  php kits/glas/tools/contrast.php
 * Standardwerte müssen mit :root in assets/css/_tokens.css übereinstimmen (Vorlage „Aurora“).
 */

// Reihenfolge der Farbwerte in den Vorlagen
$colorKeys = ['accent', 'accent_ink', 'on_accent', 'ink', 'text', 'muted', 'background', 'glass', 'field_1', 'field_2', 'field_3', 'dark_section'];

/** Vorlage: Farben hell + dunkel (je 12 Werte in obiger Reihenfolge) und übrige Werte */
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

// Gemeinsame Werte der Vorlagen (werden je Vorlage überschrieben)
$base = [
    'font_body' => 'figtree', 'font_head' => 'outfit',
    'fs_min' => 16, 'fs_max' => 18, 'ratio' => 1.25, 'heading_weight' => 600, 'heading_tracking' => -2,
    'density' => 'standard', 'blur' => 20, 'field' => 'soft', 'grain' => true, 'solid' => false,
    'radius' => 20, 'buttons' => 'soft', 'space' => 'normal', 'wrap' => 76,
    'header' => 'dock', 'header_sticky' => true, 'nav_parent' => 'split', 'nav_levels' => 'indent', 'footer' => 'glass',
    'motion' => true, 'dark' => true,
];

return [
    'groups' => [
        ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
            $color('accent', 'Akzent (Füllfarbe)', '--g-a', '#6D28D9', '#A78BFA', ['with' => 'background', 'min' => 3],
                'Buttons, aktive Punkte, Skalen. Als Bedienelement mindestens 3:1 zum Hintergrund.'),
            $color('accent_ink', 'Akzent als Schrift', '--g-a-ink', '#5B21B6', '#C4B5FD', ['with' => 'background', 'min' => 4.5],
                'Links, Dachzeilen, hervorgehobene Wörter – lesbar auf Glas und Farbfeld.'),
            $color('on_accent', 'Schrift auf Akzent', '--g-a-on', '#FFFFFF', '#140A33', ['with' => 'accent', 'min' => 4.5],
                'Beschriftung der Buttons und des Abschnitts „Akzentfarbe“.'),
            $color('ink', 'Überschriften', '--g-ink', '#12142B', '#F4F3FF', ['with' => 'background', 'min' => 7]),
            $color('text', 'Fließtext', '--g-text', '#2A2D48', '#DCDCF2', ['with' => 'background', 'min' => 4.5]),
            $color('muted', 'Nebentext', '--g-muted', '#44475F', '#BEBFDD', ['with' => 'background', 'min' => 4.5],
                'Einleitungen, Beschriftungen, Metadaten. Geprüft auch auf Glas über der ungünstigsten Feldfarbe (tools/contrast.php).'),
            $color('background', 'Grundfarbe', '--g-bg', '#F3F1FB', '#0B0D1C', null,
                'Die Fläche unter dem Farbfeld – sichtbar, wo das Feld ausläuft, und bei „Transparenz reduzieren“.'),
            $color('glass', 'Glastönung', '--g-glass', '#FFFFFF', '#161A33', ['with' => 'text', 'min' => 7],
                'Farbe der Glasflächen (Karten, Kopf, Menüs, Dialoge). Die Deckkraft stellt „Glasdichte“ ein.'),
            $color('field_1', 'Farbfeld 1', '--g-f1', '#B9A6FF', '#5B2BB5', null, 'Erste Farbe des Farbfelds und der Aurora (oben links).'),
            $color('field_2', 'Farbfeld 2', '--g-f2', '#8FE3F0', '#0E6A86', null, 'Zweite Farbe (oben rechts).'),
            $color('field_3', 'Farbfeld 3', '--g-f3', '#FFB3D9', '#8C1F66', null, 'Dritte Farbe (unten).'),
            $color('dark_section', 'Nacht (dunkle Abschnitte)', '--g-dark', '#15123A', '#1B1840', ['with' => '#F4F3FF', 'min' => 7, 'dark_with' => '#F4F3FF'],
                'Hintergrund der Abschnitte „Nacht“ (Schrift hell).'),
        ]],
        ['id' => 'glas', 'label' => 'Glas & Farbfeld', 'tokens' => [
            ['name' => 'density', 'label' => 'Glasdichte', 'type' => 'choice', 'class' => 'gd-{value}', 'default' => 'standard',
                'options' => ['light' => 'Klar (mehr Durchblick)', 'standard' => 'Mattglas (ausgewogen)', 'dense' => 'Milchglas (ruhig, am besten lesbar)'],
                'help' => 'Wie viel vom Farbfeld durch Karten, Kopf und Menüs scheint. Jede Stufe hält 4,5:1 für Text – hell wie dunkel.'],
            ['name' => 'blur', 'label' => 'Unschärfe des Glases (px)', 'type' => 'range', 'var' => '--g-blur', 'unit' => 'px', 'min' => 8, 'max' => 32, 'step' => 2, 'default' => 20,
                'help' => 'Auf kleinen Bildschirmen automatisch geringer (schneller beim Scrollen).'],
            ['name' => 'field', 'label' => 'Farbfeld hinter der Seite', 'type' => 'choice', 'class' => 'ff-{value}', 'default' => 'soft',
                'options' => ['soft' => 'Sanft', 'rich' => 'Kräftig', 'plain' => 'Nur im Einstieg (Seite einfarbig)']],
            ['name' => 'grain', 'label' => 'Feine Körnung auf dem Farbfeld', 'type' => 'bool', 'class' => 'has-grain', 'default' => true,
                'help' => 'Ein leises Rauschen – das Glas darüber wirkt dadurch glatt und matt.'],
            ['name' => 'solid', 'label' => 'Ohne Transparenz (deckende Flächen)', 'type' => 'bool', 'class' => 'is-solid', 'default' => false,
                'help' => 'Wie „Transparenz reduzieren“ im Gerät der Besucher – das gilt ohnehin immer automatisch.'],
        ]],
        ['id' => 'typo', 'label' => 'Typografie', 'tokens' => [
            ['name' => 'font_body', 'label' => 'Schrift Fließtext', 'type' => 'font', 'var' => '--g-font', 'default' => 'figtree'],
            ['name' => 'font_head', 'label' => 'Schrift Überschriften', 'type' => 'font', 'var' => '--g-font-head', 'default' => 'outfit'],
            ['name' => 'fs_min', 'label' => 'Grundschrift auf kleinen Bildschirmen (px)', 'type' => 'range', 'var' => '--g-fs-min', 'unit' => '',
                'min' => 15, 'max' => 19, 'step' => 0.5, 'default' => 16, 'help' => 'Bezogen auf die Standardgröße des Browsers (16 px); Besucher können weiter zoomen.'],
            ['name' => 'fs_max', 'label' => 'Grundschrift auf großen Bildschirmen (px)', 'type' => 'range', 'var' => '--g-fs-max', 'unit' => '',
                'min' => 15, 'max' => 22, 'step' => 0.5, 'default' => 18],
            ['name' => 'ratio', 'label' => 'Verhältnis der Schriftstufen', 'type' => 'range', 'var' => '--g-ratio', 'unit' => '',
                'min' => 1.125, 'max' => 1.5, 'step' => 0.025, 'default' => 1.25,
                'help' => '1,125 = zurückhaltend · 1,25 = ausgewogen · 1,333 = deutlich · 1,414+ = plakativ. Auf kleinen Bildschirmen automatisch flacher.'],
            ['name' => 'heading_weight', 'label' => 'Stärke der Überschriften', 'type' => 'range', 'var' => '--g-hw', 'unit' => '',
                'min' => 300, 'max' => 800, 'step' => 50, 'default' => 600],
            ['name' => 'heading_tracking', 'label' => 'Laufweite der Überschriften (1/100 em)', 'type' => 'range', 'var' => '--g-track', 'unit' => '',
                'min' => -5, 'max' => 2, 'step' => 0.5, 'default' => -2, 'help' => 'Negativ = enger, wie bei großen Überschriften üblich.'],
        ]],
        ['id' => 'form', 'label' => 'Form & Abstände', 'tokens' => [
            ['name' => 'radius', 'label' => 'Eckenradius der Glasflächen', 'type' => 'range', 'var' => '--g-radius', 'unit' => 'px', 'min' => 8, 'max' => 32, 'step' => 2, 'default' => 20,
                'help' => 'Karten, Paneele und Dialoge. Buttons und Eingabefelder behalten 8 px.'],
            ['name' => 'buttons', 'label' => 'Buttons', 'type' => 'choice', 'class' => 'btn-{value}', 'default' => 'soft', 'preview' => 'radius',
                'options' => ['soft' => 'Weich (8 px)', 'pill' => 'Pille (rund)'], 'values' => ['soft' => '8px', 'pill' => '999px']],
            ['name' => 'space', 'label' => 'Abstand zwischen Abschnitten', 'type' => 'choice', 'var' => '--g-space', 'default' => 'normal',
                'options' => ['compact' => 'Kompakt', 'normal' => 'Normal', 'airy' => 'Großzügig'], 'values' => ['compact' => '.75', 'normal' => '1', 'airy' => '1.3']],
            ['name' => 'wrap', 'label' => 'Maximale Inhaltsbreite (rem)', 'type' => 'range', 'var' => '--g-wrap', 'unit' => 'rem', 'min' => 60, 'max' => 96, 'step' => 2, 'default' => 76,
                'help' => '1 rem = 16 px.'],
        ]],
        ['id' => 'navigation', 'label' => 'Kopf & Fuß', 'tokens' => [
            ['name' => 'header', 'markup' => true, 'label' => 'Kopfbereich', 'type' => 'choice', 'class' => 'hdr-{value}', 'default' => 'dock', 'preview' => 'nav',
                'thumbs' => ['dock' => 'floating', 'floating' => 'left', 'bar' => 'left', 'centered' => 'center', 'index' => 'burger'],
                'options' => [
                    'dock' => 'Glas-Dock – mittige Glasinsel mit gleitender Linse, auf Telefonen Tab-Leiste unten',
                    'floating' => 'Schwebende Leiste – volle Inhaltsbreite mit Abstand zum Rand',
                    'bar' => 'Leiste – volle Breite, Marke links, Menü rechts',
                    'centered' => 'Zentriert – Marke über dem Menü',
                    'index' => 'Minimal – nur Marke und Menü-Schaltfläche',
                ],
                'help' => 'Das Menü steht in der Leiste, solange es hineinpasst – sonst öffnet eine Schaltfläche das Menü als Glasblatt. „Glas-Dock“: Unterseiten als Glaspanel mit Symbol und Kurzbeschreibung (Meta-Beschreibung der Seite); auf Telefonen Start, drei Hauptseiten und „Mehr“ als Tab-Leiste am unteren Rand.'],
            ['name' => 'header_sticky', 'label' => 'Kopfbereich beim Scrollen sichtbar halten', 'type' => 'bool', 'class' => 'hdr-sticky', 'default' => true],
            ['name' => 'nav_parent', 'markup' => true, 'label' => 'Menüpunkte mit Unterseiten', 'type' => 'choice', 'class' => 'np-{value}', 'default' => 'split',
                'options' => ['split' => 'Link + Pfeil – der Menüpunkt öffnet seine Seite, der Pfeil die Unterseiten', 'hover' => 'Link + Pfeil, Unterseiten öffnen auch beim Überfahren mit der Maus', 'overview' => 'Klick öffnet die Unterseiten, Eintrag „Übersicht“ führt zur Seite'],
                'help' => 'Gilt für das Menü in der Leiste und im Seitenblatt (Telefon). „Link + Pfeil“ kommt ohne zusätzlichen Eintrag „Übersicht“ aus.'],
            ['name' => 'nav_levels', 'label' => 'Dritte Menüebene im Aufklappmenü', 'type' => 'choice', 'class' => 'nv-{value}', 'default' => 'indent',
                'options' => ['indent' => 'Eingerückt – mit Linie, etwas kleiner', 'groups' => 'Gruppiert – zweite Ebene als Zwischenüberschrift, dritte darunter'],
                'help' => 'Wie Unterseiten von Unterseiten im Aufklappmenü der Leiste erscheinen.'],
            ['name' => 'footer', 'markup' => true, 'label' => 'Fußbereich', 'type' => 'choice', 'class' => 'ft-{value}', 'default' => 'glass',
                'options' => ['glass' => 'Glaspaneel (Seiten, Kontakt, Zeiten)', 'panel' => 'Aussage (Satz, Button, darunter Seiten)', 'simple' => 'Schlicht in einer Zeile']],
        ]],
        // Kopfbereich-Aktionen (Core\HeaderActions) – Standard des Kits: Befehlsfeld „Suchen … ⌘K“ aus Glas im Dock + Kontakt-Menü als Glastaste mit Glaspaneel
        \Core\HeaderActions::designGroup(['ha_cta_style' => 'menu', 'ha_menu_icon' => 'chats', 'ha_search' => 'command']),
        ['id' => 'modus', 'label' => 'Bewegung & Farbschema', 'tokens' => [
            ['name' => 'motion', 'label' => 'Sanfte Bewegung (Aurora, Einblenden)', 'type' => 'bool', 'class' => 'has-motion', 'default' => true,
                'help' => 'Besucher mit „Bewegung reduzieren“ sehen nie Animationen – unabhängig von dieser Einstellung. Außer Sicht pausiert die Aurora.'],
            ['name' => 'dark', 'label' => 'Dunkles Farbschema, wenn im Gerät der Besucher eingestellt', 'type' => 'bool', 'class' => 'has-dark', 'default' => true],
        ]],
    ],

    // Schriften: 'fontsource' = installiert der Schriften-Manager (Core\Fonts, fonts:sync; selbst gehostet, ohne externe Anfragen) – variable Schriften: ein Download für alle Stärken
    'fonts' => [
        'outfit' => ['label' => 'Outfit (geometrisch, klar · variabel)', 'stack' => 'Outfit,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'fontsource' => 'outfit'],
        'figtree' => ['label' => 'Figtree (freundlich, gut lesbar · variabel)', 'stack' => 'Figtree,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'fontsource' => 'figtree'],
        'sora' => ['label' => 'Sora (technisch-weit · variabel)', 'stack' => 'Sora,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'sora'],
        'urbanist' => ['label' => 'Urbanist (geometrisch, elegant · variabel)', 'stack' => 'Urbanist,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'urbanist'],
        'system' => ['label' => 'Systemschrift (ohne Download)', 'stack' => 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'],
    ],

    // Vier Vorlagen – alle hell und dunkel, jede Glasdichte, AA-geprüft (tools/contrast.php)
    'presets' => [
        'aurora' => $preset('Aurora (Standard)', 'Violett, Türkis und Rosé als weiches Farbfeld, Mattglas, schwebender Kopf – Outfit und Figtree.',
            ['#6D28D9', '#5B21B6', '#FFFFFF', '#12142B', '#2A2D48', '#44475F', '#F3F1FB', '#FFFFFF', '#B9A6FF', '#8FE3F0', '#FFB3D9', '#15123A'],
            ['#A78BFA', '#C4B5FD', '#140A33', '#F4F3FF', '#DCDCF2', '#BEBFDD', '#0B0D1C', '#161A33', '#5B2BB5', '#0E6A86', '#8C1F66', '#1B1840'],
            $base),
        'lagune' => $preset('Lagune', 'Türkis, Petrol und helles Blau – klar und frisch; klares Glas, Leiste über die volle Breite, Sora für Überschriften.',
            ['#0F766E', '#0B5E57', '#FFFFFF', '#0B1F24', '#1E3A40', '#3D5A60', '#EEF7F7', '#FFFFFF', '#8EE6D6', '#9CC9FF', '#C8F2A6', '#0B2A30'],
            ['#2DD4BF', '#7EE8D8', '#04201C', '#EAFBF8', '#CDE7E4', '#A3C4C0', '#071417', '#10252A', '#0B5E57', '#1D4E89', '#2F6B2A', '#0E2F35'],
            ['font_head' => 'sora', 'heading_weight' => 600, 'heading_tracking' => -2.5, 'density' => 'light', 'header' => 'bar', 'radius' => 16] + $base),
        'daemmerung' => $preset('Dämmerung', 'Pfirsich, Koralle und Pflaume wie ein Abendhimmel – warm, kräftiges Feld, Pillen-Buttons, Urbanist.',
            ['#B4234F', '#8A193C', '#FFFFFF', '#2A1020', '#44283A', '#573C4D', '#FBF1EE', '#FFFFFF', '#FFC29E', '#FF9EB3', '#C9A8F5', '#2A1030'],
            ['#FB7196', '#FDA4B8', '#2A0714', '#FFF1F3', '#EDD6DD', '#D6BEC8', '#140A12', '#241424', '#9A3412', '#9D174D', '#5B21B6', '#2B1233'],
            ['font_head' => 'urbanist', 'font_body' => 'figtree', 'heading_weight' => 700, 'heading_tracking' => -1.5, 'field' => 'rich', 'buttons' => 'pill', 'radius' => 24, 'footer' => 'panel'] + $base),
        'graphit' => $preset('Graphit', 'Rauchglas auf Anthrazit mit kühlem Blau – von Anfang an dunkel, Milchglas, minimaler Kopf, Outfit.',
            ['#8FA8FF', '#B3C3FF', '#0A1030', '#F2F4FA', '#D5D9E6', '#AEB4C6', '#101218', '#1C2029', '#2B3A73', '#1F4F5E', '#46325F', '#171A24'],
            ['#8FA8FF', '#B3C3FF', '#0A1030', '#F2F4FA', '#D5D9E6', '#AEB4C6', '#0B0C11', '#181B23', '#24305F', '#173F4B', '#3A2952', '#14161E'],
            ['font_body' => 'outfit', 'font_head' => 'outfit', 'heading_weight' => 500, 'heading_tracking' => -2.5, 'density' => 'dense', 'header' => 'index', 'footer' => 'simple', 'grain' => false, 'radius' => 14] + $base),
    ],

    // Dunkle Werte bei „Dunkles Farbschema“ (Klasse has-dark) und Geräte-Einstellung; Vorschau im Editor setzt is-dark
    'dark' => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark', 'force' => 'is-dark'],

    // Musterseite für die Vorschau im Editor (siehe tools/demo.php)
    'sample' => 'labor',
];
