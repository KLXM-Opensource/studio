<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Lang;
use Core\PageAccess;

/**
 * Abos neuer Einträge einer Datentabelle (Funktion „push“): Einstellung settings.push der Tabelle
 *   enabled  Besucher können neue Einträge abonnieren (Standard aus)
 *   title    Titel der Mitteilung mit Platzhaltern {title} {table} {site} {feldname} (leer = Titel des Eintrags)
 *   body     Feld für den Kurztext (leer = Beschreibungsfeld der Tabelle bzw. erstes Textfeld; '-' = kein Text)
 *   filter   ['field' => …, 'value' => …] – nur Einträge, deren Feld diesen Wert hat (z. B. Kategorie „presse“)
 * Thema der Abos: „data:{kurzname}“. Gesendet wird, wenn ein Eintrag zum ersten Mal veröffentlicht wird (published_at war leer:
 * Entries::save und setStatus) – nicht bei späteren Änderungen, nicht beim Abgleich externer Quellen, nie für geschützte Tabellen
 * ohne öffentliche Freigabe (PageAccess) und nur an Abos in der Sprache des Eintrags.
 */
final class Topics
{
    /** Thema einer Tabelle */
    public static function topic(array|string $table): string
    {
        return 'data:' . (is_array($table) ? (string) $table['handle'] : $table);
    }

    /** Können Besucher die Tabelle abonnieren? (Funktion an, Inhaltstabelle, Einstellung an) */
    public static function enabled(array $table): bool
    {
        return Push::on() && ($table['settings']['kind'] ?? 'content') !== 'inbox' && !empty($table['settings']['push']['enabled']);
    }

    /** Einstellung prüfen (Tables::validate) – gespeichert wird nur, was gesetzt ist */
    public static function validate(array $in, array $fields, array $settings): array
    {
        if (($settings['kind'] ?? 'content') === 'inbox') return [];
        $types = array_column($fields, 'type', 'name');
        $out = [];
        $en = $in['enabled'] ?? null;
        if ($en === true || $en === '1' || $en === 1) $out['enabled'] = true;
        $title = trim(strip_tags(mb_substr((string) ($in['title'] ?? ''), 0, 120)));
        if ($title !== '') $out['title'] = $title;
        $body = (string) ($in['body'] ?? '');
        if ($body === '-' || in_array($types[$body] ?? '', ['text', 'textarea', 'richtext'], true)) $out['body'] = $body;
        $ff = (string) ($in['filter']['field'] ?? '');
        $fv = trim(mb_substr((string) ($in['filter']['value'] ?? ''), 0, 120));
        if ($ff !== '' && in_array($types[$ff] ?? '', ['text', 'select', 'multiselect', 'bool', 'relation'], true) && $fv !== '') $out['filter'] = ['field' => $ff, 'value' => $fv];
        return $out;
    }

    /**
     * Nach dem Speichern bzw. Statuswechsel: zum ersten Mal veröffentlicht? → Mitteilung an die Abos der Tabelle (Sprache des Eintrags).
     * $wasPublishedAt: published_at vor der Änderung (leer = noch nie veröffentlicht). Wirft nie.
     */
    public static function published(array $table, int $id, ?string $wasPublishedAt): int
    {
        try {
            if (!self::enabled($table) || ($wasPublishedAt !== null && $wasPublishedAt !== '') || \Core\Sources\Sync::$writing) return 0;
            $e = Entries::find($table, $id);
            if (!$e || ($e['status'] ?? '') !== 'published') return 0;
            // Geschützte Tabelle (Erweiterung, geschützter Bereich): nur, was auch Besucher sehen dürfen
            if (PageAccess::tableRestricted($table)) {
                $e = PageAccess::filterEntries($table, [$e], 'public')[0] ?? null;
                if (!$e) return 0;
            }
            if (!self::matches($table, $e)) return 0;
            return Push::notifyTopic(self::topic($table), self::payload($table, $e), 'data.published',
                ['lang' => Lang::norm($e['lang'] ?? null), 'ttl' => 3 * 86400, 'urgency' => 'normal']);
        } catch (\Throwable $ex) {
            error_log('[push] Tabelle ' . ($table['handle'] ?? '?') . ': ' . $ex->getMessage());
            return 0;
        }
    }

    /** Erfüllt der Eintrag den Filter der Tabelle? */
    public static function matches(array $table, array $e): bool
    {
        $f = $table['settings']['push']['filter'] ?? null;
        if (!is_array($f) || ($f['field'] ?? '') === '') return true;
        $v = $e[$f['field']] ?? null;
        $want = (string) $f['value'];
        if (is_string($v) && str_starts_with($v, '[')) $v = json_decode($v, true) ?? $v;   // Mehrfachauswahl (JSON)
        if (is_array($v)) return in_array($want, array_map('strval', $v), true);
        $fd = Tables::field($table, (string) $f['field']);
        if (($fd['type'] ?? '') === 'bool') return (bool) $v === in_array(mb_strtolower($want), ['1', 'ja', 'yes', 'true', 'an'], true);
        return mb_strtolower(trim((string) $v)) === mb_strtolower($want);
    }

    /** Inhalt der Mitteilung: Titel (Vorlage), Kurztext, Detailseite (absolut), Symbol der Website */
    public static function payload(array $table, array $e): array
    {
        $cfg = (array) ($table['settings']['push'] ?? []);
        $title = Entries::title($table, $e);
        $tpl = trim((string) ($cfg['title'] ?? ''));
        if ($tpl !== '') {
            $title = (string) preg_replace_callback('~\{([a-z_][a-z0-9_]*)\}~', function ($m) use ($table, $e, $title) {
                return match ($m[1]) {
                    'title' => $title, 'table' => (string) ($table['name'] ?? ''), 'site' => site_name(),
                    default => Tables::field($table, $m[1]) ? Entries::text($table, $e, $m[1]) : $m[0],
                };
            }, $tpl);
        }
        $bodyField = (string) ($cfg['body'] ?? '');
        if ($bodyField === '') $bodyField = (string) ($table['settings']['description_field'] ?? '');
        if ($bodyField === '') {
            foreach ($table['fields'] as $f) {
                if (in_array($f['type'], ['textarea', 'richtext'], true)) { $bodyField = (string) $f['name']; break; }
            }
        }
        $body = $bodyField !== '' && $bodyField !== '-' && Tables::field($table, $bodyField) ? Entries::text($table, $e, $bodyField) : '';
        $body = fmt()->excerpt(\Core\EditorNotes::strip($body), 160, false);   // Redaktionsnotizen vor dem Kürzen entfernen
        // Absolute Adresse (Cron ohne kanonische Adresse: erste Domain der Website – url() nicht doppelt anwenden, Basis-Pfad steckt schon drin)
        $url = Entries::absUrl($table, $e) ?? (site_url() . url(Lang::prefix($e['lang'] ?? null) . '/'));
        if (!preg_match('~^https?://~i', $url)) $url = Push::origin() . $url;
        return ['title' => \Core\EditorNotes::strip($title), 'body' => $body, 'url' => $url, 'tag' => 'data-' . $table['handle'] . '-' . (int) $e['id']];
    }

    /** Anzahl der Abos einer Tabelle (alle Sprachen) */
    public static function subscribers(array $table): int
    {
        return (int) app()->db->fetchValue('SELECT COUNT(*) FROM push_subscriptions WHERE topics LIKE ? AND key_fp = ?', ['%,' . self::topic($table) . ',%', Keys::fingerprint()]);
    }

    /** Tabellen, die Besucher abonnieren können (für Block-Auswahl und Status) */
    public static function tables(): array
    {
        if (!Push::on()) return [];
        return array_values(array_filter(Tables::content(), fn($t) => self::enabled($t)));
    }
}
