<?php
/**
 * Kontrastprüfung aller Voreinstellungen des Themes „essenz“ (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/essenz/tools/contrast.php        (Exit-Code 1 bei Verstößen, -v zeigt alle Werte)
 *
 * Geprüft werden die Farbpaare, die das Theme-CSS tatsächlich verwendet – auch abgeleitete Farben, genau wie in
 * assets/css/_tokens.css berechnet (color-mix in sRGB):
 *   --e-a-soft  = Signal 16 % in den Hintergrund gemischt (Abschnitt „Signal hell“, Etiketten)
 *   Nebentext im Nachtpaneel = Nachtschrift 78 % über der Fläche; im Abschnitt „Signalfarbe“ = Schrift auf Signal (Flächen darin nur 4 % getönt)
 *   Signal als Bedienelement (Tasten, Skalen, Fokusring) braucht 3:1 (WCAG 1.4.11 Nicht-Text-Kontrast).
 * Rechnung und Ausgabe: Core\Contrast.
 */
declare(strict_types=1);

use Core\Contrast;

// Kern-Klassen (design.php nutzt Kopfbereich-Aktionen, Core\Contrast) – Autoloader des Projekts laden, falls vorhanden
if (is_file($al = dirname(__DIR__, 3) . '/vendor/autoload.php')) require_once $al;
require_once dirname(__DIR__, 3) . '/app/Contrast.php';
$design = require dirname(__DIR__) . '/design.php';

$night = '#F2F1EE';   // Schrift im Nachtpaneel (--e-on-dark)
exit(Contrast::run($design['presets'], function (callable $v) use ($night): array {
    $soft = Contrast::mix($v('accent'), $v('background'), 16);
    return [
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
        'Abschnitt Signal: Schrift auf Fläche (4 %)' => [$v('on_accent'), Contrast::mix($v('on_accent'), $v('accent'), 4), 4.5],
        'Nachtpaneel: Schrift / Fläche' => [$night, $v('dark_section'), 7],
        'Nachtpaneel: Nebentext (78 %)' => [Contrast::mix($night, $v('dark_section'), 78), $v('dark_section'), 4.5],
        'Nachtpaneel: Signal als Bedienelement' => [$v('accent'), $v('dark_section'), 3],
        'Formularrahmen (Linie kräftig) / Paneel' => [Contrast::mix($v('ink'), $v('panel'), 55), $v('panel'), 3],
        'Fokusring (Signal-Schrift) / getönt' => [$v('accent_ink'), $v('surface'), 3],
    ];
}, ['width' => [9, 42]]));
