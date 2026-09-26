<?php
declare(strict_types=1);

namespace Core\Chat;

use Core\Support\Markdown;
use Core\Support\Support;
use Core\Support\Tickets;

/**
 * Nachrichten des Chats: senden, bearbeiten, löschen (weich), Reaktionen, Erwähnungen, Bilder, Ausgabe, E-Mail-Hinweise.
 *
 * Text: Markdown-lite (Core\Support\Markdown – nie HTML). Adressen der Verwaltung (/admin/pages/12, /admin/data/news/5,
 * /admin/media#m33, Support-Meldungen, Favoriten …) erscheinen als Chips (Core\Chat\LinkChips, Titel beim Senden gemerkt).
 * Bilder: dieselbe Prüfung wie im Support (getimagesize, Neu-Kodieren per GD entfernt EXIF/GPS), Auslieferung nur über
 * /admin/chat/datei/{id} nach Rechteprüfung.
 */
final class Messages
{
    public const MAX_FILES = 4;
    public const PAGE = 40;

    public static function find(int $id): ?array
    {
        return Chat::db()->fetch('SELECT * FROM messages WHERE id = ?', [$id]);
    }

    /** Nachrichten eines Raums, neueste zuerst geladen, aufsteigend geliefert. $before: ältere als diese ID */
    public static function list(array $room, int $before = 0, int $limit = self::PAGE): array
    {
        $limit = max(1, min(100, $limit));
        $rows = Chat::db()->fetchAll('SELECT * FROM messages WHERE room_id = ?' . ($before > 0 ? ' AND id < ' . $before : '') . ' ORDER BY id DESC LIMIT ' . ($limit + 1), [(int) $room['id']]);
        $more = count($rows) > $limit;
        $rows = array_reverse(array_slice($rows, 0, $limit));
        return [self::hydrate($rows), $more];
    }

    /** Reaktionen und Bilder für mehrere Nachrichten auf einmal laden */
    private static function hydrate(array $rows): array
    {
        if (!$rows) return [];
        $ids = implode(',', array_map(fn($m) => (int) $m['id'], $rows));
        $re = $fi = [];
        foreach (Chat::db()->fetchAll("SELECT * FROM reactions WHERE message_id IN ($ids) ORDER BY created_at") as $r) $re[(int) $r['message_id']][] = $r;
        foreach (Chat::db()->fetchAll("SELECT * FROM files WHERE message_id IN ($ids) ORDER BY id") as $f) $fi[(int) $f['message_id']][] = $f;
        return array_map(fn($m) => self::toJson($m, $re[(int) $m['id']] ?? [], $fi[(int) $m['id']] ?? []), $rows);
    }

    /** Nachricht für die Oberfläche (aus Sicht der angemeldeten Person) */
    public static function toJson(array $m, ?array $reactions = null, ?array $files = null): array
    {
        $me = Chat::me();
        $id = (int) $m['id'];
        $deleted = $m['deleted_at'] !== null;
        $reactions ??= Chat::db()->fetchAll('SELECT * FROM reactions WHERE message_id = ? ORDER BY created_at', [$id]);
        $files ??= Chat::db()->fetchAll('SELECT * FROM files WHERE message_id = ? ORDER BY id', [$id]);
        $groups = [];
        foreach ($reactions as $r) {
            $g = &$groups[$r['emoji']];
            $g ??= ['emoji' => $r['emoji'], 'count' => 0, 'mine' => false, 'names' => []];
            $g['count']++;
            $g['mine'] = $g['mine'] || $r['user_key'] === $me;
            $g['names'][] = (string) ($r['user_name'] ?: __('Unbekannt'));
            unset($g);
        }
        $author = Chat::personLabel((string) $m['author_key'], (string) $m['author_name']);
        return [
            'id' => $id,
            'room' => (int) $m['room_id'],
            'author' => ['key' => (string) $m['author_key'], 'name' => $author['name'], 'site' => $author['site']],
            'mine' => $m['author_key'] === $me,
            'html' => $deleted ? '' : self::render($m),
            'body' => !$deleted && $m['author_key'] === $me ? (string) $m['body'] : null,
            'at' => date('c', strtotime((string) $m['created_at']) ?: time()),
            'edited' => $m['edited_at'] !== null,
            'deleted' => $deleted,
            'mentionsMe' => !$deleted && str_contains((string) $m['mentions'], ',' . $me . ','),
            'files' => $deleted ? [] : array_map(fn($f) => ['id' => (int) $f['id'], 'url' => url('/admin/chat/datei/' . (int) $f['id']), 'name' => (string) $f['name'],
                'w' => (int) $f['width'], 'h' => (int) $f['height']], $files),
            'reactions' => $deleted ? [] : array_values($groups),
        ];
    }

    /** Markdown-lite + Chips für Verwaltungs-Adressen + hervorgehobene Erwähnungen */
    public static function render(array $m): string
    {
        $body = (string) $m['body'];
        $links = json_decode((string) ($m['links_json'] ?? ''), true) ?: [];
        $chips = [];
        foreach ($links as $i => $l) {
            if (!is_array($l) || !is_string($l['src'] ?? null) || $l['src'] === '') continue;
            $token = "\x02" . $i . "\x02";
            if (str_contains($body, $l['src'])) {
                $body = str_replace($l['src'], $token, $body);
                $chips[$token] = LinkChips::html($l);
            }
        }
        $html = Markdown::render($body);
        if ($chips) $html = strtr($html, $chips);
        foreach (array_filter(explode(',', (string) $m['mentions'])) as $k) {
            $p = Chat::person($k);
            if (!$p || trim((string) $p['name']) === '') continue;
            $at = '@' . e((string) $p['name']);
            $cls = $k === Chat::me() ? 'uc-at uc-at--me' : 'uc-at';
            $html = str_replace($at, '<span class="' . $cls . '">' . $at . '</span>', $html);
        }
        return $html;
    }

    // ------------------------------------------------------------------ Schreiben

    /**
     * Nachricht senden. $fileIds: vorher hochgeladene Bilder (upload()). → [Nachricht, null] oder [null, Fehler]
     */
    public static function send(array $room, string $body, array $fileIds = []): array
    {
        $body = self::clean($body);
        $db = Chat::db();
        $me = Chat::me();
        $fileIds = array_slice(array_values(array_unique(array_map('intval', $fileIds))), 0, self::MAX_FILES);
        $files = [];
        if ($fileIds) {
            $in = implode(',', $fileIds);
            $files = $db->fetchAll("SELECT id FROM files WHERE id IN ($in) AND message_id IS NULL AND uploaded_by = ? AND room_id = ?", [$me, (int) $room['id']]);
        }
        if ($body === '' && !$files) return [null, __('Bitte eine Nachricht eingeben.')];
        if (mb_strlen($body) > Chat::MAX_BODY) return [null, __('Die Nachricht ist zu lang (höchstens {n} Zeichen).', ['n' => Chat::MAX_BODY])];
        $mentions = self::mentions($room, $body);
        $id = $db->transaction(function () use ($db, $room, $body, $me, $files, $mentions) {
            $id = $db->insert('messages', ['room_id' => (int) $room['id'], 'author_key' => $me, 'author_name' => Chat::myName(), 'body' => $body,
                'links_json' => self::linksJson($body), 'mentions' => $mentions ? ',' . implode(',', $mentions) . ',' : '', 'files' => count($files), 'created_at' => now()]);
            foreach ($files as $f) $db->update('files', ['message_id' => $id], 'id = :id', ['id' => (int) $f['id']]);
            foreach ($mentions as $k) $db->insert('mentions', ['message_id' => $id, 'user_key' => $k, 'room_id' => (int) $room['id'], 'mailed' => 0, 'created_at' => now()]);
            $db->update('rooms', ['last_msg_id' => $id, 'last_msg_at' => now()], 'id = :id', ['id' => (int) $room['id']]);
            Chat::event((int) $room['id'], 'message', $id);
            return $id;
        });
        Chat::markRead((int) $room['id'], $id);
        return [self::find($id), null];
    }

    public static function edit(array $m, string $body): ?string
    {
        if ($m['author_key'] !== Chat::me()) return __('Nur eigene Nachrichten lassen sich bearbeiten.');
        if ($m['deleted_at'] !== null) return __('Die Nachricht wurde gelöscht.');
        $body = self::clean($body);
        if ($body === '' && !(int) $m['files']) return __('Bitte eine Nachricht eingeben.');
        if (mb_strlen($body) > Chat::MAX_BODY) return __('Die Nachricht ist zu lang (höchstens {n} Zeichen).', ['n' => Chat::MAX_BODY]);
        $room = Chat::room((int) $m['room_id']);
        $mentions = $room ? self::mentions($room, $body) : [];
        $db = Chat::db();
        $db->update('messages', ['body' => $body, 'links_json' => self::linksJson($body), 'mentions' => $mentions ? ',' . implode(',', $mentions) . ',' : '',
            'edited_at' => now()], 'id = :id', ['id' => (int) $m['id']]);
        foreach ($mentions as $k) {
            $db->query('INSERT OR IGNORE INTO mentions (message_id, user_key, room_id, mailed, created_at) VALUES (?, ?, ?, 0, ?)', [(int) $m['id'], $k, (int) $m['room_id'], now()]);
        }
        Chat::event((int) $m['room_id'], 'edit', (int) $m['id']);
        return null;
    }

    /** Weich löschen: Text, Chips und Bilder entfernen, die Zeile bleibt („Nachricht gelöscht“). Eigene – oder als Verwaltende in Kanälen. */
    public static function delete(array $m): ?string
    {
        $room = Chat::room((int) $m['room_id']);
        $mod = $room && $room['kind'] === 'channel' && Chat::canManage();
        if ($m['author_key'] !== Chat::me() && !$mod) return __('Nur eigene Nachrichten lassen sich löschen.');
        $db = Chat::db();
        foreach ($db->fetchAll('SELECT stored FROM files WHERE message_id = ?', [(int) $m['id']]) as $f) @unlink(Chat::dir('files/' . $f['stored']));
        $db->query('DELETE FROM files WHERE message_id = ?', [(int) $m['id']]);
        $db->query('DELETE FROM reactions WHERE message_id = ?', [(int) $m['id']]);
        $db->query('DELETE FROM mentions WHERE message_id = ?', [(int) $m['id']]);
        $db->update('messages', ['body' => '', 'links_json' => null, 'mentions' => '', 'files' => 0, 'deleted_at' => now()], 'id = :id', ['id' => (int) $m['id']]);
        Chat::event((int) $m['room_id'], 'delete', (int) $m['id']);
        return null;
    }

    /** Reaktion umschalten (kleine feste Auswahl) */
    public static function react(array $m, string $emoji): ?string
    {
        if (!in_array($emoji, Chat::REACTIONS, true)) return __('Diese Reaktion gibt es nicht.');
        if ($m['deleted_at'] !== null) return __('Die Nachricht wurde gelöscht.');
        $db = Chat::db();
        $me = Chat::me();
        if ($db->fetchValue('SELECT 1 FROM reactions WHERE message_id = ? AND user_key = ? AND emoji = ?', [(int) $m['id'], $me, $emoji])) {
            $db->query('DELETE FROM reactions WHERE message_id = ? AND user_key = ? AND emoji = ?', [(int) $m['id'], $me, $emoji]);
        } else {
            $db->insert('reactions', ['message_id' => (int) $m['id'], 'user_key' => $me, 'emoji' => $emoji, 'user_name' => Chat::myName(), 'created_at' => now()]);
        }
        Chat::event((int) $m['room_id'], 'react', (int) $m['id']);
        return null;
    }

    private static function clean(string $body): string
    {
        $body = str_replace(["\r\n", "\r", "\x01", "\x02"], ["\n", "\n", '', ''], $body);
        $body = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~u', '', $body) ?? '';
        return trim(preg_replace("~\n{4,}~", "\n\n\n", $body) ?? '');
    }

    /** Erwähnte Personen: „@Vollständiger Name“ von Personen, die zum Raum gehören (nicht man selbst) */
    public static function mentions(array $room, string $body): array
    {
        if (!str_contains($body, '@')) return [];
        $me = Chat::me();
        $people = Chat::audience($room);
        usort($people, fn($a, $b) => mb_strlen($b['name']) <=> mb_strlen($a['name']));
        $out = [];
        $lower = mb_strtolower($body);
        foreach ($people as $p) {
            if ($p['key'] === $me || trim($p['name']) === '') continue;
            $needle = '@' . mb_strtolower($p['name']);
            $pos = mb_strpos($lower, $needle);
            if ($pos === false) continue;
            $next = mb_substr($lower, $pos + mb_strlen($needle), 1);
            if ($next !== '' && preg_match('~[\p{L}\p{N}]~u', $next)) continue;
            $out[] = $p['key'];
            Chat::ensurePerson($p['key'], $p['name'], $p['email'], $room['scope'] === 'network');
        }
        return array_values(array_unique($out));
    }

    private static function linksJson(string $body): ?string
    {
        $links = LinkChips::extract($body);
        return $links ? json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    // ------------------------------------------------------------------ Bilder

    /** Bild vor dem Senden hochladen (Einfügen/Auswahl im Eingabefeld). → [Datei, null] oder [null, Fehler] */
    public static function upload(array $room, array $f): array
    {
        $name = mb_substr(basename(str_replace('\\', '/', (string) ($f['name'] ?? 'bild'))), 0, 120);
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || (!is_uploaded_file((string) $f['tmp_name']) && PHP_SAPI !== 'cli')) return [null, __('„{name}“ konnte nicht hochgeladen werden.', ['name' => $name])];
        if ((int) $f['size'] > Tickets::MAX_BYTES) return [null, __('„{name}“ ist größer als {mb} MB.', ['name' => $name, 'mb' => Tickets::MAX_BYTES / 1048576])];
        $pending = (int) Chat::db()->fetchValue('SELECT COUNT(*) FROM files WHERE message_id IS NULL AND uploaded_by = ? AND created_at > ?', [Chat::me(), date('Y-m-d H:i:s', time() - 3600)]);
        if ($pending >= 20) return [null, __('Zu viele Bilder in kurzer Zeit. Bitte später erneut versuchen.')];
        $info = @getimagesize((string) $f['tmp_name']);
        if (!$info || !isset(Tickets::IMAGE_TYPES[$info[2]])) return [null, __('„{name}“ ist kein Bild (erlaubt: JPEG, PNG, WebP, GIF).', ['name' => $name])];
        [$mime, $ext] = Tickets::IMAGE_TYPES[$info[2]];
        $rel = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = Chat::dir('files/' . $rel);
        if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0770, true);
        // Neu kodieren (EXIF/GPS weg); GIF wird wie im Support unverändert übernommen
        if (!Tickets::reencode((string) $f['tmp_name'], $dest, $info[2]) && !@copy((string) $f['tmp_name'], $dest)) {
            return [null, __('„{name}“ konnte nicht gespeichert werden.', ['name' => $name])];
        }
        @chmod($dest, 0640);
        $id = Chat::db()->insert('files', ['room_id' => (int) $room['id'], 'message_id' => null, 'name' => $name, 'stored' => $rel, 'mime' => $mime,
            'size' => (int) filesize($dest), 'width' => (int) $info[0], 'height' => (int) $info[1], 'uploaded_by' => Chat::me(), 'created_at' => now()]);
        return [['id' => $id, 'name' => $name, 'url' => url('/admin/chat/datei/' . $id), 'w' => (int) $info[0], 'h' => (int) $info[1]], null];
    }

    /** Bild, falls die angemeldete Person den Raum sehen darf (noch nicht gesendete: nur die hochladende Person) */
    public static function file(int $id): ?array
    {
        $f = Chat::db()->fetch('SELECT * FROM files WHERE id = ?', [$id]);
        if (!$f || str_contains((string) $f['stored'], '..')) return null;
        if ($f['message_id'] === null) return $f['uploaded_by'] === Chat::me() ? $f : null;
        return Chat::room((int) $f['room_id']) ? $f : null;
    }

    // ------------------------------------------------------------------ E-Mail-Hinweise zu Erwähnungen

    /**
     * Ungelesene Erwähnungen (älter als 10 Minuten) als Sammel-E-Mail – höchstens einmal je Stunde und Person,
     * nur mit eingeschaltetem Hinweis (people.notify_mail). Versand über Core\Mailer der aktuellen Website
     * (außerhalb der Produktion: config 'mail_redirect' bzw. nur Protokoll). → Anzahl versendeter E-Mails
     */
    public static function digest(): int
    {
        $db = Chat::db();
        $rows = $db->fetchAll("SELECT n.user_key, n.message_id, n.room_id, m.author_name, m.body, m.created_at, m.deleted_at,
                p.email, p.name AS pname, p.notify_mail, p.digest_at, p.site AS psite, COALESCE(r.last_read_id, 0) AS last_read
            FROM mentions n JOIN messages m ON m.id = n.message_id JOIN people p ON p.user_key = n.user_key
            LEFT JOIN reads r ON r.user_key = n.user_key AND r.room_id = n.room_id
            WHERE n.mailed = 0 AND n.created_at < ? ORDER BY n.user_key, n.message_id LIMIT 500", [date('Y-m-d H:i:s', time() - 600)]);
        $by = [];
        foreach ($rows as $r) $by[$r['user_key']][] = $r;
        $sent = 0;
        foreach ($by as $key => $list) {
            $p = $list[0];
            $unread = array_filter($list, fn($r) => (int) $r['message_id'] > (int) $r['last_read'] && $r['deleted_at'] === null);
            $recent = $p['digest_at'] !== null && strtotime((string) $p['digest_at']) > time() - 3600;
            if ($unread && $recent) continue;   // später bündeln
            $ids = implode(',', array_map(fn($r) => (int) $r['message_id'], $list));
            $db->query("UPDATE mentions SET mailed = 1 WHERE user_key = ? AND message_id IN ($ids)", [$key]);
            if (!$unread || !(int) $p['notify_mail'] || !filter_var($p['email'], FILTER_VALIDATE_EMAIL)) continue;
            $rooms = [];
            foreach ($db->fetchAll('SELECT * FROM rooms WHERE id IN (' . implode(',', array_unique(array_map(fn($r) => (int) $r['room_id'], $unread))) . ')') as $room) $rooms[(int) $room['id']] = $room;
            $lines = [];
            foreach ($unread as $r) {
                $room = $rooms[(int) $r['room_id']] ?? null;
                $where = $room && $room['kind'] === 'channel' ? '#' . $room['slug'] : __('Direktnachricht');
                $lines[] = '• ' . $r['author_name'] . ' (' . $where . ', ' . date('d.m. H:i', strtotime((string) $r['created_at'])) . '): ' . Markdown::plain((string) $r['body'], 160);
            }
            $link = Support::urlOn((string) $p['psite'], '/admin/chat');
            $text = __('Hallo {name},', ['name' => (string) ($p['pname'] ?: $p['email'])]) . "\n\n"
                . __('Sie wurden im Chat erwähnt und haben die Nachrichten noch nicht gelesen:') . "\n\n" . implode("\n", $lines) . "\n\n"
                . __('Zum Chat: {url}', ['url' => $link]) . "\n\n"
                . __('Diese Hinweise lassen sich im Chat unter „Einstellungen“ abschalten.');
            try {
                $err = \Core\Mailer::send(__('Neue Erwähnungen im Chat ({n})', ['n' => count($unread)]), $text . "\n\n—\n" . __('Automatische Nachricht von {name}.', ['name' => CMS_NAME]), [(string) $p['email']]);
                if ($err !== null) error_log('[chat] digest: ' . $err);
            } catch (\Throwable $e) {
                error_log('[chat] digest: ' . $e->getMessage());
            }
            $db->update('people', ['digest_at' => now()], 'user_key = :k', ['k' => $key]);
            $sent++;
        }
        return $sent;
    }
}
