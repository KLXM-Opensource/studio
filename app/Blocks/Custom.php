<?php
declare(strict_types=1);

namespace Core\Blocks;

use Core\Block;
use Core\Database;
use Core\Features;
use Core\Icons;
use Core\Media;
use Core\PageCache;
use Core\Pages;
use Core\StructuredData;

/**
 * Eigene Blöcke (Block-Designer, Verwaltung → Blöcke): Admins und Integratoren bauen Blöcke aus Feldern, einer sicheren
 * Vorlage (Core\Blocks\Template) und begrenztem CSS (Core\Blocks\Css) – ohne PHP, ohne eigenes JavaScript.
 *
 * Speicher je Website: custom_blocks (Arbeitsstand + veröffentlichte Fassung) und custom_block_versions (Verlauf).
 * Erst „Für Redaktion freigeben“ (publish) macht den Block zum Blocktyp cblk_{schlüssel}: Theme::blocks(), Editor.js-Schublade,
 * REST/MCP (block_types), Suche, JSON-LD. Das CSS liegt dann als statische Datei im Medienordner
 * ({media}/blocks/cblk-{schlüssel}-{hash}.css) und lädt nur auf Seiten mit dem Block (Theme::conditionalCss).
 * Zurückgezogene Blöcke rendern weiter (Seiten verlieren nichts), lassen sich aber nicht mehr einfügen.
 * Netzwerk-Bibliothek (Kopien, keine Live-Verknüpfung): storage/blocks/{schlüssel}.json – für Netzwerk-Administration/Integratoren.
 */
final class Custom
{
    public const PREFIX = 'cblk_';
    public const TYPES = ['text', 'textarea', 'richtext', 'inline', 'media', 'file', 'link', 'select', 'bool', 'number', 'date', 'color', 'icon', 'repeater'];
    public const RESERVED = ['variant', 'block', 'loop', 'true', 'false', 'not', 'and', 'or', 'in', 'id', 'type', 'data', 'tunes'];
    public const SCHEMAS = ['Service', 'Product', 'Person', 'Event', 'Place', 'Organization', 'Course', 'CreativeWork'];
    public const PROPS = ['name', 'description', 'image', 'url', 'telephone', 'email', 'price', 'jobTitle', 'startDate', 'location'];
    public const MAX_VERSIONS = 60;

    private static array $defs = [];
    private static array $compiled = [];

    // ================================================================== Tabellen

    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $long = $my ? 'LONGTEXT' : 'TEXT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->query("CREATE TABLE IF NOT EXISTS custom_blocks (id $pk, bkey VARCHAR(40) NOT NULL UNIQUE, label VARCHAR(191) NOT NULL,
            icon VARCHAR(60) NULL, bgroup VARCHAR(80) NULL, description TEXT, fields_json $long, template $long, css $long,
            behaviours VARCHAR(191) NULL, jsonld_json TEXT, settings_json TEXT, sample_json $long, status VARCHAR(12) NOT NULL DEFAULT 'draft',
            version INT NOT NULL DEFAULT 0, published_json $long NULL, published_at VARCHAR(25) NULL, updated_by INT NULL,
            created_at VARCHAR(25), updated_at VARCHAR(25))$tail");
        $db->query("CREATE TABLE IF NOT EXISTS custom_block_versions (id $pk, block_id INT NOT NULL, kind VARCHAR(12) NOT NULL DEFAULT 'save',
            data_json $long, note VARCHAR(191) NULL, user_id INT NULL, created_at VARCHAR(25))$tail");
        if (!$my) $db->query('CREATE INDEX IF NOT EXISTS custom_block_versions_block ON custom_block_versions (block_id, id)');
    }

    // ================================================================== Lesen

    public static function type(string $key): string
    {
        return self::PREFIX . $key;
    }

    public static function all(): array
    {
        return array_map([self::class, 'hydrate'], app()->db->fetchAll('SELECT * FROM custom_blocks ORDER BY bgroup, label'));
    }

    public static function find(string $key): ?array
    {
        $r = app()->db->fetch('SELECT * FROM custom_blocks WHERE bkey = ?', [$key]);
        return $r ? self::hydrate($r) : null;
    }

    private static function hydrate(array $r): array
    {
        $j = fn($v, $d = []) => is_string($v) && $v !== '' ? (json_decode($v, true) ?? $d) : $d;
        $def = [
            'key' => (string) $r['bkey'], 'label' => (string) $r['label'], 'icon' => (string) ($r['icon'] ?? ''), 'group' => (string) ($r['bgroup'] ?? ''),
            'description' => (string) ($r['description'] ?? ''), 'fields' => $j($r['fields_json']), 'template' => (string) ($r['template'] ?? ''),
            'css' => (string) ($r['css'] ?? ''), 'behaviours' => array_values(array_filter(explode(',', (string) ($r['behaviours'] ?? '')))),
            'jsonld' => $j($r['jsonld_json'], ['type' => 'none']), 'settings' => $j($r['settings_json']), 'sample' => $j($r['sample_json']),
        ];
        $pub = $j($r['published_json'] ?? null, null);
        return $def + [
            'id' => (int) $r['id'], 'status' => (string) $r['status'], 'version' => (int) $r['version'],
            'published' => is_array($pub) ? $pub : null, 'published_at' => $r['published_at'] ?? null,
            'changed' => is_array($pub) && self::hash($def) !== self::hash($pub),
            'updated_at' => $r['updated_at'] ?? null, 'updated_by' => $r['updated_by'] ?? null,
        ];
    }

    /** Fingerabdruck der Definition (ohne Beispieldaten) – „Änderungen noch nicht freigegeben“ */
    public static function hash(array $def): string
    {
        $keys = ['label', 'icon', 'group', 'description', 'fields', 'template', 'css', 'behaviours', 'jsonld', 'settings'];
        return md5((string) json_encode(array_intersect_key($def, array_flip($keys)) + array_fill_keys($keys, null), JSON_UNESCAPED_UNICODE));
    }

    /** Nur die Definition (für Export, Versionen, Vorschau) */
    public static function definition(array $row): array
    {
        return array_intersect_key($row, array_flip(['key', 'label', 'icon', 'group', 'description', 'fields', 'template', 'css', 'behaviours', 'jsonld', 'settings', 'sample']));
    }

    // ================================================================== Theme-Integration

    /** Veröffentlichte (auch zurückgezogene) Blöcke als Theme-Blockdefinitionen: cblk_{schlüssel} => def */
    public static function definitions(): array
    {
        $site = site()->key;
        if (isset(self::$defs[$site])) return self::$defs[$site];
        $out = [];
        try {
            foreach (app()->db->fetchAll("SELECT bkey, status, published_json FROM custom_blocks WHERE published_json IS NOT NULL AND status IN ('published', 'withdrawn')") as $r) {
                $pub = json_decode((string) $r['published_json'], true);
                if (is_array($pub)) $out[self::type((string) $r['bkey'])] = self::themeDef($pub, (string) $r['status']);
            }
        } catch (\Throwable $e) {
            error_log('[blocks] ' . $e->getMessage());
        }
        return self::$defs[$site] = $out;
    }

    public static function forget(): void
    {
        self::$defs = [];
    }

    /** Definition im Format von theme.php → blocks */
    public static function themeDef(array $def, string $status = 'published'): array
    {
        $s = (array) ($def['settings'] ?? []);
        $bg = (string) ($s['background'] ?? '');
        $out = [
            'label' => (string) $def['label'],
            'icon' => Icons::isName((string) ($def['icon'] ?? '')) ? (string) $def['icon'] : 'package',
            'group' => trim((string) ($def['group'] ?? '')) ?: 'Eigene Blöcke',
            'help' => trim((string) ($s['help'] ?? '')) ?: (string) ($def['description'] ?? ''),
            'fields' => (array) ($def['fields'] ?? []),
            'custom' => true,
            'insertable' => $status === 'published',
            'cblk' => [
                'key' => (string) $def['key'], 'behaviours' => (array) ($def['behaviours'] ?? []), 'width' => ($s['width'] ?? '') === 'full' ? 'full' : 'wrap',
                'template' => (string) ($def['template'] ?? ''), 'css' => (string) ($def['css_file'] ?? ''), 'jsonld' => (array) ($def['jsonld'] ?? []),
            ],
        ];
        if ($bg !== '') $out['background'] = $bg;   // Kit prüft gegen seine Hintergründe (Theme::__construct)
        if (($def['jsonld']['type'] ?? 'none') !== 'none') $out['jsonld'] = fn(Block $b) => self::jsonld($b);
        return $out;
    }

    /** Ausgabe eines eigenen Blocks (innerhalb der normalen Abschnitts-Hülle des Themes) */
    public static function render(Block $b): string
    {
        $c = (array) ($b->def['cblk'] ?? []);
        try {
            $tpl = self::compiled((string) ($c['template'] ?? ''), (array) $b->def['fields'], (array) ($c['behaviours'] ?? []));
            $rt = Runtime::for($b);
            return $rt->open() . $tpl->run($rt, $b->data) . $rt->close();
        } catch (\Throwable $e) {
            error_log('[blocks] ' . $b->type . ': ' . $e->getMessage());
            if (!app()->editing) return '';
            $msg = $e instanceof TemplateError ? $e->display() : $e->getMessage();
            return '<div class="' . e((string) (app()->theme->def['container_class'] ?? 'wrap')) . '"><p class="cms-error">'
                . e(__('Block „{label}“ konnte nicht angezeigt werden: {msg}', ['label' => $b->def['label'] ?? $b->type, 'msg' => $msg])) . '</p></div>';
        }
    }

    public static function compiled(string $template, array $fields, array $behaviours): Template
    {
        $k = md5($template . json_encode($fields) . implode(',', $behaviours));
        return self::$compiled[$k] ??= Template::compile($template, $fields, $behaviours);
    }

    /**
     * Stylesheets eigener Blöcke für die übergebenen Blocktypen (null = alle, z. B. im Bearbeiten-Modus)
     * @return array{css: list<string>, media: bool} media: Lightbox-Styles des Kerns (media.css) nötig
     */
    public static function assets(?array $types): array
    {
        $css = [];
        $media = false;
        foreach (self::definitions() as $type => $d) {
            if ($types !== null && !in_array($type, $types, true)) continue;
            if (($d['cblk']['css'] ?? '') !== '') $css[] = site()->mediaUrl('blocks/' . $d['cblk']['css']);
            if (in_array('lightbox', (array) ($d['cblk']['behaviours'] ?? []), true)) $media = true;
        }
        return ['css' => $css, 'media' => $media];
    }

    /** schema.org-Daten nach der Zuordnung im Block-Designer (FAQ oder Einträge eines Typs) */
    public static function jsonld(Block $b, ?array $spec = null): ?array
    {
        $spec ??= (array) ($b->def['cblk']['jsonld'] ?? []);
        $d = $b->data;
        $type = (string) ($spec['type'] ?? 'none');
        if ($type === 'faq') {
            StructuredData::faq((array) ($d[(string) ($spec['items'] ?? '')] ?? []), (string) ($spec['question'] ?? ''), (string) ($spec['answer'] ?? ''));
            return null;
        }
        if ($type !== 'item' || !in_array($spec['schema'] ?? '', self::SCHEMAS, true)) return null;
        $fields = array_column((array) $b->def['fields'], null, 'name');
        $list = (string) ($spec['list'] ?? '');
        $sub = $list !== '' ? array_column((array) ($fields[$list]['fields'] ?? []), null, 'name') : $fields;
        $node = function (array $src) use ($spec, $sub): ?array {
            $n = ['@type' => $spec['schema']];
            foreach ((array) ($spec['props'] ?? []) as $prop => $field) {
                if (!in_array($prop, self::PROPS, true) || !isset($sub[$field])) continue;
                $v = $src[$field] ?? null;
                $t = $sub[$field]['type'] ?? 'text';
                if ($t === 'media') {
                    $m = is_numeric($v) ? Media::find((int) $v) : null;
                    $v = $m ? absolute_url(Media::url($m, 1200)) : null;
                } elseif ($t === 'link') {
                    $h = Runtime::resolveLink((string) $v);
                    $v = $v && str_starts_with($h, '/') ? absolute_url($h) : (preg_match('~^https://~', $h) ? $h : null);
                } else {
                    $v = trim(html_entity_decode(strip_tags((string) (is_scalar($v) ? $v : '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
                if ($v === null || $v === '' || str_contains((string) $v, '[')) continue;
                if ($prop === 'price') {
                    if (preg_match('~(\d+(?:[.,]\d{1,2})?)~', str_replace('.', '', (string) $v), $pm)) {
                        $n['offers'] = ['@type' => 'Offer', 'price' => str_replace(',', '.', $pm[1]), 'priceCurrency' => 'EUR'];
                    }
                    continue;
                }
                if ($prop === 'location') $v = ['@type' => 'Place', 'name' => $v];
                $n[$prop] = $v;
            }
            return !empty($n['name']) ? $n : null;
        };
        if ($list !== '') {
            $items = array_values(array_filter(array_map($node, array_filter((array) ($d[$list] ?? []), 'is_array'))));
            if (!$items) return null;
            return ['@type' => 'ItemList', 'itemListElement' => array_map(fn($it, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'item' => $it], $items, array_keys($items))];
        }
        return $node($d);
    }

    // ================================================================== Prüfen & Normalisieren

    /** Feldtypen des Block-Designers: Typ => [Bezeichnung, Symbol] */
    public static function fieldTypes(): array
    {
        return [
            'text' => [__('Text (einzeilig)'), 'text-t'], 'textarea' => [__('Text (mehrzeilig)'), 'paragraph'], 'richtext' => [__('Formatierter Text'), 'text-b'],
            'inline' => [__('Kurztext mit Fett/Kursiv/Link'), 'text-b'], 'media' => [__('Bild'), 'image'], 'file' => [__('Datei'), 'paperclip'],
            'link' => [__('Link'), 'link'], 'select' => [__('Auswahl'), 'caret-down'], 'bool' => [__('Ja / Nein'), 'toggle-left'], 'number' => [__('Zahl'), 'hash'],
            'date' => [__('Datum'), 'calendar-blank'], 'color' => [__('Farbe'), 'palette'], 'icon' => [__('Symbol'), 'star'], 'repeater' => [__('Liste (wiederholbar)'), 'copy'],
        ];
    }

    public static function normName(string $s): string
    {
        $s = str_replace('-', '_', Pages::slugify($s));
        if ($s === '' || !preg_match('~^[a-z]~', $s)) $s = 'f_' . $s;
        return substr(trim($s, '_'), 0, 40);
    }

    /**
     * Eingaben des Block-Designers → Definition. Fehler in Name/Feldern verhindern das Speichern;
     * Vorlage und CSS werden getrennt geprüft (check) – ein Entwurf mit Fehlern lässt sich speichern, aber nicht freigeben.
     * @return array{0: array, 1: array<string, string>}
     */
    public static function normalize(array $in, ?string $key = null): array
    {
        $errors = [];
        $label = mb_substr(trim(strip_tags((string) ($in['label'] ?? ''))), 0, 80);
        if ($label === '') $errors['label'] = __('Bitte eine Bezeichnung eingeben.');
        if ($key === null) {
            $key = trim((string) ($in['key'] ?? ''));
            $key = $key === '' ? self::normName($label) : $key;
            if (!preg_match('~^[a-z][a-z0-9_]{1,30}$~', $key)) {
                $errors['key'] = __('Kurzname: 2–31 Zeichen, a–z, 0–9 und _, beginnt mit einem Buchstaben.');
            }
        }
        [$fields, $fe] = self::normFields(is_string($in['fields'] ?? null) ? (json_decode((string) $in['fields'], true) ?: []) : (array) ($in['fields'] ?? []));
        if ($fe) $errors['fields'] = implode(' ', $fe);
        $beh = array_values(array_intersect(Css::BEHAVIOURS, (array) ($in['behaviours'] ?? [])));
        $s = (array) ($in['settings'] ?? []);
        $settings = [
            'help' => mb_substr(trim(strip_tags((string) ($s['help'] ?? ''))), 0, 300),
            'background' => preg_replace('~[^a-z0-9_-]~', '', (string) ($s['background'] ?? '')),
            'width' => ($s['width'] ?? '') === 'full' ? 'full' : 'wrap',
        ];
        $icon = trim((string) ($in['icon'] ?? ''));
        $def = [
            'key' => $key,
            'label' => $label,
            'icon' => Icons::isName($icon) ? $icon : (Icons::resolve($icon) ?? 'package'),
            'group' => mb_substr(trim(strip_tags((string) ($in['group'] ?? ''))), 0, 60),
            'description' => mb_substr(trim(strip_tags((string) ($in['description'] ?? ''))), 0, 400),
            'fields' => $fields,
            'template' => str_replace(["\r\n", "\r"], "\n", (string) ($in['template'] ?? '')),
            'css' => str_replace(["\r\n", "\r"], "\n", (string) ($in['css'] ?? '')),
            'behaviours' => $beh,
            'jsonld' => self::normJsonld((array) ($in['jsonld'] ?? []), $fields),
            'settings' => $settings,
        ];
        $def['sample'] = self::normSample($fields, is_string($in['sample'] ?? null) ? (json_decode((string) $in['sample'], true) ?: []) : (array) ($in['sample'] ?? []));
        return [$def, $errors];
    }

    /** @return array{0: list<array>, 1: list<string>} */
    public static function normFields(array $in, bool $sub = false): array
    {
        $out = [];
        $errors = [];
        $names = [];
        foreach (array_slice(array_values($in), 0, $sub ? 15 : 30) as $i => $f) {
            if (!is_array($f)) continue;
            $label = mb_substr(trim(strip_tags((string) ($f['label'] ?? ''))), 0, 80);
            $name = trim((string) ($f['name'] ?? '')) !== '' ? self::normName((string) $f['name']) : ($label !== '' ? self::normName($label) : '');
            if ($label === '' && $name === '') continue;
            if ($label === '') $label = $name;
            $type = in_array($f['type'] ?? '', self::TYPES, true) ? (string) $f['type'] : 'text';
            if ($sub && $type === 'repeater') $type = 'text';
            if (in_array($name, self::RESERVED, true)) {
                $errors[] = __('„{name}“ ist als Kurzname reserviert.', ['name' => $name]);
                continue;
            }
            if (isset($names[$name])) {
                $errors[] = __('Der Kurzname „{name}“ ist doppelt.', ['name' => $name]);
                continue;
            }
            $names[$name] = true;
            $row = ['name' => $name, 'label' => $label, 'type' => $type];
            if (!empty($f['required'])) $row['required'] = true;
            foreach (['help' => 240, 'placeholder' => 100] as $k => $max) {
                $v = mb_substr(trim(strip_tags((string) ($f[$k] ?? ''))), 0, $max);
                if ($v !== '') $row[$k] = $v;
            }
            if (($f['width'] ?? '') === 'half') $row['width'] = 'half';
            if (in_array($type, ['text', 'textarea', 'inline'], true) && (int) ($f['max'] ?? 0) > 0) $row['max'] = min(5000, (int) $f['max']);
            if ($type === 'textarea') $row['rows'] = max(2, min(12, (int) ($f['rows'] ?? 3)));
            if ($type === 'select') {
                $opts = [];
                $raw = $f['options'] ?? [];
                if (is_string($raw)) $raw = preg_split('~\R~', $raw) ?: [];
                foreach ((array) $raw as $k => $v) {
                    if (is_int($k) && is_string($v) && str_contains($v, '=')) [$k, $v] = array_map('trim', explode('=', $v, 2));
                    $v = mb_substr(trim(strip_tags((string) $v)), 0, 80);
                    if ($v === '') continue;
                    $ok = is_int($k) ? self::normName($v) : self::normName((string) $k);
                    $opts[$ok] = $v;
                }
                if (!$opts) $errors[] = __('Auswahlfeld „{label}“: bitte Auswahlmöglichkeiten angeben.', ['label' => $label]);
                $row['options'] = array_slice($opts, 0, 40, true);
            }
            if ($type === 'repeater') {
                [$subs, $se] = self::normFields((array) ($f['fields'] ?? []), true);
                foreach ($se as $e) $errors[] = $label . ': ' . $e;
                if (!$subs) $errors[] = __('Liste „{label}“: bitte mindestens ein Unterfeld anlegen.', ['label' => $label]);
                $row['fields'] = $subs;
                $row['item_label'] = mb_substr(trim(strip_tags((string) ($f['item_label'] ?? ''))), 0, 40) ?: __('Eintrag');
                $row['max_items'] = max(1, min(50, (int) ($f['max_items'] ?? 12) ?: 12));
                $titles = array_values(array_filter($subs, fn($x) => $x['type'] === 'text'));
                if ($titles) $row['title_field'] = $titles[0]['name'];
            }
            if (array_key_exists('default', $f) && $f['default'] !== '' && $f['default'] !== null && !in_array($type, ['repeater', 'media', 'file'], true)) {
                [$v] = \Core\Fields::clean($row, $f['default']);
                if ($v !== '' && $v !== null) $row['default'] = $v;
            }
            $out[] = $row;
        }
        return [$out, $errors];
    }

    private static function normJsonld(array $j, array $fields): array
    {
        $names = array_column($fields, null, 'name');
        $type = in_array($j['type'] ?? '', ['faq', 'item'], true) ? $j['type'] : 'none';
        if ($type === 'faq') {
            $items = (string) ($j['items'] ?? '');
            $subs = array_column((array) ($names[$items]['fields'] ?? []), 'name');
            if (($names[$items]['type'] ?? '') !== 'repeater' || !in_array($j['question'] ?? '', $subs, true) || !in_array($j['answer'] ?? '', $subs, true)) {
                return ['type' => 'none'];
            }
            return ['type' => 'faq', 'items' => $items, 'question' => (string) $j['question'], 'answer' => (string) $j['answer']];
        }
        if ($type === 'item') {
            $schema = in_array($j['schema'] ?? '', self::SCHEMAS, true) ? $j['schema'] : 'Service';
            $list = (string) ($j['list'] ?? '');
            if ($list !== '' && ($names[$list]['type'] ?? '') !== 'repeater') $list = '';
            $avail = $list !== '' ? array_column((array) $names[$list]['fields'], 'name') : array_keys($names);
            $props = [];
            foreach ((array) ($j['props'] ?? []) as $p => $f) {
                if (in_array($p, self::PROPS, true) && in_array($f, $avail, true)) $props[$p] = (string) $f;
            }
            return $props ? ['type' => 'item', 'schema' => $schema, 'list' => $list, 'props' => $props] : ['type' => 'none'];
        }
        return ['type' => 'none'];
    }

    /** Beispieldaten auf das Feld-Schema bringen (gleiche Bereinigung wie Blockdaten) */
    public static function normSample(array $fields, array $sample): array
    {
        if (!$sample) return self::sample($fields);
        [$v] = \Core\Fields::sanitize($fields, $sample);
        return $v;
    }

    /** Beispieldaten für die Vorschau – deutlich als „Beispiel“ markiert */
    public static function sample(array $fields, int $n = 0): array
    {
        static $img = null, $file = null;
        if ($img === null) {
            $img = (int) app()->db->fetchValue("SELECT id FROM media WHERE mime LIKE 'image/%' ORDER BY id DESC LIMIT 1");
            $file = (int) app()->db->fetchValue("SELECT id FROM media WHERE mime NOT LIKE 'image/%' ORDER BY id DESC LIMIT 1");
        }
        $sfx = $n ? ' ' . $n : '';
        $out = [];
        foreach ($fields as $f) {
            $l = (string) $f['label'];
            $v = match ($f['type']) {
                'text' => __('Beispiel: {label}', ['label' => $l]) . $sfx,
                'textarea' => __('Beispiel: {label}', ['label' => $l]) . $sfx . "\n" . __('Zweite Zeile'),
                'richtext' => '<p>' . e(__('Beispiel: {label}', ['label' => $l]) . $sfx) . ' – <b>' . e(__('formatierter Text')) . '</b>.</p>',
                'inline' => e(__('Beispiel:')) . ' <b>' . e($l) . '</b>' . e($sfx),
                'media' => $img ?: null,
                'file' => $file ?: null,
                'link' => '/',
                'select' => (string) array_key_first((array) ($f['options'] ?? [])),
                'bool' => $n !== 2,
                'number' => 3 * max(1, $n),
                'date' => date('Y-m-d', strtotime('+' . (7 * max(1, $n)) . ' days')),
                'color' => '#0F766E',
                'icon' => ['star', 'heart', 'lightbulb', 'check-circle'][$n % 4],
                'repeater' => array_map(fn($i) => self::sample((array) ($f['fields'] ?? []), $i), [1, 2, 3]),
                default => '',
            };
            if (in_array($f['type'], ['text', 'textarea'], true) && !empty($f['max'])) $v = mb_substr((string) $v, 0, (int) $f['max']);
            $out[$f['name']] = $v;
        }
        return $out;
    }

    /**
     * Vorlage und CSS prüfen. @return array{errors: array<string, string>, warnings: list<string>, css: ?string}
     */
    public static function check(array $def): array
    {
        $errors = [];
        $warnings = [];
        $css = null;
        if (trim($def['template']) === '') {
            $errors['template'] = __('Die Vorlage ist leer.');
        } else {
            try {
                $t = Template::compile($def['template'], $def['fields'], $def['behaviours']);
                $warnings = array_merge($warnings, $t->warnings);
            } catch (TemplateError $e) {
                $errors['template'] = $e->display();
                $errors['template_line'] = (string) $e->templateLine;
            }
        }
        try {
            $r = Css::compile($def['css'], $def['key'], $def['behaviours']);
            $css = $r['css'];
            $warnings = array_merge($warnings, $r['warnings']);
        } catch (TemplateError $e) {
            $errors['css'] = $e->display();
            $errors['css_line'] = (string) $e->templateLine;
        }
        return ['errors' => $errors, 'warnings' => $warnings, 'css' => $css];
    }

    // ================================================================== Schreiben

    /**
     * Arbeitsstand speichern (neuer Block, wenn $key null) und im Verlauf ablegen.
     * @return array{key: ?string, errors: array}
     */
    public static function save(?string $key, array $in, int $userId, string $kind = 'save', string $note = ''): array
    {
        $row = $key !== null ? self::find($key) : null;
        if ($key !== null && !$row) return ['key' => null, 'errors' => ['key' => __('Block nicht gefunden.')]];
        [$def, $errors] = self::normalize($in, $row['key'] ?? null);
        if ($row === null && !isset($errors['key']) && self::find($def['key'])) {
            $errors['key'] = __('Den Kurznamen „{key}“ gibt es schon.', ['key' => $def['key']]);
        }
        if ($row === null && !isset($errors['key']) && isset(app()->theme->blocks()[self::type($def['key'])])) {
            $errors['key'] = __('Den Kurznamen „{key}“ gibt es schon.', ['key' => $def['key']]);
        }
        if ($errors) return ['key' => null, 'errors' => $errors];
        $db = app()->db;
        $cols = [
            'label' => $def['label'], 'icon' => $def['icon'], 'bgroup' => $def['group'], 'description' => $def['description'],
            'fields_json' => json_encode($def['fields'], JSON_UNESCAPED_UNICODE), 'template' => $def['template'], 'css' => $def['css'],
            'behaviours' => implode(',', $def['behaviours']), 'jsonld_json' => json_encode($def['jsonld'], JSON_UNESCAPED_UNICODE),
            'settings_json' => json_encode($def['settings'], JSON_UNESCAPED_UNICODE), 'sample_json' => json_encode($def['sample'], JSON_UNESCAPED_UNICODE),
            'updated_by' => $userId ?: null, 'updated_at' => now(),
        ];
        if ($row === null) {
            $id = $db->insert('custom_blocks', $cols + ['bkey' => $def['key'], 'status' => 'draft', 'version' => 0, 'created_at' => now()]);
        } else {
            $id = (int) $row['id'];
            $last = $db->fetchValue('SELECT data_json FROM custom_block_versions WHERE block_id = ? ORDER BY id DESC LIMIT 1', [$id]);
            $db->update('custom_blocks', $cols, 'id = :id', ['id' => $id]);
            if ($kind === 'save' && is_string($last) && self::hash((array) json_decode($last, true)) === self::hash($def)) {
                return ['key' => $def['key'], 'errors' => []];   // keine Änderung → kein neuer Verlaufseintrag
            }
        }
        self::version($id, $kind, $def, $userId, $note);
        return ['key' => $def['key'], 'errors' => []];
    }

    private static function version(int $id, string $kind, array $def, int $userId, string $note = ''): void
    {
        $db = app()->db;
        $db->insert('custom_block_versions', ['block_id' => $id, 'kind' => $kind, 'data_json' => json_encode($def, JSON_UNESCAPED_UNICODE),
            'note' => mb_substr($note, 0, 180) ?: null, 'user_id' => $userId ?: null, 'created_at' => now()]);
        $ids = array_column($db->fetchAll('SELECT id FROM custom_block_versions WHERE block_id = ? ORDER BY id DESC', [$id]), 'id');
        foreach (array_slice($ids, self::MAX_VERSIONS) as $old) $db->query('DELETE FROM custom_block_versions WHERE id = ?', [$old]);
    }

    public static function versions(string $key): array
    {
        $row = self::find($key);
        if (!$row) return [];
        return app()->db->fetchAll('SELECT v.id, v.kind, v.note, v.created_at, u.name AS user_name, u.email AS user_email FROM custom_block_versions v
            LEFT JOIN users u ON u.id = v.user_id WHERE v.block_id = ? ORDER BY v.id DESC', [$row['id']]);
    }

    public static function restore(string $key, int $versionId, int $userId): array
    {
        $row = self::find($key);
        $v = $row ? app()->db->fetch('SELECT * FROM custom_block_versions WHERE id = ? AND block_id = ?', [$versionId, $row['id']]) : null;
        $data = $v ? json_decode((string) $v['data_json'], true) : null;
        if (!is_array($data)) return ['key' => null, 'errors' => ['version' => __('Fassung nicht gefunden.')]];
        return self::save($key, $data, $userId, 'restore', __('Fassung vom {date} wiederhergestellt', ['date' => (string) $v['created_at']]));
    }

    /**
     * Für die Redaktion freigeben: prüfen, CSS-Datei schreiben, veröffentlichte Fassung festhalten.
     * @return array{ok: bool, errors: array, warnings: list<string>}
     */
    public static function publish(string $key, int $userId): array
    {
        $row = self::find($key);
        if (!$row) return ['ok' => false, 'errors' => ['key' => __('Block nicht gefunden.')], 'warnings' => []];
        $def = self::definition($row);
        [$def] = self::normalize($def, $key);
        $chk = self::check($def);
        if ($chk['errors']) return ['ok' => false, 'errors' => $chk['errors'], 'warnings' => $chk['warnings']];
        $file = self::writeCss($key, (string) $chk['css']);
        $pub = $def;
        unset($pub['sample']);
        $pub['css_file'] = $file;
        $pub['version'] = $row['version'] + 1;
        app()->db->update('custom_blocks', ['published_json' => json_encode($pub, JSON_UNESCAPED_UNICODE), 'status' => 'published',
            'version' => $row['version'] + 1, 'published_at' => now(), 'updated_by' => $userId ?: null, 'updated_at' => now()], 'id = :id', ['id' => $row['id']]);
        self::version((int) $row['id'], 'publish', $def, $userId, __('Freigegeben (Version {n})', ['n' => $row['version'] + 1]));
        self::cleanupCss($key, $file);
        self::forget();
        PageCache::clear();
        return ['ok' => true, 'errors' => [], 'warnings' => $chk['warnings']];
    }

    /** Aus der Schublade nehmen – bestehende Seiten zeigen den Block weiter */
    public static function withdraw(string $key): bool
    {
        $row = self::find($key);
        if (!$row || $row['published'] === null) return false;
        app()->db->update('custom_blocks', ['status' => 'withdrawn', 'updated_at' => now()], 'id = :id', ['id' => $row['id']]);
        self::forget();
        PageCache::clear();
        return true;
    }

    /** Seiten, die den Block verwenden (Entwurf oder veröffentlicht) */
    public static function usages(string $key): array
    {
        $like = '%"type":"' . self::type($key) . '"%';
        return app()->db->fetchAll('SELECT id, title, status, type FROM pages WHERE content_draft LIKE ? OR content_published LIKE ? ORDER BY title', [$like, $like]);
    }

    /** Löschen (nur ohne Verwendung) */
    public static function delete(string $key): ?string
    {
        $row = self::find($key);
        if (!$row) return __('Block nicht gefunden.');
        if ($n = count(self::usages($key))) return __('Der Block wird noch auf {n} Seite(n) verwendet – dort zuerst entfernen oder den Block zurückziehen.', ['n' => $n]);
        app()->db->query('DELETE FROM custom_block_versions WHERE block_id = ?', [$row['id']]);
        app()->db->query('DELETE FROM custom_blocks WHERE id = ?', [$row['id']]);
        self::cleanupCss($key, null);
        self::forget();
        PageCache::clear();
        return null;
    }

    private static function writeCss(string $key, string $css): string
    {
        $dir = site()->mediaDir('blocks');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $name = 'cblk-' . str_replace('_', '-', $key) . '-' . substr(hash('sha256', $css), 0, 10) . '.css';
        if (@file_put_contents($dir . '/' . $name, $css) === false) throw new \RuntimeException(__('CSS-Datei konnte nicht geschrieben werden ({dir}).', ['dir' => $dir]));
        return $name;
    }

    private static function cleanupCss(string $key, ?string $keep): void
    {
        foreach (glob(site()->mediaDir('blocks') . '/cblk-' . str_replace('_', '-', $key) . '-*.css') ?: [] as $f) {
            if (basename($f) !== $keep && preg_match('~^cblk-' . preg_quote(str_replace('_', '-', $key), '~') . '-[0-9a-f]{10}\.css$~', basename($f))) @unlink($f);
        }
    }

    // ================================================================== Export, Import, Bibliothek

    public static function export(string $key): ?array
    {
        $row = self::find($key);
        if (!$row) return null;
        return ['format' => 'mycms-block', 'format_version' => 1, 'cms' => CMS_VERSION, 'exported_at' => date('c'), 'site' => site()->key,
            'block' => self::definition($row)];
    }

    /** JSON-Export übernehmen – immer als neuer Entwurf. @return array{key: ?string, errors: array} */
    public static function import(string $json, int $userId, string $kind = 'import'): array
    {
        $d = json_decode($json, true);
        if (!is_array($d) || ($d['format'] ?? '') !== 'mycms-block' || !is_array($d['block'] ?? null)) {
            return ['key' => null, 'errors' => ['import' => __('Keine gültige Block-Datei (Format „mycms-block“).')]];
        }
        $b = $d['block'];
        $base = preg_match('~^[a-z][a-z0-9_]{1,30}$~', (string) ($b['key'] ?? '')) ? (string) $b['key'] : self::normName((string) ($b['label'] ?? 'block'));
        $key = $base;
        for ($i = 2; self::find($key) || isset(app()->theme->blocks()[self::type($key)]); $i++) $key = substr($base, 0, 27) . '_' . $i;
        $b['key'] = $key;
        return self::save(null, $b, $userId, $kind, __('Importiert'));
    }

    public static function libraryDir(): string
    {
        return ROOT . '/storage/blocks';
    }

    /** Netzwerk-Bibliothek: nur Netzwerk-Administration und Integratoren */
    public static function libraryAllowed(): bool
    {
        return Features::integrator();
    }

    public static function libraryList(): array
    {
        $out = [];
        foreach (glob(self::libraryDir() . '/*.json') ?: [] as $f) {
            $d = json_decode((string) file_get_contents($f), true);
            if (!is_array($d) || ($d['format'] ?? '') !== 'mycms-block') continue;
            $out[] = ['file' => basename($f), 'label' => (string) ($d['block']['label'] ?? basename($f)), 'description' => (string) ($d['block']['description'] ?? ''),
                'icon' => (string) ($d['block']['icon'] ?? 'package'), 'site' => (string) ($d['site'] ?? ''), 'exported_at' => (string) ($d['exported_at'] ?? '')];
        }
        usort($out, fn($a, $b) => strcasecmp($a['label'], $b['label']));
        return $out;
    }

    public static function libraryPut(string $key): bool
    {
        $x = self::export($key);
        if (!$x) return false;
        $dir = self::libraryDir();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return @file_put_contents($dir . '/' . $key . '.json', json_encode($x, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
    }

    public static function libraryImport(string $file, int $userId): array
    {
        $file = basename($file);
        $path = self::libraryDir() . '/' . $file;
        if (!preg_match('~^[a-z0-9_]+\.json$~', $file) || !is_file($path)) return ['key' => null, 'errors' => ['import' => __('Datei nicht gefunden.')]];
        return self::import((string) file_get_contents($path), $userId, 'library');
    }

    /**
     * „Als Theme-Block exportieren“: ZIP mit PHP-Renderer (aus der Vorlage übersetzt), CSS, theme.php-Ausschnitt, JSON.
     * @return array{file: string, name: string}
     */
    public static function themeExport(string $key): array
    {
        $row = self::find($key) ?? throw new \RuntimeException(__('Block nicht gefunden.'));
        [$def] = self::normalize(self::definition($row), $key);
        $chk = self::check($def);
        if ($chk['errors']) throw new \RuntimeException(__('Bitte zuerst die Fehler in Vorlage bzw. CSS beheben.'));
        $php = Template::compile($def['template'], $def['fields'], $def['behaviours'])->toPhp($def['label']);
        $cssName = 'css/cblk-' . str_replace('_', '-', $key) . '.css';
        $themeDef = ['label' => $def['label'], 'icon' => $def['icon'], 'group' => $def['group'] ?: 'Inhalt',
            'help' => $def['settings']['help'] ?: $def['description'], 'fields' => $def['fields'],
            'cblk' => ['key' => $key, 'behaviours' => $def['behaviours'], 'width' => $def['settings']['width']]];
        if (($def['settings']['background'] ?? '') !== '') $themeDef['background'] = $def['settings']['background'];
        $jsonld = $def['jsonld']['type'] === 'faq'
            ? "\n        'jsonld' => " . self::export_var(['type' => 'faq', 'items' => $def['jsonld']['items'], 'question' => $def['jsonld']['question'], 'answer' => $def['jsonld']['answer']], 2) . ','
            : ($def['jsonld']['type'] === 'item' ? "\n        'jsonld' => fn(\\Core\\Block \$b) => \\Core\\Blocks\\Custom::jsonld(\$b, " . self::export_var($def['jsonld'], 2) . '),' : '');
        $snippet = "<?php\n// theme.php → 'blocks' – Block „{$def['label']}“ (exportiert aus dem Block-Designer, " . date('Y-m-d') . ")\n"
            . "// Dateien: blocks/{$key}.php → kits/{name}/blocks/, {$cssName} → kits/{name}/assets/{$cssName} (Build kopiert nach public/assets/kits/{name}/{$cssName})\n"
            . "return [\n    '{$key}' => " . rtrim(substr(self::export_var($themeDef, 1), 0, -1)) . $jsonld . "\n    ],\n];\n\n"
            . "// theme.php → 'conditional_css' (CSS nur auf Seiten mit dem Block):\n// '{$cssName}' => ['{$key}'],\n";
        $readme = "Block „{$def['label']}“ als Kit-Block\n" . str_repeat('=', 40) . "\n\n"
            . "1. blocks/{$key}.php nach kits/<kit>/blocks/ kopieren.\n"
            . "2. {$cssName} nach kits/<kit>/assets/{$cssName} kopieren (bzw. direkt nach public/assets/kits/<theme>/{$cssName}).\n"
            . "3. Den Eintrag aus theme-snippet.php in theme.php unter 'blocks' einfügen und 'conditional_css' ergänzen.\n"
            . "4. Blocktyp heißt „{$key}“. Klassen (.cblk-" . str_replace('_', '-', $key) . ") und Ausgabe sind identisch zum eigenen Block;\n"
            . "   der Renderer nutzt Core\\Blocks\\Runtime (Escaping, Filter, Grenzen) und bleibt damit sicher.\n"
            . "5. Bestehende Seiten mit dem eigenen Block (Typ cblk_{$key}) werden nicht automatisch umgestellt.\n\n"
            . "block.json lässt sich unter Verwaltung → Blöcke → Importieren wieder einlesen.\n";
        $tmp = tempnam(sys_get_temp_dir(), 'cblk');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException(__('ZIP-Datei konnte nicht erstellt werden.'));
        $dir = 'block-' . $key . '/';
        $zip->addFromString($dir . 'blocks/' . $key . '.php', $php);
        $zip->addFromString($dir . $cssName, (string) $chk['css']);
        $zip->addFromString($dir . 'theme-snippet.php', $snippet);
        $zip->addFromString($dir . 'block.json', (string) json_encode(self::export($key), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->addFromString($dir . 'README.txt', $readme);
        $zip->close();
        return ['file' => $tmp, 'name' => 'block-' . $key . '.zip'];
    }

    /** var_export mit kurzer Array-Schreibweise und Einrückung */
    public static function export_var(mixed $v, int $level = 0): string
    {
        if (!is_array($v)) return var_export($v, true);
        if ($v === []) return '[]';
        $pad = str_repeat('    ', $level + 1);
        $list = array_is_list($v);
        $out = "[\n";
        foreach ($v as $k => $x) $out .= $pad . ($list ? '' : var_export($k, true) . ' => ') . self::export_var($x, $level + 1) . ",\n";
        return $out . str_repeat('    ', $level) . ']';
    }
}
