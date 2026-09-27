<?php
/**
 * Kontrastprüfung aller Voreinstellungen des Kits „nature“ (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/nature/tools/contrast.php        (Exit-Code 1 bei Verstößen, -v zeigt alle Werte)
 *
 * Geprüft werden die Farbpaare, die das Kit-CSS tatsächlich verwendet – auch abgeleitete Farben, genau wie in
 * assets/css/_tokens.css berechnet (color-mix in sRGB):
 *   --n-a-soft  = Akzent 16 % in den Hintergrund gemischt (Abschnitt „Moos hell“, Etiketten)
 *   Nebentext in Waldnacht = helle Schrift 78 % über der Fläche; im Abschnitt „Akzentfarbe“ = Schrift auf Akzent (Flächen darin nur 4 % getönt)
 *   Akzent in Waldnacht = Akzent 45 % in die helle Schrift gemischt (Buttons dort mit dunkler Schrift)
 *   Akzent als Bedienelement (Buttons, Skalen, Marken) und „Erde“ als Markierung brauchen 3:1 (WCAG 1.4.11).
 */
declare(strict_types=1);

$design = require dirname(__DIR__) . '/design.php';

function lum(string $hex): float
{
    $c = array_map(fn($i) => hexdec(substr(ltrim($hex, '#'), $i, 2)) / 255, [0, 2, 4]);
    $c = array_map(fn($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function ratio(string $a, string $b): float
{
    [$x, $y] = [lum($a), lum($b)];
    return round((max($x, $y) + 0.05) / (min($x, $y) + 0.05), 2);
}
/** color-mix(in srgb, $a $pct%, $b) */
function mix(string $a, float $pct, string $b): string
{
    $out = '#';
    foreach ([0, 2, 4] as $i) {
        $va = hexdec(substr(ltrim($a, '#'), $i, 2));
        $vb = hexdec(substr(ltrim($b, '#'), $i, 2));
        $out .= sprintf('%02X', (int) round($va * $pct / 100 + $vb * (1 - $pct / 100)));
    }
    return $out;
}

$fail = 0;
$verbose = in_array('-v', $argv, true);
$night = '#F1EEE3';   // Schrift in Waldnacht (--n-on-dark)
$rows = [];
foreach ($design['presets'] as $key => $p) {
    foreach (['hell' => '', 'dunkel' => '@dark'] as $mode => $sfx) {
        $v = fn(string $k) => $p['values'][$k . $sfx];
        $soft = mix($v('accent'), 16, $v('background'));
        $ui = mix($v('accent'), 45, $night);
        $checks = [
            'Fließtext / Hintergrund' => [$v('text'), $v('background'), 4.5],
            'Fließtext / Sand' => [$v('text'), $v('surface'), 4.5],
            'Fließtext / Karte' => [$v('text'), $v('panel'), 4.5],
            'Überschrift / Hintergrund' => [$v('ink'), $v('background'), 7],
            'Überschrift / Sand' => [$v('ink'), $v('surface'), 4.5],
            'Nebentext / Hintergrund' => [$v('muted'), $v('background'), 4.5],
            'Nebentext / Sand' => [$v('muted'), $v('surface'), 4.5],
            'Nebentext / Karte' => [$v('muted'), $v('panel'), 4.5],
            'Akzent-Schrift / Hintergrund' => [$v('accent_ink'), $v('background'), 4.5],
            'Akzent-Schrift / Sand' => [$v('accent_ink'), $v('surface'), 4.5],
            'Akzent-Schrift / Karte' => [$v('accent_ink'), $v('panel'), 4.5],
            'Akzent-Schrift / Moos hell' => [$v('accent_ink'), $soft, 4.5],
            'Abschnitt Moos hell: Text' => [$v('text'), $soft, 4.5],
            'Abschnitt Moos hell: Nebentext' => [$v('muted'), $soft, 4.5],
            'Button: Schrift / Akzent' => [$v('on_accent'), $v('accent'), 4.5],
            'Akzent als Bedienelement / Hintergrund' => [$v('accent'), $v('background'), 3],
            'Akzent als Bedienelement / Karte' => [$v('accent'), $v('panel'), 3],
            'Akzent als Bedienelement / Sand' => [$v('accent'), $v('surface'), 3],
            'Erde als Markierung / Hintergrund' => [$v('earth'), $v('background'), 3],
            'Erde als Markierung / Sand' => [$v('earth'), $v('surface'), 3],
            'Abschnitt Akzent: Schrift auf Fläche (4 %)' => [$v('on_accent'), mix($v('on_accent'), 4, $v('accent')), 4.5],
            'Waldnacht: Schrift / Fläche' => [$night, $v('dark_section'), 7],
            'Waldnacht: Nebentext (78 %)' => [mix($night, 78, $v('dark_section')), $v('dark_section'), 4.5],
            'Waldnacht: Akzent als Bedienelement' => [$ui, $v('dark_section'), 3],
            'Waldnacht: Button-Schrift / Akzent' => [$v('dark_section'), $ui, 4.5],
            'Formularrahmen (Linie kräftig) / Karte' => [mix($v('ink'), 55, $v('panel')), $v('panel'), 3],
            'Fokusring (Akzent-Schrift) / Sand' => [$v('accent_ink'), $v('surface'), 3],
        ];
        foreach ($checks as $label => [$fg, $bg, $min]) {
            $r = ratio($fg, $bg);
            $rows[$key][$mode][$label] = $r;
            if ($r < $min) {
                $fail++;
                printf("✗ %-9s %-7s %-42s %5.2f < %.1f  (%s auf %s)\n", $key, $mode, $label, $r, $min, $fg, $bg);
            } elseif ($verbose) {
                printf("✓ %-9s %-7s %-42s %5.2f\n", $key, $mode, $label, $r);
            }
        }
    }
}
if (in_array('--table', $argv, true)) {
    // Kurzübersicht (Markdown): wichtigste Paare je Vorlage, hell / dunkel
    $cols = ['Fließtext / Hintergrund', 'Nebentext / Sand', 'Akzent-Schrift / Hintergrund', 'Button: Schrift / Akzent', 'Akzent als Bedienelement / Hintergrund', 'Erde als Markierung / Hintergrund', 'Waldnacht: Nebentext (78 %)', 'Waldnacht: Akzent als Bedienelement'];
    echo '| Vorlage | ' . implode(' | ', $cols) . " |\n|" . str_repeat('---|', count($cols) + 1) . "\n";
    foreach ($rows as $key => $modes) {
        echo '| ' . $key . ' | ' . implode(' | ', array_map(fn($c) => number_format($modes['hell'][$c], 2) . ' / ' . number_format($modes['dunkel'][$c], 2), $cols)) . " |\n";
    }
}
echo $fail ? "\n$fail Verstöße.\n" : 'Alle ' . count($design['presets']) . " Voreinstellungen erfüllen WCAG 2.2 AA (hell und dunkel).\n";
exit($fail ? 1 : 0);
