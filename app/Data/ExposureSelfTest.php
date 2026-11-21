<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Data;

use Core\Http\Request;
use Core\Pages;

/**
 * Selbsttest „private Nummer nirgends“ (Core\Data\Exposure) – Teil von `php bin/console data:selftest`. Legt eine vorübergehende
 * Personen-Tabelle (Schema-Typ „Person“, Detailseite) mit einem privaten Mobilfeld und einer privaten Notiz an und greift jeden
 * öffentlichen Weg ab: Block „Personen“, Datenliste (auch Besucher-Suche), Detailseite mit Vorlage (Datensatz-Felder ohne Auswahl,
 * Porträt, Platzhalter {{mobil}}), JSON-LD, Visitenkarte (auch die Route), Suchindex, RSS-Feed, iCal. Die private Nummer darf
 * nirgends auftauchen; die öffentliche Nummer erscheint (Gegenprobe). Dazu: Option im Designer (Rundlauf, Eingang ohne Option),
 * Suche (Gewichtung „aus“, Namensregel), Verwaltung/API sehen den Wert, Health-Hinweis. Alles wird danach wieder gelöscht.
 */
final class ExposureSelfTest
{
    private const SECRET = '5550199';                    // Teil der privaten Mobilnummer
    private const NOTE = 'Vertraulich-Notiz-Musterstadt';   // private Notiz
    private const PUBLIC = '5550100';                    // Teil der öffentlichen Dienstnummer

    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails, 'Nie öffentlich – ');
        $h = 'selftest_priv_' . bin2hex(random_bytes(3));
        $prevEntry = app()->entry;
        $prevReq = app()->request;
        $clean = fn(string $s) => !str_contains($s, self::SECRET) && !str_contains(str_replace([' ', '-', '/'], '', $s), self::SECRET) && !str_contains($s, self::NOTE);
        try {
            [$def, $errors] = Tables::validate(['name' => 'Selbsttest Personen', 'singular' => 'Person', 'handle' => $h, 'fields' => [
                ['label' => 'Name', 'type' => 'text', 'required' => 1],
                ['label' => 'Funktion', 'type' => 'text'],
                ['label' => 'Telefon', 'type' => 'tel'],
                ['label' => 'Mobil', 'name' => 'mobil', 'type' => 'tel', 'private' => 1],
                ['label' => 'Handy (alt)', 'name' => 'handy_alt', 'type' => 'tel'],
                ['label' => 'Notiz', 'name' => 'notiz', 'type' => 'textarea', 'private' => 1, 'searchable' => 1],
                ['label' => 'Beginn', 'name' => 'beginn', 'type' => 'datetime'],
            ], 'settings' => ['route' => str_replace('_', '-', $h), 'schema_type' => 'Person', 'description_field' => 'notiz', 'workflow' => 0,
                'search' => ['fields' => ['mobil' => 'normal', 'telefon' => 'normal']]]]);
            $eq('Tabelle angelegt', $errors, []);
            $eq('Option gespeichert', [Tables::field($def, 'mobil')['private'] ?? null, isset(Tables::field($def, 'telefon')['private'])], [true, false]);
            $inbox = Tables::validate(['name' => 'X', 'handle' => $h . 'i', 'settings' => ['kind' => 'inbox'], 'fields' => [['label' => 'Name', 'type' => 'text', 'private' => 1]]])[0];
            $eq('Eingang ohne Option', isset($inbox['fields'][0]['private']), false);
            Tables::create($def);
            Tables::flush();
            $t = Tables::find($h) ?? throw new \RuntimeException('Tabelle fehlt');
            [$def2, $err2] = Tables::validate(Tables::toInput($t), $t);
            $eq('Rundlauf validate(toInput) behält die Option', [$err2, Tables::field($def2, 'mobil')['private'] ?? null], [[], true]);

            // Vorlage der Detailseite: Datensatz-Felder ohne Auswahl (alle Felder), Porträt, Kontaktkarte, Platzhalter {{mobil}}
            $bid = fn() => bin2hex(random_bytes(5));
            $pid = Pages::create(['slug' => '_vorlage-' . $h, 'title' => 'Selbsttest – Detailseite', 'type' => 'template', 'template_for' => $h,
                'status' => 'published', 'noindex' => 1], Pages::sanitizeBlocks([
                ['id' => $bid(), 'type' => 'data_fields', 'data' => ['table' => $h, 'layout' => 'prose', 'fields' => []]],
                ['id' => $bid(), 'type' => 'data_fields', 'data' => ['table' => $h, 'layout' => 'portrait', 'fields' => []]],
                ['id' => $bid(), 'type' => 'data_fields', 'data' => ['table' => $h, 'layout' => 'contact', 'fields' => ["$h.telefon", "$h.mobil"]]],
                ['id' => $bid(), 'type' => 'text', 'data' => ['text' => '<p>Mobil: {{mobil}} Notiz: {{notiz}}</p>']],
            ]));
            Tables::setDetailPage($t, $pid);
            $t = Tables::find((string) $t['handle']) ?? throw new \RuntimeException('Tabelle fehlt');   // neu gelesen: mit Detailseite

            // A: Dienst- und Mobilnummer · B: nur Mobilnummer (der Fall, in dem früher das Mobilfeld nachrückte)
            [$a, $ea] = Entries::save($t, null, ['name' => 'Erika Musterfrau', 'funktion' => 'Vorsitz', 'telefon' => '02841 ' . self::PUBLIC,
                'mobil' => '0171 ' . self::SECRET, 'notiz' => self::NOTE, 'beginn' => date('Y-m-d', strtotime('+3 days')) . ' 10:00', 'status' => 'published']);
            [$b, $eb] = Entries::save($t, null, ['name' => 'Max Mustermann', 'funktion' => 'Kasse', 'mobil' => '0172 ' . self::SECRET,
                'notiz' => self::NOTE, 'beginn' => date('Y-m-d', strtotime('+4 days')) . ' 10:00', 'status' => 'published']);
            $eq('Einträge gespeichert', [$ea, $eb], [[], []]);
            $ra = Entries::find($t, (int) $a) ?? [];
            $rb = Entries::find($t, (int) $b) ?? [];
            $eq('Verwaltung sieht den Wert', [str_contains((string) ($rb['mobil'] ?? ''), self::SECRET), ($rb['notiz'] ?? '') === self::NOTE], [true, true]);
            $eq('publicEntry leert nur private Felder', [Exposure::publicEntry($t, $rb)['mobil'], Exposure::publicEntry($t, $rb)['name']], [null, 'Max Mustermann']);
            $api = (new \Core\Api\CmsService(['user_id' => null, 'name' => 'Selbsttest']))->dataTables();
            $apiF = array_column(array_values(array_filter($api, fn($x) => $x['handle'] === $h))[0]['fields'] ?? [], null, 'name');
            $eq('API-Schema nennt die Option', $apiF['mobil']['private'] ?? null, true);

            // Block „Personen“ (Kontakt + Visitenkarte an)
            $th = app()->theme;
            $render = function (array $blk) use ($th): string {
                $s = Pages::sanitizeBlocks([$blk]);
                return $s ? (string) $th->renderInner($th->makeBlock($s[0])) : '';
            };
            if (!empty($th->block('people')['core'])) {   // Kern-Block (Kits wie praxis haben einen eigenen ohne Tabelle)
                $html = $render(['id' => 'qaprivpeople', 'type' => 'people', 'data' => ['table' => $h, 'source' => 'filter', 'contact' => true, 'vcard' => true, 'link_detail' => true]]);
                $eq('Personen-Block: beide Personen', substr_count($html, 'class="cms-person"'), 2);
                $eq('Personen-Block: Dienstnummer sichtbar (Gegenprobe)', str_contains($html, self::PUBLIC), true);
                $eq('Personen-Block: private Nummer nirgends', $clean($html), true);
                $man = $render(['id' => 'qaprivpeople2', 'type' => 'people', 'data' => ['table' => $h, 'contact' => true, 'items' => [['person' => "entry:$h:$b"]]]]);
                $eq('Personen-Block von Hand: private Nummer nirgends', $clean($man) && str_contains($man, 'Max Mustermann'), true);
            }
            // Datenliste: private Felder auch bei ausdrücklicher Auswahl nicht, Besucher-Suche findet sie nicht
            $list = $render(['id' => 'qaprivlist', 'type' => 'data_list', 'data' => ['table' => $h, 'layout' => 'table', 'fields' => ["$h._title", "$h.telefon", "$h.mobil", "$h.notiz"]]]);
            $eq('Datenliste: Gegenprobe und private Nummer nirgends', [str_contains($list, self::PUBLIC), $clean($list)], [true, true]);
            $eq('Besucher-Suche: kein Treffer über private Felder', count(Entries::query($t, ['q' => self::NOTE, 'q_public' => true])), 0);
            $eq('Suche der Verwaltung findet weiter', count(Entries::query($t, ['q' => self::NOTE])), 2);

            // Strukturierte Daten und Visitenkarte – auch mit ungefiltertem Eintrag
            $ld = (string) json_encode(\Core\StructuredData::graph(Pages::find($pid) ?? [], $t, $rb), JSON_UNESCAPED_UNICODE);
            $eq('JSON-LD: Person ohne private Nummer', [str_contains($ld, '"Person"'), $clean($ld)], [true, true]);
            $lda = (string) json_encode(\Core\StructuredData::graph(Pages::find($pid) ?? [], $t, $ra), JSON_UNESCAPED_UNICODE);
            $eq('JSON-LD: Dienstnummer (Gegenprobe)', str_contains($lda, self::PUBLIC), true);
            $eq('vCard: ohne private Nummer und Notiz', $clean(\Core\VCard::person($t, $rb)) && str_contains(\Core\VCard::person($t, $rb), 'FN:Max Mustermann'), true);
            $eq('vCard: Dienstnummer (Gegenprobe)', str_contains(\Core\VCard::person($t, $ra), self::PUBLIC), true);
            if (!app()->settings->get('sys.maintenance')) {
                $vr = (new \Core\Http\Controllers\VCardController())->person(new Request('GET', "/vcard/$h/{$rb['slug']}.vcf", [], [], [], []), $h, (string) $rb['slug']);
                $eq('Route /vcard/…: ohne private Nummer', [$vr->status, $clean($vr->body)], [200, true]);

                // Detailseite wie für Besucher (Vorlage, Blöcke, Kopf, JSON-LD)
                $path = $t['settings']['route'] . '/' . $rb['slug'];
                $res = (new \Core\Http\Controllers\SiteController())->page(new Request('GET', '/' . $path, ['selbsttest' => '1'], [], [], []), $path);
                $eq('Detailseite: geladen', [$res->status, str_contains($res->body, 'Max Mustermann')], [200, true]);
                $eq('Detailseite: private Nummer und Notiz nirgends (Felder, Porträt, Kontakt, Platzhalter, JSON-LD)', $clean($res->body), true);
                app()->entry = $prevEntry;
                $pa = $t['settings']['route'] . '/' . $ra['slug'];
                $resA = (new \Core\Http\Controllers\SiteController())->page(new Request('GET', '/' . $pa, ['selbsttest' => '1'], [], [], []), $pa);
                $eq('Detailseite: Dienstnummer (Gegenprobe)', [str_contains($resA->body, self::PUBLIC), $clean($resA->body)], [true, true]);
                app()->entry = $prevEntry;
            }

            // Suchindex (auch Grundlage für Besucher-Chat und KI-Antwort)
            $cfg = \Core\Search\TableSearch::config($t);
            $eq('Suche: privates Feld „aus“ trotz gespeicherter Gewichtung', [$cfg['fields']['mobil'], $cfg['fields']['notiz'], $cfg['fields']['telefon']], ['off', 'off', 'normal']);
            $eq('Suche: Namensregel Mobil/Handy/privat', array_map(fn($n) => \Core\Search\TableSearch::defaultWeight($t, ['name' => $n, 'type' => 'text', 'label' => $n]), ['mobilnummer', 'handy', 'privatadresse', 'ort']),
                ['off', 'off', 'off', 'normal']);
            $docs = array_filter(\Core\Search\Documents::build(\Core\Lang::default()), fn($d) => ($d['table'] ?? '') === $h);
            if (\Core\Search\Documents::tables() && in_array($h, array_column(\Core\Search\Documents::tables(), 'handle'), true)) {
                $eq('Suchindex: beide Einträge', count($docs), 2);
                $eq('Suchindex: Dienstnummer, keine private Nummer/Notiz', [str_contains(json_encode($docs, JSON_UNESCAPED_UNICODE) ?: '', self::PUBLIC), $clean(json_encode($docs, JSON_UNESCAPED_UNICODE) ?: '')], [true, true]);
            }

            // Feeds: RSS (Beschreibung = private Notiz) und iCal (Beschreibung/Ort privat)
            $rss = Feed::xml($t);
            $eq('RSS: Einträge, ohne private Notiz', [str_contains($rss, 'Max Mustermann'), $clean($rss)], [true, true]);
            $ct = $t;
            $ct['settings']['calendar'] = ['enabled' => true, 'start' => 'beginn', 'description' => 'notiz', 'location' => 'notiz', 'feed' => true] + Calendar::DEFAULTS;
            if (Calendar::enabled($ct)) {
                $ics = ICal::calendar($ct, [$ra, $rb], ['publish' => true]);
                $eq('iCal: Termine, ohne private Notiz', [substr_count($ics, 'BEGIN:VEVENT'), $clean($ics)], [2, true]);
            }

            // Health: Feld „Handy (alt)“ sieht privat aus und ist nicht markiert – „Mobil“ ist markiert und fehlt im Hinweis
            $hint = implode("\n", array_keys(Exposure::health()));
            $eq('Health: Hinweis nennt nur das unmarkierte Feld', [str_contains($hint, 'Selbsttest Personen'), str_contains($hint, '„Handy (alt)“'), str_contains($hint, '„Mobil“')], [true, true, false]);
            $eq('looksPrivate', [Exposure::looksPrivate(['name' => 'mobil', 'type' => 'tel']), Exposure::looksPrivate(['name' => 'mobil', 'type' => 'tel', 'private' => true]),
                Exposure::looksPrivate(['name' => 'telefon', 'type' => 'tel']), Exposure::looksPrivate(['name' => 'x', 'label' => 'Geburtstag', 'type' => 'date'])], [true, false, false, true]);
        } catch (\Throwable $e) {
            $fails[] = 'Nie öffentlich – Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            app()->entry = $prevEntry;
            app()->request = $prevReq;
            if ($x = Tables::find($h)) Tables::delete($x);
            Tables::flush();
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
