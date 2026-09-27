<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\AI\AiException;
use Core\AI\Assist;
use Core\AI\SeoCheck;
use Core\Data\Entries;
use Core\Data\Tables;
use Core\Fields;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Lang;
use Core\Media;
use Core\Pages;
use Core\Support\Support;
use Core\Support\Tickets;

/**
 * KI-Assistent der Redaktion (JSON für resources/js/_ai.js) + SEO-Übersicht.
 * Jeder KI-Endpunkt: Anmeldung, CSRF, Recht „ai.use“, Ai::enabled(Fähigkeit), Tageslimit (Core\AI\Assist::call) und das
 * fachliche Recht des Gegenstands (Seiten, Einträge, Medien, Support). Antworten sind Vorschläge – gespeichert wird nur
 * über die „…-apply“-Endpunkte bzw. die normalen Formulare, nachdem ein Mensch sie geprüft hat.
 */
final class AiController extends AdminController
{
    /**
     * JSON-Antwort mit einheitlicher Fehlerbehandlung. $kind: Bereich für den Verlauf (nur Metadaten, Core\AI\Assist::log);
     * Vorschläge werden als „suggested“, *-apply als „applied“ protokolliert.
     */
    private function run(Request $r, string $cap, ?string $perm, callable $fn, string $kind = '', string $status = 'suggested'): Response
    {
        try {
            $this->auth($r, $perm ?? false);
            if ($cap !== '' && !Assist::available($cap)) {
                return $this->fail(__('Der KI-Assistent ist für Sie auf dieser Website nicht verfügbar.'), 403);
            }
            $out = $fn();
            if ($out instanceof Response) return $out;
            if ($kind !== '') Assist::log($kind, $r->str('action'), self::target($r), $status, (string) ($out['model'] ?? ''), (int) ($out['ms'] ?? 0));
            return self::secure(Response::json(['ok' => true, 'quota' => Assist::quota()['left']] + $out));
        } catch (AiException $e) {
            if ($kind !== '') Assist::log($kind, $r->str('action'), self::target($r), 'failed');
            return $this->fail($e->getMessage(), 422);
        } catch (HttpException $e) {
            return $this->fail($e->getMessage() ?: __('Keine Berechtigung.'), $e->getCode() ?: 400);
        }
    }

    /** Ziel einer Anfrage für den Verlauf (ohne Inhalte) */
    private static function target(Request $r): string
    {
        $p = $r->post;
        if (!empty($p['page']) && is_scalar($p['page'])) return __('Seite') . ' #' . (int) $p['page'];
        if (preg_match('~/(?:page-translate|seo-check|page-fields)/(\d+)~', $r->path, $m)) return __('Seite') . ' #' . $m[1];
        if (is_array($p['entry'] ?? null)) return __('Eintrag') . ' ' . preg_replace('~[^a-z0-9_]~', '', (string) ($p['entry']['table'] ?? '')) . ' #' . (int) ($p['entry']['id'] ?? 0);
        if (!empty($p['media'])) return __('Medien') . ' #' . (int) $p['media'];
        if (!empty($p['image'])) return __('Hochladen');
        if (!empty($p['issue'])) return __('Meldung') . ' #' . (int) $p['issue'];
        if (!empty($p['target']) && is_scalar($p['target'])) return mb_substr(strip_tags((string) $p['target']), 0, 80);
        if (isset($p['items']) && is_array($p['items'])) return __('{n} Einträge', ['n' => count($p['items'])]);
        return '';
    }

    // ------------------------------------------------------------------ Prüf-Ebene (Core\Review): Protokoll bzw. Freigabe von KI-Übernahmen

    /** Sollen KI-Übernahmen zusätzlich freigegeben werden? (Einstellung der Website, Eingereicht → Einstellungen) */
    private static function reviewAi(): bool
    {
        return \Core\Review\Queue::enabled() && (bool) app()->settings->get('sys.review_ai', false);
    }

    /** Übernahme als Einreichung anlegen (CmsService::forAi) → ID der Einreichung */
    private static function submit(callable $fn): ?int
    {
        try {
            $fn();
        } catch (\Core\Review\Pending $p) {
            return (int) $p->data['id'];
        } catch (\Core\Api\ApiError $e) {
            throw new AiException($e->getMessage());
        }
        return null;
    }

    /** Antwortteil für eingereichte Übernahmen (resources/js/_ai.js zeigt den Hinweis) */
    private static function reviewInfo(array $ids): array
    {
        $ids = array_values(array_filter($ids));
        return $ids ? ['review' => ['ids' => $ids, 'url' => url('/admin/ai/eingereicht' . (count($ids) === 1 ? '/' . $ids[0] : '')),
            'message' => count($ids) === 1 ? __('Zur Freigabe eingereicht – gespeichert wird nach der Prüfung unter „Eingereicht“.')
                : __('{n} Änderungen zur Freigabe eingereicht – gespeichert wird nach der Prüfung unter „Eingereicht“.', ['n' => count($ids)])]] : [];
    }

    private function fail(string $msg, int $status): Response
    {
        return self::secure(Response::json(['ok' => false, 'error' => $msg, 'quota' => Assist::quota()['left']], $status));
    }

    /** Aktueller Stand für die Oberfläche (Tageslimit) */
    public function status(Request $r): Response
    {
        return $this->run($r, 'text', null, fn() => ['client' => Assist::client()]);
    }

    // ------------------------------------------------------------------ Text-Assistent

    public function text(Request $r): Response
    {
        return $this->run($r, 'text', null, function () use ($r) {
            $entry = is_array($r->post['entry'] ?? null) ? $r->post['entry'] : null;
            $page = ctype_digit((string) ($r->post['page'] ?? '')) ? (int) $r->post['page'] : null;
            $lang = Lang::valid($r->str('lang')) ? $r->str('lang') : Lang::default();
            return Assist::text($r->str('action'), (string) ($r->post['text'] ?? ''), $r->str('format'), [
                'tone' => $r->str('tone'), 'instruction' => $r->str('instruction'), 'language' => $lang,
                'context' => Assist::context($page, $entry),
            ]);
        }, 'text');
    }

    public function translate(Request $r): Response
    {
        return $this->run($r, 'text', null, function () use ($r) {
            $from = $r->str('from') ?: Lang::default();
            $to = $r->str('to');
            if (!Lang::valid($from) || !Lang::valid($to)) throw new AiException(__('Unbekannte Sprache.'));
            $items = [];
            foreach (array_slice((array) ($r->post['items'] ?? []), 0, 80) as $it) {
                if (!is_array($it) || !isset($it['key'])) continue;
                $items[(string) $it['key']] = ['text' => (string) ($it['text'] ?? ''), 'html' => !empty($it['html'])];
            }
            if (!$items) throw new AiException(__('Nichts zu übersetzen.'));
            return ['items' => Assist::translate($items, $from, $to)];
        }, 'translate');
    }

    // ------------------------------------------------------------------ SEO

    public function seo(Request $r): Response
    {
        return $this->run($r, 'text', null, function () use ($r) {
            $entry = is_array($r->post['entry'] ?? null) ? $r->post['entry'] : null;
            $warn = [];
            if ($entry) {
                $t = Tables::find((string) ($entry['table'] ?? '')) ?? throw new HttpException(404);
                if (!can('data.edit', $t['handle'])) throw new HttpException(403, __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.'));
                $e = Entries::find($t, (int) ($entry['id'] ?? 0)) ?? throw new HttpException(404);
                $title = $r->str('title') ?: Entries::title($t, $e);
                $text = $r->str('text') ?: Assist::entryText($t, $e);
                $s = Assist::seo($title, $text, Lang::norm($e['lang'] ?? null), SeoCheck::TITLE_MAX - mb_strlen(Assist::titleSuffix()), 155);
                if (($e['status'] ?? '') === 'published' && $s['slug'] !== ($e['slug'] ?? '')) $warn[] = __('Der Eintrag ist online: Eine neue Adresse macht alte Links und Lesezeichen ungültig (keine automatische Weiterleitung).');
                return ['suggest' => $s, 'current' => ['slug' => (string) ($e['slug'] ?? '')], 'notes' => $warn, 'titleMax' => SeoCheck::TITLE_MAX - mb_strlen(Assist::titleSuffix())];
            }
            if (!can('pages.manage')) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
            $p = Pages::find((int) ($r->post['page'] ?? 0)) ?? throw new HttpException(404);
            $s = Assist::seo($p['title'], Assist::pageText($p), Lang::norm($p['lang']), SeoCheck::TITLE_MAX - mb_strlen(Assist::titleSuffix()), 155);
            if ($p['is_home']) {
                $s['slug'] = '';
            } else {
                $parent = $p['parent_id'] ? (int) $p['parent_id'] : null;
                if ($s['slug'] !== $p['slug'] && Pages::slugTaken($s['slug'], $parent, (int) $p['id'])) {
                    $base = $s['slug'];
                    for ($n = 2; Pages::slugTaken($s['slug'], $parent, (int) $p['id']); $n++) $s['slug'] = $base . '-' . $n;
                    $warn[] = __('Die Adresse „{slug}“ ist auf dieser Ebene schon vergeben – vorgeschlagen ist „{alt}“.', ['slug' => $base, 'alt' => $s['slug']]);
                }
                if (in_array($s['slug'], ['admin', 'api', 'anfrage', 'assets', 'media', 'kits', 'themes', 'home'], true)) $s['slug'] = $p['slug'];
                if ($p['content_published'] !== null && $s['slug'] !== $p['slug']) {
                    $warn[] = __('Die Seite ist online: Eine neue Adresse macht alte Links und Lesezeichen ungültig (keine automatische Weiterleitung).');
                }
            }
            return ['suggest' => $s, 'current' => ['title' => $p['title'], 'meta_title' => (string) ($p['meta_title'] ?? ''), 'description' => (string) $p['meta_description'], 'slug' => $p['slug']], 'notes' => $warn];
        }, 'seo');
    }

    /** SEO-Check einer Seite (ohne KI) */
    public function seoCheck(Request $r, string $id): Response
    {
        return $this->run($r, '', 'pages.manage', function () use ($id) {
            $p = Pages::find((int) $id) ?? throw new HttpException(404);
            return ['checks' => SeoCheck::page($p)];
        });
    }

    /** Vorschläge aus der SEO-Übersicht übernehmen: [{page, meta_title?, meta_description?}] bzw. [{table, id, value}] */
    public function seoApply(Request $r): Response
    {
        return $this->run($r, '', null, function () use ($r) {
            $done = 0;
            $errors = [];
            $pending = [];
            foreach (array_slice((array) ($r->post['items'] ?? []), 0, 200) as $it) {
                if (!is_array($it)) continue;
                if (isset($it['page'])) {
                    if (!can('pages.manage')) { $errors[] = __('Seiten: keine Berechtigung.'); continue; }
                    $p = Pages::find((int) $it['page']);
                    if (!$p) continue;
                    $upd = [];
                    if (isset($it['meta_description'])) $upd['meta_description'] = mb_substr(trim(strip_tags((string) $it['meta_description'])), 0, 300);
                    if (isset($it['meta_title'])) $upd['meta_title'] = mb_substr(trim(strip_tags((string) $it['meta_title'])), 0, 120) ?: null;
                    if ($upd && self::reviewAi()) {
                        $pending[] = self::submit(fn() => \Core\Api\CmsService::forAi('seo')->pageUpdate((int) $p['id'], array_filter($upd, fn($v) => $v !== null)));
                    } elseif ($upd) {
                        \Core\Review\Queue::track('seo', 'aiSeo', ['type' => 'page', 'id' => (int) $p['id']],
                            fn() => app()->db->update('pages', $upd + ['updated_at' => now()], 'id = :id', ['id' => (int) $p['id']]));
                        $done++;
                    }
                } elseif (isset($it['table'], $it['id'])) {
                    $t = Tables::find((string) $it['table']);
                    if (!$t || !can('data.edit', $t['handle'])) { $errors[] = __('Tabelle: keine Berechtigung.'); continue; }
                    $e = Entries::find($t, (int) $it['id']);
                    $df = (string) ($t['settings']['description_field'] ?? '');
                    if (!$e || $df === '') continue;
                    if (self::reviewAi()) {
                        $pending[] = self::submit(fn() => \Core\Api\CmsService::forAi('seo')->dataSave($t['handle'], (int) $e['id'], [$df => (string) ($it['value'] ?? '')]));
                        continue;
                    }
                    [, $err] = \Core\Review\Queue::track('seo', 'aiSeo', ['type' => 'entry', 'table' => $t['handle'], 'id' => (int) $e['id']],
                        fn() => Entries::save($t, (int) $e['id'], [$df => (string) ($it['value'] ?? ''), 'status' => $e['status'], 'slug' => $e['slug'] ?? '']));
                    if ($err) $errors[] = Entries::title($t, $e) . ': ' . implode(' ', $err); else $done++;
                }
            }
            $this->changed();
            return ['done' => $done, 'errors' => array_values(array_unique($errors))] + self::reviewInfo($pending);
        }, 'seo', 'applied');
    }

    // ------------------------------------------------------------------ Bereich „KLXM Ai“ (/admin/ai, Marke: config 'ai_brand')

    /** Alle Seiten des Bereichs bekommen die Bereichsnavigation (views/ai/_nav.php, Drill-down wie „Daten“) */
    protected function view(string $view, array $vars = [], int $status = 200): Response
    {
        $vars['drill'] ??= \Core\Theme::capture(ROOT . '/app/Admin/views/ai/_nav.php', ['cur' => $vars['cur'] ?? '']);
        $vars['drillTitle'] ??= Assist::brand();
        if (isset($vars['sub'])) $vars['title'] ??= Assist::brand() . ' · ' . $vars['sub'];
        return parent::view($view, $vars, $status);
    }

    /** Zugang zum Bereich: Funktion „ai“ + Recht „ai.use“ (ist KI noch nicht eingerichtet, erklärt die Übersicht die Einrichtung) */
    private function area(Request $r, ?string $perm = null): array
    {
        $u = $this->auth($r, $perm ?? false);
        if (!\Core\Features::on('ai', false)) throw new HttpException(404);
        if (!can('ai.use')) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        return $u;
    }

    public function areaIndex(Request $r): Response
    {
        $this->area($r);
        return $this->view('ai/index', ['cur' => 'index', 'title' => Assist::brand()]);
    }

    public function areaWrite(Request $r): Response
    {
        $this->area($r);
        return $this->view('ai/write', ['cur' => 'write', 'sub' => __('Texte')]);
    }

    public function areaTranslate(Request $r): Response
    {
        $this->area($r);
        return $this->view('ai/translate', ['cur' => 'translate', 'sub' => __('Übersetzen')]);
    }

    /** SEO-Übersicht */
    public function overview(Request $r): Response
    {
        $this->area($r, 'pages.manage');
        return $this->view('ai/seo', ['cur' => 'seo', 'sub' => __('SEO'), 'data' => SeoCheck::overview(), 'ai' => Assist::available('text')]);
    }

    /** Tabellen-Generator (Recht „data.schema“) */
    public function areaTables(Request $r): Response
    {
        $this->area($r, 'data.schema');
        return $this->view('ai/table-gen', ['cur' => 'tables', 'sub' => __('Tabellen-Generator')]);
    }

    /** Seiten-Generator (Recht „pages.manage“) */
    public function areaPages(Request $r): Response
    {
        $this->area($r, 'pages.manage');
        return $this->view('ai/page-gen', ['cur' => 'pages', 'sub' => __('Seiten-Generator')]);
    }

    public function tablePropose(Request $r): Response
    {
        return $this->run($r, 'text', 'data.schema', fn() => \Core\AI\Generator::proposeTable($r->str('description')), 'tablegen');
    }

    /** Geprüften Tabellen-Entwurf anlegen (normale Prüfung Tables::validate) */
    public function tableCreate(Request $r): Response
    {
        return $this->run($r, 'text', 'data.schema', function () use ($r) {
            // Schema-Änderung: immer direkt (ein Mensch hat den Entwurf geprüft), im Verlauf automatischer Änderungen protokolliert
            $res = \Core\Review\Queue::track('tablegen', 'aiTableCreate', ['type' => 'table', 'id' => null],
                fn() => \Core\AI\Generator::createTable((array) ($r->post['def'] ?? []), !empty($r->post['examples']) ? (int) $r->post['examples'] : 0));
            if ($res['errors']) return self::secure(Response::json(['ok' => false, 'error' => __('Bitte prüfen Sie die markierten Angaben – es wurde nichts angelegt.'), 'errors' => $res['errors']], 422));
            $this->changed();
            return $res + ['url' => url('/admin/data/' . $res['handle']), 'schema' => url('/admin/data/' . $res['handle'] . '/schema')];
        }, 'tablegen', 'applied');
    }

    public function pagePropose(Request $r): Response
    {
        return $this->run($r, 'text', 'pages.manage', fn() => \Core\AI\Generator::proposePage([
            'topic' => $r->str('topic'), 'facts' => $r->str('facts'), 'context' => $r->str('context'), 'audience' => $r->str('audience'), 'tone' => $r->str('tone'), 'language' => $r->str('language'),
            'blocks' => (array) ($r->post['blocks'] ?? []),
        ]), 'pagegen');
    }

    /** Geprüften Seiten-Entwurf als unveröffentlichte Seite anlegen */
    public function pageCreate(Request $r): Response
    {
        return $this->run($r, 'text', 'pages.manage', function () use ($r) {
            // Legt eine unveröffentlichte Seite an – protokolliert unter „Eingereicht“ (Verlauf, Kanal KI)
            $res = \Core\Review\Queue::track('pagegen', 'aiPageCreate', ['type' => 'page', 'id' => null],
                fn() => \Core\AI\Generator::createPage((array) ($r->post['draft'] ?? [])));
            $this->changed();
            return $res;
        }, 'pagegen', 'applied');
    }

    public function areaAlt(Request $r): Response
    {
        $this->area($r, 'media.upload');
        return $this->view('ai/alt', ['cur' => 'alt', 'sub' => __('Alt-Texte')]);
    }

    /** Untertitel: Videos/Audio ohne Untertitel bzw. Transkript + Aufträge der KI-Transkription (views/ai/captions.php) */
    public function areaCaptions(Request $r): Response
    {
        $this->area($r, 'media.upload');
        if (!\Core\MediaTracks::enabled()) throw new HttpException(404);
        return $this->view('ai/captions', ['cur' => 'captions', 'sub' => __('Untertitel')]);
    }

    public function areaHistory(Request $r): Response
    {
        $u = $this->area($r);
        return $this->view('ai/history', ['cur' => 'history', 'sub' => __('Verlauf'), 'rows' => Assist::history((int) $u['id'])]);
    }

    public function areaSettings(Request $r): Response
    {
        $this->area($r);
        return $this->view('ai/settings', ['cur' => 'settings', 'sub' => __('Einstellungen')]);
    }

    /** Übernahme eines Vorschlags im Browser (Formular gefüllt) für den Verlauf melden – nur Metadaten */
    public function applied(Request $r): Response
    {
        return $this->run($r, '', 'ai.use', function () use ($r) {
            Assist::log(preg_replace('~[^a-z_-]~', '', $r->str('kind')) ?: 'text', mb_substr($r->str('action'), 0, 30), self::target($r), 'applied');
            return [];
        });
    }

    /** Textfelder einer Seite (Entwurf) für „In Seite einfügen“ */
    public function pageFields(Request $r, string $id): Response
    {
        return $this->run($r, 'text', 'pages.edit', function () use ($id) {
            $p = Pages::find((int) $id) ?? throw new HttpException(404);
            $out = [];
            foreach (Pages::blocks($p, true) as $b) {
                $def = app()->theme->block((string) ($b['type'] ?? ''));
                if (!$def || !is_array($b['data'] ?? null)) continue;
                foreach (self::texts((array) ($def['fields'] ?? []), $b['data'] + self::blank((array) ($def['fields'] ?? [])), $b['data']) as [$path, $label, $s, , $html]) {
                    $f = self::fieldAt((array) ($def['fields'] ?? []), $path);
                    $out[] = ['key' => 'b:' . $b['id'] . ':' . $path, 'label' => $def['label'] . ' · ' . $label,
                        'format' => ($f['type'] ?? '') === 'richtext' ? 'rich' : (($f['type'] ?? '') === 'inline' ? 'inline' : 'plain'),
                        'excerpt' => mb_strimwidth(trim(strip_tags($s)), 0, 60, '…')];
                }
            }
            return ['fields' => $out, 'title' => $p['title']];
        });
    }

    /** Leere Textfelder eines Blocks als Platzhalter („ – leer –“), damit auch sie als Ziel wählbar sind */
    private static function blank(array $fields): array
    {
        $o = [];
        foreach ($fields as $f) if (isset($f['name']) && in_array($f['type'] ?? 'text', ['text', 'textarea', 'richtext', 'inline'], true)) $o[$f['name']] = '–';
        return $o;
    }

    /** Geprüften Text als Entwurf in ein Textfeld einer Seite schreiben (ersetzen oder anhängen) */
    public function pageInsert(Request $r): Response
    {
        return $this->run($r, 'text', 'pages.edit', function () use ($r) {
            $p = Pages::find((int) ($r->post['page'] ?? 0)) ?? throw new HttpException(404);
            $key = $r->str('key');
            $text = (string) ($r->post['text'] ?? '');
            if (trim(strip_tags($text)) === '') throw new AiException(__('Nichts einzufügen.'));
            $blocks = Pages::blocks($p, true);
            $done = false;
            foreach ($blocks as &$b) {
                if (!str_starts_with($key, 'b:' . ($b['id'] ?? '') . ':')) continue;
                $def = app()->theme->block((string) ($b['type'] ?? ''));
                $path = substr($key, strlen('b:' . $b['id'] . ':'));
                $f = $def ? self::fieldAt((array) ($def['fields'] ?? []), $path) : null;
                if (!$f) break;
                $type = $f['type'] ?? 'text';
                $isHtml = (bool) preg_match('~<(p|ul|ol|li|h[2-4]|blockquote|b|strong|i|em|a|br)\b~i', $text);
                $new = match ($type) {
                    'richtext' => $isHtml ? Assist::clean($text, 'rich') : Assist::clean($text, 'rich'),
                    'inline' => Assist::clean($text, 'inline'),
                    default => Assist::clean($isHtml ? $text : e($text), 'plain'),
                };
                $cur = self::getPath($b['data'], $path);
                if ($r->str('mode') === 'append' && is_string($cur) && trim(strip_tags($cur)) !== '') {
                    $new = $type === 'richtext' ? $cur . $new : ($type === 'inline' ? $cur . '<br>' . $new : $cur . "\n\n" . $new);
                }
                [$v] = Fields::clean($f, $new);
                $done = self::setPath($b['data'], $path, $v);
                break;
            }
            unset($b);
            if (!$done) throw new AiException(__('Das Feld wurde nicht gefunden – bitte die Liste neu laden.'));
            if (self::reviewAi()) {
                $rid = self::submit(fn() => \Core\Api\CmsService::forAi('text')->blocksReplace((int) $p['id'], \Core\Review\Snapshot::exportBlocks(Pages::sanitizeBlocks($blocks))));
                return ['url' => url('/admin/ai/eingereicht/' . $rid)] + self::reviewInfo([$rid]);
            }
            \Core\Review\Queue::track('text', 'aiPageText', ['type' => 'page', 'id' => (int) $p['id']],
                fn() => Pages::saveDraft((int) $p['id'], Pages::sanitizeBlocks($blocks), (int) app()->auth->user()['id'], __('KI-Text eingefügt (geprüft)')));
            $this->changed();
            return ['url' => Pages::url($p) . '?edit=1'];
        }, 'write', 'applied');
    }

    private static function getPath(array $data, string $path): mixed
    {
        $ref = $data;
        foreach (explode('.', $path) as $k) {
            if (!is_array($ref)) return null;
            if (ctype_digit($k)) { $ref = array_values($ref)[(int) $k] ?? null; continue; }
            $ref = $ref[$k] ?? null;
        }
        return $ref;
    }

    // ------------------------------------------------------------------ Seite übersetzen (Blöcke → Entwurf)

    /** Übersetzbare Texte der Seite in der Standardsprache + aktueller Stand dieser Übersetzung */
    public function pageTranslateItems(Request $r, string $id): Response
    {
        return $this->run($r, 'text', 'pages.edit', function () use ($id) {
            [$p, $src] = $this->pagePair((int) $id);
            $items = [];
            foreach (['title' => __('Titel'), 'nav_title' => __('Beschriftung im Menü'), 'meta_title' => __('Titel für Suchmaschinen'), 'meta_description' => __('Beschreibung für Suchmaschinen')] as $k => $label) {
                $s = trim((string) ($src[$k] ?? ''));
                if ($s !== '') $items[] = ['key' => 'form:' . $k, 'form' => $k, 'label' => __('Seite') . ' · ' . $label, 'source' => $s, 'current' => (string) ($p[$k] ?? ''), 'html' => false];
            }
            $target = [];
            foreach (Pages::blocks($p, true) as $b) $target[(string) ($b['id'] ?? '')] = $b;
            $skipped = 0;
            foreach (Pages::blocks($src, true) as $b) {
                $bid = (string) ($b['id'] ?? '');
                $def = app()->theme->block((string) ($b['type'] ?? ''));
                if (!$def || !is_array($b['data'] ?? null)) continue;
                if (!isset($target[$bid]) || ($target[$bid]['type'] ?? '') !== $b['type']) { $skipped++; continue; }
                foreach (self::texts((array) ($def['fields'] ?? []), $b['data'], (array) $target[$bid]['data']) as [$path, $label, $s, $cur, $html]) {
                    $items[] = ['key' => 'b:' . $bid . ':' . $path, 'label' => $def['label'] . ' · ' . $label, 'source' => $s, 'current' => $cur, 'html' => $html];
                }
            }
            return ['items' => $items, 'from' => Lang::norm($src['lang']), 'to' => Lang::norm($p['lang']), 'skipped' => $skipped];
        });
    }

    /** Geprüfte Übersetzungen in den Entwurf der Seite schreiben (Formularfelder füllt die Oberfläche selbst) */
    public function pageTranslateApply(Request $r, string $id): Response
    {
        return $this->run($r, 'text', 'pages.edit', function () use ($r, $id) {
            [$p] = $this->pagePair((int) $id);
            $in = (array) ($r->post['items'] ?? []);
            $blocks = Pages::blocks($p, true);
            $n = 0;
            foreach ($blocks as &$b) {
                $def = app()->theme->block((string) ($b['type'] ?? ''));
                if (!$def) continue;
                foreach ($in as $key => $text) {
                    if (!is_string($text) || !str_starts_with((string) $key, 'b:' . ($b['id'] ?? '') . ':')) continue;
                    $path = substr((string) $key, strlen('b:' . $b['id'] . ':'));
                    $f = self::fieldAt((array) ($def['fields'] ?? []), $path);
                    if (!$f || !Fields::translatable($f)) continue;
                    [$v] = Fields::clean($f, $text);
                    if (self::setPath($b['data'], $path, $v)) $n++;
                }
            }
            unset($b);
            // Seitenangaben (Titel, Menü, SEO) direkt nur bei Entwürfen – veröffentlichte Seiten füllt die Oberfläche ins Formular
            $nf = 0;
            $upd = [];
            if ($p['status'] !== 'published') {
                foreach (['title' => 120, 'nav_title' => 60, 'meta_title' => 120, 'meta_description' => 300] as $k => $max) {
                    $v = $r->post['fields'][$k] ?? null;
                    if (is_string($v) && trim($v) !== '') { $upd[$k] = mb_substr(trim(strip_tags($v)), 0, $max); $nf++; }
                }
            }
            // Prüf-Ebene: zur Freigabe einreichen (Blöcke und Seitenangaben getrennt) bzw. direkt speichern und protokollieren
            if (self::reviewAi()) {
                $ids = [];
                if ($upd) $ids[] = self::submit(fn() => \Core\Api\CmsService::forAi('translate')->pageUpdate((int) $p['id'], $upd));
                if ($n) $ids[] = self::submit(fn() => \Core\Api\CmsService::forAi('translate')->blocksReplace((int) $p['id'], \Core\Review\Snapshot::exportBlocks(Pages::sanitizeBlocks($blocks))));
                return ['saved' => 0, 'fields' => 0, 'url' => url('/admin/ai/eingereicht'), 'settings' => url('/admin/pages/' . $p['id'])] + self::reviewInfo($ids);
            }
            if ($upd || $n) {
                \Core\Review\Queue::track('translate', 'aiPageTranslate', ['type' => 'page', 'id' => (int) $p['id']], function () use ($p, $upd, $n, $blocks) {
                    if ($upd) app()->db->update('pages', $upd + ['updated_at' => now()], 'id = :id', ['id' => (int) $p['id']]);
                    if ($n) Pages::saveDraft((int) $p['id'], Pages::sanitizeBlocks($blocks), (int) app()->auth->user()['id'], __('KI-Übersetzung übernommen (geprüft)'));
                });
                $this->changed();
            }
            return ['saved' => $n, 'fields' => $nf, 'url' => Pages::url($p) . '?edit=1', 'settings' => url('/admin/pages/' . $p['id'])];
        }, 'translate', 'applied');
    }

    /** [Übersetzung (diese Seite), Seite in der Standardsprache] */
    private function pagePair(int $id): array
    {
        $p = Pages::find($id) ?? throw new HttpException(404);
        if (Lang::norm($p['lang']) === Lang::default()) throw new AiException(__('Diese Seite ist in der Standardsprache – übersetzt wird in ihren Übersetzungen.'));
        $src = Pages::translations($p)[Lang::default()] ?? throw new AiException(__('Zu dieser Seite gibt es keine Fassung in der Standardsprache.'));
        return [$p, $src];
    }

    /** Übersetzbare Textfelder: [Pfad, Bezeichnung, Quelltext, aktueller Text, HTML?] – nur, wo die Übersetzung dieselbe Struktur hat */
    private static function texts(array $fields, array $src, array $cur, string $prefix = '', string $lp = '', int $depth = 0): array
    {
        $out = [];
        foreach ($fields as $f) {
            $name = (string) ($f['name'] ?? '');
            if ($name === '' || !array_key_exists($name, $src)) continue;
            $type = (string) ($f['type'] ?? 'text');
            $label = ($lp !== '' ? $lp . ' › ' : '') . ($f['label'] ?? $name);
            if ($type === 'repeater' && is_array($src[$name]) && is_array($cur[$name] ?? null) && $depth < 3) {
                foreach (array_values($src[$name]) as $i => $item) {
                    $ci = array_values($cur[$name])[$i] ?? null;
                    if (is_array($item) && is_array($ci)) {
                        $out = array_merge($out, self::texts((array) ($f['fields'] ?? []), $item, $ci, $prefix . $name . '.' . $i . '.', $label . ' ' . ($i + 1), $depth + 1));
                    }
                }
                continue;
            }
            if (!Fields::translatable($f) || !in_array($type, ['text', 'textarea', 'richtext', 'inline'], true)) continue;
            $s = $src[$name];
            if (!is_string($s) || trim(strip_tags($s)) === '' || str_contains($s, '{{')) continue;
            $out[] = [$prefix . $name, $label, $s, is_string($cur[$name] ?? null) ? $cur[$name] : '', in_array($type, ['richtext', 'inline'], true)];
        }
        return $out;
    }

    private static function fieldAt(array $fields, string $path): ?array
    {
        $parts = explode('.', $path);
        while ($parts) {
            $name = array_shift($parts);
            $f = null;
            foreach ($fields as $x) if (($x['name'] ?? '') === $name) { $f = $x; break; }
            if (!$f) return null;
            if (!$parts) return $f;
            if (($f['type'] ?? '') !== 'repeater' || !ctype_digit((string) array_shift($parts))) return null;
            $fields = (array) ($f['fields'] ?? []);
        }
        return null;
    }

    private static function setPath(array &$data, string $path, mixed $v): bool
    {
        $ref = &$data;
        $parts = explode('.', $path);
        $last = array_pop($parts);
        foreach ($parts as $k) {
            if (!is_array($ref)) return false;
            if (ctype_digit($k)) { $vals = array_keys($ref); $k = $vals[(int) $k] ?? null; if ($k === null) return false; }
            if (!array_key_exists($k, $ref)) return false;
            $ref = &$ref[$k];
        }
        if (!is_array($ref)) return false;
        $ref[$last] = $v;
        return true;
    }

    // ------------------------------------------------------------------ Medien

    /** Alt-Text vorschlagen: {media, pool?} (vorhandenes Bild) oder {image: data:-URL, name} (vor dem Hochladen) */
    public function alt(Request $r): Response
    {
        return $this->run($r, 'vision', 'media.upload', function () use ($r) {
            $langs = array_values(array_filter(array_map('strval', (array) ($r->post['langs'] ?? [])), [Lang::class, 'valid'])) ?: array_keys(Lang::all());
            $data = (string) ($r->post['image'] ?? '');
            if ($data !== '') {
                if (!preg_match('~^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$~', $data, $m) || strlen($m[2]) > 6 * 1024 * 1024) {
                    throw new AiException(__('Bild konnte nicht gelesen werden.'));
                }
                $dir = ROOT . '/storage/cache';
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $file = $dir . '/ai-upload-' . bin2hex(random_bytes(8)) . '.' . ($m[1] === 'jpeg' ? 'jpg' : $m[1]);
                file_put_contents($file, base64_decode($m[2]));
                try {
                    if (!@getimagesize($file)) throw new AiException(__('Bild konnte nicht gelesen werden.'));
                    $res = Assist::alt($file, $langs, mb_substr(basename($r->str('name')), 0, 120));
                    Assist::log('alt', 'upload', __('Hochladen'));
                    return $res;
                } finally {
                    @unlink($file);
                }
            }
            $pool = $r->str('pool');
            if ($pool !== '') {
                if (!\Core\MediaPools::available($pool)) throw new HttpException(404, __('Unbekannter Medien-Pool.'));
                Media::usePool($pool);
            }
            $m = Media::find((int) ($r->post['media'] ?? 0)) ?? throw new HttpException(404, __('Bild nicht gefunden.'));
            return Assist::alt((int) $m['id'], $langs, (string) ($m['title'] ?: $m['original_name']));
        }, 'alt');
    }

    /** Geprüfte Alt-Texte speichern: {items: [{id, alt: {de: …, en: …}}], pool?} – nur mit Recht zum Ändern (auch in Pools) */
    public function altApply(Request $r): Response
    {
        // Übersetzte Alt-Texte (Alt-Texte → Sprache) kommen von der Text-KI, Beschreibungen von der Bild-KI
        return $this->run($r, $r->str('mode') === 'translate' ? 'text' : 'vision', 'media.upload', function () use ($r) {
            $pool = $r->str('pool');
            if ($pool !== '') {
                if (!\Core\MediaPools::canEdit($pool)) throw new HttpException(403, __('Diesen geteilten Pool darf diese Website nur verwenden.'));
                Media::usePool($pool);
            }
            $saved = [];
            $errors = [];
            $pending = [];
            foreach (array_slice((array) ($r->post['items'] ?? []), 0, 100) as $it) {
                if (!is_array($it)) continue;
                $id = (int) ($it['id'] ?? 0);
                $m = Media::find($id);
                if (!$m) continue;
                $restore = Media::pool();
                if ($restore === null && !empty($m['pool_ref'])) {
                    if (!\Core\MediaPools::canEdit((string) $m['_pool'])) { $errors[] = ($m['title'] ?: $m['original_name']) . ': ' . __('liegt in einem geteilten Pool, den diese Website nur verwendet.'); continue; }
                    Media::usePool((string) $m['_pool']);
                    $id = (int) $m['_pool_id'];
                    $m = Media::find($id);
                }
                $alts = array_filter(array_map(fn($v) => mb_substr(trim(strip_tags((string) $v)), 0, 250), (array) ($it['alt'] ?? [])), fn($v) => $v !== '');
                $upd = [];
                $def = Lang::default();
                if (isset($alts[$def])) {
                    if (mb_strlen($alts[$def]) < 3) { $errors[] = __('Alt-Text zu kurz.'); }
                    else { $upd['alt'] = $alts[$def]; $upd['decorative'] = 0; }
                }
                $tr = json_decode((string) ($m['i18n'] ?? ''), true) ?: [];
                $trChanged = false;
                foreach ($alts as $l => $v) {
                    if ($l === $def || !Lang::valid((string) $l)) continue;
                    $tr[$l]['alt'] = $v;
                    $trChanged = true;
                }
                if ($trChanged) $upd['i18n'] = Media::cleanTranslations($tr);
                if ($upd && self::reviewAi() && Media::pool() === null) {
                    // Prüf-Ebene: als Einreichung (geteilte Pools werden immer direkt gespeichert)
                    $in = isset($upd['alt']) ? ['alt' => $upd['alt'], 'decorative' => false] : [];
                    foreach ($alts as $l => $v) if ($l !== $def && Lang::valid((string) $l)) $in['i18n'][$l]['alt'] = $v;
                    $pending[] = self::submit(fn() => \Core\Api\CmsService::forAi('alt')->mediaUpdate($id, $in));
                } elseif ($upd) {
                    \Core\Review\Queue::track('alt', 'aiAlt', ['type' => 'media', 'id' => $id], function () use ($upd, $id) {
                        Media::db()->update('media', $upd + ['updated_at' => now()], 'id = :id', ['id' => $id]);
                        Media::forget($id);
                    });
                    $saved[] = (int) ($it['id'] ?? $id);
                }
                Media::usePool($restore);
            }
            if ($saved) $this->changed();
            return ['saved' => $saved, 'errors' => $errors] + self::reviewInfo($pending);
        }, 'alt', 'applied');
    }

    // ------------------------------------------------------------------ Einträge, Support, Tabellen

    public function summary(Request $r): Response
    {
        return $this->run($r, 'text', null, function () use ($r) {
            $entry = (array) ($r->post['entry'] ?? []);
            $t = Tables::find((string) ($entry['table'] ?? '')) ?? throw new HttpException(404);
            if (!can('data.edit', $t['handle'])) throw new HttpException(403, __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.'));
            $e = isset($entry['id']) ? Entries::find($t, (int) $entry['id']) : null;
            $text = mb_substr((string) ($r->post['text'] ?? ''), 0, 20000) ?: ($e ? Assist::entryText($t, $e) : '');
            $title = $r->str('title') ?: ($e ? Entries::title($t, $e) : '');
            $lang = Lang::valid($r->str('lang')) ? $r->str('lang') : Lang::norm($e['lang'] ?? null);
            return Assist::summary($title, $text, max(60, min(600, (int) ($r->post['max'] ?? 200))), $lang);
        }, 'summary');
    }

    public function supportReply(Request $r): Response
    {
        return $this->run($r, 'text', null, function () use ($r) {
            if (!Support::isStaff()) throw new HttpException(403, __('Nur für das Support-Team.'));
            $issue = Tickets::find((int) ($r->post['issue'] ?? 0)) ?? throw new HttpException(404);
            return Assist::supportReply($issue);
        }, 'support');
    }

    public function kbPolish(Request $r): Response
    {
        return $this->run($r, 'text', null, function () use ($r) {
            if (!Support::isStaff()) throw new HttpException(403, __('Nur für das Support-Team.'));
            return Assist::kbPolish(mb_substr($r->str('title'), 0, 200), mb_substr((string) ($r->post['body'] ?? ''), 0, 20000));
        }, 'kb');
    }

    public function schema(Request $r): Response
    {
        return $this->run($r, 'text', 'data.schema', function () use ($r) {
            $types = array_values(array_filter(array_map('strval', (array) ($r->post['types'] ?? [])), fn($t) => (bool) preg_match('~^[a-z]+$~', $t)));
            return Assist::schema(mb_substr($r->str('description'), 0, 2000), $types ?: array_keys(Tables::TYPES));
        }, 'schema');
    }
}
