<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * php bin/console format:selftest – Core\Format: Datum (Stile, DE/EN), Uhrzeit, relative Angaben (Minute/Tag), Zahlen, Beträge,
 * Größen, Dauer, Host, Auszug – und dieselben Ausgaben wie die früheren Helfer (Media::humanSize, Links::ago, Dashboard::ago,
 * MediaJobs::duration, Clamp::excerpt, date_local, Block-Filter „number“), die jetzt Format nutzen.
 */
final class FormatSelfTest
{
    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . json_encode($want, JSON_UNESCAPED_UNICODE) . ', erhalten ' . json_encode($got, JSON_UNESCAPED_UNICODE);
        };
        $de = Format::for('de');
        $en = Format::for('en');
        $ts = strtotime('2026-09-24 14:05:00');

        // Datum und Zeit
        $eq('date short de/en', [$de->date($ts), $en->date('2026-09-24')], ['24.09.2026', '24/09/2026']);
        $eq('date long de/en', [$de->date($ts, 'long'), $en->date($ts, 'long')], ['24. September 2026', '24 September 2026']);
        $eq('date weekday, day_month', [$de->date($ts, 'weekday'), $en->date($ts, 'weekday'), $de->date($ts, 'day_month')], ['Donnerstag', 'Thursday', '24. September']);
        $eq('date leer/ungültig/DateTime', [$de->date(null), $de->date(''), $de->date('kein Datum'), $de->date(new \DateTimeImmutable('2026-01-02'))], ['', '', '', '02.01.2026']);
        $eq('time, datetime', [$de->time($ts), $de->datetime($ts), $en->datetime($ts), $de->time('')], ['14:05', '24.09.2026, 14:05', '24/09/2026, 14:05', '']);

        // Relative Angaben (fester Zeitpunkt)
        $now = strtotime('2026-09-30 12:00:00');
        $eq('relative Minute: gerade eben, Min., heute', [$de->relative($now - 30, $now), $de->relative($now - 300, $now), $de->relative('2026-09-30 09:05:00', $now)],
            ['gerade eben', 'vor 5 Min.', 'heute, 09:05']);
        $eq('relative Minute: gestern, Tage, Datum', [$de->relative('2026-09-29 23:59:00', $now), $de->relative('2026-09-26 10:00:00', $now), $de->relative('2026-08-01 10:00:00', $now)],
            ['gestern, 23:59', 'vor 5 Tagen', '01.08.2026']);
        $eq('relative englisch (wie lang/en.php)', [$en->relative($now - 300, $now), $en->relative('2026-09-29 23:59:00', $now), $en->relative('2026-09-26 10:00:00', $now, 'day')],
            ['5 min. ago', 'yesterday, 23:59', '4 days ago']);
        $eq('relative Tag (Links::ago)', [Links::ago('2026-09-30 09:05:00', $now), Links::ago('2026-09-29 23:59:00', $now), Links::ago('2026-09-26 10:00:00', $now), Links::ago('2026-08-01 10:00:00', $now), Links::ago('kein Datum', $now)],
            [__('heute, {time}', ['time' => '09:05']), __('gestern'), __('vor {n} Tagen', ['n' => 4]), I18n::locale() === 'de' ? '01.08.2026' : '01/08/2026', '']);
        $eq('Dashboard::ago = relative()', Dashboard\Dashboard::ago(date('Y-m-d H:i:s', time() - 300)), __('vor {n} Min.', ['n' => 5]));

        // Zahlen und Beträge
        $eq('number de/en', [$de->number(1234.5, 1), $en->number(1234.5, 1), $de->number('7'), $de->number('abc'), $de->number(null)], ['1.234,5', '1,234.5', '7', '', '']);
        $eq('decimal', [$de->decimal(2.50), $de->decimal(3), $de->decimal(1234.567), $en->decimal(0.1), $de->decimal(-1.20)], ['2,5', '3', '1.234,57', '0.1', '-1,2']);
        $eq('currency', [$de->currency(19.9), $en->currency(19.9), $de->currency(1500, 'EUR', null), $de->currency(-5), $de->currency(10, 'CHF'), $en->currency(3, 'USD')],
            ['19,90 €', '€19.90', '1.500 €', '−5,00 €', '10,00 CHF', '$3.00']);

        // Größe (wie Media::humanSize) und Dauer (wie MediaJobs::duration)
        $eq('bytes', [$de->bytes(0), $de->bytes(1536), $de->bytes(1048575), $de->bytes(1572864), $de->bytes(99999999), $de->bytes(1610612736), $en->bytes(1572864), $de->bytes(null)],
            ['1 KB', '2 KB', '1024 KB', '1,5 MB', '95,4 MB', '1,5 GB', '1.5 MB', '']);
        $eq('Media::humanSize = bytes()', Media::humanSize(1572864), Format::for(app()->lang ?? I18n::locale())->bytes(1572864));
        $eq('duration', [$de->duration(0), $de->duration(185), $de->duration(3599), $de->duration(3661), $de->duration(185, false), $de->duration(3661, false), $de->duration(-1)],
            ['0:00 min', '3:05 min', '59:59 min', '1:01:01 h', '3:05', '1:01:01', '']);
        $eq('MediaJobs::duration = duration()', AI\MediaJobs::duration(3661), Format::admin()->duration(3661));

        // Text
        $eq('host', [$de->host('https://www.Example.org/a?b'), $de->host('example.org/pfad'), $de->host('mailto:x'), $de->host('')], ['example.org', 'example.org', '', '']);
        $long = 'Ein ganz normaler Satz mit einigen Wörtern, der lang genug ist, um gekürzt zu werden – oder?';
        $eq('excerpt: Wortgrenze, „ …“', [$de->excerpt($long, 40), $de->excerpt('kurz', 40), $de->excerpt($long, 0) === $long], ['Ein ganz normaler Satz mit einigen …', 'kurz', true]);
        $eq('excerpt: HTML → Text', $de->excerpt('<p>Erster&nbsp;Absatz</p><p>Zweiter <b>Absatz</b> &amp; mehr</p>', 200), 'Erster Absatz Zweiter Absatz & mehr');
        $eq('excerpt: reiner Text ($html = false) bleibt (<b>, &amp;)', $de->excerpt('Zeile 2 <b> &amp; c', 50, false), 'Zeile 2 <b> &amp; c');
        $eq('Clamp::excerpt = excerpt(…, false)', [Data\Clamp::excerpt("Zeile\n\n  mit   Leerraum", 10), Data\Clamp::excerpt('a <b>', 20)], ['Zeile mit …', 'a <b>']);
        $eq('phone: Standardsprache wie eingegeben', Format::for(Lang::default())->phone('0211 123 45'), '0211 123 45');

        // Helfer
        $eq('fmt() und date_local()', [fmt('de')->date($ts), date_local($ts, 'long') === Format::for()->date($ts, 'long'), fmt() === Format::for()], ['24.09.2026', true, true]);
        $eq('Format::for normalisiert (de-DE → de)', Format::for('de-DE') === $de, true);
        return ['ok' => $ok, 'fails' => $fails];
    }
}
