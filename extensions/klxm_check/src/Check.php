<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

use Core\Features;

/**
 * KLXM Check – zentrale Angaben: Funktionen, Konfiguration, Grenzen, Zwischenspeicher, anonyme Zähler, Ausgabe des Werkzeugs.
 *
 * Konfiguration (optional) in config/sites/{key}.php bzw. config/config.local.php:
 *   'klxm_check' => [
 *       'page' => true,                            // eigenständige Seite ein/aus (sonst Schalter unter Verwaltung → KLXM Check)
 *       'path' => '/check',                        // Adresse der eigenständigen Seite
 *       'limits' => ['minute' => 40, 'day' => 400, 'site_day' => 5000],   // Anfragen je Besucher/Minute, je Besucher/Tag, je Website/Tag
 *       'cache_ttl' => 300,                        // Sekunden, die ein Ergebnis je Domain wiederverwendet wird
 *       'smtp' => true,                            // STARTTLS auf Port 25 beim ersten Mailserver versuchen
 *       'resolver' => '',                          // Resolver nur für die DNSSEC-Angabe (Standard: /etc/resolv.conf)
 *   ]
 */
final class Check
{
    public const NAME = 'klxm_check';
    public const VERSION = '1.0.0';
    /** Funktion: Werkzeug (Block, API) – mit der Erweiterung eingeschaltet */
    public const FEATURE = 'check';
    /** Einstellung der Website: eigenständige Seite unter /check (Standard aus; Verwaltung → KLXM Check) */
    public const PAGE_SETTING = 'ext.klxm_check.page';
    public const USER_AGENT = 'KLXM-Check/1.0 (+https://klxm.de/check/)';
    /** Abschnitte der Domain-Analyse */
    public const SECTIONS = ['spf', 'dmarc', 'dkim', 'mail', 'hosting', 'web'];
    /** Reiter: Schlüssel (wie im alten Werkzeug, ?tab=…) */
    public const TABS = ['checker', 'ssl-checker', 'spf-generator', 'dmarc-generator'];

    private static ?\Core\Extension $ext = null;

    public static function boot(\Core\Extension $x): void
    {
        self::$ext = $x;
    }

    public static function config(): array
    {
        try {
            return (array) app()->config->get(self::NAME, []);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function on(): bool
    {
        return Features::on(self::FEATURE, false);
    }

    /** Eigenständige Seite aktiv? Konfiguration 'klxm_check' => ['page' => true|false] geht vor, sonst Schalter der Verwaltung */
    public static function pageOn(): bool
    {
        if (!self::on()) return false;
        return self::pageLock() ?? self::pageSetting();
    }

    public static function pageSetting(): bool
    {
        try {
            return (bool) app()->settings->get(self::PAGE_SETTING, false);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Wert aus der Konfiguration (sperrt den Schalter) oder null */
    public static function pageLock(): ?bool
    {
        $c = self::config();
        return array_key_exists('page', $c) ? (bool) $c['page'] : null;
    }

    public static function path(): string
    {
        $p = '/' . trim((string) (self::config()['path'] ?? '/check'), '/');
        return preg_match('~^/[a-z0-9][a-z0-9/_-]{0,60}$~', $p) && !str_starts_with($p, '/admin') && !str_starts_with($p, '/api') ? $p : '/check';
    }

    /** @return array{minute: int, day: int, site_day: int} */
    public static function limits(): array
    {
        $l = (array) (self::config()['limits'] ?? []);
        return ['minute' => max(6, (int) ($l['minute'] ?? 40)), 'day' => max(20, (int) ($l['day'] ?? 400)), 'site_day' => max(100, (int) ($l['site_day'] ?? 5000))];
    }

    public static function ttl(): int
    {
        return max(0, min(3600, (int) (self::config()['cache_ttl'] ?? 300)));
    }

    public static function smtpProbe(): bool
    {
        return (bool) (self::config()['smtp'] ?? true);
    }

    /** Name für EHLO: Host der Website (kein Besucher-Wert) */
    public static function ehloName(): string
    {
        $h = strtolower((string) parse_url((string) (function_exists('site_url') ? site_url() : ''), PHP_URL_HOST));
        return preg_match('~^[a-z0-9.-]+\.[a-z]{2,}$~', $h) && !str_ends_with($h, '.localhost') ? $h : 'klxm-check.invalid';
    }

    public static function asset(string $path): string
    {
        return self::$ext ? self::$ext->asset($path) : base_path() . '/extensions/' . self::NAME . '/' . ltrim($path, '/');
    }

    // ------------------------------------------------------------------ Zwischenspeicher je Domain (Ergebnisse, kurz)

    private static function cacheDir(): string
    {
        return ROOT . '/storage/cache/klxm-check';
    }

    /** Schlüssel ohne Klartext (HMAC) – der Dateiname verrät die Domain nicht */
    private static function cacheFile(string $key): string
    {
        return self::cacheDir() . '/' . substr(hash_hmac('sha256', $key, (string) app()->config->get('app_key', 'klxm-check')), 0, 40) . '.json';
    }

    public static function cacheGet(string $key): ?array
    {
        $ttl = self::ttl();
        if ($ttl === 0) return null;
        $f = self::cacheFile($key);
        if (!is_file($f) || filemtime($f) < time() - $ttl) return null;
        $d = json_decode((string) @file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }

    public static function cachePut(string $key, array $data): void
    {
        if (self::ttl() === 0) return;
        $dir = self::cacheDir();
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        @file_put_contents(self::cacheFile($key), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        // Gelegentlich aufräumen: abgelaufene Ergebnisse löschen (enthalten Domainnamen – nicht länger als nötig aufheben)
        if (random_int(1, 25) === 1) {
            foreach (glob($dir . '/*.json') ?: [] as $old) {
                if (filemtime($old) < time() - max(600, self::ttl() * 2)) @unlink($old);
            }
        }
    }

    // ------------------------------------------------------------------ Anonyme Zähler (ohne Domains, ohne IP)

    private static function statsFile(): string
    {
        return site()->storage('klxm-check/stats.json');
    }

    public static function count(string $what): void
    {
        $f = self::statsFile();
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0770, true);
        $h = @fopen($f, 'c+');
        if (!$h) return;
        try {
            if (!flock($h, LOCK_EX)) return;
            $d = json_decode((string) stream_get_contents($h), true) ?: [];
            $day = date('Y-m-d');
            $d[$day][$what] = (int) ($d[$day][$what] ?? 0) + 1;
            ksort($d);
            $d = array_slice($d, -60, null, true);
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, (string) json_encode($d));
            fflush($h);
            flock($h, LOCK_UN);
        } finally {
            fclose($h);
        }
    }

    /** @return array<string, array<string, int>> Tag => Art => Anzahl */
    public static function stats(): array
    {
        $d = json_decode((string) @file_get_contents(self::statsFile()), true);
        return is_array($d) ? $d : [];
    }

    /** Summe je Art in den letzten $days Tagen */
    public static function totals(int $days = 7): array
    {
        $from = date('Y-m-d', time() - ($days - 1) * 86400);
        $out = [];
        foreach (self::stats() as $day => $row) {
            if ($day < $from) continue;
            foreach ((array) $row as $k => $n) $out[$k] = ($out[$k] ?? 0) + (int) $n;
        }
        return $out;
    }

    // ------------------------------------------------------------------ Betrieb

    /** Ausgehende Verbindungen möglich? (DNS, TLS zu example.com:443) – Ergebnis 10 Minuten zwischengespeichert */
    public static function health(): array
    {
        $f = ROOT . '/storage/cache/klxm-check-health.json';
        $d = is_file($f) && filemtime($f) > time() - 600 ? json_decode((string) @file_get_contents($f), true) : null;
        if (!is_array($d)) {
            $dns = new Dns(8);
            $ips = $dns->a('example.com');
            $tls = false;
            if ($ips) {
                $hs = Tls::handshake('example.com', $ips[0], 443, null, true);
                $tls = $hs['ok'];
            }
            $d = ['dns' => (bool) $ips, 'tls' => $tls, 'curl' => function_exists('curl_init')];
            @mkdir(dirname($f), 0770, true);
            @file_put_contents($f, json_encode($d));
        }
        return [
            __('KLXM Check: DNS-Abfragen möglich') => (bool) $d['dns'],
            __('KLXM Check: ausgehende TLS-Verbindung (Port 443)') => $d['tls'] ? true : null,
            __('KLXM Check: PHP-Erweiterung curl (Website-Prüfung)') => $d['curl'] ? true : null,
        ];
    }

    // ------------------------------------------------------------------ Ausgabe

    /**
     * Werkzeug ausgeben (Block und eigenständige Seite). $o: title, intro, tab (Start-Reiter), tabs (all|checker|ssl|generators),
     * guides (bool), level (Überschrift des Titels: 1 oder 2), wrap (Container-Klasse des Kits)
     */
    public static function render(array $o = []): string
    {
        static $n = 0;
        $n++;
        $vars = [
            'o' => $o + ['title' => '', 'intro' => '', 'tab' => 'checker', 'tabs' => 'all', 'guides' => true, 'level' => 2],
            'uid' => 'kc' . $n,
            'first' => $n === 1,
            'api' => url('/check/api'),
            'css' => self::asset('css/check.css'),
            'js' => self::asset('js/check.mjs'),
            'i18n' => require dirname(__DIR__) . '/views/i18n.php',
        ];
        extract($vars, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__) . '/views/tool.php';
        return (string) ob_get_clean();
    }

    /** Blockdefinition „KLXM Check“ (für alle Kits) */
    public static function blockDefinition(): array
    {
        return [
            'label' => 'KLXM Check (Domain-, Mail- & TLS-Prüfung)', 'icon' => 'shield-check', 'group' => 'Werkzeuge',
            'help' => 'Prüft SPF, DMARC, DKIM, Mailserver, Hosting, Website-Sicherheit und SSL/TLS einer Domain; dazu SPF- und DMARC-Generator. Der Server verbindet sich dabei mit fremden Servern (nur öffentliche Adressen, begrenzt je Besucher).',
            'fields' => [
                ['name' => 'title', 'label' => 'Überschrift (optional)', 'type' => 'text', 'width' => 'half'],
                ['name' => 'tab', 'label' => 'Start-Reiter', 'type' => 'select', 'default' => 'checker', 'required' => true, 'width' => 'half',
                    'options' => ['checker' => 'Domain-Analyse', 'ssl-checker' => 'SSL/TLS-Checker', 'spf-generator' => 'SPF-Generator', 'dmarc-generator' => 'DMARC-Generator']],
                ['name' => 'intro', 'label' => 'Einleitung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 400],
                ['name' => 'tabs', 'label' => 'Angebotene Werkzeuge', 'type' => 'select', 'default' => 'all', 'required' => true, 'width' => 'half',
                    'options' => ['all' => 'Alle (Analyse, SSL/TLS, Generatoren)', 'checker' => 'Nur Domain-Analyse', 'ssl' => 'Nur SSL/TLS-Checker', 'generators' => 'Nur SPF- und DMARC-Generator']],
                ['name' => 'guides', 'label' => 'Einsteiger-Anleitungen anzeigen', 'type' => 'bool', 'default' => true, 'width' => 'half'],
            ],
        ];
    }
}
