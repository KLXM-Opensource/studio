<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Features;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\RateLimiter;
use Core\Sources\Mapper;
use Core\Sources\Sources;
use Core\Sources\Sync;

/**
 * Daten → Externe Quellen (Core\Sources): Quellen anlegen, Zuordnung mit Vorschau, abrufen, ZIP hochladen, Protokoll.
 * Funktion „sources“ (Standard aus; Netzwerk-Administration/Integratoren schalten sie hier ein), Recht „sources.manage“.
 */
final class SourceController extends AdminController
{
    /** Daten-Navigation links wie bei den Tabellen */
    protected function view(string $view, array $vars = [], int $status = 200): Response
    {
        $vars['drill'] ??= \Core\Theme::capture(ROOT . '/app/Admin/views/data/_nav.php', ['cur' => 'sources', 'active' => null]);
        $vars['drillTitle'] ??= __('Daten');
        return parent::view($view, $vars, $status);
    }

    private function gate(Request $r): array
    {
        $u = $this->auth($r);
        if (!Sources::canManage()) {
            throw new HttpException(403, Sources::enabled() || !Features::integrator()
                ? __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.')
                : __('Die Funktion „Externe Quellen“ ist auf dieser Website ausgeschaltet.'));
        }
        return $u;
    }

    private function source(string $id): array
    {
        return Sources::find((int) $id) ?? throw new HttpException(404, __('Quelle nicht gefunden.'));
    }

    // ------------------------------------------------------------------ Übersicht

    public function index(Request $r): Response
    {
        $this->auth($r);
        if (!Sources::navVisible()) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        return $this->view('data/sources', ['title' => __('Externe Quellen'), 'sources' => Sources::canManage() ? Sources::all() : [],
            'presets' => array_filter(Sources::presets(), fn($p) => empty($p['hidden'])), 'enabled' => Sources::enabled(),
            'configured' => Sources::configured(), 'integrator' => Features::integrator()]);
    }

    /** Funktion für diese Website ein-/ausschalten (nur Netzwerk-Administration/Integratoren, solange config nichts vorgibt) */
    public function toggle(Request $r): Response
    {
        $this->auth($r);
        if (!Features::integrator()) throw new HttpException(403, __('Keine Berechtigung.'));
        if (Sources::configured() !== null) return $this->back('/admin/quellen', 'error', __('Die Funktion ist in der Konfiguration der Website festgelegt (features → sources).'));
        $on = $r->str('on') === '1';
        app()->settings->set('sys.sources_enabled', $on ? 1 : 0);
        return $this->back('/admin/quellen', 'success', $on ? __('Externe Quellen sind jetzt eingeschaltet.') : __('Externe Quellen sind ausgeschaltet – vorhandene Einträge bleiben erhalten, es wird nichts mehr abgerufen.'));
    }

    // ------------------------------------------------------------------ Anlegen / Bearbeiten

    public function edit(Request $r, string $id = ''): Response
    {
        $this->gate($r);
        if ($id !== '') {
            $src = $this->source($id);
            return $this->form($src, $src, [], null);
        }
        $preset = Sources::presets()[$r->str('preset')] ?? Sources::presets()['rss'];
        $vals = ['id' => 0, 'name' => $preset['name'], 'format' => $preset['format'], 'url' => '', 'active' => true,
            'auth' => ['type' => 'none', 'header' => '', 'user' => '', 'secret' => ''], 'options' => ((array) ($preset['options'] ?? [])) + Sources::DEFAULTS,
            'mapping' => ['id_path' => '', 'slug_path' => '', 'rows' => []], 'table_handle' => null, 'table' => null];
        // Passende Tabelle gibt es schon (z. B. „immobilien“)? → vorwählen und Vorlage der Zuordnung übernehmen
        if (!empty($preset['table']) && ($t = Tables::findContent($preset['table']['handle'])) && !Tables::isShared($t)) {
            $vals['table_handle'] = $t['handle'];
            $vals['table'] = $t;
            $vals['mapping'] = self::fitMapping($t, (array) $preset['mapping']);
        }
        return $this->form(null, $vals, [], null, $r->str('preset'));
    }

    /** Vorlagen-Zuordnung auf die tatsächlich vorhandenen Felder beschränken */
    private static function fitMapping(array $t, array $mapping): array
    {
        $rows = array_intersect_key((array) ($mapping['rows'] ?? []), array_flip(array_column($t['fields'], 'name')));
        return ['id_path' => (string) ($mapping['id_path'] ?? ''), 'slug_path' => (string) ($mapping['slug_path'] ?? ''), 'rows' => $rows];
    }

    /** $x: notice (Hinweis über der Zuordnung), newTable (Vorschlag „Neue Tabelle“), focus (ID, die nach dem Laden den Fokus bekommt) */
    private function form(?array $src, array $vals, array $errors, ?array $preview, string $preset = '', int $status = 200, array $x = []): Response
    {
        $tables = array_values(array_filter(Tables::content(), fn($t) => !Tables::isShared($t)));
        return $this->view('data/source', ['title' => $src ? $src['name'] : __('Neue Quelle'), 'src' => $src, 'v' => $vals, 'errors' => $errors,
            'preview' => $preview, 'tables' => $tables, 'preset' => $preset, 'logs' => $src ? Sources::logs($src['id']) : [],
            'upload' => $src ? Sync::uploadFile($src['id']) : null, 'sample' => Sync::sample($vals), 'canSchema' => can('data.schema'),
            'notice' => $x['notice'] ?? null, 'newTable' => $x['newTable'] ?? null, 'focus' => $x['focus'] ?? null], $status);
    }

    public function save(Request $r, string $id = ''): Response
    {
        $this->gate($r);
        $src = $id !== '' ? $this->source($id) : null;
        $in = self::input($r);
        $do = $r->str('do');
        // Zieltabelle „+ Neue Tabelle aus dieser Quelle anlegen …“
        $wantNew = $in['table_handle'] === '__new';
        if ($wantNew) $in['table_handle'] = $in['mapping_table'] = '';
        if ($do === 'probe') return $this->probe($src, $in);
        if ($do === 'newtable') return $this->newTable($r, $src, $in);
        if (in_array($do, ['preview', 'suggest', 'dryrun'], true) || $wantNew) {
            [$vals, $errors] = Sources::normalize($src, $in);
            unset($errors['name']);
            $preview = null;
            $x = [];
            // Abrufen bei „Vorschau laden“ – sonst nur, wenn noch keine Beispiel-Einträge vorliegen
            if (($do === 'preview' || !Sync::sample($vals)) && (!$errors || array_keys($errors) === ['table_handle'])) {
                $limiter = new RateLimiter(app()->db);
                $key = 'sources:preview:' . (int) (app()->auth->user()['id'] ?? 0);
                if ($limiter->tooMany($key, 40, 600)) {
                    return $this->form($src, $vals, $errors + ['_' => __('Zu viele Abrufe in kurzer Zeit – bitte einige Minuten warten.')], null, '', 429);
                }
                $limiter->hit($key);
                $preview = Sync::preview($vals, $vals['table'], 5, $r->str('fresh') === '1');
                // Noch keine Zuordnung: Vorschlag aus den gefundenen Pfaden (bzw. Vorlage) ins Formular übernehmen
                if ($preview['suggested'] && $vals['table']) {
                    $preset = Sources::presets()[$vals['format']] ?? null;
                    $vals['mapping'] = !empty($preset['mapping']) ? self::fitMapping($vals['table'], (array) $preset['mapping']) : $preview['suggested'];
                    // Vorlage passt nur teilweise (eigene Tabelle): übrige Zeilen nach Name und Feldtyp vorschlagen
                    $sug = Mapper::suggest($vals['table'], $preview['paths'], $vals['format'], $vals['mapping']);
                    $vals['mapping'] = ['id_path' => $sug['id_path'], 'slug_path' => $sug['slug_path'], 'rows' => $sug['rows']];
                    $x['notice'] = ['ok', __('Zuordnung vorgeschlagen – bitte die Beispiele prüfen und speichern.')];
                }
            }
            $sample = Sync::sample($vals);
            // Tabelle gewählt, Zuordnung noch leer (Beispiele schon da): gleich vorschlagen
            if ($vals['table'] && $sample && !$vals['mapping']['rows'] && $vals['mapping']['id_path'] === '' && $do !== 'suggest') {
                $preset = Sources::presets()[$vals['format']] ?? null;
                $base = !empty($preset['mapping']) ? self::fitMapping($vals['table'], (array) $preset['mapping']) : null;
                $sug = Mapper::suggest($vals['table'], $sample['paths'], $vals['format'], $base);
                $vals['mapping'] = ['id_path' => $sug['id_path'], 'slug_path' => $sug['slug_path'], 'rows' => $sug['rows']];
                if ($sug['rows']) $x['notice'] = ['ok', __('Zuordnung vorgeschlagen – bitte die Beispiele prüfen und speichern.')];
            }
            if ($do === 'suggest') {
                $x['focus'] = 'zuordnung';
                if (!$vals['table']) $x['notice'] = ['warn', __('Bitte zuerst eine Zieltabelle wählen.')];
                elseif (!$sample) $x['notice'] = ['warn', __('Noch keine Vorschau: Erst Adresse eintragen und „Vorschau laden“.')];
                else {
                    $sug = Mapper::suggest($vals['table'], $sample['paths'], $vals['format'], $vals['mapping']);
                    $vals['mapping'] = ['id_path' => $sug['id_path'], 'slug_path' => $sug['slug_path'], 'rows' => $sug['rows']];
                    $x['notice'] = $sug['filled']
                        ? ['ok', __('Zuordnung vorgeschlagen für: {list}. Bitte die Beispiele prüfen und speichern.', ['list' => implode(', ', $sug['filled'])])]
                        : ['warn', __('Für die übrigen Felder gibt es keinen passenden Pfad in der Quelle – bitte von Hand zuordnen oder leer lassen.')];
                }
            }
            if ($wantNew) {
                $x['focus'] = 'src-newtable';
                $x['newTable'] = $sample
                    ? Mapper::proposeTable($sample['items'], $vals['format'], (string) ($sample['meta']['title'] ?? '') ?: $vals['name']) + ['detail' => true, 'list' => true, 'menu' => false, 'errors' => []]
                    : ['unavailable' => true];
            } elseif ($do === 'preview') {
                // Tabellenwechsel (data-autosubmit) → zur Zuordnung, sonst zu den Feldern der Quelle
                $x['focus'] = $vals['table'] && $in['table_handle'] !== $in['mapping_table'] ? 'zuordnung' : 'src-felder';
            } elseif ($do === 'dryrun') {
                $x['focus'] = 'probeabruf';
            }
            return $this->form($src, $vals, $errors, $preview, '', 200, $x);
        }
        [$newId, $errors, $vals] = Sources::save($src, $in);
        if ($errors) {
            return $this->form($src, $vals, $errors, null, '', 422);
        }
        $saved = Sources::find((int) $newId);
        $msg = $src ? __('Quelle gespeichert.') : __('Quelle angelegt.');
        if ($saved && $saved['table'] && ($miss = Mapper::missingRequired($saved['table'], $saved['mapping']))) {
            $msg .= ' ' . __('Hinweis: Pflichtfelder ohne Zuordnung: {list}', ['list' => implode(', ', $miss)]);
        } elseif ($saved && !$saved['table']) {
            $msg .= ' ' . __('Als Nächstes: Zieltabelle wählen oder aus der Vorlage anlegen.');
        }
        return $this->back('/admin/quellen/' . $newId, 'success', $msg);
    }

    /**
     * Beispiel je Feld und Probeabruf (JSON, ohne Abruf): rechnet die aktuelle – auch ungespeicherte – Zuordnung auf die
     * Beispiel-Einträge der letzten Vorschau. Für die Live-Anzeige unter jeder Zeile der Zuordnung.
     */
    private function probe(?array $src, array $in): Response
    {
        [$vals] = Sources::normalize($src, $in);
        $t = $vals['table'];
        $sample = Sync::sample($vals);
        if (!$t) return Response::json(['ok' => false, 'message' => __('Bitte zuerst eine Zieltabelle wählen.')]);
        if (!$sample) return Response::json(['ok' => false, 'message' => __('Noch keine Vorschau: Erst Adresse eintragen und „Vorschau laden“.')]);
        $miss = Mapper::missingRequired($t, $vals['mapping']);
        return Response::json(['ok' => true, 'fields' => Mapper::explain($t, $vals['mapping'], $sample['items'][0]),
            'items' => Mapper::preview($t, $vals['mapping'], $sample['items'], 3), 'missing' => $miss ? array_keys($miss) : [],
            'missing_text' => $miss ? __('Pflichtfelder ohne Zuordnung: {list}', ['list' => implode(', ', $miss)]) : '']);
    }

    /** „Neue Tabelle aus dieser Quelle“: Vorschlag aus dem Formular lesen (gleiche Form wie Mapper::proposeTable) */
    private static function newTableInput(array $p): array
    {
        $fields = [];
        foreach (array_slice((array) ($p['fields'] ?? []), 0, 60) as $f) {
            if (!is_array($f)) continue;
            $type = (string) ($f['type'] ?? 'text');
            $fields[] = ['on' => !empty($f['on']), 'label' => mb_substr(trim(strip_tags((string) ($f['label'] ?? ''))), 0, 80),
                'type' => in_array($type, Mapper::NEW_TABLE_TYPES, true) ? $type : 'text', 'path' => mb_substr(trim((string) ($f['path'] ?? '')), 0, 300),
                'sample' => mb_substr((string) ($f['sample'] ?? ''), 0, 160)];
        }
        return ['name' => mb_substr(trim(strip_tags((string) ($p['name'] ?? ''))), 0, 60), 'handle' => Tables::normName((string) ($p['handle'] ?? '')),
            'route' => trim((string) preg_replace('~[^a-z0-9-]+~', '-', strtolower((string) ($p['route'] ?? ''))), '-'),
            'detail' => !empty($p['detail']), 'list' => !empty($p['list']), 'menu' => !empty($p['menu']), 'fields' => $fields, 'errors' => []];
    }

    /** Tabelle aus dem Vorschlag anlegen, als Ziel setzen, Zuordnung füllen, Quelle speichern → Probeabruf */
    private function newTable(Request $r, ?array $src, array $in): Response
    {
        if (!can('data.schema')) throw new HttpException(403, __('Tabellen anlegen darf Ihre Rolle nicht (Recht „Tabellen und Felder ändern“).'));
        $nt = self::newTableInput((array) ($r->post['nt'] ?? []));
        [$vals, $errors] = Sources::normalize($src, $in);
        $fail = function (array $msgs) use ($src, $vals, $errors, $nt): Response {
            return $this->form($src, $vals, $errors, null, '', 422, ['newTable' => ['errors' => $msgs] + $nt, 'focus' => 'src-newtable']);
        };
        if ($errors) return $fail([__('Bitte zuerst die markierten Angaben der Quelle prüfen.')]);
        // Felder: Bezeichnung → Feldname (eindeutig, keine Systemnamen), Pfad merken
        $fields = [];
        $paths = [];
        foreach ($nt['fields'] as $f) {
            if (!$f['on'] || $f['label'] === '') continue;
            $name = Tables::normName($f['label']) ?: 'feld';
            while (in_array($name, Tables::SYSTEM, true) || isset($paths[$name])) $name .= '_2';
            $fields[] = ['label' => $f['label'], 'name' => $name, 'type' => $f['type'], 'searchable' => in_array($f['type'], ['text', 'textarea', 'richtext'], true) ? 1 : 0];
            $paths[$name] = $f['path'];
        }
        if (!$fields) return $fail([__('Bitte mindestens ein Feld auswählen.')]);
        $pick = function (array $types, string $re = '') use ($fields, $paths): string {
            foreach ($fields as $f) if (in_array($f['type'], $types, true) && ($re === '' || preg_match($re, $f['name'] . ' ' . $paths[$f['name']]))) return $f['name'];
            return '';
        };
        $title = $pick(['text'], '~(titel|title|name|headline)~') ?: $pick(['text']) ?: $fields[0]['name'];
        $date = $pick(['datetime', 'date']);
        foreach ($fields as &$f) {
            if ($f['name'] === $title) { $f['required'] = 1; $f['in_list'] = 1; }
            if ($f['name'] === $date) { $f['in_list'] = 1; $f['width'] = 'half'; }
        }
        unset($f);
        $name = $nt['name'] !== '' ? $nt['name'] : $vals['name'];
        [$freeHandle, $freeRoute] = Mapper::freeHandle($nt['handle'] !== '' ? $nt['handle'] : $name);
        $route = '';
        if ($nt['detail'] || $nt['list']) {
            $route = $nt['route'] !== '' ? $nt['route'] : $freeRoute;
            for ($n = 2, $base = $route; Tables::byRoute($route) || \Core\Pages::byPath($route); $n++) $route = $base . '-' . $n;
        }
        $def = ['name' => $name, 'singular' => $name, 'handle' => $freeHandle,
            'icon' => match ($vals['format']) { 'rss', 'atom' => 'newspaper', 'json' => 'brackets-curly', 'openimmo' => 'house-line', default => 'file-code' },
            'description' => __('Aus der externen Quelle „{name}“', ['name' => $vals['name']]), 'fields' => $fields,
            'settings' => ['route' => $route, 'title_field' => $title, 'image_field' => $pick(['media']), 'description_field' => $pick(['textarea']) ?: $pick(['richtext']),
                'sort_field' => $date ?: 'created_at', 'sort_dir' => 'desc', 'workflow' => 1]];
        [$clean, $terr] = Tables::validate($def);
        if ($terr) return $fail(array_values(array_map('strval', $terr)));
        Tables::create($clean);
        $t = Tables::find($clean['handle']);
        if (!$t) return $fail([__('Die Tabelle konnte nicht angelegt werden.')]);
        $made = [];
        try {
            // Übersichtsseite vor der Detailseite (die bekommt dann den Zurück-Link)
            if ($route !== '' && $nt['list'] && ($pid = \Core\Data\Templates::makeListPage($t))) {
                $made[] = __('Übersichtsseite /{route}', ['route' => $route]);
                if ($nt['menu']) { \Core\Pages::db()->update('pages', ['menu' => 1], 'id = :id', ['id' => $pid]); \Core\Pages::rebuildPaths(); }
            }
            if ($route !== '' && $nt['detail']) { DataController::makeTemplate(Tables::find($t['handle'])); $made[] = __('Detailseite'); }
        } catch (\Throwable $e) {
            error_log('[sources] new table pages: ' . $e->getMessage());
        }
        $t = Tables::find($t['handle']);
        // Zuordnung: Feld ← Pfad aus dem Vorschlag; Kennung und Alt-Text wie beim Vorschlag
        $rows = [];
        foreach ($paths as $fname => $path) {
            if ($path === '') continue;
            $rows[$fname] = ['path' => $path];
            // Teaser aus Beschreibung/Volltext: auf 200 Zeichen kürzen
            $fd = Tables::field($t, $fname);
            if ($fd && in_array($fd['type'], ['text', 'textarea'], true) && preg_match(Mapper::TEASER, $fname)) $rows[$fname] += ['tx' => 'truncate', 'opt' => '200'];
        }
        $sample = Sync::sample($vals);
        $sug = Mapper::suggest($t, $sample['paths'] ?? [], $vals['format'], ['id_path' => '', 'slug_path' => '', 'rows' => $rows]);
        $titlePath = $paths[$title] ?? '';
        foreach ($t['fields'] as $f) if (in_array($f['type'], ['media', 'file'], true) && isset($rows[$f['name']]) && $titlePath !== '') $rows[$f['name']]['alt'] = trim(explode('|', $titlePath)[0]);
        $in['table_handle'] = $in['mapping_table'] = $t['handle'];
        $in['mapping'] = ['id_path' => $sug['id_path'], 'slug_path' => '', 'rows' => $rows];
        [$newId, $serr] = Sources::save($src, $in);
        $this->changed();
        if ($serr || !$newId) return $this->back('/admin/data/' . $t['handle'], 'error', __('Tabelle angelegt, die Quelle konnte aber nicht gespeichert werden.'));
        return $this->back('/admin/quellen/' . $newId . '#probeabruf', 'success', __('Tabelle „{name}“ mit {n} Feldern angelegt{extra} und als Ziel zugeordnet. Prüfen Sie unten den Probeabruf – dann „Jetzt abrufen“.',
            ['name' => $t['name'], 'n' => count($t['fields']), 'extra' => $made ? ' (' . implode(', ', $made) . ')' : '']));
    }

    /** Formularfelder → Eingaben für Sources::normalize */
    private static function input(Request $r): array
    {
        $p = $r->post;
        return [
            'name' => (string) ($p['name'] ?? ''), 'format' => (string) ($p['format'] ?? ''), 'url' => (string) ($p['url'] ?? ''),
            'options' => (array) ($p['options'] ?? []), 'auth' => (array) ($p['auth'] ?? []), 'table_handle' => (string) ($p['table_handle'] ?? ''),
            'mapping_table' => (string) ($p['mapping_table'] ?? ($p['table_handle'] ?? '')), 'mapping' => (array) ($p['mapping'] ?? []),
            'active' => ($p['active'] ?? '') === '1',
        ];
    }

    // ------------------------------------------------------------------ Zieltabelle aus Vorlage

    public function createTable(Request $r, string $id): Response
    {
        $this->gate($r);
        if (!can('data.schema')) throw new HttpException(403, __('Tabellen anlegen darf Ihre Rolle nicht (Recht „Tabellen und Felder ändern“).'));
        $src = $this->source($id);
        $preset = Sources::presets()[$src['format']] ?? null;
        $def = $preset['table'] ?? [
            'name' => $src['name'], 'singular' => 'Eintrag', 'icon' => $src['format'] === 'xml' ? 'file-code' : 'brackets-curly', 'handle' => Tables::normName($src['name']),
            'description' => __('Aus der externen Quelle „{name}“', ['name' => $src['name']]),
            'fields' => [
                ['label' => 'Titel', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'searchable' => 1],
                ['label' => 'Datum', 'type' => 'date', 'in_list' => 1, 'width' => 'half'],
                ['label' => 'Link', 'type' => 'url', 'width' => 'half'],
                ['label' => 'Text', 'type' => 'textarea', 'searchable' => 1],
                ['label' => 'Bild', 'type' => 'media'],
            ],
            'settings' => ['route' => Tables::normName($src['name']) !== '' ? str_replace('_', '-', Tables::normName($src['name'])) : '', 'title_field' => 'titel', 'image_field' => 'bild',
                'description_field' => 'text', 'sort_field' => 'updated_at', 'sort_dir' => 'desc', 'workflow' => 1],
        ];
        // Kurzname und Adresse eindeutig machen
        $base = (string) $def['handle'];
        for ($n = 2; Tables::find($def['handle']); $n++) $def['handle'] = $base . '_' . $n;
        if ($def['handle'] !== $base) {
            $def['name'] .= ' ' . ($n - 1);
            if (!empty($def['settings']['route'])) $def['settings']['route'] .= '-' . ($n - 1);
        }
        foreach ($def['fields'] as &$f) $f['name'] = Tables::normName($f['label']);
        unset($f);
        [$clean, $errors] = Tables::validate($def);
        if ($errors) return $this->back('/admin/quellen/' . $src['id'], 'error', __('Tabelle konnte nicht angelegt werden: {error}', ['error' => implode(' ', array_map('strval', $errors))]));
        Tables::create($clean);
        $t = Tables::find($clean['handle']);
        if ($t && $t['settings']['route'] !== '') {
            try {
                $tplId = (new DataController())->makeTemplate($t);
                // OpenImmo: Detailseite mit Eckdaten (beschriftet), Texten, Bildern, Energieausweis und Kontakt
                if ($src['format'] === 'openimmo') {
                    $json = json_encode(['time' => (int) (microtime(true) * 1000), 'blocks' => \Core\Pages::sanitizeBlocks(\Core\Sources\OpenImmo::templateBlocks($t)), 'version' => '2.31'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    app()->db->update('pages', ['content_draft' => $json, 'content_published' => $json, 'updated_at' => now()], 'id = :id', ['id' => $tplId]);
                }
            } catch (\Throwable $e) {
                error_log('[sources] template: ' . $e->getMessage());
            }
            $t = Tables::find($clean['handle']);
        }
        $mapping = !empty($preset['mapping']) ? self::fitMapping($t, (array) $preset['mapping']) : ['id_path' => '', 'slug_path' => '', 'rows' => []];
        app()->db->update('ext_sources', ['table_handle' => $t['handle'], 'mapping_json' => json_encode($mapping, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'updated_at' => now()],
            'id = :id', ['id' => $src['id']]);
        $this->changed();
        return $this->back('/admin/quellen/' . $src['id'] . '#zuordnung', 'success', __('Tabelle „{name}“ mit Detailseite angelegt und zugeordnet. Prüfen Sie die Zuordnung mit „Vorschau & Test“.', ['name' => $t['name']]));
    }

    // ------------------------------------------------------------------ Abrufen / Hochladen / Löschen

    public function sync(Request $r, string $id): Response
    {
        $this->gate($r);
        $src = $this->source($id);
        $limiter = new RateLimiter(app()->db);
        $key = 'sources:sync:' . $src['id'];
        if ($limiter->tooMany($key, 30, 3600)) return $this->back('/admin/quellen/' . $src['id'], 'error', __('Zu viele Abrufe in kurzer Zeit – bitte einige Minuten warten.'));
        $limiter->hit($key);
        @set_time_limit(300);
        $s = Sync::run($src, 'manual', ['force' => true]);
        return $this->back('/admin/quellen/' . $src['id'] . '#protokoll', $s['ok'] ? ($s['failed'] ? 'error' : 'success') : 'error', $s['message']);
    }

    /** OpenImmo: ZIP (openimmo.xml + Bilder) oder XML hochladen und sofort einlesen */
    public function upload(Request $r, string $id): Response
    {
        $this->gate($r);
        $src = $this->source($id);
        $f = $r->files['datei'] ?? null;
        $back = '/admin/quellen/' . $src['id'];
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
            return $this->back($back, 'error', __('Bitte eine ZIP- oder XML-Datei wählen (Upload fehlgeschlagen).'));
        }
        $max = min(\Core\Media::maxBytes(), max(5, (int) app()->config->get('sources_upload_mb', 100)) * 1024 * 1024);
        if ((int) $f['size'] > $max) return $this->back($back, 'error', __('Datei zu groß (mehr als {mb} MB).', ['mb' => (int) ($max / 1048576)]));
        $head = (string) file_get_contents((string) $f['tmp_name'], false, null, 0, 64);
        $isZip = str_starts_with($head, "PK\x03\x04");
        $isXml = !$isZip && preg_match('~^\s*(<\?xml|<)~', ltrim($head, "\xEF\xBB\xBF"));
        if (!$isZip && !$isXml) return $this->back($back, 'error', __('Nur ZIP (openimmo.xml + Bilder) oder XML-Dateien.'));
        $dir = Sync::dir($src['id']);
        $ext = $isZip ? 'zip' : 'xml';
        $new = $dir . '/incoming.' . $ext;
        if (!move_uploaded_file((string) $f['tmp_name'], $new)) return $this->back($back, 'error', __('Datei konnte nicht gespeichert werden.'));
        @set_time_limit(300);
        $s = Sync::run($src, 'upload', ['upload' => $new]);
        // Nur eine erfolgreich gelesene Datei ersetzt die bisherige (für „Jetzt abrufen“ ohne Adresse)
        if ($s['ok']) {
            foreach (['upload.zip', 'upload.xml'] as $old) @unlink($dir . '/' . $old);
            @rename($new, $dir . '/upload.' . $ext);
        } else {
            @unlink($new);
        }
        return $this->back($back . '#protokoll', $s['ok'] ? ($s['failed'] ? 'error' : 'success') : 'error', $s['message']);
    }

    public function delete(Request $r, string $id): Response
    {
        $this->gate($r);
        $src = $this->source($id);
        $withEntries = $r->str('entries') === '1';
        if ($withEntries && $src['table'] && !can('data.delete', $src['table']['handle'])) throw new HttpException(403, __('Löschen ist Ihrer Rolle nicht erlaubt.'));
        $n = Sources::delete($src, $withEntries);
        return $this->back('/admin/quellen', 'success', $withEntries
            ? __('Quelle „{name}“ und {n} Einträge gelöscht.', ['name' => $src['name'], 'n' => $n])
            : __('Quelle „{name}“ gelöscht – die übernommenen Einträge bleiben als normale, bearbeitbare Einträge erhalten.', ['name' => $src['name']]));
    }

    /** Übernommenen Eintrag ein-/ausblenden (Redaktion mit Veröffentlichungsrecht) */
    public function entryStatus(Request $r, string $handle, string $id): Response
    {
        $this->auth($r);
        $t = Tables::findContent($handle) ?? throw new HttpException(404);
        if (!can('data.publish', $handle)) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        $e = Entries::find($t, (int) $id) ?? throw new HttpException(404);
        $on = $r->str('state') === 'published';
        Entries::setStatus($t, [(int) $e['id']], $on ? 'published' : 'draft');
        // Von Hand ausgeblendet: der Abgleich blendet ihn nicht wieder ein
        app()->db->query('UPDATE ext_source_items SET hidden_by_sync = 0 WHERE table_handle = ? AND entry_id = ?', [$t['handle'], (int) $e['id']]);
        $this->changed();
        return $this->back('/admin/data/' . $handle . '/' . (int) $e['id'], 'success', $on ? __('Eintrag ist wieder online.') : __('Eintrag ausgeblendet (Entwurf) – er bleibt es auch nach dem nächsten Abruf.'));
    }
}
