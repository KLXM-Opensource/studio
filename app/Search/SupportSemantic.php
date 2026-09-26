<?php
declare(strict_types=1);

namespace Core\Search;

use Core\AI\Ai;
use Core\Support\SearchBackend;
use Core\Support\SqliteSearch;
use Core\Support\Support;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\VectorQuery;

/**
 * Semantische Suche für Support & Wissensdatenbank (Core\Support\Search::use()): Stichwortsuche (SqliteSearch, FTS5)
 * plus Embeddings über Symfony AI, gemischt per Reciprocal Rank Fusion. Vektoren liegen in der zentralen
 * Support-Datenbank (Tabelle kb_vectors). Fällt der Anbieter aus, bleibt es bei der Stichwortsuche.
 * Nutzt die KI-Konfiguration der Installation (config.local.php), nicht die einer einzelnen Website.
 */
final class SupportSemantic implements SearchBackend
{
    private ?SqliteSearch $inner = null;
    private ?VectorStore $store = null;

    public function __construct(private array $cfg) {}

    private function inner(): SqliteSearch
    {
        return $this->inner ??= new SqliteSearch(Support::db());
    }

    private function store(): VectorStore
    {
        if (!$this->store) {
            $this->store = new VectorStore(Support::db(), 'kb_vectors');
            $this->store->setup();
        }
        return $this->store;
    }

    public function index(string $kind, int $id, string $title, string $body, string $tags): void
    {
        $this->inner()->index($kind, $id, $title, $body, $tags);
        if (Ai::paused($this->cfg)) return;
        try {
            $this->embedOne($kind, $id, $title, $body, $tags, $this->cfg['timeout'] + 5);
        } catch (\Throwable $e) {
            Ai::failed($e, $this->cfg);   // Nachholen: php bin/console search:index --kb
        }
    }

    public function embedOne(string $kind, int $id, string $title, string $body, string $tags, ?float $timeout = null): int
    {
        $emb = Ai::embedder($this->cfg, $timeout ?? $this->cfg['index_timeout']);
        if (!$emb) return 0;
        $chunks = Text::chunks(trim($body . ' ' . $tags)) ?: [''];
        $vecs = $emb->embed(array_map(fn($c) => trim($title . "\n" . $c), $chunks), 'document');
        $this->store()->removeDocs(["$kind-$id"]);
        $docs = [];
        foreach ($chunks as $n => $c) {
            $docs[] = new VectorDocument("$kind-$id#$n", new Vector($vecs[$n]), new Metadata(['doc' => "$kind-$id", 'lang' => '', 'model' => $emb->id(), 'text' => mb_substr($c, 0, 1000)]));
        }
        $this->store()->add($docs);
        return count($docs);
    }

    public function remove(string $kind, int $id): void
    {
        $this->inner()->remove($kind, $id);
        $this->store()->removeDocs(["$kind-$id"]);
    }

    public function find(string $q, array $kinds, int $limit, bool $loose = false): array
    {
        $kw = $this->inner()->find($q, $kinds, $limit * 2, $loose);
        if (Ai::paused($this->cfg)) return array_slice($kw, 0, $limit);
        try {
            $emb = Ai::embedder($this->cfg) ?? throw new \RuntimeException('Kein Embedding-Modell.');
            $vec = $emb->embed([$q], 'query')[0];
            $res = $this->store()->query(new VectorQuery(new Vector($vec)), ['maxItems' => $limit * 3, 'model' => $emb->id(), 'minScore' => Ranker::minSimilarity($emb->id())]);
        } catch (\Throwable $e) {
            Ai::failed($e, $this->cfg);
            return array_slice($kw, 0, $limit);
        }
        $sem = [];
        foreach ($res as $d) {
            $doc = (string) $d->getMetadata()['doc'];
            [$kind] = explode('-', $doc, 2);
            if (!in_array($kind, $kinds, true) || isset($sem[$doc])) continue;
            $sem[$doc] = true;
        }
        $byKey = [];
        foreach ($kw as $h) $byKey[$h['kind'] . '-' . $h['id']] = $h;
        $score = [];
        foreach ([array_keys($byKey), array_keys($sem)] as $list) {
            foreach ($list as $rank => $key) $score[$key] = ($score[$key] ?? 0) + 1 / (60 + $rank + 1);
        }
        arsort($score);
        $out = [];
        foreach (array_slice(array_keys($score), 0, $limit) as $key) {
            [$kind, $id] = explode('-', (string) $key, 2);
            $out[] = ['kind' => $kind, 'id' => (int) $id, 'score' => round($score[$key] * 1000, 3), 'snippet' => $byKey[$key]['snippet'] ?? null];
        }
        return $out;
    }

    /** Alle Artikel und Fragen (neu) einbetten – CLI `search:index --kb` */
    public function reindex(?\Closure $log = null): int
    {
        $n = 0;
        foreach (Support::db()->fetchAll('SELECT kind, ref, title, body, tags FROM search_idx') as $r) {
            $n += $this->embedOne((string) $r['kind'], (int) $r['ref'], (string) $r['title'], (string) $r['body'], (string) $r['tags']);
            if ($log) $log("  {$r['kind']} {$r['ref']}: {$r['title']}");
        }
        return $n;
    }
}
