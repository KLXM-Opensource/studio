<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Data;

/**
 * RSS-Feed (RSS 2.0) einer Datentabelle mit Detailseiten – Beiträge, News, Termine …: GET /feed/{handle}.xml (?lang=en).
 * Nur veröffentlichte Einträge (Reihenfolge der Tabelle, höchstens LIMIT), nur Tabellen mit URL-Basis + Detailvorlage, die nicht
 * auf „Nicht indexieren“ stehen; Eingänge nie. Seiten der Tabelle (Detailseiten und die Seite unter der URL-Basis) verweisen im
 * Kopf darauf (<link rel="alternate">, SiteController → inject()), damit Feed-Reader ihn finden.
 */
final class Feed
{
    public const LIMIT = 30;

    public static function available(?array $t): bool
    {
        return $t !== null && !Tables::isInbox($t) && ($t['settings']['route'] ?? '') !== '' && !empty($t['settings']['detail_page_id'])
            && empty($t['settings']['noindex']);
    }

    public static function path(array $t): string
    {
        return '/feed/' . $t['handle'] . '.xml';
    }

    public static function xml(array $t): string
    {
        $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $site = site_url();
        $self = $site . url(self::path($t)) . (\Core\Lang::current() !== \Core\Lang::default() ? '?lang=' . \Core\Lang::current() : '');
        $list = $site . url(\Core\Lang::prefix(\Core\Lang::current()) . '/' . $t['settings']['route']);
        $descField = (string) ($t['settings']['description_field'] ?? '');
        $items = '';
        foreach (Entries::query($t, ['limit' => self::LIMIT]) as $e) {
            $link = Entries::absUrl($t, $e);
            if ($link === null) continue;
            $date = (string) (($e['published_at'] ?? '') ?: ($e['created_at'] ?? ''));
            $desc = $descField !== '' ? trim(preg_replace('~\s+~', ' ', strip_emphasis(Entries::text($t, $e, $descField)))) : '';
            $items .= "  <item>\n    <title>" . $x(strip_emphasis(Entries::title($t, $e))) . "</title>\n    <link>" . $x($link) . "</link>\n"
                . '    <guid isPermaLink="true">' . $x($link) . "</guid>\n"
                . ($date !== '' && ($ts = strtotime($date)) ? '    <pubDate>' . gmdate('D, d M Y H:i:s', $ts) . " GMT</pubDate>\n" : '')
                . ($desc !== '' ? '    <description>' . $x(mb_strimwidth($desc, 0, 500, '…')) . "</description>\n" : '') . "  </item>\n";
        }
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n<channel>\n"
            . '  <title>' . $x($t['name'] . ' – ' . site_name()) . "</title>\n  <link>" . $x($list) . "</link>\n"
            . '  <description>' . $x(trim((string) ($t['description'] ?? '')) ?: $t['name']) . "</description>\n"
            . '  <language>' . $x(\Core\Lang::current()) . "</language>\n"
            . '  <atom:link href="' . $x($self) . '" rel="self" type="application/rss+xml"/>' . "\n" . $items . "</channel>\n</rss>\n";
    }

    /**
     * Kopf der Website: Verweis auf den Feed der Tabelle, deren Einträge die Seite zeigt (Detailseite) bzw. deren URL-Basis die Seite ist.
     */
    public static function inject(string $html, ?array $page, ?array $table): string
    {
        $tables = [];
        if (self::available($table)) $tables[] = $table;
        elseif ($page && ($p = trim((string) ($page['path'] ?? ''), '/')) !== '') {
            foreach (Tables::content() as $t) if (self::available($t) && trim((string) $t['settings']['route'], '/') === $p) $tables[] = $t;
        }
        if (!$tables || stripos($html, 'application/rss+xml') !== false) return $html;
        $tags = '';
        foreach ($tables as $t) {
            $tags .= '<link rel="alternate" type="application/rss+xml" title="' . e($t['name'] . ' – ' . site_name()) . '" href="' . e(url(self::path($t))) . '">' . "\n";
        }
        $pos = stripos($html, '</head>');
        return $pos === false ? $html : substr($html, 0, $pos) . $tags . substr($html, $pos);
    }

    public static function selftest(): array
    {
        $ok = 0; $fails = [];
        $t = ['handle' => 'x', 'kind' => 'content', 'settings' => ['kind' => 'content', 'route' => 'news', 'detail_page_id' => 5, 'noindex' => false]];
        $check = function (string $what, bool $got) use (&$ok, &$fails) { $got ? $ok++ : $fails[] = $what; };
        $check('Feed mit Detailseiten', self::available($t));
        $check('kein Feed ohne URL-Basis', !self::available(['settings' => ['route' => ''] + $t['settings']] + $t));
        $check('kein Feed bei „Nicht indexieren“', !self::available(['settings' => ['noindex' => true] + $t['settings']] + $t));
        $check('kein Feed im Eingang', !self::available(['settings' => ['kind' => 'inbox'] + $t['settings']] + $t));
        return ['ok' => $ok, 'fails' => $fails];
    }
}
