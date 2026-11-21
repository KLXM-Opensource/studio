<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Graphics;

use Core\Network\Network;
use Core\Svg;

/**
 * Bausteine des Grafik-Editors für alle Websites der Installation (Bibliothek → „Alle Websites“). Liegen in der
 * Netzwerk-Datenbank (Tabelle graphic_shared, angelegt von Network::ensureSchema) – jede Website sieht dieselben.
 * Jedes SVG läuft durch Core\Svg::clean(); Website und Konto des Anlegens werden vermerkt.
 */
final class Shared
{
    /** Größte Anzahl gemeinsamer Bausteine */
    public const MAX = 500;

    /** @return list<array{id: int, name: string, folder: string, svg: string, width: int, height: int, site: string}> */
    public static function all(): array
    {
        try {
            $rows = Network::db()->fetchAll("SELECT id, name, folder, svg, width, height, site FROM graphic_shared WHERE kind IS NULL OR kind = '' OR kind = 'part' ORDER BY folder, name, id");
        } catch (\Throwable) {
            return [];   // Netzwerk-Datenbank nicht erreichbar: keine gemeinsamen Bausteine
        }
        return array_map(fn($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'folder' => (string) ($r['folder'] ?? ''), 'svg' => (string) $r['svg'],
            'width' => (int) $r['width'], 'height' => (int) $r['height'], 'site' => (string) ($r['site'] ?? '')], $rows);
    }

    public static function find(int $id): ?array
    {
        return Network::db()->fetch('SELECT * FROM graphic_shared WHERE id = ?', [$id]);
    }

    /**
     * Speichern (neu oder ersetzen, $id). Rückgabe: Eintrag wie all().
     * @throws \RuntimeException verständliche Meldung
     */
    public static function store(string $name, string $raw, string $folder = '', ?int $id = null): array
    {
        $db = Network::db();
        $name = Graphics::cleanName($name) ?: __('Baustein');
        $r = Svg::clean($raw, ['keep_ids' => true]);
        $row = ['name' => $name, 'folder' => Graphics::cleanFolder($folder), 'svg' => $r['svg'], 'width' => $r['width'], 'height' => $r['height'], 'updated_at' => now()];
        if ($id) {
            if (!self::find($id)) throw new \RuntimeException(__('Baustein nicht gefunden.'));
            $db->update('graphic_shared', $row, 'id = :id', ['id' => $id]);
        } else {
            if ((int) $db->fetchValue('SELECT COUNT(*) FROM graphic_shared') >= self::MAX) throw new \RuntimeException(__('Zu viele gemeinsame Bausteine.'));
            $id = $db->insert('graphic_shared', $row + ['site' => site()->key, 'user_email' => (string) (app()->auth->user()['email'] ?? ''), 'created_at' => now()]);
        }
        $g = self::find((int) $id);
        return ['id' => (int) $id, 'name' => (string) $g['name'], 'folder' => (string) $g['folder'], 'svg' => (string) $g['svg'], 'width' => (int) $g['width'], 'height' => (int) $g['height'], 'site' => (string) $g['site']];
    }

    /** Umbenennen bzw. in einen Ordner legen */
    public static function update(int $id, ?string $name, ?string $folder): bool
    {
        if (!self::find($id)) return false;
        $row = ['updated_at' => now()];
        if ($name !== null && ($n = Graphics::cleanName($name)) !== '') $row['name'] = $n;
        if ($folder !== null) $row['folder'] = Graphics::cleanFolder($folder);
        Network::db()->update('graphic_shared', $row, 'id = :id', ['id' => $id]);
        return true;
    }

    public static function delete(int $id): bool
    {
        if (!self::find($id)) return false;
        Network::db()->query('DELETE FROM graphic_shared WHERE id = ?', [$id]);
        return true;
    }
}
