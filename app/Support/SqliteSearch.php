<?php
declare(strict_types=1);

namespace Core\Support;

use Core\Database;

/**
 * Volltextsuche in der zentralen Support-Datenbank: SQLite FTS5 mit „unicode61 remove_diacritics 2“
 * (Groß/klein und Umlaute/Akzente egal: „Uberschrift“ findet „Überschrift“), Präfix-Suche („bild*“),
 * Gewichtung Titel > Tags > Text (bm25). Ohne FTS5 (seltene PHP-Builds): gleiche Tabelle als normale
 * Tabelle, Suche per LIKE.
 */
final class SqliteSearch implements SearchBackend
{
    private static ?bool $fts = null;

    public function __construct(private Database $db) {}

    public static function migrate(Database $db): void
    {
        $exists = (bool) $db->fetchValue("SELECT COUNT(*) FROM sqlite_master WHERE name = 'search_idx'");
        if ($exists) {
            self::$fts = (bool) $db->fetchValue("SELECT COUNT(*) FROM sqlite_master WHERE name = 'search_idx' AND sql LIKE '%fts5%'");
            return;
        }
        try {
            $db->pdo->exec("CREATE VIRTUAL TABLE search_idx USING fts5(kind UNINDEXED, ref UNINDEXED, title, body, tags, tokenize = 'unicode61 remove_diacritics 2')");
            self::$fts = true;
        } catch (\Throwable) {
            $db->pdo->exec('CREATE TABLE IF NOT EXISTS search_idx (kind VARCHAR(12) NOT NULL, ref INT NOT NULL, title TEXT, body TEXT, tags TEXT)');
            self::$fts = false;
        }
    }

    public static function fts(): bool
    {
        if (self::$fts === null) Support::db();
        return (bool) self::$fts;
    }

    public function index(string $kind, int $id, string $title, string $body, string $tags): void
    {
        $this->remove($kind, $id);
        $this->db->insert('search_idx', ['kind' => $kind, 'ref' => $id, 'title' => $title, 'body' => $body, 'tags' => $tags]);
    }

    public function remove(string $kind, int $id): void
    {
        $this->db->query('DELETE FROM search_idx WHERE kind = ? AND ref = ?', [$kind, $id]);
    }

    public function find(string $q, array $kinds, int $limit, bool $loose = false): array
    {
        $words = Search::words($q);
        if (!$words) $words = Search::words($q, false);
        if (!$words) return [];
        $kinds = array_values(array_intersect($kinds, ['article', 'question'])) ?: ['article'];
        $in = implode(',', array_fill(0, count($kinds), '?'));
        if (self::fts()) {
            // Jedes Wort als Präfix; Anführungszeichen maskieren Sonderzeichen der FTS-Syntax
            $terms = array_map(fn($w) => '"' . str_replace('"', '', $w) . '"*', $words);
            $match = implode($loose ? ' OR ' : ' AND ', $terms);
            try {
                $rows = $this->db->fetchAll("SELECT kind, ref, bm25(search_idx, 0, 0, 10.0, 1.0, 4.0) AS rank,
                        snippet(search_idx, 3, char(2), char(3), '…', 14) AS snip
                    FROM search_idx WHERE search_idx MATCH ? AND kind IN ($in) ORDER BY rank LIMIT " . (int) $limit, [$match, ...$kinds]);
                return array_map(fn($r) => ['kind' => $r['kind'], 'id' => (int) $r['ref'], 'score' => -(float) $r['rank'], 'snippet' => (string) $r['snip']], $rows);
            } catch (\Throwable $e) {
                error_log('[support] fts: ' . $e->getMessage());
            }
        }
        // LIKE: Treffer je Wort zählen, Titel zählt dreifach
        $score = [];
        $params = [];
        foreach ($words as $w) {
            $score[] = '(CASE WHEN LOWER(title) LIKE ? THEN 3 ELSE 0 END + CASE WHEN LOWER(tags) LIKE ? THEN 2 ELSE 0 END + CASE WHEN LOWER(body) LIKE ? THEN 1 ELSE 0 END)';
            array_push($params, "%$w%", "%$w%", "%$w%");
        }
        $sum = implode(' + ', $score);
        $cond = implode($loose ? ' OR ' : ' AND ', array_map(fn($s) => "$s > 0", $score));
        $rows = $this->db->fetchAll("SELECT kind, ref, ($sum) AS s FROM search_idx WHERE kind IN ($in) AND ($cond) ORDER BY s DESC LIMIT " . (int) $limit,
            [...$params, ...$kinds, ...$params]);
        return array_map(fn($r) => ['kind' => $r['kind'], 'id' => (int) $r['ref'], 'score' => (float) $r['s'], 'snippet' => null], $rows);
    }
}
