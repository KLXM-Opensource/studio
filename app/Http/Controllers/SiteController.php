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
        if ($wait = $this->onboarding()) return $wait;
        // Landing-Domain (Core\Landings): „/“ zeigt die Einstiegsseite der Landingpage
        if ($l = Landings::current()) return $this->landing($r, $l, '');
        $page = Pages::home() ?? throw new HttpException(404);
        return $this->render($r, $page);
    }

    /** Seiten nach vollständigem Pfad, Detailseiten von Datentabellen, Weiterleitung alter Kurz-Adressen */
    public function page(Request $r, string $path): Response
    {
        if ($wait = $this->onboarding()) return $wait;
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
        // Wie ausgegeben: Erweiterungen lösen Blöcke auf (Extension::blocksResolve, z. B. gemeinsame Bausteine → Typ des Originals),
        // sonst fehlte das bedingte CSS/JS des tatsächlich gerenderten Blocktyps
        $blocks = \Core\Extensions::resolveBlocks($blocks, ['page' => app()->currentPage, 'draft' => false, 'purpose' => 'render']);
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
        // Style-Editor der Website: persönliche Vorschau ungespeicherter Kopf-/Fußvarianten (nur angemeldet, Recht design.edit)
        if ($loggedIn) \Core\Design::applySessionPreview();

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

        // Geschützter Bereich (Erweiterung, Core\PageAccess): vor dem Seiten-Cache prüfen; erlaubte Aufrufe nie cachen
        $private = false;
        if (!$error && !$loggedIn && (\Core\PageAccess::restricted($page) || ($ctx && \Core\PageAccess::tableRestricted($ctx['table'])))) {
            if ($deny = \Core\PageAccess::deny($page, $r, $ctx['table'] ?? null)) return $deny;
            $private = true;
            \Core\PageAccess::$allowedEntry = $ctx !== null;
        }

        $cacheKey = $ctx ? 'entry:' . $ctx['table']['handle'] . ':' . $ctx['entry']['id'] : 'page:' . $page['id'];
        $cacheable = !$error && !$loggedIn && !$private && $r->method === 'GET' && !$r->query;
        if ($cacheable && ($html = PageCache::get($cacheKey)) !== null) {
            \Core\Glossary\Glossary::$done = true;   // Glossar-Begriffe sind im Seiten-Cache schon markiert
            return $this->respond($html, false)->header('X-Cache', 'HIT');
        }

        $app->currentPage = $page;
        \Core\StructuredData::reset();
        // Modus „Inhalt“ (?edit=inhalt): Inhaltsblöcke des Eintrags im Seiten-Editor – Recht am Eintrag statt pages.edit
        $app->contentEdit = $loggedIn && $ctx && ($r->query['edit'] ?? '') === 'inhalt' ? \Core\Data\EntryBlocks::contentEdit($ctx, Pages::blocks($page, true)) : null;
        $app->editing = $app->contentEdit !== null || ($loggedIn && isset($r->query['edit']) && ($r->query['edit'] ?? '') !== 'inhalt' && can('pages.edit'));
        // Bearbeitungssperre (Core\EditLock): bearbeitet jemand anderes diese Seite, nur Ansehen (Hinweis und „Übernehmen“ in der Leiste)
        if ($app->editing && $app->contentEdit === null && !empty($page['id']) && \Core\EditLock::blockedFor('page', (string) (int) $page['id'])) $app->editing = false;
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
        if ($app->contentEdit) {
            // Vorlage davor/danach als feste Vorschau (wie für Besucher), dazwischen der Editor mit den Blöcken des Eintrags
            $ce = $app->contentEdit;
            $app->editing = false;
            $before = $theme->renderBlocks(array_slice($blocks, 0, $ce['at']));
            $after = $ce['at'] < count($blocks) ? $theme->renderBlocks(array_slice($blocks, $ce['at'] + 1)) : '';
            $app->editing = true;
            $own = \Core\Data\EntryBlocks::items($ctx['entry'][$ce['field']] ?? null);
            $previews = [];
            foreach ($own as $b) {
                if (($html = $theme->previewBlock($b)) !== null) $previews[$b['id']] = $html;
            }
            $editor = ['page' => $page, 'blocks' => $own, 'previews' => $previews, 'content' => $ce + ['before' => $before, 'after' => $after]];
            $content = '';
        } elseif ($app->editing) {
            $previews = [];
            foreach ($blocks as $b) {
                if (($html = $theme->previewBlock($b)) !== null) $previews[$b['id']] = $html;   // aufgelöst wie EditorController::preview
            }
            $editor = ['page' => $page, 'blocks' => $blocks, 'previews' => $previews];
            $content = '';
        } else {
            $content = $theme->renderBlocks($blocks);
        }

        // Angemeldet auf der Website unterwegs: „online“ mit Ort (Core\Presence::ONLINE) – Besucher nie
        if ($loggedIn) \Core\Http\Controllers\Admin\PresenceController::seen((string) ($ctx['entry']['title'] ?? $page['title'] ?? ''), (string) $r->path);
        $html = $theme->render(Landings::template(), [
            'page' => $page,
            'content' => $content,
            'seo' => $error ? ['title' => (string) ($page['meta_title'] ?: $page['title'])] + Seo::forError(404)
                : ($ctx ? Seo::forEntry($page, $ctx['table'], $ctx['entry']) : Seo::forPage($page)),
            'editor' => $editor,
            'extraCss' => $theme->conditionalCss($app->editing ? null : self::types($blocks)),
            'extraJs' => $app->editing ? $theme->editorJs() : $theme->conditionalJs(self::types($blocks)),
            'toolbar' => $loggedIn ? ['page' => $page, 'editing' => $app->editing, 'dirty' => Pages::hasUnpublished($page), 'live' => $live] : null,
        ]);

        // Besucher: ein Sprite der Website mit nur den verwendeten Symbolen statt mehrerer Themen-Sprites (Core\Icons)
        if (!$loggedIn) {
            $html = \Core\Icons::siteSprite($html);
        } else {
            $html = \Core\Icons::applyStyle($html);   // Redaktion: gewählter Symbolstil, Leiste bleibt duotone
        }
        // Push-Benachrichtigungen (Core\Push\Visitor): Banner und Glocke – für alle gleich, das Skript entscheidet im Browser
        if (!$app->editing) $html = \Core\Push\Visitor::inject($html);
        // PDF-Links: Auswahl „Anzeigen | Herunterladen“ (Core\PdfLinks) – Skript nur, wenn die Seite solche Links hat
        if (!$app->editing) $html = \Core\PdfLinks::inject($html);
        // RSS-Feed der Tabelle (Detailseite bzw. Seite unter der URL-Basis) im Kopf verlinken (Core\Data\Feed)
        if (!$app->editing && !$error) $html = \Core\Data\Feed::inject($html, $page, $ctx['table'] ?? null);
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
        return $this->respond($html, $loggedIn, $status, $private);
    }

    /** $private: Inhalt nur für diese Sitzung (geschützter Bereich) – nicht in geteilten Caches oder dem Service Worker */
    public function respond(string $html, bool $loggedIn, int $status = 200, bool $private = false): Response
    {
        // Redaktionsnotizen [# … #] auch aus Seiten außerhalb von render() entfernen (Formularseiten /anfrage/…, Datenformulare,
        // Suche, Fehler-/Wartungsseite): dort kommen Notizen aus Einstellungen und Kit-Vorlagen (z. B. fehlende Praxisdaten)
        if (!\Core\EditorNotes::$show) $html = \Core\EditorNotes::publicHtml($html);
        // Glossar: Seiten außerhalb von render() (z. B. eigene Seiten von Erweiterungen) – einmal je Anfrage
        if (!\Core\Glossary\Glossary::$done) $html = \Core\Glossary\Glossary::page($html, $status);
        $res = new Response($html, $status);
        $res->header('Content-Security-Policy', self::csp($loggedIn));
        $res->header('Cache-Control', $loggedIn || $private ? 'no-store, private' : 'public, max-age=0, must-revalidate');
        if ($private) $res->header('X-Robots-Tag', 'noindex, nofollow');
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

    /** Erststart noch offen (Core\Onboarding): Angemeldete in den Willkommen-Bildschirm, Besucher „wird eingerichtet“ (503) */
    private function onboarding(): ?Response
    {
        if (!\Core\Onboarding::pending()) return null;
        if (app()->auth->check()) return Response::redirect(url('/admin/willkommen'));
        $html = \Core\Theme::capture(ROOT . '/app/Views/setup-pending.php', ['lang' => \Core\Lang::current()]);
        return (new Response($html, 503))->header('Retry-After', '3600')->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
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
        $urls = [];   // [{loc, lastmod?, images?: [url, …]}] – Erweiterungen ändern sie über den Filter „sitemap.urls“
        // Datum mit Uhrzeit (W3C) wie andere Systeme; nur gültige Werte
        $mod = fn(?string $d) => ($t = strtotime((string) $d)) ? date('c', $t) : '';
        // Landing-Domain: nur ihre Seiten (Modus „Eigene Domain“; Spiegel und „nicht in Suchmaschinen“ → leer)
        if ($l = Landings::current()) {
            foreach ($l->mode === 'own' && !$l->noindex ? $l->pages() : [] as $p) {
                if ($p['noindex'] || \Core\PageAccess::restricted($p)) continue;
                $urls[] = ['loc' => (string) $l->absUrl($p), 'lastmod' => $mod($p['published_at'] ?? $p['updated_at']), 'images' => self::sitemapImages(self::pageMediaIds($p))];
            }
            return $this->sitemapXml($urls);
        }
        foreach (Pages::published() as $p) {
            // Seiten einer Landingpage mit eigener Domain stehen in deren Sitemap
            if ($p['noindex'] || Landings::owner($p) || \Core\PageAccess::restricted($p)) {
                continue;
            }
            $urls[] = ['loc' => abs_url(Pages::url($p)), 'lastmod' => $mod($p['published_at'] ?? $p['updated_at']), 'images' => self::sitemapImages(self::pageMediaIds($p))];
        }
        foreach (Tables::content() as $t) {                 // Eingangs-Tabellen haben nie Detailseiten
            if ($t['settings']['route'] === '' || empty($t['settings']['detail_page_id']) || !empty($t['settings']['noindex'])
                || \Core\PageAccess::tableRestricted($t)) continue;
            // Geteilte Tabellen: nur Einträge, deren Canonical hier liegt – eigene, fremde nur bei „Canonical: eigene Adresse“
            // bzw. wenn die Ursprungs-Website keine Detailseiten hat (sonst steht der Eintrag in deren Sitemap)
            $mediaFields = array_column(array_filter($t['fields'], fn($f) => ($f['type'] ?? '') === 'media'), 'name');
            foreach (Entries::query($t, ['status' => 'published', 'limit' => 5000, 'source' => Tables::isShared($t) ? 'site' : 'own']) as $e) {
                $loc = site_url() . Entries::url($t, $e);
                if (Tables::isShared($t) && Entries::absUrl($t, $e) !== $loc) continue;
                $urls[] = ['loc' => $loc, 'lastmod' => $mod($e['updated_at'] ?? $e['published_at']),
                    'images' => self::sitemapImages(array_map(fn($f) => (int) ($e[$f] ?? 0), $mediaFields))];
            }
        }
        return $this->sitemapXml($urls);
    }

    /** Medien einer Seite: Vorschaubild (Seiteneinstellungen), dann Medienfelder der Blöcke in Reihenfolge (auch in Gruppen/Listen) */
    private static function pageMediaIds(array $page): array
    {
        static $byType = null;
        if ($byType === null) {
            $byType = [];
            $walk = function (array $fields) use (&$walk): array {
                $n = [];
                foreach ($fields as $f) {
                    if (($f['type'] ?? '') === 'media') $n[] = (string) $f['name'];
                    if (!empty($f['fields']) && is_array($f['fields'])) $n = array_merge($n, $walk($f['fields']));
                }
                return $n;
            };
            foreach (app()->theme->blocks() as $type => $def) $byType[$type] = array_flip($walk((array) ($def['fields'] ?? [])));
        }
        $ids = [(int) ($page['og_image'] ?? 0)];
        $collect = function ($data, array $names) use (&$collect, &$ids): void {
            if (!is_array($data) || count($ids) > 12) return;
            foreach ($data as $k => $v) {
                if (is_string($k) && isset($names[$k]) && is_numeric($v)) $ids[] = (int) $v;
                elseif (is_array($v)) $collect($v, $names);
            }
        };
        try {
            foreach (Pages::blocks($page) as $b) if (!empty($byType[$b['type'] ?? ''])) $collect($b['data'] ?? [], $byType[$b['type']]);
        } catch (\Throwable) {}
        return $ids;
    }

    /** Bild-Adressen für die Sitemap (Bilder bzw. Standbild eines Videos; geschützte Medien nie) – höchstens 5 je Adresse */
    private static function sitemapImages(array $ids): array
    {
        $out = [];
        foreach (array_unique(array_filter($ids)) as $id) {
            $m = \Core\Media::find((int) $id);
            if (!$m || \Core\MediaPools::mediaProtected($m) || !($still = \Core\Media::stillOf($m))) continue;
            $out[site_url() . \Core\Media::url($still, 1600)] = true;   // gleiche Datei (z. B. Bild und Videoposter) nur einmal
            if (count($out) >= 5) break;
        }
        return array_keys($out);
    }

    /**
     * sitemap.xml aus Adressen – vorher Filter „sitemap.urls“ (Extension::filter). Mit Bildern (image:image, Google-Erweiterung)
     * und Stylesheet /sitemap.xsl für eine lesbare Ansicht im Browser (Suchmaschinen ignorieren es).
     */
    private function sitemapXml(array $urls): Response
    {
        $urls = \Core\Extensions::filter('sitemap.urls', $urls, ['host' => (string) (app()->request?->host() ?? '')]);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<?xml-stylesheet type="text/xsl" href="' . e(url('/sitemap.xsl')) . '"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach (is_array($urls) ? $urls : [] as $u) {
            if (!is_array($u) || trim((string) ($u['loc'] ?? '')) === '') continue;
            $m = (string) ($u['lastmod'] ?? '');
            $img = '';
            foreach ((array) ($u['images'] ?? []) as $i) if (is_string($i) && $i !== '') $img .= '<image:image><image:loc>' . e($i) . '</image:loc></image:image>';
            $xml .= '  <url><loc>' . e((string) $u['loc']) . '</loc>' . ($m !== '' ? '<lastmod>' . e($m) . '</lastmod>' : '') . $img . "</url>\n";
        }
        return new Response($xml . '</urlset>' . "\n", 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=0, must-revalidate']);
    }

    /** /sitemap.xsl – lesbare Ansicht der Sitemap im Browser (Tabelle: Adresse, Bilder, zuletzt geändert); Suchmaschinen ignorieren es */
    public function sitemapXsl(Request $r): Response
    {
        $t = fn(string $s, array $a = []) => htmlspecialchars(lt($s, $a), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars(site_name(), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $lang = htmlspecialchars(Lang::current(), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xsl = <<<XSL
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" exclude-result-prefixes="s image">
<xsl:output method="html" encoding="UTF-8" indent="yes"/>
<xsl:template match="/">
<html lang="{$lang}"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/><meta name="robots" content="noindex"/>
<title>{$t('Sitemap')} · {$name}</title>
<style>
:root{color-scheme:light dark;--bg:#f6f5f2;--card:#fff;--ink:#1f1f1e;--muted:#6b6862;--line:#e3e0da;--ac:#314164}
@media (prefers-color-scheme:dark){:root{--bg:#16191f;--card:#1f232b;--ink:#f1eee8;--muted:#a9a49b;--line:#343a46;--ac:#9fb3dd}}
body{margin:0;padding:clamp(16px,4vw,48px);background:var(--bg);color:var(--ink);font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
h1{margin:0 0 4px;font-size:clamp(1.6rem,4vw,2.4rem);letter-spacing:-.02em}
p{margin:0 0 20px;color:var(--muted)}
.wrap{overflow-x:auto;border:1px solid var(--line);border-radius:14px;background:var(--card)}
table{width:100%;border-collapse:collapse;font-size:.95rem}
th,td{padding:10px 14px;text-align:left;border-bottom:1px solid var(--line);vertical-align:top}
th{position:sticky;top:0;background:var(--card);font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
tr:last-child td{border-bottom:0}
td.n{color:var(--muted);font-variant-numeric:tabular-nums;white-space:nowrap}
a{color:var(--ac);text-decoration:none;word-break:break-all}a:hover{text-decoration:underline}
</style></head><body>
<h1>{$t('Sitemap')} · {$name}</h1>
<p><xsl:value-of select="count(s:urlset/s:url)"/> {$t('Adressen')} · <xsl:value-of select="count(s:urlset/s:url/image:image)"/> {$t('Bilder')} · {$t('Diese Seite ist für Suchmaschinen gedacht.')}</p>
<div class="wrap"><table><thead><tr><th>{$t('Adresse')}</th><th>{$t('Bilder')}</th><th>{$t('Zuletzt geändert')}</th></tr></thead><tbody>
<xsl:for-each select="s:urlset/s:url"><tr>
<td><a href="{loc}"><xsl:value-of select="s:loc"/></a></td>
<td class="n"><xsl:value-of select="count(image:image)"/></td>
<td class="n"><xsl:value-of select="concat(substring(s:lastmod,9,2),'.',substring(s:lastmod,6,2),'.',substring(s:lastmod,1,4),' ',substring(s:lastmod,12,5))"/></td>
</tr></xsl:for-each>
</tbody></table></div></body></html>
</xsl:template>
</xsl:stylesheet>
XSL;
        $xsl = str_replace('href="{loc}"', 'href="{s:loc}"', $xsl);
        return new Response($xsl, 200, ['Content-Type' => 'text/xsl; charset=utf-8', 'X-Robots-Tag' => 'noindex', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function robots(Request $r): Response
    {
        return new Response(\Core\Indexing::robotsTxt(), 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=0, must-revalidate']);   // nie in Proxy-Caches festhalten: hängt an Einstellungen (Aussperren, Staging, KI-Crawler)   // Core\Indexing
    }

    /** /llms.txt – Übersicht für KI-Assistenten (Grundeinstellungen → Indexierung & Crawler) */
    public function llms(Request $r): Response
    {
        if (!\Core\Indexing::llmsEnabled()) throw new HttpException(404);
        return new Response(\Core\Indexing::llmsTxt(), 200, ['Content-Type' => 'text/markdown; charset=utf-8', 'X-Robots-Tag' => 'noindex', 'Cache-Control' => 'public, max-age=0, must-revalidate']);
    }
}
