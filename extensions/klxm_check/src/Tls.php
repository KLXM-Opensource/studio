<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * TLS-Verbindungen und Zertifikate: HTTPS (443), SMTPS (465), SMTP mit STARTTLS (587, 25), IMAPS (993), POP3S (995).
 *
 * Sicherheit: Ziel ist immer eine vorher geprüfte öffentliche IP (IpGuard::resolve), verbunden wird direkt mit dieser IP
 * (peer_name/SNI = Hostname), nur feste Ports, Verbindungsaufbau 5 s, Lesen/Handshake 8 s, gelesene Daten gedeckelt.
 * Es werden keine Daten außer EHLO/STARTTLS/QUIT gesendet.
 */
final class Tls
{
    public const CONNECT_TIMEOUT = 5.0;
    public const IO_TIMEOUT = 8;

    /** Dienst => [Port, STARTTLS-Protokoll oder null, Bezeichnung] */
    public const SERVICES = [
        'https' => [443, null, 'HTTPS (443)'],
        'smtps' => [465, null, 'SMTPS (465)'],
        'submission' => [587, 'smtp', 'SMTP STARTTLS (587)'],
        'smtp' => [25, 'smtp', 'SMTP STARTTLS (25)'],
        'imaps' => [993, null, 'IMAPS (993)'],
        'pop3s' => [995, null, 'POP3S (995)'],
    ];

    /**
     * Ein TLS-Handshake. @return array{ok: bool, error: string, protocol: string, cipher: string, bits: int, chain: list<\OpenSSLCertificate>, ms: int, banner: string}
     */
    public static function handshake(string $host, string $ip, int $port, ?string $starttls, bool $verify, ?int $method = null, string $ciphers = ''): array
    {
        $out = ['ok' => false, 'error' => '', 'protocol' => '', 'cipher' => '', 'bits' => 0, 'chain' => [], 'ms' => 0, 'banner' => '', 'stage' => 'connect'];
        if (!IpGuard::portAllowed($port)) {
            $out['error'] = lt('Port nicht erlaubt.');
            return $out;
        }
        if (!IpGuard::isPublic($ip) && !IpGuard::devAllowed($ip)) {
            $out['error'] = lt('Nicht öffentliche Adresse – keine Verbindung.');
            return $out;
        }
        $ssl = [
            'peer_name' => $host, 'SNI_enabled' => true, 'verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify,
            'capture_peer_cert' => true, 'capture_peer_cert_chain' => true, 'disable_compression' => true,
        ];
        if ($ciphers !== '') $ssl['ciphers'] = $ciphers;
        if ($method !== null) $ssl['crypto_method'] = $method;
        $ctx = stream_context_create(['ssl' => $ssl, 'socket' => ['tcp_nodelay' => true]]);
        $target = 'tcp://' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip) . ':' . $port;
        $errors = [];
        set_error_handler(function (int $no, string $msg) use (&$errors): bool {
            $errors[] = $msg;
            return true;
        });
        $t0 = microtime(true);
        try {
            $s = stream_socket_client($target, $errno, $errstr, self::CONNECT_TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
            if (!$s) {
                $out['error'] = $errno === 110 || $errno === 60 || stripos((string) $errstr, 'timed out') !== false
                    ? lt('Zeitüberschreitung beim Verbindungsaufbau (Port {port}).', ['port' => $port])
                    : lt('Verbindung zu Port {port} nicht möglich ({msg}).', ['port' => $port, 'msg' => self::clean((string) $errstr)]);
                return $out;
            }
            stream_set_timeout($s, self::IO_TIMEOUT);
            if ($starttls === 'smtp') {
                $out['stage'] = 'smtp';
                $banner = self::smtpRead($s);
                $out['banner'] = substr($banner, 0, 200);
                if (!str_starts_with($banner, '220')) {
                    $out['error'] = $banner === '' ? lt('Der Mailserver hat nicht geantwortet.') : lt('Unerwartete Begrüßung des Mailservers.');
                    fclose($s);
                    return $out;
                }
                fwrite($s, 'EHLO ' . Check::ehloName() . "\r\n");
                $ehlo = self::smtpRead($s);
                if (!str_starts_with($ehlo, '250')) {
                    $out['error'] = lt('Der Mailserver hat EHLO abgelehnt.');
                    fclose($s);
                    return $out;
                }
                if (!preg_match('~^250[ -]STARTTLS~mi', $ehlo)) {
                    $out['error'] = lt('Der Mailserver bietet kein STARTTLS an – Mails würden unverschlüsselt übertragen.');
                    $out['stage'] = 'nostarttls';
                    @fwrite($s, "QUIT\r\n");
                    fclose($s);
                    return $out;
                }
                fwrite($s, "STARTTLS\r\n");
                if (!str_starts_with(self::smtpRead($s), '220')) {
                    $out['error'] = lt('STARTTLS wurde vom Mailserver abgelehnt.');
                    fclose($s);
                    return $out;
                }
            }
            $out['stage'] = 'tls';
            $ok = stream_socket_enable_crypto($s, true, $method ?? (STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT));
            $out['ms'] = (int) round((microtime(true) - $t0) * 1000);
            if ($ok !== true) {
                $out['error'] = self::clean(implode(' ', $errors)) ?: lt('TLS-Handshake fehlgeschlagen.');
                fclose($s);
                return $out;
            }
            $meta = stream_get_meta_data($s)['crypto'] ?? [];
            $params = stream_context_get_params($s)['options']['ssl'] ?? [];
            $chain = (array) ($params['peer_certificate_chain'] ?? []);
            if (!$chain && isset($params['peer_certificate'])) $chain = [$params['peer_certificate']];
            $out = array_merge($out, ['ok' => true, 'protocol' => (string) ($meta['protocol'] ?? ''), 'cipher' => (string) ($meta['cipher_name'] ?? ''),
                'bits' => (int) ($meta['cipher_bits'] ?? 0), 'chain' => $chain]);
            if ($starttls === 'smtp') @fwrite($s, "QUIT\r\n");
            fclose($s);
            return $out;
        } finally {
            restore_error_handler();
        }
    }

    /** SMTP-Antwort lesen (mehrzeilig „250-…“ bis „250 …“), höchstens 32 KB bzw. 100 Zeilen */
    private static function smtpRead($s): string
    {
        $buf = '';
        for ($i = 0; $i < 100 && strlen($buf) < 32768; $i++) {
            $line = fgets($s, 1024);
            if ($line === false) break;
            $buf .= $line;
            if (preg_match('~^\d{3} ~', $line)) break;
        }
        return $buf;
    }

    /** OpenSSL-/PHP-Meldung kürzen (ohne Dateipfade und Funktionsnamen) */
    public static function clean(string $msg): string
    {
        $msg = (string) preg_replace('~^.*?(stream_socket_\w+\(\)|stream_socket_enable_crypto\(\)):\s*~', '', $msg);
        $msg = (string) preg_replace('~ in /\S+ on line \d+~', '', $msg);
        $msg = (string) preg_replace('~error:[0-9A-F]+:[^:]*:[^:]*:~i', '', $msg);
        $msg = (string) preg_replace('~SSL operation failed with code \d+\.\s*OpenSSL Error messages:\s*~i', '', $msg);
        $msg = (string) preg_replace('~\s+~', ' ', $msg);
        return trim(mb_substr($msg, 0, 240));
    }

    // ------------------------------------------------------------------ Zertifikate

    /** Angaben eines Zertifikats für die Anzeige und Bewertung */
    public static function certInfo(\OpenSSLCertificate $c, string $host): array
    {
        $p = openssl_x509_parse($c, false) ?: [];
        $subj = (array) ($p['subject'] ?? []);
        $iss = (array) ($p['issuer'] ?? []);
        $sans = [];
        foreach (explode(',', (string) ($p['extensions']['subjectAltName'] ?? '')) as $x) {
            $x = trim($x);
            if (str_starts_with($x, 'DNS:')) $sans[] = strtolower(substr($x, 4));
            elseif (str_starts_with($x, 'IP Address:')) $sans[] = substr($x, 11);
        }
        $pub = @openssl_pkey_get_public($c);
        $kd = $pub ? (openssl_pkey_get_details($pub) ?: []) : [];
        $ktype = match ($kd['type'] ?? -1) {
            OPENSSL_KEYTYPE_RSA => 'RSA', OPENSSL_KEYTYPE_EC => 'EC', OPENSSL_KEYTYPE_DSA => 'DSA',
            default => defined('OPENSSL_KEYTYPE_ED25519') && ($kd['type'] ?? -1) === OPENSSL_KEYTYPE_ED25519 ? 'Ed25519' : '?',
        };
        $to = (int) ($p['validTo_time_t'] ?? 0);
        $from = (int) ($p['validFrom_time_t'] ?? 0);
        $selfSigned = self::dn($subj) === self::dn($iss) && $pub && @openssl_x509_verify($c, $pub) === 1;
        $sig = (string) ($p['signatureTypeLN'] ?? $p['signatureTypeSN'] ?? '');
        return [
            'cn' => self::first($subj['commonName'] ?? ''), 'org' => self::first($subj['organizationName'] ?? ''),
            'subject' => self::dn($subj), 'issuer' => self::dn($iss),
            'issuerName' => trim(self::first($iss['organizationName'] ?? '') . ' ' . (self::first($iss['commonName'] ?? '') !== '' ? '(' . self::first($iss['commonName'] ?? '') . ')' : '')),
            'sans' => $sans, 'from' => $from, 'to' => $to, 'days' => (int) floor(($to - time()) / 86400),
            'keyType' => $ktype, 'keyBits' => (int) ($kd['bits'] ?? 0), 'curve' => (string) ($kd['ec']['curve_name'] ?? ''),
            'sig' => $sig, 'serial' => (string) ($p['serialNumberHex'] ?? ''), 'selfSigned' => $selfSigned,
            'match' => self::hostMatches($host, $sans, self::first($subj['commonName'] ?? '')),
            'ca' => str_contains(strtolower((string) ($p['extensions']['basicConstraints'] ?? '')), 'ca:true'),
        ];
    }

    private static function first(mixed $v): string
    {
        return is_array($v) ? (string) ($v[0] ?? '') : (string) $v;
    }

    private static function dn(array $parts): string
    {
        $map = ['commonName' => 'CN', 'organizationName' => 'O', 'organizationalUnitName' => 'OU', 'countryName' => 'C', 'stateOrProvinceName' => 'ST', 'localityName' => 'L'];
        $out = [];
        foreach ($parts as $k => $v) $out[] = ($map[$k] ?? $k) . '=' . (is_array($v) ? implode('+', $v) : $v);
        return implode(', ', $out);
    }

    /** Passt der Hostname zu den SANs (Platzhalter nur als ganzes linkes Label, genau eine Ebene)? CN nur ohne SANs. */
    public static function hostMatches(string $host, array $sans, string $cn = ''): bool
    {
        $host = strtolower(rtrim($host, '.'));
        $names = $sans ?: ($cn !== '' ? [strtolower($cn)] : []);
        foreach ($names as $n) {
            $n = strtolower(rtrim((string) $n, '.'));
            if ($n === $host) return true;
            if (str_starts_with($n, '*.') && substr_count($n, '.') >= 2) {
                $suffix = substr($n, 1);
                if (str_ends_with($host, $suffix)) {
                    $left = substr($host, 0, -strlen($suffix));
                    if ($left !== '' && !str_contains($left, '.')) return true;
                }
            }
        }
        return false;
    }

    // ------------------------------------------------------------------ SSL/TLS-Checker

    public static function check(string $host, string $service, Dns $dns): Result
    {
        if (!isset(self::SERVICES[$service])) throw new CheckException(lt('Unbekannter Dienst.'));
        [$port, $starttls, $label] = self::SERVICES[$service];
        $r = new Result('tls', lt('SSL/TLS: {host} · {service}', ['host' => $host, 'service' => $label]));
        $ips = IpGuard::resolve($host, $dns);
        $hs = null;
        $ip = '';
        foreach (array_slice($ips, 0, 2) as $cand) {
            $ip = $cand;
            $hs = self::handshake($host, $ip, $port, $starttls, false);
            if ($hs['ok'] || $hs['stage'] !== 'connect') break;
        }
        if (!$hs || !$hs['ok']) {
            $r->error($hs['error'] ?? lt('Keine Verbindung möglich.'));
            if ($port === 25 && ($hs['stage'] ?? '') === 'connect') {
                $r->info(lt('Viele Hoster sperren ausgehende Verbindungen auf Port 25. Das sagt nichts über deinen Mailserver – prüfe alternativ Port 587 oder 465.'));
                $r->status = 'info';
            }
            $r->summary = lt('Keine TLS-Verbindung zustande gekommen.');
            return $r;
        }
        $leaf = $hs['chain'][0] ?? null;
        if (!$leaf) {
            $r->error(lt('Der Server hat kein Zertifikat geschickt.'));
            return $r;
        }
        $c = self::certInfo($leaf, $host);
        // Zweiter Handshake mit Prüfung gegen die Zertifizierungsstellen des Servers
        $v = self::handshake($host, $ip, $port, $starttls, true);
        $trusted = $v['ok'];

        if ($c['days'] < 0) $r->error(lt('Das Zertifikat ist seit {n} Tag(en) abgelaufen.', ['n' => -$c['days']]));
        elseif ($c['from'] > time()) $r->error(lt('Das Zertifikat ist noch nicht gültig.'));
        elseif ($c['days'] < 21) $r->warn(lt('Das Zertifikat läuft in {n} Tag(en) ab – rechtzeitig erneuern (bzw. automatische Verlängerung prüfen).', ['n' => $c['days']]));
        else $r->ok(lt('Zertifikat gültig bis {date} (noch {n} Tage).', ['date' => date_local($c['to']), 'n' => $c['days']]));
        if (!$c['match']) $r->error(lt('Das Zertifikat passt nicht zum Namen {host} – Browser und Mailserver zeigen eine Warnung.', ['host' => $host]));
        else $r->ok(lt('Zertifikat passt zum Namen {host}.', ['host' => $host]));
        if ($c['selfSigned']) {
            $r->error(lt('Selbst signiertes Zertifikat – ihm vertraut kein Browser. Kostenlose, vertrauenswürdige Zertifikate gibt es z. B. von Let’s Encrypt.'));
        } elseif ($trusted) {
            $r->ok(lt('Zertifikatskette vertrauenswürdig.'));
        } elseif ($c['days'] >= 0 && $c['from'] <= time() && $c['match']) {
            // PHP nennt den Grund nicht – aus der geschickten Kette ableiten
            $last = self::certInfo($hs['chain'][count($hs['chain']) - 1], $host);
            if (count($hs['chain']) === 1 || !$last['selfSigned']) {
                $r->error(lt('Die Zertifikatskette ist unvollständig (Zwischenzertifikat fehlt) oder die Zertifizierungsstelle ist unbekannt. Der Server muss die Zwischenzertifikate mitschicken – manche Browser gleichen das aus, Mailserver und Apps oft nicht.'));
            } else {
                $r->error(lt('Die Kette endet bei einer nicht vertrauenswürdigen Stammzertifizierungsstelle ({ca}) – Browser zeigen eine Warnung.', ['ca' => $last['cn'] ?: $last['subject']]));
            }
        }
        // Protokoll
        $proto = $hs['protocol'];
        if (in_array($proto, ['TLSv1', 'TLSv1.1', 'SSLv3'], true)) $r->error(lt('Ausgehandelt wurde {p} – veraltet und unsicher. Mindestens TLS 1.2 verwenden.', ['p' => $proto]));
        elseif ($proto === 'TLSv1.3') $r->ok(lt('TLS 1.3 wird verwendet.'));
        elseif ($proto === 'TLSv1.2') $r->info(lt('TLS 1.2 wird verwendet – in Ordnung; TLS 1.3 wäre moderner.'));
        // Alte Protokolle (nur prüfbar, wenn OpenSSL des Servers sie noch anbietet)
        $old = [];
        foreach (['TLS 1.0' => [STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT, 'TLSv1'], 'TLS 1.1' => [STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT, 'TLSv1.1']] as $name => [$m, $want]) {
            $x = self::handshake($host, $ip, $port, $starttls, false, $m, 'DEFAULT:@SECLEVEL=0');
            $err = strtolower($x['error']);
            // „no protocols available“: das OpenSSL dieses Servers kann das alte Protokoll selbst nicht mehr → nicht prüfbar
            $old[$name] = $x['ok'] && $x['protocol'] === $want ? true : ($x['stage'] !== 'tls' || str_contains($err, 'no protocols available') || str_contains($err, 'no ciphers available') ? null : false);
        }
        $accepted = array_keys(array_filter($old, fn($v) => $v === true));
        if ($accepted) $r->warn(lt('Der Server akzeptiert noch {list} – veraltet; BSI und Mozilla empfehlen, nur TLS 1.2 und 1.3 anzubieten.', ['list' => implode(lt(' und '), $accepted)]));
        // Schlüssel und Signatur
        if ($c['keyType'] === 'RSA' && $c['keyBits'] && $c['keyBits'] < 2048) $r->warn(lt('RSA-Schlüssel mit nur {bits} Bit – mindestens 2048 Bit verwenden.', ['bits' => $c['keyBits']]));
        if (preg_match('~sha1|md5~i', $c['sig'])) $r->warn(lt('Das Zertifikat ist mit einem veralteten Verfahren signiert ({sig}).', ['sig' => $c['sig']]));

        $r->kv(lt('Zertifikat'), [
            [lt('Name (CN)'), $c['cn']],
            [lt('Alternative Namen (SAN)'), implode(', ', array_slice($c['sans'], 0, 30)) . (count($c['sans']) > 30 ? ' … (+' . (count($c['sans']) - 30) . ')' : '')],
            [lt('Aussteller'), $c['issuerName'] ?: $c['issuer']],
            [lt('Gültig ab'), $c['from'] ? date_local($c['from']) : ''],
            [lt('Gültig bis'), $c['to'] ? date_local($c['to']) . ' (' . lt('{n} Tage', ['n' => $c['days']]) . ')' : '', $c['days'] < 0 ? 'error' : ($c['days'] < 21 ? 'warn' : 'ok')],
            [lt('Schlüssel'), $c['keyType'] . ($c['keyBits'] ? ' ' . $c['keyBits'] . ' Bit' : '') . ($c['curve'] ? ' (' . $c['curve'] . ')' : '')],
            [lt('Signatur'), $c['sig']],
            [lt('Seriennummer'), $c['serial']],
            [lt('Passt zum Namen'), $c['match'] ? lt('ja') : lt('nein'), $c['match'] ? 'ok' : 'error'],
            [lt('Vertrauenswürdig'), $trusted ? lt('ja') : lt('nein'), $trusted ? 'ok' : 'error'],
        ]);
        $r->kv(lt('Verbindung'), [
            [lt('Server-Adresse'), $ip],
            [lt('Protokoll'), $proto],
            [lt('Verschlüsselung'), $hs['cipher'] . ($hs['bits'] ? ' (' . $hs['bits'] . ' Bit)' : '')],
            [lt('Handshake'), $hs['ms'] . ' ms'],
            ['TLS 1.0', $old['TLS 1.0'] === null ? lt('nicht prüfbar') : ($old['TLS 1.0'] ? lt('angeboten') : lt('abgeschaltet')), $old['TLS 1.0'] ? 'warn' : ($old['TLS 1.0'] === null ? null : 'ok')],
            ['TLS 1.1', $old['TLS 1.1'] === null ? lt('nicht prüfbar') : ($old['TLS 1.1'] ? lt('angeboten') : lt('abgeschaltet')), $old['TLS 1.1'] ? 'warn' : ($old['TLS 1.1'] === null ? null : 'ok')],
        ]);
        $chainRows = [];
        foreach (array_slice($hs['chain'], 0, 6) as $i => $cert) {
            $ci = self::certInfo($cert, $host);
            $chainRows[] = [(string) ($i + 1), $ci['cn'] ?: $ci['subject'], $ci['issuerName'] ?: $ci['issuer'], [$ci['to'] ? date_local($ci['to']) : '–', $ci['days'] < 0 ? 'error' : null]];
        }
        $r->table(lt('Zertifikatskette (vom Server geschickt)'), ['#', lt('Inhaber'), lt('Aussteller'), lt('Gültig bis')], $chainRows,
            lt('Das Stammzertifikat schickt ein Server normalerweise nicht mit – es steckt im Browser bzw. Betriebssystem.'));

        $r->summary = match ($r->worst()) {
            'error' => lt('Probleme mit Zertifikat oder Verbindung gefunden.'),
            'warn' => lt('TLS funktioniert, es gibt aber Verbesserungsbedarf.'),
            default => lt('TLS ist sauber eingerichtet.'),
        };
        $r->data = ['days' => $c['days'], 'protocol' => $proto, 'trusted' => $trusted, 'match' => $c['match']];
        return $r;
    }
}
