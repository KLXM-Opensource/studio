<?php
declare(strict_types=1);

namespace Core\Blocks;

/**
 * Gemeinsame Regel für Besucher-Filter in Listen (Blöcke „Datenliste“, „Kalender“, Downloads und Karten in fluid …):
 * Redaktionen schalten Filter einmal ein – die Seite wächst mit, ohne bei wenigen Einträgen leer oder überladen zu wirken.
 *
 *  1. „Filter erst ab … Einträgen“ (Blockfeld filter_min, leer = Kit-Vorgabe bzw. MIN_ENTRIES, 0 = immer): Zeigt die Liste
 *     ohne Filter weniger Einträge (veröffentlicht, beim Kalender: kommende Termine), entfallen Filterleiste, Suche und A–Z.
 *  2. Nur Optionen mit Treffern als Schaltflächen (filter_options '' ), auf Wunsch alle (filter_options 'all').
 *  3. Bleiben weniger als MIN_BUTTONS Schaltflächen (ohne „Alle“), entfällt die Leiste. A–Z analog: erst ab zwei Buchstaben.
 *     Die Suche hängt nur an Regel 1.
 *  4. Aktive Auswahl aus der Adresse: Ist die Leiste sichtbar, bleibt die gewählte Option als Schaltfläche stehen – auch ohne
 *     Treffer (die Liste meldet „Keine Einträge für diese Auswahl“ mit „Alle anzeigen“). Ist die Leiste ausgeblendet, wird der
 *     Parameter ignoriert und die ganze Liste erscheint – kein leeres Ergebnis, kein 404.
 *
 * Kit-Vorgabe (theme.php): 'list_filter' => ['min' => 0, 'options' => 'all', 'min_buttons' => 1] gilt für Blöcke ohne eigene
 * Angabe – so behält ein Kit das frühere Verhalten (alle Optionen, Leiste immer).
 */
final class ListFilter
{
    /** Standard für „Filter erst ab … Einträgen“ */
    public const MIN_ENTRIES = 8;
    /** Mindestzahl an Schaltflächen (ohne „Alle“), damit eine Filterleiste erscheint */
    public const MIN_BUTTONS = 2;

    /**
     * Felddefinitionen für den Block-Editor. $extra wird jedem Feld hinzugefügt (z. B. ['variants' => ['list']]).
     * $options = false: ohne die Wahl „auch leere Optionen“ (z. B. wenn die Optionen ohnehin aus den Einträgen stammen).
     */
    public static function fields(array $extra = [], bool $options = true): array
    {
        $out = [
            ['name' => 'filter_min', 'label' => 'Filter erst ab … Einträgen anzeigen', 'type' => 'number', 'width' => 'half', 'placeholder' => (string) self::MIN_ENTRIES,
                'help' => 'Bei weniger Einträgen erscheint die Liste ohne Filter, Suche und A–Z-Leiste – die Seite wächst so mit. Leer = ' . self::MIN_ENTRIES . ', 0 = Filter immer zeigen.'] + $extra,
        ];
        if ($options) {
            $out[] = ['name' => 'filter_options', 'label' => 'Filter-Schaltflächen', 'type' => 'select', 'default' => '', 'width' => 'half',
                'options' => ['' => 'Nur Optionen mit Einträgen', 'all' => 'Alle Optionen, auch ohne Einträge'],
                'help' => 'Bleibt weniger als zwei Schaltflächen übrig, entfällt die Filterleiste.'] + $extra;
        }
        return $out;
    }

    /**
     * Einstellungen eines Blocks: fehlende Werte aus der Kit-Vorgabe 'list_filter' (bzw. $kit), sonst Standard.
     * @return array{min: int, all: bool, buttons: int}
     */
    public static function config(array $d, ?array $kit = null): array
    {
        $kit ??= isset(app()->theme) ? (array) (app()->theme->def['list_filter'] ?? []) : [];
        $min = $d['filter_min'] ?? null;
        $min = is_numeric($min) ? (int) $min : (int) ($kit['min'] ?? self::MIN_ENTRIES);
        $opt = (string) ($d['filter_options'] ?? '');
        return [
            'min' => max(0, $min),
            'all' => $opt === 'all' || ($opt === '' && ($kit['options'] ?? '') === 'all'),
            'buttons' => max(1, (int) ($kit['min_buttons'] ?? self::MIN_BUTTONS)),
        ];
    }

    /** Regel 1: Genug Einträge für Filter, Suche und A–Z? */
    public static function enough(array $d, int $total, ?array $kit = null): bool
    {
        $min = self::config($d, $kit)['min'];
        return $min === 0 || $total >= $min;
    }

    /**
     * Regeln 2–4: Schaltflächen [key => label] aus den Optionen eines Feldes und den Trefferzahlen [key => n] (Reihenfolge wie
     * die Optionen). Leeres Ergebnis = keine Filterleiste. $selected (aus der Adresse) bleibt bei sichtbarer Leiste stehen.
     * $kit: Kit-Vorgabe statt der des aktiven Kits (Selbsttest).
     */
    public static function buttons(array $options, array $counts, array $d, ?string $selected = null, ?array $kit = null): array
    {
        $c = self::config($d, $kit);
        $used = [];
        foreach ($options as $k => $l) {
            if ($c['all'] || (int) ($counts[(string) $k] ?? 0) > 0) $used[(string) $k] = $l;
        }
        if (count($used) < $c['buttons']) return [];
        if ($selected === null || isset($used[$selected]) || !array_key_exists($selected, $options)) return $used;
        $out = [];
        foreach ($options as $k => $l) {
            if (isset($used[(string) $k]) || (string) $k === $selected) $out[(string) $k] = $l;
        }
        return $out;
    }

    /** Regel 3 für Gruppen ohne Zählung (A–Z-Buchstaben, Kategorien aus den Einträgen selbst): genug für eine Leiste? */
    public static function enoughButtons(int $n, array $d = [], ?array $kit = null): bool
    {
        return $n >= self::config($d, $kit)['buttons'];
    }
}
