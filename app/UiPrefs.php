<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Persönliche Einstellungen der Oberfläche je Konto (users.ui_prefs, JSON) – z. B. Akzentfarbe (Core\Accent), Willkommen
 * (Core\Welcome), Anordnung der Übersicht (Core\Dashboard). Erweiterungen speichern unter eigenem Schlüssel „ext.{name}“:
 *
 *   UiPrefs::get($user, 'ext.meine_erweiterung', 'einfach');   UiPrefs::set((int) $user['id'], 'ext.meine_erweiterung', 'normal');
 *
 * Werte: Skalare oder Arrays (JSON). Beim Schreiben wird der aktuelle Stand aus der Datenbank gelesen (keine verlorenen Schlüssel).
 */
final class UiPrefs
{
    /** Alle Einstellungen eines Kontos (aus dem Datensatz, ohne Datenbankzugriff) */
    public static function all(?array $user): array
    {
        $p = json_decode((string) ($user['ui_prefs'] ?? ''), true);
        return is_array($p) ? $p : [];
    }

    public static function get(?array $user, string $key, mixed $default = null): mixed
    {
        return self::all($user)[$key] ?? $default;
    }

    /** Wert setzen (null = entfernen) – gilt ab der nächsten Anfrage (danach umleiten) */
    public static function set(int $userId, string $key, mixed $value): void
    {
        $raw = app()->db->fetchValue('SELECT ui_prefs FROM users WHERE id = ?', [$userId]);
        $p = json_decode((string) $raw, true);
        $p = is_array($p) ? $p : [];
        if ($value === null) unset($p[$key]); else $p[$key] = $value;
        $json = json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        app()->db->update('users', ['ui_prefs' => $json], 'id = :id', ['id' => $userId]);
    }
}
