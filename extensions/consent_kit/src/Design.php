<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace MyCms\Consent;

use Core\Design as SiteDesign;

/**
 * Gestaltung des Hinweises: CSS Custom Properties (--ck-*), deren Standardwerte aus den Design-Tokens der Website
 * (Core\Design, Verwaltung → Design) abgeleitet werden – hell und dunkel, sofern das Theme einen Dunkelmodus hat.
 * Abweichungen aus dem Design-Editor der Erweiterung überschreiben die abgeleiteten Werte. Ausgeliefert wird eine Datei
 * /consent/style.css (Basis-CSS der Komponente + Variablen), geladen im Shadow DOM – keine Inline-Styles.
 */
final class Design
{
    /** Farben mit Dunkel-Wert: name => [Bezeichnung, Standard hell, Standard dunkel] */
    public const COLORS = [
        'bg' => ['Hintergrund', '#FFFFFF', '#18181B'],
        'text' => ['Text', '#1A1A1A', '#F4F4F5'],
        'muted' => ['Nebentext', '#595959', '#B4B4BB'],
        'border' => ['Rahmen, Schalter aus', '#767676', '#8E8E96'],
        'line' => ['Trennlinien', '#D9D9D9', '#3F3F46'],
        'accent' => ['Akzent (Links, Schalter an, Fokus)', '#1D4ED8', '#93C5FD'],
        'button_bg' => ['Schaltflächen', '#1F2937', '#F4F4F5'],
        'button_text' => ['Schrift auf Schaltflächen', '#FFFFFF', '#18181B'],
        'button_border' => ['Rahmen der Schaltflächen', '#1F2937', '#F4F4F5'],
    ];
    /** Maße: name => [Bezeichnung, Einheit, min, max, Schritt, Standard] */
    public const SIZES = [
        'radius' => ['Ecken von Hinweis und Platzhalter', 'px', 0, 32, 1, 12],
        'button_radius' => ['Ecken der Schaltflächen', 'px', 0, 999, 1, 8],
        'width' => ['Breite von Box und Dialog', 'rem', 20, 44, 1, 30],
        'font_size' => ['Schriftgröße', 'rem', 0.8, 1.25, 0.025, 1],
    ];
    /** Kontrastprüfungen: [Vordergrund, Hintergrund, Minimum, Bezeichnung] */
    public const CHECKS = [
        ['text', 'bg', 4.5, 'Text auf Hintergrund'],
        ['muted', 'bg', 4.5, 'Nebentext auf Hintergrund'],
        ['accent', 'bg', 4.5, 'Links auf Hintergrund'],
        ['button_text', 'button_bg', 4.5, 'Schrift auf Schaltflächen'],
        ['button_border', 'bg', 3.0, 'Rahmen der Schaltflächen'],
        ['border', 'bg', 3.0, 'Rahmen und Schalter'],
    ];

    /** Aus den Design-Tokens der Website abgeleitete Werte (hell + „name@dark“) */
    public static function derived(): array
    {
        $out = [];
        foreach (self::COLORS as $k => [, $l, $d]) { $out[$k] = $l; $out[$k . '@dark'] = $d; }
        foreach (self::SIZES as $k => $def) $out[$k] = $def[5];
        try {
            if (!SiteDesign::enabled()) return $out;
            $tokens = SiteDesign::tokens();
            $values = SiteDesign::values();
            $hasDark = !empty(SiteDesign::def()['dark']);
            $pick = function (array $names) use ($tokens, $values, $hasDark): ?array {
                foreach ($names as $n) {
                    if (isset($tokens[$n]) && ($tokens[$n]['type'] ?? 'color') === 'color' && isset($values[$n])) {
                        return [$values[$n], $hasDark ? ($values[$n . '@dark'] ?? null) : null];
                    }
                }
                return null;
            };
            $map = [
                'bg' => ['background', 'bg', 'paper', 'base', 'canvas', 'white'],
                'text' => ['text', 'body', 'ink', 'foreground', 'fg'],
                'muted' => ['muted', 'text_2', 'text_muted', 'subtle', 'secondary_text'],
                'line' => ['line', 'border', 'rule', 'divider'],
                'accent' => ['accent', 'primary', 'brand'],
                'button_text' => ['on_accent', 'accent_on', 'on_primary'],
            ];
            foreach ($map as $k => $names) {
                if ($v = $pick($names)) {
                    $out[$k] = $v[0];
                    if ($v[1]) $out[$k . '@dark'] = $v[1];
                }
            }
            // Schaltflächen im Akzent der Website – alle drei gleich (Ablehnen so auffällig wie Akzeptieren)
            foreach (['', '@dark'] as $m) {
                $out['button_bg' . $m] = $out['accent' . $m];
                $out['button_border' . $m] = $out['accent' . $m];
                if (!$pick($map['button_text']) || SiteDesign::contrast($out['button_text' . $m], $out['button_bg' . $m]) < 4.5) {
                    $out['button_text' . $m] = SiteDesign::contrast('#FFFFFF', $out['button_bg' . $m]) >= SiteDesign::contrast('#111111', $out['button_bg' . $m]) ? '#FFFFFF' : '#111111';
                }
                // Rahmen/Schalter: Nebentext (erreicht 3:1 sicher), wenn der Standard zu schwach ist
                if (SiteDesign::contrast($out['border' . $m], $out['bg' . $m]) < 3) $out['border' . $m] = $out['muted' . $m];
            }
            foreach ($tokens as $n => $t) {
                if (($t['type'] ?? '') === 'range' && $n === 'radius' && is_numeric($values[$n] ?? null)) {
                    $r = (float) $values[$n];
                    $out['button_radius'] = $r;
                    $out['radius'] = min(32, $r + 6);
                }
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    /** Gespeicherte Abweichungen (nur gültige Werte) */
    public static function overrides(): array
    {
        return self::clean((array) app()->settings->get('consent.design', []));
    }

    public static function clean(array $in): array
    {
        $out = [];
        foreach (self::COLORS as $k => $_) {
            foreach (['', '@dark'] as $m) {
                $c = SiteDesign::color($in[$k . $m] ?? null);
                if ($c) $out[$k . $m] = $c;
            }
        }
        foreach (self::SIZES as $k => [, , $min, $max]) {
            if (isset($in[$k]) && $in[$k] !== '' && is_numeric($in[$k])) $out[$k] = max($min, min($max, (float) $in[$k]));
        }
        return $out;
    }

    public static function values(?array $overrides = null): array
    {
        return array_merge(self::derived(), $overrides ?? self::overrides());
    }

    /** Variablen-Block für das Shadow DOM (hell, dunkel per theme="dark" bzw. theme="auto" + prefers-color-scheme) */
    public static function varsCss(?array $values = null): string
    {
        $v = $values ?? self::values();
        $num = fn($x) => rtrim(rtrim(number_format((float) $x, 3, '.', ''), '0'), '.');
        $light = $dark = [];
        foreach (self::COLORS as $k => $_) {
            $css = str_replace('_', '-', $k);
            $light[] = "--_{$css}:var(--ck-{$css}," . $v[$k] . ')';
            $dark[] = "--_{$css}:var(--ck-dark-{$css}," . $v[$k . '@dark'] . ')';
        }
        foreach (self::SIZES as $k => [, $unit]) {
            $css = str_replace('_', '-', $k);
            $light[] = "--_{$css}:var(--ck-{$css}," . $num($v[$k]) . $unit . ')';
        }
        $d = implode(';', $dark) . ';color-scheme:dark';
        return ':host{' . implode(';', $light) . ";color-scheme:light}\n:host([theme=dark]){" . $d . "}\n@media (prefers-color-scheme:dark){:host([theme=auto]){" . $d . "}}\n";
    }

    /** Vollständiges Stylesheet: Basis-CSS der Komponente (gebaut nach public/extensions/…) + Variablen */
    public static function css(?array $values = null): string
    {
        $base = '';
        foreach ([ROOT . '/public/extensions/consent_kit/css/consent.css', dirname(__DIR__) . '/assets/css/consent.css'] as $f) {
            if (is_file($f)) { $base = (string) file_get_contents($f); break; }
        }
        return "/* KLXM Studio Consent-Kit – Basis + Variablen dieser Website */\n" . self::varsCss($values) . $base;
    }

    public static function version(): string
    {
        $f = ROOT . '/public/extensions/consent_kit/css/consent.css';
        return substr(sha1(self::varsCss() . (is_file($f) ? (string) filemtime($f) : '0') . app()->theme->name), 0, 10);
    }

    /** @return list<array{label: string, mode: string, fg: string, bg: string, ratio: float, min: float, ok: bool}> */
    public static function checks(?array $values = null): array
    {
        $v = $values ?? self::values();
        $out = [];
        foreach (['light' => '', 'dark' => '@dark'] as $mode => $m) {
            foreach (self::CHECKS as [$fg, $bg, $min, $label]) {
                $r = SiteDesign::contrast($v[$fg . $m], $v[$bg . $m]);
                $out[] = ['label' => $label, 'mode' => $mode, 'fg' => $v[$fg . $m], 'bg' => $v[$bg . $m], 'ratio' => $r, 'min' => $min, 'ok' => $r >= $min];
            }
        }
        return $out;
    }
}
