<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Fields;

/**
 * Bedingungen je Feld einer Datentabelle – eine zentrale Auswertung für Verwaltung, API, MCP, CalDAV/CardDAV
 * (alle über Entries::save) und öffentliche Formulare (DataForms). Spiegel im Browser: resources/js/_conditions.js.
 *
 * Feld-Definition (normalisiert in Tables::validate):
 *   'visible_if'  => ['mode' => 'all'|'any', 'rules' => [['field' => 'anrede', 'op' => '=', 'value' => 'firma'], …]]
 *   'required_if' => ['mode' => …, 'rules' => […]]                         (Pflicht nur, wenn zutreffend)
 *   'compare'     => [['op' => '>', 'field' => 'beginn', 'message' => 'Ende muss nach Beginn liegen'], …]
 * Operatoren: = != filled empty contains > <   ·   Vergleich: > >= < <= = !=
 *
 * Ausgeblendete Felder werden geleert und nicht geprüft; ihr Wert zählt für andere Bedingungen als leer.
 */
final class Rules
{
    public const OPS = ['=', '!=', 'filled', 'empty', 'contains', '>', '<'];
    public const CMP = ['>', '>=', '<', '<=', '=', '!='];
    /** Feldtypen, deren Wert in Bedingungen geprüft werden kann */
    public const SOURCE_TYPES = ['text', 'textarea', 'number', 'bool', 'date', 'datetime', 'time', 'select', 'multiselect', 'email', 'tel',
        'url', 'link', 'color', 'iban', 'relation', 'media', 'file', 'group'];
    /** Feldtypen mit Vergleichsregel („muss größer sein als …“) */
    public const COMPARE_TYPES = ['date', 'datetime', 'time', 'number', 'text'];
    private const MAX_RULES = 10;

    // ================================================================= Definition prüfen (Tables::validate)

    /**
     * Bedingungen eines Feldes bereinigen. $all = [name => typ] aller Felder der Tabelle.
     * Unvollständige Zeilen (ohne Feld) fallen weg; Verweise auf unbekannte Felder sind Fehler.
     */
    public static function normalize(array $in, array $def, array $all, array &$errors, string $key): array
    {
        $label = $def['label'];
        foreach (['visible_if', 'required_if'] as $kind) {
            $group = (array) ($in[$kind] ?? []);
            $rules = [];
            foreach (array_values((array) ($group['rules'] ?? [])) as $r) {
                $field = (string) ($r['field'] ?? '');
                if ($field === '') continue;
                $op = (string) ($r['op'] ?? '=');
                if (!isset($all[$field]) || $field === $def['name']) {
                    $errors[$key] = __('Feld „{label}“: Eine Bedingung verweist auf ein Feld, das es nicht (mehr) gibt.', ['label' => $label]);
                    continue;
                }
                if (!in_array($op, self::OPS, true)) $op = '=';
                // Wiederholbare Gruppe: nur „ist ausgefüllt“ / „ist leer“ (mindestens eine Zeile)
                if ($all[$field] === 'group' && !in_array($op, ['filled', 'empty'], true)) $op = 'filled';
                $value = in_array($op, ['filled', 'empty'], true) ? '' : mb_substr(trim(strip_tags((string) ($r['value'] ?? ''))), 0, 200);
                // Ja/Nein: „Nein“ = leer
                if ($all[$field] === 'bool' && in_array(strtolower($value), ['0', 'nein', 'no', 'false'], true)) $value = '';
                $rules[] = ['field' => $field, 'op' => $op, 'value' => $value];
                if (count($rules) >= self::MAX_RULES) break;
            }
            if ($rules) {
                $def[$kind] = ['mode' => ($group['mode'] ?? '') === 'any' ? 'any' : 'all', 'rules' => $rules];
            }
        }
        if (in_array($def['type'], self::COMPARE_TYPES, true)) {
            $cmp = [];
            foreach (array_values((array) ($in['compare'] ?? [])) as $c) {
                $field = (string) ($c['field'] ?? '');
                if ($field === '') continue;
                if (!isset($all[$field]) || $field === $def['name'] || !in_array($all[$field], self::COMPARE_TYPES, true)) {
                    $errors[$key] = __('Feld „{label}“: Vergleich nur mit einem anderen Datums-, Uhrzeit-, Zahl- oder Textfeld möglich.', ['label' => $label]);
                    continue;
                }
                $cmp[] = [
                    'op' => in_array((string) ($c['op'] ?? ''), self::CMP, true) ? (string) $c['op'] : '>',
                    'field' => $field,
                    'message' => mb_substr(trim(strip_tags((string) ($c['message'] ?? ''))), 0, 200),
                ];
                if (count($cmp) >= 3) break;
            }
            if ($cmp) $def['compare'] = $cmp;
        }
        return $def;
    }

    /** Hat das Feld irgendeine Bedingung? */
    public static function has(array $f): bool
    {
        return !empty($f['visible_if']) || !empty($f['required_if']) || !empty($f['compare']);
    }

    // ================================================================= Auswerten

    /** Wert als vergleichbarer Text bzw. Liste (Mehrfachauswahl) */
    private static function val(mixed $v): string|array
    {
        if (is_array($v)) return array_values(array_map(fn($x) => is_array($x) ? '1' : (string) $x, $v));
        if (is_bool($v)) return $v ? '1' : '';
        if ($v === null) return '';
        if (is_float($v) && floor($v) === $v && abs($v) < 1e15) return (string) (int) $v;
        return trim((string) $v);
    }

    public static function test(mixed $value, string $op, string $x): bool
    {
        $v = self::val($value);
        $lc = fn($s) => mb_strtolower((string) $s);
        $empty = is_array($v) ? !$v : $v === '';
        return match ($op) {
            'filled' => !$empty,
            'empty' => $empty,
            '=' => is_array($v) ? in_array($x, $v, true) : $lc($v) === $lc($x),
            '!=' => is_array($v) ? !in_array($x, $v, true) : $lc($v) !== $lc($x),
            'contains' => is_array($v) ? in_array($x, $v, true) : ($x === '' || str_contains($lc($v), $lc($x))),
            '>', '<' => !$empty && !is_array($v) && ($c = self::cmp($v, $x)) !== 0 && ($op === '>' ? $c > 0 : $c < 0),
            default => false,
        };
    }

    /** -1/0/1: Zahlen numerisch, sonst Text (ISO-Datum/Uhrzeit sind so sortierbar) */
    private static function cmp(string $a, string $b): int
    {
        $num = '~^-?\d+(\.\d+)?$~';
        if (preg_match($num, $a) && preg_match($num, $b)) return (float) $a <=> (float) $b;
        return strcmp(mb_strtolower($a), mb_strtolower($b)) <=> 0;
    }

    private static function group(?array $g, callable $get): bool
    {
        if (!$g || empty($g['rules'])) return true;
        $any = ($g['mode'] ?? 'all') === 'any';
        foreach ($g['rules'] as $r) {
            $ok = self::test($get($r['field']), $r['op'], (string) $r['value']);
            if ($any && $ok) return true;
            if (!$any && !$ok) return false;
        }
        return !$any;
    }

    /**
     * Sichtbarkeit aller Felder [name => bool]. Ausgeblendete Felder zählen für andere Bedingungen als leer
     * (wiederholt, bis sich nichts mehr ändert – Ketten wie A → B → C funktionieren).
     */
    public static function visibility(array $fields, array $values): array
    {
        $vis = array_fill_keys(array_column($fields, 'name'), true);
        $get = fn($n) => !empty($vis[$n]) ? ($values[$n] ?? null) : null;
        for ($pass = 0; $pass <= count($fields); $pass++) {
            $changed = false;
            foreach ($fields as $f) {
                if (empty($f['visible_if'])) continue;
                $ok = self::group($f['visible_if'], $get);
                if ($ok !== $vis[$f['name']]) { $vis[$f['name']] = $ok; $changed = true; }
            }
            if (!$changed) break;
        }
        return $vis;
    }

    /**
     * Regeln auf bereinigte Werte anwenden: ausgeblendete Felder leeren (und ihre Fehler verwerfen),
     * „Pflicht wenn“ und Vergleiche prüfen. Meldungen in der Sprache von Fields::$site.
     * @return array{0: array, 1: array} [Werte, Fehler]
     */
    public static function apply(array $fields, array $values, array $errors): array
    {
        if (!array_filter($fields, [self::class, 'has'])) return [$values, $errors];
        $vis = self::visibility($fields, $values);
        $byName = array_column($fields, null, 'name');
        foreach ($fields as $f) {
            $n = $f['name'];
            if (!$vis[$n]) {
                $values[$n] = Fields::emptyValue(['type' => in_array($f['type'], ['relations'], true) ? 'multiselect' : $f['type']]);
                unset($errors[$n]);
                foreach (array_keys($errors) as $k) if (str_starts_with((string) $k, "$n.")) unset($errors[$k]);   // Zeilen einer Gruppe
                continue;
            }
            if (isset($errors[$n])) continue;
            $v = $values[$n] ?? null;
            $empty = $v === '' || $v === null || $v === [] || $v === false;
            if (!empty($f['required_if']) && $empty && self::group($f['required_if'], fn($x) => !empty($vis[$x]) ? ($values[$x] ?? null) : null)) {
                $errors[$n] = $f['type'] === 'bool'
                    ? Fields::msg('Bitte bestätigen: {label}.', ['label' => $f['label']])
                    : Fields::msg('Bitte „{label}“ ausfüllen.', ['label' => $f['label']]);
                continue;
            }
            foreach ((array) ($f['compare'] ?? []) as $c) {
                $other = $byName[$c['field']] ?? null;
                if (!$other || empty($vis[$c['field']])) continue;
                $a = self::val($v);
                $b = self::val($values[$c['field']] ?? null);
                if ($a === '' || $b === '' || is_array($a) || is_array($b)) continue;
                $r = self::cmp($a, $b);
                $ok = match ($c['op']) { '>' => $r > 0, '>=' => $r >= 0, '<' => $r < 0, '<=' => $r <= 0, '=' => $r === 0, '!=' => $r !== 0, default => true };
                if (!$ok) {
                    $errors[$n] = $c['message'] !== '' ? (Fields::$site ? lt($c['message']) : $c['message']) : self::compareMessage($f, $other, $c['op']);
                    break;
                }
            }
        }
        return [$values, $errors];
    }

    private static function compareMessage(array $f, array $other, string $op): string
    {
        $p = ['label' => $f['label'], 'other' => $other['label']];
        if (in_array($f['type'], ['date', 'datetime', 'time'], true)) {
            return match ($op) {
                '>' => Fields::msg('„{label}“ muss nach „{other}“ liegen.', $p),
                '>=' => Fields::msg('„{label}“ darf nicht vor „{other}“ liegen.', $p),
                '<' => Fields::msg('„{label}“ muss vor „{other}“ liegen.', $p),
                '<=' => Fields::msg('„{label}“ darf nicht nach „{other}“ liegen.', $p),
                '=' => Fields::msg('„{label}“ muss mit „{other}“ übereinstimmen.', $p),
                default => Fields::msg('„{label}“ muss sich von „{other}“ unterscheiden.', $p),
            };
        }
        return match ($op) {
            '>' => Fields::msg('„{label}“ muss größer sein als „{other}“.', $p),
            '>=' => Fields::msg('„{label}“ muss mindestens so groß sein wie „{other}“.', $p),
            '<' => Fields::msg('„{label}“ muss kleiner sein als „{other}“.', $p),
            '<=' => Fields::msg('„{label}“ darf höchstens so groß sein wie „{other}“.', $p),
            '=' => Fields::msg('„{label}“ muss mit „{other}“ übereinstimmen.', $p),
            default => Fields::msg('„{label}“ muss sich von „{other}“ unterscheiden.', $p),
        };
    }

    // ================================================================= Browser (resources/js/_conditions.js)

    /**
     * Kompakte Konfiguration für das Formular-Skript: {feld: {v: [any, [[feld, op, wert], …]], r: […], q: 0|1}}
     * v = anzeigen wenn, r = Pflicht wenn, q = immer Pflicht. Nur Felder mit Bedingungen.
     */
    public static function client(array $fields): array
    {
        $out = [];
        $pack = fn(array $g) => [($g['mode'] ?? 'all') === 'any' ? 1 : 0, array_map(fn($r) => [$r['field'], $r['op'], (string) $r['value']], $g['rules'])];
        foreach ($fields as $f) {
            $c = [];
            if (!empty($f['visible_if'])) $c['v'] = $pack($f['visible_if']);
            if (!empty($f['required_if'])) $c['r'] = $pack($f['required_if']);
            if ($c) $out[$f['name']] = $c + ['q' => !empty($f['required']) ? 1 : 0];
        }
        return $out;
    }
}
