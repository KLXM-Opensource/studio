<?php
declare(strict_types=1);

namespace Core\AI;

/**
 * Verbindungen (Profile) und Verwendung der KI – Konfigurationsformat 2.
 *
 * Statt eines einzigen Anbieters gibt es beliebig viele benannte Verbindungen („Ollama Büro“, „Mistral EU“, „OpenAI“) und je
 * Verwendungszweck eine Zuordnung Verbindung + Modell (optional eine Ersatz-Verbindung, falls die erste ausfällt):
 *
 *   'ai' => [
 *     'version'  => 2,
 *     'profiles' => [
 *       'ollama_buero' => ['label' => 'Ollama Büro', 'type' => 'ollama', 'base_url' => 'http://10.0.0.5:11434', 'api_key' => ''],
 *       'mistral_eu'   => ['label' => 'Mistral EU', 'type' => 'mistral', 'api_key' => '…', 'timeout' => 60],
 *       'whisper'      => ['label' => 'whisper.cpp', 'type' => 'whisper_cpp', 'model_path' => 'storage/ai/models/ggml-large-v3-turbo-q5_0.bin'],
 *     ],
 *     'assign' => [
 *       'text'   => ['profile' => 'ollama_buero', 'model' => 'gemma3', 'fallback' => 'mistral_eu', 'fallback_model' => 'mistral-small-latest'],
 *       'chat'   => ['profile' => 'mistral_eu', 'model' => 'mistral-small-latest'],   // leer = wie „text“
 *       'embed'  => ['profile' => 'ollama_buero', 'model' => 'bge-m3'],
 *       'vision' => ['profile' => 'ollama_buero', 'model' => 'gemma3'],
 *       'transcribe' => ['profile' => 'whisper', 'language' => 'auto'],
 *     ],
 *     'timeout' => 2.5, 'text_timeout' => 60, 'index_timeout' => 60, …       // wie bisher
 *   ]
 *
 * Ebenen wie bisher: storage/ai/config.json (Verwaltung, nur Agentur) ← config/config.local.php ← config/sites/{key}.php.
 * Jede Ebene darf noch das alte Format nutzen (provider, base_url, api_key, region, models, providers, transcribe) – upgrade()
 * wandelt es beim Lesen um: der alte Anbieter wird zur Verbindung „standard“, alle Zwecke zeigen darauf. Geschrieben wird nur
 * Format 2. Werte aus den Konfigurationsdateien haben Vorrang und sind in der Verwaltung je Verbindung bzw. Zuordnung gesperrt
 * (locks()). Verbindungen gelten für die ganze Installation; eine Website kann in config/sites/{key}.php Zuordnungen überschreiben.
 */
final class Profiles
{
    /** Verwendungszwecke → Bezeichnung (Reihenfolge = Anzeige) */
    public const PURPOSES = [
        'text' => 'Texte & Redaktion',
        'chat' => 'Besucher-Chat',
        'embed' => 'Embeddings (Suche)',
        'vision' => 'Bilder (Alt-Texte)',
        'transcribe' => 'Sprache → Text (Untertitel)',
    ];
    /** Kurze Erklärung je Zweck */
    public const PURPOSE_HELP = [
        'text' => 'KI-Assistent, Texte schreiben und übersetzen, SEO-Vorschläge',
        'chat' => 'Antworten auf der Website – leer = wie „Texte & Redaktion“',
        'embed' => 'Semantische Suche und Wissensdatenbank',
        'vision' => 'Alt-Texte für Bilder vorschlagen',
        'transcribe' => 'Untertitel und Transkripte für Videos und Audio',
    ];
    /** Arten von Verbindungen → Bezeichnung */
    public const TYPES = [
        'ollama' => 'Ollama (lokal/eigener Server)',
        'mistral' => 'Mistral AI (EU)',
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic (Claude)',
        'generic' => 'OpenAI-kompatibel (z. B. vLLM, LM Studio, IONOS, Scaleway, STACKIT)',
        'whisper_cpp' => 'whisper.cpp (lokal, nur Sprache → Text)',
        'fake' => 'Test-Anbieter (ohne KI)',
    ];
    /** Welche Zwecke eine Art bedienen kann */
    public const SUPPORTS = [
        'ollama' => ['text', 'chat', 'embed', 'vision'],
        'mistral' => ['text', 'chat', 'embed', 'vision', 'transcribe'],
        'openai' => ['text', 'chat', 'embed', 'vision', 'transcribe'],
        'anthropic' => ['text', 'chat', 'vision'],
        'generic' => ['text', 'chat', 'embed', 'vision', 'transcribe'],
        'whisper_cpp' => ['transcribe'],
        'fake' => ['text', 'chat', 'embed', 'vision'],
    ];
    /** Felder einer Verbindung (alles andere wird verworfen) */
    private const PROFILE_KEYS = ['label', 'type', 'base_url', 'api_key', 'region', 'timeout', 'inherit',
        'whisper_bin', 'model_path', 'ffmpeg', 'threads'];
    private const ASSIGN_KEYS = ['profile', 'model', 'fallback', 'fallback_model', 'language'];
    /** Schlüssel des alten Formats (Format 1) */
    private const LEGACY = ['provider', 'base_url', 'api_key', 'region', 'models', 'embeddings', 'model', 'providers', 'transcribe'];

    // ================================================================== Format 1 → 2

    /**
     * Eine Konfigurations-Ebene ins Format 2 bringen. Es entstehen nur Schlüssel, die die Ebene wirklich setzt – so überschreibt
     * eine Datei mit nur 'models' => ['text' => …] nicht die Verbindung der Ebene darunter.
     */
    public static function upgrade(array $c): array
    {
        $out = array_diff_key($c, array_flip(self::LEGACY));
        $has = fn(string $k) => array_key_exists($k, $c);
        $profiles = [];
        $assign = [];
        // Alter Hauptanbieter → Verbindung „standard“
        $std = [];
        if ($has('provider')) $std['type'] = strtolower(trim((string) $c['provider']));
        foreach (['base_url', 'api_key', 'region'] as $k) if ($has($k)) $std[$k] = $c[$k];
        if ($std) $profiles['standard'] = $std;
        if ($has('provider')) foreach (['text', 'embed', 'vision'] as $p) $assign[$p]['profile'] = trim((string) $c['provider']) === '' ? '' : 'standard';
        // Modelle: models[cap], ältere Schlüssel 'embeddings' und 'model'
        $models = (array) ($c['models'] ?? []);
        if ($has('embeddings') && !isset($models['embed'])) $models['embed'] = $c['embeddings'];
        if ($has('model') && !isset($models['text'])) $models['text'] = $c['model'];
        foreach ($models as $p => $m) {
            if (in_array($p, ['text', 'embed', 'vision'], true) && is_scalar($m)) $assign[$p]['model'] = trim((string) $m);
        }
        // Anderer Anbieter je Fähigkeit → eigene Verbindung „standard_{cap}“ (fehlende Angaben erbt sie von „standard“)
        foreach ((array) ($c['providers'] ?? []) as $p => $o) {
            if (!in_array($p, ['text', 'embed', 'vision'], true) || !is_array($o) || !$o) continue;
            $prof = ['inherit' => 'standard', 'label' => 'Standard (' . self::PURPOSES[$p] . ')'];
            if (isset($o['provider'])) $prof['type'] = strtolower(trim((string) $o['provider']));
            foreach (['base_url', 'api_key', 'region'] as $k) if (array_key_exists($k, $o)) $prof[$k] = $o[$k];
            // Anderer Anbieter ohne eigene Adresse/Schlüssel: nicht die Werte von „standard“ erben (die gehören zu einem anderen Dienst)
            if (isset($prof['type']) && $prof['type'] !== strtolower(trim((string) ($c['provider'] ?? '')))) unset($prof['inherit']);
            $profiles['standard_' . $p] = $prof;
            $assign[$p]['profile'] = 'standard_' . $p;
            if (isset($o['model'])) $assign[$p]['model'] = trim((string) $o['model']);
        }
        // Sprache → Text: whisper.cpp → Verbindung „whisper“, OpenAI-kompatibel → Verbindung „transcribe“
        if (is_array($c['transcribe'] ?? null) && $c['transcribe']) {
            $t = $c['transcribe'];
            $backend = (string) ($t['backend'] ?? '');
            $w = array_intersect_key($t, array_flip(['whisper_bin', 'model_path', 'ffmpeg', 'threads', 'timeout']));
            if ($backend === 'whisper_cpp' || array_filter($w, fn($v) => $v !== '' && $v !== null)) {
                $profiles['whisper'] = ['type' => 'whisper_cpp', 'label' => 'whisper.cpp'] + $w;
            }
            $o = array_intersect_key($t, array_flip(['base_url', 'api_key']));
            if ($backend === 'openai' || array_filter($o, fn($v) => $v !== '' && $v !== null)) {
                $base = rtrim(trim((string) ($o['base_url'] ?? '')), '/');
                $profiles['transcribe'] = ['type' => $base === '' || str_contains($base, 'api.openai.com') ? 'openai' : 'generic',
                    'label' => 'Sprache → Text'] + ($base !== '' && !str_contains($base, 'api.openai.com') ? ['base_url' => $base] : []) + array_intersect_key($o, ['api_key' => 1]);
                // Zeitlimit von whisper.cpp gilt auch hier (lange Tonspuren)
            }
            if (array_key_exists('backend', $t)) {
                $assign['transcribe']['profile'] = match ($backend) { 'whisper_cpp' => 'whisper', 'openai' => 'transcribe', default => '' };
            }
            if (isset($t['model'])) $assign['transcribe']['model'] = trim((string) $t['model']);
            if (isset($t['language'])) $assign['transcribe']['language'] = (string) $t['language'];
            if (isset($t['timeout']) && $backend === 'openai' && isset($profiles['transcribe'])) $profiles['transcribe']['timeout'] = $t['timeout'];
        }
        if ($profiles) $out['profiles'] = array_replace($profiles, (array) ($out['profiles'] ?? []));
        if ($assign) $out['assign'] = array_replace_recursive($assign, (array) ($out['assign'] ?? []));
        return $out;
    }

    /** Mehrere Ebenen (jeweils altes oder neues Format) zusammenführen – spätere überschreiben frühere */
    public static function merge(array ...$layers): array
    {
        $out = [];
        foreach ($layers as $l) $out = array_replace_recursive($out, self::upgrade($l));
        return $out;
    }

    // ================================================================== Wirksame Werte

    /** Verbindungs-Kennung: Kleinbuchstaben, Ziffern, _ (aus der Bezeichnung) */
    public static function slug(string $label): string
    {
        $s = strtolower(strtr(trim($label), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss']));
        $s = trim((string) preg_replace('~[^a-z0-9]+~', '_', $s), '_');
        return substr($s !== '' ? $s : 'verbindung', 0, 40);
    }

    /**
     * Wirksame Verbindungen (Format 2, zusammengeführt): je ID label, type, base_url (Vorgabe je Art), api_key, region,
     * timeout (null = allgemein), Pfade für whisper.cpp; ungültige Arten werden zu '' (Verbindung unbrauchbar).
     */
    public static function profiles(array $raw): array
    {
        $src = (array) ($raw['profiles'] ?? []);
        $out = [];
        foreach ($src as $id => $p) {
            if (!is_array($p) || !preg_match('~^[a-z0-9_]{1,40}$~', (string) $id)) continue;
            $p = array_intersect_key($p, array_flip(self::PROFILE_KEYS));
            // „inherit“: fehlende Angaben aus einer anderen Verbindung (Format 1: anderer Anbieter je Fähigkeit)
            if (!empty($p['inherit']) && is_array($src[$p['inherit']] ?? null) && $p['inherit'] !== $id) {
                $p += array_intersect_key($src[$p['inherit']], array_flip(['type', 'base_url', 'api_key', 'region', 'timeout']));
            }
            $out[(string) $id] = self::normalize((string) $id, $p);
        }
        return $out;
    }

    public static function normalize(string $id, array $p): array
    {
        $type = strtolower(trim((string) ($p['type'] ?? '')));
        $type = isset(self::TYPES[$type]) ? $type : '';
        $base = rtrim(trim((string) ($p['base_url'] ?? '')), '/');
        if ($type === 'generic') $base = (string) preg_replace('~/v1$~', '', $base);   // Plattform hängt /v1/… selbst an
        if ($type === 'ollama' && $base === '') $base = 'http://127.0.0.1:11434';
        $timeout = $p['timeout'] ?? null;
        return [
            'id' => $id,
            'label' => trim((string) ($p['label'] ?? '')) ?: ($id === 'standard' ? 'Standard' : ucfirst(str_replace('_', ' ', $id))),
            'type' => $type,
            'base_url' => $base,
            'api_key' => (string) ($p['api_key'] ?? ''),
            'region' => !empty($p['region']) && strtoupper((string) $p['region']) === 'EU' ? 'EU' : null,
            'timeout' => $timeout === null || $timeout === '' ? null : max(1.0, min(600.0, (float) $timeout)),
            'whisper_bin' => trim((string) ($p['whisper_bin'] ?? '')),
            'model_path' => trim((string) ($p['model_path'] ?? '')),
            'ffmpeg' => trim((string) ($p['ffmpeg'] ?? '')),
            'threads' => max(1, min(64, (int) ($p['threads'] ?? 4))),
        ];
    }

    /** Wirksame Zuordnungen je Zweck: profile, model, fallback, fallback_model, language */
    public static function assignments(array $raw): array
    {
        $out = [];
        foreach (array_keys(self::PURPOSES) as $p) {
            $a = array_intersect_key((array) ($raw['assign'][$p] ?? []), array_flip(self::ASSIGN_KEYS));
            $out[$p] = [
                'profile' => (string) ($a['profile'] ?? ''),
                'model' => trim(mb_substr((string) ($a['model'] ?? ''), 0, 120)),
                'fallback' => (string) ($a['fallback'] ?? ''),
                'fallback_model' => trim(mb_substr((string) ($a['fallback_model'] ?? ''), 0, 120)),
                'language' => (string) ($a['language'] ?? 'auto'),
            ];
        }
        return $out;
    }

    /**
     * Zweck → Verbindung + Modell. Liefert die Felder einer Fähigkeit (provider, base_url, api_key, region, model, profile,
     * label, timeout) oder null, wenn nichts zugeordnet ist. „chat“ ohne eigene Verbindung nutzt die von „text“.
     */
    public static function resolve(array $raw, string $purpose, bool $fallback = false): ?array
    {
        $profiles = self::profiles($raw);
        $assign = self::assignments($raw);
        $a = $assign[$purpose] ?? null;
        if ($a === null) return null;
        if ($purpose === 'chat' && $a['profile'] === '' && !$fallback) {
            $t = self::resolve($raw, 'text');
            if ($t && $a['model'] !== '') $t['model'] = $a['model'];
            return $t ? ['purpose' => 'chat'] + $t : null;
        }
        $pid = $fallback ? $a['fallback'] : $a['profile'];
        $prof = $profiles[$pid] ?? null;
        if (!$prof || $prof['type'] === '' || !in_array($purpose, self::SUPPORTS[$prof['type']] ?? [], true)) return null;
        $model = $fallback ? ($a['fallback_model'] !== '' ? $a['fallback_model'] : $a['model']) : $a['model'];
        return ['purpose' => $purpose, 'profile' => $prof['id'], 'label' => $prof['label'], 'provider' => $prof['type'],
            'base_url' => $prof['base_url'], 'api_key' => $prof['api_key'], 'region' => $prof['region'], 'timeout' => $prof['timeout'],
            'model' => $model];
    }

    /** Konfiguration für Core\AI\Transcriber aus der Zuordnung „transcribe“ */
    public static function transcribe(array $raw): array
    {
        $a = self::assignments($raw)['transcribe'];
        $p = self::profiles($raw)[$a['profile']] ?? null;
        $t = ['language' => $a['language'], 'model' => $a['model']];
        if ($p && $p['type'] === 'whisper_cpp') {
            $t += ['backend' => 'whisper_cpp', 'whisper_bin' => $p['whisper_bin'], 'model_path' => $p['model_path'] !== '' ? $p['model_path'] : $a['model'],
                'ffmpeg' => $p['ffmpeg'], 'threads' => $p['threads'], 'timeout' => (int) ($p['timeout'] ?? 3600)];
            $t['model'] = '';
        } elseif ($p && in_array('transcribe', self::SUPPORTS[$p['type']] ?? [], true)) {
            $t += ['backend' => 'openai', 'base_url' => Ai::endpoint(['provider' => $p['type']] + $p), 'api_key' => $p['api_key'],
                'timeout' => (int) ($p['timeout'] ?? 3600)];
            if ($t['model'] === '' && $p['type'] === 'mistral') $t['model'] = 'voxtral-mini-latest';
        } else {
            $t['backend'] = '';
        }
        return $t;
    }

    // ================================================================== Sperren (Konfigurationsdateien)

    /**
     * Was Konfigurationsdateien festlegen (config.local.php, config/sites/{key}.php) – in der Verwaltung gesperrt:
     * ['profiles' => [id => [feld => true]], 'assign' => [zweck => [feld => true]], 'globals' => [schlüssel => true]]
     */
    public static function locks(array $fileLayer): array
    {
        $f = self::upgrade($fileLayer);
        $out = ['profiles' => [], 'assign' => [], 'globals' => []];
        foreach ((array) ($f['profiles'] ?? []) as $id => $p) {
            $out['profiles'][(string) $id] = array_fill_keys(array_keys((array) $p), true);
        }
        foreach ((array) ($f['assign'] ?? []) as $p => $a) {
            $out['assign'][(string) $p] = array_fill_keys(array_keys((array) $a), true);
        }
        foreach (array_diff_key($f, ['profiles' => 1, 'assign' => 1]) as $k => $_) $out['globals'][$k] = true;
        return $out;
    }

    // ================================================================== Letzte Prüfung je Verbindung (Statuspunkt)

    private static function checksFile(): string
    {
        return ROOT . '/storage/ai/checks.json';
    }

    /** Ergebnis von „Verbindung prüfen“ merken (nur Status, Meldung, Dauer, Version – keine Schlüssel) */
    public static function rememberCheck(string $id, array $res): void
    {
        $all = self::lastChecks();
        $all[$id] = ['status' => (string) ($res['status'] ?? 'error'), 'message' => mb_substr((string) ($res['message'] ?? ''), 0, 300),
            'ms' => $res['ms'] ?? null, 'version' => $res['version'] ?? null, 'at' => time()];
        @mkdir(dirname(self::checksFile()), 0770, true);
        @file_put_contents(self::checksFile(), json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /** @return array<string, array{status: string, message: string, ms: ?int, version: ?string, at: int}> */
    public static function lastChecks(): array
    {
        $d = is_file(self::checksFile()) ? json_decode((string) @file_get_contents(self::checksFile()), true) : null;
        return is_array($d) ? $d : [];
    }

    // ================================================================== Speichern (Verwaltung, nur Agentur)

    /** Gespeicherte Installations-Konfiguration im Format 2 */
    public static function storedV2(): array
    {
        $s = self::upgrade(Ai::stored());
        $s['version'] = 2;
        return $s;
    }

    /** Adresse prüfen: http nur für lokale/private Hosts, sonst https; keine Zugangsdaten in der Adresse */
    public static function urlError(string $base): ?string
    {
        if ($base === '') return null;
        if (!preg_match('~^https?://[a-z0-9.\-\[\]:]+(:\d+)?(/[\w\-./]*)?$~i', $base)) return __('Adresse (base_url) ist ungültig.');
        if (str_starts_with(strtolower($base), 'http://') && !Ai::isLocalHost((string) parse_url($base, PHP_URL_HOST))) return __('Externe Anbieter nur über https://.');
        return null;
    }

    /**
     * Verbindung anlegen bzw. ändern. $id = null → neu (ID aus der Bezeichnung). API-Schlüssel: leer = unverändert, „-“ = löschen.
     * @return array{0: ?string, 1: list<string>} [ID, Fehler]
     */
    public static function saveProfile(?string $id, array $in, array $locks = []): array
    {
        $s = self::storedV2();
        $errors = [];
        $label = trim(mb_substr((string) ($in['label'] ?? ''), 0, 60));
        $type = strtolower(trim((string) ($in['type'] ?? '')));
        if ($label === '') $errors[] = __('Bitte eine Bezeichnung angeben.');
        if (!isset(self::TYPES[$type])) $errors[] = __('Unbekannter Anbieter.');
        $base = rtrim(trim((string) ($in['base_url'] ?? '')), '/');
        if ($e = self::urlError($base)) $errors[] = $e;
        foreach (['whisper_bin', 'ffmpeg', 'model_path'] as $k) {
            $v = trim((string) ($in[$k] ?? ''));
            if ($v !== '' && (str_contains($v, '..') || !preg_match('~^[\w./@+\-]+$~', $v))) $errors[] = __('Pfad „{path}“ ist ungültig.', ['path' => $v]);
        }
        if ($errors) return [null, $errors];
        if ($id === null || $id === '') {
            $id = self::slug($label);
            $all = array_keys(self::profiles(array_replace_recursive($s, ['profiles' => (array) (self::upgrade(Ai::fileLayer())['profiles'] ?? [])])));
            $base0 = $id;
            for ($i = 2; in_array($id, $all, true) || isset($s['profiles'][$id]); $i++) $id = substr($base0, 0, 36) . '_' . $i;
            $cur = [];
        } else {
            if (!preg_match('~^[a-z0-9_]{1,40}$~', $id)) return [null, [__('Unbekannte Verbindung.')]];
            $cur = (array) ($s['profiles'][$id] ?? []);
        }
        $new = ['label' => $label, 'type' => $type, 'base_url' => $base,
            'region' => ($in['region'] ?? '') === 'EU' ? 'EU' : '',
            'timeout' => trim((string) ($in['timeout'] ?? '')) === '' ? '' : max(1, min(600, (float) str_replace(',', '.', (string) $in['timeout']))),
            'whisper_bin' => trim((string) ($in['whisper_bin'] ?? '')), 'model_path' => trim((string) ($in['model_path'] ?? '')),
            'ffmpeg' => trim((string) ($in['ffmpeg'] ?? '')), 'threads' => trim((string) ($in['threads'] ?? '')) === '' ? '' : max(1, min(64, (int) $in['threads']))];
        $key = trim((string) ($in['api_key'] ?? ''));
        $new['api_key'] = $key === '-' ? '' : ($key !== '' ? $key : (string) ($cur['api_key'] ?? ''));
        unset($cur['inherit']);   // ab jetzt eigenständig (geerbte Werte stehen beim Bearbeiten im Formular)
        if ($type !== 'whisper_cpp') unset($new['whisper_bin'], $new['model_path'], $new['ffmpeg'], $new['threads']);
        foreach (array_keys($locks['profiles'][$id] ?? []) as $k) unset($new[$k]);   // aus Dateien: nicht überschreiben
        $s['profiles'][$id] = array_filter($new, fn($v) => $v !== '' && $v !== null);
        Ai::store($s);
        return [$id, []];
    }

    /** Verbindung löschen; Zuordnungen, die darauf zeigen, werden geleert. @return list<string> betroffene Zwecke */
    public static function deleteProfile(string $id): array
    {
        $s = self::storedV2();
        unset($s['profiles'][$id]);
        $hit = [];
        foreach ((array) ($s['assign'] ?? []) as $p => $a) {
            if (($a['profile'] ?? '') === $id) { $s['assign'][$p]['profile'] = ''; $hit[] = $p; }
            if (($a['fallback'] ?? '') === $id) { unset($s['assign'][$p]['fallback'], $s['assign'][$p]['fallback_model']); }
        }
        Ai::store($s);
        return $hit;
    }

    /**
     * Zuordnungen und allgemeine Zeitlimits speichern. $effective = wirksame Konfiguration vorher (für den Hinweis bei
     * geändertem Embedding-Modell). @return array{errors: list<string>, embed_changed: bool}
     */
    public static function saveAssign(array $in, array $effective, array $locks = []): array
    {
        $s = self::storedV2();
        $profiles = self::profiles($effective);
        $errors = [];
        foreach (array_keys(self::PURPOSES) as $p) {
            if (!isset($in['assign'][$p]) || !is_array($in['assign'][$p])) continue;
            $a = $in['assign'][$p];
            $row = [];
            foreach (['profile', 'fallback'] as $k) {
                $v = (string) ($a[$k] ?? '');
                if ($v !== '' && !isset($profiles[$v])) { $errors[] = __('Unbekannte Verbindung.'); continue; }
                if ($v !== '' && !in_array($p, self::SUPPORTS[$profiles[$v]['type']] ?? [], true)) {
                    $errors[] = __('„{profile}“ kann „{purpose}“ nicht.', ['profile' => $profiles[$v]['label'], 'purpose' => __(self::PURPOSES[$p])]);
                    continue;
                }
                $row[$k] = $v;
            }
            foreach (['model', 'fallback_model'] as $k) {
                $v = trim(mb_substr((string) ($a[$k] ?? ''), 0, 120));
                if ($v !== '' && !preg_match('~^[\w.:/@+\-]+$~u', $v)) { $errors[] = __('Modellname „{model}“ ist ungültig.', ['model' => $v]); continue; }
                $row[$k] = $v;
            }
            if ($p === 'transcribe') {
                $l = strtolower(trim((string) ($a['language'] ?? 'auto')));
                $row['language'] = $l === 'auto' || \Core\MediaTracks::validLang($l) ? $l : 'auto';
            }
            if (($row['fallback'] ?? '') === '' || ($row['fallback'] ?? '') === ($row['profile'] ?? '')) unset($row['fallback'], $row['fallback_model']);
            foreach (array_keys($locks['assign'][$p] ?? []) as $k) unset($row[$k]);
            $s['assign'][$p] = $row;
        }
        foreach (['timeout' => [0.5, 30.0], 'text_timeout' => [5, 300], 'index_timeout' => [5, 300]] as $k => [$min, $max]) {
            if (!isset($in[$k]) || trim((string) $in[$k]) === '' || isset($locks['globals'][$k])) continue;
            $s[$k] = max($min, min($max, (float) str_replace(',', '.', (string) $in[$k])));
        }
        if ($errors) return ['errors' => array_values(array_unique($errors)), 'embed_changed' => false];
        // Embedding-Modell geändert? Dann berechnet der Suchindex alle Vektoren neu (Core\Search\Indexer vergleicht das Modell)
        $id = function (array $raw): string {
            $c = Ai::capability('embed', Ai::config($raw));
            return $c['configured'] ? $c['provider'] . '|' . Ai::endpoint($c) . '|' . $c['model'] : '';
        };
        $before = $id($effective);
        $after = $id(array_replace_recursive($effective, ['assign' => $s['assign'] ?? []]));
        Ai::store($s);
        return ['errors' => [], 'embed_changed' => $before !== '' && $before !== $after];
    }
}
