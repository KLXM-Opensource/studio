<?php
/**
 * Kontrastprüfung aller Voreinstellungen des Kits „modern“ (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/modern/tools/contrast.php        (Exit-Code 1 bei Verstößen, -v zeigt alle Werte)
 *
 * Geprüft werden die Farbpaare, die das Theme-CSS tatsächlich verwendet – auch abgeleitete Farben, genau wie in
 * assets/css/_tokens.css berechnet (color-mix in sRGB):
 *   --m-a-soft   = Akzent 14 % in den Hintergrund gemischt (Abschnitt „Akzent hell“, Etiketten, Hinweise)
 *   Nebentext in dunklen Abschnitten = Weiß 80 % über der Fläche, im Abschnitt „Akzentfarbe“ = Schrift auf Akzent (auf Karten: 6 % getönt)
 * Rechnung und Ausgabe: Core\Contrast.
 */
declare(strict_types=1);

use Core\Contrast;

// Kern-Klassen (design.php nutzt Kopfbereich-Aktionen, Core\Contrast) – Autoloader des Projekts laden, falls vorhanden
if (is_file($al = dirname(__DIR__, 3) . '/vendor/autoload.php')) require_once $al;
require_once dirname(__DIR__, 3) . '/app/Contrast.php';
$design = require dirname(__DIR__) . '/design.php';

exit(Contrast::run($design['presets'], function (callable $v): array {
    $soft = Contrast::mix($v('accent'), $v('background'), 14);
    $white = '#FFFFFF';
    return [
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
        'Blockfarbe: Überschrift' => [$v('ink'), $v('pop'), 4.5],
        'Blockfarbe: Fließtext' => [$v('text'), $v('pop'), 4.5],
        'Blockfarbe: Nebentext' => [$v('muted'), $v('pop'), 4.5],
        'Nebentext / Akzent hell' => [$v('muted'), $soft, 4.5],
        'Button-Rahmen Kontur (Überschrift) / Hintergrund' => [$v('ink'), $v('background'), 3],
        'Abschnitt Akzent: Nebentext auf Karte (6 %)' => [$v('on_accent'), Contrast::mix($v('on_accent'), $v('accent'), 6), 4.5],
        'Abschnitt Dunkel: Weiß / Fläche' => [$white, $v('dark_section'), 7],
        'Abschnitt Dunkel: Nebentext (80 % Weiß)' => [Contrast::mix($white, $v('dark_section'), 80), $v('dark_section'), 4.5],
        'Formularrahmen (Nebentext) / Hintergrund' => [$v('muted'), $v('background'), 3],
        'Fokusring (Akzent) / getönt' => [$v('accent'), $v('surface'), 3],
    ];
}, ['width' => [10, 44]]));
