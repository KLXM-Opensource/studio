<?php
declare(strict_types=1);

namespace Core;

/**
 * Schema-basierte Felder – gemeinsame Grundlage für
 *  - zentrale Einstellungen des Themes, Grundeinstellungen (System),
 *  - Block-Daten (Editor.js-Tools werden aus demselben Schema generiert),
 *  - öffentliche Formulare des Themes.
 *
 * Feld: ['name','label','type','required','help','default','options','fields','width','max','placeholder']
 * Typen: text textarea richtext inline email tel url link number bool select date time
 *        page pages (mehrere Seiten, Core\PagePicker) media file repeater secret heading icon (Symbolauswahl, Wert = Symbolname, Ausgabe: icon($wert))
 * Überschrift mit 'collapse' => true|'open': aufklappbarer Abschnitt bis zur nächsten Überschrift (geöffnet bei Fehlern darin).
 */
final class Fields
{
    public const TYPES = ['text', 'textarea', 'richtext', 'inline', 'email', 'tel', 'url', 'link', 'number',
        'bool', 'select', 'multiselect', 'iban', 'datatable', 'datafield', 'datafields', 'date', 'datetime', 'time', 'recurrence', 'color', 'icon', 'geo', 'page', 'pages', 'media', 'file', 'collection', 'repeater', 'group', 'secret', 'heading'];

    /** Standardwerte eines Schemas als flaches Array */
    public static function defaults(array $fields): array
    {
        $out = [];
        foreach ($fields as $f) {
            if (($f['type'] ?? '') === 'heading') {
                continue;
            }
            $out[$f['name']] = $f['default'] ?? self::emptyValue($f);
        }
        return $out;
    }

    public static function emptyValue(array $f): mixed
    {
        return match ($f['type'] ?? 'text') {
            'bool' => false,
            'repeater', 'group', 'multiselect', 'datafields' => [],
            'pages' => ($f['store'] ?? 'ids') === 'paths' ? '' : [],
            'number', 'media', 'file', 'page', 'collection' => null,
            default => '',
        };
    }

    /**
     * Bereinigt & validiert Eingaben.
     * @return array{0: array, 1: array<string,string>} [Werte, Fehler je Pfad]
     */
    public static function sanitize(array $fields, array $input, array $current = [], string $path = ''): array
    {
        $values = [];
        $errors = [];

        foreach ($fields as $f) {
            $type = $f['type'] ?? 'text';
            if ($type === 'heading') {
                continue;
            }
            $name = $f['name'];
            $p = $path === '' ? $name : "$path.$name";
            // Fehlende Felder (z. B. nach einem Update neu hinzugekommen) erhalten ihren Standardwert
            $raw = array_key_exists($name, $input) ? $input[$name] : ($f['default'] ?? null);
            $label = $f['label'] ?? $name;

            if ($type === 'repeater') {
                $items = [];
                foreach (array_values(is_array($raw) ? $raw : []) as $i => $item) {
                    if (!is_array($item) || self::isBlankItem($item)) {
                        continue;
                    }
                    [$v, $e] = self::sanitize($f['fields'] ?? [], $item, [], "$p." . count($items));
                    $items[] = $v;
                    $errors += $e;
                }
                if (!empty($f['max_items'])) {
                    $items = array_slice($items, 0, (int) $f['max_items']);
                }
                if (!empty($f['required']) && !$items) {
                    $errors[$p] = self::msg('Bitte mindestens einen Eintrag für „{label}“ anlegen.', ['label' => $label]);
                }
                $values[$name] = $items;
                continue;
            }

            if ($type === 'group') {
                // Wiederholbare Gruppe (Datentabellen): leere Zeilen fallen weg; Fehler je Zeile unter „feld.position.unterfeld“
                // (Position wie gesendet, damit sie zur angezeigten Zeile passt), Anzahl unter „feld“
                $items = [];
                $itemLabel = $f['item_label'] ?? 'Eintrag';
                $list = is_array($raw) ? array_values($raw) : (is_scalar($raw) && trim((string) $raw) !== '' ? [[($f['fields'][0]['name'] ?? 'wert') => (string) $raw]] : []);
                foreach ($list as $i => $item) {
                    if (!is_array($item)) continue;
                    $item = array_intersect_key($item, array_flip(array_column($f['fields'] ?? [], 'name')));
                    if (self::isBlankItem($item)) continue;
                    [$v, $e] = self::sanitize($f['fields'] ?? [], $item, [], "$p.$i");
                    $items[] = $v;
                    foreach ($e as $k => $m) $errors[$k] = $itemLabel . ' ' . ($i + 1) . ': ' . $m;
                }
                $n = count($items);
                $min = (int) ($f['min'] ?? (!empty($f['required']) ? 1 : 0));
                $max = (int) ($f['max'] ?? 10);
                if ($n < max($min, !empty($f['required']) ? 1 : 0)) {
                    $errors[$p] = $n === 0 ? self::msg('Bitte „{label}“ ausfüllen.', ['label' => $label])
                        : self::msg('„{label}“: Bitte mindestens {min} angeben.', ['label' => $label, 'min' => $min]);
                } elseif ($max > 0 && $n > $max) {
                    $errors[$p] = self::msg('„{label}“: Höchstens {max} Einträge möglich.', ['label' => $label, 'max' => $max]);
                }
                $values[$name] = $items;
                continue;
            }

            if ($type === 'secret') {
                $raw = is_scalar($raw) ? (string) $raw : '';
                $values[$name] = $raw === '' ? ($current[$name] ?? '') : $raw;
                continue;
            }

            [$val, $err] = self::clean($f, $raw);
            $empty = $val === '' || $val === null || $val === [] || $val === false && $type !== 'bool';
            if ($err === null && !empty($f['required']) && ($empty || ($type === 'bool' && !$val))) {
                $err = $type === 'bool' ? self::msg('Bitte bestätigen: {label}.', ['label' => $label]) : self::msg('Bitte „{label}“ ausfüllen.', ['label' => $label]);
            }
            if ($err === null && !empty($f['max']) && is_string($val) && mb_strlen(strip_tags($val)) > (int) $f['max']) {
                $err = self::msg('„{label}“ ist zu lang (max. {max} Zeichen).', ['label' => $label, 'max' => $f['max']]);
            }
            if ($err !== null) {
                $errors[$p] = $err;
            }
            $values[$name] = $val;
        }
        return [$values, $errors];
    }

    private static function isBlankItem(array $item): bool
    {
        foreach ($item as $k => $v) {
            if ($k === '_open') continue;
            if (is_array($v) ? $v : (trim((string) $v) !== '' && $v !== '0')) {
                return false;
            }
        }
        return true;
    }

    /** true = Meldungen für Besucher (Website-Formulare, Sprache der Seite), sonst Sprache der Verwaltung */
    public static bool $site = false;

    public static function msg(string $text, array $params = []): string
    {
        return self::$site ? lt($text, $params) : __($text, $params);
    }

    // ---------------------------------------------------------------- Mehrsprachigkeit

    /** Wird das Feld je Sprache gepflegt? 'translate' => true|false überschreibt; Standard: Textfelder ja, alles andere nein. */
    public static function translatable(array $f): bool
    {
        if (array_key_exists('translate', $f)) return (bool) $f['translate'];
        return in_array($f['type'] ?? 'text', ['text', 'textarea', 'richtext', 'inline'], true);
    }

    /**
     * Felder für die Übersetzungsansicht: nur übersetzbare (ohne Pflicht, Vorgabe = Wert der Standardsprache als Platzhalter),
     * Zwischenüberschriften nur, wenn danach etwas Übersetzbares folgt.
     */
    public static function forTranslation(array $fields, array $source = []): array
    {
        $out = [];
        $heading = null;
        foreach ($fields as $f) {
            if (($f['type'] ?? '') === 'heading') { $heading = $f; continue; }
            if (!isset($f['name']) || !self::translatable($f)) continue;
            if ($heading) { $out[] = $heading; $heading = null; }
            unset($f['required'], $f['default']);
            $src = $source[$f['name']] ?? null;
            if (is_scalar($src) && trim((string) $src) !== '' && in_array($f['type'] ?? 'text', ['text', 'textarea', 'url', 'link', 'email', 'tel'], true)) {
                $f['placeholder'] = mb_strimwidth(strip_tags((string) $src), 0, 160, '…');
            }
            $f['width'] = $f['width'] ?? null;
            $out[] = $f;
        }
        return $out;
    }

    /** @return array{0: mixed, 1: ?string} */
    public static function clean(array $f, mixed $raw): array
    {
        $type = $f['type'] ?? 'text';
        $label = $f['label'] ?? $f['name'];
        $s = is_scalar($raw) ? trim((string) $raw) : '';

        switch ($type) {
            case 'bool':
                return [in_array($raw, [true, 1, '1', 'on', 'true', 'yes'], true), null];
            case 'number':
                return [$s === '' ? null : (is_numeric($s) ? $s + 0 : null), $s !== '' && !is_numeric($s) ? self::msg('„{label}“ muss eine Zahl sein.', ['label' => $label]) : null];
            case 'media':
            case 'file':
            case 'page':
            case 'collection':
                return [$s === '' ? null : (int) $s, null];
            case 'email':
                if ($s === '') return ['', null];
                return [$s, filter_var($s, FILTER_VALIDATE_EMAIL) ? null : self::msg('„{label}“ ist keine gültige E-Mail-Adresse.', ['label' => $label])];
            case 'url':
                if ($s === '') return ['', null];
                $s = preg_replace('~^http://~i', 'https://', $s);
                $ok = str_starts_with(strtolower($s), 'https://') && filter_var($s, FILTER_VALIDATE_URL);
                return [$s, $ok ? null : self::msg('„{label}“ muss mit https:// beginnen.', ['label' => $label])];
            case 'link':
                if ($s === '') return ['', null];
                // Sonderwerte des Themes (z. B. „doctolib“, „telefon“) und stabile Verweise „page:12“, „entry:news:5“, „media:9“ (Core\Links)
                if (in_array($s, (array) (app()->theme->def['link_keywords'] ?? []), true) || \Core\Links::isRef($s)) {
                    return [$s, null];
                }
                $safe = Sanitizer::safeHref($s);
                return [$safe ?? $s, $safe === null ? self::msg('„{label}“: nur https://, mailto:, tel:, #anker oder /seite erlaubt.', ['label' => $label]) : null];
            case 'tel':
                if ($s === '') return ['', null];
                $ok = str_starts_with($s, '[') || preg_match('~^[+\d][\d\s/()\-–.]{3,}$~u', $s);
                return [$s, $ok ? null : self::msg('„{label}“ ist keine gültige Telefonnummer.', ['label' => $label])];
            case 'iban':
                // Gespeichert ohne Leerzeichen in Großbuchstaben; Prüfung: Land, Länge (SEPA), Prüfziffer (ISO 13616)
                $s = Iban::normalize($s);
                if ($s === '') return ['', null];
                return [$s, match (Iban::error($s)) {
                    null => null,
                    'country' => self::msg('„{label}“: Diese IBAN beginnt nicht mit einem Ländercode aus dem SEPA-Raum (z. B. DE).', ['label' => $label]),
                    'length' => self::msg('„{label}“: Eine IBAN aus {country} hat {n} Zeichen – bitte prüfen.', ['label' => $label, 'country' => substr($s, 0, 2), 'n' => Iban::LENGTHS[substr($s, 0, 2)] ?? '?']),
                    'checksum' => self::msg('„{label}“: Die Prüfziffer stimmt nicht – bitte die IBAN auf Tippfehler prüfen.', ['label' => $label]),
                    default => self::msg('„{label}“ ist keine gültige IBAN (z. B. DE89 3704 0044 0532 0130 00).', ['label' => $label]),
                }];
            case 'date':
                if ($s === '') return ['', null];
                return [$s, preg_match('~^\d{4}-\d{2}-\d{2}$~', $s) ? null : self::msg('„{label}“: ungültiges Datum.', ['label' => $label])];
            case 'time':
                if ($s === '') return ['', null];
                return [$s, preg_match('~^\d{2}:\d{2}$~', $s) ? null : self::msg('„{label}“: Uhrzeit im Format HH:MM.', ['label' => $label])];
            case 'datetime':
                // Gespeichert „JJJJ-MM-TT HH:MM“ (Ortszeit der Website); akzeptiert auch „T“ und Sekunden (datetime-local, ISO)
                if ($s === '') return ['', null];
                if (preg_match('~^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?$~', $s, $m) && checkdate((int) substr($m[1], 5, 2), (int) substr($m[1], 8, 2), (int) substr($m[1], 0, 4))
                    && (int) $m[2] < 24 && (int) $m[3] < 60) {
                    return ["$m[1] $m[2]:$m[3]", null];
                }
                return [$s, self::msg('„{label}“: Datum und Uhrzeit im Format JJJJ-MM-TT HH:MM.', ['label' => $label])];
            case 'recurrence':
                // RFC-5545-RRULE (+ Zeile „EXDATE:…“ mit Ausnahmen) – siehe Core\Data\Calendar
                [$v, $err] = \Core\Data\Calendar::normalize(is_array($raw) ? $raw : $s);
                return [$v, match ($err) {
                    null => null,
                    'freq' => self::msg('„{label}“: Wiederholung nur täglich, wöchentlich, monatlich oder jährlich (FREQ=DAILY|WEEKLY|MONTHLY|YEARLY).', ['label' => $label]),
                    'count' => self::msg('„{label}“: Anzahl der Wiederholungen zwischen 1 und 1000.', ['label' => $label]),
                    'until' => self::msg('„{label}“: Enddatum der Wiederholung ungültig (UNTIL=JJJJMMTT).', ['label' => $label]),
                    default => self::msg('„{label}“: ungültige Wiederholungsregel (RFC 5545, z. B. FREQ=WEEKLY;BYDAY=MO).', ['label' => $label]),
                }];
            case 'datatable':
                // Eingangs-Tabellen (verschlüsselte Anfragen) nur, wo das Feld sie ausdrücklich zulässt (Block „Formular“)
                $dt = $s === '' ? null : \Core\Data\Tables::find($s);
                return [$dt && (!\Core\Data\Tables::isInbox($dt) || !empty($f['inbox'])) ? $s : '', null];
            case 'datafield':
                return [preg_match('~^[a-z][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$~', $s) ? $s : '', null];
            case 'datafields':
                return [array_values(array_filter(array_map('strval', is_array($raw) ? $raw : []), fn($v) => (bool) preg_match('~^[a-z][a-z0-9_]*\.[a-z_][a-z0-9_]*$~', $v))), null];
            case 'pages':
                return [PagePicker::clean($f, $raw), null];
            case 'multiselect':
                $opts = self::options($f);
                $vals = array_values(array_unique(array_filter(array_map('strval', is_array($raw) ? $raw : ($s === '' ? [] : explode(',', $s))), fn($v) => $v !== '' && array_key_exists($v, $opts))));
                return [$vals, null];
            case 'color':
                if ($s === '') return [$f['default'] ?? '', null];
                if (!empty($f['transparent']) && strtolower($s) === 'transparent') return ['transparent', null];   // 'transparent' => true erlaubt „durchsichtig“
                if (preg_match('~^#?([0-9a-f]{3})$~i', $s, $m3)) $s = '#' . $m3[1][0] . $m3[1][0] . $m3[1][1] . $m3[1][1] . $m3[1][2] . $m3[1][2];
                if ($s[0] !== '#') $s = '#' . $s;
                return [strtoupper($s), preg_match('~^#[0-9A-F]{6}$~i', $s) ? null : self::msg('„{label}“: Farbe im Format #RRGGBB.', ['label' => $label])];
            case 'icon':
                // Symbolname (Core\Icons); alte Zeichen werden abgebildet, 'options' = weitere erlaubte Werte (z. B. Theme-eigene Symbole)
                if ($s === '') return [$f['default'] ?? '', null];
                if (array_key_exists($s, self::options($f))) return [$s, null];
                $n = Icons::resolve($s);
                return [$n ?? $s, $n !== null ? null : self::msg('„{label}“: unbekanntes Symbol.', ['label' => $label])];
            case 'geo':
                if ($s === '') return ['', null];
                $p = Maps::parse($s);
                return [$p ? Maps::format($p) : $s, $p ? null : self::msg('„{label}“: Koordinaten im Format Breite, Länge (z. B. 51.1634, 10.4477).', ['label' => $label])];
            case 'select':
                $opts = self::options($f);
                if ($s === '') return ['', null];   // leer: Pflichtprüfung meldet „Bitte … ausfüllen“
                return [$s, array_key_exists($s, $opts) ? null : self::msg('„{label}“: ungültige Auswahl.', ['label' => $label])];
            case 'richtext':
                return [Sanitizer::block($s), null];
            case 'inline':
                return [Sanitizer::inline($s), null];
            case 'textarea':
                return [str_replace("\r\n", "\n", $s), null];
            default:
                return [strip_tags($s), null];
        }
    }

    /** Optionen: ['a'=>'A'] oder ['a','b'] */
    public static function options(array $f): array
    {
        $o = $f['options'] ?? [];
        if (is_string($o)) {
            $o = array_filter(array_map('trim', preg_split('~[\n,]~', $o)));
        }
        return array_is_list($o) ? array_combine($o, $o) : $o;
    }

    // ---------------------------------------------------------------- Rendering (Admin)

    public static function renderForm(array $fields, array $values, array $errors = [], string $prefix = 'f', string $path = ''): string
    {
        $html = '';
        $open = null;   // aufklappbarer Abschnitt: [Kopf, Inhalt, offen?]
        $close = function () use (&$open, &$html): void {
            if ($open === null) return;
            $html .= '<details class="f-sec"' . ($open[2] ? ' open' : '') . '>' . $open[0] . '<div class="f-sec__body">' . $open[1] . '</div></details>';
            $open = null;
        };
        foreach ($fields as $f) {
            if (($f['type'] ?? '') === 'heading') {
                $close();
                if (!empty($f['collapse']) && $path === '') {
                    $help = (!empty($f['help']) ? '<p class="f-help">' . e($f['help']) . '</p>' : '')
                        . (!empty($f['links']) ? '<p class="f-help">' . implode(' · ', array_map(fn($l) => '<a href="' . e(url((string) $l['url'])) . '">' . e((string) $l['label']) . '</a>', (array) $f['links'])) . '</p>' : '');
                    $open = ['<summary class="f-sec__sum"><span class="f-sec__title">' . e($f['label']) . '</span>'
                        . (!empty($f['summary']) ? '<span class="f-sec__hint">' . e((string) $f['summary']) . '</span>' : '') . '</summary>', $help, $f['collapse'] === 'open'];
                    continue;
                }
            }
            $one = self::renderField($f, $values[$f['name'] ?? ''] ?? ($f['default'] ?? self::emptyValue($f)), $errors, $prefix, $path);
            if ($open !== null) {
                $open[1] .= $one;
                $n = $f['name'] ?? null;
                if ($n !== null && array_filter(array_keys($errors), fn($k) => $k === $n || str_starts_with((string) $k, $n . '.'))) $open[2] = true;
            } else {
                $html .= $one;
            }
        }
        $close();
        return $html;
    }

    /** Bindungs-Kontext im Editor für Detailseiten-Vorlagen: ['table' => array, 'bound' => [feld => quelle]] */
    public static ?array $binding = null;

    public static function renderField(array $f, mixed $value, array $errors, string $prefix, string $path = ''): string
    {
        // Feld nur bei bestimmten Varianten des Blocks ('variants' => ['search', …]): Seitenleiste blendet es sonst aus
        // (resources/js/editor.js; ohne Varianten-Auswahl bleibt es sichtbar). Wert bleibt beim Umschalten erhalten.
        if (!empty($f['variants']) && $path === '') {
            $vs = implode(' ', array_map('strval', (array) $f['variants']));
            unset($f['variants']);
            return '<div class="f-vis" data-variants="' . e($vs) . '">' . self::renderField($f, $value, $errors, $prefix, $path) . '</div>';
        }
        if (self::$binding && $path === '' && isset($f['name']) && ($opts = \Core\Data\Entries::bindable(self::$binding['table'], $f['type'] ?? 'text'))) {
            $b = self::$binding;
            self::$binding = null;                           // Feld selbst normal rendern
            $inner = self::renderField($f, $value, $errors, $prefix, $path);
            self::$binding = $b;
            $cur = (string) ($b['bound'][$f['name']] ?? '');
            $sel = '<select name="' . $prefix . '[_bind][' . e($f['name']) . ']" aria-label="' . e(__('{label} aus dem Eintrag', ['label' => $f['label'] ?? $f['name']])) . '" data-bind-select>'
                . '<option value="">' . e(__('– nicht verknüpft –')) . '</option>';
            foreach ($opts as $k => $l) $sel .= '<option value="' . e($k) . '"' . ($k === $cur ? ' selected' : '') . '>' . e($l) . '</option>';
            $sel .= '</select>';
            return '<div class="f-bindwrap' . (($f['width'] ?? '') === 'half' ? ' f-bindwrap--half' : '') . ($cur !== '' ? ' is-bound' : '') . '" data-bindwrap>'
                . '<button type="button" class="f-bindbtn" data-bind-toggle title="' . e(__('„{label}“ aus dem Eintrag übernehmen', ['label' => $f['label'] ?? $f['name']])) . '" aria-label="' . e(__('„{label}“ mit einem Feld des Eintrags verknüpfen', ['label' => $f['label'] ?? $f['name']])) . '" aria-pressed="' . ($cur !== '' ? 'true' : 'false') . '">' . icon('link') . '</button>'
                . $inner
                . '<div class="f-bind"><span class="f-bind__label">' . e(__('{label} aus', ['label' => $f['label'] ?? $f['name']])) . ' <b>' . e($b['table']['singular']) . '</b>:</span>' . $sel
                . '<p class="f-help">' . e(__('Zeigt auf jeder Detailseite den Wert des aufgerufenen Eintrags.')) . '</p></div></div>';
        }
        $type = $f['type'] ?? 'text';
        if ($type === 'heading') {
            return '<h3 class="f-heading">' . e($f['label']) . '</h3>'
                . (!empty($f['help']) ? '<p class="f-help">' . e($f['help']) . '</p>' : '')
                // Optionale Links unter der Überschrift: 'links' => [['label' => …, 'url' => '/admin/…'], …]
                . (!empty($f['links']) ? '<p class="f-help">' . implode(' · ', array_map(fn($l) => '<a href="' . e(url((string) $l['url'])) . '">' . e((string) $l['label']) . '</a>', (array) $f['links'])) . '</p>' : '');
        }
        $name = $f['name'];
        $p = $path === '' ? $name : "$path.$name";
        $inputName = $prefix . '[' . $name . ']';
        $id = 'f-' . preg_replace('~[^a-z0-9]+~i', '-', $prefix . '-' . $name);
        $err = $errors[$p] ?? null;
        $req = !empty($f['required']);
        $label = e($f['label'] ?? $name) . ($req ? ' <span class="req" aria-hidden="true">*</span>' : '');
        $help = !empty($f['help']) ? '<p class="f-help" id="' . $id . '-h">' . e($f['help']) . '</p>' : '';
        $describedBy = trim((!empty($f['help']) ? "$id-h " : '') . ($err ? "$id-e" : ''));
        $aria = ($describedBy ? ' aria-describedby="' . $describedBy . '"' : '') . ($err ? ' aria-invalid="true"' : '') . ($req ? ' required' : '');
        $errHtml = $err ? '<p class="f-error" id="' . $id . '-e">' . e($err) . '</p>' : '';
        $width = ($f['width'] ?? 'full') === 'half' ? ' f--half' : '';
        $ph = !empty($f['placeholder']) ? ' placeholder="' . e($f['placeholder']) . '"' : '';

        if ($type === 'repeater' || $type === 'group') {
            // Gruppe mit altem Einzelwert (Feld war früher Text): als erste Zeile übernehmen
            $items = is_array($value) ? $value : ($type === 'group' && is_scalar($value) && trim((string) $value) !== '' ? [[($f['fields'][0]['name'] ?? '') => (string) $value]] : []);
            return self::renderRepeater($f, $items, $errors, $inputName, $p, $label, $errHtml);
        }

        if ($type === 'datafields') {
            // Felder aller Tabellen; das Skript blendet nur die der gewählten Tabelle ein (data-table)
            $sel = array_map('strval', is_array($value) ? $value : []);
            $h = '<fieldset class="f f--multi f--datafields' . $width . '" data-datafields><legend>' . $label . '</legend>'
                . '<input type="hidden" name="' . $inputName . '[]" value=""><div class="f-multi">';
            foreach (\Core\Data\Tables::content() as $t) {
                $opts = ['_title' => (\Core\Data\Tables::field($t, $t['settings']['title_field'])['label'] ?? 'Titel') . ' (Titel, verlinkt)'];
                if (\Core\Data\Calendar::enabled($t)) $opts['_when'] = __('Termin (Datum, Uhrzeit, Wiederholung)');
                foreach ($t['fields'] as $tf) {
                    if ($tf['name'] !== $t['settings']['title_field']) $opts[$tf['name']] = $tf['label'];
                }
                $opts['published_at'] = 'Veröffentlicht am';
                foreach ($opts as $k => $l) {
                    $v = $t['handle'] . '.' . $k;
                    $h .= '<label class="f-check" data-table="' . e($t['handle']) . '"><input type="checkbox" name="' . $inputName . '[]" value="' . e($v) . '"'
                        . (in_array($v, $sel, true) ? ' checked' : '') . '> <span>' . e($l) . '</span></label>';
                }
            }
            return $h . '</div>' . $help . $errHtml . '</fieldset>';
        }

        if ($type === 'multiselect') {
            $sel = array_map('strval', is_array($value) ? $value : []);
            $rel = !empty($f['relation']) ? ' data-relation="' . e($f['relation']['create']) . '" data-singular="' . e($f['relation']['singular']) . '" data-name="' . e($inputName) . '[]"' : '';
            $h = '<fieldset class="f f--multi' . ($rel ? ' f--relation' : '') . $width . ($err ? ' f--error' : '') . '"' . $rel . ($describedBy ? ' aria-describedby="' . $describedBy . '"' : '') . '><legend>' . $label . '</legend>'
                . '<input type="hidden" name="' . $inputName . '[]" value=""><div class="f-multi">';
            foreach (self::options($f) as $k => $l) {
                $h .= '<label class="f-check"><input type="checkbox" name="' . $inputName . '[]" value="' . e((string) $k) . '"'
                    . (in_array((string) $k, $sel, true) ? ' checked' : '') . '> <span>' . e((string) $l) . '</span></label>';
            }
            return $h . '</div>' . $help . $errHtml . '</fieldset>';
        }

        if ($type === 'pages') {
            return PagePicker::render($f, $value, $id, $inputName, $label, $help, $errHtml, $describedBy, $width);
        }

        if ($type === 'bool') {
            return '<div class="f f--bool' . $width . '"><input type="hidden" name="' . $inputName . '" value="0">'
                . '<label class="f-check"><input type="checkbox" id="' . $id . '" name="' . $inputName . '" value="1"'
                . ($value ? ' checked' : '') . $aria . '> <span>' . $label . '</span></label>' . $help . $errHtml . '</div>';
        }

        if ($type === 'recurrence') {
            // Wiederholung: Regel-Editor (resources/js/_rrule.js) über dem Rohfeld „Erweitert“ – ohne JavaScript bleibt das Rohfeld
            $start = !empty($f['start_field']) ? $prefix . '[' . $f['start_field'] . ']' : '';
            $allDay = !empty($f['all_day_field']) ? $prefix . '[' . $f['all_day_field'] . ']' : '';
            return '<fieldset class="f f--rrule' . $width . ($err ? ' f--error' : '') . '" data-rrule data-start="' . e($start) . '" data-allday="' . e($allDay) . '"'
                . ($describedBy ? ' aria-describedby="' . $describedBy . '"' : '') . '><legend>' . $label . '</legend>'
                . '<div class="rr" data-rrule-ui hidden></div>'
                . '<details class="rr-adv"' . ($err ? ' open' : '') . '><summary>' . e(__('Erweitert: Regel nach RFC 5545 (RRULE)')) . '</summary>'
                . '<label class="rr-adv__label" for="' . $id . '">' . e(__('Regel, optional zweite Zeile „EXDATE:“ mit Ausnahmen (JJJJ-MM-TT, durch Komma getrennt)')) . '</label>'
                . '<textarea id="' . $id . '" name="' . $inputName . '" rows="2" spellcheck="false" autocomplete="off" placeholder="FREQ=WEEKLY;BYDAY=MO;UNTIL=20261231" data-rrule-raw>' . e(is_scalar($value) ? (string) $value : '') . '</textarea></details>'
                . $help . $errHtml . '</fieldset>';
        }

        $v = is_scalar($value) ? (string) $value : '';
        $control = match ($type) {
            'textarea' => '<textarea id="' . $id . '" name="' . $inputName . '" rows="' . ($f['rows'] ?? 4) . '"' . $aria . $ph . '>' . e($v) . '</textarea>',
            'richtext', 'inline' => '<div class="rte" data-mode="' . $type . '"><div class="rte-bar" role="toolbar" aria-label="Formatierung">'
                . '</div><div class="rte-area" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="' . $id . '-l">' . $v . '</div>'
                . '<input type="hidden" id="' . $id . '" name="' . $inputName . '" value="' . e($v) . '"></div>',
            'select' => !empty($f['relation'])
                ? '<span class="f-relselect" data-relation="' . e($f['relation']['create']) . '" data-singular="' . e($f['relation']['singular']) . '">' . self::renderSelect($id, $inputName, self::options($f), $v, $aria, !$req) . '</span>'
                : self::renderSelect($id, $inputName, self::options($f), $v, $aria, !$req),
            'color' => !empty($f['transparent'])
                ? '<span class="f-color' . ($v === 'transparent' ? ' is-transparent' : '') . '"><input type="color" value="' . e($v && $v !== 'transparent' ? $v : '#FFFFFF') . '" aria-hidden="true" tabindex="-1" data-color-for="' . $id . '">'
                    . '<input type="text" id="' . $id . '" name="' . $inputName . '" value="' . e($v) . '" maxlength="11" pattern="#[0-9A-Fa-f]{6}|transparent" spellcheck="false"' . $aria . '>'
                    . '<label class="f-color__none"><input type="checkbox" data-color-none="' . $id . '"' . ($v === 'transparent' ? ' checked' : '') . '> ' . e(__('Transparent')) . '</label></span>'
                : '<span class="f-color"><input type="color" value="' . e($v ?: '#000000') . '" aria-hidden="true" tabindex="-1" data-color-for="' . $id . '">'
                    . '<input type="text" id="' . $id . '" name="' . $inputName . '" value="' . e($v) . '" maxlength="7" pattern="#[0-9A-Fa-f]{6}" spellcheck="false"' . $aria . '></span>',
            'icon' => Icons::picker($id, $inputName, $v, ['optional' => !$req, 'attrs' => $aria]),
            'geo' => self::renderGeo($id, $inputName, $v, $aria, $f),
            'iban' => '<input type="text" id="' . $id . '" name="' . $inputName . '" value="' . e($v !== '' ? Iban::format($v) : '') . '" maxlength="42" autocomplete="off" spellcheck="false" autocapitalize="characters" placeholder="DE00 0000 0000 0000 0000 00" data-iban' . $aria . '>',
            'datetime' => '<input type="datetime-local" id="' . $id . '" name="' . $inputName . '" value="' . e(str_replace(' ', 'T', $v)) . '" step="60"' . $aria . '>',
            'page' => self::renderSelect($id, $inputName, self::pageOptions(), $v, $aria, true),
            'collection' => self::renderSelect($id, $inputName, array_column(Media::collections(), 'name', 'id'), $v, $aria, true),
            'datatable' => self::renderSelect($id, $inputName, array_column(!empty($f['inbox']) ? array_values(array_filter(\Core\Data\Tables::all(),
                fn($t) => !\Core\Data\Tables::isInbox($t) || \Core\Data\Inbox::available())) : \Core\Data\Tables::content(), 'name', 'handle'), $v, $aria . ' data-datatable', !$req),
            'datafield' => self::renderDataField($id, $inputName, $v, $aria, $f),
            'media', 'file' => '<div class="media-field" data-accept="' . ($type === 'media' ? (($f['accept'] ?? '') === 'visual' ? 'visual' : 'image') : 'file') . '">'
                . '<input type="hidden" id="' . $id . '" name="' . $inputName . '" value="' . e($v) . '">'
                . '<div class="media-field-preview">' . self::mediaPreview($v === '' ? null : (int) $v) . '</div>'
                . '<button type="button" class="btn btn--small" data-media-pick>Auswählen …</button> '
                . '<button type="button" class="btn btn--small btn--ghost" data-media-clear>Entfernen</button></div>',
            'link' => self::renderLink($id, $inputName, $v, $aria . $ph),
            'secret' => '<input type="password" id="' . $id . '" name="' . $inputName . '" value="" autocomplete="new-password"'
                . ' placeholder="' . ($v !== '' ? '•••••••• (gespeichert – leer lassen = unverändert)' : '') . '"' . $aria . '>',
            default => '<input type="' . match ($type) {
                'email' => 'email', 'tel' => 'tel', 'url' => 'url', 'number' => 'number', 'date' => 'date', 'time' => 'time', default => 'text'
            } . '" id="' . $id . '" name="' . $inputName . '" value="' . e($v) . '"' . $aria . $ph
                . ($type === 'link' ? ' list="cms-links"' : '')
                // Zahlen: Dezimalwerte erlauben (sonst meldet der Browser „0.4“ als ungültig); eigene Schrittweite per 'step'
                . ($type === 'number' ? ' step="' . e((string) ($f['step'] ?? 'any')) . '"' : '')
                . (!empty($f['max']) ? ' data-max="' . (int) $f['max'] . '"' : '') . '>',
        };

        return '<div class="f' . $width . ($err ? ' f--error' : '') . '"><label for="' . $id . '" id="' . $id . '-l">' . $label . '</label>'
            . $control . $help . $errHtml . '</div>';
    }

    /**
     * Link: Eingabefeld (Adresse von Hand) + „Auswählen …“ (Linkauswahl, resources/js/_links.js) + lesbare Anzeige des Ziels.
     * Werte: https://…, mailto:, tel:, #anker, /pfad, Sonderziele des Themes, stabile Verweise page:12, entry:news:5, media:9 (Core\Links).
     */
    public static function renderLink(string $id, string $name, string $v, string $attrs = ''): string
    {
        return '<div class="f-link" data-link-field><div class="f-link__row">'
            . '<input type="text" id="' . $id . '" name="' . $name . '" value="' . e($v) . '" list="cms-links" autocomplete="off" spellcheck="false"' . $attrs . '>'
            . '<button type="button" class="btn btn--small" data-link-pick aria-haspopup="dialog">' . e(__('Auswählen …')) . '</button></div>'
            . self::linkChip($v) . '</div>';
    }

    /** Anzeige des gewählten Linkziels (Art · Titel · Adresse) mit Entfernen-Knopf */
    public static function linkChip(string $v): string
    {
        if (trim($v) === '') return '<p class="f-link__chip" data-link-chip hidden></p>';
        try {
            $d = Links::describe($v);
        } catch (\Throwable) {
            $d = ['type' => __('Adresse'), 'label' => $v, 'href' => $v, 'missing' => false, 'kind' => 'url'];
        }
        return '<p class="f-link__chip' . ($d['missing'] ? ' is-missing' : '') . '" data-link-chip data-kind="' . e($d['kind']) . '">'
            . '<span class="f-link__type">' . e($d['type']) . '</span> <span class="f-link__label">' . e($d['label']) . '</span>'
            . ($d['href'] !== '' && $d['href'] !== $d['label'] && $d['kind'] !== 'url' ? ' <span class="f-link__href">' . e($d['href']) . '</span>' : '')
            . ($d['missing'] ? ' <span class="f-link__warn">' . e(__('Ziel nicht gefunden – bitte neu wählen')) . '</span>' : '')
            . ' <button type="button" class="f-link__clear" data-link-clear aria-label="' . e(__('Link entfernen')) . '" title="' . e(__('Link entfernen')) . '">×</button></p>';
    }

    /** Ort: Koordinaten + Adresssuche + Karte zum Klicken (MapLibre über den eigenen Proxy) */
    private static function renderGeo(string $id, string $name, string $v, string $aria, array $f): string
    {
        $cfg = [
            'style' => Maps::styleUrl(), 'vendor' => base_path() . '/assets/vendor/maplibre/', 'css' => asset('vendor/maplibre/maplibre-gl.css'),
            'geocode' => url('/admin/api/geocode'), 'address' => array_values((array) ($f['address_fields'] ?? [])),
        ];
        return '<div class="geo" data-geo="' . e(json_encode($cfg, JSON_UNESCAPED_SLASHES)) . '">'
            . '<div class="geo-row"><input type="text" id="' . $id . '" name="' . $name . '" value="' . e($v) . '" placeholder="51.1634, 10.4477" inputmode="decimal" spellcheck="false" autocomplete="off"' . $aria . '>'
            . '<button type="button" class="btn btn--small" data-geo-clear>' . e(__('Entfernen')) . '</button></div>'
            . '<div class="geo-row geo-search"><input type="search" data-geo-q placeholder="' . e(__('Adresse suchen, z. B. Hauptstraße 1, Musterstadt')) . '" aria-label="' . e(__('Adresse suchen')) . '">'
            . '<button type="button" class="btn btn--small" data-geo-find>' . e(__('Suchen')) . '</button></div>'
            . '<ul class="geo-results" data-geo-results hidden></ul>'
            . '<div class="geo-map" data-geo-map role="application" aria-label="' . e(__('Karte: klicken, um den Ort zu setzen')) . '"></div>'
            . '<p class="geo-status" data-geo-status aria-live="polite"></p></div>';
    }

    /** Feldauswahl gruppiert nach Tabelle (optgroup data-table); System-Sortierungen optional */
    private static function renderDataField(string $id, string $name, string $v, string $aria, array $f): string
    {
        $h = '<select id="' . $id . '" name="' . $name . '"' . $aria . ' data-datafield><option value="">' . e($f['empty_label'] ?? '– keine –') . '</option>';
        foreach (\Core\Data\Tables::content() as $t) {
            $opts = [];
            if (!empty($f['system'])) {
                $opts += ['sort' => 'Manuelle Reihenfolge', 'published_at' => 'Veröffentlicht am', 'created_at' => 'Angelegt am'];
            }
            // 'types' => ['media', 'text', '_title', '_url', …]: nur Felder dieser Typen (Zuordnung, z. B. Block „Partner & Logos“)
            $types = isset($f['types']) ? (array) $f['types'] : null;
            if ($types !== null) {
                if (in_array('_title', $types, true)) $opts['_title'] = 'Titel des Eintrags';
                if (in_array('_url', $types, true) && ($t['settings']['route'] ?? '') !== '') $opts['_url'] = 'Adresse der Detailseite';
            }
            foreach ($t['fields'] as $tf) {
                if ($types !== null ? in_array($tf['type'], $types, true) : !in_array($tf['type'], ['richtext', 'textarea', 'media', 'file'], true)) $opts[$tf['name']] = $tf['label'];
            }
            $h .= '<optgroup label="' . e($t['name']) . '" data-table="' . e($t['handle']) . '">';
            foreach ($opts as $k => $l) {
                $val = $t['handle'] . '.' . $k;
                $h .= '<option value="' . e($val) . '"' . ($val === $v ? ' selected' : '') . '>' . e($l) . '</option>';
            }
            $h .= '</optgroup>';
        }
        return $h . '</select>';
    }

    private static function renderSelect(string $id, string $name, array $opts, string $v, string $aria, bool $empty): string
    {
        $h = '<select id="' . $id . '" name="' . $name . '"' . $aria . '>' . ($empty ? '<option value="">– keine Auswahl –</option>' : '');
        foreach ($opts as $k => $l) {
            $h .= '<option value="' . e((string) $k) . '"' . ((string) $k === $v ? ' selected' : '') . '>' . e((string) $l) . '</option>';
        }
        return $h . '</select>';
    }

    private static function renderRepeater(array $f, array $items, array $errors, string $inputName, string $p, string $label, string $errHtml): string
    {
        $sub = $f['fields'] ?? [];
        $itemLabel = $f['item_label'] ?? 'Eintrag';
        $title = $f['title_field'] ?? ($sub[0]['name'] ?? null);
        $renderItem = function (array $item, string $idx) use ($f, $sub, $errors, $inputName, $p, $itemLabel, $title) {
            $head = $title && isset($item[$title]) && is_scalar($item[$title]) && $item[$title] !== '' ? strip_tags((string) $item[$title]) : $itemLabel;
            if (isset($item['tag']) && $title === 'tag') {
                $head = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 0 => 'Sonntag'][(int) $item['tag']] ?? $head;
            }
            $head = mb_strimwidth(html_entity_decode($head), 0, 60, '…');
            // Einklappbar: Kopfzeile mit Titel + Kurzinfo (Status, Zeitraum, Bild – per JS aus den Feldern)
            $hasErr = (bool) array_filter(array_keys($errors), fn($k) => str_starts_with((string) $k, "$p.$idx."));
            return '<fieldset class="rep-item" data-index="' . $idx . '"' . ($hasErr ? ' data-open' : '') . '><legend>'
                . '<button type="button" class="rep-toggle" data-rep="toggle" aria-expanded="true"><span class="rep-chev" aria-hidden="true"></span><span class="rep-title">' . e($head) . '</span></button></legend>'
                . '<span class="rep-meta" data-rep-meta aria-hidden="true"></span>'
                . '<div class="rep-tools">' . (!empty($f['preview']) ? '<button type="button" class="icon-btn rep-pv" data-rep="preview" aria-label="' . e(__('Vorschau dieses Eintrags')) . '" title="' . e(__('Vorschau dieses Eintrags')) . '">◉</button>' : '')
                . '<button type="button" class="icon-btn" data-rep="up" aria-label="Nach oben">↑</button>'
                . '<button type="button" class="icon-btn" data-rep="down" aria-label="Nach unten">↓</button>'
                . '<button type="button" class="icon-btn icon-btn--danger" data-rep="remove" aria-label="Eintrag entfernen">✕</button></div>'
                . '<div class="rep-fields">' . self::renderForm($sub, $item, $errors, $inputName . '[' . $idx . ']', "$p.$idx") . '</div></fieldset>';
        };

        $h = '<div class="f f--repeater"><div class="rep" data-name="' . e($inputName) . '">'
            . '<div class="rep-head"><span class="f-label">' . $label . '</span>'
            . (count($items) > 1 ? ' <button type="button" class="adm-link rep-all" data-rep="all">' . e(__('Alle aufklappen')) . '</button>' : '')
            . (!empty($f['help']) ? '<p class="f-help">' . e($f['help']) . '</p>' : '') . '</div><div class="rep-items">';
        foreach (array_values($items) as $i => $item) {
            $h .= $renderItem(is_array($item) ? $item : [], (string) $i);
        }
        $h .= '</div><template>' . $renderItem([], '__i__') . '</template>'
            . '<button type="button" class="btn btn--small btn--ghost" data-rep="add">+ ' . (($f['add_label'] ?? '') !== '' ? e($f['add_label']) : e($itemLabel) . ' hinzufügen') . '</button>'
            . (($f['type'] ?? '') === 'group' && !empty($f['max']) ? '<p class="f-help">' . e(__('{min}–{max} Einträge', ['min' => (int) ($f['min'] ?? 0), 'max' => (int) $f['max']])) . '</p>' : '')
            . $errHtml . '</div></div>';
        return $h;
    }

    public static function pageOptions(): array
    {
        $o = [];
        // ohne die Seite „Nicht gefunden (404)“ (Core\NotFound) – sie hat keine erreichbare Adresse
        foreach (app()->db->fetchAll('SELECT id, title, slug FROM pages WHERE COALESCE(template_for, \'\') != ? ORDER BY is_home DESC, sort, title', [NotFound::MARK]) as $r) {
            $o[$r['id']] = $r['title'] . ' (/' . ($r['slug'] === 'home' ? '' : $r['slug']) . ')';
        }
        return $o;
    }

    public static function mediaPreview(?int $id): string
    {
        if (!$id || !($m = Media::find($id))) {
            return '<span class="media-empty">Keine Datei gewählt</span>';
        }
        if (str_starts_with($m['mime'], 'image/')) {
            return '<img src="' . e(Media::url($m, 480)) . '" alt="" width="120" height="' . (int) round(120 * ($m['height'] ?: 1) / max(1, $m['width'])) . '"><span>' . e($m['alt'] ?: $m['original_name']) . '</span>';
        }
        // Video: Poster bzw. automatisches Vorschaubild; fehlt es (ffmpeg vorhanden), lädt die Verwaltung es nach (data-vthumb, _media.js)
        if (str_starts_with($m['mime'], 'video/')) {
            $j = Media::toJson($m);
            $label = '<span>' . e(Media::displayName($m)) . ' · ' . e(Media::humanSize((int) $m['size'])) . '</span>';
            if ($j['thumb']) return '<img src="' . e($j['thumb']) . '" alt="" width="120" height="68" class="media-vthumb">' . $label;
            if ($j['thumb_gen']) return '<span class="fx-vthumb media-vthumb" data-vthumb="' . e($j['thumb_gen']) . '">' . icon('video-camera') . '</span>' . $label;
        }
        return '<span class="media-file">' . e($m['original_name']) . ' · ' . e(Media::humanSize((int) $m['size'])) . '</span>';
    }
}
