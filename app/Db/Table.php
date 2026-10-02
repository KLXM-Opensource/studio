<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Db;

use Core\Database;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table as DbalTable;
use Doctrine\DBAL\Types\Type;

/**
 * Tabelle deklarativ beschreiben und angleichen (nach rex_sql_table) – für Erweiterungen und neue Tabellen des Cores.
 * SQLite und MySQL/MariaDB über Doctrine DBAL (gleiche PDO-Verbindung wie Core\Database, also auch in Transaktionen).
 *
 *   Table::named('kalender_termine')
 *       ->id()                                                       INTEGER-Primärschlüssel mit Autoincrement
 *       ->column('titel', 'string', ['length' => 120, 'null' => false])
 *       ->column('status', 'string', ['length' => 12, 'default' => 'neu'])
 *       ->column('beginn', 'datetime')                                VARCHAR(25) „Y-m-d H:i:s“ wie im übrigen Core
 *       ->column('media_id', 'int')
 *       ->index(['status', 'beginn'])                                 Name automatisch: {tabelle}_{spalten}
 *       ->unique(['titel', 'beginn'])
 *       ->foreignKey('media_id', 'media', 'id', 'SET NULL')           nur wenn beide Tabellen in derselben Datenbank liegen
 *       ->renameColumn('title', 'titel')                              ausdrücklich: alte Spalte → neue (Daten bleiben)
 *       ->ensure();                                                   anlegen bzw. angleichen – beliebig oft, gleiches Ergebnis
 *
 * Typen: id, int, bigint, string (Länge 191), text, longtext, bool, float, decimal (precision/scale), datetime, date, json (TEXT).
 * Optionen: 'null' (Standard true, außer id), 'default', 'length', 'precision', 'scale', 'unsigned'.
 * ensure() ist additiv: Spalten und Indizes, die nicht beschrieben sind, bleiben – entfernt wird nur mit dropColumn()/dropIndex().
 * Der Primärschlüssel wird nur beim Anlegen gesetzt (bestehende Tabellen behalten ihren).
 */
final class Table
{
    /** Typen → Doctrine-Typ + Vorgaben */
    private const TYPES = [
        'id' => ['integer', ['autoincrement' => true, 'notnull' => true]],
        'int' => ['integer', []], 'bigint' => ['bigint', []],
        'string' => ['string', ['length' => 191]], 'text' => ['text', []], 'longtext' => ['text', ['length' => 4294967295]],
        'bool' => ['boolean', []], 'float' => ['float', []], 'decimal' => ['decimal', ['precision' => 10, 'scale' => 2]],
        'datetime' => ['string', ['length' => 25]], 'date' => ['string', ['length' => 10]], 'json' => ['text', []],
    ];

    /** @var array<string, array{0: string, 1: array}> Spalten in Reihenfolge */
    private array $columns = [];
    private array $primary = [];
    /** @var array<string, array{cols: list<string>, unique: bool}> */
    private array $indexes = [];
    /** @var list<array{cols: list<string>, table: string, ref: list<string>, onDelete: ?string}> */
    private array $foreign = [];
    /** @var array<string, string> alt → neu */
    private array $renames = [];
    private array $dropColumns = [];
    private array $dropIndexes = [];

    private function __construct(public readonly string $name)
    {
        if (!preg_match('~^[a-z][a-z0-9_]{0,62}$~', $name)) throw new \InvalidArgumentException("Ungültiger Tabellenname „{$name}“ (a–z, 0–9, _).");
    }

    public static function named(string $name): self
    {
        return new self($name);
    }

    /** Gibt es die Tabelle? */
    public static function exists(string $name, ?Database $db = null): bool
    {
        return self::conn($db ?? app()->db)->createSchemaManager()->tablesExist([$name]);
    }

    /** Primärschlüssel „id“ (INTEGER, Autoincrement) */
    public function id(string $name = 'id'): self
    {
        $this->column($name, 'id');
        $this->primary = [$name];
        return $this;
    }

    public function column(string $name, string $type, array $opts = []): self
    {
        self::ident($name);
        if (!isset(self::TYPES[$type])) throw new \InvalidArgumentException("Unbekannter Spaltentyp „{$type}“ (" . implode(', ', array_keys(self::TYPES)) . ').');
        $this->columns[$name] = [$type, $opts];
        return $this;
    }

    /** Primärschlüssel aus mehreren Spalten (nur beim Anlegen) */
    public function primary(array $cols): self
    {
        $this->primary = array_map([self::class, 'ident'], array_values($cols));
        return $this;
    }

    public function index(array|string $cols, ?string $name = null): self
    {
        return $this->addIndex((array) $cols, $name, false);
    }

    public function unique(array|string $cols, ?string $name = null): self
    {
        return $this->addIndex((array) $cols, $name, true);
    }

    /** Fremdschlüssel; $onDelete: CASCADE | SET NULL | RESTRICT | null (Standard der Datenbank) */
    public function foreignKey(array|string $cols, string $table, array|string $ref = 'id', ?string $onDelete = null): self
    {
        if ($onDelete !== null && !in_array(strtoupper($onDelete), ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'], true)) {
            throw new \InvalidArgumentException("Ungültiges onDelete „{$onDelete}“.");
        }
        $this->foreign[] = ['cols' => array_map([self::class, 'ident'], (array) $cols), 'table' => self::ident($table),
            'ref' => array_map([self::class, 'ident'], (array) $ref), 'onDelete' => $onDelete === null ? null : strtoupper($onDelete)];
        return $this;
    }

    /** Spalte umbenennen (nur wenn die alte da und die neue noch nicht da ist – wiederholbar) */
    public function renameColumn(string $from, string $to): self
    {
        $this->renames[self::ident($from)] = self::ident($to);
        return $this;
    }

    public function dropColumn(string $name): self
    {
        $this->dropColumns[] = self::ident($name);
        return $this;
    }

    public function dropIndex(string $name): self
    {
        $this->dropIndexes[] = self::ident($name);
        return $this;
    }

    /**
     * Tabelle anlegen bzw. angleichen. @return string 'created' | 'altered' | 'unchanged'
     */
    public function ensure(?Database $db = null): string
    {
        $conn = self::conn($db ?? app()->db);
        $sm = $conn->createSchemaManager();
        if (!$sm->tablesExist([$this->name])) {
            $t = new DbalTable($this->name);
            foreach ($this->columns as $col => [$type, $opts]) $t->addColumn($col, ...self::spec($type, $opts));
            if ($this->primary) $t->setPrimaryKey($this->primary);
            $this->applyIndexes($t);
            $this->applyForeign($t);
            $sm->createTable($t);
            return 'created';
        }
        $changed = false;
        if ($this->renames) {
            $have = array_map(fn($c) => strtolower($c->getName()), $sm->listTableColumns($this->name));
            foreach ($this->renames as $from => $to) {
                if (in_array($from, $have, true) && !in_array($to, $have, true)) {
                    $conn->executeStatement(sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s', $conn->quoteSingleIdentifier($this->name),
                        $conn->quoteSingleIdentifier($from), $conn->quoteSingleIdentifier($to)));
                    $changed = true;
                }
            }
        }
        $current = $sm->introspectTable($this->name);
        $t = clone $current;
        foreach ($this->dropColumns as $col) if ($t->hasColumn($col)) $t->dropColumn($col);
        foreach ($this->dropIndexes as $ix) if ($t->hasIndex($ix)) $t->dropIndex($ix);
        foreach ($this->columns as $col => [$type, $opts]) {
            [$dbalType, $o] = self::spec($type, $opts);
            if ($t->hasColumn($col)) {
                // Primärschlüssel/Autoincrement bestehender Tabellen nicht anfassen; sonst Typ, NULL und Vorgabe wie beschrieben
                if ($type === 'id') continue;
                $o += ['default' => null];
                // NULL → NOT NULL nur mit Vorgabe (leere Werte werden damit gefüllt), sonst bleibt die Spalte nullable
                if ($o['notnull'] && !$t->getColumn($col)->getNotnull()) {
                    if ($o['default'] === null) {
                        $o['notnull'] = false;
                    } else {
                        $conn->executeStatement(sprintf('UPDATE %s SET %s = ? WHERE %2$s IS NULL', $conn->quoteSingleIdentifier($this->name),
                            $conn->quoteSingleIdentifier($col)), [$o['default']]);
                    }
                }
                $t->modifyColumn($col, ['type' => Type::getType($dbalType)] + $o);
            } else {
                // Neue Spalte in bestehender Tabelle: NOT NULL nur mit Vorgabe (sonst scheitert ALTER bei vorhandenen Zeilen)
                if (($o['notnull'] ?? false) && !array_key_exists('default', $o)) $o['notnull'] = false;
                unset($o['autoincrement']);
                $t->addColumn($col, $dbalType, $o);
            }
        }
        $this->applyIndexes($t);
        $this->applyForeign($t);
        $diff = $sm->createComparator()->compareTables($current, $t);
        if (!$diff->isEmpty()) {
            $sm->alterTable($diff);
            $changed = true;
        }
        return $changed ? 'altered' : 'unchanged';
    }

    /** Beschreibung als Zeichenkette (für den Fingerabdruck in Extension::schema) */
    public function fingerprint(): string
    {
        return md5(serialize([$this->name, $this->columns, $this->primary, $this->indexes, $this->foreign, $this->renames, $this->dropColumns, $this->dropIndexes]));
    }

    // ------------------------------------------------------------------ intern

    private function addIndex(array $cols, ?string $name, bool $unique): self
    {
        $cols = array_map([self::class, 'ident'], array_values($cols));
        $name ??= substr($this->name . '_' . implode('_', $cols), 0, 60);
        $this->indexes[self::ident($name)] = ['cols' => $cols, 'unique' => $unique];
        return $this;
    }

    private function applyIndexes(DbalTable $t): void
    {
        foreach ($this->indexes as $name => $ix) {
            if ($t->hasIndex($name)) {
                $have = $t->getIndex($name);
                $same = array_map('strtolower', $have->getUnquotedColumns()) === $ix['cols'] && $have->isUnique() === $ix['unique'];
                if ($same) continue;
                $t->dropIndex($name);
            }
            $ix['unique'] ? $t->addUniqueIndex($ix['cols'], $name) : $t->addIndex($ix['cols'], $name);
        }
    }

    private function applyForeign(DbalTable $t): void
    {
        foreach ($this->foreign as $fk) {
            // Vorhanden = gleiche Spalten auf dieselbe Tabelle (SQLite kennt keine Namen für Fremdschlüssel)
            foreach ($t->getForeignKeys() as $have) {
                if (array_map('strtolower', $have->getUnquotedLocalColumns()) === $fk['cols'] && strtolower($have->getForeignTableName()) === $fk['table']) continue 2;
            }
            $t->addForeignKeyConstraint($fk['table'], $fk['cols'], $fk['ref'], $fk['onDelete'] ? ['onDelete' => $fk['onDelete']] : [],
                substr($this->name . '_' . implode('_', $fk['cols']) . '_fk', 0, 60));
        }
    }

    /** @return array{0: string, 1: array} Doctrine-Typ und Optionen */
    private static function spec(string $type, array $opts): array
    {
        [$dbal, $o] = self::TYPES[$type];
        if ($type !== 'id') $o['notnull'] = array_key_exists('null', $opts) ? !$opts['null'] : false;
        foreach (['length', 'precision', 'scale', 'unsigned', 'default'] as $k) {
            if (array_key_exists($k, $opts)) $o[$k] = $opts[$k];
        }
        if ($dbal === 'boolean' && isset($o['default']) && is_bool($o['default'])) $o['default'] = (int) $o['default'];
        return [$dbal, $o];
    }

    private static function ident(string $name): string
    {
        if (!preg_match('~^[a-z_][a-z0-9_]{0,62}$~', $name)) throw new \InvalidArgumentException("Ungültiger Name „{$name}“ (a–z, 0–9, _).");
        return $name;
    }

    /** @var \WeakMap<\PDO, Connection>|null */
    private static ?\WeakMap $conns = null;

    /** Doctrine-Verbindung auf derselben PDO-Verbindung wie Core\Database (gleiche Transaktion, keine zweite Sperre bei SQLite) */
    public static function conn(Database $db): Connection
    {
        self::$conns ??= new \WeakMap();
        if (isset(self::$conns[$db->pdo])) return self::$conns[$db->pdo];
        $pdo = $db->pdo;
        $driver = $db->driver === 'mysql'
            ? new class($pdo) extends \Doctrine\DBAL\Driver\AbstractMySQLDriver {
                public function __construct(private \PDO $pdo) {}
                public function connect(array $params): \Doctrine\DBAL\Driver\Connection { return new \Doctrine\DBAL\Driver\PDO\Connection($this->pdo); }
            }
            : new class($pdo) extends \Doctrine\DBAL\Driver\AbstractSQLiteDriver {
                public function __construct(private \PDO $pdo) {}
                public function connect(array $params): \Doctrine\DBAL\Driver\Connection { return new \Doctrine\DBAL\Driver\PDO\Connection($this->pdo); }
            };
        return self::$conns[$db->pdo] = new Connection([], $driver);
    }
}
