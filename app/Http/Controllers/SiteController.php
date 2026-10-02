<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Data\Entries;
use Core\Data\Tables;
use Core\Landings;
use Core\Lang;
use Core\NotFound;
use Core\Pages;
use Core\PageCache;
use Core\Seo;

final class SiteController
{
    public function home(Request $r): Response
    {
        // Landing-Domain (Core\Landings): „/“ zeigt die Einstiegsseite der Landingpage
        if ($l = Landings::current()) return $this->landing($r, $l, '');
        $page = Pages::home() ?? throw new HttpException(404);
        return $this->render($r, $page);
    }

    /** Seiten nach vollständigem Pfad, Detailseiten von Datentabellen, Weiterleitung alter Kurz-Adressen */
    public function page(Request $r, string $path): Response
    {
        $path = trim($path, '/');
        if ($l = Landings::current()) return $this->landing($r, $l, $path);
        // Sprachpräfix /en/…: weitere Sprachen; die Standardsprache hat kein Präfix
        $first = explode('/', $path)[0];
        if (Lang::multi() && $first !== Lang::default() && Lang::valid($first)) {
            app()->lang = $first;
            $path = trim(substr($path, strlen($first)), '/');
            if ($path === '') {
                $home = Pages::home($first);
                if (!$home || Lang::norm($home['lang']) !== $first) throw new HttpException(404);
                return $this->render($r, $home);
            }
        }
        $page = Pages::byPath($path);
        if ($page) {
            return $page['is_home'] ? Response::redirect(Pages::url($page), 301) : $this->render($r, $page);
        }
        // Detailseite: /{URL-Basis der Tabelle}/{slug}
        if (preg_match('~^(.+)/([^/]+)$~', $path, $m) && ($t = Tables::byRoute($m[1])) && !empty($t['settings']['detail_page_id'])) {
            $tpl = Pages::find((int) $t['settings']['detail_page_id']);
            if ($tpl && Lang::multi()) {
                $tpl = Pages::translations($tpl)[Lang::current()] ?? $tpl;   // übersetzte Vorlage, sonst Standard
            }
            // Geteilte Tabellen: nur Einträge, die diese Website zeigt (eigene + gewählte Quellen)
            $entry = Entries::bySlug($t, $m[2], !app()->auth->check(), Lang::current(), 'site');
            if ($tpl && $entry) {
                app()->entry = ['table' => $t, 'entry' => $entry];
                return $this->render($r, $tpl);
            }
        }
        // Seitenvorlage (Core\PageTemplates): nur angemeldet mit „system.manage“, nie für Besucher
        if (preg_match('~^' . \Core\PageTemplates::PATH . '/(\d+)$~', $path, $m)) {
            $tpl = Pages::find((int) $m[1]);
            if ($tpl && \Core\PageTemplates::isTemplatePage($tpl) && app()->auth->check() && can('system.manage')) return $this->render($r, $tpl);
            throw new HttpException(404);
        }
        // Eigene Adresse der Seite „Nicht gefunden“ (/404): antwortet selbst mit 404 – ohne Weiterleitungen und 404-Protokoll;
        // angemeldet mit ?edit=1 öffnet sie den Editor (Core\NotFound)
        if (NotFound::isOwnPath($path)) {
            return $this->error(404);
        }
        // Alte Adresse einer inzwischen verschachtelten Seite → dauerhaft weiterleiten
        if (!app()->lang && !str_contains($path, '/') && ($p = Pages::bySlug($path)) && $p['type'] === 'page' && ($p['path'] ?? '') !== $path) {
            return Response::redirect(Pages::url($p), 301);
        }
        throw new HttpException(404);
    }

    /**
     * Anfrage auf einer Landing-Domain: Einstiegsseite, Unterseiten (falls eingeschaltet), Übersetzungen unter /en/…;
     * alles andere → Hauptdomain (302) oder 404.
     */
    private function landing(Request $r, \Core\Landing $l, string $path): Response
    {
        $first = explode('/', $path)[0];
        if (Lang::multi() && $first !== Lang::default() && Lang::valid($first)) {
            app()->lang = $first;
            $path = trim(substr($path, strlen($first)), '/');
        }
        $page = $l->resolve($path, Lang::current());
        if ($page) return $this->render($r, $page);
        if ($l->redirectOther) {
            $qs = (string) parse_url((string) ($r->server['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
            return Response::redirect(Landings::mainOrigin() . url($r->path) . ($qs !== '' ? '?' . $qs : ''), 302);
        }
        throw new HttpException(404);
    }

    /** Blocktypen der Seite für conditional_css (Stylesheets und Skripte): „typ“ und „typ:variante“ */
    private static function types(array $blocks): array
    {
        $blocks = \Core\Layout::flatten($blocks);   // auch Blöcke in Spalten (Stylesheets/Skripte je Typ)
        return array_merge(array_column($blocks, 'type'), array_map(fn($b) => $b['type'] . ':' . ($b['data']['variant'] ?? ''), $blocks));
    }

    /**
     * HTML einer Seite für Vorschauen: veröffentlichte Fassung (bzw. Entwurf, solange nie veröffentlicht), mit $draft = true
     * immer der aktuelle Entwurf (z. B. Erweiterungen, die einen Entwurf zur Abstimmung zeigen). Ohne Editor-Leiste,
     * ohne Seiten-Cache, noindex. Header (CSP, Cache-Control) setzt der Aufrufer – z. B. respond() bzw. csp().
     */
    public function previewHtml(array $page, bool $draft = false): string
    {
        $app = app();
        $app->currentPage = $page;
        \Core\StructuredData::reset();
        $blocks = Pages::blocks($page, $draft || $page['content_published'] === null);
        $theme = $app->theme;
        $show = \Core\EditorNotes::$show;
        \Core\EditorNotes::$show = false;   // Vorschau/Freigabe für Dritte: Redaktionsnotizen nie zeigen
        try {
            $html = $theme->render('layout', [
                'page' => $page,
                'content' => $theme->renderBlocks($blocks),
                'seo' => ['noindex' => true] + Seo::forPage($page),
                'editor' => null,
                'extraCss' => $theme->conditionalCss(self::types($blocks)),
                'extraJs' => $theme->conditionalJs(self::types($blocks)),
                'toolbar' => null,
            ]);
        } finally {
            \Core\EditorNotes::$show = $show;
        }
        return \Core\EditorNotes::publicHtml($html);
    }

    /**
     * Seite rendern. $status ≠ 200: Seite „Nicht gefunden“ (Core\NotFound) für eine 404/410 – ohne Wartungsseite,
     * Landing-Weiterleitung und Seiten-Cache (die Vorschläge hängen von der Adresse ab), Meta-Angaben wie eine Fehlerseite (noindex).
     */
    private function render(Request $r, array $page, int $status = 200): Response
    {
        $app = app();
        $loggedIn = $app->auth->check();
        $error = $status !== 200;

        $ctx = $app->entry;
        if ($page['status'] !== 'published' && !$loggedIn && !$ctx) {
            throw new HttpException(404);
        }
        if (!$error && $app->settings->get('sys.maintenance') && !$loggedIn) {
            return $this->maintenance();
        }

        // Hauptdomain → Landing-Domain (Modus „Eigene Domain“ mit Weiterleitung) – nur für Besucher
        if (!$error && !$ctx && !$loggedIn && ($to = Landings::redirectFor($page, $r))) {
            return Response::redirect($to, 301);
        }

        $cacheKey = $ctx ? 'entry:' . $ctx['table']['handle'] . ':' . $ctx['entry']['id'] : 'page:' . $page['id'];
        $cacheable = !$error && !$loggedIn && $r->method === 'GET' && !$r->query;
        if ($cacheable && ($html = PageCache::get($cacheKey)) !== null) {
            \Core\Glossary\Glossary::$done = true;   // Glossar-Begriffe sind im Seiten-Cache schon markiert
            return $this->respond($html, false)->header('X-Cache', 'HIT');
        }

        $app->currentPage = $page;
        \Core\StructuredData::reset();
        $app->editing = $loggedIn && isset($r->query['edit']) && can('pages.edit');
        // Eingeloggte sehen den Arbeitsstand (Entwurf) – mit ?live=1 die veröffentlichte Fassung wie Besucher
        $live = $loggedIn && isset($r->query['live']) && !$app->editing;
        // Redaktionsnotizen [# … #]: Redaktion sieht sie als Hinweis (nicht in der Ansicht „wie Besucher“), sonst entfernt
        \Core\EditorNotes::$show = $loggedIn && !$live && can('pages.edit');
        $showDraft = $loggedIn && !$live;
        // Redaktion: Einträge direkt bearbeiten (Stifte in Datenlisten; auf Detailseiten Felder im Text) – nie für Besucher
        $app->dataEdit = $loggedIn && !$live;
        $app->entryEdit = $loggedIn && !$live && !$app->editing && $ctx !== null && \Core\Data\EntryEdit::canEdit($ctx['table'], $ctx['entry']);
        $blocks = Pages::blocks($page, $showDraft);
        $theme = $app->theme;

        $editor = null;
        if ($app->editing) {
            $previews = [];
            foreach ($blocks as $b) {
                $block = $theme->makeBlock($b);
                if ($block) {
                    $previews[$b['id']] = $theme->renderBlock($block);
                }
            }
            $editor = ['page' => $page, 'blocks' => $blocks, 'previews' => $previews];
            $content = '';
        } else {
            $content = $theme->renderBlocks($blocks);
        }

        $html = $theme->render(Landings::template(), [
            'page' => $page,
            'content' => $content,
            'seo' => $error ? ['title' => (string) ($page['meta_title'] ?: $page['title'])] + Seo::forError(404)
                : ($ctx ? Seo::forEntry($page, $ctx['table'], $ctx['entry']) : Seo::forPage($page)),
            'editor' => $editor,
            'extraCss' => $theme->conditionalCss($app->editing ? null : self::types($blocks)),
            'extraJs' => $app->editing ? [] : $theme->conditionalJs(self::types($blocks)),
            'toolbar' => $loggedIn ? ['page' => $page, 'editing' => $app->editing, 'dirty' => Pages::hasUnpublished($page), 'live' => $live] : null,
        ]);

        // Besucher: ein Sprite der Website mit nur den verwendeten Symbolen statt mehrerer Themen-Sprites (Core\Icons)
        if (!$loggedIn) {
            $html = \Core\Icons::siteSprite($html);
        }
        // Erweiterungen (z. B. consent_kit): Ausgabe ergänzen – vor dem Seiten-Cache, also nie besucherspezifisch
        $html = \Core\Extensions::filterHtml($html, ['page' => $page, 'editing' => $app->editing, 'loggedIn' => $loggedIn, 'status' => $status]);
        // Redaktionsnotizen: für Besucher aus der ganzen Seite entfernen (auch Einträge, Meta-Angaben, JSON-LD, Daten-Skripte);
        // Entwurfsansicht der Redaktion: als Hinweis. Im Bearbeiten-Modus nicht – die Editor-Daten brauchen den Rohtext.
        if (!\Core\EditorNotes::$show) $html = \Core\EditorNotes::publicHtml($html);
        elseif (!$app->editing) $html = \Core\EditorNotes::decorate($html);
        // Glossar (Funktion „glossary“): erstes Vorkommen der Begriffe markieren – vor dem Seiten-Cache, nie im Bearbeiten-Modus
        $html = \Core\Glossary\Glossary::page($html, $status);
        if ($cacheable) {
            PageCache::put($cacheKey, $html);
        }
        return $this->respond($html, $loggedIn, $status);
    }

    public function respond(string $html, bool $loggedIn, int $status = 200): Response
    {
        // Redaktionsnotizen [# … #] auch aus Seiten außerhalb von render() entfernen (Formularseiten /anfrage/…, Datenformulare,
        // Suche, Fehler-/Wartungsseite): dort kommen Notizen aus Einstellungen und Kit-Vorlagen (z. B. fehlende Praxisdaten)
        if (!\Core\EditorNotes::$show) $html = \Core\EditorNotes::publicHtml($html);
        // Glossar: Seiten außerhalb von render() (z. B. eigene Seiten von Erweiterungen) – einmal je Anfrage
        if (!\Core\Glossary\Glossary::$done) $html = \Core\Glossary\Glossary::page($html, $status);
        $res = new Response($html, $status);
        $res->header('Content-Security-Policy', self::csp($loggedIn));
        $res->header('Cache-Control', $loggedIn ? 'no-store, private' : 'public, max-age=0, must-revalidate');
        if (noindex_site() || Landings::current()?->noindex) {
            $res->header('X-Robots-Tag', 'noindex, nofollow');
        }
        return $res;
    }

    public static function csp(bool $loggedIn = false): string
    {
        $frames = ["'self'", 'https://www.youtube-nocookie.com', 'https://player.vimeo.com'];
        foreach ((array) (app()->theme->def['frame_hosts'] ?? []) as $settingKey) {
            $host = parse_url((string) setting($settingKey, ''), PHP_URL_HOST);
            if ($host) {
                $frames[] = 'https://' . $host;
            }
        }
        // Erweiterungen ergänzen einzelne Hosts je Website/Anfrage (z. B. consent_kit: nur nach Einwilligung), nie 'unsafe-inline'
        $x = \Core\Extensions::cspSources();
        $add = fn(string $dir) => isset($x[$dir]) ? ' ' . implode(' ', $x[$dir]) : '';
        return implode('; ', [
            "default-src 'self'",
            "img-src 'self' data: blob:" . $add('img-src'),
            "font-src 'self'" . $add('font-src'),
            // Editor.js injiziert Styles → nur für eingeloggte Nutzer
            "style-src 'self'" . ($loggedIn ? " 'unsafe-inline'" : '') . $add('style-src'),
            "script-src 'self'" . $add('script-src'),
            "connect-src 'self'" . $add('connect-src'),
            // MapLibre startet seinen Worker über eine blob:-Adresse (Kacheln kommen trotzdem nur von /proxy)
            "worker-src 'self' blob:",
            'frame-src ' . implode(' ', array_unique(array_merge($frames, $x['frame-src'] ?? []))),
            "media-src 'self'" . $add('media-src'),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' https:",
            "frame-ancestors 'self'",
        ]);
    }

    private function maintenance(): Response
    {
        $html = app()->theme->render('maintenance', ['text' => setting('sys.maintenance_text')]);
        return $this->respond($html, false, 503)->header('Retry-After', '3600');
    }

    public function error(int $code, string $message = ''): Response
    {
        $code = in_array($code, [403, 404, 405, 410, 419, 500], true) ? $code : 500;
        // 410 (Weiterleitungen: „entfernt“, Core\Redirects): Kits kennen nur 404 – gleiche Seite, anderer Status
        $shown = $code === 410 ? 404 : $code;
        if (app()->request?->isAdminPath() || app()->request?->wantsJson()) {
            if (app()->request?->wantsJson()) {
                return Response::json(['ok' => false, 'error' => $message ?: 'Fehler ' . $code], $code);
            }
        }
        // 404/410: gepflegte Seite „Nicht gefunden“ der Sprache (Core\NotFound) – Status bleibt; sonst die Kit-Vorlage error.php
        if ($shown === 404 && ($r = app()->request) && !$r->isAdminPath() && ($page = NotFound::page(null, app()->auth->check()))) {
            if (!isset($r->query['edit'])) NotFound::begin($r->path);   // im Editor: keine Adresse → Hinweis statt Vorschlägen
            try {
                return $this->render($r, $page, $code);
            } catch (\Throwable $e) {
                error_log('[404] ' . $e->getMessage());   // Rückfall: Kit-Vorlage
            } finally {
                NotFound::end();
                app()->editing = false;
            }
        }
        try {
            $html = app()->theme->render(Landings::template(), [
                'page' => ['id' => 0, 'title' => $shown === 404 ? 'Seite nicht gefunden' : 'Fehler', 'slug' => '', 'is_home' => 0, 'meta_description' => '', 'noindex' => 1],
                'content' => app()->theme->render('error', ['code' => $shown, 'message' => $message]),
                'seo' => Seo::forError($shown),
                'editor' => null, 'toolbar' => null,
            ]);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $html = '<!doctype html><meta charset="utf-8"><title>Fehler</title><p>Fehler ' . $code . '</p>';
        }
        $html = \Core\Extensions::filterHtml($html, ['page' => null, 'editing' => false, 'loggedIn' => false, 'status' => $code]);
        return $this->respond(\Core\Icons::siteSprite($html), false, $code);
    }

    public function sitemap(Request $r): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        // Landing-Domain: nur ihre Seiten (Modus „Eigene Domain“; Spiegel und „nicht in Suchmaschinen“ → leer)
        if ($l = Landings::current()) {
            foreach ($l->mode === 'own' && !$l->noindex ? $l->pages() : [] as $p) {
                if ($p['noindex']) continue;
                $mod = substr((string) ($p['published_at'] ?? $p['updated_at']), 0, 10);
                $xml .= '  <url><loc>' . e((string) $l->absUrl($p)) . '</loc>' . ($mod ? "<lastmod>$mod</lastmod>" : '') . "</url>\n";
            }
            return new Response($xml . '</urlset>' . "\n", 200, ['Content-Type' => 'application/xml; charset=utf-8']);
        }
        foreach (Pages::published() as $p) {
            // Seiten einer Landingpage mit eigener Domain stehen in deren Sitemap
            if ($p['noindex'] || Landings::owner($p)) {
                continue;
            }
            $loc = abs_url(Pages::url($p));
            $mod = substr((string) ($p['published_at'] ?? $p['updated_at']), 0, 10);
            $xml .= '  <url><loc>' . e($loc) . '</loc>' . ($mod ? "<lastmod>$mod</lastmod>" : '') . "</url>\n";
        }
        foreach (Tables::content() as $t) {                 // Eingangs-Tabellen haben nie Detailseiten
            if ($t['settings']['route'] === '' || empty($t['settings']['detail_page_id'])) continue;
            // Geteilte Tabellen: nur Einträge, deren Canonical hier liegt – eigene, fremde nur bei „Canonical: eigene Adresse“
            // bzw. wenn die Ursprungs-Website keine Detailseiten hat (sonst steht der Eintrag in deren Sitemap)
            foreach (Entries::query($t, ['status' => 'published', 'limit' => 5000, 'source' => Tables::isShared($t) ? 'site' : 'own']) as $e) {
                $loc = site_url() . Entries::url($t, $e);
                if (Tables::isShared($t) && Entries::absUrl($t, $e) !== $loc) continue;
                $mod = substr((string) ($e['updated_at'] ?? $e['published_at']), 0, 10);
                $xml .= '  <url><loc>' . e($loc) . '</loc>' . ($mod ? "<lastmod>$mod</lastmod>" : '') . "</url>\n";
            }
        }
        $xml .= '</urlset>' . "\n";
        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public function robots(Request $r): Response
    {
        $txt = noindex_site() || Landings::current()?->noindex
            ? "User-agent: *\nDisallow: /\n"
            : "User-agent: *\nDisallow: /admin\nDisallow: /anfrage/\nDisallow: /api/\nDisallow: /mcp\n\nSitemap: " . absolute_url('/sitemap.xml') . "\n";
        return new Response($txt, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
