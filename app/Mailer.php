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
    public static function dsn(bool $requireTls = false): string
    {
        $s = app()->settings;
        $t = (string) $s->get('sys.mail_transport', 'smtp');
        return match ($t) {
            'sendmail' => 'sendmail://default',
            'native' => 'native://default',
            'null' => 'null://null',
            default => self::smtpDsn($requireTls),
        };
    }

    /**
     * Versand eingerichtet? Gültiger Absender und SMTP-Server (bzw. sendmail/native); lokal genügt config 'mail_dump'.
     * Versandart „Deaktiviert“ (null) gilt als nicht eingerichtet. Ohne Verbindungstest – z. B. für „Passwort vergessen“.
     */
    public static function ready(): bool
    {
        if (self::$testDump !== null || app()->config->get('mail_dump')) return true;
        $s = app()->settings;
        if (!filter_var((string) $s->get('sys.mail_from', ''), FILTER_VALIDATE_EMAIL)) return false;
        return match ((string) $s->get('sys.mail_transport', 'smtp')) {
            'null' => false,
            'sendmail', 'native' => true,
            default => (string) $s->get('sys.mail_host', '') !== '',
        };
    }

    private static function smtpDsn(bool $requireTls = false): string
    {
        $s = app()->settings;
        $host = (string) $s->get('sys.mail_host', '');
        if ($host === '') {
            throw new \RuntimeException('Kein SMTP-Server eingetragen (System → E-Mail-Versand).');
        }
        $enc = (string) $s->get('sys.mail_encryption', 'tls');
        $scheme = $enc === 'ssl' ? 'smtps' : 'smtp';
        $user = rawurlencode((string) $s->get('sys.mail_user', ''));
        $pass = rawurlencode((string) $s->get('sys.mail_pass', ''));
        $auth = $user !== '' ? "$user:$pass@" : '';
        $port = (int) ($s->get('sys.mail_port') ?: ($enc === 'ssl' ? 465 : 587));
        $q = [];
        if (!$s->get('sys.mail_verify_peer', true)) {
            $q[] = 'verify_peer=0';
        }
        if ($enc === 'none') {
            $q[] = 'auto_tls=false';
        } elseif ($enc === 'tls' && $requireTls) {
            $q[] = 'require_tls=true';
        }
        return "$scheme://$auth$host:$port" . ($q ? '?' . implode('&', $q) : '');
    }

    /**
     * Zuletzt versendete E-Mail wirklich an die Empfänger übergeben? false: nur protokolliert, umgeleitet (außerhalb der
     * Produktion), als Datei abgelegt (config 'mail_dump') oder Versandart „Deaktiviert“ – z. B. Einladungen zeigen dann den Link.
     */
    public static bool $delivered = false;

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
     *        smime: Zertifikat(e) der Empfänger (PEM) – die ganze Nachricht (Text, HTML, Anhänge) wird mit S/MIME verschlüsselt
     *               (openssl_pkcs7_encrypt über Symfony SMimeEncrypter, AES-256-CBC); unverschlüsselt bleiben nur Kopfzeilen wie der Betreff
     *        require_tls: SMTP nur mit TLS (siehe dsn())
     * @return string|null Fehlermeldung oder null bei Erfolg
     */
    public static function send(string $subject, string $text, ?array $to = null, array $o = []): ?string
    {
        self::$delivered = false;
        self::$outcome = 'failed';
        $s = app()->settings;
        $to ??= array_filter(array_map('trim', explode(',', (string) $s->get('sys.mail_to', ''))));
        $from = (string) $s->get('sys.mail_from', '');
        if (!$to) {
            return 'Kein Empfänger eingetragen (System → E-Mail-Versand).';
        }
        // Lokale Tests: als Datei ablegen statt versenden (config 'mail_dump' => true bzw. Ordner) – nie an echte Empfänger
        if ($dump = self::$testDump ?? app()->config->get('mail_dump')) {
            return self::dump($dump === true ? site()->storage('mail') : (string) $dump, $subject, $text, $to,
                filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : 'website@example.com', $o);
        }
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return 'Keine gültige Absender-Adresse eingetragen.';
        }
        $redirected = false;
        // Staging/Entwicklung: nie an echte Empfänger – umleiten (config 'mail_redirect') oder nur protokollieren
        if (environment() !== 'production') {
            $redirect = array_filter(array_map('trim', explode(',', (string) app()->config->get('mail_redirect', ''))));
            error_log('[Mailer] ' . environment() . ': „' . $subject . '“ an ' . implode(', ', $to) . ($redirect ? ' → umgeleitet an ' . implode(', ', $redirect) : ' → nicht versendet'));
            if (!$redirect) { self::$outcome = 'logged'; return null; }
            $note = 'Ursprüngliche Empfänger: ' . implode(', ', $to);
            $subject = '[' . strtoupper(environment()) . '] ' . $subject;
            $text = $note . "\n\n" . $text;
            if (isset($o['html'])) {
                $o['html'] = preg_replace('~<body[^>]*>~i', '$0<p style="margin:0;padding:8px 12px;background:#FFF3C4;color:#111;font:13px sans-serif">' . htmlspecialchars($note) . '</p>', (string) $o['html'], 1);
            }
            $to = $redirect;
            $redirected = true;
        }
        try {
            $mailer = new SymfonyMailer(Transport::fromDsn(self::dsn(!empty($o['require_tls']))));
            $mailer->send(self::build($from, $subject, $text, $to, $o));
            $null = (string) $s->get('sys.mail_transport', 'smtp') === 'null';
            self::$delivered = !$redirected && !$null;
            self::$outcome = $null ? 'logged' : ($redirected ? 'redirected' : 'sent');
            return null;
        } catch (\Throwable $e) {
            error_log('[Mailer] ' . $e->getMessage());
            return 'Versand fehlgeschlagen: ' . $e->getMessage();
        }
    }

    private static function build(string $from, string $subject, string $text, array $to, array $o): \Symfony\Component\Mime\Message
    {
        $email = (new Email())
            ->from(new Address($from, (string) app()->settings->get('sys.mail_from_name', 'Website')))
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
        if (!empty($o['reply_to']) && filter_var((string) $o['reply_to'], FILTER_VALIDATE_EMAIL)) $email->replyTo((string) $o['reply_to']);
        if (!empty($o['message_id'])) $email->getHeaders()->addIdHeader('Message-ID', (string) $o['message_id']);
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
