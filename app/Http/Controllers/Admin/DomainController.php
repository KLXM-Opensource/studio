<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Domains;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Network\Network;
use Core\RateLimiter;
use Core\Sites;

/**
 * System → Domain (Core\Domains): Domains der Website, Hauptadresse (erst nach erfolgreicher Erreichbarkeitsprüfung),
 * Weiterleitung weiterer Domains (301); außerdem Umgebung (staging ↔ production) für jede Website unter Grundeinstellungen. Schreibt config/sites/{key}.php (Sicherung .bak).
 * Nur Einzel-Installation ohne Netzwerk – sonst 404 (Domains verwaltet dann die Netzwerk-Administration). Recht: system.manage.
 */
final class DomainController extends AdminController
{
    private const BACK = '/admin/system/domain';

    private function guard(Request $r): array
    {
        $user = $this->auth($r, 'system.manage');
        if (!Domains::available()) throw new HttpException(404);
        return $user;
    }

    /** Protokoll (network_log, auch in Einzel-Installationen) */
    private function log(array $user, string $action, string $detail = ''): void
    {
        Network::log($action, site()->key, (string) ($user['email'] ?? ''), $detail);
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        $hosts = site()->hosts();
        return $this->view('system/domain', [
            'title' => __('Domain'), 'hosts' => $hosts, 'landing' => site()->landingHosts(),
            'current' => strtolower($r->host()), 'redirect' => Domains::redirectOn(), 'env' => environment(),
            'siteUrl' => (string) app()->settings->get('sys.site_url', ''),
            'checked' => (array) (app()->session->get('domain_checked') ?? []),
        ]);
    }

    public function add(Request $r): Response
    {
        $user = $this->guard($r);
        $h = Sites::normalizeHost($r->str('host'));
        if ($h === null) return $this->back(self::BACK, 'error', __('Bitte eine gültige Domain eintragen, z. B. www.beispiel.de.'));
        if (in_array($h, array_merge(site()->hosts(), site()->landingHosts()), true)) return $this->back(self::BACK, 'error', __('Die Domain {host} ist bereits eingetragen.', ['host' => $h]));
        try {
            // Noch keine Domain eingetragen: die aktuell aufgerufene wird Hauptadresse (über sie ist die Verwaltung gerade erreichbar)
            $cur = Sites::normalizeHost($r->host());
            if (!site()->hosts() && $cur !== null && $cur !== $h) Sites::setHosts(site()->key, 'add', $cur);
            Sites::setHosts(site()->key, 'add', $h);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->back(self::BACK, 'error', $e->getMessage());
        }
        $this->log($user, 'hosts.add', $h);
        $this->changed();
        return $this->back(self::BACK, 'success', __('Domain {host} hinzugefügt.', ['host' => $h]));
    }

    public function remove(Request $r): Response
    {
        $user = $this->guard($r);
        $h = Sites::normalizeHost($r->str('host')) ?? '';
        $hosts = site()->hosts();
        if (!in_array($h, $hosts, true)) return $this->back(self::BACK, 'error', __('Diese Domain ist nicht eingetragen.'));
        if ($h === ($hosts[0] ?? '')) return $this->back(self::BACK, 'error', __('Die Hauptadresse kann nicht entfernt werden – legen Sie zuerst eine andere Domain als Hauptadresse fest.'));
        if ($h === strtolower($r->host())) return $this->back(self::BACK, 'error', __('Über diese Domain sind Sie gerade angemeldet – sie kann nicht entfernt werden. Bitte die Verwaltung über die Hauptadresse öffnen.'));
        try {
            Sites::setHosts(site()->key, 'remove', $h);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->back(self::BACK, 'error', $e->getMessage());
        }
        $this->log($user, 'hosts.remove', $h);
        $this->changed();
        return $this->back(self::BACK, 'success', __('Domain {host} entfernt.', ['host' => $h]));
    }

    /** Erreichbarkeit prüfen: zeigt die Domain auf diese Installation? Ergebnis 10 Minuten in der Sitzung (Voraussetzung für „Hauptadresse“) */
    public function check(Request $r): Response
    {
        $user = $this->guard($r);
        $h = Sites::normalizeHost($r->str('host')) ?? '';
        if (!in_array($h, site()->hosts(), true)) return $this->back(self::BACK, 'error', __('Diese Domain ist nicht eingetragen.'));
        $limiter = new RateLimiter(app()->db);
        $key = 'domaincheck:' . (int) ($user['id'] ?? 0);
        if ($limiter->tooMany($key, 10, 600)) return $this->back(self::BACK, 'error', __('Zu viele Prüfungen. Bitte warten Sie einige Minuten.'));
        $limiter->hit($key);
        $res = Domains::check($h);
        $checked = (array) (app()->session->get('domain_checked') ?? []);
        if (!$res['ok']) {
            unset($checked[$h]);
            app()->session->set('domain_checked', $checked);
            return $this->back(self::BACK, 'error', self::notReady($res['reachable']));
        }
        $checked[$h] = ['at' => time(), 'scheme' => $res['scheme']];
        app()->session->set('domain_checked', $checked);
        return $this->back(self::BACK, 'success', $res['scheme'] === 'https'
            ? __('{host} zeigt auf diese Website (HTTPS mit gültigem Zertifikat).', ['host' => $h])
            : __('{host} zeigt auf diese Website – aber nur über HTTP. Bitte beim Hosting ein SSL-Zertifikat einrichten.', ['host' => $h]));
    }

    /** Hauptadresse festlegen – nur nach erfolgreicher Prüfung (prüft erneut, falls die letzte Prüfung älter als 10 Minuten ist) */
    public function primary(Request $r): Response
    {
        $user = $this->guard($r);
        $h = Sites::normalizeHost($r->str('host')) ?? '';
        $hosts = site()->hosts();
        if (!in_array($h, $hosts, true)) return $this->back(self::BACK, 'error', __('Diese Domain ist nicht eingetragen.'));
        if ($h === $hosts[0]) return $this->back(self::BACK, 'success', __('{host} ist bereits die Hauptadresse.', ['host' => $h]));
        $checked = (array) (app()->session->get('domain_checked') ?? []);
        $scheme = (string) ($checked[$h]['scheme'] ?? '');
        if ((int) ($checked[$h]['at'] ?? 0) < time() - 600) {
            $limiter = new RateLimiter(app()->db);
            $key = 'domaincheck:' . (int) ($user['id'] ?? 0);
            if ($limiter->tooMany($key, 10, 600)) return $this->back(self::BACK, 'error', __('Zu viele Prüfungen. Bitte warten Sie einige Minuten.'));
            $limiter->hit($key);
            $res = Domains::check($h);
            if (!$res['ok']) return $this->back(self::BACK, 'error', self::notReady($res['reachable']));
            $scheme = $res['scheme'];
        }
        $old = $hosts[0];
        try {
            Sites::setPrimary(site()->key, $h);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->back(self::BACK, 'error', $e->getMessage());
        }
        // Kanonische Adresse (Grundeinstellungen) zeigte auf die alte Hauptadresse → mitziehen
        $su = (string) app()->settings->get('sys.site_url', '');
        if ($su !== '' && strtolower((string) parse_url($su, PHP_URL_HOST) . (parse_url($su, PHP_URL_PORT) ? ':' . parse_url($su, PHP_URL_PORT) : '')) === $old) {
            app()->settings->set('sys.site_url', ($scheme ?: 'https') . '://' . $h);
        }
        unset($checked[$h]);
        app()->session->set('domain_checked', $checked);
        $this->log($user, 'hosts.primary', $old . ' → ' . $h);
        $this->changed();
        return $this->back(self::BACK, 'success', __('{host} ist jetzt die Hauptadresse der Website.', ['host' => $h]));
    }

    public function redirect(Request $r): Response
    {
        $user = $this->guard($r);
        $on = $r->str('on') === '1';
        try {
            Sites::setOption(site()->key, 'redirect_to_primary', $on);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->back(self::BACK . '#weiterleitung', 'error', $e->getMessage());
        }
        $this->log($user, 'hosts.redirect', $on ? 'an' : 'aus');
        $this->changed();
        return $this->back(self::BACK . '#weiterleitung', 'success', $on
            ? __('Weitere Domains leiten jetzt auf die Hauptadresse weiter (301).') : __('Weiterleitung auf die Hauptadresse ausgeschaltet.'));
    }

    /** Umgebung: staging ↔ production (development nur, wenn schon gesetzt – dann auch zurück nach production/staging) */
    /** Grundeinstellungen → Umgebung: staging ↔ production – für jede Website (auch im Netzwerk), Recht system.manage */
    public function environment(Request $r): Response
    {
        $user = $this->auth($r, 'system.manage');
        $back = '/admin/system#umgebung';
        $env = $r->str('environment');
        if (!in_array($env, ['production', 'staging'], true)) return $this->back($back, 'error', __('Unbekannte Umgebung.'));
        $old = environment();
        if ($env === $old) return $this->back($back, 'success', __('Die Umgebung ist bereits „{env}“.', ['env' => $env]));
        try {
            Sites::setOption(site()->key, 'environment', $env);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->back($back, 'error', $e->getMessage());
        }
        $this->log($user, 'site.environment', $old . ' → ' . $env);
        \Core\Network\Stats::forget(site()->key);
        $this->changed();
        return $this->back($back, 'success', __('Umgebung auf „{env}“ umgestellt.', ['env' => $env]));
    }

    private static function notReady(bool $reachable): string
    {
        return __('Die Domain zeigt noch nicht auf diese Website – DNS/Hosting (Plesk: Alias bzw. zusätzliche Domain, SSL-Zertifikat) einrichten.')
            . ($reachable ? ' ' . __('Unter der Domain antwortet ein anderer Server bzw. eine andere Website.') : ' ' . __('Unter der Domain war nichts erreichbar.'));
    }
}
