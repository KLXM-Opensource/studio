<?php
declare(strict_types=1);

namespace Core\AI;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * „Verbindung prüfen“ und „Modelle anzeigen“ für eine KI-Verbindung (Core\AI\Profiles) – Grundeinstellungen → KI.
 *
 *   Ollama             GET /api/version (Version), GET /api/tags (Modelle mit Größe, Parametern, Quantisierung),
 *                      POST /api/show je Modell (Kontextlänge, Fähigkeiten; parallel, kurzes Zeitbudget)
 *   OpenAI, Mistral,   GET /v1/models (Bearer-Schlüssel); Mistral liefert Fähigkeiten und Kontextlänge mit
 *   OpenAI-kompatibel
 *   Anthropic          GET /v1/models (x-api-key, anthropic-version) – ohne Liste: kuratierte Modelle
 *   whisper.cpp        Programme und Modelle auf diesem Server (storage/ai/models/ggml-*.bin)
 *
 * Ergebnis „status“: ok | auth (Schlüssel falsch/fehlt) | unreachable (keine Verbindung, Zeitüberschreitung) | wrong_type
 * (antwortet, ist aber kein Dienst dieser Art) | blocked (Adresse nicht erlaubt) | error. Schlüssel erscheinen nie im Ergebnis
 * oder Protokoll. Ausgehende Anfragen: nur die Adresse der Verbindung, keine Weiterleitungen, Zeitlimit, Antwort ≤ 4 MB.
 * Adressen im eigenen Netz (localhost, private IP-Adressen) nur für Ollama und OpenAI-kompatible Dienste – dort hat sie die
 * Agentur ausdrücklich eingetragen; Dienste mit fester Adresse (OpenAI, Mistral, Anthropic) nur öffentlich und per https.
 * Tests (ai:selftest) übergeben einen Symfony MockHttpClient – kein echtes Netz.
 */
final class Probe
{
    public const TIMEOUT = 8.0;
    private const MAX_BYTES = 4_000_000;
    private const SHOW_MAX = 40;
    /** Anthropic: falls die Modellliste nicht abrufbar ist */
    public const ANTHROPIC_CURATED = ['claude-opus-4-1', 'claude-sonnet-4-5', 'claude-haiku-4-5'];

    // ================================================================== Öffentlich

    /** @return array{ok: bool, status: string, message: string, ms: ?int, version: ?string, count: ?int, endpoint: string} */
    public static function check(array $profile, ?HttpClientInterface $http = null): array
    {
        $p = Profiles::normalize((string) ($profile['id'] ?? 'neu'), $profile);
        $out = ['ok' => false, 'status' => 'error', 'message' => '', 'ms' => null, 'version' => null, 'count' => null, 'endpoint' => self::endpoint($p)];
        if ($p['type'] === 'whisper_cpp') return self::checkWhisper($p) + $out;
        if ($p['type'] === 'fake') return ['ok' => true, 'status' => 'ok', 'message' => __('Test-Anbieter bereit.'), 'ms' => 0] + $out;
        if ($p['type'] === '') return ['status' => 'error', 'message' => __('Unbekannter Anbieter.')] + $out;
        try {
            [$client, $base] = self::client($p, $http);
        } catch (AiException $e) {
            return ['status' => 'blocked', 'message' => $e->getMessage()] + $out;
        }
        $t = microtime(true);
        if ($p['type'] === 'ollama') {
            $r = self::get($client, $base . '/api/version', self::headers($p));
            $out['ms'] = (int) round((microtime(true) - $t) * 1000);
            if ($r['status'] !== 200) return self::fail($r, $p) + $out;
            if (!is_array($r['json']) || !isset($r['json']['version'])) return ['status' => 'wrong_type', 'message' => self::msg('wrong_type', $p)] + $out;
            $out['version'] = mb_substr((string) $r['json']['version'], 0, 40);
            $tags = self::get($client, $base . '/api/tags', self::headers($p));
            if ($tags['status'] !== 200) return self::fail($tags, $p) + $out;   // Version ist oft frei, Modelle hinter dem Token
            $out['count'] = count(self::parseOllamaTags((array) $tags['json']));
        } else {
            $r = self::get($client, self::modelsUrl($p, $base), self::headers($p));
            $out['ms'] = (int) round((microtime(true) - $t) * 1000);
            if ($r['status'] === 404 && $p['type'] === 'anthropic') {
                $out['count'] = count(self::ANTHROPIC_CURATED);
            } elseif ($r['status'] !== 200) {
                return self::fail($r, $p) + $out;
            } elseif (!is_array($r['json']) || !is_array($r['json']['data'] ?? null)) {
                return ['status' => 'wrong_type', 'message' => self::msg('wrong_type', $p)] + $out;
            } else {
                $out['count'] = count($r['json']['data']);
            }
        }
        return ['ok' => true, 'status' => 'ok', 'message' => self::okMessage($out)] + $out;
    }

    /**
     * Modelle der Verbindung.
     * @return array{ok: bool, status: string, message: string, ms: ?int, curated: bool, models: list<array{name: string, size: ?int, params: string, quant: string, family: string, context: ?int, caps: list<string>}>}
     */
    public static function models(array $profile, ?HttpClientInterface $http = null): array
    {
        $p = Profiles::normalize((string) ($profile['id'] ?? 'neu'), $profile);
        $out = ['ok' => false, 'status' => 'error', 'message' => '', 'ms' => null, 'curated' => false, 'models' => []];
        if ($p['type'] === 'whisper_cpp') {
            $list = [];
            foreach (glob(ROOT . '/storage/ai/models/ggml-*.bin') ?: [] as $f) {
                $list[] = self::model(['name' => 'storage/ai/models/' . basename($f), 'size' => (int) @filesize($f), 'caps' => ['audio']]);
            }
            return ['ok' => true, 'status' => 'ok', 'models' => $list, 'message' => $list ? '' : __('Keine Modelle in storage/ai/models/ (ggml-*.bin).')] + $out;
        }
        if ($p['type'] === 'fake') return ['ok' => true, 'status' => 'ok', 'models' => [self::model(['name' => 'fake', 'caps' => ['chat', 'vision']]), self::model(['name' => 'hash', 'caps' => ['embed']])]] + $out;
        if ($p['type'] === '') return ['message' => __('Unbekannter Anbieter.')] + $out;
        try {
            [$client, $base] = self::client($p, $http);
        } catch (AiException $e) {
            return ['status' => 'blocked', 'message' => $e->getMessage()] + $out;
        }
        $t = microtime(true);
        if ($p['type'] === 'ollama') {
            $r = self::get($client, $base . '/api/tags', self::headers($p));
            $out['ms'] = (int) round((microtime(true) - $t) * 1000);
            if ($r['status'] !== 200) return self::fail($r, $p) + $out;
            if (!is_array($r['json']) || !isset($r['json']['models'])) return ['status' => 'wrong_type', 'message' => self::msg('wrong_type', $p)] + $out;
            $models = self::parseOllamaTags($r['json']);
            $models = self::ollamaDetails($client, $base, $p, $models);
        } else {
            $r = self::get($client, self::modelsUrl($p, $base), self::headers($p));
            $out['ms'] = (int) round((microtime(true) - $t) * 1000);
            if ($r['status'] === 404 && $p['type'] === 'anthropic') {
                $out['curated'] = true;
                $models = array_map(fn($n) => self::model(['name' => $n, 'caps' => ['chat', 'vision']]), self::ANTHROPIC_CURATED);
            } elseif ($r['status'] !== 200) {
                return self::fail($r, $p) + $out;
            } elseif (!is_array($r['json']) || !is_array($r['json']['data'] ?? null)) {
                return ['status' => 'wrong_type', 'message' => self::msg('wrong_type', $p)] + $out;
            } else {
                $models = $p['type'] === 'anthropic' ? self::parseAnthropic($r['json']) : self::parseOpenAi($r['json'], $p['type']);
            }
        }
        usort($models, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        return ['ok' => true, 'status' => 'ok', 'models' => $models] + $out;
    }

    // ================================================================== Antworten auswerten (auch für den Selbsttest)

    /** Ollama /api/tags → Modelle */
    public static function parseOllamaTags(array $j): array
    {
        $out = [];
        foreach ((array) ($j['models'] ?? []) as $m) {
            if (!is_array($m) || !is_string($m['name'] ?? $m['model'] ?? null)) continue;
            $d = (array) ($m['details'] ?? []);
            $name = (string) ($m['name'] ?? $m['model']);
            $out[] = self::model(['name' => $name, 'size' => isset($m['size']) ? (int) $m['size'] : null,
                'params' => (string) ($d['parameter_size'] ?? ''), 'quant' => (string) ($d['quantization_level'] ?? ''),
                'family' => (string) ($d['family'] ?? ''), 'caps' => self::guessCaps($name, (string) ($d['family'] ?? ''), (array) ($d['families'] ?? []))]);
        }
        return $out;
    }

    /** Ollama /api/show → [context, caps|null] */
    public static function parseOllamaShow(array $j): array
    {
        $ctx = null;
        foreach ((array) ($j['model_info'] ?? []) as $k => $v) {
            if (is_string($k) && str_ends_with($k, '.context_length') && is_numeric($v)) { $ctx = (int) $v; break; }
        }
        $caps = null;
        if (is_array($j['capabilities'] ?? null)) {
            $caps = [];
            foreach ($j['capabilities'] as $c) {
                $c = match ((string) $c) { 'completion' => 'chat', 'embedding' => 'embed', 'vision' => 'vision', 'audio' => 'audio', default => null };
                if ($c) $caps[] = $c;
            }
        }
        return [$ctx, $caps];
    }

    /** OpenAI-kompatibles /v1/models (auch Mistral mit capabilities und max_context_length) */
    public static function parseOpenAi(array $j, string $type = 'openai'): array
    {
        $out = [];
        foreach ((array) ($j['data'] ?? []) as $m) {
            if (!is_array($m) || !is_string($m['id'] ?? null)) continue;
            $name = $m['id'];
            $caps = self::guessCaps($name, '', []);
            if (is_array($m['capabilities'] ?? null)) {   // Mistral
                $c = $m['capabilities'];
                $caps = array_values(array_filter([
                    !empty($c['completion_chat']) ? 'chat' : null,
                    !empty($c['vision']) ? 'vision' : null,
                    !empty($c['audio']) || !empty($c['audio_transcription']) ? 'audio' : null,
                    str_contains(strtolower($name), 'embed') ? 'embed' : null,
                ])) ?: $caps;
            }
            $out[] = self::model(['name' => $name, 'context' => isset($m['max_context_length']) ? (int) $m['max_context_length'] : (isset($m['context_length']) ? (int) $m['context_length'] : null),
                'family' => (string) ($m['owned_by'] ?? ''), 'caps' => $caps]);
        }
        return $out;
    }

    /** Anthropic /v1/models */
    public static function parseAnthropic(array $j): array
    {
        $out = [];
        foreach ((array) ($j['data'] ?? []) as $m) {
            if (!is_array($m) || !is_string($m['id'] ?? null)) continue;
            $out[] = self::model(['name' => $m['id'], 'family' => (string) ($m['display_name'] ?? ''), 'caps' => ['chat', 'vision']]);
        }
        return $out;
    }

    /** Fähigkeiten aus Name/Familie raten (wenn der Dienst sie nicht nennt) */
    public static function guessCaps(string $name, string $family = '', array $families = []): array
    {
        $n = strtolower($name);
        $fam = strtolower($family . ' ' . implode(' ', array_map('strval', $families)));
        if (preg_match('~embed|bge-|e5-|gte-|minilm|nomic-bert|arctic-embed~', $n) || preg_match('~\bbert\b|nomic-bert~', $fam)) return ['embed'];
        if (preg_match('~whisper|voxtral|transcribe|speech|tts~', $n)) return ['audio'];
        if (preg_match('~dall-e|gpt-image|moderation|rerank~', $n)) return [];
        $caps = ['chat'];
        if (preg_match('~llava|vision|gemma3|gemma-3|qwen2\.5vl|qwen2\.5-vl|qwen2-vl|qwen3-vl|minicpm-v|moondream|pixtral|llama4|mistral-small3\.[12]|bakllava|gpt-4o|gpt-4\.1|gpt-5|claude|granite3\.2-vision~', $n)
            || str_contains($fam, 'clip') || str_contains($fam, 'mllama')) $caps[] = 'vision';
        return $caps;
    }

    // ================================================================== Intern

    private static function model(array $m): array
    {
        return ['name' => (string) $m['name'], 'size' => $m['size'] ?? null, 'params' => (string) ($m['params'] ?? ''), 'quant' => (string) ($m['quant'] ?? ''),
            'family' => (string) ($m['family'] ?? ''), 'context' => $m['context'] ?? null, 'caps' => array_values(array_unique((array) ($m['caps'] ?? [])))];
    }

    /** Kontextlänge und Fähigkeiten je Modell (POST /api/show, parallel, höchstens SHOW_MAX Modelle, kurzes Zeitbudget) */
    private static function ollamaDetails(HttpClientInterface $client, string $base, array $p, array $models): array
    {
        $resp = [];
        foreach (array_slice($models, 0, self::SHOW_MAX, true) as $i => $m) {
            try {
                $resp[$i] = $client->request('POST', $base . '/api/show', ['headers' => self::headers($p) + ['Content-Type' => 'application/json'],
                    'body' => json_encode(['model' => $m['name']]), 'timeout' => 3, 'max_duration' => 4]);
            } catch (\Throwable) {}
        }
        foreach ($resp as $i => $r) {
            try {
                if ($r->getStatusCode() !== 200) continue;
                $j = json_decode(substr($r->getContent(false), 0, self::MAX_BYTES), true);
                if (!is_array($j)) continue;
                [$ctx, $caps] = self::parseOllamaShow($j);
                if ($ctx) $models[$i]['context'] = $ctx;
                if ($caps) $models[$i]['caps'] = $caps;
            } catch (\Throwable) {}
        }
        return $models;
    }

    /** Adresse, an die die Prüfung geht (ohne Pfad) */
    public static function endpoint(array $p): string
    {
        return rtrim(Ai::endpoint(['provider' => $p['type']] + $p), '/');
    }

    private static function modelsUrl(array $p, string $base): string
    {
        return $base . (preg_match('~/v1$~', $base) ? '/models' : '/v1/models');
    }

    private static function headers(array $p): array
    {
        $key = trim((string) $p['api_key']);
        if ($p['type'] === 'anthropic') return array_filter(['x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'Accept' => 'application/json']);
        return array_filter(['Authorization' => $key !== '' ? 'Bearer ' . $key : '', 'Accept' => 'application/json']);
    }

    /**
     * Adresse prüfen und Client bauen. Regeln wie bei den übrigen ausgehenden Abrufen des Kerns (Core\Sources\Fetcher):
     * keine Zugangsdaten in der Adresse, keine Weiterleitungen, Metadaten-Adressen der Cloud nie; eigenes Netz nur für
     * Ollama/OpenAI-kompatibel (dort ausdrücklich eingetragen), http nur im eigenen Netz.
     * @return array{0: HttpClientInterface, 1: string}
     */
    private static function client(array $p, ?HttpClientInterface $http): array
    {
        $base = self::endpoint($p);
        $u = parse_url($base);
        $scheme = strtolower((string) ($u['scheme'] ?? ''));
        $host = strtolower(trim((string) ($u['host'] ?? ''), '[]'));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') throw new AiException(__('Adresse (base_url) fehlt oder ist ungültig.'));
        if (isset($u['user']) || isset($u['pass'])) throw new AiException(__('Zugangsdaten gehören nicht in die Adresse – bitte als API-Schlüssel eintragen.'));
        $local = in_array($p['type'], ['ollama', 'generic'], true);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($http ? [] : self::resolve($host));
        if (!$http && !$ips) throw new AiException(__('Der Name „{host}“ lässt sich nicht auflösen.', ['host' => $host]));
        foreach ($ips as $ip) {
            // Link-local (169.254.0.0/16, fe80::/10) – u. a. Metadaten-Dienste der Cloud – nie
            if (str_starts_with($ip, '169.254.') || str_starts_with(strtolower($ip), 'fe80:') || $host === 'metadata.google.internal') {
                throw new AiException(__('Diese Adresse ist aus Sicherheitsgründen gesperrt.'));
            }
            if (!$local && !\Core\Sources\Fetcher::isPublicIp($ip)) {
                throw new AiException(__('Adressen im eigenen Netz sind nur für Ollama und OpenAI-kompatible Dienste erlaubt.'));
            }
        }
        $private = Ai::isLocalHost($host) || ($ips && !array_filter($ips, [\Core\Sources\Fetcher::class, 'isPublicIp']));
        if ($scheme === 'http' && !$private) throw new AiException(__('Externe Anbieter nur über https://.'));
        $opts = ['timeout' => self::TIMEOUT, 'max_duration' => self::TIMEOUT + 2, 'max_redirects' => 0,
            'headers' => ['User-Agent' => CMS_NAME . '/' . CMS_VERSION]];
        // Aufgelöste Adresse festhalten (kein zweites, anderes DNS-Ergebnis beim eigentlichen Abruf)
        if ($ips && !filter_var($host, FILTER_VALIDATE_IP)) $opts['resolve'] = [$host => $ips[0]];
        $inner = $http ?? HttpClient::create($opts);
        return [new GuardedHttpClient($http ? $inner->withOptions(['max_redirects' => 0]) : $inner, [$host]), $base];
    }

    private static function resolve(string $host): array
    {
        if ($host === 'localhost') return ['127.0.0.1'];
        if (!preg_match('~^[a-z0-9.-]+$~', $host)) return [];
        $ips = @gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $r) if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
        return array_values(array_unique($ips));
    }

    /** GET mit Größenbegrenzung → [status (0 = keine Verbindung), json, error] */
    private static function get(HttpClientInterface $client, string $url, array $headers): array
    {
        try {
            $r = $client->request('GET', $url, ['headers' => $headers]);
            $status = $r->getStatusCode();
            $body = '';
            foreach ($client->stream($r) as $chunk) {
                $body .= $chunk->getContent();
                if (strlen($body) > self::MAX_BYTES) { $r->cancel(); break; }
            }
            $json = json_decode($body, true);
            return ['status' => $status, 'json' => is_array($json) ? $json : null, 'error' => ''];
        } catch (TransportExceptionInterface $e) {
            return ['status' => 0, 'json' => null, 'error' => Ai::shortError($e)];
        } catch (\Throwable $e) {
            return ['status' => 0, 'json' => null, 'error' => Ai::shortError($e)];
        }
    }

    private static function fail(array $r, array $p): array
    {
        $status = match (true) {
            $r['status'] === 0 => 'unreachable',
            in_array($r['status'], [401, 403], true) => 'auth',
            in_array($r['status'], [404, 405], true) => 'wrong_type',
            default => 'error',
        };
        $msg = self::msg($status, $p);
        if ($status === 'error') $msg .= ' (HTTP ' . (int) $r['status'] . ')';
        if ($status === 'unreachable' && $r['error'] !== '') $msg .= ' ' . mb_strimwidth((string) preg_replace('~(Bearer|key[=:])\s*\S+~i', '$1 ***', $r['error']), 0, 160, '…');
        return ['status' => $status, 'message' => $msg];
    }

    private static function msg(string $status, array $p): string
    {
        $type = __(Profiles::TYPES[$p['type']] ?? $p['type']);
        return match ($status) {
            'auth' => $p['api_key'] === '' ? __('Zugriff verweigert – der Dienst verlangt einen Schlüssel bzw. Token.') : __('Zugriff verweigert – Schlüssel bzw. Token ist falsch oder abgelaufen.'),
            'unreachable' => __('Keine Verbindung – Adresse, Port und Firewall prüfen.'),
            'wrong_type' => __('Antwortet, ist aber kein Dienst der Art „{type}“ – Anbieter oder Adresse prüfen.', ['type' => $type]),
            default => __('Unerwartete Antwort des Dienstes.'),
        };
    }

    private static function okMessage(array $o): string
    {
        $bits = [__('Verbindung in Ordnung')];
        if ($o['version']) $bits[] = __('Version {v}', ['v' => $o['version']]);
        if ($o['count'] !== null) $bits[] = __('{n} Modelle', ['n' => $o['count']]);
        if ($o['ms'] !== null) $bits[] = $o['ms'] . ' ms';
        return implode(' · ', $bits);
    }

    private static function checkWhisper(array $p): array
    {
        $t = Transcriber::normalize(['backend' => 'whisper_cpp', 'whisper_bin' => $p['whisper_bin'], 'model_path' => $p['model_path'], 'ffmpeg' => $p['ffmpeg']]);
        $errors = Transcriber::check($t);
        return $errors ? ['ok' => false, 'status' => 'error', 'message' => implode(' ', $errors)]
            : ['ok' => true, 'status' => 'ok', 'message' => __('whisper.cpp, Modell und ffmpeg gefunden.'), 'ms' => 0];
    }
}
