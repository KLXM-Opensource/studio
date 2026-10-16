<?php
// SPDX-License-Identifier: MIT
/**
 * Kontrastprüfung aller Voreinstellungen (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/starter/tools/contrast.php        (Exit-Code 1 bei Verstößen, -v zeigt alle Werte)
 * Geprüft werden die Paare, die das Kit-CSS tatsächlich verwendet (assets/css/_tokens.css, _base.css) – auch die
 * abgeleiteten Farben der Abschnitte „Akzentfarbe“ (color-mix) und „Dunkel“ (feste helle Schrift).
 * Eigene Farben/Rollen ergänzt? Hier ein Paar mehr eintragen. Rechnung und Ausgabe: Core\Contrast.
 */
declare(strict_types=1);

use Core\Contrast;

// Kern-Klassen (Core\Contrast) – Autoloader des Projekts laden, falls vorhanden
if (is_file($al = dirname(__DIR__, 3) . '/vendor/autoload.php')) require_once $al;
require_once dirname(__DIR__, 3) . '/app/Contrast.php';
$design = (require dirname(__DIR__) . '/theme.php')['design'];

exit(Contrast::run($design['presets'], fn(callable $v): array => [
    'Fließtext / Hintergrund' => [$v('text'), $v('background'), 4.5],
    'Fließtext / getönt' => [$v('text'), $v('surface'), 4.5],
    'Überschrift / Hintergrund' => [$v('ink'), $v('background'), 7],
    'Nebentext / Hintergrund' => [$v('muted'), $v('background'), 4.5],
    'Nebentext / getönt' => [$v('muted'), $v('surface'), 4.5],
    'Link / Hintergrund' => [$v('accent'), $v('background'), 4.5],
    'Link / getönt' => [$v('accent'), $v('surface'), 4.5],
    'Button: Schrift / Akzent' => [$v('on_accent'), $v('accent'), 4.5],
    'Abschnitt Akzent: Nebentext auf Karte' => [$v('on_accent'), Contrast::mix($v('on_accent'), $v('accent'), 10), 4.5],
    'Abschnitt Dunkel: Fließtext' => ['#DDE2E9', $v('dark_section'), 4.5],
    'Abschnitt Dunkel: Nebentext' => ['#AEB6C2', $v('dark_section'), 4.5],
    'Linie (stark) / Hintergrund' => [Contrast::mix($v('text'), $v('background'), 55), $v('background'), 3],   // Rahmen von Eingabefeldern (1.4.11)
], [
    'defaults' => Contrast::tokenDefaults($design),   // Standardwerte der Tokens – Voreinstellungen überschreiben nur, was sie nennen
    'row' => function (string $key, string $mode, string $label, string $fg, string $bg, float $r, float $min, bool $verbose): void {
        if ($r < $min || $verbose) printf("%-11s %-7s %-32s %s / %s  %5.2f:1 (min %.1f) %s\n", $key, $mode, $label, $fg, $bg, $r, $min, $r < $min ? 'FEHLER' : 'ok');
    },
    'summary' => fn(int $fail, int $count): string => $fail ? "$fail Prüfungen nicht bestanden.\n" : 'Alle ' . $count . " Voreinstellungen bestehen WCAG 2.2 AA (hell + dunkel).\n",
]));
