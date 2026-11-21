<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Graphics;

use Core\Media;
use Core\Svg;

/**
 * Grafik-Editor (Verwaltung → Medien → Neu → Grafik, resources/js/grafik.js): Ablage und Speichern.
 *
 * Zwei Ziele:
 *  - Infografiken → Mediathek (normale Medien mit Alt-Text, Titel; Ersetzen über Media::replace, ID und Verwendungen bleiben)
 *  - Symbole, Blockvorschauen und eigene Bausteine → eigene Ablage: Tabelle `graphics`, Dateien in {Medienordner}/grafik/ –
 *    nie in der Mediathek, in Auswahldialogen oder in Media::all(). Blockvorschauen wirken über die Website-Einstellung
 *    'block_previews' (Core\Blocks\Picker::overrides) – dieselbe Andockstelle, die Kits und Erweiterungen kennen.
 *
 * Jede Grafik läuft beim Speichern durch Core\Svg::clean() (keep_ids: ids für Ebenen und spätere Animationen bleiben);
 * das SVG aus dem Browser wird nie ungeprüft gespeichert. Beim Überschreiben bleibt die vorige Fassung (graphic_versions,
 * höchstens VERSIONS je Grafik).
 */
final class Graphics
{
    /** Formate: Infografik (frei, Mediathek), Symbol 24 × 24, Blockvorschau 160 × 100 (16:10) */
    public const FORMATS = [
        'info' => ['w' => 800, 'h' => 500, 'store' => 'media', 'free' => true],
        'icon' => ['w' => 24, 'h' => 24, 'store' => 'graphics', 'free' => false],
        'preview' => ['w' => 160, 'h' => 100, 'store' => 'graphics', 'free' => false],
    ];
    /** Arten in der eigenen Ablage */
    public const KINDS = ['icon', 'preview', 'part'];
    public const VERSIONS = 20;
    /** Größte Zeichenfläche einer Infografik (Seitenlänge) */
    public const MAX_SIZE = 10000;

    /** Bezeichnungen der Formate (übersetzt) */
    public static function formatLabels(): array
    {
        return ['info' => __('Infografik'), 'icon' => __('Symbol'), 'preview' => __('Blockvorschau'), 'part' => __('Baustein')];
    }

    /** Ordner der eigenen Ablage (öffentlich: Symbole und Vorschauen werden als <img> gezeigt) */
    public static function dir(): string
    {
        return site()->mediaDir('grafik');
    }

    public static function url(string $file): string
    {
        return site()->mediaUrl('grafik/' . $file);
    }

    // ================================================================= Ablage (Symbole, Vorschauen, Bausteine)

    /** @return list<array<string, mixed>> Einträge einer Art (ohne SVG-Inhalt), neueste zuerst */
    public static function all(?string $kind = null): array
    {
        $sql = 'SELECT id, kind, name, block, file, width, height, created_at, updated_at FROM graphics';
        $rows = $kind !== null
            ? app()->db->fetchAll($sql . ' WHERE kind = ? ORDER BY updated_at DESC, id DESC', [$kind])
            : app()->db->fetchAll($sql . ' ORDER BY kind, updated_at DESC, id DESC');
        return array_map([self::class, 'toJson'], $rows);
    }

    public static function find(int $id): ?array
    {
        return app()->db->fetch('SELECT * FROM graphics WHERE id = ?', [$id]);
    }

    public static function toJson(array $g): array
    {
        $cur = self::previewMap();
        return [
            'id' => (int) $g['id'], 'kind' => (string) $g['kind'], 'name' => (string) $g['name'],
            'block' => $g['block'] !== null && $g['block'] !== '' ? (string) $g['block'] : null,
            'url' => !empty($g['file']) ? self::url((string) $g['file']) : null,
            'width' => (int) ($g['width'] ?? 0), 'height' => (int) ($g['height'] ?? 0),
            'active' => !empty($g['block']) && !empty($g['file']) && ($cur[(string) $g['block']] ?? '') === self::url((string) $g['file']),
            'updated_at' => (string) ($g['updated_at'] ?? ''),
        ];
    }

    /**
     * Speichert eine Grafik der eigenen Ablage (neu oder überschreiben). Bausteine (part) nur in der Datenbank, Symbole und
     * Vorschauen zusätzlich als Datei (neuer Name je Fassung – kein Cache-Problem).
     * @return array<string, mixed> Eintrag (toJson)
     * @throws \RuntimeException verständliche Meldung
     */
    public static function store(string $kind, string $name, string $raw, ?int $id = null, ?string $block = null, ?string $source = null): array
    {
        if (!in_array($kind, self::KINDS, true)) throw new \RuntimeException(__('Unbekannte Art.'));
        $name = mb_substr(trim(strip_tags($name)), 0, 120);
        if ($name === '') $name = self::formatLabels()[$kind];
        $block = $block !== null && preg_match('~^[a-z0-9_-]{1,80}$~', $block) ? $block : null;
        $r = Svg::clean($raw, ['keep_ids' => true]);
        // Spalte svg = Arbeitsfassung des Editors (Ebenen, Hilfslinien …), Datei = Web-Fassung (optimiert)
        $work = $source !== null && $source !== '' ? Svg::clean($source, ['editor' => true])['svg'] : $r['svg'];
        $old = $id ? self::find($id) : null;
        if ($id && (!$old || $old['kind'] !== $kind)) throw new \RuntimeException(__('Grafik nicht gefunden.'));
        $db = app()->db;
        $row = ['kind' => $kind, 'name' => $name, 'block' => $kind === 'preview' ? $block : null, 'svg' => $kind === 'part' ? $r['svg'] : $work,
            'width' => $r['width'], 'height' => $r['height'], 'updated_at' => now()];
        if ($old) {
            if ((string) $old['svg'] !== $row['svg']) self::addVersion('graphic:' . $old['id'], (string) $old['svg']);
            $db->update('graphics', $row, 'id = :id', ['id' => (int) $old['id']]);
            $gid = (int) $old['id'];
        } else {
            $gid = $db->insert('graphics', $row + ['created_at' => now(), 'user_id' => (int) (app()->auth->user()['id'] ?? 0) ?: null]);
        }
        if ($kind !== 'part') {
            $dir = self::dir();
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            Svg::guard($dir);
            $file = $kind . '-' . $gid . '-' . substr(sha1($r['svg']), 0, 8) . '.svg';
            if (file_put_contents($dir . '/' . $file, $r['svg']) === false) throw new \RuntimeException(__('Datei konnte nicht gespeichert werden.'));
            $was = (string) ($old['file'] ?? '');
            if ($was !== '' && $was !== $file) @unlink($dir . '/' . basename($was));
            $db->update('graphics', ['file' => $file], 'id = :id', ['id' => $gid]);
            // Vorschau, die schon gilt, zeigt auf die neue Datei
            if ($was !== '' && $old && !empty($old['block'])) {
                $map = self::previewMap();
                if (($map[(string) $old['block']] ?? '') === self::url($was)) self::assignPreview((string) $old['block'], $gid);
            }
        }
        \Core\PageCache::clear();
        return self::toJson((array) self::find($gid));
    }

    /** Löscht eine Grafik der Ablage samt Datei, Fassungen und Zuordnung als Blockvorschau */
    public static function delete(int $id): bool
    {
        $g = self::find($id);
        if (!$g) return false;
        if (!empty($g['file'])) {
            $url = self::url((string) $g['file']);
            $map = self::previewMap();
            $left = array_filter($map, fn($u) => $u !== $url);
            if ($left !== $map) app()->settings->set('block_previews', $left);
            @unlink(self::dir() . '/' . basename((string) $g['file']));
        }
        app()->db->query('DELETE FROM graphics WHERE id = ?', [$id]);
        app()->db->query('DELETE FROM graphic_versions WHERE ref = ?', ['graphic:' . $id]);
        app()->db->query('DELETE FROM graphic_sources WHERE ref = ?', ['graphic:' . $id]);
        \Core\PageCache::clear();
        return true;
    }

    // ================================================================= Blockvorschauen (Website-Einstellung block_previews)

    /** @return array<string, string> [Blocktyp => Adresse] */
    public static function previewMap(): array
    {
        $m = app()->settings->get('block_previews', []);
        return is_array($m) ? array_filter($m, fn($v, $k) => is_string($v) && is_string($k), ARRAY_FILTER_USE_BOTH) : [];
    }

    /** Blockvorschau zuordnen ($id) bzw. aufheben (null) – danach zeigt die Blockauswahl diese Grafik für den Blocktyp */
    public static function assignPreview(string $block, ?int $id): void
    {
        if (!preg_match('~^[a-z0-9_-]{1,80}$~', $block)) throw new \RuntimeException(__('Unbekannter Blocktyp.'));
        $map = self::previewMap();
        if ($id === null) {
            unset($map[$block]);
        } else {
            $g = self::find($id);
            if (!$g || $g['kind'] !== 'preview' || empty($g['file'])) throw new \RuntimeException(__('Grafik nicht gefunden.'));
            $map[$block] = self::url((string) $g['file']);
            if ((string) $g['block'] !== $block) app()->db->update('graphics', ['block' => $block], 'id = :id', ['id' => $id]);
        }
        app()->settings->set('block_previews', $map);
    }

    /** Blocktypen des aktiven Kits für die Zuordnung [typ => Bezeichnung], dazu Varianten als „typ--variante“ */
    public static function blockTypes(): array
    {
        $out = [];
        foreach ((array) app()->theme->blocks() as $type => $b) {
            if (!is_array($b)) continue;
            $label = __((string) ($b['label'] ?? $type));
            $out[(string) $type] = $label;
            if (is_array($b['variants'] ?? null) && count($b['variants']) > 1) {
                foreach ($b['variants'] as $v => $vl) if (preg_match('~^[a-z0-9_-]{1,40}$~', (string) $v)) $out[$type . '--' . $v] = $label . ' – ' . __((string) $vl);
            }
        }
        asort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }

    // ================================================================= Mediathek (Infografiken)

    /**
     * Speichert eine Infografik in die Mediathek: neu (Media::import) oder als neue Fassung einer vorhandenen SVG
     * (Media::replace – ID, Alt-Text, Verwendungen bleiben; vorige Fassung in graphic_versions).
     * $meta: alt, title, decorative, name (Dateiname ohne Endung)
     * @return array{0: ?array, 1: ?string} [Medium, Fehler]
     */
    public static function saveMedia(string $raw, array $meta, ?int $mediaId = null, ?string $source = null): array
    {
        try {
            $r = Svg::clean($raw, ['keep_ids' => true]);
            $work = $source !== null && $source !== '' ? Svg::clean($source, ['editor' => true])['svg'] : null;
        } catch (\RuntimeException $e) {
            return [null, $e->getMessage()];
        }
        $tmp = tempnam(sys_get_temp_dir(), 'grafik');
        if ($tmp === false || file_put_contents($tmp, $r['svg']) === false) return [null, __('Datei konnte nicht gespeichert werden.')];
        $name = Media::fileSlug(trim((string) ($meta['name'] ?? '')) ?: (trim((string) ($meta['title'] ?? '')) ?: 'grafik')) . '.svg';
        try {
            if ($mediaId) {
                $old = Media::find($mediaId);
                if (!$old || ($old['mime'] ?? '') !== Svg::MIME) return [null, __('Nur SVG-Grafiken lassen sich im Grafik-Editor bearbeiten.')];
                if (!empty($old['pool_ref'])) return [null, __('Geteilte Medien (Pools) lassen sich hier nicht überschreiben.')];
                // ohne neuen Namen bleibt der bisherige (Anzeigename und sprechende Adresse)
                if (trim((string) ($meta['name'] ?? '')) === '') $name = (string) ($old['original_name'] ?: basename((string) $old['file']));
                $prev = @file_get_contents(Media::path($old));
                $opt = ['graphic' => true];
                if (array_key_exists('alt', $meta)) $opt['alt'] = (string) $meta['alt'];
                [$m, $err] = Media::replace($mediaId, $tmp, $name, $opt);
                if ($m && is_string($prev) && $prev !== '') self::addVersion('media:' . $mediaId, $prev);
                if ($m) self::saveSource('media:' . $mediaId, $work, $r['svg']);
                if ($m && array_key_exists('title', $meta)) {
                    app()->db->update('media', ['title' => mb_substr(strip_tags((string) $meta['title']), 0, 180)], 'id = :id', ['id' => $mediaId]);
                    Media::forget($mediaId);
                    $m = Media::find($mediaId);
                }
                return [$m, $err];
            }
            $res = Media::import($tmp, $name, (string) ($meta['alt'] ?? ''), [
                'graphic' => true, 'title' => (string) ($meta['title'] ?? ''), 'decorative' => !empty($meta['decorative']),
                'tags' => (string) ($meta['tags'] ?? ''),
            ]);
            if (!empty($res[0]['id'])) self::saveSource('media:' . (int) $res[0]['id'], $work, $r['svg']);
            return $res;
        } finally {
            @unlink($tmp);
        }
    }

    // ================================================================= Arbeitsfassung (Editor) zur Web-Fassung

    /**
     * Arbeitsfassung merken (null = vergessen): gilt nur, solange die Web-Datei dieselbe ist (hash). So bleiben Ebenennamen,
     * Sperren, Hilfslinien und Textrahmen beim nächsten Öffnen erhalten, obwohl die ausgelieferte Datei sie nicht enthält.
     */
    public static function saveSource(string $ref, ?string $work, string $web): void
    {
        $db = app()->db;
        $db->query('DELETE FROM graphic_sources WHERE ref = ?', [$ref]);
        if ($work === null || $work === '' || $work === $web) return;
        $db->insert('graphic_sources', ['ref' => $ref, 'svg' => $work, 'hash' => sha1($web), 'updated_at' => now()]);
    }

    /** Arbeitsfassung zur Web-Datei $web (null, wenn keine oder die Datei inzwischen anders ist) */
    public static function source(string $ref, string $web): ?string
    {
        $row = app()->db->fetch('SELECT svg, hash FROM graphic_sources WHERE ref = ?', [$ref]);
        return $row && hash_equals((string) $row['hash'], sha1($web)) ? (string) $row['svg'] : null;
    }

    // ================================================================= Fassungen

    public static function addVersion(string $ref, string $svg): void
    {
        $db = app()->db;
        $db->insert('graphic_versions', ['ref' => $ref, 'svg' => $svg, 'user_id' => (int) (app()->auth->user()['id'] ?? 0) ?: null, 'created_at' => now()]);
        $keep = $db->fetchAll('SELECT id FROM graphic_versions WHERE ref = ? ORDER BY id DESC LIMIT ' . self::VERSIONS, [$ref]);
        if (count($keep) >= self::VERSIONS) {
            $db->query('DELETE FROM graphic_versions WHERE ref = ? AND id < ?', [$ref, (int) end($keep)['id']]);
        }
    }

    /** @return list<array{id: int, created_at: string}> frühere Fassungen, neueste zuerst */
    public static function versions(string $ref): array
    {
        return array_map(fn($v) => ['id' => (int) $v['id'], 'created_at' => (string) $v['created_at']],
            app()->db->fetchAll('SELECT id, created_at FROM graphic_versions WHERE ref = ? ORDER BY id DESC', [$ref]));
    }

    public static function version(string $ref, int $id): ?string
    {
        $v = app()->db->fetchValue('SELECT svg FROM graphic_versions WHERE ref = ? AND id = ?', [$ref, $id]);
        return is_string($v) ? $v : null;
    }

    // ================================================================= Editor-Daten

    /** Leere Zeichenfläche eines Formats (Symbol: Strichstil wie die Kern-Symbole, Farbe über currentColor) */
    public static function blank(string $format, int $w = 0, int $h = 0): string
    {
        $f = self::FORMATS[$format] ?? self::FORMATS['info'];
        if (!empty($f['free'])) {
            $w = $w > 0 ? min(self::MAX_SIZE, $w) : $f['w'];
            $h = $h > 0 ? min(self::MAX_SIZE, $h) : $f['h'];
        } else {
            [$w, $h] = [$f['w'], $f['h']];
        }
        $attrs = $format === 'icon' ? ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' : '';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '"' . $attrs . '></svg>';
    }

    /** Vorlage für eine Blockvorschau: die Vorschau des Kits bzw. Kerns für diesen Blocktyp bzw. „typ--variante“ (falls vorhanden) */
    public static function previewTemplate(string $block): ?string
    {
        if (!preg_match('~^[a-z0-9_-]{1,80}$~', $block)) return null;
        [$type, $variant] = array_pad(explode('--', $block, 2), 2, null);
        $theme = app()->theme;
        $blocks = (array) $theme->blocks();
        $p = \Core\Blocks\Picker::previews($theme, [$type => (array) ($blocks[$type] ?? [])]);
        $url = (string) (($variant !== null ? ($p[$type]['variants'][$variant] ?? null) : null) ?? ($p[$type]['url'] ?? ''));
        if ($url === '') return null;
        $path = parse_url($url, PHP_URL_PATH);
        $base = base_path();
        if (!is_string($path)) return null;
        if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
        $file = realpath(ROOT . '/public' . $path);
        if (!$file || !str_starts_with($file, realpath(ROOT . '/public') . '/') || !str_ends_with($file, '.svg')) return null;
        try {
            return Svg::clean((string) file_get_contents($file), ['keep_ids' => true])['svg'];
        } catch (\RuntimeException) {
            return null;
        }
    }

    /** Farbfelder: Farben des Designs der Website (Core\Design, Tokens vom Typ color) */
    public static function swatches(): array
    {
        $out = [];
        try {
            $values = \Core\Design::values();
            foreach (\Core\Design::tokens() as $name => $t) {
                if (($t['type'] ?? '') !== 'color') continue;
                $c = \Core\Design::color($values[$name] ?? ($t['default'] ?? null));
                if ($c !== null) $out[] = ['name' => __((string) ($t['label'] ?? $name)), 'color' => $c];
            }
        } catch (\Throwable) {
            // Kit ohne Design-Tokens: keine Farbfelder des Designs
        }
        return $out;
    }

    /**
     * Schriftdateien für „Text in Kurven umwandeln“ (fontkit liest WOFF2 und variable Schriften): installierte Schriften des
     * Schriften-Managers (Core\Fonts) und Lato der Verwaltung. [Familie => ['files' => [[url, subset, style, weight|null]],
     * 'css' => font.css oder null]] – weight null = variable Schrift (Achse wght).
     * @return array<string, array{files: list<array{url: string, subset: string, style: string, weight: ?int}>, css: ?string}>
     */
    public static function fontFiles(): array
    {
        $out = [];
        $parse = function (string $file): ?array {
            if (!preg_match('~^([a-z0-9-]+?)-(wght|\d{3})-(normal|italic)\.woff2$~', $file, $m)) return null;
            return ['subset' => $m[1], 'weight' => $m[2] === 'wght' ? null : (int) $m[2], 'style' => $m[3]];
        };
        try {
            foreach (\Core\Fonts::installed() as $id => $f) {
                $family = \Core\Fonts::cleanFamily((string) ($f['family'] ?? ''));
                if ($family === '') continue;
                $files = [];
                foreach ((array) ($f['files'] ?? []) as $file) {
                    $info = $parse((string) ($file['file'] ?? ''));
                    if ($info) $files[] = ['url' => \Core\Fonts::url($id . '/' . $file['file'])] + $info;
                }
                if ($files) $out[$family] = ['files' => $files, 'css' => \Core\Fonts::cssUrl((string) $id)];
            }
        } catch (\Throwable) {
            // ohne Schriften-Manager: nur die Schrift der Verwaltung
        }
        if (!isset($out['Lato'])) {
            $files = [];
            foreach (glob(ROOT . '/public/assets/fonts/lato-*.woff2') ?: [] as $path) {
                $info = $parse(substr(basename($path), 5));
                if ($info) $files[] = ['url' => asset('fonts/' . basename($path))] + $info;
            }
            if ($files) $out['Lato'] = ['files' => $files, 'css' => null];
        }
        return $out;
    }

    /** Schriften für Text: Kit-Schriften (Core\Design::fonts) und allgemeine Familien */
    public static function fonts(): array
    {
        $out = [];
        try {
            // Schriften, die das Design der Website gerade verwendet (Tokens vom Typ font): erste Familie des Stapels
            $fonts = \Core\Design::fonts();
            $values = \Core\Design::values();
            foreach (\Core\Design::tokens() as $name => $t) {
                if (($t['type'] ?? '') !== 'font') continue;
                $stack = (string) ($fonts[(string) ($values[$name] ?? '')]['stack'] ?? '');
                $first = trim(explode(',', $stack)[0] ?? '', " \"'");
                if ($first !== '' && preg_match('~^[\w \-]{1,60}$~u', $first)) $out[] = $first;
            }
        } catch (\Throwable) {
            // Kit ohne Design-Tokens
        }
        return array_values(array_unique([...$out, 'Lato', 'system-ui', 'sans-serif', 'serif', 'monospace']));
    }
}
