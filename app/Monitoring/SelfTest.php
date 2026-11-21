<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Monitoring;

use Core\Testing\Check;

/** php bin/console monitor:selftest – Signatur status-v1 (gültig, verändert, Zeit, Wiederholung, Schlüssel) und Felder der Antwort */
final class SelfTest
{
    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = Check::eq($ok, $fails);
        $kp = sodium_crypto_sign_keypair();
        $sec = sodium_crypto_sign_secretkey($kp);
        $pub = sodium_crypto_sign_publickey($kp);
        $eq('Schlüssel: Format hin und zurück', Signature::parseKey(Signature::formatKey($pub)), $pub);
        $eq('Schlüssel: falsches Format', Signature::parseKey('ed25519:abc'), null);
        $url = 'https://www.example.org/api/monitoring/status';
        $path = '/api/monitoring/status';
        $eq('Host ohne Standard-Port', [Signature::hostOf($url), Signature::hostOf('http://Example.org:8153/x')], ['www.example.org', 'example.org:8153']);
        $seenSet = [];
        $seen = function (string $kid, string $nonce) use (&$seenSet): bool {
            if (isset($seenSet["$kid|$nonce"])) return true;
            $seenSet["$kid|$nonce"] = 1;
            return false;
        };
        $hdr = function (array $lines): array {
            $h = [];
            foreach ($lines as $l) { [$k, $v] = explode(': ', $l, 2); $h[strtolower($k)] = $v; }
            return $h;
        };
        $h = $hdr(Signature::headers($sec, $url));
        $eq('Gültige Anfrage', Signature::verify($h, 'GET', 'www.example.org', $path, [$pub], $seen), null);
        $eq('Wiederholung abgewiesen', Signature::verify($h, 'GET', 'www.example.org', $path, [$pub], $seen), 'replay');
        $h = $hdr(Signature::headers($sec, $url));
        $eq('Anderer Pfad abgewiesen', Signature::verify($h, 'GET', 'www.example.org', $path . '?x=1', [$pub], $seen), 'signature');
        $eq('Anderer Host abgewiesen', Signature::verify($h, 'GET', 'boese.example.net', $path, [$pub], $seen), 'signature');
        $eq('Andere Methode abgewiesen', Signature::verify($h, 'POST', 'www.example.org', $path, [$pub], $seen), 'signature');
        $other = sodium_crypto_sign_publickey(sodium_crypto_sign_keypair());
        $eq('Unbekannter Schlüssel abgewiesen', Signature::verify($h, 'GET', 'www.example.org', $path, [$other], $seen), 'key');
        $old = $hdr(Signature::headers($sec, $url, time() - 900));
        $eq('Alter Zeitstempel abgewiesen', Signature::verify($old, 'GET', 'www.example.org', $path, [$pub], $seen), 'time');
        $bad = ['x-status-signature' => 'AAAA'] + $hdr(Signature::headers($sec, $url));
        $eq('Kaputte Signatur abgewiesen', Signature::verify($bad, 'GET', 'www.example.org', $path, [$pub], $seen), 'format');
        $forged = ['x-status-key' => Signature::keyId($pub)] + $hdr(Signature::headers(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()), $url));
        $eq('Signatur eines anderen Schlüssels abgewiesen', Signature::verify($forged, 'GET', 'www.example.org', $path, [$pub], $seen), 'signature');
        $s = Status::collect('n0nce-n0nce-n0nce');
        foreach (['protocol', 'system', 'system_version', 'php', 'generated_at', 'health', 'components', 'backup', 'integrity', 'theme', 'nonce'] as $k) {
            $eq("Antwort enthält $k", array_key_exists($k, $s), true);
        }
        $eq('Ampel gültig', in_array($s['health']['status'] ?? '', ['green', 'yellow', 'red'], true), true);
        $eq('Keine E-Mail-Adressen in der Antwort', str_contains((string) json_encode($s), '@'), false);
        return ['ok' => $ok, 'fails' => $fails];
    }
}
