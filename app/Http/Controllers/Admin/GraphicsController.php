<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Graphics\Graphics;
use Core\Graphics\Library;
use Core\Http\Request;
use Core\Http\Response;
use Core\Media;
use Core\Svg;

/**
 * Grafik-Editor (Core\Graphics, resources/js/grafik.js): eigene Seite /admin/grafik/editor und JSON-API für das Overlay
 * (CMSGraphicEditor.open aus Mediathek, Blockauswahl, Erweiterungen). Alle Routen: Anmeldung + Recht „media.upload“,
 * POST zusätzlich CSRF (AdminController::auth). Jedes gespeicherte SVG läuft durch Core\Svg::clean().
 */
final class GraphicsController extends AdminController
{
    /** Übersicht der eigenen Ablage: Symbole, Blockvorschauen, Bausteine (nicht in der Mediathek) */
    public function index(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        // nur die Arten, die die Rolle bearbeiten darf (Graphics::RIGHTS)
        $groups = [];
        foreach (['preview', 'icon', 'part'] as $k) if (Graphics::canKind($k)) $groups[$k] = Graphics::all($k);
        return $this->view('grafik/index', [
            'css' => ['css/grafik.css'],
            'groups' => $groups,
            'blocks' => Graphics::canKind('preview') ? Graphics::blockTypes() : [],
        ]);
    }

    /** Recht für eine Art der Ablage (Graphics::RIGHTS) – sonst 403 */
    private function needKind(Request $r, string $kind): ?Response
    {
        if (Graphics::canKind($kind)) return null;
        $msg = __('Für diese Grafik fehlt Ihrer Rolle die Berechtigung.');
        if (!$r->wantsJson() && $r->method === 'POST') return $this->back('/admin/grafik', 'error', $msg);
        return Response::fail($msg, 403);
    }

    /** Editor als eigene Seite (Vollbild) – gleiche Daten wie boot() für das Overlay */
    public function editor(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        $boot = $this->bootData($r);
        if (isset($boot['error']) || isset($boot['denied'])) {
            return $this->back('/admin/media', 'error', (string) ($boot['denied'] ?? $boot['error']));
        }
        return $this->view('grafik/editor', ['css' => ['css/grafik.css'], 'boot' => $boot, 'title' => __('Grafik-Editor')]);
    }

    /** Daten zum Öffnen im Overlay: GET /admin/api/grafik/boot?format=…|media=…|id=…|block=… */
    public function boot(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        $boot = $this->bootData($r);
        if (isset($boot['denied'])) return Response::fail((string) $boot['denied'], 403);
        return isset($boot['error']) ? Response::fail((string) $boot['error'], 404) : Response::json(['ok' => true] + $boot);
    }

    /**
     * Dokument + Umgebung für den Editor. Quellen (eine davon):
     *   media={id}   SVG aus der Mediathek (Ziel: Mediathek, neue Fassung derselben Datei)
     *   id={id}      Grafik der eigenen Ablage
     *   format=info|icon|preview (+ w, h bei Infografik, block bei Blockvorschau) → leere Fläche bzw. Vorschau des Kits als Vorlage
     */
    private function bootData(Request $r): array
    {
        $doc = ['format' => 'info', 'target' => 'media', 'svg' => '', 'name' => '', 'alt' => '', 'title' => '', 'mediaId' => null,
            'id' => null, 'block' => null, 'ref' => null];
        $mediaId = (int) $r->str('media');
        $id = (int) $r->str('id');
        if ($mediaId > 0) {
            $m = Media::find($mediaId);
            if (!$m || ($m['mime'] ?? '') !== Svg::MIME) return ['error' => __('Nur SVG-Grafiken lassen sich im Grafik-Editor bearbeiten.')];
            $raw = @file_get_contents(Media::path($m));
            if (!is_string($raw) || $raw === '') return ['error' => __('Datei nicht gefunden.')];
            try {
                // Arbeitsfassung des Editors, wenn sie zur Datei gehört (Ebenen, Hilfslinien), sonst die Datei selbst
                $work = Graphics::source('media:' . $mediaId, $raw);
                $svg = Svg::clean($work ?? $raw, $work !== null ? ['editor' => true] : ['keep_ids' => true])['svg'];
            } catch (\RuntimeException $e) {
                return ['error' => $e->getMessage()];
            }
            $doc = ['svg' => $svg, 'mediaId' => $mediaId, 'name' => pathinfo((string) $m['file'], PATHINFO_FILENAME), 'alt' => (string) ($m['alt'] ?? ''),
                'title' => (string) ($m['title'] ?? ''), 'shared' => !empty($m['pool_ref']), 'ref' => 'media:' . $mediaId] + $doc;
        } elseif ($id > 0) {
            $g = Graphics::find($id);
            if (!$g) return ['error' => __('Grafik nicht gefunden.')];
            if (!Graphics::canKind((string) $g['kind'])) return ['denied' => __('Für diese Grafik fehlt Ihrer Rolle die Berechtigung.')];
            $fmt = $g['kind'] === 'part' ? 'info' : (string) $g['kind'];
            $doc = ['format' => $fmt, 'target' => 'graphics', 'kind' => (string) $g['kind'], 'svg' => (string) $g['svg'], 'id' => (int) $g['id'],
                'name' => (string) $g['name'], 'block' => $g['block'] ? (string) $g['block'] : null, 'ref' => 'graphic:' . (int) $g['id']] + $doc;
        } else {
            $fmt = isset(Graphics::FORMATS[$r->str('format')]) ? $r->str('format') : 'info';
            if ($fmt !== 'info' && !Graphics::canKind($fmt)) return ['denied' => __('Für diese Grafik fehlt Ihrer Rolle die Berechtigung.')];
            $block = preg_match('~^[a-z0-9_-]{1,80}$~', $r->str('block')) ? $r->str('block') : null;
            // Blockauswahl „Vorschau gestalten“: Variante als „typ--variante“
            if ($block !== null && preg_match('~^[a-z0-9_-]{1,40}$~', $r->str('variant')) && !str_contains($block, '--')) $block .= '--' . $r->str('variant');
            $svg = $fmt === 'preview' && $block ? Graphics::previewTemplate($block) : null;
            $blocks = $block ? Graphics::blockTypes() : [];
            $doc = ['format' => $fmt, 'target' => Graphics::FORMATS[$fmt]['store'], 'kind' => $fmt === 'info' ? null : $fmt,
                'svg' => $svg ?? Graphics::blank($fmt, (int) $r->str('w'), (int) $r->str('h')), 'block' => $block,
                'name' => $block ? (string) ($blocks[$block] ?? $block) : ''] + $doc;
        }
        $doc['versions'] = $doc['ref'] ? Graphics::versions((string) $doc['ref']) : [];
        return [
            'doc' => $doc,
            'formats' => Graphics::FORMATS,
            'formatLabels' => Graphics::formatLabels(),
            'library' => Library::forEditor(),
            'libraryGroups' => Library::groups(),
            'parts' => array_map(fn($p) => $p + ['svg' => (string) (Graphics::find((int) $p['id'])['svg'] ?? '')], Graphics::all('part')),
            // eigene Bausteine speichern (Bibliothek der Website) nur mit Recht „blocks.build“
            'canParts' => Graphics::canKind('part'),
            'swatches' => Graphics::swatches(),
            'fonts' => Graphics::fonts(),
            'fontFiles' => Graphics::fontFiles(),
            // Bibliotheken auf Abruf (nur beim ersten Gebrauch): Geometrie, Text in Kurven, PDF-Export
            'modules' => ['paper' => asset('js/grafik-paper.mjs'), 'fonts' => asset('js/grafik-fonts.mjs'), 'pdf' => asset('js/grafik-pdf.mjs'),
                // Silbentrennung in Textrahmen (Trennmuster je Sprache)
                'hyphen' => ['de' => asset('js/grafik-hyph-de.mjs'), 'en' => asset('js/grafik-hyph-en.mjs')]],
            'blocks' => Graphics::blockTypes(),
            'icons' => url('/assets/icons/catalog.json'),
            'urls' => [
                'save' => url('/admin/api/grafik/save'), 'part' => url('/admin/api/grafik/part'), 'version' => url('/admin/api/grafik/version'),
                'clean' => url('/admin/api/grafik/clean'),
                'delete' => url('/admin/api/grafik/{id}/delete'), 'media' => url('/admin/media'), 'store' => url('/admin/grafik'),
                'page' => url('/admin/grafik/editor'), 'prefs' => url('/admin/api/grafik/prefs'),
            ],
            'canMediaSvg' => \Core\Features::on('media.svg', false),
            // persönlich (Core\UiPrefs „grafik“): Schrift-Favoriten, zuletzt verwendete Schriften, Farbmodus
            'prefs' => Graphics::prefs(app()->auth->user()),
        ];
    }

    /**
     * Speichern: POST {svg, target: media|graphics, mediaId?, id?, kind?, name, alt, title, decorative, block, assign}
     * Antwort: {ok, item, url} bzw. {ok: false, error}
     */
    public function save(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        // svg = Arbeitsfassung des Editors, web = optimierte Fassung für die Website (fehlt sie, gilt svg auch fürs Web)
        $source = (string) ($r->post['svg'] ?? '');
        $svg = is_string($r->post['web'] ?? null) && $r->post['web'] !== '' ? (string) $r->post['web'] : $source;
        if ($svg === '') return Response::fail(__('Keine Grafik übermittelt.'));
        $target = (string) ($r->post['target'] ?? 'media');
        try {
            if ($target === 'graphics') {
                $kind = (string) ($r->post['kind'] ?? 'icon');
                if ($deny = $this->needKind($r, $kind)) return $deny;
                $block = isset($r->post['block']) && is_string($r->post['block']) && $r->post['block'] !== '' ? $r->post['block'] : null;
                $item = Graphics::store($kind, (string) ($r->post['name'] ?? ''), $svg, (int) ($r->post['id'] ?? 0) ?: null, $block, $source);
                if ($kind === 'preview' && $block !== null && !empty($r->post['assign'])) {
                    Graphics::assignPreview($block, $item['id']);
                    $item = Graphics::toJson((array) Graphics::find($item['id']));
                }
                return Response::json(['ok' => true, 'item' => $item, 'versions' => Graphics::versions('graphic:' . $item['id'])]);
            }
            $mediaId = (int) ($r->post['mediaId'] ?? 0) ?: null;
            // Überschreiben ohne Angaben: Alt-Text und Titel der Datei bleiben (nur mitgeschickte Felder ändern sich)
            $meta = ['name' => (string) ($r->post['name'] ?? ''), 'decorative' => !empty($r->post['decorative'])];
            foreach (['title', 'alt'] as $k) if (isset($r->post[$k]) && is_string($r->post[$k])) $meta[$k] = $r->post[$k];
            [$m, $err] = Graphics::saveMedia($svg, $meta, $mediaId, $source);
            if ($err || !$m) return Response::fail((string) ($err ?: __('Speichern fehlgeschlagen.')));
            $this->changed();
            return Response::json(['ok' => true, 'item' => ['id' => (int) $m['id'], 'url' => Media::originalUrl($m), 'name' => Media::displayName($m)],
                'versions' => Graphics::versions('media:' . (int) $m['id'])]);
        } catch (\RuntimeException $e) {
            return Response::fail($e->getMessage());
        }
    }

    /** SVG nur bereinigen (Einbettungen ohne Ablage, fremdes SVG vor dem Laden): POST {svg} → {svg} */
    public function clean(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        try {
            // editor: Arbeitsfassung (Editor-Daten bleiben); images: entfernte Pixelbilder (der Editor meldet sie)
            $c = Svg::clean((string) ($r->post['svg'] ?? ''), !empty($r->post['editor']) ? ['editor' => true] : ['keep_ids' => true]);
            return Response::json(['ok' => true, 'svg' => $c['svg'], 'images' => $c['images'], 'bytes' => $c['out']]);
        } catch (\RuntimeException $e) {
            return Response::fail($e->getMessage());
        }
    }

    /** Persönliche Einstellung des Editors (Schrift-Favoriten, zuletzt verwendet, Farbmodus): POST {key, value(JSON)} */
    public function prefs(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        $user = app()->auth->user();
        if (!$user) return Response::fail(__('Nicht angemeldet.'), 403);
        // JSON-Anfrage: Liste bzw. Text direkt; Formular: JSON-Text
        $value = $r->post['value'] ?? null;
        if (is_string($value) && ($value[0] ?? '') === '[') $value = json_decode($value, true);
        try {
            return Response::json(['ok' => true, 'prefs' => Graphics::setPref((int) $user['id'], (string) ($r->post['key'] ?? ''), $value)]);
        } catch (\RuntimeException $e) {
            return Response::fail($e->getMessage());
        }
    }

    /** Eigenen Baustein speichern: POST {svg, name} */
    public function part(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        if ($deny = $this->needKind($r, 'part')) return $deny;
        try {
            $item = Graphics::store('part', (string) ($r->post['name'] ?? ''), (string) ($r->post['svg'] ?? ''));
        } catch (\RuntimeException $e) {
            return Response::fail($e->getMessage());
        }
        return Response::json(['ok' => true, 'item' => $item + ['svg' => (string) (Graphics::find($item['id'])['svg'] ?? '')]]);
    }

    /** Grafik der eigenen Ablage löschen (Recht „Dateien löschen“) */
    public function delete(Request $r, string $id): Response
    {
        $this->auth($r, 'media.delete');
        $g = Graphics::find((int) $id);
        if ($g && ($deny = $this->needKind($r, (string) $g['kind']))) return $deny;
        $ok = Graphics::delete((int) $id);
        if (!$r->wantsJson()) return $this->back('/admin/grafik', $ok ? 'success' : 'error', $ok ? __('Grafik gelöscht.') : __('Grafik nicht gefunden.'));
        return $ok ? Response::json(['ok' => true]) : Response::fail(__('Grafik nicht gefunden.'), 404);
    }

    /** Blockvorschau zuordnen oder aufheben: POST {block, id|''} */
    public function assign(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        if ($deny = $this->needKind($r, 'preview')) return $deny;   // gilt für die ganze Website (block_previews)
        $block = (string) ($r->post['block'] ?? '');
        $id = (int) ($r->post['id'] ?? 0);
        try {
            Graphics::assignPreview($block, $id ?: null);
        } catch (\RuntimeException $e) {
            if (!$r->wantsJson()) return $this->back('/admin/grafik', 'error', $e->getMessage());
            return Response::fail($e->getMessage());
        }
        if (!$r->wantsJson()) return $this->back('/admin/grafik', 'success', $id ? __('Blockvorschau gilt jetzt in der Blockauswahl.') : __('Blockvorschau aufgehoben.'));
        return Response::json(['ok' => true]);
    }

    /** Frühere Fassung laden: GET ?ref=media:{id}|graphic:{id}&id={version} */
    public function version(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        $ref = $r->str('ref');
        if (!preg_match('~^(media|graphic):\d+$~', $ref)) return Response::fail(__('Unbekannte Grafik.'), 404);
        if (str_starts_with($ref, 'graphic:') && ($g = Graphics::find((int) substr($ref, 8))) && ($deny = $this->needKind($r, (string) $g['kind']))) return $deny;
        $svg = Graphics::version($ref, (int) $r->str('id'));
        if ($svg === null) return Response::fail(__('Fassung nicht gefunden.'), 404);
        try {
            $svg = Svg::clean($svg, ['keep_ids' => true])['svg'];   // vor dem Laden in die Verwaltung erneut bereinigen
        } catch (\RuntimeException $e) {
            return Response::fail($e->getMessage());
        }
        return Response::json(['ok' => true, 'svg' => $svg]);
    }
}
