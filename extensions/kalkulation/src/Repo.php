<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Kalkulation;

use Core\Database;
use Core\Settings;

/**
 * Tabellen der Erweiterung (Datenbank der Website – jede Website hat ihre eigenen Kalkulationen):
 *   kalk_store     Dokumente „settings“ (Stundensätze, Aufschläge, Texte) und „catalog“ (Leistungskatalog)
 *   kalk_calcs     Kalkulationen: Nummer, Status, Datum als Spalten; Inhalt (Kunde, Positionen, Grundlage, Texte) als JSON
 *   kalk_versions  Stände einer Kalkulation (Sicherung mit Notiz, automatisch höchstens alle 15 Minuten und bei Statuswechsel)
 *   kalk_log       Wer hat wann was getan (anlegen, speichern, duplizieren, löschen, Export, Einstellungen, Katalog)
 * Optional verschlüsselt (Einstellung „encrypt“): JSON-Inhalte mit libsodium secretbox über Core\Settings::encrypt()
 * (Schlüssel aus app_key der Konfiguration). Nummer, Status und Datum bleiben lesbar (Liste, Sortierung).
 */
final class Repo
{
    public const MAX_POSITIONS = 400;
    private static ?array $settings = null;

    public static function migrate(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $long = $my ? 'MEDIUMTEXT' : 'TEXT';
        $ine = $my ? '' : 'IF NOT EXISTS ';
        $db->query("CREATE TABLE IF NOT EXISTS kalk_store (skey VARCHAR(40) NOT NULL PRIMARY KEY, data $long NULL, updated_by INT NULL, updated_at VARCHAR(25) NULL)");
        $db->query("CREATE TABLE IF NOT EXISTS kalk_calcs (id $pk, number VARCHAR(60) NOT NULL DEFAULT '', status VARCHAR(12) NOT NULL DEFAULT 'draft',
            calc_date VARCHAR(10) NULL, data $long NULL, rev INT NOT NULL DEFAULT 1, created_by INT NULL, created_at VARCHAR(25) NULL,
            updated_by INT NULL, updated_at VARCHAR(25) NULL, snapshot_at VARCHAR(25) NULL)");
        $db->query("CREATE INDEX {$ine}kalk_calcs_status ON kalk_calcs (status, updated_at)");
        $db->query("CREATE TABLE IF NOT EXISTS kalk_versions (id $pk, calc_id INT NOT NULL, rev INT NOT NULL, status VARCHAR(12) NULL, data $long NULL,
            note VARCHAR(191) NULL, user_id INT NULL, created_at VARCHAR(25) NULL)");
        $db->query("CREATE INDEX {$ine}kalk_versions_calc ON kalk_versions (calc_id, id)");
        $db->query("CREATE TABLE IF NOT EXISTS kalk_log (id $pk, at VARCHAR(25) NOT NULL, user_id INT NULL, action VARCHAR(20) NOT NULL, calc_id INT NULL, info VARCHAR(191) NULL)");
        $db->query("CREATE INDEX {$ine}kalk_log_at ON kalk_log (id)");
        // Vorgaben (unverschlüsselt – Verschlüsselung ist anfangs aus): Einstellungen ohne Beträge, Beispiel-Katalog
        foreach (['settings' => Seed::settings(), 'catalog' => Seed::catalog()] as $k => $doc) {
            if (!$db->fetchValue('SELECT COUNT(*) FROM kalk_store WHERE skey = ?', [$k])) {
                $db->insert('kalk_store', ['skey' => $k, 'data' => json_encode($doc, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            }
        }
    }

    public static function ready(): bool
    {
        return (int) app()->settings->get('ext.kalkulation.schema', 0) >= 1;
    }

    // ------------------------------------------------------------------ Ver-/Entschlüsselung

    private static function encrypting(): bool
    {
        return !empty(self::settings()['encrypt']);
    }

    private static function pack(array $doc, ?bool $encrypt = null): string
    {
        $json = (string) json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return ($encrypt ?? self::encrypting()) ? Settings::encrypt($json) : $json;
    }

    private static function unpack(?string $raw): array
    {
        $raw = (string) $raw;
        if ($raw === '') return [];
        if (str_starts_with($raw, 'enc1:')) $raw = Settings::decrypt($raw);
        return is_array($d = json_decode($raw, true)) ? $d : [];
    }

    /** Ist ein Wert verschlüsselt, aber nicht mehr lesbar (anderer app_key)? */
    public static function unreadable(?string $raw): bool
    {
        return str_starts_with((string) $raw, 'enc1:') && Settings::decrypt((string) $raw) === '';
    }

    /** Verschlüsselung umstellen: alle Inhalte neu schreiben (im Zweifel nichts ändern) */
    public static function setEncryption(bool $on, int $userId): void
    {
        if ($on) Settings::encrypt('probe');   // wirft ohne app_key
        $db = app()->db;
        $db->transaction(function () use ($db, $on): void {
            foreach ($db->fetchAll('SELECT skey, data FROM kalk_store') as $r) {
                $doc = self::unpack($r['data']);
                if ($r['skey'] === 'settings') $doc['encrypt'] = $on;
                $db->update('kalk_store', ['data' => self::pack($doc, $on)], 'skey = :wk', ['wk' => $r['skey']]);
            }
            foreach (['kalk_calcs', 'kalk_versions'] as $t) {
                foreach ($db->fetchAll("SELECT id, data FROM $t") as $r) {
                    if (self::unreadable($r['data'])) throw new \RuntimeException(__('Ein Eintrag ist mit einem anderen Schlüssel verschlüsselt – nichts geändert.'));
                    $db->update($t, ['data' => self::pack(self::unpack($r['data']), $on)], 'id = :wid', ['wid' => (int) $r['id']]);
                }
            }
        });
        self::$settings = null;
        self::log($on ? 'encrypt-on' : 'encrypt-off', null, '', $userId);
    }

    // ------------------------------------------------------------------ Einstellungen und Katalog

    public static function settings(): array
    {
        if (self::$settings !== null) return self::$settings;
        $raw = app()->db->fetchValue('SELECT data FROM kalk_store WHERE skey = ?', ['settings']);
        return self::$settings = self::unpack(is_string($raw) ? $raw : null) + Seed::settings();
    }

    public static function saveSettings(array $s, int $userId): void
    {
        $s['encrypt'] = !empty(self::settings()['encrypt']);   // Umstellen nur über setEncryption()
        self::putDoc('settings', $s, $userId);
        self::$settings = null;
        self::log('settings', null, '', $userId);
    }

    public static function catalog(): array
    {
        $raw = app()->db->fetchValue('SELECT data FROM kalk_store WHERE skey = ?', ['catalog']);
        $c = self::unpack(is_string($raw) ? $raw : null);
        return ['services' => array_values((array) ($c['services'] ?? [])), 'next' => max(1, (int) ($c['next'] ?? 1))];
    }

    public static function saveCatalog(array $c, int $userId, string $info = ''): void
    {
        self::putDoc('catalog', $c, $userId);
        self::log('catalog', null, $info, $userId);
    }

    private static function putDoc(string $key, array $doc, int $userId): void
    {
        $db = app()->db;
        $row = ['data' => self::pack($doc), 'updated_by' => $userId, 'updated_at' => now()];
        if ($db->fetchValue('SELECT COUNT(*) FROM kalk_store WHERE skey = ?', [$key])) $db->update('kalk_store', $row, 'skey = :wk', ['wk' => $key]);
        else $db->insert('kalk_store', $row + ['skey' => $key]);
    }

    // ------------------------------------------------------------------ Kalkulationen

    private static function hydrate(array $r): array
    {
        $locked = self::unreadable($r['data']);
        $d = $locked ? [] : self::unpack($r['data']);
        return ['id' => (int) $r['id'], 'number' => (string) $r['number'], 'status' => (string) $r['status'], 'calc_date' => (string) ($r['calc_date'] ?? ''),
            'rev' => (int) $r['rev'], 'created_by' => $r['created_by'] !== null ? (int) $r['created_by'] : null, 'created_at' => (string) ($r['created_at'] ?? ''),
            'updated_by' => $r['updated_by'] !== null ? (int) $r['updated_by'] : null, 'updated_at' => (string) ($r['updated_at'] ?? ''),
            'snapshot_at' => (string) ($r['snapshot_at'] ?? ''), 'locked' => $locked] + self::normalize($d);
    }

    /** @return list<array> alle Kalkulationen (neueste zuerst) */
    public static function all(): array
    {
        return array_map([self::class, 'hydrate'], app()->db->fetchAll('SELECT * FROM kalk_calcs ORDER BY updated_at DESC, id DESC'));
    }

    public static function find(int $id): ?array
    {
        $r = app()->db->fetch('SELECT * FROM kalk_calcs WHERE id = ?', [$id]);
        return $r ? self::hydrate($r) : null;
    }

    /** Neue Kalkulation mit Grundlage aus den Einstellungen und Standardtexten */
    public static function create(int $userId, array $data = []): int
    {
        $s = self::settings();
        $valid = (new \DateTimeImmutable('today'))->modify('+' . max(0, (int) $s['validity_days']) . ' days')->format('Y-m-d');
        $doc = self::normalize($data + ['params' => self::paramsFromSettings($s), 'valid_until' => $valid, 'intro' => (string) $s['text_intro'],
            'payment' => (string) $s['text_payment'], 'validity' => (string) $s['text_validity'], 'term' => (string) $s['text_term'],
            'closing' => (string) $s['text_closing']]);
        $id = app()->db->insert('kalk_calcs', ['number' => self::nextNumber(), 'status' => 'draft', 'calc_date' => date('Y-m-d'), 'data' => self::pack($doc),
            'rev' => 1, 'created_by' => $userId, 'created_at' => now(), 'updated_by' => $userId, 'updated_at' => now()]);
        self::log('create', $id, (string) $doc['title'], $userId);
        return $id;
    }

    /**
     * Speichern mit Prüfung des Stands ($baseRev): hat inzwischen jemand anderes gespeichert, wirft Conflict.
     * Stand-Sicherung: bei Statuswechsel oder wenn die letzte Sicherung älter als 15 Minuten ist. @return array neue Zeile
     */
    public static function save(int $id, array $in, int $baseRev, int $userId, bool $force = false): array
    {
        $cur = self::find($id) ?? throw new \InvalidArgumentException(__('Kalkulation nicht gefunden.'));
        if ($cur['locked']) throw new \RuntimeException(__('Diese Kalkulation ist mit einem anderen Schlüssel verschlüsselt und kann nicht gespeichert werden.'));
        if (!$force && $baseRev !== $cur['rev']) throw new Conflict($cur);
        $status = array_key_exists((string) ($in['status'] ?? ''), Kalkulation::statuses()) ? (string) $in['status'] : $cur['status'];
        $number = mb_substr(trim((string) ($in['number'] ?? $cur['number'])), 0, 60);
        $date = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) ($in['calc_date'] ?? '')) ? (string) $in['calc_date'] : $cur['calc_date'];
        $doc = self::normalize($in);
        $rev = $cur['rev'] + 1;
        $snapshot = $status !== $cur['status'] || $cur['snapshot_at'] === '' || strtotime($cur['snapshot_at']) < time() - 900;
        app()->db->transaction(function () use ($id, $cur, $status, $number, $date, $doc, $rev, $userId, $snapshot): void {
            if ($snapshot) {
                // Sicherung des bisherigen Stands (so lässt sich zu „vor dieser Sitzung“ zurück)
                self::putVersion($id, $cur, $cur['status'] !== $status ? __('vor Statuswechsel') : __('automatisch'), $userId);
            }
            app()->db->update('kalk_calcs', ['number' => $number, 'status' => $status, 'calc_date' => $date, 'data' => self::pack($doc), 'rev' => $rev,
                'updated_by' => $userId, 'updated_at' => now()] + ($snapshot ? ['snapshot_at' => now()] : []), 'id = :wid', ['wid' => $id]);
        });
        if ($status !== $cur['status']) self::log('status', $id, $cur['status'] . ' → ' . $status, $userId);
        else if ($snapshot) self::log('save', $id, (string) $doc['title'], $userId);
        return self::find($id);
    }

    private static function putVersion(int $id, array $calc, string $note, int $userId): void
    {
        $doc = self::normalize($calc);
        $doc['_meta'] = ['number' => $calc['number'], 'calc_date' => $calc['calc_date']];
        app()->db->insert('kalk_versions', ['calc_id' => $id, 'rev' => $calc['rev'], 'status' => $calc['status'], 'data' => self::pack($doc),
            'note' => mb_substr($note, 0, 191), 'user_id' => $userId, 'created_at' => now()]);
        // höchstens 60 Stände je Kalkulation
        $keep = app()->db->fetchValue('SELECT id FROM kalk_versions WHERE calc_id = ? ORDER BY id DESC LIMIT 1 OFFSET 59', [$id]);
        if ($keep) app()->db->query('DELETE FROM kalk_versions WHERE calc_id = ? AND id < ?', [$id, (int) $keep]);
    }

    /** Aktuellen Stand ausdrücklich sichern (mit Notiz) */
    public static function snapshot(int $id, string $note, int $userId): void
    {
        $cur = self::find($id) ?? throw new \InvalidArgumentException(__('Kalkulation nicht gefunden.'));
        self::putVersion($id, $cur, $note !== '' ? $note : __('Stand gesichert'), $userId);
        app()->db->update('kalk_calcs', ['snapshot_at' => now()], 'id = :wid', ['wid' => $id]);
        self::log('version', $id, $note, $userId);
    }

    public static function versions(int $id): array
    {
        return app()->db->fetchAll('SELECT id, rev, status, note, user_id, created_at FROM kalk_versions WHERE calc_id = ? ORDER BY id DESC LIMIT 60', [$id]);
    }

    /** Stand wiederherstellen: aktueller Stand wird vorher gesichert */
    public static function restore(int $id, int $vid, int $userId): void
    {
        $cur = self::find($id) ?? throw new \InvalidArgumentException(__('Kalkulation nicht gefunden.'));
        $v = app()->db->fetch('SELECT * FROM kalk_versions WHERE id = ? AND calc_id = ?', [$vid, $id]) ?? throw new \InvalidArgumentException(__('Stand nicht gefunden.'));
        if (self::unreadable($v['data'])) throw new \RuntimeException(__('Dieser Stand ist mit einem anderen Schlüssel verschlüsselt.'));
        $doc = self::unpack($v['data']);
        $meta = (array) ($doc['_meta'] ?? []);
        app()->db->transaction(function () use ($id, $cur, $doc, $meta, $v, $userId): void {
            self::putVersion($id, $cur, __('vor Wiederherstellung'), $userId);
            app()->db->update('kalk_calcs', ['data' => self::pack(self::normalize($doc)), 'status' => (string) ($v['status'] ?: $cur['status']),
                'number' => (string) ($meta['number'] ?? $cur['number']), 'calc_date' => (string) ($meta['calc_date'] ?? $cur['calc_date']),
                'rev' => $cur['rev'] + 1, 'updated_by' => $userId, 'updated_at' => now(), 'snapshot_at' => now()], 'id = :wid', ['wid' => $id]);
        });
        self::log('restore', $id, 'Stand #' . $vid, $userId);
    }

    public static function duplicate(int $id, int $userId): int
    {
        $cur = self::find($id) ?? throw new \InvalidArgumentException(__('Kalkulation nicht gefunden.'));
        $doc = self::normalize($cur);
        $doc['title'] = __('Kopie von {title}', ['title' => $doc['title'] !== '' ? $doc['title'] : $cur['number']]);
        foreach ($doc['positions'] as &$p) $p['id'] = self::pid();
        unset($p);
        $new = app()->db->insert('kalk_calcs', ['number' => self::nextNumber(), 'status' => 'draft', 'calc_date' => date('Y-m-d'), 'data' => self::pack($doc),
            'rev' => 1, 'created_by' => $userId, 'created_at' => now(), 'updated_by' => $userId, 'updated_at' => now()]);
        self::log('duplicate', $new, __('aus {number}', ['number' => $cur['number']]), $userId);
        return $new;
    }

    public static function delete(int $id, int $userId): void
    {
        $cur = self::find($id);
        app()->db->transaction(function () use ($id): void {
            app()->db->query('DELETE FROM kalk_versions WHERE calc_id = ?', [$id]);
            app()->db->query('DELETE FROM kalk_calcs WHERE id = ?', [$id]);
        });
        self::log('delete', $id, $cur ? $cur['number'] . ' ' . $cur['title'] : '', $userId);
    }

    /** Nächste Angebotsnummer: Präfix ({Y} = Jahr) + laufende Nummer (3-stellig) */
    public static function nextNumber(): string
    {
        $prefix = str_replace(['{Y}', '{y}'], [date('Y'), date('y')], (string) self::settings()['number_prefix']);
        $max = 0;
        foreach (app()->db->fetchAll('SELECT number FROM kalk_calcs WHERE number LIKE ?', [str_replace(['%', '_'], ['\\%', '\\_'], $prefix) . '%']) as $r) {
            if (preg_match('~(\d+)$~', substr((string) $r['number'], strlen($prefix)), $m)) $max = max($max, (int) $m[1]);
        }
        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------ Protokoll

    public static function log(string $action, ?int $calcId, string $info, int $userId): void
    {
        app()->db->insert('kalk_log', ['at' => now(), 'user_id' => $userId ?: null, 'action' => $action, 'calc_id' => $calcId, 'info' => mb_substr($info, 0, 191)]);
        if (random_int(1, 50) === 1) {
            $keep = app()->db->fetchValue('SELECT id FROM kalk_log ORDER BY id DESC LIMIT 1 OFFSET 1999');
            if ($keep) app()->db->query('DELETE FROM kalk_log WHERE id < ?', [(int) $keep]);
        }
    }

    public static function logs(int $limit = 50, ?int $calcId = null): array
    {
        return $calcId !== null
            ? app()->db->fetchAll('SELECT * FROM kalk_log WHERE calc_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$calcId])
            : app()->db->fetchAll('SELECT * FROM kalk_log ORDER BY id DESC LIMIT ' . max(1, $limit));
    }

    // ------------------------------------------------------------------ Datenform

    /** Grundlage einer neuen Kalkulation aus den Einstellungen (Sätze, Aufschläge, USt, Rundung) */
    public static function paramsFromSettings(array $s): array
    {
        $rates = [];
        foreach ((array) $s['rates'] as $r) $rates[(string) $r['key']] = ['label' => (string) $r['label'], 'rate' => $r['rate'] === null ? null : (float) $r['rate']];
        return ['rates' => $rates, 'markup' => $s['markup'], 'buffer' => $s['buffer'], 'vat' => $s['vat'], 'currency' => (string) $s['currency'],
            'round_step' => (float) $s['round_step'], 'round_mode' => (string) $s['round_mode'], 'cost_rate' => $s['cost_rate'],
            'rate_once' => (string) $s['rate_once'], 'rate_monthly' => (string) $s['rate_monthly']];
    }

    public static function pid(): string
    {
        return 'p' . bin2hex(random_bytes(5));
    }

    /** Eingaben (Editor, Stände, Kopien) in die gespeicherte Form bringen: Längen begrenzen, Zahlen lesen, Unbekanntes verwerfen */
    public static function normalize(array $in): array
    {
        $str = fn(string $k, int $max = 200) => mb_substr(trim(str_replace("\r", '', (string) ($in[$k] ?? ''))), 0, $max);
        $num = fn(mixed $v) => $v === null || $v === '' ? null : Num::parse($v);
        $p = (array) ($in['params'] ?? []);
        $rates = [];
        foreach ((array) ($p['rates'] ?? []) as $k => $r) {
            if (!preg_match('~^[a-z0-9_-]{1,30}$~', (string) $k)) continue;
            $rates[(string) $k] = ['label' => mb_substr(trim((string) ($r['label'] ?? $k)), 0, 80), 'rate' => $num($r['rate'] ?? null)];
        }
        $params = ['rates' => $rates, 'markup' => $num($p['markup'] ?? null), 'buffer' => $num($p['buffer'] ?? null), 'vat' => $num($p['vat'] ?? null) ?? 0.0,
            'currency' => array_key_exists((string) ($p['currency'] ?? ''), Kalkulation::currencies()) ? (string) $p['currency'] : 'EUR',
            'round_step' => max(0.0, (float) ($num($p['round_step'] ?? 0) ?? 0)), 'round_mode' => ($p['round_mode'] ?? '') === 'up' ? 'up' : 'nearest',
            'cost_rate' => $num($p['cost_rate'] ?? null), 'rate_once' => (string) ($p['rate_once'] ?? ''), 'rate_monthly' => (string) ($p['rate_monthly'] ?? '')];
        $positions = [];
        foreach (array_slice(array_values((array) ($in['positions'] ?? [])), 0, self::MAX_POSITIONS) as $q) {
            if (!is_array($q)) continue;
            $basis = (string) ($q['basis'] ?? 'fixed');
            if ($basis !== 'fixed' && !preg_match('~^[a-z0-9_-]{1,30}$~', $basis)) $basis = 'fixed';
            $positions[] = [
                'id' => preg_match('~^p[a-z0-9]{4,20}$~', (string) ($q['id'] ?? '')) ? (string) $q['id'] : self::pid(),
                'kind' => ($q['kind'] ?? '') === 'monthly' ? 'monthly' : 'once',
                'group' => mb_substr(trim((string) ($q['group'] ?? '')), 0, 120),
                'name' => mb_substr(trim((string) ($q['name'] ?? '')), 0, 200),
                'desc' => mb_substr(trim(str_replace("\r", '', (string) ($q['desc'] ?? ''))), 0, 4000),
                'qty' => $num($q['qty'] ?? null), 'unit' => mb_substr(trim((string) ($q['unit'] ?? '')), 0, 40),
                'basis' => $basis, 'amount' => $num($q['amount'] ?? null), 'hours_internal' => $num($q['hours_internal'] ?? null),
                'cost' => $num($q['cost'] ?? null), 'discount' => $num($q['discount'] ?? null),
                'buffer' => !empty($q['buffer']), 'optional' => !empty($q['optional']), 'alternative' => !empty($q['alternative']),
                'note' => mb_substr(trim(str_replace("\r", '', (string) ($q['note'] ?? ''))), 0, 1000),
                'src' => mb_substr((string) ($q['src'] ?? ''), 0, 40),
            ];
        }
        $vu = (string) ($in['valid_until'] ?? '');
        return [
            'title' => $str('title'), 'customer' => $str('customer'), 'address' => $str('address', 600), 'contact' => $str('contact'),
            'project' => $str('project'), 'valid_until' => preg_match('~^\d{4}-\d{2}-\d{2}$~', $vu) ? $vu : '',
            'intro' => $str('intro', 4000), 'payment' => $str('payment', 4000), 'validity' => $str('validity', 1000), 'term' => $str('term', 2000),
            'closing' => $str('closing', 2000), 'note' => $str('note', 4000),
            'discount_once' => $num($in['discount_once'] ?? null), 'discount_monthly' => $num($in['discount_monthly'] ?? null),
            'params' => $params, 'positions' => $positions,
        ];
    }
}
