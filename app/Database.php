<?php
declare(strict_types=1);

namespace Core;

use PDO;

/**
 * PDO-Wrapper. SQLite (Standard) oder MySQL/MariaDB.
 */
final class Database
{
    public readonly PDO $pdo;
    public readonly string $driver;

    public function __construct(array $cfg)
    {
        $this->driver = $cfg['driver'] ?? 'sqlite';

        if ($this->driver === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'], $cfg['port'] ?? 3306, $cfg['name'], $cfg['charset'] ?? 'utf8mb4');
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass']);
        } else {
            $dir = dirname($cfg['path']);
            if (!is_dir($dir)) {
                mkdir($dir, 0770, true);
            }
            $this->pdo = new PDO('sqlite:' . $cfg['path']);
            $this->pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(',', $cols),
            implode(',', array_map(fn($c) => ':' . $c, $cols)));
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): void
    {
        $set = implode(',', array_map(fn($c) => "$c = :$c", array_keys($data)));
        $this->query("UPDATE $table SET $set WHERE $where", array_merge($data, $params));
    }

    public function transaction(callable $fn): mixed
    {
        // Verschachtelt (z. B. Probelauf der Prüf-Ebene, Core\Review\Queue): Sicherungspunkt statt zweiter Transaktion
        if ($this->pdo->inTransaction()) {
            $sp = 'sp_' . bin2hex(random_bytes(4));
            $this->pdo->exec("SAVEPOINT $sp");
            try {
                $r = $fn($this);
                $this->pdo->exec("RELEASE SAVEPOINT $sp");
                return $r;
            } catch (\Throwable $e) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT $sp");
                $this->pdo->exec("RELEASE SAVEPOINT $sp");
                throw $e;
            }
        }
        $this->pdo->beginTransaction();
        try {
            $r = $fn($this);
            $this->pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Fügt fehlende Spalten hinzu (idempotent, SQLite und MySQL). */
    public function ensureColumns(string $table, array $columns): void
    {
        if ($this->driver === 'mysql') {
            $have = array_column($this->fetchAll("SHOW COLUMNS FROM $table"), 'Field');
        } else {
            $have = array_column($this->fetchAll("PRAGMA table_info($table)"), 'name');
        }
        foreach ($columns as $name => $ddl) {
            if (!in_array($name, $have, true)) {
                $this->pdo->exec("ALTER TABLE $table ADD COLUMN $name $ddl");
            }
        }
    }

    /** Legt fehlende Tabellen an (idempotent). */
    public function migrate(): void
    {
        $my = $this->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $long = $my ? 'LONGTEXT' : 'TEXT';
        $str = 'VARCHAR(191)';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

        $tables = [
            'pages' => "id $pk, slug $str NOT NULL, title $str NOT NULL, meta_description TEXT,
                og_image INT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', is_home INT NOT NULL DEFAULT 0,
                sort INT NOT NULL DEFAULT 0, noindex INT NOT NULL DEFAULT 0,
                content_draft $long, content_published $long,
                updated_at VARCHAR(25), published_at VARCHAR(25)",
            'settings' => "skey $str NOT NULL PRIMARY KEY, value_json $long",
            'media' => "id $pk, file $str NOT NULL, original_name $str, alt TEXT, credit TEXT, width INT, height INT,
                mime VARCHAR(80), size INT, variants_json TEXT, created_at VARCHAR(25)",
            'users' => "id $pk, email $str NOT NULL UNIQUE, name $str, password_hash $str NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'editor', created_at VARCHAR(25), last_login VARCHAR(25)",
            'revisions' => "id $pk, page_id INT NOT NULL, blocks_json $long, created_at VARCHAR(25), user_id INT NULL, note $str",
            // Früher: 'requests' (Online-Anfragen). Seit den Eingangs-Tabellen (Core\Data\Inbox) nicht mehr angelegt;
            // bestehende Tabellen bleiben unangetastet (Übernahme: Inbox::migrate, danach per DROP TABLE requests entfernbar).
            // Protokoll der Eingangs-Tabellen: Entschlüsseln, Status, Zuweisung, Löschen – nie Inhalte
            'inbox_log' => "id $pk, table_handle VARCHAR(64) NOT NULL, entry_ids TEXT, user_id INT NULL, action VARCHAR(20) NOT NULL,
                detail $str NULL, ip_hash VARCHAR(32) NULL, created_at VARCHAR(25)",
            'hits' => "id $pk, hkey $str NOT NULL, created_at INT NOT NULL",
            'media_collections' => "id $pk, name $str NOT NULL, description TEXT, created_at VARCHAR(25)",
            'media_collection_items' => "collection_id INT NOT NULL, media_id INT NOT NULL, sort INT NOT NULL DEFAULT 0, PRIMARY KEY (collection_id, media_id)",
            'api_tokens' => "id $pk, name $str NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE, prefix VARCHAR(16),
                scope VARCHAR(20) NOT NULL DEFAULT 'read', user_id INT NULL, created_at VARCHAR(25), last_used_at VARCHAR(25),
                expires_at VARCHAR(25) NULL",
            // Prüf-Ebene (Core\Review\Queue): Herkunft und Freigabe von Änderungen über API, MCP und KI
            'change_log' => "id $pk, created_at VARCHAR(25) NOT NULL, channel VARCHAR(10) NOT NULL, token_id INT NULL, token_name $str NULL,
                client $str NULL, feature VARCHAR(30) NULL, user_id INT NULL, action VARCHAR(40) NOT NULL, entity_type VARCHAR(20) NOT NULL,
                entity_id VARCHAR(80) NULL, entity_label $str NULL, summary TEXT, lang VARCHAR(10) NULL, payload_json $long, target_json TEXT,
                before_json $long, after_json $long, diff_json $long, base_hash VARCHAR(64) NULL, status VARCHAR(12) NOT NULL DEFAULT 'applied',
                reviewer_id INT NULL, reviewed_at VARCHAR(25) NULL, reason TEXT, error TEXT, checked_at VARCHAR(25) NULL",
            // Untertitel/Kapitel für Video und Audio (Core\MediaTracks) – WebVTT in der DB, veröffentlichte zusätzlich als Datei
            'media_tracks' => "id $pk, media_id INT NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT 'subtitles', lang VARCHAR(12) NOT NULL,
                label $str NULL, status VARCHAR(12) NOT NULL DEFAULT 'draft', source VARCHAR(12) NOT NULL DEFAULT 'editor', note $str NULL,
                vtt $long, cues INT NOT NULL DEFAULT 0, file $str NULL, created_by INT NULL, reviewed_by INT NULL, reviewed_at VARCHAR(25) NULL,
                created_at VARCHAR(25), updated_at VARCHAR(25)",
            // Hintergrund-Aufträge für Medien (Core\AI\MediaJobs): KI-Transkription, Untertitel übersetzen
            'media_jobs' => "id $pk, type VARCHAR(20) NOT NULL, media_id INT NOT NULL, pool VARCHAR(40) NULL, lang VARCHAR(12) NULL,
                target VARCHAR(12) NULL, track_id INT NULL, result_track INT NULL, status VARCHAR(12) NOT NULL DEFAULT 'queued',
                progress INT NOT NULL DEFAULT 0, message TEXT, user_id INT NULL, pid INT NULL, seconds INT NULL, cancel INT NOT NULL DEFAULT 0,
                created_at VARCHAR(25), started_at VARCHAR(25) NULL, finished_at VARCHAR(25) NULL",
            // Funktionen & Erweiterungen: wer hat wann was geschaltet (Core\FeatureLog)
            'feature_log' => "id $pk, created_at VARCHAR(25) NOT NULL, user_id INT NULL, user_email $str NULL, kind VARCHAR(12) NOT NULL,
                target VARCHAR(80) NOT NULL, old_value VARCHAR(12) NULL, new_value VARCHAR(12) NULL, detail $str NULL, ip_hash VARCHAR(32) NULL",
            // Landingpages mit eigenen Domains (Core\Landings): Domain(s) → Seite bzw. Seitenzweig, Optionen/Marke als JSON
            'landings' => "id $pk, label $str NULL, hosts TEXT NOT NULL, page_id INT NOT NULL, include_subpages INT NOT NULL DEFAULT 1,
                mode VARCHAR(10) NOT NULL DEFAULT 'own', options_json TEXT, active INT NOT NULL DEFAULT 1, created_at VARCHAR(25), updated_at VARCHAR(25)",
        ];

        foreach ($tables as $name => $cols) {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS $name ($cols)$tail");
        }
        // Neue Spalten in bestehenden Installationen ergänzen
        $this->ensureColumns('media', [
            'title' => "$str NULL",
            'tags' => 'TEXT NULL',
            'focus_x' => 'INT NOT NULL DEFAULT 50',
            'focus_y' => 'INT NOT NULL DEFAULT 50',
            'decorative' => 'INT NOT NULL DEFAULT 0',
            'updated_at' => 'VARCHAR(25) NULL',
            'crops' => 'TEXT NULL',
            'i18n' => 'TEXT NULL',   // Übersetzungen: {"en": {"alt": "…", "title": "…"}}
            'pool_ref' => 'VARCHAR(80) NULL',   // Verweis auf eine geteilte Pool-Datei „pool:id“ (MediaPools)
            'transcripts' => 'TEXT NULL',   // Transkripte je Sprache (Video/Audio): {"de": {"text": "…", "status": "published"}} – Core\MediaTracks
        ]);

        // Seitenbaum: Eltern, Pfad, Menü, Seitentyp (page | template für Detailseiten von Datentabellen)
        $this->ensureColumns('pages', [
            'parent_id' => 'INT NULL',
            'path' => "$str NULL",
            'menu' => 'INT NOT NULL DEFAULT 0',
            'nav_title' => "$str NULL",
            'type' => "VARCHAR(20) NOT NULL DEFAULT 'page'",
            'template_for' => 'VARCHAR(64) NULL',
            'lang' => 'VARCHAR(10) NULL',
            'translation_group' => 'INT NULL',
            'meta_title' => "$str NULL",   // eigener Titel für Suchmaschinen (sonst Seitentitel), SEO/KI-Assistent
        ]);
        $this->dropUniqueSlug();
        // API-Tokens: Änderungen direkt übernehmen oder zur Freigabe einreichen (Core\Review), zuletzt gemeldeter MCP-Client
        $this->ensureColumns('api_tokens', ['review_mode' => "VARCHAR(10) NOT NULL DEFAULT 'direct'", 'mcp_client' => "$str NULL"]);

        // Rollen mit Rechten (siehe Core\Permissions), Oberflächensprache je Benutzer
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS roles (rkey VARCHAR(40) NOT NULL PRIMARY KEY, name $str NOT NULL, description TEXT,
            permissions_json TEXT, tables_json TEXT NULL, builtin INT NOT NULL DEFAULT 0)$tail");
        Permissions::seed($this);
        $this->ensureColumns('users', ['locale' => 'VARCHAR(10) NULL', 'appearance' => 'VARCHAR(10) NULL',
            'favorites' => 'TEXT NULL']);   // Favoriten der Verwaltung (JSON, Core\Favorites)
        $this->ensureColumns('users', ['ui_prefs' => 'TEXT NULL']);   // persönliche Oberfläche: Akzentfarbe u. a. (JSON, Core\Accent)
        // Sperre, Sitzungs-Version, Netzwerk-Schatten-Konten, Zwei-Faktor-Anmeldung (Core\Network, Core\Totp)
        $this->ensureColumns('users', Network\Network::USER_COLUMNS);
        // Passkeys (WebAuthn) je Konto und Domain (Core\Passkeys)
        Passkeys::ensureTable($this);
        // Eigene Blöcke (Block-Baukasten, Core\Blocks\Custom): Definitionen und Verlauf je Website
        Blocks\Custom::ensureTable($this);
        // Externe Quellen (Core\Sources): Feeds/APIs/OpenImmo → Datentabellen, Herkunft je Eintrag, Protokoll
        Sources\Sources::ensureTable($this);

        // Datentabellen (YForm-ähnlich): Definition; die Einträge liegen in eigenen Tabellen data_{handle}
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS data_tables (id $pk, handle VARCHAR(64) NOT NULL UNIQUE, name $str NOT NULL,
            singular $str, icon VARCHAR(40), description TEXT, fields_json $long, settings_json $long, sort INT NOT NULL DEFAULT 0,
            created_at VARCHAR(25), updated_at VARCHAR(25))$tail");

        if (!$my) {
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS hits_key ON hits (hkey, created_at)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS rev_page ON revisions (page_id, id)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS pages_path ON pages (path)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS pages_parent ON pages (parent_id, sort)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS inbox_log_time ON inbox_log (created_at)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS change_log_status ON change_log (status, id)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS media_tracks_media ON media_tracks (media_id, status)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS media_jobs_status ON media_jobs (status, id)');
        }
    }

    /**
     * Seiten-Slugs sind nur noch je Ebene eindeutig (/blog/uebersicht und /shop/uebersicht).
     * Entfernt die alte UNIQUE-Einschränkung einmalig (SQLite: Tabelle neu aufbauen).
     */
    private function dropUniqueSlug(): void
    {
        if ($this->driver === 'mysql') {
            $idx = $this->fetchAll("SHOW INDEX FROM pages WHERE Column_name = 'slug' AND Non_unique = 0");
            foreach (array_unique(array_column($idx, 'Key_name')) as $name) {
                $this->pdo->exec("ALTER TABLE pages DROP INDEX `$name`");
            }
            return;
        }
        $unique = false;
        foreach ($this->fetchAll('PRAGMA index_list(pages)') as $i) {
            if ((int) $i['unique'] === 1 && $i['origin'] === 'u') {
                $cols = array_column($this->fetchAll('PRAGMA index_info(' . $this->pdo->quote($i['name']) . ')'), 'name');
                $unique = $unique || $cols === ['slug'];
            }
        }
        if (!$unique) {
            return;
        }
        $sql = (string) $this->fetchValue("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'pages'");
        $new = preg_replace('~(slug\s+VARCHAR\(191\)\s+NOT NULL)\s+UNIQUE~i', '$1', $sql);
        $new = preg_replace('~CREATE TABLE\s+"?pages"?~i', 'CREATE TABLE pages_new', $new, 1);
        $cols = implode(', ', array_column($this->fetchAll('PRAGMA table_info(pages)'), 'name'));
        $this->pdo->exec('PRAGMA foreign_keys = OFF');
        $this->transaction(function () use ($new, $cols) {
            $this->pdo->exec($new);
            $this->pdo->exec("INSERT INTO pages_new ($cols) SELECT $cols FROM pages");
            $this->pdo->exec('DROP TABLE pages');
            $this->pdo->exec('ALTER TABLE pages_new RENAME TO pages');
        });
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }
}
