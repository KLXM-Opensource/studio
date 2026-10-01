<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Fields;
use Core\Media;
use Core\PageCache;
use Core\Pages;
use Core\Sanitizer;

/**
 * Einträge einer Datentabelle: Abfragen, Speichern, Ausgabe-Formatierung, Detail-URLs.
 */
final class Entries
{
    /**
     * Schutz: Eingangs-Tabellen (verschlüsselte Anfragen) laufen nie über Entries – weder lesen noch schreiben.
     * Greift zentral für Blöcke, Sitemap, Suche, API, MCP, DAV und Feldbindung (siehe Core\Data\Inbox).
     */
    private static function guard(array $table): void
    {
        if (Tables::isInbox($table)) {
            throw new \LogicException("„{$table['handle']}“ ist eine Eingangs-Tabelle – Zugriff nur über Core\\Data\\Inbox.");
        }
    }

    /**
     * Einträge abfragen.
     * $o: status (published|draft|all), q (Suche), where [[feld, op, wert], …] (op: = != > < >= <= contains),
     *     sort (Feld), dir (asc|desc), limit, offset, ids (nur diese IDs)
     * Geteilte Tabellen (Core\Data\Shared) zusätzlich: source (site = wie für die Website eingestellt [Standard], own, owner,
     *     members, own_owner, all, featured), suggested (true | 'pending'), pick (Auswahl dieser Website), origin (Website), featured_first
     */
    public static function query(array $table, array $o = []): array
    {
        [$where, $params] = self::where($table, $o);
        $sort = (string) ($o['sort'] ?? $table['settings']['sort_field']);
        if (!in_array($sort, array_merge(Tables::SYSTEM, array_column($table['fields'], 'name')), true)) $sort = 'sort';
        $dir = strtolower((string) ($o['dir'] ?? $table['settings']['sort_dir'])) === 'desc' ? 'DESC' : 'ASC';
        $pre = [];
        $order = "$sort $dir, id $dir";
        if (Tables::isShared($table) && !empty($o['featured_first'])) $order = "CASE WHEN _pick = 'featured' THEN 0 ELSE 1 END, " . $order;
        $sql = 'SELECT ' . self::select($table, $pre) . " FROM {$table['table']}" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY $order";
        if (!empty($o['limit'])) {
            $sql .= ' LIMIT ' . max(1, (int) $o['limit']) . ' OFFSET ' . max(0, (int) ($o['offset'] ?? 0));
        }
        return self::attach($table, array_map(fn($r) => self::decode($table, $r), Tables::db($table)->fetchAll($sql, array_merge($pre, $params))));
    }

    /** Spalten: bei geteilten Tabellen zusätzlich die Auswahl dieser Website (_pick: visible|featured|hidden|rejected) */
    private static function select(array $table, array &$params): string
    {
        if (!Tables::isShared($table)) return '*';
        $params[] = site()->key;
        return "*, (SELECT pick_state FROM share_picks WHERE pick_site = ? AND pick_entry = {$table['table']}.id) AS _pick";
    }

    /** Nach Änderungen: Seiten-Cache dieser Website bzw. aller Websites einer geteilten Tabelle leeren */
    private static function changed(array $table): void
    {
        PageCache::clear();
        if (Tables::isShared($table)) {
            Shared::clearCaches($table['shared']['key']);
            Shared::touch($table);
        }
    }

    /** n:m-Verknüpfungen für mehrere Einträge auf einmal laden */
    private static function attach(array $table, array $rows): array
    {
        if (!$rows) return $rows;
        $ids = implode(',', array_map(fn($r) => (int) $r['id'], $rows));
        foreach ($table['fields'] as $f) {
            if ($f['type'] !== 'relations') continue;
            $map = [];
            foreach (Tables::db($table)->fetchAll('SELECT entry_id, target_id FROM ' . Tables::pivot($table, $f['name']) . " WHERE entry_id IN ($ids) ORDER BY sort, target_id") as $p) {
                $map[(int) $p['entry_id']][] = (int) $p['target_id'];
            }
            foreach ($rows as &$r) $r[$f['name']] = $map[$r['id']] ?? [];
            unset($r);
        }
        return $rows;
    }

    public static function count(array $table, array $o = []): int
    {
        [$where, $params] = self::where($table, $o);
        return (int) Tables::db($table)->fetchValue("SELECT COUNT(*) FROM {$table['table']}" . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $params);
    }

    private static function where(array $table, array $o): array
    {
        self::guard($table);
        $where = [];
        $params = [];
        // Sprache: Standard = aktuelle Sprache (nur wenn mehrere Sprachen aktiv sind); 'all' = alle
        $lang = $o['lang'] ?? (!empty($o['ids']) || !\Core\Lang::multi() ? 'all' : \Core\Lang::current());
        if ($lang !== 'all') {
            $where[] = \Core\Lang::sql();
            $params[] = \Core\Lang::norm($lang);
        }
        $status = $o['status'] ?? 'published';
        if ($status !== 'all') {
            $where[] = 'status = ?';
            $params[] = $status === 'draft' ? 'draft' : 'published';
        }
        if (!empty($o['exclude'])) {
            $where[] = 'id != ?';
            $params[] = (int) $o['exclude'];
        }
        if (!empty($o['ids'])) {
            $ids = array_values(array_filter(array_map('intval', (array) $o['ids'])));
            $where[] = $ids ? 'id IN (' . implode(',', $ids) . ')' : '1 = 0';
        }
        $q = trim((string) ($o['q'] ?? ''));
        if ($q !== '') {
            $cols = array_column(array_filter($table['fields'], fn($f) => !empty($f['searchable']) || in_array($f['type'], ['text'], true)), 'name');
            $cols[] = 'slug';
            $where[] = '(' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $cols)) . ')';
            array_push($params, ...array_fill(0, count($cols), '%' . $q . '%'));
        }
        array_push($where, ...self::conditions($table, (array) ($o['where'] ?? []), $params));
        // Stellenangebote (Core\Data\Jobs): abgelaufene („Gültig bis“ vor heute) erscheinen auf der Website nicht – in Listen, Sitemap,
        // Suche und API; Ausnahmen: Verwaltung, Abfragen nach IDs, ausdrücklich 'expired' => true
        if ($status === 'published' && empty($o['expired']) && empty($o['ids']) && Jobs::is($table) && !(app()->request?->isAdminPath() ?? false)
            && ($sql = Jobs::currentSql($table, $params))) {
            $where[] = $sql;
        }
        // Geteilte Tabellen: Quelle (eigene, Eigentümer, Mitglieder, wie eingestellt …), Vorschläge, Auswahl, Herkunft
        if (Tables::isShared($table)) {
            $src = (string) ($o['source'] ?? 'site');
            $where[] = Shared::sourceSql($table, in_array($src, Shared::SOURCES, true) ? $src : 'site', $params,
                fn(array $w, array &$p) => self::conditions($table, $w, $p));
            if (!empty($o['suggested'])) {
                $where[] = 'suggest = 1';
                if ($o['suggested'] === 'pending') {
                    $where[] = 'id NOT IN (SELECT pick_entry FROM share_picks WHERE pick_site = ?)';
                    $params[] = $table['shared']['owner'];
                }
            }
            if (!empty($o['pick'])) {
                $where[] = 'id IN (SELECT pick_entry FROM share_picks WHERE pick_site = ? AND pick_state = ?)';
                array_push($params, site()->key, (string) $o['pick']);
            }
            if (!empty($o['origin'])) {
                $where[] = 'origin_site = ?';
                $params[] = (string) $o['origin'];
            }
        }
        return [$where, $params];
    }

    /** Bedingungen [[feld, op, wert], …] als SQL-Teile (Parameter werden an $params angehängt) */
    public static function conditions(array $table, array $list, array &$params): array
    {
        $where = [];
        $names = array_merge(Tables::SYSTEM, array_column($table['fields'], 'name'));
        foreach ($list as $w) {
            [$f, $op, $v] = array_pad(array_values((array) $w), 3, '');
            if (!in_array($f, $names, true) || $v === '' || $v === null) continue;
            $field = Tables::field($table, (string) $f);
            // Verknüpfungen: Wert = ID, Slug oder Titel des verknüpften Eintrags
            if (in_array($field['type'] ?? '', ['relation', 'relations'], true)) {
                $target = Tables::findContent($field['target'] ?? '');
                $tid = ctype_digit((string) $v) ? (int) $v : ($target ? (int) (Tables::db($target)->fetchValue("SELECT id FROM {$target['table']} WHERE slug = ?", [Pages::slugify((string) $v)]) ?: 0) : 0);
                $neg = $op === '!=';
                if ($field['type'] === 'relation') {
                    $where[] = $neg ? "($f IS NULL OR $f != ?)" : "$f = ?";
                } else {
                    $where[] = 'id ' . ($neg ? 'NOT ' : '') . 'IN (SELECT entry_id FROM ' . Tables::pivot($table, $f) . ' WHERE target_id = ?)';
                }
                $params[] = $tid;
                continue;
            }
            if (($field['type'] ?? '') === 'multiselect' || $op === 'contains') {
                $where[] = "$f LIKE ?";
                $params[] = '%' . (($field['type'] ?? '') === 'multiselect' ? json_encode((string) $v) : $v) . '%';
                continue;
            }
            $sqlOp = match ($op) { '!=' => '!=', '>' => '>', '<' => '<', '>=' => '>=', '<=' => '<=', default => '=' };
            // „heute“ als Wert für Datumsfelder (z. B. nur künftige Termine)
            $where[] = "$f $sqlOp ?";
            $params[] = $v === 'heute' ? date('Y-m-d') : $v;
        }
        return $where;
    }

    public static function find(array $table, int $id): ?array
    {
        self::guard($table);
        $params = [];
        $sel = self::select($table, $params);
        $params[] = $id;
        $r = Tables::db($table)->fetch("SELECT $sel FROM {$table['table']} WHERE id = ?", $params);
        return $r ? self::attach($table, [self::decode($table, $r)])[0] : null;
    }

    /** $source (nur geteilte Tabellen): nur Einträge dieser Quelle, z. B. 'site' für Detailseiten der Website */
    public static function bySlug(array $table, string $slug, bool $publishedOnly = true, ?string $lang = null, ?string $source = null): ?array
    {
        self::guard($table);
        $params = [];
        $sql = 'SELECT ' . self::select($table, $params) . " FROM {$table['table']} WHERE slug = ?" . ($publishedOnly ? " AND status = 'published'" : '');
        $params[] = $slug;
        if ($lang !== null) {
            $sql .= ' AND ' . \Core\Lang::sql();
            $params[] = \Core\Lang::norm($lang);
        }
        if ($source !== null && Tables::isShared($table)) {
            $sql .= ' AND ' . Shared::sourceSql($table, $source, $params, fn(array $w, array &$p) => self::conditions($table, $w, $p));
        }
        $r = Tables::db($table)->fetch($sql, $params);
        return $r ? self::attach($table, [self::decode($table, $r)])[0] : null;
    }

    private static function decode(array $table, array $r): array
    {
        $shared = Tables::isShared($table);
        foreach ($table['fields'] as $f) {
            if ($shared && in_array($f['type'], ['media', 'file'], true)) {
                // Geteilt: gespeichert ist die Pool-ID – für diese Website als eigener Verweis (Medien-ID der Website)
                $r[$f['name']] = Shared::fromPool($table, $r[$f['name']] ?? null);
                continue;
            }
            if ($f['type'] === 'multiselect') {
                $r[$f['name']] = json_decode((string) ($r[$f['name']] ?? ''), true) ?: [];
            } elseif ($f['type'] === 'group') {
                $r[$f['name']] = self::groupRows($f, $r[$f['name']] ?? null);
            } elseif ($f['type'] === 'bool') {
                $r[$f['name']] = (bool) ($r[$f['name']] ?? false);
            } elseif (in_array($f['type'], ['media', 'file', 'relation'], true)) {
                $r[$f['name']] = $r[$f['name']] !== null ? (int) $r[$f['name']] : null;
            }
        }
        $r['id'] = (int) $r['id'];
        if ($shared) {
            $r['suggest'] = !empty($r['suggest']);
            $r['_pick'] ??= null;
        }
        return $r;
    }

    // ================================================================= Formular-Schema

    /** Felder der Tabelle als Fields-Schema (für Formulare und Bereinigung) */
    public static function schema(array $table): array
    {
        $out = [];
        foreach ($table['fields'] as $f) {
            $def = ['name' => $f['name'], 'label' => $f['label'], 'type' => $f['type'], 'required' => $f['required'],
                'help' => $f['help'] ?? '', 'width' => $f['width'] ?? ''];
            if (in_array($f['type'], ['select', 'multiselect'], true)) {
                $def['options'] = $f['options'] ?? [];
            }
            if ($f['type'] === 'group') {
                $def = self::groupSchema($f) + $def;
            }
            if ($f['type'] === 'recurrence') {
                // Regel-Editor liest Beginn (Wochentag, Tag im Monat) und „ganztägig“ aus dem Formular
                $cal = Calendar::config($table);
                $def['start_field'] = $cal['start'] ?: (array_values(array_filter($table['fields'], fn($x) => in_array($x['type'], ['datetime', 'date'], true)))[0]['name'] ?? '');
                $def['all_day_field'] = $cal['all_day'];
            }
            if (in_array($f['type'], ['relation', 'relations'], true)) {
                $target = Tables::findContent($f['target'] ?? '');
                $def['type'] = $f['type'] === 'relation' ? 'select' : 'multiselect';
                $def['options'] = $target ? self::titles($target) : [];
                if ($target) {
                    $def['relation'] = ['table' => $target['name'], 'singular' => $target['singular'], 'create' => url('/admin/data/' . $target['handle'] . '/quick')];
                }
            }
            $out[] = $def;
        }
        return $out;
    }

    /**
     * Wiederholbare Gruppe als Fields-Schema: fields (Unterfelder), min, max, item_label, add_label.
     * $site = true: Beschriftungen in der Sprache der Seite (Übersetzung der Definition, sonst lt()) – für öffentliche Formulare.
     */
    public static function groupSchema(array $f, bool $site = false): array
    {
        $tr = fn(string $s) => $site && $s !== '' ? lt($s) : $s;
        $subs = [];
        foreach ((array) ($f['fields'] ?? []) as $sf) {
            $label = $site ? (isset($sf['labels'][\Core\Lang::current()]) ? Tables::label($sf) : $tr((string) $sf['label'])) : (string) $sf['label'];
            $d = ['name' => $sf['name'], 'label' => $label, 'type' => $sf['type'], 'required' => !empty($sf['required']), 'width' => $sf['width'] ?? ''];
            if ($sf['type'] === 'select') {
                $d['options'] = [];
                foreach ((array) ($sf['options'] ?? []) as $k => $v) {
                    $d['options'][$k] = $site ? (isset($sf['options_i18n'][\Core\Lang::current()][$k]) ? Tables::optionLabel($sf, (string) $k) : $tr((string) $v)) : $v;
                }
            }
            if (in_array($sf['type'], ['text', 'email', 'tel'], true)) $d['max'] = 255;
            if ($sf['type'] === 'textarea') $d['max'] = 1000;
            $subs[] = $d;
        }
        return ['fields' => $subs, 'min' => (int) ($f['min'] ?? 0), 'max' => (int) ($f['max'] ?? 10),
            'item_label' => $tr((string) ($f['item_label'] ?? '')) ?: ($site ? lt('Eintrag') : 'Eintrag'), 'add_label' => $tr((string) ($f['add_label'] ?? ''))];
    }

    /** Wert einer Gruppe als Liste von Zeilen (alte Einzelwerte = eine Zeile im ersten Unterfeld) */
    public static function groupRows(array $f, mixed $v): array
    {
        if (is_string($v) && $v !== '' && ($j = json_decode($v, true)) !== null && is_array($j)) $v = $j;
        if (is_scalar($v) && trim((string) $v) !== '') return [[($f['fields'][0]['name'] ?? 'wert') => (string) $v]];
        return is_array($v) ? array_values(array_filter($v, 'is_array')) : [];
    }

    /**
     * Unterfeld-Wert als Klartext (Verwaltung, Ausgabe). $lang: Sprache der Auswahltexte (null = aktuelle).
     */
    public static function groupCell(array $sf, mixed $v, ?string $lang = null): string
    {
        if ($v === null || $v === '' || $v === []) return '';
        return match ($sf['type'] ?? 'text') {
            'bool' => $v ? '✓' : '',
            'select' => Tables::optionLabel($sf, (string) $v, $lang),
            'date' => preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', (string) $v, $m) ? "$m[3].$m[2].$m[1]" : (string) $v,
            'number' => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ','),
            'iban' => \Core\Iban::format((string) $v),
            default => is_scalar($v) ? (string) $v : '',
        };
    }

    /** Kurzfassung für Listen: „Ibuprofen, Paracetamol +1“ bzw. „3 Einträge“ */
    public static function groupSummary(array $f, mixed $v): string
    {
        $rows = self::groupRows($f, $v);
        if (!$rows) return '';
        $first = $f['fields'][0] ?? null;
        $vals = $first ? array_values(array_filter(array_map(fn($r) => self::groupCell($first, $r[$first['name']] ?? null), $rows), fn($s) => $s !== '')) : [];
        if (!$vals) return count($rows) === 1 ? __('1 Eintrag') : __('{n} Einträge', ['n' => count($rows)]);
        $shown = array_slice($vals, 0, 2);
        return mb_strimwidth(implode(', ', $shown), 0, 80, '…') . (count($rows) > count($shown) ? ' +' . (count($rows) - count($shown)) : '');
    }

    /** id → Titel aller Einträge (für Verknüpfungen) */
    public static function titles(array $table): array
    {
        $out = [];
        foreach (self::query($table, ['status' => 'all', 'limit' => 1000, 'source' => 'all']) as $e) {
            $out[$e['id']] = self::title($table, $e);
        }
        return $out;
    }

    // ================================================================= Speichern

    /**
     * Eintrag anlegen oder ändern.
     * $in: Feldwerte + optional slug, status. @return array{0: ?int, 1: array} [id, errors]
     */
    public static function save(array $table, ?int $id, array $in): array
    {
        self::guard($table);
        $current = $id ? self::find($table, $id) : null;
        if ($id && !$current) {
            return [null, ['_' => 'Eintrag nicht gefunden.']];
        }
        // Geteilte Tabelle: jede Website ändert nur ihre eigenen Einträge
        $shared = Tables::isShared($table);
        if ($current && $shared && Shared::isForeign($table, $current)) {
            return [null, ['_' => __('Dieser Eintrag gehört zu einer anderen Website und lässt sich hier nicht ändern.')]];
        }
        // Externe Quellen (Core\Sources): übernommene Einträge ändert nur der Abgleich – Verwaltung, API, MCP, DAV sind schreibgeschützt
        if ($current && !\Core\Sources\Sync::$writing && \Core\Sources\Sources::isExternal($table, (int) $id)) {
            return [null, ['_' => __('Dieser Eintrag stammt aus einer externen Quelle und wird bei jedem Abruf aktualisiert – Änderungen bitte in der Quelle vornehmen.')]];
        }
        $db = Tables::db($table);
        // Teilaktualisierung (z. B. per API): fehlende Felder behalten ihren Wert
        if ($current) {
            foreach ($table['fields'] as $f) {
                if (!array_key_exists($f['name'], $in)) $in[$f['name']] = $current[$f['name']];
            }
        }
        [$values, $errors] = Fields::sanitize(self::schema($table), $in, $current ?? []);
        // Bedingungen: ausgeblendete Felder leeren, „Pflicht wenn“ und Vergleiche prüfen (gilt für Verwaltung, API, MCP, DAV, Formulare)
        [$values, $errors] = Rules::apply($table['fields'], $values, $errors);
        if ($errors) {
            return [null, $errors];
        }
        $row = [];
        $pivots = [];
        foreach ($table['fields'] as $f) {
            $v = $values[$f['name']] ?? null;
            if ($f['type'] === 'relations') {
                $pivots[$f['name']] = array_values(array_unique(array_filter(array_map('intval', (array) $v))));
                continue;
            }
            $row[$f['name']] = match (true) {
                $f['type'] === 'multiselect' => json_encode(array_values((array) $v), JSON_UNESCAPED_UNICODE),
                $f['type'] === 'group' => $v ? json_encode(array_values((array) $v), JSON_UNESCAPED_UNICODE) : null,
                $f['type'] === 'bool' => $v ? 1 : 0,
                in_array($f['type'], ['media', 'file', 'relation'], true) => $v !== null && $v !== '' ? (int) $v : null,
                $f['type'] === 'number' => $v === null || $v === '' ? null : (float) $v,
                default => $v === null ? null : (string) $v,
            };
            // Geteilt: Bilder/Dateien liegen im Pool der Tabelle, gespeichert wird die Pool-ID
            if ($shared && in_array($f['type'], ['media', 'file'], true) && $row[$f['name']] !== null) {
                try {
                    $row[$f['name']] = Shared::toPool($table, $row[$f['name']]);
                } catch (\Throwable $ex) {
                    return [null, [$f['name'] => __('„{label}“: Datei konnte nicht in die geteilten Medien übernommen werden ({error}).', ['label' => $f['label'], 'error' => $ex->getMessage()])]];
                }
            }
        }
        // Slug: aus Eingabe oder Titel, je Tabelle eindeutig
        $title = (string) ($values[$table['settings']['title_field']] ?? '');
        $slug = Pages::slugify((string) ($in['slug'] ?? '') ?: ($current['slug'] ?? '') ?: strip_tags($title) ?: 'eintrag');
        $base = $slug;
        $lang = array_key_exists('lang', $in) ? (\Core\Lang::valid((string) $in['lang']) && $in['lang'] !== \Core\Lang::default() ? (string) $in['lang'] : null) : ($current['lang'] ?? null);
        $row['lang'] = $lang;
        for ($n = 2; $db->fetchValue("SELECT id FROM {$table['table']} WHERE slug = ? AND id != ? AND " . \Core\Lang::sql(), [$slug, (int) $id, \Core\Lang::norm($lang)]); $n++) {
            $slug = $base . '-' . $n;
        }
        $row['slug'] = $slug;
        $status = ($in['status'] ?? ($current['status'] ?? 'published')) === 'draft' ? 'draft' : 'published';
        if (!$table['settings']['workflow']) $status = 'published';
        $row['status'] = $status;
        $row['updated_at'] = now();
        if ($status === 'published' && empty($current['published_at'])) {
            $row['published_at'] = now();
        }
        if ($shared && array_key_exists('_suggest', $in)) {
            // „Dem Verband vorschlagen“ – nur Mitglieder, nicht die Eigentümer-Website
            $row['suggest'] = !Shared::isOwner($table) && !empty($in['_suggest']) && $in['_suggest'] !== '0' ? 1 : 0;
        }
        if ($current) {
            $db->update($table['table'], $row, 'id = :id', ['id' => $id]);
        } else {
            $row['created_at'] = now();
            $row['sort'] = (int) $db->fetchValue("SELECT COALESCE(MAX(sort), 0) + 10 FROM {$table['table']}");
            if ($shared) $row['origin_site'] = site()->key;
            $id = $db->insert($table['table'], $row);
        }
        foreach ($pivots as $name => $ids) {
            $pv = Tables::pivot($table, $name);
            $db->query("DELETE FROM $pv WHERE entry_id = ?", [$id]);
            foreach ($ids as $i => $tid) {
                $db->insert($pv, ['entry_id' => $id, 'target_id' => $tid, 'sort' => $i]);
            }
        }
        self::changed($table);
        return [$id, []];
    }

    public static function delete(array $table, int $id): void
    {
        self::guard($table);
        $db = Tables::db($table);
        if (Tables::isShared($table)) {
            // Geteilt: nur eigene Einträge; Auswahl aller Websites mit entfernen
            if ($db->query("DELETE FROM {$table['table']} WHERE id = ? AND origin_site = ?", [$id, site()->key])->rowCount() === 0) return;
            $db->query('DELETE FROM share_picks WHERE pick_entry = ?', [$id]);
        } else {
            $db->query("DELETE FROM {$table['table']} WHERE id = ?", [$id]);
        }
        foreach ($table['fields'] as $f) {
            if ($f['type'] === 'relations') $db->query('DELETE FROM ' . Tables::pivot($table, $f['name']) . ' WHERE entry_id = ?', [$id]);
        }
        // Verweise anderer Tabellen lösen (jeweils in deren Datenbank)
        foreach (Tables::referencing($table) as [$t, $f]) {
            if ($f['type'] === 'relation') Tables::db($t)->query("UPDATE {$t['table']} SET {$f['name']} = NULL WHERE {$f['name']} = ?", [$id]);
            else Tables::db($t)->query('DELETE FROM ' . Tables::pivot($t, $f['name']) . ' WHERE target_id = ?', [$id]);
        }
        self::changed($table);
    }

    /** Wer verweist auf diesen Eintrag? [['table' => …, 'field' => …, 'entries' => […]], …] */
    public static function backlinks(array $table, int $id): array
    {
        $out = [];
        foreach (Tables::referencing($table) as [$t, $f]) {
            $rows = self::query($t, ['status' => 'all', 'where' => [[$f['name'], '=', (string) $id]], 'limit' => 50, 'source' => 'all']);
            if ($rows) $out[] = ['table' => $t, 'field' => $f, 'entries' => $rows];
        }
        return $out;
    }

    /** Übersetzungen eines Eintrags: [sprache => eintrag] */
    public static function translations(array $table, array $e): array
    {
        $group = (int) (($e['translation_group'] ?? null) ?: $e['id']);
        $out = [];
        foreach (self::query($table, ['status' => 'all', 'lang' => 'all', 'where' => [], 'source' => 'all', 'ids' => array_map('intval', array_column(
            Tables::db($table)->fetchAll("SELECT id FROM {$table['table']} WHERE id = ? OR translation_group = ?", [$group, $group]), 'id'))]) as $r) {
            $out[\Core\Lang::norm($r['lang'] ?? null)] = $r;
        }
        return $out;
    }

    /** Übersetzung eines Eintrags anlegen (Kopie als Entwurf in der Zielsprache, verknüpft) */
    public static function translate(array $table, int $id, string $lang): array
    {
        $e = self::find($table, $id) ?? throw new \RuntimeException('Eintrag nicht gefunden.');
        if (Shared::isForeign($table, $e)) throw new \RuntimeException(__('Dieser Eintrag gehört zu einer anderen Website und lässt sich hier nicht ändern.'));
        if (!\Core\Lang::valid($lang)) throw new \RuntimeException('Unbekannte Sprache.');
        if ($existing = self::translations($table, $e)[$lang] ?? null) return $existing;
        $group = (int) (($e['translation_group'] ?? null) ?: $e['id']);
        Tables::db($table)->update($table['table'], ['translation_group' => $group], 'id = :id', ['id' => $group]);
        $in = [];
        foreach ($table['fields'] as $f) $in[$f['name']] = $e[$f['name']] ?? null;
        [$newId, $errors] = self::save($table, null, $in + ['slug' => $e['slug'], 'status' => 'draft', 'lang' => $lang]);
        if ($errors) throw new \RuntimeException(implode(' ', $errors));
        Tables::db($table)->update($table['table'], ['translation_group' => $group], 'id = :id', ['id' => $newId]);
        return self::find($table, $newId);
    }

    /** Schnell einen Eintrag nur mit Titel anlegen (z. B. neues Schlagwort im Formular) */
    public static function quickCreate(array $table, string $title): array
    {
        $title = trim(strip_tags($title));
        $tf = $table['settings']['title_field'];
        if ($title === '' || $tf === '') return [null, 'Bitte einen Namen angeben.'];
        $existing = Tables::db($table)->fetchValue("SELECT id FROM {$table['table']} WHERE $tf = ?", [$title]);
        if ($existing) return [(int) $existing, null];
        $in = [$tf => $title, 'status' => 'published'];
        foreach ($table['fields'] as $f) {
            if ($f['required'] && $f['name'] !== $tf) return [null, "„{$table['name']}“ hat weitere Pflichtfelder – bitte dort anlegen."];
        }
        [$id, $errors] = self::save($table, null, $in);
        return [$id, $errors ? implode(' ', $errors) : null];
    }

    public static function setStatus(array $table, array $ids, string $status): void
    {
        self::guard($table);
        $db = Tables::db($table);
        // Geteilt: nur eigene Einträge
        [$own, $op] = Tables::isShared($table) ? [' AND origin_site = :origin', ['origin' => site()->key]] : ['', []];
        foreach (array_map('intval', $ids) as $id) {
            $upd = ['status' => $status === 'draft' ? 'draft' : 'published', 'updated_at' => now()];
            $db->update($table['table'], $upd, 'id = :id' . $own, ['id' => $id] + $op);
            if ($upd['status'] === 'published') {
                $db->query("UPDATE {$table['table']} SET published_at = ? WHERE id = ? AND published_at IS NULL" . ($own ? ' AND origin_site = ?' : ''), array_merge([now(), $id], array_values($op)));
            }
        }
        self::changed($table);
    }

    /** Manuelle Reihenfolge speichern */
    public static function reorder(array $table, array $ids): void
    {
        self::guard($table);
        $db = Tables::db($table);
        [$own, $op] = Tables::isShared($table) ? [' AND origin_site = :origin', ['origin' => site()->key]] : ['', []];
        foreach (array_values(array_map('intval', $ids)) as $i => $id) {
            $db->update($table['table'], ['sort' => ($i + 1) * 10], 'id = :id' . $own, ['id' => $id] + $op);
        }
        self::changed($table);
    }

    // ================================================================= Ausgabe

    public static function title(array $table, array $e): string
    {
        $f = $table['settings']['title_field'];
        $t = $f !== '' ? trim(strip_tags((string) ($e[$f] ?? ''))) : '';
        return $t !== '' ? $t : ($table['singular'] . ' #' . $e['id']);
    }

    /**
     * Link für Listen, Kalender und Verknüpfungen: wie url(); fremde Einträge geteilter Tabellen auf Wunsch der Website
     * („Direkt auf die Ursprungs-Website verlinken“) mit der Adresse auf ihrer Ursprungs-Website.
     */
    public static function href(array $table, array $e): ?string
    {
        if (Tables::isShared($table) && Shared::isForeign($table, $e) && Shared::localConfig($table['shared']['key'])['link_origin']
            && ($o = Shared::originUrl($table, $e))) {
            return $o;
        }
        return self::url($table, $e);
    }

    /** Absolute Adresse (für Canonical, JSON-LD, Feeds): fremde Einträge → Ursprungs-Website, falls sie Detailseiten hat */
    public static function absUrl(array $table, array $e): ?string
    {
        if (Tables::isShared($table) && ($o = Shared::originUrl($table, $e))) return $o;
        $u = self::url($table, $e);
        return $u !== null ? site_url() . $u : null;
    }

    /** URL der Detailseite (nur wenn eine URL-Basis und eine Detailvorlage existieren) */
    public static function url(array $table, array $e): ?string
    {
        if ($table['settings']['route'] === '' || empty($table['settings']['detail_page_id']) || empty($e['slug'])) {
            return null;
        }
        return url(\Core\Lang::prefix($e['lang'] ?? null) . '/' . $table['settings']['route'] . '/' . $e['slug']);
    }

    /** Rohwert als Text (für Platzhalter {{feld}} in normalen Textfeldern) */
    public static function text(array $table, array $e, string $name): string
    {
        return trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</li>'], ["\n", "\n", "\n"], self::html($table, $e, $name))), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * Feld als sicheres HTML ausgeben.
     * $o: ratio (Bildformat), sizes, link (bool: Verknüpfungen verlinken), date_style (short|long, sprachabhängig) oder date_format (fest)
     */
    public static function html(array $table, array $e, string $name, array $o = []): string
    {
        if ($name === '_title') return e(self::title($table, $e));
        if ($name === '_url') return e(self::href($table, $e) ?? '');
        if ($name === '_origin') {
            // Geteilte Tabellen: Name der Website, von der der Eintrag stammt
            return Tables::isShared($table) && !empty($e['origin_site']) ? e(Shared::siteInfo((string) $e['origin_site'], $table['shared']['key'])['name']) : '';
        }
        if ($name === '_when') {
            // Kalender: Zeitraum des Termins + Wiederholung, z. B. „Mo., 05.10.2026, 09:00–11:00 Uhr · Wöchentlich am Montag“
            if (!Calendar::enabled($table) || !($span = Calendar::span($table, $e))) return '';
            $rec = Calendar::recurrence($table, $e);
            return e(Calendar::when($span + ['recurring' => false], $o['date_style'] ?? 'short') . ($rec['rrule'] !== '' ? ' · ' . Calendar::describe($rec) : ''));
        }
        if (in_array($name, ['created_at', 'updated_at', 'published_at'], true)) {
            return !empty($e[$name]) ? e(date_local((string) $e[$name], $o['date_style'] ?? 'short')) : '';
        }
        $f = Tables::field($table, $name);
        if (!$f) return '';
        $v = $e[$name] ?? null;
        if ($v === null || $v === '' || $v === []) return '';
        return match ($f['type']) {
            'richtext' => Sanitizer::block((string) $v),
            'textarea' => nl2br(e((string) $v), false),
            'bool' => $v ? lt('Ja') : lt('Nein'),
            'number' => e(rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',')),
            'date' => ($ts = strtotime((string) $v)) ? e(isset($o['date_format']) ? date($o['date_format'], $ts) : date_local($ts, $o['date_style'] ?? 'short')) : e((string) $v),
            'time' => e(ltrim((string) $v, '0') ?: (string) $v),
            'datetime' => ($ts = strtotime((string) $v)) ? e((isset($o['date_format']) ? date($o['date_format'], $ts) : date_local($ts, $o['date_style'] ?? 'short')) . ', ' . lt('{time} Uhr', ['time' => date('H:i', $ts)])) : e((string) $v),
            'recurrence' => e(Calendar::describe((string) $v)),
            'select' => e(Tables::optionLabel($f, (string) $v)),
            'multiselect' => e(implode(', ', array_map(fn($k) => Tables::optionLabel($f, (string) $k), (array) $v))),
            'media' => Media::picture((int) $v, $o['sizes'] ?? '(min-width: 1080px) 800px, 100vw', array_filter(['ratio' => $o['ratio'] ?? null])),
            // Video/Audio: Player mit Untertiteln und Transkript (Core\MediaTracks), sonst Download-Link
            'file' => ($m = Media::find((int) $v)) && empty($o['plain']) && \Core\MediaTracks::supports($m) ? \Core\MediaTracks::player($m, ['class' => 'dt-media'])
                : (($m = Media::find((int) $v)) ? '<a href="' . e(Media::url($m)) . '" download>' . e(Media::displayName($m)) . ' (' . e(Media::typeLabel($m['mime'])) . ', ' . e(Media::humanSize((int) $m['size'])) . ')</a>' : ''),
            'link' => '<a href="' . e(link_href((string) $v)) . '"' . ext_attrs(link_href((string) $v)) . '>' . e(preg_replace('~^https?://(www\.)?~', '', (string) $v)) . '</a>',
            'url' => '<a href="' . e((string) $v) . '" rel="noopener" target="_blank">' . e(preg_replace('~^https?://(www\.)?~', '', (string) $v)) . '</a>',
            'email' => '<a href="mailto:' . e((string) $v) . '">' . e((string) $v) . '</a>',
            'tel' => '<a href="tel:' . e(preg_replace('~[^\d+]~', '', (string) $v)) . '">' . e((string) $v) . '</a>',
            'color' => '<span class="dt-swatch" data-color="' . e((string) $v) . '"></span>' . e((string) $v),
            // IBAN: Vierergruppen; maskiert, solange das Feld „In Listen maskieren“ hat (Ausnahme: $o['unmask'])
            'iban' => e(($f['mask'] ?? true) && empty($o['unmask']) ? \Core\Iban::mask((string) $v) : \Core\Iban::format((string) $v)),
            'geo' => ($o['plain'] ?? false) ? e((string) $v) : \Core\Maps::render(['point' => $v, 'label' => self::title($table, $e), 'height' => 's']),
            'relation', 'relations' => self::relationHtml($f, is_array($v) ? $v : [(int) $v], $o),
            'group' => self::groupHtml($f, $v),
            default => e((string) $v),
        };
    }

    /** Gruppe als Liste: je Zeile der erste Wert, dann „Beschriftung: Wert“ der übrigen Unterfelder */
    private static function groupHtml(array $f, mixed $v): string
    {
        $out = [];
        foreach (self::groupRows($f, $v) as $row) {
            $parts = [];
            foreach ((array) ($f['fields'] ?? []) as $i => $sf) {
                $cell = self::groupCell($sf, $row[$sf['name']] ?? null);
                if ($cell === '') continue;
                $parts[] = $i === 0 ? e($cell) : '<span class="dt-group__k">' . e(Tables::label($sf)) . ':</span> ' . e($cell);
            }
            if ($parts) $out[] = '<li>' . implode(' · ', $parts) . '</li>';
        }
        return $out ? '<ul class="dt-group">' . implode('', $out) . '</ul>' : '';
    }

    private static function relationHtml(array $f, array $ids, array $o): string
    {
        $target = Tables::findContent($f['target'] ?? '');
        if (!$target || !$ids) return '';
        $out = [];
        $rows = array_column(self::query($target, ['ids' => $ids, 'source' => 'all']), null, 'id');
        foreach ($ids as $id) {
            if (!isset($rows[(int) $id])) continue;
            $r = $rows[(int) $id];
            $url = ($o['link'] ?? true) ? self::href($target, $r) : null;
            $out[] = $url ? '<a class="dl-chip" href="' . e($url) . '">' . e(self::title($target, $r)) . '</a>' : '<span class="dl-chip">' . e(self::title($target, $r)) . '</span>';
        }
        return $out ? '<span class="dl-chips">' . implode(' ', $out) . '</span>' : '';
    }

    /**
     * Welche Datensatz-Felder passen zu einem Blockfeld-Typ? [quelle => Bezeichnung]
     * Sonderquellen: _title (Titel), _url (Adresse der Detailseite), published_at.
     */
    public static function bindable(array $table, string $targetType): array
    {
        $allow = match ($targetType) {
            'media' => ['media'],
            'file' => ['file', 'media'],
            'text', 'textarea', 'tel', 'email' => ['_title', 'text', 'textarea', 'select', 'date', 'datetime', 'time', 'recurrence', 'number', 'email', 'tel', 'url', 'relation', 'relations', 'multiselect', 'iban', 'published_at'],
            'richtext', 'inline' => ['_title', 'richtext', 'textarea', 'text', 'select', 'date', 'datetime', 'recurrence', 'relation', 'relations', 'multiselect', 'published_at'],
            'link', 'url' => ['_url', 'url', 'link'],
            'date' => ['date', 'datetime', 'published_at'],
            'datetime' => ['datetime', 'date'],
            'time' => ['time', 'datetime'],
            'number' => ['number'],
            'bool' => ['bool'],
            'color' => ['color'],
            'geo' => ['geo'],
            default => [],
        };
        if (!$allow) return [];
        $out = [];
        if (in_array('_title', $allow, true)) $out['_title'] = 'Titel (' . (Tables::field($table, $table['settings']['title_field'])['label'] ?? 'automatisch') . ')';
        if (in_array('_url', $allow, true) && $table['settings']['route'] !== '') $out['_url'] = 'Adresse dieser Detailseite';
        if (in_array('_url', $allow, true) && Calendar::enabled($table) && Calendar::config($table)['feed']) $out['_ics'] = 'Termin als iCal-Datei (.ics)';
        foreach ($table['fields'] as $f) {
            if (in_array($f['type'], $allow, true) && $f['name'] !== $table['settings']['title_field']) $out[$f['name']] = $f['label'];
        }
        if (in_array('published_at', $allow, true)) $out['published_at'] = 'Veröffentlicht am';
        return $out;
    }

    /** Wert eines Datensatz-Feldes passend zum Typ des Blockfeldes liefern */
    public static function bindValue(array $table, array $e, string $source, string $targetType): mixed
    {
        if ($source === '_url') return self::href($table, $e) ?? '';
        if ($source === '_ics') return empty($e['slug']) ? '' : url('/kalender/' . $table['handle'] . '/' . $e['slug'] . '.ics')
            . (($e['lang'] ?? null) && $e['lang'] !== \Core\Lang::default() ? '?lang=' . $e['lang'] : '');
        $srcType = $source === '_title' ? 'text' : ($source === 'published_at' ? 'date' : (Tables::field($table, $source)['type'] ?? 'text'));
        $raw = $source === '_title' ? self::title($table, $e) : ($e[$source] ?? null);
        return match ($targetType) {
            'media', 'file', 'number', 'bool', 'color', 'geo' => $raw,
            'time' => $srcType === 'datetime' ? substr((string) $raw, 11, 5) : $raw,
            'date' => $source === 'published_at' || $srcType === 'datetime' ? substr((string) $raw, 0, 10) : $raw,
            'datetime' => (string) $raw,
            'link', 'url' => (string) $raw,
            'richtext' => $srcType === 'richtext' ? self::html($table, $e, $source) : ($srcType === 'textarea' ? '<p>' . self::html($table, $e, $source) . '</p>' : '<p>' . e($source === '_title' ? (string) $raw : self::text($table, $e, $source)) . '</p>'),
            'inline' => $srcType === 'richtext' ? strip_tags(self::html($table, $e, $source), '<b><strong><i><em><a><br>') : e($source === '_title' ? (string) $raw : self::text($table, $e, $source)),
            default => $source === '_title' ? (string) $raw : self::text($table, $e, $source),
        };
    }

    /** Platzhalter {{feld}} in einem Text durch Werte des aktuellen Eintrags ersetzen */
    public static function replaceTokens(string $s, bool $html = false): string
    {
        $ctx = app()->entry;
        if (!$ctx || !str_contains($s, '{{')) return $s;
        return (string) preg_replace_callback('~\{\{\s*([a-z_][a-z0-9_]*)\s*\}\}~', function ($m) use ($ctx, $html) {
            [$t, $e] = [$ctx['table'], $ctx['entry']];
            if ($m[1] === 'titel' && !Tables::field($t, 'titel')) $m[1] = '_title';
            return $html ? self::html($t, $e, $m[1]) : ($m[1] === '_title' ? self::title($t, $e) : self::text($t, $e, $m[1]));
        }, $s);
    }
}
