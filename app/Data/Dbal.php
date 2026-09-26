<?php
declare(strict_types=1);

namespace Core\Data;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

/**
 * Doctrine-DBAL-Verbindung für die Schema-Verwaltung der Datentabellen
 * (plattformneutral: SQLite und MySQL/MariaDB, inkl. Tabellen-Neuaufbau bei SQLite-ALTER).
 * Lese-/Schreibzugriffe auf Einträge laufen über die gemeinsame PDO-Verbindung (Core\Database).
 */
final class Dbal
{
    private static ?Connection $conn = null;

    public static function conn(): Connection
    {
        if (self::$conn) {
            return self::$conn;
        }
        $c = app()->config->get('db');
        $params = ($c['driver'] ?? 'sqlite') === 'mysql'
            ? ['driver' => 'pdo_mysql', 'host' => $c['host'], 'port' => (int) ($c['port'] ?? 3306), 'dbname' => $c['name'],
                'user' => $c['user'], 'password' => $c['pass'], 'charset' => $c['charset'] ?? 'utf8mb4']
            : ['driver' => 'pdo_sqlite', 'path' => $c['path']];
        return self::$conn = DriverManager::getConnection($params);
    }

    /** @var array<string, Connection> SQLite-Verbindungen je Datei (geteilte Datentabellen, siehe Core\Data\Shared) */
    private static array $files = [];

    /** Verbindung zu einer eigenen SQLite-Datei (z. B. storage/shared/{key}/share.sqlite) */
    public static function sqlite(string $path): Connection
    {
        return self::$files[$path] ??= DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
    }
}
