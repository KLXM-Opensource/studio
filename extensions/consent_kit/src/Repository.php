<?php
// SPDX-License-Identifier: MIT
// Portions ported from FriendsOfREDAXO/consent_kit (MIT, © KLXM Crossmedia GmbH)
declare(strict_types=1);

namespace MyCms\Consent;

use Core\Database;

/**
 * Tabellen der Website (eigene Datenbank je Website):
 *   consent_services   Dienste inkl. Code-Felder, Cookies/Speicher (items), Ereignisse, Varianten, Domains – JSON-Spalten
 *   consent_revisions  Stände (Schnappschuss dessen, was zur Auswahl stand) je Domain
 *   consent_log        Protokoll: Einwilligungs-ID, Zeit, Domain, Stand, Entscheidung, Dienste, GPC, Sprache – ohne IP/User-Agent
 * Einstellungen und Design liegen in app()->settings unter consent.settings / consent.design / consent.epoch.
 */
final class Repository
{
    /** Gruppen in Anzeige-Reihenfolge; „necessary“ ist nicht abwählbar (Texte: lang/site/en.php) */
    public const GROUPS = [
        'necessary' => ['Notwendig', 'Diese Dienste sind für den Betrieb der Website erforderlich und können nicht deaktiviert werden.', true],
        'functional' => ['Funktional', 'Diese Dienste stellen Zusatzfunktionen bereit, etwa Spamschutz, Terminbuchung oder Chat.', false],
        'statistics' => ['Statistik', 'Diese Dienste erfassen, wie die Website genutzt wird, um sie verbessern zu können.', false],
        'marketing' => ['Marketing', 'Diese Dienste werden eingesetzt, um Werbung auszuspielen und deren Erfolg zu messen.', false],
        'media' => ['Externe Medien', 'Inhalte von Video-, Karten- und Social-Media-Plattformen werden erst nach Einwilligung geladen.', false],
    ];

    public const CODE_FIELDS = ['html_head', 'html_body', 'js_default', 'js_accept', 'js_revoke'];
    public const ITEM_TYPES = ['cookie' => 'Cookie', 'local_storage' => 'Local Storage', 'session_storage' => 'Session Storage', 'indexed_db' => 'IndexedDB'];
    public const UNITS = ['session' => 'Sitzung', 'minutes' => 'Minuten', 'hours' => 'Stunden', 'days' => 'Tage', 'months' => 'Monate', 'years' => 'Jahre', 'persistent' => 'Unbegrenzt'];
    public const GCM_SIGNALS = ['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage', 'functionality_storage', 'personalization_storage', 'security_storage'];
    private const JSON = ['description', 'params', 'param_defs', 'gcm', 'embed_hosts', 'csp', 'items', 'events', 'variants', 'hosts', 'sources', 'note'];

    private static ?array $cache = null;

    public static function migrate(Database $db): void
    {
        $mysql = $db->driver === 'mysql';
        $pk = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $big = $mysql ? 'MEDIUMTEXT' : 'TEXT';
        $ine = $mysql ? '' : 'IF NOT EXISTS ';
        $db->query("CREATE TABLE IF NOT EXISTS consent_services (id $pk, skey VARCHAR(60) NOT NULL, grp VARCHAR(20) NOT NULL DEFAULT 'statistics',
            active INT NOT NULL DEFAULT 0, prio INT NOT NULL DEFAULT 0, name VARCHAR(190) NOT NULL, description $big, provider TEXT, privacy_url TEXT,
            params $big, param_defs $big, html_head $big, html_body $big, js_default $big, js_accept $big, js_revoke $big,
            gcm TEXT, embed_hosts TEXT, csp TEXT, items $big, events $big, variants $big, hosts TEXT, preset VARCHAR(60),
            sources TEXT, verified VARCHAR(20), note TEXT, created_at VARCHAR(25), updated_at VARCHAR(25))");
        $db->query("CREATE UNIQUE INDEX {$ine}consent_services_key ON consent_services (skey)");
        $db->query("CREATE TABLE IF NOT EXISTS consent_revisions (id $pk, host VARCHAR(190) NOT NULL, hash VARCHAR(64) NOT NULL, snapshot $big, created_at VARCHAR(25))");
        $db->query("CREATE INDEX {$ine}consent_revisions_host ON consent_revisions (host" . ($mysql ? '(100)' : '') . ', id)');
        $db->query("CREATE TABLE IF NOT EXISTS consent_log (id $pk, consent_id VARCHAR(40), created_at VARCHAR(25) NOT NULL, host VARCHAR(190),
            revision_id INT, action VARCHAR(20) NOT NULL, accepted TEXT, rejected TEXT, gpc INT NOT NULL DEFAULT 0, lang VARCHAR(10))");
        $db->query("CREATE INDEX {$ine}consent_log_created ON consent_log (created_at)");
        $db->query("CREATE INDEX {$ine}consent_log_cid ON consent_log (consent_id)");
    }

    /** Tabelle vorhanden? (vor der ersten Migration bzw. auf Websites ohne Erweiterung) */
    public static function ready(): bool
    {
        return (int) app()->settings->get('ext.consent_kit.schema', 0) >= 1;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @return list<array> alle Dienste, dekodiert, in Anzeige-Reihenfolge (Gruppe, prio) */
    public static function services(bool $onlyActive = false): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            if (self::ready()) {
                try {
                    $rows = app()->db->fetchAll('SELECT * FROM consent_services ORDER BY prio, id');
                } catch (\Throwable) {
                    $rows = [];
                }
                $order = array_flip(array_keys(self::GROUPS));
                $rows = array_map([self::class, 'decode'], $rows);
                usort($rows, fn($a, $b) => [$order[$a['grp']] ?? 9, $a['prio'], $a['id']] <=> [$order[$b['grp']] ?? 9, $b['prio'], $b['id']]);
                self::$cache = $rows;
            }
        }
        return $onlyActive ? array_values(array_filter(self::$cache, fn($s) => $s['active'])) : self::$cache;
    }

    public static function find(int $id): ?array
    {
        foreach (self::services() as $s) if ($s['id'] === $id) return $s;
        return null;
    }

    public static function byKey(string $key): ?array
    {
        foreach (self::services() as $s) if ($s['skey'] === $key) return $s;
        return null;
    }

    public static function keyExists(string $key, int $exceptId = 0): bool
    {
        $s = self::byKey($key);
        return $s !== null && $s['id'] !== $exceptId;
    }

    private static function decode(array $r): array
    {
        foreach (self::JSON as $k) {
            $v = json_decode((string) ($r[$k] ?? ''), true);
            $r[$k] = is_array($v) ? $v : [];
        }
        $r['id'] = (int) $r['id'];
        $r['active'] = (bool) $r['active'];
        $r['prio'] = (int) $r['prio'];
        foreach (self::CODE_FIELDS as $k) $r[$k] = (string) ($r[$k] ?? '');
        $r['csp'] += ['script' => [], 'connect' => [], 'img' => [], 'frame' => []];
        return $r;
    }

    /** Dienst anlegen ($id = 0) oder ändern; liefert die ID */
    public static function save(int $id, array $data): int
    {
        $row = [];
        foreach (['skey', 'grp', 'name', 'provider', 'privacy_url', 'preset', 'verified'] as $k) {
            if (array_key_exists($k, $data)) $row[$k] = (string) $data[$k];
        }
        foreach (self::CODE_FIELDS as $k) {
            if (array_key_exists($k, $data)) $row[$k] = str_replace("\r\n", "\n", (string) $data[$k]);
        }
        foreach (self::JSON as $k) {
            if (array_key_exists($k, $data)) $row[$k] = json_encode($data[$k] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (array_key_exists('active', $data)) $row['active'] = $data['active'] ? 1 : 0;
        if (array_key_exists('prio', $data)) $row['prio'] = (int) $data['prio'];
        $row['updated_at'] = now();
        if ($id > 0) {
            app()->db->update('consent_services', $row, 'id = ?', [$id]);
        } else {
            $row['created_at'] = now();
            $row['prio'] ??= (int) app()->db->fetchValue('SELECT COALESCE(MAX(prio), 0) + 1 FROM consent_services');
            foreach (['description', 'params', 'param_defs', 'gcm', 'embed_hosts', 'csp', 'items', 'events', 'variants', 'hosts', 'sources', 'note'] as $k) $row[$k] ??= '[]';
            $id = app()->db->insert('consent_services', $row);
        }
        self::changed();
        return $id;
    }

    public static function delete(int $id): void
    {
        app()->db->query('DELETE FROM consent_services WHERE id = ?', [$id]);
        self::changed();
    }

    /** Nach jeder Änderung: Zwischenspeicher leeren, Seiten-Cache leeren (die Konfiguration steht im HTML) */
    public static function changed(): void
    {
        self::flush();
        Consent::flush();
        \Core\PageCache::clear();
    }

    // ------------------------------------------------------------------ Einstellungen

    public const SETTING_DEFAULTS = [
        'layout' => 'box',            // box | bar | modal | offcanvas
        'position' => 'bottom-left',  // bottom-left | bottom-right | top-left | top-right
        'theme' => 'site',            // site (folgt dem Dunkelmodus der Website) | light | dark | auto
        'dismiss' => true,            // Schließen-Schaltfläche (×) ohne Entscheidung
        'trigger' => false,           // schwebende Schaltfläche (Standard aus: „Cookie-Einstellungen“ steht im Fußbereich)
        'footer_link' => true,        // „Cookie-Einstellungen“ automatisch in der Rechtliches-Zeile der Kits
        'banner_groups' => false,     // Gruppen im Hinweis (nur Dialog/Off-Canvas)
        'days' => 365,                // Gültigkeit der Entscheidung
        'reload' => true,             // nach Widerruf neu laden
        'gpc' => 'reject',            // reject | ask | ignore
        'gcm_redaction' => true,
        'gcm_passthrough' => false,
        'gcm_wait' => 500,
        'retention' => 1095,          // Tage, 0 = nie löschen
        'privacy' => '',              // Link (page:ID, /pfad, https://…); leer = Einstellung des Kits
        'imprint' => '',
    ];

    public static function settings(): array
    {
        $s = (array) app()->settings->get('consent.settings', []);
        return array_intersect_key($s, self::SETTING_DEFAULTS) + self::SETTING_DEFAULTS;
    }

    public static function saveSettings(array $s): void
    {
        app()->settings->set('consent.settings', array_intersect_key($s, self::SETTING_DEFAULTS) + self::settings());
        self::changed();
    }

    public static function epoch(): int
    {
        return (int) app()->settings->get('consent.epoch', 0);
    }

    public static function bumpEpoch(): void
    {
        app()->settings->set('consent.epoch', self::epoch() + 1);
        self::changed();
    }

    // ------------------------------------------------------------------ Stände

    /** Stand zum Hash (neu anlegen, wenn sich die einwilligungsrelevanten Angaben geändert haben) */
    public static function revision(string $host, string $hash, array $snapshot): int
    {
        if (!self::ready()) return 0;
        $latest = app()->db->fetch('SELECT id, hash FROM consent_revisions WHERE host = ? ORDER BY id DESC LIMIT 1', [$host]);
        if ($latest && $latest['hash'] === $hash) return (int) $latest['id'];
        return app()->db->insert('consent_revisions', ['host' => $host, 'hash' => $hash, 'created_at' => now(),
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    public static function findRevision(int $id): ?array
    {
        $r = app()->db->fetch('SELECT * FROM consent_revisions WHERE id = ?', [$id]);
        if (!$r) return null;
        $r['snapshot'] = json_decode((string) $r['snapshot'], true) ?: [];
        return $r;
    }
}
