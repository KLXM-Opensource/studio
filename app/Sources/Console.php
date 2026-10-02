<?php
declare(strict_types=1);

namespace Core\Sources;

use Core\Sites;

/**
 * Kommandozeile der externen Quellen (bin/console):
 *   sources:sync [--all] [--source=ID] [--force]   fällige Quellen abgleichen (--source: nur diese, sofort; --force: alle aktiven, Zwischenspeicher umgehen)
 *   sources:list [--all]                            Quellen mit Stand
 *   sources:selftest                                Selbsttest der Zuordnung (Vorschlag, Beispielwerte, Umwandlungen, Typen für neue Tabellen)
 * Cron (empfohlen): *\/15 * * * * php bin/console sources:sync --all
 */
final class Console
{
    public static function run(string $cmd, array $args, string $script): int
    {
        if (in_array('--all', $args, true)) {
            $rest = array_values(array_filter($args, fn($a) => $a !== '--all'));
            $fail = 0;
            foreach (array_keys(Sites::all()) as $k) {
                echo "── $k\n";
                passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . $cmd . ' ' . implode(' ', array_map('escapeshellarg', $rest)) . ' --site=' . escapeshellarg($k), $code);
                $fail = $fail ?: $code;
            }
            return $fail;
        }
        $opt = function (string $name) use ($args): ?string {
            foreach ($args as $a) {
                if ($a === '--' . $name) return '';
                if (str_starts_with($a, '--' . $name . '=')) return substr($a, strlen($name) + 3);
            }
            return null;
        };
        if ($cmd === 'sources:selftest') return self::selftest();
        if (!Sources::enabled()) {
            echo "Externe Quellen sind auf dieser Website ausgeschaltet (" . site()->key . ", Funktion „sources“).\n";
            return 0;
        }
        return match ($cmd) {
            'sources:list' => self::list(),
            default => self::sync($opt('source'), $opt('force') !== null),
        };
    }

    private static function list(): int
    {
        $all = Sources::all();
        if (!$all) { echo "Keine Quellen (" . site()->key . ").\n"; return 0; }
        foreach ($all as $s) {
            printf("#%-4d %-32s %-9s %-7s %4d Einträge  %s\n", $s['id'], mb_strimwidth($s['name'], 0, 32, '…'), $s['format'], $s['active'] ? $s['options']['schedule'] : 'pausiert',
                $s['items'], $s['last_error'] ? '✗ ' . mb_strimwidth((string) $s['last_error'], 0, 70, '…') : ($s['last_ok_at'] ? '✓ ' . $s['last_ok_at'] : '– noch nie'));
        }
        return 0;
    }

    private static function sync(?string $only, bool $force): int
    {
        $print = function (array $src, array $s): void {
            echo ($s['ok'] ? ($s['failed'] ? '  ! ' : '  ✓ ') : '  ✗ ') . '#' . $src['id'] . ' ' . $src['name'] . ': ' . $s['message'] . "\n";
            foreach (array_slice($s['details'], 0, 5) as $d) echo "      $d\n";
        };
        if ($only !== null && $only !== '') {
            $src = Sources::find((int) $only);
            if (!$src) { fwrite(STDERR, "Quelle #$only gibt es auf " . site()->key . " nicht.\n"); return 1; }
            $s = Sync::run($src, 'cron', ['force' => true]);
            $print($src, $s);
            return $s['ok'] ? 0 : 1;
        }
        if ($force) {
            $bad = 0;
            foreach (Sources::all() as $src) {
                if (!$src['active']) continue;
                $s = Sync::run($src, 'cron', ['force' => true]);
                $print($src, $s);
                $bad += $s['ok'] ? 0 : 1;
            }
            return $bad ? 1 : 0;
        }
        $done = Sync::runDue('cron', 0.0, $print);
        if (!$done) echo "Keine fälligen Quellen (" . site()->key . ").\n";
        return count(array_filter($done, fn($s) => !$s['ok'])) ? 1 : 0;
    }

    /** Selbsttest ohne Netz und ohne Schreiben: Feeds lesen, Felder der Quelle, Vorschlag, Beispiel je Feld, Probeabruf, Umwandlungen, Typen */
    private static function selftest(): int
    {
        $fail = 0;
        $ok = function (string $name, bool $cond, string $extra = '') use (&$fail): void {
            echo ($cond ? '  ✓ ' : '  ✗ ') . $name . ($cond || $extra === '' ? '' : ' – ' . $extra) . "\n";
            if (!$cond) $fail++;
        };
        $tz = new \DateTimeZone((string) app()->config->get('timezone', 'Europe/Berlin'));
        $long = str_repeat('Ein ziemlich langer Satz für den Teaser. ', 12);
        $rss = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:media="http://search.yahoo.com/mrss/">'
            . '<channel><title>Testmeldungen</title><link>https://example.org/</link>'
            . '<item><title>Erste &amp; beste Meldung</title><link>https://example.org/a</link><guid isPermaLink="false">id-1</guid>'
            . '<pubDate>Thu, 01 Oct 2026 12:05:00 +0000</pubDate><dc:creator>Erika Muster</dc:creator><category>Politik</category><category>Sport</category>'
            . '<description>' . htmlspecialchars('<p>' . $long . '</p><p>Zweiter Absatz.</p>') . '</description>'
            . '<content:encoded><![CDATA[<p>Volltext <b>fett</b></p>]]></content:encoded><enclosure url="https://example.org/bild.jpg" type="image/jpeg" length="1234"/></item>'
            . '<item><title>Zweite Meldung</title><link>https://example.org/b</link><guid>id-2</guid><pubDate>Fri, 02 Oct 2026 08:00:00 +0200</pubDate><category>Kultur</category>'
            . '<description>Kurz.</description></item></channel></rss>';
        $atom = '<?xml version="1.0" encoding="utf-8"?><feed xmlns="http://www.w3.org/2005/Atom"><title>Atom-Test</title>'
            . '<entry><title>Atom eins</title><id>urn:a:1</id><link rel="alternate" href="https://example.org/x"/><published>2026-10-01T10:00:00Z</published>'
            . '<updated>2026-10-01T11:00:00Z</updated><author><name>Max</name></author><summary>Zusammenfassung</summary></entry></feed>';
        $json = json_encode(['data' => ['items' => [
            ['id' => 7, 'name' => 'Produkt A', 'price' => '12.50', 'date' => '2026-10-01', 'url' => 'https://example.org/p/7', 'image' => ['url' => 'https://example.org/p7.png'], 'active' => 'true', 'zip' => '01234'],
            ['id' => 8, 'name' => 'Produkt B', 'price' => '9', 'date' => '2026-10-02', 'url' => 'https://example.org/p/8', 'image' => ['url' => 'https://example.org/p8.webp'], 'active' => 'false', 'zip' => '47441'],
        ]]]);

        echo "Feeds lesen, Felder der Quelle\n";
        $p = Parser::parse($rss, 'rss');
        $items = $p['items'];
        $paths = Parser::paths($items);
        $ok('RSS: 2 Einträge, Titel des Kanals', count($items) === 2 && ($p['meta']['title'] ?? '') === 'Testmeldungen');
        $sf = array_column(Mapper::sourceFields($paths), null, 'path');
        $ok('Felder der Quelle: pubDate mit Bezeichnung und Beispiel', ($sf['pubDate']['label'] ?? '') === __('Datum der Veröffentlichung') && str_contains($sf['pubDate']['sample'] ?? '', '2026'));
        $ok('Felder der Quelle: enclosure@url als Bild-Adresse', ($sf['enclosure@url']['label'] ?? '') === __('Anhang / Bild (Adresse)'));
        $ok('Felder der Quelle: mehrere Kategorien als category[*]', isset($sf['category[*]']) && str_contains($sf['category[*]']['sample'], 'Politik, Sport') && !isset($sf['category[1]']));

        echo "Zuordnung vorschlagen\n";
        $f = fn(string $name, string $label, string $type, bool $req = false) => ['name' => $name, 'label' => $label, 'type' => $type, 'required' => $req, 'options' => []];
        $table = ['handle' => 'test', 'settings' => ['title_field' => 'titel'], 'fields' => [
            $f('titel', 'Titel', 'text', true), $f('veroeffentlichen_ab', 'Veröffentlichen ab', 'datetime', true), $f('teaser', 'Teaser', 'textarea'),
            $f('text', 'Text', 'richtext'), $f('bild', 'Bild', 'media'), $f('link', 'Link', 'url'), $f('autor', 'Autor', 'text'), $f('kategorie', 'Kategorie', 'text'),
            $f('notiz', 'Interne Notiz', 'text')]];
        $sug = Mapper::suggest($table, $paths, 'rss');
        $r = $sug['rows'];
        $ok('Titel ← title', ($r['titel']['path'] ?? '') === 'title');
        $ok('„Veröffentlichen ab“ (Pflicht, Datum & Uhrzeit) ← pubDate', str_starts_with($r['veroeffentlichen_ab']['path'] ?? '', 'pubDate'), json_encode($r['veroeffentlichen_ab'] ?? null));
        $ok('Teaser ← description, „Kürzen 200“', str_starts_with($r['teaser']['path'] ?? '', 'description') && ($r['teaser']['tx'] ?? '') === 'truncate' && ($r['teaser']['opt'] ?? '') === '200', json_encode($r['teaser'] ?? null));
        $ok('Text ← content:encoded | description', str_starts_with($r['text']['path'] ?? '', 'content:encoded'), json_encode($r['text'] ?? null));
        $ok('Bild ← enclosure@url, Alt-Text ← title', str_contains($r['bild']['path'] ?? '', 'enclosure') && ($r['bild']['alt'] ?? '') === 'title');
        $ok('Link ← link', ($r['link']['path'] ?? '') === 'link');
        $ok('Autor ← dc:creator', ($r['autor']['path'] ?? '') === 'dc:creator');
        $ok('Kategorie ← category', str_starts_with($r['kategorie']['path'] ?? '', 'category'));
        $ok('Unpassendes Feld bleibt leer', !isset($r['notiz']));
        $ok('Eindeutige ID ← guid | link', $sug['id_path'] === 'guid | link', $sug['id_path']);
        $keep = Mapper::suggest($table, $paths, 'rss', ['id_path' => 'x', 'slug_path' => '', 'rows' => ['titel' => ['path' => 'dc:creator']]]);
        $ok('Belegte Zeilen bleiben unverändert', $keep['rows']['titel']['path'] === 'dc:creator' && $keep['id_path'] === 'x' && !in_array('Titel', $keep['filled'], true));
        $noDate = Mapper::suggest($table, array_diff_key($paths, ['pubDate' => 1]), 'rss');
        $ok('Ohne Datum in der Quelle: Pflicht-Datum → „jetzt (Zeitpunkt des Abrufs)“', ($noDate['rows']['veroeffentlichen_ab']['tx'] ?? '') === 'now');
        $ok('… dann fehlt kein Pflichtfeld', Mapper::missingRequired($table, $noDate) === []);
        $ap = Parser::paths(Parser::parse($atom, 'atom')['items']);
        $as = Mapper::suggest($table, $ap, 'atom')['rows'];
        $ok('Atom: Datum ← published | updated', str_starts_with($as['veroeffentlichen_ab']['path'] ?? '', 'published | updated'), json_encode($as['veroeffentlichen_ab'] ?? null));
        $ok('Atom: Link ← link[@rel=alternate]@href | link@href', ($as['link']['path'] ?? '') === 'link[@rel=alternate]@href | link@href', json_encode($as['link'] ?? null));
        $ok('Atom: Autor ← author.name', ($as['autor']['path'] ?? '') === 'author.name');
        $jp = Parser::paths(Parser::parse((string) $json, 'json')['items']);
        $js = Mapper::suggest($table, $jp, 'json')['rows'];
        $ok('JSON: Datum ← date, Bild ← image.url, Link ← url', ($js['veroeffentlichen_ab']['path'] ?? '') === 'date' && ($js['bild']['path'] ?? '') === 'image.url' && ($js['link']['path'] ?? '') === 'url',
            json_encode([$js['veroeffentlichen_ab'] ?? null, $js['bild'] ?? null, $js['link'] ?? null]));

        echo "Beispiel je Feld und Probeabruf\n";
        $mapping = ['id_path' => $sug['id_path'], 'slug_path' => '', 'rows' => $sug['rows']];
        $ex = Mapper::explain($table, $mapping, $items[0]);
        $want = (new \DateTimeImmutable('2026-10-01 12:05:00', new \DateTimeZone('UTC')))->setTimezone($tz)->format('d.m.Y, H:i');
        $ok('Datum lesbar: „' . $want . '“', ($ex['veroeffentlichen_ab']['state'] ?? '') === 'ok' && ($ex['veroeffentlichen_ab']['text'] ?? '') === $want, json_encode($ex['veroeffentlichen_ab'] ?? null));
        $ok('Titel: Entities aufgelöst', ($ex['titel']['text'] ?? '') === 'Erste & beste Meldung');
        $ok('Nicht zugeordnet: Hinweis statt Fehler', ($ex['notiz']['state'] ?? '') === 'none');
        $ex2 = Mapper::explain($table, ['rows' => ['veroeffentlichen_ab' => ['path' => 'gibtsnicht']]], $items[0]);
        $ok('Pflichtfeld leer: Fehler', ($ex2['veroeffentlichen_ab']['state'] ?? '') === 'error' && ($ex2['titel']['state'] ?? '') === 'error');
        $ex3 = Mapper::explain($table, ['rows' => ['link' => ['path' => 'title']]], $items[0]);
        $ok('Webadresse ohne https://: Hinweis', ($ex3['link']['state'] ?? '') === 'warn', json_encode($ex3['link'] ?? null));
        $dry = Mapper::preview($table, $mapping, $items, 3);
        $ok('Probeabruf: 2 Einträge, ohne Fehler', count($dry) === 2 && !$dry[0]['errors'] && !$dry[1]['errors']);
        $ok('Probeabruf: Bild als Verweis', !empty($dry[0]['fields']['bild']['media']));
        $bad = Mapper::preview($table, ['rows' => ['titel' => ['path' => 'title']]], $items, 1);
        $ok('Probeabruf: leeres Pflichtfeld hervorgehoben', count($bad[0]['errors']) === 1 && !empty($bad[0]['fields']['veroeffentlichen_ab']['error']));
        $m = Mapper::map($table, ['rows' => ['kategorie' => ['path' => 'category[*]']]], $items[0]);
        $ok('category[*] verbindet alle Werte', ($m['values']['kategorie'] ?? '') === 'Politik, Sport');

        echo "Umwandlungen\n";
        $tr = Mapper::truncate($long, 200);
        $cut = mb_substr($tr, 0, -1);
        $ok('Kürzen: höchstens 200 Zeichen + „…“, an Wortgrenze', mb_strlen($tr) <= 201 && str_ends_with($tr, '…') && preg_match('~[\s.]~u', mb_substr($long, mb_strlen($cut), 1)) === 1, $tr);
        $ok('Kürzen: kurzer Text bleibt', Mapper::truncate('Kurz.', 200) === 'Kurz.');
        $mt = Mapper::map($table, ['rows' => ['teaser' => ['path' => 'description', 'tx' => 'truncate', 'opt' => '80']]], $items[0]);
        $ok('Kürzen 80 aus HTML-Beschreibung: ohne HTML', mb_strlen($mt['values']['teaser'] ?? '') <= 81 && !str_contains($mt['values']['teaser'] ?? '<', '<'), $mt['values']['teaser'] ?? '');
        $mp = Mapper::map($table, ['rows' => ['teaser' => ['path' => 'description', 'tx' => 'para']]], $items[0]);
        $ok('Nur erster Absatz', ($mp['values']['teaser'] ?? '') === trim($long), mb_strimwidth($mp['values']['teaser'] ?? '', 0, 60));
        $ok('Erster Absatz aus Text mit Leerzeile', Mapper::firstParagraph("Eins\nnoch eins\n\nZwei") === 'Eins noch eins');
        $mr = Mapper::map($table, ['rows' => ['text' => ['path' => 'description', 'tx' => 'truncate', 'opt' => '50']]], $items[0]);
        $ok('Kürzen in formatierten Text: ein sicherer Absatz', preg_match('~^<p>[^<]+…</p>$~u', (string) ($mr['values']['text'] ?? '')) === 1, (string) ($mr['values']['text'] ?? ''));
        $n1 = Mapper::map($table, ['rows' => ['veroeffentlichen_ab' => ['tx' => 'now']]], $items[1]);
        $ok('„jetzt“: Zeitpunkt des Abrufs, gekennzeichnet', preg_match('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$~', (string) ($n1['values']['veroeffentlichen_ab'] ?? '')) === 1 && isset($n1['now']['veroeffentlichen_ab']));
        $n2 = $n1;
        $n2['values']['veroeffentlichen_ab'] = '1999-01-01 00:00';
        $ok('„jetzt“ zählt nicht zur Prüfsumme (kein Dauer-Update)', Mapper::hash($n1) === Mapper::hash($n2));
        $n3 = Mapper::map($table, ['rows' => ['veroeffentlichen_ab' => ['path' => 'pubDate', 'tx' => 'now']]], $items[1]);
        $ok('„jetzt“ mit Pfad: Datum der Quelle hat Vorrang', !isset($n3['now']['veroeffentlichen_ab']) && str_starts_with((string) ($n3['values']['veroeffentlichen_ab'] ?? ''), '2026-10-02'));
        $ok('Unix-Zeit und ISO 8601 werden gelesen', Mapper::date('1790000000', '', 'date') !== null && Mapper::date('2026-10-01T10:00:00Z', '', 'date') === '2026-10-01');

        echo "Feldtypen für eine neue Tabelle\n";
        $cases = [
            ['pubDate', ['Thu, 01 Oct 2026 12:05:00 +0000'], 'datetime'], ['published', ['2026-10-01T10:00:00Z'], 'datetime'], ['date', ['2026-10-01'], 'date'],
            ['datum', ['01.10.2026'], 'date'], ['enclosure@url', ['https://example.org/a.jpg'], 'media'], ['media:thumbnail@url', ['https://cdn.example.org/thumb?id=3'], 'media'],
            ['enclosure@url', ['https://example.org/folge.mp3'], 'url'], ['link', ['https://example.org/a'], 'url'], ['price', ['12.50', '9'], 'number'],
            ['zip', ['01234'], 'text'], ['description', ['<p>Hallo <b>Welt</b></p>'], 'richtext'], ['summary', [str_repeat('lang ', 50)], 'textarea'],
            ['title', ['Kurzer Titel'], 'text'], ['email', ['info@example.org'], 'email'], ['active', ['true', 'false'], 'bool'],
            ['category[*]', ['A', 'B'], 'text'], ['updated_at', ['1790000000'], 'datetime'], ['id', ['12345'], 'text'],
        ];
        foreach ($cases as [$path, $vals, $want]) {
            $got = Mapper::inferType($path, $vals);
            $ok(sprintf('%-22s → %s', $path . ' ' . mb_strimwidth((string) $vals[0], 0, 18, '…'), $want), $got === $want, 'erkannt: ' . $got);
        }
        $prop = Mapper::proposeTable($items, 'rss', 'Testmeldungen');
        $byLabel = array_column($prop['fields'], null, 'label');
        $ok('Vorschlag RSS: Titel, Datum, Autor, Teaser, Text, Bild, Link, Kategorie angehakt',
            count(array_filter(['Titel', 'Datum', 'Autor', 'Teaser', 'Text', 'Bild', 'Link', 'Kategorie'], fn($l) => !empty($byLabel[$l]['on']))) === 8, implode(', ', array_keys($byLabel)));
        $ok('Vorschlag RSS: Typen datetime / media / url / richtext', ($byLabel['Datum']['type'] ?? '') === 'datetime' && ($byLabel['Bild']['type'] ?? '') === 'media'
            && ($byLabel['Link']['type'] ?? '') === 'url' && ($byLabel['Text']['type'] ?? '') === 'richtext');
        $ok('Vorschlag RSS: Kategorie mit allen Werten', ($byLabel['Kategorie']['path'] ?? '') === 'category[*]', $byLabel['Kategorie']['path'] ?? '');
        $ok('Vorschlag RSS: Name aus dem Feed, freier Kurzname', $prop['name'] === 'Testmeldungen' && preg_match('~^testmeldungen(_\d+)?$~', $prop['handle']) === 1);
        $ok('Vorschlag RSS: weitere Felder abgewählt angeboten (guid nicht doppelt)', !array_filter($prop['fields'], fn($x) => !$x['on'] && in_array($x['path'], ['title', 'guid'], true)));
        $jprop = Mapper::proposeTable(Parser::parse((string) $json, 'json')['items'], 'json', 'Produkte');
        $jt = array_column($jprop['fields'], 'type', 'path');
        $ok('Vorschlag JSON: price → Zahl, date → Datum, image.url → Bild, url → Webadresse', ($jt['price'] ?? '') === 'number' && ($jt['date'] ?? '') === 'date'
            && ($jt['image.url'] ?? '') === 'media' && ($jt['url'] ?? '') === 'url', json_encode($jt));

        echo $fail ? "\n✗ $fail Prüfungen fehlgeschlagen\n" : "\n✓ Alle Prüfungen bestanden\n";
        return $fail ? 1 : 0;
    }
}
