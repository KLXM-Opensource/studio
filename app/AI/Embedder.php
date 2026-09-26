<?php
declare(strict_types=1);

namespace Core\AI;

/**
 * Erzeugt Vektoren (Embeddings) für Texte. Standard: PlatformEmbedder (Symfony AI Platform);
 * FakeEmbedder für Tests ohne Anbieter.
 */
interface Embedder
{
    /**
     * @param list<string> $texts
     * @param 'document'|'query' $kind  Manche Modelle (nomic, e5) erwarten unterschiedliche Präfixe
     * @return list<list<float>>  gleiche Reihenfolge wie $texts
     */
    public function embed(array $texts, string $kind = 'document'): array;

    /** Kennung „anbieter:modell“ – ändert sie sich, sind gespeicherte Vektoren ungültig */
    public function id(): string;
}
