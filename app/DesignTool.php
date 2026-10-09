<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * „Design“: Style-Editor als Werkzeug der Werkzeugleiste auf der Website (Core\FrontendTools) – Vorlagen und alle
 * Design-Werte des Kits (Core\Design) in einer Seitenleiste rechts; die Website wird schmaler (body.has-tdrawer) und zeigt jede
 * Änderung sofort auf der aktuellen Seite (ohne iframe, ohne Neuladen). „Speichern“ gilt für die ganze Website, „Abbrechen“
 * stellt den gespeicherten Stand wieder her. Gleiche Werte wie Verwaltung → Design.
 *
 * Nur mit Recht design.edit und eingeschalteter Funktion „design“, beim Ansehen und Bearbeiten. Modul resources/js/design-live.mjs,
 * Endpunkt /admin/api/design-live (Admin\DesignController::live / liveApply – prüft Recht und CSRF selbst).
 */
final class DesignTool
{
    public const ID = 'design';

    public static function definition(): array
    {
        return [
            'id' => self::ID, 'label' => __('Design'), 'icon' => 'palette', 'module' => asset('js/design-live.mjs'),
            'placement' => 'main', 'hint' => __('Farben, Schriften und Formen der Website'),
            'modes' => FrontendTools::ALL_MODES, 'perm' => 'design.edit', 'feature' => 'design',
            'visible' => fn(array $bar) => Design::enabled(),
            'panel' => ['title' => __('Design'), 'size' => 'drawer'],
            'data' => fn(array $bar) => ['endpoint' => url('/admin/api/design-live'), 'admin' => url('/admin/design')],
            'texts' => [
                'presets' => __('Vorlagen'), 'presetsHelp' => __('Ein Klick zeigt die Vorlage sofort auf dieser Seite – gespeichert wird erst mit „Speichern“.'),
                'light' => __('hell'), 'dark' => __('dunkel'), 'darkTitle' => __('Im dunklen Farbschema'),
                'markup' => __('Vorschau lädt die Seite neu'),
                'markupHelp' => __('Diese Einstellung ändert den Aufbau der Seite – für die Vorschau lädt die Seite neu (nur für Sie sichtbar, bis Sie speichern).'),
                'editorDirty' => __('Erst die Änderungen an der Seite speichern oder verwerfen – die Vorschau dieser Einstellung lädt die Seite neu.'),
                'previewing' => __('Vorschau nur für Sie – „Speichern“ übernimmt sie für alle, „Abbrechen“ verwirft sie.'),
                'contrast' => __('Kontrast prüfen'), 'contrastLine' => __('{label} ({mode}): {ratio} : 1 – mindestens {min} : 1'),
                'defaults' => __('Standard'), 'defaultsTitle' => __('Standardwerte des Kits'), 'cancel' => __('Abbrechen'),
                'save' => __('Speichern'), 'saving' => __('Wird gespeichert …'),
                'saved' => __('Design gespeichert – gilt für die ganze Website.'),
                'reload' => __('Kopf- oder Fußbereich ändern sich vollständig beim nächsten Laden der Seite.'),
                'reverted' => __('Design-Änderungen verworfen – es gilt wieder der gespeicherte Stand.'),
                'presetApplied' => __('Vorlage „{label}“ – Vorschau. „Speichern“ übernimmt sie für die Website.'),
                'loadErr' => __('Das Design konnte nicht geladen werden.'), 'admin' => __('Mehr in Verwaltung → Design (Verlauf, Export)'),
                'drawerBusy' => __('Bitte zuerst die Seitenleiste des Blocks schließen.'),
                'fontsFailed' => __('Schrift {fonts} konnte nicht installiert werden – bis dahin zeigt die Website die Ersatzschrift.'),
                'all' => __('Für die ganze Website'),
            ],
        ];
    }
}
