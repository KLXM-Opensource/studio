<?php
declare(strict_types=1);

namespace Core\Search;

use Core\AI\Ai;
use Core\Database;
use Core\Features;
use Core\Lang;
use Core\Site;
use Core\Sites;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Query\VectorQuery;

/**
 * Website-Suche für Besucher (Funktion „search“): Stichwortsuche (Loupe) und – wenn eingeschaltet – semantische Suche
 * (Embeddings über Symfony AI, Core\AI\Ai). Beide Listen werden per Reciprocal Rank Fusion gemischt; fällt der
 * KI-Anbieter aus oder antwortet er zu langsam, bleibt es bei der Stichwortsuche.
 *
 * Speicher je Website: {storage}/search/ – index.sqlite (Dokumente, Vektoren, Suchanfrage-Vektoren als Hash,
 * anonyme Zähler „ohne Treffer“) und loupe-{sprache}/ (Stichwort-Index je Sprache).
 * Aktualisierung: Änderungen leeren den Seiten-Cache (PageCache::clear) → Markierung „dirty“ → Abgleich nach der Antwort
 * (nur geänderte Dokumente), zusätzlich `php bin/console search:index [--all] [--full]` per Cron.
 */
final class Search
{
    public const SLUGS = ['de' => 'suche', 'en' => 'search', 'fr' => 'recherche', 'it' => 'cerca', 'es' => 'buscar', 'nl' => 'zoeken', 'pl' => 'szukaj'];
    public const SUGGEST = ['de' => 'vorschlag', 'en' => 'suggest', 'fr' => 'suggestion', 'it' => 'suggerimenti', 'es' => 'sugerencias', 'nl' => 'suggesties', 'pl' => 'podpowiedzi'];
    public const PER_PAGE = 10;
    /** Maximal berücksichtigte Treffer je Liste (Stichwort, semantisch) */
    public const MAX_HITS = 60;
    private const RRF_K = 60;

    private static ?Database $db = null;
    private static ?string $dbSite = null;
    private static bool $shutdown = false;

    // ------------------------------------------------------------------ Einstellungen

    public static function enabled(): bool
    {
        if (!Features::on('search', false)) return false;
        // Landing-Domain (Core\Landings): Suche je Landingpage abschaltbar
        return (\Core\Landings::current()?->search ?? '') !== 'off';
    }

    /** Einstellungen der Website (Grundeinstellungen → Suche, Core\Search\AdminSettings) */
    public static function settings(): array
    {
        $s = app()->settings;
        $syn = [];
        foreach (array_keys(Lang::all()) as $l) $syn[$l] = (string) $s->get('sys.search_syn_' . $l, '');
        return [
            'exclude' => array_values(array_filter(array_map('strval', (array) $s->get('sys.search_exclude', [])))),   // Datentabellen ohne Suche
            'media' => (bool) $s->get('sys.search_media', false),
            'semantic' => (bool) $s->get('sys.search_semantic', false),
            'misses' => (bool) $s->get('sys.search_misses', true),
            'boost' => ['title' => max(0, min(10, (int) $s->get('sys.search_boost_title', 3))), 'headings' => max(0, min(10, (int) $s->get('sys.search_boost_headings', 2)))],
            'synonyms' => $syn,
        ];
    }

    /** Synonym-Gruppen einer Sprache: eine Zeile je Gruppe, Begriffe mit Komma getrennt */
    public static function synonyms(string $lang): array
    {
        $out = [];
        foreach (preg_split('~\R~u', (string) (self::settings()['synonyms'][$lang] ?? '')) ?: [] as $line) {
            $terms = array_values(array_unique(array_filter(array_map(fn($t) => trim(mb_substr($t, 0, 60)), explode(',', $line)), fn($t) => $t !== '')));
            if (count($terms) > 1) $out[] = array_slice($terms, 0, 12);
        }
        return array_slice($out, 0, 200);
    }

    /** Semantische Suche wirklich aktiv? (Suche → semantisch an UND KI mit Embeddings für diese Website an – Grundeinstellungen → KI) */
    public static function semanticActive(): bool
    {
        return self::settings()['semantic'] && Ai::enabled('embed');
    }

    // ------------------------------------------------------------------ Adressen

    public static function slug(?string $lang = null): string
    {
        $lang = Lang::norm($lang ?? Lang::current());
        return self::SLUGS[substr($lang, 0, 2)] ?? 'search';
    }

    /** Adresse der Ergebnisseite, z. B. /suche?q=… bzw. /en/search?q=… */
    public static function url(string $q = '', ?string $lang = null, array $extra = []): string
    {
        $lang = Lang::norm($lang ?? Lang::current());
        $qs = http_build_query(array_filter(['q' => $q] + $extra, fn($v) => $v !== '' && $v !== null));
        return url(Lang::prefix($lang) . '/' . self::slug($lang)) . ($qs !== '' ? '?' . $qs : '');
    }

    public static function suggestUrl(?string $lang = null): string
    {
        $lang = Lang::norm($lang ?? Lang::current());
        return url(Lang::prefix($lang) . '/' . self::slug($lang) . '/' . (self::SUGGEST[substr($lang, 0, 2)] ?? 'suggest'));
    }

    /** Muster der öffentlichen Routen (für routes.php): /suche, /en/search, /suche/vorschlag … */
    public static function routePattern(bool $suggest = false): string
    {
        $slugs = implode('|', array_unique(array_merge(array_values(self::SLUGS), ['search'])));
        $sugg = implode('|', array_unique(array_merge(array_values(self::SUGGEST), ['suggest'])));
        // Keine {n}-Quantoren: der Router liest {name} als Parameter
        return '/(?:(?P<lang>[a-z][a-z](?:-[a-z][a-z])?)/)?(?P<slug>' . $slugs . ')' . ($suggest ? '/(?:' . $sugg . ')' : '');
    }

    /** schema.org SearchAction für den WebSite-Knoten (StructuredData) */
    public static function potentialAction(): ?array
    {
        if (!self::enabled()) return null;
        return ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => site_url() . self::url() . '?q={search_term_string}'],
            'query-input' => 'required name=search_term_string'];
    }

    /** Suchformular (für Themes): Theme-Partial „search-form“, sonst das des Kerns. $variant: header | page | menu */
    public static function form(string $variant = 'header', string $q = '', array $opt = []): string
    {
        if (!self::enabled()) return '';
        // $opt['panelOnly']: nur das Popover ohne Lupe (eigener Auslöser, z. B. Befehlsfeld der Kopfbereich-Aktionen, Core\HeaderActions)
        $vars = ['action' => url(Lang::prefix(Lang::current()) . '/' . self::slug()), 'q' => $q, 'variant' => $variant,
            'suggest' => self::suggestUrl(), 'js' => asset('js/search.js'), 'id' => 'q-' . $variant, 'pid' => self::panelId($variant),
            'panelOnly' => !empty($opt['panelOnly']),
            // $opt['label'], $opt['placeholder']: eigene Texte (z. B. Such-Einstieg, Core\Blocks\Hero::search; $variant 'hero')
            'label' => trim((string) ($opt['label'] ?? '')), 'placeholder' => trim((string) ($opt['placeholder'] ?? ''))];
        $theme = app()->theme;
        return is_file($theme->path . '/templates/partials/search-form.php')
            ? $theme->partial('search-form', $vars)
            : \Core\Theme::capture(ROOT . '/app/Views/search-form.php', $vars);
    }

    /** id des Popovers der Kopf-Suche (Ziel eines eigenen Auslösers mit popovertarget) */
    public static function panelId(string $variant = 'header'): string
    {
        return 'hsearch-' . substr(md5('q-' . $variant), 0, 6);
    }

    // ------------------------------------------------------------------ Speicher

    public static function dir(?Site $site = null): string
    {
        return ($site ?? site())->storage('search');
    }

    public static function db(): Database
    {
        if (self::$db !== null && self::$dbSite === site()->key) return self::$db;
        $dir = self::dir();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $db = new Database(['driver' => 'sqlite', 'path' => $dir . '/index.sqlite']);
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS docs (id TEXT NOT NULL, lang TEXT NOT NULL, hash TEXT NOT NULL DEFAULT '', type TEXT, tbl TEXT,
            badge TEXT, title TEXT, headings TEXT, url TEXT, date TEXT, date_label TEXT, origin TEXT, excerpt TEXT,
            vhash TEXT NULL, vmodel TEXT NULL, updated INT, PRIMARY KEY (id, lang))");
        $db->ensureColumns('docs', ['summary' => 'TEXT NULL', 'image' => 'TEXT NULL', 'facets' => 'TEXT NULL']);   // Anzeige & Filter (TableSearch)
        $db->pdo->exec('CREATE TABLE IF NOT EXISTS state (k TEXT PRIMARY KEY, v TEXT)');
        // Vektoren der Suchanfragen: nur Hash der Anfrage (kein Klartext), für wiederholte Suchen ohne erneuten Anbieter-Aufruf
        $db->pdo->exec('CREATE TABLE IF NOT EXISTS qcache (h TEXT PRIMARY KEY, vec BLOB NOT NULL, t INT NOT NULL)');
        // Anonyme Zähler „Suchbegriffe ohne Treffer“ (keine IP, keine Uhrzeit, nur Tag der letzten Suche)
        $db->pdo->exec('CREATE TABLE IF NOT EXISTS misses (lang TEXT NOT NULL, term TEXT NOT NULL, n INT NOT NULL DEFAULT 1, last TEXT, PRIMARY KEY (lang, term))');
        (new VectorStore($db))->setup();
        self::$dbSite = site()->key;
        return self::$db = $db;
    }

    public static function keyword(string $lang): KeywordIndex
    {
        return new LoupeIndex(self::dir() . '/loupe-' . preg_replace('~[^a-z\-]~', '', $lang), $lang);
    }

    public static function vectors(): VectorStore
    {
        return new VectorStore(self::db());
    }

    public static function state(string $k, ?string $v = null): ?string
    {
        if (func_num_args() > 1) {
            self::db()->query('INSERT INTO state (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v', [$k, $v]);
            return $v;
        }
        $r = self::db()->fetchValue('SELECT v FROM state WHERE k = ?', [$k]);
        return $r === null ? null : (string) $r;
    }

    // ------------------------------------------------------------------ Aktualisierung

    /** Inhalte geändert (aus PageCache::clear/clearSite): Website zum Abgleich vormerken */
    public static function changed(?string $siteKey = null): void
    {
        try {
            $siteKey ??= site()->key;
            $cfg = Sites::all()[$siteKey] ?? null;
            if ($cfg === null) return;
            $dir = self::dir(new Site($siteKey, $cfg));
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            @touch($dir . '/dirty');
            if ($siteKey === site()->key && !self::$shutdown) {
                self::$shutdown = true;
                register_shutdown_function([self::class, 'afterResponse']);
            }
        } catch (\Throwable $e) {
            error_log('[search] changed: ' . $e->getMessage());
        }
    }

    public static function isDirty(): bool
    {
        return is_file(self::dir() . '/dirty');
    }

    /** Nach der Antwort: Abgleich (Stichwort + fehlende Vektoren) – Besucher und Redaktion warten nicht */
    public static function afterResponse(): void
    {
        try {
            if (!self::enabled()) return;
            $dirty = self::isDirty();
            $vec = !$dirty && self::semanticActive() && self::pendingVectors() > 0 && !Ai::paused();
            if (!$dirty && !$vec) return;
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            ignore_user_abort(true);
            @set_time_limit(180);
            self::process(budget: 60.0);
        } catch (\Throwable $e) {
            error_log('[search] after response: ' . $e->getMessage());
        }
    }

    /**
     * Abgleich ausführen, falls vorgemerkt (bzw. $force). Nur ein Prozess gleichzeitig (Sperrdatei).
     * @return ?array Statistik oder null (gesperrt / nichts zu tun)
     */
    public static function process(bool $force = false, bool $full = false, bool $vectors = true, float $budget = 0.0, ?\Closure $log = null): ?array
    {
        $dir = self::dir();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $lock = @fopen($dir . '/.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return null;
        try {
            $dirty = self::isDirty();
            if ($dirty) @unlink($dir . '/dirty');
            try {
                // Index immer mit den Adressen der Hauptdomain (auch wenn der Abgleich während einer Anfrage auf einer Landing-Domain läuft)
                return \Core\Landings::suspend(fn() => (new Indexer($log))->sync($full, $vectors && self::semanticActive(), $budget));
            } catch (\Throwable $e) {
                if ($dirty) @touch($dir . '/dirty');
                self::state('error', mb_strimwidth($e->getMessage(), 0, 300, '…'));
                throw $e;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function pendingVectors(): int
    {
        $model = self::state('vmodel') ?? '';
        return (int) self::db()->fetchValue('SELECT COUNT(*) FROM docs WHERE vhash IS NULL OR vhash != hash OR vmodel IS NULL OR vmodel != ?', [$model]);
    }

    // ------------------------------------------------------------------ Suche

    /** Suchbegriff bereinigen: max. 200 Zeichen, Steuerzeichen raus */
    public static function clean(string $q): string
    {
        $q = (string) preg_replace('~[\x00-\x1F\x7F]+~u', ' ', $q);
        return trim(mb_substr((string) preg_replace('~\s+~u', ' ', $q), 0, 200));
    }

    /**
     * Ergebnisse für die aktuelle Sprache.
     * $o: page (1…), per, type (page|file|Kurzname einer Tabelle), facet ([feld => wert], nur mit Tabelle), semantic (bool – z. B. false
     *     bei Ratenbegrenzung), count_miss (bool)
     * @return array{q: string, items: list<array>, total: int, page: int, pages: int, mode: string, fallback: bool, types: array, type: string}
     */
    public static function query(string $q, array $o = []): array
    {
        $q = self::clean($q);
        $lang = Lang::current();
        $per = max(1, min(50, (int) ($o['per'] ?? self::PER_PAGE)));
        $page = max(1, (int) ($o['page'] ?? 1));
        $type = preg_replace('~[^a-z0-9_\-]~', '', (string) ($o['type'] ?? ''));
        $facetIn = array_filter(array_map(fn($v) => is_scalar($v) ? trim(mb_substr((string) $v, 0, 120)) : '', (array) ($o['facet'] ?? [])), fn($v) => $v !== '');
        $out = ['q' => $q, 'items' => [], 'total' => 0, 'page' => $page, 'pages' => 0, 'mode' => 'keyword', 'fallback' => false, 'types' => [], 'type' => $type,
            'facets' => [], 'facet' => $facetIn];
        if ($q === '' || !self::enabled()) return $out;

        // Vorgemerkte Änderungen (oder neuer Tag: „nur künftige Termine“, Datumsangaben): Stichwort-Index sofort abgleichen, Vektoren nach der Antwort
        if (self::isDirty() || self::state('synced_day') !== date('Y-m-d')) {
            try {
                self::process(vectors: false, budget: 8.0);
            } catch (\Throwable $e) {
                error_log('[search] inline sync: ' . $e->getMessage());
            }
            if (!self::$shutdown) { self::$shutdown = true; register_shutdown_function([self::class, 'afterResponse']); }
        }

        $filter = $type === '' ? [] : (in_array($type, ['page', 'file'], true) ? ['type' => $type] : ['table' => $type]);
        $kw = self::keyword($lang)->search($q, self::MAX_HITS, $filter);
        $kwHits = array_column($kw['hits'], null, 'id');
        $lists = [array_keys($kwHits)];
        $vecHits = [];
        if (($o['semantic'] ?? true) && self::semanticActive()) {
            if (Ai::paused()) {
                $out['fallback'] = true;
            } else {
                try {
                    $vecHits = self::semantic($q, $lang, $filter);
                    $lists[] = array_keys($vecHits);
                    $out['mode'] = 'hybrid';
                } catch (\Throwable $e) {
                    Ai::failed($e);
                    $out['fallback'] = true;
                }
            }
        }
        $ids = array_values(array_unique(array_merge(...$lists)));
        if (!$ids) {
            if (($o['count_miss'] ?? true) && $page === 1 && $type === '') Stats::miss($q, $lang);
            return $out;
        }
        $docs = self::docRows($ids, $lang);
        $ids = array_values(array_filter($ids, fn($id) => isset($docs[$id])));
        $ranked = Ranker::fuse($lists, $docs, Text::words($q), self::settings()['boost'], self::RRF_K);
        // Landing-Domain: nur Seiten der Landingpage (Einstellung „Nur Seiten der Landingpage“)
        $landing = \Core\Landings::current();
        if ($landing) $ranked = array_values(array_filter($ranked, fn($id) => $landing->searchAllows($docs[$id])));
        // Filter (Facetten) einer Tabelle: Auswahl-/Verknüpfungsfelder, die in den Such-Einstellungen der Tabelle markiert sind
        if ($type !== '' && !in_array($type, ['page', 'file'], true) && ($t = \Core\Data\Tables::findContent($type))) {
            $cfg = TableSearch::config($t);
            $facetIn = array_intersect_key($facetIn, array_flip($cfg['facets']));
            $out['facet'] = $facetIn;
            foreach ($cfg['facets'] as $fn) {
                $f = \Core\Data\Tables::field($t, $fn);
                $out['facets'][$fn] = ['label' => $f ? \Core\Data\Tables::label($f) : $fn, 'values' => []];
            }
            foreach ($ranked as $id) {
                foreach ((array) json_decode((string) ($docs[$id]['facets'] ?? ''), true) as $fn => $vals) {
                    if (!isset($out['facets'][$fn])) continue;
                    foreach ((array) $vals as $v) $out['facets'][$fn]['values'][$v] = ($out['facets'][$fn]['values'][$v] ?? 0) + 1;
                }
            }
            foreach ($out['facets'] as &$fc) arsort($fc['values']);
            unset($fc);
            $out['facets'] = array_filter($out['facets'], fn($fc) => $fc['values']);
            if ($facetIn) {
                $ranked = array_values(array_filter($ranked, function ($id) use ($docs, $facetIn) {
                    $have = (array) json_decode((string) ($docs[$id]['facets'] ?? ''), true);
                    foreach ($facetIn as $fn => $v) if (!in_array($v, (array) ($have[$fn] ?? []), true)) return false;
                    return true;
                }));
            }
        }

        // Arten für die Filter-Chips (über alle Treffer)
        foreach ($ranked as $id) {
            $d = $docs[$id];
            $key = $d['type'] === 'entry' ? (string) $d['tbl'] : (string) $d['type'];
            $out['types'][$key] ??= ['label' => (string) $d['badge'], 'n' => 0];
            $out['types'][$key]['n']++;
        }
        $out['total'] = count($ranked);
        $out['pages'] = (int) ceil($out['total'] / $per);
        $words = Text::words($q);
        foreach (array_slice($ranked, ($page - 1) * $per, $per) as $id) {
            $d = $docs[$id];
            $k = $kwHits[$id] ?? null;
            $v = $vecHits[$id] ?? null;
            // Auszug: Zusammenfassungsfeld der Tabelle (Treffer markiert), sonst Fundstelle im Text
            $snippet = ($d['summary'] ?? '') !== '' ? Text::excerpt((string) $d['summary'], $words)
                // Loupe-Ausschnitt nur, wenn der Treffer im Text liegt (sonst liefert Loupe den ungekürzten Text) und er kurz ist
                : ($k && str_contains((string) $k['text'], '<mark') && mb_strlen(strip_tags((string) $k['text'])) <= 400 ? $k['text']
                : ($v ? Text::excerpt($v['text'], $words) : (($k['text'] ?? '') ?: Text::excerpt((string) $d['excerpt'], $words))));
            $out['items'][] = [
                'id' => $id, 'type' => (string) $d['type'], 'table' => (string) $d['tbl'], 'badge' => (string) $d['badge'],
                'title' => $k ? $k['title'] : Text::mark((string) $d['title'], $words), 'title_plain' => (string) $d['title'],
                'snippet' => $snippet, 'url' => $landing ? $landing->mapHref((string) $d['url']) : (string) $d['url'], 'date' => (string) $d['date'], 'date_label' => (string) $d['date_label'],
                'origin' => (string) $d['origin'], 'image' => (int) ($d['image'] ?? 0) ?: null,
                'match' => $k && $v ? 'both' : ($k ? 'keyword' : 'semantic'),
            ];
        }
        return $out;
    }

    /** Vorschläge beim Tippen (nur Stichwortsuche – schnell, ohne KI-Anbieter) */
    public static function suggest(string $q, int $limit = 6): array
    {
        $q = self::clean($q);
        if (mb_strlen($q) < 2 || !self::enabled()) return [];
        $lang = Lang::current();
        $kw = self::keyword($lang)->search($q, $limit, []);
        $docs = self::docRows(array_column($kw['hits'], 'id'), $lang);
        $out = [];
        $landing = \Core\Landings::current();
        foreach ($kw['hits'] as $h) {
            if (!isset($docs[$h['id']])) continue;
            $d = $docs[$h['id']];
            if ($landing && !$landing->searchAllows($d)) continue;
            $out[] = ['title' => (string) $d['title'], 'url' => $landing ? $landing->mapHref((string) $d['url']) : (string) $d['url'], 'badge' => (string) $d['badge'], 'date' => (string) $d['date_label']];
        }
        return $out;
    }

    /** @return array<string, array> id => Zeile aus docs */
    private static function docRows(array $ids, string $lang): array
    {
        $out = [];
        foreach (array_chunk(array_values($ids), 400) as $chunk) {
            if (!$chunk) continue;
            $in = implode(',', array_fill(0, count($chunk), '?'));
            foreach (self::db()->fetchAll("SELECT * FROM docs WHERE lang = ? AND id IN ($in)", [$lang, ...$chunk]) as $r) $out[$r['id']] = $r;
        }
        return $out;
    }

    /**
     * Semantische Treffer: Anfrage-Vektor (Cache) → ähnlichste Abschnitte → bester Abschnitt je Dokument.
     * @return array<string, array{score: float, text: string}>
     */
    private static function semantic(string $q, string $lang, array $filter): array
    {
        $emb = Ai::embedder() ?? throw new \RuntimeException('Kein Embedding-Modell.');
        $model = $emb->id();
        $h = hash('sha256', $model . '|' . $lang . '|' . Text::fold($q));
        $row = self::db()->fetch('SELECT vec FROM qcache WHERE h = ?', [$h]);
        if ($row) {
            $vec = array_values(unpack('g*', (string) $row['vec']));
        } else {
            $vec = $emb->embed([$q], 'query')[0] ?? throw new \RuntimeException('Leere Antwort des Anbieters.');
            self::db()->query('INSERT OR REPLACE INTO qcache (h, vec, t) VALUES (?, ?, ?)', [$h, VectorStore::pack($vec), time()]);
            if (random_int(1, 100) === 1) self::db()->query('DELETE FROM qcache WHERE h NOT IN (SELECT h FROM qcache ORDER BY t DESC LIMIT 5000)');
            Ai::recovered();
        }
        $res = self::vectors()->query(new VectorQuery(new Vector($vec)), ['maxItems' => self::MAX_HITS * 2, 'lang' => $lang, 'model' => $model, 'minScore' => 0.0]);
        $best = [];
        foreach ($res as $d) {
            $m = $d->getMetadata();
            $doc = (string) $m['doc'];
            if (!isset($best[$doc]) || $d->getScore() > $best[$doc]['score']) {
                $best[$doc] = ['score' => (float) $d->getScore(), 'text' => (string) ($m['text'] ?? '')];
            }
        }
        if (!$best) return [];
        // Nur ähnliche Inhalte: absolute Untergrenze (je Modell verschieden skaliert) + höchstens 0,06 unter dem besten Treffer
        $top = max(array_column($best, 'score'));
        $min = max(Ranker::minSimilarity($model), $top - 0.06);
        $best = array_filter($best, fn($b) => $b['score'] >= $min);
        if ($filter) {
            $rows = self::docRows(array_keys($best), $lang);
            $best = array_filter($best, fn($b, $id) => isset($rows[$id]) && (isset($filter['type']) ? $rows[$id]['type'] === $filter['type'] : $rows[$id]['tbl'] === $filter['table']), ARRAY_FILTER_USE_BOTH);
        }
        uasort($best, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($best, 0, 20, true);
    }

    // ------------------------------------------------------------------ Status & Gesundheit

    /** Übersicht für Verwaltung und CLI */
    public static function status(): array
    {
        $langs = [];
        foreach (array_keys(Lang::all()) as $l) {
            $langs[$l] = [
                'docs' => (int) self::db()->fetchValue('SELECT COUNT(*) FROM docs WHERE lang = ?', [$l]),
                'keyword' => self::keyword($l)->count(),
                'vectors' => (int) self::db()->fetchValue('SELECT COUNT(*) FROM docs WHERE lang = ? AND vhash = hash AND vmodel = ?', [$l, self::state('vmodel') ?? '']),
            ];
        }
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::dir(), \FilesystemIterator::SKIP_DOTS)) as $f) $size += $f->getSize();
        return [
            'enabled' => self::enabled(), 'languages' => $langs, 'dirty' => self::isDirty(),
            'synced_at' => self::state('synced_at'), 'full_at' => self::state('full_at'), 'error' => self::state('error'), 'vector_error' => self::state('vector_error'),
            'semantic' => self::semanticActive(), 'vmodel' => self::state('vmodel'), 'pending' => self::semanticActive() ? self::pendingVectors() : 0,
            'chunks' => self::vectors()->count(), 'bytes' => $size, 'dir' => self::dir(),
        ];
    }

    public static function health(): array
    {
        if (!self::enabled()) return [];
        $dir = self::dir();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $ok = is_writable($dir);
        try {
            $n = (int) self::db()->fetchValue('SELECT COUNT(*) FROM docs');
        } catch (\Throwable) {
            $ok = false;
            $n = 0;
        }
        return ['Suchindex beschreibbar (' . $n . ' Dokumente)' => $ok];
    }

    /** Index löschen (z. B. nach Wiederherstellen einer Sicherung) – wird beim nächsten Abgleich neu aufgebaut */
    public static function reset(): void
    {
        foreach (array_keys(Lang::all()) as $l) self::keyword($l)->clear();
        self::db()->pdo->exec('DELETE FROM docs');
        self::vectors()->clear();
        self::db()->pdo->exec('DELETE FROM qcache');
    }
}
