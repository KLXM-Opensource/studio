<?php
declare(strict_types=1);

namespace Core\AI;

use Core\Features;
use Symfony\AI\Platform\Bridge\Generic\Factory as GenericFactory;
use Symfony\AI\Platform\Bridge\Mistral\Factory as MistralFactory;
use Symfony\AI\Platform\Bridge\Ollama\Factory as OllamaFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAiFactory;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * KI-Dienst des Kerns auf Basis von Symfony AI (symfony/ai-platform + Bridges Ollama, Mistral, OpenAI, OpenAI-kompatibel).
 * Standard: AUS. Drei Fähigkeiten (CAPS) mit eigenem Modell – optional eigenem Anbieter:
 *   text    Texte erzeugen/umschreiben/übersetzen, SEO (chat(), complete())
 *   embed   Embeddings (semantische Suche, embed(), embedder())
 *   vision  Bild + Anweisung → Text, z. B. Alt-Texte (vision())
 *
 * Konfiguration (drei Ebenen, spätere überschreiben frühere):
 *   1. Grundeinstellungen → KI (nur Agentur/Netzwerk): storage/ai/config.json – gilt für die ganze Installation
 *   2. config/config.local.php 'ai' => [...] (Installation, in der Verwaltung dann gesperrt)
 *   3. config/sites/{key}.php 'ai' => [...] (einzelne Website)
 *   'ai' => [
 *     'provider' => 'ollama',                      // ollama | mistral | openai | generic (OpenAI-kompatibel) | fake (nur Tests)
 *     'base_url' => 'http://127.0.0.1:11434',      // ollama/generic: Adresse; mistral optional; openai: nicht nötig
 *     'api_key'  => '',                            // mistral/openai/generic
 *     'region'   => 'EU',                          // nur openai: EU-Datenresidenz (eu.api.openai.com)
 *     'models'   => ['text' => 'gemma3', 'embed' => 'nomic-embed-text', 'vision' => 'gemma3'],
 *     'providers'=> ['vision' => ['provider' => 'mistral', 'api_key' => '…']],   // optional: anderer Anbieter je Fähigkeit
 *     'timeout' => 2.5, 'text_timeout' => 60, 'index_timeout' => 60,          // Sekunden (Besucher-Suche / Redaktion / Indexierung)
 *     'prices'  => ['gpt-4o-mini' => [0.15, 0.60]],                            // optional: USD je 1 Mio. Tokens (Ein-/Ausgabe) für die Kostenschätzung
 *   ]
 * Je Website schaltet die Administration KI und einzelne Fähigkeiten ein (Grundeinstellungen → KI: sys.ai_enabled, sys.ai_text|embed|vision);
 * Funktion „ai“ (Features) und Recht „ai.use“ (Rollen) begrenzen zusätzlich.
 *
 * Datenschutz: Texte/Bilder gehen nur an den Anbieter, wenn eine Funktion sie ausdrücklich sendet. Lokale Anbieter (Ollama
 * auf demselben Server/Netz) verlassen den Server nicht – external(). Protokolliert werden nur Zähler (Aufrufe, Tokens,
 * Dauer, Fehler) je Website, Tag, Fähigkeit und Modell – nie Inhalte.
 */
final class Ai
{
    /** transcribe: Sprache → Text für Untertitel (eigene Wege: whisper.cpp lokal oder OpenAI-kompatibel, Core\AI\Transcriber) */
    public const CAPS = ['text' => 'Texte', 'embed' => 'Embeddings (Suche)', 'vision' => 'Bilder (Vision)', 'transcribe' => 'Sprache → Text (Untertitel)'];
    public const PROVIDERS = [
        'ollama' => 'Ollama (lokal/eigener Server)',
        'mistral' => 'Mistral AI (EU)',
        'openai' => 'OpenAI',
        'generic' => 'OpenAI-kompatibel (z. B. vLLM, LM Studio, IONOS, Scaleway, STACKIT)',
        'fake' => 'Test-Anbieter (ohne KI)',
    ];
    /** Vorgaben je Anbieter und Fähigkeit, falls kein Modell konfiguriert ist */
    private const DEFAULT_MODELS = [
        'ollama' => ['embed' => 'nomic-embed-text'],
        'mistral' => ['text' => 'mistral-small-latest', 'embed' => 'mistral-embed', 'vision' => 'pixtral-12b-latest'],
        'openai' => ['text' => 'gpt-4o-mini', 'embed' => 'text-embedding-3-small', 'vision' => 'gpt-4o-mini'],
        'fake' => ['embed' => 'hash', 'text' => 'fake', 'vision' => 'fake'],
    ];
    /** Nach einem Fehler wird der Anbieter so lange nicht mehr gefragt (Sekunden) – Besucher warten nicht auf Zeitüberschreitungen */
    private const PAUSE = 60;
    /** Bilder für vision(): längste Seite in Pixeln */
    public const VISION_MAX = 1024;

    private static array $platforms = [];

    // ================================================================== Konfiguration

    /** In der Verwaltung gespeicherte Installations-Konfiguration (storage/ai/config.json) */
    public static function stored(): array
    {
        $f = ROOT . '/storage/ai/config.json';
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        return is_array($d) ? $d : [];
    }

    /** Installations-Konfiguration speichern (nur Agentur/Netzwerk; Datei außerhalb des Webroots, 0600) */
    public static function store(array $values): void
    {
        $dir = ROOT . '/storage/ai';
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        file_put_contents($dir . '/config.json', json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($dir . '/config.json', 0600);
        self::$platforms = [];
    }

    /** Werte aus den Konfigurationsdateien der Installation (config.php ← config.local.php) – in der Verwaltung gesperrt */
    public static function fileConfig(): array
    {
        $base = (array) ((require ROOT . '/config/config.php')['ai'] ?? []);
        $local = is_file(ROOT . '/config/config.local.php') ? (require ROOT . '/config/config.local.php') : [];
        return array_replace_recursive($base, is_array($local) ? (array) ($local['ai'] ?? []) : []);
    }

    /** Konfiguration der Installation (Verwaltung ← Dateien), ohne Werte einzelner Websites – z. B. für die Wissensdatenbank */
    public static function installationConfig(): array
    {
        return self::config(array_replace_recursive(self::stored(), self::fileConfig()));
    }

    /**
     * Normalisierte Konfiguration dieser Website (Verwaltung ← config.local.php ← config/sites/{key}.php).
     * Enthält u. a. provider, base_url, api_key, region, models[cap], timeout, text_timeout, index_timeout, configured, external.
     */
    public static function config(?array $raw = null): array
    {
        $c = $raw ?? array_replace_recursive(self::stored(), (array) app()->config->get('ai', []));
        $provider = strtolower(trim((string) ($c['provider'] ?? '')));
        $provider = isset(self::PROVIDERS[$provider]) ? $provider : '';
        $models = (array) ($c['models'] ?? []);
        // Ältere Schlüssel: 'embeddings' (Embedding-Modell), 'model' (Textmodell)
        $models += array_filter(['embed' => $c['embeddings'] ?? null, 'text' => $c['model'] ?? null]);
        $out = [
            'provider' => $provider,
            'base_url' => rtrim(trim((string) ($c['base_url'] ?? '')), '/'),
            'api_key' => (string) ($c['api_key'] ?? ''),
            'region' => ($c['region'] ?? null) ? strtoupper((string) $c['region']) : null,
            'models' => [],
            'providers' => [],
            'timeout' => max(0.5, min(30.0, (float) ($c['timeout'] ?? 2.5))),
            'text_timeout' => max(5.0, min(300.0, (float) ($c['text_timeout'] ?? 60))),
            'index_timeout' => max(5.0, min(300.0, (float) ($c['index_timeout'] ?? 60))),
            'batch' => max(1, min(256, (int) ($c['batch'] ?? 32))),
            'kb' => ($c['kb'] ?? true) !== false,   // Wissensdatenbank (Support) semantisch durchsuchen
            'prices' => (array) ($c['prices'] ?? []),
        ];
        if ($out['provider'] === 'ollama' && $out['base_url'] === '') $out['base_url'] = 'http://127.0.0.1:11434';
        $out['transcribe'] = Transcriber::normalize((array) ($c['transcribe'] ?? []));
        foreach (array_keys(self::CAPS) as $cap) {
            if ($cap === 'transcribe') continue;   // eigener Abschnitt 'transcribe' (Transcriber::capability)
            $o = (array) ($c['providers'][$cap] ?? []);
            $p = strtolower(trim((string) ($o['provider'] ?? $provider)));
            $p = isset(self::PROVIDERS[$p]) ? $p : '';
            $out['providers'][$cap] = [
                'provider' => $p,
                'base_url' => rtrim(trim((string) ($o['base_url'] ?? ($p === $provider ? $out['base_url'] : ''))), '/') ?: ($p === 'ollama' ? 'http://127.0.0.1:11434' : ''),
                'api_key' => (string) ($o['api_key'] ?? ($p === $provider ? $out['api_key'] : '')),
                'region' => isset($o['region']) ? strtoupper((string) $o['region']) : ($p === $provider ? $out['region'] : null),
            ];
            $m = trim((string) ($o['model'] ?? $models[$cap] ?? ''));
            $out['models'][$cap] = $m !== '' ? $m : (self::DEFAULT_MODELS[$p][$cap] ?? '');
        }
        $out['configured'] = $provider !== '' && ($c['enabled'] ?? true) !== false;
        $out['enabled'] = $out['configured'];                     // Kompatibilität
        $out['embeddings'] = $out['configured'] ? $out['models']['embed'] : '';
        $out['model'] = $out['models']['text'];
        $out['external'] = $out['configured'] && self::isExternal(self::capability('embed', $out)) ;
        return $out;
    }

    /**
     * Angaben einer Fähigkeit: provider, base_url, api_key, region, model, endpoint, external, configured
     * @param 'text'|'embed'|'vision' $cap
     */
    public static function capability(string $cap, ?array $cfg = null): array
    {
        $cfg ??= self::config();
        if ($cap === 'transcribe') return Transcriber::capability($cfg['transcribe'] ?? Transcriber::normalize([]), true);
        $p = $cfg['providers'][$cap] ?? ['provider' => '', 'base_url' => '', 'api_key' => '', 'region' => null];
        $out = $p + ['cap' => $cap, 'model' => (string) ($cfg['models'][$cap] ?? '')];
        $out['configured'] = ($cfg['configured'] ?? false) && $out['provider'] !== '' && $out['model'] !== '';
        $out['endpoint'] = self::endpoint($out);
        $out['external'] = $out['configured'] && self::isExternal($out);
        return $out;
    }

    /** Schalter dieser Website: KI an/aus und je Fähigkeit (Grundeinstellungen → KI) */
    public static function siteSettings(): array
    {
        $out = ['enabled' => (bool) app()->settings->get('sys.ai_enabled', false), 'caps' => []];
        foreach (array_keys(self::CAPS) as $cap) $out['caps'][$cap] = (bool) app()->settings->get('sys.ai_' . $cap, true);
        return $out;
    }

    /**
     * Darf diese Website die Fähigkeit nutzen? Funktion „ai“ + Anbieter/Modell konfiguriert + Schalter der Website.
     * (Das Recht „ai.use“ der angemeldeten Person prüft der Aufrufer: can('ai.use').)
     */
    public static function enabled(string $cap = 'text'): bool
    {
        if (!Features::on('ai', false) || !self::capability($cap)['configured']) return false;
        $s = self::siteSettings();
        return $s['enabled'] && ($s['caps'][$cap] ?? false);
    }

    /** Verlassen Daten den Server? (Anbieter nicht auf localhost/privatem Netz) */
    public static function isExternal(array $cap): bool
    {
        if (($cap['provider'] ?? '') === 'fake' || ($cap['provider'] ?? '') === '') return false;
        return !self::isLocalHost(strtolower((string) parse_url(self::endpoint($cap), PHP_URL_HOST)));
    }

    public static function isLocalHost(string $host): bool
    {
        $host = trim(strtolower($host), '[]');
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || $host === '::1') return true;
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        return false;
    }

    /** Adresse, an die Anfragen gehen */
    public static function endpoint(array $cap): string
    {
        return match ($cap['provider'] ?? '') {
            'openai' => ($cap['region'] ?? null) === 'EU' ? 'https://eu.api.openai.com' : 'https://api.openai.com',
            'mistral' => ($cap['base_url'] ?? '') ?: 'https://api.mistral.ai',
            'fake' => 'local://fake',
            default => (string) ($cap['base_url'] ?? ''),
        };
    }

    // ================================================================== Symfony AI

    /** Symfony-AI-Platform für eine Fähigkeit (Anbieter-Bridge mit Positivliste und Zeitlimit) */
    public static function platform(array $cap, float $timeout): PlatformInterface
    {
        if (!in_array($cap['provider'] ?? '', ['ollama', 'mistral', 'openai', 'generic'], true)) {
            throw new AiException(__('Kein KI-Anbieter konfiguriert.'));
        }
        $key = md5(json_encode([$cap['provider'], $cap['base_url'], $cap['api_key'], $cap['region'], $timeout]));
        if (isset(self::$platforms[$key])) return self::$platforms[$key];
        $endpoint = self::endpoint($cap);
        if (!preg_match('~^https?://~', $endpoint)) {
            throw new AiException(__('KI-Anbieter: Adresse (base_url) fehlt oder ist ungültig.'));
        }
        $http = new GuardedHttpClient(HttpClient::create([
            'timeout' => $timeout, 'max_duration' => $timeout + 1, 'max_redirects' => 0,
            'headers' => ['User-Agent' => CMS_NAME . '/' . CMS_VERSION],
        ]), [strtolower((string) parse_url($endpoint, PHP_URL_HOST))]);
        $apiKey = $cap['api_key'] !== '' ? $cap['api_key'] : null;
        $platform = match ($cap['provider']) {
            'ollama' => OllamaFactory::createPlatform($endpoint, $apiKey, $http, \Symfony\AI\Platform\Bridge\Ollama\Contract\OllamaContract::create([new OllamaImageNormalizer()])),
            'mistral' => MistralFactory::createPlatform((string) $apiKey, $http, baseUrl: $endpoint),
            'openai' => OpenAiFactory::createPlatform((string) $apiKey, $http, region: ($cap['region'] ?? null) === 'EU' ? OpenAiFactory::REGION_EU : null),
            'generic' => GenericFactory::createPlatform($endpoint, $apiKey, $http),
        };
        return self::$platforms[$key] = $platform;
    }

    /**
     * Embeddings-Dienst (ohne Schalter der Website – Aufrufer prüft enabled('embed') bzw. Search::semanticActive()).
     * $cfg: eigene Konfiguration (z. B. Installation für die Wissensdatenbank). $timeout: Besucher kurz, Indexierung lang.
     */
    public static function embedder(?array $cfg = null, ?float $timeout = null): ?Embedder
    {
        $cfg ??= self::config();
        $cap = self::capability('embed', $cfg);
        if (!$cap['configured']) return null;
        if ($cap['provider'] === 'fake') return new FakeEmbedder($cap['base_url'] === 'fail');
        return new TrackingEmbedder(new PlatformEmbedder(self::platform($cap, $timeout ?? $cfg['timeout']), $cap['provider'], $cap['model'], $cfg['batch']), $cap);
    }

    // ================================================================== Öffentliche API

    /**
     * Text erzeugen. $messages: Anweisung (string) oder Verlauf [['role' => 'system'|'user'|'assistant', 'content' => '…'], …]
     * $o: system (Systemanweisung), max_tokens (Standard 800), temperature (Standard 0.3), model (anderes Modell), timeout (s),
     *     think (Ollama: Denkmodus, Standard aus), json (Antwort als JSON-Objekt, Ollama: format=json),
     *     fake (Antwort des Test-Anbieters „fake“)
     * @return array{text: string, model: string, provider: string, tokens_in: ?int, tokens_out: ?int, ms: int}
     * @throws AiException bei ausgeschalteter KI, Zeitüberschreitung oder Anbieterfehler (Meldung für die Oberfläche geeignet)
     */
    public static function chat(string|array $messages, array $o = []): array
    {
        $cap = self::ready('text', $o);
        $bag = [];
        if (($o['system'] ?? '') !== '') $bag[] = Message::forSystem((string) $o['system']);
        foreach (is_string($messages) ? [['role' => 'user', 'content' => $messages]] : $messages as $m) {
            $bag[] = match ($m['role'] ?? 'user') {
                'system' => Message::forSystem((string) $m['content']),
                'assistant' => Message::ofAssistant((string) $m['content']),
                default => Message::ofUser((string) $m['content']),
            };
        }
        return self::run('text', $cap, new MessageBag(...$bag), $o);
    }

    /** Kurzform: Anweisung → Text */
    public static function complete(string $prompt, string $system = '', array $o = []): string
    {
        return self::chat($prompt, ['system' => $system] + $o)['text'];
    }

    /**
     * Text erzeugen und Stück für Stück ausgeben (Streaming, z. B. für Server-Sent Events – Core\Http\Sse).
     * $messages, $o wie chat(); zusätzlich $o['track']: Zählname für die Nutzung (Standard „text“, der Besucher-Chat
     * zählt als „chat“ und nicht zum Tageslimit der Redaktion). $onDelta(string $text): bool – false bricht ab
     * (z. B. Besucher hat das Fenster geschlossen). Anbieter ohne Streaming liefern die ganze Antwort in einem Stück.
     * @return array wie chat(), zusätzlich 'streamed' (bool) und 'aborted' (bool)
     */
    public static function stream(string|array $messages, array $o, callable $onDelta): array
    {
        $cap = self::ready('text', $o);
        $track = preg_replace('~[^a-z_]~', '', (string) ($o['track'] ?? 'text')) ?: 'text';
        $bag = [];
        if (($o['system'] ?? '') !== '') $bag[] = Message::forSystem((string) $o['system']);
        foreach (is_string($messages) ? [['role' => 'user', 'content' => $messages]] : $messages as $m) {
            $bag[] = match ($m['role'] ?? 'user') {
                'system' => Message::forSystem((string) $m['content']),
                'assistant' => Message::ofAssistant((string) $m['content']),
                default => Message::ofUser((string) $m['content']),
            };
        }
        $cfg = self::config();
        $t = microtime(true);
        $max = max(1, min(8000, (int) ($o['max_tokens'] ?? 800)));
        $temp = max(0.0, min(2.0, (float) ($o['temperature'] ?? 0.3)));
        $opts = match ($cap['provider']) {
            'ollama' => ['temperature' => $temp, 'num_predict' => $max, 'think' => (bool) ($o['think'] ?? false)],
            'openai' => ['temperature' => $temp, 'max_output_tokens' => $max],
            default => ['temperature' => $temp, 'max_tokens' => $max],
        };
        $text = '';
        $streamed = false;
        $aborted = false;
        $usage = null;
        try {
            if ($cap['provider'] === 'fake') {
                // Test-Anbieter: feste Antwort in kleinen Stücken (wie ein echter Stream)
                $fake = isset($o['fake']) ? (string) $o['fake'] : 'bereit';
                foreach (preg_split('~(?<=\s)~u', $fake) ?: [] as $piece) {
                    if ($piece === '') continue;
                    $text .= $piece;
                    $streamed = true;
                    if ($onDelta($piece) === false) { $aborted = true; break; }
                    usleep(15000);
                }
            } else {
                $deferred = self::platform($cap, (float) ($o['timeout'] ?? $cfg['text_timeout']))->invoke($cap['model'], new MessageBag(...$bag), $opts + ['stream' => true]);
                $result = $deferred->getResult();
                if ($result instanceof \Symfony\AI\Platform\Result\StreamResult) {
                    foreach ($result->getContent() as $delta) {
                        // Nur sichtbarer Text – Denk-Abschnitte (ThinkingDelta) und Metadaten bleiben weg
                        if (!$delta instanceof \Symfony\AI\Platform\Result\Stream\Delta\TextDelta) continue;
                        $piece = $delta->getText();
                        if ($piece === '') continue;
                        $text .= $piece;
                        $streamed = true;
                        if ($onDelta($piece) === false) { $aborted = true; break; }
                    }
                    $usage = $result->getMetadata()->get('token_usage');
                } else {
                    // Anbieter streamt nicht: ganze Antwort auf einmal
                    $text = trim((string) $deferred->asText());
                    $usage = $result->getMetadata()->get('token_usage');
                    if ($text !== '') $onDelta($text);
                }
            }
        } catch (\Throwable $e) {
            self::track($track, $cap, 0, 0, (int) ((microtime(true) - $t) * 1000), true);
            self::failed($e, $cap);
            throw new AiException(self::shortError($e), 0, $e);
        }
        $ms = (int) ((microtime(true) - $t) * 1000);
        $in = $usage instanceof TokenUsageInterface ? $usage->getPromptTokens() : null;
        $out = $usage instanceof TokenUsageInterface ? $usage->getCompletionTokens() : null;
        self::track($track, $cap, (int) ($in ?? (int) ceil(mb_strlen(json_encode($messages) . ($o['system'] ?? '')) / 4)), (int) ($out ?? (int) ceil(mb_strlen($text) / 4)), $ms, false);
        self::recovered($cap);
        return ['text' => trim($text), 'model' => $cap['model'], 'provider' => $cap['provider'], 'tokens_in' => $in, 'tokens_out' => $out, 'ms' => $ms,
            'streamed' => $streamed, 'aborted' => $aborted];
    }

    /**
     * Bild beschreiben lassen (z. B. Alt-Text). $image: Medien-ID der Website oder Dateipfad.
     * Gesendet wird immer eine verkleinerte Fassung (max. 1024 px, JPEG/WebP) – nie das Original.
     * $o wie chat(); @return array wie chat()
     */
    public static function vision(int|string $image, string $prompt, array $o = []): array
    {
        $cap = self::ready('vision', $o);
        [$data, $mime] = self::imageForVision($image);
        $bag = [];
        if (($o['system'] ?? '') !== '') $bag[] = Message::forSystem((string) $o['system']);
        $bag[] = Message::ofUser($prompt, new Image($data, $mime));
        return self::run('vision', $cap, new MessageBag(...$bag), $o + ['max_tokens' => 300]);
    }

    /**
     * Embeddings für Texte (normiert nicht). $kind: document | query (manche Modelle erwarten Präfixe).
     * @return list<list<float>>
     */
    public static function embed(array $texts, string $kind = 'document', array $o = []): array
    {
        self::ready('embed', $o);
        $emb = self::embedder(null, (float) ($o['timeout'] ?? self::config()['index_timeout'])) ?? throw new AiException(__('Kein Embedding-Modell konfiguriert.'));
        try {
            return $emb->embed(array_values($texts), $kind);
        } catch (\Throwable $e) {
            self::failed($e, self::capability('embed'));
            throw new AiException(self::shortError($e), 0, $e);
        }
    }

    /** Fähigkeit bereit? (Website-Schalter, Anbieter, Pause nach Fehler) – sonst AiException */
    private static function ready(string $cap, array $o): array
    {
        if (empty($o['force']) && !self::enabled($cap)) {
            throw new AiException(__('KI ist für diese Website nicht eingeschaltet (Grundeinstellungen → KI).'));
        }
        $c = self::capability($cap);
        if (!$c['configured']) throw new AiException(__('Für diese KI-Funktion ist kein Modell konfiguriert.'));
        if (!empty($o['model'])) $c['model'] = (string) $o['model'];
        if (self::paused($c) && empty($o['force'])) throw new AiException(__('Der KI-Anbieter ist gerade nicht erreichbar. Bitte in einer Minute erneut versuchen.'));
        return $c;
    }

    /** Aufruf ausführen, Zähler schreiben, Fehler vereinheitlichen */
    private static function run(string $capName, array $cap, MessageBag $bag, array $o): array
    {
        $cfg = self::config();
        $t = microtime(true);
        $max = max(1, min(8000, (int) ($o['max_tokens'] ?? 800)));
        $temp = max(0.0, min(2.0, (float) ($o['temperature'] ?? 0.3)));
        $opts = match ($cap['provider']) {
            // „Denkende“ Modelle (gemma4, qwen3 …) sonst mit leerer Antwort, wenn max_tokens im Denkteil aufgebraucht ist
            'ollama' => ['temperature' => $temp, 'num_predict' => $max, 'think' => (bool) ($o['think'] ?? false)],
            'openai' => ['temperature' => $temp, 'max_output_tokens' => $max],
            default => ['temperature' => $temp, 'max_tokens' => $max],
        };
        // JSON-Antwort erzwingen (KI-Assistent, Core\AI\Assist): Ollama kennt „format“; übrige Anbieter per Anweisung im Prompt
        if (!empty($o['json']) && $cap['provider'] === 'ollama') $opts['format'] = 'json';
        try {
            if ($cap['provider'] === 'fake') {
                // Test-Anbieter: feste Antwort; Aufrufer können eine eigene Test-Antwort mitgeben ('fake')
                $text = isset($o['fake']) ? (string) $o['fake'] : ($capName === 'vision' ? 'Testbild' : 'bereit');
                $usage = null;
            } else {
                $deferred = self::platform($cap, (float) ($o['timeout'] ?? $cfg['text_timeout']))->invoke($cap['model'], $bag, $opts);
                $result = $deferred->getResult();
                $text = trim((string) $deferred->asText());
                $usage = $result->getMetadata()->get('token_usage');
            }
        } catch (\Throwable $e) {
            self::track($capName, $cap, 0, 0, (int) ((microtime(true) - $t) * 1000), true);
            self::failed($e, $cap);
            throw new AiException(self::shortError($e), 0, $e);
        }
        $ms = (int) ((microtime(true) - $t) * 1000);
        $in = $usage instanceof TokenUsageInterface ? $usage->getPromptTokens() : null;
        $out = $usage instanceof TokenUsageInterface ? $usage->getCompletionTokens() : null;
        self::track($capName, $cap, (int) ($in ?? 0), (int) ($out ?? (int) ceil(mb_strlen($text) / 4)), $ms, false);
        self::recovered($cap);
        return ['text' => $text, 'model' => $cap['model'], 'provider' => $cap['provider'], 'tokens_in' => $in, 'tokens_out' => $out, 'ms' => $ms];
    }

    /** Verkleinerte Bilddaten für vision(): vorhandene Größe ≥ 1024 px (WebP) oder per GD verkleinertes JPEG */
    public static function imageForVision(int|string $image): array
    {
        $path = null;
        if (is_int($image) || ctype_digit((string) $image)) {
            $m = \Core\Media::find((int) $image) ?? throw new AiException(__('Bild nicht gefunden.'));
            if (!str_starts_with((string) $m['mime'], 'image/')) throw new AiException(__('Nur Bilder können beschrieben werden.'));
            if ($m['mime'] === \Core\Svg::MIME) {
                // SVG (Core\Svg): nur mit Imagick in Pixel umwandelbar
                $img = \Core\Svg::rasterize(\Core\Media::path($m), self::VISION_MAX)
                    ?? throw new AiException(__('SVG-Grafiken kann die KI auf diesem Server nicht ansehen – bitte den Alt-Text selbst eingeben.'));
                $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
                imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
                imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
                ob_start();
                imagejpeg($bg, null, 82);
                return [(string) ob_get_clean(), 'image/jpeg'];
            }
            $dir = !empty($m['_pool']) ? \Core\MediaPools::mediaDir((string) $m['_pool']) : site()->mediaDir();
            $v = json_decode((string) ($m['variants_json'] ?? ''), true) ?: [];
            foreach ($v['sizes'] ?? [] as $s) {
                $f = $dir . '/cache/' . $v['base'] . '-' . $s['w'] . '.webp';
                if ($s['w'] >= 800 && $s['w'] <= self::VISION_MAX && in_array('webp', $s['f'], true) && is_file($f)) $path = $f;
            }
            if ($path) return [(string) file_get_contents($path), 'image/webp'];
            $path = $dir . '/' . $m['file'];
        } else {
            $path = (string) $image;
        }
        if (!is_file($path)) throw new AiException(__('Bilddatei nicht gefunden.'));
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (!$src) throw new AiException(__('Bild konnte nicht gelesen werden.'));
        $w = imagesx($src);
        $h = imagesy($src);
        $f = min(1, self::VISION_MAX / max($w, $h));
        $dst = imagecreatetruecolor(max(1, (int) round($w * $f)), max(1, (int) round($h * $f)));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        ob_start();
        imagejpeg($dst, null, 82);
        return [(string) ob_get_clean(), 'image/jpeg'];
    }

    // ================================================================== Zähler (ohne Inhalte)

    private static function usageDb(): \Core\Database
    {
        static $db = [];
        $k = site()->key;
        if (isset($db[$k])) return $db[$k];
        $dir = site()->storage('ai');
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $d = new \Core\Database(['driver' => 'sqlite', 'path' => $dir . '/usage.sqlite']);
        $d->pdo->exec('CREATE TABLE IF NOT EXISTS usage (day TEXT NOT NULL, cap TEXT NOT NULL, provider TEXT NOT NULL, model TEXT NOT NULL,
            calls INT NOT NULL DEFAULT 0, errors INT NOT NULL DEFAULT 0, tokens_in INT NOT NULL DEFAULT 0, tokens_out INT NOT NULL DEFAULT 0, ms INT NOT NULL DEFAULT 0,
            PRIMARY KEY (day, cap, provider, model))');
        $d->ensureColumns('usage', ['seconds' => 'INT NOT NULL DEFAULT 0']);   // Sekunden Audio (transcribe)
        return $db[$k] = $d;
    }

    /** Aufruf zählen (Website, Tag, Fähigkeit, Anbieter, Modell) – nie Inhalte */
    public static function track(string $cap, array $c, int $in, int $out, int $ms, bool $error, int $seconds = 0): void
    {
        try {
            // $seconds: Länge verarbeiteter Tonspuren (Fähigkeit „transcribe“)
            self::usageDb()->query('INSERT INTO usage (day, cap, provider, model, calls, errors, tokens_in, tokens_out, ms, seconds) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?)
                ON CONFLICT(day, cap, provider, model) DO UPDATE SET calls = calls + 1, errors = errors + excluded.errors,
                tokens_in = tokens_in + excluded.tokens_in, tokens_out = tokens_out + excluded.tokens_out, ms = ms + excluded.ms, seconds = seconds + excluded.seconds',
                [date('Y-m-d'), $cap, (string) $c['provider'], (string) $c['model'], $error ? 1 : 0, $in, $out, $ms, $seconds]);
        } catch (\Throwable $e) {
            error_log('[ai] usage: ' . $e->getMessage());
        }
    }

    /**
     * Nutzung der letzten $days Tage je Fähigkeit und Modell, mit Kostenschätzung (config 'prices', USD je 1 Mio. Tokens)
     * @return list<array{cap: string, provider: string, model: string, calls: int, errors: int, tokens_in: int, tokens_out: int, ms: int, cost: ?float}>
     */
    public static function usage(int $days = 30): array
    {
        $prices = self::config()['prices'];
        $rows = self::usageDb()->fetchAll('SELECT cap, provider, model, SUM(calls) calls, SUM(errors) errors, SUM(tokens_in) tokens_in, SUM(tokens_out) tokens_out, SUM(ms) ms, SUM(seconds) seconds
            FROM usage WHERE day >= ? GROUP BY cap, provider, model ORDER BY cap, calls DESC', [date('Y-m-d', strtotime('-' . max(1, $days) . ' days'))]);
        foreach ($rows as &$r) {
            $p = $prices[$r['model']] ?? null;
            $r['cost'] = is_array($p) ? round(((float) $r['tokens_in'] * (float) ($p[0] ?? 0) + (float) $r['tokens_out'] * (float) ($p[1] ?? 0)) / 1e6, 4) : null;
        }
        return $rows;
    }

    /**
     * Aufrufe dieser Website heute (Standard: Texte + Bilder, inkl. Fehler; ohne Suche) – für das Tageslimit des KI-Assistenten.
     * $caps: andere Zählnamen, z. B. ['chat'] für das Tageslimit des Besucher-Chats (Core\AI\VisitorChat)
     */
    public static function callsToday(array $caps = ['text', 'vision']): int
    {
        try {
            $caps = array_values(array_filter(array_map(fn($c) => preg_replace('~[^a-z_]~', '', (string) $c), $caps))) ?: ['text'];
            $in = implode(',', array_fill(0, count($caps), '?'));
            return (int) self::usageDb()->fetchValue("SELECT COALESCE(SUM(calls), 0) FROM usage WHERE day = ? AND cap IN ($in)", [date('Y-m-d'), ...$caps]);
        } catch (\Throwable) {
            return 0;
        }
    }

    // ================================================================== Ausfall-Pause („Circuit Breaker“)

    private static function pauseFile(array $cap): string
    {
        return ROOT . '/storage/cache/ai-pause-' . substr(md5(($cap['provider'] ?? '') . '|' . self::endpoint($cap) . '|' . ($cap['model'] ?? '')), 0, 12);
    }

    /** Anbieter hatte gerade einen Fehler → vorerst nicht fragen. $cap: capability() oder config() (dann: Embeddings) */
    public static function paused(?array $cap = null): bool
    {
        $f = self::pauseFile(self::capOf($cap));
        return is_file($f) && (time() - (int) @filemtime($f)) < self::PAUSE;
    }

    public static function failed(\Throwable $e, ?array $cap = null): void
    {
        $c = self::capOf($cap);
        @mkdir(ROOT . '/storage/cache', 0775, true);
        @touch(self::pauseFile($c));
        // Nur Fehlerart und Anbieter protokollieren – nie Inhalte
        error_log('[ai] ' . ($c['provider'] ?? '?') . '/' . ($c['model'] ?? '?') . ': ' . self::shortError($e));
    }

    public static function recovered(?array $cap = null): void
    {
        $f = self::pauseFile(self::capOf($cap));
        if (is_file($f)) @unlink($f);
    }

    private static function capOf(?array $x): array
    {
        if ($x === null) return self::capability('embed');
        return isset($x['cap']) ? $x : self::capability('embed', $x);
    }

    /** Fehlermeldung ohne Schlüssel und ohne Anfrageinhalt */
    public static function shortError(\Throwable $e): string
    {
        $m = (string) preg_replace('~(Bearer|key[=:])\s*\S+~i', '$1 ***', $e->getMessage());
        $name = (new \ReflectionClass($e))->getShortName();
        return mb_strimwidth(in_array($name, ['RuntimeException', 'AiException'], true) ? $m : $name . ': ' . $m, 0, 300, '…');
    }

    // ================================================================== Test & Gesundheit

    /**
     * Verbindung einer Fähigkeit prüfen (unabhängig vom Schalter der Website). $cfg: eigene Konfiguration (z. B. Installation)
     * @return array{ok: bool, cap: string, provider: string, model: string, endpoint: string, external: bool, dims: ?int, text: ?string, ms: ?int, error: ?string}
     */
    public static function test(string $cap = 'embed', ?array $cfg = null): array
    {
        $cfg ??= self::config();
        if ($cap === 'transcribe') return Transcriber::test($cfg);
        $c = self::capability($cap, $cfg);
        $out = ['ok' => false, 'cap' => $cap, 'provider' => $c['provider'], 'model' => $c['model'], 'endpoint' => $c['endpoint'],
            'external' => $c['external'], 'dims' => null, 'text' => null, 'ms' => null, 'error' => null];
        if (!$c['configured']) {
            $out['error'] = $cfg['configured'] ? __('Für „{cap}“ ist kein Modell konfiguriert.', ['cap' => __(self::CAPS[$cap] ?? $cap)]) : __('Kein KI-Anbieter konfiguriert.');
            return $out;
        }
        $t = microtime(true);
        try {
            if ($cap === 'embed') {
                $v = self::embedder($cfg, max($cfg['timeout'], 15.0))->embed(['Öffnungszeiten und Kontakt'], 'query');
                $out['dims'] = count($v[0] ?? []);
                $out['ok'] = $out['dims'] > 0;
            } else {
                $o = ['force' => true, 'max_tokens' => 20, 'temperature' => 0.0, 'timeout' => max($cfg['text_timeout'], 30.0)];
                if ($cap === 'text') {
                    $r = self::run('text', $c, new MessageBag(Message::ofUser('Antworte nur mit dem Wort „bereit“.')), $o);
                } else {
                    $img = imagecreatetruecolor(64, 64);
                    imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 40));
                    ob_start();
                    imagejpeg($img, null, 85);
                    $r = self::run('vision', $c, new MessageBag(Message::ofUser('Welche Farbe hat dieses Bild? Antworte mit einem Wort.', new Image((string) ob_get_clean(), 'image/jpeg'))), $o);
                }
                $out['text'] = mb_strimwidth($r['text'], 0, 80, '…');
                $out['ok'] = $r['text'] !== '';
            }
            self::recovered($c);
        } catch (\Throwable $e) {
            $out['error'] = self::shortError($e);
        }
        $out['ms'] = (int) round((microtime(true) - $t) * 1000);
        return $out;
    }

    /** Prüfungen für `health`: nur konfigurierte und auf dieser Website eingeschaltete Fähigkeiten */
    public static function health(): array
    {
        $out = [];
        foreach (array_keys(self::CAPS) as $cap) {
            if (!self::enabled($cap)) continue;
            if ($cap === 'transcribe') {
                // Transkription nicht bei jedem Deploy laufen lassen: nur Programme und Modell prüfen
                $tc = self::config()['transcribe'];
                $out['KI transcribe eingerichtet (' . $tc['backend'] . ', ' . self::capability('transcribe')['model'] . ')'] = !Transcriber::check($tc);
                continue;
            }
            $t = self::test($cap);
            $out['KI ' . $cap . ' erreichbar (' . $t['provider'] . ', ' . $t['model'] . ')'] = $t['ok'];
        }
        return $out;
    }

    // ================================================================== Start

    /** Beim Start: Recht „ai.use“ einmalig der Redaktion geben; semantische Suche für die Wissensdatenbank einsetzen */
    public static function boot(): void
    {
        self::ensureRoles();
        $cfg = self::installationConfig();
        if (self::capability('embed', $cfg)['configured'] && $cfg['kb']) {
            \Core\Support\Search::use(new \Core\Search\SupportSemantic($cfg));
        }
    }

    /** Bestehende Rolle „Redaktion“ bekommt „ai.use“ einmalig (danach frei änderbar; Merker sys.ai_roles) */
    private static function ensureRoles(): void
    {
        try {
            if ((int) app()->settings->get('sys.ai_roles', 0) >= 1) return;
            $r = app()->db->fetch("SELECT permissions_json FROM roles WHERE rkey = 'editor'");
            if ($r) {
                $p = json_decode((string) $r['permissions_json'], true) ?: [];
                if (!in_array('*', $p, true) && !in_array('ai.use', $p, true)) {
                    $p[] = 'ai.use';
                    app()->db->update('roles', ['permissions_json' => json_encode(array_values($p))], "rkey = 'editor'", []);
                }
            }
            app()->settings->set('sys.ai_roles', 1);
        } catch (\Throwable $e) {
            error_log('[ai] roles: ' . $e->getMessage());
        }
    }
}
