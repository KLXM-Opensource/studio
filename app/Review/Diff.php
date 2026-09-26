<?php
declare(strict_types=1);

namespace Core\Review;

/**
 * Unterschiede zweier Zustände (Core\Review\Snapshot) – feldweise, bei Seiten blockweise.
 *
 * Ergebnis: Liste von Änderungen
 *   ['type' => 'field', 'path' => 'title', 'label' => 'Titel', 'before' => …, 'after' => …]
 *   ['type' => 'blocks', 'path' => 'blocks', 'label' => 'Inhalt (Entwurf)', 'items' => [
 *       ['op' => added|removed|changed|moved, 'id' => …, 'block' => 'text', 'label' => 'Text', 'from' => 2, 'to' => 3,
 *        'fields' => [['path' => 'data.text', 'label' => …, 'before' => …, 'after' => …]]]]]
 */
final class Diff
{
    public static function compute(array $target, ?array $before, ?array $after): array
    {
        $labels = Snapshot::labels($target);
        $b = $before ?? [];
        $a = $after ?? [];
        $out = [];
        foreach (array_unique(array_merge(array_keys($b), array_keys($a))) as $k) {
            $x = $b[$k] ?? null;
            $y = $a[$k] ?? null;
            if (self::same($x, $y) || (self::blank($x) && self::blank($y))) continue;
            if (in_array($k, ['blocks', 'live'], true) && ($target['type'] ?? '') === 'page') {
                $items = self::blocks((array) ($x ?? []), (array) ($y ?? []));
                if ($items) $out[] = ['type' => 'blocks', 'path' => (string) $k, 'label' => $labels[$k] ?? (string) $k, 'items' => $items];
                continue;
            }
            $out[] = ['type' => 'field', 'path' => (string) $k, 'label' => $labels[$k] ?? (string) $k, 'before' => $x, 'after' => $y];
        }
        return $out;
    }

    /** Anzahl geänderter Felder (Blöcke einzeln gezählt) */
    public static function count(array $diff): int
    {
        $n = 0;
        foreach ($diff as $d) $n += $d['type'] === 'blocks' ? count($d['items']) : 1;
        return $n;
    }

    private static function blank(mixed $v): bool
    {
        return $v === null || $v === '' || $v === [];
    }

    private static function same(mixed $x, mixed $y): bool
    {
        if (is_array($x) || is_array($y)) return json_encode($x) === json_encode($y);
        if (is_bool($x) || is_bool($y)) return (bool) $x === (bool) $y;
        return (string) ($x ?? '') === (string) ($y ?? '');
    }

    /** Blockweiser Vergleich über die Block-IDs */
    private static function blocks(array $before, array $after): array
    {
        $theme = app()->theme;
        $bi = [];
        foreach (array_values($before) as $i => $blk) $bi[(string) ($blk['id'] ?? '')] = [$i, $blk];
        $ai = [];
        foreach (array_values($after) as $i => $blk) $ai[(string) ($blk['id'] ?? '')] = [$i, $blk];
        // Reihenfolge der gemeinsamen Blöcke: nur echte Verschiebungen melden (nicht die Folge von Einfügen/Löschen)
        $common = array_values(array_filter(array_keys($ai), fn($id) => isset($bi[$id])));
        $orderBefore = array_values(array_filter(array_keys($bi), fn($id) => isset($ai[$id])));
        $movedIds = [];
        foreach ($common as $pos => $id) if (($orderBefore[$pos] ?? null) !== $id) $movedIds[$id] = true;
        $label = fn(array $blk) => (string) ($theme->block((string) ($blk['type'] ?? ''))['label'] ?? ($blk['type'] ?? ''));
        $items = [];
        foreach ($ai as $id => [$i, $blk]) {
            if (!isset($bi[$id])) {
                $items[] = ['op' => 'added', 'id' => $id, 'block' => $blk['type'], 'label' => $label($blk), 'to' => $i, 'fields' => self::blockFields([], $blk)];
                continue;
            }
            [$j, $old] = $bi[$id];
            $fields = self::blockFields($old, $blk);
            $moved = isset($movedIds[$id]);
            if (!$fields && !$moved) continue;
            $items[] = ['op' => $fields ? 'changed' : 'moved', 'id' => $id, 'block' => $blk['type'], 'label' => $label($blk), 'from' => $j, 'to' => $i,
                'moved' => $moved, 'fields' => $fields];
        }
        foreach ($bi as $id => [$j, $blk]) {
            if (isset($ai[$id])) continue;
            $items[] = ['op' => 'removed', 'id' => $id, 'block' => $blk['type'], 'label' => $label($blk), 'from' => $j, 'fields' => self::blockFields($blk, [])];
        }
        usort($items, fn($x, $y) => ($x['to'] ?? $x['from'] ?? 0) <=> ($y['to'] ?? $y['from'] ?? 0));
        return $items;
    }

    /** Geänderte Felder eines Blocks (data + Abschnitts-Optionen), Beschriftungen aus dem Blocktyp */
    private static function blockFields(array $old, array $new): array
    {
        $def = app()->theme->block((string) ($new['type'] ?? $old['type'] ?? '')) ?? [];
        $labels = [];
        foreach ((array) ($def['fields'] ?? []) as $f) if (isset($f['name'])) $labels[$f['name']] = (string) ($f['label'] ?? $f['name']);
        $out = [];
        // Neue bzw. entfernte Blöcke: nur Inhalte, keine Abschnitts-Standardwerte
        $parts = $old && $new ? ['data' => '', 'section' => __('Abschnitt') . ': '] : ['data' => ''];
        foreach ($parts as $part => $prefix) {
            $x = (array) ($old[$part] ?? []);
            $y = (array) ($new[$part] ?? []);
            foreach (array_unique(array_merge(array_keys($x), array_keys($y))) as $k) {
                if (self::same($x[$k] ?? null, $y[$k] ?? null) || (self::blank($x[$k] ?? null) && self::blank($y[$k] ?? null))) continue;
                $out[] = ['path' => $part . '.' . $k, 'label' => $prefix . ($part === 'data' ? ($labels[$k] ?? (string) $k) : (string) $k),
                    'before' => $x[$k] ?? null, 'after' => $y[$k] ?? null];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ Darstellung

    /** Wert als lesbarer Text (HTML ohne Tags, Listen/Objekte als JSON) */
    public static function text(mixed $v): string
    {
        if ($v === null) return '';
        if (is_bool($v)) return $v ? __('ja') : __('nein');
        if (is_array($v)) {
            if (array_is_list($v) && !array_filter($v, 'is_array')) return implode(', ', array_map(fn($x) => is_bool($x) ? ($x ? '1' : '0') : (string) $x, $v));
            return (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        $s = (string) $v;
        if ($s !== strip_tags($s)) {
            $s = preg_replace('~<(br|/p|/li|/h\d|/div)\b[^>]*>~i', "$0\n", $s);
            $s = html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $s = preg_replace("~\n{3,}~", "\n\n", trim($s));
        }
        return (string) $s;
    }

    /**
     * Wortweiser Vergleich → [HTML vorher, HTML nachher] mit <del>/<ins> (escaped).
     * Sehr große geänderte Bereiche werden als Ganzes markiert.
     */
    public static function words(string $a, string $b): array
    {
        // Wörter samt folgendem Leerraum – so bleiben geänderte Wortfolgen eine zusammenhängende Markierung
        $ta = preg_split('~(?<=\s)(?=\S)~u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tb = preg_split('~(?<=\s)(?=\S)~u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($a === '' || $b === '') {
            return [$a === '' ? '' : '<del>' . e($a) . '</del>', $b === '' ? '' : '<ins>' . e($b) . '</ins>'];
        }
        // Gleicher Anfang und gleiches Ende bleiben unmarkiert – verglichen wird nur die Mitte
        $pre = 0;
        while ($pre < count($ta) && $pre < count($tb) && $ta[$pre] === $tb[$pre]) $pre++;
        $suf = 0;
        while ($suf < count($ta) - $pre && $suf < count($tb) - $pre && $ta[count($ta) - 1 - $suf] === $tb[count($tb) - 1 - $suf]) $suf++;
        $head = e(implode('', array_slice($ta, 0, $pre)));
        $tailA = e(implode('', array_slice($ta, count($ta) - $suf)));
        $tailB = e(implode('', array_slice($tb, count($tb) - $suf)));
        $ta = array_slice($ta, $pre, count($ta) - $pre - $suf);
        $tb = array_slice($tb, $pre, count($tb) - $pre - $suf);
        if (count($ta) * count($tb) > 250_000) {
            return [$head . ($ta ? '<del>' . e(implode('', $ta)) . '</del>' : '') . $tailA, $head . ($tb ? '<ins>' . e(implode('', $tb)) . '</ins>' : '') . $tailB];
        }
        // Längste gemeinsame Teilfolge (LCS) über Wörter
        $n = count($ta);
        $m = count($tb);
        $L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $L[$i][$j] = $ta[$i] === $tb[$j] ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
            }
        }
        $outA = $outB = '';
        $i = $j = 0;
        $delBuf = $insBuf = '';
        $flush = function () use (&$outA, &$outB, &$delBuf, &$insBuf) {
            if ($delBuf !== '') { $outA .= trim($delBuf) === '' ? e($delBuf) : '<del>' . e($delBuf) . '</del>'; $delBuf = ''; }
            if ($insBuf !== '') { $outB .= trim($insBuf) === '' ? e($insBuf) : '<ins>' . e($insBuf) . '</ins>'; $insBuf = ''; }
        };
        while ($i < $n && $j < $m) {
            if ($ta[$i] === $tb[$j]) {
                $flush();
                $outA .= e($ta[$i]);
                $outB .= e($tb[$j]);
                $i++;
                $j++;
            } elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) {
                $delBuf .= $ta[$i++];
            } else {
                $insBuf .= $tb[$j++];
            }
        }
        while ($i < $n) $delBuf .= $ta[$i++];
        while ($j < $m) $insBuf .= $tb[$j++];
        $flush();
        return [$head . $outA . $tailA, $head . $outB . $tailB];
    }
}
