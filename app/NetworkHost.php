<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Http\Request;
use Core\Http\Router;
use Core\Network\Network;

/**
 * Netzwerk-Domain (Verwaltungs-Domain): optionale, zentrale Adresse für Verwaltung und Anmeldung der Netzwerk-Website –
 * z. B. die Agentur-Adresse „kunde.agentur.example“, während die Website selbst unter „www.kunde.example“ erscheint.
 *
 *  - Einstellung nur für die Installation: config 'network_host' in config/config.php bzw. config.local.php (Website-Dateien
 *    können sie nicht setzen). Ändern: Netzwerk → Administratoren → „Netzwerk-Domain“, Grundeinstellungen → „Adresse der
 *    Verwaltung“ oder `php bin/console network:host <domain>|--reset`. Standard leer = Verhalten wie bisher.
 *  - Gilt für die Netzwerk-Website (config 'network_site'); in einer Einzel-Installation ist das die einzige Website – dort wirkt
 *    die Einstellung als „Verwaltungs-Domain“ genauso. Andere Websites eines Netzwerks behalten ihre eigene Verwaltung.
 *  - Die Domain muss zur Netzwerk-Website führen: Eintrag in ihren 'hosts' oder – ohne Eintrag – 'fallback_site' = Netzwerk-Website.
 *    Keine Landing-Domain, keine Domain einer anderen Website.
 *  - Wirkung: absolute Verwaltungsadressen (admin_url_abs(), Mails, OIDC, Einmal-Anmeldung) zeigen auf die Netzwerk-Domain;
 *    Seitenaufrufe von /admin… auf anderen Domains der Netzwerk-Website werden dorthin umgeleitet (adminRedirect) – bei
 *    bestehender Sitzung mit Einmal-Token (Core\HostHandover), damit man angemeldet bleibt.
 *  - Die Website selbst wird nicht kanonisiert: Canonical, Sitemap und Links bleiben bei der Hauptadresse; die Weiterleitung auf
 *    die Hauptadresse (redirect_to_primary) lässt die Netzwerk-Domain aus.
 */
final class NetworkHost
{
    public const CONFIG = 'network_host';

    /** false = noch nicht gelesen */
    private static string|false|null $host = false;

    /** Eingestellte Netzwerk-Domain (klein, ohne Schema, ggf. mit :port) oder null */
    public static function get(): ?string
    {
        if (self::$host !== false) return self::$host;
        $raw = (string) (Network::install()[self::CONFIG] ?? '');
        return self::$host = $raw === '' ? null : Sites::normalizeHost($raw);
    }

    /** Nur für Tests und nach dem Speichern: Wert setzen bzw. (ohne Argument) neu lesen */
    public static function reset(string|false|null $host = false): void
    {
        self::$host = $host;
    }

    /** Gilt die Netzwerk-Domain für diese (bzw. die aktuelle) Website? */
    public static function active(?string $site = null): bool
    {
        return self::get() !== null && ($site ?? site()->key) === Network::siteKey();
    }

    /** Gehört der Host (Host-Header, ggf. mit Port) zur Netzwerk-Domain? Ohne Port in der Einstellung zählt jeder Port. */
    public static function is(string $host): bool
    {
        $nh = self::get();
        if ($nh === null) return false;
        $h = strtolower(trim($host));
        return $h === $nh || (!str_contains($nh, ':') && (string) preg_replace('~:\d+$~', '', $h) === $nh);
    }

    /** Läuft die aktuelle Anfrage über die Netzwerk-Domain? */
    public static function isCurrent(): bool
    {
        return ($r = app()->request) !== null && self::is($r->host());
    }

    /** Ursprung der Netzwerk-Domain, z. B. https://kunde.agentur.example (lokale Test-Domains: http) – null = nicht gesetzt */
    public static function origin(): ?string
    {
        $h = self::get();
        if ($h === null) return null;
        $secure = !Domains::isLocal($h) || (app()->request?->isSecure() ?? false);
        return ($secure ? 'https://' : 'http://') . $h;
    }

    /**
     * Absolute Adresse eines Pfads der Verwaltung dieser Website – auf der Netzwerk-Website mit Netzwerk-Domain dort, sonst wie
     * absolute_url(). Helfer admin_url_abs().
     */
    public static function adminUrl(string $path = '/admin'): string
    {
        return self::active() ? self::origin() . url($path) : absolute_url($path);
    }

    /**
     * Ziel einer Weiterleitung auf die Netzwerk-Domain oder null. Nur Seitenaufrufe (GET/HEAD, Dokument) von /admin… auf einer
     * anderen Domain der Netzwerk-Website. Ausnahmen: Einmal-Anmeldung und Domainwechsel (/admin/sso…), signierte Abfrage
     * zwischen den Websites, öffentliche Routen von Erweiterungen (AdminPath::directAllowed) sowie alles, was Skripte, Formulare,
     * Stylesheets, Bilder oder Rahmen der Website laden (Werkzeugleiste, Bearbeiten auf der Website, Vorschau, Live) – dafür gilt
     * die Sitzung der jeweiligen Domain. $direct: /admin direkt trotz eigener Verwaltungsadresse – nie umleiten (verriete sie).
     */
    public static function redirectTarget(Request $r, ?Router $router = null, bool $direct = false): ?string
    {
        if (!self::active() || $direct || !$r->isAdminPath() || !in_array($r->method, ['GET', 'HEAD'], true)) return null;
        if (self::is($r->host())) return null;
        if (AdminPath::directAllowed($r->path, $router, $r->method)) return null;
        if (!self::isNavigation($r)) return null;
        $q = (string) parse_url((string) ($r->server['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
        return self::origin() . url($r->path) . ($q !== '' ? '?' . $q : '');
    }

    /** Seitenaufruf im Browserfenster? Sec-Fetch-Dest = document; ohne Fetch-Metadaten: HTML erwartet, kein JSON/XHR */
    public static function isNavigation(Request $r): bool
    {
        $dest = strtolower((string) ($r->server['HTTP_SEC_FETCH_DEST'] ?? ''));
        if ($dest !== '') return $dest === 'document';
        if ($r->wantsJson() || strtolower((string) ($r->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') return false;
        $accept = (string) ($r->server['HTTP_ACCEPT'] ?? '');
        return $accept === '' || str_contains($accept, 'text/html') || $accept === '*/*';
    }

    // ================================================================= Prüfen, Speichern

    /** Website, an die die Installation den Host gibt (Eintrag in hosts/landing_hosts, sonst fallback_site) – null = keine */
    public static function servedBy(string $host): ?string
    {
        $inst = Network::install();
        $fallback = array_key_exists('fallback_site', $inst) ? $inst['fallback_site'] : Site::DEFAULT;
        return Sites::resolve($host, is_string($fallback) && $fallback !== '' ? $fallback : null);
    }

    /** Fehlermeldung für eine gewünschte Netzwerk-Domain oder null (nur Konfiguration, ohne Abruf) */
    public static function problem(string $raw): ?string
    {
        $h = Sites::normalizeHost($raw);
        if ($h === null) return __('Bitte eine gültige Domain angeben, z. B. verwaltung.example.org (ohne https://).');
        $net = Network::siteKey();
        $site = self::servedBy($h);
        if ($site === null) return __('Die Domain {host} führt zu keiner Website dieser Installation. Tragen Sie sie bei der Netzwerk-Website ein oder stellen Sie fallback_site auf die Netzwerk-Website.', ['host' => $h]);
        if ($site !== $net) return __('Die Domain {host} gehört zur Website „{site}“ – die Netzwerk-Domain muss zur Netzwerk-Website führen.', ['host' => $h, 'site' => $site]);
        $netSite = new Site($net, Sites::all()[$net] ?? []);
        if (in_array($h, $netSite->landingHosts(), true) || in_array((string) preg_replace('~:\d+$~', '', $h), $netSite->landingHosts(), true)) {
            return __('{host} ist eine Landing-Domain – dort gibt es keine Verwaltung. Bitte eine andere Domain wählen.', ['host' => $h]);
        }
        return null;
    }

    /**
     * Netzwerk-Domain in config/config.local.php speichern (Kommentare und übrige Werte bleiben); '' = entfernen.
     * $check: vorher per Abruf prüfen, ob die Domain auf diese Installation zeigt (Core\Domains::check, nicht für lokale Adressen).
     * @return ?string Fehlermeldung oder null
     */
    public static function save(string $raw, bool $check = true): ?string
    {
        $raw = trim($raw);
        $h = $raw === '' ? '' : (string) Sites::normalizeHost($raw);
        if ($raw !== '' && ($err = self::problem($raw))) return $err;
        if ($h !== '' && $check && !Domains::isLocal($h)) {
            try {
                $res = Domains::check($h);
            } catch (\Throwable $e) {
                error_log('[network-host] ' . $e->getMessage());
                $res = ['ok' => false, 'reachable' => false];
            }
            if (!$res['ok']) return $res['reachable']
                ? __('{host} ist erreichbar, zeigt aber nicht auf diese Installation (DNS bzw. Hosting-Alias prüfen).', ['host' => $h])
                : __('{host} ist nicht erreichbar. Bitte zuerst DNS und Hosting (Alias, Zertifikat) einrichten – sonst wäre die Verwaltung nicht mehr erreichbar.', ['host' => $h]);
        }
        if ($err = self::writeLocal($h)) return $err;
        self::reset();
        return null;
    }

    /** Wert in config/config.local.php schreiben – vorher die neue Datei prüfen, dann atomar ersetzen */
    private static function writeLocal(string $host): ?string
    {
        $file = ROOT . '/config/config.local.php';
        if (!is_file($file)) return __('config/config.local.php fehlt.');
        $src = (string) file_get_contents($file);
        $new = $host === '' ? Sites::removeTopLevel($src, self::CONFIG, true)
            : Sites::replaceTopLevel($src, self::CONFIG, var_export($host, true));
        if ($new === null) return __('config/config.local.php hat kein erkennbares return-Array – bitte {key} von Hand eintragen.', ['key' => self::CONFIG]);
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (!is_writable($file) || @file_put_contents($tmp, $new, LOCK_EX) === false) {
            return __('config/config.local.php ist nicht beschreibbar – bitte per Konsole setzen: {cmd}', ['cmd' => 'php bin/console network:host ' . ($host ?: '--reset')]);
        }
        try {
            $check = (static fn(string $__f) => require $__f)($tmp);
        } catch (\Throwable $e) {
            @unlink($tmp);
            return __('Geänderte Konfiguration ist ungültig – nichts geändert.');
        }
        if (!is_array($check) || (string) ($check[self::CONFIG] ?? '') !== $host) {
            @unlink($tmp);
            return __('Geänderte Konfiguration ist ungültig – nichts geändert.');
        }
        @chmod($tmp, 0640);
        rename($tmp, $file);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
        Network::reset();
        return null;
    }

    /** Ändern in der Verwaltung: wie die Adresse der Verwaltung (Core\AdminPath::canManage) und nur auf der Netzwerk-Website */
    public static function canManage(): bool
    {
        return AdminPath::canManage() && Network::isNetworkSite();
    }

    /** Prüfung für health/Überwachung: nur Konfiguration (kein Abruf) */
    public static function health(): array
    {
        $h = self::get();
        if ($h === null || !Network::isNetworkSite()) return [];
        $err = self::problem($h);
        return [($err === null ? 'Netzwerk-Domain ' . $h : 'Netzwerk-Domain ' . $h . ': ' . $err) => $err === null];
    }
}
