<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Data\Subscriptions;
use Core\Data\Tables;
use Core\Http\Request;
use Core\Http\Response;
use Core\Lang;

/**
 * Abonnieren per App (Core\Data\Subscriptions):
 *   GET /kalender/{tabelle}/abo/{token}.ics   Kalender mit Abo-Token (Umfang des Tokens)
 *   GET /vcard/{tabelle}/abo/{token}.vcf      Kontakte mit Abo-Token
 *   GET /vcard/{tabelle}.vcf                  öffentliche Kontakte-Datei (nur freigegebene Kontakte und Angaben)
 * Fehler antworten ohne Fehlerseite und ohne 404-Protokoll (der Token steht im Pfad); ?lang=en wählt die Sprache.
 */
final class SubscriptionController
{
    public function calendar(Request $r, string $handle, #[\SensitiveParameter] string $token): Response
    {
        return $this->serve($r, $handle, $token, 'calendar');
    }

    public function contacts(Request $r, string $handle, #[\SensitiveParameter] string $token): Response
    {
        return $this->serve($r, $handle, $token, 'contacts');
    }

    public function contactsPublic(Request $r, string $handle): Response
    {
        $this->lang($r);
        $t = Tables::findContent($handle);
        if (!$t || !Subscriptions::publicContacts($t) || (app()->settings->get('sys.maintenance') && !app()->auth->check())) return Subscriptions::error(404);
        return Subscriptions::response(Subscriptions::contactsBody($t, 'public'), 'vcf', $t['handle'] . '.vcf', false);
    }

    private function serve(Request $r, string $handle, #[\SensitiveParameter] string $token, string $kind): Response
    {
        $this->lang($r);
        $res = Subscriptions::verify($token, $handle);
        if ($res['status'] !== 200) return Subscriptions::error($res['status']);
        $t = $res['table'];
        if (Subscriptions::kind($t) !== $kind) return Subscriptions::error(404);
        $scope = (string) $res['token']['scope'];
        return $kind === 'calendar'
            ? Subscriptions::response(Subscriptions::calendarBody($t, $scope), 'ics', $t['handle'] . '.ics', true)
            : Subscriptions::response(Subscriptions::contactsBody($t, $scope), 'vcf', $t['handle'] . '.vcf', true);
    }

    private function lang(Request $r): void
    {
        $lang = strtolower((string) ($r->query['lang'] ?? ''));
        if ($lang !== '' && Lang::valid($lang)) app()->lang = $lang;
    }
}
