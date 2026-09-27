<?php
declare(strict_types=1);

namespace Core\Api;

use Core\Fields;
use Core\Lang;
use Core\Maps;
use Core\FormCrypto;
use Core\Forms;
use Core\Media;
use Core\PageCache;
use Core\Pages;

/**
 * Fachlogik für REST-API und MCP-Server (eine Implementierung, zwei Schnittstellen).
 * Alle Änderungen laufen durch dieselben Validierungen wie im Admin.
 * Inhaltsänderungen landen als Entwurf (mit Revision); veröffentlicht wird explizit.
 */
final class CmsService
{
    private const DAY_NAMES = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 0 => 'Sonntag'];

    /** Sprachkontext der Anfrage (API: ?lang=en, MCP: Parameter lang); null = Standardsprache bzw. alle */
    private ?string $lang = null;

    public function __construct(private readonly array $token) {}

    /** Sprache für diese Anfrage setzen – wirkt auf Seiten, Einträge, Einstellungen und Medientexte */
    public function useLang(?string $lang): void
    {
        $lang = strtolower(trim((string) $lang));
        if ($lang === '') return;
        if (!Lang::valid($lang)) {
            throw new ApiError(422, "Unbekannte Sprache „{$lang}“. Aktiv: " . implode(', ', array_keys(Lang::all())) . '.');
        }
        $this->lang = $lang;
        app()->lang = $lang;
    }

    /** Übersetzung (nicht die Standardsprache) aktiv? */
    private function foreign(): bool
    {
        return $this->lang !== null && $this->lang !== Lang::default();
    }

    private function userId(): ?int
    {
        return $this->token['user_id'] !== null ? (int) $this->token['user_id'] : null;
    }

    private function note(string $what): string
    {
        return (['mcp' => 'MCP', 'ai' => 'KI'][$this->origin['channel']] ?? 'API') . ' „' . $this->token['name'] . '“: ' . $what;
    }

    // ================================================================= Prüf-Ebene (Core\Review\Queue)

    /** Herkunft: api | mcp | ai, dazu MCP-Client bzw. KI-Funktion */
    private array $origin = ['channel' => 'api', 'client' => null, 'feature' => null];
    private bool $inGate = false;
    private ?int $change = null;
    private ?int $recheck = null;
    private ?bool $forceReview = null;

    public function origin(string $channel, ?string $client = null, ?string $feature = null): self
    {
        $this->origin = ['channel' => $channel, 'client' => $client, 'feature' => $feature];
        return $this;
    }

    /** KI-Übernahmen aus der Verwaltung (AiController): angemeldete Person als Urheber, Freigabe laut sys.review_ai */
    public static function forAi(string $feature): self
    {
        $u = app()->auth->user();
        $svc = new self(['id' => null, 'name' => \Core\AI\Assist::brand(), 'scope' => 'write', 'user_id' => $u['id'] ?? null, 'review_mode' => 'review']);
        $svc->forceReview = true;
        return $svc->origin('ai', null, $feature);
    }

    /** Freigabe einer Einreichung: direkt ausführen, Protokollzeile $id wird von Queue::approve aktualisiert */
    public function applyingChange(int $id): void
    {
        $this->change = $id;
    }

    /** Einreichung $id gegen den aktuellen Stand neu prüfen (Probelauf) */
    public function recheckChange(int $id): void
    {
        $this->recheck = $id;
    }

    /** Werden Änderungen dieses Zugangs zur Freigabe eingereicht statt ausgeführt? */
    public function reviewMode(): bool
    {
        if ($this->change !== null) return false;
        if (!\Core\Review\Queue::enabled()) return false;
        return $this->forceReview ?? (($this->token['review_mode'] ?? 'direct') === 'review');
    }

    /**
     * Jede Schreibaktion ruft zuerst gate() auf. Beim ersten Aufruf übernimmt die Prüf-Ebene (ausführen + protokollieren
     * bzw. einreichen → Core\Review\Pending) und ruft die Methode erneut auf; dann liefert gate() null und die Methode läuft.
     * $simulate: Folgezustand ohne Ausführung (Aktionen mit Dateien), sonst Probelauf in einer Transaktion.
     */
    private function gate(string $method, array $args, array $target, ?callable $simulate = null): mixed
    {
        if ($this->inGate) return null;
        $this->inGate = true;
        try {
            $out = \Core\Review\Queue::run([
                'token' => $this->token, 'origin' => $this->origin, 'lang' => $this->lang, 'review' => $this->reviewMode(),
                'change' => $this->change, 'recheck' => $this->recheck,
            ], $method, $args, $target, $simulate, fn() => $this->{$method}(...$args));
        } finally {
            $this->inGate = false;
        }
        return ['__gate' => $out];
    }

    /** Stand einer eigenen Einreichung (GET /changes/{id}, MCP get_change_status) */
    public function changeStatus(int $id): array
    {
        $row = \Core\Review\Queue::find($id);
        if (!$row || !isset($this->token['id']) || (int) $row['token_id'] !== (int) $this->token['id']) {
            throw new ApiError(404, 'Einreichung nicht gefunden (sichtbar sind nur Einreichungen dieses Tokens).');
        }
        return \Core\Review\Queue::publicJson($row);
    }

    /** Eigene Einreichungen, neueste zuerst (?status=pending|applied|rejected) */
    public function changesList(?string $status = null): array
    {
        $status = $status === 'pending_review' ? 'pending' : $status;
        $f = ['token' => (string) (int) ($this->token['id'] ?? 0)] + (in_array($status, \Core\Review\Queue::STATUSES, true) ? ['status' => $status] : []);
        return ['review_mode' => $this->reviewMode() ? 'review' : 'direct',
            'changes' => array_map(fn($r) => \Core\Review\Queue::publicJson(\Core\Review\Queue::find((int) $r['id'])), \Core\Review\Queue::list($f)['rows'])];
    }

    // ================================================================= Übersicht

    public function siteInfo(): array
    {
        $theme = app()->theme;
        return [
            'name' => site_name(),
            'url' => absolute_url('/'),
            'settings_title' => $theme->settingsTitle(),
            'features' => array_values(array_filter(['hours' => self::hasHours() ? 'hours' : null, 'notice' => self::hasNotice() ? 'notice' : null])),
            'theme' => ['name' => $theme->name, 'label' => $theme->label(), 'version' => (string) ($theme->def['version'] ?? '')],
            'site' => site()->key,
            'languages' => ['default' => Lang::default(), 'active' => Lang::all(), 'current' => $this->lang ?? Lang::default(),
                'hint' => Lang::multi() ? 'Sprache je Anfrage wählen: API ?lang=en, MCP-Parameter lang. Übersetzungen mit translate_page / translate_entry anlegen.' : 'Nur eine Sprache aktiv.'],
            'map' => ($loc = Maps::siteLocation()) ? ['location' => Maps::format($loc), 'geocode' => true] : ['location' => null, 'geocode' => true],
            'token' => ['name' => $this->token['name'], 'scope' => $this->token['scope']],
            'pages' => $this->pagesList(),
            'settings_groups' => array_map(fn($g) => ['id' => $g['id'], 'label' => $g['label']], $theme->settingsGroups()),
            'block_types' => array_map(fn($b) => $b['label'], $theme->blocks()),
            'forms' => array_map(fn($k) => ['key' => $k, 'enabled' => Forms::enabled($k), 'mode' => Forms::mode($k), 'table' => Forms::table($k)['handle'] ?? null], array_keys($theme->forms())),
            'inbox_tables' => array_column(\Core\Data\Inbox::tables(), 'handle'),
            'encryption_ready' => FormCrypto::ready(),
            'hint' => 'Inhaltsänderungen werden als Entwurf gespeichert. Mit publish_page (bzw. POST /pages/{id}/publish) veröffentlichen.',
        ];
    }

    /** Öffentliche Basisdaten (ohne Token abrufbar): Name, URL + was das Theme beisteuert */
    public static function publicInfo(): array
    {
        $svc = new self(['name' => 'public', 'scope' => 'read', 'user_id' => null]);
        $fn = project('public_info');
        $out = ['name' => site_name(), 'url' => absolute_url('/')]
            + (is_string($fn) && function_exists($fn) ? (array) $fn() : []);
        if (self::hasHours()) {
            $out['opening_hours'] = $svc->hoursGet();
        }
        if (self::hasNotice() && setting(project('notice.active'))) {
            $out['notice'] = \Core\EditorNotes::strip(strip_tags((string) setting(project('notice.text'))));
        }
        return array_filter($out, fn($v) => $v !== null && $v !== [] && $v !== '');
    }

    /** Bietet das Theme Öffnungszeiten bzw. einen Hinweisbalken an? */
    public static function hasHours(): bool
    {
        return (bool) project('hours.setting');
    }

    public static function hasNotice(): bool
    {
        return (bool) project('notice.text');
    }

    // ================================================================= Einstellungen (Titel vom Theme, z. B. „Praxisdaten“)

    public function settingsSchema(): array
    {
        return array_map(fn($g) => [
            'id' => $g['id'], 'label' => $g['label'],
            'fields' => array_values(array_map(fn($f) => $f + ['translatable' => Fields::translatable($f)],
                array_filter($g['fields'], fn($f) => ($f['type'] ?? '') !== 'heading'))),
        ], app()->theme->settingsGroups());
    }

    public function settingsGet(?string $group = null): array
    {
        $out = [];
        foreach (app()->theme->settingsGroups() as $g) {
            if ($group !== null && $g['id'] !== $group) {
                continue;
            }
            foreach ($g['fields'] as $f) {
                if (isset($f['name'])) {
                    $out[$f['name']] = setting($f['name']);
                }
            }
        }
        if ($group !== null && !$out) {
            throw new ApiError(404, "Einstellungsgruppe „{$group}“ gibt es nicht.");
        }
        if ($this->foreign()) {
            // Werte wie auf der Website in dieser Sprache (mit Rückfall) + welche Felder eigens übersetzt sind
            $out['_lang'] = $this->lang;
            $out['_translated'] = array_values(array_filter(array_keys($out), fn($k) => $k[0] !== '_' && app()->settings->get($k . '@' . $this->lang) !== null));
        }
        return $out;
    }

    /** Teil-Update: nur übergebene Felder werden geprüft und gespeichert. */
    public function settingsUpdate(array $values): array
    {
        if (($g = $this->gate(__FUNCTION__, [$values], ['type' => 'settings', 'keys' => array_keys($values), 'lang' => $this->foreign() ? $this->lang : null])) !== null) return $g['__gate'];
        $byName = [];
        foreach (app()->theme->settingsFields() as $f) {
            if (isset($f['name'])) {
                $byName[$f['name']] = $f;
            }
        }
        $unknown = array_diff(array_keys($values), array_keys($byName));
        if ($unknown) {
            throw new ApiError(422, 'Unbekannte Felder: ' . implode(', ', $unknown), ['unknown' => array_values($unknown)]);
        }
        if (!$values) {
            throw new ApiError(422, 'Keine Felder übergeben.');
        }
        $fields = array_values(array_intersect_key($byName, $values));
        if ($this->foreign()) {
            // Übersetzung: nur übersetzbare Felder, leere Werte = Rückfall auf die Standardsprache
            $fixed = array_values(array_map(fn($f) => $f['name'], array_filter($fields, fn($f) => !Fields::translatable($f))));
            if ($fixed) {
                throw new ApiError(422, 'Diese Felder gelten für alle Sprachen und werden ohne lang geändert: ' . implode(', ', $fixed), ['not_translatable' => $fixed]);
            }
            [$clean, $errors] = Fields::sanitize(Fields::forTranslation($fields), $values);
            if ($errors) {
                throw new ApiError(422, 'Validierung fehlgeschlagen.', ['errors' => $errors]);
            }
            foreach ($clean as $k => $v) {
                $v === null || $v === '' || $v === [] ? app()->settings->delete($k . '@' . $this->lang) : app()->settings->set($k . '@' . $this->lang, $v);
            }
            PageCache::clear();
            return $clean + ['_lang' => $this->lang];
        }
        [$clean, $errors] = Fields::sanitize($fields, $values);
        if ($errors) {
            throw new ApiError(422, 'Validierung fehlgeschlagen.', ['errors' => $errors]);
        }
        app()->settings->setMany($clean);
        PageCache::clear();
        return $clean;
    }

    // ================================================================= Design (Style-Editor, theme.php → 'design')

    private function designAvailable(): void
    {
        if (!\Core\Features::on('design', false)) throw new ApiError(404, 'Design-Einstellungen sind für diese Website abgeschaltet.');
        if (!\Core\Design::enabled()) throw new ApiError(404, 'Das aktive Kit „' . app()->theme->name . '“ bietet keine Design-Einstellungen an.');
    }

    /** Definition (Gruppen/Tokens, Schriften, Vorlagen), aktuelle Werte, Standardwerte und Kontrastprüfung */
    public function designGet(): array
    {
        $this->designAvailable();
        $values = \Core\Design::values();
        return ['theme' => app()->theme->name] + \Core\Design::schema() + [
            'values' => $values, 'defaults' => \Core\Design::defaults(),
            'contrast' => \Core\Design::checks($values),
            'history' => array_map(fn($h) => ['at' => $h['at'] ?? null, 'by' => $h['by'] ?? '', 'note' => $h['note'] ?? ''], \Core\Design::history()),
            'hint' => 'Ändern mit PATCH /design {"values": {…}} (nur übergebene Werte) oder {"preset": "…"} bzw. {"reset": true}. Farben #RRGGBB, dunkle Werte als „name@dark“.',
        ];
    }

    /**
     * Design ändern: values (Teil-Update), preset (Vorlage als Grundlage), reset (Theme-Standard als Grundlage).
     * Braucht ein write-Token, dessen Benutzer-Rolle das Recht design.edit hat.
     */
    public function designUpdate(array $in): array
    {
        $this->designAvailable();
        $user = $this->userId() !== null ? app()->db->fetch('SELECT id, email, role FROM users WHERE id = ?', [$this->userId()]) : null;
        if (!$user || !\Core\Permissions::allows(\Core\Permissions::role((string) $user['role']), 'design.edit')) {
            throw new ApiError(403, 'Für Design-Änderungen muss das Token einem Benutzer gehören, dessen Rolle das Recht „Design (Style-Editor)“ hat.');
        }
        $known = array_keys(\Core\Design::defaults());
        $values = (array) ($in['values'] ?? []);
        $unknown = array_values(array_diff(array_keys($values), $known));
        if ($unknown) {
            throw new ApiError(422, 'Unbekannte Design-Werte: ' . implode(', ', $unknown), ['unknown' => $unknown, 'known' => $known]);
        }
        $base = !empty($in['reset']) ? \Core\Design::defaults() : \Core\Design::values();
        if (isset($in['preset'])) {
            $presets = (array) (\Core\Design::def()['presets'] ?? []);
            if (!isset($presets[$in['preset']])) {
                throw new ApiError(422, 'Unbekannte Vorlage. Verfügbar: ' . implode(', ', array_keys($presets)));
            }
            $base = array_merge($base, (array) $presets[$in['preset']]['values']);
        }
        if (!$values && !isset($in['preset']) && empty($in['reset'])) {
            throw new ApiError(422, 'Nichts zu ändern: values, preset oder reset übergeben.');
        }
        // Ungültige Angaben nicht still ersetzen, sondern melden
        $bad = [];
        foreach ($values as $k => $v) {
            $t = \Core\Design::tokens()[preg_replace('~@dark$~', '', $k)] ?? [];
            if (($t['type'] ?? '') === 'color' && \Core\Design::color($v) === null) $bad[$k] = 'Farbe als #RRGGBB erwartet';
            if (($t['type'] ?? '') === 'choice' && !array_key_exists((string) $v, (array) ($t['options'] ?? []))) $bad[$k] = 'erlaubt: ' . implode(', ', array_keys((array) $t['options']));
            if (($t['type'] ?? '') === 'font' && !isset(\Core\Design::def()['fonts'][(string) $v])) $bad[$k] = 'erlaubt: ' . implode(', ', array_keys((array) \Core\Design::def()['fonts']));
            if (($t['type'] ?? '') === 'range' && !is_numeric($v)) $bad[$k] = 'Zahl erwartet';
        }
        if ($bad) throw new ApiError(422, 'Validierung fehlgeschlagen.', ['errors' => $bad]);
        if (($g = $this->gate(__FUNCTION__, [$in], ['type' => 'design'], fn() => \Core\Design::normalize(array_merge($base, $values)))) !== null) return $g['__gate'];
        \Core\Design::save(array_merge($base, $values), $this->note('Design'), (string) $user['email']);
        return ['saved' => \Core\Design::values(), 'contrast' => \Core\Design::checks(), 'css' => \Core\Design::fileUrl()];
    }

    // ================================================================= Öffnungszeiten (nur wenn das Theme sie anbietet)

    /** Landingpages mit eigenen Domains (Core\Landings): Domains, Seite, Canonical-Modus, Marke, Seiten mit Adressen */
    public function landingsGet(): array
    {
        if (!\Core\Landings::enabled()) {
            throw new ApiError(404, 'Landingpages sind auf dieser Website nicht eingeschaltet (Funktion „landings“).');
        }
        return ['main_url' => \Core\Landings::mainOrigin(), 'landings' => array_map(fn($l) => $l->toArray(), \Core\Landings::all()),
            'hint' => 'Nur lesen. Anlegen/Ändern: Verwaltung → Landingpages (Recht system.manage). Neue Domains: php bin/console site:hosts <site> add <domain>.'];
    }

    /** Weiterleitungen (Core\Redirects) – nur lesen; optional Suche und „Wohin führt …?“ */
    public function redirectsGet(?string $q = null, ?string $test = null): array
    {
        if (!\Core\Redirects\Redirects::enabled()) {
            throw new ApiError(404, 'Weiterleitungen sind auf dieser Website nicht eingeschaltet (Funktion „redirects“).');
        }
        $l = \Core\Redirects\Redirects::list(['q' => (string) $q, 'per' => 500]);
        $out = ['total' => $l['total'], 'redirects' => array_map([\Core\Redirects\Redirects::class, 'toArray'], $l['rows']),
            'hint' => 'Nur lesen (höchstens 500, neueste zuerst). Anlegen/Import: Verwaltung → Administration → Weiterleitungen oder php bin/console redirects:import <datei> --site=<key>.'];
        if ($test !== null && trim($test) !== '') {
            $x = \Core\Redirects\Redirects::explain($test);
            $out['test'] = ['path' => $x['path'], 'status' => $x['status'], 'code' => $x['code'], 'location' => $x['location'],
                'rule' => $x['rule'] ? (int) $x['rule']['id'] : null, 'text' => $x['text']];
        }
        return $out;
    }

    public function hoursGet(): array
    {
        if (!self::hasHours()) {
            throw new ApiError(404, 'Diese Website hat keine Öffnungszeiten-Funktion.');
        }
        $fmt = project('hours.format');
        $out = [];
        foreach ((array) setting((string) project('hours.setting'), []) as $r) {
            $dow = (int) ($r['tag'] ?? -1);
            $out[] = [
                'tag' => $dow, 'wochentag' => self::DAY_NAMES[$dow] ?? '?',
                'von' => $r['von'] ?? '', 'bis' => $r['bis'] ?? '',
                'pause_von' => $r['pause_von'] ?? '', 'pause_bis' => $r['pause_bis'] ?? '',
                'notiz' => $r['notiz'] ?? '',
                'text' => is_string($fmt) && function_exists($fmt) ? $fmt($r) : trim(($r['von'] ?? '') . '–' . ($r['bis'] ?? ''), '–'),
            ];
        }
        return $out;
    }

    /**
     * Ersetzt alle Öffnungszeiten. Wochentag als Zahl (1 = Montag … 0 = Sonntag) oder Name („Mo“, „Montag“).
     */
    public function hoursSet(array $rows): array
    {
        if (!self::hasHours()) {
            throw new ApiError(404, 'Diese Website hat keine Öffnungszeiten-Funktion.');
        }
        $map = ['mo' => 1, 'di' => 2, 'mi' => 3, 'do' => 4, 'fr' => 5, 'sa' => 6, 'so' => 0];
        $norm = [];
        foreach ($rows as $i => $r) {
            if (!is_array($r)) {
                throw new ApiError(422, "Eintrag $i ist kein Objekt.");
            }
            $t = mb_strtolower(trim((string) ($r['tag'] ?? $r['wochentag'] ?? '')));
            $dow = ctype_digit($t) ? (int) $t % 7 : ($map[mb_substr($t, 0, 2)] ?? null);
            if ($dow === null || $t === '') {
                throw new ApiError(422, "Eintrag $i: unbekannter Wochentag „{$t}“.");
            }
            $norm[] = [
                'tag' => (string) $dow,
                'von' => (string) ($r['von'] ?? ''), 'bis' => (string) ($r['bis'] ?? ''),
                'pause_von' => (string) ($r['pause_von'] ?? ''), 'pause_bis' => (string) ($r['pause_bis'] ?? ''),
                'notiz' => (string) ($r['notiz'] ?? ''),
            ];
        }
        $this->settingsUpdate([(string) project('hours.setting') => $norm]);
        return $this->hoursGet();
    }

    public function noticeSet(string $text, bool $active): array
    {
        if (!self::hasNotice()) {
            throw new ApiError(404, 'Diese Website hat keinen Hinweisbalken.');
        }
        return $this->settingsUpdate(array_filter([(string) project('notice.text') => $text, (string) project('notice.active', '') => $active], fn($k) => $k !== '', ARRAY_FILTER_USE_KEY));
    }

    // ================================================================= Seiten

    public function page(string|int $ref): array
    {
        $ref = trim((string) $ref, '/ ');
        $page = match (true) {
            $ref === '' || $ref === 'home' => Pages::home(),
            ctype_digit($ref) => Pages::find((int) $ref),
            default => Pages::byPath($ref) ?? Pages::bySlug($ref),
        };
        if (!$page) {
            throw new ApiError(404, "Seite „{$ref}“ nicht gefunden.");
        }
        return $page;
    }

    private function pageMeta(array $p): array
    {
        return [
            'id' => (int) $p['id'], 'slug' => $p['slug'], 'path' => $p['is_home'] ? '' : (string) ($p['path'] ?? $p['slug']),
            'parent_id' => $p['parent_id'] !== null ? (int) $p['parent_id'] : null, 'menu' => (bool) ($p['menu'] ?? false),
            'nav_title' => (string) ($p['nav_title'] ?? ''), 'title' => $p['title'], 'url' => site_url() . Pages::url($p),
            'status' => $p['status'], 'is_home' => (bool) $p['is_home'], 'noindex' => (bool) $p['noindex'],
            'meta_description' => (string) $p['meta_description'],
            'has_unpublished_changes' => Pages::hasUnpublished($p),
            'lang' => Lang::norm($p['lang'] ?? null),
            'translations' => Lang::multi() ? array_map(fn($t) => (int) $t['id'], Pages::translations($p)) : null,
            'updated_at' => $p['updated_at'], 'published_at' => $p['published_at'],
        ];
    }

    public function pagesList(): array
    {
        $pages = Pages::all();
        if ($this->lang !== null) {
            $pages = array_filter($pages, fn($p) => Lang::norm($p['lang'] ?? null) === $this->lang);
        }
        return array_values(array_map(fn($p) => $this->pageMeta($p), $pages));
    }

    /** Übersetzung einer Seite anlegen (Kopie als Entwurf in der Zielsprache; vorhandene wird zurückgegeben) */
    public function pageTranslate(string|int $ref, string $lang): array
    {
        if (!Lang::valid($lang)) throw new ApiError(422, "Unbekannte Sprache „{$lang}“.");
        $p = $this->page($ref);
        if (($g = $this->gate(__FUNCTION__, [(int) $p['id'], $lang], ['type' => 'page', 'id' => null])) !== null) return $g['__gate'];
        $t = Pages::translate((int) $p['id'], $lang);
        PageCache::clear();
        return $this->pageGet((int) $t['id']);
    }

    private static function exportBlock(array $b): array
    {
        return ['id' => $b['id'], 'type' => $b['type'], 'data' => $b['data'] ?? [], 'section' => $b['tunes']['section'] ?? []];
    }

    public function pageGet(string|int $ref, bool $published = false): array
    {
        $p = $this->page($ref);
        return $this->pageMeta($p) + [
            'version' => $published ? 'published' : 'draft',
            // Veröffentlichte Fassung = was Besucher sehen: ohne Redaktionsnotizen [# … #]; der Entwurf behält sie
            'blocks' => array_map([self::class, 'exportBlock'], $published ? \Core\EditorNotes::stripData(Pages::blocks($p)) : Pages::blocks($p, true)),
        ];
    }

    public function pageCreate(array $in): array
    {
        if (($g = $this->gate(__FUNCTION__, [$in], ['type' => 'page', 'id' => null])) !== null) return $g['__gate'];
        $title = trim(strip_tags((string) ($in['title'] ?? '')));
        if ($title === '') {
            throw new ApiError(422, 'title ist erforderlich.');
        }
        $slug = Pages::slugify((string) ($in['slug'] ?? $title));
        $parent = isset($in['parent']) && $in['parent'] !== null && $in['parent'] !== '' ? (int) $this->page($in['parent'])['id'] : null;
        $lang = isset($in['lang']) && Lang::valid((string) $in['lang']) ? (string) $in['lang'] : ($this->lang ?? Lang::default());
        $this->assertSlug($slug, null, $parent, $lang);
        $blocks = isset($in['blocks']) ? $this->importBlocks((array) $in['blocks']) : [];
        $id = Pages::create([
            'slug' => $slug, 'title' => mb_substr($title, 0, 120), 'parent_id' => $parent,
            'menu' => !empty($in['menu']) ? 1 : 0, 'nav_title' => mb_substr(strip_tags((string) ($in['nav_title'] ?? '')), 0, 60),
            'meta_description' => mb_substr(strip_tags((string) ($in['meta_description'] ?? '')), 0, 300),
            'status' => ($in['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
            'noindex' => !empty($in['noindex']) ? 1 : 0,
            'lang' => $lang === Lang::default() ? null : $lang,
        ], $blocks);
        PageCache::clear();
        return $this->pageGet($id);
    }

    private function assertSlug(string $slug, ?int $ownId, ?int $parentId = null, ?string $lang = null): void
    {
        $reserved = ['admin', 'api', 'anfrage', 'assets', 'media', 'kits', 'themes', 'mcp', 'home', 'sitemap-xml', 'robots-txt', 'index-php'];
        if (in_array($slug, $reserved, true) && !($ownId && (Pages::find($ownId)['slug'] ?? null) === $slug)) {   // bestehende Seiten behalten ihre Adresse
            throw new ApiError(422, "Die Adresse „{$slug}“ ist reserviert.");
        }
        if (Pages::slugTaken($slug, $parentId, $ownId, $lang)) {
            throw new ApiError(409, "Die Adresse „{$slug}“ ist auf dieser Ebene bereits vergeben.");
        }
    }

    public function pageUpdate(string|int $ref, array $in): array
    {
        $p = $this->page($ref);
        if (($g = $this->gate(__FUNCTION__, [(int) $p['id'], $in], ['type' => 'page', 'id' => (int) $p['id']])) !== null) return $g['__gate'];
        $upd = [];
        if (isset($in['title'])) {
            $upd['title'] = mb_substr(trim(strip_tags((string) $in['title'])), 0, 120) ?: $p['title'];
        }
        if (isset($in['slug']) && !$p['is_home']) {
            $upd['slug'] = Pages::slugify((string) $in['slug']);
            $this->assertSlug($upd['slug'], (int) $p['id'], $p['parent_id'] ? (int) $p['parent_id'] : null, Lang::norm($p['lang'] ?? null));
        }
        if (isset($in['menu'])) $upd['menu'] = $in['menu'] ? 1 : 0;
        if (isset($in['nav_title'])) $upd['nav_title'] = mb_substr(strip_tags((string) $in['nav_title']), 0, 60);
        if (array_key_exists('parent', $in) && !$p['is_home']) {
            $parent = $in['parent'] !== null && $in['parent'] !== '' ? (int) $this->page($in['parent'])['id'] : null;
            if ($err = Pages::move((int) $p['id'], $parent, (int) ($in['position'] ?? 999))) {
                throw new ApiError(422, $err);
            }
        }
        if (isset($in['meta_description'])) {
            $upd['meta_description'] = mb_substr(strip_tags((string) $in['meta_description']), 0, 300);
        }
        if (isset($in['meta_title'])) {
            $upd['meta_title'] = mb_substr(trim(strip_tags((string) $in['meta_title'])), 0, 120) ?: null;
        }
        if (isset($in['noindex'])) {
            $upd['noindex'] = $in['noindex'] ? 1 : 0;
        }
        if (isset($in['status']) && !$p['is_home']) {
            $upd['status'] = $in['status'] === 'published' ? 'published' : 'draft';
            if ($upd['status'] === 'published' && $p['content_published'] === null) {
                $upd['content_published'] = $p['content_draft'];
                $upd['published_at'] = now();
            }
        }
        if ($upd) {
            $upd['updated_at'] = now();
            app()->db->update('pages', $upd, 'id = :id', ['id' => (int) $p['id']]);
            Pages::rebuildPaths();
            PageCache::clear();
        }
        return $this->pageMeta($this->page((int) $p['id']));
    }

    public function pageDelete(string|int $ref): array
    {
        $p = $this->page($ref);
        if (($g = $this->gate(__FUNCTION__, [(int) $p['id']], ['type' => 'page', 'id' => (int) $p['id']], fn() => null)) !== null) return $g['__gate'];
        if ($p['is_home']) {
            throw new ApiError(422, 'Die Startseite kann nicht gelöscht werden.');
        }
        app()->db->query('DELETE FROM revisions WHERE page_id = ?', [(int) $p['id']]);
        app()->db->query('DELETE FROM pages WHERE id = ?', [(int) $p['id']]);
        app()->db->query('UPDATE pages SET parent_id = ? WHERE parent_id = ?', [$p['parent_id'], (int) $p['id']]);
        Pages::rebuildPaths();
        PageCache::clear();
        return ['deleted' => (int) $p['id']];
    }

    public function publish(string|int $ref): array
    {
        $p = $this->page($ref);
        if (($g = $this->gate(__FUNCTION__, [(int) $p['id']], ['type' => 'page', 'id' => (int) $p['id']])) !== null) return $g['__gate'];
        try {
            Pages::publish((int) $p['id'], $this->userId());
        } catch (\RuntimeException $e) {   // offene Platzhalter „[bitte ergänzen: …]“
            throw new ApiError(422, $e->getMessage());
        }
        return $this->pageMeta($this->page((int) $p['id']));
    }

    public function discard(string|int $ref): array
    {
        $p = $this->page($ref);
        if (($g = $this->gate(__FUNCTION__, [(int) $p['id']], ['type' => 'page', 'id' => (int) $p['id']])) !== null) return $g['__gate'];
        if (!Pages::discardDraft((int) $p['id'], $this->userId(), $this->note('Entwurf verworfen'))) {
            throw new ApiError(422, 'Diese Seite wurde noch nie veröffentlicht – nichts zum Zurücksetzen.');
        }
        return $this->pageGet((int) $p['id']);
    }

    public function revisions(string|int $ref): array
    {
        $p = $this->page($ref);
        return Pages::revisions((int) $p['id']);
    }

    public function restore(string|int $ref, int $revId): array
    {
        $p = $this->page($ref);
        if (($g = $this->gate(__FUNCTION__, [(int) $p['id'], $revId], ['type' => 'page', 'id' => (int) $p['id']])) !== null) return $g['__gate'];
        $row = app()->db->fetch('SELECT * FROM revisions WHERE id = ? AND page_id = ?', [$revId, (int) $p['id']]);
        if (!$row) {
            throw new ApiError(404, 'Version nicht gefunden.');
        }
        $blocks = json_decode((string) $row['blocks_json'], true)['blocks'] ?? [];
        Pages::saveDraft((int) $p['id'], Pages::sanitizeBlocks($blocks), $this->userId(), $this->note('Version ' . $row['created_at'] . ' wiederhergestellt'));
        return $this->pageGet((int) $p['id']);
    }

    // ================================================================= Blöcke

    public function blockTypes(): array
    {
        $out = [];
        foreach (app()->theme->blocks() as $type => $b) {
            if (!\Core\Features::allowsBlock($type)) continue;   // für dieses Projekt nicht freigegeben
            $out[$type] = [
                'label' => $b['label'], 'group' => $b['group'] ?? null,
                'variants' => $b['variants'] ?? null, 'default_background' => $b['background'] ?? 'white',
                'central' => $b['central'] ?? null,
                'fields' => $b['fields'],
            ] + (!empty($b['custom']) ? ['custom' => true, 'insertable' => $b['insertable'] ?? true] : []);   // eigener Block (Block-Baukasten)
        }
        return [
            'types' => $out,
            'section_options' => [
                'background' => app()->theme->backgrounds(),
                'anchor' => 'Sprungmarke, z. B. „ueber-uns“ (a–z, 0–9, -)',
                'visible' => 'bool', 'showInNav' => 'bool (erfordert anchor)', 'navLabel' => 'string',
                'spaceTop' => ['normal', 'small', 'none'], 'spaceBottom' => ['normal', 'small', 'none'], 'divider' => 'bool',
                'height' => ['auto', 'screen'], 'bgImage' => 'Medien-ID (Bild) oder null', 'overlay' => ['none', 'light', 'dark'],
                'align' => ['top', 'center', 'bottom'],
            ],
        ];
    }

    /** API-Blöcke ({type, data, section}) → Editor.js-Format, validiert */
    private function importBlocks(array $blocks): array
    {
        $raw = [];
        foreach (array_values($blocks) as $i => $b) {
            if (!is_array($b) || !app()->theme->block((string) ($b['type'] ?? ''))) {
                throw new ApiError(422, "Block $i: unbekannter Typ „" . ($b['type'] ?? '') . '“. Siehe block_types.');
            }
            $raw[] = [
                'id' => $b['id'] ?? substr(bin2hex(random_bytes(6)), 0, 10),
                'type' => $b['type'],
                'data' => (array) ($b['data'] ?? []),
                'tunes' => ['section' => (array) ($b['section'] ?? $b['tunes']['section'] ?? [])],
            ];
        }
        return Pages::sanitizeBlocks($raw);
    }

    /** Hinweise auf fehlende Pflichtfelder (blockieren das Speichern nicht – Entwürfe dürfen unvollständig sein) */
    private function warnings(array $blocks): array
    {
        $w = [];
        foreach ($blocks as $b) {
            $def = app()->theme->block($b['type']);
            [, $errors] = Fields::sanitize($def['fields'] ?? [], $b['data']);
            foreach ($errors as $path => $msg) {
                $w[] = "Block {$b['id']} ({$b['type']}) · $path: $msg";
            }
        }
        return $w;
    }

    private function saveBlocks(array $page, array $blocks, string $note, bool $publish): array
    {
        Pages::saveDraft((int) $page['id'], $blocks, $this->userId(), $this->note($note));
        if ($publish) {
            try {
                Pages::publish((int) $page['id'], $this->userId());
            } catch (\RuntimeException $e) {   // offene Platzhalter – Entwurf ist gespeichert
                throw new ApiError(422, __('Entwurf gespeichert.') . ' ' . $e->getMessage());
            }
        }
        PageCache::clear();
        $out = $this->pageGet((int) $page['id']);
        $out['published'] = $publish;
        if ($w = $this->warnings($blocks)) {
            $out['warnings'] = $w;
        }
        return $out;
    }

    public function blocksReplace(string|int $ref, array $blocks, bool $publish = false): array
    {
        $p = $this->page($ref);
        if (($g = $this->gate(__FUNCTION__, [(int) $p['id'], $blocks, $publish], ['type' => 'page', 'id' => (int) $p['id']])) !== null) return $g['__gate'];
        return $this->saveBlocks($p, $this->importBlocks($blocks), 'Blöcke ersetzt', $publish);
    }

    private function findIndex(array $blocks, string $blockId): int
    {
        foreach ($blocks as $i => $b) {
            if ($b['id'] === $blockId) {
                return $i;
            }
        }
        throw new ApiError(404, "Block „{$blockId}“ nicht gefunden.");
    }

    /**
     * Neuer Block. Position: 0-basiert; ohne Angabe ans Ende. Alternativ after = Block-ID.
     */
    public function blockAdd(string|int $ref, string $type, array $data = [], array $section = [], ?int $position = null, ?string $after = null, bool $publish = false): array
    {
        $p = $this->page($ref);
        if (($g = $this->gate(__FUNCTION__, [(int) $p['id'], $type, $data, $section, $position, $after, $publish], ['type' => 'page', 'id' => (int) $p['id']])) !== null) return $g['__gate'];
        $blocks = Pages::blocks($p, true);
        if (!\Core\Features::allowsBlock($type)) {
            throw new ApiError(422, "Der Blocktyp „{$type}“ ist für diese Website nicht freigegeben.");
        }
        $new = $this->importBlocks([['type' => $type, 'data' => $data, 'section' => $section]])[0];
        if ($after !== null) {
            $position = $this->findIndex($blocks, $after) + 1;
        }
        $position = $position === null ? count($blocks) : max(0, min(count($blocks), $position));
        array_splice($blocks, $position, 0, [$new]);
        $out = $this->saveBlocks($p, $blocks, "Block „{$type}“ hinzugefügt", $publish);
        $out['block_id'] = $new['id'];
        return $out;
    }

    /**
     * Block ändern. data wird standardmäßig zusammengeführt (nur übergebene Felder ändern sich);
     * mit replace = true vollständig ersetzt. section ebenso zusammengeführt.
     */
    public function blockUpdate(string|int $ref, string $blockId, ?array $data = null, ?array $section = null, bool $replace = false, bool $publish = false): array
    {
        $p = $this->page($ref);
        if (($g = $this->gate(__FUNCTION__, [(int) $p['id'], $blockId, $data, $section, $replace, $publish], ['type' => 'page', 'id' => (int) $p['id']])) !== null) return $g['__gate'];
        $blocks = Pages::blocks($p, true);
        $i = $this->findIndex($blocks, $blockId);
        $b = $blocks[$i];
        if ($data !== null) {
            $b['data'] = $replace ? $data : array_replace($b['data'] ?? [], $data);
        }
        if ($section !== null) {
            $b['tunes']['section'] = array_replace($b['tunes']['section'] ?? [], $section);
        }
        $blocks[$i] = $this->importBlocks([self::exportBlock($b)])[0];
        return $this->saveBlocks($p, $blocks, "Block „{$b['type']}“ geändert", $publish);
    }

    public function blockRemove(string|int $ref, string $blockId, bool $publish = false): array
    {
        $p = $this->page($ref);
        if (($g = $this->gate(__FUNCTION__, [(int) $p['id'], $blockId, $publish], ['type' => 'page', 'id' => (int) $p['id']])) !== null) return $g['__gate'];
        $blocks = Pages::blocks($p, true);
        $i = $this->findIndex($blocks, $blockId);
        $type = $blocks[$i]['type'];
        array_splice($blocks, $i, 1);
        return $this->saveBlocks($p, $blocks, "Block „{$type}“ entfernt", $publish);
    }

    public function blockMove(string|int $ref, string $blockId, int $position, bool $publish = false): array
    {
        $p = $this->page($ref);
        if (($g = $this->gate(__FUNCTION__, [(int) $p['id'], $blockId, $position, $publish], ['type' => 'page', 'id' => (int) $p['id']])) !== null) return $g['__gate'];
        $blocks = Pages::blocks($p, true);
        $i = $this->findIndex($blocks, $blockId);
        $b = array_splice($blocks, $i, 1)[0];
        array_splice($blocks, max(0, min(count($blocks), $position)), 0, [$b]);
        return $this->saveBlocks($p, $blocks, 'Block verschoben', $publish);
    }

    // ================================================================= Medien

    /** Mediathek mit Filtern: kind (image|pdf|video), q, tag, collection (ID), noalt */
    public function mediaList(string|array|null $f = null): array
    {
        $f = is_array($f) ? $f : ['kind' => $f === 'image' ? 'image' : ''];
        $f = array_intersect_key($f, ['kind' => 1, 'q' => 1, 'tag' => 1, 'collection' => 1, 'noalt' => 1, 'missing_lang' => 1, 'notitle' => 1, 'nocaptions' => 1, 'notranscript' => 1]);
        // Prüf-Filter: Wahrheitswerte auch als "1"/"true" (REST-Query), missing_lang = Sprachkürzel (Bilder ohne Alt-Text in dieser Sprache)
        foreach (['noalt', 'notitle', 'nocaptions', 'notranscript'] as $k) {
            if (isset($f[$k])) $f[$k] = filter_var($f[$k], FILTER_VALIDATE_BOOLEAN);
        }
        if (($f['kind'] ?? '') === 'all') unset($f['kind']);
        return array_map(fn($m) => Media::toJson($m) + ['absolute_url' => site_url() . Media::url($m)], Media::all($f));
    }

    /** Sammlungen, Tags und Bildformate (für Zuschnitte) */
    public function mediaMeta(): array
    {
        return [
            'collections' => array_map(fn($c) => ['id' => (int) $c['id'], 'name' => $c['name'], 'count' => (int) $c['count']], Media::collections()),
            'tags' => Media::allTags(),
            'ratios' => Media::ratios(),
        ];
    }

    /**
     * Medien-Infos ändern: alt, decorative, title, credit, tags (Liste oder Komma-Text), focus {x,y} (0–100), collections (IDs),
     * adjust (Bild anpassen, Core\ImageFx).
     * Bilder brauchen einen Alt-Text (mind. 3 Zeichen) oder decorative = true.
     * decorative gilt auch für Videos (Hintergrund-/Stimmungsvideo): keine Untertitel nötig, auf der Website aria-hidden.
     */
    public function mediaUpdate(int $id, array $in): array
    {
        $m = Media::find($id) ?? throw new ApiError(404, 'Datei nicht gefunden.');
        if (($g = $this->gate(__FUNCTION__, [$id, $in], ['type' => 'media', 'id' => $id])) !== null) return $g['__gate'];
        $upd = [];
        $clean = fn($v, $n) => mb_substr(trim(strip_tags((string) $v)), 0, $n);
        if (array_key_exists('alt', $in)) $upd['alt'] = $clean($in['alt'], 250);
        if (array_key_exists('credit', $in)) $upd['credit'] = $clean($in['credit'], 250);
        if (array_key_exists('title', $in)) $upd['title'] = $clean($in['title'], 180);
        if (array_key_exists('decorative', $in)) $upd['decorative'] = !empty($in['decorative']) && $in['decorative'] !== 'false' ? 1 : 0;
        if (array_key_exists('tags', $in)) $upd['tags'] = Media::tagString($in['tags']);
        // Übersetzungen, z. B. {"i18n": {"en": {"alt": "…", "title": "…"}}} – wird mit vorhandenen zusammengeführt
        if (isset($in['i18n']) && is_array($in['i18n'])) {
            $upd['i18n'] = Media::cleanTranslations(array_replace_recursive(Media::translations($m), $in['i18n']));
        }
        if (isset($in['focus']['x'], $in['focus']['y'])) {
            $upd['focus_x'] = max(0, min(100, (int) $in['focus']['x']));
            $upd['focus_y'] = max(0, min(100, (int) $in['focus']['y']));
        }
        // Bild anpassen (Core\ImageFx): „sepia s120 c110 sharp40“ oder {"preset": "sepia", "saturation": 120, "sharpness": 40, …}; leer = Original
        if (array_key_exists('adjust', $in) && str_starts_with((string) $m['mime'], 'image/')) {
            $adj = \Core\ImageFx::normalize($in['adjust']);
            if ($adj === null || $adj === \Core\ImageFx::NONE) throw new ApiError(422, 'Ungültige Bildanpassung (Effekt gray|sepia|warm|cool|muted|vivid|contrast, s 0–200, b/c 50–150, Schärfe sharp-100…sharp100 in 10er-Schritten).');
            $upd['adjust'] = $adj !== '' ? $adj : null;
        }
        $alt = $upd['alt'] ?? (string) $m['alt'];
        $deco = $upd['decorative'] ?? (int) $m['decorative'];
        if (str_starts_with($m['mime'], 'image/') && !$deco && mb_strlen($alt) < 3 && (isset($upd['alt']) || isset($upd['decorative']))) {
            throw new ApiError(422, 'Bilder brauchen einen Alt-Text (mind. 3 Zeichen) – oder decorative = true.');
        }
        if ($upd) {
            $upd['updated_at'] = now();
            app()->db->update('media', $upd, 'id = :id', ['id' => $id]);
        }
        if (isset($in['collections']) && is_array($in['collections'])) {
            Media::setCollections($id, $in['collections']);
        }
        Media::forget($id);
        PageCache::clear();
        return Media::toJson(Media::find($id) ?? $m);
    }

    /** Zuschnitt für ein Bildformat setzen (rect {x,y,w,h} als Anteile 0–1) oder mit rect = null entfernen */
    public function mediaCrop(int $id, string $ratio, ?array $rect): array
    {
        Media::find($id) ?? throw new ApiError(404, 'Datei nicht gefunden.');
        if (!isset(Media::ratios()[$ratio])) throw new ApiError(422, 'Unbekanntes Bildformat. Formate: ' . implode(', ', array_keys(Media::ratios())));
        if (($g = $this->gate(__FUNCTION__, [$id, $ratio, $rect], ['type' => 'media', 'id' => $id], function (?array $cur) use ($ratio, $rect) {
            $f = fn($k) => round(max(0.0, min(1.0, (float) ($rect[$k] ?? 0))), 5);
            if ($rect === null) unset($cur['crops'][$ratio]); else $cur['crops'][$ratio] = implode(', ', [$f('x'), $f('y'), $f('w'), $f('h')]);
            return $cur;
        })) !== null) return $g['__gate'];
        [$m, $err] = Media::setCrop($id, $ratio, $rect);
        if (!$m) {
            throw new ApiError(422, (string) $err . ' Formate: ' . implode(', ', array_keys(Media::ratios())));
        }
        return Media::toJson($m);
    }

    public function mediaDelete(int $id): array
    {
        Media::find($id) ?? throw new ApiError(404, 'Datei nicht gefunden.');
        if (($g = $this->gate(__FUNCTION__, [$id], ['type' => 'media', 'id' => $id], fn() => null)) !== null) return $g['__gate'];
        Media::delete($id);
        return ['deleted' => $id];
    }

    /** Upload per Base64 (für MCP) – gleiche Prüfungen wie der Admin-Upload */
    public function mediaUploadBase64(string $filename, string $base64, string $alt = '', array $opt = []): array
    {
        $bin = base64_decode(preg_replace('~^data:[^,]+,~', '', $base64), true);
        if ($bin === false || $bin === '') {
            throw new ApiError(422, 'Ungültige Base64-Daten.');
        }
        $max = Media::maxBytes();
        if (strlen($bin) > $max) {
            throw new ApiError(413, 'Datei ist zu groß.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'cms');
        file_put_contents($tmp, $bin);
        try {
            [$m, $err] = Media::import($tmp, $filename, $alt, self::uploadOpt($opt));
        } finally {
            @unlink($tmp);
        }
        if (!$m) {
            throw new ApiError(422, (string) $err);
        }
        $this->logUpload($m);
        return Media::toJson($m);
    }

    /** Uploads gelten sofort (auch im Modus „zur Freigabe“) – protokolliert werden sie trotzdem */
    private function logUpload(array $m): void
    {
        \Core\Review\Queue::record(['token' => $this->token, 'origin' => $this->origin, 'lang' => $this->lang], 'mediaUpload', ['type' => 'media', 'id' => (int) $m['id']]);
    }

    public function mediaUploadFile(array $file, string $alt = '', array $opt = []): array
    {
        [$m, $err] = Media::upload($file, $alt, self::uploadOpt($opt));
        if (!$m) {
            throw new ApiError(422, (string) $err);
        }
        $this->logUpload($m);
        return Media::toJson($m);
    }

    /** Upload-Optionen: decorative, title, tags, collection */
    private static function uploadOpt(array $o): array
    {
        return [
            'decorative' => !empty($o['decorative']) && $o['decorative'] !== 'false',
            'title' => (string) ($o['title'] ?? ''), 'tags' => $o['tags'] ?? '', 'collection' => (int) ($o['collection'] ?? 0),
        ];
    }

    // ================================================================= Datentabellen

    private function dataTable(string $handle): array
    {
        return \Core\Data\Tables::find($handle) ?? throw new ApiError(404, "Tabelle „{$handle}“ nicht gefunden. Verfügbar: "
            . implode(', ', array_column(\Core\Data\Tables::all(), 'handle')));
    }

    /** Eingangs-Tabellen: Einträge entstehen nur über das öffentliche Formular (verschlüsselt), Inhalte nie über die API */
    private function noInboxWrite(array $t): void
    {
        if (\Core\Data\Inbox::is($t)) {
            throw new ApiError(403, "„{$t['handle']}“ ist eine Eingangs-Tabelle (verschlüsselte Anfragen): Einträge entstehen nur über das öffentliche Formular. "
                . 'Metadaten und Status: GET /requests?table=' . $t['handle'] . ' bzw. PATCH /requests/{id} (MCP: list_requests, set_request_status).');
        }
    }

    /** Tabellen mit Feld-Schema (für Einträge und den Block „Datenliste“) */
    public function dataTables(): array
    {
        return array_map(fn($t) => \Core\Data\Inbox::is($t) ? [
            // Eingang: nur Metadaten (Anzahl, Schema des Formulars) – nie Inhalte
            'handle' => $t['handle'], 'name' => $t['name'], 'singular' => $t['singular'], 'icon' => $t['icon'], 'kind' => 'inbox',
            'entries' => \Core\Data\Inbox::count($t), 'new' => \Core\Data\Inbox::count($t, 'neu'),
            'form' => \Core\Data\Inbox::config($t)['form'] ?: null,
            'fields' => array_map(fn($f) => array_filter(['name' => $f['name'], 'label' => $f['label'], 'type' => $f['type'], 'required' => $f['required'],
                'group' => self::groupInfo($f)], fn($v) => $v !== null), $t['fields']),
            'public_form' => \Core\Data\DataForms::enabled($t) ? site_url() . url('/formular/' . $t['handle']) : null,
            'requests' => site_url() . url('/api/v1/requests?table=' . $t['handle']),
            'hint' => 'Eingangs-Tabelle: Inhalte Ende-zu-Ende verschlüsselt, nur Metadaten über /requests; anlegen nur über das öffentliche Formular.',
        ] : [
            // icon: Symbolname (Phosphor duotone, Sprite /assets/icons/{datei}.svg#i-{icon}, Datei laut icons-map.json; ältere Tabellen ggf. ein Zeichen)
            'handle' => $t['handle'], 'name' => $t['name'], 'singular' => $t['singular'], 'icon' => $t['icon'], 'kind' => 'content',
            'entries' => \Core\Data\Entries::count($t, ['status' => 'all']),
            'detail_url' => $t['settings']['route'] !== '' ? site_url() . url('/' . $t['settings']['route'] . '/{slug}') : null,
            'title_field' => $t['settings']['title_field'],
            // Website-Suche (Core\Search\TableSearch): wirksame Einstellungen inkl. automatischer Vorgaben – änderbar unter Felder & Einstellungen
            'search' => \Core\Features::on('search', false) ? \Core\Search\TableSearch::config($t) : null,
            'calendar' => \Core\Data\Calendar::enabled($t) ? array_diff_key(\Core\Data\Calendar::config($t), ['enabled' => 1])
                + ['ics' => \Core\Data\Calendar::config($t)['feed'] ? \Core\Data\Calendar::feedUrls($t)['https'] : null] : null,
            'fields' => array_map(fn($f) => array_filter([
                'name' => $f['name'], 'label' => $f['label'], 'type' => $f['type'], 'required' => $f['required'],
                'options' => $f['options'] ?? null, 'target' => $f['target'] ?? null, 'help' => $f['help'] ?: null,
                'labels' => $f['labels'] ?? null, 'options_i18n' => $f['options_i18n'] ?? null,
                // IBAN: Ausgabe maskiert; Bedingungen (Core\Data\Rules) – ausgeblendete Felder werden beim Speichern geleert
                'mask' => $f['type'] === 'iban' ? ($f['mask'] ?? true) : null,
                'visible_if' => $f['visible_if'] ?? null, 'required_if' => $f['required_if'] ?? null, 'compare' => $f['compare'] ?? null,
                'group' => self::groupInfo($f),
            ], fn($v) => $v !== null), $t['fields']),
            'public_form' => \Core\Data\DataForms::enabled($t) ? site_url() . url('/formular/' . $t['handle']) : null,
            // Geteilte Tabelle (mehrere Websites): Rolle dieser Website, Quellen für ?source=, Anzeige-Einstellungen
            'shared' => \Core\Data\Tables::isShared($t) ? [
                'key' => $t['shared']['key'], 'owner' => $t['shared']['owner'], 'role' => \Core\Data\Shared::isOwner($t) ? 'owner' : 'member',
                'members' => $t['shared']['members'], 'members_see_members' => $t['shared']['members_see_members'],
                'sources' => \Core\Data\Shared::SOURCES, 'display' => array_diff_key(\Core\Data\Shared::localConfig($t['shared']['key']), ['detail_page_id' => 1]),
                'hint' => 'Schreiben nur eigene Einträge (origin_site = ' . site()->key . '). Lesen: ?source=site (Standard, wie auf der Website), own, owner, members, all.',
            ] : null,
        ], \Core\Data\Tables::all());
    }

    /** Wiederholbare Gruppe: Unterfelder und Anzahl (Wert = Array von Objekten {unterfeld: wert}) – sonst null */
    private static function groupInfo(array $f): ?array
    {
        if (($f['type'] ?? '') !== 'group') return null;
        return ['min' => (int) ($f['min'] ?? 0), 'max' => (int) ($f['max'] ?? 10), 'item_label' => $f['item_label'] ?? '', 'add_label' => $f['add_label'] ?? '',
            'value' => 'array of objects, e.g. [{"' . ($f['fields'][0]['name'] ?? 'feld') . '": "…"}]',
            'fields' => array_map(fn($sf) => array_filter(['name' => $sf['name'], 'label' => $sf['label'], 'type' => $sf['type'], 'required' => !empty($sf['required']),
                'options' => $sf['options'] ?? null, 'labels' => $sf['labels'] ?? null], fn($v) => $v !== null), (array) ($f['fields'] ?? []))];
    }

    private function entryOut(array $t, array $e): array
    {
        $shared = \Core\Data\Tables::isShared($t) ? ['_foreign' => \Core\Data\Shared::isForeign($t, $e),
            '_origin' => \Core\Data\Shared::siteInfo((string) ($e['origin_site'] ?? ''), $t['shared']['key'])['name'],
            '_canonical' => \Core\Data\Entries::absUrl($t, $e)] : [];
        return $e + ['_title' => \Core\Data\Entries::title($t, $e), '_url' => ($u = \Core\Data\Entries::url($t, $e)) ? site_url() . $u : null,
            '_lang' => Lang::norm($e['lang'] ?? null)] + $shared
            + (Lang::multi() ? ['_translations' => array_map(fn($x) => (int) $x['id'], \Core\Data\Entries::translations($t, $e))] : []);
    }

    /** Einträge: q, status (published|draft|all), filter {feld: wert}, sort, dir, limit, offset */
    public function dataEntries(string $handle, array $o): array
    {
        $t = $this->dataTable($handle);
        if (\Core\Data\Inbox::is($t)) {
            // Eingang: Metadaten (Status, Vorgangsnummer, Zeitpunkte) – Chiffretext nur mit ciphertext=1
            $status = in_array($o['status'] ?? '', [...\Core\Data\Inbox::statusKeys($t), 'alle'], true) ? (string) $o['status'] : 'alle';
            $rows = \Core\Data\Inbox::query($t, ['status' => $status, 'limit' => max(1, min(500, (int) ($o['limit'] ?? 50))), 'offset' => max(0, (int) ($o['offset'] ?? 0)),
                'payload' => !empty($o['ciphertext'])]);
            return ['kind' => 'inbox', 'total' => \Core\Data\Inbox::count($t, $status), 'entries' => array_map(fn($r) => \Core\Data\Inbox::meta($t, $r, !empty($o['ciphertext'])), $rows)];
        }
        $where = [];
        foreach ((array) ($o['filter'] ?? []) as $f => $v) {
            $where[] = [(string) $f, '=', is_scalar($v) ? (string) $v : ''];
        }
        $q = ['status' => in_array($o['status'] ?? '', ['published', 'draft', 'all'], true) ? $o['status'] : 'all',
            'q' => (string) ($o['q'] ?? ''), 'where' => $where, 'limit' => max(1, min(200, (int) ($o['limit'] ?? 50))), 'offset' => max(0, (int) ($o['offset'] ?? 0))];
        // Sprache: ?lang=en bzw. lang-Parameter; „all“ = alle Sprachen
        $q['lang'] = ($o['lang'] ?? null) === 'all' ? 'all' : ($this->lang ?? (Lang::multi() ? 'all' : Lang::default()));
        if (!empty($o['sort'])) $q['sort'] = (string) $o['sort'];
        if (!empty($o['dir'])) $q['dir'] = (string) $o['dir'];
        $q['source'] = $this->source($t, $o);
        return ['total' => \Core\Data\Entries::count($t, $q), 'entries' => array_map(fn($e) => $this->entryOut($t, $e), \Core\Data\Entries::query($t, $q))];
    }

    /** Geteilte Tabellen: ?source=site|own|owner|members|own_owner|all|featured (Standard: site = wie auf der Website) */
    private function source(array $t, array $o): string
    {
        $src = (string) ($o['source'] ?? 'site');
        if (!in_array($src, \Core\Data\Shared::SOURCES, true)) {
            throw new ApiError(422, 'source muss eines von ' . implode(', ', \Core\Data\Shared::SOURCES) . ' sein.');
        }
        return $src;
    }

    private function sharedTable(string $handle): array
    {
        $t = $this->dataTable($handle);
        if (!\Core\Data\Tables::isShared($t)) throw new ApiError(422, "„{$handle}“ ist keine geteilte Tabelle.");
        return $t;
    }

    /** Fremde Einträge einer geteilten Tabelle sind nur lesbar */
    private function ownEntry(array $t, int $id): void
    {
        $e = \Core\Data\Entries::find($t, $id);
        if ($e && \Core\Data\Shared::isForeign($t, $e)) {
            throw new ApiError(403, 'Dieser Eintrag gehört zur Website „' . $e['origin_site'] . '“ und ist hier nur lesbar. Auswahl dieser Website: POST /data/' . $t['handle'] . '/picks.');
        }
    }

    /**
     * Vorschläge der übrigen Websites (nur Eigentümer-Website). state: pending (offen, Standard), visible, featured, rejected, all
     */
    public function dataSuggestions(string $handle, string $state = 'pending'): array
    {
        $t = $this->sharedTable($handle);
        if (!\Core\Data\Shared::isOwner($t)) throw new ApiError(403, 'Vorschläge sieht nur die Website, der die Tabelle gehört (' . $t['shared']['owner'] . ').');
        $q = ['status' => 'published', 'lang' => 'all', 'source' => 'members', 'suggested' => $state === 'pending' ? 'pending' : true, 'limit' => 500, 'sort' => 'updated_at', 'dir' => 'desc'];
        if (in_array($state, ['visible', 'featured', 'rejected', 'hidden'], true)) $q['pick'] = $state;
        return ['state' => $state, 'entries' => array_map(fn($e) => $this->entryOut($t, $e), \Core\Data\Entries::query($t, $q))];
    }

    /** Auswahl dieser Website setzen: visible (übernehmen), rejected (ablehnen) – nur Eigentümer –, featured, hidden, reset */
    public function dataPick(string $handle, array $ids, string $state): array
    {
        $t = $this->sharedTable($handle);
        if (!in_array($state, [...\Core\Data\Shared::STATES, 'reset'], true)) {
            throw new ApiError(422, 'state muss visible, featured, hidden, rejected oder reset sein.');
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) throw new ApiError(422, 'ids (Liste von Eintrags-IDs) oder entry_id angeben.');
        if (($g = $this->gate(__FUNCTION__, [$handle, $ids, $state], ['type' => 'pick', 'table' => $t['handle'], 'ids' => $ids])) !== null) return $g['__gate'];
        try {
            $n = \Core\Data\Shared::setPick($t, $ids, $state === 'reset' ? null : $state, 'api');
        } catch (\InvalidArgumentException $e) {
            throw new ApiError(422, $e->getMessage());
        }
        return ['updated' => $n, 'state' => $state, 'picks' => \Core\Data\Shared::picks($t, $ids)];
    }

    public function dataEntry(string $handle, string|int $ref, bool $ciphertext = false): array
    {
        $t = $this->dataTable($handle);
        if (\Core\Data\Inbox::is($t)) {
            $row = ctype_digit((string) $ref) ? \Core\Data\Inbox::find($t, (int) $ref, $ciphertext) : null;
            return $row ? \Core\Data\Inbox::meta($t, $row, $ciphertext) : throw new ApiError(404, 'Eintrag nicht gefunden.');
        }
        $e = ctype_digit((string) $ref) ? \Core\Data\Entries::find($t, (int) $ref) : \Core\Data\Entries::bySlug($t, (string) $ref, false, $this->lang, \Core\Data\Tables::isShared($t) ? 'all' : null);
        if ($e && !\Core\Data\Shared::visibleAll($t, $e)) $e = null;   // geteilt: fremde Entwürfe/nicht freigegebene nie
        return $e ? $this->entryOut($t, $e) : throw new ApiError(404, 'Eintrag nicht gefunden.');
    }

    /** Eintrag anlegen ($id null) oder ändern (nur übergebene Felder); slug und status optional */
    public function dataSave(string $handle, ?int $id, array $in): array
    {
        $t = $this->dataTable($handle);
        $this->noInboxWrite($t);
        if ($id !== null) $this->ownEntry($t, $id);
        if ($id !== null && !\Core\Data\Entries::find($t, $id)) throw new ApiError(404, 'Eintrag nicht gefunden.');
        if (($g = $this->gate(__FUNCTION__, [$handle, $id, $in], ['type' => 'entry', 'table' => $t['handle'], 'id' => $id])) !== null) return $g['__gate'];
        // Geteilt: „dem Eigentümer vorschlagen“ (Feld suggest: true/false)
        if (\Core\Data\Tables::isShared($t) && array_key_exists('suggest', $in)) $in['_suggest'] = !empty($in['suggest']) && $in['suggest'] !== 'false';
        unset($in['suggest'], $in['origin_site']);
        if ($id === null && $this->foreign() && !isset($in['lang'])) $in['lang'] = $this->lang;
        [$newId, $errors] = \Core\Data\Entries::save($t, $id, $in);
        if ($errors) {
            throw new ApiError(422, 'Ungültige Eingaben: ' . implode(' ', $errors), ['errors' => $errors]);
        }
        return $this->dataEntry($handle, (int) $newId);
    }

    /**
     * Termine (Vorkommen inkl. Wiederholungen) einer Kalender-Tabelle im Zeitraum [from, to).
     * $o: from, to (JJJJ-MM-TT oder ISO 8601; Standard: heute bis +1 Monat, max. 366 Tage), status (published|draft|all), filter {feld: wert}, limit (max. 1000)
     */
    public function dataOccurrences(string $handle, array $o): array
    {
        $t = $this->dataTable($handle);
        $this->noInboxWrite($t);
        if (!\Core\Features::on('calendar', false)) throw new ApiError(404, 'Die Funktion „Kalender“ ist auf dieser Website abgeschaltet.');
        if (!\Core\Data\Calendar::enabled($t)) throw new ApiError(422, "Tabelle „{$handle}“ ist nicht als Kalender eingerichtet (Einstellung „Als Kalender nutzen“).");
        $tz = \Core\Data\Calendar::tz();
        $parse = function (string $k, string $default) use ($o, $tz) {
            $v = trim((string) ($o[$k] ?? ''));
            try {
                return new \DateTimeImmutable($v !== '' ? $v : $default, $tz);
            } catch (\Throwable) {
                throw new ApiError(422, "„{$k}“: ungültiges Datum (JJJJ-MM-TT oder ISO 8601).");
            }
        };
        $from = $parse('from', 'today');
        $to = $parse('to', $from->modify('+1 month')->format('Y-m-d H:i'));
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', trim((string) ($o['to'] ?? '')))) $to = $to->modify('+1 day');   // Enddatum einschließlich
        if ($to <= $from) throw new ApiError(422, '„to“ muss nach „from“ liegen.');
        if ($from->diff($to)->days > 366) throw new ApiError(422, 'Zeitraum höchstens 366 Tage.');
        $where = [];
        foreach ((array) ($o['filter'] ?? []) as $f => $v) $where[] = [(string) $f, '=', is_scalar($v) ? (string) $v : ''];
        $q = ['where' => $where, 'status' => in_array($o['status'] ?? '', ['published', 'draft', 'all'], true) ? $o['status'] : 'published',
            'limit' => max(1, min(1000, (int) ($o['limit'] ?? 500)))];
        $q['lang'] = $this->lang ?? (Lang::multi() ? 'all' : Lang::default());
        $q['source'] = $this->source($t, $o);
        $c = \Core\Data\Calendar::config($t);
        $occ = \Core\Data\Calendar::occurrences($t, $from, $to, $q);
        return [
            'from' => $from->format(DATE_ATOM), 'to' => $to->format(DATE_ATOM), 'timezone' => $tz->getName(), 'total' => count($occ),
            'occurrences' => array_map(fn($x) => [
                'entry_id' => $x['entry']['id'], 'title' => \Core\Data\Entries::title($t, $x['entry']),
                'start' => $x['all_day'] ? $x['start']->format('Y-m-d') : $x['start']->format(DATE_ATOM),
                'end' => $x['all_day'] ? $x['end']->modify('-1 day')->format('Y-m-d') : $x['end']->format(DATE_ATOM),
                'all_day' => $x['all_day'], 'recurring' => $x['recurring'],
                'location' => $c['location'] !== '' ? ((string) ($x['entry'][$c['location']] ?? '') ?: null) : null,
                'category' => $c['category'] !== '' ? ($x['entry'][$c['category']] ?? null) : null,
                'url' => ($u = \Core\Data\Entries::url($t, $x['entry'])) ? site_url() . $u : null,
                'origin_site' => $x['entry']['origin_site'] ?? null,
                'lang' => Lang::norm($x['entry']['lang'] ?? null),
            ], $occ),
        ];
    }

    /** Übersetzung eines Eintrags anlegen (Entwurf in der Zielsprache; vorhandene wird zurückgegeben) */
    public function dataTranslate(string $handle, int $id, string $lang): array
    {
        $t = $this->dataTable($handle);
        $this->noInboxWrite($t);
        if (!Lang::valid($lang)) throw new ApiError(422, "Unbekannte Sprache „{$lang}“.");
        if (($g = $this->gate(__FUNCTION__, [$handle, $id, $lang], ['type' => 'entry', 'table' => $t['handle'], 'id' => null])) !== null) return $g['__gate'];
        try {
            $e = \Core\Data\Entries::translate($t, $id, $lang);
        } catch (\RuntimeException $ex) {
            throw new ApiError(404, $ex->getMessage());
        }
        return $this->entryOut($t, $e);
    }

    // ================================================================= Website-Suche (Core\Search)

    /**
     * Suche wie für Besucher: veröffentlichte Seiten, Einträge mit Detailseite (inkl. gewählter geteilter), ggf. PDF-Dokumente.
     * $o: page, limit (1–50), type (page | file | Kurzname einer Tabelle). Sprache: useLang(). Kein Zähler „ohne Treffer“.
     */
    public function search(string $q, array $o = []): array
    {
        if (!\Core\Search\Search::enabled()) throw new ApiError(404, 'Die Website-Suche ist auf dieser Website abgeschaltet (Funktion „search“).');
        $q = \Core\Search\Search::clean($q);
        if (mb_strlen($q) < 2) throw new ApiError(422, 'Bitte einen Suchbegriff angeben (mind. 2 Zeichen).');
        $r = \Core\Search\Search::query($q, ['page' => max(1, (int) ($o['page'] ?? 1)), 'per' => max(1, min(50, (int) ($o['limit'] ?? 10))),
            'type' => (string) ($o['type'] ?? ''), 'count_miss' => false]);
        $plain = fn(string $html) => trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
        return [
            'q' => $r['q'], 'lang' => Lang::current(), 'mode' => $r['mode'], 'fallback' => $r['fallback'],
            'total' => $r['total'], 'page' => $r['page'], 'pages' => $r['pages'],
            'types' => array_map(fn($t) => ['label' => $t['label'], 'count' => $t['n']], $r['types']),
            'items' => array_map(fn($it) => [
                'id' => $it['id'], 'type' => $it['type'], 'table' => $it['table'] ?: null, 'badge' => $it['badge'], 'title' => $it['title_plain'],
                'url' => str_starts_with($it['url'], 'http') ? $it['url'] : site_url() . $it['url'],
                'date' => $it['date'] ?: null, 'date_label' => $it['date_label'] ?: null, 'origin' => $it['origin'] ?: null,
                'excerpt' => $plain($it['snippet']), 'match' => $it['match'],
            ], $r['items']),
        ];
    }

    // ================================================================= Karten

    /** Adresse → Koordinaten (für Felder vom Typ geo, z. B. Standort). Serverseitig über Nominatim, max. 1 Anfrage/Sekunde. */
    public function geocode(string $q): array
    {
        if (mb_strlen(trim($q)) < 3) throw new ApiError(422, 'Bitte eine Adresse angeben (mind. 3 Zeichen).');
        $limiter = new \Core\RateLimiter(app()->db);
        if ($limiter->tooMany('geocode', 1, 1)) usleep(1_100_000);
        $limiter->hit('geocode');
        return array_map(fn($r) => $r + ['value' => Maps::format([$r['lat'], $r['lng']])], Maps::geocode($q));
    }

    public function dataDelete(string $handle, int $id): array
    {
        $t = $this->dataTable($handle);
        $this->noInboxWrite($t);
        \Core\Data\Entries::find($t, $id) ?? throw new ApiError(404, 'Eintrag nicht gefunden.');
        $this->ownEntry($t, $id);
        if (($g = $this->gate(__FUNCTION__, [$handle, $id], ['type' => 'entry', 'table' => $t['handle'], 'id' => $id], fn() => null)) !== null) return $g['__gate'];
        \Core\Data\Entries::delete($t, $id);
        return ['deleted' => $id];
    }

    // ================================================================= Anfragen = Eingangs-Tabellen (nur Metadaten)

    /** Eingangs-Tabellen, optional nur eine (?table=) */
    private function inboxTables(?string $table): array
    {
        $all = \Core\Data\Inbox::tables();
        if ($table === null || $table === '') return $all;
        foreach ($all as $t) if ($t['handle'] === $table) return [$t];
        throw new ApiError(404, "Eingangs-Tabelle „{$table}“ nicht gefunden. Verfügbar: " . implode(', ', array_column($all, 'handle')));
    }

    /**
     * Anfragen aller (bzw. einer) Eingangs-Tabelle(n), neueste zuerst – nur Metadaten; Chiffretext nur mit $withCiphertext.
     * status: neu (Standard) | in_bearbeitung | erledigt | alle
     */
    public function requestsList(string $status = 'neu', bool $withCiphertext = false, ?string $table = null): array
    {
        if (!in_array($status, [...\Core\Data\Inbox::allStatusKeys(), 'alle'], true)) $status = 'alle';
        $out = [];
        foreach ($this->inboxTables($table) as $t) {
            foreach (\Core\Data\Inbox::query($t, ['status' => $status, 'limit' => 500, 'payload' => $withCiphertext]) as $r) {
                $out[] = \Core\Data\Inbox::meta($t, $r, $withCiphertext);
            }
        }
        usort($out, fn($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']) ?: $b['id'] <=> $a['id']);
        return array_slice($out, 0, 500);
    }

    /** Status setzen; ohne table wird die ID in allen Eingangs-Tabellen gesucht (mehrdeutig → 409) */
    public function requestStatus(int $id, string $status, ?string $table = null): array
    {
        if (!in_array($status, \Core\Data\Inbox::allStatusKeys(), true)) {
            throw new ApiError(422, 'status muss „neu“, „in_bearbeitung“ oder „erledigt“ sein (bzw. ein Status des Eingangs).');
        }
        $hits = array_values(array_filter($this->inboxTables($table), fn($t) => \Core\Data\Inbox::find($t, $id) !== null));
        if (!$hits) throw new ApiError(404, 'Anfrage nicht gefunden.');
        if (count($hits) > 1) {
            throw new ApiError(409, 'Die ID kommt in mehreren Eingangs-Tabellen vor – bitte table angeben: ' . implode(', ', array_column($hits, 'handle')) . '.');
        }
        if (!in_array($status, \Core\Data\Inbox::statusKeys($hits[0]), true)) {
            throw new ApiError(422, 'status muss einer von ' . implode(', ', \Core\Data\Inbox::statusKeys($hits[0])) . ' sein.');
        }
        try {
            \Core\Data\Inbox::setStatus($hits[0], [$id], $status, $this->note('Status'));
        } catch (\InvalidArgumentException $e) {
            throw new ApiError(409, $e->getMessage());
        }
        return ['id' => $id, 'table' => $hits[0]['handle'], 'status' => $status];
    }
}
