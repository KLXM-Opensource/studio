<?php
declare(strict_types=1);

namespace Core;

use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** E-Mail-Versand über Symfony Mailer, konfiguriert aus den Grundeinstellungen. */
final class Mailer
{
    /** $requireTls: SMTP mit STARTTLS nur verschlüsselt (bricht ab, wenn der Server kein TLS anbietet) – für Inhalte (Core\Data\Delivery) */
    /** DSN des Kontos für einen Zweck (Core\Mail\Accounts) – Standard: Konto & Sicherheit der Website */
    public static function dsn(bool $requireTls = false, string $purpose = 'account', bool $network = false): string
    {
        return Mail\Accounts::dsn(Mail\Accounts::route($purpose, $network), $requireTls);
    }

    /**
     * Versand für einen Zweck eingerichtet? Gültiger Absender und Versandweg des gewählten Kontos (Core\Mail\Accounts); lokal
     * genügt config 'mail_dump'. Versandart „Deaktiviert“ gilt als nicht eingerichtet. Ohne Verbindungstest – z. B. „Passwort vergessen“.
     */
    public static function ready(string $purpose = 'account', bool $network = false): bool
    {
        if (self::$testDump !== null || app()->config->get('mail_dump')) return true;
        return Mail\Accounts::ready(Mail\Accounts::route($purpose, $network));
    }

    /** Konto des letzten Versands (id, Beschreibung) – für Protokolle und Tests */
    public static ?array $account = null;

    /**
     * Zuletzt versendete E-Mail wirklich an die Empfänger übergeben? false: nur protokolliert, umgeleitet (außerhalb der
     * Produktion), als Datei abgelegt (config 'mail_dump') oder Versandart „Deaktiviert“ – z. B. Einladungen zeigen dann den Link.
     */
    public static bool $delivered = false;

    /** @var array<string, \Symfony\Component\Mailer\Transport\TransportInterface> offene Verbindungen (keep_alive) je Konto */
    private static array $keep = [];

    /** Offene Verbindungen (keep_alive) schließen – am Ende eines Stapels */
    public static function closeTransports(): void
    {
        foreach (self::$keep as $t) {
            if ($t instanceof \Symfony\Component\Mailer\Transport\Smtp\SmtpTransport) { try { $t->stop(); } catch (\Throwable) {} }
        }
        self::$keep = [];
    }

    /** Nur Selbsttests (z. B. Core\Data\Delivery::selftest): wie config 'mail_dump', Ablage in diesen Ordner statt Versand */
    public static ?string $testDump = null;

    /**
     * Ergebnis des letzten Versands: sent (übergeben), redirected (außerhalb der Produktion umgeleitet), dumped (config 'mail_dump'),
     * logged (außerhalb der Produktion nur protokolliert – NICHT zugestellt), failed. Für Inhalte (Core\Data\Delivery) zählt „logged“ als Fehlschlag.
     */
    public static string $outcome = '';

    /**
     * E-Mail senden – reiner Text oder zusätzlich HTML (multipart/alternative).
     * @param array{html?:string, inline?:array<string,array{path:string, type?:string}>, attach?:list<array{name:string, data:string, type?:string}>} $o
     *        inline: eingebettete Bilder, im HTML als „cid:{name}“ (z. B. App-Icon als Logo)
     *        attach: Anhänge aus dem Speicher (z. B. Termin als .ics, type text/calendar) – klein halten
     *        reply_to: Antwortadresse; message_id: eigene Message-ID (ohne < >), z. B. für das Zustellprotokoll
     *        return_path: Rücklaufadresse (Return-Path/Envelope-From) – Newsletter-Erweiterungen mit eigener Bounce-Adresse (VERP)
     *        keep_alive: Verbindung des Kontos für weitere Mails offen halten (Stapelversand im Cron, closeTransports() am Ende)
     *        smime: Zertifikat(e) der Empfänger (PEM) – die ganze Nachricht (Text, HTML, Anhänge) wird mit S/MIME verschlüsselt
     *               (openssl_pkcs7_encrypt über Symfony SMimeEncrypter, AES-256-CBC); unverschlüsselt bleiben nur Kopfzeilen wie der Betreff
     *        require_tls: SMTP nur mit TLS (siehe dsn())
     * @return string|null Fehlermeldung oder null bei Erfolg
     */
    /** Test-Umleitung aus den Grundeinstellungen (sys.mail_redirect): gültige Adressen, leer = aus */
    public static function testRedirect(): array
    {
        $raw = (string) (app()->settings->get('sys.mail_redirect', '') ?? '');
        return array_values(array_filter(array_map('trim', preg_split('~[,;\s]+~', $raw) ?: []), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
    }

    public static function send(string $subject, string $text, ?array $to = null, array $o = []): ?string
    {
        self::$delivered = false;
        self::$outcome = 'failed';
        $s = app()->settings;
        $to ??= array_filter(array_map('trim', explode(',', (string) $s->get('sys.mail_to', ''))));
        // Konto je Zweck (Core\Mail\Accounts): Absender, Antwortadresse und Versandweg kommen vom Konto. Netzwerk-Konten
        // (network: true) nur über das Netzwerk-Konto – nie über das Konto einer Website.
        $purpose = (string) ($o['purpose'] ?? (!empty($o['account']) ? 'account' : 'notify'));
        $acc = Mail\Accounts::route($purpose, !empty($o['network']));
        self::$account = ['id' => $acc['id'], 'scope' => $acc['scope'], 'purpose' => $purpose];
        $from = $acc['from'];
        if (!$to) {
            return 'Kein Empfänger eingetragen (System → E-Mail-Versand).';
        }
        // Lokale Tests: als Datei ablegen statt versenden (config 'mail_dump' => true bzw. Ordner) – nie an echte Empfänger
        $dump = self::$testDump ?? app()->config->get('mail_dump');
        if (!$dump && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return 'Keine gültige Absender-Adresse eingetragen.';
        }
        // Erweiterungen (Extension::filter 'mail.message'): ändern oder mit false unterdrücken – nie Mails zur Kontosicherheit;
        // Test-Umleitung und Staging-Schutz gelten danach weiter
        if (empty($o['account']) && Extensions::filters('mail.message')) {
            $m = Extensions::filter('mail.message', ['subject' => $subject, 'text' => $text, 'to' => $to, 'options' => $o], ['account' => false, 'purpose' => $purpose, 'mail_account' => $acc['id']]);
            if ($m === false) { self::$outcome = 'logged'; return null; }
            if (is_array($m)) {
                $subject = (string) ($m['subject'] ?? $subject);
                $text = (string) ($m['text'] ?? $text);
                $to = array_values(array_filter((array) ($m['to'] ?? $to), fn($a) => is_string($a) && filter_var($a, FILTER_VALIDATE_EMAIL))) ?: $to;
                $o = is_array($m['options'] ?? null) ? $m['options'] : $o;
            }
        }
        $redirected = false;
        // Test-Umleitung (Grundeinstellungen → E-Mail-Versand, auch live): alle E-Mails an die Test-Adresse(n) – nie Mails zur
        // Kontosicherheit ($o['account']: Passwort, Einladung, E-Mail-Änderung), sonst sperrt man sich beim Testen aus.
        // Staging/Entwicklung: nie an echte Empfänger – umleiten (Test-Umleitung bzw. config 'mail_redirect') oder nur protokollieren
        $prod = environment() === 'production';
        $test = empty($o['account']) ? self::testRedirect() : [];
        // Testnachricht „Testumgebung umgehen“ (E-Mail-Konten, Netzwerk-Dashboard): wirklich an genau diese eine Adresse senden,
        // um Zugangsdaten auch auf Staging zu prüfen – ohne Umleitung, mit Hinweis im Protokoll
        $real = !empty($o['force_real']) && count($to) === 1;
        if ($real && (!$prod || $test)) {
            error_log('[Mailer] ' . ($prod ? 'Test-Umleitung' : environment()) . ' umgangen (Testnachricht): „' . $subject . '“ an ' . $to[0]);
        }
        if (!$real && (!$prod || $test)) {
            $redirect = $test ?: array_filter(array_map('trim', explode(',', (string) app()->config->get('mail_redirect', ''))));
            if (!$dump) error_log('[Mailer] ' . ($prod ? 'Test-Umleitung' : environment()) . ': „' . $subject . '“ an ' . implode(', ', $to) . ($redirect ? ' → umgeleitet an ' . implode(', ', $redirect) : ' → nicht versendet'));
            if (!$redirect && !$dump) { self::$outcome = 'logged'; return null; }
            if ($redirect) {
                $note = 'Ursprüngliche Empfänger: ' . implode(', ', $to);
                $subject = '[' . ($prod ? 'TEST' : strtoupper(environment())) . '] ' . $subject;
                $text = $note . "\n\n" . $text;
                if (isset($o['html'])) {
                    $o['html'] = preg_replace('~<body[^>]*>~i', '$0<p style="margin:0;padding:8px 12px;background:#FFF3C4;color:#111;font:13px sans-serif">' . htmlspecialchars($note) . '</p>', (string) $o['html'], 1);
                }
                $to = $redirect;
                $redirected = true;
            }
        }
        if ($dump) {
            return self::dump($dump === true ? site()->storage('mail') : (string) $dump, $subject, $text, $to,
                filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : 'website@example.com', $o + ['_account' => $acc]);
        }
        try {
            $tk = $acc['id'] . '|' . (empty($o['require_tls']) ? 0 : 1);
            $transport = !empty($o['keep_alive']) ? (self::$keep[$tk] ??= Mail\Accounts::transport($acc, !empty($o['require_tls'])))
                : Mail\Accounts::transport($acc, !empty($o['require_tls']));
            $mailer = new SymfonyMailer($transport);
            $mailer->send(self::build($from, $subject, $text, $to, $o + ['_account' => $acc]));
            $null = $acc['transport'] === 'null';
            self::$delivered = !$redirected && !$null;
            self::$outcome = $null ? 'logged' : ($redirected ? 'redirected' : 'sent');
            return null;
        } catch (\Throwable $e) {
            error_log('[Mailer] ' . $e->getMessage());
            $hint = Mail\Accounts::hint($e->getMessage());
            return 'Versand fehlgeschlagen: ' . $e->getMessage() . ($hint !== null ? ' – ' . $hint : '');
        }
    }

    private static function build(string $from, string $subject, string $text, array $to, array $o): \Symfony\Component\Mime\Message
    {
        $acc = (array) ($o['_account'] ?? []);
        $email = (new Email())
            ->from(new Address($from, (string) ($acc['from_name'] ?? app()->settings->get('sys.mail_from_name', 'Website'))))
            ->subject($subject)
            ->text($text);
        if (isset($o['html']) && $o['html'] !== '') {
            $email->html((string) $o['html']);
            foreach ((array) ($o['inline'] ?? []) as $cid => $img) {
                if (is_file((string) ($img['path'] ?? ''))) $email->embedFromPath((string) $img['path'], (string) $cid, $img['type'] ?? null);
            }
        }
        foreach ((array) ($o['attach'] ?? []) as $a) {
            $name = basename((string) ($a['name'] ?? ''));
            if ($name !== '' && isset($a['data'])) $email->attach((string) $a['data'], $name, (string) ($a['type'] ?? 'application/octet-stream'));
        }
        foreach ($to as $addr) {
            $email->addTo($addr);
        }
        $reply = (string) ($o['reply_to'] ?? '') ?: (string) ($acc['reply_to'] ?? '');   // eigene Antwortadresse, sonst die des Kontos
        if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL)) $email->replyTo($reply);
        // Zusätzliche Kopfzeilen (z. B. List-Unsubscribe für Newsletter-Erweiterungen) – nur einfache Textwerte, keine Adressfelder
        foreach ((array) ($o['headers'] ?? []) as $hn => $hv) {
            if (is_string($hn) && preg_match('~^(List-[A-Za-z-]+|X-[A-Za-z0-9-]+|Precedence)$~', $hn) && is_scalar($hv)) $email->getHeaders()->addTextHeader($hn, (string) $hv);
        }
        if (!empty($o['message_id'])) $email->getHeaders()->addIdHeader('Message-ID', (string) $o['message_id']);
        // Rücklaufadresse (VERP der Newsletter-Erweiterung): Symfony nimmt Return-Path als Envelope-From
        if (!empty($o['return_path']) && filter_var((string) $o['return_path'], FILTER_VALIDATE_EMAIL)) $email->returnPath((string) $o['return_path']);
        if (!empty($o['smime'])) return self::encrypt($email, (string) $o['smime']);
        return $email;
    }

    /**
     * S/MIME-Verschlüsselung (RFC 5751, enveloped-data) mit den Zertifikaten der Empfänger (PEM, ein oder mehrere Blöcke).
     * Die Zertifikate liegen nur für den Aufruf in einer temporären Datei (0600) – Symfony erwartet Dateipfade.
     */
    private static function encrypt(Email $email, string $pem): \Symfony\Component\Mime\Message
    {
        preg_match_all('~-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----~s', $pem, $m);
        if (!$m[0]) throw new \RuntimeException('Kein S/MIME-Zertifikat für die Verschlüsselung.');
        $files = [];
        try {
            foreach ($m[0] as $cert) {
                $f = tempnam(sys_get_temp_dir(), 'smime');
                if ($f === false || file_put_contents($f, $cert . "\n") === false) throw new \RuntimeException('Temporäre Datei für S/MIME nicht beschreibbar.');
                chmod($f, 0600);
                $files[] = $f;
            }
            return (new \Symfony\Component\Mime\Crypto\SMimeEncrypter(count($files) === 1 ? $files[0] : $files, OPENSSL_CIPHER_AES_256_CBC))->encrypt($email);
        } finally {
            foreach ($files as $f) @unlink($f);
        }
    }

    /**
     * Ablage statt Versand (config 'mail_dump'): {Ordner}/{Zeit}-{Betreff}.eml (vollständige Nachricht) und – bei HTML –
     * .html zur Vorschau im Browser (eingebettete Bilder als data:-URI). Nur für lokale Tests gedacht.
     */
    private static function dump(string $dir, string $subject, string $text, array $to, string $from, array $o): ?string
    {
        try {
            if (!is_dir($dir) && !@mkdir($dir, 0770, true)) return 'Ordner für mail_dump ist nicht beschreibbar: ' . $dir;
            $base = $dir . '/' . date('Ymd-His') . '-' . substr(trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower($subject)), '-'), 0, 40) . '-' . bin2hex(random_bytes(2));
            file_put_contents($base . '.eml', self::build($from, $subject, $text, $to, $o)->toString());
            self::$outcome = 'dumped';
            if (isset($o['html']) && $o['html'] !== '' && empty($o['smime'])) {          // verschlüsselt: keine Klartext-Vorschau
                $html = (string) $o['html'];
                foreach ((array) ($o['inline'] ?? []) as $cid => $img) {
                    if (is_file((string) ($img['path'] ?? ''))) {
                        $html = str_replace('cid:' . $cid, 'data:' . ($img['type'] ?? 'image/png') . ';base64,' . base64_encode((string) file_get_contents((string) $img['path'])), $html);
                    }
                }
                file_put_contents($base . '.html', $html);
            }
            error_log('[Mailer] mail_dump: „' . $subject . '“ an ' . implode(', ', $to) . ' → ' . $base . '.eml');
            return null;
        } catch (\Throwable $e) {
            return 'Ablage fehlgeschlagen: ' . $e->getMessage();
        }
    }
}
