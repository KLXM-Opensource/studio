<?php
declare(strict_types=1);

namespace Core\Sources;

/**
 * Formate externer Quellen → Liste gleichartiger Einträge (verschachtelte Arrays), dazu Pfad-Zugriff für die Zuordnung.
 *
 * XML (RSS, Atom, generisch, OpenImmo) wird zu Arrays: Element → Text (ohne Attribute/Kinder) bzw.
 * ['@attribut' => …, 'kind' => …, '#text' => …]; mehrfach vorkommende Kinder werden Listen. Präfixe bleiben
 * im Namen (media:content, dc:creator, content:encoded).
 *
 * XML-Sicherheit (XXE): Dokumente mit <!ENTITY werden abgelehnt, geladen wird ohne Netzwerk (LIBXML_NONET), ohne
 * Entity-Ersetzung und ohne DTD-Laden; externe Entities liefert ein leerer Loader. Keine XInclude-Verarbeitung.
 *
 * Pfade (Zuordnung): Segmente mit Punkt, z. B. „channel.item“, „enclosure@url“, „link[@rel=alternate]@href“,
 * „anhaenge.anhang[0].daten.pfad“, „data.items[*]“, „#text“. JSON-Pfade dürfen mit „$.“ beginnen.
 */
final class Parser
{
    public const MAX_ITEMS = 5000;

    /**
     * @param array $opt items_path (JSON), xpath (XML), max (Anzahl)
     * @return array{items: list<array>, meta: array}
     * @throws SourceException
     */
    public static function parse(string $body, string $format, array $opt = []): array
    {
        $max = max(1, min(self::MAX_ITEMS, (int) ($opt['max'] ?? 500)));
        $body = self::stripBom($body);
        if (trim($body) === '') throw new SourceException(__('Die Quelle hat keine Daten geliefert (leere Antwort).'));
        $out = match ($format) {
            'json' => self::json($body, (string) ($opt['items_path'] ?? '')),
            'rss', 'atom' => self::feed($body),
            'xml' => self::xml($body, (string) ($opt['xpath'] ?? '')),
            'openimmo' => OpenImmo::parse($body),
            default => throw new SourceException(__('Unbekanntes Format.')),
        };
        $out['meta']['total'] = count($out['items']);
        $out['items'] = array_slice(array_values(array_filter($out['items'], 'is_array')), 0, $max);
        return $out;
    }

    private static function stripBom(string $s): string
    {
        return str_starts_with($s, "\xEF\xBB\xBF") ? substr($s, 3) : $s;
    }

    // ------------------------------------------------------------------ JSON

    private static function json(string $body, string $path): array
    {
        try {
            $data = json_decode($body, true, 128, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new SourceException(__('Kein gültiges JSON: {error}', ['error' => $e->getMessage()]));
        }
        $path = trim($path);
        if ($path !== '' && $path !== '$') {
            $items = self::get($data, preg_replace('~\[\*\]$~', '', $path));
            if ($items === null) throw new SourceException(__('Pfad „{path}“ nicht gefunden.', ['path' => $path]));
        } else {
            $items = is_array($data) && array_is_list($data) ? $data : self::firstList($data);
        }
        if (!is_array($items)) throw new SourceException(__('Unter dem Pfad liegt keine Liste von Einträgen.'));
        if (!array_is_list($items)) $items = [$items];
        $items = array_map(fn($i) => is_array($i) ? $i : ['value' => $i], $items);
        return ['items' => $items, 'meta' => ['format' => 'json']];
    }

    /** Erste Liste von Objekten in einem JSON-Dokument (Breitensuche, z. B. {"data": {"items": [...]}}) */
    private static function firstList(mixed $data): ?array
    {
        $queue = [$data];
        while ($queue) {
            $cur = array_shift($queue);
            if (!is_array($cur)) continue;
            if (array_is_list($cur) && $cur && is_array($cur[0])) return $cur;
            foreach ($cur as $v) if (is_array($v)) $queue[] = $v;
        }
        return is_array($data) ? [$data] : null;
    }

    // ------------------------------------------------------------------ XML

    /** XML sicher laden (XXE, Entity-Expansion, Netzwerk) @throws SourceException */
    public static function xmlDoc(string $body): \DOMDocument
    {
        if (preg_match('~<!ENTITY~i', $body)) {
            throw new SourceException(__('XML mit eigenen Entities (<!ENTITY …>) wird aus Sicherheitsgründen nicht gelesen.'));
        }
        $prevErr = libxml_use_internal_errors(true);
        libxml_clear_errors();
        libxml_set_external_entity_loader(static fn() => null);
        try {
            $doc = new \DOMDocument();
            $ok = $doc->loadXML($body, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
            $err = libxml_get_errors()[0] ?? null;
        } finally {
            libxml_set_external_entity_loader(null);
            libxml_clear_errors();
            libxml_use_internal_errors($prevErr);
        }
        if (!$ok || !$doc->documentElement) {
            throw new SourceException(__('Kein gültiges XML{detail}', ['detail' => $err ? ': ' . trim($err->message) . ' (Zeile ' . $err->line . ')' : '.']));
        }
        if ($doc->doctype && ($doc->doctype->entities->length > 0 || $doc->doctype->internalSubset)) {
            throw new SourceException(__('XML mit eigenen Entities (<!ENTITY …>) wird aus Sicherheitsgründen nicht gelesen.'));
        }
        return $doc;
    }

    /** RSS 2.0, RSS 1.0 (RDF) und Atom */
    private static function feed(string $body): array
    {
        $doc = self::xmlDoc($body);
        $root = $doc->documentElement;
        $name = strtolower($root->localName);
        $items = [];
        $meta = ['format' => $name === 'feed' ? 'atom' : 'rss'];
        if ($name === 'rss') {
            foreach ($root->childNodes as $ch) {
                if ($ch instanceof \DOMElement && $ch->localName === 'channel') {
                    $meta['title'] = self::text($ch, 'title');
                    foreach ($ch->childNodes as $it) if ($it instanceof \DOMElement && $it->localName === 'item') $items[] = self::node($it);
                }
            }
        } elseif ($name === 'feed') {
            $meta['title'] = self::text($root, 'title');
            foreach ($root->childNodes as $it) if ($it instanceof \DOMElement && $it->localName === 'entry') $items[] = self::node($it);
        } elseif ($name === 'rdf') {
            foreach ($root->childNodes as $it) if ($it instanceof \DOMElement && $it->localName === 'item') $items[] = self::node($it);
        } else {
            throw new SourceException(__('Kein RSS- oder Atom-Feed (Wurzelelement „{name}“).', ['name' => $root->nodeName]));
        }
        return ['items' => array_map(fn($i) => is_array($i) ? $i : ['#text' => $i], $items), 'meta' => $meta];
    }

    /** Beliebiges XML: Einträge per XPath (Standard: Kinder des Wurzelelements) */
    private static function xml(string $body, string $xpath): array
    {
        // Standard-Namensraum entfernen, damit einfache XPath-Ausdrücke wie //produkt greifen
        $body = (string) preg_replace('~(<[^!?][^>]*?)\sxmlns="[^"]*"~', '$1', $body, 50);
        $doc = self::xmlDoc($body);
        $xp = new \DOMXPath($doc);
        $xpath = trim($xpath);
        if ($xpath === '') {
            $nodes = [];
            foreach ($doc->documentElement->childNodes as $n) if ($n instanceof \DOMElement) $nodes[] = $n;
        } else {
            if (preg_match('~(document|php:|unparsed-entity)~i', $xpath)) throw new SourceException(__('Dieser XPath-Ausdruck ist nicht erlaubt.'));
            $res = @$xp->query($xpath);
            if ($res === false) throw new SourceException(__('Ungültiger XPath-Ausdruck „{xpath}“.', ['xpath' => $xpath]));
            $nodes = iterator_to_array($res);
        }
        $items = [];
        foreach ($nodes as $n) {
            if (!$n instanceof \DOMElement) continue;
            $v = self::node($n);
            $items[] = is_array($v) ? $v : ['#text' => $v];
        }
        return ['items' => $items, 'meta' => ['format' => 'xml', 'root' => $doc->documentElement->nodeName]];
    }

    private static function text(\DOMElement $el, string $child): string
    {
        foreach ($el->childNodes as $c) if ($c instanceof \DOMElement && $c->localName === $child) return trim($c->textContent);
        return '';
    }

    /** Element → Array/Text (siehe Klassenbeschreibung) */
    public static function node(\DOMElement $el, int $depth = 0): array|string
    {
        $out = [];
        foreach ($el->attributes ?? [] as $a) $out['@' . $a->nodeName] = $a->value;
        // XHTML-Inhalt (Atom type="xhtml"): als HTML-Text übernehmen
        if ($el->getAttribute('type') === 'xhtml') {
            $html = '';
            foreach ($el->childNodes as $c) $html .= $el->ownerDocument->saveXML($c);
            $out['#text'] = trim($html);
            return $out;
        }
        $text = '';
        $groups = [];
        foreach ($el->childNodes as $c) {
            if ($c instanceof \DOMElement) {
                if ($depth > 30) continue;
                $groups[$c->nodeName][] = self::node($c, $depth + 1);
            } elseif ($c instanceof \DOMText || $c instanceof \DOMCdataSection) {
                $text .= $c->nodeValue;
            }
        }
        foreach ($groups as $k => $vals) {
            $out[(string) $k] = count($vals) === 1 ? $vals[0] : $vals;
        }
        $text = trim($text);
        if (!$out) return $text;
        if ($text !== '') $out['#text'] = $text;
        return $out;
    }

    // ------------------------------------------------------------------ Pfade

    /** Wert unter einem Pfad (null = nicht vorhanden); [*] liefert eine Liste */
    public static function get(mixed $data, string $path): mixed
    {
        $path = trim($path);
        if ($path === '' || $path === '$') return $data;
        if (str_starts_with($path, '$.')) $path = substr($path, 2);
        $segs = self::segments($path);
        $cur = [$data];
        $multi = false;
        foreach ($segs as [$name, $filter, $attr]) {
            $next = [];
            foreach ($cur as $node) {
                if ($name !== '') {
                    if (!is_array($node) || array_is_list($node)) {
                        // Liste ohne Index: Name auf jedes Element anwenden (z. B. anhang.daten bei mehreren Anhängen → erstes)
                        if (is_array($node) && array_is_list($node) && isset($node[0]) && is_array($node[0]) && array_key_exists($name, $node[0])) $node = $node[0];
                        else continue;
                    }
                    if (!array_key_exists($name, $node)) continue;
                    $node = $node[$name];
                }
                if ($filter !== null) {
                    $list = is_array($node) && array_is_list($node) ? $node : [$node];
                    if ($filter === '*') { $multi = true; array_push($next, ...$list); continue; }
                    if (ctype_digit($filter)) { if (array_key_exists((int) $filter, $list)) $next[] = $list[(int) $filter]; continue; }
                    if (preg_match('~^@?([\w:.-]+)\s*(!?=)\s*["\']?(.*?)["\']?$~u', $filter, $m)) {
                        foreach ($list as $el) {
                            $v = is_array($el) ? ($el['@' . $m[1]] ?? $el[$m[1]] ?? null) : null;
                            $v = is_array($v) ? self::scalar($v) : $v;
                            $hit = strcasecmp((string) $v, $m[3]) === 0;
                            if ($m[2] === '!=' ? !$hit : $hit) { $next[] = $el; if (!$multi) break; }
                        }
                    }
                    continue;
                }
                $next[] = $node;
            }
            if ($attr !== null) {
                $vals = [];
                foreach ($next as $n) {
                    $n = is_array($n) && array_is_list($n) ? ($n[0] ?? null) : $n;
                    if (is_array($n) && array_key_exists('@' . $attr, $n)) $vals[] = $n['@' . $attr];
                }
                $next = $vals;
            }
            $cur = $next;
            if (!$cur) return null;
        }
        return $multi ? $cur : ($cur[0] ?? null);
    }

    /** „a.b[0].c@href“ → [[name, filter, attr], …] */
    private static function segments(string $path): array
    {
        $out = [];
        // Punkte innerhalb von [...] nicht trennen
        preg_match_all('~(?:[^.\[\]]+|\[[^\]]*\])+~u', $path, $m);
        foreach ($m[0] as $seg) {
            $attr = null;
            if (preg_match('~^(.*?)@([\w:.-]+)$~u', $seg, $a) && !str_ends_with($seg, ']')) { $seg = $a[1]; $attr = $a[2]; }
            if (preg_match('~^([^\[]*)((?:\[[^\]]*\])+)$~u', $seg, $b)) {
                preg_match_all('~\[([^\]]*)\]~', $b[2], $fs);
                $name = $b[1];
                foreach ($fs[1] as $i => $f) {
                    $out[] = [$i === 0 ? $name : '', $f, null];
                }
                if ($attr !== null) $out[count($out) - 1][2] = $attr;
                continue;
            }
            if ($seg === '' && $attr !== null) { $out[] = ['', null, $attr]; continue; }
            $out[] = [$seg, null, $attr];
        }
        return $out;
    }

    /** Wert als Text: Element mit Attributen → #text, Link-Elemente → href/url, Listen → erstes Element */
    public static function scalar(mixed $v): ?string
    {
        if ($v === null) return null;
        if (is_bool($v)) return $v ? '1' : '0';
        if (is_scalar($v)) return (string) $v;
        if (is_array($v)) {
            if (array_is_list($v)) return isset($v[0]) ? self::scalar($v[0]) : null;
            foreach (['#text', '@href', '@url', '@src', '@value', 'value', 'url', 'href'] as $k) {
                if (array_key_exists($k, $v) && !is_array($v[$k])) return (string) $v[$k];
            }
        }
        return null;
    }

    /** Alle Werte einer Liste als Texte (für Mehrfachauswahl, Bilder) */
    public static function scalars(mixed $v): array
    {
        if ($v === null) return [];
        if (is_array($v) && array_is_list($v)) return array_values(array_filter(array_map([self::class, 'scalar'], $v), fn($s) => $s !== null && $s !== ''));
        $s = self::scalar($v);
        return $s === null || $s === '' ? [] : [$s];
    }

    /**
     * Gefundene Pfade mit Beispielwert (für die Zuordnung): ['title' => 'Beispiel', 'enclosure@url' => 'https://…', …]
     * Listen erscheinen mit [0] und – falls mehrere Elemente – zusätzlich mit [*].
     */
    public static function paths(array $items, int $max = 300): array
    {
        $out = [];
        foreach (array_slice($items, 0, 5) as $item) self::walk($item, '', $out, 0);
        return array_slice($out, 0, $max, true);
    }

    private static function walk(mixed $v, string $prefix, array &$out, int $depth): void
    {
        if ($depth > 12 || count($out) > 400) return;
        if (!is_array($v)) {
            if ($prefix !== '' && !isset($out[$prefix])) $out[$prefix] = mb_strimwidth(trim(preg_replace('~\s+~u', ' ', strip_tags((string) $v))), 0, 120, '…');
            return;
        }
        if (array_is_list($v)) {
            foreach (array_slice($v, 0, 3) as $i => $el) self::walk($el, $prefix . '[' . $i . ']', $out, $depth + 1);
            return;
        }
        foreach ($v as $k => $child) {
            $k = (string) $k;
            if ($k === '#text') { self::walk($child, $prefix === '' ? '#text' : $prefix, $out, $depth + 1); continue; }
            if (str_starts_with($k, '@')) { self::walk($child, $prefix . $k, $out, $depth + 1); continue; }
            self::walk($child, $prefix === '' ? $k : $prefix . '.' . $k, $out, $depth + 1);
        }
    }
}
