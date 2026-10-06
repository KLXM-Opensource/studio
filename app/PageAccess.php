<?php
declare(strict_types=1);

namespace Core;

use Core\Http\Request;
use Core\Http\Response;

/**
 * Zugriffsschutz für Seiten der Website durch Erweiterungen (z. B. Mitgliederbereich): $x->pageAccess([...]) in Core\Extension.
 *
 *  - 'restricted' => fn(array $page): bool – gehört die Seite zu einem geschützten Bereich? Muss billig und für alle Besucher
 *    gleich sein (wird für Listen, Menü, Sitemap und Suchindex abgefragt). Ergebnis je Anfrage und Seite gemerkt.
 *  - 'allow' => fn(array $page, Request $r, ?array $table = null): bool|Response – darf DIESE Anfrage die geschützte Seite sehen?
 *    true = ausliefern, false = 404, Response = diese Antwort (z. B. Weiterleitung zur Anmeldung). $table: Datentabelle bei Detailseiten.
 *  - 'table' => fn(array $table): bool (optional) – ganze Datentabelle geschützt: Detailseiten verlangen Zugriff, und für Besucher
 *    liefert Entries::query keine Einträge – außer auf geschützten Seiten (die nie im Cache liegen). So tauchen Einträge nicht in
 *    Listen, Kalendern, Feeds, Sitemap oder Suche öffentlicher Seiten auf.
 *  - 'entry' => fn(array $table, array $entry, string $audience): ?array (optional) – einzelne Einträge/Felder einer geschützten
 *    Tabelle doch zeigen ('public') bzw. für Mitglieder kürzen ('members'), z. B. Profile, deren Inhaber Felder veröffentlichen.
 *
 * Geschützte Seiten (und Detailseiten von Einträgen auf einer geschützten Vorlage) werden nie im Seiten-Cache abgelegt, mit
 * „Cache-Control: private, no-store“ ausgeliefert (der Service Worker übernimmt sie dann nicht) und erscheinen nicht im Menü,
 * in der Sitemap, in der llms.txt, im Suchindex (damit auch nicht im Besucher-Chat) und nicht über /api/live/block.
 * Angemeldete Redakteur:innen sehen alles (Vorschau). Fehler einer Erweiterung schützen im Zweifel (restricted = true, allow = 404).
 */
final class PageAccess
{
    /** @var array<int, bool> */
    private static array $memo = [];
    /** @var array<string, bool> */
    private static array $tables = [];

    /** Gehört die Seite zu einem geschützten Bereich (irgendeiner aktiven Erweiterung)? */
    public static function restricted(array $page): bool
    {
        $id = (int) ($page['id'] ?? 0);
        if (isset(self::$memo[$id])) return self::$memo[$id];
        $out = false;
        foreach (Extensions::active() as $x) {
            foreach ($x->pageGuards as $g) {
                try {
                    if (($g['restricted'])($page)) $out = true;
                } catch (\Throwable $e) {
                    error_log('[Erweiterung ' . $x->name . '] pageAccess.restricted: ' . $e->getMessage());
                    $out = true;
                }
                if ($out) break 2;
            }
        }
        return self::$memo[$id] = $out;
    }

    /** Gibt es überhaupt Schutzregeln (sonst müssen Listen nicht geprüft werden)? */
    public static function active(): bool
    {
        foreach (Extensions::active() as $x) if ($x->pageGuards) return true;
        return false;
    }

    /**
     * Prüfung beim Ausliefern einer Seite: null = frei zugänglich bzw. erlaubt, sonst die Antwort für Besucher ohne Zugriff.
     * Für erlaubte geschützte Seiten setzt die aufrufende Stelle private() (kein Cache).
     */
    public static function deny(array $page, Request $r, ?array $table = null): ?Response
    {
        if (app()->auth->check() || (!self::restricted($page) && !($table && self::tableRestricted($table)))) return null;
        foreach (Extensions::active() as $x) {
            foreach ($x->pageGuards as $g) {
                try {
                    $ok = ($g['allow'])($page, $r, $table);
                } catch (\Throwable $e) {
                    error_log('[Erweiterung ' . $x->name . '] pageAccess.allow: ' . $e->getMessage());
                    $ok = false;
                }
                if ($ok instanceof Response) return $ok->header('Cache-Control', 'private, no-store');
                if ($ok !== true) throw new Http\HttpException(404);
            }
        }
        return null;
    }

    /** Für Besucher sichtbare Seiten einer Liste (geschützte entfernt; Redaktion sieht alle) */
    public static function visible(array $pages): array
    {
        if (!self::active() || app()->auth->check()) return $pages;
        return array_values(array_filter($pages, fn($p) => !self::restricted($p)));
    }

    /** Datentabelle geschützt (eigene Regel einer Erweiterung) oder ihre Detailseite in einem geschützten Bereich? */
    public static function tableRestricted(array $table): bool
    {
        $key = (string) ($table['handle'] ?? '');
        if (isset(self::$tables[$key])) return self::$tables[$key];
        if (!self::active()) return self::$tables[$key] = false;
        $out = false;
        foreach (Extensions::active() as $x) {
            foreach ($x->pageGuards as $g) {
                if (!isset($g['table'])) continue;
                try {
                    if (($g['table'])($table)) $out = true;
                } catch (\Throwable $e) {
                    error_log('[Erweiterung ' . $x->name . '] pageAccess.table: ' . $e->getMessage());
                    $out = true;
                }
                if ($out) break 2;
            }
        }
        if (!$out && ($id = (int) ($table['settings']['detail_page_id'] ?? 0)) && ($p = Pages::find($id))) $out = self::restricted($p);
        return self::$tables[$key] = $out;
    }

    /**
     * Wer sieht Einträge dieser Tabelle in der aktuellen Anfrage? 'all' = ungefiltert (Verwaltung, API, MCP, Konsole, angemeldete
     * Redaktion, ungeschützte Tabellen); 'members' = geschützte Seite bzw. erlaubte Detailseite (nie im Cache); 'public' = Besucher
     * einer öffentlichen Seite (Seiten-Cache – für alle gleich).
     */
    public static function audience(array $table): string
    {
        $r = app()->request;
        if (!$r || PHP_SAPI === 'cli' || !self::active() || app()->auth->check()) return 'all';
        if ($r->isAdminPath() || preg_match('~^/(api|mcp)(/|$)~', $r->path)) return 'all';
        if (!self::tableRestricted($table)) return 'all';
        $cur = app()->currentPage;
        if ($cur && !empty($cur['id']) && self::restricted($cur)) return 'members';
        $ctx = app()->entry;
        if ($ctx && ($ctx['table']['handle'] ?? '') === ($table['handle'] ?? '') && self::$allowedEntry) return 'members';
        return 'public';
    }

    /** Ganze Tabelle für diese Anfrage leer? (öffentliche Seite, keine Erweiterung gibt einzelne Einträge frei) */
    public static function hidesTable(array $table): bool
    {
        return self::audience($table) === 'public' && !self::entryFilters();
    }

    /** @return list<array{0: Extension, 1: callable}> */
    private static function entryFilters(): array
    {
        $out = [];
        foreach (Extensions::active() as $x) foreach ($x->pageGuards as $g) if (isset($g['entry'])) $out[] = [$x, $g['entry']];
        return $out;
    }

    /**
     * Einträge einer geschützten Tabelle für das Publikum filtern: 'entry' => fn(array $table, array $entry, string $audience): ?array
     * jeder Erweiterung (null = ausblenden, sonst Eintrag mit entfernten Feldern). Ohne Filter: öffentlich nichts, Mitglieder alles.
     */
    public static function filterEntries(array $table, array $rows, string $audience): array
    {
        if ($audience === 'all') return $rows;
        $filters = self::entryFilters();
        if (!$filters) return $audience === 'public' ? [] : $rows;
        $out = [];
        foreach ($rows as $row) {
            foreach ($filters as [$x, $fn]) {
                try {
                    $row = $fn($table, $row, $audience);
                } catch (\Throwable $e) {
                    error_log('[Erweiterung ' . $x->name . '] pageAccess.entry: ' . $e->getMessage());
                    $row = null;
                }
                if ($row === null) continue 2;
            }
            $out[] = $row;
        }
        return $out;
    }

    /** Detailseite einer geschützten Tabelle wurde erlaubt (SiteController::render) – Felder des Eintrags dürfen erscheinen */
    public static bool $allowedEntry = false;

    /** Nach Änderungen an den Regeln (Erweiterung) bzw. für Tests */
    public static function reset(): void
    {
        self::$memo = [];
        self::$tables = [];
    }
}
