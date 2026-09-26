<?php
declare(strict_types=1);

namespace Core;

/**
 * Ganzseiten-Cache für anonyme Besucher. Wird bei jeder Änderung im Admin geleert.
 * Schlüssel enthält das Datum, damit zeitgesteuerte Hero-Themen korrekt wechseln.
 */
final class PageCache
{
    private static function dir(): string
    {
        return site()->storage('cache/pages');
    }

    private static function file(string $key): string
    {
        // Domain gehört zum Schlüssel: Landing-Domains (Core\Landings) rendern dieselbe Seite mit anderen Links, Marke und Canonical
        $host = strtolower((string) (app()->request?->host() ?? ''));
        return self::dir() . '/' . hash('sha256', $key . '|' . $host . '|' . date('Y-m-d')) . '.html';
    }

    public static function enabled(): bool
    {
        return (bool) app()->config->get('page_cache', true) && !app()->config->get('debug');
    }

    public static function get(string $key): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        $f = self::file($key);
        return is_file($f) ? (string) file_get_contents($f) : null;
    }

    public static function put(string $key, string $html): void
    {
        if (!self::enabled()) {
            return;
        }
        if (!is_dir(self::dir())) {
            @mkdir(self::dir(), 0770, true);
        }
        @file_put_contents(self::file($key), $html, LOCK_EX);
    }

    public static function clear(): void
    {
        foreach (glob(self::dir() . '/*.html') ?: [] as $f) {
            @unlink($f);
        }
        // Inhalte geändert → Suchindex nach der Antwort abgleichen (Core\Search)
        Search\Search::changed();
    }

    /** Seiten-Cache einer anderen Website dieser Installation leeren (z. B. nach Änderungen an geteilten Daten) */
    public static function clearSite(string $siteKey): void
    {
        if ($siteKey === site()->key) {
            self::clear();
            return;
        }
        $cfg = Sites::all()[$siteKey] ?? null;
        if ($cfg === null) return;
        foreach (glob((new Site($siteKey, $cfg))->storage('cache/pages') . '/*.html') ?: [] as $f) {
            @unlink($f);
        }
        Search\Search::changed($siteKey);   // Suchindex der anderen Website vormerken (z. B. geteilte Einträge)
    }
}
