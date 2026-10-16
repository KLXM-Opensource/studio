<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Mail;

use Core\Extension;
use Core\Extensions;
use Core\Mailer;

/**
 * php bin/console mail:selftest [--site=…] – E-Mail-Konten: Zuordnung je Zweck, Rückfall, Netzwerk-Mails nur übers Netzwerk-Konto,
 * verschlüsselte Ablage, DSN, Versandwege und Zwecke von Erweiterungen, Absender/Antwortadresse/Kopfzeilen in der fertigen Mail
 * (Ablage statt Versand). Einstellungen werden danach zurückgesetzt; Datenbank in einer Transaktion.
 */
final class SelfTest
{
    public static function run(): int
    {
        $ok = 0; $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails, '', true);
        $s = app()->settings;
        $keys = ['sys.mail_accounts', 'sys.mail_routes', 'sys.mail_transport', 'sys.mail_host', 'sys.mail_from', 'sys.mail_from_name', 'sys.mail_redirect'];
        $prev = [];
        foreach ($keys as $k) $prev[$k] = $s->get($k);
        $hadNet = null; $netOk = false;
        try { $hadNet = Accounts::network(); $netOk = true; } catch (\Throwable) {}
        $dump = sys_get_temp_dir() . '/klxm-mail-selftest-' . bin2hex(random_bytes(3));
        try {
            $s->set('sys.mail_redirect', '');
            $s->set('sys.mail_transport', 'smtp'); $s->set('sys.mail_host', 'smtp.example.org');
            $s->set('sys.mail_from', 'web@example.org'); $s->set('sys.mail_from_name', 'Website');
            Accounts::saveExtra([['id' => 'news', 'label' => 'Newsletter', 'transport' => 'dsn', 'dsn' => 'null://null', 'from' => 'news@example.org',
                'from_name' => 'Newsletter', 'reply_to' => 'antwort@example.org'],
                ['id' => 'leer', 'label' => 'Ohne Absender', 'transport' => 'smtp', 'host' => 'x.example.org', 'from' => '']]);
            $raw = json_encode($s->get('sys.mail_accounts'));
            $eq('Geheimnisse verschlüsselt abgelegt (DSN nicht im Klartext)', str_contains((string) $raw, 'null://null'), false);
            $eq('weitere Konten lesbar', Accounts::extra()['news']['dsn'] ?? null, 'null://null');
            $eq('ohne Zuordnung: Standard', Accounts::route('notify')['id'], 'standard');
            $s->set('sys.mail_routes', ['newsletter' => 'news', 'visitor' => 'fehlt']);
            $eq('Zweck → Konto', Accounts::route('newsletter')['id'], 'news');
            $eq('fehlendes Konto → Standard', Accounts::route('visitor')['id'], 'standard');
            $eq('SMTP-DSN', Accounts::dsn(Accounts::standard()), 'smtp://smtp.example.org:587');
            $eq('SSL-Port', Accounts::dsn(['encryption' => 'ssl', 'port' => 0] + Accounts::normalize(['transport' => 'smtp', 'host' => 'h', 'encryption' => 'ssl'])), 'smtps://h:465');
            $eq('sendmail/PHP-mail nicht mehr möglich (→ SMTP)', Accounts::normalize(['transport' => 'sendmail'])['transport'], 'smtp');

            // Netzwerk-Konto: nur auf einer Installation mit Netzwerk-Datenbank
            if ($netOk) {
                Accounts::saveNetwork(['transport' => 'dsn', 'dsn' => 'null://null', 'from' => 'system@example.net', 'from_name' => 'System']);
                $n = Accounts::network();
                $eq('Netzwerk-Konto gespeichert und lesbar', [$n['from'] ?? null, $n['dsn'] ?? null], ['system@example.net', 'null://null']);
                $eq('Netzwerk-Geheimnis verschlüsselt', str_contains((string) self::rawNetwork(), 'null://null'), false);
                $eq('Netzwerk-Mail → Netzwerk-Konto', Accounts::route('account', true)['id'], 'network');
                $s->set('sys.mail_from', '');
                $eq('Website ohne gültigen Absender → Netzwerk-Konto', Accounts::route('notify')['id'], 'network');
                $s->set('sys.mail_from', 'web@example.org');
                $eq('Website mit eigenem Konto bleibt bei ihrem Konto', Accounts::route('notify')['id'], 'standard');
            }

            // Erweiterung: eigener Zweck und Versandweg
            $x = new Extension('qa_mail', __DIR__, ['label' => 'QA']);
            $x->mailPurpose('kampagne', 'QA-Kampagne', 'Test');
            $x->mailTransport('qa', 'QA-Dienst', fn(array $a) => \Symfony\Component\Mailer\Transport::fromDsn('null://null'));
            $act = new \ReflectionProperty(Extensions::class, 'active');
            $list = $act->getValue(); $act->setValue(null, array_merge((array) $list, ['qa_mail' => $x]));
            try {
                $eq('Zweck einer Erweiterung', Purposes::valid('ext:qa_mail.kampagne'), true);
                $eq('Versandweg einer Erweiterung', Accounts::normalize(['transport' => 'ext:qa'])['transport'], 'ext:qa');
                $eq('Versandweg einer Erweiterung baut Transport', Accounts::transport(Accounts::normalize(['transport' => 'ext:qa', 'from' => 'a@example.org'])) instanceof \Symfony\Component\Mailer\Transport\TransportInterface, true);
            } finally {
                $act->setValue(null, $list);
            }

            // Fertige Mail (Ablage): Absender, Name, Antwortadresse, Kopfzeile vom Konto
            Mailer::$testDump = $dump;
            $err = Mailer::send('QA Newsletter', 'Hallo', ['empfaenger@example.org'], ['purpose' => 'newsletter', 'headers' => ['List-Unsubscribe' => '<https://example.org/ab>', 'Bcc' => 'boese@example.org']]);
            $eml = (string) @file_get_contents((glob($dump . '/*.eml') ?: [''])[0]);
            $eq('Versand über Konto „news“ ohne Fehler', [$err, Mailer::$account['id'] ?? null], [null, 'news']);
            $eq('Absender des Kontos', str_contains($eml, 'news@example.org') && str_contains($eml, 'Newsletter'), true);
            $eq('Antwortadresse des Kontos', str_contains($eml, 'Reply-To: antwort@example.org'), true);
            $eq('Kopfzeile List-Unsubscribe übernommen', str_contains($eml, 'List-Unsubscribe:'), true);
            $eq('Adressfelder über headers abgewiesen', str_contains($eml, 'boese@example.org'), false);
        } finally {
            Mailer::$testDump = null;
            foreach ($prev as $k => $v) $v === null ? $s->delete($k) : $s->set($k, $v);
            if ($netOk) Accounts::saveNetwork($hadNet);
            foreach (glob($dump . '/*') ?: [] as $f) @unlink($f);
            @rmdir($dump);
        }
        echo "\n  " . $ok . ' Prüfungen bestanden, ' . count($fails) . " fehlgeschlagen\n";
        return $fails ? 1 : 0;
    }

    private static function rawNetwork(): ?string
    {
        $raw = \Core\Network\Network::db()->fetchValue("SELECT v FROM network_mail WHERE k = 'account'");
        return $raw === null ? null : (string) $raw;
    }
}
