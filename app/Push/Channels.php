<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

use Core\Database;
use Core\Data\Tables;

/**
 * Kanäle der Push-Benachrichtigungen, die Besucher abonnieren können:
 *   Tabellen-Kanäle  „data:{tabelle}“ – automatisch aus Datentabellen mit „Besucher können neue Einträge abonnieren“ (Core\Push\Topics),
 *                    neue Einträge lösen eine Mitteilung aus
 *   freie Kanäle     „ch:{kurzname}“ – Tabelle push_channels (Name, Beschreibung, öffentlich, Reihenfolge, archiviert), z. B.
 *                    „Allgemeine News“ oder „Notdienst“; Mitteilungen nur von Hand (Mitteilungen → Verfassen)
 * Öffentlich = in Block, Banner und Glocke wählbar und über /api/push/subscribe abonnierbar. Nicht öffentliche freie Kanäle
 * bleiben für bestehende Abos erhalten (z. B. ein Kanal nur für Mitglieder, der per Link angeboten wird). Archivierte Kanäle
 * nehmen keine Abos mehr an und erscheinen nicht mehr als Ziel; bestehende Abos bleiben, bis jemand abbestellt.
 */
final class Channels
{
    public const PREFIX = 'ch:';

    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->query("CREATE TABLE IF NOT EXISTS push_channels (id $pk, handle VARCHAR(40) NOT NULL UNIQUE, name VARCHAR(80) NOT NULL,
            description VARCHAR(240) NULL, public INT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0, created_at VARCHAR(25) NULL,
            archived_at VARCHAR(25) NULL)$tail");
    }

    /** Freie Kanäle (auch archivierte) in Reihenfolge */
    public static function free(): array
    {
        return app()->db->fetchAll('SELECT * FROM push_channels ORDER BY archived_at IS NOT NULL, sort, name');
    }

    public static function find(int $id): ?array
    {
        return app()->db->fetch('SELECT * FROM push_channels WHERE id = ?', [$id]);
    }

    /**
     * Alle Kanäle einheitlich: [Thema => ['topic', 'kind' (table|free), 'name', 'description', 'public', 'archived', 'id'|'handle', 'table'?]].
     * $publicOnly: nur, was Besucher abonnieren können.
     */
    public static function all(bool $publicOnly = false): array
    {
        $out = [];
        if (!Push::on()) return $out;
        foreach (Tables::content() as $t) {
            if (!Topics::enabled($t)) continue;
            $topic = Topics::topic($t);
            $out[$topic] = ['topic' => $topic, 'kind' => 'table', 'name' => (string) $t['name'], 'description' => (string) ($t['settings']['push']['description'] ?? ($t['description'] ?? '')),
                'public' => true, 'archived' => false, 'handle' => (string) $t['handle'], 'table' => $t];
        }
        foreach (self::free() as $c) {
            $arch = $c['archived_at'] !== null;
            if ($publicOnly && (!(int) $c['public'] || $arch)) continue;
            $topic = self::PREFIX . $c['handle'];
            $out[$topic] = ['topic' => $topic, 'kind' => 'free', 'name' => (string) $c['name'], 'description' => (string) ($c['description'] ?? ''),
                'public' => (bool) (int) $c['public'], 'archived' => $arch, 'handle' => (string) $c['handle'], 'id' => (int) $c['id']];
        }
        return $out;
    }

    /** Auswahl für Blöcke und Formulare: [Thema => Name] (öffentliche Kanäle; wirft nie – Blockdefinitionen laden früh) */
    public static function options(): array
    {
        try {
            return array_map(fn($c) => $c['name'], self::all(true));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Themen, die Besucher abonnieren dürfen */
    public static function publicTopics(): array
    {
        return array_keys(self::all(true));
    }

    /** Lesbare Bezeichnung eines Themas (auch für ehemalige Kanäle) */
    public static function label(string $topic): string
    {
        if ($topic === Stats::STAFF) return __('Geräte der Redaktion');
        static $all = null;
        $all ??= self::all();
        if (isset($all[$topic])) return $all[$topic]['name'];
        if (str_starts_with($topic, 'data:') && ($t = Tables::findContent(substr($topic, 5)))) return (string) $t['name'];
        return $topic;
    }

    /** Kanal anlegen bzw. ändern → [id, Fehler[]] */
    public static function save(?array $cur, array $in): array
    {
        $name = trim(strip_tags(mb_substr((string) ($in['name'] ?? ''), 0, 80)));
        $desc = trim(strip_tags(mb_substr((string) ($in['description'] ?? ''), 0, 240)));
        $errors = [];
        if ($name === '') $errors['name'] = __('Bitte einen Namen angeben (z. B. „Allgemeine News“).');
        $handle = $cur['handle'] ?? Tables::normName((string) ($in['handle'] ?? '') ?: $name);
        $handle = substr((string) preg_replace('~[^a-z0-9_-]~', '', $handle), 0, 40);
        if (!$cur) {
            if (!preg_match('~^[a-z0-9][a-z0-9_-]{0,39}$~', $handle)) $errors['handle'] = __('Kurzname: a–z, 0–9, _ und - .');
            elseif (app()->db->fetchValue('SELECT 1 FROM push_channels WHERE handle = ?', [$handle])) $errors['handle'] = __('Diesen Kurznamen gibt es schon.');
        }
        if ($errors) return [null, $errors];
        $data = ['name' => $name, 'description' => $desc !== '' ? $desc : null, 'public' => !empty($in['public']) && $in['public'] !== '0' ? 1 : 0,
            'sort' => max(0, min(9999, (int) ($in['sort'] ?? ($cur['sort'] ?? 0))))];
        if ($cur) {
            app()->db->update('push_channels', $data, 'id = :id', ['id' => (int) $cur['id']]);
            return [(int) $cur['id'], []];
        }
        return [app()->db->insert('push_channels', $data + ['handle' => $handle, 'created_at' => now()]), []];
    }

    /** Archivieren bzw. wiederherstellen */
    public static function archive(array $c, bool $on): void
    {
        app()->db->update('push_channels', ['archived_at' => $on ? now() : null], 'id = :id', ['id' => (int) $c['id']]);
    }

    /** Abos eines Kanals (gültiger Schlüssel) */
    public static function subscribers(string $topic): int
    {
        return (int) app()->db->fetchValue('SELECT COUNT(*) FROM push_subscriptions WHERE topics LIKE ? AND key_fp = ?', ['%,' . $topic . ',%', Keys::fingerprint()]);
    }
}
