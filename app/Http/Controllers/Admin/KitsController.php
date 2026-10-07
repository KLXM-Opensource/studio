<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\KitPackages;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Grundeinstellungen → Kits (Core\KitPackages): installierte Kits mit Herkunft, Version und nutzenden Websites,
 * Kit-Paket als ZIP hochladen, hochgeladene Kits entfernen. Kits gelten für alle Websites der Installation –
 * Recht wie bei Schriften (Integratoren, Netzwerk-Administration, Administration der Netzwerk-Website).
 */
final class KitsController extends AdminController
{
    private function guard(Request $r): array
    {
        $user = $this->auth($r);
        if (!KitPackages::canManage()) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        return $user;
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        return $this->view('system/kits', ['title' => __('Kits'), 'kits' => KitPackages::overview(), 'upload' => KitPackages::uploadAllowed(),
            'maxMb' => (int) min(KitPackages::MAX_ZIP / 1048576, self::iniBytes('upload_max_filesize') / 1048576, self::iniBytes('post_max_size') / 1048576)]);
    }

    public function install(Request $r): Response
    {
        $user = $this->guard($r);
        if (!KitPackages::uploadAllowed()) throw new HttpException(403, __('Das Hochladen von Kits ist abgeschaltet (Konfiguration kit_upload).'));
        $f = $r->files['kit'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($f['tmp_name'] ?? ''))) {
            $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
            return $this->back('/admin/system/kits', 'error', in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? __('Die Datei ist zu groß für den Server (upload_max_filesize/post_max_size).') : __('Bitte eine ZIP-Datei auswählen.'));
        }
        if (strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)) !== 'zip') return $this->back('/admin/system/kits', 'error', __('Bitte eine ZIP-Datei auswählen.'));
        if (empty($r->post['trust'])) return $this->back('/admin/system/kits', 'error', __('Bitte bestätigen, dass das Kit aus einer vertrauenswürdigen Quelle stammt.'));
        @set_time_limit(120);
        $res = KitPackages::install((string) $f['tmp_name'], !empty($r->post['replace']), (string) ($user['email'] ?? ''), basename((string) $f['name']));
        $this->changed();
        $msg = $res['message'] . ($res['warnings'] ? ' ' . __('Hinweise:') . ' ' . implode(' · ', $res['warnings']) : '');
        return $this->back('/admin/system/kits' . ($res['ok'] ? '#kit-' . $res['name'] : '#hochladen'), $res['ok'] ? 'success' : 'error', $msg);
    }

    public function remove(Request $r, string $name): Response
    {
        $this->guard($r);
        $res = KitPackages::remove($name);
        $this->changed();
        return $this->back('/admin/system/kits', $res['ok'] ? 'success' : 'error', $res['message']);
    }

    private static function iniBytes(string $key): int
    {
        $v = trim((string) ini_get($key));
        if ($v === '' || $v === '0' || $v === '-1') return PHP_INT_MAX;
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    }
}
