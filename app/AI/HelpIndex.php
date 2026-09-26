<?php
declare(strict_types=1);

namespace Core\AI;

use Core\Database;
use Core\Search\Text;
use Core\Search\VectorStore;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\VectorQuery;

/**
 * Kleiner Suchindex über die Hilfe für den Redaktions-Assistenten (Core\AI\Assistant): Handbuch (help/manual, inkl.
 * Theme-Kapitel), technische Dokumentation (help/technical) und Tutorials (app/Admin/tutorials.php + Theme). Abschnitte
 * je Kapitel bzw. Zwischenüberschrift (h3) mit Link auf den Anker. Die Wissensdatenbank (Core\Support) fragt der
 * Assistent direkt ab (eigene Suche, eigene Rechte).
 *
 * Speicher: {storage}/ai/help.sqlite – Tabelle sections, Volltext (SQLite FTS5, Umlaute egal, Präfixe) und optional
 * Vektoren (Embeddings der Website, VectorStore). Stichwort-Teil baut sich beim ersten Bedarf bzw. nach Änderungen der
 * Hilfe-Dateien selbst neu; Vektoren: `php bin/console search:index --help` (Cron) oder nach der Antwort (PHP-FPM).
 */
final class HelpIndex
{
    private const VERSION = 2;
    /** Wörter je Abschnitt (längere Kapitelteile werden geteilt) */
    private const WORDS = 260;

    private static array $db = [];

    public static function db(): Database
    {
        $k = site()->key;
        if (isset(self::$db[$k])) return self::$db[$k];
        $dir = site()->storage('ai');
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $db = new Database(['driver' => 'sqlite', 'path' => $dir . '/help.sqlite']);
        $db->pdo->exec('CREATE TABLE IF NOT EXISTS sections (id TEXT PRIMARY KEY, kind TEXT NOT NULL, title TEXT NOT NULL, url TEXT NOT NULL, text TEXT NOT NULL, hash TEXT NOT NULL)');
        $db->pdo->exec('CREATE TABLE IF NOT EXISTS meta (k TEXT PRIMARY KEY, v TEXT)');
        try {
            $db->pdo->exec("CREATE VIRTUAL TABLE IF NOT EXISTS help_fts USING fts5(id UNINDEXED, title, text, tokenize = 'unicode61 remove_diacritics 2')");
        } catch (\Throwable) {
            // Ohne FTS5: Suche per LIKE über sections
        }
        (new VectorStore($db))->setup();
        return self::$db[$k] = $db;
    }

    private static function fts(): bool
    {
        return (bool) self::db()->fetchValue("SELECT COUNT(*) FROM sqlite_master WHERE name = 'help_fts'");
    }

    private static function meta(string $k, ?string $v = null): ?string
    {
        if (func_num_args() > 1) {
            self::db()->query('INSERT INTO meta (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v', [$k, $v]);
            return $v;
        }
        $r = self::db()->fetchValue('SELECT v FROM meta WHERE k = ?', [$k]);
        return $r === null ? null : (string) $r;
    }

    /** Stand der Hilfe-Dateien (Änderung → Stichwort-Index neu) */
    public static function signature(): string
    {
        $files = [...(glob(ROOT . '/app/Admin/views/help/manual/*.php') ?: []), ...(glob(ROOT . '/app/Admin/views/help/technical/*.php') ?: []),
            ROOT . '/app/Admin/views/help/manual.php', ROOT . '/app/Admin/views/help/technical.php', ROOT . '/app/Admin/tutorials.php'];
        $theme = app()->theme->path . '/docs';
        if (is_dir($theme)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($theme, \FilesystemIterator::SKIP_DOTS)) as $f) if ($f->isFile()) $files[] = $f->getPathname();
        }
        $sig = CMS_VERSION . '|' . self::VERSION . '|' . app()->theme->name;
        foreach ($files as $f) $sig .= '|' . basename((string) $f) . ':' . (is_file($f) ? filemtime($f) . ':' . filesize($f) : 0);
        return md5($sig);
    }

    /** Index aktuell? Sonst Stichwort-Teil neu bauen (schnell, ohne KI-Anbieter) */
    public static function ensure(): void
    {
        try {
            if (self::meta('signature') !== self::signature()) self::rebuild(false);
        } catch (\Throwable $e) {
            error_log('[ai] help index: ' . $e->getMessage());
        }
    }

    /**
     * Neu aufbauen. $vectors: fehlende Vektoren erzeugen (Embeddings der Website); $budget: Sekunden für Vektoren (0 = ohne Grenze)
     * @return array{sections: int, vectors: int, error: ?string}
     */
    public static function rebuild(bool $vectors = true, ?\Closure $log = null, float $budget = 0.0): array
    {
        $t0 = microtime(true);
        $db = self::db();
        $docs = self::documents();
        $have = array_column($db->fetchAll('SELECT id, hash FROM sections'), 'hash', 'id');
        $db->transaction(function (Database $db) use ($docs, $have) {
            $fts = self::fts();
            foreach ($docs as $id => $d) {
                if (($have[$id] ?? null) === $d['hash']) continue;
                $db->query('INSERT INTO sections (id, kind, title, url, text, hash) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(id) DO UPDATE SET kind = excluded.kind,
                    title = excluded.title, url = excluded.url, text = excluded.text, hash = excluded.hash', [$id, $d['kind'], $d['title'], $d['url'], $d['text'], $d['hash']]);
                if ($fts) {
                    $db->query('DELETE FROM help_fts WHERE id = ?', [$id]);
                    $db->query('INSERT INTO help_fts (id, title, text) VALUES (?, ?, ?)', [$id, $d['title'], $d['text']]);
                }
                $db->query('DELETE FROM vectors WHERE doc = ?', [$id]);
            }
            foreach (array_diff(array_keys($have), array_keys($docs)) as $gone) {
                $db->query('DELETE FROM sections WHERE id = ?', [$gone]);
                if ($fts) $db->query('DELETE FROM help_fts WHERE id = ?', [$gone]);
                $db->query('DELETE FROM vectors WHERE doc = ?', [$gone]);
            }
        });
        self::meta('signature', self::signature());
        self::meta('built_at', date('c'));
        if ($log) $log('  Hilfe: ' . count($docs) . ' Abschnitte (Handbuch, Technik, Tutorials)');
        $out = ['sections' => count($docs), 'vectors' => 0, 'error' => null];
        if ($vectors && Ai::enabled('embed')) {
            try {
                $out['vectors'] = self::embedMissing($log, $budget, $t0);
            } catch (\Throwable $e) {
                $out['error'] = Ai::shortError($e);
                Ai::failed($e);
            }
        }
        return $out;
    }

    /** Fehlende Vektoren erzeugen (Modell der Website) */
    private static function embedMissing(?\Closure $log, float $budget, float $t0): int
    {
        $emb = Ai::embedder(null, Ai::config()['index_timeout']);
        if (!$emb) return 0;
        $model = $emb->id();
        $db = self::db();
        $db->query('DELETE FROM vectors WHERE model != ?', [$model]);
        $todo = $db->fetchAll('SELECT s.id, s.title, s.text FROM sections s WHERE NOT EXISTS (SELECT 1 FROM vectors v WHERE v.doc = s.id AND v.model = ?)', [$model]);
        if ($log && $todo) $log('  Hilfe-Vektoren: ' . count($todo) . ' Abschnitte (' . $model . ')');
        $store = new VectorStore($db);
        $n = 0;
        foreach (array_chunk($todo, 24) as $batch) {
            if ($budget > 0 && microtime(true) - $t0 > $budget) break;
            $vecs = $emb->embed(array_map(fn($r) => $r['title'] . "\n" . mb_substr($r['text'], 0, 2000), $batch), 'document');
            $vd = [];
            foreach ($batch as $i => $r) {
                $vd[] = new VectorDocument('h:' . $r['id'], new Vector($vecs[$i]), new Metadata(['doc' => $r['id'], 'lang' => 'de', 'model' => $model]));
            }
            $store->add($vd);
            $n += count($batch);
        }
        self::meta('vmodel', $model);
        Ai::recovered();
        return $n;
    }

    /** Stand für die Oberfläche/CLI */
    public static function status(): array
    {
        return ['sections' => (int) self::db()->fetchValue('SELECT COUNT(*) FROM sections'), 'vectors' => (int) self::db()->fetchValue('SELECT COUNT(*) FROM vectors'),
            'built_at' => self::meta('built_at'), 'current' => self::meta('signature') === self::signature()];
    }

    // ================================================================== Quellen

    /** Alle Abschnitte: id => [kind, title, url, text, hash] */
    public static function documents(): array
    {
        $out = [];
        $add = function (string $id, string $kind, string $title, string $url, string $text) use (&$out) {
            $text = trim((string) preg_replace('~\s+~u', ' ', $text));
            if ($text === '' || mb_strlen($text) < 40) return;
            foreach (Text::chunks($text, self::WORDS, 40) as $i => $c) {
                $key = $id . ($i ? '~' . $i : '');
                $out[$key] = ['kind' => $kind, 'title' => $title, 'url' => $url, 'text' => $c, 'hash' => md5($title . '|' . $url . '|' . $c)];
            }
        };
        foreach (self::render('manual') as $s) $add('m:' . $s['id'], 'manual', $s['title'], url('/admin/hilfe') . '#' . $s['anchor'], $s['text']);
        foreach (self::render('technical') as $s) $add('t:' . $s['id'], 'tech', $s['title'], url('/admin/hilfe/technik') . '#' . $s['anchor'], $s['text']);
        foreach (self::tutorials() as $slug => $t) {
            $text = implode(' ', array_filter([(string) ($t['summary'] ?? ''), (string) ($t['goal'] ?? ''), implode(' ', array_map(fn($x) => strip_tags((string) $x), (array) ($t['steps'] ?? []))),
                implode(' ', array_map(fn($x) => strip_tags((string) $x), (array) ($t['tips'] ?? []))), implode(' ', array_map(fn($x) => strip_tags((string) $x), (array) ($t['pitfalls'] ?? [])))]));
            $add('v:' . $slug, 'tutorial', (string) ($t['title'] ?? $slug), url('/admin/hilfe/tutorials/' . rawurlencode((string) $slug)), html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
        }
        return $out;
    }

    /** Tutorials (Kern + Theme, wie HelpController) */
    private static function tutorials(): array
    {
        $core = require ROOT . '/app/Admin/tutorials.php';
        $file = app()->theme->path . '/docs/tutorials.php';
        $theme = is_file($file) ? (array) require $file : [];
        $tracks = array_merge((array) $core['tracks'], (array) ($theme['tracks'] ?? []));
        return array_filter(array_merge((array) $core['tutorials'], (array) ($theme['tutorials'] ?? [])),
            fn($t) => is_array($t) && isset($tracks[$t['track'] ?? '']) && (empty($t['feature']) || \Core\Features::on((string) $t['feature'], false)));
    }

    /**
     * Handbuch bzw. technische Dokumentation rendern und in Abschnitte teilen (Kapitel = <section class="doc-ch" id>,
     * darin je <h3> ein Abschnitt). @return list<array{id: string, anchor: string, title: string, text: string}>
     */
    private static function render(string $which): array
    {
        try {
            $vars = ['css' => ['css/docs.css'], 'blocks' => app()->theme->blocks(), 'themeDoc' => []];
            if ($which === 'manual') {
                $file = app()->theme->path . '/docs/manual.php';
                if (is_file($file)) {
                    ob_start();
                    $def = (static fn(string $__file) => include $__file)($file);
                    ob_end_clean();
                    if (is_array($def)) $vars['themeDoc'] = $def + ['dir' => dirname($file)];
                }
            } else {
                $vars += ['openapi' => \Core\Api\OpenApi::spec(), 'mcp' => \Core\Http\Controllers\McpController::catalog(), 'fieldTypes' => \Core\Fields::TYPES];
            }
            $html = \Core\Theme::capture(ROOT . '/app/Admin/views/help/' . $which . '.php', $vars);
        } catch (\Throwable $e) {
            error_log('[ai] help render ' . $which . ': ' . $e->getMessage());
            return [];
        }
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);
        $out = [];
        foreach ($xp->query('//section[contains(concat(" ", normalize-space(@class), " "), " doc-ch ") and @id]') ?: [] as $sec) {
            /** @var \DOMElement $sec */
            $id = $sec->getAttribute('id');
            if (in_array($id, ['inhalt', 'l-intro'], true)) continue;
            $h2 = $xp->query('.//h2', $sec)->item(0);
            $chapter = trim((string) preg_replace('~\s+~u', ' ', $h2?->textContent ?? $id));
            $chapter = trim((string) preg_replace('~^\d+\s*~u', '', rtrim($chapter, '.')));
            $parts = [['title' => $chapter, 'text' => '']];
            $walk = function (\DOMNode $n) use (&$walk, &$parts, $chapter) {
                foreach ($n->childNodes as $c) {
                    if ($c instanceof \DOMElement && in_array(strtolower($c->tagName), ['script', 'style', 'svg', 'nav', 'figure', 'button', 'form'], true)) continue;
                    if ($c instanceof \DOMElement && strtolower($c->tagName) === 'h2') continue;
                    if ($c instanceof \DOMElement && strtolower($c->tagName) === 'h3') {
                        $parts[] = ['title' => $chapter . ' › ' . trim((string) preg_replace('~\s+~u', ' ', $c->textContent)), 'text' => ''];
                        continue;
                    }
                    if ($c instanceof \DOMElement && in_array(strtolower($c->tagName), ['p', 'li', 'td', 'th', 'dt', 'dd', 'pre', 'blockquote', 'h4', 'figcaption', 'summary'], true)) {
                        $parts[count($parts) - 1]['text'] .= ' ' . $c->textContent . (in_array(strtolower($c->tagName), ['li', 'td', 'th'], true) ? ';' : '');
                        continue;
                    }
                    if ($c->hasChildNodes()) $walk($c);
                    elseif ($c instanceof \DOMText && trim($c->textContent) !== '') $parts[count($parts) - 1]['text'] .= ' ' . $c->textContent;
                }
            };
            $walk($sec);
            foreach ($parts as $i => $p) {
                $out[] = ['id' => $id . ($i ? '-' . $i : ''), 'anchor' => $id, 'title' => $p['title'], 'text' => $p['text']];
            }
        }
        return $out;
    }

    // ================================================================== Suche

    /**
     * Passende Abschnitte (Stichwort + – falls vorhanden – Vektoren, Reciprocal Rank Fusion).
     * $tech: technische Dokumentation gleichrangig (Administration) oder nachrangig (Redaktion)
     * @return list<array{title: string, kind: string, url: string, text: string}>
     */
    public static function query(string $q, int $k = 5, bool $tech = true): array
    {
        self::ensure();
        $db = self::db();
        $lists = [];
        $words = array_values(array_filter(\Core\Support\Search::words($q), fn($w) => mb_strlen($w) >= 2));
        if ($words) {
            if (self::fts()) {
                $match = implode(' OR ', array_map(fn($w) => '"' . str_replace('"', '', $w) . '"*', array_slice($words, 0, 12)));
                try {
                    $lists[] = array_column($db->fetchAll('SELECT id FROM help_fts WHERE help_fts MATCH ? ORDER BY bm25(help_fts, 0, 6.0, 1.0) LIMIT 20', [$match]), 'id');
                } catch (\Throwable $e) {
                    error_log('[ai] help fts: ' . $e->getMessage());
                }
            } else {
                $score = [];
                $params = [];
                foreach (array_slice($words, 0, 8) as $w) { $score[] = '(CASE WHEN LOWER(title) LIKE ? THEN 3 ELSE 0 END + CASE WHEN LOWER(text) LIKE ? THEN 1 ELSE 0 END)'; array_push($params, "%$w%", "%$w%"); }
                $lists[] = array_column($db->fetchAll('SELECT id, (' . implode('+', $score) . ') s FROM sections WHERE s > 0 ORDER BY s DESC LIMIT 20', $params), 'id');
            }
        }
        // Semantisch (nur wenn Vektoren zum aktuellen Modell vorhanden sind und der Anbieter nicht pausiert)
        $vmodel = self::meta('vmodel');
        if ($vmodel && Ai::enabled('embed') && !Ai::paused() && (int) $db->fetchValue('SELECT COUNT(*) FROM vectors WHERE model = ?', [$vmodel]) > 0) {
            try {
                $emb = Ai::embedder(null, 6.0);
                if ($emb && $emb->id() === $vmodel) {
                    $vec = $emb->embed([$q], 'query')[0] ?? null;
                    if ($vec) {
                        $res = (new VectorStore($db))->query(new VectorQuery(new Vector($vec)), ['maxItems' => 20, 'model' => $vmodel]);
                        $lists[] = array_map(fn($d) => (string) $d->getMetadata()['doc'], is_array($res) ? $res : iterator_to_array($res));
                    }
                }
            } catch (\Throwable $e) {
                Ai::failed($e);
            }
        }
        $score = [];
        foreach ($lists as $list) {
            foreach (array_values($list) as $rank => $id) $score[$id] = ($score[$id] ?? 0) + 1 / (60 + $rank + 1);
        }
        if (!$score) return [];
        $rows = [];
        $in = implode(',', array_fill(0, count($score), '?'));
        foreach ($db->fetchAll("SELECT * FROM sections WHERE id IN ($in)", array_keys($score)) as $r) $rows[$r['id']] = $r;
        foreach ($score as $id => &$s) if (!$tech && ($rows[$id]['kind'] ?? '') === 'tech') $s *= 0.6;
        unset($s);
        arsort($score);
        $out = [];
        $seen = [];
        foreach (array_keys($score) as $id) {
            $r = $rows[$id] ?? null;
            if (!$r) continue;
            // Höchstens zwei Abschnitte je Kapitel-Anker (mehr Vielfalt)
            $key = $r['url'];
            if (($seen[$key] = ($seen[$key] ?? 0) + 1) > 2) continue;
            $out[] = ['title' => (string) $r['title'], 'kind' => (string) $r['kind'], 'url' => (string) $r['url'], 'text' => mb_strimwidth((string) $r['text'], 0, 1500, '…')];
            if (count($out) >= $k) break;
        }
        return $out;
    }
}
