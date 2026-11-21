<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Data;

use Core\Combo;
use Core\Http\Request;
use Core\Pages;

/**
 * Selbsttest Adressbuch und Freigabe je Ziel – Teil von `php bin/console data:selftest`. Ohne Datenbank: Mehrfachfelder (Beschriftung,
 * Freigabe, „nur Ort“, Ausgabe), Freigabe-Regeln (Modus, Ziele, ältere Angaben, Rundlauf im Designer). Mit vorübergehenden Tabellen:
 *
 *  - Adressbuch (Art contacts): ein freigegebener Kontakt mit öffentlicher Dienst- und interner Mobilnummer, „Vita“ (Nur Website),
 *    Feld „Durchwahl“ (Redaktion entscheidet, Standard aus, für einen Eintrag an), ein nicht freigegebener Kontakt. Geprüft je Weg:
 *    Personen-Block (Filter und von Hand), Datenliste, Datensatz-Felder, Abfrage eines Kits auf der Website, Detailseite, JSON-LD,
 *    Visitenkarte (Route), Suchindex, RSS, CardDAV (falls dav geladen): Vita nie in vCard/CardDAV, aber auf der Detailseite.
 *  - Termine mit Feldern „Nur Website“ und „Redaktion entscheidet“: ICS-Datei und CalDAV ohne sie, Website mit ihnen.
 *  - data:convert: Probelauf ändert nichts, Umwandlung (Werte, Freigaben, Verknüpfung bleibt), Zurückdrehen.
 * Alles wird danach gelöscht.
 */
final class ContactsSelfTest
{
    private const MOBILE = '0171 5550188';   // interne Mobilnummer (Kontakt A) – darf nirgends öffentlich auftauchen
    private const WORK = '02841 5550177';    // öffentliche Dienstnummer
    private const VITA = 'Vita-Nur-Website-Musterstadt';
    private const HIDDEN = 'Verborgen Mustermann';   // Name des nicht freigegebenen Kontakts

    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails, 'Adressbuch – ');
        self::units($eq);
        $prevReq = app()->request;
        $prevEntry = app()->entry;
        $made = [];
        $load = fn(string $hh): array => Tables::find($hh) ?? throw new \RuntimeException("Tabelle „{$hh}“ fehlt");
        $no = fn(string $s) => !str_contains(str_replace([' ', '-', '/'], '', $s), str_replace(' ', '', self::MOBILE)) && !str_contains($s, self::HIDDEN);
        try {
            // ---------------------------------------------------------- Adressbuch
            $h = 'selftest_kb_' . bin2hex(random_bytes(3));
            $def = \Core\Http\Controllers\Admin\DataController::presetDef('contacts');
            $def['handle'] = $h;
            $def['name'] = 'Selbsttest Adressbuch';
            $def['settings']['route'] = str_replace('_', '-', $h);
            $def['fields'][] = ['label' => 'Vita', 'name' => 'vita', 'type' => 'richtext', 'exposure' => ['mode' => 'always', 'web' => 1, 'vcard' => 0, 'ics' => 0]];
            $def['fields'][] = ['label' => 'Durchwahl', 'name' => 'durchwahl', 'type' => 'text', 'exposure' => ['mode' => 'editor', 'web' => 0, 'vcard' => 0, 'ics' => 0]];
            [$clean, $err] = Tables::validate($def);
            $eq('Vorlage gültig', $err, []);
            $eq('Art und Version', [$clean['settings']['kind'] ?? null, $clean['settings']['kind_version'] ?? null], ['contacts', 1]);
            Tables::create($clean);
            $made[] = $h;
            $t = $load($h);
            [$rt, $rerr] = Tables::validate(Tables::toInput($t), $t);
            $eq('Rundlauf validate(toInput)', [$rerr, $rt['fields'] == Tables::allFields($t)], [[], true]);
            $eq('Grundfelder gesperrt (system)', !empty(Tables::field($t, 'telefon')['system']) && empty(Tables::field($t, 'vita')['system']), true);
            $eq('Spalte für Entscheidungen je Eintrag', in_array(Exposure::STATE, array_column(Tables::db($t)->fetchAll("PRAGMA table_info({$t['table']})"), 'name'), true), true);
            $pid = Pages::create(['slug' => '_vorlage-' . $h, 'title' => 'Selbsttest – Kontakt', 'type' => 'template', 'template_for' => $h, 'status' => 'published', 'noindex' => 1],
                Pages::sanitizeBlocks([['id' => bin2hex(random_bytes(5)), 'type' => 'data_fields', 'data' => ['table' => $h, 'layout' => 'portrait', 'fields' => []]]]));
            Tables::setDetailPage($t, $pid);
            $t = $load($h);
            [$a, $ea] = Entries::save($t, null, ['vorname' => 'Erika', 'nachname' => 'Musterfrau', 'position' => 'Vorsitz', 'oeffentlich' => true, 'status' => 'published',
                'telefon' => [['label' => 'office', 'value' => self::WORK, 'public' => '1'], ['label' => 'mobile', 'value' => self::MOBILE, 'public' => '0']],
                'email' => [['label' => 'work', 'value' => 'erika@example.org', 'public' => '1']], 'vita' => '<p>' . self::VITA . '</p>', 'durchwahl' => '-42',
                Exposure::STATE => ['durchwahl' => ['web' => '1', 'vcard' => '0']]]);
            [$b, $eb] = Entries::save($t, null, ['vorname' => 'Verborgen', 'nachname' => 'Mustermann', 'status' => 'published', 'durchwahl' => '-43',
                'telefon' => [['label' => 'work', 'value' => '0201 5550166', 'public' => '1']]]);
            $eq('Kontakte gespeichert', [$ea, $eb], [[], []]);
            $ra = Entries::find($t, (int) $a) ?? [];
            $rb = Entries::find($t, (int) $b) ?? [];
            $eq('Anzeigename gebildet, neu = nicht freigegeben', [$ra['name'] ?? null, $rb['oeffentlich'] ?? null], ['Erika Musterfrau', false]);
            $eq('Entscheidung je Eintrag gespeichert (Standard für B)', [Exposure::allowed(Tables::field($t, 'durchwahl') ?? [], $ra), Exposure::allowed(Tables::field($t, 'durchwahl') ?? [], $rb)], [true, false]);
            $eq('Verwaltung sieht alles', [count(Entries::query($t, ['status' => 'all'])), str_contains((string) json_encode($ra['telefon']), '5550188')], [2, true]);

            // Website-Anfrage: Abfragen (auch von Kits) liefern nur freigegebene Kontakte
            app()->request = new Request('GET', '/' . $t['settings']['route'], [], [], [], []);
            $eq('Kit-Abfrage auf der Website: nur freigegebene', array_column(Entries::query($t, ['limit' => 10]), 'id'), [(int) $a]);
            $eq('Detail per Slug: nicht freigegebener fehlt', Entries::bySlug($t, (string) $rb['slug']), null);
            $th = app()->theme;
            $render = function (array $blk) use ($th): string {
                $s = Pages::sanitizeBlocks([$blk]);
                return $s ? (string) $th->renderInner($th->makeBlock($s[0])) : '';
            };
            if (!empty($th->block('people')['core'])) {
                $ppl = $render(['id' => 'qakbpeople', 'type' => 'people', 'data' => ['table' => $h, 'source' => 'filter', 'contact' => true, 'vcard' => true]]);
                $eq('Personen-Block: eine Person, Dienstnummer, keine Mobilnummer', [substr_count($ppl, 'class="cms-person"'), str_contains($ppl, '5550177'), $no($ppl)], [1, true, true]);
                $man = $render(['id' => 'qakbpeople2', 'type' => 'people', 'data' => ['table' => $h, 'contact' => true, 'items' => [['person' => "entry:$h:$b"]]]]);
                $eq('Personen-Block von Hand: nicht freigegebener fehlt', str_contains($man, 'Verborgen'), false);
            }
            $list = $render(['id' => 'qakblist', 'type' => 'data_list', 'data' => ['table' => $h, 'layout' => 'table', 'fields' => ["$h._title", "$h.telefon", "$h.durchwahl"]]]);
            $eq('Datenliste: Dienstnummer, Durchwahl (freigegeben), keine Mobilnummer', [str_contains($list, '5550177'), str_contains($list, '-42'), $no($list)], [true, true, true]);
            $eq('Besucher-Suche: keine Treffer über interne Angaben', count(Entries::query($t, ['q' => '5550188', 'q_public' => true])), 0);
            $res = (new \Core\Http\Controllers\SiteController())->page(new Request('GET', '/' . $t['settings']['route'] . '/' . $ra['slug'], ['selbsttest' => '1'], [], [], []), $t['settings']['route'] . '/' . $ra['slug']);
            $eq('Detailseite: Vita (Nur Website) sichtbar, keine Mobilnummer', [$res->status, str_contains($res->body, self::VITA), $no($res->body)], [200, true, true]);
            app()->entry = $prevEntry;
            $ld = (string) json_encode(\Core\StructuredData::graph(Pages::find($pid) ?? [], $t, $ra), JSON_UNESCAPED_UNICODE);
            $eq('JSON-LD: Dienstnummer, keine Mobilnummer', [str_contains($ld, '5550177'), $no($ld)], [true, true]);
            $eq('JSON-LD: nicht freigegebener Kontakt ohne Person', str_contains((string) json_encode(\Core\StructuredData::graph(Pages::find($pid) ?? [], $t, $rb)), '"Person"'), false);
            $vc = (new \Core\Http\Controllers\VCardController())->person(new Request('GET', "/vcard/$h/{$ra['slug']}.vcf", [], [], [], []), $h, (string) $ra['slug']);
            $eq('Visitenkarte: Dienstnummer mit Beschriftung, keine Mobilnummer, keine Vita, Durchwahl nicht für vCard', [$vc->status, str_contains($vc->body, 'X-ABLabel:Geschäftsstelle'),
                $no($vc->body), str_contains($vc->body, self::VITA), str_contains($vc->body, '-42')], [200, true, true, false, false]);
            try {
                (new \Core\Http\Controllers\VCardController())->person(new Request('GET', "/vcard/$h/{$rb['slug']}.vcf", [], [], [], []), $h, (string) $rb['slug']);
                $eq('Visitenkarte des nicht freigegebenen Kontakts: 404', 'gefunden', '404');
            } catch (\Throwable $x) {
                $eq('Visitenkarte des nicht freigegebenen Kontakts: 404', $x instanceof \Core\Http\HttpException ? '404' : get_class($x), '404');
            }
            app()->request = $prevReq;
            $rss = Feed::xml($t);
            $eq('RSS: nur freigegebener Kontakt', [str_contains($rss, 'Erika'), $no($rss)], [true, true]);
            $docs = array_filter(\Core\Search\Documents::build(\Core\Lang::default()), fn($d) => ($d['table'] ?? '') === $h);
            if (in_array($h, array_column(\Core\Search\Documents::tables(), 'handle'), true)) {
                $eq('Suchindex: nur freigegebener Kontakt, ohne Mobilnummer', [count($docs), $no((string) json_encode($docs, JSON_UNESCAPED_UNICODE))], [1, true]);
            }
            if (class_exists(\Klxm\Dav\CardBackend::class)) {
                $card = \Klxm\Dav\CardBackend::render($t, $ra, null);
                $eq('CardDAV: Redaktion sieht Mobilnummer (CELL), keine Vita, Beschriftung per X-ABLabel', [str_contains($card, '5550188'), str_contains($card, self::VITA), str_contains($card, 'TEL;TYPE=CELL'), (bool) preg_match('~item\d+\.X-ABLABEL:Geschäftsstelle~', $card)], [true, false, true, true]);
                [$in] = \Klxm\Dav\CardBackend::parse($t, $ra, str_replace(self::WORK, '02841 5550199', $card));
                $pub = array_column((array) ($in['telefon'] ?? []), 'public', 'value');
                $eq('CardDAV: geänderte Nummer wird intern, unveränderte behält die Freigabe', [$pub['02841 5550199'] ?? null, $pub[self::MOBILE] ?? null], [false, false]);
                $eq('CardDAV: Vita und Freigaben schreibt die App nie', [array_key_exists('vita', $in), array_key_exists(Exposure::STATE, $in), array_key_exists('oeffentlich', $in)], [false, false, false]);
            }

            // ---------------------------------------------------------- Termine: ICS/CalDAV ohne „Nur Website“ und ohne nicht freigegebene Angaben
            if (\Core\Features::on('calendar')) {
                $ch = 'selftest_kt_' . bin2hex(random_bytes(3));
                [$cdef, $cerr] = Tables::validate(['name' => 'Selbsttest Termine', 'handle' => $ch, 'fields' => [
                    ['label' => 'Titel', 'type' => 'text', 'required' => 1], ['label' => 'Beginn', 'type' => 'datetime'],
                    ['label' => 'Ort', 'type' => 'text', 'exposure' => ['mode' => 'editor', 'web' => 1, 'vcard' => 1, 'ics' => 0]],
                    ['label' => 'Hinweis', 'name' => 'hinweis', 'type' => 'textarea', 'exposure' => ['mode' => 'always', 'web' => 1, 'vcard' => 0, 'ics' => 0]],
                ], 'settings' => ['route' => str_replace('_', '-', $ch), 'workflow' => 0, 'calendar' => ['enabled' => true, 'start' => 'beginn', 'location' => 'ort', 'description' => 'hinweis', 'feed' => true]]]);
                $eq('Termin-Tabelle gültig', $cerr, []);
                Tables::create($cdef);
                $made[] = $ch;
                $ct = $load($ch);
                [$ta] = Entries::save($ct, null, ['titel' => 'Treffen', 'beginn' => date('Y-m-d', strtotime('+5 days')) . ' 18:00', 'ort' => 'Saal-Musterstadt', 'hinweis' => 'Nur-Website-Hinweis']);
                $te = Entries::find($ct, (int) $ta) ?? [];
                $ics = ICal::calendar($ct, [$te], ['publish' => true]);
                $eq('ICS: Termin ohne „Nur Website“ und ohne Ort (Standard ics aus)', [substr_count($ics, 'BEGIN:VEVENT'), str_contains($ics, 'Nur-Website-Hinweis'), str_contains($ics, 'Saal-Musterstadt')], [1, false, false]);
                $eq('Website: Ort und Hinweis erscheinen', [Exposure::publicEntry($ct, $te)['ort'] ?? null, Exposure::publicEntry($ct, $te)['hinweis'] ?? null], ['Saal-Musterstadt', 'Nur-Website-Hinweis']);
                if (class_exists(\Klxm\Dav\CalendarBackend::class)) {
                    $cal = \Klxm\Dav\CalendarBackend::render($ct, $te, null);
                    $eq('CalDAV: Redaktion sieht den Ort, nie den „Nur Website“-Hinweis', [str_contains($cal, 'Saal-Musterstadt'), str_contains($cal, 'Nur-Website-Hinweis')], [true, false]);
                }
            }

            // ---------------------------------------------------------- data:convert (Probelauf, Umwandlung, Zurückdrehen)
            $vh = 'selftest_kc_' . bin2hex(random_bytes(3));
            [$vdef] = Tables::validate(['name' => 'Selbsttest Vorstand', 'handle' => $vh, 'fields' => [
                ['label' => 'Name', 'type' => 'text', 'required' => 1], ['label' => 'Funktion', 'type' => 'text'], ['label' => 'Telefon', 'type' => 'tel'],
                ['label' => 'Mobil', 'type' => 'tel', 'private' => 1], ['label' => 'Ort', 'type' => 'text'], ['label' => 'Rolle', 'type' => 'multiselect', 'options' => "vorstand=Vorstand\npresse=Presse"],
            ], 'settings' => ['route' => '', 'workflow' => 1, 'schema_type' => 'Person']]);
            Tables::create($vdef);
            $made[] = $vh;
            $vt = $load($vh);
            [$v1] = Entries::save($vt, null, ['name' => 'Dr. Erika Musterfrau', 'funktion' => 'Vorsitz', 'telefon' => self::WORK, 'mobil' => self::MOBILE, 'ort' => 'Musterstadt', 'rolle' => ['vorstand'], 'status' => 'published']);
            $plan = Convert::plan($vt, 'contacts');
            $eq('Probelauf: Zuordnung', [$plan['ok'], $plan['fields']['funktion']['to'] ?? null, $plan['fields']['rolle']['to'] ?? null, $plan['fields']['mobil']['public'] ?? null, $plan['fields']['telefon']['public'] ?? null],
                [true, 'position', 'listen', false, true]);
            $eq('Probelauf ändert nichts', [Kinds::of($load($vh)), Tables::field($load($vh), 'funktion') !== null], ['content', true]);
            $eq('Probelauf: Bericht', str_contains(implode("\n", Convert::report($plan, $vt)), 'Feldzuordnung'), true);
            Convert::apply($vt, $plan);
            $ct2 = $load($vh);
            $e1 = Entries::find($ct2, (int) $v1) ?? [];
            $eq('Umwandlung: Art, Name aufgeteilt, Funktion umbenannt, Listen', [Kinds::of($ct2), $e1['praefix'] ?? null, $e1['vorname'] ?? null, $e1['nachname'] ?? null, $e1['position'] ?? null, $e1['listen'] ?? null],
                ['contacts', 'Dr.', 'Erika', 'Musterfrau', 'Vorsitz', ['vorstand']]);
            $eq('Umwandlung: Telefon öffentlich, Mobil intern, Ort als „nur Ort“', [array_column(Combo::items($e1['telefon'] ?? []), 'public', 'label'), Combo::items($e1['adresse'] ?? [])[0]['public'] ?? null, $e1['oeffentlich'] ?? null],
                [['work' => true, 'mobile' => false], '1', true]);
            $eq('Umwandlung: Altfelder ausgeblendet und nie öffentlich', [Exposure::isPrivate(Tables::field($ct2, 'mobil')), !empty(Tables::field($ct2, 'telefon_alt')['legacy'])], [true, true]);
            Convert::revert($ct2);
            $rv = $load($vh);
            $r1 = Entries::find($rv, (int) $v1) ?? [];
            $eq('Zurückdrehen: Art, Felder und Werte wie vorher', [Kinds::of($rv), $r1['funktion'] ?? null, $r1['telefon'] ?? null, $r1['mobil'] ?? null, $r1['rolle'] ?? null],
                ['content', 'Vorsitz', self::WORK, self::MOBILE, ['vorstand']]);
        } catch (\Throwable $x) {
            $fails[] = 'Adressbuch – Ausnahme: ' . $x->getMessage() . ' (' . basename($x->getFile()) . ':' . $x->getLine() . ')';
        } finally {
            app()->request = $prevReq;
            app()->entry = $prevEntry;
            foreach ($made as $mh) {
                if ($x = Tables::find($mh)) Tables::delete($x);
                foreach (glob(site()->storage('convert') . '/' . $mh . '*') ?: [] as $file) @unlink($file);
            }
            Tables::flush();
        }
        return ['ok' => $ok, 'fails' => $fails];
    }

    /** Ohne Datenbank: Mehrfachfelder und Freigabe-Regeln */
    private static function units(\Closure $eq): void
    {
        $items = [['label' => 'office', 'value' => '0201 1', 'public' => true], ['label' => 'mobile', 'value' => '0171 2'], ['label' => 'custom', 'label_custom' => 'Hütte', 'value' => '0201 3', 'public' => '1']];
        $eq('Mehrfachfeld: nur freigegebene Angaben', array_column(Combo::publicItems('phones', $items), 'value'), ['0201 1', '0201 3']);
        $eq('Mehrfachfeld: Beschriftungen', [Combo::labelText('phones', $items[0]), Combo::labelText('phones', $items[2])], [__('Geschäftsstelle'), 'Hütte']);
        $adr = Combo::publicItems('addresses', [['street' => 'Musterweg 1', 'zip' => '12345', 'city' => 'Musterstadt', 'public' => 'city']]);
        $eq('Adresse „nur Ort“: ohne Straße und PLZ', [isset($adr[0]['street']), $adr[0]['city'] ?? null], [false, 'Musterstadt']);
        $h = Combo::html(['type' => 'phones'], Combo::publicItems('phones', $items));
        $eq('Mehrfachfeld: Ausgabe mit tel-Link, ohne interne Nummer', [str_contains($h, 'href="tel:'), str_contains($h, '0171')], [true, false]);
        $eq('Mehrfachfeld: Kontakt-Knöpfe', count(Combo::actions('phones', Combo::publicItems('phones', $items))), 2);
        $eq('Regel: ohne Angabe = immer, alle Ziele', Exposure::rule(['name' => 'x']), ['mode' => 'always', 'web' => true, 'vcard' => true, 'ics' => true]);
        $eq('Regel: ältere Angaben', [Exposure::rule(['private' => true])['mode'], Exposure::rule(['website_only' => true])['vcard'], Exposure::rule(['legacy' => true])['mode']], ['never', false, 'never']);
        $eq('Designer: Standard wird nicht gespeichert, „nie“ als private', [Exposure::fieldDef(['exposure' => ['mode' => 'always', 'web' => '1', 'vcard' => '1', 'ics' => '1']]), Exposure::fieldDef(['exposure' => ['mode' => 'never']])],
            [[], ['private' => true]]);
        $ed = ['name' => 'mobil', 'type' => 'tel', 'exposure' => ['mode' => 'editor', 'web' => 1, 'vcard' => 0, 'ics' => 0]];
        $eq('Redaktion entscheidet: Standard und Entscheidung je Eintrag', [Exposure::allowed($ed, []), Exposure::allowed($ed, [], 'vcard'), Exposure::allowed($ed, [Exposure::STATE => ['mobil' => ['web' => false]]])],
            [true, false, false]);
        $eq('Hinweis entfällt bei „Redaktion entscheidet“', [Exposure::looksPrivate(['name' => 'mobil', 'type' => 'tel']), Exposure::looksPrivate($ed)], [true, false]);
        $eq('Apps der Redaktion: „Nur Website“ fehlt, „nie“ bleibt', array_column(Exposure::appFields(['fields' => [['name' => 'a', 'website_only' => true], ['name' => 'b', 'private' => true], ['name' => 'c']]]), 'name'), ['b', 'c']);
        $eq('Zuordnung --map', Convert::parseMap('handy:telefon[mobile-],fax:-'), ['handy' => ['to' => 'telefon', 'label' => 'mobile', 'public' => false], 'fax' => ['to' => '-', 'label' => null, 'public' => null]]);
        $eq('Namensaufteilung', [Convert::splitName('Dr. Erika von Musterfrau'), Convert::splitName('Musterfrau, Erika')], [['Dr.', 'Erika', 'von Musterfrau'], ['', 'Erika', 'Musterfrau']]);
    }
}
