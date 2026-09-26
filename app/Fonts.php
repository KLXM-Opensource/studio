<?php
declare(strict_types=1);

namespace Core;

/**
 * Schriften aus dem Google-Fonts-Katalog installieren und selbst ausliefern (Grundeinstellungen → Schriften, CLI fonts:*).
 *
 * Quelle: Fontsource-API (api.fontsource.org, Spiegel des Google-Fonts-Katalogs mit Metadaten und Lizenz), Dateien von
 * cdn.jsdelivr.net/fontsource; Ausweichweg: Google Fonts CSS2-API (fonts.googleapis.com → fonts.gstatic.com, woff2).
 * Alles läuft serverseitig über Core\Proxy::http() (Quelle „fontsource“, nur Server) – Browser der Redaktion und der
 * Besucher haben nie Kontakt zu Google, Fontsource oder jsDelivr. Auch die Vorschau in der Verwaltung kommt von der
 * eigenen Domain (/admin/fonts/preview/{id}.woff2, zwischengespeichert unter storage/cache/fonts).
 *
 * Installation (für alle Websites der Installation): public/fonts/{id}/
 *   *.woff2       nur geprüfte Dateien (woff2-Signatur „wOF2“, Größenlimit)
 *   font.css      @font-face je Schnitt/Zeichensatz (font-display: swap, unicode-range)
 *   LICENSE.txt   Lizenztext des Pakets (OFL-1.1, Apache-2.0 oder UFL-1.0 – andere Lizenzen werden abgelehnt)
 * public/fonts/fonts.json: Metadaten (Familie, Schnitte, Lizenz, Copyright, Größe) und Verwendung (Website · Theme).
 *
 * Style-Editor (Core\Design): installierte Schriften erscheinen in jedem Token vom Typ „font“ als „Name (installiert)“,
 * sofern das Theme sie nicht mit 'design' => ['fonts_extra' => false] ausschließt; design_head() bindet font.css ein.
 */
final class Fonts
{
    public const API = 'https://api.fontsource.org/v1';
    public const CDN = 'https://cdn.jsdelivr.net';
    public const LICENSES = ['OFL-1.1' => 'SIL Open Font License 1.1', 'Apache-2.0' => 'Apache License 2.0', 'UFL-1.0' => 'Ubuntu Font Licence 1.0'];
    public const DEFAULT_SUBSETS = ['latin', 'latin-ext'];
    public const CATEGORIES = ['sans-serif' => 'Serifenlos', 'serif' => 'Serifen', 'display' => 'Auffällig (Display)', 'handwriting' => 'Handschrift', 'monospace' => 'Festbreite'];
    /** Hinweis im Style-Editor, wenn die Schrift (lateinische Dateien aller Schnitte) mehr lädt */
    public const BUDGET_KB = 150;
    private const MAX_FILE = 2 * 1024 * 1024;
    private const MAX_TOTAL = 24 * 1024 * 1024;
    private const MAX_FILES = 120;
    public const PREFIX = 'installed:';
    /** Beliebte Familien für die Startansicht (ohne Suchbegriff) */
    private const POPULAR = ['inter', 'roboto', 'open-sans', 'lato', 'montserrat', 'poppins', 'source-sans-3', 'nunito', 'raleway', 'work-sans',
        'dm-sans', 'manrope', 'figtree', 'outfit', 'space-grotesk', 'ibm-plex-sans', 'fira-sans', 'noto-sans', 'merriweather', 'lora',
        'playfair-display', 'libre-baskerville', 'eb-garamond', 'source-serif-4', 'crimson-pro', 'fraunces', 'dm-serif-display',
        'caveat', 'jetbrains-mono', 'ibm-plex-mono'];
    private const GOOGLE_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    private static ?array $manifest = null;

    /** Proxy-Quelle (nur serverseitig) – aufgerufen von Proxy::sources() */
    public static function registerProxy(): void
    {
        Proxy::register('fontsource', [
            'upstream' => self::API, 'label' => 'Schriften (Fontsource / Google Fonts)', 'public' => false,
            'hosts' => ['cdn.jsdelivr.net', 'fonts.googleapis.com', 'fonts.gstatic.com'],
            'types' => ['application/json', 'font/', 'application/font', 'application/octet-stream', 'text/plain', 'text/css'],
            'max' => 8 * 1024 * 1024, 'ttl' => 86400,
        ]);
    }

    /**
     * Schriften installieren/entfernen: wirkt auf ALLE Websites der Installation – daher nur Integratoren/Netzwerk-Konten
     * oder die Administration der Netzwerk-Website (wie bei geteilten Medien-Pools). Verwenden dürfen alle (Style-Editor).
     */
    public static function canManage(): bool
    {
        return Features::on('fonts') && (Features::integrator() || (can('system.manage') && \Core\Network\Network::isNetworkSite()));
    }

    // ------------------------------------------------------------------ Ablage

    public static function dir(string $sub = ''): string
    {
        return ROOT . '/public/fonts' . ($sub !== '' ? '/' . $sub : '');
    }

    public static function url(string $path): string
    {
        return base_path() . '/fonts/' . ltrim($path, '/');
    }

    private static function cacheDir(string $sub = ''): string
    {
        return ROOT . '/storage/cache/fonts' . ($sub !== '' ? '/' . $sub : '');
    }

    public static function validId(string $id): bool
    {
        return (bool) preg_match('~^[a-z0-9][a-z0-9-]{0,63}$~', $id);
    }

    /** Familienname für CSS und Anzeige: nur Buchstaben, Ziffern, Leerzeichen, Bindestrich, Punkt, Apostroph-frei */
    public static function cleanFamily(string $family): string
    {
        return trim((string) preg_replace('~\s+~u', ' ', (string) preg_replace('~[^\p{L}\p{N} .\-]~u', '', $family)));
    }

    /** @return array{fonts: array<string, array>, usage: array<string, array<string, true>>} */
    public static function manifest(): array
    {
        if (self::$manifest !== null) return self::$manifest;
        $m = is_file(self::dir('fonts.json')) ? json_decode((string) file_get_contents(self::dir('fonts.json')), true) : null;
        $m = is_array($m) ? $m : [];
        return self::$manifest = ['fonts' => (array) ($m['fonts'] ?? []), 'usage' => (array) ($m['usage'] ?? [])];
    }

    private static function saveManifest(array $m): void
    {
        @mkdir(self::dir(), 0775, true);
        ksort($m['fonts']);
        $json = json_encode(['_' => 'Installierte Schriften (Core\Fonts) – automatisch gepflegt', 'fonts' => $m['fonts'] ?: new \stdClass(), 'usage' => $m['usage'] ?: new \stdClass()],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tmp = self::dir('fonts.json.' . bin2hex(random_bytes(4)));
        file_put_contents($tmp, $json . "\n", LOCK_EX);
        rename($tmp, self::dir('fonts.json'));
        self::$manifest = $m;
    }

    /** Installierte Schriften [id => Metadaten] */
    public static function installed(): array
    {
        return array_filter(self::manifest()['fonts'], fn($f, $id) => self::validId((string) $id) && is_file(self::dir($id . '/font.css')), ARRAY_FILTER_USE_BOTH);
    }

    public static function get(string $id): ?array
    {
        return self::installed()[$id] ?? null;
    }

    /** CSS-Schriftstapel: 'Familie', passende Ersatzschriften */
    public static function stack(array $f): string
    {
        $fallback = match ((string) ($f['category'] ?? '')) {
            'serif' => 'Georgia, "Times New Roman", serif',
            'monospace' => 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
            'handwriting' => 'cursive',
            default => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        };
        return '"' . self::cleanFamily((string) $f['family']) . '", ' . $fallback;
    }

    /** Adresse von font.css (mit Versionsparameter) */
    public static function cssUrl(string $id): string
    {
        $file = self::dir($id . '/font.css');
        return self::url($id . '/font.css') . '?v=' . (is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0');
    }

    /** Datei zum Vorladen (lateinisch, Normalschnitt 400 bzw. nächstliegend) */
    public static function primaryFile(array $f): ?string
    {
        $files = array_column((array) ($f['files'] ?? []), 'file');
        $sub = in_array('latin', (array) ($f['subsets'] ?? []), true) ? 'latin' : ((array) ($f['subsets'] ?? []))[0] ?? 'latin';
        if (!empty($f['variable'])) {
            foreach ($files as $n) if (preg_match('~^' . preg_quote($sub, '~') . '-[a-z]+-normal\.woff2$~', $n)) return $n;
        }
        $weights = array_map('intval', (array) ($f['weights'] ?? [400]));
        usort($weights, fn($a, $b) => abs($a - 400) <=> abs($b - 400));
        foreach ($weights as $w) if (in_array("$sub-$w-normal.woff2", $files, true)) return "$sub-$w-normal.woff2";
        return $files[0] ?? null;
    }

    /** Bytes, die eine Seite höchstens lädt (lateinische Zeichensätze aller Schnitte) */
    public static function pageBytes(array $f): int
    {
        $n = 0;
        foreach ((array) ($f['files'] ?? []) as $file) {
            if (preg_match('~^latin(-ext)?-~', (string) $file['file'])) $n += (int) $file['bytes'];
        }
        return $n ?: (int) ($f['bytes'] ?? 0);
    }

    // ------------------------------------------------------------------ Style-Editor (Core\Design)

    /**
     * Zusätzliche Optionen für Tokens vom Typ „font“: [installed:{id} => label, stack, href, kb, installed]
     * Leer, wenn das Theme 'fonts_extra' => false setzt oder die Funktion „fonts“ aus ist.
     */
    public static function designOptions(array $def): array
    {
        if (($def['fonts_extra'] ?? true) === false || !Features::on('fonts', false)) return [];
        $out = [];
        foreach (self::installed() as $id => $f) {
            $out[self::PREFIX . $id] = ['label' => self::cleanFamily((string) $f['family']) . ' ' . __('(installiert)'), 'stack' => self::stack($f),
                'href' => self::cssUrl($id), 'kb' => (int) ceil(self::pageBytes($f) / 1024), 'installed' => true,
                'preload' => !empty($f['preload']) && ($p = self::primaryFile($f)) ? self::url($id . '/' . $p) : null];
        }
        return $out;
    }

    /** Verwendung merken (nach dem Speichern im Style-Editor): Website · Theme → installierte Schriften */
    public static function recordUsage(string $site, string $theme, array $values, array $tokens): void
    {
        $m = self::manifest();
        if (!$m['fonts'] && !$m['usage']) return;
        $where = $site . ':' . $theme;
        $used = [];
        foreach ($tokens as $n => $t) {
            $v = (string) ($values[$n] ?? '');
            if (($t['type'] ?? '') === 'font' && str_starts_with($v, self::PREFIX)) $used[substr($v, strlen(self::PREFIX))] = true;
        }
        $changed = false;
        foreach (array_keys($m['usage']) as $id) {
            if (isset($m['usage'][$id][$where]) && !isset($used[$id])) { unset($m['usage'][$id][$where]); $changed = true; }
            if (empty($m['usage'][$id])) { unset($m['usage'][$id]); $changed = true; }
        }
        foreach (array_keys($used) as $id) {
            if (!isset($m['usage'][$id][$where])) { $m['usage'][$id][$where] = true; $changed = true; }
        }
        if ($changed) self::saveManifest($m);
    }

    /** @return list<string> „Website · Theme“ */
    public static function usage(string $id): array
    {
        $out = [];
        foreach (array_keys((array) (self::manifest()['usage'][$id] ?? [])) as $w) {
            [$site, $theme] = array_pad(explode(':', (string) $w, 2), 2, '');
            $out[] = ($site === 'default' ? __('Hauptwebsite') : $site) . ' · ' . $theme;
        }
        return $out;
    }

    // ------------------------------------------------------------------ Katalog (Fontsource)

    private static function fetchJson(string $url, string $cache, int $ttl): ?array
    {
        $file = self::cacheDir($cache);
        if (is_file($file) && time() - (int) filemtime($file) < $ttl) {
            $d = json_decode((string) file_get_contents($file), true);
            if (is_array($d)) return $d;
        }
        $res = Proxy::http($url, ['Accept: application/json']);
        $d = $res && $res['status'] === 200 ? json_decode($res['body'], true) : null;
        if (is_array($d)) {
            @mkdir(dirname($file), 0775, true);
            @file_put_contents($file, $res['body'], LOCK_EX);
            return $d;
        }
        // Anbieter nicht erreichbar: älteren Stand verwenden
        if (is_file($file)) {
            $d = json_decode((string) file_get_contents($file), true);
            if (is_array($d)) return $d;
        }
        return null;
    }

    /**
     * Katalog der Google-Fonts-Familien mit erlaubter Lizenz (1 Tag zwischengespeichert).
     * @return array<string, array{id: string, family: string, category: string, subsets: list<string>, weights: list<int>, styles: list<string>, variable: bool, license: string}>
     */
    public static function catalog(bool $refresh = false): array
    {
        $list = self::fetchJson(self::API . '/fonts', 'catalog.json', $refresh ? 0 : 86400);
        if ($list === null) return [];
        $out = [];
        foreach ($list as $f) {
            $id = (string) ($f['id'] ?? '');
            if (!self::validId($id) || ($f['type'] ?? '') !== 'google' || !isset(self::LICENSES[(string) ($f['license'] ?? '')])) continue;
            if (($f['category'] ?? '') === 'icons') continue;
            $out[$id] = ['id' => $id, 'family' => self::cleanFamily((string) ($f['family'] ?? $id)), 'category' => (string) ($f['category'] ?? ''),
                'subsets' => array_values(array_filter(array_map('strval', (array) ($f['subsets'] ?? [])), fn($s) => (bool) preg_match('~^[a-z0-9-]{1,40}$~', $s))),
                'weights' => array_values(array_map('intval', (array) ($f['weights'] ?? []))), 'styles' => array_values(array_intersect(['normal', 'italic'], (array) ($f['styles'] ?? []))),
                'variable' => !empty($f['variable']), 'license' => (string) $f['license'], 'defSubset' => (string) ($f['defSubset'] ?? 'latin')];
        }
        return $out;
    }

    /** Suche im Katalog (Name, optional Kategorie); ohne Begriff: beliebte Familien */
    public static function search(string $q, string $category = '', int $limit = 48): array
    {
        $cat = self::catalog();
        $q = mb_strtolower(trim($q));
        if ($q === '') {
            $hits = array_values(array_filter(array_map(fn($id) => $cat[$id] ?? null, self::POPULAR)));
        } else {
            $hits = array_values(array_filter($cat, fn($f) => str_contains(mb_strtolower($f['family']), $q) || str_contains($f['id'], str_replace(' ', '-', $q))));
            usort($hits, fn($a, $b) => [!str_starts_with(mb_strtolower($a['family']), $q), $a['family']] <=> [!str_starts_with(mb_strtolower($b['family']), $q), $b['family']]);
        }
        if ($category !== '') $hits = array_values(array_filter($hits, fn($f) => $f['category'] === $category));
        return array_slice($hits, 0, $limit);
    }

    /** Katalogeintrag + Details (Dateien je Schnitt, unicode-range, Achsen der variablen Fassung) */
    public static function detail(string $id): ?array
    {
        if (!self::validId($id) || !($base = self::catalog()[$id] ?? null)) return null;
        $d = self::fetchJson(self::API . '/fonts/' . $id, 'detail/' . $id . '.json', 86400);
        if (!$d) return $base + ['unicodeRange' => [], 'axes' => [], 'version' => '', 'npmVersion' => 'latest'];
        $axes = [];
        if ($base['variable'] && ($v = self::fetchJson(self::API . '/variable/' . $id, 'detail/' . $id . '.vf.json', 86400))) {
            foreach ((array) ($v['axes'] ?? []) as $ax => $a) {
                if (preg_match('~^[a-zA-Z]{4}$~', (string) $ax)) $axes[(string) $ax] = ['min' => (float) ($a['min'] ?? 0), 'max' => (float) ($a['max'] ?? 0), 'default' => (float) ($a['default'] ?? 0)];
            }
        }
        $ranges = [];
        foreach ((array) ($d['unicodeRange'] ?? []) as $sub => $r) {
            if (preg_match('~^[a-z0-9-]{1,40}$~', (string) $sub) && preg_match('~^[U+0-9A-Fa-f?,\- ]{1,4000}$~', (string) $r)) $ranges[(string) $sub] = (string) $r;
        }
        return $base + ['unicodeRange' => $ranges, 'axes' => $axes, 'version' => preg_replace('~[^\w.\-]~', '', (string) ($d['version'] ?? '')),
            'npmVersion' => preg_match('~^\d+\.\d+\.\d+$~', (string) ($d['npmVersion'] ?? '')) ? (string) $d['npmVersion'] : 'latest'];
    }

    // ------------------------------------------------------------------ Vorschau (nur Verwaltung, von der eigenen Domain)

    /** Pfad zu einer zwischengespeicherten woff2-Datei (lateinisch, Normalschnitt) für die Vorschau – oder null */
    public static function previewFile(string $id): ?string
    {
        if (!self::validId($id) || !($f = self::catalog()[$id] ?? null)) return null;
        $file = self::cacheDir('preview/' . $id . '.woff2');
        if (is_file($file) && time() - (int) filemtime($file) < 30 * 86400) return $file;
        $sub = in_array('latin', $f['subsets'], true) ? 'latin' : ($f['defSubset'] ?: ($f['subsets'][0] ?? 'latin'));
        $weights = $f['weights'] ?: [400];
        usort($weights, fn($a, $b) => abs($a - 400) <=> abs($b - 400));
        $style = in_array('normal', $f['styles'], true) ? 'normal' : ($f['styles'][0] ?? 'normal');
        $bin = self::download(self::CDN . "/fontsource/fonts/$id@latest/$sub-{$weights[0]}-$style.woff2");
        if ($bin === null) return is_file($file) ? $file : null;
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, $bin, LOCK_EX);
        return $file;
    }

    // ------------------------------------------------------------------ Installieren / Entfernen

    /** woff2-Datei laden und prüfen (Signatur, Größe) */
    private static function download(string $url): ?string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (!in_array($host, ['cdn.jsdelivr.net', 'fonts.gstatic.com'], true)) return null;
        $res = Proxy::http($url, ['Accept: font/woff2,*/*']);
        if (!$res || $res['status'] !== 200) return null;
        $bin = $res['body'];
        return self::isWoff2($bin) && strlen($bin) <= self::MAX_FILE ? $bin : null;
    }

    public static function isWoff2(string $bin): bool
    {
        return strlen($bin) > 48 && str_starts_with($bin, 'wOF2');
    }

    /**
     * Schrift installieren (ersetzt eine vorhandene Installation derselben Familie).
     * @param array{weights?: list<int|string>, styles?: list<string>, subsets?: list<string>, variable?: bool, preload?: bool} $opt
     * @return array{ok: bool, message: string, font?: array}
     */
    public static function install(string $id, array $opt = [], string $by = ''): array
    {
        $id = strtolower(trim($id));
        if (!self::validId($id)) return ['ok' => false, 'message' => __('Ungültige Schrift.')];
        $d = self::detail($id);
        if (!$d) return ['ok' => false, 'message' => __('Schrift „{id}“ nicht im Katalog gefunden (oder Lizenz nicht erlaubt).', ['id' => $id])];
        if (!isset(self::LICENSES[$d['license']])) return ['ok' => false, 'message' => __('Lizenz {license} ist nicht erlaubt (nur OFL, Apache, UFL).', ['license' => $d['license']])];

        $subsets = array_values(array_intersect(array_map('strval', (array) ($opt['subsets'] ?? self::DEFAULT_SUBSETS)), $d['subsets']));
        if (!$subsets) $subsets = array_values(array_intersect(self::DEFAULT_SUBSETS, $d['subsets'])) ?: [$d['defSubset'] ?: $d['subsets'][0]];
        $styles = array_values(array_intersect(array_map('strval', (array) ($opt['styles'] ?? ['normal'])), $d['styles'])) ?: [$d['styles'][0] ?? 'normal'];
        $variable = !empty($opt['variable']) && $d['variable'] && isset($d['axes']['wght']);
        $weights = array_values(array_intersect(array_map('intval', (array) ($opt['weights'] ?? [400, 700])), $d['weights']));
        if (!$weights && !$variable) $weights = in_array(400, $d['weights'], true) ? [400] : [$d['weights'][0] ?? 400];
        sort($weights);
        if (!$variable && count($weights) * count($styles) * count($subsets) > self::MAX_FILES) {
            return ['ok' => false, 'message' => __('Zu viele Dateien – bitte weniger Schnitte oder Zeichensätze wählen.')];
        }

        // Dateien laden: Fontsource (jsDelivr), sonst Google Fonts CSS2
        $plan = [];   // [file, url, style, weight|range, subset]
        foreach ($styles as $st) {
            foreach ($subsets as $sub) {
                if ($variable) {
                    $plan[] = ["$sub-wght-$st.woff2", self::CDN . "/fontsource/fonts/$id:vf@latest/$sub-wght-$st.woff2", $st, $d['axes']['wght']['min'] . ' ' . $d['axes']['wght']['max'], $sub];
                } else {
                    foreach ($weights as $w) $plan[] = ["$sub-$w-$st.woff2", self::CDN . "/fontsource/fonts/$id@latest/$sub-$w-$st.woff2", $st, (string) $w, $sub];
                }
            }
        }
        $tmp = self::dir('.tmp-' . $id . '-' . bin2hex(random_bytes(4)));
        @mkdir($tmp, 0775, true);
        $source = 'fontsource';
        $files = [];
        $total = 0;
        $google = null;
        $fail = function (string $msg) use ($tmp): array { self::rmdir($tmp); return ['ok' => false, 'message' => $msg]; };
        foreach ($plan as [$name, $url, $st, $w, $sub]) {
            $bin = self::download($url);
            if ($bin === null) {
                // Ausweichweg: Google Fonts CSS2 (woff2) – dieselbe Familie, gleicher Schnitt und Zeichensatz
                $google ??= self::googleFaces($d['family'], $variable ? [] : $weights, $styles, $variable ? $d['axes']['wght'] : null);
                $gurl = $google[$st . '|' . ($variable ? 'var' : $w) . '|' . $sub] ?? null;
                $bin = $gurl ? self::download($gurl) : null;
                if ($bin !== null) $source = 'google';
            }
            if ($bin === null) return $fail(__('Datei {file} konnte nicht geladen werden (Anbieter nicht erreichbar oder keine gültige woff2-Datei).', ['file' => $name]));
            $total += strlen($bin);
            if ($total > self::MAX_TOTAL) return $fail(__('Die Auswahl ist zu groß (über {mb} MB).', ['mb' => self::MAX_TOTAL / 1048576]));
            file_put_contents("$tmp/$name", $bin);
            $files[] = ['file' => $name, 'bytes' => strlen($bin), 'style' => $st, 'weight' => $w, 'subset' => $sub];
        }

        // Lizenz und Copyright aus dem npm-Paket (@fontsource/{id}) – Pflicht für OFL/Apache/UFL
        $lic = Proxy::http(self::CDN . '/npm/@fontsource/' . $id . '@' . $d['npmVersion'] . '/LICENSE', ['Accept: text/plain']);
        $licText = $lic && $lic['status'] === 200 && strlen($lic['body']) < 200000 ? str_replace("\r\n", "\n", $lic['body']) : '';
        if ($licText === '' || !preg_match('~(Open Font License|Apache License|Ubuntu Font Licen[cs]e)~i', $licText)) {
            return $fail(__('Der Lizenztext konnte nicht geladen oder nicht als OFL/Apache/UFL erkannt werden – nichts installiert.'));
        }
        file_put_contents("$tmp/LICENSE.txt", $licText);
        $copyright = '';
        foreach (preg_split('~\n~', $licText) ?: [] as $line) {
            if (preg_match('~^\s*Copyright\b~i', $line)) {
                // Nur der erste Vermerk (weitere Zeilen je Datei, z. B. „Lora-Italic[wght].ttf: Copyright …“, fallen weg)
                $line = (string) preg_replace('~\s+\S+\.(ttf|otf)\s*:.*$~i', '', strip_tags($line));
                $copyright = trim(mb_substr($line, 0, 300));
                break;
            }
        }

        $meta = ['id' => $id, 'family' => $d['family'], 'category' => $d['category'], 'license' => $d['license'], 'license_name' => self::LICENSES[$d['license']],
            'copyright' => $copyright, 'source' => $source, 'version' => $d['version'], 'variable' => $variable,
            'axes' => $variable ? ['wght' => $d['axes']['wght']] : [], 'weights' => $variable ? array_values(array_filter($d['weights'], fn($w) => $w >= $d['axes']['wght']['min'] && $w <= $d['axes']['wght']['max'])) : $weights,
            'styles' => $styles, 'subsets' => $subsets, 'files' => array_map(fn($f) => ['file' => $f['file'], 'bytes' => $f['bytes']], $files),
            'bytes' => $total, 'installed_at' => date('c'), 'by' => $by, 'preload' => !empty($opt['preload'])];
        file_put_contents("$tmp/font.css", self::css($meta, $files, $d['unicodeRange']));

        // Atomar austauschen
        $dst = self::dir($id);
        $old = is_dir($dst) ? self::dir('.old-' . $id . '-' . bin2hex(random_bytes(4))) : null;
        if ($old) rename($dst, $old);
        if (!rename($tmp, $dst)) { if ($old) rename($old, $dst); return $fail(__('Schrift konnte nicht gespeichert werden (Schreibrechte für public/fonts prüfen).')); }
        if ($old) self::rmdir($old);
        $m = self::manifest();
        $m['fonts'][$id] = $meta;
        self::saveManifest($m);
        PageCache::clear();
        return ['ok' => true, 'message' => __('„{family}“ installiert ({size}).', ['family' => $d['family'], 'size' => Media::humanSize($total)]), 'font' => $meta];
    }

    /** @font-face-Regeln (relative Adressen – unabhängig vom Basis-Pfad) */
    private static function css(array $meta, array $files, array $ranges): string
    {
        $fam = self::cleanFamily((string) $meta['family']);
        $out = "/* {$fam} – {$meta['license_name']} ({$meta['license']}), siehe LICENSE.txt. Selbst gehostet (KLXM Studio, Core\\Fonts). */\n";
        foreach ($files as $f) {
            $range = $ranges[$f['subset']] ?? '';
            $out .= '@font-face{font-family:"' . $fam . '";font-style:' . $f['style'] . ';font-weight:' . $f['weight'] . ';font-display:swap;'
                . 'src:url(' . $f['file'] . ') format("woff2")' . ($range !== '' ? ';unicode-range:' . $range : '') . "}\n";
        }
        return $out;
    }

    /**
     * Google Fonts CSS2 (Ausweichweg): [style|weight|subset => woff2-URL] – „var“ statt Gewicht für variable Schriften.
     * @return array<string, string>
     */
    private static function googleFaces(string $family, array $weights, array $styles, ?array $wght): array
    {
        $ital = in_array('italic', $styles, true);
        $tuples = [];
        foreach ($styles as $st) {
            $i = $st === 'italic' ? 1 : 0;
            if ($wght) $tuples[] = ($ital ? "$i," : '') . (int) $wght['min'] . '..' . (int) $wght['max'];
            else foreach ($weights as $w) $tuples[] = ($ital ? "$i," : '') . (int) $w;
        }
        sort($tuples);
        $url = 'https://fonts.googleapis.com/css2?family=' . str_replace('%20', '+', rawurlencode($family)) . ':' . ($ital ? 'ital,' : '') . 'wght@' . implode(';', array_unique($tuples)) . '&display=swap';
        $res = Proxy::http($url, ['User-Agent: ' . self::GOOGLE_UA, 'Accept: text/css']);
        if (!$res || $res['status'] !== 200) return [];
        $out = [];
        preg_match_all('~/\*\s*([a-z0-9-]+)\s*\*/\s*@font-face\s*\{([^}]*)\}~', $res['body'], $m, PREG_SET_ORDER);
        foreach ($m as [, $sub, $body]) {
            if (!preg_match('~font-style:\s*(normal|italic)~', $body, $st) || !preg_match('~src:\s*url\((https://fonts\.gstatic\.com/[^)\s]+\.woff2)\)~', $body, $u)) continue;
            preg_match('~font-weight:\s*(\d+)~', $body, $w);
            $out[$st[1] . '|' . ($wght ? 'var' : ($w[1] ?? '400')) . '|' . $sub] = $u[1];
        }
        return $out;
    }

    /** Schrift entfernen; in Verwendung nur mit $force */
    public static function remove(string $id, bool $force = false): array
    {
        if (!self::validId($id) || !is_dir(self::dir($id))) return ['ok' => false, 'message' => __('Schrift nicht installiert.')];
        $used = self::usage($id);
        if ($used && !$force) {
            return ['ok' => false, 'message' => __('„{id}“ wird noch verwendet: {where}. Im Style-Editor eine andere Schrift wählen oder das Entfernen bestätigen.', ['id' => $id, 'where' => implode(', ', $used)])];
        }
        self::rmdir(self::dir($id));
        $m = self::manifest();
        unset($m['fonts'][$id], $m['usage'][$id]);
        self::saveManifest($m);
        PageCache::clear();
        return ['ok' => true, 'message' => __('Schrift „{id}“ entfernt. Wo sie gewählt war, gilt wieder die Standardschrift des Kits.', ['id' => $id])];
    }

    public static function setPreload(string $id, bool $on): bool
    {
        $m = self::manifest();
        if (!isset($m['fonts'][$id])) return false;
        $m['fonts'][$id]['preload'] = $on;
        self::saveManifest($m);
        PageCache::clear();
        return true;
    }

    private static function rmdir(string $dir): void
    {
        // Nur innerhalb von public/fonts
        $real = realpath($dir);
        $root = realpath(self::dir());
        if (!$real || !$root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) return;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($real);
    }

    // ------------------------------------------------------------------ Lizenzen & Danksagungen

    /** Abschnitt „Installierte Schriften“ für die Seite Lizenzen & Danksagungen (help/licenses.php) – leer ohne Schriften */
    public static function licensesSection(): string
    {
        $fonts = self::installed();
        if (!$fonts) return '';
        $h = '<section class="doc-ch" id="l-installed-fonts"><h2>' . e(__('Installierte Schriften')) . '</h2>'
            . '<p class="adm-muted">' . e(__('Über Grundeinstellungen → Schriften aus dem Google-Fonts-Katalog installiert und von dieser Website ausgeliefert. Der Lizenztext liegt jeweils neben den Dateien (public/fonts/{id}/LICENSE.txt).')) . '</p><ul>';
        foreach ($fonts as $id => $f) {
            $h .= '<li><strong>' . e((string) $f['family']) . '</strong> – ' . e((string) ($f['license_name'] ?? $f['license'])) . ' (<code>' . e((string) $f['license']) . '</code>)'
                . (($f['copyright'] ?? '') !== '' ? '<br><small>' . e((string) $f['copyright']) . '</small>' : '')
                . '<br><small><a href="' . e(self::url($id . '/LICENSE.txt')) . '">LICENSE.txt</a> · ' . e(__('Quelle: Google Fonts über {src}', ['src' => ($f['source'] ?? '') === 'google' ? 'fonts.google.com' : 'Fontsource'])) . '</small></li>';
        }
        return $h . '</ul></section>';
    }

    // ------------------------------------------------------------------ Kommandozeile

    public static function console(string $cmd, array $args): int
    {
        $opt = [];
        $pos = [];
        foreach ($args as $a) {
            if (preg_match('~^--([a-z-]+)(?:=(.*))?$~', $a, $m)) $opt[$m[1]] = $m[2] ?? true;
            else $pos[] = $a;
        }
        $list = fn(string $v) => array_values(array_filter(array_map('trim', explode(',', $v)), fn($x) => $x !== ''));
        switch ($cmd) {
            case 'fonts:search':
                $hits = self::search(implode(' ', $pos), (string) ($opt['category'] ?? ''), 40);
                if (!$hits) { fwrite(STDERR, "Nichts gefunden (oder Katalog nicht erreichbar).\n"); return 1; }
                foreach ($hits as $f) {
                    printf("%-28s %-26s %-12s %-9s %s%s\n", $f['id'], $f['family'], $f['category'], $f['license'], implode(',', $f['weights']), $f['variable'] ? '  variabel' : '');
                }
                return 0;
            case 'fonts:install':
                $id = self::resolveId(implode(' ', $pos));
                if ($id === null) { fwrite(STDERR, "Schrift nicht gefunden. Suche: php bin/console fonts:search <name>\n"); return 1; }
                $o = ['subsets' => isset($opt['subsets']) ? $list((string) $opt['subsets']) : self::DEFAULT_SUBSETS, 'variable' => isset($opt['variable']),
                    'styles' => isset($opt['styles']) ? $list((string) $opt['styles']) : (isset($opt['italic']) ? ['normal', 'italic'] : ['normal']), 'preload' => isset($opt['preload'])];
                if (isset($opt['weights'])) $o['weights'] = $list((string) $opt['weights']);
                $r = self::install($id, $o, 'cli');
                echo ($r['ok'] ? '' : 'Fehler: ') . strip_tags($r['message']) . "\n";
                if ($r['ok']) echo '  ' . self::cssUrl($id) . "\n";
                return $r['ok'] ? 0 : 1;
            case 'fonts:list':
                $fonts = self::installed();
                if (!$fonts) { echo "Keine Schriften installiert.\n"; return 0; }
                foreach ($fonts as $id => $f) {
                    printf("%-24s %-24s %-9s %8s  %s  %s\n", $id, $f['family'], $f['license'], Media::humanSize((int) $f['bytes']),
                        $f['variable'] ? 'variabel ' . $f['axes']['wght']['min'] . '–' . $f['axes']['wght']['max'] : implode(',', $f['weights']),
                        ($u = self::usage($id)) ? 'verwendet: ' . implode(', ', $u) : '');
                }
                return 0;
            case 'fonts:remove':
                $id = self::resolveId(implode(' ', $pos), true) ?? '';
                $r = self::remove($id, isset($opt['force']));
                echo ($r['ok'] ? '' : 'Fehler: ') . $r['message'] . ($r['ok'] ? '' : ' (--force)') . "\n";
                return $r['ok'] ? 0 : 1;
        }
        return 1;
    }

    /** „Space Grotesk“ oder „space-grotesk“ → Katalog-ID */
    private static function resolveId(string $in, bool $installed = false): ?string
    {
        $id = trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower(trim($in))), '-');
        if ($id === '') return null;
        if ($installed) return self::validId($id) ? $id : null;
        if (isset(self::catalog()[$id])) return $id;
        foreach (self::catalog() as $k => $f) if (strcasecmp($f['family'], trim($in)) === 0) return $k;
        return null;
    }
}
