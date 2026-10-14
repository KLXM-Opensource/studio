<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Mail;

use Core\Extensions;
use Core\Network\Network;
use Core\Settings;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * E-Mail-Konten: „Standard“ (Grundeinstellungen → E-Mail-Versand, sys.mail_*), weitere Konten der Website (sys.mail_accounts)
 * und das Netzwerk-Konto der Installation (Netzwerk-Datenbank, Tabelle network_mail). Je Zweck (Core\Mail\Purposes) wählt die
 * Website ein Konto (sys.mail_routes); Mails für Netzwerk-Konten laufen nur über das Netzwerk-Konto.
 *
 * Datenschutz zwischen Kunden: Netzwerk-Mails nutzen nie das Konto einer Website, Websites nie das Konto einer anderen; ohne
 * eigenes Konto darf eine Website das Netzwerk-Konto (neutraler Absender) nutzen. Absender, Antwortadresse und Message-ID
 * (Domain des Absenders) kommen immer vom verwendeten Konto.
 *
 * Konto = ['id', 'label', 'transport' (smtp|dsn|null|ext:{schema}), 'host', 'port', 'user', 'pass',
 *          'encryption' (tls|ssl|none), 'verify_peer', 'dsn' (Symfony-DSN, z. B. „brevo+api://SCHLÜSSEL@default“ – braucht das
 *          Bridge-Paket), 'from', 'from_name', 'reply_to', 'scope' (site|network)]. Geheimnisse (pass, dsn) verschlüsselt gespeichert.
 */
final class Accounts
{
    /** Versandarten: SMTP, Dienst per Symfony-DSN (API), deaktiviert – sendmail/PHP-mail() bewusst nicht (Zustellbarkeit, kein SPF/DKIM) */
    public const TRANSPORTS = ['smtp', 'dsn', 'null'];
    public const SECRET_KEYS = ['pass', 'dsn'];
    private const NET_TABLE = 'network_mail';

    /** Konto „Standard“ aus den Grundeinstellungen (bisheriger Einzel-Versand) */
    public static function standard(): array
    {
        $s = app()->settings;
        return ['id' => 'standard', 'label' => __('Standard'), 'scope' => 'site',
            'transport' => (string) $s->get('sys.mail_transport', 'smtp'), 'host' => (string) $s->get('sys.mail_host', ''),
            'port' => (int) ($s->get('sys.mail_port') ?: 0), 'user' => (string) $s->get('sys.mail_user', ''), 'pass' => (string) $s->get('sys.mail_pass', ''),
            'encryption' => (string) $s->get('sys.mail_encryption', 'tls'), 'verify_peer' => (bool) $s->get('sys.mail_verify_peer', true),
            'dsn' => '', 'from' => (string) $s->get('sys.mail_from', ''), 'from_name' => (string) $s->get('sys.mail_from_name', 'Website'), 'reply_to' => ''];
    }

    /** Weitere Konten der Website (ohne Standard) – Geheimnisse entschlüsselt */
    public static function extra(): array
    {
        $out = [];
        foreach ((array) app()->settings->get('sys.mail_accounts', []) as $a) {
            if (!is_array($a) || !preg_match('~^[a-z0-9-]{2,32}$~', (string) ($a['id'] ?? ''))) continue;
            foreach (self::SECRET_KEYS as $k) $a[$k] = Settings::decrypt((string) ($a[$k] ?? ''));
            $out[$a['id']] = self::normalize($a) + ['scope' => 'site'];
        }
        return $out;
    }

    /** Konten speichern (ohne Standard) – Geheimnisse verschlüsselt */
    public static function saveExtra(array $accounts): void
    {
        $out = [];
        foreach ($accounts as $a) {
            $a = self::normalize($a);
            foreach (self::SECRET_KEYS as $k) $a[$k] = $a[$k] !== '' ? Settings::encrypt($a[$k]) : '';
            unset($a['scope']);
            $out[] = $a;
        }
        app()->settings->set('sys.mail_accounts', $out);
    }

    public static function normalize(array $a): array
    {
        $t = (string) ($a['transport'] ?? 'smtp');
        if (!in_array($t, self::TRANSPORTS, true) && !(str_starts_with($t, 'ext:') && Extensions::mailTransport(substr($t, 4)))) $t = 'smtp';
        return ['id' => (string) ($a['id'] ?? ''), 'label' => mb_substr(trim((string) ($a['label'] ?? '')), 0, 80), 'transport' => $t,
            'host' => trim((string) ($a['host'] ?? '')), 'port' => max(0, min(65535, (int) ($a['port'] ?? 0))), 'user' => (string) ($a['user'] ?? ''),
            'pass' => (string) ($a['pass'] ?? ''), 'encryption' => in_array($a['encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $a['encryption'] : 'tls',
            'verify_peer' => (bool) ($a['verify_peer'] ?? true), 'dsn' => trim((string) ($a['dsn'] ?? '')),
            'from' => trim((string) ($a['from'] ?? '')), 'from_name' => mb_substr(trim((string) ($a['from_name'] ?? '')), 0, 120),
            'reply_to' => trim((string) ($a['reply_to'] ?? '')), 'scope' => ($a['scope'] ?? 'site') === 'network' ? 'network' : 'site'];
    }

    // ------------------------------------------------------------------ Netzwerk-Konto (Installation)

    /** Schlüssel für Geheimnisse des Netzwerk-Kontos (aus dem app_key der Netzwerk-Website – gleich auf allen Websites) */
    private static function netKey(): string
    {
        return sodium_crypto_generichash('network-mail|' . Network::appKey(), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private static function netSeal(string $v): string
    {
        if ($v === '') return '';
        $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'nm1:' . base64_encode($n . sodium_crypto_secretbox($v, $n, self::netKey()));
    }

    private static function netOpen(string $v): string
    {
        if (!str_starts_with($v, 'nm1:')) return $v;
        $raw = base64_decode(substr($v, 4), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
        $out = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::netKey());
        return $out === false ? '' : $out;
    }

    private static function netDb(): ?\Core\Database
    {
        try {
            $db = Network::db();
            $db->pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::NET_TABLE . ' (k VARCHAR(40) NOT NULL PRIMARY KEY, v TEXT)');
            return $db;
        } catch (\Throwable) {
            return null;   // keine Netzwerk-Website (Einzelinstallation ohne Netzwerk)
        }
    }

    /** Netzwerk-Konto oder null (nicht eingerichtet) */
    /** @var array<string, ?array> Netzwerk-Konto je Netzwerk-Website (pro Anfrage) */
    private static array $netCache = [];

    public static function network(): ?array
    {
        $k = Network::siteKey();
        $cache = &self::$netCache;
        if (array_key_exists($k, $cache)) return $cache[$k];
        $db = self::netDb();
        $raw = $db ? $db->fetchValue('SELECT v FROM ' . self::NET_TABLE . " WHERE k = 'account'") : null;
        $a = $raw ? json_decode((string) $raw, true) : null;
        if (!is_array($a)) return $cache[$k] = null;
        foreach (self::SECRET_KEYS as $s) $a[$s] = self::netOpen((string) ($a[$s] ?? ''));
        return $cache[$k] = ['id' => 'network', 'label' => __('Netzwerk')] + self::normalize($a + ['scope' => 'network']);
    }

    /** Netzwerk-Konto speichern (null = entfernen) */
    public static function saveNetwork(?array $a): void
    {
        $db = self::netDb() ?? throw new \RuntimeException(__('Keine Netzwerk-Datenbank.'));
        self::$netCache = [];
        $db->query('DELETE FROM ' . self::NET_TABLE . " WHERE k = 'account'");
        if ($a !== null) {
            $a = self::normalize($a + ['scope' => 'network']);
            foreach (self::SECRET_KEYS as $s) $a[$s] = self::netSeal($a[$s]);
            unset($a['id'], $a['label'], $a['scope']);
            $db->insert(self::NET_TABLE, ['k' => 'account', 'v' => json_encode($a, JSON_UNESCAPED_UNICODE)]);
        }
    }

    // ------------------------------------------------------------------ Auswahl

    /** Alle Konten, die diese Website wählen kann: standard, weitere, network (wenn eingerichtet) */
    public static function all(): array
    {
        $out = ['standard' => self::standard()] + self::extra();
        if ($n = self::network()) $out['network'] = $n;
        return $out;
    }

    public static function find(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    /** Zuordnung Zweck → Konto (sys.mail_routes) */
    public static function routes(): array
    {
        return array_filter((array) app()->settings->get('sys.mail_routes', []), 'is_string');
    }

    /**
     * Konto für einen Versand. $network = Mail für ein Netzwerk-Konto (Einladung, Sicherheit): nur das Netzwerk-Konto –
     * fehlt es, bleibt es beim Standard der Website (Health meldet das auf der Netzwerk-Website).
     * Website ohne eigenes, betriebsbereites Konto: Netzwerk-Konto (neutraler Absender), sonst Standard.
     */
    public static function route(string $purpose, bool $network = false): array
    {
        if ($network && ($n = self::network()) && self::ready($n)) return $n;
        $id = self::routes()[$purpose] ?? 'standard';
        $a = self::find($id) ?? self::standard();
        if (!self::ready($a) && ($n = self::network()) && self::ready($n)) return $n;
        return $a;
    }

    /** Konto versandbereit (ohne Verbindungstest)? */
    public static function ready(array $a): bool
    {
        if (!filter_var($a['from'], FILTER_VALIDATE_EMAIL)) return false;
        return match (true) {
            $a['transport'] === 'null' => false,
            $a['transport'] === 'dsn' => $a['dsn'] !== '',
            str_starts_with($a['transport'], 'ext:') => Extensions::mailTransport(substr($a['transport'], 4)) !== null,
            default => $a['host'] !== '',
        };
    }

    // ------------------------------------------------------------------ Versandweg

    public static function dsn(array $a, bool $requireTls = false): string
    {
        return match ($a['transport']) {
            'null' => 'null://null',
            'dsn' => $a['dsn'] !== '' ? $a['dsn'] : throw new \RuntimeException(__('Keine DSN eingetragen.')),
            default => self::smtpDsn($a, $requireTls),
        };
    }

    private static function smtpDsn(array $a, bool $requireTls): string
    {
        if ($a['host'] === '') throw new \RuntimeException(__('Kein SMTP-Server eingetragen.'));
        $scheme = $a['encryption'] === 'ssl' ? 'smtps' : 'smtp';
        $auth = $a['user'] !== '' ? rawurlencode($a['user']) . ':' . rawurlencode($a['pass']) . '@' : '';
        $port = $a['port'] ?: ($a['encryption'] === 'ssl' ? 465 : 587);
        $q = [];
        if (!$a['verify_peer']) $q[] = 'verify_peer=0';
        if ($a['encryption'] === 'none') $q[] = 'auto_tls=false';
        elseif ($a['encryption'] === 'tls' && $requireTls) $q[] = 'require_tls=true';
        return "$scheme://$auth{$a['host']}:$port" . ($q ? '?' . implode('&', $q) : '');
    }

    /** Symfony-Transport für ein Konto (Versandwege der Erweiterungen über Extension::mailTransport) */
    public static function transport(array $a, bool $requireTls = false): TransportInterface
    {
        if (str_starts_with($a['transport'], 'ext:')) {
            $t = Extensions::mailTransport(substr($a['transport'], 4)) ?? throw new \RuntimeException(__('Versandweg „{name}“ ist nicht verfügbar.', ['name' => substr($a['transport'], 4)]));
            return ($t['factory'])($a);
        }
        try {
            return Transport::fromDsn(self::dsn($a, $requireTls));
        } catch (\Symfony\Component\Mailer\Exception\UnsupportedSchemeException $e) {
            // z. B. „brevo+api“ ohne Paket: Symfony nennt das fehlende Paket (composer require symfony/brevo-mailer)
            throw new \RuntimeException(__('Dieser Dienst braucht ein zusätzliches Paket: {msg}', ['msg' => $e->getMessage()]));
        }
    }

    /** Anzeige ohne Geheimnisse (Verwaltung, Health) */
    public static function describe(array $a): string
    {
        $how = match (true) {
            $a['transport'] === 'dsn' => (string) (parse_url($a['dsn'], PHP_URL_SCHEME) ?: 'DSN'),
            str_starts_with($a['transport'], 'ext:') => (string) (Extensions::mailTransport(substr($a['transport'], 4))['label'] ?? substr($a['transport'], 4)),
            $a['transport'] === 'smtp' => trim('SMTP ' . $a['host']),
            default => $a['transport'],
        };
        $who = $a['from'] !== '' ? trim(($a['from_name'] !== '' ? $a['from_name'] . ' ' : '') . '<' . $a['from'] . '>') : __('kein Absender');
        return $who . ' · ' . $how;
    }

    /**
     * Prüfungen für health (null = Warnung, blockiert nichts): Netzwerk-Konto in Installationen mit mehreren Websites, Zwecke
     * mit fehlendem Konto. Bezeichnungen ohne Geheimnisse.
     */
    public static function health(): array
    {
        $out = [];
        try {
            if (\Core\Sites::multi() && Network::isNetworkSite()) {
                $n = self::network();
                $out[$n ? 'E-Mail: Netzwerk-Konto eingerichtet (' . $n['from'] . ')' : 'E-Mail: Netzwerk-Konto fehlt – Mails an Netzwerk-Konten gehen über das Konto dieser Website (Grundeinstellungen → E-Mail-Konten)']
                    = $n ? self::ready($n) : null;
            }
            $all = self::all();
            foreach (self::routes() as $purpose => $id) {
                if (!isset($all[$id])) $out['E-Mail: Zweck „' . $purpose . '“ zeigt auf ein fehlendes Konto – versendet über „Standard“'] = null;
            }
        } catch (\Throwable $e) {
            $out['E-Mail-Konten lesbar (' . $e->getMessage() . ')'] = null;
        }
        return $out;
    }
}
