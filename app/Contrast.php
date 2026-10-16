<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Farbkontrast nach WCAG 2.x – eine Rechnung für Kern, Kits und die Prüfwerkzeuge der Kits.
 *
 *   Contrast::luminance('#0F766E')            relative Helligkeit (0–1)
 *   Contrast::ratio('#FFFFFF', '#0F766E')     Kontrastverhältnis 1–21, auf zwei Stellen gerundet
 *   Contrast::mix('#0F766E', '#FFFFFF', 14)   color-mix(in srgb, #0F766E 14%, #FFFFFF) als #RRGGBB
 *
 * Für Zwischenwerte (überlagerte, gefilterte Farben) rechnen relative() und ratioRgb() mit Kanälen 0–1 ohne Rundung.
 * Die Schwelle der sRGB-Linearisierung ist wählbar: WCAG 2.x nennt 0,03928, die sRGB-Norm 0,04045 (das Kit „glas“ rechnet
 * so) – bei 8-Bit-Farben ergibt beides dieselben Werte, die Prüfwerkzeuge bleiben trotzdem bei ihrer Schwelle.
 *
 * run() ist der gemeinsame Rahmen der Kommandozeilen-Werkzeuge kits/{kit}/tools/contrast.php: Jede Voreinstellung wird hell
 * und dunkel geprüft, das Kit liefert nur die Farbpaare (ohne CMS lauffähig – die Klasse hat keine Abhängigkeiten).
 */
final class Contrast
{
    /** Schwelle der sRGB-Linearisierung nach WCAG 2.x */
    public const THRESHOLD = 0.03928;

    /**
     * #RRGGBB (mit oder ohne #) in Kanäle 0–1
     * @return array{0: float, 1: float, 2: float}
     */
    public static function rgb(string $hex): array
    {
        $c = array_map(fn($i) => hexdec(substr(ltrim($hex, '#'), $i, 2)) / 255, [0, 2, 4]);
        return [$c[0], $c[1], $c[2]];
    }

    /** Relative Helligkeit (WCAG) einer #RRGGBB-Farbe */
    public static function luminance(string $hex): float
    {
        return self::relative(self::rgb($hex));
    }

    /** Relative Helligkeit aus Kanälen 0–1 (auch Zwischenwerte gemischter oder gefilterter Farben) */
    public static function relative(array $c, float $threshold = self::THRESHOLD): float
    {
        $c = array_map(fn($x) => $x <= $threshold ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    /** Kontrastverhältnis zweier #RRGGBB-Farben (1–21, zwei Nachkommastellen) */
    public static function ratio(string $a, string $b): float
    {
        return round(self::ratioRgb(self::rgb($a), self::rgb($b)), 2);
    }

    /** Kontrastverhältnis aus Kanälen 0–1, ungerundet */
    public static function ratioRgb(array $a, array $b, float $threshold = self::THRESHOLD): float
    {
        [$x, $y] = [self::relative($a, $threshold), self::relative($b, $threshold)];
        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    /** color-mix(in srgb, $a $pct%, $b) – $pct ist der Anteil von $a in Prozent; Ergebnis #RRGGBB */
    public static function mix(string $a, string $b, float $pct): string
    {
        $out = '#';
        foreach ([0, 2, 4] as $i) {
            $va = hexdec(substr(ltrim($a, '#'), $i, 2));
            $vb = hexdec(substr(ltrim($b, '#'), $i, 2));
            $out .= sprintf('%02X', (int) round($va * $pct / 100 + $vb * (1 - $pct / 100)));
        }
        return $out;
    }

    /**
     * Standardfarben der Design-Tokens (Typ color) hell und dunkel: ['accent' => …, 'accent@dark' => …] – für Voreinstellungen,
     * die nur einen Teil der Farben nennen (Option 'defaults' von run()).
     * @return array<string, string>
     */
    public static function tokenDefaults(array $design): array
    {
        $out = [];
        foreach ($design['groups'] ?? [] as $g) foreach ($g['tokens'] ?? [] as $t) {
            if (($t['type'] ?? '') !== 'color') continue;
            $out[$t['name']] = $t['default'];
            $out[$t['name'] . '@dark'] = $t['dark'];
        }
        return $out;
    }

    /** Ausführliche Ausgabe gewünscht (Schalter -v auf der Kommandozeile) */
    public static function verbose(): bool
    {
        return in_array('-v', (array) ($_SERVER['argv'] ?? []), true);
    }

    /**
     * Kontrastprüfung aller Voreinstellungen hell und dunkel für die Kommandozeile; Rückgabe ist der Exit-Code (1 bei Verstößen).
     *
     * $checks(callable $v, string $sfx, string $key, array $preset) liefert die Prüfungen einer Voreinstellung in einem Schema:
     *   ['Fließtext / Hintergrund' => [$v('text'), $v('background'), 4.5], …] – $v('accent') ist die Farbe des Schemas
     *   (hell: accent, dunkel: accent@dark), $sfx ist '' bzw. '@dark'. Ein vierter Wert macht aus dem Unterschreiten einen
     *   Hinweis statt eines Verstoßes (der Text steht am Ende der Hinweiszeile).
     *
     * $opt:
     *   defaults  Farben für Voreinstellungen, die sie nicht nennen (Schlüssel name bzw. name@dark, siehe tokenDefaults())
     *   width     [Breite der Spalte Voreinstellung, Breite der Spalte Prüfung] der Standardausgabe (Vorgabe [10, 44])
     *   modes     Beschriftung => Suffix (Vorgabe ['hell' => '', 'dunkel' => '@dark'])
     *   row       fn(key, mode, label, fg, bg, ratio, min, verbose): eigene Ausgabe je Prüfung statt ✗/✓-Zeilen
     *   after     fn(bool $verbose, array $results): int – weitere Prüfungen/Ausgaben vor der Zusammenfassung, liefert die Zahl
     *             weiterer Verstöße; $results[key][mode][label] = Verhältnis
     *   summary   fn(int $fail, int $presets): string – eigene Zusammenfassung
     */
    public static function run(array $presets, callable $checks, array $opt = []): int
    {
        $verbose = self::verbose();
        [$kw, $lw] = $opt['width'] ?? [10, 44];
        $defaults = (array) ($opt['defaults'] ?? []);
        $fail = 0;
        $results = [];
        foreach ($presets as $key => $p) {
            $vals = $p['values'] + $defaults;
            foreach ($opt['modes'] ?? ['hell' => '', 'dunkel' => '@dark'] as $mode => $sfx) {
                $v = fn(string $k) => $vals[$k . $sfx];
                foreach ($checks($v, $sfx, (string) $key, $p) as $label => $c) {
                    [$fg, $bg, $min] = $c;
                    $r = self::ratio($fg, $bg);
                    $results[$key][$mode][$label] = $r;
                    if (isset($opt['row'])) {
                        if ($r < $min) $fail++;
                        ($opt['row'])((string) $key, $mode, (string) $label, $fg, $bg, $r, (float) $min, $verbose);
                        continue;
                    }
                    if ($r < $min && isset($c[3])) {
                        printf("  Hinweis %-{$kw}s %-7s %-{$lw}s %5.2f < %.1f %s\n", $key, $mode, $label, $r, $min, $c[3]);
                        continue;
                    }
                    if ($r < $min) {
                        $fail++;
                        printf("✗ %-{$kw}s %-7s %-{$lw}s %5.2f < %.1f  (%s auf %s)\n", $key, $mode, $label, $r, $min, $fg, $bg);
                    } elseif ($verbose) {
                        printf("✓ %-{$kw}s %-7s %-{$lw}s %5.2f\n", $key, $mode, $label, $r);
                    }
                }
            }
        }
        if (isset($opt['after'])) $fail += (int) ($opt['after'])($verbose, $results);
        echo isset($opt['summary']) ? ($opt['summary'])($fail, count($presets))
            : ($fail ? "\n$fail Verstöße.\n" : 'Alle ' . count($presets) . " Voreinstellungen erfüllen WCAG 2.2 AA (hell und dunkel).\n");
        return $fail ? 1 : 0;
    }
}
