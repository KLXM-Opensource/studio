<?php
declare(strict_types=1);

namespace Core\Sources;

/**
 * Serverseitiger HTTP-Abruf für externe Quellen (Feeds, APIs, OpenImmo, Bilder) – mit SSRF-Schutz.
 *
 * Anders als Core\Proxy::http (nur fest registrierte Anbieter) sind die Adressen hier frei einstellbar. Daher:
 *   - nur http/https, keine Zugangsdaten in der Adresse, Ports 80/443/8080/8443
 *   - der Hostname wird selbst aufgelöst; ALLE Adressen müssen öffentlich sein (keine privaten, Loopback-, Link-local-,
 *     CGNAT-, reservierten oder IPv4-in-IPv6-Adressen) – curl verbindet danach fest mit der geprüften Adresse
 *     (CURLOPT_RESOLVE, kein zweites DNS → kein DNS-Rebinding)
 *   - Weiterleitungen (max. 3) werden einzeln geprüft, nie automatisch verfolgt; kein Proxy aus der Umgebung
 *   - Größenlimit während des Empfangs (auch nach gzip-Entpacken), Zeitlimit, Prüfung des Content-Type
 * Ausnahme nur für die Entwicklung: config 'sources_allow_private' => ['127.0.0.1:8099', …] wirkt ausschließlich,
 * wenn 'environment' => 'development' gesetzt ist.
 */
final class Fetcher
{
    public const PORTS = [80, 443, 8080, 8443];

    /** Erlaubte Content-Types je Art (Präfixe) */
    public const TYPES = [
        'xml' => ['application/xml', 'text/xml', 'application/rss+xml', 'application/atom+xml', 'application/rdf+xml', 'text/plain', 'application/octet-stream'],
        'json' => ['application/json', 'text/json', 'text/plain', 'application/octet-stream', 'application/ld+json', 'application/vnd.api+json', 'application/hal+json', 'application/feed+json'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/octet-stream'],
        'file' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'application/octet-stream'],
    ];

    /**
     * GET mit Schutzmaßnahmen.
     * $o: headers (list<string>), timeout (s, 2–60), max (Bytes), kind (Schlüssel aus TYPES oder null = alles), ua (User-Agent),
     *     basic ([user, pass]) – Zugangsdaten nur für den ersten Host (bei Weiterleitung auf andere Hosts nicht mitsenden)
     * @return array{status: int, body: string, type: string, url: string, bytes: int, ms: int}
     * @throws SourceException
     */
    public static function get(string $url, array $o = []): array
    {
        if (!function_exists('curl_init')) throw new SourceException(__('Auf dem Server fehlt die PHP-Erweiterung curl.'));
        $timeout = max(2, min(60, (int) ($o['timeout'] ?? 15)));
        $max = max(1024, (int) ($o['max'] ?? 5 * 1024 * 1024));
        $ua = trim((string) ($o['ua'] ?? '')) ?: self::userAgent();
        $started = microtime(true);
        $firstHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        for ($hop = 0; $hop <= 3; $hop++) {
            [$scheme, $host, $port, $ip] = self::check($url);
            $headers = array_values(array_filter(array_map('strval', (array) ($o['headers'] ?? []))));
            // Kopfzeilen mit Zugangsdaten nur an den eingestellten Host – nicht an Weiterleitungsziele
            if ($host !== $firstHost) $headers = array_values(array_filter($headers, fn($h) => !preg_match('~^(authorization|x-api-key|api-key|cookie)\s*:~i', $h)));
            $body = '';
            $tooBig = false;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE => [$host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
                CURLOPT_PROXY => '',
                CURLOPT_NOPROXY => '*',
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_USERAGENT => $ua,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_ENCODING => '',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HEADER => false,
                CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body, &$tooBig, $max): int {
                    if (strlen($body) + strlen($chunk) > $max) { $tooBig = true; return 0; }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if (!empty($o['basic']) && $host === $firstHost) {
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_USERPWD, $o['basic'][0] . ':' . $o['basic'][1]);
            }
            $ok = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $type = strtolower(trim(explode(';', (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE))[0]));
            $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $err = curl_error($ch);
            $errno = curl_errno($ch);
            if ($tooBig) {
                throw new SourceException(__('Antwort zu groß (mehr als {mb} MB) – Abruf abgebrochen.', ['mb' => self::mb($max)]));
            }
            if ($ok === false) {
                throw new SourceException($errno === CURLE_OPERATION_TIMEDOUT
                    ? __('Zeitüberschreitung nach {s} Sekunden.', ['s' => $timeout])
                    : __('Abruf fehlgeschlagen: {error}', ['error' => $err !== '' ? $err : 'curl ' . $errno]));
            }
            if (in_array($status, [301, 302, 303, 307, 308], true) && $location !== '') {
                $url = $location;
                continue;
            }
            if ($status !== 200) {
                throw new SourceException(__('Der Server antwortete mit HTTP {status}.', ['status' => $status ?: '–']));
            }
            $kind = $o['kind'] ?? null;
            if ($kind !== null && $type !== '' && !self::typeAllowed($type, (string) $kind)) {
                throw new SourceException(__('Unerwarteter Inhaltstyp „{type}“ – erwartet: {expected}.', ['type' => $type, 'expected' => implode(', ', array_slice(self::TYPES[$kind] ?? [], 0, 3))]));
            }
            return ['status' => $status, 'body' => $body, 'type' => $type, 'url' => $url, 'bytes' => strlen($body), 'ms' => (int) round((microtime(true) - $started) * 1000)];
        }
        throw new SourceException(__('Zu viele Weiterleitungen.'));
    }

    public static function typeAllowed(string $type, string $kind): bool
    {
        foreach (self::TYPES[$kind] ?? [] as $t) {
            if (str_starts_with($type, $t)) return true;
        }
        // Varianten wie application/vnd.xyz+json bzw. +xml
        return ($kind === 'json' && str_ends_with($type, '+json')) || ($kind === 'xml' && str_ends_with($type, '+xml'));
    }

    /**
     * Adresse prüfen und auflösen. @return array{0: string, 1: string, 2: int, 3: string} [scheme, host, port, ip]
     * @throws SourceException
     */
    public static function check(string $url): array
    {
        $p = parse_url(trim($url));
        $scheme = strtolower((string) ($p['scheme'] ?? ''));
        $host = strtolower(trim((string) ($p['host'] ?? ''), '[]'));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new SourceException(__('Bitte eine vollständige Adresse mit http:// oder https:// angeben.'));
        }
        if (isset($p['user']) || isset($p['pass'])) {
            throw new SourceException(__('Zugangsdaten gehören nicht in die Adresse – bitte unter „Anmeldung“ eintragen.'));
        }
        $port = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        $dev = self::allowPrivate($host, $port);
        if (!$dev && !in_array($port, self::PORTS, true)) {
            throw new SourceException(__('Port {port} ist nicht erlaubt (nur 80, 443, 8080, 8443).', ['port' => $port]));
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);
        if (!$ips) throw new SourceException(__('Der Name „{host}“ lässt sich nicht auflösen.', ['host' => $host]));
        if (!$dev) {
            if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
                throw new SourceException(__('Adressen im eigenen Netz (z. B. localhost, private IP-Adressen) sind aus Sicherheitsgründen gesperrt.'));
            }
            foreach ($ips as $ip) {
                if (!self::isPublicIp($ip)) {
                    throw new SourceException(__('Adressen im eigenen Netz (z. B. localhost, private IP-Adressen) sind aus Sicherheitsgründen gesperrt.'));
                }
            }
        }
        return [$scheme, $host, $port, $ips[0]];
    }

    /** A- und AAAA-Einträge (IPv4 zuerst) */
    private static function resolve(string $host): array
    {
        if (!preg_match('~^[a-z0-9.-]+$~', $host)) return [];
        $ips = @gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $r) {
            if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
        }
        return array_values(array_unique($ips));
    }

    /** Öffentlich routbare Adresse? (IPv4 und IPv6, inkl. CGNAT, Benchmark-Netz, IPv4-gemappte IPv6-Adressen) */
    public static function isPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        $bin = inet_pton($ip);
        if ($bin === false) return false;
        if (strlen($bin) === 16) {
            // ::ffff:a.b.c.d, ::a.b.c.d, 64:ff9b::/96 (NAT64) → eingebettete IPv4 prüfen
            if (str_starts_with($bin, str_repeat("\0", 10) . "\xff\xff") || str_starts_with($bin, str_repeat("\0", 12)) || str_starts_with($bin, "\x00\x64\xff\x9b" . str_repeat("\0", 8))) {
                return self::isPublicIp((string) inet_ntop(substr($bin, 12)));
            }
            $b0 = ord($bin[0]);
            if (($b0 & 0xfe) === 0xfc || ($b0 === 0xfe && (ord($bin[1]) & 0xc0) === 0x80) || $b0 === 0xff || $b0 === 0x00) return false;   // ULA, link-local, multicast, ::/8
            return true;
        }
        [$a, $b] = [ord($bin[0]), ord($bin[1])];
        return !($a === 0 || $a === 10 || $a === 127 || ($a === 100 && ($b & 0xc0) === 64) || ($a === 169 && $b === 254)
            || ($a === 172 && ($b & 0xf0) === 16) || ($a === 192 && $b === 168) || ($a === 198 && ($b & 0xfe) === 18) || $a >= 224);
    }

    /** Nur Entwicklung: Host:Port aus config 'sources_allow_private' (bei 'environment' => 'development') */
    public static function allowPrivate(string $host, int $port): bool
    {
        if (environment() !== 'development') return false;
        $list = array_map('strtolower', (array) app()->config->get('sources_allow_private', []));
        return in_array($host . ':' . $port, $list, true) || in_array($host, $list, true);
    }

    public static function userAgent(): string
    {
        return CMS_NAME . '/' . CMS_VERSION . ' (+' . (setting('sys.site_url') ?: site_url()) . ')';
    }

    public static function mb(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, ',', ''), '0'), ',');
    }
}
