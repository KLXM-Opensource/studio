<?php
declare(strict_types=1);

namespace Core;

/**
 * Automatische Vorschaubilder für Videos (mit ffmpeg) und PDF-Dokumente (1. Seite, mit pdftoppm aus poppler-utils) der
 * Mediathek – ohne das jeweilige Programm wirkungslos. PDF: abschaltbar mit media.pdf_thumbs = false; Ablage, Sperren,
 * Grenzen und Ablauf wie bei Videos (variants_json['poster'], Dateien {zufall}-p-{breite}.webp).
 *
 *  - Standbild bei ~10 % der Laufzeit (mindestens 1 s, höchstens 10 s; ffprobe liefert die Dauer), ffmpeg-Filter
 *    „thumbnail“ wählt unter den folgenden 24 Bildern das typischste (überspringt Schwarzblenden/Überblendungen).
 *  - Gespeichert wie Bildgrößen: {Medienordner}/cache/{zufall}-v-{breite}.webp in den Breiten aus media.sizes (bis
 *    1600 px), vermerkt in variants_json['poster'] (base, sizes, W, H, at). Neuer Zufallsname je Erzeugung → die Dateien
 *    sind unveränderlich und dürfen lange gecacht werden. Ersetzen der Videodatei setzt variants_json zurück.
 *  - Erzeugt wird nur in der Verwaltung (Raster, Auswahl, Felder – lazy über /admin/api/media/{id}/thumb), nach dem
 *    Hochladen/Ersetzen (nach der Antwort) und per CLI `media:thumbs`. Die Website nutzt nur vorhandene Bilder.
 *  - Grenzen: Sperre je Datei, höchstens media.video_thumbs_parallel (Standard 2) gleichzeitig installationsweit
 *    (Semaphor aus Sperrdateien), Zeitlimit media.video_thumbs_timeout (Standard 10 s), nice 10, Ausgabe höchstens
 *    1600 px. Fehlschläge werden vermerkt und erst nach 6 Stunden erneut versucht.
 *  - Vorrang: gewähltes Poster (Erweiterung, z. B. video_tools) > automatisches Vorschaubild > Platzhalter.
 */
final class VideoThumbs
{
    private const FAIL_TTL = 6 * 3600;
    private const MAX_W = 1600;
    private static array $queue = [];
    private static bool $shutdown = false;

    /** Eingeschaltet (Standard) und ffmpeg/ffprobe vorhanden? */
    public static function enabled(): bool
    {
        return app()->config->get('media.video_thumbs', true) !== false && Ffmpeg::available();
    }

    public static function isVideo(?array $m): bool
    {
        return $m !== null && str_starts_with((string) ($m['mime'] ?? ''), 'video/');
    }

    public static function isPdf(?array $m): bool
    {
        return $m !== null && ($m['mime'] ?? '') === 'application/pdf';
    }

    /** Datei, für die es automatische Vorschaubilder gibt (Video oder PDF) */
    public static function handles(?array $m): bool
    {
        return self::isVideo($m) || self::isPdf($m);
    }

    /** Für diese Datei eingeschaltet und das nötige Programm vorhanden? */
    public static function enabledFor(array $m): bool
    {
        if (self::isPdf($m)) return app()->config->get('media.pdf_thumbs', true) !== false && Ffmpeg::pdfAvailable();
        return self::isVideo($m) && self::enabled();
    }

    /** Vorhandenes automatisches Vorschaubild: ['base', 'sizes' => [['w', 'f']], 'W', 'H', 'at'] oder null */
    public static function data(array $m): ?array
    {
        $v = json_decode((string) ($m['variants_json'] ?? ''), true);
        $p = is_array($v) ? ($v['poster'] ?? null) : null;
        return is_array($p) && !empty($p['base']) && !empty($p['sizes']) ? $p : null;
    }

    /** Zuletzt fehlgeschlagen (innerhalb der Wartezeit)? */
    public static function failedRecently(array $m): bool
    {
        $v = json_decode((string) ($m['variants_json'] ?? ''), true);
        $t = is_array($v) ? (int) ($v['poster']['failed'] ?? 0) : 0;
        return $t > 0 && $t > time() - self::FAIL_TTL;
    }

    /** Fehlt ein Vorschaubild, das sich jetzt erzeugen ließe? */
    public static function pending(array $m): bool
    {
        return self::handles($m) && !self::data($m) && !self::failedRecently($m) && self::enabledFor($m);
    }

    /** Dateien eines Vorschaubilds relativ zum Medienordner */
    public static function files(array $m): array
    {
        $p = self::data($m);
        return $p ? array_map(fn($s) => 'cache/' . $p['base'] . '-' . (int) $s['w'] . '.webp', $p['sizes']) : [];
    }

    // ------------------------------------------------------------------ Hintergrund

    /** Nach der Antwort erzeugen (Upload, Import, Ersetzen) – billig, falls ffmpeg fehlt */
    public static function queue(array $m): void
    {
        if (!self::pending($m)) return;
        self::$queue[] = $m;
        if (self::$shutdown) return;
        self::$shutdown = true;
        register_shutdown_function(static function (): void {
            if (PHP_SAPI !== 'cli' && function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            ignore_user_abort(true);
            @set_time_limit(120);
            foreach (array_slice(self::$queue, 0, 20) as $m) {
                try { self::generate($m, true); } catch (\Throwable $e) { error_log('[video-thumbs] ' . $e->getMessage()); }
            }
            self::$queue = [];
        });
    }

    // ------------------------------------------------------------------ Erzeugen

    /**
     * Vorschaubild erzeugen. $wait: auf Sperre/freien Platz warten (Hintergrund, CLI), sonst sofort 'busy'.
     * $force: auch vorhandene/fehlgeschlagene neu erzeugen (CLI --all).
     * @return string 'ok' | 'busy' | 'failed' | 'skip'
     */
    public static function generate(array $m, bool $wait = false, bool $force = false): string
    {
        if (!self::handles($m) || !self::enabledFor($m)) return 'skip';
        [$db, $dir, $rid] = self::target($m);
        $lock = @fopen(self::lockDir() . '/m-' . md5($dir . '|' . $rid) . '.lock', 'c');
        if (!$lock) return 'busy';
        if (!flock($lock, LOCK_EX | ($wait ? 0 : LOCK_NB))) { fclose($lock); return 'busy'; }
        try {
            // frisch lesen: evtl. hat eine parallele Anfrage das Bild gerade erzeugt
            $row = $db->fetch('SELECT file, mime, variants_json FROM media WHERE id = ?', [$rid]);
            if (!$row || $row['file'] !== $m['file'] || !self::handles($row)) return 'skip';
            if (!$force && self::data($row)) return 'ok';
            if (!$force && self::failedRecently($row)) return 'failed';
            $file = self::source($dir, (string) $row['file']);
            $slot = $file !== null ? self::slot($wait) : null;
            if ($file !== null && !$slot) return 'busy';
            try {
                $res = $file !== null ? (self::isPdf($row) ? self::renderPdf($file, $dir) : self::render($file, $dir)) : 'Datei fehlt';
            } finally {
                if ($slot) { flock($slot, LOCK_UN); fclose($slot); }
            }
            $v = json_decode((string) $row['variants_json'], true);
            if (!is_array($v)) $v = [];
            $old = self::data($row);
            if (is_array($res)) {
                $v['poster'] = $res;
            } elseif ($old) {
                return 'failed';   // neu erzeugen fehlgeschlagen: vorhandenes Bild behalten
            } else {
                $v['poster'] = ['failed' => time(), 'error' => mb_substr($res, 0, 200)];
                error_log('[video-thumbs] ' . basename((string) $row['file']) . ': ' . $res);
            }
            $db->update('media', ['variants_json' => json_encode($v)], 'id = :id', ['id' => $rid]);
            if ($old && is_array($res)) {
                foreach (self::files($row) as $rel) @unlink($dir . '/' . $rel);
            }
            Media::forget((int) $m['id']);
            if (is_array($res)) PageCache::clear();
            return is_array($res) ? 'ok' : 'failed';
        } finally {
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

    /** Eingangsdatei: muss im Medienordner liegen (kein Pfad von außen) */
    private static function source(string $dir, string $rel): ?string
    {
        $base = realpath($dir);
        $f = realpath($dir . '/' . $rel);
        return $base && $f && str_starts_with($f, $base . '/') && is_file($f) ? $f : null;
    }

    private static function lockDir(): string
    {
        $d = ROOT . '/storage/cache/video-thumbs';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    /** Freier Platz im installationsweiten Semaphor (Sperrdateien slot-0 … slot-N) */
    private static function slot(bool $wait)
    {
        $n = max(1, min(8, (int) app()->config->get('media.video_thumbs_parallel', 2)));
        $end = microtime(true) + ($wait ? 90 : 0);
        do {
            for ($i = 0; $i < $n; $i++) {
                $h = @fopen(self::lockDir() . "/slot-$i.lock", 'c');
                if ($h && flock($h, LOCK_EX | LOCK_NB)) return $h;
                if ($h) fclose($h);
            }
            if (!$wait) return null;
            usleep(250000);
        } while (microtime(true) < $end);
        return null;
    }

    /** Standbild herausziehen und in Größen speichern → Angaben für variants_json['poster'] oder Fehlertext */
    private static function render(string $file, string $dir): array|string
    {
        $timeout = max(3, min(60, (int) app()->config->get('media.video_thumbs_timeout', 10)));
        [$c, $out] = Ffmpeg::exec([Ffmpeg::bin('ffprobe'), '-v', 'error', '-select_streams', 'v:0',
            '-show_entries', 'format=duration:stream=codec_type', '-of', 'json', $file], min(8, $timeout));
        $j = $c === 0 ? json_decode($out, true) : null;
        if (!is_array($j) || empty($j['streams'])) return 'Keine lesbare Videospur';
        $dur = (float) ($j['format']['duration'] ?? 0);
        $at = $dur > 0 ? max(1.0, min(10.0, $dur * 0.1)) : 0.0;
        if ($dur > 0 && $at >= $dur) $at = $dur / 2;

        $cache = $dir . '/cache';
        if (!is_dir($cache)) @mkdir($cache, 0775, true);
        // Zeitpunkte: ~10 % der Laufzeit; ist das Bild fast schwarz (Schwarzblende am Anfang), bis zu zwei spätere Versuche
        $times = [$at];
        if ($dur > 3) {
            $times[] = min($dur - 0.5, max($at + 2.0, $dur * 0.3));
            $times[] = $dur * 0.6;
        }
        $deadline = microtime(true) + $timeout;
        $img = null;
        $best = -1.0;
        $used = $at;
        foreach (array_values(array_unique(array_map(fn($t) => round($t, 3), $times))) as $i => $t) {
            $left = (int) floor($deadline - microtime(true));
            if ($i > 0 && $left < 2) break;
            $r = self::frame($file, $cache, $t, $i === 0 ? $timeout : $left);
            if (is_string($r)) {
                if ($img === null && $i === 0) return $r;
                break;
            }
            $luma = self::luma($r);
            if ($luma > $best) { $img = $r; $best = $luma; $used = $t; }
            if ($luma >= 24) break;   // hell genug
        }
        if (!$img) return 'Standbild nicht lesbar';
        return self::store($img, $cache, '-v', $used);
    }

    /** PDF: 1. Seite mit pdftoppm als Bild (höchstens MAX_W an der längeren Seite) → Angaben für variants_json['poster'] */
    private static function renderPdf(string $file, string $dir): array|string
    {
        $timeout = max(3, min(60, (int) app()->config->get('media.video_thumbs_timeout', 10)));
        $cache = $dir . '/cache';
        if (!is_dir($cache)) @mkdir($cache, 0775, true);
        $tmp = $cache . '/.pthumb-' . bin2hex(random_bytes(6));
        try {
            [$c, , $err] = Ffmpeg::exec([Ffmpeg::bin('pdftoppm'), '-f', '1', '-l', '1', '-singlefile', '-jpeg', '-jpegopt', 'quality=88',
                '-scale-to', (string) self::MAX_W, $file, $tmp], $timeout, 10);
            $out = $tmp . '.jpg';
            if ($c !== 0 || !is_file($out) || filesize($out) < 100 || filesize($out) > 20 * 1024 * 1024) {
                return 'pdftoppm: ' . (trim((string) strtok(trim((string) $err), "\n")) ?: 'exit ' . $c);
            }
            $info = @getimagesize($out);
            $img = $info && $info[0] * $info[1] <= 16_000_000 ? @imagecreatefromjpeg($out) : false;
            if (!$img) return 'Seite nicht lesbar';
            return self::store($img, $cache, '-p', 0.0);
        } finally {
            @unlink($tmp . '.jpg');
        }
    }

    /** Bild in den Breiten aus media.sizes (bis MAX_W) als WebP speichern → Angaben für variants_json['poster'] */
    private static function store(\GdImage $img, string $cache, string $suffix, float $at): array|string
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $sizes = array_map('intval', (array) app()->config->get('media.sizes', [480, 800, 1200, 1600, 2400]));
        $targets = array_filter($sizes, fn($s) => $s > 0 && $s < $w && $s <= self::MAX_W);
        $targets[] = min($w, self::MAX_W);
        $targets = array_values(array_unique($targets));
        sort($targets);
        $base = bin2hex(random_bytes(8)) . $suffix;
        $q = (int) app()->config->get('media.quality', 80);
        $list = [];
        foreach ($targets as $tw) {
            $th = max(1, (int) round($h * $tw / $w));
            $r = $img;
            if ($tw !== $w) {
                $r = imagecreatetruecolor($tw, $th);
                imagecopyresampled($r, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
            }
            if (!@imagewebp($r, "$cache/$base-$tw.webp", $q)) {
                foreach ($list as $s) @unlink("$cache/$base-{$s['w']}.webp");
                return 'WebP konnte nicht geschrieben werden';
            }
            $list[] = ['w' => $tw, 'f' => ['webp']];
        }
        return ['base' => $base, 'sizes' => $list, 'W' => $w, 'H' => $h, 'at' => (int) round($at * 1000), 'created' => time()];
    }

    /** Ein Standbild ab $at (Filter „thumbnail“ wählt unter 24 Bildern das typischste), höchstens MAX_W breit */
    private static function frame(string $file, string $cache, float $at, int $timeout): \GdImage|string
    {
        $tmp = $cache . '/.vthumb-' . bin2hex(random_bytes(6)) . '.jpg';
        try {
            [$c, , $err] = Ffmpeg::exec([Ffmpeg::bin('ffmpeg'), '-nostdin', '-hide_banner', '-loglevel', 'error', '-y', '-threads', '2',
                '-ss', sprintf('%.3f', $at), '-i', $file, '-map', '0:v:0', '-an', '-sn', '-dn',
                '-vf', 'thumbnail=24,scale=w=min(' . self::MAX_W . '\,iw):h=-2', '-frames:v', '1', '-f', 'image2', '-c:v', 'mjpeg', '-q:v', '3', $tmp],
                max(2, $timeout), 10);
            if ($c !== 0 || !is_file($tmp) || filesize($tmp) < 100 || filesize($tmp) > 20 * 1024 * 1024) {
                return 'ffmpeg: ' . (trim((string) strtok(trim((string) $err), "\n")) ?: 'exit ' . $c);
            }
            $info = @getimagesize($tmp);
            $img = $info && $info[0] * $info[1] <= 16_000_000 ? @imagecreatefromjpeg($tmp) : false;
            return $img ?: 'Standbild nicht lesbar';
        } finally {
            @unlink($tmp);
        }
    }

    /** Mittlere Helligkeit (0–255) aus 16 × 9 Stichproben */
    private static function luma(\GdImage $img): float
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $sum = 0.0;
        $n = 0;
        for ($y = 0; $y < 9; $y++) {
            for ($x = 0; $x < 16; $x++) {
                $c = imagecolorat($img, (int) (($x + 0.5) * $w / 16), (int) (($y + 0.5) * $h / 9));
                $sum += 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
                $n++;
            }
        }
        return $n ? $sum / $n : 0.0;
    }

    // ------------------------------------------------------------------ CLI

    /** media:thumbs [--all] [--missing] [--pool=key] – Vorschaubilder nachträglich erzeugen */
    public static function console(array $args): int
    {
        Ffmpeg::refresh();
        $video = self::enabled();
        $pdf = app()->config->get('media.pdf_thumbs', true) !== false && Ffmpeg::pdfAvailable();
        if (!$video) echo "Videos: ffmpeg/ffprobe nicht gefunden, proc_open gesperrt oder ausgeschaltet (media.video_thumbs) – übersprungen.\n";
        if (!$pdf) echo "PDF: pdftoppm (poppler-utils) nicht gefunden, proc_open gesperrt oder ausgeschaltet (media.pdf_thumbs) – übersprungen.\n";
        if (!$video && !$pdf) return 0;
        $force = in_array('--all', $args, true);
        $pool = null;
        foreach ($args as $a) if (str_starts_with($a, '--pool=')) $pool = substr($a, 7);
        if ($pool !== null) {
            if (!isset(MediaPools::all()[$pool])) { fwrite(STDERR, "Unbekannter Pool: $pool\n"); return 1; }
            Media::usePool($pool);
        }
        $rows = array_merge($video ? Media::all(['kind' => 'video']) : [], $pdf ? Media::all(['kind' => 'pdf']) : []);
        $n = ['ok' => 0, 'failed' => 0, 'skip' => 0, 'busy' => 0];
        foreach ($rows as $m) {
            if (!$force && self::data($m)) { $n['skip']++; continue; }
            // --missing (Standard): auch kürzlich fehlgeschlagene erneut versuchen
            $r = self::generate($m, true, true);
            $n[$r] = ($n[$r] ?? 0) + 1;
            echo sprintf("  #%-5d %-8s %s\n", (int) $m['id'], $r, (string) $m['original_name']);
        }
        echo sprintf("Vorschaubilder (%s): %d erzeugt, %d fehlgeschlagen, %d übersprungen%s.\n", $pool ?? site()->key,
            $n['ok'], $n['failed'], $n['skip'], $n['busy'] ? ", {$n['busy']} belegt" : '');
        return 0;   // Fehlschläge (z. B. Datei ohne Videospur) sind vermerkt, kein Abbruch für Cron
    }
}
