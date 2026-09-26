<?php
// SPDX-License-Identifier: MIT
// Portions ported from FriendsOfREDAXO/consent_kit (MIT, © KLXM Crossmedia GmbH): Backend/ServiceController, pages/*
declare(strict_types=1);

namespace MyCms\Consent;

use Core\Fields;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Theme;

/** Verwaltung → Cookie-Einwilligung (Recht consent.manage): Dienste, Vorlagen, Einstellungen, Design, Protokoll, Import/Export. */
final class AdminController extends \Core\Http\Controllers\Admin\AdminController
{
    private function guard(Request $r): array
    {
        if (!\Core\Extensions::isActive('consent_kit') || !\Core\Features::on('consent')) throw new HttpException(404);
        $u = $this->auth($r, 'consent.manage');
        if (!Repository::ready()) throw new HttpException(503, __('Die Tabellen der Erweiterung werden noch angelegt. Bitte neu laden.'));
        Log::maybePurge();
        return $u;
    }

    private function page(string $view, array $vars = [], int $status = 200): Response
    {
        $vars += ['errors' => [], 'tab' => $view];
        $vars['flash'] = app()->session->takeFlash();
        $vars['css'] = [];
        $vars['user'] = app()->auth->user();
        $dir = dirname(__DIR__) . '/views/';
        $content = Theme::capture($dir . '_nav.php', $vars) . Theme::capture($dir . $view . '.php', $vars)
            . '<link rel="stylesheet" href="' . e(Consent::asset('css/consent-admin.css')) . '">'
            . '<script src="' . e(Consent::asset('js/consent-admin.js')) . '" defer></script>';
        $html = Theme::capture(ROOT . '/app/Admin/views/layout.php', $vars + ['content' => $content, 'view' => 'consent', 'title' => ($vars['title'] ?? __('Cookie-Einwilligung'))]);
        return self::secure(new Response($html, $status));
    }

    // ------------------------------------------------------------------ Dienste

    public function index(Request $r): Response
    {
        $this->guard($r);
        return $this->page('services', ['title' => __('Cookie-Einwilligung'), 'services' => Repository::services(), 'domains' => Compiler::domains(), 'status' => self::status()]);
    }

    /** Status oben auf der Dienste-Seite: Hinweis aktiv? Rechtstexte verlinkt? Unvollständige Dienste? */
    private static function status(): array
    {
        $out = [];
        $needed = Consent::needed();
        $out[] = [$needed ? 'ok' : 'info', $needed ? __('Der Hinweis wird auf der Website angezeigt (mindestens ein einwilligungspflichtiger Dienst ist aktiv).')
            : __('Kein einwilligungspflichtiger Dienst aktiv – Besucher sehen keinen Hinweis, es wird kein Skript geladen.')];
        foreach (['privacy' => __('Datenschutzerklärung'), 'imprint' => __('Impressum')] as $k => $l) {
            [$href] = Consent::legal($k);
            if (!$href) $out[] = ['warn', __('{label} ist nicht verlinkt (Einstellungen → Rechtstexte).', ['label' => $l])];
        }
        foreach (Repository::services(true) as $s) {
            if (!Compiler::isComplete($s)) $out[] = ['warn', __('„{name}“ ist unvollständig (es fehlt eine Angabe) und wird nicht ausgeliefert.', ['name' => $s['name']])];
        }
        $st = Repository::settings();
        if ($needed && !$st['trigger'] && !$st['footer_link']) $out[] = ['warn', __('Weder schwebende Schaltfläche noch Link im Fußbereich: Besucher müssen ihre Auswahl jederzeit ändern können – bitte eines davon einschalten oder consent_settings_link() im Kit ausgeben.')];
        return $out;
    }

    public function toggle(Request $r): Response
    {
        $this->guard($r);
        $s = Repository::find((int) $r->str('id'));
        if (!$s) throw new HttpException(404);
        if (!$s['active'] && !Compiler::isComplete($s)) {
            return $this->back('/admin/consent', 'error', __('„{name}“ ist unvollständig – bitte zuerst die fehlenden Angaben eintragen.', ['name' => $s['name']]));
        }
        Repository::save($s['id'], ['active' => !$s['active']]);
        return $this->back('/admin/consent', 'success', $s['active'] ? __('„{name}“ ist aus.', ['name' => $s['name']]) : __('„{name}“ ist aktiv.', ['name' => $s['name']]));
    }

    public function domains(Request $r): Response
    {
        $this->guard($r);
        $all = array_keys(Compiler::domains());
        foreach (Repository::services() as $s) {
            $sel = array_values(array_intersect($all, array_map('strval', (array) ($r->post['d'][$s['id']] ?? []))));
            if (!$sel) $sel = [$all[0]];   // die letzte Domain lässt sich nicht abwählen – dafür gibt es den Schalter
            $hosts = count($sel) === count($all) ? [] : $sel;
            if ($hosts !== $s['hosts']) Repository::save($s['id'], ['hosts' => $hosts]);
        }
        return $this->back('/admin/consent', 'success', __('Domains gespeichert.'));
    }

    public function move(Request $r): Response
    {
        $this->guard($r);
        $s = Repository::find((int) $r->str('id'));
        if (!$s) throw new HttpException(404);
        $same = array_values(array_filter(Repository::services(), fn($x) => $x['grp'] === $s['grp']));
        $i = array_search($s['id'], array_column($same, 'id'), true);
        $j = $r->str('dir') === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($same[$j])) {
            [$same[$i], $same[$j]] = [$same[$j], $same[$i]];
            foreach ($same as $n => $x) app()->db->update('consent_services', ['prio' => $n + 1], 'id = ?', [$x['id']]);
            Repository::changed();
        }
        return $this->back('/admin/consent#s' . $s['id']);
    }

    public function templates(Request $r): Response
    {
        $this->guard($r);
        return $this->page('templates', ['title' => __('Dienst hinzufügen'), 'presets' => Presets::all(), 'existing' => array_column(Repository::services(), 'id', 'skey')]);
    }

    public function create(Request $r): Response
    {
        $this->guard($r);
        $key = $r->str('preset');
        $data = $key !== '' ? Presets::toService($key) : null;
        if ($key !== '' && !$data) throw new HttpException(404);
        if ($data && Repository::keyExists($data['skey'])) {
            // Zweiter Dienst aus derselben Vorlage (z. B. zweite Matomo-Instanz): Schlüssel mit Nummer
            for ($n = 2; Repository::keyExists($data['skey'] . '_' . $n); $n++);
            $data['skey'] .= '_' . $n;
        }
        $data ??= ['skey' => '', 'grp' => 'statistics', 'name' => '', 'provider' => '', 'privacy_url' => '', 'description' => [], 'params' => [], 'param_defs' => [],
            'html_head' => '', 'html_body' => '', 'js_default' => '', 'js_accept' => '', 'js_revoke' => '', 'gcm' => [], 'embed_hosts' => [],
            'csp' => ['script' => [], 'connect' => [], 'img' => [], 'frame' => []], 'items' => [], 'events' => [], 'variants' => [], 'hosts' => [],
            'preset' => '', 'sources' => [], 'verified' => '', 'note' => [], 'active' => false];
        return $this->page('service', ['title' => __('Dienst hinzufügen'), 's' => $data + ['id' => 0], 'domains' => Compiler::domains()]);
    }

    public function edit(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = Repository::find((int) $id) ?? throw new HttpException(404);
        return $this->page('service', ['title' => $s['name'], 's' => $s, 'domains' => Compiler::domains(), 'complete' => Compiler::isComplete($s)]);
    }

    /** Formularfelder der Wiederholungen (Fields-API des Kerns: Repeater, Auswahl, Mehrzeilig) */
    public static function itemFields(): array
    {
        return [
            ['name' => 'type', 'label' => __('Art'), 'type' => 'select', 'required' => true, 'default' => 'cookie', 'width' => 'half', 'options' => Repository::ITEM_TYPES],
            ['name' => 'name', 'label' => __('Name (* als Platzhalter)'), 'type' => 'text', 'required' => true, 'width' => 'half'],
            ['name' => 'host', 'label' => __('Domain (leer = eigene)'), 'type' => 'text', 'width' => 'half', 'placeholder' => '.example.com'],
            ['name' => 'value', 'label' => __('Laufzeit'), 'type' => 'number', 'width' => 'half', 'step' => 1],
            ['name' => 'unit', 'label' => __('Einheit'), 'type' => 'select', 'required' => true, 'default' => 'days', 'width' => 'half', 'options' => array_map('__', Repository::UNITS)],
            ['name' => 'purpose_de', 'label' => __('Zweck (Deutsch)'), 'type' => 'text', 'width' => 'half'],
            ['name' => 'purpose_en', 'label' => __('Zweck (Englisch)'), 'type' => 'text', 'width' => 'half'],
        ];
    }

    public static function eventFields(): array
    {
        return [
            ['name' => 'trigger', 'label' => __('Wenn …'), 'type' => 'select', 'required' => true, 'default' => 'click', 'width' => 'half', 'options' => array_map('__', Events::TRIGGERS)],
            ['name' => 'target', 'label' => __('Ziel (CSS-Selektor bzw. Pfad)'), 'type' => 'text', 'width' => 'half', 'placeholder' => 'a[href^="mailto:"], a[href^="tel:"]'],
            ['name' => 'event', 'label' => __('… dann melde'), 'type' => 'select', 'required' => true, 'default' => 'lead', 'width' => 'half', 'options' => array_map('__', Events::TYPES)],
            ['name' => 'label', 'label' => __('Kennung beim Anbieter (optional, z. B. Google-Ads-Label)'), 'type' => 'text', 'width' => 'half'],
            ['name' => 'code', 'label' => __('Eigener Code (nur bei „Eigener Code“)'), 'type' => 'textarea', 'rows' => 2],
        ];
    }

    public static function variantFields(array $domains): array
    {
        $langs = ['' => __('alle Sprachen')] + (array) \Core\Lang::all();
        return [
            ['name' => 'domain', 'label' => __('Gilt für Domain'), 'type' => 'select', 'width' => 'half', 'options' => ['' => __('alle Domains')] + $domains],
            ['name' => 'lang', 'label' => __('Gilt für Sprache'), 'type' => 'select', 'width' => 'half', 'options' => $langs],
            ['name' => 'params', 'label' => __('Abweichende Angaben (eine je Zeile: schlüssel=wert)'), 'type' => 'textarea', 'rows' => 2, 'placeholder' => 'site_id=7'],
            ['name' => 'html_head', 'label' => __('Eigener Code: HTML im <head> (leer = wie Dienst)'), 'type' => 'textarea', 'rows' => 2],
            ['name' => 'html_body', 'label' => __('Eigener Code: HTML am Ende des <body>'), 'type' => 'textarea', 'rows' => 2],
            ['name' => 'js_accept', 'label' => __('Eigener Code: JavaScript bei Einwilligung'), 'type' => 'textarea', 'rows' => 2],
        ];
    }

    private static function lines(string $s): array
    {
        return array_values(array_filter(array_map('trim', preg_split('~[\r\n,]+~', $s) ?: [])));
    }

    /** CSP-Quelle normalisieren: host → https://host; nur https://(*.)host erlaubt */
    private static function cspHosts(string $s, array &$errors, string $field): array
    {
        $out = [];
        foreach (self::lines($s) as $h) {
            $h = preg_replace('~^(?!https?://)~', 'https://', rtrim($h, '/'));
            if (preg_match('~^https://(\*\.)?[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+(:\d+)?$~i', (string) $h)) $out[] = strtolower((string) $h);
            else $errors[$field] = __('„{value}“ ist keine gültige Quelle (erlaubt: https://host oder https://*.host).', ['value' => $h]);
        }
        return array_values(array_unique($out));
    }

    public function save(Request $r, string $id): Response
    {
        $this->guard($r);
        $id = (int) $id;
        $old = $id ? (Repository::find($id) ?? throw new HttpException(404)) : null;
        $in = (array) ($r->post['s'] ?? []);
        $errors = [];
        $domains = Compiler::domains();

        $key = strtolower(trim((string) ($in['skey'] ?? ($old['skey'] ?? ''))));
        if ($old && empty($in['change_key'])) $key = $old['skey'];
        if (!preg_match('~^[a-z0-9_]{1,60}$~', $key)) $errors['skey'] = __('Schlüssel: nur a–z, 0–9 und _ (höchstens 60 Zeichen).');
        elseif (Repository::keyExists($key, $id)) $errors['skey'] = __('Diesen Schlüssel gibt es schon.');
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') $errors['name'] = __('Bitte einen Namen angeben.');
        $grp = isset(Repository::GROUPS[$in['grp'] ?? '']) ? (string) $in['grp'] : 'statistics';
        $privacy = trim((string) ($in['privacy_url'] ?? ''));
        if ($privacy !== '' && !preg_match('~^https://~i', $privacy)) $errors['privacy_url'] = __('Datenschutz-Link: bitte mit https:// angeben.');

        $defs = $old['param_defs'] ?? array_values((array) json_decode((string) ($in['param_defs'] ?? '[]'), true));
        $params = [];
        foreach ($defs as $def) {
            $k = (string) ($def['key'] ?? '');
            $v = trim((string) ($in['params'][$k] ?? ''));
            if ($v !== '' && !empty($def['pattern']) && @preg_match('~' . str_replace('~', '\~', (string) $def['pattern']) . '~', $v) === 0) {
                $errors['param_' . $k] = __('„{label}“ hat nicht das erwartete Format ({ph}).', ['label' => Compiler::pick((array) ($def['label'] ?? []), I18nLang::admin()), 'ph' => (string) ($def['placeholder'] ?? '')]);
            }
            if ($v !== '') $params[$k] = $v;
        }
        [$items, $e1] = Fields::sanitize([['name' => 'items', 'type' => 'repeater', 'fields' => self::itemFields()]], ['items' => $in['items'] ?? []]);
        [$events, $e2] = Fields::sanitize([['name' => 'events', 'type' => 'repeater', 'fields' => self::eventFields()]], ['events' => $in['events'] ?? []]);
        [$variants, $e3] = Fields::sanitize([['name' => 'variants', 'type' => 'repeater', 'fields' => self::variantFields($domains)]], ['variants' => $in['variants'] ?? []]);
        $errors += $e1 + $e2 + $e3;
        $vlist = [];
        foreach ($variants['variants'] as $v) {
            $p = [];
            foreach (self::lines(str_replace(',', "\n", (string) $v['params'])) as $line) {
                if (preg_match('~^([a-z0-9_]+)\s*=\s*(.*)$~', $line, $m)) $p[$m[1]] = trim($m[2]);
            }
            $vlist[] = ['domain' => (string) $v['domain'], 'lang' => (string) $v['lang'], 'params' => $p,
                'html_head' => (string) $v['html_head'], 'html_body' => (string) $v['html_body'], 'js_accept' => (string) $v['js_accept']];
        }
        $csp = [];
        foreach (['script', 'connect', 'img', 'frame'] as $k) $csp[$k] = self::cspHosts((string) ($in['csp'][$k] ?? ''), $errors, 'csp_' . $k);
        $embedHosts = array_values(array_unique(array_map(fn($h) => strtolower((string) preg_replace(['~^https?://~i', '~/.*$~'], '', $h)), self::lines((string) ($in['embed_hosts'] ?? '')))));
        $hosts = array_values(array_intersect(array_keys($domains), array_map('strval', (array) ($in['hosts'] ?? []))));
        if (count($hosts) === count($domains)) $hosts = [];

        $data = [
            'skey' => $key, 'grp' => $grp, 'name' => mb_substr($name, 0, 190), 'provider' => trim((string) ($in['provider'] ?? '')), 'privacy_url' => $privacy,
            'description' => ['de' => trim((string) ($in['description_de'] ?? '')), 'en' => trim((string) ($in['description_en'] ?? ''))],
            'params' => $params, 'param_defs' => $defs,
            'html_head' => (string) ($in['html_head'] ?? ''), 'html_body' => (string) ($in['html_body'] ?? ''),
            'js_default' => (string) ($in['js_default'] ?? ''), 'js_accept' => (string) ($in['js_accept'] ?? ''), 'js_revoke' => (string) ($in['js_revoke'] ?? ''),
            'gcm' => array_values(array_intersect(Repository::GCM_SIGNALS, array_map('strval', (array) ($in['gcm'] ?? [])))),
            'embed_hosts' => $embedHosts, 'csp' => $csp, 'items' => $items['items'], 'events' => Events::normalize($events['events']), 'variants' => $vlist,
            'hosts' => $hosts, 'active' => !empty($in['active']),
        ];
        if (!$old) {
            $data += ['preset' => (string) ($in['preset'] ?? ''), 'sources' => [], 'verified' => '', 'note' => []];
            if ($data['preset'] !== '' && ($p = Presets::get($data['preset']))) {
                $data['sources'] = (array) ($p['sources'] ?? []);
                $data['verified'] = (string) ($p['verified'] ?? '');
                $data['note'] = (array) ($p['note'] ?? []);
            } else {
                $data['preset'] = '';
            }
        }
        $probe = $data + ($old ?? []) + ['preset' => ''];
        if ($data['active'] && !$errors && !Compiler::isComplete($probe)) {
            $errors['params'] = __('Es fehlen Angaben (offene Platzhalter im Code) – der Dienst kann erst aktiviert werden, wenn alles ausgefüllt ist.');
        }
        if ($errors) {
            return $this->page('service', ['title' => $name ?: __('Dienst'), 's' => $probe + ['id' => $id], 'domains' => $domains, 'errors' => $errors], 422);
        }
        $newId = Repository::save($id, $data);
        return $this->back('/admin/consent/service/' . $newId, 'success', __('Dienst gespeichert.'));
    }

    public function delete(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = Repository::find((int) $id) ?? throw new HttpException(404);
        Repository::delete($s['id']);
        return $this->back('/admin/consent', 'success', __('„{name}“ gelöscht.', ['name' => $s['name']]));
    }

    /** Auf Vorlage zurücksetzen: alles außer Schlüssel, Gruppe, Status, Domains, eingetragenen Werten, Ereignissen und Varianten */
    public function reset(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = Repository::find((int) $id) ?? throw new HttpException(404);
        $p = $s['preset'] ? Presets::toService($s['preset']) : null;
        if (!$p) return $this->back('/admin/consent/service/' . $s['id'], 'error', __('Zu diesem Dienst gibt es keine Vorlage.'));
        foreach (['skey', 'grp', 'active', 'hosts', 'params', 'events', 'variants', 'prio'] as $k) unset($p[$k]);
        Repository::save($s['id'], $p);
        return $this->back('/admin/consent/service/' . $s['id'], 'success', __('Auf die Vorlage zurückgesetzt.'));
    }

    // ------------------------------------------------------------------ Einstellungen

    public static function settingsFields(): array
    {
        return [
            ['type' => 'heading', 'label' => __('Darstellung')],
            ['name' => 'layout', 'label' => __('Form'), 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => ['box' => __('Box (Ecke, Seite bleibt bedienbar)'), 'bar' => __('Leiste (volle Breite)'), 'modal' => __('Dialog (mittig, Seite gesperrt)'), 'offcanvas' => __('Off-Canvas (Panel am Rand)')]],
            ['name' => 'position', 'label' => __('Position'), 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => ['bottom-left' => __('unten links'), 'bottom-right' => __('unten rechts'), 'top-left' => __('oben links'), 'top-right' => __('oben rechts')]],
            ['name' => 'theme', 'label' => __('Farbschema'), 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => ['site' => __('Wie die Website (Dunkelmodus des Kits)'), 'light' => __('Hell'), 'dark' => __('Dunkel'), 'auto' => __('Automatisch (System der Besucher)')]],
            ['name' => 'banner_groups', 'label' => __('Gruppen im Hinweis zeigen (nur Dialog/Off-Canvas; nichts vorausgewählt)'), 'type' => 'bool', 'width' => 'half'],
            ['name' => 'dismiss', 'label' => __('Schließen-Schaltfläche (×): schließt ohne Entscheidung, nichts wird geladen'), 'type' => 'bool'],
            ['name' => 'footer_link', 'label' => __('„Cookie-Einstellungen“ im Fußbereich der Website (Rechtliches)'), 'type' => 'bool'],
            ['name' => 'trigger', 'label' => __('Schwebende Schaltfläche zum erneuten Öffnen'), 'type' => 'bool',
                'help' => __('Lädt die Oberfläche auf jeder Seite (ca. 9 KB). Der Widerruf muss so einfach sein wie die Einwilligung – mindestens eines von beiden einschalten.')],
            ['type' => 'heading', 'label' => __('Rechtstexte')],
            ['name' => 'privacy', 'label' => __('Datenschutzerklärung'), 'type' => 'link', 'width' => 'half', 'help' => __('Leer = Seite aus den Einstellungen des Kits.')],
            ['name' => 'imprint', 'label' => __('Impressum'), 'type' => 'link', 'width' => 'half'],
            ['type' => 'heading', 'label' => __('Einwilligung und Protokoll')],
            ['name' => 'days', 'label' => __('Gültigkeit der Entscheidung (Tage, höchstens 400)'), 'type' => 'number', 'width' => 'half', 'step' => 1],
            ['name' => 'retention', 'label' => __('Aufbewahrung des Protokolls (Tage, 0 = nie löschen)'), 'type' => 'number', 'width' => 'half', 'step' => 1],
            ['name' => 'reload', 'label' => __('Seite nach Widerruf neu laden (bereits geladene Skripte lassen sich nur so sicher stoppen)'), 'type' => 'bool'],
            ['type' => 'heading', 'label' => __('Signale')],
            ['name' => 'gpc', 'label' => __('Global Privacy Control (Browser-Signal „Sec-GPC“)'), 'type' => 'select', 'required' => true,
                'options' => ['reject' => __('Als Ablehnung werten – kein Hinweis, optionale Dienste bleiben aus'), 'ask' => __('Trotzdem fragen (mit Anmerkung zum Signal)'), 'ignore' => __('Ignorieren')]],
            ['name' => 'gcm_redaction', 'label' => __('Google Consent Mode: ads_data_redaction (Klick-Kennungen entfernen, solange ad_storage abgelehnt ist)'), 'type' => 'bool'],
            ['name' => 'gcm_passthrough', 'label' => __('Google Consent Mode: url_passthrough'), 'type' => 'bool'],
            ['name' => 'gcm_wait', 'label' => __('Google Consent Mode: wait_for_update (ms)'), 'type' => 'number', 'width' => 'half', 'step' => 50],
        ];
    }

    public function settings(Request $r): Response
    {
        $this->guard($r);
        return $this->page('settings', ['title' => __('Einstellungen · Cookie-Einwilligung'), 'values' => Repository::settings()]);
    }

    public function saveSettings(Request $r): Response
    {
        $this->guard($r);
        [$v, $errors] = Fields::sanitize(self::settingsFields(), (array) ($r->post['f'] ?? []));
        $v['days'] = max(1, min(400, (int) ($v['days'] ?? 365)));
        $v['retention'] = max(0, (int) ($v['retention'] ?? 1095));
        $v['gcm_wait'] = max(0, min(10000, (int) ($v['gcm_wait'] ?? 500)));
        if ($errors) return $this->page('settings', ['title' => __('Einstellungen · Cookie-Einwilligung'), 'values' => $v + Repository::settings(), 'errors' => $errors], 422);
        Repository::saveSettings($v);
        return $this->back('/admin/consent/settings', 'success', __('Einstellungen gespeichert.'));
    }

    public function reask(Request $r): Response
    {
        $this->guard($r);
        Repository::bumpEpoch();
        return $this->back('/admin/consent/settings', 'success', __('Alle Besucher werden beim nächsten Aufruf erneut gefragt.'));
    }

    // ------------------------------------------------------------------ Design

    public function design(Request $r): Response
    {
        $this->guard($r);
        return $this->page('design', ['title' => __('Design · Cookie-Einwilligung'), 'values' => Design::values(), 'derived' => Design::derived(),
            'overrides' => Design::overrides(), 'checks' => Design::checks(), 'preview' => self::previewConfig()]);
    }

    /** Konfiguration für die Vorschau (echte Dienste, ohne Cookie/Protokoll) */
    private static function previewConfig(): array
    {
        $b = Compiler::build('main');
        $cfg = Consent::config($b);
        $cfg['preview'] = true;
        $cfg['trigger'] = false;
        if (!array_filter($b['services'], fn($c) => $c['grp'] !== 'necessary')) {
            // Ohne aktive Dienste: Beispiel zur Ansicht
            $cfg['groups'][] = ['key' => 'statistics', 'name' => lt('Statistik'), 'description' => lt('Diese Dienste erfassen, wie die Website genutzt wird, um sie verbessern zu können.'), 'required' => false,
                'services' => [['key' => 'example', 'h' => 'x', 'name' => __('Beispieldienst'), 'provider' => '', 'privacyUrl' => '', 'description' => '', 'items' => [], 'gcm' => [], 'embed' => ['example.org'], 'x' => false, 'head' => [], 'body' => [], 'acc' => false, 'rev' => false, 'v' => '', 'hosts' => []]]];
        }
        return $cfg;
    }

    public function saveDesign(Request $r): Response
    {
        $this->guard($r);
        if ($r->str('reset') === '1') {
            app()->settings->set('consent.design', []);
        } else {
            $derived = Design::derived();
            $in = Design::clean((array) ($r->post['v'] ?? []));
            // Nur Abweichungen vom abgeleiteten Standard speichern
            $save = array_filter($in, fn($v, $k) => (string) $v !== (string) ($derived[$k] ?? ''), ARRAY_FILTER_USE_BOTH);
            app()->settings->set('consent.design', $save);
        }
        Repository::changed();
        $fails = array_filter(Design::checks(), fn($c) => !$c['ok']);
        return $this->back('/admin/consent/design', $fails ? 'error' : 'success', $fails
            ? __('Gespeichert – {n} Kombinationen erreichen den Mindestkontrast (WCAG AA) nicht.', ['n' => count($fails)]) : __('Design gespeichert.'));
    }

    // ------------------------------------------------------------------ Protokoll

    private static function filters(Request $r): array
    {
        return ['id' => mb_substr($r->str('id'), 0, 40), 'action' => $r->str('action'), 'from' => $r->str('from'), 'to' => $r->str('to'), 'domain' => $r->str('domain')];
    }

    public function log(Request $r): Response
    {
        $this->guard($r);
        $f = self::filters($r);
        $page = max(1, (int) $r->str('page', '1'));
        return $this->page('log', ['title' => __('Protokoll · Cookie-Einwilligung'), 'filters' => $f, 'rows' => Log::list($f, 50, ($page - 1) * 50),
            'total' => Log::total($f), 'pageNo' => $page, 'stats' => Log::stats(30), 'domains' => Compiler::domains(), 'settings' => Repository::settings()]);
    }

    public function logCsv(Request $r): Response
    {
        $this->guard($r);
        return self::secure(new Response(Log::csv(self::filters($r)), 200, [
            'Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="consent-protokoll-' . site()->key . '-' . date('Y-m-d') . '.csv"']));
    }

    public function purge(Request $r): Response
    {
        $this->guard($r);
        $n = Log::purge();
        return $this->back('/admin/consent/log', 'success', __('{n} Protokolleinträge gelöscht (älter als die Aufbewahrungsfrist).', ['n' => $n]));
    }

    public function revision(Request $r, string $id): Response
    {
        $this->guard($r);
        $rev = Repository::findRevision((int) $id) ?? throw new HttpException(404);
        return $this->page('revision', ['title' => __('Stand #{id}', ['id' => $rev['id']]), 'rev' => $rev, 'domains' => Compiler::domains()]);
    }

    // ------------------------------------------------------------------ Eigene Vorlagen

    public function io(Request $r): Response
    {
        $this->guard($r);
        $report = app()->session->get('consent_import');
        app()->session->forget('consent_import');
        return $this->page('io', ['title' => __('Eigene Vorlagen · Cookie-Einwilligung'), 'files' => Presets::ownFiles(), 'services' => Repository::services(), 'report' => is_array($report) ? $report : null]);
    }

    public function import(Request $r): Response
    {
        $this->guard($r);
        $f = $r->files['file'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || ($f['size'] ?? 0) > 2 * 1024 * 1024) {
            return $this->back('/admin/consent/io', 'error', __('Bitte eine JSON-Datei (höchstens 2 MB) wählen.'));
        }
        try {
            $rep = Presets::import((string) file_get_contents((string) $f['tmp_name']), (string) ($f['name'] ?? 'vorlagen.json'), $r->str('overwrite') === '1');
        } catch (\InvalidArgumentException $e) {
            return $this->back('/admin/consent/io', 'error', $e->getMessage());
        }
        app()->session->flash('success', __('{n} Vorlagen importiert.', ['n' => count($rep['imported'])]) . ($rep['skipped'] ? ' ' . __('{n} übersprungen.', ['n' => count($rep['skipped'])]) : ''));
        app()->session->set('consent_import', $rep);
        return Response::redirect(url('/admin/consent/io'));
    }

    public function export(Request $r): Response
    {
        $this->guard($r);
        $ids = array_map('intval', (array) ($r->post['ids'] ?? []));
        $list = array_values(array_filter(Repository::services(), fn($s) => !$ids || in_array($s['id'], $ids, true)));
        return self::download(array_map([Presets::class, 'fromService'], $list), 'consent-vorlagen-' . site()->key . '-' . date('Y-m-d') . '.json');
    }

    public function exportOne(Request $r, string $id): Response
    {
        $this->guard($r);
        $s = Repository::find((int) $id) ?? throw new HttpException(404);
        return self::download([Presets::fromService($s)], 'consent-vorlage-' . $s['skey'] . '.json');
    }

    private static function download(array $services, string $name): Response
    {
        return self::secure(new Response((string) json_encode(['services' => $services], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200,
            ['Content-Type' => 'application/json; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . $name . '"']));
    }

    public function downloadTemplate(Request $r, string $name): Response
    {
        $this->guard($r);
        $p = Presets::ownPath($name) ?? throw new HttpException(404);
        return self::secure(new Response((string) file_get_contents($p), 200, ['Content-Type' => 'application/json; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . basename($p) . '"']));
    }

    public function deleteTemplate(Request $r, string $name): Response
    {
        $this->guard($r);
        Presets::deleteOwn($name);
        return $this->back('/admin/consent/io', 'success', __('Vorlagen-Datei gelöscht. Bereits angelegte Dienste bleiben bestehen.'));
    }
}

