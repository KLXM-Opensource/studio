<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Fragen & Antworten (wie Stack Overflow): Fragen mit Tags, Antworten, Stimmen (+1 je Person und Beitrag),
 * akzeptierte Antwort (Fragesteller:in oder Support-Team), Aufrufe.
 * Sichtbarkeit einer Frage: „all“ (alle Websites) oder „site“ (nur die eigene Website + Support-Team).
 * Personen anderer Websites erscheinen anonym (Support::authorLabel).
 */
final class Questions
{
    public static function scope(string $a = 'q'): array
    {
        if (Support::isStaff()) return ['1 = 1', []];
        return ["($a.visibility = 'all' OR $a.site = ?)", [site()->key]];
    }

    public static function canSee(array $q): bool
    {
        return Support::isStaff() || $q['visibility'] === 'all' || $q['site'] === site()->key;
    }

    public static function find(int $id): ?array
    {
        $q = Support::db()->fetch('SELECT * FROM questions WHERE id = ?', [$id]);
        return $q && self::canSee($q) ? $q : null;
    }

    public static function canEdit(array $row): bool
    {
        return Support::isStaff() || $row['author_key'] === Support::me();
    }

    /** @return array{rows:list<array>,total:int,pages:int} */
    public static function list(array $f, int $per = 20): array
    {
        [$w, $p] = self::scope();
        if (($f['tag'] ?? '') !== '') { $w .= ' AND q.tags LIKE ?'; $p[] = '%,' . $f['tag'] . ',%'; }
        $filter = (string) ($f['filter'] ?? '');
        if ($filter === 'offen') $w .= ' AND q.answers = 0';
        if ($filter === 'geloest') $w .= ' AND q.accepted_answer_id IS NOT NULL';
        if ($filter === 'meine') { $w .= ' AND q.author_key = ?'; $p[] = Support::me(); }
        $order = match ($f['sort'] ?? '') {
            'beliebt' => 'q.score DESC, q.views DESC, q.activity_at DESC',
            'neu' => 'q.created_at DESC',
            default => 'q.activity_at DESC',
        };
        $db = Support::db();
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $ids = array_column(array_filter(Search::find($q, ['question'], 200), fn($h) => $h['kind'] === 'question'), 'snippet', 'id');
            if (!$ids) return ['rows' => [], 'total' => 0, 'pages' => 1];
            $rows = $db->fetchAll("SELECT q.* FROM questions q WHERE $w AND q.id IN (" . implode(',', array_map('intval', array_keys($ids))) . ')', $p);
            $pos = array_flip(array_keys($ids));
            usort($rows, fn($x, $y) => $pos[$x['id']] <=> $pos[$y['id']]);
            foreach ($rows as &$r) $r['snippet'] = $ids[$r['id']];
            return ['rows' => $rows, 'total' => count($rows), 'pages' => 1];
        }
        $total = (int) $db->fetchValue("SELECT COUNT(*) FROM questions q WHERE $w", $p);
        $page = max(1, (int) ($f['page'] ?? 1));
        $rows = $db->fetchAll("SELECT q.* FROM questions q WHERE $w ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $per))];
    }

    /** Beantwortete Fragen zuerst (für Spotlight und Vorschläge beim Melden) */
    public static function search(string $q, int $n = 5, bool $loose = false, bool $answeredOnly = false): array
    {
        $out = [];
        foreach (Search::find($q, ['question'], $n * 4, $loose) as $h) {
            if ($h['kind'] !== 'question') continue;
            $x = self::find($h['id']);
            if (!$x || ($answeredOnly && !(int) $x['answers'])) continue;
            $out[] = $x + ['snippet' => $h['snippet']];
            if (count($out) >= $n) break;
        }
        return $out;
    }

    public static function countView(int $id): void
    {
        $seen = (array) app()->session->get('support_seen_q', []);
        if (in_array($id, $seen, true)) return;
        $seen[] = $id;
        app()->session->set('support_seen_q', array_slice($seen, -200));
        Support::db()->query('UPDATE questions SET views = views + 1 WHERE id = ?', [$id]);
    }

    public static function save(?array $q, array $in): int
    {
        $db = Support::db();
        $data = [
            'title' => mb_substr(trim((string) $in['title']), 0, 160),
            'body' => mb_substr(trim((string) $in['body']), 0, 20000),
            'tags' => Support::tagString(Support::tags($in['tags'] ?? '')),
            'visibility' => ($in['visibility'] ?? 'all') === 'site' ? 'site' : 'all',
            'updated_at' => now(),
        ];
        if ($q) {
            $db->update('questions', $data, 'id = :id', ['id' => (int) $q['id']]);
            $id = (int) $q['id'];
        } else {
            $id = $db->insert('questions', $data + ['site' => site()->key, 'author_key' => Support::me(), 'author_name' => Support::myName(),
                'author_staff' => (int) Support::isStaff(), 'created_at' => now(), 'activity_at' => now()]);
        }
        Search::index('question', $id, $data['title'], $data['body'] . "\n" . self::answerText($id), $data['tags']);
        return $id;
    }

    /** Antworttexte gehören zum Suchindex der Frage (findet Lösungen auch über die Antwort) */
    private static function answerText(int $id): string
    {
        return implode("\n", array_column(Support::db()->fetchAll('SELECT body FROM answers WHERE question_id = ?', [$id]), 'body'));
    }

    private static function reindex(int $id): void
    {
        $q = Support::db()->fetch('SELECT * FROM questions WHERE id = ?', [$id]);
        if ($q) Search::index('question', $id, $q['title'], $q['body'] . "\n" . self::answerText($id), (string) $q['tags']);
    }

    public static function answers(array $q): array
    {
        return Support::db()->fetchAll('SELECT * FROM answers WHERE question_id = ? ORDER BY CASE WHEN id = ? THEN 0 ELSE 1 END, score DESC, id',
            [(int) $q['id'], (int) ($q['accepted_answer_id'] ?? 0)]);
    }

    public static function answer(int $id): ?array
    {
        $a = Support::db()->fetch('SELECT * FROM answers WHERE id = ?', [$id]);
        return $a && self::find((int) $a['question_id']) ? $a : null;
    }

    public static function addAnswer(array $q, string $body): int
    {
        $db = Support::db();
        $id = $db->insert('answers', ['question_id' => (int) $q['id'], 'site' => site()->key, 'author_key' => Support::me(), 'author_name' => Support::myName(),
            'author_staff' => (int) Support::isStaff(), 'body' => mb_substr(trim($body), 0, 20000), 'created_at' => now(), 'updated_at' => now()]);
        $db->query('UPDATE questions SET answers = (SELECT COUNT(*) FROM answers WHERE question_id = ?), activity_at = ? WHERE id = ?', [(int) $q['id'], now(), (int) $q['id']]);
        self::reindex((int) $q['id']);
        return $id;
    }

    public static function updateAnswer(array $a, string $body): void
    {
        Support::db()->update('answers', ['body' => mb_substr(trim($body), 0, 20000), 'updated_at' => now()], 'id = :id', ['id' => (int) $a['id']]);
        self::reindex((int) $a['question_id']);
    }

    public static function deleteAnswer(array $a): void
    {
        $db = Support::db();
        $db->query("DELETE FROM votes WHERE target = 'a' AND target_id = ?", [(int) $a['id']]);
        $db->query('DELETE FROM answers WHERE id = ?', [(int) $a['id']]);
        $db->query('UPDATE questions SET answers = (SELECT COUNT(*) FROM answers WHERE question_id = ?),
            accepted_answer_id = CASE WHEN accepted_answer_id = ? THEN NULL ELSE accepted_answer_id END WHERE id = ?', [(int) $a['question_id'], (int) $a['id'], (int) $a['question_id']]);
        self::reindex((int) $a['question_id']);
    }

    public static function delete(array $q): void
    {
        $db = Support::db();
        foreach ($db->fetchAll('SELECT id FROM answers WHERE question_id = ?', [(int) $q['id']]) as $a) {
            $db->query("DELETE FROM votes WHERE target = 'a' AND target_id = ?", [(int) $a['id']]);
        }
        $db->query("DELETE FROM votes WHERE target = 'q' AND target_id = ?", [(int) $q['id']]);
        $db->query('DELETE FROM answers WHERE question_id = ?', [(int) $q['id']]);
        $db->query('DELETE FROM questions WHERE id = ?', [(int) $q['id']]);
        Search::remove('question', (int) $q['id']);
    }

    /** +1 geben oder zurücknehmen. Eigene Beiträge zählen nicht. @return bool neuer Zustand (true = gestimmt) */
    public static function toggleVote(string $target, array $row): bool
    {
        if ($row['author_key'] === Support::me()) return false;
        $db = Support::db();
        $id = (int) $row['id'];
        $has = (bool) $db->fetchValue('SELECT 1 FROM votes WHERE target = ? AND target_id = ? AND user_key = ?', [$target, $id, Support::me()]);
        if ($has) $db->query('DELETE FROM votes WHERE target = ? AND target_id = ? AND user_key = ?', [$target, $id, Support::me()]);
        else $db->insert('votes', ['target' => $target, 'target_id' => $id, 'user_key' => Support::me(), 'value' => 1, 'created_at' => now()]);
        $table = $target === 'q' ? 'questions' : 'answers';
        $db->query("UPDATE $table SET score = (SELECT COALESCE(SUM(value), 0) FROM votes WHERE target = ? AND target_id = ?) WHERE id = ?", [$target, $id, $id]);
        return !$has;
    }

    /** Stimmen der angemeldeten Person: ['q' => [ids], 'a' => [ids]] */
    public static function myVotes(array $q, array $answers): array
    {
        $rows = Support::db()->fetchAll("SELECT target, target_id FROM votes WHERE user_key = ? AND ((target = 'q' AND target_id = ?) OR (target = 'a' AND target_id IN (" .
            (implode(',', array_map(fn($a) => (int) $a['id'], $answers)) ?: '0') . ')))', [Support::me(), (int) $q['id']]);
        $out = ['q' => [], 'a' => []];
        foreach ($rows as $r) $out[$r['target']][] = (int) $r['target_id'];
        return $out;
    }

    public static function canAccept(array $q): bool
    {
        return Support::isStaff() || $q['author_key'] === Support::me();
    }

    /** Antwort akzeptieren – erneut = zurücknehmen */
    public static function accept(array $q, array $a): void
    {
        $new = (int) $q['accepted_answer_id'] === (int) $a['id'] ? null : (int) $a['id'];
        Support::db()->update('questions', ['accepted_answer_id' => $new, 'activity_at' => now()], 'id = :id', ['id' => (int) $q['id']]);
    }
}
