<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Seiten der Verwaltung, die Funktionen des Cores und Erweiterungen anmelden – und WO sie erscheinen.
 *
 * Jede Seite hat eine Art (kind):
 *   content   arbeitet mit Inhalten (Seiten, Einträge, Buchungen …)        → Hauptmenü (place 'main') bzw. Administration ('admin')
 *   tool      Werkzeug / Arbeitsablauf (Prüfen, Importieren, Video …)     → Administration → Gruppe „Werkzeuge“ (Standard-Platz
 *                                                                           'admin'; mit 'place' => 'main' ausdrücklich im Hauptmenü)
 *   settings  reine Konfiguration                                         → NICHT im Menü: Sammelseite „Einstellungen“
 *                                                                           (/admin/einstellungen), mit 'table' zusätzlich an der Datentabelle
 *   stats     Berichte, Statistiken                                       → NICHT im Menü: Sammelseite „Statistiken“ (/admin/statistiken)
 *
 * Anmelden: Erweiterungen mit $x->adminPage([...]) (bzw. $x->nav(…) – ohne Art: 'main' → content, 'admin' → tool), der Core
 * über core() (unten). Felder einer Anmeldung:
 *   href (Pflicht, /admin/…), label (Pflicht), kind, icon (Symbolname oder Menü-Schlüssel), perm (Recht, can()), table (Kurzname
 *   einer Datentabelle – nur settings), tableLabel (Beschriftung an der Tabelle, Standard label), description (eine Zeile für die Karte), feature (Funktion muss an sein), visible (fn(): bool
 *   für weitere Bedingungen), place ('main' | 'admin', nur content/tool; Standard: tool → 'admin', sonst 'main'), id (Standard: aus href).
 * Adressen und Rechte ändern sich durch die Einordnung nicht – nur der Ort in der Navigation.
 *
 * Abschnitt „Administration“ der Seitenleiste (app/Admin/views/layout.php): nur aufklappbare Gruppen – „Einstellungen“
 * (Grundeinstellungen, Funktionen & Erweiterungen, Einstellungen der Funktionen = Sammelseite, Benutzer & Rollen, Design) und
 * „Werkzeuge“ (Blöcke, Landingpages, Weiterleitungen, Statistiken und alle Seiten mit Platz 'admin', also die Werkzeuge der
 * Erweiterungen aus nav('admin')). Eine Gruppe mit nur einem sichtbaren Punkt erscheint als einfacher Link.
 */
final class AdminPages
{
    public const KINDS = ['content', 'tool', 'settings', 'stats'];
    /** Sammelseiten */
    public const HUB = '/admin/einstellungen';
    public const STATS = '/admin/statistiken';

    /** @var list<array> zusätzliche Anmeldungen des Cores (z. B. aus Tests oder Kits) */
    private static array $extra = [];

    /** Seite des Cores anmelden (Erweiterungen: Extension::adminPage) */
    public static function register(array $def): void
    {
        if ($p = self::normalize($def, 'core')) self::$extra[$p['id']] = $p;
    }

    /** Anmeldungen des Cores zurücksetzen (Selbsttest) */
    public static function reset(): void
    {
        self::$extra = [];
    }

    /**
     * Anmeldung prüfen und vervollständigen – null bei ungültiger Adresse/Beschriftung.
     * Ohne Art: aus place abgeleitet ('admin' → tool, sonst content) – so bleiben ältere nav()-Aufrufe an ihrem Platz.
     */
    public static function normalize(array $def, string $source = 'core', string $sourceLabel = ''): ?array
    {
        $href = (string) ($def['href'] ?? '');
        $label = trim((string) ($def['label'] ?? ''));
        if ($label === '' || !str_starts_with($href, '/') || str_starts_with($href, '//') || preg_match('~[\s"<>]~', $href)) return null;
        $kind = (string) ($def['kind'] ?? '');
        // Ohne Platz: Werkzeuge in die Administration (Gruppe „Werkzeuge“), alles andere ins Hauptmenü
        $place = ($def['place'] ?? ($kind === 'tool' ? 'admin' : 'main')) === 'admin' ? 'admin' : 'main';
        $legacy = !in_array($kind, self::KINDS, true);
        if ($legacy) $kind = $place === 'admin' ? 'tool' : 'content';
        $table = isset($def['table']) && preg_match('~^[a-z][a-z0-9_]{0,40}$~', (string) $def['table']) ? (string) $def['table'] : null;
        return [
            'id' => (string) ($def['id'] ?? trim(preg_replace('~[^a-z0-9]+~', '-', strtolower($href)) ?? '', '-')),
            'href' => $href, 'label' => $label, 'kind' => $kind, 'place' => $place,
            'icon' => (string) (($def['icon'] ?? '') ?: 'ext'),
            'perm' => isset($def['perm']) && $def['perm'] !== '' ? (string) $def['perm'] : null,
            'table' => $table,
            'description' => trim((string) ($def['description'] ?? '')),
            'tableLabel' => trim((string) ($def['tableLabel'] ?? '')),
            'feature' => isset($def['feature']) && $def['feature'] !== '' ? (string) $def['feature'] : null,
            'visible' => is_callable($def['visible'] ?? null) ? $def['visible'] : null,
            'source' => $source, 'sourceLabel' => $sourceLabel, 'legacy' => $legacy,
        ];
    }

    /**
     * Seiten des Cores, die nicht fest im Menü stehen (reine Einstellungen). Inhalte und Werkzeuge des Cores (Seiten, Medien,
     * Daten, Anfragen, Chat, KI, Design, Weiterleitungen …) stehen weiter fest in app/Admin/views/layout.php.
     */
    private static function core(): array
    {
        $gt = fn() => \Core\Glossary\Glossary::table();
        return [
            // Glossar (Core\Glossary): Hinweise, schnell hinzufügen, Import/Export, Markierung – gehört zur Datentabelle „glossar“
            self::normalize(['id' => 'glossary', 'href' => '/admin/glossar', 'label' => __('Glossar'), 'icon' => 'glossary', 'kind' => 'settings',
                'table' => \Core\Glossary\Glossary::HANDLE, 'tableLabel' => __('Prüfen & Einstellungen'), 'feature' => \Core\Glossary\Glossary::FEATURE,
                'description' => __('Markierung auf der Website, Hinweise zu Begriffen, schnell hinzufügen, Import & Export'),
                'visible' => fn() => ($t = $gt()) ? can('data.edit', $t['handle']) : can('data.schema')], 'core', __('Funktionen')),
            // Chat zwischen Benutzern (Core\Chat): Ein/Aus und Kanäle
            self::normalize(['id' => 'chat-settings', 'href' => '/admin/chat/einstellungen', 'label' => __('Chat'), 'icon' => 'chatcfg', 'kind' => 'settings',
                'description' => __('Chat ein- oder ausschalten, Kanäle anlegen und verwalten'),
                'visible' => fn() => \Core\Chat\Chat::settingsVisible()], 'core', __('Funktionen')),
            // Schnittstellen: Tokens für REST-API und MCP
            self::normalize(['id' => 'api', 'href' => '/admin/api-tokens', 'label' => __('API & MCP'), 'icon' => 'api', 'kind' => 'settings', 'perm' => 'api.manage',
                'description' => __('Zugänge (Tokens) für Automatisierungen und KI-Assistenten')], 'core', __('Funktionen')),
        ];
    }

    /** Alle Anmeldungen: Core + aktive Erweiterungen (noch ohne Rechteprüfung) */
    public static function all(): array
    {
        $out = [];
        foreach ([...self::core(), ...array_values(self::$extra)] as $p) if ($p) $out[] = $p;
        foreach (Extensions::active() as $x) foreach ($x->pages as $p) $out[] = $p;
        return $out;
    }

    /** Darf der angemeldete Benutzer die Seite sehen? (Recht, Funktion, weitere Bedingung) */
    public static function visible(array $p): bool
    {
        try {
            if ($p['feature'] !== null && !(Features::all()[$p['feature']] ?? false)) return false;   // unbekannte Funktion = aus
            if ($p['perm'] !== null && !can($p['perm'])) return false;
            return $p['visible'] === null || (bool) ($p['visible'])();
        } catch (\Throwable $e) {
            error_log('[Verwaltung] Seite ' . $p['href'] . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Sichtbare Seiten einer oder mehrerer Arten */
    public static function ofKind(string ...$kinds): array
    {
        $out = [];
        foreach (self::all() as $p) {
            if (!in_array($p['kind'], $kinds, true) || !self::visible($p)) continue;
            // Beschriftungen der Erweiterungen übersetzen (lang/{locale}.php der Erweiterung)
            if (str_starts_with($p['source'], 'ext:')) {
                $p['tableLabel'] = $p['tableLabel'] !== '' ? self::tr(['label' => $p['tableLabel']] + $p) : '';
                $p['description'] = $p['description'] !== '' ? self::tr(['label' => $p['description']] + $p) : '';
                $p['label'] = self::tr($p);
            }
            $out[] = $p;
        }
        return $out;
    }

    /**
     * Einträge für die Seitenleiste: nur content und tool, je Platz ('main' = Hauptmenü, 'admin' = Administration).
     * Format wie im Layout: [href, label, key (Symbol), sichtbar]
     */
    public static function nav(string $place = 'main'): array
    {
        $out = [];
        foreach (self::all() as $p) {
            if ($p['place'] !== $place || !in_array($p['kind'], ['content', 'tool'], true)) continue;
            $out[] = [$p['href'], $p['source'] === 'core' ? $p['label'] : self::tr($p), $p['icon'], self::visible($p)];
        }
        return $out;
    }

    /**
     * Gruppen des Abschnitts „Einrichtung“ der Seitenleiste: „Website“, „System“ (inkl. Werkzeuge der Erweiterungen) und „Statistiken“,
     * je [key, label, icon, items => [[href, label, key (Symbol/Menü-Schlüssel), true], …]] – nur sichtbare Punkte, keine leeren Gruppen.
     * Werkzeuge: feste Seiten des Cores + alle Seiten mit Platz 'admin' (nav('admin'), v. a. kind tool der Erweiterungen).
     */
    public static function groups(): array
    {
        $groups = [
            // Website: alles, was Auftritt und Aufbau der Website betrifft – Angaben, Gestaltung, Vorlagen, Bausteine, Adressen
            ['key' => 'website', 'label' => __('Website'), 'icon' => 'globe', 'items' => [
                ['/admin/settings', app()->theme->settingsTitle(), 'settings', can('settings.edit')],
                ['/admin/design', __('Design'), 'design', Features::on('design') && can('design.edit')],
                ['/admin/seitenvorlagen', __('Seitenvorlagen'), 'pagetemplates', can('system.manage')],
                // Block-Designer (Core\Blocks\Custom), Landingpages (Core\Landings), Weiterleitungen und 404-Protokoll (Core\Redirects)
                ['/admin/blocks', __('Blöcke'), 'blocks', Features::on('blocks.custom') && can('blocks.build')],
                ['/admin/landingpages', __('Landingpages'), 'landings', Features::on('landings') && can('system.manage')],
                ['/admin/weiterleitungen', __('Weiterleitungen'), 'redirects', Features::on('redirects') && can('redirects.manage')],
            ]],
            // System: Betrieb der Installation – Grundeinstellungen, Funktionen, Personen
            ['key' => 'einstellungen', 'label' => __('System'), 'icon' => 'gear', 'items' => [
                ['/admin/system', __('Grundeinstellungen'), 'system', can('system.manage')],
                // Funktionen & Erweiterungen (Core\Features): Haupt-Admin schaltet, im Netzwerk liest die Website-Administration mit
                ['/admin/funktionen', __('Funktionen & Erweiterungen'), 'features', Features::canView()],
                // Sammelseite (kind settings): Einstellungen der Funktionen & Erweiterungen – nur wenn es etwas zu zeigen gibt
                [self::HUB, __('Einstellungen der Funktionen'), 'prefs', self::hasSettings()],
                ['/admin/users', __('Benutzer & Rollen'), 'users', can('users.manage')],
                // Werkzeuge/Infoseiten von Funktionen und Erweiterungen (nav('admin'), v. a. kind tool – z. B. Video-Werkzeuge)
                ...self::nav('admin'),
            ]],
            // Auswertungen (Sammelseite kind stats) – als einzelner Punkt, sobald es Statistiken gibt
            ['key' => 'werkzeuge', 'label' => __('Statistiken'), 'icon' => 'stats', 'items' => [
                [self::STATS, __('Statistiken'), 'stats', self::hasStats()],
            ]],
        ];
        $out = [];
        foreach ($groups as $g) {
            $g['items'] = array_values(array_filter($g['items'], fn($n) => (bool) $n[3]));
            if ($g['items']) $out[] = $g;
        }
        return $out;
    }

    /** Einstellungsseiten für die Sammelseite, gruppiert: [['label' => 'Funktionen'|Erweiterung, 'pages' => [...]], …] */
    public static function settings(): array
    {
        return self::grouped(self::ofKind('settings'));
    }

    /** Statistik-Seiten für „Statistiken“, gruppiert wie settings() */
    public static function stats(): array
    {
        return self::grouped(self::ofKind('stats'));
    }

    /** Gibt es etwas für die Sammelseite? (Menüpunkt nur dann) */
    public static function hasSettings(): bool
    {
        return (bool) self::ofKind('settings');
    }

    public static function hasStats(): bool
    {
        return (bool) self::ofKind('stats');
    }

    /** Einstellungsseiten, die zu einer Datentabelle gehören ('table' => …) – Reiter/Knöpfe an der Tabelle */
    public static function forTable(string $handle): array
    {
        return array_values(array_map(fn($p) => ['label' => $p['tableLabel'] !== '' ? $p['tableLabel'] : $p['label']] + $p,
            array_filter(self::ofKind('settings'), fn($p) => $p['table'] === $handle)));
    }

    /** Angemeldete Seite zu einer Adresse (längster passender Pfad) – z. B. um „Einstellungen“ im Menü als aktuell zu markieren */
    public static function match(string $path): ?array
    {
        $best = null;
        foreach (self::all() as $p) {
            if (($path === $p['href'] || str_starts_with($path, rtrim($p['href'], '/') . '/')) && (!$best || strlen($p['href']) > strlen($best['href']))) $best = $p;
        }
        return $best;
    }

    /** Beschriftung einer Erweiterung übersetzen (lang/{locale}.php der Erweiterung, sonst __()) */
    private static function tr(array $p): string
    {
        return str_starts_with($p['source'], 'ext:') ? Extensions::tr(substr($p['source'], 4), $p['label']) : $p['label'];
    }

    private static function grouped(array $pages): array
    {
        $groups = [];
        foreach ($pages as $p) {
            $key = $p['source'];
            $groups[$key] ??= ['key' => $key, 'label' => $p['sourceLabel'] ?: ($key === 'core' ? __('Funktionen') : $key), 'pages' => []];
            $groups[$key]['pages'][] = $p;
        }
        // Core zuerst, Erweiterungen nach Name
        uksort($groups, fn($a, $b) => [$a !== 'core', $groups[$a]['label']] <=> [$b !== 'core', $groups[$b]['label']]);
        return array_values($groups);
    }
}
