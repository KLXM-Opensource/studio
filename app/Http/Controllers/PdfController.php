<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Media;
use Core\Theme;

/** PDF-Anzeige im Browser mit Mozilla PDF.js (lokal ausgeliefert, keine Dritten). */
final class PdfController
{
    /** PDF aus einem geteilten Pool, den diese Website nutzen darf (Mediathek-Ansicht „Geteilt“) */
    public function showPool(Request $r, string $pool, string $id): Response
    {
        if (!isset(\Core\MediaPools::forSite()[$pool]) || !ctype_digit($id)) throw new HttpException(404);
        Media::usePool($pool);
        try { $m = Media::find((int) $id); } finally { Media::usePool(null); }
        return $this->render($r, $m);
    }

    public function show(Request $r, string $id): Response
    {
        return $this->render($r, ctype_digit($id) ? Media::find((int) $id) : null);
    }

    private function render(Request $r, ?array $m): Response
    {
        if (!$m || $m['mime'] !== 'application/pdf') {
            throw new HttpException(404);
        }
        // Geschützter Pool: Ansicht nur mit Zugriff wie die Datei selbst (Core\Http\Controllers\ProtectedMediaController)
        $private = \Core\MediaPools::mediaProtected($m);
        if ($private && !app()->auth->check()) {
            $key = (string) ($m['_pool'] ?? strtok((string) $m['pool_ref'], ':'));
            $ok = \Core\Extensions::mediaAccess(['pool' => $key, 'path' => (string) $m['file']], $r);
            if ($ok instanceof Response) return $ok->header('Cache-Control', 'private, no-store');
            if ($ok !== true) throw new HttpException(404);
        }
        $html = Theme::capture(ROOT . '/app/Views/pdf-viewer.php', [
            'm' => $m, 'embed' => isset($r->query['embed']),
            'back' => isset($r->query['embed']) ? null : url('/'),
        ]);
        return (new Response($html))
            ->header('Cache-Control', $private ? 'private, no-store' : 'public, max-age=300')
            ->header('X-Robots-Tag', 'noindex')
            ->header('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'wasm-unsafe-eval'",   // PDF.js-Bilddecoder (WebAssembly)
                "worker-src 'self' blob:",
                "style-src 'self'",
                "img-src 'self' data: blob:",
                "font-src 'self' data: blob:",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "frame-ancestors 'self'",
            ]));
    }
}
