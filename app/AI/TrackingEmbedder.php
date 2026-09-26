<?php
declare(strict_types=1);

namespace Core\AI;

/** Zählt Embedding-Aufrufe (Aufrufe, geschätzte Tokens, Dauer, Fehler) in den Nutzungszählern der Website – nie Inhalte. */
final class TrackingEmbedder implements Embedder
{
    public function __construct(private Embedder $inner, private array $cap) {}

    public function embed(array $texts, string $kind = 'document'): array
    {
        $t = microtime(true);
        try {
            $out = $this->inner->embed($texts, $kind);
        } catch (\Throwable $e) {
            Ai::track('embed', $this->cap, 0, 0, (int) ((microtime(true) - $t) * 1000), true);
            throw $e;
        }
        // Embedding-APIs melden Tokens uneinheitlich → Schätzung ≈ 4 Zeichen je Token
        Ai::track('embed', $this->cap, (int) ceil(array_sum(array_map('mb_strlen', $texts)) / 4), 0, (int) ((microtime(true) - $t) * 1000), false);
        return $out;
    }

    public function id(): string
    {
        return $this->inner->id();
    }
}
