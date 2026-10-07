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

    private static ?bool $pending = null;

    /** Wie eingespielt wird – null = noch offen (Kit oder Inhalte nicht entschieden) */
    public static function mode(App $app): ?string
    {
        $kit = (string) $app->settings->get('sys.theme', '') !== '' || (string) ($app->config->get('kit') ?: $app->config->get('theme')) !== '';
        if (!$kit) return null;
        $s = (string) $app->settings->get(self::SEED_KEY, '');
        if (in_array($s, self::MODES, true)) return $s;
        $c = $app->config->get('seed');
        if ($c === true || $c === 'full') return 'full';
        if ($c === false || $c === 'empty') return 'empty';
        return $c === null ? 'full' : null;   // 'ask' → Willkommen-Bildschirm
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
        self::$pending = null;
    }

    /**
     * Kits zur Auswahl (erlaubte Kits der Website) mit Bezeichnung und Kurzbeschreibung aus theme.php ('label', 'description',
     * 'description_en') – gelesen ohne die Datei auszuführen (wie Theme::available).
     * @return array<string, array{label: string, description: string}>
     */
    public static function kits(): array
    {
        $en = I18n::locale() === 'en';
        $out = [];
        foreach (site()->allowedThemes() as $name => $label) {
            $src = (string) @file_get_contents((string) Kit::definitionFile(Kit::dir($name)));
            $get = fn(string $k) => preg_match("~^    '" . $k . "'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'~m", $src, $m) ? stripslashes($m[1]) : '';
            $desc = ($en ? $get('description_en') : '') ?: $get('description');
            $out[$name] = ['label' => (string) $label, 'description' => $desc];
        }
        return $out;
    }

    public static function reset(): void
    {
        self::$pending = null;
    }
}
