<?php
/**
 * Kontrastprüfung aller Voreinstellungen des Kits „nature“ (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/nature/tools/contrast.php        (Exit-Code 1 bei Verstößen, -v zeigt alle Werte, --table Kurzübersicht)
 *
 * Geprüft werden die Farbpaare, die das Kit-CSS tatsächlich verwendet – auch abgeleitete Farben, genau wie in
 * assets/css/_tokens.css berechnet (color-mix in sRGB):
 *   --n-a-soft  = Akzent 16 % in den Hintergrund gemischt (Abschnitt „Moos hell“, Etiketten)
 *   Nebentext in Waldnacht = helle Schrift 78 % über der Fläche; im Abschnitt „Akzentfarbe“ = Schrift auf Akzent (Flächen darin nur 4 % getönt)
 *   Akzent in Waldnacht = Akzent 45 % in die helle Schrift gemischt (Buttons dort mit dunkler Schrift)
 *   Akzent als Bedienelement (Buttons, Skalen, Marken) und „Erde“ als Markierung brauchen 3:1 (WCAG 1.4.11).
 * Rechnung und Ausgabe: Core\Contrast.
 */
declare(strict_types=1);

use Core\Contrast;

// Kern-Klassen (design.php nutzt Kopfbereich-Aktionen, Core\Contrast) – Autoloader des Projekts laden, falls vorhanden
if (is_file($al = dirname(__DIR__, 3) . '/vendor/autoload.php')) require_once $al;
require_once dirname(__DIR__, 3) . '/app/Contrast.php';
$design = require dirname(__DIR__) . '/design.php';

$night = '#F1EEE3';   // Schrift in Waldnacht (--n-on-dark)
exit(Contrast::run($design['presets'], function (callable $v) use ($night): array {
    $soft = Contrast::mix($v('accent'), $v('background'), 16);
    $ui = Contrast::mix($v('accent'), $night, 45);
    return [
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
        'Abschnitt Akzent: Schrift auf Fläche (4 %)' => [$v('on_accent'), Contrast::mix($v('on_accent'), $v('accent'), 4), 4.5],
        'Waldnacht: Schrift / Fläche' => [$night, $v('dark_section'), 7],
        'Waldnacht: Nebentext (78 %)' => [Contrast::mix($night, $v('dark_section'), 78), $v('dark_section'), 4.5],
        'Waldnacht: Akzent als Bedienelement' => [$ui, $v('dark_section'), 3],
        'Waldnacht: Button-Schrift / Akzent' => [$v('dark_section'), $ui, 4.5],
        'Formularrahmen (Linie kräftig) / Karte' => [Contrast::mix($v('ink'), $v('panel'), 55), $v('panel'), 3],
        'Fokusring (Akzent-Schrift) / Sand' => [$v('accent_ink'), $v('surface'), 3],
    ];
}, ['width' => [9, 42], 'after' => function (bool $verbose, array $rows): int {
    if (in_array('--table', (array) ($_SERVER['argv'] ?? []), true)) {
        // Kurzübersicht (Markdown): wichtigste Paare je Vorlage, hell / dunkel
        $cols = ['Fließtext / Hintergrund', 'Nebentext / Sand', 'Akzent-Schrift / Hintergrund', 'Button: Schrift / Akzent', 'Akzent als Bedienelement / Hintergrund', 'Erde als Markierung / Hintergrund', 'Waldnacht: Nebentext (78 %)', 'Waldnacht: Akzent als Bedienelement'];
        echo '| Vorlage | ' . implode(' | ', $cols) . " |\n|" . str_repeat('---|', count($cols) + 1) . "\n";
        foreach ($rows as $key => $modes) {
            echo '| ' . $key . ' | ' . implode(' | ', array_map(fn($c) => number_format($modes['hell'][$c], 2) . ' / ' . number_format($modes['dunkel'][$c], 2), $cols)) . " |\n";
        }
    }
    return 0;
}]));
