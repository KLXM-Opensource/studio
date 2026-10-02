<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Slots der Verwaltung: die festen Stellen, an denen Erweiterungen Inhalte in Core-Seiten einhängen (Extension::pageList,
 * pagePanel, tableActions, mediaPanel, dashboard, account). Erweiterungen liefern Daten, der Core rendert und escaped hier.
 *
 * Karte (pagePanel, mediaPanel, account, dashboard 'body'):
 *   ['title' => 'Feedback', 'text' => 'Eine Zeile Klartext', 'tone' => 'ok'|'warn'|'info'|'muted',
 *    'lines' => ['Offen' => '3', 'Links' => '1'],                       Bezeichnung → Wert (Klartext)
 *    'actions' => [['label' => 'Ansehen', 'href' => '/admin/feedback/12', 'primary' => true]]]   nur Pfade dieser Installation
 * Aktionen: ['label' => …, 'href' => '/admin/…'] – Platzhalter {id}, {table} (tableActions) werden ersetzt und kodiert.
 */
final class Slots
{
    /** Alle Slots: Schlüssel → [Methode, Ort, Rückgabe] (Entwicklerhandbuch, Selbsttest) */
    public const ALL = [
        'page.tree' => ['pageList', 'Seitenbaum: Hinweis in der Spalte „Status“ und Einträge im Kontextmenü', "['badges' => [...], 'actions' => [...]]"],
        'page.panel' => ['pagePanel', 'Seiteneinstellungen: Karte in der Seitenleiste', 'Karte'],
        'table.actions' => ['tableActions', 'Datentabelle: Knöpfe im Kopf der Liste und Aktion je Zeile', "['actions' => [...], 'row' => [...]]"],
        'media.panel' => ['mediaPanel', 'Mediathek: Abschnitt unter „Informationen“ einer Datei', 'Karte'],
        'dashboard' => ['dashboard', 'Übersicht: Kennzahlen-Kacheln und Karten', "['tiles' => [...], 'cards' => [...]]"],
        'account' => ['account', 'Konto: eigener Abschnitt auf „Konto“', 'Karte'],
    ];

    public const TONES = ['ok', 'warn', 'info', 'muted'];

    /**
     * Karte rendern (alles escaped). $variant: 'card' (adm-card mit h2), 'media' (Abschnitt der Mediathek, h3),
     * 'body' (nur Inhalt, z. B. in einer Karte der Übersicht). '' bei leerer Karte.
     */
    public static function card(array $spec, string $variant = 'card', string $owner = ''): string
    {
        $title = trim((string) ($spec['title'] ?? ''));
        $text = trim((string) ($spec['text'] ?? ''));
        $tone = self::tone($spec['tone'] ?? null);
        $lines = '';
        foreach ((array) ($spec['lines'] ?? []) as $label => $value) {
            if (is_array($value) || trim((string) $label) === '') continue;
            $lines .= '<dt>' . e((string) $label) . '</dt><dd>' . e((string) $value) . '</dd>';
        }
        $btns = '';
        foreach (self::actions((array) ($spec['actions'] ?? [])) as $a) {
            $btns .= '<a class="adm-btn adm-btn--small' . ($a['primary'] ? ' adm-btn--primary' : '') . '" href="' . e(url($a['href'])) . '">' . e($a['label']) . '</a>';
        }
        if ($title === '' && $text === '' && $lines === '' && $btns === '') return '';
        $body = ($text !== '' ? '<p class="slot-card__text' . ($tone !== 'info' ? ' slot-card__text--' . $tone : '') . '">' . e($text) . '</p>' : '')
            . ($lines !== '' ? '<dl class="adm-dl slot-card__lines">' . $lines . '</dl>' : '')
            . ($btns !== '' ? '<div class="adm-row slot-card__actions">' . $btns . '</div>' : '');
        $data = $owner !== '' ? ' data-slot="' . e($owner) . '"' : '';
        return match ($variant) {
            'body' => '<div class="slot-card slot-card--body"' . $data . '>' . $body . '</div>',
            'media' => '<section class="fx-i-sec slot-card slot-card--media"' . $data . '>' . ($title !== '' ? '<h3>' . e($title) . '</h3>' : '') . $body . '</section>',
            default => '<section class="adm-card slot-card"' . $data . '>' . ($title !== '' ? '<h2>' . e($title) . '</h2>' : '') . $body . '</section>',
        };
    }

    /**
     * Aktionen prüfen: Beschriftung, Pfad dieser Installation (beginnt mit „/“, kein „//“), Platzhalter aus $vars (kodiert).
     * @return list<array{label: string, href: string, primary: bool, icon: string}>
     */
    public static function actions(array $list, array $vars = []): array
    {
        $out = [];
        foreach ($list as $a) {
            if (!is_array($a) || trim((string) ($a['label'] ?? '')) === '') continue;
            $href = (string) ($a['href'] ?? '');
            foreach ($vars as $k => $v) $href = str_replace('{' . $k . '}', rawurlencode((string) $v), $href);
            if (!self::localPath($href)) continue;
            $out[] = ['label' => trim((string) $a['label']), 'href' => $href, 'primary' => !empty($a['primary']),
                'icon' => preg_match('~^[a-z0-9-]{1,40}$~', (string) ($a['icon'] ?? '')) ? (string) $a['icon'] : ''];
        }
        return $out;
    }

    /** Pfad dieser Installation? („/…“, kein „//“, kein Backslash, keine Steuerzeichen, keine offenen Platzhalter) */
    public static function localPath(string $href): bool
    {
        return str_starts_with($href, '/') && !str_starts_with($href, '//') && !preg_match('~[\\\\\x00-\x1f{}]~', $href);
    }

    public static function tone(mixed $tone): string
    {
        return in_array($tone, self::TONES, true) ? (string) $tone : 'info';
    }
}
