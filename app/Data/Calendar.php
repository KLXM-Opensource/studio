<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Features;
use Core\Lang;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RRule\RRule;

/**
 * Kalender für Datentabellen: Termine mit Beginn/Ende, ganztägig, Wiederholung (RFC 5545 RRULE) und Ausnahmen.
 *
 * Tabellen-Einstellung settings.calendar (Tabellen-Designer → „Als Kalender nutzen“):
 *   enabled, start (date|datetime), end (date|datetime, optional), all_day (bool), recurrence (recurrence),
 *   location (text|textarea|geo), description (text|textarea|richtext), category (select|multiselect),
 *   duration (Minuten, wenn kein Ende gepflegt ist), feed (öffentlicher iCal-Feed /kalender/{handle}.ics)
 *
 * Feldtyp „recurrence“ speichert eine RRULE ohne Präfix, optional gefolgt von einer Zeile mit Ausnahmen:
 *   FREQ=WEEKLY;BYDAY=MO,WE;UNTIL=20261231T225959Z
 *   EXDATE:2026-10-12,2026-10-19
 * Wiederholungen werden mit rlanvin/php-rrule in der Zeitzone der Website (date_default_timezone_get()) berechnet –
 * die Uhrzeit bleibt über Sommer-/Winterzeit hinweg gleich.
 */
final class Calendar
{
    public const DEFAULTS = ['enabled' => false, 'start' => '', 'end' => '', 'all_day' => '', 'recurrence' => '', 'location' => '',
        'description' => '', 'category' => '', 'duration' => 60, 'feed' => true];

    /** Zuordnung → erlaubte Feldtypen */
    public const MAP = [
        'start' => ['date', 'datetime'], 'end' => ['date', 'datetime'], 'all_day' => ['bool'], 'recurrence' => ['recurrence'],
        'location' => ['text', 'textarea', 'geo'], 'description' => ['text', 'textarea', 'richtext'], 'category' => ['select', 'multiselect'],
    ];

    /** Schutz vor ausufernden Regeln */
    public const MAX_OCCURRENCES = 1000;
    public const MAX_PER_ENTRY = 500;
    public const MAX_COUNT = 1000;
    private const FREQS = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];

    // ================================================================= Einstellungen

    /** Tabellen-Einstellung prüfen (aus dem Formular bzw. der API) */
    public static function validateSettings(array $in, array $fields, array &$errors, ?array $old = null): array
    {
        if (!$in && $old) return $old + self::DEFAULTS;          // Formular ohne Kalender-Bereich (Funktion aus) → unverändert
        $types = array_column($fields, 'type', 'name');
        $out = self::DEFAULTS;
        $out['enabled'] = !empty($in['enabled']);
        foreach (self::MAP as $k => $allowed) {
            $v = (string) ($in[$k] ?? '');
            $out[$k] = isset($types[$v]) && in_array($types[$v], $allowed, true) ? $v : '';
        }
        $out['duration'] = max(0, min(10080, (int) ($in['duration'] ?? 60)));
        $out['feed'] = !array_key_exists('feed', $in) || !empty($in['feed']);
        if ($out['enabled'] && $out['start'] === '') {
            $errors['settings.calendar'] = 'Kalender: Bitte das Feld für den Beginn wählen (Typ „Datum“ oder „Datum & Uhrzeit“).';
        }
        return $out;
    }

    /** Ist die Tabelle ein Kalender (Funktion an, Beginn-Feld vorhanden)? */
    public static function enabled(array $table): bool
    {
        $c = $table['settings']['calendar'] ?? [];
        return !empty($c['enabled']) && !Tables::isInbox($table) && ($c['start'] ?? '') !== '' && Tables::field($table, (string) $c['start'])
            && Features::on('calendar', false) && Features::on('data', false);
    }

    public static function config(array $table): array
    {
        return (array) ($table['settings']['calendar'] ?? []) + self::DEFAULTS;
    }

    /** Alle Tabellen mit Kalender */
    public static function tables(): array
    {
        return array_values(array_filter(Tables::content(), [self::class, 'enabled']));
    }

    public static function tz(): DateTimeZone
    {
        return new DateTimeZone(date_default_timezone_get());
    }

    // ================================================================= Feldtyp „recurrence“

    /** Gespeicherten Wert zerlegen: ['rrule' => 'FREQ=…', 'exdates' => ['2026-10-12', …]] */
    public static function parseValue(mixed $v): array
    {
        $rrule = '';
        $ex = [];
        if (is_array($v)) {
            $rrule = (string) ($v['rrule'] ?? '');
            $ex = array_map('strval', (array) ($v['exdates'] ?? []));
        } else {
            foreach (preg_split('~\R|(?<=\S)\s+(?=EXDATE)~i', trim((string) $v)) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (preg_match('~^EXDATE[^:]*:(.*)$~i', $line, $m)) {
                    array_push($ex, ...array_map('trim', explode(',', $m[1])));
                } else {
                    $rrule = preg_replace('~^RRULE:~i', '', $line);
                }
            }
        }
        $dates = [];
        foreach ($ex as $d) {
            if (($n = self::normDate($d)) !== null) $dates[$n] = true;
        }
        $dates = array_keys($dates);
        sort($dates);
        return ['rrule' => strtoupper(preg_replace('~\s+~', '', (string) $rrule)), 'exdates' => $dates];
    }

    /** Datum aus 2026-10-12, 20261012 oder 20261012T090000(Z) → 2026-10-12 (Ortszeit) */
    private static function normDate(string $d): ?string
    {
        $d = trim($d);
        if (preg_match('~^(\d{4})-?(\d{2})-?(\d{2})$~', $d, $m)) return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "$m[1]-$m[2]-$m[3]" : null;
        if (preg_match('~^(\d{8})T(\d{6})(Z?)$~', $d, $m)) {
            $dt = DateTimeImmutable::createFromFormat('Ymd\THis', $m[1] . 'T' . $m[2], $m[3] ? new DateTimeZone('UTC') : self::tz());
            return $dt ? $dt->setTimezone(self::tz())->format('Y-m-d') : null;
        }
        return null;
    }

    /**
     * Eingabe prüfen und in die gespeicherte Form bringen.
     * @return array{0: string, 1: ?string} [Wert, Fehlerschlüssel: rule|freq|count|until]
     */
    public static function normalize(mixed $raw): array
    {
        $p = self::parseValue($raw);
        if ($p['rrule'] === '') return ['', null];
        $parts = [];
        foreach (explode(';', trim($p['rrule'], ';')) as $kv) {
            if (!str_contains($kv, '=')) return [(string) $p['rrule'], 'rule'];
            [$k, $v] = explode('=', $kv, 2);
            if ($k === 'DTSTART' || $k === '') continue;              // Beginn kommt aus dem Eintrag
            $parts[$k] = $v;
        }
        if (!in_array($parts['FREQ'] ?? '', self::FREQS, true)) return [$p['rrule'], 'freq'];
        if (isset($parts['COUNT']) && ((int) $parts['COUNT'] < 1 || (int) $parts['COUNT'] > self::MAX_COUNT)) return [$p['rrule'], 'count'];
        if (isset($parts['UNTIL'], $parts['COUNT'])) unset($parts['COUNT']);
        // UNTIL als Datum → Ende dieses Tages in Ortszeit, gespeichert in UTC (RFC 5545 bei Beginn mit Zeitzone)
        if (isset($parts['UNTIL'])) {
            $u = $parts['UNTIL'];
            if (preg_match('~^(\d{4})-?(\d{2})-?(\d{2})$~', $u, $m)) {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', "$m[1]-$m[2]-$m[3] 23:59:59", self::tz());
            } elseif (preg_match('~^(\d{8})T(\d{6})(Z?)$~', $u, $m)) {
                $dt = DateTimeImmutable::createFromFormat('Ymd\THis', $m[1] . 'T' . $m[2], $m[3] ? new DateTimeZone('UTC') : self::tz());
            } else {
                $dt = false;
            }
            if (!$dt) return [$p['rrule'], 'until'];
            $parts['UNTIL'] = $dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        }
        $rule = implode(';', array_map(fn($k, $v) => "$k=$v", array_keys($parts), $parts));
        try {
            new RRule($rule, new \DateTime('2026-01-01 09:00'));
        } catch (\Throwable) {
            return [$rule, 'rule'];
        }
        return [$rule . ($p['exdates'] ? "\nEXDATE:" . implode(',', $p['exdates']) : ''), null];
    }

    /** Lesbare Beschreibung in der Sprache der Seite, z. B. „Wöchentlich am Montag und Mittwoch, bis zum 31.12.2026“ */
    public static function describe(mixed $value, ?string $lang = null): string
    {
        $p = self::parseValue($value);
        if ($p['rrule'] === '') return '';
        $lang ??= Lang::current();
        try {
            $r = new RRule($p['rrule'], new \DateTime('today 09:00'));
            $txt = $r->humanReadable(['locale' => $lang, 'include_start' => false, 'fallback' => 'en',
                'date_formatter' => fn(\DateTimeInterface $d) => date_local($d->getTimestamp())]);
        } catch (\Throwable) {
            return $p['rrule'];
        }
        $txt = mb_strtoupper(mb_substr($txt, 0, 1)) . mb_substr($txt, 1);
        if ($p['exdates']) {
            $txt .= ' ' . lt('(außer {dates})', ['dates' => implode(', ', array_map(fn($d) => date_local($d), $p['exdates']))]);
        }
        return $txt;
    }

    // ================================================================= Zeitraum eines Eintrags

    /** Datum/Zeit aus einem Feldwert (Y-m-d oder Y-m-d H:i) in Ortszeit */
    public static function parse(mixed $v): ?array
    {
        $s = trim((string) $v);
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $s)) {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s, self::tz());
            return $d ? [$d, true] : null;
        }
        if (preg_match('~^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})~', $s, $m)) {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$m[1] $m[2]", self::tz());
            return $d ? [$d, false] : null;
        }
        return null;
    }

    /**
     * Beginn/Ende (Ende exklusiv, bei ganztägig = Folgetag 0 Uhr) eines Eintrags.
     * @return ?array{start: DateTimeImmutable, end: DateTimeImmutable, all_day: bool, has_end: bool}
     */
    public static function span(array $table, array $e): ?array
    {
        $c = self::config($table);
        $s = self::parse($e[$c['start']] ?? null);
        if (!$s) return null;
        [$start, $dateOnly] = $s;
        $allDay = $dateOnly || ($c['all_day'] !== '' && !empty($e[$c['all_day']]));
        if ($allDay) $start = $start->setTime(0, 0);
        $end = null;
        $hasEnd = false;
        if ($c['end'] !== '' && ($x = self::parse($e[$c['end']] ?? null))) {
            [$end, $endDateOnly] = $x;
            if ($allDay || $endDateOnly) $end = $end->setTime(0, 0)->modify('+1 day');   // Enddatum zählt mit
            $hasEnd = $end > $start;
            if (!$hasEnd) $end = null;
        }
        $end ??= $allDay ? $start->modify('+1 day') : $start->add(new DateInterval('PT' . (int) $c['duration'] . 'M'));
        return ['start' => $start, 'end' => $end, 'all_day' => $allDay, 'has_end' => $hasEnd];
    }

    /** Gespeicherte Wiederholung eines Eintrags */
    public static function recurrence(array $table, array $e): array
    {
        $c = self::config($table);
        return self::parseValue($c['recurrence'] !== '' ? ($e[$c['recurrence']] ?? '') : '');
    }

    // ================================================================= Termine im Zeitraum

    /**
     * Termine (einzelne Vorkommen) im Zeitraum [$from, $to), sortiert nach Beginn.
     * $opts: status (published|draft|all, Standard published), lang (Standard: Sprache der Seite), where (wie Entries::query),
     *        limit (max. Vorkommen, Standard/Obergrenze MAX_OCCURRENCES), ids
     * @return list<array{entry: array, start: DateTimeImmutable, end: DateTimeImmutable, all_day: bool, has_end: bool, recurring: bool, key: string}>
     */
    public static function occurrences(array $table, DateTimeInterface $from, DateTimeInterface $to, array $opts = []): array
    {
        if (!self::enabled($table)) return [];
        $c = self::config($table);
        $tz = self::tz();
        $from = DateTimeImmutable::createFromInterface($from)->setTimezone($tz);
        $to = DateTimeImmutable::createFromInterface($to)->setTimezone($tz);
        if ($to <= $from) return [];
        $limit = max(1, min(self::MAX_OCCURRENCES, (int) ($opts['limit'] ?? self::MAX_OCCURRENCES)));

        $q = ['status' => $opts['status'] ?? 'published', 'where' => array_merge((array) ($opts['where'] ?? []), [[$c['start'], '<', $to->format('Y-m-d H:i')]]),
            'sort' => $c['start'], 'dir' => 'asc'];
        if (isset($opts['lang'])) $q['lang'] = $opts['lang'];
        if (!empty($opts['ids'])) $q['ids'] = $opts['ids'];
        if (!empty($opts['q'])) $q['q'] = (string) $opts['q'];
        if (!empty($opts['source'])) $q['source'] = (string) $opts['source'];   // geteilte Tabellen (Core\Data\Shared)
        $out = [];
        foreach (Entries::query($table, $q) as $e) {
            $span = self::span($table, $e);
            if (!$span) continue;
            $rec = self::recurrence($table, $e);
            if ($rec['rrule'] === '') {
                if ($span['end'] > $from && $span['start'] < $to) $out[] = self::occ($e, $span, $span['start'], false);
                continue;
            }
            $dur = $span['start']->diff($span['end']);
            try {
                $rule = new RRule($rec['rrule'], \DateTime::createFromImmutable($span['start']));
                $begin = \DateTime::createFromImmutable($from->sub($dur)->modify('+1 second'));
                $until = \DateTime::createFromImmutable($to->modify('-1 second'));
                $starts = $rule->getOccurrencesBetween($begin, $until, self::MAX_PER_ENTRY);
            } catch (\Throwable) {
                $starts = [$span['start']];                       // ungültige Regel → Einzeltermin
            }
            $skip = array_flip($rec['exdates']);
            foreach ($starts as $st) {
                $st = DateTimeImmutable::createFromInterface($st)->setTimezone($tz);
                if (isset($skip[$st->format('Y-m-d')])) continue;
                $end = $st->add($dur);
                if ($end > $from && $st < $to) $out[] = self::occ($e, $span, $st, true);
            }
        }
        usort($out, fn($a, $b) => [$a['start'], !$a['all_day'], $a['entry']['id']] <=> [$b['start'], !$b['all_day'], $b['entry']['id']]);
        return array_slice($out, 0, $limit);
    }

    private static function occ(array $e, array $span, DateTimeImmutable $start, bool $recurring): array
    {
        $end = $start->add($span['start']->diff($span['end']));
        return ['entry' => $e, 'start' => $start, 'end' => $end, 'all_day' => $span['all_day'], 'has_end' => $span['has_end'],
            'recurring' => $recurring, 'key' => $e['id'] . '-' . $start->format('Ymd\THi')];
    }

    /** Nächste Termine ab jetzt (laufende eingeschlossen); $days = 0 → bis zu einem Jahr */
    public static function upcoming(array $table, int $limit = 5, int $days = 0, array $opts = []): array
    {
        $now = new DateTimeImmutable('now', self::tz());
        $to = $now->modify('+' . ($days > 0 ? min($days, 3660) : 366) . ' days');
        return self::occurrences($table, $now, $to, ['limit' => max(1, $limit)] + $opts);
    }

    /**
     * Filter der Blöcke „calendar“/„upcoming“: fester Filter (filter_field „tabelle.feld“ + filter_value)
     * und – beim Kalender – die vom Besucher gewählte Kategorie (?kategorie=kurzname).
     * @return array{0: array, 1: ?string} [where, gewählte Kategorie]
     */
    public static function blockWhere(array $table, array $d, array $query = []): array
    {
        $where = [];
        $pfx = $table['handle'] . '.';
        if (str_starts_with((string) ($d['filter_field'] ?? ''), $pfx) && trim((string) ($d['filter_value'] ?? '')) !== '') {
            $where[] = [substr((string) $d['filter_field'], strlen($pfx)), '=', trim((string) $d['filter_value'])];
        }
        $cat = null;
        $cf = self::config($table)['category'];
        if (!empty($d['visitor_filter']) && $cf !== '' && isset($query['kategorie']) && is_string($query['kategorie'])
            && array_key_exists($query['kategorie'], (array) (Tables::field($table, $cf)['options'] ?? []))) {
            $cat = $query['kategorie'];
            $where[] = [$cf, '=', $cat];
        }
        return [$where, $cat];
    }

    // ================================================================= Ausgabe

    /** Uhrzeit bzw. Zeitspanne eines Termins in der Sprache der Seite: „09:00–11:00 Uhr“, „ganztägig“ */
    public static function timeLabel(array $o): string
    {
        if ($o['all_day']) return lt('ganztägig');
        $t = $o['start']->format('H:i');
        if ($o['has_end'] && $o['end']->format('Y-m-d') === $o['start']->format('Y-m-d')) $t .= '–' . $o['end']->format('H:i');
        return lt('{time} Uhr', ['time' => $t]);
    }

    /** Datum + Uhrzeit: „Mo., 12.10.2026, 09:00–11:00 Uhr“ bzw. mehrtägig „03.10.2026 – 05.10.2026“ */
    public static function when(array $o, string $style = 'short'): string
    {
        $s = $o['start'];
        $lastDay = $o['all_day'] ? $o['end']->modify('-1 day') : $o['end'];
        $multi = $o['has_end'] && $lastDay->format('Y-m-d') !== $s->format('Y-m-d');
        $date = self::dayLabel($s, $style);
        if ($multi) {
            return $o['all_day']
                ? $date . ' – ' . self::dayLabel($lastDay, $style)
                : $date . ', ' . $s->format('H:i') . ' – ' . self::dayLabel($lastDay, $style) . ', ' . lt('{time} Uhr', ['time' => $lastDay->format('H:i')]);
        }
        return $o['all_day'] ? $date : $date . ', ' . self::timeLabel($o);
    }

    /** „Mo., 12.10.2026“ (short) bzw. „Montag, 12. Oktober 2026“ (long) */
    public static function dayLabel(DateTimeInterface $d, string $style = 'short'): string
    {
        $wd = date_local($d->getTimestamp(), 'weekday');
        return ($style === 'long' ? $wd : mb_substr($wd, 0, 2) . '.') . ', ' . date_local($d->getTimestamp(), $style === 'long' ? 'long' : 'short');
    }

    /** Datum nach ICU-Muster in der Sprache der Seite, z. B. fmt($d, 'LLLL y') → „Oktober 2026“, 'EEEEEE' → „Mo“ */
    public static function fmt(DateTimeInterface $d, string $pattern): string
    {
        if (class_exists(\IntlDateFormatter::class)) {
            $f = new \IntlDateFormatter(Lang::current(), \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $d->getTimezone(), null, $pattern);
            $out = $f->format($d);
            if (is_string($out)) return $out;
        }
        return $d->format(match ($pattern) { 'LLLL y' => 'F Y', 'LLLL' => 'F', 'EEEEEE' => 'D', 'EEEE' => 'l', default => 'Y-m-d' });
    }

    /** Stabile UID eines Eintrags für iCal/CalDAV */
    public static function uid(array $table, array $e): string
    {
        // Geteilte Tabellen: auf allen Websites dieselbe UID (Host der Eigentümer-Website)
        $host = Tables::isShared($table)
            ? (parse_url(Shared::siteInfo($table['shared']['owner'], $table['shared']['key'])['url'], PHP_URL_HOST) ?: $table['shared']['owner'] . '.shared')
            : (parse_url(site_url(), PHP_URL_HOST) ?: 'localhost');
        return $table['handle'] . '-' . (int) $e['id'] . '@' . $host;
    }

    /** Öffentliche Adressen des Feeds: ['https' => …, 'webcal' => …] */
    public static function feedUrls(array $table, ?string $lang = null): array
    {
        $https = site_url() . url('/kalender/' . $table['handle'] . '.ics') . ($lang && $lang !== Lang::default() ? '?lang=' . rawurlencode($lang) : '');
        return ['https' => $https, 'webcal' => preg_replace('~^https?://~', 'webcal://', $https)];
    }
}
