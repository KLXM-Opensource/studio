<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\AI\AiException;
use Core\AI\Assist;
use Core\Block;
use Core\Blocks\Custom;
use Core\Blocks\Demos;
use Core\Design;
use Core\Features;
use Core\Fields;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Pages;
use Core\Seo;

/**
 * Verwaltung → Blöcke (Block-Designer): eigene Blöcke anlegen, prüfen, in der Vorschau testen, versionieren,
 * für die Redaktion freigeben, exportieren/importieren, als Theme-Block ausgeben und mit KLXM AI vorschlagen lassen.
 * Recht „blocks.build“ (Administration, Integratoren), Funktion „blocks.custom“.
 */
final class BlockController extends AdminController
{
    private function guard(Request $r): array
    {
        if (!Features::on('blocks.custom')) throw new HttpException(404);
        return $this->auth($r, 'blocks.build');
    }

    private function row(string $key): array
    {
        return Custom::find($key) ?? throw new HttpException(404);
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        $blocks = Custom::all();
        foreach ($blocks as &$b) $b['uses'] = count(Custom::usages($b['key']));
        unset($b);
        $demos = Demos::all();
        foreach ($demos as $n => &$d) $d['copies'] = count(array_filter($blocks, fn($b) => ($b['settings']['demo'] ?? '') === $n));
        unset($d);
        return $this->view('blocks/index', [
            'blocks' => $blocks,
            'library' => Custom::libraryAllowed() ? Custom::libraryList() : null,
            'demos' => $demos,
            'demoToken' => bin2hex(random_bytes(8)),
            'css' => ['css/blockdemos.css'],
        ]);
    }

    /**
     * Beispiel „Als Vorlage übernehmen“: immer eine neue Kopie als Entwurf – je Klick genau einmal
     * (das Formular trägt ein Einmal-Kennzeichen; ein doppelt abgeschickter Klick führt zur schon angelegten Kopie).
     */
    public function demoInstall(Request $r): Response
    {
        $user = $this->guard($r);
        $name = $r->str('demo');
        $token = preg_replace('~[^a-f0-9]~', '', $r->str('token'));
        $done = (array) app()->session->get('cblk_demo_tokens', []);
        if ($token !== '' && isset($done[$token]) && Custom::find((string) $done[$token])) {
            return $this->back('/admin/blocks/' . $done[$token], 'info', __('Das Beispiel wurde bereits übernommen.'));
        }
        $res = Demos::install($name, (int) $user['id']);
        if ($res['errors']) return $this->back('/admin/blocks', 'error', implode(' ', $res['errors']));
        if ($token !== '') app()->session->set('cblk_demo_tokens', array_slice([$token => $res['key']] + $done, 0, 30, true));
        return $this->back('/admin/blocks/' . $res['key'], 'success', __('Beispiel als Entwurf übernommen – ändern Sie es nach Belieben. Erst „Für Redaktion freigeben“ macht es beim Bearbeiten der Seiten verfügbar.'));
    }

    /** Vorschau eines Beispiels (iframe in der Übersicht): Seite des aktiven Kits, nur der Block mit Beispieldaten */
    public function demoPreview(Request $r, string $name): Response
    {
        $this->guard($r);
        if (!Demos::find($name)) throw new HttpException(404);
        $html = Demos::page($name, url('/admin/blocks/demos/' . $name . '/preview.css') . '?v=' . substr(md5((string) json_encode(Demos::find($name))), 0, 8));
        if ($html === null) throw new HttpException(404);
        if ($r->str('dark') === '1') $html = DesignController::forceDark($html, Design::values());
        return self::secure(new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']));
    }

    public function demoCss(Request $r, string $name): Response
    {
        $this->guard($r);
        if (!Demos::find($name)) throw new HttpException(404);
        return self::secure(new Response(Demos::css($name), 200, ['Content-Type' => 'text/css; charset=utf-8']));
    }

    public function create(Request $r): Response
    {
        $this->guard($r);
        $def = ['key' => '', 'label' => '', 'icon' => 'package', 'group' => '', 'description' => '', 'fields' => [], 'template' => '', 'css' => '',
            'behaviours' => [], 'jsonld' => ['type' => 'none'], 'settings' => ['help' => '', 'background' => '', 'width' => 'wrap'], 'sample' => []];
        return $this->builder(null, $def, [], $r->str('ai') === '1');
    }

    public function edit(Request $r, string $key): Response
    {
        $this->guard($r);
        $row = $this->row($key);
        return $this->builder($row, Custom::definition($row), []);
    }

    private function builder(?array $row, array $def, array $errors, bool $ai = false): Response
    {
        $check = $row ? Custom::check($def) : ['errors' => [], 'warnings' => []];
        return $this->view('blocks/edit', [
            'row' => $row, 'def' => $def, 'errors' => $errors + $check['errors'], 'warnings' => $check['warnings'],
            'types' => Custom::fieldTypes(), 'versions' => $row ? Custom::versions($row['key']) : [],
            'uses' => $row ? Custom::usages($row['key']) : [], 'aiOn' => Assist::available('text'), 'aiOpen' => $ai,
            'backgrounds' => app()->theme->backgrounds(), 'hasDark' => !empty((array) (Design::def()['dark'] ?? [])),
            'library' => Custom::libraryAllowed(),
            'demo' => Demos::find((string) ($def['settings']['demo'] ?? '')),
            'css' => ['css/blockdemos.css'],
            'title' => $row ? $row['label'] . ' · ' . __('Blöcke') : __('Neuer Block'),
        ]);
    }

    /** Eingaben des Block-Designer-Formulars */
    private static function input(Request $r): array
    {
        $p = $r->post;
        return ['key' => (string) ($p['key'] ?? ''), 'label' => (string) ($p['label'] ?? ''), 'icon' => (string) ($p['icon'] ?? ''),
            'group' => (string) ($p['group'] ?? ''), 'description' => (string) ($p['description'] ?? ''),
            'fields' => (string) ($p['fields_json'] ?? '[]'), 'template' => (string) ($p['template'] ?? ''), 'css' => (string) ($p['css'] ?? ''),
            'behaviours' => (array) ($p['behaviours'] ?? []), 'jsonld' => is_string($p['jsonld_json'] ?? null) ? (json_decode($p['jsonld_json'], true) ?: []) : [],
            'settings' => (array) ($p['settings'] ?? []), 'sample' => (array) ($p['sample'] ?? [])];
    }

    public function store(Request $r): Response
    {
        $user = $this->guard($r);
        $res = Custom::save(null, self::input($r), (int) $user['id'], $r->str('_ai') === '1' ? 'ai' : 'save', $r->str('_ai') === '1' ? __('Vorschlag von {brand}', ['brand' => Assist::brand()]) : '');
        if ($res['errors']) {
            [$def] = Custom::normalize(self::input($r));
            app()->session->flash('error', __('Bitte prüfen: {msg}', ['msg' => implode(' ', $res['errors'])]));
            return $this->builder(null, $def, $res['errors']);
        }
        return $this->back('/admin/blocks/' . $res['key'] . '#' . preg_replace('~[^\w-]~', '', $r->str('_tab') ?: 'felder'), 'success', __('Block als Entwurf gespeichert.'));
    }

    public function update(Request $r, string $key): Response
    {
        $user = $this->guard($r);
        $this->row($key);
        $res = Custom::save($key, self::input($r), (int) $user['id']);
        $tab = '#' . preg_replace('~[^\w-]~', '', $r->str('_tab') ?: 'felder');
        if ($res['errors']) {
            [$def] = Custom::normalize(self::input($r), $key);
            app()->session->flash('error', __('Nicht gespeichert: {msg}', ['msg' => implode(' ', $res['errors'])]));
            return $this->builder($this->row($key), $def, $res['errors']);
        }
        if ($r->str('_action') === 'publish') return $this->doPublish($key, (int) $user['id'], $tab);
        $chk = Custom::check(Custom::definition($this->row($key)));
        return $this->back('/admin/blocks/' . $key . $tab, $chk['errors'] ? 'error' : 'success',
            $chk['errors'] ? __('Entwurf gespeichert – vor der Freigabe bitte die markierten Fehler beheben.') : __('Entwurf gespeichert.'));
    }

    public function publish(Request $r, string $key): Response
    {
        $user = $this->guard($r);
        $this->row($key);
        return $this->doPublish($key, (int) $user['id'], '');
    }

    private function doPublish(string $key, int $userId, string $tab): Response
    {
        $res = Custom::publish($key, $userId);
        if (!$res['ok']) {
            return $this->back('/admin/blocks/' . $key . $tab, 'error', __('Nicht freigegeben: {msg}', ['msg' => implode(' ', array_filter($res['errors'], fn($k) => !str_ends_with((string) $k, '_line'), ARRAY_FILTER_USE_KEY))]));
        }
        return $this->back('/admin/blocks/' . $key . $tab, 'success', __('Für die Redaktion freigegeben – der Block steht jetzt beim Bearbeiten der Seiten zur Verfügung.'));
    }

    public function withdraw(Request $r, string $key): Response
    {
        $this->guard($r);
        Custom::withdraw($key);
        return $this->back('/admin/blocks/' . $key, 'success', __('Zurückgezogen: Der Block lässt sich nicht mehr neu einfügen; bestehende Seiten zeigen ihn weiter.'));
    }

    public function delete(Request $r, string $key): Response
    {
        $this->guard($r);
        $err = Custom::delete($key);
        return $err ? $this->back('/admin/blocks/' . $key, 'error', $err) : $this->back('/admin/blocks', 'success', __('Block gelöscht.'));
    }

    public function restore(Request $r, string $key, string $version): Response
    {
        $user = $this->guard($r);
        $res = Custom::restore($key, (int) $version, (int) $user['id']);
        return $this->back('/admin/blocks/' . $key, $res['errors'] ? 'error' : 'success',
            $res['errors'] ? implode(' ', $res['errors']) : __('Fassung wiederhergestellt (als Entwurf – zur Übernahme erneut freigeben).'));
    }

    public function export(Request $r, string $key): Response
    {
        $this->guard($r);
        $x = Custom::export($key) ?? throw new HttpException(404);
        return self::secure(new Response((string) json_encode($x, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json; charset=utf-8', 'Content-Disposition' => 'attachment; filename="block-' . $key . '.json"']));
    }

    public function themeExport(Request $r, string $key): Response
    {
        $this->guard($r);
        $this->row($key);
        try {
            $z = Custom::themeExport($key);
        } catch (\RuntimeException $e) {
            return $this->back('/admin/blocks/' . $key, 'error', $e->getMessage());
        }
        $body = (string) file_get_contents($z['file']);
        @unlink($z['file']);
        return self::secure(new Response($body, 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="' . $z['name'] . '"']));
    }

    public function import(Request $r): Response
    {
        $user = $this->guard($r);
        $json = trim($r->str('json'));
        $f = $r->files['file'] ?? null;
        if ($json === '' && is_array($f) && ($f['error'] ?? 1) === UPLOAD_ERR_OK && ($f['size'] ?? 0) < 1_000_000) $json = (string) file_get_contents($f['tmp_name']);
        $res = Custom::import($json, (int) $user['id']);
        if ($res['errors']) return $this->back('/admin/blocks', 'error', implode(' ', $res['errors']));
        return $this->back('/admin/blocks/' . $res['key'], 'success', __('Block importiert (Entwurf) – bitte prüfen und freigeben.'));
    }

    public function library(Request $r, string $key): Response
    {
        $this->guard($r);
        if (!Custom::libraryAllowed()) throw new HttpException(403);
        $this->row($key);
        $ok = Custom::libraryPut($key);
        return $this->back('/admin/blocks/' . $key, $ok ? 'success' : 'error', $ok ? __('In die Netzwerk-Bibliothek kopiert – andere Websites können den Block von dort übernehmen.') : __('Konnte nicht gespeichert werden.'));
    }

    public function libraryImport(Request $r): Response
    {
        $user = $this->guard($r);
        if (!Custom::libraryAllowed()) throw new HttpException(403);
        $res = Custom::libraryImport($r->str('file'), (int) $user['id']);
        if ($res['errors']) return $this->back('/admin/blocks', 'error', implode(' ', $res['errors']));
        return $this->back('/admin/blocks/' . $res['key'], 'success', __('Aus der Bibliothek übernommen (Entwurf).'));
    }

    // ------------------------------------------------------------------ JSON-Endpunkte des Block-Designers

    /** Prüfen + Vorschau: ganze Seite des aktiven Themes mit dem Block und Beispieldaten */
    public function preview(Request $r): Response
    {
        $this->guard($r);
        $in = self::input($r);
        $key = preg_match('~^[a-z][a-z0-9_]{1,30}$~', $r->str('_key')) ? $r->str('_key') : null;
        [$def, $errors] = Custom::normalize($in, $key ?? 'vorschau');
        $chk = Custom::check($def);
        $errors += $chk['errors'];
        $out = ['ok' => true, 'errors' => $errors, 'warnings' => $chk['warnings'], 'fields' => $def['fields']];
        if (isset($errors['template']) || isset($errors['fields'])) {
            return self::secure(Response::json($out + ['html' => self::message($errors)]));
        }
        // Entwurfs-CSS für den iframe in der Sitzung (nur für diese Person, ohne Datei)
        $token = bin2hex(random_bytes(6));
        app()->session->set('cblk_preview_css', [$token => (string) ($chk['css'] ?? '')]);
        $type = Custom::type($def['key']);
        $tdef = Custom::themeDef($def + ['css_file' => ''], 'published') + ['type' => $type];
        $theme = app()->theme;
        if (isset($tdef['background']) && !isset($theme->backgrounds()[$tdef['background']])) unset($tdef['background']);
        $data = array_replace(Fields::defaults($def['fields']), $def['sample']);
        $tunes = $theme->sanitizeTunes(['background' => $r->str('_bg')], $tdef);
        $home = Pages::home();
        if (!$home) return self::secure(Response::json(['ok' => false, 'error' => __('Keine Startseite gefunden.')], 404));
        app()->currentPage = $home;
        \Core\StructuredData::reset();
        $block = new Block('vorschau', $type, $data, $tunes, $tdef);
        $section = $theme->renderBlock($block);
        $published = '/blocks/' . 'cblk-' . str_replace('_', '-', $def['key']) . '-';
        $css = array_values(array_filter($theme->conditionalCss(in_array('lightbox', $def['behaviours'], true) ? [$type, 'gallery'] : [$type]),
            fn($u) => !str_contains($u, $published)));
        $css[] = url('/admin/blocks/preview.css') . '?t=' . $token;
        $html = $theme->render('layout', [
            'page' => $home, 'content' => $section, 'seo' => ['noindex' => true] + Seo::forPage($home), 'editor' => null,
            'extraCss' => $css, 'extraJs' => [], 'toolbar' => null,
        ]);
        if ($r->str('_dark') === '1') $html = DesignController::forceDark($html, Design::values());
        return self::secure(Response::json($out + ['html' => $html]));
    }

    private static function message(array $errors): string
    {
        $list = '';
        foreach ($errors as $k => $m) if (!str_ends_with((string) $k, '_line')) $list .= '<li>' . e($m) . '</li>';
        return '<!doctype html><html lang="de"><meta charset="utf-8"><body><div class="cblk-previewerr"><b>' . e(__('Vorschau nicht möglich:')) . '</b><ul>' . $list . '</ul></div></body></html>';
    }

    /** Entwurfs-CSS der letzten Vorschau (aus der Sitzung) */
    public function previewCss(Request $r): Response
    {
        $this->guard($r);
        $all = (array) app()->session->get('cblk_preview_css', []);
        $css = (string) ($all[$r->str('t')] ?? '');
        return self::secure(new Response($css, 200, ['Content-Type' => 'text/css; charset=utf-8']));
    }

    /** Formular für Beispieldaten (gleicher Feld-Renderer wie in der Seitenleiste des Editors) */
    public function sampleForm(Request $r): Response
    {
        $this->guard($r);
        [$fields] = Custom::normFields(json_decode($r->str('fields_json', '[]'), true) ?: []);
        $json = json_decode($r->str('sample_json'), true);
        $sample = Custom::normSample($fields, is_array($json) ? $json : (array) ($r->post['sample'] ?? []));
        if ($r->str('reset') === '1') $sample = Custom::sample($fields);
        return self::secure(Response::json(['ok' => true, 'html' => $fields ? Fields::renderForm($fields, $sample, [], 'sample')
            : '<p class="adm-muted">' . e(__('Noch keine Felder – zuerst im Reiter „Felder“ anlegen.')) . '</p>']));
    }

    /** KLXM AI: Vorschlag (wird nie gespeichert oder freigegeben – die Person prüft ihn im Block-Designer) */
    public function ai(Request $r): Response
    {
        try {
            $this->guard($r);
            if (!Assist::available('text')) return self::secure(Response::json(['ok' => false, 'error' => __('Der KI-Assistent ist für Sie auf dieser Website nicht verfügbar.')], 403));
            $res = \Core\AI\Generator::proposeBlock($r->str('description'));
            Assist::log('blockgen', 'propose', mb_substr($r->str('description'), 0, 80), 'suggested', (string) ($res['model'] ?? ''), (int) ($res['ms'] ?? 0));
            $def = $res['def'];
            $def['sample'] = Custom::sample($def['fields']);
            return self::secure(Response::json(['ok' => true, 'def' => $def, 'errors' => $res['errors'], 'warnings' => $res['warnings'], 'quota' => Assist::quota()['left']]));
        } catch (AiException $e) {
            Assist::log('blockgen', 'propose', '', 'failed');
            return self::secure(Response::json(['ok' => false, 'error' => $e->getMessage()], 422));
        }
    }
}
