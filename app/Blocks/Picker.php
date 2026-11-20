<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Blocks;

use Core\Kit;
use Core\PublicPaths;
use Core\Theme;
use Core\UiPrefs;

/**
 * Blockauswahl im Editor („+ Block einfügen“, resources/js/editor.js BlockPicker): Kurzbeschreibung, Vorschaugrafik und
 * persönliche Einstellungen (Ansicht, Favoriten, zuletzt verwendet). Alles optional – Kits ohne Beschreibung und ohne
 * Vorschauen funktionieren unverändert (Symbol + Name).
 *
 *   theme.php → blocks → {typ} → 'description' => 'Ein Satz, was der Block zeigt.'   (übersetzbar wie label, __())
 *   theme.php → blocks → {typ} → 'preview' => 'faq' | false                         (Vorschau eines anderen Namens / keine)
 *
 * Vorschauen sind SVG-Dateien im öffentlichen Asset-Ordner, gesucht in dieser Reihenfolge (erster Treffer gilt):
 *   1. Kit und Eltern-Kits (Core\Kit::chain):  kits/{kit}/assets/img/previews/{name}.svg → public/assets/kits/{kit}/img/previews/
 *   2. Erweiterung des Blocks:                  extensions/{ext}/assets/img/previews/{name}.svg → public/assets/ext/{ext}/img/previews/
 *   3. Kern:                                    resources/img/previews/{name}.svg → public/assets/img/previews/
 * {name} ist der Blocktyp (bzw. 'preview'); gleichbedeutende Namen teilen sich die Kern-Vorschau (ALIASES).
 * Optional je Variante: {name}--{variante}.svg (z. B. hero--compact.svg) – fehlt sie, gilt die des Blocks.
 * Je Website übersteuerbar (Einstellung 'block_previews', Filter 'blocks.previews') – siehe overrides().
 */
final class Picker
{
    /** Gleiches Aussehen, anderer Name → Kern-Vorschau mitbenutzen */
    public const ALIASES = ['text' => 'richtext', 'accordion' => 'faq', 'media_text' => 'text_image', 'photo_text' => 'text_image'];

    /** Ansichten der Blockauswahl */
    public const VIEWS = ['list', 'tiles', 'split'];

    /** UiPrefs-Schlüssel und Grenzen der persönlichen Listen */
    public const PREF = 'blockpicker';
    public const MAX_FAV = 24;
    public const MAX_RECENT = 5;

    /**
     * Vorschau-Adressen je Blocktyp: [typ => ['url' => …, 'variants' => [variante => url]]] – nur Typen mit Vorschau.
     * Je Ordner ein scandir() (kein Zugriff je Block).
     * @param array<string, array<string, mixed>> $blocks
     * @return array<string, array{url: string, variants: array<string, string>}>
     */
    public static function previews(Theme $theme, array $blocks): array
    {
        // [ordner, adresse ohne Dateiname, Erweiterung|null]
        $dirs = [];
        foreach (array_keys($theme->chain) as $kit) $dirs[] = [Kit::publicDir((string) $kit) . '/img/previews', Kit::url((string) $kit, 'img/previews/'), null];
        $core = [ROOT . '/public/assets/img/previews', base_path() . '/assets/img/previews/', null];
        $index = [];
        $scan = function (string $dir) use (&$index): array {
            if (!isset($index[$dir])) {
                $index[$dir] = [];
                foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $f) if (str_ends_with($f, '.svg')) $index[$dir][substr($f, 0, -4)] = true;
            }
            return $index[$dir];
        };
        $out = [];
        foreach ($blocks as $type => $b) {
            $want = $b['preview'] ?? null;
            if ($want === false) continue;
            $name = is_string($want) && preg_match('~^[a-z0-9_-]{1,60}$~', $want) ? $want : (string) $type;
            $list = $dirs;
            if (!empty($b['extension']) && is_string($b['extension'])) {
                $ext = preg_replace('~[^a-z0-9_-]~', '', $b['extension']);
                $list[] = [PublicPaths::dir(PublicPaths::EXT, $ext) . '/img/previews', PublicPaths::url(PublicPaths::EXT, $ext, 'img/previews/'), $ext];
            }
            $list[] = $core;
            foreach (array_unique(array_filter([$name, self::ALIASES[$name] ?? null])) as $n) {
                foreach ($list as [$dir, $url]) {
                    $files = $scan($dir);
                    if (!isset($files[$n])) continue;
                    $variants = [];
                    foreach (array_keys((array) ($b['variants'] ?? [])) as $v) {
                        if (isset($files[$n . '--' . $v])) $variants[(string) $v] = self::url($dir, $url, $n . '--' . $v);
                    }
                    $out[(string) $type] = ['url' => self::url($dir, $url, $n), 'variants' => $variants];
                    continue 3;
                }
            }
        }
        return self::overrides($out, $theme);
    }

    /**
     * Andockstelle: Vorschauen je Website übersteuern die des Kits bzw. Kerns. Heute zwei Quellen, beide optional:
     *   - Website-Einstellung 'block_previews' => [typ => adresse] (z. B. später ein Mediathek-Bild oder eine Grafik aus einem
     *     SVG-Editor; eine Oberfläche dafür gibt es noch nicht),
     *   - Filter 'blocks.previews' für Erweiterungen (Extension::filter): bekommt und liefert die ganze Liste.
     * Adressen müssen relativ zur Installation sein („/…“, kein „//“), damit keine fremden Quellen nachgeladen werden.
     * @param array<string, array{url: string, variants: array<string, string>}> $out
     * @return array<string, array{url: string, variants: array<string, string>}>
     */
    private static function overrides(array $out, Theme $theme): array
    {
        $site = app()->settings->get('block_previews', []);
        foreach (is_array($site) ? $site : [] as $type => $url) {
            if (is_string($url) && str_starts_with($url, '/') && !str_starts_with($url, '//')) $out[(string) $type] = ['url' => $url, 'variants' => []];
        }
        $f = \Core\Extensions::filter('blocks.previews', $out, ['kit' => $theme->name]);
        if (!is_array($f)) return $out;
        return array_filter($f, fn($p) => is_array($p) && is_string($p['url'] ?? null) && str_starts_with($p['url'], '/') && !str_starts_with($p['url'], '//'));
    }

    private static function url(string $dir, string $base, string $name): string
    {
        $m = @filemtime("$dir/$name.svg");
        return $base . rawurlencode($name) . '.svg?v=' . ($m ? substr(md5((string) $m), 0, 8) : CMS_VERSION);
    }

    /**
     * Kurzbeschreibung eines Blocks (übersetzt): 'description', sonst der erste Satz von 'help' (bis 160 Zeichen) – so haben
     * auch Kits ohne Beschreibungen einen Text für Kacheln und Tooltip. null, wenn beides fehlt.
     */
    public static function description(array $b): ?string
    {
        $d = trim((string) ($b['description'] ?? ''));
        if ($d !== '') return __($d);
        $h = trim(__((string) ($b['help'] ?? '')));
        if ($h === '') return null;
        if (preg_match('~^(.{20,160}?[.!?])(?:\s|$)~u', $h, $m)) return $m[1];
        return mb_strlen($h) > 160 ? rtrim(mb_substr($h, 0, 157)) . ' …' : $h;
    }

    /** Persönliche Einstellungen der Blockauswahl: ['view' => list|tiles|split, 'fav' => [typ …], 'recent' => [typ …]] */
    public static function prefs(?array $user): array
    {
        $p = (array) UiPrefs::get($user, self::PREF, []);
        return [
            'view' => in_array($p['view'] ?? null, self::VIEWS, true) ? $p['view'] : 'list',
            'fav' => self::types($p['fav'] ?? [], self::MAX_FAV),
            'recent' => self::types($p['recent'] ?? [], self::MAX_RECENT),
        ];
    }

    /** Teil-Änderung übernehmen (nur bekannte Schlüssel, geprüfte Werte) und speichern; gibt den neuen Stand zurück */
    public static function savePrefs(array $user, array $in): array
    {
        $p = self::prefs($user);
        if (isset($in['view']) && in_array($in['view'], self::VIEWS, true)) $p['view'] = $in['view'];
        if (array_key_exists('fav', $in)) $p['fav'] = self::types($in['fav'], self::MAX_FAV);
        if (array_key_exists('recent', $in)) $p['recent'] = self::types($in['recent'], self::MAX_RECENT);
        UiPrefs::set((int) $user['id'], self::PREF, $p);
        return $p;
    }

    /** @return list<string> Blocktypen (Kleinbuchstaben, Ziffern, _ und -), ohne Doppelte, höchstens $max */
    private static function types(mixed $v, int $max): array
    {
        $out = [];
        foreach (is_array($v) ? $v : [] as $t) {
            if (is_string($t) && preg_match('~^[a-z0-9_-]{1,60}$~', $t) && !in_array($t, $out, true)) $out[] = $t;
            if (count($out) >= $max) break;
        }
        return $out;
    }
}
