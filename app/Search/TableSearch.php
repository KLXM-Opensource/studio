<?php
declare(strict_types=1);

namespace Core\Search;

use Core\Data\Calendar;
use Core\Data\Tables;
use Core\Lang;

/**
 * Such-Einstellungen je Datentabelle (Daten → Tabelle → Felder & Einstellungen → „Suche“), gespeichert in
 * settings.search (nur Abweichungen; der Rest ergibt sich automatisch). Geteilte Tabellen: Einstellung der
 * Eigentümer-Website (Schema); welche fremden Einträge eine Website zeigt, entscheidet weiter deren Quellen-Einstellung.
 *
 *   enabled   null = automatisch (an mit Detailseite, sonst aus; Eingangs-Tabellen nie)
 *   fields    [feld => high|normal|low|off]   Standard: Titel hoch, Text normal, Auswahl/Verknüpfung niedrig,
 *                                              E-Mail/Telefon aus (nur ausdrücklich), IBAN nie; Gruppen als Text (ohne IBAN/E-Mail/Telefon)
 *   facets    [feld, …]                       Auswahl-/Verknüpfungsfelder als Filter auf der Ergebnisseite
 *   title, summary, image, date               Anzeige im Treffer (Standard: Titelfeld, Auszug mit Treffern, Bildfeld, Termin/erstes Datum)
 *   label, labels[lang]                       Art im Treffer, z. B. „Termin“ (Standard: Einzahl der Tabelle)
 *   future    nur künftige Termine (Kalender: nächstes Vorkommen; sonst Datumsfeld ≥ heute)
 *   exclude   [field, value]                  Einträge mit diesem Wert nicht finden
 */
final class TableSearch
{
    public const WEIGHTS = ['high' => 'hoch', 'normal' => 'normal', 'low' => 'niedrig', 'off' => 'nicht durchsuchen'];
    /** Feldtypen, die durchsucht werden können */
    public const TYPES = ['text', 'textarea', 'richtext', 'select', 'multiselect', 'relation', 'relations', 'group', 'email', 'tel', 'url', 'link', 'number'];
    /** Nur ausdrücklich (personenbezogen) */
    public const OPT_IN = ['email', 'tel', 'url', 'link', 'number'];
    public const FACET_TYPES = ['select', 'multiselect', 'relation', 'relations'];
    /** Unterfelder wiederholbarer Gruppen, die nie in die Suche gehen */
    private const GROUP_SKIP = ['iban', 'email', 'tel'];

    public static function eligible(array $f): bool
    {
        return in_array($f['type'] ?? '', self::TYPES, true);
    }

    public static function defaultWeight(array $table, array $f): string
    {
        $type = (string) ($f['type'] ?? '');
        if (!self::eligible($f) || in_array($type, self::OPT_IN, true)) return 'off';
        if (($f['name'] ?? '') === ($table['settings']['title_field'] ?? '')) return 'high';
        if (in_array($type, ['text', 'textarea'], true) && preg_match('~(mail|telefon|phone|tel$|fax|iban|konto|geburt|birth)~i', (string) $f['name'])) return 'off';
        return match ($type) {
            'select', 'multiselect', 'relation', 'relations' => 'low',
            default => 'normal',
        };
    }

    /** Wirksame Einstellungen (gespeicherte Abweichungen + automatische Vorgaben) */
    public static function config(array $table): array
    {
        $s = (array) ($table['settings']['search'] ?? []);
        $hasDetail = ($table['settings']['route'] ?? '') !== '';
        $fields = [];
        foreach ($table['fields'] as $f) {
            if (!self::eligible($f)) continue;
            $w = (string) ($s['fields'][$f['name']] ?? '');
            $fields[$f['name']] = isset(self::WEIGHTS[$w]) ? $w : self::defaultWeight($table, $f);
        }
        $names = array_column($table['fields'], 'type', 'name');
        $pick = fn(string $k, array $types) => isset($s[$k], $names[$s[$k]]) && in_array($names[$s[$k]], $types, true) ? (string) $s[$k] : '';
        return [
            'enabled' => !Tables::isInbox($table) && (isset($s['enabled']) && $s['enabled'] !== null ? (bool) $s['enabled'] : $hasDetail),
            'fields' => $fields,
            'facets' => array_values(array_filter((array) ($s['facets'] ?? []), fn($n) => in_array($names[$n] ?? '', self::FACET_TYPES, true))),
            'title' => $pick('title', ['text', 'textarea']) ?: (string) $table['settings']['title_field'],
            'summary' => $pick('summary', ['text', 'textarea', 'richtext']) ?: (string) ($table['settings']['description_field'] ?? ''),
            'image' => $pick('image', ['media']) ?: Tables::imageField($table),
            'date' => $pick('date', ['date', 'datetime']),
            'label' => trim((string) ($s['label'] ?? '')),
            'labels' => array_filter(array_map(fn($v) => trim((string) $v), (array) ($s['labels'] ?? []))),
            'future' => !empty($s['future']),
            'exclude' => isset($s['exclude']['field'], $names[$s['exclude']['field']]) && (string) ($s['exclude']['value'] ?? '') !== ''
                ? ['field' => (string) $s['exclude']['field'], 'value' => (string) $s['exclude']['value']] : null,
        ];
    }

    /** Art im Treffer in der Sprache der Seite */
    public static function label(array $table, ?array $cfg = null): string
    {
        $cfg ??= self::config($table);
        $lang = Lang::current();
        if (!empty($cfg['labels'][$lang])) return $cfg['labels'][$lang];
        return lt($cfg['label'] !== '' ? $cfg['label'] : (string) ($table['singular'] ?: $table['name']));
    }

    /** Eingabe aus dem Formular (settings[search]) bereinigen – gespeichert werden nur Abweichungen von den Vorgaben */
    public static function validate(array $in, array $fields, array $settings): array
    {
        if (($settings['kind'] ?? 'content') === 'inbox') return [];
        $table = ['fields' => $fields, 'settings' => $settings];
        $names = array_column($fields, 'type', 'name');
        $out = [];
        $en = (string) ($in['enabled'] ?? '');
        if ($en === '1' || $en === '0') $out['enabled'] = $en === '1';
        foreach ($fields as $f) {
            $w = (string) ($in['fields'][$f['name']] ?? '');
            if (self::eligible($f) && isset(self::WEIGHTS[$w]) && $w !== self::defaultWeight($table, $f)) $out['fields'][$f['name']] = $w;
        }
        $facets = array_values(array_filter((array) ($in['facets'] ?? []), fn($n) => in_array($names[$n] ?? '', self::FACET_TYPES, true)));
        if ($facets) $out['facets'] = $facets;
        foreach (['title' => ['text', 'textarea'], 'summary' => ['text', 'textarea', 'richtext'], 'image' => ['media'], 'date' => ['date', 'datetime']] as $k => $types) {
            $v = (string) ($in[$k] ?? '');
            if ($v !== '' && in_array($names[$v] ?? '', $types, true)) $out[$k] = $v;
        }
        $label = trim(strip_tags(mb_substr((string) ($in['label'] ?? ''), 0, 40)));
        if ($label !== '') $out['label'] = $label;
        foreach ((array) ($in['labels'] ?? []) as $l => $v) {
            $v = trim(strip_tags(mb_substr((string) $v, 0, 40)));
            if ($v !== '' && Lang::valid((string) $l)) $out['labels'][$l] = $v;
        }
        if (!empty($in['future']) && $in['future'] !== '0') $out['future'] = true;
        $xf = (string) ($in['exclude']['field'] ?? '');
        $xv = trim(mb_substr((string) ($in['exclude']['value'] ?? ''), 0, 120));
        if ($xf !== '' && isset($names[$xf]) && $xv !== '') $out['exclude'] = ['field' => $xf, 'value' => $xv];
        return $out;
    }

    /** Hat die Tabelle ein Datum für „nur künftige“? (Kalender oder Datumsfeld) */
    public static function datable(array $table): bool
    {
        if (Calendar::enabled($table)) return true;
        foreach ($table['fields'] as $f) if (in_array($f['type'], ['date', 'datetime'], true)) return true;
        return false;
    }
}
