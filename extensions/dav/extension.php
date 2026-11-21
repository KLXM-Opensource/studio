<?php
/**
 * Erweiterung „dav“: CalDAV (Kalender-Tabellen) und CardDAV (Adressbuch-Tabellen) mit sabre/dav 4.7.
 * Aktivieren je Website: 'extensions' => ['dav'] in config/sites/{key}.php bzw. config/config.local.php.
 * Doku: extensions/dav/README.md und Verwaltung → Hilfe → Technik → „CalDAV/CardDAV“.
 */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Klxm\\Dav\\')) {
        $file = __DIR__ . '/src/' . substr($class, strlen('Klxm\\Dav\\')) . '.php';
        if (is_file($file)) require $file;
    }
});

return [
    'name' => 'dav',
    'label' => 'CalDAV/CardDAV',
    'version' => '1.5.0',
    'requires' => '>=1.0.0',
    'description' => 'Termine und Kontakte aus Datentabellen mit Kalender- und Adressbuch-Apps abgleichen (Apple, Thunderbird, DAVx⁵). Anmeldung mit App-Passwörtern.',
    'author' => 'KLXM Crossmedia GmbH and contributors',
    'license' => 'MIT',
    'risk' => 'Öffnet /dav für Kalender- und Adressbuch-Apps. Wer ein App-Passwort hat, kann freigegebene Tabellen lesen und – je nach Passwort – ändern.',
    'provides' => ['Abschnitt „Kalender & Kontakte in Apps“ im Konto (App-Passwörter)', 'Einstellungen je Tabelle unter Administration → Einstellungen → Einstellungen der Funktionen (Recht data.schema)', 'Adressen /dav und /.well-known/caldav|carddav', 'App-Passwörter je Benutzer'],
    'requirements' => fn(): array => ['sabre/dav (Composer)' => class_exists(\Sabre\DAV\Server::class)],
    'docs' => ['Technik: CalDAV/CardDAV' => '/admin/hilfe/technik#dav'],
    'commands' => ['dav:password', 'dav:selftest'],
    'boot' => function (Core\Extension $x): void {
        $x->feature('dav', 'CalDAV/CardDAV (Kalender und Kontakte in Apps)', ['dav.use']);
        $x->permissions('CalDAV/CardDAV', ['dav.use' => 'Kalender und Kontakte mit Apps abgleichen (App-Passwörter)']);
        $x->migration(1, fn(Core\Database $db) => Klxm\Dav\Dav::migrate($db));
        // Persönliche Einrichtung (App-Passwörter) über den Slot „Konto“; Einstellungen je Tabelle auf der Sammelseite „Einstellungen“
        $x->account(fn(array $user) => Klxm\Dav\Dav::accountCard($user), 'dav.use');
        // Konto gelöscht: seine App-Passwörter entfernen
        $x->on(Core\Events\UserDeleted::class, fn(Core\Events\UserDeleted $e) => app()->db->query('DELETE FROM dav_passwords WHERE user_id = ?', [$e->id]));
        // Seite „Abonnieren“ (Core\Data\Subscriptions): Kontakte per CardDAV mit dem Abo-Token als Passwort (nur lesen)
        $x->filter('subscriptions.apps', fn(array $apps, array $c) => Klxm\Dav\Dav::enabled() && Core\Data\Subscriptions::kind($c['table']) === 'contacts'
            ? [...$apps, ['key' => 'carddav', 'label' => __('CardDAV (Adressbuch-Apps)'), 'url' => absolute_url('/dav/'), 'user' => 'abo', 'password' => $c['token'] ?? null,
                'help' => __('In der Adressbuch-App ein CardDAV-Konto anlegen: Server-Adresse, Benutzername „abo“, Passwort = Abo-Token. Nur lesen – Änderungen nur im CMS.')]] : null);
        $x->adminPage(['href' => '/admin/dav', 'label' => 'Kalender & Kontakte in Apps', 'kind' => 'settings', 'icon' => 'dav',
            'perm' => 'data.schema', 'feature' => 'dav', 'visible' => fn(): bool => can('dav.use') && Klxm\Dav\Dav::enabled(),
            'description' => 'Tabellen als Kalender bzw. Adressbuch für Apps bereitstellen, App-Passwörter']);
        $x->routes(function (Core\Http\Router $r): void {
            // WebDAV nutzt eigene Methoden (PROPFIND, REPORT, PUT, DELETE, MKCALENDAR, OPTIONS …) → any()
            $r->any('/dav', [Klxm\Dav\Server::class, 'handle']);
            $r->any('/dav/{path*}', fn(Core\Http\Request $req, string $path) => Klxm\Dav\Server::handle($req));
            $r->any('/.well-known/caldav', [Klxm\Dav\Server::class, 'wellKnown']);
            $r->any('/.well-known/carddav', [Klxm\Dav\Server::class, 'wellKnown']);
            // Verwaltung: Recht an jeder Route (Anmeldung, Recht und CSRF prüft der Router vor dem Controller)
            $c = Klxm\Dav\AdminController::class;
            $r->get('/admin/dav', [$c, 'index'], 'dav.use');
            $r->post('/admin/dav/passwords', [$c, 'create'], 'dav.use');
            $r->post('/admin/dav/passwords/{id}/delete', [$c, 'delete'], 'dav.use');
            $r->post('/admin/dav/tables', [$c, 'tables'], 'data.schema');
        });
        $x->command('dav:selftest', 'CalDAV/CardDAV: Namen (FN → N), Organisation, Rich-Text als Text, Rückweg aus Apps, nur veröffentlichte Einträge',
            fn(array $args): int => Klxm\Dav\SelfTest::run());
        $x->command('dav:password', 'App-Passwort anlegen: dav:password <e-mail> [name] [read|write]', function (array $args): int {
            $u = app()->db->fetch('SELECT id FROM users WHERE LOWER(email) = LOWER(?)', [(string) ($args[0] ?? '')]);
            if (!$u) { fwrite(STDERR, "Benutzer nicht gefunden.\n"); return 1; }
            echo Klxm\Dav\Dav::createPassword((int) $u['id'], (string) ($args[1] ?? 'CLI'), (string) ($args[2] ?? 'write')), "\n";
            return 0;
        });
    },
];
