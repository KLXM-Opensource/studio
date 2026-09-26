<?php
// SPDX-License-Identifier: MIT
// Portions ported from FriendsOfREDAXO/consent_kit lib/Api/Save.php (MIT, © KLXM Crossmedia GmbH)
declare(strict_types=1);

namespace MyCms\Consent;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Lang;

/** Öffentliche Endpunkte (ohne Sitzung): Entscheidung speichern, Code der Dienste, Stylesheet der Komponente. */
final class PublicController
{
    private function guard(): void
    {
        if (!Consent::enabled()) throw new HttpException(404);
    }

    private static function lang(?string $l): string
    {
        return Lang::valid($l) ? (string) $l : Lang::default();
    }

    /**
     * POST /consent/save – {"id":"uuid","action":"custom","accepted":["matomo"],"gpc":false,"lang":"de"}
     * Übernimmt nur Schlüssel, die auf dieser Domain aktiv und optional sind; schreibt das Protokoll (ohne IP/User-Agent),
     * setzt den Cookie per Set-Cookie (Safari kürzt reine JavaScript-Cookies auf 7 Tage) und antwortet mit dem Zustand.
     */
    public function save(Request $r): Response
    {
        $this->guard();
        // Nur Anfragen der eigenen Seite (Fetch Metadata; ältere Browser: Origin muss zum Host passen)
        $site = (string) ($r->server['HTTP_SEC_FETCH_SITE'] ?? '');
        $origin = (string) ($r->server['HTTP_ORIGIN'] ?? '');
        if ($site !== '' ? $site !== 'same-origin' : ($origin === '' || strtolower((string) parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '')) !== strtolower($r->host()))) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $limiter = new \Core\RateLimiter(app()->db);
        $rk = 'consent:' . substr(hash_hmac('sha256', $r->ip(), app()->key()), 0, 24);
        if ($limiter->tooMany($rk, 60, 600)) return Response::json(['ok' => false, 'error' => 'rate'], 429);
        $limiter->hit($rk);

        $action = (string) ($r->post['action'] ?? '');
        if (!isset(Log::ACTIONS[$action])) return Response::json(['ok' => false, 'error' => 'action'], 422);
        $id = (string) ($r->post['id'] ?? '');
        if (!preg_match('~^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$~', $id)) $id = self::uuid();
        $lang = self::lang(is_string($r->post['lang'] ?? null) ? $r->post['lang'] : null);
        $gpc = !empty($r->post['gpc']) || ($r->server['HTTP_SEC_GPC'] ?? '') === '1';

        $b = Compiler::build(null, $lang);
        $optional = array_filter($b['services'], fn($c) => $c['grp'] !== 'necessary');
        $want = array_map('strval', (array) ($r->post['accepted'] ?? []));
        $a = $rj = [];
        foreach ($optional as $k => $c) {
            if ($action !== 'withdraw' && in_array($k, $want, true)) $a[$k] = $c['h'];
            else $rj[$k] = $c['h'];
        }
        $rev = Compiler::revision($b);
        Log::add($id, $action, array_keys($a), array_keys($rj), $rev, $b['domain'], $gpc, $lang);

        $days = max(1, min(400, (int) Repository::settings()['days']));
        $state = ['id' => $id, 'e' => Repository::epoch(), 'rev' => $rev, 'ts' => time(), 'a' => (object) $a, 'r' => (object) $rj];
        $res = Response::json(['ok' => true, 'state' => $action === 'withdraw' ? null : $state]);
        $cookie = Consent::COOKIE . '=' . ($action === 'withdraw' ? '' : rawurlencode((string) json_encode($state, JSON_UNESCAPED_SLASHES)))
            . '; Max-Age=' . ($action === 'withdraw' ? 0 : $days * 86400) . '; Path=' . (base_path() ?: '/') . '; SameSite=Lax' . ($r->isSecure() ? '; Secure' : '');
        return $res->header('Set-Cookie', $cookie);
    }

    /**
     * GET /consent/js/{dienst}.{teil}.js?l=de&v=… – Code eines Dienstes als Datei der eigenen Domain (CSP script-src 'self'):
     * Teil n = n-ter Inline-Code aus „HTML im head/body“, a = „JavaScript bei Einwilligung“ + Ereignisse, r = „bei Widerruf“,
     * „_.d.js“ = „JavaScript vor jeder Entscheidung“ aller Dienste. Nur für aktive, vollständige Dienste dieser Domain.
     */
    public function code(Request $r, string $file): Response
    {
        $this->guard();
        if (!preg_match('~^([a-z0-9_]{1,60})\.(d|a|r|\d{1,2})\.js$~', $file, $m)) throw new HttpException(404);
        $lang = self::lang(is_string($r->query['l'] ?? null) ? $r->query['l'] : null);
        $b = Compiler::build(null, $lang);
        if ($m[1] === '_' && $m[2] === 'd') {
            $js = $b['default'];
        } else {
            $c = $b['services'][$m[1]] ?? null;
            if (!$c) throw new HttpException(404);
            $js = match ($m[2]) {
                'a' => $c['_accept'],
                'r' => $c['_revoke'],
                'd' => $c['default'],
                default => $c['_chunks'][(int) $m[2]] ?? null,
            };
        }
        if ($js === null) throw new HttpException(404);
        $versioned = isset($r->query['v']) && is_string($r->query['v']) && $r->query['v'] !== '';
        return new Response($js . "\n", 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => $versioned ? 'public, max-age=31536000, immutable' : 'public, max-age=300',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /** GET /consent/style.css?v=… – Basis-CSS der Komponente + Variablen dieser Website (im Shadow DOM geladen) */
    public function style(Request $r): Response
    {
        $this->guard();
        $versioned = ($r->query['v'] ?? '') === Design::version();
        return new Response(Design::css(), 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => $versioned ? 'public, max-age=31536000, immutable' : 'public, max-age=300',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
