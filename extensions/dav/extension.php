<?php
/**
 * Erweiterung „dav“: CalDAV (Kalender-Tabellen) und CardDAV (Adressbuch-Tabellen) mit sabre/dav 4.7.
 * Aktivieren je Website: 'extensions' => ['dav'] in config/sites/{key}.php bzw. config/config.local.php.
 * Doku: extensions/dav/README.md und Verwaltung → Hilfe → Technik → „CalDAV/CardDAV“.
 */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'MyCms\\Dav\\')) {
        $file = __DIR__ . '/src/' . substr($class, strlen('MyCms\\Dav\\')) . '.php';
        if (is_file($file)) require $file;
    }
});

return [
    'name' => 'dav',
    'label' => 'CalDAV/CardDAV',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',
    'description' => 'Termine und Kontakte aus Datentabellen mit Kalender- und Adressbuch-Apps abgleichen (Apple, Thunderbird, DAVx⁵). Anmeldung mit App-Passwörtern.',
    'author' => 'KLXM Crossmedia GmbH and contributors',
    'license' => 'MIT',
    'risk' => 'Öffnet /dav für Kalender- und Adressbuch-Apps. Wer ein App-Passwort hat, kann freigegebene Tabellen lesen und – je nach Passwort – ändern.',
    'provides' => ['Menüpunkt „Kalender & Kontakte in Apps“', 'Adressen /dav und /.well-known/caldav|carddav', 'App-Passwörter je Benutzer'],
    'requirements' => fn(): array => ['sabre/dav (Composer)' => class_exists(\Sabre\DAV\Server::class)],
    'docs' => ['Technik: CalDAV/CardDAV' => '/admin/hilfe/technik#dav'],
    'boot' => function (Core\Extension $x): void {
        $x->feature('dav', 'CalDAV/CardDAV (Kalender und Kontakte in Apps)', ['dav.use']);
        $x->permissions('CalDAV/CardDAV', ['dav.use' => 'Kalender und Kontakte mit Apps abgleichen (App-Passwörter)']);
        $x->migration(1, fn(Core\Database $db) => MyCms\Dav\Dav::migrate($db));
        $x->nav('/admin/dav', 'Kalender & Kontakte in Apps', 'dav', 'dav.use');
        $x->routes(function (Core\Http\Router $r): void {
            // WebDAV nutzt eigene Methoden (PROPFIND, REPORT, PUT, DELETE, MKCALENDAR, OPTIONS …) → any()
            $r->any('/dav', [MyCms\Dav\Server::class, 'handle']);
            $r->any('/dav/{path*}', fn(Core\Http\Request $req, string $path) => MyCms\Dav\Server::handle($req));
            $r->any('/.well-known/caldav', [MyCms\Dav\Server::class, 'wellKnown']);
            $r->any('/.well-known/carddav', [MyCms\Dav\Server::class, 'wellKnown']);
            $r->get('/admin/dav', [MyCms\Dav\AdminController::class, 'index']);
            $r->post('/admin/dav/passwords', [MyCms\Dav\AdminController::class, 'create']);
            $r->post('/admin/dav/passwords/{id}/delete', [MyCms\Dav\AdminController::class, 'delete']);
            $r->post('/admin/dav/tables', [MyCms\Dav\AdminController::class, 'tables']);
        });
        $x->command('dav:password', 'App-Passwort anlegen: dav:password <e-mail> [name] [read|write]', function (array $args): int {
            $u = app()->db->fetch('SELECT id FROM users WHERE LOWER(email) = LOWER(?)', [(string) ($args[0] ?? '')]);
            if (!$u) { fwrite(STDERR, "Benutzer nicht gefunden.\n"); return 1; }
            echo MyCms\Dav\Dav::createPassword((int) $u['id'], (string) ($args[1] ?? 'CLI'), (string) ($args[2] ?? 'write')), "\n";
            return 0;
        });
    },
];
