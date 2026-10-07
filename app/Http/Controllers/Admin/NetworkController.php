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
        // Einladungslink einmal anzeigen, wenn die E-Mail nicht zugestellt wurde (wie Benutzer & Rollen)
        $inviteLink = app()->session->get('_net_invite_link');
        app()->session->forget('_net_invite_link');
        $old = app()->session->get('_net_invite_old');
        app()->session->forget('_net_invite_old');
        return $this->view('network/index', ['stats' => $stats, 'warn' => $warn, 'accounts' => Network::accounts(),
            'invites' => \Core\Invites::open(true), 'inviteLink' => is_array($inviteLink) ? $inviteLink : null, 'inviteOld' => is_array($old) ? $old : null,
            'log' => Network::recentLog(20), 'shared' => Stats::shared(), 'themes' => Theme::available(), 'me' => $user, 'newSite' => $newSite,
            'css' => ['css/passkey.css', 'css/invite.css', 'css/network.css']]);
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
    /**
     * Verwendungen von Pool-Dateien auf DIESER Website (für das Löschen in geteilten Medien auf einer anderen Website):
     * ohne Anmeldung, aber mit Signatur über den Netzwerk-Schlüssel (MediaPools::usageSig), 60 s gültig. Antwort:
     * {ok, usages: {poolId: [{label, url}]}} – nur Dateien, die hier einen Verweis-Eintrag haben.
     */
    public function mediaUsages(Request $r): Response
    {
        $key = $r->str('pool'); $ids = $r->str('ids'); $exp = (int) $r->str('exp');
        $ok = preg_match('~^[a-z0-9_\-]{1,40}$~', $key) && preg_match('~^\d+(,\d+){0,199}$~', $ids) && $exp >= time() && $exp <= time() + 120
            && strlen(Network::key()) >= 32 && hash_equals(\Core\MediaPools::usageSig($key, $ids, $exp), $r->str('sig'));
        if (!$ok) return Response::json(['ok' => false, 'error' => 'Ungültige Anfrage.'], 403);
        $out = [];
        foreach (explode(',', $ids) as $pid) {
            $local = app()->db->fetchValue('SELECT id FROM media WHERE pool_ref = ?', [$key . ':' . (int) $pid]);
            if ($local && ($u = \Core\Media::usages((int) $local))) $out[(int) $pid] = $u;
        }
        return Response::json(['ok' => true, 'usages' => (object) $out])->header('Cache-Control', 'no-store');
    }

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
                case 'environment':
                    // Testumgebung (staging) setzen/aufheben – config/sites/{key}.php → 'environment' (Sicherung .bak)
                    $env = $r->str('environment') === 'staging' ? 'staging' : 'production';
                    $old = (string) Network::config($key)->get('environment', 'production');
                    if ($env !== $old) {
                        Sites::setOption($key, 'environment', $env);
                        Stats::forget($key);
                        Network::log('site.environment', $key, (string) $user['email'], $old . ' → ' . $env);
                    }
                    $msg = $env === 'staging' ? __('{site} ist jetzt Testumgebung (staging): Suchmaschinen ausgesperrt, E-Mails nicht an echte Empfänger.', ['site' => $key])
                        : __('{site} ist jetzt im Livebetrieb (production).', ['site' => $key]);
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
            // Startinhalte: beim ersten Anmelden entscheiden (Willkommen-Bildschirm) oder hier vorgeben (Core\Onboarding)
            $content = in_array($r->str('content'), ['ask', 'full', 'empty'], true) ? $r->str('content') : 'ask';
            $res = Sites::create($key, preg_split('~[\s,]+~', $r->str('hosts')) ?: [], $r->str('theme'), ['seed' => $content]);
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

    // ================================================================= Netzwerk-Administratoren einladen (Core\Invites)

    /** „Netzwerk-Admin einladen“: E-Mail mit Link (7 Tage), Konto entsteht erst beim Annehmen – nur durch aktive Netzwerk-Konten */
    public function inviteStore(Request $r): Response
    {
        $user = $this->inviter($r);
        $limiter = Network::limiter();
        $key = 'net-invite:' . (int) $user['id'];
        $old = ['email' => $r->str('email'), 'name' => $r->str('name'), 'message' => (string) ($r->post['message'] ?? '')];
        if ($limiter->tooMany($key, 20, 3600)) return $this->inviteError($old, ['email' => __('Zu viele Einladungen in kurzer Zeit. Bitte warten Sie eine Stunde.')]);
        $res = \Core\Invites::createNetwork($old + ['locale' => $r->str('locale')], $user);
        if (!isset($res['token'])) return $this->inviteError($old, $res);
        $limiter->hit($key);
        $inv = \Core\Invites::get((int) $res['id']);
        $sent = \Core\Invites::send($inv, $res['token']);
        Network::log('network.invite', null, (string) $user['email'], $inv['email'] . ($sent['delivered'] ? '' : ' · ohne E-Mail'));
        return $this->afterInvite($inv, $res['token'], $sent, __('Einladung an {email} gesendet.', ['email' => $inv['email']]));
    }

    public function inviteResend(Request $r, string $id): Response
    {
        $user = $this->inviter($r);
        $inv = \Core\Invites::get((int) $id);
        if (!$inv || !\Core\Invites::isNetwork($inv)) return $this->back('/admin/network#konten', 'error', __('Einladung nicht gefunden.'));
        $limiter = Network::limiter();
        $key = 'net-invite:' . (int) $user['id'];
        if ($limiter->tooMany($key, 20, 3600)) return $this->back('/admin/network#konten', 'error', __('Zu viele Einladungen in kurzer Zeit. Bitte warten Sie eine Stunde.'));
        if (app()->db->fetchValue('SELECT COUNT(*) FROM users WHERE LOWER(email) = ?', [strtolower((string) $inv['email'])])) {
            return $this->back('/admin/network#konten', 'error', __('Diese E-Mail-Adresse ist auf der Netzwerk-Website bereits registriert.'));
        }
        $token = \Core\Invites::renew((int) $id);
        if ($token === null) return $this->back('/admin/network#konten', 'error', __('Einladung nicht gefunden.'));
        // Wer erneut sendet, lädt ein (das ursprünglich einladende Konto kann inzwischen gesperrt sein)
        app()->db->update('user_invites', ['invited_by' => (int) $user['id'], 'invited_by_name' => (string) ($user['name'] ?: $user['email'])], 'id = :id', ['id' => (int) $id]);
        $limiter->hit($key);
        $inv = \Core\Invites::get((int) $id);
        $sent = \Core\Invites::send($inv, $token);
        Network::log('network.invite-resend', null, (string) $user['email'], $inv['email'] . ($sent['delivered'] ? '' : ' · ohne E-Mail'));
        return $this->afterInvite($inv, $token, $sent, __('Einladung an {email} erneut gesendet – der vorige Link gilt nicht mehr.', ['email' => $inv['email']]));
    }

    public function inviteRevoke(Request $r, string $id): Response
    {
        $user = $this->inviter($r);
        $inv = \Core\Invites::get((int) $id);
        if (!$inv || !\Core\Invites::isNetwork($inv) || !\Core\Invites::revoke((int) $id)) {
            return $this->back('/admin/network#konten', 'error', __('Einladung nicht gefunden.'));
        }
        Network::log('network.invite-revoke', null, (string) $user['email'], (string) $inv['email']);
        return $this->back('/admin/network#konten', 'success', __('Einladung an {email} zurückgezogen – der Link gilt nicht mehr.', ['email' => $inv['email']]));
    }

    /** Netzwerk-Konto auf der Netzwerk-Website, das einladen darf (aktiv, kein Schatten-Konto) */
    private function inviter(Request $r): array
    {
        $user = $this->network($r);
        if (!\Core\Invites::networkInviter((int) $user['id'])) throw new HttpException(403, __('Nur Netzwerk-Administratoren können weitere einladen.'));
        return $user;
    }

    private function inviteError(array $old, array $errors): Response
    {
        app()->session->set('_net_invite_old', $old + ['errors' => $errors]);
        return $this->back('/admin/network#net-invite', 'error', (string) reset($errors));
    }

    /** Nach dem Versand: Erfolg – oder den Link einmal anzeigen (E-Mail nicht zugestellt) */
    private function afterInvite(array $inv, string $token, array $sent, string $ok): Response
    {
        if ($sent['delivered']) return $this->back('/admin/network#konten', 'success', $ok);
        app()->session->set('_net_invite_link', ['email' => (string) $inv['email'], 'url' => \Core\Invites::url($token), 'error' => $sent['error']]);
        return $this->back('/admin/network#net-invite-link');
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
