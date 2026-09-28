<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Data\Entries;
use Core\Data\SchemaPanel;
use Core\Data\Shared;
use Core\Data\Tables;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Theme;

/**
 * „Felder bearbeiten“ im Seiten-Editor (JSON für resources/js/_form_fields.js):
 *  GET  /admin/api/formfields/{table}  → Seitenleiste mit den Feldern und Formular-Einstellungen der Tabelle
 *  POST /admin/api/formfields/{table}  → speichern (gleicher Weg wie der Tabellen-Designer: DataController::saveSchema)
 * Recht „data.schema“; geteilte Tabellen nur auf der Eigentümer-Website (Shared::canSchema). Logik: Core\Data\SchemaPanel.
 */
final class FormFieldsController extends AdminController
{
    private function table(Request $r, string $handle): array
    {
        $this->auth($r, 'data.schema');
        $t = Tables::find($handle) ?? throw new HttpException(404, __('Diese Tabelle gibt es nicht (mehr).'));
        if (!Shared::canSchema($t)) {
            throw new HttpException(403, __('Die Felder dieser geteilten Tabelle legt die Website „{site}“ fest.', ['site' => Shared::siteInfo($t['shared']['owner'], $t['shared']['key'])['name']]));
        }
        return $t;
    }

    public function form(Request $r, string $handle): Response
    {
        try {
            $t = $this->table($r, $handle);
        } catch (HttpException $e) {
            return $this->fail($e);
        }
        $in = Tables::toInput($t);
        return $this->payload($t, $in['fields'], $in['settings']['form'] ?? [], [], false);
    }

    public function save(Request $r, string $handle): Response
    {
        try {
            $t = $this->table($r, $handle);
        } catch (HttpException $e) {
            return $this->fail($e);
        }
        $res = SchemaPanel::save($t, $r->post);
        if (!$res['ok']) {
            // 200 statt 422: Prüfhinweise sind kein Fehler der Anfrage (wie EntryEditController)
            return $this->payload($t, $res['input']['fields'], (array) ($res['input']['settings']['form'] ?? []), $res['errors'], $res['askDrop']);
        }
        $this->changed();
        $t = Tables::find($handle);
        $in = Tables::toInput($t);
        return $this->payload($t, $in['fields'], $in['settings']['form'] ?? [], [], false, true);
    }

    private function fail(HttpException $e): Response
    {
        $code = $e->getCode() ?: 403;
        return Response::json(['ok' => false, 'error' => $e->getMessage() ?: __('Keine Berechtigung.')], $code)->header('Cache-Control', 'no-store, private');
    }

    /** Seitenleiste: Felder (Eingabeformat von Tables::validate), Formular-Einstellungen, Fehler */
    private function payload(array $t, array $fields, array $form, array $errors, bool $askDrop, bool $saved = false): Response
    {
        $inbox = Tables::isInbox($t);
        $count = $inbox ? 0 : (int) Tables::db($t)->fetchValue("SELECT COUNT(*) FROM {$t['table']}");
        $html = Theme::capture(ROOT . '/app/Views/formfields-panel.php', [
            't' => $t, 'fields' => $fields, 'form' => (array) $form + \Core\Data\DataForms::DEFAULTS, 'errors' => $errors, 'askDrop' => $askDrop,
            'count' => $count, 'saved' => $saved,
        ]);
        return Response::json([
            'ok' => !$errors, 'saved' => $saved, 'html' => $html,
            'error' => $errors ? __('Bitte prüfen Sie die markierten Angaben – es wurde nichts gespeichert.') : null,
            'title' => __('Felder bearbeiten'), 'table' => $t['name'], 'handle' => $t['handle'], 'icon' => $t['icon'], 'ico' => \Core\Icons::resolve((string) $t['icon']),
            'saved_at' => date('H:i'),
            'shadowCss' => [asset('css/admin.shadow.css'), asset('css/entry-panel.css')],
            'uiCss' => ['admin' => asset('css/admin.shadow.css'), 'ui' => asset('css/editor.shadow.css')],
            'appearance' => in_array(app()->auth->user()['appearance'] ?? '', ['light', 'dark'], true) ? app()->auth->user()['appearance'] : '',
            'accent' => \Core\Accent::client(),
            'csrf' => \Core\Csrf::token(),
            'texts' => [
                'close' => __('Schließen'), 'cancel' => __('Abbrechen'), 'saving' => __('Speichere …'),
                'saved' => __('Gespeichert {time} – das Formular auf der Seite ist aktualisiert.'), 'error' => __('Fehler beim Speichern'),
                'failed' => __('Laden fehlgeschlagen: {error}'), 'session' => __('Ihre Sitzung ist abgelaufen. Bitte in einem neuen Tab anmelden und erneut speichern.'),
                'discardTitle' => __('Änderungen verwerfen?'), 'discardBody' => __('Die Felder enthalten Änderungen, die noch nicht gespeichert sind. „Verwerfen“ schließt die Seitenleiste ohne zu speichern.'),
                'keep' => __('Weiter bearbeiten'), 'discardBtn' => __('Verwerfen'), 'saveExit' => __('Speichern & schließen'),
                'moved' => __('„{label}“ ist jetzt an Position {n} von {total}.'), 'removed' => __('Feld „{label}“ entfernt – wird beim Speichern gelöscht.'),
                'added' => __('Feld „{label}“ hinzugefügt.'), 'noname' => __('ohne Namen'),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }
}
