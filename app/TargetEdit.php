<?php
declare(strict_types=1);

namespace Core;

use Core\Data\Entries;
use Core\Data\EntryEdit;
use Core\Data\Tables;

/**
 * „Ziel bearbeiten“: kleine Aktion an Karten und Kacheln, die auf eine andere Seite oder einen Eintrag zeigen
 * (Leistungs-Kacheln, Team-Karten, Datenlisten …). Nur für die angemeldete Redaktion mit Recht – Besucher, der
 * Seiten-Cache und die Ansicht „wie Besucher“ (?live=1) bekommen nichts (keine Abfrage, kein Markup).
 *
 * Vorlagen:  <?= edit_link('page:12') ?>   <?= edit_link($it['link'], $it['title']) ?>   <?= $b->targetEdit("entry:arbeiten:{$e['id']}") ?>
 *
 * Verweise wie im Feld „link“ (Core\Links): page:ID (auch page:ID#anker), entry:{tabelle}:{id} – oder eine Adresse
 * dieser Website („/leistungen/marke“, „/leistungen#web“, „https://eigene-domain/…“, Detailseiten „/{URL-Basis}/{slug}“),
 * die über den Seitenbaum bzw. die URL-Basis der Tabelle aufgelöst wird. Fremde Adressen, mailto:, tel:, Anker der
 * aktuellen Seite und Sonderziele des Kits ergeben nichts.
 *
 *  - Seite (Recht pages.edit): öffnet die Seite im Seiten-Editor (…?edit=1). Im Editor mit ungespeicherten Änderungen fragt
 *    die Werkzeugleiste wie beim Verlassen („Speichern & beenden“ · „Verwerfen“ · „Weiter bearbeiten“), resources/js/_target_edit.js.
 *  - Eintrag (data.edit für die Tabelle, EntryEdit::canEdit): öffnet die Seitenleiste „Eintrag bearbeiten“ an Ort und Stelle
 *    (die Seite bleibt offen, nichts geht verloren); ohne Skript bzw. mit Strg/⌘ die Detailseite im Bearbeiten-Modus
 *    (#cms-bearbeiten), ohne Detailseite das Formular in der Verwaltung.
 *
 * Aussehen: resources/css/editor.css (.cms-target-edit, oben rechts in der Karte, über dem Karten-Link ::after; erscheint bei
 * Zeiger/Fokus, auf Touch-Geräten und im Seiten-Editor immer).
 */
final class TargetEdit
{
    /** Nur für den Selbsttest: Rechte und Anmeldung vorgeben (null = echte Prüfung) */
    public static ?\Closure $testCan = null;
    public static ?bool $testActive = null;

    /** @var array<string, ?array> aufgelöste Verweise je Anfrage */
    private static array $memo = [];

    /** Zeigt die Website gerade Bearbeiten-Aktionen? (angemeldet, nicht „wie Besucher“ – im Seiten-Editor immer) */
    public static function active(): bool
    {
        if (self::$testActive !== null) return self::$testActive;
        return (app()->editing || app()->dataEdit) && app()->auth->check();
    }

    /**
     * Verweis bzw. Adresse → Ziel. ['kind' => 'page', 'ref' => 'page:12', 'page' => […]] bzw.
     * ['kind' => 'entry', 'ref' => 'entry:news:5', 'table' => […], 'entry' => […]]; null = kein internes Ziel.
     */
    public static function resolve(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '' || $ref[0] === '#') return null;
        if (array_key_exists($ref, self::$memo)) return self::$memo[$ref];
        return self::$memo[$ref] = self::lookup($ref);
    }

    private static function lookup(string $ref): ?array
    {
        if (preg_match('~^page:(\d+)(#[\w-]{1,80})?$~', $ref, $m)) {
            $p = Pages::find((int) $m[1]);
            return $p ? ['kind' => 'page', 'ref' => 'page:' . (int) $p['id'], 'page' => $p] : null;
        }
        if (preg_match('~^entry:([a-z][a-z0-9_]{0,40}):(\d+)$~', $ref, $m)) {
            $t = Tables::findContent($m[1]);
            $e = $t && !Tables::isInbox($t) ? Entries::find($t, (int) $m[2]) : null;
            return $e ? ['kind' => 'entry', 'ref' => 'entry:' . $t['handle'] . ':' . (int) $e['id'], 'table' => $t, 'entry' => $e] : null;
        }
        if (Links::isRef($ref)) return null;   // media:… – Dateien haben kein Bearbeiten-Ziel
        $path = self::localPath($ref);
        return $path === null ? null : self::byPath($path);
    }

    /** Pfad einer Adresse dieser Website (ohne Basis, Anfrage, Anker) – null für fremde Adressen und Sonderziele */
    private static function localPath(string $href): ?string
    {
        if (preg_match('~^(mailto|tel|sms|javascript|data):~i', $href)) return null;
        $u = parse_url($href);
        if ($u === false) return null;
        if (isset($u['host'])) {
            $own = strtolower((string) (app()->request?->host() ?? parse_url(site_url(), PHP_URL_HOST) ?? ''));
            if ($own === '' || strtolower($u['host']) !== preg_replace('~:\d+$~', '', $own)) return null;
        } elseif (!isset($u['path']) || ($u['path'][0] ?? '') !== '/' || str_starts_with($href, '//')) {
            return null;   // relative Angaben, Sonderziele des Kits („phone“, „email“)
        }
        $path = (string) ($u['path'] ?? '/');
        $base = base_path();
        if ($base !== '' && str_starts_with($path, $base . '/')) $path = substr($path, strlen($base));
        if (str_starts_with($path, '/index.php/')) $path = substr($path, 10);
        return '/' . trim(rawurldecode($path), '/');
    }

    private static function byPath(string $path): ?array
    {
        $lang = Lang::current();
        $rest = trim($path, '/');
        $first = explode('/', $rest, 2)[0];
        if (Lang::multi() && $first !== Lang::default() && Lang::valid($first)) {
            $lang = $first;
            $rest = trim(substr($rest, strlen($first)), '/');
        }
        if ($rest === '') {
            $home = Pages::home();
            if ($home && $lang !== Lang::norm($home['lang'] ?? null)) $home = Pages::translations($home)[$lang] ?? $home;
            return $home ? ['kind' => 'page', 'ref' => 'page:' . (int) $home['id'], 'page' => $home] : null;
        }
        if ($p = Pages::byPath($rest, $lang)) return ['kind' => 'page', 'ref' => 'page:' . (int) $p['id'], 'page' => $p];
        // Detailseite: /{URL-Basis der Tabelle}/{slug} (wie SiteController)
        if (preg_match('~^(.+)/([^/]+)$~', $rest, $m) && ($t = Tables::byRoute($m[1])) && !empty($t['settings']['detail_page_id'])
            && ($e = Entries::bySlug($t, $m[2], false, $lang))) {
            return ['kind' => 'entry', 'ref' => 'entry:' . $t['handle'] . ':' . (int) $e['id'], 'table' => $t, 'entry' => $e];
        }
        return null;
    }

    private static function can(string $perm, ?string $table = null): bool
    {
        return self::$testCan ? (bool) (self::$testCan)($perm, $table) : can($perm, $table);
    }

    /**
     * Ziel samt Rechteprüfung und Adressen – null ohne Recht bzw. ohne internes Ziel.
     * @return ?array{kind: string, ref: string, title: string, label: string, href: string, panel: string}
     */
    public static function target(string $ref, ?string $label = null): ?array
    {
        $x = self::resolve($ref);
        if (!$x) return null;
        if ($x['kind'] === 'page') {
            if (!self::can('pages.edit')) return null;
            $title = trim($label ?? '') !== '' ? strip_emphasis(trim((string) $label)) : (string) $x['page']['title'];
            return ['kind' => 'page', 'ref' => $x['ref'], 'title' => $title,
                'label' => __('Seite „{title}“ bearbeiten', ['title' => $title]),
                'href' => Pages::url($x['page']) . '?edit=1', 'panel' => ''];
        }
        $t = $x['table'];
        $e = $x['entry'];
        if (self::$testCan ? !self::can('data.edit', $t['handle']) : !EntryEdit::canEdit($t, $e)) return null;
        $title = trim($label ?? '') !== '' ? strip_emphasis(trim((string) $label)) : Entries::title($t, $e);
        $detail = Entries::url($t, $e);
        return ['kind' => 'entry', 'ref' => $x['ref'], 'title' => $title,
            'label' => __('Eintrag „{title}“ bearbeiten', ['title' => $title]),
            'href' => $detail !== null ? $detail . '#cms-bearbeiten' : EntryEdit::adminUrl($t, $e),
            'panel' => EntryEdit::endpoint($t, $e)];
    }

    /**
     * Die Aktion als HTML (leer für Besucher, ohne Recht und ohne internes Ziel).
     * $label: Titel für die Ansage („Seite „…“ bearbeiten“), sonst der Titel der Seite bzw. des Eintrags.
     * $o: class (zusätzliche Klasse, z. B. für die Lage in einer Kit-Karte)
     */
    public static function html(?string $ref, ?string $label = null, array $o = []): string
    {
        if ($ref === null || trim($ref) === '' || !self::active()) return '';
        $x = self::target($ref, $label);
        if (!$x) return '';
        $cls = 'cms-target-edit' . (!empty($o['class']) ? ' ' . preg_replace('~[^\w -]~', '', (string) $o['class']) : '');
        return '<a class="' . e($cls) . '" href="' . e($x['href']) . '" data-cms-target="' . e($x['kind']) . '"'
            . ($x['panel'] !== '' ? ' data-entry-edit="' . e($x['panel']) . '"' : '')
            . ' aria-label="' . e($x['label']) . '" title="' . e($x['label']) . '" contenteditable="false">'
            . '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path d="M11.1 2.2a1.6 1.6 0 0 1 2.3 0l.4.4a1.6 1.6 0 0 1 0 2.3L6 12.7l-3.3.8.8-3.3z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>'
            . '<span>' . e(__('Bearbeiten')) . '</span></a>';
    }

    /** Zwischenspeicher leeren (Selbsttest) */
    public static function reset(): void
    {
        self::$memo = [];
    }

    /**
     * Selbsttest (targetedit:selftest) in einer Transaktion, die zurückgerollt wird: Auflösen (page:ID, Anker, Pfad, Pfad mit
     * Anker/Anfrage, eigene Domain, fremde Domain, mailto/tel, Sonderziele, Detailseite), keine Ausgabe für Besucher und
     * ohne Recht, Seiten nur mit pages.edit, Einträge nur mit data.edit der Tabelle, Bezeichnung und Adressen.
     */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $pdo = app()->db->pdo;
        $pdo->beginTransaction();
        $prev = [app()->editing, app()->dataEdit];
        try {
            self::reset();
            $lang = Lang::default();
            $parent = Pages::create(['slug' => 'ziel-selbsttest', 'title' => 'Zielselbsttest', 'status' => 'published', 'lang' => $lang, 'sort' => 9000]);
            $child = Pages::create(['slug' => 'marke', 'title' => 'Marke & Corporate Design', 'parent_id' => $parent, 'status' => 'published', 'lang' => $lang, 'sort' => 1]);
            $host = (string) (app()->request?->host() ?? parse_url(site_url(), PHP_URL_HOST) ?? '');

            // Auflösen
            $eq('page:ID', self::resolve('page:' . $child)['ref'] ?? null, 'page:' . $child);
            $eq('page:ID#anker', self::resolve('page:' . $child . '#oben')['ref'] ?? null, 'page:' . $child);
            $eq('Pfad', self::resolve('/ziel-selbsttest/marke')['ref'] ?? null, 'page:' . $child);
            $eq('Pfad mit Schrägstrich am Ende', self::resolve('/ziel-selbsttest/marke/')['ref'] ?? null, 'page:' . $child);
            $eq('Pfad mit Anker', self::resolve('/ziel-selbsttest#gestaltung')['ref'] ?? null, 'page:' . $parent);
            $eq('Pfad mit Anfrage', self::resolve('/ziel-selbsttest/marke?x=1')['ref'] ?? null, 'page:' . $child);
            if ($host !== '') $eq('Adresse der eigenen Domain', self::resolve('https://' . preg_replace('~:\d+$~', '', $host) . '/ziel-selbsttest/marke')['ref'] ?? null, 'page:' . $child);
            else $ok++;
            $eq('Startseite', self::resolve('/')['kind'] ?? null, Pages::home() ? 'page' : null);
            $eq('fremde Domain', self::resolve('https://example.org/ziel-selbsttest/marke'), null);
            $eq('protokollrelativ', self::resolve('//example.org/ziel-selbsttest'), null);
            $eq('mailto', self::resolve('mailto:info@example.org'), null);
            $eq('tel', self::resolve('tel:+49123'), null);
            $eq('Anker der Seite', self::resolve('#kontakt'), null);
            $eq('Sonderziel des Kits', self::resolve('phone'), null);
            $eq('unbekannter Pfad', self::resolve('/gibt-es-nicht-' . bin2hex(random_bytes(3))), null);
            $eq('gelöschte Seite', self::resolve('page:999999999'), null);
            $eq('Datei', self::resolve('media:1'), null);
            $eq('leerer Verweis', self::resolve(''), null);

            // Einträge: erste Inhaltstabelle mit Einträgen (Detailseite, falls vorhanden)
            $entry = null;
            foreach (Tables::content() as $t) {
                if (Tables::isInbox($t)) continue;
                $rows = Entries::query($t, ['limit' => 1]);
                if ($rows) { $entry = [$t, $rows[0]]; break; }
            }
            if ($entry) {
                [$t, $e] = $entry;
                $ref = 'entry:' . $t['handle'] . ':' . $e['id'];
                $eq('entry:tabelle:id', self::resolve($ref)['ref'] ?? null, $ref);
                $eq('Eintrag gelöscht/unbekannt', self::resolve('entry:' . $t['handle'] . ':999999999'), null);
                if (($u = Entries::url($t, $e)) !== null) {
                    $eq('Adresse der Detailseite → Eintrag', self::resolve($u)['ref'] ?? null, $ref);
                } else $ok++;
            } else {
                $ok += 3;   // keine Einträge – nichts zu prüfen
            }

            // Ausgabe: Besucher nie, auch nicht mit Recht
            self::$testCan = fn(string $perm, ?string $table) => true;
            self::$testActive = false;
            $eq('Besucher: keine Ausgabe', self::html('page:' . $child), '');
            app()->editing = false; app()->dataEdit = false; self::$testActive = null;
            $eq('Nicht angemeldet, nicht im Editor: keine Ausgabe', self::html('page:' . $child), '');
            self::$testActive = true;
            $h = self::html('page:' . $child);
            $eq('Seite: Link zum Seiten-Editor', str_contains($h, 'href="' . e(Pages::url(Pages::find($child)) . '?edit=1') . '"'), true);
            $eq('Seite: Bezeichnung mit Titel', str_contains($h, 'aria-label="' . e(__('Seite „{title}“ bearbeiten', ['title' => 'Marke & Corporate Design'])) . '"'), true);
            $eq('Seite: sichtbarer Text', str_contains($h, '>' . e(__('Bearbeiten')) . '</span>'), true);
            $eq('Seite: keine Seitenleiste', str_contains($h, 'data-entry-edit'), false);
            $eq('Seite: eigene Bezeichnung (ohne *Betonung*)', self::target('page:' . $child, '*Marke* neu')['title'] ?? null, 'Marke neu');
            $eq('Fremde Adresse: keine Ausgabe', self::html('https://example.org/'), '');
            $eq('Leerer Verweis: keine Ausgabe', self::html(''), '');
            $eq('null: keine Ausgabe', self::html(null), '');
            // Rechte
            self::$testCan = fn(string $perm, ?string $table) => $perm !== 'pages.edit';
            $eq('Seite ohne pages.edit: keine Ausgabe', self::html('page:' . $child), '');
            if ($entry) {
                [$t, $e] = $entry;
                $ref = 'entry:' . $t['handle'] . ':' . $e['id'];
                self::$testCan = fn(string $perm, ?string $table) => $perm === 'data.edit' && $table === $t['handle'];
                $h = self::html($ref);
                $eq('Eintrag mit data.edit: Seitenleiste', str_contains($h, 'data-entry-edit="' . e(EntryEdit::endpoint($t, $e)) . '"'), true);
                $want = Entries::url($t, $e) !== null ? Entries::url($t, $e) . '#cms-bearbeiten' : EntryEdit::adminUrl($t, $e);
                $eq('Eintrag: Rückfall-Adresse (Detailseite bzw. Verwaltung)', str_contains($h, 'href="' . e($want) . '"'), true);
                $eq('Eintrag: Bezeichnung', str_contains($h, e(__('Eintrag „{title}“ bearbeiten', ['title' => Entries::title($t, $e)]))), true);
                self::$testCan = fn(string $perm, ?string $table) => $perm === 'data.edit' && $table === 'andere_tabelle';
                $eq('Eintrag ohne data.edit für diese Tabelle: keine Ausgabe', self::html($ref), '');
                self::$testCan = fn(string $perm, ?string $table) => $perm === 'pages.edit';
                $eq('Eintrag nur mit pages.edit: keine Ausgabe', self::html($ref), '');
            } else {
                $ok += 5;
            }
        } catch (\Throwable $ex) {
            $fails[] = 'Ausnahme: ' . $ex->getMessage() . ' (' . basename($ex->getFile()) . ':' . $ex->getLine() . ')';
        } finally {
            self::$testCan = null;
            self::$testActive = null;
            [app()->editing, app()->dataEdit] = $prev;
            self::reset();
            if ($pdo->inTransaction()) $pdo->rollBack();
            PageCache::clear();
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
