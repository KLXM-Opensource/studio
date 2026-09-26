<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Suchverfahren für Support & Wissensdatenbank. Standard: SqliteSearch (FTS5, sonst LIKE).
 * Eine semantische Suche (z. B. Symfony AI mit Embeddings) implementiert dieselbe Schnittstelle
 * und wird mit Search::use(new MeineSuche()) eingesetzt – Aufrufer ändern sich nicht.
 */
interface SearchBackend
{
    /** Dokument (neu) aufnehmen. $kind: 'article' | 'question' */
    public function index(string $kind, int $id, string $title, string $body, string $tags): void;

    public function remove(string $kind, int $id): void;

    /**
     * Treffer, bestes zuerst.
     * @param list<string> $kinds
     * @param bool $loose true = „ähnliche“ (ein Wort genügt), false = alle Wörter müssen vorkommen
     * @return list<array{kind:string,id:int,score:float,snippet:?string}>
     */
    public function find(string $q, array $kinds, int $limit, bool $loose = false): array;
}
