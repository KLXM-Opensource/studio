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
    /**
     * Rechte je Art der Ablage (zusätzlich zu „media.upload“): Blockvorschauen gelten für die ganze Website (Einstellung
     * block_previews, Darstellung des Kits) → „design.edit“; Symbole und eigene Bausteine sind Bausteine für eigene Blöcke
     * und den Editor der Website → „blocks.build“. Infografiken (Mediathek) brauchen nur „media.upload“.
     */
    public const RIGHTS = ['preview' => 'design.edit', 'icon' => 'blocks.build', 'part' => 'blocks.build'];

    /** Darf der angemeldete Benutzer Grafiken dieser Art anlegen, ändern, zuordnen, löschen? */
    public static function canKind(string $kind): bool
    {
        if (!isset(self::RIGHTS[$kind])) return $kind === 'info' || $kind === 'media';
        return can('media.upload') && can(self::RIGHTS[$kind]);
    }
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

    /** @return list<array{id: int, created_at: string, user: string}> frühere Fassungen, neueste zuerst (user = wer sie durch Speichern ersetzt hat) */
    public static function versions(string $ref): array
    {
        $db = app()->db;
        $names = [];
        $rows = $db->fetchAll('SELECT id, created_at, user_id FROM graphic_versions WHERE ref = ? ORDER BY id DESC', [$ref]);
        foreach (array_unique(array_filter(array_map(fn($v) => (int) $v['user_id'], $rows))) as $uid) {
            $u = $db->fetch('SELECT name, email FROM users WHERE id = ?', [$uid]);
            $names[$uid] = $u ? (string) ($u['name'] ?: $u['email']) : '';
        }
        return array_map(fn($v) => ['id' => (int) $v['id'], 'created_at' => (string) $v['created_at'], 'user' => $names[(int) $v['user_id']] ?? ''], $rows);
    }

    /**
     * Wo wird die Grafik verwendet? media:{id} → Seiten, Blöcke, Einträge (Media::usages), graphic:{id} → Blockauswahl (Vorschau eines Blocks).
     * @return list<array{label: string, url: ?string, preview: ?string}> preview = öffentliche Adresse für die Vorschau der Seite
     */
    public static function usages(string $ref): array
    {
        if (preg_match('~^media:(\d+)$~', $ref, $m)) {
            $out = [];
            foreach (Media::usages((int) $m[1]) as $u) {
                $url = isset($u['url']) && is_string($u['url']) ? $u['url'] : null;
                $preview = $url !== null && !str_starts_with($url, url('/admin')) ? $url : null;
                $out[] = ['label' => (string) $u['label'], 'url' => $url, 'preview' => $preview];
            }
            return $out;
        }
        if (preg_match('~^graphic:(\d+)$~', $ref, $m)) {
            $out = [];
            $blocks = self::blockTypes();
            foreach (self::previewMap() as $block => $id) {
                if ((int) $id === (int) $m[1]) $out[] = ['label' => __('Blockauswahl: {name}', ['name' => (string) ($blocks[$block] ?? $block)]), 'url' => url('/admin/grafik'), 'preview' => null];
            }
            return $out;
        }
        return [];
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
                // token = Name des Design-Tokens (Andockstelle für Farben als Variablen: der Editor merkt ihn am Element)
                // dark = Wert im dunklen Farbschema (Hell/Dunkel im Editor, Core\Graphics\Themed); ohne eigenen Dunkel-Wert = hell
                if ($c !== null) $out[] = ['name' => __((string) ($t['label'] ?? $name)), 'color' => $c, 'token' => (string) $name,
                    'dark' => \Core\Design::color($values[$name . '@dark'] ?? null) ?? $c];
            }
        } catch (\Throwable) {
            // Kit ohne Design-Tokens: keine Farbfelder des Designs
        }
        return $out;
    }

    /**
     * Abschnitts-Hintergründe des Kits (theme.php 'backgrounds': Standard, Getönt, Akzent, Dunkel …) mit Farben hell/dunkel für
     * die Vorschau im Editor. Die Farben kommen aus den Design-Tokens mit üblichen Namen (background, surface, accent,
     * secondary, dark_section …); „Akzent hell“ = 14 % Akzent im Hintergrund (wie fluid); Unbekanntes fällt weg.
     * @return list<array{id: string, label: string, light: string, dark: string}>
     */
    public static function sections(): array
    {
        $pal = Themed::palette();
        $pick = function (array $names) use ($pal): ?array {
            foreach ($names as $n) if (isset($pal[$n])) return $pal[$n];
            return null;
        };
        $mix = function (string $a, string $b, float $p): string {
            $ca = sscanf($a, '#%02x%02x%02x'); $cb = sscanf($b, '#%02x%02x%02x');
            return sprintf('#%02X%02X%02X', ...array_map(fn($i) => (int) round($ca[$i] * $p + $cb[$i] * (1 - $p)), [0, 1, 2]));
        };
        $bg = $pick(['background', 'bg', 'paper', 'page', 'base']) ?? ['light' => '#FFFFFF', 'dark' => '#111318'];
        $acc = $pick(['accent', 'primary', 'brand', 'signal']);
        $map = [
            'white' => $bg,
            'muted' => $pick(['surface', 'bg_muted', 'muted_bg', 'tinted', 'gray', 'grey', 'surface_alt']) ?? ['light' => $mix('#000000', $bg['light'], .04), 'dark' => $mix('#FFFFFF', $bg['dark'], .05)],
            'tint' => $acc ? ['light' => $mix($acc['light'], $bg['light'], .14), 'dark' => $mix($acc['dark'], $bg['dark'], .14)] : null,
            'accent' => $acc,
            'secondary' => $pick(['secondary', 'accent2', 'brand2']),
            'dark' => $pick(['dark_section', 'dark_bg', 'night', 'dark', 'panel_dark']) ?? ['light' => '#111827', 'dark' => '#0B0D14'],
        ];
        $out = [];
        foreach ((array) app()->theme->backgrounds() as $id => $label) {
            $c = $map[(string) $id] ?? null;
            if ($c === null) continue;
            $out[] = ['id' => (string) $id, 'label' => __((string) $label), 'light' => $c['light'], 'dark' => $c['dark']];
        }
        if (!$out) $out[] = ['id' => 'white', 'label' => __('Standard'), 'light' => $bg['light'], 'dark' => $bg['dark']];
        return $out;
    }

    /**
     * Schriftdateien für „Text in Kurven umwandeln“ (fontkit liest WOFF2 und variable Schriften): installierte Schriften des
     * Schriften-Managers (Core\Fonts) und Lato der Verwaltung. [Familie => ['files' => [[url, subset, style, weight|null]],
     * 'css' => font.css oder null]] – weight null = variable Schrift (Achse wght).
     * Dazu axes (variable Schriften: Tag => min/max/default) und category (serif, sans-serif, …) für die Schriftauswahl.
     * @return array<string, array{files: list<array{url: string, subset: string, style: string, weight: ?int}>, css: ?string, axes?: ?array<string, array{min: float, max: float, default: float}>, category?: ?string}>
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
                // Achsen (variable Schriften) und Art (Serife, serifenlos …) für die Schriftauswahl
                $axes = [];
                foreach ((array) ($f['axes'] ?? []) as $tag => $a) {
                    if (is_string($tag) && preg_match('~^[A-Za-z]{4}$~', $tag) && is_array($a)) $axes[$tag] = ['min' => (float) ($a['min'] ?? 0), 'max' => (float) ($a['max'] ?? 0), 'default' => (float) ($a['default'] ?? 0)];
                }
                if ($files) $out[$family] = ['files' => $files, 'css' => \Core\Fonts::cssUrl((string) $id), 'axes' => $axes ?: null,
                    'category' => preg_match('~^[a-z-]{1,20}$~', (string) ($f['category'] ?? '')) ? (string) $f['category'] : null];
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

    // ================================================================= Bibliothek: Ordner, Namen, Austausch als Datei

    /** Name ohne Markup, gekürzt */
    public static function cleanName(string $name): string
    {
        return mb_substr(trim((string) preg_replace('~\s+~u', ' ', strip_tags($name))), 0, 120);
    }

    /** Ordnername (frei, ohne Markup; leer = ohne Ordner) */
    public static function cleanFolder(string $folder): string
    {
        return mb_substr(trim((string) preg_replace('~[\x00-\x1f<>/\\\\]+~u', ' ', strip_tags($folder))), 0, 60);
    }

    /** Eigene Bausteine der Website für den Editor (mit SVG und Ordner) */
    public static function partsForEditor(): array
    {
        $out = [];
        foreach (app()->db->fetchAll("SELECT id, name, folder, svg, width, height, updated_at FROM graphics WHERE kind = 'part' ORDER BY folder, name, id") as $r) {
            $out[] = ['id' => (int) $r['id'], 'kind' => 'part', 'name' => (string) $r['name'], 'folder' => (string) ($r['folder'] ?? ''), 'svg' => (string) $r['svg'],
                'width' => (int) $r['width'], 'height' => (int) $r['height'], 'updated_at' => (string) $r['updated_at']];
        }
        return $out;
    }

    /** Baustein der Website umbenennen bzw. in einen Ordner legen */
    public static function updatePart(int $id, ?string $name, ?string $folder): bool
    {
        $g = self::find($id);
        if (!$g || $g['kind'] !== 'part') return false;
        $row = ['updated_at' => now()];
        if ($name !== null && ($n = self::cleanName($name)) !== '') $row['name'] = $n;
        if ($folder !== null) $row['folder'] = self::cleanFolder($folder);
        app()->db->update('graphics', $row, 'id = :id', ['id' => $id]);
        return true;
    }

    /**
     * Bausteine aus einer Austauschdatei (JSON des Editors: {format: 'klxm-grafik-bausteine', version: 1, items: [{name, folder, svg}]}).
     * Jedes SVG wird bereinigt; ungültige Einträge werden übersprungen. Rückgabe: [gespeichert (Einträge), übersprungen]
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    public static function importParts(mixed $data, string $scope = 'site'): array
    {
        if (!is_array($data) || ($data['format'] ?? '') !== 'klxm-grafik-bausteine' || !is_array($data['items'] ?? null)) {
            throw new \RuntimeException(__('Das ist keine Austauschdatei des Grafik-Editors.'));
        }
        $done = []; $skipped = 0;
        foreach (array_slice($data['items'], 0, 200) as $it) {
            if (!is_array($it) || !is_string($it['svg'] ?? null) || strlen($it['svg']) > Svg::MAX_BYTES) { $skipped++; continue; }
            try {
                if ($scope === 'network') {
                    $done[] = Shared::store((string) ($it['name'] ?? ''), $it['svg'], (string) ($it['folder'] ?? '')) + ['scope' => 'network'];
                } else {
                    $item = self::store('part', (string) ($it['name'] ?? ''), $it['svg']);
                    self::updatePart($item['id'], null, (string) ($it['folder'] ?? ''));
                    $done[] = ['id' => $item['id'], 'name' => $item['name'], 'folder' => self::cleanFolder((string) ($it['folder'] ?? '')), 'svg' => (string) (self::find($item['id'])['svg'] ?? ''), 'scope' => 'site'];
                }
            } catch (\RuntimeException) {
                $skipped++;
            }
        }
        return [$done, $skipped];
    }

    // ================================================================= Formatvorlagen für Text (Bibliothek der Website)

    /** Eigenschaften, die eine Vorlage tragen darf (Zeichen bzw. Absatz) */
    private const STYLE_PROPS = ['font-family', 'font-weight', 'font-style', 'font-size', 'fill', 'letter-spacing', 'text-decoration', 'font-feature-settings', 'font-variation-settings'];
    private const STYLE_PARA = ['data-ge-para', 'data-ge-lh', 'text-anchor', 'word-spacing'];

    /** @return array{p: list<array<string, mixed>>, c: list<array<string, mixed>>} Absatz- und Zeichenvorlagen der Website */
    public static function textStyles(): array
    {
        return self::cleanStyles(app()->settings->get('graphic_text_styles', []));
    }

    /** Vorlagen der Website speichern (bereinigt: bekannte Eigenschaften, Werte ohne Markup, url(), Ausdrücke) */
    public static function saveTextStyles(mixed $styles): array
    {
        $clean = self::cleanStyles($styles);
        app()->settings->set('graphic_text_styles', $clean);
        return $clean;
    }

    /** @return array{p: list<array<string, mixed>>, c: list<array<string, mixed>>} */
    public static function cleanStyles(mixed $in): array
    {
        $out = ['p' => [], 'c' => []];
        $val = fn(mixed $v): ?string => is_scalar($v) && ($s = trim((string) $v)) !== '' && mb_strlen($s) <= 300
            && !preg_match('~[<>{}@\\\\]|url\(|expression|javascript:|data:~i', $s) ? $s : null;
        foreach (['p', 'c'] as $kind) {
            foreach (is_array($in[$kind] ?? null) ? $in[$kind] : [] as $st) {
                if (!is_array($st)) continue;
                $n = trim(strip_tags((string) ($st['n'] ?? '')));
                if ($n === '' || mb_strlen($n) > 60) continue;
                $props = [];
                foreach (self::STYLE_PROPS as $k) if (($v = $val($st['props'][$k] ?? null)) !== null) $props[$k] = $v;
                $row = ['n' => $n, 'props' => $props];
                if ($kind === 'p') {
                    $para = [];
                    foreach (self::STYLE_PARA as $k) if (($v = $val($st['para'][$k] ?? null)) !== null) $para[$k] = $v;
                    $row['para'] = $para;
                }
                $out[$kind][] = $row;
                if (count($out[$kind]) >= 100) break;
            }
        }
        return $out;
    }

    // ================================================================= Persönliche Einstellungen (Core\UiPrefs, Schlüssel „grafik“)

    /** Farbmodi des Bereichs „Farbe“ */
    public const COLOR_MODES = ['hex', 'rgb', 'hsl', 'hsb', 'oklch'];

    /**
     * Einstellungen des Editors je Konto: Favoriten und zuletzt verwendete Schriften (Stapel bzw. Familien), Farbmodus.
     * @return array{fontFav: list<string>, fontRecent: list<string>, colorMode: string, libFav: list<string>, libSiteColors: bool, marquee: string}
     */
    public static function prefs(?array $user): array
    {
        $p = \Core\UiPrefs::get($user, 'grafik', []);
        return self::cleanPrefs(is_array($p) ? $p : []);
    }

    /** Ein Wert setzen (fontFav, fontRecent, colorMode) – unbekannte Schlüssel und Werte werden verworfen */
    public static function setPref(int $userId, string $key, mixed $value): array
    {
        $raw = app()->db->fetchValue('SELECT ui_prefs FROM users WHERE id = ?', [$userId]);
        $all = json_decode((string) $raw, true);
        $cur = is_array($all) && is_array($all['grafik'] ?? null) ? $all['grafik'] : [];
        if (!in_array($key, ['fontFav', 'fontRecent', 'colorMode', 'libFav', 'libSiteColors', 'marquee'], true)) throw new \RuntimeException(__('Unbekannte Einstellung.'));
        $cur[$key] = $value;
        $clean = self::cleanPrefs($cur);
        \Core\UiPrefs::set($userId, 'grafik', $clean);
        return $clean;
    }

    /** Nur Text ohne Markup, begrenzte Längen (Schriftnamen bzw. -stapel) */
    private static function cleanPrefs(array $p): array
    {
        $list = function (mixed $v, int $max): array {
            $out = [];
            foreach (is_array($v) ? $v : [] as $s) {
                if (!is_string($s)) continue;
                $s = trim($s);
                if ($s === '' || mb_strlen($s) > 120 || !preg_match('~^[\p{L}\p{N} ,\'"_.-]+$~u', $s)) continue;
                if (!in_array($s, $out, true)) $out[] = $s;
                if (count($out) >= $max) break;
            }
            return $out;
        };
        $mode = is_string($p['colorMode'] ?? null) && in_array($p['colorMode'], self::COLOR_MODES, true) ? $p['colorMode'] : 'hex';
        // Favoriten der Bibliothek: lib:{id} (mitgeliefert), part:{id} (Website), net:{id} (alle Websites)
        $fav = array_values(array_unique(array_filter(is_array($p['libFav'] ?? null) ? $p['libFav'] : [], fn($v) => is_string($v) && preg_match('~^(lib|part|net):[a-z0-9_-]{1,60}$~i', $v))));
        return ['fontFav' => $list($p['fontFav'] ?? [], 60), 'fontRecent' => $list($p['fontRecent'] ?? [], 8), 'colorMode' => $mode,
            'libFav' => array_slice($fav, 0, 200), 'libSiteColors' => !empty($p['libSiteColors']),
            // Auswahlrahmen: berühren (Standard) oder vollständig enthalten
            'marquee' => ($p['marquee'] ?? '') === 'contain' ? 'contain' : 'touch'];
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
