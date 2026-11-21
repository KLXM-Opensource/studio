<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Network;

use Core\Http\Request;
use Core\Sites;

/**
 * Netzwerk-Bereich der Verwaltung (/admin/network/…): eigener Bereich mit dunklem Kopf „NETZWERK“, eigener Seitenleiste und
 * Umschalter Netzwerk ↔ Website – nur in Installationen mit mehreren Websites (Sites::multi()), nur auf der Netzwerk-Website,
 * nur für Netzwerk-Konten (Rolle network). Alles, was für die ganze Installation gilt (Geplante Aufgaben, Sicherung außer Haus,
 * Kits, Schriften, Branding, Dateien-Integrität, geteilte Medien/Daten, KI-Verbindungen, Netzwerk-Domain, Adresse der Verwaltung,
 * Agentur-Zugang per OpenID Connect, Netzwerk-Konten, E-Mail des Netzwerks), liegt dort – die Verwaltung einer Website zeigt nur
 * Website-Eigenes.
 *
 * Umzug ohne zweite Logik: dieselben Controller und Ansichten, zwei Hüllen. Alte Adressen leiten weiter (redirect(), aus
 * Core\App vor dem Router), Rücksprünge nach dem Speichern laufen über path() (AdminController::back). In Einzelinstallationen
 * bleibt alles an seinem bisherigen Platz.
 */
final class Area
{
    /** Umgezogene Seiten: alte Adresse → Adresse im Netzwerk-Bereich (nur Seitenaufrufe; Formulare senden weiter an die alten Adressen) */
    public const MOVED = [
        '/admin/system/aufgaben' => '/admin/network/aufgaben',
        '/admin/system/sicherung' => '/admin/network/sicherung',
        '/admin/system/kits' => '/admin/network/kits',
        '/admin/system/fonts' => '/admin/network/schriften',
        '/admin/system/branding' => '/admin/network/branding',
    ];

    /** Umgezogene Reiter einer Seite (Sprungmarke bzw. deren Präfix → Adresse im Netzwerk-Bereich) – nur für Netzwerk-Konten */
    public const MOVED_HASH = [
        '/admin/system' => ['pools' => '/admin/network/geteilt', 'ki' => '/admin/network/ki', 'adminpath' => '/admin/network/anmeldung'],
        '/admin/users/sicherheit' => ['oidc' => '/admin/network/anmeldung'],
        '/admin/network/administratoren' => ['net-host' => '/admin/network/anmeldung', 'net-auth' => '/admin/network/anmeldung'],
    ];

    /** Seiten des Netzwerk-Bereichs mit eigenem Controller (Routen in app/routes.php vor /admin/network/{section}) */
    public const PAGES = ['aufgaben', 'sicherung', 'kits', 'schriften', 'branding', 'integritaet'];

    /** Gibt es den Netzwerk-Bereich? Nur mit mehreren Websites – Einzelinstallationen behalten alles in den Grundeinstellungen */
    public static function active(): bool
    {
        return Sites::multi();
    }

    /** Ist die aktuelle Seite Teil des Netzwerk-Bereichs (dunkler Kopf, eigene Seitenleiste)? */
    public static function here(?string $path = null): bool
    {
        if (!self::active() || !Network::isNetworkSite() || !Network::isNetworkUser()) return false;
        $path ??= (string) (app()->request->path ?? '');
        return $path === '/admin/network' || str_starts_with($path, '/admin/network/');
    }

    /**
     * Ziel eines Rücksprungs bzw. Links auf eine umgezogene Seite umschreiben (Pfad, Abfrage und Sprungmarke bleiben erhalten).
     * Einzelinstallation: unverändert.
     */
    public static function path(string $to): string
    {
        if (!self::active()) return $to;
        $hash = null;
        if (($h = strpos($to, '#')) !== false) { $hash = substr($to, $h + 1); $to = substr($to, 0, $h); }
        $query = '';
        if (($q = strpos($to, '?')) !== false) { $query = substr($to, $q); $to = substr($to, 0, $q); }
        $tail = fn(): string => $query . ($hash !== null ? '#' . $hash : '');
        if (isset(self::MOVED[$to])) return self::MOVED[$to] . $tail();
        if ($hash !== null && isset(self::MOVED_HASH[$to]) && Network::isNetworkUser()
            && ($to !== '/admin/users/sicherheit' || Network::isNetworkSite())) {
            foreach (self::MOVED_HASH[$to] as $prefix => $new) {
                if ($hash === $prefix || str_starts_with($hash, $prefix . '-')) return $new . $tail();
            }
        }
        return $to . $tail();
    }

    /**
     * Weiterleitung für Seitenaufrufe (GET/HEAD): alte Adresse → Netzwerk-Bereich (301 auf der Netzwerk-Website, sonst 302 zur
     * Netzwerk-Website), Adresse des Netzwerk-Bereichs auf einer anderen Website → Netzwerk-Website, in Einzelinstallationen
     * zurück an den alten Platz. null = keine Weiterleitung. @return ?array{0: string, 1: int}
     */
    public static function redirect(Request $r): ?array
    {
        if (!in_array($r->method, ['GET', 'HEAD'], true)) return null;
        $q = $r->query ? '?' . http_build_query($r->query) : '';
        $page = str_starts_with($r->path, '/admin/network/') ? substr($r->path, strlen('/admin/network/')) : null;
        if (!self::active()) {
            // Einzelinstallation: Adressen des Netzwerk-Bereichs führen auf den bisherigen Platz
            $old = array_search($r->path, self::MOVED, true);
            if ($old !== false) return [url((string) $old) . $q, 302];
            if ($page === 'integritaet') return [url('/admin/system/integritaet') . $q, 302];
            return null;
        }
        $new = self::MOVED[$r->path] ?? null;
        if ($new === null) {
            // Bereich des Netzwerks auf einer anderen Website aufgerufen → Netzwerk-Website (dort gilt die Anmeldung des Netzwerk-Kontos)
            if ($page === null || Network::isNetworkSite() || !in_array(explode('/', $page)[0], [...self::PAGES, ...self::sectionKeys()], true)) return null;
            $new = $r->path;
        }
        if (Network::isNetworkSite()) return [url($new) . $q, 301];
        return [Network::siteLink(Network::siteKey(), $new) . $q, 302];
    }

    /** Schlüssel der Bereiche, die NetworkController selbst darstellt (/admin/network/{bereich}) */
    public static function sectionKeys(): array
    {
        return ['administratoren', 'anmeldung', 'email', 'neu', 'geteilt', 'ki', 'speicher', 'protokoll'];
    }

    /**
     * Seitenleiste des Netzwerk-Bereichs: Gruppen mit Punkten [key, href, label, icon, warn]. Punkte ohne Recht bzw. ohne die
     * zugehörige Funktion entfallen.
     * @return list<array{label: string, items: list<array{0: string, 1: string, 2: string, 3: string, 4: string}>}>
     */
    public static function nav(): array
    {
        $nm = \Core\Mail\Accounts::network();
        $mailWarn = $nm && \Core\Mail\Accounts::ready($nm) ? '' : __('Noch kein E-Mail-Konto des Netzwerks');
        $bk = array_filter(\Core\Backup\Offsite::state(), fn($s) => !empty($s['error'])) ? __('Letzte Sicherung fehlgeschlagen') : '';
        $files = \Core\Integrity::lastFiles();
        $igWarn = $files && array_filter((array) $files['findings'], fn($x) => $x['level'] === 'error') ? __('Unerwartete Änderungen gefunden') : '';
        $groups = [
            ['label' => '', 'items' => [
                ['', '/admin/network', __('Websites'), 'globe', ''],
                ['administratoren', '/admin/network/administratoren', __('Administratoren'), 'users-three', ''],
                ['anmeldung', '/admin/network/anmeldung', __('Anmeldung & Domain'), 'lock-key', ''],
                ['email', '/admin/network/email', __('E-Mail'), 'envelope-simple', $mailWarn],
            ]],
            ['label' => __('Gestaltung & Inhalte'), 'items' => array_values(array_filter([
                \Core\KitPackages::canManage() ? ['kits', '/admin/network/kits', __('Kits'), 'package', ''] : null,
                \Core\Fonts::canManage() ? ['schriften', '/admin/network/schriften', __('Schriften'), 'text-t', ''] : null,
                \Core\Branding::canEdit() ? ['branding', '/admin/network/branding', __('Branding & Support'), 'paint-brush', ''] : null,
                ['geteilt', '/admin/network/geteilt', __('Geteilte Medien & Daten'), 'share-network', ''],
                ['ki', '/admin/network/ki', __('KI-Verbindungen'), 'sparkle', ''],
            ]))],
            ['label' => __('Betrieb & Sicherheit'), 'items' => array_values(array_filter([
                \Core\Cron::canManage() ? ['aufgaben', '/admin/network/aufgaben', __('Geplante Aufgaben'), 'clock-countdown', \Core\Cron::running() ? '' : __('Cronjob läuft nicht')] : null,
                ['sicherung', '/admin/network/sicherung', __('Sicherung außer Haus'), 'cloud-arrow-up', $bk],
                ['integritaet', '/admin/network/integritaet', __('Integrität der Dateien'), 'shield-check', $igWarn],
                ['speicher', '/admin/network/speicher', __('Speicher'), 'hard-drives', ''],
                ['protokoll', '/admin/network/protokoll', __('Protokoll'), 'clock-counter-clockwise', ''],
            ]))],
        ];
        return $groups;
    }

    /** Aktueller Punkt der Seitenleiste zur Adresse */
    public static function current(string $path): string
    {
        if (!str_starts_with($path, '/admin/network/')) return '';
        $k = explode('/', substr($path, strlen('/admin/network/')))[0];
        return $k === 'neu' ? '' : $k;
    }

    /** Pfad der Brotkrume über dem Titel: im Netzwerk-Bereich „Netzwerk“, sonst „Grundeinstellungen“ (Seiten mit zwei Hüllen) */
    public static function crumb(): string
    {
        return self::here()
            ? '<a href="' . e(url('/admin/network')) . '">' . e(__('Netzwerk')) . '</a>'
            : '<a href="' . e(url('/admin/system')) . '">' . e(__('Grundeinstellungen')) . '</a>';
    }
}
