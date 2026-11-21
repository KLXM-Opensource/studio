<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Blocks;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Fields;

/**
 * Kern-Block „Personen“ (Renderer app/Blocks/people.php): welche Einträge der Personen-Tabelle erscheinen.
 *
 *  - source = manual (Standard, wie bisher): die Einträge der Liste „Personen“ (Verweis entry:{tabelle}:{id}), in deren Reihenfolge,
 *    mit Funktion, Foto und Text je Stelle.
 *  - source = filter: alle veröffentlichten Einträge der Tabelle, optional nur die, deren Feld filter_field einen der Werte aus
 *    filter_values hat (mehrere Werte: einer je Zeile oder durch Komma – „oder“). Auswahlfelder vergleichen den Kurznamen oder die
 *    Beschriftung der Option, Verknüpfungen ID, Slug oder Titel, Textfelder „enthält“. Sortierung: sort_field (auch die Reihenfolge
 *    von Hand der Tabelle, Feld „sort“) und sort_dir, sonst wie in der Tabelle eingestellt; limit = Höchstzahl (0 = alle).
 *    So wird z. B. der Vorstand nur an einer Stelle gepflegt (Funktion in der Tabelle) und erscheint überall von selbst.
 * Der Seiten-Cache leert sich beim Speichern eines Eintrags ohnehin (Entries::save → PageCache::clear).
 */
final class PeopleSource
{
    /**
     * Zeilen für die Ausgabe: ['i' => Index in der Liste (nur von Hand, sonst null), 'entry' => Eintrag, 'item' => Angaben je Stelle]
     * @return list<array{i: ?int, entry: array, item: array}>
     */
    public static function rows(array $t, array $d, bool $editing = false): array
    {
        if (($d['source'] ?? 'manual') === 'filter') {
            return array_map(fn(array $e) => ['i' => null, 'entry' => $e, 'item' => []], self::filtered($t, $d));
        }
        $out = [];
        foreach ((array) ($d['items'] ?? []) as $i => $it) {
            if (!preg_match('~^entry:([a-z][a-z0-9_]*):(\d+)$~', (string) ($it['person'] ?? ''), $m) || $m[1] !== $t['handle']) continue;
            $e = Entries::find($t, (int) $m[2]);
            if (!$e || (($e['status'] ?? 'published') !== 'published' && !$editing)) continue;
            $out[] = ['i' => (int) $i, 'entry' => $e, 'item' => (array) $it];
        }
        return $out;
    }

    /** Einträge bei source = filter (nur veröffentlichte) */
    public static function filtered(array $t, array $d): array
    {
        $pfx = $t['handle'] . '.';
        $o = ['status' => 'published'];
        $sf = (string) ($d['sort_field'] ?? '');
        if (str_starts_with($sf, $pfx)) $o['sort'] = substr($sf, strlen($pfx));
        if (in_array($d['sort_dir'] ?? '', ['asc', 'desc'], true)) $o['dir'] = (string) $d['sort_dir'];
        $limit = max(0, min(200, (int) ($d['limit'] ?? 0)));
        if ($limit) $o['limit'] = $limit;

        $ff = (string) ($d['filter_field'] ?? '');
        $field = str_starts_with($ff, $pfx) ? Tables::field($t, substr($ff, strlen($pfx))) : null;
        $values = self::values((string) ($d['filter_values'] ?? ''));
        if ($field && $values) {
            // „oder“ über mehrere Werte: je Wert die passenden IDs, dann gemeinsam sortiert und begrenzt
            $ids = [];
            foreach ($values as $v) {
                [$op, $v] = self::condition($field, $v);
                foreach (Entries::query($t, ['status' => 'published', 'where' => [[$field['name'], $op, $v]]]) as $e) $ids[(int) $e['id']] = true;
            }
            if (!$ids) return [];
            // 'ids' gilt sonst für alle Sprachen – die Teilabfragen haben die Sprache schon beachtet
            $o['ids'] = array_keys($ids);
        }
        return Entries::query($t, $o);
    }

    /** Werte aus dem Textfeld: je Zeile oder durch Komma/Semikolon getrennt, ohne Leerzeichen am Rand, ohne Doppelte */
    public static function values(string $raw): array
    {
        $out = [];
        foreach (preg_split('~[\r\n,;]+~', $raw) ?: [] as $v) {
            $v = trim($v);
            if ($v !== '' && !in_array($v, $out, true)) $out[] = $v;
        }
        return array_slice($out, 0, 30);
    }

    /**
     * Bedingung je Feldtyp: Auswahl (auch Beschriftung statt Kurzname), Verknüpfung, Ja/Nein, Zahl/Datum = gleich; Text = enthält.
     * @return array{0: string, 1: string}
     */
    public static function condition(array $field, string $v): array
    {
        $type = (string) ($field['type'] ?? 'text');
        if (in_array($type, ['select', 'multiselect'], true)) {
            $opts = Fields::options($field);
            if (!array_key_exists($v, $opts)) {
                foreach ($opts as $k => $label) {
                    if (mb_strtolower(trim((string) $label)) === mb_strtolower($v) || mb_strtolower((string) $k) === mb_strtolower($v)) { $v = (string) $k; break; }
                }
            }
            return ['=', $v];
        }
        if ($type === 'bool') return ['=', in_array(mb_strtolower($v), ['1', 'ja', 'yes', 'true', 'an'], true) ? '1' : '0'];
        if (in_array($type, ['relation', 'relations', 'number', 'date', 'datetime', 'email', 'tel', 'url'], true)) return ['=', $v];
        return ['contains', $v];
    }
}
