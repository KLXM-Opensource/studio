<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Begrüßungsfenster der Verwaltung („Willkommen“ + nächste Schritte, app/Admin/views/_welcome-modal.php): erscheint einmal je
 * Konto automatisch, sobald der Erststart erledigt ist (Core\Onboarding) – für alle, die die Grundeinstellungen pflegen dürfen –
 * und lässt sich jederzeit über Hilfe & Support → „Erste Schritte“ wieder öffnen (?erste-schritte=1).
 * Gemerkt wird das in users.ui_prefs (JSON, Schlüssel „welcome“) – serverseitig beim Anzeigen, damit es auch ohne JavaScript gilt.
 */
final class Welcome
{
    public const QUERY = 'erste-schritte';

    /** Fenster auf dieser Seite zeigen? [zeigen, automatisch] */
    public static function state(?array $user, array $query): array
    {
        if (!$user || !can('system.manage') || Onboarding::pending()) return [false, false];
        if (isset($query[self::QUERY])) return [true, false];
        $p = json_decode((string) ($user['ui_prefs'] ?? ''), true);
        $seen = is_array($p) && !empty($p['welcome']);
        return $seen ? [false, false] : [true, true];
    }

    /** Als gezeigt merken (beim automatischen Anzeigen) */
    public static function markSeen(int $userId): void
    {
        $row = app()->db->fetch('SELECT ui_prefs FROM users WHERE id = ?', [$userId]);
        $p = json_decode((string) ($row['ui_prefs'] ?? ''), true);
        $p = is_array($p) ? $p : [];
        $p['welcome'] = date('c');
        app()->db->update('users', ['ui_prefs' => json_encode($p)], 'id = :id', ['id' => $userId]);
    }

    /**
     * Nächste Schritte mit Stand: Kit, die Prüfungen der Übersicht (Dashboard\Metrics::setupChecks), im Netzwerk „weitere
     * Websites“, dazu die tägliche Sicherung (Cron).
     * @return list<array{ok: bool, label: string, hint: string, link: string, icon: string, external?: bool}>
     */
    public static function steps(): array
    {
        $kit = app()->theme->label();
        $steps = [['ok' => true, 'label' => __('Kit und Startinhalte gewählt'), 'hint' => __('Ihre Website nutzt das Kit „{kit}“. Wechseln geht unter Grundeinstellungen.', ['kit' => $kit]),
            'link' => '/admin/system', 'icon' => 'palette']];
        $hints = [
            '#keys' => [__('Schützt Anfragen aus Formularen – der geheime Schlüssel wird nur einmal angezeigt, gut aufbewahren.'), 'key'],
            '#mail' => [__('Absender und Empfänger für Anfragen, Einladungen und Passwort-Links – mit Testmail.'), 'envelope-simple'],
            '#website' => [__('Die feste Adresse für Links in E-Mails, Sitemap und Suchmaschinen.'), 'globe'],
            '/admin/settings' => [__('Name, Adresse, Kontakt und Logo – erscheinen automatisch überall auf der Website.'), 'identification-card'],
            '/admin/pages' => [__('Texte in [eckigen Klammern] sind Platzhalter der Startinhalte – vor dem Livegang ersetzen.'), 'pencil-simple'],
        ];
        $site = null;   // Angaben der Website (Prüfungen des Kits) als ein Schritt
        foreach (Dashboard\Metrics::setupChecks() as $c) {
            $link = (string) ($c['link'] ?? '');
            if (str_starts_with($link, '/admin/settings')) {
                if ($site === null) { $site = count($steps); $steps[] = ['ok' => true, 'label' => __('Angaben der Website'), 'hint' => $hints['/admin/settings'][0], 'link' => '/admin/settings', 'icon' => 'identification-card', 'missing' => []]; }
                if (!$c['ok']) { $steps[$site]['ok'] = false; $steps[$site]['missing'][] = (string) $c['label']; }
                continue;
            }
            $h = null;
            foreach ($hints as $k => $v) if (str_contains($link, $k)) { $h = $v; break; }
            if ($h === null && str_starts_with($link, '/admin/pages')) $h = $hints['/admin/pages'];
            $steps[] = ['ok' => (bool) $c['ok'], 'label' => (string) $c['label'], 'hint' => (string) ($h[0] ?? ''), 'link' => $link, 'icon' => (string) ($h[1] ?? 'list-checks')];
        }
        if ($site !== null && $steps[$site]['missing']) $steps[$site]['hint'] = __('Noch offen: {list}', ['list' => implode(' · ', $steps[$site]['missing'])]);
        if ($site !== null) unset($steps[$site]['missing']);
        if (Network\Network::isNetworkSite() && Network\Network::isNetworkUser()) {
            $steps[] = ['ok' => count(Sites::all()) > 1, 'label' => __('Weitere Websites anlegen'), 'hint' => __('In der Netzwerk-Übersicht: Kurzname, Domain – Kit und Startinhalte wählt, wer die Website zuerst öffnet.'),
                'link' => '/admin/network#neu', 'icon' => 'buildings'];
        }
        $steps[] = ['ok' => self::recentBackup(), 'label' => __('Tägliche Sicherung einrichten'), 'hint' => __('Per Cron jede Nacht Datenbank und Medien sichern – Befehle im Entwicklerhandbuch.'),
            'link' => '/admin/hilfe/technik#sicherung', 'icon' => 'archive'];
        return $steps;
    }

    /** Gibt es eine Sicherung dieser Website aus den letzten 7 Tagen (storage/backups)? */
    private static function recentBackup(): bool
    {
        foreach (glob(ROOT . '/storage/backups/' . site()->key . '-*.tar.gz') ?: [] as $f) if (filemtime($f) > time() - 7 * 86400) return true;
        return false;
    }
}
