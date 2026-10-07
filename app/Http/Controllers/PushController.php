<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Data\Tables;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Push\Channels;
use Core\Push\Keys;
use Core\Push\Push;
use Core\Push\Topics;
use Core\Push\Visitor;
use Core\Push\WebPush;
use Core\RateLimiter;

/**
 * Öffentliche Seite der Push-Benachrichtigungen (Funktion „push“, sonst 404 – Features::allowsPath):
 *   GET  /push-sw.js              Service Worker (resources/sw/push.js) – Bereich {base}/push-sw/, steuert keine Seiten
 *   POST /api/push/subscribe      {subscription, topics[], offered[]?, lang, token} – Abo für öffentliche Kanäle (Core\Push\Channels)
 *   POST /api/push/unsubscribe    {endpoint, auth, topics[]|null, token} – Themen entfernen (ohne Themen: Abo löschen)
 *   POST /api/push/status         {endpoint, auth, token} → abonnierte Themen dieses Browsers
 *   POST /api/push/renew          {old, subscription} – vom Service Worker bei pushsubscriptionchange
 * Schutz statt Sitzung (Besucher haben keine): nur JSON, nur von dieser Website (Origin bzw. Sec-Fetch-Site), signiertes Merkmal
 * der Website, Begrenzung je Anschluss (IP nur als HMAC); Änderungen am Abo nur mit Endpunkt + Auth-Geheimnis (kennt nur der Browser).
 */
final class PushController
{
    public function serviceWorker(Request $r): Response
    {
        if (!Push::enabled()) throw new HttpException(404);
        $js = strtr((string) file_get_contents(ROOT . '/resources/sw/push.js'), [
            '__PUSH_KEY__' => json_encode(Keys::publicB64()),
            '__PUSH_RENEW__' => json_encode(url(Push::API . '/renew'), JSON_UNESCAPED_SLASHES),
        ]);
        return (new Response($js))
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'no-cache')
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * Abo für Kanäle: topics = gewählte Kanäle. Mit offered (die Kanäle, die das Formular angeboten hat – Mehrfachauswahl in Block,
     * Banner, Glocke) gilt die Auswahl als vollständig: angebotene, aber nicht gewählte Kanäle werden abbestellt; andere bleiben.
     */
    public function subscribe(Request $r): Response
    {
        if ($deny = $this->guard($r, 'sub', Push::VISITOR_PER_HOUR)) return $deny;
        $public = Channels::publicTopics();
        $pick = function (mixed $list) use ($public): ?array {
            $out = [];
            foreach (array_slice((array) $list, 0, 20) as $t) {
                if (!in_array((string) $t, $public, true)) return null;
                $out[] = (string) $t;
            }
            return array_values(array_unique($out));
        };
        $topics = $pick($r->post['topics'] ?? []);
        $offered = isset($r->post['offered']) ? $pick($r->post['offered']) : null;
        if ($topics === null || (isset($r->post['offered']) && $offered === null) || (!$topics && $offered === null)) {
            return self::json(['ok' => false, 'error' => lt('Diese Inhalte lassen sich nicht abonnieren.')], 422);
        }
        $s = Push::parse($r->post['subscription'] ?? null);
        if (!$s) return self::json(['ok' => false, 'error' => lt('Dieser Browser bzw. Push-Dienst wird nicht unterstützt.')], 422);
        $lang = (string) ($r->post['lang'] ?? '');
        $row = Push::find($s['endpoint']);
        if (!$topics) {
            // Nichts gewählt: angebotene Kanäle abbestellen (nur mit passendem Geheimnis)
            $state = $row && Push::owns($row, $s['auth']) ? Push::removeTopics($row, $offered) : 'unknown';
            $left = $state === 'updated' ? Push::topicsOf(Push::find($s['endpoint']) ?? []) : [];
            return self::json(['ok' => true, 'state' => $state, 'topics' => $left]);
        }
        [$id, $err] = Push::store($s, null, $topics, \Core\Lang::valid($lang) ? $lang : null, (string) ($r->server['HTTP_USER_AGENT'] ?? ''));
        if ($err === 'conflict') return self::json(['ok' => false, 'error' => 'conflict'], 409);
        $row = app()->db->fetch('SELECT * FROM push_subscriptions WHERE id = ?', [(int) $id]);
        if ($row && $offered !== null && ($drop = array_values(array_diff($offered, $topics)))) {
            Push::removeTopics($row, $drop);
            $row = app()->db->fetch('SELECT * FROM push_subscriptions WHERE id = ?', [(int) $id]);
        }
        return self::json(['ok' => true, 'state' => 'updated', 'topics' => Push::topicsOf($row ?? [])]);
    }

    public function unsubscribe(Request $r): Response
    {
        if ($deny = $this->guard($r, 'uns', 60)) return $deny;
        $row = $this->own($r->post);
        if (!$row) return self::json(['ok' => true, 'state' => 'unknown', 'topics' => []]);
        $topics = isset($r->post['topics']) && is_array($r->post['topics']) ? array_map('strval', $r->post['topics']) : null;
        $state = Push::removeTopics($row, $topics);
        $left = $state === 'deleted' ? [] : Push::topicsOf(app()->db->fetch('SELECT topics FROM push_subscriptions WHERE id = ?', [(int) $row['id']]) ?? []);
        return self::json(['ok' => true, 'state' => $state, 'topics' => $left]);
    }

    public function status(Request $r): Response
    {
        // Ohne Begrenzung (bei jedem Seitenaufruf mit dem Block; liest nur – das 16-Byte-Geheimnis ist nicht zu erraten)
        if ($deny = $this->guard($r, null, 0)) return $deny;
        $row = $this->own($r->post);
        if ($row && strtotime((string) ($row['last_seen'] ?? '')) < time() - 86400) {
            app()->db->update('push_subscriptions', ['last_seen' => now()], 'id = :id', ['id' => (int) $row['id']]);
        }
        return self::json(['ok' => true, 'topics' => $row ? Push::topicsOf($row) : []]);
    }

    /** Neues Abo eines Browsers (pushsubscriptionchange im Service Worker): Themen und Konto vom alten übernehmen */
    public function renew(Request $r): Response
    {
        if ($deny = $this->guard($r, 'rn', Push::VISITOR_PER_HOUR, false)) return $deny;
        $old = is_array($r->post['old'] ?? null) ? $r->post['old'] : [];
        $row = $this->own(['endpoint' => $old['endpoint'] ?? '', 'auth' => $old['keys']['auth'] ?? '']);
        $s = Push::parse($r->post['subscription'] ?? null);
        if (!$row || !$s) return self::json(['ok' => false], 422);
        app()->db->update('push_subscriptions', ['endpoint' => $s['endpoint'], 'endpoint_hash' => hash('sha256', $s['endpoint']),
            'p256dh' => WebPush::b64u($s['p256dh']), 'auth' => WebPush::b64u($s['auth']), 'key_fp' => Keys::fingerprint(), 'last_seen' => now(), 'failures' => 0],
            'id = :id', ['id' => (int) $row['id']]);
        return self::json(['ok' => true]);
    }

    // ------------------------------------------------------------------ intern

    /** Abo, für das der Aufrufer Endpunkt und Auth-Geheimnis kennt */
    private function own(array $in): ?array
    {
        $ep = (string) ($in['endpoint'] ?? '');
        $auth = WebPush::unb64u((string) ($in['auth'] ?? ''));
        if ($ep === '' || $auth === false) return null;
        $row = Push::find($ep);
        return $row && Push::owns($row, $auth) ? $row : null;
    }

    /** Funktion an, JSON, gleiche Website, Merkmal, Begrenzung je Anschluss und Art ($bucket null = keine) → null oder Fehlerantwort */
    private function guard(Request $r, ?string $bucket, int $perHour, bool $token = true): ?Response
    {
        if (!Push::enabled()) return self::json(['ok' => false, 'error' => lt('Push-Mitteilungen sind auf dieser Website nicht eingeschaltet.')], 404);
        if (!str_contains((string) ($r->server['CONTENT_TYPE'] ?? ''), 'application/json')) return self::json(['ok' => false], 415);
        if (!self::sameOrigin($r)) return self::json(['ok' => false], 403);
        if ($token && !hash_equals(Visitor::token(), (string) ($r->post['token'] ?? ''))) return self::json(['ok' => false], 403);
        if ($bucket !== null) {
            $limiter = new RateLimiter(app()->db);
            $key = 'push-' . $bucket . ':' . substr(hash_hmac('sha256', $r->ip(), app()->key()), 0, 32);
            if ($limiter->tooMany($key, $perHour, 3600)) {
                return self::json(['ok' => false, 'error' => lt('Zu viele Anfragen. Bitte versuchen Sie es später noch einmal.')], 429);
            }
            $limiter->hit($key);
        }
        return null;
    }

    /** Anfrage von dieser Website? (Origin = Host; Browser ohne Origin: Sec-Fetch-Site same-origin) */
    public static function sameOrigin(Request $r): bool
    {
        $site = strtolower((string) ($r->server['HTTP_SEC_FETCH_SITE'] ?? ''));
        if ($site !== '' && $site !== 'same-origin') return false;
        $origin = (string) ($r->server['HTTP_ORIGIN'] ?? '');
        if ($origin === '') return $site === 'same-origin';
        $u = parse_url($origin);
        $auth = strtolower(($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : ''));
        return $auth !== '' && $auth === strtolower($r->host());
    }

    private static function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status)->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }
}
