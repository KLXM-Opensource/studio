<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\Network\Network;
use Core\Oidc\Accounts;
use Core\Oidc\Client;
use Core\Oidc\OidcException;
use Core\Oidc\Providers;
use Core\RateLimiter;
use Core\SecurityLog;
use Core\Sites;

/**
 * Anmeldung per OpenID Connect (Core\Oidc) – Standard aus.
 *
 *   GET /admin/login/oidc            Anmeldung beginnen (Knopf der Anmeldeseite; auch vom Anbieter ausgelöst mit ?iss=…,
 *                                    „initiate_login_uri“ nach OIDC Core 4). Auf anderen Websites eines Netzwerks mit
 *                                    Netzwerk-Anbieter: weiter zur Netzwerk-Website (?site=…), danach Einmal-Anmeldung.
 *   GET /admin/login/oidc/callback   Rückkehr vom Anbieter (state, code) → Konto (Core\Oidc\Accounts) → Sitzung
 *   POST /admin/users/oidc           Einstellungen (Benutzer & Rollen → Anmeldung & Sicherheit, Recht users.manage)
 */
final class OidcController extends AdminController
{
    private function fail(string $msg, int $status = 403): Response
    {
        SecurityLog::write('oidc-failed');
        (new RateLimiter(app()->db))->hit($this->ipKey());
        return $this->view('login', ['user' => null, 'error' => $msg, 'email' => '', 'next' => '', 'css' => ['css/passkey.css']], $status)
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function ipKey(): string
    {
        return 'oidc:' . hash_hmac('sha256', (string) (app()->request?->ip() ?? ''), app()->key());
    }

    private function limited(): bool
    {
        return (new RateLimiter(app()->db))->tooMany($this->ipKey(), 20, 600);
    }

    /** Ziel nach der Anmeldung: nur Pfade dieser Website (wie die Einmal-Anmeldung) */
    private static function safeNext(string $next): string
    {
        return $next === '' ? '/admin' : Network::safePath($next);
    }

    public function start(Request $r): Response
    {
        if ($this->limited()) return $this->fail(__('Zu viele Anmeldeversuche. Bitte warten Sie einige Minuten.'), 429);
        $p = Providers::forLogin();
        if (!$p) return Response::redirect(url('/admin/login'));
        // Vom Anbieter ausgelöst (iss): nur der eingestellte Anbieter
        $iss = $r->str('iss');
        if ($iss !== '' && !hash_equals($p['issuer'], rtrim($iss, '/'))) return $this->fail(__('Unbekannter Anbieter.'), 400);
        $next = self::safeNext($r->str('next'));
        // Anbieter der Netzwerk-Website: Anmeldung dort, danach Einmal-Anmeldung hierher
        if ($p['site'] !== site()->key) {
            return Response::redirect(Network::siteLink($p['site'], '/admin/login/oidc') . '?' . http_build_query(['site' => site()->key, 'next' => $next]))
                ->header('Referrer-Policy', 'no-referrer');
        }
        $target = $r->str('site');
        if ($target !== '' && ($target === site()->key || !isset(Sites::all()[$target]) || $p['role'] !== 'network')) $target = '';
        if (app()->auth->check() && $target === '') return Response::redirect(url($next));
        try {
            $url = Client::begin($p, ['next' => $next, 'site' => $target, 'login_hint' => $r->str('login_hint')]);
        } catch (\Throwable $e) {
            error_log('[oidc] ' . $e->getMessage());
            return $this->fail($e instanceof OidcException ? $e->getMessage() : __('Der Anbieter ist gerade nicht erreichbar.'), 502);
        }
        return Response::redirect($url)->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function callback(Request $r): Response
    {
        if ($this->limited()) return $this->fail(__('Zu viele Anmeldeversuche. Bitte warten Sie einige Minuten.'), 429);
        $pend = Client::takeState($r->str('state'));
        if (!$pend) return $this->fail(__('Die Anmeldung ist abgelaufen oder ungültig. Bitte erneut beginnen.'), 400);
        $p = Providers::get((string) $pend['p']);
        if (!$p) return $this->fail(__('Die Anmeldung über diesen Anbieter ist ausgeschaltet.'), 400);
        // RFC 9207: nennt der Anbieter seinen Issuer, muss er passen (Schutz vor Verwechslung mehrerer Anbieter)
        if ($r->str('iss') !== '' && !hash_equals($p['issuer'], rtrim($r->str('iss'), '/'))) return $this->fail(__('Die Antwort stammt von einem anderen Anbieter.'), 400);
        if (($err = $r->str('error')) !== '') {
            $code = preg_replace('~[^\w.-]~', '', $err);
            return $this->fail($code === 'access_denied' ? __('Die Anmeldung wurde beim Anbieter abgebrochen oder ist für diese Website nicht freigegeben.')
                : __('Der Anbieter meldet einen Fehler ({error}).', ['error' => mb_substr($code, 0, 40)]), 400);
        }
        try {
            $res = Client::finish($p, $pend, $r->str('code'));
            $row = Accounts::resolve($p, $res['claims']);
        } catch (OidcException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            error_log('[oidc] ' . $e->getMessage());
            return $this->fail(__('Die Anmeldung über den Anbieter ist fehlgeschlagen.'), 502);
        }
        $amr = array_values(array_unique(array_merge(['fed'], array_filter(array_map('strval', (array) ($res['claims']['amr'] ?? [])), fn($v) => preg_match('~^[a-z]{2,10}$~', $v)),
            $p['mfa'] === 'trust' || Client::strongAmr($res['claims']) ? ['mfa'] : [])));
        $next = self::safeNext((string) $pend['next']);
        if ($p['role'] === 'network') {
            if ($e2 = Network::loginShadow($row, 'oidc.login', $amr)) return $this->fail($e2);
        } else {
            app()->auth->login((int) $row['id'], ['amr' => $amr]);
        }
        (new RateLimiter(app()->db))->clear($this->ipKey());
        // Für das Abmelden beim Anbieter (RP-initiated logout)
        if ($p['logout']) app()->session->set('oidc_end', ['p' => $p['id'], 't' => $res['id_token']]);
        $target = (string) $pend['site'];
        if ($target !== '' && $p['role'] === 'network' && isset(Sites::all()[$target])) {
            $tok = Network::issue((int) $row['id'], $target, $next, (string) ($r->server['HTTP_USER_AGENT'] ?? ''));
            Network::log('open', $target, (string) $row['email'], 'oidc');
            return Response::redirect(Network::siteLink($target, '/admin/sso') . '?t=' . rawurlencode($tok))->header('Referrer-Policy', 'no-referrer');
        }
        if ($next === '/admin' && Network::isNetworkSite() && Network::isNetworkUser()) $next = '/admin/network';
        return Response::redirect(url($next))->header('Referrer-Policy', 'no-referrer');
    }

    /** Abmelden beim Anbieter, falls eingestellt: Adresse oder null (AuthController::logout ruft vor dem Beenden der Sitzung) */
    public static function providerLogout(): ?string
    {
        $end = app()->session->get('oidc_end');
        if (!is_array($end) || empty($end['t'])) return null;
        $p = Providers::get((string) ($end['p'] ?? ''));
        return $p && $p['logout'] ? Client::logoutUrl($p, (string) $end['t'], absolute_url('/admin/login')) : null;
    }

    /** Einstellungen speichern (Benutzer & Rollen → Anmeldung & Sicherheit) */
    public function save(Request $r): Response
    {
        $this->auth($r, 'users.manage');
        if (!Csrf::valid($r)) return $this->back('/admin/users/sicherheit#oidc', 'error', __('Sitzung abgelaufen – bitte erneut versuchen.'));
        if (is_array(app()->config->get('oidc')) && app()->config->get('oidc')) {
            return $this->back('/admin/users/sicherheit#oidc', 'error', __('Die Anmeldung über einen Anbieter ist in der Konfiguration der Website festgelegt.'));
        }
        $err = Providers::save((array) ($r->post['oidc'] ?? []));
        if ($err) return $this->back('/admin/users/sicherheit#oidc', 'error', $err);
        // Verbindung gleich prüfen (Discovery), wenn eingeschaltet
        $p = Providers::all()['main'] ?? null;
        if ($p && Providers::ready($p)) {
            try {
                Client::discovery($p['issuer'], true);
            } catch (\Throwable $e) {
                return $this->back('/admin/users/sicherheit#oidc', 'error', __('Gespeichert, aber der Anbieter ist nicht erreichbar: {msg}', ['msg' => mb_strimwidth($e->getMessage(), 0, 160, '…')]));
            }
        }
        return $this->back('/admin/users/sicherheit#oidc', 'success', __('Anmeldung über einen Anbieter gespeichert.'));
    }
}
