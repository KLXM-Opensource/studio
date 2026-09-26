<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Features;
use Core\FormCrypto;
use Core\Lang;
use Core\Pages;
use Core\Permissions;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;

/**
 * Eingangs-Tabellen („Eingang“, settings.kind = inbox): Datentabellen, deren Einträge nur über das öffentliche
 * Formular entstehen und Ende-zu-Ende verschlüsselt gespeichert werden (Ersatz für die früheren Online-Anfragen).
 *
 * Speicherung in data_{handle} – bewusst OHNE Feldspalten, damit nie Klartext in der Datenbank landet:
 *   id, status (neu|in_bearbeitung|erledigt), ref (Vorgangsnummer, nicht sensibel), lang, assignee (Benutzer-ID),
 *   legacy (ID der übernommenen Alt-Anfrage), payload (libsodium sealed box, Base64), created_at, updated_at.
 * Payload (JSON, versiegelt mit FormCrypto): {"v":2,"table":…,"submitted_at":…,"lang":…,"values":{feld: wert},"labels":{feld: beschriftung}}
 * Alt-Anfragen (Core\Forms bis 2026-09): {"form","label","submitted_at","fields":[{"label","value"}]} – unverändert übernommen.
 *
 * Gesperrt für Eingangs-Tabellen (zentral über Tables::isInbox / Entries-Schutz): Detailseiten, Datenliste/Datensatz-Felder,
 * Kalender, Karte, Sitemap, Spotlight-Inhalte, strukturierte Daten, CalDAV/CardDAV, Feldbindung, Uploads, API-Schreibzugriffe.
 * Protokoll (inbox_log): jedes Entschlüsseln, jede Status-/Zuweisungsänderung und jedes Löschen – nie Inhalte.
 */
final class Inbox
{
    public const STATUSES = ['neu', 'in_bearbeitung', 'erledigt'];
    public const DEFAULTS = ['form' => '', 'title' => '', 'intro' => '', 'retention_days' => 90, 'i18n' => []];
    /** Metadaten-Spalten (alles andere steckt verschlüsselt im payload) */
    public const META = ['id', 'status', 'ref', 'lang', 'assignee', 'legacy', 'created_at', 'updated_at'];

    public static function is(array $t): bool
    {
        return ($t['settings']['kind'] ?? 'content') === 'inbox';
    }

    /** Funktion „requests“ (Anfragen) für diese Website eingeschaltet? */
    public static function available(): bool
    {
        return Features::on('requests', false);
    }

    /** Alle Eingangs-Tabellen */
    public static function tables(): array
    {
        return array_values(array_filter(Tables::all(), [self::class, 'is']));
    }

    /** Eingangs-Tabellen, die der angemeldete Benutzer lesen darf (Recht requests.read, optional je Tabelle) */
    public static function readable(string $perm = 'requests.read'): array
    {
        if (!self::available() || !isset(app()->auth) || !app()->auth->user()) return [];
        return array_values(array_filter(self::tables(), fn($t) => app()->auth->can($perm, $t['handle'])));
    }

    public static function statusLabel(string $s): string
    {
        return match ($s) { 'neu' => __('Neu'), 'in_bearbeitung' => __('In Bearbeitung'), 'erledigt' => __('Erledigt'), default => $s };
    }

    // ================================================================= Einstellungen

    public static function config(array $t): array
    {
        return (array) ($t['settings']['inbox'] ?? []) + self::DEFAULTS;
    }

    /** settings.inbox prüfen (Tables::validate) */
    public static function validateSettings(array $s, ?array $existing): array
    {
        $existing = ($existing ?? []) + self::DEFAULTS;
        if (!$s) return $existing;
        $clean = fn($v, int $max) => mb_substr(trim(strip_tags((string) $v)), 0, $max);
        $form = (string) ($s['form'] ?? $existing['form']);
        $i18n = [];
        foreach ((array) ($s['i18n'] ?? $existing['i18n']) as $lc => $texts) {
            if (!Lang::valid((string) $lc) || $lc === Lang::default()) continue;
            $row = array_filter(['title' => $clean($texts['title'] ?? '', 120), 'intro' => $clean($texts['intro'] ?? '', 600),
                'success' => $clean($texts['success'] ?? '', 500), 'submit' => $clean($texts['submit'] ?? '', 60)], fn($v) => $v !== '');
            if ($row) $i18n[$lc] = $row;
        }
        return [
            'form' => preg_match('~^[a-z0-9_-]{1,40}$~', $form) ? $form : '',
            'title' => $clean($s['title'] ?? $existing['title'], 120),
            'intro' => $clean($s['intro'] ?? $existing['intro'], 600),
            'retention_days' => max(0, min(3650, (int) ($s['retention_days'] ?? $existing['retention_days']))),
            'i18n' => $i18n,
        ];
    }

    /**
     * Text der Tabelle in der Sprache der Seite: title, intro, success, submit.
     * Übersetzung aus settings.inbox.i18n, sonst der Standardtext (über lt(), damit Theme-Texte wie „Online-Rezept“ übersetzt werden).
     */
    public static function text(array $t, string $key, ?string $lang = null): string
    {
        $lang ??= Lang::current();
        $c = self::config($t);
        if ($lang !== Lang::default() && ($v = (string) ($c['i18n'][$lang][$key] ?? '')) !== '') return $v;
        $base = match ($key) {
            'success' => (string) ($t['settings']['form']['success'] ?? ''),
            'submit' => (string) ($t['settings']['form']['submit'] ?? ''),
            default => (string) ($c[$key] ?? ''),
        };
        if ($base === '' && $key === 'title') $base = (string) $t['name'];
        if ($base === '' && $key === 'success') {
            $def = $c['form'] !== '' ? (app()->theme->forms()[$c['form']] ?? null) : null;
            $base = (string) ($def['success'] ?? 'Vielen Dank – Ihre Anfrage ist eingegangen.');
        }
        return $base === '' ? '' : lt($base);
    }

    // ================================================================= Theme-Formulare (theme.php → forms)

    /** Eingangs-Tabelle, die ein Theme-Formular (z. B. „rezept“) bedient */
    public static function forForm(string $key): ?array
    {
        foreach (self::tables() as $t) {
            if (self::config($t)['form'] === $key) return $t;
        }
        return null;
    }

    // ================================================================= Datenbank-Schema

    /** data_{handle} für Eingangs-Tabellen anlegen bzw. angleichen (keine Feldspalten) */
    public static function sync(array $table): void
    {
        $sm = Dbal::conn()->createSchemaManager();
        $name = $table['table'];
        $t = new Table($name);
        $t->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
        $t->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $t->addColumn('status', 'string', ['length' => 20, 'default' => 'neu']);
        $t->addColumn('ref', 'string', ['length' => 20, 'notnull' => false]);
        $t->addColumn('lang', 'string', ['length' => 10, 'notnull' => false]);
        $t->addColumn('assignee', 'integer', ['notnull' => false]);
        $t->addColumn('legacy', 'integer', ['notnull' => false]);
        $t->addColumn('payload', 'text', ['notnull' => false]);
        $t->addColumn('created_at', 'string', ['length' => 25, 'notnull' => false]);
        $t->addColumn('updated_at', 'string', ['length' => 25, 'notnull' => false]);
        $t->addIndex(['status'], $name . '_status');
        $t->addIndex(['ref'], $name . '_ref');
        if (!$sm->tablesExist([$name])) {
            $sm->createTable($t);
            return;
        }
        $diff = $sm->createComparator()->compareTables($sm->introspectTable($name), $t);
        if (!$diff->isEmpty()) $sm->alterTable($diff);
    }

    // ================================================================= Speichern (nur über das öffentliche Formular)

    /**
     * Einsendung versiegeln und speichern. $values: bereinigte Feldwerte (ohne Datenschutz-Checkbox).
     * @return array{id: int, ref: string}
     */
    public static function store(array $t, array $values): array
    {
        $labels = [];
        $clean = [];
        foreach ($t['fields'] as $f) {
            if (!array_key_exists($f['name'], $values)) continue;
            $v = $values[$f['name']];
            if ($v === null || $v === '' || $v === []) continue;      // ausgeblendete/leere Felder nicht speichern
            $clean[$f['name']] = $v;
            $labels[$f['name']] = Tables::label($f, Lang::default());
        }
        $lang = Lang::multi() && Lang::current() !== Lang::default() ? Lang::current() : null;
        $payload = ['v' => 2, 'table' => $t['handle'], 'submitted_at' => date('c'), 'lang' => $lang ?? Lang::default(), 'values' => $clean, 'labels' => $labels];
        $sealed = FormCrypto::seal($payload);                            // wirft ohne öffentlichen Schlüssel – nie Klartext speichern
        $ref = self::newRef($t);
        $id = app()->db->insert($t['table'], ['status' => 'neu', 'ref' => $ref, 'lang' => $lang, 'payload' => $sealed,
            'created_at' => now(), 'updated_at' => now()]);
        return ['id' => $id, 'ref' => $ref];
    }

    /** Vorgangsnummer wie „K7QX-9MZA“ (zufällig, ohne Bezug zu Inhalten) */
    private static function newRef(array $t): string
    {
        $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $s = '';
            for ($i = 0; $i < 8; $i++) $s .= $abc[random_int(0, 31)];
            $ref = substr($s, 0, 4) . '-' . substr($s, 4);
        } while (app()->db->fetchValue("SELECT id FROM {$t['table']} WHERE ref = ?", [$ref]));
        return $ref;
    }

    // ================================================================= Lesen (Metadaten)

    /** $o: status (neu|in_bearbeitung|erledigt|alle), ids, limit, offset, payload (bool) */
    public static function query(array $t, array $o = []): array
    {
        [$where, $params] = self::where($o);
        $cols = implode(', ', array_merge(self::META, !empty($o['payload']) ? ['payload'] : []));
        $sql = "SELECT $cols FROM {$t['table']}" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC';
        if (!empty($o['limit'])) $sql .= ' LIMIT ' . max(1, (int) $o['limit']) . ' OFFSET ' . max(0, (int) ($o['offset'] ?? 0));
        return array_map(fn($r) => ['id' => (int) $r['id'], 'assignee' => $r['assignee'] !== null ? (int) $r['assignee'] : null,
            'legacy' => $r['legacy'] !== null ? (int) $r['legacy'] : null] + $r, app()->db->fetchAll($sql, $params));
    }

    public static function count(array $t, string $status = 'alle'): int
    {
        [$where, $params] = self::where(['status' => $status]);
        return (int) app()->db->fetchValue("SELECT COUNT(*) FROM {$t['table']}" . ($where ? ' WHERE ' . implode(' AND ', $where) : ''), $params);
    }

    private static function where(array $o): array
    {
        $where = [];
        $params = [];
        $status = (string) ($o['status'] ?? 'alle');
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if (array_key_exists('ids', $o)) {
            $ids = array_values(array_filter(array_map('intval', (array) $o['ids'])));
            $where[] = $ids ? 'id IN (' . implode(',', $ids) . ')' : '1 = 0';
        }
        return [$where, $params];
    }

    public static function find(array $t, int $id, bool $payload = false): ?array
    {
        return self::query($t, ['ids' => [$id], 'payload' => $payload])[0] ?? null;
    }

    /** Anzahl „neu“ in allen Eingangs-Tabellen, die der Benutzer lesen darf (Badge in Navigation und Übersicht) */
    public static function newCount(): int
    {
        $n = 0;
        foreach (self::readable() as $t) $n += self::count($t, 'neu');
        return $n;
    }

    /** Metadaten für API/MCP – Inhalte nie, Chiffretext nur auf ausdrücklichen Wunsch */
    public static function meta(array $t, array $row, bool $ciphertext = false): array
    {
        $c = self::config($t);
        return [
            'id' => (int) $row['id'], 'table' => $t['handle'], 'table_name' => $t['name'], 'form' => $c['form'] !== '' ? $c['form'] : $t['handle'],
            'label' => self::text($t, 'title', Lang::default()), 'ref' => $row['ref'], 'status' => $row['status'],
            'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'], 'lang' => Lang::norm($row['lang'] ?? null),
            'assignee' => $row['assignee'], 'legacy' => $row['legacy'] !== null,
        ] + ($ciphertext && isset($row['payload']) ? ['ciphertext' => $row['payload'], 'encryption' => 'libsodium crypto_box_seal (X25519, XSalsa20-Poly1305), Base64'] : []);
    }

    // ================================================================= Entschlüsseln (nur für die aktuelle Antwort)

    /**
     * Payload entschlüsseln und für die Anzeige aufbereiten.
     * @return array{legacy: bool, submitted_at: ?string, lang: ?string, fields: list<array{label: string, value: string}>}|null  null = falscher Schlüssel
     */
    public static function open(array $t, array $row, string $secret): ?array
    {
        $d = FormCrypto::open((string) ($row['payload'] ?? ''), $secret);
        if ($d === null) return null;
        $out = ['legacy' => !isset($d['values']), 'submitted_at' => $d['submitted_at'] ?? null, 'lang' => $d['lang'] ?? null, 'fields' => []];
        if (!isset($d['values'])) {                                                  // Alt-Anfrage: Beschriftung/Wert-Paare
            foreach ((array) ($d['fields'] ?? []) as $f) {
                $out['fields'][] = ['label' => (string) ($f['label'] ?? ''), 'value' => (string) ($f['value'] ?? '')];
            }
            return $out;
        }
        $values = (array) $d['values'];
        $labels = (array) ($d['labels'] ?? []);
        foreach ($t['fields'] as $f) {                                               // Reihenfolge und Beschriftung wie im Schema
            if (!array_key_exists($f['name'], $values)) continue;
            $v = $values[$f['name']];
            $out['fields'][] = $f['type'] === 'group' && is_array($v)
                ? self::groupTable(Tables::label($f, Lang::default()), array_map(fn($sf) => [$sf['name'], Tables::label($sf, Lang::default()), $sf], (array) ($f['fields'] ?? [])), $v)
                : ['label' => Tables::label($f, Lang::default()), 'value' => self::display($f, $v)];   // auch alte Einzelwerte eines heutigen Gruppenfeldes
            unset($values[$f['name']]);
        }
        foreach ($values as $name => $v) {                                           // inzwischen gelöschte Felder: gespeicherte Beschriftung
            if (is_array($v) && $v && is_array(reset($v))) {                        // frühere Gruppe: Spalten = gespeicherte Unterfeld-Namen
                $cols = array_keys(array_merge(...array_values(array_filter($v, 'is_array'))));
                $out['fields'][] = self::groupTable((string) ($labels[$name] ?? $name), array_map(fn($k) => [$k, (string) $k, ['type' => 'text']], $cols), $v);
                continue;
            }
            $out['fields'][] =['label' => (string) ($labels[$name] ?? $name), 'value' => is_array($v) ? implode(', ', array_map('strval', $v)) : (is_bool($v) ? ($v ? __('Ja') : __('Nein')) : (string) $v)];
        }
        return $out;
    }

    /**
     * Gruppe als Tabelle für die Anzeige: ['label', 'value' (Klartext für Kopieren), 'table' => ['cols' => [...], 'rows' => [[...], …]]].
     * $cols: [[name, beschriftung, unterfeld-definition], …]; leere Spalten entfallen.
     */
    private static function groupTable(string $label, array $cols, array $rows): array
    {
        $lang = Lang::default();
        $rows = array_values(array_filter($rows, 'is_array'));
        $cells = array_map(fn($r) => array_map(fn($c) => Entries::groupCell($c[2], $r[$c[0]] ?? null, $lang), $cols), $rows);
        $keep = array_keys(array_filter($cols, fn($c, $i) => array_filter(array_column($cells, $i), fn($s) => $s !== ''), ARRAY_FILTER_USE_BOTH));
        $cols = array_values(array_intersect_key($cols, array_flip($keep)));
        $cells = array_map(fn($r) => array_values(array_intersect_key($r, array_flip($keep))), $cells);
        $text = [];
        foreach ($cells as $i => $r) {
            $parts = [];
            foreach ($r as $j => $s) if ($s !== '') $parts[] = $cols[$j][1] . ': ' . $s;
            $text[] = ($i + 1) . '. ' . implode(' · ', $parts);
        }
        return ['label' => $label, 'value' => implode("\n", $text), 'table' => ['cols' => array_column($cols, 1), 'rows' => $cells]];
    }

    /** Wert als Klartext (Verwaltung, Standardsprache) */
    private static function display(array $f, mixed $v): string
    {
        $lang = Lang::default();
        return match ($f['type']) {
            'bool' => $v ? __('Ja') : __('Nein'),
            'date' => preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', (string) $v, $m) ? "$m[3].$m[2].$m[1]" : (string) $v,
            'datetime' => ($ts = strtotime((string) $v)) ? date('d.m.Y H:i', $ts) : (string) $v,
            'select' => Tables::optionLabel($f, (string) $v, $lang),
            'multiselect' => implode(', ', array_map(fn($k) => Tables::optionLabel($f, (string) $k, $lang), (array) $v)),
            'iban' => \Core\Iban::format((string) $v),
            'number' => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ','),
            default => is_array($v) ? implode(', ', array_map('strval', $v)) : (string) $v,
        };
    }

    // ================================================================= Ändern (mit Protokoll)

    public static function setStatus(array $t, array $ids, string $status, string $via = ''): int
    {
        if (!in_array($status, self::STATUSES, true)) throw new \InvalidArgumentException('Unbekannter Status.');
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return 0;
        app()->db->query("UPDATE {$t['table']} SET status = ?, updated_at = ? WHERE id IN (" . implode(',', $ids) . ')', [$status, now()]);
        self::log($t['handle'], $ids, 'status', trim($status . ' ' . $via));
        return count($ids);
    }

    public static function assign(array $t, int $id, ?int $userId): void
    {
        app()->db->update($t['table'], ['assignee' => $userId, 'updated_at' => now()], 'id = :id', ['id' => $id]);
        self::log($t['handle'], [$id], 'assign', $userId ? 'user:' . $userId : '–');
    }

    public static function delete(array $t, array $ids, string $via = ''): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return 0;
        app()->db->query("DELETE FROM {$t['table']} WHERE id IN (" . implode(',', $ids) . ')');
        self::log($t['handle'], $ids, 'delete', $via);
        return count($ids);
    }

    // ================================================================= Protokoll (inbox_log) – nie Inhalte

    public static function log(string $handle, array $ids, string $action, string $detail = ''): void
    {
        $ip = app()->request?->ip() ?? (PHP_SAPI === 'cli' ? 'cli' : '');
        $user = isset(app()->auth) ? app()->auth->user() : null;
        app()->db->insert('inbox_log', [
            'table_handle' => $handle, 'entry_ids' => json_encode(array_values(array_map('intval', $ids))),
            'user_id' => $user['id'] ?? null, 'action' => $action, 'detail' => mb_substr($detail, 0, 190),
            'ip_hash' => $ip !== '' ? substr(hash_hmac('sha256', $ip, app()->key()), 0, 16) : null, 'created_at' => now(),
        ]);
    }

    /** Letzte Ereignisse; $o: table, user (ID), limit */
    public static function logQuery(array $o = []): array
    {
        $where = [];
        $params = [];
        if (!empty($o['tables'])) {
            $where[] = 'l.table_handle IN (' . implode(',', array_fill(0, count($o['tables']), '?')) . ')';
            array_push($params, ...array_values($o['tables']));
        }
        if (!empty($o['user'])) {
            $where[] = 'l.user_id = ?';
            $params[] = (int) $o['user'];
        }
        return app()->db->fetchAll('SELECT l.*, u.name AS user_name, u.email AS user_email FROM inbox_log l LEFT JOIN users u ON u.id = l.user_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY l.id DESC LIMIT ' . max(1, min(1000, (int) ($o['limit'] ?? 200))), $params);
    }

    // ================================================================= Aufbewahrung

    /**
     * Erledigte Anfragen nach N Tagen löschen (je Tabelle, 0 = nie) und alte Protokolleinträge entfernen.
     * @return array<string, int> gelöschte Anfragen je Tabelle
     */
    public static function purge(): array
    {
        $out = [];
        foreach (self::tables() as $t) {
            $days = (int) self::config($t)['retention_days'];
            if ($days <= 0) continue;
            $cut = date('Y-m-d H:i:s', time() - $days * 86400);
            $n = (int) app()->db->fetchValue("SELECT COUNT(*) FROM {$t['table']} WHERE status = 'erledigt' AND COALESCE(updated_at, created_at) < ?", [$cut]);
            if ($n > 0) {
                app()->db->query("DELETE FROM {$t['table']} WHERE status = 'erledigt' AND COALESCE(updated_at, created_at) < ?", [$cut]);
                self::log($t['handle'], [], 'purge', $n . ' ' . ($n === 1 ? 'Anfrage' : 'Anfragen') . " (> $days Tage erledigt)");
            }
            $out[$t['handle']] = $n;
        }
        $months = max(1, (int) app()->settings->get('sys.inbox_log_months', 12));
        app()->db->query('DELETE FROM inbox_log WHERE created_at < ?', [date('Y-m-d H:i:s', strtotime("-$months months"))]);
        app()->settings->set('sys.inbox_purged_at', now());
        return $out;
    }

    /** Gelegentlich bei Verwaltungsaufrufen (≈ 1 %, höchstens alle 6 Stunden) */
    public static function maybePurge(): void
    {
        if (random_int(1, 100) !== 1) return;
        $last = (string) app()->settings->get('sys.inbox_purged_at', '');
        if ($last !== '' && strtotime($last) > time() - 6 * 3600) return;
        try {
            self::purge();
        } catch (\Throwable $e) {
            error_log('[inbox] purge: ' . $e->getMessage());
        }
    }

    // ================================================================= Umstellung von den früheren Online-Anfragen

    /** Muss die Umstellung (noch) laufen? – billige Prüfung beim Start */
    public static function needsMigration(): bool
    {
        $s = app()->settings;
        $done = $s->get('sys.inbox_migrated');
        return !is_array($done) || array_diff(array_keys(app()->theme->forms()), $done) || !$s->get('sys.inbox_legacy_moved')
            || array_diff(self::fieldUpdateIds(), (array) ($s->get('sys.inbox_field_updates') ?: []));
    }

    /** IDs aller Feld-Änderungen der Theme-Formulare (theme.php → forms → {key} → field_updates → id) */
    private static function fieldUpdateIds(): array
    {
        $ids = [];
        foreach (app()->theme->forms() as $def) {
            foreach ((array) ($def['field_updates'] ?? []) as $u) if (!empty($u['id'])) $ids[] = (string) $u['id'];
        }
        return $ids;
    }

    /**
     * Feld-Änderungen der Theme-Formulare einmalig auf die Eingangs-Tabelle anwenden (idempotent, Merker sys.inbox_field_updates):
     *   ['id' => 'eindeutig', 'replace' => 'altes_feld', 'field' => [neue Felddefinition wie in Tables::validate]]
     * Ersetzt das alte Feld an seiner Stelle – nur wenn es noch existiert und das neue Feld noch fehlt (von der Redaktion
     * geänderte Tabellen bleiben sonst unangetastet). Alte Anfragen behalten ihren Wert im verschlüsselten payload und werden
     * unter der gespeicherten Beschriftung angezeigt.
     */
    private static function applyFieldUpdates(array &$log): void
    {
        $s = app()->settings;
        $done = (array) ($s->get('sys.inbox_field_updates') ?: []);
        foreach (app()->theme->forms() as $key => $def) {
            foreach ((array) ($def['field_updates'] ?? []) as $u) {
                $id = (string) ($u['id'] ?? '');
                if ($id === '' || in_array($id, $done, true)) continue;
                Tables::flush();
                $t = self::forForm((string) $key);
                if (!$t) continue;                                               // Tabelle (noch) nicht vorhanden – später erneut
                $new = (array) ($u['field'] ?? []);
                $newName = Tables::normName((string) (($new['name'] ?? '') ?: ($new['label'] ?? '')));
                $names = array_column($t['fields'], 'name');
                $pos = array_search((string) ($u['replace'] ?? ''), $names, true);
                if (in_array($newName, $names, true)) {
                    $log[] = "Formular „{$key}“: Feld „{$newName}“ vorhanden – Änderung {$id} übersprungen.";
                } elseif ($pos === false) {
                    $log[] = "Formular „{$key}“: Feld „" . ($u['replace'] ?? '') . "“ fehlt – Änderung {$id} übersprungen (Tabelle wurde angepasst).";
                } else {
                    $fields = $t['fields'];
                    $fields[$pos] = $new;
                    $in = ['name' => $t['name'], 'singular' => $t['singular'], 'icon' => $t['icon'], 'description' => $t['description'],
                        'fields' => array_map(fn($f) => isset($f['options']) && is_array($f['options']) && ($f['type'] ?? '') !== 'group'
                            ? ['options' => implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys($f['options']), $f['options']))] + $f : $f, $fields),
                        'settings' => ['kind' => 'inbox', 'form' => $t['settings']['form'], 'inbox' => $t['settings']['inbox']]];
                    [$clean, $errors] = Tables::validate($in, $t);
                    if ($errors) {
                        $log[] = "Formular „{$key}“: FEHLER bei Änderung {$id} – " . implode(' ', $errors);
                        continue;                                                // nicht als erledigt merken
                    }
                    Tables::update($t, $clean);
                    $log[] = "Formular „{$key}“: Änderung {$id} angewendet („" . ($u['replace'] ?? '') . "“ → „{$newName}“).";
                }
                $done[] = $id;
            }
        }
        $s->set('sys.inbox_field_updates', array_values(array_unique($done)));
        Tables::flush();
    }

    /**
     * Idempotent: legt für jedes Theme-Formular (theme.php → forms) eine Eingangs-Tabelle an (Felder aus dem Einstellungs-Repeater),
     * übernimmt Alt-Anfragen aus `requests` (Payload unverändert, gleicher Schlüssel) und ergänzt Rollen mit Tabellenauswahl.
     * @return list<string> Protokoll
     */
    public static function migrate(): array
    {
        $s = app()->settings;
        $log = [];
        $done = (array) ($s->get('sys.inbox_migrated') ?: []);
        $created = [];
        foreach (app()->theme->forms() as $key => $def) {
            if (in_array($key, $done, true)) continue;
            if ($t = self::forForm($key)) {
                $log[] = "Formular „{$key}“: Tabelle „{$t['handle']}“ vorhanden.";
            } else {
                [$handle, $err] = self::createForForm((string) $key, (array) $def);
                $log[] = $handle ? "Formular „{$key}“: Eingangs-Tabelle „{$handle}“ angelegt." : "Formular „{$key}“: FEHLER – " . $err;
                if (!$handle) continue;
                $created[] = $handle;
            }
            $done[] = $key;
        }
        $s->set('sys.inbox_migrated', array_values(array_unique($done)));
        self::applyFieldUpdates($log);

        if (!$s->get('sys.inbox_legacy_moved')) {
            $n = self::moveLegacy($log, $created);
            $s->set('sys.inbox_legacy_moved', now());
            $log[] = "Alt-Anfragen übernommen: $n.";
        }
        // Rollen, die auf bestimmte Tabellen beschränkt sind und Anfragen lesen dürfen: neue Eingangs-Tabellen ergänzen
        if ($created) {
            foreach (Permissions::roles() as $role) {
                if (!is_array($role['tables']) || !array_intersect(['requests.read', 'requests.manage'], $role['permissions'])) continue;
                app()->db->update('roles', ['tables_json' => json_encode(array_values(array_unique(array_merge($role['tables'], $created))))],
                    'rkey = :k', ['k' => $role['key']]);
                $log[] = "Rolle „{$role['name']}“: Tabellen ergänzt.";
            }
        }
        Tables::flush();
        return $log;
    }

    /** Eingangs-Tabelle für ein Theme-Formular anlegen. @return array{0: ?string, 1: ?string} [handle, Fehler] */
    private static function createForForm(string $key, array $def): array
    {
        $tbl = (array) ($def['table'] ?? []);
        $label = (string) ($def['label'] ?? $key);
        $fields = is_array($def['fields'] ?? null) ? $def['fields'] : self::fieldsFromRepeater((string) ($def['fields'] ?? ''));
        if (!$fields) {
            $fields = [['label' => 'Name', 'name' => 'name', 'type' => 'text', 'required' => 1], ['label' => 'Telefon', 'name' => 'telefon', 'type' => 'tel', 'required' => 1],
                ['label' => 'Nachricht', 'name' => 'nachricht', 'type' => 'textarea']];
        }
        $base = Tables::normName((string) ($tbl['handle'] ?? '') ?: $key . '_anfragen');
        $in = [
            'name' => (string) ($tbl['name'] ?? $label), 'singular' => (string) ($tbl['singular'] ?? 'Anfrage'), 'icon' => (string) ($tbl['icon'] ?? 'envelope-simple'),
            'description' => 'Eingang für das Formular „' . $label . '“ (' . url('/anfrage/' . $key) . ').',
            'fields' => $fields,
            'settings' => ['kind' => 'inbox', 'form' => ['enabled' => 1, 'notify' => (string) ($tbl['notify'] ?? '')],
                'inbox' => ['form' => $key, 'title' => $label, 'intro' => (string) ($def['intro'] ?? ''), 'retention_days' => (int) ($tbl['retention_days'] ?? 90)]],
        ];
        for ($i = 1; $i < 20; $i++) {
            $in['handle'] = $i === 1 ? $base : $base . '_' . $i;
            [$clean, $errors] = Tables::validate($in);
            if (isset($errors['handle'])) continue;
            if ($errors) return [null, implode(' ', $errors)];
            Tables::create($clean);
            return [$clean['handle'], null];
        }
        return [null, 'Kein freier Kurzname.'];
    }

    /** Felder aus dem Repeater der Theme-Einstellungen (label, name, typ, breite, pflicht, optionen) – Namen bleiben stabil */
    public static function fieldsFromRepeater(string $setting): array
    {
        if ($setting === '') return [];
        $st = app()->settings;
        $map = function (array $r): ?array {
            $name = str_replace('-', '_', Pages::slugify((string) (($r['name'] ?? '') ?: ($r['label'] ?? ''))));
            if ($name === '' || $name === 'seite' || $name === 'datenschutz') return null;
            $type = match ($r['typ'] ?? 'text') {
                'date' => 'date', 'tel' => 'tel', 'email' => 'email', 'select' => 'select', 'checkbox' => 'bool', 'textarea' => 'textarea', default => 'text',
            };
            $opts = array_values(array_filter(array_map('trim', preg_split('~[\n,]~', (string) ($r['optionen'] ?? '')))));
            return ['name' => $name, 'label' => trim((string) ($r['label'] ?? '')) ?: $name, 'type' => $type, 'required' => !empty($r['pflicht']) ? 1 : 0,
                'in_list' => 0, 'width' => ($r['breite'] ?? 'half') === 'full' ? '' : 'half', 'options' => implode("\n", $opts), '_opts' => $opts];
        };
        $out = [];
        foreach ((array) $st->get($setting, []) as $r) {
            if (is_array($r) && ($f = $map($r))) $out[$f['name']] = $f;
        }
        // Übersetzte Beschriftungen („formfelder_rezept@en“): gleiche Position bzw. gleicher Name
        foreach (array_keys(Lang::all()) as $lc) {
            if ($lc === Lang::default()) continue;
            $rows = array_values(array_filter((array) $st->get($setting . '@' . $lc, []), 'is_array'));
            $keys = array_keys($out);
            foreach ($rows as $i => $r) {
                $f = $map($r);
                $name = $f && isset($out[$f['name']]) ? $f['name'] : ($keys[$i] ?? null);
                if (!$name || !$f) continue;
                if ($f['label'] !== '' && $f['label'] !== $out[$name]['label']) $out[$name]['labels'][$lc] = $f['label'];
                if ($out[$name]['type'] === 'select' && $f['_opts']) {
                    $lines = [];
                    foreach ($out[$name]['_opts'] as $j => $o) {
                        if (isset($f['_opts'][$j])) $lines[] = Tables::normName($o) . '=' . $f['_opts'][$j];
                    }
                    $out[$name]['options_i18n'][$lc] = implode("\n", $lines);
                }
            }
        }
        return array_values(array_map(fn($f) => array_diff_key($f, ['_opts' => 1]), $out));
    }

    /** Zeilen der alten Tabelle `requests` übernehmen (Payload bleibt, wie er ist – gleicher Schlüssel) */
    private static function moveLegacy(array &$log, array $created): int
    {
        $db = app()->db;
        try {
            $rows = $db->fetchAll('SELECT * FROM requests ORDER BY id');
        } catch (\Throwable) {
            return 0;                                                                // keine Alt-Tabelle (Neuinstallation)
        }
        $n = 0;
        foreach ($rows as $r) {
            $t = self::forForm((string) $r['form']) ?? self::archive($log);
            if ($db->fetchValue("SELECT id FROM {$t['table']} WHERE legacy = ?", [(int) $r['id']])) continue;
            $status = in_array($r['status'], self::STATUSES, true) ? $r['status'] : 'neu';
            $db->insert($t['table'], ['status' => $status, 'ref' => self::newRef($t), 'lang' => null, 'legacy' => (int) $r['id'],
                'payload' => $r['payload'], 'created_at' => $r['created_at'], 'updated_at' => $r['created_at']]);
            $n++;
        }
        return $n;
    }

    /** Sammel-Tabelle für Alt-Anfragen, deren Formular das Theme nicht mehr kennt */
    private static function archive(array &$log): array
    {
        if ($t = Tables::find('anfragen_archiv')) return $t;
        [$def] = Tables::validate(['name' => 'Anfragen (Archiv)', 'singular' => 'Anfrage', 'icon' => 'envelope-simple', 'handle' => 'anfragen_archiv',
            'description' => 'Übernommene Online-Anfragen ohne passendes Formular im Kit.',
            'fields' => [['label' => 'Nachricht', 'name' => 'nachricht', 'type' => 'textarea']],
            'settings' => ['kind' => 'inbox', 'form' => ['enabled' => 0], 'inbox' => ['title' => 'Anfragen (Archiv)']]]);
        Tables::create($def);
        $log[] = 'Sammel-Tabelle „anfragen_archiv“ angelegt.';
        return Tables::find('anfragen_archiv');
    }
}
