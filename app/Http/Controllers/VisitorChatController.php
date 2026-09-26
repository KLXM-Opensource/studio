<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\AI\AiException;
use Core\AI\VisitorChat;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Sse;
use Core\Lang;
use Core\RateLimiter;

/**
 * Besucher-Chat (Core\AI\VisitorChat): GET /api/chat/config (Texte in der Sprache der Seite), POST /api/chat (Antwort als
 * Server-Sent Events, ohne Accept: text/event-stream als JSON). Keine Cookies, keine Sitzung, keine Protokollierung der
 * Inhalte; Ratenbegrenzung je HMAC der IP und Tageslimit der Website. Nur Anfragen von der eigenen Domain (Origin).
 */
final class VisitorChatController
{
    public function config(Request $r): Response
    {
        if (!$this->lang($r) || !VisitorChat::available()) return self::json(['ok' => false], 404);
        return self::json(['ok' => true] + VisitorChat::publicConfig(), 200, 'public, max-age=60');
    }

    public function ask(Request $r): Response
    {
        if (!$this->lang($r) || !VisitorChat::available()) return self::json(['ok' => false, 'error' => lt('Der Chat ist gerade nicht verfügbar.')], 404);
        if (!self::sameOrigin($r)) return self::json(['ok' => false, 'error' => 'Origin'], 403);
        $q = VisitorChat::clean((string) ($r->post['q'] ?? ''));
        if (mb_strlen($q) < 2) return self::json(['ok' => false, 'error' => lt('Bitte stellen Sie eine Frage.')], 422);

        $limiter = new RateLimiter(app()->db);
        $key = 'chat:' . VisitorChat::ipKey($r->ip());
        if ($limiter->tooMany($key, VisitorChat::PER_MINUTE, 60) || $limiter->tooMany($key, VisitorChat::PER_DAY, 86400)) {
            return self::json(['ok' => false, 'error' => lt('Sie haben gerade sehr viele Fragen gestellt. Bitte versuchen Sie es in einer Minute erneut.')], 429)->header('Retry-After', '60');
        }
        $limiter->hit($key);
        if (VisitorChat::quota()['left'] === 0) {
            return self::json(['ok' => false, 'error' => lt('Der Chat ist für heute ausgelastet. Bitte nutzen Sie die Suche oder nehmen Sie Kontakt mit uns auf.'), 'busy' => true], 503);
        }
        $history = VisitorChat::history($r->post['history'] ?? []);

        if (!Sse::wanted($r)) {
            // Rückfall ohne Streaming: ganze Antwort als JSON
            try {
                $out = VisitorChat::answer($q, $history, fn() => true);
            } catch (AiException) {
                return self::json(['ok' => false, 'error' => lt('Das hat leider nicht geklappt. Bitte versuchen Sie es später noch einmal.')], 502);
            }
            return self::json(['ok' => true] + $out);
        }
        return Sse::stream(function (Sse $sse) use ($q, $history) {
            try {
                $out = VisitorChat::answer($q, $history, fn(string $ev, array $data) => $sse->send($ev, $data));
                $sse->send('done', ['ok' => true] + $out);
            } catch (AiException) {
                $sse->send('error', ['error' => lt('Das hat leider nicht geklappt. Bitte versuchen Sie es später noch einmal.')]);
            }
        }, ['Referrer-Policy' => 'no-referrer']);
    }

    /** Sprache der Seite (?lang= bzw. Feld lang) übernehmen – nur aktive Sprachen */
    private function lang(Request $r): bool
    {
        $l = (string) ($r->post['lang'] ?? $r->query['lang'] ?? '');
        if ($l === '' || $l === Lang::default()) return true;
        if (!Lang::multi() || !Lang::valid($l)) return false;
        app()->lang = $l;
        return true;
    }

    /** Anfrage von der eigenen Website? (Origin bzw. Referer auf denselben Host; ohne beide Angaben: erlaubt, z. B. curl) */
    private static function sameOrigin(Request $r): bool
    {
        $o = (string) ($r->server['HTTP_ORIGIN'] ?? $r->server['HTTP_REFERER'] ?? '');
        if ($o === '') return true;
        return strcasecmp((string) parse_url($o, PHP_URL_HOST) . (($p = parse_url($o, PHP_URL_PORT)) ? ':' . $p : ''), $r->host()) === 0;
    }

    private static function json(array $data, int $status = 200, string $cache = 'no-store'): Response
    {
        return Response::json($data, $status)->header('Cache-Control', $cache)->header('X-Robots-Tag', 'noindex')
            ->header('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")->header('Referrer-Policy', 'no-referrer');
    }
}
