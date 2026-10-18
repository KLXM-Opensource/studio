<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Web-Fassung von Videos (mit ffmpeg): große Videos der Mediathek bekommen automatisch eine kleinere Fassung für die Website –
 * H.264, höchstens 1080 px hoch, CRF 26, faststart (startet sofort beim Laden). Sie liegt wie Bildgrößen und Vorschaubilder
 * im Zwischenspeicher des Medienordners (cache/{zufall}-web.mp4, vermerkt in variants_json['web']) und erscheint nicht als
 * eigene Datei in der Mediathek. Die Website spielt sie statt des Originals aus (Media::url ohne Breite, außerhalb der
 * Verwaltung); Original, Herunterladen und Bearbeiten in der Verwaltung bleiben beim Original.
 *
 *  - Wann: nach Hochladen/Ersetzen nach der Antwort (queue), per Cron („Web-Fassung für Videos“, alle 10 min, media:web --all)
 *    und beim ersten Aufruf auf der Website (fehlt sie, wird der Cron-Lauf sofort eingereiht).
 *  - Nur wenn es sich lohnt: Original größer als media.video_web_min_mb (Standard 2 MB) oder höher als 1080 px; ist die
 *    neue Fassung nicht mindestens 10 % kleiner, bleibt es beim Original (vermerkt als 'same').
 *  - Ersetzen setzt variants_json zurück, Löschen räumt die Datei mit ab (Media::files). Abschalten: media.video_web = false.
 *  - Grenzen wie bei Vorschaubildern: Sperre je Datei, ein Lauf zugleich, nice 10, Zeitlimit media.video_web_timeout (600 s).
 */
final class VideoWeb
{
    private const FAIL_TTL = 6 * 3600;
    private const MAX_H = 1080;
    private static array $queue = [];
    private static bool $shutdown = false;
    private static bool $enqueued = false;

    public static function enabled(): bool
    {
        return app()->config->get('media.video_web', true) !== false && Ffmpeg::available();
    }

    /** ['file', 'w', 'h', 'size', 'at'] | ['same' => ts] | ['failed' => ts] | null */
    public static function data(array $m): ?array
    {
        $v = json_decode((string) ($m['variants_json'] ?? ''), true);
        $w = is_array($v) ? ($v['web'] ?? null) : null;
        return is_array($w) ? $w : null;
    }

    /** Lohnt eine Web-Fassung? (Video, groß oder sehr hoch) */
    public static function worth(array $m): bool
    {
        if (!str_starts_with((string) ($m['mime'] ?? ''), 'video/')) return false;
        $min = max(0.2, (float) app()->config->get('media.video_web_min_mb', 2)) * 1048576;
        return (int) ($m['size'] ?? 0) > $min || (int) ($m['height'] ?? 0) > self::MAX_H;
    }

    public static function pending(array $m): bool
    {
        if (!self::worth($m) || !self::enabled()) return false;
        $d = self::data($m);
        if ($d === null) return true;
        return isset($d['failed']) && (int) $d['failed'] < time() - self::FAIL_TTL;
    }

    /** Dateien der Web-Fassung (relativ zum Medienordner) – für Löschen/Verschieben */
    public static function files(array $m): array
    {
        $d = self::data($m);
        return $d && !empty($d['file']) ? [(string) $d['file']] : [];
    }

    /** Relativer Pfad der fertigen Web-Fassung oder null (dann das Original); fehlt sie, Cron-Lauf einreihen (einmal je Anfrage) */
    public static function fileFor(array $m, string $dir): ?string
    {
        $d = self::data($m);
        if ($d && !empty($d['file']) && is_file($dir . '/' . $d['file'])) return (string) $d['file'];
        if (!self::$enqueued && self::pending($m)) {
            self::$enqueued = true;
            try { Cron::enqueue('media.video_web', 'website'); } catch (\Throwable) {}
        }
        return null;
    }

    /** Nach der Antwort erzeugen (Hochladen, Ersetzen) */
    public static function queue(?array $m): void
    {
        if (!$m || !self::pending($m)) return;
        self::$queue[] = $m;
        if (self::$shutdown) return;
        self::$shutdown = true;
        register_shutdown_function(static function (): void {
            if (PHP_SAPI !== 'cli' && function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            ignore_user_abort(true);
            @set_time_limit((int) app()->config->get('media.video_web_timeout', 600) + 60);
            foreach (array_slice(self::$queue, 0, 3) as $m) {
                try { self::generate($m, true); } catch (\Throwable $e) { error_log('[video-web] ' . $e->getMessage()); }
            }
            self::$queue = [];
        });
    }

    /** @return string 'ok' | 'same' | 'busy' | 'failed' | 'skip' */
    public static function generate(array $m, bool $wait = false, bool $force = false): string
    {
        if (!self::worth($m) || !self::enabled()) return 'skip';
        [$db, $dir, $rid] = self::target($m);
        $lock = @fopen(self::lockDir() . '/w-' . md5($dir . '|' . $rid) . '.lock', 'c');
        if (!$lock) return 'busy';
        if (!flock($lock, LOCK_EX | ($wait ? 0 : LOCK_NB))) { fclose($lock); return 'busy'; }
        $slot = null;
        try {
            $row = $db->fetch('SELECT file, mime, size, height, variants_json FROM media WHERE id = ?', [$rid]);
            if (!$row || $row['file'] !== $m['file'] || !self::worth($row)) return 'skip';
            $old = self::data($row);
            if (!$force && $old && (!empty($old['file']) || isset($old['same']))) return !empty($old['file']) ? 'ok' : 'same';
            $base = realpath($dir);
            $src = realpath($dir . '/' . $row['file']);
            if (!$base || !$src || !str_starts_with($src, $base . '/') || !is_file($src)) return 'skip';
            $slot = @fopen(self::lockDir() . '/slot.lock', 'c');
            if (!$slot || !flock($slot, LOCK_EX | ($wait ? 0 : LOCK_NB))) return 'busy';
            $rel = 'cache/' . bin2hex(random_bytes(8)) . '-web.mp4';
            $out = $dir . '/' . $rel;
            if (!is_dir(dirname($out))) @mkdir(dirname($out), 0775, true);
            $tmp = $out . '.tmp.mp4';
            [$c, , $err] = Ffmpeg::exec([Ffmpeg::bin('ffmpeg'), '-y', '-v', 'error', '-i', $src, '-map', '0:v:0', '-map', '0:a:0?', '-sn', '-dn',
                '-vf', 'scale=w=-2:h=min(' . self::MAX_H . '\,ih),format=yuv420p', '-c:v', 'libx264', '-preset', 'medium', '-crf', '26', '-profile:v', 'high',
                '-c:a', 'aac', '-b:a', '128k', '-ac', '2', '-movflags', '+faststart', '-map_metadata', '-1', $tmp],
                max(30, (int) app()->config->get('media.video_web_timeout', 600)), 10);
            $v = json_decode((string) $row['variants_json'], true);
            if (!is_array($v)) $v = [];
            $res = 'failed';
            if ($c !== 0 || !is_file($tmp) || filesize($tmp) < 1000) {
                @unlink($tmp);
                $v['web'] = ['failed' => time(), 'error' => mb_substr(trim((string) strtok(trim((string) $err), "\n")) ?: 'exit ' . $c, 0, 200)];
                error_log('[video-web] ' . basename((string) $row['file']) . ': ' . $v['web']['error']);
            } elseif (filesize($tmp) > 0.9 * (int) filesize($src)) {
                @unlink($tmp);
                $v['web'] = ['same' => time()];   // lohnt nicht – Original ausspielen
                $res = 'same';
            } else {
                rename($tmp, $out);
                $dim = @getimagesize($out) ?: [0, 0];
                $v['web'] = ['file' => $rel, 'size' => (int) filesize($out), 'at' => time(), 'w' => (int) $dim[0], 'h' => (int) $dim[1]];
                $res = 'ok';
            }
            $db->update('media', ['variants_json' => json_encode($v)], 'id = :id', ['id' => $rid]);
            if ($res === 'ok' && $old && !empty($old['file']) && $old['file'] !== $rel) @unlink($dir . '/' . $old['file']);
            Media::forget((int) $m['id']);
            if ($res === 'ok') PageCache::clear();
            return $res;
        } finally {
            if ($slot) { @flock($slot, LOCK_UN); fclose($slot); }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** [Datenbank, Medienordner, ID in dieser Datenbank] – Pool-Dateien liegen im Pool */
    private static function target(array $m): array
    {
        if (!empty($m['_pool'])) {
            return [MediaPools::db((string) $m['_pool']), MediaPools::mediaDir((string) $m['_pool']), (int) ($m['_pool_id'] ?? $m['id'])];
        }
        return [app()->db, site()->mediaDir(), (int) $m['id']];
    }

    private static function lockDir(): string
    {
        $d = ROOT . '/storage/cache/video-web';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    /**
     * media:web [--force] [--pool=key] [--limit=N] – fehlende Web-Fassungen erzeugen (Website und ihre Pools); --force: neu erzeugen.
     * --all: für jede Website (Cron).
     * Zeitbudget je Lauf (media.video_web_budget, 480 s), damit der Cron-Lauf nicht überläuft.
     */
    public static function console(array $args): int
    {
        Ffmpeg::refresh();
        if (!self::enabled()) { echo "Web-Fassung: ffmpeg nicht gefunden oder ausgeschaltet (media.video_web) – übersprungen.\n"; return 0; }
        $force = in_array('--force', $args, true);
        $limit = max(1, (int) (Cli::opt($args, 'limit') ?? 20));
        $only = Cli::opt($args, 'pool');
        $sources = $only !== null ? [$only] : [null, ...array_keys(MediaPools::forSite())];
        $end = time() + max(60, (int) app()->config->get('media.video_web_budget', 480));
        $n = ['ok' => 0, 'same' => 0, 'failed' => 0, 'skip' => 0, 'busy' => 0];
        foreach ($sources as $pool) {
            $db = $pool === null ? app()->db : MediaPools::db($pool);
            $rows = $db->fetchAll("SELECT id, file, mime, size, height, variants_json, original_name FROM media WHERE mime LIKE 'video/%'"
                . ($pool === null ? " AND (pool_ref IS NULL OR pool_ref = '')" : '') . ' ORDER BY id DESC');
            foreach ($rows as $r) {
                if ($limit <= 0 || time() > $end) break 2;
                $m = $r + ($pool !== null ? ['_pool' => $pool, '_pool_id' => (int) $r['id']] : []);
                if (!$force && !self::pending($m)) continue;
                $res = self::generate($m, true, $force);
                $n[$res] = ($n[$res] ?? 0) + 1;
                $limit--;
                echo sprintf("  %s#%-5d %-6s %s\n", $pool !== null ? $pool . ':' : '', (int) $r['id'], $res, (string) $r['original_name']);
            }
        }
        echo sprintf("Web-Fassungen (%s): %d erzeugt, %d unverändert (lohnt nicht), %d fehlgeschlagen%s.\n", site()->key, $n['ok'], $n['same'], $n['failed'], $n['busy'] ? ", {$n['busy']} belegt" : '');
        return 0;
    }
}
