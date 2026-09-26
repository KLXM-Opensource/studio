<?php
declare(strict_types=1);

namespace Core\Search;

/**
 * Anonyme Zähler „Suchbegriffe ohne Treffer“ für die Redaktion (Grundeinstellungen → Suche).
 * Gespeichert werden nur Begriff (Vergleichsform, max. 60 Zeichen), Sprache, Anzahl und Tag der letzten Suche –
 * keine IP, kein Zeitpunkt, keine Sitzung. Begriffe mit @, langen Ziffernfolgen oder Adressen werden nie gespeichert
 * (könnten personenbezogen sein). Abschaltbar; Liste jederzeit leerbar.
 */
final class Stats
{
    public static function miss(string $q, string $lang): void
    {
        try {
            if (!Search::settings()['misses']) return;
            $term = trim((string) preg_replace('~\s+~u', ' ', Text::fold($q)));
            if ($term === '' || mb_strlen($term) > 60 || mb_strlen($term) < 2 || str_contains($term, '@') || preg_match('~\d{4,}|https?:|www\.|/~', $term)) return;
            Search::db()->query('INSERT INTO misses (lang, term, n, last) VALUES (?, ?, 1, ?) ON CONFLICT(lang, term) DO UPDATE SET n = n + 1, last = excluded.last',
                [$lang, $term, date('Y-m-d')]);
            if (random_int(1, 200) === 1) {
                // Einmalige Begriffe nach 90 Tagen, alles nach einem Jahr vergessen
                Search::db()->query("DELETE FROM misses WHERE (n < 2 AND last < ?) OR last < ?", [date('Y-m-d', strtotime('-90 days')), date('Y-m-d', strtotime('-1 year'))]);
            }
        } catch (\Throwable $e) {
            error_log('[search] miss: ' . $e->getMessage());
        }
    }

    /** @return list<array{lang: string, term: string, n: int, last: string}> */
    public static function top(int $limit = 20): array
    {
        return Search::db()->fetchAll('SELECT lang, term, n, last FROM misses ORDER BY n DESC, last DESC LIMIT ' . max(1, $limit));
    }

    public static function clear(): void
    {
        Search::db()->pdo->exec('DELETE FROM misses');
    }
}
