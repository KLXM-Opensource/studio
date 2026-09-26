<?php
declare(strict_types=1);

namespace Core\Chat;

use Core\Database;
use Core\Features;
use Core\Network\Network;
use Core\Permissions;
use Core\Support\Support;

/**
 * Chat zwischen Benutzern der Verwaltung – optional (Funktion „chat“, Standard: aus).
 *
 *   storage/chat/chat.sqlite   Räume (Direktnachrichten, Kanäle), Nachrichten, Reaktionen, Lesestände, Erwähnungen,
 *                              Ereignis-Feed für Server-Sent Events, Personenverzeichnis, Einstellungen (meta)
 *   storage/chat/files/        Bilder (nie öffentlich – nur über /admin/chat/datei/{id} nach Rechteprüfung)
 *
 * Wie der Support öffnet jede Website dieselbe Datenbank. Personen: „Website:Benutzer-ID“ (z. B. „demo:3“), Konten der
 * Netzwerk-Administration website-übergreifend als „net:ID“ (dasselbe Konto auf jeder Website).
 *
 * Bereiche:
 *   site     Direktnachrichten und Kanäle einer Website (z. B. „#redaktion“) – Funktion „chat“ + Recht „chat.use“
 *   network  Kanäle und Direktnachrichten der Netzwerk-Administration und Integratoren über alle Websites
 *            (Schalter in Chat-Einstellungen, meta 'network')
 * Kanäle legt an, wer „chat.manage“ hat (Standard: Administration); Mitglieder nach Rollen und/oder Personen.
 */
final class Chat
{
    public const REACTIONS = ['👍', '❤️', '😄', '🎉', '👀', '✅'];
    public const MAX_BODY = 4000;
    public const RETENTION_DEFAULT = 180;

    private static ?Database $db = null;
    private static ?array $rooms = null;

    public static function dir(string $sub = ''): string
    {
        return ROOT . '/storage/chat' . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    public static function db(): Database
    {
        if (self::$db === null) {
            self::$db = new Database(['driver' => 'sqlite', 'path' => self::dir('chat.sqlite')]);
            @chmod(self::dir(), 0770);
            self::migrate(self::$db);
        }
        return self::$db;
    }

    public static function reset(): void
    {
        self::$db = null;
        self::$rooms = null;
    }

    /** Schema – nur ergänzend (neue Tabellen/Spalten, nie Umbenennen oder Löschen) */
    private static function migrate(Database $db): void
    {
        $pk = 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tables = [
            'rooms' => "id $pk, kind VARCHAR(10) NOT NULL, scope VARCHAR(10) NOT NULL DEFAULT 'site', site VARCHAR(40) NOT NULL DEFAULT '',
                slug VARCHAR(40) NULL, name TEXT, topic TEXT, dm_key VARCHAR(200) NULL, access_json TEXT NULL, created_by VARCHAR(80),
                created_at VARCHAR(25) NOT NULL, archived INT NOT NULL DEFAULT 0, last_msg_id INT NOT NULL DEFAULT 0, last_msg_at VARCHAR(25) NULL",
            'messages' => "id $pk, room_id INT NOT NULL, author_key VARCHAR(80) NOT NULL, author_name TEXT, body TEXT NOT NULL DEFAULT '',
                links_json TEXT NULL, mentions TEXT NOT NULL DEFAULT '', files INT NOT NULL DEFAULT 0, created_at VARCHAR(25) NOT NULL,
                edited_at VARCHAR(25) NULL, deleted_at VARCHAR(25) NULL",
            'reactions' => "message_id INT NOT NULL, user_key VARCHAR(80) NOT NULL, emoji VARCHAR(16) NOT NULL, user_name TEXT, created_at VARCHAR(25),
                PRIMARY KEY (message_id, user_key, emoji)",
            'reads' => "user_key VARCHAR(80) NOT NULL, room_id INT NOT NULL, last_read_id INT NOT NULL DEFAULT 0, PRIMARY KEY (user_key, room_id)",
            'mentions' => "message_id INT NOT NULL, user_key VARCHAR(80) NOT NULL, room_id INT NOT NULL, mailed INT NOT NULL DEFAULT 0,
                created_at VARCHAR(25) NOT NULL, PRIMARY KEY (message_id, user_key)",
            'files' => "id $pk, room_id INT NOT NULL, message_id INT NULL, name TEXT, stored TEXT NOT NULL, mime VARCHAR(40) NOT NULL,
                size INT NOT NULL DEFAULT 0, width INT NULL, height INT NULL, uploaded_by VARCHAR(80), created_at VARCHAR(25) NOT NULL",
            // Änderungs-Feed: neue/geänderte/gelöschte Nachrichten, Reaktionen, Räume – Grundlage für SSE und Polling (Last-Event-ID)
            'events' => "id $pk, room_id INT NOT NULL, kind VARCHAR(12) NOT NULL, message_id INT NULL, created_at VARCHAR(25) NOT NULL",
            'people' => "user_key VARCHAR(80) NOT NULL PRIMARY KEY, site VARCHAR(40) NOT NULL, user_id INT NOT NULL, name TEXT, email TEXT,
                network INT NOT NULL DEFAULT 0, notify_mail INT NOT NULL DEFAULT 1, first_seen VARCHAR(25), last_seen VARCHAR(25), digest_at VARCHAR(25) NULL",
            'meta' => "k VARCHAR(40) NOT NULL PRIMARY KEY, v TEXT",
        ];
        foreach ($tables as $name => $cols) $db->pdo->exec("CREATE TABLE IF NOT EXISTS $name ($cols)");
        foreach ([
            'CREATE INDEX IF NOT EXISTS messages_room ON messages(room_id, id)',
            'CREATE INDEX IF NOT EXISTS messages_created ON messages(created_at)',
            'CREATE INDEX IF NOT EXISTS rooms_site ON rooms(scope, site, kind)',
            'CREATE UNIQUE INDEX IF NOT EXISTS rooms_dm ON rooms(scope, site, dm_key) WHERE dm_key IS NOT NULL',
            'CREATE INDEX IF NOT EXISTS mentions_user ON mentions(user_key, mailed)',
            'CREATE INDEX IF NOT EXISTS files_message ON files(message_id)',
            'CREATE INDEX IF NOT EXISTS events_created ON events(created_at)',
        ] as $sql) $db->pdo->exec($sql);
    }

    // ------------------------------------------------------------------ Einstellungen (zentral, meta)

    public static function meta(string $k, ?string $default = null): ?string
    {
        $v = self::db()->fetchValue('SELECT v FROM meta WHERE k = ?', [$k]);
        return $v === null ? $default : (string) $v;
    }

    public static function setMeta(string $k, ?string $v): void
    {
        self::db()->query('INSERT INTO meta (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v', [$k, $v]);
    }

    /** Aufbewahrung in Tagen (0 = unbegrenzt) – zentral für alle Websites; Vorgabe config 'chat_retention_days' */
    public static function retentionDays(): int
    {
        $v = self::meta('retention_days');
        return max(0, (int) ($v ?? app()->config->get('chat_retention_days', self::RETENTION_DEFAULT)));
    }

    /** Übertragung: 'sse' (Standard auf PHP-FPM) oder 'poll' (Entwicklungsserver php -S; config 'chat_transport' => 'auto'|'sse'|'poll') */
    public static function transport(): string
    {
        $mode = (string) app()->config->get('chat_transport', 'auto');
        // php -S mit PHP_CLI_SERVER_WORKERS > 1 bedient mehrere Anfragen gleichzeitig – dort gehen Streams auch
        if ($mode === 'auto' && PHP_SAPI === 'cli-server' && (int) getenv('PHP_CLI_SERVER_WORKERS') > 1) return 'sse';
        return \Core\Http\Sse::supported($mode) ? 'sse' : 'poll';
    }

    /** Dauer eines Streams in Sekunden (config 'chat_stream_seconds', 5–25) */
    public static function streamSeconds(): int
    {
        return max(5, min(25, (int) app()->config->get('chat_stream_seconds', 25)));
    }

    // ------------------------------------------------------------------ Schalter & Rechte

    /** Schalter der Website (Chat-Einstellungen, nur Netzwerk-Administration/Integratoren) – wirkt, solange config 'features' nichts vorgibt */
    public static function siteSwitch(): bool
    {
        try {
            return isset(app()->settings) && (bool) app()->settings->get('sys.userchat_enabled', false);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Ist 'chat' in config 'features' ausdrücklich gesetzt? (dann gilt die Konfiguration, der Schalter ist gesperrt) */
    public static function configured(): ?bool
    {
        $f = (array) app()->config->get('features', []);
        return array_key_exists('chat', $f) ? (bool) $f['chat'] : null;
    }

    /** Chat auf dieser Website eingeschaltet (ohne Integrator-Ausnahme) */
    public static function siteOn(): bool
    {
        return Features::on('chat', false);
    }

    /** Netzwerk-Bereich (Kanäle/Direktnachrichten über alle Websites) eingeschaltet */
    public static function networkOn(): bool
    {
        try {
            return self::meta('network', '0') === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    /** Netzwerk-Administration oder Integrator (config 'integrators') */
    public static function isNet(): bool
    {
        return Features::integrator();
    }

    /** Chat dieser Website nutzen */
    public static function canSite(): bool
    {
        if (!isset(app()->auth) || !app()->auth->user() || !self::siteOn()) return false;
        self::ensureRoles();
        return self::isNet() || can('chat.use');
    }

    public static function canNetwork(): bool
    {
        return isset(app()->auth) && app()->auth->user() && self::isNet() && self::networkOn();
    }

    public static function canUse(): bool
    {
        try {
            return self::canSite() || self::canNetwork();
        } catch (\Throwable $e) {
            error_log('[chat] ' . $e->getMessage());
            return false;
        }
    }

    /** Kanäle dieser Website anlegen/ändern, fremde Nachrichten in Kanälen entfernen */
    public static function canManage(): bool
    {
        return self::isNet() || (self::canSite() && can('chat.manage'));
    }

    /** Chat ein-/ausschalten, Netzwerk-Bereich, Aufbewahrung */
    public static function canConfigure(): bool
    {
        return isset(app()->auth) && app()->auth->user() && self::isNet();
    }

    /** Einstellungsseite sichtbar (Menü „Administration“) */
    public static function settingsVisible(): bool
    {
        try {
            return self::canConfigure() || self::canManage();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Bestehende Rollen einmalig ergänzen: „chat.use“ für die Redaktion (danach frei änderbar, Merker sys.userchat_roles) */
    public static function ensureRoles(): void
    {
        static $done = false;
        if ($done || !isset(app()->settings)) return;
        $done = true;
        if ((int) app()->settings->get('sys.userchat_roles', 0) >= 1) return;
        try {
            foreach (app()->db->fetchAll('SELECT rkey, permissions_json FROM roles') as $r) {
                $p = json_decode((string) $r['permissions_json'], true) ?: [];
                if (in_array('*', $p, true) || $r['rkey'] !== 'editor' || in_array('chat.use', $p, true)) continue;
                app()->db->update('roles', ['permissions_json' => json_encode([...$p, 'chat.use'])], 'rkey = :k', ['k' => $r['rkey']]);
            }
            app()->settings->set('sys.userchat_roles', 1);
        } catch (\Throwable $e) {
            error_log('[chat] roles: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ Personen

    /** Kennung der angemeldeten Person: „Website:ID“ bzw. „net:ID“ (Netzwerk-Konto) */
    public static function me(): string
    {
        return self::keyFor(app()->auth->user() ?? []);
    }

    public static function keyFor(array $u): string
    {
        if (($u['role'] ?? '') === 'network' && (Network::isNetworkSite() || !empty($u['network_uid']))) {
            return 'net:' . (Network::isNetworkSite() ? (int) $u['id'] : (int) $u['network_uid']);
        }
        return site()->key . ':' . (int) ($u['id'] ?? 0);
    }

    public static function myName(): string
    {
        return Support::myName();
    }

    /** Person im Verzeichnis vormerken (Name, E-Mail, Netzwerk) – höchstens alle 5 Minuten schreiben */
    public static function register(): void
    {
        $u = app()->auth->user();
        if (!$u) return;
        $key = self::me();
        $db = self::db();
        $row = $db->fetch('SELECT last_seen, name, email FROM people WHERE user_key = ?', [$key]);
        $name = self::myName();
        $net = self::isNet() ? 1 : 0;
        if (!$row) {
            $db->insert('people', ['user_key' => $key, 'site' => str_starts_with($key, 'net:') ? Network::siteKey() : site()->key, 'user_id' => (int) $u['id'],
                'name' => $name, 'email' => (string) $u['email'], 'network' => $net, 'notify_mail' => 1, 'first_seen' => now(), 'last_seen' => now()]);
            // Erster Besuch: vorhandene Kanäle gelten als gelesen (alte Nachrichten sind nicht „neu“)
            foreach (self::rooms() as $r) {
                if ($r['kind'] === 'channel') self::markRead((int) $r['id'], (int) $r['last_msg_id']);
            }
            return;
        }
        if (strtotime((string) $row['last_seen']) < time() - 300 || $row['name'] !== $name || $row['email'] !== (string) $u['email']) {
            $db->update('people', ['name' => $name, 'email' => (string) $u['email'], 'network' => $net, 'last_seen' => now()], 'user_key = :k', ['k' => $key]);
        }
    }

    /** Person anlegen, falls unbekannt (z. B. Empfänger einer ersten Direktnachricht) */
    public static function ensurePerson(string $key, string $name, string $email, bool $network = false): void
    {
        $db = self::db();
        if ($db->fetchValue('SELECT 1 FROM people WHERE user_key = ?', [$key])) return;
        [$site, $id] = explode(':', $key, 2) + ['', '0'];
        $db->insert('people', ['user_key' => $key, 'site' => $site === 'net' ? Network::siteKey() : $site, 'user_id' => (int) $id, 'name' => $name,
            'email' => $email, 'network' => $network ? 1 : 0, 'notify_mail' => 1, 'first_seen' => null, 'last_seen' => null]);
    }

    public static function person(string $key): ?array
    {
        return self::db()->fetch('SELECT * FROM people WHERE user_key = ?', [$key]);
    }

    /** Anzeigename + Website (fremde Websites/Netzwerk mit Bezeichnung) */
    public static function personLabel(string $key, ?string $name = null): array
    {
        $p = $name === null ? self::person($key) : null;
        $name ??= (string) ($p['name'] ?? '');
        [$site] = explode(':', $key, 2) + [''];
        $siteLabel = match (true) {
            $site === 'net' => __('Netzwerk'),
            $site === site()->key => '',
            default => Support::siteLabel($site),
        };
        return ['name' => $name !== '' ? $name : __('Unbekannt'), 'site' => $siteLabel];
    }

    /**
     * Personen dieser Website, die den Chat nutzen dürfen (für Direktnachrichten, @Erwähnungen, Kanal-Mitglieder).
     * @return list<array{key:string,name:string,email:string,role:string,site:string}>
     */
    public static function siteUsers(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $out = [];
        $roles = Permissions::roles();
        $cols = array_column(app()->db->fetchAll('PRAGMA table_info(users)'), 'name');
        $sel = 'id, email, name, role' . (in_array('network_uid', $cols, true) ? ', network_uid' : '') . (in_array('disabled', $cols, true) ? ', disabled' : '');
        foreach (app()->db->fetchAll("SELECT $sel FROM users ORDER BY name, email") as $u) {
            if ((int) ($u['disabled'] ?? 0)) continue;
            $role = $roles[$u['role']] ?? null;
            $net = $u['role'] === 'network';
            if (!$net && !Permissions::allows($role, 'chat.use')) continue;
            $out[] = ['key' => self::keyFor($u), 'name' => (string) (($u['name'] ?? '') ?: $u['email']), 'email' => (string) $u['email'], 'role' => (string) $u['role'], 'site' => site()->key];
        }
        return $cache = $out;
    }

    /** Personen des Netzwerk-Bereichs (Netzwerk-Administration und Integratoren, die den Chat schon einmal geöffnet haben) */
    public static function networkPeople(): array
    {
        $out = [];
        // Konten der Netzwerk-Administration (auch wenn sie den Chat noch nie geöffnet haben)
        try {
            foreach (Network::accounts() as $a) {
                if ((int) ($a['disabled'] ?? 0)) continue;
                $out['net:' . (int) $a['id']] = ['key' => 'net:' . (int) $a['id'], 'name' => (string) (($a['name'] ?? '') ?: $a['email']), 'email' => (string) $a['email'], 'role' => 'network', 'site' => Network::siteKey()];
            }
        } catch (\Throwable $e) {
            error_log('[chat] network accounts: ' . $e->getMessage());
        }
        foreach (self::db()->fetchAll('SELECT * FROM people WHERE network = 1 ORDER BY name') as $p) {
            $out[(string) $p['user_key']] ??= ['key' => (string) $p['user_key'], 'name' => (string) ($p['name'] ?: $p['email']), 'email' => (string) $p['email'], 'role' => '', 'site' => (string) $p['site']];
        }
        return array_values($out);
    }

    // ------------------------------------------------------------------ Räume

    /** Für die angemeldete Person sichtbare Räume (Kanäle zuerst), gecacht je Anfrage */
    public static function rooms(bool $fresh = false): array
    {
        if (self::$rooms !== null && !$fresh) return self::$rooms;
        $me = self::me();
        $rows = self::db()->fetchAll("SELECT * FROM rooms WHERE archived = 0 AND (
                (kind = 'channel' AND ((scope = 'site' AND site = :s) OR scope = 'network'))
             OR (kind = 'dm' AND ('|' || dm_key || '|') LIKE :me))
            ORDER BY kind = 'dm', name", ['s' => site()->key, 'me' => '%|' . $me . '|%']);
        $out = [];
        foreach ($rows as $r) if (self::visible($r)) $out[(int) $r['id']] = $r;
        return self::$rooms = $out;
    }

    public static function room(int $id): ?array
    {
        $rooms = self::rooms();
        if (!isset($rooms[$id])) $rooms = self::rooms(true);
        return $rooms[$id] ?? null;
    }

    /** Darf die angemeldete Person den Raum sehen? */
    public static function visible(array $r): bool
    {
        if ((int) $r['archived']) return false;
        if ($r['scope'] === 'network') {
            if (!self::canNetwork()) return false;
            return $r['kind'] === 'channel' || self::isMember($r);
        }
        if ($r['site'] !== site()->key || !self::canSite()) return false;
        if ($r['kind'] === 'dm') return self::isMember($r);
        return self::accessOk($r);
    }

    public static function isMember(array $r, ?string $key = null): bool
    {
        return in_array($key ?? self::me(), explode('|', (string) $r['dm_key']), true);
    }

    /** Kanal-Mitgliedschaft: keine Auswahl = alle Chat-Nutzer der Website; sonst Rollen und/oder Personen. Verwaltende sehen alle Kanäle. */
    public static function accessOk(array $r, ?array $who = null): bool
    {
        $a = json_decode((string) ($r['access_json'] ?? ''), true) ?: [];
        $roles = (array) ($a['roles'] ?? []);
        $users = (array) ($a['users'] ?? []);
        if (!$roles && !$users) return true;
        if ($who !== null) return in_array($who['role'], $roles, true) || in_array($who['key'], $users, true);
        if (self::canManage()) return true;
        $u = app()->auth->user();
        return in_array((string) ($u['role'] ?? ''), $roles, true) || in_array(self::me(), $users, true);
    }

    /** Wer gehört zum Raum? (für Erwähnungen und E-Mail-Hinweise) @return list<array{key,name,email}> */
    public static function audience(array $r): array
    {
        if ($r['kind'] === 'dm') {
            $out = [];
            foreach (explode('|', (string) $r['dm_key']) as $k) {
                $p = self::person($k);
                $out[] = ['key' => $k, 'name' => (string) ($p['name'] ?? ''), 'email' => (string) ($p['email'] ?? ''), 'role' => '', 'site' => ''];
            }
            return $out;
        }
        if ($r['scope'] === 'network') return self::networkPeople();
        return array_values(array_filter(self::siteUsers(), fn($u) => self::accessOk($r, $u)));
    }

    /** Anzeigename eines Raums für die angemeldete Person */
    public static function roomLabel(array $r): array
    {
        if ($r['kind'] === 'channel') {
            return ['name' => '#' . ($r['slug'] ?: $r['name']), 'site' => $r['scope'] === 'network' ? __('Netzwerk') : ''];
        }
        $me = self::me();
        $other = array_values(array_filter(explode('|', (string) $r['dm_key']), fn($k) => $k !== $me))[0] ?? $me;
        return self::personLabel($other) + ['key' => $other];
    }

    /** Direktnachricht öffnen oder anlegen. $scope 'site' | 'network' */
    public static function openDm(string $other, string $scope): array
    {
        $me = self::me();
        if ($other === $me) throw new \InvalidArgumentException(__('Direktnachrichten an sich selbst sind nicht möglich.'));
        $pool = $scope === 'network' ? self::networkPeople() : self::siteUsers();
        $person = null;
        foreach ($pool as $p) if ($p['key'] === $other) { $person = $p; break; }
        if (!$person) throw new \InvalidArgumentException(__('Diese Person kann den Chat nicht nutzen.'));
        if ($scope === 'network' && !self::canNetwork()) throw new \InvalidArgumentException(__('Keine Berechtigung.'));
        if ($scope === 'site' && !self::canSite()) throw new \InvalidArgumentException(__('Keine Berechtigung.'));
        self::ensurePerson($other, $person['name'], $person['email'], $scope === 'network');
        $keys = [$me, $other];
        sort($keys);
        $dmKey = implode('|', $keys);   // nur Personen; Bereich und Website stehen in scope/site
        $db = self::db();
        $row = $db->fetch('SELECT * FROM rooms WHERE dm_key = ? AND kind = ? AND scope = ? AND site = ?', [$dmKey, 'dm', $scope, $scope === 'network' ? '' : site()->key]);
        if ($row) {
            if ((int) $row['archived']) $db->update('rooms', ['archived' => 0], 'id = :id', ['id' => (int) $row['id']]);
            self::$rooms = null;
            return $row;
        }
        $id = $db->insert('rooms', ['kind' => 'dm', 'scope' => $scope, 'site' => $scope === 'network' ? '' : site()->key, 'slug' => null, 'name' => '',
            'topic' => '', 'dm_key' => $dmKey, 'access_json' => null, 'created_by' => $me, 'created_at' => now()]);
        self::event($id, 'room');
        self::$rooms = null;
        return $db->fetch('SELECT * FROM rooms WHERE id = ?', [$id]);
    }

    /** Kurzname für Kanäle: klein, a–z, 0–9, Bindestrich */
    public static function slug(string $s): string
    {
        $s = mb_strtolower(trim(ltrim(trim($s), '#')));
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $s = preg_replace('~[^a-z0-9]+~', '-', $s);
        return mb_substr(trim((string) $s, '-'), 0, 40);
    }

    /** Kanal anlegen/ändern. $in: name, topic, scope, roles[], users[] → Fehlermeldung oder null */
    public static function saveChannel(?array $room, array $in): ?string
    {
        $slug = self::slug((string) ($in['name'] ?? ''));
        if ($slug === '') return __('Bitte einen Namen für den Kanal angeben.');
        $scope = ($in['scope'] ?? 'site') === 'network' && self::isNet() ? 'network' : 'site';
        if ($room) $scope = $room['scope'];
        if ($scope === 'network' && !self::isNet()) return __('Keine Berechtigung.');
        $site = $scope === 'network' ? '' : site()->key;
        $dup = self::db()->fetchValue("SELECT id FROM rooms WHERE kind = 'channel' AND scope = ? AND site = ? AND slug = ? AND archived = 0" . ($room ? ' AND id != ' . (int) $room['id'] : ''), [$scope, $site, $slug]);
        if ($dup) return __('Einen Kanal „#{name}“ gibt es schon.', ['name' => $slug]);
        $roles = array_values(array_filter((array) ($in['roles'] ?? []), fn($r) => is_string($r) && preg_match('~^[\w-]{1,40}$~', $r)));
        $valid = array_column($scope === 'network' ? self::networkPeople() : self::siteUsers(), 'key');
        $users = array_values(array_intersect(array_filter((array) ($in['users'] ?? []), 'is_string'), $valid));
        $data = ['slug' => $slug, 'name' => $slug, 'topic' => mb_substr(trim((string) ($in['topic'] ?? '')), 0, 200),
            'access_json' => $scope === 'network' ? null : json_encode(['roles' => $roles, 'users' => $users])];
        $db = self::db();
        if ($room) {
            $db->update('rooms', $data, 'id = :id', ['id' => (int) $room['id']]);
            self::event((int) $room['id'], 'room');
        } else {
            $id = $db->insert('rooms', $data + ['kind' => 'channel', 'scope' => $scope, 'site' => $site, 'dm_key' => null, 'created_by' => self::me(), 'created_at' => now()]);
            self::event($id, 'room');
        }
        self::$rooms = null;
        return null;
    }

    public static function archiveChannel(array $room, bool $archived = true): void
    {
        self::db()->update('rooms', ['archived' => $archived ? 1 : 0], 'id = :id', ['id' => (int) $room['id']]);
        self::event((int) $room['id'], 'room');
        self::$rooms = null;
    }

    /** Kanäle zur Verwaltung: diese Website (+ Netzwerk für Integratoren), inkl. archivierter */
    public static function manageableChannels(): array
    {
        $sql = "SELECT r.*, (SELECT COUNT(*) FROM messages m WHERE m.room_id = r.id AND m.deleted_at IS NULL) AS n FROM rooms r WHERE kind = 'channel' AND ((scope = 'site' AND site = ?)"
            . (self::isNet() ? " OR scope = 'network'" : '') . ') ORDER BY archived, scope, slug';
        return self::db()->fetchAll($sql, [site()->key]);
    }

    /** Beim Einschalten: Kanal „#redaktion“ anlegen, falls die Website noch keinen hat */
    public static function ensureDefaultChannel(): void
    {
        if (self::db()->fetchValue("SELECT 1 FROM rooms WHERE kind = 'channel' AND scope = 'site' AND site = ?", [site()->key])) return;
        self::db()->insert('rooms', ['kind' => 'channel', 'scope' => 'site', 'site' => site()->key, 'slug' => 'redaktion', 'name' => 'redaktion',
            'topic' => __('Absprachen der Redaktion'), 'dm_key' => null, 'access_json' => json_encode(['roles' => [], 'users' => []]), 'created_by' => self::me(), 'created_at' => now()]);
    }

    // ------------------------------------------------------------------ Lesestände & Zähler

    public static function markRead(int $roomId, int $lastId): void
    {
        self::db()->query('INSERT INTO reads (user_key, room_id, last_read_id) VALUES (?, ?, ?)
            ON CONFLICT(user_key, room_id) DO UPDATE SET last_read_id = MAX(last_read_id, excluded.last_read_id)', [self::me(), $roomId, $lastId]);
    }

    /** Ungelesene Nachrichten und Erwähnungen je Raum: [room_id => ['unread' => n, 'mentions' => n]] */
    public static function unreadByRoom(): array
    {
        $rooms = self::rooms();
        if (!$rooms) return [];
        $me = self::me();
        $ids = implode(',', array_map('intval', array_keys($rooms)));
        $rows = self::db()->fetchAll("SELECT m.room_id, COUNT(*) AS n, SUM(CASE WHEN m.mentions LIKE :pat THEN 1 ELSE 0 END) AS mn
            FROM messages m LEFT JOIN reads r ON r.room_id = m.room_id AND r.user_key = :me
            WHERE m.room_id IN ($ids) AND m.id > COALESCE(r.last_read_id, 0) AND m.author_key != :me2 AND m.deleted_at IS NULL
            GROUP BY m.room_id", ['pat' => '%,' . $me . ',%', 'me' => $me, 'me2' => $me]);
        $out = [];
        foreach ($rows as $r) $out[(int) $r['room_id']] = ['unread' => (int) $r['n'], 'mentions' => (int) $r['mn']];
        return $out;
    }

    /** Summe für die Seitenleiste: [unread, mentions] */
    public static function totals(): array
    {
        $u = $m = 0;
        foreach (self::unreadByRoom() as $x) { $u += $x['unread']; $m += $x['mentions']; }
        return [$u, $m];
    }

    /** Zahl für die Navigation (0 ohne Recht oder bei Fehlern) */
    public static function navCount(): array
    {
        try {
            if (!self::canUse()) return [0, 0];
            return self::totals();
        } catch (\Throwable $e) {
            error_log('[chat] nav: ' . $e->getMessage());
            return [0, 0];
        }
    }

    // ------------------------------------------------------------------ Ereignisse

    public static function event(int $roomId, string $kind, ?int $messageId = null): int
    {
        return self::db()->insert('events', ['room_id' => $roomId, 'kind' => $kind, 'message_id' => $messageId, 'created_at' => now()]);
    }

    public static function lastEventId(): int
    {
        return (int) self::db()->fetchValue('SELECT COALESCE(MAX(id), 0) FROM events');
    }

    /**
     * Ereignisse nach $after, die die angemeldete Person sehen darf – fertig für die Oberfläche.
     * Eine günstige Abfrage über den Primärschlüssel; Räume werden nur bei unbekannter ID neu geladen.
     * @return array{0: list<array>, 1: int} [Ereignisse, neue letzte ID]
     */
    public static function eventsSince(int $after, int $limit = 100): array
    {
        $rows = self::db()->fetchAll('SELECT id, room_id, kind, message_id FROM events WHERE id > ? ORDER BY id LIMIT ' . (int) $limit, [$after]);
        if (!$rows) return [[], $after];
        $out = [];
        $reloaded = false;
        foreach ($rows as $e) {
            $after = (int) $e['id'];
            $rid = (int) $e['room_id'];
            $rooms = self::rooms();
            if (!isset($rooms[$rid]) && !$reloaded) { $rooms = self::rooms(true); $reloaded = true; }
            if (!isset($rooms[$rid])) {
                // Raum nicht (mehr) sichtbar: Änderungen an Räumen trotzdem melden (z. B. Kanal archiviert → Liste neu laden)
                if ($e['kind'] === 'room') $out[] = ['id' => $after, 'type' => 'room', 'room' => $rid];
                continue;
            }
            if ($e['kind'] === 'room') { $out[] = ['id' => $after, 'type' => 'room', 'room' => $rid]; continue; }
            $msg = Messages::find((int) $e['message_id']);
            if (!$msg) continue;
            $out[] = ['id' => $after, 'type' => $e['kind'], 'room' => $rid, 'message' => Messages::toJson($msg)];
        }
        return [$out, $after];
    }

    // ------------------------------------------------------------------ Oberfläche

    /** Konfiguration für resources/js/userchat.js + Stylesheet/Skript (leer ohne Recht) */
    public static function head(): string
    {
        if (!self::canUse()) return '';
        $cfg = [
            'base' => url('/admin/api/chat'),
            'page' => url('/admin/chat'),
            'settings' => self::settingsVisible() ? url('/admin/chat/einstellungen') : '',
            'transport' => self::transport(),
            'pollOpen' => 3000,
            'pollClosed' => 60000,
            'me' => self::me(),
            'reactions' => self::REACTIONS,
            'canManage' => self::canManage(),
            'network' => self::canNetwork(),
            'site' => self::canSite(),
            'maxFiles' => Messages::MAX_FILES,
            'maxBytes' => \Core\Support\Tickets::MAX_BYTES,
        ];
        return '<link rel="stylesheet" href="' . e(asset('css/userchat.css')) . '">' . "\n"
            . '<script type="application/json" id="uc-config">' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n"
            . '<script src="' . e(asset('js/userchat.js')) . '" defer></script>';
    }

    // ------------------------------------------------------------------ Aufbewahrung & E-Mail

    /** Nachrichten älter als die Aufbewahrungsfrist löschen (inkl. Bilder), alte Ereignisse aufräumen. → Anzahl gelöschter Nachrichten */
    public static function purge(?int $days = null): int
    {
        $db = self::db();
        $days ??= self::retentionDays();
        // Ereignisse braucht nur die Wiederverbindung – nach 2 Tagen entbehrlich
        $db->query('DELETE FROM events WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 2 * 86400)]);
        if ($days <= 0) return 0;
        $cut = date('Y-m-d H:i:s', time() - $days * 86400);
        $ids = array_map('intval', array_column($db->fetchAll('SELECT id FROM messages WHERE created_at < ?', [$cut]), 'id'));
        foreach (array_chunk($ids, 400) as $chunk) {
            $in = implode(',', $chunk);
            foreach ($db->fetchAll("SELECT stored FROM files WHERE message_id IN ($in)") as $f) @unlink(self::dir('files/' . $f['stored']));
            foreach (['files' => 'message_id', 'reactions' => 'message_id', 'mentions' => 'message_id', 'messages' => 'id'] as $t => $col) {
                $db->query("DELETE FROM $t WHERE $col IN ($in)");
            }
        }
        // Verwaiste Bilder (hochgeladen, Nachricht nie gesendet) nach einem Tag
        foreach ($db->fetchAll('SELECT id, stored FROM files WHERE message_id IS NULL AND created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]) as $f) {
            @unlink(self::dir('files/' . $f['stored']));
            $db->query('DELETE FROM files WHERE id = ?', [(int) $f['id']]);
        }
        // Leere Direktnachrichten-Räume ohne Nachrichten bleiben (Liste), Kanäle ohnehin
        return count($ids);
    }

    /** Gelegentlich nebenbei (≈ 1 % der Aufrufe): Aufbewahrung + E-Mail-Hinweise */
    public static function maybeHousekeeping(): void
    {
        try {
            $last = (int) self::meta('housekeeping_at', '0');
            if ($last > time() - 300) return;
            self::setMeta('housekeeping_at', (string) time());
            if (mt_rand(1, 12) === 1) self::purge();
            Messages::digest();
        } catch (\Throwable $e) {
            error_log('[chat] housekeeping: ' . $e->getMessage());
        }
    }
}
