<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Lang;
use Core\Pages;
use Core\RateLimiter;
use Core\Search\Search;
use Core\Seo;

/**
 * Öffentliche Website-Suche: /suche?q=… (Standardsprache), /en/search?q=… (weitere Sprachen) und
 * /suche/vorschlag?q=… (JSON für die Vorschläge beim Tippen). Serverseitig gerendert, funktioniert ohne JavaScript.
 * Seiten mit demselben Pfad haben Vorrang; ist die Funktion „search“ aus, verhält sich die Adresse wie jede andere.
 *
 * Keine Cookies, keine Protokollierung der Suchtexte; Ratenbegrenzung über einen Hash der IP (Tabelle hits).
 */
final class SearchPageController
{
    /** Anfragen je Minute und IP: Ergebnisseite (darüber: 429), semantische Suche (darüber: nur Stichwortsuche), Vorschläge */
    private const LIMIT_PAGE = 60;
    private const LIMIT_SEMANTIC = 20;
    private const LIMIT_SUGGEST = 120;

    public function show(Request $r, string $lang = '', string $slug = ''): Response
    {
        if ($res = $this->prepare($r, $lang, $slug)) return $res;
        $app = app();
        if ($app->settings->get('sys.maintenance') && !$app->auth->check()) {
            return (new SiteController())->home($r);   // Startseite rendert im Wartungsmodus die Wartungsseite (503)
        }
        $q = Search::clean((string) ($r->query['q'] ?? ''));
        $page = max(1, min(100, (int) ($r->query['seite'] ?? $r->query['page'] ?? 1)));
        $type = (string) ($r->query['typ'] ?? $r->query['type'] ?? '');
        $result = ['q' => $q, 'items' => [], 'total' => 0, 'page' => 1, 'pages' => 0, 'mode' => 'keyword', 'fallback' => false, 'types' => [], 'type' => '', 'facets' => [], 'facet' => []];
        $limited = false;
        if ($q !== '') {
            $limiter = new RateLimiter($app->db);
            $key = 'search:' . self::ipKey($r);
            if ($limiter->tooMany($key, self::LIMIT_PAGE, 60)) {
                $limited = true;
            } else {
                $limiter->hit($key);
                $semantic = !$limiter->tooMany($key, self::LIMIT_SEMANTIC, 60);
                $result = Search::query($q, ['page' => $page, 'type' => $type, 'semantic' => $semantic, 'facet' => (array) ($r->query['f'] ?? [])]);
            }
        }
        $theme = $app->theme;
        $vars = ['result' => $result, 'q' => $q, 'limited' => $limited, 'form' => Search::form('page', $q),
            'url' => fn(array $extra = []) => Search::url($q, null, $extra)];
        $content = is_file($theme->path . '/templates/search.php')
            ? $theme->render('search', $vars)
            : \Core\Theme::capture(ROOT . '/app/Views/search.php', $vars);

        $title = $q !== '' ? lt('Suche: {q}', ['q' => $q]) : lt('Suche');
        $pseudo = ['id' => 0, 'title' => $title, 'slug' => Search::slug(), 'is_home' => 0, 'meta_description' => '', 'noindex' => 1, 'status' => 'published',
            'lang' => Lang::current() === Lang::default() ? null : Lang::current(), 'type' => 'search'];
        $seo = Seo::forError(200);
        $seo['title'] = $title . (($s = self::suffix()) !== '' ? ' | ' . $s : '');
        $seo['noindex'] = true;
        $css = is_file(ROOT . '/public/themes/' . $theme->name . '/css/search.css') ? $theme->asset('css/search.css') : asset('css/search.css');
        $html = $theme->render('layout', [
            'page' => $pseudo, 'content' => $content, 'seo' => $seo, 'editor' => null, 'toolbar' => null,
            'extraCss' => [$css], 'extraJs' => [],
        ]);
        $res = (new SiteController())->respond(\Core\Icons::siteSprite($html), false, $limited ? 429 : 200);
        return $res->header('X-Robots-Tag', 'noindex, follow')->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'same-origin');
    }

    /** JSON: {q, items: [{title, url, badge, date}], all} – Vorschläge beim Tippen (nur Stichwortsuche) */
    public function suggest(Request $r, string $lang = '', string $slug = ''): Response
    {
        if ($res = $this->prepare($r, $lang, $slug)) return $res;
        $q = Search::clean((string) ($r->query['q'] ?? ''));
        $limiter = new RateLimiter(app()->db);
        $key = 'suggest:' . self::ipKey($r);
        if ($limiter->tooMany($key, self::LIMIT_SUGGEST, 60)) {
            return self::json(['q' => $q, 'items' => [], 'all' => Search::url($q)], 429)->header('Retry-After', '60');
        }
        $limiter->hit($key);
        return self::json(['q' => $q, 'items' => Search::suggest($q), 'all' => Search::url($q)]);
    }

    /** Sprache setzen, Vorrang für Seiten gleichen Pfads, Funktion aus → wie normale Seite */
    private function prepare(Request $r, string $lang, string $slug): ?Response
    {
        $path = trim($r->path, '/');
        $site = new SiteController();
        if ($lang !== '') {
            if (!Lang::multi() || $lang === Lang::default() || !Lang::valid($lang)) return $site->page($r, $path);
            app()->lang = $lang;
        }
        if (!Search::enabled() || Pages::byPath($lang !== '' ? substr($path, strlen($lang) + 1) : $path)) {
            app()->lang = null;
            return $site->page($r, $path);
        }
        return null;
    }

    private static function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status)->header('Cache-Control', $status === 200 ? 'public, max-age=60' : 'no-store')
            ->header('X-Robots-Tag', 'noindex')->header('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    /** Kurzer HMAC der IP (mit app_key) – nie die IP selbst */
    private static function ipKey(Request $r): string
    {
        return substr(hash_hmac('sha256', $r->ip(), (string) app()->config->get('app_key', CMS_NAME)), 0, 20);
    }

    private static function suffix(): string
    {
        $theme = app()->theme->def;
        $s = (string) setting($theme['seo']['title_suffix'] ?? 'site_title_suffix', '');
        return $s !== '' ? $s : (string) setting($theme['seo']['title_suffix_fallback'] ?? 'site_name', '');
    }
}
