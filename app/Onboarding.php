<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Erststart einer Website: Kit und Startinhalte wählt die Administration im Willkommen-Bildschirm (/admin/willkommen) –
 * oder sie stehen schon fest (Konfiguration, site:create, Netzwerk „Neue Website“). Erst wenn beides entschieden ist,
 * spielt Core\Seeder die Inhalte ein (App::boot, solange die Website noch keine Seiten hat).
 *
 *  Kit entschieden:      Einstellung sys.theme (Willkommen) oder 'kit'/'theme' in der Konfiguration der Website
 *  Inhalte entschieden:  Einstellung sys.seed (Willkommen) oder 'seed' => 'full' | 'empty' | 'ask' in der Konfiguration;
 *                        ohne 'seed' und mit fest eingetragenem Kit wie bisher sofort mit Startinhalten ('full')
 *
 * Bis dahin sehen Besucher „Diese Website wird gerade eingerichtet“ (503), Angemeldete landen im Willkommen-Bildschirm.
 */
final class Onboarding
{
    public const SEED_KEY = 'sys.seed';
    /** full = Startinhalte des Kits (Musterseiten, Beispieltexte, ggf. Demo) · empty = leere Startseite + Rechtstexte */
    public const MODES = ['full', 'empty'];

    /** Gruppen der Kits im Willkommen-Bildschirm (Reihenfolge) */
    public const CATEGORIES = ['general', 'branch', 'dev'];

    private static ?bool $pending = null;

    /** Wie eingespielt wird – null = noch offen (Kit oder Inhalte nicht entschieden) */
    public static function mode(App $app): ?string
    {
        return self::decide((string) $app->settings->get('sys.theme', ''), (string) $app->settings->get(self::SEED_KEY, ''),
            (string) ($app->config->get('kit') ?: $app->config->get('theme')), $app->config->get('seed'));
    }

    /**
     * Entscheidung aus Einstellungen (sys.theme, sys.seed) und Konfiguration ('kit'/'theme', 'seed') – auch für andere
     * Websites des Netzwerks (Network\Stats). null = Erststart offen.
     */
    public static function decide(string $setTheme, string $setSeed, string $cfgKit, mixed $cfgSeed): ?string
    {
        if ($setTheme === '' && $cfgKit === '') return null;
        if (in_array($setSeed, self::MODES, true)) return $setSeed;
        if ($cfgSeed === true || $cfgSeed === 'full') return 'full';
        if ($cfgSeed === false || $cfgSeed === 'empty') return 'empty';
        return $cfgSeed === null ? 'full' : null;   // 'ask' → Willkommen-Bildschirm
    }

    /** Website noch ohne Seiten und Kit/Inhalte nicht entschieden? (je Anfrage gemerkt) */
    public static function pending(): bool
    {
        return self::$pending ??= !app()->db->fetchValue('SELECT COUNT(*) FROM pages') && self::mode(app()) === null;
    }

    /** Vorauswahl im Willkommen-Bildschirm: [kit, inhalte] aus Einstellung bzw. Konfiguration */
    public static function preset(): array
    {
        $kit = (string) (app()->settings->get('sys.theme', '') ?: (app()->config->get('kit') ?: app()->config->get('theme')));
        $c = app()->config->get('seed');
        $content = (string) app()->settings->get(self::SEED_KEY, '') ?: ($c === false || $c === 'empty' ? 'empty' : ($c === true || $c === 'full' ? 'full' : ''));
        return [isset(self::kits()[$kit]) ? $kit : '', $content];
    }

    /** Auswahl speichern – die nächste Anfrage spielt die Inhalte mit diesem Kit ein */
    public static function choose(string $kit, string $mode): void
    {
        if (!isset(self::kits()[$kit])) throw new \InvalidArgumentException(__('Bitte ein Kit wählen.'));
        if (!in_array($mode, self::MODES, true)) throw new \InvalidArgumentException(__('Bitte wählen, ob Startinhalte eingespielt werden sollen.'));
        app()->settings->set('sys.theme', $kit);
        app()->settings->set(self::SEED_KEY, $mode);
        Fonts::requestSync();   // Schriften des Kits installieren (nächster Aufruf der Verwaltung, Core\Fonts)
        self::$pending = null;
    }

    /**
     * Kits zur Auswahl (erlaubte Kits der Website) mit Bezeichnung und Kurzbeschreibung aus theme.php ('label', 'description',
     * 'description_en') – gelesen ohne die Datei auszuführen (wie Theme::available).
     * Dazu 'category' (general | branch | dev – Gruppe im Willkommen-Bildschirm, Standard general) und 'recommended' (true = empfohlen).
     * @return array<string, array{label: string, description: string, category: string, recommended: bool}>
     */
    public static function kits(?array $themes = null): array
    {
        $en = I18n::locale() === 'en';
        $out = [];
        foreach ($themes ?? site()->allowedThemes() as $name => $label) {
            $src = (string) @file_get_contents((string) Kit::definitionFile(Kit::dir($name)));
            $get = fn(string $k) => preg_match("~^    '" . $k . "'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'~m", $src, $m) ? stripslashes($m[1]) : '';
            $desc = ($en ? $get('description_en') : '') ?: $get('description');
            $cat = $get('category');
            $out[$name] = ['label' => (string) $label, 'description' => $desc, 'category' => in_array($cat, self::CATEGORIES, true) ? $cat : 'general',
                'recommended' => (bool) preg_match("~^    'recommended'\s*=>\s*true~m", $src)];
        }
        return $out;
    }

    /** Bezeichnungen der Gruppen (Willkommen-Bildschirm, Netzwerk „Neue Website“) */
    public static function categoryLabel(string $cat): string
    {
        return match ($cat) { 'branch' => __('Für Branchen und Themen'), 'dev' => __('Für Entwickler'), default => __('Allgemein') };
    }

    public static function reset(): void
    {
        self::$pending = null;
    }
}
