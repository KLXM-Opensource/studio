<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * „Seiten“: Seitenstruktur auf der Website (Werkzeug der Werkzeugleiste, Core\FrontendTools) – Redaktion mit Recht pages.manage
 * sortiert und verschiebt Seiten (Ziehen oder ↑ ↓ ← →), schaltet Menü und Online/Offline, legt Unterseiten an und löscht – ohne in die
 * Verwaltung zu wechseln. Seitenleiste in voller Höhe (panel drawer), beim Ansehen und Bearbeiten. Unter Seiten, die eine
 * Datentabelle zeigen (URL-Basis = Pfad der Seite), stehen ihre Einträge („3 Beiträge ▸“).
 * Modul resources/js/pages-panel.mjs; Endpunkte wie die Seitenliste der Verwaltung (PageController: apiTree mit full=1, apiCreate,
 * move, quick, publish, offline, delete) – gleiche Rechte, Prüfungen, Weiterleitungen und Aktionslog.
 */
final class PagesTool
{
    public const ID = 'pages';

    public static function definition(): array
    {
        return [
            'id' => self::ID, 'label' => __('Seiten'), 'icon' => 'tree-structure', 'module' => asset('js/pages-panel.mjs'),
            'placement' => 'main', 'scope' => 'site', 'shortcut' => 'Alt+P', 'hint' => __('Seitenstruktur: sortieren, verschieben, anlegen'),
            'view' => true, 'perm' => 'pages.manage',
            'panel' => ['title' => __('Seiten'), 'size' => 'drawer', 'width' => 560],
            'endpoints' => ['tree' => '/admin/api/pages/tree', 'create' => '/admin/api/pages/create', 'base' => '/admin/pages'],
            'data' => fn(array $bar) => [
                'current' => !empty($bar['page']['id']) && ($bar['page']['type'] ?? 'page') === 'page' ? (int) $bar['page']['id'] : null,
                'lang' => Lang::norm($bar['page']['lang'] ?? null),
                'admin' => url('/admin/pages'),
            ],
            'texts' => [
                'help' => __('Ziehen am Griff ⠿ sortiert und verschiebt (auf eine Seite fallen lassen = darunter einordnen). Ohne Ziehen: ↑ ↓ sortieren, ← eine Ebene höher, → unter die Seite darüber.'),
                'filter' => __('Seiten filtern'), 'loading' => __('Seiten werden geladen …'), 'loadErr' => __('Der Seitenbaum konnte nicht geladen werden.'),
                'home' => __('Startseite'), 'inMenu' => __('Menü'), 'page' => __('Seite'), 'current' => __('diese Seite'),
                'up' => __('Nach oben: {title}'), 'down' => __('Nach unten: {title}'), 'left' => __('Eine Ebene höher: {title}'), 'right' => __('Unter die Seite darüber: {title}'),
                'menuOn' => __('Im Menü – klicken zum Entfernen: {title}'), 'menuOff' => __('Nicht im Menü – klicken zum Aufnehmen: {title}'),
                'online' => __('Online – klicken zum Offline nehmen: {title}'), 'offline' => __('Offline – klicken zum Online stellen: {title}'),
                'draft' => __('Entwurf – klicken zum Veröffentlichen: {title}'), 'homeOnline' => __('Die Startseite ist immer online.'),
                'add' => __('Unterseite anlegen unter „{title}“'), 'addTop' => __('Neue Seite (oberste Ebene)'), 'del' => __('„{title}“ löschen'),
                'newTitle' => __('Titel der neuen Seite'), 'newMenu' => __('Im Menü zeigen'), 'create' => __('Anlegen'), 'cancel' => __('Abbrechen'),
                'created' => __('„{title}“ angelegt (Entwurf).'), 'open' => __('Öffnen'), 'moved' => __('Verschoben.'),
                'delTitle' => __('„{title}“ löschen?'), 'delBody' => __('Die Seite wird gelöscht. Unterseiten rücken eine Ebene nach oben. Die Versionen der Seite gehen verloren.'),
                'delOk' => __('Löschen'), 'deleted' => __('„{title}“ gelöscht.'),
                'offTitle' => __('Seite offline nehmen?'), 'offBody' => __('Besucher sehen sie dann nicht mehr; sie verschwindet aus Menü, Sitemap und Suche. Die veröffentlichte Fassung bleibt erhalten.'),
                'offOk' => __('Offline nehmen'), 'entries' => __('{n} Einträge'), 'entriesAll' => __('Alle in der Verwaltung'),
                'reloadHint' => __('Menü und Navigation der Seite zeigen Änderungen nach dem Neuladen.'),
                'manage' => __('Seitenliste in der Verwaltung'), 'draftBadge' => __('Entwurf'),
                'foldIt' => __('Unterseiten von „{title}“ zuklappen'), 'unfold' => __('Unterseiten von „{title}“ aufklappen'),
                'foldAll' => __('Alle zuklappen'), 'unfoldAll' => __('Alle aufklappen'), 'noindex' => __('Nicht in Suchmaschinen'), 'none' => __('Keine Seite gefunden.'),
            ],
        ];
    }
}
