<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

use Core\Database;
use Core\Features;
use Core\Lang;
use Core\Permissions;

/**
 * Push-Benachrichtigungen (Funktion „push“, Standard aus) – echte Web-Push-Mitteilungen auch bei geschlossenem Tab.
 *
 *   Personen der Verwaltung   Konto → Benachrichtigungen: Gerät anmelden, Geräte verwalten, Schalter je Ereignis (requests.new,
 *                             forms.entry, chat.direct, chat.mention, review.pending, Ereignisse von Erweiterungen)
 *   Besucher                  Datentabellen mit „Besucher können neue Einträge abonnieren“ (Core\Push\Topics): Thema „data:{tabelle}“,
 *                             Block „Benachrichtigungen abonnieren“ bzw. push_subscribe() im Kit (Core\Push\Visitor)
 *
 * Tabellen je Website (Database::migrate → ensureTable): push_subscriptions (Endpunkt, Schlüssel, Konto, Themen, Sprache, Fehler),
 * push_user_events (Schalter je Person und Ereignis), push_messages (Nachricht + Zähler) und push_queue (eine Zeile je Gerät).
 * Versand: `php bin/console push:send --all` (Cron, jede Minute bis alle 5 min) und nebenbei nach der Antwort (kick(), kleine
 * Mengen nach fastcgi_finish_request). Verschlüsselung und VAPID: Core\Push\WebPush, Schlüssel: Core\Push\Keys.
 *
 * Datenschutz: gespeichert wird nur das Abo (Endpunkt des Push-Dienstes, Schlüssel), bei Besuchern ohne IP, Cookie oder Konto.
 * Nachrichten an Besucher enthalten nur öffentliche Inhalte (Titel, Kurztext, Adresse). Testumgebung (environment staging):
 * keine Nachrichten an Besucher (config 'push_staging_visitors' => true erlaubt sie); Abos mit fremdem Schlüssel (z. B. aus
 * einer Sicherung der Live-Website) werden nie beliefert.
 */
final class Push
{
    public const FEATURE = 'push';
    /** Service Worker nur für Push (steuert keine Seiten – eigener Bereich) */
    public const SW_PATH = '/push-sw.js';
    public const SW_SCOPE = '/push-sw/';
    /** Öffentliche Schnittstelle für Besucher (subscribe, unsubscribe, status, renew) */
    public const API = '/api/push';
    /**
     * Push-Dienste der Browser: Google FCM (Chrome, Edge, Opera, Android; Chromium-Builds ohne Google-Schlüssel: jmt17.google.com),
     * Mozilla autopush, Apple, Microsoft WNS; weitere: config 'push_hosts'
     */
    public const HOSTS = ['fcm.googleapis.com', 'android.googleapis.com', 'jmt17.google.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com'];
    public const MAX_ATTEMPTS = 5;
    /** Fehlversuche in Folge, nach denen ein Abo gelöscht wird */
    public const MAX_FAILURES = 5;
    /** Höchstens so viele Nachrichten je Thema (Datentabelle) und Stunde – schützt vor Massen-Veröffentlichungen */
    public const TOPIC_PER_HOUR = 10;
    /** Höchstens so viele Nachrichten je Gerät in 10 Minuten (Personen der Verwaltung) */
    public const DEVICE_PER_10MIN = 30;
    /** Neue Besucher-Abos je Anschluss und Stunde */
    public const VISITOR_PER_HOUR = 20;

    /** Ereignisse von Erweiterungen (Extension::pushEvent, registerEvent) */
    private static array $extra = [];
    private static bool $kicked = false;
    private static ?bool $enabled = null;
    /** Versand ersetzen (Selbsttest): fn(array $jobs): array – Rückgabe wie WebPush::sendAll */
    public static mixed $transport = null;
    /** Funktion für den Selbsttest erzwingen (null = Funktionsumfang der Website) */
    public static ?bool $force = null;

    // ================================================================= Zustand

    /** Funktion eingeschaltet (unabhängig von den Schlüsseln) */
    public static function on(): bool
    {
        if (self::$force !== null) return self::$force;
        return Features::on(self::FEATURE, false);
    }

    /** Einsatzbereit: Funktion an und VAPID-Schlüssel vorhanden (fehlen sie, werden sie beim ersten Gebrauch angelegt) */
    public static function enabled(): bool
    {
        if (self::$force !== null) return self::$force && Keys::ready();
        if (self::$enabled !== null) return self::$enabled;
        if (!self::on()) return self::$enabled = false;
        try {
            return self::$enabled = Keys::ready() || Keys::ensure();
        } catch (\Throwable $e) {
            error_log('[push] Schlüssel: ' . $e->getMessage());
            return self::$enabled = false;
        }
    }

    /** Zwischenspeicher verwerfen (nach dem Umschalten der Funktion, Tests) */
    public static function flush(): void
    {
        self::$enabled = null;
        Keys::reset();
    }

    /** Darf in dieser Umgebung an Besucher gesendet werden? (staging: nur mit config 'push_staging_visitors' => true) */
    public static function visitorsAllowed(): bool
    {
        return environment() !== 'staging' || (bool) app()->config->get('push_staging_visitors', false);
    }

    // ================================================================= Schema

    /** Tabellen anlegen (idempotent, nur ergänzend) – aus Database::migrate */
    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->query("CREATE TABLE IF NOT EXISTS push_subscriptions (id $pk, endpoint TEXT NOT NULL, endpoint_hash VARCHAR(64) NOT NULL UNIQUE,
            p256dh VARCHAR(120) NOT NULL, auth VARCHAR(40) NOT NULL, key_fp VARCHAR(16) NOT NULL, user_id INT NULL, topics TEXT NULL,
            lang VARCHAR(10) NULL, label VARCHAR(80) NULL, created_at VARCHAR(25), last_seen VARCHAR(25) NULL, last_sent_at VARCHAR(25) NULL,
            failures INT NOT NULL DEFAULT 0, last_error VARCHAR(191) NULL)$tail");
        $db->query("CREATE TABLE IF NOT EXISTS push_user_events (user_id INT NOT NULL, event VARCHAR(60) NOT NULL, enabled INT NOT NULL DEFAULT 1,
            PRIMARY KEY (user_id, event))$tail");
        $db->query("CREATE TABLE IF NOT EXISTS push_messages (id $pk, kind VARCHAR(10) NOT NULL, event VARCHAR(60) NOT NULL, topic VARCHAR(80) NULL,
            title VARCHAR(191) NULL, payload_json TEXT NOT NULL, ttl INT NOT NULL DEFAULT 86400, urgency VARCHAR(10) NOT NULL DEFAULT 'normal',
            collapse VARCHAR(32) NULL, user_id INT NULL, recipients INT NOT NULL DEFAULT 0, sent INT NOT NULL DEFAULT 0, failed INT NOT NULL DEFAULT 0,
            created_at VARCHAR(25) NOT NULL, done_at VARCHAR(25) NULL)$tail");
        $db->query("CREATE TABLE IF NOT EXISTS push_queue (id $pk, message_id INT NOT NULL, subscription_id INT NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'queued', attempts INT NOT NULL DEFAULT 0, next_at INT NOT NULL DEFAULT 0, created_at INT NOT NULL,
            sent_at INT NULL, http_code INT NULL, error VARCHAR(191) NULL)$tail");
        if (!$my) {
            $db->pdo->exec('CREATE INDEX IF NOT EXISTS push_sub_user ON push_subscriptions (user_id)');
            $db->pdo->exec('CREATE INDEX IF NOT EXISTS push_queue_due ON push_queue (status, next_at)');
            $db->pdo->exec('CREATE INDEX IF NOT EXISTS push_queue_sub ON push_queue (subscription_id, created_at)');
            $db->pdo->exec('CREATE INDEX IF NOT EXISTS push_msg_topic ON push_messages (topic, created_at)');
        }
        // Stufe 2: Mitteilungen von Hand (geplant, Empfänger, Bild), getrennte Zähler, Kanäle, Statistik
        $db->ensureColumns('push_messages', ['status' => 'VARCHAR(12) NULL', 'source' => 'VARCHAR(10) NULL', 'scheduled_at' => 'INT NULL',
            'targets_json' => 'TEXT NULL', 'gone' => 'INT NOT NULL DEFAULT 0', 'expired' => 'INT NOT NULL DEFAULT 0', 'link' => 'VARCHAR(600) NULL', 'image_id' => 'INT NULL']);
        if (!$my) $db->pdo->exec('CREATE INDEX IF NOT EXISTS push_msg_status ON push_messages (status, scheduled_at)');
        Channels::ensureTable($db);
        Stats::ensureTable($db);
    }

    // ================================================================= Abos

    /** Endpunkt erlaubt? Nur https auf Port 443 und nur bekannte Push-Dienste (kein SSRF über erfundene Endpunkte) */
    public static function endpointAllowed(string $url): bool
    {
        if (strlen($url) > 1024) return false;
        $u = parse_url($url);
        if (!is_array($u) || ($u['scheme'] ?? '') !== 'https' || empty($u['host']) || isset($u['user']) || isset($u['pass'])) return false;
        if (isset($u['port']) && (int) $u['port'] !== 443) return false;
        $host = strtolower((string) $u['host']);
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) || !str_contains($host, '.')) return false;
        foreach (self::hosts() as $h) {
            if ($host === $h || str_ends_with($host, '.' . $h)) return true;
        }
        return false;
    }

    /** Erlaubte Hosts der Push-Dienste (Kern + config 'push_hosts') */
    public static function hosts(): array
    {
        $extra = array_filter(array_map(fn($h) => strtolower(trim((string) $h, ". \t")), (array) app()->config->get('push_hosts', [])),
            fn($h) => preg_match('~^[a-z0-9.-]+\.[a-z]{2,}$~', $h) === 1);
        return array_values(array_unique([...self::HOSTS, ...$extra]));
    }

    /**
     * Abo aus dem Browser (PushSubscription.toJSON(): endpoint, keys.p256dh, keys.auth) prüfen
     * → ['endpoint' => …, 'p256dh' => roh, 'auth' => roh] oder null
     */
    public static function parse(mixed $sub): ?array
    {
        if (!is_array($sub)) return null;
        $ep = trim((string) ($sub['endpoint'] ?? ''));
        $keys = is_array($sub['keys'] ?? null) ? $sub['keys'] : [];
        $p = WebPush::unb64u((string) ($keys['p256dh'] ?? ''));
        $a = WebPush::unb64u((string) ($keys['auth'] ?? ''));
        if (!self::endpointAllowed($ep) || $p === false || $a === false || strlen($a) !== 16 || !WebPush::validPublic($p)) return null;
        return ['endpoint' => $ep, 'p256dh' => $p, 'auth' => $a];
    }

    /** Abo-Zeile zu einem Endpunkt */
    public static function find(string $endpoint, ?Database $db = null): ?array
    {
        return ($db ?? app()->db)->fetch('SELECT * FROM push_subscriptions WHERE endpoint_hash = ?', [hash('sha256', $endpoint)]);
    }

    /** Gehört das Auth-Geheimnis zum gespeicherten Abo? (Besucher weisen sich so aus – nur ihr Browser kennt es) */
    public static function owns(array $row, string $authRaw): bool
    {
        return hash_equals((string) $row['auth'], WebPush::b64u($authRaw));
    }

    /**
     * Abo speichern bzw. auffrischen → [id, null] oder [null, Fehlercode]. $userId: Gerät einer Person (null lässt eine bestehende
     * Zuordnung unverändert); $topics werden ergänzt. Fehlercodes: 'conflict' (Endpunkt mit anderem Auth-Geheimnis gespeichert).
     */
    public static function store(array $s, ?int $userId, array $topics = [], ?string $lang = null, string $ua = '', ?Database $db = null): array
    {
        $db ??= app()->db;
        $row = self::find($s['endpoint'], $db);
        $data = ['p256dh' => WebPush::b64u($s['p256dh']), 'auth' => WebPush::b64u($s['auth']), 'key_fp' => Keys::fingerprint(),
            'last_seen' => now(), 'failures' => 0, 'last_error' => null];
        if ($ua !== '') $data['label'] = self::deviceLabel($ua);
        if ($lang !== null) $data['lang'] = Lang::norm($lang);
        if ($row) {
            // Derselbe Endpunkt mit anderem Geheimnis: nur übernehmen, wenn das bisherige Abo ohnehin veraltet ist (alter Schlüssel)
            if (!hash_equals((string) $row['auth'], $data['auth']) && $row['key_fp'] === Keys::fingerprint()) return [null, 'conflict'];
            if ($userId !== null) $data['user_id'] = $userId;
            if ($topics) $data['topics'] = self::topicsString([...self::topicsOf($row), ...$topics]);
            $db->update('push_subscriptions', $data, 'id = :id', ['id' => (int) $row['id']]);
            // Statistik (nur Zähler): neu abonnierte Kanäle, neu angemeldetes Gerät der Redaktion; altes Abo mit anderem Schlüssel = neu
            $stale = $row['key_fp'] !== Keys::fingerprint();
            foreach (array_diff(self::topicsOf(['topics' => $data['topics'] ?? $row['topics']]), $stale ? [] : self::topicsOf($row)) as $t) Stats::hit($t, 'sub', 1, $db);
            if ($userId !== null && ($stale || (int) $row['user_id'] !== $userId)) Stats::hit(Stats::STAFF, 'sub', 1, $db);
            return [(int) $row['id'], null];
        }
        $id = $db->insert('push_subscriptions', $data + ['endpoint' => $s['endpoint'], 'endpoint_hash' => hash('sha256', $s['endpoint']),
            'user_id' => $userId, 'topics' => self::topicsString($topics), 'created_at' => now(), 'label' => $data['label'] ?? self::deviceLabel('')]);
        foreach (self::topicsOf(['topics' => self::topicsString($topics)]) as $t) Stats::hit($t, 'sub', 1, $db);
        if ($userId !== null) Stats::hit(Stats::STAFF, 'sub', 1, $db);
        return [$id, null];
    }

    /** Gerät einer Person abmelden → 'deleted' (keine Themen mehr), 'unlinked' (bleibt für abonnierte Themen) oder null */
    public static function unlinkUser(int $userId, ?int $id = null, ?string $endpoint = null): ?string
    {
        $db = app()->db;
        $row = $id !== null ? $db->fetch('SELECT * FROM push_subscriptions WHERE id = ? AND user_id = ?', [$id, $userId])
            : ($endpoint !== null ? $db->fetch('SELECT * FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?', [hash('sha256', $endpoint), $userId]) : null);
        if (!$row) return null;
        Stats::hit(Stats::STAFF, 'unsub', 1, $db);
        if (!self::topicsOf($row)) {
            self::delete((int) $row['id'], $db, null);
            return 'deleted';
        }
        $db->update('push_subscriptions', ['user_id' => null], 'id = :id', ['id' => (int) $row['id']]);
        return 'unlinked';
    }

    /** Themen eines Abos entfernen (null = alle) → 'deleted', 'updated' oder null (unbekannt) */
    public static function removeTopics(array $row, ?array $topics): ?string
    {
        $left = $topics === null ? [] : array_values(array_diff(self::topicsOf($row), $topics));
        foreach (array_diff(self::topicsOf($row), $left) as $t) Stats::hit($t, 'unsub');
        if (!$left && $row['user_id'] === null) {
            self::delete((int) $row['id'], null, null);
            return 'deleted';
        }
        app()->db->update('push_subscriptions', ['topics' => self::topicsString($left), 'last_seen' => now()], 'id = :id', ['id' => (int) $row['id']]);
        return 'updated';
    }

    /** Abo löschen (offene Zustellungen werden verworfen). $stat: Art für die Statistik ('gone' = Push-Dienst/Fehler; null = schon gezählt) */
    public static function delete(int $id, ?Database $db = null, ?string $stat = 'gone'): void
    {
        $db ??= app()->db;
        if ($stat !== null && ($row = $db->fetch('SELECT topics, user_id FROM push_subscriptions WHERE id = ?', [$id]))) Stats::lost($row, $stat, $db);
        $db->query("UPDATE push_queue SET status = 'gone' WHERE subscription_id = ? AND status = 'queued'", [$id]);
        $db->query('DELETE FROM push_subscriptions WHERE id = ?', [$id]);
    }

    /** Geräte einer Person (neueste zuerst) */
    public static function devices(int $userId): array
    {
        return app()->db->fetchAll('SELECT id, label, created_at, last_seen, last_sent_at, failures, key_fp, endpoint_hash FROM push_subscriptions
            WHERE user_id = ? ORDER BY COALESCE(last_seen, created_at) DESC', [$userId]);
    }

    /** @return list<string> Themen eines Abos */
    public static function topicsOf(array $row): array
    {
        return array_values(array_filter(explode(',', (string) ($row['topics'] ?? ''))));
    }

    /** Themen als „,data:news,data:termine,“ (Suche mit LIKE '%,thema,%') */
    public static function topicsString(array $topics): ?string
    {
        $t = array_values(array_unique(array_filter(array_map('strval', $topics), fn($x) => self::validTopic($x))));
        sort($t);
        return $t ? ',' . implode(',', array_slice($t, 0, 50)) . ',' : null;
    }

    public static function validTopic(string $t): bool
    {
        return preg_match('~^[a-z][a-z0-9_-]{0,20}:[a-z0-9_.-]{1,60}$~', $t) === 1;
    }

    /** Kurzbezeichnung des Geräts aus dem User-Agent (ohne Versionsnummern), z. B. „Chrome · macOS“ */
    public static function deviceLabel(string $ua): string
    {
        $b = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox', str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari', default => 'Browser',
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone', str_contains($ua, 'iPad') => 'iPad', str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS', str_contains($ua, 'Linux') => 'Linux', default => '',
        };
        return $os !== '' ? "$b · $os" : $b;
    }

    // ================================================================= Ereignisse für Personen der Verwaltung

    /**
     * Ereignisse: key => [label, help, perm (Recht, das die Person haben muss), feature (Funktion), default (Schalter vorbelegt), owner].
     * Erweiterungen melden eigene an: $x->pushEvent('freigabe.feedback', 'Neues Feedback', 'freigabe.view').
     */
    public static function events(): array
    {
        return [
            'requests.new' => ['label' => __('Neue Online-Anfragen'), 'help' => __('Hinweis ohne Inhalte, sobald über ein Formular eine Anfrage in einem Eingang ankommt.'),
                'perm' => 'requests.read', 'feature' => 'requests', 'default' => true, 'owner' => 'core'],
            'forms.entry' => ['label' => __('Neue Einträge über Formulare der Website'), 'help' => __('Besucher haben über ein Formular einen Eintrag in einer Datentabelle angelegt.'),
                'perm' => 'data.edit', 'feature' => 'forms.data', 'default' => true, 'owner' => 'core'],
            'chat.direct' => ['label' => __('Direktnachrichten im Chat'), 'help' => __('Nicht, solange die Verwaltung in einem sichtbaren Fenster offen ist.'),
                'perm' => 'chat.use', 'feature' => 'chat', 'default' => true, 'owner' => 'core'],
            'chat.mention' => ['label' => __('Erwähnungen im Chat'), 'help' => __('Wenn Sie in einem Kanal mit @Name erwähnt werden.'),
                'perm' => 'chat.use', 'feature' => 'chat', 'default' => true, 'owner' => 'core'],
            'push.manual' => ['label' => __('Mitteilungen der Redaktion'), 'help' => __('Nachrichten, die jemand unter „Mitteilungen“ an Personen der Verwaltung schickt.'),
                'perm' => null, 'feature' => self::FEATURE, 'default' => true, 'owner' => 'core'],
            'review.pending' => ['label' => __('Einreichungen zur Freigabe'), 'help' => __('Änderungen über API, MCP oder KI warten unter „Eingereicht“ auf Ihre Prüfung.'),
                'perm' => 'review.manage', 'feature' => 'review', 'default' => true, 'owner' => 'core'],
        ] + self::$extra;
    }

    /** Ereignis einer Erweiterung anmelden (Extension::pushEvent) – Schlüssel a–z, 0–9, Punkt, Bindestrich, Unterstrich */
    public static function registerEvent(string $key, string $label, ?string $perm = null, array $o = []): void
    {
        if (!preg_match('~^[a-z0-9][a-z0-9_.-]{1,58}$~', $key) || trim($label) === '') {
            throw new \InvalidArgumentException("Push-Ereignis „{$key}“: Schlüssel a–z, 0–9, . _ - (2–59 Zeichen) und eine Bezeichnung angeben.");
        }
        self::$extra[$key] = ['label' => $label, 'help' => (string) ($o['help'] ?? ''), 'perm' => $perm, 'feature' => isset($o['feature']) ? (string) $o['feature'] : null,
            'default' => (bool) ($o['default'] ?? true), 'owner' => (string) ($o['owner'] ?? 'ext')];
    }

    /** Ereignis wieder abmelden (Selbsttest) */
    public static function unregisterEvent(string $key): void
    {
        unset(self::$extra[$key]);
    }

    /** Funktion eines Ereignisses an? (die eigene Funktion „push“ über on(), damit der Selbsttest sie erzwingen kann) */
    public static function featureOn(?string $feature): bool
    {
        if ($feature === null || $feature === '') return true;
        return $feature === self::FEATURE ? self::on() : Features::on($feature, false);
    }

    /** Darf das Konto das (Rolle + Funktionsumfang)? Gesperrte Konten nie. */
    public static function userCan(array $u, ?string $perm, ?string $table = null, ?array $roles = null): bool
    {
        if (!empty($u['disabled'])) return false;
        if ($perm === null || $perm === '') return true;
        $roles ??= Permissions::roles();
        return Permissions::allows($roles[(string) $u['role']] ?? ($roles['author'] ?? null), $perm, $table);
    }

    /** Ereignisse, die eine Person unter Konto → Benachrichtigungen sieht (Funktion an, Recht vorhanden) */
    public static function visibleEvents(array $user): array
    {
        $roles = Permissions::roles();
        return array_filter(self::events(), fn($ev) => self::featureOn($ev['feature'] ?? null)
            && self::userCan($user, $ev['perm'] ?? null, null, $roles));
    }

    /** Schalter einer Person je Ereignis (gespeichert oder Vorgabe) */
    public static function userEvents(int $userId): array
    {
        $saved = [];
        foreach (app()->db->fetchAll('SELECT event, enabled FROM push_user_events WHERE user_id = ?', [$userId]) as $r) $saved[$r['event']] = (bool) $r['enabled'];
        $out = [];
        foreach (self::events() as $k => $ev) $out[$k] = $saved[$k] ?? (bool) $ev['default'];
        return $out;
    }

    /** Schalter speichern (nur bekannte Ereignisse) */
    public static function saveUserEvents(int $userId, array $on): void
    {
        $db = app()->db;
        $db->transaction(function () use ($db, $userId, $on): void {
            foreach (self::events() as $k => $ev) {
                if (!array_key_exists($k, $on)) continue;
                $db->query('DELETE FROM push_user_events WHERE user_id = ? AND event = ?', [$userId, $k]);
                $db->insert('push_user_events', ['user_id' => $userId, 'event' => $k, 'enabled' => $on[$k] ? 1 : 0]);
            }
        });
    }

    // ================================================================= Nachrichten

    /**
     * Mitteilung an Personen der Verwaltung (alle ihre Geräte), z. B. aus einer Erweiterung:
     *   Push::notifyUsers('freigabe.view', ['title' => 'Neues Feedback', 'body' => 'Startseite', 'url' => '/admin/freigabe/3'], 'freigabe.feedback');
     * $who: Liste von Benutzer-IDs | Recht (string) | ['perm' => Recht, 'table' => Tabelle]. Zusätzlich gelten Funktion und Recht des
     * Ereignisses und der Schalter der Person (Konto → Benachrichtigungen).
     * $payload: title, body, url (Pfad wie '/admin/…' oder absolute Adresse dieser Website), tag (gleicher Tag ersetzt die vorige Mitteilung),
     *           quiet (true = nicht anzeigen, solange die Verwaltung in einem sichtbaren Fenster offen ist) – oder fn(): array, dann je
     *           Sprache der Empfänger einmal aufgerufen (__() liefert darin die Sprache der Person)
     * $o: except (Benutzer-ID, z. B. wer es ausgelöst hat), table (Recht je Tabelle), ttl (Sekunden, Standard 1 Tag), urgency
     *     (very-low|low|normal|high), collapse (≤ 32 Zeichen a–z0–9_-: noch nicht zugestellte Nachricht gleichen Themas ersetzen)
     * → Anzahl der vorgemerkten Zustellungen (0 auch, wenn die Funktion aus ist). Wirft nie – Fehler gehen ins Fehlerprotokoll.
     */
    public static function notifyUsers(array|string $who, array|\Closure $payload, string $event, array $o = []): int
    {
        try {
            if (!self::enabled()) return 0;
            $ev = self::events()[$event] ?? null;
            if ($ev === null) {
                error_log("[push] Unbekanntes Ereignis „{$event}“ (Push::events, Extension::pushEvent)");
                return 0;
            }
            if (!self::featureOn($ev['feature'] ?? null)) return 0;
            $db = app()->db;
            $fp = Keys::fingerprint();
            $users = $db->fetchAll('SELECT DISTINCT u.id, u.role, u.disabled, u.locale FROM users u JOIN push_subscriptions s ON s.user_id = u.id WHERE s.key_fp = ?', [$fp]);
            if (!$users) return 0;
            $roles = Permissions::roles();
            $table = isset($o['table']) ? (string) $o['table'] : (is_array($who) && isset($who['table']) ? (string) $who['table'] : null);
            $ids = [];
            foreach ($users as $u) {
                $uid = (int) $u['id'];
                if (isset($o['except']) && (int) $o['except'] === $uid) continue;
                if (is_array($who) && array_is_list($who)) {
                    if (!in_array($uid, array_map('intval', $who), true)) continue;
                } elseif (!self::userCan($u, is_string($who) ? $who : (string) ($who['perm'] ?? ''), $table, $roles)) {
                    continue;
                }
                if (!self::userCan($u, $ev['perm'] ?? null, $table, $roles)) continue;
                if (!(self::userEvents($uid)[$event] ?? false)) continue;
                // Sprache der Mitteilung = Sprache der Verwaltung dieser Person (Inhalt als Callable wird je Sprache erzeugt)
                $loc = (string) (($u['locale'] ?? '') ?: (app()->settings->get('sys.admin_locale') ?: \Core\I18n::SOURCE));
                $ids[$payload instanceof \Closure ? $loc : ''][] = $uid;
            }
            if (!$ids) return 0;
            $since = time() - 600;
            $now = time();
            $n = 0;
            $before = \Core\I18n::locale();
            foreach ($ids as $loc => $group) {
                if ($payload instanceof \Closure) \Core\I18n::setLocale((string) $loc);
                try {
                    $msg = self::message('user', $event, null, $payload instanceof \Closure ? (array) $payload() : $payload, $o);
                } finally {
                    if ($payload instanceof \Closure) \Core\I18n::setLocale($before);
                }
                $in = implode(',', array_map('intval', $group));
                $c = 0;
                foreach ($db->fetchAll("SELECT s.id, (SELECT COUNT(*) FROM push_queue q WHERE q.subscription_id = s.id AND q.created_at > ?) AS recent
                    FROM push_subscriptions s WHERE s.user_id IN ($in) AND s.key_fp = ?", [$since, $fp]) as $s) {
                    if ((int) $s['recent'] >= self::DEVICE_PER_10MIN) continue;   // Gerät bekommt gerade sehr viel – überspringen
                    $db->insert('push_queue', ['message_id' => $msg, 'subscription_id' => (int) $s['id'], 'status' => 'queued', 'attempts' => 0, 'next_at' => $now, 'created_at' => $now]);
                    $c++;
                }
                $db->update('push_messages', ['recipients' => $c] + ($c ? [] : ['done_at' => now()]), 'id = :id', ['id' => $msg]);
                $n += $c;
            }
            if ($n) self::kick();
            return $n;
        } catch (\Throwable $e) {
            error_log('[push] notifyUsers: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mitteilung an alle Abos eines Themas (Besucher und Personen, die es abonniert haben), z. B. „data:aktuelles“.
     * $o: lang (nur Abos dieser Sprache; Standard = Standardsprache), ttl, urgency, collapse. Höchstens TOPIC_PER_HOUR je Thema
     * und Stunde; in der Testumgebung (staging) gar nicht (config 'push_staging_visitors'). → Anzahl der Zustellungen
     */
    public static function notifyTopic(string $topic, array $payload, string $event = 'topic', array $o = []): int
    {
        try {
            if (!self::enabled() || !self::validTopic($topic)) return 0;
            if (!self::visitorsAllowed()) {
                error_log("[push] Thema {$topic}: Testumgebung – keine Nachrichten an Besucher (config push_staging_visitors)");
                return 0;
            }
            $db = app()->db;
            $limit = max(1, (int) app()->config->get('push_topic_per_hour', self::TOPIC_PER_HOUR));
            if ((int) $db->fetchValue('SELECT COUNT(*) FROM push_messages WHERE topic = ? AND created_at > ?', [$topic, date('Y-m-d H:i:s', time() - 3600)]) >= $limit) {
                error_log("[push] Thema {$topic}: mehr als {$limit} Nachrichten in einer Stunde – übersprungen");
                return 0;
            }
            $lang = Lang::norm(isset($o['lang']) ? (string) $o['lang'] : null);
            $fp = Keys::fingerprint();
            $msg = self::message('topic', $event, $topic, $payload + ['lang' => $lang], $o);
            $now = time();
            $db->query("INSERT INTO push_queue (message_id, subscription_id, status, attempts, next_at, created_at)
                SELECT ?, id, 'queued', 0, ?, ? FROM push_subscriptions WHERE topics LIKE ? AND key_fp = ? AND COALESCE(lang, ?) = ?",
                [$msg, $now, $now, '%,' . $topic . ',%', $fp, Lang::default(), $lang]);
            $n = (int) $db->fetchValue('SELECT COUNT(*) FROM push_queue WHERE message_id = ?', [$msg]);
            $db->update('push_messages', ['recipients' => $n] + ($n ? [] : ['done_at' => now()]), 'id = :id', ['id' => $msg]);
            if ($n) self::kick();
            return $n;
        } catch (\Throwable $e) {
            error_log('[push] notifyTopic: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Testnachricht an alle Geräte einer Person – sofort gesendet (ohne Schalter je Ereignis).
     * → ['devices' => Anzahl, 'sent' => …, 'failed' => …, 'error' => ?string]
     */
    public static function test(int $userId): array
    {
        if (!self::enabled()) return ['devices' => 0, 'sent' => 0, 'failed' => 0, 'error' => __('Push-Benachrichtigungen sind nicht eingeschaltet.')];
        $db = app()->db;
        $subs = $db->fetchAll('SELECT id FROM push_subscriptions WHERE user_id = ? AND key_fp = ?', [$userId, Keys::fingerprint()]);
        if (!$subs) return ['devices' => 0, 'sent' => 0, 'failed' => 0, 'error' => __('Für Ihr Konto ist noch kein Gerät angemeldet.')];
        $msg = self::message('test', 'test', null, ['title' => __('Testnachricht von {site}', ['site' => site_name()]),
            'body' => __('Push-Benachrichtigungen funktionieren auf diesem Gerät.'), 'url' => '/admin/account#benachrichtigungen', 'tag' => 'push-test'],
            ['ttl' => 600, 'urgency' => 'high']);
        $now = time();
        foreach ($subs as $s) $db->insert('push_queue', ['message_id' => $msg, 'subscription_id' => (int) $s['id'], 'status' => 'queued', 'attempts' => 0, 'next_at' => $now, 'created_at' => $now]);
        $db->update('push_messages', ['recipients' => count($subs)], 'id = :id', ['id' => $msg]);
        $st = self::process(count($subs) + 20, 15.0);
        $row = $db->fetch('SELECT sent, failed FROM push_messages WHERE id = ?', [$msg]);
        $err = $db->fetchValue("SELECT error FROM push_queue WHERE message_id = ? AND status != 'sent' AND error IS NOT NULL ORDER BY id DESC LIMIT 1", [$msg]);
        return ['devices' => count($subs), 'sent' => (int) ($row['sent'] ?? 0), 'failed' => (int) ($row['failed'] ?? 0),
            'error' => !empty($st['locked']) ? __('Der Versand läuft gerade – die Testnachricht folgt in Kürze.') : ($err !== null ? (string) $err : null)];
    }

    /** Nachricht anlegen (Inhalt geprüft und gekürzt) → ID */
    private static function message(string $kind, string $event, ?string $topic, array $payload, array $o): int
    {
        $p = self::payload($payload);
        $collapse = isset($o['collapse']) && preg_match('~^[A-Za-z0-9_-]{1,32}$~', (string) $o['collapse']) ? (string) $o['collapse'] : null;
        $user = null;
        try {
            $user = isset(app()->auth) ? (app()->auth->user()['id'] ?? null) : null;
        } catch (\Throwable) {
        }
        return app()->db->insert('push_messages', ['kind' => $kind, 'event' => mb_substr($event, 0, 60), 'topic' => $topic, 'title' => mb_substr($p['title'], 0, 190),
            'payload_json' => json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'ttl' => max(60, min(28 * 86400, (int) ($o['ttl'] ?? 86400))),
            'urgency' => in_array($o['urgency'] ?? '', ['very-low', 'low', 'normal', 'high'], true) ? $o['urgency'] : 'normal',
            'collapse' => $collapse, 'user_id' => $user ? (int) $user : null, 'recipients' => 0, 'sent' => 0, 'failed' => 0, 'created_at' => now(),
            'status' => 'sent', 'source' => $kind === 'test' ? 'test' : 'auto']);
    }

    /**
     * Inhalt einer Mitteilung: nur title, body, url, tag, icon, image, quiet, lang, ts – Text ohne HTML, gekürzt; Adresse absolut auf dieser
     * Website (der Service Worker öffnet nur Adressen seiner eigenen Website). Höchstens WebPush::MAX_PAYLOAD Byte als JSON.
     */
    public static function payload(array $p): array
    {
        // Redaktionsnotizen „[# … #]“ (Core\EditorNotes) gehen nie hinaus
        $clean = fn(string $s, int $max) => mb_substr(trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(\Core\EditorNotes::strip($s)), ENT_QUOTES | ENT_HTML5))), 0, $max);
        $title = $clean((string) ($p['title'] ?? ''), 120);
        $url = trim((string) ($p['url'] ?? '/'));
        if (!preg_match('~^https?://~i', $url)) $url = self::origin() . url(str_starts_with($url, '/') ? $url : '/' . $url);
        $out = ['title' => $title !== '' ? $title : site_name(), 'body' => $clean((string) ($p['body'] ?? ''), 240), 'url' => mb_substr($url, 0, 600),
            'icon' => self::icon(), 'ts' => time()];
        // Großes Bild (Chrome/Edge/Android zeigen es, andere ignorieren es) – nur absolute Adressen
        if (!empty($p['image']) && preg_match('~^https?://~i', (string) $p['image'])) $out['image'] = mb_substr((string) $p['image'], 0, 600);
        if (!empty($p['tag'])) $out['tag'] = mb_substr((string) preg_replace('~[^A-Za-z0-9_.:-]~', '', (string) $p['tag']), 0, 64);
        if (!empty($p['quiet'])) $out['quiet'] = \Core\AdminPath::prefix();
        if (!empty($p['lang'])) $out['lang'] = (string) $p['lang'];
        while (strlen((string) json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > WebPush::MAX_PAYLOAD && $out['body'] !== '') {
            $out['body'] = mb_substr($out['body'], 0, max(0, mb_strlen($out['body']) - 40));
        }
        return $out;
    }

    /**
     * Ursprung der Website für Adressen in Mitteilungen: kanonische Adresse bzw. aufgerufene Domain, auf der Kommandozeile (Cron)
     * die erste Domain der Website. Leer → relative Adresse (der Service Worker löst sie auf seiner Website auf).
     */
    public static function origin(): string
    {
        $u = site_url();
        if ($u !== '') return $u;
        $host = (string) (site()->hosts()[0] ?? '');
        if ($host === '' || !preg_match('~^[a-z0-9.-]+(:\d+)?$~i', $host)) return '';
        return (str_contains($host, 'localhost') ? 'http://' : 'https://') . $host;
    }

    /** Symbol der Mitteilungen: App-Icon der Website (192 px), absolut */
    public static function icon(): string
    {
        try {
            return self::origin() . \Core\AppIcons::url('icon-192.png');
        } catch (\Throwable) {
            return '';
        }
    }

    // ================================================================= Versand

    /** Nach der Antwort eine kleine Menge senden (einmal je Anfrage; nicht auf der Kommandozeile – dort push:send) */
    public static function kick(): void
    {
        if (self::$kicked || PHP_SAPI === 'cli') return;
        self::$kicked = true;
        register_shutdown_function(static function (): void {
            try {
                if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
                ignore_user_abort(true);
                @set_time_limit(60);
                self::process(30, 8.0);
            } catch (\Throwable $e) {
                error_log('[push] nach der Antwort: ' . $e->getMessage());
            }
        });
    }

    /**
     * Fällige Zustellungen senden (eine Sperre je Website). → Zähler sent, retry, failed, gone, expired (+ locked/keys)
     */
    public static function process(int $limit = 500, float $budget = 50.0): array
    {
        $stats = ['sent' => 0, 'retry' => 0, 'failed' => 0, 'gone' => 0, 'expired' => 0];
        $keys = Keys::get();
        if (!$keys) return $stats + ['keys' => false];
        $dir = site()->storage('cache');
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $lock = @fopen($dir . '/push.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) fclose($lock);
            return $stats + ['locked' => true];
        }
        $db = app()->db;
        $touched = [];
        try {
            $fp = Keys::fingerprint();
            $subject = Keys::subject();
            // Geplante Mitteilungen, deren Zeit gekommen ist: Empfänger jetzt ermitteln und vormerken (Core\Push\Compose)
            if (Compose::due($db)) $touched[0] = true;
            $start = microtime(true);
            while ($limit > 0 && microtime(true) - $start < $budget) {
                $rows = $db->fetchAll("SELECT q.id, q.attempts, q.message_id, q.subscription_id, s.id AS sid, s.endpoint, s.p256dh, s.auth, s.key_fp,
                        m.payload_json, m.ttl, m.urgency, m.collapse, m.created_at AS mcreated
                    FROM push_queue q JOIN push_messages m ON m.id = q.message_id LEFT JOIN push_subscriptions s ON s.id = q.subscription_id
                    WHERE q.status = 'queued' AND q.next_at <= ? ORDER BY q.id LIMIT " . min(50, $limit), [time()]);
                if (!$rows) break;
                $limit -= count($rows);
                $jobs = [];
                $byId = [];
                foreach ($rows as $r) {
                    $touched[(int) $r['message_id']] = true;
                    $age = max(0, time() - (int) strtotime((string) $r['mcreated']));
                    if ($r['sid'] === null || $r['key_fp'] !== $fp) {
                        self::mark($db, (int) $r['id'], 'gone', null, __('Abo nicht mehr gültig'));
                        $stats['gone']++;
                        continue;
                    }
                    if ($age >= (int) $r['ttl']) {
                        self::mark($db, (int) $r['id'], 'expired', null, __('Zu lange gewartet (TTL)'));
                        $stats['expired']++;
                        continue;
                    }
                    $byId[(int) $r['id']] = $r;
                    $jobs[] = ['id' => (int) $r['id'], 'endpoint' => (string) $r['endpoint'], 'p256dh' => (string) WebPush::unb64u((string) $r['p256dh']),
                        'auth' => (string) WebPush::unb64u((string) $r['auth']), 'payload' => (string) $r['payload_json'],
                        'ttl' => max(0, (int) $r['ttl'] - $age), 'urgency' => (string) $r['urgency'], 'topic' => $r['collapse']];
                }
                if (!$jobs) continue;
                $res = is_callable(self::$transport) ? (self::$transport)($jobs) : WebPush::sendAll($jobs, $keys, $subject);
                foreach ($byId as $qid => $r) {
                    $x = $res[$qid] ?? ['code' => 0, 'error' => 'keine Antwort', 'retry_after' => null];
                    $code = (int) $x['code'];
                    $sid = (int) $r['sid'];
                    if ($code >= 200 && $code < 300) {
                        self::mark($db, $qid, 'sent', $code, null);
                        $db->update('push_subscriptions', ['last_sent_at' => now(), 'failures' => 0, 'last_error' => null], 'id = :id', ['id' => $sid]);
                        $stats['sent']++;
                    } elseif ($code === 404 || $code === 410) {
                        // Abo abgelaufen oder abbestellt (Browser, Push-Dienst) → löschen
                        self::mark($db, $qid, 'gone', $code, (string) $x['error']);
                        self::delete($sid, $db);
                        $stats['gone']++;
                    } elseif (empty($x['fatal']) && ($code === 0 || $code === 429 || $code >= 500)) {
                        $att = (int) $r['attempts'] + 1;
                        if ($att >= self::MAX_ATTEMPTS) {
                            self::mark($db, $qid, 'failed', $code ?: null, (string) $x['error'], $att);
                            self::failure($db, $sid, (string) $x['error']);
                            $stats['failed']++;
                        } else {
                            $wait = max((int) ($x['retry_after'] ?? 0), 60 * (2 ** ($att - 1)));
                            $db->update('push_queue', ['attempts' => $att, 'next_at' => time() + min($wait, 6 * 3600), 'http_code' => $code ?: null,
                                'error' => mb_substr((string) $x['error'], 0, 190)], 'id = :id', ['id' => $qid]);
                            $stats['retry']++;
                        }
                    } else {
                        // 400, 401, 403 (z. B. falscher VAPID-Schlüssel), 413 (zu groß) … – nicht wiederholen
                        self::mark($db, $qid, 'failed', $code ?: null, (string) $x['error']);
                        self::failure($db, $sid, (string) $x['error']);
                        $stats['failed']++;
                    }
                }
            }
            unset($touched[0]);
            foreach (array_keys($touched) as $mid) self::count($db, $mid);
            if ($touched || random_int(1, 20) === 1) self::housekeeping($db, $fp);
            if ($touched) app()->settings->set('sys.push_last_run', ['at' => now(), 'stats' => $stats]);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return $stats;
    }

    private static function mark(Database $db, int $id, string $status, ?int $code, ?string $error, ?int $attempts = null): void
    {
        $db->update('push_queue', ['status' => $status, 'http_code' => $code, 'error' => $error !== null ? mb_substr($error, 0, 190) : null, 'sent_at' => time()]
            + ($attempts !== null ? ['attempts' => $attempts] : []), 'id = :id', ['id' => $id]);
    }

    /** Fehlversuch eines Abos zählen; nach MAX_FAILURES in Folge löschen */
    private static function failure(Database $db, int $sid, string $error): void
    {
        $db->query('UPDATE push_subscriptions SET failures = failures + 1, last_error = ? WHERE id = ?', [mb_substr($error, 0, 190), $sid]);
        if ((int) $db->fetchValue('SELECT failures FROM push_subscriptions WHERE id = ?', [$sid]) >= self::MAX_FAILURES) self::delete($sid, $db);
    }

    /** Zähler einer Nachricht aktualisieren; fertig, wenn nichts mehr wartet */
    private static function count(Database $db, int $mid): void
    {
        $c = [];
        foreach ($db->fetchAll('SELECT status, COUNT(*) AS n FROM push_queue WHERE message_id = ? GROUP BY status', [$mid]) as $r) $c[$r['status']] = (int) $r['n'];
        $db->update('push_messages', ['sent' => $c['sent'] ?? 0, 'failed' => $c['failed'] ?? 0, 'gone' => $c['gone'] ?? 0, 'expired' => $c['expired'] ?? 0]
            + (empty($c['queued']) ? ['done_at' => now()] : []), 'id = :id', ['id' => $mid]);
    }

    /** Aufräumen: erledigte Zustellungen nach 14 Tagen, Nachrichten nach 90 Tagen, Abos mit altem Schlüssel */
    private static function housekeeping(Database $db, string $fp): void
    {
        $db->query("DELETE FROM push_queue WHERE status != 'queued' AND created_at < ?", [time() - 14 * 86400]);
        $db->query('DELETE FROM push_messages WHERE done_at IS NOT NULL AND created_at < ? AND id NOT IN (SELECT DISTINCT message_id FROM push_queue)', [date('Y-m-d H:i:s', time() - 90 * 86400)]);
        if ($fp !== '') {
            foreach ($db->fetchAll('SELECT id FROM push_subscriptions WHERE key_fp != ?', [$fp]) as $r) self::delete((int) $r['id'], $db);
        }
        Stats::purge($db);
    }

    // ================================================================= Übersicht

    /** Stand für Grundeinstellungen → Push-Benachrichtigungen und push:status */
    public static function status(): array
    {
        $db = app()->db;
        $fp = Keys::fingerprint();
        $k = Keys::get();
        $topics = [];
        foreach ($db->fetchAll("SELECT topics FROM push_subscriptions WHERE topics IS NOT NULL AND topics != '' AND key_fp = ?", [$fp]) as $r) {
            foreach (self::topicsOf($r) as $t) $topics[$t] = ($topics[$t] ?? 0) + 1;
        }
        ksort($topics);
        $since = time() - 7 * 86400;
        return [
            'feature' => self::on(), 'keys' => $k !== null, 'fingerprint' => $fp, 'created' => $k['created'] ?? null, 'environment' => environment(),
            'visitors_allowed' => self::visitorsAllowed(),
            'devices' => (int) $db->fetchValue('SELECT COUNT(*) FROM push_subscriptions WHERE user_id IS NOT NULL AND key_fp = ?', [$fp]),
            'people' => (int) $db->fetchValue('SELECT COUNT(DISTINCT user_id) FROM push_subscriptions WHERE user_id IS NOT NULL AND key_fp = ?', [$fp]),
            'visitors' => (int) $db->fetchValue("SELECT COUNT(*) FROM push_subscriptions WHERE user_id IS NULL AND topics IS NOT NULL AND topics != '' AND key_fp = ?", [$fp]),
            'stale' => (int) $db->fetchValue('SELECT COUNT(*) FROM push_subscriptions WHERE key_fp != ?', [$fp]),
            'topics' => $topics,
            'queued' => (int) $db->fetchValue("SELECT COUNT(*) FROM push_queue WHERE status = 'queued'"),
            'sent7' => (int) $db->fetchValue("SELECT COUNT(*) FROM push_queue WHERE status = 'sent' AND created_at > ?", [$since]),
            'failed7' => (int) $db->fetchValue("SELECT COUNT(*) FROM push_queue WHERE status IN ('failed', 'expired') AND created_at > ?", [$since]),
            'gone7' => (int) $db->fetchValue("SELECT COUNT(*) FROM push_queue WHERE status = 'gone' AND created_at > ?", [$since]),
            'last_run' => (array) app()->settings->get('sys.push_last_run', []),
            'last_error' => $db->fetchValue("SELECT error FROM push_queue WHERE status IN ('failed', 'expired') AND error IS NOT NULL ORDER BY id DESC LIMIT 1"),
        ];
    }

    /** Vorschlag für die Datenschutzerklärung (Grundeinstellungen → Push-Benachrichtigungen, Tabelleneinstellungen) */
    public static function privacyText(): string
    {
        return __('Push-Benachrichtigungen: Wenn Sie auf unserer Website Benachrichtigungen abonnieren, speichern wir nur die von Ihrem Browser erzeugte Zustelladresse (Endpunkt beim Push-Dienst Ihres Browser-Herstellers, z. B. Google, Mozilla, Apple oder Microsoft) mit den zugehörigen Schlüsseln, die gewählten Themen und die Sprache – keine IP-Adresse, keine Cookies, kein Tracking. Die Mitteilungen werden Ende-zu-Ende-verschlüsselt über diesen Push-Dienst zugestellt; er erfährt dabei nur Zeitpunkt und Größe. Rechtsgrundlage ist Ihre Einwilligung (Art. 6 Abs. 1 lit. a DSGVO), die Sie jederzeit über „Abbestellen“ auf unserer Website oder in den Einstellungen Ihres Browsers widerrufen können. Ungültig gewordene Abos löschen wir automatisch.');
    }

    /** Zeilen für `php bin/console health` (nur Hinweise) */
    public static function health(): array
    {
        if (!self::on()) return [];
        $s = self::status();
        $out = ['Push: VAPID-Schlüssel vorhanden (config.local.php)' => $s['keys'] ? true : null];
        $last = (string) ($s['last_run']['at'] ?? '');
        if ($s['queued'] > 0) $out['Push: Versand läuft (' . $s['queued'] . ' wartend, zuletzt ' . ($last ?: 'nie') . ') – Cron push:send --all'] = $last !== '' && strtotime($last) > time() - 3600 ? true : null;
        return $out;
    }
}
