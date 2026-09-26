<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Kalkulation;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Theme;

/**
 * Verwaltung → Kalkulation (Recht kalkulation.manage, Funktion „kalkulation“): Liste, Editor (JSON-Speichern mit
 * Stand-Prüfung), Angebotsansicht zum Drucken, CSV, Leistungskatalog, Einstellungen, Protokoll.
 * Nur Verwaltung: keine Website-Routen, keine REST-/MCP-Schnittstelle, kein Suchindex, kein Seiten-Cache (no-store).
 */
final class AdminController extends \Core\Http\Controllers\Admin\AdminController
{
    private const BASE = '/admin/kalkulation';

    private function guard(Request $r): array
    {
        if (!Kalkulation::on()) throw new HttpException(404);
        $u = $this->auth($r, Kalkulation::PERM);
        if (!Repo::ready()) throw new HttpException(503, __('Die Tabellen der Erweiterung werden noch angelegt. Bitte neu laden.'));
        return $u;
    }

    private function page(string $view, array $vars = [], int $status = 200): Response
    {
        $vars += ['errors' => [], 'tab' => $view];
        $vars['flash'] = app()->session->takeFlash();
        $vars['css'] = [];
        $vars['user'] = app()->auth->user();
        $dir = dirname(__DIR__) . '/views/';
        $content = Theme::capture($dir . '_nav.php', $vars) . Theme::capture($dir . $view . '.php', $vars);
        $html = Theme::capture(ROOT . '/app/Admin/views/layout.php', $vars + ['content' => $content, 'view' => 'calculator/' . $view,   // Bereich = Menü-Schlüssel „calculator“ (Symbol + aktiver Menüpunkt)
            'title' => $vars['title'] ?? __('Kalkulation')]);
        return self::secure(new Response($html, $status));
    }

    private function calc(string $id): array
    {
        return Repo::find((int) $id) ?? throw new HttpException(404, __('Kalkulation nicht gefunden.'));
    }

    // ------------------------------------------------------------------ Liste

    public function index(Request $r): Response
    {
        $this->guard($r);
        $q = mb_strtolower($r->str('q'));
        $status = $r->str('status');
        $rows = [];
        foreach (Repo::all() as $c) {
            if ($status === '' ? $c['status'] === 'archived' : ($status !== 'all' && $c['status'] !== $status)) continue;
            if ($q !== '' && !str_contains(mb_strtolower($c['number'] . ' ' . $c['title'] . ' ' . $c['customer'] . ' ' . $c['project']), $q)) continue;
            $c['_totals'] = Engine::run($c)['totals'];
            $rows[] = $c;
        }
        $counts = array_fill_keys(array_keys(Kalkulation::statuses()), 0);
        foreach (app()->db->fetchAll('SELECT status, COUNT(*) AS n FROM kalk_calcs GROUP BY status') as $c) $counts[$c['status']] = (int) $c['n'];
        return $this->page('list', ['title' => __('Kalkulationen'), 'rows' => $rows, 'q' => $r->str('q'), 'status' => $status, 'counts' => $counts]);
    }

    public function create(Request $r): Response
    {
        $u = $this->guard($r);
        $id = Repo::create((int) $u['id'], ['title' => $r->str('title')]);
        return Response::redirect(url(self::BASE . '/' . $id));
    }

    public function duplicate(Request $r, string $id): Response
    {
        $u = $this->guard($r);
        $new = Repo::duplicate((int) $this->calc($id)['id'], (int) $u['id']);
        return $this->back(self::BASE . '/' . $new, 'success', __('Kopie angelegt – Sie bearbeiten jetzt die Kopie.'));
    }

    public function delete(Request $r, string $id): Response
    {
        $u = $this->guard($r);
        $c = $this->calc($id);
        if ($r->str('confirm') !== $c['number']) {
            return $this->back(self::BASE . '/' . $c['id'], 'error', __('Zum Löschen bitte die Nummer {number} eintippen.', ['number' => $c['number']]));
        }
        Repo::delete($c['id'], (int) $u['id']);
        return $this->back(self::BASE, 'success', __('Kalkulation {number} gelöscht.', ['number' => $c['number']]));
    }

    // ------------------------------------------------------------------ Editor

    public function edit(Request $r, string $id): Response
    {
        $this->guard($r);
        $c = $this->calc($id);
        $s = Repo::settings();
        $boot = [
            'calc' => $c, 'catalog' => Repo::catalog()['services'], 'statuses' => array_map(fn($x) => $x[0], Kalkulation::statuses()),
            'units' => Kalkulation::units(), 'currencies' => Kalkulation::currencies(), 'defaults' => Repo::paramsFromSettings($s),
            'user' => Kalkulation::userName($c['updated_by']),
            'urls' => ['save' => url(self::BASE . '/' . $c['id'] . '/speichern'), 'snapshot' => url(self::BASE . '/' . $c['id'] . '/sichern'),
                'offer' => url(self::BASE . '/' . $c['id'] . '/angebot'), 'list' => url(self::BASE)],
        ];
        return $this->page('editor', ['title' => ($c['title'] !== '' ? $c['title'] : $c['number']) . ' · ' . __('Kalkulation'), 'c' => $c, 'boot' => $boot,
            'versions' => Repo::versions($c['id']), 'log' => Repo::logs(12, $c['id'])]);
    }

    /** JSON: {rev, force?, calc: {...}} → {ok, rev, updated_at, user, totals} bzw. 409 bei fremdem Zwischenstand */
    public function save(Request $r, string $id): Response
    {
        $u = $this->guard($r);
        $c = $this->calc($id);
        $in = (array) ($r->post['calc'] ?? []);
        try {
            $new = Repo::save($c['id'], $in, (int) ($r->post['rev'] ?? 0), (int) $u['id'], !empty($r->post['force']));
        } catch (Conflict $e) {
            return Response::json(['ok' => false, 'conflict' => true, 'error' => $e->getMessage(), 'rev' => $e->current['rev']], 409);
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        return Response::json(['ok' => true, 'rev' => $new['rev'], 'updated_at' => Kalkulation::dateTime($new['updated_at']),
            'user' => Kalkulation::userName($new['updated_by']), 'number' => $new['number'], 'totals' => Engine::run($new)['totals']]);
    }

    public function snapshot(Request $r, string $id): Response
    {
        $u = $this->guard($r);
        $c = $this->calc($id);
        Repo::snapshot($c['id'], mb_substr($r->str('note'), 0, 191), (int) $u['id']);
        return $r->wantsJson() ? Response::json(['ok' => true]) : $this->back(self::BASE . '/' . $c['id'] . '#kx-versions', 'success', __('Stand gesichert.'));
    }

    public function restore(Request $r, string $id, string $vid): Response
    {
        $u = $this->guard($r);
        $c = $this->calc($id);
        try {
            Repo::restore($c['id'], (int) $vid, (int) $u['id']);
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            return $this->back(self::BASE . '/' . $c['id'], 'error', $e->getMessage());
        }
        return $this->back(self::BASE . '/' . $c['id'], 'success', __('Stand wiederhergestellt. Der vorherige Stand ist als „vor Wiederherstellung“ gesichert.'));
    }

    // ------------------------------------------------------------------ Angebot und Export

    /** Druckansicht (A4) der Kundensicht – ohne interne Kosten; „Als PDF speichern“ über den Druckdialog des Browsers */
    public function offer(Request $r, string $id): Response
    {
        $this->guard($r);
        $c = $this->calc($id);
        $html = Theme::capture(dirname(__DIR__) . '/views/offer.php', ['c' => $c, 'run' => Engine::run($c), 'sender' => self::sender(), 'settings' => Repo::settings()]);
        return self::secure(new Response($html));
    }

    /** Absender für den Angebotskopf: eigene Angabe aus den Einstellungen, sonst die zentralen Angaben der Website */
    private static function sender(): array
    {
        $own = trim((string) Repo::settings()['sender']);
        $logo = null;
        try {
            $lid = (int) (setting('logo') ?? 0);
            if ($lid && ($m = \Core\Media::find($lid))) $logo = \Core\Media::url($m, 480);
        } catch (\Throwable) {
        }
        $str = fn(string $k) => trim(strip_tags((string) (setting($k) ?? '')));
        $lines = $own !== '' ? array_values(array_filter(array_map('trim', explode("\n", $own)))) : array_values(array_filter([
            $str('street'), trim($str('zip') . ' ' . $str('city')), $str('phone') !== '' ? __('Telefon') . ' ' . $str('phone') : '', $str('email'),
        ]));
        $name = $own !== '' ? array_shift($lines) : site_name();
        return ['name' => (string) $name, 'lines' => $lines, 'logo' => $logo];
    }

    /** CSV der Positionen (Semikolon, UTF-8 mit BOM, deutsche Zahlen); ?ansicht=kunde lässt interne Spalten weg */
    public function csv(Request $r, string $id): Response
    {
        $u = $this->guard($r);
        $c = $this->calc($id);
        $internal = $r->str('ansicht') !== 'kunde';
        $run = Engine::run($c);
        $p = $c['params'];
        $cell = function (mixed $v): string {
            $v = (string) $v;
            if ($v !== '' && str_contains('=+-@', $v[0]) && !preg_match('~^-?[\d.,]+$~', $v)) $v = "'" . $v;   // CSV-Injektion
            return '"' . str_replace('"', '""', $v) . '"';
        };
        $head = [__('Pos.'), __('Art'), __('Gruppe'), __('Bezeichnung'), __('Beschreibung'), __('Menge'), __('Einheit'), __('Einzelpreis netto'), __('Rabatt %'), __('Gesamt netto'), __('Hinweis')];
        if ($internal) array_push($head, __('Preisbasis'), __('Std. bzw. Festpreis je Einheit'), __('Fremdkosten je Einheit'), __('Puffer'), __('Fremdkosten gesamt'), __('Stunden gesamt'));
        $out = [implode(';', array_map($cell, $head))];
        $n = 0;
        // Reihenfolge wie im Angebot: erst einmalige, dann monatliche Positionen (gleiche Positionsnummern)
        $ordered = array_merge(array_filter($c['positions'], fn($x) => $x['kind'] !== 'monthly'), array_filter($c['positions'], fn($x) => $x['kind'] === 'monthly'));
        foreach ($ordered as $pos) {
            $l = $run['lines'][$pos['id']];
            $n++;
            $flag = $pos['alternative'] ? __('Alternative') : ($pos['optional'] ? __('optional') : '');
            $row = [$n, $pos['kind'] === 'monthly' ? __('monatlich') : __('einmalig'), $pos['group'], $pos['name'], $pos['desc'], Num::input($pos['qty']), $pos['unit'],
                Num::fmt($l['unit_price']), Num::input($pos['discount']), Num::fmt($l['total']), $flag];
            if ($internal) array_push($row, $pos['basis'] === 'fixed' ? __('Festpreis') : (string) ($p['rates'][$pos['basis']]['label'] ?? $pos['basis']),
                Num::input($pos['amount']), Num::input($pos['cost']), $pos['buffer'] ? __('ja') : '', Num::fmt($l['cost_total']), Num::input($l['hours_total']));
            $out[] = implode(';', array_map($cell, $row));
        }
        Repo::log('export', $c['id'], $internal ? 'CSV intern' : 'CSV Kunde', (int) $u['id']);
        $file = preg_replace('~[^A-Za-z0-9._-]+~', '-', $c['number'] ?: ('kalkulation-' . $c['id'])) . ($internal ? '-intern' : '') . '.csv';
        return self::secure(new Response("\u{FEFF}" . implode("\r\n", $out) . "\r\n", 200, [
            'Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . $file . '"']));
    }

    // ------------------------------------------------------------------ Leistungskatalog

    public function catalog(Request $r): Response
    {
        $this->guard($r);
        return $this->page('catalog', ['title' => __('Leistungskatalog'), 'services' => Repo::catalog()['services'], 'rates' => Repo::paramsFromSettings(Repo::settings())['rates']]);
    }

    public function serviceNew(Request $r): Response
    {
        $u = $this->guard($r);
        $c = Repo::catalog();
        $sid = 's' . $c['next'];
        $c['services'][] = ['id' => $sid, 'name' => $r->str('name') ?: __('Neue Leistung'), 'desc' => '', 'notes' => '', 'text' => '', 'items' => []];
        $c['next']++;
        Repo::saveCatalog($c, (int) $u['id'], 'neu: ' . $sid);
        return Response::redirect(url(self::BASE . '/katalog/' . $sid));
    }

    public function service(Request $r, string $sid): Response
    {
        $this->guard($r);
        $s = $this->findService($sid);
        return $this->page('service', ['title' => $s['name'], 's' => $s, 'rates' => Repo::paramsFromSettings(Repo::settings())['rates']]);
    }

    private function findService(string $sid): array
    {
        foreach (Repo::catalog()['services'] as $s) if ($s['id'] === $sid) return $s;
        throw new HttpException(404, __('Leistung nicht gefunden.'));
    }

    public function serviceSave(Request $r, string $sid): Response
    {
        $u = $this->guard($r);
        $c = Repo::catalog();
        $idx = array_search($sid, array_column($c['services'], 'id'), true);
        if ($idx === false) throw new HttpException(404);
        $errors = [];
        $rates = Repo::paramsFromSettings(Repo::settings())['rates'];
        $num = function (string $field, mixed $v) use (&$errors): ?float {
            if (Num::invalid($v)) { $errors[$field] = __('Bitte eine Zahl eingeben (z. B. 12,5).'); return null; }
            return Num::parse($v);
        };
        $basis = fn(mixed $v) => (string) $v === 'fixed' || isset($rates[(string) $v]) ? (string) $v : '';
        $items = [];
        foreach (array_values((array) ($r->post['items'] ?? [])) as $i => $it) {
            if (!is_array($it) || !empty($it['delete'])) continue;
            $name = mb_substr(trim((string) ($it['name'] ?? '')), 0, 200);
            $hasNum = array_filter([$it['once_amount'] ?? '', $it['once_cost'] ?? '', $it['monthly_amount'] ?? '', $it['monthly_cost'] ?? ''], fn($v) => trim((string) $v) !== '');
            if ($name === '' && !$hasNum && trim((string) ($it['desc'] ?? '')) === '') continue;   // leere Zeile
            if ($name === '') $errors["items.$i.name"] = __('Bitte einen Namen eingeben.');
            $id = preg_match('~^i\d+$~', (string) ($it['id'] ?? '')) ? (string) $it['id'] : 'i' . $c['next']++;
            $items[] = ['id' => $id, 'name' => $name, 'desc' => mb_substr(trim(str_replace("\r", '', (string) ($it['desc'] ?? ''))), 0, 2000),
                'bill' => in_array($it['bill'] ?? '', ['once', 'monthly', 'both'], true) ? $it['bill'] : 'once',
                'unit' => mb_substr(trim((string) ($it['unit'] ?? '')), 0, 40),
                'once_basis' => $basis($it['once_basis'] ?? ''), 'once_amount' => $num("items.$i.once_amount", $it['once_amount'] ?? ''),
                'once_cost' => $num("items.$i.once_cost", $it['once_cost'] ?? ''), 'buffer' => !empty($it['buffer']),
                'monthly_basis' => $basis($it['monthly_basis'] ?? ''), 'monthly_amount' => $num("items.$i.monthly_amount", $it['monthly_amount'] ?? ''),
                'monthly_cost' => $num("items.$i.monthly_cost", $it['monthly_cost'] ?? ''), 'example' => !empty($it['example'])];
        }
        $s = ['id' => $sid, 'name' => mb_substr($r->str('name'), 0, 120), 'desc' => mb_substr(str_replace("\r", '', $r->str('desc')), 0, 2000),
            'notes' => mb_substr(str_replace("\r", '', $r->str('notes')), 0, 4000), 'text' => mb_substr(str_replace("\r", '', $r->str('text')), 0, 4000), 'items' => $items];
        if ($s['name'] === '') $errors['name'] = __('Bitte einen Namen eingeben.');
        if ($errors) {
            return $this->page('service', ['title' => $s['name'] ?: __('Leistung'), 's' => $s, 'rates' => $rates, 'errors' => $errors, 'raw' => $r->post], 422);
        }
        $c['services'][$idx] = $s;
        Repo::saveCatalog($c, (int) $u['id'], $s['name']);
        return $this->back(self::BASE . '/katalog/' . $sid, 'success', __('„{name}“ gespeichert.', ['name' => $s['name']]));
    }

    public function serviceDelete(Request $r, string $sid): Response
    {
        $u = $this->guard($r);
        $c = Repo::catalog();
        $s = $this->findService($sid);
        $c['services'] = array_values(array_filter($c['services'], fn($x) => $x['id'] !== $sid));
        Repo::saveCatalog($c, (int) $u['id'], 'gelöscht: ' . $s['name']);
        return $this->back(self::BASE . '/katalog', 'success', __('„{name}“ aus dem Katalog entfernt. Bestehende Kalkulationen behalten ihre Positionen.', ['name' => $s['name']]));
    }

    public function serviceMove(Request $r, string $sid): Response
    {
        $u = $this->guard($r);
        $c = Repo::catalog();
        $i = array_search($sid, array_column($c['services'], 'id'), true);
        $j = $r->str('dir') === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($c['services'][$j])) {
            [$c['services'][$i], $c['services'][$j]] = [$c['services'][$j], $c['services'][$i]];
            Repo::saveCatalog($c, (int) $u['id'], 'Reihenfolge');
        }
        return $this->back(self::BASE . '/katalog#s-' . $sid);
    }

    // ------------------------------------------------------------------ Einstellungen

    public function settings(Request $r): Response
    {
        $this->guard($r);
        return $this->page('settings', ['title' => __('Einstellungen · Kalkulation'), 's' => Repo::settings(), 'log' => Repo::logs(40),
            'appKey' => strlen((string) app()->config->get('app_key', '')) >= 32]);
    }

    public function settingsSave(Request $r): Response
    {
        $u = $this->guard($r);
        $old = Repo::settings();
        $errors = [];
        $num = function (string $field, mixed $v, ?float $min = null, ?float $max = null) use (&$errors): ?float {
            if (Num::invalid($v)) { $errors[$field] = __('Bitte eine Zahl eingeben (z. B. 12,5).'); return null; }
            $f = Num::parse($v);
            if ($f !== null && (($min !== null && $f < $min) || ($max !== null && $f > $max))) { $errors[$field] = __('Wert außerhalb des erlaubten Bereichs.'); return null; }
            return $f;
        };
        $rates = [];
        $used = [];
        foreach (array_values((array) ($r->post['rates'] ?? [])) as $i => $rt) {
            if (!is_array($rt) || !empty($rt['delete'])) continue;
            $label = mb_substr(trim((string) ($rt['label'] ?? '')), 0, 80);
            $key = (string) ($rt['key'] ?? '');
            if ($label === '' && trim((string) ($rt['rate'] ?? '')) === '') continue;
            if ($label === '') $errors["rates.$i.label"] = __('Bitte eine Bezeichnung eingeben.');
            if (!preg_match('~^[a-z0-9_-]{1,30}$~', $key)) {
                $key = trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $label) ?: 'satz')), '-') ?: 'satz';
                $key = substr($key, 0, 24);
            }
            for ($k = $key, $n = 2; isset($used[$k]); $n++) $k = $key . '-' . $n;
            $used[$k] = true;
            $rates[] = ['key' => $k, 'label' => $label, 'rate' => $num("rates.$i.rate", $rt['rate'] ?? '', 0)];
        }
        if (!$rates) $errors['rates'] = __('Mindestens ein Stundensatz ist nötig.');
        $keys = array_column($rates, 'key');
        $s = [
            'rates' => $rates,
            'rate_once' => in_array($r->str('rate_once'), $keys, true) ? $r->str('rate_once') : ($keys[0] ?? ''),
            'rate_monthly' => in_array($r->str('rate_monthly'), $keys, true) ? $r->str('rate_monthly') : ($keys[0] ?? ''),
            'markup' => $num('markup', $r->str('markup'), -100, 1000), 'buffer' => $num('buffer', $r->str('buffer'), 0, 1000),
            'vat' => $num('vat', $r->str('vat'), 0, 100) ?? 0.0,
            'currency' => array_key_exists($r->str('currency'), Kalkulation::currencies()) ? $r->str('currency') : 'EUR',
            'round_step' => in_array($r->str('round_step'), ['0', '0.1', '1', '5', '10', '50'], true) ? (float) $r->str('round_step') : 0.0,
            'round_mode' => $r->str('round_mode') === 'up' ? 'up' : 'nearest',
            'cost_rate' => $num('cost_rate', $r->str('cost_rate'), 0),
            'number_prefix' => mb_substr($r->str('number_prefix'), 0, 30),
            'validity_days' => max(0, min(365, (int) $r->str('validity_days'))),
            'sender' => mb_substr(str_replace("\r", '', $r->str('sender')), 0, 600),
        ];
        foreach (['text_intro', 'text_payment', 'text_validity', 'text_term', 'text_closing'] as $k) $s[$k] = mb_substr(str_replace("\r", '', $r->str($k)), 0, 4000);
        if ($errors) {
            return $this->page('settings', ['title' => __('Einstellungen · Kalkulation'), 's' => $s + $old, 'errors' => $errors, 'raw' => $r->post, 'log' => Repo::logs(40),
                'appKey' => strlen((string) app()->config->get('app_key', '')) >= 32], 422);
        }
        Repo::saveSettings($s + $old, (int) $u['id']);
        return $this->back(self::BASE . '/einstellungen', 'success', __('Einstellungen gespeichert. Sie gelten für neue Kalkulationen; bestehende übernehmen sie im Editor über „Grundlage aus Einstellungen laden“.'));
    }

    public function encryption(Request $r): Response
    {
        $u = $this->guard($r);
        $on = $r->str('on') === '1';
        try {
            Repo::setEncryption($on, (int) $u['id']);
        } catch (\Throwable $e) {
            return $this->back(self::BASE . '/einstellungen#kx-crypt', 'error', $e->getMessage());
        }
        return $this->back(self::BASE . '/einstellungen#kx-crypt', 'success', $on ? __('Kalkulationen, Katalog und Einstellungen werden jetzt verschlüsselt gespeichert.')
            : __('Verschlüsselung ausgeschaltet – Inhalte liegen wieder im Klartext in der Datenbank.'));
    }
}
