<?php
declare(strict_types=1);

namespace Core;

/**
 * Protokoll „Funktionen & Erweiterungen“ (Tabelle feature_log je Website): wer hat wann welche Funktion bzw. Erweiterung
 * geschaltet oder die Freigabe geändert. In Netzwerk-Installationen zusätzlich im Netzwerk-Protokoll (network_log).
 */
final class FeatureLog
{
    /** $kind: feature | extension | delegate | release */
    public static function add(string $kind, string $target, ?bool $old, ?bool $new, string $detail = ''): void
    {
        $u = isset(app()->auth) ? app()->auth->user() : null;
        $fmt = fn(?bool $v) => $v === null ? null : ($v ? 'an' : 'aus');
        try {
            app()->db->insert('feature_log', ['created_at' => now(), 'user_id' => $u ? (int) $u['id'] : null,
                'user_email' => $u ? mb_substr((string) $u['email'], 0, 190) : (PHP_SAPI === 'cli' ? 'Kommandozeile' : null),
                'kind' => $kind, 'target' => mb_substr($target, 0, 80), 'old_value' => $fmt($old), 'new_value' => $fmt($new),
                'detail' => $detail !== '' ? mb_substr($detail, 0, 190) : null,
                'ip_hash' => PHP_SAPI === 'cli' || !app()->request ? null : substr(hash_hmac('sha256', app()->request->ip(), app()->key()), 0, 32)]);
        } catch (\Throwable $e) {
            error_log('[features] log: ' . $e->getMessage());
        }
        if (Sites::multi()) {
            try {
                Network\Network::log('features.' . $kind, site()->key, $u ? (string) $u['email'] : null, trim($target . ' → ' . ($fmt($new) ?? '–') . ($detail !== '' ? ' · ' . $detail : '')));
            } catch (\Throwable) {
            }
        }
    }

    /** @return list<array> die letzten Einträge (neueste zuerst) */
    public static function recent(int $n = 10): array
    {
        try {
            return app()->db->fetchAll('SELECT * FROM feature_log ORDER BY id DESC LIMIT ' . max(1, min(100, $n)));
        } catch (\Throwable) {
            return [];
        }
    }
}
