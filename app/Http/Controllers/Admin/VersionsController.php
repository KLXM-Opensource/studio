<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Data\Entries;
use Core\Data\Revisions;
use Core\Data\Shared;
use Core\Data\Tables;
use Core\Format;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\NotFound;
use Core\Pages;

/**
 * Versionen ansehen und wiederherstellen (resources/js/versions.mjs) – Seiten (Tabelle revisions) und Einträge (Core\Data\Revisions).
 *
 *  GET  /admin/api/pages/{id}/versions                → Stände der Seite, je Stand eine Vorschau-Adresse (echte Seite im Iframe)
 *  GET  /admin/pages/{id}/versions/{rev}/vorschau     → HTML der Seite in diesem Stand (noindex, no-store, ohne Redaktionsnotizen)
 *  POST /admin/pages/{id}/restore/{rev}               → PageController::restore (JSON: Stand als Entwurf)
 *  GET  /admin/api/data/{handle}/{id}/versions        → Stände des Eintrags mit Feldern (HTML wie auf der Website, „geändert“ ggü. jetzt)
 *  POST /admin/api/data/{handle}/{id}/versions/{rev}/restore → Stand zurückschreiben (Status bleibt)
 *
 * Erster Eintrag der Liste ist immer „Jetzt“ (aktueller Stand); ältere Stände, die dem aktuellen gleichen, fallen weg.
 */
final class VersionsController extends AdminController
{
    /** Attribute für einen Knopf, der die Versionen öffnet (admin.js lädt resources/js/versions.mjs erst beim Klick) */
    public static function attrs(string $endpoint): string
    {
        return ' data-versions="' . e(url($endpoint)) . '" data-versions-module="' . e(asset('js/versions.mjs')) . '" data-versions-csrf="' . e(\Core\Csrf::token()) . '" aria-haspopup="dialog"';
    }

    /** Texte für resources/js/versions.mjs (Sprache der Verwaltung) */
    public static function texts(): array
    {
        return [
            'title' => __('Versionen'), 'today' => __('Heute'), 'desktop' => __('Desktop'), 'mobile' => __('Mobil'), 'changes' => __('Geändert: {list}'),
            'preview' => __('Vorschau dieses Stands'),
            'now' => __('Jetzt'), 'count' => __('{n} ältere Stände'), 'count1' => __('1 älterer Stand'), 'close' => __('Schließen'), 'timeline' => __('Zeitleiste'),
            'older' => __('Älterer Stand'), 'newer' => __('Neuerer Stand'), 'cancel' => __('Abbrechen'), 'restore' => __('Wiederherstellen'),
            'back' => __('Zurück'), 'yes' => __('Ja, wiederherstellen'), 'restoring' => __('Wird wiederhergestellt …'),
            'askPage' => __('Diesen Stand als Entwurf wiederherstellen? Die Seite öffnet danach im Editor – veröffentlicht wird erst, wenn Sie es tun.'),
            'askEntry' => __('Diesen Stand wiederherstellen? Die Felder des Eintrags werden sofort auf diesen Stand gesetzt; der jetzige Stand bleibt als Version erhalten.'),
            'isNow' => __('Das ist der aktuelle Stand.'), 'loading' => __('Lade Vorschau …'), 'failed' => __('Wiederherstellen fehlgeschlagen.'),
            'done' => __('Stand wiederhergestellt.'), 'onlyChanges' => __('Nur Änderungen'), 'changed' => __('geändert'), 'empty' => __('leer'),
            'noFields' => __('Keine Felder.'), 'draft' => __('Entwurf'), 'published' => __('Veröffentlicht'),
        ];
    }

    // ------------------------------------------------------------------ Seiten

    private function page(Request $r, string $id): array
    {
        $this->auth($r, 'pages.edit');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        if (\Core\PageTemplates::isTemplatePage($page) && !can('system.manage')) throw new HttpException(403);
        return $page;
    }

    /** Zeitangaben eines Stands: day (Gruppe der Zeitleiste), time, ago, at (lang) */
    private static function when(?string $at): array
    {
        $f = Format::admin();
        $at = $at ?: now();
        return ['day' => $f->date($at, 'long'), 'time' => $f->time($at), 'ago' => $f->relative($at), 'at' => $f->datetime($at, 'long')];
    }

    public function pageVersions(Request $r, string $id): Response
    {
        $page = $this->page($r, $id);
        $f = Format::admin();
        $strip = fn(?string $json) => json_encode(json_decode((string) $json, true)['blocks'] ?? []);
        $now = $strip($page['content_draft'] ?? $page['content_published']);
        $out = [[
            'id' => 0, 'now' => true, 'label' => __('Jetzt'), ...self::when($page['updated_at'] ?? null),
            'note' => $page['status'] === 'published' && ($page['content_draft'] ?? null) !== null && $page['content_draft'] !== $page['content_published'] ? __('Entwurf mit unveröffentlichten Änderungen') : __('Aktueller Stand'),
            'user' => '', 'preview' => url('/admin/pages/' . (int) $page['id'] . '/vorschau'),
        ]];
        $seen = [$now => true];
        foreach (app()->db->fetchAll('SELECT r.id, r.created_at, r.note, r.blocks_json, u.email FROM revisions r LEFT JOIN users u ON u.id = r.user_id WHERE r.page_id = ? ORDER BY r.id DESC', [(int) $page['id']]) as $rv) {
            $k = $strip($rv['blocks_json']);
            if (isset($seen[$k])) continue;   // gleicher Inhalt wie ein neuerer Stand
            $seen[$k] = true;
            $out[] = ['id' => (int) $rv['id'], 'now' => false, 'label' => $f->relative($rv['created_at']), ...self::when($rv['created_at']),
                'note' => (string) ($rv['note'] ?? '') ?: __('Gespeichert'), 'user' => (string) ($rv['email'] ?? ''),
                'preview' => url('/admin/pages/' . (int) $page['id'] . '/versions/' . (int) $rv['id'] . '/vorschau')];
        }
        return Response::json(['ok' => true, 'kind' => 'page', 'title' => (string) $page['title'], 'versions' => $out, 'texts' => self::texts(), 'today' => Format::admin()->date(now(), 'long'),
            'restore' => url('/admin/pages/' . (int) $page['id'] . '/restore/{rev}'), 'can_restore' => can('pages.edit')]);
    }

    public function pagePreview(Request $r, string $id, string $rev): Response
    {
        $page = $this->page($r, $id);
        if (($page['type'] ?? 'page') === 'template' && !\Core\PageTemplates::isTemplatePage($page) && !NotFound::isPage($page)) throw new HttpException(404);
        $json = app()->db->fetchValue('SELECT blocks_json FROM revisions WHERE id = ? AND page_id = ?', [(int) $rev, (int) $page['id']]) ?? throw new HttpException(404);
        $site = new \Core\Http\Controllers\SiteController();
        return $site->respond($site->previewHtml(['content_draft' => (string) $json] + $page, true), true)
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store, private');
    }

    // ------------------------------------------------------------------ Einträge

    private function entry(Request $r, string $handle, string $id): array
    {
        $this->auth($r);
        $t = Tables::find($handle) ?? throw new HttpException(404);
        if (!can('data.edit', $handle)) throw new HttpException(403, __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.'));
        $e = Entries::find($t, (int) $id) ?? throw new HttpException(404);
        if (Shared::isForeign($t, $e)) throw new HttpException(403);
        return [$t, $e];
    }

    public function entryVersions(Request $r, string $handle, string $id): Response
    {
        [$t, $e] = $this->entry($r, $handle, $id);
        $f = Format::admin();
        $now = Revisions::snapshot($t, $e);
        $key = fn(array $d) => json_encode($d, JSON_UNESCAPED_UNICODE);
        $fields = function (array $data) use ($t, $e, $now): array {
            $row = ['id' => (int) $e['id']] + $data + $e;
            $out = [];
            foreach ($t['fields'] as $fd) {
                $n = $fd['name'];
                $v = $data[$n] ?? null;
                $empty = $v === null || $v === '' || $v === [];
                $changed = json_encode($v) !== json_encode($now[$n] ?? null);
                if ($empty && !$changed) continue;
                try {
                    $html = $empty ? '' : Entries::html($t, $row, $n, ['sizes' => '480px']);
                } catch (\Throwable) {
                    $html = e(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
                }
                $out[] = ['name' => $n, 'label' => (string) ($fd['label'] ?? $n), 'type' => (string) $fd['type'], 'html' => $html, 'empty' => $empty, 'changed' => $changed];
            }
            return $out;
        };
        $out = [['id' => 0, 'now' => true, 'label' => __('Jetzt'), ...self::when($e['updated_at'] ?? null), 'note' => __('Aktueller Stand'), 'user' => '',
            'status' => (string) ($e['status'] ?? ''), 'title' => Entries::title($t, $e), 'fields' => $fields($now), '_d' => $now]];
        $seen = [$key($now) => true];
        foreach (Revisions::list($t, (int) $e['id']) as $rv) {
            $k = $key($rv['data']);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $out[] = ['id' => $rv['id'], 'now' => false, 'label' => $f->relative($rv['created_at']), ...self::when($rv['created_at']),
                'note' => $rv['note'] ?: __('Gespeichert'), 'user' => $rv['user_email'], 'status' => $rv['status'],
                'title' => Entries::title($t, ['id' => (int) $e['id']] + $rv['data'] + $e), 'fields' => $fields($rv['data']), '_d' => $rv['data']];
        }
        // Je Stand: welche Felder sich gegenüber dem nächstälteren geändert haben (Karte in der Zeitleiste: „Titel, Bild“)
        foreach ($out as $i => &$v) {
            $older = $out[$i + 1]['_d'] ?? null;
            $v['changes'] = $older === null ? [] : array_values(array_map(fn($fd) => (string) ($fd['label'] ?? $fd['name']),
                array_filter($t['fields'], fn($fd) => json_encode($v['_d'][$fd['name']] ?? null) !== json_encode($older[$fd['name']] ?? null))));
        }
        unset($v);
        $out = array_map(function ($v) { unset($v['_d']); return $v; }, $out);
        return Response::json(['ok' => true, 'kind' => 'entry', 'title' => Entries::title($t, $e), 'table' => (string) $t['name'], 'versions' => $out, 'texts' => self::texts(), 'today' => Format::admin()->date(now(), 'long'),
            'restore' => url('/admin/api/data/' . $t['handle'] . '/' . (int) $e['id'] . '/versions/{rev}/restore'), 'can_restore' => true]);
    }

    public function entryRestore(Request $r, string $handle, string $id, string $rev): Response
    {
        [$t, $e] = $this->entry($r, $handle, $id);
        $errors = Revisions::restore($t, (int) $e['id'], (int) $rev);
        if ($errors) return Response::json(['ok' => false, 'error' => implode(' ', array_map('strval', $errors)), 'errors' => $errors], 422);
        return Response::json(['ok' => true, 'message' => __('Stand wiederhergestellt.'), 'url' => (string) Entries::url($t, Entries::find($t, (int) $e['id']) ?? $e)]);
    }
}
