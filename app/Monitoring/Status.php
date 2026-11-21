<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Monitoring;

use Core\Backup\Offsite;
use Core\Extensions;
use Core\Health;
use Core\Integrity;
use Core\RateLimiter;

/**
 * Lesender Status-Endpunkt für eine Überwachung (Protokoll status-v1, Core\Monitoring\Signature) – Standard aus.
 *
 *   GET /api/monitoring/status   nur mit gültig signierter Anfrage eines eingetragenen Schlüssels
 *
 * Antwort: Version von System, PHP und Kit, Erweiterungen mit Versionen, Ampel der Prüfungen (Core\Health) mit Meldungen und
 * Kategorie (technical | setup | editorial – Ampel und Zähler nur aus technischen), Anzahl der Einträge im Fehlerprotokoll
 * (ErrorLog, nur Zahlen), letzte Sicherung, Integrität, Zeitstempel – keine Inhalte, keine Personendaten, keine Geheimnisse.
 * Einstellung sys.monitoring (Grundeinstellungen → Überwachung, Recht system.manage) oder Website-Konfiguration
 * 'monitoring' => ['enabled' => true, 'keys' => ['ed25519:…']].
 */
final class Status
{
    public const KEY = 'sys.monitoring';
    private const CACHE_TTL = 60;

    /** @return array{enabled: bool, keys: list<array{label: string, key: string, added: int}>, from_config: bool} */
    public static function settings(): array
    {
        $cfg = app()->config->get('monitoring');
        $fromConfig = is_array($cfg) && $cfg;
        $raw = $fromConfig ? $cfg : app()->settings->get(self::KEY);
        $raw = is_array($raw) ? $raw : [];
        $keys = [];
        foreach ((array) ($raw['keys'] ?? []) as $k) {
            $k = is_string($k) ? ['key' => $k] : (array) $k;
            if (Signature::parseKey((string) ($k['key'] ?? '')) === null) continue;
            $keys[] = ['label' => mb_substr((string) ($k['label'] ?? ''), 0, 60), 'key' => trim((string) $k['key']), 'added' => (int) ($k['added'] ?? 0)];
        }
        return ['enabled' => !empty($raw['enabled']), 'keys' => $keys, 'from_config' => $fromConfig];
    }

    /** Speichern: Zeilen „Bezeichnung ed25519:…“ bzw. nur „ed25519:…“. @return ?string Fehlertext */
    public static function save(bool $enabled, string $keysText): ?string
    {
        $old = [];
        foreach (self::settings()['keys'] as $k) $old[$k['key']] = $k;
        $keys = [];
        foreach (preg_split('~\R~', $keysText) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (!preg_match('~^(.*?)\s*(ed25519:[A-Za-z0-9_-]+)$~', $line, $m) || Signature::parseKey($m[2]) === null) {
                return __('Ungültiger Schlüssel: „{line}“ – erwartet wird „ed25519:…“ (optional mit Bezeichnung davor).', ['line' => mb_strimwidth($line, 0, 60, '…')]);
            }
            $keys[] = ['label' => mb_substr(trim($m[1]), 0, 60), 'key' => $m[2], 'added' => $old[$m[2]]['added'] ?? time()];
        }
        if ($enabled && !$keys) return __('Zum Einschalten brauchen Sie mindestens einen Schlüssel des Überwachungsdienstes.');
        app()->settings->set(self::KEY, ['enabled' => $enabled, 'keys' => array_slice($keys, 0, 10)]);
        return null;
    }

    /** Anfrage prüfen → Fehlergrund oder null */
    public static function check(\Core\Http\Request $r, ?int $now = null): ?string
    {
        $s = self::settings();
        if (!$s['enabled'] || !$s['keys']) return 'off';
        $h = [];
        foreach (['x-status-key', 'x-status-timestamp', 'x-status-nonce', 'x-status-signature'] as $k) {
            $h[$k] = (string) ($r->server['HTTP_' . strtoupper(str_replace('-', '_', $k))] ?? '');
        }
        $keys = array_values(array_filter(array_map(fn($k) => Signature::parseKey($k['key']), $s['keys'])));
        $uri = (string) ($r->server['REQUEST_URI'] ?? '/');
        $limiter = new RateLimiter(app()->db);
        // Host normalisiert wie beim Dienst (Signature::hostOf) und nur, wenn er zu dieser Installation gehört – eine für eine fremde
        // Installation signierte Anfrage (gleicher Hub-Schlüssel) lässt sich hier nicht mit deren Host-Header wiederholen
        $host = Signature::normalizeHost($r->host(), $r->isSecure());
        if (!\Core\InstanceInfo::known($host) && !\Core\InstanceInfo::known((string) preg_replace('~:\d+$~', '', $host))) return 'host';
        return Signature::verify($h, (string) ($r->server['REQUEST_METHOD'] ?? 'GET'), $host, $uri, $keys,
            function (string $kid, string $nonce) use ($limiter): bool {
                $key = 'mon-nonce:' . $kid . ':' . $nonce;
                if ($limiter->count($key, Signature::NONCE_TTL) > 0) return true;
                $limiter->hit($key);
                return false;
            }, $now);
    }

    /**
     * Status nach status-v1 (Pflichtfelder: protocol, system, system_version, php, generated_at, health, nonce). nonce ist Pflicht:
     * das Echo des X-Status-Nonce der Anfrage – der Dienst verwirft Antworten ohne passendes nonce (keine alte, zwischengespeicherte Antwort).
     */
    public static function collect(string $nonce = ''): array
    {
        $file = site()->storage('cache/monitoring-status.json');
        $base = null;
        if (is_file($file) && filemtime($file) > time() - self::CACHE_TTL) $base = json_decode((string) @file_get_contents($file), true);
        if (!is_array($base)) {
            $base = self::build();
            @mkdir(dirname($file), 0770, true);
            @file_put_contents($file, json_encode($base, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
        return $base + ['nonce' => $nonce];
    }

    private static function iso(?int $t): ?string
    {
        return $t ? date('c', $t) : null;
    }

    private static function build(): array
    {
        // Meldungen mit Kategorie (Core\Health): Ampel und Zähler nur aus technischen Meldungen, Einrichtung/Redaktion als notices
        $msgs = [];
        $err = $warn = $notices = 0;
        try {
            foreach (Health::rows() as $r) {
                if ($r['ok'] === true) continue;
                $level = $r['ok'] === false ? 'error' : 'warning';
                if ($r['category'] === Health::TECHNICAL) $level === 'error' ? $err++ : $warn++;
                else $notices++;
                $msgs[] = ['level' => $level, 'category' => $r['category'], 'text' => mb_strimwidth($r['label'], 0, 200, '…')];
            }
        } catch (\Throwable $e) {
            error_log('[monitoring] ' . $e->getMessage());
            $err++;
            $msgs[] = ['level' => 'error', 'category' => Health::TECHNICAL, 'text' => 'health check failed'];
        }
        // Technische Meldungen zuerst (höchstens 50 – Einrichtung und Redaktion fallen zuerst weg), Fehler vor Hinweisen
        $rank = fn(array $m) => ($m['category'] === Health::TECHNICAL ? 0 : 2) + ($m['level'] === 'error' ? 0 : 1);
        usort($msgs, fn($a, $b) => $rank($a) <=> $rank($b));
        $components = [];
        foreach (Extensions::active() as $name => $x) {
            $components[] = ['type' => 'extension', 'name' => (string) $name, 'version' => (string) ($x->manifest['version'] ?? ''), 'active' => true];
        }
        // Sicherungen: neueste lokale Datei dieser Website bzw. der Installation, letzte Übertragung außer Haus
        $local = 0;
        foreach (Offsite::latestLocal() as $prefix => $path) {
            if ($prefix === site()->key || $prefix === 'install') $local = max($local, (int) @filemtime($path));
        }
        $off = 0;
        foreach (Offsite::state() as $s) $off = max($off, (int) ($s['ok'] ?? 0));
        $ig = ['db' => Integrity::lastDb()] + (site()->isDefault() ? ['files' => Integrity::lastFiles()] : []);
        $igStatus = 'ok';
        $igAt = null;
        foreach ($ig as $res) {
            if ($res === null) { $igStatus = $igStatus === 'error' ? 'error' : 'unknown'; continue; }
            $igAt = max((int) $igAt, (int) $res['time']);
            $levels = array_column((array) $res['findings'], 'level');
            if (in_array('error', $levels, true)) $igStatus = 'error';
            elseif (in_array('warning', $levels, true) && $igStatus === 'ok') $igStatus = 'warning';
        }
        return [
            'protocol' => Signature::PROTOCOL,
            'system' => 'studio',
            'system_version' => CMS_VERSION,
            'php' => PHP_VERSION,
            'generated_at' => date('c'),
            'site' => ['key' => site()->key, 'url' => site_url()],
            'theme' => ['name' => app()->theme->name, 'version' => (string) (app()->theme->def['version'] ?? '')],
            'components' => $components,
            'health' => ['status' => $err ? 'red' : ($warn ? 'yellow' : 'green'), 'errors' => $err, 'warnings' => $warn, 'notices' => $notices,
                'messages' => array_slice($msgs, 0, 50)],
            'log' => ErrorLog::summary(),
            'backup' => ['last_at' => self::iso($local ?: null), 'offsite_at' => self::iso($off ?: null)],
            'integrity' => ['status' => $igStatus, 'checked_at' => self::iso($igAt ?: null)],
            'ssl' => ['expires_at' => null],   // ermittelt der Dienst selbst aus der TLS-Verbindung
        ];
    }
}
