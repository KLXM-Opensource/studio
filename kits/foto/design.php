<?php
/*
 * Design-Tokens des Kits „foto“ für den Style-Editor (Verwaltung → Design) – siehe Core\Design.
 *
 * Grundlage ist die breakpointlose Architektur von „fluid“: Jeder Token wird eine CSS-Variable (--f-…) oder eine Klasse am
 * <html> (hdr-inline, cap-hover, gs-hover, mat-white …). Typografie und Abstände sind fließend (clamp, Utopia-Prinzip).
 * Für Fotografinnen und Fotografen kommt die Gruppe „Bilder & Galerien“ dazu: Bildabstand, Ecken, Bildunterschriften,
 * Lightbox, Schwarzweiß, Passepartout, Breite der Galerien, Bildwirkung beim Zeigen.
 * Voreinstellungen sind mit tools/contrast.php auf WCAG 2.2 AA geprüft (hell und dunkel):
 *   php kits/foto/tools/contrast.php
 * Standardwerte müssen mit :root in assets/css/_tokens.css übereinstimmen (Voreinstellung „Weiß & still“) – ohne
 * Abweichung liefert der Kern keine Design-Datei aus.
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

// Gemeinsame Werte der Voreinstellungen (= „Weiß & still“, werden je Vorlage überschrieben)
$base = [
    'secondary' => '#6B6B6B', 'secondary@dark' => '#A6A6A6', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#111111',
    'font_body' => 'inter', 'font_head' => 'inter', 'font_mono' => 'system-mono', 'label_font' => 'body',
    'fs_min' => 16, 'fs_max' => 18, 'ratio' => 1.2, 'vw_min' => 360, 'vw_max' => 1440,
    'heading_weight' => 450, 'heading_tracking' => -1.5,
    'radius' => 0, 'buttons' => 'outline', 'cards' => 'flat', 'shadow' => 'none', 'density' => 'normal', 'space' => 'airy', 'wrap' => 80,
    'secondary_role' => 'sections', 'heading_case' => 'normal', 'emphasis' => 'italic', 'links' => 'underline',
    'hover' => 'none', 'icons' => 'plain', 'icon_pos' => 'top', 'dividers' => 'none', 'motion_style' => 'fade',
    'eyebrow' => 'plain', 'header' => 'inline', 'header_sticky' => true, 'header_bg' => 'page', 'nav_style' => 'plain', 'topbar' => 'off',
    'footer' => 'simple', 'footer_bg' => 'page', 'logo_style' => 'text',
    // Bilder & Galerien
    'img_gap' => 'medium', 'images' => 'sharp', 'captions' => 'below', 'caption_style' => 'plain', 'lightbox' => 'dark',
    'grayscale' => 'off', 'mat' => 'none', 'gallery_width' => 'wide', 'img_hover' => 'none',
    'motion' => true, 'dark' => true,
];

return [
    'groups' => [
        ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
            $color('accent', 'Akzent', '--f-a', '#1A1A1A', '#F0F0EE', ['with' => 'background', 'min' => 4.5],
                'Links, Buttons und Markierungen. Für Fotografie meist zurückhaltend – die Bilder sollen wirken.'),
            $color('accent_strong', 'Akzent kräftig', '--f-a-strong', '#000000', '#FFFFFF', ['with' => 'background', 'min' => 4.5],
                'Hover-Zustand; hell etwas dunkler als der Akzent, dunkel etwas heller.'),
            $color('on_accent', 'Schrift auf Akzent', '--f-a-on', '#FFFFFF', '#111111', ['with' => 'accent', 'min' => 4.5],
                'Text auf Buttons und im Abschnitt „Akzentfarbe“.'),
            $color('highlight', 'Hervorhebung (Flächen, Marker)', '--f-a2', '#EDEDEA', '#2A2A28', ['with' => 'ink', 'min' => 4.5],
                'Markierungen, Etiketten und die Betonung „Textmarker“ – Überschriftenfarbe muss darauf lesbar sein.'),
            $color('secondary', 'Zweite Markenfarbe', '--f-b', '#6B6B6B', '#A6A6A6', ['with' => 'background', 'min' => 3],
                'Abschnitte „Zweite Markenfarbe“ und – je nach Einstellung unten – Symbole, Dachzeilen-Marken und zweite Buttons.'),
            $color('on_secondary', 'Schrift auf zweiter Markenfarbe', '--f-b-on', '#FFFFFF', '#111111', ['with' => 'secondary', 'min' => 4.5]),
            $color('ink', 'Überschriften', '--f-ink', '#111111', '#F4F4F2', ['with' => 'background', 'min' => 7]),
            $color('text', 'Fließtext', '--f-text', '#2E2E2E', '#D4D4D0', ['with' => 'background', 'min' => 4.5]),
            $color('muted', 'Nebentext und Bildunterschriften', '--f-muted', '#5E5E5E', '#A3A3A0', ['with' => 'surface', 'min' => 4.5],
                'Bildunterschriften, Jahreszahlen, Metadaten – auch auf getönten Flächen lesbar.'),
            $color('background', 'Hintergrund', '--f-bg', '#FFFFFF', '#0F0F0F'),
            $color('surface', 'Getönte Fläche', '--f-surface', '#F5F5F3', '#191919', ['with' => 'text', 'min' => 4.5],
                'Abschnitte „Getönt“, Platzhalter beim Laden der Bilder, Karten im Stil „Fläche“.'),
            $color('line', 'Linien', '--f-line', '#E6E6E3', '#2A2A2A', null, 'Trennlinien und Rahmen (dekorativ).'),
            $color('dark_section', 'Dunkle Abschnitte', '--f-dark', '#141414', '#1E1E1E', ['with' => '#FFFFFF', 'min' => 7, 'dark_with' => '#FFFFFF'],
                'Hintergrund der Abschnitte „Dunkel“ (Schrift weiß) – ideal für Bildstrecken mit viel Licht.'),
            ['name' => 'secondary_role', 'label' => 'Zweite Markenfarbe einsetzen für', 'type' => 'choice', 'class' => 'sec2-{value}', 'default' => 'sections',
                'options' => ['sections' => 'Nur Abschnitte in dieser Farbe', 'details' => 'Auch Symbole und Dachzeilen-Marken', 'buttons' => 'Auch zweite Buttons', 'all' => 'Symbole, Marken und zweite Buttons']],
        ]],
        // ---------------------------------------------------------------- Bilder & Galerien (eigene Gruppe des Kits „foto“)
        ['id' => 'bilder', 'label' => 'Bilder & Galerien', 'tokens' => [
            ['name' => 'img_gap', 'label' => 'Abstand zwischen den Bildern', 'type' => 'choice', 'var' => '--f-gap-img', 'class' => 'gap-{value}', 'default' => 'medium',
                'options' => ['none' => 'Kein Abstand (Bild an Bild)', 'small' => 'Klein', 'medium' => 'Mittel', 'large' => 'Groß (viel Weißraum)'],
                'values' => ['none' => '0px', 'small' => 'clamp(.25rem,.15rem + .35vw,.5rem)', 'medium' => 'clamp(.5rem,.25rem + 1vw,1.25rem)', 'large' => 'clamp(1rem,.4rem + 2.6vw,3rem)'],
                'help' => 'Gilt für Fotostrecken, Serien-Übersichten und Bühne. Der Abstand wächst fließend mit dem Fenster.'],
            ['name' => 'images', 'label' => 'Ecken der Bilder', 'type' => 'choice', 'var' => '--f-img-r', 'class' => 'img-{value}', 'default' => 'sharp',
                'options' => ['sharp' => 'Eckig (klassisch)', 'soft' => 'Leicht gerundet', 'round' => 'Deutlich gerundet'],
                'values' => ['sharp' => '0px', 'soft' => '.375rem', 'round' => '1.25rem']],
            ['name' => 'captions', 'label' => 'Bildunterschriften', 'type' => 'choice', 'class' => 'cap-{value}', 'default' => 'below',
                'options' => ['below' => 'Unter dem Bild', 'hover' => 'Beim Zeigen auf dem Bild (Touch: darunter)', 'overlay' => 'Immer auf dem Bild (mit Verlauf)', 'hidden' => 'Ausgeblendet (nur in der Lightbox und für Screenreader)'],
                'help' => 'Bildunterschriften pflegen Sie je Bild im Block. Ausgeblendete Texte bleiben für Screenreader und in der Lightbox erhalten.'],
            ['name' => 'caption_style', 'label' => 'Schrift der Bildunterschriften', 'type' => 'choice', 'class' => 'capst-{value}', 'default' => 'plain',
                'options' => ['plain' => 'Wie Fließtext, klein', 'mono' => 'Monospace (wie Kameradaten)', 'italic' => 'Kursiv (poetisch)', 'caps' => 'Versalien, gesperrt']],
            ['name' => 'lightbox', 'label' => 'Hintergrund der Lightbox', 'type' => 'choice', 'class' => 'lb-{value}', 'default' => 'dark',
                'options' => ['dark' => 'Dunkel', 'light' => 'Hell (wie die Seite)', 'blur' => 'Verschwommene Seite'],
                'help' => 'Die Lightbox öffnet ein Bild groß – mit Pfeiltasten, Wischen, Escape und Bildunterschrift.'],
            ['name' => 'grayscale', 'label' => 'Schwarzweiß', 'type' => 'choice', 'class' => 'gs-{value}', 'default' => 'off',
                'options' => ['off' => 'Aus (Bilder wie hochgeladen)', 'hover' => 'Schwarzweiß, farbig beim Zeigen', 'always' => 'Immer schwarzweiß'],
                'help' => 'Wirkt nur in der Darstellung – die Originale in der Mediathek bleiben unverändert.'],
            ['name' => 'mat', 'label' => 'Rahmen / Passepartout', 'type' => 'choice', 'class' => 'mat-{value}', 'default' => 'none',
                'options' => ['none' => 'Ohne', 'line' => 'Feine Linie', 'white' => 'Weißes Passepartout (wie gerahmt)']],
            ['name' => 'gallery_width', 'label' => 'Breite der Galerien', 'type' => 'choice', 'class' => 'gw-{value}', 'default' => 'wide',
                'options' => ['contained' => 'Wie der Text (Inhaltsbreite)', 'wide' => 'Breiter als der Text', 'full' => 'Randlos (volle Fensterbreite)'],
                'help' => 'Standard für Fotostrecken und Serien-Übersichten; jeder Block kann eine eigene Breite wählen.'],
            ['name' => 'img_hover', 'label' => 'Verlinkte Bilder beim Zeigen', 'type' => 'choice', 'class' => 'ih-{value}', 'default' => 'none',
                'options' => ['none' => 'Ruhig (nur Mauszeiger)', 'zoom' => 'Leicht vergrößern', 'fade' => 'Leicht aufhellen']],
        ]],
        ['id' => 'typo', 'label' => 'Typografie', 'tokens' => [
            ['name' => 'font_body', 'label' => 'Schrift Fließtext', 'type' => 'font', 'var' => '--f-font', 'default' => 'inter'],
            ['name' => 'font_head', 'label' => 'Schrift Überschriften', 'type' => 'font', 'var' => '--f-font-head', 'default' => 'inter'],
            ['name' => 'font_mono', 'label' => 'Schrift für Code und Kennzeichnungen', 'type' => 'font', 'var' => '--f-font-mono', 'default' => 'system-mono',
                'help' => 'Für Code, Bildunterschriften im Stil „Monospace“ und – wenn unten gewählt – Dachzeilen. Die Datei lädt nur, wenn sie gebraucht wird.'],
            ['name' => 'label_font', 'label' => 'Dachzeilen und Etiketten', 'type' => 'choice', 'var' => '--f-font-label', 'default' => 'body',
                'options' => ['body' => 'Wie Fließtext', 'head' => 'Wie Überschriften', 'mono' => 'Monospace (technisch)'],
                'values' => ['body' => 'var(--f-font)', 'head' => 'var(--f-font-head)', 'mono' => 'var(--f-font-mono)']],
            ['name' => 'fs_min', 'label' => 'Grundschrift auf kleinen Bildschirmen (px)', 'type' => 'range', 'var' => '--f-fs-min', 'unit' => '',
                'min' => 14, 'max' => 19, 'step' => 0.5, 'default' => 16, 'help' => 'Bezogen auf die Standardgröße des Browsers (16 px); Besucher können weiter zoomen.'],
            ['name' => 'fs_max', 'label' => 'Grundschrift auf großen Bildschirmen (px)', 'type' => 'range', 'var' => '--f-fs-max', 'unit' => '',
                'min' => 15, 'max' => 23, 'step' => 0.5, 'default' => 18],
            ['name' => 'ratio', 'label' => 'Verhältnis der Schriftstufen', 'type' => 'range', 'var' => '--f-ratio', 'unit' => '',
                'min' => 1.125, 'max' => 1.6, 'step' => 0.025, 'default' => 1.2,
                'help' => '1,125 = ruhig · 1,2 = zurückhaltend (Standard für Fotografie) · 1,333 = editorial · 1,5+ = plakativ. Auf kleinen Bildschirmen automatisch flacher.'],
            ['name' => 'vw_min', 'label' => 'Fließend ab Bildschirmbreite (px)', 'type' => 'range', 'var' => '--f-vw-min', 'unit' => '',
                'min' => 320, 'max' => 480, 'step' => 10, 'default' => 360, 'help' => 'Unterhalb gelten die kleinen Werte.'],
            ['name' => 'vw_max', 'label' => 'Fließend bis Bildschirmbreite (px)', 'type' => 'range', 'var' => '--f-vw-max', 'unit' => '',
                'min' => 960, 'max' => 1800, 'step' => 20, 'default' => 1440, 'help' => 'Oberhalb gelten die großen Werte.'],
            ['name' => 'heading_weight', 'label' => 'Stärke der Überschriften', 'type' => 'range', 'var' => '--f-hw', 'unit' => '',
                'min' => 300, 'max' => 900, 'step' => 50, 'default' => 450, 'help' => 'Stufenlos bei variablen Schriften (Inter, Manrope, Fraunces …). Leichte Stärken wirken ruhig und hochwertig.'],
            ['name' => 'heading_case', 'label' => 'Schreibweise der Überschriften', 'type' => 'choice', 'class' => 'hcase-{value}', 'default' => 'normal',
                'options' => ['normal' => 'Normal', 'upper' => 'VERSALIEN (z. B. mit schmaler Schrift)', 'smallcaps' => 'Kapitälchen']],
            ['name' => 'emphasis', 'label' => 'Betonung *Wort* in Überschriften', 'type' => 'choice', 'class' => 'em-{value}', 'default' => 'italic',
                'options' => ['italic' => 'Kursiv', 'accent' => 'Akzentfarbe (bei Serifen kursiv)', 'bold' => 'Fett', 'marker' => 'Textmarker (Hervorhebungsfarbe)', 'underline' => 'Unterstrichen in Akzentfarbe']],
            ['name' => 'links', 'label' => 'Links im Fließtext', 'type' => 'choice', 'class' => 'ln-{value}', 'default' => 'underline',
                'options' => ['underline' => 'Unterstrichen', 'accent' => 'Fett in Akzentfarbe, unterstrichen beim Zeigen', 'marker' => 'Getönte Linie, füllt sich beim Zeigen']],
            ['name' => 'heading_tracking', 'label' => 'Laufweite der Überschriften (1/100 em)', 'type' => 'range', 'var' => '--f-track', 'unit' => '',
                'min' => -6, 'max' => 6, 'step' => 0.5, 'default' => -1.5, 'help' => 'Negativ = enger (große Grotesk-Überschriften), 0 = normal (Serifen), positiv = gesperrt (Versalien).'],
        ]],
        ['id' => 'form', 'label' => 'Form & Raum', 'tokens' => [
            ['name' => 'radius', 'label' => 'Eckenradius (Buttons, Karten, Felder)', 'type' => 'range', 'var' => '--f-radius', 'unit' => 'px', 'min' => 0, 'max' => 32, 'step' => 1, 'default' => 0],
            ['name' => 'buttons', 'label' => 'Buttons', 'type' => 'choice', 'class' => 'btn-{value}', 'default' => 'outline', 'preview' => 'radius',
                'options' => ['solid' => 'Gefüllt', 'pill' => 'Gefüllt, rund (Pille)', 'outline' => 'Kontur', 'soft' => 'Getönt', 'sharp' => 'Eckig mit Pfeil'],
                'values' => ['solid' => '8px', 'pill' => '999px', 'outline' => '8px', 'soft' => '12px', 'sharp' => '0']],
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
            ['name' => 'wrap', 'label' => 'Maximale Inhaltsbreite (rem)', 'type' => 'range', 'var' => '--f-wrap', 'unit' => 'rem', 'min' => 56, 'max' => 100, 'step' => 2, 'default' => 80,
                'help' => '1 rem = 16 px. Gilt für Texte und Kopfbereich; Galerien „breiter als der Text“ gehen darüber hinaus.'],
            ['name' => 'eyebrow', 'label' => 'Dachzeilen', 'type' => 'choice', 'class' => 'eb-{value}', 'default' => 'plain',
                'options' => ['plain' => 'Schlicht', 'line' => 'Mit Strich', 'pill' => 'Als Etikett', 'dot' => 'Mit Punkt']],
            ['name' => 'hover', 'label' => 'Verlinkte Karten beim Zeigen', 'type' => 'choice', 'class' => 'hov-{value}', 'default' => 'none',
                'options' => ['none' => 'Ruhig (nur Mauszeiger)', 'lift' => 'Leicht anheben', 'glow' => 'Rahmen in Akzentfarbe']],
            ['name' => 'icons', 'label' => 'Symbole (z. B. Leistungen)', 'type' => 'choice', 'class' => 'ic-{value}', 'default' => 'plain',
                'options' => ['plain' => 'Ohne Fläche', 'soft' => 'Getönte Fläche', 'circle' => 'Kreis', 'outline' => 'Kontur']],
            ['name' => 'icon_pos', 'label' => 'Anordnung der Symbole', 'type' => 'choice', 'class' => 'icp-{value}', 'default' => 'top',
                'options' => ['top' => 'Über dem Titel', 'inline' => 'In einer Zeile mit dem Titel', 'side' => 'Links neben dem Text']],
            ['name' => 'dividers', 'label' => 'Übergang zwischen Abschnitten', 'type' => 'choice', 'class' => 'div-{value}', 'default' => 'none',
                'options' => ['none' => 'Gerade Kante', 'wave' => 'Welle', 'slant' => 'Schräg', 'curve' => 'Bogen', 'zigzag' => 'Zickzack'],
                'help' => 'Sichtbar, wo zwei Abschnitte mit verschiedenen Hintergründen aufeinandertreffen.'],
        ]],
        ['id' => 'navigation', 'label' => 'Kopf & Fuß', 'tokens' => [
            ['name' => 'header', 'label' => 'Navigation', 'type' => 'choice', 'class' => 'hdr-{value}', 'default' => 'inline', 'preview' => 'nav',
                'thumbs' => ['inline' => 'left', 'centered' => 'center', 'split' => 'split', 'floating' => 'floating', 'rail' => 'sidebar', 'minimal' => 'left'],
                'options' => [
                    'inline' => 'Leiste oben – Name links, Menü rechts',
                    'minimal' => 'Minimal – nur Name und Menü-Schaltfläche',
                    'rail' => 'Seitenleiste links (breite Fenster)',
                    'centered' => 'Zentriert – Name über dem Menü',
                    'split' => 'Geteilt – Menü in der Mitte',
                    'floating' => 'Schwebend – abgerundete Leiste',
                ],
                'help' => 'Unterseiten (z. B. Serien unter „Arbeiten“) erscheinen als Aufklappmenü. Passt das Menü nicht in die Leiste, öffnet eine Menü-Schaltfläche ein Seitenblatt – ganz ohne feste Bildschirmbreiten.'],
            ['name' => 'logo_style', 'label' => 'Marke oben links', 'type' => 'choice', 'class' => 'logo-{value}', 'default' => 'text',
                'options' => ['text' => 'Wortmarke (Name als Schrift)', 'image' => 'Logo-Bild (falls unter „Website“ hinterlegt)'],
                'help' => 'Die Wortmarke nutzt den Kurznamen und die Schrift der Überschriften.'],
            ['name' => 'header_sticky', 'label' => 'Kopfbereich beim Scrollen sichtbar halten', 'type' => 'bool', 'class' => 'hdr-sticky', 'default' => true],
            ['name' => 'header_bg', 'label' => 'Hintergrund des Kopfbereichs', 'type' => 'choice', 'class' => 'hbg-{value}', 'default' => 'page',
                'options' => ['page' => 'Wie die Seite (leicht durchscheinend)', 'surface' => 'Getönte Fläche', 'accent' => 'Akzentfarbe', 'secondary' => 'Zweite Markenfarbe', 'dark' => 'Dunkel']],
            ['name' => 'nav_style', 'label' => 'Menüpunkte', 'type' => 'choice', 'class' => 'nl-{value}', 'default' => 'plain',
                'options' => ['plain' => 'Schlicht (Fläche beim Zeigen)', 'underline' => 'Unterstrichen', 'pill' => 'Pille (aktiver Punkt hinterlegt)', 'caps' => 'Versalien, gesperrt']],
            ['name' => 'topbar', 'label' => 'Infoleiste über dem Kopfbereich', 'type' => 'choice', 'class' => 'meta-{value}', 'default' => 'off',
                'options' => ['off' => 'Aus', 'dark' => 'Dunkel', 'accent' => 'Akzentfarbe', 'secondary' => 'Zweite Markenfarbe', 'surface' => 'Getönt'],
                'help' => 'Telefon, E-Mail und Social Media aus Website → Stammdaten, dazu ein kurzer Text (Website → „Text der Infoleiste“).'],
            ['name' => 'footer', 'label' => 'Fußbereich', 'type' => 'choice', 'class' => 'ft-{value}', 'default' => 'simple',
                'options' => ['simple' => 'Schlicht in einer Zeile', 'columns' => 'Spalten (Kontakt, Seiten, Social)', 'centered' => 'Zentriert (Name, Seiten, Social untereinander)', 'statement' => 'Großer Schriftzug mit Handlungsaufruf']],
            ['name' => 'footer_bg', 'label' => 'Hintergrund des Fußbereichs', 'type' => 'choice', 'class' => 'fbg-{value}', 'default' => 'page',
                'options' => ['page' => 'Wie die Seite', 'surface' => 'Getönte Fläche', 'dark' => 'Dunkel', 'accent' => 'Akzentfarbe', 'secondary' => 'Zweite Markenfarbe']],
        ]],
        // Kopfbereich-Aktionen (Core\HeaderActions) – Standard des Kits: ruhig – Handlungsaufruf als letzter Menüpunkt, Suche als Lupe
        \Core\HeaderActions::designGroup(['ha_cta_style' => 'navitem', 'ha_search' => 'popover']),
        ['id' => 'modus', 'label' => 'Bewegung & Farbschema', 'tokens' => [
            ['name' => 'motion', 'label' => 'Dezente Animationen (Einblenden, Überblenden der Bühne)', 'type' => 'bool', 'class' => 'has-motion', 'default' => true,
                'help' => 'Besucher mit „Bewegung reduzieren“ sehen nie Animationen – unabhängig von dieser Einstellung.'],
            ['name' => 'motion_style', 'label' => 'Art des Einblendens', 'type' => 'choice', 'class' => 'mo-{value}', 'default' => 'fade',
                'options' => ['fade' => 'Nur Blende', 'rise' => 'Aufsteigen', 'scale' => 'Leicht wachsen', 'blur' => 'Aus der Unschärfe']],
            ['name' => 'dark', 'label' => 'Dunkles Farbschema, wenn im Gerät der Besucher eingestellt', 'type' => 'bool', 'class' => 'has-dark', 'default' => true],
        ]],
    ],

    // Selbst gehostete Schriften (kits/foto/build.mjs erzeugt css/font-*.css) – Auswahl für Fotografie: ruhige Grotesk,
    // Serifen für Editorial, eine schmale Schrift für Reportage, Monospace für Bildunterschriften
    'fonts' => [
        'inter' => ['label' => 'Inter (Grotesk, neutral · variabel)', 'stack' => 'Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif', 'css' => 'css/font-inter.css'],
        'manrope' => ['label' => 'Manrope (geometrisch, modern · variabel)', 'stack' => 'Manrope,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'css' => 'css/font-manrope.css'],
        'dm-sans' => ['label' => 'DM Sans (geometrisch, rund · variabel)', 'stack' => '"DM Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'css' => 'css/font-dm-sans.css'],
        'instrument-sans' => ['label' => 'Instrument Sans (Grotesk, freundlich · variabel)', 'stack' => '"Instrument Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'css' => 'css/font-instrument-sans.css'],
        'space-grotesk' => ['label' => 'Space Grotesk (technisch · variabel)', 'stack' => '"Space Grotesk",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'css' => 'css/font-space-grotesk.css'],
        'roboto-condensed' => ['label' => 'Roboto Condensed (schmal, für Reportage-Überschriften · variabel)', 'stack' => '"Roboto Condensed","Arial Narrow",ui-sans-serif,system-ui,sans-serif', 'css' => 'css/font-roboto-condensed.css'],
        'fraunces' => ['label' => 'Fraunces (Serifen, weich, optische Größen · variabel)', 'stack' => 'Fraunces,ui-serif,Georgia,Cambria,serif', 'css' => 'css/font-fraunces.css'],
        'newsreader' => ['label' => 'Newsreader (Serifen, editorial · variabel)', 'stack' => 'Newsreader,ui-serif,Georgia,Cambria,serif', 'css' => 'css/font-newsreader.css'],
        'instrument-serif' => ['label' => 'Instrument Serif (Display-Serife, nur große Überschriften)', 'stack' => '"Instrument Serif",ui-serif,Georgia,Cambria,serif', 'css' => 'css/font-instrument-serif.css'],
        'jetbrains-mono' => ['label' => 'JetBrains Mono (Monospace · variabel)', 'stack' => '"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace', 'css' => 'css/font-jetbrains-mono.css'],
        'system' => ['label' => 'Systemschrift (ohne Download)', 'stack' => 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'],
        'system-mono' => ['label' => 'System-Monospace (ohne Download)', 'stack' => 'ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace'],
    ],

    // Sechs Vorlagen für Fotografie – alle hell und dunkel AA-geprüft (tools/contrast.php)
    'presets' => [
        'still' => $preset('Weiß & still', 'Minimal und ruhig: weißer Grund, schwarze Schrift, Inter in leichter Stärke, eckige Bilder, viel Weißraum – die Bilder tragen das Design.',
            ['#1A1A1A', '#000000', '#FFFFFF', '#EDEDEA', '#111111', '#2E2E2E', '#5E5E5E', '#FFFFFF', '#F5F5F3', '#E6E6E3', '#141414'],
            ['#F0F0EE', '#FFFFFF', '#111111', '#2A2A28', '#F4F4F2', '#D4D4D0', '#A3A3A0', '#0F0F0F', '#191919', '#2A2A2A', '#1E1E1E'],
            $base),
        'dunkelkammer' => $preset('Dunkelkammer', 'Dunkel von Anfang an: fast schwarzer Grund, warmes Rotlicht als Akzent, Manrope, minimale Navigation, Bildunterschriften auf dem Bild, randlose Galerien.',
            ['#FF8F7A', '#FFB3A6', '#1A0503', '#3A1712', '#F2F0EC', '#CFCCC6', '#9C9891', '#0C0C0C', '#161615', '#2A2928', '#1C1B1A'],
            ['#FF8F7A', '#FFB3A6', '#1A0503', '#3A1712', '#F2F0EC', '#CFCCC6', '#9C9891', '#080808', '#131312', '#262524', '#1C1B1A'],
            ['secondary' => '#C9B79C', 'secondary@dark' => '#C9B79C', 'on_secondary' => '#14110C', 'on_secondary@dark' => '#14110C',
             'font_body' => 'manrope', 'font_head' => 'manrope', 'heading_weight' => 400, 'heading_tracking' => -1, 'ratio' => 1.25,
             'header' => 'minimal', 'header_sticky' => false, 'captions' => 'overlay', 'lightbox' => 'dark', 'gallery_width' => 'full', 'img_gap' => 'small',
             'img_hover' => 'fade', 'footer' => 'centered', 'eyebrow' => 'plain'] + $base),
        'galerie' => $preset('Galerie-Grau', 'Wie in einer Ausstellung: warmes Grau, weiße Passepartouts, Instrument Sans, zentrierter Kopf, Bildunterschriften beim Zeigen, großer Bildabstand.',
            ['#4A3F35', '#2E2620', '#FFFFFF', '#D9D3C9', '#1C1915', '#34302B', '#5C564E', '#E9E6E1', '#DEDAD3', '#CFCAC2', '#262320'],
            ['#D8CBB8', '#EADFCF', '#1C1915', '#3A342D', '#F1EEE9', '#D3CEC6', '#A39D94', '#1A1816', '#23211E', '#36322D', '#2A2724'],
            ['secondary' => '#6E6458', 'secondary@dark' => '#B9AE9F', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#1C1915',
             'font_body' => 'instrument-sans', 'font_head' => 'instrument-sans', 'heading_weight' => 500, 'heading_tracking' => -1,
             'header' => 'centered', 'nav_style' => 'underline', 'captions' => 'hover', 'mat' => 'white', 'img_gap' => 'large', 'gallery_width' => 'contained',
             'lightbox' => 'light', 'footer' => 'centered', 'footer_bg' => 'surface'] + $base),
        'editorial' => $preset('Editorial', 'Wie ein Bildband: Newsreader-Überschriften, kursive Betonung, Tiefblau als Akzent, Bildunterschriften kursiv, geteilter Kopf.',
            ['#1D3557', '#12233B', '#FFFFFF', '#E9E4D8', '#121212', '#2B2B2B', '#5A5A57', '#FBFAF7', '#F2F0EA', '#E2DFD6', '#15202E'],
            ['#A9C1E3', '#C9D8EE', '#0E1A2B', '#2C2A24', '#F3F1EC', '#D5D2CB', '#A19E97', '#121212', '#1B1B1A', '#2E2D2B', '#1D2733'],
            ['secondary' => '#7A5C2E', 'secondary@dark' => '#D9B97F', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#1C1408',
             'font_body' => 'inter', 'font_head' => 'newsreader', 'heading_weight' => 400, 'heading_tracking' => -0.5, 'ratio' => 1.333, 'fs_max' => 19,
             'emphasis' => 'italic', 'caption_style' => 'italic', 'header' => 'split', 'nav_style' => 'underline', 'eyebrow' => 'line',
             'gallery_width' => 'wide', 'img_gap' => 'medium', 'footer' => 'statement', 'wrap' => 76] + $base),
        'reportage' => $preset('Reportage', 'Direkt und kräftig: Roboto Condensed in Versalien, Signalrot, Schwarzweiß bis zum Zeigen, kleiner Bildabstand, randlose Strecken.',
            ['#C8102E', '#A00D25', '#FFFFFF', '#FFE2D6', '#0A0A0A', '#262626', '#595959', '#FFFFFF', '#F3F3F3', '#E0E0E0', '#111111'],
            ['#FF8A8A', '#FFB0B0', '#2A0508', '#4A1B12', '#F5F5F5', '#D9D9D9', '#A3A3A3', '#0B0B0B', '#161616', '#2C2C2C', '#1F1F1F'],
            ['secondary' => '#262626', 'secondary@dark' => '#D9D9D9', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#111111',
             'font_body' => 'inter', 'font_head' => 'roboto-condensed', 'font_mono' => 'jetbrains-mono', 'label_font' => 'mono',
             'heading_weight' => 700, 'heading_tracking' => 0, 'heading_case' => 'upper', 'ratio' => 1.333, 'emphasis' => 'accent',
             'buttons' => 'sharp', 'nav_style' => 'caps', 'grayscale' => 'hover', 'img_gap' => 'small', 'gallery_width' => 'full', 'caption_style' => 'mono',
             'space' => 'normal', 'footer' => 'columns', 'footer_bg' => 'dark', 'header_bg' => 'page'] + $base),
        'analog' => $preset('Analog', 'Wie Film und Fotopapier: Creme und Rost, Instrument Serif mit Instrument Sans, feine Bildrahmen, leicht gerundete Ecken, Kameradaten-Schrift, Seitenleiste.',
            ['#8E3B1C', '#6F2D14', '#FFFFFF', '#E6D9BF', '#1F1A14', '#3A332A', '#645A4E', '#F3EEE3', '#E9E2D3', '#DDD3C0', '#2A231C'],
            ['#E8A07E', '#F2BEA3', '#2A1206', '#3B3122', '#F4EEE3', '#DCD3C4', '#AA9F8E', '#17130F', '#201B16', '#362E25', '#29211A'],
            ['secondary' => '#4F6B4A', 'secondary@dark' => '#A9C4A2', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#14200F',
             'font_body' => 'instrument-sans', 'font_head' => 'instrument-serif', 'font_mono' => 'jetbrains-mono', 'heading_weight' => 400, 'heading_tracking' => -0.5, 'ratio' => 1.4,
             'images' => 'soft', 'mat' => 'line', 'caption_style' => 'mono', 'header' => 'rail', 'radius' => 4, 'buttons' => 'outline',
             'gallery_width' => 'contained', 'img_hover' => 'zoom', 'footer' => 'simple'] + $base),
    ],

    // Dunkle Werte bei „Dunkles Farbschema“ (Klasse has-dark) und Geräte-Einstellung; Vorschau im Editor setzt is-dark
    'dark' => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark', 'force' => 'is-dark'],

    // Musterseite für die Vorschau im Editor (Seitenbaum „Arbeiten“, siehe tools/demo-content.php)
    'sample' => 'arbeiten',
];
