<?php
declare(strict_types=1);

namespace Core\AI;

/**
 * Deterministische Embeddings ohne Anbieter (config 'ai' => ['provider' => 'fake']) – nur für Tests und Entwicklung.
 * Wörter und Wortanfänge (4 Zeichen) werden per Hash auf 256 Dimensionen verteilt: Texte mit gemeinsamen Wörtern
 * liegen nah beieinander. Keine echte Semantik, aber derselbe Code-Pfad wie mit einem echten Modell.
 */
final class FakeEmbedder implements Embedder
{
    public const DIMS = 256;

    public function __construct(private bool $fail = false) {}

    public function embed(array $texts, string $kind = 'document'): array
    {
        if ($this->fail) {
            throw new \RuntimeException('Fake-Anbieter: absichtlich nicht erreichbar.');
        }
        $out = [];
        foreach ($texts as $t) {
            $v = array_fill(0, self::DIMS, 0.0);
            preg_match_all('~[\p{L}\p{N}]{2,}~u', \Core\Search\Text::fold((string) $t), $m);
            foreach ($m[0] as $w) {
                foreach ([$w, mb_substr($w, 0, 4)] as $i => $tok) {
                    $h = crc32($tok);
                    $v[$h % self::DIMS] += ($h & 1 ? 1.0 : -1.0) * ($i ? 0.5 : 1.0);
                }
            }
            $norm = sqrt(array_sum(array_map(fn($x) => $x * $x, $v))) ?: 1.0;
            $out[] = array_map(fn($x) => $x / $norm, $v);
        }
        return $out;
    }

    public function id(): string
    {
        return 'fake:hash-' . self::DIMS;
    }
}
