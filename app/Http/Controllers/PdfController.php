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
    public function show(Request $r, string $id): Response
    {
        $m = ctype_digit($id) ? Media::find((int) $id) : null;
        if (!$m || $m['mime'] !== 'application/pdf') {
            throw new HttpException(404);
        }
        $html = Theme::capture(ROOT . '/app/Views/pdf-viewer.php', [
            'm' => $m, 'embed' => isset($r->query['embed']),
            'back' => isset($r->query['embed']) ? null : url('/'),
        ]);
        return (new Response($html))
            ->header('Cache-Control', 'public, max-age=300')
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
