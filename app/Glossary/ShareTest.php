<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Glossary;

use Core\Data\Entries;
use Core\Data\Shared;
use Core\Data\Tables;
use Core\Features;
use Core\Lang;
use Core\Pages;
use Core\Sites;

/**
 * php bin/console glossary:sharetest --sandbox – Ende-zu-Ende-Test des geteilten Glossars mit zwei Websites (A = aufrufende Website,
 * B = nächste Website der Installation). Jeder Schritt läuft als eigener Prozess mit --site=… (eine Website je Prozess), der
 * Ablauf prüft die Ergebnisse: Teilen ein/aus, Beitreten mit Doppeln (existing/mine), gemeinsames Lesen, Ausblenden je Website,
 * Markierung mit fremden Begriffen, Canonical, Rechte, Verweise nach dem Umnummerieren, Verlassen mit Kopie.
 *
 * ÄNDERT DIE GLOSSARE BEIDER WEBSITES – nur in einer Wegwerf-Kopie der Installation ausführen (Schalter --sandbox). Bricht ab,
 * wenn es schon ein geteiltes Glossar gibt oder eine der beiden Websites eigene Begriffe hat.
 */
final class ShareTest
{
    private static int $ok = 0;
    private static array $fail = [];

    public static function run(bool $sandbox, ?string $step = null, ?string $state = null): int
    {
        if ($step !== null) return self::step($step, (string) $state);
        if (!$sandbox) {
            fwrite(STDERR, "Dieser Test ändert die Glossare zweier Websites – nur in einer Wegwerf-Kopie: php bin/console glossary:sharetest --sandbox\n");
            return 1;
        }
        $a = site()->key;
        $b = array_values(array_diff(array_keys(Sites::all()), [$a]))[0] ?? null;
        if ($b === null) { fwrite(STDERR, "Es braucht mindestens zwei Websites (config/sites/*.php).\n"); return 1; }
        if (Shared::meta(Sharing::KEY)) { fwrite(STDERR, "Es gibt schon ein geteiltes Glossar – Abbruch.\n"); return 1; }
        $file = tempnam(sys_get_temp_dir(), 'glsshare');
        file_put_contents($file, '{}');
        $call = function (string $site, string $step) use ($file): array {
            $out = [];
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT . '/bin/console') . ' glossary:sharetest --sandbox --step=' . escapeshellarg($step)
                . ' --state=' . escapeshellarg($file) . ' --site=' . escapeshellarg($site) . ' 2>&1', $out, $code);
            $last = (string) end($out);
            $res = json_decode($last, true);
            if ($code !== 0 || !is_array($res)) {
                self::$fail[] = "$step ($site): Prozess fehlgeschlagen – " . implode(' | ', array_slice($out, -5));
                return [];
            }
            return $res;
        };
        $eq = function (string $name, mixed $got, mixed $want): void {
            if ($got === $want) { self::$ok++; return; }
            self::$fail[] = $name . ': erwartet ' . json_encode($want, JSON_UNESCAPED_UNICODE) . ', erhalten ' . json_encode($got, JSON_UNESCAPED_UNICODE);
        };
        try {
            $sa = $call($a, 'setupA');
            $sb = $call($b, 'setupB');
            if (!$sa || !$sb) throw new \RuntimeException('Einrichten fehlgeschlagen');
            $eq('Vorher: B kann nicht beitreten (nicht geteilt)', $call($b, 'joinTry')['error'] ?? '', 'not-shared');
            $eq('Rechte: ohne Anmeldung kein Teilen einrichten', $call($a, 'perm')['canManage'] ?? null, false);
            $r = $call($a, 'share');
            $eq('Teilen: A ist Eigentümerin, B eingeladen', [$r['owner'] ?? null, $r['invited'] ?? null], [$a, [$b]]);
            $eq('Teilen: IDs bleiben (Verweise wirken weiter)', $r['ids'] ?? null, $sa['ids']);
            $eq('Teilen: Tabelle ist geteilt', $r['shared'] ?? null, true);
            $p = $call($b, 'plan');
            $eq('Beitreten: Doppel erkannt (Groß/klein, Akzente)', $p['dupes'] ?? null, ['Ubertragung' => ['Übertragung'], 'spf' => ['SPF']]);
            $j = $call($b, 'join');
            $eq('Beitreten: B ist beteiligt', $j['role'] ?? null, 'member');
            $eq('Beitreten: Verweis auf eigenen Begriff umgestellt', $j['refDns'] ?? null, true);
            $eq('Beitreten: Verweis auf Doppel zeigt auf A-Begriff', $j['refSpf'] ?? null, true);
            $rb = $call($b, 'readB');
            $eq('B liest: eigene + sichtbare fremde Begriffe (ohne ausgeblendete, ohne fremde Entwürfe)', $rb['terms'] ?? null,
                ['Barrierefreiheit', 'DNS', 'SPF', 'Ubertragung', 'Zwischenspeicher-Entwurf']);
            $eq('B liest: Herkunft fremder Begriffe', $rb['foreign'] ?? null, ['Barrierefreiheit', 'SPF']);
            $eq('B: Markierung mit fremden Begriffen', $rb['marked'] ?? null, ['Barrierefreiheit', 'DNS', 'SPF']);
            $eq('B: Detailseite eines fremden Begriffs unter eigener Adresse', $rb['detail'] ?? null, true);
            $eq('B: ausgeblendeter Begriff hat keine Detailseite', $rb['hiddenDetail'] ?? null, false);
            $eq('B: Canonical (Standard) → Ursprungs-Website', $rb['canonOrigin'] ?? null, true);
            $eq('B: Canonical „Diese Website“ → eigene Adresse', $rb['canonSelf'] ?? null, true);
            $eq('B: Quick-Glossar findet fremden Begriff mit Herkunft', $rb['quickOrigin'] ?? null, true);
            $eq('B: Doppelprüfung über das geteilte Glossar (Variante von A)', $rb['quickDupe'] ?? null, true);
            $eq('B: fremder Begriff nicht änderbar', $rb['editForeign'] ?? null, false);
            $eq('B: Ausblenden je Website', $rb['hideWorks'] ?? null, true);
            $eq('B: neuer Begriff gehört B', $rb['newOrigin'] ?? null, $b);
            $eq('B: Linkauswahl zeigt fremden Begriff', $rb['links'] ?? null, true);
            $ra = $call($a, 'readA');
            $eq('A liest: eigene + Begriffe von B (automatische Übernahme), eigener Entwurf', $ra['terms'] ?? null,
                ['Barrierefreiheit', 'Cookie-Entwurf', 'DNS', 'Neu-von-B', 'SPF', 'Ubertragung', 'Übertragung']);
            $eq('A: Markierung mit Begriff von B', $ra['marked'] ?? null, ['DNS', 'SPF']);
            $eq('A: ausgeblendet bei B gilt nicht für A', $ra['hasUe'] ?? null, true);
            $eq('A: Teilen beenden mit Mitgliedern abgelehnt', $ra['endBlocked'] ?? null, true);
            $l = $call($b, 'leaveB');
            $eq('B verlässt: eigene Tabelle mit eigenen Begriffen + Kopien', $l['terms'] ?? null, ['Barrierefreiheit', 'DNS', 'Neu-von-B', 'SPF', 'Ubertragung', 'Zwischenspeicher-Entwurf']);
            $eq('B verlässt: nicht mehr geteilt', $l['shared'] ?? null, false);
            $eq('B verlässt: Verweis wirkt weiter (gleiche ID)', $l['refOk'] ?? null, true);
            $e = $call($a, 'endA');
            $eq('A: Begriffe von B weg', $e['before'] ?? null, ['Barrierefreiheit', 'Cookie-Entwurf', 'SPF', 'Übertragung']);
            $eq('A: Teilen beendet, wieder eigene Tabelle', [$e['shared'] ?? null, $e['meta'] ?? null], [false, false]);
        } catch (\Throwable $ex) {
            self::$fail[] = 'Abbruch: ' . $ex->getMessage();
        } finally {
            @unlink($file);
        }
        foreach (self::$fail as $f) echo "  FEHLER: $f\n";
        echo '  ' . self::$ok . ' Prüfungen bestanden, ' . count(self::$fail) . " fehlgeschlagen\n";
        return self::$fail ? 1 : 0;
    }

    // ================================================================= Schritte (je Website ein Prozess)

    private static function step(string $step, string $file): int
    {
        $state = json_decode((string) @file_get_contents($file), true) ?: [];
        $res = [];
        $add = function (string $begriff, string $kurz, string $status = 'published', string $var = '') {
            [$id, $err] = Entries::save(Glossary::table(), null, ['begriff' => $begriff, 'kurz' => $kurz, 'varianten' => $var, 'status' => $status]);
            if ($err) throw new \RuntimeException(implode(' ', $err));
            Glossary::flush();
            return (int) $id;
        };
        $names = function (array $terms): array {
            $n = array_column($terms, 'term');
            sort($n);
            return $n;
        };
        $marked = function (): array {
            Glossary::$done = false;
            $html = Glossary::page('<html><body><main><p>SPF, DNS und Barrierefreiheit im Text.</p></main></body></html>');
            preg_match_all('~class="gl-pop__t">([^<]+)<~', $html, $m);
            $m = $m[1];
            sort($m);
            return $m;
        };
        switch ($step) {
            case 'setupA':
            case 'setupB':
                if (Glossary::table() && Glossary::terms(true)) throw new \RuntimeException('Diese Website hat schon Begriffe – Abbruch (nur Wegwerf-Kopie).');
                Features::setUi(Glossary::FEATURE, true);
                Glossary::install(['publish' => true]);
                Glossary::flush();
                // Adresse der Website wie im Betrieb einstellen (sonst kennt das Register auf der Kommandozeile keine Adresse von A –
                // live kommt sie aus sys.site_url bzw. dem ersten Aufruf im Browser)
                if ((string) app()->settings->get('sys.site_url', '') === '') app()->settings->set('sys.site_url', 'https://' . site()->key . '.sharetest.invalid');
                $state[$step === 'setupA' ? 'urlA' : 'urlB'] = rtrim((string) app()->settings->get('sys.site_url'), '/');
                $ids = [];
                if ($step === 'setupA') {
                    $ids['SPF'] = $add('SPF', 'A: Absender-Richtlinie.', 'published', 'Sender Policy Framework');
                    $ids['Barrierefreiheit'] = $add('Barrierefreiheit', 'A: Zugang für alle.');
                    $ids['Übertragung'] = $add('Übertragung', 'A: Senden von Daten.');
                    $ids['Cookie-Entwurf'] = $add('Cookie-Entwurf', 'A: Entwurf.', 'draft');
                } else {
                    $ids['spf'] = $add('spf', 'B: eigene SPF-Erklärung.');
                    $ids['Ubertragung'] = $add('Ubertragung', 'B: eigene Erklärung.');
                    $ids['DNS'] = $add('DNS', 'B: Namensauflösung.');
                    $ids['Zwischenspeicher-Entwurf'] = $add('Zwischenspeicher-Entwurf', 'B: Entwurf.', 'draft');
                    // Seite mit Verweisen auf Begriffe (wird beim Beitreten umgeschrieben)
                    $pid = Pages::create(['slug' => 'qa-glossar-verweise', 'title' => 'QA Verweise', 'status' => 'published'], []);
                    $json = json_encode([['type' => 'text', 'data' => ['text' => '<a href="entry:glossar:' . $ids['DNS'] . '">DNS</a> <a href="entry:glossar:' . $ids['spf'] . '">SPF</a>']]]);
                    app()->db->update('pages', ['content_draft' => $json, 'content_published' => $json], 'id = :id', ['id' => $pid]);
                    $state['pageB'] = $pid;
                }
                $state[$step === 'setupA' ? 'idsA' : 'idsB'] = $ids;
                $res = ['ids' => $ids];
                break;
            case 'joinTry':
                try { Sharing::join(); $res = ['error' => 'none']; } catch (\InvalidArgumentException) { $res = ['error' => 'not-shared']; }
                break;
            case 'perm':
                $res = ['canManage' => Sharing::canManage()];
                break;
            case 'share':
                Sharing::share([array_values(array_diff(array_keys(Sites::all()), [site()->key]))[0]]);
                $m = Shared::meta(Sharing::KEY);
                $t = Glossary::table();
                $ids = [];
                foreach ($state['idsA'] as $name => $id) $ids[$name] = Entries::find($t, (int) $id) ? (int) $id : 0;
                $res = ['owner' => $m['owner'], 'invited' => $m['invited'], 'ids' => $ids, 'shared' => Tables::isShared($t)];
                break;
            case 'plan':
                $d = [];
                foreach (Sharing::plan()['dupes'] as $x) $d[$x['term']['term']] = array_column($x['matches'], 'term');
                ksort($d);
                $res = ['dupes' => $d];
                break;
            case 'join':
                $choices = [];
                foreach (Sharing::plan()['dupes'] as $x) $choices[(int) $x['term']['id']] = $x['term']['term'] === 'Ubertragung' ? 'mine' : 'existing';
                Sharing::join($choices, 'qa');
                $t = Glossary::table();
                $content = (string) app()->db->fetchValue('SELECT content_published FROM pages WHERE id = ?', [(int) $state['pageB']]);
                $dns = Entries::query($t, ['status' => 'all', 'source' => 'own', 'where' => [['begriff', '=', 'DNS']]])[0] ?? null;
                $state['dnsB'] = (int) ($dns['id'] ?? 0);
                preg_match_all('~entry:glossar:(\d+)~', $content, $m);
                $refs = array_map('intval', $m[1]);
                $res = ['role' => Sharing::status()['role'],
                    'refDns' => $dns !== null && ($refs[0] ?? 0) === (int) $dns['id'] && (int) $dns['id'] !== (int) $state['idsB']['DNS'],
                    'refSpf' => ($refs[1] ?? 0) === (int) $state['idsA']['SPF']];
                break;
            case 'readB':
                $t = Glossary::table();
                $terms = Glossary::terms(true);
                $res['terms'] = $names($terms);
                $res['foreign'] = $names(array_filter($terms, fn($x) => $x['foreign']));
                $res['marked'] = $marked();
                $lang = Lang::default();
                $res['detail'] = Entries::bySlug($t, 'spf', true, $lang, 'site') !== null && str_contains((string) Entries::url($t, Entries::find($t, (int) $state['idsA']['SPF'])), '/glossar/spf');
                $ue = Entries::find($t, (int) $state['idsA']['Übertragung']);
                $res['hiddenDetail'] = Entries::bySlug($t, (string) $ue['slug'], true, $lang, 'site') !== null;
                $spf = Entries::find($t, (int) $state['idsA']['SPF']);
                $aUrl = Shared::siteInfo((string) $spf['origin_site'], Sharing::KEY)['url'];
                $res['canonOrigin'] = $aUrl === ($state['urlA'] ?? '') && $aUrl !== '' && str_starts_with((string) Entries::absUrl($t, $spf), $aUrl . '/');
                Shared::saveLocal(Sharing::KEY, ['canonical' => 'self']);
                $res['canonSelf'] = Entries::absUrl($t, $spf) === site_url() . Entries::url($t, $spf);
                Shared::saveLocal(Sharing::KEY, ['canonical' => 'origin']);
                $s = QuickTool::search('SPF', $lang);
                $res['quickOrigin'] = ($s['items'][0]['foreign'] ?? false) && ($s['items'][0]['origin'] ?? '') !== '';
                $q = QuickTool::create(['term' => 'sender policy framework', 'short' => 'x', 'lang' => $lang]);
                $res['quickDupe'] = !$q['ok'] && (int) ($q['exists']['id'] ?? 0) === (int) $state['idsA']['SPF'];
                try {
                    [, $err] = Entries::save($t, (int) $state['idsA']['SPF'], ['begriff' => 'SPF', 'kurz' => 'geändert', 'status' => 'published']);
                    $res['editForeign'] = !$err;
                } catch (\Throwable) {
                    $res['editForeign'] = false;
                }
                Sharing::hide((int) $state['idsA']['Barrierefreiheit'], true, 'qa');
                $hidden = !in_array('Barrierefreiheit', array_column(Glossary::terms(true), 'term'), true);
                Sharing::hide((int) $state['idsA']['Barrierefreiheit'], false, 'qa');
                $res['hideWorks'] = $hidden && in_array('Barrierefreiheit', array_column(Glossary::terms(true), 'term'), true);
                $nid = $add('Neu-von-B', 'B: neu nach dem Beitreten.');
                $res['newOrigin'] = (string) (Entries::find(Glossary::table(), $nid)['origin_site'] ?? '');
                [$items] = \Core\Links::tableEntries($t, 'Barrierefreiheit', 0, 10);
                $res['links'] = in_array('entry:glossar:' . $state['idsA']['Barrierefreiheit'], array_column($items, 'value'), true);
                break;
            case 'readA':
                $res['terms'] = $names(Glossary::terms(true));
                $res['marked'] = array_values(array_diff($marked(), ['Barrierefreiheit']));
                $res['hasUe'] = in_array('Übertragung', array_column(Glossary::terms(true), 'term'), true);
                try { Sharing::leave(); $res['endBlocked'] = false; } catch (\InvalidArgumentException) { $res['endBlocked'] = true; }
                break;
            case 'leaveB':
                Sharing::leave(true);
                Glossary::flush();
                Tables::flush();
                $t = Glossary::table();
                $res['terms'] = $names(Glossary::terms(true));
                $res['shared'] = $t !== null && Tables::isShared($t);
                $res['refOk'] = $t !== null && Entries::find($t, (int) $state['dnsB']) !== null && (Entries::find($t, (int) $state['dnsB'])['begriff'] ?? '') === 'DNS';
                break;
            case 'endA':
                $res['before'] = $names(Glossary::terms(true));
                Sharing::leave();
                Glossary::flush();
                Tables::flush();
                $t = Glossary::table();
                $res['shared'] = $t !== null && Tables::isShared($t);
                $res['meta'] = Shared::meta(Sharing::KEY) !== null;
                break;
            default:
                throw new \InvalidArgumentException('Unbekannter Schritt ' . $step);
        }
        file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE));
        echo json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";
        return 0;
    }
}
