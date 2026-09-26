<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * Schlanker DNS-Client (UDP, bei abgeschnittener Antwort TCP) für den Resolver des Servers – mit festen Zeitlimits.
 *
 * Warum nicht nur dns_get_record? Es kennt kein Zeitlimit (ein kaputter Nameserver hält die Prüfung 30 s auf), keine
 * DS-Einträge und kein AD-Bit (DNSSEC). Gefragt wird ausschließlich der Resolver des Servers (config 'klxm_check.resolver'
 * bzw. erster „nameserver“ aus /etc/resolv.conf) – nie eine Adresse aus Eingaben. Ohne lesbare resolv.conf (open_basedir)
 * fällt Dns auf dns_get_record zurück.
 *
 * Antworten werden in die Form von dns_get_record übersetzt (type, ip, ipv6, txt/entries, pri/target, mname/rname/serial …,
 * flags/tag/value), damit Dns beide Wege gleich behandelt.
 */
final class DnsWire
{
    public const TYPES = ['A' => 1, 'NS' => 2, 'CNAME' => 5, 'SOA' => 6, 'PTR' => 12, 'MX' => 15, 'TXT' => 16, 'AAAA' => 28, 'DS' => 43, 'DNSKEY' => 48, 'CAA' => 257];
    public const TIMEOUT = 2.5;

    /** PHP-Konstante (DNS_A …) → Typnummer */
    public static function typeFromPhp(int $t): ?int
    {
        return [DNS_A => 1, DNS_NS => 2, DNS_CNAME => 5, DNS_SOA => 6, DNS_PTR => 12, DNS_MX => 15, DNS_TXT => 16, DNS_AAAA => 28, DNS_CAA => 257][$t] ?? null;
    }

    /**
     * Abfrage. @return array{rcode: int, ad: bool, tc: bool, records: list<array>}|null  null = keine Antwort (Zeitlimit, Netzfehler)
     */
    public static function query(string $name, int $type, float $timeout = self::TIMEOUT): ?array
    {
        $name = rtrim($name, '.');
        if (!Domain::validDnsName($name) && !preg_match('~^[0-9a-f.]+\.(in-addr|ip6)\.arpa$~', $name)) return null;
        $ns = self::resolver();
        if ($ns === null) return null;
        $id = random_int(0, 0xffff);
        // Kopf: RD + AD (Validierungsstatus erbitten); EDNS0 mit 1232 Byte und DO-Bit
        $q = pack('nnnnnn', $id, 0x0120, 1, 0, 0, 1);
        foreach (explode('.', $name) as $label) $q .= chr(strlen($label)) . $label;
        $q .= "\0" . pack('nn', $type, 1) . "\0" . pack('nnNn', 41, 1232, 0x00008000, 0);
        $host = str_contains($ns, ':') ? '[' . $ns . ']' : $ns;
        $resp = self::exchange('udp://' . $host . ':53', $q, $timeout, false);
        if ($resp === null) return null;
        $parsed = self::parse($resp, $id, $type);
        if ($parsed && $parsed['tc']) {
            $tcp = self::exchange('tcp://' . $host . ':53', pack('n', strlen($q)) . $q, $timeout, true);
            if ($tcp !== null) $parsed = self::parse($tcp, $id, $type) ?? $parsed;
        }
        return $parsed;
    }

    private static function exchange(string $target, string $payload, float $timeout, bool $tcp): ?string
    {
        $s = @stream_socket_client($target, $errno, $errstr, $timeout);
        if (!$s) return null;
        $sec = (int) floor($timeout);
        stream_set_timeout($s, $sec, (int) (($timeout - $sec) * 1e6));
        @fwrite($s, $payload);
        if ($tcp) {
            $len = @fread($s, 2);
            if (!is_string($len) || strlen($len) !== 2) {
                fclose($s);
                return null;
            }
            $want = unpack('n', $len)[1];
            $buf = '';
            while (strlen($buf) < $want && !feof($s)) {
                $chunk = @fread($s, $want - strlen($buf));
                if ($chunk === false || $chunk === '') break;
                $buf .= $chunk;
                if (stream_get_meta_data($s)['timed_out']) break;
            }
        } else {
            $buf = @fread($s, 65535);
        }
        $timedOut = stream_get_meta_data($s)['timed_out'] ?? false;
        fclose($s);
        return is_string($buf) && strlen($buf) >= 12 && !$timedOut ? $buf : null;
    }

    /** Antwort zerlegen (nur Antwort-Abschnitt) */
    public static function parse(string $buf, int $id, int $type): ?array
    {
        $h = unpack('nid/nflags/nqd/nan/nns/nar', substr($buf, 0, 12));
        if (!$h || $h['id'] !== $id || !($h['flags'] & 0x8000)) return null;
        $pos = 12;
        for ($i = 0; $i < $h['qd']; $i++) {
            $pos = self::skipName($buf, $pos);
            if ($pos < 0) return null;
            $pos += 4;
        }
        $records = [];
        for ($i = 0; $i < $h['an'] && $i < 200; $i++) {
            $pos = self::skipName($buf, $pos);
            if ($pos < 0 || $pos + 10 > strlen($buf)) break;
            $rr = unpack('ntype/nclass/Nttl/nlen', substr($buf, $pos, 10));
            $pos += 10;
            $rd = $pos;
            $pos += $rr['len'];
            if ($pos > strlen($buf)) break;
            $rec = self::rdata($buf, $rr['type'], $rd, $rr['len']);
            if ($rec !== null) $records[] = $rec + ['ttl' => $rr['ttl']];
        }
        return ['rcode' => $h['flags'] & 0x0f, 'ad' => (bool) ($h['flags'] & 0x0020), 'tc' => (bool) ($h['flags'] & 0x0200), 'records' => $records];
    }

    private static function rdata(string $buf, int $type, int $at, int $len): ?array
    {
        $rd = substr($buf, $at, $len);
        switch ($type) {
            case 1:
                return $len === 4 ? ['type' => 'A', 'ip' => (string) inet_ntop($rd)] : null;
            case 28:
                return $len === 16 ? ['type' => 'AAAA', 'ipv6' => (string) inet_ntop($rd)] : null;
            case 2:
            case 5:
            case 12:
                $n = self::readName($buf, $at);
                return $n === null ? null : ['type' => [2 => 'NS', 5 => 'CNAME', 12 => 'PTR'][$type], 'target' => $n];
            case 15:
                $n = $len > 2 ? self::readName($buf, $at + 2) : null;
                return $n === null ? null : ['type' => 'MX', 'pri' => unpack('n', substr($rd, 0, 2))[1], 'target' => $n];
            case 16:
                $entries = [];
                for ($p = 0; $p < $len;) {
                    $l = ord($rd[$p]);
                    $entries[] = substr($rd, $p + 1, $l);
                    $p += $l + 1;
                }
                return ['type' => 'TXT', 'txt' => implode('', $entries), 'entries' => $entries];
            case 6:
                $p = $at;
                $m = self::readName($buf, $p, $p);
                $r = $m === null ? null : self::readName($buf, $p, $p);
                if ($m === null || $r === null || $p + 20 > strlen($buf)) return null;
                $n = unpack('Nserial/Nrefresh/Nretry/Nexpire/Nmin', substr($buf, $p, 20));
                return ['type' => 'SOA', 'mname' => $m, 'rname' => $r, 'serial' => $n['serial'], 'refresh' => $n['refresh'], 'retry' => $n['retry'], 'expire' => $n['expire'], 'minimum-ttl' => $n['min']];
            case 257:
                if ($len < 2) return null;
                $tl = ord($rd[1]);
                return ['type' => 'CAA', 'flags' => ord($rd[0]), 'tag' => substr($rd, 2, $tl), 'value' => substr($rd, 2 + $tl)];
            case 43:
                return ['type' => 'DS'];
            case 48:
                return ['type' => 'DNSKEY'];
        }
        return null;
    }

    /** Namen lesen (mit Kompression); $next erhält die Position hinter dem Namen */
    private static function readName(string $buf, int $pos, ?int &$next = null): ?string
    {
        $labels = [];
        $jumped = false;
        $len = strlen($buf);
        for ($guard = 0; $guard < 128; $guard++) {
            if ($pos >= $len) return null;
            $l = ord($buf[$pos]);
            if ($l === 0) {
                if (!$jumped) $next = $pos + 1;
                return strtolower(implode('.', $labels));
            }
            if (($l & 0xc0) === 0xc0) {
                if ($pos + 1 >= $len) return null;
                if (!$jumped) $next = $pos + 2;
                $jumped = true;
                $pos = (($l & 0x3f) << 8) | ord($buf[$pos + 1]);
                continue;
            }
            $labels[] = substr($buf, $pos + 1, $l);
            $pos += $l + 1;
        }
        return null;
    }

    private static function skipName(string $buf, int $pos): int
    {
        $len = strlen($buf);
        for ($guard = 0; $guard < 128 && $pos < $len; $guard++) {
            $l = ord($buf[$pos]);
            if ($l === 0) return $pos + 1;
            if (($l & 0xc0) === 0xc0) return $pos + 2;
            $pos += $l + 1;
        }
        return -1;
    }

    /** DNSSEC-Angabe: DS-Eintrag in der übergeordneten Zone vorhanden? AD-Bit des Resolvers? @return array{ds: bool, ad: bool}|null */
    public static function dnssec(string $zone): ?array
    {
        $r = self::query($zone, self::TYPES['DS']);
        if ($r === null || !in_array($r['rcode'], [0, 3], true)) return null;
        return ['ds' => (bool) array_filter($r['records'], fn($x) => $x['type'] === 'DS'), 'ad' => $r['ad']];
    }

    /** Resolver des Servers (nur Adresse, keine Eingabe von außen) */
    public static function resolver(): ?string
    {
        static $cached = false;
        if ($cached !== false) return $cached;
        $cfg = (string) (Check::config()['resolver'] ?? '');
        if ($cfg !== '' && filter_var($cfg, FILTER_VALIDATE_IP)) return $cached = $cfg;
        $f = '/etc/resolv.conf';
        $list = [];
        if (@is_readable($f)) {
            foreach (@file($f, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (preg_match('~^\s*nameserver\s+([0-9a-f.:]+)(%\S+)?\s*$~i', $line, $m) && filter_var($m[1], FILTER_VALIDATE_IP) && empty($m[2])) $list[] = $m[1];
            }
        }
        // IPv4 zuerst (IPv6-Resolver mit Zonen-Angabe wie fe80::1%en0 sind nicht nutzbar)
        usort($list, fn($a, $b) => str_contains($a, ':') <=> str_contains($b, ':'));
        if ($list) return $cached = $list[0];
        return $cached = null;
    }
}
