<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
// Portions ported from FriendsOfREDAXO/consent_kit (MIT, © KLXM Crossmedia GmbH) – see extensions/consent_kit/THIRD-PARTY-NOTICES.md
/**
 * Erweiterung „consent_kit“: Einwilligungsverwaltung (Cookie-Hinweis) – Dienste mit Vorlagen, barrierefreie Web Component,
 * 2-Klick-Platzhalter, Google Consent Mode v2, Global Privacy Control, Conversions ohne Code, Protokoll ohne IP/User-Agent.
 * Aktivieren je Website: 'extensions' => ['consent_kit'] in config/sites/{key}.php bzw. config/config.local.php.
 * Abschalten ohne Entfernen: 'features' => ['consent' => false].
 * Doku: extensions/consent_kit/README.md und Verwaltung → Hilfe (Handbuch „Cookie-Einwilligung“, Technik „Consent-Kit“).
 */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'MyCms\\Consent\\')) {
        $file = __DIR__ . '/src/' . substr($class, strlen('MyCms\\Consent\\')) . '.php';
        if (is_file($file)) require $file;
    }
});

if (!function_exists('consent_settings_link')) {
    /**
     * Link „Cookie-Einstellungen“ zum erneuten Öffnen der Auswahl – z. B. im Fußbereich eines eigenen Themes.
     * Liefert '' solange auf dieser Website kein einwilligungspflichtiger Dienst aktiv ist (dann gibt es nichts einzustellen).
     */
    function consent_settings_link(string $label = '', string $class = ''): string
    {
        return class_exists(\MyCms\Consent\Consent::class) ? \MyCms\Consent\Consent::settingsLink($label, $class) : '';
    }
}

if (!function_exists('consent_embed')) {
    /**
     * Fremden Inhalt (iframe, Social-Media-Einbettung) erst nach Einwilligung in den Dienst $service laden (2-Klick-Platzhalter).
     * consent_embed('google_maps', '<iframe src="https://www.google.com/maps/embed?…" title="Anfahrt"></iframe>', ['title' => 'Anfahrt', 'ratio' => '4/3'])
     */
    function consent_embed(string $service, string $html, array $options = []): string
    {
        return class_exists(\MyCms\Consent\Consent::class) ? \MyCms\Consent\Consent::embed($service, $html, $options) : '';
    }
}

if (!function_exists('consent_has')) {
    /** Serverseitig: Einwilligung in den Dienst liegt im Cookie dieser Anfrage vor (nicht hinter dem Seiten-Cache verwenden). */
    function consent_has(string $service): bool
    {
        return class_exists(\MyCms\Consent\Consent::class) && \MyCms\Consent\Consent::has($service);
    }
}

return [
    'name' => 'consent_kit',
    'label' => 'Consent-Kit (Cookie-Einwilligung)',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',
    'description' => 'Einwilligungsverwaltung: Dienste aus geprüften Vorlagen, barrierefreier Hinweis im Design der Website, 2-Klick-Platzhalter, Google Consent Mode v2, GPC, Protokoll ohne IP-Adresse. Port des REDAXO-AddOns consent_kit (MIT).',
    'author' => 'KLXM Crossmedia GmbH and contributors',
    'license' => 'MIT',
    'provides' => ['Menüpunkt „Cookie-Einwilligung“', 'Hinweis und Skript auf der Website, sobald ein einwilligungspflichtiger Dienst aktiv ist', 'Block „Externer Inhalt (mit Einwilligung)“', 'Link „Cookie-Einstellungen“ im Fußbereich'],
    'docs' => ['Technik: Consent-Kit' => '/admin/hilfe/technik#consent'],
    'boot' => function (Core\Extension $x): void {
        $x->feature('consent', 'Cookie-Einwilligung (Consent-Kit): Dienste, Hinweis, Protokoll', ['consent.manage']);
        $x->permissions('Cookie-Einwilligung', ['consent.manage' => 'Dienste, Design, Einstellungen und Protokoll der Cookie-Einwilligung verwalten']);
        $x->migration(1, fn(Core\Database $db) => MyCms\Consent\Repository::migrate($db));
        $x->nav('/admin/consent', 'Cookie-Einwilligung', 'cookie', 'consent.manage');

        // Website: Konfiguration + Skript nur auf Websites mit aktivem, einwilligungspflichtigem Dienst
        $x->htmlFilter(fn(string $html, array $ctx) => MyCms\Consent\Consent::filterHtml($html, $ctx));
        // CSP: Hosts eines Dienstes erst nach Einwilligung (Cookie dieser Anfrage); iframe-Hosts aktiver Dienste für den Platzhalter
        $x->csp(fn() => MyCms\Consent\Consent::cspSources());
        // „Cookie-Einstellungen“ in der Rechtliches-Zeile der Theme-Fußbereiche (footer_links())
        $x->footerLinks(fn() => MyCms\Consent\Consent::footerLinks());

        // Block „Externer Inhalt (mit Einwilligung)“: Karten-, Social-Media- und Audio-Einbettungen als 2-Klick-Platzhalter
        $x->blocks(['consent_embed' => MyCms\Consent\Consent::blockDefinition()]);

        $x->routes(function (Core\Http\Router $r): void {
            // Website (ohne Sitzung, ohne Cookies bis zur Entscheidung)
            $r->post('/consent/save', [MyCms\Consent\PublicController::class, 'save']);
            $r->get('/consent/js/{file}', [MyCms\Consent\PublicController::class, 'code']);
            $r->get('/consent/style.css', [MyCms\Consent\PublicController::class, 'style']);
            // Verwaltung
            $c = MyCms\Consent\AdminController::class;
            $r->get('/admin/consent', [$c, 'index']);
            $r->post('/admin/consent/services/toggle', [$c, 'toggle']);
            $r->post('/admin/consent/services/domains', [$c, 'domains']);
            $r->post('/admin/consent/services/move', [$c, 'move']);
            $r->get('/admin/consent/templates', [$c, 'templates']);
            $r->get('/admin/consent/service/new', [$c, 'create']);
            $r->get('/admin/consent/service/{id}', [$c, 'edit']);
            $r->post('/admin/consent/service/{id}', [$c, 'save']);
            $r->post('/admin/consent/service/{id}/delete', [$c, 'delete']);
            $r->post('/admin/consent/service/{id}/reset', [$c, 'reset']);
            $r->get('/admin/consent/service/{id}/export', [$c, 'exportOne']);
            $r->get('/admin/consent/settings', [$c, 'settings']);
            $r->post('/admin/consent/settings', [$c, 'saveSettings']);
            $r->post('/admin/consent/reask', [$c, 'reask']);
            $r->get('/admin/consent/design', [$c, 'design']);
            $r->post('/admin/consent/design', [$c, 'saveDesign']);
            $r->get('/admin/consent/log', [$c, 'log']);
            $r->get('/admin/consent/log.csv', [$c, 'logCsv']);
            $r->post('/admin/consent/log/purge', [$c, 'purge']);
            $r->get('/admin/consent/revision/{id}', [$c, 'revision']);
            $r->get('/admin/consent/io', [$c, 'io']);
            $r->post('/admin/consent/io/import', [$c, 'import']);
            $r->post('/admin/consent/io/export', [$c, 'export']);
            $r->get('/admin/consent/io/file/{name}', [$c, 'downloadTemplate']);
            $r->post('/admin/consent/io/file/{name}/delete', [$c, 'deleteTemplate']);
        });

        $x->command('consent:purge', 'Consent-Kit: Protokoll nach Aufbewahrungsfrist bereinigen [--days=N] (Standard: Einstellung, 1095 Tage; 0 = nie)', function (array $args): int {
            $days = null;
            foreach ($args as $a) if (preg_match('~^--days=(\d+)$~', (string) $a, $m)) $days = (int) $m[1];
            $n = MyCms\Consent\Log::purge($days);
            echo 'Consent-Kit (' . site()->key . '): ' . $n . " Protokolleinträge gelöscht.\n";
            return 0;
        });
        $x->command('consent:status', 'Consent-Kit: aktive Dienste, Hosts in der CSP und Protokoll-Umfang dieser Website', function (array $args): int {
            foreach (MyCms\Consent\Consent::statusLines() as $line) echo $line, "\n";
            return 0;
        });
    },
];
