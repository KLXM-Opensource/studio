<?php
declare(strict_types=1);

namespace MyCms\Dav;

use Core\Data\Calendar;
use Core\Data\Entries;
use Core\Data\ICal;
use Core\Data\Tables;
use Core\Lang;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

/**
 * CalDAV: jede Tabelle mit „Als Kalender nutzen“ = ein Kalender, jeder Eintrag (Standardsprache) = ein VEVENT.
 *
 * Abbildung VEVENT ↔ Eintrag: SUMMARY ↔ Titel-Feld, DTSTART/DTEND (+ ganztägig) ↔ Beginn/Ende, RRULE/EXDATE ↔ Wiederholung,
 * DESCRIPTION ↔ Beschreibung (reiner Text), LOCATION/GEO ↔ Ort, CATEGORIES ↔ Kategorie (Auswahltexte), URL = Detailseite (nur lesen).
 * Alles andere (Erinnerungen, Teilnehmer, X-Eigenschaften, geänderte Einzeltermine mit RECURRENCE-ID) bleibt in dav_objects.raw erhalten
 * und wird bei der Ausgabe mit den aktuellen Feldwerten zusammengeführt.
 */
final class CalendarBackend extends \Sabre\CalDAV\Backend\AbstractBackend
{
    private const COLORS = ['#7A1F35', '#0F6E68', '#1D4ED8', '#137138', '#9A5B00', '#6D28D9', '#374151'];

    private function table(mixed $calendarId): array
    {
        $t = Tables::find((int) $calendarId);
        if (!$t || !Calendar::enabled($t) || !Dav::tableSettings($t)['caldav'] || !Dav::canSee($t)) throw new NotFound('Kalender nicht gefunden.');
        return $t;
    }

    public function getCalendarsForUser($principalUri)
    {
        $out = [];
        foreach (Dav::calendarTables() as $t) {
            if (!Dav::canSee($t)) continue;
            $out[] = [
                'id' => (int) $t['id'], 'uri' => $t['handle'], 'principaluri' => $principalUri,
                '{DAV:}displayname' => $t['name'],
                '{urn:ietf:params:xml:ns:caldav}calendar-description' => (string) ($t['description'] ?? ''),
                '{http://calendarserver.org/ns/}getctag' => Dav::ctag($t, 'cal'),
                '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VEVENT']),
                '{urn:ietf:params:xml:ns:caldav}calendar-timezone' => "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:" . ICal::prodId() . "\r\n"
                    . implode("\r\n", ICal::vtimezone(Calendar::tz(), (int) date('Y'))) . "\r\nEND:VCALENDAR\r\n",
                '{http://apple.com/ns/ical/}calendar-color' => self::COLORS[crc32($t['handle']) % count(self::COLORS)] . 'FF',
                '{http://sabredav.org/ns}read-only' => !Dav::canWrite($t),
            ];
        }
        return $out;
    }

    public function createCalendar($principalUri, $calendarUri, array $properties)
    {
        throw new Forbidden('Kalender werden im CMS angelegt (Daten → Tabelle → „Als Kalender nutzen“).');
    }

    public function updateCalendar($calendarId, PropPatch $propPatch)
    {
        // Name, Farbe usw. kommen aus dem CMS – Änderungen aus Apps werden nicht übernommen
    }

    public function deleteCalendar($calendarId)
    {
        throw new Forbidden('Kalender können nur im CMS gelöscht werden.');
    }

    // ------------------------------------------------------------------ Objekte

    /** Einträge der Standardsprache; per DAV „gelöschte“ Entwürfe ausgeblendet */
    private function entries(array $t): array
    {
        $objs = Dav::objects('cal', $t['handle']);
        $out = [];
        foreach (Entries::query($t, ['status' => 'all', 'lang' => Lang::default(), 'limit' => 5000]) as $e) {
            $o = $objs[$e['id']] ?? null;
            if ($o && (int) $o['hidden'] === 1 && $e['status'] === 'draft') continue;
            if (!Calendar::span($t, $e)) continue;
            $out[] = [$e, $o];
        }
        return $out;
    }

    private static function uri(array $e, ?array $o): string
    {
        return $o['uri'] ?? 'cms-' . (int) $e['id'] . '.ics';
    }

    private function row(array $t, array $e, ?array $o): array
    {
        $data = self::render($t, $e, $o);
        return ['id' => (int) $e['id'], 'uri' => self::uri($e, $o), 'lastmodified' => strtotime((string) $e['updated_at']) ?: time(),
            'etag' => Dav::etag($data), 'size' => strlen($data), 'calendardata' => $data, 'component' => 'vevent'];
    }

    public function getCalendarObjects($calendarId)
    {
        $t = $this->table($calendarId);
        return array_map(fn($x) => $this->row($t, $x[0], $x[1]), $this->entries($t));
    }

    public function getCalendarObject($calendarId, $objectUri)
    {
        $t = $this->table($calendarId);
        [$e, $o] = $this->find($t, (string) $objectUri);
        return $e ? $this->row($t, $e, $o) : null;
    }

    public function getMultipleCalendarObjects($calendarId, array $uris)
    {
        return array_values(array_filter(array_map(fn($u) => $this->getCalendarObject($calendarId, $u), $uris)));
    }

    public function getCalendarObjectByUID($principalUri, $uid)
    {
        foreach (Dav::calendarTables() as $t) {
            if (!Dav::canSee($t)) continue;
            $o = app()->db->fetch('SELECT * FROM dav_objects WHERE kind = ? AND table_handle = ? AND uid = ?', ['cal', $t['handle'], (string) $uid]);
            if ($o) return $t['handle'] . '/' . $o['uri'];
            if (preg_match('~^' . preg_quote($t['handle'], '~') . '-(\d+)@~', (string) $uid, $m) && Entries::find($t, (int) $m[1])) {
                return $t['handle'] . '/cms-' . $m[1] . '.ics';
            }
        }
        return null;
    }

    /** @return array{0: ?array, 1: ?array} [Eintrag, Zuordnung] */
    private function find(array $t, string $uri): array
    {
        $o = Dav::objectByUri($t['handle'], $uri);
        $id = $o ? (int) $o['entry_id'] : (preg_match('~^cms-(\d+)\.ics$~', $uri, $m) ? (int) $m[1] : 0);
        $e = $id ? Entries::find($t, $id) : null;
        // Geteilte Tabellen: fremde Einträge nur, wenn die Website sie sehen darf
        if ($e && !\Core\Data\Shared::visibleAll($t, $e)) $e = null;
        if (!$e || ($o && (int) $o['hidden'] === 1 && $e['status'] === 'draft')) return [null, $o];
        return [$e, $o ?: (Dav::objects('cal', $t['handle'])[$id] ?? null)];
    }

    public function createCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $t = $this->table($calendarId);
        if (!Dav::canWrite($t)) throw new Forbidden('Nur lesender Zugriff.');
        if (Dav::objectByUri($t['handle'], (string) $objectUri)) throw new BadRequest('Objekt existiert bereits.');
        [$in, $uid] = $this->parse($t, null, (string) $calendarData);
        $s = Dav::tableSettings($t);
        $in['status'] = $s['status'] === 'draft' || ($t['settings']['workflow'] && !Dav::can('data.publish', $t)) ? 'draft' : 'published';
        $in['lang'] = Lang::default();
        [$id, $errors] = Entries::save($t, null, $in);
        if ($errors) throw new BadRequest('Ungültige Eingaben: ' . implode(' ', $errors));
        Dav::saveObject('cal', $t['handle'], (int) $id, (string) $objectUri, $uid, (string) $calendarData);
        return null;                                         // Daten werden normalisiert → Client lädt neu
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $t = $this->table($calendarId);
        if (!Dav::canWrite($t)) throw new Forbidden('Nur lesender Zugriff.');
        [$e, $o] = $this->find($t, (string) $objectUri);
        if (!$e) throw new NotFound('Termin nicht gefunden.');
        if (\Core\Data\Shared::isForeign($t, $e)) throw new Forbidden('Termin einer anderen Website – nur lesbar.');
        [$in, $uid] = $this->parse($t, $e, (string) $calendarData);
        [, $errors] = Entries::save($t, (int) $e['id'], $in);
        if ($errors) throw new BadRequest('Ungültige Eingaben: ' . implode(' ', $errors));
        Dav::saveObject('cal', $t['handle'], (int) $e['id'], (string) $objectUri, $o['uid'] ?? $uid, (string) $calendarData);
        return null;
    }

    /** Löschen: Standard „Entwurf“ (Eintrag bleibt im CMS, verschwindet aus dem Kalender); „delete“ löscht endgültig (Recht data.delete) */
    public function deleteCalendarObject($calendarId, $objectUri)
    {
        $t = $this->table($calendarId);
        if (!Dav::canWrite($t)) throw new Forbidden('Nur lesender Zugriff.');
        [$e, $o] = $this->find($t, (string) $objectUri);
        if (!$e) return;
        if (\Core\Data\Shared::isForeign($t, $e)) throw new Forbidden('Termin einer anderen Website – nur lesbar.');
        if (Dav::tableSettings($t)['on_delete'] === 'delete' && Dav::can('data.delete', $t)) {
            Entries::delete($t, (int) $e['id']);
            app()->db->query('DELETE FROM dav_objects WHERE table_handle = ? AND entry_id = ?', [$t['handle'], (int) $e['id']]);
            return;
        }
        Entries::setStatus($t, [(int) $e['id']], 'draft');
        Dav::saveObject('cal', $t['handle'], (int) $e['id'], self::uri($e, $o), $o['uid'] ?? Calendar::uid($t, $e), $o['raw'] ?? null);
        app()->db->query('UPDATE dav_objects SET hidden = 1 WHERE table_handle = ? AND entry_id = ?', [$t['handle'], (int) $e['id']]);
    }

    // ------------------------------------------------------------------ Eintrag → iCalendar

    /** iCalendar eines Eintrags; vorhandene Rohdaten des Clients bleiben erhalten, abgebildete Eigenschaften kommen aus dem Eintrag */
    public static function render(array $t, array $e, ?array $o): string
    {
        $uid = $o['uid'] ?? Calendar::uid($t, $e);
        $lines = array_merge(['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:' . ICal::prodId(), 'CALSCALE:GREGORIAN'],
            ICal::vtimezone(Calendar::tz(), (int) date('Y')), ICal::vevent($t, $e, ['uid' => $uid]), ['END:VCALENDAR']);
        $fresh = implode("\r\n", array_map([ICal::class, 'fold'], $lines)) . "\r\n";
        if (empty($o['raw'])) return $fresh;
        try {
            $raw = Reader::read($o['raw'], Reader::OPTION_FORGIVING);
            $new = Reader::read($fresh);
        } catch (\Throwable) {
            return $fresh;
        }
        $master = self::master($raw);
        $src = self::master($new);
        if (!$master || !$src) return $fresh;
        $c = Calendar::config($t);
        $managed = ['SUMMARY', 'DTSTART', 'DTEND', 'DURATION', 'DTSTAMP', 'LAST-MODIFIED', 'CREATED', 'UID'];
        if ($c['recurrence'] !== '') array_push($managed, 'RRULE', 'EXDATE', 'RDATE');
        if ($c['description'] !== '') $managed[] = 'DESCRIPTION';
        if ($c['location'] !== '') array_push($managed, 'LOCATION', 'GEO');
        if ($c['category'] !== '') $managed[] = 'CATEGORIES';
        foreach (['URL', 'STATUS', 'TRANSP'] as $p) if (isset($src->$p)) $managed[] = $p;
        foreach ($managed as $p) $master->remove($p);
        foreach ($src->children() as $prop) {
            if ($prop instanceof \Sabre\VObject\Property) $master->add(clone $prop);
        }
        if (!isset($master->UID)) $master->add('UID', $uid);
        // Zeitzone der Website mitliefern
        $tzid = Calendar::tz()->getName();
        $has = false;
        foreach ($raw->select('VTIMEZONE') as $vt) if ((string) $vt->TZID === $tzid) $has = true;
        if (!$has) foreach ($new->select('VTIMEZONE') as $vt) $raw->add(clone $vt);
        return $raw->serialize();
    }

    private static function master(VCalendar $vc): ?\Sabre\VObject\Component
    {
        foreach ($vc->select('VEVENT') as $ev) {
            if (!isset($ev->{'RECURRENCE-ID'})) return $ev;
        }
        return null;
    }

    // ------------------------------------------------------------------ iCalendar → Eintrag

    /** @return array{0: array, 1: string} [Feldwerte für Entries::save, UID] */
    private function parse(array $t, ?array $e, string $data): array
    {
        try {
            $vc = Reader::read($data, Reader::OPTION_FORGIVING);
        } catch (\Throwable $ex) {
            throw new BadRequest('Ungültiges iCalendar: ' . $ex->getMessage());
        }
        $ev = $vc instanceof VCalendar ? self::master($vc) : null;
        if (!$ev || !isset($ev->DTSTART)) throw new BadRequest('Erwartet wird ein VEVENT mit DTSTART.');
        $c = Calendar::config($t);
        $tz = Calendar::tz();
        $in = [];
        $allDay = !$ev->DTSTART->hasTime();
        $start = \DateTimeImmutable::createFromInterface($ev->DTSTART->getDateTime($tz))->setTimezone($tz);
        if (isset($ev->DTEND)) $end = \DateTimeImmutable::createFromInterface($ev->DTEND->getDateTime($tz))->setTimezone($tz);
        elseif (isset($ev->DURATION)) $end = $start->add($ev->DURATION->getDateInterval());
        else $end = $allDay ? $start->modify('+1 day') : $start;

        $tf = $t['settings']['title_field'];
        if ($tf !== '') $in[$tf] = trim((string) ($ev->SUMMARY ?? '')) ?: ($e[$tf] ?? '') ?: '(ohne Titel)';
        // Feld vom Typ „date“ → nur Datum, „datetime“ → Datum + Uhrzeit (ganztägig: 00:00 und Schalter „ganztägig“)
        $fmt = fn(string $field, \DateTimeImmutable $d, bool $allDay) => (Tables::field($t, $field)['type'] ?? '') === 'date'
            ? $d->format('Y-m-d') : $d->format('Y-m-d H:i');
        $in[$c['start']] = $fmt($c['start'], $allDay ? $start->setTime(0, 0) : $start, $allDay);
        if ($c['all_day'] !== '') $in[$c['all_day']] = $allDay;
        if ($c['end'] !== '') {
            $last = $allDay ? $end->modify('-1 day') : $end;
            $in[$c['end']] = ($allDay ? $last->format('Y-m-d') === $start->format('Y-m-d') : $end <= $start) ? '' : $fmt($c['end'], $allDay ? $last->setTime(0, 0) : $last, $allDay);
        }
        if ($c['recurrence'] !== '') {
            $rule = isset($ev->RRULE) ? (string) $ev->RRULE->getValue() : '';
            $ex = [];
            foreach ($ev->select('EXDATE') as $x) {
                foreach ($x->getDateTimes($tz) as $d) $ex[] = \DateTimeImmutable::createFromInterface($d)->setTimezone($tz)->format('Y-m-d');
            }
            $value = $rule !== '' ? $rule . ($ex ? "\nEXDATE:" . implode(',', array_unique($ex)) : '') : '';
            // Regeln, die das CMS nicht abbildet (z. B. stündlich), bleiben nur in den Rohdaten
            $in[$c['recurrence']] = Calendar::normalize($value)[1] === null ? $value : ($e[$c['recurrence']] ?? '');
        }
        if ($c['description'] !== '' && isset($ev->DESCRIPTION)) {
            $txt = trim(str_replace("\r", '', (string) $ev->DESCRIPTION));
            $cur = $e ? ICal::plain(Entries::html($t, $e, $c['description'])) : '';
            if ($txt !== $cur) {                                   // unverändert → Formatierung im CMS behalten
                $in[$c['description']] = (Tables::field($t, $c['description'])['type'] ?? '') === 'richtext'
                    ? implode('', array_map(fn($p) => '<p>' . str_replace("\n", '<br>', e($p)) . '</p>', preg_split("~\n{2,}~", $txt) ?: []))
                    : $txt;
            }
        }
        if ($c['location'] !== '') {
            if ((Tables::field($t, $c['location'])['type'] ?? '') === 'geo') {
                if (isset($ev->GEO)) $in[$c['location']] = str_replace(';', ', ', (string) $ev->GEO);
            } else {
                $in[$c['location']] = trim((string) ($ev->LOCATION ?? ''));
            }
        }
        if ($c['category'] !== '' && isset($ev->CATEGORIES) && ($cf = Tables::field($t, $c['category']))) {
            $wanted = [];
            foreach ($ev->select('CATEGORIES') as $cat) foreach ($cat->getParts() as $p) $wanted[] = mb_strtolower(trim((string) $p));
            $keys = [];
            foreach ((array) ($cf['options'] ?? []) as $k => $label) {
                $names = array_map('mb_strtolower', array_merge([(string) $k, (string) $label], array_map(fn($l) => (string) ($l[$k] ?? ''), (array) ($cf['options_i18n'] ?? []))));
                if (array_intersect($names, $wanted)) $keys[] = (string) $k;
            }
            $in[$c['category']] = $cf['type'] === 'multiselect' ? $keys : ($keys[0] ?? '');
        }
        return [$in, trim((string) ($ev->UID ?? '')) ?: bin2hex(random_bytes(16))];
    }
}
