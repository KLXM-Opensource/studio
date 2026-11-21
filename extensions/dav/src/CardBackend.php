<?php
declare(strict_types=1);

namespace Klxm\Dav;

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
 * Alle übrigen Felder als X-MYCMS-{FELD} (z. B. X-MYCMS-FACHGEBIET) – in beide Richtungen; Rich-Text als reiner Text.
 * Nur FN zugeordnet → N aus dem ganzen Namen (splitName); ORG ohne Feld aus der Einstellung org; published_only blendet Entwürfe aus.
 * Unbekannte Eigenschaften aus Apps bleiben in dav_objects.raw erhalten.
 *
 * Kontakte-Tabellen (Core\Data\Kinds, Art contacts) brauchen keine Zuordnung: das Grundschema geht fest auf die vCard
 * (contactCard/contactIn) – Mehrfachfelder mit vCard-TYPE bzw. Apple-Beschriftung (item.X-ABLabel), Listen als CATEGORIES.
 * Eine in der App neue oder geänderte Angabe ist nie von selbst öffentlich: „Öffentlich“ bleibt nur bei unverändertem Wert.
 * Felder, die bewusst nur für andere Ziele freigegeben sind (Exposure::appFields, z. B. „Nur Website“), und Altfelder gehen nie an
 * Apps und werden von Apps nie geschrieben.
 */
final class CardBackend extends \Sabre\CardDAV\Backend\AbstractBackend
{
    private function table(mixed $id): array
    {
        $t = Tables::find((int) $id);
        if (!$t || !(Dav::tableSettings($t)['addressbook'] || Dav::aboTable($t)) || !Dav::canSee($t)) throw new NotFound('Adressbuch nicht gefunden.');
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
        $s = Dav::tableSettings($t);
        $out = [];
        foreach (Entries::query($t, ['status' => 'all', 'lang' => Lang::default(), 'limit' => 5000]) as $e) {
            $o = $objs[$e['id']] ?? null;
            if ($o && (int) $o['hidden'] === 1 && $e['status'] === 'draft') continue;
            if (!Dav::visible($s, $e) || !Dav::aboAllows($t, $e)) continue;
            $out[] = [$e, $o];
        }
        return $out;
    }

    private function row(array $t, array $e, ?array $o): array
    {
        $data = self::render($t, Dav::aboEntry($t, $e), $o);   // Abo-Token „nur öffentlich“: nur freigegebene Angaben
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
        // „Nur veröffentlichte Einträge“: Entwürfe sind für Apps nicht vorhanden (Abruf/Ändern → 404, Löschen → nichts zu tun)
        if (!Dav::visible(Dav::tableSettings($t), $e) || !Dav::aboAllows($t, $e)) return [null, $o];
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
        // Adresse eines vorhandenen, für Apps nicht sichtbaren Eintrags (Entwurf bei „Nur veröffentlichte Einträge“) nicht neu belegen
        if (Dav::reservedUri($t, (string) $cardUri, 'vcf')) throw new Forbidden('Kontakt ist im CMS nicht veröffentlicht – nur dort bearbeitbar.');
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

    /** Felder mit verschachtelten Werten (JSON: Blöcke, Kombifelder, Gruppen) – nicht als Text abbildbar, daher nie X-MYCMS-* */
    private const NESTED = ['blocks', 'mediatext', 'person', 'faq', 'address', 'downloads', 'group', 'recurrence'];

    /** Felder, die als X-MYCMS-* ausgegeben werden (alles, was nicht zugeordnet ist, ohne verschachtelte Felder) */
    private static function extraFields(array $t, array $map): array
    {
        $used = \Core\Data\Kinds::isContacts($t) ? \Core\Data\Kinds::systemNames(\Core\Data\Kinds::CONTACTS)
            : array_merge(array_values($map), array_keys(self::typed($t, 'email')), array_keys(self::typed($t, 'tel')));
        return array_values(array_filter(\Core\Data\Exposure::appFields($t), fn($f) => !in_array($f['name'], $used, true) && !in_array($f['type'], self::NESTED, true)
            && !\Core\Combo::isMulti($f['type'])));
    }

    /** vCard-Eigenschaft je Mehrfachfeld des Grundschemas */
    private const MULTI_PROPS = ['telefon' => ['phones', 'TEL'], 'email' => ['emails', 'EMAIL'], 'adresse' => ['addresses', 'ADR'], 'web' => ['urls', 'URL']];

    /** Kontakt (Art contacts) → vCard ohne Zuordnung: Grundschema fest, Mehrfachfelder beschriftet, Listen = CATEGORIES */
    private static function contactCard(array $t, array $e, VCard $card): void
    {
        $e = \Core\Data\Exposure::forApps($t, $e);
        $s = fn(string $k) => trim(strip_tags((string) (is_scalar($e[$k] ?? null) ? $e[$k] : '')));
        $isOrg = !empty($e['ist_firma']) && $s('firma') !== '';
        $fn = $s('name') ?: Entries::title($t, $e);
        $card->add('FN', $fn);
        $card->add('N', $isOrg ? ['', '', '', '', ''] : ($s('nachname') . $s('vorname') !== '' ? [$s('nachname'), $s('vorname'), '', $s('praefix'), ''] : self::splitName($fn)));
        if ($s('firma') !== '') $card->add('ORG', array_values(array_filter([$s('firma'), $s('abteilung')], fn($x) => $x !== '')));
        if ($isOrg) $card->add('X-ABSHOWAS', 'COMPANY');
        if ($s('position') !== '') $card->add('TITLE', $s('position'));
        $n = 0;
        foreach (self::MULTI_PROPS as $field => [$type, $prop]) {
            if ((Tables::field($t, $field)['type'] ?? '') !== $type) continue;
            foreach (\Core\Combo::items($e[$field] ?? null) as $i) {
                if (\Core\Combo::itemText($type, $i) === '') continue;
                $val = $prop === 'ADR' ? ['', '', trim((string) ($i['street'] ?? '')), trim((string) ($i['city'] ?? '')), '', trim((string) ($i['zip'] ?? '')), trim((string) ($i['country'] ?? ''))]
                    : trim((string) $i['value']);
                $types = \Core\Combo::VCARD_TYPES[$type][(string) ($i['label'] ?? '')] ?? null;
                if ($types !== null) {
                    $card->add($prop, $val, ['TYPE' => $types]);
                    continue;
                }
                $n++;
                // Gruppe „itemN.“: Wert und Apple-Beschriftung gehören zusammen
                $card->add("item$n.$prop", $val, $prop === 'EMAIL' ? ['TYPE' => ['INTERNET']] : []);
                $card->add("item$n.X-ABLABEL", \Core\Combo::labelText($type, $i, true));
            }
        }
        if (($note = $s('notiz')) !== '' && Tables::field($t, 'notiz')) $card->add('NOTE', $note);
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $s('geburtstag'))) $card->add('BDAY', $s('geburtstag'), ['VALUE' => 'DATE']);
        if (!empty($e['foto']) && ($jpeg = self::photo((int) $e['foto']))) $card->add('PHOTO', $jpeg, ['ENCODING' => 'b', 'TYPE' => 'JPEG']);
        if (($lf = Tables::field($t, 'listen')) && ($cats = array_values(array_filter(array_map(fn($k) => Tables::optionLabel($lf, (string) $k, Lang::default()), (array) ($e['listen'] ?? [])))))) {
            $card->add('CATEGORIES', $cats);
        }
    }

    /**
     * vCard → Felder eines Kontakts (Art contacts). Mehrfachfelder: Beschriftung aus TYPE bzw. X-ABLabel (eigene Beschriftung,
     * wenn unbekannt); „Öffentlich“ nur, wenn dieselbe Angabe vorher schon öffentlich war (neue/geänderte Angaben sind intern).
     */
    private static function contactIn(array $t, ?array $e, VCard $card): array
    {
        $in = [];
        $n = isset($card->N) ? array_pad($card->N->getParts(), 5, '') : ['', '', '', '', ''];
        $fn = trim((string) ($card->FN ?? ''));
        $org = isset($card->ORG) ? array_pad($card->ORG->getParts(), 2, '') : ['', ''];
        $in['nachname'] = trim((string) $n[0]);
        $in['vorname'] = trim((string) $n[1]);
        $in['praefix'] = trim((string) $n[3]);
        $in['firma'] = trim((string) $org[0]);
        $in['abteilung'] = trim((string) $org[1]);
        $in['position'] = trim((string) ($card->TITLE ?? ''));
        $in['ist_firma'] = strtoupper(trim((string) ($card->{'X-ABSHOWAS'} ?? ''))) === 'COMPANY';
        // Anzeigename: FN der App, außer er ist nur „Vorname Nachname“ (dann bildet ihn das CMS selbst)
        $in['name'] = $fn !== '' && $fn !== trim($in['vorname'] . ' ' . $in['nachname']) && $fn !== trim($in['praefix'] . ' ' . $in['vorname'] . ' ' . $in['nachname']) ? $fn : '';
        $labels = [];
        foreach ($card->children() as $p) {
            if ($p instanceof \Sabre\VObject\Property && strtoupper($p->name) === 'X-ABLABEL' && $p->group) $labels[strtolower((string) $p->group)] = (string) $p;
        }
        foreach (self::MULTI_PROPS as $field => [$type, $prop]) {
            if ((Tables::field($t, $field)['type'] ?? '') !== $type) continue;
            $before = [];
            foreach (\Core\Combo::items($e[$field] ?? null) as $i) $before[self::itemKey($type, $i)] = $i['public'] ?? false;
            $items = [];
            foreach ($card->select($prop) as $p) {
                $types = array_map('strtoupper', isset($p['TYPE']) ? $p['TYPE']->getParts() : []);
                $ab = $p->group ? ($labels[strtolower((string) $p->group)] ?? null) : null;
                $item = ['label' => self::labelFrom($type, $types, $ab)];
                if ($item['label'] === \Core\Combo::CUSTOM) $item['label_custom'] = self::appleLabel((string) $ab);
                if ($prop === 'ADR') {
                    $a = array_pad($p->getParts(), 7, '');
                    $item += ['street' => trim((string) $a[2]), 'zip' => trim((string) $a[5]), 'city' => trim((string) $a[3]), 'country' => trim((string) $a[6])];
                } else {
                    $item['value'] = trim(preg_replace('~^(tel|mailto):~i', '', (string) $p) ?? '');
                }
                if (\Core\Combo::itemText($type, $item) === '') continue;
                $item['public'] = $before[self::itemKey($type, $item)] ?? ($type === 'addresses' ? '0' : false);
                $items[] = $item;
            }
            $in[$field] = $items;
        }
        $in['notiz'] = trim(str_replace("\r", '', (string) ($card->NOTE ?? '')));
        $b = (string) ($card->BDAY ?? '');
        $in['geburtstag'] = preg_match('~^(\d{4})-?(\d{2})-?(\d{2})~', $b, $m) ? "$m[1]-$m[2]-$m[3]" : '';
        if (($lf = Tables::field($t, 'listen')) && isset($card->CATEGORIES)) $in['listen'] = self::optionKey($lf, $card->CATEGORIES->getParts());
        if (!$e) $in[\Core\Data\Kinds::PUBLIC_FIELD] = false;   // neu aus der App: nie von selbst auf der Website
        return $in;
    }

    /** Vergleichsschlüssel einer Angabe (Ziffern einer Nummer, E-Mail klein, Adresse ohne Leerraum) */
    private static function itemKey(string $type, array $i): string
    {
        $v = \Core\Combo::itemText($type, $i);
        return $type . ':' . ($type === 'phones' ? preg_replace('~[^\d+]~', '', $v) : mb_strtolower((string) preg_replace('~\s+~u', '', $v)));
    }

    /** Beschriftung aus vCard-TYPE bzw. X-ABLabel (Teile übernommen aus KLXM/contacts VCard/Labels, MIT) */
    private static function labelFrom(string $type, array $types, ?string $ab): string
    {
        if ($ab !== null && trim($ab) !== '') {
            $text = self::appleLabel($ab);
            foreach (\Core\Combo::LABELS[$type] as $k => $de) if (mb_strtolower($text) === mb_strtolower($de) || mb_strtolower($text) === mb_strtolower(lt($de)) || strtolower($text) === $k) return $k;
            return \Core\Combo::CUSTOM;
        }
        $has = fn(string $x) => in_array($x, $types, true);
        return match ($type) {
            'phones' => match (true) { $has('FAX') => 'fax', $has('CELL') || $has('IPHONE') => 'mobile', $has('MAIN') => 'main', $has('HOME') => 'home', $has('WORK') => 'work', default => 'other' },
            'urls' => $has('HOME') ? 'home' : ($has('WORK') ? 'work' : 'homepage'),
            default => $has('HOME') ? 'home' : ($has('WORK') ? 'work' : 'other'),
        };
    }

    /** Apple-Beschriftung „_$!<Mobile>!$_“ → „Mobile“, sonst der Text */
    private static function appleLabel(string $ab): string
    {
        $ab = trim($ab);
        if (preg_match('~^_\$!<(.+)>!\$_$~', $ab, $m)) {
            return ['Mobile' => 'mobile', 'Home' => 'home', 'Work' => 'work', 'Main' => 'main', 'Other' => 'other', 'HomePage' => 'homepage'][$m[1]] ?? $m[1];
        }
        return $ab;
    }

    private static function fieldText(array $f, mixed $v): string
    {
        return match (true) {
            $f['type'] === 'bool' => $v ? '1' : '0',
            $f['type'] === 'richtext' => self::plain($v),
            is_array($v) => implode(',', array_map('strval', $v)),
            default => (string) ($v ?? ''),
        };
    }

    /** vCard 3.0 eines Eintrags (mit Rohdaten des Clients zusammengeführt); $s = Einstellungen der Tabelle (Standard: gespeicherte) */
    public static function render(array $t, array $e, ?array $o, ?array $s = null): string
    {
        $s ??= Dav::tableSettings($t);
        $e = \Core\Data\Exposure::forApps($t, $e);           // „Nur Website“-Felder und Altfelder nie an Apps
        $map = $s['map'];
        $v = fn(string $k) => isset($map[$k]) && $map[$k] !== '' ? trim(strip_tags((string) ($e[$map[$k]] ?? ''))) : '';
        $card = new VCard(['VERSION' => '3.0', 'PRODID' => '-//KLXM//' . CMS_NAME . ' ' . CMS_VERSION . '//DE', 'UID' => $o['uid'] ?? self::uid($t, $e)]);
        $contacts = \Core\Data\Kinds::isContacts($t);
        if ($contacts) {
            self::contactCard($t, $e, $card);
            $map = [];
        } else {
            $family = $v('n_family');
            $given = $v('n_given');
            $fn = $v('fn') ?: trim($given . ' ' . $family) ?: $v('org') ?: Entries::title($t, $e);
            $card->add('FN', $fn);
            // Nur „Name (FN)“ zugeordnet: N aus dem ganzen Namen ableiten (Nachname;Vorname;;Präfix;Suffix), damit Apps richtig sortieren
            $card->add('N', $family === '' && $given === '' && $v('fn') !== '' ? self::splitName($fn) : [$family, $given, '', '', '']);
            if ($v('org') !== '') $card->add('ORG', [isset($map['org']) && (Tables::field($t, $map['org'])['type'] ?? '') === 'select' ? Tables::optionLabel(Tables::field($t, $map['org']), $v('org')) : $v('org')]);
            elseif (empty($map['org']) && ($org = Dav::fixedOrg($s)) !== '') $card->add('ORG', [$org]);
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
            if (isset($map['note']) && $map['note'] !== '' && ($note = self::plain($e[$map['note']] ?? '')) !== '') $card->add('NOTE', $note);
            if ($v('bday') !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $v('bday'))) $card->add('BDAY', $v('bday'), ['VALUE' => 'DATE']);
            if (!empty($map['photo']) && !empty($e[$map['photo']]) && ($jpeg = self::photo((int) $e[$map['photo']]))) {
                $card->add('PHOTO', $jpeg, ['ENCODING' => 'b', 'TYPE' => 'JPEG']);
            }
            if (!empty($map['categories']) && ($cf = Tables::field($t, $map['categories']))) {
                $cats = array_values(array_filter(array_map(fn($k) => Tables::optionLabel($cf, (string) $k, Lang::default()), (array) ($e[$map['categories']] ?? []))));
                if ($cats) $card->add('CATEGORIES', $cats);
            }
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
        if (!empty($map['photo']) || $contacts) $managed[] = 'PHOTO';
        if (!empty($map['categories']) || $contacts) $managed[] = 'CATEGORIES';
        if ($contacts) array_push($managed, 'X-ABLABEL', 'X-ABSHOWAS');
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
        if (!$contacts && $rawN && $rawN[0] === (string) ($newN[0] ?? '') && $rawN[1] === (string) ($newN[1] ?? '')) {
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

    /** vCard → Feldwerte; $s = Einstellungen der Tabelle (Standard: gespeicherte). @return array{0: array, 1: string} */
    public static function parse(array $t, ?array $e, string $data, ?array $s = null): array
    {
        try {
            $card = Reader::read($data, Reader::OPTION_FORGIVING);
        } catch (\Throwable $ex) {
            throw new BadRequest('Ungültige vCard: ' . $ex->getMessage());
        }
        if (!$card instanceof VCard) throw new BadRequest('Erwartet wird eine vCard.');
        if (\Core\Data\Kinds::isContacts($t)) {
            $in = self::contactIn($t, $e, $card);
            $map = [];
        } else {
            $map = ($s ?? Dav::tableSettings($t))['map'];
            $in = [];
            $set = function (string $k, string $val) use (&$in, $map) { if (!empty($map[$k])) $in[$map[$k]] = trim($val); };
            $n = isset($card->N) ? $card->N->getParts() : [];
            $fn = trim((string) ($card->FN ?? ''));
            // Nur „Name (FN)“ zugeordnet: FN hat Vorrang (Pflicht in vCard 3/4). Ohne FN – oder wenn die App nur N geändert hat (FN wie
            // gespeichert, Vor-/Nachname anders) – den Namen aus N zusammensetzen
            if (!empty($map['fn']) && empty($map['n_family']) && empty($map['n_given'])) {
                $fromN = self::joinName($n);
                $cur = $e ? trim(strip_tags((string) ($e[$map['fn']] ?? ''))) : '';
                $was = self::splitName($cur);
                $nChanged = [trim((string) ($n[0] ?? '')), trim((string) ($n[1] ?? ''))] !== [$was[0], $was[1]];
                if ($fromN !== '' && ($fn === '' || ($cur !== '' && $fn === $cur && $nChanged))) $fn = $fromN;
            }
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
            if (!empty($map['note']) && ($nf = Tables::field($t, $map['note']))) {
                $note = self::textIn($nf, $e, (string) ($card->NOTE ?? ''));
                if ($note !== null) $in[$map['note']] = $note;
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
        }
        // Übrige Felder aus X-MYCMS-*
        $extra = [];
        foreach (self::extraFields($t, $map) as $f) $extra[self::xName($f['name'])] = $f;
        foreach ($card->children() as $p) {
            $name = strtoupper((string) $p->name);
            if (!isset($extra[$name])) continue;
            $f = $extra[$name];
            $val = (string) $p;
            if ($f['type'] === 'richtext') {
                // Ausgabe ist reiner Text: unverändert → Formatierung im CMS bleibt; geändert → Absätze/Umbrüche als HTML
                if (($val = self::textIn($f, $e, $val)) !== null) $in[$f['name']] = $val;
                continue;
            }
            $in[$f['name']] = match ($f['type']) {
                'bool' => in_array(strtolower($val), ['1', 'true', 'ja', 'yes'], true),
                'multiselect', 'relations' => array_values(array_filter(array_map('trim', explode(',', $val)), fn($x) => $x !== '')),
                default => $val,
            };
        }
        // „Nur Website“-Felder und Altfelder schreiben Apps nie (sie bekommen sie auch nicht)
        $apps = array_column(\Core\Data\Exposure::appFields($t, 'vcard'), 'name');
        foreach ($t['fields'] as $f) if (!in_array($f['name'], $apps, true)) unset($in[$f['name']]);
        $tf = $t['settings']['title_field'];
        $fn = trim((string) ($card->FN ?? ''));
        $org = isset($card->ORG) ? (string) ($card->ORG->getParts()[0] ?? '') : '';
        if (!\Core\Data\Kinds::isContacts($t) && $tf !== '' && trim((string) ($in[$tf] ?? $e[$tf] ?? '')) === '') $in[$tf] = $fn ?: ($org ?: '(ohne Namen)');
        return [$in, trim((string) ($card->UID ?? '')) ?: bin2hex(random_bytes(16))];
    }

    /** Feldwert als reiner Text für Apps: Absätze → Leerzeile, Umbrüche/Listenpunkte → Zeile, Entities dekodiert, ohne Redaktionsnotizen [# … #] */
    private static function plain(mixed $v): string
    {
        return \Core\Data\ICal::plain(\Core\EditorNotes::strip((string) ($v ?? '')));
    }

    /**
     * Text aus der App für ein Text-/Rich-Text-Feld (NOTE, X-MYCMS-* von Rich-Text): null, wenn er dem gespeicherten (als reiner Text)
     * entspricht – dann bleiben Formatierung und Redaktionsnotizen im CMS –, sonst der Wert (Rich-Text: Leerzeile → Absatz,
     * Zeilenumbruch → <br>, alles escaped); vorhandene Redaktionsnotizen werden wieder angehängt.
     */
    private static function textIn(array $f, ?array $e, string $text): ?string
    {
        $text = trim(str_replace("\r", '', $text));
        $cur = (string) ($e[$f['name']] ?? '');
        if ($e && $text === self::plain($cur)) return null;
        $notes = \Core\EditorNotes::find($cur);
        if ($f['type'] !== 'richtext') return trim($text . implode('', array_map(fn($n) => "\n\n[# $n #]", $notes)));
        return implode('', array_map(fn($p) => '<p>' . str_replace("\n", '<br>', e($p)) . '</p>', $text === '' ? [] : (preg_split("~\n{2,}~", $text) ?: [])))
            . implode('', array_map(fn($n) => '<p>[# ' . e($n) . ' #]</p>', $notes));
    }

    // ------------------------------------------------------------------ Namen

    /** Titel/Anrede vor dem Namen: Dr., Prof., Dr. med., Dipl.-Ing., Priv.-Doz., h. c., Herr, Frau … */
    private static function isPrefix(string $w): bool
    {
        return (bool) preg_match('~^(?:(?:dr|prof|med|dent|vet|rer|nat|pol|oec|phil|jur|theol|habil|soc|sc|mult|ing|mag|apl|univ|hon|em|hr|fr|mr|mrs|ms)\.'
            . '|(?:dr|prof)\.(?:\p{L}+\.)+|\p{L}+\.-\p{L}+\.?|h\.c\.|e\.h\.|herr|frau|pd)$~iu', $w);
    }

    /** Zusatz nach dem Namen (nach Komma oder am Ende): M.A., B.Sc., MBA, LL.M., Ph.D., jun., sen., III … */
    private static function isSuffix(string $w): bool
    {
        return (bool) preg_match('~^(?:(?:[mb]\.\s?(?:a|sc|eng|ed)\.|[mb]sc|mba|ll\.\s?[mb]\.|ph\.\s?d\.|phd|mdb|mdl|jun\.|jr\.|sen\.|sr\.|ii|iii|iv)[\s,]*)+$~iu', $w);
    }

    /** Nachnamen-Partikel (nur klein geschrieben): von, van, de, zu, von der, van den, … */
    private static function isParticle(string $w): bool
    {
        return in_array($w, ['von', 'van', 'vom', 'zu', 'zum', 'zur', 'und', 'de', 'der', 'den', 'des', 'del', 'della', 'di', 'da', 'du', 'dos', 'das',
            'le', 'la', 'ten', 'ter', 'op', 'af', 'av', 'y'], true);
    }

    /**
     * Ganzer Name → N-Teile [Nachname, Vorname, weitere, Präfix, Suffix]: letztes Wort = Nachname (mit Partikeln davor: „von der Leyen“),
     * der Rest Vorname; führende Titel/Anreden als Präfix, Zusätze am Ende als Suffix; „Musterfrau, Erika“ = Nachname, Vorname.
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
     */
    public static function splitName(string $fn): array
    {
        $fn = trim((string) preg_replace('~\s+~u', ' ', $fn));
        if ($fn === '') return ['', '', '', '', ''];
        $suffix = [];
        $family = null;
        if (preg_match('~^(.+?),\s*(.+)$~u', $fn, $m)) {
            if (self::isSuffix($m[2])) {
                [$fn, $suffix] = [$m[1], [$m[2]]];
            } elseif (!str_contains($m[2], ',')) {
                [$family, $fn] = [$m[1], $m[2]];          // „Musterfrau, Erika“ bzw. „Musterfrau, Dr. Erika“
            }
        }
        $w = explode(' ', $fn);
        $prefix = [];
        while (count($w) > ($family === null ? 1 : 0) && self::isPrefix($w[0])) $prefix[] = array_shift($w);
        if ($family === null) {
            while (count($w) > 1 && self::isSuffix((string) end($w))) array_unshift($suffix, (string) array_pop($w));
            $last = [(string) array_pop($w)];
            while (count($w) > 1 && self::isParticle((string) end($w))) array_unshift($last, (string) array_pop($w));
            $family = implode(' ', $last);
        }
        return [$family, implode(' ', $w), '', implode(' ', $prefix), implode(' ', $suffix)];
    }

    /** N-Teile → ganzer Name („Prof. Dr. Erika Musterfrau, M.A.“); leer ohne Vor- und Nachname */
    public static function joinName(array $n): string
    {
        $n = array_map(fn($x) => trim((string) $x), array_pad(array_values($n), 5, ''));
        if ($n[0] === '' && $n[1] === '') return '';
        $name = implode(' ', array_filter([$n[3], $n[1], $n[2], $n[0]], fn($x) => $x !== ''));
        return $n[4] !== '' ? $name . ', ' . $n[4] : $name;
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
