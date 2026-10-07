<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

use Core\Chat\Chat;
use Core\Network\Network;

/**
 * Ereignisse des Cores → Push-Mitteilungen an Personen der Verwaltung (Schalter unter Konto → Benachrichtigungen).
 * Inhalte bleiben knapp: Anfragen ohne Inhalt (nur Eingang und Website), Chat mit Absender und Textanfang (Ende-zu-Ende-
 * verschlüsselt an das eigene Gerät), Freigaben mit Gegenstand. Alle Aufrufe werfen nie.
 */
final class Hooks
{
    /** Neue Anfrage in einem Eingang (DataForms::submit) – an alle mit requests.read für diese Tabelle */
    public static function request(array $t): void
    {
        if (!Push::on()) return;
        $name = (string) ($t['name'] ?? $t['handle']);
        Push::notifyUsers(['perm' => 'requests.read', 'table' => (string) $t['handle']], fn() => [
            'title' => __('Neue Anfrage: {name}', ['name' => $name]),
            'body' => __('Über die Website ist eine Anfrage eingegangen ({site}). Inhalte sehen Sie nur in der Verwaltung.', ['site' => site_name()]),
            'url' => '/admin/requests?table=' . rawurlencode((string) $t['handle']), 'tag' => 'req-' . $t['handle'],
        ], 'requests.new', ['ttl' => 2 * 86400, 'urgency' => 'high', 'collapse' => 'req-' . substr((string) $t['handle'], 0, 28)]);
    }

    /** Neuer Eintrag über das Formular einer Inhaltstabelle (DataForms::submit) – an alle mit data.edit für diese Tabelle */
    public static function formEntry(array $t, int $id, string $status): void
    {
        if (!Push::on()) return;
        $name = (string) ($t['name'] ?? $t['handle']);
        Push::notifyUsers(['perm' => 'data.edit', 'table' => (string) $t['handle']], fn() => [
            'title' => __('Neuer Eintrag: {name}', ['name' => $name]),
            'body' => $status === 'draft' ? __('Über das Formular der Website – als Entwurf, bitte prüfen und freigeben.') : __('Über das Formular der Website.'),
            'url' => '/admin/data/' . $t['handle'] . '/' . $id, 'tag' => 'form-' . $t['handle'],
        ], 'forms.entry', ['ttl' => 2 * 86400]);
    }

    /**
     * Chat-Nachricht (Messages::send): Direktnachricht → die anderen Personen des Gesprächs, Kanal → erwähnte Personen.
     * Empfänger dieser Website: „{website}:{id}“ und Netzwerk-Konten „net:{id}“ (ihr Konto auf dieser Website). Personen anderer
     * Websites erreicht die Mitteilung nur, wenn sie ihr Gerät auf dieser Website angemeldet haben (Abos gelten je Website).
     */
    public static function chat(array $room, array $message, array $mentions): void
    {
        if (!Push::on()) return;
        try {
            $me = Chat::me();
            $dm = ($room['kind'] ?? '') === 'dm';
            $keys = $dm ? array_values(array_filter(explode('|', (string) $room['dm_key']), fn($k) => $k !== $me)) : array_values(array_diff($mentions, [$me]));
            $ids = self::localUsers($keys);
            if (!$ids) return;
            $author = trim((string) ($message['author_name'] ?? '')) ?: __('Jemand');
            $text = \Core\Support\Markdown::plain((string) ($message['body'] ?? ''), 140);
            $files = (int) ($message['files'] ?? 0);
            $where = $dm ? '' : '#' . ($room['slug'] ?: $room['name']);
            Push::notifyUsers($ids, fn() => [
                'title' => $dm ? __('Nachricht von {name}', ['name' => $author]) : __('{name} hat Sie in {room} erwähnt', ['name' => $author, 'room' => $where]),
                'body' => $text !== '' ? $text : ($files ? __('Bild') : ''),
                'url' => '/admin/chat?raum=' . (int) $room['id'], 'tag' => 'uc-' . (int) $room['id'], 'quiet' => true,
            ], $dm ? 'chat.direct' : 'chat.mention', ['ttl' => 6 * 3600, 'urgency' => 'high', 'collapse' => 'uc-' . (int) $room['id']]);
        } catch (\Throwable $e) {
            error_log('[push] chat: ' . $e->getMessage());
        }
    }

    /** Personen-Schlüssel des Chats → Benutzer-IDs dieser Website */
    public static function localUsers(array $keys): array
    {
        $site = site()->key;
        $db = app()->db;
        $ids = [];
        foreach ($keys as $k) {
            [$s, $id] = array_pad(explode(':', (string) $k, 2), 2, '');
            if ($s === $site && ctype_digit($id)) {
                $ids[] = (int) $id;
            } elseif ($s === 'net' && ctype_digit($id)) {
                $uid = Network::isNetworkSite() ? (int) $id : (int) $db->fetchValue('SELECT id FROM users WHERE network_uid = ?', [(int) $id]);
                if ($uid) $ids[] = $uid;
            }
        }
        return array_values(array_unique($ids));
    }

    /** Neue Einreichung unter „Eingereicht“ (Review\Queue::run) – an alle mit review.manage */
    public static function review(int $id, string $summary, string $label): void
    {
        if (!Push::on()) return;
        Push::notifyUsers('review.manage', fn() => [
            'title' => __('Zur Freigabe eingereicht'),
            'body' => trim($label . ($summary !== '' ? ' – ' . $summary : '')),
            'url' => '/admin/ai/eingereicht/' . $id, 'tag' => 'review',
        ], 'review.pending', ['ttl' => 2 * 86400, 'collapse' => 'review']);
    }
}
