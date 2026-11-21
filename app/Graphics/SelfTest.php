<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Graphics;

use Core\Media;
use Core\Svg;

/**
 * Selbsttest der Anzeige von Grafiken (php bin/console graphics:selftest --site=key) – alles, was die Website ohne den
 * Grafik-Editor braucht (der Editor ist ein eigenes Paket mit eigenem Selbsttest, grafik:selftest):
 *  - Bereinigung: typische Ausgabe eines Grafik-Editors übersteht Core\Svg::clean unverändert (ids, Ebenennamen, Verläufe,
 *    Pfeilspitzen, Schatten, Text bleibt Text) und ist idempotent; Schadcode wird entfernt; Arbeitsfassung ('editor') behält
 *    Editor-Daten nur als Text, die Web-Fassung nie
 *  - Design-Farben (Themed), Bewegung (Motion), Daten und Zustände (Bind): Modell, Ausgabe, Mediathek (Media::pictureOf)
 *  - Ablage-Daten (Store): Arbeitsfassung gilt nur zur passenden Datei, Fotos in Bildern mit Foto und Text gelten als verwendet
 * Datenbank-Änderungen laufen in einer Transaktion und werden zurückgerollt; angelegte Dateien werden gelöscht.
 */
final class SelfTest
{
    /** Typische Ausgabe des Editors (Formen, Verlauf, Pfeil, Schatten, Gruppe mit Ebenennamen, mehrzeiliger Text) */
    public const SAMPLE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 500" width="800" height="500">'
        . '<title>Umsatz 2026</title><desc>Balken und Kreis</desc>'
        . '<defs><linearGradient id="verlauf-1" x1="0" y1="0.5" x2="1" y2="0.5"><stop offset="0" stop-color="#5B73AD"/><stop offset="1" stop-color="#FFFFFF" stop-opacity="0.5"/></linearGradient>'
        . '<marker id="pfeil-1" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" fill="#1F2430"/></marker>'
        . '<filter id="schatten-1" x="-50%" y="-50%" width="200%" height="200%"><feDropShadow dx="4" dy="6" stdDeviation="6" flood-color="#000000" flood-opacity="0.25"/></filter></defs>'
        . '<g id="diagramm" data-name="Diagramm" transform="translate(20 21.5)"><rect id="balken" x="80" y="75" width="280" height="200" rx="8" fill="url(#verlauf-1)" filter="url(#schatten-1)"/>'
        . '<ellipse cx="560" cy="200" rx="120" ry="100" fill="#5B73AD" opacity="0.8" style="mix-blend-mode:multiply"/>'
        . '<line x1="376" y1="175" x2="424" y2="175" stroke="#1F2430" stroke-width="2" marker-end="url(#pfeil-1)"/></g>'
        . '<path data-name="Welle" d="M10 400C60 350 110 450 160 400" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="6 4"/>'
        . '<text x="80" y="460" font-size="32" font-family="Lato" fill="#1F2430"><tspan x="80">Umsatz</tspan><tspan x="80" dy="1.2em">2026</tspan></text></svg>';

    private static int $ok = 0;
    private static array $fails = [];

    /** @return array{ok: int, fails: list<string>} */
    public static function run(): array
    {
        self::$ok = 0;
        self::$fails = [];
        self::sanitize();
        self::editorData();
        $db = app()->db;
        $db->pdo->beginTransaction();
        $files = [];
        try {
            self::store($files);
            self::themed($files);
            self::motion($files);
            self::bind($files);
        } catch (\Throwable $e) {
            self::$fails[] = 'Ausnahme: ' . $e->getMessage();
        } finally {
            $db->pdo->rollBack();
            foreach ($files as $f) if (is_file($f)) @unlink($f);
            app()->settings->forget();
        }
        return ['ok' => self::$ok, 'fails' => self::$fails];
    }

    private static function t(bool $cond, string $label): void
    {
        $cond ? self::$ok++ : self::$fails[] = $label;
    }

    /**
     * Grafik in die Mediathek legen wie ein Grafik-Editor (Media::import mit 'graphic', Arbeitsfassung in graphic_sources) –
     * nur für diesen Test, ohne das Paket. @return array{0: ?array, 1: ?string}
     */
    private static function saveMedia(string $web, array $meta, ?int $mediaId = null, ?string $work = null): array
    {
        $clean = Svg::clean($web, ['keep_ids' => true])['svg'];
        $tmp = tempnam(sys_get_temp_dir(), 'grafik');
        if ($tmp === false || file_put_contents($tmp, $clean) === false) return [null, 'Datei konnte nicht geschrieben werden'];
        try {
            [$m, $err] = Media::import($tmp, Media::fileSlug((string) ($meta['name'] ?? 'grafik')) . '.svg', (string) ($meta['alt'] ?? ''), ['graphic' => true]);
        } finally {
            @unlink($tmp);
        }
        if ($m && $work !== null) {
            $w = Svg::clean($work, ['editor' => true])['svg'];
            app()->db->query('DELETE FROM graphic_sources WHERE ref = ?', ['media:' . (int) $m['id']]);
            if ($w !== $clean) app()->db->insert('graphic_sources', ['ref' => 'media:' . (int) $m['id'], 'svg' => $w, 'hash' => sha1($clean), 'updated_at' => now()]);
        }
        return [$m, $err];
    }

    /** Ablage-Daten ohne Editor: Arbeitsfassung nur zur passenden Datei, Fotos in Kompositionen gelten als verwendet, Verwendet auf ohne Adresse */
    private static function store(array &$files): void
    {
        $db = app()->db;
        $db->insert('graphic_sources', ['ref' => 'media:999999991', 'svg' => '<svg/>', 'hash' => sha1('web'), 'updated_at' => now()]);
        self::t(Store::source('media:999999991', 'web') === '<svg/>' && Store::source('media:999999991', 'anders') === null && Store::source('media:999999992', 'web') === null,
            'Arbeitsfassung gilt nur zur passenden Web-Datei');
        $gid = $db->insert('graphics', ['kind' => Store::PHOTO, 'name' => 'Selbsttest Komposition', 'svg' => '<svg/>', 'created_at' => now(), 'updated_at' => now()]);
        $db->insert('graphic_media', ['graphic_id' => $gid, 'media_id' => 999999993, 'role' => 'src']);
        $u = Store::usagesOf(999999993);
        self::t(isset(Store::usedIds()[999999993]) && count($u) === 1 && $u[0]['url'] === null && $u[0]['graphic'] === $gid && str_contains($u[0]['label'], 'Selbsttest Komposition'),
            'Fotos in Bildern mit Foto und Text: verwendet, „Verwendet auf“ ohne Editor-Adresse');
        self::t(str_starts_with(Store::url('t/x.svg'), '/') && str_ends_with(Store::dir(), '/grafik'), 'Ablage: Ordner und Adresse im Medienordner');
    }

    /**
     * Ausbaustufe 3b – Design-Farben (Core\Graphics\Themed): Klassen überstehen die Bereinigung, Hell/Dunkel werden aus der
     * Palette gesetzt (Attribut und style), eigene Dunkel-Farben gehen vor, „same“ = wie hell, unbekannte Tokens bleiben,
     * Grafiken ohne Klassen bleiben bytegleich; Mediathek: zwei Dateien, <picture> mit <source> nur bei dunklem Farbschema.
     */
    private static function themed(array &$files): void
    {
        $pal = ['accent' => ['light' => '#4338CA', 'dark' => '#A5B4FC'], 'ink' => ['light' => '#0B0D17', 'dark' => '#F3F4F8']];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50" width="100" height="50">'
            . '<rect width="50" height="50" fill="#123456" class="ge-tf-accent"/>'
            . '<rect x="50" width="50" height="50" style="fill:#123456;opacity:.5" class="x ge-tf-accent ge-df-112233"/>'
            . '<path d="M0 0h10" stroke="#000000" class="ge-ds-eeeeee"/>'
            . '<circle r="4" fill="#123456" class="ge-tf-ink ge-df-same"/>'
            . '<circle r="3" fill="#ABCDEF" class="ge-tf-unbekannt"/>'
            . '<linearGradient id="v"><stop offset="0" stop-color="#000000" class="ge-tf-accent"/></linearGradient></svg>';
        $clean = Svg::clean($svg, ['keep_ids' => true])['svg'];
        self::t(Themed::isThemed($clean) && str_contains($clean, 'ge-df-112233'), 'Klassen der Design-Farben überstehen die Bereinigung');
        $l = Themed::render($svg, 'light', $pal);
        $d = Themed::render($svg, 'dark', $pal);
        self::t(str_contains($l, '<rect width="50" height="50" fill="#4338CA" class="ge-tf-accent"/>'), 'hell: Akzent der Website als Füllung');
        self::t(str_contains($d, '<rect width="50" height="50" fill="#A5B4FC" class="ge-tf-accent"/>'), 'dunkel: Dunkel-Wert des Akzents');
        self::t(str_contains($l, 'style="fill:#4338CA;opacity:.5"') && str_contains($d, 'style="fill:#112233;opacity:.5"'), 'style: Akzent hell, eigene Dunkel-Farbe geht vor');
        self::t(str_contains($l, 'stroke="#000000"') && str_contains($d, 'stroke="#EEEEEE"'), 'eigene Dunkel-Farbe ohne Token (Kontur)');
        self::t(str_contains($d, 'fill="#0B0D17" class="ge-tf-ink ge-df-same"'), '„gleich in beiden“: Hell-Wert auch im Dunkeln');
        self::t(str_contains($d, 'fill="#ABCDEF" class="ge-tf-unbekannt"'), 'unbekannter Token behält die gespeicherte Farbe');
        self::t(str_contains($d, 'stop-color="#A5B4FC"'), 'Verlaufsstopp: stop-color folgt dem Design');
        $plain = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="5" height="5" fill="#123456" class="cls-1"/></svg>';
        self::t(Themed::render($plain, 'dark', $pal) === $plain && !Themed::isThemed($plain), 'Grafik ohne Design-Farben bleibt bytegleich');
        // Mediathek: Fassungen je Website
        if (!Themed::palette()) { self::t(true, ''); return; }
        $tok = (string) array_key_first(Themed::palette());
        [$m, $err] = self::saveMedia('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" width="10" height="10"><rect width="10" height="10" fill="#123456" class="ge-tf-' . $tok . '"/></svg>', ['name' => 'selbsttest-themed', 'alt' => 'Selbsttest Design-Farben']);
        self::t($m !== null, 'Grafik mit Design-Farben gespeichert' . ($err ? " ($err)" : ''));
        if (!$m) return;
        $files[] = Media::path($m);
        Themed::forget();
        $v = Themed::variants($m);
        self::t($v !== null && str_ends_with($v['light'], '-l.svg'), 'helle Fassung je Website erzeugt');
        if (!$v) return;
        $lf = Store::dir() . '/t/' . basename($v['light']);
        $files[] = $lf;
        if ($v['dark']) $files[] = Store::dir() . '/t/' . basename($v['dark']);
        self::t(is_file($lf) && str_contains((string) file_get_contents($lf), Themed::palette()[$tok]['light']), 'helle Fassung trägt die Farbe des Designs');
        self::t(($v['dark'] !== null) === Themed::darkActive(), 'dunkle Fassung nur bei dunklem Farbschema der Website');
        $html = Media::pictureOf($m);
        self::t(str_contains($html, basename($v['light'])) && (Themed::darkActive() ? str_contains($html, 'media="(prefers-color-scheme: dark)"') && str_contains($html, 'cms-scheme-img') : !str_contains($html, '<source')), '<picture> mit heller Fassung und <source> für dunkel');
        self::t(str_contains(Themed::inject('<html><body>' . $html . '</body></html>'), 'data-cms-graphic-scheme') === Themed::darkActive() && Themed::inject('<body></body>') === '<body></body>', 'Skript nur auf Seiten mit solchen Grafiken');
    }

    /**
     * Ausbaustufe 4 – Bewegung und Interaktion (Core\Graphics\Motion): Modell übersteht die Bereinigung nur in der Arbeitsfassung,
     * unsichere Werte fallen weg; Ausgabe inline ohne style-Attribut/<style>, ids mit Präfix, Hülle, Links/Tooltips/Hotspots,
     * Endzustand bei reduzierter Bewegung; Mediathek: inline statt <img>, Skript nur bei Bedarf; Grafik ohne Bewegung bleibt <img>.
     */
    private static function motion(array &$files): void
    {
        $b = fn(array $o) => base64_encode((string) json_encode($o));
        $work = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 200" width="400" height="200" data-ge-motion="d=2;dl=0.5;it=0;dir=alternate;tr=view">'
            . '<title>Bewegt</title><style>.f{fill:#123456}#r2{stroke:#000}</style>'
            . '<rect id="r1" class="f" width="80" height="80" style="opacity:.9" data-ge-anim="' . $b(['p' => [40, 40], 'k' => ['pos' => [[0, -50, 0, 'out'], [1, 0, 0, [0.2, 0.1, 0.3, 1]]], 'o' => [[0, 0, 'linear'], [0.5, 1, 'linear']], 'f' => [[0, '#ff0000', 'smooth', '#00ff00'], [2, '#0000ff', 'smooth']]]]) . '" data-ge-link="https://example.org/a" data-ge-linklabel="Mehr" data-ge-tip="Tipp" data-ge-tipbox="10 10 60 20"/>'
            . '<rect id="r2" x="100" width="40" height="40" data-ge-link="javascript:alert(1)"/>'
            . '<path id="p1" d="M0 150H300" stroke="#000" data-ge-anim="' . $b(['k' => ['w' => [[0, 0, 'smooth'], [2, 1, 'smooth']]], 'on' => 'h1']) . '"/>'
            . '<circle id="h1" cx="350" cy="50" r="20" data-ge-hot="1" data-ge-tip="Schalter"/>'
            . '<text id="z" x="10" y="190" data-ge-anim="' . $b(['k' => [], 'n' => ['a' => 0, 'b' => 1250, 'd' => 0, 'g' => '.', 'p' => ',', 't0' => 0, 't1' => 1, 'e' => 'out']]) . '">1.250</text>'
            . '<rect id="bad" width="1" height="1" data-ge-anim="!!kein-base64"/></svg>';
        $w = Svg::clean($work, ['editor' => true])['svg'];
        self::t(str_contains($w, 'data-ge-anim="') && str_contains($w, 'data-ge-motion=') && !str_contains($w, '!!kein-base64'), 'Bewegung bleibt in der Arbeitsfassung, nur Base64');
        $model = Motion::model($w);
        self::t($model !== null && $model['scene']['d'] === 2.0 && $model['scene']['it'] === 0 && $model['scene']['dir'] === 'alternate', 'Szene gelesen (Dauer, endlos, hin und zurück)');
        self::t(($model['items']['r2']['link'] ?? '') === '' && !str_contains($w, 'javascript'), 'Link „javascript:“ entfällt schon beim Bereinigen');
        self::t(!isset(Motion::model($work)['items']['r2']), 'Link „javascript:“ auch ohne Bereinigung verworfen (Modell)');
        self::t(($model['items']['p1']['anim']['on'] ?? '') === 'h1', 'Start bei Klick auf Hotspot bleibt');
        self::t(($model['items']['r1']['anim']['k']['pos'][1][3] ?? '') === 'cubic-bezier(0.2,0.1,0.3,1)', 'eigene Kurve als cubic-bezier');
        $web = (string) preg_replace('~ data-(ge|name)[\w-]*="[^"]*"~', '', str_replace('<svg ', '<svg class="ge-anim" ', $work));
        $web = Svg::clean($web, ['keep_ids' => true])['svg'];
        self::t(Motion::isAnimated($web) && !Motion::isAnimated(self::SAMPLE), 'Kennzeichen ge-anim an der Web-Fassung');
        $o = Motion::build($web, null, (array) $model, 'gmtest', ['w' => 400, 'h' => 200]);
        $svg = $o['svg'];
        self::t(!preg_match('~\sstyle="~', $svg) && !str_contains($svg, '<style'), 'inline: kein style-Attribut, kein <style> (CSP)');
        self::t(str_contains($o['css'], '.gmtest .gmtest-f{fill:#123456}') && str_contains($o['css'], '.gmtest .gmtest-i-r2{stroke:#000}') && str_contains($o['css'], '{opacity:.9}'), '<style> und style-Attribute als Regeln in der CSS-Datei');
        self::t(str_contains($svg, 'id="__GM__-r1"') && !preg_match('~\sid="(?!__GM__-)~', $svg), 'ids mit Präfix je Einbindung');
        self::t(str_contains($svg, '<g class="gm-a gm-a1">') && str_contains($o['css'], 'transform-origin:40px 40px') && str_contains($o['css'], 'animation-timing-function:ease-out'), 'Hülle mit Drehpunkt und Verlauf je Abschnitt');
        self::t(str_contains($svg, '<a href="__GMHREF0__" class="gm-l') && $o['links'] === ['https://example.org/a'] && str_contains($svg, 'aria-label="Mehr"'), 'Link-Bereich als <a> mit Namen');
        self::t(str_contains($svg, '<title>Tipp</title>') && str_contains($svg, 'class="gm-tip gm-tip1"'), 'Tooltip als <title> und sichtbares Schild');
        self::t(str_contains($svg, 'role="button" tabindex="0" aria-pressed="false" data-gm-hot="1"'), 'Hotspot als Schalter');
        self::t(str_contains($svg, 'pathLength="1"') && str_contains($svg, 'data-gm-count="0 1250 0 . , 0 1 ease-out"'), 'Linie zeichnen (pathLength) und Zählen');
        self::t(str_contains($o['css'], '@media (prefers-reduced-motion:reduce)') && str_contains($o['css'], 'infinite alternate both') && str_contains($o['css'], 'animation-play-state:paused'), 'reduzierte Bewegung, Schleife, Anhalten');
        self::t($o['js'] && $o['interactive'], 'Skript nötig (sichtbar, Schleife, Hotspot, Zählen)');
        // WCAG 2.2.2: automatisch gestartet und länger als 5 s bzw. endlos → Knopf „Animation anhalten“
        self::t(Motion::needsPause(['tr' => 'view', 'it' => 0, 'd' => 2, 'dl' => 0]) && Motion::needsPause(['tr' => 'load', 'it' => 3, 'd' => 2, 'dl' => 0])
            && !Motion::needsPause(['tr' => 'load', 'it' => 2, 'd' => 2, 'dl' => 0.5]) && !Motion::needsPause(['tr' => 'hover', 'it' => 0, 'd' => 2, 'dl' => 0]), 'Knopf „Animation anhalten“ ab 5 s oder endlos (nicht beim Zeigen)');
        self::t($o['pause'] && str_contains($o['css'], '.gmtest-box .cms-gm-pause{') && str_contains($o['css'], ':is(.is-off,.is-paused)'), 'Knopf: Regeln in der CSS-Datei, Anhalten über .is-paused');
        $lm = $model;
        $lm['scene'] = ['tr' => 'load'] + $lm['scene'];
        $lo = Motion::build($web, null, (array) $lm, 'gmload', ['w' => 400, 'h' => 200]);
        self::t($lo['pause'] && $lo['js'] && str_contains($lo['css'], '.gmload.is-play .gm-a1{animation:') && !str_contains($lo['css'], "\n.gmload .gm-a1{animation:"), 'endlos beim Laden: spielt erst mit dem Skript (ohne Skript Endbild)');
        $sm = $model;
        $sm['scene'] = ['it' => 1, 'd' => 2, 'dl' => 0, 'tr' => 'load'] + $sm['scene'];
        self::t(!Motion::build($web, null, (array) $sm, 'gmshort', ['w' => 400, 'h' => 200])['pause'], 'kurze Bewegung ohne Knopf');
        self::t(strlen(Motion::generator()) === 12 && Motion::generator() === Motion::generator(), 'Dateinamen hängen am Erzeuger (Prüfsumme statt Handzahl)');
        $alone = Motion::standalone($web, $w);
        self::t(str_contains($alone, '<style><![CDATA[') && str_contains($alone, 'href="https://example.org/a"') && !str_contains($alone, '__GM'), 'eigenständiges animiertes SVG mit CSS');
        // Mediathek: inline statt <img>
        [$m, $err] = self::saveMedia($web, ['name' => 'selbsttest-motion', 'alt' => 'Selbsttest Bewegung'], null, $w);
        self::t($m !== null, 'bewegte Grafik gespeichert' . ($err ? " ($err)" : ''));
        if (!$m) return;
        $files[] = Media::path($m);
        Motion::forget();
        $html = Media::pictureOf($m);
        foreach (glob(Store::dir() . '/m/gm-' . (int) $m['id'] . '-*.css') ?: [] as $f) $files[] = $f;
        foreach (glob(site()->storage('cache/grafik-motion') . '/gm-' . (int) $m['id'] . '-*.json') ?: [] as $f) $files[] = $f;
        self::t(str_contains($html, '<svg') && !str_contains($html, '<img') && str_contains($html, 'data-gm-css') && str_contains($html, 'aria-label="Selbsttest Bewegung"'), 'Mediathek: bewegte Grafik inline mit CSS-Datei');
        self::t(str_contains($html, 'href="https://example.org/a"') && !str_contains($html, '__GM'), 'Platzhalter ersetzt (Links, ids)');
        self::t(str_contains($html, '<span class="cms-gm-box ') && str_contains($html, '<button type="button" class="cms-gm-pause" hidden') && str_contains($html, 'aria-label="' . e(lt('Animation anhalten')) . '"') && str_contains($html, 'role="status"'), 'Website: Knopf „Animation anhalten“ in der Hülle (erst mit Skript sichtbar)');
        self::t(str_contains(Motion::inject('<body>' . $html . '</body>'), 'graphic-motion.js') && Motion::inject('<body></body>') === '<body></body>', 'Skript nur auf Seiten mit bewegten Grafiken');
        self::t(str_contains(Media::pictureOf($m, '100vw', ['static' => true]), '<img'), '„static“ erzwingt das Bild');
        [$m2] = self::saveMedia(self::SAMPLE, ['name' => 'selbsttest-still', 'alt' => 'Still']);
        if ($m2) { $files[] = Media::path($m2); self::t(str_contains(Media::pictureOf($m2), '<img'), 'Grafik ohne Bewegung bleibt ein Bild'); }
    }

    /**
     * Ausbaustufe 5 – Daten und Zustände (Core\Graphics\Bind): Modell nur Base64 und bereinigt, Zustände zu fester Zeit (geöffnet nach
     * den Öffnungszeiten, Tag/Nacht, Wochentag), nächster Wechsel für den Seiten-Cache, Werte aus einer Datentabelle wie per Hand
     * gezählt, Anwenden (Länge, Drehung, Farbe, Sichtbarkeit, Text bleibt Text, <desc> mit Werten), Mediathek: Datei je Zustand,
     * Alt-Text mit Werten, Frist des Seiten-Caches; Seiten-Cache mit abgelaufener Frist wird neu erzeugt.
     */
    private static function bind(array &$files): void
    {
        $b = fn(mixed $o) => base64_encode((string) json_encode($o));
        $table = null;
        foreach (\Core\Data\Tables::content() as $t) {
            if (\Core\Data\Tables::isInbox($t)) continue;
            foreach ($t['fields'] as $f) if (($f['type'] ?? '') === 'number') { $table = [$t, (string) $f['name']]; break 2; }
        }
        $defs = ['plaetze' => ['t' => 'table', 'tb' => $table ? $table[0]['handle'] : 'gibt_es_nicht', 'a' => 'sum', 'f' => $table ? $table[1] : 'x', 'l' => 'Plätze'],
            'anzahl' => ['t' => 'table', 'tb' => $table ? $table[0]['handle'] : 'gibt_es_nicht', 'a' => 'count', 'l' => 'Kurse'],
            'Böse' => ['t' => 'table', 'tb' => 'x; DROP', 'a' => 'sum']];
        $work = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 200" width="400" height="200" data-ge-data="' . $b(['v' => $defs]) . '"><title>Kurse</title>'
            . '<rect id="balken" x="20" y="20" width="200" height="30" fill="#5b73ad" class="ge-tf-accent" data-ge-bind="' . $b([['p' => 'w', 'v' => 'plaetze', 'a' => [0, 100], 'o' => [20, 35]], ['p' => 'fill', 'v' => 'plaetze', 'm' => 'steps', 'b' => '#cccccc', 's' => [[10, '#00aa00'], [50, '#aa0000']]], ['p' => 'evil', 'v' => 'plaetze']]) . '"/>'
            . '<text id="zahl" x="20" y="90" font-size="20">Noch <tspan>12</tspan> Plätze<tspan x="20" dy="1.2em">zweite Zeile</tspan></text>'
            . '<circle id="sonne" cx="300" cy="50" r="20" fill="#f5b400" data-ge-bind="' . $b([['p' => 'show', 'v' => '@tag', 'op' => '=', 'x' => 1]]) . '"/>'
            . '<circle id="mond" cx="300" cy="50" r="20" fill="#334" data-ge-bind="' . $b([['p' => 'show', 'v' => '@tag', 'op' => '=', 'x' => 0]]) . '"/>'
            . '<g id="offen" data-ge-bind="' . $b([['p' => 'show', 'v' => '@offen', 'op' => '=', 'x' => 1]]) . '"><text x="20" y="180">Geöffnet</text></g>'
            . '<g id="zu" data-ge-bind="' . $b([['p' => 'show', 'v' => '@offen', 'op' => '=', 'x' => 0]]) . '"><text x="20" y="180">Geschlossen</text></g>'
            . '<path id="zeiger" d="M200 150h40" stroke="#000" data-ge-bind="' . $b([['p' => 'r', 'v' => '@uhrzeit', 'a' => [0, 24], 'g' => [0, 360], 'o' => [200, 150]]]) . '"/>'
            . '<rect id="x" width="1" height="1" data-ge-bind="!!kein-base64"/></svg>';
        // Text-Bindung nachträglich (zahl), damit der Text oben lesbar bleibt
        $work = str_replace('<text id="zahl"', '<text id="zahl" data-ge-bind="' . $b([['p' => 'text', 'v' => 'plaetze', 'tpl' => '{v} Plätze frei', 'fmt' => 'num', 'd' => 0]]) . '"', $work);
        $w = Svg::clean($work, ['editor' => true])['svg'];
        self::t(str_contains($w, 'data-ge-data="') && str_contains($w, 'data-ge-bind="') && !str_contains($w, '!!kein-base64'), 'Daten: Bindungen bleiben in der Arbeitsfassung, nur Base64');
        // Export-Bereiche (Persona Export): nur Base64, nur in der Arbeitsfassung
        $sl = base64_encode('[{"id":"s1","n":"Favicon","x":0,"y":0,"w":24,"h":24,"f":["ico"]}]');
        $sx = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" data-ge-slices="' . $sl . '"><rect width="10" height="10" data-ge-slices="&lt;script&gt;"/></svg>';
        self::t(str_contains(Svg::clean($sx, ['editor' => true])['svg'], 'data-ge-slices="' . $sl . '"') && substr_count(Svg::clean($sx, ['editor' => true])['svg'], 'data-ge-slices') === 1
            && !str_contains(Svg::clean($sx, ['keep_ids' => true])['svg'], 'data-ge-slices'), 'Export: Bereiche nur als Base64 in der Arbeitsfassung, nie in der Web-Fassung');
        $os = base64_encode('[{"n":"Hervorhebung","props":{"fill":"#e5484d"},"fx":null}]');
        $ox = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" data-ge-ostyles="' . $os . '"><rect width="10" height="10" data-ge-os="Hervorhebung" data-ge-ostyles="x y"/></svg>';
        $oc = Svg::clean($ox, ['editor' => true])['svg'];
        self::t(str_contains($oc, 'data-ge-ostyles="' . $os . '"') && substr_count($oc, 'data-ge-ostyles') === 1 && str_contains($oc, 'data-ge-os="Hervorhebung"'), 'Objektstile: Base64 an der Wurzel, Name am Objekt');
        $model = Bind::model($w);
        self::t($model !== null && isset($model['values']['plaetze']) && !isset($model['values']['Böse']) && count($model['items']['balken'] ?? []) === 2, 'Daten: Modell bereinigt (Namen, unbekannte Eigenschaft entfällt)');
        if (!$model) return;
        // Zustände zu fester Zeit: Mittwoch 10:00 bzw. 23:00 (Zeitzone der Website), Öffnungszeiten Mi 9–17 Uhr
        app()->settings->set('hours', [['tag' => '3', 'von' => '09:00', 'bis' => '17:00', 'pause_von' => '', 'pause_bis' => '']]);
        app()->settings->forget();
        $tz = new \DateTimeZone(date_default_timezone_get());
        // ein Mittwoch in der Zukunft (die Frist des Seiten-Caches gilt nur für künftige Zeitpunkte)
        $wed = (new \DateTimeImmutable('now', $tz))->modify('next wednesday');
        $wed10 = $wed->setTime(10, 0)->getTimestamp();
        $wed23 = $wed->setTime(23, 0)->getTimestamp();
        self::t(Bind::state('@offen', $wed10) === 1.0 && Bind::state('@offen', $wed23) === 0.0 && Bind::state('@wochentag', $wed10) === 3.0, 'Daten: geöffnet nach den Öffnungszeiten, Wochentag');
        self::t(Bind::state('@tag', (new \DateTimeImmutable('2026-06-18 12:00', $tz))->getTimestamp()) === 1.0 && Bind::state('@tag', (new \DateTimeImmutable('2026-06-18 02:00', $tz))->getTimestamp()) === 0.0, 'Daten: Tag und Nacht');
        self::t(Bind::state('@uhrzeit', $wed->setTime(14, 37)->getTimestamp()) === round(14 + 35 / 60, 4) && Bind::state('@datum', $wed10) === (float) $wed->format('md'), 'Daten: Uhrzeit in 5-Minuten-Schritten, Datum MMTT');
        self::t(Bind::nextChange('@offen', $wed10) === $wed->setTime(17, 0)->getTimestamp() && Bind::nextChange('@uhrzeit', $wed10 + 61) === $wed10 + 300, 'Daten: nächster Wechsel (Schließen, nächste 5 Minuten)');
        $sunNext = Bind::nextChange('@tag', $wed10);
        self::t($sunNext !== null && $sunNext > $wed10 && $sunNext <= $wed10 + 86400, 'Daten: nächster Wechsel Tag/Nacht innerhalb eines Tages');
        // Werte aus der Datentabelle wie per Hand gerechnet
        if ($table) {
            $sum = 0.0; $n = 0;
            foreach (\Core\Data\Entries::query($table[0], ['status' => 'published']) as $e) { $n++; if (is_numeric($e[$table[1]] ?? null)) $sum += (float) $e[$table[1]]; }
            Bind::forget();
            self::t(Bind::tableValue($model['values']['plaetze']) === $sum && Bind::tableValue($model['values']['anzahl']) === (float) $n, 'Daten: Summe und Anzahl aus der Datentabelle');
        } else {
            self::t(Bind::tableValue($model['values']['plaetze']) === null, 'Daten: fehlende Tabelle liefert keinen Wert');
        }
        // Anwenden
        $web = (string) preg_replace('~ data-(ge|name)[\w-]*="[^"]*"~', '', str_replace('<svg ', '<svg class="ge-bound" ', $work));
        $web = Svg::clean($web, ['keep_ids' => true])['svg'];
        self::t(Bind::isBound($web) && !Bind::isBound(self::SAMPLE), 'Daten: Kennzeichen ge-bound an der Web-Fassung');
        $vals = ['plaetze' => 60.0, '@tag' => 1.0, '@offen' => 0.0, '@uhrzeit' => 6.0];
        $out = Bind::apply($web, $model, $vals);
        self::t(str_contains($out, 'transform="translate(20 35) scale(0.6 1) translate(-20 -35)"') && str_contains($out, 'fill="#aa0000"') && !str_contains($out, 'ge-tf-accent'), 'Daten: Länge (Anker), Farbe nach Schwellen, Design-Farbe weicht');
        self::t(str_contains($out, '>60 Plätze frei<') && !str_contains($out, 'zweite Zeile') && str_contains($out, '<text id="zahl"'), 'Daten: Text bleibt Text, Vorlage mit Wert');
        self::t(str_contains($out, 'id="sonne"') && !str_contains($out, 'id="mond"') && !str_contains($out, 'id="offen"') && str_contains($out, 'id="zu"'), 'Daten: Sichtbarkeit (Tag, geschlossen)');
        self::t(str_contains($out, 'rotate(90 200 150)') && preg_match('~<desc>[^<]*Plätze: 60[^<]*geschlossen~u', $out) === 1, 'Daten: Drehung nach Uhrzeit, Beschreibung nennt die Werte');
        self::t(Bind::alt('Belegung: {plaetze} Plätze', $model, $vals) === 'Belegung: 60 Plätze' && str_starts_with(Bind::alt('Kursbelegung', $model, $vals), 'Kursbelegung (Plätze: 60'), 'Daten: Alt-Text mit Platzhalter bzw. angehängten Werten');
        self::t(!preg_match('~\sstyle="|<script|on\w+="~i', Svg::clean($out, ['keep_ids' => true])['svg']), 'Daten: Ausgabe ohne style/Skript (CSP)');
        // Mediathek: Datei je Zustand, Frist des Seiten-Caches
        [$m, $err] = self::saveMedia($web, ['name' => 'selbsttest-daten', 'alt' => 'Kurse: {anzahl}'], null, $w);
        self::t($m !== null, 'Daten: Grafik gespeichert' . ($err ? " ($err)" : ''));
        if (!$m) return;
        $files[] = Media::path($m);
        Bind::$now = $wed10;
        \Core\PageCache::resetUntil();
        Motion::forget();
        $h1 = Media::pictureOf($m);
        $until = \Core\PageCache::pendingUntil();
        Bind::$now = $wed23;
        Bind::forget();
        $h2 = Media::pictureOf($m);
        foreach (glob(Store::dir() . '/b/gb-' . (int) $m['id'] . '-*.svg') ?: [] as $f) $files[] = $f;
        preg_match('~src="([^"]+)"~', $h1, $s1);
        preg_match('~src="([^"]+)"~', $h2, $s2);
        self::t(str_contains($h1, '/grafik/b/gb-' . (int) $m['id'] . '-') && ($s1[1] ?? '') !== ($s2[1] ?? ''), 'Daten: Datei je Zustand (geöffnet / geschlossen)');
        $f1 = site()->mediaDir() . '/' . preg_replace('~^.*?/(grafik/b/[^"?]+)$~', '$1', $s1[1] ?? '');
        self::t(is_file($f1) && str_contains((string) file_get_contents($f1), 'id="offen"') && !str_contains((string) file_get_contents($f1), 'id="zu"'), 'Daten: um 10 Uhr „geöffnet“ in der Datei');
        self::t($until !== null && $until <= $wed10 + 300, 'Daten: Seiten-Cache gilt nur bis zum nächsten Wechsel');
        self::t(str_contains($h1, 'alt="Kurse: ') && !str_contains($h1, '{anzahl}'), 'Daten: Alt-Text mit Wert');
        Bind::$now = null;
        \Core\PageCache::resetUntil();
        // Seiten-Cache: abgelaufene Frist → neu erzeugen
        if (\Core\PageCache::enabled()) {
            \Core\PageCache::put('selbsttest:bind', '<p>alt</p>');
            $c = glob(site()->storage('cache/pages') . '/*.html') ?: [];
            usort($c, fn($a, $z) => filemtime($z) <=> filemtime($a));
            $hit = \Core\PageCache::get('selbsttest:bind') === '<p>alt</p>';
            foreach ($c as $f) if ((string) @file_get_contents($f) === '<p>alt</p>') { file_put_contents($f . '.until', (string) (time() - 5)); $files[] = $f; $files[] = $f . '.until'; }
            self::t($hit && \Core\PageCache::get('selbsttest:bind') === null, 'Daten: Seiten-Cache mit abgelaufener Frist wird neu erzeugt');
        }
    }

    private static function sanitize(): void
    {
        $r = Svg::clean(self::SAMPLE, ['keep_ids' => true]);
        $s = $r['svg'];
        foreach (['id="diagramm"', 'id="balken"', 'data-name="Diagramm"', 'data-name="Welle"', 'url(#verlauf-1)', 'marker-end="url(#pfeil-1)"',
            '<feDropShadow', 'mix-blend-mode:multiply', 'stroke="currentColor"', '<title>Umsatz 2026</title>', '<desc>', '<tspan', '>2026</tspan>',
            'stroke-dasharray="6 4"', 'transform="translate(20 21.5)"', 'viewBox="0 0 800 500"'] as $needle) {
            self::t(str_contains($s, $needle), "Editor-Ausgabe behält $needle");
        }
        self::t(Svg::clean($s, ['keep_ids' => true])['svg'] === $s, 'Bereinigung ist idempotent (zweimal speichern ändert nichts)');
        self::t($r['width'] === 800 && $r['height'] === 500, 'Maße aus der viewBox');
        $plain = Svg::clean(self::SAMPLE)['svg'];
        self::t(!str_contains($plain, 'id="diagramm"') && str_contains($plain, 'data-name="Diagramm"'), 'ohne keep_ids fallen unbenutzte ids weg, Ebenennamen bleiben');
        $bad = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10" onload="alert(1)">'
            . '<script>alert(1)</script><foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject>'
            . '<rect width="10" height="10" data-name="&lt;b&gt;x" data-evil="1" onclick="x()" fill="url(https://example.org/x.svg#a)"/>'
            . '<use xlink:href="https://example.org/a.svg#b"/><image href="data:image/png;base64,AAAA"/></svg>';
        try {
            $b = Svg::clean($bad, ['keep_ids' => true])['svg'];
            self::t(!preg_match('~script|onload|onclick|foreignObject|example\.org|data-evil|<image~i', $b), 'Schadcode in Editor-Ausgabe wird entfernt');
            self::t(!str_contains($b, '<b>'), 'Ebenenname ohne Markup');
        } catch (\RuntimeException $e) {
            self::t(true, 'Schadcode abgelehnt: ' . $e->getMessage());
        }
    }

    /** Editor-Daten der Arbeitsfassung: nur mit 'editor', nur als Text, nie in der Web-Fassung */
    private static function editorData(): void
    {
        $src = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" data-ge-guides="v:50 h:20" data-ge-grid="10 2">'
            . '<rect width="10" height="10" data-locked="1" data-ge-fill="#5B73AD" data-ge-evil="javascript:alert(1)" data-ge-x="url(https://example.org/a)"/>'
            . '<text x="1" y="9" data-ge-frame="80">A</text><image href="https://example.org/a.png" width="5" height="5"/></svg>';
        $e = Svg::clean($src, ['editor' => true]);
        self::t(str_contains($e['svg'], 'data-ge-guides="v:50 h:20"') && str_contains($e['svg'], 'data-locked="1"') && str_contains($e['svg'], 'data-ge-frame="80"'), 'Arbeitsfassung behält Hilfslinien, Sperren, Textrahmen');
        self::t(!str_contains($e['svg'], 'javascript:') && !str_contains($e['svg'], 'example.org'), 'Editor-Daten ohne Skript-Schemata und fremde Adressen');
        $w = Svg::clean($src, ['keep_ids' => true]);
        self::t(!str_contains($w['svg'], 'data-ge-') && !str_contains($w['svg'], 'data-locked'), 'ohne editor: keine Editor-Daten');
        self::t(($w['images'] ?? 0) === 1 && !str_contains($w['svg'], '<image'), 'Pixelbild entfernt und gezählt (Meldung im Editor)');
        // Formatvorlagen (data-ge-styles): nur Base64; Typografie bleibt in beiden Fassungen (Ausbaustufe 3a)
        $b64 = base64_encode('{"p":[{"n":"Titel","props":{"font-size":"32"}}],"c":[]}');
        $t = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" data-ge-styles="' . $b64 . '"><g data-ge-styles="x&lt;script&gt;"/>'
            . '<text x="1" y="9" data-ge-para="al:justify;hy:de" style="font-feature-settings:&quot;smcp&quot;, &quot;liga&quot; 0;font-variation-settings:\'wdth\' 80" word-spacing="2">A<tspan baseline-shift="super" font-size="60%">2</tspan></text></svg>';
        $e = Svg::clean($t, ['editor' => true]);
        self::t(str_contains($e['svg'], 'data-ge-styles="' . $b64 . '"') && substr_count($e['svg'], 'data-ge-styles') === 1, 'Formatvorlagen: Base64 bleibt, anderes wird verworfen');
        self::t(str_contains($e['svg'], 'data-ge-para="al:justify;hy:de"'), 'Absatz-Einstellungen in der Arbeitsfassung');
        $w = Svg::clean($t, ['keep_ids' => true]);
        self::t(str_contains($w['svg'], 'font-feature-settings') && str_contains($w['svg'], 'font-variation-settings') && str_contains($w['svg'], 'baseline-shift="super"') && str_contains($w['svg'], 'word-spacing="2"'),
            'OpenType, Achsen, Grundlinienversatz und Wortabstand bleiben in der Web-Fassung');
    }
}
