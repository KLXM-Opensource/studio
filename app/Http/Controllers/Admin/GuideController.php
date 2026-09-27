<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Guide;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/**
 * „Hinweise zu diesem Projekt“ (Core\Guide): Hinweise der Website anlegen, bearbeiten, Kit-Hinweise für die Website
 * anpassen oder ausblenden (Recht system.manage); Bilder aus den guide-Ordnern für alle Angemeldeten.
 */
final class GuideController extends AdminController
{
    public function index(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        return $this->view('help/guide/index', ['title' => __('Projekt-Hinweise'), 'notes' => Guide::all(), 'kitDir' => Guide::kitDir()]);
    }

    public function edit(Request $r, string $key = '', ?string $src = null, array $errors = []): Response
    {
        $this->auth($r, 'system.manage');
        $note = $key !== '' ? (Guide::find($key) ?? throw new HttpException(404)) : null;
        $src ??= $note ? Guide::source($note) : "---\nbereich:\nblock:\n---\n# " . __('Titel des Hinweises') . "\n\n";
        return $this->view('help/guide/edit', ['title' => $note ? $note['title'] : __('Neuer Projekt-Hinweis'), 'note' => $note, 'key' => $key,
            'src' => $src, 'errors' => $errors, 'newKey' => (string) ($r->post['key'] ?? '')], $errors ? 422 : 200);
    }

    public function save(Request $r, string $key = ''): Response
    {
        $this->auth($r, 'system.manage');
        $src = (string) ($r->post['src'] ?? '');
        $errors = [];
        if ($key === '') {
            $new = strtolower(trim((string) ($r->post['key'] ?? '')));
            if (!Guide::validKey($new)) $errors['key'] = __('Nur Kleinbuchstaben, Ziffern, - und _ (z. B. 10-startseite) – die Zahl vorn bestimmt die Reihenfolge.');
            elseif (Guide::find($new)) $errors['key'] = __('Diesen Dateinamen gibt es schon.');
        } else {
            $new = $key;
            Guide::find($key) ?? throw new HttpException(404);
        }
        if (trim($src) === '') $errors['src'] = __('Bitte einen Text eingeben.');
        elseif (strlen($src) > Guide::MAX) $errors['src'] = __('Der Text ist zu lang.');
        if ($errors) {
            app()->session->flash('error', __('Bitte prüfen Sie die markierten Felder – es wurde nichts gespeichert.'));
            return $this->edit($r, $key, $src, $errors);
        }
        Guide::save($new, $src);
        return $this->back('/admin/hilfe/projekt', 'success', __('Projekt-Hinweis „{name}“ gespeichert.', ['name' => Guide::find($new)['title'] ?? $new]));
    }

    /** Website-Fassung löschen – ein gleichnamiger Hinweis aus dem Kit gilt dann wieder */
    public function delete(Request $r, string $key): Response
    {
        $this->auth($r, 'system.manage');
        $note = Guide::find($key) ?? throw new HttpException(404);
        if ($note['source'] !== 'site') throw new HttpException(404);
        Guide::delete($key);
        $kit = Guide::find($key);
        return $this->back('/admin/hilfe/projekt', 'success', $kit ? __('Anpassung entfernt – es gilt wieder der Hinweis aus dem Kit.') : __('Projekt-Hinweis gelöscht.'));
    }

    /** Bild aus themes/{kit}/guide bzw. {storage}/guide (nur Bildformate, nur Dateiname) */
    public function image(Request $r, string $file): Response
    {
        $this->auth($r);
        $path = Guide::image($file) ?? throw new HttpException(404);
        $type = Guide::IMAGES[strtolower(pathinfo($path, PATHINFO_EXTENSION))];
        return (new Response((string) file_get_contents($path)))->header('Content-Type', $type)->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, max-age=3600')->header('Content-Security-Policy', "default-src 'none'");
    }
}
