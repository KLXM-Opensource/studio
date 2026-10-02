<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Data\Entries;
use Core\Data\Tables;

/**
 * Indexierung & Crawler (Grundeinstellungen → „Indexierung & Crawler“):
 *  - robots.txt: Standardregeln (Verwaltung, Formulare, API, MCP gesperrt; Sitemap) + KI-Crawler + eigene Zeilen
 *  - KI-Crawler (sys.ai_crawlers): 'allow' (alle erlaubt), 'training' (Bots zum Trainieren gesperrt, Such-/Antwort-Bots
 *    erlaubt), 'all' (alle KI-Bots gesperrt) – Regeln der Anbieter, keine technische Sperre
 *  - /llms.txt (sys.llms_txt): kurze Übersicht der indexierbaren Seiten für KI-Assistenten (llmstxt.org)
 * Je Seite „Nicht indexieren“ (pages.noindex) und je Datentabelle (settings.noindex): noindex + nicht in Sitemap/llms.txt.
 * Website ausgesperrt (sys.noindex, Staging, Landingpage mit noindex) → „Disallow: /“ für alle.
 */
final class Indexing
{
    /** Bots, die Inhalte zum Trainieren von KI-Modellen sammeln */
    public const TRAINING_BOTS = ['GPTBot', 'ClaudeBot', 'anthropic-ai', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'Meta-ExternalAgent',
        'Bytespider', 'cohere-training-data-crawler', 'Diffbot', 'Omgilibot'];
    /** Bots, die für Antworten/Suche in KI-Assistenten abrufen (Quellenangaben, Links) */
    public const ANSWER_BOTS = ['OAI-SearchBot', 'ChatGPT-User', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'DuckAssistBot', 'MistralAI-User'];

    public static function aiMode(): string
    {
        $m = (string) app()->settings->get('sys.ai_crawlers', 'allow');
        return in_array($m, ['allow', 'training', 'all'], true) ? $m : 'allow';
    }

    /** Felder für die Grundeinstellungen */
    public static function fields(): array
    {
        return [
            ['name' => 'sys.ai_crawlers', 'label' => 'KI-Crawler', 'type' => 'select', 'required' => true, 'default' => 'allow',
                'options' => ['allow' => 'Alle erlauben (wie Suchmaschinen)', 'training' => 'KI-Training sperren, Antworten mit Quellenlink erlauben', 'all' => 'Alle KI-Crawler sperren'],
                'help' => '„KI-Training sperren“ hält z. B. GPTBot, ClaudeBot, Google-Extended und CCBot fern; Assistenten, die beim Antworten auf Ihre Seiten verlinken (z. B. ChatGPT-Suche, Perplexity), bleiben erlaubt. Seriöse Anbieter halten sich an die robots.txt – eine technische Sperre ist es nicht.'],
            ['name' => 'sys.llms_txt', 'label' => 'llms.txt anbieten (Übersicht für KI-Assistenten)', 'type' => 'bool', 'default' => false,
                'help' => 'Unter /llms.txt eine kurze Liste der wichtigsten Seiten mit Beschreibung (Standard llmstxt.org). Nicht bei „Alle KI-Crawler sperren“.'],
            ['name' => 'sys.robots_extra', 'label' => 'Eigene Regeln für die robots.txt (optional)', 'type' => 'textarea', 'rows' => 4, 'max' => 4000,
                'placeholder' => "User-agent: *\nDisallow: /intern/",
                'help' => 'Werden unten angefügt. Erlaubt: User-agent, Allow, Disallow, Crawl-delay, Sitemap und Kommentare (#). Seiten einzeln sperren Sie besser über „Nicht indexieren“ in den Seiteneinstellungen.'],
        ];
    }

    /** Eigene Zeilen: nur bekannte Anweisungen und Kommentare, ohne Steuerzeichen */
    public static function extraLines(): array
    {
        $out = [];
        foreach (preg_split('~\R~', (string) app()->settings->get('sys.robots_extra', '')) as $l) {
            $l = trim(preg_replace('~[\x00-\x1F\x7F]~', '', $l));
            if ($l === '' ? ($out && end($out) !== '') : (str_starts_with($l, '#') || preg_match('~^(User-agent|Allow|Disallow|Crawl-delay|Sitemap)\s*:~i', $l))) $out[] = mb_substr($l, 0, 500);
        }
        while ($out && end($out) === '') array_pop($out);   // Leerzeilen trennen Gruppen – nicht doppelt, nicht am Ende
        return $out;
    }

    /** Inhalt der robots.txt */
    public static function robotsTxt(): string
    {
        if (noindex_site() || Landings::current()?->noindex) return "User-agent: *\nDisallow: /\n";
        $txt = "User-agent: *\nDisallow: /admin\nDisallow: /anfrage/\nDisallow: /api/\nDisallow: /mcp\n";
        $mode = self::aiMode();
        $bots = match ($mode) { 'training' => self::TRAINING_BOTS, 'all' => array_merge(self::TRAINING_BOTS, self::ANSWER_BOTS), default => [] };
        if ($bots) {
            $txt .= "\n# KI-Crawler: " . ($mode === 'all' ? 'alle gesperrt' : 'Training gesperrt, Antworten mit Quellenlink erlaubt') . "\n";
            foreach ($bots as $b) $txt .= 'User-agent: ' . $b . "\n";
            $txt .= "Disallow: /\n";
        }
        if ($extra = self::extraLines()) $txt .= "\n# Eigene Regeln\n" . implode("\n", $extra) . "\n";
        return $txt . "\nSitemap: " . absolute_url('/sitemap.xml') . "\n";
    }

    public static function llmsEnabled(): bool
    {
        return (bool) app()->settings->get('sys.llms_txt', false) && self::aiMode() !== 'all' && !noindex_site() && !Landings::current()?->noindex;
    }

    /** Inhalt der llms.txt (Markdown): Name, Beschreibung, indexierbare Seiten, Datentabellen mit Detailseiten */
    public static function llmsTxt(): string
    {
        $home = Pages::home();
        $desc = trim((string) ($home['meta_description'] ?? ''));
        $out = '# ' . site_name() . "\n\n" . ($desc !== '' ? '> ' . str_replace("\n", ' ', $desc) . "\n\n" : '');
        $out .= "## Seiten\n\n";
        foreach (Pages::published() as $p) {
            if ($p['noindex'] || Landings::owner($p)) continue;
            $d = trim(str_replace("\n", ' ', (string) ($p['meta_description'] ?? '')));
            $out .= '- [' . str_replace(['[', ']'], '', (string) $p['title']) . '](' . abs_url(Pages::url($p)) . ')' . ($d !== '' ? ': ' . $d : '') . "\n";
        }
        foreach (Tables::content() as $t) {
            if ($t['settings']['route'] === '' || empty($t['settings']['detail_page_id']) || !empty($t['settings']['noindex'])) continue;
            $rows = Entries::query($t, ['status' => 'published', 'limit' => 50, 'source' => Tables::isShared($t) ? 'site' : 'own']);
            if (!$rows) continue;
            $out .= "\n## " . $t['name'] . "\n\n";
            foreach ($rows as $e) $out .= '- [' . str_replace(['[', ']'], '', Entries::title($t, $e)) . '](' . site_url() . Entries::url($t, $e) . ")\n";
        }
        return $out;
    }

    /** Warnungen für die Grundeinstellungen */
    public static function warnings(): array
    {
        $w = [];
        if ((bool) app()->settings->get('sys.noindex') && environment() === 'production') $w[] = 'Die Live-Website ist für Suchmaschinen gesperrt („Suchmaschinen aussperren“ ist an).';
        foreach (self::extraLines() as $l) {
            if (preg_match('~^Disallow\s*:\s*/\s*$~i', $l)) { $w[] = 'Eine eigene Regel „Disallow: /“ sperrt die ganze Website für die genannten Crawler.'; break; }
        }
        return $w;
    }
}
