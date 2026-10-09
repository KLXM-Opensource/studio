<?php
/*
 * Design-Tokens des Themes „editorial“ für den Style-Editor (Verwaltung → Design) – siehe Core\Design.
 *
 * Jeder Token landet als CSS-Variable (--e-…) oder Klasse am <html> (head-masthead, grid-asym, img-duotone, kick-mono …).
 * Das Theme-CSS nutzt ausschließlich diese Variablen; hell und dunkel („Nachtausgabe“) haben je eigene Farbwerte.
 * Voreinstellungen (presets) sind mit tools/contrast.php auf WCAG 2.2 AA geprüft (hell und dunkel):
 *   php kits/editorial/tools/contrast.php
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
            $color('accent', 'Akzent', '--e-a', '#B42318', '#FF8A7A', ['with' => 'background', 'min' => 4.5],
                'Dachzeilen, Links, Markierungen und Abschnitte „Akzentfarbe“. Muss auf dem Papierton als Linkfarbe lesbar sein.'),
            $color('accent_strong', 'Akzent kräftig', '--e-a-strong', '#8E1B12', '#FFB3A8', ['with' => 'background', 'min' => 4.5],
                'Hover-Zustand von Buttons und Links; hell etwas dunkler, dunkel etwas heller als der Akzent.'),
            $color('on_accent', 'Schrift auf Akzent', '--e-a-on', '#FFFFFF', '#2A0703', ['with' => 'accent', 'min' => 4.5],
                'Text auf Buttons und im Abschnitt „Akzentfarbe“.'),
            $color('ink', 'Überschriften (Druckschwarz)', '--e-ink', '#111111', '#F4F1EA', ['with' => 'background', 'min' => 7]),
            $color('text', 'Fließtext', '--e-text', '#262522', '#DAD6CD', ['with' => 'background', 'min' => 7],
                'Lange Texte: mindestens 7:1 (AAA) für entspanntes Lesen.'),
            $color('muted', 'Nebentext', '--e-muted', '#5C5850', '#A8A398', ['with' => 'surface', 'min' => 4.5],
                'Vorspann, Bildunterschriften, Autorzeile, Datum – auch auf getönten Flächen lesbar.'),
            $color('background', 'Papier (Hintergrund)', '--e-bg', '#FBFAF7', '#12110F'),
            $color('surface', 'Getönte Fläche', '--e-surface', '#F2EFE8', '#1B1A17', ['with' => 'text', 'min' => 4.5],
                'Kästen, „Kurz notiert“, Abschnitte „Getönt“ und Fußbereich.'),
            $color('line', 'Linien', '--e-line', '#DDD8CD', '#302E2A', null, 'Spalten- und Trennlinien (dekorativ).'),
            $color('dark_section', 'Nachtausgabe (dunkle Abschnitte)', '--e-night', '#15130F', '#080807', ['with' => '#FFFFFF', 'min' => 7, 'dark_with' => 'text'],
                'Hintergrund der Abschnitte „Nachtausgabe“ (Schrift hell).'),
        ]],
        ['id' => 'typo', 'label' => 'Typografie', 'tokens' => [
            ['name' => 'font_display', 'label' => 'Schrift Überschriften (Display)', 'type' => 'font', 'var' => '--e-font-display', 'default' => 'newsreader',
                'help' => 'Schlagzeilen, Titelkopf, Zitate, große Zahlen. Serifen wirken redaktionell; Groteskschriften sachlich.'],
            ['name' => 'font_body', 'label' => 'Schrift Fließtext', 'type' => 'font', 'var' => '--e-font', 'default' => 'source-sans',
                'help' => 'Lesetexte, Menü, Formulare. Humanistische Grotesk oder Textserife.'],
            ['name' => 'font_mono', 'label' => 'Schrift Auszeichnungen (Mono)', 'type' => 'font', 'var' => '--e-font-mono', 'default' => 'plex-mono',
                'help' => 'Datumszeile, Rubriken, Nummern, Kennzahlen-Beschriftungen.'],
            ['name' => 'base_size', 'label' => 'Grundschriftgröße Fließtext (px)', 'type' => 'range', 'var' => '--e-fs', 'unit' => '',
                'min' => 16, 'max' => 20, 'step' => 0.5, 'default' => 18, 'help' => 'Bezogen auf die Standardgröße des Browsers (16 px); Besucher können weiter zoomen.'],
            ['name' => 'scale', 'label' => 'Typografische Skala', 'type' => 'choice', 'var' => '--e-ratio', 'default' => 'magazin',
                'options' => ['ruhig' => 'Ruhig (1,2 – Verwaltung, Bildung)', 'magazin' => 'Magazin (1,25)', 'kraeftig' => 'Kräftig (1,333)', 'plakat' => 'Plakat (1,414 – große Schlagzeilen)'],
                'values' => ['ruhig' => '1.2', 'magazin' => '1.25', 'kraeftig' => '1.333', 'plakat' => '1.414'],
                'help' => 'Verhältnis zwischen den Überschriften-Stufen. Auf kleinen Bildschirmen automatisch gedämpft.'],
            ['name' => 'heading_weight', 'label' => 'Stärke der Überschriften', 'type' => 'choice', 'var' => '--e-hw', 'default' => '600',
                'options' => ['400' => 'Normal', '500' => 'Mittel', '600' => 'Halbfett', '700' => 'Fett', '800' => 'Extrafett'],
                'values' => ['400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800'],
                'help' => 'DM Serif Display und Instrument Serif gibt es nur in „Normal“.'],
            ['name' => 'heading_tracking', 'label' => 'Laufweite der Überschriften', 'type' => 'choice', 'var' => '--e-track', 'default' => 'tight',
                'options' => ['tight' => 'Eng (Schlagzeile)', 'normal' => 'Normal'],
                'values' => ['tight' => '1', 'normal' => '.2']],
            ['name' => 'measure', 'label' => 'Lesebreite (Zeilenlänge)', 'type' => 'choice', 'var' => '--e-measure', 'default' => 'normal',
                'options' => ['schmal' => 'Schmal (ca. 60 Zeichen)', 'normal' => 'Normal (ca. 66 Zeichen)', 'breit' => 'Breit (ca. 74 Zeichen)'],
                'values' => ['schmal' => '60ch', 'normal' => '66ch', 'breit' => '74ch'],
                'help' => 'Maximale Zeilenlänge von Artikeln und Fließtexten.'],
            ['name' => 'kicker', 'label' => 'Dachzeilen (Rubrik über der Schlagzeile)', 'type' => 'choice', 'class' => 'kick-{value}', 'default' => 'caps',
                'options' => ['caps' => 'Versalien, gesperrt', 'mono' => 'Mono, Versalien', 'serif' => 'Kursiv (Serife)', 'bar' => 'Mit Farbbalken']],
            ['name' => 'dropcap', 'label' => 'Initiale am Artikelanfang', 'type' => 'bool', 'class' => 'has-dropcap', 'default' => true,
                'help' => 'Großer Anfangsbuchstabe im ersten Absatz von Artikeln und Texten mit „Initiale“.'],
        ]],
        ['id' => 'raster', 'label' => 'Raster, Bilder & Linien', 'tokens' => [
            ['name' => 'grid', 'label' => 'Seitenraster', 'type' => 'choice', 'class' => 'grid-{value}', 'default' => 'classic',
                'options' => ['classic' => 'Klassisch (12 Spalten, Randspalte rechts)', 'asym' => 'Asymmetrisch (Randspalte links, versetzt)', 'center' => 'Zentrierte Lesespalte'],
                'help' => 'Bestimmt, wie Aufmacher, Artikel und Randnotizen angeordnet werden.'],
            ['name' => 'images', 'label' => 'Bildbehandlung', 'type' => 'choice', 'class' => 'img-{value}', 'default' => 'color',
                'options' => ['color' => 'Farbig', 'duotone' => 'Duplex (Akzentfarbe + Schwarz)', 'gray' => 'Schwarzweiß, farbig beim Überfahren']],
            ['name' => 'rules', 'label' => 'Linien', 'type' => 'choice', 'class' => 'rules-{value}', 'default' => 'double',
                'options' => ['hair' => 'Haarlinie', 'double' => 'Doppellinie (Zeitung)', 'bold' => 'Kräftiger Balken']],
            ['name' => 'radius', 'label' => 'Eckenradius', 'type' => 'range', 'var' => '--e-radius', 'unit' => 'px', 'min' => 0, 'max' => 16, 'step' => 1, 'default' => 0],
            ['name' => 'spacing', 'label' => 'Abstand zwischen Abschnitten', 'type' => 'choice', 'var' => '--e-sec-y', 'default' => 'normal',
                'options' => ['compact' => 'Kompakt', 'normal' => 'Normal', 'airy' => 'Großzügig'],
                'values' => ['compact' => 'clamp(40px,5.5vw,72px)', 'normal' => 'clamp(56px,7.5vw,112px)', 'airy' => 'clamp(72px,10vw,152px)']],
        ]],
        ['id' => 'navigation', 'label' => 'Kopf & Navigation', 'tokens' => [
            ['name' => 'header', 'label' => 'Kopfbereich', 'type' => 'choice', 'class' => 'head-{value}', 'default' => 'masthead', 'preview' => 'nav',
                'thumbs' => ['masthead' => 'center', 'compact' => 'left', 'split' => 'split', 'ressorts' => 'burger'],
                'options' => [
                    'masthead' => 'Titelkopf – Name groß zentriert, Datumszeile',
                    'compact' => 'Kompakt – schmale Leiste, bleibt oben',
                    'split' => 'Geteilt – Rubriken als Reiter',
                    'ressorts' => 'Ressorts – großes Menü mit allen Rubriken',
                ],
                'help' => 'Alle Varianten sind per Tastatur bedienbar; mobil öffnet ein Menü-Blatt mit Suche, Rubriken und Sprache.'],
            ['name' => 'nav_sticky', 'label' => 'Navigation beim Scrollen sichtbar halten', 'type' => 'bool', 'class' => 'nav-sticky', 'default' => true],
            ['name' => 'nav_parent', 'label' => 'Menüpunkte mit Unterseiten', 'type' => 'choice', 'class' => 'np-{value}', 'default' => 'split',
                'options' => ['split' => 'Link + Pfeil – der Menüpunkt öffnet seine Seite, der Pfeil die Unterseiten', 'hover' => 'Link + Pfeil, Unterseiten öffnen auch beim Überfahren mit der Maus', 'overview' => 'Klick öffnet die Unterseiten, Eintrag „Übersicht“ führt zur Seite'],
                'help' => 'Gilt für das Menü in der Leiste und im Menü-Blatt (Telefon). „Link + Pfeil“ kommt ohne zusätzlichen Eintrag „Übersicht“ aus.'],
            ['name' => 'nav_levels', 'label' => 'Dritte Menüebene im Aufklappmenü', 'type' => 'choice', 'class' => 'nv-{value}', 'default' => 'indent',
                'options' => ['indent' => 'Eingerückt – mit Linie, etwas kleiner', 'groups' => 'Gruppiert – zweite Ebene als Zwischenüberschrift, dritte darunter'],
                'help' => 'Wie Unterseiten von Unterseiten im Aufklappmenü der Leiste erscheinen.'],
            ['name' => 'dateline', 'label' => 'Datumszeile mit Ausgabe (Titelkopf, Geteilt)', 'type' => 'bool', 'default' => true,
                'help' => 'Wochentag, Datum und – falls eingetragen – die Ausgabe (Website → Darstellung).'],
            ['name' => 'footer', 'label' => 'Fußbereich', 'type' => 'choice', 'class' => 'foot-{value}', 'default' => 'sitemap',
                'options' => ['sitemap' => 'Sitemap in Spalten', 'simple' => 'Schlicht in einer Zeile']],
        ]],
        // Kopfbereich-Aktionen (Core\HeaderActions) – Standard des Kits: Befehlsfeld „Suchen … ⌘K“ + Newsletter als Textlink (zur Datumszeile)
        \Core\HeaderActions::designGroup(['ha_cta_style' => 'link', 'ha_search' => 'command']),
        ['id' => 'modus', 'label' => 'Farbschema', 'tokens' => [
            ['name' => 'dark', 'label' => 'Nachtausgabe (dunkles Farbschema), wenn im Gerät der Besucher eingestellt', 'type' => 'bool', 'class' => 'has-dark', 'default' => true],
        ]],
    ],

    // Schriften: 'fontsource' = installiert der Schriften-Manager (Core\Fonts, fonts:sync; selbst gehostet, ohne externe Anfragen); variable Schriften: alle Stärken in einer Datei
    'fonts' => [
        'newsreader' => ['label' => 'Newsreader (Serife, Zeitung)', 'stack' => 'Newsreader,ui-serif,Georgia,Cambria,serif', 'fontsource' => 'newsreader', 'styles' => ['normal', 'italic']],
        'fraunces' => ['label' => 'Fraunces (Serife, verspielt)', 'stack' => 'Fraunces,ui-serif,Georgia,Cambria,serif', 'fontsource' => 'fraunces', 'styles' => ['normal', 'italic']],
        'playfair' => ['label' => 'Playfair Display (Serife, Magazin)', 'stack' => '"Playfair Display",ui-serif,Georgia,serif', 'fontsource' => 'playfair-display', 'styles' => ['normal', 'italic']],
        'literata' => ['label' => 'Literata (Serife, Buch)', 'stack' => 'Literata,ui-serif,Georgia,Cambria,serif', 'fontsource' => 'literata', 'styles' => ['normal', 'italic']],
        'source-serif' => ['label' => 'Source Serif 4 (Serife, sachlich)', 'stack' => '"Source Serif 4",ui-serif,Georgia,Cambria,serif', 'fontsource' => 'source-serif-4', 'styles' => ['normal', 'italic']],
        'dm-serif' => ['label' => 'DM Serif Display (Serife, kontrastreich – nur Normal)', 'stack' => '"DM Serif Display",ui-serif,Georgia,serif', 'fontsource' => 'dm-serif-display', 'styles' => ['normal', 'italic']],
        'instrument' => ['label' => 'Instrument Serif (Serife, elegant – nur Normal)', 'stack' => '"Instrument Serif",ui-serif,Georgia,serif', 'fontsource' => 'instrument-serif', 'styles' => ['normal', 'italic']],
        'source-sans' => ['label' => 'Source Sans 3 (Grotesk, humanistisch)', 'stack' => '"Source Sans 3",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'source-sans-3', 'styles' => ['normal', 'italic']],
        'public-sans' => ['label' => 'Public Sans (Grotesk, neutral)', 'stack' => '"Public Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'public-sans', 'styles' => ['normal', 'italic']],
        'work-sans' => ['label' => 'Work Sans (Grotesk, freundlich)', 'stack' => '"Work Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'work-sans', 'styles' => ['normal', 'italic']],
        'franklin' => ['label' => 'Libre Franklin (Grotesk, Zeitung)', 'stack' => '"Libre Franklin",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'libre-franklin', 'styles' => ['normal', 'italic']],
        'plex-sans' => ['label' => 'IBM Plex Sans (Grotesk, technisch)', 'stack' => '"IBM Plex Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'ibm-plex-sans', 'styles' => ['normal', 'italic']],
        'inter' => ['label' => 'Inter (Grotesk, Bildschirm)', 'stack' => 'Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif', 'fontsource' => 'inter', 'styles' => ['normal', 'italic']],
        'plex-mono' => ['label' => 'IBM Plex Mono (Mono)', 'stack' => '"IBM Plex Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace', 'fontsource' => 'ibm-plex-mono', 'variable' => false, 'weights' => [400, 500]],
        'jetbrains' => ['label' => 'JetBrains Mono (Mono)', 'stack' => '"JetBrains Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace', 'fontsource' => 'jetbrains-mono'],
        'system' => ['label' => 'Systemschrift Grotesk (ohne Download)', 'stack' => 'ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'],
        'system-serif' => ['label' => 'Systemschrift Serife (ohne Download)', 'stack' => 'ui-serif,Georgia,Cambria,"Times New Roman",serif'],
        'system-mono' => ['label' => 'Systemschrift Mono (ohne Download)', 'stack' => 'ui-monospace,SFMono-Regular,Menlo,Consolas,monospace'],
    ],

    'presets' => [
        'tageszeitung' => $preset('Tageszeitung', 'Druckschwarz auf warmem Papier, Zeitungsrot, Titelkopf mit Datumszeile und Doppellinien.',
            ['#B42318', '#8E1B12', '#FFFFFF', '#111111', '#262522', '#5C5850', '#FBFAF7', '#F2EFE8', '#DDD8CD', '#15130F'],
            ['#FF8A7A', '#FFB3A8', '#2A0703', '#F4F1EA', '#DAD6CD', '#A8A398', '#12110F', '#1B1A17', '#302E2A', '#080807'],
            ['font_display' => 'newsreader', 'font_body' => 'source-sans', 'font_mono' => 'plex-mono', 'base_size' => 18, 'scale' => 'magazin',
             'heading_weight' => '600', 'heading_tracking' => 'tight', 'measure' => 'normal', 'kicker' => 'caps', 'dropcap' => true,
             'grid' => 'classic', 'images' => 'color', 'rules' => 'double', 'radius' => 0, 'spacing' => 'normal',
             'header' => 'masthead', 'nav_sticky' => true, 'dateline' => true, 'footer' => 'sitemap', 'dark' => true]),
        'kulturhaus' => $preset('Kulturhaus', 'Kräftiges Magenta, verspielte Fraunces, Duplex-Bilder, asymmetrisches Raster und Rubriken als Reiter.',
            ['#AE1459', '#8A0F46', '#FFFFFF', '#1A0F14', '#2F242A', '#675960', '#FFFFFF', '#F7F0F3', '#EBDDE3', '#210F18'],
            ['#FF8CC4', '#FFB5DA', '#3A0620', '#FBEFF4', '#E8D8DF', '#B6A0AA', '#140B10', '#1E1218', '#3A2630', '#0A0508'],
            ['font_display' => 'fraunces', 'font_body' => 'work-sans', 'font_mono' => 'jetbrains', 'base_size' => 18, 'scale' => 'kraeftig',
             'heading_weight' => '700', 'heading_tracking' => 'tight', 'measure' => 'normal', 'kicker' => 'mono', 'dropcap' => true,
             'grid' => 'asym', 'images' => 'duotone', 'rules' => 'bold', 'radius' => 0, 'spacing' => 'normal',
             'header' => 'split', 'nav_sticky' => true, 'dateline' => true, 'footer' => 'sitemap', 'dark' => true]),
        'verband' => $preset('Verband', 'Vertrauensvolles Blau, Literata mit Public Sans, Ressort-Menü mit allen Rubriken – für Verbände mit Vereinen.',
            ['#1D4E89', '#153A68', '#FFFFFF', '#0E1726', '#243044', '#56627A', '#FFFFFF', '#F1F4F8', '#DCE2EB', '#0F1F36'],
            ['#8DB8F2', '#B7D2F8', '#0A1B33', '#EEF3FA', '#CFD8E6', '#98A5BA', '#0B111C', '#121A28', '#243047', '#05080E'],
            ['font_display' => 'literata', 'font_body' => 'public-sans', 'font_mono' => 'plex-mono', 'base_size' => 18, 'scale' => 'ruhig',
             'heading_weight' => '600', 'heading_tracking' => 'normal', 'measure' => 'normal', 'kicker' => 'bar', 'dropcap' => false,
             'grid' => 'classic', 'images' => 'color', 'rules' => 'hair', 'radius' => 4, 'spacing' => 'normal',
             'header' => 'ressorts', 'nav_sticky' => true, 'dateline' => false, 'footer' => 'sitemap', 'dark' => true]),
        'magazin' => $preset('Magazin Bold', 'Plakative Playfair-Schlagzeilen, Ultramarin, Schwarzweiß-Bilder, große Skala und kräftige Balken.',
            ['#2B3FD6', '#1F2FA8', '#FFFFFF', '#0A0A0A', '#1F1F1C', '#5A5A52', '#F7F5EE', '#ECE9DF', '#D6D2C4', '#0B0B0A'],
            ['#A3AEFF', '#C7CDFF', '#0A0F3A', '#FAFAF5', '#DDDDD5', '#A6A69D', '#0B0B0A', '#161614', '#2C2C28', '#000000'],
            ['font_display' => 'playfair', 'font_body' => 'franklin', 'font_mono' => 'jetbrains', 'base_size' => 18, 'scale' => 'plakat',
             'heading_weight' => '800', 'heading_tracking' => 'tight', 'measure' => 'schmal', 'kicker' => 'bar', 'dropcap' => true,
             'grid' => 'asym', 'images' => 'gray', 'rules' => 'bold', 'radius' => 0, 'spacing' => 'airy',
             'header' => 'compact', 'nav_sticky' => true, 'dateline' => false, 'footer' => 'sitemap', 'dark' => true]),
        'stiftung' => $preset('Stiftung', 'Ruhiges Grün, elegante Instrument Serif, zentrierte Lesespalte und viel Weißraum.',
            ['#2D6A4E', '#1F4F39', '#FFFFFF', '#16211B', '#28342D', '#59665D', '#FCFBF7', '#F0F2EB', '#DCE2D7', '#14261D'],
            ['#86D0A6', '#AFE3C5', '#062313', '#EEF4EF', '#D0DBD3', '#9EAEA2', '#0D1410', '#141D17', '#26332A', '#060A07'],
            ['font_display' => 'instrument', 'font_body' => 'public-sans', 'font_mono' => 'system-mono', 'base_size' => 18.5, 'scale' => 'kraeftig',
             'heading_weight' => '400', 'heading_tracking' => 'normal', 'measure' => 'schmal', 'kicker' => 'serif', 'dropcap' => true,
             'grid' => 'center', 'images' => 'color', 'rules' => 'hair', 'radius' => 2, 'spacing' => 'airy',
             'header' => 'compact', 'nav_sticky' => true, 'dateline' => false, 'footer' => 'simple', 'dark' => true]),
        'hochschule' => $preset('Hochschule', 'Petrol und Messing, Source Serif mit IBM Plex, Mono-Auszeichnungen und Rubriken als Reiter.',
            ['#0E5C6B', '#0A4551', '#FFFFFF', '#0C1A1E', '#223236', '#546569', '#FFFFFF', '#EFF4F5', '#D6E0E2', '#0B2328'],
            ['#7CCFDD', '#A8E0E9', '#032228', '#ECF5F6', '#CADADD', '#95A9AD', '#0A1315', '#101C1F', '#223337', '#040809'],
            ['font_display' => 'source-serif', 'font_body' => 'plex-sans', 'font_mono' => 'plex-mono', 'base_size' => 17.5, 'scale' => 'magazin',
             'heading_weight' => '600', 'heading_tracking' => 'normal', 'measure' => 'breit', 'kicker' => 'mono', 'dropcap' => false,
             'grid' => 'classic', 'images' => 'color', 'rules' => 'hair', 'radius' => 2, 'spacing' => 'normal',
             'header' => 'split', 'nav_sticky' => true, 'dateline' => true, 'footer' => 'sitemap', 'dark' => true]),
        'stadtmagazin' => $preset('Stadtmagazin', 'Orange auf Weiß, kontrastreiche DM Serif, Duplex-Bilder und Titelkopf – laut, aber lesbar.',
            ['#B83A0E', '#8F2C08', '#FFFFFF', '#141210', '#2A2723', '#615B54', '#FFFFFF', '#F6F1EC', '#E8DFD5', '#1C1410'],
            ['#FF9A6B', '#FFC0A1', '#2E0F02', '#FAF4EE', '#E2D8CE', '#AFA398', '#110E0C', '#1B1714', '#352E28', '#070504'],
            ['font_display' => 'dm-serif', 'font_body' => 'work-sans', 'font_mono' => 'jetbrains', 'base_size' => 18, 'scale' => 'plakat',
             'heading_weight' => '400', 'heading_tracking' => 'tight', 'measure' => 'normal', 'kicker' => 'bar', 'dropcap' => true,
             'grid' => 'asym', 'images' => 'duotone', 'rules' => 'bold', 'radius' => 0, 'spacing' => 'normal',
             'header' => 'masthead', 'nav_sticky' => true, 'dateline' => true, 'footer' => 'sitemap', 'dark' => true]),
    ],

    // Dunkle Werte („Nachtausgabe“) gelten bei „Nachtausgabe“ (Klasse has-dark) und Geräte-Einstellung; die Vorschau im Editor setzt is-dark
    'dark' => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark', 'force' => 'is-dark'],

    // Musterseite für die Vorschau im Editor (Startseite der Demo, siehe tools/demo.php)
    'sample' => 'start',
];
