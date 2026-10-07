<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * „Seiteneinstellungen“: Werkzeug in der Werkzeugleiste der Website (Core\FrontendTools) – Redaktion mit Recht pages.manage
 * ändert Titel, Adresse, Titel/Beschreibung für Suchmaschinen, Vorschaubild, Status und Menü der aktuellen Seite, ohne in die
 * Verwaltung zu wechseln. Eintrag im Menü „⋯“ (ersetzt dort den Link in die Verwaltung), beim Ansehen und Bearbeiten; Modal wie
 * „Neue Seite“ (Core\PageTool). Gespeichert wird über denselben Weg wie das Formular der Verwaltung
 * (PageController::saveSettings): Prüfungen, Weiterleitung bei neuer Adresse, Seiten-Cache.
 * Modul resources/js/page-settings.mjs, Endpunkte PageController::apiSettings/apiSettingsSave.
 */
final class PageSettingsTool
{
    public const ID = 'pagesettings';

    /** Nur auf echten Seiten (nicht auf Detailseiten von Einträgen oder im Vorlagen-Editor); Seitenvorlagen nur für die Administration */
    public static function applies(array $bar): bool
    {
        $p = $bar['page'] ?? [];
        if (($bar['kind'] ?? '') !== 'page' || empty($p['id'])) return false;
        if (PageTemplates::isTemplatePage($p)) return can('system.manage');
        return ($p['type'] ?? 'page') === 'page' || NotFound::isPage($p);
    }

    public static function definition(): array
    {
        return [
            'id' => self::ID, 'label' => __('Seiteneinstellungen'), 'icon' => 'gear-six', 'module' => asset('js/page-settings.mjs'),
            'placement' => 'more', 'hint' => __('Titel, Adresse, Suchmaschinen, Vorschaubild'),
            'view' => true, 'perm' => 'pages.manage', 'visible' => fn(array $bar) => self::applies($bar),
            'panel' => ['title' => __('Seiteneinstellungen'), 'size' => 'modal'],
            'data' => fn(array $bar) => [
                'id' => (int) $bar['page']['id'],
                'endpoint' => url('/admin/api/pages/' . (int) $bar['page']['id'] . '/settings'),
                'ai' => url('/admin/api/ai/seo'),
                'admin' => url('/admin/pages/' . (int) $bar['page']['id']),
            ],
            'texts' => [
                'general' => __('Seite'), 'search' => __('Suchmaschinen & soziale Netzwerke'),
                'title' => __('Titel'), 'slug' => __('Adresse (URL)'), 'slugHelp' => __('Leer: wird aus dem Titel gebildet.'),
                'slugFixed' => __('Die Adresse dieser Seite ist fest.'),
                'slugRedirect' => __('Neue Adresse: Die alte leitet automatisch weiter (Weiterleitungen).'),
                'slugBreak' => __('Achtung: Die Seite ist online – unter der alten Adresse ist sie danach nicht mehr erreichbar, alte Links funktionieren nicht mehr.'),
                'reserved' => __('Achtung: Unter /{slug} liegen Dateien des Systems – diese Seite ist dort für Besucher nicht erreichbar. Bitte eine andere Adresse wählen.'),
                'metaTitle' => __('Titel für Suchmaschinen (optional)'),
                'metaTitleHelp' => __('Leer = Seitentitel. Suchmaschinen zeigen etwa 60 Zeichen; angehängt wird „{suffix}“.'),
                'metaDesc' => __('Beschreibung für Suchmaschinen'), 'metaDescHelp' => __('Ideal 120–160 Zeichen. Leer = Standardbeschreibung der Website.'),
                'count' => __('{n} von {max} Zeichen'), 'over' => __('{n} von {max} Zeichen – zu lang'),
                'og' => __('Vorschaubild (soziale Netzwerke)'), 'ogNone' => __('Kein Bild gewählt – es gilt das Standardbild der Website.'),
                'ogPick' => __('Bild wählen'), 'ogUpload' => __('Hochladen'), 'ogClear' => __('Entfernen'),
                'status' => __('Status'), 'draft' => __('Entwurf (nicht öffentlich)'), 'online' => __('Online'),
                'homeOnline' => __('Die Startseite ist immer online.'),
                'menu' => __('Im Hauptmenü zeigen'), 'navTitle' => __('Beschriftung im Menü (optional)'),
                'noindex' => __('Nicht in Suchmaschinen / Sitemap aufnehmen'),
                'ai' => __('Vorschlag für Titel und Beschreibung'), 'aiBusy' => __('Vorschlag wird erstellt …'),
                'aiDone' => __('Vorschlag eingesetzt – bitte prüfen und speichern.'),
                'save' => __('Speichern'), 'saving' => __('Wird gespeichert …'), 'cancel' => __('Abbrechen'),
                'manage' => __('Alle Einstellungen in der Verwaltung'), 'needTitle' => __('Bitte einen Titel angeben.'),
                'loadErr' => __('Die Seiteneinstellungen konnten nicht geladen werden.'),
                'unsaved' => __('Ungespeicherte Änderungen verwerfen?'),
            ],
        ];
    }
}
