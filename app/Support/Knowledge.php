<?php
declare(strict_types=1);

namespace Core\Support;

use Core\Sites;

/**
 * Wissensdatenbank: Artikel mit Versionen, Tags, Sichtbarkeit (alle | bestimmte Websites | nur Support-Team),
 * Aufrufen und „Hilfreich?“-Rückmeldungen. Anlegen und Bearbeiten: Support-Team.
 */
final class Knowledge
{
    /** WHERE für sichtbare Artikel */
    private static function scope(string $a = 'a'): array
    {
        if (Support::isStaff()) return ['1 = 1', []];
        return ["$a.status = 'published' AND ($a.visibility = 'all' OR ($a.visibility = 'sites' AND $a.sites LIKE ?))", ['%,' . site()->key . ',%']];
    }

    public static function canSee(array $a): bool
    {
        if (Support::isStaff()) return true;
        if ($a['status'] !== 'published') return false;
        return $a['visibility'] === 'all' || ($a['visibility'] === 'sites' && str_contains((string) $a['sites'], ',' . site()->key . ','));
    }

    public static function find(int $id): ?array
    {
        $a = Support::db()->fetch('SELECT * FROM articles WHERE id = ?', [$id]);
        return $a && self::canSee($a) ? $a : null;
    }

    /** @return array{rows:list<array>,total:int,pages:int} */
    public static function list(array $f, int $per = 20): array
    {
        [$w, $p] = self::scope();
        $order = match ($f['sort'] ?? '') {
            'beliebt' => '(a.views + 5 * a.helpful_yes - 3 * a.helpful_no) DESC, a.updated_at DESC',
            'titel' => 'a.title COLLATE NOCASE',
            default => 'a.updated_at DESC',
        };
        if (($f['tag'] ?? '') !== '') { $w .= ' AND a.tags LIKE ?'; $p[] = '%,' . $f['tag'] . ',%'; }
        $db = Support::db();
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $ids = array_column(array_filter(Search::find($q, ['article'], 200), fn($h) => $h['kind'] === 'article'), 'snippet', 'id');
            if (!$ids) return ['rows' => [], 'total' => 0, 'pages' => 1];
            $rows = $db->fetchAll("SELECT a.* FROM articles a WHERE $w AND a.id IN (" . implode(',', array_map('intval', array_keys($ids))) . ')', $p);
            $pos = array_flip(array_keys($ids));
            usort($rows, fn($x, $y) => $pos[$x['id']] <=> $pos[$y['id']]);
            foreach ($rows as &$r) $r['snippet'] = $ids[$r['id']];
            return ['rows' => $rows, 'total' => count($rows), 'pages' => 1];
        }
        $total = (int) $db->fetchValue("SELECT COUNT(*) FROM articles a WHERE $w", $p);
        $page = max(1, (int) ($f['page'] ?? 1));
        $rows = $db->fetchAll("SELECT a.* FROM articles a WHERE $w ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $per))];
    }

    /** Für Spotlight und „ähnliche Artikel“ */
    public static function search(string $q, int $n = 5, bool $loose = false): array
    {
        $out = [];
        foreach (Search::find($q, ['article'], $n * 4, $loose) as $h) {
            if ($h['kind'] !== 'article') continue;
            $a = self::find($h['id']);
            if ($a && ($a['status'] === 'published' || !$loose)) $out[] = $a + ['snippet' => $h['snippet']];
            if (count($out) >= $n) break;
        }
        return $out;
    }

    public static function related(array $a, int $n = 4): array
    {
        $q = $a['title'] . ' ' . str_replace(',', ' ', (string) $a['tags']);
        return array_values(array_filter(self::search($q, $n + 1, true), fn($x) => (int) $x['id'] !== (int) $a['id']));
    }

    public static function popular(int $n = 5): array
    {
        return self::list(['sort' => 'beliebt'], $n)['rows'];
    }

    public static function newest(int $n = 5): array
    {
        return self::list([], $n)['rows'];
    }

    /** Einmal je Sitzung zählen */
    public static function countView(int $id): void
    {
        $seen = (array) app()->session->get('support_seen_a', []);
        if (in_array($id, $seen, true)) return;
        $seen[] = $id;
        app()->session->set('support_seen_a', array_slice($seen, -200));
        Support::db()->query('UPDATE articles SET views = views + 1 WHERE id = ?', [$id]);
    }

    public static function feedback(int $id, bool $helpful): void
    {
        $db = Support::db();
        $db->query('INSERT INTO article_feedback (article_id, user_key, helpful, created_at) VALUES (?, ?, ?, ?)
            ON CONFLICT(article_id, user_key) DO UPDATE SET helpful = excluded.helpful, created_at = excluded.created_at', [$id, Support::me(), (int) $helpful, now()]);
        $db->query('UPDATE articles SET helpful_yes = (SELECT COUNT(*) FROM article_feedback WHERE article_id = ? AND helpful = 1),
            helpful_no = (SELECT COUNT(*) FROM article_feedback WHERE article_id = ? AND helpful = 0) WHERE id = ?', [$id, $id, $id]);
    }

    public static function myFeedback(int $id): ?bool
    {
        $v = Support::db()->fetchValue('SELECT helpful FROM article_feedback WHERE article_id = ? AND user_key = ?', [$id, Support::me()]);
        return $v === null ? null : (bool) $v;
    }

    /**
     * Anlegen oder ändern (Support-Team). Jede gespeicherte Fassung landet in den Versionen.
     * @return int Artikel-ID
     */
    public static function save(?int $id, array $in, string $note = ''): int
    {
        $db = Support::db();
        $vis = in_array($in['visibility'] ?? '', ['all', 'sites', 'staff'], true) ? $in['visibility'] : 'all';
        $sites = array_values(array_intersect((array) ($in['sites'] ?? []), array_keys(Sites::all())));
        if ($vis === 'sites' && !$sites) $vis = 'staff';
        $data = [
            'title' => mb_substr(trim((string) $in['title']), 0, 160),
            'body' => mb_substr(trim((string) $in['body']), 0, 50000),
            'tags' => Support::tagString(Support::tags($in['tags'] ?? '')),
            'visibility' => $vis,
            'sites' => $vis === 'sites' ? ',' . implode(',', $sites) . ',' : '',
            'status' => ($in['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
            'editor_key' => Support::me(), 'editor_name' => Support::myName(), 'updated_at' => now(),
        ];
        if ($id) {
            $db->update('articles', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = $db->insert('articles', $data + ['author_key' => Support::me(), 'author_name' => Support::myName(), 'created_at' => now(),
                'source_issue_id' => isset($in['source_issue_id']) ? (int) $in['source_issue_id'] : null]);
        }
        $db->insert('article_revisions', ['article_id' => $id, 'title' => $data['title'], 'body' => $data['body'], 'tags' => $data['tags'],
            'visibility' => $data['visibility'], 'sites' => $data['sites'], 'editor_key' => $data['editor_key'], 'editor_name' => $data['editor_name'],
            'note' => mb_substr(trim($note), 0, 200), 'created_at' => now()]);
        Search::index('article', $id, $data['title'], $data['body'], $data['tags']);
        return $id;
    }

    public static function revisions(int $id): array
    {
        return Support::db()->fetchAll('SELECT * FROM article_revisions WHERE article_id = ? ORDER BY id DESC', [$id]);
    }

    public static function delete(int $id): void
    {
        $db = Support::db();
        foreach (['article_revisions', 'article_feedback'] as $t) $db->query("DELETE FROM $t WHERE article_id = ?", [$id]);
        $db->query('DELETE FROM articles WHERE id = ?', [$id]);
        $db->query('UPDATE issues SET kb_article_id = NULL WHERE kb_article_id = ?', [$id]);
        Search::remove('article', $id);
    }

    /** Tags mit Anzahl (sichtbare Artikel + Fragen) */
    public static function tagCloud(): array
    {
        [$w, $p] = self::scope();
        [$wq, $pq] = Questions::scope();
        $count = [];
        foreach (Support::db()->fetchAll("SELECT tags FROM articles a WHERE $w UNION ALL SELECT tags FROM questions q WHERE $wq", [...$p, ...$pq]) as $r) {
            foreach (Support::tagList((string) $r['tags']) as $t) $count[$t] = ($count[$t] ?? 0) + 1;
        }
        arsort($count);
        return $count;
    }

    /**
     * Entwurf eines Wissensartikels aus einer gelösten Meldung: Problem + öffentliche Antworten des Teams,
     * ohne Kontext, Namen, E-Mail-Adressen, Telefonnummern und Adressen der Website.
     */
    public static function draftFromIssue(array $issue): array
    {
        $posts = array_filter(Tickets::posts($issue), fn($p) => $p['kind'] === 'reply' && !(int) $p['internal'] && trim((string) $p['body']) !== '');
        $answers = array_filter($posts, fn($p) => (int) $p['author_staff'] === 1);
        $body = '### ' . __('Problem') . "\n\n" . self::anonymise((string) $issue['body'], $issue)
            . "\n\n### " . __('Lösung') . "\n\n" . ($answers ? implode("\n\n", array_map(fn($p) => self::anonymise((string) $p['body'], $issue), $answers)) : __('(Lösung hier beschreiben)'));
        return ['title' => self::anonymise((string) $issue['title'], $issue), 'body' => $body,
            'tags' => $issue['category'] === 'fehler' ? 'fehler' : '',
            'visibility' => 'all', 'sites' => [], 'status' => 'published'];
    }

    public static function anonymise(string $text, array $issue = []): string
    {
        $t = preg_replace('~[\w.+-]+@[\w-]+(\.[\w-]+)+~u', '[' . __('E-Mail') . ']', $text);
        // Telefonnummern: mindestens 7 Ziffern, keine Datumsangaben/Versionsnummern
        $t = preg_replace_callback('~(?<![\w/.])(\+|00)?\d[\d ()/-]{5,}\d(?![\w.])~', fn($m) => preg_match_all('~\d~', $m[0]) >= 7
            && !preg_match('~^\d{4}-\d{2}-\d{2}$~', trim($m[0])) ? '[' . __('Telefon') . ']' : $m[0], $t);
        $t = preg_replace('~\b\d{1,3}(\.\d{1,3}){3}\b~', '[IP]', $t);
        // Adressen der Websites dieser Installation
        foreach (Sites::all() as $cfg) {
            foreach ((array) ($cfg['hosts'] ?? []) as $h) {
                if ($h !== '') $t = preg_replace('~(https?://)?' . preg_quote((string) $h, '~') . '~i', '[' . __('Website') . ']', $t);
            }
        }
        foreach (array_filter([(string) ($issue['reporter_name'] ?? '')]) as $name) {
            foreach (array_unique([$name, ...array_filter(preg_split('~\s+~', $name), fn($w) => mb_strlen($w) >= 3)]) as $part) {
                if (!str_contains($part, '@')) $t = preg_replace('~\b' . preg_quote($part, '~') . '\b~iu', '[' . __('Name') . ']', $t);
            }
        }
        return (string) $t;
    }
}
