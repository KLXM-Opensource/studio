<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Data\Entries;
use Core\Data\Shared;
use Core\Data\Tables;

/**
 * SEO-Prüfung ohne Crawl: Titel, Beschreibung, Canonical und Vorschaubild so, wie die Website sie ausgibt (Seo::forPage/forEntry),
 * für alle veröffentlichten, indexierbaren Seiten und Detailseiten. php bin/console seo:check [--all]; health() fasst zusammen.
 *  - Fehler: Live-Website für Suchmaschinen gesperrt.
 *  - Hinweise: weitere Domains ohne Weiterleitung auf die Hauptadresse, Titel über 60 bzw. Beschreibung über 160 Zeichen
 *    (Google kürzt), fehlende Beschreibung, doppelte Titel, Canonical auf eine andere Domain, Vorschaubild auf ein gelöschtes Medium.
 * Eine fehlende <h1> setzt der Kern selbst (Core\Headings), Bilder im ersten Block laden mit Vorrang (Media::$autoEager).
 */
final class SeoCheck
{
    public const TITLE_MAX = 60;
    public const DESC_MAX = 160;

    /** Systemprüfung: [Text => true | false (Fehler) | null (Hinweis)] */
    public static function health(): array
    {
        $out = [];
        if (environment() === 'production' && (bool) app()->settings->get('sys.noindex')) {
            $out[__('Suchmaschinen: Die Live-Website ist gesperrt („Suchmaschinen aussperren“ in den Grundeinstellungen)')] = false;
            return $out;
        }
        if (noindex_site()) return [];
        foreach (self::site() as $t) $out[$t] = null;
        $n = count(self::pages());
        if ($n) $out[__('SEO: {n} Hinweise zu Titeln, Beschreibungen und Vorschaubildern – php bin/console seo:check', ['n' => $n])] = null;
        return $out;
    }

    /** Hinweise zur ganzen Website (Domains) */
    public static function site(): array
    {
        $out = [];
        $hosts = array_values(array_filter(site()->hosts(), fn($h) => !Domains::isLocal((string) $h)));
        if (count($hosts) > 1 && !Domains::redirectOn()) {
            $out[] = __('SEO: Die Domains {hosts} zeigen dieselben Inhalte – Weiterleitung auf die Hauptadresse {main} einschalten', ['hosts' => implode(', ', array_slice($hosts, 1)), 'main' => $hosts[0]]);
        }
        $set = (string) app()->settings->get('sys.site_url', '');
        $host = (string) parse_url($set, PHP_URL_HOST);
        if ($host !== '' && $hosts && !in_array(strtolower($host), array_map('strtolower', $hosts), true)) {
            $out[] = __('SEO: Die kanonische Adresse {url} gehört nicht zu den Domains dieser Website', ['url' => $set]);
        }
        return $out;
    }

    /**
     * Hinweise je Seite bzw. Detailseite
     * @return list<array{url: string, text: string}>
     */
    public static function pages(): array
    {
        $items = [];   // [url, lang, seo, ogMissing, foreign, page]
        foreach (Pages::published() as $p) {
            if (!empty($p['noindex']) || PageAccess::restricted($p) || Landings::owner($p) || PageTemplates::isTemplatePage($p)) continue;
            $og = (int) ($p['og_image'] ?? 0);
            $items[] = [Pages::plainUrl($p), (string) ($p['lang'] ?? ''), Seo::forPage($p, false), $og && !Media::find($og), false, true];
        }
        foreach (Tables::content() as $t) {
            if ($t['settings']['route'] === '' || empty($t['settings']['detail_page_id']) || !empty($t['settings']['noindex']) || PageAccess::tableRestricted($t)) continue;
            $dp = Pages::find((int) $t['settings']['detail_page_id']);
            if (!$dp || $dp['status'] !== 'published' || !empty($dp['noindex'])) continue;
            $q = ['status' => 'published', 'limit' => 2000, 'source' => Tables::isShared($t) ? 'site' : 'own'] + (Lang::multi() ? ['lang' => 'all'] : []);
            foreach (Entries::query($t, $q) as $e) {
                if (!($u = Entries::url($t, $e))) continue;
                $items[] = [$u, (string) ($e['lang'] ?? ''), Seo::forEntry($dp, $t, $e), false, Tables::isShared($t) && Shared::isForeign($t, $e), false];
            }
        }
        $own = strtolower((string) parse_url(site_url(), PHP_URL_HOST));
        $out = [];
        $titles = [];
        foreach ($items as [$url, $lang, $seo, $ogMissing, $foreign, $isPage]) {
            $title = (string) ($seo['title'] ?? '');
            $desc = trim((string) ($seo['description'] ?? ''));
            if (mb_strlen($title) > self::TITLE_MAX) $out[] = ['url' => $url, 'text' => $isPage
                ? __('Titel hat {n} Zeichen (Google zeigt etwa {max}) – „Titel für Suchmaschinen“ in den Seiteneinstellungen setzen', ['n' => mb_strlen($title), 'max' => self::TITLE_MAX])
                : __('Titel hat {n} Zeichen (Google zeigt etwa {max})', ['n' => mb_strlen($title), 'max' => self::TITLE_MAX])];
            if ($desc === '') $out[] = ['url' => $url, 'text' => __('Keine Beschreibung für Suchmaschinen')];
            elseif (mb_strlen($desc) > self::DESC_MAX) $out[] = ['url' => $url, 'text' => __('Beschreibung hat {n} Zeichen (Google zeigt etwa {max})', ['n' => mb_strlen($desc), 'max' => self::DESC_MAX])];
            $ch = strtolower((string) parse_url((string) ($seo['canonical'] ?? ''), PHP_URL_HOST));
            if (!$foreign && $ch !== '' && $own !== '' && $ch !== $own) $out[] = ['url' => $url, 'text' => __('Canonical zeigt auf eine andere Domain: {host}', ['host' => $ch])];
            if ($ogMissing) $out[] = ['url' => $url, 'text' => __('Vorschaubild für Social Media ist gelöscht – in den Seiteneinstellungen neu wählen')];
            if ($title !== '') $titles[$lang . "\0" . $title][] = $url;
        }
        // Doppelte Titel: ein Hinweis je Gruppe (z. B. Terminserien), mit bis zu drei weiteren Adressen
        foreach ($titles as $key => $urls) {
            if (count($urls) < 2) continue;
            $more = array_slice($urls, 1, 3);
            $out[] = ['url' => $urls[0], 'text' => __('Gleicher Titel „{title}“ auf {n} Seiten, z. B. {other}', ['title' => explode("\0", $key, 2)[1], 'n' => count($urls), 'other' => implode(', ', $more) . (count($urls) > 4 ? ' …' : '')])];
        }
        usort($out, fn($a, $b) => strcmp($a['url'], $b['url']));
        return $out;
    }
}
