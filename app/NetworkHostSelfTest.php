<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Http\Request;
use Core\Monitoring\Signature;
use Core\Network\Network;
use Core\Testing\Check;

/**
 * php bin/console network-host:selftest [--site=<Netzwerk-Website>] – Netzwerk-Domain (Core\NetworkHost) und Selbstauskunft
 * (Core\InstanceInfo): Adressen mit/ohne Netzwerk-Domain (Verwaltung, OIDC, Mails, Einmal-Anmeldung), Weiterleitung von /admin
 * samt Ausnahmen, redirect_to_primary, Prüfung der Domain, Domainwechsel mit Ziel und Verfahren. Die Einstellung wird nur im
 * Speicher gesetzt (keine Änderung an config.local.php), Testdaten in der Datenbank werden zurückgerollt.
 */
final class NetworkHostSelfTest
{
    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = Check::eq($ok, $fails);
        $keepHost = NetworkHost::get();
        $keepReq = app()->request;
        $net = Network::isNetworkSite();
        try {
            // ---------------------------------------------------------- ohne Netzwerk-Domain: wie bisher
            NetworkHost::reset(null);
            $eq('ohne: admin_url_abs = absolute_url', admin_url_abs('/admin/x'), absolute_url('/admin/x'));
            $eq('ohne: nicht aktiv', NetworkHost::active(), false);
            $eq('ohne: keine Weiterleitung', NetworkHost::redirectTarget(self::req('GET', '/admin', 'www.example.org')), null);
            $eq('ohne: health leer', NetworkHost::health(), []);
            if (!$net) {
                // Andere Website eines Netzwerks: eigene Verwaltung bleibt, nur Links zur Netzwerk-Website nutzen die Netzwerk-Domain
                NetworkHost::reset('verwaltung.example.org');
                $eq('andere Website: nicht aktiv', NetworkHost::active(), false);
                $eq('andere Website: eigene Verwaltungsadresse', admin_url_abs('/admin/x'), absolute_url('/admin/x'));
                $eq('andere Website: keine Weiterleitung', NetworkHost::redirectTarget(self::req('GET', '/admin', 'www.example.org', ['HTTP_SEC_FETCH_DEST' => 'document'])), null);
                $eq('andere Website: Weg zur Netzwerk-Website über die Netzwerk-Domain', Network::siteLink(Network::siteKey(), '/admin/sso'), 'https://verwaltung.example.org' . url('/admin/sso'));
                return ['ok' => $ok, 'fails' => $fails];
            }

            // ---------------------------------------------------------- mit Netzwerk-Domain
            $nh = 'verwaltung.example.org';
            NetworkHost::reset($nh);
            $o = 'https://' . $nh;
            $eq('aktiv auf der Netzwerk-Website', NetworkHost::active(), true);
            $eq('nicht aktiv für andere Websites', NetworkHost::active('x-andere'), false);
            $eq('Ursprung https', NetworkHost::origin(), $o);
            $eq('admin_url_abs', admin_url_abs('/admin/system'), $o . url('/admin/system'));
            $eq('admin_url_abs Standard /admin', admin_url_abs(), $o . url('/admin'));
            $eq('Website-Adressen bleiben (absolute_url /)', str_starts_with(absolute_url('/'), $o), false);
            $eq('OIDC redirect_uri', Oidc\Providers::redirectUri(), $o . url('/admin/login/oidc/callback'));
            $eq('OIDC initiate_login_uri', Oidc\Providers::initiateUri(), $o . url('/admin/login/oidc'));
            $eq('Einmal-Anmeldung zur Netzwerk-Website (siteLink /admin/sso)', Network::siteLink(Network::siteKey(), '/admin/sso'), $o . url('/admin/sso'));
            $eq('siteLink Website-Pfad bleibt Hauptadresse', str_starts_with(Network::siteLink(Network::siteKey(), '/impressum'), $o), false);
            $eq('Mail: Einladung', str_starts_with(Invites::url(str_repeat('a', 43)), $o . url('/admin/einladung/')), true);
            $eq('Mail: E-Mail-Änderung', str_starts_with(EmailChange::url('confirm', str_repeat('a', 43)), $o . url('/admin/konto/email/')), true);
            $pr = PasswordReset::url(str_repeat('a', 43));
            $eq('Mail: Passwort vergessen', $pr === null || str_starts_with($pr, $o . url('/admin/passwort/')), true);
            $eq('Mail: Support auf dieser Website', Support\Support::urlOn(site()->key, '/admin/support/fragen/1'), $o . url('/admin/support/fragen/1'));
            $eq('Port in der Einstellung: lokal http', (function () { NetworkHost::reset('netz.localhost:8154'); $v = NetworkHost::origin(); NetworkHost::reset('verwaltung.example.org'); return $v; })(), 'http://netz.localhost:8154');
            $eq('is(): mit beliebigem Port, wenn ohne Port eingestellt', [NetworkHost::is($nh), NetworkHost::is($nh . ':443'), NetworkHost::is('VERWALTUNG.example.org'), NetworkHost::is('www.example.org')], [true, true, true, false]);

            // ---------------------------------------------------------- Weiterleitung von /admin auf anderen Domains
            $doc = ['HTTP_SEC_FETCH_DEST' => 'document', 'HTTP_SEC_FETCH_MODE' => 'navigate'];
            $to = fn(Request $r, bool $direct = false) => NetworkHost::redirectTarget($r, null, $direct);
            $eq('GET /admin/pages auf der Hauptdomain → Netzwerk-Domain', $to(self::req('GET', '/admin/pages', 'www.example.org', $doc)), $o . url('/admin/pages'));
            $eq('Abfrage bleibt erhalten', $to(self::req('GET', '/admin/pages', 'www.example.org', $doc, '/admin/pages?tab=x&y=1')), $o . url('/admin/pages') . '?tab=x&y=1');
            $eq('HEAD wie GET', $to(self::req('HEAD', '/admin', 'www.example.org', $doc)) !== null, true);
            $eq('auf der Netzwerk-Domain selbst: nie', $to(self::req('GET', '/admin', $nh, $doc)), null);
            $eq('POST: nie (Formulare, Werkzeugleiste)', $to(self::req('POST', '/admin/pages/1', 'www.example.org', $doc)), null);
            $eq('Website-Seiten: nie', $to(self::req('GET', '/kontakt', 'www.example.org', $doc)), null);
            $eq('Einmal-Anmeldung /admin/sso: Ausnahme', $to(self::req('GET', '/admin/sso', 'www.example.org', $doc)), null);
            $eq('Domainwechsel /admin/sso/domain: Ausnahme', $to(self::req('GET', '/admin/sso/domain', 'www.example.org', $doc)), null);
            $eq('signierte Abfrage /admin/network/media-usages: Ausnahme', $to(self::req('GET', '/admin/network/media-usages', 'www.example.org', $doc)), null);
            $eq('fetch()/XHR der Werkzeugleiste (Sec-Fetch-Dest empty): Ausnahme', $to(self::req('GET', '/admin/api/links', 'www.example.org', ['HTTP_SEC_FETCH_DEST' => 'empty'])), null);
            $eq('Rahmen (iframe, Vorschau): Ausnahme', $to(self::req('GET', '/admin/pages/1/vorschau', 'www.example.org', ['HTTP_SEC_FETCH_DEST' => 'iframe'])), null);
            $eq('Stylesheet: Ausnahme', $to(self::req('GET', '/admin/blocks/preview.css', 'www.example.org', ['HTTP_SEC_FETCH_DEST' => 'style'])), null);
            $eq('ohne Fetch-Metadaten, JSON erwartet: Ausnahme', $to(self::req('GET', '/admin/api/links', 'www.example.org', ['HTTP_ACCEPT' => 'application/json'])), null);
            $eq('ohne Fetch-Metadaten, XMLHttpRequest: Ausnahme', $to(self::req('GET', '/admin/api/links', 'www.example.org', ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_ACCEPT' => 'text/html'])), null);
            $eq('ohne Fetch-Metadaten, HTML: umleiten', $to(self::req('GET', '/admin', 'www.example.org', ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml'])) !== null, true);
            $eq('/admin direkt trotz eigener Verwaltungsadresse: nie (verriete sie)', $to(self::req('GET', '/admin', 'www.example.org', $doc), true), null);

            // ---------------------------------------------------------- redirect_to_primary lässt die Netzwerk-Domain aus
            $site = new Site(site()->key, ['hosts' => ['www.example.org', $nh, 'alt.example.org'], 'redirect_to_primary' => true]);
            $eq('redirect_to_primary: Netzwerk-Domain nie umleiten', Domains::redirectTarget($site, 'GET', $nh, '/admin', '/admin', true), null);
            $eq('redirect_to_primary: andere weitere Domain weiter', Domains::redirectTarget($site, 'GET', 'alt.example.org', '/x', '/x', true), 'https://www.example.org/x');

            // ---------------------------------------------------------- Prüfung der Domain
            $eq('ungültige Domain', NetworkHost::problem('https://exa mple') !== null, true);
            $served = NetworkHost::servedBy('nh-selftest-unbekannt.example.org');
            $eq('unbekannte Domain: nur über fallback_site = Netzwerk-Website', NetworkHost::problem('nh-selftest-unbekannt.example.org') === null, $served === Network::siteKey());
            foreach (Sites::all() as $k => $c) {
                if ($k === Network::siteKey()) continue;
                $h = (new Site($k, $c))->hosts()[0] ?? null;
                if ($h !== null) { $eq('Domain einer anderen Website abgelehnt (' . $k . ')', NetworkHost::problem($h) !== null, true); break; }
            }
            foreach ((new Site(site()->key, site()->cfg))->landingHosts() as $lh) { $eq('Landing-Domain abgelehnt', NetworkHost::problem($lh) !== null, true); break; }
            $eq('speichern lehnt ungültige Domain ab (ohne Schreiben)', NetworkHost::save('nicht gültig!', false) !== null, true);
            // Schreibweg: Ersetzen/Entfernen im return-Array lässt Kommentare stehen
            $src = "<?php\n// Kopf\nreturn [\n  'app_key' => 'x', // Schlüssel\n];\n";
            $set = (string) Sites::replaceTopLevel($src, NetworkHost::CONFIG, var_export($nh, true));
            $tmp = tempnam(sys_get_temp_dir(), 'nh') ?: '';
            file_put_contents($tmp, $set);
            $got = (static fn(string $__f) => require $__f)($tmp);
            @unlink($tmp);
            $eq('config: Eintrag ergänzt', is_array($got) ? ($got[NetworkHost::CONFIG] ?? null) : null, $nh);
            $eq('config: Kommentare bleiben', str_contains($set, '// Schlüssel') && str_contains($set, '// Kopf'), true);
            $eq('config: Eintrag entfernt', (string) Sites::removeTopLevel($set, NetworkHost::CONFIG, true), $src);

            // ---------------------------------------------------------- Domainwechsel mit Ziel (HostHandover)
            $eq('safePath: Verwaltung mit Abfrage', HostHandover::safePath('/admin/pages?tab=x'), '/admin/pages?tab=x');
            $eq('safePath: /admin/sso, /admin/login, fremde Hosts, ..', array_map([HostHandover::class, 'safePath'], ['/admin/sso', '/admin/login', '//evil.example', '/admin/../x', 'https://evil.example']), ['', '', '', '', '']);
            $eq('safePath: Seite der Website', HostHandover::safePath('/'), '/');
            $eq('Netzwerk-Domain ist Ziel des Domainwechsels', in_array($nh, HostHandover::targets(), true), true);
            $db = app()->db;
            $db->pdo->beginTransaction();
            try {
                $uid = (int) ($db->fetchValue('SELECT id FROM users ORDER BY id LIMIT 1') ?? 0);
                if ($uid) {
                    $t = HostHandover::issue($uid, $nh, 'UA-selftest', '/admin/pages?tab=x');
                    $res = HostHandover::consume($t, $nh, 'UA-selftest');
                    $eq('Domainwechsel: Konto und Ziel', is_array($res) ? [$res['uid'], $res['path']] : $res, [$uid, '/admin/pages?tab=x']);
                    $eq('Domainwechsel: nur einmal', is_string(HostHandover::consume($t, $nh, 'UA-selftest')), true);
                    $t2 = HostHandover::issue($uid, $nh, 'UA-selftest');
                    $eq('Domainwechsel: anderer Browser abgelehnt', is_string(HostHandover::consume($t2, $nh, 'UA-anders')), true);
                }
            } finally {
                $db->pdo->rollBack();
            }

            // ---------------------------------------------------------- Selbstauskunft /.well-known/klxm-studio
            $db->pdo->beginTransaction();
            try {
                app()->settings->set(Monitoring\Status::KEY, ['enabled' => true, 'keys' => [['label' => 'QA', 'key' => Signature::formatKey(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()))]]]);
                $info = InstanceInfo::build();
                $eq('Auskunft: Kennung', [$info['spec'] ?? null, $info['system'] ?? null], [InstanceInfo::SPEC, 'studio']);
                $eq('Auskunft: redirect_uri mit Netzwerk-Domain', $info['login']['redirect_uri'] ?? null, $o . url('/admin/login/oidc/callback'));
                $eq('Auskunft: initiate_login_uri', $info['login']['initiate_login_uri'] ?? null, $o . url('/admin/login/oidc'));
                $eq('Auskunft: post_logout_redirect_uri', $info['login']['post_logout_redirect_uri'] ?? null, $o . url('/admin/login'));
                $mine = array_values(array_filter((array) ($info['monitoring']['sites'] ?? []), fn($s) => $s['key'] === site()->key));
                $eq('Auskunft: Status-Adresse auf der Website (nicht Netzwerk-Domain)', isset($mine[0]) && str_ends_with($mine[0]['status_url'], '/api/monitoring/status') && !str_starts_with($mine[0]['status_url'], $o), true);
                $json = (string) json_encode($info);
                $eq('Auskunft: keine Versionen, keine Schlüssel', [str_contains($json, CMS_VERSION), str_contains($json, 'ed25519'), str_contains($json, PHP_VERSION)], [false, false, false]);
            } finally {
                $db->pdo->rollBack();
                app()->settings->forget();
            }
            $off = InstanceInfo::build();
            $eq('Auskunft: ohne OIDC und Überwachung null (404) – sonst Daten', $off === null || $off['spec'] === InstanceInfo::SPEC, true);
        } catch (\Throwable $e) {
            $fails[] = 'Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            NetworkHost::reset($keepHost === null ? null : $keepHost);
            NetworkHost::reset();
            app()->request = $keepReq;
        }
        return ['ok' => $ok, 'fails' => $fails];
    }

    private static function req(string $method, string $path, string $host, array $server = [], ?string $uri = null): Request
    {
        return new Request($method, $path, [], [], [], ['HTTP_HOST' => $host, 'REQUEST_URI' => $uri ?? $path, 'REQUEST_METHOD' => $method] + $server);
    }
}
