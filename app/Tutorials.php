<?php
declare(strict_types=1);

namespace Core;

/**
 * Tutorials (Handbuch & Hilfe › Tutorials, Übersicht „Hilfe & Einstieg“, php bin/console tutorials:export).
 *
 * Inhalte: app/Admin/tutorials.php (Deutsch) + app/Admin/tutorials.en.php (Englisch, gleiche Kurznamen) + optional
 * kits/{name}/docs/tutorials.php (gleicher Kurzname ersetzt, false entfernt).
 * Die Videos des Kerns werden NICHT mit dem CMS ausgeliefert: Sie liegen auf der Produkt-Website (config 'docs_url',
 * Standard https://studio.klxm.de → /tutorials/{kurzname}, Englisch /en/tutorials/{kurzname}). Die Verwaltung setzt dorthin
 * nur Links (neuer Tab) – keine Einbettung, kein Vorladen, keine Anfrage nach außen. Die Schritte bleiben als Text offline verfügbar.
 * Kits können eigene Videos lokal mitbringen ('video' => '/kits/{name}/tutorials/datei', ohne Endung, unter public/).
 */
final class Tutorials
{
    /** Stufen (Kurzname im Katalog = deutsche Bezeichnung) */
    public const LEVELS = ['Einstieg', 'Grundlagen', 'Fortgeschritten'];

    /** Adresse der Produkt-Website ohne abschließenden Schrägstrich; '' = keine Links nach außen (White-Label oder ausgeschaltet) */
    public static function docsUrl(): string
    {
        $url = rtrim(trim((string) app()->config->get('docs_url', 'https://studio.klxm.de')), '/');
        return preg_match('~^https?://[^\s/?#]+(?:/[^\s?#]*)?$~i', $url) ? $url : '';
    }

    /** Hostname der Produkt-Website für Beschriftungen („studio.klxm.de“) */
    public static function docsHost(): string
    {
        $u = parse_url(self::docsUrl());
        return (string) ($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : '');
    }

    /** Englische Verwaltung? (Links und Texte des Katalogs) */
    public static function en(?string $locale = null): bool
    {
        return str_starts_with($locale ?? I18n::locale(), 'en');
    }

    /** Video eines Kern-Tutorials auf der Produkt-Website (null ohne docs_url) */
    public static function videoUrl(string $slug, ?bool $en = null): ?string
    {
        $base = self::docsUrl();
        if ($base === '') return null;
        return $base . (($en ?? self::en()) ? '/en' : '') . '/tutorials/' . rawurlencode($slug);
    }

    /** Übersicht aller Tutorials auf der Produkt-Website */
    public static function overviewUrl(?bool $en = null): ?string
    {
        $base = self::docsUrl();
        return $base === '' ? null : $base . (($en ?? self::en()) ? '/en' : '') . '/tutorials';
    }

    /** Trailer „KLXM Studio im Überblick“ auf der Startseite der Produkt-Website */
    public static function trailerUrl(?bool $en = null): ?string
    {
        $base = self::docsUrl();
        return $base === '' ? null : $base . (($en ?? self::en()) ? '/en/' : '/') . '#trailer';
    }

    /**
     * Katalog in der Sprache der Verwaltung: ['tracks' => …, 'tutorials' => [kurzname => [… 'web' => Link zum Video auf der
     * Produkt-Website oder null, 'media' => lokales Kit-Video oder null, 'mobileMedia', 'off']]].
     */
    public static function catalogue(?bool $en = null): array
    {
        $en ??= self::en();
        $core = require ROOT . '/app/Admin/tutorials.php';
        $theme = [];
        if (isset(app()->theme)) {
            $themeFile = app()->theme->path . '/docs/tutorials.php';
            $theme = is_file($themeFile) ? (array) require $themeFile : [];
        }
        $tracks = $core['tracks'];
        $tutorials = $core['tutorials'];
        if ($en) {
            [$tracks, $tutorials] = self::english($tracks, $tutorials);
        }
        $tracks = array_merge($tracks, (array) ($theme['tracks'] ?? []));
        $themeTuts = (array) ($theme['tutorials'] ?? []);
        $tutorials = array_filter(array_merge($tutorials, $themeTuts), fn($t) => is_array($t) && isset($tracks[$t['track'] ?? '']));
        foreach ($tutorials as $slug => &$t) {
            $fromTheme = array_key_exists($slug, $themeTuts);
            $video = $t['video'] ?? ($fromTheme ? false : $slug);
            // Kern-Tutorial → Video auf der Produkt-Website; Kit-Tutorial mit eigenem Video (Pfad unter public/) → lokal eingebettet
            $t['web'] = !$fromTheme && $video !== false ? self::videoUrl((string) $slug, $en) : null;
            $t['media'] = is_string($video) && str_starts_with($video, '/') ? self::localMedia($video) : null;
            $t['mobileMedia'] = !empty($t['mobile']) && str_starts_with((string) $t['mobile'], '/') ? self::localMedia((string) $t['mobile']) : null;
            $t['off'] = !empty($t['feature']) && !Features::on((string) $t['feature']);
            $t['levelLabel'] = $en ? self::levelEn((string) ($t['level'] ?? 'Grundlagen')) : (string) ($t['level'] ?? 'Grundlagen');
        }
        unset($t);
        return ['tracks' => $tracks, 'tutorials' => $tutorials];
    }

    /** Empfohlene Zielgruppe nach Rolle/Rechten (alle bleiben sichtbar) */
    public static function recommended(array $tracks): string
    {
        $r = Features::integrator() ? 'agentur'
            : (can('users.manage') || can('system.manage') || can('design.edit') || can('data.schema') ? 'admin' : 'redaktion');
        return isset($tracks[$r]) ? $r : (string) array_key_first($tracks);
    }

    public static function levelEn(string $level): string
    {
        $map = (array) ((require ROOT . '/app/Admin/tutorials.en.php')['levels'] ?? []);
        return (string) ($map[$level] ?? $level);
    }

    /** Englische Texte (app/Admin/tutorials.en.php) über den deutschen Katalog legen; fehlende Felder bleiben deutsch */
    private static function english(array $tracks, array $tutorials): array
    {
        $file = ROOT . '/app/Admin/tutorials.en.php';
        $en = is_file($file) ? (array) require $file : [];
        foreach ((array) ($en['tracks'] ?? []) as $k => $tr) if (isset($tracks[$k])) $tracks[$k] = array_merge($tracks[$k], (array) $tr);
        foreach ((array) ($en['tutorials'] ?? []) as $slug => $x) {
            if (!isset($tutorials[$slug])) continue;
            $t = &$tutorials[$slug];
            foreach (['title', 'summary', 'goal', 'prerequisites', 'tips', 'pitfalls', 'mobileText'] as $k) if (isset($x[$k])) $t[$k] = $x[$k];
            // Schritte: Klartext (Untertitel) → für die HTML-Ausgabe maskieren
            if (!empty($x['steps'])) $t['steps'] = array_map(fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5), (array) $x['steps']);
            foreach ((array) ($x['manual'] ?? []) as $i => $label) if (isset($t['manual'][$i])) $t['manual'][$i][0] = $label;
            unset($t);
        }
        return [$tracks, $tutorials];
    }

    /** Lokales Kit-Video: Pfad unter public/ ohne Endung → {mp4, webm, poster, de, en} (nur vorhandene Dateien) */
    private static function localMedia(string $base): ?array
    {
        $base = rtrim($base, '/');
        $url = fn(string $ext) => is_file($p = ROOT . '/public' . $base . $ext) ? base_path() . $base . $ext . '?v=' . substr(md5((string) filemtime($p)), 0, 8) : null;
        if (!$url('.mp4') && !$url('.webm')) return null;
        $meta = [];
        if (is_file($j = ROOT . '/public' . dirname($base) . '/videos.json')) {
            $all = (array) json_decode((string) file_get_contents($j), true);
            $meta = (array) ($all[basename($base)] ?? []);
        }
        return ['mp4' => $url('.mp4'), 'webm' => $url('.webm'), 'poster' => $url('.jpg'), 'de' => $url('.de.vtt'), 'en' => $url('.en.vtt'),
            'duration' => (int) ($meta['duration'] ?? 0), 'width' => (int) ($meta['width'] ?? 1280), 'height' => (int) ($meta['height'] ?? 800)];
    }

    // ------------------------------------------------------------------ Export für die Produkt-Website

    /**
     * Katalog als JSON-Struktur für die Produkt-Website (php bin/console tutorials:export): Kern-Tutorials DE/EN mit Schritten
     * (= Transkript), Tipps, Stolperfallen, Voraussetzungen, Handbuch-Verweisen, Dauer und Dateinamen. $videos: Ordner der
     * Aufnahmen (videos.json, {kurzname}.mp4 …) – Standard TUT_OUT bzw. der Ordner site-tools/tutorials der Website.
     */
    public static function export(string $videos): array
    {
        $core = require ROOT . '/app/Admin/tutorials.php';
        $enFile = ROOT . '/app/Admin/tutorials.en.php';
        $en = is_file($enFile) ? (array) require $enFile : [];
        $manifest = is_file($videos . '/videos.json') ? (array) json_decode((string) file_get_contents($videos . '/videos.json'), true) : [];
        $text = fn(string $html) => trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
        $pair = fn($de, $e) => ['de' => $de, 'en' => $e ?? $de];
        $files = function (string $name) use ($videos, $manifest): ?array {
            $f = [];
            foreach (['mp4' => '.mp4', 'webm' => '.webm', 'poster' => '.jpg', 'vtt_de' => '.de.vtt', 'vtt_en' => '.en.vtt'] as $k => $ext) {
                if (is_file($videos . '/' . $name . $ext)) $f[$k] = $name . $ext;
            }
            if (!$f) return null;
            $m = (array) ($manifest[$name] ?? []);
            return $f + ['duration' => (int) ($m['duration'] ?? 0), 'width' => (int) ($m['width'] ?? 0), 'height' => (int) ($m['height'] ?? 0),
                'bytes' => (int) ($m['mp4'] ?? 0), 'recorded' => (string) ($m['recorded'] ?? '')];
        };
        $tracks = [];
        foreach ($core['tracks'] as $k => $tr) {
            $e = (array) ($en['tracks'][$k] ?? []);
            $tracks[$k] = ['title' => $pair($tr['title'], $e['title'] ?? null), 'lead' => $pair($tr['lead'] ?? '', $e['lead'] ?? null),
                'for' => $pair($tr['for'] ?? '', $e['for'] ?? null), 'icon' => (string) ($tr['icon'] ?? '')];
        }
        $out = [];
        foreach ($core['tutorials'] as $slug => $t) {
            if (($t['video'] ?? $slug) === false) continue;
            $e = (array) ($en['tutorials'][$slug] ?? []);
            $list = fn(string $k) => ['de' => array_values((array) ($t[$k] ?? [])), 'en' => array_values((array) ($e[$k] ?? $t[$k] ?? []))];
            $stepsEn = array_map(fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5), (array) ($e['steps'] ?? []));
            $manual = [];
            foreach ((array) ($t['manual'] ?? []) as $i => [$label, $path]) {
                $manual[] = ['label' => $pair($label, $e['manual'][$i] ?? null), 'path' => $path];
            }
            $level = (string) ($t['level'] ?? 'Grundlagen');
            $out[$slug] = [
                'slug' => $slug, 'track' => $t['track'], 'icon' => (string) ($t['icon'] ?? ''),
                'level' => $level, 'level_label' => $pair($level, (string) ($en['levels'][$level] ?? $level)),
                'title' => $pair($t['title'], $e['title'] ?? null),
                'summary' => $pair($t['summary'] ?? '', $e['summary'] ?? null),
                'goal' => $pair($t['goal'] ?? '', $e['goal'] ?? null),
                'prerequisites' => $list('prerequisites'),
                // Schritte = Transkript der Videos (HTML mit <b>, <code>, <kbd>); steps_text = dasselbe als Klartext
                'steps' => ['de' => array_values($t['steps'] ?? []), 'en' => $stepsEn ?: array_values($t['steps'] ?? [])],
                'steps_text' => ['de' => array_map($text, array_values($t['steps'] ?? [])), 'en' => array_map('strval', (array) ($e['steps'] ?? $t['steps'] ?? []))],
                'tips' => $list('tips'),
                'pitfalls' => $list('pitfalls'),
                'commands' => (string) ($t['commands'] ?? ''),
                'manual' => $manual,
                'feature' => (string) ($t['feature'] ?? ''),
                'video' => $files((string) ($t['video'] ?? $slug)),
                'mobile' => !empty($t['mobile']) && ($mf = $files((string) $t['mobile'])) ? $mf + ['text' => $pair($t['mobileText'] ?? '', $e['mobileText'] ?? null)] : null,
            ];
        }
        return ['generator' => 'KLXM Studio ' . (defined('CMS_VERSION') ? CMS_VERSION : ''), 'exported' => date('c'),
            'audio' => ($manifest['audio'] ?? false) !== false, 'levels' => ['de' => self::LEVELS, 'en' => array_map(fn($l) => (string) ($en['levels'][$l] ?? $l), self::LEVELS)],
            'tracks' => $tracks, 'tutorials' => $out];
    }
}
