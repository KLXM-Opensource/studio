<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Api\Tokens;
use Core\Http\Request;
use Core\Http\Response;

/** Admin → API & MCP: Tokens verwalten, Einrichtungshilfe. */
final class ApiTokenController extends AdminController
{
    public function index(Request $r, ?string $newToken = null, array $errors = []): Response
    {
        $this->auth($r, 'api.manage');
        return $this->view('api', ['tokens' => Tokens::all(), 'newToken' => $newToken, 'errors' => $errors], $errors ? 422 : 200);
    }

    public function store(Request $r): Response
    {
        $user = $this->auth($r, 'api.manage');
        $name = trim(strip_tags($r->str('name')));
        $expires = $r->str('expires');
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen vergeben (z. B. „Claude – Redaktion“).';
        }
        if ($expires !== '' && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $expires)) {
            $errors['expires'] = 'Ungültiges Datum.';
        }
        if ($errors) {
            return $this->index($r, null, $errors);
        }
        // Neue Tokens mit Schreibrecht reichen Änderungen standardmäßig zur Freigabe ein (Core\Review)
        $t = Tokens::create($name, $r->str('scope'), (int) $user['id'], $expires ?: null, $r->str('review_mode', 'review'));
        app()->session->flash('success', 'Token erzeugt. Jetzt kopieren – er wird nur dieses eine Mal angezeigt.');
        return $this->index($r, $t['token']);
    }

    /** Änderungen dieses Tokens: direkt übernehmen | zur Freigabe (Core\Review) */
    public function mode(Request $r, string $id): Response
    {
        $this->own($this->auth($r, 'api.manage'), (int) $id);
        $mode = $r->str('review_mode') === 'review' ? 'review' : 'direct';
        Tokens::setMode((int) $id, $mode);
        return $this->back('/admin/api-tokens', 'success', $mode === 'review'
            ? __('Änderungen dieses Tokens werden ab sofort zur Freigabe eingereicht.') : __('Änderungen dieses Tokens werden ab sofort direkt übernommen.'));
    }

    public function delete(Request $r, string $id): Response
    {
        $this->own($this->auth($r, 'api.manage'), (int) $id);
        Tokens::revoke((int) $id);
        return $this->back('/admin/api-tokens', 'success', 'Token widerrufen. Zugriffe damit sind ab sofort gesperrt.');
    }

    /** Fremde Tokens ändern oder widerrufen nur Konten mit allen Rechten (sonst z. B. Freigabe-Pflicht eines Admin-Tokens abschaltbar) */
    private function own(array $user, int $id): void
    {
        $role = app()->auth->role();
        if ($role !== null && in_array('*', $role['permissions'], true)) return;
        $owner = app()->db->fetchValue('SELECT user_id FROM api_tokens WHERE id = ?', [$id]);
        if ($owner === null || (int) $owner !== (int) ($user['id'] ?? 0)) throw new \Core\Http\HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
    }
}
