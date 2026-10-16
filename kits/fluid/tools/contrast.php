<?php
/**
 * Kontrastprüfung aller Voreinstellungen des Themes „fluid“ (hell + dunkel) nach WCAG 2.2 AA – ohne CMS lauffähig:
 *   php kits/fluid/tools/contrast.php        (Exit-Code 1 bei Verstößen, -v zeigt alle Werte)
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

// Zweite Markenfarbe und Farben 3–5: in älteren Vorlagen nicht gesetzt → Standard des Tokens
$defaults = ['secondary' => '#0F766E', 'secondary@dark' => '#5EEAD4', 'on_secondary' => '#FFFFFF', 'on_secondary@dark' => '#062B27',
    'color3' => '#B45309', 'color3@dark' => '#FCD34D', 'color4' => '#BE185D', 'color4@dark' => '#F9A8D4', 'color5' => '#0369A1', 'color5@dark' => '#7DD3FC'];
exit(Contrast::run($design['presets'], function (callable $v, string $sfx, string $key, array $p): array {
    $soft = Contrast::mix($v('accent'), $v('background'), 14);
    $white = '#FFFFFF';
    $checks = [
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
    // Farbwirkung „Farbenfroh“ (opt-palette-colorful.css): fünf Farben als Grafik (≥ 3 : 1) und als zarte Tönung
    // (14 % in den Hintergrund) unter Überschrift, Text, Nebentext und Links. Pflicht für Vorlagen mit palette = colorful;
    // für die übrigen nur mit -v als Hinweis (die Farben dort sind Standardwerte, die erst beim Einschalten zählen).
    $colorful = ($p['values']['palette'] ?? 'brand') === 'colorful';
    if ($colorful || Contrast::verbose()) {
        $hint = $colorful ? [] : ['(nur bei „Farbenfroh“)'];
        foreach (['accent', 'secondary', 'color3', 'color4', 'color5'] as $n => $tk) {
            $tone = $v($tk);
            $tsoft = Contrast::mix($tone, $v('background'), 14);
            $pre = 'Farbe ' . ($n + 1) . ': ';
            $checks[$pre . 'Grafik / Hintergrund'] = [$tone, $v('background'), 3, ...$hint];
            $checks[$pre . 'Grafik / getönt'] = [$tone, $v('surface'), 3, ...$hint];
            $checks[$pre . 'Überschrift auf Tönung'] = [$v('ink'), $tsoft, 4.5, ...$hint];
            $checks[$pre . 'Fließtext auf Tönung'] = [$v('text'), $tsoft, 4.5, ...$hint];
            $checks[$pre . 'Nebentext auf Tönung'] = [$v('muted'), $tsoft, 4.5, ...$hint];
            $checks[$pre . 'Link auf Tönung'] = [$v('accent'), $tsoft, 4.5, ...$hint];
        }
    }
    return $checks;
}, ['width' => [11, 44], 'defaults' => $defaults]));
