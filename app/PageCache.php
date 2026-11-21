<?php
declare(strict_types=1);

namespace Core;

/**
 * Ganzseiten-Cache für anonyme Besucher. Wird bei jeder Änderung im Admin geleert.
 * Schlüssel enthält das Datum, damit zeitgesteuerte Hero-Themen korrekt wechseln.
 * Frist: Inhalte, die von der Uhrzeit abhängen (z. B. Grafiken mit Zustand „geöffnet“ oder Tag/Nacht, Core\Graphics\Bind), melden
 * beim Erzeugen der Seite den nächsten Wechsel (until()) – die zwischengespeicherte Seite gilt nur bis dahin (Datei {hash}.until).
 */
final class PageCache
{
    /** Frist der gerade erzeugten Seite (Unix-Zeit) – null = bis Tagesende bzw. zur nächsten Änderung */
    private static ?int $until = null;

    /** Nächster Wechsel eines zeitabhängigen Inhalts der Seite, die gerade entsteht (früheste Angabe gilt) */
    public static function until(int $ts): void
    {
        if ($ts > time()) self::$until = self::$until === null ? $ts : min(self::$until, $ts);
    }

    /** Für Tests: Frist der aktuellen Anfrage (null = keine) */
    public static function pendingUntil(): ?int
    {
        return self::$until;
    }

    /** Für Tests und vor einer neuen Seite: Frist vergessen */
    public static function resetUntil(): void
    {
        self::$until = null;
    }
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
        if (!is_file($f)) return null;
        // abgelaufene Frist (zeitabhängiger Inhalt): neu erzeugen
        $u = $f . '.until';
        if (is_file($u) && time() >= (int) @file_get_contents($u)) return null;
        return (string) file_get_contents($f);
    }

    public static function put(string $key, string $html): void
    {
        if (!self::enabled()) {
            return;
        }
        if (!is_dir(self::dir())) {
            @mkdir(self::dir(), 0770, true);
        }
        $f = self::file($key);
        @file_put_contents($f, $html, LOCK_EX);
        if (self::$until !== null) @file_put_contents($f . '.until', (string) self::$until, LOCK_EX);
        elseif (is_file($f . '.until')) @unlink($f . '.until');
    }

    public static function clear(): void
    {
        foreach (glob(self::dir() . '/*.{html,until}', GLOB_BRACE) ?: [] as $f) {
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
        foreach (glob((new Site($siteKey, $cfg))->storage('cache/pages') . '/*.{html,until}', GLOB_BRACE) ?: [] as $f) {
            @unlink($f);
        }
        Search\Search::changed($siteKey);   // Suchindex der anderen Website vormerken (z. B. geteilte Einträge)
    }
}
