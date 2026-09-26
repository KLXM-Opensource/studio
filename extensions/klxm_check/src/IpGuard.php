<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * SSRF-Schutz: nur öffentlich routbare Adressen, Namen werden selbst aufgelöst und die geprüfte Adresse wird festgehalten
 * („pinning“ – curl über CURLOPT_RESOLVE, Sockets direkt auf die IP mit SNI/peer_name). Kein zweites DNS → kein DNS-Rebinding.
 *
 * IPv4 gesperrt: 0/8, 10/8, 100.64/10 (CGNAT), 127/8, 169.254/16, 172.16/12, 192.0.0/24, 192.0.2/24, 192.88.99/24,
 *   192.168/16, 198.18/15, 198.51.100/24, 203.0.113/24, 224/4 (Multicast), 240/4 (inkl. Broadcast).
 * IPv6 nur aus 2000::/3 (Global Unicast), darin gesperrt: 2001::/23 (IETF, u. a. Teredo, Benchmark), 2001:db8::/32 und 3fff::/20
 *   (Dokumentation), 2002::/16 (6to4 – nur wenn die eingebettete IPv4 öffentlich ist); damit entfallen automatisch ::1, ::,
 *   ::ffff:0:0/96 (IPv4-gemappt), 64:ff9b::/96 (NAT64), 100::/64, fc00::/7 (ULA), fe80::/10, fec0::/10, ff00::/8.
 * Ausnahme nur für die Entwicklung: config 'klxm_check' => ['allow_private' => ['127.0.0.1']] bei 'environment' => 'development'.
 */
final class IpGuard
{
    /** Erlaubte Ziel-Ports (fest): HTTP(S) und Mail-TLS */
    public const PORTS = [80, 443, 25, 465, 587, 993, 995];

    private const V4_BLOCKED = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
        '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ];
    private const V6_BLOCKED = ['2001::/23', '2001:db8::/32', '3fff::/20'];

    public static function isPublic(string $ip): bool
    {
        $bin = @inet_pton(trim($ip, '[]'));
        if ($bin === false) return false;
        if (strlen($bin) === 4) {
            foreach (self::V4_BLOCKED as $net) if (self::inNet($bin, $net)) return false;
            return true;
        }
        if (!self::inNet($bin, '2000::/3')) return false;
        foreach (self::V6_BLOCKED as $net) if (self::inNet($bin, $net)) return false;
        // 6to4: 2002:AABB:CCDD::/48 trägt die IPv4 a.b.c.d
        if (self::inNet($bin, '2002::/16')) return self::isPublic((string) inet_ntop(substr($bin, 2, 4)));
        return true;
    }

    /** Liegt die Adresse (binär) im Netz „a.b.c.d/n“ bzw. „x::/n“? */
    public static function inNet(string $bin, string $cidr): bool
    {
        [$net, $bits] = explode('/', $cidr) + [1 => ''];
        $nb = @inet_pton($net);
        if ($nb === false || strlen($nb) !== strlen($bin)) return false;
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if ($bytes && substr($bin, 0, $bytes) !== substr($nb, 0, $bytes)) return false;
        $rest = $bits % 8;
        if ($rest === 0) return true;
        $mask = (0xff << (8 - $rest)) & 0xff;
        return (ord($bin[$bytes]) & $mask) === (ord($nb[$bytes]) & $mask);
    }

    /**
     * Namen auflösen und prüfen: ALLE Adressen müssen öffentlich sein (sonst Abbruch – auch gemischte Antworten gelten als
     * Versuch, ins interne Netz zu gelangen). @return list<string> IPv4 zuerst. @throws CheckException
     */
    public static function resolve(string $host, Dns $dns): array
    {
        Domain::assertHostname($host);
        $ips = array_merge($dns->a($host), $dns->aaaa($host));
        if (!$ips) throw new CheckException(lt('Der Name {host} lässt sich nicht auflösen.', ['host' => $host]));
        foreach ($ips as $ip) {
            if (!self::isPublic($ip) && !self::devAllowed($ip)) {
                throw new CheckException(lt('{host} zeigt auf eine nicht öffentliche Adresse – aus Sicherheitsgründen wird keine Verbindung aufgebaut.', ['host' => $host]), 403);
            }
        }
        return $ips;
    }

    /** Port aus der festen Liste? */
    public static function portAllowed(int $port): bool
    {
        return in_array($port, self::PORTS, true);
    }

    /** Anzeige einer Adresse aus DNS-Antworten: nicht öffentliche Adressen werden ausgeblendet (keine internen Netze preisgeben) */
    public static function show(string $ip): string
    {
        return self::isPublic($ip) ? $ip : lt('nicht öffentliche Adresse (ausgeblendet)');
    }

    /** Nur Entwicklung: config 'klxm_check' => ['allow_private' => [...]] und 'environment' => 'development' */
    public static function devAllowed(string $ip): bool
    {
        if (!function_exists('environment') || environment() !== 'development') return false;
        return in_array($ip, (array) (Check::config()['allow_private'] ?? []), true);
    }
}
