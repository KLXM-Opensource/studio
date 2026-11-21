<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Oidc;

use Core\Network\Network;
use Core\Permissions;
use Core\Settings;

/**
 * Anbieter für die Anmeldung per OpenID Connect – je Website, Standard aus.
 *
 *  - Grundeinstellung sys.oidc (Benutzer & Rollen → Anmeldung & Sicherheit): ['main' => [...]] – das Datenmodell trägt mehrere
 *    Anbieter (Schlüssel a–z0–9), die Oberfläche pflegt einen („main“). Das Client-Geheimnis liegt verschlüsselt
 *    (Core\Settings::encrypt, Schlüssel aus dem app_key) in client_secret_enc.
 *  - Website-Konfiguration 'oidc' => ['main' => [... 'client_secret' => '…']] ersetzt die Einstellung vollständig (Deploy, Tests).
 *  - Rolle 'network' gibt es nur auf der Netzwerk-Website: Konten werden Netzwerk-Konten, andere Websites des Netzwerks zeigen den
 *    Knopf ebenfalls und leiten zur Anmeldung auf die Netzwerk-Website (danach Einmal-Anmeldung wie „Öffnen“).
 */
final class Providers
{
    public const KEY = 'sys.oidc';
    public const DEFAULTS = [
        'enabled' => false,
        'label' => '',                  // Beschriftung des Knopfs (leer = „Mit {name} anmelden“)
        'name' => '',                   // Name des Anbieters (Protokoll, Hinweise)
        'issuer' => '',
        'client_id' => '',
        'client_secret' => '',          // nur Laufzeit (aus client_secret_enc bzw. Konfiguration)
        'auth' => 'client_secret_basic', // client_secret_basic | client_secret_post
        'scopes' => 'openid email profile',
        'role' => 'auto',               // auto | network | Rollen-Schlüssel der Website
        'account_label' => '',          // Kennzeichnung der Konten (leer = „Agentur-Konto“)
        'jit' => true,                  // Konto bei der ersten Anmeldung anlegen
        'mfa' => 'amr',                 // amr = Token muss einen zweiten Faktor belegen | trust = Anbieter erzwingt ihn selbst
        'claim' => '',                  // optional: Claim, der einen der Werte enthalten muss (z. B. roles)
        'claim_values' => [],
        'domains' => [],                // optional: erlaubte Domains der E-Mail-Adresse
        'logout' => false,              // beim Abmelden auch beim Anbieter abmelden (RP-initiated logout)
    ];

    /** Alle Anbieter dieser Website (mit Standardwerten, Geheimnis entschlüsselt) @return array<string, array> */
    public static function all(?string $site = null): array
    {
        $site ??= site()->key;
        $cfg = $site === site()->key ? app()->config->get('oidc') : Network::config($site)->get('oidc');
        if (is_array($cfg) && $cfg) {
            $raw = $cfg;
            $fromConfig = true;
        } else {
            $raw = $site === site()->key ? app()->settings->get(self::KEY) : self::remoteSetting($site);
            $fromConfig = false;
        }
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $id => $p) {
            if (!is_array($p) || !preg_match('~^[a-z0-9]{1,20}$~', (string) $id)) continue;
            $p = array_replace(self::DEFAULTS, array_intersect_key($p, self::DEFAULTS + ['client_secret_enc' => 1]));
            if (!$fromConfig) {
                $p['client_secret'] = $site === site()->key ? Settings::decrypt((string) ($p['client_secret_enc'] ?? '')) : '';
            }
            unset($p['client_secret_enc']);
            $p['id'] = (string) $id;
            $p['site'] = $site;
            $p['from_config'] = $fromConfig;
            $p['issuer'] = rtrim(trim((string) $p['issuer']), '/');
            $p['claim_values'] = array_values(array_filter(array_map('trim', (array) $p['claim_values']), fn($v) => $v !== ''));
            $p['domains'] = array_values(array_filter(array_map(fn($d) => strtolower(trim((string) $d)), (array) $p['domains']), fn($v) => $v !== ''));
            $p['role'] = self::effectiveRole((string) $p['role'], $site);
            $out[(string) $id] = $p;
        }
        return $out;
    }

    /** Einstellung einer anderen Website (Netzwerk-Website) – ohne deren Geheimnisse */
    private static function remoteSetting(string $site): mixed
    {
        try {
            if ($site !== Network::siteKey()) return null;
            return json_decode((string) Network::db()->fetchValue('SELECT value_json FROM settings WHERE skey = ?', [self::KEY]), true);
        } catch (\Throwable $e) {
            error_log('[oidc] ' . $e->getMessage());
            return null;
        }
    }

    /** auto → Netzwerk-Konto auf der Netzwerk-Website eines Netzwerks (mehrere Websites), sonst Administration */
    public static function effectiveRole(string $role, ?string $site = null): string
    {
        $site ??= site()->key;
        $isNet = $site === Network::siteKey();
        if ($role === 'auto') return $isNet && \Core\Sites::multi() ? 'network' : 'admin';
        if ($role === 'network') return $isNet ? 'network' : 'admin';
        return Permissions::role($role) ? $role : 'editor';
    }

    public static function ready(array $p): bool
    {
        return !empty($p['enabled']) && $p['issuer'] !== '' && $p['client_id'] !== '' && preg_match('~^https?://~', $p['issuer']);
    }

    /**
     * Anbieter für den Knopf auf der Anmeldeseite dieser Website: eigener (eingeschaltet) oder – auf anderen Websites eines
     * Netzwerks – der der Netzwerk-Website mit Rolle „network“. @return ?array (mit 'site' = zuständige Website)
     */
    public static function forLogin(): ?array
    {
        foreach (self::all() as $p) if (self::ready($p)) return $p;
        if (!Network::isNetworkSite() && \Core\Sites::multi()) {
            try {
                foreach (self::all(Network::siteKey()) as $p) {
                    if (!empty($p['enabled']) && $p['issuer'] !== '' && $p['client_id'] !== '' && $p['role'] === 'network') return $p;
                }
            } catch (\Throwable $e) {
                error_log('[oidc] ' . $e->getMessage());
            }
        }
        return null;
    }

    public static function get(string $id): ?array
    {
        $p = self::all()[$id] ?? null;
        return $p && self::ready($p) ? $p : null;
    }

    /** Beschriftung des Knopfs */
    public static function buttonLabel(array $p): string
    {
        $l = trim((string) $p['label']);
        if ($l !== '') return $l;
        $n = trim((string) $p['name']) ?: (string) parse_url((string) $p['issuer'], PHP_URL_HOST);
        return __('Mit {name} anmelden', ['name' => $n]);
    }

    public static function accountLabel(?array $p): string
    {
        $l = trim((string) ($p['account_label'] ?? ''));
        return $l !== '' ? $l : __('Agentur-Konto');
    }

    /** Kennzeichnung für ein Konto (users.idp) – Anbieter der Website bzw. der Netzwerk-Website */
    public static function labelFor(string $idp): string
    {
        static $cache = [];
        if (!isset($cache[$idp])) {
            $p = self::all()[$idp] ?? null;
            if (!$p && !Network::isNetworkSite()) {
                try { $p = self::all(Network::siteKey())[$idp] ?? null; } catch (\Throwable) {}
            }
            $cache[$idp] = self::accountLabel($p);
        }
        return $cache[$idp];
    }

    /** Rückkehr-Adresse (redirect_uri) dieser Website – genau so beim Anbieter eintragen */
    public static function redirectUri(): string
    {
        return absolute_url('/admin/login/oidc/callback');
    }

    /** Einstieg für vom Anbieter ausgelöste Anmeldung (OIDC „initiate_login_uri“) */
    public static function initiateUri(): string
    {
        return absolute_url('/admin/login/oidc');
    }

    /**
     * Speichern aus der Verwaltung. Geheimnis: leer = unverändert, „-“ = löschen. @return ?string Fehlertext
     */
    public static function save(array $in, string $id = 'main'): ?string
    {
        $old = app()->settings->get(self::KEY);
        $old = is_array($old) ? $old : [];
        $prev = (array) ($old[$id] ?? []);
        $issuer = rtrim(trim((string) ($in['issuer'] ?? '')), '/');
        $enabled = !empty($in['enabled']);
        if ($issuer !== '' && !preg_match('~^https://[^\s/?#]+(/[^\s?#]*)?$~', $issuer) && !(environment() === 'development' && preg_match('~^http://[^\s/?#]+(/[^\s?#]*)?$~', $issuer))) {
            return __('Die Issuer-Adresse muss mit https:// beginnen (ohne ? und #).');
        }
        $role = (string) ($in['role'] ?? 'auto');
        if (!in_array($role, ['auto', 'network'], true) && (!Permissions::role($role) || $role === 'network')) $role = 'auto';
        $secret = (string) ($in['client_secret'] ?? '');
        $enc = $secret === '-' ? '' : ($secret !== '' ? Settings::encrypt($secret) : (string) ($prev['client_secret_enc'] ?? ''));
        $list = fn(string $k) => array_values(array_filter(array_map('trim', preg_split('~[,\n]+~', (string) ($in[$k] ?? '')) ?: []), fn($v) => $v !== ''));
        $p = [
            'enabled' => $enabled, 'label' => mb_substr(trim((string) ($in['label'] ?? '')), 0, 60), 'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 60),
            'issuer' => $issuer, 'client_id' => mb_substr(trim((string) ($in['client_id'] ?? '')), 0, 191), 'client_secret_enc' => $enc,
            'auth' => ($in['auth'] ?? '') === 'client_secret_post' ? 'client_secret_post' : 'client_secret_basic',
            'scopes' => 'openid email profile', 'role' => $role, 'account_label' => mb_substr(trim((string) ($in['account_label'] ?? '')), 0, 40),
            'jit' => !empty($in['jit']), 'mfa' => ($in['mfa'] ?? '') === 'trust' ? 'trust' : 'amr',
            'claim' => preg_replace('~[^\w.:-]~', '', (string) ($in['claim'] ?? '')), 'claim_values' => $list('claim_values'),
            'domains' => array_map('strtolower', $list('domains')), 'logout' => !empty($in['logout']),
        ];
        if ($enabled && ($p['issuer'] === '' || $p['client_id'] === '')) return __('Zum Einschalten brauchen Sie Issuer-Adresse und Client-ID.');
        $old[$id] = $p;
        app()->settings->set(self::KEY, $old);
        Client::forgetCache($issuer);
        return null;
    }
}
