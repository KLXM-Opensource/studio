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
        $keys = ['sys.mail_accounts', 'sys.mail_routes', 'sys.mail_transport', 'sys.mail_host', 'sys.mail_from', 'sys.mail_from_name', 'sys.mail_redirect',
            Imprint::SETTING, 'imprint_page', 'impressum_seite', 'sys.languages'];
        $pages = [];
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
            Mailer::$testDump = $dump;
            Mailer::send('QA via', 'x', ['a@example.org'], ['purpose' => 'notify', 'via' => 'news']);
            $eq('via: bestimmtes Konto statt Zweck', Mailer::$account['id'] ?? null, 'news');
            Mailer::send('QA via', 'x', ['a@example.org'], ['purpose' => 'notify', 'via' => 'leer']);
            $eq('via: nicht versandbereites Konto → Zweck', Mailer::$account['id'] ?? null, 'standard');
            Mailer::$testDump = null;
            foreach (glob($dump . '/*') ?: [] as $f) @unlink($f);
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
                // Übersicht „Welche Adresse gilt wofür“ (Core\Mail\Overview): Herkunft aus derselben Rangfolge
                $std = Accounts::standard();
                $eq('Herkunft: Netzwerk-Mail über das Netzwerk-Konto', Accounts::resolve('account', true, Accounts::all(), [], $n, $std)['origin'], 'network');
                $eq('Herkunft: Rückfall aufs Netzwerk-Konto', Accounts::resolve('notify', false, ['standard' => ['from' => ''] + $std], [], $n, ['from' => ''] + $std)['origin'], 'fallback');
                $eq('Herkunft: fehlendes Konto → Standard', Accounts::resolve('visitor', false, Accounts::all(), ['visitor' => 'fehlt'], null, $std)['origin'], 'missing');
                $rows = array_column(Overview::rows(true, false), null, 'key');
                $eq('Übersicht: Anfragen über Konto der Website, Netzwerk-Mails über das Netzwerk-Konto',
                    [$rows['requests']['acc']['id'] ?? null, \Core\Sites::multi() ? ($rows['network']['acc']['id'] ?? null) : 'network'], ['standard', 'network']);
                $eq('Übersicht: Netzwerk-Mails nur für die Netzwerk-Administration', isset(Overview::types(false)['network']), false);
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
            $err = Mailer::send('QA Newsletter', 'Hallo', ['empfaenger@example.org'], ['purpose' => 'newsletter', 'headers' => ['List-Unsubscribe' => '<https://example.org/ab>', 'Bcc' => 'boese@example.org'], 'return_path' => 'bounce+1.2.abc@example.org']);
            $eml = (string) @file_get_contents((glob($dump . '/*.eml') ?: [''])[0]);
            $eq('Versand über Konto „news“ ohne Fehler', [$err, Mailer::$account['id'] ?? null], [null, 'news']);
            $eq('Absender des Kontos', str_contains($eml, 'news@example.org') && str_contains($eml, 'Newsletter'), true);
            $eq('Antwortadresse des Kontos', str_contains($eml, 'Reply-To: antwort@example.org'), true);
            $eq('Kopfzeile List-Unsubscribe übernommen', str_contains($eml, 'List-Unsubscribe:'), true);
            $eq('Adressfelder über headers abgewiesen', str_contains($eml, 'boese@example.org'), false);
            $eq('Hinweis bei abgelehnter Anmeldung', str_starts_with((string) Accounts::hint('Failed to authenticate on SMTP server with username "x": Expected response code "235" but got code "535"'), 'Anmeldung abgelehnt'), true);
            $eq('Hinweis Microsoft 365 vor allgemeiner Anmeldung', str_starts_with((string) Accounts::hint('535 5.7.139 Authentication unsuccessful, SmtpClientAuthentication is disabled for the Tenant'), 'Microsoft 365'), true);
            $eq('kein Hinweis bei unbekanntem Fehler', Accounts::hint('Irgendwas'), null);
            $eq('Rücklaufadresse (return_path) gesetzt', str_contains($eml, 'Return-Path: <bounce+1.2.abc@example.org>'), true);
            foreach (glob($dump . '/*') ?: [] as $f) @unlink($f);

            // Impressum in jeder Mail (Core\Mail\Imprint): Text nach „-- “, HTML-Fußzeile, Option gegen Dopplung, health, Sprache
            $pages = self::imprintPages();
            $s->set('sys.languages', [['code' => 'de', 'label' => 'Deutsch', 'active' => true], ['code' => 'en', 'label' => 'English', 'active' => true]]);
            \Core\Lang::flush();
            $s->set('impressum_seite', 0);
            $s->set('imprint_page', $pages['de']);
            $s->set(Imprint::SETTING, "Muster GmbH\nMusterstraße 1, 12345 Musterstadt\nUSt-IdNr. DE123456789");
            Imprint::flush();
            $mark = 'Muster GmbH';
            $read = function () use ($dump): array {
                $f = glob($dump . '/*.eml') ?: [];
                sort($f);
                $out = $f ? [self::textPart((string) file_get_contents((string) end($f))), (is_file($hf = substr((string) end($f), 0, -4) . '.html') ? (string) file_get_contents($hf) : '')] : ['', ''];
                foreach (glob($dump . '/*') ?: [] as $x) @unlink($x);
                return $out;
            };
            Mailer::send('QA Impressum', 'Hallo', ['a@example.org'], ['html' => '<!doctype html><html><body><p>Hallo</p></body></html>']);
            [$t, $h] = $read();
            $imp = Imprint::get('de');
            $eq('Impressum: Text nach dem Trenner „-- “', [str_contains($t, "Hallo\n\n-- \nImpressum\nMuster GmbH\n"), substr_count($t, $mark)], [true, 1]);
            $eq('Impressum: HTML-Fußzeile vor </body>, einmal', [substr_count($h, $mark), (bool) preg_match('~Muster GmbH.*</td></tr></table>\s*</body>~s', $h)], [1, true]);
            $eq('Impressum: Link zur Impressum-Seite (absolut, Text und HTML)', [str_starts_with($imp['url'], 'http'), str_contains($t, $imp['url']), str_contains($h, 'href="' . e($imp['url']) . '"')], [true, true, true]);
            $eq('Impressum: Fußzeile ohne Bilder, Kontrast ≥ 4,5:1', [str_contains($imp['html'], '<img'), \Core\Contrast::ratio('#4a4a4a', '#ffffff') >= 4.5, \Core\Contrast::ratio('#1f4e8c', '#ffffff') >= 4.5], [false, true, true]);
            Mailer::send('QA nur Text', 'Nur Text', ['a@example.org']);
            [$t, $h] = $read();
            $eq('Impressum: Mail ohne HTML erhält es im Textteil', [substr_count($t, $mark), $h], [1, '']);
            Mailer::send('QA Konto', 'Passwort', ['a@example.org'], ['account' => true, 'purpose' => 'account']);
            $eq('Impressum: auch in Mails zur Kontosicherheit', substr_count($read()[0], $mark), 1);
            Mailer::send('QA eigenes Layout', Imprint::appendText('Inhalt', $imp), ['a@example.org'], ['html' => Imprint::appendHtml('<html><body>x</body></html>', $imp), 'imprint' => 'included']);
            [$t, $h] = $read();
            $eq('Option imprint => included: keine Dopplung', [substr_count($t, $mark), substr_count($h, $mark)], [1, 1]);
            $en = Imprint::get('en');
            $eq('Sprache: Link auf die englische Impressum-Seite, Beschriftung englisch', [str_ends_with($en['url'], '/en/qa-legal-notice-selftest'), $en['label'], str_ends_with($imp['url'], '/qa-impressum-selbsttest')], [true, 'Legal notice', true]);
            Mailer::send('QA Sprache', 'Hello', ['a@example.org'], ['lang' => 'en']);
            $eq('Mailer-Option lang: Link der Sprache', str_contains($read()[0], $en['url']), true);
            $eq('health: kein Fehler mit Impressum', Imprint::health(), []);
            // Netzwerk-Mails (Option network) auf einer anderen Website: Angaben der Netzwerk-Website, nicht die eigenen
            if ($netOk && \Core\Sites::multi() && !\Core\Network\Network::isNetworkSite()) {
                $raw = \Core\Network\Network::db()->fetchValue('SELECT value_json FROM settings WHERE skey = ?', [Imprint::SETTING]);
                $netBody = trim((string) ($raw === null ? '' : json_decode((string) $raw, true)));
                $n = Imprint::get('de', true);
                $eq('Netzwerk-Mail: Impressum der Netzwerk-Website', [$n['body'], str_contains($n['text'], $mark) && !str_contains($netBody, $mark)], [$netBody, false]);
                $eq('Netzwerk-Mail: Link auf die Netzwerk-Website', $n['url'] === '' || str_starts_with($n['url'], \Core\Network\Network::siteUrl(\Core\Network\Network::siteKey())), true);
            }
            $s->set(Imprint::SETTING, '');
            Imprint::flush();
            $only = Imprint::get('de');
            $eq('Ohne Text: mindestens der Link', [$only['body'], str_contains($only['text'], $imp['url']), str_contains($only['html'], $imp['url'])], ['', true, true]);
            $s->set('imprint_page', 0);
            Imprint::flush();
            $eq('Ohne Text und Seite: fehlt, health meldet Fehler, Massenversand gesperrt', [Imprint::missing(), array_values(Imprint::health()), Imprint::bulkError() !== null], [true, [false], true]);
            $err = Mailer::send('QA ohne Impressum', 'Passwort zurücksetzen', ['a@example.org'], ['account' => true, 'purpose' => 'account']);
            [$t] = $read();
            $eq('Ohne Impressum: Mail geht trotzdem hinaus (ohne Trenner)', [$err, str_contains($t, 'Passwort zurücksetzen'), str_contains($t, "\n-- \n")], [null, true, false]);
            $s->set('imprint_page', $pages['de']);
            $pub = fn(int $id, string $st) => app()->db->update('pages', ['status' => $st], 'id = :wid', ['wid' => $id]);
            $pub($pages['de'], 'draft');
            $pub($pages['en'], 'draft');
            Imprint::flush();
            $eq('Entwurf zählt nicht als Impressum-Seite', Imprint::missing(), true);
            $pub($pages['de'], 'published');
            $pub($pages['en'], 'published');
            $s->set(Imprint::SETTING, 'Muster GmbH');
            Imprint::flush();
            $x2 = new Extension('qa_imprint', __DIR__, ['label' => 'QA']);
            $x2->filter('mail.message', fn(array $m) => ['options' => ['imprint' => 'included'] + (array) $m['options']] + $m);
            $act2 = new \ReflectionProperty(Extensions::class, 'active');
            $list2 = $act2->getValue(); $act2->setValue(null, array_merge((array) $list2, ['qa_imprint' => $x2]));
            try {
                Mailer::send('QA Filter', 'Text', ['a@example.org']);
                $eq('Filter einer Erweiterung kann das Impressum nicht abschalten', substr_count($read()[0], $mark), 1);
            } finally {
                $act2->setValue(null, $list2);
            }
        } finally {
            Mailer::$testDump = null;
            foreach ($prev as $k => $v) $v === null ? $s->delete($k) : $s->set($k, $v);
            foreach ($pages as $pid) app()->db->query('DELETE FROM pages WHERE id = ?', [$pid]);
            \Core\Lang::flush();
            Imprint::flush();
            if ($netOk) Accounts::saveNetwork($hadNet);
            foreach (glob($dump . '/*') ?: [] as $f) @unlink($f);
            @rmdir($dump);
        }
        echo "\n  " . $ok . ' Prüfungen bestanden, ' . count($fails) . " fehlgeschlagen\n";
        return $fails ? 1 : 0;
    }

    /** Textteil (text/plain) einer abgelegten Nachricht, dekodiert (quoted-printable/base64) */
    private static function textPart(string $eml): string
    {
        $eml = str_replace("\r\n", "\n", $eml);
        // Teile an der Grenze aus dem Kopf trennen (nicht an jeder Zeile mit „--“ – die Signatur beginnt selbst mit „-- “)
        $parts = preg_match('~boundary="?([^";\n]+)"?~i', $eml, $bm) ? explode("\n--" . $bm[1], $eml) : [$eml];
        foreach ($parts as $part) {
            [$head, $body] = array_pad(explode("\n\n", ltrim($part, "\n"), 2), 2, '');
            if (!preg_match('~^Content-Type:\s*text/plain~mi', $head)) continue;
            if (preg_match('~^Content-Transfer-Encoding:\s*quoted-printable~mi', $head)) return quoted_printable_decode($body);
            if (preg_match('~^Content-Transfer-Encoding:\s*base64~mi', $head)) return (string) base64_decode($body);
            return $body;
        }
        return '';
    }

    /** Zwei veröffentlichte Testseiten (Impressum de + Übersetzung en) – werden am Ende gelöscht */
    private static function imprintPages(): array
    {
        $db = app()->db;
        $now = date('Y-m-d H:i:s');
        $db->insert('pages', ['slug' => 'qa-impressum-selbsttest', 'path' => 'qa-impressum-selbsttest', 'title' => 'QA Impressum', 'status' => 'published', 'lang' => 'de', 'updated_at' => $now, 'published_at' => $now]);
        $de = (int) $db->pdo->lastInsertId();
        $db->update('pages', ['translation_group' => $de], 'id = :wid', ['wid' => $de]);
        $db->insert('pages', ['slug' => 'qa-legal-notice-selftest', 'path' => 'qa-legal-notice-selftest', 'title' => 'QA Legal notice', 'status' => 'published', 'lang' => 'en', 'translation_group' => $de, 'updated_at' => $now, 'published_at' => $now]);
        return ['de' => $de, 'en' => (int) $db->pdo->lastInsertId()];
    }

    private static function rawNetwork(): ?string
    {
        $raw = \Core\Network\Network::db()->fetchValue("SELECT v FROM network_mail WHERE k = 'account'");
        return $raw === null ? null : (string) $raw;
    }
}
