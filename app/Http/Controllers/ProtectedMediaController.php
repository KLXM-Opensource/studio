<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Extensions;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\MediaPools;

/**
 * GET /geschuetzt/{pool}/{pfad} – Dateien geschützter Medien-Pools (Core\MediaPools::isProtected). Liegen außerhalb von public/,
 * deshalb nur hier: Pool geschützt und für diese Website freigegeben, Pfad im Pool-Ordner, Zugriff erlaubt – angemeldete Redaktion
 * oder eine Erweiterung (Extension::mediaAccess, z. B. angemeldete Mitglieder). Ohne Zugriff 404 (verrät nicht, dass es die Datei
 * gibt) bzw. die Antwort der Erweiterung (z. B. Weiterleitung zur Anmeldung beim direkten Aufruf).
 * Antwort: private Zwischenspeicherung, nosniff, noindex; Bilder, PDF, Audio, Video im Browser (mit Byte-Bereichen für Videos),
 * alles andere – auch SVG – als Download.
 */
final class ProtectedMediaController
{
    private const INLINE = ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif', 'application/pdf', 'video/mp4', 'video/webm',
        'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'text/vtt'];

    public function serve(Request $r, string $pool, string $path): Response
    {
        if (!MediaPools::isProtected($pool) || !MediaPools::available($pool)) throw new HttpException(404);
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..') || preg_match('~(^|/)\.|[\x00-\x1f]~', $path)) throw new HttpException(404);
        $base = realpath(MediaPools::mediaDir($pool));
        $file = $base ? realpath($base . '/' . $path) : false;
        if (!$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) throw new HttpException(404);
        if (!app()->auth->check()) {
            $ok = Extensions::mediaAccess(['pool' => $pool, 'path' => $path], $r);
            if ($ok instanceof Response) return $ok->header('Cache-Control', 'private, no-store');
            if ($ok !== true) throw new HttpException(404);
        }
        return self::send($file, $r);
    }

    /** Datei streamen (auch Byte-Bereiche, z. B. Springen in Videos) */
    public static function send(string $file, Request $r): Response
    {
        $mime = (string) ((new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream');
        if (str_ends_with($file, '.vtt')) $mime = 'text/vtt';
        $inline = in_array($mime, self::INLINE, true);
        $size = (int) filesize($file);
        $start = 0;
        $end = $size - 1;
        $status = 200;
        $range = (string) ($r->server['HTTP_RANGE'] ?? '');
        if ($range !== '' && preg_match('~^bytes=(\d*)-(\d*)$~', $range, $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') { $start = max(0, $size - (int) $m[2]); }
            else { $start = (int) $m[1]; if ($m[2] !== '') $end = min($end, (int) $m[2]); }
            if ($start > $end || $start >= $size) {
                return (new Response('', 416))->header('Content-Range', 'bytes */' . $size);
            }
            $status = 206;
        }
        while (ob_get_level()) ob_end_clean();
        http_response_code($status);
        header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
        header('Content-Length: ' . ($end - $start + 1));
        header('Accept-Ranges: bytes');
        if ($status === 206) header("Content-Range: bytes $start-$end/$size");
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . (preg_replace('~[^A-Za-z0-9._-]+~', '_', basename($file)) ?: 'datei') . '"');
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');
        header('Cross-Origin-Resource-Policy: same-origin');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox");
        $h = fopen($file, 'rb');
        if ($h) {
            fseek($h, $start);
            $left = $end - $start + 1;
            while ($left > 0 && !feof($h)) {
                $chunk = fread($h, min(65536, $left));
                if ($chunk === false) break;
                echo $chunk;
                $left -= strlen($chunk);
            }
            fclose($h);
        }
        return Response::alreadySent();
    }
}
