<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Chat\Chat;
use Core\Chat\Messages;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Sse;

/**
 * Chat zwischen Benutzern der Verwaltung (Core\Chat). JSON-Endpunkte unter /admin/api/chat/* für resources/js/userchat.js,
 * Live-Aktualisierung per Server-Sent Events (/admin/api/chat/stream, ≤ 25 s je Verbindung) oder – auf dem
 * Entwicklungsserver – per Abfrage (/admin/api/chat/poll). Seiten: /admin/chat (Vollbild) und /admin/chat/einstellungen.
 */
final class ChatController extends AdminController
{
    /** Anmeldung + Chat-Recht; Person im Verzeichnis vormerken */
    private function gate(Request $r): array
    {
        $u = $this->auth($r);
        if (!Chat::canUse()) throw new HttpException(403, __('Der Chat ist für Ihr Konto nicht freigeschaltet.'));
        Chat::register();
        return $u;
    }

    private function roomOr404(string $id): array
    {
        return Chat::room((int) $id) ?? throw new HttpException(404, __('Unterhaltung nicht gefunden.'));
    }

    private function messageOr404(string $id): array
    {
        $m = Messages::find((int) $id);
        if (!$m || !Chat::room((int) $m['room_id'])) throw new HttpException(404, __('Nachricht nicht gefunden.'));
        return $m;
    }

    private static function fail(string $msg, int $status = 422): Response
    {
        return Response::json(['ok' => false, 'error' => $msg], $status);
    }

    // ------------------------------------------------------------------ Seiten

    /** Vollbild-Ansicht (ohne JavaScript: Hinweis) */
    public function page(Request $r): Response
    {
        $this->gate($r);
        return $this->view('chat/index', ['title' => __('Chat'), 'room' => (int) $r->str('raum')]);
    }

    public function settings(Request $r): Response
    {
        $this->auth($r);
        if (!Chat::settingsVisible()) throw new HttpException(403, __('Keine Berechtigung.'));
        if (Chat::canUse()) Chat::register();
        $me = Chat::canUse() ? Chat::person(Chat::me()) : null;
        return $this->view('chatcfg', ['title' => __('Chat-Einstellungen'), 'channels' => Chat::canManage() ? Chat::manageableChannels() : [],
            'roles' => \Core\Permissions::roles(), 'users' => Chat::siteOn() ? Chat::siteUsers() : [], 'me' => $me, 'edit' => (int) $r->str('kanal')]);
    }

    public function saveSettings(Request $r): Response
    {
        $this->auth($r);
        if (!Chat::settingsVisible()) throw new HttpException(403, __('Keine Berechtigung.'));
        if (Chat::canConfigure()) {
            if (Chat::configured() === null) {
                $on = $r->str('site_on') === '1';
                app()->settings->set('sys.userchat_enabled', $on ? 1 : 0);
                if ($on) Chat::ensureDefaultChannel();
            }
            Chat::setMeta('network', $r->str('network_on') === '1' ? '1' : '0');
            Chat::setMeta('retention_days', (string) max(0, min(3650, (int) $r->str('retention', (string) Chat::RETENTION_DEFAULT))));
        }
        return $this->back('/admin/chat/einstellungen', 'success', __('Gespeichert.'));
    }

    /** Eigene E-Mail-Hinweise zu Erwähnungen (JSON oder Formular) */
    public function prefs(Request $r): Response
    {
        $this->gate($r);
        Chat::db()->update('people', ['notify_mail' => $r->str('mail') === '1' ? 1 : 0], 'user_key = :k', ['k' => Chat::me()]);
        return $r->wantsJson() ? Response::json(['ok' => true]) : $this->back('/admin/chat/einstellungen', 'success', __('Gespeichert.'));
    }

    public function channelSave(Request $r, string $id = ''): Response
    {
        $this->auth($r);
        if (!Chat::canManage()) throw new HttpException(403, __('Keine Berechtigung.'));
        $room = null;
        if ($id !== '') {
            $room = Chat::db()->fetch("SELECT * FROM rooms WHERE id = ? AND kind = 'channel'", [(int) $id]);
            if (!$room || ($room['scope'] === 'site' && $room['site'] !== site()->key) || ($room['scope'] === 'network' && !Chat::isNet())) throw new HttpException(404);
        }
        $err = Chat::saveChannel($room, ['name' => $r->str('name'), 'topic' => $r->str('topic'), 'scope' => $r->str('scope'),
            'roles' => (array) ($r->post['roles'] ?? []), 'users' => (array) ($r->post['users'] ?? [])]);
        if ($err !== null) {
            return $this->back('/admin/chat/einstellungen' . ($room ? '?kanal=' . (int) $room['id'] : '') . '#kanaele', 'error', $err);
        }
        return $this->back('/admin/chat/einstellungen#kanaele', 'success', $room ? __('Kanal gespeichert.') : __('Kanal angelegt.'));
    }

    public function channelUpdate(Request $r, string $id): Response
    {
        return $this->channelSave($r, $id);
    }

    public function channelArchive(Request $r, string $id): Response
    {
        $this->auth($r);
        if (!Chat::canManage()) throw new HttpException(403, __('Keine Berechtigung.'));
        $room = Chat::db()->fetch("SELECT * FROM rooms WHERE id = ? AND kind = 'channel'", [(int) $id]);
        if (!$room || ($room['scope'] === 'site' && $room['site'] !== site()->key) || ($room['scope'] === 'network' && !Chat::isNet())) throw new HttpException(404);
        $archive = !(int) $room['archived'];
        Chat::archiveChannel($room, $archive);
        return $this->back('/admin/chat/einstellungen#kanaele', 'success', $archive ? __('Kanal archiviert.') : __('Kanal wiederhergestellt.'));
    }

    /** Bild – nur für Personen, die die Unterhaltung sehen dürfen */
    public function file(Request $r, string $id): Response
    {
        $this->gate($r);
        $f = Messages::file((int) $id) ?? throw new HttpException(404);
        $path = Chat::dir('files/' . $f['stored']);
        if (!is_file($path)) throw new HttpException(404);
        $name = preg_replace('~[^\w.\- ]+~u', '_', (string) $f['name']) ?: 'bild';
        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => (string) $f['mime'],
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => ($r->str('download') === '1' ? 'attachment' : 'inline') . '; filename="' . $name . '"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    // ------------------------------------------------------------------ JSON

    /** Räume, Zähler, eigene Einstellungen, letzte Ereignis-ID, Übertragungsart */
    public function state(Request $r): Response
    {
        $this->gate($r);
        Chat::maybeHousekeeping();
        $unread = Chat::unreadByRoom();
        $rooms = [];
        foreach (Chat::rooms() as $id => $room) {
            $label = Chat::roomLabel($room);
            $rooms[] = ['id' => $id, 'kind' => $room['kind'], 'scope' => $room['scope'], 'name' => $label['name'], 'site' => $label['site'],
                'topic' => (string) $room['topic'], 'unread' => $unread[$id]['unread'] ?? 0, 'mentions' => $unread[$id]['mentions'] ?? 0,
                'lastAt' => $room['last_msg_at'] ? date('c', strtotime((string) $room['last_msg_at'])) : null, 'lastId' => (int) $room['last_msg_id'], 'with' => $label['key'] ?? null];
        }
        [$u, $m] = Chat::totals();
        $me = Chat::person(Chat::me());
        return Response::json(['ok' => true, 'rooms' => $rooms, 'unread' => $u, 'mentions' => $m, 'lastEventId' => Chat::lastEventId(),
            'transport' => Chat::transport(), 'mail' => (int) ($me['notify_mail'] ?? 1) === 1, 'me' => Chat::me(), 'myName' => Chat::myName(),
            'site' => Chat::canSite(), 'network' => Chat::canNetwork(), 'canManage' => Chat::canManage()]);
    }

    /** Zähler für die Seitenleiste (langsame Abfrage bei geschlossenem Chat) */
    public function unread(Request $r): Response
    {
        $this->gate($r);
        Chat::maybeHousekeeping();
        [$u, $m] = Chat::totals();
        return Response::json(['ok' => true, 'unread' => $u, 'mentions' => $m, 'lastEventId' => Chat::lastEventId()]);
    }

    /** Personen für Direktnachrichten und @Erwähnungen. scope=site|network, room=ID (nur Mitglieder dieses Raums) */
    public function people(Request $r): Response
    {
        $this->gate($r);
        if (($rid = (int) $r->str('room')) > 0) {
            $room = $this->roomOr404((string) $rid);
            $list = Chat::audience($room);
            $scope = $room['scope'];
        } else {
            $scope = $r->str('scope') === 'network' ? 'network' : 'site';
            $list = $scope === 'network' ? (Chat::canNetwork() ? Chat::networkPeople() : []) : (Chat::canSite() ? Chat::siteUsers() : []);
        }
        $q = mb_strtolower(trim(mb_substr($r->str('q'), 0, 60)));
        $me = Chat::me();
        $out = [];
        foreach ($list as $p) {
            if ($p['key'] === $me) continue;
            if ($q !== '' && !str_contains(mb_strtolower($p['name'] . ' ' . $p['email']), $q)) continue;
            $label = Chat::personLabel($p['key'], $p['name']);
            $out[] = ['key' => $p['key'], 'name' => $label['name'], 'site' => $scope === 'network' ? \Core\Support\Support::siteLabel(explode(':', $p['key'])[0] === 'net' ? \Core\Network\Network::siteKey() : explode(':', $p['key'])[0]) : $label['site']];
            if (count($out) >= 50) break;
        }
        return Response::json(['ok' => true, 'people' => $out]);
    }

    /** Direktnachricht öffnen/anlegen: user=Kennung, scope=site|network */
    public function dm(Request $r): Response
    {
        $this->gate($r);
        try {
            $room = Chat::openDm($r->str('user'), $r->str('scope') === 'network' ? 'network' : 'site');
        } catch (\InvalidArgumentException $e) {
            return self::fail($e->getMessage());
        }
        return Response::json(['ok' => true, 'room' => (int) $room['id']]);
    }

    public function messages(Request $r, string $id): Response
    {
        $this->gate($r);
        $room = $this->roomOr404($id);
        [$list, $more] = Messages::list($room, (int) $r->str('before'));
        return Response::json(['ok' => true, 'messages' => $list, 'more' => $more]);
    }

    public function send(Request $r, string $id): Response
    {
        $this->gate($r);
        $room = $this->roomOr404($id);
        if (!$this->throttle('send', 30)) return self::fail(__('Zu viele Nachrichten in kurzer Zeit. Bitte kurz warten.'), 429);
        [$m, $err] = Messages::send($room, (string) ($r->post['body'] ?? ''), (array) ($r->post['files'] ?? []));
        if ($err !== null) return self::fail($err);
        return Response::json(['ok' => true, 'message' => Messages::toJson($m)]);
    }

    /** Bild hochladen (vor dem Senden) – Feld „file“ */
    public function upload(Request $r, string $id): Response
    {
        $this->gate($r);
        $room = $this->roomOr404($id);
        $f = $r->files['file'] ?? null;
        if (!is_array($f) || is_array($f['name'] ?? null)) return self::fail(__('Bitte ein Bild auswählen.'));
        [$file, $err] = Messages::upload($room, $f);
        return $err !== null ? self::fail($err) : Response::json(['ok' => true, 'file' => $file]);
    }

    public function read(Request $r, string $id): Response
    {
        $this->gate($r);
        $room = $this->roomOr404($id);
        Chat::markRead((int) $room['id'], min((int) $r->str('last_id'), (int) $room['last_msg_id'] ?: PHP_INT_MAX));
        [$u, $m] = Chat::totals();
        return Response::json(['ok' => true, 'unread' => $u, 'mentions' => $m]);
    }

    public function edit(Request $r, string $id): Response
    {
        $this->gate($r);
        $m = $this->messageOr404($id);
        $err = Messages::edit($m, (string) ($r->post['body'] ?? ''));
        return $err !== null ? self::fail($err) : Response::json(['ok' => true, 'message' => Messages::toJson(Messages::find((int) $m['id']))]);
    }

    public function delete(Request $r, string $id): Response
    {
        $this->gate($r);
        $m = $this->messageOr404($id);
        $err = Messages::delete($m);
        return $err !== null ? self::fail($err, 403) : Response::json(['ok' => true, 'message' => Messages::toJson(Messages::find((int) $m['id']))]);
    }

    public function react(Request $r, string $id): Response
    {
        $this->gate($r);
        $m = $this->messageOr404($id);
        $err = Messages::react($m, $r->str('emoji'));
        return $err !== null ? self::fail($err) : Response::json(['ok' => true, 'message' => Messages::toJson(Messages::find((int) $m['id']))]);
    }

    /** Abfrage-Modus (Entwicklungsserver, Rückfall): Ereignisse seit last_id, sofort zurück */
    public function poll(Request $r): Response
    {
        $this->gate($r);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $last = (int) $r->str('last_id');
        if ($last <= 0) $last = Chat::lastEventId();
        [$events, $last] = Chat::eventsSince($last);
        return Response::json(['ok' => true, 'events' => $events, 'lastEventId' => $last]);
    }

    /**
     * Server-Sent Events: hält höchstens chat_stream_seconds (≤ 25 s), fragt etwa jede Sekunde den Ereignis-Feed ab
     * (eine Abfrage über den Primärschlüssel), sendet „retry: 2000“ und schließt – EventSource verbindet sich mit
     * Last-Event-ID neu. Auf dem Entwicklungsserver (php -S) 409 → die Oberfläche fragt stattdessen regelmäßig ab.
     */
    public function stream(Request $r): Response
    {
        $this->gate($r);
        if (Chat::transport() !== 'sse') {
            return Response::json(['ok' => false, 'error' => 'poll', 'transport' => 'poll'], 409);
        }
        $last = (int) ((string) ($r->server['HTTP_LAST_EVENT_ID'] ?? '') !== '' ? $r->server['HTTP_LAST_EVENT_ID'] : $r->str('last_id'));
        if ($last <= 0) $last = Chat::lastEventId();
        $seconds = Chat::streamSeconds();
        return Sse::stream(function (Sse $sse) use ($last, $seconds) {
            @set_time_limit($seconds + 20);
            $end = microtime(true) + $seconds;
            $beat = microtime(true);
            $sse->send('hello', ['lastEventId' => $last, 'seconds' => $seconds], (string) $last);
            while (microtime(true) < $end) {
                [$events, $next] = Chat::eventsSince($last);
                foreach ($events as $ev) {
                    if (!$sse->send('chat', $ev, (string) $ev['id'])) return;
                }
                if ($next !== $last && !$events) {
                    // Nur fremde Ereignisse: Stand trotzdem merken (id ohne Daten → Last-Event-ID aktuell)
                    if (!$sse->send('skip', ['lastEventId' => $next], (string) $next)) return;
                }
                $last = $next;
                if (microtime(true) - $beat >= 10) {
                    if (!$sse->ping()) return;
                    $beat = microtime(true);
                }
                if ($sse->aborted()) return;
                usleep(1_000_000);
            }
            $sse->send('bye', ['lastEventId' => $last], (string) $last);
        }, [], 2000);
    }

    /** Einfache Bremse je Sitzung: höchstens $max Aktionen je Minute */
    private function throttle(string $key, int $max): bool
    {
        $k = 'chat_rl_' . $key;
        $now = time();
        $list = array_values(array_filter((array) app()->session->get($k, []), fn($t) => $t > $now - 60));
        if (count($list) >= $max) return false;
        $list[] = $now;
        app()->session->set($k, $list);
        return true;
    }
}
