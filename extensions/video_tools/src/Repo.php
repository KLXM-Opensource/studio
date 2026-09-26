<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

use Core\Database;
use Core\Media;

/**
 * Tabellen der Erweiterung (Datenbank der Website, SQLite oder MySQL):
 *   video_jobs   Hintergrund-Aufträge (optimize, trim, poster, preview) mit Stand, Fortschritt, Protokoll
 *   video_meta   Analyse je Video (zwischengespeichert, Schlüssel = Datei|Größe|Änderung), Poster, animierte Vorschau
 *   video_links  Herkunft erzeugter Dateien: Version/Ausschnitt/Poster von … (media_id → source_id)
 * Nur die Mediathek der Website – Dateien geteilter Pools bearbeitet die Erweiterung nicht.
 */
final class Repo
{
    private static array $meta = [];
    private static ?array $all = null;

    public static function migrate(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $long = $my ? 'MEDIUMTEXT' : 'TEXT';
        $ine = $my ? '' : 'IF NOT EXISTS ';
        $db->query("CREATE TABLE IF NOT EXISTS video_jobs (id $pk, type VARCHAR(12) NOT NULL, media_id INT NOT NULL, preset VARCHAR(20) NULL,
            params TEXT NULL, mode VARCHAR(10) NOT NULL DEFAULT 'new', status VARCHAR(12) NOT NULL DEFAULT 'queued', progress INT NOT NULL DEFAULT 0,
            message TEXT NULL, log $long NULL, result_id INT NULL, bytes_in BIGINT NULL, bytes_out BIGINT NULL, user_id INT NULL, pid INT NULL,
            cancel INT NOT NULL DEFAULT 0, attempts INT NOT NULL DEFAULT 0, batch VARCHAR(20) NULL,
            created_at VARCHAR(25), started_at VARCHAR(25) NULL, finished_at VARCHAR(25) NULL)");
        $db->query("CREATE INDEX {$ine}video_jobs_status ON video_jobs (status, id)");
        $db->query("CREATE INDEX {$ine}video_jobs_media ON video_jobs (media_id, id)");
        $db->query("CREATE TABLE IF NOT EXISTS video_meta (media_id INT NOT NULL PRIMARY KEY, sig VARCHAR(191) NOT NULL, info TEXT NULL, loud TEXT NULL,
            score INT NULL, optimized INT NOT NULL DEFAULT 0, faststart INT NULL, source VARCHAR(10) NULL, poster_id INT NULL, poster_at INT NULL,
            preview VARCHAR(191) NULL, checked_at VARCHAR(25) NULL)");
        $db->query("CREATE TABLE IF NOT EXISTS video_links (id $pk, media_id INT NOT NULL, source_id INT NOT NULL, kind VARCHAR(10) NOT NULL,
            preset VARCHAR(20) NULL, label VARCHAR(191) NULL, created_at VARCHAR(25))");
        $db->query("CREATE INDEX {$ine}video_links_source ON video_links (source_id)");
        $db->query("CREATE INDEX {$ine}video_links_media ON video_links (media_id)");
    }

    public static function ready(): bool
    {
        return (int) app()->settings->get('ext.video_tools.schema', 0) >= 1;
    }

    /** Video der Mediathek dieser Website (kein Pool, kein Verweis) oder null */
    public static function video(int $id): ?array
    {
        Media::usePool(null);
        $m = Media::find($id);
        if (!$m || !str_starts_with((string) $m['mime'], 'video/') || !empty($m['_pool']) || !empty($m['pool_ref'])) return null;
        return $m;
    }

    public static function path(array $m): string
    {
        return rtrim(Media::dir(), '/') . '/' . $m['file'];
    }

    private static function sig(array $m): string
    {
        return $m['file'] . '|' . (int) $m['size'] . '|' . ($m['updated_at'] ?? $m['created_at'] ?? '');
    }

    /** Alle Meta-Zeilen (einmal je Anfrage, für die Liste) */
    public static function allMeta(): array
    {
        if (self::$all !== null) return self::$all;
        self::$all = [];
        if (!self::ready()) return self::$all;
        foreach (app()->db->fetchAll('SELECT * FROM video_meta') as $r) self::$all[(int) $r['media_id']] = $r;
        return self::$all;
    }

    /**
     * Analyse eines Videos (zwischengespeichert). ffprobe, wenn vorhanden – sonst die Kopfdaten aus PHP (Mp4Info).
     * @return array{info: array, assess: array, row: array}
     */
    public static function meta(array $m, bool $refresh = false): array
    {
        $id = (int) $m['id'];
        if (!$refresh && isset(self::$meta[$id])) return self::$meta[$id];
        $row = app()->db->fetch('SELECT * FROM video_meta WHERE media_id = ?', [$id]);
        $sig = self::sig($m);
        $probeOk = Ffmpeg::available();
        $stale = !$row || $row['sig'] !== $sig || $refresh || ($probeOk && $row['source'] !== 'ffprobe');
        if ($stale) {
            $file = self::path($m);
            $head = str_contains((string) $m['mime'], 'mp4') ? Mp4Info::read($file) : null;
            $info = ($probeOk ? Ffmpeg::probe($file) : null) ?? ($head ?? ['ok' => false, 'streams' => [], 'size' => (int) @filesize($file)]);
            $info['source'] ??= 'php';
            if ($head) $info['faststart'] = $head['faststart'];
            $loud = $row && $row['sig'] === $sig && $row['loud'] ? json_decode((string) $row['loud'], true) : null;
            $a = Analyzer::assess($info, $loud);
            $data = ['sig' => $sig, 'info' => json_encode($info), 'loud' => $loud ? json_encode($loud) : null, 'score' => $a['score'], 'optimized' => $a['optimized'] ? 1 : 0,
                'faststart' => $info['faststart'] === null ? null : ($info['faststart'] ? 1 : 0), 'source' => $info['source'], 'checked_at' => now()];
            if ($row) app()->db->update('video_meta', $data, 'media_id = :id', ['id' => $id]);
            else app()->db->insert('video_meta', $data + ['media_id' => $id]);
            $row = app()->db->fetch('SELECT * FROM video_meta WHERE media_id = ?', [$id]);
            self::$all = null;
        }
        $info = json_decode((string) $row['info'], true) ?: [];
        $loud = $row['loud'] ? json_decode((string) $row['loud'], true) : null;
        $a = Analyzer::assess($info, $loud);
        // Bewertungsregeln können sich ändern (neue Version der Erweiterung) → gespeicherte Werte angleichen
        if ((int) $row['score'] !== $a['score'] || (bool) $row['optimized'] !== $a['optimized']) {
            app()->db->update('video_meta', ['score' => $a['score'], 'optimized' => $a['optimized'] ? 1 : 0], 'media_id = :id', ['id' => $id]);
            $row['score'] = $a['score'];
            $row['optimized'] = $a['optimized'] ? 1 : 0;
            self::$all = null;
        }
        return self::$meta[$id] = ['info' => $info, 'loud' => $loud, 'assess' => $a, 'row' => $row];
    }

    public static function setLoudness(array $m, ?array $loud): void
    {
        self::meta($m);
        app()->db->update('video_meta', ['loud' => $loud ? json_encode($loud) : null], 'media_id = :id', ['id' => (int) $m['id']]);
        unset(self::$meta[(int) $m['id']]);
        self::meta($m, true);
    }

    /** Analyse für alle Videos der Website nachholen (höchstens $limit je Aufruf – „Prüfen“ bleibt schnell) */
    public static function ensureAll(int $limit = 40): void
    {
        if (!self::ready()) return;
        $rows = app()->db->fetchAll("SELECT m.*, v.sig AS vt_sig FROM media m LEFT JOIN video_meta v ON v.media_id = m.id WHERE m.mime LIKE 'video/%' AND m.pool_ref IS NULL");
        $rows = array_slice(array_filter($rows, fn($m) => $m['vt_sig'] !== self::sig($m)), 0, max(1, $limit));
        foreach ($rows as $m) {
            unset($m['vt_sig']);
            try { self::meta($m); } catch (\Throwable $e) { error_log('[video_tools] meta ' . $m['id'] . ': ' . $e->getMessage()); }
        }
    }

    public static function setPoster(int $videoId, ?int $imageId, ?int $atMs = null): void
    {
        $m = self::video($videoId);
        if (!$m) return;
        self::meta($m);
        $old = app()->db->fetch('SELECT poster_id FROM video_meta WHERE media_id = ?', [$videoId]);
        app()->db->update('video_meta', ['poster_id' => $imageId, 'poster_at' => $atMs], 'media_id = :id', ['id' => $videoId]);
        if ($imageId) self::link($imageId, $videoId, 'poster', null, null);
        // altes, von uns erzeugtes Poster ohne weitere Verwendung aufräumen
        $oldId = (int) ($old['poster_id'] ?? 0);
        if ($oldId && $oldId !== $imageId && app()->db->fetchValue("SELECT 1 FROM video_links WHERE media_id = ? AND kind = 'poster'", [$oldId]) && !Media::usages($oldId)
            && !app()->db->fetchValue('SELECT 1 FROM video_meta WHERE poster_id = ?', [$oldId])) {
            Media::delete($oldId);
        }
        unset(self::$meta[$videoId]);
        self::$all = null;
        \Core\PageCache::clear();
    }

    public static function posterId(array $m): ?int
    {
        $row = self::allMeta()[(int) $m['id']] ?? null;
        $id = (int) ($row['poster_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public static function link(int $mediaId, int $sourceId, string $kind, ?string $preset, ?string $label): void
    {
        app()->db->query('DELETE FROM video_links WHERE media_id = ? AND kind = ?', [$mediaId, $kind]);
        app()->db->insert('video_links', ['media_id' => $mediaId, 'source_id' => $sourceId, 'kind' => $kind, 'preset' => $preset,
            'label' => $label !== null ? mb_substr($label, 0, 190) : null, 'created_at' => now()]);
    }

    /** Herkunft (Version/Ausschnitt von …) und abgeleitete Dateien eines Mediums */
    public static function relations(int $id): array
    {
        $from = app()->db->fetch("SELECT * FROM video_links WHERE media_id = ? AND kind IN ('version', 'clip') ORDER BY id DESC LIMIT 1", [$id]);
        $children = app()->db->fetchAll("SELECT * FROM video_links WHERE source_id = ? AND kind IN ('version', 'clip') ORDER BY id", [$id]);
        $pack = function (?array $l, string $key) {
            if (!$l) return null;
            $mm = Media::find((int) $l[$key]);
            return $mm ? ['id' => (int) $mm['id'], 'display' => Media::displayName($mm), 'size' => Media::humanSize((int) $mm['size']), 'bytes' => (int) $mm['size'],
                'kind' => $l['kind'], 'preset' => $l['preset'], 'label' => $l['label']] : null;
        };
        return ['source' => $pack($from ?: null, 'source_id'), 'derived' => array_values(array_filter(array_map(fn($l) => $pack($l, 'media_id'), $children)))];
    }

    /** Aufräumen, wenn eine Datei gelöscht wird (Ereignis media.deleted) */
    public static function forgetMedia(array $m): void
    {
        if (!self::ready() || !empty($m['_pool'])) return;
        $id = (int) $m['id'];
        $row = app()->db->fetch('SELECT preview FROM video_meta WHERE media_id = ?', [$id]);
        if (!empty($row['preview'])) @unlink(rtrim(Media::dir(), '/') . '/' . $row['preview']);
        app()->db->query('DELETE FROM video_meta WHERE media_id = ?', [$id]);
        app()->db->query('UPDATE video_meta SET poster_id = NULL WHERE poster_id = ?', [$id]);
        app()->db->query('DELETE FROM video_links WHERE media_id = ? OR source_id = ?', [$id, $id]);
        app()->db->query("UPDATE video_jobs SET cancel = 1 WHERE media_id = ? AND status IN ('queued', 'running')", [$id]);
        app()->db->query("UPDATE video_jobs SET status = 'canceled', message = ? WHERE media_id = ? AND status = 'queued'", [__('Datei gelöscht.'), $id]);
        unset(self::$meta[$id]);
        self::$all = null;
    }

    /** Datei ersetzt (media.replaced): Analyse und animierte Vorschau neu */
    public static function replaced(array $m): void
    {
        if (!self::ready() || !empty($m['_pool'])) return;
        $row = app()->db->fetch('SELECT preview FROM video_meta WHERE media_id = ?', [(int) $m['id']]);
        if (!empty($row['preview'])) {
            @unlink(rtrim(Media::dir(), '/') . '/' . $row['preview']);
            app()->db->update('video_meta', ['preview' => null], 'media_id = :id', ['id' => (int) $m['id']]);
        }
        unset(self::$meta[(int) $m['id']]);
        self::$all = null;
    }
}
