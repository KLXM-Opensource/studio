<?php
declare(strict_types=1);

namespace Core\Http;

/**
 * Vorgeschalteter Proxy (z. B. nginx im Plesk-Abonnement des Kunden): Domain, https und Besucher-IP aus den X-Forwarded-Köpfen
 * übernehmen – einmal am Anfang (bootstrap, vor der Website-Auswahl), damit alle Stellen, die HTTP_HOST/HTTPS/REMOTE_ADDR lesen,
 * die Sicht des Besuchers haben.
 *
 * Nur wenn die Anfrage von einem vertrauenswürdigen Proxy kommt (Loopback, private Adressen, 'trusted_proxies' in der Konfiguration):
 *   X-Forwarded-Host   → HTTP_HOST, aber nur, wenn die Domain einer Website dieser Installation gehört (sonst unverändert –
 *                        keine fremden Domains in Links, z. B. „Passwort vergessen“). Abschalten: 'forwarded_host' => false.
 *   X-Forwarded-Proto  → HTTPS=on bei „https“
 *   X-Forwarded-For    → REMOTE_ADDR = rechteste Adresse, die kein vertrauenswürdiger Proxy ist (mehrere Stufen, z. B.
 *                        Kunden-nginx → Studio-nginx → Apache)
 * Die ursprünglichen Werte stehen danach in KLXM_PROXY_ADDR und KLXM_PROXY_HOST.
 */
final class Forwarded
{
    /**
     * @param array<string, mixed> $server  wird angepasst ($_SERVER)
     * @param list<string> $trusted  zusätzliche vertrauenswürdige Proxy-Adressen
     * @param callable(string): bool $known  gehört die Domain (mit/ohne Port) einer Website?
     */
    public static function apply(array &$server, array $trusted, callable $known, bool $hostOn = true): void
    {
        $addr = (string) ($server['REMOTE_ADDR'] ?? '');
        if (!self::trusted($addr, $trusted)) return;
        $server['KLXM_PROXY_ADDR'] = $addr;

        $fh = strtolower(trim(explode(',', (string) ($server['HTTP_X_FORWARDED_HOST'] ?? ''))[0]));
        if ($hostOn && $fh !== '' && preg_match('~^[a-z0-9.-]+(:\d{1,5})?$~', $fh) && $known($fh)) {
            $server['KLXM_PROXY_HOST'] = (string) ($server['HTTP_HOST'] ?? '');
            $server['HTTP_HOST'] = $fh;
            $server['SERVER_NAME'] = preg_replace('~:\d+$~', '', $fh);
        }
        $proto = strtolower(trim(explode(',', (string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        if ($proto === 'https') $server['HTTPS'] = 'on';

        $chain = array_values(array_filter(array_map('trim', explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''))), fn($ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false));
        if ($chain) {
            $client = $chain[0];
            for ($i = count($chain) - 1; $i >= 0; $i--) {
                if (!self::trusted($chain[$i], $trusted)) { $client = $chain[$i]; break; }
            }
            $server['REMOTE_ADDR'] = $client;
        }
    }

    /** Vertrauenswürdiger Proxy: Loopback, private/reservierte Adressen oder ausdrücklich eingetragen */
    public static function trusted(string $ip, array $trusted): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return true;
        return in_array($ip, array_map('strval', $trusted), true);
    }

    /** Teil von security-log:selftest @return array{ok: int, fails: list<string>} */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails, 'Proxy-Köpfe – ');
        $known = fn(string $h) => in_array($h, ['kunde.de', 'www.kunde.de', 'kunde.localhost:8120'], true);
        $run = function (array $s, array $trusted = [], bool $on = true) use ($known) { Forwarded::apply($s, $trusted, $known, $on); return $s; };
        $hdr = ['HTTP_HOST' => 'studio.example', 'HTTP_X_FORWARDED_HOST' => 'kunde.de', 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'];

        $s = $run(['REMOTE_ADDR' => '127.0.0.1'] + $hdr);
        $eq('Loopback: Domain übernommen', [$s['HTTP_HOST'], $s['SERVER_NAME'], $s['KLXM_PROXY_HOST']], ['kunde.de', 'kunde.de', 'studio.example']);
        $eq('Loopback: https und Besucher-IP', [$s['HTTPS'] ?? '', $s['REMOTE_ADDR'], $s['KLXM_PROXY_ADDR']], ['on', '198.51.100.7', '127.0.0.1']);
        $s = $run(['REMOTE_ADDR' => '203.0.113.9'] + $hdr);
        $eq('Fremde Adresse: nichts übernommen', [$s['HTTP_HOST'], $s['HTTPS'] ?? '', $s['REMOTE_ADDR']], ['studio.example', '', '203.0.113.9']);
        $s = $run(['REMOTE_ADDR' => '203.0.113.9'] + $hdr, ['203.0.113.9']);
        $eq('Eingetragener Proxy (trusted_proxies)', [$s['HTTP_HOST'], $s['REMOTE_ADDR']], ['kunde.de', '198.51.100.7']);
        $s = $run(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_HOST' => 'boese.example'] + $hdr);
        $eq('Unbekannte Domain bleibt draußen', $s['HTTP_HOST'], 'studio.example');
        $s = $run(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_HOST' => 'kunde.de/x"'] + $hdr);
        $eq('Ungültige Domain bleibt draußen', $s['HTTP_HOST'], 'studio.example');
        $s = $run(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_HOST' => 'kunde.localhost:8120, studio.example'] + $hdr);
        $eq('Erste Domain der Kette, mit Port', $s['HTTP_HOST'], 'kunde.localhost:8120');
        $s = $run(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.7, 203.0.113.9, 10.0.0.2'] + $hdr, ['203.0.113.9']);
        $eq('Mehrere Stufen: rechteste fremde Adresse (vorgetäuschte links ignoriert)', $s['REMOTE_ADDR'], '198.51.100.7');
        $s = $run(['REMOTE_ADDR' => '127.0.0.1'] + $hdr, [], false);
        $eq('forwarded_host aus: Domain bleibt, IP/https trotzdem', [$s['HTTP_HOST'], $s['REMOTE_ADDR'], $s['HTTPS'] ?? ''], ['studio.example', '198.51.100.7', 'on']);
        $s = $run(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'studio.example']);
        $eq('Ohne Köpfe unverändert', [$s['HTTP_HOST'], $s['REMOTE_ADDR'], isset($s['HTTPS'])], ['studio.example', '127.0.0.1', false]);
        return ['ok' => $ok, 'fails' => $fails];
    }
}
