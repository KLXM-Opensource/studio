<?php
declare(strict_types=1);

namespace Core\AI;

use Core\Fields;
use Core\Lang;
use Core\PagePicker;
use Core\Pages;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Selbsttest „ai:selftest“ – ohne Netz und ohne gespeicherte Konfiguration zu ändern:
 *   - Verbindungen/Verwendung (Core\AI\Profiles): Umwandlung des alten Formats, Zusammenführen der Ebenen, Zweck → Verbindung,
 *     Besucher-Chat erbt „Texte“, Ersatz-Verbindung, Sperren aus Konfigurationsdateien, Arten ohne passende Fähigkeit
 *   - Prüfen und Modelle (Core\AI\Probe) mit vorgefertigten Antworten (Symfony MockHttpClient): Ollama mit Token, OpenAI,
 *     Mistral, Anthropic (Liste bzw. kuratiert), Fehlerarten, gesperrte Adressen, kein Schlüssel in Ergebnissen
 *   - Feldtyp „pages“ (Core\PagePicker): Werte hin und zurück, Unterseiten, Pfade (in einer Transaktion, wird zurückgerollt)
 */
final class SelfTest
{
    private int $ok = 0;
    private array $fails = [];

    public static function run(): array
    {
        $t = new self();
        $t->profiles();
        $t->probe();
        $t->pages();
        return ['ok' => $t->ok, 'fails' => $t->fails];
    }

    private function eq(string $what, mixed $got, mixed $want): void
    {
        if ($got === $want) { $this->ok++; return; }
        $this->fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
    }

    // ------------------------------------------------------------------ Profile

    private function profiles(): void
    {
        // Altes Format (eine Datei storage/ai/config.json vor Format 2)
        $legacy = ['provider' => 'ollama', 'base_url' => 'http://127.0.0.1:11434', 'api_key' => 'geheim-1',
            'models' => ['text' => 'gemma3', 'embed' => 'bge-m3'], 'embeddings' => 'alt-embed', 'model' => 'alt-text',
            'providers' => ['vision' => ['provider' => 'mistral', 'api_key' => 'geheim-2', 'model' => 'pixtral-12b-latest']],
            'transcribe' => ['backend' => 'whisper_cpp', 'model_path' => 'storage/ai/models/ggml-test.bin', 'language' => 'de', 'threads' => 2],
            'timeout' => 3];
        $up = Profiles::upgrade($legacy);
        $this->eq('Umwandlung: Verbindung „standard“', $up['profiles']['standard']['type'] ?? null, 'ollama');
        $this->eq('Umwandlung: Schlüssel bleibt', $up['profiles']['standard']['api_key'] ?? null, 'geheim-1');
        $this->eq('Umwandlung: Zuordnung Texte', $up['assign']['text'] ?? null, ['profile' => 'standard', 'model' => 'gemma3']);
        $this->eq('Umwandlung: models[] vor altem Schlüssel embeddings', $up['assign']['embed']['model'] ?? null, 'bge-m3');
        $this->eq('Umwandlung: anderer Anbieter je Fähigkeit', [$up['assign']['vision']['profile'] ?? null, $up['profiles']['standard_vision']['type'] ?? null], ['standard_vision', 'mistral']);
        $this->eq('Umwandlung: whisper.cpp', [$up['profiles']['whisper']['type'] ?? null, $up['assign']['transcribe']['profile'] ?? null], ['whisper_cpp', 'whisper']);
        $this->eq('Umwandlung: keine alten Schlüssel mehr', array_intersect(array_keys($up), ['provider', 'models', 'providers', 'transcribe', 'api_key']), []);
        $this->eq('Umwandlung: allgemeine Werte bleiben', $up['timeout'] ?? null, 3);
        $this->eq('Umwandlung idempotent', Profiles::upgrade($up), $up);

        $cfg = Ai::config($legacy);
        $this->eq('Wirksam: Texte über Ollama', [$cfg['providers']['text']['provider'], $cfg['models']['text'], $cfg['providers']['text']['profile']], ['ollama', 'gemma3', 'standard']);
        $this->eq('Wirksam: Bilder über Mistral mit eigenem Schlüssel', [$cfg['providers']['vision']['provider'], $cfg['providers']['vision']['api_key'], $cfg['models']['vision']], ['mistral', 'geheim-2', 'pixtral-12b-latest']);
        $this->eq('Wirksam: Besucher-Chat erbt Texte', [$cfg['providers']['chat']['provider'], $cfg['models']['chat']], ['ollama', 'gemma3']);
        $this->eq('Wirksam: Einzelwerte wie bisher', [$cfg['provider'], $cfg['base_url'], $cfg['configured']], ['ollama', 'http://127.0.0.1:11434', true]);
        $this->eq('Wirksam: Transkription', [$cfg['transcribe']['backend'], $cfg['transcribe']['language'], $cfg['transcribe']['threads']], ['whisper_cpp', 'de', 2]);
        $this->eq('Wirksam: Ollama lokal = nicht extern', Ai::capability('embed', $cfg)['external'], false);
        $this->eq('Wirksam: Ai::capability kennt die Verbindung', Ai::capability('vision', $cfg)['label'], 'Standard (Bilder (Alt-Texte))');

        // Format 2: mehrere Verbindungen, eigener Chat, Ersatz, Anthropic ohne Embeddings
        $v2 = ['version' => 2,
            'profiles' => ['buero' => ['label' => 'Ollama Büro', 'type' => 'ollama', 'base_url' => 'http://10.0.0.5:11434'],
                'eu' => ['label' => 'Mistral EU', 'type' => 'mistral', 'api_key' => 'k-eu', 'timeout' => 45],
                'claude' => ['label' => 'Claude', 'type' => 'anthropic', 'api_key' => 'k-a'],
                'gen' => ['label' => 'vLLM', 'type' => 'generic', 'base_url' => 'https://llm.example.org/v1']],
            'assign' => ['text' => ['profile' => 'buero', 'model' => 'gemma3', 'fallback' => 'eu'],
                'chat' => ['profile' => 'claude', 'model' => 'claude-haiku-4-5'],
                'embed' => ['profile' => 'claude', 'model' => 'x'],
                'vision' => ['profile' => 'eu', 'model' => '']]];
        $c2 = Ai::config($v2);
        $this->eq('Format 2: Chat mit eigener Verbindung', [$c2['providers']['chat']['provider'], $c2['models']['chat']], ['anthropic', 'claude-haiku-4-5']);
        $this->eq('Format 2: Anthropic kann keine Embeddings', $c2['providers']['embed']['provider'], '');
        $this->eq('Format 2: Vorgabe-Modell je Art', $c2['models']['vision'], 'pixtral-12b-latest');
        $this->eq('Format 2: Ersatz-Verbindung mit Vorgabe-Modell', [$c2['providers']['text']['fallback']['provider'] ?? null, $c2['providers']['text']['fallback']['model'] ?? null], ['mistral', 'gemma3']);
        $this->eq('Format 2: Zeitlimit der Verbindung', Ai::capability('vision', $c2)['timeout'], 45.0);
        $this->eq('Format 2: OpenAI-kompatibel ohne /v1', Profiles::profiles($v2)['gen']['base_url'], 'https://llm.example.org');
        $this->eq('Format 2: Anthropic extern', Ai::capability('chat', $c2)['external'], true);
        $this->eq('Format 2: abgeschaltet', Ai::config($v2 + ['enabled' => false])['configured'], false);

        // Ebenen: Datei mit altem Format überschreibt nur, was sie setzt
        $merged = Profiles::merge($v2, ['models' => ['text' => 'qwen3']]);
        $this->eq('Ebenen: nur das Modell aus der Datei', [Profiles::resolve($merged, 'text')['model'] ?? null, Profiles::resolve($merged, 'text')['profile'] ?? null], ['qwen3', 'buero']);
        $merged = Profiles::merge($v2, ['provider' => 'openai', 'api_key' => 'datei']);
        $this->eq('Ebenen: alter Anbieter in der Datei gilt für alle Zwecke', [Profiles::resolve($merged, 'text')['provider'] ?? null, Profiles::resolve($merged, 'vision')['provider'] ?? null], ['openai', 'openai']);
        $locks = Profiles::locks(['provider' => 'mistral', 'models' => ['embed' => 'mistral-embed'], 'timeout' => 4]);
        $this->eq('Sperren: Verbindung aus der Datei', [$locks['profiles']['standard']['type'] ?? false, $locks['assign']['text']['profile'] ?? false, $locks['assign']['embed']['model'] ?? false, $locks['globals']['timeout'] ?? false], [true, true, true, true]);
        $this->eq('Sperren: nichts gesetzt', Profiles::locks([]), ['profiles' => [], 'assign' => [], 'globals' => []]);
        $this->eq('Kennung aus Bezeichnung', Profiles::slug('Ollama Büro (2. OG)'), 'ollama_buero_2_og');
        $this->eq('Adresse: http extern verboten', Profiles::urlError('http://llm.example.org') !== null, true);
        $this->eq('Adresse: http lokal erlaubt', Profiles::urlError('http://127.0.0.1:11434'), null);
    }

    // ------------------------------------------------------------------ Prüfen und Modelle (ohne Netz)

    private function probe(): void
    {
        $tags = ['models' => [
            ['name' => 'gemma3:12b', 'size' => 8149190253, 'details' => ['family' => 'gemma3', 'parameter_size' => '12.2B', 'quantization_level' => 'Q4_K_M']],
            ['name' => 'bge-m3:latest', 'size' => 1157672605, 'details' => ['family' => 'bert', 'parameter_size' => '566.70M', 'quantization_level' => 'F16']],
            ['name' => 'qwen3:8b', 'size' => 5225376047, 'details' => ['family' => 'qwen3', 'parameter_size' => '8.2B', 'quantization_level' => 'Q4_K_M']],
        ]];
        $parsed = Probe::parseOllamaTags($tags);
        $this->eq('Ollama: Anzahl', count($parsed), 3);
        $this->eq('Ollama: Größe, Parameter, Quantisierung', [$parsed[0]['size'], $parsed[0]['params'], $parsed[0]['quant']], [8149190253, '12.2B', 'Q4_K_M']);
        $this->eq('Ollama: Embedding erkannt (Familie bert)', $parsed[1]['caps'], ['embed']);
        $this->eq('Ollama: Vision erkannt (gemma3)', $parsed[0]['caps'], ['chat', 'vision']);
        $this->eq('Ollama: /api/show', Probe::parseOllamaShow(['model_info' => ['general.architecture' => 'qwen3', 'qwen3.context_length' => 40960], 'capabilities' => ['completion', 'tools', 'thinking']]), [40960, ['chat']]);
        $mistral = Probe::parseOpenAi(['object' => 'list', 'data' => [
            ['id' => 'mistral-small-latest', 'max_context_length' => 131072, 'capabilities' => ['completion_chat' => true, 'vision' => true]],
            ['id' => 'mistral-embed', 'max_context_length' => 8192, 'capabilities' => ['completion_chat' => false]],
            ['id' => 'voxtral-mini-latest', 'capabilities' => ['completion_chat' => true, 'audio' => true]]]], 'mistral');
        $this->eq('Mistral: Fähigkeiten und Kontext', [$mistral[0]['caps'], $mistral[0]['context'], $mistral[1]['caps'], $mistral[2]['caps']], [['chat', 'vision'], 131072, ['embed'], ['chat', 'audio']]);
        $openai = Probe::parseOpenAi(['data' => [['id' => 'gpt-4o-mini', 'owned_by' => 'system'], ['id' => 'text-embedding-3-small'], ['id' => 'whisper-1'], ['id' => 'dall-e-3']]]);
        $this->eq('OpenAI: Fähigkeiten geraten', array_column($openai, 'caps'), [['chat', 'vision'], ['embed'], ['audio'], []]);

        $token = 'tok-' . bin2hex(random_bytes(4));
        $ollama = function (string $method, string $url, array $o) use ($tags, $token): MockResponse {
            $auth = '';
            foreach ($o['headers'] ?? [] as $h) if (stripos((string) $h, 'authorization:') === 0) $auth = trim(substr((string) $h, 14));
            $path = (string) parse_url($url, PHP_URL_PATH);
            if ($path === '/api/version') return new MockResponse(json_encode(['version' => '0.12.3']), ['http_code' => 200]);
            if ($auth !== 'Bearer ' . $token) return new MockResponse('{"error":"unauthorized"}', ['http_code' => 401]);
            if ($path === '/api/tags') return new MockResponse(json_encode($tags), ['http_code' => 200]);
            if ($path === '/api/show') return new MockResponse(json_encode(['model_info' => ['x.context_length' => 8192], 'capabilities' => ['completion', 'vision']]), ['http_code' => 200]);
            return new MockResponse('', ['http_code' => 404]);
        };
        $p = ['type' => 'ollama', 'base_url' => 'http://127.0.0.1:11434', 'api_key' => $token];
        $r = Probe::check($p, new MockHttpClient($ollama));
        $this->eq('Prüfen Ollama: OK mit Version und Anzahl', [$r['status'], $r['version'], $r['count']], ['ok', '0.12.3', 3]);
        $this->eq('Prüfen Ollama: Schlüssel nie im Ergebnis', str_contains(json_encode($r), $token), false);
        $r = Probe::check(['api_key' => 'falsch'] + $p, new MockHttpClient($ollama));
        $this->eq('Prüfen Ollama: falsches Token', $r['status'], 'auth');
        $m = Probe::models($p, new MockHttpClient($ollama));
        $this->eq('Modelle Ollama: sortiert, Kontext aus /api/show', [array_column($m['models'], 'name'), $m['models'][0]['context']], [['bge-m3:latest', 'gemma3:12b', 'qwen3:8b'], 8192]);
        $r = Probe::check($p, new MockHttpClient(fn() => new MockResponse('', ['error' => 'Connection refused'])));
        $this->eq('Prüfen: nicht erreichbar', $r['status'], 'unreachable');
        $r = Probe::check($p, new MockHttpClient(fn() => new MockResponse('<html>nginx</html>', ['http_code' => 404])));
        $this->eq('Prüfen: falsche Art', $r['status'], 'wrong_type');
        $r = Probe::check($p, new MockHttpClient(fn() => new MockResponse('<html>ok</html>', ['http_code' => 200])));
        $this->eq('Prüfen: antwortet ohne Ollama-JSON', $r['status'], 'wrong_type');

        $seen = [];
        $oa = function (string $method, string $url, array $o) use (&$seen): MockResponse {
            $seen[] = $url;
            return new MockResponse(json_encode(['data' => [['id' => 'gpt-4o-mini'], ['id' => 'text-embedding-3-small']]]), ['http_code' => 200]);
        };
        $r = Probe::check(['type' => 'openai', 'api_key' => 'sk-test', 'region' => 'EU'], new MockHttpClient($oa));
        $this->eq('Prüfen OpenAI (EU): Adresse und Anzahl', [$r['status'], $r['count'], $seen[0] ?? ''], ['ok', 2, 'https://eu.api.openai.com/v1/models']);
        $seen = [];
        Probe::models(['type' => 'generic', 'base_url' => 'https://llm.example.org/v1', 'api_key' => 'x'], new MockHttpClient($oa));
        $this->eq('OpenAI-kompatibel: /v1 nicht doppelt', $seen[0] ?? '', 'https://llm.example.org/v1/models');
        $r = Probe::models(['type' => 'anthropic', 'api_key' => 'x'], new MockHttpClient(fn() => new MockResponse('', ['http_code' => 404])));
        $this->eq('Anthropic ohne Liste: kuratiert', [$r['ok'], $r['curated'], count($r['models'])], [true, true, count(Probe::ANTHROPIC_CURATED)]);
        $r = Probe::models(['type' => 'anthropic', 'api_key' => 'x'], new MockHttpClient(fn() => new MockResponse(json_encode(['data' => [['id' => 'claude-sonnet-4-5', 'display_name' => 'Claude Sonnet 4.5']], 'has_more' => false]), ['http_code' => 200])));
        $this->eq('Anthropic: Liste', [$r['models'][0]['name'] ?? '', $r['models'][0]['caps'] ?? []], ['claude-sonnet-4-5', ['chat', 'vision']]);
        $r = Probe::check(['type' => 'mistral', 'api_key' => 'x'], new MockHttpClient(fn() => new MockResponse('{"message":"Unauthorized"}', ['http_code' => 401])));
        $this->eq('Prüfen Mistral: Schlüssel falsch', $r['status'], 'auth');

        // Gesperrte Adressen (vor jeder Anfrage)
        $never = new MockHttpClient(function () { throw new \LogicException('darf nicht anfragen'); });
        $this->eq('Gesperrt: Metadaten-Adresse', Probe::check(['type' => 'ollama', 'base_url' => 'http://169.254.169.254'], $never)['status'], 'blocked');
        $this->eq('Gesperrt: eigenes Netz für Mistral', Probe::check(['type' => 'mistral', 'base_url' => 'https://10.1.2.3'], $never)['status'], 'blocked');
        $this->eq('Gesperrt: http zu öffentlichem Host', Probe::check(['type' => 'generic', 'base_url' => 'http://93.184.215.14'], $never)['status'], 'blocked');
        $this->eq('Gesperrt: Zugangsdaten in der Adresse', Probe::check(['type' => 'ollama', 'base_url' => 'http://u:p@127.0.0.1:11434'], $never)['status'], 'blocked');
        $this->eq('Erlaubt: Ollama im eigenen Netz', Probe::check(['type' => 'ollama', 'base_url' => 'http://192.168.1.20:11434', 'api_key' => $token], new MockHttpClient($ollama))['status'], 'ok');
    }

    // ------------------------------------------------------------------ Feldtyp „pages“

    private function pages(): void
    {
        $f = ['name' => 'x', 'type' => 'pages'];
        $this->eq('Seiten: IDs bereinigen', PagePicker::clean($f, ['', '12', '12*', 'abc', '0', '7*', '12', '3; DROP']), ['12', '12*', '7*']);
        $this->eq('Seiten: ohne Unterseiten-Option', PagePicker::clean($f + ['subpages' => false], ['5*', '6']), ['5', '6']);
        $this->eq('Seiten: alter Wert der Mehrfachauswahl bleibt gültig', PagePicker::clean($f, ['3', '9']), ['3', '9']);
        $pf = ['name' => 'y', 'type' => 'pages', 'store' => 'paths'];
        $this->eq('Pfade: Liste → Zeilen', PagePicker::clean($pf, ['/impressum', '/blog/*', 'kein-pfad', '/a b', '/impressum']), "/impressum\n/blog/*");
        $this->eq('Pfade: Text → Zeilen', PagePicker::clean($pf, "/a\r\n/b/*\n"), "/a\n/b/*");
        [$v, $e] = Fields::sanitize([$f, $pf], ['x' => ['', '4*', '4'], 'y' => ['', '/x/*']]);
        $this->eq('Fields::sanitize Rundlauf', [$v, $e], [['x' => ['4*', '4'], 'y' => '/x/*'], []]);
        $this->eq('Fields: Leerwert je Format', [Fields::emptyValue($f), Fields::emptyValue($pf)], [[], '']);

        $pdo = app()->db->pdo;
        $pdo->beginTransaction();
        try {
            $base = ['lang' => Lang::default(), 'sort' => 9100, 'status' => 'published'];
            $parent = Pages::create(['slug' => 'pgf-selbsttest', 'title' => 'Seitenauswahl Eltern'] + $base);
            $child = Pages::create(['slug' => 'kind', 'title' => 'Seitenauswahl Kind', 'parent_id' => $parent] + $base);
            $grand = Pages::create(['slug' => 'enkel', 'title' => 'Seitenauswahl Enkel', 'parent_id' => $child] + $base);
            $g = Pages::find($grand);
            $this->eq('Treffer: genau diese Seite', PagePicker::matches([(string) $grand], $g), true);
            $this->eq('Treffer: Elternseite ohne Unterseiten', PagePicker::matches([(string) $parent], $g), false);
            $this->eq('Treffer: Elternseite mit Unterseiten', PagePicker::matches([$parent . '*'], $g), true);
            $this->eq('Treffer: Seite ohne Auswahl', PagePicker::matches([], $g), false);
            $items = PagePicker::items($f, [$child . '*', '999999']);
            $this->eq('Chips: Titel, Pfad im Baum, Unterseiten', [$items[0]['label'], $items[0]['trail'], $items[0]['sub']], ['Seitenauswahl Kind', 'Seitenauswahl Eltern', true]);
            $this->eq('Chips: fehlende Seite markiert', $items[1]['missing'], true);
            $path = PagePicker::pathOf(Pages::find($child));
            $this->eq('Pfad einer Seite', $path, Lang::prefix(Lang::default()) . '/pgf-selbsttest/kind');
            $pi = PagePicker::items($pf, $path . "/*\n/eigener/pfad");
            $this->eq('Pfade: Seite erkannt, eigener Pfad bleibt', [$pi[0]['label'], $pi[0]['sub'], $pi[1]['custom']], ['Seitenauswahl Kind', true, true]);
            $this->eq('Glossar versteht die Pfade', \Core\Glossary\Glossary::excluded($path . '/enkel', $path . '/*'), true);
            $html = Fields::renderField($f + ['label' => 'Test'], [$child . '*'], [], 'f');
            $this->eq('Ausgabe: Chip mit verstecktem Wert', str_contains($html, 'name="f[x][]" value="' . $child . '*"'), true);
            $tree = \Core\Links::tree(Lang::default());
            $flat = [];
            $walk = function (array $n) use (&$walk, &$flat): void { foreach ($n as $x) { $flat[$x['id']] = $x['path'] ?? null; $walk($x['children']); } };
            $walk($tree['nodes']);
            $this->eq('Baum liefert Pfade für die Auswahl', $flat[$child] ?? null, $path);
        } finally {
            $pdo->rollBack();
        }
    }
}
