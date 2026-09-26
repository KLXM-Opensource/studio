<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

/**
 * Feste Presets → Argument-Arrays für ffmpeg. Es gibt bewusst KEINE frei editierbaren Befehle: Eingaben der Nutzer
 * (Preset-Schlüssel, Zeiten) werden gegen diese Liste geprüft bzw. als Zahlen formatiert; Pfade erzeugt der Server.
 */
final class Presets
{
    /** Schlüssel → [Bezeichnung, Beschreibung, Symbol, Gruppe] (Texte werden in all() übersetzt) */
    private const DEFS = [
        'web1080' => ['Web 1080p', 'H.264/AAC, CRF 23, faststart – Standard für die Website', 'monitor', 'encode'],
        'web720' => ['Web 720p', 'H.264/AAC, CRF 23 – kleiner, für eingebettete Videos', 'monitor', 'encode'],
        'mobile540' => ['Mobil 540p', 'H.264/AAC, CRF 26 – sehr klein, für schwache Verbindungen', 'device-mobile', 'encode'],
        'archive' => ['Archiv (hohe Qualität)', 'H.264/AAC, CRF 18, Originalauflösung – zum Aufbewahren', 'archive', 'encode'],
        'webm' => ['WebM (VP9/Opus)', 'Offenes Format, 1080p – als zusätzliche Version', 'globe', 'encode'],
        'faststart' => ['Nur faststart', 'Verlustfrei umpacken: Kopf an den Anfang, startet sofort', 'lightning', 'remux'],
        'mute' => ['Ton entfernen', 'Tonspur entfernen, Bild unverändert (verlustfrei)', 'speaker-slash', 'remux'],
        'loudnorm' => ['Lautheit normalisieren', 'EBU R128 (−16 LUFS), Bild unverändert', 'speaker-high', 'audio'],
    ];
    private const LIMITS = ['web1080' => 1080, 'web720' => 720, 'mobile540' => 540, 'webm' => 1080];

    /** Presets mit Verfügbarkeit (WebM nur mit libvpx-vp9 + libopus) */
    public static function all(): array
    {
        $out = [];
        foreach (self::DEFS as $k => [$label, $desc, $icon, $group]) {
            $ok = $k !== 'webm' || (Ffmpeg::hasEncoder('libvpx-vp9') && Ffmpeg::hasEncoder('libopus'));
            $out[$k] = ['key' => $k, 'label' => __($label), 'description' => __($desc), 'icon' => $icon, 'group' => $group, 'available' => $ok,
                'lossless' => $group === 'remux', 'needs_audio' => $k === 'loudnorm' || $k === 'mute'];
        }
        return $out;
    }

    public static function exists(string $key): bool
    {
        return isset(self::DEFS[$key]);
    }

    public static function label(string $key): string
    {
        return isset(self::DEFS[$key]) ? __(self::DEFS[$key][0]) : $key;
    }

    /** Zieldateiendung + MIME eines Presets */
    public static function output(string $key): array
    {
        return $key === 'webm' ? ['webm', 'video/webm'] : ['mp4', 'video/mp4'];
    }

    private static function head(): array
    {
        return ['-hide_banner', '-nostdin', '-y', '-loglevel', 'error', '-progress', 'pipe:1', '-nostats'];
    }

    private static function threads(): array
    {
        $t = Ffmpeg::config()['threads'];
        return $t > 0 ? ['-threads', (string) $t] : [];
    }

    /** Skalierung auf höchstens $limit Pixel kurze Seite (nur verkleinern, gerade Maße), Bildrate höchstens 30 */
    private static function filters(array $info, ?int $limit): array
    {
        $v = Analyzer::video($info);
        $f = [];
        if ($v && $limit && $v['width'] && $v['height']) {
            [$w, $h] = [(int) $v['width'], (int) $v['height']];
            if (in_array(abs((int) ($v['rotation'] ?? 0)) % 180, [90], true)) [$w, $h] = [$h, $w];
            $short = min($w, $h);
            if ($short > $limit) {
                $k = $limit / $short;
                $f[] = sprintf('scale=%d:%d', (int) round($w * $k / 2) * 2, (int) round($h * $k / 2) * 2);
            }
        }
        if ($v && ($v['fps'] ?? 0) > 31) $f[] = 'fps=30';
        return $f ? ['-vf', implode(',', $f)] : [];
    }

    private static function hasAudio(array $info): bool
    {
        return (bool) Analyzer::audio($info);
    }

    private static function h264(string $crf, string $preset, string $profile = 'high'): array
    {
        return ['-c:v', 'libx264', '-preset', $preset, '-crf', $crf, '-profile:v', $profile, '-pix_fmt', 'yuv420p'];
    }

    /**
     * Argumente für ein Preset (ohne Programmpfad). $in/$out = vom Server erzeugte Pfade.
     * @throws \InvalidArgumentException bei unbekanntem/ungeeignetem Preset
     */
    public static function optimize(string $key, string $in, string $out, array $info): array
    {
        if (!self::exists($key)) throw new \InvalidArgumentException(__('Unbekanntes Preset.'));
        $audio = self::hasAudio($info);
        if (in_array($key, ['loudnorm', 'mute'], true) && !$audio) throw new \InvalidArgumentException(__('Das Video hat keine Tonspur.'));
        $a = [...self::head(), '-i', $in, ...self::threads()];
        $aac = fn(string $br) => $audio ? ['-c:a', 'aac', '-b:a', $br, '-ac', '2'] : ['-an'];
        $map = ['-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-sn', '-dn'];
        $args = match ($key) {
            'web1080', 'web720' => [...$map, ...self::filters($info, self::LIMITS[$key]), ...self::h264('23', 'medium'), ...$aac('128k'), '-movflags', '+faststart'],
            'mobile540' => [...$map, ...self::filters($info, 540), ...self::h264('26', 'medium', 'main'), ...$aac('96k'), '-movflags', '+faststart'],
            'archive' => ['-map', '0:v:0', '-map', '0:a?', '-map_metadata', '0', '-sn', '-dn', ...self::h264('18', 'slow'), ...$aac('192k'), '-movflags', '+faststart'],
            'webm' => [...$map, ...self::filters($info, 1080), '-c:v', 'libvpx-vp9', '-crf', '32', '-b:v', '0', '-row-mt', '1', '-deadline', 'good', '-cpu-used', '2',
                '-pix_fmt', 'yuv420p', ...($audio ? ['-c:a', 'libopus', '-b:a', '96k'] : ['-an'])],
            'faststart' => ['-map', '0:v', '-map', '0:a?', '-c', 'copy', '-map_metadata', '-1', '-movflags', '+faststart'],
            'mute' => ['-map', '0:v', '-c:v', 'copy', '-an', '-map_metadata', '-1', '-movflags', '+faststart'],
            'loudnorm' => ['-map', '0:v:0', '-map', '0:a:0', '-c:v', 'copy', '-af', 'loudnorm=I=-16:TP=-1.5:LRA=11', '-c:a', 'aac', '-b:a', '160k', '-ar', '48000',
                '-map_metadata', '-1', '-movflags', '+faststart'],
        };
        return [...$a, ...$args, $out];
    }

    /** Ausschnitt: verlustfrei (Stream-Copy, beginnt am Keyframe davor) oder präzise (neu kodiert) */
    public static function trim(string $in, string $out, int $fromMs, int $toMs, bool $precise, array $info): array
    {
        $ss = sprintf('%.3f', $fromMs / 1000);
        $t = sprintf('%.3f', max(0.1, ($toMs - $fromMs) / 1000));
        $a = [...self::head(), '-ss', $ss, '-i', $in, '-t', $t, ...self::threads(), '-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-sn', '-dn'];
        if ($precise) {
            return [...$a, ...self::h264('20', 'medium'), ...(self::hasAudio($info) ? ['-c:a', 'aac', '-b:a', '160k', '-ac', '2'] : ['-an']), '-movflags', '+faststart', $out];
        }
        return [...$a, '-c', 'copy', '-avoid_negative_ts', 'make_zero', '-movflags', '+faststart', $out];
    }

    /** Einzelbild als JPEG (Poster) – beste Qualität, volle Auflösung (Mediathek erzeugt die Größen) */
    public static function poster(string $in, string $out, int $atMs): array
    {
        return [...self::head(), '-ss', sprintf('%.3f', $atMs / 1000), '-i', $in, '-frames:v', '1', '-q:v', '2', '-update', '1', $out];
    }

    /** Animierte Vorschau für das Raster der Mediathek: 3 s, stumm, 360 px, 15 Bilder/s, Schleife als MP4 */
    public static function preview(string $in, string $out, int $startMs): array
    {
        return [...self::head(), '-ss', sprintf('%.3f', $startMs / 1000), '-i', $in, '-t', '3', '-an', '-sn', '-dn', '-map_metadata', '-1',
            '-vf', 'scale=360:-2,fps=15', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '30', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', $out];
    }
}
