<?php
declare(strict_types=1);

namespace Core\AI;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Features;
use Core\Fields;
use Core\Lang;
use Core\Pages;

/**
 * Generatoren im Bereich „KLXM Ai“: Tabellen-Generator und Seiten-Generator.
 * Die KI liefert einen Entwurf, den die Person in einer Vorschau bearbeitet; angelegt wird über die normalen Wege
 * (Tables::validate/create, Pages::create als Entwurf) – eine spätere Prüf-Ebene kann dort eingreifen.
 * Seiten mit „[bitte ergänzen: …]“ lassen sich nicht veröffentlichen (Pages::publish).
 */
final class Generator
{
    /** Feldtypen, die der Tabellen-Generator vorschlagen darf (Gruppen brauchen Unterfelder – die legt man von Hand an) */
    public static function tableTypes(): array
    {
        $out = [];
        foreach (Tables::TYPES as $k => $t) {
            if ($k === 'group') continue;
            if (in_array($k, ['relation', 'relations'], true) && !Tables::content()) continue;
            $out[$k] = $t[0];
        }
        return $out;
    }

    /** Vorschlag für eine neue Tabelle → ['def' => Formularwerte für Tables::validate, 'errors' => Prüfhinweise, 'types' => …] */
    public static function proposeTable(string $description): array
    {
        if (mb_strlen(trim($description)) < 8) throw new AiException(__('Bitte beschreiben Sie kurz, was die Tabelle enthalten soll.'));
        $types = self::tableTypes();
        $tables = array_column(Tables::content(), 'name', 'handle');
        $fake = json_encode(['name' => 'Test-Tabelle', 'singular' => 'Test', 'icon' => '▤', 'description' => 'Test-KI', 'route' => '', 'fields' => [
            ['label' => 'Titel', 'name' => 'titel', 'type' => 'text', 'required' => true, 'in_list' => true],
            ['label' => 'Beschreibung', 'name' => 'beschreibung', 'type' => 'textarea']]], JSON_UNESCAPED_UNICODE);
        $d = Assist::json(Assist::call('text', Prompts::tableGen(mb_substr($description, 0, 2000), $types, $tables, array_keys(\Core\StructuredData::TYPES)), (string) $fake)['text']);
        $in = self::tableInput($d);
        [, $errors] = Tables::validate($in);
        return ['def' => $in, 'errors' => $errors, 'types' => $types, 'tables' => $tables];
    }

    /** KI-Antwort bzw. bearbeitete Vorschau → Formularwerte wie im Tabellen-Designer */
    public static function tableInput(array $d): array
    {
        $types = self::tableTypes();
        $fields = [];
        $names = [];
        foreach (array_slice((array) ($d['fields'] ?? []), 0, 25) as $f) {
            if (!is_array($f)) continue;
            $label = mb_substr(trim(strip_tags((string) ($f['label'] ?? ''))), 0, 60);
            if ($label === '') continue;
            $type = isset($types[(string) ($f['type'] ?? '')]) ? (string) $f['type'] : 'text';
            // Verknüpfung nur zu vorhandenen Inhaltstabellen – sonst einfaches Textfeld
            if (in_array($type, ['relation', 'relations'], true) && !Tables::findContent(Tables::normName((string) ($f['target'] ?? '')))) $type = 'text';
            $name = Tables::normName((string) ($f['name'] ?? '') ?: $label);
            $opts = $f['options'] ?? [];
            $opts = is_array($opts) ? implode("\n", array_map(fn($o) => trim(strip_tags((string) $o)), $opts)) : trim(strip_tags((string) $opts));
            $row = ['label' => $label, 'name' => $name, 'type' => $type, 'required' => !empty($f['required']) ? 1 : 0,
                'in_list' => !empty($f['in_list']) ? 1 : 0, 'searchable' => !empty($f['searchable']) ? 1 : 0,
                'help' => mb_substr(trim(strip_tags((string) ($f['help'] ?? ''))), 0, 200), 'options' => in_array($type, ['select', 'multiselect'], true) ? $opts : '',
                'target' => in_array($type, ['relation', 'relations'], true) ? Tables::normName((string) ($f['target'] ?? '')) : ''];
            $vi = $f['visible_if'] ?? null;
            if (is_array($vi) && isset($vi['field'], $vi['value']) && !isset($vi['rules'])) $vi = ['mode' => 'all', 'rules' => [['field' => (string) $vi['field'], 'op' => '=', 'value' => (string) $vi['value']]]];
            if (is_array($vi) && !empty($vi['rules'])) $row['visible_if'] = $vi;
            $fields[] = $row;
            $names[] = $name;
        }
        if ($fields && !array_filter($fields, fn($f) => $f['in_list'])) foreach (array_slice(array_keys($fields), 0, 3) as $k) $fields[$k]['in_list'] = 1;
        $pick = fn($k) => in_array((string) ($d[$k] ?? ''), $names, true) ? (string) $d[$k] : '';
        $uc = fn(string $x) => mb_strtoupper(mb_substr($x, 0, 1)) . mb_substr($x, 1);
        $cal = (array) ($d['calendar'] ?? []);
        $calendar = ['enabled' => !empty($cal['enabled']) ? 1 : 0];
        foreach (['start', 'end', 'all_day', 'recurrence', 'location', 'description'] as $k) $calendar[$k] = in_array((string) ($cal[$k] ?? ''), $names, true) ? (string) $cal[$k] : '';
        if (!$calendar['enabled'] || $calendar['start'] === '') $calendar = ['enabled' => 0];
        $name = $uc(mb_substr(trim(strip_tags((string) ($d['name'] ?? ''))), 0, 60));
        return [
            'name' => $name, 'handle' => Tables::normName((string) ($d['handle'] ?? '') ?: $name),
            'singular' => $uc(mb_substr(trim(strip_tags((string) ($d['singular'] ?? ''))), 0, 60)),
            'icon' => \Core\Icons::resolve((string) ($d['icon'] ?? '')) ?? (\Core\Icons::suggest($name, 1)[0] ?? 'table'),   // Symbolname (Core\Icons)
            'description' => mb_substr(trim(strip_tags((string) ($d['description'] ?? ''))), 0, 300),
            'fields' => $fields,
            'settings' => [
                'kind' => 'content', 'workflow' => 1,   // Entwürfe möglich – nichts geht ungeprüft online
                'route' => trim((string) preg_replace('~[^a-z0-9\-]+~', '-', strtolower((string) ($d['route'] ?? ''))), '-'),
                'title_field' => $pick('title_field') ?: ($names[0] ?? ''), 'image_field' => $pick('image_field'), 'description_field' => $pick('description_field'),
                'sort_field' => $pick('sort_field') ?: 'sort', 'sort_dir' => ($d['sort_dir'] ?? '') === 'desc' ? 'desc' : 'asc',
                'schema_type' => array_key_exists((string) ($d['schema_type'] ?? ''), \Core\StructuredData::TYPES) ? (string) $d['schema_type'] : '',
                'calendar' => $calendar,
            ],
        ];
    }

    /**
     * Tabelle anlegen (Tables::validate/create). Optional 1–3 deutlich markierte Beispiel-Einträge als ENTWURF – ohne KI,
     * ohne erfundene Angaben (Platzhalter „[Beispiel – bitte ersetzen]“).
     * @return array{handle: ?string, errors: array, examples: int}
     */
    public static function createTable(array $in, int $examples = 0): array
    {
        $in = self::tableInput($in);
        [$def, $errors] = Tables::validate($in);
        if ($errors) return ['handle' => null, 'errors' => $errors, 'examples' => 0];
        Tables::create($def);
        $t = Tables::find($def['handle']);
        $n = 0;
        for ($i = 1; $t && $i <= max(0, min(3, $examples)); $i++) {
            [$id, $err] = Entries::save($t, null, self::exampleEntry($t, $i));
            if ($id && !$err) $n++;
        }
        return ['handle' => $def['handle'], 'errors' => [], 'examples' => $n];
    }

    private static function exampleEntry(array $t, int $i): array
    {
        $mark = '[' . __('Beispiel – bitte ersetzen') . ']';
        $in = ['status' => 'draft', 'lang' => Lang::default()];
        foreach ($t['fields'] as $f) {
            $in[$f['name']] = match ($f['type']) {
                'text' => $f['name'] === ($t['settings']['title_field'] ?? '') ? __('Beispiel {n} – bitte ersetzen', ['n' => $i]) : $mark,
                'textarea' => $mark,
                'richtext' => '<p>' . e($mark) . '</p>',
                'select' => (string) array_key_first((array) ($f['options'] ?? [])),
                'date' => date('Y-m-d', strtotime('+' . (7 * $i) . ' days')),
                'datetime' => date('Y-m-d', strtotime('+' . (7 * $i) . ' days')) . ' 10:00',
                'time' => '10:00',
                'tel' => $mark,
                'email' => 'beispiel@example.org',
                'url' => 'https://example.org',
                'bool' => false,
                default => null,
            };
            if ($in[$f['name']] === null) unset($in[$f['name']]);
        }
        return $in;
    }

    // ================================================================== Seiten-Generator

    private const PAGE_SKIP = ['data_list', 'data_fields', 'data_form', 'calendar', 'upcoming', 'map', 'gallery', 'slideshow', 'contact', 'hours', 'doctors', 'downloads', 'embed'];
    private const PAGE_TYPES = ['text', 'textarea', 'richtext', 'inline', 'select', 'repeater'];

    /** Blöcke, die der Seiten-Generator füllen darf: freigegeben (Features::allowsBlock), mit Textfeldern, ohne dynamische Inhalte */
    public static function blockCatalog(): array
    {
        $out = [];
        foreach (app()->theme->blocks() as $type => $b) {
            if (in_array($type, self::PAGE_SKIP, true) || !Features::allowsBlock($type) || !empty($b['central'])) continue;
            $fields = self::catalogFields((array) ($b['fields'] ?? []));
            if (!array_filter($fields, fn($f) => in_array($f['type'], ['text', 'textarea', 'richtext', 'inline', 'repeater'], true))) continue;
            $out[$type] = ['type' => $type, 'label' => (string) $b['label'], 'help' => mb_substr((string) ($b['help'] ?? ''), 0, 160), 'fields' => $fields];
        }
        return $out;
    }

    private static function catalogFields(array $fields, int $depth = 0): array
    {
        $out = [];
        foreach ($fields as $f) {
            $type = (string) ($f['type'] ?? 'text');
            if (!isset($f['name']) || !in_array($type, self::PAGE_TYPES, true) || str_ends_with((string) $f['name'], 'link')) continue;
            $row = ['name' => $f['name'], 'type' => $type, 'label' => (string) ($f['label'] ?? $f['name'])];
            if ($type === 'select') $row['options'] = array_keys(Fields::options($f));
            if ($type === 'repeater') {
                if ($depth > 0) continue;
                $row['fields'] = self::catalogFields((array) ($f['fields'] ?? []), 1);
                if (!$row['fields']) continue;
            }
            if (!empty($f['max'])) $row['max'] = (int) $f['max'];
            $out[] = $row;
        }
        return $out;
    }

    /** Angaben der Website als einzige Faktenquelle: zentrale Textangaben + Titel/Ausschnitte passender Seiten */
    public static function siteFacts(string $topic, string $level = 'full'): string
    {
        if ($level === 'none') return '';
        $lines = ['Website: ' . site_name()];
        foreach (app()->theme->settingsFields() as $f) {
            if (!isset($f['name']) || !in_array($f['type'] ?? 'text', ['text', 'textarea', 'email', 'tel', 'url', 'inline', 'richtext'], true)) continue;
            // „Nur Name & Kontakt“: nur Kontaktfelder (E-Mail, Telefon, Adresse) – sonst zieht die KI das Profil der Organisation ins Thema
            if ($level === 'basic' && !in_array($f['type'] ?? '', ['email', 'tel', 'url'], true)
                && !preg_match('~(adress|anschrift|strasse|straße|ort|plz|telefon|phone|mail|kontakt|name)~iu', (string) $f['name'] . ' ' . ($f['label'] ?? ''))) continue;
            $v = app()->settings->get($f['name']);
            if (is_string($v) && trim($v) !== '' && !str_starts_with(trim($v), '[')) $lines[] = ($f['label'] ?? $f['name']) . ': ' . mb_substr(\Core\Search\Text::plain($v), 0, 300);
        }
        if ($level === 'basic') return mb_substr(implode("\n", $lines), 0, 2000);
        $words = array_filter(preg_split('~[^\p{L}\p{N}]+~u', mb_strtolower($topic)) ?: [], fn($w) => mb_strlen($w) >= 5);
        $pages = app()->db->fetchAll("SELECT * FROM pages WHERE type = 'page' AND status = 'published' AND " . Lang::sql(), [Lang::default()]);
        $lines[] = 'Vorhandene Seiten: ' . implode(', ', array_map(fn($p) => $p['title'] . ' (' . Pages::url($p) . ')', array_slice($pages, 0, 30)));
        $n = 0;
        foreach ($pages as $p) {
            $hay = mb_strtolower($p['title'] . ' ' . $p['meta_description']);
            if (!$words || !array_filter($words, fn($w) => str_contains($hay, $w))) continue;
            $lines[] = "\nAuszug „" . $p['title'] . "“: " . mb_substr(Assist::pageText($p, 1200), 0, 1200);
            if (++$n >= 3) break;
        }
        return mb_substr(implode("\n", $lines), 0, 7000);
    }

    /**
     * Seiten-Entwurf vorschlagen. $o: topic, audience, tone, language, blocks (erlaubte Typen, leer = alle)
     * @return array{title: string, slug: string, meta_description: string, sections: list<array>, markers: list<string>, warnings: list<string>}
     */
    public static function proposePage(array $o): array
    {
        $topic = trim(mb_substr((string) ($o['topic'] ?? ''), 0, 1500));
        if (mb_strlen($topic) < 8) throw new AiException(__('Bitte beschreiben Sie Thema und Ziel der Seite in ein, zwei Sätzen.'));
        $catalog = self::blockCatalog();
        $only = array_values(array_intersect(array_map('strval', (array) ($o['blocks'] ?? [])), array_keys($catalog)));
        if ($only) $catalog = array_intersect_key($catalog, array_flip($only));
        if (!$catalog) throw new AiException(__('Keine passenden Blöcke ausgewählt.'));
        $lang = Lang::valid((string) ($o['language'] ?? '')) ? (string) $o['language'] : Lang::default();
        $first = array_key_first($catalog);
        $fake = json_encode(['title' => 'Test: ' . mb_substr($topic, 0, 40), 'slug' => Pages::slugify(mb_substr($topic, 0, 30)), 'meta_description' => 'Test-KI [bitte ergänzen: Beschreibung]',
            'sections' => [['type' => $first, 'data' => [$catalog[$first]['fields'][0]['name'] => '[Test-KI] ' . $topic . ' [bitte ergänzen: Details]']]]], JSON_UNESCAPED_UNICODE);
        $level = in_array($o['context'] ?? '', ['none', 'basic', 'full'], true) ? (string) $o['context'] : 'basic';
        $pack = Prompts::pageGen($topic, array_values($catalog), self::siteFacts($topic, $level), Assist::ctx() + ['language' => $lang,
            'facts' => trim(mb_substr((string) ($o['facts'] ?? ''), 0, 4000)),
            'audience' => mb_substr((string) ($o['audience'] ?? ''), 0, 200), 'tone' => (string) ($o['tone'] ?? '')]);
        $d = Assist::json(Assist::call('text', $pack, (string) $fake)['text']);
        return self::pageDraft($d, $catalog) + ['language' => $lang];
    }

    /** KI-Antwort bzw. bearbeitete Vorschau bereinigen: nur bekannte Blöcke/Felder, Texte je Feldart gesäubert */
    public static function pageDraft(array $d, ?array $catalog = null): array
    {
        $catalog ??= self::blockCatalog();
        $sections = [];
        foreach (array_slice((array) ($d['sections'] ?? []), 0, 12) as $s) {
            $type = (string) ($s['type'] ?? '');
            if (!isset($catalog[$type]) || !is_array($s['data'] ?? null)) continue;
            $data = self::cleanData($catalog[$type]['fields'], $s['data']);
            if (!$data) continue;
            $sections[] = ['type' => $type, 'label' => $catalog[$type]['label'], 'fields' => $catalog[$type]['fields'], 'data' => $data];
        }
        $out = ['title' => mb_substr(trim(strip_tags((string) ($d['title'] ?? ''))), 0, 120), 'slug' => Pages::slugify((string) ($d['slug'] ?? $d['title'] ?? '')),
            'meta_description' => Assist::cut(trim(strip_tags((string) ($d['meta_description'] ?? ''))), 160), 'sections' => $sections];
        $out['markers'] = Pages::openMarkers(json_encode($out, JSON_UNESCAPED_UNICODE));
        $out['warnings'] = $sections ? [] : [__('Die KI hat keine verwendbaren Abschnitte geliefert. Bitte den Auftrag genauer fassen.')];
        return $out;
    }

    private static function cleanData(array $fields, array $data): array
    {
        $out = [];
        foreach ($fields as $f) {
            $v = $data[$f['name']] ?? null;
            if ($v === null || $v === '' || $v === []) continue;
            $out[$f['name']] = match ($f['type']) {
                'richtext' => Assist::clean(is_string($v) ? $v : '', 'rich'),
                'inline' => Assist::clean(is_string($v) ? $v : '', 'inline'),
                'text', 'textarea' => trim(strip_tags(is_scalar($v) ? (string) $v : '')),
                'select' => in_array((string) $v, $f['options'] ?? [], true) ? (string) $v : null,
                'repeater' => array_values(array_filter(array_map(fn($item) => is_array($item) ? self::cleanData($f['fields'], $item) : [], array_slice((array) $v, 0, 12)))),
                default => null,
            };
            if ($out[$f['name']] === null || $out[$f['name']] === '' || $out[$f['name']] === []) unset($out[$f['name']]);
        }
        return $out;
    }

    /** Seite als ENTWURF anlegen (Pages::create) – öffnet danach im Editor; veröffentlicht wird von Hand */
    public static function createPage(array $in): array
    {
        $d = self::pageDraft($in);
        if (!$d['sections']) throw new AiException(__('Keine Abschnitte zum Anlegen.'));
        if ($d['title'] === '') throw new AiException(__('Bitte einen Titel angeben.'));
        $lang = Lang::valid((string) ($in['language'] ?? '')) && $in['language'] !== Lang::default() ? (string) $in['language'] : null;
        $parent = ctype_digit((string) ($in['parent'] ?? '')) && (int) $in['parent'] > 0 && Pages::find((int) $in['parent']) ? (int) $in['parent'] : null;
        $slug = $d['slug'] ?: Pages::slugify($d['title']);
        if (!$parent && !$lang && \Core\PublicPaths::isReserved($slug)) $slug .= '-seite';
        $base = $slug;
        for ($n = 2; Pages::slugTaken($slug, $parent, null, $lang); $n++) $slug = $base . '-' . $n;
        $blocks = Pages::sanitizeBlocks(array_map(fn($s) => ['type' => $s['type'], 'data' => $s['data'], 'tunes' => ['section' => []]], $d['sections']));
        $sort = (int) app()->db->fetchValue('SELECT COALESCE(MAX(sort), 0) + 10 FROM pages WHERE ' . ($parent ? 'parent_id = ?' : 'parent_id IS NULL'), $parent ? [$parent] : []);
        $id = Pages::create(['slug' => $slug, 'title' => $d['title'], 'meta_description' => $d['meta_description'], 'status' => 'draft',
            'parent_id' => $parent, 'lang' => $lang, 'sort' => $sort, 'menu' => 0], $blocks);
        Pages::addRevision($id, (string) Pages::find($id)['content_draft'], (int) app()->auth->user()['id'], __('Mit dem Seiten-Generator angelegt (Entwurf)'));
        return ['id' => $id, 'url' => Pages::url(Pages::find($id)) . '?edit=1', 'settings' => url('/admin/pages/' . $id), 'markers' => $d['markers']];
    }

    /**
     * Block-Designer: Vorschlag für einen eigenen Block aus einer Beschreibung. Der Vorschlag wird mit dem Compiler geprüft
     * (Vorlage + CSS); bei Fehlern bekommt die KI einmal die Fehlermeldung zur Korrektur. Nichts wird gespeichert –
     * die Person prüft den Vorschlag im Block-Designer (Beispieldaten sind als „Beispiel“ markiert).
     * @return array{def: array, errors: array, warnings: list<string>}
     */
    public static function proposeBlock(string $description): array
    {
        $description = trim(mb_substr($description, 0, 2000));
        if (mb_strlen($description) < 8) throw new AiException(__('Bitte beschreiben Sie kurz, was der Block zeigen soll.'));
        $types = array_map(fn($t) => $t[0], \Core\Blocks\Custom::fieldTypes());
        $filters = array_keys(\Core\Blocks\Runtime::FILTERS);
        $fake = json_encode(['label' => 'Hinweis-Box', 'key' => 'hinweis_box', 'icon' => 'info', 'group' => 'Eigene Blöcke', 'description' => 'Hervorgehobener Hinweis mit Symbol.',
            'fields' => [['name' => 'title', 'label' => 'Überschrift', 'type' => 'text', 'required' => true], ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
                ['name' => 'symbol', 'label' => 'Symbol', 'type' => 'icon', 'default' => 'info']],
            'template' => "<aside class=\"note\" role=\"note\">\n  <span class=\"note__icon\" aria-hidden=\"true\">{{ symbol | icon }}</span>\n  <div class=\"note__body\">\n    <h2 class=\"note__title\" id=\"{{ block.title_id }}\">{{ title }}</h2>\n    <div class=\"note__text\">{{ text | rich }}</div>\n  </div>\n</aside>",
            'css' => ".note{display:flex;gap:1rem;padding:1.25rem 1.5rem;border:1px solid var(--cb-line);border-left:4px solid var(--cb-accent);border-radius:var(--cb-radius);background:var(--cb-surface)}\n.note__icon{font-size:1.75rem;color:var(--cb-accent);flex:none}\n.note__title{margin:0 0 .25rem;font-size:1.25rem}",
            'behaviours' => []], JSON_UNESCAPED_UNICODE);
        $pack = Prompts::blockGen($description, $types, $filters);
        $raw = Assist::call('text', $pack, (string) $fake);
        $out = self::blockCheck(Assist::json($raw['text']));
        if ($out['errors'] && !isset($out['errors']['label'])) {
            // Eine Korrekturrunde mit den Meldungen des Compilers
            $pack['user'] .= "\n\nDein letzter Vorschlag hatte Fehler:\n- " . implode("\n- ", array_filter($out['errors'], fn($k) => !str_ends_with((string) $k, '_line'), ARRAY_FILTER_USE_KEY))
                . "\nHier ist er zur Korrektur – gib den vollständigen, korrigierten Vorschlag erneut als JSON aus:\n" . mb_substr((string) $raw['text'], 0, 12000);
            try {
                $second = self::blockCheck(Assist::json(Assist::call('text', $pack, (string) $fake)['text']));
                if (count($second['errors']) <= count($out['errors'])) $out = $second;
            } catch (AiException) {
                // erster Vorschlag bleibt – mit Fehlerhinweisen
            }
        }
        return $out + ['model' => (string) ($raw['model'] ?? ''), 'ms' => (int) ($raw['ms'] ?? 0)];
    }

    private static function blockCheck(array $d): array
    {
        $in = ['label' => (string) ($d['label'] ?? ''), 'key' => (string) ($d['key'] ?? ''), 'icon' => (string) ($d['icon'] ?? ''), 'group' => (string) ($d['group'] ?? ''),
            'description' => (string) ($d['description'] ?? ''), 'fields' => is_array($d['fields'] ?? null) ? $d['fields'] : [],
            'template' => is_string($d['template'] ?? null) ? $d['template'] : '', 'css' => is_string($d['css'] ?? null) ? $d['css'] : '',
            'behaviours' => is_array($d['behaviours'] ?? null) ? $d['behaviours'] : [], 'settings' => ['help' => (string) ($d['description'] ?? '')]];
        [$def, $errors] = \Core\Blocks\Custom::normalize($in);
        if (\Core\Blocks\Custom::find($def['key'])) $def['key'] = substr($def['key'], 0, 26) . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
        $chk = \Core\Blocks\Custom::check($def);
        return ['def' => $def, 'errors' => $errors + $chk['errors'], 'warnings' => $chk['warnings']];
    }
}
