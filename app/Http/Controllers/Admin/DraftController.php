<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Review\Drafts;
use Core\Review\Queue;

/**
 * Verwaltung → Entwürfe (Core\Review\Drafts): alle offenen Entwürfe der Website mit Unterschieden, Notiz/Zuständigkeit,
 * Veröffentlichen und Verwerfen (einzeln und gesammelt). Rechte wie in Seiten- und Datenverwaltung:
 * pages.edit / pages.publish bzw. data.edit / data.publish / data.delete je Tabelle.
 */
final class DraftController extends AdminController
{
    private function guard(Request $r): array
    {
        $u = $this->auth($r);
        if (!Drafts::canView()) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        return $u;
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        $f = in_array($r->str('filter'), Drafts::FILTERS, true) ? $r->str('filter') : 'all';
        $all = Drafts::items();
        $items = Drafts::filter($all, $f, $counts);
        return $this->view('drafts/index', ['f' => $f, 'items' => $items, 'counts' => $counts, 'users' => Drafts::userNames(),
            'discards' => Drafts::discards(), 'submitted' => Queue::canReview() ? Queue::pendingCount() : 0, 'title' => __('Entwürfe')]);
    }

    /** Unterschiede Entwurf ↔ veröffentlicht: /admin/entwuerfe/seite/{id} bzw. /admin/entwuerfe/eintrag/{table}/{id} */
    public function page(Request $r, string $id): Response
    {
        return $this->show($r, 'page:' . (int) $id);
    }

    public function entry(Request $r, string $table, string $id): Response
    {
        return $this->show($r, 'entry:' . $table . ':' . (int) $id);
    }

    private function show(Request $r, string $key): Response
    {
        $this->guard($r);
        $i = Drafts::find($key) ?? throw new HttpException(404, __('Kein offener Entwurf (schon veröffentlicht oder verworfen).'));
        return $this->view('drafts/show', ['i' => $i, 'diff' => Drafts::diff($i), 'users' => Drafts::userNames(), 'title' => __('Entwurf') . ': ' . $i['title']]);
    }

    /** Veröffentlichen bzw. Verwerfen: ein Entwurf („one“) oder die Auswahl („items[]“) */
    public function action(Request $r, string $op): Response
    {
        $u = $this->guard($r);
        if (!in_array($op, ['veroeffentlichen', 'verwerfen'], true)) throw new HttpException(404);
        $back = $this->backUrl($r);
        $keys = $r->str('one') !== '' ? [$r->str('one')] : array_values(array_unique(array_filter(array_map('strval', (array) ($r->post['items'] ?? [])))));
        if (!$keys) return $this->back($back, 'error', __('Bitte zuerst Entwürfe auswählen.'));
        $byKey = array_column(Drafts::items(), null, 'key');
        $ok = 0;
        $fail = [];
        foreach (array_slice($keys, 0, 200) as $k) {
            $i = $byKey[$k] ?? null;
            if (!$i) { $fail[] = __('Entwurf nicht mehr vorhanden'); continue; }
            $err = $op === 'veroeffentlichen' ? Drafts::publish($i, (int) $u['id']) : Drafts::discard($i, (int) $u['id']);
            if ($err === null) $ok++; else $fail[] = $err;
        }
        if ($ok) $this->changed();
        $msg = $op === 'veroeffentlichen'
            ? ($ok === 1 ? __('1 Entwurf veröffentlicht.') : __('{n} Entwürfe veröffentlicht.', ['n' => $ok]))
            : ($ok === 1 ? __('1 Entwurf verworfen.') : __('{n} Entwürfe verworfen.', ['n' => $ok])) . ($ok ? ' ' . __('Seiten-Entwürfe bleiben unter „Versionen“ gesichert, Einträge unter „Zuletzt verworfen“.') : '');
        if ($fail) {
            $fail = array_values(array_unique($fail));
            $msg = ($ok ? $msg . ' ' : '') . __('Nicht möglich: {list}', ['list' => implode(' · ', array_slice($fail, 0, 5)) . (count($fail) > 5 ? ' …' : '')]);
        }
        // Nach Einzelaktion auf der Detailseite: zurück zur Liste (der Entwurf ist weg)
        if ($ok && str_starts_with($back, '/admin/entwuerfe/')) $back = '/admin/entwuerfe';
        return $this->back($back, $fail ? 'error' : 'success', $msg);
    }

    /** Notiz und zuständige Person speichern */
    public function note(Request $r): Response
    {
        $u = $this->guard($r);
        $key = $r->str('item');
        $back = $this->backUrl($r);
        if (!Drafts::find($key)) return $this->back($back, 'error', __('Entwurf nicht mehr vorhanden.'));
        $a = $r->str('assignee');
        Drafts::setNote($key, (string) ($r->post['note'] ?? ''), ctype_digit($a) ? (int) $a : null, (int) $u['id']);
        return $this->back($back . '#' . self::anchor($key), 'success', __('Notiz gespeichert.'));
    }

    /** Verworfenen Eintrag als Entwurf wiederherstellen */
    public function restore(Request $r, string $id): Response
    {
        $this->guard($r);
        [$newId, $err] = Drafts::restore((int) $id);
        if ($err !== null) return $this->back('/admin/entwuerfe', 'error', $err);
        $this->changed();
        return $this->back('/admin/entwuerfe', 'success', __('Eintrag als Entwurf wiederhergestellt.'));
    }

    /** Anker einer Zeile (für den Rücksprung nach dem Speichern) */
    public static function anchor(string $key): string
    {
        return 'dr-' . preg_replace('~[^a-z0-9_-]+~i', '-', $key);
    }

    /** Rücksprung: nur Adressen der Entwürfe-Übersicht (mit Filter) */
    private function backUrl(Request $r): string
    {
        $b = $r->str('back');
        return preg_match('~^/admin/entwuerfe(/(seite/\d+|eintrag/[a-z0-9_]+/\d+))?(\?filter=[a-z]+)?$~', $b) ? $b : '/admin/entwuerfe';
    }
}
