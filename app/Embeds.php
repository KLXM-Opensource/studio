<?php
declare(strict_types=1);

namespace Core;

/**
 * Externe Videos (YouTube, Vimeo) datenschutzkonform einbinden.
 *
 *  - Erkennt Anbieter und Video-ID aus einem Link (oder einer reinen YouTube-ID).
 *  - Vorschaubild und Titel holt der SERVER (Proxy) einmalig beim Anbieter und
 *    speichert sie lokal unter /public/media/embeds. Besucher laden bis zur
 *    Freigabe nichts von Google/Vimeo – keine IP-Übertragung, keine Cookies.
 *  - Der Player wird erst nach Einwilligung geladen (youtube-nocookie / dnt=1).
 */
final class Embeds
{
    public const PROVIDERS = [
        'youtube' => ['label' => 'YouTube', 'company' => 'Google Ireland Ltd.'],
        'vimeo' => ['label' => 'Vimeo', 'company' => 'Vimeo.com, Inc.'],
    ];

    /** Wurde das Skript der Zwei-Klick-Lösung in dieser Anfrage schon ausgegeben? */
    private static bool $scriptDone = false;

    /**
     * <script> der Zwei-Klick-Lösung (resources/js/embed.js → public/assets/js/embed.js) – einmal je Seite, direkt nach dem
     * ersten Video (Kern-Fragment video-embed). Nicht im Bearbeiten-Modus (dort bleibt der Player aus, wie bisher).
     * Ersetzt die früheren Kit-Skripte js/video.js, js/embed.js und den Video-Teil von js/blocks.js.
     */
    public static function script(): string
    {
        if (self::$scriptDone || (function_exists('is_editing') && is_editing())) return '';
        self::$scriptDone = true;
        return '<script src="' . e(asset('js/embed.js')) . '" defer></script>';
    }

    /** @return array{provider: string, id: string}|null */
    public static function parse(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('~^[\w\-]{11}$~', $url)) {
            return ['provider' => 'youtube', 'id' => $url];
        }
        if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([\w\-]{11})~i', $url, $m)) {
            return ['provider' => 'youtube', 'id' => $m[1]];
        }
        if (preg_match('~vimeo\.com/(?:video/|channels/[\w\-]+/|groups/[\w\-]+/videos/)?(\d{6,12})~i', $url, $m)) {
            return ['provider' => 'vimeo', 'id' => $m[1]];
        }
        return null;
    }

    /** URL des Players (erst nach Einwilligung laden) */
    public static function playerUrl(array $v): string
    {
        return $v['provider'] === 'youtube'
            ? 'https://www.youtube-nocookie.com/embed/' . $v['id'] . '?autoplay=1&rel=0&modestbranding=1&playsinline=1'
            : 'https://player.vimeo.com/video/' . $v['id'] . '?autoplay=1&dnt=1';
    }

    /** Link zum Video beim Anbieter (Alternative ohne Einbettung) */
    public static function watchUrl(array $v): string
    {
        return $v['provider'] === 'youtube' ? 'https://www.youtube.com/watch?v=' . $v['id'] : 'https://vimeo.com/' . $v['id'];
    }

    private static function dir(): string
    {
        return site()->mediaDir('embeds');
    }

    /**
     * Lokales Vorschaubild + Titel (per Server-Proxy geholt und zwischengespeichert).
     * @return array{poster: ?string, poster_small: ?string, title: string, width: int, height: int}
     */
    public static function meta(array $v): array
    {
        $key = $v['provider'] . '-' . $v['id'];
        $dir = self::dir();
        $json = "$dir/$key.json";
        if (is_file($json)) {
            $meta = json_decode((string) file_get_contents($json), true) ?: [];
            // Fehlgeschlagene Abrufe nach einem Tag erneut versuchen
            if (!empty($meta['ok']) || (time() - (int) ($meta['fetched'] ?? 0)) < 86400) {
                return self::publicMeta($meta, $key);
            }
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $meta = self::fetch($v, $key);
        @file_put_contents($json, json_encode($meta, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return self::publicMeta($meta, $key);
    }

    private static function publicMeta(array $meta, string $key): array
    {
        $base = site()->mediaUrl('embeds/' . $key);
        return [
            'poster' => !empty($meta['ok']) ? "$base-1280.webp" : null,
            'poster_small' => !empty($meta['ok']) ? "$base-640.webp" : null,
            'title' => (string) ($meta['title'] ?? ''),
            'width' => (int) ($meta['w'] ?? 1280),
            'height' => (int) ($meta['h'] ?? 720),
        ];
    }

    private static function fetch(array $v, string $key): array
    {
        $meta = ['ok' => false, 'title' => '', 'fetched' => time()];
        $imageUrls = [];
        if ($v['provider'] === 'youtube') {
            $o = json_decode((string) self::get('https://www.youtube.com/oembed?format=json&url=' . rawurlencode(self::watchUrl($v))), true);
            $meta['title'] = (string) ($o['title'] ?? '');
            $imageUrls = ["https://i.ytimg.com/vi/{$v['id']}/maxresdefault.jpg", "https://i.ytimg.com/vi/{$v['id']}/sddefault.jpg", "https://i.ytimg.com/vi/{$v['id']}/hqdefault.jpg"];
        } else {
            $o = json_decode((string) self::get('https://vimeo.com/api/oembed.json?width=1280&url=' . rawurlencode(self::watchUrl($v))), true);
            $meta['title'] = (string) ($o['title'] ?? '');
            if (!empty($o['thumbnail_url']) && preg_match('~^https://i\.vimeocdn\.com/~', $o['thumbnail_url'])) {
                $imageUrls[] = $o['thumbnail_url'];
            }
        }
        foreach ($imageUrls as $u) {
            $bin = self::get($u);
            // YouTube liefert für fehlende maxres-Bilder ein 120 px breites Platzhalterbild
            if (!$bin || !($img = @imagecreatefromstring($bin)) || imagesx($img) < 400) {
                continue;
            }
            $w = imagesx($img);
            $h = imagesy($img);
            // Schwarze Balken von 4:3-Vorschaubildern (hqdefault) auf 16:9 zuschneiden
            if (abs($w / $h - 4 / 3) < 0.02) {
                $nh = (int) round($w * 9 / 16);
                $img = imagecrop($img, ['x' => 0, 'y' => (int) (($h - $nh) / 2), 'width' => $w, 'height' => $nh]) ?: $img;
                $h = $nh;
            }
            foreach ([1280, 640] as $tw) {
                $out = $img;
                if ($w > $tw) {
                    $th = (int) round($h * $tw / $w);
                    $out = imagecreatetruecolor($tw, $th);
                    imagecopyresampled($out, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
                }
                imagewebp($out, self::dir() . "/$key-$tw.webp", 80);
            }
            $meta += ['w' => min($w, 1280), 'h' => (int) round($h * min($w, 1280) / $w)];
            $meta['ok'] = true;
            break;
        }
        return $meta;
    }

    /** Serverseitige Quellen im zentralen Proxy (nicht öffentlich abrufbar) */
    public static function registerProxy(): void
    {
        Proxy::register('youtube', ['label' => 'YouTube-Vorschaubilder (nur Server)', 'upstream' => 'https://www.youtube.com',
            'hosts' => ['i.ytimg.com'], 'public' => false, 'types' => ['application/json', 'image/'], 'max' => 3 * 1024 * 1024]);
        Proxy::register('vimeo', ['label' => 'Vimeo-Vorschaubilder (nur Server)', 'upstream' => 'https://vimeo.com',
            'hosts' => ['i.vimeocdn.com'], 'public' => false, 'types' => ['application/json', 'image/'], 'max' => 3 * 1024 * 1024]);
    }

    /** HTTP-GET über den zentralen Proxy (nur registrierte Anbieter-Hosts, kein offener Proxy). */
    private static function get(string $url): ?string
    {
        $res = Proxy::http($url);
        return $res && $res['status'] === 200 ? $res['body'] : null;
    }
}
