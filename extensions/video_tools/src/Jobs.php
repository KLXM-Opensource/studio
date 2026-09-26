<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

use Core\Media;

/**
 * Hintergrund-Aufträge (Tabelle video_jobs der Website).
 *
 *  - Anlegen in der Verwaltung → Arbeiter startet losgelöst (`php bin/console video:work --site=…`, nohup, Log storage/logs/video-jobs.log).
 *  - Rückfall ohne Start: nach der Antwort eines Verwaltungsaufrufs (Extension::afterAdminResponse) bzw. Cron `video:work --site={key} --all`.
 *  - Gleichzeitigkeit: installationsweite „Plätze“ storage/video/slot-{n}.lock (config video_tools.max_jobs, Standard 1).
 *  - Sperre je Auftrag: storage/video/locks/{site}-{id}.lock – hält der Arbeiter während des Laufs. Ist sie frei, obwohl der
 *    Auftrag „läuft“, ist der Prozess weg → Auftrag gilt als abgebrochen (keine PID-Raterei).
 *  - Grenzen: Zeitlimit, max. Eingangsgröße, freier Speicher, `nice`, Abbruch (cancel=1, geprüft jede Sekunde).
 */
final class Jobs
{
    public const TYPES = ['optimize', 'trim', 'poster', 'preview'];
    public const OPEN = ['queued', 'running'];

    private static function db(): \Core\Database
    {
        return app()->db;
    }

    public static function dir(string $sub = ''): string
    {
        $d = ROOT . '/storage/video' . ($sub !== '' ? '/' . $sub : '');
        if (!is_dir($d)) @mkdir($d, 0770, true);
        return $d;
    }

    // ================================================================== Anlegen

    /**
     * Auftrag anlegen. $params je Typ: optimize {delete_original}, trim {from, to, precise}, poster {at}, preview {at}.
     * @throws JobException
     */
    public static function create(string $type, array $m, array $params = [], ?string $preset = null, string $mode = 'new', ?string $batch = null, bool $spawn = true): array
    {
        if (!in_array($type, self::TYPES, true)) throw new JobException(__('Unbekannter Auftrag.'));
        if (!empty($m['_pool']) || !empty($m['pool_ref'])) throw new JobException(__('Geteilte Medien lassen sich hier nicht bearbeiten.'));
        if (!str_starts_with((string) $m['mime'], 'video/')) throw new JobException(__('Nur Videos lassen sich bearbeiten.'));
        if (!Ffmpeg::available()) throw new JobException(__('ffmpeg ist auf diesem Server nicht verfügbar.'));
        $mode = $mode === 'replace' ? 'replace' : 'new';
        if ($type === 'optimize') {
            if (!Presets::exists((string) $preset)) throw new JobException(__('Unbekanntes Preset.'));
            if (!(Presets::all()[$preset]['available'] ?? false)) throw new JobException(__('Dieses Preset ist mit dem ffmpeg dieses Servers nicht möglich.'));
            if ($preset === 'webm' && $mode === 'replace') throw new JobException(__('WebM nur als zusätzliche Version – die Kits erwarten MP4.'));
            $params = ['delete_original' => !empty($params['delete_original']) && $mode === 'new'];
        } else {
            $mode = 'new';
            $preset = null;
        }
        $info = Repo::meta($m)['info'];
        $dur = (int) round(((float) ($info['duration'] ?? 0)) * 1000);
        if ($type === 'trim') {
            $from = max(0, (int) ($params['from'] ?? 0));
            $to = (int) ($params['to'] ?? 0);
            if ($dur > 0) $to = min($to, $dur);
            if ($to - $from < 300) throw new JobException(__('Der Ausschnitt ist zu kurz (mindestens 0,3 Sekunden).'));
            $params = ['from' => $from, 'to' => $to, 'precise' => !empty($params['precise'])];
        }
        if ($type === 'poster' || $type === 'preview') {
            $at = max(0, (int) ($params['at'] ?? 0));
            if ($dur > 0) $at = min($at, max(0, $dur - 100));
            $params = ['at' => $at];
        }
        // Gleicher offener Auftrag (gleiche Datei, gleiches Preset/Ausschnitt) → nicht doppelt
        $key = json_encode($params);
        foreach (self::db()->fetchAll("SELECT id, preset, params FROM video_jobs WHERE media_id = ? AND type = ? AND status IN ('queued', 'running')", [(int) $m['id'], $type]) as $o) {
            if ((string) $o['preset'] === (string) $preset && ($type === 'optimize' || $type === 'preview' || $o['params'] === $key)) {
                throw new JobException(__('Für diese Datei läuft bereits der gleiche Auftrag.'));
            }
        }
        $id = (int) self::db()->insert('video_jobs', [
            'type' => $type, 'media_id' => (int) $m['id'], 'preset' => $preset, 'params' => $key, 'mode' => $mode, 'status' => 'queued', 'progress' => 0,
            'bytes_in' => (int) $m['size'], 'user_id' => (int) (app()->auth->user()['id'] ?? 0) ?: null, 'batch' => $batch, 'created_at' => now(),
        ]);
        if ($spawn) self::spawn();
        return self::get($id);
    }

    /** Losgelösten Arbeiter starten (überlebt das Ende der Anfrage). Nur feste Werte des Servers in der Befehlszeile. */
    public static function spawn(): bool
    {
        if (!Ffmpeg::canExec()) return false;
        app()->settings->set('ext.video_tools.spawned', time());
        $php = \Core\Network\Stats::phpBinary();
        @mkdir(ROOT . '/storage/logs', 0770, true);
        $cmd = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg(ROOT . '/bin/console') . ' video:work --site=' . escapeshellarg(site()->key)
            . ' >> ' . escapeshellarg(ROOT . '/storage/logs/video-jobs.log') . ' 2>&1 &';
        // Pipes statt /dev/null (open_basedir, Plesk); der Hintergrund-Befehl schreibt ins Protokoll, die Shell endet sofort
        $p = @proc_open(['/bin/sh', '-c', $cmd], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT);
        if (!is_resource($p)) return false;
        foreach ($pipes as $pp) fclose($pp);
        proc_close($p);
        return true;
    }

    /**
     * Für Extension::afterAdminResponse: wartende Aufträge, aber kein Arbeiter? → nach der Antwort starten.
     * Billig: eine indizierte Abfrage; höchstens alle 20 s ein Startversuch.
     */
    public static function maybeRun(): ?callable
    {
        if (!Repo::ready() || !Ffmpeg::canExec()) return null;
        if (time() - (int) app()->settings->get('ext.video_tools.spawned', 0) < 20) return null;
        $q = (int) self::db()->fetchValue("SELECT COUNT(*) FROM video_jobs WHERE status = 'queued'");
        if (!$q) return null;
        foreach (self::db()->fetchAll("SELECT * FROM video_jobs WHERE status = 'running'") as $j) self::stale($j);
        return function (): void {
            if (!Ffmpeg::available()) return;
            if (!self::spawn()) {
                // Ohne losgelösten Start: einen Auftrag in diesem Prozess (nach der Antwort)
                @set_time_limit(Ffmpeg::config()['timeout'] + 120);
                self::work(null, null, 1);
            }
        };
    }

    // ================================================================== Lesen

    public static function get(int $id): ?array
    {
        $j = self::db()->fetch('SELECT * FROM video_jobs WHERE id = ?', [$id]);
        return $j ? self::json(self::stale($j)) : null;
    }

    public static function raw(int $id): ?array
    {
        return self::db()->fetch('SELECT * FROM video_jobs WHERE id = ?', [$id]) ?: null;
    }

    /** Aufträge (neueste zuerst): $f = media (ID), open (bool), ids (Liste) */
    public static function list(array $f = [], int $limit = 50): array
    {
        $w = [];
        $p = [];
        if (!empty($f['media'])) { $w[] = 'media_id = ?'; $p[] = (int) $f['media']; }
        if (!empty($f['open'])) $w[] = "status IN ('queued', 'running')";
        if (!empty($f['ids'])) {
            $ids = array_slice(array_values(array_filter(array_map('intval', (array) $f['ids']))), 0, 100);
            if (!$ids) return [];
            $w[] = 'id IN (' . implode(',', $ids) . ')';
        }
        $rows = self::db()->fetchAll('SELECT * FROM video_jobs' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)), $p);
        return array_map(fn($j) => self::json(self::stale($j)), $rows);
    }

    /** Offene Aufträge je Medium (für die Liste der Mediathek: Ring mit Prozent) */
    public static function openByMedia(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = [];
        if (!Repo::ready()) return $cache;
        $preview = Ffmpeg::config()['preview'];
        foreach (self::db()->fetchAll("SELECT id, media_id, type, status, progress, created_at FROM video_jobs WHERE status IN ('queued', 'running') ORDER BY id") as $j) {
            if ($j['type'] === 'preview' && !$preview) continue;   // animierte Vorschau aus: kein Ring im Raster
            $cache[(int) $j['media_id']] ??= ['id' => (int) $j['id'], 'type' => $j['type'], 'status' => $j['status'], 'progress' => (int) $j['progress'],
                'waiting' => self::waiting($j)];
        }
        return $cache;
    }

    /**
     * Wartet der Auftrag vergeblich auf einen Arbeiter? (seit über 2 Minuten eingereiht und kein Auftrag läuft) –
     * die Oberfläche zeigt dann „wartet auf Hintergrunddienst“ statt eines endlosen Kreisels.
     */
    public static function waiting(array $j): bool
    {
        if (($j['status'] ?? '') !== 'queued' || strtotime((string) ($j['created_at'] ?? '')) > time() - 120) return false;
        static $running = null;
        $running ??= (int) self::db()->fetchValue("SELECT COUNT(*) FROM video_jobs WHERE status = 'running'");
        return $running === 0;
    }

    private static function lockFile(int $id): string
    {
        return self::dir('locks') . '/' . preg_replace('~[^a-z0-9_-]~i', '', site()->key) . '-' . $id . '.lock';
    }

    /** Läuft laut Tabelle, aber niemand hält die Sperre → Prozess beendet (Absturz, Neustart, Zeitlimit des Servers) */
    private static function stale(array $j): array
    {
        if ($j['status'] !== 'running') return $j;
        $f = self::lockFile((int) $j['id']);
        $fh = @fopen($f, 'c');
        if (!$fh) return $j;
        $free = flock($fh, LOCK_EX | LOCK_NB);
        if ($free) flock($fh, LOCK_UN);
        fclose($fh);
        if ($free) {
            $msg = __('Abgebrochen (Prozess beendet).');
            self::db()->query("UPDATE video_jobs SET status = 'failed', message = ?, finished_at = ? WHERE id = ? AND status = 'running'", [$msg, now(), (int) $j['id']]);
            $j['status'] = 'failed';
            $j['message'] = $msg;
        }
        return $j;
    }

    public static function json(array $j): array
    {
        $m = Media::find((int) $j['media_id']);
        $r = $j['result_id'] ? Media::find((int) $j['result_id']) : null;
        $p = json_decode((string) $j['params'], true) ?: [];
        $pos = null;
        if ($j['status'] === 'queued') $pos = (int) self::db()->fetchValue("SELECT COUNT(*) FROM video_jobs WHERE status IN ('queued', 'running') AND id < ?", [(int) $j['id']]) + 1;
        return [
            'id' => (int) $j['id'], 'type' => $j['type'], 'preset' => $j['preset'], 'mode' => $j['mode'], 'params' => $p,
            'label' => self::label($j), 'media_id' => (int) $j['media_id'], 'media' => $m ? Media::displayName($m) : __('(gelöscht)'),
            'status' => $j['status'], 'progress' => (int) $j['progress'], 'position' => $pos, 'message' => (string) $j['message'],
            'waiting' => self::waiting($j),
            'result_id' => $r ? (int) $r['id'] : null, 'result' => $r ? Media::displayName($r) : null,
            'bytes_in' => $j['bytes_in'] !== null ? (int) $j['bytes_in'] : null, 'bytes_out' => $j['bytes_out'] !== null ? (int) $j['bytes_out'] : null,
            'has_log' => trim((string) $j['log']) !== '', 'attempts' => (int) $j['attempts'], 'batch' => $j['batch'],
            'created_at' => $j['created_at'], 'started_at' => $j['started_at'], 'finished_at' => $j['finished_at'],
        ];
    }

    public static function label(array $j): string
    {
        $p = json_decode((string) $j['params'], true) ?: [];
        return match ($j['type']) {
            'optimize' => Presets::label((string) $j['preset']) . ($j['mode'] === 'replace' ? ' · ' . __('ersetzt Original') : ''),
            'trim' => __('Ausschnitt {from}–{to}', ['from' => self::clock((int) ($p['from'] ?? 0)), 'to' => self::clock((int) ($p['to'] ?? 0))])
                . (!empty($p['precise']) ? ' · ' . __('präzise') : ''),
            'poster' => __('Poster bei {at}', ['at' => self::clock((int) ($p['at'] ?? 0))]),
            'preview' => __('Animierte Vorschau'),
            default => (string) $j['type'],
        };
    }

    /** ms → „00:12“ bzw. „1:02:03“ */
    public static function clock(int $ms): string
    {
        $s = intdiv(max(0, $ms), 1000);
        return $s >= 3600 ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60) : sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }

    // ================================================================== Steuern

    public static function cancel(int $id): void
    {
        self::db()->query("UPDATE video_jobs SET status = 'canceled', message = ?, finished_at = ? WHERE id = ? AND status = 'queued'", [__('Abgebrochen.'), now(), $id]);
        self::db()->query("UPDATE video_jobs SET cancel = 1 WHERE id = ? AND status = 'running'", [$id]);
    }

    /** Fehlgeschlagenen/abgebrochenen Auftrag erneut einreihen */
    public static function retry(int $id): ?array
    {
        $j = self::raw($id);
        if (!$j || !in_array($j['status'], ['failed', 'canceled'], true)) return null;
        if (!Repo::video((int) $j['media_id'])) throw new JobException(__('Die Datei gibt es nicht mehr.'));
        self::db()->query("UPDATE video_jobs SET status = 'queued', progress = 0, cancel = 0, message = NULL, result_id = NULL, bytes_out = NULL,
            attempts = attempts + 1, started_at = NULL, finished_at = NULL WHERE id = ?", [$id]);
        self::spawn();
        return self::get($id);
    }

    /** Erledigte Aufträge nach 30 Tagen entfernen (Protokoll bleibt so lange lesbar) */
    public static function purge(int $days = 30): int
    {
        return self::db()->query("DELETE FROM video_jobs WHERE status IN ('done', 'failed', 'canceled') AND finished_at < ?", [date('Y-m-d H:i:s', time() - $days * 86400)])->rowCount();
    }

    // ================================================================== Arbeiter

    /**
     * Wartende Aufträge dieser Website abarbeiten. Belegt einen freien Platz (installationsweit, ohne zu warten).
     * @return int Anzahl bearbeiteter Aufträge
     */
    public static function work(?callable $log = null, ?int $only = null, ?int $max = null): int
    {
        $log ??= fn(string $s) => null;
        if (!Repo::ready()) return 0;
        $slot = self::slot();
        if (!$slot) { $log(__('Alle Plätze belegt (max_jobs) – ein anderer Arbeiter ist aktiv.')); return 0; }
        $done = 0;
        try {
            while ($max === null || $done < $max) {
                $j = self::db()->fetch("SELECT * FROM video_jobs WHERE status = 'queued'" . ($only ? ' AND id = ' . (int) $only : '') . ' ORDER BY id LIMIT 1');
                if (!$j) break;
                if ($j['type'] === 'preview' && !Ffmpeg::config()['preview']) {
                    // animierte Vorschau inzwischen ausgeschaltet: wartende Aufträge verwerfen
                    self::db()->query("UPDATE video_jobs SET status = 'canceled', message = ?, finished_at = ? WHERE id = ? AND status = 'queued'",
                        [__('Animierte Vorschau ist ausgeschaltet.'), now(), (int) $j['id']]);
                    continue;
                }
                $fh = fopen(self::lockFile((int) $j['id']), 'c');
                if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) { if ($fh) fclose($fh); break; }
                try {
                    $claimed = self::db()->query("UPDATE video_jobs SET status = 'running', started_at = ?, pid = ?, progress = 1 WHERE id = ? AND status = 'queued'",
                        [now(), getmypid(), (int) $j['id']])->rowCount();
                    if (!$claimed) continue;
                    $log(sprintf('[%s] %s #%d %s (Medium %d)', date('H:i:s'), site()->key, $j['id'], self::label($j), $j['media_id']));
                    self::runOne($j, $log);
                    $done++;
                } finally {
                    flock($fh, LOCK_UN);
                    fclose($fh);
                    @unlink(self::lockFile((int) $j['id']));
                }
            }
        } finally {
            flock($slot, LOCK_UN);
            fclose($slot);
        }
        return $done;
    }

    /** Freien Platz belegen (Datei-Sperre), sonst null */
    private static function slot()
    {
        $n = Ffmpeg::config()['max_jobs'];
        for ($i = 1; $i <= $n; $i++) {
            $fh = @fopen(self::dir() . '/slot-' . $i . '.lock', 'c');
            if ($fh && flock($fh, LOCK_EX | LOCK_NB)) return $fh;
            if ($fh) fclose($fh);
        }
        return null;
    }

    private static function runOne(array $j, callable $log): void
    {
        $id = (int) $j['id'];
        $tmp = [];
        $logText = '';
        try {
            Media::usePool(null);
            $m = Repo::video((int) $j['media_id']) ?? throw new JobException(__('Die Datei gibt es nicht mehr.'));
            $cfg = Ffmpeg::config();
            $in = Repo::path($m);
            $size = (int) @filesize($in);
            if (!is_file($in)) throw new JobException(__('Die Videodatei fehlt auf dem Server.'));
            if ($size > $cfg['max_input_mb'] * 1048576) throw new JobException(__('Die Datei ist größer als erlaubt ({mb} MB, video_tools.max_input_mb).', ['mb' => $cfg['max_input_mb']]));
            $free = @disk_free_space(self::dir('tmp'));
            if ($free !== false && $free < $size * 1.5 + $cfg['min_free_mb'] * 1048576) {
                throw new JobException(__('Zu wenig freier Speicher ({free} MB frei).', ['free' => (int) ($free / 1048576)]));
            }
            // Datei prüfen, bevor etwas passiert (ffprobe muss sie als Video lesen können)
            $info = Ffmpeg::probe($in);
            if (!$info || !Analyzer::video($info)) throw new JobException(__('Die Datei ist kein lesbares Video.'));
            $info['faststart'] = Repo::meta($m)['info']['faststart'] ?? null;
            $p = json_decode((string) $j['params'], true) ?: [];
            $base = self::dir('tmp') . '/' . preg_replace('~[^a-z0-9_-]~i', '', site()->key) . '-' . $id;
            [$args, $out, $duration] = match ($j['type']) {
                'optimize' => (function () use ($j, $in, $base, $info) {
                    [$ext] = Presets::output((string) $j['preset']);
                    $out = "$base.$ext";
                    return [Presets::optimize((string) $j['preset'], $in, $out, $info), $out, (float) $info['duration']];
                })(),
                'trim' => [Presets::trim($in, "$base.mp4", (int) $p['from'], (int) $p['to'], !empty($p['precise']), $info), "$base.mp4", ((int) $p['to'] - (int) $p['from']) / 1000],
                'poster' => [Presets::poster($in, "$base.jpg", (int) $p['at']), "$base.jpg", 0.0],
                'preview' => [Presets::preview($in, "$base.mp4", (int) $p['at']), "$base.mp4", 3.0],
            };
            $tmp[] = $out;
            $logText = '$ ' . Runner::display($args, [$in => '<eingabe>', $out => '<ausgabe>']) . "\n";
            $res = Runner::run($args, $duration, fn(int $pct) => self::db()->query('UPDATE video_jobs SET progress = ? WHERE id = ?', [$pct, $id]),
                fn() => (int) self::db()->fetchValue('SELECT cancel FROM video_jobs WHERE id = ?', [$id]) === 1, $cfg['timeout']);
            $logText .= ($res['stderr'] !== '' ? $res['stderr'] . "\n" : '') . sprintf("exit %d · %.1f s\n", $res['code'], $res['seconds']);
            if ($res['canceled']) throw new JobException(__('Abgebrochen.'));
            if ($res['timeout']) throw new JobException(__('Zeitlimit überschritten ({s} s, video_tools.timeout).', ['s' => $cfg['timeout']]));
            if (!$res['ok'] || !is_file($out) || filesize($out) < 100) throw new JobException(__('ffmpeg ist mit Fehler {code} beendet – Details im Protokoll.', ['code' => $res['code']]));
            self::db()->query('UPDATE video_jobs SET progress = 99 WHERE id = ?', [$id]);
            [$resultId, $msg, $bytesOut] = Output::store($j, $m, $out, $info, $p);
            self::db()->update('video_jobs', ['status' => 'done', 'progress' => 100, 'message' => $msg, 'result_id' => $resultId, 'bytes_out' => $bytesOut,
                'log' => mb_substr($logText, -60000), 'finished_at' => now()], 'id = :id', ['id' => $id]);
            $log('  ✓ ' . $msg);
        } catch (\Throwable $e) {
            $canceled = (int) self::db()->fetchValue('SELECT cancel FROM video_jobs WHERE id = ?', [$id]) === 1;
            $msg = $e instanceof JobException || $e instanceof \InvalidArgumentException ? $e->getMessage() : __('Interner Fehler – Details im Protokoll.');
            if (!($e instanceof JobException)) {
                error_log('[video_tools] Auftrag ' . $id . ': ' . $e);
                $logText .= get_class($e) . ': ' . $e->getMessage() . "\n";
            }
            self::db()->update('video_jobs', ['status' => $canceled || $msg === __('Abgebrochen.') ? 'canceled' : 'failed', 'message' => mb_substr($msg, 0, 500),
                'log' => mb_substr($logText, -60000), 'finished_at' => now()], 'id = :id', ['id' => $id]);
            $log('  ✗ ' . $msg);
        } finally {
            foreach ($tmp as $f) if (is_file($f)) @unlink($f);
            Media::usePool(null);
        }
    }

    /** Verwaiste Dateien in storage/video/tmp (älter als 1 Tag) entfernen */
    public static function cleanTmp(): int
    {
        $n = 0;
        foreach (glob(self::dir('tmp') . '/*') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < time() - 86400 && @unlink($f)) $n++;
        }
        foreach (glob(self::dir('locks') . '/*.lock') ?: [] as $f) {
            if (filemtime($f) < time() - 7 * 86400) @unlink($f);
        }
        return $n;
    }
}
