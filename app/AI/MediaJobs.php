<?php
declare(strict_types=1);

namespace Core\AI;

use Core\Lang;
use Core\Media;
use Core\MediaTracks;

/**
 * Hintergrund-Aufträge für Medien (Tabelle media_jobs der Website): KI-Transkription (whisper.cpp / OpenAI-kompatibel) und
 * Übersetzen von Untertiteln (Text-KI, Cue für Cue mit gleichen Zeiten). Ergebnis ist immer ein ENTWURF („KI – bitte prüfen“),
 * der erst nach Prüfung im Untertitel-Editor auf der Website erscheint.
 *
 * Ablauf: Verwaltung legt den Auftrag an (queued) und startet `php bin/console ai:jobs --site=…` losgelöst (nohup … &).
 * Der Arbeiter wartet auf die Sperre storage/ai/jobs.lock – installationsweit läuft immer nur EIN Auftrag (Whisper braucht
 * die ganze CPU) – und arbeitet dann alle wartenden Aufträge seiner Website ab. Cron als Rückfall: `ai:jobs --all`.
 * Gezählt werden nur Sekunden Audio, Aufrufe und Dauer (Core\AI\Ai::usage) – keine Inhalte.
 */
final class MediaJobs
{
    public const TYPES = ['transcribe' => 'Transkription', 'translate' => 'Übersetzung'];
    /** Untertitel je Übersetzungs-Anfrage */
    private const BATCH = 25;

    private static function db(): \Core\Database
    {
        return app()->db;
    }

    /** Darf die angemeldete Person transkribieren (Recht „ai.use“, KI-Fähigkeit an)? */
    public static function canTranscribe(): bool
    {
        return MediaTracks::enabled() && Assist::available('transcribe');
    }

    public static function canTranslate(): bool
    {
        return MediaTracks::enabled() && Assist::available('text');
    }

    /** Auftrag anlegen und Arbeiter starten. $m = Medium im richtigen Kontext (Website/Pool) */
    public static function create(string $type, array $m, array $o = []): array
    {
        if (!isset(self::TYPES[$type])) throw new AiException(__('Unbekannter Auftrag.'));
        if (!MediaTracks::supports($m)) throw new AiException(__('Nur Videos und Audio haben eine Tonspur.'));
        $pool = !empty($m['_pool']) ? (string) $m['_pool'] : null;
        $mid = (int) ($pool !== null ? ($m['_pool_id'] ?? $m['id']) : $m['id']);
        $open = self::db()->fetchValue("SELECT id FROM media_jobs WHERE type = ? AND media_id = ? AND COALESCE(pool, '') = ? AND status IN ('queued', 'running') AND COALESCE(target, '') = ?",
            [$type, $mid, (string) $pool, (string) ($o['target'] ?? '')]);
        if ($open) throw new AiException(__('Für diese Datei läuft bereits ein Auftrag.'));
        if ($type === 'translate') {
            $q = Assist::quota();
            if ($q['left'] === 0) throw new AiException(__('Das Tageslimit für KI-Aufrufe dieser Website ist erreicht ({n}). Morgen geht es weiter – oder die Administration erhöht das Limit unter Grundeinstellungen → KI.', ['n' => $q['cap']]));
        }
        $id = (int) self::db()->insert('media_jobs', [
            'type' => $type, 'media_id' => $mid, 'pool' => $pool, 'lang' => (string) ($o['lang'] ?? ''), 'target' => $o['target'] ?? null,
            'track_id' => isset($o['track']) ? (int) $o['track'] : null, 'status' => 'queued', 'progress' => 0,
            'user_id' => (int) (app()->auth->user()['id'] ?? 0) ?: null, 'created_at' => now(),
        ]);
        if (empty($o['no_spawn'])) self::spawn();
        return self::get($id);
    }

    /** Arbeiter losgelöst starten (überlebt das Ende der Anfrage; Ausgabe: storage/logs/ai-jobs.log) */
    public static function spawn(): bool
    {
        if (!function_exists('proc_open')) return false;
        $php = \Core\Network\Stats::phpBinary();
        $log = ROOT . '/storage/logs/ai-jobs.log';
        $cmd = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg(ROOT . '/bin/console') . ' ai:jobs --site=' . escapeshellarg(site()->key)
            . ' >> ' . escapeshellarg($log) . ' 2>&1 &';
        // Pipes statt /dev/null (open_basedir, Plesk); der Hintergrund-Befehl schreibt ins Protokoll, die Shell endet sofort
        $p = @proc_open(['/bin/sh', '-c', $cmd], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT);
        if (!is_resource($p)) return false;
        foreach ($pipes as $pp) fclose($pp);
        proc_close($p);
        return true;
    }

    public static function get(int $id): ?array
    {
        $j = self::db()->fetch('SELECT * FROM media_jobs WHERE id = ?', [$id]);
        return $j ? self::json(self::stale($j)) : null;
    }

    /** Aufträge (neueste zuerst), optional für eine Datei */
    public static function list(?array $m = null, int $limit = 30): array
    {
        $sql = 'SELECT * FROM media_jobs';
        $p = [];
        if ($m) {
            $pool = !empty($m['_pool']) ? (string) $m['_pool'] : '';
            $sql .= " WHERE media_id = ? AND COALESCE(pool, '') = ?";
            $p = [(int) ($pool !== '' ? ($m['_pool_id'] ?? $m['id']) : $m['id']), $pool];
        }
        $rows = self::db()->fetchAll($sql . ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)), $p);
        return array_map(fn($j) => self::json(self::stale($j)), $rows);
    }

    /** Hängengebliebene Aufträge (Prozess weg) als fehlgeschlagen markieren */
    private static function stale(array $j): array
    {
        if ($j['status'] !== 'running') return $j;
        $alive = $j['pid'] && function_exists('posix_kill') ? @posix_kill((int) $j['pid'], 0) : true;
        $timeout = (int) (Ai::config()['transcribe']['timeout'] ?? 3600) + 600;
        if (!$alive || strtotime((string) $j['started_at']) < time() - $timeout) {
            self::db()->update('media_jobs', ['status' => 'failed', 'message' => __('Abgebrochen (Prozess beendet).'), 'finished_at' => now()], 'id = :id', ['id' => (int) $j['id']]);
            $j['status'] = 'failed';
            $j['message'] = __('Abgebrochen (Prozess beendet).');
        }
        return $j;
    }

    private static function json(array $j): array
    {
        $pos = null;
        if ($j['status'] === 'queued') {
            $pos = (int) self::db()->fetchValue("SELECT COUNT(*) FROM media_jobs WHERE status IN ('queued', 'running') AND id < ?", [(int) $j['id']]) + 1;
        }
        return ['id' => (int) $j['id'], 'type' => $j['type'], 'media_id' => (int) $j['media_id'], 'pool' => $j['pool'], 'lang' => $j['lang'], 'target' => $j['target'],
            'status' => $j['status'], 'progress' => (int) $j['progress'], 'message' => (string) $j['message'], 'result_track' => $j['result_track'] ? (int) $j['result_track'] : null,
            'seconds' => $j['seconds'] !== null ? (int) $j['seconds'] : null, 'position' => $pos, 'created_at' => $j['created_at'], 'finished_at' => $j['finished_at']];
    }

    public static function cancel(int $id): void
    {
        self::db()->query("UPDATE media_jobs SET status = 'canceled', finished_at = ? WHERE id = ? AND status = 'queued'", [now(), $id]);
        self::db()->query("UPDATE media_jobs SET cancel = 1 WHERE id = ? AND status = 'running'", [$id]);
    }

    // ================================================================== Arbeiter (Kommandozeile)

    /**
     * Wartende Aufträge dieser Website abarbeiten. Hält die installationsweite Sperre (nur ein Auftrag gleichzeitig).
     * @return int Anzahl erledigter Aufträge
     */
    public static function work(?callable $log = null, ?int $only = null): int
    {
        $log ??= fn(string $m) => null;
        $dir = ROOT . '/storage/ai';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $fh = fopen($dir . '/jobs.lock', 'c');
        if (!$fh) throw new \RuntimeException('Sperre storage/ai/jobs.lock nicht möglich.');
        flock($fh, LOCK_EX);   // wartet, bis der laufende Auftrag (auch anderer Websites) fertig ist
        $done = 0;
        try {
            while (true) {
                $j = self::db()->fetch("SELECT * FROM media_jobs WHERE status = 'queued'" . ($only ? ' AND id = ' . (int) $only : '') . ' ORDER BY id LIMIT 1');
                if (!$j) break;
                $claimed = self::db()->query("UPDATE media_jobs SET status = 'running', started_at = ?, pid = ?, progress = 1 WHERE id = ? AND status = 'queued'",
                    [now(), getmypid(), (int) $j['id']])->rowCount();
                if (!$claimed) continue;
                $log(sprintf('[%s] Auftrag #%d %s (Medium %s%d)', date('H:i:s'), $j['id'], $j['type'], $j['pool'] ? $j['pool'] . ':' : '', $j['media_id']));
                try {
                    $msg = $j['type'] === 'transcribe' ? self::runTranscribe($j) : self::runTranslate($j);
                    self::db()->update('media_jobs', ['status' => 'done', 'progress' => 100, 'message' => $msg, 'finished_at' => now()], 'id = :id', ['id' => (int) $j['id']]);
                    $log('  ✓ ' . $msg);
                } catch (\Throwable $e) {
                    $canceled = (int) self::db()->fetchValue('SELECT cancel FROM media_jobs WHERE id = ?', [(int) $j['id']]) === 1;
                    $msg = $e instanceof AiException || $e instanceof \InvalidArgumentException ? $e->getMessage() : Ai::shortError($e);
                    self::db()->update('media_jobs', ['status' => $canceled ? 'canceled' : 'failed', 'message' => mb_substr($msg, 0, 500), 'finished_at' => now()], 'id = :id', ['id' => (int) $j['id']]);
                    $log('  ✗ ' . $msg);
                    if (!($e instanceof AiException) && !($e instanceof \InvalidArgumentException)) error_log('[ai-jobs] ' . $e);
                }
                $done++;
                Media::usePool(null);
            }
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
        return $done;
    }

    /** Medium eines Auftrags im richtigen Kontext */
    private static function media(array $j): array
    {
        Media::usePool($j['pool'] ?: null);
        return Media::find((int) $j['media_id']) ?? throw new AiException(__('Mediendatei nicht gefunden.'));
    }

    private static function progress(int $id, int $p): void
    {
        self::db()->query('UPDATE media_jobs SET progress = ? WHERE id = ?', [max(1, min(99, $p)), $id]);
    }

    private static function runTranscribe(array $j): string
    {
        if (!Ai::enabled('transcribe')) throw new AiException(__('Die Transkription ist für diese Website nicht eingeschaltet (Grundeinstellungen → KI).'));
        $m = self::media($j);
        $file = Media::path($m);
        $lang = (string) ($j['lang'] ?: 'auto');
        $id = (int) $j['id'];
        $res = Transcriber::transcribe(Ai::config(), $file, $lang, fn(int $p) => self::progress($id, $p),
            fn() => (int) self::db()->fetchValue('SELECT cancel FROM media_jobs WHERE id = ?', [$id]) === 1, site_name());
        self::db()->update('media_jobs', ['seconds' => $res['seconds']], 'id = :id', ['id' => $id]);
        if (!$res['cues']) throw new AiException(__('Keine Sprache erkannt – die Tonspur ist stumm oder zu leise.'));
        // Sprache: gewählt, sonst von Whisper erkannt, sonst Standardsprache der Website
        $trackLang = $lang !== 'auto' ? $lang : (MediaTracks::validLang((string) ($res['language'] ?? '')) ? (string) $res['language'] : Lang::default());
        $t = MediaTracks::save($m, ['kind' => 'subtitles', 'lang' => $trackLang, 'label' => '', 'cues' => $res['cues'], 'status' => 'draft',
            'source' => 'ai', 'note' => trim(__('KI-Transkript – bitte prüfen') . ($res['note'] !== '' ? ' · ' . $res['note'] : ''))], null, $j['user_id'] ? (int) $j['user_id'] : null);
        // Transkript-Entwurf aus den Untertiteln (wird mit der Prüfung veröffentlicht)
        $existing = MediaTracks::transcripts($m)[$trackLang] ?? null;
        if (!$existing || ($existing['status'] ?? '') === 'draft') {
            MediaTracks::setTranscript($m, $trackLang, MediaTracks::transcriptFromCues(MediaTracks::cues($t)), 'draft', 'ai');
        }
        self::db()->update('media_jobs', ['result_track' => (int) $t['id']], 'id = :id', ['id' => $id]);
        return __('{n} Untertitel aus {s} erkannt ({model}, {sec} s Rechenzeit) – Entwurf bitte prüfen.', [
            'n' => count($res['cues']), 's' => self::duration($res['seconds']), 'model' => $res['model'], 'sec' => number_format($res['ms'] / 1000, 1, ',', '.')]);
    }

    /** Untertitel Cue für Cue übersetzen – Zeiten bleiben gleich, Ergebnis = Entwurf in der Zielsprache */
    private static function runTranslate(array $j): string
    {
        if (!Ai::enabled('text')) throw new AiException(__('KI ist für diese Website nicht eingeschaltet (Grundeinstellungen → KI).'));
        $m = self::media($j);
        $src = MediaTracks::find($m, (int) $j['track_id']) ?? throw new AiException(__('Untertitel nicht gefunden.'));
        $to = (string) $j['target'];
        $from = (string) $src['lang'];
        if (!MediaTracks::validLang($to) || $to === $from) throw new AiException(__('Quell- und Zielsprache sind gleich.'));
        $cues = MediaTracks::cues($src);
        $ctx = Assist::ctx();
        $total = count($cues);
        $out = [];
        foreach (array_chunk($cues, self::BATCH, true) as $chunk) {
            if ((int) self::db()->fetchValue('SELECT cancel FROM media_jobs WHERE id = ?', [(int) $j['id']]) === 1) throw new AiException(__('Abgebrochen.'));
            $q = Assist::quota();
            if ($q['left'] === 0) throw new AiException(__('Das Tageslimit für KI-Aufrufe dieser Website ist erreicht ({n}). Morgen geht es weiter – oder die Administration erhöht das Limit unter Grundeinstellungen → KI.', ['n' => $q['cap']]));
            $in = [];
            foreach ($chunk as $i => $c) $in[(string) ($i + 1)] = MediaTracks::plain($c['text']);
            $pack = Prompts::translateBatch($in, $from, $to, $ctx);
            $pack['system'] .= "\nEs sind Untertitel eines Videos: kurz und gut lesbar halten, Zeilenumbrüche (\\n) dürfen bleiben.";
            $got = [];
            try {
                $got = Assist::json(Ai::chat($pack['user'], ['system' => $pack['system'], 'json' => true, 'max_tokens' => 4000, 'temperature' => 0.1,
                    'fake' => json_encode(array_map(fn($t) => '[' . strtoupper($to) . '] ' . $t, $in), JSON_UNESCAPED_UNICODE)])['text']);
            } catch (AiException $e) {
                if (count($chunk) === 1) throw $e;
            }
            foreach ($chunk as $i => $c) {
                $t = $got[(string) ($i + 1)] ?? null;
                if (!is_string($t) || trim($t) === '') {
                    $one = Prompts::translateOne($in[(string) ($i + 1)], $from, $to, false, $ctx);
                    $t = Ai::chat($one['user'], ['system' => $one['system'], 'max_tokens' => 400, 'temperature' => 0.1, 'fake' => '[' . strtoupper($to) . '] ' . $in[(string) ($i + 1)]])['text'];
                }
                $out[] = ['start' => $c['start'], 'end' => $c['end'], 'settings' => $c['settings'], 'text' => trim(strip_tags((string) $t))];
            }
            self::progress((int) $j['id'], (int) round(count($out) / max(1, $total) * 95));
        }
        $t = MediaTracks::save($m, ['kind' => $src['kind'], 'lang' => $to, 'label' => '', 'cues' => $out, 'status' => 'draft', 'source' => 'translate',
            'note' => __('KI-Übersetzung aus {lang} – bitte prüfen', ['lang' => MediaTracks::langLabel($from)])], null, $j['user_id'] ? (int) $j['user_id'] : null);
        self::db()->update('media_jobs', ['result_track' => (int) $t['id']], 'id = :id', ['id' => (int) $j['id']]);
        $existing = MediaTracks::transcripts($m)[$to] ?? null;
        if (in_array($src['kind'], ['subtitles', 'captions'], true) && (!$existing || ($existing['status'] ?? '') === 'draft')) {
            MediaTracks::setTranscript($m, $to, MediaTracks::transcriptFromCues(MediaTracks::cues($t)), 'draft', 'translate');
        }
        return __('{n} Untertitel nach {lang} übersetzt – Entwurf bitte prüfen.', ['n' => count($out), 'lang' => MediaTracks::langLabel($to)]);
    }

    public static function duration(int $s): string
    {
        return $s >= 3600 ? sprintf('%d:%02d:%02d h', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60) : sprintf('%d:%02d min', intdiv($s, 60), $s % 60);
    }

    // ================================================================== Kommandozeile

    /**
     *   ai:jobs [--all]                               wartende Aufträge abarbeiten (startet die Verwaltung selbst; Cron als Rückfall)
     *   ai:transcribe <medien-id> [--lang=de|auto] [--pool=key]   Datei sofort transkribieren (Entwurf)
     */
    public static function console(string $cmd, array $args): int
    {
        $opt = function (string $name, string $def = '') use ($args): string {
            foreach ($args as $a) if (str_starts_with($a, "--$name=")) return substr($a, strlen($name) + 3);
            return $def;
        };
        $log = fn(string $m) => print($m . "\n");
        if ($cmd === 'ai:jobs') {
            $n = self::work($log);
            echo "Aufträge erledigt (" . site()->key . "): $n\n";
            return 0;
        }
        $id = (int) (array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')))[0] ?? 0);
        $pool = $opt('pool');
        Media::usePool($pool !== '' ? $pool : null);
        $m = $id ? Media::find($id) : null;
        if (!$m) { fwrite(STDERR, "Aufruf: ai:transcribe <medien-id> [--lang=de|auto] [--pool=key]\n"); return 1; }
        try {
            $j = self::create('transcribe', $m, ['lang' => $opt('lang', (string) (Ai::config()['transcribe']['language'] ?: 'auto')), 'no_spawn' => true]);
        } catch (AiException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }
        Media::usePool(null);
        self::work($log, $j['id']);
        $j = self::get($j['id']);
        echo ($j['status'] === 'done' ? '✓ ' : '✗ ') . $j['message'] . "\n";
        return $j['status'] === 'done' ? 0 : 1;
    }
}
