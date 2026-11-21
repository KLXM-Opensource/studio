<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Graphics;

use Core\Design;
use Core\Media;
use Core\Svg;

/**
 * Grafiken mit Design-Farben (Ausbaustufe 3b): eine Infografik, deren Flächen „Akzent“, „Überschriften“ … tragen, folgt dem
 * Design der Website – auch dessen Dunkel-Werten.
 *
 * Warum Dateien je Website und Farbschema statt CSS-Variablen: Die Website bindet Medien als <img> ein. Ein SVG im <img> sieht
 * die Variablen der Seite nicht; ein eingebettetes SVG (inline) bräuchte ein <style> in der Seite (CSP style-src 'self'
 * verbietet es), var() in Darstellungsattributen ist nicht in allen Browsern verlässlich, ids kollidieren, der Browser-Cache
 * fällt weg. Deshalb:
 *  - Die Web-Fassung (Editor → export.js) trägt an Elementen mit Design-Farbe Klassen: ge-tf-{token} (Füllung), ge-ts-{token}
 *    (Kontur), ge-tc-{token} (color für currentColor); eigene Dunkel-Farben ge-df-{rrggbb} bzw. ge-df-t-{token} (und -ds-, -dc-).
 *    Klassen überstehen Core\Svg::clean, die Hex-Farben bleiben als Rückfall (Feeds, Herunterladen, andere Websites).
 *  - variants() erzeugt daraus je Website zwei Dateien – hell und dunkel – mit den Werten aus Core\Design (Name enthält einen
 *    Hash über Datei und verwendete Werte: ändert sich das Design, entstehen neue Adressen; der Seiten-Cache wird beim Speichern
 *    des Designs ohnehin geleert). Ablage {Medienordner}/grafik/t/ (Schutz durch Svg::guard des Ordners grafik).
 *  - Media::pictureOf() gibt <picture class="cms-scheme-img"> mit <source media="(prefers-color-scheme: dark)"> aus – nur, wenn
 *    das Design der Website ein dunkles Farbschema hat und es eingeschaltet ist. Ohne Skript gilt damit die Einstellung des Geräts;
 *    js/graphic-scheme.js (nur auf Seiten mit solchen Bildern) folgt außerdem dem Hell/Dunkel-Schalter der Website und dunklen
 *    Abschnitten (wie Karten und Diagramme: helle Schrift = dunkler Grund).
 * MIT
 */
final class Themed
{
    /** Kurzname der Eigenschaft → Attribut */
    private const PROPS = ['f' => 'fill', 's' => 'stroke', 'c' => 'color'];
    private const RE = '~\bge-([td])([fsc])-(t-)?([a-z0-9_]{1,40})\b~';

    /** @var array<string, ?array{light: string, dark: ?string}> */
    private static array $memo = [];

    /** Trägt die Web-Fassung Design-Farben oder eigene Dunkel-Farben? */
    public static function isThemed(string $svg): bool
    {
        return (bool) preg_match('~\bclass="[^"]*\bge-[td][fsc]-~', $svg);
    }

    /**
     * Farben des Designs: [token => ['light' => #hex, 'dark' => #hex]] (dark = light, wenn das Kit keinen Dunkel-Wert kennt)
     * @return array<string, array{light: string, dark: string}>
     */
    public static function palette(?array $values = null): array
    {
        $out = [];
        try {
            if (!Design::enabled()) return [];
            $values ??= Design::values();
            foreach (Design::tokens() as $name => $t) {
                if (($t['type'] ?? '') !== 'color') continue;
                $l = Design::color($values[$name] ?? ($t['default'] ?? null));
                if ($l === null) continue;
                $out[(string) $name] = ['light' => $l, 'dark' => Design::color($values[$name . '@dark'] ?? null) ?? $l];
            }
        } catch (\Throwable) {
            return [];
        }
        return $out;
    }

    /**
     * Hat die Website ein dunkles Farbschema, das gerade gilt? (Design 'dark' => ['scope' => 'html.has-dark', …]: alle Klassen
     * des Bereichs müssen am <html> stehen – z. B. Schalter „Dunkles Farbschema“ an)
     */
    public static function darkActive(): bool
    {
        try {
            if (!Design::enabled()) return false;
            $d = (array) (Design::def()['dark'] ?? []);
            if (empty($d['scope'])) return false;
            $hasDark = false;
            foreach (Design::tokens() as $t) if (($t['type'] ?? '') === 'color' && isset($t['dark'])) { $hasDark = true; break; }
            if (!$hasDark) return false;
            preg_match_all('~\.([a-z0-9_-]+)~i', (string) $d['scope'], $m);
            if (!$m[1]) return true;
            $have = preg_split('~\s+~', Design::htmlClasses(Design::values())) ?: [];
            foreach ($m[1] as $c) if (!in_array($c, $have, true)) return false;
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * SVG mit den Farben eines Schemas: $mode 'light' | 'dark'. Nur Farben werden gesetzt (Attribut bzw. Eigenschaft im style),
     * sonst bleibt alles Zeichen für Zeichen. Unbekannte Tokens (anderes Kit) behalten die gespeicherte Farbe.
     * @param array<string, array{light: string, dark: string}> $pal
     */
    public static function render(string $svg, string $mode, array $pal): string
    {
        if (!self::isThemed($svg)) return $svg;
        $dark = $mode === 'dark';
        // Nur öffnende Tags mit class="…ge-…" anfassen (kein DOM-Durchlauf: Ausgabe bleibt bytegleich außerhalb der Farben)
        return (string) preg_replace_callback('~<([a-zA-Z][\w:-]*)(\s[^<>]*?\bclass="([^"]*\bge-[td][fsc]-[^"]*)"[^<>]*?)(/?)>~', function (array $x) use ($dark, $pal): string {
            [$all, $tag, $attrs, $cls, $close] = $x;
            preg_match_all(self::RE, $cls, $mm, PREG_SET_ORDER);
            // je Eigenschaft: Token (hell/dunkel) und eigene Dunkel-Angabe (#hex, Token nur dunkel oder „same“ = wie hell)
            $tok = $own = [];
            foreach ($mm as $c) {
                [, $kind, $p, $isTok, $val] = $c;
                $attr = $p === 'f' && $tag === 'stop' ? 'stop-color' : self::PROPS[$p];
                if ($kind === 't') $tok[$attr] = $val;
                else $own[$attr] = $isTok !== '' ? ['t', $val] : ($val === 'same' ? ['same', ''] : ['hex', $val]);
            }
            $set = [];
            foreach (array_unique([...array_keys($tok), ...array_keys($own)]) as $attr) {
                $t = isset($tok[$attr]) ? ($pal[$tok[$attr]] ?? null) : null;
                $v = null;
                if (!$dark || ($own[$attr][0] ?? '') === 'same') {
                    $v = $t['light'] ?? null;
                } elseif (isset($own[$attr])) {
                    [$k, $x] = $own[$attr];
                    $v = $k === 't' ? ($pal[$x]['dark'] ?? null) : (preg_match('~^[0-9a-f]{6}$~', $x) ? '#' . strtoupper($x) : null);
                } else {
                    $v = $t['dark'] ?? null;
                }
                if ($v !== null) $set[$attr] = $v;
            }
            foreach ($set as $a => $v) {
                $q = preg_quote($a, '~');
                if (preg_match('~\sstyle="[^"]*(?<![\w-])' . $q . '\s*:~', $attrs)) {
                    $attrs = (string) preg_replace_callback('~(\sstyle=")([^"]*)"~', fn($s) => $s[1] . preg_replace('~((?<![\w-])' . $q . '\s*:\s*)[^;"]+~', '${1}' . $v, $s[2]) . '"', $attrs, 1);
                }
                if (preg_match('~\s' . $q . '="[^"]*"~', $attrs)) $attrs = (string) preg_replace('~(\s' . $q . '=")[^"]*"~', '${1}' . $v . '"', $attrs, 1);
                elseif (!preg_match('~\sstyle="[^"]*(?<![\w-])' . $q . '\s*:~', $attrs)) $attrs .= ' ' . $a . '="' . $v . '"';
            }
            return '<' . $tag . $attrs . $close . '>';
        }, $svg);
    }

    /**
     * Adressen der hellen und dunklen Fassung einer Infografik der Mediathek mit Design-Farben (sonst null).
     * dark = null, wenn die Website kein dunkles Farbschema nutzt.
     * @return ?array{light: string, dark: ?string}
     */
    public static function variants(array $m): ?array
    {
        if (($m['mime'] ?? '') !== Svg::MIME || empty($m['id'])) return null;
        $key = site()->key . ':' . ($m['_pool'] ?? '') . ':' . $m['id'] . ':' . ($m['file'] ?? '');
        if (array_key_exists($key, self::$memo)) return self::$memo[$key];
        $path = Media::path($m);
        $svg = is_file($path) ? (string) @file_get_contents($path, false, null, 0, Svg::MAX_BYTES + 1) : '';
        if ($svg === '' || !self::isThemed($svg) || !($pal = self::palette())) return self::$memo[$key] = null;
        $dark = self::darkActive();
        // nur die verwendeten Tokens zählen für den Namen (anderes Design-Detail ändert die Adresse nicht)
        preg_match_all(self::RE, $svg, $mm, PREG_SET_ORDER);
        $used = [];
        foreach ($mm as $c) if ($c[1] === 't' || $c[3] !== '') $used[$c[4]] = $pal[$c[4]] ?? null;
        ksort($used);
        $hash = substr(sha1($svg . "\n" . json_encode($used) . ($dark ? 'd' : '')), 0, 10);
        $dir = Graphics::dir() . '/t';
        $id = ($m['_pool'] ?? '') !== '' ? preg_replace('~[^a-z0-9]~', '', (string) $m['_pool']) . '-' . (int) $m['id'] : (string) (int) $m['id'];
        $base = 'gt-' . $id . '-' . $hash;
        $out = ['light' => '', 'dark' => null];
        foreach ($dark ? ['light', 'dark'] : ['light'] as $mode) {
            $file = $base . '-' . $mode[0] . '.svg';
            if (!is_file($dir . '/' . $file)) {
                if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return self::$memo[$key] = null;
                Svg::guard(Graphics::dir());
                // ältere Fassungen dieser Grafik entfernen (anderes Design oder neue Datei) – erst nach einem Tag, weil
                // zwischengespeicherte Seiten noch auf sie verweisen können (wie die Design-Datei, Core\Design::fileUrl)
                foreach (glob($dir . '/gt-' . $id . '-*-' . $mode[0] . '.svg') ?: [] as $old) {
                    if (filemtime($old) < time() - 86400) @unlink($old);
                }
                if (@file_put_contents($dir . '/' . $file, self::render($svg, $mode, $pal), LOCK_EX) === false) return self::$memo[$key] = null;
            }
            $out[$mode] = Graphics::url('t/' . $file);
        }
        return self::$memo[$key] = $out;
    }

    /** Für Tests: Zwischenspeicher der Anfrage leeren */
    public static function forget(): void
    {
        self::$memo = [];
    }

    /** Skript für den Hell/Dunkel-Schalter der Website – nur auf Seiten mit Grafiken in zwei Fassungen (vor dem Seiten-Cache) */
    public static function inject(string $html): string
    {
        if (!str_contains($html, 'cms-scheme-img') || str_contains($html, 'data-cms-graphic-scheme')) return $html;
        $tag = '<script type="module" src="' . e(asset('js/graphic-scheme.js')) . '" data-cms-graphic-scheme></script>';
        $pos = stripos($html, '</body>');
        return $pos === false ? $html . $tag : substr($html, 0, $pos) . $tag . substr($html, $pos);
    }
}
