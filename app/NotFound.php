<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Data\Entries;
use Core\Data\Tables;

/**
 * Gepflegte Seite „Nicht gefunden (404)“ je Website und Sprache.
 *
 * Eine Seite mit type = 'template' und template_for = '@404' (wie Detailseiten-Vorlagen: nie unter eigener Adresse öffentlich,
 * nicht in Menü, Sitemap, Suchindex und Link-Auswahl). Bearbeitet wird sie wie jede Seite im Frontend-Editor unter /404?edit=1
 * (Entwurf, Veröffentlichen, Versionen); ihre eigene Adresse antwortet selbst mit 404.
 *
 * Ablauf (SiteController::error): 404 bzw. 410 und eine veröffentlichte Seite der aktuellen Sprache (Rückfall: Standardsprache)
 * → ihre Blöcke im normalen Layout, Status bleibt 404/410, noindex (Seo::forError). Angemeldete sehen auch einen Entwurf.
 * Sonst – und immer bei 403/405/419/500 – die Kit-Vorlage templates/error.php wie bisher.
 *
 * Blöcke kennen die aufgerufene Adresse (NotFound::path()); der Kern-Block „404-Vorschläge“ (not_found) nutzt suggestions().
 * Kits liefern Startinhalte beim Anlegen: theme.php → 'not_found_blocks' => fn(string $lang): array (Editor.js-Blöcke) oder ein
 * Array; ohne Angabe ein Block „404-Vorschläge“ mit Überschrift, Text, Vorschlägen, Suche und Button zur Startseite.
 */
final class NotFound
{
    /** Kennzeichen in pages.template_for (Tabellen-Kurznamen beginnen mit a–z, also keine Verwechslung) */
    public const MARK = '@404';
    public const SLUG = '404';

    /** Aufgerufene Adresse der Anfrage, die gerade als 404 gerendert wird (null = keine 404-Darstellung) */
    private static ?string $path = null;

    /** Ist die Seite die 404-Seite? */
    public static function isPage(?array $page): bool
    {
        return $page !== null && ($page['type'] ?? '') === 'template' && ($page['template_for'] ?? '') === self::MARK;
    }

    /** SQL-Bedingung „normale Seite oder 404-Seite“ (z. B. Entwürfe-Übersicht) */
    public static function sqlPagesAnd404(): string
    {
        return "(type = 'page' OR (type = 'template' AND template_for = '" . self::MARK . "'))";
    }

    /**
     * 404-Seite der Sprache (Standard: aktuelle), Rückfall auf die Standardsprache. Ohne $withDrafts nur veröffentlichte
     * (Besucher), sonst auch nie veröffentlichte (angemeldete Redaktion).
     */
    public static function page(?string $lang = null, bool $withDrafts = false): ?array
    {
        $lang = Lang::norm($lang ?? Lang::current());
        foreach (array_unique([$lang, Lang::default()]) as $l) {
            $p = self::exact($l);
            if ($p && ($withDrafts || ($p['status'] === 'published' && $p['content_published'] !== null))) return $p;
        }
        return null;
    }

    /** 404-Seite genau dieser Sprache (ohne Rückfall, auch Entwürfe) */
    public static function exact(string $lang): ?array
    {
        try {
            return app()->db->fetch("SELECT * FROM pages WHERE type = 'template' AND template_for = ? AND " . Lang::sql() . ' ORDER BY id LIMIT 1',
                [self::MARK, Lang::norm($lang)]);
        } catch (\Throwable) {
            return null;   // Datenbank noch nicht eingerichtet
        }
    }

    /** Alle 404-Seiten: [sprache => seite] */
    public static function all(): array
    {
        $out = [];
        foreach (app()->db->fetchAll("SELECT * FROM pages WHERE type = 'template' AND template_for = ? ORDER BY id", [self::MARK]) as $p) {
            $out[Lang::norm($p['lang'])] ??= $p;
        }
        return $out;
    }

    // ================================================================== Anfrage

    /** Beginn einer 404-Darstellung: aufgerufene Adresse (ohne Installations-Unterordner) für die Blöcke merken */
    public static function begin(string $path): void
    {
        self::$path = '/' . ltrim($path, '/');
    }

    public static function end(): void
    {
        self::$path = null;
    }

    /** Aufgerufene Adresse, während eine 404 gerendert wird – sonst null (z. B. im Editor unter /404) */
    public static function path(): ?string
    {
        return self::$path;
    }

    /** Ist $path (ohne Sprachpräfix) die Adresse der 404-Seite dieser Sprache? */
    public static function isOwnPath(string $path): bool
    {
        $p = self::page(null, true);
        return $p !== null && trim($path, '/') === (string) ($p['path'] ?: $p['slug']);
    }

    // ================================================================== Vorschläge

    /**
     * „Vielleicht meinten Sie …“: Seiten und Einträge (mit Detailseite) der aktuellen Sprache, deren Adressteil dem letzten Teil
     * der aufgerufenen Adresse ähnelt (Levenshtein, enthalten zählt als sehr ähnlich), dazu der Vorschlag des 404-Protokolls
     * (Redirects::suggest – gleicher Slug an anderer Stelle) zuerst. Ohne aufgerufene Adresse leer.
     * @return list<array{title: string, href: string}>
     */
    public static function suggestions(?string $path = null, int $max = 3): array
    {
        $path ??= self::$path;
        if ($path === null || $max < 1) return [];
        $segs = array_values(array_filter(explode('/', rawurldecode(explode('?', $path, 2)[0])), fn($s) => $s !== ''));
        if ($segs && Lang::multi() && $segs[0] !== Lang::default() && Lang::valid($segs[0])) array_shift($segs);
        if (!$segs) return [];
        $want = Pages::slugify((string) preg_replace('~\.(html?|php)$~i', '', (string) end($segs)));
        if (strlen($want) < 3 || $want === 'seite') return [];
        $lang = Lang::current();
        $out = [];
        try {
            $s = Redirects\Redirects::suggest($path);
            if ($s && Lang::norm($s['page']['lang']) === $lang && empty($s['page']['noindex'])) {
                $out[Pages::url($s['page'])] = [0, (string) ($s['page']['nav_title'] ?: $s['page']['title'])];
            }
            $cand = [];
            foreach (Pages::published() as $p) {
                if (!empty($p['noindex']) || Lang::norm($p['lang']) !== $lang) continue;
                $cand[Pages::url($p)] = [(string) $p['slug'], (string) ($p['nav_title'] ?: $p['title'])];
            }
            foreach (Tables::content() as $t) {
                if (($t['settings']['route'] ?? '') === '' || empty($t['settings']['detail_page_id'])) continue;
                foreach (Entries::query($t, ['status' => 'published', 'limit' => 300]) as $e) {
                    if (($u = Entries::href($t, $e)) && !isset($cand[$u])) $cand[$u] = [(string) ($e['slug'] ?? ''), Entries::title($t, $e)];
                }
            }
        } catch (\Throwable $e) {
            error_log('[404] ' . $e->getMessage());
            return [];
        }
        foreach ($cand as $u => [$slug, $title]) {
            if ($slug === '' || isset($out[$u])) continue;
            $a = substr($want, 0, 60);
            $b = substr($slug, 0, 60);
            $d = levenshtein($a, $b);
            if (strlen($a) >= 4 && strlen($b) >= 4 && (str_contains($b, $a) || str_contains($a, $b))) $d = min($d, 1);
            if ($d <= max(2, intdiv(min(strlen($a), strlen($b)), 3))) $out[$u] = [$d + 1, $title];
        }
        uasort($out, fn($x, $y) => $x[0] <=> $y[0]);
        $res = [];
        foreach (array_slice($out, 0, $max, true) as $u => [, $title]) $res[] = ['title' => $title, 'href' => (string) $u];
        return $res;
    }

    // ================================================================== Anlegen

    /**
     * 404-Seite einer Sprache anlegen (Entwurf) – gibt es sie schon, wird sie zurückgegeben. Weitere Sprachen: Übersetzung der
     * Seite der Standardsprache (falls vorhanden), sonst Startinhalte des Kits.
     */
    public static function create(string $lang): array
    {
        if (!Lang::valid($lang)) $lang = Lang::default();
        if ($p = self::exact($lang)) return $p;
        $def = self::exact(Lang::default());
        if ($lang !== Lang::default() && $def) return Pages::translate((int) $def['id'], $lang);
        $slug = self::SLUG;
        for ($n = 2; Pages::slugTaken($slug, null, null, $lang); $n++) $slug = self::SLUG . '-' . $n;
        $prev = app()->lang;
        app()->lang = $lang;   // Startinhalte (lt()) in der Sprache der Seite
        try {
            $id = Pages::create(['slug' => $slug, 'title' => lt('Seite nicht gefunden'), 'type' => 'template', 'template_for' => self::MARK,
                'status' => 'draft', 'menu' => 0, 'noindex' => 1, 'lang' => $lang === Lang::default() ? null : $lang],
                Pages::sanitizeBlocks(self::defaultBlocks($lang)));
        } finally {
            app()->lang = $prev;
        }
        PageCache::clear();
        return Pages::find($id) ?? throw new \RuntimeException('404-Seite konnte nicht angelegt werden.');
    }

    /** Startinhalte: vom Kit (theme.php → 'not_found_blocks') oder der Kern-Block „404-Vorschläge“ mit allen Teilen */
    public static function defaultBlocks(string $lang): array
    {
        $hook = app()->theme->def['not_found_blocks'] ?? null;
        $blocks = is_callable($hook) ? $hook($lang) : (is_array($hook) ? $hook : null);
        if (is_array($blocks) && $blocks) return $blocks;
        return [['type' => 'not_found', 'data' => [
            'eyebrow' => lt('Fehler 404'),
            'title' => lt('Diese Seite gibt es nicht (mehr).'),
            'intro' => lt('Vielleicht hat sich die Adresse geändert. Über die Startseite, das Menü oder die Suche finden Sie weiter.'),
            'suggest' => true, 'suggest_title' => lt('Vielleicht meinten Sie:'), 'search' => true, 'search_title' => lt('Oder suchen Sie danach:'),
            'home_label' => lt('Zur Startseite'),
        ]]];
    }

    /**
     * Felder des Blocks „404-Vorschläge“ – für Kits, die den Block mit eigenem Aussehen überschreiben (theme.php → blocks →
     * not_found mit eigenen Kopf-Feldern + ...NotFound::blockFields()) und blocks/not_found.php.
     */
    public static function blockFields(): array
    {
        return [
            ['type' => 'heading', 'label' => 'Vorschläge'],
            ['name' => 'suggest', 'label' => '„Vielleicht meinten Sie …“ – ähnliche Seiten zur aufgerufenen Adresse', 'type' => 'bool', 'default' => true],
            ['name' => 'suggest_title', 'label' => 'Überschrift der Vorschläge', 'type' => 'text', 'max' => 60, 'width' => 'half', 'default' => 'Vielleicht meinten Sie:'],
            ['name' => 'suggest_max', 'label' => 'Höchstens', 'type' => 'select', 'required' => true, 'default' => '3', 'width' => 'half',
                'options' => ['1' => '1', '3' => '3', '5' => '5']],
            ['type' => 'heading', 'label' => 'Suche'],
            ['name' => 'search', 'label' => 'Suchfeld zeigen (wenn die Suche eingeschaltet ist)', 'type' => 'bool', 'default' => true],
            ['name' => 'search_title', 'label' => 'Überschrift der Suche', 'type' => 'text', 'max' => 60, 'width' => 'half', 'default' => 'Oder suchen Sie danach:'],
            ['name' => 'search_placeholder', 'label' => 'Platzhalter im Suchfeld (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
            ['type' => 'heading', 'label' => 'Button'],
            ['name' => 'home_label', 'label' => 'Button zur Startseite (leer = kein Button)', 'type' => 'text', 'max' => 40, 'width' => 'half', 'default' => 'Zur Startseite'],
            ['name' => 'link_label', 'label' => 'Zweiter Button (optional)', 'type' => 'text', 'max' => 40, 'width' => 'half'],
            ['name' => 'link', 'label' => 'Ziel des zweiten Buttons', 'type' => 'link'],
        ];
    }

    /**
     * Vorschläge und Suchfeld eines Blocks (Felder aus blockFields()) als Raster zweier Kästen – für den Kern-Block und für
     * Kits, die nur Kopf und Buttons selbst gestalten. Klassen cms-404__* (resources/css/notfound.css). Im Editor ohne
     * aufgerufene Adresse ein Hinweis statt der Liste.
     */
    public static function boxes(Block $b): string
    {
        $d = $b->data;
        $editing = is_editing();
        $sug = !empty($d['suggest']) ? self::suggestions(null, max(1, min(5, (int) ($d['suggest_max'] ?? 3)))) : [];
        $showSug = $sug || (!empty($d['suggest']) && $editing && self::$path === null);
        $search = !empty($d['search']) ? Search\Search::form('page', '', ['placeholder' => trim((string) ($d['search_placeholder'] ?? ''))]) : '';
        if (!$showSug && $search === '') {
            return !empty($d['search']) && $editing
                ? '<p class="cms-404__hint">' . e(__('Das Suchfeld erscheint, sobald die Suche eingeschaltet ist (Verwaltung → Suche).')) . '</p>' : '';
        }
        $h = '<div class="cms-404__grid">';
        if ($showSug) {
            $sid = $b->domId() . '-sug';
            $h .= '<nav class="cms-404__box" aria-labelledby="' . e($sid) . '"><h2 class="cms-404__h" id="' . e($sid) . '"' . $b->edit('suggest_title') . '>'
                . e((string) (($d['suggest_title'] ?? '') ?: lt('Vielleicht meinten Sie:'))) . '</h2>';
            $h .= $sug
                ? '<ul class="cms-404__list" role="list">' . implode('', array_map(fn($s) => '<li><a href="' . e($s['href']) . '">' . e($s['title'])
                    . ' <span aria-hidden="true">→</span></a></li>', $sug)) . '</ul>'
                : '<p class="cms-404__hint">' . e(__('Hier erscheinen Seiten, deren Adresse der aufgerufenen ähnelt – nur, wenn es passende gibt. Im Editor bleibt die Liste leer.')) . '</p>';
            $h .= '</nav>';
        }
        if ($search !== '') {
            $st = trim((string) ($d['search_title'] ?? ''));
            $h .= '<div class="cms-404__box cms-404__search">' . ($st !== '' ? '<h2 class="cms-404__h"' . $b->edit('search_title') . '>' . e($st) . '</h2>' : '') . $search . '</div>';
        }
        return $h . '</div>';
    }

    // ================================================================== Kommandozeile

    /** notfound:create [--lang=xx] [--publish] | notfound:selftest (php bin/console, --site=…) */
    public static function console(string $cmd, array $args): int
    {
        if ($cmd === 'notfound:selftest') {
            $res = self::selfTest();
            foreach ($res['fails'] as $f) echo "  FEHLER: $f\n";
            echo "  {$res['ok']} Prüfungen bestanden, " . count($res['fails']) . " fehlgeschlagen\n";
            return $res['fails'] ? 1 : 0;
        }
        $lang = Lang::default();
        foreach ($args as $a) if (str_starts_with($a, '--lang=')) $lang = substr($a, 7);
        if (!Lang::valid($lang)) { fwrite(STDERR, "Unbekannte Sprache: $lang\n"); return 1; }
        $had = self::exact($lang) !== null;
        $p = self::create($lang);
        if (in_array('--publish', $args, true)) Pages::publish((int) $p['id'], null);
        echo ($had ? 'Vorhanden' : 'Angelegt') . ': „' . $p['title'] . '“ (#' . $p['id'] . ', ' . $lang . ') – bearbeiten unter ' . Pages::plainUrl($p) . "?edit=1\n";
        return 0;
    }

    /**
     * Selbsttest (notfound:selftest) in einer Transaktion, die am Ende zurückgerollt wird: Anlegen, nur veröffentlicht für Besucher,
     * eigene Adresse, nicht in Seitenliste/Menü/Sitemap/Link-Auswahl, Übersetzung bleibt 404-Seite, Vorschläge.
     */
    public static function selfTest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $pdo = app()->db->pdo;
        $pdo->beginTransaction();
        try {
            app()->db->query("DELETE FROM pages WHERE type = 'template' AND template_for = ?", [self::MARK]);
            $def = Lang::default();
            $eq('ohne Seite: keine 404-Seite', self::page(), null);
            $p = self::create($def);
            $eq('angelegt als Vorlage', self::isPage($p), true);
            $eq('Entwurf', $p['status'], 'draft');
            $eq('noindex', (int) $p['noindex'], 1);
            $eq('Startinhalt vorhanden', count(Pages::blocks($p, true)) > 0, true);
            $eq('Besucher: Entwurf zählt nicht', self::page(), null);
            $eq('Redaktion: Entwurf zählt', (int) (self::page(null, true)['id'] ?? 0), (int) $p['id']);
            $eq('zweites Anlegen liefert dieselbe', (int) self::create($def)['id'], (int) $p['id']);
            Pages::publish((int) $p['id'], null);
            $p = Pages::find((int) $p['id']);
            $eq('veröffentlicht: für Besucher', (int) (self::page()['id'] ?? 0), (int) $p['id']);
            $own = (string) ($p['path'] ?: $p['slug']);
            $eq('eigene Adresse erkannt', self::isOwnPath('/' . $own . '/'), true);
            $eq('andere Adresse nicht', self::isOwnPath('/gibt-es-nicht-' . $own), false);
            $eq('nicht per Pfad erreichbar', Pages::byPath($own), null);
            $ids = fn(array $rows) => in_array((int) $p['id'], array_map(fn($r) => (int) $r['id'], $rows), true);
            $eq('nicht in Pages::all (Link-Auswahl, API)', $ids(Pages::all()), false);
            $eq('nicht in Pages::published (Sitemap, Suche)', $ids(Pages::published()), false);
            $eq('nicht im Menü', in_array((int) $p['id'], array_column(Pages::menu(true), 'id'), true), false);
            $eq('nicht in Seiten-Auswahlfeldern', isset(Fields::pageOptions()[(int) $p['id']]), false);
            $eq('Adresse belegt', Pages::slugTaken($p['slug'], null, null, $def), true);
            foreach (array_keys(Lang::all()) as $l) {
                if ($l === $def) continue;
                $eq("Sprache $l: Rückfall auf Standardsprache", (int) (self::page($l)['id'] ?? 0), (int) $p['id']);
                $t = self::create($l);
                $eq("Übersetzung $l bleibt 404-Seite", self::isPage($t), true);
                $eq("Übersetzung $l als Entwurf: Besucher sehen Standardsprache", (int) (self::page($l)['id'] ?? 0), (int) $p['id']);
                break;
            }
            // Vorschläge
            $q = Pages::create(['slug' => 'nf-selbsttest-leistungen', 'title' => 'Selbsttest Leistungen', 'status' => 'published']);
            $href = Pages::url(Pages::find($q));
            $eq('ohne Adresse keine Vorschläge', self::suggestions(null), []);
            $eq('ähnlicher Slug', in_array($href, array_column(self::suggestions('/nf-selbsttest-leistungn'), 'href'), true), true);
            $eq('enthaltener Slug', in_array($href, array_column(self::suggestions('/alt/nf-selbsttest-leistungen.html'), 'href'), true), true);
            $eq('zu kurz: keine', self::suggestions('/ab'), []);
            $eq('Höchstzahl', count(self::suggestions('/nf-selbsttest-leistungen', 1)) <= 1, true);
            self::begin('/nf-selbsttest-leistung');
            $eq('Adresse der Anfrage', self::path(), '/nf-selbsttest-leistung');
            $eq('Vorschläge aus der Anfrage', in_array($href, array_column(self::suggestions(), 'href'), true), true);
            self::end();
            $eq('nach der Anfrage leer', self::path(), null);
        } catch (\Throwable $e) {
            $fails[] = 'Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            self::end();
            if ($pdo->inTransaction()) $pdo->rollBack();
            PageCache::clear();
        }
        return ['ok' => $ok, 'fails' => $fails];
    }

    /** Adresse der Startseite in der aktuellen Sprache */
    public static function homeUrl(): string
    {
        return url(Lang::prefix(Lang::current()) . '/');
    }
}
