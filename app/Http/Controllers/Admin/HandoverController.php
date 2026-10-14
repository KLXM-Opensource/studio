<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\HostHandover;
use Core\Http\Request;
use Core\Http\Response;
use Core\Network\Network;
use Core\RateLimiter;

/**
 * Domainwechsel: angemeldet auf eine andere Domain derselben Website wechseln (Core\HostHandover) – dort einen Passkey anlegen,
 * bevor die Hauptadresse umgestellt wird. POST /admin/account/domain (angemeldet, CSRF) → Zwischenseite → GET /admin/sso/domain?t=…
 * auf der Ziel-Domain (ohne Anmeldung erreichbar) → Konto-Seite, Bereich Passkeys.
 */
final class HandoverController extends AdminController
{
    public function start(Request $r): Response
    {
        $user = $this->auth($r);
        $host = \Core\Sites::normalizeHost($r->str('host')) ?? '';
        $back = $r->str('back') === 'domain' ? '/admin/system/domain' : '/admin/account#passkeys';
        if (!in_array($host, HostHandover::targets(), true)) return $this->back($back, 'error', __('Diese Domain gehört nicht zu dieser Website.'));
        $limiter = new RateLimiter(app()->db);
        $lk = 'handover:' . (int) $user['id'];
        if ($limiter->tooMany($lk, 10, 600)) return $this->back($back, 'error', __('Zu viele Anmeldungen in kurzer Zeit. Bitte warten Sie einige Minuten.'));
        $limiter->hit($lk);
        $token = HostHandover::issue((int) $user['id'], $host, (string) ($r->server['HTTP_USER_AGENT'] ?? ''));
        Network::log('handover.issue', site()->key, (string) $user['email'], strtolower($r->host()) . ' → ' . $host);
        return self::secure(HostHandover::page(HostHandover::link($host, HostHandover::PATH) . '?t=' . rawurlencode($token), $host));
    }

    public function land(Request $r): Response
    {
        $limiter = new RateLimiter(app()->db);
        $ipKey = 'handover-in:' . hash_hmac('sha256', $r->ip(), app()->key());
        $fail = fn(string $msg, int $status = 403): Response
            => $this->view('login', ['user' => null, 'error' => $msg, 'email' => '', 'next' => ''], $status)->header('Referrer-Policy', 'no-referrer');
        if ($limiter->tooMany($ipKey, 20, 600)) return $fail(__('Zu viele Anmeldeversuche. Bitte warten Sie einige Minuten.'), 429);
        $res = HostHandover::consume($r->str('t'), (string) \Core\Sites::normalizeHost($r->host()), (string) ($r->server['HTTP_USER_AGENT'] ?? ''));
        if (is_string($res)) {
            $limiter->hit($ipKey);
            return $fail($res);
        }
        app()->auth->login($res);
        $email = (string) (app()->db->fetchValue('SELECT email FROM users WHERE id = ?', [$res]) ?? '');
        Network::log('handover.login', site()->key, $email, strtolower($r->host()));
        app()->session->flash('success', __('Sie sind jetzt auf {host} angemeldet. Legen Sie hier einen Passkey für diese Domain an.', ['host' => strtolower($r->host())]));
        return Response::redirect(url(HostHandover::TARGET . '#passkeys'))->header('Referrer-Policy', 'no-referrer');
    }
}
