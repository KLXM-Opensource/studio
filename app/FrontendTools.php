<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Werkzeuge für das Bearbeiten auf der Website (Werkzeugleiste, Core\Toolbar) – angemeldet von Funktionen des Cores
 * (register) und Erweiterungen ($x->frontendTool([...])).
 *
 * Ein Werkzeug ist ein Knopf in der Werkzeugleiste (placement 'main') bzw. ein Eintrag im Menü „⋯“ ('more'), optional mit
 * Tastenkürzel. Erst beim ersten Öffnen lädt der Browser sein ES-Modul (module) und ruft mount(ctx) auf – Besucher und
 * Redaktion außerhalb des Bearbeitens laden nichts. Oberfläche: Seitenleiste in der Shadow-DOM-Ebene (CSS isoliert).
 * Verhalten im Browser: resources/js/_tools.js (CMSAdmin.tools), Ereignisse cms:* (Technik → Erweiterungen).
 *
 * Angaben: id (a–z, 0–9, -, _), label, icon (Symbolname), module ('js/x.mjs' im assets-Ordner der Erweiterung bzw. für den Core
 * eine fertige Adresse ab „/“), placement 'main'|'more', shortcut ('Alt+G', 'Alt+Shift+K' …), hint (kleine Zeile im Menü),
 * perm (Recht), table (Tabelle zum Recht, can($perm, $table)), feature (Funktion muss an sein), visible (fn(array $bar): bool),
 * modes (['page', 'entry'] – Seiten-Editor inkl. Vorlage bzw. Eintrag direkt im Text), panel (['title' => …, 'size' => 'narrow'|'wide']),
 * endpoints (['name' => '/admin/api/…'] → absolute Pfade), data (array oder fn(array $bar): array – frei für das Modul),
 * texts (übersetzte Texte für das Modul – auf der Website gibt es kein Wörterbuch der Verwaltung).
 * Rechte der Endpunkte prüft der Server bei JEDEM Aufruf selbst; die Angaben hier steuern nur die Anzeige.
 */
final class FrontendTools
{
    public const MODES = ['page', 'entry'];
    /** @var array<string, array> Werkzeuge des Cores */
    private static array $core = [];

    /** Werkzeug des Cores anmelden (Erweiterungen: Extension::frontendTool) */
    public static function register(array $def): void
    {
        if ($t = self::normalize($def, 'core')) self::$core[$t['id']] = $t;
    }

    public static function reset(): void
    {
        self::$core = [];
    }

    /** Angaben prüfen – null, wenn id, label oder module fehlen bzw. ungültig sind */
    public static function normalize(array $def, string $source = 'core', ?Extension $x = null): ?array
    {
        $id = (string) ($def['id'] ?? '');
        $label = trim((string) ($def['label'] ?? ''));
        $module = trim((string) ($def['module'] ?? ''));
        if (!preg_match('~^[a-z][a-z0-9_-]{1,40}$~', $id) || $label === '' || $module === '') return null;
        // Modul: Erweiterung → Datei aus ihrem assets-Ordner (nur .mjs/.js, kein ..), Core → Pfad ab „/“ auf derselben Domain
        if ($x && !str_starts_with($module, '/')) {
            if (!preg_match('~^[a-z0-9_./-]+\.(mjs|js)$~i', $module) || str_contains($module, '..')) return null;
            $module = $x->asset($module);
        } elseif (!str_starts_with($module, '/') || str_starts_with($module, '//')) {
            return null;
        }
        $modes = array_values(array_intersect((array) ($def['modes'] ?? self::MODES), self::MODES)) ?: self::MODES;
        $endpoints = [];
        foreach ((array) ($def['endpoints'] ?? []) as $k => $v) {
            $v = (string) $v;
            if (preg_match('~^[a-z][a-zA-Z0-9_]{0,40}$~', (string) $k) && str_starts_with($v, '/') && !str_starts_with($v, '//')) $endpoints[(string) $k] = $v;
        }
        return [
            'id' => $id, 'label' => $label, 'icon' => (string) (($def['icon'] ?? '') ?: 'puzzle-piece'), 'module' => $module,
            'placement' => ($def['placement'] ?? 'main') === 'more' ? 'more' : 'main',
            'shortcut' => self::shortcut((string) ($def['shortcut'] ?? '')),
            'hint' => trim((string) ($def['hint'] ?? '')),
            'perm' => isset($def['perm']) && $def['perm'] !== '' ? (string) $def['perm'] : null,
            'table' => isset($def['table']) && $def['table'] !== '' ? (string) $def['table'] : null,
            'feature' => isset($def['feature']) && $def['feature'] !== '' ? (string) $def['feature'] : null,
            'visible' => is_callable($def['visible'] ?? null) ? $def['visible'] : null,
            'modes' => $modes,
            'panel' => ['title' => trim((string) ($def['panel']['title'] ?? $label)), 'size' => ($def['panel']['size'] ?? '') === 'wide' ? 'wide' : 'narrow'],
            'endpoints' => $endpoints,
            'data' => $def['data'] ?? [],
            'texts' => array_map('strval', (array) ($def['texts'] ?? [])),
            'source' => $source,
        ];
    }

    /**
     * Tastenkürzel prüfen und vereinheitlichen: „Alt+G“ → ['keys' => 'Alt+G' (aria-keyshortcuts), 'label' => '⌥G', 'code' => 'KeyG',
     * alt/shift/ctrl/meta]. Ohne Alt/Strg/⌘ kein Kürzel (Tippen in Texten darf nichts auslösen).
     */
    public static function shortcut(string $s): ?array
    {
        $parts = array_values(array_filter(array_map('trim', explode('+', $s)), 'strlen'));
        if (!$parts) return null;
        $key = strtoupper((string) array_pop($parts));
        $mods = array_map('strtolower', $parts);
        $m = ['alt' => in_array('alt', $mods, true) || in_array('option', $mods, true), 'shift' => in_array('shift', $mods, true),
            'ctrl' => in_array('ctrl', $mods, true) || in_array('control', $mods, true), 'meta' => in_array('meta', $mods, true) || in_array('cmd', $mods, true)];
        if (!preg_match('~^([A-Z]|[0-9]|F[1-9]|F1[0-2])$~', $key) || !($m['alt'] || $m['ctrl'] || $m['meta'])) return null;
        $code = ctype_digit($key) ? 'Digit' . $key : (strlen($key) === 1 ? 'Key' . $key : $key);
        $aria = implode('+', array_merge(array_keys(array_filter(['Control' => $m['ctrl'], 'Meta' => $m['meta'], 'Alt' => $m['alt'], 'Shift' => $m['shift']])), [$key]));
        $label = ($m['ctrl'] ? '⌃' : '') . ($m['alt'] ? '⌥' : '') . ($m['shift'] ? '⇧' : '') . ($m['meta'] ? '⌘' : '') . $key;
        return ['keys' => $aria, 'label' => $label, 'code' => $code] + $m;
    }

    /**
     * Werkzeuge der Funktionen des Cores – angemeldet wie von einer Erweiterung (gleiche Angaben wie $x->frontendTool()).
     * Quick-Glossar: Core\Glossary\QuickTool::definition() (Referenzbeispiel in Technik → Erweiterungen).
     */
    private static function coreTools(): array
    {
        $out = [];
        foreach ([fn() => \Core\Glossary\QuickTool::definition()] as $def) {
            try {
                if ($t = self::normalize($def(), 'core')) $out[$t['id']] = $t;
            } catch (\Throwable $e) {
                error_log('[Werkzeug] ' . $e->getMessage());
            }
        }
        return $out;
    }

    /** Alle angemeldeten Werkzeuge (Core + aktive Erweiterungen) */
    public static function all(): array
    {
        $out = self::coreTools() + self::$core;
        foreach (Extensions::active() as $x) {
            foreach ($x->frontendTools as $t) $out[$t['id']] ??= $t;   // gleiche id: der Core bzw. die erste Erweiterung gewinnt
        }
        return $out;
    }

    /** Darf der angemeldete Benutzer das Werkzeug in dieser Werkzeugleiste sehen? */
    public static function visible(array $t, array $bar): bool
    {
        try {
            if (!app()->auth->check()) return false;
            if ($t['feature'] !== null && !(Features::all()[$t['feature']] ?? false)) return false;   // unbekannte Funktion = aus
            if ($t['perm'] !== null && !can($t['perm'], $t['table'])) return false;
            return $t['visible'] === null || (bool) ($t['visible'])($bar);
        } catch (\Throwable $e) {
            error_log('[Werkzeug ' . $t['id'] . '] ' . $e->getMessage());
            return false;
        }
    }

    /** Bearbeiten-Art der Werkzeugleiste: 'page' (Seiten-Editor, auch Vorlage), 'entry' (Eintrag direkt im Text) oder null */
    public static function mode(array $bar): ?string
    {
        if (!empty($bar['live'])) return null;
        if (($bar['kind'] ?? '') === 'entry') return !empty($bar['entryEditable']) ? 'entry' : null;
        return !empty($bar['editing']) && !empty($bar['hasPage']) ? 'page' : null;
    }

    /**
     * Werkzeuge für eine Werkzeugleiste (Core\Toolbar::context): nur angemeldet, nur in einem Bearbeiten-Modus, nur mit Recht.
     * Ergebnis je Werkzeug: Angaben für das Markup und die JSON-Konfiguration (ohne Callables).
     */
    public static function forBar(array $bar): array
    {
        $mode = self::mode($bar);
        if ($mode === null) return [];
        $out = [];
        foreach (self::all() as $t) {
            if (!in_array($mode, $t['modes'], true) || !self::visible($t, $bar)) continue;
            $data = $t['data'];
            if (is_callable($data)) {
                try {
                    $data = $data($bar);
                } catch (\Throwable $e) {
                    error_log('[Werkzeug ' . $t['id'] . '] data: ' . $e->getMessage());
                    continue;
                }
            }
            $out[] = ['id' => $t['id'], 'label' => $t['label'], 'icon' => $t['icon'], 'module' => $t['module'], 'placement' => $t['placement'],
                'shortcut' => $t['shortcut'], 'hint' => $t['hint'], 'panel' => $t['panel'], 'texts' => $t['texts'], 'source' => $t['source'],
                'endpoints' => array_map(fn($p) => url($p), $t['endpoints']), 'data' => is_array($data) ? $data : []];
        }
        return $out;
    }

    /** JSON-Konfiguration für resources/js/_tools.js (<script type="application/json" id="cms-tools">) */
    public static function config(array $bar, array $tools): array
    {
        $page = $bar['page'] ?? [];
        return [
            'mode' => self::mode($bar), 'kind' => $bar['kind'] ?? 'page',
            'page' => !empty($page['id']) ? ['id' => (int) $page['id'], 'title' => (string) ($page['title'] ?? ''), 'lang' => Lang::norm($page['lang'] ?? null)] : null,
            'entry' => isset($bar['entry'], $bar['table']) ? ['table' => (string) $bar['table']['handle'], 'id' => (int) $bar['entry']['id']] : null,
            'lang' => Lang::current(), 'csrf' => Csrf::token(),
            'texts' => ['close' => __('Schließen'), 'noText' => __('Bitte zuerst in einen Text klicken – dort wird eingefügt.'),
                'noLinks' => __('Dieses Feld kennt keine Links – eingefügt wird nur der Text.'), 'error' => __('Das hat nicht geklappt.'),
                'loading' => __('Wird geladen …'), 'backToText' => __('Zurück zum Text')],
            'tools' => $tools,
        ];
    }
}
