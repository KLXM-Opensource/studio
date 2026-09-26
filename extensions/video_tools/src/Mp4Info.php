<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

/**
 * MP4/MOV-Container ohne ffprobe lesen (reines PHP, nur Kopfdaten): Reihenfolge der Boxen (faststart = moov vor mdat),
 * Dauer, Spuren (Video/Audio), Codec (FourCC), Auflösung, Bildrate, Bitrate je Spur.
 * Dient als Rückfall, wenn ffmpeg/ffprobe fehlen, und für die billige faststart-Prüfung aller Videos („Prüfen“).
 */
final class Mp4Info
{
    private const MAX_MOOV = 64 * 1024 * 1024;
    private const CONTAINERS = ['moov', 'trak', 'mdia', 'minf', 'stbl', 'edts', 'udta'];

    /** @return array{ok: bool, faststart: ?bool, duration: ?float, size: int, bitrate: ?int, streams: list<array>, container: string, error?: string} */
    public static function read(string $file): array
    {
        $size = (int) @filesize($file);
        $out = ['ok' => false, 'faststart' => null, 'duration' => null, 'size' => $size, 'bitrate' => null, 'streams' => [], 'container' => 'mp4', 'brand' => ''];
        $fh = @fopen($file, 'rb');
        if (!$fh) return $out + ['error' => 'Datei nicht lesbar.'];
        try {
            $pos = 0;
            $moovAt = $mdatAt = null;
            $moov = null;
            $guard = 0;
            while ($pos < $size && $guard++ < 10000) {
                if (fseek($fh, $pos) !== 0) break;
                $h = fread($fh, 16);
                if ($h === false || strlen($h) < 8) break;
                [$len, $type, $hdr] = self::header($h, $size - $pos);
                if ($len < 8) break;
                if ($type === 'ftyp') $out['brand'] = trim(substr((string) fread($fh, 4) ?: '', 0, 4));
                if ($type === 'moov' && $moovAt === null) {
                    $moovAt = $pos;
                    if ($len <= self::MAX_MOOV) {
                        fseek($fh, $pos + $hdr);
                        $moov = (string) fread($fh, $len - $hdr);
                    }
                }
                if ($type === 'mdat' && $mdatAt === null) $mdatAt = $pos;
                $pos += $len;
            }
            if ($moovAt === null) return $out + ['error' => 'Kein MP4-Kopf (moov) gefunden.'];
            $out['faststart'] = $mdatAt === null || $moovAt < $mdatAt;
            if ($moov !== null) self::parseMoov($moov, $out);
            if ($out['duration']) $out['bitrate'] = (int) round($size * 8 / $out['duration']);
            $out['ok'] = true;
            return $out;
        } finally {
            fclose($fh);
        }
    }

    /** [Länge, Typ, Kopflänge] einer Box */
    private static function header(string $h, int $rest): array
    {
        $len = unpack('N', substr($h, 0, 4))[1];
        $type = substr($h, 4, 4);
        $hdr = 8;
        if ($len === 1 && strlen($h) >= 16) {
            $len = (int) unpack('J', substr($h, 8, 8))[1];
            $hdr = 16;
        } elseif ($len === 0) {
            $len = $rest;
        }
        return [$len, $type, $hdr];
    }

    /** Kind-Boxen eines Puffers: [[Typ, Inhalt], …] */
    private static function boxes(string $buf): array
    {
        $out = [];
        $pos = 0;
        $n = strlen($buf);
        while ($pos + 8 <= $n && count($out) < 2000) {
            [$len, $type, $hdr] = self::header(substr($buf, $pos, 16), $n - $pos);
            if ($len < $hdr || $pos + $len > $n) break;
            $out[] = [$type, substr($buf, $pos + $hdr, $len - $hdr)];
            $pos += $len;
        }
        return $out;
    }

    private static function find(string $buf, string $path): ?string
    {
        $parts = explode('/', $path);
        $cur = $buf;
        foreach ($parts as $p) {
            $hit = null;
            foreach (self::boxes($cur) as [$t, $c]) if ($t === $p) { $hit = $c; break; }
            if ($hit === null) return null;
            $cur = $hit;
        }
        return $cur;
    }

    private static function parseMoov(string $moov, array &$out): void
    {
        $mvhd = self::find($moov, 'mvhd');
        if ($mvhd !== null && strlen($mvhd) >= 32) {
            $v = ord($mvhd[0]);
            [$ts, $dur] = $v === 1 ? [unpack('N', substr($mvhd, 20, 4))[1], (int) unpack('J', substr($mvhd, 24, 8))[1]]
                : [unpack('N', substr($mvhd, 12, 4))[1], unpack('N', substr($mvhd, 16, 4))[1]];
            if ($ts > 0) $out['duration'] = round($dur / $ts, 3);
        }
        foreach (self::boxes($moov) as [$type, $trak]) {
            if ($type !== 'trak') continue;
            $s = self::track($trak);
            if ($s) $out['streams'][] = $s;
        }
    }

    private static function track(string $trak): ?array
    {
        $hdlr = self::find($trak, 'mdia/hdlr');
        $kind = $hdlr !== null && strlen($hdlr) >= 12 ? substr($hdlr, 8, 4) : '';
        if (!in_array($kind, ['vide', 'soun'], true)) return null;
        $s = ['type' => $kind === 'vide' ? 'video' : 'audio', 'codec' => '', 'width' => null, 'height' => null, 'fps' => null, 'bitrate' => null,
            'duration' => null, 'channels' => null, 'sample_rate' => null];
        $mdhd = self::find($trak, 'mdia/mdhd');
        $ts = 0;
        $dur = 0;
        if ($mdhd !== null && strlen($mdhd) >= 24) {
            $v = ord($mdhd[0]);
            [$ts, $dur] = $v === 1 ? [unpack('N', substr($mdhd, 20, 4))[1], (int) unpack('J', substr($mdhd, 24, 8))[1]]
                : [unpack('N', substr($mdhd, 12, 4))[1], unpack('N', substr($mdhd, 16, 4))[1]];
            if ($ts > 0) $s['duration'] = round($dur / $ts, 3);
        }
        $stsd = self::find($trak, 'mdia/minf/stbl/stsd');
        if ($stsd !== null && strlen($stsd) >= 16) {
            $entry = substr($stsd, 8);
            $s['codec'] = self::codecName(substr($entry, 4, 4));
            if ($s['type'] === 'video' && strlen($entry) >= 36) {
                $s['width'] = unpack('n', substr($entry, 32, 2))[1];
                $s['height'] = unpack('n', substr($entry, 34, 2))[1];
            } elseif ($s['type'] === 'audio' && strlen($entry) >= 36) {
                $s['channels'] = unpack('n', substr($entry, 24, 2))[1];
                $s['sample_rate'] = unpack('N', substr($entry, 32, 4))[1] >> 16;
            }
        }
        // Bildrate: Anzahl Samples / Dauer (stts)
        $stts = self::find($trak, 'mdia/minf/stbl/stts');
        if ($s['type'] === 'video' && $stts !== null && strlen($stts) >= 8 && $s['duration']) {
            $n = unpack('N', substr($stts, 4, 4))[1];
            $frames = 0;
            for ($i = 0; $i < min($n, 100000); $i++) {
                $o = 8 + $i * 8;
                if ($o + 8 > strlen($stts)) break;
                $frames += unpack('N', substr($stts, $o, 4))[1];
            }
            if ($frames > 0) $s['fps'] = round($frames / $s['duration'], 2);
        }
        // Bitrate: Summe der Sample-Größen (stsz) / Dauer
        $stsz = self::find($trak, 'mdia/minf/stbl/stsz');
        if ($stsz !== null && strlen($stsz) >= 12 && $s['duration']) {
            $fixed = unpack('N', substr($stsz, 4, 4))[1];
            $count = unpack('N', substr($stsz, 8, 4))[1];
            $bytes = 0;
            if ($fixed > 0) {
                $bytes = $fixed * $count;
            } else {
                $data = substr($stsz, 12, min($count, 2_000_000) * 4);
                foreach (unpack('N*', $data) ?: [] as $b) $bytes += $b;
            }
            if ($bytes > 0) $s['bitrate'] = (int) round($bytes * 8 / $s['duration']);
        }
        if (!$s['width'] && $s['type'] === 'video') {
            $tkhd = self::find($trak, 'tkhd');
            if ($tkhd !== null && strlen($tkhd) >= 84) {
                $o = ord($tkhd[0]) === 1 ? 88 : 76;
                if (strlen($tkhd) >= $o + 8) {
                    $s['width'] = unpack('N', substr($tkhd, $o, 4))[1] >> 16;
                    $s['height'] = unpack('N', substr($tkhd, $o + 4, 4))[1] >> 16;
                }
            }
        }
        return $s;
    }

    private static function codecName(string $fourcc): string
    {
        return match ($fourcc) {
            'avc1', 'avc3' => 'h264', 'hvc1', 'hev1' => 'hevc', 'av01' => 'av1', 'vp09' => 'vp9', 'mp4v' => 'mpeg4',
            'mp4a' => 'aac', 'Opus', 'opus' => 'opus', 'ac-3' => 'ac3', 'ec-3' => 'eac3', '.mp3' => 'mp3',
            default => preg_replace('~[^a-zA-Z0-9.-]~', '', $fourcc) ?: '?',
        };
    }
}
