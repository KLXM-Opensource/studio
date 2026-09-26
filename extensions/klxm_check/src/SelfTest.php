<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * Selbsttest (php bin/console check:selftest --site=…): IP-Filter, Domain-Prüfung, Punycode, SPF-Parser und Lookup-Zählung
 * (mit Test-DNS, ohne Netz), SPF-Optimierung, DMARC-Parser, Namensprüfung von Zertifikaten, Weiterleitungsziele.
 * --online zusätzlich: SSRF-Sperren mit echter Namensauflösung (localtest.me → 127.0.0.1 u. a.) und eine SPF-Prüfung.
 * Und check:run für Prüfungen auf der Kommandozeile.
 */
final class SelfTest
{
    private static int $ok = 0;
    private static array $fail = [];

    private static function expect(string $name, bool $cond, string $info = ''): void
    {
        if ($cond) {
            self::$ok++;
        } else {
            self::$fail[] = $name . ($info !== '' ? ' → ' . $info : '');
        }
    }

    /** Wirft der Aufruf eine CheckException? */
    private static function throws(callable $fn): bool
    {
        try {
            $fn();
            return false;
        } catch (CheckException) {
            return true;
        }
    }

    public static function run(array $args): int
    {
        self::$ok = 0;
        self::$fail = [];

        // --- IP-Filter
        foreach (['8.8.8.8', '1.1.1.1', '172.15.0.1', '172.32.0.1', '100.128.0.1', '193.0.0.1', '2a00:1450:4001:80b::200e', '2606:4700::1111', '2002:808:808::1'] as $ip) {
            self::expect("öffentlich: $ip", IpGuard::isPublic($ip));
        }
        foreach (['127.0.0.1', '127.8.8.8', '10.0.0.1', '172.16.5.4', '172.31.255.255', '192.168.1.1', '100.64.0.1', '100.127.255.254', '169.254.169.254', '0.0.0.0',
            '255.255.255.255', '224.0.0.1', '240.0.0.1', '198.18.0.1', '198.19.255.1', '192.0.2.1', '198.51.100.7', '203.0.113.9', '192.0.0.8', '::1', '::', '::ffff:127.0.0.1',
            '::ffff:8.8.8.8', 'fe80::1', 'fc00::1', 'fd12:3456::1', 'ff02::1', 'fec0::1', '64:ff9b::7f00:1', '64:ff9b:1::1', '100::1', '2001:db8::1', '2001::1', '2001:2::1',
            '3fff::1', '2002:7f00:1::1', '2002:c0a8:101::1', 'abc', '', '1.2.3', '[::1]'] as $ip) {
            self::expect("gesperrt: $ip", !IpGuard::isPublic($ip));
        }

        // --- Domain-Prüfung
        $norm = ['Example.COM' => 'example.com', 'https://www.Example.com/pfad?x=1#y' => 'www.example.com', 'info@example.com' => 'example.com',
            'example.com.' => 'example.com', 'bücher.de' => 'xn--bcher-kva.de', 'MÜNCHEN.de' => 'xn--mnchen-3ya.de', 'xn--bcher-kva.de' => 'xn--bcher-kva.de',
            'mail.example.co.uk' => 'mail.example.co.uk'];
        foreach ($norm as $in => $want) {
            try {
                $got = Domain::normalize($in);
            } catch (CheckException $e) {
                $got = 'Fehler: ' . $e->getMessage();
            }
            self::expect("normalize($in)", $got === $want, $got);
        }
        foreach (['', 'localhost', 'foo.localhost', 'drucker.local', 'x.internal', 'foo.test', 'a.invalid', '1.0.0.127.in-addr.arpa', '127.0.0.1', '10.0.0.1', '[::1]', '::1',
            'example.com:8080', 'a..b', '-a.com', 'a-.com', 'x', 'exa mple.com', 'ex_ample.com', str_repeat('a', 64) . '.com', str_repeat('a.', 140) . 'com',
            'http://user@127.0.0.1/', 'http://[::1]:80/', '0x7f000001', '2130706433', 'ab--cd.com', "exa\x00mple.com", 'example.123'] as $bad) {
            self::expect('abgelehnt: ' . json_encode($bad), self::throws(fn() => Domain::normalize($bad)));
        }
        self::expect('Punycode münchen', Punycode::label('münchen') === 'xn--mnchen-3ya', Punycode::label('münchen'));
        self::expect('Punycode bücher', Punycode::label('bücher') === 'xn--bcher-kva', Punycode::label('bücher'));
        self::expect('Punycode 例え', Punycode::label('例え') === 'xn--r8jz45g', Punycode::label('例え'));
        self::expect('orgDomain co.uk', Domain::orgDomain('a.b.example.co.uk') === 'example.co.uk');
        self::expect('orgDomain de', Domain::orgDomain('mail.example.de') === 'example.de');

        // --- SPF-Parser
        $p = Spf::parse('v=spf1 ip4:192.0.2.0/24 ip6:2001:db8::/32 a:mail.example.com/28//64 mx include:_spf.google.com -exists:%{i}.x.example ~all');
        self::expect('SPF: 7 Ausdrücke', count($p['terms']) === 7 && !$p['errors'], json_encode($p['errors']));
        self::expect('SPF: a mit CIDR', ($p['terms'][2]['value'] ?? '') === 'mail.example.com' && ($p['terms'][2]['c4'] ?? null) === 28 && ($p['terms'][2]['c6'] ?? null) === 64);
        self::expect('SPF: Qualifier', ($p['terms'][5]['q'] ?? '') === '-' && ($p['terms'][5]['macro'] ?? false) === true);
        self::expect('SPF: unbekannter Mechanismus', (bool) Spf::parse('v=spf1 foo:bar -all')['errors']);
        self::expect('SPF: ungültige IPv4', (bool) Spf::parse('v=spf1 ip4:300.1.1.1 -all')['errors']);
        self::expect('SPF: CIDR zu groß', (bool) Spf::parse('v=spf1 ip4:1.2.3.4/33 -all')['errors']);
        self::expect('SPF: ohne v=spf1', (bool) Spf::parse('ip4:1.2.3.4 -all')['errors']);
        self::expect('SPF: include ohne Ziel', (bool) Spf::parse('v=spf1 include -all')['errors']);
        self::expect('SPF: redirect-Modifikator', (Spf::parse('v=spf1 redirect=_spf.example.com')['terms'][0]['kind'] ?? '') === 'mod');

        // --- Lookup-Zählung mit Test-DNS
        $dns = new FakeDns([
            'TXT example.net' => ['v=spf1 a mx include:a.example.net include:b.example.net ~all'],
            'TXT a.example.net' => ['v=spf1 include:c.example.net ip4:192.0.2.1 -all'],
            'TXT b.example.net' => ['v=spf1 exists:%{i}.x.example.net -all'],
            'TXT c.example.net' => ['v=spf1 a:host.example.net -all'],
            'A host.example.net' => ['198.51.100.10'], 'A example.net' => ['198.51.100.1'], 'MX example.net' => [[10, 'mx.example.net']], 'A mx.example.net' => ['198.51.100.2'],
            'TXT loop.example.net' => ['v=spf1 include:loop.example.net -all'],
            'TXT void.example.net' => ['v=spf1 a:gone1.example.net a:gone2.example.net a:gone3.example.net include:nospf.example.net -all'],
            'TXT nospf.example.net' => ['kein spf'],
            'TXT two.example.net' => ['v=spf1 -all', 'v=spf1 ~all'],
        ] + self::longChain());
        $w = new SpfWalk();
        Spf::walk($dns, 'example.net', null, $w);
        self::expect('Lookups gezählt (7)', $w->lookups === 7, (string) $w->lookups);
        self::expect('Keine Fehler im gültigen Eintrag', !$w->errors, json_encode($w->errors, JSON_UNESCAPED_UNICODE));
        self::expect('all erkannt', $w->all === '~');
        $w = new SpfWalk();
        Spf::walk($dns, 'l0.example.net', null, $w);
        self::expect('Mehr als 10 Lookups erkannt', $w->lookups > Spf::MAX_LOOKUPS, (string) $w->lookups);
        $w = new SpfWalk();
        Spf::walk($dns, 'loop.example.net', null, $w);
        self::expect('Schleife erkannt', (bool) array_filter($w->errors, fn($e) => str_contains($e, 'loop.example.net')));
        $w = new SpfWalk();
        Spf::walk($dns, 'void.example.net', null, $w);
        self::expect('Void lookups gezählt (4)', $w->voids === 4, (string) $w->voids);
        self::expect('include ohne SPF = Fehler', (bool) array_filter($w->errors, fn($e) => str_contains($e, 'nospf.example.net')));
        $res = Spf::check('two.example.net', $dns)->toArray();
        self::expect('Zwei SPF-Einträge = Fehler', $res['status'] === 'error');
        $res = Spf::check('none.example.net', $dns)->toArray();
        self::expect('Kein SPF = Fehler', $res['status'] === 'error' && ($res['data']['has'] ?? true) === false);

        // --- Optimierung
        $o = Spf::optimize('v=spf1 a a:example.com mx ip4:192.0.2.1 ip4:192.0.2.0/24 ip4:192.0.2.0/24 ptr +all', 'example.com');
        self::expect('Optimierung', $o['record'] === 'v=spf1 ip4:192.0.2.0/24 a mx ~all', $o['record']);
        $o = Spf::optimize('v=spf1 include:x.example ~all', null);
        self::expect('Optimierung: nichts zu tun', $o['record'] === 'v=spf1 include:x.example ~all' && !$o['changes'], $o['record']);
        $o = Spf::optimize('v=spf1 a mx ip4:198.51.100.0/24', 'example.net', $dns);
        self::expect('Optimierung: a/mx durch ip4 abgedeckt', $o['record'] === 'v=spf1 ip4:198.51.100.0/24 ~all', $o['record']);

        // --- DMARC
        $d = Dmarc::parse('v=DMARC1; p=reject; rua=mailto:dmarc@example.com,mailto:x@report.example!10m; pct=100; adkim=s; fo=1:d');
        self::expect('DMARC gültig', !$d['errors'] && $d['tags']['p'] === 'reject' && count($d['rua']) === 2, json_encode($d['errors'], JSON_UNESCAPED_UNICODE));
        foreach (['v=DMARC1; p=blah', 'p=none; v=DMARC1', 'v=DMARC1; p=none; rua=dmarc@example.com', 'v=DMARC1; p=none; pct=150', 'v=DMARC1; p=none; fo=2',
            'v=DMARC1; rua=mailto:a@example.com', 'v=DMARC1; p=none; adkim=x', 'v=DMARC1; p=none; sp=all'] as $bad) {
            self::expect('DMARC-Fehler: ' . $bad, (bool) Dmarc::parse($bad)['errors']);
        }
        self::expect('DMARC-Warnung: unbekanntes Tag', (bool) Dmarc::parse('v=DMARC1; p=none; foo=bar')['warnings']);
        self::expect('DMARC erkannt', Dmarc::isDmarc('v=DMARC1; p=none') && !Dmarc::isDmarc('v=spf1 -all'));

        // --- DKIM
        self::expect('DKIM widerrufen', Dkim::parseKey('v=DKIM1; k=rsa; p=')['revoked'] === true);
        $key = @openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key) {
            $pem = openssl_pkey_get_details($key)['key'];
            $b64 = preg_replace('~-----[^-]+-----|\s~', '', $pem);
            $k = Dkim::parseKey('v=DKIM1; k=rsa; t=y; p=' . $b64);
            self::expect('DKIM RSA-Bits', $k['bits'] === 1024 && $k['testing'] === true, (string) $k['bits']);
        }
        self::expect('DKIM-Selektor gültig', Dkim::validSelector('selector1') && Dkim::validSelector('s1.mail') && !Dkim::validSelector('a/b') && !Dkim::validSelector('../x'));

        // --- Zertifikatsnamen
        self::expect('Wildcard passt', Tls::hostMatches('www.example.com', ['*.example.com']));
        self::expect('Wildcard nicht für Apex', !Tls::hostMatches('example.com', ['*.example.com']));
        self::expect('Wildcard nur eine Ebene', !Tls::hostMatches('a.b.example.com', ['*.example.com']));
        self::expect('CN nur ohne SAN', !Tls::hostMatches('a.example.com', ['b.example.com'], 'a.example.com') && Tls::hostMatches('a.example.com', [], 'a.example.com'));

        // --- Weiterleitungsziele
        self::expect('URL relativ', Web::resolveUrl('https://example.com/a/b', 'c') === 'https://example.com/a/c');
        self::expect('URL absolut-pfad', Web::resolveUrl('https://example.com/a/b', '/x') === 'https://example.com/x');
        self::expect('URL protokoll-relativ', Web::resolveUrl('http://example.com/', '//www.example.com/') === 'http://www.example.com/');
        self::expect('Ports fest', IpGuard::portAllowed(443) && !IpGuard::portAllowed(22) && !IpGuard::portAllowed(8080) && !IpGuard::portAllowed(6379));

        // --- SSRF: Namen, die auf interne Adressen zeigen (Test-DNS, ohne Netz)
        $evil = new FakeDns(['A evil.example.net' => ['127.0.0.1'], 'A mixed.example.net' => ['93.184.215.14', '10.0.0.5'], 'AAAA six.example.net' => ['::ffff:127.0.0.1'],
            'A meta.example.net' => ['169.254.169.254'], 'AAAA ula.example.net' => ['fd00::1'], 'A cgnat.example.net' => ['100.64.1.1']]);
        foreach (['evil', 'mixed', 'six', 'meta', 'ula', 'cgnat'] as $h) {
            self::expect("SSRF (Test-DNS) gesperrt: $h", self::throws(fn() => IpGuard::resolve($h . '.example.net', $evil)));
            self::expect("Website-Abruf (Test-DNS) gesperrt: $h", self::throws(fn() => Web::fetch('https://' . $h . '.example.net/', $evil)));
        }
        self::expect('TLS-Checker (Test-DNS) gesperrt', self::throws(fn() => Tls::check('evil.example.net', 'https', $evil)));
        self::expect('DNS-Anzeige blendet interne Adressen aus', IpGuard::show('10.1.2.3') !== '10.1.2.3' && IpGuard::show('8.8.8.8') === '8.8.8.8');

        // --- Online: echte Namensauflösung, SSRF-Sperren
        if (in_array('--online', $args, true)) {
            $real = new Dns(20);
            foreach (['localtest.me', 'localhost.klxm.invalid', '127.0.0.1.nip.io', '10.0.0.1.nip.io', '169.254.169.254.nip.io'] as $h) {
                self::expect("SSRF gesperrt: $h", self::throws(fn() => IpGuard::resolve($h, $real)));
            }
            foreach (['http://127.0.0.1/', 'http://localhost/', 'http://[::1]/', 'http://10.0.0.1/', 'http://localtest.me/', 'https://example.com:8443/', 'ftp://example.com/', 'http://user:pw@example.com/', 'gopher://example.com/'] as $u) {
                self::expect("SSRF-Abruf gesperrt: $u", self::throws(fn() => Web::fetch($u, $real)));
            }
            self::expect('TLS zu localtest.me gesperrt', self::throws(fn() => Tls::check('localtest.me', 'https', $real)));
            $hs = Tls::handshake('example.com', '127.0.0.1', 443, null, false);
            self::expect('Handshake auf 127.0.0.1 verweigert', !$hs['ok'] && $hs['stage'] === 'connect');
            $hs = Tls::handshake('example.com', '93.184.215.14', 22, null, false);
            self::expect('Port 22 verweigert', !$hs['ok']);
            // Weiterleitung auf eine interne Adresse wird nicht verfolgt
            $chain = Web::chain('https://httpbin.org/redirect-to?url=' . rawurlencode('http://127.0.0.1/') . '&status_code=302', $real);
            self::expect('Weiterleitung auf 127.0.0.1 nicht verfolgt', count($chain['hops']) === 1 && $chain['stopped'] !== '', $chain['stopped']);
            $chain = Web::chain('https://httpbin.org/redirect-to?url=' . rawurlencode('http://169.254.169.254/latest/meta-data/') . '&status_code=301', $real);
            self::expect('Weiterleitung auf 169.254.169.254 nicht verfolgt', count($chain['hops']) === 1 && $chain['stopped'] !== '', $chain['stopped']);
            $spf = Spf::check('google.com', $real)->toArray();
            self::expect('SPF google.com gefunden', ($spf['data']['has'] ?? false) === true, $spf['summary']);
        }

        echo 'KLXM Check – Selbsttest: ' . self::$ok . ' bestanden, ' . count(self::$fail) . " fehlgeschlagen\n";
        foreach (self::$fail as $f) echo '  ✗ ' . $f . "\n";
        return self::$fail ? 1 : 0;
    }

    /** include-Kette mit 12 Stufen (l0 → l1 → … → l11) */
    private static function longChain(): array
    {
        $z = [];
        for ($i = 0; $i < 12; $i++) $z["TXT l$i.example.net"] = ['v=spf1 include:l' . ($i + 1) . '.example.net -all'];
        $z['TXT l12.example.net'] = ['v=spf1 -all'];
        return $z;
    }

    /** Prüfung auf der Kommandozeile: check:run <abschnitt> <domain> [--service=https] [--selector=s] [--json] */
    public static function cli(array $args): int
    {
        $pos = array_values(array_filter($args, fn($a) => !str_starts_with((string) $a, '--')));
        $opt = [];
        foreach ($args as $a) if (preg_match('~^--([a-z]+)(?:=(.*))?$~', (string) $a, $m)) $opt[$m[1]] = $m[2] ?? '1';
        [$section, $input] = $pos + ['', ''];
        try {
            $domain = Domain::normalize((string) $input);
            $dns = new Dns(25);
            $res = match ($section) {
                'spf' => Spf::check($domain, $dns),
                'dmarc' => Dmarc::check($domain, $dns),
                'dkim' => Dkim::check($domain, $dns, (string) ($opt['selector'] ?? '')),
                'mail' => Mail::check($domain, $dns, !isset($opt['no-smtp'])),
                'hosting' => Hosting::check($domain, $dns),
                'web' => Web::check($domain, $dns),
                'tls' => Tls::check($domain, (string) ($opt['service'] ?? 'https'), $dns),
                default => throw new CheckException('Abschnitt: spf|dmarc|dkim|mail|hosting|web|tls'),
            };
        } catch (CheckException $e) {
            fwrite(STDERR, 'Abgelehnt: ' . $e->getMessage() . "\n");
            return 2;
        }
        $a = $res->toArray();
        if (isset($opt['json'])) {
            echo json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
            return 0;
        }
        $sym = ['ok' => '✓', 'info' => 'i', 'warn' => '!', 'error' => '✗'];
        echo strtoupper($a['title']) . ' – ' . $domain . ' [' . $a['status'] . ', ' . $a['score'] . '/100] ' . $a['summary'] . "\n";
        foreach ($a['findings'] as $f) echo '  ' . $sym[$f['level']] . ' ' . $f['text'] . "\n";
        foreach ($a['blocks'] as $b) {
            echo '  — ' . $b['title'] . "\n";
            if ($b['type'] === 'kv') foreach ($b['rows'] as $row) echo '      ' . $row[0] . ': ' . $row[1] . "\n";
            if ($b['type'] === 'code') echo '      ' . $b['text'] . "\n";
            if ($b['type'] === 'list') foreach ($b['items'] as $it) echo '      • ' . $it[0] . "\n";
            if ($b['type'] === 'table') foreach ($b['rows'] as $row) echo '      | ' . implode(' | ', array_map(fn($c) => is_array($c) ? (string) $c[0] : (string) $c, $row)) . "\n";
        }
        return 0;
    }
}

/** Test-DNS: feste Antworten „TYP name“ => Werte (TXT: Zeichenketten, A/AAAA: Adressen, MX: [prio, host]) */
final class FakeDns extends Dns
{
    public function __construct(private array $zone)
    {
        parent::__construct(5);
    }

    public function raw(string $name, int $type): ?array
    {
        $this->queries++;
        $t = [DNS_TXT => 'TXT', DNS_A => 'A', DNS_AAAA => 'AAAA', DNS_MX => 'MX'][$type] ?? 'X';
        $vals = $this->zone[$t . ' ' . strtolower($name)] ?? [];
        return array_map(fn($v) => match ($t) {
            'TXT' => ['type' => 'TXT', 'txt' => $v, 'entries' => [$v]],
            'A' => ['type' => 'A', 'ip' => $v],
            'AAAA' => ['type' => 'AAAA', 'ipv6' => $v],
            'MX' => ['type' => 'MX', 'pri' => $v[0], 'target' => $v[1]],
            default => [],
        }, $vals);
    }
}
