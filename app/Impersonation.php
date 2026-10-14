<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Ansicht als Rolle bzw. als Person (Support, Prüfen von Rechten) – nur für Administration und Netzwerk-Administration.
 *
 *   mode „role“: eigenes Konto, aber nur die Rechte einer Rolle (Auth::user()['role'] = Rolle)
 *   mode „user“: im Namen einer Person – Auth::user() liefert ihr Konto, Rechte und Speichern laufen als diese Person
 *
 * Die Sitzung bleibt die der echten Person (uid, auth_ver, zweiter Faktor). Sitzungs-Schlüssel „imp“: {mode, value, by, since,
 * until}. Endet nach TTL, beim Abmelden oder mit stop(). Während der Ansicht sind Konto-Sicherheit, Benutzer & Rollen,
 * API-Tokens, Grundeinstellungen und Netzwerk gesperrt (blocked()). Beginn/Ende im Sicherheitsprotokoll (Network::log).
 * Nie: Netzwerk-Konten als Ziel; Administration nicht in andere Administrationskonten (Netzwerk-Administration schon).
 */
final class Impersonation
{
    public const TTL = 3600;
    public const BLOCKED = ['/admin/account', '/admin/users', '/admin/roles', '/admin/api-tokens', '/admin/system', '/admin/network', '/admin/features'];
    public const ALLOWED = ['/admin/impersonate/stop', '/admin/logout'];

    /** Sitzungsdaten der laufenden Ansicht oder null */
    public static function data(): ?array
    {
        try {
            $d = app()->session->get('imp');
        } catch (\Throwable) {
            return null;
        }
        return is_array($d) && isset($d['mode'], $d['value'], $d['by']) ? $d : null;
    }

    public static function active(): bool
    {
        return (bool) (app()->auth->user()['_imp'] ?? null);
    }

    /** Darf diese (echte) Person die Ansicht wechseln? */
    public static function canStart(?array $real): bool
    {
        return $real !== null && in_array((string) ($real['role'] ?? ''), ['admin', 'network'], true);
    }

    /** Rollen für „Als Rolle ansehen“: alle außer Administration und Netzwerk */
    public static function roles(): array
    {
        return array_filter(Permissions::roles(), fn($r, $k) => !in_array($k, ['admin', 'network'], true), ARRAY_FILTER_USE_BOTH);
    }

    /** Darf $real als $target arbeiten? */
    public static function canUser(array $real, array $target): bool
    {
        if (!self::canStart($real) || (int) $target['id'] === (int) $real['id']) return false;
        if (($target['role'] ?? '') === 'network' || !empty($target['network_uid']) || (int) ($target['disabled'] ?? 0)) return false;
        if (($target['role'] ?? '') === 'admin' && ($real['role'] ?? '') !== 'network') return false;   // Administration nicht in Administration
        return true;
    }

    /**
     * Wirksames Konto für Auth::user() (echtes Konto + Sitzungsdaten) oder null, wenn die Ansicht nicht (mehr) gilt.
     * Ergebnis trägt '_imp' => [mode, value, label, until, real_id, real_name, real_role].
     */
    public static function effective(array $real, array $imp, Database $db): ?array
    {
        if ((int) $imp['by'] !== (int) $real['id'] || !self::canStart($real) || (int) ($imp['until'] ?? 0) < time()) return null;
        $meta = ['mode' => $imp['mode'], 'value' => (string) $imp['value'], 'until' => (int) $imp['until'],
            'real_id' => (int) $real['id'], 'real_name' => (string) ($real['name'] ?: $real['email']), 'real_role' => (string) $real['role']];
        if ($imp['mode'] === 'role') {
            $role = Permissions::role((string) $imp['value']);
            if (!$role || in_array((string) $imp['value'], ['admin', 'network'], true)) return null;
            return ['role' => (string) $imp['value'], '_imp' => $meta + ['label' => (string) $role['name']]] + $real;
        }
        $t = $db->fetch('SELECT id, email, name, role, locale, appearance, ui_prefs, network_uid, auth_ver, disabled, totp_enabled FROM users WHERE id = ?', [(int) $imp['value']]);
        if (!$t || !self::canUser($real, $t)) return null;
        return $t + ['_imp' => $meta + ['label' => (string) ($t['name'] ?: $t['email'])]];
    }

    /** Ansicht starten (Prüfung durch den Aufrufer: echte Person, canStart) */
    public static function start(array $real, string $mode, string $value): ?string
    {
        if (!self::canStart($real)) return __('Nur die Administration kann die Ansicht wechseln.');
        if ($mode === 'role') {
            if (!isset(self::roles()[$value])) return __('Diese Rolle gibt es nicht oder sie ist nicht wählbar.');
            $label = (string) self::roles()[$value]['name'];
        } elseif ($mode === 'user') {
            $t = app()->db->fetch('SELECT id, email, name, role, network_uid, disabled FROM users WHERE id = ?', [(int) $value]);
            if (!$t || !self::canUser($real, $t)) return __('Als diese Person können Sie nicht arbeiten.');
            $label = (string) ($t['name'] ?: $t['email']) . ' <' . $t['email'] . '>';
        } else {
            return __('Unbekannte Ansicht.');
        }
        app()->session->set('imp', ['mode' => $mode, 'value' => $value, 'by' => (int) $real['id'], 'since' => time(), 'until' => time() + self::TTL]);
        self::log('impersonate.start', $real, ($mode === 'role' ? 'Rolle ' : 'Person ') . $label);
        return null;
    }

    public static function stop(): void
    {
        $u = app()->auth->user();
        if ($u && !empty($u['_imp'])) {
            self::log('impersonate.stop', ['email' => app()->db->fetchValue('SELECT email FROM users WHERE id = ?', [$u['_imp']['real_id']])], (string) $u['_imp']['label']);
        }
        app()->session->forget('imp');
        app()->auth->reset();
    }

    /** Pfad während der Ansicht gesperrt? */
    public static function blocked(string $path): bool
    {
        foreach (self::ALLOWED as $ok) if ($path === $ok) return false;
        foreach (self::BLOCKED as $b) if ($path === $b || str_starts_with($path, $b . '/')) return true;   // Pfade intern immer /admin/…
        return false;
    }

    private static function log(string $action, array $real, string $detail): void
    {
        try {
            Network\Network::log($action, site()->key, (string) ($real['email'] ?? ''), $detail);
        } catch (\Throwable) {
        }
    }

    /** Selbsttest (impersonate:selftest): Regeln, wirksames Konto, Sperren, Zeitlimit – Transaktion wird zurückgerollt */
    public static function selftest(): array
    {
        $ok = 0; $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; echo "  ✓ $what\n"; return; }
            $fails[] = $what; echo "  ✗ $what – erwartet " . var_export($want, true) . ', erhalten ' . var_export($got, true) . "\n";
        };
        $db = app()->db;
        $db->pdo->beginTransaction();
        try {
            $sfx = bin2hex(random_bytes(3));
            $mk = fn(string $role, array $x = []) => (int) $db->insert('users', $x + ['email' => "imp-$role-$sfx@example.invalid", 'name' => ucfirst($role), 'password_hash' => '!', 'role' => $role, 'created_at' => now()]);
            $admin = $db->fetch('SELECT * FROM users WHERE id = ?', [$mk('admin')]);
            $admin2 = $db->fetch('SELECT * FROM users WHERE id = ?', [(int) $db->insert('users', ['email' => "imp-admin2-$sfx@example.invalid", 'name' => 'A2', 'password_hash' => '!', 'role' => 'admin', 'created_at' => now()])]);
            $net = $db->fetch('SELECT * FROM users WHERE id = ?', [$mk('network')]);
            $ed = $db->fetch('SELECT * FROM users WHERE id = ?', [$mk('editor')]);
            $off = $db->fetch('SELECT * FROM users WHERE id = ?', [(int) $db->insert('users', ['email' => "imp-off-$sfx@example.invalid", 'name' => 'Off', 'password_hash' => '!', 'role' => 'editor', 'disabled' => 1, 'created_at' => now()])]);
            $eq('Redaktion darf nicht wechseln', self::canStart($ed), false);
            $eq('Administration → Redaktion', self::canUser($admin, $ed), true);
            $eq('Administration → andere Administration: nein', self::canUser($admin, $admin2), false);
            $eq('Netzwerk → Administration: ja', self::canUser($net, $admin), true);
            $eq('nie in Netzwerk-Konten', self::canUser($net, $net) || self::canUser($admin, $net), false);
            $eq('nicht in gesperrte Konten', self::canUser($admin, $off), false);
            $eq('nicht in sich selbst', self::canUser($admin, $admin), false);
            $eq('Rollen ohne Administration/Netzwerk', array_intersect(array_keys(self::roles()), ['admin', 'network']), []);
            $r = self::effective($admin, ['mode' => 'role', 'value' => 'editor', 'by' => $admin['id'], 'until' => time() + 60], $db);
            $eq('Rolle: eigenes Konto, Rolle getauscht', [$r['id'] ?? null, $r['role'] ?? null], [$admin['id'], 'editor']);
            $u = self::effective($admin, ['mode' => 'user', 'value' => $ed['id'], 'by' => $admin['id'], 'until' => time() + 60], $db);
            $eq('Person: deren Konto', [(int) ($u['id'] ?? 0), $u['_imp']['real_id'] ?? null], [(int) $ed['id'], (int) $admin['id']]);
            $eq('abgelaufen → keine Ansicht', self::effective($admin, ['mode' => 'role', 'value' => 'editor', 'by' => $admin['id'], 'until' => time() - 1], $db), null);
            $eq('fremde Sitzungsdaten → keine Ansicht', self::effective($admin, ['mode' => 'role', 'value' => 'editor', 'by' => $ed['id'], 'until' => time() + 60], $db), null);
            $eq('Rolle Administration nicht wählbar', self::effective($admin, ['mode' => 'role', 'value' => 'admin', 'by' => $admin['id'], 'until' => time() + 60], $db), null);
            $eq('gesperrt: Konto, Benutzer, Grundeinstellungen, Tokens, Netzwerk', array_map([self::class, 'blocked'], ['/admin/account/2fa', '/admin/users', '/admin/system', '/admin/api-tokens', '/admin/network']), [true, true, true, true, true]);
            $eq('erlaubt: Beenden, Seiten, Daten', array_map([self::class, 'blocked'], ['/admin/impersonate/stop', '/admin/pages', '/admin/data/news', '/admin/systemx']), [false, false, false, false]);
        } finally {
            $db->pdo->rollBack();
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
