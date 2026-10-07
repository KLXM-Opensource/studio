<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Push\Keys;
use Core\Push\Push;
use Core\RateLimiter;

/**
 * Push-Benachrichtigungen in der Verwaltung (Core\Push): Konto → Benachrichtigungen (Gerät an-/abmelden, Geräte, Schalter je
 * Ereignis, Testnachricht) und Grundeinstellungen → Push-Benachrichtigungen (Schlüssel anlegen). Anmeldung und CSRF über auth().
 */
final class PushController extends AdminController
{
    /** Gerät der angemeldeten Person anmelden (JSON: subscription = PushSubscription.toJSON()) */
    public function subscribe(Request $r): Response
    {
        $user = $this->auth($r);
        if (!Push::enabled()) return Response::json(['ok' => false, 'error' => __('Push-Benachrichtigungen sind auf dieser Website nicht eingeschaltet.')], 404);
        $s = Push::parse($r->post['subscription'] ?? null);
        if (!$s) return Response::json(['ok' => false, 'error' => __('Dieser Browser bzw. sein Push-Dienst wird nicht unterstützt.')], 422);
        // Tägliches Auffrischen (_push.js): nur, solange das Gerät noch zu dieser Person gehört – ein entferntes Gerät meldet sich nicht still wieder an
        if (!empty($r->post['refresh'])) {
            $row = Push::find($s['endpoint']);
            if (!$row || (int) $row['user_id'] !== (int) $user['id']) return Response::json(['ok' => false, 'state' => 'removed']);
        }
        [$id, $err] = Push::store($s, (int) $user['id'], [], null, (string) ($r->server['HTTP_USER_AGENT'] ?? ''));
        if ($err === 'conflict') return Response::json(['ok' => false, 'error' => 'conflict'], 409);
        return Response::json(['ok' => true, 'id' => $id, 'devices' => count(Push::devices((int) $user['id']))]);
    }

    /** Dieses Gerät abmelden (JSON: endpoint) → state deleted (Browser-Abo kann weg) | unlinked (bleibt für Website-Abos) */
    public function unsubscribe(Request $r): Response
    {
        $user = $this->auth($r);
        $state = Push::unlinkUser((int) $user['id'], null, (string) ($r->post['endpoint'] ?? ''));
        return Response::json(['ok' => true, 'state' => $state ?? 'unknown']);
    }

    /** Gerät aus der Liste entfernen (Formular) */
    public function deleteDevice(Request $r, string $id): Response
    {
        $user = $this->auth($r);
        $state = Push::unlinkUser((int) $user['id'], (int) $id);
        return $r->wantsJson() ? Response::json(['ok' => $state !== null, 'state' => $state])
            : $this->back('/admin/account#benachrichtigungen', $state ? 'success' : 'error', $state ? __('Gerät entfernt – es erhält keine Mitteilungen der Verwaltung mehr.') : __('Gerät nicht gefunden.'));
    }

    /** Schalter je Ereignis (Formular events[schlüssel] = 0|1; JSON für sofortiges Speichern) */
    public function events(Request $r): Response
    {
        $user = $this->auth($r);
        $visible = Push::visibleEvents($user);
        $on = [];
        foreach ((array) ($r->post['events'] ?? []) as $k => $v) {
            if (isset($visible[(string) $k])) $on[(string) $k] = (string) $v === '1' || $v === true;
        }
        Push::saveUserEvents((int) $user['id'], $on);
        return $r->wantsJson() ? Response::json(['ok' => true]) : $this->back('/admin/account#benachrichtigungen', 'success', __('Benachrichtigungen gespeichert.'));
    }

    /** Testnachricht an alle eigenen Geräte (höchstens 5 in 10 Minuten) */
    public function test(Request $r): Response
    {
        $user = $this->auth($r);
        $limiter = new RateLimiter(app()->db);
        $key = 'push-test:' . (int) $user['id'];
        if ($limiter->tooMany($key, 5, 600)) {
            $msg = __('Zu viele Testnachrichten. Bitte warten Sie ein paar Minuten.');
            return $r->wantsJson() ? Response::json(['ok' => false, 'error' => $msg], 429) : $this->back($this->origin($r), 'error', $msg);
        }
        $limiter->hit($key);
        $res = Push::test((int) $user['id']);
        $ok = $res['sent'] > 0;
        $msg = $ok ? __('Testnachricht an {n} von {total} Gerät(en) gesendet.', ['n' => $res['sent'], 'total' => $res['devices']])
            : (string) ($res['error'] ?? __('Die Testnachricht konnte nicht gesendet werden.'));
        if ($ok && $res['failed']) $msg .= ' ' . __('Fehler: {error}', ['error' => (string) $res['error']]);
        return $r->wantsJson() ? Response::json(['ok' => $ok, 'message' => $msg] + $res) : $this->back($this->origin($r), $ok ? 'success' : 'error', $msg);
    }

    /** Grundeinstellungen → Push-Benachrichtigungen: fehlende VAPID-Schlüssel anlegen */
    public function keys(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        if (Keys::ready()) return $this->back('/admin/system#push', 'success', __('Die Schlüssel sind schon vorhanden.'));
        $ok = Keys::ensure();
        Push::flush();
        return $this->back('/admin/system#push', $ok ? 'success' : 'error', $ok ? __('VAPID-Schlüssel angelegt (config/config.local.php).')
            : __('Die Schlüssel konnten nicht in config/config.local.php geschrieben werden (Datei nicht beschreibbar) – bitte php bin/console push:keys --generate ausführen.'));
    }

    /** Zurück zur aufrufenden Seite der Verwaltung (Konto oder Grundeinstellungen) */
    private function origin(Request $r): string
    {
        return ($r->post['back'] ?? '') === 'system' ? '/admin/system#push' : '/admin/account#benachrichtigungen';
    }
}
