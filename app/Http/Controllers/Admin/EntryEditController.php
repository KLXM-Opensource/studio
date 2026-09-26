<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Data\Entries;
use Core\Data\EntryEdit;
use Core\Data\Shared;
use Core\Data\Tables;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Theme;

/**
 * Einträge auf der Website bearbeiten (JSON für resources/js/_entry_edit.js):
 *  GET  /admin/api/entries/{table}/{id|new}  → Formular für die Seitenleiste (gleiche Feld-Widgets wie in der Verwaltung)
 *  POST /admin/api/entries/{table}/{id|new}  → speichern (Formular der Seitenleiste oder einzelne Felder aus dem Text)
 * Gleiche Regeln wie DataController::entrySave: Recht data.edit je Tabelle, ohne data.publish nur Entwürfe,
 * fremde Einträge geteilter Tabellen nur lesen, Eingangs-Tabellen nie.
 */
final class EntryEditController extends AdminController
{
    private function table(Request $r, string $handle): array
    {
        $this->auth($r);
        $t = Tables::find($handle) ?? throw new HttpException(404);
        if (Tables::isInbox($t) || !can('data.edit', $handle)) {
            throw new HttpException(403, __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.'));
        }
        return $t;
    }

    public function form(Request $r, string $handle, string $id): Response
    {
        $t = $this->table($r, $handle);
        $e = $id === 'new' ? null : (Entries::find($t, (int) $id) ?? throw new HttpException(404));
        return Response::json($this->payload($t, $e, $e ?? [], []))->header('Cache-Control', 'no-store, private');
    }

    public function save(Request $r, string $handle, string $id): Response
    {
        $t = $this->table($r, $handle);
        $e = $id === 'new' ? null : (Entries::find($t, (int) $id) ?? throw new HttpException(404));
        if ($e && ($why = EntryEdit::reason($t, $e)) !== null) {
            return Response::json(['ok' => false, 'error' => $why], 403);
        }
        $in = (array) ($r->post['f'] ?? []);
        $partial = !empty($r->post['partial']);                 // einzelne Felder aus dem Text: übrige behalten ihren Wert
        if ($partial && $e) {
            $in = array_intersect_key($in, array_flip(array_column($t['fields'], 'name')));
        }
        if (array_key_exists('slug', $r->post)) $in['slug'] = $r->str('slug');
        $status = $r->str('status');
        if (in_array($status, ['draft', 'published'], true)) {
            if ($status === 'published' && !can('data.publish', $handle)) {
                return Response::json(['ok' => false, 'error' => __('Veröffentlichen ist Ihrer Rolle nicht erlaubt.')], 403);
            }
            $in['status'] = $status;
        } elseif ($e) {
            $in['status'] = $e['status'];
        } else {
            $in['status'] = 'draft';
        }
        if (!can('data.publish', $handle)) $in['status'] = 'draft';
        if (!$e && $r->str('lang') !== '') $in['lang'] = $r->str('lang');
        [$newId, $errors] = Entries::save($t, $e ? (int) $e['id'] : null, $in);
        if ($errors) {
            $values = $in + ($e ?? []);
            return Response::json(['ok' => false, 'error' => __('Bitte prüfen Sie die markierten Felder – es wurde nichts gespeichert.'),
                'errors' => $errors, 'labels' => array_column($t['fields'], 'label', 'name')]
                // 200 statt 422: Prüfhinweise sind kein Fehler der Anfrage (keine Meldung in der Browser-Konsole)
                + ($partial ? [] : $this->payload($t, $e, $values, $errors)));
        }
        $this->changed();
        $saved = Entries::find($t, (int) $newId);
        return Response::json(['ok' => true, 'id' => (int) $newId, 'status' => $saved['status'] ?? '', 'url' => $saved ? Entries::url($t, $saved) : null,
            'title' => $saved ? Entries::title($t, $saved) : '', 'saved_at' => date('H:i')]);
    }

    /** Formular + Kopfdaten für die Seitenleiste */
    private function payload(array $t, ?array $e, array $values, array $errors): array
    {
        $foreign = $e && Shared::isForeign($t, $e);
        $html = Theme::capture(ROOT . '/app/Views/entry-panel.php', [
            't' => $t, 'e' => $e, 'values' => $values, 'errors' => $errors,
            'reason' => $e ? EntryEdit::reason($t, $e) : null, 'foreign' => $foreign,
        ]);
        return [
            'ok' => true, 'html' => $html,
            'title' => $e ? Entries::title($t, $e) : __('Neu: {singular}', ['singular' => $t['singular'] ?: $t['name']]),
            'table' => $t['name'], 'icon' => $t['icon'], 'ico' => \Core\Icons::resolve((string) $t['icon']), 'status' => $e['status'] ?? 'draft',
            'admin' => EntryEdit::adminUrl($t, $e),
            // Seitenleiste im Shadow DOM: Verwaltungs-CSS (:host-Variante) + Rahmen; Dialoge (Mediathek, Link) in der
            // Shadow-DOM-Ebene (resources/js/_shadow.js) – admin.css wird nicht mehr ins Dokument der Website geladen
            'shadowCss' => [asset('css/admin.shadow.css'), asset('css/entry-panel.css')],
            'uiCss' => ['admin' => asset('css/admin.shadow.css'), 'ui' => asset('css/editor.shadow.css')],
            'appearance' => in_array(app()->auth->user()['appearance'] ?? '', ['light', 'dark'], true) ? app()->auth->user()['appearance'] : '',
            'accent' => \Core\Accent::client(),   // persönliche Akzentfarbe am Schatten-Host (data-accent, data-side)
            'links' => url('/admin/api/links'),
            'csrf' => \Core\Csrf::token(), 'texts' => EntryEdit::texts(),
            'conditions' => \Core\Data\Rules::client($t['fields']) ?: null,
        ];
    }
}
