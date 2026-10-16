<?php
/**
 * Kontrastprüfung aller Voreinstellungen (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/basis/tools/contrast.php            (Exit-Code 1 bei Verstößen)
 *
 * Geprüft werden die Farbpaare, die das Theme-CSS tatsächlich verwendet – auch abgeleitete Farben
 * (z. B. --b-a-soft = Akzent zu 11 % bzw. 16 % in den Hintergrund gemischt, wie in site.css).
 * Rechnung und Ausgabe: Core\Contrast.
 */
declare(strict_types=1);

use Core\Contrast;

// Kern-Klassen (design.php nutzt Kopfbereich-Aktionen, Core\Contrast) – Autoloader des Projekts laden, falls vorhanden
if (is_file($al = dirname(__DIR__, 3) . '/vendor/autoload.php')) require_once $al;
require_once dirname(__DIR__, 3) . '/app/Contrast.php';
$design = require dirname(__DIR__) . '/design.php';

exit(Contrast::run($design['presets'], function (callable $v, string $sfx): array {
    $soft = Contrast::mix($v('accent'), $v('background'), $sfx ? 16 : 11);
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
            'Abschnitt Dunkel: Nebentext (88 % Weiß) / Fläche' => [Contrast::mix($white, $v('dark_section'), 88), $v('dark_section'), 4.5],
        ];
    }
    return $checks;
}, ['width' => [10, 48]]));
