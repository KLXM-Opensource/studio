<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Features;
use Core\FormCrypto;
use Core\Lang;
use Core\Mailer;

/**
 * Zustellung der Anfragen einer Eingangs-Tabelle (settings.inbox.delivery, Funktion „requests.mail“).
 *
 * Modi:
 *   system – „Im System (verschlüsselt)“: wie bisher (Inbox::store), Benachrichtigung ohne Inhalte.
 *   both   – „Im System und per E-Mail“: versiegelt speichern UND den vollständigen Inhalt per E-Mail zustellen.
 *   mail   – „Nur per E-Mail – nicht im System speichern“: nur die E-Mail; in der Datenbank bleibt nichts vom Inhalt, nur ein
 *            Zustellprotokoll in inbox_log (Zeit, Tabelle, Empfänger als Hash, Status, Message-ID – keine personenbezogenen Daten).
 *            Scheitert der Versand (kein Empfänger, kein TLS, Zertifikat abgelaufen, SMTP-Fehler, Testumgebung ohne Zustellung), wird
 *            die Anfrage verschlüsselt gesichert (Rückfall) und die Administration gewarnt – eine Anfrage geht nie still verloren.
 *
 * E-Mail: HTML + Text, alle Felder mit Beschriftung in Formular-Reihenfolge, Eingangszeit, Formular, Vorgangsnummer; Dateien als
 * Anhang (Gesamtgrenze attach_mb, darüber ein Hinweis); optional JSON/XML zum Import; Betreff mit Platzhaltern (Standard ohne Inhalte);
 * Reply-To = E-Mail-Feld der Anfrage (abschaltbar). Transport: SMTP nur mit TLS (require_tls), ohne TLS kein Versand von Inhalten.
 * Ende-zu-Ende: S/MIME mit dem Zertifikat der Praxis (PEM) – die ganze Nachricht inkl. Anhängen (Mailer 'smime'); nur Kopfzeilen
 * (Betreff, Absender, Empfänger) bleiben lesbar. PGP: bewusst nicht eingebaut (keine gepflegte MIT-kompatible PHP-Bibliothek ohne
 * externes gpg-Programm).
 *
 * Erweiterungen, die einen Eingang verantworten (Extension::inbox, z. B. Buchungen), brauchen die gespeicherten Einträge
 * (Belegung, Status, Storno) – für sie gibt es „nur per E-Mail“ nicht, „System und E-Mail“ schon.
 */
final class Delivery
{
    public const MODES = ['system', 'both', 'mail'];
    public const MACHINE = ['', 'json', 'xml'];
    public const DEFAULTS = ['mode' => 'system', 'to' => '', 'route_field' => '', 'routes' => [], 'subject' => '', 'reply_to' => true,
        'machine' => '', 'smime' => '', 'attach_mb' => 10];
    /** Standard-Betreff – bewusst ohne Inhalte der Anfrage */
    public const SUBJECT = '[{form}] {ref}';
    /** Hinweise an die Administration (fehlgeschlagene Zustellungen, ohne Inhalte) */
    public const ALERTS = 'sys.inbox_delivery_alerts';
    private const MAX_TO = 10;

    /** Funktion „requests.mail“ (und „requests“) eingeschaltet? */
    public static function available(): bool
    {
        return Inbox::available() && Features::on('requests.mail', false);
    }

    public static function config(array $t): array
    {
        return (array) ($t['settings']['inbox']['delivery'] ?? []) + self::DEFAULTS;
    }

    /** Verantwortet eine Erweiterung den Eingang (z. B. Buchungen)? Dann kein Modus „nur per E-Mail“. */
    public static function managed(array $t): bool
    {
        return Inbox::ext($t) !== [];
    }

    /** Wirksamer Modus (Funktion aus → system; „mail“ bei Eingängen einer Erweiterung → both) */
    public static function mode(array $t): string
    {
        if (!Inbox::is($t) || !self::available()) return 'system';
        $m = (string) self::config($t)['mode'];
        if (!in_array($m, self::MODES, true)) return 'system';
        return $m === 'mail' && self::managed($t) ? 'both' : $m;
    }

    /** Stellt die Tabelle Inhalte per E-Mail zu? */
    public static function mails(array $t): bool
    {
        return self::mode($t) !== 'system';
    }

    public static function modeLabel(string $m): string
    {
        return match ($m) {
            'both' => __('Im System und per E-Mail'),
            'mail' => __('Nur per E-Mail – nicht im System speichern'),
            default => __('Im System (verschlüsselt)'),
        };
    }

    // ================================================================= Einstellungen (Tables::validate)

    /**
     * settings.inbox.delivery prüfen. $fields: Felder der Tabelle (Weiterleitung nach Auswahlfeld), $table: bestehende Tabelle.
     * Nur wer Anfragen verwalten darf (requests.manage), ändert die Zustellung – sonst bleibt sie unverändert.
     */
    public static function validateSettings(array $s, array $fields, array &$errors, ?array $existing, ?array $table): array
    {
        $existing = ($existing ?? []) + self::DEFAULTS;
        if (!$s) return $existing;
        if ($table && isset(app()->auth) && app()->auth->user() && !app()->auth->can('requests.manage', $table['handle'])) return $existing;
        $key = 'settings.delivery';
        $mode = in_array($s['mode'] ?? null, self::MODES, true) ? (string) $s['mode'] : (string) $existing['mode'];
        if ($mode === 'mail' && $table && self::managed($table)) {
            $errors[$key] = __('Diesen Eingang verwaltet eine Erweiterung (z. B. Buchungen), die gespeicherte Einträge braucht – „Nur per E-Mail“ ist hier nicht möglich. Bitte „Im System und per E-Mail“ wählen.');
            $mode = $existing['mode'] === 'mail' ? 'both' : (string) $existing['mode'];
        }
        $to = self::cleanList((string) ($s['to'] ?? $existing['to']), $errors, $key);
        $selects = [];
        foreach ($fields as $f) if (($f['type'] ?? '') === 'select') $selects[$f['name']] = (array) ($f['options'] ?? []);
        $rf = (string) ($s['route_field'] ?? $existing['route_field']);
        if (!isset($selects[$rf])) $rf = '';
        $routes = [];
        if ($rf !== '') {
            foreach ((array) ($s['routes'] ?? $existing['routes']) as $k => $v) {
                if (!array_key_exists((string) $k, $selects[$rf])) continue;
                $list = self::cleanList((string) $v, $errors, $key);
                if ($list !== '') $routes[(string) $k] = $list;
            }
        }
        $pem = (string) $existing['smime'];
        $new = trim((string) ($s['smime'] ?? ''));
        if ($new !== '') {
            [$norm, $err] = self::normalizeCerts($new);
            if ($err !== null) $errors['settings.delivery.smime'] = $err;
            else $pem = $norm;
        } elseif (!empty($s['smime_remove'])) {
            $pem = '';
        }
        return [
            'mode' => $mode,
            'to' => $to,
            'route_field' => $rf,
            'routes' => $routes,
            'subject' => mb_substr(trim((string) preg_replace('~[\r\n\t]+~', ' ', strip_tags((string) ($s['subject'] ?? $existing['subject'])))), 0, 200),
            'reply_to' => array_key_exists('reply_to', $s) ? !empty($s['reply_to']) : (bool) $existing['reply_to'],
            'machine' => in_array($s['machine'] ?? $existing['machine'], self::MACHINE, true) ? (string) ($s['machine'] ?? $existing['machine']) : '',
            'smime' => $pem,
            'attach_mb' => max(1, min(25, (int) ($s['attach_mb'] ?? $existing['attach_mb']))),
        ];
    }

    /** Adressliste („a@x.de, b@y.de“) prüfen und normalisieren */
    private static function cleanList(string $raw, array &$errors, string $key): string
    {
        $mails = array_values(array_unique(array_filter(array_map('trim', preg_split('~[,;\s]+~', $raw) ?: []))));
        foreach ($mails as $m) {
            if (!filter_var($m, FILTER_VALIDATE_EMAIL)) $errors[$key] = __('Zustellung: „{mail}“ ist keine gültige E-Mail-Adresse.', ['mail' => $m]);
        }
        $mails = array_filter($mails, fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL));
        if (count($mails) > self::MAX_TO) $errors[$key] = __('Zustellung: höchstens {n} Empfänger.', ['n' => self::MAX_TO]);
        return implode(', ', array_slice($mails, 0, self::MAX_TO));
    }

    public static function split(string $list): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $list)), fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL)));
    }

    // ================================================================= S/MIME-Zertifikate

    /**
     * Eingefügte Zertifikate (PEM, ein oder mehrere Blöcke) prüfen und normalisieren.
     * @return array{0: string, 1: ?string} [PEM, Fehler]
     */
    public static function normalizeCerts(string $pem): array
    {
        if (preg_match('~PRIVATE KEY~', $pem)) {
            return ['', __('Bitte nur das Zertifikat (öffentlicher Teil) einfügen – der private Schlüssel gehört nicht auf den Server und wurde nicht gespeichert.')];
        }
        preg_match_all('~-----BEGIN CERTIFICATE-----\s*([A-Za-z0-9+/=\s]+?)\s*-----END CERTIFICATE-----~', $pem, $m);
        if (!$m[0]) return ['', __('Kein Zertifikat erkannt – bitte den öffentlichen Teil im PEM-Format einfügen („-----BEGIN CERTIFICATE-----“ …).')];
        if (count($m[0]) > 5) return ['', __('Höchstens 5 Zertifikate.')];
        $out = [];
        foreach ($m[1] as $b64) {
            $der = base64_decode((string) preg_replace('~\s+~', '', $b64), true);
            if ($der === false || $der === '') return ['', __('Das Zertifikat ist beschädigt (Base64).')];
            $c = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($der), 64, "\n") . '-----END CERTIFICATE-----';
            $i = self::certInfo($c);
            if (!$i) return ['', __('Das Zertifikat lässt sich nicht lesen (kein gültiges X.509-Zertifikat).')];
            if ($i['expired']) return ['', __('Das Zertifikat „{name}“ ist am {date} abgelaufen.', ['name' => $i['name'], 'date' => date('d.m.Y', $i['valid_to'])])];
            if ($i['not_yet']) return ['', __('Das Zertifikat „{name}“ gilt erst ab {date}.', ['name' => $i['name'], 'date' => date('d.m.Y', $i['valid_from'])])];
            if (!$i['usable']) return ['', __('Das Zertifikat „{name}“ eignet sich nicht zum Verschlüsseln ({why}).', ['name' => $i['name'], 'why' => $i['why']])];
            $out[] = $c;
        }
        return [implode("\n", $out), null];
    }

    /** Angaben zu einem Zertifikat (PEM) oder null */
    public static function certInfo(string $pem): ?array
    {
        $x = @openssl_x509_read($pem);
        if (!$x) return null;
        $p = openssl_x509_parse($x);
        if (!$p) return null;
        $key = openssl_pkey_get_public($x);
        $det = $key ? openssl_pkey_get_details($key) : null;
        $why = '';
        if (($det['type'] ?? -1) !== OPENSSL_KEYTYPE_RSA) $why = __('nur RSA-Schlüssel werden unterstützt');
        $ku = (string) ($p['extensions']['keyUsage'] ?? '');
        if ($why === '' && $ku !== '' && !str_contains($ku, 'Key Encipherment')) $why = __('Schlüsselverwendung ohne „Key Encipherment“');
        $eku = (string) ($p['extensions']['extendedKeyUsage'] ?? '');
        if ($why === '' && $eku !== '' && !str_contains($eku, 'E-mail Protection') && !str_contains($eku, 'Any Extended Key Usage')) $why = __('erweiterte Schlüsselverwendung ohne „E-Mail-Schutz“');
        $emails = [];
        if (!empty($p['subject']['emailAddress'])) $emails[] = (string) (is_array($p['subject']['emailAddress']) ? $p['subject']['emailAddress'][0] : $p['subject']['emailAddress']);
        foreach (explode(',', (string) ($p['extensions']['subjectAltName'] ?? '')) as $alt) {
            if (str_starts_with(trim($alt), 'email:')) $emails[] = substr(trim($alt), 6);
        }
        $cn = $p['subject']['CN'] ?? '';
        $icn = $p['issuer']['CN'] ?? ($p['issuer']['O'] ?? '');
        $to = (int) ($p['validTo_time_t'] ?? 0);
        $from = (int) ($p['validFrom_time_t'] ?? 0);
        return [
            'name' => (string) (is_array($cn) ? $cn[0] : $cn) ?: ($emails[0] ?? '?'),
            'emails' => array_values(array_unique($emails)),
            'issuer' => (string) (is_array($icn) ? $icn[0] : $icn),
            'self_signed' => ($p['subject'] ?? null) == ($p['issuer'] ?? null),
            'valid_from' => $from, 'valid_to' => $to,
            'expired' => $to < time(), 'not_yet' => $from > time(), 'days_left' => (int) floor(($to - time()) / 86400),
            'fingerprint' => strtoupper(implode(':', str_split((string) openssl_x509_fingerprint($x, 'sha256'), 2))),
            'usable' => $why === '', 'why' => $why,
        ];
    }

    /** Alle Zertifikate einer PEM-Kette */
    public static function certs(string $pem): array
    {
        preg_match_all('~-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----~s', $pem, $m);
        return array_map(fn($c) => self::certInfo($c), $m[0]);
    }

    // ================================================================= Transport

    /**
     * Ist der E-Mail-Versand für Inhalte sicher genug? level: ok | warn | error (error = keine Inhalte per E-Mail).
     * @return array{level: string, text: string}
     */
    public static function transport(?array $s = null): array
    {
        $st = app()->settings;
        $get = fn(string $k, mixed $d = null) => $s !== null ? ($s[$k] ?? $d) : $st->get($k, $d);
        if ($s === null && (Mailer::$testDump !== null || app()->config->get('mail_dump'))) {
            return ['level' => 'ok', 'text' => __('Lokale Testablage (mail_dump) – es wird nichts versendet.')];
        }
        if (!filter_var((string) $get('sys.mail_from', ''), FILTER_VALIDATE_EMAIL)) {
            return ['level' => 'error', 'text' => __('Keine gültige Absender-Adresse (Grundeinstellungen → E-Mail-Versand).')];
        }
        return match ((string) $get('sys.mail_transport', 'smtp')) {
            'null' => ['level' => 'error', 'text' => __('Der E-Mail-Versand ist deaktiviert (Grundeinstellungen → E-Mail-Versand).')],
            'sendmail', 'native' => ['level' => 'warn', 'text' => __('Versand über den Mailserver des Webhosters (sendmail) – ob die Weiterleitung per TLS verschlüsselt ist, hängt vom Server ab. Empfohlen: SMTP mit TLS.')],
            default => match (true) {
                (string) $get('sys.mail_host', '') === '' => ['level' => 'error', 'text' => __('Kein SMTP-Server eingetragen (Grundeinstellungen → E-Mail-Versand).')],
                (string) $get('sys.mail_encryption', 'tls') === 'none' => ['level' => 'error', 'text' => __('SMTP ohne Verschlüsselung – Inhalte werden so nicht per E-Mail versendet. Bitte TLS oder SSL einstellen.')],
                !$get('sys.mail_verify_peer', true) => ['level' => 'warn', 'text' => __('SMTP mit TLS, aber das Zertifikat des Servers wird nicht geprüft („Zertifikat prüfen“ ist aus).')],
                default => ['level' => 'ok', 'text' => (string) $get('sys.mail_encryption', 'tls') === 'ssl'
                    ? __('SMTP über SSL/TLS.') : __('SMTP mit STARTTLS – für Inhalte erzwungen (ohne TLS kein Versand).')],
            },
        };
    }

    /**
     * Hinweise zur Einrichtung (Verwaltung, Anfragen): [['level' => ok|warn|error, 'text' => …], …] – leer bei Modus „system“.
     */
    public static function problems(array $t): array
    {
        $c = self::config($t);
        if ($c['mode'] === 'system') return [];
        $out = [];
        if (!self::available()) {
            $out[] = ['level' => 'warn', 'text' => __('Die Funktion „Anfragen per E-Mail“ ist ausgeschaltet – Anfragen werden nur im System gespeichert.')];
            return $out;
        }
        $mode = self::mode($t);
        if (!self::split($c['to']) && !$c['routes']) {
            $out[] = ['level' => 'error', 'text' => $mode === 'mail'
                ? __('Kein Empfänger eingetragen – Anfragen werden bis dahin verschlüsselt im System gesichert (Rückfall) und die Administration gewarnt.')
                : __('Kein Empfänger eingetragen – Anfragen liegen nur im System.')];
        } elseif (!self::split($c['to'])) {
            $out[] = ['level' => 'warn', 'text' => __('Ohne Standard-Empfänger gehen Anfragen ohne passende Weiterleitung nicht per E-Mail hinaus.')];
        }
        $tr = self::transport();
        if ($tr['level'] !== 'ok') $out[] = $tr;
        if ($c['smime'] === '') {
            $out[] = ['level' => 'warn', 'text' => __('Inhalte werden per E-Mail übertragen. Für Gesundheitsdaten empfehlen wir S/MIME-Verschlüsselung mit dem Zertifikat der Empfänger – ohne sie ist die Nachricht nur auf dem Transportweg (TLS) geschützt und liegt unverschlüsselt im Postfach.')];
        } else {
            foreach (self::certs($c['smime']) as $i) {
                if (!$i || $i['expired']) $out[] = ['level' => 'error', 'text' => __('S/MIME-Zertifikat ungültig oder abgelaufen – es werden keine Inhalte per E-Mail versendet.')];
                elseif ($i['days_left'] < 30) $out[] = ['level' => 'warn', 'text' => __('S/MIME-Zertifikat „{name}“ läuft am {date} ab – bitte rechtzeitig ein neues hinterlegen.', ['name' => $i['name'], 'date' => date('d.m.Y', $i['valid_to'])])];
            }
        }
        if (!FormCrypto::ready()) {
            $out[] = ['level' => 'error', 'text' => __('Kein öffentlicher Schlüssel – ohne ihn nimmt das Formular nichts an (auch der Rückfall-Speicher braucht ihn).')];
        }
        return $out;
    }

    // ================================================================= Empfänger und Nachricht

    /** Empfänger: Weiterleitung nach Auswahlfeld (z. B. „Rezept“ → rezept@…), sonst die Standard-Empfänger */
    public static function recipients(array $t, array $values): array
    {
        $c = self::config($t);
        $v = $c['route_field'] !== '' ? ($values[$c['route_field']] ?? null) : null;
        if (is_scalar($v) && ($r = (string) ($c['routes'][(string) $v] ?? '')) !== '' && ($list = self::split($r))) return $list;
        return self::split($c['to']);
    }

    /** Dateien einer Einsendung zu groß für die E-Mail? (nur „nur per E-Mail“ lehnt ab – sonst bleiben sie im System) */
    public static function tooLarge(array $t, array $files): ?string
    {
        if (self::mode($t) !== 'mail' || !$files) return null;
        $mb = (int) self::config($t)['attach_mb'];
        $sum = array_sum(array_map(fn($f) => strlen((string) ($f['data'] ?? '')) ?: (int) ($f['size'] ?? 0), $files));
        return $sum > $mb * 1024 * 1024 ? lt('Die Dateien sind zusammen größer als {mb} MB. Bitte kleinere Dateien wählen.', ['mb' => $mb]) : null;
    }

    /** Dateien für den versiegelten Payload: [feld => ['file', 'type', 'size', 'sha256', 'data' (Base64)]] in $values */
    public static function sealFiles(array $values, array $files): array
    {
        foreach ($files as $n => $f) {
            $values[$n] = ['file' => (string) $f['name'], 'type' => (string) $f['type'], 'size' => strlen((string) $f['data']),
                'sha256' => hash('sha256', (string) $f['data']), 'data' => base64_encode((string) $f['data'])];
        }
        return $values;
    }

    /**
     * Inhalt der E-Mail. $files: [feld => ['name', 'type', 'data' (binär)]]; $at: Eingangszeit (Y-m-d H:i:s).
     * @return array{subject: string, text: string, html: string, attach: list<array>, reply_to: ?string, rows: list<array>, files: list<array>}
     */
    public static function message(array $t, array $values, array $files, string $ref, string $at, string $mode, bool $test = false): array
    {
        $c = self::config($t);
        $lang = Lang::default();
        $limit = (int) $c['attach_mb'] * 1024 * 1024;
        $used = 0;
        $rows = [];
        $fileInfo = [];
        $attach = [];
        $replyTo = null;
        foreach (DataForms::fields($t) as $f) {
            $n = $f['name'];
            if (!array_key_exists($n, $values) || $values[$n] === null) continue;   // ausgeblendetes Feld (Bedingung)
            $label = Tables::label($f, $lang);
            $v = $values[$n];
            if (in_array($f['type'], DataForms::UPLOAD_TYPES, true)) {
                if (!isset($files[$n])) continue;
                $file = $files[$n];
                $size = strlen((string) $file['data']);
                $ok = $used + $size <= $limit;
                if ($ok) {
                    $used += $size;
                    $attach[] = ['name' => self::fileName((string) $file['name']), 'data' => (string) $file['data'], 'type' => (string) $file['type']];
                }
                $note = $ok ? __('als Anhang') : ($mode === 'both' ? __('nicht angehängt (zu groß für die E-Mail) – liegt verschlüsselt im System') : __('nicht angehängt (zu groß für die E-Mail)'));
                $meta = ['file' => self::fileName((string) $file['name']), 'size' => $size];
                $fileInfo[] = ['field' => $n, 'name' => $meta['file'], 'type' => (string) $file['type'], 'size' => $size, 'sha256' => hash('sha256', (string) $file['data']), 'attached' => $ok];
                $rows[] = ['name' => $n, 'type' => $f['type'], 'label' => $label, 'value' => Inbox::fileLabel($meta) . ' – ' . $note, 'raw' => $meta['file']];
                continue;
            }
            if ($f['type'] === 'group' && is_array($v)) {
                $g = Inbox::groupTable($label, array_map(fn($sf) => [$sf['name'], Tables::label($sf, $lang), $sf], (array) ($f['fields'] ?? [])), $v);
                $rows[] = ['name' => $n, 'type' => 'group', 'label' => $label, 'value' => $g['value'] !== '' ? $g['value'] : '–', 'table' => $g['table'], 'raw' => $v];
                continue;
            }
            $shown = Inbox::display($f, $v);
            if ($f['type'] === 'email' && $replyTo === null && $c['reply_to'] && filter_var((string) $v, FILTER_VALIDATE_EMAIL)) $replyTo = (string) $v;
            $rows[] = ['name' => $n, 'type' => $f['type'], 'label' => $label, 'value' => trim($shown) !== '' ? $shown : '–', 'raw' => $v];
        }

        $form = Inbox::text($t, 'title', $lang) ?: (string) $t['name'];
        $ts = strtotime($at) ?: time();
        $vars = [
            '{form}' => $form, '{table}' => (string) $t['name'], '{ref}' => $ref, '{date}' => date('d.m.Y', $ts), '{time}' => date('H:i', $ts),
        ];
        foreach ($rows as $r) {
            if (!isset($r['table'])) $vars['{' . $r['name'] . '}'] = mb_substr(trim((string) preg_replace('~\s+~', ' ', (string) $r['value'])), 0, 60);
        }
        $subject = strtr((string) ($c['subject'] !== '' ? $c['subject'] : self::SUBJECT), $vars);
        $subject = trim((string) preg_replace(['~\{[a-z0-9_]+\}~', '~\s+~'], ['', ' '], $subject));
        if ($test) $subject = '[TEST] ' . $subject;

        $meta = [
            __('Formular') => $form,
            __('Vorgangsnummer') => $ref,
            __('Eingegangen') => date('d.m.Y H:i', $ts) . ' ' . __('Uhr'),
            __('Website') => site_url(),
            __('Zustellung') => self::modeLabel($mode) . ($c['smime'] !== '' ? ' · ' . __('S/MIME-verschlüsselt') : ''),
        ];
        if (Lang::multi() && Lang::current() !== $lang) $meta[__('Sprache der Anfrage')] = strtoupper(Lang::current());
        $footer = $mode === 'mail'
            ? __('Diese Anfrage ist nicht auf der Website gespeichert – diese E-Mail ist das einzige Exemplar. Bitte gemäß Ihren Aufbewahrungspflichten archivieren.')
            : __('Die Anfrage liegt zusätzlich verschlüsselt im Verwaltungsbereich (Anfragen) und wird dort nach der eingestellten Frist gelöscht.');
        if ($test) $footer = __('TESTNACHRICHT mit erfundenen Angaben („Testmail senden“ in der Verwaltung) – bitte nicht archivieren.') . ' ' . $footer;

        $text = $form . ' – ' . __('Vorgangsnummer') . ' ' . $ref . "\n" . str_repeat('=', 60) . "\n";
        foreach ($meta as $k => $v) $text .= $k . ': ' . $v . "\n";
        $text .= "\n";
        foreach ($rows as $r) {
            $val = (string) $r['value'];
            $text .= $r['label'] . ':' . (str_contains($val, "\n") ? "\n  " . str_replace("\n", "\n  ", $val) : ' ' . $val) . "\n";
        }
        $text .= "\n-- \n" . $footer . "\n";

        $machine = null;
        if ($c['machine'] !== '') {
            $doc = self::machine($t, $rows, $fileInfo, $ref, $ts, $form, $mode, $c['machine']);
            $machine = ['name' => 'anfrage-' . $ref . '.' . $c['machine'], 'data' => $doc, 'type' => $c['machine'] === 'json' ? 'application/json' : 'application/xml'];
            array_unshift($attach, $machine);
        }
        $html = \Core\Theme::capture(self::template(), ['subject' => $subject, 'form' => $form, 'ref' => $ref, 'meta' => $meta, 'rows' => $rows,
            'footer' => $footer, 'test' => $test, 'machine' => $machine['name'] ?? null]);
        return ['subject' => $subject, 'text' => $text, 'html' => $html, 'attach' => $attach, 'reply_to' => $replyTo, 'rows' => $rows, 'files' => $fileInfo];
    }

    /** Vorlage: Kit (kits/{kit}/templates/mail/request.php) vor Core (app/Admin/views/mail/request.php) */
    private static function template(): string
    {
        $kit = app()->theme->path . '/templates/mail/request.php';
        return is_file($kit) ? $kit : ROOT . '/app/Admin/views/mail/request.php';
    }

    private static function fileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('~[^\pL\pN._ -]+~u', '_', $name);
        return mb_substr(trim($name, '. ') ?: 'datei', 0, 120);
    }

    /** Maschinenlesbarer Anhang (JSON oder XML) – gleiche Angaben wie die E-Mail, Werte zusätzlich roh */
    private static function machine(array $t, array $rows, array $files, string $ref, int $ts, string $form, string $mode, string $fmt): string
    {
        $fields = array_map(fn($r) => ['name' => $r['name'], 'label' => $r['label'], 'type' => $r['type'],
            'value' => $r['raw'], 'display' => $r['value']], $rows);
        $doc = ['format' => 'klxm-studio-request', 'version' => 1, 'ref' => $ref, 'submitted_at' => date('c', $ts),
            'form' => $form, 'table' => ['handle' => $t['handle'], 'name' => $t['name']], 'site' => site_url(), 'delivery' => $mode,
            'lang' => Lang::current(), 'fields' => $fields, 'files' => array_map(fn($f) => array_diff_key($f, ['field' => 0]) + ['field' => $f['field']], $files)];
        if ($fmt === 'json') return (string) json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $x = new \DOMDocument('1.0', 'UTF-8');
        $x->formatOutput = true;
        $root = $x->createElement('request');
        $root->setAttribute('format', 'klxm-studio-request');
        $root->setAttribute('version', '1');
        $x->appendChild($root);
        $add = function (\DOMElement $p, string $name, string $val) use ($x): \DOMElement {
            $e = $x->createElement($name);
            $e->appendChild($x->createTextNode($val));
            $p->appendChild($e);
            return $e;
        };
        foreach (['ref' => $ref, 'submitted_at' => $doc['submitted_at'], 'form' => $form, 'site' => $doc['site'], 'delivery' => $mode, 'lang' => $doc['lang']] as $k => $v) $add($root, $k, (string) $v);
        $tbl = $add($root, 'table', (string) $t['name']);
        $tbl->setAttribute('handle', (string) $t['handle']);
        $fe = $x->createElement('fields');
        $root->appendChild($fe);
        foreach ($rows as $r) {
            $f = $x->createElement('field');
            $f->setAttribute('name', (string) $r['name']);
            $f->setAttribute('type', (string) $r['type']);
            $f->setAttribute('label', (string) $r['label']);
            if (isset($r['table']) && is_array($r['raw'])) {
                foreach (array_values(array_filter($r['raw'], 'is_array')) as $i => $row) {
                    $re = $x->createElement('row');
                    $re->setAttribute('n', (string) ($i + 1));
                    foreach ($row as $k => $v) {
                        if (!preg_match('~^[a-z_][a-z0-9_]*$~i', (string) $k)) continue;
                        $add($re, 'value', is_scalar($v) ? (string) $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE))->setAttribute('name', (string) $k);
                    }
                    $f->appendChild($re);
                }
            } else {
                $raw = $r['raw'];
                $add($f, 'value', is_array($raw) ? implode(', ', array_map('strval', $raw)) : (is_bool($raw) ? ($raw ? '1' : '0') : (string) $raw));
            }
            $add($f, 'display', (string) $r['value']);
            $fe->appendChild($f);
        }
        $fl = $x->createElement('files');
        $root->appendChild($fl);
        foreach ($files as $f) {
            $e = $add($fl, 'file', (string) $f['name']);
            foreach (['field', 'type', 'size', 'sha256'] as $k) $e->setAttribute($k, (string) $f[$k]);
            $e->setAttribute('attached', $f['attached'] ? '1' : '0');
        }
        return (string) $x->saveXML();
    }

    // ================================================================= Versand

    /**
     * Inhalt per E-Mail zustellen (ohne Speichern, ohne Protokoll).
     * @return array{ok: bool, error: ?string, message_id: ?string, to: list<string>, smime: bool}
     */
    public static function send(array $t, array $values, array $files, string $ref, ?string $mode = null, bool $test = false, ?string $at = null): array
    {
        $mode ??= self::mode($t);
        $c = self::config($t);
        $to = self::recipients($t, $values);
        $fail = fn(string $e) => ['ok' => false, 'error' => $e, 'message_id' => null, 'to' => $to, 'smime' => $c['smime'] !== ''];
        if (!$to) return $fail(__('Kein Empfänger eingetragen.'));
        $tr = self::transport();
        if ($tr['level'] === 'error') return $fail($tr['text']);
        if ($c['smime'] !== '') {
            foreach (self::certs($c['smime']) as $i) {
                if (!$i || $i['expired'] || !$i['usable']) return $fail(__('S/MIME-Zertifikat ungültig oder abgelaufen – kein Versand im Klartext.'));
            }
        }
        try {
            $m = self::message($t, $values, $files, $ref, $at ?? now(), $mode, $test);
        } catch (\Throwable $e) {
            error_log('[inbox] Zustellung: Nachricht nicht erstellt: ' . $e->getMessage());
            return $fail(__('Die E-Mail konnte nicht erstellt werden.'));
        }
        $from = (string) app()->settings->get('sys.mail_from', '');
        $domain = str_contains($from, '@') ? substr($from, strrpos($from, '@') + 1) : 'localhost';
        $mid = bin2hex(random_bytes(8)) . '.' . strtolower(str_replace('-', '', $ref)) . '@' . ($domain !== '' ? $domain : 'localhost');
        $err = Mailer::send($m['subject'], $m['text'], $to, ['html' => $m['html'], 'attach' => $m['attach'], 'reply_to' => $m['reply_to'],
            'message_id' => $mid, 'smime' => $c['smime'] !== '' ? $c['smime'] : null, 'require_tls' => true]);
        if ($err === null && Mailer::$outcome === 'logged') {
            $err = __('Nicht zugestellt: Testumgebung ohne Weiterleitung (mail_redirect) bzw. Versand deaktiviert.');
        }
        return $err === null ? ['ok' => true, 'error' => null, 'message_id' => $mid, 'to' => $to, 'smime' => $c['smime'] !== ''] : $fail($err);
    }

    /**
     * Einsendung einer Eingangs-Tabelle annehmen (DataForms::submit): je nach Modus speichern und/oder per E-Mail zustellen.
     * $files: [feld => ['name', 'type', 'data']]; $store: Speicher-Callback einer Erweiterung (Rückgabe ['id', …] bzw. ['error']).
     * ok = mindestens eines hat geklappt (gespeichert oder zugestellt); sonst Fehlermeldung für den Besucher.
     * @return array{ok: bool, id: int, ref: string, stored: ?array, mailed: bool, error?: string, rejected?: bool}
     */
    public static function accept(array $t, array $values, array $files = [], ?callable $store = null): array
    {
        $mode = self::mode($t);
        if ($store && $mode === 'mail') $mode = 'both';
        $sealed = self::sealFiles($values, $files);
        $doStore = function (?string $ref = null, array $meta = []) use ($t, $values, $sealed, $store): array {
            if ($store) return (array) $store($values);
            return Inbox::store($t, $sealed, $ref, $meta);
        };
        if ($mode === 'system') {
            $stored = $doStore();
            if (!empty($stored['error'])) return ['ok' => false, 'id' => 0, 'ref' => '', 'stored' => $stored, 'mailed' => false, 'error' => (string) $stored['error'], 'rejected' => true];
            return ['ok' => true, 'id' => (int) ($stored['id'] ?? 0), 'ref' => (string) ($stored['ref'] ?? ''), 'stored' => $stored, 'mailed' => false];
        }

        if ($mode === 'both') {
            $stored = null;
            $storeErr = null;
            try {
                $stored = $doStore();
            } catch (\Throwable $e) {
                $storeErr = $e;
                error_log('[inbox] Speichern fehlgeschlagen (' . $t['handle'] . '): ' . $e->getMessage());
            }
            if ($stored && !empty($stored['error'])) {
                return ['ok' => false, 'id' => 0, 'ref' => '', 'stored' => $stored, 'mailed' => false, 'error' => (string) $stored['error'], 'rejected' => true];
            }
            $id = (int) ($stored['id'] ?? 0);
            $ref = (string) ($stored['ref'] ?? '');
            if ($ref === '' && $id) $ref = (string) (Inbox::find($t, $id)['ref'] ?? '');
            if ($ref === '') $ref = Inbox::newRef($t);
            $sent = self::send($t, $values, $files, $ref, 'both');
            self::log($t, $id ? [$id] : [], 'both', $sent, $ref, $sent['ok'] ? '' : ($stored ? 'im System' : 'NICHT gespeichert'));
            if (!$sent['ok']) self::alert($t, $ref, (string) $sent['error'], (bool) $stored);
            if (!$stored && !$sent['ok']) {
                return ['ok' => false, 'id' => 0, 'ref' => $ref, 'stored' => null, 'mailed' => false, 'error' => lt('Ihre Anfrage konnte nicht übermittelt werden. Bitte versuchen Sie es später erneut oder rufen Sie uns an.')];
            }
            return ['ok' => true, 'id' => $id, 'ref' => $ref, 'stored' => $stored, 'mailed' => $sent['ok']];
        }

        // Nur per E-Mail: Vorgangsnummer ohne Datenbankzeile, Versand; Rückfall = verschlüsselt sichern
        $ref = Inbox::newRef($t);
        $at = now();
        $sent = self::send($t, $values, $files, $ref, 'mail', false, $at);
        if ($sent['ok']) {
            self::log($t, [], 'mail', $sent, $ref);
            return ['ok' => true, 'id' => 0, 'ref' => $ref, 'stored' => null, 'mailed' => true];
        }
        try {
            $stored = Inbox::store($t, $sealed, $ref, ['delivery' => 'fallback']);
        } catch (\Throwable $e) {
            error_log('[inbox] Rückfall-Speicher fehlgeschlagen (' . $t['handle'] . '): ' . $e->getMessage());
            self::log($t, [], 'mail', $sent, $ref, 'NICHT gesichert');
            self::alert($t, $ref, (string) $sent['error'], false);
            return ['ok' => false, 'id' => 0, 'ref' => $ref, 'stored' => null, 'mailed' => false, 'error' => lt('Ihre Anfrage konnte nicht übermittelt werden. Bitte versuchen Sie es später erneut oder rufen Sie uns an.')];
        }
        self::log($t, [(int) $stored['id']], 'mail', $sent, $ref, 'verschlüsselt gesichert');
        self::alert($t, $ref, (string) $sent['error'], true);
        return ['ok' => true, 'id' => (int) $stored['id'], 'ref' => $ref, 'stored' => $stored, 'mailed' => false];
    }

    // ================================================================= Protokoll und Warnungen (nie Inhalte)

    /** Empfänger als Hash (HMAC mit dem App-Schlüssel, 12 Zeichen) – gleiche Adressen ergeben denselben Wert */
    public static function recipientHash(array $to): string
    {
        $to = array_map('strtolower', $to);
        sort($to);
        return substr(hash_hmac('sha256', implode(',', $to), app()->key()), 0, 12);
    }

    /** Adressen aus Fehlermeldungen entfernen (SMTP-Antworten nennen oft den Empfänger) */
    private static function scrub(string $s): string
    {
        return mb_substr(trim((string) preg_replace(['~[^\s<>"\'@]+@[^\s<>"\']+~', '~\s+~'], ['[Adresse]', ' '], $s)), 0, 160);
    }

    /** Zustellprotokoll in inbox_log (action „mail“): Modus, Status, Empfänger-Hash, Vorgangsnummer, Message-ID – ohne IP und Inhalte */
    public static function log(array $t, array $ids, string $mode, array $sent, string $ref, string $fallback = ''): void
    {
        $status = $sent['ok'] ? 'gesendet' : 'fehlgeschlagen' . ($fallback !== '' ? ' → ' . $fallback : '');
        $detail = $mode . ' · ' . $status . ' · ' . $ref . ' · an ' . self::recipientHash((array) $sent['to']) . ($sent['smime'] ? ' · S/MIME' : '')
            . ($sent['message_id'] ? ' · <' . $sent['message_id'] . '>' : ($sent['error'] ? ' · ' . self::scrub((string) $sent['error']) : ''));
        $user = isset(app()->auth) ? app()->auth->user() : null;
        try {
            app()->db->insert('inbox_log', ['table_handle' => $t['handle'], 'entry_ids' => json_encode(array_values(array_map('intval', $ids))),
                'user_id' => $user['id'] ?? null, 'action' => 'mail', 'detail' => mb_substr($detail, 0, 190), 'ip_hash' => null, 'created_at' => now()]);
        } catch (\Throwable $e) {
            error_log('[inbox] Zustellprotokoll: ' . $e->getMessage());
        }
    }

    /**
     * Zustellung fehlgeschlagen: Hinweis in der Verwaltung (Anfragen, Tabelle), Fehlerprotokoll und – falls möglich – eine
     * inhaltsfreie E-Mail an die Benachrichtigungsadresse der Tabelle bzw. die Empfänger aus den Grundeinstellungen.
     */
    public static function alert(array $t, string $ref, string $reason, bool $stored): void
    {
        $reason = self::scrub($reason);
        try {
            $list = (array) (app()->settings->get(self::ALERTS) ?: []);
            array_unshift($list, ['at' => now(), 'table' => $t['handle'], 'ref' => $ref, 'reason' => $reason, 'stored' => $stored]);
            app()->settings->set(self::ALERTS, array_slice($list, 0, 20));
        } catch (\Throwable $e) {
            error_log('[inbox] Hinweis nicht gespeichert: ' . $e->getMessage());
        }
        error_log('[inbox] Zustellung per E-Mail fehlgeschlagen: ' . $t['handle'] . ' ' . $ref . ' – ' . $reason . ($stored ? ' (verschlüsselt gesichert)' : ' (NICHT gesichert)'));
        $notify = (string) ($t['settings']['form']['notify'] ?? '');
        $to = $notify !== '' ? array_map('trim', explode(',', $notify)) : null;
        $subject = $stored ? __('Zustellung fehlgeschlagen – Anfrage verschlüsselt gesichert') : __('Zustellung fehlgeschlagen – Anfrage NICHT gesichert');
        Mailer::send($subject . ': ' . $t['name'],
            __('Guten Tag,') . "\n\n" . __('eine Anfrage in „{table}“ (Vorgangsnummer {ref}) konnte nicht per E-Mail zugestellt werden.', ['table' => $t['name'], 'ref' => $ref]) . "\n"
            . __('Grund: {reason}', ['reason' => $reason]) . "\n\n"
            . ($stored ? __('Die Anfrage wurde stattdessen verschlüsselt im System gesichert und ist unter „Anfragen“ mit dem Schlüssel lesbar:') . "\n" . absolute_url('/admin/requests?table=' . $t['handle'])
                : __('Die Anfrage konnte auch nicht gesichert werden. Bitte die Einstellungen prüfen:') . "\n" . absolute_url('/admin/data/' . $t['handle'] . '/schema'))
            . "\n\n" . __('Diese E-Mail enthält aus Datenschutzgründen keine Inhalte.') . "\n", $to);
    }

    /** Offene Hinweise (optional nur für bestimmte Tabellen) */
    public static function alerts(?array $handles = null): array
    {
        $list = array_values(array_filter((array) (app()->settings->get(self::ALERTS) ?: []), 'is_array'));
        return $handles === null ? $list : array_values(array_filter($list, fn($a) => in_array($a['table'] ?? '', $handles, true)));
    }

    public static function clearAlerts(array $handles): void
    {
        app()->settings->set(self::ALERTS, array_values(array_filter(self::alerts(), fn($a) => !in_array($a['table'] ?? '', $handles, true))));
    }

    // ================================================================= Testmail

    /** Erfundene Anfrage für „Testmail senden“ */
    public static function dummy(array $t): array
    {
        $v = [];
        $val = function (array $f) {
            $n = $f['name'];
            return match ($f['type']) {
                'email' => 'erika.mustermann@example.com',
                'tel' => '+49 2841 000000',
                'date' => date('Y-m-d'),
                'datetime' => date('Y-m-d H:i'),
                'time' => '10:00',
                'number' => 1,
                'bool' => true,
                'color' => '#336699',
                'iban' => 'DE89370400440532013000',
                'textarea' => 'Dies ist eine Testanfrage mit erfundenen Angaben.',
                'select' => (string) (array_key_first((array) ($f['options'] ?? [])) ?? ''),
                'multiselect' => array_slice(array_map('strval', array_keys((array) ($f['options'] ?? []))), 0, 1),
                default => match (true) {
                    in_array($n, ['vorname', 'first_name'], true) => 'Erika',
                    in_array($n, ['nachname', 'last_name'], true) => 'Mustermann',
                    in_array($n, ['name', 'vollstaendiger_name', 'full_name'], true) => 'Erika Mustermann',
                    default => 'Beispiel',
                },
            };
        };
        foreach (DataForms::fields($t) as $f) {
            if (in_array($f['type'], DataForms::UPLOAD_TYPES, true)) continue;
            if ($f['type'] === 'group') {
                $row = [];
                foreach ((array) ($f['fields'] ?? []) as $sf) $row[$sf['name']] = $val($sf);
                $v[$f['name']] = [$row];
                continue;
            }
            $v[$f['name']] = $val($f);
        }
        return $v;
    }

    /** Testmail mit erfundener Anfrage an die gespeicherten Empfänger (inkl. S/MIME, Anhang-Beispiel bei Dateifeldern) */
    public static function test(array $t): array
    {
        $values = self::dummy($t);
        $files = [];
        foreach (DataForms::fields($t) as $f) {
            if (!in_array($f['type'], DataForms::UPLOAD_TYPES, true)) continue;
            $files[$f['name']] = ['name' => 'beispiel.pdf', 'type' => 'application/pdf', 'data' => self::samplePdf()];
            $values[$f['name']] = 0;
        }
        $mode = self::mode($t) === 'system' ? 'both' : self::mode($t);
        $ref = 'TEST-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $sent = self::send($t, $values, $files, $ref, $mode, true);
        self::log($t, [], 'test', $sent, $ref);
        return $sent;
    }

    /** Kleinste gültige PDF-Datei (eine leere Seite) als Beispielanhang */
    private static function samplePdf(): string
    {
        $objs = ["<< /Type /Catalog /Pages 2 0 R >>", "<< /Type /Pages /Kids [3 0 R] /Count 1 >>", "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>"];
        $pdf = "%PDF-1.4\n";
        $off = [];
        foreach ($objs as $i => $o) {
            $off[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n";
        }
        $x = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
        return $pdf . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$x\n%%EOF\n";
    }
}
