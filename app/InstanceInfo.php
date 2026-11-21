<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Monitoring\Status;
use Core\Network\Network;
use Core\Oidc\Providers;

/**
 * Öffentliche Selbstauskunft der Installation (Protokoll klxm-instance-v1) – damit ein Agentur-Hub die richtigen Adressen für
 * Anmeldung (OpenID Connect) und Überwachung selbst ermitteln kann, statt sie abzutippen.
 *
 *   GET /.well-known/klxm-studio   JSON, auf jedem Host der Installation gleich; 404, solange weder OIDC (Netzwerk-Website)
 *                                  noch die Überwachung (irgendeine Website) eingerichtet ist.
 *
 * Inhalt: Name der Netzwerk-Website, Adressen für den Anbieter (redirect_uri, initiate_login_uri, post_logout_redirect_uri –
 * mit der Netzwerk-Domain, falls gesetzt, Core\NetworkHost) und je Website mit eingeschalteter Überwachung die Status-Adresse.
 * Keine Versionen, keine Personendaten, keine Inhalte, keine Schlüssel. Spezifikation: docs/agentur-hub/instance-v1.md.
 */
final class InstanceInfo
{
    public const SPEC = 'klxm-instance-v1';
    public const PATH = '/.well-known/klxm-studio';
    private const CACHE_TTL = 60;

    /** Auskunft oder null (nichts eingerichtet → 404). Zwischengespeichert für 60 s (öffnet sonst die Datenbanken aller Websites). */
    public static function get(bool $fresh = false): ?array
    {
        $file = ROOT . '/storage/cache/klxm-instance.json';
        if (!$fresh && is_file($file) && filemtime($file) > time() - self::CACHE_TTL) {
            $d = json_decode((string) @file_get_contents($file), true);
            if (is_array($d)) return $d['info'] ?? null;
        }
        $info = self::build();
        @mkdir(dirname($file), 0770, true);
        @file_put_contents($file, json_encode(['info' => $info], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $info;
    }

    /** Zwischenspeicher verwerfen (nach Änderungen an OIDC, Überwachung, Netzwerk-Domain) */
    public static function flush(): void
    {
        @unlink(ROOT . '/storage/cache/klxm-instance.json');
    }

    public static function build(): ?array
    {
        $net = Network::siteKey();
        $oidc = false;
        try {
            foreach (Providers::all($net) as $p) if ($p['issuer'] !== '' || $p['client_id'] !== '') $oidc = true;
        } catch (\Throwable $e) {
            error_log('[instance] oidc: ' . $e->getMessage());
        }
        $sites = [];
        foreach (array_keys(Sites::all()) as $key) {
            try {
                if (!self::monitoringOn($key)) continue;
                $entry = ['key' => $key, 'name' => self::name($key), 'url' => Network::siteUrl($key),
                    'status_url' => Network::siteLink($key, '/api/monitoring/status'), 'network' => $key === $net];
                // Netzwerk-Website zuerst (ein Hub mit der Netzwerk-Domain als Basis-Adresse nimmt ohne Treffer den ersten Eintrag)
                if ($key === $net) array_unshift($sites, $entry); else $sites[] = $entry;
            } catch (\Throwable $e) {
                error_log('[instance] monitoring ' . $key . ': ' . $e->getMessage());
            }
        }
        if (!$oidc && !$sites) return null;
        $mine = $net === site()->key;
        return [
            'spec' => self::SPEC,
            'system' => 'studio',
            'name' => self::name($net),
            'network' => Sites::multi(),
            'login' => [
                'redirect_uri' => $mine ? Providers::redirectUri() : Network::siteLink($net, '/admin/login/oidc/callback'),
                'initiate_login_uri' => $mine ? Providers::initiateUri() : Network::siteLink($net, '/admin/login/oidc'),
                'post_logout_redirect_uri' => $mine ? admin_url_abs('/admin/login') : Network::siteLink($net, '/admin/login'),
                'configured' => $oidc,
            ],
            // Einzel-Installation: eine Status-Adresse; Netzwerk: Liste der Websites mit eingeschalteter Überwachung
            'monitoring' => Sites::multi() ? ['sites' => $sites] : ['status_url' => $sites[0]['status_url'] ?? null, 'sites' => $sites],
        ];
    }

    /** Überwachung auf dieser Website eingeschaltet (mit Schlüssel)? */
    private static function monitoringOn(string $key): bool
    {
        if ($key === site()->key) {
            $s = Status::settings();
            return $s['enabled'] && $s['keys'];
        }
        $cfg = Network::config($key)->get('monitoring');
        $raw = is_array($cfg) && $cfg ? $cfg : self::remoteSetting($key, Status::KEY);
        return is_array($raw) && !empty($raw['enabled']) && !empty($raw['keys']);
    }

    /** Name: eigener Name der Website (config 'label'), sonst „Name der Website“ aus den Einstellungen, sonst Hauptadresse */
    private static function name(string $key): string
    {
        $label = trim((string) (Sites::all()[$key]['label'] ?? ''));
        if ($label !== '') return $label;
        $sn = $key === site()->key ? setting('site_name', '') : self::remoteSetting($key, 'site_name');
        $sn = trim(strip_tags(is_string($sn) ? $sn : ''));
        return $sn !== '' ? $sn : (new Site($key, Sites::all()[$key] ?? []))->label();
    }

    /** Einstellung einer anderen Website direkt aus ihrer Datenbank (nur lesen; fehlt sie: null) */
    private static function remoteSetting(string $key, string $skey): mixed
    {
        try {
            $cfg = (array) Network::config($key)->get('db');
            if (($cfg['driver'] ?? 'sqlite') === 'sqlite' && !is_file((string) ($cfg['path'] ?? ''))) return null;
            $db = $key === Network::siteKey() ? Network::db() : new Database($cfg);
            $v = $db->fetchValue('SELECT value_json FROM settings WHERE skey = ?', [$skey]);
            return $v === null ? null : json_decode((string) $v, true);
        } catch (\Throwable $e) {
            error_log('[instance] ' . $key . ': ' . $e->getMessage());
            return null;
        }
    }
}
