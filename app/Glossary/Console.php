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
