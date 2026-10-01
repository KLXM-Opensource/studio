<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\AI\Assist;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Pages;
use Core\Review\Queue;
use Core\Review\Snapshot;

/**
 * Prüf-Ebene „Eingereicht“ (Bereich KLXM AI): Änderungen über REST-API, MCP und KI ansehen, übernehmen, bearbeiten, ablehnen.
 * Recht „review.manage“ (Standard: Administration), Funktion „review“.
 */
final class ReviewController extends AdminController
{
    private function guard(Request $r): array
    {
        $u = $this->auth($r, 'review.manage');
        if (!Queue::enabled()) throw new HttpException(404);
        return $u;
    }

    /** Im KI-Bereich mit dessen Navigation (Drill-down), sonst mit der Hauptnavigation */
    protected function view(string $view, array $vars = [], int $status = 200): Response
    {
        if (Assist::navVisible()) {
            $vars['drill'] ??= \Core\Theme::capture(ROOT . '/app/Admin/views/ai/_nav.php', ['cur' => 'review']);
            $vars['drillTitle'] ??= Assist::brand();
        }
        return parent::view($view, $vars, $status);
    }

    private static function filters(Request $r): array
    {
        $status = (string) ($r->query['status'] ?? 'pending');
        return [
            'status' => in_array($status, [...Queue::STATUSES, 'all'], true) ? $status : 'pending',
            'channel' => in_array($r->query['channel'] ?? '', Queue::CHANNELS, true) ? (string) $r->query['channel'] : '',
            'token' => ctype_digit((string) ($r->query['token'] ?? '')) ? (string) $r->query['token'] : '',
            'type' => in_array($r->query['type'] ?? '', Snapshot::TYPES, true) ? (string) $r->query['type'] : '',
            'q' => mb_substr(trim((string) ($r->query['q'] ?? '')), 0, 100),
            'page' => max(1, (int) ($r->query['page'] ?? 1)),
        ];
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        $f = self::filters($r);
        return $this->view('review/index', ['f' => $f, 'list' => Queue::list($f), 'counts' => Queue::counts($f), 'sources' => Queue::sources(),
            'title' => __('Eingereicht')]);
    }

    public function show(Request $r, string $id): Response
    {
        $this->guard($r);
        $row = Queue::find((int) $id) ?? throw new HttpException(404);
        return $this->view('review/show', ['row' => Queue::decode($row), 'conflict' => Queue::conflict($row),
            'edit' => isset($r->query['bearbeiten']) && $row['status'] === 'pending', 'title' => __('Eingereicht') . ' #' . (int) $id]);
    }

    /** Übernehmen, Ablehnen, Bearbeiten & übernehmen, Als Entwurf übernehmen, Neu prüfen */
    public function action(Request $r, string $id, string $action): Response
    {
        $u = $this->guard($r);
        $id = (int) $id;
        $back = '/admin/ai/eingereicht/' . $id;
        switch ($action) {
            case 'uebernehmen':
            case 'bearbeiten':
            case 'entwurf':
                $values = $action === 'bearbeiten' ? array_map('strval', array_filter((array) ($r->post['values'] ?? []), 'is_scalar')) : [];
                $res = Queue::approve($id, (int) $u['id'], $values, $action === 'entwurf');
                if (!$res['ok']) return $this->back($back, 'error', $res['error'] ?? __('Übernehmen fehlgeschlagen.'));
                $this->changed();
                $t = $res['target'] ?? [];
                if ($action === 'entwurf' && ($t['type'] ?? '') === 'page' && ($p = Pages::find((int) $t['id']))) {
                    app()->session->flash('success', __('Als Entwurf übernommen – jetzt im Editor prüfen und veröffentlichen.'));
                    return \Core\Http\Response::redirect(url(Pages::url($p) . '?edit=1'));
                }
                return $this->back($this->next($id), 'success', __('Änderung #{id} übernommen.', ['id' => $id]));
            case 'ablehnen':
                if (!Queue::reject($id, (int) $u['id'], (string) ($r->post['reason'] ?? ''))) return $this->back($back, 'error', __('Diese Einreichung ist nicht mehr offen.'));
                return $this->back($this->next($id), 'success', __('Änderung #{id} abgelehnt.', ['id' => $id]));
            case 'pruefen':
                $err = Queue::recheck($id);
                return $this->back($back, $err ? 'error' : 'success', $err ? __('Neu prüfen: {error}', ['error' => $err]) : __('Gegen den aktuellen Stand neu geprüft – bitte die Änderungen ansehen.'));
        }
        throw new HttpException(404);
    }

    /** Nach einer Entscheidung: nächste offene Einreichung, sonst die Liste */
    private function next(int $after): string
    {
        $n = app()->db->fetchValue("SELECT id FROM change_log WHERE status = 'pending' AND id != ? ORDER BY id DESC LIMIT 1", [$after]);
        return $n ? '/admin/ai/eingereicht/' . (int) $n : '/admin/ai/eingereicht';
    }

    /** Sammelaktion: ausgewählte übernehmen oder ablehnen */
    public function bulk(Request $r): Response
    {
        $u = $this->guard($r);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($r->post['ids'] ?? [])))));
        $op = $r->str('op');
        if (!$ids || !in_array($op, ['approve', 'reject'], true)) return $this->back('/admin/ai/eingereicht', 'error', __('Bitte zuerst Einreichungen auswählen.'));
        $ok = 0;
        $fail = [];
        foreach (array_slice($ids, 0, 100) as $id) {
            if ($op === 'reject') {
                if (Queue::reject($id, (int) $u['id'], $r->str('reason'))) $ok++; else $fail[] = '#' . $id;
                continue;
            }
            $res = Queue::approve($id, (int) $u['id']);
            if ($res['ok']) $ok++; else $fail[] = '#' . $id . (!empty($res['conflict']) ? ' (' . __('Konflikt') . ')' : '');
        }
        if ($op === 'approve' && $ok) $this->changed();
        $msg = ($op === 'approve' ? __('{n} übernommen.', ['n' => $ok]) : __('{n} abgelehnt.', ['n' => $ok]))
            . ($fail ? ' ' . __('Nicht möglich: {list} – bitte einzeln prüfen.', ['list' => implode(', ', $fail)]) : '');
        return $this->back('/admin/ai/eingereicht', $fail ? 'error' : 'success', $msg);
    }

    /** Einstellungen der Website: E-Mail bei neuen Einreichungen, KI-Übernahmen ebenfalls zur Freigabe */
    public function settings(Request $r): Response
    {
        $this->guard($r);
        app()->settings->set('sys.review_notify', !empty($r->post['notify']));
        app()->settings->set('sys.review_ai', !empty($r->post['ai']));
        return $this->back('/admin/ai/eingereicht#rv-settings', 'success', __('Einstellungen gespeichert.'));
    }

    /** Vorschau einer Seite mit den vorgeschlagenen Blöcken (nichts wird gespeichert) */
    public function preview(Request $r, string $id): Response
    {
        $this->guard($r);
        $row = Queue::find((int) $id) ?? throw new HttpException(404);
        $d = Queue::decode($row);
        if (($d['target']['type'] ?? '') !== 'page' || !is_array($d['after']['blocks'] ?? null)) throw new HttpException(404);
        $page = Pages::find((int) ($d['target']['id'] ?? 0)) ?? ['id' => 0, 'slug' => 'vorschau', 'path' => 'vorschau', 'parent_id' => null, 'is_home' => 0,
            'meta_description' => '', 'noindex' => 1, 'status' => 'draft', 'type' => 'page', 'lang' => null, 'og_image' => null, 'menu' => 0, 'nav_title' => '',
            'updated_at' => now(), 'published_at' => null, 'content_draft' => null, 'meta_title' => null, 'translation_group' => null, 'template_for' => null, 'sort' => 0];
        $page['title'] = (string) ($d['after']['title'] ?? $page['title'] ?? '');
        $page['content_published'] = json_encode(['blocks' => Snapshot::importBlocks($d['after']['blocks'])], JSON_UNESCAPED_UNICODE);
        $site = new \Core\Http\Controllers\SiteController();
        return $site->respond($site->previewHtml($page), true)->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
