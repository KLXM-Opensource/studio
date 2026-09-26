<?php
declare(strict_types=1);

namespace Core\AI;

use Core\MediaTracks;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

/**
 * KI-Fähigkeit „transcribe“ (Sprache → Text mit Zeitmarken) für Untertitel und Transkripte (Core\MediaTracks).
 * Ollama kennt kein Speech-to-Text – daher zwei eigene Wege:
 *
 *   whisper_cpp   lokal: ffmpeg zieht die Tonspur als 16-kHz-Mono-WAV heraus, whisper-cli (whisper.cpp) schreibt WebVTT.
 *                 Nichts verlässt den Server. Modelle (ggml-*.bin) unter storage/ai/models/ (nicht im Webroot).
 *   openai        OpenAI-kompatibles /v1/audio/transcriptions (OpenAI whisper-1, Mistral Voxtral, eigener Server wie
 *                 faster-whisper-server/speaches): Tonspur als MP3 (16 kHz mono) – extern, Hinweis in der Datenschutzerklärung.
 *
 * Konfiguration (Grundeinstellungen → KI, nur Agentur; storage/ai/config.json bzw. config.local.php) unter 'ai' => ['transcribe' => [
 *   'backend' => 'whisper_cpp' | 'openai' | '',
 *   'whisper_bin' => '/usr/local/bin/whisper-cli', 'model_path' => 'storage/ai/models/ggml-large-v3-turbo-q5_0.bin',
 *   'ffmpeg' => '/usr/bin/ffmpeg', 'threads' => 4, 'timeout' => 3600, 'language' => 'auto',
 *   'base_url' => 'https://api.openai.com', 'model' => 'whisper-1', 'api_key' => '…',
 * ]]
 * Leere Pfade werden automatisch gesucht (/opt/homebrew/bin, /usr/local/bin, /usr/bin, storage/ai/bin; Modelle in storage/ai/models).
 */
final class Transcriber
{
    public const BACKENDS = ['whisper_cpp' => 'whisper.cpp (lokal auf diesem Server)', 'openai' => 'OpenAI-kompatibel (/v1/audio/transcriptions)'];
    private const BIN_DIRS = ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin', '/bin'];
    /** Obergrenze für externe Anbieter (OpenAI: 25 MB je Datei) */
    private const UPLOAD_MAX = 25 * 1024 * 1024;

    /** Normalisierte Konfiguration aus dem Abschnitt 'transcribe' */
    public static function normalize(array $c): array
    {
        $backend = (string) ($c['backend'] ?? '');
        $lang = strtolower(trim((string) ($c['language'] ?? 'auto')));
        return [
            'backend' => isset(self::BACKENDS[$backend]) ? $backend : '',
            'whisper_bin' => trim((string) ($c['whisper_bin'] ?? '')),
            'model_path' => trim((string) ($c['model_path'] ?? '')),
            'ffmpeg' => trim((string) ($c['ffmpeg'] ?? '')),
            'threads' => max(1, min(64, (int) ($c['threads'] ?? 4))),
            'timeout' => max(60, min(21600, (int) ($c['timeout'] ?? 3600))),
            'language' => $lang === 'auto' || MediaTracks::validLang($lang) ? $lang : 'auto',
            'base_url' => rtrim(trim((string) ($c['base_url'] ?? '')), '/'),
            'model' => trim((string) ($c['model'] ?? '')),
            'api_key' => (string) ($c['api_key'] ?? ''),
        ];
    }

    /** Wirksame Pfade (leer = automatisch gesucht) */
    public static function paths(array $t): array
    {
        return [
            'whisper_bin' => $t['whisper_bin'] !== '' ? $t['whisper_bin'] : (self::which(['whisper-cli', 'whisper-cpp', 'whisper']) ?? ''),
            // ffmpeg: Erkennung per Aufruf (Core\Ffmpeg – auch mit open_basedir), Rückfall storage/ai/bin
            'ffmpeg' => $t['ffmpeg'] !== '' ? $t['ffmpeg'] : (\Core\Ffmpeg::bin('ffmpeg') ?: (self::which(['ffmpeg']) ?? '')),
            'model_path' => self::modelPath($t['model_path']),
        ];
    }

    private static function which(array $names): ?string
    {
        foreach ([ROOT . '/storage/ai/bin', ...self::BIN_DIRS] as $dir) {
            foreach ($names as $n) if (@is_file("$dir/$n") && @is_executable("$dir/$n")) return "$dir/$n";
        }
        return null;
    }

    /** Modell: absoluter Pfad oder relativ zur Installation; leer = bestes Modell in storage/ai/models */
    public static function modelPath(string $p): string
    {
        if ($p !== '') return str_starts_with($p, '/') ? $p : ROOT . '/' . ltrim($p, '/');
        $all = glob(ROOT . '/storage/ai/models/ggml-*.bin') ?: [];
        usort($all, fn($a, $b) => self::rank(basename($b)) <=> self::rank(basename($a)));
        return $all[0] ?? '';
    }

    private static function rank(string $f): int
    {
        return (str_contains($f, 'large-v3-turbo') ? 50 : 0) + (str_contains($f, 'large') ? 20 : 0) + (str_contains($f, 'medium') ? 10 : 0)
            + (str_contains($f, 'small') ? 5 : 0) - (str_contains($f, '.en') ? 40 : 0);
    }

    /** Angaben wie Ai::capability(): provider, model, endpoint, configured, external */
    public static function capability(array $t, bool $on): array
    {
        $p = self::paths($t);
        $out = ['cap' => 'transcribe', 'provider' => $t['backend'], 'base_url' => $t['base_url'], 'api_key' => $t['api_key'], 'region' => null];
        if ($t['backend'] === 'whisper_cpp') {
            $out['model'] = $p['model_path'] !== '' ? basename($p['model_path']) : '';
            $out['endpoint'] = 'local://whisper.cpp';
            $out['configured'] = $on && $p['whisper_bin'] !== '' && $p['model_path'] !== '' && $p['ffmpeg'] !== '';
            $out['external'] = false;
        } elseif ($t['backend'] === 'openai') {
            $out['model'] = $t['model'] !== '' ? $t['model'] : 'whisper-1';
            $out['endpoint'] = $t['base_url'] !== '' ? $t['base_url'] : 'https://api.openai.com';
            $out['configured'] = $on && $p['ffmpeg'] !== '';
            $out['external'] = !Ai::isLocalHost(strtolower((string) parse_url($out['endpoint'], PHP_URL_HOST)));
        } else {
            $out += ['model' => '', 'endpoint' => '', 'configured' => false, 'external' => false];
        }
        return $out;
    }

    /** Einrichtung prüfen, ohne etwas zu starten (für `health`): Programme ausführbar, Modell vorhanden */
    public static function check(array $t): array
    {
        $p = self::paths($t);
        $errors = [];
        if ($p['ffmpeg'] === '' || !\Core\Ffmpeg::works($p['ffmpeg'])) $errors[] = __('ffmpeg nicht gefunden.');
        if ($t['backend'] === 'whisper_cpp') {
            if ($p['whisper_bin'] === '' || !@is_executable($p['whisper_bin'])) $errors[] = __('whisper-cli nicht gefunden.');
            if ($p['model_path'] === '' || !@is_file($p['model_path'])) $errors[] = __('Whisper-Modell nicht gefunden (storage/ai/models/ggml-….bin).');
        }
        return $errors;
    }

    // ================================================================== Transkribieren

    /**
     * Tonspur einer Datei transkribieren. $lang: Sprachkürzel oder 'auto'.
     * $progress(int $prozent) wird gelegentlich aufgerufen; $cancelled() → true bricht ab.
     * @return array{cues: list<array>, seconds: int, model: string, provider: string, ms: int, note: string}
     * @throws AiException
     */
    public static function transcribe(array $cfg, string $file, string $lang, ?callable $progress = null, ?callable $cancelled = null, string $prompt = ''): array
    {
        $t = $cfg['transcribe'];
        $cap = self::capability($t, true);
        if (!$cap['configured']) throw new AiException(__('Für die Transkription ist nichts eingerichtet (Grundeinstellungen → KI → Sprache → Text).'));
        if (!is_file($file)) throw new AiException(__('Mediendatei nicht gefunden.'));
        $p = self::paths($t);
        $tmp = ROOT . '/storage/ai/tmp/' . bin2hex(random_bytes(6));
        @mkdir($tmp, 0770, true);
        $started = microtime(true);
        $seconds = 0;
        try {
            $wav = "$tmp/audio.wav";
            $r = self::exec([$p['ffmpeg'], '-nostdin', '-hide_banner', '-loglevel', 'error', '-y', '-i', $file, '-vn', '-ac', '1', '-ar', '16000', '-c:a', 'pcm_s16le', $wav],
                min(1800, $t['timeout']), null, $cancelled);
            if (str_contains($r['err'], 'does not contain any stream') || str_contains($r['err'], 'matches no streams')) {
                throw new AiException(__('Diese Datei hat keine Tonspur.'));
            }
            if ($r['code'] !== 0 || !is_file($wav) || filesize($wav) < 1000) {
                throw new AiException(__('Tonspur konnte nicht gelesen werden (ffmpeg): {msg}', ['msg' => mb_strimwidth(trim(str_replace([$tmp . '/', ROOT . '/'], '', $r['err'])) ?: 'exit ' . $r['code'], 0, 200, '…')]));
            }
            $seconds = (int) round((filesize($wav) - 44) / 32000);
            if ($progress) $progress(3);
            $lang = $lang !== '' ? $lang : $t['language'];
            $res = $t['backend'] === 'whisper_cpp'
                ? self::whisper($t, $p, $wav, "$tmp/out", $lang, $progress, $cancelled, $prompt)
                : self::openai($t, $p, $wav, $tmp, $lang, $seconds, $cancelled);
            $ms = (int) ((microtime(true) - $started) * 1000);
            Ai::track('transcribe', $cap, 0, 0, $ms, false, $seconds);
            return $res + ['seconds' => $seconds, 'model' => $cap['model'], 'provider' => $cap['provider'], 'ms' => $ms];
        } catch (AiException $e) {
            Ai::track('transcribe', $cap, 0, 0, (int) ((microtime(true) - $started) * 1000), true, $seconds);
            throw $e;
        } finally {
            foreach (glob("$tmp/*") ?: [] as $f) @unlink($f);
            @rmdir($tmp);
        }
    }

    private static function whisper(array $t, array $p, string $wav, string $out, string $lang, ?callable $progress, ?callable $cancelled, string $prompt): array
    {
        $cmd = [$p['whisper_bin'], '-m', $p['model_path'], '-f', $wav, '-l', $lang, '-t', (string) $t['threads'],
            '-ovtt', '-of', $out, '-np', '-pp', '-ml', '84', '-sow'];
        // Eigennamen (Name der Website) als Anfangs-Prompt – als Satz mit Punkt, sonst lässt Whisper die Satzzeichen weg
        if (($prompt = trim($prompt)) !== '') array_push($cmd, '--prompt', rtrim(mb_substr($prompt, 0, 200), '.') . '.');
        $last = 0;
        $detected = '';
        $r = self::exec($cmd, $t['timeout'], function (string $line) use ($progress, &$last, &$detected) {
            if (preg_match('~auto-detected language:\s*([a-z]{2,3})\b~', $line, $m)) $detected = $m[1];
            if ($progress && preg_match('~progress\s*=\s*(\d+)%~', $line, $m) && (int) $m[1] !== $last) {
                $last = (int) $m[1];
                $progress(5 + (int) round($last * 0.93));
            }
        }, $cancelled);
        if ($r['code'] !== 0 || !is_file("$out.vtt")) {
            $err = trim(implode("\n", array_slice(array_filter(explode("\n", $r['err']), fn($l) => !preg_match('~^(ggml_|load_backend|whisper_|system_info|main:|read_audio)~', $l)), -3)));
            throw new AiException(__('whisper.cpp ist fehlgeschlagen: {msg}', ['msg' => mb_strimwidth($err ?: 'exit ' . $r['code'], 0, 240, '…')]));
        }
        [$cues] = MediaTracks::parse((string) file_get_contents("$out.vtt"));
        return ['cues' => self::tidy($cues), 'note' => '', 'language' => $lang !== 'auto' ? $lang : $detected];
    }

    private static function openai(array $t, array $p, string $wav, string $tmp, string $lang, int $seconds, ?callable $cancelled): array
    {
        // Kleiner senden: MP3 16 kHz mono (≈ 4 KB/s); ohne MP3-Encoder die WAV-Datei
        $upload = "$tmp/audio.mp3";
        $r = self::exec([$p['ffmpeg'], '-nostdin', '-hide_banner', '-loglevel', 'error', '-y', '-i', $wav, '-c:a', 'libmp3lame', '-b:a', '32k', $upload], 900, null, $cancelled);
        if ($r['code'] !== 0 || !is_file($upload)) $upload = $wav;
        if (filesize($upload) > self::UPLOAD_MAX) {
            throw new AiException(__('Die Tonspur ist für den Anbieter zu groß (max. 25 MB, etwa 100 Minuten). Bitte kürzeres Medium oder whisper.cpp verwenden.'));
        }
        $data = self::post($t, $upload, $lang, 'verbose_json');
        $cues = [];
        foreach ((array) ($data['segments'] ?? []) as $s) {
            if (!is_array($s) || trim((string) ($s['text'] ?? '')) === '') continue;
            $cues[] = ['start' => (int) round((float) ($s['start'] ?? 0) * 1000), 'end' => (int) round((float) ($s['end'] ?? 0) * 1000), 'text' => trim((string) $s['text'])];
        }
        $note = '';
        if (!$cues && trim((string) ($data['text'] ?? '')) !== '') {
            // Anbieter ohne Zeitmarken: Sätze gleichmäßig über die Dauer verteilen – Zeiten unbedingt prüfen
            $parts = preg_split('~(?<=[.!?])\s+~u', trim((string) $data['text'])) ?: [];
            $len = max(1, array_sum(array_map('mb_strlen', $parts)));
            $pos = 0;
            foreach ($parts as $s) {
                $d = (int) round($seconds * 1000 * mb_strlen($s) / $len);
                $cues[] = ['start' => $pos, 'end' => $pos + max(800, $d), 'text' => $s];
                $pos += $d;
            }
            $note = __('Zeiten geschätzt (Anbieter ohne Zeitmarken)');
        }
        // Erkannte Sprache: Kürzel (Mistral, eigene Server) oder englischer Name (OpenAI: „german“)
        $dl = strtolower(trim((string) ($data['language'] ?? '')));
        $names = ['german' => 'de', 'english' => 'en', 'french' => 'fr', 'spanish' => 'es', 'italian' => 'it', 'dutch' => 'nl', 'polish' => 'pl',
            'turkish' => 'tr', 'arabic' => 'ar', 'russian' => 'ru', 'ukrainian' => 'uk', 'portuguese' => 'pt'];
        $dl = $names[$dl] ?? (preg_match('~^[a-z]{2,3}$~', $dl) ? $dl : '');
        return ['cues' => self::tidy(MediaTracks::cleanCues($cues)), 'note' => $note, 'language' => $lang !== 'auto' ? $lang : $dl];
    }

    /** Anfrage an /v1/audio/transcriptions (multipart) */
    private static function post(array $t, string $file, string $lang, string $format): array
    {
        $cap = self::capability($t, true);
        $url = rtrim($cap['endpoint'], '/');
        if (!preg_match('~^https?://~', $url)) throw new AiException(__('KI-Anbieter: Adresse (base_url) fehlt oder ist ungültig.'));
        if (!str_ends_with($url, '/v1')) $url .= '/v1';
        $fields = ['model' => $cap['model'], 'response_format' => $format, 'file' => DataPart::fromPath($file)];
        if ($lang !== '' && $lang !== 'auto') $fields['language'] = substr($lang, 0, 2);
        if ($format === 'verbose_json') $fields['timestamp_granularities[]'] = 'segment';
        $form = new FormDataPart($fields);
        $headers = $form->getPreparedHeaders()->toArray();
        if ($t['api_key'] !== '') $headers[] = 'Authorization: Bearer ' . $t['api_key'];
        try {
            $res = HttpClient::create(['timeout' => 60, 'max_duration' => $t['timeout'], 'max_redirects' => 0])
                ->request('POST', $url . '/audio/transcriptions', ['headers' => $headers, 'body' => $form->bodyToIterable()]);
            $code = $res->getStatusCode();
            $body = $res->getContent(false);
        } catch (\Throwable $e) {
            throw new AiException(Ai::shortError($e), 0, $e);
        }
        if ($code >= 400) {
            $msg = json_decode($body, true)['error']['message'] ?? json_decode($body, true)['message'] ?? mb_substr(strip_tags($body), 0, 200);
            throw new AiException(__('Anbieter meldet Fehler {code}: {msg}', ['code' => $code, 'msg' => is_string($msg) ? $msg : json_encode($msg)]));
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : ['text' => $body];
    }

    /** Whisper-Eigenheiten glätten: führende Leerzeichen, [Musik]-Platzhalter ohne Inhalt, doppelte Wiederholungen */
    private static function tidy(array $cues): array
    {
        $out = [];
        foreach ($cues as $c) {
            $c['text'] = trim((string) preg_replace('~^\s*[-–]\s*~u', '', $c['text']));
            if ($c['text'] === '' || preg_match('~^[\[(]?\s*(BLANK_AUDIO|Musik|Music|Stille|Silence)\s*[\])]?\.?$~iu', MediaTracks::plain($c['text']))) continue;
            $prev = end($out);
            if ($prev && $prev['text'] === $c['text'] && $c['start'] - $prev['end'] < 1500) { $out[array_key_last($out)]['end'] = $c['end']; continue; }
            $out[] = $c;
        }
        return $out;
    }

    // ================================================================== Test

    /** „Verbindung testen“ mit einem kurzen, erzeugten Testton (1,5 s Sinus, 16 kHz mono) */
    public static function test(array $cfg): array
    {
        $t = $cfg['transcribe'];
        $cap = self::capability($t, true);
        $out = ['ok' => false, 'cap' => 'transcribe', 'provider' => $cap['provider'], 'model' => $cap['model'], 'endpoint' => $cap['endpoint'],
            'external' => $cap['external'], 'dims' => null, 'text' => null, 'ms' => null, 'error' => null];
        if ($errors = ($t['backend'] !== '' ? self::check($t) : [__('Für die Transkription ist nichts eingerichtet (Grundeinstellungen → KI → Sprache → Text).')])) {
            $out['error'] = implode(' ', $errors);
            return $out;
        }
        $dir = ROOT . '/storage/ai/tmp/test-' . bin2hex(random_bytes(4));
        @mkdir($dir, 0770, true);
        $wav = "$dir/test.wav";
        file_put_contents($wav, self::testWav());
        $start = microtime(true);
        try {
            if ($t['backend'] === 'whisper_cpp') {
                $p = self::paths($t);
                $r = self::exec([$p['whisper_bin'], '-m', $p['model_path'], '-f', $wav, '-l', 'de', '-t', (string) $t['threads'], '-ovtt', '-of', "$dir/out", '-np'], 300);
                if ($r['code'] !== 0 || !is_file("$dir/out.vtt")) throw new AiException(__('whisper.cpp ist fehlgeschlagen: {msg}', ['msg' => mb_strimwidth(trim($r['err']) ?: 'exit ' . $r['code'], 0, 240, '…')]));
                $out['text'] = __('Modell geladen, Testton verarbeitet');
            } else {
                $d = self::post($t, $wav, 'de', 'json');
                $out['text'] = mb_strimwidth(trim((string) ($d['text'] ?? '')) ?: __('Testton verarbeitet'), 0, 80, '…');
            }
            $out['ok'] = true;
        } catch (\Throwable $e) {
            $out['error'] = Ai::shortError($e);
        } finally {
            foreach (glob("$dir/*") ?: [] as $f) @unlink($f);
            @rmdir($dir);
        }
        $out['ms'] = (int) round((microtime(true) - $start) * 1000);
        return $out;
    }

    /** WAV (PCM 16 bit, 16 kHz, mono) mit 1,5 s Sinuston 440 Hz – ohne mitgelieferte Audiodatei */
    public static function testWav(float $sec = 1.5): string
    {
        $rate = 16000;
        $n = (int) ($rate * $sec);
        $pcm = '';
        for ($i = 0; $i < $n; $i++) $pcm .= pack('v', (int) (sin(2 * M_PI * 440 * $i / $rate) * 6000) & 0xFFFF);
        return 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16) . 'data' . pack('V', strlen($pcm)) . $pcm;
    }

    // ================================================================== Prozesse

    /**
     * Programm ohne Shell starten (Argumente als Liste), stderr zeilenweise an $onErr, Zeitlimit und Abbruch.
     * @return array{code: int, out: string, err: string}
     */
    public static function exec(array $cmd, int $timeout, ?callable $onErr = null, ?callable $cancelled = null): array
    {
        if (!function_exists('proc_open')) throw new AiException(__('Auf diesem Server nicht möglich (proc_open gesperrt).'));
        // stdin als Pipe (sofort geschlossen) statt /dev/null – mit open_basedir (Plesk) wäre /dev/null gesperrt
        $p = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT, self::env());
        if (!is_resource($p)) throw new AiException(__('Programm konnte nicht gestartet werden: {cmd}', ['cmd' => basename((string) $cmd[0])]));
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = $err = $buf = '';
        $end = time() + $timeout;
        $checked = 0;
        while (true) {
            $r = [$pipes[1], $pipes[2]];
            $w = $x = null;
            if (@stream_select($r, $w, $x, 1) === false) break;
            foreach ($r as $s) {
                $chunk = (string) fread($s, 65536);
                if ($s === $pipes[1]) { $out .= mb_substr($chunk, 0, 1_000_000); continue; }
                $err .= $chunk;
                $buf .= $chunk;
                while (($nl = strcspn($buf, "\n\r")) < strlen($buf)) {
                    $line = substr($buf, 0, $nl);
                    $buf = ltrim(substr($buf, $nl + 1), "\n\r");
                    if ($onErr && $line !== '') $onErr($line);
                }
            }
            if (strlen($err) > 200_000) $err = substr($err, -100_000);
            $st = proc_get_status($p);
            if (!$st['running']) {
                $out .= (string) stream_get_contents($pipes[1]);
                $err .= (string) stream_get_contents($pipes[2]);
                foreach ($pipes as $pp) @fclose($pp);
                proc_close($p);
                return ['code' => (int) $st['exitcode'], 'out' => $out, 'err' => $err];
            }
            $stop = time() > $end;
            if (!$stop && $cancelled && time() - $checked >= 2) { $checked = time(); $stop = (bool) $cancelled(); }
            if ($stop) {
                proc_terminate($p, 15);
                usleep(300_000);
                if (proc_get_status($p)['running']) proc_terminate($p, 9);
                foreach ($pipes as $pp) @fclose($pp);
                proc_close($p);
                throw new AiException(time() > $end ? __('Zeitlimit überschritten ({s} s).', ['s' => $timeout]) : __('Abgebrochen.'));
            }
        }
        foreach ($pipes as $pp) @fclose($pp);
        return ['code' => proc_close($p), 'out' => $out, 'err' => $err];
    }

    /** Umgebung für Unterprozesse: PATH mit üblichen Verzeichnissen (unter PHP-FPM oft leer) */
    private static function env(): array
    {
        $env = [];
        foreach (['HOME', 'LANG', 'TMPDIR'] as $k) if (($v = getenv($k)) !== false) $env[$k] = $v;
        $env['PATH'] = implode(':', array_unique(array_merge(self::BIN_DIRS, explode(':', (string) getenv('PATH')))));
        $env['HOME'] ??= ROOT . '/storage/ai';
        return $env;
    }
}
