<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

use Core\Database;
use Core\Features;
use Core\Links;
use Core\Media;
use Core\Permissions;

/**
 * Mitteilungen von Hand (Verwaltung → Mitteilungen → Verfassen): Titel, Text, Ziel (Linkauswahl: page:ID, entry:…, Pfad oder
 * Adresse dieser Website), optional Bild (Mediathek → Mitteilung „image“, wo der Browser es zeigt), Empfänger (Kanäle für
 * Besucher und/oder Personen der Verwaltung nach Rolle bzw. einzeln – mit ihrem Schalter „Mitteilungen der Redaktion“),
 * Zeitpunkt sofort oder geplant (push:send verschickt fällige; ohne Cron nebenbei bei Verwaltungsaufrufen).
 * Empfänger werden erst beim Versand ermittelt – wer bis dahin abbestellt, bekommt nichts. Ein Gerät erhält eine Nachricht nur
 * einmal, auch wenn es mehrere gewählte Kanäle abonniert hat. Testumgebung: keine Besucher (Push::visitorsAllowed).
 */
final class Compose
{
    public const EVENT = 'push.manual';
    /** Geplant höchstens so weit voraus */
    public const MAX_DAYS = 90;

    /** Eingaben prüfen → [Daten, Fehler] (Daten: title, body, link, url, image_id, image, targets{topics, roles, users}, at|null) */
    public static function validate(array $in): array
    {
        $errors = [];
        $title = trim(strip_tags(mb_substr((string) ($in['title'] ?? ''), 0, 120)));
        $body = trim(strip_tags(mb_substr((string) ($in['body'] ?? ''), 0, 240)));
        if ($title === '') $errors['title'] = __('Bitte einen Titel angeben.');
        $link = trim((string) ($in['link'] ?? ''));
        $url = Push::origin() . url('/');
        if ($link !== '') {
            $url = self::resolve($link);
            if ($url === null) $errors['link'] = __('Bitte eine Seite, einen Eintrag oder eine Adresse dieser Website wählen.');
        }
        $imageId = (int) ($in['image'] ?? 0);
        $image = null;
        if ($imageId > 0) {
            $m = Media::find($imageId);
            if (!$m || !str_starts_with((string) $m['mime'], 'image/')) $errors['image'] = __('Bitte ein Bild aus der Mediathek wählen.');
            // 800 px WebP (zeigen alle Browser mit Bild-Mitteilungen), sonst das Original
            else $image = Push::origin() . (is_file(Media::localFile($m, 800, 'webp')) ? Media::url($m, 800, 'webp') : Media::url($m, null));
        }
        $all = Channels::all();
        $topics = array_values(array_unique(array_filter(array_map('strval', (array) ($in['topics'] ?? [])),
            fn($t) => isset($all[$t]) && !$all[$t]['archived'])));
        $roles = array_values(array_intersect(array_map('strval', (array) ($in['roles'] ?? [])), array_keys(Permissions::roles())));
        $users = array_values(array_unique(array_filter(array_map('intval', (array) ($in['users'] ?? [])))));
        if ($users) $users = array_map('intval', array_column(app()->db->fetchAll('SELECT id FROM users WHERE id IN (' . implode(',', $users) . ')'), 'id'));
        if (!$topics && !$roles && !$users) $errors['targets'] = __('Bitte mindestens einen Kanal oder eine Person bzw. Rolle wählen.');
        $at = null;
        if (($in['when'] ?? 'now') === 'later') {
            $ts = strtotime(str_replace('T', ' ', (string) ($in['at'] ?? '')));
            if ($ts === false) $errors['at'] = __('Bitte Datum und Uhrzeit angeben.');
            elseif ($ts < time() + 60) $errors['at'] = __('Der Zeitpunkt muss in der Zukunft liegen.');
            elseif ($ts > time() + self::MAX_DAYS * 86400) $errors['at'] = __('Höchstens {n} Tage im Voraus.', ['n' => self::MAX_DAYS]);
            else $at = $ts;
        }
        return [['title' => $title, 'body' => $body, 'link' => $link, 'url' => $url, 'image_id' => $imageId ?: null, 'image' => $image,
            'targets' => ['topics' => $topics, 'roles' => $roles, 'users' => $users], 'at' => $at], $errors];
    }

    /** Ziel → absolute Adresse dieser Website (null = fremd/ungültig) */
    public static function resolve(string $link): ?string
    {
        $abs = fn(string $h) => preg_match('~^https?://~i', $h) ? $h : Push::origin() . $h;   // url() schon angewendet – nicht doppelt
        if (Links::isRef($link)) {
            $h = Links::href($link);
            return $h !== null && $h !== '#' && $h !== '' ? $abs($h) : null;
        }
        if (str_starts_with($link, '/') && !str_starts_with($link, '//')) return $abs(url($link));
        if (preg_match('~^https?://~i', $link)) {
            $host = strtolower((string) parse_url($link, PHP_URL_HOST));
            $own = array_filter([parse_url(Push::origin(), PHP_URL_HOST), ...array_map(fn($h) => explode(':', $h)[0], site()->hosts())]);
            return in_array($host, array_map('strtolower', $own), true) ? $link : null;
        }
        return null;
    }

    /**
     * Erreichbare Geräte für Empfänger (vor dem Senden angezeigt): ['visitors' => Geräte über Kanäle, 'staff' => Geräte der Redaktion,
     * 'people' => Personen, 'total' => verschiedene Geräte, 'blocked' => true wenn Besucher in dieser Umgebung nicht beliefert werden]
     */
    public static function reach(array $targets): array
    {
        $ids = self::deviceIds($targets);
        return ['visitors' => count($ids['topic']), 'staff' => count($ids['staff']), 'people' => $ids['people'],
            'total' => count(array_unique([...$ids['topic'], ...$ids['staff']])), 'blocked' => !Push::visitorsAllowed() && $targets['topics']];
    }

    /** Geräte-IDs: ['topic' => […], 'staff' => […], 'people' => n] */
    private static function deviceIds(array $targets, ?Database $db = null): array
    {
        $db ??= app()->db;
        $fp = Keys::fingerprint();
        $topic = [];
        if ($targets['topics'] && Push::visitorsAllowed()) {
            $where = implode(' OR ', array_fill(0, count($targets['topics']), 'topics LIKE ?'));
            $topic = array_map('intval', array_column($db->fetchAll("SELECT id FROM push_subscriptions WHERE key_fp = ? AND ($where)",
                [$fp, ...array_map(fn($t) => '%,' . $t . ',%', $targets['topics'])]), 'id'));
        }
        $people = self::people($targets);
        $staff = $people ? array_map('intval', array_column($db->fetchAll('SELECT id FROM push_subscriptions WHERE key_fp = ? AND user_id IN ('
            . implode(',', $people) . ')', [$fp]), 'id')) : [];
        return ['topic' => $topic, 'staff' => $staff, 'people' => count($people)];
    }

    /** Personen (IDs) nach Rollen und Auswahl, die den Schalter „Mitteilungen der Redaktion“ an haben und nicht gesperrt sind */
    public static function people(array $targets): array
    {
        if (!$targets['roles'] && !$targets['users']) return [];
        $out = [];
        foreach (app()->db->fetchAll('SELECT id, role, disabled FROM users') as $u) {
            if (!empty($u['disabled'])) continue;
            if (!in_array((string) $u['role'], $targets['roles'], true) && !in_array((int) $u['id'], $targets['users'], true)) continue;
            if (!(Push::userEvents((int) $u['id'])[self::EVENT] ?? true)) continue;
            $out[] = (int) $u['id'];
        }
        return $out;
    }

    /** Mitteilung anlegen: sofort (vorgemerkt + nebenbei gesendet) oder geplant → ID */
    public static function create(array $d, ?int $userId): int
    {
        $payload = Push::payload(['title' => $d['title'], 'body' => $d['body'], 'url' => $d['url'], 'tag' => 'manual-' . bin2hex(random_bytes(3))]);
        if (!empty($d['image'])) $payload['image'] = mb_substr((string) $d['image'], 0, 600);
        $id = app()->db->insert('push_messages', ['kind' => 'manual', 'event' => self::EVENT, 'topic' => null, 'title' => mb_substr($d['title'], 0, 190),
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'ttl' => 2 * 86400, 'urgency' => 'normal',
            'user_id' => $userId, 'recipients' => 0, 'sent' => 0, 'failed' => 0, 'created_at' => now(), 'source' => 'manual',
            'status' => $d['at'] ? 'scheduled' : 'queued', 'scheduled_at' => $d['at'], 'targets_json' => json_encode($d['targets']),
            'link' => mb_substr((string) $d['link'], 0, 600) ?: null, 'image_id' => $d['image_id']]);
        if (!$d['at']) {
            self::fanout($id);
            Push::kick();
        }
        return $id;
    }

    /** Empfänger einer Nachricht ermitteln und vormerken (jedes Gerät einmal) */
    public static function fanout(int $id, ?Database $db = null): int
    {
        $db ??= app()->db;
        $m = $db->fetch('SELECT * FROM push_messages WHERE id = ?', [$id]);
        if (!$m || !in_array($m['status'], ['queued', 'scheduled'], true)) return 0;
        $t = json_decode((string) $m['targets_json'], true) ?: [];
        $t += ['topics' => [], 'roles' => [], 'users' => []];
        $ids = self::deviceIds($t, $db);
        $all = array_values(array_unique([...$ids['topic'], ...$ids['staff']]));
        $now = time();
        foreach ($all as $sid) $db->insert('push_queue', ['message_id' => $id, 'subscription_id' => $sid, 'status' => 'queued', 'attempts' => 0, 'next_at' => $now, 'created_at' => $now]);
        // Zeitstempel der Mitteilung = Versand (nicht Planung)
        $p = json_decode((string) $m['payload_json'], true) ?: [];
        $p['ts'] = $now;
        $db->update('push_messages', ['recipients' => count($all), 'status' => 'sent', 'created_at' => $m['scheduled_at'] ? date('Y-m-d H:i:s', $now) : $m['created_at'],
            'payload_json' => json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)] + ($all ? [] : ['done_at' => now()]), 'id = :id', ['id' => $id]);
        return count($all);
    }

    /** Fällige geplante Nachrichten vormerken → Anzahl */
    public static function due(?Database $db = null): int
    {
        $db ??= app()->db;
        $n = 0;
        foreach ($db->fetchAll("SELECT id FROM push_messages WHERE status = 'scheduled' AND scheduled_at <= ? ORDER BY scheduled_at LIMIT 20", [time()]) as $r) {
            self::fanout((int) $r['id'], $db);
            $n++;
        }
        return $n;
    }

    /** Ohne Cron: bei Verwaltungsaufrufen fällige Planungen nebenbei verschicken (billige Prüfung) */
    public static function maybeDue(): void
    {
        try {
            if (!Push::enabled()) return;
            if (app()->db->fetchValue("SELECT 1 FROM push_messages WHERE status = 'scheduled' AND scheduled_at <= ? LIMIT 1", [time()])) Push::kick();
        } catch (\Throwable) {
        }
    }

    /** Geplante Nachricht abbrechen → true, wenn sie noch geplant war */
    public static function cancel(int $id): bool
    {
        $m = app()->db->fetch('SELECT status FROM push_messages WHERE id = ?', [$id]);
        if (!$m || $m['status'] !== 'scheduled') return false;
        app()->db->update('push_messages', ['status' => 'canceled', 'done_at' => now()], 'id = :id', ['id' => $id]);
        return true;
    }

    /** Formularwerte aus einer bestehenden Nachricht (Duplizieren/Erneut senden als Entwurf) */
    public static function prefill(array $m): array
    {
        $p = json_decode((string) $m['payload_json'], true) ?: [];
        $t = json_decode((string) ($m['targets_json'] ?? ''), true) ?: [];
        if (!$t && !empty($m['topic'])) $t = ['topics' => [(string) $m['topic']]];
        return ['title' => (string) ($p['title'] ?? $m['title'] ?? ''), 'body' => (string) ($p['body'] ?? ''), 'link' => (string) ($m['link'] ?? ($p['url'] ?? '')),
            'image' => (string) ($m['image_id'] ?? ''), 'topics' => (array) ($t['topics'] ?? []), 'roles' => (array) ($t['roles'] ?? []), 'users' => (array) ($t['users'] ?? []), 'when' => 'now'];
    }

    /** Status zur Anzeige: geplant | wartet | sendet | gesendet | abgebrochen */
    public static function state(array $m): string
    {
        $s = (string) ($m['status'] ?? '');
        if ($s === 'scheduled' || $s === 'canceled') return $s;
        return $m['done_at'] === null ? 'sending' : 'sent';
    }

    /** Empfänger in Worten (Verlauf): „Kanäle: News, Notdienst · Rollen: Redaktion · 2 Personen“ */
    public static function summary(array $m): string
    {
        $t = json_decode((string) ($m['targets_json'] ?? ''), true) ?: [];
        if (!$t) {
            if (!empty($m['topic'])) return __('Kanal: {name}', ['name' => Channels::label((string) $m['topic'])]);
            return match ((string) $m['kind']) { 'test' => __('Testnachricht an eigene Geräte'), 'user' => __('Redaktion: {event}', ['event' => (string) (Push::events()[$m['event']]['label'] ?? $m['event'])]), default => '–' };
        }
        $parts = [];
        if (!empty($t['topics'])) $parts[] = __('Kanäle: {list}', ['list' => implode(', ', array_map([Channels::class, 'label'], $t['topics']))]);
        if (!empty($t['roles'])) {
            $roles = Permissions::roles();
            $parts[] = __('Rollen: {list}', ['list' => implode(', ', array_map(fn($r) => (string) ($roles[$r]['name'] ?? $r), $t['roles']))]);
        }
        if (!empty($t['users'])) $parts[] = count($t['users']) === 1 ? __('1 Person') : __('{n} Personen', ['n' => count($t['users'])]);
        return implode(' · ', $parts);
    }

    /** Features-Prüfung für Seiten und Routen */
    public static function available(): bool
    {
        return Features::on(Push::FEATURE, false);
    }
}
