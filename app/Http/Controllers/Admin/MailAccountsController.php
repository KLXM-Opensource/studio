<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Extensions;
use Core\Fields;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\Accounts;
use Core\Mail\Purposes;
use Core\Mailer;
use Core\Network\Network;

/**
 * Grundeinstellungen → E-Mail-Konten (/admin/system/mail): weitere Konten der Website, Verwendung je Zweck, Testnachricht je
 * Konto; auf der Netzwerk-Website für die Netzwerk-Administration zusätzlich das Netzwerk-Konto (Core\Mail\Accounts).
 * Das Konto „Standard“ bleibt in den Grundeinstellungen (Reiter E-Mail-Versand).
 */
final class MailAccountsController extends AdminController
{
    private const BASE = '/admin/system/mail';

    /** Felder eines Kontos (Core\Fields, show_if je Versandart) */
    public static function fields(bool $network = false): array
    {
        $tr = ['smtp' => __('SMTP-Server'), 'dsn' => __('Dienst per DSN (z. B. Mailjet, Mailchimp Transactional, Brevo, Postmark)'), 'null' => __('Deaktiviert')];
        foreach (Extensions::mailTransports() as $k => $t) $tr['ext:' . $k] = $t['label'];
        $smtp = ['transport' => 'smtp'];
        return [
            ...($network ? [] : [['name' => 'label', 'label' => __('Name des Kontos'), 'type' => 'text', 'required' => true, 'max' => 80, 'placeholder' => __('z. B. Newsletter')]]),
            ['name' => 'transport', 'label' => __('Versandart'), 'type' => 'select', 'required' => true, 'options' => $tr, 'default' => 'smtp'],
            ['name' => 'host', 'label' => __('SMTP-Server'), 'type' => 'text', 'width' => 'half', 'show_if' => $smtp, 'placeholder' => 'mail.example.org'],
            ['name' => 'port', 'label' => __('Port'), 'type' => 'number', 'width' => 'half', 'show_if' => $smtp, 'placeholder' => '587'],
            ['name' => 'user', 'label' => __('Benutzername'), 'type' => 'text', 'width' => 'half', 'show_if' => $smtp],
            ['name' => 'pass', 'label' => __('Passwort'), 'type' => 'secret', 'width' => 'half', 'show_if' => $smtp, 'help' => __('Leer lassen = unverändert.')],
            ['name' => 'encryption', 'label' => __('Verschlüsselung'), 'type' => 'select', 'width' => 'half', 'show_if' => $smtp, 'default' => 'tls',
                'options' => ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => __('keine')]],
            ['name' => 'verify_peer', 'label' => __('TLS-Zertifikat prüfen'), 'type' => 'bool', 'width' => 'half', 'show_if' => $smtp, 'default' => true],
            ['name' => 'dsn', 'label' => 'DSN', 'type' => 'secret', 'show_if' => ['transport' => 'dsn'],
                'help' => __('Zugang zum Dienst als Symfony-DSN, z. B. „mailjet+api://SCHLÜSSEL:GEHEIMNIS@default“ oder „mandrill+api://SCHLÜSSEL@default“ (Mailchimp Transactional). Jeder Dienst braucht sein Paket (z. B. symfony/mailjet-mailer) – fehlt es, nennt die Testnachricht den Namen. Verschlüsselt gespeichert; leer lassen = unverändert.')],
            ['name' => 'from', 'label' => __('Absender-Adresse'), 'type' => 'email', 'required' => true, 'width' => 'half', 'placeholder' => $network ? 'studio@example.org' : 'news@example.org'],
            ['name' => 'from_name', 'label' => __('Absender-Name'), 'type' => 'text', 'width' => 'half', 'max' => 120,
                'help' => $network ? __('Neutral halten (z. B. Name des Systems) – Netzwerk-Mails gehen an alle Kunden.') : ''],
            ['name' => 'reply_to', 'label' => __('Antworten an (optional)'), 'type' => 'email', 'width' => 'half'],
        ];
    }

    private function guard(Request $r): array
    {
        return $this->auth($r, 'system.manage');
    }

    /** Netzwerk-Konto bearbeiten: Netzwerk-Administration auf der Netzwerk-Website */
    private static function canNetwork(array $user): bool
    {
        try {
            return Network::isNetworkSite() && Network::isNetworkUser($user);
        } catch (\Throwable) {
            return false;
        }
    }

    public function index(Request $r, array $errors = [], ?array $draft = null, string $draftId = ''): Response
    {
        $u = $this->guard($r);
        if ($draftId === '') $draftId = isset($r->query['neu']) ? 'neu' : (string) ($r->query['konto'] ?? '');   // Links aus den Grundeinstellungen
        return $this->view('system/mail-accounts', [
            'accounts' => Accounts::all(), 'extra' => Accounts::extra(), 'routes' => Accounts::routes(), 'purposes' => Purposes::all(),
            'network' => Accounts::network(), 'canNetwork' => self::canNetwork($u), 'fields' => self::fields(), 'netFields' => self::fields(true),
            'stdFields' => array_values(array_filter(self::fields(true), fn($f) => $f['name'] !== 'dsn' && $f['name'] !== 'reply_to')),
            'errors' => $errors, 'draft' => $draft, 'draftId' => $draftId, 'me' => $u,
        ], $errors ? 422 : 200);
    }

    /** Konto anlegen bzw. ändern (id leer = neu) */
    public function save(Request $r): Response
    {
        $this->guard($r);
        $id = (string) ($r->post['id'] ?? '');
        if ($id === 'standard') {   // Konto „Standard“ = Grundeinstellungen sys.mail_* (ohne Namen, Versandart SMTP oder deaktiviert)
            $fields = array_values(array_filter(self::fields(true), fn($f) => $f['name'] !== 'dsn' && $f['name'] !== 'reply_to'));
            [$v, $errors] = Fields::sanitize($fields, (array) ($r->post['f'] ?? []), Accounts::standard());
            if ($errors) return $this->index($r, $errors, $v, 'standard');
            Accounts::saveStandard($v + Accounts::standard());
            return $this->back(self::BASE . '#konto-standard', 'success', __('Konto „{name}“ gespeichert.', ['name' => __('Standard')]));
        }
        $all = Accounts::extra();
        $cur = $id !== '' ? ($all[$id] ?? throw new HttpException(404)) : [];
        [$v, $errors] = Fields::sanitize(self::fields(), (array) ($r->post['f'] ?? []), $cur);
        if ($errors) return $this->index($r, $errors, $v, $id ?: 'neu');
        if ($id === '') {
            $base = trim((string) preg_replace('~[^a-z0-9]+~', '-', mb_strtolower((string) $v['label'])), '-') ?: 'konto';
            $id = substr($base, 0, 24);
            for ($n = 2; isset($all[$id]) || in_array($id, ['standard', 'network', 'neu'], true); $n++) $id = substr($base, 0, 20) . '-' . $n;
        }
        $all[$id] = ['id' => $id] + $v;
        Accounts::saveExtra(array_values($all));
        return $this->back(self::BASE . '#konto-' . $id, 'success', __('Konto „{name}“ gespeichert.', ['name' => $v['label']]));
    }

    public function delete(Request $r, string $id): Response
    {
        $this->guard($r);
        $all = Accounts::extra();
        if (!isset($all[$id])) throw new HttpException(404);
        $label = $all[$id]['label'];
        unset($all[$id]);
        Accounts::saveExtra(array_values($all));
        // Zwecke, die auf das Konto zeigten, fallen auf „Standard“ zurück
        $routes = array_filter(Accounts::routes(), fn($a) => $a !== $id);
        app()->settings->set('sys.mail_routes', $routes);
        return $this->back(self::BASE, 'success', __('Konto „{name}“ entfernt – seine Zwecke nutzen wieder „Standard“.', ['name' => $label]));
    }

    /** Verwendung: Zweck → Konto */
    public function routes(Request $r): Response
    {
        $this->guard($r);
        $all = Accounts::all();
        $out = [];
        foreach (Purposes::all() as $k => $_) {
            $v = (string) ($r->post['route'][$k] ?? 'standard');
            if ($v !== 'standard' && isset($all[$v])) $out[$k] = $v;
        }
        app()->settings->set('sys.mail_routes', $out);
        return $this->back(self::BASE . '#verwendung', 'success', __('Verwendung gespeichert.'));
    }

    /** Testnachricht über ein bestimmtes Konto an die eigene Adresse (bzw. eingegebene) */
    public function test(Request $r, string $id): Response
    {
        $u = $this->guard($r);
        $acc = Accounts::find($id) ?? throw new HttpException(404);
        [$type, $msg] = self::sendTest($acc, trim((string) ($r->post['to'] ?? '')) ?: (string) $u['email']);
        return $this->back(self::BASE . '#konto-' . $id, $type, $msg);
    }

    /** Testnachricht über das E-Mail-Konto des Netzwerks (Netzwerk-Dashboard) */
    public function networkTest(Request $r): Response
    {
        $u = $this->networkGuard($r);
        $acc = Accounts::network() ?? throw new HttpException(404);
        [$type, $msg] = self::sendTest($acc, trim((string) ($r->post['to'] ?? '')) ?: (string) $u['email']);
        return $this->back('/admin/network#email', $type, $msg);
    }

    /** @return array{0: string, 1: string} Meldungstyp und Text */
    private static function sendTest(array $acc, string $to): array
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return ['error', __('Ungültige Empfängeradresse.')];
        // Versandweg zuerst aufbauen – meldet z. B. ein fehlendes Paket für Mailjet auch in der Testumgebung
        try {
            Accounts::transport($acc);
        } catch (\Throwable $e) {
            return ['error', $e->getMessage()];
        }
        // Kurzzeitig nur dieses Konto für „Redaktion“ (Netzwerk-Konto über network: true)
        $routes = app()->settings->get('sys.mail_routes', []);
        if ($acc['id'] !== 'network') app()->settings->set('sys.mail_routes', ['notify' => $acc['id']] + (array) $routes);
        try {
            $err = Mailer::send(__('Testnachricht: Konto „{name}“', ['name' => $acc['label']]),
                __('Diese Testnachricht wurde über das E-Mail-Konto „{name}“ versendet ({how}).', ['name' => $acc['label'], 'how' => Accounts::describe($acc)]) . "\n\n" . absolute_url('/'),
                [$to], ['purpose' => 'notify', 'network' => $acc['id'] === 'network']);
        } finally {
            if ($acc['id'] !== 'network') app()->settings->set('sys.mail_routes', $routes);
        }
        if ($err) return ['error', $err];
        return match (Mailer::$outcome) {
            'sent' => ['success', __('Testnachricht an {to} übergeben.', ['to' => $to])],
            'redirected' => ['success', __('Testnachricht an die Test-Umleitung gesendet (statt an {to}).', ['to' => $to])],
            'dumped' => ['success', __('Testnachricht als Datei abgelegt (lokaler Test, mail_dump).')],
            'logged' => ['info', $acc['transport'] === 'null' ? __('Versand ist bei diesem Konto deaktiviert – nur protokolliert.')
                : __('Testumgebung: nicht versendet, nur protokolliert. Zum Testen eine Test-Umleitung eintragen (Grundeinstellungen → E-Mail-Versand). Der Versandweg ist eingerichtet.')],
            default => ['error', __('Nicht zugestellt.')],
        };
    }

    /** Netzwerk-Administration auf der Netzwerk-Website */
    private function networkGuard(Request $r): array
    {
        $u = $this->auth($r);
        if (!self::canNetwork($u)) throw new HttpException(403, __('Nur für die Netzwerk-Administration auf der Netzwerk-Website.'));
        return $u;
    }

    /** E-Mail-Konto des Netzwerks speichern bzw. entfernen (Netzwerk-Dashboard) */
    public function network(Request $r): Response
    {
        $this->networkGuard($r);
        if (!empty($r->post['remove'])) {
            Accounts::saveNetwork(null);
            return $this->back('/admin/network#email', 'success', __('Netzwerk-Konto entfernt – Netzwerk-Mails nutzen wieder das Konto der jeweiligen Website.'));
        }
        [$v, $errors] = Fields::sanitize(self::fields(true), (array) ($r->post['n'] ?? []), Accounts::network() ?? []);
        if ($errors) return $this->back('/admin/network#email', 'error', implode(' ', array_unique(array_map('strval', $errors))));
        Accounts::saveNetwork($v);
        return $this->back('/admin/network#email', 'success', __('Netzwerk-Konto gespeichert.'));
    }
}
