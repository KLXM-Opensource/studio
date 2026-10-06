<?php
/**
 * Kontrastprüfung aller Voreinstellungen des Themes „fluid“ (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/fluid/tools/contrast.php        (Exit-Code 1 bei Verstößen, -v zeigt alle Werte)
 *
 * Geprüft werden die Farbpaare, die das Theme-CSS tatsächlich verwendet – auch abgeleitete Farben, genau wie in
 * assets/css/_tokens.css berechnet (color-mix in sRGB):
 *   --f-a-soft   = Akzent 14 % in den Hintergrund gemischt (Abschnitt „Akzent hell“, Etiketten, Hinweise)
 *   Nebentext in dunklen Abschnitten = Weiß 80 % über der Fläche, im Abschnitt „Akzentfarbe“ = Schrift auf Akzent (auf Karten: 7 % getönt)
 */
declare(strict_types=1);

// design.php nutzt Kern-Klassen (Kopfbereich-Aktionen) – Autoloader des Projekts laden, falls vorhanden
if (is_file($al = dirname(__DIR__, 3) . '/vendor/autoload.php')) require_once $al;
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
foreach ($design['presets'] as $key => $p) {
    foreach (['hell' => '', 'dunkel' => '@dark'] as $mode => $sfx) {
        // Zweite Markenfarbe: in älteren Vorlagen nicht gesetzt → Standard des Tokens
        $def2 = ['secondary' => ['#0F766E', '#5EEAD4'], 'on_secondary' => ['#FFFFFF', '#062B27']];
        $v = fn(string $k) => $p['values'][$k . $sfx] ?? $def2[$k][$sfx === '' ? 0 : 1];
        $soft = mix($v('accent'), 14, $v('background'));
        $white = '#FFFFFF';
        $checks = [
            'Fließtext / Hintergrund' => [$v('text'), $v('background'), 4.5],
            'Fließtext / getönt' => [$v('text'), $v('surface'), 4.5],
            'Überschrift / Hintergrund' => [$v('ink'), $v('background'), 7],
            'Überschrift / getönt' => [$v('ink'), $v('surface'), 4.5],
            'Nebentext / Hintergrund' => [$v('muted'), $v('background'), 4.5],
            'Nebentext / getönt' => [$v('muted'), $v('surface'), 4.5],
            'Link (Akzent) / Hintergrund' => [$v('accent'), $v('background'), 4.5],
            'Link (Akzent) / getönt' => [$v('accent'), $v('surface'), 4.5],
            'Link hover (kräftig) / Hintergrund' => [$v('accent_strong'), $v('background'), 4.5],
            'Link (Akzent) / Akzent hell' => [$v('accent'), $soft, 4.5],
            'Button: Schrift / Akzent' => [$v('on_accent'), $v('accent'), 4.5],
            'Button hover: Schrift / Akzent kräftig' => [$v('on_accent'), $v('accent_strong'), 4.5],
            'Abschnitt Akzent hell: Text' => [$v('text'), $soft, 4.5],
            'Abschnitt Akzent hell: Nebentext' => [$v('muted'), $soft, 4.5],
            'Zweitfarbe: Überschrift' => [$v('ink'), $v('highlight'), 4.5],
            'Zweite Markenfarbe / Hintergrund (Grafik)' => [$v('secondary'), $v('background'), 3],
            'Schrift auf zweiter Markenfarbe' => [$v('on_secondary'), $v('secondary'), 4.5],
            'Zweitfarbe: Fließtext' => [$v('text'), $v('highlight'), 4.5],
            'Abschnitt Akzent: Nebentext auf Karte (7 %)' => [$v('on_accent'), mix($v('on_accent'), 7, $v('accent')), 4.5],
            'Abschnitt Dunkel: Weiß / Fläche' => [$white, $v('dark_section'), 7],
            'Abschnitt Dunkel: Nebentext (80 % Weiß)' => [mix($white, 80, $v('dark_section')), $v('dark_section'), 4.5],
            'Formularrahmen (Nebentext) / Hintergrund' => [$v('muted'), $v('background'), 3],
            'Fokusring (Akzent) / getönt' => [$v('accent'), $v('surface'), 3],
        ];
        foreach ($checks as $label => [$fg, $bg, $min]) {
            $r = ratio($fg, $bg);
            if ($r < $min) {
                $fail++;
                printf("✗ %-11s %-7s %-44s %5.2f < %.1f  (%s auf %s)\n", $key, $mode, $label, $r, $min, $fg, $bg);
            } elseif ($verbose) {
                printf("✓ %-11s %-7s %-44s %5.2f\n", $key, $mode, $label, $r);
            }
        }
    }
}
echo $fail ? "\n$fail Verstöße.\n" : 'Alle ' . count($design['presets']) . " Voreinstellungen erfüllen WCAG 2.2 AA (hell und dunkel).\n";
exit($fail ? 1 : 0);
