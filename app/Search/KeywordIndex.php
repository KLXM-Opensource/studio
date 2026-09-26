<?php
declare(strict_types=1);

namespace Core\Search;

/**
 * Stichwort-Index einer Website in einer Sprache. Standard: LoupeIndex (loupe/loupe, SQLite, tippfehlertolerant).
 * Andere Verfahren (z. B. Meilisearch, SQLite FTS5) implementieren dieselbe Schnittstelle.
 */
interface KeywordIndex
{
    /** @param list<array> $docs Dokumente (siehe Documents) */
    public function upsert(array $docs): void;

    /** @param list<string> $ids */
    public function delete(array $ids): void;

    public function clear(): void;

    public function count(): int;

    /**
     * Beste Treffer. $filter: type, table
     * @return array{total: int, hits: list<array{id: string, score: float, title: string, text: string}>}  title/text: HTML mit <mark>
     */
    public function search(string $q, int $limit, array $filter = []): array;

    /** Index passt nicht mehr zur Konfiguration/Version → vollständig neu aufbauen */
    public function needsRebuild(): bool;
}
