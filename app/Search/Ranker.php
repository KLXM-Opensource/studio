<?php
declare(strict_types=1);

namespace Core\Search;

/**
 * Hybride Rangfolge: Reciprocal Rank Fusion (RRF, k = 60) über Stichwort- und semantische Treffer –
 * Score = Σ 1 / (k + Rang). Dazu eine kleine Gewichtung, wenn Suchwörter im Titel bzw. in Überschriften stehen
 * (Grundeinstellungen → Suche → Gewichtung; Loupe gewichtet selbst schon title > headings > text).
 */
final class Ranker
{
    private const BOOST_UNIT = 0.001;

    /**
     * @param list<list<string>> $lists  Trefferlisten (beste zuerst)
     * @param array<string, array> $docs  id => Zeile (title, headings)
     * @return list<string> IDs, beste zuerst
     */
    public static function fuse(array $lists, array $docs, array $words, array $boost, int $k = 60): array
    {
        $score = [];
        foreach ($lists as $list) {
            foreach (array_values($list) as $rank => $id) {
                if (!isset($docs[$id])) continue;
                $score[$id] = ($score[$id] ?? 0.0) + 1.0 / ($k + $rank + 1);
            }
        }
        if ($words) {
            foreach ($score as $id => $s) {
                $score[$id] += self::BOOST_UNIT * ((int) ($boost['title'] ?? 0) * self::share((string) $docs[$id]['title'], $words)
                    + (int) ($boost['headings'] ?? 0) * self::share((string) ($docs[$id]['headings'] ?? ''), $words));
            }
        }
        arsort($score);
        return array_map('strval', array_keys($score));
    }

    /**
     * Rechtsseiten (Impressum, Datenschutz, Barrierefreiheit) nennen fast alles einmal (Name, Adresse, Kontakt, Formulare) und
     * landen sonst als Beifang weit oben – ans Ende, außer der Titel passt zur Suche („impressum“, „datenschutz“ …).
     */
    public static function legalLast(array $ranked, array $docs, array $words, array $legal): array
    {
        if (!$legal) return $ranked;
        $front = $back = [];
        foreach ($ranked as $id) {
            if (isset($legal[$id]) && self::share((string) ($docs[$id]['title'] ?? ''), $words) === 0.0) $back[] = $id; else $front[] = $id;
        }
        return array_merge($front, $back);
    }

    /** Anteil der Suchwörter, die (als Wortanfang) im Text vorkommen: 0…1 */
    public static function share(string $text, array $words): float
    {
        if ($text === '' || !$words) return 0.0;
        $f = ' ' . preg_replace('~[^\p{L}\p{N}]+~u', ' ', Text::fold($text));
        $n = 0;
        foreach ($words as $w) if (str_contains($f, ' ' . $w)) $n++;
        return $n / count($words);
    }

    /** Untergrenze der Kosinus-Ähnlichkeit, ab der ein Abschnitt „ähnlich“ ist – je Modellfamilie verschieden skaliert */
    public static function minSimilarity(string $model): float
    {
        $m = strtolower($model);
        return match (true) {
            str_contains($m, 'nomic-embed') => 0.55,
            str_contains($m, 'mistral-embed') => 0.72,
            str_contains($m, 'text-embedding-3') => 0.35,
            str_contains($m, 'mxbai') => 0.55,
            str_contains($m, 'bge-m3'), str_contains($m, 'e5') => 0.6,
            str_starts_with($m, 'fake') => 0.3,
            default => 0.45,
        };
    }
}
