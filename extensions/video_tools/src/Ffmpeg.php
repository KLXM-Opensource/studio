<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

use Core\Ffmpeg as CoreFfmpeg;

/**
 * ffmpeg/ffprobe finden und aufrufen – ausschließlich per proc_open mit Argument-Array (keine Shell, keine Eingaben der
 * Nutzer in Befehlen). Erkennung und Aufruf liefert der Kern (Core\Ffmpeg, seit Core 1.0.0 mit Video-Vorschaubildern);
 * Pfade: 'video_tools' => ['ffmpeg' => …, 'ffprobe' => …] geht vor, sonst 'ffmpeg_path'/'ffprobe_path' des Kerns, sonst
 * Suche in storage/video/bin, /opt/homebrew/bin, /usr/local/bin, /usr/bin, /bin und PATH.
 */
final class Ffmpeg
{
    private static array $cache = [];

    public static function config(): array
    {
        $c = (array) app()->config->get('video_tools', []);
        return [
            'ffmpeg' => trim((string) ($c['ffmpeg'] ?? '')),
            'ffprobe' => trim((string) ($c['ffprobe'] ?? '')),
            'max_jobs' => max(1, min(8, (int) ($c['max_jobs'] ?? 1))),
            'nice' => max(0, min(19, (int) ($c['nice'] ?? 10))),
            'timeout' => max(60, min(6 * 3600, (int) ($c['timeout'] ?? 3600))),
            'max_input_mb' => max(1, (int) ($c['max_input_mb'] ?? 2048)),
            'threads' => max(0, min(64, (int) ($c['threads'] ?? 0))),
            'min_free_mb' => max(0, (int) ($c['min_free_mb'] ?? 500)),
            // Animierte Vorschau (Hover-Schleife) – Standard AUS: das Raster zeigt ein Standbild (Core\VideoThumbs)
            'preview' => (bool) ($c['preview'] ?? false),
        ];
    }

    /** proc_open erlaubt (nicht in disable_functions)? – Core\Ffmpeg */
    public static function canExec(): bool
    {
        return CoreFfmpeg::canExec();
    }

    /**
     * Wirksamer Pfad zu ffmpeg bzw. ffprobe ('' = nicht gefunden): 'video_tools' => ['ffmpeg' => …] geht vor, sonst die
     * Erkennung des Kerns (Core\Ffmpeg: 'ffmpeg_path'/'ffprobe_path', storage/video/bin, übliche Ordner, PATH).
     */
    public static function bin(string $name): string
    {
        return CoreFfmpeg::bin($name, self::config()[$name] ?? '');
    }

    /** Konfigurierter, aber ungültiger Pfad? (für die Statusanzeige) */
    public static function misconfigured(string $name): bool
    {
        // nur der eigene Pfad der Erweiterung zählt als Fehler; ein falscher 'ffmpeg_path' des Kerns ist dort eine Warnung
        return (self::config()[$name] ?? '') !== '' && CoreFfmpeg::misconfigured($name, self::config()[$name]);
    }

    /** Wirksame Pfad-Einstellung (eigene oder die des Kerns) – für Status und Hinweise */
    public static function configuredPath(string $name): string
    {
        return (self::config()[$name] ?? '') !== '' ? self::config()[$name] : CoreFfmpeg::configured($name);
    }

    /** Alles da, um Videos zu bearbeiten? */
    public static function available(): bool
    {
        return self::canExec() && self::bin('ffmpeg') !== '' && self::bin('ffprobe') !== '';
    }

    /** Programm mit Argument-Array ausführen (kurz, synchron): [exit, stdout, stderr] – Core\Ffmpeg::exec */
    public static function exec(array $argv, int $timeout = 30): array
    {
        return CoreFfmpeg::exec($argv, $timeout);
    }

    /** Schlanke Umgebung für Unterprozesse (kein Erbe von Geheimnissen aus $_ENV) */
    public static function env(): array
    {
        return CoreFfmpeg::env();
    }

    /** Versionszeile („ffmpeg version 7.1.1 …“) */
    public static function version(string $name = 'ffmpeg'): string
    {
        return CoreFfmpeg::version($name, self::config()[$name] ?? '');   // bei der Erkennung ermittelt, zwischengespeichert
    }

    /** Vorhandene Encoder (für optionale Presets wie WebM), zwischengespeichert je Programmversion */
    public static function encoders(): array
    {
        if (!self::available()) return [];
        if (isset(self::$cache['enc'])) return self::$cache['enc'];
        $bin = self::bin('ffmpeg');
        $file = ROOT . '/storage/video/encoders-' . substr(md5($bin . '|' . self::version()), 0, 10) . '.json';
        $list = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($list)) {
            [$c, $o] = self::exec([$bin, '-hide_banner', '-encoders'], 15);
            $list = [];
            if ($c === 0 && preg_match_all('~^\s*[VAS][F.][S.][X.][B.][D.]\s+(\S+)~m', $o, $m)) $list = $m[1];
            @mkdir(dirname($file), 0770, true);
            @file_put_contents($file, json_encode($list));
        }
        return self::$cache['enc'] = $list;
    }

    public static function hasEncoder(string $name): bool
    {
        return in_array($name, self::encoders(), true);
    }

    /**
     * Datei mit ffprobe untersuchen → normalisierte Angaben (wie Mp4Info::read, mit mehr Details).
     * Liefert null, wenn ffprobe fehlt oder die Datei kein lesbares Video ist.
     */
    public static function probe(string $file): ?array
    {
        $bin = self::bin('ffprobe');
        if ($bin === '' || !self::canExec() || !is_file($file)) return null;
        [$c, $o] = self::exec([$bin, '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $file], 30);
        $j = $c === 0 ? json_decode($o, true) : null;
        if (!is_array($j) || empty($j['format'])) return null;
        $f = $j['format'];
        $out = [
            'ok' => true, 'size' => (int) ($f['size'] ?? filesize($file)), 'duration' => isset($f['duration']) ? round((float) $f['duration'], 3) : null,
            'bitrate' => isset($f['bit_rate']) ? (int) $f['bit_rate'] : null, 'container' => (string) ($f['format_name'] ?? ''),
            'streams' => [], 'faststart' => null, 'source' => 'ffprobe',
        ];
        foreach ((array) ($j['streams'] ?? []) as $s) {
            $type = (string) ($s['codec_type'] ?? '');
            if (!in_array($type, ['video', 'audio'], true)) continue;
            if ($type === 'video' && !empty($s['disposition']['attached_pic'])) continue;   // Coverbild
            $fps = null;
            if (!empty($s['avg_frame_rate']) && preg_match('~^(\d+)/(\d+)$~', (string) $s['avg_frame_rate'], $m) && (int) $m[2] > 0) $fps = round((int) $m[1] / (int) $m[2], 2);
            $rot = 0;
            foreach ((array) ($s['side_data_list'] ?? []) as $sd) if (isset($sd['rotation'])) $rot = (int) $sd['rotation'];
            if (isset($s['tags']['rotate'])) $rot = (int) $s['tags']['rotate'];
            $out['streams'][] = [
                'type' => $type, 'codec' => (string) ($s['codec_name'] ?? '?'), 'profile' => (string) ($s['profile'] ?? ''),
                'width' => isset($s['width']) ? (int) $s['width'] : null, 'height' => isset($s['height']) ? (int) $s['height'] : null,
                'pix_fmt' => (string) ($s['pix_fmt'] ?? ''), 'fps' => $fps, 'rotation' => $rot,
                'bitrate' => isset($s['bit_rate']) ? (int) $s['bit_rate'] : null, 'duration' => isset($s['duration']) ? round((float) $s['duration'], 3) : null,
                'channels' => isset($s['channels']) ? (int) $s['channels'] : null, 'sample_rate' => isset($s['sample_rate']) ? (int) $s['sample_rate'] : null,
                'lang' => (string) ($s['tags']['language'] ?? ''), 'start' => isset($s['start_time']) ? round((float) $s['start_time'], 3) : null,
            ];
        }
        return $out;
    }

    /** Lautheit (EBU R128, integriert in LUFS, True Peak dBTP) – ffmpeg ebur128, ein Durchlauf ohne Ausgabe */
    public static function loudness(string $file, int $timeout = 300): ?array
    {
        $bin = self::bin('ffmpeg');
        if ($bin === '' || !self::canExec()) return null;
        [$c, , $err] = self::exec([$bin, '-hide_banner', '-nostats', '-i', $file, '-map', '0:a:0', '-af', 'ebur128=peak=true', '-f', 'null', '-'], $timeout);
        if ($c !== 0) return null;
        if (!preg_match('~Integrated loudness:\s*I:\s*(-?[\d.]+) LUFS~s', $err, $i)) return null;
        preg_match('~True peak:\s*Peak:\s*(-?[\d.]+|-inf) dBFS~s', $err, $p);
        return ['lufs' => (float) $i[1], 'peak' => isset($p[1]) && $p[1] !== '-inf' ? (float) $p[1] : null];
    }

    /** Zeitpunkt (ms) des letzten Keyframes an oder vor $ms – für den Hinweis beim verlustfreien Schnitt */
    public static function keyframeBefore(string $file, int $ms): ?int
    {
        $bin = self::bin('ffprobe');
        if ($bin === '' || !self::canExec()) return null;
        $from = max(0, $ms / 1000 - 12);
        [$c, $o] = self::exec([$bin, '-v', 'error', '-select_streams', 'v:0', '-skip_frame', 'nokey', '-show_entries', 'frame=pts_time,best_effort_timestamp_time',
            '-read_intervals', sprintf('%.3f%%%.3f', $from, $ms / 1000 + 0.05), '-of', 'json', $file], 20);
        $j = $c === 0 ? json_decode($o, true) : null;
        $best = null;
        foreach ((array) ($j['frames'] ?? []) as $f) {
            $t = $f['pts_time'] ?? $f['best_effort_timestamp_time'] ?? null;
            if ($t === null) continue;
            $t = (int) round((float) $t * 1000);
            if ($t <= $ms + 1 && ($best === null || $t > $best)) $best = $t;
        }
        return $best;
    }
}
