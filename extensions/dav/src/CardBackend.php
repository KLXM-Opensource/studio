<?php
declare(strict_types=1);

namespace MyCms\Dav;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Lang;
use Core\Media;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

/**
 * CardDAV: Tabellen mit „Als Adressbuch nutzen“ (Einstellung der Erweiterung) = je ein Adressbuch, Eintrag = vCard 3.0.
 *
 * Zuordnung (Dav::CARD_MAP, im Admin einstellbar): FN bzw. N (Nachname/Vorname), ORG, TITLE, ADR (Straße, PLZ, Ort, Land), URL, NOTE, BDAY,
 * PHOTO (Bildfeld → eingebettetes JPEG, nur Ausgabe), CATEGORIES (Auswahl). EMAIL/TEL: alle Felder vom Typ E-Mail bzw. Telefon
 * (Typ aus dem Feldnamen: mobil/handy → CELL, fax → FAX, privat → HOME, sonst WORK).
 * Alle übrigen Felder als X-MYCMS-{FELD} (z. B. X-MYCMS-FACHGEBIET) – in beide Richtungen.
 * Unbekannte Eigenschaften aus Apps bleiben in dav_objects.raw erhalten.
 */
final class CardBackend extends \Sabre\CardDAV\Backend\AbstractBackend
{
    private function table(mixed $id): array
    {
        $t = Tables::find((int) $id);
        if (!$t || !Dav::tableSettings($t)['addressbook'] || !Dav::canSee($t)) throw new NotFound('Adressbuch nicht gefunden.');
        return $t;
    }

    public function getAddressBooksForUser($principalUri)
    {
        $out = [];
        foreach (Dav::addressBookTables() as $t) {
            if (!Dav::canSee($t)) continue;
            $out[] = ['id' => (int) $t['id'], 'uri' => $t['handle'], 'principaluri' => $principalUri, '{DAV:}displayname' => $t['name'],
                '{urn:ietf:params:xml:ns:carddav}addressbook-description' => (string) ($t['description'] ?? ''),
                '{http://calendarserver.org/ns/}getctag' => Dav::ctag($t, 'card')];
        }
        return $out;
    }

    public function updateAddressBook($addressBookId, PropPatch $propPatch)
    {
    }

    public function createAddressBook($principalUri, $url, array $properties)
    {
        throw new Forbidden('Adressbücher werden im CMS angelegt.');
    }

    public function deleteAddressBook($addressBookId)
    {
        throw new Forbidden('Adressbücher können nur im CMS gelöscht werden.');
    }

    private function entries(array $t): array
    {
        $objs = Dav::objects('card', $t['handle']);
        $out = [];
        foreach (Entries::query($t, ['status' => 'all', 'lang' => Lang::default(), 'limit' => 5000]) as $e) {
            $o = $objs[$e['id']] ?? null;
            if ($o && (int) $o['hidden'] === 1 && $e['status'] === 'draft') continue;
            $out[] = [$e, $o];
        }
        return $out;
    }

    private function row(array $t, array $e, ?array $o): array
    {
        $data = self::render($t, $e, $o);
        return ['id' => (int) $e['id'], 'uri' => $o['uri'] ?? 'cms-' . (int) $e['id'] . '.vcf', 'lastmodified' => strtotime((string) $e['updated_at']) ?: time(),
            'etag' => Dav::etag($data), 'size' => strlen($data), 'carddata' => $data];
    }

    public function getCards($addressbookId)
    {
        $t = $this->table($addressbookId);
        return array_map(fn($x) => $this->row($t, $x[0], $x[1]), $this->entries($t));
    }

    private function find(array $t, string $uri): array
    {
        $o = Dav::objectByUri($t['handle'], $uri);
        $id = $o ? (int) $o['entry_id'] : (preg_match('~^cms-(\d+)\.vcf$~', $uri, $m) ? (int) $m[1] : 0);
        $e = $id ? Entries::find($t, $id) : null;
        // Geteilte Tabellen: fremde Einträge nur, wenn die Website sie sehen darf
        if ($e && !\Core\Data\Shared::visibleAll($t, $e)) $e = null;
        if (!$e || ($o && (int) $o['hidden'] === 1 && $e['status'] === 'draft')) return [null, $o];
        return [$e, $o ?: (Dav::objects('card', $t['handle'])[$id] ?? null)];
    }

    public function getCard($addressBookId, $cardUri)
    {
        $t = $this->table($addressBookId);
        [$e, $o] = $this->find($t, (string) $cardUri);
        return $e ? $this->row($t, $e, $o) : false;
    }

    public function createCard($addressBookId, $cardUri, $cardData)
    {
        $t = $this->table($addressBookId);
        if (!Dav::canWrite($t)) throw new Forbidden('Nur lesender Zugriff.');
        if (Dav::objectByUri($t['handle'], (string) $cardUri)) throw new BadRequest('Kontakt existiert bereits.');
        [$in, $uid] = self::parse($t, null, (string) $cardData);
        $in['status'] = Dav::tableSettings($t)['status'] === 'draft' || ($t['settings']['workflow'] && !Dav::can('data.publish', $t)) ? 'draft' : 'published';
        [$id, $errors] = Entries::save($t, null, $in);
        if ($errors) throw new BadRequest('Ungültige Eingaben: ' . implode(' ', $errors));
        Dav::saveObject('card', $t['handle'], (int) $id, (string) $cardUri, $uid, (string) $cardData);
        return null;
    }

    public function updateCard($addressBookId, $cardUri, $cardData)
    {
        $t = $this->table($addressBookId);
        if (!Dav::canWrite($t)) throw new Forbidden('Nur lesender Zugriff.');
        [$e, $o] = $this->find($t, (string) $cardUri);
        if (!$e) throw new NotFound('Kontakt nicht gefunden.');
        if (\Core\Data\Shared::isForeign($t, $e)) throw new Forbidden('Kontakt einer anderen Website – nur lesbar.');
        [$in, $uid] = self::parse($t, $e, (string) $cardData);
        [, $errors] = Entries::save($t, (int) $e['id'], $in);
        if ($errors) throw new BadRequest('Ungültige Eingaben: ' . implode(' ', $errors));
        Dav::saveObject('card', $t['handle'], (int) $e['id'], (string) $cardUri, $o['uid'] ?? $uid, (string) $cardData);
        return null;
    }

    public function deleteCard($addressBookId, $cardUri)
    {
        $t = $this->table($addressBookId);
        if (!Dav::canWrite($t)) throw new Forbidden('Nur lesender Zugriff.');
        [$e, $o] = $this->find($t, (string) $cardUri);
        if (!$e) return false;
        if (\Core\Data\Shared::isForeign($t, $e)) throw new Forbidden('Kontakt einer anderen Website – nur lesbar.');
        if (Dav::tableSettings($t)['on_delete'] === 'delete' && Dav::can('data.delete', $t)) {
            Entries::delete($t, (int) $e['id']);
            app()->db->query('DELETE FROM dav_objects WHERE table_handle = ? AND entry_id = ?', [$t['handle'], (int) $e['id']]);
            return true;
        }
        Entries::setStatus($t, [(int) $e['id']], 'draft');
        Dav::saveObject('card', $t['handle'], (int) $e['id'], $o['uri'] ?? 'cms-' . (int) $e['id'] . '.vcf', $o['uid'] ?? self::uid($t, $e), $o['raw'] ?? null);
        app()->db->query('UPDATE dav_objects SET hidden = 1 WHERE table_handle = ? AND entry_id = ?', [$t['handle'], (int) $e['id']]);
        return true;
    }

    // ------------------------------------------------------------------ Zuordnung

    public static function uid(array $t, array $e): string
    {
        return \Core\Data\Calendar::uid($t, $e);
    }

    /** Felder vom Typ E-Mail/Telefon mit vCard-Typ */
    private static function typed(array $t, string $type): array
    {
        $out = [];
        foreach ($t['fields'] as $f) {
            if ($f['type'] !== $type) continue;
            $s = mb_strtolower($f['name'] . ' ' . $f['label']);
            $out[$f['name']] = match (true) {
                (bool) preg_match('~mobil|handy|cell~', $s) => 'CELL',
                str_contains($s, 'fax') => 'FAX',
                (bool) preg_match('~privat|home|zuhause~', $s) => 'HOME',
                default => 'WORK',
            };
        }
        return $out;
    }

    /** Name der X-Eigenschaft für ein Feld: X-MYCMS-FACHGEBIET */
    public static function xName(string $field): string
    {
        return 'X-MYCMS-' . strtoupper(str_replace('_', '-', $field));
    }

    /** Felder, die als X-MYCMS-* ausgegeben werden (alles, was nicht zugeordnet ist) */
    private static function extraFields(array $t, array $map): array
    {
        $used = array_merge(array_values($map), array_keys(self::typed($t, 'email')), array_keys(self::typed($t, 'tel')));
        return array_values(array_filter($t['fields'], fn($f) => !in_array($f['name'], $used, true)));
    }

    private static function fieldText(array $f, mixed $v): string
    {
        return match (true) {
            $f['type'] === 'bool' => $v ? '1' : '0',
            is_array($v) => implode(',', array_map('strval', $v)),
            default => (string) ($v ?? ''),
        };
    }

    /** vCard 3.0 eines Eintrags (mit Rohdaten des Clients zusammengeführt) */
    public static function render(array $t, array $e, ?array $o): string
    {
        $map = Dav::tableSettings($t)['map'];
        $v = fn(string $k) => isset($map[$k]) && $map[$k] !== '' ? trim(strip_tags((string) ($e[$map[$k]] ?? ''))) : '';
        $card = new VCard(['VERSION' => '3.0', 'PRODID' => '-//KLXM//' . CMS_NAME . ' ' . CMS_VERSION . '//DE', 'UID' => $o['uid'] ?? self::uid($t, $e)]);
        $family = $v('n_family');
        $given = $v('n_given');
        $fn = $v('fn') ?: trim($given . ' ' . $family) ?: $v('org') ?: Entries::title($t, $e);
        $card->add('FN', $fn);
        $card->add('N', [$family ?: ($v('fn') ? $fn : ''), $given, '', '', '']);
        if ($v('org') !== '') $card->add('ORG', [isset($map['org']) && (Tables::field($t, $map['org'])['type'] ?? '') === 'select' ? Tables::optionLabel(Tables::field($t, $map['org']), $v('org')) : $v('org')]);
        if ($v('title') !== '') $card->add('TITLE', $v('title'));
        foreach (self::typed($t, 'email') as $name => $type) {
            if (trim((string) ($e[$name] ?? '')) !== '') $card->add('EMAIL', trim((string) $e[$name]), ['TYPE' => ['INTERNET', $type === 'HOME' ? 'HOME' : 'WORK']]);
        }
        foreach (self::typed($t, 'tel') as $name => $type) {
            if (trim((string) ($e[$name] ?? '')) !== '') $card->add('TEL', trim((string) $e[$name]), ['TYPE' => $type === 'CELL' ? ['CELL', 'VOICE'] : [$type, $type === 'FAX' ? 'FAX' : 'VOICE']]);
        }
        if ($v('adr_street') . $v('adr_zip') . $v('adr_city') . $v('adr_country') !== '') {
            $country = $v('adr_country');
            if ($country !== '' && (Tables::field($t, $map['adr_country'])['type'] ?? '') === 'select') $country = Tables::optionLabel(Tables::field($t, $map['adr_country']), $country);
            $card->add('ADR', ['', '', $v('adr_street'), $v('adr_city'), '', $v('adr_zip'), $country], ['TYPE' => 'WORK']);
        }
        if ($v('url') !== '') $card->add('URL', link_href($v('url')));
        if (isset($map['note']) && $map['note'] !== '' && ($note = \Core\Data\ICal::plain((string) ($e[$map['note']] ?? ''))) !== '') $card->add('NOTE', $note);
        if ($v('bday') !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $v('bday'))) $card->add('BDAY', $v('bday'), ['VALUE' => 'DATE']);
        if (!empty($map['photo']) && !empty($e[$map['photo']]) && ($jpeg = self::photo((int) $e[$map['photo']]))) {
            $card->add('PHOTO', $jpeg, ['ENCODING' => 'b', 'TYPE' => 'JPEG']);
        }
        if (!empty($map['categories']) && ($cf = Tables::field($t, $map['categories']))) {
            $cats = array_values(array_filter(array_map(fn($k) => Tables::optionLabel($cf, (string) $k, Lang::default()), (array) ($e[$map['categories']] ?? []))));
            if ($cats) $card->add('CATEGORIES', $cats);
        }
        foreach (self::extraFields($t, $map) as $f) {
            $val = self::fieldText($f, $e[$f['name']] ?? null);
            if ($val !== '' && $val !== '0' || $f['type'] === 'bool') $card->add(self::xName($f['name']), $val);
        }
        $card->add('REV', gmdate('Ymd\THis\Z', strtotime((string) $e['updated_at']) ?: time()));
        if (empty($o['raw'])) return $card->serialize();

        try {
            $raw = Reader::read($o['raw'], Reader::OPTION_FORGIVING);
        } catch (\Throwable) {
            return $card->serialize();
        }
        if (!$raw instanceof VCard) return $card->serialize();
        if ((string) $raw->VERSION !== '3.0') $raw = $raw->convert(\Sabre\VObject\Document::VCARD30);
        $managed = ['FN', 'N', 'ORG', 'TITLE', 'EMAIL', 'TEL', 'ADR', 'URL', 'NOTE', 'BDAY', 'REV', 'PRODID'];
        if (!empty($map['photo'])) $managed[] = 'PHOTO';
        if (!empty($map['categories'])) $managed[] = 'CATEGORIES';
        // Namenszusätze (Titel „Dr.“, Suffix) und Anzeigename der App behalten, solange Vor-/Nachname gleich sind
        $rawN = isset($raw->N) ? array_pad($raw->N->getParts(), 5, '') : null;
        $rawFn = isset($raw->FN) ? (string) $raw->FN : null;
        foreach ($managed as $p) $raw->remove($p);
        foreach ($raw->children() as $p) {
            if ($p instanceof \Sabre\VObject\Property && str_starts_with(strtoupper($p->name), 'X-MYCMS-')) $raw->remove($p);
        }
        foreach ($card->children() as $p) {
            if (in_array($p->name, ['VERSION', 'UID'], true)) continue;
            $raw->add(clone $p);
        }
        $newN = $card->N->getParts();
        if ($rawN && $rawN[0] === (string) ($newN[0] ?? '') && $rawN[1] === (string) ($newN[1] ?? '')) {
            $raw->remove('N');
            $raw->add('N', $rawN);
            if ($rawFn !== null && empty($map['fn'])) {
                $raw->remove('FN');
                $raw->add('FN', $rawFn);
            }
        }
        return $raw->serialize();
    }

    /** Bild aus der Mediathek als JPEG (max. 400 px) */
    private static function photo(int $id): ?string
    {
        $m = Media::find($id);
        if (!$m || !str_starts_with((string) $m['mime'], 'image/')) return null;
        $dir = !empty($m['_pool']) ? \Core\MediaPools::mediaDir((string) $m['_pool']) : site()->mediaDir();
        $file = rtrim($dir, '/') . '/' . $m['file'];
        if (!is_file($file) || !function_exists('imagecreatefromstring')) return null;
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if (!$img) return null;
        $w = imagesx($img);
        $h = imagesy($img);
        $s = min(1, 400 / max($w, $h));
        $out = imagecreatetruecolor(max(1, (int) round($w * $s)), max(1, (int) round($h * $s)));
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        ob_start();
        imagejpeg($out, null, 82);
        return (string) ob_get_clean();
    }

    /** vCard → Feldwerte. @return array{0: array, 1: string} */
    public static function parse(array $t, ?array $e, string $data): array
    {
        try {
            $card = Reader::read($data, Reader::OPTION_FORGIVING);
        } catch (\Throwable $ex) {
            throw new BadRequest('Ungültige vCard: ' . $ex->getMessage());
        }
        if (!$card instanceof VCard) throw new BadRequest('Erwartet wird eine vCard.');
        $map = Dav::tableSettings($t)['map'];
        $in = [];
        $set = function (string $k, string $val) use (&$in, $map) { if (!empty($map[$k])) $in[$map[$k]] = trim($val); };
        $n = isset($card->N) ? $card->N->getParts() : [];
        $fn = trim((string) ($card->FN ?? ''));
        $set('fn', $fn);
        $set('n_family', (string) ($n[0] ?? '') ?: (empty($map['n_given']) ? $fn : ''));
        $set('n_given', (string) ($n[1] ?? ''));
        $org = isset($card->ORG) ? (string) ($card->ORG->getParts()[0] ?? '') : '';
        if (!empty($map['org']) && (Tables::field($t, $map['org'])['type'] ?? '') === 'select') {
            $org = self::optionKey(Tables::field($t, $map['org']), [$org])[0] ?? '';
        }
        $set('org', $org);
        $set('title', (string) ($card->TITLE ?? ''));
        $adr = isset($card->ADR) ? $card->ADR->getParts() : [];
        $set('adr_street', (string) ($adr[2] ?? ''));
        $set('adr_city', (string) ($adr[3] ?? ''));
        $set('adr_zip', (string) ($adr[5] ?? ''));
        $country = (string) ($adr[6] ?? '');
        if (!empty($map['adr_country']) && (Tables::field($t, $map['adr_country'])['type'] ?? '') === 'select') $country = self::optionKey(Tables::field($t, $map['adr_country']), [$country])[0] ?? '';
        $set('adr_country', $country);
        $set('url', (string) ($card->URL ?? ''));
        if (!empty($map['note'])) {
            $note = trim(str_replace("\r", '', (string) ($card->NOTE ?? '')));
            $cur = $e ? \Core\Data\ICal::plain((string) ($e[$map['note']] ?? '')) : '';
            if ($note !== $cur) $in[$map['note']] = (Tables::field($t, $map['note'])['type'] ?? '') === 'richtext'
                ? implode('', array_map(fn($p) => '<p>' . str_replace("\n", '<br>', e($p)) . '</p>', preg_split("~\n{2,}~", $note) ?: [])) : $note;
        }
        if (!empty($map['bday'])) {
            $b = (string) ($card->BDAY ?? '');
            $in[$map['bday']] = preg_match('~^(\d{4})-?(\d{2})-?(\d{2})~', $b, $m) ? "$m[1]-$m[2]-$m[3]" : '';
        }
        if (!empty($map['categories']) && ($cf = Tables::field($t, $map['categories'])) && isset($card->CATEGORIES)) {
            $keys = self::optionKey($cf, $card->CATEGORIES->getParts());
            $in[$map['categories']] = $cf['type'] === 'multiselect' ? $keys : ($keys[0] ?? '');
        }
        // E-Mail und Telefon: passender Typ zuerst, übrige der Reihe nach
        foreach (['email' => 'EMAIL', 'tel' => 'TEL'] as $type => $prop) {
            $fields = self::typed($t, $type);
            foreach ($fields as $name => $_) $in[$name] = '';
            $values = [];
            foreach ($card->select($prop) as $p) {
                $types = array_map('strtoupper', isset($p['TYPE']) ? $p['TYPE']->getParts() : []);
                $values[] = [trim(preg_replace('~^tel:~i', '', (string) $p)), $types];
            }
            foreach ($values as $i => [$val, $types]) {
                foreach ($fields as $name => $ft) {
                    if ($in[$name] === '' && in_array($ft, $types, true)) { $in[$name] = $val; unset($values[$i]); break; }
                }
            }
            foreach ($values as [$val]) {
                foreach ($fields as $name => $ft) if ($in[$name] === '' && $ft !== 'FAX') { $in[$name] = $val; break; }
            }
        }
        // Übrige Felder aus X-MYCMS-*
        $extra = [];
        foreach (self::extraFields($t, $map) as $f) $extra[self::xName($f['name'])] = $f;
        foreach ($card->children() as $p) {
            $name = strtoupper((string) $p->name);
            if (!isset($extra[$name])) continue;
            $f = $extra[$name];
            $val = (string) $p;
            $in[$f['name']] = match ($f['type']) {
                'bool' => in_array(strtolower($val), ['1', 'true', 'ja', 'yes'], true),
                'multiselect', 'relations' => array_values(array_filter(array_map('trim', explode(',', $val)), fn($x) => $x !== '')),
                default => $val,
            };
        }
        $tf = $t['settings']['title_field'];
        if ($tf !== '' && trim((string) ($in[$tf] ?? $e[$tf] ?? '')) === '') $in[$tf] = $fn ?: ($org ?: '(ohne Namen)');
        return [$in, trim((string) ($card->UID ?? '')) ?: bin2hex(random_bytes(16))];
    }

    /** Auswahl-Kurznamen zu Texten (Kurzname, Beschriftung oder Übersetzung, ohne Groß/klein) */
    private static function optionKey(array $f, array $texts): array
    {
        $wanted = array_map(fn($x) => mb_strtolower(trim((string) $x)), $texts);
        $out = [];
        foreach ((array) ($f['options'] ?? []) as $k => $label) {
            $names = array_map('mb_strtolower', array_merge([(string) $k, (string) $label], array_map(fn($l) => (string) ($l[$k] ?? ''), (array) ($f['options_i18n'] ?? []))));
            if (array_intersect($names, $wanted)) $out[] = (string) $k;
        }
        return $out;
    }
}
