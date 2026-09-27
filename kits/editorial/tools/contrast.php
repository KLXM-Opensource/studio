<?php
/**
 * Kontrastprüfung aller Voreinstellungen des Themes „editorial“ (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/editorial/tools/contrast.php        (Exit-Code 1 bei Verstößen; -v zeigt alle Werte)
 *
 * Geprüft werden die Farbpaare, die das Theme-CSS tatsächlich verwendet – auch abgeleitete Farben wie in site.css:
 *   --e-a-soft   Akzent zu 10 % (dunkel 16 %) in den Papierton gemischt (Markierungen, Etiketten, „Kurz notiert“)
 *   Nachtausgabe (Abschnitt „dunkel“, helles Schema): Schrift Weiß, Nebentext 80 % Weiß, Dachzeile/Links = Akzent zu 40 % in Weiß
 *   Akzentfläche (helles Schema): Schrift auf Akzent, Nebentext 88 % davon
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
foreach ($design['presets'] as $key => $p) {
    foreach (['hell' => '', 'dunkel' => '@dark'] as $mode => $sfx) {
        $v = fn(string $k) => $p['values'][$k . $sfx];
        $soft = mix($v('accent'), $sfx ? 16 : 10, $v('background'));
        $white = '#FFFFFF';
        $checks = [
            'Fließtext / Papier (AAA)' => [$v('text'), $v('background'), 7],
            'Fließtext / getönt' => [$v('text'), $v('surface'), 4.5],
            'Überschrift / Papier (AAA)' => [$v('ink'), $v('background'), 7],
            'Überschrift / getönt' => [$v('ink'), $v('surface'), 4.5],
            'Nebentext / Papier' => [$v('muted'), $v('background'), 4.5],
            'Nebentext / getönt' => [$v('muted'), $v('surface'), 4.5],
            'Dachzeile, Link (Akzent) / Papier' => [$v('accent'), $v('background'), 4.5],
            'Dachzeile, Link (Akzent) / getönt' => [$v('accent'), $v('surface'), 4.5],
            'Link hover (Akzent kräftig) / Papier' => [$v('accent_strong'), $v('background'), 4.5],
            'Link (Akzent) / Akzent-Tönung' => [$v('accent'), $soft, 4.5],
            'Text / Akzent-Tönung (Markierung, Etikett)' => [$v('ink'), $soft, 4.5],
            'Button: Schrift / Akzent' => [$v('on_accent'), $v('accent'), 4.5],
            'Button hover: Schrift / Akzent kräftig' => [$v('on_accent'), $v('accent_strong'), 4.5],
            'Formularrahmen (Nebentext) / Papier' => [$v('muted'), $v('background'), 3],
            'Linie kräftig (Tinte 30 %) / Papier (Grafik)' => [mix($v('ink'), 30, $v('background')), $v('background'), 1.3],
        ];
        if ($sfx) {
            // Dunkles Schema: Akzentfläche = Akzent-Tönung mit normalen Farben; Nachtausgabe = noch dunklere Fläche
            $checks += [
                'Abschnitt Akzent: Text / Fläche' => [$v('text'), $soft, 4.5],
                'Abschnitt Akzent: Nebentext / Fläche' => [$v('muted'), $soft, 4.5],
                'Nachtausgabe: Text / Fläche' => [$v('text'), $v('dark_section'), 7],
                'Nachtausgabe: Nebentext / Fläche' => [$v('muted'), $v('dark_section'), 4.5],
                'Nachtausgabe: Akzent / Fläche' => [$v('accent'), $v('dark_section'), 4.5],
            ];
        } else {
            $checks += [
                'Abschnitt Akzent: Schrift / Fläche' => [$v('on_accent'), $v('accent'), 4.5],
                'Abschnitt Akzent: Nebentext (88 %) / Fläche' => [mix($v('on_accent'), 88, $v('accent')), $v('accent'), 4.5],
                'Abschnitt Akzent: Button (Akzent auf Schrift)' => [$v('accent'), $v('on_accent'), 4.5],
                'Nachtausgabe: Weiß / Fläche' => [$white, $v('dark_section'), 7],
                'Nachtausgabe: Nebentext (80 % Weiß) / Fläche' => [mix($white, 80, $v('dark_section')), $v('dark_section'), 4.5],
                'Nachtausgabe: Dachzeile (Akzent 40 % in Weiß)' => [mix($v('accent'), 40, $white), $v('dark_section'), 4.5],
            ];
        }
        foreach ($checks as $label => [$fg, $bg, $min]) {
            $r = ratio($fg, $bg);
            if ($r < $min) {
                $fail++;
                printf("✗ %-13s %-7s %-46s %5.2f < %.1f  (%s auf %s)\n", $key, $mode, $label, $r, $min, $fg, $bg);
            } elseif ($verbose) {
                printf("✓ %-13s %-7s %-46s %5.2f\n", $key, $mode, $label, $r);
            }
        }
    }
}
echo $fail ? "\n$fail Verstöße.\n" : 'Alle ' . count($design['presets']) . " Voreinstellungen erfüllen WCAG 2.2 AA (hell und dunkel).\n";
exit($fail ? 1 : 0);
