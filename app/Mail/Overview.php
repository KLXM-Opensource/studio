<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Mail;

use Core\Mailer;
use Core\Network\Network;
use Core\Network\Stats;
use Core\Sites;

/**
 * E-Mail-Übersicht „Welche Adresse gilt wofür“ (Grundeinstellungen → E-Mail-Versand → Übersicht, Netzwerk → E-Mail):
 * je Mailart der wirksame Absender (Konto, Herkunft: diese Website / Netzwerk / Rückfall), Empfänger mit Quelle, Antwortadresse,
 * Impressum und ob wirklich versendet wird (Test-Umleitung, Testumgebung, Ablage). Nur lesend – die Rangfolge kommt aus
 * Accounts::resolve() (dieselbe Regel wie beim Versand). Testversand je Mailart an die eigene Adresse über Core\Mailer.
 * Für das Netzwerk wertet sites() die Einstellungen aller Websites aus (ohne Geheimnisse zu entschlüsseln).
 */
final class Overview
{
    /**
     * Mailarten des Cores: Schlüssel → [Bezeichnung, Zweck (Core\Mail\Purposes), Netzwerk-Mail, Kontosicherheit, Empfänger-Art, Erklärung].
     * Empfänger-Art: person | network | notify | delivery | visitor | admins | reviewers | support | mention | list
     */
    public const TYPES = [
        'account' => ['Einladung, Passwort, E-Mail-Änderung', 'account', false, true, 'person', 'An Konten dieser Website. Geht immer an die Person selbst – auch bei aktiver Test-Umleitung.'],
        'network' => ['Mails an Netzwerk-Konten', 'account', true, true, 'network', 'Einladung, Passwort und E-Mail-Änderung der Netzwerk-Administration – auf jeder Website über das Netzwerk-Konto.'],
        'requests' => ['Neue Anfrage, neuer Eintrag', 'notify', false, false, 'notify', 'Hinweis an die Redaktion ohne Inhalte, mit Link in die Verwaltung. Auch „Zustellung fehlgeschlagen“.'],
        'delivery' => ['Zustellung von Anfragen per E-Mail', 'notify', false, false, 'delivery', 'Der Inhalt der Anfrage für Tabellen mit Zustellung per E-Mail – nur verschlüsselt (TLS), optional S/MIME.'],
        'receipt' => ['Eingangsbestätigung an Besucher', 'visitor', false, false, 'visitor', 'Bestätigung an die Adresse aus dem Formular, wenn die Tabelle sie eingeschaltet hat (höchstens 3 je Stunde und Adresse).'],
        'integrity' => ['Sicherheitshinweis der Integritätsprüfung', 'notify', false, false, 'admins', 'Unerwartete Änderungen an Dateien oder Datenbank – einmal je Fund.'],
        'review' => ['Änderungen warten auf Freigabe', 'notify', false, false, 'reviewers', 'Über API, MCP oder KI eingereichte Änderungen – höchstens alle 15 Minuten.'],
        'support' => ['Support-Meldungen', 'notify', false, false, 'support', 'Neue Meldungen und Antworten im Support.'],
        'chat' => ['Erwähnungen im Chat', 'notify', false, false, 'mention', 'Ungelesene Erwähnungen im Chat der Verwaltung.'],
        'tasks' => ['Aufgaben: Zuweisung und Erinnerung', 'notify', false, false, 'assignee', 'Neue Aufgabe für eine Person oder Rolle, Erinnerung an fällige Aufgaben – je Konto abschaltbar.'],
        'newsletter' => ['Newsletter und Massenversand', 'newsletter', false, false, 'list', 'Für Erweiterungen wie einen Newsletter – ohne Impressum gesperrt.'],
    ];

    /** Mailarten dieser Website (Core + Zwecke der Erweiterungen); Netzwerk-Mails nur in Netzwerken und nur für die Netzwerk-Administration */
    public static function types(bool $withNetwork): array
    {
        $out = [];
        foreach (self::TYPES as $k => [$label, $purpose, $net, $acc, $kind, $help]) {
            if ($net && (!$withNetwork || !Sites::multi())) continue;
            $out[$k] = ['key' => $k, 'label' => __($label), 'purpose' => $purpose, 'network' => $net, 'account' => $acc, 'kind' => $kind, 'help' => __($help)];
        }
        foreach (Purposes::all() as $pk => $p) {
            if ($p['owner'] === 'core') continue;
            $out[$pk] = ['key' => $pk, 'label' => $p['label'], 'purpose' => $pk, 'network' => false, 'account' => false, 'kind' => 'list',
                'help' => trim($p['help'] . ' · ' . __('Erweiterung {name}', ['name' => $p['owner']]), ' ·')];
        }
        return $out;
    }

    public static function type(string $key, bool $withNetwork): ?array
    {
        return self::types($withNetwork)[$key] ?? null;
    }

    /** Darf die angemeldete Person Netzwerk-Mails sehen und testen? (Netzwerk-Administration) */
    public static function networkViewer(): bool
    {
        try {
            return Sites::multi() && Network::isNetworkUser();
        } catch (\Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------ Diese Website

    /**
     * Zeilen der Übersicht für die aktuelle Website.
     * @return list<array{key: string, label: string, help: string, purpose: string, purpose_label: string, network: bool, account: bool,
     *   acc: array, origin: string, origin_text: string, scope_text: string, ready: bool, to: string, to_src: string, reply: string,
     *   imprint: array{level: string, text: string}, state: array{level: string, text: string}, edit_from: ?string, edit_to: ?string}>
     */
    public static function rows(bool $withNetwork, bool $canNetwork): array
    {
        $all = Accounts::all();
        $routes = Accounts::routes();
        $net = Accounts::network();
        $purposes = Purposes::all();
        $out = [];
        foreach (self::types($withNetwork) as $k => $t) {
            $r = Accounts::resolve($t['purpose'], $t['network'], $all, $routes, $net, $all['standard']);
            $a = $r['account'];
            [$to, $src, $editTo] = self::recipients($t['kind']);
            $isNet = ($a['scope'] ?? 'site') === 'network';
            $out[] = $t + [
                'purpose_label' => (string) ($purposes[$t['purpose']]['label'] ?? $t['purpose']),
                'acc' => $a, 'origin' => $r['origin'], 'ready' => Accounts::ready($a),
                'origin_text' => self::originText($r['origin'], $a, isset($routes[$t['purpose']])),
                'scope_text' => $isNet ? __('gilt für: alle Websites (Netzwerk)') : __('gilt für: nur diese Website'),
                'to' => $to, 'to_src' => $src,
                'reply' => $t['kind'] === 'delivery'
                    ? __('Adresse aus dem Formular (E-Mail-Feld), sonst: {addr}', ['addr' => $a['reply_to'] !== '' ? $a['reply_to'] : __('Absender')])
                    : ($a['reply_to'] !== '' ? $a['reply_to'] : __('– (Antworten gehen an den Absender)')),
                'imprint' => self::imprintState($t['network']),
                'state' => self::state($a, $t['account']),
                'edit_from' => $isNet ? ($canNetwork ? '/admin/network/email' : null) : '/admin/system/mail?konto=' . rawurlencode((string) $a['id']) . '#formular',
                'edit_to' => $editTo,
            ];
        }
        return $out;
    }

    /** Herkunft des Absenders in Worten */
    public static function originText(string $origin, array $a, bool $chosen): string
    {
        $name = (string) $a['label'];
        return match ($origin) {
            'network' => __('Netzwerk-Konto'),
            'fallback' => __('Rückfall auf das Netzwerk-Konto – das Konto dieser Website ist nicht versandbereit'),
            'missing' => __('Konto „{name}“ – das unter „Verwendung“ gewählte Konto gibt es nicht mehr', ['name' => $name]),
            'network-missing' => __('Konto „{name}“ dieser Website – kein Netzwerk-Konto eingerichtet', ['name' => $name]),
            default => ($a['scope'] ?? 'site') === 'network' ? __('Netzwerk-Konto (unter „Verwendung“ gewählt)')
                : ($chosen ? __('Konto „{name}“ (unter „Verwendung“ gewählt)', ['name' => $name]) : __('Konto „{name}“ (Standard)', ['name' => $name])),
        };
    }

    /** Wird wirklich versendet? ok | warn | error mit Text – wie Mailer::send (Ablage, Testumgebung, Test-Umleitung) */
    public static function state(array $a, bool $account, ?string $env = null, mixed $dump = null, ?array $testRedirect = null, ?array $cfgRedirect = null): array
    {
        $env ??= environment();
        $dump ??= Mailer::$testDump ?? app()->config->get('mail_dump');
        // Erst die Einrichtung (gilt auch live), dann die lokale Ablage
        if (!Accounts::ready($a)) {
            return $a['transport'] === 'null' ? ['level' => 'warn', 'text' => __('Versand deaktiviert – nur protokolliert.')]
                : ['level' => 'error', 'text' => __('Nicht eingerichtet – diese Mails gehen nicht hinaus.')];
        }
        if ($dump) return ['level' => 'warn', 'text' => __('Lokale Ablage als Datei (mail_dump) – es wird nichts versendet.')];
        $prod = $env === 'production';
        $test = $account ? [] : ($testRedirect ?? Mailer::testRedirect());
        if (!$prod || $test) {
            $redirect = $test ?: ($cfgRedirect ?? array_values(array_filter(array_map('trim', explode(',', (string) app()->config->get('mail_redirect', ''))))));
            if ($redirect) return ['level' => 'warn', 'text' => ($prod ? __('Test-Umleitung') : __('Testumgebung')) . ': ' . __('an {to} statt an die Empfänger', ['to' => implode(', ', $redirect)])];
            return ['level' => 'warn', 'text' => __('Testumgebung ({env}): wird nicht versendet, nur protokolliert.', ['env' => $env])];
        }
        return ['level' => 'ok', 'text' => __('wird versendet')];
    }

    /** Impressum dieser Mailart: aus dieser Website, aus der Netzwerk-Website oder fehlt */
    public static function imprintState(bool $network): array
    {
        $foreign = $network && Sites::multi() && !self::isNetworkSite();
        if (Imprint::missing($network)) return ['level' => 'error', 'text' => $foreign ? __('fehlt auf der Netzwerk-Website') : __('fehlt')];
        $imp = Imprint::get(null, $network);
        $what = $imp['body'] !== '' ? ($imp['url'] !== '' ? __('Angaben + Link') : __('Angaben')) : __('nur Link zur Impressum-Seite');
        return ['level' => 'ok', 'text' => ($foreign ? __('der Netzwerk-Website') : __('dieser Website')) . ' · ' . $what];
    }

    private static function isNetworkSite(): bool
    {
        try {
            return Network::isNetworkSite();
        } catch (\Throwable) {
            return true;
        }
    }

    /** @return array{0: string, 1: string, 2: ?string} Empfänger, Quelle, Link zum Ändern */
    private static function recipients(string $kind): array
    {
        $mailTo = implode(', ', array_filter(array_map('trim', explode(',', (string) app()->settings->get('sys.mail_to', '')))));
        $none = __('– niemand eingetragen');
        return match ($kind) {
            'person' => [__('die Person selbst'), __('Konto'), null],
            'network' => [__('die Netzwerk-Administration'), __('Netzwerk-Konten'), null],
            'notify' => self::notifyRecipients($mailTo, $none),
            'delivery' => self::deliveryRecipients(),
            'visitor' => [__('Adresse aus dem Formular'), __('Einstellung der Tabelle (Eingangsbestätigung)'), '/admin/data'],
            'admins' => [implode(', ', self::admins()) ?: $none, app()->settings->get('sys.integrity_mail', true)
                ? (\Core\Sites::multi() ? __('Administration dieser Website (Datenbank)') : __('alle Konten mit allen Rechten dieser Website'))
                : __('ausgeschaltet (Grundeinstellungen → Integrität)'), '/admin/system/integritaet'],
            'reviewers' => self::reviewRecipients($none),
            'support' => [__('Support-Team bzw. die meldende Person'), __('Support'), null],
            'mention' => [__('die erwähnte Person'), __('Chat'), null],
            'assignee' => [__('die zuständige Person bzw. die Personen der Rolle'), __('Aufgaben (Konto → Mitteilungen)'), '/admin/aufgaben'],
            default => [__('Verteiler der Erweiterung'), __('Erweiterung'), null],
        };
    }

    private static function notifyRecipients(string $mailTo, string $none): array
    {
        $own = [];
        foreach (self::tables() as $t) {
            $n = trim((string) ($t['settings']['form']['notify'] ?? ''));
            if ($n !== '') $own[] = $t['name'] . ': ' . $n;
        }
        $src = __('Grundeinstellungen → Empfänger für Benachrichtigungen');
        if ($own) $src .= ' · ' . __('Tabellen mit eigenem Empfänger gehen vor: {list}', ['list' => implode('; ', $own)]);
        return [$mailTo !== '' ? $mailTo : $none, $src, '/admin/system#mail'];
    }

    private static function deliveryRecipients(): array
    {
        $list = [];
        foreach (self::tables() as $t) {
            try {
                if (\Core\Data\Delivery::mode($t) === 'system') continue;
                $c = \Core\Data\Delivery::config($t);
            } catch (\Throwable) {
                continue;
            }
            $to = trim((string) $c['to']);
            $list[] = $t['name'] . ': ' . ($to !== '' ? $to : __('– niemand eingetragen')) . ($c['routes'] ? ' ' . __('(+ Weiterleitung je Feldwert)') : '');
        }
        return [$list ? implode('; ', $list) : __('– keine Tabelle stellt per E-Mail zu'), __('Zustellung der Tabelle'), '/admin/data'];
    }

    private static function reviewRecipients(string $none): array
    {
        $on = (bool) app()->settings->get('sys.review_notify', false);
        $to = [];
        try { $to = \Core\Review\Queue::reviewerEmails(); } catch (\Throwable) {}
        return [implode(', ', $to) ?: $none, $on ? __('alle, die freigeben dürfen') : __('ausgeschaltet (Eingereicht → Einstellungen)'), '/admin/ai/eingereicht'];
    }

    /** @return list<array> Datentabellen dieser Website (leer ohne Funktion „Daten“) */
    private static function tables(): array
    {
        try {
            return \Core\Data\Tables::all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<string> Empfänger der Integritäts-Hinweise dieser Website (wie Integrity::notify) */
    private static function admins(): array
    {
        if (\Core\Sites::multi()) return \Core\Integrity::recipients('db');
        $roles = array_keys(array_filter(\Core\Permissions::roles(), fn($r) => in_array('*', (array) $r['permissions'], true)));
        if (!$roles) return [];
        $in = implode(',', array_fill(0, count($roles), '?'));
        return array_values(array_map('strval', array_column(app()->db->fetchAll("SELECT email FROM users WHERE disabled = 0 AND role IN ($in) ORDER BY email", $roles), 'email')));
    }

    // ------------------------------------------------------------------ Testversand

    /**
     * Testnachricht einer Mailart an $to – mit denselben Optionen wie der echte Versand (Zweck, Netzwerk, Kontosicherheit, TLS),
     * also mit Absender, Antwortadresse und Impressum wie im Ernstfall. $real: Testumgebung umgehen (nur diese eine Adresse).
     * @return array{0: string, 1: string} Meldungstyp und Text
     */
    public static function test(array $t, string $to, bool $real = false): array
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return ['error', __('Ungültige Empfängeradresse.')];
        $acc = Accounts::route($t['purpose'], $t['network']);
        $text = __('Diese Testnachricht zeigt, wie die Mailart „{type}“ ankommt – mit Absender, Antwortadresse und Impressum wie im Ernstfall.', ['type' => $t['label']]) . "\n\n"
            . __('Konto: {how}', ['how' => Accounts::describe($acc)]) . "\n"
            . __('Gesendet aus der E-Mail-Übersicht von {site}.', ['site' => site()->label()]) . "\n\n" . absolute_url('/');
        $err = Mailer::send(__('Testnachricht: {type}', ['type' => $t['label']]), $text, [$to], ['purpose' => $t['purpose'], 'network' => $t['network'],
            'account' => $t['account'], 'require_tls' => $t['kind'] === 'delivery', 'force_real' => $real]);
        if ($err) return ['error', $err];
        return self::outcome($to, $acc);
    }

    /** Meldung zum Ergebnis des letzten Versands (Mailer::$outcome) – auch für die Testnachricht je Konto */
    public static function outcome(string $to, array $acc): array
    {
        return match (Mailer::$outcome) {
            'sent' => ['success', __('Testnachricht an {to} übergeben.', ['to' => $to])],
            'redirected' => ['success', __('Testnachricht an die Test-Umleitung gesendet (statt an {to}).', ['to' => $to])],
            'dumped' => ['success', __('Testnachricht als Datei abgelegt (lokaler Test, mail_dump).')],
            'logged' => ['info', $acc['transport'] === 'null' ? __('Versand ist bei diesem Konto deaktiviert – nur protokolliert.')
                : __('Testumgebung: nicht versendet, nur protokolliert. Zum Testen eine Test-Umleitung eintragen (Grundeinstellungen → E-Mail-Versand). Der Versandweg ist eingerichtet.')],
            default => ['error', __('Nicht zugestellt.')],
        };
    }

    // ------------------------------------------------------------------ Netzwerk: alle Websites

    /** Zwecke in der Netzwerk-Tabelle (Spalten) */
    public const NET_PURPOSES = ['account', 'notify', 'visitor', 'newsletter'];

    /**
     * Wirksame Absender aller Websites (Netzwerk → E-Mail) aus deren Einstellungen – Geheimnisse bleiben verschlüsselt
     * (für „versandbereit“ zählt nur, ob eine DSN eingetragen ist).
     * @return list<array{key: string, label: string, network_site: bool, error: string, env: string, purposes: array, netmail: ?array,
     *   mail_to: string, redirect: string, imprint: bool, state: array}>
     */
    public static function sites(): array
    {
        $net = Accounts::network();
        $netKey = Network::siteKey();
        $out = [];
        foreach (Sites::all() as $key => $cfg) {
            $key = (string) $key;
            $row = ['key' => $key, 'label' => (string) (Stats::cached($key)['label'] ?? (new \Core\Site($key, (array) $cfg))->label()), 'network_site' => $key === $netKey,
                'error' => '', 'env' => 'production', 'purposes' => [], 'netmail' => null, 'mail_to' => '', 'redirect' => '', 'imprint' => false, 'state' => []];
            try {
                $conf = Network::config($key);
                $row['env'] = (string) $conf->get('environment', 'production');
                $db = Stats::db($key);
                $set = self::rawSettings($db);
            } catch (\Throwable $e) {
                $row['error'] = __('Website noch nicht eingerichtet.');
                $out[] = $row;
                continue;
            }
            $std = self::standardOf($set);
            $all = ['standard' => $std] + self::extraOf((array) ($set['sys.mail_accounts'] ?? [])) + ($net ? ['network' => $net] : []);
            $routes = array_filter((array) ($set['sys.mail_routes'] ?? []), 'is_string');
            foreach (self::NET_PURPOSES as $p) {
                $r = Accounts::resolve($p, false, $all, $routes, $net, $std);
                $row['purposes'][$p] = ['acc' => $r['account'], 'origin' => $r['origin'], 'ready' => Accounts::ready($r['account'])];
            }
            $nr = Accounts::resolve('account', true, $all, $routes, $net, $std);
            $row['netmail'] = ['acc' => $nr['account'], 'origin' => $nr['origin'], 'ready' => Accounts::ready($nr['account'])];
            $row['mail_to'] = implode(', ', array_filter(array_map('trim', explode(',', (string) ($set['sys.mail_to'] ?? '')))));
            $redir = array_values(array_filter(array_map('trim', preg_split('~[,;\s]+~', (string) ($set['sys.mail_redirect'] ?? '')) ?: []), fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
            $row['redirect'] = implode(', ', $redir);
            $row['imprint'] = trim((string) ($set[Imprint::SETTING] ?? '')) !== '' || self::imprintPage($db, $set);
            $cfgRedirect = array_values(array_filter(array_map('trim', explode(',', (string) $conf->get('mail_redirect', '')))));
            $row['state'] = self::state($row['purposes']['notify']['acc'], false, $row['env'], $conf->get('mail_dump'), $redir, $cfgRedirect);
            $out[] = $row;
        }
        return $out;
    }

    private static function rawSettings(\Core\Database $db): array
    {
        $keys = array_merge(array_values(Accounts::STANDARD_KEYS), ['sys.mail_accounts', 'sys.mail_routes', 'sys.mail_to', 'sys.mail_redirect', Imprint::SETTING],
            \Core\Legal::KEYS['imprint']);
        $in = implode(',', array_fill(0, count($keys), '?'));
        $out = [];
        foreach ($db->fetchAll("SELECT skey, value_json FROM settings WHERE skey IN ($in)", $keys) as $r) $out[(string) $r['skey']] = json_decode((string) $r['value_json'], true);
        return $out;
    }

    /** Konto „Standard“ aus rohen Einstellungen (wie Accounts::standard) */
    private static function standardOf(array $set): array
    {
        $a = [];
        foreach (Accounts::STANDARD_KEYS as $k => $sk) if (isset($set[$sk])) $a[$k] = $set[$sk];
        $a += ['transport' => 'smtp', 'from_name' => 'Website'];
        if (!in_array($a['transport'], ['smtp', 'null'], true)) $a['transport'] = 'smtp';
        return ['id' => 'standard', 'label' => __('Standard')] + Accounts::normalize($a);
    }

    /** Weitere Konten aus rohen Einstellungen – Geheimnisse nicht entschlüsselt (DSN zählt als eingetragen, wenn vorhanden) */
    private static function extraOf(array $list): array
    {
        $out = [];
        foreach ($list as $a) {
            if (!is_array($a) || !preg_match('~^[a-z0-9-]{2,32}$~', (string) ($a['id'] ?? ''))) continue;
            $a['pass'] = '';
            $a['dsn'] = (string) ($a['dsn'] ?? '') !== '' ? 'set' : '';
            $out[(string) $a['id']] = Accounts::normalize($a);
        }
        return $out;
    }

    /** Veröffentlichte Impressum-Seite eingetragen? */
    private static function imprintPage(\Core\Database $db, array $set): bool
    {
        foreach (\Core\Legal::KEYS['imprint'] as $k) {
            $id = (int) ($set[$k] ?? 0);
            if ($id > 0 && (string) $db->fetchValue('SELECT status FROM pages WHERE id = ?', [$id]) === 'published') return true;
        }
        return false;
    }
}
