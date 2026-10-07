<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Data\Tables;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Permissions;

/** Rollen anlegen und Rechte zuweisen (Recht: users.manage). */
final class RoleController extends AdminController
{
    public function edit(Request $r, ?string $key = null): Response
    {
        $this->auth($r, 'users.manage');
        $role = $key ? (Permissions::role($key) ?? throw new HttpException(404)) : null;
        return $this->view('role', ['role' => $role, 'catalog' => array_filter(array_map(fn($g) => array_filter($g, fn($p) => \Core\Features::allowsPermission($p), ARRAY_FILTER_USE_KEY), Permissions::catalog())), 'tables' => Tables::all(), 'errors' => []]);
    }

    public function save(Request $r, ?string $key = null): Response
    {
        $this->auth($r, 'users.manage');
        $role = $key ? (Permissions::role($key) ?? throw new HttpException(404)) : null;
        if ($role && $role['key'] === 'admin') {
            return $this->back('/admin/users/rollen', 'error', __('Die Rolle „Administration“ hat immer alle Rechte.'));
        }
        if ($role && $role['key'] === 'network') {
            return $this->back('/admin/users/rollen', 'error', __('Netzwerk-Konten werden zentral in der Netzwerk-Verwaltung verwaltet.'));
        }
        $name = mb_substr(trim(strip_tags($r->str('name'))), 0, 60);
        if ($name === '') {
            return $this->back($key ? "/admin/roles/$key" : '/admin/roles/new', 'error', __('Bitte einen Namen angeben.'));
        }
        $perms = array_values(array_intersect((array) ($r->post['perms'] ?? []), Permissions::all()));
        $tables = $r->str('tables_mode') === 'some'
            ? json_encode(array_values(array_intersect((array) ($r->post['tables'] ?? []), array_column(Tables::all(), 'handle'))))
            : null;
        $data = ['name' => $name, 'description' => mb_substr(trim(strip_tags($r->str('description'))), 0, 200),
            'permissions_json' => json_encode($perms), 'tables_json' => $tables];
        if ($role) {
            app()->db->update('roles', $data, 'rkey = :k', ['k' => $role['key']]);
        } else {
            $base = Tables::normName($name) ?: 'rolle';
            $k = $base;
            for ($n = 2; Permissions::role($k); $n++) $k = $base . '_' . $n;
            app()->db->insert('roles', $data + ['rkey' => $k, 'builtin' => 0]);
        }
        return $this->back('/admin/users/rollen', 'success', __('Rolle „{name}“ gespeichert.', ['name' => $name]));
    }

    public function delete(Request $r, string $key): Response
    {
        $this->auth($r, 'users.manage');
        $role = Permissions::role($key) ?? throw new HttpException(404);
        if ($role['builtin'] || isset(Permissions::defaults()[$key])) {   // Standardrollen nie löschen (auch bei falsch gesetztem Kennzeichen)
            return $this->back('/admin/users/rollen', 'error', __('Diese Rolle kann nicht gelöscht werden.'));
        }
        $used = (int) app()->db->fetchValue('SELECT COUNT(*) FROM users WHERE role = ?', [$key]);
        if ($used) {
            return $this->back('/admin/users/rollen', 'error', __('Die Rolle ist noch {n} Benutzer(n) zugewiesen.', ['n' => $used]));
        }
        app()->db->query('DELETE FROM roles WHERE rkey = ?', [$key]);
        return $this->back('/admin/users/rollen', 'success', __('Rolle gelöscht.'));
    }

    /** Rolle eines Benutzers ändern */
    public function assign(Request $r, string $id): Response
    {
        $me = $this->auth($r, 'users.manage');
        $role = Permissions::role($r->str('role')) ?? throw new HttpException(422, __('Unbekannte Rolle.'));
        // Netzwerk-Administration: weder vergeben noch entziehen (zentral, Core\Network)
        if ($role['key'] === 'network' || UserController::isNetworkAccount((int) $id)) {
            return $this->back('/admin/users/personen', 'error', __('Netzwerk-Konten werden zentral in der Netzwerk-Verwaltung verwaltet.'));
        }
        if ((int) $id === (int) $me['id'] && $role['key'] !== 'admin' && $me['role'] === 'admin'
            && !(int) app()->db->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != ?", [(int) $id])) {
            return $this->back('/admin/users/personen', 'error', __('Es muss mindestens ein Administrationskonto bestehen bleiben.'));
        }
        app()->db->update('users', ['role' => $role['key']], 'id = :id', ['id' => (int) $id]);
        return $this->back('/admin/users/personen', 'success', __('Rolle geändert.'));
    }
}
