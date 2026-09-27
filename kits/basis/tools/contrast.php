<?php
/**
 * Kontrastprüfung aller Voreinstellungen (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/basis/tools/contrast.php            (Exit-Code 1 bei Verstößen)
 *
 * Geprüft werden die Farbpaare, die das Theme-CSS tatsächlich verwendet – auch abgeleitete Farben
 * (z. B. --b-a-soft = Akzent zu 11 % bzw. 16 % in den Hintergrund gemischt, wie in site.css).
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
foreach ($design['presets'] as $key => $p) {
    foreach (['hell' => '', 'dunkel' => '@dark'] as $mode => $sfx) {
        $v = fn(string $k) => $p['values'][$k . $sfx];
        $soft = mix($v('accent'), $sfx ? 16 : 11, $v('background'));
        $white = '#FFFFFF';
        // Abschnitt „Akzentfarbe“: hell = Akzentfläche mit „Schrift auf Akzent“, dunkel = getönte Akzentfläche mit normalen Farben
        $band = $sfx ? $soft : $v('accent');
        $checks = [
            'Fließtext / Hintergrund' => [$v('text'), $v('background'), 4.5],
            'Fließtext / getönt' => [$v('text'), $v('surface'), 4.5],
            'Überschrift / Hintergrund' => [$v('ink'), $v('background'), 7],
            'Nebentext / Hintergrund' => [$v('muted'), $v('background'), 4.5],
            'Nebentext / getönt' => [$v('muted'), $v('surface'), 4.5],
            'Link (Akzent) / Hintergrund' => [$v('accent'), $v('background'), 4.5],
            'Link (Akzent) / getönt' => [$v('accent'), $v('surface'), 4.5],
            'Link (Akzent) / Akzent-Tönung' => [$v('accent'), $soft, 4.5],
            'Button: Schrift / Akzent' => [$v('on_accent'), $v('accent'), 4.5],
            'Button hover: Schrift / Akzent kräftig' => [$v('on_accent'), $v('accent_strong'), 4.5],
            'Text / Akzent-Tönung (Hinweis, Symbole)' => [$v('ink'), $soft, 4.5],
            'Formularrahmen (Nebentext) / Hintergrund' => [$v('muted'), $v('background'), 3],
        ];
        if ($sfx) {
            $checks += [
                'Abschnitt Akzent: Text / Fläche' => [$v('text'), $band, 4.5],
                'Abschnitt Akzent: Nebentext / Fläche' => [$v('muted'), $band, 4.5],
                'Abschnitt Dunkel: Link / Fläche' => [$v('accent'), $v('dark_section'), 4.5],
                'Abschnitt Dunkel: Text / Fläche' => [$v('text'), $v('dark_section'), 4.5],
                'Abschnitt Dunkel: Nebentext / Fläche' => [$v('muted'), $v('dark_section'), 4.5],
            ];
        } else {
            $checks += [
                'Abschnitt Akzent: Text / Fläche' => [$v('on_accent'), $band, 4.5],
                'Abschnitt Akzent: Button (Akzent auf Schriftfarbe)' => [$v('accent'), $v('on_accent'), 4.5],
                'Abschnitt Dunkel: Weiß / Fläche' => [$white, $v('dark_section'), 7],
                'Abschnitt Dunkel: Nebentext (88 % Weiß) / Fläche' => [mix($white, 88, $v('dark_section')), $v('dark_section'), 4.5],
            ];
        }
        foreach ($checks as $label => [$fg, $bg, $min]) {
            $r = ratio($fg, $bg);
            if ($r < $min) {
                $fail++;
                printf("✗ %-10s %-7s %-48s %5.2f < %.1f  (%s auf %s)\n", $key, $mode, $label, $r, $min, $fg, $bg);
            } elseif (in_array('-v', $argv, true)) {
                printf("✓ %-10s %-7s %-48s %5.2f\n", $key, $mode, $label, $r);
            }
        }
    }
}
echo $fail ? "\n$fail Verstöße.\n" : 'Alle ' . count($design['presets']) . " Voreinstellungen erfüllen WCAG 2.2 AA (hell und dunkel).\n";
exit($fail ? 1 : 0);
