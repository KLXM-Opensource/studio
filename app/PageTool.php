<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * „Neue Seite“: Werkzeug in der Werkzeugleiste der Website (Core\FrontendTools) – Redaktion mit Recht pages.manage legt Seiten
 * an, ohne in die Verwaltung zu wechseln. Knopf „Neue Seite“ (⌥N), auch beim Ansehen; öffnet ein Modal (panel size 'modal')
 * mit Seitenbaum: Ort wählen (unter einer Seite, davor oder danach), Titel, Adresse, Vorlage, „Im Menü zeigen“.
 * Angelegt wird immer ein Entwurf; danach öffnet die neue Seite im Bearbeiten-Modus.
 * Modul resources/js/new-page.mjs (erst beim ersten Öffnen geladen), Endpunkte PageController::apiTree/apiCreate.
 */
final class PageTool
{
    public static function definition(): array
    {
        return [
            'id' => 'newpage', 'label' => __('Neue Seite'), 'icon' => 'file-plus', 'module' => asset('js/new-page.mjs'),
            'placement' => 'main', 'shortcut' => 'Alt+N', 'hint' => __('Seite im Seitenbaum anlegen'),
            'view' => true, 'perm' => 'pages.manage',
            'panel' => ['title' => __('Neue Seite anlegen'), 'size' => 'modal'],
            'endpoints' => ['tree' => '/admin/api/pages/tree', 'create' => '/admin/api/pages/create'],
            'data' => fn(array $bar) => [
                'current' => !empty($bar['page']['id']) && ($bar['page']['type'] ?? 'page') === 'page' ? (int) $bar['page']['id'] : null,
                'lang' => Lang::norm($bar['page']['lang'] ?? null),
                'admin' => url('/admin/pages'),
            ],
            'texts' => [
                'where' => __('Wo soll die Seite hin?'), 'filter' => __('Seiten filtern'), 'top' => __('Oberste Ebene'),
                'under' => __('Unter „{title}“'), 'before' => __('Davor'), 'after' => __('Danach'), 'inside' => __('Darunter'),
                'posHelp' => __('Seite im Baum wählen, dann: darunter (als Unterseite), davor oder danach.'),
                'title' => __('Titel'), 'slug' => __('Adresse (optional)'), 'slugHelp' => __('Leer: wird aus dem Titel gebildet.'),
                'template' => __('Vorlage'), 'empty' => __('Leere Seite'), 'menu' => __('Im Menü zeigen'),
                'create' => __('Seite anlegen und bearbeiten'), 'cancel' => __('Abbrechen'), 'creating' => __('Seite wird angelegt …'),
                'draft' => __('Die Seite wird als Entwurf angelegt – Besucher sehen sie erst nach dem Veröffentlichen.'),
                'result' => __('Neue Seite: {path}'), 'none' => __('Keine Seite gefunden.'), 'loadErr' => __('Der Seitenbaum konnte nicht geladen werden.'),
                'draftBadge' => __('Entwurf'), 'hidden' => __('nicht im Menü'), 'current' => __('diese Seite'), 'manage' => __('Seitenbaum in der Verwaltung'),
                'needTitle' => __('Bitte einen Titel angeben.'),
            ],
        ];
    }
}
