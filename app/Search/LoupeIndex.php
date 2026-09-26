<?php
declare(strict_types=1);

namespace Core\Search;

use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\SearchParameters;

/**
 * Stichwortsuche mit Loupe (loupe/loupe): SQLite-Datei je Website und Sprache unter storage/…/search/loupe-{lang}/.
 * Tippfehlertolerant (State-Set-Index + Damerau-Levenshtein), Präfixsuche, Umlaute/Akzente egal, Stemming (de, en, fr …),
 * Zerlegung deutscher/englischer Komposita, Relevanz nach Wortanzahl, Tippfehlern, Nähe, Attribut, Genauigkeit.
 * Attribut-Reihenfolge = Gewichtung: title > headings (hoch) > keywords (Synonyme) > text (normal) > extra (niedrig).
 *
 * Es wird immer genau eine Sprache vorgegeben – Loupes automatische Spracherkennung (nitotm/efficient-language-detector,
 * 170 MB Daten) ist per composer „replace“ ausgeschlossen und wird so nie geladen.
 */
final class LoupeIndex implements KeywordIndex
{
    private const HL = ["\u{E000}", "\u{E001}"];
    private ?Loupe $loupe = null;

    public function __construct(private string $dir, private string $lang) {}

    private function configuration(): Configuration
    {
        return Configuration::create()
            ->withPrimaryKey('id')
            ->withSearchableAttributes(['title', 'headings', 'keywords', 'text', 'extra'])
            ->withFilterableAttributes(['type', 'tbl'])   // nicht „table“: Loupe legt Spalten ungequotet an
            ->withSortableAttributes(['date'])
            ->withLanguages([substr($this->lang, 0, 2)])
            ->withStopWords(Text::stop($this->lang))
            ->withMaxTotalHits(1000)
            ->withMinTokenLengthForPrefixSearch(2);
    }

    private function loupe(): Loupe
    {
        if (!is_dir($this->dir)) @mkdir($this->dir, 0775, true);
        return $this->loupe ??= (new LoupeFactory())->create($this->dir, $this->configuration());
    }

    public function upsert(array $docs): void
    {
        if (!$docs) return;
        $keep = ['id', 'type', 'tbl', 'title', 'headings', 'keywords', 'text', 'extra', 'date'];
        foreach (array_chunk($docs, 200) as $chunk) {
            $this->loupe()->addDocuments(array_map(fn($d) => array_intersect_key($d + ['tbl' => $d['table'] ?? ''], array_flip($keep)), $chunk));
        }
    }

    public function delete(array $ids): void
    {
        if ($ids) $this->loupe()->deleteDocuments(array_values($ids));
    }

    public function clear(): void
    {
        $this->loupe = null;
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '/*') ?: [] as $f) @unlink($f);
        }
    }

    public function count(): int
    {
        try {
            return $this->loupe()->countDocuments();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function needsRebuild(): bool
    {
        try {
            return $this->loupe()->needsReindex();
        } catch (\Throwable) {
            return true;
        }
    }

    public function search(string $q, int $limit, array $filter = []): array
    {
        $q = trim($q);
        if ($q === '' || $this->count() === 0) return ['total' => 0, 'hits' => []];
        $filters = [];
        foreach (['type' => 'type', 'table' => 'tbl'] as $k => $attr) {
            if (!empty($filter[$k])) $filters[] = $attr . " = '" . str_replace("'", '', (string) $filter[$k]) . "'";
        }
        $params = SearchParameters::create()
            ->withQuery($q)
            ->withHitsPerPage(max(1, min(200, $limit)))
            ->withAttributesToRetrieve(['id', 'title', 'text'])
            ->withAttributesToHighlight(['title', 'text'], self::HL[0], self::HL[1])
            ->withAttributesToCrop(['text'], 160, '…', 1)
            ->withShowRankingScore(true);
        if ($filters) $params = $params->withFilter(implode(' AND ', $filters));
        // Erst alle Wörter verlangen; ohne Treffer genügt eines (z. B. „Öffnungszeiten Samstag“)
        $res = $this->loupe()->search($params->withMatchingStrategy('all'));
        if ($res->getTotalHits() === 0 && count(Text::words($q)) > 1) {
            $res = $this->loupe()->search($params->withMatchingStrategy('any'));
        }
        $hits = [];
        foreach ($res->getHits() as $h) {
            $f = (array) ($h['_formatted'] ?? []);
            $hits[] = [
                'id' => (string) $h['id'],
                'score' => (float) ($h['_rankingScore'] ?? 0),
                'title' => self::html((string) ($f['title'] ?? $h['title'] ?? '')),
                'text' => self::html((string) ($f['text'] ?? '')),
                'marked' => str_contains((string) ($f['title'] ?? '') . ($f['text'] ?? ''), self::HL[0]),
            ];
        }
        return ['total' => $res->getTotalHits(), 'hits' => $hits];
    }

    /** Gespeichertes Dokument (title, headings, keywords, text, extra …) – z. B. Textstellen für den Besucher-Chat (Core\AI\VisitorChat) */
    public function document(string $id): ?array
    {
        try {
            return $this->count() > 0 ? $this->loupe()->getDocument($id) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Loupe-Ausgabe (Rohtext mit Markierungszeichen) → sicheres HTML mit <mark> */
    private static function html(string $s): string
    {
        return str_replace(self::HL, ['<mark>', '</mark>'], e($s));
    }

    public function size(): int
    {
        try {
            return $this->loupe()->size();
        } catch (\Throwable) {
            return 0;
        }
    }
}
