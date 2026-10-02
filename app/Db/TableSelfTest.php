<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Db;

use Core\Database;

/**
 * php bin/console db:selftest – Core\Db\Table in einer Wegwerf-Datenbank (SQLite, storage/cache): Anlegen, Idempotenz, Ergänzen,
 * Umbenennen (Daten bleiben), Indizes ändern/entfernen, Fremdschlüssel, Spalte entfernen, NOT NULL ohne Vorgabe, Transaktion.
 */
final class TableSelfTest
{
    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . json_encode($want, JSON_UNESCAPED_UNICODE) . ', erhalten ' . json_encode($got, JSON_UNESCAPED_UNICODE);
        };
        $file = sys_get_temp_dir() . '/klxm-db-selftest-' . bin2hex(random_bytes(4)) . '.sqlite';
        try {
            $db = new Database(['driver' => 'sqlite', 'path' => $file]);
            $cols = fn(string $t) => array_column($db->fetchAll("PRAGMA table_info($t)"), null, 'name');
            $idx = fn(string $t) => array_column($db->fetchAll("PRAGMA index_list($t)"), 'unique', 'name');
            $def = fn() => Table::named('qa_termine')->id()
                ->column('titel', 'string', ['length' => 120, 'null' => false, 'default' => ''])
                ->column('status', 'string', ['length' => 12, 'default' => 'neu'])
                ->column('beginn', 'datetime')
                ->column('aktiv', 'bool', ['default' => true])
                ->column('preis', 'decimal', ['precision' => 8, 'scale' => 2])
                ->column('daten', 'json')
                ->index(['status', 'beginn'])
                ->unique('titel');

            $eq('anlegen', $def()->ensure($db), 'created');
            $c = $cols('qa_termine');
            $eq('Spalten', array_keys($c), ['id', 'titel', 'status', 'beginn', 'aktiv', 'preis', 'daten']);
            $eq('id: Primärschlüssel', (int) $c['id']['pk'], 1);
            $eq('titel: NOT NULL, status: Vorgabe', [(int) $c['titel']['notnull'], trim((string) $c['status']['dflt_value'], "'")], [1, 'neu']);
            $eq('Indizes (unique)', $idx('qa_termine'), ['qa_termine_titel' => 1, 'qa_termine_status_beginn' => 0]);
            $eq('Idempotent (2. Lauf)', $def()->ensure($db), 'unchanged');
            $eq('Idempotent (3. Lauf)', $def()->ensure($db), 'unchanged');

            $db->insert('qa_termine', ['titel' => 'Sommerfest', 'beginn' => '2026-07-01 18:00:00']);
            $db->insert('qa_termine', ['titel' => 'Herbstmarkt', 'beginn' => '2026-10-01 10:00:00']);
            $eq('Vorgaben beim Einfügen', $db->fetch('SELECT status, aktiv FROM qa_termine WHERE titel = ?', ['Sommerfest']), ['status' => 'neu', 'aktiv' => 1]);
            $eq('autoincrement', (int) $db->fetchValue('SELECT MAX(id) FROM qa_termine'), 2);

            // Ergänzen: neue Spalte (NOT NULL ohne Vorgabe → nullable, damit vorhandene Zeilen gehen) + neuer Index
            $more = fn() => $def()->column('ort', 'string', ['length' => 80, 'null' => false])->column('plaetze', 'int', ['default' => 0])->index('ort');
            $eq('ergänzen', $more()->ensure($db), 'altered');
            $c = $cols('qa_termine');
            $eq('neue Spalten, ort nullable', [isset($c['ort'], $c['plaetze']), (int) $c['ort']['notnull']], [true, 0]);
            $eq('Daten bleiben beim Ergänzen', (int) $db->fetchValue('SELECT COUNT(*) FROM qa_termine'), 2);
            $eq('ergänzen idempotent', $more()->ensure($db), 'unchanged');

            // Umbenennen: Daten bleiben, zweiter Lauf ändert nichts
            $ren = fn() => Table::named('qa_termine')->id()
                ->column('title', 'string', ['length' => 120, 'null' => false, 'default' => ''])->renameColumn('titel', 'title')
                ->column('status', 'string', ['length' => 12, 'default' => 'neu'])->column('beginn', 'datetime')
                ->column('aktiv', 'bool', ['default' => true])->column('preis', 'decimal', ['precision' => 8, 'scale' => 2])->column('daten', 'json')
                ->column('ort', 'string', ['length' => 80])->column('plaetze', 'int', ['default' => 0])
                ->index(['status', 'beginn'])->unique('title', 'qa_termine_title')->dropIndex('qa_termine_titel')->index('ort');
            $eq('umbenennen', $ren()->ensure($db), 'altered');
            $c = $cols('qa_termine');
            $eq('alte Spalte weg, neue da', [isset($c['titel']), isset($c['title'])], [false, true]);
            $eq('Daten nach dem Umbenennen', array_column($db->fetchAll('SELECT title FROM qa_termine ORDER BY id'), 'title'), ['Sommerfest', 'Herbstmarkt']);
            $eq('Index umbenannt (alter entfernt)', array_key_exists('qa_termine_titel', $idx('qa_termine')) || !array_key_exists('qa_termine_title', $idx('qa_termine')), false);
            $eq('umbenennen idempotent', $ren()->ensure($db), 'unchanged');

            // Index ändern (andere Spalten, gleicher Name) und Spalte entfernen
            $chg = fn() => Table::named('qa_termine')->index(['beginn'], 'qa_termine_status_beginn')->dropColumn('daten');
            $eq('Index ändern + Spalte entfernen', $chg()->ensure($db), 'altered');
            $eq('Spalte entfernt, andere bleiben (additiv)', [isset($cols('qa_termine')['daten']), isset($cols('qa_termine')['ort'])], [false, true]);
            $eq('Index mit neuen Spalten', array_column($db->fetchAll('PRAGMA index_info(qa_termine_status_beginn)'), 'name'), ['beginn']);
            $eq('Index ändern idempotent', $chg()->ensure($db), 'unchanged');

            // Fremdschlüssel (SQLite: PRAGMA foreign_keys = ON in Core\Database)
            $fk = fn() => Table::named('qa_anmeldungen')->id()->column('termin_id', 'int', ['null' => false])->column('name', 'string')
                ->foreignKey('termin_id', 'qa_termine', 'id', 'CASCADE')->index('termin_id');
            $eq('Fremdschlüssel anlegen', $fk()->ensure($db), 'created');
            $eq('Fremdschlüssel idempotent', $fk()->ensure($db), 'unchanged');
            $db->insert('qa_anmeldungen', ['termin_id' => 1, 'name' => 'Erika Musterfrau']);
            try {
                $db->insert('qa_anmeldungen', ['termin_id' => 999, 'name' => 'Max Mustermann']);
                $eq('Fremdschlüssel greift', 'eingefügt', 'abgelehnt');
            } catch (\PDOException) {
                $ok++;
            }
            $db->query('DELETE FROM qa_termine WHERE id = 1');
            $eq('ON DELETE CASCADE', (int) $db->fetchValue('SELECT COUNT(*) FROM qa_anmeldungen'), 0);
            $fk2 = fn() => Table::named('qa_termine')->column('serie_id', 'int')->foreignKey('serie_id', 'qa_termine', 'id', 'SET NULL');
            $eq('Fremdschlüssel an bestehender Tabelle', $fk2()->ensure($db), 'altered');
            $eq('… idempotent, Daten bleiben', [$fk2()->ensure($db), (int) $db->fetchValue('SELECT COUNT(*) FROM qa_termine')], ['unchanged', 1]);

            // In einer offenen Transaktion derselben Verbindung (Extension::runMigrations) – keine zweite Sperre
            $db->transaction(function () use ($db, $eq): void {
                $db->insert('qa_termine', ['title' => 'Winterzauber']);
                $eq('in Transaktion', Table::named('qa_termine')->column('notiz', 'text')->ensure($db), 'altered');
            });
            $eq('Transaktion: Zeile und Spalte da', [(int) $db->fetchValue('SELECT COUNT(*) FROM qa_termine'), isset($cols('qa_termine')['notiz'])], [2, true]);

            // Ungültige Angaben
            foreach (['Böse Tabelle' => fn() => Table::named('drop table x'), 'Spaltenname' => fn() => Table::named('qa_x')->column('a b', 'int'),
                'Typ' => fn() => Table::named('qa_x')->column('a', 'varchar'), 'onDelete' => fn() => Table::named('qa_x')->foreignKey('a', 'b', 'id', 'DROP')] as $what => $fn) {
                try { $fn(); $eq('abgelehnt: ' . $what, 'angenommen', 'Ausnahme'); } catch (\InvalidArgumentException) { $ok++; }
            }
            $eq('exists()', [Table::exists('qa_termine', $db), Table::exists('qa_gibt_es_nicht', $db)], [true, false]);
        } catch (\Throwable $e) {
            $fails[] = 'Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            unset($db);
            foreach ([$file, $file . '-wal', $file . '-shm'] as $f) @unlink($f);
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
