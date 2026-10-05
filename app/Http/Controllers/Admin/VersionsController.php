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
        return ' data-versions="' . e(url($endpoint)) . '" data-versions-module="' . e(asset('js/versions.mjs')) . '" data-versions-css="' . e(asset('css/versions.css')) . '" data-versions-csrf="' . e(\Core\Csrf::token()) . '" aria-haspopup="dialog"';
    }

    /** Texte für resources/js/versions.mjs (Sprache der Verwaltung) */
    public static function texts(): array
    {
        return [
            'title' => __('Versionen'), 'today' => __('Heute'), 'desktop' => __('Desktop'), 'mobile' => __('Mobil'), 'changes' => __('Geändert: {list}'),
            'preview' => __('Vorschau dieses Stands'),
            'compare' => __('Gegenüberstellen'), 'live' => __('Live'), 'liveDraft' => __('Jetzt (noch nie veröffentlicht)'), 'selected' => __('Gewählter Stand'),
            'highlight' => __('Änderungen hervorheben'), 'markNew' => __('Neu'), 'markChanged' => __('Geändert'),
            'blocksNew' => __('{n} neu'), 'blocksChanged' => __('{n} geändert'), 'blocksRemoved' => __('Entfernt: {list}'),
            'noDiff' => __('Keine Änderung an den Blöcken gegenüber dem vorherigen Stand.'), 'firstState' => __('Ältester Stand – nichts zum Vergleichen.'),
            'diffHint' => __('Hervorgehoben: Änderungen gegenüber dem vorherigen Stand.'),
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

    /** Blöcke eines Stands (JSON der Seite bzw. Version) */
    private static function blocksOf(?string $json): array
    {
        return array_values(array_filter((array) (json_decode((string) $json, true)['blocks'] ?? []), 'is_array'));
    }

    /**
     * Unterschied zweier Stände einer Seite (je Block-ID): neu, geändert, entfernt. Vergleich ohne Zeitstempel/Reihenfolge-Felder.
     * @return array{marks: array<string,string>, new: int, changed: int, removed: list<string>}
     */
    private static function blockDiff(array $blocks, ?array $base): array
    {
        $out = ['marks' => [], 'new' => 0, 'changed' => 0, 'removed' => []];
        if ($base === null) return $out;
        $key = fn(array $b) => json_encode([$b['type'] ?? '', $b['data'] ?? [], $b['tunes'] ?? []], JSON_UNESCAPED_UNICODE);
        $old = [];
        foreach ($base as $b) if (isset($b['id'])) $old[(string) $b['id']] = $b;
        $seen = [];
        foreach ($blocks as $b) {
            $id = (string) ($b['id'] ?? '');
            if ($id === '') continue;
            $seen[$id] = true;
            if (!isset($old[$id])) { $out['marks'][$id] = 'new'; $out['new']++; }
            elseif ($key($old[$id]) !== $key($b)) { $out['marks'][$id] = 'changed'; $out['changed']++; }
        }
        foreach ($old as $id => $b) {
            if (isset($seen[$id])) continue;
            $out['removed'][] = (string) (app()->theme->block((string) ($b['type'] ?? ''))['label'] ?? ($b['type'] ?? '?'));
        }
        return $out;
    }

    /** Stände einer Seite, neueste zuerst: ['id' (0 = jetzt), 'json', 'created_at', 'note', 'email'] – gleiche Inhalte nur einmal */
    private static function pageStates(array $page): array
    {
        $strip = fn(?string $json) => json_encode(self::blocksOf($json));
        $now = (string) ($page['content_draft'] ?? $page['content_published']);
        $out = [['id' => 0, 'json' => $now, 'created_at' => $page['updated_at'] ?? null, 'note' => '', 'email' => '']];
        $seen = [$strip($now) => true];
        foreach (app()->db->fetchAll('SELECT r.id, r.created_at, r.note, r.blocks_json, u.email FROM revisions r LEFT JOIN users u ON u.id = r.user_id WHERE r.page_id = ? ORDER BY r.id DESC', [(int) $page['id']]) as $rv) {
            $k = $strip($rv['blocks_json']);
            if (isset($seen[$k])) continue;   // gleicher Inhalt wie ein neuerer Stand
            $seen[$k] = true;
            $out[] = ['id' => (int) $rv['id'], 'json' => (string) $rv['blocks_json'], 'created_at' => $rv['created_at'], 'note' => (string) ($rv['note'] ?? ''), 'email' => (string) ($rv['email'] ?? '')];
        }
        return $out;
    }

    public function pageVersions(Request $r, string $id): Response
    {
        $page = $this->page($r, $id);
        $f = Format::admin();
        $states = self::pageStates($page);
        $out = [];
        foreach ($states as $i => $st) {
            $older = $states[$i + 1] ?? null;
            $d = self::blockDiff(self::blocksOf($st['json']), $older ? self::blocksOf($older['json']) : null);
            $base = '/admin/pages/' . (int) $page['id'] . '/versions/' . $st['id'] . '/vorschau';
            $out[] = ['id' => $st['id'], 'now' => $st['id'] === 0, 'label' => $st['id'] === 0 ? __('Jetzt') : $f->relative($st['created_at']), ...self::when($st['created_at']),
                'note' => $st['id'] === 0
                    ? ($page['status'] === 'published' && ($page['content_draft'] ?? null) !== null && $page['content_draft'] !== $page['content_published'] ? __('Entwurf mit unveröffentlichten Änderungen') : __('Aktueller Stand'))
                    : ($st['note'] ?: __('Gespeichert')),
                'user' => $st['email'],
                'preview' => url($base), 'preview_mark' => url($base) . '?mark=1',
                'diff' => ['new' => $d['new'], 'changed' => $d['changed'], 'removed' => $d['removed'], 'first' => $older === null]];
        }
        $live = $page['content_published'] !== null ? url('/admin/pages/' . (int) $page['id'] . '/vorschau') . '?stand=live' : url('/admin/pages/' . (int) $page['id'] . '/versions/0/vorschau');
        return Response::json(['ok' => true, 'kind' => 'page', 'title' => (string) $page['title'], 'versions' => $out, 'live' => $live, 'has_live' => $page['content_published'] !== null, 'texts' => self::texts(), 'today' => Format::admin()->date(now(), 'long'),
            'restore' => url('/admin/pages/' . (int) $page['id'] . '/restore/{rev}'), 'can_restore' => can('pages.edit')]);
    }

    /** HTML der Seite in einem Stand (rev 0 = jetzt); ?mark=1 markiert neue/geänderte Blöcke gegenüber dem vorherigen Stand */
    public function pagePreview(Request $r, string $id, string $rev): Response
    {
        $page = $this->page($r, $id);
        if (($page['type'] ?? 'page') === 'template' && !\Core\PageTemplates::isTemplatePage($page) && !NotFound::isPage($page)) throw new HttpException(404);
        $states = self::pageStates($page);
        $at = null;
        foreach ($states as $i => $st) if ($st['id'] === (int) $rev) { $at = $i; break; }
        if ($at === null) {
            // Stand ist nicht in der Liste (gleicher Inhalt wie ein neuerer) – trotzdem direkt aus der Tabelle zeigen
            $json = app()->db->fetchValue('SELECT blocks_json FROM revisions WHERE id = ? AND page_id = ?', [(int) $rev, (int) $page['id']]) ?? throw new HttpException(404);
            $states = [['id' => (int) $rev, 'json' => (string) $json]];
            $at = 0;
        }
        \Core\Theme::$vdiff = $r->str('mark') === '1' && isset($states[$at + 1])
            ? self::blockDiff(self::blocksOf($states[$at]['json']), self::blocksOf($states[$at + 1]['json']))['marks'] : [];
        $site = new \Core\Http\Controllers\SiteController();
        try {
            $html = $site->previewHtml(['content_draft' => $states[$at]['json']] + $page, true);
        } finally {
            \Core\Theme::$vdiff = [];
        }
        return $site->respond($html, true)->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store, private');
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
        // Stände, neueste zuerst; gleiche Inhalte nur einmal
        $now = Revisions::snapshot($t, $e);
        $key = fn(array $d) => json_encode($d, JSON_UNESCAPED_UNICODE);
        $states = [['id' => 0, 'data' => $now, 'created_at' => $e['updated_at'] ?? null, 'note' => __('Aktueller Stand'), 'user_email' => '', 'status' => (string) ($e['status'] ?? '')]];
        $seen = [$key($now) => true];
        foreach (Revisions::list($t, (int) $e['id']) as $rv) {
            if (isset($seen[$key($rv['data'])])) continue;
            $seen[$key($rv['data'])] = true;
            $states[] = $rv;
        }
        // Felder eines Stands; changed = anders als im vorherigen (älteren) Stand
        $fields = function (array $data, ?array $base) use ($t, $e): array {
            $row = ['id' => (int) $e['id']] + $data + $e;
            $out = [];
            foreach ($t['fields'] as $fd) {
                $n = $fd['name'];
                $v = $data[$n] ?? null;
                $empty = $v === null || $v === '' || $v === [];
                $changed = $base !== null && json_encode($v) !== json_encode($base[$n] ?? null);
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
        $out = [];
        foreach ($states as $i => $st) {
            $base = $states[$i + 1]['data'] ?? null;
            $fl = $fields($st['data'], $base);
            $out[] = ['id' => (int) $st['id'], 'now' => (int) $st['id'] === 0, 'label' => (int) $st['id'] === 0 ? __('Jetzt') : $f->relative($st['created_at']), ...self::when($st['created_at']),
                'note' => (string) ($st['note'] ?? '') ?: __('Gespeichert'), 'user' => (string) ($st['user_email'] ?? ''), 'status' => (string) ($st['status'] ?? ''),
                'title' => Entries::title($t, ['id' => (int) $e['id']] + $st['data'] + $e), 'fields' => $fl,
                'changes' => array_values(array_map(fn($x) => $x['label'], array_filter($fl, fn($x) => $x['changed']))),
                'diff' => ['first' => $base === null]];
        }
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
