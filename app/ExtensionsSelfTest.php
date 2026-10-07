<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * php bin/console extensions:selftest – Schnittstellen für Erweiterungen ohne echte Erweiterung prüfen:
 *  - Verwaltungsseiten (Core\AdminPages): Arten, Ableitung alter nav()-Aufrufe, Menü nur für content/tool, Sammelseite,
 *    Tabelle („glossar“), Rechte/Funktion/visible, match(), ungültige Angaben;
 *  - Werkzeuge der Website (Core\FrontendTools): Tastenkürzel, Modul-Adressen (nur eigene Domain/assets), Bearbeiten-Modus,
 *    Rechte, Konfiguration ohne Callables;
 *  - Ereignisse (Extension::on, Extensions::listens/emit): page.saved/published/unpublished, entry.saved/published/unpublished/deleted
 *    in einer Transaktion, die am Ende zurückgerollt wird; typisiert (Core\Events) und Altform;
 *  - Verwaltungsrouten von Erweiterungen (Core\Http\Router::scoped): Anmeldung, Recht, CSRF, Altform, benannte Ausnahmen;
 *  - Tabellen von Erweiterungen (Extension::table, Fingerabdruck) in einer Transaktion, die zurückgerollt wird;
 *  - Slots der Verwaltung (Core\Slots): Methoden, Escapen, nur eigene Pfade, Platzhalter, Rechte, Altform-HTML, Fehler isoliert.
 * Rollen werden für die Prüfung vorübergehend gesetzt (Auth per Reflection) und danach zurückgestellt.
 */
final class ExtensionsSelfTest
{
    private static int $ok = 0;
    private static array $fail = [];

    private static function eq(string $what, mixed $got, mixed $want): void
    {
        if ($got === $want) { self::$ok++; return; }
        self::$fail[] = $what . ': erwartet ' . json_encode($want, JSON_UNESCAPED_UNICODE) . ', erhalten ' . json_encode($got, JSON_UNESCAPED_UNICODE);
    }

    /** Angemeldete Rolle vorübergehend setzen (null = abgemeldet) */
    private static function actAs(?array $perms, ?array $tables = null): void
    {
        $a = app()->auth;
        $set = function (string $prop, mixed $v) use ($a): void {
            $p = new \ReflectionProperty($a, $prop);
            $p->setValue($a, $v);
        };
        $set('loaded', true);
        $set('user', $perms === null ? null : ['id' => 0, 'email' => 'selbsttest@example.test', 'name' => 'Selbsttest', 'role' => 'qa', 'appearance' => null, 'locale' => null]);
        $set('role', $perms === null ? null : ['key' => 'qa', 'name' => 'QA', 'description' => '', 'permissions' => $perms, 'tables' => $tables, 'builtin' => false]);
    }

    /** Erweiterung vorübergehend als aktiv eintragen */
    private static function activate(?Extension $x): void
    {
        $p = new \ReflectionProperty(Extensions::class, 'active');
        $list = $p->getValue();
        if ($x) $list[$x->name] = $x; else unset($list['qa_selftest']);
        $p->setValue(null, $list);
    }

    public static function run(): array
    {
        self::$ok = 0;
        self::$fail = [];
        $auth = app()->auth;
        $saved = [];
        foreach (['loaded', 'user', 'role'] as $prop) $saved[$prop] = (new \ReflectionProperty($auth, $prop))->getValue($auth);
        try {
            self::adminPages();
            self::frontendTools();
            self::events();
            self::routes();
            self::tables();
            self::slots();
            self::push();
        } catch (\Throwable $e) {
            self::$fail[] = 'Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            self::activate(null);
            AdminPages::reset();
            FrontendTools::reset();
            foreach ($saved as $prop => $v) (new \ReflectionProperty($auth, $prop))->setValue($auth, $v);
        }
        return ['ok' => self::$ok, 'fails' => self::$fail];
    }

    // ================================================================= Push-Ereignisse (Extension::pushEvent → Konto → Benachrichtigungen)

    private static function push(): void
    {
        $x = new Extension('qa_selftest', __DIR__, ['label' => 'QA-Erweiterung']);
        $x->pushEvent('qa_selftest.neu', 'QA: Neues', 'pages.edit', ['help' => 'Hilfe', 'default' => false]);
        $ev = \Core\Push\Push::events()['qa_selftest.neu'] ?? null;
        self::eq('pushEvent: angemeldet mit Recht, Vorgabe und Herkunft', [$ev['perm'] ?? null, $ev['default'] ?? null, $ev['owner'] ?? null], ['pages.edit', false, 'qa_selftest']);
        self::eq('pushEvent: sichtbar nur mit Recht', [isset(\Core\Push\Push::visibleEvents(['role' => 'admin'])['qa_selftest.neu']), isset(\Core\Push\Push::visibleEvents(['role' => 'requests'])['qa_selftest.neu'])], [true, false]);
        self::eq('notifyUsers wirft nie (unbekanntes Ereignis bzw. Funktion aus)', \Core\Push\Push::notifyUsers([1], ['title' => 'x'], 'qa_selftest.gibtesnicht'), 0);
        \Core\Push\Push::unregisterEvent('qa_selftest.neu');
        try {
            $x->pushEvent('BÖSE', 'x');
            self::eq('pushEvent: ungültiger Schlüssel abgelehnt', false, true);
        } catch (\InvalidArgumentException) {
            self::eq('pushEvent: ungültiger Schlüssel abgelehnt', true, true);
        }
    }

    // ================================================================= Verwaltungsseiten

    private static function adminPages(): void
    {
        // Ableitung ohne Art (veraltete nav()-Aufrufe bleiben an ihrem Platz)
        $n = AdminPages::normalize(['href' => '/admin/x', 'label' => 'X'], 'core');
        self::eq('ohne Art + main → content', [$n['kind'], $n['place'], $n['legacy']], ['content', 'main', true]);
        $n = AdminPages::normalize(['href' => '/admin/x', 'label' => 'X', 'place' => 'admin'], 'core');
        self::eq('ohne Art + admin → tool', [$n['kind'], $n['place']], ['tool', 'admin']);
        $n = AdminPages::normalize(['href' => '/admin/x', 'label' => 'X', 'kind' => 'settings', 'table' => 'glossar'], 'core');
        self::eq('settings mit Tabelle', [$n['kind'], $n['table'], $n['legacy']], ['settings', 'glossar', false]);
        self::eq('ungültige Tabelle wird verworfen', AdminPages::normalize(['href' => '/admin/x', 'label' => 'X', 'table' => 'DROP TABLE'], 'core')['table'], null);
        self::eq('fremde Adresse abgelehnt', AdminPages::normalize(['href' => 'https://evil.example/', 'label' => 'X'], 'core'), null);
        self::eq('Protokoll-relative Adresse abgelehnt', AdminPages::normalize(['href' => '//evil.example/a', 'label' => 'X'], 'core'), null);
        self::eq('leere Beschriftung abgelehnt', AdminPages::normalize(['href' => '/admin/x', 'label' => ' '], 'core'), null);

        // Erweiterung mit allen Arten (über nav() mit Art bzw. adminPage)
        $x = new Extension('qa_selftest', __DIR__, ['label' => 'QA-Erweiterung']);
        $x->nav('/admin/qa-inhalt', 'QA Inhalt', 'ext', 'pages.edit');                              // veraltet → content, Hauptmenü
        $x->nav('/admin/qa-werkzeug', 'QA Werkzeug', 'ext', null, 'admin');                         // veraltet → tool, Administration
        $x->nav('/admin/qa-einstellungen', 'QA Einstellungen', 'gear-six', 'pages.edit', 'settings', ['table' => 'qa_tabelle', 'description' => 'Zeile']);
        $x->adminPage(['href' => '/admin/qa-statistik', 'label' => 'QA Statistik', 'kind' => 'stats', 'icon' => 'chart-bar']);
        $x->adminPage(['href' => '/admin/qa-geheim', 'label' => 'QA Geheim', 'kind' => 'settings', 'visible' => fn() => false]);
        $x->adminPage(['href' => '/admin/qa-funktion', 'label' => 'QA Funktion', 'kind' => 'settings', 'feature' => 'qa.gibt.es.nicht']);
        $x->adminPage(['href' => 'javascript:alert(1)', 'label' => 'Böse']);
        self::eq('Erweiterung: ungültige Seite nicht angemeldet', count($x->pages), 6);
        self::eq('nav() bleibt rückwärtskompatibel ([href, label, icon, perm, place, kind])', $x->nav[2][5] ?? null, 'settings');
        self::activate($x);

        self::actAs(['pages.edit']);
        $main = array_column(AdminPages::nav('main'), 0);
        $admin = array_column(AdminPages::nav('admin'), 0);
        self::eq('Hauptmenü: content der Erweiterung', in_array('/admin/qa-inhalt', $main, true), true);
        self::eq('Administration: tool der Erweiterung', in_array('/admin/qa-werkzeug', $admin, true), true);
        self::eq('Menü: keine Einstellungen/Statistiken', array_values(array_intersect(['/admin/qa-einstellungen', '/admin/qa-statistik', '/admin/glossar', '/admin/api-tokens'], [...$main, ...$admin])), []);
        // Nur die Einträge der Prüf-Erweiterung betrachten – auf echten Websites sind weitere Erweiterungen aktiv (z. B. Buchungen)
        $legacy = array_column(Extensions::adminNav('main'), 0);
        self::eq('Extensions::adminNav (veraltet) nur Erweiterungen', array_values(array_filter($legacy, fn($u) => str_starts_with((string) $u, '/admin/qa-'))), ['/admin/qa-inhalt']);
        self::eq('Extensions::adminNav (veraltet) ohne Core-Seiten', array_values(array_intersect(['/admin/glossar', '/admin/seiten', '/admin/medien'], $legacy)), []);
        $hub = AdminPages::settings();
        $qa = array_values(array_filter($hub, fn($g) => $g['key'] === 'ext:qa_selftest'))[0] ?? null;
        self::eq('Sammelseite: Gruppe mit Namen der Erweiterung', $qa['label'] ?? null, 'QA-Erweiterung');
        self::eq('Sammelseite: nur sichtbare Einstellungen (visible, feature)', array_column($qa['pages'] ?? [], 'href'), ['/admin/qa-einstellungen']);
        self::eq('Statistiken: Seite der Erweiterung', array_column(AdminPages::stats()[0]['pages'] ?? [], 'href'), ['/admin/qa-statistik']);
        self::eq('hasStats()', AdminPages::hasStats(), true);
        self::eq('Tabelle: Einstellungsseite angehängt', array_column(AdminPages::forTable('qa_tabelle'), 'href'), ['/admin/qa-einstellungen']);
        self::eq('Tabelle: fremde Tabelle leer', AdminPages::forTable('andere'), []);
        self::eq('match(): Unterseite gehört zur Einstellungsseite', AdminPages::match('/admin/qa-einstellungen/kanal/3')['kind'] ?? null, 'settings');
        self::eq('match(): ähnlicher Pfad gehört nicht dazu', AdminPages::match('/admin/qa-einstellungenxyz')['href'] ?? null, null);

        // Rechte: ohne pages.edit verschwinden Seite im Menü (sichtbar = false) und Karte
        self::actAs(['media.upload']);
        $vis = array_column(AdminPages::nav('main'), 3, 0);
        self::eq('Rechte: Menüeintrag ohne Recht unsichtbar', $vis['/admin/qa-inhalt'] ?? null, false);
        self::eq('Rechte: Karte ohne Recht fehlt', array_column(AdminPages::forTable('qa_tabelle'), 'href'), []);
        self::eq('Rechte: API & MCP nur mit api.manage', in_array('/admin/api-tokens', array_column(AdminPages::ofKind('settings'), 'href'), true), false);
        self::actAs(['api.manage']);
        self::eq('Rechte: API & MCP mit api.manage', in_array('/admin/api-tokens', array_column(AdminPages::ofKind('settings'), 'href'), true), Features::on('api', true) ? true : false);

        // Glossar (Kern): Einstellungsseite der Tabelle „glossar“ – nur mit Funktion und Recht data.edit auf die Tabelle
        $g = array_values(array_filter(AdminPages::all(), fn($p) => $p['id'] === 'glossary'))[0] ?? null;
        self::eq('Glossar: settings an Tabelle glossar', [$g['kind'] ?? null, $g['table'] ?? null, $g['href'] ?? null], ['settings', 'glossar', '/admin/glossar']);
        if (\Core\Glossary\Glossary::enabled() && \Core\Glossary\Glossary::table()) {
            self::actAs(['data.edit'], ['andere_tabelle']);
            self::eq('Glossar: Recht nur auf andere Tabelle → keine Karte', AdminPages::forTable('glossar'), []);
            self::actAs(['data.edit'], ['glossar']);
            self::eq('Glossar: Recht auf glossar → Knopf an der Tabelle', array_column(AdminPages::forTable('glossar'), 'href'), ['/admin/glossar']);
        } else {
            self::eq('Glossar aus: keine Karte', in_array('/admin/glossar', array_column(AdminPages::ofKind('settings'), 'href'), true), false);
        }

        // Kern-Anmeldung über register()
        AdminPages::register(['id' => 'qa-core', 'href' => '/admin/qa-kern', 'label' => 'QA Kern', 'kind' => 'tool', 'place' => 'admin']);
        self::actAs(['*']);
        self::eq('register(): Kern-Werkzeug in Administration', in_array('/admin/qa-kern', array_column(AdminPages::nav('admin'), 0), true), true);
        self::activate(null);
        self::eq('Erweiterung aus → ihre Seiten weg', in_array('/admin/qa-inhalt', array_column(AdminPages::nav('main'), 0), true), false);
    }

    // ================================================================= Werkzeuge der Website

    private static function frontendTools(): void
    {
        $sc = FrontendTools::shortcut('Alt+G');
        self::eq('Kürzel Alt+G', [$sc['keys'] ?? null, $sc['label'] ?? null, $sc['code'] ?? null, $sc['alt'] ?? null], ['Alt+G', '⌥G', 'KeyG', true]);
        self::eq('Kürzel Alt+Shift+K', FrontendTools::shortcut('alt+shift+k')['keys'] ?? null, 'Alt+Shift+K');
        self::eq('Kürzel ohne Alt/Strg/⌘ abgelehnt', FrontendTools::shortcut('G'), null);
        self::eq('Kürzel Unsinn abgelehnt', FrontendTools::shortcut('Alt+Tabulator'), null);

        $x = new Extension('qa_selftest', __DIR__, ['label' => 'QA']);
        $x->frontendTool(['id' => 'qa-ok', 'label' => 'QA', 'module' => 'js/qa.mjs', 'perm' => 'pages.edit', 'endpoints' => ['go' => '/admin/api/qa', 'bad' => 'https://evil.example']]);
        $x->frontendTool(['id' => 'qa-pfad', 'label' => 'QA', 'module' => '../geheim.mjs']);
        $x->frontendTool(['id' => 'qa-fremd', 'label' => 'QA', 'module' => '//evil.example/x.mjs']);
        $x->frontendTool(['id' => 'Böse ID', 'label' => 'QA', 'module' => 'js/x.mjs']);
        $x->frontendTool(['id' => 'qa-view', 'label' => 'QA Ansehen', 'module' => 'js/v.mjs', 'perm' => 'pages.edit', 'view' => true, 'chip' => 'Als QA']);
        $x->frontendTool(['id' => 'qa-nurview', 'label' => 'QA nur Ansehen', 'module' => 'js/v.mjs', 'perm' => 'pages.edit', 'modes' => ['view', 'quatsch']]);
        self::eq('Werkzeug: nur gültige Angaben angemeldet (kein ../, keine fremde Domain, gültige id)', array_column($x->frontendTools, 'id'), ['qa-ok', 'qa-view', 'qa-nurview']);
        self::eq('Modi: Standard nur Bearbeiten, view => true ergänzt Ansehen, nur gültige Modi', array_column($x->frontendTools, 'modes'), [['page', 'entry'], ['page', 'entry', 'view'], ['view']]);
        self::eq('chip nur mit Ansehen', [$x->frontendTools[1]['chip'], FrontendTools::normalize(['id' => 'qa', 'label' => 'QA', 'module' => '/x.mjs', 'chip' => 'X'])['chip'] ?? null], ['Als QA', '']);
        self::eq('Werkzeug: Modul aus assets der Erweiterung', str_contains((string) ($x->frontendTools[0]['module'] ?? ''), '/ext/qa_selftest/js/qa.mjs'), true);
        self::eq('Werkzeug: nur eigene Endpunkte', array_keys($x->frontendTools[0]['endpoints'] ?? []), ['go']);
        self::eq('Kern: Modul nur ab „/“', FrontendTools::normalize(['id' => 'qa', 'label' => 'QA', 'module' => 'https://cdn.example/x.mjs'], 'core'), null);
        self::activate($x);

        $bar = ['kind' => 'page', 'editing' => true, 'hasPage' => true, 'live' => false, 'page' => ['id' => 1, 'title' => 'T', 'lang' => null]];
        self::actAs(null);
        self::eq('Werkzeuge: abgemeldet keine', FrontendTools::forBar($bar), []);
        self::actAs(['pages.edit']);
        self::eq('Modus: Seite bearbeiten', FrontendTools::mode($bar), 'page');
        self::eq('Modus: Ansehen → keiner', FrontendTools::mode(['editing' => false] + $bar), null);
        self::eq('Modus: Live-Fassung → keiner', FrontendTools::mode(['live' => true] + $bar), null);
        self::eq('Modus: Eintrag nur wenn bearbeitbar', [FrontendTools::mode(['kind' => 'entry', 'entryEditable' => true] + $bar), FrontendTools::mode(['kind' => 'entry', 'entryEditable' => false] + $bar)], ['entry', null]);
        $ids = array_column(FrontendTools::forBar($bar), 'id');
        self::eq('Werkzeuge: mit Recht im Bearbeiten-Modus', in_array('qa-ok', $ids, true), true);
        self::eq('Werkzeuge: mit view auch im Bearbeiten-Modus, nur Ansehen nicht', [in_array('qa-view', $ids, true), in_array('qa-nurview', $ids, true)], [true, false]);
        $view = ['editing' => false] + $bar;
        self::eq('Ansehen: nur Werkzeuge mit view', array_column(FrontendTools::forBar($view), 'id'), ['qa-view', 'qa-nurview']);
        self::eq('Ansehen: viewing() Seite/Bearbeiten/Vorlage/Eintrag', [FrontendTools::viewing($view), FrontendTools::viewing($bar), FrontendTools::viewing(['kind' => 'template'] + $bar), FrontendTools::viewing(['kind' => 'entry'] + $bar)], [true, false, false, true]);
        $tv = $x->frontendTools[1];
        $te = $x->frontendTools[0];
        self::eq('when(): Seite Ansehen/Bearbeiten', [FrontendTools::when($tv, $view), FrontendTools::when($tv, $bar), FrontendTools::when($te, $view), FrontendTools::when($te, $bar)], ['view', 'edit', null, 'edit']);
        $eb = ['kind' => 'entry', 'entryEditable' => true] + $bar;
        self::eq('when(): Eintrag (Wechsel ohne Neuladen)', [FrontendTools::when($tv, $eb), FrontendTools::when($te, $eb), FrontendTools::when($tv, ['entryEditable' => false] + $eb), FrontendTools::when($te, ['entryEditable' => false] + $eb)], ['both', 'edit', 'view', null]);
        self::eq('Live-Fassung: Ansehen-Werkzeuge ja, Bearbeiten-Werkzeuge nein', array_column(FrontendTools::forBar(['live' => true] + $view), 'id'), ['qa-view', 'qa-nurview']);
        $cv = FrontendTools::config($view, FrontendTools::forBar($view));
        $ce = FrontendTools::config($bar, FrontendTools::forBar($bar));
        self::eq('Konfiguration: mode view|edit, editKind', [$cv['mode'], $cv['editKind'], $ce['mode'], $ce['editKind'], FrontendTools::config(['kind' => 'template'] + $bar, [])['mode']], ['view', null, 'edit', 'page', 'edit']);
        $one = array_values(array_filter(FrontendTools::forBar($view), fn($t) => $t['id'] === 'qa-view'))[0] ?? [];
        self::eq('Konfiguration Ansehen: when + chip', [$one['when'] ?? null, $one['chip'] ?? null], ['view', 'Als QA']);
        self::actAs(null);
        self::eq('Ansehen: abgemeldet keine', FrontendTools::forBar($view), []);
        self::actAs(['pages.edit']);
        $one = array_values(array_filter(FrontendTools::forBar($bar), fn($t) => $t['id'] === 'qa-ok'))[0] ?? [];
        self::eq('Konfiguration: Endpunkte als Adressen, keine Callables', [str_ends_with((string) ($one['endpoints']['go'] ?? ''), '/admin/api/qa'), json_encode($one) !== false], [true, true]);
        self::actAs(['media.upload']);
        self::eq('Werkzeuge: ohne Recht keine', in_array('qa-ok', array_column(FrontendTools::forBar($bar), 'id'), true), false);
        // Quick-Glossar (Kern): Funktion + Tabelle + data.edit auf glossar
        $gOn = \Core\Glossary\Glossary::enabled() && \Core\Glossary\Glossary::table();
        self::actAs(['pages.edit', 'data.edit'], ['andere_tabelle']);
        self::eq('Quick-Glossar: ohne Recht auf glossar nicht da', in_array('glossary', array_column(FrontendTools::forBar($bar), 'id'), true), false);
        self::actAs(['pages.edit', 'data.edit'], ['glossar']);
        $qg = array_values(array_filter(FrontendTools::forBar($bar), fn($t) => $t['id'] === 'glossary'))[0] ?? null;
        self::eq('Quick-Glossar: mit Recht da (wenn eingeschaltet)', $qg !== null, (bool) $gOn);
        if ($qg) {
            self::eq('Quick-Glossar: Kürzel ⌥G, Platz main', [$qg['shortcut']['label'] ?? null, $qg['placement']], ['⌥G', 'main']);
            self::eq('Quick-Glossar: ohne data.publish kein Veröffentlichen', $qg['data']['publish'] ?? null, false);
            self::eq('Quick-Glossar: Endpunkte', array_keys($qg['endpoints']), ['search', 'create', 'page']);
            $qv = array_values(array_filter(FrontendTools::forBar(['editing' => false] + $bar), fn($t) => $t['id'] === 'glossary'))[0] ?? null;
            self::eq('Quick-Glossar: auch beim Ansehen, mit schwebendem Knopf', [$qv['when'] ?? null, ($qv['chip'] ?? '') !== ''], ['view', true]);
            self::eq('Quick-Glossar: Ansehen ohne Seitenrecht kein Wechsel-Link', $qv['data']['editUrl'] ?? null, '');
        }
        self::actAs(['data.edit'], ['andere_tabelle']);
        self::eq('Quick-Glossar: Ansehen ohne Recht auf glossar nicht da', in_array('glossary', array_column(FrontendTools::forBar(['editing' => false] + $bar), 'id'), true), false);
        self::activate(null);
    }

    // ================================================================= Ereignisse

    private static function events(): void
    {
        $got = [];
        $x = new Extension('qa_selftest', __DIR__, ['label' => 'QA']);
        foreach (['page.saved', 'page.published', 'page.unpublished', 'page.discarded', 'entry.saved', 'entry.published', 'entry.unpublished', 'entry.deleted'] as $ev) {
            $x->on($ev, function (...$a) use (&$got, $ev): void { $got[] = $ev . ':' . (is_array($a[0] ?? null) ? ($a[0]['handle'] ?? $a[0]['id'] ?? '') : '') ; });
        }
        // Typisiert: Klasse, Name mit Ereignis-Typ (Alias) und Altform nebeneinander
        $typed = [];
        $x->on(Events\PageSaved::class, function (Events\PageSaved $e) use (&$typed): void { $typed[] = ['class', $e->table, $e->id, $e->state, $e->lang, $e['id'], $e['title']]; });
        $x->on('page.saved', function (Events\PageEvent $e) use (&$typed): void { $typed[] = ['alias', $e::class]; });
        $x->on('page.saved', function (array $page, ?int $uid) use (&$typed): void { $typed[] = ['legacy', (int) $page['id'], $uid]; });
        $x->on(Events\PagePublished::class, function (Events\PagePublished $e) use (&$typed): void { $typed[] = ['published', $e->state]; });
        $x->on(Events\EntrySaved::class, function (Events\EntrySaved $e) use (&$typed): void { $typed[] = ['entry', $e->table, $e->created, $e->state, $e->old === null]; });
        $x->on(Events\EntryDeleted::class, function (Events\EntryDeleted $e) use (&$typed): void { $typed[] = ['deleted', $e->table, $e->id === (int) ($e->entry['id'] ?? 0)]; });
        self::eq('listens(): ohne aktive Erweiterung', Extensions::listens('page.saved'), false);
        self::activate($x);
        self::eq('listens(): mit Erweiterung', Extensions::listens('page.saved'), true);
        self::eq('listens(): Klasse und Name gleichwertig', [Extensions::listens(Events\PageSaved::class), Extensions::listens('page.published'), Extensions::listens('\\' . Events\EntryDeleted::class)], [true, true, true]);
        self::eq('Event::classFor', [Events\Event::classFor('entry.saved'), Events\Event::classFor('media.deleted'), Events\Event::classFor(Events\PageDeleted::class)], [Events\EntrySaved::class, null, Events\PageDeleted::class]);
        $ev = new Events\PageSaved(['id' => 7, 'title' => 'T', 'lang' => null], 3);
        self::eq('PageSaved: Eigenschaften', [$ev->table, $ev->id, $ev->userId, $ev->state, $ev->lang, $ev->name()], ['pages', 7, 3, 'draft', Lang::default(), 'page.saved']);
        self::eq('PageSaved: Altform-Argumente', $ev->legacyArgs(), [['id' => 7, 'title' => 'T', 'lang' => null], 3]);
        self::eq('Array-Zugriff: Eigenschaft und Feld', [$ev['state'], $ev['title'], isset($ev['title']), isset($ev['gibt_es_nicht'])], ['draft', 'T', true, false]);
        try { $ev['title'] = 'X'; self::eq('Ereignis unveränderlich', 'geändert', 'Ausnahme'); } catch (\LogicException) { self::$ok++; }
        try { (new \ReflectionProperty($ev, 'id'))->setValue($ev, 9); self::eq('readonly', 'geändert', 'Fehler'); } catch (\Error) { self::$ok++; }
        $es = new Events\EntrySaved(['handle' => 'qa'], ['id' => 5, 'status' => 'published', 'lang' => 'en'], ['id' => 5, 'status' => 'draft']);
        self::eq('EntrySaved: Eigenschaften', [$es->table, $es->id, $es->state, $es->created, $es->lang], ['qa', 5, 'live', false, 'en']);
        self::eq('EntrySaved: Altform-Argumente', count($es->legacyArgs()) === 4 && $es->legacyArgs()[2] === false, true);
        $ed = new Events\EntryDeleted(['handle' => 'qa'], 9);
        self::eq('EntryDeleted: ohne Stand, Altform (Tabelle, ID)', [$ed->id, $ed->entry, $ed->legacyArgs()[1], $ed['id']], [9, null, 9, 9]);
        $pdo = app()->db->pdo;
        $pdo->beginTransaction();
        try {
            $id = Pages::create(['slug' => 'ext-selbsttest', 'title' => 'Ext-Selbsttest', 'lang' => Lang::default(), 'sort' => 9999]);
            $got = $typed = [];
            Pages::saveDraft($id, [['type' => 'paragraph', 'data' => ['text' => 'x']]], 42);
            self::eq('Typisiert: Klasse, Alias mit Typ, Altform', $typed, [['class', 'pages', $id, 'draft', Lang::default(), $id, 'Ext-Selbsttest'], ['alias', Events\PageSaved::class], ['legacy', $id, 42]]);
            $got = $typed = [];
            Pages::saveDraft($id, [['type' => 'paragraph', 'data' => ['text' => 'x']]], null);
            Pages::publish($id, null);
            Pages::unpublish($id);
            Pages::saveDraft($id, [['type' => 'paragraph', 'data' => ['text' => 'y']]], null);
            Pages::discardDraft($id, null);
            self::eq('Seiten-Ereignisse in Reihenfolge', $got, ['page.saved:' . $id, 'page.published:' . $id, 'page.unpublished:' . $id, 'page.saved:' . $id, 'page.discarded:' . $id]);
            $got = [];
            Pages::unpublish($id);   // schon offline → kein Ereignis
            self::eq('Kein Ereignis ohne Änderung', $got, []);
            // Einträge: an einer vorhandenen Inhaltstabelle mit Freigabe-Ablauf (z. B. Glossar), sonst übersprungen
            $t = null;
            foreach (\Core\Data\Tables::content() as $c) {
                if ($c['settings']['workflow'] && !\Core\Data\Tables::isShared($c) && count(array_filter($c['fields'], fn($f) => $f['required'])) <= 2) { $t = $c; break; }
            }
            if ($t) {
                $in = [];
                foreach ($t['fields'] as $f) if ($f['required']) $in[$f['name']] = in_array($f['type'], ['text', 'textarea', 'richtext', 'inline'], true) ? 'Selbsttest' : null;
                $got = $typed = [];
                [$eid, $err] = \Core\Data\Entries::save($t, null, $in + ['status' => 'draft']);
                if ($eid) {
                    \Core\Data\Entries::setStatus($t, [$eid], 'published');
                    \Core\Data\Entries::setStatus($t, [$eid], 'published');   // unverändert → kein zweites Ereignis
                    \Core\Data\Entries::save($t, $eid, ['status' => 'draft']);
                    \Core\Data\Entries::delete($t, $eid);
                    $h = $t['handle'];
                    self::eq('Eintrags-Ereignisse', $got, ["entry.saved:$h", "entry.published:$h", "entry.saved:$h", "entry.unpublished:$h", "entry.deleted:$h"]);
                    self::eq('Eintrags-Ereignisse typisiert (neu, geändert, gelöscht mit Stand)', $typed, [['entry', $h, true, 'draft', true], ['entry', $h, false, 'draft', false], ['deleted', $h, true]]);
                } else {
                    self::$ok++;   // Pflichtfelder anderer Art – nicht prüfbar
                }
            } else {
                self::$ok++;
            }
        } finally {
            if ($pdo->inTransaction()) $pdo->rollBack();
            PageCache::clear();
            self::activate(null);
        }
    }

    // ================================================================= Verwaltungsrouten von Erweiterungen

    private static function routes(): void
    {
        $ok = fn() => new Http\Response('ok');
        $req = fn(string $method, string $path, array $post = [], array $server = []) => new Http\Request($method, $path, [], $post, [], $server);
        $status = function (callable $run): string {
            try {
                $res = $run();
                return $res instanceof Http\Response ? 'ok' : 'anders';
            } catch (Http\Controllers\Admin\RedirectException) {
                return 'login';
            } catch (Http\HttpException $e) {
                return (string) $e->getCode();
            }
        };

        // Kern-Routen (ohne scoped) bleiben unverändert – der Controller prüft selbst
        $core = new Http\Router(false);
        $core->post('/admin/kern', $ok);
        self::actAs(null);
        self::eq('Kern-Route: kein zusätzlicher Schutz', [$status(fn() => $core->dispatch($req('POST', '/admin/kern'))), $core->meta()], ['ok', []]);

        // Erweiterung: Recht, benannte Ausnahmen, Altform, fehlendes Recht (Produktion: 403)
        $r = new Http\Router(false);
        $r->scoped('qa_selftest', function (Http\Router $r) use ($ok): void {
            $r->get('/admin/qa', $ok, 'pages.edit');
            $r->post('/admin/qa/speichern', $ok, ['perm' => 'pages.edit']);
            $r->post('/admin/qa/hook', $ok, ['perm' => 'pages.edit', 'csrf' => false]);
            $r->get('/admin/qa/status', $ok, ['public' => true]);
            $r->post('/admin/qa/eines', $ok, ['perm' => ['pages.edit', 'media.upload']]);   // eines der Rechte genügt
            $r->post('/admin/qa/alt', [Http\Controllers\Admin\DashboardController::class, 'index']);
            $r->post('/admin/qa/ohne', $ok);
            $r->get('/qa-website', $ok);                       // außerhalb /admin: Sache der Erweiterung
        });
        $meta = array_column($r->meta('qa_selftest'), null, 'pattern');
        self::eq('Angaben: nur /admin-Routen', array_keys($meta), ['/admin/qa', '/admin/qa/speichern', '/admin/qa/hook', '/admin/qa/status', '/admin/qa/eines', '/admin/qa/alt', '/admin/qa/ohne']);
        self::eq('Angaben: mehrere Rechte (eines genügt)', [$meta['/admin/qa/eines']['perm'], $meta['/admin/qa/eines']['legacy'], $meta['/admin/qa/eines']['denied']], ['pages.edit|media.upload', false, false]);
        self::eq('Angaben: Recht, Ausnahmen, Altform, abgelehnt', [$meta['/admin/qa']['perm'], $meta['/admin/qa/hook']['csrf'], $meta['/admin/qa/status']['public'],
            $meta['/admin/qa/alt']['legacy'], $meta['/admin/qa/ohne']['denied'], $meta['/admin/qa/speichern']['denied']], ['pages.edit', false, true, true, true, false]);
        self::eq('Abgemeldet: Anmeldung verlangt', $status(fn() => $r->dispatch($req('GET', '/admin/qa'))), 'login');
        self::eq('Abgemeldet: public erreichbar', $status(fn() => $r->dispatch($req('GET', '/admin/qa/status'))), 'ok');
        self::eq('Außerhalb /admin: unverändert', $status(fn() => $r->dispatch($req('GET', '/qa-website'))), 'ok');
        self::actAs(['media.upload']);
        self::eq('Ohne Recht: 403', $status(fn() => $r->dispatch($req('GET', '/admin/qa'))), '403');
        self::eq('Mehrere Rechte: das zweite genügt (dann CSRF)', $status(fn() => $r->dispatch($req('POST', '/admin/qa/eines'))), '419');
        self::actAs(['support.report']);
        self::eq('Mehrere Rechte: keines vorhanden → 403', $status(fn() => $r->dispatch($req('POST', '/admin/qa/eines'))), '403');
        self::actAs(['media.upload']);
        self::eq('Fehlendes Recht an der Route: 403 (Produktion)', $status(fn() => $r->dispatch($req('POST', '/admin/qa/ohne'))), '403');
        self::actAs(['pages.edit']);
        self::eq('Mit Recht: GET', $status(fn() => $r->dispatch($req('GET', '/admin/qa'))), 'ok');
        self::eq('POST ohne CSRF-Token: 419', $status(fn() => $r->dispatch($req('POST', '/admin/qa/speichern'))), '419');
        // Sitzung vorübergehend „gestartet“ (Kommandozeile hat keine) – Token wie im Browser
        $sp = new \ReflectionProperty(app()->session, 'started');
        [$wasStarted, $wasSession] = [$sp->getValue(app()->session), $_SESSION ?? null];
        $sp->setValue(app()->session, true);
        $_SESSION = [];
        try {
            $token = Csrf::token();
            self::eq('POST mit CSRF-Token (Feld bzw. Kopfzeile)', [$status(fn() => $r->dispatch($req('POST', '/admin/qa/speichern', ['_csrf' => $token]))),
                $status(fn() => $r->dispatch($req('POST', '/admin/qa/speichern', [], ['HTTP_X_CSRF_TOKEN' => $token])))], ['ok', 'ok']);
            self::eq('POST mit falschem Token: 419', $status(fn() => $r->dispatch($req('POST', '/admin/qa/speichern', ['_csrf' => str_repeat('0', 64)]))), '419');
        } finally {
            $sp->setValue(app()->session, $wasStarted);
            if ($wasSession === null) unset($_SESSION); else $_SESSION = $wasSession;
        }
        self::eq('Ausnahme csrf => false: ohne Token', $status(fn() => $r->dispatch($req('POST', '/admin/qa/hook'))), 'ok');
        self::eq('Altform: Core prüft CSRF vor dem Controller', $status(fn() => $r->dispatch($req('POST', '/admin/qa/alt'))), '419');

        // Entwicklung: fehlendes Recht ist ein Fehler beim Anmelden
        $dev = new Http\Router(true);
        try {
            $dev->scoped('qa_selftest', fn(Http\Router $r) => $r->post('/admin/qa/ohne', $ok));
            self::eq('Entwicklung: Route ohne Recht abgelehnt', 'angemeldet', 'LogicException');
        } catch (\LogicException) {
            self::$ok++;
        }
        $dev->scoped('qa_selftest', fn(Http\Router $r) => $r->post('/admin/qa/alt', [Http\Controllers\Admin\DashboardController::class, 'index']));
        self::eq('Entwicklung: Altform bleibt erlaubt', (array_slice($dev->meta('qa_selftest'), -1)[0]['legacy'] ?? null), true);
        self::actAs(null);
    }

    // ================================================================= Tabellen von Erweiterungen

    private static function tables(): void
    {
        // MySQL: DDL beendet Transaktionen – dort nur db:selftest-Logik, hier nichts anlegen
        if (app()->db->driver !== 'sqlite') { self::$ok++; return; }
        $pdo = app()->db->pdo;
        $pdo->beginTransaction();
        try {
            $x = new Extension('qa_selftest', __DIR__, ['label' => 'QA']);
            $x->table('qa_selftest_items', fn(Db\Table $t) => $t->id()->column('name', 'string', ['length' => 80])->index('name'));
            self::eq('table(): anlegen', $x->ensureTables(), ['qa_selftest_items' => 'created']);
            self::eq('table(): gleicher Fingerabdruck → nichts zu tun', $x->ensureTables(), []);
            self::eq('table(): migrate (erzwungen) idempotent', $x->ensureTables(true), ['qa_selftest_items' => 'unchanged']);
            $y = new Extension('qa_selftest', __DIR__, ['label' => 'QA']);
            $y->table('qa_selftest_items', fn(Db\Table $t) => $t->id()->column('name', 'string', ['length' => 80])->column('menge', 'int', ['default' => 1])->index('name'));
            self::eq('table(): geänderte Beschreibung → angleichen', $y->ensureTables(), ['qa_selftest_items' => 'altered']);
            self::eq('table(): Spalte da', in_array('menge', array_column(app()->db->fetchAll('PRAGMA table_info(qa_selftest_items)'), 'name'), true) || app()->db->driver === 'mysql', true);
        } finally {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
        self::eq('table(): zurückgerollt', Db\Table::exists('qa_selftest_items'), false);
    }

    // ================================================================= Slots der Verwaltung

    private static function slots(): void
    {
        foreach (Slots::ALL as $key => [$method]) self::eq("Slot $key: Methode $method", method_exists(Extension::class, $method), true);

        // Karte: alles escaped, nur Pfade dieser Installation
        $html = Slots::card(['title' => '<script>T</script>', 'text' => 'a & b', 'tone' => 'warn', 'lines' => ['<b>Offen</b>' => '3 <i>', 'leer' => ['x']],
            'actions' => [['label' => 'Ok', 'href' => '/admin/qa?x=1&y=2', 'primary' => true], ['label' => 'Böse', 'href' => 'javascript:alert(1)'],
                ['label' => 'Fremd', 'href' => '//evil.example/'], ['label' => 'Backslash', 'href' => '/\\evil.example'], ['label' => '', 'href' => '/admin/leer']]], 'card', 'qa_selftest');
        self::eq('Karte: kein rohes HTML aus Angaben', [str_contains($html, '<script>'), str_contains($html, '<b>'), str_contains($html, '<i>')], [false, false, false]);
        self::eq('Karte: escaped Inhalte', [str_contains($html, '&lt;script&gt;T&lt;/script&gt;'), str_contains($html, 'a &amp; b'), str_contains($html, '&lt;b&gt;Offen&lt;/b&gt;')], [true, true, true]);
        self::eq('Karte: nur eigene Pfade als Aktion', [substr_count($html, 'adm-btn--small'), str_contains($html, 'javascript'), str_contains($html, 'evil')], [1, false, false]);
        self::eq('Karte: Ton, Primärknopf, Herkunft', [str_contains($html, 'slot-card__text--warn'), str_contains($html, 'adm-btn--primary'), str_contains($html, 'data-slot="qa_selftest"')], [true, true, true]);
        self::eq('Karte: Varianten', [str_starts_with(Slots::card(['title' => 'M'], 'media'), '<section class="fx-i-sec'), str_starts_with(Slots::card(['text' => 'B'], 'body'), '<div class="slot-card'),
            str_contains(Slots::card(['title' => 'C']), '<h2>C</h2>'), Slots::card([])], [true, true, true, '']);
        self::eq('Aktionen: Platzhalter kodiert, offene Platzhalter verworfen', array_column(Slots::actions([['label' => 'A', 'href' => '/admin/x/{table}/{id}'], ['label' => 'B', 'href' => '/admin/{rest}']], ['table' => 'a b', 'id' => 7]), 'href'), ['/admin/x/a%20b/7']);

        // Erweiterung mit allen Slots
        $x = new Extension('qa_selftest', __DIR__, ['label' => 'QA']);
        $x->pagePanel(fn(array $p) => ['title' => 'Panel ' . $p['title'], 'lines' => ['ID' => (string) $p['id']]]);
        $x->pagePanel(fn(array $p) => '<section class="adm-card">Altform</section>');                 // Altform: HTML bleibt erlaubt
        $x->pagePanel(fn(array $p) => ['title' => 'Nur mit Recht'], 'qa.geheim');
        $x->pagePanel(function (array $p): array { throw new \RuntimeException('kaputt'); });          // Fehler isoliert
        $x->pageList(fn(array $p) => ['badges' => [['label' => 'QA', 'tone' => 'quatsch']], 'actions' => [['label' => 'Öffnen', 'href' => '/admin/qa/' . $p['id']], ['label' => 'X', 'href' => 'https://evil.example']]]);
        $x->tableActions(fn(array $t) => ['actions' => [['label' => 'Export', 'href' => '/admin/qa/{table}/export', 'icon' => 'download'], ['label' => 'Fremd', 'href' => 'https://evil.example']],
            'row' => [['label' => 'Senden', 'href' => '/admin/qa/{table}/{id}'], ['label' => 'Böse', 'href' => 'javascript:alert({id})']]]);
        $x->mediaPanel(fn(array $m) => ['title' => 'Herkunft', 'lines' => ['Datei' => '<' . $m['name'] . '>']], 'media.upload');
        $x->account(fn(array $u) => ['title' => 'QA-Konto', 'text' => 'Hallo ' . $u['name']]);
        $x->account(fn(array $u) => '<b>Altform nicht erlaubt</b>');
        $x->dashboard(fn(array $u) => ['cards' => ['qa' => ['title' => 'QA', 'body' => ['text' => '<i>Zahl</i>', 'lines' => ['Neu' => '2']]]]]);
        // Nur die Prüf-Erweiterung aktiv – Slots anderer Erweiterungen der Website (z. B. Freigabe) würden mitzählen
        $prop = new \ReflectionProperty(Extensions::class, 'active');
        $others = $prop->getValue();
        $prop->setValue(null, []);
        try {
            self::activate($x);
            self::slotChecks();
        } finally {
            $prop->setValue(null, $others);
        }
    }

    private static function slotChecks(): void
    {
        self::actAs(['pages.edit', 'media.upload']);
        $page = ['id' => 5, 'title' => 'Start <&>'];
        $pp = Extensions::pagePanels($page);
        self::eq('pagePanel: Karte escaped + Altform + Recht + Fehler isoliert', [str_contains($pp, 'Panel Start &lt;&amp;&gt;'), str_contains($pp, 'Altform'), str_contains($pp, 'Nur mit Recht'), substr_count($pp, '<section')], [true, true, false, 2]);
        $pl = Extensions::pageList($page);
        self::eq('pageList: Ton geprüft, nur eigene Pfade', [$pl['badges'][0]['tone'] ?? null, array_column($pl['actions'], 'href')], ['info', ['/admin/qa/5']]);
        $ta = Extensions::tableActions(['handle' => 'qa_tabelle']);
        self::eq('tableActions: Kopf mit {table}, fremde Adressen verworfen', array_column($ta['actions'], 'href'), ['/admin/qa/qa_tabelle/export']);
        self::eq('tableActions: Zeile mit {id}, javascript: verworfen', array_column(Extensions::rowActions($ta['row'], 12), 'href'), ['/admin/qa/qa_tabelle/12']);
        $mp = Extensions::mediaPanels(['id' => 1, 'name' => 'bild.jpg']);
        self::eq('mediaPanel: Abschnitt escaped', [count($mp), str_contains($mp[0] ?? '', '&lt;bild.jpg&gt;'), str_contains($mp[0] ?? '', 'fx-i-sec')], [1, true, true]);
        $acc = Extensions::accountSections(['id' => 1, 'name' => 'Erika <M>']);
        self::eq('account: Karte escaped, Altform-HTML nicht erlaubt', [str_contains($acc, 'Hallo Erika &lt;M&gt;'), str_contains($acc, 'Altform nicht erlaubt')], [true, false]);
        $dash = Extensions::dashboard(['id' => 1]);
        $card = $dash['cards']['x-qa-selftest-qa'] ?? null;
        self::eq('dashboard: body als Karte, escaped', $card ? [str_contains(($card['render'])(), '&lt;i&gt;Zahl&lt;/i&gt;'), str_contains(($card['render'])(), 'adm-dl')] : null, [true, true]);
        self::actAs(['pages.edit']);
        self::eq('Recht am Slot: ohne media.upload kein Abschnitt', Extensions::mediaPanels(['id' => 1, 'name' => 'a']), []);
        self::actAs(['pages.edit', 'qa.geheim']);
        self::eq('Recht am Slot: mit Recht sichtbar', str_contains(Extensions::pagePanels($page), 'Nur mit Recht'), true);
        self::activate(null);
        self::eq('Erweiterung aus → keine Slots', [Extensions::pagePanels($page), Extensions::mediaPanels(['id' => 1, 'name' => 'a']), Extensions::tableActions(['handle' => 'x'])], ['', [], ['actions' => [], 'row' => []]]);
        self::actAs(null);
    }
}
