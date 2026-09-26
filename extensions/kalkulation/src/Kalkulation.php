<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Kalkulation;

use Core\Extensions;
use Core\Features;

/** Einstieg der Erweiterung: Zustand, Recht, feste Listen (Status, Einheiten, Währungen). */
final class Kalkulation
{
    public const NAME = 'kalkulation';
    public const FEATURE = 'kalkulation';
    public const PERM = 'kalkulation.manage';

    /** Status einer Kalkulation => [Bezeichnung, Badge-Klasse] */
    public static function statuses(): array
    {
        return [
            'draft' => [__('Entwurf'), 'adm-badge--draft'],
            'offered' => [__('Angeboten'), 'adm-badge--adm-warn'],
            'accepted' => [__('Angenommen'), ''],
            'declined' => [__('Abgelehnt'), 'kx-badge--declined'],
            'archived' => [__('Archiv'), 'adm-badge--muted'],
        ];
    }

    /** Vorschläge für die Einheit (frei ergänzbar) */
    public static function units(): array
    {
        return [__('pauschal'), __('Std.'), __('Monat'), __('je Nutzer'), __('je GB'), __('je Seite'), __('je 100 Seiten'), __('je Modul'),
            __('je Block'), __('je Website'), __('je Instanz'), __('je Termin'), __('Stück')];
    }

    public static function currencies(): array
    {
        return ['EUR' => 'Euro (€)', 'CHF' => 'Franken (CHF)', 'USD' => 'US-Dollar (US-$)', 'GBP' => 'Pfund (£)'];
    }

    /** Erweiterung aktiv und Funktion für diese Website eingeschaltet */
    public static function on(): bool
    {
        return Extensions::isActive(self::NAME) && Features::on(self::FEATURE);
    }

    public static function allowed(): bool
    {
        return self::on() && isset(app()->auth) && app()->auth->user() && can(self::PERM);
    }

    public static function asset(string $path): string
    {
        $x = Extensions::active()[self::NAME] ?? null;
        return $x ? $x->asset($path) : base_path() . '/extensions/' . self::NAME . '/' . ltrim($path, '/');
    }

    /** Name eines Benutzers für „geändert von“ (gelöschte Konten: „#12“) */
    public static function userName(?int $id): string
    {
        static $cache = [];
        if (!$id) return '–';
        if (!array_key_exists($id, $cache)) {
            $u = app()->db->fetch('SELECT name, email FROM users WHERE id = ?', [$id]);
            $cache[$id] = $u ? (trim((string) ($u['name'] ?? '')) ?: (string) $u['email']) : '#' . $id;
        }
        return $cache[$id];
    }

    /** Datum „2026-09-26“ → „26.09.2026“ */
    public static function date(?string $ymd): string
    {
        if (!$ymd || !preg_match('~^(\d{4})-(\d{2})-(\d{2})~', $ymd, $m)) return '';
        return $m[3] . '.' . $m[2] . '.' . $m[1];
    }

    /** Zeitstempel „2026-09-26 14:05:00“ → „26.09.2026, 14:05“ */
    public static function dateTime(?string $ts): string
    {
        if (!$ts || !preg_match('~^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})~', $ts, $m)) return '';
        return $m[3] . '.' . $m[2] . '.' . $m[1] . ', ' . $m[4] . ':' . $m[5];
    }
}
