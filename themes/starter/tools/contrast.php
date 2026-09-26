<?php
// SPDX-License-Identifier: MIT
/**
 * Kontrastprüfung aller Voreinstellungen (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php themes/starter/tools/contrast.php        (Exit-Code 1 bei Verstößen, -v zeigt alle Werte)
 * Geprüft werden die Paare, die das Kit-CSS tatsächlich verwendet (assets/css/_tokens.css, _base.css) – auch die
 * abgeleiteten Farben der Abschnitte „Akzentfarbe“ (color-mix) und „Dunkel“ (feste helle Schrift).
 * Eigene Farben/Rollen ergänzt? Hier ein Paar mehr eintragen.
 */
declare(strict_types=1);

$design = (require dirname(__DIR__) . '/theme.php')['design'];

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
        $out .= sprintf('%02X', (int) round(hexdec(substr(ltrim($a, '#'), $i, 2)) * $pct / 100 + hexdec(substr(ltrim($b, '#'), $i, 2)) * (1 - $pct / 100)));
    }
    return $out;
}

// Standardwerte der Tokens (hell/dunkel) – Voreinstellungen überschreiben nur, was sie nennen
$defaults = [];
foreach ($design['groups'] as $g) foreach ($g['tokens'] as $t) {
    if (($t['type'] ?? '') !== 'color') continue;
    $defaults[$t['name']] = $t['default'];
    $defaults[$t['name'] . '@dark'] = $t['dark'];
}
$fail = 0;
$verbose = in_array('-v', $argv, true);
foreach ($design['presets'] as $key => $p) {
    $vals = $p['values'] + $defaults;
    foreach (['hell' => '', 'dunkel' => '@dark'] as $mode => $sfx) {
        $v = fn(string $k) => $vals[$k . $sfx];
        $checks = [
            'Fließtext / Hintergrund' => [$v('text'), $v('background'), 4.5],
            'Fließtext / getönt' => [$v('text'), $v('surface'), 4.5],
            'Überschrift / Hintergrund' => [$v('ink'), $v('background'), 7],
            'Nebentext / Hintergrund' => [$v('muted'), $v('background'), 4.5],
            'Nebentext / getönt' => [$v('muted'), $v('surface'), 4.5],
            'Link / Hintergrund' => [$v('accent'), $v('background'), 4.5],
            'Link / getönt' => [$v('accent'), $v('surface'), 4.5],
            'Button: Schrift / Akzent' => [$v('on_accent'), $v('accent'), 4.5],
            'Abschnitt Akzent: Nebentext' => [mix($v('on_accent'), 88, $v('accent')), $v('accent'), 4.5],
            'Abschnitt Dunkel: Fließtext' => ['#DDE2E9', $v('dark_section'), 4.5],
            'Abschnitt Dunkel: Nebentext' => ['#AEB6C2', $v('dark_section'), 4.5],
            'Linie (stark) / Hintergrund' => [mix($v('text'), 55, $v('background')), $v('background'), 3],   // Rahmen von Eingabefeldern (1.4.11)
        ];
        foreach ($checks as $label => [$fg, $bg, $min]) {
            $r = ratio($fg, $bg);
            if ($r < $min) $fail++;
            if ($r < $min || $verbose) printf("%-11s %-7s %-32s %s / %s  %5.2f:1 (min %.1f) %s\n", $key, $mode, $label, $fg, $bg, $r, $min, $r < $min ? 'FEHLER' : 'ok');
        }
    }
}
echo $fail ? "$fail Prüfungen nicht bestanden.\n" : 'Alle ' . count($design['presets']) . " Voreinstellungen bestehen WCAG 2.2 AA (hell + dunkel).\n";
exit($fail ? 1 : 0);
