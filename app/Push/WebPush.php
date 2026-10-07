<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

/**
 * Web Push ohne Zusatzbibliothek – nur PHP openssl, hash und curl (Rückfall: Streams):
 *
 *  - RFC 8292 (VAPID): JWT mit ES256 (ECDSA P-256 + SHA-256), Kopfzeile „Authorization: vapid t=<JWT>, k=<öffentlicher Schlüssel>“
 *  - RFC 8291 (Nachrichtenverschlüsselung) mit RFC 8188 „aes128gcm“: Wegwerf-Schlüssel des Servers + ECDH (P-256) mit dem
 *    Schlüssel des Browsers (p256dh), HKDF-SHA-256 mit dem Auth-Geheimnis, AES-128-GCM, ein Datensatz (rs 4096)
 *  - RFC 8030 (Zustellung): POST an den Endpunkt des Push-Dienstes, TTL, Urgency, Topic; 201 = angenommen,
 *    404/410 = Abo ungültig (löschen), 413 = zu groß, 429 = später erneut (Retry-After), 5xx = später erneut
 *
 * Schlüssel liegen roh vor: öffentlich 65 Byte (0x04 || X || Y, unkomprimierter Punkt), privat 32 Byte (d). Für openssl werden
 * daraus PEM-Strukturen gebaut (SubjectPublicKeyInfo bzw. ECPrivateKey nach RFC 5915).
 * Selbsttest (Rundlauf, RFC-8291-Testvektor, JWT-Prüfung): php bin/console push:selftest
 */
final class WebPush
{
    /** DER-Kopf von SubjectPublicKeyInfo für P-256 (id-ecPublicKey, prime256v1) bis einschließlich BIT STRING – danach 65 Byte Punkt */
    private const SPKI_P256 = '3059301306072a8648ce3d020106082a8648ce3d030107034200';
    /** Datensatzgröße (RFC 8188); eine Nachricht = ein Datensatz */
    public const RECORD_SIZE = 4096;
    /** Höchstlänge des Klartexts: 4096 − 86 Kopf − 16 Tag − 1 Trenner, mit Reserve für Dienste mit kleinerer Grenze */
    public const MAX_PAYLOAD = 3000;

    // ================================================================= Base64url

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    /** Base64url (auch mit „=“ bzw. Standard-Alphabet) → Bytes, false bei ungültiger Eingabe */
    public static function unb64u(string $s): string|false
    {
        $s = strtr(trim($s), '-_', '+/');
        if ($s === '' || !preg_match('~^[A-Za-z0-9+/]+=*$~', $s)) return false;
        $s = rtrim($s, '=');
        return base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    // ================================================================= Schlüssel

    /** Neues P-256-Schlüsselpaar: ['public' => 65 Byte, 'private' => 32 Byte] */
    public static function generateKeys(): array
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($k === false) throw new \RuntimeException('openssl: P-256-Schlüssel konnte nicht erzeugt werden (' . openssl_error_string() . ').');
        $ec = openssl_pkey_get_details($k)['ec'] ?? null;
        if (!$ec) throw new \RuntimeException('openssl: Schlüsseldetails fehlen.');
        $p = fn(string $v) => str_pad($v, 32, "\0", STR_PAD_LEFT);
        return ['public' => "\x04" . $p($ec['x']) . $p($ec['y']), 'private' => $p($ec['d'])];
    }

    /** Ist das ein gültiger öffentlicher P-256-Punkt (65 Byte, 0x04, auf der Kurve)? */
    public static function validPublic(string $raw): bool
    {
        if (strlen($raw) !== 65 || $raw[0] !== "\x04") return false;
        return self::publicKey($raw, false) !== null;
    }

    /** Öffentlicher Schlüssel (roh) → openssl-Schlüssel */
    public static function publicKey(string $raw, bool $throw = true): ?\OpenSSLAsymmetricKey
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(self::SPKI_P256) . $raw), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $k = strlen($raw) === 65 ? @openssl_pkey_get_public($pem) : false;
        if ($k === false) {
            while (openssl_error_string() !== false);   // Fehlerpuffer von openssl leeren
            if ($throw) throw new \InvalidArgumentException('Ungültiger öffentlicher P-256-Schlüssel.');
            return null;
        }
        return $k;
    }

    /** Privater Schlüssel (roh d + öffentlicher Punkt) → openssl-Schlüssel (ECPrivateKey, RFC 5915) */
    public static function privateKey(string $d, string $public): \OpenSSLAsymmetricKey
    {
        if (strlen($d) !== 32 || strlen($public) !== 65) throw new \InvalidArgumentException('Ungültiger privater P-256-Schlüssel.');
        $der = "\x30\x77" . "\x02\x01\x01" . "\x04\x20" . $d
            . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
            . "\xa1\x44\x03\x42\x00" . $public;
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
        $k = @openssl_pkey_get_private($pem);
        if ($k === false) {
            while (openssl_error_string() !== false);
            throw new \InvalidArgumentException('Ungültiger privater P-256-Schlüssel.');
        }
        return $k;
    }

    /** Gehören privater und öffentlicher Schlüssel zusammen? (öffentlichen Punkt aus d neu berechnen lassen) */
    public static function keysMatch(string $d, string $public): bool
    {
        try {
            // Signatur mit d prüfen gegen den öffentlichen Punkt – unabhängig davon, was openssl aus dem PEM übernimmt
            $msg = random_bytes(32);
            if (!openssl_sign($msg, $sig, self::privateKey($d, $public), OPENSSL_ALGO_SHA256)) return false;
            return openssl_verify($msg, $sig, self::publicKey($public), OPENSSL_ALGO_SHA256) === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    // ================================================================= VAPID (RFC 8292)

    /** JWT (ES256) mit den Angaben $claims, signiert mit dem VAPID-Schlüsselpaar */
    public static function jwt(array $claims, array $keys): string
    {
        $input = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES))
            . '.' . self::b64u(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if (!openssl_sign($input, $der, self::privateKey($keys['private'], $keys['public']), OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('openssl: Signatur fehlgeschlagen.');
        }
        return $input . '.' . self::b64u(self::derToRaw($der));
    }

    /** JWT prüfen (Signatur ES256 mit dem öffentlichen Schlüssel) → Angaben oder null */
    public static function verifyJwt(string $jwt, string $public): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return null;
        $head = json_decode((string) self::unb64u($parts[0]), true);
        $sig = self::unb64u($parts[2]);
        if (($head['alg'] ?? '') !== 'ES256' || $sig === false || strlen($sig) !== 64) return null;
        $ok = openssl_verify($parts[0] . '.' . $parts[1], self::rawToDer($sig), self::publicKey($public), OPENSSL_ALGO_SHA256);
        if ($ok !== 1) return null;
        $claims = json_decode((string) self::unb64u($parts[1]), true);
        return is_array($claims) ? $claims : null;
    }

    /** Kopfzeile Authorization für einen Endpunkt (aud = Ursprung des Push-Dienstes, exp ≤ 24 h, sub = Kontakt) */
    public static function vapidHeader(string $endpoint, string $subject, array $keys, ?int $exp = null): string
    {
        $u = parse_url($endpoint);
        $aud = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : '');
        $jwt = self::jwt(['aud' => $aud, 'exp' => $exp ?? time() + 12 * 3600, 'sub' => $subject], $keys);
        return 'vapid t=' . $jwt . ', k=' . self::b64u($keys['public']);
    }

    /** DER-kodierte ECDSA-Signatur (SEQUENCE { INTEGER r, INTEGER s }) → 64 Byte r || s */
    public static function derToRaw(string $der): string
    {
        $pos = 0;
        $read = function () use ($der, &$pos): string {
            if (($der[$pos++] ?? '') !== "\x02") throw new \RuntimeException('Ungültige DER-Signatur.');
            $len = ord($der[$pos++]);
            $v = substr($der, $pos, $len);
            $pos += $len;
            return str_pad(ltrim($v, "\0"), 32, "\0", STR_PAD_LEFT);
        };
        if (($der[0] ?? '') !== "\x30") throw new \RuntimeException('Ungültige DER-Signatur.');
        $pos = ord($der[1]) === 0x81 ? 3 : 2;   // Länge in Kurz- oder Langform (bei P-256 höchstens 72 Byte)
        $r = $read();
        $s = $read();
        if (strlen($r) !== 32 || strlen($s) !== 32) throw new \RuntimeException('Ungültige DER-Signatur.');
        return $r . $s;
    }

    /** 64 Byte r || s → DER (für openssl_verify) */
    public static function rawToDer(string $raw): string
    {
        $int = function (string $v): string {
            $v = ltrim($v, "\0");
            if ($v === '' || ord($v[0]) > 0x7f) $v = "\0" . $v;
            return "\x02" . chr(strlen($v)) . $v;
        };
        $body = $int(substr($raw, 0, 32)) . $int(substr($raw, 32, 32));
        return "\x30" . chr(strlen($body)) . $body;
    }

    // ================================================================= Verschlüsselung (RFC 8291 / RFC 8188 aes128gcm)

    /**
     * Klartext für einen Browser verschlüsseln. $uaPublic = p256dh (65 Byte), $auth = Auth-Geheimnis (16 Byte).
     * $as (Wegwerf-Schlüsselpaar des Servers) und $salt nur für Tests vorgeben; $pad = Füllbytes (verschleiern die Länge).
     */
    public static function encrypt(string $plain, string $uaPublic, string $auth, ?array $as = null, ?string $salt = null, int $pad = 0): string
    {
        if (strlen($plain) > self::MAX_PAYLOAD) throw new \LengthException('Nachricht zu lang für Web Push.');
        $as ??= self::generateKeys();
        $salt ??= random_bytes(16);
        $secret = openssl_pkey_derive(self::publicKey($uaPublic), self::privateKey($as['private'], $as['public']));
        if ($secret === false || strlen($secret) !== 32) throw new \RuntimeException('openssl: ECDH fehlgeschlagen.');
        [$cek, $nonce] = self::derive($secret, $auth, $uaPublic, $as['public'], $salt);
        $tag = '';
        $ct = openssl_encrypt($plain . "\x02" . str_repeat("\0", max(0, $pad)), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ct === false) throw new \RuntimeException('openssl: AES-128-GCM fehlgeschlagen.');
        return $salt . pack('N', self::RECORD_SIZE) . chr(65) . $as['public'] . $ct . $tag;
    }

    /**
     * Gegenstück zu encrypt() aus Sicht des Browsers (für den Selbsttest): Nachricht mit dem privaten Schlüssel des Empfängers
     * entschlüsseln → Klartext oder null (manipuliert, falscher Schlüssel, Format).
     */
    public static function decrypt(string $body, string $uaPrivate, string $uaPublic, string $auth): ?string
    {
        if (strlen($body) < 21 + 65 + 17) return null;
        $salt = substr($body, 0, 16);
        $idlen = ord($body[20]);
        $keyid = substr($body, 21, $idlen);
        $data = substr($body, 21 + $idlen);
        if ($idlen !== 65 || strlen($data) < 17) return null;
        try {
            $secret = openssl_pkey_derive(self::publicKey($keyid), self::privateKey($uaPrivate, $uaPublic));
        } catch (\Throwable) {
            return null;
        }
        if ($secret === false) return null;
        [$cek, $nonce] = self::derive($secret, $auth, $uaPublic, $keyid, $salt);
        $plain = openssl_decrypt(substr($data, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($data, -16));
        if ($plain === false) return null;
        $plain = rtrim($plain, "\0");
        return str_ends_with($plain, "\x02") ? substr($plain, 0, -1) : null;
    }

    /** Inhaltsschlüssel (16 Byte) und Nonce (12 Byte) nach RFC 8291 Abschnitt 3.4 */
    private static function derive(string $secret, string $auth, string $uaPublic, string $asPublic, string $salt): array
    {
        $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $uaPublic . $asPublic, $auth);
        return [hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt), hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt)];
    }

    // ================================================================= Zustellung (RFC 8030)

    /**
     * Mehrere Nachrichten senden (curl_multi, gleichzeitig $parallel). Jede Aufgabe:
     *   ['id' => …, 'endpoint' => URL, 'p256dh' => roh, 'auth' => roh, 'payload' => Klartext (JSON), 'ttl' => s, 'urgency' => very-low|low|normal|high,
     *    'topic' => ?string (≤ 32 Zeichen Base64url – ersetzt beim Dienst eine noch nicht zugestellte Nachricht gleichen Themas)]
     * → [id => ['code' => HTTP-Status (0 = keine Verbindung), 'error' => ?string, 'retry_after' => ?int]]
     */
    public static function sendAll(array $jobs, array $keys, string $subject, int $parallel = 8, int $timeout = 10): array
    {
        $out = [];
        $auth = [];   // ein JWT je Ursprung (aud) und Lauf
        $requests = [];
        foreach ($jobs as $j) {
            try {
                $origin = (string) preg_replace('~^(https://[^/]+).*$~', '$1', (string) $j['endpoint']);
                $auth[$origin] ??= self::vapidHeader((string) $j['endpoint'], $subject, $keys);
                $body = self::encrypt((string) $j['payload'], (string) $j['p256dh'], (string) $j['auth']);
                $headers = ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: ' . max(0, (int) ($j['ttl'] ?? 86400)),
                    'Urgency: ' . (in_array($j['urgency'] ?? '', ['very-low', 'low', 'normal', 'high'], true) ? $j['urgency'] : 'normal'),
                    'Authorization: ' . $auth[$origin], 'Content-Length: ' . strlen($body)];
                if (!empty($j['topic']) && preg_match('~^[A-Za-z0-9_-]{1,32}$~', (string) $j['topic'])) $headers[] = 'Topic: ' . $j['topic'];
                $requests[$j['id']] = ['url' => (string) $j['endpoint'], 'headers' => $headers, 'body' => $body];
            } catch (\Throwable $e) {
                $out[$j['id']] = ['code' => 0, 'error' => mb_substr($e->getMessage(), 0, 180), 'retry_after' => null, 'fatal' => true];
            }
        }
        if (!$requests) return $out;
        return $out + (function_exists('curl_multi_init') ? self::curlAll($requests, $parallel, $timeout) : self::streamAll($requests, $timeout));
    }

    private static function curlAll(array $requests, int $parallel, int $timeout): array
    {
        $out = [];
        $mh = curl_multi_init();
        $queue = $requests;
        $active = [];
        $retry = [];
        $add = function () use (&$queue, &$active, &$retry, $mh, $timeout): void {
            $id = array_key_first($queue);
            $r = $queue[$id];
            unset($queue[$id]);
            $ch = curl_init($r['url']);
            $retry[$id] = null;
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $r['body'], CURLOPT_HTTPHEADER => $r['headers'], CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_USERAGENT => 'KLXM-Studio-Push/' . (defined('CMS_VERSION') ? CMS_VERSION : '1'),
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$retry, $id): int {
                    if (preg_match('~^retry-after:\s*(\d+)~i', $line, $m)) $retry[$id] = (int) $m[1];
                    return strlen($line);
                },
            ]);
            curl_multi_add_handle($mh, $ch);
            $active[(int) spl_object_id($ch)] = [$id, $ch];
        };
        while ($queue && count($active) < $parallel) $add();
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 1.0);
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                [$id] = $active[(int) spl_object_id($ch)];
                $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $err = $info['result'] !== CURLE_OK ? curl_error($ch) : null;
                $resp = (string) curl_multi_getcontent($ch);
                if ($err === null && ($code < 200 || $code >= 300)) $err = 'HTTP ' . $code . ($resp !== '' ? ': ' . mb_substr(trim(strip_tags($resp)), 0, 140) : '');
                $out[$id] = ['code' => $code, 'error' => $err, 'retry_after' => $retry[$id] ?? null];
                curl_multi_remove_handle($mh, $ch);
                unset($active[(int) spl_object_id($ch)]);
                if ($queue) $add();
            }
        } while (($running || $active) && $status === CURLM_OK);
        curl_multi_close($mh);
        return $out;
    }

    /** Rückfall ohne curl: nacheinander über HTTPS-Streams */
    private static function streamAll(array $requests, int $timeout): array
    {
        $out = [];
        foreach ($requests as $id => $r) {
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $r['headers']), 'content' => $r['body'],
                'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 0], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $resp = @file_get_contents($r['url'], false, $ctx);
            $code = 0;
            $retry = null;
            foreach ((array) ($http_response_header ?? []) as $h) {
                if (preg_match('~^HTTP/\S+\s+(\d+)~', $h, $m)) $code = (int) $m[1];
                if (preg_match('~^retry-after:\s*(\d+)~i', $h, $m)) $retry = (int) $m[1];
            }
            $out[$id] = ['code' => $code, 'error' => $code >= 200 && $code < 300 ? null : ($code ? 'HTTP ' . $code . ': ' . mb_substr(trim(strip_tags((string) $resp)), 0, 140) : 'keine Verbindung'),
                'retry_after' => $retry];
        }
        return $out;
    }
}
