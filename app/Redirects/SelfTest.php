<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Redirects;

/**
 * Selbsttest der Weiterleitungen (php bin/console redirects:selftest): Normalisieren, Vergleichsschlüssel, Auswahl der Regel
 * (exakt vor Platzhalter, längster Präfix), Rest für „*“, Query, Location-Kodierung, Import-Formate. Ohne Datenbank.
 */
final class SelfTest
{
    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };

        // Normalisieren
        $eq('Domain entfernt', Redirects::normalize('https://www.example.de/Alte-Seite/?a=1#x'), '/Alte-Seite/?a=1');
        $eq('Protokoll-relativ', Redirects::normalize('//example.de/x'), '/x');
        $eq('führender Schrägstrich', Redirects::normalize('alte-seite'), '/alte-seite');
        $eq('doppelte Schrägstriche', Redirects::normalize('//a//b/'), '/b/');   // „//a“ ist eine Domain
        $eq('doppelte Schrägstriche im Pfad', Redirects::normalize('/a//b///c'), '/a/b/c');
        $eq('Prozent-Kodierung', Redirects::normalize('/agentur/m%C3%BCntel/'), '/agentur/müntel/');
        $eq('UTF-8 bleibt', Redirects::normalize('/agentur/müntel/'), '/agentur/müntel/');
        $eq('ungültige Kodierung bleibt', Redirects::normalize('/x%FF'), '/x%FF');
        $eq('leer', Redirects::normalize('   '), '');
        $eq('Fragment weg', Redirects::normalize('/seite#anker'), '/seite');

        // Vergleichsschlüssel
        $eq('Schlüssel: Schrägstrich', Redirects::key('/Alte-Seite/'), '/alte-seite');
        $eq('Schlüssel: Wurzel', Redirects::key('/'), '/');
        $eq('Schlüssel: Umlaut bleibt', Redirects::key('/MÜNTEL/'), '/mÜntel');
        $eq('Schlüssel: Query', Redirects::key('/Index.php?Article_ID=5'), '/index.php?article_id=5');
        $eq('Platzhalter erkannt', Redirects::isWildcard('/blog/*'), true);
        $eq('kein Platzhalter mit Query', Redirects::isWildcard('/a?x=*'), false);

        // Auswahl
        $rules = [
            ['id' => 1, 'source' => '/alte-seite/', 'wildcard' => 0, 'active' => 1],
            ['id' => 2, 'source' => '/blog/*', 'wildcard' => 1, 'active' => 1],
            ['id' => 3, 'source' => '/blog/archiv/*', 'wildcard' => 1, 'active' => 1],
            ['id' => 4, 'source' => '/blog/sonderfall', 'wildcard' => 0, 'active' => 1],
            ['id' => 5, 'source' => '/aus', 'wildcard' => 0, 'active' => 0],
            ['id' => 6, 'source' => '/agentur/m%C3%BCntel/', 'wildcard' => 0, 'active' => 1],
            ['id' => 7, 'source' => '/index.php?article_id=5', 'wildcard' => 0, 'active' => 1],
            ['id' => 8, 'source' => '/shop*', 'wildcard' => 1, 'active' => 1],
        ];
        $id = fn(?array $p) => $p ? (int) $p[0]['id'] : null;
        $rest = fn(?array $p) => $p ? $p[1] : null;
        $eq('exakt', $id(Redirects::pick($rules, '/alte-seite')), 1);
        $eq('exakt, Groß/klein + Schrägstrich', $id(Redirects::pick($rules, '/ALTE-SEITE/')), 1);
        $eq('exakt vor Platzhalter', $id(Redirects::pick($rules, '/blog/sonderfall/')), 4);
        $eq('Platzhalter', $id(Redirects::pick($rules, '/blog/2024/beitrag')), 2);
        $eq('Platzhalter: Rest', $rest(Redirects::pick($rules, '/blog/2024/beitrag')), '2024/beitrag');
        $eq('Platzhalter: Rest mit Schrägstrich', $rest(Redirects::pick($rules, '/blog/2024/beitrag/')), '2024/beitrag/');
        $eq('Platzhalter: Rest behält Schreibweise', $rest(Redirects::pick($rules, '/BLOG/Neu')), 'Neu');
        $eq('längster Präfix', $id(Redirects::pick($rules, '/blog/archiv/2019')), 3);
        $eq('Platzhalter: Ordner selbst', $id(Redirects::pick($rules, '/blog')), 2);
        $eq('Präfix ohne Schrägstrich', $id(Redirects::pick($rules, '/shopping/x')), 8);
        $eq('Präfix ohne Schrägstrich: Rest', $rest(Redirects::pick($rules, '/shopping/x')), 'ping/x');
        $eq('inaktiv', $id(Redirects::pick($rules, '/aus')), null);
        $eq('kodiert gespeichert, UTF-8 angefragt', $id(Redirects::pick($rules, Redirects::normalize('/agentur/müntel'))), 6);
        $eq('Query-Quelle', $id(Redirects::pick($rules, '/index.php', 'article_id=5')), 7);
        $eq('Query-Quelle: andere Query', $id(Redirects::pick($rules, '/index.php', 'article_id=6')), null);
        $eq('keine Regel', $id(Redirects::pick($rules, '/gibt-es-nicht')), null);
        $eq('ähnlicher Präfix greift nicht', $id(Redirects::pick($rules, '/blogger')), null);

        // Query anhängen, Location kodieren, Zielart
        $eq('Query anhängen', Redirects::appendQuery('/neu', 'utm=1'), '/neu?utm=1');
        $eq('Query ergänzen', Redirects::appendQuery('/neu?a=1', 'utm=1'), '/neu?a=1&utm=1');
        $eq('Query vor Anker', Redirects::appendQuery('/neu#teil', 'utm=1'), '/neu?utm=1#teil');
        $eq('Location kodiert', Redirects::encodeLocation('/agentur/müntel/ x'), '/agentur/m%C3%BCntel/%20x');
        $eq('Ziel: Seite', Redirects::targetKind('page:12#anker'), 'ref');
        $eq('Ziel: URL', Redirects::targetKind('https://example.de/x'), 'url');
        $eq('Ziel: Pfad', Redirects::targetKind('/neu/'), 'path');
        $eq('Ziel: ungültig', Redirects::targetKind('javascript:alert(1)'), '');
        $eq('Ziel: protokoll-relativ ungültig', Redirects::targetKind('//evil.example'), '');

        // Import-Formate
        $j = Redirects::parse('[{"from":"/agentur/referenzen/referenz/foo-12/","to":"/arbeiten/foo"}]');
        $eq('JSON from/to', [$j[0]['source'] ?? null, $j[0]['target'] ?? null], ['/agentur/referenzen/referenz/foo-12/', '/arbeiten/foo']);
        $m = Redirects::parse('{"/a": "/b"}');
        $eq('JSON-Objekt', [$m[0]['source'] ?? null, $m[0]['target'] ?? null], ['/a', '/b']);
        $c = Redirects::parse("source;target;code;note\n/alt;/neu;302;Umzug\n\n# Kommentar\n/weg;;410;\n");
        $eq('CSV mit Kopf', count($c), 2);
        $eq('CSV Werte', [$c[0]['source'], $c[0]['target'], (int) $c[0]['code'], $c[0]['note']], ['/alt', '/neu', 302, 'Umzug']);
        $eq('CSV 410', (int) $c[1]['code'], 410);
        $k = Redirects::parse("/a,/b\n/c,/d");
        $eq('CSV Komma ohne Kopf', [count($k), $k[1]['target'] ?? null], [2, '/d']);

        return ['ok' => $ok, 'fails' => $fails];
    }
}
