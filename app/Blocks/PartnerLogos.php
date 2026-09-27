<?php
declare(strict_types=1);

namespace Core\Blocks;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;

/**
 * Kern-Block „Partner & Logos“ (Renderer app/Blocks/partners.php, Styles resources/css/partners.css, Verhalten resources/js/partners.mjs).
 *
 * Logos sehr unterschiedlicher Formate wirken gleich schwer: Jedes Logo bekommt in seiner Kachel (einheitliches
 * Seitenverhältnis, Innenabstand) dieselbe FLÄCHE statt derselben Höhe – breite Schriftzüge werden dadurch nicht riesig,
 * quadratische Zeichen nicht winzig. Die Breite wird serverseitig aus Breite/Höhe der Datei berechnet und als Klasse
 * „pl-w-{prozent}“ (2er-Schritte, setzt --pl-w) ausgegeben – keine Inline-Styles (CSP). Grenzen: höchstens volle Breite
 * und 90 % der Höhe der Innenfläche.
 */
final class PartnerLogos
{
    /** Seitenverhältnis der Kachel → [Breite, Höhe] */
    public const RATIOS = ['3:2' => [3, 2], '1:1' => [1, 1], '2:1' => [2, 1]];

    /** Anteil der Innenfläche, den ein Logo einnimmt (Logogröße klein / mittel / groß) */
    public const AREA = ['s' => .2, 'm' => .28, 'l' => .38];

    /** Innenabstand der Kachel in Anteilen der Kachelbreite – muss zu „padding:10%“ in partners.css passen */
    public const PAD = .1;

    /** Feinjustierung je Logo (Logo mit viel Weißraum in der Datei → „größer“) */
    public const ADJUST = ['-1' => .85, '0' => 1.0, '1' => 1.18];

    /**
     * Breite des Logos in Prozent der Innenfläche der Kachel (6–100, gerade Zahl).
     * Fläche konstant: Innenfläche 1 × 1/Ri, Logo wf × wf/a → wf² / a = A / Ri → wf = √(A · a / Ri).
     */
    public static function width(int $w, int $h, string $ratio = '3:2', string $size = 'm', int $adjust = 0): int
    {
        [$rw, $rh] = self::RATIOS[$ratio] ?? self::RATIOS['3:2'];
        $innerW = 1 - 2 * self::PAD;
        $innerH = $rh / $rw - 2 * self::PAD;
        $ri = $innerW / $innerH;                               // Seitenverhältnis der Innenfläche
        $a = $w > 0 && $h > 0 ? $w / $h : 2.0;                 // ohne Maße: typisches Querformat
        $area = self::AREA[$size] ?? self::AREA['m'];
        $wf = sqrt($area * $a / $ri) * (self::ADJUST[(string) max(-1, min(1, $adjust))] ?? 1.0);
        $wf = min($wf, 1.0, $a * .9 / $ri);                    // volle Breite bzw. 90 % der Höhe
        return (int) max(6, min(100, 2 * round($wf * 50)));
    }

    /**
     * Einträge aus der Liste („manual“) oder einer Datentabelle („table“), sortiert.
     * @return list<array{key: string, name: string, info: string, link: string, link_label: string, category: string,
     *     media: ?array, mono: bool, adjust: int, path: ?string, table: ?array, entry: ?array}>
     */
    public static function items(array $d): array
    {
        $items = ($d['source'] ?? 'manual') === 'table' ? self::fromTable($d) : self::fromList($d);
        return self::sort($items, (string) ($d['sort'] ?? 'manual'), ($d['source'] ?? 'manual') === 'table');
    }

    private static function fromList(array $d): array
    {
        $out = [];
        foreach ((array) ($d['items'] ?? []) as $i => $it) {
            if (!is_array($it)) continue;
            $name = trim((string) ($it['name'] ?? ''));
            $m = !empty($it['logo']) ? Media::find((int) $it['logo']) : null;
            if ($m && !str_starts_with((string) $m['mime'], 'image/')) $m = null;
            if ($name === '' && !$m) continue;
            if ($name === '' && $m) $name = Media::alt($m) ?: Media::title($m);
            $out[] = [
                'key' => (string) $i, 'name' => $name, 'info' => trim((string) ($it['info'] ?? '')),
                'link' => trim((string) ($it['link'] ?? '')), 'link_label' => trim((string) ($it['link_label'] ?? '')),
                'category' => trim((string) ($it['category'] ?? '')), 'media' => $m, 'mono' => !empty($it['mono']),
                'adjust' => (int) ($it['scale'] ?? 0), 'path' => "items.$i", 'table' => null, 'entry' => null,
            ];
        }
        return $out;
    }

    private static function fromTable(array $d): array
    {
        $t = Tables::findContent((string) ($d['table'] ?? ''));
        if (!$t) return [];
        $pfx = $t['handle'] . '.';
        // Zuordnung „tabelle.feld“ → Feldname dieser Tabelle; leer = sinnvolle Vorgabe
        $map = function (string $key, ?string $fallback) use ($d, $pfx): ?string {
            $v = (string) ($d[$key] ?? '');
            return str_starts_with($v, $pfx) ? substr($v, strlen($pfx)) : $fallback;
        };
        $first = function (array $types) use ($t): ?string {
            foreach ($t['fields'] as $f) if (in_array($f['type'], $types, true)) return (string) $f['name'];
            return null;
        };
        $fLogo = $map('map_logo', Tables::imageField($t) ?: null);
        $fName = $map('map_name', '_title');
        $fInfo = $map('map_info', ($t['settings']['description_field'] ?? '') ?: null);
        $fLink = $map('map_link', $first(['url', 'link']));
        $fCat = $map('map_category', null);
        $fMono = $map('map_mono', null);

        $o = ['status' => 'published'];
        $op = in_array($d['filter_op'] ?? '=', ['=', '!=', 'contains'], true) ? (string) ($d['filter_op'] ?? '=') : '=';
        if (str_starts_with((string) ($d['filter_field'] ?? ''), $pfx) && trim((string) ($d['filter_value'] ?? '')) !== '') {
            $o['where'] = [[substr((string) $d['filter_field'], strlen($pfx)), $op, trim((string) $d['filter_value'])]];
        }
        if (($d['sort'] ?? '') === 'field' && str_starts_with((string) ($d['sort_field'] ?? ''), $pfx)) {
            $o['sort'] = substr((string) $d['sort_field'], strlen($pfx));
            $o['dir'] = ($d['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        }
        if (!empty($d['source_shared'])) $o['source'] = (string) $d['source_shared'];
        $limit = max(0, (int) ($d['limit'] ?? 0));
        if ($limit) $o['limit'] = $limit;

        $out = [];
        foreach (Entries::query($t, $o) as $e) {
            $m = $fLogo && !empty($e[$fLogo]) && (Tables::field($t, $fLogo)['type'] ?? '') === 'media' ? Media::find((int) $e[$fLogo]) : null;
            if ($m && !str_starts_with((string) $m['mime'], 'image/')) $m = null;
            $name = $fName === '_title' || !$fName ? Entries::title($t, $e) : Entries::text($t, $e, $fName);
            $info = $fInfo ? Entries::html($t, $e, $fInfo, ['link' => false]) : '';   // sicheres HTML (Rich-Text bereinigt, sonst escaped)
            $link = '';
            if ($fLink === '_url') $link = (string) (Entries::href($t, $e) ?? '');
            elseif ($fLink) $link = trim((string) ($e[$fLink] ?? ''));
            $out[] = [
                'key' => 'e' . (int) $e['id'], 'name' => $name, 'info' => trim($info), 'link' => $link, 'link_label' => '',
                'category' => $fCat ? Entries::text($t, $e, $fCat) : '', 'media' => $m, 'mono' => $fMono ? !empty($e[$fMono]) : false,
                'adjust' => 0, 'path' => null, 'table' => $t, 'entry' => $e,
            ];
        }
        return $out;
    }

    /** manual = Reihenfolge der Liste/Tabelle, name = A–Z, category = Kategorie, dann Name, random = zufällig, field = Datenbank */
    public static function sort(array $items, string $sort, bool $table = false): array
    {
        $coll = class_exists(\Collator::class) ? new \Collator(\Core\Lang::current() ?: 'de') : null;
        $cmp = fn(string $a, string $b): int => $coll ? (int) $coll->compare($a, $b) : strnatcasecmp($a, $b);
        switch ($sort) {
            case 'name':
                usort($items, fn($a, $b) => $cmp($a['name'], $b['name']));
                break;
            case 'category':
                // Einträge ohne Kategorie ans Ende
                usort($items, fn($a, $b) => (($a['category'] === '') <=> ($b['category'] === ''))
                    ?: $cmp($a['category'], $b['category']) ?: $cmp($a['name'], $b['name']));
                break;
            case 'random':
                shuffle($items);
                break;
        }
        return array_values($items);
    }

    /** Einträge nach Kategorie gruppieren (Reihenfolge bleibt) → [kategorie => items] */
    public static function groups(array $items): array
    {
        $out = [];
        foreach ($items as $it) $out[$it['category']][] = $it;
        return $out;
    }

    /** Link-Beschriftung: eigene Angabe, sonst „Website besuchen“ bzw. Domain */
    public static function linkLabel(array $it): string
    {
        if ($it['link_label'] !== '') return $it['link_label'];
        return lt('Website besuchen');
    }
}
