<?php
declare(strict_types=1);

namespace Core\Search;

use Core\Database;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\Exception\UnsupportedQueryTypeException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

/**
 * Vektorspeicher in SQLite (Symfony-AI-Store-Schnittstelle): Vektoren als float32-BLOB (normiert), Suche per
 * Kosinus-Ähnlichkeit ohne Index (brute force). Gut bis etwa 20 000 Abschnitte je Website und Sprache
 * (≈ 60 MB bei 768 Dimensionen, Suche < 1 s); darüber einen Store mit Vektorindex einsetzen
 * (symfony/ai-postgres-store, -meilisearch-store, -qdrant-store …) – er implementiert dieselbe StoreInterface.
 *
 * Metadaten je Abschnitt: doc (Dokument-ID), lang, chunk (Nr.), model (Embedder-ID), text (Auszug für Treffer).
 */
final class VectorStore implements StoreInterface, ManagedStoreInterface
{
    public function __construct(private Database $db, private string $table = 'vectors') {}

    public function setup(array $options = []): void
    {
        $this->db->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->table} (id TEXT PRIMARY KEY, doc TEXT NOT NULL, lang TEXT NOT NULL DEFAULT '',
            model TEXT NOT NULL DEFAULT '', dims INT NOT NULL, vec BLOB NOT NULL, meta TEXT)");
        $this->db->pdo->exec("CREATE INDEX IF NOT EXISTS {$this->table}_doc ON {$this->table} (doc)");
        $this->db->pdo->exec("CREATE INDEX IF NOT EXISTS {$this->table}_lang ON {$this->table} (lang, model)");
    }

    public function drop(array $options = []): void
    {
        $this->db->pdo->exec("DROP TABLE IF EXISTS {$this->table}");
    }

    /** @param VectorDocumentInterface|list<VectorDocumentInterface> $documents */
    public function add(VectorDocumentInterface|array $documents): void
    {
        $st = $this->db->pdo->prepare("INSERT OR REPLACE INTO {$this->table} (id, doc, lang, model, dims, vec, meta) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $this->db->pdo->beginTransaction();
        try {
            foreach (is_array($documents) ? $documents : [$documents] as $d) {
                $data = $d->getVector()->getData();
                $meta = $d->getMetadata()->getArrayCopy();
                $st->bindValue(1, (string) $d->getId());
                $st->bindValue(2, (string) ($meta['doc'] ?? $d->getId()));
                $st->bindValue(3, (string) ($meta['lang'] ?? ''));
                $st->bindValue(4, (string) ($meta['model'] ?? ''));
                $st->bindValue(5, count($data), \PDO::PARAM_INT);
                $st->bindValue(6, self::pack($data), \PDO::PARAM_LOB);
                $st->bindValue(7, json_encode(array_diff_key($meta, ['doc' => 1, 'lang' => 1, 'model' => 1]), JSON_UNESCAPED_UNICODE));
                $st->execute();
            }
            $this->db->pdo->commit();
        } catch (\Throwable $e) {
            $this->db->pdo->rollBack();
            throw $e;
        }
    }

    public function remove(string|array $ids, array $options = []): void
    {
        foreach (array_chunk((array) $ids, 400) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $this->db->query("DELETE FROM {$this->table} WHERE id IN ($in)", $chunk);
        }
    }

    /** Alle Abschnitte eines Dokuments entfernen */
    public function removeDocs(array $docIds): void
    {
        foreach (array_chunk($docIds, 400) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $this->db->query("DELETE FROM {$this->table} WHERE doc IN ($in)", $chunk);
        }
    }

    public function clear(array $options = []): void
    {
        $this->db->pdo->exec("DELETE FROM {$this->table}");
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM {$this->table}");
    }

    public function supports(string $queryClass): bool
    {
        return $queryClass === VectorQuery::class;
    }

    /**
     * Ähnlichste Abschnitte. $options: maxItems (Standard 50), lang, model (nur Vektoren dieses Embedders),
     * minScore (0–1), filter (callable(VectorDocumentInterface): bool)
     * @return list<VectorDocumentInterface>
     */
    public function query(QueryInterface $query, array $options = []): iterable
    {
        if (!$query instanceof VectorQuery) {
            throw new UnsupportedQueryTypeException(get_class($query), $this);
        }
        $q = self::normalize($query->getVector()->getData());
        $dims = count($q);
        $max = max(1, (int) ($options['maxItems'] ?? 50));
        $min = (float) ($options['minScore'] ?? -1.0);
        $where = ['dims = ?'];
        $params = [$dims];
        foreach (['lang', 'model'] as $k) {
            if (isset($options[$k])) { $where[] = "$k = ?"; $params[] = (string) $options[$k]; }
        }
        $st = $this->db->pdo->prepare("SELECT id, doc, lang, model, vec, meta FROM {$this->table} WHERE " . implode(' AND ', $where));
        $st->execute($params);
        $top = [];   // score => row (klein gehalten)
        $floor = -2.0;
        while ($r = $st->fetch(\PDO::FETCH_ASSOC)) {
            $v = unpack('g*', (string) $r['vec']);
            $s = 0.0;
            $i = 0;
            foreach ($v as $x) { $s += $x * $q[$i++]; }
            if ($s < $min || (count($top) >= $max && $s <= $floor)) continue;
            $top[] = [$s, $r];
            if (count($top) > $max * 2) {
                usort($top, fn($a, $b) => $b[0] <=> $a[0]);
                $top = array_slice($top, 0, $max);
                $floor = $top[count($top) - 1][0];
            }
        }
        usort($top, fn($a, $b) => $b[0] <=> $a[0]);
        $out = [];
        foreach ($top as [$s, $r]) {
            $meta = new Metadata((json_decode((string) $r['meta'], true) ?: []) + ['doc' => $r['doc'], 'lang' => $r['lang'], 'model' => $r['model']]);
            $doc = new VectorDocument($r['id'], new Vector(array_values(unpack('g*', (string) $r['vec']))), $meta, $s);
            if (isset($options['filter']) && !($options['filter'])($doc)) continue;
            $out[] = $doc;
            if (count($out) >= $max) break;
        }
        return $out;
    }

    /** Anzahl Abschnitte je Sprache und Modell */
    public function stats(): array
    {
        return $this->db->fetchAll("SELECT lang, model, dims, COUNT(*) AS n, COUNT(DISTINCT doc) AS docs FROM {$this->table} GROUP BY lang, model, dims");
    }

    /** IDs der Dokumente, die Vektoren eines Modells haben */
    public function docs(string $lang, string $model): array
    {
        return array_column($this->db->fetchAll("SELECT DISTINCT doc FROM {$this->table} WHERE lang = ? AND model = ?", [$lang, $model]), 'doc');
    }

    public static function normalize(array $v): array
    {
        $n = sqrt(array_sum(array_map(fn($x) => $x * $x, $v))) ?: 1.0;
        return array_map(fn($x) => (float) $x / $n, array_values($v));
    }

    public static function pack(array $v): string
    {
        return pack('g*', ...self::normalize($v));
    }
}
