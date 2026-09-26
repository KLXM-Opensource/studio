<?php
declare(strict_types=1);

namespace Core\Search;

use Core\AI\Ai;
use Core\Lang;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\VectorDocument;

/**
 * Abgleich des Suchindex einer Website: Dokumente je Sprache bauen (Documents), per Hash mit dem Stand vergleichen,
 * nur geänderte in Loupe schreiben bzw. löschen; danach – bei semantischer Suche – fehlende Vektoren in Abschnitten
 * (~500 Tokens mit Überlappung) über den KI-Anbieter erzeugen. Unveränderte Dokumente kosten keinen Anbieter-Aufruf.
 */
final class Indexer
{
    /** Abschnitte je Anbieter-Aufruf */
    private const BATCH = 24;
    /** Im selben Lauf gebaute Dokumente je Sprache (für die Vektoren wiederverwendet) */
    private array $built = [];

    public function __construct(private ?\Closure $log = null) {}

    private function log(string $msg): void
    {
        if ($this->log) ($this->log)($msg);
    }

    /**
     * @param bool $full     Stichwort-Index verwerfen und neu aufbauen (Vektoren unveränderter Dokumente bleiben)
     * @param bool $vectors  fehlende Vektoren erzeugen
     * @param float $budget  Zeitbudget in Sekunden für Vektoren (0 = unbegrenzt); der Rest folgt beim nächsten Abgleich
     */
    public function sync(bool $full = false, bool $vectors = true, float $budget = 0.0): array
    {
        $t0 = microtime(true);
        $db = Search::db();
        $stats = ['added' => 0, 'updated' => 0, 'removed' => 0, 'docs' => 0, 'vectors' => 0, 'chunks' => 0, 'vector_error' => null, 'pending' => 0];
        $langs = array_keys(Lang::all());
        // Sprachen, die es nicht mehr gibt
        foreach ($db->fetchAll('SELECT DISTINCT lang FROM docs') as $r) {
            if (!in_array($r['lang'], $langs, true)) {
                Search::keyword((string) $r['lang'])->clear();
                $db->query('DELETE FROM docs WHERE lang = ?', [$r['lang']]);
                $db->query('DELETE FROM vectors WHERE lang = ?', [$r['lang']]);
            }
        }
        foreach ($langs as $lang) {
            $idx = Search::keyword($lang);
            if ($full || $idx->needsRebuild() || ($idx->count() === 0 && (int) $db->fetchValue('SELECT COUNT(*) FROM docs WHERE lang = ?', [$lang]) > 0)) {
                $idx->clear();
                $db->query("UPDATE docs SET hash = '' WHERE lang = ?", [$lang]);
                $this->log("  $lang: Stichwort-Index wird neu aufgebaut");
            }
            $docs = $this->built[$lang] = Documents::build($lang);
            $have = array_column($db->fetchAll('SELECT id, hash FROM docs WHERE lang = ?', [$lang]), 'hash', 'id');
            $changed = array_filter($docs, fn($d) => ($have[$d['id']] ?? null) !== $d['hash']);
            $removed = array_values(array_diff(array_keys($have), array_keys($docs)));
            $idx->upsert(array_values($changed));
            $idx->delete($removed);
            $db->transaction(function (\Core\Database $db) use ($changed, $removed, $lang, $have, &$stats) {
                $st = $db->pdo->prepare('INSERT INTO docs (id, lang, hash, type, tbl, badge, title, headings, url, date, date_label, origin, excerpt, summary, image, facets, updated)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON CONFLICT(id, lang) DO UPDATE SET hash = excluded.hash, type = excluded.type, tbl = excluded.tbl, badge = excluded.badge,
                    title = excluded.title, headings = excluded.headings, url = excluded.url, date = excluded.date, date_label = excluded.date_label,
                    origin = excluded.origin, excerpt = excluded.excerpt, summary = excluded.summary, image = excluded.image, facets = excluded.facets, updated = excluded.updated');
                foreach ($changed as $d) {
                    $st->execute([$d['id'], $lang, $d['hash'], $d['type'], $d['table'], $d['badge'], $d['title'], mb_substr($d['headings'], 0, 500),
                        $d['url'], $d['date'], $d['date_label'], $d['origin'], mb_substr(trim($d['text'] . ' ' . $d['extra']), 0, 600),
                        mb_substr($d['summary'], 0, 600), $d['image'], $d['facets'], time()]);
                    isset($have[$d['id']]) ? $stats['updated']++ : $stats['added']++;
                }
                foreach (array_chunk($removed, 400) as $chunk) {
                    $in = implode(',', array_fill(0, count($chunk), '?'));
                    $db->query("DELETE FROM docs WHERE lang = ? AND id IN ($in)", [$lang, ...$chunk]);
                    $db->query("DELETE FROM vectors WHERE lang = ? AND doc IN ($in)", [$lang, ...$chunk]);
                }
                $stats['removed'] += count($removed);
            });
            $stats['docs'] += count($docs);
            $this->log(sprintf('  %s: %d Dokumente (%d neu/geändert, %d entfernt)', $lang, count($docs), count($changed), count($removed)));
        }
        Search::state('synced_at', date('c'));
        Search::state('synced_day', date('Y-m-d'));
        if ($full) Search::state('full_at', date('c'));
        Search::state('error', null);

        if ($vectors) {
            $this->vectors($stats, $budget, $t0);
        }
        $stats['seconds'] = round(microtime(true) - $t0, 2);
        return $stats;
    }

    /** Fehlende bzw. veraltete Vektoren erzeugen */
    private function vectors(array &$stats, float $budget, float $t0): void
    {
        $db = Search::db();
        try {
            $emb = Ai::embedder(null, Ai::config()['index_timeout']);
        } catch (\Throwable $e) {
            $stats['vector_error'] = Ai::shortError($e);
            Search::state('vector_error', $stats['vector_error']);
            return;
        }
        if (!$emb) return;
        $model = $emb->id();
        if (Search::state('vmodel') !== $model) {
            // Anderes Modell: alte Vektoren passen nicht mehr (andere Dimensionen/Skalierung)
            $db->query('DELETE FROM vectors WHERE model != ?', [$model]);
            Search::state('vmodel', $model);
        }
        $store = Search::vectors();
        $todo = $db->fetchAll('SELECT id, lang, hash FROM docs WHERE vhash IS NULL OR vhash != hash OR vmodel IS NULL OR vmodel != ? ORDER BY updated DESC', [$model]);
        if (!$todo) { Search::state('vector_error', null); return; }
        $this->log('  Vektoren: ' . count($todo) . ' Dokumente (' . $model . ')');
        // Volltext der Dokumente kommt aus dem Stichwort-Index nicht zurück → Dokumente je Sprache neu bauen (nur benötigte)
        $byLang = [];
        foreach ($todo as $r) $byLang[$r['lang']][] = $r['id'];
        $queue = [];
        foreach ($byLang as $lang => $ids) {
            $docs = $this->built[$lang] ??= Documents::build($lang);
            foreach ($ids as $id) {
                if (!isset($docs[$id])) continue;
                $d = $docs[$id];
                $head = trim($d['title'] . ($d['headings'] !== '' ? ' – ' . $d['headings'] : ''));
                foreach (Text::chunks(trim($d['summary'] . ' ' . $d['text'] . ' ' . $d['extra'] . ' ' . $d['keywords'])) ?: [''] as $n => $chunk) {
                    $queue[] = ['doc' => $id, 'lang' => $lang, 'hash' => $d['hash'], 'n' => $n, 'embed' => trim($head . "\n" . $chunk), 'text' => $chunk !== '' ? $chunk : $head];
                }
            }
        }
        $done = [];
        $pendingDocs = array_count_values(array_map(fn($c) => $c['lang'] . "\0" . $c['doc'], $queue));
        try {
            foreach (array_chunk($queue, self::BATCH) as $batch) {
                if ($budget > 0 && microtime(true) - $t0 > $budget) break;
                $vecs = $emb->embed(array_column($batch, 'embed'), 'document');
                $vd = [];
                foreach ($batch as $i => $c) {
                    $vd[] = new VectorDocument($c['lang'] . ':' . $c['doc'] . '#' . $c['n'], new Vector($vecs[$i]),
                        new Metadata(['doc' => $c['doc'], 'lang' => $c['lang'], 'model' => $model, 'chunk' => $c['n'], 'text' => mb_substr($c['text'], 0, 2400)]));
                }
                // Alte Abschnitte eines Dokuments vor dem ersten neuen entfernen
                foreach ($batch as $c) {
                    if ($c['n'] === 0) $db->query('DELETE FROM vectors WHERE lang = ? AND doc = ?', [$c['lang'], $c['doc']]);
                }
                $store->add($vd);
                $stats['chunks'] += count($batch);
                foreach ($batch as $c) {
                    $key = $c['lang'] . "\0" . $c['doc'];
                    $done[$key] = ($done[$key] ?? 0) + 1;
                    if ($done[$key] === ($pendingDocs[$key] ?? 0)) {
                        $db->query('UPDATE docs SET vhash = ?, vmodel = ? WHERE id = ? AND lang = ?', [$c['hash'], $model, $c['doc'], $c['lang']]);
                        $stats['vectors']++;
                    }
                }
            }
            Search::state('vector_error', null);
            Ai::recovered();
        } catch (\Throwable $e) {
            $stats['vector_error'] = Ai::shortError($e);
            Search::state('vector_error', $stats['vector_error']);
            Ai::failed($e);
            $this->log('  Vektoren abgebrochen: ' . $stats['vector_error']);
        }
        $stats['pending'] = Search::pendingVectors();
    }
}
