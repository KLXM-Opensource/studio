<?php
declare(strict_types=1);

namespace Core;

/**
 * Symbole (Phosphor Icons, Stil „duotone“, MIT – siehe THIRD-PARTY-NOTICES.md).
 *
 * Quelle der Auswahl: resources/icons/icons.json (Themen, Bezeichnungen de/en, Suchbegriffe). Der Build (tools/icons.mjs)
 * erzeugt daraus kleine Sprites (<symbol id="i-{name}">): core.svg (Verwaltung/Bedienung) und eines je Thema ({thema}.svg),
 * dazu icons-map.json (Symbolname → Datei), catalog.json (Symbolauswahl) und icons.svg (alle – Rückwärtskompatibilität).
 *
 * Einbindung: icon('car') → <svg class="ico"><use href="/assets/icons/transport.svg?v=…#i-car"/></svg>.
 * Website-Besucher: siteSprite() ersetzt nach dem Rendern alle Verweise durch EIN Sprite der Website mit genau den
 * Symbolen, die auf ihren Seiten vorkommen (public/assets/icons/sites/{site}.{hash}.svg, wächst mit, Hash im Namen).
 * Beide Ebenen übernehmen currentColor, die Fläche bleibt durchscheinend (CSS-Variable --ico-2-opacity, Standard .2).
 *
 * Gespeicherte Werte (z. B. Symbol einer Datentabelle, Feldtyp „icon“) sind Symbolnamen. Ältere Werte (Unicode-Zeichen
 * wie ◷ ✎ ▦) werden über LEGACY auf passende Symbole abgebildet; Unbekanntes wird als Zeichen angezeigt.
 */
final class Icons
{
    /** Menü-Schlüssel der Verwaltung (data-ico, Favoriten, Suche) → Symbol */
    public const NAV = [
        'dashboard' => 'squares-four', 'pages' => 'files', 'page' => 'file-text', 'home' => 'house', 'settings' => 'sliders-horizontal',
        'media' => 'images', 'data' => 'database', 'requests' => 'tray', 'inbox' => 'tray', 'calendar' => 'calendar-dots', 'dav' => 'calendar-dots',
        'system' => 'gear-six', 'gear' => 'gear-six', 'features' => 'toggle-left', 'design' => 'palette', 'users' => 'users', 'role' => 'users', 'account' => 'user-circle',
        'user' => 'user', 'api' => 'code', 'network' => 'network', 'globe' => 'globe', 'landings' => 'globe', 'domain' => 'globe', 'support' => 'lifebuoy', 'chat' => 'chats', 'chatcfg' => 'chats', 'help' => 'question', 'review' => 'clipboard-text', 'drafts' => 'pencil-simple', 'pagetemplates' => 'stamp', 'redirects' => 'signpost', 'glossary' => 'book-open-text',
        'prefs' => 'sliders-horizontal', 'stats' => 'chart-bar', 'tools' => 'wrench',
        'fav' => 'star', 'star' => 'star', 'ext' => 'puzzle-piece', 'table' => 'table', 'image' => 'image', 'file' => 'file',
        'plus' => 'plus', 'upload' => 'upload-simple', 'blocks' => 'package', 'key' => 'key', 'search' => 'magnifying-glass',
    ];

    /** Frühere Unicode-Symbole (Datentabellen, Blöcke, Vorlagen) → Symbol */
    public const LEGACY = [
        '◷' => 'clock', '◴' => 'clock', '⌚' => 'clock', '⏰' => 'alarm', '✎' => 'pencil-simple', '✏' => 'pencil-simple', '✍' => 'pencil-simple',
        '▦' => 'table', '▤' => 'table', '▥' => 'table', '☷' => 'list-bullets', '☰' => 'list', '≡' => 'list', '▾' => 'caret-down',
        '☏' => 'phone', '☎' => 'phone', '✆' => 'phone', '☺' => 'user', '☻' => 'user', '◉' => 'users-three', '♀' => 'user', '♂' => 'user',
        '◆' => 'tag', '◈' => 'tag', '◇' => 'tag', '#' => 'hash', '№' => 'hash', '✉' => 'envelope-simple', '@' => 'at',
        '?' => 'question', '❓' => 'question', '!' => 'warning', '⚠' => 'warning', '℞' => 'prescription', '⚕' => 'stethoscope', '✚' => 'first-aid', '+' => 'plus',
        '↗' => 'arrow-up-right', '➜' => 'arrow-right', '→' => 'arrow-right', '⇢' => 'path', '⇉' => 'link', '⛓' => 'link', '↻' => 'arrows-clockwise',
        '★' => 'star', '☆' => 'star', '✦' => 'sparkle', '✧' => 'sparkle', '♥' => 'heart', '❤' => 'heart', '♡' => 'heart',
        '▣' => 'image', '▭' => 'image', '◫' => 'slideshow', '❏' => 'copy', '⧉' => 'copy', '◧' => 'square-half', '¶' => 'paragraph',
        '❝' => 'quotes', '“' => 'quotes', '€' => 'currency-eur', '$' => 'money', '%' => 'percent', '⋯' => 'dots-three', '…' => 'dots-three',
        '⊟' => 'list-bullets', '▶' => 'play-circle', '►' => 'play-circle', '◎' => 'handshake', '↓' => 'download-simple', '⤓' => 'download-simple',
        '⌖' => 'map-pin', '⚑' => 'flag', '⚐' => 'flag', '✓' => 'check', '✔' => 'check', '☑' => 'check-circle', '⎙' => 'printer', '⌁' => 'globe',
        '●' => 'palette', '♪' => 'music-notes', '♫' => 'music-notes', '☀' => 'sun', '☼' => 'sun', '❄' => 'snowflake', '✈' => 'airplane',
        '⌂' => 'house', '⚽' => 'soccer-ball', '☕' => 'coffee', '✂' => 'pencil-ruler', '⚖' => 'scales', '✿' => 'flower-tulip', '❀' => 'flower-tulip',
        '☘' => 'leaf', '♻' => 'recycle', '⚙' => 'gear-six', '✱' => 'sparkle', '⚡' => 'lightning', '☂' => 'cloud-rain',
        '📅' => 'calendar', '📆' => 'calendar', '🗓' => 'calendar', '📰' => 'newspaper', '👤' => 'user', '👥' => 'users', '📞' => 'phone',
        '✉️' => 'envelope-simple', '📧' => 'envelope-simple', '📍' => 'map-pin', '🏠' => 'house', '🏥' => 'hospital', '💊' => 'pill',
        '📷' => 'camera', '🎉' => 'confetti', '🎓' => 'graduation-cap', '⚽️' => 'soccer-ball', '🍽' => 'fork-knife', '🛒' => 'shopping-cart',
        '💶' => 'currency-eur', '📄' => 'file-text', '📁' => 'folder', '🔒' => 'lock', '🔐' => 'lock-key', '💬' => 'chat-circle-text',
        '⭐' => 'star', '❤️' => 'heart', '🚗' => 'car', '🎫' => 'ticket', '🏆' => 'trophy', '🌳' => 'tree', '♿' => 'wheelchair',
    ];

    public const DEFAULT = 'table';

    /**
     * Symbolstil der Website (Grundeinstellungen → „Symbolstil auf der Website“, sys.symbol_style): duotone = Kern-Sprites (Standard),
     * sonst ein Sprite aus public/assets/icons/styles/{stil}.svg (tools/icons.mjs). Die Verwaltung und die Redaktionsleiste bleiben duotone.
     */
    public const STYLES = ['duotone' => 'Phosphor Duotone (zweifarbig, Standard)', 'regular' => 'Phosphor Linie', 'light' => 'Phosphor Fein',
        'thin' => 'Phosphor Haarfein', 'bold' => 'Phosphor Kräftig', 'fill' => 'Phosphor Gefüllt', 'lucide' => 'Lucide (Linie)', 'tabler' => 'Tabler (Linie)'];

    /** Gewählter Stil der Website (nur wenn das Stil-Sprite existiert) */
    public static function style(): string
    {
        static $memo = [];
        $key = site()->key;
        if (isset($memo[$key])) return $memo[$key];
        $s = (string) (app()->settings->get('sys.symbol_style') ?: 'duotone');
        if ($s !== 'duotone' && (!isset(self::STYLES[$s]) || !is_file(ROOT . '/public/assets/icons/styles/' . $s . '.svg'))) $s = 'duotone';
        return $memo[$key] = $s;
    }

    /** Auswahl für die Einstellung (Bezeichnungen übersetzt) */
    public static function styleOptions(): array
    {
        return array_map(fn($l) => __($l), self::STYLES);
    }

    /**
     * Angemeldete Redaktion auf der Website: Symbole im fertigen HTML auf den gewählten Stil umstellen (volles Stil-Sprite),
     * die Redaktionsleiste (.cms-bar-host) ausgenommen. Besucher bekommen ihn über siteSprite().
     */
    public static function applyStyle(string $html): string
    {
        $style = self::style();
        if ($style === 'duotone') return $html;
        $url = asset('icons/styles/' . $style . '.svg');
        $re = '~(<use href=")' . preg_quote(base_path() . '/assets/icons/', '~') . '[a-z0-9-]+\.svg\?v=[a-z0-9]+(#i-[a-z0-9-]+")~';
        $start = strpos($html, '<div class="cms-bar-host');
        $end = $start === false ? false : strpos($html, '</template></div>', $start);
        $fix = fn(string $part) => (string) preg_replace($re, '$1' . $url . '$2', $part);
        if ($start === false || $end === false) return $fix($html);
        return $fix(substr($html, 0, $start)) . substr($html, $start, $end - $start) . $fix(substr($html, $end));
    }

    private static ?array $catalog = null;
    private static ?array $map = null;
    private static array $urls = [];

    /** Themen, die immer in der Symbolauswahl erscheinen (Einstellung „Symbolbereiche“ gilt für die übrigen) */
    public const ALWAYS = ['general', 'ui'];

    /** Katalog (catalog.json): ['topics' => [[key, de, en, icons[]]], 'icons' => [name => [de, en, suchbegriffe, tags (en)]]] */
    public static function catalog(): array
    {
        if (self::$catalog === null) {
            $f = ROOT . '/public/assets/icons/catalog.json';
            self::$catalog = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
            self::$catalog += ['topics' => [], 'icons' => []];
        }
        return self::$catalog;
    }

    /** Index icons-map.json: Symbolname → Sprite-Datei (ohne .svg), z. B. ['car' => 'transport', 'x' => 'core'] */
    public static function map(): array
    {
        if (self::$map === null) {
            $f = ROOT . '/public/assets/icons/icons-map.json';
            self::$map = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
            // Älterer Build ohne Index: alles aus icons.svg
            if (!self::$map) self::$map = array_fill_keys(array_keys(self::catalog()['icons']), 'icons');
        }
        return self::$map;
    }

    /** Gibt es das Symbol im Sprite? */
    public static function exists(string $name): bool
    {
        return isset(self::map()[$name]);
    }

    /**
     * Adresse eines Sprites (mit Versionsparameter wie alle Assets): ohne Namen das vollständige icons.svg
     * (data-icons für JavaScript, Rückwärtskompatibilität), mit Namen das kleine Sprite, in dem das Symbol liegt.
     */
    public static function sprite(?string $name = null): string
    {
        $file = $name !== null ? (self::map()[$name] ?? 'icons') : 'icons';
        return self::$urls[$file] ??= asset('icons/' . $file . '.svg');
    }

    /** Aktivierte Symbolbereiche (Grundeinstellungen → „Symbolbereiche“); null = alle */
    public static function enabledTopics(): ?array
    {
        $on = array_values(array_filter((array) (app()->settings->get('sys.icon_topics') ?: []), 'is_string'));
        return $on ? array_values(array_unique([...self::ALWAYS, ...$on])) : null;
    }

    /** Auswahl für die Einstellung „Symbolbereiche“: Themen-Schlüssel → Bezeichnung (ohne die immer aktiven) */
    public static function topicOptions(): array
    {
        $en = str_starts_with(I18n::locale(), 'en');
        $out = [];
        foreach (self::catalog()['topics'] as $t) {
            if (!in_array($t['key'], self::ALWAYS, true)) $out[$t['key']] = $en ? $t['en'] : $t['de'];
        }
        return $out;
    }

    /**
     * Website-Sprite für Besucherseiten: alle Verweise auf Symbol-Sprites im fertigen HTML durch ein Sprite der Website
     * ersetzen, das genau die bisher auf ihren Seiten verwendeten Symbole enthält (typisch wenige KB statt mehrerer Themen).
     * Neue Symbole erweitern die Menge (storage/…/cache/icons/site.json) → neue Datei mit neuem Hash; ältere Dateien
     * bleiben zwei Tage liegen (Seiten-Cache, Browser). Schlägt etwas fehl, bleiben die Themen-Sprites (unverändert).
     */
    public static function siteSprite(string $html): string
    {
        $re = '~(<use href=")' . preg_quote(base_path() . '/assets/icons/', '~') . '(?:styles/)?[a-z0-9-]+\.svg\?v=[a-z0-9]+#i-([a-z0-9-]+)"~';
        if (!preg_match_all($re, $html, $m)) return $html;
        $map = self::map();
        $names = array_values(array_unique(array_filter($m[2], fn($n) => isset($map[$n]))));
        try {
            $url = $names ? self::siteSpriteUrl($names) : null;
        } catch (\Throwable) {
            $url = null;
        }
        if ($url === null) return $html;
        return (string) preg_replace_callback($re, fn($x) => isset($map[$x[2]]) ? $x[1] . $url . '#i-' . $x[2] . '"' : $x[0], $html);
    }

    /** Adresse des Website-Sprites, das mindestens $names enthält (legt es bei Bedarf an); null = nicht möglich */
    public static function siteSpriteUrl(array $names): ?string
    {
        $style = self::style();
        $full = ROOT . '/public/assets/icons/' . ($style === 'duotone' ? 'icons.svg' : 'styles/' . $style . '.svg');
        $dir = ROOT . '/public/assets/icons/sites';
        $stateDir = site()->storage('cache/icons');
        if (!is_file($full) || (!is_dir($stateDir) && !@mkdir($stateDir, 0770, true))) return null;
        $stateFile = $stateDir . '/site' . ($style === 'duotone' ? '' : '-' . $style) . '.json';
        $state = is_file($stateFile) ? (json_decode((string) file_get_contents($stateFile), true) ?: []) : [];
        $known = (array) ($state['names'] ?? []);
        $file = (string) ($state['file'] ?? '');
        // Schneller Weg: alles bekannt und Datei vorhanden
        if ($file !== '' && !array_diff($names, $known) && ($state['build'] ?? 0) === filemtime($full) && is_file("$dir/$file")) {
            return base_path() . '/assets/icons/sites/' . $file;
        }
        $lock = @fopen($stateDir . '/site.lock', 'c');
        if ($lock) flock($lock, LOCK_EX);
        try {
            // Unter Sperre erneut lesen (parallele Anfragen)
            $state = is_file($stateFile) ? (json_decode((string) file_get_contents($stateFile), true) ?: []) : [];
            $map = self::map();
            $all = array_values(array_filter(array_unique([...(array) ($state['names'] ?? []), ...$names]), fn($n) => is_string($n) && isset($map[$n])));
            sort($all);
            $build = filemtime($full);
            $file = site()->key . '.' . substr(hash('sha256', implode(',', $all) . '|' . $build . '|' . $style), 0, 12) . '.svg';
            if (!is_file("$dir/$file")) {
                if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return null;
                if (!preg_match_all('~<symbol id="i-([a-z0-9-]+)".*?</symbol>~s', (string) file_get_contents($full), $sm)) return null;
                $syms = array_combine($sm[1], $sm[0]);
                $body = '';
                foreach ($all as $n) $body .= $syms[$n] ?? '';
                $svg = '<svg xmlns="http://www.w3.org/2000/svg">' . ($style === 'duotone' ? '<style>[opacity]{opacity:var(--ico-2-opacity,.2)}</style>' : '') . $body . "</svg>\n";
                $tmp = "$dir/.$file." . bin2hex(random_bytes(4));
                if (@file_put_contents($tmp, $svg) === false || !@rename($tmp, "$dir/$file")) { @unlink($tmp); return null; }
                @chmod("$dir/$file", 0644);
                // Ältere Fassungen dieser Website nach zwei Tagen entfernen (Seiten-Cache gilt je Tag)
                foreach (glob($dir . '/' . site()->key . '.*.svg') ?: [] as $old) {
                    if (basename($old) !== $file && filemtime($old) < time() - 2 * 86400) @unlink($old);
                }
            }
            @file_put_contents($stateFile, json_encode(['names' => $all, 'file' => $file, 'build' => $build]), LOCK_EX);
            return base_path() . '/assets/icons/sites/' . $file;
        } finally {
            if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
        }
    }

    /** Wert → Symbolname: Symbolname, Menü-Schlüssel oder altes Unicode-Zeichen; sonst null */
    public static function resolve(?string $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') return null;
        if (self::exists($v)) return $v;
        if (isset(self::NAV[$v])) return self::NAV[$v];
        if (isset(self::LEGACY[$v])) return self::LEGACY[$v];
        $plain = preg_replace('~\x{FE0F}~u', '', $v);   // Emoji-Darstellungsvariante
        return self::LEGACY[$plain] ?? null;
    }

    /** Ist der Wert ein gültiger Symbolname (für Speichern/Validieren)? */
    public static function isName(?string $value): bool
    {
        return is_string($value) && self::exists($value);
    }

    /**
     * Wert für die Speicherung bereinigen: Symbolname bleibt, altes Zeichen wird abgebildet, sonst $default
     * (bei $default === null bleibt ein kurzes Zeichen erhalten – Abwärtskompatibilität).
     */
    public static function clean(?string $value, ?string $default = self::DEFAULT): string
    {
        $v = trim((string) $value);
        if ($v === '') return (string) $default;
        if ($name = self::resolve($v)) return $name;
        if ($default === null && mb_strlen($v) <= 4 && !preg_match('~[<>&"\'\s]~u', $v)) return $v;
        return (string) $default;
    }

    /** SVG-Markup für einen Symbolnamen. $opt: class, label (zugänglicher Name, sonst dekorativ), title */
    public static function svg(string $name, array $opt = []): string
    {
        $cls = trim('ico ' . ($opt['class'] ?? ''));
        $label = trim((string) ($opt['label'] ?? ''));
        $a11y = $label !== '' ? ' role="img" aria-label="' . e($label) . '"' : ' aria-hidden="true"';
        $title = $label !== '' ? '<title>' . e($label) . '</title>' : '';
        return '<svg class="' . e($cls) . '"' . $a11y . ' focusable="false" width="1em" height="1em" fill="currentColor">' . $title
            . '<use href="' . e(self::sprite($name)) . '#i-' . e($name) . '"/></svg>';
    }

    /**
     * Symbol ausgeben – für gespeicherte Werte: Symbolname/Menü-Schlüssel/altes Zeichen → SVG,
     * unbekanntes Zeichen → <span class="ico ico--glyph">, leer → $opt['fallback'] (Symbolname) oder ''.
     */
    public static function render(?string $value, array $opt = []): string
    {
        $name = self::resolve($value) ?? (trim((string) $value) === '' && !empty($opt['fallback']) ? self::resolve($opt['fallback']) : null);
        if ($name !== null) return self::svg($name, $opt);
        $v = trim((string) $value);
        if ($v === '') return '';
        $label = trim((string) ($opt['label'] ?? ''));
        return '<span class="' . e(trim('ico ico--glyph ' . ($opt['class'] ?? ''))) . '"'
            . ($label !== '' ? ' role="img" aria-label="' . e($label) . '"' : ' aria-hidden="true"') . '>' . e($v) . '</span>';
    }

    /** Menüsymbol der Verwaltung: '' für Schlüssel ohne Sprite-Symbol (z. B. „ai“ = KLXM-Logo als CSS-Maske) */
    public static function nav(string $key, string $class = ''): string
    {
        if ($key === 'ai' || !($name = self::resolve($key))) return '';
        return self::svg($name, ['class' => $class]);
    }

    /**
     * Eingabe mit Symbolauswahl (resources/js/_iconpicker.js). Ohne JavaScript: Textfeld für den Symbolnamen.
     * $opt: suggest (id des Felds, dessen Text Vorschläge liefert, z. B. Tabellenname), optional (bool: „ohne Symbol“ erlaubt),
     * attrs (weitere Attribute für das Eingabefeld, bereits maskiert)
     */
    public static function picker(string $id, string $name, ?string $value, array $opt = []): string
    {
        $v = trim((string) $value);
        return '<span class="icp" data-icon-picker' . (!empty($opt['suggest']) ? ' data-suggest="' . e($opt['suggest']) . '"' : '')
            . (!empty($opt['optional']) ? ' data-optional' : '') . '>'
            . '<input type="text" class="icp__input" id="' . e($id) . '" name="' . e($name) . '" value="' . e($v) . '" maxlength="40" spellcheck="false" autocomplete="off"'
            . ($opt['attrs'] ?? '') . '>'
            . '<span class="icp__preview" aria-hidden="true">' . self::render($v) . '</span></span>';
    }

    /** Vorschläge passend zu einem Text (z. B. Tabellenname): Symbolnamen, deren Bezeichnung/Suchbegriffe passen */
    public static function suggest(string $text, int $max = 8): array
    {
        $words = array_filter(preg_split('~[^\p{L}\p{N}]+~u', mb_strtolower($text)) ?: [], fn($w) => mb_strlen($w) >= 3);
        if (!$words) return [];
        $score = [];
        foreach (self::catalog()['icons'] as $name => [$de, $en, $kw]) {
            $hay = ' ' . mb_strtolower("$de $en $kw") . ' ';
            $toks = array_filter(explode(' ', $hay), fn($k) => mb_strlen($k) >= 5);
            foreach ($words as $w) {
                $stem = mb_strlen($w) > 5 ? mb_substr($w, 0, -2) : $w;   // Termine → termi, Beiträge → beiträ
                if (str_contains($hay, ' ' . $w)) $score[$name] = ($score[$name] ?? 0) + 3;
                elseif (str_contains($hay, ' ' . $stem)) $score[$name] = ($score[$name] ?? 0) + 2;
                elseif (array_filter($toks, fn($k) => str_contains($w, $k))) $score[$name] = ($score[$name] ?? 0) + 2;   // Komposita
            }
        }
        arsort($score);
        return array_slice(array_keys($score), 0, $max);
    }

    /**
     * Einmalige Übernahme: Symbole der Datentabellen (Unicode-Zeichen) → Symbolnamen. Alte Werte bleiben in
     * sys.icons_legacy erhalten ({handle: zeichen}). Idempotent; läuft beim Start (App::boot) und über `migrate`.
     * @return list<string> Protokoll
     */
    public static function migrateTables(): array
    {
        $db = app()->db;
        $out = [];
        if (!self::catalog()['icons']) return $out;   // Sprite noch nicht gebaut
        $legacy = (array) (app()->settings->get('sys.icons_legacy') ?: []);
        // Eigene Tabellen und geteilte (storage/shared/{key}/share.sqlite, für alle beteiligten Websites)
        $dbs = ['' => $db];
        foreach (array_keys(Data\Shared::all()) as $key) {
            try { $dbs["shared:$key"] = Data\Shared::db($key); } catch (\Throwable) { /* nicht lesbar: später erneut */ }
        }
        foreach ($dbs as $prefix => $conn) {
            foreach ($conn->fetchAll('SELECT id, handle, icon FROM data_tables') as $t) {
                $old = (string) ($t['icon'] ?? '');
                if (self::isName($old)) continue;
                $new = self::resolve($old) ?? self::DEFAULT;
                $conn->update('data_tables', ['icon' => $new], 'id = :id', ['id' => (int) $t['id']]);
                $legacy[$prefix !== '' ? $prefix : $t['handle']] = $old;
                $out[] = "Symbol " . ($prefix !== '' ? $prefix : $t['handle']) . ": " . ($old === '' ? '(leer)' : $old) . " → $new";
            }
        }
        if ($out) Data\Tables::flush();
        if ($out) app()->settings->set('sys.icons_legacy', $legacy);
        return $out;
    }
}
