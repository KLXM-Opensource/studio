<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Geplante Aufgaben mit einem einzigen Cronjob für die ganze Installation:
 *
 *   * * * * *   cd /pfad/zur/installation && php bin/console cron:run
 *
 * Kern und Erweiterungen melden Aufgaben an (Cron::register bzw. Manifest-Schlüssel 'schedule'): Schlüssel, Bezeichnung, Befehl der
 * Kommandozeile (läuft als eigener Prozess, z. B. „search:index --all“), Zeitplan im Cron-Format („*\/15 * * * *“). cron:run
 * prüft jede Minute, was fällig ist, und startet es im Hintergrund (cron:exec) – mit Sperre gegen Doppelläufe und Zeitlimit.
 * Dazu eine Warteschlange für einmalige Läufe („Jetzt ausführen“, Cron::enqueue()) mit Wiederholung bei Fehlern.
 * Zustand und Protokoll: storage/cron/cron.sqlite (installationsweit). Verwaltung: System → Geplante Aufgaben (/admin/system/aufgaben).
 * Zeitpläne lassen sich dort ändern und Aufgaben abschalten; die Vorgabe aus dem Code bleibt als „Standard“ erhalten.
 *
 * Erweiterungen (Manifest, wird ohne Start der Erweiterung gelesen):
 *   'schedule' => ['rollup' => ['label' => 'Tage verdichten', 'command' => 'report:rollup', 'cron' => '20 1 * * *',
 *                               'scope' => 'site' (Standard: je Website, auf der die Erweiterung aktiv ist) | 'installation' (einmal), 'timeout' => 600]]
 * Schlüssel der Aufgabe: {erweiterung}.{name}. Der Befehl läuft mit --site=… der jeweiligen Website.
 */
final class Cron
{
    /** Herzschlag älter als das: Cron läuft nicht */
    public const STALE = 600;
    /** Höchstdauer je Lauf (Sekunden), danach wird der Prozess beendet */
    public const TIMEOUT = 3300;

    /** @var array<string, array{key: string, label: string, command: string, schedule: string, group: string, owner: string, timeout: int, help: string, scope: string}> */
    private static array $tasks = [];
    private static ?Database $db = null;
    private static bool $booted = false;

    /**
     * Aufgabe anmelden. $opt: group (Überschrift in der Verwaltung), owner (core | Name der Erweiterung), timeout (Sekunden), help.
     * Befehl ohne „php bin/console“, z. B. „report:rollup --all“.
     */
    public static function register(string $key, string $label, string $command, string $schedule, array $opt = []): void
    {
        if (!preg_match('~^[a-z][a-z0-9_.:-]{1,60}$~', $key) || !self::valid($schedule) || !preg_match('~^[a-z][a-z0-9_.-]*:[a-z0-9_-]+( [^;&|`$<>]*)?$~i', $command)) {
            error_log("[cron] ungültige Aufgabe $key ($schedule, $command)");
            return;
        }
        self::$tasks[$key] = ['key' => $key, 'label' => $label, 'command' => $command, 'schedule' => $schedule, 'group' => (string) ($opt['group'] ?? __('Kern')),
            'owner' => (string) ($opt['owner'] ?? 'core'), 'timeout' => max(30, min(self::TIMEOUT, (int) ($opt['timeout'] ?? 1800))), 'help' => (string) ($opt['help'] ?? ''),
            'scope' => in_array($opt['scope'] ?? '', ['site', 'installation'], true) ? $opt['scope'] : 'installation'];
    }

    /** Aufgaben von Kern und installierten Erweiterungen anmelden (einmal je Prozess) */
    public static function boot(): void
    {
        if (self::$booted) return;
        self::$booted = true;
        self::core();
        self::extensions();
    }

    /** Aufgaben aus den Manifesten installierter Erweiterungen ('schedule') */
    public static function extensions(): void
    {
        foreach (Extensions::available() as $name => $m) {
            foreach ((array) ($m['schedule'] ?? []) as $k => $t) {
                if (!is_array($t) || !is_string($k) || empty($t['command'])) continue;
                $label = (string) ($m['label'] ?? $name);
                self::register($name . '.' . $k, Extensions::tr($name, (string) ($t['label'] ?? $k)), (string) $t['command'], (string) ($t['cron'] ?? ''), [
                    'group' => Extensions::tr($name, $label), 'owner' => $name, 'timeout' => (int) ($t['timeout'] ?? 1800),
                    'help' => isset($t['help']) ? Extensions::tr($name, (string) $t['help']) : '', 'scope' => ($t['scope'] ?? 'site') === 'installation' ? 'installation' : 'site']);
            }
        }
    }

    /** Websites, auf denen die Erweiterung einer Aufgabe aktiv ist (Kern: []) */
    public static function sitesFor(string $key): array
    {
        $t = self::$tasks[$key] ?? null;
        if (!$t || $t['owner'] === 'core') return [];
        $out = [];
        foreach (array_keys(Sites::all()) as $s) if (Extensions::enabledForSite((string) $s, $t['owner'])) $out[] = (string) $s;
        return $out;
    }

    /** Aufgaben des Kerns (bisher einzeln als Cronjob dokumentiert) */
    public static function core(): void
    {
        $g = __('Kern');
        self::register('search.sync', __('Suchindex abgleichen'), 'search:index --all', '*/15 * * * *', ['group' => $g, 'help' => __('Nur geänderte Seiten und Einträge.')]);
        self::register('search.full', __('Suchindex neu aufbauen'), 'search:index --all --full', '30 3 * * *', ['group' => $g]);
        self::register('inbox.purge', __('Aufbewahrungsfristen anwenden'), 'inbox:purge --all', '15 4 * * *', ['group' => $g, 'help' => __('Erledigte Anfragen und alte Protokolleinträge löschen.')]);
        self::register('media.video_web', __('Web-Fassung für Videos'), 'media:web --all', '*/10 * * * *', ['group' => $g, 'timeout' => 900,
            'help' => __('Große Videos der Mediathek bekommen eine kleinere Fassung, die auf der Website sofort startet. Ohne ffmpeg wirkungslos.')]);
        self::register('push.send', __('Push-Benachrichtigungen senden'), 'push:send --all', '* * * * *', ['group' => $g, 'timeout' => 300]);
        self::register('ai.jobs', __('KI-Aufträge abarbeiten'), 'ai:jobs --all', '*/5 * * * *', ['group' => $g, 'help' => __('Transkription und Übersetzung von Untertiteln.')]);
        self::register('sources.sync', __('Externe Quellen abgleichen'), 'sources:sync --all', '*/15 * * * *', ['group' => $g]);
        self::register('chat.digest', __('Chat: Hinweise auf ungelesene Erwähnungen'), 'chat:digest', '*/15 * * * *', ['group' => $g]);
        self::register('backup.sites', __('Sicherungen der Websites'), 'site:backup --all --keep=14', '0 2 * * *', ['group' => $g, 'timeout' => 3300, 'help' => __('Die neuesten 14 je Website bleiben.')]);
        self::register('backup.pools', __('Sicherungen der geteilten Medien'), 'pool:backup --all --keep=14', '20 2 * * *', ['group' => $g, 'timeout' => 3300]);
        self::register('backup.shared', __('Sicherungen der geteilten Tabellen'), 'shared:backup --all --keep=14', '40 2 * * *', ['group' => $g]);
    }

    /** @return array<string, array> alle angemeldeten Aufgaben mit Zustand (enabled, schedule, default, last, next) */
    public static function tasks(): array
    {
        self::boot();
        $state = [];
        try {
            foreach (self::db()->fetchAll('SELECT * FROM cron_tasks') as $r) $state[$r['k']] = $r;
        } catch (\Throwable) {
        }
        $out = [];
        foreach (self::$tasks as $k => $t) {
            $s = $state[$k] ?? [];
            $sched = (string) (($s['schedule'] ?? '') ?: $t['schedule']);
            $out[$k] = ['default' => $t['schedule'], 'schedule' => $sched, 'custom' => ($s['schedule'] ?? '') !== '' && $s['schedule'] !== $t['schedule'],
                'enabled' => !isset($s['enabled']) || (int) $s['enabled'] === 1, 'last_start' => $s['last_start'] ?? null, 'last_end' => $s['last_end'] ?? null,
                'last_status' => $s['last_status'] ?? null, 'last_output' => $s['last_output'] ?? '', 'next' => self::next($sched)] + $t;
        }
        return $out;
    }

    // ------------------------------------------------------------------ Zeitausdrücke (Minute Stunde Tag Monat Wochentag)

    public static function valid(string $expr): bool
    {
        $f = preg_split('~\s+~', trim($expr));
        if (count($f) !== 5) return false;
        foreach ([[0, 59], [0, 23], [1, 31], [1, 12], [0, 7]] as $i => [$lo, $hi]) if (self::values($f[$i], $lo, $hi) === null) return false;
        return true;
    }

    /** Werte eines Feldes: *, a, a-b, a,b, *\/n, a-b/n → Liste oder null bei Fehler */
    private static function values(string $field, int $lo, int $hi): ?array
    {
        $out = [];
        foreach (explode(',', $field) as $part) {
            if (!preg_match('~^(\*|\d+(?:-\d+)?)(?:/(\d+))?$~', $part, $m)) return null;
            $step = isset($m[2]) ? max(1, (int) $m[2]) : 1;
            [$a, $b] = $m[1] === '*' ? [$lo, $hi] : (str_contains($m[1], '-') ? array_map('intval', explode('-', $m[1])) : [(int) $m[1], isset($m[2]) ? $hi : (int) $m[1]]);
            if ($a < $lo || $b > $hi || $a > $b) return null;
            for ($v = $a; $v <= $b; $v += $step) $out[$v] = true;
        }
        return array_keys($out);
    }

    public static function due(string $expr, int $ts): bool
    {
        $f = preg_split('~\s+~', trim($expr));
        if (count($f) !== 5) return false;
        [$mi, $h, $d, $mo, $w] = [(int) date('i', $ts), (int) date('G', $ts), (int) date('j', $ts), (int) date('n', $ts), (int) date('w', $ts)];
        $in = fn(int $i, int $v, int $lo, int $hi) => in_array($v, self::values($f[$i], $lo, $hi) ?? [], true);
        $dow = $in(4, $w, 0, 7) || ($w === 0 && $in(4, 7, 0, 7));
        $dom = $in(2, $d, 1, 31);
        // Wie cron: sind Tag UND Wochentag eingeschränkt, genügt eines von beiden
        $day = ($f[2] !== '*' && $f[4] !== '*') ? ($dom || $dow) : ($dom && $dow);
        return $in(0, $mi, 0, 59) && $in(1, $h, 0, 23) && $in(3, $mo, 1, 12) && $day;
    }

    /** Nächster Zeitpunkt (bis 32 Tage voraus) oder null */
    public static function next(string $expr, ?int $from = null): ?int
    {
        if (!self::valid($expr)) return null;
        $t = (int) (floor(($from ?? time()) / 60) * 60) + 60;
        for ($i = 0; $i < 46080; $i++, $t += 60) if (self::due($expr, $t)) return $t;
        return null;
    }

    /** Zeitplan in Worten: „jede Minute“, „alle 15 Minuten“, „täglich um 04:15“, „montags um 06:00“ … sonst der Ausdruck */
    public static function describe(string $expr): string
    {
        $f = preg_split('~\s+~', trim($expr));
        if (count($f) !== 5) return $expr;
        [$mi, $h, $d, $mo, $w] = $f;
        if ($expr === '* * * * *') return __('jede Minute');
        if (preg_match('~^\*/(\d+)$~', $mi, $m) && $h === '*' && $d === '*' && $mo === '*' && $w === '*') return __('alle {n} Minuten', ['n' => $m[1]]);
        if (ctype_digit($mi) && $h === '*' && $d === '*' && $mo === '*' && $w === '*') return __('stündlich um :{m}', ['m' => str_pad($mi, 2, '0', STR_PAD_LEFT)]);
        if (ctype_digit($mi) && preg_match('~^\*/(\d+)$~', $h, $m) && $d === '*' && $mo === '*' && $w === '*') return __('alle {n} Stunden', ['n' => $m[1]]);
        if (ctype_digit($mi) && ctype_digit($h) && $mo === '*') {
            $time = sprintf('%02d:%02d', (int) $h, (int) $mi);
            if ($d === '*' && $w === '*') return __('täglich um {time}', ['time' => $time]);
            if ($d === '*' && ctype_digit($w)) return __('{day}s um {time}', ['day' => Format::admin()->date(mktime(12, 0, 0, 10, 18 + (int) $w % 7, 2026), 'weekday'), 'time' => $time]);
            if (ctype_digit($d) && $w === '*') return __('monatlich am {d}. um {time}', ['d' => $d, 'time' => $time]);
        }
        return $expr;
    }

    /** Auswahl für die Verwaltung */
    public static function presets(): array
    {
        return ['* * * * *' => __('jede Minute'), '*/5 * * * *' => __('alle 5 Minuten'), '*/15 * * * *' => __('alle 15 Minuten'), '0 * * * *' => __('stündlich'),
            '0 */6 * * *' => __('alle 6 Stunden'), '0 3 * * *' => __('täglich um 03:00'), '0 4 * * 1' => __('montags um 04:00'), '0 4 1 * *' => __('monatlich am 1. um 04:00')];
    }

    // ------------------------------------------------------------------ Speicher

    public static function dir(): string
    {
        $d = ROOT . '/storage/cron';
        if (!is_dir($d)) @mkdir($d, 0770, true);
        return $d;
    }

    public static function db(): Database
    {
        if (self::$db) return self::$db;
        $db = new Database(['driver' => 'sqlite', 'path' => self::dir() . '/cron.sqlite']);
        $db->pdo->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000');
        Db\Table::named('cron_tasks')->column('k', 'string', ['length' => 64, 'null' => false])->column('enabled', 'bool', ['default' => true])
            ->column('schedule', 'string', ['length' => 64])->column('last_start', 'int')->column('last_end', 'int')->column('last_status', 'string', ['length' => 12])
            ->column('last_output', 'text')->primary(['k'])->ensure($db);
        Db\Table::named('cron_queue')->id()->column('k', 'string', ['length' => 64, 'null' => false])->column('status', 'string', ['length' => 12, 'default' => 'waiting'])
            ->column('created', 'int')->column('started', 'int')->column('ended', 'int')->column('attempts', 'int', ['default' => 0])->column('output', 'text')
            ->column('by', 'string', ['length' => 120])->index(['status'])->ensure($db);
        Db\Table::named('cron_runs')->id()->column('k', 'string', ['length' => 64, 'null' => false])->column('started', 'int')->column('ended', 'int')
            ->column('status', 'string', ['length' => 12])->column('output', 'text')->column('trigger', 'string', ['length' => 12])->index(['k', 'started'])->ensure($db);
        return self::$db = $db;
    }

    /** Zustand einer Aufgabe setzen (Verwaltung): enabled, schedule ('' = Standard) */
    public static function configure(string $key, ?bool $enabled = null, ?string $schedule = null): void
    {
        self::boot();
        if (!isset(self::$tasks[$key])) throw new \InvalidArgumentException('Unbekannte Aufgabe.');
        if ($schedule !== null && $schedule !== '' && !self::valid($schedule)) throw new \InvalidArgumentException(__('Ungültiger Zeitplan.'));
        $db = self::db();
        $db->query('INSERT OR IGNORE INTO cron_tasks (k, enabled) VALUES (?, 1)', [$key]);
        if ($enabled !== null) $db->query('UPDATE cron_tasks SET enabled = ? WHERE k = ?', [$enabled ? 1 : 0, $key]);
        if ($schedule !== null) $db->query('UPDATE cron_tasks SET schedule = ? WHERE k = ?', [$schedule === self::$tasks[$key]['schedule'] ? '' : $schedule, $key]);
    }

    /** Einmaligen Lauf einreihen (läuft beim nächsten cron:run) */
    public static function enqueue(string $key, string $by = ''): int
    {
        self::boot();
        if (!isset(self::$tasks[$key])) throw new \InvalidArgumentException('Unbekannte Aufgabe.');
        $db = self::db();
        $open = $db->fetchValue("SELECT id FROM cron_queue WHERE k = ? AND status IN ('waiting', 'running')", [$key]);
        if ($open) return (int) $open;
        return $db->insert('cron_queue', ['k' => $key, 'status' => 'waiting', 'created' => time(), 'by' => mb_substr($by, 0, 120)]);
    }

    public static function heartbeat(): ?int
    {
        $f = self::dir() . '/heartbeat';
        return is_file($f) ? (int) file_get_contents($f) : null;
    }

    public static function running(): bool
    {
        $h = self::heartbeat();
        return $h !== null && $h > time() - self::STALE;
    }

    // ------------------------------------------------------------------ Ausführen

    /** cron:run – fällige Aufgaben und Warteschlange im Hintergrund starten; gibt gestartete Schlüssel zurück */
    public static function run(?int $now = null): array
    {
        $now = $now ?? time();
        @file_put_contents(self::dir() . '/heartbeat', (string) $now, LOCK_EX);
        $start = [];
        foreach (self::tasks() as $k => $t) {
            if (!$t['enabled'] || !self::due($t['schedule'], $now)) continue;
            if ($t['owner'] !== 'core' && !self::sitesFor($k)) continue;   // Erweiterung auf keiner Website aktiv
            $start[$k] = 'schedule';
        }
        $db = self::db();
        foreach ($db->fetchAll("SELECT id, k FROM cron_queue WHERE status = 'waiting' ORDER BY id LIMIT 20") as $q) {
            if (!isset(self::$tasks[$q['k']])) { $db->query("UPDATE cron_queue SET status = 'failed', output = 'Aufgabe unbekannt' WHERE id = ?", [$q['id']]); continue; }
            $start[$q['k']] = 'queue:' . $q['id'];
        }
        foreach ($start as $k => $trigger) self::spawn($k, $trigger);
        // Protokoll begrenzen: je Aufgabe die letzten 50 Läufe, fertige Einträge der Warteschlange 14 Tage
        if ((int) date('i', $now) === 7) {
            $db->query('DELETE FROM cron_runs WHERE id NOT IN (SELECT id FROM cron_runs r WHERE r.k = cron_runs.k ORDER BY id DESC LIMIT 50)');
            $db->query("DELETE FROM cron_queue WHERE status IN ('done', 'failed') AND ended < ?", [$now - 14 * 86400]);
        }
        return array_keys($start);
    }

    /** Aufgabe als eigenen Prozess starten (cron:exec), ohne zu warten */
    private static function spawn(string $key, string $trigger): void
    {
        $cmd = escapeshellarg(Network\Stats::phpBinary()) . ' ' . escapeshellarg(ROOT . '/bin/console') . ' cron:exec ' . escapeshellarg($key) . ' ' . escapeshellarg($trigger);
        if (PHP_OS_FAMILY === 'Windows') { pclose(popen('start /B ' . $cmd, 'r')); return; }
        exec($cmd . ' > /dev/null 2>&1 &');
    }

    /** cron:exec – eine Aufgabe ausführen (Sperre, Zeitlimit, Protokoll). Rückgabe: Exit-Code */
    public static function exec(string $key, string $trigger = 'manual'): int
    {
        self::boot();
        $t = self::$tasks[$key] ?? null;
        if (!$t) return 2;
        $lock = fopen(self::dir() . '/' . preg_replace('~[^a-z0-9_.-]~', '_', $key) . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return 0;   // läuft schon
        $db = self::db();
        $qid = str_starts_with($trigger, 'queue:') ? (int) substr($trigger, 6) : 0;
        $started = time();
        if ($qid) $db->query("UPDATE cron_queue SET status = 'running', started = ?, attempts = attempts + 1 WHERE id = ?", [$started, $qid]);
        $db->query('INSERT OR IGNORE INTO cron_tasks (k, enabled) VALUES (?, 1)', [$key]);
        $db->query("UPDATE cron_tasks SET last_start = ?, last_status = 'running' WHERE k = ?", [$started, $key]);
        if ($t['owner'] === 'core') {
            [$code, $out] = self::process($t['command'], $t['timeout']);
        } else {
            // Erweiterung: je Website, auf der sie aktiv ist (scope site), bzw. einmal mit der ersten solchen Website
            $sites = self::sitesFor($key);
            if ($t['scope'] === 'installation') $sites = array_slice($sites, 0, 1);
            [$code, $out] = [0, $sites ? '' : __('Auf keiner Website aktiv – nichts zu tun.')];
            $until = time() + $t['timeout'];
            foreach ($sites as $s) {
                [$c, $o] = self::process($t['command'] . ' --site=' . $s, max(30, $until - time()));
                $out .= ($t['scope'] === 'site' ? "── $s\n" : '') . trim($o) . "\n";
                if ($c !== 0 && $code === 0) $code = $c;
                if ($c === 124) break;
            }
        }
        $status = $code === 0 ? 'ok' : ($code === 124 ? 'timeout' : 'error');
        $out = mb_substr(trim($out), -4000);
        $ended = time();
        $db->query('UPDATE cron_tasks SET last_end = ?, last_status = ?, last_output = ? WHERE k = ?', [$ended, $status, $out, $key]);
        $db->insert('cron_runs', ['k' => $key, 'started' => $started, 'ended' => $ended, 'status' => $status, 'output' => $out, 'trigger' => $qid ? 'queue' : ($trigger === 'schedule' ? 'schedule' : 'manual')]);
        if ($qid) {
            // Fehler: bis zu 3 Versuche (nächster Lauf), dann „fehlgeschlagen“
            $attempts = (int) $db->fetchValue('SELECT attempts FROM cron_queue WHERE id = ?', [$qid]);
            $q = $status === 'ok' ? 'done' : ($attempts < 3 ? 'waiting' : 'failed');
            $db->query('UPDATE cron_queue SET status = ?, ended = ?, output = ? WHERE id = ?', [$q, $ended, $out, $qid]);
        }
        flock($lock, LOCK_UN);
        return $code;
    }

    /** Befehl der Kommandozeile als Prozess mit Zeitlimit → [Exit-Code, Ausgabe] */
    private static function process(string $command, int $timeout): array
    {
        $args = array_merge([Network\Stats::phpBinary(), ROOT . '/bin/console'], preg_split('~\s+~', trim($command)) ?: []);
        $p = proc_open($args, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT);
        if (!is_resource($p)) return [1, 'Prozess konnte nicht gestartet werden.'];
        stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $out = ''; $until = time() + $timeout;
        while (true) {
            $out .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            if (strlen($out) > 200000) $out = substr($out, -100000);
            $st = proc_get_status($p);
            if (!$st['running']) { $out .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]); $code = (int) $st['exitcode']; break; }
            if (time() > $until) { proc_terminate($p); $out .= "\n[Zeitlimit $timeout s überschritten]"; $code = 124; break; }
            usleep(200000);
        }
        fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
        return [$code, $out];
    }

    /** Systemprüfung: Cronjob läuft? Fehlgeschlagene Aufgaben? (nur Hinweise, nie Fehler) */
    public static function health(): array
    {
        try {
            $hb = self::heartbeat();
            if ($hb === null && environment() !== 'production') return [];
            $out = [self::running() ? __('Geplante Aufgaben: Cronjob läuft') : __('Geplante Aufgaben: Cronjob läuft nicht – System → Geplante Aufgaben') => self::running() ? true : null];
            foreach (self::tasks() as $t) {
                if ($t['enabled'] && in_array($t['last_status'], ['error', 'timeout'], true)) $out[__('Geplante Aufgabe fehlgeschlagen: {name}', ['name' => $t['label']])] = null;
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** Wer darf die geplanten Aufgaben verwalten? (installationsweit: Netzwerk-Website bzw. Einzel-Installation) */
    public static function canManage(): bool
    {
        return Permissions::installationAdmin();
    }

    /** Zeile für den einzigen Cronjob */
    public static function line(): string
    {
        return '* * * * *  cd ' . ROOT . ' && ' . Network\Stats::phpBinary() . ' bin/console cron:run > /dev/null 2>&1';
    }
}
