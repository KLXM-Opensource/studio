<?php
/**
 * Kontrastprüfung des Kits „glas“ nach WCAG 2.2 AA – alle Vorlagen, hell + dunkel, jede Glasdichte, jedes Farbfeld.
 * Ohne CMS lauffähig:
 *   php themes/glas/tools/contrast.php        (Exit-Code 1 bei Verstößen)
 *   php themes/glas/tools/contrast.php -v     (alle Tiefstwerte je Vorlage und Schema)
 *
 * Modell (identisch mit assets/css/_tokens.css – Werte dort ändern heißt: hier mitändern):
 *   Farbfeld der Seite  = Feldfarbe mit FF[Schema] (sanft/kräftig; im Dunkeln schwächer) über der Grundfarbe gemischt – ungünstigster Punkt ist die
 *                         Mitte eines Farbflecks bzw. die Überlagerung zweier Flecken
 *   Aurora im Einstieg  = Feldfarben mit AURORA-Deckkraft (fast volle Stärke)
 *   Hintergrundfilter   = saturate(SAT) auf alles hinter dem Glas (CSS-Filtermatrix, sRGB)
 *   Glas                = Glastönung mit Deckkraft ALPHA[Schema][Dichte] über dem gefilterten Hintergrund,
 *                         dazu im Dunkeln die Lichtkante oben (HILITE_DARK Weiß) – ungünstig für helle Schrift
 *   Kopfbereich         = Deckkraft HEADER über beliebigen Inhalten (Schwarz, Weiß, Farbfeld, Aurora) – gilt für Glas-Dock,
 *                         Tab-Leiste, Aufklappmenü, Such-Popover und Glasblatt; im Dock auch auf der gleitenden Glaslinse
 *   Mobil / ohne backdrop-filter / reduzierte Transparenz: höhere bzw. volle Deckkraft → immer besser als geprüft.
 * Schrift auf Glas und direkt auf dem Farbfeld: Überschriften, Fließtext, Nebentext, Akzent als Schrift ≥ 4,5:1.
 * Akzent als Bedienelement (Buttons, Skalen, Fokusring) ≥ 3:1 (WCAG 1.4.11), Schrift auf Akzent ≥ 4,5:1.
 */
declare(strict_types=1);

$design = require dirname(__DIR__) . '/design.php';

const FF = ['light' => ['soft' => 0.58, 'rich' => 0.78], 'dark' => ['soft' => 0.42, 'rich' => 0.6]];
const AURORA = 0.9;
const SAT = 1.4;
const ALPHA = ['light' => ['light' => 0.62, 'standard' => 0.72, 'dense' => 0.84], 'dark' => ['light' => 0.68, 'standard' => 0.76, 'dense' => 0.86]];
const HEADER = ['light' => 0.8, 'dark' => 0.82];
const HILITE_DARK = 0.05;
const ON_DARK = '#F4F3FF';

/** @return array{float,float,float} */
function rgb(string $hex): array
{
    $h = ltrim($hex, '#');
    return [hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255];
}
function hex(array $c): string
{
    return '#' . implode('', array_map(fn($v) => sprintf('%02X', (int) round(max(0, min(1, $v)) * 255)), $c));
}
function lum(array $c): float
{
    $c = array_map(fn($x) => $x <= 0.04045 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function ratio(array $a, array $b): float
{
    [$x, $y] = [lum($a), lum($b)];
    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}
/** a über b mit Deckkraft $p (0–1) – wie color-mix(in srgb) bzw. Alpha-Überlagerung */
function over(array $a, float $p, array $b): array
{
    return [$a[0] * $p + $b[0] * (1 - $p), $a[1] * $p + $b[1] * (1 - $p), $a[2] * $p + $b[2] * (1 - $p)];
}
/** CSS saturate(s) */
function sat(array $c, float $s): array
{
    [$r, $g, $b] = $c;
    return array_map(fn($v) => max(0, min(1, $v)), [
        (0.213 + 0.787 * $s) * $r + (0.715 - 0.715 * $s) * $g + (0.072 - 0.072 * $s) * $b,
        (0.213 - 0.213 * $s) * $r + (0.715 + 0.285 * $s) * $g + (0.072 - 0.072 * $s) * $b,
        (0.213 - 0.213 * $s) * $r + (0.715 - 0.715 * $s) * $g + (0.072 + 0.928 * $s) * $b,
    ]);
}

$defaults = [];
foreach ($design['groups'] as $g) foreach ($g['tokens'] as $t) {
    if (($t['type'] ?? '') === 'color') { $defaults[$t['name']] = $t['default']; $defaults[$t['name'] . '@dark'] = $t['dark']; }
}

$fail = 0;
$verbose = in_array('-v', $argv, true);
$report = [];
foreach ($design['presets'] as $key => $p) {
    $v = $p['values'] + $defaults;
    foreach (['light' => '', 'dark' => '@dark'] as $mode => $sfx) {
        $c = fn(string $k) => rgb($v[$k . $sfx]);
        $bg = $c('background');
        $fields = [$c('field_1'), $c('field_2'), $c('field_3')];
        // Hintergründe: Grundfarbe, Farbfeld (sanft/kräftig, Fleckmitte und Überlagerungen), Aurora (volle Stärke + Akzent)
        $page = ['Grundfarbe' => $bg];
        foreach (['soft', 'rich'] as $ff) {
            $k = FF[$mode][$ff];
            foreach ($fields as $i => $f) $page["Feld $ff " . ($i + 1)] = over($f, $k, $bg);
            foreach ([[0, 1], [1, 2], [0, 2]] as [$i, $j]) $page["Feld $ff " . ($i + 1) . '+' . ($j + 1)] = over($fields[$i], $k, over($fields[$j], $k, $bg));
        }
        $aurora = [];
        foreach ($fields as $i => $f) $aurora['Aurora ' . ($i + 1)] = over($f, AURORA, $bg);
        $texts = ['Überschrift' => $c('ink'), 'Fließtext' => $c('text'), 'Nebentext' => $c('muted'), 'Akzent-Schrift' => $c('accent_ink')];
        $min = [];
        $check = function (string $what, array $fg, array $bgc, float $need) use (&$min, &$fail, $key, $mode) {
            $r = ratio($fg, $bgc);
            if (!isset($min[$what]) || $r < $min[$what][0]) $min[$what] = [$r, $need];
            if ($r + 1e-9 < $need) { $fail++; echo sprintf("FEHLER %-11s %-5s %-44s %5.2f:1 (mind. %.1f) auf %s\n", $key, $mode, $what, $r, $need, hex($bgc)); }
        };
        // 1. Schrift direkt auf Grundfarbe und Farbfeld (Abschnittsköpfe, Fließtext zwischen den Glasflächen)
        foreach ($page as $where => $b) foreach ($texts as $tn => $t) $check("$tn auf Farbfeld", $t, $b, 4.5);
        // 2. Schrift auf Glas (jede Dichte) über Farbfeld und Aurora, Hintergrund gesättigt
        foreach (ALPHA[$mode] as $dn => $a) {
            foreach ($page + $aurora as $where => $b) {
                $surf = over($c('glass'), $a, sat($b, SAT));
                if ($mode === 'dark') $surf = over([1, 1, 1], HILITE_DARK, $surf);
                foreach ($texts as $tn => $t) $check("$tn auf Glas ($dn)", $t, $surf, 4.5);
                $check("Akzent (Button) auf Glas ($dn)", $c('accent'), $surf, 3);
            }
        }
        // 3. Kopf, Glas-Dock, Tab-Leiste, Aufklappmenü, Glasblatt über beliebigem Inhalt (Schwarz, Weiß, Farbfeld, Aurora);
        //    im Dock zusätzlich auf der Glaslinse (Glas 70 % + Schimmer oben: hell 50 %, dunkel 10 % Weiß)
        foreach (['Schwarz' => [0, 0, 0], 'Weiß' => [1, 1, 1]] + $page + $aurora as $where => $b) {
            $surf = over($c('glass'), HEADER[$mode], sat($b, SAT));
            $lens = over([1, 1, 1], ($mode === 'dark' || lum($bg) < .18) ? .1 : .5, over($c('glass'), .7, $surf));   // is-darkbase: dunkle Linse wie im Dunkeln
            // Im Kopf, in Tab-Leiste und Aufklappmenü stehen nur Überschrift-, Fließtext- und Akzent-Schrift (keine Nebentext-Farbe)
            foreach (['Überschrift' => $c('ink'), 'Fließtext' => $c('text'), 'Akzent-Schrift' => $c('accent_ink')] as $tn => $t) $check("$tn im Kopf/Menü", $t, $surf, 4.5);
            foreach (['Überschrift' => $c('ink'), 'Fließtext' => $c('text')] as $tn => $t) $check("$tn auf der Glaslinse", $t, $lens, 4.5);
            $check('Akzent (Symbol, Button) im Kopf', $c('accent'), $surf, 3);
        }
        // 4. Bedienelemente
        $check('Schrift auf Akzent', $c('on_accent'), $c('accent'), 4.5);
        foreach ($page as $where => $b) $check('Akzent (Button) auf Farbfeld', $c('accent'), $b, 3);
        // 5. Abschnitt „Nacht“: helle Schrift, Nebentext 80 %, Glas darin (Weiß 8 %)
        $dark = $c('dark_section');
        $check('Schrift in „Nacht“', rgb(ON_DARK), $dark, 7);
        $check('Nebentext in „Nacht“', over(rgb(ON_DARK), .8, $dark), over([1, 1, 1], .08, $dark), 4.5);
        // 6. Abschnitt „Akzentfarbe“: Nebentext = Schrift auf Akzent, auf Glas darin (Schrift auf Akzent 6 %)
        $check('Nebentext auf Glas in Akzent', $c('on_accent'), over($c('on_accent'), .06, $c('accent')), 4.5);
        $report[$key][$mode] = $min;
    }
}

if ($verbose) {
    foreach ($report as $key => $modes) foreach ($modes as $mode => $min) {
        echo "\n$key · $mode\n";
        foreach ($min as $what => [$r, $need]) echo sprintf("  %-44s %6.2f:1  (mind. %.1f)\n", $what, $r, $need);
    }
}
echo $fail ? "\n$fail Verstöße.\n" : "Alle Vorlagen bestehen WCAG 2.2 AA (hell + dunkel, jede Glasdichte, sanftes und kräftiges Farbfeld, Aurora).\n";
exit($fail ? 1 : 0);
