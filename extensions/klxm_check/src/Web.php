<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * Website: Erreichbarkeit per HTTPS, Weiterleitung HTTP → HTTPS, Weiterleitungskette (max. 5, jede Station neu geprüft),
 * HSTS, Sicherheits-Header, Cookie-Attribute (nur Namen, nie Werte), Server-Angaben, HTTP-Version, Antwortzeit, Kompression.
 *
 * SSRF-Schutz je Station: nur http/https auf Port 80/443, Hostname nach Domain::assertHostname, alle Adressen öffentlich
 * (IpGuard::resolve), curl verbindet fest mit der geprüften IP (CURLOPT_RESOLVE), folgt nie selbst Weiterleitungen,
 * kein Proxy aus der Umgebung, keine Cookies/Zugangsdaten, Verbindungsaufbau 5 s, gesamt 10 s, höchstens 256 KB Antwort.
 */
final class Web
{
    public const MAX_REDIRECTS = 5;
    public const MAX_BYTES = 262144;
    public const TIMEOUT = 10;

    /** Eine Anfrage (ohne Weiterleitungen zu folgen). @throws CheckException */
    public static function fetch(string $url, Dns $dns, bool $verify = true): array
    {
        if (!function_exists('curl_init')) throw new CheckException(lt('Auf diesem Server fehlt die PHP-Erweiterung curl – die Website-Prüfung ist nicht verfügbar.'), 503);
        $p = parse_url($url);
        $scheme = strtolower((string) ($p['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || empty($p['host'])) throw new CheckException(lt('Nur http- und https-Adressen werden geprüft.'), 403);
        if (isset($p['user']) || isset($p['pass'])) throw new CheckException(lt('Adressen mit Zugangsdaten werden nicht aufgerufen.'), 403);
        $host = Domain::toAscii(trim((string) $p['host'], '[]'));
        if (filter_var($host, FILTER_VALIDATE_IP)) throw new CheckException(lt('Weiterleitungen auf IP-Adressen werden aus Sicherheitsgründen nicht verfolgt.'), 403);
        $port = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        if ($port !== ($scheme === 'https' ? 443 : 80)) throw new CheckException(lt('Port {port} wird aus Sicherheitsgründen nicht aufgerufen (nur 80 und 443).', ['port' => $port]), 403);
        $ips = IpGuard::resolve($host, $dns);
        $v4 = array_values(array_filter($ips, fn($ip) => !str_contains($ip, ':')));
        $ip = $v4[0] ?? $ips[0];
        $path = ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
        $clean = $scheme . '://' . $host . $path;

        $headers = [];
        $statusLine = '';
        $body = '';
        $ch = curl_init($clean);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT => Check::USER_AGENT,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.5', 'Accept-Language: de,en;q=0.8'],
            CURLOPT_ENCODING => '',
            CURLOPT_HTTP_VERSION => defined('CURL_HTTP_VERSION_2TLS') ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$headers, &$statusLine): int {
                $t = rtrim($line, "\r\n");
                if (preg_match('~^HTTP/\S+\s+\d{3}~', $t)) {
                    $statusLine = $t;
                    $headers = [];   // bei 100 Continue o. Ä. nur die letzte Antwort behalten
                } elseif (str_contains($t, ':') && count($headers) < 200) {
                    [$k, $v] = explode(':', $t, 2);
                    $headers[strtolower(trim($k))][] = trim($v);
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body): int {
                if (strlen($body) >= self::MAX_BYTES) return 0;   // genug gelesen – Abbruch ist gewollt
                $body .= substr($chunk, 0, self::MAX_BYTES - strlen($body));
                return strlen($chunk);
            },
        ]);
        // Ohne Cookie-Engine: curl sendet keine Cookies; Set-Cookie wird nur aus den Kopfzeilen gelesen
        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $info = curl_getinfo($ch);
        $err = curl_error($ch);
        $httpVersion = (int) ($info['http_version'] ?? 0);
        $out = [
            'url' => $clean, 'host' => $host, 'ip' => $ip, 'status' => (int) ($info['http_code'] ?? 0), 'headers' => $headers,
            'location' => '', 'bytes' => strlen($body), 'body' => $body, 'error' => '', 'tlsError' => '', 'verified' => $verify,
            'httpVersion' => match ($httpVersion) { 3 => 'HTTP/2', 30 => 'HTTP/3', 2 => 'HTTP/1.1', 1 => 'HTTP/1.0', default => (string) preg_replace('~\s.*$~', '', $statusLine) },
            'ttfb' => (int) round(((float) ($info['starttransfer_time'] ?? 0)) * 1000), 'total' => (int) round(((float) ($info['total_time'] ?? 0)) * 1000),
        ];
        if ($ok === false && $errno !== 23 && $out['status'] === 0) {
            // TLS-Fehler: einmal ohne Prüfung wiederholen, damit die Header trotzdem bewertet werden können
            if ($verify && in_array($errno, [35, 51, 58, 60, 77, 83, 90, 91], true)) {
                $again = self::fetch($url, $dns, false);
                $again['tlsError'] = self::curlMsg($errno, $err);
                return $again;
            }
            $out['error'] = self::curlMsg($errno, $err);
            return $out;
        }
        if (in_array($out['status'], [301, 302, 303, 307, 308], true) && isset($headers['location'][0])) {
            $out['location'] = self::resolveUrl($clean, $headers['location'][0]);
        }
        return $out;
    }

    private static function curlMsg(int $errno, string $err): string
    {
        return match ($errno) {
            6 => lt('Name nicht auflösbar.'),
            7 => lt('Verbindung abgelehnt.'),
            28 => lt('Zeitüberschreitung.'),
            35 => lt('TLS-Handshake fehlgeschlagen.'),
            51, 60 => lt('Zertifikat ungültig oder nicht vertrauenswürdig ({msg}).', ['msg' => Tls::clean($err)]),
            default => Tls::clean($err) ?: 'curl ' . $errno,
        };
    }

    /** Relative Weiterleitungsziele auflösen */
    public static function resolveUrl(string $base, string $loc): string
    {
        $loc = trim($loc);
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $loc)) return $loc;
        $b = parse_url($base);
        $origin = ($b['scheme'] ?? 'https') . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($loc, '//')) return ($b['scheme'] ?? 'https') . ':' . $loc;
        if (str_starts_with($loc, '/')) return $origin . $loc;
        $dir = preg_replace('~/[^/]*$~', '/', $b['path'] ?? '/') ?: '/';
        return $origin . $dir . $loc;
    }

    /** Weiterleitungen folgen (jede Station geprüft). @return array{hops: list<array>, final: ?array, stopped: string} */
    public static function chain(string $start, Dns $dns): array
    {
        $hops = [];
        $url = $start;
        $seen = [];
        for ($i = 0; $i <= self::MAX_REDIRECTS; $i++) {
            if (isset($seen[$url])) return ['hops' => $hops, 'final' => end($hops) ?: null, 'stopped' => lt('Weiterleitungsschleife.')];
            $seen[$url] = true;
            try {
                $res = self::fetch($url, $dns);
            } catch (CheckException $e) {
                $msg = $hops ? lt('Weiterleitung auf {url} nicht verfolgt: {msg}', ['url' => mb_substr($url, 0, 120), 'msg' => $e->getMessage()])
                    : lt('Aufruf von {url} verweigert: {msg}', ['url' => mb_substr($url, 0, 120), 'msg' => $e->getMessage()]);
                return ['hops' => $hops, 'final' => end($hops) ?: null, 'stopped' => $msg];
            }
            $hops[] = $res;
            if ($res['error'] !== '' || $res['location'] === '') return ['hops' => $hops, 'final' => $res, 'stopped' => ''];
            $url = $res['location'];
        }
        return ['hops' => $hops, 'final' => end($hops) ?: null, 'stopped' => lt('Mehr als {n} Weiterleitungen – abgebrochen.', ['n' => self::MAX_REDIRECTS])];
    }

    public static function check(string $domain, Dns $dns): Result
    {
        $r = new Result('web', lt('Website: Sicherheit & Leistung'));
        $http = self::chain('http://' . $domain . '/', $dns);
        $first = $http['hops'][0] ?? null;
        $final = $http['final'];
        $https = null;
        if ($final && str_starts_with($final['url'], 'https://') && $final['error'] === '') {
            $https = $http;
        } else {
            $https = self::chain('https://' . $domain . '/', $dns);
        }
        $land = $https['final'] ?? null;
        if (!$land || $land['error'] !== '' || !str_starts_with($land['url'], 'https://')) {
            $r->error(lt('Die Website ist per HTTPS nicht erreichbar{msg}.', ['msg' => $land && $land['error'] !== '' ? ' (' . $land['error'] . ')' : ($https['stopped'] ? ' (' . $https['stopped'] . ')' : '')]));
            if ($final && $final['error'] === '' && $final['status']) {
                $land = $final;   // Header der HTTP-Antwort trotzdem zeigen
            } else {
                if (!$first || $first['error'] !== '') $r->error(lt('Auch per HTTP keine Antwort{msg}.', ['msg' => $first && $first['error'] !== '' ? ' (' . $first['error'] . ')' : '']));
                $r->summary = lt('Website nicht erreichbar.');
                return $r;
            }
        }
        // HTTP → HTTPS
        if ($first && $first['error'] === '') {
            $to = $first['location'];
            if ($to !== '' && str_starts_with($to, 'https://')) {
                in_array($first['status'], [301, 308], true)
                    ? $r->ok(lt('HTTP leitet dauerhaft auf HTTPS um ({code}).', ['code' => $first['status']]))
                    : $r->info(lt('HTTP leitet auf HTTPS um, aber nur vorübergehend ({code}) – besser 301 oder 308.', ['code' => $first['status']]));
            } elseif ($to !== '') {
                $r->warn(lt('HTTP leitet nicht direkt auf HTTPS um (erstes Ziel: {url}).', ['url' => mb_substr($to, 0, 120)]));
            } else {
                $r->warn(lt('HTTP (Port 80) leitet nicht auf HTTPS um – Besucher können unverschlüsselt surfen.'));
            }
        } elseif ($first) {
            $r->info(lt('HTTP (Port 80) nicht erreichbar ({msg}) – unproblematisch, wenn HSTS gesetzt ist.', ['msg' => $first['error']]));
        }
        if ($http['stopped'] !== '') $r->warn($http['stopped']);
        if ($https !== $http && $https['stopped'] !== '') $r->warn($https['stopped']);
        if ($land['tlsError'] !== '') $r->error(lt('TLS-Problem: {msg} Browser zeigen eine Warnung. Details im SSL/TLS-Checker.', ['msg' => $land['tlsError']]));

        // Kette anzeigen
        $items = [];
        $hopsShown = $https === $http ? $http['hops'] : array_merge($http['hops'], $https['hops']);
        foreach ($hopsShown as $h) {
            $lvl = $h['error'] !== '' ? 'error' : ($h['status'] >= 400 ? 'warn' : null);
            $items[] = [($h['status'] ?: '–') . ' · ' . $h['url'] . ($h['location'] ? ' → ' . $h['location'] : '') . ($h['error'] ? ' (' . $h['error'] . ')' : '') . ' · ' . $h['total'] . ' ms', $lvl];
        }
        $r->list(lt('Aufrufe und Weiterleitungen'), $items, true);
        $redirects = count(array_filter($http['hops'], fn($h) => $h['location'] !== ''));
        if ($redirects > 2) $r->info(lt('{n} Weiterleitungen bis zur Startseite – jede kostet Zeit. Möglichst direkt auf die endgültige Adresse leiten.', ['n' => $redirects]));

        $hd = $land['headers'];
        $get = fn(string $k): string => implode(', ', $hd[$k] ?? []);
        if ($land['status'] >= 400) $r->error(lt('Die Startseite antwortet mit HTTP {code}.', ['code' => $land['status']]));

        // HSTS
        $rows = [];
        $hsts = $hd['strict-transport-security'][0] ?? '';
        if ($hsts === '') {
            $r->warn(lt('Kein HSTS (Strict-Transport-Security) – Browser könnten beim ersten Aufruf unverschlüsselt verbinden.'));
            $rows[] = ['Strict-Transport-Security', [lt('fehlt'), 'warn']];
        } else {
            $max = preg_match('~max-age\s*=\s*"?(\d+)~i', $hsts, $m) ? (int) $m[1] : 0;
            $sub = (bool) preg_match('~includesubdomains~i', $hsts);
            $pre = (bool) preg_match('~preload~i', $hsts);
            if ($max < 15552000) $r->warn(lt('HSTS mit kurzer Laufzeit ({days} Tage) – empfohlen sind mindestens 180 Tage, besser 1 Jahr.', ['days' => intdiv($max, 86400)]));
            else $r->ok(lt('HSTS aktiv ({days} Tage{sub}{pre}).', ['days' => intdiv($max, 86400), 'sub' => $sub ? ', includeSubDomains' : '', 'pre' => $pre ? ', preload' : '']));
            if ($pre && (!$sub || $max < 31536000)) $r->info(lt('„preload“ verlangt includeSubDomains und mindestens 1 Jahr Laufzeit.'));
            $rows[] = ['Strict-Transport-Security', [$hsts, $max >= 15552000 ? 'ok' : 'warn']];
        }
        // CSP und weitere Header
        $csp = $get('content-security-policy');
        $cspRo = $get('content-security-policy-report-only');
        if ($csp === '') {
            $r->warn($cspRo !== '' ? lt('Content-Security-Policy nur im Testmodus (Report-Only) – schützt noch nicht.') : lt('Keine Content-Security-Policy – sie begrenzt, welche Skripte laden dürfen, und bremst Cross-Site-Scripting aus.'));
            $rows[] = ['Content-Security-Policy', [$cspRo !== '' ? 'Report-Only' : lt('fehlt'), 'warn']];
        } else {
            $weak = preg_match("~(script-src|default-src)[^;]*'unsafe-inline'~i", $csp) && !preg_match("~(script-src|default-src)[^;]*('nonce-|'sha256-|'strict-dynamic')~i", $csp);
            if ($weak) $r->info(lt("Die Content-Security-Policy erlaubt 'unsafe-inline' für Skripte – das schwächt den Schutz deutlich."));
            else $r->ok(lt('Content-Security-Policy gesetzt.'));
            $rows[] = ['Content-Security-Policy', [mb_substr($csp, 0, 160) . (mb_strlen($csp) > 160 ? ' …' : ''), $weak ? 'info' : 'ok']];
        }
        $xcto = strtolower($get('x-content-type-options'));
        if ($xcto !== 'nosniff') $r->warn(lt('„X-Content-Type-Options: nosniff“ fehlt.'));
        $rows[] = ['X-Content-Type-Options', [$xcto ?: lt('fehlt'), $xcto === 'nosniff' ? 'ok' : 'warn']];
        $xfo = $get('x-frame-options');
        $fa = preg_match('~frame-ancestors\s+([^;]+)~i', $csp, $m) ? trim($m[1]) : '';
        if ($xfo === '' && $fa === '') $r->warn(lt('Kein Schutz gegen Einbetten in fremde Seiten (Clickjacking): „frame-ancestors“ in der CSP oder „X-Frame-Options“ setzen.'));
        $rows[] = [lt('Einbetten (X-Frame-Options / frame-ancestors)'), [trim($xfo . ($fa !== '' ? ($xfo !== '' ? ' · ' : '') . 'frame-ancestors ' . $fa : '')) ?: lt('fehlt'), $xfo || $fa ? 'ok' : 'warn']];
        foreach (['referrer-policy' => 'Referrer-Policy', 'permissions-policy' => 'Permissions-Policy', 'cross-origin-opener-policy' => 'Cross-Origin-Opener-Policy', 'cross-origin-resource-policy' => 'Cross-Origin-Resource-Policy'] as $k => $name) {
            $v = $get($k);
            $rows[] = [$name, [$v !== '' ? mb_substr($v, 0, 160) : lt('fehlt'), $v !== '' ? 'ok' : 'info']];
        }
        if ($get('referrer-policy') === '') $r->info(lt('Keine Referrer-Policy – Browser nutzen dann „strict-origin-when-cross-origin“; eine eigene Angabe macht es ausdrücklich.'));
        if ($get('permissions-policy') === '') $r->info(lt('Keine Permissions-Policy – damit lassen sich Kamera, Mikrofon, Standort usw. für die Seite abschalten.'));
        if (($x = $get('x-xss-protection')) !== '' && !str_starts_with($x, '0')) $r->info(lt('„X-XSS-Protection“ ist veraltet und kann entfernt (oder auf 0 gesetzt) werden.'));
        $r->table(lt('Sicherheits-Header der Startseite'), [lt('Header'), lt('Wert')], $rows);

        // Server-Angaben
        $server = $get('server');
        $powered = $get('x-powered-by');
        if (preg_match('~\d+\.\d+~', $server)) $r->warn(lt('Der Header „Server“ verrät die Version ({v}) – Angreifer finden so leichter passende Lücken.', ['v' => mb_substr($server, 0, 60)]));
        if ($powered !== '') $r->warn(lt('„X-Powered-By: {v}“ verrät die Technik – abschalten (z. B. expose_php = Off).', ['v' => mb_substr($powered, 0, 60)]));

        // Cookies (nur Namen und Attribute)
        $cookieRows = [];
        foreach (array_slice($hd['set-cookie'] ?? [], 0, 20) as $c) {
            $parts = array_map('trim', explode(';', $c));
            $name = mb_substr((string) strtok($parts[0], '='), 0, 60);
            $attrs = strtolower(implode(';', array_slice($parts, 1)));
            $secure = str_contains($attrs, 'secure');
            $httpOnly = str_contains($attrs, 'httponly');
            $same = preg_match('~samesite\s*=\s*(\w+)~', $attrs, $m) ? ucfirst($m[1]) : '';
            $cookieRows[] = [$name, [$secure ? lt('ja') : lt('nein'), $secure ? 'ok' : 'warn'], [$httpOnly ? lt('ja') : lt('nein'), $httpOnly ? 'ok' : 'info'], [$same ?: lt('nicht gesetzt'), $same !== '' && !($same === 'None' && !$secure) ? 'ok' : 'info']];
            if (!$secure) $r->warn(lt('Cookie „{name}“ ohne „Secure“ – könnte unverschlüsselt übertragen werden.', ['name' => $name]));
        }
        if ($cookieRows) {
            $r->table(lt('Cookies der Startseite (ohne Werte)'), [lt('Name'), 'Secure', 'HttpOnly', 'SameSite'], $cookieRows);
            $r->info(lt('Die Startseite setzt {n} Cookie(s) ohne Zutun des Besuchers – prüfe, ob dafür eine Einwilligung nötig ist.', ['n' => count($cookieRows)]));
        }

        // Leistung
        $enc = strtolower($get('content-encoding'));
        $alt = $get('alt-svc');
        $perf = [
            [lt('HTTP-Version'), $land['httpVersion'] . (str_contains($alt, 'h3') ? ' · ' . lt('HTTP/3 angeboten') : ''), in_array($land['httpVersion'], ['HTTP/2', 'HTTP/3'], true) ? 'ok' : 'info'],
            [lt('Antwortzeit (erstes Byte)'), $land['ttfb'] . ' ms', $land['ttfb'] < 600 ? 'ok' : ($land['ttfb'] < 1500 ? 'info' : 'warn')],
            [lt('Gesamtzeit'), $land['total'] . ' ms'],
            [lt('Kompression'), $enc ?: lt('keine'), $enc !== '' ? 'ok' : 'warn'],
            [lt('Adresse'), $land['url']],
            ['Server', $server],
        ];
        $r->kv(lt('Leistung & Server'), $perf);
        if (!in_array($land['httpVersion'], ['HTTP/2', 'HTTP/3'], true)) $r->info(lt('Die Website nutzt {v} – HTTP/2 lädt Seiten mit vielen Dateien spürbar schneller.', ['v' => $land['httpVersion'] ?: 'HTTP/1.x']));
        if ($land['ttfb'] >= 1500) $r->warn(lt('Die erste Antwort braucht {ms} ms – ein Seiten-Cache oder schnelleres Hosting hilft.', ['ms' => $land['ttfb']]));
        if ($enc === '' && $land['bytes'] > 1024) $r->warn(lt('Die Startseite wird unkomprimiert ausgeliefert – gzip oder Brotli spart viel Datenvolumen.'));

        $r->summary = match ($r->worst()) {
            'error' => lt('Die Website hat Probleme.'),
            'warn' => lt('Website erreichbar, Sicherheit ausbaufähig.'),
            default => lt('Website sicher eingerichtet.'),
        };
        return $r;
    }
}
