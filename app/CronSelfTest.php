<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * php bin/console cron:selftest – Core\Cron: Zeitausdrücke prüfen, Fälligkeit (inkl. Tag ODER Wochentag wie cron),
 * nächster Lauf, Beschreibung in Worten, Anmeldung von Kern und Erweiterungen, Warteschlange ohne Doppel, Ausführen mit Protokoll.
 */
final class CronSelfTest
{
    public static function run(): int
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . json_encode($want, JSON_UNESCAPED_UNICODE) . ', erhalten ' . json_encode($got, JSON_UNESCAPED_UNICODE);
        };
        $eq('gültige Ausdrücke', array_map([Cron::class, 'valid'], ['* * * * *', '*/15 * * * *', '30 3 * * *', '0 4 1 * *', '0 8-18/2 * * 1-5', '5,35 * * * 7']), [true, true, true, true, true, true]);
        $eq('ungültige Ausdrücke', array_map([Cron::class, 'valid'], ['', '* * * *', '60 * * * *', '* 24 * * *', '* * 0 * *', 'x * * * *', '5-1 * * * *', '* * * * * *']), array_fill(0, 8, false));
        $t = mktime(3, 30, 0, 10, 16, 2026);   // Freitag, 16.10.2026 03:30
        $eq('fällig: täglich 03:30', Cron::due('30 3 * * *', $t), true);
        $eq('nicht fällig: 03:31', Cron::due('30 3 * * *', $t + 60), false);
        $eq('alle 15 Minuten', [Cron::due('*/15 * * * *', $t), Cron::due('*/15 * * * *', $t + 15 * 60), Cron::due('*/15 * * * *', $t + 60)], [true, true, false]);
        $eq('Wochentag Freitag (5)', [Cron::due('30 3 * * 5', $t), Cron::due('30 3 * * 1-4', $t)], [true, false]);
        $eq('Sonntag als 0 und 7', [Cron::due('0 0 * * 7', mktime(0, 0, 0, 10, 18, 2026)), Cron::due('0 0 * * 0', mktime(0, 0, 0, 10, 18, 2026))], [true, true]);
        $eq('Tag ODER Wochentag (wie cron)', Cron::due('30 3 1 * 5', $t), true);
        $eq('nächster Lauf', Cron::next('15 4 * * *', $t), mktime(4, 15, 0, 10, 16, 2026));
        $eq('nächster Lauf monatlich', Cron::next('0 4 1 * *', $t), mktime(4, 0, 0, 11, 1, 2026));
        $eq('in Worten', [Cron::describe('* * * * *'), Cron::describe('*/15 * * * *'), Cron::describe('15 4 * * *')],
            [__('jede Minute'), __('alle {n} Minuten', ['n' => '15']), __('täglich um {time}', ['time' => '04:15'])]);

        $tasks = Cron::tasks();
        $eq('Kern-Aufgaben angemeldet', isset($tasks['search.sync'], $tasks['push.send'], $tasks['backup.sites']), true);
        foreach (Extensions::available() as $name => $m) foreach ((array) ($m['schedule'] ?? []) as $k => $_) {
            $eq("Erweiterung $name.$k angemeldet", isset($tasks["$name.$k"]) && $tasks["$name.$k"]['owner'] === $name, true);
        }
        Cron::register('selftest.bad', 'x', 'rm -rf /', '* * * * *');
        Cron::register('selftest.bad2', 'x', 'cache:clear; rm -rf /', '* * * * *');
        $eq('nur Befehle der Konsole', isset(Cron::tasks()['selftest.bad']) || isset(Cron::tasks()['selftest.bad2']), false);

        Cron::register('selftest.echo', 'Selbsttest', 'site:list', '0 0 1 1 *', ['group' => 'Selbsttest']);
        $db = Cron::db();
        Cron::configure('selftest.echo', false, '*/10 * * * *');
        $eq('eigener Zeitplan und aus', [Cron::tasks()['selftest.echo']['schedule'], Cron::tasks()['selftest.echo']['enabled'], Cron::tasks()['selftest.echo']['custom']], ['*/10 * * * *', false, true]);
        Cron::configure('selftest.echo', true, '0 0 1 1 *');
        $eq('zurück auf Standard', [Cron::tasks()['selftest.echo']['schedule'], Cron::tasks()['selftest.echo']['custom']], ['0 0 1 1 *', false]);
        $db->query("DELETE FROM cron_queue WHERE k = 'selftest.echo'");
        $a = Cron::enqueue('selftest.echo', 'selftest');
        $eq('Warteschlange ohne Doppel', Cron::enqueue('selftest.echo', 'selftest'), $a);
        $code = Cron::exec('selftest.echo', 'queue:' . $a);
        $eq('Ausführen: Exit-Code 0', $code, 0);
        $eq('Warteschlange erledigt', $db->fetchValue('SELECT status FROM cron_queue WHERE id = ?', [$a]), 'done');
        $eq('Protokoll mit Ausgabe', str_contains((string) $db->fetchValue('SELECT last_output FROM cron_tasks WHERE k = ?', ['selftest.echo']), site()->key), true);
        $db->query("DELETE FROM cron_queue WHERE k = 'selftest.echo'");
        $db->query("DELETE FROM cron_runs WHERE k = 'selftest.echo'");
        $db->query("DELETE FROM cron_tasks WHERE k = 'selftest.echo'");

        foreach ($fails as $f) echo "  FEHLER: $f\n";
        echo "cron:selftest: $ok bestanden, " . count($fails) . " fehlgeschlagen\n";
        return $fails ? 1 : 0;
    }
}
