<?php
/*
 * Design-Tokens des Kits „galerie“ für den Style-Editor (Verwaltung → Design) – siehe Core\Design.
 *
 * Jeder Token wird eine CSS-Variable (--f-…, --g-…) oder eine Klasse am <html> (hdr-inline, af-wall, cap-museum, has-dots …).
 * Typografie und Abstände sind fließend (Utopia-Prinzip, clamp) – keine Breakpoints, keine Media-Queries für Breiten.
 * Galerie-Optionen (Gruppe „Galerie“): Bildpräsentation, Werkangaben, Verfügbarkeitspunkte, Preise, Datumsformat,
 * Ausstellungs-Etiketten, Dichte der Listen – teils serverseitig (galerie_* in functions.php), teils per Klasse.
 * Voreinstellungen sind mit tools/contrast.php auf WCAG 2.2 AA geprüft (hell und dunkel):
 *   php kits/galerie/tools/contrast.php
 * Standardwerte müssen mit :root in assets/css/_tokens.css übereinstimmen (Voreinstellung „White Cube“).
 */

// Reihenfolge der Farbwerte in den Voreinstellungen
$colorKeys = ['accent', 'accent_strong', 'on_accent', 'highlight', 'ink', 'text', 'muted', 'background', 'surface', 'line', 'dark_section'];

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

// Gemeinsame Werte der Voreinstellungen (werden je Vorlage überschrieben) – entsprechen „White Cube“
$base = [
    'secondary' => '#3D3D3A', 'secondary@dark' => '#D6D6D2', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#111111',
    'available' => '#1A7F37', 'available@dark' => '#5BD07F', 'sold' => '#C62828', 'sold@dark' => '#FF8A80',
    'font_body' => 'inter', 'font_head' => 'inter', 'font_mono' => 'system-mono', 'label_font' => 'body',
    'fs_min' => 16, 'fs_max' => 18, 'ratio' => 1.25, 'vw_min' => 360, 'vw_max' => 1440,
    'heading_weight' => 500, 'heading_tracking' => -2.5,
    'radius' => 0, 'buttons' => 'solid', 'cards' => 'flat', 'shadow' => 'none', 'density' => 'normal', 'space' => 'airy', 'wrap' => 90,
    'secondary_role' => 'sections', 'heading_case' => 'normal', 'emphasis' => 'italic', 'links' => 'underline',
    'images' => 'sharp', 'image_fx' => 'none', 'hover' => 'none', 'icons' => 'plain', 'icon_pos' => 'top', 'dividers' => 'none', 'motion_style' => 'fade',
    'eyebrow' => 'plain', 'header' => 'inline', 'header_sticky' => true, 'header_bg' => 'page', 'nav_style' => 'underline', 'topbar' => 'off',
    'footer' => 'columns', 'footer_bg' => 'page', 'pagebg' => 'plain',
    'artframe' => 'flat', 'caption' => 'museum', 'dots' => true, 'price_mode' => 'per_work', 'date_style' => 'long', 'label_style' => 'text', 'list_density' => 'normal',
    'motion' => true, 'dark' => true,
];

return [
    'groups' => [
        ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
            $color('accent', 'Akzent', '--f-a', '#111111', '#F2F2F0', ['with' => 'background', 'min' => 4.5],
                'Buttons, Links, Etikett „Jetzt“ und Abschnitte „Akzentfarbe“. Galerien wählen oft Schwarz – die Kunst bringt die Farbe.'),
            $color('accent_strong', 'Akzent kräftig', '--f-a-strong', '#000000', '#FFFFFF', ['with' => 'background', 'min' => 4.5],
                'Hover-Zustand; hell etwas dunkler als der Akzent, dunkel etwas heller.'),
            $color('on_accent', 'Schrift auf Akzent', '--f-a-on', '#FFFFFF', '#0A0A0A', ['with' => 'accent', 'min' => 4.5],
                'Text auf Buttons und im Abschnitt „Akzentfarbe“.'),
            $color('highlight', 'Hervorhebung (Flächen, Marker)', '--f-a2', '#EDEDE9', '#2A2A28', ['with' => 'ink', 'min' => 4.5],
                'Hervorgehobene Flächen, Markierungen und Etiketten – Überschriftenfarbe muss darauf lesbar sein.'),
            $color('secondary', 'Zweite Markenfarbe', '--f-b', '#3D3D3A', '#D6D6D2', ['with' => 'background', 'min' => 3],
                'Abschnitte „Zweite Markenfarbe“ und – je nach Einstellung unten – Dachzeilen-Marken und zweite Buttons.'),
            $color('on_secondary', 'Schrift auf zweiter Markenfarbe', '--f-b-on', '#FFFFFF', '#111111', ['with' => 'secondary', 'min' => 4.5]),
            $color('ink', 'Überschriften', '--f-ink', '#0A0A0A', '#F5F5F3', ['with' => 'background', 'min' => 7]),
            $color('text', 'Fließtext', '--f-text', '#262626', '#D6D6D2', ['with' => 'background', 'min' => 4.5]),
            $color('muted', 'Nebentext', '--f-muted', '#5C5C59', '#A3A39E', ['with' => 'surface', 'min' => 4.5],
                'Werkangaben, Daten, Bildunterschriften – auch auf getönten Flächen lesbar.'),
            $color('background', 'Hintergrund (Wand)', '--f-bg', '#FFFFFF', '#0B0B0B'),
            $color('surface', 'Getönte Fläche', '--f-surface', '#F4F4F2', '#161615', ['with' => 'text', 'min' => 4.5],
                'Abschnitte „Getönt“, Passepartout im dunklen Schema, Karten im Stil „Fläche“.'),
            $color('line', 'Linien', '--f-line', '#E4E4E0', '#2A2A28', null, 'Trennlinien und Rahmen (dekorativ).'),
            $color('dark_section', 'Dunkle Abschnitte', '--f-dark', '#111111', '#1C1C1B', ['with' => '#FFFFFF', 'min' => 7, 'dark_with' => '#FFFFFF'],
                'Hintergrund der Abschnitte „Dunkel“ (Schrift weiß). Im dunklen Farbschema etwas heller als der Hintergrund.'),
            $color('available', 'Punkt „Verfügbar“', '--g-avail', '#1A7F37', '#5BD07F', ['with' => 'background', 'min' => 3],
                'Verfügbarkeit von Werken – immer zusammen mit dem Wort „Verfügbar“, nie nur als Farbe.'),
            $color('sold', 'Punkt „Verkauft“', '--g-sold', '#C62828', '#FF8A80', ['with' => 'background', 'min' => 3],
                'Der rote Punkt neben verkauften Werken – wie in der Galerie. Reserviert: halb gefüllt.'),
            ['name' => 'secondary_role', 'label' => 'Zweite Markenfarbe einsetzen für', 'type' => 'choice', 'class' => 'sec2-{value}', 'default' => 'sections',
                'options' => ['sections' => 'Nur Abschnitte in dieser Farbe', 'details' => 'Auch Symbole und Dachzeilen-Marken', 'buttons' => 'Auch zweite Buttons', 'all' => 'Symbole, Marken und zweite Buttons']],
        ]],
        ['id' => 'typo', 'label' => 'Typografie', 'tokens' => [
            ['name' => 'font_body', 'label' => 'Schrift Fließtext', 'type' => 'font', 'var' => '--f-font', 'default' => 'inter'],
            ['name' => 'font_head', 'label' => 'Schrift Überschriften', 'type' => 'font', 'var' => '--f-font-head', 'default' => 'inter'],
            ['name' => 'font_mono', 'label' => 'Schrift für Kennzeichnungen (Monospace)', 'type' => 'font', 'var' => '--f-font-mono', 'default' => 'system-mono',
                'help' => 'Für Etiketten, Werkangaben und Daten, wenn unten „Monospace“ gewählt ist. Die Datei lädt nur, wenn sie gebraucht wird.'],
            ['name' => 'label_font', 'label' => 'Dachzeilen, Etiketten und Daten', 'type' => 'choice', 'var' => '--f-font-label', 'default' => 'body',
                'options' => ['body' => 'Wie Fließtext', 'head' => 'Wie Überschriften', 'mono' => 'Monospace (Archiv, Katalog)'],
                'values' => ['body' => 'var(--f-font)', 'head' => 'var(--f-font-head)', 'mono' => 'var(--f-font-mono)']],
            ['name' => 'fs_min', 'label' => 'Grundschrift auf kleinen Bildschirmen (px)', 'type' => 'range', 'var' => '--f-fs-min', 'unit' => '',
                'min' => 14, 'max' => 19, 'step' => 0.5, 'default' => 16, 'help' => 'Bezogen auf die Standardgröße des Browsers (16 px); Besucher können weiter zoomen.'],
            ['name' => 'fs_max', 'label' => 'Grundschrift auf großen Bildschirmen (px)', 'type' => 'range', 'var' => '--f-fs-max', 'unit' => '',
                'min' => 15, 'max' => 23, 'step' => 0.5, 'default' => 18],
            ['name' => 'ratio', 'label' => 'Verhältnis der Schriftstufen', 'type' => 'range', 'var' => '--f-ratio', 'unit' => '',
                'min' => 1.125, 'max' => 1.6, 'step' => 0.025, 'default' => 1.25,
                'help' => '1,2 = ruhig (Archiv) · 1,25 = ausgewogen · 1,4+ = plakativ (Kunstverein, große Ausstellungstitel). Auf kleinen Bildschirmen automatisch flacher.'],
            ['name' => 'vw_min', 'label' => 'Fließend ab Bildschirmbreite (px)', 'type' => 'range', 'var' => '--f-vw-min', 'unit' => '',
                'min' => 320, 'max' => 480, 'step' => 10, 'default' => 360, 'help' => 'Unterhalb gelten die kleinen Werte.'],
            ['name' => 'vw_max', 'label' => 'Fließend bis Bildschirmbreite (px)', 'type' => 'range', 'var' => '--f-vw-max', 'unit' => '',
                'min' => 960, 'max' => 1800, 'step' => 20, 'default' => 1440, 'help' => 'Oberhalb gelten die großen Werte.'],
            ['name' => 'heading_weight', 'label' => 'Stärke der Überschriften', 'type' => 'range', 'var' => '--f-hw', 'unit' => '',
                'min' => 300, 'max' => 900, 'step' => 50, 'default' => 500, 'help' => 'Stufenlos bei variablen Schriften. Galerien wirken mit 400–500 ruhiger.'],
            ['name' => 'heading_case', 'label' => 'Schreibweise der Überschriften', 'type' => 'choice', 'class' => 'hcase-{value}', 'default' => 'normal',
                'options' => ['normal' => 'Normal', 'upper' => 'VERSALIEN', 'smallcaps' => 'Kapitälchen']],
            ['name' => 'emphasis', 'label' => 'Betonung *Wort* in Überschriften', 'type' => 'choice', 'class' => 'em-{value}', 'default' => 'italic',
                'options' => ['italic' => 'Kursiv', 'accent' => 'Akzentfarbe (bei Serifen kursiv)', 'bold' => 'Fett', 'marker' => 'Textmarker (Hervorhebungsfarbe)', 'underline' => 'Unterstrichen in Akzentfarbe']],
            ['name' => 'links', 'label' => 'Links im Fließtext', 'type' => 'choice', 'class' => 'ln-{value}', 'default' => 'underline',
                'options' => ['underline' => 'Unterstrichen', 'accent' => 'Fett in Akzentfarbe, unterstrichen beim Zeigen', 'marker' => 'Getönte Linie, füllt sich beim Zeigen']],
            ['name' => 'heading_tracking', 'label' => 'Laufweite der Überschriften (1/100 em)', 'type' => 'range', 'var' => '--f-track', 'unit' => '',
                'min' => -6, 'max' => 3, 'step' => 0.5, 'default' => -2.5, 'help' => 'Negativ = enger (große Grotesk-Überschriften), 0 = normal (Serifen).'],
        ]],
        // Galerie: Präsentation der Kunst – CSS-Klassen am <html> (af-*, cap-*, has-dots, lbl-*, ld-*) und Ausgabe der Blöcke (Preis, Datum)
        ['id' => 'galerie', 'label' => 'Galerie', 'tokens' => [
            ['name' => 'artframe', 'label' => 'Präsentation der Werke', 'type' => 'choice', 'class' => 'af-{value}', 'default' => 'flat',
                'options' => ['flat' => 'Flach (Bild ohne Rahmen)', 'wall' => 'An der Wand (feiner Schatten)', 'mat' => 'Passepartout (weißer Rand)'],
                'help' => 'Gilt für Werkabbildungen in Rastern, Viewing Room und Detailseite – nicht für Ausstellungsansichten.'],
            ['name' => 'caption', 'label' => 'Werkangaben', 'type' => 'choice', 'class' => 'cap-{value}', 'default' => 'museum',
                'options' => ['museum' => 'Museumsschild (Künstler fett, Titel kursiv, Jahr, Technik, Maße)', 'minimal' => 'Knapp (Künstler, Titel, Jahr)', 'line' => 'Eine Zeile, klein (Katalog)'],
                'help' => 'Wie die Angaben unter Werkabbildungen erscheinen. Auf der Detailseite stehen immer alle Angaben.'],
            ['name' => 'dots', 'label' => 'Verfügbarkeit als Punkt zeigen (grün verfügbar · halb reserviert · rot verkauft)', 'type' => 'bool', 'class' => 'has-dots', 'default' => true,
                'help' => 'Der Punkt steht immer zusammen mit dem Wort (z. B. „Verkauft“) – Farbe allein trägt keine Information.'],
            ['name' => 'price_mode', 'label' => 'Preise', 'type' => 'choice', 'class' => 'pr-{value}', 'default' => 'per_work',
                'options' => ['per_work' => 'Wie am Werk eingestellt (Preis, „auf Anfrage“ oder ausgeblendet)', 'request' => 'Immer „Preis auf Anfrage“', 'never' => 'Nie anzeigen'],
                'help' => 'Verkaufte Werke zeigen nie einen Preis.'],
            ['name' => 'date_style', 'label' => 'Datumsangaben von Ausstellungen', 'type' => 'choice', 'class' => 'ds-{value}', 'default' => 'long',
                'options' => ['long' => '12. März – 30. April 2026', 'numeric' => '12.03. – 30.04.2026', 'relative' => 'Bis 30. April · Ab 12. März (je nach Status)']],
            ['name' => 'label_style', 'label' => 'Etikett „Jetzt“ / „Demnächst“', 'type' => 'choice', 'class' => 'lbl-{value}', 'default' => 'text',
                'options' => ['text' => 'Schlichter Text', 'pill' => 'Etikett in Akzentfarbe', 'dot' => 'Mit Punkt', 'outline' => 'Kontur']],
            ['name' => 'list_density', 'label' => 'Dichte der Listen und Raster', 'type' => 'choice', 'class' => 'ld-{value}', 'var' => '--g-density', 'default' => 'normal',
                'options' => ['compact' => 'Dicht (Archiv)', 'normal' => 'Normal', 'airy' => 'Großzügig (White Cube)'], 'values' => ['compact' => '.66', 'normal' => '1', 'airy' => '1.45']],
        ]],
        ['id' => 'form', 'label' => 'Form & Raum', 'tokens' => [
            ['name' => 'radius', 'label' => 'Eckenradius', 'type' => 'range', 'var' => '--f-radius', 'unit' => 'px', 'min' => 0, 'max' => 32, 'step' => 1, 'default' => 0],
            ['name' => 'buttons', 'label' => 'Buttons', 'type' => 'choice', 'class' => 'btn-{value}', 'default' => 'solid', 'preview' => 'radius',
                'options' => ['solid' => 'Gefüllt', 'pill' => 'Gefüllt, rund (Pille)', 'outline' => 'Kontur', 'soft' => 'Getönt', 'sharp' => 'Eckig mit Pfeil'],
                'values' => ['solid' => '0', 'pill' => '999px', 'outline' => '0', 'soft' => '12px', 'sharp' => '0']],
            ['name' => 'cards', 'label' => 'Karten', 'type' => 'choice', 'class' => 'cards-{value}', 'default' => 'flat',
                'options' => ['flat' => 'Fläche (ohne Rahmen)', 'outlined' => 'Mit Rahmen', 'elevated' => 'Mit Schatten', 'glass' => 'Glas (durchscheinend)']],
            ['name' => 'shadow', 'label' => 'Schatten', 'type' => 'choice', 'var' => '--f-shadow', 'default' => 'none',
                'options' => ['none' => 'Keine', 'soft' => 'Weich', 'crisp' => 'Knapp (grafisch)', 'layered' => 'Tief, mehrschichtig'],
                'values' => [
                    'none' => 'none',
                    'soft' => '0 1px 2px var(--f-sh1),0 12px 32px -12px var(--f-sh2)',
                    'crisp' => '4px 4px 0 var(--f-ink)',
                    'layered' => '0 1px 1px var(--f-sh1),0 4px 8px -2px var(--f-sh1),0 24px 48px -16px var(--f-sh2)',
                ]],
            ['name' => 'density', 'label' => 'Dichte (Innenabstände)', 'type' => 'choice', 'var' => '--f-density', 'default' => 'normal',
                'options' => ['compact' => 'Kompakt', 'normal' => 'Normal', 'relaxed' => 'Luftig'], 'values' => ['compact' => '.8', 'normal' => '1', 'relaxed' => '1.2']],
            ['name' => 'space', 'label' => 'Abstand zwischen Abschnitten', 'type' => 'choice', 'var' => '--f-space', 'default' => 'airy',
                'options' => ['compact' => 'Kompakt', 'normal' => 'Normal', 'airy' => 'Großzügig'], 'values' => ['compact' => '.72', 'normal' => '1', 'airy' => '1.32']],
            ['name' => 'wrap', 'label' => 'Maximale Inhaltsbreite (rem)', 'type' => 'range', 'var' => '--f-wrap', 'unit' => 'rem', 'min' => 56, 'max' => 110, 'step' => 2, 'default' => 90,
                'help' => '1 rem = 16 px. Galerien profitieren von breiten Werten – Bilder brauchen Platz.'],
            ['name' => 'eyebrow', 'label' => 'Dachzeilen', 'type' => 'choice', 'class' => 'eb-{value}', 'default' => 'plain',
                'options' => ['plain' => 'Schlicht', 'line' => 'Mit Strich', 'pill' => 'Als Etikett', 'dot' => 'Mit Punkt']],
            ['name' => 'images', 'label' => 'Ecken der Bilder (Ausstellungsansichten, Porträts)', 'type' => 'choice', 'class' => 'img-{value}', 'default' => 'sharp',
                'options' => ['sharp' => 'Eckig', 'radius' => 'Wie Eckenradius', 'round' => 'Stark gerundet', 'arch' => 'Bogen oben (Torbogen)'],
                'help' => 'Werkabbildungen bleiben immer unbeschnitten und eckig – Kunst wird nicht abgerundet.'],
            ['name' => 'image_fx', 'label' => 'Bildwirkung (Ausstellungsansichten, Porträts)', 'type' => 'choice', 'class' => 'imgfx-{value}', 'default' => 'none',
                'options' => ['none' => 'Natürlich', 'mono' => 'Schwarzweiß, farbig beim Zeigen', 'tint' => 'In Akzentfarbe getönt, farbig beim Zeigen']],
            ['name' => 'hover', 'label' => 'Verlinkte Karten beim Zeigen', 'type' => 'choice', 'class' => 'hov-{value}', 'default' => 'none',
                'options' => ['none' => 'Ruhig (nur Mauszeiger)', 'lift' => 'Leicht anheben', 'glow' => 'Rahmen in Akzentfarbe']],
            ['name' => 'icons', 'label' => 'Symbole (z. B. Merkmale)', 'type' => 'choice', 'class' => 'ic-{value}', 'default' => 'plain',
                'options' => ['plain' => 'Ohne Fläche', 'soft' => 'Getönte Fläche', 'circle' => 'Kreis', 'outline' => 'Kontur']],
            ['name' => 'icon_pos', 'label' => 'Anordnung der Symbole', 'type' => 'choice', 'class' => 'icp-{value}', 'default' => 'top',
                'options' => ['top' => 'Über dem Titel', 'inline' => 'In einer Zeile mit dem Titel', 'side' => 'Links neben dem Text']],
            ['name' => 'dividers', 'label' => 'Übergang zwischen Abschnitten', 'type' => 'choice', 'class' => 'div-{value}', 'default' => 'none',
                'options' => ['none' => 'Gerade Kante', 'slant' => 'Schräg', 'curve' => 'Bogen'],
                'help' => 'Sichtbar, wo zwei Abschnitte mit verschiedenen Hintergründen aufeinandertreffen.'],
        ]],
        ['id' => 'navigation', 'label' => 'Kopf & Fuß', 'tokens' => [
            ['name' => 'header', 'label' => 'Navigation', 'type' => 'choice', 'class' => 'hdr-{value}', 'default' => 'inline', 'preview' => 'nav',
                'thumbs' => ['inline' => 'left', 'centered' => 'center', 'split' => 'split', 'rail' => 'sidebar', 'minimal' => 'left'],
                'options' => [
                    'inline' => 'Leiste oben – Name links, Menü rechts',
                    'centered' => 'Zentriert – Name über dem Menü',
                    'split' => 'Geteilt – Menü in der Mitte',
                    'rail' => 'Seitenleiste – Menü links (breite Fenster)',
                    'minimal' => 'Minimal – nur Name und Menü-Schaltfläche',
                ],
                'help' => 'Das Menü steht in der Leiste, solange es hineinpasst – sonst öffnet eine Menü-Schaltfläche ein Seitenblatt. Ganz ohne feste Bildschirmbreiten.'],
            ['name' => 'header_sticky', 'label' => 'Kopfbereich beim Scrollen sichtbar halten', 'type' => 'bool', 'class' => 'hdr-sticky', 'default' => true],
            ['name' => 'header_bg', 'label' => 'Hintergrund des Kopfbereichs', 'type' => 'choice', 'class' => 'hbg-{value}', 'default' => 'page',
                'options' => ['page' => 'Wie die Seite (leicht durchscheinend)', 'surface' => 'Getönte Fläche', 'accent' => 'Akzentfarbe', 'secondary' => 'Zweite Markenfarbe', 'dark' => 'Dunkel']],
            ['name' => 'nav_style', 'label' => 'Menüpunkte', 'type' => 'choice', 'class' => 'nl-{value}', 'default' => 'underline',
                'options' => ['underline' => 'Unterstrichen', 'plain' => 'Schlicht (Fläche beim Zeigen)', 'pill' => 'Pille (aktiver Punkt hinterlegt)', 'caps' => 'Versalien, gesperrt']],
            ['name' => 'topbar', 'label' => 'Infoleiste über dem Kopfbereich', 'type' => 'choice', 'class' => 'meta-{value}', 'default' => 'off',
                'options' => ['off' => 'Aus', 'dark' => 'Dunkel', 'accent' => 'Akzentfarbe', 'secondary' => 'Zweite Markenfarbe', 'surface' => 'Getönt'],
                'help' => 'Telefon, E-Mail und Social Media aus Website → Stammdaten, dazu ein kurzer Text (z. B. „Mi–Sa 12–18 Uhr · Eintritt frei“).'],
            ['name' => 'footer', 'label' => 'Fußbereich', 'type' => 'choice', 'class' => 'ft-{value}', 'default' => 'columns',
                'options' => ['columns' => 'Spalten (Adresse, Öffnungszeiten, Seiten, Social)', 'simple' => 'Schlicht in einer Zeile', 'centered' => 'Zentriert', 'statement' => 'Großer Schriftzug mit Handlungsaufruf']],
            ['name' => 'footer_bg', 'label' => 'Hintergrund des Fußbereichs', 'type' => 'choice', 'class' => 'fbg-{value}', 'default' => 'page',
                'options' => ['page' => 'Wie die Seite', 'surface' => 'Getönte Fläche', 'dark' => 'Dunkel', 'accent' => 'Akzentfarbe', 'secondary' => 'Zweite Markenfarbe']],
            ['name' => 'pagebg', 'label' => 'Seitenhintergrund', 'type' => 'choice', 'class' => 'pagebg-{value}', 'default' => 'plain',
                'options' => ['plain' => 'Einfarbig (White Cube)', 'grid' => 'Feines Raster', 'dots' => 'Punktraster']],
        ]],
        // Kopfbereich-Aktionen (Core\HeaderActions) – Standard des Kits: Suche als Symbol + Textlink („Besuch planen“)
        \Core\HeaderActions::designGroup(['ha_cta_style' => 'link', 'ha_search' => 'icon']),
        ['id' => 'modus', 'label' => 'Bewegung & Farbschema', 'tokens' => [
            ['name' => 'motion', 'label' => 'Dezente Animationen beim Scrollen (Einblenden)', 'type' => 'bool', 'class' => 'has-motion', 'default' => true,
                'help' => 'Besucher mit „Bewegung reduzieren“ sehen nie Animationen – unabhängig von dieser Einstellung.'],
            ['name' => 'motion_style', 'label' => 'Art des Einblendens', 'type' => 'choice', 'class' => 'mo-{value}', 'default' => 'fade',
                'options' => ['fade' => 'Nur Blende', 'rise' => 'Aufsteigen', 'scale' => 'Leicht wachsen', 'blur' => 'Aus der Unschärfe']],
            ['name' => 'dark', 'label' => 'Dunkles Farbschema, wenn im Gerät der Besucher eingestellt', 'type' => 'bool', 'class' => 'has-dark', 'default' => true],
        ]],
    ],

    // Schriften: 'fontsource' = installiert der Schriften-Manager (Core\Fonts, fonts:sync; selbst gehostet, ohne externe Anfragen) – variable Schriften: ein Download für alle Stärken
    'fonts' => [
        'inter' => ['label' => 'Inter (Grotesk, neutral · variabel)', 'stack' => 'Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'fontsource' => 'inter', 'styles' => ['normal', 'italic']],
        'manrope' => ['label' => 'Manrope (geometrisch, offen · variabel)', 'stack' => 'Manrope,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'manrope'],
        'instrument-sans' => ['label' => 'Instrument Sans (Grotesk, freundlich · variabel)', 'stack' => '"Instrument Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'instrument-sans', 'styles' => ['normal', 'italic']],
        'hanken' => ['label' => 'Hanken Grotesk (Grotesk, schlicht, auch sehr leicht · variabel)', 'stack' => '"Hanken Grotesk",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'hanken-grotesk', 'styles' => ['normal', 'italic']],
        'space-grotesk' => ['label' => 'Space Grotesk (technisch, markant · variabel)', 'stack' => '"Space Grotesk",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'space-grotesk'],
        'atkinson' => ['label' => 'Atkinson Hyperlegible Next (barrierearm · variabel)', 'stack' => '"Atkinson Hyperlegible Next","Atkinson Hyperlegible",ui-sans-serif,system-ui,sans-serif', 'fontsource' => 'atkinson-hyperlegible-next', 'styles' => ['normal', 'italic']],
        'newsreader' => ['label' => 'Newsreader (Serifen, sachlich · variabel)', 'stack' => 'Newsreader,ui-serif,Georgia,Cambria,serif', 'fontsource' => 'newsreader', 'styles' => ['normal', 'italic']],
        'fraunces' => ['label' => 'Fraunces (Serifen, weich, handwerklich · variabel)', 'stack' => 'Fraunces,ui-serif,Georgia,Cambria,serif', 'fontsource' => 'fraunces', 'styles' => ['normal', 'italic'], 'axis' => 'opsz'],
        'instrument-serif' => ['label' => 'Instrument Serif (Display-Serife, nur große Überschriften)', 'stack' => '"Instrument Serif",ui-serif,Georgia,Cambria,serif', 'fontsource' => 'instrument-serif', 'styles' => ['normal', 'italic']],
        'eb-garamond' => ['label' => 'EB Garamond (klassische Buchschrift, Salon · variabel)', 'stack' => '"EB Garamond",ui-serif,Garamond,Georgia,serif', 'fontsource' => 'eb-garamond', 'styles' => ['normal', 'italic']],
        'jetbrains-mono' => ['label' => 'JetBrains Mono (Monospace · variabel)', 'stack' => '"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace', 'fontsource' => 'jetbrains-mono', 'styles' => ['normal', 'italic']],
        'plex-mono' => ['label' => 'IBM Plex Mono (Monospace, Schreibmaschine · 400/500)', 'stack' => '"IBM Plex Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace', 'fontsource' => 'ibm-plex-mono', 'styles' => ['normal', 'italic'], 'variable' => false, 'weights' => [400, 500]],
        'system' => ['label' => 'Systemschrift (ohne Download)', 'stack' => 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'],
        'system-mono' => ['label' => 'System-Monospace (ohne Download)', 'stack' => 'ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace'],
    ],

    // Sechs Vorlagen für Galerien, Kunstvereine und Projekträume – alle hell und dunkel AA-geprüft (tools/contrast.php)
    'presets' => [
        'whitecube' => $preset('White Cube', 'Weiß, Schwarz, Inter: maximale Ruhe, große Bilder, Museumsschild unter den Werken – der Standard des Kits.',
            ['#111111', '#000000', '#FFFFFF', '#EDEDE9', '#0A0A0A', '#262626', '#5C5C59', '#FFFFFF', '#F4F4F2', '#E4E4E0', '#111111'],
            ['#F2F2F0', '#FFFFFF', '#0A0A0A', '#2A2A28', '#F5F5F3', '#D6D6D2', '#A3A39E', '#0B0B0B', '#161615', '#2A2A28', '#1C1C1B'],
            $base),
        'salon' => $preset('Salon · Warm & Serife', 'Gebrochenes Weiß, EB Garamond in den Überschriften, Bordeaux als Akzent, zentrierte Navigation, Werke an der Wand.',
            ['#7A2E2A', '#5E2320', '#FFFFFF', '#EEE4D3', '#1F1A16', '#3A332D', '#685D53', '#FBF8F3', '#F3EDE3', '#E5DCCD', '#241C17'],
            ['#E9B3A6', '#F3CEC4', '#2A0F0B', '#3D3022', '#F6EFE7', '#DDD3C8', '#ADA196', '#14110E', '#1E1915', '#362E27', '#2A221C'],
            ['secondary' => '#55473B', 'secondary@dark' => '#D9CBB8', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#1F1A16',
             'font_body' => 'instrument-sans', 'font_head' => 'eb-garamond', 'ratio' => 1.333, 'fs_max' => 18.5, 'heading_weight' => 450, 'heading_tracking' => -1,
             'radius' => 2, 'buttons' => 'outline', 'space' => 'airy', 'wrap' => 84, 'emphasis' => 'italic', 'nav_style' => 'plain',
             'header' => 'centered', 'footer' => 'columns', 'footer_bg' => 'surface', 'artframe' => 'wall', 'label_style' => 'dot'] + $base),
        'nacht' => $preset('Nacht · Dunkel von Anfang an', 'Für Fotografie, Licht- und Videokunst: dunkle Wand, knochenweiße Schrift, Instrument Serif, Werke flach auf der Wand.',
            ['#E8E3D8', '#FFFFFF', '#111110', '#2E2C28', '#F4F1EA', '#CFCAC0', '#A09A90', '#0E0E0D', '#181816', '#2C2B28', '#1F1E1C'],
            ['#E8E3D8', '#FFFFFF', '#111110', '#2E2C28', '#F4F1EA', '#CFCAC0', '#A09A90', '#0A0A09', '#151513', '#292825', '#1F1E1C'],
            ['secondary' => '#BDB6A8', 'secondary@dark' => '#BDB6A8', 'on_secondary' => '#111110', 'on_secondary@dark' => '#111110',
             'available' => '#5BD07F', 'available@dark' => '#5BD07F', 'sold' => '#FF8A80', 'sold@dark' => '#FF8A80',
             'font_body' => 'hanken', 'font_head' => 'instrument-serif', 'ratio' => 1.45, 'heading_weight' => 400, 'heading_tracking' => -1,
             'buttons' => 'outline', 'wrap' => 96, 'emphasis' => 'italic', 'header' => 'minimal', 'footer' => 'statement', 'artframe' => 'flat', 'label_style' => 'outline'] + $base),
        'kunstverein' => $preset('Kunstverein · Signalblau', 'Laut und offen: Space Grotesk, Signalblau mit Neongelb, Monospace-Etiketten, geteilte Navigation, große Stufen.',
            ['#2433C9', '#1A26A0', '#FFFFFF', '#E9FF70', '#0A0A14', '#24243A', '#53536A', '#FFFFFF', '#F2F2F7', '#DFDFEA', '#12124A'],
            ['#A3ADFF', '#C5CBFF', '#0B0F3D', '#2D3207', '#F4F4FA', '#D3D3E2', '#A0A0B8', '#0A0A12', '#13131F', '#262638', '#1A1A4A'],
            ['secondary' => '#1A1A4A', 'secondary@dark' => '#E9FF70', 'on_secondary' => '#E9FF70', 'on_secondary@dark' => '#0A0A14', 'secondary_role' => 'sections',
             'font_body' => 'inter', 'font_head' => 'space-grotesk', 'font_mono' => 'jetbrains-mono', 'label_font' => 'mono', 'ratio' => 1.5, 'heading_weight' => 600, 'heading_tracking' => -3.5,
             'buttons' => 'solid', 'space' => 'normal', 'wrap' => 96, 'eyebrow' => 'pill', 'emphasis' => 'marker', 'nav_style' => 'caps',
             'header' => 'split', 'footer' => 'statement', 'footer_bg' => 'accent', 'artframe' => 'flat', 'label_style' => 'pill', 'pagebg' => 'grid'] + $base),
        'archiv' => $preset('Archiv · Grau & Monospace', 'Sachlich wie ein Werkverzeichnis: helles Grau, IBM Plex Mono für Angaben, dichte Listen, Datum in Ziffern, Seitenleiste.',
            ['#2E4234', '#1F2E24', '#FFFFFF', '#D9DAD2', '#121412', '#2C302C', '#545954', '#F1F1EE', '#E6E6E1', '#D2D3CC', '#1F2420'],
            ['#B9C9BC', '#D3DFD5', '#142017', '#2E352F', '#ECEEEA', '#CBCFC9', '#9AA099', '#121412', '#1B1E1B', '#2F332F', '#232823'],
            ['secondary' => '#5B6B5F', 'secondary@dark' => '#9FB2A4', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#121412',
             'font_body' => 'inter', 'font_head' => 'space-grotesk', 'font_mono' => 'plex-mono', 'label_font' => 'mono', 'ratio' => 1.2, 'fs_max' => 17.5, 'heading_weight' => 500, 'heading_tracking' => -1.5,
             'buttons' => 'outline', 'space' => 'normal', 'wrap' => 100, 'eyebrow' => 'line', 'nav_style' => 'plain',
             'header' => 'rail', 'footer' => 'simple', 'footer_bg' => 'surface', 'caption' => 'line', 'date_style' => 'numeric', 'list_density' => 'compact', 'label_style' => 'outline'] + $base),
        'atelier' => $preset('Atelier · Creme & Handwerk', 'Warm und nahbar: Creme, Terrakotta, Fraunces, runde Ecken, Passepartout – für Produzentengalerien, Keramik und Editionen.',
            ['#8E4320', '#723518', '#FFFFFF', '#EBDDBF', '#2A1E14', '#43362A', '#6A5B4C', '#F7F1E6', '#EFE6D6', '#E0D3BD', '#2E2219'],
            ['#F0A882', '#F7C5A9', '#301306', '#4A3820', '#F7EEE2', '#E0D3C3', '#B3A594', '#16110C', '#211A13', '#3A2F24', '#2E241B'],
            ['secondary' => '#4F6B4A', 'secondary@dark' => '#A9C7A2', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#14200F',
             'font_body' => 'instrument-sans', 'font_head' => 'fraunces', 'ratio' => 1.3, 'heading_weight' => 420, 'heading_tracking' => -1.5,
             'radius' => 14, 'buttons' => 'pill', 'images' => 'radius', 'space' => 'normal', 'wrap' => 82, 'eyebrow' => 'dot', 'emphasis' => 'italic', 'nav_style' => 'pill',
             'header' => 'inline', 'footer' => 'columns', 'footer_bg' => 'surface', 'artframe' => 'mat', 'label_style' => 'pill', 'list_density' => 'airy'] + $base),
    ],

    // Dunkle Werte bei „Dunkles Farbschema“ (Klasse has-dark) und Geräte-Einstellung; Vorschau im Editor setzt is-dark
    'dark' => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark', 'force' => 'is-dark'],

    // Musterseite für die Vorschau im Editor: Startseite der Demo (aktuelle Ausstellung, Künstler, Werke)
    'sample' => 'werke',
];
