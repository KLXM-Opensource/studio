<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Baukasten der Verwaltung (Vorbild: macOS-Systemeinstellungen) – für Kernseiten und Erweiterungen, immer gleich:
 *
 *   head()          Seitenkopf: Dachzeile, Titel, eine Zeile Beschreibung, Aktionen rechts
 *   open()/close()  Bereichsliste links, eine Adresse je Bereich (schmal: Auswahlfeld, admin.js [data-secnav])
 *   tabs()          Bereichsliste links, Bereiche auf derselben Seite (ein Formular oder mehrere, admin.js [data-tabs])
 *   group()/row()   Inhalt als gruppierte Zeilen: Beschriftung + Erklärung links, Bedienung rechts (oder darunter: stack)
 *   link()          ganze Zeile als Link mit Pfeil (Unterseite öffnen)
 *   toolbar()       Werkzeugzeile über dem Inhalt (z. B. Vorschau, Kontrast, Sprache)
 *   savebar()       feste Speichern-Leiste unten (in einem <form>)
 *   Formularfelder: Fields::renderGroups($fields, $values, $errors) ergibt dieselben Gruppen und Zeilen.
 *   Musterseite mit allen Bausteinen: Entwicklerhandbuch → Oberfläche (/admin/hilfe/ui).
 *
 *   echo AdminUi::head('Mitglieder', 'Erweiterung', 'Einladen, Gruppen und geschützte Seiten', $actionsHtml);
 *   echo AdminUi::open([
 *       'members' => ['href' => url('/admin/mitglieder'), 'label' => 'Mitglieder', 'icon' => 'users', 'badge' => 3],
 *       'groups'  => ['href' => url('/admin/mitglieder/gruppen'), 'label' => 'Gruppen', 'icon' => 'users-three'],
 *       '-',      // Trennlinie
 *       'settings' => ['href' => …, 'label' => 'Einstellungen', 'icon' => 'gear-six', 'warn' => 'Angaben fehlen'],
 *   ], 'members', 'Mitglieder');   // Texte im echten Code mit __()
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

    /**
     * Bereiche auf derselben Seite (Wechsel ohne Neuladen, Adresse #bereich): $sections id => [label, icon, html, warn?, error?].
     * $form: ['action' => …, 'savebar' => true|'Text'] umschließt alles mit einem Formular (sonst bringt jeder Bereich eigene mit).
     */
    public static function tabs(array $sections, array $form = [], string $label = ''): string
    {
        $tag = $form ? 'form' : 'div';
        $attr = $form ? ' method="post" action="' . e((string) $form['action']) . '" novalidate' . (!empty($form['multipart']) ? ' enctype="multipart/form-data"' : '') : '';
        $h = '<' . $tag . ' class="adm-tabs-form adm-tabs-form--side" data-tabs' . $attr . '>' . ($form ? csrf_field() . '<input type="hidden" name="_tab" value="">' : '');
        $h .= '<div class="adm-tabs" role="tablist" aria-label="' . e($label !== '' ? $label : __('Bereiche')) . '">';
        $i = 0;
        foreach ($sections as $id => $sec) {
            if ($sec === '-') { $h .= '<span class="adm-tabs__sep" role="separator"></span>'; continue; }
            $warn = (string) ($sec['warn'] ?? '');
            $err = !empty($sec['error']);   // Bereich enthält Fehler: wird beim Laden geöffnet (admin.js data-tab-error)
            $h .= '<button type="button" role="tab" id="tab-' . e($id) . '" aria-controls="panel-' . e($id) . '" data-tab="' . e($id) . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '"'
                . ($i === 0 ? '' : ' tabindex="-1"') . ($warn !== '' ? ' class="is-warn" title="' . e($warn) . '"' : '') . ($err ? ' data-tab-error' : '') . '>'
                . '<span class="adm-tabs__ico" aria-hidden="true">' . icon((string) ($sec['icon'] ?? 'circle')) . '</span><span class="adm-tabs__label">' . e((string) $sec['label']) . '</span>'
                . ($warn !== '' ? '<span class="adm-tabs__dot" aria-hidden="true"></span><span class="adm-sr"> – ' . e($warn) . '</span>' : '') . '</button>';
            $i++;
        }
        $h .= '</div>';
        $i = 0;
        foreach ($sections as $id => $sec) {
            if ($sec === '-') continue;
            $h .= '<section class="adm-panel adm-panel--groups ui-panel" role="tabpanel" id="panel-' . e($id) . '" aria-labelledby="tab-' . e($id) . '"' . ($i === 0 ? '' : ' hidden') . '>'
                . '<h2>' . e((string) $sec['label']) . '</h2>' . (string) ($sec['html'] ?? '') . '</section>';
            $i++;
        }
        if ($form && !empty($form['savebar'])) $h .= self::savebar(is_string($form['savebar']) ? $form['savebar'] : '');
        return $h . '</' . $tag . '>';
    }

    /** Gruppe von Zeilen: Titel über der Karte, Zeilen darin, Hinweis darunter */
    public static function group(string $title, array|string $rows, string $note = '', string $id = ''): string
    {
        return '<section class="set-group"' . ($id !== '' ? ' id="' . e($id) . '"' : '') . '>' . ($title !== '' ? '<h3 class="set-group__title">' . e($title) . '</h3>' : '')
            . '<div class="set-list">' . (is_array($rows) ? implode('', $rows) : $rows) . '</div>'
            . ($note !== '' ? '<p class="set-group__note">' . e($note) . '</p>' : '') . '</section>';
    }

    /**
     * Eine Zeile: Beschriftung und Erklärung links, Bedienung rechts ($control = fertiges HTML, z. B. Schalter, Knopf, Wert).
     * $opt: for (id des Feldes → <label>), stack (Bedienung unter der Beschriftung, z. B. Text, Liste, Tabelle)
     */
    public static function row(string $label, string $control = '', string $sub = '', array $opt = []): string
    {
        $lab = !empty($opt['for']) ? '<label class="set-row__label" for="' . e((string) $opt['for']) . '">' . e($label) . '</label>' : '<span class="set-row__label">' . e($label) . '</span>';
        return '<div class="set-row' . (!empty($opt['stack']) ? ' set-row--stack' : '') . '"><div class="set-row__main">' . $lab
            . ($sub !== '' ? '<span class="set-row__sub">' . e($sub) . '</span>' : '') . '</div>'
            . ($control !== '' ? '<div class="set-row__ctl">' . $control . '</div>' : '') . '</div>';
    }

    /** Ganze Zeile als Link (öffnet eine Unterseite) */
    public static function link(string $label, string $href, string $sub = '', string $value = ''): string
    {
        return '<a class="set-row set-row--link" href="' . e($href) . '"><span class="set-row__main"><span class="set-row__label">' . e($label) . '</span>'
            . ($sub !== '' ? '<span class="set-row__sub">' . e($sub) . '</span>' : '') . '</span>' . ($value !== '' ? '<span class="set-row__ctl">' . e($value) . '</span>' : '') . '</a>';
    }

    /** Werkzeugzeile über dem Inhalt, rechtsbündig (fertiges HTML) */
    public static function toolbar(string $html): string
    {
        return '<div class="ds-top ui-toolbar">' . $html . '</div>';
    }

    /** Feste Speichern-Leiste (innerhalb eines Formulars) */
    public static function savebar(string $note = '', string $label = ''): string
    {
        return '<div class="adm-savebar"><button class="adm-btn adm-btn--primary" type="submit">' . e($label !== '' ? $label : __('Speichern')) . '</button>'
            . ($note !== '' ? '<span class="adm-muted">' . e($note) . '</span>' : '') . '</div>';
    }
}
