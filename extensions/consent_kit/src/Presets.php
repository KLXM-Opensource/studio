<?php
// SPDX-License-Identifier: MIT
// Portions ported from FriendsOfREDAXO/consent_kit (MIT, © KLXM Crossmedia GmbH): PresetRepository, PresetIo
declare(strict_types=1);

namespace MyCms\Consent;

/**
 * Vorlagen (Format: presets/FORMAT.md – kompatibel mit dem REDAXO-AddOn consent_kit, Export/Import in beide Richtungen).
 *
 *  - Mitgeliefert: extensions/consent_kit/presets/*.json (38 Dienste, DE/EN, geprüft gegen die Anbieter-Dokumentation).
 *  - CSP-Hosts je Vorlage: presets/_csp.json (Ergänzung von KLXM Studio; Feld „csp“ in eigenen Vorlagen überschreibt).
 *  - Eigene Vorlagen je Website: storage/…/consent/presets/*.json – überschreiben mitgelieferte mit gleichem Schlüssel.
 */
final class Presets
{
    private const REQUIRED = ['key', 'name', 'group'];
    private static ?array $all = null;

    public static function ownDir(): string
    {
        return site()->storage('consent/presets');
    }

    /** @return array<string, array> key => Vorlage (mit 'origin' = bundled|own) */
    public static function all(): array
    {
        if (self::$all !== null) return self::$all;
        $out = [];
        $csp = self::read(dirname(__DIR__) . '/presets/_csp.json');
        foreach (glob(dirname(__DIR__) . '/presets/*.json') ?: [] as $f) {
            if (str_starts_with(basename($f), '_')) continue;
            foreach ((array) (self::read($f)['services'] ?? []) as $p) {
                if (is_array($p) && isset($p['key'])) $out[(string) $p['key']] = $p + ['origin' => 'bundled', 'csp' => $csp[(string) $p['key']] ?? []];
            }
        }
        foreach (glob(self::ownDir() . '/*.json') ?: [] as $f) {
            foreach ((array) (self::read($f)['services'] ?? []) as $p) {
                if (is_array($p) && isset($p['key'])) $out[(string) $p['key']] = $p + ['origin' => 'own', 'file' => basename($f), 'csp' => $csp[(string) $p['key']] ?? []];
            }
        }
        return self::$all = $out;
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    private static function read(string $file): array
    {
        $d = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return is_array($d) ? $d : [];
    }

    /** Vorlage → Daten für Repository::save() (inaktiv, IDs leer) */
    public static function toService(string $key): ?array
    {
        $p = self::get($key);
        if (!$p) return null;
        return [
            'skey' => $p['key'], 'grp' => isset(Repository::GROUPS[$p['group'] ?? '']) ? $p['group'] : 'statistics',
            'name' => (string) $p['name'], 'provider' => (string) ($p['provider'] ?? ''), 'privacy_url' => (string) ($p['privacy_url'] ?? ''),
            'description' => (array) ($p['description'] ?? []), 'params' => [], 'param_defs' => array_values((array) ($p['params'] ?? [])),
            'html_head' => (string) ($p['html_head'] ?? ''), 'html_body' => (string) ($p['html_body'] ?? ''),
            'js_default' => (string) ($p['js_default'] ?? ''), 'js_accept' => (string) ($p['js_accept'] ?? ''), 'js_revoke' => (string) ($p['js_revoke'] ?? ''),
            'gcm' => array_values(array_intersect((array) ($p['gcm_signals'] ?? []), Repository::GCM_SIGNALS)),
            'embed_hosts' => array_values((array) ($p['embed_hosts'] ?? [])),
            'csp' => self::cspOf($p),
            'items' => self::items((array) ($p['items'] ?? [])),
            'events' => [], 'variants' => [], 'hosts' => [], 'preset' => $p['key'],
            'sources' => array_values((array) ($p['sources'] ?? [])), 'verified' => (string) ($p['verified'] ?? ''),
            'note' => (array) ($p['note'] ?? []), 'active' => false,
        ];
    }

    private static function cspOf(array $p): array
    {
        $c = (array) ($p['csp'] ?? []);
        $out = [];
        foreach (['script', 'connect', 'img', 'frame'] as $k) $out[$k] = array_values(array_filter(array_map('strval', (array) ($c[$k] ?? []))));
        return $out;
    }

    /** Einträge „Cookies & Speicher“ vereinheitlichen: duration {value, unit} → value/unit */
    public static function items(array $raw): array
    {
        $out = [];
        foreach ($raw as $i) {
            if (!is_array($i) || trim((string) ($i['name'] ?? '')) === '') continue;
            $d = (array) ($i['duration'] ?? []);
            $out[] = [
                'type' => isset(Repository::ITEM_TYPES[$i['type'] ?? '']) ? $i['type'] : 'cookie',
                'name' => trim((string) $i['name']),
                'host' => trim((string) ($i['host'] ?? '')),
                'value' => (int) ($d['value'] ?? $i['value'] ?? 0),
                'unit' => isset(Repository::UNITS[$d['unit'] ?? $i['unit'] ?? '']) ? ($d['unit'] ?? $i['unit']) : 'session',
                'purpose_de' => (string) ($i['purpose']['de'] ?? $i['purpose_de'] ?? ''),
                'purpose_en' => (string) ($i['purpose']['en'] ?? $i['purpose_en'] ?? ''),
            ];
        }
        return $out;
    }

    /** Aufruf-Vorlagen je Conversion-Ereignis (lead, registration, appointment, page_view) */
    public static function events(?string $key): array
    {
        return $key ? array_map('strval', (array) (self::get($key)['events'] ?? [])) : [];
    }

    // ------------------------------------------------------------------ Export / Import (Format des AddOns)

    /** Dienst → Vorlage: ohne eingetragene Werte, Domains, Varianten, Status und Ereignis-Auslöser */
    public static function fromService(array $s): array
    {
        $defs = $s['param_defs'];
        if (!$defs) {
            // Ohne Ursprungsvorlage: Platzhalter aus dem Code lesen
            preg_match_all('~\{\{([a-z0-9_]+)\}\}~', implode("\n", array_map(fn($k) => $s[$k], Repository::CODE_FIELDS)), $m);
            foreach (array_unique(array_diff($m[1], ['lang', 'domain', 'label'])) as $k) $defs[] = ['key' => $k, 'label' => ['de' => $k, 'en' => $k], 'placeholder' => '', 'pattern' => ''];
        }
        $origin = $s['preset'] ? self::get($s['preset']) : null;
        return array_filter([
            'key' => $s['skey'], 'name' => $s['name'], 'group' => $s['grp'], 'provider' => $s['provider'], 'privacy_url' => $s['privacy_url'],
            'description' => $s['description'], 'params' => array_values($defs),
            'html_head' => $s['html_head'], 'html_body' => $s['html_body'], 'js_default' => $s['js_default'], 'js_accept' => $s['js_accept'], 'js_revoke' => $s['js_revoke'],
            'gcm_signals' => $s['gcm'], 'embed_hosts' => $s['embed_hosts'],
            'csp' => array_filter($s['csp']),
            'items' => array_map(fn($i) => ['type' => $i['type'], 'name' => $i['name'], 'host' => $i['host'],
                'duration' => ['value' => (int) $i['value'], 'unit' => $i['unit']], 'purpose' => ['de' => $i['purpose_de'], 'en' => $i['purpose_en']]], $s['items']),
            'events' => $origin['events'] ?? null,
            'sources' => $s['sources'] ?: ($origin['sources'] ?? []),
            'verified' => date('Y-m-d'),
            'note' => $s['note'] ?: null,
        ], fn($v) => $v !== null);
    }

    /**
     * Import einer Vorlagen-Datei: prüft jeden Eintrag, übernimmt gültige, benennt übersprungene.
     * @return array{file: string, imported: list<string>, skipped: list<string>}
     */
    public static function import(string $json, string $name, bool $overwrite): array
    {
        $data = json_decode($json, true);
        if (!is_array($data) || !is_array($data['services'] ?? null)) {
            throw new \InvalidArgumentException(__('Die Datei ist keine Vorlagen-Datei (erwartet: JSON mit „services“).'));
        }
        $ok = [];
        $skipped = [];
        foreach ($data['services'] as $i => $p) {
            $err = self::validate($p);
            if ($err) { $skipped[] = '#' . ($i + 1) . ' ' . (is_array($p) ? (string) ($p['key'] ?? '') : '') . ': ' . $err; continue; }
            $ok[] = $p;
        }
        if (!$ok) return ['file' => '', 'imported' => [], 'skipped' => $skipped];
        $dir = self::ownDir();
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $file = self::safeName($name);
        if (!$overwrite && is_file("$dir/$file")) {
            $base = substr($file, 0, -5);
            for ($n = 2; is_file("$dir/$base-$n.json"); $n++);
            $file = "$base-$n.json";
        }
        file_put_contents("$dir/$file", json_encode(['services' => $ok], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        self::$all = null;
        return ['file' => $file, 'imported' => array_column($ok, 'key'), 'skipped' => $skipped];
    }

    public static function validate(mixed $p): ?string
    {
        if (!is_array($p)) return __('kein Objekt');
        foreach (self::REQUIRED as $k) if (trim((string) ($p[$k] ?? '')) === '') return __('„{field}“ fehlt', ['field' => $k]);
        if (!preg_match('~^[a-z0-9_]{1,60}$~', (string) $p['key'])) return __('Schlüssel nur a–z, 0–9, _');
        if (!isset(Repository::GROUPS[(string) $p['group']])) return __('unbekannte Gruppe „{group}“', ['group' => (string) $p['group']]);
        foreach ((array) ($p['items'] ?? []) as $item) {
            if (!is_array($item) || trim((string) ($item['name'] ?? '')) === '') return __('Eintrag unter „items“ ohne Namen');
            if (!isset(Repository::ITEM_TYPES[$item['type'] ?? ''])) return __('unbekannte Art „{type}“', ['type' => (string) ($item['type'] ?? '')]);
            if (!isset(Repository::UNITS[$item['duration']['unit'] ?? ''])) return __('unbekannte Laufzeit-Einheit');
        }
        if (array_diff((array) ($p['gcm_signals'] ?? []), Repository::GCM_SIGNALS)) return __('ungültiges Consent-Mode-Signal');
        foreach ((array) ($p['params'] ?? []) as $d) if (!preg_match('~^[a-z0-9_]+$~', (string) ($d['key'] ?? ''))) return __('ungültiger Platzhalter-Schlüssel');
        return null;
    }

    public static function safeName(string $name): string
    {
        $name = trim((string) preg_replace('~[^a-zA-Z0-9_-]+~', '-', pathinfo($name, PATHINFO_FILENAME) ?: ''), '-');
        return ($name === '' ? 'vorlagen' : substr($name, 0, 64)) . '.json';
    }

    /** @return list<array{file: string, size: int, keys: list<string>}> */
    public static function ownFiles(): array
    {
        $out = [];
        foreach (glob(self::ownDir() . '/*.json') ?: [] as $f) {
            $out[] = ['file' => basename($f), 'size' => (int) filesize($f), 'keys' => array_values(array_filter(array_map(fn($p) => is_array($p) ? (string) ($p['key'] ?? '') : '', (array) (self::read($f)['services'] ?? []))))];
        }
        return $out;
    }

    public static function ownPath(string $file): ?string
    {
        $path = self::ownDir() . '/' . self::safeName($file);
        return is_file($path) ? $path : null;
    }

    public static function deleteOwn(string $file): bool
    {
        $p = self::ownPath($file);
        $ok = $p !== null && @unlink($p);
        self::$all = null;
        return $ok;
    }
}
