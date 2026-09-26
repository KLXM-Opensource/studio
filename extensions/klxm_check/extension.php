<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – KLXM Check. Copyright (C) 2026 KLXM Crossmedia GmbH and contributors (see LICENSE)
/**
 * Erweiterung „klxm_check“ (Paket klxm/studio-check): Domain-, Mail- und TLS-Analyse als Block für alle Kits –
 * SPF (mit Lookup-Zählung), DMARC (mit Freigabe externer Berichts-Empfänger), DKIM, MX/PTR/FCrDNS, STARTTLS, Hosting &
 * DNS (NS, SOA, CAA, DNSSEC, IPv6), Website (Weiterleitungen, HSTS, Sicherheits-Header, Cookies, HTTP/2, Kompression),
 * SSL/TLS-Checker für HTTPS/SMTP/IMAP/POP3 sowie SPF- und DMARC-Generator. Neubau von https://klxm.de/check/.
 *
 * Aktivieren je Website: Administration → Funktionen & Erweiterungen (Bestätigung des Sicherheitshinweises) oder per
 * Konfiguration 'extensions' => ['klxm_check']. Danach Block „KLXM Check“ auf einer Seite einfügen; optional eigenständige
 * Seite /check (Verwaltung → KLXM Check bzw. 'klxm_check' => ['page' => true]). Doku: README.md, Verwaltung → Hilfe (Handbuch/Technik „KLXM Check“).
 */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Klxm\\Check\\')) {
        $file = __DIR__ . '/src/' . substr($class, strlen('Klxm\\Check\\')) . '.php';
        if (is_file($file)) require $file;
    }
});

use Klxm\Check\AdminController;
use Klxm\Check\Check;
use Klxm\Check\Controller;
use Klxm\Check\SelfTest;

return [
    'name' => 'klxm_check',
    'label' => 'KLXM Check (Domain-, Mail- & TLS-Analyse)',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',
    'description' => 'Prüft SPF, DMARC, DKIM, Mailserver, Hosting, Website-Sicherheit und SSL/TLS einer Domain – als Block auf jeder Seite, mit SPF- und DMARC-Generator und Einsteiger-Anleitungen.',
    'author' => 'KLXM Crossmedia GmbH and contributors',
    'license' => 'MIT',
    'homepage' => 'https://github.com/KLXM/studio-check',
    'risk' => 'Der Server baut im Auftrag von Besuchern Verbindungen zu fremden Servern auf (DNS, HTTP/HTTPS, TLS auf den Ports 25, 443, 465, 587, 993, 995). Schutz: nur öffentliche Adressen, feste Ports, kurze Zeitlimits, Ratenbegrenzung je Besucher und Website. Manche Hoster untersagen solche Prüfungen oder sperren Port 25 – vorher die Bedingungen des Hosters prüfen.',
    'provides' => [
        'Block „KLXM Check“ für alle Kits (Gruppe „Werkzeuge“)',
        'Prüf-Schnittstelle /check/api/… (nur für das Werkzeug, mit Ratenbegrenzung)',
        'Menüpunkt „KLXM Check“ (Zustand, Zähler, Schalter für die eigenständige Seite /check – Standard aus)',
        'Kachel auf der Übersicht (anonyme Zähler), Prüfung unter „health“',
    ],
    'commands' => ['check:selftest', 'check:run'],
    'docs' => ['Handbuch: KLXM Check' => '/admin/hilfe#klxm-check', 'Technik: KLXM Check' => '/admin/hilfe/technik#klxm-check'],
    'requirements' => fn(): array => [
        __('KLXM Check: PHP-Erweiterung openssl') => extension_loaded('openssl'),
        __('KLXM Check: PHP-Erweiterung curl (Website-Prüfung)') => function_exists('curl_init') ? true : null,
        __('KLXM Check: dns_get_record verfügbar') => function_exists('dns_get_record'),
        __('KLXM Check: intl für internationale Domains (sonst eingebauter Punycode)') => function_exists('idn_to_ascii') ? true : null,
    ],
    'usage' => function (): ?string {
        $t = array_sum(Check::totals(30));
        return $t ? __('{n} Prüfungen in den letzten 30 Tagen', ['n' => $t]) : null;
    },
    'boot' => function (Core\Extension $x): void {
        Check::boot($x);
        $x->feature(Check::FEATURE, 'KLXM Check: Prüf-Werkzeug (Block, Schnittstelle)', [], true);
        $x->blocks(['klxm_check' => Check::blockDefinition()]);

        $x->routes(function (Core\Http\Router $r): void {
            $r->get('/check/api/{section}', [Controller::class, 'api']);
            $r->post('/check/api/{section}', [Controller::class, 'api']);
            $r->get(Check::path(), [Controller::class, 'page']);
            $r->get('/admin/klxm-check', [AdminController::class, 'index']);
            $r->post('/admin/klxm-check', [AdminController::class, 'save']);
        });
        $x->nav('/admin/klxm-check', 'KLXM Check', 'shield-check', 'system.manage', 'admin');

        $x->health(fn() => Check::health());
        $x->dashboard(function (array $user): array {
            if (!can('system.manage')) return [];
            $t = Check::totals(7);
            $n = array_sum($t);
            return ['tiles' => [[
                'key' => 'klxm-check', 'label' => __('KLXM Check'), 'value' => number_format($n, 0, ',', '.'),
                'text' => __('Prüfungen (7 Tage, anonym)'), 'href' => '/admin/klxm-check', 'icon' => 'shield-check',
            ]]];
        });
        $x->docs('manual', ['klxm-check' => ['title' => 'KLXM Check: Domain- & Mail-Prüfung auf der Website', 'file' => __DIR__ . '/docs/manual.php']]);
        $x->docs('technical', ['klxm-check' => ['title' => 'KLXM Check (Erweiterung: DNS, Mail, TLS)', 'file' => __DIR__ . '/docs/technical.php', 'part' => 2]]);

        $x->command('check:selftest', 'KLXM Check: Selbsttest (IP-Filter, Domain-Prüfung, SPF-/DMARC-Parser, Lookup-Zählung) [--online]', fn(array $a) => SelfTest::run($a));
        $x->command('check:run', 'KLXM Check: Prüfung auf der Kommandozeile – check:run <spf|dmarc|dkim|mail|hosting|web|tls> <domain> [--service=https] [--json]', fn(array $a) => SelfTest::cli($a));
    },
];
