<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

/**
 * Bewertung eines Videos für die Auslieferung im Web: Punktzahl 0–100 und konkrete Empfehlungen (mit passendem Preset).
 * „Optimiert“ = faststart und mindestens 80 Punkte.
 */
final class Analyzer
{
    /** Empfohlene Obergrenze der Video-Bitrate (bit/s) je kurzer Bildseite */
    private const TARGET = [360 => 1_000_000, 540 => 1_800_000, 720 => 3_000_000, 1080 => 6_000_000, 1440 => 10_000_000, 2160 => 20_000_000];

    public static function video(array $info): ?array
    {
        foreach ($info['streams'] ?? [] as $s) if ($s['type'] === 'video') return $s;
        return null;
    }

    public static function audio(array $info): array
    {
        return array_values(array_filter($info['streams'] ?? [], fn($s) => $s['type'] === 'audio'));
    }

    /** Kurze Bildseite (720, 1080 …) – berücksichtigt Hochformat */
    public static function shortSide(?array $v): int
    {
        if (!$v || !$v['width'] || !$v['height']) return 0;
        return (int) min($v['width'], $v['height']);
    }

    public static function label(int $short): string
    {
        return $short >= 2000 ? '4K' : ($short > 0 ? $short . 'p' : '');
    }

    /** Ziel-Bitrate für eine kurze Bildseite */
    public static function target(int $short): int
    {
        foreach (self::TARGET as $h => $b) if ($short <= $h * 1.05) return $b;
        return 20_000_000;
    }

    /**
     * @return array{score: int, optimized: bool, items: list<array{level: string, code: string, text: string, preset: ?string}>}
     */
    public static function assess(array $info, ?array $loud = null): array
    {
        $items = [];
        $score = 100;
        $add = function (string $level, string $code, string $text, int $minus = 0, ?string $preset = null) use (&$items, &$score): void {
            $items[] = ['level' => $level, 'code' => $code, 'text' => $text, 'preset' => $preset];
            $score -= $minus;
        };
        $v = self::video($info);
        $audio = self::audio($info);
        $webm = str_contains((string) ($info['container'] ?? ''), 'webm') || str_contains((string) ($info['container'] ?? ''), 'matroska');

        if (!$v) {
            $add('err', 'novideo', __('Keine Videospur gefunden.'), 60);
            return ['score' => max(0, $score), 'optimized' => false, 'items' => $items];
        }
        if (!$webm && ($info['faststart'] ?? null) === false) {
            $add('warn', 'faststart', __('Nicht für Web optimiert: kein faststart – der Browser muss erst das Dateiende laden, bevor das Video startet.'), 30, 'faststart');
        }
        $codec = strtolower((string) $v['codec']);
        if ($codec === 'hevc') {
            $add('warn', 'codec', __('Codec HEVC (H.265) spielt nicht in allen Browsern – für das Web H.264 verwenden.'), 20, 'web1080');
        } elseif (!in_array($codec, ['h264', 'vp9', 'av1'], true)) {
            $add('warn', 'codec', __('Codec {codec} ist im Web ungeeignet – H.264 (MP4) empfohlen.', ['codec' => strtoupper($codec)]), 35, 'web1080');
        }
        if (($v['pix_fmt'] ?? '') !== '' && !in_array($v['pix_fmt'], ['yuv420p', 'yuvj420p'], true) && $codec === 'h264') {
            $add('warn', 'pixfmt', __('Farbformat {fmt}: manche Geräte spielen nur yuv420p (8 Bit).', ['fmt' => $v['pix_fmt']]), 10, 'web1080');
        }
        $short = self::shortSide($v);
        if ($short > 1100) {
            $add('info', 'resolution', __('Auflösung {w} × {h} ist größer als fürs Web nötig – 1080p genügt meist.', ['w' => $v['width'], 'h' => $v['height']]), 10, 'web1080');
        }
        $vbit = $v['bitrate'] ?: (($info['bitrate'] ?? 0) - array_sum(array_map(fn($a) => (int) ($a['bitrate'] ?? 128000), $audio)));
        if ($short > 0 && $vbit > 0) {
            $target = self::target($short);
            if ($vbit > $target * 1.5) {
                $add('warn', 'bitrate', __('Bitrate hoch für {res} ({is} Mbit/s, empfohlen bis {max} Mbit/s).', [
                    'res' => self::label($short), 'is' => self::mbit($vbit), 'max' => self::mbit($target)]), $vbit > $target * 3 ? 25 : 15, $short > 760 ? 'web1080' : 'web720');
            }
        }
        if (($v['fps'] ?? 0) > 31) {
            $add('info', 'fps', __('{fps} Bilder/s – für die meisten Inhalte genügen 25–30 (halbe Dateigröße).', ['fps' => rtrim(rtrim(number_format((float) $v['fps'], 2, ',', ''), '0'), ',')]), 5);
        }
        if (!$audio) {
            $add('info', 'noaudio', __('Audio fehlt – für Hintergrundvideos gut, sonst Tonspur prüfen.'));
        } else {
            $a = $audio[0];
            if (!in_array(strtolower((string) $a['codec']), ['aac', 'opus', 'mp3', 'vorbis'], true)) {
                $add('warn', 'acodec', __('Audio-Codec {codec} spielt nicht überall – AAC empfohlen.', ['codec' => strtoupper((string) $a['codec'])]), 10, 'web1080');
            }
            if (count($audio) > 1) $add('info', 'astreams', __('{n} Tonspuren – Browser spielen nur die erste.', ['n' => count($audio)]));
            if ($loud) {
                if ($loud['lufs'] > -9) $add('warn', 'loud', __('Audio zu laut ({lufs} LUFS, Ziel −16) – Lautheit normalisieren.', ['lufs' => self::num($loud['lufs'])]), 10, 'loudnorm');
                elseif ($loud['lufs'] < -28) $add('info', 'quiet', __('Audio sehr leise ({lufs} LUFS, Ziel −16) – Lautheit normalisieren.', ['lufs' => self::num($loud['lufs'])]), 5, 'loudnorm');
                if (($loud['peak'] ?? -99) > -0.5) $add('warn', 'clip', __('Pegelspitzen bis {peak} dBFS – Übersteuerung möglich.', ['peak' => self::num((float) $loud['peak'])]), 5, 'loudnorm');
            }
        }
        if (($info['size'] ?? 0) > 150 * 1024 * 1024) {
            $add('info', 'size', __('Große Datei ({mb} MB) – auf Mobilgeräten lange Ladezeit.', ['mb' => number_format($info['size'] / 1048576, 0, ',', '.')]), 5, 'web720');
        }
        $score = max(0, min(100, $score));
        // Optimiert: faststart, mindestens 80 Punkte und keine Warnung zu Codec, Farbformat, Bitrate oder Audio-Codec
        $blocking = array_filter($items, fn($i) => $i['level'] === 'warn' && in_array($i['code'], ['faststart', 'codec', 'pixfmt', 'bitrate', 'acodec'], true));
        $optimized = $score >= 80 && !$blocking && ($webm || ($info['faststart'] ?? false) === true);
        if (!$items) $add('ok', 'ok', __('Für das Web gut vorbereitet.'));
        return ['score' => $score, 'optimized' => $optimized, 'items' => $items];
    }

    public static function mbit(int $bps): string
    {
        return number_format($bps / 1_000_000, 1, ',', '.');
    }

    private static function num(float $v): string
    {
        return str_replace('-', '−', number_format($v, 1, ',', '.'));
    }
}
