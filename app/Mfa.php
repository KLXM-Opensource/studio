<?php
declare(strict_types=1);

namespace Core;

use Core\Network\Network;

/**
 * Anmelde-Richtlinie: erlaubte Verfahren (Authenticator-App/TOTP, Passkeys, Anmeldung ohne Passwort), zweiter Faktor je Rolle
 * („verlangt“ = App oder Passkey, „nur Passkey“ = phishing-resistent) und Übergangsfrist.
 *
 * - Website: Einstellung sys.auth_policy (Benutzer & Rollen → Anmeldung & Sicherheit, Recht users.manage). Fehlt sie, gelten
 *   alle Verfahren als erlaubt und die ältere Liste sys.twofa_roles als „verlangt“.
 * - Netzwerk-Konten: Einstellung sys.net_auth der Netzwerk-Website (Netzwerk-Übersicht → Netzwerk-Administratoren).
 *   Sie brauchen immer TOTP oder einen Passkey (lokale Tests: 'network_2fa' => false, siehe Totp::required()).
 * - Eingerichtete Verfahren werden immer abgefragt. Ein abgeschaltetes Verfahren wird nicht mehr angeboten, solange das Konto
 *   ein erlaubtes hat, und erfüllt keine Pflicht.
 * - Passkey ohne Passwort (mit Benutzer-Verifizierung) gilt als starke Anmeldung und erfüllt die Pflicht.
 */
final class Mfa
{
    public const KEY = 'sys.auth_policy';
    public const NET_KEY = 'sys.net_auth';
    /** Zeitraum nach Anmeldung/Bestätigung, in dem Passkeys ohne erneute Passworteingabe verwaltet werden dürfen */
    public const REAUTH = 900;
    /** Pfade, die bei ausstehender Pflicht-Einrichtung erreichbar bleiben */
    public const SETUP_PATHS = ['/admin/account/2fa', '/admin/account/2fa/enable', '/admin/account/2fa/choose', '/admin/account/2fa/codes',
        '/admin/account/passkeys/options', '/admin/account/passkeys', '/admin/account/reauth', '/admin/logout'];

    private static ?array $net = null;

    // ================================================================= Richtlinie

    /** @return array{totp:bool, passkey:bool, passwordless:bool, roles:array<string,string>, grace_days:int, since:array<string,int>} */
    public static function policy(): array
    {
        $p = app()->settings->get(self::KEY);
        $p = is_array($p) ? $p : [];
        $roles = [];
        if (isset($p['roles']) && is_array($p['roles'])) {
            foreach ($p['roles'] as $k => $v) if (in_array($v, ['any', 'passkey'], true) && $k !== 'network') $roles[(string) $k] = $v;
        } else {
            foreach ((array) app()->settings->get('sys.twofa_roles', []) as $k) if ($k !== 'network') $roles[(string) $k] = 'any';
        }
        $passkey = (bool) ($p['passkey'] ?? true);
        return ['totp' => (bool) ($p['totp'] ?? true), 'passkey' => $passkey, 'passwordless' => $passkey && (bool) ($p['passwordless'] ?? true),
            'roles' => $roles, 'grace_days' => max(0, min(365, (int) ($p['grace_days'] ?? 0))), 'since' => array_map('intval', (array) ($p['since'] ?? []))];
    }

    /** Richtlinie der Netzwerk-Konten (Netzwerk-Website) @return array{totp:bool, passkey:bool, passwordless:bool, passkey_only:bool} */
    public static function netPolicy(): array
    {
        if (self::$net === null) {
            $p = null;
            try {
                $p = Network::isNetworkSite() ? app()->settings->get(self::NET_KEY)
                    : json_decode((string) Network::db()->fetchValue('SELECT value_json FROM settings WHERE skey = ?', [self::NET_KEY]), true);
            } catch (\Throwable $e) {
                error_log('[mfa] ' . $e->getMessage());
            }
            $p = is_array($p) ? $p : [];
            $passkey = (bool) ($p['passkey'] ?? true);
            $totp = (bool) ($p['totp'] ?? true) || !$passkey;   // mindestens ein Verfahren
            self::$net = ['totp' => $totp, 'passkey' => $passkey, 'passwordless' => $passkey && (bool) ($p['passwordless'] ?? true),
                'passkey_only' => $passkey && (bool) ($p['passkey_only'] ?? false)];
        }
        return self::$net;
    }

    public static function forget(): void
    {
        self::$net = null;
    }

    /** Netzwerk-Konto (Zeile der Netzwerk-Datenbank oder Schatten-Konto)? */
    public static function isNet(array $u): bool
    {
        return ($u['role'] ?? '') === 'network';
    }

    /** Erlaubte Verfahren für ein Konto */
    public static function allowed(array $u): array
    {
        return self::isNet($u) ? self::netPolicy() : self::policy();
    }

    /** Pflicht für ein Konto: null (freiwillig), 'any' (App oder Passkey) oder 'passkey' */
    public static function requirement(array $u): ?string
    {
        if (self::isNet($u)) {
            if (!Totp::required('network')) return null;
            return self::netPolicy()['passkey_only'] ? 'passkey' : 'any';
        }
        return self::policy()['roles'][(string) ($u['role'] ?? '')] ?? null;
    }

    // ================================================================= Konto: Datenbank, Kennung, eingerichtete Verfahren

    /**
     * Datenbank und ID, unter denen Passkeys/TOTP eines Kontos liegen: Netzwerk-Konten auf anderen Websites → Netzwerk-Datenbank.
     * @return array{0: Database, 1: int}
     */
    public static function store(array $u): array
    {
        if (self::isNet($u) && !Network::isNetworkSite()) {
            return [Network::db(), (int) (!empty($u['network_uid']) ? $u['network_uid'] : $u['id'])];
        }
        return [app()->db, (int) $u['id']];
    }

    /** Benutzerkennung für Passkeys (Netzwerk-Konten: gleiche Kennung auf allen Websites, andere Domain = anderer Passkey) */
    public static function handle(array $u): string
    {
        [, $uid] = self::store($u);
        return self::isNet($u) ? Passkeys::userHandle('net', $uid, Network::appKey()) : Passkeys::userHandle('site', $uid, app()->key());
    }

    /** Zeile des Kontos in der zuständigen Datenbank (Schatten-Konten: Netzwerk-Konto) */
    public static function row(array $u): ?array
    {
        [$db, $uid] = self::store($u);
        return $db->fetch('SELECT * FROM users WHERE id = ?', [$uid]);
    }

    /** @return array{totp:bool, passkeys:int, here:int, recovery:int} */
    public static function factors(array $row, ?Database $db = null): array
    {
        // Zeile aus der zuständigen Datenbank (Netzwerk-Konten: Zeile der Netzwerk-Datenbank, kein Schatten-Konto)
        $uid = (int) $row['id'];
        if ($db === null) [$db, $uid] = self::store($row);
        return ['totp' => (bool) (int) ($row['totp_enabled'] ?? 0), 'passkeys' => Passkeys::count($db, $uid),
            'here' => Passkeys::count($db, $uid, Passkeys::rpId()),
            'recovery' => count(json_decode((string) ($row['totp_recovery'] ?? ''), true) ?: [])];
    }

    /** Hat das Konto überhaupt einen zweiten Faktor (→ nach dem Passwort abfragen)? */
    public static function hasFactor(array $row, ?Database $db = null): bool
    {
        $f = self::factors($row, $db);
        return $f['totp'] || $f['passkeys'] > 0;
    }

    /** Pflicht erfüllt? ($req: Standard = Pflicht des Kontos) */
    public static function satisfied(array $row, ?string $req = null, ?Database $db = null): bool
    {
        $req ??= self::requirement($row);
        if ($req === null) return true;
        $pol = self::allowed($row);
        $f = self::factors($row, $db);
        $pk = $pol['passkey'] && $f['passkeys'] > 0;
        return $req === 'passkey' ? $pk : ($pk || ($pol['totp'] && $f['totp']));
    }

    /** Netzwerk-Konto darf sich auf Websites anmelden (SSO, direkt): immer TOTP oder Passkey – bzw. nur Passkey */
    public static function networkStrong(array $n): bool
    {
        return self::satisfied($n, self::netPolicy()['passkey_only'] ? 'passkey' : 'any', Network::db());
    }

    /**
     * Verfahren für den zweiten Schritt nach dem Passwort, bevorzugtes zuerst (zuletzt verwendet, sonst Passkey).
     * Abgeschaltete Verfahren entfallen, solange ein erlaubtes übrig bleibt. @return list<'passkey'|'totp'>
     */
    public static function loginMethods(array $row, ?Database $db = null): array
    {
        $f = self::factors($row, $db);
        $pol = self::allowed($row);
        if (self::requirement($row) === 'passkey') $pol['totp'] = false;   // „nur Passkey“: App-Code nur noch als Rückfall
        $have = array_keys(array_filter(['passkey' => $f['here'] > 0, 'totp' => $f['totp']]));
        $ok = array_values(array_filter($have, fn($m) => $pol[$m]));
        $list = $ok ?: $have;
        if (($row['mfa_last'] ?? '') === 'totp' && count($list) === 2) $list = ['totp', 'passkey'];
        return $list;
    }

    public static function remember(Database $db, int $uid, string $method): void
    {
        try {
            $db->update('users', ['mfa_last' => $method], 'id = :id', ['id' => $uid]);
        } catch (\Throwable) {
        }
    }

    // ================================================================= Pflicht-Einrichtung nach der Anmeldung

    /** Frist für die Einrichtung (0 = sofort) */
    public static function deadline(array $row): int
    {
        if (self::isNet($row)) return 0;
        $pol = self::policy();
        if ($pol['grace_days'] < 1) return 0;
        $base = max((int) ($pol['since'][(string) $row['role']] ?? 0), (int) strtotime((string) ($row['created_at'] ?? '')) ?: 0);
        return $base > 0 ? $base + $pol['grace_days'] * 86400 : 0;
    }

    /** Nach jeder vollständigen Anmeldung (Auth::login): Pflicht offen? → Einrichtung verlangen bzw. daran erinnern */
    public static function afterLogin(int $uid): void
    {
        $s = app()->session;
        $s->forget('2fa_setup');
        $s->forget('2fa_nagged');
        $row = app()->db->fetch('SELECT * FROM users WHERE id = ?', [$uid]);
        // Schatten-Konten: Regeln der Netzwerk-Konten gelten bereits bei der Anmeldung (Network::loginShadow)
        if (!$row || (!empty($row['network_uid']) && !Network::isNetworkSite())) return;
        $req = self::requirement($row);
        if ($req !== null && !self::satisfied($row, $req, app()->db)) {
            $s->set('2fa_setup', ['req' => $req, 'until' => self::deadline($row)]);
        }
    }

    /** Einrichtung jetzt erzwungen (Frist abgelaufen oder ohne Frist)? */
    public static function due(mixed $need): bool
    {
        return !is_array($need) || (int) ($need['until'] ?? 0) <= time();
    }

    /** Wohin zur Einrichtung? Nur App erlaubt → bisherige TOTP-Seite, sonst Auswahl */
    public static function setupPath(array $u): string
    {
        $pol = self::allowed($u);
        $req = self::requirement($u) ?? 'any';
        return $req === 'any' && !$pol['passkey'] ? '/admin/account/2fa' : '/admin/account/2fa/choose';
    }

    /** Kürzlich angemeldet bzw. Passwort bestätigt (Passkeys verwalten ohne erneute Eingabe) */
    public static function recentAuth(): bool
    {
        $s = app()->session;
        return time() - max((int) $s->get('login_at', 0), (int) $s->get('reauth_at', 0)) < self::REAUTH;
    }

    // ================================================================= Speichern (Benutzer & Rollen, Netzwerk-Übersicht)

    /** @return string|null Fehlertext */
    public static function savePolicy(array $in, array $roleKeys): ?string
    {
        $old = self::policy();
        $totp = !empty($in['totp']);
        $passkey = !empty($in['passkey']);
        $roles = [];
        foreach ((array) ($in['req'] ?? []) as $k => $v) {
            if (in_array($k, $roleKeys, true) && $k !== 'network' && in_array($v, ['any', 'passkey'], true)) $roles[$k] = $v;
        }
        if ($roles && !$totp && !$passkey) return __('Wenn eine Rolle einen zweiten Faktor verlangt, muss mindestens ein Verfahren erlaubt sein.');
        if (in_array('passkey', $roles, true) && !$passkey) return __('„Nur Passkey“ setzt voraus, dass Passkeys erlaubt sind.');
        // Beginn der Pflicht je Rolle (für die Übergangsfrist): bleibt bestehen, solange die Rolle verpflichtet bleibt
        $since = [];
        foreach ($roles as $k => $v) $since[$k] = isset($old['roles'][$k]) && isset($old['since'][$k]) ? $old['since'][$k] : time();
        app()->settings->set(self::KEY, ['totp' => $totp, 'passkey' => $passkey, 'passwordless' => $passkey && !empty($in['passwordless']),
            'roles' => $roles, 'grace_days' => max(0, min(365, (int) ($in['grace_days'] ?? 0))), 'since' => $since]);
        // Ältere Einstellung weiter pflegen (Rollen mit Pflicht)
        app()->settings->set('sys.twofa_roles', array_keys($roles));
        return null;
    }

    public static function saveNetPolicy(array $in): ?string
    {
        $totp = !empty($in['totp']);
        $passkey = !empty($in['passkey']);
        if (!$totp && !$passkey) return __('Mindestens ein Verfahren muss erlaubt sein – Netzwerk-Konten melden sich immer mit zweitem Faktor an.');
        app()->settings->set(self::NET_KEY, ['totp' => $totp, 'passkey' => $passkey, 'passwordless' => $passkey && !empty($in['passwordless']),
            'passkey_only' => $passkey && !empty($in['passkey_only'])]);
        self::$net = null;
        return null;
    }
}
