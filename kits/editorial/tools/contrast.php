<?php
/**
 * Kontrastprüfung aller Voreinstellungen des Themes „editorial“ (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/editorial/tools/contrast.php        (Exit-Code 1 bei Verstößen; -v zeigt alle Werte)
 *
 * Geprüft werden die Farbpaare, die das Theme-CSS tatsächlich verwendet – auch abgeleitete Farben wie in site.css:
 *   --e-a-soft   Akzent zu 10 % (dunkel 16 %) in den Papierton gemischt (Markierungen, Etiketten, „Kurz notiert“)
 *   Nachtausgabe (Abschnitt „dunkel“, helles Schema): Schrift Weiß, Nebentext 80 % Weiß, Dachzeile/Links = Akzent zu 40 % in Weiß
 *   Akzentfläche (helles Schema): Schrift auf Akzent, Nebentext 88 % davon
 * Rechnung und Ausgabe: Core\Contrast.
 */
declare(strict_types=1);

use Core\Contrast;

// Kern-Klassen (design.php nutzt Kopfbereich-Aktionen, Core\Contrast) – Autoloader des Projekts laden, falls vorhanden
if (is_file($al = dirname(__DIR__, 3) . '/vendor/autoload.php')) require_once $al;
require_once dirname(__DIR__, 3) . '/app/Contrast.php';
$design = require dirname(__DIR__) . '/design.php';

exit(Contrast::run($design['presets'], function (callable $v, string $sfx): array {
    $soft = Contrast::mix($v('accent'), $v('background'), $sfx ? 16 : 10);
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
        'Linie kräftig (Tinte 30 %) / Papier (Grafik)' => [Contrast::mix($v('ink'), $v('background'), 30), $v('background'), 1.3],
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
            'Abschnitt Akzent: Nebentext (88 %) / Fläche' => [Contrast::mix($v('on_accent'), $v('accent'), 88), $v('accent'), 4.5],
            'Abschnitt Akzent: Button (Akzent auf Schrift)' => [$v('accent'), $v('on_accent'), 4.5],
            'Nachtausgabe: Weiß / Fläche' => [$white, $v('dark_section'), 7],
            'Nachtausgabe: Nebentext (80 % Weiß) / Fläche' => [Contrast::mix($white, $v('dark_section'), 80), $v('dark_section'), 4.5],
            'Nachtausgabe: Dachzeile (Akzent 40 % in Weiß)' => [Contrast::mix($v('accent'), $white, 40), $v('dark_section'), 4.5],
        ];
    }
    return $checks;
}, ['width' => [13, 46]]));
