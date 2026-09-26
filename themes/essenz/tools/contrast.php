<?php
/**
 * Kontrastprüfung aller Voreinstellungen des Themes „essenz“ (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php themes/essenz/tools/contrast.php        (Exit-Code 1 bei Verstößen, -v zeigt alle Werte)
 *
 * Geprüft werden die Farbpaare, die das Theme-CSS tatsächlich verwendet – auch abgeleitete Farben, genau wie in
 * assets/css/_tokens.css berechnet (color-mix in sRGB):
 *   --e-a-soft  = Signal 16 % in den Hintergrund gemischt (Abschnitt „Signal hell“, Etiketten)
 *   Nebentext im Nachtpaneel = Nachtschrift 78 % über der Fläche; im Abschnitt „Signalfarbe“ = Schrift auf Signal 92 %
 *   Signal als Bedienelement (Tasten, Skalen, Fokusring) braucht 3:1 (WCAG 1.4.11 Nicht-Text-Kontrast).
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
$night = '#F2F1EE';   // Schrift im Nachtpaneel (--e-on-dark)
foreach ($design['presets'] as $key => $p) {
    foreach (['hell' => '', 'dunkel' => '@dark'] as $mode => $sfx) {
        $v = fn(string $k) => $p['values'][$k . $sfx];
        $soft = mix($v('accent'), 16, $v('background'));
        $checks = [
            'Fließtext / Hintergrund' => [$v('text'), $v('background'), 4.5],
            'Fließtext / getönt' => [$v('text'), $v('surface'), 4.5],
            'Fließtext / Paneel' => [$v('text'), $v('panel'), 4.5],
            'Überschrift / Hintergrund' => [$v('ink'), $v('background'), 7],
            'Überschrift / getönt' => [$v('ink'), $v('surface'), 4.5],
            'Nebentext / Hintergrund' => [$v('muted'), $v('background'), 4.5],
            'Nebentext / getönt' => [$v('muted'), $v('surface'), 4.5],
            'Nebentext / Paneel' => [$v('muted'), $v('panel'), 4.5],
            'Signal-Schrift / Hintergrund' => [$v('accent_ink'), $v('background'), 4.5],
            'Signal-Schrift / getönt' => [$v('accent_ink'), $v('surface'), 4.5],
            'Signal-Schrift / Paneel' => [$v('accent_ink'), $v('panel'), 4.5],
            'Signal-Schrift / Signal hell' => [$v('accent_ink'), $soft, 4.5],
            'Abschnitt Signal hell: Text' => [$v('text'), $soft, 4.5],
            'Abschnitt Signal hell: Nebentext' => [$v('muted'), $soft, 4.5],
            'Taste: Schrift / Signal' => [$v('on_accent'), $v('accent'), 4.5],
            'Signal als Bedienelement / Hintergrund' => [$v('accent'), $v('background'), 3],
            'Signal als Bedienelement / Paneel' => [$v('accent'), $v('panel'), 3],
            'Signal als Bedienelement / getönt' => [$v('accent'), $v('surface'), 3],
            'Abschnitt Signal: Nebentext (92 %)' => [mix($v('on_accent'), 92, $v('accent')), $v('accent'), 4.5],
            'Nachtpaneel: Schrift / Fläche' => [$night, $v('dark_section'), 7],
            'Nachtpaneel: Nebentext (78 %)' => [mix($night, 78, $v('dark_section')), $v('dark_section'), 4.5],
            'Nachtpaneel: Signal als Bedienelement' => [$v('accent'), $v('dark_section'), 3],
            'Formularrahmen (Linie kräftig) / Paneel' => [mix($v('ink'), 55, $v('panel')), $v('panel'), 3],
            'Fokusring (Signal-Schrift) / getönt' => [$v('accent_ink'), $v('surface'), 3],
        ];
        foreach ($checks as $label => [$fg, $bg, $min]) {
            $r = ratio($fg, $bg);
            if ($r < $min) {
                $fail++;
                printf("✗ %-9s %-7s %-42s %5.2f < %.1f  (%s auf %s)\n", $key, $mode, $label, $r, $min, $fg, $bg);
            } elseif ($verbose) {
                printf("✓ %-9s %-7s %-42s %5.2f\n", $key, $mode, $label, $r);
            }
        }
    }
}
echo $fail ? "\n$fail Verstöße.\n" : 'Alle ' . count($design['presets']) . " Voreinstellungen erfüllen WCAG 2.2 AA (hell und dunkel).\n";
exit($fail ? 1 : 0);
