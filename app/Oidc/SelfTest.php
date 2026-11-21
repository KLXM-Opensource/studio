<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Oidc;

use Core\Testing\Check;

/**
 * php bin/console oidc:selftest – JWK/JWT (RS256, EdDSA, abgewiesene Verfahren), vollständiger Ablauf gegen einen Test-Anbieter
 * im Prozess (Discovery, JWKS mit Schlüsselwechsel, Token-Endpunkt mit PKCE-Prüfung) und Fehlerfälle (state, nonce, iss, aud,
 * exp, iat, abgelaufener Code, falsche Signatur, at_hash) sowie die Zuordnung zu Konten (in einer Transaktion, danach verworfen).
 * Keine externen Dienste, alle Schlüssel werden hier erzeugt.
 */
final class SelfTest
{
    private const ISS = 'https://idp.example.org/oidc';

    /** Leere Zuordnung (für den Test-Anbieter) @return array<string, mixed> */
    private static function map(): array
    {
        return [];
    }

    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = Check::eq($ok, $fails);
        $throws = function (string $what, callable $fn, string $contains = '') use (&$ok, &$fails): void {
            try {
                $fn();
                $fails[] = "$what: keine Ausnahme";
            } catch (\Throwable $e) {
                if ($contains === '' || str_contains($e->getMessage(), $contains)) $ok++;
                else $fails[] = "$what: erwartet „{$contains}“, erhalten „{$e->getMessage()}“";
            }
        };

        // Logos des Anbieters (Core\Oidc\Logo) in einem eigenen Ordner – die echte Ablage bleibt unberührt
        $logoDir = sys_get_temp_dir() . '/klxm-oidc-logo-' . bin2hex(random_bytes(4));
        Logo::$dir = $logoDir;

        // ------------------------------------------------------------ Schlüssel
        $rsa = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $rsaJwk = Jwk::fromRsa(openssl_pkey_get_details($rsa)['key'], 'r1');
        $edKp = sodium_crypto_sign_keypair();
        $edSec = sodium_crypto_sign_secretkey($edKp);
        $edJwk = Jwk::fromEd25519(sodium_crypto_sign_publickey($edKp), 'e1');
        $eq('RSA-JWK → PEM lesbar', openssl_pkey_get_public((string) Jwk::rsaPem($rsaJwk)) !== false, true);
        $eq('Ed25519-JWK → 32 Byte', strlen((string) Jwk::ed25519($edJwk)), 32);
        $small = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $eq('RSA unter 2048 Bit abgewiesen', Jwk::rsaPem(Jwk::fromRsa(openssl_pkey_get_details($small)['key'])), null);
        // 2047 Bit (256 Byte, erstes Bit 0) und Exponent 1/gerade: abgewiesen
        $eq('RSA mit 2047 Bit abgewiesen', Jwk::rsaPem(['kty' => 'RSA', 'n' => Jwk::b64u("\x7f" . str_repeat("\xff", 255)), 'e' => 'AQAB']), null);
        $eq('RSA-Exponent 1 abgewiesen', Jwk::rsaPem(['n' => $rsaJwk['n'], 'e' => Jwk::b64u("\x01"), 'kty' => 'RSA']), null);
        $eq('RSA-Exponent gerade abgewiesen', Jwk::rsaPem(['n' => $rsaJwk['n'], 'e' => Jwk::b64u("\x01\x00\x00"), 'kty' => 'RSA']), null);
        // Abrufe nur für erlaubte Hosts: Prüfung vor jedem Abruf (ohne Netz, da vor der Namensauflösung)
        $throws('Fetch: fremder Host vor dem Abruf abgewiesen', fn() => \Core\Http\Fetch::get('https://fremd.example.net/x', ['hosts' => ['example.org']]), 'Fremder Host');
        // Selbstauskunft: nur bekannte Hosts werden zwischengespeichert (sonst Vergiftung über den Host-Header)
        $eq('Selbstauskunft: fremder Host gilt nicht als bekannt', \Core\InstanceInfo::known('angreifer-' . bin2hex(random_bytes(3)) . '.example.net'), false);
        $eq('Fingerabdruck RFC 7638 (Beispiel)', Jwk::thumbprint(['kty' => 'RSA', 'e' => 'AQAB', 'n' => '0vx7agoebGcQSuuPiLJXZptN9nndrQmbXEps2aiAFbWhM78LhWx4cbbfAAtVT86zwu1RK7aPFFxuhDR1L6tSoc_BJECPebWKRXjBZCiFV4n3oknjhMstn64tZ_2W-5JsGY4Hc5n9yBXArwl93lqt7_RN5w6Cf0h4QyQ5v-65YGjQR0_FDW2QvzqY368QQMicAtaSqzs8KJZgnYb9c7d0zgdAZHzu6qMQvRL5hajrn1n91CbOpbISD08qNLyrdkt-bFTWhAI4vMQFh6WeZu0fM4lFd2NcRwr3XPksINHaQ-G_xBniIqbw0Ls1jF44-csFCur-kEgU8awapJzKnqDKgw']), 'NzbLsXh8uDCcd-6MNwXF4W_7noWXFZAfHkxZsRGC9Xs');

        $jwks = ['keys' => [$rsaJwk, $edJwk]];
        $claims = ['iss' => self::ISS, 'sub' => 'u1', 'aud' => 'client-a', 'exp' => time() + 300, 'iat' => time()];
        $tRs = Jwt::sign($claims, 'RS256', $rsa, 'r1');
        $tEd = Jwt::sign($claims, 'EdDSA', $edSec, 'e1');
        $eq('RS256 gültig', Jwt::verify($tRs, $jwks)[1]['sub'], 'u1');
        $eq('EdDSA gültig', Jwt::verify($tEd, $jwks)[1]['sub'], 'u1');
        [$h, $p] = explode('.', $tRs);
        $none = Jwk::b64u('{"alg":"none","typ":"JWT"}') . '.' . $p . '.';
        $throws('alg none abgewiesen', fn() => Jwt::verify($none, $jwks), 'format');
        $none2 = Jwk::b64u('{"alg":"none","typ":"JWT"}') . '.' . $p . '.' . Jwk::b64u('x');
        $throws('alg none (mit Signatur) abgewiesen', fn() => Jwt::verify($none2, $jwks), 'alg');
        // HS256 mit dem öffentlichen Schlüssel als Geheimnis (Verwechslungsangriff)
        $hsIn = Jwk::b64u('{"alg":"HS256","typ":"JWT","kid":"r1"}') . '.' . $p;
        $hs = $hsIn . '.' . Jwk::b64u(hash_hmac('sha256', $hsIn, (string) Jwk::rsaPem($rsaJwk), true));
        $throws('HS256 abgewiesen', fn() => Jwt::verify($hs, $jwks), 'alg');
        $evil = Jwk::b64u((string) json_encode(['sub' => 'admin'] + $claims));
        $throws('Veränderter Inhalt abgewiesen', fn() => Jwt::verify($h . '.' . $evil . '.' . explode('.', $tRs)[2], $jwks), 'signature');
        $throws('Unbekannte kid', fn() => Jwt::verify(Jwt::sign($claims, 'RS256', $rsa, 'zz'), $jwks), 'unknown_kid');
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $throws('Falscher Schlüssel (gleiche kid)', fn() => Jwt::verify(Jwt::sign($claims, 'RS256', $other, 'r1'), $jwks), 'signature');
        $throws('crit abgewiesen', fn() => Jwt::verify(Jwt::sign($claims, 'RS256', $rsa, 'r1', ['crit' => ['exp']]), $jwks), 'crit');
        $throws('Nur erlaubte Verfahren', fn() => Jwt::verify($tEd, $jwks, ['RS256']), 'alg');
        $eq('at_hash RS256 (SHA-256)', Jwt::halfHash('abc', 'RS256'), Jwk::b64u(substr(hash('sha256', 'abc', true), 0, 16)));
        $eq('PKCE S256: 43 Zeichen base64url ohne Auffüllung', (bool) preg_match('~^[A-Za-z0-9_-]{43}$~', Client::challenge(Jwk::b64u(random_bytes(48)))), true);

        // ------------------------------------------------------------ Test-Anbieter im Prozess
        $idp = ['keys' => [$rsaJwk], 'signer' => [$rsa, 'r1', 'RS256'], 'codes' => self::map(), 'claims' => self::map(), 'issuer' => self::ISS, 'secret' => 'geheim-ä:/+'];
        Client::resetForTest();
        Client::$transport = function (string $method, string $url, array $headers, ?string $body) use (&$idp): array {
            $j = fn($d, $s = 200) => ['status' => $s, 'body' => (string) json_encode($d, JSON_UNESCAPED_SLASHES)];
            if ($method === 'GET' && $url === self::ISS . '/.well-known/openid-configuration') {
                return $j(['issuer' => $idp['issuer'], 'authorization_endpoint' => self::ISS . '/authorize', 'token_endpoint' => self::ISS . '/token',
                    'jwks_uri' => self::ISS . '/jwks', 'end_session_endpoint' => self::ISS . '/logout', 'code_challenge_methods_supported' => ['S256'],
                    'id_token_signing_alg_values_supported' => ['RS256', 'EdDSA']]);
            }
            if ($method === 'GET' && $url === self::ISS . '/jwks') return $j(['keys' => $idp['keys']]);
            if ($method === 'POST' && $url === self::ISS . '/token') {
                parse_str((string) $body, $f);
                $auth = '';
                foreach ($headers as $hh) if (stripos($hh, 'Authorization: Basic ') === 0) $auth = (string) base64_decode(substr($hh, 21));
                [$cid, $sec] = array_map('rawurldecode', explode(':', $auth, 2) + [1 => '']);
                if ($cid !== 'client-a' || $sec !== $idp['secret']) return $j(['error' => 'invalid_client'], 401);
                $c = $idp['codes'][$f['code'] ?? ''] ?? null;
                unset($idp['codes'][$f['code'] ?? '']);   // Code nur einmal
                if (!$c || $c['exp'] < Client::now() || $c['redirect'] !== ($f['redirect_uri'] ?? '') || !hash_equals($c['challenge'], Client::challenge((string) ($f['code_verifier'] ?? '')))) {
                    return $j(['error' => 'invalid_grant'], 400);
                }
                $at = 'at-' . bin2hex(random_bytes(8));
                [$key, $kid, $alg] = $idp['signer'];
                $cl = $idp['claims'] + ['iss' => $idp['issuer'], 'sub' => 'staff-1', 'aud' => 'client-a', 'exp' => Client::now() + 300, 'iat' => Client::now(),
                    'nonce' => $c['nonce'], 'email' => 'erika@example.org', 'email_verified' => true, 'name' => 'Erika Musterfrau', 'amr' => ['pwd', 'hwk'],
                    'at_hash' => Jwt::halfHash($at, $alg)];
                $cl = array_filter($cl, fn($v) => $v !== null);
                return $j(['access_token' => $at, 'token_type' => 'Bearer', 'expires_in' => 300, 'id_token' => Jwt::sign($cl, $alg, $key, $kid)]);
            }
            return $j(['error' => 'not_found'], 404);
        };
        $prov = array_replace(Providers::DEFAULTS, ['id' => 'main', 'enabled' => true, 'issuer' => self::ISS, 'client_id' => 'client-a', 'client_secret' => $idp['secret'],
            'role' => 'editor', 'site' => site()->key]);
        // Anmeldung beim Anbieter nachspielen: Code ausstellen → [offene Anmeldung, Code]
        $authorize = function (?array $p = null) use (&$idp, $prov): array {
            $url = Client::begin($p ?? $prov, ['next' => '/admin']);
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $code = bin2hex(random_bytes(12));
            $idp['codes'][$code] = ['challenge' => $q['code_challenge'], 'nonce' => $q['nonce'], 'redirect' => $q['redirect_uri'], 'exp' => Client::now() + 60, 'q' => $q];
            return [$q, $code];
        };
        try {
            [$q, $code] = $authorize();
            $eq('Anfrage: Code-Flow mit PKCE S256', [$q['response_type'], $q['code_challenge_method'], strlen($q['state']) >= 43, strlen($q['nonce']) >= 43],
                ['code', 'S256', true, true]);
            $pend = Client::takeState($q['state']);
            $res = Client::finish($prov, (array) $pend, $code);
            $eq('Ablauf: Anmeldung gültig', [$res['claims']['sub'], $res['alg']], ['staff-1', 'RS256']);
            $eq('state nur einmal verwendbar', Client::takeState($q['state']), null);
            $eq('Fremder state abgewiesen', Client::takeState(Jwk::b64u(random_bytes(32))), null);
            $throws('Code nur einmal einlösbar', fn() => Client::finish($prov, (array) $pend, $code), 'invalid_grant');

            [$q, $code] = $authorize();
            $pend = (array) Client::takeState($q['state']);
            Client::$now = time() + 120;
            $throws('Abgelaufener Code abgewiesen', fn() => Client::finish($prov, $pend, $code), 'invalid_grant');
            Client::$now = null;

            [$q, $code] = $authorize();
            $pend = (array) Client::takeState($q['state']);
            $throws('Falsches nonce abgewiesen', fn() => Client::finish($prov, ['n' => 'anderes-nonce'] + $pend, $code), 'nonce');
            [$q, $code] = $authorize();
            $pend = (array) Client::takeState($q['state']);
            $throws('Falscher code_verifier (PKCE) abgewiesen', fn() => Client::finish($prov, ['v' => Jwk::b64u(random_bytes(48))] + $pend, $code), 'invalid_grant');
            [$q, $code] = $authorize();
            $pend = (array) Client::takeState($q['state']);
            $throws('Falsches Client-Geheimnis abgewiesen', fn() => Client::finish(['client_secret' => 'falsch'] + $prov, $pend, $code), 'invalid_client');

            foreach ([['iss', ['iss' => 'https://boese.example.net']], ['aud', ['aud' => 'client-b']], ['azp', ['aud' => ['client-a', 'client-b']]],
                ['exp', ['exp' => time() - 3600]], ['iat', ['iat' => time() + 3600]], ['iat', ['iat' => time() - 3600]]] as [$why, $over]) {
                $idp['claims'] = $over;
                [$q, $code] = $authorize();
                $pend = (array) Client::takeState($q['state']);
                $throws("Claim $why abgewiesen (" . key($over) . ')', fn() => Client::finish($prov, $pend, $code), $why);
            }
            $idp['claims'] = ['at_hash' => 'falsch'];
            [$q, $code] = $authorize();
            $pend = (array) Client::takeState($q['state']);
            $throws('at_hash abgewiesen', fn() => Client::finish($prov, $pend, $code), 'at_hash');
            $idp['claims'] = [];

            // Falsche Signatur: Anbieter signiert mit fremdem Schlüssel unter bekannter kid
            $idp['signer'] = [$other, 'r1', 'RS256'];
            [$q, $code] = $authorize();
            $pend = (array) Client::takeState($q['state']);
            $throws('Falsche Signatur abgewiesen', fn() => Client::finish($prov, $pend, $code), 'signature');

            // Schlüsselwechsel: neue kid (Ed25519), JWKS wird einmal neu geladen
            $idp['keys'] = [$rsaJwk, $edJwk];
            $idp['signer'] = [$edSec, 'e1', 'EdDSA'];
            [$q, $code] = $authorize();
            $pend = (array) Client::takeState($q['state']);
            $eq('Schlüsselwechsel (neue kid, EdDSA)', Client::finish($prov, $pend, $code)['alg'], 'EdDSA');
            $idp['signer'] = [$rsa, 'r1', 'RS256'];

            // Discovery mit fremdem Issuer
            Client::resetForTest();
            $idp['issuer'] = 'https://anderer.example.net';
            $throws('Discovery mit fremdem Issuer abgewiesen', fn() => Client::discovery(self::ISS), 'Issuer');
            $idp['issuer'] = self::ISS;
            Client::resetForTest();
            $eq('Abmelden beim Anbieter: Adresse', str_starts_with((string) Client::logoutUrl($prov, 'x.y.z', 'https://example.org/admin/login'), self::ISS . '/logout?id_token_hint='), true);

            // ------------------------------------------------------------ Bedingungen und Konten
            $c = ['sub' => 's', 'email' => 'Erika@Example.org', 'email_verified' => true, 'amr' => ['pwd', 'otp']];
            $eq('E-Mail klein geschrieben', Accounts::admissible($prov, $c), 'erika@example.org');
            $throws('Ohne zweiten Faktor (amr) abgewiesen', fn() => Accounts::admissible($prov, ['amr' => ['pwd']] + $c), 'zweitem Faktor');
            $eq('Anbieter erzwingt zweiten Faktor (trust)', Accounts::admissible(['mfa' => 'trust'] + $prov, ['amr' => ['pwd']] + $c), 'erika@example.org');
            $throws('Claim fehlt', fn() => Accounts::admissible(['claim' => 'roles', 'claim_values' => ['admin']] + $prov, ['roles' => ['redaktion']] + $c), 'nicht freigegeben');
            $eq('Claim mit erlaubtem Wert', Accounts::admissible(['claim' => 'roles', 'claim_values' => ['admin']] + $prov, ['roles' => ['admin']] + $c), 'erika@example.org');
            // Rolle aus dem Claim „roles“ (Hub: admin | redaktion)
            $eq('Rolle: ohne Claim = Rolle des Anbieters', Accounts::roleFor(['role' => 'admin'] + $prov, $c), ['admin', false]);
            $eq('Rolle: admin aus dem Claim', Accounts::roleFor(['role' => 'admin'] + $prov, ['roles' => ['admin']] + $c), ['admin', true]);
            $eq('Rolle: redaktion → Redaktionsrolle (keine Administration)', Accounts::roleFor(['role' => 'admin'] + $prov, ['roles' => ['redaktion']] + $c), ['editor', true]);
            $eq('Rolle: redaktion bei Netzwerk-Anbieter → keine Netzwerk-Rolle', Accounts::roleFor(['role' => 'network', 'editor_role' => 'network'] + $prov, ['roles' => 'redaktion'] + $c), ['editor', true]);
            $throws('Rolle: unbekannter Wert abgewiesen', fn() => Accounts::roleFor(['role' => 'admin'] + $prov, ['roles' => ['superuser']] + $c), 'nicht freigegeben');
            $throws('Rolle: verschachtelter Wert abgewiesen', fn() => Accounts::roleFor(['role' => 'admin'] + $prov, ['roles' => [['admin']]] + $c), 'nicht freigegeben');
            $eq('Rolle: Auswertung abschaltbar', Accounts::roleFor(['role' => 'admin', 'role_claim' => ''] + $prov, ['roles' => ['redaktion']] + $c), ['admin', false]);
            $throws('Domain nicht erlaubt', fn() => Accounts::admissible(['domains' => ['example.net']] + $prov, $c), 'nicht zugelassen');
            $throws('E-Mail nicht bestätigt', fn() => Accounts::admissible($prov, ['email_verified' => false] + $c), 'nicht bestätigt');

            $db = app()->db;
            Accounts::ensureTable($db);
            $db->pdo->beginTransaction();
            try {
                $mail = 'oidc-selftest-' . bin2hex(random_bytes(3)) . '@example.org';
                $u = Accounts::resolve($prov, ['sub' => 'st-1', 'email' => $mail] + $c);
                $eq('Konto angelegt (Rolle, Kennzeichnung, ohne Passwort)', [$u['role'], $u['idp'], $u['password_hash']], ['editor', 'main', Accounts::NO_PASSWORD]);
                $eq('Zweite Anmeldung: dasselbe Konto', (int) Accounts::resolve($prov, ['sub' => 'st-1', 'email' => $mail] + $c)['id'], (int) $u['id']);
                $eq('Kein lokales Passwort möglich', password_verify('', Accounts::NO_PASSWORD), false);
                $local = 'oidc-local-' . bin2hex(random_bytes(3)) . '@example.org';
                $db->insert('users', ['email' => $local, 'name' => '', 'password_hash' => password_hash('x' . random_int(1, 9), PASSWORD_DEFAULT), 'role' => 'admin', 'created_at' => now()]);
                $throws('Lokales Konto mit gleicher E-Mail wird nicht übernommen', fn() => Accounts::resolve($prov, ['sub' => 'st-2', 'email' => $local] + $c), 'bereits ein Konto');
                $throws('Ohne Just-in-time kein neues Konto', fn() => Accounts::resolve(['jit' => false] + $prov, ['sub' => 'st-3', 'email' => 'neu-' . $mail] + $c), 'noch kein Zugang');
                $pre = Accounts::allow($prov, 'vorab-' . $mail);
                $pre2 = Accounts::allow($prov, 'vorab2-' . $mail);
                $cNoVer = $c;
                unset($cNoVer['email_verified']);
                $throws('Vorab freigegebenes Konto: ohne email_verified nicht verknüpft', fn() => Accounts::resolve(['jit' => false] + $prov, ['sub' => 'st-5', 'email' => 'vorab2-' . $mail] + $cNoVer), 'bereits ein Konto');
                $eq('Vorab freigegebenes Konto (2) bleibt unverknüpft', (int) $db->fetchValue('SELECT COUNT(*) FROM ' . Accounts::TABLE . ' WHERE user_id = ?', [$pre2]), 0);
                $eq('Vorab freigegebenes Konto wird verknüpft', (int) Accounts::resolve(['jit' => false] + $prov, ['sub' => 'st-4', 'email' => 'vorab-' . $mail] + $c)['id'], $pre);
                // Rolle folgt dem Claim bei jeder Anmeldung: admin → redaktion stuft herab und beendet Sitzungen (auth_ver)
                $pa = ['role' => 'admin'] + $prov;
                $ra = Accounts::resolve($pa, ['sub' => 'st-6', 'email' => 'rolle-' . $mail, 'roles' => ['admin']] + $c);
                $ver = (int) $db->fetchValue('SELECT auth_ver FROM users WHERE id = ?', [(int) $ra['id']]);
                $rr = Accounts::resolve($pa, ['sub' => 'st-6', 'email' => 'rolle-' . $mail, 'roles' => ['redaktion']] + $c);
                $eq('Herabstufen im Hub wirkt (Rolle, Sitzungen beendet)', [$ra['role'], $rr['role'], (int) $db->fetchValue('SELECT auth_ver FROM users WHERE id = ?', [(int) $ra['id']]) > $ver],
                    ['admin', 'editor', true]);
                Accounts::revoke($db, (int) $u['id']);
                $throws('Entzogenes Konto abgewiesen', fn() => Accounts::resolve(['jit' => false] + $prov, ['sub' => 'st-1', 'email' => $mail] + $c), 'gesperrt');
                $db->update('users', ['disabled' => 1], 'id = :id', ['id' => $pre]);
                $throws('Gesperrtes Konto abgewiesen', fn() => Accounts::resolve($prov, ['sub' => 'st-4', 'email' => 'vorab-' . $mail] + $c), 'gesperrt');
                $eq('Netzwerk-Rolle nur auf der Netzwerk-Website', Providers::effectiveRole('network', '__keine__'), 'admin');
            } finally {
                $db->pdo->rollBack();
            }
        } finally {
            Client::$transport = null;
            Client::$now = null;
            Client::resetForTest();
        }

        // ------------------------------------------------------------ Logo des Anbieters (logo_uri)
        try {
            self::logoTests($eq, $logoDir);
        } finally {
            Logo::$transport = null;
            Logo::$dir = null;
            foreach (glob($logoDir . '/*') ?: [] as $f) @unlink($f);
            @rmdir($logoDir);
        }
        return ['ok' => $ok, 'fails' => $fails];
    }

    /** Logo: Bereinigung, Größenlimit, nur einmal laden, nur lokal ausgeben (kein Hotlink), Ersatzsymbol */
    private static function logoTests(\Closure $eq, string $dir): void
    {
        $iss = 'https://hub.example.org';
        $src = 'https://hub.example.org/static/logo.svg';
        $calls = 0;
        $body = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="Hub" onload="alert(1)">'
            . '<title>Hub</title><style>body{display:none}</style><script>alert(2)</script>'
            . '<defs><linearGradient id="g"><stop offset="0" stop-color="#2F6FED"/></linearGradient></defs>'
            . '<circle cx="32" cy="32" r="20" fill="currentColor"/><rect width="10" height="10" fill="url(#g)"/>'
            . '<image href="https://tracker.example.net/p.png" width="1" height="1"/></svg>';
        Logo::$transport = function (string $url) use (&$calls, &$body): array {
            $calls++;
            return ['status' => 200, 'body' => $body];
        };
        $eq('Logo: geladen', Logo::sync($iss, ['logo_uri' => $src]), null);
        $svg = (string) (Logo::stored($iss)['data'] ?? '');
        $eq('Logo: Skript, on…-Attribut, <style>, <image> und title entfernt', [str_contains($svg, 'script'), str_contains($svg, 'onload'), str_contains($svg, '<style'), str_contains($svg, '<image'), str_contains($svg, '<title')], [false, false, false, false, false]);
        $eq('Logo: dekorativ (aria-hidden), currentColor bleibt', [str_contains($svg, 'aria-hidden="true"'), str_contains($svg, 'role='), str_contains($svg, 'currentColor')], [true, false, true]);
        $eq('Logo: ids mit Präfix samt Verweis', [str_contains($svg, 'id="oidc-logo-g"'), str_contains($svg, 'url(#oidc-logo-g)')], [true, true]);
        $html = Logo::html(['issuer' => $iss, 'site' => site()->key, 'logo_media' => 0]);
        $eq('Logo: Ausgabe lokal eingebettet, kein Verweis auf den Anbieter', [str_starts_with($html, '<svg'), str_contains($html, 'hub.example.org'), str_contains($html, 'tracker.example.net'), (bool) preg_match('~(href|src)="https?:~', $html)], [true, false, false, false]);
        Logo::sync($iss, ['logo_uri' => $src]);
        $eq('Logo: gleiche Adresse → nur einmal geladen', $calls, 1);
        Logo::sync($iss, ['logo_uri' => $src], true);
        $eq('Logo: Speichern lädt neu', $calls, 2);

        // Größenlimit: zu groß → kein Logo, Ersatzsymbol
        $body = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h10v10z"/>' . str_repeat('<!-- x -->', 7000) . '</svg>';
        $eq('Logo: über 64 KB abgewiesen', Logo::sync($iss, ['logo_uri' => $src . '?v=2']) !== null, true);
        $eq('Logo: nach Fehler keins gespeichert', Logo::stored($iss), null);
        $eq('Logo: ohne Logo neutrales Symbol', str_contains(Logo::html(['issuer' => $iss]), 'auth-alt__logo--icon'), true);
        $n = $calls;
        Logo::sync($iss, ['logo_uri' => $src . '?v=2']);
        $eq('Logo: fehlerhafte Adresse nicht bei jedem Abruf erneut geladen', $calls, $n);

        // Nur https, nur SVG/PNG
        $eq('Logo: http/ftp-Adresse abgewiesen', Logo::download($iss, 'ftp://hub.example.org/logo.svg') !== null, true);
        $body = '<html><body>kein Bild</body></html>';
        $eq('Logo: weder SVG noch PNG abgewiesen', Logo::sync($iss, ['logo_uri' => $src . '?v=3']) !== null, true);
        $body = 'GIF89a' . str_repeat("\0", 20);
        $eq('Logo: GIF abgewiesen', Logo::sync($iss, ['logo_uri' => $src . '?v=4']) !== null, true);

        // PNG: geprüft und als data:-Adresse ausgegeben
        if (function_exists('imagecreatetruecolor')) {
            $png = function (int $w, int $h): string {
                $im = imagecreatetruecolor($w, $h);
                ob_start();
                imagepng($im);
                return (string) ob_get_clean();
            };
            $body = $png(32, 32);
            $eq('Logo: PNG übernommen', [Logo::sync($iss, ['logo_uri' => 'https://hub.example.org/logo.png']), Logo::stored($iss)['type'] ?? null], [null, 'png']);
            $html = Logo::html(['issuer' => $iss]);
            $eq('Logo: PNG als data:-Adresse, ohne Verweis auf den Anbieter', [str_contains($html, 'src="data:image/png;base64,'), str_contains($html, 'hub.example.org'), str_contains($html, 'alt=""')], [true, false, true]);
            $body = $png(600, 20);
            $eq('Logo: PNG über 512 px abgewiesen', Logo::sync($iss, ['logo_uri' => 'https://hub.example.org/gross.png']) !== null, true);
            $body = "\x89PNG\r\n\x1a\n" . 'kaputt';
            $eq('Logo: beschädigtes PNG abgewiesen', Logo::sync($iss, ['logo_uri' => 'https://hub.example.org/kaputt.png']) !== null, true);
        }

        // Abruf scheitert → Anmeldung unberührt, kein Logo
        Logo::$transport = function (): array { throw new \RuntimeException('nicht erreichbar'); };
        $eq('Logo: Abruf scheitert → Fehlertext', Logo::sync($iss, ['logo_uri' => $src . '?v=5']), 'nicht erreichbar');
        // logo_uri entfällt → gespeichertes Logo und Vermerk weg
        Logo::sync($iss, ['logo_uri' => $src . '?v=5']);
        Logo::sync($iss, []);
        $eq('Logo: ohne logo_uri entfernt', [Logo::stored($iss), Logo::status($iss)], [null, null]);
        $eq('Logo: Ablage nur im Test-Ordner', Logo::dir(), $dir);
    }
}
