<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Einheitlicher Aufbau der Verwaltung (Vorbild: macOS-Systemeinstellungen) – für Kernseiten und Erweiterungen:
 *
 *   Seiten mit mehreren Bereichen: links eine Bereichsliste mit Symbolen (eine Adresse je Bereich), schmal ein Auswahlfeld
 *   (admin.js [data-secnav]); rechts der Inhalt als gruppierte Zeilen (.set-group / .set-row bzw. Fields::renderGroups).
 *
 *   echo AdminUi::head(__('Mitglieder'), __('Erweiterung'), __('Einladen, Gruppen …'), $actionsHtml);
 *   echo AdminUi::open([
 *       'members' => ['href' => url('/admin/mitglieder'), 'label' => __('Mitglieder'), 'icon' => 'users', 'badge' => 3],
 *       'groups'  => ['href' => url('/admin/mitglieder/gruppen'), 'label' => __('Gruppen'), 'icon' => 'users-three'],
 *       '-',      // Trennlinie
 *       'settings' => ['href' => …, 'label' => __('Einstellungen'), 'icon' => 'gear-six', 'warn' => __('Angaben fehlen')],
 *   ], 'members', __('Mitglieder'));
 *   … Inhalt …
 *   echo AdminUi::close();
 */
final class AdminUi
{
    /** Kopf einer Verwaltungsseite: Dachzeile, Titel, eine Zeile Beschreibung, Aktionen rechts (fertiges HTML) */
    public static function head(string $title, string $eyebrow = '', string $text = '', string $actions = ''): string
    {
        return '<header class="adm-head"><div>' . ($eyebrow !== '' ? '<p class="adm-eyebrow">' . e($eyebrow) . '</p>' : '')
            . '<h1>' . e($title) . '</h1>' . ($text !== '' ? '<p class="adm-muted">' . e($text) . '</p>' : '') . '</div>'
            . ($actions !== '' ? '<div class="adm-head__actions">' . $actions . '</div>' : '') . '</header>';
    }

    /** Bereichsliste (Links): $items key => [href, label, icon, badge?, warn?] oder '-' für eine Trennlinie */
    public static function nav(array $items, string $current, string $label = ''): string
    {
        $h = '<nav class="adm-tabs adm-secnav" aria-label="' . e($label !== '' ? $label : __('Bereiche')) . '">';
        foreach ($items as $k => $it) {
            if ($it === '-') { $h .= '<span class="adm-tabs__sep" role="separator"></span>'; continue; }
            $warn = (string) ($it['warn'] ?? '');
            $badge = $it['badge'] ?? null;
            $h .= '<a href="' . e((string) $it['href']) . '"' . ($warn !== '' ? ' class="is-warn" title="' . e($warn) . '"' : '')
                . ((string) $k === $current ? ' aria-current="page"' : '') . '>'
                . '<span class="adm-tabs__ico" aria-hidden="true">' . icon((string) ($it['icon'] ?? 'circle')) . '</span>'
                . '<span class="adm-tabs__label">' . e((string) $it['label']) . '</span>'
                . ($badge !== null && $badge !== '' && $badge !== 0 ? '<span class="adm-tabs__badge">' . e((string) $badge) . '</span>' : '')
                . ($warn !== '' ? '<span class="adm-tabs__dot" aria-hidden="true"></span><span class="adm-sr"> – ' . e($warn) . '</span>' : '') . '</a>';
        }
        return $h . '</nav>';
    }

    /** Rahmen öffnen: Bereichsliste links, Inhalt rechts (mit Überschrift des Bereichs) */
    public static function open(array $items, string $current, string $label = '', bool $title = true): string
    {
        $t = '';
        if ($title && isset($items[$current]['label'])) $t = '<h2 class="us-main__title">' . e((string) $items[$current]['label']) . '</h2>';
        return '<div class="adm-tabs-form--side us-shell" data-secnav>' . self::nav($items, $current, $label) . '<div class="us-main">' . $t;
    }

    public static function close(): string
    {
        return '</div></div>';
    }
}
