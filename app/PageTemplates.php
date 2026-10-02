<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Seitenvorlagen für die Redaktion: Die Administration bietet Seiten als Vorlage an (Name, Beschreibung, Quellseite,
 * „vorschlagen unter“ bestimmten Bereichen) und legt die Reihenfolge fest – Werkzeuge → Seitenvorlagen (/admin/seitenvorlagen).
 * Beim Anlegen einer Seite wählt die Redaktion „Leere Seite“ oder eine Vorlage; passt „vorschlagen unter“ zur gewählten
 * übergeordneten Seite, ist die Vorlage vorgewählt. Übernommen werden die Blöcke der Quellseite (Arbeitsstand, neue Block-IDs).
 * Die Quellseite bleibt eine normale Seite (am besten offline, z. B. unter „Vorlagen“) – Änderungen dort gelten für neue Seiten.
 * Gespeichert in der Einstellung sys.page_templates (Liste in Reihenfolge).
 */
final class PageTemplates
{
    public const KEY = 'sys.page_templates';

    /** Formular der Verwaltungsseite (ein Repeater – Reihenfolge per ↑/↓) */
    public static function fields(): array
    {
        return [[
            'name' => 'templates', 'label' => __('Vorlagen'), 'type' => 'repeater', 'item_label' => __('Vorlage'), 'title_field' => 'label',
            'add_label' => __('Vorlage hinzufügen'),
            'help' => __('In dieser Reihenfolge erscheinen die Vorlagen beim Anlegen einer Seite. Die Blöcke kommen aus der Quellseite – Änderungen dort gelten für alle neuen Seiten.'),
            'fields' => [
                ['name' => 'label', 'label' => __('Name'), 'type' => 'text', 'required' => true, 'max' => 60, 'width' => 'half', 'placeholder' => __('z. B. Leistung, Stellenanzeige, Veranstaltung')],
                ['name' => 'page', 'label' => __('Quellseite'), 'type' => 'page', 'required' => true, 'width' => 'half',
                    'help' => __('Seite mit dem gewünschten Aufbau – am besten offline, z. B. unter einer Seite „Vorlagen“.')],
                ['name' => 'icon', 'label' => __('Symbol (optional)'), 'type' => 'icon', 'width' => 'half'],
                ['name' => 'description', 'label' => __('Beschreibung für die Redaktion (optional)'), 'type' => 'textarea', 'rows' => 2, 'max' => 200],
                ['name' => 'parents', 'label' => __('Vorschlagen unter (optional)'), 'type' => 'pages',
                    'help' => __('Wird eine neue Seite unter einer dieser Seiten angelegt, ist diese Vorlage vorgewählt.')],
            ],
        ]];
    }

    /** Alle Vorlagen in Reihenfolge, nur mit vorhandener Quellseite: [['i', 'label', 'description', 'page', 'parents', 'source' => Seite], …] */
    public static function all(): array
    {
        $out = [];
        foreach ((array) app()->settings->get(self::KEY, []) as $i => $t) {
            if (!is_array($t) || empty($t['page']) || !($p = Pages::find((int) $t['page']))) continue;
            $out[] = ['i' => (int) $i, 'label' => trim((string) ($t['label'] ?? '')) ?: (string) $p['title'], 'description' => trim((string) ($t['description'] ?? '')), 'icon' => trim((string) ($t['icon'] ?? '')),
                'page' => (int) $p['id'], 'parents' => array_values((array) ($t['parents'] ?? [])), 'source' => $p];
        }
        return $out;
    }

    /** Gespeicherte Rohwerte fürs Formular */
    public static function values(): array
    {
        return ['templates' => array_values(array_filter((array) app()->settings->get(self::KEY, []), 'is_array'))];
    }

    public static function save(array $templates): void
    {
        app()->settings->set(self::KEY, array_values($templates));
    }

    /** Ist die Seite Quelle einer Vorlage? */
    public static function isSource(int $pageId): bool
    {
        foreach (self::all() as $t) if ($t['page'] === $pageId) return true;
        return false;
    }

    /** Seite als Vorlage anbieten (ans Ende, Name = Seitentitel) */
    public static function add(int $pageId): void
    {
        $p = Pages::find($pageId);
        if (!$p || self::isSource($pageId)) return;
        $list = (array) app()->settings->get(self::KEY, []);
        $list[] = ['label' => (string) $p['title'], 'page' => $pageId, 'icon' => '', 'description' => '', 'parents' => []];
        self::save($list);
    }

    public static function remove(int $pageId): void
    {
        self::save(array_filter((array) app()->settings->get(self::KEY, []), fn($t) => (int) ($t['page'] ?? 0) !== $pageId));
    }

    /** Vorlage, die unter dieser übergeordneten Seite vorgeschlagen wird (Index) oder null */
    public static function suggested(?int $parentId): ?int
    {
        $parent = $parentId ? Pages::find($parentId) : null;
        if (!$parent) return null;
        foreach (self::all() as $t) if ($t['parents'] && PagePicker::matches($t['parents'], $parent)) return $t['i'];
        return null;
    }

    /** Blöcke der Vorlage (Arbeitsstand der Quellseite) mit neuen IDs – auch in Spalten des Blocks „Layout“ */
    public static function blocks(int $index): array
    {
        foreach (self::all() as $t) {
            if ($t['i'] === $index) return self::freshIds(Pages::blocks($t['source'], true));
        }
        return [];
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
