<?php
declare(strict_types=1);

namespace Core;

use Core\Http\HttpException;
use Core\Http\Response;

/**
 * Zentraler Proxy für externe Quellen (Karten-Kacheln, Schriften, Vorschaubilder, APIs …).
 *
 * Besucher laden externe Inhalte ausschließlich über /proxy/{quelle}/{pfad} von der eigenen Domain.
 * Der Anbieter sieht nur den Server – keine IP-Adresse, keine Cookies, keinen Referer der Besucher.
 * Dadurch entfällt die Zwei-Klick-Einwilligung für diese Inhalte.
 *
 * Quellen werden registriert (kein offener Proxy):
 *   - Kern:   Core\Maps (OpenFreeMap), Core\Embeds (YouTube/Vimeo-Vorschaubilder, nur serverseitig)
 *   - Theme:  theme.php → 'proxy' => ['name' => [...Definition...]]
 *   - Code:   Proxy::register('name', [...]) z. B. in functions.php (später auch Plugins)
 *
 * Definition:
 *   upstream  string    https-Basisadresse, z. B. https://tiles.openfreemap.org
 *   label     string    Anzeigename (Grundeinstellungen → Externe Quellen)
 *   public    bool      über /proxy/… abrufbar (false = nur serverseitig via Proxy::http)
 *   allow     string    Regex für erlaubte Pfade (ohne führenden /), Standard: alles
 *   types     string[]  erlaubte Content-Types (Präfixe), Standard: Bilder, JSON, Protobuf, Schriften, CSS
 *   ttl       int|callable(string $pfad): int   Cache-Dauer in Sekunden (Standard 1 Tag)
 *   rewrite   bool      in Text-Antworten (JSON/CSS) Upstream-Adressen auf den Proxy umschreiben
 *   guard     callable(string $pfad): bool      zusätzliche Prüfung (z. B. Kartengebiet)
 *   hosts     string[]  weitere Hosts für Proxy::http (serverseitig), z. B. Bild-CDN des Anbieters
 *   headers   string[]  zusätzliche Request-Header an den Anbieter
 *   max       int       maximale Antwortgröße in Bytes (Standard 5 MB)
 */
final class Proxy
{
    private const DEFAULT_TYPES = ['image/', 'application/json', 'application/x-protobuf', 'application/vnd.mapbox-vector-tile',
        'application/octet-stream', 'font/', 'application/font', 'text/css', 'application/geo+json'];
    private const COMPRESS = ['application/json', 'application/x-protobuf', 'application/vnd.mapbox-vector-tile', 'application/octet-stream', 'text/', 'application/geo+json'];

    private static array $sources = [];
    private static bool $booted = false;

    public static function register(string $key, array $def): void
    {
        if (!preg_match('~^[a-z][a-z0-9_-]{1,30}$~', $key) || !preg_match('~^https://[a-z0-9.-]+(:\d+)?(/.*)?$~i', (string) ($def['upstream'] ?? ''))) {
            throw new \InvalidArgumentException("Proxy-Quelle „{$key}“: Name oder upstream (https://…) ungültig.");
        }
        $def['upstream'] = rtrim($def['upstream'], '/');
        self::$sources[$key] = $def + ['label' => $key, 'public' => true, 'rewrite' => false, 'ttl' => 86400, 'max' => 5 * 1024 * 1024];
    }

    /** Alle registrierten Quellen (Kern + Theme) */
    public static function sources(): array
    {
        if (!self::$booted) {
            self::$booted = true;
            Maps::registerProxy();
            Embeds::registerProxy();
            Fonts::registerProxy();   // Schriften-Katalog und -Dateien (nur serverseitig, Grundeinstellungen → Schriften)
            foreach ((array) (app()->theme->def['proxy'] ?? []) as $key => $def) {
                self::register((string) $key, (array) $def);
            }
        }
        return self::$sources;
    }

    public static function source(string $key): ?array
    {
        return self::sources()[$key] ?? null;
    }

    /** Öffentliche Adresse einer Quelle (absolut, da z. B. MapLibre-Styles absolute URLs brauchen) */
    public static function url(string $key, string $path = ''): string
    {
        return self::origin() . url('/proxy/' . $key . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }

    private static function origin(): string
    {
        $s = app()->request?->server ?? $_SERVER;
        $https = ($s['HTTPS'] ?? '') === 'on' || ($s['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || ($s['SERVER_PORT'] ?? '') === '443';
        $host = (string) ($s['HTTP_HOST'] ?? '');
        if (!preg_match('~^[a-z0-9.-]+(:\d+)?$~i', $host)) {
            $host = (string) parse_url((string) setting('sys.site_url'), PHP_URL_HOST);
        }
        return ($https ? 'https' : 'http') . '://' . $host;
    }

    // ------------------------------------------------------------------ öffentlicher Abruf

    public static function handle(string $key, string $path, array $server = []): Response
    {
        $src = self::source($key);
        $path = ltrim($path, '/');
        if (!$src || empty($src['public']) || str_contains($path, '..') || !preg_match('~^[\w.\-/@+, ]*$~u', $path)) {
            throw new HttpException(404);
        }
        if (!empty($src['allow']) && !preg_match('~' . str_replace('~', '\~', $src['allow']) . '~', $path)) {
            throw new HttpException(404);
        }
        // Angemeldete Redaktion darf überall hin (z. B. neuen Ort in der Karte wählen)
        if (isset($src['guard']) && is_callable($src['guard']) && !app()->auth->check() && !($src['guard'])($path)) {
            return self::out('', 'text/plain', 204, 3600, $src, $key);
        }

        $file = self::cacheFile($key, $path);
        $meta = is_file("$file.json") ? (json_decode((string) file_get_contents("$file.json"), true) ?: null) : null;
        $fresh = $meta && (time() - (int) $meta['fetched']) < (int) $meta['ttl'];
        $state = 'HIT';

        if (!$fresh) {
            // Nachladen beim Anbieter – je Besucher begrenzt (Missbrauch als freier Kachelserver verhindern)
            $ip = hash('sha256', ($server['REMOTE_ADDR'] ?? '') . app()->config->get('app_key', CMS_NAME));
            $limiter = new RateLimiter(app()->db);
            if ($limiter->tooMany('proxy:' . substr($ip, 0, 24), 2000, 600)) {
                if ($meta) {
                    $state = 'STALE';
                } else {
                    throw new HttpException(429);
                }
            } else {
                $limiter->hit('proxy:' . substr($ip, 0, 24));
                $res = self::fetch($src, $src['upstream'] . '/' . implode('/', array_map('rawurlencode', explode('/', $path))));
                if ($res && $res['status'] === 200) {
                    $meta = self::store($key, $path, $src, $res);
                    $state = 'MISS';
                } elseif ($res && in_array($res['status'], [204, 404], true)) {
                    // Leere Kacheln (Meer, Rand) kurz merken
                    $meta = self::store($key, $path, $src, ['status' => $res['status'], 'body' => '', 'type' => 'text/plain'], 3600);
                    $state = 'MISS';
                } elseif (!$meta) {
                    throw new HttpException(502);
                } else {
                    $state = 'STALE';
                }
            }
        }
        if ((int) $meta['status'] !== 200) {
            return self::out('', 'text/plain', 204, 3600, $src, $key);
        }

        $etag = '"' . $meta['etag'] . '"';
        $cc = 'public, max-age=' . min((int) $meta['ttl'], 30 * 86400);
        if (trim((string) ($server['HTTP_IF_NONE_MATCH'] ?? '')) === $etag && empty($src['rewrite'])) {
            return (new Response('', 304))->header('ETag', $etag)->header('Cache-Control', $cc);
        }

        $body = (string) file_get_contents($file);
        $gz = !empty($meta['gz']);
        $acceptsGzip = str_contains((string) ($server['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip');
        if (!empty($src['rewrite']) && self::isText($meta['type'])) {
            $body = self::rewrite($gz ? (string) gzdecode($body) : $body, $key, $src);
            $gz = false;
        }
        if ($gz && !$acceptsGzip) {
            $body = (string) gzdecode($body);
            $gz = false;
        }
        $r = self::out($body, $meta['type'], 200, (int) $meta['ttl'], $src, $key)->header('ETag', $etag)->header('X-Proxy-Cache', $state);
        if ($gz) {
            $r->header('Content-Encoding', 'gzip')->header('Vary', 'Accept-Encoding');
        }
        return $r;
    }

    private static function out(string $body, string $type, int $status, int $ttl, array $src, string $key): Response
    {
        return (new Response($body, $status, [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age=' . min($ttl, 30 * 86400),
            'Access-Control-Allow-Origin' => '*',
            'Cross-Origin-Resource-Policy' => 'same-site',
            'X-Robots-Tag' => 'noindex',
        ]));
    }

    /** Upstream-Adressen in Text-Antworten auf den eigenen Proxy umbiegen */
    private static function rewrite(string $body, string $key, array $src): string
    {
        $local = self::url($key);
        $map = [$src['upstream'] => $local, str_replace('/', '\/', $src['upstream']) => str_replace('/', '\/', $local)];
        return strtr($body, $map);
    }

    private static function isText(string $type): bool
    {
        return str_starts_with($type, 'application/json') || str_starts_with($type, 'text/') || str_contains($type, '+json');
    }

    // ------------------------------------------------------------------ Cache

    private static function dir(?string $key = null): string
    {
        return ROOT . '/storage/cache/proxy' . ($key !== null ? '/' . $key : '');
    }

    private static function cacheFile(string $key, string $path): string
    {
        $h = sha1($path);
        return self::dir($key) . '/' . substr($h, 0, 2) . '/' . $h;
    }

    private static function store(string $key, string $path, array $src, array $res, ?int $ttl = null): array
    {
        $file = self::cacheFile($key, $path);
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        $type = strtolower(trim(explode(';', (string) $res['type'])[0])) ?: 'application/octet-stream';
        if (self::isText($type)) $type .= '; charset=utf-8';
        $body = (string) $res['body'];
        $gz = false;
        foreach (self::COMPRESS as $c) {
            if (str_starts_with($type, $c) && strlen($body) > 512 && function_exists('gzencode')) {
                $body = (string) gzencode($body, 6);
                $gz = true;
                break;
            }
        }
        $t = $ttl ?? (is_callable($src['ttl']) ? (int) ($src['ttl'])($path) : (int) $src['ttl']);
        $meta = ['status' => (int) $res['status'], 'type' => $type, 'fetched' => time(), 'ttl' => max(60, $t), 'gz' => $gz, 'etag' => substr(sha1($body), 0, 16), 'path' => $path];
        @file_put_contents($file, $body, LOCK_EX);
        @file_put_contents("$file.json", json_encode($meta, JSON_UNESCAPED_SLASHES), LOCK_EX);
        if (random_int(1, 300) === 1) {
            self::prune();
        }
        return $meta;
    }

    /** Cache verkleinern, wenn er die eingestellte Größe überschreitet (älteste Einträge zuerst) */
    public static function prune(): void
    {
        $limit = max(50, (int) setting('sys.proxy_cache_mb', 500)) * 1024 * 1024;
        $files = [];
        $total = 0;
        foreach (self::files() as $f) {
            $size = (int) @filesize($f);
            $total += $size;
            $files[] = [$f, (int) @filemtime($f), $size];
        }
        if ($total <= $limit) return;
        usort($files, fn($a, $b) => $a[1] <=> $b[1]);
        foreach ($files as [$f, , $size]) {
            @unlink($f);
            @unlink("$f.json");
            $total -= $size;
            if ($total < $limit * 0.8) break;
        }
    }

    private static function files(?string $key = null): \Generator
    {
        $dir = self::dir($key);
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && !str_ends_with($f->getFilename(), '.json')) yield $f->getPathname();
        }
    }

    /** @return array<string, array{files: int, bytes: int}> */
    public static function stats(): array
    {
        $out = [];
        foreach (self::sources() as $key => $src) {
            $n = $b = 0;
            foreach (self::files($key) as $f) {
                $n++;
                $b += (int) @filesize($f);
            }
            $out[$key] = ['files' => $n, 'bytes' => $b];
        }
        return $out;
    }

    public static function clear(?string $key = null): int
    {
        $n = 0;
        foreach (self::files($key) as $f) {
            @unlink($f);
            @unlink("$f.json");
            $n++;
        }
        return $n;
    }

    // ------------------------------------------------------------------ ausgehende Anfragen (auch serverseitig)

    /**
     * HTTP-GET an eine registrierte Quelle (serverseitig, z. B. Geocoding, oEmbed).
     * Nur Hosts registrierter Quellen; https, keine Weiterleitungen, kurze Timeouts.
     * @return array{status: int, body: string, type: string}|null
     */
    public static function http(string $url, array $headers = []): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::sources() as $src) {
            $hosts = array_merge([strtolower((string) parse_url($src['upstream'], PHP_URL_HOST))], array_map('strtolower', (array) ($src['hosts'] ?? [])));
            if (in_array($host, $hosts, true)) {
                return self::fetch($src + ['headers' => []], $url, $headers);
            }
        }
        return null;
    }

    private static function fetch(array $src, string $url, array $extra = []): ?array
    {
        if (!str_starts_with($url, 'https://')) return null;
        $headers = array_merge(['Accept: */*'], (array) ($src['headers'] ?? []), $extra);
        $ua = CMS_NAME . '/' . CMS_VERSION . ' (+' . (setting('sys.site_url') ?: 'server') . ')';
        $max = (int) ($src['max'] ?? 5 * 1024 * 1024);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $size = 0;
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_USERAGENT => $ua, CURLOPT_HTTPHEADER => $headers, CURLOPT_ENCODING => '',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => function ($ch, $dlTotal, $dl) use ($max) { return $dl > $max ? 1 : 0; },
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            if (!is_string($body)) return null;
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 8, 'follow_location' => 0, 'user_agent' => $ua, 'header' => implode("\r\n", $headers), 'ignore_errors' => true]]);
            $body = @file_get_contents($url, false, $ctx, 0, $max + 1);
            if ($body === false) return null;
            $status = 0;
            $type = '';
            // PHP 8.5: $http_response_header ist veraltet – Kopfzeilen der letzten Antwort über die Funktion (ab PHP 8.4)
            foreach (http_get_last_response_headers() ?? [] as $h) {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $status = (int) $m[1];
                if (stripos($h, 'content-type:') === 0) $type = trim(substr($h, 13));
                if (stripos($h, 'content-encoding: gzip') === 0) $body = (string) @gzdecode($body);
            }
        }
        if (strlen($body) > $max) return null;
        if ($status === 200) {
            $allowed = (array) ($src['types'] ?? self::DEFAULT_TYPES);
            $t = strtolower($type);
            $ok = false;
            foreach ($allowed as $a) {
                if (str_starts_with($t, strtolower($a))) { $ok = true; break; }
            }
            // Manche Kachelserver liefern Protobuf ohne passenden Typ
            if (!$ok && $t === '' && str_ends_with(parse_url($url, PHP_URL_PATH) ?? '', '.pbf')) {
                $type = 'application/x-protobuf';
                $ok = true;
            }
            if (!$ok) return null;
        }
        return ['status' => $status, 'body' => $body, 'type' => $type];
    }
}
