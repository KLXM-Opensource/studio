<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Monitoring;

use Core\Health;
use Core\Testing\Check;

/**
 * php bin/console monitor:selftest – Signatur status-v1 (gültig, verändert, Zeit, Wiederholung, Schlüssel), Felder der Antwort,
 * Kategorien der Prüfungen (Core\Health), Lebensende der PHP-Version, Zählung des Fehlerprotokolls (ErrorLog).
 */
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
        $eq('Host-Header normalisiert wie hostOf', [Signature::normalizeHost('WWW.Example.org:443', true), Signature::normalizeHost('www.example.org.', true),
            Signature::normalizeHost('example.org:80', false), Signature::normalizeHost('example.org:8153', true), Signature::normalizeHost('[::1]:8080', false)],
            ['www.example.org', 'www.example.org', 'example.org', 'example.org:8153', '[::1]:8080']);
        $eq('Nonce länger gemerkt als das ganze Zeitfenster (2 × 300 s + 60 s)', Signature::NONCE_TTL, 660);
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
        $cats = array_unique(array_column((array) $s['health']['messages'], 'category'));
        $eq('Jede Meldung mit gültiger Kategorie', [count($cats) === count(array_filter($cats, fn($c) => in_array($c, Health::CATEGORIES, true))),
            count(array_column((array) $s['health']['messages'], 'category')) === count((array) $s['health']['messages'])], [true, true]);
        $tech = array_filter((array) $s['health']['messages'], fn($m) => $m['category'] === Health::TECHNICAL);
        $eq('Zähler nur aus technischen Meldungen', [$s['health']['errors'] + $s['health']['warnings'], $s['health']['notices']],
            [count($tech), count($s['health']['messages']) - count($tech)]);
        $eq('Antwort enthält log (Zahlen)', [isset($s['log']['errors'], $s['log']['warnings'], $s['log']['counts']), $s['log']['window_hours'] ?? null], [true, 24]);

        // Kategorien
        $eq('Kategorie: Standard technisch, deutsch, unbekannt', [Health::category(null), Health::category('einrichtung'), Health::category('Redaktionell'),
            Health::category('quatsch', Health::SETUP)], ['technical', 'setup', 'editorial', 'setup']);
        $eq('Zeile: alte Form', Health::row('x', null, Health::SETUP), ['label' => 'x', 'ok' => null, 'category' => 'setup']);
        $eq('Zeile: mit Kategorie', Health::row('x', ['ok' => 0, 'category' => 'editorial']), ['label' => 'x', 'ok' => false, 'category' => 'editorial']);
        $eq('Zeile: Array ohne Kategorie → Standard der Prüfung', Health::row('x', ['ok' => true], Health::SETUP)['category'], 'setup');
        $byLabel = [];
        foreach (Health::rows() as $r) $byLabel[$r['label']] = $r['category'];
        $eq('Datenbank technisch', $byLabel['Datenbank'] ?? null, 'technical');
        $eq('checks() wie bisher (Bezeichnung => Wert)', array_keys(Health::checks()), array_column(Health::rows(), 'label'));
        $seo = \Core\SeoCheck::health(true);
        $eq('SEO-Hinweise nie technisch', array_values(array_filter(array_map(fn($v) => is_array($v) ? $v['category'] : 'alt', $seo), fn($c) => $c === Health::TECHNICAL)), []);
        // PHP-Lebensende (8.4: 31.12.2028)
        $eol = strtotime('2028-12-31 23:59:59');
        $eq('PHP: gut, bald zu Ende, abgelaufen, unbekannt', [array_values(Health::phpSupport('8.4.12', $eol - 400 * 86400)), array_values(Health::phpSupport('8.4.12', $eol - 30 * 86400)),
            array_values(Health::phpSupport('8.4.12', $eol + 86400)), Health::phpSupport('9.1.0')], [[true], [null], [false], []]);

        // Fehlerprotokoll: nur Anzahl und Art
        $now = time();
        $f = tempnam(sys_get_temp_dir(), 'klxm-errlog-');
        $ts = fn(int $t) => '[' . (new \DateTimeImmutable('@' . $t))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d-M-Y H:i:s') . ' Europe/Berlin] ';
        file_put_contents($f, implode("\n", [
            $ts($now - 2 * 86400) . 'PHP Fatal error:  alt, außerhalb des Fensters',
            $ts($now - 3600) . 'PHP Fatal error:  Uncaught Error: Call to undefined function x() in /srv/app/x.php:3',
            '#0 {main}',
            $ts($now - 1800) . 'TypeError: Core\\Pages::find(): Argument #1 must be of type int in /srv/app/Pages.php:10',
            'Stack trace:',
            $ts($now - 900) . 'PHP Warning:  Undefined array key "x" in /srv/app/y.php on line 4',
            $ts($now - 600) . 'PHP Deprecated:  strlen(): Passing null',
            $ts($now - 300) . '[mailer] Versand fehlgeschlagen an erika@example.org',
            'ohne Zeitstempel',
        ]) . "\n");
        $l = ErrorLog::summary($f, $now);
        @unlink($f);
        $eq('Fehlerprotokoll: Arten gezählt', [$l['errors'], $l['warnings'], $l['counts']], [2, 1, ['fatal' => 1, 'exception' => 1, 'warning' => 1, 'notice' => 0, 'deprecated' => 1, 'application' => 1]]);
        $eq('Fehlerprotokoll: letzter Fehler, keine Inhalte', [$l['last_error_at'] === date('c', $now - 1800), str_contains((string) json_encode($l), 'example.org'), str_contains((string) json_encode($l), 'undefined')], [true, false, false]);
        $eq('Fehlerprotokoll: fehlende Datei', ErrorLog::summary('/gibt/es/nicht.log', $now)['errors'], 0);
        $eq('Keine E-Mail-Adressen in der Antwort', str_contains((string) json_encode($s), '@'), false);
        return ['ok' => $ok, 'fails' => $fails];
    }
}
