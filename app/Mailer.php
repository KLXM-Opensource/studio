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
    public static function dsn(): string
    {
        $s = app()->settings;
        $t = (string) $s->get('sys.mail_transport', 'smtp');
        return match ($t) {
            'sendmail' => 'sendmail://default',
            'native' => 'native://default',
            'null' => 'null://null',
            default => self::smtpDsn(),
        };
    }

    private static function smtpDsn(): string
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
        }
        return "$scheme://$auth$host:$port" . ($q ? '?' . implode('&', $q) : '');
    }

    /**
     * Zuletzt versendete E-Mail wirklich an die Empfänger übergeben? false: nur protokolliert, umgeleitet (außerhalb der
     * Produktion), als Datei abgelegt (config 'mail_dump') oder Versandart „Deaktiviert“ – z. B. Einladungen zeigen dann den Link.
     */
    public static bool $delivered = false;

    /**
     * E-Mail senden – reiner Text oder zusätzlich HTML (multipart/alternative).
     * @param array{html?:string, inline?:array<string,array{path:string, type?:string}>} $o
     *        inline: eingebettete Bilder, im HTML als „cid:{name}“ (z. B. App-Icon als Logo)
     * @return string|null Fehlermeldung oder null bei Erfolg
     */
    public static function send(string $subject, string $text, ?array $to = null, array $o = []): ?string
    {
        self::$delivered = false;
        $s = app()->settings;
        $to ??= array_filter(array_map('trim', explode(',', (string) $s->get('sys.mail_to', ''))));
        $from = (string) $s->get('sys.mail_from', '');
        if (!$to) {
            return 'Kein Empfänger eingetragen (System → E-Mail-Versand).';
        }
        // Lokale Tests: als Datei ablegen statt versenden (config 'mail_dump' => true bzw. Ordner) – nie an echte Empfänger
        if ($dump = app()->config->get('mail_dump')) {
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
            if (!$redirect) return null;
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
            $mailer = new SymfonyMailer(Transport::fromDsn(self::dsn()));
            $mailer->send(self::build($from, $subject, $text, $to, $o));
            self::$delivered = !$redirected && (string) $s->get('sys.mail_transport', 'smtp') !== 'null';
            return null;
        } catch (\Throwable $e) {
            error_log('[Mailer] ' . $e->getMessage());
            return 'Versand fehlgeschlagen: ' . $e->getMessage();
        }
    }

    private static function build(string $from, string $subject, string $text, array $to, array $o): Email
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
        foreach ($to as $addr) {
            $email->addTo($addr);
        }
        return $email;
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
            if (isset($o['html']) && $o['html'] !== '') {
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
