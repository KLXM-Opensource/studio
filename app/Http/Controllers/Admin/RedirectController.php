<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Features;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Redirects\Redirects;

/** Administration → Weiterleitungen: Liste, Bearbeiten, Adresse testen, Import/Export, 404-Protokoll (Core\Redirects). */
final class RedirectController extends AdminController
{
    private function gate(Request $r): array
    {
        $user = $this->auth($r, 'redirects.manage');
        if (!Features::on('redirects')) throw new HttpException(404);
        return $user;
    }

    public function index(Request $r): Response
    {
        $this->gate($r);
        $f = ['q' => trim((string) ($r->query['q'] ?? '')), 'code' => (string) ($r->query['code'] ?? ''), 'origin' => (string) ($r->query['origin'] ?? ''),
            'sort' => (string) ($r->query['sort'] ?? ''), 'page' => (int) ($r->query['page'] ?? 1)];
        $test = trim((string) ($r->query['test'] ?? ''));
        $import = app()->session->get('_redirects_import');   // Ergebnis des letzten Imports einmal anzeigen
        app()->session->forget('_redirects_import');
        return $this->view('redirects/index', ['f' => $f, 'list' => Redirects::list($f), 'test' => $test, 'result' => $test !== '' ? Redirects::explain($test) : null,
            'count404' => Redirects::notFoundCount(), 'import' => $import,
            'auto' => (bool) app()->settings->get(Redirects::SET_AUTO, true), 'log' => Redirects::logEnabled()]);
    }

    public function edit(Request $r, string $id = '', array $errors = [], ?array $values = null): Response
    {
        $this->gate($r);
        $row = $id !== '' ? (Redirects::find((int) $id) ?? throw new HttpException(404)) : null;
        if ($values === null) {
            $values = $row ?? ['source' => Redirects::normalize((string) ($r->query['source'] ?? '')), 'target' => (string) ($r->query['target'] ?? ''),
                'code' => 301, 'note' => '', 'active' => 1];
            if (!$row && ($values['source'] ?? '') !== '' && $values['target'] === '' && ($s = Redirects::suggest($values['source']))) {
                $values['target'] = $s['target'];   // Vorschlag aus dem 404-Protokoll: Seite mit gleichem Adressteil
                $values['suggested'] = true;
            }
        }
        $from404 = ($r->query['from'] ?? '') === '404' || ($r->post['back'] ?? '') === '404';
        return $this->view('redirects/edit', ['row' => $row, 'values' => $values, 'errors' => $errors, 'from404' => $from404], $errors ? 422 : 200);
    }

    public function save(Request $r, string $id = ''): Response
    {
        $this->gate($r);
        $row = $id !== '' ? (Redirects::find((int) $id) ?? throw new HttpException(404)) : null;
        $in = (array) ($r->post['f'] ?? []);
        $in['active'] = !empty($in['active']);
        [$newId, $errors, $notes] = Redirects::save($in, $row ? (int) $row['id'] : null);
        if ($errors) {
            app()->session->flash('error', __('Bitte prüfen Sie die markierten Felder – es wurde nichts gespeichert.'));
            return $this->edit($r, $id, $errors, $in + ['source' => '', 'target' => '', 'code' => 301, 'note' => '']);
        }
        foreach ($notes as $n) app()->session->flash('info', $n);
        // Aus dem 404-Protokoll angelegt → zurück dorthin
        $back = ($r->post['back'] ?? '') === '404' ? '/admin/weiterleitungen/404' : '/admin/weiterleitungen';
        return $this->back($back, 'success', $row ? __('Weiterleitung gespeichert.') : __('Weiterleitung angelegt.'));
    }

    public function delete(Request $r, string $id): Response
    {
        $this->gate($r);
        $row = Redirects::find((int) $id) ?? throw new HttpException(404);
        Redirects::delete([(int) $row['id']]);
        return $this->back('/admin/weiterleitungen', 'success', __('Weiterleitung von „{source}“ gelöscht.', ['source' => $row['source']]));
    }

    /** Sammelaktion: löschen, aktivieren, deaktivieren */
    public function bulk(Request $r): Response
    {
        $this->gate($r);
        $ids = array_map('intval', (array) ($r->post['ids'] ?? []));
        if (!$ids) return $this->back('/admin/weiterleitungen', 'error', __('Bitte zuerst Weiterleitungen auswählen.'));
        $op = (string) ($r->post['op'] ?? '');
        $n = match ($op) {
            'delete' => Redirects::delete($ids),
            'on' => Redirects::setActive($ids, true),
            'off' => Redirects::setActive($ids, false),
            default => 0,
        };
        $msg = match ($op) {
            'delete' => __('{n} Weiterleitung(en) gelöscht.', ['n' => $n]),
            'on' => __('{n} Weiterleitung(en) aktiviert.', ['n' => $n]),
            default => __('{n} Weiterleitung(en) deaktiviert.', ['n' => $n]),
        };
        return $this->back('/admin/weiterleitungen', 'success', $msg);
    }

    /** Import: Datei (CSV/JSON) oder eingefügter Text; „Nur prüfen“ speichert nichts */
    public function import(Request $r): Response
    {
        $this->gate($r);
        $text = trim((string) ($r->post['text'] ?? ''));
        $name = '';
        $file = $r->files['file'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string) $file['tmp_name'])) {
            if ((int) $file['size'] > 5 * 1024 * 1024) return $this->back('/admin/weiterleitungen#import', 'error', __('Die Datei ist zu groß (höchstens 5 MB).'));
            $text = (string) file_get_contents((string) $file['tmp_name']);
            $name = (string) $file['name'];
        }
        if (trim($text) === '') return $this->back('/admin/weiterleitungen#import', 'error', __('Bitte eine CSV- oder JSON-Datei wählen oder Zeilen einfügen.'));
        try {
            $rows = Redirects::parse($text, $name);
        } catch (\RuntimeException $e) {
            return $this->back('/admin/weiterleitungen#import', 'error', $e->getMessage());
        }
        $dry = !empty($r->post['dry']);
        $res = Redirects::import($rows, ['dry' => $dry, 'overwrite' => !empty($r->post['overwrite']), 'link' => !empty($r->post['link'])]);
        $res['dry'] = $dry;
        $res['errors'] = array_slice($res['errors'], 0, 30, true);
        app()->session->set('_redirects_import', $res);
        $msg = $dry
            ? __('Probelauf: {c} neu, {u} geändert, {s} übersprungen – nichts gespeichert.', ['c' => $res['created'], 'u' => $res['updated'], 's' => $res['skipped']])
            : __('Import: {c} neu, {u} geändert, {s} übersprungen.', ['c' => $res['created'], 'u' => $res['updated'], 's' => $res['skipped']]);
        return $this->back('/admin/weiterleitungen#import', $res['errors'] && !$res['created'] && !$res['updated'] ? 'error' : 'success', $msg);
    }

    public function export(Request $r): Response
    {
        $this->gate($r);
        $json = ($r->query['format'] ?? 'csv') === 'json';
        $file = 'weiterleitungen-' . site()->key . '-' . date('Y-m-d') . ($json ? '.json' : '.csv');
        return self::secure(new Response($json ? Redirects::exportJson() : Redirects::exportCsv(), 200, [
            'Content-Type' => $json ? 'application/json; charset=utf-8' : 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
        ]));
    }

    public function settings(Request $r): Response
    {
        $this->gate($r);
        app()->settings->set(Redirects::SET_AUTO, !empty($r->post['auto']));
        app()->settings->set(Redirects::SET_LOG, !empty($r->post['log']));
        return $this->back('/admin/weiterleitungen#einstellungen', 'success', __('Einstellungen gespeichert.'));
    }

    // ------------------------------------------------------------------ 404-Protokoll

    public function notFound(Request $r): Response
    {
        $this->gate($r);
        $q = trim((string) ($r->query['q'] ?? ''));
        $rows = Redirects::notFound($q);
        $suggest = [];
        foreach (array_slice($rows, 0, 100) as $row) {
            if ($s = Redirects::suggest((string) $row['path'])) $suggest[(int) $row['id']] = $s;
        }
        return $this->view('redirects/notfound', ['rows' => $rows, 'q' => $q, 'suggest' => $suggest, 'count' => Redirects::count(), 'log' => Redirects::logEnabled()]);
    }

    /** 404-Protokoll: Vorschlag übernehmen (ein Klick), Einträge ignorieren, Protokoll leeren */
    public function notFoundAction(Request $r): Response
    {
        $this->gate($r);
        $op = (string) ($r->post['op'] ?? '');
        if ($op === 'clear') {
            Redirects::notFoundClear();
            return $this->back('/admin/weiterleitungen/404', 'success', __('404-Protokoll geleert.'));
        }
        if ($op === 'ignore') {
            $n = Redirects::notFoundDelete((array) ($r->post['ids'] ?? []));
            return $this->back('/admin/weiterleitungen/404', $n ? 'success' : 'error', $n ? __('{n} Eintrag/Einträge aus dem Protokoll entfernt.', ['n' => $n]) : __('Bitte zuerst Einträge auswählen.'));
        }
        if ($op === 'create') {
            [, $errors, $notes] = Redirects::save(['source' => (string) ($r->post['source'] ?? ''), 'target' => (string) ($r->post['target'] ?? ''), 'code' => 301]);
            if ($errors) return $this->back('/admin/weiterleitungen/404', 'error', implode(' ', $errors));
            foreach ($notes as $n) app()->session->flash('info', $n);
            return $this->back('/admin/weiterleitungen/404', 'success', __('Weiterleitung angelegt: {source}', ['source' => Redirects::normalize((string) $r->post['source'])]));
        }
        throw new HttpException(400);
    }
}
