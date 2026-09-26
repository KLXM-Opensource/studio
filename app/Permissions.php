<?php
declare(strict_types=1);

namespace Core;

/**
 * Rollen und Berechtigungen.
 *
 * Rollen liegen in der Tabelle `roles` (key, name, description, permissions_json, tables_json, builtin).
 * „admin“ hat immer alle Rechte und ist nicht änderbar. Alle anderen Rollen sind frei einstellbar –
 * bei Datentabellen und Anfragen (Eingangs-Tabellen) optional nur für ausgewählte Tabellen.
 */
final class Permissions
{
    /** Alle Rechte, gruppiert (Bezeichnungen übersetzbar) */
    public static function catalog(): array
    {
        return [
            __('Seiten') => [
                'pages.edit' => __('Seiten bearbeiten (als Entwurf)'),
                'pages.publish' => __('Seiten veröffentlichen'),
                'pages.manage' => __('Seiten anlegen, verschieben, löschen, Seiteneinstellungen'),
                'settings.edit' => __('{title} ändern', ['title' => app()->theme->settingsTitle()]),
                // Weiterleitungen und 404-Protokoll (Core\Redirects) – zusätzlich Funktion „redirects“
                'redirects.manage' => __('Weiterleitungen und 404-Protokoll verwalten'),
            ],
            __('Medien') => [
                'media.upload' => __('Dateien hochladen und bearbeiten'),
                'media.delete' => __('Dateien löschen'),
                'media.shared' => __('Geteilte Medien (Pools) pflegen – hochladen, Alt-Texte, Zuschnitte, Sammlungen'),
            ],
            __('Daten') => [
                'data.edit' => __('Einträge anlegen und bearbeiten'),
                'data.publish' => __('Einträge veröffentlichen'),
                'data.delete' => __('Einträge löschen'),
                'data.schema' => __('Tabellen und Felder ändern'),
                'data.shared.manage' => __('Geteilte Daten verwalten (Tabellen für mehrere Websites anlegen, Mitglieder zuordnen)'),
                // Externe Quellen (Core\Sources) – zusätzlich Funktion „sources“ der Website
                'sources.manage' => __('Externe Quellen einrichten und abrufen (Feeds, APIs, OpenImmo)'),
            ],
            __('Anfragen') => [
                'requests.read' => __('Online-Anfragen lesen (mit Schlüssel)'),
                'requests.manage' => __('Anfragen erledigen und löschen'),
            ],
            __('Administration') => [
                'system.manage' => __('Grundeinstellungen'),
                // Haupt-Admin: Funktionen & Erweiterungen der Website schalten (Core\Features) – im Netzwerk nur mit Freigabe
                'system.features' => __('Funktionen & Erweiterungen ein- und ausschalten (Haupt-Admin)'),
                'design.edit' => __('Design (Style-Editor)'),
                'blocks.build' => __('Eigene Blöcke bauen und freigeben (Block-Baukasten)'),
                'users.manage' => __('Benutzer und Rollen'),
                'api.manage' => __('API & MCP'),
            ],
            // KI (Core\AI\Ai) – zusätzlich Funktion „ai“ und Schalter der Website (Grundeinstellungen → KI)
            __('KI') => [
                'ai.use' => __('KI-Funktionen nutzen (Texte, Übersetzung, SEO, Alt-Texte)'),
                // Prüf-Ebene „Eingereicht“ (Core\Review\Queue) – Standard: nur Administration
                'review.manage' => __('Eingereichte Änderungen von API, MCP und KI prüfen und freigeben'),
            ],
            // Support & Wissensdatenbank (Core\Support) – zentral für alle Websites
            __('Support') => [
                'support.report' => __('Probleme melden, eigene Meldungen sehen, Wissensdatenbank lesen'),
                'support.answer' => __('Fragen stellen und beantworten'),
                'support.manage' => __('Alle Meldungen dieser Website sehen und beantworten (auf der Hauptwebsite: Support-Team für alle Websites)'),
            ],
            // Chat zwischen Benutzern (Core\Chat) – zusätzlich Funktion „chat“ der Website
            __('Chat') => [
                'chat.use' => __('Chat nutzen (Direktnachrichten, Kanäle der Website)'),
                'chat.manage' => __('Kanäle anlegen, Mitglieder festlegen, Nachrichten in Kanälen entfernen'),
            ],
        ] + Extensions::permissions();
    }

    public static function all(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::catalog())));
    }

    /** Vorgaben beim ersten Start (danach frei änderbar) */
    public static function defaults(): array
    {
        return [
            'admin' => ['name' => 'Administration', 'description' => 'Alle Rechte', 'permissions' => ['*'], 'builtin' => 1],
            // Zentrale Konten der Netzwerk-Website (Core\Network) – nicht in „Benutzer & Rollen“ vergebbar
            'network' => ['name' => 'Netzwerk-Administration', 'description' => 'Alle Websites und alle Daten (zentral verwaltet)', 'permissions' => ['*'], 'builtin' => 1],
            'editor' => ['name' => 'Redaktion', 'description' => 'Inhalte pflegen und veröffentlichen',
                'permissions' => ['pages.edit', 'pages.publish', 'pages.manage', 'settings.edit', 'media.upload', 'media.delete',
                    'data.edit', 'data.publish', 'data.delete', 'requests.read', 'requests.manage', 'support.report', 'support.answer', 'ai.use', 'chat.use']],
            'author' => ['name' => 'Autorin / Autor', 'description' => 'Entwürfe schreiben, nichts veröffentlichen',
                'permissions' => ['pages.edit', 'media.upload', 'data.edit', 'support.report']],
            'requests' => ['name' => 'Anfragen bearbeiten', 'description' => 'Nur Online-Anfragen',
                'permissions' => ['requests.read', 'requests.manage', 'support.report']],
        ];
    }

    /** Fehlende Standardrollen anlegen (idempotent) */
    public static function seed(Database $db): void
    {
        foreach (self::defaults() as $key => $r) {
            if (!$db->fetchValue('SELECT COUNT(*) FROM roles WHERE rkey = ?', [$key])) {
                $db->insert('roles', ['rkey' => $key, 'name' => $r['name'], 'description' => $r['description'],
                    'permissions_json' => json_encode($r['permissions']), 'tables_json' => null, 'builtin' => (int) ($r['builtin'] ?? 0)]);
            }
        }
    }

    public static function roles(): array
    {
        $out = [];
        foreach (app()->db->fetchAll('SELECT * FROM roles ORDER BY builtin DESC, name') as $r) {
            $out[$r['rkey']] = self::hydrate($r);
        }
        return $out;
    }

    public static function role(string $key): ?array
    {
        $r = app()->db->fetch('SELECT * FROM roles WHERE rkey = ?', [$key]);
        return $r ? self::hydrate($r) : null;
    }

    private static function hydrate(array $r): array
    {
        return ['key' => $r['rkey'], 'name' => $r['name'], 'description' => (string) $r['description'],
            'permissions' => json_decode((string) $r['permissions_json'], true) ?: [],
            'tables' => $r['tables_json'] === null ? null : (json_decode((string) $r['tables_json'], true) ?: []),
            'builtin' => (bool) $r['builtin']];
    }

    /** Darf die Rolle das? Optional für eine bestimmte Datentabelle. */
    public static function allows(?array $role, string $perm, ?string $table = null): bool
    {
        if (!$role) return false;
        // Funktionsumfang der Website (config) schlägt jede Rolle
        if (!Features::allowsPermission($perm)) return false;
        if (in_array('*', $role['permissions'], true)) return true;
        if (!in_array($perm, $role['permissions'], true)) return false;
        // Tabellenauswahl der Rolle gilt für Daten-Rechte und für Anfragen (Eingangs-Tabellen, z. B. nur „Rezeptanfragen“)
        if ($table !== null && is_array($role['tables'])
            && ((str_starts_with($perm, 'data.') && $perm !== 'data.schema') || str_starts_with($perm, 'requests.'))) {
            return in_array($table, $role['tables'], true);
        }
        return true;
    }
}
