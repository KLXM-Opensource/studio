<?php
declare(strict_types=1);

namespace Core\Data;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * iCalendar (RFC 5545) aus Kalender-Tabellen: Feed einer Tabelle und einzelne Termine.
 * Zeitangaben mit TZID der Website + VTIMEZONE (aus den Zeitzonen-Übergängen erzeugt), ganztägige Termine als DATE.
 * Zeilen werden nach 75 Oktetts gefaltet (UTF-8-sicher), Texte nach RFC 5545 §3.3.11 maskiert, Zeilenende CRLF.
 */
final class ICal
{
    /** Kompletter Kalender (VCALENDAR) mit allen übergebenen Einträgen */
    public static function calendar(array $table, array $entries, array $o = []): string
    {
        $tz = Calendar::tz();
        $events = [];
        $years = [];
        foreach ($entries as $e) {
            $lines = self::vevent($table, $e);
            if (!$lines) continue;
            $events[] = $lines;
            $span = Calendar::span($table, $e);
            $years[] = (int) $span['start']->format('Y');
        }
        $name = (string) ($o['name'] ?? $table['name']);
        $out = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:' . self::prodId(), 'CALSCALE:GREGORIAN'];
        if (!empty($o['publish'])) {
            $out[] = 'METHOD:PUBLISH';
            $out[] = 'X-WR-CALNAME:' . self::text($name);
            $out[] = 'X-WR-TIMEZONE:' . $tz->getName();
            if (!empty($table['description'])) $out[] = 'X-WR-CALDESC:' . self::text((string) $table['description']);
            $out[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT6H';
            $out[] = 'X-PUBLISHED-TTL:PT6H';
        }
        if ($events) {
            array_push($out, ...self::vtimezone($tz, $years ? min($years) : (int) date('Y')));
        }
        foreach ($events as $lines) array_push($out, ...$lines);
        $out[] = 'END:VCALENDAR';
        return implode("\r\n", array_map([self::class, 'fold'], $out)) . "\r\n";
    }

    public static function prodId(): string
    {
        return '-//KLXM//' . CMS_NAME . ' ' . CMS_VERSION . '//' . strtoupper(\Core\Lang::current());
    }

    /**
     * VEVENT-Zeilen eines Eintrags (ungefaltet). $o['uid'] überschreibt die UID (z. B. aus CalDAV),
     * $o['extra'] = zusätzliche Zeilen vor END:VEVENT.
     */
    public static function vevent(array $table, array $e, array $o = []): array
    {
        $span = Calendar::span($table, $e);
        if (!$span) return [];
        $c = Calendar::config($table);
        $tzid = Calendar::tz()->getName();
        $utc = new DateTimeZone('UTC');
        $stamp = fn(?string $v) => ($v && ($ts = strtotime($v))) ? gmdate('Ymd\THis\Z', $ts) : gmdate('Ymd\THis\Z');
        $l = ['BEGIN:VEVENT', 'UID:' . self::text($o['uid'] ?? Calendar::uid($table, $e)), 'DTSTAMP:' . $stamp($e['updated_at'] ?? null)];
        if (!empty($e['created_at'])) $l[] = 'CREATED:' . $stamp($e['created_at']);
        if (!empty($e['updated_at'])) $l[] = 'LAST-MODIFIED:' . $stamp($e['updated_at']);
        if ($span['all_day']) {
            $l[] = 'DTSTART;VALUE=DATE:' . $span['start']->format('Ymd');
            $l[] = 'DTEND;VALUE=DATE:' . $span['end']->format('Ymd');
        } else {
            $l[] = 'DTSTART;TZID=' . $tzid . ':' . $span['start']->format('Ymd\THis');
            if ($span['end'] > $span['start']) $l[] = 'DTEND;TZID=' . $tzid . ':' . $span['end']->format('Ymd\THis');
        }
        $l[] = 'SUMMARY:' . self::text(Entries::title($table, $e));
        $rec = Calendar::recurrence($table, $e);
        if ($rec['rrule'] !== '') {
            $rule = $rec['rrule'];
            // Ganztägig: UNTIL als DATE (RFC 5545 – gleicher Werttyp wie DTSTART)
            if ($span['all_day']) {
                $rule = preg_replace_callback('~UNTIL=(\d{8})T(\d{6})Z~', fn($m) => 'UNTIL=' . DateTimeImmutable::createFromFormat('Ymd\THis', $m[1] . 'T' . $m[2], $utc)
                    ->setTimezone(Calendar::tz())->format('Ymd'), $rule);
            }
            $l[] = 'RRULE:' . $rule;
            if ($rec['exdates']) {
                $l[] = $span['all_day']
                    ? 'EXDATE;VALUE=DATE:' . implode(',', array_map(fn($d) => str_replace('-', '', $d), $rec['exdates']))
                    : 'EXDATE;TZID=' . $tzid . ':' . implode(',', array_map(fn($d) => str_replace('-', '', $d) . 'T' . $span['start']->format('His'), $rec['exdates']));
            }
        }
        if ($c['description'] !== '' && ($d = self::plain(Entries::html($table, $e, $c['description']))) !== '') $l[] = 'DESCRIPTION:' . self::text($d);
        if ($c['location'] !== '' && ($loc = trim((string) ($e[$c['location']] ?? ''))) !== '') {
            if ((Tables::field($table, $c['location'])['type'] ?? '') === 'geo') {
                if (($p = \Core\Maps::parse($loc))) $l[] = 'GEO:' . $p[0] . ';' . $p[1];
            } else {
                $l[] = 'LOCATION:' . self::text(self::plain($loc));
            }
        }
        if ($c['category'] !== '' && ($f = Tables::field($table, $c['category']))) {
            $cats = array_filter(array_map(fn($k) => Tables::optionLabel($f, (string) $k), (array) ($e[$c['category']] ?? [])), fn($x) => $x !== '');
            if ($cats) $l[] = 'CATEGORIES:' . implode(',', array_map([self::class, 'text'], $cats));
        }
        if (($url = Entries::absUrl($table, $e))) $l[] = 'URL:' . $url;
        if ($span['all_day']) $l[] = 'TRANSP:TRANSPARENT';
        if (($e['status'] ?? 'published') !== 'published') $l[] = 'STATUS:TENTATIVE';
        array_push($l, ...(array) ($o['extra'] ?? []));
        $l[] = 'END:VEVENT';
        return $l;
    }

    /** Text aus HTML/Mehrzeilern: Tags weg, Entities auflösen, Zeilen bereinigen */
    public static function plain(string $s): string
    {
        // Absätze → Leerzeile, Zeilenumbruch/Listenpunkt → Zeile (verlustarm für den Rückweg aus Kalender-Apps)
        if ($s !== strip_tags($s)) {
            $s = str_replace(["\r", "\n"], ['', ' '], $s);
            $s = str_replace(['<br>', '<br/>', '<br />', '</li>'], "\n", $s);
            $s = str_replace(['</p>', '</h3>', '</h4>', '</ul>', '</ol>'], "\n\n", $s);
        }
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace("~\n{3,}~", "\n\n", preg_replace('~[ \t]*\n[ \t]*~', "\n", str_replace("\r", '', $s))));
    }

    /** TEXT-Wert maskieren (RFC 5545 §3.3.11) */
    public static function text(string $s): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\;', '\,', '\n', '\n', ''], $s);
    }

    /** Zeile nach 75 Oktetts falten, ohne UTF-8-Zeichen zu zerteilen */
    public static function fold(string $line): string
    {
        if (strlen($line) <= 75) return $line;
        $out = '';
        $cur = '';
        $max = 75;
        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if (strlen($cur) + strlen($ch) > $max) {
                $out .= $cur . "\r\n ";
                $cur = '';
                $max = 74;                               // Folgezeilen beginnen mit einem Leerzeichen
            }
            $cur .= $ch;
        }
        return $out . $cur;
    }

    /**
     * VTIMEZONE aus den Übergängen der Zeitzone: Sommer-/Normalzeit mit jährlicher Regel (z. B. letzter Sonntag im März),
     * bei Zonen ohne Umstellung nur STANDARD.
     */
    public static function vtimezone(DateTimeZone $tz, int $fromYear): array
    {
        $name = $tz->getName();
        $out = ['BEGIN:VTIMEZONE', 'TZID:' . $name];
        $year = max(1970, min($fromYear, (int) date('Y')));
        $tr = $tz->getTransitions(mktime(0, 0, 0, 1, 1, $year), mktime(0, 0, 0, 12, 31, $year));
        $changes = array_slice($tr ?: [], 1);
        $fmtOff = fn(int $s) => ($s < 0 ? '-' : '+') . sprintf('%02d%02d', intdiv(abs($s), 3600), intdiv(abs($s) % 3600, 60));
        if (count($changes) >= 2) {
            foreach (array_slice($changes, 0, 2) as $i => $t) {
                $prev = $i === 0 ? ($tr[0]['offset'] ?? $t['offset']) : $changes[0]['offset'];
                $local = (new DateTimeImmutable('@' . $t['ts']))->setTimezone(new DateTimeZone($fmtOff($prev)));
                $dom = (int) $local->format('j');
                $nth = $dom + 7 > (int) $local->format('t') ? -1 : intdiv($dom - 1, 7) + 1;
                $wd = strtoupper(substr($local->format('D'), 0, 2));
                $kind = $t['isdst'] ? 'DAYLIGHT' : 'STANDARD';
                // Erster Beginn der Regel (1970), z. B. „last sun of March 1970“
                $words = [-1 => 'last', 1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth'];
                $first = new DateTimeImmutable($words[$nth] . ' ' . $local->format('D') . ' of ' . $local->format('F') . ' 1970', new DateTimeZone('UTC'));
                array_push($out, 'BEGIN:' . $kind, 'TZOFFSETFROM:' . $fmtOff($prev), 'TZOFFSETTO:' . $fmtOff($t['offset']), 'TZNAME:' . $t['abbr'],
                    'DTSTART:' . $first->format('Ymd') . 'T' . $local->format('His'),
                    'RRULE:FREQ=YEARLY;BYMONTH=' . (int) $local->format('n') . ';BYDAY=' . $nth . $wd, 'END:' . $kind);
            }
        } else {
            $t = $tr[0] ?? ['offset' => 0, 'abbr' => 'UTC'];
            array_push($out, 'BEGIN:STANDARD', 'TZOFFSETFROM:' . $fmtOff($t['offset']), 'TZOFFSETTO:' . $fmtOff($t['offset']),
                'TZNAME:' . $t['abbr'], 'DTSTART:19700101T000000', 'END:STANDARD');
        }
        $out[] = 'END:VTIMEZONE';
        return $out;
    }

    /** Hilfsfunktion für Tests/Clients: Zeitpunkt als UTC-Stempel */
    public static function utc(DateTimeInterface $d): string
    {
        return DateTimeImmutable::createFromInterface($d)->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }
}
