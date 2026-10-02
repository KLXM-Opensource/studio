<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Data\Entries;
use Core\Glossary\Glossary;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Verwaltung → Glossar (Funktion „glossary“, Core\Glossary): einrichten, Begriffe mit Vorkommen und Hinweisen, schnell
 * hinzufügen (optional mit KI-Vorschlag als Entwurf), Import/Export (CSV), Einstellungen. Bearbeitet wird in der Datentabelle.
 */
final class GlossaryController extends AdminController
{
    private const BASE = '/admin/glossar';

    private function gate(Request $r, string $need = 'edit'): array
    {
        $user = $this->auth($r);
        if (!Glossary::enabled()) throw new HttpException(404);
        $t = Glossary::table();
        $ok = match ($need) {
            'schema' => can('data.schema'),
            default => $t ? can('data.edit', $t['handle']) : can('data.schema'),
        };
        if (!$ok) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        return $user;
    }

    public function index(Request $r): Response
    {
        $this->gate($r);
        $t = Glossary::table();
        $terms = $t ? Glossary::terms(true, 'all') : [];
        $occ = $t ? Glossary::occurrences(isset($r->query['neu'])) : [];
        $import = app()->session->get('_glossary_import');
        app()->session->forget('_glossary_import');
        // Einstellungsseite der Tabelle „glossar“ (Core\AdminPages): Bereichsnavigation „Daten“ mit der Tabelle geöffnet
        $drill = can('data.schema') || ($t && can('data.edit', $t['handle']))
            ? \Core\Theme::capture(ROOT . '/app/Admin/views/data/_nav.php', ['cur' => 'page:glossary', 'active' => $t]) : '';
        return $this->view('glossary/index', ['drill' => $drill, 'drillTitle' => __('Daten'), 't' => $t, 'terms' => $terms, 'checks' => $t ? Glossary::checks($terms) : [], 'occ' => $occ,
            'settings' => Glossary::settings(), 'import' => $import, 'ai' => Glossary::aiAvailable(), 'overview' => Glossary::overviewUrl()]);
    }

    public function install(Request $r): Response
    {
        $this->gate($r, 'schema');
        try {
            [$msgs] = Glossary::install(['publish' => !empty($r->post['publish'])]);
        } catch (\RuntimeException $e) {
            return $this->back(self::BASE, 'error', $e->getMessage());
        }
        return $this->back(self::BASE, 'success', implode(' ', $msgs));
    }

    public function settings(Request $r): Response
    {
        $this->gate($r, 'schema');
        Glossary::saveSettings((array) ($r->post['s'] ?? []));
        return $this->back(self::BASE . '#einstellungen', 'success', __('Einstellungen gespeichert.'));
    }

    /** Begriff schnell hinzufügen – ohne Kurz-Erklärung mit KI-Vorschlag (immer als Entwurf) */
    public function quick(Request $r): Response
    {
        $this->gate($r);
        $t = Glossary::table() ?? throw new HttpException(404);
        $term = trim(strip_tags((string) ($r->post['begriff'] ?? '')));
        $variants = Glossary::splitVariants((string) ($r->post['varianten'] ?? ''));
        $short = Glossary::short((string) ($r->post['kurz'] ?? ''));
        if ($term === '') return $this->back(self::BASE . '#neu', 'error', __('Bitte einen Begriff eingeben.'));
        $ai = false;
        if ($short === '' && !empty($r->post['ai']) && Glossary::aiAvailable()) {
            try {
                $short = Glossary::suggest($term, $variants);
                $ai = true;
            } catch (\Throwable $e) {
                return $this->back(self::BASE . '#neu', 'error', $e->getMessage());
            }
        }
        if ($short === '') return $this->back(self::BASE . '#neu', 'error', __('Bitte eine Kurz-Erklärung eingeben (oder von der KI vorschlagen lassen).'));
        [$id, $errors] = Entries::save($t, null, ['begriff' => $term, 'varianten' => implode("\n", $variants), 'kurz' => $short, 'status' => 'draft']);
        if ($errors) return $this->back(self::BASE . '#neu', 'error', implode(' ', $errors));
        Glossary::flush();
        return $this->back('/admin/data/' . $t['handle'] . '/' . $id, 'success', $ai
            ? __('„{t}“ als Entwurf angelegt – die Erklärung stammt von der KI. Bitte prüfen und dann veröffentlichen.', ['t' => $term])
            : __('„{t}“ als Entwurf angelegt.', ['t' => $term]));
    }

    public function import(Request $r): Response
    {
        $this->gate($r);
        $text = trim((string) ($r->post['text'] ?? ''));
        $file = $r->files['file'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string) $file['tmp_name'])) {
            if ((int) $file['size'] > 5 * 1024 * 1024) return $this->back(self::BASE . '#import', 'error', __('Die Datei ist zu groß (höchstens 5 MB).'));
            $text = (string) file_get_contents((string) $file['tmp_name']);
        }
        if (trim($text) === '') return $this->back(self::BASE . '#import', 'error', __('Bitte eine CSV-Datei wählen oder Zeilen einfügen.'));
        try {
            $res = Glossary::importCsv($text, !empty($r->post['dry']), !empty($r->post['overwrite']));
        } catch (\RuntimeException $e) {
            return $this->back(self::BASE . '#import', 'error', $e->getMessage());
        }
        $res['dry'] = !empty($r->post['dry']);
        $res['errors'] = array_slice($res['errors'], 0, 30, true);
        app()->session->set('_glossary_import', $res);
        return $this->back(self::BASE . '#import', $res['errors'] && !$res['created'] && !$res['updated'] ? 'error' : 'success',
            __('Import: {c} neu, {u} geändert, {s} übersprungen.', ['c' => $res['created'], 'u' => $res['updated'], 's' => $res['skipped']]));
    }

    public function export(Request $r): Response
    {
        $this->gate($r);
        return self::secure(new Response(Glossary::exportCsv(), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="glossar-' . site()->key . '-' . date('Y-m-d') . '.csv"',
        ]));
    }
}
