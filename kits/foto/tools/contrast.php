<?php
/**
 * Kontrastprüfung aller Voreinstellungen des Kits „foto“ (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/foto/tools/contrast.php        (Exit-Code 1 bei Verstößen, -v zeigt alle Werte)
 *
 * Geprüft werden die Farbpaare, die das Theme-CSS tatsächlich verwendet – auch abgeleitete Farben, genau wie in
 * assets/css/_tokens.css berechnet (color-mix in sRGB):
 *   --f-a-soft   = Akzent 14 % in den Hintergrund gemischt (Abschnitt „Akzent hell“, Etiketten, Hinweise)
 *   Nebentext in dunklen Abschnitten = Weiß 80 % über der Fläche, im Abschnitt „Akzentfarbe“ = Schrift auf Akzent (auf Karten: 7 % getönt)
 * Rechnung und Ausgabe: Core\Contrast.
 */
declare(strict_types=1);

use Core\Contrast;

// Kern-Klassen (design.php nutzt Kopfbereich-Aktionen, Core\Contrast) – Autoloader des Projekts laden, falls vorhanden
if (is_file($al = dirname(__DIR__, 3) . '/vendor/autoload.php')) require_once $al;
require_once dirname(__DIR__, 3) . '/app/Contrast.php';
$design = require dirname(__DIR__) . '/design.php';

// Zweite Markenfarbe: in älteren Vorlagen nicht gesetzt → Standard des Tokens
$defaults = ['secondary' => '#6B6B6B', 'secondary@dark' => '#A6A6A6', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#111111'];
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
        'Zweitfarbe: Überschrift' => [$v('ink'), $v('highlight'), 4.5],
        'Zweite Markenfarbe / Hintergrund (Grafik)' => [$v('secondary'), $v('background'), 3],
        'Schrift auf zweiter Markenfarbe' => [$v('on_secondary'), $v('secondary'), 4.5],
        'Zweitfarbe: Fließtext' => [$v('text'), $v('highlight'), 4.5],
        'Abschnitt Akzent: Nebentext auf Karte (7 %)' => [$v('on_accent'), Contrast::mix($v('on_accent'), $v('accent'), 7), 4.5],
        'Abschnitt Dunkel: Weiß / Fläche' => [$white, $v('dark_section'), 7],
        'Abschnitt Dunkel: Nebentext (80 % Weiß)' => [Contrast::mix($white, $v('dark_section'), 80), $v('dark_section'), 4.5],
        'Formularrahmen (Nebentext) / Hintergrund' => [$v('muted'), $v('background'), 3],
        'Fokusring (Akzent) / getönt' => [$v('accent'), $v('surface'), 3],
    ];
}, ['width' => [11, 44], 'defaults' => $defaults, 'after' => function (bool $verbose): int {
    // Bühne und Bildunterschrift „auf dem Bild“: Weiß auf dem Verlauf (mind. 55 % Schwarz) – schlimmster Fall: weißes Bild
    $scrim = Contrast::mix('#000000', '#FFFFFF', 55);
    if (Contrast::ratio('#FFFFFF', $scrim) < 4.5) { printf("✗ Verlauf der Bühne: Weiß auf %s nur %.2f\n", $scrim, Contrast::ratio('#FFFFFF', $scrim)); return 1; }
    if ($verbose) printf("✓ Verlauf der Bühne/Bildunterschrift: Weiß auf weißem Bild mit 55 %% Schwarz %5.2f\n", Contrast::ratio('#FFFFFF', $scrim));
    return 0;
}]));
