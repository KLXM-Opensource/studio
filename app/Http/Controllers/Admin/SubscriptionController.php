<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Data\Subscriptions;
use Core\Data\Tables;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Daten → Tabelle → Abonnieren (Core\Data\Subscriptions): öffentliche Adressen (webcal bzw. .vcf), Abo-Tokens anlegen und widerrufen,
 * QR-Code und Anleitung je App. Recht: Einträge der Tabelle bearbeiten (data.edit); Tokens nur mit eingeschalteter Funktion.
 */
final class SubscriptionController extends AdminController
{
    private function table(Request $r, string $handle): array
    {
        $this->auth($r, 'data.edit', $handle);
        $t = Tables::find($handle) ?? throw new HttpException(404);
        if (Subscriptions::kind($t) === null) throw new HttpException(404);
        return $t;
    }

    public function index(Request $r, string $handle): Response
    {
        return $this->page($this->table($r, $handle));
    }

    public function store(Request $r, string $handle): Response
    {
        $t = $this->table($r, $handle);
        if (!Subscriptions::enabled()) throw new HttpException(403, __('Die Funktion „Abonnieren mit Token“ ist für diese Website ausgeschaltet.'));
        $name = trim(strip_tags($r->str('name')));
        $scope = $r->str('scope') === 'internal' ? 'internal' : 'public';
        $expires = $r->str('expires');
        $errors = [];
        if ($name === '') $errors['name'] = __('Bitte einen Namen angeben, z. B. „iPhone Vorstand“ oder „Outlook Geschäftsstelle“.');
        elseif (mb_strlen($name) > Subscriptions::NAME_MAX) $errors['name'] = __('Höchstens {n} Zeichen.', ['n' => Subscriptions::NAME_MAX]);
        if ($expires !== '' && (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $expires) || $expires < date('Y-m-d'))) $errors['expires'] = __('Bitte ein Datum ab heute wählen oder leer lassen.');
        if ($errors) return $this->page($t, null, $errors, ['name' => $name, 'scope' => $scope, 'expires' => $expires], 422);
        $new = Subscriptions::create($t, $name, $scope, (int) (app()->auth->user()['id'] ?? 0) ?: null, $expires ?: null);
        return $this->page($t, $new + ['name' => $name, 'scope' => $scope]);
    }

    public function revoke(Request $r, string $handle, string $id): Response
    {
        $t = $this->table($r, $handle);
        $ok = Subscriptions::revoke((string) $t['handle'], (int) $id);
        return $this->back('/admin/data/' . $t['handle'] . '/abonnieren', $ok ? 'success' : 'error',
            $ok ? __('Abo widerrufen. Apps mit dieser Adresse erhalten ab sofort nichts mehr.') : __('Dieses Abo gibt es nicht mehr.'));
    }

    /** Seite mit Bereichsnavigation „Daten“; ein neuer Token steht nur in dieser einen Antwort (nie in Sitzung oder Protokoll) */
    private function page(array $t, ?array $new = null, array $errors = [], array $old = [], int $status = 200): Response
    {
        $res = $this->view('data/subscribe', [
            't' => $t, 'kind' => Subscriptions::kind($t), 'tokens' => Subscriptions::forTable((string) $t['handle']), 'new' => $new,
            'errors' => $errors, 'old' => $old,
            'drill' => \Core\Theme::capture(ROOT . '/app/Admin/views/data/_nav.php', ['cur' => 'subscribe', 'active' => $t]), 'drillTitle' => __('Daten'),
        ], $status);
        return self::secure($res)->header('Referrer-Policy', 'no-referrer');
    }
}
