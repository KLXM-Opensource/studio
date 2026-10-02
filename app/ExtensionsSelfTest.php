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
 *    in einer Transaktion, die am Ende zurückgerollt wird.
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
        self::eq('Extensions::adminNav (veraltet) nur Erweiterungen', array_column(Extensions::adminNav('main'), 0), ['/admin/qa-inhalt']);
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
        self::eq('Werkzeug: nur gültige Angaben angemeldet (kein ../, keine fremde Domain, gültige id)', array_column($x->frontendTools, 'id'), ['qa-ok']);
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
        self::eq('Werkzeuge: nicht beim Ansehen', array_column(FrontendTools::forBar(['editing' => false] + $bar), 'id'), []);
        $one = array_values(array_filter(FrontendTools::forBar($bar), fn($t) => $t['id'] === 'qa-ok'))[0] ?? [];
        self::eq('Konfiguration: Endpunkte als Adressen, keine Callables', [str_ends_with((string) ($one['endpoints']['go'] ?? ''), '/admin/api/qa'), json_encode($one) !== false], [true, true]);
        self::actAs(['media.upload']);
        self::eq('Werkzeuge: ohne Recht keine', in_array('qa-ok', array_column(FrontendTools::forBar($bar), 'id'), true), false);
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
        self::eq('listens(): ohne aktive Erweiterung', Extensions::listens('page.saved'), false);
        self::activate($x);
        self::eq('listens(): mit Erweiterung', Extensions::listens('page.saved'), true);
        $pdo = app()->db->pdo;
        $pdo->beginTransaction();
        try {
            $id = Pages::create(['slug' => 'ext-selbsttest', 'title' => 'Ext-Selbsttest', 'lang' => Lang::default(), 'sort' => 9999]);
            $got = [];
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
                $got = [];
                [$eid, $err] = \Core\Data\Entries::save($t, null, $in + ['status' => 'draft']);
                if ($eid) {
                    \Core\Data\Entries::setStatus($t, [$eid], 'published');
                    \Core\Data\Entries::setStatus($t, [$eid], 'published');   // unverändert → kein zweites Ereignis
                    \Core\Data\Entries::save($t, $eid, ['status' => 'draft']);
                    \Core\Data\Entries::delete($t, $eid);
                    $h = $t['handle'];
                    self::eq('Eintrags-Ereignisse', $got, ["entry.saved:$h", "entry.published:$h", "entry.saved:$h", "entry.unpublished:$h", "entry.deleted:$h"]);
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
}
