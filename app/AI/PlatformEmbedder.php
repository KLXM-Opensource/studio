<?php
declare(strict_types=1);

namespace Core\AI;

use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Vectorizer;

/**
 * Embeddings über Symfony AI (symfony/ai-platform + Bridge des Anbieters, Vectorizer aus symfony/ai-store).
 * Große Mengen werden in Stapeln gesendet; manche Modelle erwarten Präfixe für Dokument bzw. Suchanfrage.
 */
final class PlatformEmbedder implements Embedder
{
    /** Präfixe je Modellfamilie (Teil des Modellnamens) */
    private const PREFIX = [
        'nomic-embed' => ['document' => 'search_document: ', 'query' => 'search_query: '],
        'e5' => ['document' => 'passage: ', 'query' => 'query: '],
        'mxbai-embed' => ['document' => '', 'query' => 'Represent this sentence for searching relevant passages: '],
    ];

    private Vectorizer $vectorizer;

    public function __construct(private PlatformInterface $platform, private string $provider, private string $model, private int $batch = 32)
    {
        $this->vectorizer = new Vectorizer($platform, $model);
    }

    public function embed(array $texts, string $kind = 'document'): array
    {
        $prefix = '';
        foreach (self::PREFIX as $needle => $p) {
            if (str_contains(strtolower($this->model), $needle)) { $prefix = $p[$kind] ?? ''; break; }
        }
        $out = [];
        foreach (array_chunk(array_values($texts), max(1, $this->batch)) as $chunk) {
            $vectors = $this->vectorizer->vectorize(array_map(fn($t) => $prefix . $t, $chunk));
            if (count($vectors) !== count($chunk)) {
                throw new \RuntimeException('Anbieter lieferte ' . count($vectors) . ' statt ' . count($chunk) . ' Vektoren.');
            }
            foreach ($vectors as $v) {
                $out[] = $v instanceof Vector ? $v->getData() : (array) $v->getData();
            }
        }
        return $out;
    }

    public function id(): string
    {
        return $this->provider . ':' . $this->model;
    }
}
