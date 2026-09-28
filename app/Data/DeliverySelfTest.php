<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Mailer;

/**
 * Selbsttest der Zustellung per E-Mail (Core\Data\Delivery) – `php bin/console inbox:selftest`.
 * Legt eine vorübergehende Eingangs-Tabelle an (danach gelöscht), nutzt einen Test-Schlüssel (nur im Speicher) und legt E-Mails
 * in einem temporären Ordner ab (Mailer::$testDump) – nichts wird versendet, Einstellungen bleiben unverändert.
 * Prüft: Einstellungen, alle drei Modi, Rückfall bei Versandfehler und ohne Empfänger, keine Inhalte in Datenbank/Protokoll im Modus
 * „nur per E-Mail“, Weiterleitung, Reply-To, Betreff, JSON/XML, Anhang-Grenzen, Transport-Prüfung und S/MIME (Entschlüsseln mit
 * einem selbst signierten Test-Zertifikat).
 */
final class DeliverySelfTest
{
    private const MARK = 'Selbsttest-Inhalt-7Q4Z';

    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $true = fn(string $what, bool $cond) => $eq($what, $cond, true);
        if (!Delivery::available()) {
            return ['ok' => 0, 'fails' => ['Funktionen „requests“ und „requests.mail“ müssen eingeschaltet sein – Selbsttest übersprungen.']];
        }

        $st = app()->settings;
        $alertsBefore = $st->has(Delivery::ALERTS) ? $st->get(Delivery::ALERTS) : null;
        $kp = sodium_crypto_box_keypair();
        $pk = base64_encode(sodium_crypto_box_publickey($kp));
        $sk = base64_encode(sodium_crypto_box_secretkey($kp));
        $st->override(['sys.form_public_key' => $pk, 'sys.mail_from' => 'website@example.invalid', 'sys.mail_from_name' => 'Selbsttest']);
        $dir = sys_get_temp_dir() . '/klxm-delivery-selftest-' . bin2hex(random_bytes(4));
        Mailer::$testDump = $dir;
        $handle = 'selftest_zustellung_' . bin2hex(random_bytes(3));
        $t = null;
        try {
            // ---------------------------------------------------------- Einstellungen
            [$def, $errors] = Tables::validate([
                'name' => 'Selbsttest Zustellung', 'singular' => 'Anfrage', 'handle' => $handle, 'icon' => 'envelope-simple',
                'fields' => [
                    ['label' => 'Name', 'name' => 'name', 'type' => 'text', 'required' => 1],
                    ['label' => 'E-Mail', 'name' => 'email', 'type' => 'email'],
                    ['label' => 'Anliegen', 'name' => 'anliegen', 'type' => 'select', 'options' => "rezept=Rezept\nueberweisung=Überweisung"],
                    ['label' => 'Nachricht', 'name' => 'nachricht', 'type' => 'textarea'],
                    ['label' => 'Befund', 'name' => 'befund', 'type' => 'file'],
                ],
                'settings' => ['kind' => 'inbox', 'form' => ['enabled' => 1, 'upload_mb' => 5], 'inbox' => ['title' => 'Selbsttest', 'delivery' => [
                    'mode' => 'both', 'to' => 'praxis@example.invalid', 'route_field' => 'anliegen', 'routes' => ['rezept' => 'rezept@example.invalid', 'gibtsnicht' => 'x@example.invalid'],
                    'subject' => "Betreff\nmit Umbruch", 'machine' => 'json',
                ]]],
            ]);
            $eq('Tabelle mit Dateifeld und Zustellung gültig', $errors, []);
            $d = $def['settings']['inbox']['delivery'];
            $eq('Modus übernommen', $d['mode'], 'both');
            $eq('Weiterleitung nur für vorhandene Auswahl', array_keys($d['routes']), ['rezept']);
            $eq('Betreff ohne Zeilenumbruch', $d['subject'], 'Betreff mit Umbruch');
            $eq('Formular nimmt Dateien an (Zustellung per E-Mail)', $def['settings']['form']['uploads'], true);
            [, $e2] = Tables::validate(['name' => 'X', 'handle' => $handle . 'x', 'fields' => [['label' => 'Datei', 'name' => 'datei', 'type' => 'file']],
                'settings' => ['kind' => 'inbox', 'inbox' => ['delivery' => ['mode' => 'system']]]]);
            $true('Dateifeld ohne Zustellung per E-Mail abgelehnt', isset($e2['fields']));
            $e3 = [];
            Delivery::validateSettings(['to' => 'kein-at, gut@example.invalid'], [], $e3, null, null);
            $true('Ungültige Adresse abgelehnt', isset($e3['settings.delivery']));
            $e4 = [];
            $v4 = Delivery::validateSettings(['smime' => "-----BEGIN PRIVATE KEY-----\nAAAA\n-----END PRIVATE KEY-----"], [], $e4, null, null);
            $true('Privater Schlüssel wird abgelehnt und nicht gespeichert', isset($e4['settings.delivery.smime']) && $v4['smime'] === '');
            $e5 = [];
            Delivery::validateSettings(['smime' => "-----BEGIN CERTIFICATE-----\nkeinzertifikat\n-----END CERTIFICATE-----"], [], $e5, null, null);
            $true('Kaputtes Zertifikat abgelehnt', isset($e5['settings.delivery.smime']));

            // Transport
            $eq('Transport: SMTP ohne Verschlüsselung → Fehler', Delivery::transport(['sys.mail_from' => 'a@example.invalid', 'sys.mail_transport' => 'smtp', 'sys.mail_host' => 'h', 'sys.mail_encryption' => 'none'])['level'], 'error');
            $eq('Transport: SMTP mit TLS → ok', Delivery::transport(['sys.mail_from' => 'a@example.invalid', 'sys.mail_transport' => 'smtp', 'sys.mail_host' => 'h', 'sys.mail_encryption' => 'tls'])['level'], 'ok');
            $eq('Transport: SSL → ok', Delivery::transport(['sys.mail_from' => 'a@example.invalid', 'sys.mail_transport' => 'smtp', 'sys.mail_host' => 'h', 'sys.mail_encryption' => 'ssl'])['level'], 'ok');
            $eq('Transport: ohne Zertifikatsprüfung → Warnung', Delivery::transport(['sys.mail_from' => 'a@example.invalid', 'sys.mail_transport' => 'smtp', 'sys.mail_host' => 'h', 'sys.mail_encryption' => 'tls', 'sys.mail_verify_peer' => false])['level'], 'warn');
            $eq('Transport: sendmail → Warnung', Delivery::transport(['sys.mail_from' => 'a@example.invalid', 'sys.mail_transport' => 'sendmail'])['level'], 'warn');
            $eq('Transport: deaktiviert → Fehler', Delivery::transport(['sys.mail_from' => 'a@example.invalid', 'sys.mail_transport' => 'null'])['level'], 'error');
            $true('SMTP-DSN erzwingt TLS für Inhalte', (function () use ($st) {
                $st->override(['sys.mail_transport' => 'smtp', 'sys.mail_host' => 'smtp.example.invalid', 'sys.mail_encryption' => 'tls']);
                $a = Mailer::dsn(true);
                $b = Mailer::dsn();
                $st->override(['sys.mail_transport' => null, 'sys.mail_host' => null, 'sys.mail_encryption' => null]);
                return str_contains($a, 'require_tls=true') && !str_contains($b, 'require_tls');
            })());

            Tables::create($def);
            $t = Tables::find($handle);
            $true('Testtabelle angelegt', $t !== null);
            if (!$t) return ['ok' => $ok, 'fails' => $fails];
            $with = function (array $d) use ($t): array {
                $t['settings']['inbox']['delivery'] = $d + $t['settings']['inbox']['delivery'];
                return $t;
            };
            $rows = fn() => (int) app()->db->fetchValue("SELECT COUNT(*) FROM {$t['table']}");
            $mails = fn() => array_values(array_filter(glob($dir . '/*.eml') ?: [], fn($f) => !str_contains(basename($f), 'zustellung-fehlgeschlagen')));
            $pdf = "%PDF-1.4\n% " . self::MARK . "\n" . str_repeat('x', 2000) . "\n%%EOF\n";
            $values = ['name' => 'Erika ' . self::MARK, 'email' => 'erika@example.invalid', 'anliegen' => 'ueberweisung', 'nachricht' => "Zeile 1\n" . self::MARK, 'befund' => 0];
            $files = ['befund' => ['name' => 'befund.pdf', 'type' => 'application/pdf', 'data' => $pdf]];

            // ---------------------------------------------------------- Weiterleitung, Reply-To, Betreff, JSON/XML
            $eq('Weiterleitung „Rezept“', Delivery::recipients($t, ['anliegen' => 'rezept'] + $values), ['rezept@example.invalid']);
            $eq('Ohne passende Weiterleitung: Standard', Delivery::recipients($t, $values), ['praxis@example.invalid']);
            $m = Delivery::message($with(['subject' => '']), $values, $files, 'ABCD-EFGH', now(), 'both');
            $eq('Standard-Betreff ohne Inhalte', $m['subject'], '[Selbsttest] ABCD-EFGH');
            $eq('Reply-To = E-Mail der Anfrage', $m['reply_to'], 'erika@example.invalid');
            $eq('Reply-To abschaltbar', Delivery::message($with(['reply_to' => false]), $values, $files, 'ABCD-EFGH', now(), 'both')['reply_to'], null);
            $eq('Betreff mit Platzhalter', Delivery::message($with(['subject' => '[Rezeptanfrage] {name} – {ref}']), $values, [], 'ABCD-EFGH', now(), 'both')['subject'], '[Rezeptanfrage] Erika ' . self::MARK . ' – ABCD-EFGH');
            $eq('Felder in Formular-Reihenfolge', array_column($m['rows'], 'name'), ['name', 'email', 'anliegen', 'nachricht', 'befund']);
            $true('Text enthält Beschriftung und Wert', str_contains($m['text'], 'Anliegen: Überweisung') && str_contains($m['text'], self::MARK) && str_contains($m['text'], 'ABCD-EFGH'));
            $true('HTML enthält Werte (escaped)', str_contains($m['html'], 'Überweisung') && str_contains($m['html'], 'Vorgangsnummer'));
            $json = json_decode((string) ($m['attach'][0]['data'] ?? ''), true);
            $eq('JSON-Anhang: Name', $m['attach'][0]['name'] ?? '', 'anfrage-ABCD-EFGH.json');
            $eq('JSON-Anhang: Felder', array_column((array) ($json['fields'] ?? []), 'name'), ['name', 'email', 'anliegen', 'nachricht', 'befund']);
            $eq('JSON-Anhang: Rohwert der Auswahl', $json['fields'][2]['value'] ?? null, 'ueberweisung');
            $mx = Delivery::message($with(['machine' => 'xml']), $values, $files, 'ABCD-EFGH', now(), 'both');
            $xml = @simplexml_load_string((string) ($mx['attach'][0]['data'] ?? ''));
            $true('XML-Anhang lesbar', $xml !== false && (string) $xml->ref === 'ABCD-EFGH' && count($xml->fields->field) === 5);
            $eq('Datei als Anhang', array_column($m['attach'], 'name'), ['anfrage-ABCD-EFGH.json', 'befund.pdf']);

            // Anhang-Grenzen
            $big = ['befund' => ['name' => 'gross.pdf', 'type' => 'application/pdf', 'data' => str_repeat('A', (int) (1.5 * 1024 * 1024))]];
            $mb = Delivery::message($with(['attach_mb' => 1, 'machine' => '']), $values, $big, 'ABCD-EFGH', now(), 'both');
            $eq('Zu große Datei: kein Anhang', $mb['attach'], []);
            $true('Zu große Datei: Hinweis statt Anhang', str_contains($mb['text'], 'nicht angehängt'));
            $true('Nur per E-Mail: zu große Dateien lehnt das Formular ab', Delivery::tooLarge($with(['mode' => 'mail', 'attach_mb' => 1]), $big) !== null);
            $eq('System + E-Mail: zu große Dateien bleiben im System', Delivery::tooLarge($with(['attach_mb' => 1]), $big), null);

            // ---------------------------------------------------------- Modus „Im System“
            $r = Delivery::accept($with(['mode' => 'system']), $values, $files);
            $eq('System: gespeichert', [$r['ok'], $rows(), $r['mailed']], [true, 1, false]);
            $eq('System: keine E-Mail', count($mails()), 0);

            // ---------------------------------------------------------- Modus „System und E-Mail“
            $r = Delivery::accept($with(['mode' => 'both']), $values, $files);
            $eq('System + E-Mail: gespeichert und zugestellt', [$r['ok'], $rows(), $r['mailed']], [true, 2, true]);
            $eml = $mails();
            $eq('System + E-Mail: eine E-Mail', count($eml), 1);
            $raw = $eml ? (string) file_get_contents($eml[0]) : '';
            $true('E-Mail: Empfänger, Reply-To, Message-ID, Anhang', str_contains($raw, 'praxis@example.invalid') && str_contains($raw, 'Reply-To: erika@example.invalid')
                && str_contains($raw, 'Message-ID: <') && str_contains($raw, 'befund.pdf') && str_contains($raw, $r['ref']));
            $row = Inbox::find($t, $r['id'], true);
            $open = $row ? Inbox::open($t, $row, $sk) : null;
            $file = array_values(array_filter($open['fields'] ?? [], fn($f) => !empty($f['file'])))[0]['file'] ?? null;
            $eq('System + E-Mail: Datei versiegelt gespeichert', $file ? base64_decode((string) $file['data']) : null, $pdf);
            $true('Datenbank: kein Klartext im Payload', !str_contains((string) ($row['payload'] ?? ''), self::MARK));
            array_map('unlink', $eml);

            // ---------------------------------------------------------- Modus „Nur per E-Mail“
            $before = $rows();
            $r = Delivery::accept($with(['mode' => 'mail']), $values, $files);
            $eq('Nur E-Mail: zugestellt, nichts gespeichert', [$r['ok'], $r['mailed'], $r['id'], $rows()], [true, true, 0, $before]);
            $eq('Nur E-Mail: eine E-Mail', count($mails()), 1);
            $log = app()->db->fetch("SELECT * FROM inbox_log WHERE table_handle = ? AND action = 'mail' ORDER BY id DESC LIMIT 1", [$handle]);
            $true('Protokoll: gesendet, Vorgangsnummer, Empfänger-Hash, Message-ID', $log && str_contains((string) $log['detail'], 'gesendet') && str_contains((string) $log['detail'], $r['ref'])
                && str_contains((string) $log['detail'], Delivery::recipientHash(['praxis@example.invalid'])) && str_contains((string) $log['detail'], '<'));
            $true('Protokoll: keine Empfänger-Adresse, keine IP', $log && !str_contains((string) $log['detail'], 'praxis@') && !str_contains((string) $log['detail'], 'erika@') && empty($log['ip_hash']));
            array_map('unlink', $mails());

            // Rückfall: Versand scheitert (Ablage nicht beschreibbar) → verschlüsselt sichern + Warnung
            Mailer::$testDump = '/dev/null/klxm-selftest';
            $before = $rows();
            $r = Delivery::accept($with(['mode' => 'mail']), $values, $files);
            Mailer::$testDump = $dir;
            $eq('Rückfall: Besucher erhält Erfolg, Anfrage gesichert', [$r['ok'], $r['mailed'], $rows()], [true, false, $before + 1]);
            $row = Inbox::find($t, $r['id'], true);
            $open = $row ? Inbox::open($t, $row, $sk) : null;
            $eq('Rückfall: entschlüsselbar und markiert', $open['delivery'] ?? null, 'fallback');
            $true('Rückfall: Werte vollständig', str_contains(implode("\n", array_column($open['fields'] ?? [], 'value')), self::MARK));
            $eq('Rückfall: gleiche Vorgangsnummer', $row['ref'] ?? null, $r['ref']);
            $alerts = Delivery::alerts([$handle]);
            $true('Rückfall: Hinweis für die Administration', ($alerts[0]['ref'] ?? '') === $r['ref'] && !empty($alerts[0]['stored']));
            $log = app()->db->fetch("SELECT * FROM inbox_log WHERE table_handle = ? AND action = 'mail' ORDER BY id DESC LIMIT 1", [$handle]);
            $true('Rückfall: Protokoll „fehlgeschlagen → verschlüsselt gesichert“', $log && str_contains((string) $log['detail'], 'verschlüsselt gesichert'));

            // Rückfall: kein Empfänger
            $before = $rows();
            $r = Delivery::accept($with(['mode' => 'mail', 'to' => '', 'routes' => []]), $values, $files);
            $eq('Ohne Empfänger: gesichert statt verloren', [$r['ok'], $r['mailed'], $rows()], [true, false, $before + 1]);

            // Rückfall scheitert auch (kein Schlüssel) → Besucher bekommt KEINE Erfolgsmeldung
            Mailer::$testDump = '/dev/null/klxm-selftest';
            $st->override(['sys.form_public_key' => null]);
            $r = Delivery::accept($with(['mode' => 'mail']), $values, $files);
            $st->override(['sys.form_public_key' => $pk]);
            Mailer::$testDump = $dir;
            $eq('Beides gescheitert: keine Erfolgsmeldung', $r['ok'], false);
            array_map('unlink', glob($dir . '/*') ?: []);

            // ---------------------------------------------------------- S/MIME
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $csr = $key ? openssl_csr_new(['commonName' => 'Praxis Selbsttest', 'emailAddress' => 'praxis@example.invalid'], $key, ['digest_alg' => 'sha256']) : false;
            $x509 = $csr ? openssl_csr_sign($csr, null, $key, 30, ['digest_alg' => 'sha256']) : false;
            $certPem = '';
            $keyPem = '';
            if ($x509) { openssl_x509_export($x509, $certPem); openssl_pkey_export($key, $keyPem); }
            $true('Test-Zertifikat erzeugt', $certPem !== '');
            [$norm, $cerr] = Delivery::normalizeCerts("Kommentar\n" . $certPem);
            $eq('Zertifikat geprüft', $cerr, null);
            $info = Delivery::certInfo($norm);
            $true('Zertifikat: Name, Ablauf, selbst signiert', ($info['name'] ?? '') === 'Praxis Selbsttest' && ($info['days_left'] ?? 0) >= 29 && !empty($info['self_signed']));
            $r = Delivery::accept($with(['mode' => 'mail', 'smime' => $norm, 'subject' => '']), $values, $files);
            $eml = $mails();
            $eq('S/MIME: zugestellt', [$r['ok'], $r['mailed'], count($eml)], [true, true, 1]);
            $raw = $eml ? (string) file_get_contents($eml[0]) : '';
            $true('S/MIME: verschlüsselt (application/pkcs7-mime)', str_contains($raw, 'application/pkcs7-mime') && str_contains($raw, 'smime.p7m'));
            $true('S/MIME: kein Klartext in der Nachricht', !str_contains($raw, self::MARK) && !str_contains($raw, 'befund.pdf') && !str_contains($raw, 'Erika'));
            $true('S/MIME: keine Klartext-Vorschau (.html) abgelegt', !glob($dir . '/*.html'));
            $out = tempnam(sys_get_temp_dir(), 'smime-out');
            $dec = $eml && $out && openssl_pkcs7_decrypt($eml[0], $out, $certPem, $keyPem);
            $plain = $dec ? (string) file_get_contents($out) : '';
            if ($out) @unlink($out);
            $true('S/MIME: mit dem privaten Schlüssel entschlüsselbar', $dec);
            $true('S/MIME: entschlüsselt mit Inhalt und Anhang', str_contains($plain, 'befund.pdf') && str_contains(quoted_printable_decode($plain), self::MARK) && str_contains($plain, 'multipart/'));
            preg_match('~^Subject: (.*)$~m', $raw, $sm);
            $eq('S/MIME: Betreff bleibt ohne Inhalte', (bool) preg_match('~^\[Selbsttest\] [A-Z0-9]{4}-[A-Z0-9]{4}$~', trim($sm[1] ?? '')), true);
            array_map('unlink', $mails());
            // ungültiges Zertifikat gespeichert → kein Klartext-Versand, Rückfall
            $before = $rows();
            $r = Delivery::accept($with(['mode' => 'mail', 'smime' => "-----BEGIN CERTIFICATE-----\nAAAA\n-----END CERTIFICATE-----"]), $values, $files);
            $eq('S/MIME ungültig: nichts im Klartext versendet, gesichert', [$r['mailed'], count($mails()), $rows()], [false, 0, $before + 1]);

            // ---------------------------------------------------------- Auswahl „Felder im Formular“ + Dateifeld (nur per E-Mail)
            array_map('unlink', glob($dir . '/*') ?: []);
            $in = ['name' => $t['name'], 'singular' => $t['singular'], 'icon' => $t['icon'],
                'fields' => [...array_map(fn($f) => isset($f['options']) ? ['options' => implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys($f['options']), $f['options']))] + $f : $f, $t['fields']),
                    ['label' => 'Lebenslauf', 'name' => 'lebenslauf', 'type' => 'file', 'accept' => ['', 'pdf', 'docx'], 'max_mb' => '3']],
                // wie das Formular der Verwaltung: Schalter „uploads“ = 0 (gibt es im Eingang nicht), Auswahl mit Dateifeld; neues Feld stand nicht zur Wahl
                'settings' => ['kind' => 'inbox', 'form' => ['enabled' => 1, 'uploads' => '0', 'fields' => ['', 'name', 'befund'], 'upload_mb' => 5],
                    'inbox' => ['delivery' => ['mode' => 'mail']]]];
            [$d2, $e2] = Tables::validate($in, $t);
            $eq('Auswahl mit Dateifeld: gültig', $e2, []);
            $eq('Auswahl: angehaktes Dateifeld bleibt (unabhängig vom Schalter „uploads“)', $d2['settings']['form']['fields'] ?? null, ['name', 'befund']);
            $eq('Zustellung nur per E-Mail: Formular nimmt Dateien an', $d2['settings']['form']['uploads'] ?? null, true);
            $lf = Tables::field(['fields' => $d2['fields']], 'lebenslauf') ?? [];
            $eq('Dateifeld: erlaubte Typen und eigene Höchstgröße', [$lf['accept'] ?? null, $lf['max_mb'] ?? null], [['pdf', 'docx'], 3]);
            $t2 = ['fields' => $d2['fields'], 'settings' => $d2['settings']] + $t;
            $eq('Formularfelder: gewählt + Pflicht + neu angelegtes Feld', array_column(DataForms::fields($t2), 'name'), ['name', 'befund', 'lebenslauf']);
            $legacy = $t2;
            unset($legacy['settings']['form']['known']);
            $legacy['settings']['form']['fields'] = ['name'];
            $eq('Ältere Auswahl ohne Dateifeld: Dateifelder trotzdem dabei', array_column(DataForms::fields($legacy), 'name'), ['name', 'befund', 'lebenslauf']);
            $sys = $t2;
            $sys['settings']['inbox']['delivery']['mode'] = 'system';
            $eq('Ohne Zustellung per E-Mail: keine Dateifelder', array_column(DataForms::fields($sys), 'name'), ['name']);
            [, $e3] = Tables::validate(['settings' => ['kind' => 'inbox', 'inbox' => ['delivery' => ['mode' => 'mail']]], 'fields' => [['label' => 'D', 'name' => 'd', 'type' => 'file', 'accept' => ['']]]] + $in, $t);
            $true('Dateifeld ohne erlaubten Typ abgelehnt', (bool) array_filter(array_keys($e3), fn($k) => str_starts_with((string) $k, 'fields.')));
            [$d4] = Tables::validate(['name' => 'Inhalt', 'handle' => $handle . 'c', 'fields' => [['label' => 'Anhang', 'name' => 'anhang', 'type' => 'file', 'accept' => ['pdf', 'docx', 'odt']]]]);
            $eq('Inhaltstabelle: kein DOCX/ODT (Mediathek)', $d4['fields'][0]['accept'] ?? null, ['pdf']);
            $eq('Höchstgröße: Feld ≤ Tabelle', [DataForms::fileMb($lf, ['upload_mb' => 5]), DataForms::fileMb($lf, ['upload_mb' => 2]), DataForms::fileMb(['type' => 'file'], ['upload_mb' => 4])], [3, 2, 4]);
            $html = DataForms::render($t2 + ['handle' => $handle], ['uid' => 'st']);
            $true('Formular: multipart, accept, Hinweis mit aria-describedby', str_contains($html, 'enctype="multipart/form-data"')
                && str_contains($html, 'accept=".pdf,application/pdf,.docx,') && str_contains($html, 'PDF oder Word (DOCX), höchstens 3 MB.')
                && (bool) preg_match('~id="st-lebenslauf-k"[^>]*>[^<]*DOCX~', $html) && str_contains($html, 'aria-describedby="st-lebenslauf-k st-lebenslauf-e"'));
            // Hilfetext und Dateihinweis als eigene Absätze, beide über aria-describedby (früher zu einem Satz verbunden)
            $t2h = $t2 + ['handle' => $handle];
            foreach ($t2h['fields'] as &$hf) if ($hf['name'] === 'lebenslauf') $hf['help'] = 'Gerne auch als PDF';
            unset($hf);
            $html = DataForms::render($t2h, ['uid' => 'st']);
            $true('Formular: Hilfetext und Dateihinweis getrennt', (bool) preg_match('~<p class="dff-help" id="st-lebenslauf-h">Gerne auch als PDF</p><p class="dff-help dff-help--file" id="st-lebenslauf-k">PDF oder~', $html)
                && str_contains($html, 'aria-describedby="st-lebenslauf-h st-lebenslauf-k st-lebenslauf-e"'));

            // Dateityp am Inhalt (nicht an der Endung)
            $tmp = sys_get_temp_dir() . '/klxm-upload-selftest-' . bin2hex(random_bytes(3));
            @mkdir($tmp);
            $put = function (string $name, string $data) use ($tmp): string { file_put_contents("$tmp/$name", $data); return "$tmp/$name"; };
            $zip = function (string $name, array $entries) use ($tmp): string {
                $z = new \ZipArchive();
                $z->open("$tmp/$name", \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                foreach ($entries as $n => $v) { $z->addFromString($n, $v); if ($n === 'mimetype') $z->setCompressionName($n, \ZipArchive::CM_STORE); }
                $z->close();
                return "$tmp/$name";
            };
            $ct = fn(string $main) => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="' . $main . '"/></Types>';
            $docx = $zip('cv.docx', ['[Content_Types].xml' => $ct('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'), 'word/document.xml' => '<w:document/>']);
            $docm = $zip('makro.docx', ['[Content_Types].xml' => $ct('application/vnd.ms-word.document.macroEnabled.main+xml'), 'word/document.xml' => '<w:document/>', 'word/vbaProject.bin' => 'x']);
            $odt = $zip('cv.odt', ['mimetype' => 'application/vnd.oasis.opendocument.text', 'content.xml' => '<office:document-content/>']);
            $odtM = $zip('makro.odt', ['mimetype' => 'application/vnd.oasis.opendocument.text', 'content.xml' => '<x/>', 'Basic/Standard/Module1.xml' => 'x']);
            $pdfF = $put('cv.pdf', $pdf);
            $exe = $put('rechnung.pdf', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff" . str_repeat("\x00", 50) . 'This program cannot be run in DOS mode.');
            $png = $put('bild.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
            $all = array_keys(DataForms::FILE_KINDS);
            $eq('PDF erkannt', DataForms::sniff($pdfF, 'cv.pdf', ['pdf']), 'application/pdf');
            $eq('Programm mit Endung .pdf abgelehnt', DataForms::sniff($exe, 'rechnung.pdf', $all), null);
            $eq('DOCX erkannt, wenn erlaubt', DataForms::sniff($docx, 'cv.docx', ['pdf', 'docx']), DataForms::FILE_KINDS['docx']['mimes'][0]);
            $eq('DOCX abgelehnt, wenn nicht erlaubt', DataForms::sniff($docx, 'cv.docx', ['pdf', 'image']), null);
            $eq('DOCX mit Makros (DOCM umbenannt) abgelehnt', DataForms::sniff($docm, 'makro.docx', $all), null);
            $eq('ODT erkannt', DataForms::sniff($odt, 'cv.odt', ['odt']), DataForms::FILE_KINDS['odt']['mimes'][0]);
            $eq('ODT mit Basic-Makros abgelehnt', DataForms::sniff($odtM, 'makro.odt', $all), null);
            $eq('DOCX mit Endung .pdf abgelehnt', DataForms::sniff($docx, 'cv.pdf', $all), null);
            $eq('Bild erkannt', DataForms::sniff($png, 'bild.png', ['image']), 'image/png');
            $eq('Bild nur mit erlaubtem Typ', DataForms::sniff($png, 'bild.png', ['pdf']), null);

            // E-Mail mit gewählten Feldern + Datei: Anhang mit erkanntem Typ, auch S/MIME
            $v2 = ['name' => 'Erika ' . self::MARK, 'befund' => 0, 'lebenslauf' => 0];
            $f2 = ['lebenslauf' => ['name' => 'cv.docx', 'type' => DataForms::FILE_KINDS['docx']['mimes'][0], 'data' => (string) file_get_contents($docx)]];
            $t2['settings']['inbox']['delivery'] = ['mode' => 'mail', 'machine' => '', 'smime' => ''] + $t2['settings']['inbox']['delivery'];
            $m2 = Delivery::message($t2, $v2, $f2, 'WXYZ-2345', now(), 'mail');
            $eq('E-Mail: nur gewählte Felder + Datei', array_column($m2['rows'], 'name'), ['name', 'lebenslauf']);
            $eq('E-Mail: DOCX als Anhang mit Typ', array_map(fn($a) => [$a['name'], $a['type']], $m2['attach']), [['cv.docx', DataForms::FILE_KINDS['docx']['mimes'][0]]]);
            $t2['settings']['inbox']['delivery']['smime'] = $norm;
            $r = Delivery::accept($t2, $v2, $f2);
            $eml = $mails();
            $raw = $eml ? (string) file_get_contents($eml[0]) : '';
            $out = tempnam(sys_get_temp_dir(), 'smime-out');
            $dec = $eml && $out && openssl_pkcs7_decrypt($eml[0], $out, $certPem, $keyPem);
            $plain = $dec ? (string) file_get_contents($out) : '';
            if ($out) @unlink($out);
            $true('Nur E-Mail + S/MIME: Datei versiegelt zugestellt, nichts gespeichert', $r['ok'] && $r['mailed'] && !str_contains($raw, 'cv.docx') && str_contains($plain, 'cv.docx') && $r['id'] === 0);
            array_map('unlink', $mails());
            array_map('unlink', glob("$tmp/*") ?: []);
            @rmdir($tmp);

            // ---------------------------------------------------------- Nie Inhalte im Protokoll
            $logs = app()->db->fetchAll('SELECT detail FROM inbox_log WHERE table_handle = ?', [$handle]);
            $true('Protokoll: Einträge vorhanden', count($logs) >= 5);
            $true('Protokoll: nie Inhalte', !array_filter($logs, fn($l) => str_contains((string) $l['detail'], self::MARK) || str_contains((string) $l['detail'], 'erika@') || str_contains((string) $l['detail'], 'Erika')));
            $payloads = implode('', array_column(app()->db->fetchAll("SELECT payload FROM {$t['table']}"), 'payload'));
            $true('Datenbank: nie Klartext', !str_contains($payloads, self::MARK) && !str_contains($payloads, 'Erika'));
        } catch (\Throwable $e) {
            $fails[] = 'Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            Mailer::$testDump = null;
            if ($t) {
                try { Tables::delete($t); } catch (\Throwable $e) { $fails[] = 'Aufräumen: ' . $e->getMessage(); }
            } elseif ($x = Tables::find($handle)) {
                Tables::delete($x);
            }
            app()->db->query('DELETE FROM inbox_log WHERE table_handle = ?', [$handle]);
            if ($alertsBefore === null) $st->delete(Delivery::ALERTS); else $st->set(Delivery::ALERTS, $alertsBefore);
            $st->forget();
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
            sodium_memzero($kp);
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
