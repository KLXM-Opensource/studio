<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Glossary;

/**
 * php bin/console glossary:selftest [--bench] – Markierung ohne Datenbank prüfen: Wortgrenzen, Groß-/Kleinschreibung,
 * Umlaute, Abkürzungen, längste Variante, erstes Vorkommen (Seite/Abschnitt), Ausnahmen, Escaping, keine Doppel-Markierung;
 * geteiltes Glossar: Vergleichsform und Doppel-Erkennung (Sharing). Ende-zu-Ende mit zwei Websites: glossary:sharetest (ShareTest).
 */
final class SelfTest
{
    private static int $ok = 0;
    private static array $fail = [];

    public static function terms(): array
    {
        $t = fn(int $id, string $term, array $v, string $short = 'Erklärung.') => ['id' => $id, 'key' => \Core\Pages::slugify($term), 'term' => $term,
            'short' => $short, 'url' => '/glossar/' . \Core\Pages::slugify($term), 'variants' => $v];
        return [
            $t(1, 'SPF', ['Sender Policy Framework'], 'Legt im DNS fest, welche Server Mails senden dürfen.'),
            $t(2, 'DNS', ['Domain Name System']),
            $t(3, 'DNSSEC', []),
            $t(4, 'Barrierefreiheit', ['barrierefrei']),
            $t(5, 'MX-Eintrag', ['MX']),
            $t(6, 'TLS', ['Transport Layer Security']),
            $t(7, 'TLS-RPT', []),
            $t(8, 'Zertifikat', ['SSL-Zertifikat']),
            $t(9, 'Übertragung', []),
            $t(10, 'IPv6', []),
            $t(11, 'Cookie', ['"Cookie"']),
            $t(12, 'CDN', ['Content Delivery Network']),
            $t(13, 'XSS', [], 'Gefährlich: <script>alert("x")</script> & mehr'),
            $t(14, 'Leer', [], ''),
            $t(15, 'HTTP/2', []),
            $t(16, 'Straße', []),
            $t(17, 'DNS-Lookup', []),
        ];
    }

    private static function eq(string $name, mixed $got, mixed $want): void
    {
        if ($got === $want) { self::$ok++; return; }
        self::$fail[] = $name . ': erwartet ' . json_encode($want, JSON_UNESCAPED_UNICODE) . ', erhalten ' . json_encode($got, JSON_UNESCAPED_UNICODE);
    }

    /** Markierte Begriffe (Text der Buttons) */
    private static function hits(string $html): array
    {
        preg_match_all('~<button type="button" class="gl-term"[^>]*>(.*?)</button>~s', $html, $m);
        return $m[1];
    }

    public static function run(bool $bench = false): int
    {
        self::$ok = 0;
        self::$fail = [];
        $T = self::terms();
        $a = new Annotator($T);
        $mk = fn(string $body) => '<!doctype html><html><head><title>SPF</title><meta name="description" content="SPF und DNS"></head><body>' . $body . '</body></html>';
        $hits = fn(string $body, array $o = []) => self::hits((new Annotator($T, $o))->annotate($mk($body)));

        // Grundregeln
        self::eq('einfach', $hits('<main><p>SPF und DNS prüfen.</p></main>'), ['SPF', 'DNS']);
        self::eq('nach HTML-Kommentaren (Fragmente im Debug-Modus)', $hits('<!-- fragment header --><header>x</header><main><!-- a --><p>SPF <!-- b --> und DNS</p><script>SPF</script><p>TLS</p></main>'), ['SPF', 'DNS', 'TLS']);
        self::eq('erstes Vorkommen je Seite', $hits('<main><p>SPF, SPF</p><section><p>SPF</p></section></main>'), ['SPF']);
        self::eq('je Abschnitt', $hits('<main><section><p>SPF und SPF</p></section><section><p>SPF</p></section></main>', ['mode' => 'section']), ['SPF', 'SPF']);
        self::eq('Abkürzung: genaue Schreibweise', $hits('<main><p>spf und Spf</p></main>'), []);
        self::eq('Wort: Groß/klein egal', $hits('<main><p>Mehr barrierefreiheit!</p></main>'), ['barrierefreiheit']);
        self::eq('Wort: Endung', $hits('<main><p>Zwei Zertifikate</p></main>'), ['Zertifikate']);
        self::eq('Umlaut groß', $hits('<main><p>ÜBERTRAGUNG läuft</p></main>'), ['ÜBERTRAGUNG']);
        self::eq('Umlaut Endung', $hits('<main><p>Viele Übertragungen</p></main>'), ['Übertragungen']);
        self::eq('ß', $hits('<main><p>In der Straße</p></main>'), ['Straße']);
        self::eq('Wortgrenze', $hits('<main><p>SPFX, MXR, aDNS, Zertifikatsstelle</p></main>'), []);
        self::eq('längste zuerst', $hits('<main><p>DNSSEC und TLS-RPT</p></main>'), ['DNSSEC', 'TLS-RPT']);
        self::eq('Bindestrich-Wort', $hits('<main><p>Der DNS-Eintrag</p></main>'), ['DNS']);
        self::eq('Plural-s Abkürzung', $hits('<main><p>Mehrere CDNs</p></main>'), ['CDNs']);
        self::eq('IPv6', $hits('<main><p>Nur IPv6.</p></main>'), ['IPv6']);
        self::eq('IPv6 nicht in ipv6', $hits('<main><p>ipv6</p></main>'), []);
        self::eq('Anführungszeichen = genau', $hits('<main><p>cookie</p></main>'), []);
        self::eq('Anführungszeichen ok', $hits('<main><p>Cookie</p></main>'), ['Cookie']);
        self::eq('Mehrwort mit nbsp', $hits('<main><p>Sender&nbsp;Policy Framework</p></main>'), ['Sender&nbsp;Policy Framework']);
        self::eq('Mehrwort Zeilenumbruch', $hits("<main><p>Domain\n  Name System</p></main>"), ["Domain\n  Name System"]);
        self::eq('Schrägstrich', $hits('<main><p>HTTP/2 ist schnell</p></main>'), ['HTTP/2']);
        self::eq('Adresse/Domain', $hits('<main><p>spf.example.org und mail@SPF.de und /SPF/</p></main>'), []);
        self::eq('Endung nach Abkürzung', $hits('<main><p>Höchstens 10 DNS-Lookups.</p></main>'), ['DNS-Lookups']);
        self::eq('Zuweisung (=)', $hits('<main><p>Kopf: s=SPF und SPF=1</p></main>'), []);
        self::eq('Satzzeichen', $hits('<main><p>(SPF).</p></main>'), ['SPF']);
        self::eq('ohne Kurz-Erklärung', $hits('<main><p>Leer</p></main>'), []);

        // Englische Seite (Option lang): Endungen -s/-es, y → ies; keine deutschen Endungen
        $E = [['id' => 51, 'key' => 'data-table', 'term' => 'data table', 'short' => 'Structured content.', 'url' => '/en/glossar/data-table', 'variants' => []],
            ['id' => 52, 'key' => 'block', 'term' => 'Block', 'short' => 'Building block of a page.', 'url' => null, 'variants' => []],
            ['id' => 53, 'key' => 'repository', 'term' => 'repository', 'short' => 'Code storage.', 'url' => null, 'variants' => []],
            ['id' => 54, 'key' => 'api', 'term' => 'API', 'short' => 'Interface.', 'url' => null, 'variants' => []],
            ['id' => 55, 'key' => 'passkey', 'term' => 'passkey', 'short' => 'Login without password.', 'url' => null, 'variants' => []]];
        $en = fn(string $body) => self::hits((new Annotator($E, ['lang' => 'en', 'mode' => 'section']))->annotate($mk($body)));
        self::eq('en: Plural -s', $en('<main><p>Two data tables</p></main>'), ['data tables']);
        self::eq('en: y → ies', $en('<main><p>Both repositories</p></main>'), ['repositories']);
        self::eq('en: Endung -es', $en('<main><p>Two passkeys and boxes</p></main>'), ['passkeys']);
        self::eq('en: keine deutschen Endungen', $en('<main><p>A blocker, passkeyer, repositoryes</p></main>'), []);
        self::eq('en: Abkürzung Plural', $en('<main><p>Two APIs</p></main>'), ['APIs']);
        self::eq('en-gb wie en', Annotator::english('en-gb') && !Annotator::english('de') && !Annotator::english('eo'), true);
        self::eq('de bleibt: Blocker', self::hits((new Annotator($E))->annotate($mk('<main><p>Der Blocker</p></main>'))), ['Blocker']);
        self::eq('en: Muster mit lang', (bool) preg_match((string) Annotator::pattern($E, 'en'), 'repositories'), true);
        self::eq('de: Muster ohne ies', (bool) preg_match((string) Annotator::pattern($E, 'de'), 'repositories'), false);
        // Prüfungen je Sprache: gleiche Variante auf Deutsch und Englisch ist kein Doppel, in derselben Sprache schon
        $ck = fn(array $terms) => count(array_filter(Glossary::checks($terms), fn($c) => $c['level'] === 'warn'));
        self::eq('Prüfung: API de + en kein Doppel', $ck([['id' => 1, 'term' => 'API', 'short' => 'x', 'variants' => [], 'lang' => 'de'],
            ['id' => 2, 'term' => 'API', 'short' => 'x', 'variants' => [], 'lang' => 'en']]), 0);
        self::eq('Prüfung: API zweimal de = Doppel', $ck([['id' => 1, 'term' => 'API', 'short' => 'x', 'variants' => [], 'lang' => 'de'],
            ['id' => 2, 'term' => 'API', 'short' => 'x', 'variants' => [], 'lang' => 'de']]), 1);

        // Ausnahmen
        $skip = [
            'Link' => '<a href="/x">SPF</a>', 'Button' => '<button>SPF</button>', 'Code' => '<code>SPF</code>', 'Pre' => '<pre>SPF</pre>',
            'h1' => '<h1>SPF</h1>', 'h3' => '<h3>SPF</h3>', 'nav' => '<nav><span>SPF</span></nav>', 'data-glossary' => '<div data-glossary="off"><p>SPF</p></div>',
            'textarea' => '<textarea>SPF</textarea>', 'script' => '<script>var a="SPF";</script>', 'hidden' => '<p hidden>SPF</p>',
            'aria-hidden' => '<p aria-hidden="true">SPF</p>', 'contenteditable' => '<div contenteditable="false">SPF</div>', 'sr-only' => '<span class="x sr-only">SPF</span>',
            'label' => '<label>SPF <input></label>', 'summary' => '<details><summary>SPF</summary></details>', 'kbd' => '<kbd>SPF</kbd>', 'abbr' => '<abbr title="x">SPF</abbr>',
            'role=button' => '<div role="button">SPF</div>', 'Redaktionsnotiz' => '<span class="cms-note">SPF</span>', 'select' => '<select><option>SPF</option></select>',
            'Dachzeile' => '<p class="cli-eyebrow">SPF</p>', 'Schlagwort' => '<span class="card__tag">SPF</span>',
            'Attribut' => '<p title="SPF und DNS" data-x="a > SPF">x</p>', 'svg' => '<svg><text>SPF</text></svg>',
        ];
        foreach ($skip as $name => $html) self::eq('übersprungen: ' . $name, $hits('<main>' . $html . '</main>'), []);
        self::eq('h4 erlaubt', $hits('<main><h4>SPF</h4></main>'), ['SPF']);
        self::eq('h3 erlaubt mit headings=2', $hits('<main><h3>SPF</h3></main>', ['headings' => 2]), ['SPF']);
        self::eq('nach Ausnahme weiter', $hits('<main><a href="#">SPF</a> und SPF</main>'), ['SPF']);
        self::eq('Kopf außerhalb main', $hits('<header>SPF</header><main><p>DNS</p></main><footer>Cookie</footer>'), ['DNS']);
        self::eq('header im Abschnitt', $hits('<main><section><header><p>SPF</p></header></section></main>'), ['SPF']);
        self::eq('ohne main: footer übersprungen', $hits('<div><p>SPF</p></div><footer>DNS</footer>'), ['SPF']);
        self::eq('offenes <p>', $hits('<main><p>Text<p>SPF<br>DNS</main>'), ['SPF', 'DNS']);
        self::eq('ausgenommener Begriff', $hits('<main><p>SPF und DNS</p></main>', ['exclude' => [1]]), ['DNS']);

        // Kopf und Daten bleiben unberührt; Escaping; keine Doppel-Markierung
        $page = $mk('<main><p>XSS und SPF, später SPF</p><script type="application/ld+json">{"name":"SPF"}</script></main>');
        $out = $a->annotate($page);
        self::eq('Kopf unberührt', str_contains($out, '<title>SPF</title><meta name="description" content="SPF und DNS">'), true);
        self::eq('JSON-LD unberührt', str_contains($out, '{"name":"SPF"}'), true);
        self::eq('Erklärung escaped', str_contains($out, '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; mehr') && !str_contains($out, '<script>alert'), true);
        self::eq('Anzahl', $a->count, 2);
        self::eq('Markup', str_contains($out, '<button type="button" class="gl-term" popovertarget="gl-2" aria-expanded="false" aria-controls="gl-2">SPF</button><span class="gl-pop" id="gl-2" popover>'), true);
        self::eq('Link zum Glossar', str_contains($out, '<a class="gl-pop__more" href="/glossar/spf">'), true);
        $short = (new Annotator(array_map(fn($x) => ['more' => false] + $x, $T)))->annotate($page);
        self::eq('Kein Link, wenn die Bubble alles zeigt', str_contains($short, 'gl-pop__more'), false);
        $src = (new Annotator(array_map(fn($x) => ['more' => false, 'link' => 'https://www.example.org/spf'] + $x, $T)))->annotate($page);
        self::eq('Nur Quelle: Link direkt zur Quelle', str_contains($src, 'class="gl-pop__more gl-pop__src" href="https://www.example.org/spf" target="_blank" rel="noopener">example.org'), true);
        self::eq('Nur Quelle: kein Link ins Glossar', str_contains($src, 'href="/glossar/spf"'), false);
        $again = (new Annotator($T))->annotate($out);
        self::eq('zweimal = einmal', $again, $out);
        self::eq('ohne Begriffe unverändert', (new Annotator([]))->annotate($page), $page);
        self::eq('nichts gefunden unverändert', $a->annotate($mk('<main><p>Nichts.</p></main>')), $mk('<main><p>Nichts.</p></main>'));

        // Hilfsfunktionen
        self::eq('caseSensitive SPF', Annotator::caseSensitive('SPF'), true);
        self::eq('caseSensitive MTA-STS', Annotator::caseSensitive('MTA-STS'), true);
        self::eq('caseSensitive Sender Policy Framework', Annotator::caseSensitive('Sender Policy Framework'), false);
        self::eq('caseSensitive eID', Annotator::caseSensitive('eID'), true);
        self::eq('Varianten', Glossary::splitVariants("SPF-Eintrag\nSender Policy Framework; spf1, SPF-Eintrag"), ['SPF-Eintrag', 'Sender Policy Framework', 'spf1']);
        self::eq('Kurz kürzen', mb_strlen(Glossary::short(str_repeat('Wort ', 80))) <= Glossary::SHORT_MAX, true);
        self::eq('Kurz ohne HTML', Glossary::short('<p>Hallo&nbsp;<b>Welt</b></p>'), 'Hallo Welt');
        self::eq('Pfad genau', Glossary::excluded('/impressum/', "/impressum\n/blog/*"), true);
        self::eq('Pfad Präfix', Glossary::excluded('/blog/2026/x', "/impressum\n/blog/*"), true);
        self::eq('Pfad nicht', Glossary::excluded('/blogger', "/blog/*\n/impressum"), false);

        // Geteiltes Glossar (Sharing): Vergleichsform und doppelte Begriffe über Websites (ohne Datenbank)
        self::eq('Teilen: Vergleich ohne Groß/klein', Sharing::norm('SPF') === Sharing::norm('spf'), true);
        self::eq('Teilen: Vergleich ohne Akzente/Umlaute', [Sharing::norm('Übertragung'), Sharing::norm('Café')], [Sharing::norm('ubertragung'), 'cafe']);
        self::eq('Teilen: ß und Bindestrich', [Sharing::norm('Straße'), Sharing::norm('E-Mail')], ['strasse', 'email']);
        $mine = [['id' => 1, 'term' => 'spf', 'variants' => [], 'lang' => 'de'], ['id' => 2, 'term' => 'Zertifikat', 'variants' => ['TLS-Zertifikat'], 'lang' => 'de'],
            ['id' => 3, 'term' => 'Neu', 'variants' => [], 'lang' => 'de'], ['id' => 4, 'term' => 'API', 'variants' => [], 'lang' => 'en']];
        $theirs = [['id' => 11, 'term' => 'SPF', 'variants' => ['Sender Policy Framework'], 'lang' => 'de'], ['id' => 12, 'term' => 'TLS Zertifikat', 'variants' => [], 'lang' => 'de'],
            ['id' => 13, 'term' => 'API', 'variants' => [], 'lang' => 'de'], ['id' => 14, 'term' => 'Sender-Policy-Framework', 'variants' => [], 'lang' => 'de']];
        $dupes = Sharing::duplicates($mine, $theirs);
        self::eq('Teilen: Doppel gefunden (Begriff, Variante)', array_map(fn($d) => [$d['term']['id'], array_column($d['matches'], 'id')], $dupes), [[1, [11]], [2, [12]]]);
        self::eq('Teilen: Doppel je Sprache (API de ≠ en)', in_array(4, array_map(fn($d) => $d['term']['id'], $dupes), true), false);
        self::eq('Teilen: Variante ↔ Begriff', array_column(Sharing::duplicates([['id' => 5, 'term' => 'x', 'variants' => ['Sender Policy Framework'], 'lang' => 'de']], $theirs)[0]['matches'] ?? [], 'id'), [11, 14]);
        self::eq('Teilen: zu kurz zählt nicht', Sharing::duplicates([['id' => 6, 'term' => 'A', 'variants' => [], 'lang' => 'de']], [['id' => 7, 'term' => 'a', 'variants' => [], 'lang' => 'de']]), []);

        // Quick-Glossar (QuickTool): Treffer „Auf dieser Seite“ ohne Datenbank
        $h = fn(string $html, array $s = [], int $self = 0) => QuickTool::hits($T, $html, $s + ['mode' => 'page', 'headings' => 3], 'de', $self);
        self::eq('Quick: Treffer mit Text wie im Inhalt', $h('<p>Der SPF steht im DNS. Wir sind barrierefrei.</p>'), [1 => 'SPF', 2 => 'DNS', 4 => 'barrierefrei']);
        self::eq('Quick: contenteditable zählt als Text', $h('<div contenteditable="true"><p>Ein Zertifikat</p></div>'), [8 => 'Zertifikat']);
        self::eq('Quick: Überschrift bis h3 und Links nicht', $h('<h2>SPF</h2><p><a href="/x">DNS</a></p>'), []);
        self::eq('Quick: Überschriften-Einstellung 0 → markiert', $h('<h2>SPF</h2>', ['headings' => 0]), [1 => 'SPF']);
        self::eq('Quick: eigener Begriff ausgenommen', $h('<p>SPF und DNS</p>', [], 1), [2 => 'DNS']);
        self::eq('Quick: nur erstes Vorkommen', $h('<p>DNS</p><p>Domain Name System</p>'), [2 => 'DNS']);
        $db = QuickTool::selftestDb(fn(string $n, mixed $g, mixed $w) => self::eq('Quick (DB): ' . $n, $g, $w));
        if (!$db) echo "  Hinweis: Glossar nicht eingerichtet – Endpunkte des Quick-Glossars nicht mit Datenbank geprüft.\n";

        foreach (self::$fail as $f) echo "  FEHLER: $f\n";
        echo '  ' . self::$ok . ' Prüfungen bestanden, ' . count(self::$fail) . " fehlgeschlagen\n";
        if ($bench) self::bench();
        return self::$fail ? 1 : 0;
    }

    /** Laufzeit: 300 Begriffe auf einer großen Seite (≈ 400 KB, 2.000 Absätze) */
    private static function bench(): void
    {
        $terms = self::terms();
        $words = ['Lorem', 'ipsum', 'dolor', 'Netzwerk', 'Server', 'Anfrage', 'Konfiguration', 'Sicherheit', 'Daten', 'Website'];
        for ($i = 100; $i < 400; $i++) {
            $terms[] = ['id' => $i, 'key' => 'b' . $i, 'term' => 'Begriff' . $i, 'short' => 'Erklärung ' . $i, 'url' => null, 'variants' => ['Fachwort' . $i, 'FW' . $i]];
        }
        $body = '<header><nav><a href="/">Start</a></nav></header><main>';
        for ($p = 0; $p < 2000; $p++) {
            $s = [];
            for ($w = 0; $w < 25; $w++) $s[] = $words[($p * 7 + $w * 3) % count($words)];
            if ($p % 9 === 0) $s[] = 'Begriff' . (100 + $p % 300);
            if ($p % 13 === 0) $s[] = 'SPF';
            $body .= '<section><h3>Abschnitt ' . $p . '</h3><p>' . implode(' ', $s) . ' <a href="/x">Link</a> <strong>fett</strong>.</p></section>';
        }
        $html = '<!doctype html><html><head><title>x</title></head><body>' . $body . '</main></body></html>';
        $t = hrtime(true);
        $a = new Annotator($terms);
        $out = $a->annotate($html);
        $ms = (hrtime(true) - $t) / 1e6;
        printf("  Laufzeit: %.1f ms für %d KB, %d Begriffe, %d Markierungen (Ausgabe %d KB)\n", $ms, strlen($html) / 1024, count($terms), $a->count, strlen($out) / 1024);
    }
}
