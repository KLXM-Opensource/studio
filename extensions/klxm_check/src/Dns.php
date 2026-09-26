<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * DNS-Abfragen über den Resolver des Servers (dns_get_record) – mit Zwischenspeicher je Anfrage, Zähler und Zeitbudget.
 * Namen kommen entweder aus Domain::normalize (Besucher) oder aus DNS-Antworten (Domain::validDnsName); anderes wird nicht
 * abgefragt. Warnungen von dns_get_record (enthalten Namen) werden unterdrückt – nichts davon landet im Fehlerprotokoll.
 */
class Dns
{
    /** Anzahl tatsächlicher Abfragen (für Grenzen) */
    public int $queries = 0;
    /** Abfragen, die mit einem Fehler des Resolvers endeten (SERVFAIL, Zeitüberschreitung) */
    public int $failures = 0;
    private array $memo = [];
    private float $deadline;

    public function __construct(float $budgetSeconds = 20.0, private int $maxQueries = 250)
    {
        $this->deadline = microtime(true) + $budgetSeconds;
    }

    public function expired(): bool
    {
        return microtime(true) > $this->deadline || $this->queries >= $this->maxQueries;
    }

    /**
     * Rohabfrage. @return list<array>|null  null = Fehler des Resolvers bzw. Budget erschöpft, [] = keine Einträge (NXDOMAIN/NODATA)
     */
    public function raw(string $name, int $type): ?array
    {
        $name = strtolower(rtrim($name, '.'));
        $key = $type . '|' . $name;
        if (array_key_exists($key, $this->memo)) return $this->memo[$key];
        if (!Domain::validDnsName($name) && !preg_match('~^[0-9a-f.]+\.(in-addr|ip6)\.arpa$~', $name)) return $this->memo[$key] = [];
        if ($this->expired()) return null;
        $this->queries++;
        $res = $this->lookup($name, $type);
        if ($res === null) {
            $this->failures++;
            return $this->memo[$key] = null;
        }
        // dns_get_record liefert bei CNAME-Ketten auch die CNAME-Einträge mit – nur den gewünschten Typ behalten
        $want = [DNS_A => 'A', DNS_AAAA => 'AAAA', DNS_TXT => 'TXT', DNS_MX => 'MX', DNS_NS => 'NS', DNS_SOA => 'SOA', DNS_CAA => 'CAA', DNS_PTR => 'PTR', DNS_CNAME => 'CNAME'][$type] ?? null;
        $res = array_values(array_filter($res, fn($r) => $want === null || ($r['type'] ?? '') === $want));
        return $this->memo[$key] = $res;
    }

    /** Weg der Abfrage: eigener DNS-Client mit Zeitlimit (DnsWire), sonst dns_get_record. null = Fehler/Zeitlimit */
    private static int $wireOk = 0;
    private static int $wireFail = 0;

    protected function lookup(string $name, int $type): ?array
    {
        $wt = DnsWire::typeFromPhp($type);
        // DnsWire nutzen, solange der Resolver antwortet (zwei Fehlschläge ohne jeden Erfolg → dns_get_record)
        if ($wt !== null && DnsWire::resolver() !== null && (self::$wireOk > 0 || self::$wireFail < 2)) {
            $r = DnsWire::query($name, $wt, min(DnsWire::TIMEOUT, max(0.5, $this->deadline - microtime(true))));
            if ($r === null) {
                self::$wireFail++;
                return null;
            }
            self::$wireOk++;
            if ($r['rcode'] === 3) return [];
            if ($r['rcode'] !== 0) return null;
            return $r['records'];
        }
        $res = @dns_get_record($name, $type);
        return $res === false ? null : $res;
    }

    /** TXT-Einträge (Zeichenketten eines Eintrags zusammengefügt) */
    public function txt(string $name): ?array
    {
        $r = $this->raw($name, DNS_TXT);
        if ($r === null) return null;
        return array_map(fn($x) => isset($x['entries']) ? implode('', (array) $x['entries']) : (string) ($x['txt'] ?? ''), $r);
    }

    /** @return list<string> */
    public function a(string $name): array
    {
        return array_values(array_unique(array_map(fn($x) => (string) $x['ip'], array_filter($this->raw($name, DNS_A) ?? [], fn($x) => isset($x['ip'])))));
    }

    /** @return list<string> */
    public function aaaa(string $name): array
    {
        return array_values(array_unique(array_map(fn($x) => (string) $x['ipv6'], array_filter($this->raw($name, DNS_AAAA) ?? [], fn($x) => isset($x['ipv6'])))));
    }

    /** @return list<array{pri: int, host: string}> nach Priorität sortiert */
    public function mx(string $name): ?array
    {
        $r = $this->raw($name, DNS_MX);
        if ($r === null) return null;
        $out = array_map(fn($x) => ['pri' => (int) ($x['pri'] ?? 0), 'host' => strtolower(rtrim((string) ($x['target'] ?? ''), '.'))], $r);
        usort($out, fn($a, $b) => [$a['pri'], $a['host']] <=> [$b['pri'], $b['host']]);
        return $out;
    }

    /** @return list<string> */
    public function ns(string $name): array
    {
        $out = array_map(fn($x) => strtolower(rtrim((string) ($x['target'] ?? ''), '.')), $this->raw($name, DNS_NS) ?? []);
        sort($out);
        return array_values(array_unique(array_filter($out)));
    }

    public function soa(string $name): ?array
    {
        $r = $this->raw($name, DNS_SOA) ?? [];
        return $r[0] ?? null;
    }

    /** @return list<array{flags: int, tag: string, value: string}> */
    public function caa(string $name): array
    {
        return array_map(fn($x) => ['flags' => (int) ($x['flags'] ?? 0), 'tag' => (string) ($x['tag'] ?? ''), 'value' => (string) ($x['value'] ?? '')], $this->raw($name, DNS_CAA) ?? []);
    }

    public function cname(string $name): ?string
    {
        $r = $this->raw($name, DNS_CNAME) ?? [];
        return isset($r[0]['target']) ? strtolower(rtrim((string) $r[0]['target'], '.')) : null;
    }

    /** Reverse DNS (PTR) einer IP. @return list<string> */
    public function ptr(string $ip): array
    {
        $name = self::reverseName($ip);
        if ($name === null) return [];
        return array_values(array_unique(array_map(fn($x) => strtolower(rtrim((string) ($x['target'] ?? ''), '.')), $this->raw($name, DNS_PTR) ?? [])));
    }

    public static function reverseName(string $ip): ?string
    {
        $bin = @inet_pton($ip);
        if ($bin === false) return null;
        if (strlen($bin) === 4) return implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa';
        return implode('.', array_reverse(str_split(bin2hex($bin)))) . '.ip6.arpa';
    }

    /**
     * Forward-confirmed reverse DNS: PTR-Name der IP löst wieder auf dieselbe IP auf.
     * @return array{ptr: list<string>, fcrdns: bool}
     */
    public function fcrdns(string $ip): array
    {
        $ptr = $this->ptr($ip);
        $ok = false;
        $want = inet_pton($ip);
        foreach (array_slice($ptr, 0, 3) as $name) {
            foreach (str_contains($ip, ':') ? $this->aaaa($name) : $this->a($name) as $back) {
                if (@inet_pton($back) === $want) $ok = true;
            }
        }
        return ['ptr' => $ptr, 'fcrdns' => $ok];
    }
}
