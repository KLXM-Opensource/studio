<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Network;

use Core\Http\Request;
use Core\Http\Router;
use Core\Permissions;
use Core\Sites;
use Core\Testing\Check;

/**
 * php bin/console network:selftest [--site=key] – Netzwerk-Bereich (Core\Network\Area): Umleitungen alter Adressen, Rücksprünge,
 * Routen der umgezogenen Seiten, Zugriff (Netzwerk-Konto vs. Administration einer Website), Systemmeldungen (Core\Network\SystemMail).
 * Mit mehreren Websites prüft er den Netzwerk-Fall, sonst die Einzelinstallation (alles bleibt am alten Platz). Angemeldete Konten
 * werden nur im Speicher vorgetäuscht – keine Änderung an Datenbank oder Sitzung.
 */
final class AreaSelfTest
{
    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = Check::eq($ok, $fails);
        $multi = Sites::multi();
        $netSite = Network::isNetworkSite();
        $router = new Router();
        (require ROOT . '/app/routes.php')($router);
        $req = fn(string $m, string $p, array $q = []) => new Request($m, $p, $q, [], [], ['HTTP_HOST' => 'example.org', 'REQUEST_METHOD' => $m]);
        $keep = self::who();
        try {
            // ---------------------------------------------------------- Routen: jede neue Adresse hat eine Seite
            foreach (Area::MOVED as $old => $new) {
                $eq("Route $new vorhanden", $router->match('GET', $new) !== null, true);
                $eq("Alte Adresse $old bleibt erreichbar (Einzelinstallation)", $router->match('GET', $old) !== null, true);
            }
            foreach (Area::PAGES as $p) $eq("Seite /admin/network/$p hat eine Route", $router->match('GET', '/admin/network/' . $p) !== null, true);
            foreach (Area::sectionKeys() as $s) $eq("Bereich $s bekannt", in_array($s, \Core\Http\Controllers\Admin\NetworkController::SECTIONS, true), true);
            $navKeys = [];
            foreach (Area::nav() as $g) foreach ($g['items'] as $it) $navKeys[] = $it[0];
            $eq('Seitenleiste ohne doppelte Punkte', count($navKeys), count(array_unique($navKeys)));

            // ---------------------------------------------------------- Weiterleitungen
            $eq('POST wird nie umgeleitet', Area::redirect($req('POST', '/admin/system/aufgaben')), null);
            $eq('Grundeinstellungen bleiben', Area::redirect($req('GET', '/admin/system')), null);
            $eq('Website-Integrität bleibt', Area::redirect($req('GET', '/admin/system/integritaet')), null);
            if ($multi) {
                $r = Area::redirect($req('GET', '/admin/system/kits', ['a' => 'b']));
                $eq('Kits: alte Adresse leitet weiter', $r !== null, true);
                $eq('Kits: Ziel', $r ? (string) parse_url($r[0], PHP_URL_PATH) : '', $netSite ? url('/admin/network/kits') : (string) parse_url(Network::siteLink(Network::siteKey(), '/admin/network/kits'), PHP_URL_PATH));
                $eq('Kits: Abfrage bleibt erhalten', $r ? str_ends_with($r[0], '?a=b') : false, true);
                $eq('Kits: dauerhaft (301) auf der Netzwerk-Website', $r[1] ?? 0, $netSite ? 301 : 302);
                $eq('Schriften: neuer Name', Area::path('/admin/system/fonts#installiert'), '/admin/network/schriften#installiert');
                $eq('Netzwerk-Seite auf der Netzwerk-Website: keine Umleitung', $netSite ? Area::redirect($req('GET', '/admin/network/aufgaben')) : null, null);
                if (!$netSite) $eq('Netzwerk-Seite auf anderer Website → Netzwerk-Website', Area::redirect($req('GET', '/admin/network/aufgaben'))[1] ?? 0, 302);
            } else {
                $eq('Einzelinstallation: keine Umleitung alter Adressen', Area::redirect($req('GET', '/admin/system/kits')), null);
                $eq('Einzelinstallation: neue Adresse → alter Platz', Area::redirect($req('GET', '/admin/network/kits'))[0] ?? '', url('/admin/system/kits'));
                $eq('Einzelinstallation: Rücksprung unverändert', Area::path('/admin/system/aufgaben#t-x'), '/admin/system/aufgaben#t-x');
                $eq('Einzelinstallation: kein Netzwerk-Bereich', Area::active(), false);
            }

            // ---------------------------------------------------------- Zugriff: Administration einer Website ohne Netzwerk-Rolle
            self::as(['id' => -1, 'email' => 'admin@example.org', 'role' => 'admin', 'network_uid' => null]);
            $eq('Website-Admin: kein Netzwerk-Bereich', Area::here('/admin/network/kits'), false);
            $eq('Website-Admin: volle Rechte der Website', can('system.manage') && can('pages.edit') && can('users.manage'), true);
            $eq('Website-Admin: Installationsseiten ' . ($multi ? 'gesperrt' : 'erlaubt'), Permissions::installationAdmin(), !$multi);
            $eq('Website-Admin: Reiter-Rücksprünge bleiben auf der Website', Area::path('/admin/system#ki-use'), '/admin/system#ki-use');

            // ---------------------------------------------------------- Netzwerk-Konto: alles wie bisher + Netzwerk-Bereich
            $netUser = $netSite ? ['id' => -2, 'email' => 'netz@example.org', 'role' => 'network', 'network_uid' => null]
                : ['id' => -2, 'email' => 'netz@example.org', 'role' => 'network', 'network_uid' => 1];
            self::as($netUser);
            $eq('Netzwerk-Konto: volle Rechte der Website', can('system.manage') && can('pages.edit') && can('users.manage') && can('data.schema'), true);
            $eq('Netzwerk-Konto: Installationsseiten erlaubt', Permissions::installationAdmin(), true);
            $eq('Netzwerk-Konto: Netzwerk-Bereich', Area::here('/admin/network/kits'), $multi && $netSite);
            $eq('Netzwerk-Konto: Website-Seiten sind kein Netzwerk-Bereich', Area::here('/admin/system'), false);
            if ($multi) {
                $eq('Netzwerk-Konto: KI-Rücksprung → Netzwerk', Area::path('/admin/system#ki-p-x'), '/admin/network/ki#ki-p-x');
                $eq('Netzwerk-Konto: Geteilte Medien → Netzwerk', Area::path('/admin/system#pools'), '/admin/network/geteilt#pools');
                $eq('Netzwerk-Konto: Geteilte Daten bleiben auf der Website', Area::path('/admin/system#shared'), '/admin/system#shared');
                $eq('Netzwerk-Konto: Agentur-Zugang ' . ($netSite ? 'im Netzwerk' : 'Website-eigen'), Area::path('/admin/users/sicherheit#oidc'), $netSite ? '/admin/network/anmeldung#oidc' : '/admin/users/sicherheit#oidc');
            }

            // ---------------------------------------------------------- Systemmeldungen
            $eq('Adressen lesen', SystemMail::parse(' A@example.org, b@example.org;a@example.org kaputt'), [['a@example.org', 'b@example.org'], ['kaputt']]);
            $eq('Ungültige Adresse wird abgelehnt', SystemMail::save('kein-mail') !== null, true);
            $rec = SystemMail::recipients(['alt@example.org']);
            $eq('Empfänger nie leer, solange es einen Rückfall gibt', $rec !== [], true);
            if (!SystemMail::configured() && !$multi) $eq('Einzelinstallation: bisherige Empfänger', $rec, ['alt@example.org']);
            if ($multi) {
                $mine = array_map('strtolower', array_column(app()->db->fetchAll("SELECT email FROM users WHERE role = 'network' OR network_uid IS NOT NULL"), 'email'));
                $db = \Core\Integrity::recipients('db');
                $eq('Integrität (Datenbank): keine Netzwerk-/Schatten-Konten, außer als Rückfall', array_values(array_intersect($db, $mine)) === [] || $db === SystemMail::recipients(), true);
            }
        } finally {
            self::as($keep);
        }
        return ['ok' => $ok, 'fails' => $fails];
    }

    /** Angemeldetes Konto nur im Speicher setzen (kein Login, keine Sitzung) */
    private static function as(?array $u): void
    {
        $auth = app()->auth;
        \Closure::bind(function () use ($u) {
            $this->loaded = true;
            $this->user = $u;
            $this->role = $u ? (Permissions::role((string) $u['role']) ?? Permissions::role('admin')) : null;
        }, $auth, \Core\Auth::class)();
    }

    private static function who(): ?array
    {
        return app()->auth->user();
    }
}
