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

    /** @return string|null Fehlermeldung oder null bei Erfolg */
    public static function send(string $subject, string $text, ?array $to = null): ?string
    {
        $s = app()->settings;
        $to ??= array_filter(array_map('trim', explode(',', (string) $s->get('sys.mail_to', ''))));
        $from = (string) $s->get('sys.mail_from', '');
        if (!$to) {
            return 'Kein Empfänger eingetragen (System → E-Mail-Versand).';
        }
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return 'Keine gültige Absender-Adresse eingetragen.';
        }
        // Staging/Entwicklung: nie an echte Empfänger – umleiten (config 'mail_redirect') oder nur protokollieren
        if (environment() !== 'production') {
            $redirect = array_filter(array_map('trim', explode(',', (string) app()->config->get('mail_redirect', ''))));
            error_log('[Mailer] ' . environment() . ': „' . $subject . '“ an ' . implode(', ', $to) . ($redirect ? ' → umgeleitet an ' . implode(', ', $redirect) : ' → nicht versendet'));
            if (!$redirect) return null;
            $subject = '[' . strtoupper(environment()) . '] ' . $subject;
            $text = 'Ursprüngliche Empfänger: ' . implode(', ', $to) . "\n\n" . $text;
            $to = $redirect;
        }
        try {
            $mailer = new SymfonyMailer(Transport::fromDsn(self::dsn()));
            $email = (new Email())
                ->from(new Address($from, (string) $s->get('sys.mail_from_name', 'Website')))
                ->subject($subject)
                ->text($text);
            foreach ($to as $addr) {
                $email->addTo($addr);
            }
            $mailer->send($email);
            return null;
        } catch (\Throwable $e) {
            error_log('[Mailer] ' . $e->getMessage());
            return 'Versand fehlgeschlagen: ' . $e->getMessage();
        }
    }
}
