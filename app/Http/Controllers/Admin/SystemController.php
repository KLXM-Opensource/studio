<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\AppIcons;
use Core\Fields;
use Core\FormCrypto;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mailer;
use Core\PageCache;
use Core\Sites;
use Core\SystemSchema;

/** Grundeinstellungen (nur Rolle „admin“). */
final class SystemController extends AdminController
{
    public function edit(Request $r, array $errors = [], ?array $values = null, ?string $newSecret = null): Response
    {
        $this->auth($r, 'system.manage');
        $s = app()->settings;
        if ($values === null) {
            $values = [];
            foreach (SystemSchema::fields() as $f) {
                if (isset($f['name'])) {
                    $values[$f['name']] = $s->get($f['name'], $f['default'] ?? null);
                }
            }
        }
        return $this->view('system', [
            'groups' => SystemSchema::groups(), 'values' => $values, 'errors' => $errors,
            'keyReady' => FormCrypto::ready(), 'fingerprint' => FormCrypto::fingerprint(), 'newSecret' => $newSecret,
            'info' => $this->info(),
        ], $errors ? 422 : 200);
    }

    public function save(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        $current = [];
        foreach (SystemSchema::fields() as $f) {
            if (isset($f['name'])) {
                $current[$f['name']] = app()->settings->get($f['name']);
            }
        }
        // Feldnamen enthalten Punkte → PHP wandelt sie in $_POST zu "_"; daher Unterstrich-Schlüssel zurückmappen
        $input = [];
        foreach ((array) ($r->post['f'] ?? []) as $k => $v) {
            $input[preg_replace('~^sys_~', 'sys.', (string) $k)] = $v;
        }
        [$values, $errors] = Fields::sanitize(SystemSchema::fields(), $input, $current);
        if ($errors) {
            app()->session->flash('error', 'Bitte prüfen Sie die markierten Felder – es wurde nichts gespeichert.');
            return $this->edit($r, $errors, $values);
        }
        app()->settings->setMany($values);
        // Icons neu erzeugen, wenn sich etwas am App-Icon geändert hat
        $iconKeys = array_filter(array_keys($values), fn($k) => str_starts_with($k, 'sys.icon_') || str_starts_with($k, 'sys.pwa'));
        foreach ($iconKeys as $k) {
            if (($current[$k] ?? null) != $values[$k] || !is_file(AppIcons::dir() . '/favicon.ico')) {
                if ($err = AppIcons::generate()) {
                    app()->session->flash('error', $err);
                }
                break;
            }
        }
        $this->changed();
        return $this->back('/admin/system' . ($r->str('_tab') ? '#' . preg_replace('~[^\w\-]~', '', $r->str('_tab')) : ''), 'success', 'Grundeinstellungen gespeichert.');
    }

    /** Live-Vorschau des App-Icons aus den (noch nicht gespeicherten) Formularwerten */
    public function iconPreview(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        $over = [];
        foreach ((array) ($r->post['f'] ?? []) as $k => $v) {
            $k = preg_replace('~^sys_~', 'sys.', (string) $k);
            if (str_starts_with($k, 'sys.icon_')) {
                $over[$k] = is_scalar($v) ? (string) $v : '';
            }
        }
        $over['sys.icon_dot'] = !empty($over['sys.icon_dot']) && $over['sys.icon_dot'] !== '0';
        return Response::json(AppIcons::preview($over) + ['info' => AppIcons::appInfo()]);
    }

    public function testMail(Request $r): Response
    {
        $user = $this->auth($r, 'system.manage');
        $to = $r->str('to') ?: $user['email'];
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->back('/admin/system#mail', 'error', 'Ungültige Empfängeradresse.');
        }
        $err = Mailer::send('Testnachricht der Website', "Diese Testnachricht bestätigt, dass der E-Mail-Versand der Website funktioniert.\n\n" . absolute_url('/'), [$to]);
        return $this->back('/admin/system#mail', $err ? 'error' : 'success', $err ?? "Testnachricht an $to gesendet.");
    }

    public function generateKeys(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        if (FormCrypto::ready() && $r->str('confirm') !== 'NEU') {
            return $this->back('/admin/system#keys', 'error', 'Zum Ersetzen des vorhandenen Schlüssels bitte „NEU“ eintippen. Achtung: Bisherige Anfragen sind dann nur noch mit dem alten Schlüssel lesbar.');
        }
        $keys = FormCrypto::generate();
        app()->settings->set('sys.form_public_key', $keys['public']);
        app()->settings->set('sys.form_key_created', now());
        $this->changed();
        app()->session->flash('success', 'Neuer ' . term('key') . ' erzeugt. Bitte JETZT den geheimen Schlüssel sicher aufbewahren – er wird nur dieses eine Mal angezeigt.');
        return $this->edit($r, [], null, $keys['secret']);
    }

    public function clearCache(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        PageCache::clear();
        return $this->back('/admin/system#info', 'success', 'Seiten-Cache geleert.');
    }

    // ------------------------------------------------------------------ Geteilte Medien (Pools)

    private function poolAuth(Request $r): void
    {
        $this->auth($r, 'system.manage');
        if (!\Core\MediaPools::canManage()) {
            throw new \Core\Http\HttpException(403, __('Geteilte Medien verwaltet die Hauptwebsite bzw. die Agentur.'));
        }
    }

    public function poolCreate(Request $r): Response
    {
        $this->poolAuth($r);
        $key = strtolower(trim($r->str('key')));
        try {
            \Core\MediaPools::create($key, $r->str('label') ?: $key, (array) ($r->post['sites'] ?? [site()->key]));
        } catch (\InvalidArgumentException $e) {
            return $this->back('/admin/system#pools', 'error', $e->getMessage());
        }
        return $this->back('/admin/system#pools', 'success', __('Pool „{name}“ angelegt.', ['name' => $r->str('label') ?: $key]));
    }

    public function poolUpdate(Request $r, string $key): Response
    {
        $this->poolAuth($r);
        try {
            \Core\MediaPools::update($key, $r->str('label'), (array) ($r->post['sites'] ?? []), (array) ($r->post['editors'] ?? []));
        } catch (\InvalidArgumentException $e) {
            return $this->back('/admin/system#pools', 'error', $e->getMessage());
        }
        return $this->back('/admin/system#pools', 'success', __('Pool gespeichert.'));
    }

    public function poolDelete(Request $r, string $key): Response
    {
        $this->poolAuth($r);
        try {
            \Core\MediaPools::delete($key);
        } catch (\InvalidArgumentException $e) {
            return $this->back('/admin/system#pools', 'error', $e->getMessage());
        }
        return $this->back('/admin/system#pools', 'success', __('Pool gelöscht.'));
    }

    // ------------------------------------------------------------------ Geteilte Daten (Core\Data\Shared)

    private function sharedAuth(Request $r): void
    {
        $this->auth($r, 'system.manage');
        if (!\Core\Data\Shared::canManage()) {
            throw new \Core\Http\HttpException(403, __('Für geteilte Daten fehlt Ihrer Rolle die Berechtigung („Geteilte Daten verwalten“).'));
        }
    }

    /** Geteilte Tabelle anlegen: neu (aus Vorlage) oder aus einer vorhandenen Tabelle dieser Website */
    public function sharedCreate(Request $r): Response
    {
        $this->sharedAuth($r);
        $members = (array) ($r->post['members'] ?? []);
        $flags = ['members_see_members' => $r->str('members_see_members') === '1'];
        try {
            if ($r->str('mode') === 'local') {
                $handle = $r->str('local');
                \Core\Data\Shared::shareLocal($handle, $members, false, $flags);
            } else {
                $def = DataController::presetDef($r->str('preset'));
                $def['name'] = $r->str('name');
                $def['handle'] = $r->str('handle');
                [$clean, $errors] = \Core\Data\Tables::validate($def + ['_shared_owner' => site()->key]);
                if ($errors) return $this->back('/admin/system#shared', 'error', implode(' ', $errors));
                $handle = \Core\Data\Shared::create($clean, $members, $flags);
            }
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->back('/admin/system#shared', 'error', $e->getMessage());
        }
        return $this->back('/admin/data/' . $handle . (can('data.schema') ? '/schema' : ''), 'success',
            __('Geteilte Tabelle angelegt. Die beteiligten Websites sehen sie ab sofort unter „Daten“.'));
    }

    /** Bezeichnung, Mitglieder und Schalter einer geteilten Tabelle ändern (Eigentümer-Website oder Integrator) */
    public function sharedUpdate(Request $r, string $key): Response
    {
        $this->sharedAuth($r);
        if (!\Core\Data\Shared::canAdmin($key)) throw new \Core\Http\HttpException(403, __('Diese geteilte Tabelle verwaltet die Website, der sie gehört.'));
        try {
            \Core\Data\Shared::update($key, ['label' => $r->str('label'), 'members' => (array) ($r->post['members'] ?? []),
                'members_see_members' => $r->str('members_see_members') === '1']);
        } catch (\InvalidArgumentException $e) {
            return $this->back('/admin/system#shared', 'error', $e->getMessage());
        }
        return $this->back('/admin/system#shared', 'success', __('Geteilte Tabelle gespeichert.'));
    }

    /** Freigabe beenden: wieder eigene Tabelle der Eigentümer-Website (Bestätigung mit dem Kurznamen) */
    public function sharedUnshare(Request $r, string $key): Response
    {
        $this->sharedAuth($r);
        if (!\Core\Data\Shared::canAdmin($key)) throw new \Core\Http\HttpException(403, __('Diese geteilte Tabelle verwaltet die Website, der sie gehört.'));
        if ($r->str('confirm') !== $key) return $this->back('/admin/system#shared', 'error', __('Zum Beenden der Freigabe bitte den Kurznamen „{key}“ eintippen.', ['key' => $key]));
        try {
            $log = \Core\Data\Shared::unshare($key);
        } catch (\InvalidArgumentException $e) {
            return $this->back('/admin/system#shared', 'error', $e->getMessage());
        }
        return $this->back('/admin/system#shared', 'success', implode(' ', $log));
    }

    public function clearProxy(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        $n = \Core\Proxy::clear();
        return $this->back('/admin/system#proxy', 'success', __('Zwischenspeicher der externen Quellen geleert ({n} Dateien).', ['n' => $n]));
    }

    private function info(): array
    {
        $db = app()->db;
        return [
            'PHP-Version' => PHP_VERSION,
            'Version' => CMS_NAME . ' ' . CMS_VERSION,
            'Datenbank' => $db->driver === 'sqlite' ? 'SQLite (' . str_replace(ROOT . '/', '', site()->storage('database')) . ')' : 'MySQL/MariaDB',
            'Kit' => app()->theme->label() . ' (' . app()->theme->name . ')',
            'AVIF-Unterstützung' => function_exists('imageavif') ? 'ja' : 'nein (nur WebP)',
            'Saubere URLs' => app()->config->get('url_rewrite') ? 'aktiv' : 'aus (/index.php/…)',
            'Upload-Limit (PHP)' => ini_get('upload_max_filesize') . ' / POST ' . ini_get('post_max_size'),
            'Website' => site()->key . (Sites::multi() ? ' (' . count(Sites::all()) . ' Websites in dieser Installation)' : ''),
            'Datenordner beschreibbar' => is_writable(site()->storage()) || (!is_dir(site()->storage()) && is_writable(dirname(site()->storage()))) ? 'ja' : 'NEIN – bitte Rechte prüfen',
            'Medienordner beschreibbar' => is_writable(site()->mediaDir()) ? 'ja' : 'NEIN – bitte Rechte prüfen',
            'HTTPS' => app()->request?->isSecure() ? 'ja' : 'NEIN – für den Livebetrieb zwingend',
        ];
    }
}
