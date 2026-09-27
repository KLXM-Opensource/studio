<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Media;
use Core\Pages;

/** JSON-API für den Inline-Editor (Editor.js). */
final class EditorController extends AdminController
{
    /** Speichert den Entwurf; mit publish=true zusätzlich veröffentlichen. */
    public function save(Request $r, string $id): Response
    {
        $user = $this->auth($r, 'pages.edit');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        $blocks = Pages::sanitizeBlocks((array) ($r->post['blocks'] ?? []));
        $publish = !empty($r->post['publish']);
        if ($publish && !can('pages.publish')) {
            return Response::json(['ok' => false, 'error' => __('Veröffentlichen ist Ihrer Rolle nicht erlaubt – der Entwurf wurde nicht gespeichert.')], 403);
        }
        Pages::saveDraft((int) $page['id'], $blocks, (int) $user['id'], $publish ? 'Veröffentlicht' : 'Gespeichert');
        if ($publish) {
            try {
                Pages::publish((int) $page['id'], (int) $user['id']);
            } catch (\RuntimeException $e) {   // offene Platzhalter „[bitte ergänzen: …]“ – Entwurf ist gespeichert
                $this->changed();
                return Response::json(['ok' => false, 'error' => __('Entwurf gespeichert.') . ' ' . $e->getMessage()], 422);
            }
        }
        $this->changed();
        return Response::json(['ok' => true, 'published' => $publish, 'saved_at' => date('H:i')]);
    }

    /** Entwurf verwerfen (zurück zur veröffentlichten Fassung). */
    public function discard(Request $r, string $id): Response
    {
        $user = $this->auth($r, 'pages.edit');
        Pages::find((int) $id) ?? throw new HttpException(404);
        if (!Pages::discardDraft((int) $id, (int) $user['id'])) {
            return Response::json(['ok' => false, 'error' => 'Seite wurde noch nie veröffentlicht.'], 422);
        }
        $this->changed();
        return Response::json(['ok' => true]);
    }

    /** Rendert einen einzelnen Block (Live-Vorschau im Editor). */
    public function preview(Request $r): Response
    {
        $this->auth($r, 'pages.edit');
        $pageId = (int) ($r->post['page'] ?? 0);
        app()->currentPage = $pageId ? Pages::find($pageId) : Pages::home();
        app()->editing = true;
        app()->dataEdit = true;   // Datenlisten: Stift je Eintrag wie in der ersten Vorschau
        $this->entryContext($r);
        $raw = (array) ($r->post['block'] ?? []);
        $blocks = Pages::sanitizeBlocks([$raw]);
        if (!$blocks) {
            return Response::json(['ok' => false, 'error' => 'Unbekannter Blocktyp'], 422);
        }
        $block = app()->theme->makeBlock($blocks[0]);
        $html = app()->theme->renderBlock($block);
        // Bild im Rahmen (Core\ImageFit): erzeugte Regeln (Farbe, unscharfer Hintergrund) für die Vorschau mitliefern
        $html .= \Core\ImageFit::rulesLink();
        \Core\ImageFit::reset();
        return Response::json(['ok' => true, 'html' => $html, 'block' => $blocks[0]]);
    }

    /** Feldformular eines Blocks für die Seitenleiste (gleicher Renderer wie im Admin). */
    public function form(Request $r): Response
    {
        $this->auth($r, 'pages.edit');
        $type = (string) ($r->post['type'] ?? '');
        $def = app()->theme->block($type) ?? throw new HttpException(404);
        $data = array_replace(\Core\Fields::defaults($def['fields']), (array) ($r->post['data'] ?? []));
        // Auf Detailseiten-Vorlagen die Tabelle vorauswählen
        if ($ctx = $this->entryContext($r)) {
            foreach ($def['fields'] as $f) {
                if (($f['type'] ?? '') === 'datatable' && empty($data[$f['name']])) $data[$f['name']] = $ctx['table']['handle'];
            }
        }
        $html = '';
        if (!empty($def['variants'])) {
            $html .= \Core\Fields::renderField(['name' => 'variant', 'label' => 'Variante', 'type' => 'select', 'required' => true,
                'options' => $def['variants']], $data['variant'] ?? array_key_first($def['variants']), [], 'f');
        }
        // Detailseiten-Vorlage: Felder lassen sich an den Datensatz binden
        if ($ctx) {
            \Core\Fields::$binding = ['table' => $ctx['table'], 'bound' => (array) ($data['_bind'] ?? [])];
            $html = '<div class="cms-bindnote"><b>Vorlage für alle Einträge von „' . e($ctx['table']['name']) . '“.</b> Mit dem Ketten-Symbol <b>' . icon('link') . '</b> neben einem Feld nimmt es seinen Inhalt aus dem jeweiligen Eintrag – z. B. Bild, Überschrift, Text oder Datum.</div>' . $html;
        }
        // Hinweise zu diesem Projekt für diesen Blocktyp (Core\Guide, Front Matter „block:“)
        if ($guide = \Core\Guide::forBlock($type)) {
            $html = '<p class="cms-bindnote cms-guidenote">' . e(count($guide) > 1 ? __('Hinweise zum Projekt:') : __('Hinweis zum Projekt:')) . ' '
                . implode(' · ', array_map(fn($n) => '<a href="' . e(\Core\Guide::url($n)) . '" target="_blank" rel="noopener">' . e($n['title']) . '</a>', $guide)) . '</p>' . $html;
        }
        $html .= \Core\Fields::renderForm($def['fields'], $data, [], 'f');
        \Core\Fields::$binding = null;
        return Response::json(['ok' => true, 'html' => $html]);
    }

    /** Eintrag der Detailseiten-Vorlage für die Vorschau setzen */
    private function entryContext(Request $r): ?array
    {
        $e = (array) ($r->post['entry'] ?? []);
        $t = !empty($e['table']) ? \Core\Data\Tables::findContent((string) $e['table']) : null;
        $entry = $t ? \Core\Data\Entries::find($t, (int) ($e['id'] ?? 0)) : null;
        return app()->entry = $t && $entry ? ['table' => $t, 'entry' => $entry] : null;
    }

    /** Interne Links (Seiten + Anker) für Autocomplete */
    /**
     * Linkziele. Ohne Parameter: flache Liste [{value, label}] (<datalist id="cms-links">, kompatibel).
     * ?format=groups&q=…&page=ID&mode=rich|field → Gruppen für die Linkauswahl (Core\Links::sources, resources/js/_links.js).
     * ?describe=Wert → lesbare Beschreibung eines Link-Werts (Anzeige im Feld „link“).
     */
    public function links(Request $r): Response
    {
        $this->auth($r);
        if (!can('pages.edit') && !can('data.edit') && !can('settings.edit')) throw new HttpException(403);
        if (($v = $r->str('describe')) !== '') {
            return Response::json(\Core\Links::describe($v));
        }
        if ($r->str('format') === 'groups') {
            return Response::json(['groups' => \Core\Links::sources([
                'q' => mb_substr($r->str('q'), 0, 80), 'page' => (int) $r->str('page'), 'mode' => $r->str('mode'),
            ])]);
        }
        $out = [];
        foreach (Pages::all() as $p) {
            $base = $p['is_home'] ? '/' : '/' . $p['slug'];
            $out[] = ['value' => $base, 'label' => 'Seite: ' . $p['title']];
            foreach (Pages::anchors($p, true) as $a => $label) {
                $out[] = ['value' => ($p['is_home'] ? '' : $base) . '#' . $a, 'label' => $p['title'] . ' → ' . strip_tags((string) $label)];
            }
        }
        // Sonderwerte des Themes (z. B. „telefon“) mit Beschriftung aus theme.php → project.link_labels
        $labels = (array) project('link_labels', []);
        foreach ((array) (app()->theme->def['link_keywords'] ?? []) as $v) {
            $out[] = ['value' => $v, 'label' => $labels[$v] ?? $v];
        }
        return Response::json($out);
    }

    public function media(Request $r): Response
    {
        $this->auth($r);
        $kind = $r->str('kind') === 'image' ? 'image' : null;
        return Response::json(array_map([Media::class, 'toJson'], Media::all($kind)));
    }
}
