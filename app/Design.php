<?php
declare(strict_types=1);

namespace Core;

/**
 * Design-Werte (Style-Editor): Themes erklären in theme.php → 'design', was sich einstellen lässt;
 * Admins ändern es unter Verwaltung → Design. Pro Website entsteht daraus eine CSS-Datei mit Variablen.
 *
 * theme.php:
 *   'design' => [
 *     'groups' => [
 *       ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
 *         ['name' => 'accent', 'label' => 'Akzent', 'type' => 'color', 'var' => '--b-a', 'default' => '#0F766E', 'dark' => '#2DD4BF',
 *          'contrast' => ['with' => '#FFFFFF', 'min' => 4.5]],          // Prüfung im Editor (with: Farbe oder anderer Token-Name)
 *         …]],
 *       …],
 *     'fonts'   => ['inter' => ['label' => 'Inter', 'stack' => 'Inter, system-ui, sans-serif', 'css' => 'css/font-inter.css'], …],
 *     'presets' => ['petrol' => ['label' => 'Petrol', 'values' => ['accent' => '#0F766E', …]], …],
 *     'dark'    => ['media' => '(prefers-color-scheme: dark)', 'scope' => 'html.has-dark'],   // wo dunkle Werte gelten
 *   ]
 *
 * Typen: color (#RRGGBB, optional eigener Dunkel-Wert), range (Zahl + unit, min/max/step), choice (options; Ausgabe über
 * 'values' => [option => CSS-Wert] als Variable und/oder 'class' => 'nav-{value}' als Klasse am <html>), bool ('class' bzw. var 1/0),
 * font (Schlüssel aus 'fonts'; Variable = Schrift-Stack, die Schriftdatei des Themes wird automatisch eingebunden).
 * Installierte Schriften (Grundeinstellungen → Schriften, Core\Fonts) kommen als „installed:{id}“ dazu; abschalten mit
 * 'design' => ['fonts_extra' => false].
 *
 * Im Theme: design('nav') liest einen Wert, design_classes() liefert Klassen für <html>, design_head() die <link>-Tags.
 */
final class Design
{
    private static ?array $values = null;
    private static ?array $override = null;

    public static function def(): array
    {
        return (array) (app()->theme->def['design'] ?? []);
    }

    /**
     * Schriften für Tokens vom Typ „font“: die des Themes ('fonts') + installierte Schriften (Core\Fonts, Schlüssel
     * „installed:{id}“), außer das Theme setzt 'design' => ['fonts_extra' => false] (oder 'fonts_extra' => false in theme.php).
     */
    public static function fonts(): array
    {
        $def = self::def();
        $def['fonts_extra'] ??= app()->theme->def['fonts_extra'] ?? true;
        return (array) ($def['fonts'] ?? []) + Fonts::designOptions($def);
    }

    public static function enabled(): bool
    {
        return (bool) self::tokens();
    }

    /** Alle Token-Definitionen [name => def] */
    public static function tokens(): array
    {
        $out = [];
        foreach ((array) (self::def()['groups'] ?? []) as $g) {
            foreach ((array) ($g['tokens'] ?? []) as $t) {
                if (!empty($t['name'])) $out[$t['name']] = $t + ['group' => $g['id'] ?? ''];
            }
        }
        return $out;
    }

    /** Gespeicherte Werte der Website (theme-bezogen), ergänzt um Standardwerte */
    public static function values(): array
    {
        if (self::$override !== null) return self::$override;
        if (self::$values !== null) return self::$values;
        $saved = (array) app()->settings->get('design.' . app()->theme->name, []);
        return self::$values = self::normalize($saved);
    }

    /** Werte nur für diese Anfrage ersetzen (Vorschau im Editor) */
    public static function override(?array $values): void
    {
        self::$override = $values === null ? null : self::normalize($values);
    }

    public static function get(string $name): mixed
    {
        return self::values()[$name] ?? (self::tokens()[$name]['default'] ?? null);
    }

    /** Eingaben prüfen und auf erlaubte Werte begrenzen; Unbekanntes fällt weg */
    public static function normalize(array $in): array
    {
        $out = [];
        $fonts = self::fonts();
        foreach (self::tokens() as $n => $t) {
            $v = $in[$n] ?? null;
            $out[$n] = match ($t['type'] ?? 'color') {
                'color' => self::color($v) ?? self::color($t['default'] ?? '') ?? '#000000',
                'range' => is_numeric($v) ? max((float) ($t['min'] ?? 0), min((float) ($t['max'] ?? 999), (float) $v)) : (float) ($t['default'] ?? 0),
                'choice' => array_key_exists((string) $v, (array) ($t['options'] ?? [])) ? (string) $v : (string) ($t['default'] ?? array_key_first((array) ($t['options'] ?? [])) ?? ''),
                'bool' => $v === null ? (bool) ($t['default'] ?? false) : in_array($v, [true, 1, '1', 'on', 'true'], true),
                'font' => isset($fonts[(string) $v]) ? (string) $v : (string) ($t['default'] ?? array_key_first($fonts) ?? ''),
                default => null,
            };
            if (($t['type'] ?? '') === 'color' && isset($t['dark'])) {
                $out[$n . '@dark'] = self::color($in[$n . '@dark'] ?? null) ?? self::color((string) $t['dark']) ?? $out[$n];
            }
        }
        return $out;
    }

    public static function color(mixed $v): ?string
    {
        $s = strtoupper(trim((string) $v));
        if (preg_match('~^#?([0-9A-F]{3})$~', $s, $m)) $s = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        if ($s !== '' && $s[0] !== '#') $s = '#' . $s;
        return preg_match('~^#[0-9A-F]{6}$~', $s) ? $s : null;
    }

    // ------------------------------------------------------------------ Ausgabe

    /** CSS mit allen Variablen (hell + dunkel) für die aktuellen Werte */
    public static function css(?array $values = null): string
    {
        $values ??= self::values();
        $fonts = self::fonts();
        $light = $dark = [];
        foreach (self::tokens() as $n => $t) {
            $v = $values[$n] ?? null;
            $var = (string) ($t['var'] ?? '');
            $css = match ($t['type'] ?? 'color') {
                'color' => $v,
                'range' => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') . (string) ($t['unit'] ?? ''),
                'choice' => isset($t['values']) ? (string) (($t['values'][$v] ?? '')) : null,
                'bool' => isset($t['var']) ? ($v ? '1' : '0') : null,
                'font' => (string) ($fonts[$v]['stack'] ?? ''),
                default => null,
            };
            if ($var !== '' && $css !== null && $css !== '') $light[] = $var . ':' . $css;
            if ($var !== '' && isset($values[$n . '@dark'])) $dark[] = $var . ':' . $values[$n . '@dark'];
        }
        $out = ':root{' . implode(';', $light) . '}';
        $d = (array) (self::def()['dark'] ?? []);
        if ($dark && !empty($d['scope'])) {
            $rule = $d['scope'] . '{' . implode(';', $dark) . '}';
            $out .= "\n" . (!empty($d['media']) ? '@media ' . $d['media'] . '{' . $rule . '}' : $rule);
        }
        return $out . "\n";
    }

    /** Klassen für <html> aus choice-/bool-Werten mit 'class' */
    public static function classes(): string
    {
        $out = [];
        foreach (self::tokens() as $n => $t) {
            if (empty($t['class'])) continue;
            $v = self::get($n);
            if (($t['type'] ?? '') === 'bool') { if ($v) $out[] = (string) $t['class']; continue; }
            $out[] = str_replace('{value}', preg_replace('~[^a-z0-9_-]~i', '', (string) $v), (string) $t['class']);
        }
        // Landing-Domain (Core\Landings): Klassen für Themes (is-landing, is-landing--reduced beim Landing-Layout)
        if ($l = Landings::current()) {
            $out[] = 'is-landing';
            if ($l->layout === 'landing') $out[] = 'is-landing--reduced';
        }
        return implode(' ', $out);
    }

    /** Öffentliche CSS-Datei der Website (bei Bedarf neu erzeugt) → URL */
    public static function fileUrl(): ?string
    {
        if (!self::enabled()) return null;
        $css = self::css();
        $name = 'design-' . app()->theme->name . '-' . substr(sha1($css), 0, 10) . '.css';
        $dir = site()->mediaDir('design');
        if (!is_file("$dir/$name")) {
            @mkdir($dir, 0775, true);
            foreach (glob("$dir/design-" . app()->theme->name . '-*.css') ?: [] as $old) @unlink($old);
            @file_put_contents("$dir/$name", "/* Design-Werte (Verwaltung → Design) – automatisch erzeugt */\n" . $css, LOCK_EX);
        }
        return site()->mediaUrl('design/' . $name);
    }

    /** <link>-Tags: gewählte Schriften des Themes + Design-Variablen (nach dem Theme-CSS einbinden) */
    public static function head(): string
    {
        if (!self::enabled()) return '';
        $fonts = self::fonts();
        $used = $pre = [];
        $lp = self::$override === null ? Landings::current() : null;
        foreach (self::tokens() as $n => $t) {
            // Schrift der Landingpage (Überschreibung) statt der der Website
            $fv = ($t['type'] ?? '') === 'font' ? ($lp?->fontFor($n) ?? self::get($n)) : null;
            if ($fv === null || !($f = $fonts[$fv] ?? null)) continue;
            // Installierte Schrift (Core\Fonts, public/fonts/…): eigene CSS-Datei, optional Vorladen des Hauptschnitts
            if (!empty($f['href'])) {
                $used[(string) $f['href']] = true;
                if (!empty($f['preload'])) $pre[(string) $f['preload']] = true;
            } elseif (!empty($f['css'])) {
                $used[app()->theme->asset((string) $f['css'])] = true;
            }
        }
        $h = '';
        foreach (array_keys($pre) as $file) {
            $h .= '<link rel="preload" href="' . e($file) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        }
        foreach (array_keys($used) as $href) {
            $h .= '<link rel="stylesheet" href="' . e($href) . '">' . "\n";
        }
        if (self::$override !== null) {
            // Vorschau im Editor: Werte direkt (die Admin-CSP erlaubt Inline-Styles nur dort)
            return $h . '<style data-design-preview>' . self::css() . '</style>';
        }
        // Landing-Domain: Überschreibungen als eigene kleine Datei nach den Design-Variablen der Website
        $landing = ($lu = $lp?->designUrl()) ? "\n" . '<link rel="stylesheet" href="' . e($lu) . '">' : '';
        // Unverändert gegenüber dem Theme: keine zusätzliche Datei (spart eine Anfrage)
        if (self::values() == self::defaults()) return $h . ltrim($landing);
        $url = self::fileUrl();
        return $h . ($url ? '<link rel="stylesheet" href="' . e($url) . '">' : '') . $landing;
    }

    // ------------------------------------------------------------------ Speichern

    /** Werte speichern (mit Verlauf der letzten 10 Stände) */
    public static function save(array $values, string $note = '', ?string $by = null): void
    {
        $key = 'design.' . app()->theme->name;
        $clean = self::normalize($values);
        $hist = (array) app()->settings->get($key . '.history', []);
        $prev = app()->settings->get($key);
        if ($prev !== null) {
            array_unshift($hist, ['at' => now(), 'by' => $by ?? (string) (app()->auth->user()['email'] ?? ''), 'note' => $note, 'values' => $prev]);
        }
        app()->settings->set($key . '.history', array_slice($hist, 0, 10));
        app()->settings->set($key, $clean);
        Fonts::recordUsage(site()->key, app()->theme->name, $clean, self::tokens());   // Verwendung installierter Schriften (Core\Fonts)
        self::$values = null;
        PageCache::clear();
    }

    /** Standardwerte des Themes (normalisiert) */
    public static function defaults(): array
    {
        $saved = self::$override;
        self::$override = null;
        $d = self::normalize([]);
        self::$override = $saved;
        return $d;
    }

    /** Definition für Editor und API (Gruppen, Schriften, Vorlagen, Dunkel-Modus) – JSON-tauglich */
    public static function schema(): array
    {
        $def = self::def();
        return [
            'groups' => array_values(array_map(fn($g) => ['id' => (string) ($g['id'] ?? ''), 'label' => (string) ($g['label'] ?? ''),
                'tokens' => array_values(array_filter((array) ($g['tokens'] ?? []), fn($t) => !empty($t['name'])))], (array) ($def['groups'] ?? []))),
            'fonts' => array_map(fn($f) => ['label' => (string) ($f['label'] ?? ''), 'stack' => (string) ($f['stack'] ?? '')]
                + (!empty($f['installed']) ? ['installed' => true, 'kb' => (int) ($f['kb'] ?? 0), 'href' => (string) $f['href']] : []), self::fonts()),
            'presets' => array_map(fn($p) => ['label' => (string) ($p['label'] ?? ''), 'values' => (array) ($p['values'] ?? [])], (array) ($def['presets'] ?? [])),
            'dark' => (array) ($def['dark'] ?? []) ?: null,
        ];
    }

    /**
     * Kontrastprüfungen für Tokens mit 'contrast' => ['with' => Farbe|Token, 'min' => 4.5, 'dark_with' => Farbe|Token (optional)].
     * Dunkle Werte werden gegen den Dunkel-Wert des Partners (bzw. dark_with) geprüft.
     * @return list<array{token: string, mode: string, fg: string, bg: string, ratio: float, min: float, ok: bool, level: string}>
     */
    public static function checks(?array $values = null): array
    {
        $values ??= self::values();
        $tokens = self::tokens();
        $resolve = function (string $ref, bool $dark) use ($values, $tokens): ?string {
            if (isset($tokens[$ref])) return $dark ? ($values[$ref . '@dark'] ?? $values[$ref] ?? null) : ($values[$ref] ?? null);
            return self::color($ref);
        };
        $out = [];
        foreach ($tokens as $n => $t) {
            if (($t['type'] ?? 'color') !== 'color' || empty($t['contrast']['with'])) continue;
            $min = (float) ($t['contrast']['min'] ?? 4.5);
            foreach (['light', 'dark'] as $mode) {
                $fg = $mode === 'light' ? ($values[$n] ?? null) : ($values[$n . '@dark'] ?? null);
                $with = $mode === 'dark' ? (string) ($t['contrast']['dark_with'] ?? (isset($tokens[$t['contrast']['with']]) ? $t['contrast']['with'] : '')) : (string) $t['contrast']['with'];
                $bg = $with !== '' ? $resolve($with, $mode === 'dark' && isset($tokens[$with])) : null;
                if (!$fg || !$bg) continue;
                $r = self::contrast($fg, $bg);
                $aaa = $min >= 4.5 ? 7.0 : 4.5;
                $out[] = ['token' => $n, 'mode' => $mode, 'fg' => $fg, 'bg' => $bg, 'ratio' => $r, 'min' => $min,
                    'ok' => $r >= $min, 'level' => $r >= $aaa ? 'AAA' : ($r >= $min ? 'AA' : 'fail')];
            }
        }
        return $out;
    }

    public static function history(): array
    {
        return (array) app()->settings->get('design.' . app()->theme->name . '.history', []);
    }

    /** Kontrast nach WCAG 2.x zwischen zwei Farben (1–21) */
    public static function contrast(string $a, string $b): float
    {
        $l = function (string $hex): float {
            $hex = ltrim($hex, '#');
            $c = array_map(fn($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
            $c = array_map(fn($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);
            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$x, $y] = [$l($a), $l($b)];
        return round((max($x, $y) + 0.05) / (min($x, $y) + 0.05), 2);
    }
}
