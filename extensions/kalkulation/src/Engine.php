<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Kalkulation;

/**
 * Rechenkern einer Kalkulation. Dieselben Regeln rechnet der Editor live im Browser (assets/js/kalkulation.js → calc());
 * Änderungen hier immer dort nachziehen (Prüfung: Summen in Liste/Angebot = Summen im Editor).
 *
 * Je Position (Werte je Einheit):
 *   Arbeit       = Std. × Stundensatz (Basis = Satz)  bzw.  Festpreis (Basis „fixed“)
 *   Puffer       = Arbeit × Projektpuffer %, nur wenn an der Position „Puffer“ gesetzt ist (Projekte)
 *   Fremdleistung= Fremdkosten × (1 + Aufschlag %)
 *   Einzelpreis  = Arbeit + Puffer + Fremdleistung, gerundet nach der Rundungsregel (sonst auf Cent)
 *   Gesamt       = Menge × Einzelpreis × (1 − Rabatt %), auf Cent
 * Optionale und alternative Positionen erscheinen im Angebot, zählen aber nicht zur Summe.
 * Summen je Art (einmalig/monatlich): Summe − Nachlass % = netto; USt auf netto; jährlich = monatlich × 12.
 * Intern: Fremdkosten (Einkauf), Stunden (bei Festpreis „Std. intern“), optional Selbstkosten je Stunde → Marge und
 * effektiver Stundensatz = (Umsatz − Fremdkosten) / Stunden.
 */
final class Engine
{
    public const KINDS = ['once', 'monthly'];

    /** @return array{lines: array<string, array>, totals: array, warnings: list<string>} */
    public static function run(array $calc): array
    {
        $p = $calc['params'] ?? [];
        $lines = [];
        $warn = [];
        $sum = ['once' => 0.0, 'monthly' => 0.0];
        $int = ['once' => ['cost' => 0.0, 'hours' => 0.0], 'monthly' => ['cost' => 0.0, 'hours' => 0.0]];
        foreach ((array) ($calc['positions'] ?? []) as $pos) {
            $l = self::line($pos, $p);
            $lines[(string) ($pos['id'] ?? '')] = $l;
            foreach ($l['warnings'] as $w) $warn[$w] = true;
            if (!$l['counted']) continue;
            $k = $l['kind'];
            $sum[$k] += $l['total'];
            $int[$k]['cost'] += $l['cost_total'];
            $int[$k]['hours'] += $l['hours_total'];
        }
        $vat = (float) ($p['vat'] ?? 0);
        $costRate = isset($p['cost_rate']) && $p['cost_rate'] !== null ? (float) $p['cost_rate'] : null;
        $t = [];
        foreach (self::KINDS as $k) {
            $s = Num::r2($sum[$k]);
            $dPct = (float) ($calc['discount_' . $k] ?? 0);
            $disc = Num::r2($s * $dPct / 100);
            $net = Num::r2($s - $disc);
            $v = Num::r2($net * $vat / 100);
            $cost = Num::r2($int[$k]['cost']);
            $hours = round($int[$k]['hours'], 4);
            $staff = $costRate !== null ? Num::r2($hours * $costRate) : 0.0;
            $margin = Num::r2($net - $cost - $staff);
            $t[$k] = ['sum' => $s, 'discount_pct' => $dPct, 'discount' => $disc, 'net' => $net, 'vat' => $v, 'gross' => Num::r2($net + $v),
                'cost' => $cost, 'hours' => $hours, 'staff' => $staff, 'margin' => $margin,
                'margin_pct' => $net != 0.0 ? round($margin / $net * 100, 2) : null,
                'eff_rate' => $hours > 0 ? Num::r2(($net - $cost) / $hours) : null];
        }
        $t['yearly'] = ['net' => Num::r2($t['monthly']['net'] * 12), 'vat' => Num::r2($t['monthly']['vat'] * 12), 'gross' => Num::r2($t['monthly']['gross'] * 12)];
        $t['first_year'] = ['net' => Num::r2($t['once']['net'] + $t['yearly']['net']), 'gross' => Num::r2($t['once']['gross'] + $t['yearly']['gross']),
            'margin' => Num::r2($t['once']['margin'] + $t['monthly']['margin'] * 12)];
        $t['vat_pct'] = $vat;
        return ['lines' => $lines, 'totals' => $t, 'warnings' => array_keys($warn)];
    }

    /** Eine Position rechnen */
    public static function line(array $pos, array $p): array
    {
        $warn = [];
        $kind = in_array($pos['kind'] ?? '', self::KINDS, true) ? $pos['kind'] : 'once';
        $qty = self::f($pos['qty'] ?? null);
        $amount = self::f($pos['amount'] ?? null);
        $cost = self::f($pos['cost'] ?? null) ?? 0.0;
        $disc = self::f($pos['discount'] ?? null) ?? 0.0;
        $basis = (string) ($pos['basis'] ?? 'fixed');
        $name = trim((string) ($pos['name'] ?? '')) ?: __('Position');
        if ($basis === 'fixed') {
            $labour = $amount ?? 0.0;
            $hours = self::f($pos['hours_internal'] ?? null) ?? 0.0;
            $rate = null;
        } else {
            $rate = self::f($p['rates'][$basis]['rate'] ?? null);
            $hours = $amount ?? 0.0;
            if (!isset($p['rates'][$basis])) {
                $warn[] = __('„{name}“: Der Stundensatz dieser Position gibt es in der Grundlage nicht mehr.', ['name' => $name]);
            } elseif ($rate === null && $amount !== null) {
                $warn[] = __('Stundensatz „{rate}“ fehlt in der Grundlage – Positionen damit ergeben 0.', ['rate' => (string) ($p['rates'][$basis]['label'] ?? $basis)]);
            }
            $labour = $hours * ($rate ?? 0.0);
        }
        $bufF = !empty($pos['buffer']) ? 1 + (float) ($p['buffer'] ?? 0) / 100 : 1.0;
        $third = $cost * (1 + (float) ($p['markup'] ?? 0) / 100);
        $unit = self::roundUnit($labour * $bufF + $third, $p);
        $q = $qty ?? 0.0;
        $total = Num::r2($q * $unit * (1 - $disc / 100));
        $optional = !empty($pos['optional']);
        $alt = !empty($pos['alternative']);
        return [
            'kind' => $kind, 'unit_price' => $unit, 'total' => $total, 'counted' => !$optional && !$alt, 'optional' => $optional, 'alternative' => $alt,
            'labour' => Num::r2($labour), 'buffer' => Num::r2($labour * ($bufF - 1)), 'third' => Num::r2($third), 'rate' => $rate,
            'cost_total' => Num::r2($q * $cost), 'hours_total' => $q * $hours, 'empty' => $qty === null && $amount === null && $cost == 0.0,
            'warnings' => $warn,
        ];
    }

    /** Einzelpreis runden: Schritt 0 = auf Cent; sonst auf 1/5/10 … (nächster Wert oder immer aufrunden) */
    public static function roundUnit(float $v, array $p): float
    {
        $step = (float) ($p['round_step'] ?? 0);
        if ($step <= 0) return Num::r2($v);
        $x = $v / $step;
        $n = ($p['round_mode'] ?? 'nearest') === 'up' ? ceil($x - 1e-9) : floor($x + 0.5 + 1e-9);
        return Num::r2($n * $step);
    }

    private static function f(mixed $v): ?float
    {
        return $v === null || $v === '' ? null : Num::parse($v);
    }
}
