<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

use Core\Http\Controllers\SiteController;
use Core\Http\Request;
use Core\Http\Response;
use Core\Lang;
use Core\Pages;
use Core\RateLimiter;
use Core\Seo;

/**
 * Website-Routen der Erweiterung:
 *   GET  /check/api/{spf|dmarc|dkim|mail|hosting|web}?domain=…[&selector=…][&smtp=0][&lang=en]   Abschnitt der Domain-Analyse
 *   GET  /check/api/tls?host=…&service=https|smtps|submission|smtp|imaps|pop3s                    SSL/TLS-Checker
 *   POST /check/api/spf-analyse | /check/api/dmarc-analyse  (record, domain optional)              Eingefügten Eintrag prüfen
 *   GET  /check (Konfiguration 'path')                                                            eigenständige Seite (Verwaltung → KLXM Check)
 *
 * Keine Sitzung, keine Cookies. Ratenbegrenzung je HMAC der IP (Core\RateLimiter, Tabelle hits) plus Tagesgrenze je Website;
 * Ergebnisse je Domain kurz zwischengespeichert. Keine Protokollierung der Domains – nur anonyme Zähler je Tag und Art.
 */
final class Controller
{
    public function api(Request $r, string $section): Response
    {
        if (!Check::on()) return self::json(['ok' => false, 'error' => 'Not found'], 404);
        $this->lang($r);
        $analyse = in_array($section, ['spf-analyse', 'dmarc-analyse'], true);
        if (!in_array($section, [...Check::SECTIONS, 'tls'], true) && !$analyse) return self::json(['ok' => false, 'error' => lt('Unbekannte Prüfung.')], 404);
        if ($analyse !== $r->isPost()) return self::json(['ok' => false, 'error' => 'Method'], 405);
        if (!self::sameSite($r)) return self::json(['ok' => false, 'error' => lt('Anfragen sind nur von dieser Website aus möglich.')], 403);

        // Ratenbegrenzung (vor jeder Arbeit)
        $lim = Check::limits();
        $limiter = new RateLimiter(app()->db);
        $key = 'kcheck:' . substr(hash_hmac('sha256', 'klxm-check|' . $r->ip(), (string) app()->config->get('app_key', CMS_NAME)), 0, 24);
        if ($limiter->tooMany($key, $lim['minute'], 60)) {
            return self::json(['ok' => false, 'error' => lt('Zu viele Prüfungen in kurzer Zeit – bitte warte eine Minute.'), 'retry' => 60], 429)->header('Retry-After', '60');
        }
        if ($limiter->tooMany($key, $lim['day'], 86400)) {
            return self::json(['ok' => false, 'error' => lt('Tageslimit für Prüfungen erreicht – bitte morgen wieder.')], 429)->header('Retry-After', '3600');
        }
        if ($limiter->tooMany('kcheck:site', $lim['site_day'], 86400)) {
            return self::json(['ok' => false, 'error' => lt('Das Werkzeug ist heute ausgelastet – bitte später erneut versuchen.')], 503)->header('Retry-After', '3600');
        }

        @set_time_limit(60);
        try {
            if ($analyse) {
                $record = (string) ($r->post['record'] ?? '');
                $domainIn = trim((string) ($r->post['domain'] ?? ''));
                $domain = $domainIn !== '' ? Domain::normalize($domainIn) : null;
                $cacheKey = $section . '|' . Lang::current() . '|' . $domain . '|' . $record;
            } elseif ($section === 'tls') {
                $domain = Domain::normalize((string) ($r->query['host'] ?? ''));
                $service = (string) ($r->query['service'] ?? 'https');
                if (!isset(Tls::SERVICES[$service])) throw new CheckException(lt('Unbekannter Dienst.'));
                $cacheKey = 'tls|' . Lang::current() . '|' . $domain . '|' . $service;
            } else {
                $domain = Domain::normalize((string) ($r->query['domain'] ?? ''));
                $selector = strtolower(trim((string) ($r->query['selector'] ?? '')));
                if ($selector !== '' && !Dkim::validSelector($selector)) throw new CheckException(lt('Der Selektor enthält ungültige Zeichen.'));
                $smtp = Check::smtpProbe() && ($r->query['smtp'] ?? '1') !== '0';
                $cacheKey = $section . '|' . Lang::current() . '|' . $domain . '|' . ($section === 'dkim' ? $selector : '') . ($section === 'mail' ? (int) $smtp : '');
            }
            $limiter->hit($key);
            $limiter->hit('kcheck:site');
            if ($cached = Check::cacheGet($cacheKey)) {
                return self::json($cached + ['cached' => true]);
            }
            $dns = new Dns($section === 'dkim' ? 25 : 20);
            $res = match ($section) {
                'spf' => Spf::check($domain, $dns),
                'dmarc' => Dmarc::check($domain, $dns),
                'dkim' => Dkim::check($domain, $dns, $selector ?? ''),
                'mail' => Mail::check($domain, $dns, $smtp ?? false),
                'hosting' => Hosting::check($domain, $dns),
                'web' => Web::check($domain, $dns),
                'tls' => Tls::check($domain, $service ?? 'https', $dns),
                'spf-analyse' => Spf::analyse($record ?? '', $domain, $dns),
                'dmarc-analyse' => Dmarc::analyse($record ?? '', $domain, $dns),
            };
            if ($dns->failures && !$analyse) $res->info(lt('{n} DNS-Abfrage(n) sind fehlgeschlagen – Ergebnis evtl. unvollständig.', ['n' => $dns->failures]));
            $out = $res->toArray() + ['domain' => $domain ?? '', 'display' => $domain ? Domain::display($domain) : '', 'checked' => date('c')];
            Check::cachePut($cacheKey, $out);
            Check::count($section);
            return self::json($out);
        } catch (CheckException $e) {
            return self::json(['ok' => false, 'error' => $e->getMessage()], $e->status);
        } catch (\Throwable $e) {
            // Ohne Domain und ohne Nachricht protokollieren (Meldungen können Namen enthalten)
            error_log('[klxm_check] ' . $section . ': ' . get_class($e) . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
            return self::json(['ok' => false, 'error' => lt('Die Prüfung ist unerwartet fehlgeschlagen. Bitte versuche es später noch einmal.')], 500);
        }
    }

    /** Eigenständige Seite (Schalter unter Verwaltung → KLXM Check); eine Seite des CMS unter derselben Adresse hat Vorrang */
    public function page(Request $r): Response
    {
        $path = trim($r->path, '/');
        $site = new SiteController();
        if (!Check::pageOn() || Pages::byPath($path)) return $site->page($r, $path);
        $app = app();
        if ($app->settings->get('sys.maintenance') && !$app->auth->check()) return $site->home($r);
        $theme = $app->theme;
        $wrap = (string) ($theme->def['container_class'] ?? 'wrap');
        $title = lt('Domain- & Mail-Check');
        $content = '<section class="kc-page section"><div class="' . e($wrap) . '">'
            . Check::render(['title' => $title, 'intro' => lt('SPF, DMARC, DKIM, Mailserver, Hosting, Website-Sicherheit und SSL/TLS deiner Domain prüfen – dazu Generatoren für SPF und DMARC.'), 'level' => 1])
            . '</div></section>';
        $pseudo = ['id' => 0, 'title' => $title, 'slug' => trim(Check::path(), '/'), 'is_home' => 0, 'meta_description' => '', 'noindex' => 0, 'status' => 'published',
            'lang' => Lang::current() === Lang::default() ? null : Lang::current(), 'type' => 'check'];
        $seo = Seo::forError(200);
        $seo['title'] = $title;
        $seo['noindex'] = false;
        $html = $theme->render('layout', ['page' => $pseudo, 'content' => $content, 'seo' => $seo, 'editor' => null, 'toolbar' => null, 'extraCss' => [], 'extraJs' => []]);
        return $site->respond(\Core\Icons::siteSprite($html), false)->header('Referrer-Policy', 'same-origin');
    }

    /** Sprache der Seite (?lang= bzw. Feld lang) – nur aktive Sprachen */
    private function lang(Request $r): void
    {
        $l = (string) ($r->post['lang'] ?? $r->query['lang'] ?? '');
        if ($l !== '' && $l !== Lang::default() && Lang::multi() && Lang::valid($l)) app()->lang = $l;
    }

    /**
     * Nur Aufrufe von dieser Website: Sec-Fetch-Site „cross-site“ wird abgelehnt; bei POST muss Origin/Referer (falls
     * vorhanden) zum eigenen Host passen. Ohne diese Angaben (z. B. curl) greift nur die Ratenbegrenzung.
     */
    private static function sameSite(Request $r): bool
    {
        $sfs = strtolower((string) ($r->server['HTTP_SEC_FETCH_SITE'] ?? ''));
        if ($sfs === 'cross-site') return false;
        if (!$r->isPost()) return true;
        $o = (string) ($r->server['HTTP_ORIGIN'] ?? $r->server['HTTP_REFERER'] ?? '');
        if ($o === '' || $o === 'null') return $o === '';
        return strcasecmp((string) parse_url($o, PHP_URL_HOST) . (($p = parse_url($o, PHP_URL_PORT)) ? ':' . $p : ''), $r->host()) === 0;
    }

    private static function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status)->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'")->header('Referrer-Policy', 'no-referrer')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
