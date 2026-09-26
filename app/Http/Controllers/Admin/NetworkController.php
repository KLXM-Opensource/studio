<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Network\Network;
use Core\Network\Stats;
use Core\RateLimiter;
use Core\Sites;
use Core\Theme;

/**
 * Netzwerk-Administration: Übersicht aller Websites (/admin/network, nur Netzwerk-Website), Anmeldung auf anderen Websites
 * per Einmal-Token (open → /admin/sso der Ziel-Website), Wartungsaktionen, Netzwerk-Konten, neue Websites.
 */
final class NetworkController extends AdminController
{
    /** Angemeldet als Netzwerk-Administration (optional nur auf der Netzwerk-Website) */
    private function network(Request $r, bool $home = true): array
    {
        $user = $this->auth($r);
        if (!Network::isNetworkUser($user)) throw new HttpException(403, __('Nur für die Netzwerk-Administration.'));
        if ($home && !Network::isNetworkSite()) throw new HttpException(404);
        return $user;
    }

    private function siteOr404(string $key): string
    {
        if (!isset(Sites::all()[$key])) throw new HttpException(404);
        return $key;
    }

    public function index(Request $r): Response
    {
        $user = $this->network($r);
        Network::rememberUrl();
        $fresh = $r->str('refresh') === '1';
        $stats = Stats::all($fresh);
        if ($fresh) return Response::redirect(url('/admin/network'));
        $warn = [];
        foreach ($stats as $k => $s) $warn[$k] = Stats::warnings($s);
        $newSite = app()->session->get('_net_newsite');
        app()->session->forget('_net_newsite');
        return $this->view('network/index', ['stats' => $stats, 'warn' => $warn, 'accounts' => Network::accounts(),
            'log' => Network::recentLog(20), 'shared' => Stats::shared(), 'themes' => Theme::available(), 'me' => $user, 'newSite' => $newSite,
            'css' => ['css/passkey.css']]);
    }

    // ================================================================= Anmeldung auf anderen Websites

    /** Einmal-Token ausstellen und zur Ziel-Website weiterleiten (von jeder Website aus, z. B. „Website wechseln“) */
    public function open(Request $r): Response
    {
        $user = $this->network($r, false);
        $key = $this->siteOr404($r->str('site'));
        // Ziel ohne Pfad: auf der Netzwerk-Website die Übersicht aller Websites, sonst die Übersicht der Website
        $path = Network::safePath($r->str('path') ?: ($key === Network::siteKey() ? '/admin/network' : '/admin'));
        if ($key === site()->key) return Response::redirect(url($path));
        $limiter = Network::limiter();
        $lk = 'sso-issue:' . Network::uid($user);
        if ($limiter->tooMany($lk, 30, 600)) {
            return $this->back(Network::isNetworkSite() ? '/admin/network' : '/admin', 'error', __('Zu viele Anmeldungen in kurzer Zeit. Bitte warten Sie einige Minuten.'));
        }
        $limiter->hit($lk);
        $token = Network::issue(Network::uid($user), $key, $path, (string) ($r->server['HTTP_USER_AGENT'] ?? ''));
        Network::log('sso.issue', $key, (string) $user['email'], 'von ' . site()->key . ' → ' . $path);
        // Keine 303-Weiterleitung: Browser wenden „form-action 'self'“ (CSP der Verwaltung) auch auf Weiterleitungen nach
        // einem Formular an. Stattdessen eine Zwischenseite mit <meta refresh> (kein Skript, Token nur in dieser Antwort, no-store).
        $to = Network::siteLink($key, '/admin/sso') . '?t=' . rawurlencode($token);
        $label = (new \Core\Site($key, Sites::all()[$key]))->label();
        $html = '<!doctype html><html lang="' . e(\Core\I18n::locale()) . '" class="adm-ui"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer">'
            . '<meta http-equiv="refresh" content="0;url=' . e($to) . '"><title>' . e(__('Weiterleitung …')) . '</title>'
            . '<link rel="stylesheet" href="' . e(asset('css/admin.css')) . '"></head><body class="adm adm--bare"><main class="adm-auth">'
            . '<p class="adm-card">' . e(__('Anmeldung bei {site} …', ['site' => $label])) . ' <a href="' . e($to) . '" rel="noreferrer">' . e(__('Weiter')) . '</a></p>'
            . '</main></body></html>';
        return self::secure(new Response($html))->header('Referrer-Policy', 'no-referrer');
    }

    /** Ziel-Website: Token prüfen, Schatten-Konto anmelden, Token per Weiterleitung aus der Adresse entfernen */
    public function sso(Request $r): Response
    {
        $limiter = new RateLimiter(app()->db);
        $ipKey = 'sso:' . hash_hmac('sha256', $r->ip(), app()->key());
        $fail = function (string $msg, int $status = 403): Response {
            return $this->view('login', ['user' => null, 'error' => $msg, 'email' => '', 'next' => ''], $status)->header('Referrer-Policy', 'no-referrer');
        };
        if ($limiter->tooMany($ipKey, 20, 600)) return $fail(__('Zu viele Anmeldeversuche. Bitte warten Sie einige Minuten.'), 429);
        $res = Network::consume($r->str('t'), (string) ($r->server['HTTP_USER_AGENT'] ?? ''));
        if (is_string($res)) {
            $limiter->hit($ipKey);
            return $fail($res);
        }
        $n = Network::accountById($res['uid']);
        if (!$n) {
            $limiter->hit($ipKey);
            return $fail(__('Das Netzwerk-Konto gibt es nicht mehr.'));
        }
        if ($err = Network::loginShadow($n, 'sso.login')) return $fail($err);
        return Response::redirect(url($res['path']))->header('Referrer-Policy', 'no-referrer');
    }

    // ================================================================= Aktionen je Website

    public function action(Request $r, string $key, string $action): Response
    {
        $user = $this->network($r);
        $key = $this->siteOr404($key);
        try {
            switch ($action) {
                case 'maintenance':
                    $on = $r->str('on') === '1';
                    Stats::setMaintenance($key, $on);
                    Network::log($on ? 'maintenance.on' : 'maintenance.off', $key, (string) $user['email']);
                    $msg = $on ? __('Wartungsmodus für {site} eingeschaltet.', ['site' => $key]) : __('Wartungsmodus für {site} ausgeschaltet.', ['site' => $key]);
                    break;
                case 'backup':
                    $limiter = Network::limiter();
                    if ($limiter->tooMany('net-backup:' . $key, 3, 600)) throw new \RuntimeException(__('Für diese Website wurden gerade erst Sicherungen erstellt. Bitte später erneut.'));
                    $limiter->hit('net-backup:' . $key);
                    $file = Stats::backup($key);
                    Network::log('backup', $key, (string) $user['email'], basename($file));
                    $msg = __('Sicherung erstellt: {file}', ['file' => basename($file)]);
                    break;
                case 'hosts':
                    // Domains der Website (config/sites/{key}.php → 'hosts'), z. B. für Landingpages mit eigenen Domains (Core\Landings)
                    $op = $r->str('op') === 'remove' ? 'remove' : 'add';
                    Sites::setHosts($key, $op, $r->str('host'), $r->str('landing') === '1');
                    $h = (string) Sites::normalizeHost($r->str('host'));
                    Network::log('hosts.' . $op, $key, (string) $user['email'], $h);
                    $msg = $op === 'add' ? __('Domain {host} zu {site} hinzugefügt. DNS und Hosting (Plesk-Alias) nicht vergessen.', ['host' => $h, 'site' => $key])
                        : __('Domain {host} von {site} entfernt.', ['host' => $h, 'site' => $key]);
                    break;
                case 'cache':
                    Stats::clearCache($key);
                    Network::log('cache.clear', $key, (string) $user['email']);
                    $msg = __('Seiten-Cache von {site} geleert.', ['site' => $key]);
                    break;
                default:
                    throw new HttpException(404);
            }
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->back('/admin/network#site-' . $key, 'error', $e->getMessage());
        }
        return $this->back('/admin/network#site-' . $key, 'success', $msg);
    }

    /** Neue Website (gleicher Code wie `site:create`) */
    public function createSite(Request $r): Response
    {
        $user = $this->network($r);
        $key = strtolower($r->str('key'));
        try {
            $res = Sites::create($key, preg_split('~[\s,]+~', $r->str('hosts')) ?: [], $r->str('theme'));
            Network::ensureKey();
        } catch (\InvalidArgumentException $e) {
            return $this->back('/admin/network#neu', 'error', $e->getMessage());
        }
        Network::log('site.create', $key, (string) $user['email'], $r->str('hosts'));
        app()->session->set('_net_newsite', ['key' => $key, 'token' => $res['setup_token'], 'file' => str_replace(ROOT . '/', '', $res['file'])]);
        return $this->back('/admin/network#neu', 'success', __('Website „{site}“ angelegt.', ['site' => $key]));
    }

    // ================================================================= Netzwerk-Konten

    public function accountCreate(Request $r): Response
    {
        $user = $this->network($r);
        $pw = (string) ($r->post['password'] ?? '');
        try {
            Network::createAccount($r->str('email'), $pw, $r->str('name'));
        } catch (\InvalidArgumentException $e) {
            return $this->back('/admin/network#konten', 'error', $e->getMessage());
        }
        Network::log('account.create', null, (string) $user['email'], strtolower($r->str('email')));
        return $this->back('/admin/network#konten', 'success', __('Netzwerk-Konto {email} angelegt. Bei der ersten Anmeldung wird die Zwei-Faktor-Anmeldung eingerichtet.', ['email' => strtolower($r->str('email'))]));
    }

    /** Anmelde-Richtlinie der Netzwerk-Konten: erlaubte Verfahren, „nur Passkey“ (Core\Mfa, Einstellung sys.net_auth) */
    public function authPolicy(Request $r): Response
    {
        $user = $this->network($r);
        if ($err = \Core\Mfa::saveNetPolicy($r->post)) return $this->back('/admin/network#konten', 'error', $err);
        $p = \Core\Mfa::netPolicy();
        Network::log('auth.policy', null, (string) $user['email'], implode(', ', array_keys(array_filter($p))));
        return $this->back('/admin/network#konten', 'success', __('Anmelde-Richtlinie der Netzwerk-Konten gespeichert.'));
    }

    public function accountAction(Request $r, string $id, string $action): Response
    {
        $user = $this->network($r);
        $acc = Network::accountById((int) $id) ?? throw new HttpException(404);
        if ((int) $acc['id'] === (int) $user['id'] && $action !== 'enable') {
            return $this->back('/admin/network#konten', 'error', __('Das eigene Konto können Sie hier nicht sperren oder zurücksetzen.'));
        }
        switch ($action) {
            case 'disable':
            case 'enable':
                Network::setDisabled((int) $acc['id'], $action === 'disable');
                $msg = $action === 'disable' ? __('{email} gesperrt – alle Sitzungen auf allen Websites sind beendet.', ['email' => $acc['email']])
                    : __('{email} entsperrt.', ['email' => $acc['email']]);
                break;
            case 'reset-2fa':
                Network::resetTwoFactor((int) $acc['id']);
                $msg = __('Zwei-Faktor-Anmeldung von {email} zurückgesetzt – sie wird bei der nächsten Anmeldung neu eingerichtet.', ['email' => $acc['email']]);
                break;
            default:
                throw new HttpException(404);
        }
        Network::log('account.' . $action, null, (string) $user['email'], (string) $acc['email']);
        return $this->back('/admin/network#konten', 'success', $msg);
    }
}
