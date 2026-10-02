<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Glossary;

use Core\Features;

/**
 * Kommandozeile des Glossars (bin/console, je Website mit --site=…):
 *   glossary:install [--publish] [--enable] [--dry-run]   Tabelle, Detailseiten und Übersicht /glossar anlegen (wiederholbar)
 *   glossary:import <datei.csv> [--overwrite] [--dry-run]  Begriffe aus CSV übernehmen (neue als Entwurf)
 *   glossary:export [--out=datei.csv]                       Begriffe als CSV
 *   glossary:check                                          Hinweise: doppelte Varianten, Überschneidungen, fehlende Erklärungen
 *   glossary:selftest [--bench]                             Selbsttest der Markierung (ohne Datenbank); --bench: Laufzeit großer Seiten
 *   glossary:share [--invite=a,b]                          Glossar dieser Website teilen (wird Eigentümerin), Websites einladen
 *   glossary:invite a,b                                     Einladungen setzen (Eigentümer; leer = keine)
 *   glossary:join [--plan] [--choice=existing|mine|both]   Geteiltem Glossar beitreten (Doppel: Standard existing); --plan nur zeigen
 *   glossary:leave [--no-copies]                            Verlassen (Mitglied, mit Kopien fremder Begriffe) bzw. Teilen beenden (Eigentümer)
 *   glossary:sharetest --sandbox                            Ende-zu-Ende-Test des Teilens mit zwei Websites – NUR in einer Wegwerf-Kopie
 */
final class Console
{
    public static function run(string $cmd, array $args): int
    {
        $opt = function (string $name) use ($args): ?string {
            foreach ($args as $a) {
                if ($a === '--' . $name) return '';
                if (str_starts_with($a, '--' . $name . '=')) return substr($a, strlen($name) + 3);
            }
            return null;
        };
        $pos = array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')));
        if ($cmd === 'glossary:selftest') return SelfTest::run($opt('bench') !== null);
        if ($cmd === 'glossary:sharetest') return ShareTest::run($opt('sandbox') !== null, $opt('step'), $opt('state'));
        $list = fn(?string $v) => array_values(array_filter(array_map('trim', explode(',', (string) $v))));
        try {
            switch ($cmd) {
                case 'glossary:install':
                    [$msgs] = Glossary::install(['publish' => $opt('publish') !== null, 'dry' => $opt('dry-run') !== null]);
                    foreach ($msgs as $m) echo "  $m\n";
                    if ($opt('enable') !== null && $opt('dry-run') === null) {
                        Features::setUi(Glossary::FEATURE, true);
                        echo "  Funktion „Glossar“ eingeschaltet.\n";
                    }
                    echo (Glossary::enabled() ? 'Glossar ist eingeschaltet' : 'Glossar ist noch AUS (Administration → Funktionen & Erweiterungen oder --enable)') . ' (' . site()->key . ").\n";
                    return 0;
                case 'glossary:import':
                    $file = $pos[0] ?? '';
                    if ($file === '' || !is_file($file)) { fwrite(STDERR, "Aufruf: glossary:import <datei.csv> [--overwrite] [--dry-run] [--site=key]\n"); return 1; }
                    $res = Glossary::importCsv((string) file_get_contents($file), $opt('dry-run') !== null, $opt('overwrite') !== null);
                    foreach ($res['errors'] as $line => $msg) echo "  Zeile $line: $msg\n";
                    printf("%s%d Zeilen: %d neu, %d geändert, %d übersprungen (%s).\n", $opt('dry-run') !== null ? 'Probelauf – ' : '', $res['total'], $res['created'], $res['updated'], $res['skipped'], site()->key);
                    return $res['errors'] ? 1 : 0;
                case 'glossary:export':
                    $csv = Glossary::exportCsv();
                    if (($out = $opt('out')) !== null && $out !== '') { file_put_contents($out, $csv); echo "Gespeichert: $out\n"; } else echo $csv;
                    return 0;
                case 'glossary:share':
                    foreach (Sharing::share($list($opt('invite'))) as $m) echo "  $m\n";
                    return 0;
                case 'glossary:invite':
                    Sharing::invite($list($pos[0] ?? ''));
                    echo '  Eingeladen: ' . (implode(', ', \Core\Data\Shared::meta(Sharing::KEY)['invited'] ?? []) ?: '–') . "\n";
                    return 0;
                case 'glossary:join':
                    if ($opt('plan') !== null) {
                        $p = Sharing::plan();
                        printf("  %d eigene Begriffe, %d der anderen Websites, %d doppelt\n", $p['local'], $p['shared'], count($p['dupes']));
                        foreach ($p['dupes'] as $d) echo '  = ' . $d['term']['term'] . ' ↔ ' . implode(', ', array_map(fn($x) => $x['term'] . ' (' . $x['origin'] . ')', $d['matches'])) . "\n";
                        return 0;
                    }
                    $choice = (string) ($opt('choice') ?? 'existing');
                    if (!in_array($choice, Sharing::CHOICES, true)) { fwrite(STDERR, "--choice: existing, mine oder both\n"); return 1; }
                    $choices = [];
                    foreach (Sharing::plan()['dupes'] as $d) $choices[(int) $d['term']['id']] = $choice;
                    foreach (Sharing::join($choices, 'cli') as $m) echo "  $m\n";
                    return 0;
                case 'glossary:leave':
                    foreach (Sharing::leave($opt('no-copies') === null) as $m) echo "  $m\n";
                    return 0;
                case 'glossary:check':
                    if (!Glossary::table()) { echo "Kein Glossar auf dieser Website (" . site()->key . ").\n"; return 1; }
                    $terms = Glossary::terms(true);
                    $checks = Glossary::checks($terms);
                    foreach ($checks as $c) echo '  ' . ($c['level'] === 'warn' ? '!' : 'i') . ' ' . $c['text'] . "\n";
                    printf("%d Begriffe (%d veröffentlicht), %d Hinweise.\n", count($terms), count(array_filter($terms, fn($t) => !$t['draft'])), count($checks));
                    return 0;
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }
        return 1;
    }
}
