<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Data\Inbox;
use Core\FormCrypto;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Permissions;

/**
 * Anfragen: Eingangs-Tabellen (verschlüsselt). Liste zeigt nur Metadaten; „Entschlüsseln“ mit dem geheimen Schlüssel
 * (POST, weder gespeichert noch protokolliert) öffnet die Einträge der aktuellen Seite – nur für diese Antwort.
 * Jedes Entschlüsseln, jede Status-/Zuweisungsänderung und jedes Löschen landet im Protokoll (inbox_log, ohne Inhalte).
 * Rechte: requests.read / requests.manage, je Rolle optional auf bestimmte Tabellen beschränkt (roles.tables_json).
 */
final class InboxController extends AdminController
{
    private const PER_PAGE = 30;

    /** Tabelle aus der Anfrage – nur Eingangs-Tabellen, die der Benutzer lesen darf */
    private function table(string $handle, array $tables): array
    {
        foreach ($tables as $t) {
            if ($t['handle'] === $handle) return $t;
        }
        throw new HttpException(404);
    }

    public function index(Request $r): Response
    {
        $user = $this->auth($r, 'requests.read');
        $tables = Inbox::readable();
        if (!$tables) {
            return $this->view('requests/index', ['tables' => [], 't' => null, 'rows' => [], 'canLog' => self::canLog()]);
        }
        $handle = $r->str('table') ?: (string) ($r->post['table'] ?? '');
        $t = $handle !== '' ? $this->table($handle, $tables) : $tables[0];
        $status = in_array($r->str('status'), [...Inbox::statusKeys($t), 'alle'], true) ? $r->str('status') : 'neu';
        $page = max(1, (int) $r->str('seite'));
        $total = Inbox::count($t, $status);
        $rows = Inbox::query($t, ['status' => $status, 'limit' => self::PER_PAGE, 'offset' => ($page - 1) * self::PER_PAGE, 'payload' => true]);

        // Entsperren: geheimer Schlüssel nur im POST-Body dieser Anfrage – nie gespeichert, nie protokolliert
        $secret = $r->isPost() ? (string) ($r->post['secret'] ?? '') : '';
        $decrypted = [];
        $keyError = null;
        if ($r->isPost() && $secret === '') {
            $keyError = __('Bitte den geheimen Schlüssel eingeben.');
        } elseif ($secret !== '') {
            if (!FormCrypto::keyMatches($secret)) {
                $keyError = __('Der Schlüssel passt nicht zum hinterlegten öffentlichen Schlüssel.');
            } else {
                foreach ($rows as $row) {
                    $decrypted[$row['id']] = Inbox::open($t, $row, $secret);
                }
                if ($rows) Inbox::log($t['handle'], array_column($rows, 'id'), 'decrypt', count($rows) . ' ' . __('Einträge'));
            }
        }
        $secret = '';
        foreach ($rows as &$row) unset($row['payload']);                      // Chiffretext nicht an die Ansicht weiterreichen
        unset($row);

        $counts = [];
        foreach ([...Inbox::statusKeys($t), 'alle'] as $st) $counts[$st] = Inbox::count($t, $st);
        $newCounts = [];
        foreach ($tables as $x) $newCounts[$x['handle']] = Inbox::count($x, 'neu');
        return $this->view('requests/index', [
            'tables' => $tables, 't' => $t, 'rows' => $rows, 'decrypted' => $decrypted, 'keyError' => $keyError,
            'unlocked' => $decrypted !== [], 'status' => $status, 'page' => $page, 'pages' => (int) ceil($total / self::PER_PAGE),
            'counts' => $counts, 'newCounts' => $newCounts, 'users' => $this->assignees($t), 'canManage' => can('requests.manage', $t['handle']),
            'canLog' => self::canLog(), 'keyReady' => FormCrypto::ready(),
            // Zustellung per E-Mail (Core\Data\Delivery): Modus, Einrichtungshinweise, fehlgeschlagene Zustellungen, Rückfall-Einträge
            'delivery' => \Core\Data\Delivery::mode($t), 'deliveryProblems' => \Core\Data\Delivery::problems($t),
            'deliveryAlerts' => \Core\Data\Delivery::alerts(array_column($tables, 'handle')),
        ]);
    }

    /** Benutzer, die Anfragen dieser Tabelle lesen dürfen (für „Zuweisen“) */
    private function assignees(array $t): array
    {
        $roles = Permissions::roles();
        return array_values(array_filter(app()->db->fetchAll('SELECT id, name, email, role FROM users ORDER BY name, email'),
            fn($u) => Permissions::allows($roles[$u['role']] ?? null, 'requests.read', $t['handle'])));
    }

    private function back2(Request $r, string $handle, string $msg, string $type = 'success'): Response
    {
        $t = \Core\Data\Tables::find($handle);
        $status = in_array($r->str('back'), [...($t ? Inbox::statusKeys($t) : Inbox::STATUSES), 'alle'], true) ? $r->str('back') : 'neu';
        return $this->back('/admin/requests?table=' . rawurlencode($handle) . '&status=' . $status, $type, $msg);
    }

    /** Hinweise „Zustellung fehlgeschlagen“ (Core\Data\Delivery::alert) für die Tabellen des Benutzers ausblenden */
    public function clearAlerts(Request $r): Response
    {
        $this->auth($r, 'requests.manage');
        $handles = array_column(Inbox::readable('requests.manage'), 'handle');
        \Core\Data\Delivery::clearAlerts($handles);
        return $this->back('/admin/requests' . ($r->str('table') !== '' ? '?table=' . rawurlencode($r->str('table')) : ''), 'success', __('Hinweise ausgeblendet.'));
    }

    public function status(Request $r, string $table, string $id): Response
    {
        $this->auth($r, 'requests.manage', $table);
        $t = $this->table($table, Inbox::readable('requests.manage'));
        $st = $r->str('status');
        if (!in_array($st, Inbox::statusKeys($t), true)) throw new HttpException(422, __('Unbekannter Status.'));
        Inbox::find($t, (int) $id) ?? throw new HttpException(404);
        try {
            Inbox::setStatus($t, [(int) $id], $st);
        } catch (\InvalidArgumentException $e) {
            return $this->back2($r, $t['handle'], $e->getMessage(), 'error');   // Prüfung der Erweiterung (z. B. Zeitraum inzwischen belegt)
        }
        return $this->back2($r, $t['handle'], __('Status: {status}.', ['status' => Inbox::statusLabel($st, $t)]));
    }

    public function assign(Request $r, string $table, string $id): Response
    {
        $this->auth($r, 'requests.manage', $table);
        $t = $this->table($table, Inbox::readable('requests.manage'));
        Inbox::find($t, (int) $id) ?? throw new HttpException(404);
        $uid = (int) $r->str('user');
        if ($uid && !in_array($uid, array_map('intval', array_column($this->assignees($t), 'id')), true)) {
            throw new HttpException(422, __('Diese Person darf die Anfragen dieser Tabelle nicht lesen.'));
        }
        Inbox::assign($t, (int) $id, $uid ?: null);
        return $this->back2($r, $t['handle'], $uid ? __('Zugewiesen.') : __('Zuweisung entfernt.'));
    }

    public function delete(Request $r, string $table, string $id): Response
    {
        $this->auth($r, 'requests.manage', $table);
        $t = $this->table($table, Inbox::readable('requests.manage'));
        Inbox::find($t, (int) $id) ?? throw new HttpException(404);
        Inbox::delete($t, [(int) $id]);
        return $this->back2($r, $t['handle'], __('Anfrage gelöscht.'));
    }

    // ================================================================= Protokoll

    /** Protokoll sehen: Administration bzw. Rolle mit „Benutzer und Rollen“ */
    private static function canLog(): bool
    {
        return can('users.manage');
    }

    public function log(Request $r): Response
    {
        $this->auth($r, 'requests.read');
        if (!self::canLog()) throw new HttpException(403, __('Das Protokoll sehen nur Administratoren.'));
        $tables = Inbox::tables();
        $handle = $r->str('table');
        $sel = $handle !== '' && in_array($handle, array_column($tables, 'handle'), true) ? [$handle] : [];
        $events = Inbox::logQuery(['tables' => $sel, 'user' => (int) $r->str('user'), 'limit' => 200]);
        $users = app()->db->fetchAll('SELECT DISTINCT u.id, u.name, u.email FROM inbox_log l JOIN users u ON u.id = l.user_id ORDER BY u.name');
        return $this->view('requests/log', ['tables' => $tables, 'events' => $events, 'users' => $users, 'table' => $handle, 'userId' => (int) $r->str('user'),
            'months' => max(1, (int) app()->settings->get('sys.inbox_log_months', 12))]);
    }
}
