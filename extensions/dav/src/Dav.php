<?php
declare(strict_types=1);

namespace MyCms\Dav;

use Core\Data\Calendar;
use Core\Data\Tables;
use Core\Database;
use Core\Features;
use Core\Permissions;

/**
 * Gemeinsame Logik der Erweiterung „dav“: App-Passwörter, angemeldeter Benutzer, Rechte,
 * Tabellen-Einstellungen und die Zuordnungstabelle dav_objects (Rohdaten für verlustfreie Abgleiche).
 */
final class Dav
{
    /** Angemeldeter DAV-Benutzer: users-Zeile + 'scope' (read|write) des App-Passworts */
    public static ?array $user = null;

    public const SCOPES = ['write' => 'Lesen und ändern', 'read' => 'Nur lesen'];

    // ================================================================= Datenbank

    public static function migrate(Database $db): void
    {
        $mysql = $db->driver === 'mysql';
        $pk = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $big = $mysql ? 'MEDIUMTEXT' : 'TEXT';
        $db->query("CREATE TABLE IF NOT EXISTS dav_passwords (id $pk, user_id INT NOT NULL, name VARCHAR(100) NOT NULL, prefix VARCHAR(16) NOT NULL,
            hash VARCHAR(255) NOT NULL, scope VARCHAR(10) NOT NULL DEFAULT 'write', created_at VARCHAR(25), last_used_at VARCHAR(25))");
        $db->query('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . 'dav_passwords_user ON dav_passwords (user_id)');
        $db->query("CREATE TABLE IF NOT EXISTS dav_objects (id $pk, kind VARCHAR(4) NOT NULL, table_handle VARCHAR(60) NOT NULL, entry_id INT NOT NULL,
            uri VARCHAR(255) NOT NULL, uid VARCHAR(255) NOT NULL, raw $big, hidden INT NOT NULL DEFAULT 0, updated_at VARCHAR(25))");
        $db->query('CREATE UNIQUE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . 'dav_objects_uri ON dav_objects (table_handle, uri' . ($mysql ? '(191)' : '') . ')');
        $db->query('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . 'dav_objects_entry ON dav_objects (table_handle, entry_id)');
    }

    // ================================================================= App-Passwörter

    /** Neues App-Passwort (nur einmal im Klartext) @return string */
    public static function createPassword(int $userId, string $name, string $scope): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $raw = '';
        for ($i = 0; $i < 20; $i++) $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $pw = implode('-', str_split($raw, 5));
        app()->db->insert('dav_passwords', [
            'user_id' => $userId, 'name' => mb_substr(trim(strip_tags($name)) ?: 'Kalender-App', 0, 100), 'prefix' => substr($raw, 0, 5),
            'hash' => password_hash($pw, PASSWORD_DEFAULT), 'scope' => isset(self::SCOPES[$scope]) ? $scope : 'write', 'created_at' => now(),
        ]);
        return $pw;
    }

    public static function passwords(int $userId): array
    {
        return app()->db->fetchAll('SELECT id, name, prefix, scope, created_at, last_used_at FROM dav_passwords WHERE user_id = ? ORDER BY id DESC', [$userId]);
    }

    public static function revoke(int $userId, int $id): void
    {
        app()->db->query('DELETE FROM dav_passwords WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /** E-Mail + App-Passwort prüfen (mit Sperre nach Fehlversuchen je IP) */
    public static function login(string $email, string $password): bool
    {
        $limiter = new \Core\RateLimiter(app()->db);
        $key = 'dav:' . hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), app()->key());
        if ($limiter->tooMany($key, 20, 900)) return false;
        $user = app()->db->fetch('SELECT id, email, name, role FROM users WHERE LOWER(email) = LOWER(?)', [trim($email)]);
        $prefix = substr(str_replace('-', '', $password), 0, 5);
        $row = $user ? app()->db->fetch('SELECT * FROM dav_passwords WHERE user_id = ? AND prefix = ? ORDER BY id DESC', [(int) $user['id'], $prefix]) : null;
        if (!$row || !password_verify($password, (string) $row['hash'])) {
            if (!$row) password_verify($password, '$2y$12$yaNnm6RkwijxMHDaR.bX5eCLU2vn8HWPVSNJ0WE.fRaZHEEdIQoim');   // gleiche Laufzeit
            $limiter->hit($key);
            return false;
        }
        $role = Permissions::role((string) $user['role']);
        if (!$role || !Permissions::allows($role, 'dav.use')) return false;
        if (($row['last_used_at'] ?? '') < date('Y-m-d H:i', time() - 300)) {
            app()->db->update('dav_passwords', ['last_used_at' => now()], 'id = :id', ['id' => (int) $row['id']]);
        }
        self::$user = $user + ['scope' => $row['scope'], 'roleDef' => $role];
        return true;
    }

    // ================================================================= Rechte

    /** Darf der DAV-Benutzer die Tabelle sehen (Einträge bearbeiten)? */
    public static function canSee(array $table): bool
    {
        return self::$user !== null && Permissions::allows(self::$user['roleDef'], 'data.edit', $table['handle']);
    }

    /** Schreiben: App-Passwort mit „Lesen und ändern“ + Recht data.edit */
    public static function canWrite(array $table): bool
    {
        return self::canSee($table) && self::$user['scope'] === 'write';
    }

    public static function can(string $perm, array $table): bool
    {
        return self::$user !== null && Permissions::allows(self::$user['roleDef'], $perm, $table['handle']);
    }

    // ================================================================= Einstellungen je Tabelle

    public const TABLE_DEFAULTS = ['caldav' => true, 'addressbook' => false, 'status' => 'published', 'on_delete' => 'draft', 'map' => []];

    /** vCard-Zuordnung (Kurzname der Zuordnung → Feldtypen) */
    public const CARD_MAP = [
        'fn' => ['text'], 'n_family' => ['text'], 'n_given' => ['text'], 'org' => ['text', 'select'], 'title' => ['text'],
        'adr_street' => ['text', 'textarea'], 'adr_zip' => ['text'], 'adr_city' => ['text'], 'adr_country' => ['text', 'select'],
        'url' => ['url', 'link'], 'note' => ['textarea', 'text', 'richtext'], 'bday' => ['date'], 'photo' => ['media'], 'categories' => ['select', 'multiselect'],
    ];

    public static function tableSettings(array $table): array
    {
        $all = (array) app()->settings->get('ext.dav.tables', []);
        // Eingangs-Tabellen (verschlüsselte Anfragen) nie über CalDAV/CardDAV
        if (Tables::isInbox($table)) return ['caldav' => false, 'addressbook' => false] + self::TABLE_DEFAULTS;
        $s = (array) ($all[$table['handle']] ?? []) + self::TABLE_DEFAULTS;
        if ($s['addressbook'] && !$s['map']) $s['map'] = self::guessMap($table);
        return $s;
    }

    public static function saveTableSettings(string $handle, array $s): void
    {
        $all = (array) app()->settings->get('ext.dav.tables', []);
        $all[$handle] = $s;
        app()->settings->set('ext.dav.tables', $all);
    }

    /** Sinnvolle vCard-Zuordnung aus Feldnamen (vorname, nachname, firma, strasse, plz, ort …) */
    public static function guessMap(array $table): array
    {
        $names = [
            'n_family' => ['nachname', 'name', 'familienname', 'last_name', 'lastname'], 'n_given' => ['vorname', 'first_name', 'firstname'],
            'org' => ['firma', 'organisation', 'unternehmen', 'company'], 'title' => ['position', 'funktion', 'titel', 'job_title'],
            'adr_street' => ['strasse', 'adresse', 'anschrift', 'street'], 'adr_zip' => ['plz', 'postleitzahl', 'zip'], 'adr_city' => ['ort', 'stadt', 'city'],
            'adr_country' => ['land', 'country'], 'url' => ['website', 'webseite', 'homepage', 'url'], 'note' => ['notiz', 'notizen', 'bemerkung', 'note'],
            'bday' => ['geburtstag', 'geburtsdatum', 'birthday'], 'photo' => ['foto', 'bild', 'photo'], 'categories' => ['gruppe', 'kategorie', 'kategorien'],
        ];
        $map = [];
        foreach ($names as $k => $cands) {
            foreach ($table['fields'] as $f) {
                if (in_array($f['name'], $cands, true) && in_array($f['type'], self::CARD_MAP[$k], true) && !in_array($f['name'], $map, true)) { $map[$k] = $f['name']; break; }
            }
        }
        if (!isset($map['n_family']) && !isset($map['n_given'])) $map['fn'] = $table['settings']['title_field'];
        return $map;
    }

    /** Tabellen für CalDAV (Kalender) bzw. CardDAV (Adressbuch) */
    public static function calendarTables(): array
    {
        return array_values(array_filter(Calendar::tables(), fn($t) => self::tableSettings($t)['caldav']));
    }

    public static function addressBookTables(): array
    {
        return array_values(array_filter(Tables::content(), fn($t) => self::tableSettings($t)['addressbook'] && !Calendar::enabled($t)));
    }

    public static function enabled(): bool
    {
        return Features::on('dav', false) && Features::on('data', false);
    }

    // ================================================================= Zuordnung (dav_objects)

    public static function objects(string $kind, string $handle): array
    {
        $out = [];
        foreach (app()->db->fetchAll('SELECT * FROM dav_objects WHERE kind = ? AND table_handle = ?', [$kind, $handle]) as $r) $out[(int) $r['entry_id']] = $r;
        return $out;
    }

    public static function objectByUri(string $handle, string $uri): ?array
    {
        return app()->db->fetch('SELECT * FROM dav_objects WHERE table_handle = ? AND uri = ?', [$handle, $uri]);
    }

    public static function saveObject(string $kind, string $handle, int $entryId, string $uri, string $uid, ?string $raw): void
    {
        $cur = app()->db->fetch('SELECT id FROM dav_objects WHERE table_handle = ? AND (uri = ? OR entry_id = ?)', [$handle, $uri, $entryId]);
        $row = ['kind' => $kind, 'table_handle' => $handle, 'entry_id' => $entryId, 'uri' => $uri, 'uid' => $uid, 'raw' => $raw, 'hidden' => 0, 'updated_at' => now()];
        if ($cur) app()->db->update('dav_objects', $row, 'id = :id', ['id' => (int) $cur['id']]);
        else app()->db->insert('dav_objects', $row);
    }

    /** ctag/Sync-Kennung einer Tabelle: ändert sich bei jeder Änderung an Einträgen oder Zuordnungen */
    public static function ctag(array $table, string $kind): string
    {
        $a = Tables::db($table)->fetch("SELECT COUNT(*) AS n, MAX(updated_at) AS u, SUM(id) AS s FROM {$table['table']}");
        // Geteilte Tabellen: auch die Auswahl der Website (ausgeblendet/hervorgehoben) und die eigenen Anzeige-Einstellungen
        if (Tables::isShared($table)) $a['picks'] = [Tables::db($table)->fetchValue('SELECT MAX(pick_at) || COUNT(*) FROM share_picks WHERE pick_site = ?', [site()->key]),
            \Core\Data\Shared::localConfig($table['shared']['key']), \Core\Data\Shared::meta($table['shared']['key'])['members'] ?? []];
        $b = app()->db->fetch('SELECT COUNT(*) AS n, MAX(updated_at) AS u, SUM(hidden) AS h FROM dav_objects WHERE kind = ? AND table_handle = ?', [$kind, $table['handle']]);
        return md5(json_encode([$a, $b, $table['updated_at'] ?? '', self::tableSettings($table)]));
    }

    public static function etag(string $data): string
    {
        return '"' . md5($data) . '"';
    }
}
