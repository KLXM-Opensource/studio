<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Graphics;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\HeaderActions;
use Core\Maps;
use Core\Media;
use Core\Svg;

/**
 * Grafiken mit Daten und Zuständen (Grafik-Editor, Persona „Daten“, Ausbaustufe 5): Eigenschaften von Elementen hängen an Werten
 * aus Datentabellen oder an Zuständen der Website – dynamische Infografiken ohne JavaScript.
 *
 * Modell (nur in der Arbeitsfassung, Core\Svg::clean mit 'editor', Base64-JSON):
 *   <svg data-ge-data="…">  {v: {name: {t: 'table', tb: handle, a: count|sum|avg|min|max|latest, f: Feld, w: [Feld, op, Wert], l: Name}},
 *                            s: {name: Beispielwert}}         – Werte; Beispielwerte gelten nur für die Vorschau im Editor
 *   <g id="x" data-ge-bind="…">  [{p: text, v: name, tpl: '{v} Plätze', fmt: num|pct|cur|time|raw, d: Nachkommastellen},
 *                                 {p: w|h, v, a: [min, max], o: [x, y] Anker im Raum des Elternelements, c: 1 = begrenzen},
 *                                 {p: r, v, a: [min, max], g: [Grad bei min, Grad bei max], o: [x, y] Drehpunkt},
 *                                 {p: fill|stroke, v, m: scale|steps, a: [min, max], c: [#Farbe bei min, #Farbe bei max] | s: [[ab, #Farbe], …], b: #Grundfarbe},
 *                                 {p: o, v, a: [min, max], g: [Deckkraft bei min, bei max]},
 *                                 {p: show, v, op: > >= < <= = != between, x, y}]
 * Zustände (eingebaute Werte, Zeitzone der Website): @uhrzeit (Stunden, 5-Minuten-Schritte), @tag (1 = zwischen Sonnenauf- und
 * -untergang am Ort der Website, Maps::siteLocation(); ohne Ort 7–19 Uhr), @offen (1 = laut Öffnungszeiten geöffnet,
 * HeaderActions::ranges()), @wochentag (1 = Montag … 7 = Sonntag), @monat (1–12), @datum (MMTT, z. B. 1224).
 *
 * Website: Media::pictureOf → forMedia() setzt die aktuellen Werte serverseitig in die Web-Fassung (Text bleibt Text, <desc> nennt die
 * Werte, Alt-Text bekommt sie über {name} bzw. angehängt) und legt sie als Datei je Zustand ab ({Medienordner}/grafik/b/, Name aus
 * Prüfsumme – Browser-Cache; ältere nach einem Tag weg). Danach greifen Design-Farben (Themed) und Bewegung (Motion) wie gewohnt.
 * Datentabellen leeren beim Speichern ohnehin den Seiten-Cache (Entries::changed); zeitabhängige Zustände setzen dem Seiten-Cache eine
 * Frist bis zum nächsten Wechsel (PageCache::until) – der Zustand friert nicht ein. MIT
 */
final class Bind
{
    public const PROPS = ['text', 'w', 'h', 'r', 'fill', 'stroke', 'o', 'show'];
    public const STATES = ['@uhrzeit', '@tag', '@offen', '@wochentag', '@monat', '@datum'];
    public const AGGS = ['count', 'sum', 'avg', 'min', 'max', 'latest'];
    public const OPS = ['>', '>=', '<', '<=', '=', '!=', 'between'];
    public const FORMATS = ['num', 'pct', 'cur', 'time', 'raw'];
    private const MAX_ITEMS = 300;

    /** Für Tests und die Vorschau: feste Zeit (Unix-Zeit) statt jetzt */
    public static ?int $now = null;
    /** @var array<string, mixed> */
    private static array $memo = [];

    // ================================================================= Modell

    /** Trägt die Web-Fassung Bindungen? (Klasse ge-bound an der Wurzel, gesetzt von export.js) */
    public static function isBound(string $svg): bool
    {
        return (bool) preg_match('~<svg\b[^>]*\bclass="[^"]*\bge-bound\b~', substr($svg, 0, 4000));
    }

    /** Base64-JSON lesen */
    private static function b64(string $v, int $max): mixed
    {
        if ($v === '' || strlen($v) > $max) return null;
        $raw = base64_decode($v, true);
        return $raw === false ? null : json_decode($raw, true);
    }

    /** Name eines Werts: Buchstaben, Ziffern, _ (eingebaute mit @) */
    public static function name(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : '';
        if (in_array($v, self::STATES, true)) return $v;
        return preg_match('~^[a-z][a-z0-9_]{0,39}$~', $v) ? $v : null;
    }

    /**
     * Modell aus der Arbeitsfassung: ['values' => [name => def], 'items' => [id => list<binding>]] – null ohne Bindungen.
     */
    public static function model(string $work): ?array
    {
        if ($work === '' || !str_contains($work, 'data-ge-bind')) return null;
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($work, LIBXML_NONET | LIBXML_COMPACT) || !$doc->documentElement) return null;
        $root = $doc->documentElement;
        $values = self::values((array) (self::b64($root->getAttribute('data-ge-data'), 40000)['v'] ?? []));
        $items = [];
        foreach ($doc->getElementsByTagName('*') as $el) {
            if (!$el instanceof \DOMElement || $el === $root || !$el->hasAttribute('data-ge-bind')) continue;
            $id = $el->getAttribute('id');
            if ($id === '' || !preg_match('~^[\w.:-]{1,120}$~u', $id) || isset($items[$id])) continue;
            $list = [];
            foreach ((array) self::b64($el->getAttribute('data-ge-bind'), 20000) as $b) {
                if (is_array($b) && ($c = self::binding($b, $values))) $list[] = $c;
            }
            if ($list) $items[$id] = $list;
            if (count($items) >= self::MAX_ITEMS) break;
        }
        return $items ? ['values' => $values, 'items' => $items] : null;
    }

    /** Werte-Definitionen bereinigen (nur Datentabellen; Zustände sind eingebaut) */
    public static function values(array $in): array
    {
        $out = [];
        foreach ($in as $name => $d) {
            $name = self::name($name);
            if ($name === null || $name[0] === '@' || !is_array($d) || ($d['t'] ?? '') !== 'table') continue;
            $tb = is_string($d['tb'] ?? null) && preg_match('~^[a-z0-9_]{1,64}$~', $d['tb']) ? $d['tb'] : '';
            if ($tb === '') continue;
            $f = is_string($d['f'] ?? null) && preg_match('~^[a-z0-9_]{1,64}$~', $d['f']) ? $d['f'] : '';
            $w = null;
            if (is_array($d['w'] ?? null) && count($d['w']) === 3 && is_string($d['w'][0]) && preg_match('~^[a-z0-9_]{1,64}$~', $d['w'][0])
                && in_array($d['w'][1], ['=', '!=', '>', '<', '>=', '<=', 'contains'], true)) {
                $w = [$d['w'][0], $d['w'][1], mb_substr((string) $d['w'][2], 0, 200)];
            }
            $out[$name] = ['t' => 'table', 'tb' => $tb, 'a' => in_array($d['a'] ?? '', self::AGGS, true) ? $d['a'] : 'count', 'f' => $f, 'w' => $w,
                'l' => self::text((string) ($d['l'] ?? ''), 80)];
            if (count($out) >= 40) break;
        }
        return $out;
    }

    /** Eine Bindung bereinigen (null: unbrauchbar) */
    public static function binding(array $b, array $values): ?array
    {
        $p = $b['p'] ?? '';
        $v = self::name($b['v'] ?? '');
        if (!in_array($p, self::PROPS, true) || $v === null || ($v[0] !== '@' && !isset($values[$v]))) return null;
        $range = function (mixed $a, float $d0 = 0, float $d1 = 100): array {
            $a = is_array($a) ? array_values($a) : [];
            return [self::num($a[0] ?? $d0, $d0), self::num($a[1] ?? $d1, $d1)];
        };
        $pt = fn(mixed $o): array => [self::num(is_array($o) ? ($o[0] ?? 0) : 0, 0), self::num(is_array($o) ? ($o[1] ?? 0) : 0, 0)];
        return match ($p) {
            'text' => ['p' => $p, 'v' => $v, 'tpl' => mb_substr(str_contains((string) ($b['tpl'] ?? ''), '{v}') ? (string) $b['tpl'] : '{v}', 0, 300),
                'fmt' => in_array($b['fmt'] ?? '', self::FORMATS, true) ? $b['fmt'] : 'num', 'd' => max(0, min(4, (int) ($b['d'] ?? 0)))],
            'w', 'h' => ['p' => $p, 'v' => $v, 'a' => $range($b['a'] ?? null), 'o' => $pt($b['o'] ?? null), 'c' => ($b['c'] ?? 1) ? 1 : 0],
            'r' => ['p' => $p, 'v' => $v, 'a' => $range($b['a'] ?? null), 'g' => $range($b['g'] ?? null, 0, 360), 'o' => $pt($b['o'] ?? null)],
            'o' => ['p' => $p, 'v' => $v, 'a' => $range($b['a'] ?? null), 'g' => array_map(fn($x) => max(0.0, min(1.0, $x)), $range($b['g'] ?? null, 0, 1))],
            'fill', 'stroke' => self::colorBinding($p, $v, $b, $range),
            'show' => ['p' => $p, 'v' => $v, 'op' => in_array($b['op'] ?? '', self::OPS, true) ? $b['op'] : '>', 'x' => self::num($b['x'] ?? 0, 0), 'y' => self::num($b['y'] ?? 0, 0)],
        };
    }

    private static function colorBinding(string $p, string $v, array $b, callable $range): ?array
    {
        $base = self::hex($b['b'] ?? '') ?? '#000000';
        if (($b['m'] ?? 'scale') === 'steps') {
            $steps = [];
            foreach (array_slice((array) ($b['s'] ?? []), 0, 20) as $s) {
                if (is_array($s) && ($c = self::hex($s[1] ?? '')) !== null) $steps[] = [self::num($s[0] ?? 0, 0), $c];
            }
            usort($steps, fn($x, $y) => $x[0] <=> $y[0]);
            return $steps ? ['p' => $p, 'v' => $v, 'm' => 'steps', 's' => $steps, 'b' => $base] : null;
        }
        $c = is_array($b['c'] ?? null) ? array_values($b['c']) : [];
        $c0 = self::hex($c[0] ?? '') ?? $base;
        $c1 = self::hex($c[1] ?? '') ?? $base;
        return ['p' => $p, 'v' => $v, 'm' => 'scale', 'a' => $range($b['a'] ?? null), 'c' => [$c0, $c1], 'b' => $base];
    }

    /** Werte, die eine Grafik braucht (Namen) */
    public static function used(array $model): array
    {
        $n = [];
        foreach ($model['items'] as $list) foreach ($list as $b) $n[$b['v']] = true;
        return array_keys($n);
    }

    // ================================================================= Werte

    public static function now(): int
    {
        return self::$now ?? time();
    }

    /** Aktuelle Werte: alle eigenen Werte (auch für {name} im Alt-Text) und die benutzten Zustände – [name => float|string|null] */
    public static function current(array $model): array
    {
        $out = [];
        foreach ($model['values'] as $name => $def) $out[$name] = self::tableValue($def);
        foreach (self::used($model) as $name) if ($name[0] === '@') $out[$name] = self::state($name);
        return $out;
    }

    /** Eingebauter Zustand zur Zeit $ts (Zeitzone der Website) */
    public static function state(string $name, ?int $ts = null): ?float
    {
        $ts ??= self::now();
        $d = (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $hour = (int) $d->format('G') + intdiv((int) $d->format('i'), 5) * 5 / 60;
        return match ($name) {
            '@uhrzeit' => round($hour, 4),
            '@tag' => self::isDay($ts) ? 1.0 : 0.0,
            '@offen' => self::isOpen($ts) ? 1.0 : 0.0,
            '@wochentag' => (float) $d->format('N'),
            '@monat' => (float) $d->format('n'),
            '@datum' => (float) $d->format('md'),
            default => null,
        };
    }

    /** Sonnenauf- und -untergang am Ort der Website (Unix-Zeit) – ohne Ort 7 und 19 Uhr */
    public static function sun(int $ts): array
    {
        $tz = new \DateTimeZone(date_default_timezone_get());
        $day = (new \DateTimeImmutable('@' . $ts))->setTimezone($tz)->setTime(12, 0);
        $loc = null;
        try { $loc = Maps::siteLocation(); } catch (\Throwable) { $loc = null; }
        if ($loc) {
            $i = date_sun_info($day->getTimestamp(), (float) $loc[0], (float) $loc[1]);
            if (is_int($i['sunrise']) && is_int($i['sunset'])) return [$i['sunrise'], $i['sunset']];
            // Polartag/-nacht: ganzer Tag hell bzw. dunkel
            $up = $i['sunrise'] === true;
            $mid = $day->setTime(0, 0)->getTimestamp();
            return $up ? [$mid, $mid + 86400] : [$mid + 43200, $mid + 43200];
        }
        return [$day->setTime(7, 0)->getTimestamp(), $day->setTime(19, 0)->getTimestamp()];
    }

    /**
     * Tag am Ort der Website? Sonnenzeiten des Vortags, des Tags und des Folgetags prüfen – liegt der Ort in einer anderen Zeitzone als
     * die Website (z. B. Ort auf der Südhalbkugel), gehört der lichte Abschnitt sonst zum „falschen“ Kalendertag.
     */
    public static function isDay(int $ts): bool
    {
        foreach ([-1, 0, 1] as $add) {
            [$up, $down] = self::sun($ts + $add * 86400);
            if ($ts >= $up && $ts < $down) return true;
        }
        return false;
    }

    public static function isOpen(int $ts): bool
    {
        $d = (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $dow = (int) $d->format('w');
        $now = $d->format('H:i');
        foreach (self::ranges() as [$day, $from, $to]) if ($day === $dow && $from <= $now && $now < $to) return true;
        return false;
    }

    /** Öffnungszeiten der Website: [[Wochentag 0 = So, 'HH:MM', 'HH:MM'], …] */
    private static function ranges(): array
    {
        try {
            return array_values(array_filter(HeaderActions::ranges(), fn($r) => preg_match('~^\d\d:\d\d$~', $r[1]) && preg_match('~^\d\d:\d\d$~', $r[2])));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Nächster Wechsel eines Zustands nach $ts (Unix-Zeit) – für die Frist des Seiten-Caches. null = ändert sich nicht.
     */
    public static function nextChange(string $name, ?int $ts = null): ?int
    {
        $ts ??= self::now();
        $tz = new \DateTimeZone(date_default_timezone_get());
        $d = (new \DateTimeImmutable('@' . $ts))->setTimezone($tz);
        $midnight = $d->setTime(0, 0)->modify('+1 day')->getTimestamp();
        switch ($name) {
            case '@uhrzeit':
                return $ts - ($ts % 300) + 300;
            case '@tag':
                $c = [];
                foreach ([-1, 0, 1, 2] as $add) { [$up, $down] = self::sun($ts + $add * 86400); $c[] = $up; $c[] = $down; }
                $c = array_filter($c, fn($x) => $x > $ts);
                return $c ? min($c) : $midnight;
            case '@offen':
                $c = [];
                for ($i = 0; $i <= 7; $i++) {
                    $day = $d->setTime(0, 0)->modify('+' . $i . ' day');
                    $dow = (int) $day->format('w');
                    foreach (self::ranges() as [$w, $from, $to]) {
                        if ($w !== $dow) continue;
                        foreach ([$from, $to] as $hm) {
                            [$h, $m] = array_map('intval', explode(':', $hm));
                            $x = $day->setTime(min(23, $h), $m)->getTimestamp() + ($h >= 24 ? 3600 : 0);
                            if ($x > $ts) $c[] = $x;
                        }
                    }
                }
                return $c ? min($c) : null;
            case '@wochentag': case '@monat': case '@datum':
                return $midnight;
        }
        return null;
    }

    /** Wert aus einer Datentabelle (veröffentlichte Einträge) */
    public static function tableValue(array $def): float|string|null
    {
        $key = json_encode($def);
        if (array_key_exists($key, self::$memo)) return self::$memo[$key];
        $t = Tables::findContent($def['tb']);
        if (!$t || Tables::isInbox($t)) return self::$memo[$key] = null;
        $o = ['status' => 'published'];
        if ($def['w'] && Tables::field($t, $def['w'][0])) $o['where'] = [$def['w']];
        try {
            if ($def['a'] === 'count') return self::$memo[$key] = (float) Entries::count($t, $o);
            $f = $def['f'];
            if ($f === '' || (!Tables::field($t, $f) && !in_array($f, Tables::SYSTEM, true))) return self::$memo[$key] = null;
            if ($def['a'] === 'latest') {
                $e = Entries::query($t, $o + ['sort' => 'id', 'dir' => 'desc', 'limit' => 1])[0] ?? null;
                if (!$e) return self::$memo[$key] = null;
                $v = $e[$f] ?? null;
                return self::$memo[$key] = is_numeric($v) ? (float) $v : (is_scalar($v) ? Entries::text($t, $e, $f) : null);
            }
            $nums = [];
            foreach (Entries::query($t, $o + ['limit' => 5000]) as $e) if (is_numeric($e[$f] ?? null)) $nums[] = (float) $e[$f];
            if (!$nums) return self::$memo[$key] = $def['a'] === 'sum' ? 0.0 : null;
            return self::$memo[$key] = match ($def['a']) {
                'sum' => array_sum($nums), 'avg' => array_sum($nums) / count($nums), 'min' => min($nums), 'max' => max($nums), default => null,
            };
        } catch (\Throwable $e) {
            error_log('[grafik] Daten: ' . $e->getMessage());
            return self::$memo[$key] = null;
        }
    }

    // ================================================================= Anwenden

    /** Zahl → Text nach Format der Bindung (Core\Format der Website-Sprache) */
    public static function format(float|string|null $v, string $fmt = 'num', int $d = 0): string
    {
        if ($v === null) return '–';
        if (is_string($v)) return $v;
        $f = \Core\Format::for();
        return match ($fmt) {
            'pct' => $f->number($v, $d) . "\u{202F}%",
            'cur' => $f->currency($v, 'EUR', $d > 0 ? $d : null),
            'time' => sprintf('%d:%02d', (int) floor($v), (int) round(fmod($v, 1) * 60) % 60),
            'raw' => rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.'),
            default => $f->number($v, $d),
        };
    }

    /** Anteil 0..1 (bzw. darüber hinaus ohne Begrenzung) des Werts zwischen min und max */
    public static function ratio(float|string|null $v, array $a, bool $clamp = true): float
    {
        $x = is_numeric($v) ? (float) $v : $a[0];
        $k = $a[1] == $a[0] ? ($x >= $a[1] ? 1.0 : 0.0) : ($x - $a[0]) / ($a[1] - $a[0]);
        return $clamp ? max(0.0, min(1.0, $k)) : $k;
    }

    /** Bedingung erfüllt? */
    public static function test(float|string|null $v, array $b): bool
    {
        if (!is_numeric($v)) return false;
        $x = (float) $v;
        return match ($b['op']) {
            '>' => $x > $b['x'], '>=' => $x >= $b['x'], '<' => $x < $b['x'], '<=' => $x <= $b['x'],
            '=' => abs($x - $b['x']) < 1e-9, '!=' => abs($x - $b['x']) >= 1e-9,
            'between' => $x >= min($b['x'], $b['y']) && $x <= max($b['x'], $b['y']),
            default => false,
        };
    }

    /** Farbe einer Farbbindung zum Wert */
    public static function colorFor(array $b, float|string|null $v): string
    {
        if ($b['m'] === 'steps') {
            $c = $b['b'];
            if (is_numeric($v)) foreach ($b['s'] as [$at, $col]) if ((float) $v >= $at) $c = $col;
            return $c;
        }
        $k = self::ratio($v, $b['a']);
        $p = fn(string $h) => [hexdec(substr($h, 1, 2)), hexdec(substr($h, 3, 2)), hexdec(substr($h, 5, 2))];
        [$x, $y] = [$p($b['c'][0]), $p($b['c'][1])];
        return sprintf('#%02x%02x%02x', ...array_map(fn($i) => (int) round($x[$i] + ($y[$i] - $x[$i]) * $k), [0, 1, 2]));
    }

    /**
     * Web-Fassung mit Werten: Elemente nach id, nur Darstellungsattribute (transform, fill, stroke, opacity), Text als Text,
     * <desc> mit den aktuellen Werten. $labels: Namen der Werte für die Beschreibung.
     * @param array<string, float|string|null> $vals
     */
    public static function apply(string $web, array $model, array $vals): string
    {
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($web, LIBXML_NONET | LIBXML_COMPACT) || !$doc->documentElement) return $web;
        $root = $doc->documentElement;
        $byId = [];
        foreach ($doc->getElementsByTagName('*') as $el) if ($el instanceof \DOMElement && $el->getAttribute('id') !== '') $byId[$el->getAttribute('id')] ??= $el;
        $remove = [];
        foreach ($model['items'] as $id => $list) {
            $el = $byId[$id] ?? null;
            if (!$el || $el === $root) continue;
            $pre = [];
            foreach ($list as $b) {
                $v = $vals[$b['v']] ?? null;
                switch ($b['p']) {
                    case 'text':
                        self::setText($el, str_replace('{v}', self::format($v, $b['fmt'], $b['d']), $b['tpl']));
                        break;
                    case 'w': case 'h':
                        $k = self::f(self::ratio($v, $b['a'], (bool) $b['c']), 5);
                        [$x, $y] = $b['o'];
                        $pre[] = 'translate(' . self::f($x) . ' ' . self::f($y) . ') scale(' . ($b['p'] === 'w' ? $k . ' 1' : '1 ' . $k) . ') translate(' . self::f(-$x) . ' ' . self::f(-$y) . ')';
                        break;
                    case 'r':
                        $k = self::ratio($v, $b['a']);
                        $deg = $b['g'][0] + ($b['g'][1] - $b['g'][0]) * $k;
                        array_unshift($pre, 'rotate(' . self::f($deg) . ' ' . self::f($b['o'][0]) . ' ' . self::f($b['o'][1]) . ')');
                        break;
                    case 'o':
                        $k = self::ratio($v, $b['a']);
                        $el->setAttribute('opacity', self::f($b['g'][0] + ($b['g'][1] - $b['g'][0]) * $k, 4));
                        self::dropStyle($el, 'opacity');
                        break;
                    case 'fill': case 'stroke':
                        self::paint($el, $b['p'], self::colorFor($b, $v));
                        break;
                    case 'show':
                        if (!self::test($v, $b)) $remove[] = $el;
                        break;
                }
            }
            if ($pre) $el->setAttribute('transform', trim(implode(' ', $pre) . ' ' . $el->getAttribute('transform')));
        }
        foreach ($remove as $el) if ($el->parentNode) $el->parentNode->removeChild($el);
        // Beschreibung: aktuelle Werte (Screenreader bei Inline-Einbindung, Datei für sich)
        $sum = self::summary($model, $vals);
        if ($sum !== '') {
            $desc = null;
            foreach ($root->childNodes as $c) if ($c instanceof \DOMElement && $c->localName === 'desc') { $desc = $c; break; }
            if (!$desc) {
                $desc = $doc->createElementNS('http://www.w3.org/2000/svg', 'desc');
                $title = null;
                foreach ($root->childNodes as $c) if ($c instanceof \DOMElement && $c->localName === 'title') { $title = $c; break; }
                $title && $title->nextSibling ? $root->insertBefore($desc, $title->nextSibling) : ($title ? $root->appendChild($desc) : $root->insertBefore($desc, $root->firstChild));
            }
            $old = trim($desc->textContent);
            $desc->textContent = ($old !== '' ? $old . "\n" : '') . $sum;
        }
        return (string) $doc->saveXML($root);
    }

    /** „Freie Plätze: 12; Geöffnet: ja“ – Werte, die die Grafik zeigt */
    public static function summary(array $model, array $vals): string
    {
        $out = [];
        foreach (self::used($model) as $name) {
            $label = self::label($name, $model);
            $v = $vals[$name] ?? null;
            $out[] = $label . ': ' . match ($name) {
                '@tag' => $v ? lt('Tag') : lt('Nacht'),
                '@offen' => $v ? lt('geöffnet') : lt('geschlossen'),
                '@uhrzeit' => self::format($v, 'time'),
                default => self::format($v, 'num', is_float($v) && fmod($v, 1.0) ? 2 : 0),
            };
        }
        return implode('; ', $out);
    }

    public static function label(string $name, array $model): string
    {
        return match ($name) {
            '@uhrzeit' => lt('Uhrzeit'), '@tag' => lt('Tag oder Nacht'), '@offen' => lt('Öffnungsstatus'), '@wochentag' => lt('Wochentag'),
            '@monat' => lt('Monat'), '@datum' => lt('Datum'),
            default => ($model['values'][$name]['l'] ?? '') !== '' ? (string) $model['values'][$name]['l'] : $name,
        };
    }

    /** Alt-Text mit Werten: {name} wird ersetzt; ohne Platzhalter werden die Werte angehängt */
    public static function alt(string $alt, array $model, array $vals): string
    {
        $alt = trim($alt);
        if ($alt === '') return $alt;
        $hit = false;
        $alt = (string) preg_replace_callback('~\{(@?[a-z][a-z0-9_]{0,39})\}~', function ($m) use ($vals, &$hit) {
            if (!array_key_exists($m[1], $vals)) return $m[0];
            $hit = true;
            $v = $vals[$m[1]];
            return self::format($v, $m[1] === '@uhrzeit' ? 'time' : 'num', is_float($v) && fmod($v, 1.0) ? 2 : 0);
        }, $alt);
        if ($hit) return $alt;
        $sum = self::summary($model, $vals);
        return $sum !== '' ? rtrim($alt, '. ') . ' (' . $sum . ')' : $alt;
    }

    private static function setText(\DOMElement $el, string $text): void
    {
        $nodes = [];
        $walk = function (\DOMNode $n) use (&$walk, &$nodes): void {
            foreach ($n->childNodes as $c) {
                if ($c instanceof \DOMText && !$c instanceof \DOMCdataSection) $nodes[] = $c;
                elseif ($c instanceof \DOMElement && !in_array($c->localName, ['title', 'desc', 'style'], true)) $walk($c);
            }
        };
        $walk($el);
        $first = null;
        foreach ($nodes as $n) {
            if ($first === null && trim($n->data) !== '') { $first = $n; continue; }
            if ($first !== null) $n->data = '';
        }
        if ($first) $first->data = $text;
        elseif ($el->localName === 'text') $el->appendChild($el->ownerDocument->createTextNode($text));
    }

    /** Farbe setzen – am Element und an Kindern mit eigener Farbe; Design-Farben (Klassen) dieser Eigenschaft weichen */
    private static function paint(\DOMElement $el, string $prop, string $color): void
    {
        $k = $prop === 'fill' ? 'f' : 's';
        $list = [$el];
        foreach ($el->getElementsByTagName('*') as $c) if ($c instanceof \DOMElement && ($c->hasAttribute($prop) || str_contains($c->getAttribute('style'), $prop))) $list[] = $c;
        foreach ($list as $x) {
            $cur = $x->getAttribute($prop);
            if ($x !== $el && ($cur === 'none' || str_starts_with($cur, 'url('))) continue;
            $x->setAttribute($prop, $color);
            self::dropStyle($x, $prop);
            $cls = trim((string) preg_replace('~\bge-[td]' . $k . '-[\w-]+~', '', $x->getAttribute('class')));
            $cls = trim((string) preg_replace('~\s+~', ' ', $cls));
            $cls !== '' ? $x->setAttribute('class', $cls) : $x->removeAttribute('class');
        }
    }

    private static function dropStyle(\DOMElement $el, string $prop): void
    {
        $st = $el->getAttribute('style');
        if ($st === '') return;
        $st = trim((string) preg_replace('~(?:^|;)\s*' . preg_quote($prop, '~') . '\s*:[^;]*~', '', $st), '; ');
        $st !== '' ? $el->setAttribute('style', $st) : $el->removeAttribute('style');
    }

    // ================================================================= Mediathek → Website

    /**
     * Infografik der Mediathek mit Bindungen: Datensatz, dessen Datei die Fassung mit den aktuellen Werten ist (file → grafik/b/…),
     * dazu Arbeitsfassung (für Motion) und Werte – null ohne Bindungen.
     * @return ?array{media: array, work: string, values: array, model: array}
     */
    public static function forMedia(array $m): ?array
    {
        if (($m['mime'] ?? '') !== Svg::MIME || empty($m['id']) || ($m['_pool'] ?? '') !== '' || isset($m['_bound'])) return null;
        $path = Media::path($m);
        $web = is_file($path) ? (string) @file_get_contents($path, false, null, 0, Svg::MAX_BYTES + 1) : '';
        if ($web === '' || !self::isBound($web)) return null;
        $work = Graphics::source('media:' . (int) $m['id'], $web);
        $model = $work !== null ? self::model($work) : null;
        if (!$model) return null;
        $vals = self::current($model);
        // Frist für den Seiten-Cache: nächster Wechsel eines zeitabhängigen Zustands
        foreach (array_keys($vals) as $name) if ($name[0] === '@' && ($t = self::nextChange($name)) !== null) \Core\PageCache::until($t);
        $hash = substr(sha1(Motion::generator() . "\n" . $web . "\n" . json_encode($model) . "\n" . json_encode($vals) . "\n" . \Core\Lang::current()), 0, 10);
        $id = (int) $m['id'];
        $rel = 'grafik/b/gb-' . $id . '-' . $hash . '.svg';
        $file = site()->mediaDir() . '/' . $rel;
        if (!is_file($file)) {
            $dir = dirname($file);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return null;
            Svg::guard(Graphics::dir());
            foreach (glob($dir . '/gb-' . $id . '-*.svg') ?: [] as $old) if (filemtime($old) < time() - 86400) @unlink($old);
            try {
                $out = Svg::clean(self::apply($web, $model, $vals), ['keep_ids' => true])['svg'];
            } catch (\RuntimeException $e) {
                error_log('[grafik] Daten: ' . $e->getMessage());
                return null;
            }
            if (@file_put_contents($file, $out, LOCK_EX) === false) return null;
        }
        return ['media' => ['file' => $rel, '_bound' => true, '_work' => $work] + $m, 'work' => $work, 'values' => $vals, 'model' => $model];
    }

    /** Für Tests: Zwischenspeicher leeren */
    public static function forget(): void
    {
        self::$memo = [];
    }

    // ================================================================= Editor

    /** Angaben für den Editor: Tabellen mit Feldern, Öffnungszeiten, Sonnenzeiten heute, aktuelle Zustände */
    public static function forEditor(): array
    {
        $tables = [];
        try {
            foreach (Tables::content() as $t) {
                if (Tables::isInbox($t)) continue;
                $fields = [];
                foreach (Tables::dataFields($t['fields']) as $f) {
                    if (in_array($f['type'] ?? '', ['number', 'text', 'date', 'select', 'bool', 'textarea'], true)) $fields[] = ['name' => (string) $f['name'], 'label' => Tables::label($f), 'type' => (string) $f['type']];
                }
                $tables[] = ['handle' => (string) $t['handle'], 'name' => (string) $t['name'], 'fields' => $fields];
            }
        } catch (\Throwable) {
            $tables = [];
        }
        $ts = self::now();
        [$up, $down] = self::sun($ts);
        $tz = new \DateTimeZone(date_default_timezone_get());
        $h = fn(int $x) => (int) (new \DateTimeImmutable('@' . $x))->setTimezone($tz)->format('G') + (int) (new \DateTimeImmutable('@' . $x))->setTimezone($tz)->format('i') / 60;
        $loc = null;
        try { $loc = Maps::siteLocation(); } catch (\Throwable) { $loc = null; }
        $states = [];
        foreach (self::STATES as $s) $states[$s] = self::state($s, $ts);
        return ['tables' => $tables, 'hours' => self::ranges(), 'sun' => [round($h($up), 3), round($h($down), 3)], 'located' => $loc !== null,
            'states' => $states, 'tz' => date_default_timezone_get(), 'lang' => \Core\Lang::current()];
    }

    /** Werte für die Vorschau im Editor (aktuelle Werte der Datentabellen): $defs aus data-ge-data */
    public static function preview(array $defs): array
    {
        $out = [];
        foreach (self::values($defs) as $name => $d) $out[$name] = self::tableValue($d);
        return $out;
    }

    // ================================================================= Hilfen

    private static function num(mixed $v, float $def): float
    {
        $f = is_numeric($v) ? (float) $v : $def;
        if (!is_finite($f)) $f = $def;
        return round(max(-1e12, min(1e12, $f)), 6);
    }

    private static function hex(mixed $v): ?string
    {
        $v = strtolower(trim((string) $v));
        return preg_match('~^#[0-9a-f]{6}$~', $v) ? $v : null;
    }

    private static function text(string $v, int $max): string
    {
        return mb_substr(trim((string) preg_replace('~[\x00-\x1f<>]+~', ' ', $v)), 0, $max);
    }

    private static function f(float $n, int $dec = 3): string
    {
        return Motion::f($n, $dec);
    }
}
