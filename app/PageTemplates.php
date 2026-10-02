<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Seitenvorlagen für die Redaktion – eigene Seiten wie die Sonderseiten (type = 'template', template_for = '@page'):
 * keine Adresse für Besucher, nicht im Seitenbaum, Menü, Sitemap, Suche, Entwürfe oder Linkauswahl. Anlegen, gestalten,
 * benennen und anordnen nur mit „system.manage“ (Einrichtung › Website › Seitenvorlagen, /admin/seitenvorlagen); bearbeitet wird im
 * normalen Block-Editor unter /_seitenvorlage/{id}?edit=1. Die Redaktion wählt beim Anlegen einer Seite „Leere Seite“ oder
 * eine Vorlage (passend zur übergeordneten Seite vorgewählt) und bekommt eine Kopie der Blöcke mit neuen IDs.
 * Reihenfolge, Name, Symbol, Beschreibung und „Vorschlagen unter“ stehen in der Einstellung sys.page_templates
 * ([{label, page, icon, description, parents}], page = ID der Vorlagenseite).
 */
final class PageTemplates
{
    public const KEY = 'sys.page_templates';
    public const MARK = '@page';
    public const PATH = '_seitenvorlage';

    public static function isTemplatePage(?array $page): bool
    {
        return $page !== null && ($page['type'] ?? '') === 'template' && ($page['template_for'] ?? '') === self::MARK;
    }

    /** Alle Vorlagenseiten (auch nicht mehr gelistete) */
    public static function templatePages(): array
    {
        return app()->db->fetchAll("SELECT * FROM pages WHERE type = 'template' AND template_for = ? ORDER BY title", [self::MARK]);
    }

    /** Bearbeiten im Block-Editor (nur Administration) */
    public static function editUrl(int $pageId): string
    {
        return url('/' . self::PATH . '/' . $pageId) . '?edit=1';
    }

    /** Formular der Verwaltungsseite (ein Repeater – Reihenfolge per ↑/↓) */
    public static function fields(): array
    {
        $opts = [];
        foreach (self::templatePages() as $p) $opts[(string) $p['id']] = (string) $p['title'];
        return [[
            'name' => 'templates', 'label' => __('Vorlagen'), 'type' => 'repeater', 'item_label' => __('Vorlage'), 'title_field' => 'label',
            'help' => __('In dieser Reihenfolge erscheinen die Vorlagen beim Anlegen einer Seite. Eine entfernte Vorlage wird beim Speichern gelöscht.'),
            'fields' => [
                ['name' => 'label', 'label' => __('Name'), 'type' => 'text', 'required' => true, 'max' => 60, 'width' => 'half', 'placeholder' => __('z. B. Leistung, Stellenanzeige, Veranstaltung')],
                ['name' => 'icon', 'label' => __('Symbol (optional)'), 'type' => 'icon', 'width' => 'half'],
                ['name' => 'description', 'label' => __('Beschreibung für die Redaktion (optional)'), 'type' => 'textarea', 'rows' => 2, 'max' => 200],
                ['name' => 'parents', 'label' => __('Vorschlagen unter (optional)'), 'type' => 'pages',
                    'help' => __('Wird eine neue Seite unter einer dieser Seiten angelegt, ist diese Vorlage vorgewählt.')],
                ['name' => 'page', 'label' => __('Vorlagenseite'), 'type' => 'select', 'required' => true, 'options' => $opts,
                    'help' => __('Die Seite mit den Blöcken dieser Vorlage – gestalten über „Blöcke bearbeiten“ rechts.')],
            ],
        ]];
    }

    /** Vorlagen in Reihenfolge: [['i', 'label', 'description', 'icon', 'page', 'parents', 'source' => Vorlagenseite], …] */
    public static function all(): array
    {
        self::migrate();
        $out = [];
        foreach ((array) app()->settings->get(self::KEY, []) as $i => $t) {
            if (!is_array($t) || empty($t['page']) || !($p = Pages::find((int) $t['page'])) || !self::isTemplatePage($p)) continue;
            $out[] = ['i' => (int) $i, 'label' => trim((string) ($t['label'] ?? '')) ?: (string) $p['title'], 'description' => trim((string) ($t['description'] ?? '')),
                'icon' => trim((string) ($t['icon'] ?? '')), 'page' => (int) $p['id'], 'parents' => array_values((array) ($t['parents'] ?? [])), 'source' => $p];
        }
        return $out;
    }

    /** Gespeicherte Rohwerte fürs Formular */
    public static function values(): array
    {
        self::migrate();
        return ['templates' => array_values(array_map(fn($t) => ['page' => (string) ($t['page'] ?? '')] + $t,
            array_filter((array) app()->settings->get(self::KEY, []), 'is_array')))];
    }

    /** Liste speichern; Titel der Vorlagenseiten = Name; nicht mehr gelistete Vorlagenseiten löschen */
    public static function save(array $templates): void
    {
        $templates = array_values(array_filter($templates, fn($t) => is_array($t) && !empty($t['page'])));
        app()->settings->set(self::KEY, $templates);
        $keep = [];
        foreach ($templates as $t) {
            $id = (int) $t['page'];
            $keep[$id] = true;
            $label = trim((string) ($t['label'] ?? ''));
            if ($label !== '') app()->db->query("UPDATE pages SET title = ? WHERE id = ? AND type = 'template' AND template_for = ?", [mb_substr($label, 0, 120), $id, self::MARK]);
        }
        foreach (self::templatePages() as $p) {
            if (isset($keep[(int) $p['id']])) continue;
            app()->db->query('DELETE FROM revisions WHERE page_id = ?', [(int) $p['id']]);
            app()->db->query('DELETE FROM pages WHERE id = ?', [(int) $p['id']]);
        }
    }

    /**
     * Neue Vorlage anlegen – leer oder als Kopie einer Seite (Arbeitsstand, neue Block-IDs); hängt sie ans Ende der Liste.
     * @return int ID der Vorlagenseite
     */
    public static function create(string $label, ?int $fromPage = null): int
    {
        $label = mb_substr(trim($label), 0, 60) ?: __('Neue Vorlage');
        $src = $fromPage ? Pages::find($fromPage) : null;
        $blocks = $src ? self::freshIds(Pages::blocks($src, true)) : [];
        $id = Pages::create(['slug' => self::PATH . '-' . bin2hex(random_bytes(4)), 'title' => $label, 'type' => 'template', 'template_for' => self::MARK,
            'status' => 'published', 'noindex' => 1, 'menu' => 0, 'lang' => null], Pages::sanitizeBlocks($blocks));
        $list = (array) app()->settings->get(self::KEY, []);
        $list[] = ['label' => $label, 'page' => $id, 'icon' => '', 'description' => '', 'parents' => []];
        app()->settings->set(self::KEY, array_values($list));
        return $id;
    }

    /** Vorlage, die unter dieser übergeordneten Seite vorgeschlagen wird (Index) oder null */
    public static function suggested(?int $parentId): ?int
    {
        $parent = $parentId ? Pages::find($parentId) : null;
        if (!$parent) return null;
        foreach (self::all() as $t) if ($t['parents'] && PagePicker::matches($t['parents'], $parent)) return $t['i'];
        return null;
    }

    /** Blöcke der Vorlage mit neuen IDs – auch in Spalten des Blocks „Layout“ */
    public static function blocks(int $index): array
    {
        foreach (self::all() as $t) {
            if ($t['i'] === $index) return self::freshIds(Pages::blocks($t['source'], true));
        }
        return [];
    }

    /** Frühere Fassung: Vorlage verwies auf eine normale Seite → einmalig als Vorlagenseite kopieren (die Seite bleibt unverändert) */
    private static function migrate(): void
    {
        $list = (array) app()->settings->get(self::KEY, []);
        $changed = false;
        foreach ($list as &$t) {
            if (!is_array($t) || empty($t['page']) || !($p = Pages::find((int) $t['page'])) || ($p['type'] ?? 'page') !== 'page') continue;
            $t['page'] = Pages::create(['slug' => self::PATH . '-' . bin2hex(random_bytes(4)), 'title' => trim((string) ($t['label'] ?? '')) ?: (string) $p['title'],
                'type' => 'template', 'template_for' => self::MARK, 'status' => 'published', 'noindex' => 1, 'menu' => 0, 'lang' => null],
                Pages::sanitizeBlocks(self::freshIds(Pages::blocks($p, true))));
            $changed = true;
        }
        unset($t);
        if ($changed) app()->settings->set(self::KEY, array_values($list));
    }

    private static function freshIds(array $blocks): array
    {
        foreach ($blocks as &$b) {
            if (!is_array($b)) continue;
            $b['id'] = substr(bin2hex(random_bytes(6)), 0, 10);
            if (!empty($b['data']['columns']) && is_array($b['data']['columns'])) {
                foreach ($b['data']['columns'] as &$c) {
                    if (isset($c['blocks']) && is_array($c['blocks'])) $c['blocks'] = self::freshIds($c['blocks']);
                }
                unset($c);
            }
        }
        unset($b);
        return $blocks;
    }
}
