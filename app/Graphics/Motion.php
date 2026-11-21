<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Graphics;

use Core\Media;
use Core\Svg;

/**
 * Bewegte und interaktive Grafiken (Grafik-Editor, Persona „Motion“, Ausbaustufe 4).
 *
 * Modell (nur in der Arbeitsfassung, Core\Svg::clean mit 'editor'):
 *   <svg data-ge-motion="d=4;dl=0;it=1;dir=normal;tr=view">     Dauer, Verzögerung (s), Wiederholungen (0 = endlos),
 *                                                                Richtung, Auslöser load|view|hover|click
 *   <g id="x" data-ge-anim="Base64(JSON)">                       Bewegung des Elements:
 *     {p: [px, py]  Drehpunkt im Raum des Elternelements,
 *      k: {pos: [[t, dx, dy, e]], s: [[t, v, e]], r: …, o: …, w: … (Linie zeichnen 0..1), f|c: [[t, '#rrggbb', e, '#dunkel']]},
 *      n: {a, b, d, g, p, t0, t1, e}  Zählen (Text), tr: 'hover' (beim Zeigen auf das Element), on: 'id' (Hotspot)}
 *   data-ge-link="page:12" | URL, data-ge-linklabel, data-ge-tip (Tooltip), data-ge-tipbox="x y w h", data-ge-hot="1" (Hotspot)
 *   Zeiten t in Sekunden, e = Verlauf ab diesem Schlüsselbild (sanft, abbremsen, … oder [x1, y1, x2, y2]).
 *
 * Ausgabe auf der Website (Entscheidung): CSS-Animationen innerer Elemente wirken nur im Dokument, nicht im <img> – Grafiken
 * mit Bewegung oder Interaktion kommen deshalb inline. Die strenge CSP (style-src 'self') verbietet <style> und style-Attribute:
 * alles Gestalterische steht in einer erzeugten CSS-Datei je Grafik und Fassung ({Medienordner}/grafik/m/gm-{id}-{hash}.css,
 * Name aus Prüfsumme – Browser-Cache wie bei den Hell/Dunkel-Fassungen). Im SVG bleiben nur Darstellungsattribute und Klassen:
 *  - <style> der Grafik → Regeln mit Wurzel-Klasse davor (.gm-K .gm-K-x), style-Attribute → Klassen gm-K-sN,
 *  - Klassen und ids mit Präfix (ids je Einbindung __GM__ → gm{Medium}n{Zähler}, Verweise url(#…), href, aria-* mit),
 *  - bewegte Elemente in <g class="gm-a gm-aN"> (CSS translate/rotate/scale um den Drehpunkt, transform-box: view-box –
 *    das transform-Attribut des Elements bleibt unberührt), Farben direkt am Element (gm-cN),
 *  - Ruhe = Endzustand (ohne Skript, „Bewegung reduzieren“, Design-Schalter „Dezente Animationen“ aus); .is-armed = Anfang,
 *    .is-play spielt, .is-off hält Schleifen außerhalb des Bildes an (js/graphic-motion.js, nur bei Bedarf geladen).
 *  - Links als echte <a href>, Tooltips als <title> plus sichtbares Schild bei Zeigen/Fokus, Hotspots als role=button.
 * MIT
 */
final class Motion
{
    /**
     * Fassung der Ausgabe – fließt mit der Prüfsumme dieser Datei (generator()) in die Dateinamen: jede Änderung am Aufbau oder CSS
     * ergibt neue Adressen, auch wenn niemand die Zahl erhöht (vorher konnte nach einem Update alte CSS aus dem Zwischenspeicher kommen).
     */
    public const VERSION = 2;
    /** Automatisch gestartete Bewegung, die länger läuft (s), bekommt einen Knopf „Animation anhalten“ (WCAG 2.2.2) */
    public const PAUSE_AFTER = 5.0;
    public const TRIGGERS = ['load', 'view', 'hover', 'click'];
    public const DIRS = ['normal', 'reverse', 'alternate', 'alternate-reverse'];
    /** Verläufe (Editor-Namen → CSS) */
    public const EASE = [
        'smooth' => 'ease-in-out', 'out' => 'ease-out', 'in' => 'ease-in', 'linear' => 'linear',
        'spring' => 'cubic-bezier(.34,1.56,.64,1)', 'hold' => 'steps(1,end)',
    ];
    /** Eigenschaften: Wert-Spuren am Hüll-Element (gm-a) bzw. Farben am Element (gm-c) */
    public const WRAP_PROPS = ['pos', 's', 'r', 'o', 'w'];
    public const COLOR_PROPS = ['f', 'c'];
    private const SHAPES = ['path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon'];
    private const NS = 'http://www.w3.org/2000/svg';
    private const MAX_ITEMS = 300;
    private const MAX_KEYS = 400;

    /** @var array<string, ?array> */
    private static array $memo = [];
    private static ?string $gen = null;
    /** @var array<string, int> Einbindungen je Grafik in dieser Anfrage */
    private static array $seen = [];

    // ================================================================= Modell

    /** Trägt die Web-Fassung Bewegung oder Interaktion? (Klasse ge-anim an der Wurzel, gesetzt von export.js) */
    public static function isAnimated(string $svg): bool
    {
        return (bool) preg_match('~<svg\b[^>]*\bclass="[^"]*\bge-anim\b~', substr($svg, 0, 4000));
    }

    /** Szene aus data-ge-motion */
    public static function scene(string $attr): array
    {
        $kv = [];
        foreach (explode(';', $attr) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
            $kv[trim($k)] = trim($v);
        }
        return [
            'd' => self::num($kv['d'] ?? 4, 0.1, 120), 'dl' => self::num($kv['dl'] ?? 0, 0, 60),
            'it' => (int) self::num($kv['it'] ?? 1, 0, 100),
            'dir' => in_array($kv['dir'] ?? '', self::DIRS, true) ? $kv['dir'] : 'normal',
            'tr' => in_array($kv['tr'] ?? '', self::TRIGGERS, true) ? $kv['tr'] : 'view',
        ];
    }

    /**
     * Modell aus der Arbeitsfassung: ['scene' => …, 'items' => [id => [anim, link, label, tip, tipbox, hot]]]
     * (null: keine Bewegung/Interaktion oder nicht lesbar)
     */
    public static function model(string $work): ?array
    {
        if ($work === '' || !str_contains($work, 'data-ge-')) return null;
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($work, LIBXML_NONET | LIBXML_COMPACT) || !$doc->documentElement) return null;
        $root = $doc->documentElement;
        $scene = self::scene($root->getAttribute('data-ge-motion'));
        $items = [];
        $hots = [];
        foreach ($doc->getElementsByTagName('*') as $el) {
            if (!$el instanceof \DOMElement || $el === $root) continue;
            $id = $el->getAttribute('id');
            if ($id === '' || !preg_match('~^[\w.:-]{1,120}$~u', $id) || isset($items[$id])) continue;
            $anim = $el->hasAttribute('data-ge-anim') ? self::anim($el->getAttribute('data-ge-anim'), $scene['d']) : null;
            $link = self::cleanLink($el->getAttribute('data-ge-link'));
            $tip = self::text($el->getAttribute('data-ge-tip'), 300);
            $hot = $el->getAttribute('data-ge-hot') === '1';
            if (!$anim && $link === '' && $tip === '' && !$hot) continue;
            $box = array_map('floatval', preg_split('~[\s,]+~', trim($el->getAttribute('data-ge-tipbox'))) ?: []);
            $items[$id] = [
                'tag' => $el->localName, 'anim' => $anim,
                'link' => $link, 'label' => self::text($el->getAttribute('data-ge-linklabel'), 200),
                'tip' => $tip, 'tipbox' => count($box) === 4 && $box[2] > 0 && $box[3] > 0 ? $box : null,
                'hot' => $hot && $link === '', 'name' => self::text($el->getAttribute('data-name'), 120),
            ];
            if ($items[$id]['hot']) $hots[$id] = true;
            if (count($items) >= self::MAX_ITEMS) break;
        }
        // Hotspot-Verweise nur auf vorhandene Hotspots
        foreach ($items as &$it) if (isset($it['anim']['on']) && !isset($hots[$it['anim']['on']])) unset($it['anim']['on']);
        unset($it);
        if (!$items) return null;
        return ['scene' => $scene, 'items' => $items];
    }

    /** Bewegung eines Elements (Base64-JSON) bereinigen */
    public static function anim(string $b64, float $dur): ?array
    {
        if ($b64 === '' || strlen($b64) > 120000) return null;
        $j = json_decode((string) base64_decode($b64, true), true);
        if (!is_array($j)) return null;
        $p = is_array($j['p'] ?? null) ? $j['p'] : [0, 0];
        $out = ['p' => [self::num($p[0] ?? 0, -1e6, 1e6), self::num($p[1] ?? 0, -1e6, 1e6)], 'k' => []];
        $max = 120.0;
        foreach (array_merge(self::WRAP_PROPS, self::COLOR_PROPS) as $prop) {
            $list = $j['k'][$prop] ?? null;
            if (!is_array($list)) continue;
            $keys = [];
            foreach (array_slice(array_values(array_filter($list, 'is_array')), 0, self::MAX_KEYS) as $k) {
                $t = self::num($k[0] ?? 0, 0, $max);
                $key = match ($prop) {
                    'pos' => [$t, self::num($k[1] ?? 0, -1e5, 1e5), self::num($k[2] ?? 0, -1e5, 1e5), self::ease($k[3] ?? null)],
                    's' => [$t, self::num($k[1] ?? 1, 0, 100), self::ease($k[2] ?? null)],
                    'r' => [$t, self::num($k[1] ?? 0, -36000, 36000), self::ease($k[2] ?? null)],
                    'o', 'w' => [$t, self::num($k[1] ?? 1, 0, 1), self::ease($k[2] ?? null)],
                    'f', 'c' => [$t, self::hex($k[1] ?? '') ?? '#000000', self::ease($k[2] ?? null), self::hex($k[3] ?? '')],
                };
                $keys[] = $key;
            }
            usort($keys, fn($a, $b) => $a[0] <=> $b[0]);
            if ($keys) $out['k'][$prop] = $keys;
        }
        if (is_array($j['n'] ?? null)) {
            $n = $j['n'];
            $out['n'] = ['a' => self::num($n['a'] ?? 0, -1e12, 1e12), 'b' => self::num($n['b'] ?? 0, -1e12, 1e12), 'd' => (int) self::num($n['d'] ?? 0, 0, 4),
                'g' => in_array($n['g'] ?? '', ['', '.', ',', ' ', "\u{202F}", "'"], true) ? $n['g'] : '',
                'p' => ($n['p'] ?? '.') === ',' ? ',' : '.', 't0' => self::num($n['t0'] ?? 0, 0, $max), 't1' => self::num($n['t1'] ?? 1, 0, $max),
                'e' => self::ease($n['e'] ?? null)];
            if ($out['n']['t1'] < $out['n']['t0']) $out['n']['t1'] = $out['n']['t0'];
        }
        if (($j['tr'] ?? '') === 'hover') $out['tr'] = 'hover';
        if (is_string($j['on'] ?? null) && preg_match('~^[\w.:-]{1,120}$~u', $j['on'])) $out['on'] = $j['on'];
        return $out['k'] || isset($out['n']) ? $out : null;
    }

    /** Verlauf → CSS-Zeitfunktion */
    public static function ease(mixed $e): string
    {
        if (is_string($e) && isset(self::EASE[$e])) return self::EASE[$e];
        if (is_array($e) && count($e) === 4) {
            $v = array_values($e);
            return 'cubic-bezier(' . self::f(self::num($v[0], 0, 1)) . ',' . self::f(self::num($v[1], -2, 3)) . ',' . self::f(self::num($v[2], 0, 1)) . ',' . self::f(self::num($v[3], -2, 3)) . ')';
        }
        return self::EASE['smooth'];
    }

    private static function cleanLink(string $v): string
    {
        $v = trim($v);
        if ($v === '' || strlen($v) > 2000) return '';
        if (\Core\Links::isRef($v)) return $v;
        return preg_match('~^(#[\w-]{1,80}|/(?!/)[^\s"<>]*|https?://[^\s"<>]+|mailto:[^\s"<>]+|tel:[+\d\s()/-]+)$~i', $v) ? $v : '';
    }

    // ================================================================= Website

    /** „Dezente Animationen“ (Design-Schalter motion mit Klasse) aus? Dann Endzustand ohne Bewegung */
    public static function still(): bool
    {
        try {
            if (!\Core\Design::enabled()) return false;
            foreach (\Core\Design::tokens() as $name => $t) {
                if ($name !== 'motion' || ($t['type'] ?? '') !== 'bool' || empty($t['class'])) continue;
                $have = preg_split('~\s+~', \Core\Design::htmlClasses(\Core\Design::values())) ?: [];
                return !in_array((string) $t['class'], $have, true);
            }
        } catch (\Throwable) {
            return false;
        }
        return false;
    }

    /**
     * Inline-Ausgabe einer bewegten/interaktiven Infografik der Mediathek (Media::pictureOf) – null: wie bisher als Bild.
     * $opt: class (Zusatzklasse), static (true = immer als Bild)
     */
    public static function inline(array $m, string $alt, array $opt = []): ?string
    {
        if (!empty($opt['static']) || app()->editing) return null;
        $b = self::forMedia($m);
        if (!$b) return null;
        // ids: erste Einbindung {K}-{id} (Verweise aus der CSS-Datei), weitere {K}n{Zähler}-{id}
        $key = (string) $b['key'];
        self::$seen[$key] = (self::$seen[$key] ?? 0) + 1;
        $pfx = $key . (self::$seen[$key] > 1 ? 'n' . self::$seen[$key] : '');
        $svg = str_replace('__GM__', $pfx, $b['svg']);
        $svg = (string) preg_replace_callback('~__GMHREF(\d+)__~', fn($x) => e(\Core\Blocks\Runtime::resolveLink((string) ($b['links'][(int) $x[1]] ?? ''))), $svg);
        $cls = trim('cms-gm ' . $b['key'] . ($b['dark'] ? ' cms-scheme-img' : '') . (self::still() ? ' gm-still' : '') . ' ' . (string) ($opt['class'] ?? ''));
        $svg = str_replace('class="__GMCLASS__"', 'class="' . e($cls) . '"', $svg);
        // Name für Screenreader: Alt-Text der Mediathek; dekorativ (leer) und ohne Links/Hotspots → ausgeblendet
        if (trim($alt) === '' && !$b['interactive']) {
            $svg = str_replace(' role="img" aria-label="__GMALT__"', ' aria-hidden="true"', $svg);
        }
        $svg = str_replace('__GMALT__', e(trim($alt) !== '' ? $alt : (string) ($m['title'] ?? '')), $svg);
        $svg = str_replace('__GMPLAY__', e(lt('Animation abspielen')), $svg);
        if (!empty($b['pause'])) $svg = self::pauseBox($svg, $key);
        return '<link rel="stylesheet" href="' . e($b['css']) . '" data-gm-css>' . $svg;
    }

    /**
     * Hülle mit Knopf „Animation anhalten“ (WCAG 2.2.2): ein echter <button> in der Ecke der Grafik, erst sichtbar, wenn das
     * Skript läuft (ohne Skript, bei „Bewegung reduzieren“ und „Dezente Animationen“ aus steht ohnehin das Endbild). Der Zustand
     * wird über die Beschriftung und eine Statusmeldung angesagt.
     */
    private static function pauseBox(string $svg, string $key): string
    {
        $icon = '<svg class="cms-gm-pause__i" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">'
            . '<path class="cms-gm-pause__p" d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/><path class="cms-gm-pause__r" d="M8 5.5v13l11-6.5z"/></svg>';
        return '<span class="cms-gm-box ' . e($key) . '-box">' . $svg
            . '<button type="button" class="cms-gm-pause" hidden data-gm-pause="' . e(lt('Animation anhalten')) . '" data-gm-resume="'
            . e(lt('Animation fortsetzen')) . '" data-gm-paused="' . e(lt('Animation angehalten')) . '" data-gm-running="' . e(lt('Animation läuft'))
            . '" aria-label="' . e(lt('Animation anhalten')) . '">' . $icon . '</button><span class="cms-gm-sr" role="status"></span></span>';
    }

    /** Skript nur auf Seiten, die es brauchen (Auslöser „sobald sichtbar“, Klick, Hotspots, Zählen, Schleifen) */
    public static function inject(string $html): string
    {
        if (!str_contains($html, 'data-gm-js') || str_contains($html, 'data-cms-graphic-motion')) return $html;
        $tag = '<script type="module" src="' . e(asset('js/graphic-motion.js')) . '" data-cms-graphic-motion></script>';
        $pos = stripos($html, '</body>');
        return $pos === false ? $html . $tag : substr($html, 0, $pos) . $tag . substr($html, $pos);
    }

    /**
     * Ausgabe einer Mediendatei erzeugen bzw. aus dem Zwischenspeicher: [key, svg (mit Platzhaltern), css (Adresse), links,
     * interactive, dark, js] – null, wenn die Datei keine Bewegung trägt oder die Arbeitsfassung fehlt.
     */
    public static function forMedia(array $m): ?array
    {
        if (($m['mime'] ?? '') !== Svg::MIME || empty($m['id'])) return null;
        $mk = site()->key . ':' . ($m['_pool'] ?? '') . ':' . $m['id'] . ':' . ($m['file'] ?? '');
        if (array_key_exists($mk, self::$memo)) return self::$memo[$mk];
        $path = Media::path($m);
        $web = is_file($path) ? (string) @file_get_contents($path, false, null, 0, Svg::MAX_BYTES + 1) : '';
        if ($web === '' || !self::isAnimated($web)) return self::$memo[$mk] = null;
        // gebundene Fassung (Core\Graphics\Bind): Datei = Fassung mit Werten, Arbeitsfassung kommt vom Original
        $work = isset($m['_work']) ? (string) $m['_work'] : (($m['_pool'] ?? '') === '' ? Graphics::source('media:' . (int) $m['id'], $web) : null);
        $model = $work !== null ? self::model($work) : null;
        if (!$model) return self::$memo[$mk] = null;
        $pal = Themed::isThemed($web) ? Themed::palette() : [];
        $darkOn = Themed::darkActive();
        $hash = substr(sha1(self::generator() . "\n" . $web . "\n" . json_encode($model) . "\n" . json_encode($pal) . ($darkOn ? 'd' : '')), 0, 10);
        $id = (string) (int) $m['id'];
        $base = 'gm-' . $id . '-' . $hash;
        $cache = site()->storage('cache/grafik-motion');
        $meta = is_file("$cache/$base.json") ? json_decode((string) @file_get_contents("$cache/$base.json"), true) : null;
        $dir = Graphics::dir() . '/m';
        if (!is_array($meta) || !is_file("$dir/$base.css")) {
            $light = $pal ? Themed::render($web, 'light', $pal) : $web;
            $dark = $darkOn ? ($pal ? Themed::render($web, 'dark', $pal) : $web) : null;
            try {
                $out = self::build($light, $dark, $model, 'gm' . $id . $hash, ['w' => (int) ($m['width'] ?? 0), 'h' => (int) ($m['height'] ?? 0)]);
            } catch (\Throwable $e) {
                error_log('[grafik] Motion: ' . $e->getMessage());
                return self::$memo[$mk] = null;
            }
            foreach ([$dir, $cache] as $d) if (!is_dir($d) && !@mkdir($d, 0775, true) && !is_dir($d)) return self::$memo[$mk] = null;
            Svg::guard(Graphics::dir());
            // ältere Fassungen dieser Grafik erst nach einem Tag löschen (zwischengespeicherte Seiten verweisen noch darauf)
            foreach (glob($dir . '/gm-' . $id . '-*.css') ?: [] as $old) if (filemtime($old) < time() - 86400) @unlink($old);
            foreach (glob($cache . '/gm-' . $id . '-*.json') ?: [] as $old) if (filemtime($old) < time() - 86400) @unlink($old);
            $title = str_replace('*/', '', (string) ($m['title'] ?? $m['original_name'] ?? ''));
            if (@file_put_contents("$dir/$base.css", "/* KLXM Studio – Grafik „" . $title . "“, automatisch erzeugt */\n" . $out['css'], LOCK_EX) === false) return self::$memo[$mk] = null;
            $meta = ['key' => $out['key'], 'svg' => $out['svg'], 'links' => $out['links'], 'interactive' => $out['interactive'], 'dark' => $out['dark'], 'js' => $out['js'], 'pause' => $out['pause']];
            @file_put_contents("$cache/$base.json", json_encode($meta), LOCK_EX);
        }
        $meta['css'] = Graphics::url('m/' . $base . '.css');
        return self::$memo[$mk] = $meta;
    }

    /** Für Tests: Zwischenspeicher der Anfrage leeren */
    public static function forget(): void
    {
        self::$memo = [];
        self::$seen = [];
    }

    /**
     * Kennung des Erzeugers: Fassung + Prüfsumme dieser Datei und des Website-Skripts. Gehört zu jedem Dateinamen der Ausgabe –
     * nach einem Update entstehen neue Dateien, alte (gecachte) CSS kann nicht mehr ausgeliefert werden.
     */
    public static function generator(): string
    {
        if (self::$gen !== null) return self::$gen;
        $parts = [(string) self::VERSION, (string) @sha1_file(__FILE__)];
        $js = dirname(__DIR__, 2) . '/resources/js/graphic-motion.js';
        if (is_file($js)) $parts[] = (string) @sha1_file($js);
        return self::$gen = substr(sha1(implode(':', $parts)), 0, 12);
    }

    /** Braucht die Szene einen Knopf zum Anhalten? Automatisch (Laden, Sichtbar) oder per Klick gestartet und länger als 5 s bzw. endlos */
    public static function needsPause(array $scene): bool
    {
        if (!in_array($scene['tr'] ?? 'view', ['load', 'view', 'click'], true)) return false;
        $it = (int) ($scene['it'] ?? 1);
        return $it === 0 || (float) ($scene['dl'] ?? 0) + (float) ($scene['d'] ?? 0) * $it > self::PAUSE_AFTER;
    }

    /**
     * Eigenständiges animiertes SVG (Export für Präsentationen, andere Websites): CSS als <style> in der Datei, spielt beim Laden,
     * ohne Skript (Zählen zeigt den Endwert), Links als absolute Adressen.
     */
    public static function standalone(string $web, string $work): string
    {
        $model = self::model($work);
        if (!$model) return $web;
        $model['scene']['tr'] = 'load';
        foreach ($model['items'] as &$it) {
            if (isset($it['anim']['on'])) unset($it['anim']['on']);
        }
        unset($it);
        $out = self::build($web, null, $model, 'gm' . substr(sha1($web), 0, 8), ['w' => 0, 'h' => 0], true);
        $svg = str_replace(['__GM__', 'class="__GMCLASS__"', '__GMPLAY__'], ['g', 'class="cms-gm ' . $out['key'] . '"', ''], $out['svg']);
        $svg = (string) preg_replace_callback('~__GMHREF(\d+)__~', function ($x) use ($out) {
            $h = \Core\Blocks\Runtime::resolveLink((string) ($out['links'][(int) $x[1]] ?? ''));
            return e(str_starts_with($h, '/') ? abs_url($h) : $h);
        }, $svg);
        $svg = str_replace(' aria-label="__GMALT__"', '', $svg);
        $css = str_replace(']]>', '', $out['css']);
        return (string) preg_replace('~^(<svg\b[^>]*>)~', '$1<style><![CDATA[' . "\n" . $css . ']]></style>', $svg, 1);
    }

    // ================================================================= Aufbau: SVG + CSS

    /**
     * @param array{w: int, h: int} $size
     * @return array{key: string, svg: string, css: string, links: list<string>, interactive: bool, dark: bool, js: bool, pause: bool}
     */
    public static function build(string $web, ?string $dark, array $model, string $key, array $size, bool $standalone = false): array
    {
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($web, LIBXML_NONET | LIBXML_COMPACT) || !$doc->documentElement) throw new \RuntimeException('SVG nicht lesbar');
        $root = $doc->documentElement;
        $K = $key;                     // Wurzel-Klasse, z. B. gm12ab34cd
        $S = '.' . $K;
        $css = [];
        $all = [];
        foreach ($doc->getElementsByTagName('*') as $el) if ($el instanceof \DOMElement) $all[] = $el;

        // 1. Dunkel-Fassung: Farben, die sich unterscheiden, als Regeln je Element
        $darkRules = [];
        if ($dark !== null) {
            $dd = new \DOMDocument();
            if (@$dd->loadXML($dark, LIBXML_NONET | LIBXML_COMPACT)) {
                $dAll = [];
                foreach ($dd->getElementsByTagName('*') as $el) if ($el instanceof \DOMElement) $dAll[] = $el;
                if (count($dAll) === count($all)) {
                    foreach ($all as $i => $el) {
                        $diff = [];
                        foreach (['fill', 'stroke', 'color', 'stop-color', 'flood-color'] as $a) {
                            $lv = self::propOf($el, $a);
                            $dv = self::propOf($dAll[$i], $a);
                            if ($dv !== null && $dv !== $lv) $diff[$a] = $dv;
                        }
                        if ($diff) $darkRules[$i] = $diff;
                    }
                }
            }
        }

        // 2. <style> der Grafik: Regeln mit Präfix in die CSS-Datei (CSP), ids in Selektoren werden Klassen
        $sheet = '';
        foreach ($all as $el) {
            if ($el->localName === 'style') { $sheet .= $el->textContent . "\n"; }
        }
        $idClass = [];
        if ($sheet !== '') {
            $css[] = self::scopeSheet($sheet, $S, $K, $idClass);
        }
        // 3. style-Attribute → Klassen; Klassen mit Präfix; Dunkel-Regeln
        $sn = 0;
        foreach ($all as $i => $el) {
            if ($el->localName === 'style') continue;
            $cls = [];
            foreach (preg_split('~\s+~', trim($el->getAttribute('class'))) ?: [] as $c) if ($c !== '') $cls[] = $K . '-' . $c;
            if (($id = $el->getAttribute('id')) !== '' && isset($idClass[$id])) $cls[] = $K . '-i-' . $idClass[$id];
            $st = trim($el->getAttribute('style'));
            // Verweise url(#…) als Darstellungsattribut (die ids wechseln je Einbindung), der Rest als Klasse in die CSS-Datei
            if (str_contains($st, 'url(')) {
                $keep = [];
                foreach (explode(';', $st) as $d) {
                    [$p, $v] = array_map('trim', explode(':', $d, 2)) + [1 => ''];
                    if ($v !== '' && str_contains($v, 'url(') && in_array($p, ['fill', 'stroke', 'filter', 'clip-path', 'mask', 'marker-start', 'marker-mid', 'marker-end'], true)) $el->setAttribute($p, $v);
                    elseif (trim($d) !== '') $keep[] = trim($d);
                }
                $st = implode(';', $keep);
            }
            if ($st !== '') {
                $c = $K . '-s' . (++$sn);
                $css[] = ($el === $root ? $S : $S . ' .' . $c) . '{' . $st . '}';
                if ($el !== $root) $cls[] = $c;
                $el->removeAttribute('style');
            }
            if (isset($darkRules[$i])) {
                $c = $K . '-d' . $i;
                $cls[] = $c;
                $decl = implode(';', array_map(fn($a, $v) => $a . ':' . $v, array_keys($darkRules[$i]), $darkRules[$i]));
                $css[] = $S . '.is-dark .' . $c . '{' . $decl . '}';
                $css[] = '@media (prefers-color-scheme:dark){' . $S . ':not(.is-light) .' . $c . '{' . $decl . '}}';
            }
            if ($el === $root) continue;
            if ($cls) $el->setAttribute('class', implode(' ', $cls)); else $el->removeAttribute('class');
        }
        foreach ($all as $el) if ($el->localName === 'style' && $el->parentNode) $el->parentNode->removeChild($el);

        // 4. Elemente des Modells: Hülle, Farben, Zählen, Links, Tooltips, Hotspots
        $byId = [];
        foreach ($all as $el) if ($el->getAttribute('id') !== '') $byId[$el->getAttribute('id')] = $el;
        $sc = $model['scene'];
        $links = [];
        $interactive = false;
        $js = in_array($sc['tr'], ['view', 'click'], true) || $sc['it'] !== 1;
        // Knopf „Animation anhalten“: braucht das Skript; beim Auslöser „beim Laden“ spielt die Bewegung dann erst mit dem Skript
        // (ohne Skript bleibt das Endbild stehen – eine endlose Bewegung ohne Halt-Möglichkeit gäbe es sonst)
        $pause = !$standalone && self::needsPause($sc);
        if ($pause) $js = true;
        $cssScene = $pause && $sc['tr'] === 'load' ? ['tr' => 'armed'] + $sc : $sc;
        $tips = [];
        $hotIdx = [];
        $n = 0;
        foreach ($model['items'] as $id => $it) {
            if (!$it['hot'] || !isset($byId[$id])) continue;
            $hotIdx[$id] = count($hotIdx) + 1;
        }
        foreach ($model['items'] as $id => $it) {
            $el = $byId[$id] ?? null;
            if (!$el || !$el->parentNode || $el === $root) continue;
            $n++;
            $a = $it['anim'];
            $outer = $el;
            if ($a) {
                $wrapProps = array_intersect(self::WRAP_PROPS, array_keys($a['k']));
                if ($wrapProps) {
                    $g = $doc->createElementNS(self::NS, 'g');
                    $g->setAttribute('class', 'gm-a gm-a' . $n);
                    $el->parentNode->replaceChild($g, $el);
                    $g->appendChild($el);
                    $outer = $g;
                    if (isset($a['k']['w'])) {
                        foreach (array_merge([$el], iterator_to_array($el->getElementsByTagName('*'))) as $s) {
                            if ($s instanceof \DOMElement && in_array($s->localName, self::SHAPES, true)) $s->setAttribute('pathLength', '1');
                        }
                    }
                }
                if (array_intersect(self::COLOR_PROPS, array_keys($a['k']))) $el->setAttribute('class', trim($el->getAttribute('class') . ' gm-c gm-c' . $n));
                if (isset($a['n']) && $el->localName === 'text' && !$standalone) {
                    $c = $a['n'];
                    $el->setAttribute('data-gm-count', implode(' ', [self::f($c['a']), self::f($c['b']), $c['d'], $c['g'] === ' ' ? 's' : ($c['g'] === "\u{202F}" ? 'n' : ($c['g'] === '' ? '-' : $c['g'])), $c['p'], self::f($c['t0']), self::f($c['t1']), str_replace(' ', '', $c['e'])]));
                    $js = true;
                }
                if (isset($a['on'], $hotIdx[$a['on']])) $js = true;
                $css[] = self::itemCss($S, $n, $a, $cssScene, $hotIdx, $dark !== null || $standalone);
            }
            if ($it['link'] !== '') {
                $aEl = $doc->createElementNS(self::NS, 'a');
                $aEl->setAttribute('href', '__GMHREF' . count($links) . '__');
                $links[] = $it['link'];
                $aEl->setAttribute('class', 'gm-l');
                $name = $it['label'] !== '' ? $it['label'] : ($it['tip'] !== '' ? $it['tip'] : $it['name']);
                if ($name !== '') $aEl->setAttribute('aria-label', $name);
                $outer->parentNode->replaceChild($aEl, $outer);
                $aEl->appendChild($outer);
                $outer = $aEl;
                $interactive = true;
            } elseif ($it['hot'] && isset($hotIdx[$id])) {
                $outer->setAttribute('role', 'button');
                $outer->setAttribute('tabindex', '0');
                $outer->setAttribute('aria-pressed', 'false');
                $outer->setAttribute('data-gm-hot', (string) $hotIdx[$id]);
                $name = $it['label'] !== '' ? $it['label'] : ($it['tip'] !== '' ? $it['tip'] : $it['name']);
                if ($name !== '') $outer->setAttribute('aria-label', $name);
                $outer->setAttribute('class', trim($outer->getAttribute('class') . ' gm-hot'));
                $interactive = true;
                $js = true;
            }
            $outer->setAttribute('class', trim($outer->getAttribute('class') . ' gm-e' . $n));
            if ($it['tip'] !== '') {
                $t = $doc->createElementNS(self::NS, 'title');
                $t->appendChild($doc->createTextNode($it['tip']));
                $outer->insertBefore($t, $outer->firstChild);
                if ($it['tipbox']) $tips[$n] = [$it['tip'], $it['tipbox']];
            }
        }
        // sichtbare Tooltips (Schild) oben auf der Grafik – erscheinen bei Zeigen/Fokus (CSS :has)
        foreach ($tips as $i => [$text, $b]) {
            $g = $doc->createElementNS(self::NS, 'g');
            $g->setAttribute('class', 'gm-tip gm-tip' . $i);
            $g->setAttribute('aria-hidden', 'true');
            $r = $doc->createElementNS(self::NS, 'rect');
            foreach (['x' => $b[0], 'y' => $b[1], 'width' => $b[2], 'height' => $b[3], 'rx' => min(6, $b[3] / 4)] as $k => $v) $r->setAttribute($k, self::f($v));
            $r->setAttribute('fill', '#1F2430');
            $g->appendChild($r);
            $tx = $doc->createElementNS(self::NS, 'text');
            $fs = max(6, $b[3] * 0.5);
            foreach (['x' => $b[0] + $b[2] / 2, 'y' => $b[1] + $b[3] / 2 + $fs * 0.35, 'font-size' => $fs] as $k => $v) $tx->setAttribute($k, self::f($v));
            $tx->setAttribute('text-anchor', 'middle');
            $tx->setAttribute('fill', '#FFFFFF');
            $tx->setAttribute('font-family', 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif');
            $tx->appendChild($doc->createTextNode($text));
            $g->appendChild($tx);
            $root->appendChild($g);
            $css[] = $S . ' .gm-tip' . $i . '{opacity:0;transition:opacity .15s;pointer-events:none}';
            $css[] = $S . ':has(.gm-e' . $i . ':hover,.gm-e' . $i . ':focus-visible) .gm-tip' . $i . '{opacity:1}';
        }

        // 5. ids je Einbindung eindeutig (__GM__ wird bei der Ausgabe ersetzt), Verweise mit
        $ids = [];
        foreach ($doc->getElementsByTagName('*') as $el) {
            if ($el instanceof \DOMElement && $el->getAttribute('id') !== '') $ids[$el->getAttribute('id')] = true;
        }
        if ($ids) {
            foreach ($doc->getElementsByTagName('*') as $el) {
                if (!$el instanceof \DOMElement) continue;
                foreach (iterator_to_array($el->attributes) as $attr) {
                    /** @var \DOMAttr $attr */
                    $v = $attr->value;
                    $nv = $v;
                    if ($attr->localName === 'id' && isset($ids[$v])) $nv = '__GM__-' . $v;
                    elseif ($attr->localName === 'href' && str_starts_with($v, '#') && isset($ids[substr($v, 1)])) $nv = '#__GM__-' . substr($v, 1);
                    elseif (in_array($attr->localName, ['aria-labelledby', 'aria-describedby'], true)) $nv = implode(' ', array_map(fn($x) => isset($ids[$x]) ? '__GM__-' . $x : $x, preg_split('~\s+~', trim($v)) ?: []));
                    elseif (str_contains($v, 'url(')) $nv = (string) preg_replace_callback('~url\(\s*([\'"]?)#([^\'")\s]+)\1\s*\)~', fn($x) => isset($ids[$x[2]]) ? 'url(#__GM__-' . $x[2] . ')' : $x[0], $v);
                    if ($nv !== $v) {
                        if ($attr->namespaceURI) $el->setAttributeNS($attr->namespaceURI, $attr->nodeName, $nv); else $el->setAttribute($attr->localName, $nv);
                    }
                }
            }
            // url(#…) in den erzeugten Regeln (aus <style>) kann nicht je Einbindung wechseln: es gilt die erste Einbindung der Seite
            // (ihre ids heißen {K}-{id}, Inhalt identisch)
            foreach ($css as &$rule) $rule = (string) preg_replace_callback('~url\(\s*([\'"]?)#([^\'")\s]+)\1\s*\)~', fn($x) => isset($ids[$x[2]]) ? 'url(#' . $K . '-' . $x[2] . ')' : $x[0], $rule);
            unset($rule);
        }

        // 6. Wurzel: Klassen, Maße, Rolle, Auslöser
        $root->setAttribute('class', '__GMCLASS__');
        if ($size['w'] > 0 && $size['h'] > 0) { $root->setAttribute('width', (string) $size['w']); $root->setAttribute('height', (string) $size['h']); }
        foreach (['role', 'aria-label', 'aria-labelledby', 'aria-hidden', 'tabindex'] as $a) $root->removeAttribute($a);
        if ($sc['tr'] === 'click' && !$standalone) {
            $root->setAttribute('role', 'button');
            $root->setAttribute('tabindex', '0');
            $root->setAttribute('aria-label', '__GMALT__ – __GMPLAY__');
            $interactive = true;
        } elseif ($interactive) {
            $root->setAttribute('role', 'group');
            $root->setAttribute('aria-label', '__GMALT__');
        } else {
            $root->setAttribute('role', 'img');
            $root->setAttribute('aria-label', '__GMALT__');
        }
        if (!$standalone) {
            $root->setAttribute('data-gm', $sc['tr']);
            if ($sc['it'] !== 1) $root->setAttribute('data-gm-loop', '');
            if ($js) $root->setAttribute('data-gm-js', '');
            $root->setAttribute('data-gm-t', self::f($sc['d']) . ' ' . self::f($sc['dl']) . ' ' . $sc['it'] . ' ' . $sc['dir']);
        }

        // 7. Grundregeln: Größe wie ein Bild, angehalten außerhalb des Bildes, reduzierte Bewegung/Design-Schalter = Endzustand
        $base = [$S . '{display:block;max-width:100%;height:auto;overflow:hidden}'];
        if ($interactive) $base[] = $S . ' .gm-l,' . $S . ' .gm-hot{cursor:pointer;text-decoration:none}' . $S . ' .gm-l:focus-visible,' . $S . ' .gm-hot:focus-visible{outline:2px solid currentColor;outline-offset:2px}';
        if ($sc['tr'] === 'click') $base[] = $S . '[role=button]{cursor:pointer}';
        $anim = $S . ' .gm-a,' . $S . ' .gm-c,' . $S . ' .gm-c *';
        $base[] = $S . ':is(.is-off,.is-paused) :is(.gm-a,.gm-c,.gm-c *){animation-play-state:paused!important}';
        if ($pause) {
            // Knopf in der Ecke der Grafik, Farben aus dem Kit (wie Suche und Hinweise), Rückfall neutral; mindestens 24 px (WCAG 2.5.8)
            $B = $S . '-box';
            $base[] = $B . '{position:relative;display:block;width:fit-content;max-width:100%}'
                . $B . ' .cms-gm-pause{position:absolute;right:8px;bottom:8px;z-index:1;display:inline-grid;place-items:center;box-sizing:border-box;width:36px;height:36px;margin:0;padding:0;'
                . 'border:2px solid currentColor;border-radius:999px;background:var(--kit-bg,#fff);color:var(--kit-ink,#1F2430);cursor:pointer;'
                . 'box-shadow:0 1px 4px rgb(0 0 0 / .25);opacity:.92;transition:opacity .15s}'
                . $B . ' .cms-gm-pause:hover{opacity:1}'
                . $B . ' .cms-gm-pause:focus-visible{opacity:1;outline:3px solid var(--kit-accent,#1d4ed8);outline-offset:2px}'
                . $B . ' .cms-gm-pause[hidden]{display:none}'
                . $B . ' .cms-gm-pause__i{display:block;width:16px;height:16px;fill:currentColor}'
                . $B . ' .cms-gm-pause__r,' . $B . ' .cms-gm-pause.is-paused .cms-gm-pause__p{display:none}'
                . $B . ' .cms-gm-pause.is-paused .cms-gm-pause__r{display:inline}'
                . $B . ' .cms-gm-sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip-path:inset(50%);white-space:nowrap;border:0}'
                . '@media (prefers-reduced-motion:reduce){' . $B . ' .cms-gm-pause{display:none}}'
                . '@media (forced-colors:active){' . $B . ' .cms-gm-pause{border-color:ButtonText;background:ButtonFace;color:ButtonText}}';
        }
        $end = self::endCss($S, $model);
        $base[] = '@media (prefers-reduced-motion:reduce){' . $anim . '{animation:none!important}' . $end . '}';
        $base[] = $S . '.gm-still :is(.gm-a,.gm-c,.gm-c *){animation:none!important}' . str_replace($S . ' ', $S . '.gm-still ', $end);
        $svg = $doc->saveXML($root);
        // Grundregeln vorn, Endzustand (reduzierte Bewegung, Design-Schalter) zuletzt – er gewinnt gegen Ruhe/Anfang
        $tail = array_splice($base, -2);
        return ['key' => $K, 'svg' => (string) $svg, 'css' => implode("\n", array_merge($base, array_filter($css), $tail)) . "\n", 'links' => $links,
            'interactive' => $interactive, 'dark' => $dark !== null, 'js' => $js, 'pause' => $pause];
    }

    /** Regeln je Element: Ruhe/Anfang/Abspielen und @keyframes je Eigenschaft */
    private static function itemCss(string $S, int $n, array $a, array $sc, array $hotIdx, bool $darkOn): string
    {
        $out = [];
        $wrap = $S . ' .gm-a' . $n;
        $col = $S . ' .gm-c' . $n . ',' . $S . ' .gm-c' . $n . ' *';
        $name = substr($S, 1) . '-' . $n;
        $hover = ($a['tr'] ?? '') === 'hover' || $sc['tr'] === 'hover';
        $hot = isset($a['on'], $hotIdx[$a['on']]) ? $hotIdx[$a['on']] : 0;
        $iter = $hot ? '1' : ($sc['it'] === 0 ? 'infinite' : (string) $sc['it']);
        $dir = $hot ? 'normal' : $sc['dir'];
        // Hotspot: eigene Dauer = letztes Schlüsselbild (an/aus soll sofort reagieren, nicht die ganze Szene abwarten)
        $D = $sc['d'];
        if ($hot) { $D = 0.05; foreach ($a['k'] as $keys) $D = max($D, (float) end($keys)[0]); }
        $dur = self::f($D) . 's';
        $delay = $hot ? '0s' : self::f($sc['dl']) . 's';
        $wrapAnims = $colAnims = [];
        $endW = $startW = $endC = $startC = [];
        $frames = '';
        foreach ($a['k'] as $prop => $keys) {
            $isCol = in_array($prop, self::COLOR_PROPS, true);
            $kn = $name . '-' . $prop;
            $frames .= self::keyframes($kn, $keys, $prop, $D, false);
            if ($isCol) {
                $frames .= self::keyframes($kn . 'd', $keys, $prop, $D, true);
                $colAnims[] = $kn;
                $endC[] = self::decl($prop, end($keys), false);
                $startC[] = self::decl($prop, $keys[0], false);
            } else {
                $wrapAnims[] = $kn;
                $endW[] = self::decl($prop, end($keys), false);
                $startW[] = self::decl($prop, $keys[0], false);
            }
        }
        $line = fn(array $names, string $suffix = '') => implode(',', array_map(fn($x) => $x . $suffix . ' ' . $dur . ' linear ' . $delay . ' ' . $iter . ' ' . $dir . ' both', $names));
        $setup = '';
        if ($wrapAnims) {
            $setup .= $wrap . '{transform-box:view-box;transform-origin:' . self::f($a['p'][0]) . 'px ' . self::f($a['p'][1]) . 'px' . (isset($a['k']['w']) ? ';stroke-dasharray:1' : '') . '}';
        }
        // Ruhe: Ende (Zeigen-Auslöser: Anfang – die Bewegung gehört zum Zeigen)
        $rest = fn(string $sel, array $decl) => $decl ? $sel . '{' . implode(';', $decl) . '}' : '';
        $out[] = $setup;
        $out[] = $rest($wrap, $hover ? $startW : $endW) . $rest($col, $hover ? $startC : $endC);
        $playW = $wrapAnims ? '{animation:' . $line($wrapAnims) . '}' : '';
        $playC = $colAnims ? '{animation:' . $line($colAnims) . '}' : '';
        $playCd = $colAnims ? '{animation:' . $line($colAnims, 'd') . '}' : '';
        $sel = function (string $state) use ($S, $n): array {
            return [$S . $state . ' .gm-a' . $n, $S . $state . ' .gm-c' . $n . ',' . $S . $state . ' .gm-c' . $n . ' *'];
        };
        if ($hot) {
            // Hotspot: an = vorwärts, wieder aus = rückwärts (eigener Name, damit die Animation neu startet)
            [$wA, $cA] = $sel('.is-armed');
            $out[] = $rest($wA, $startW) . $rest($cA, $startC);
            [$wP, $cP] = $sel('.is-h' . $hot);
            if ($playW) $out[] = $wP . $playW;
            if ($playC) $out[] = $cP . $playC;
            [$wR, $cR] = $sel('.was-h' . $hot . ':not(.is-h' . $hot . ')');
            $rev = fn(array $names, string $sfx = '') => implode(',', array_map(fn($x) => $x . $sfx . 'b ' . $dur . ' linear 0s 1 reverse both', $names));
            if ($wrapAnims) { $out[] = $wR . '{animation:' . $rev($wrapAnims) . '}'; foreach ($a['k'] as $p => $k) if (!in_array($p, self::COLOR_PROPS, true)) $frames .= self::keyframes($name . '-' . $p . 'b', $k, $p, $D, false); }
            if ($colAnims) { $out[] = $cR . '{animation:' . $rev($colAnims) . '}'; foreach ($a['k'] as $p => $k) if (in_array($p, self::COLOR_PROPS, true)) $frames .= self::keyframes($name . '-' . $p . 'b', $k, $p, $D, false); }
        } elseif ($hover) {
            // Zeigen auf das Element (gm-eN: Link, Hülle oder Element selbst) bzw. auf die ganze Grafik
            $on = ($a['tr'] ?? '') === 'hover' ? [' .gm-e' . $n . ':is(:hover,:focus-visible) ', '.gm-e' . $n . ':is(:hover,:focus-visible)'] : null;
            $w = $on ? $S . $on[0] . '.gm-a' . $n . ',' . $S . ' .gm-a' . $n . $on[1] : $S . ':is(:hover,:focus-within) .gm-a' . $n;
            $c = $on ? $S . $on[0] . '.gm-c' . $n . ',' . $S . $on[0] . '.gm-c' . $n . ' *,' . $S . ' .gm-c' . $n . $on[1]
                : $S . ':is(:hover,:focus-within) .gm-c' . $n . ',' . $S . ':is(:hover,:focus-within) .gm-c' . $n . ' *';
            if ($playW) $out[] = $w . $playW;
            if ($playC) $out[] = $c . $playC;
        } elseif ($sc['tr'] === 'load') {
            if ($playW) $out[] = $wrap . $playW;
            if ($playC) $out[] = $col . $playC;
        } else {
            [$wA, $cA] = $sel('.is-armed:not(.is-play)');
            $out[] = $rest($wA, $startW) . $rest($cA, $startC);
            [$wP, $cP] = $sel('.is-play');
            if ($playW) $out[] = $wP . $playW;
            if ($playC) $out[] = $cP . $playC;
        }
        // Dunkel: Farb-Schlüsselbilder mit Dunkel-Werten
        $hasDark = false;
        foreach (self::COLOR_PROPS as $p) foreach ($a['k'][$p] ?? [] as $k) if (!empty($k[3]) && $k[3] !== $k[1]) $hasDark = true;
        if ($colAnims && $hasDark && $darkOn) {
            $dEnd = $dStart = [];
            foreach (self::COLOR_PROPS as $p) if (isset($a['k'][$p])) { $dEnd[] = self::decl($p, end($a['k'][$p]), true); $dStart[] = self::decl($p, $a['k'][$p][0], true); }
            foreach ([$S . '.is-dark', '@' . $S . ':not(.is-light)'] as $pre) {
                $media = $pre[0] === '@';
                $p = $media ? substr($pre, 1) : $pre;
                $x = $rest($p . ' .gm-c' . $n . ',' . $p . ' .gm-c' . $n . ' *', $hover ? $dStart : $dEnd);
                if ($hot) {
                    $x .= $rest($p . '.is-armed .gm-c' . $n . ',' . $p . '.is-armed .gm-c' . $n . ' *', $dStart);
                    $x .= $p . '.is-h' . $hot . ' .gm-c' . $n . ',' . $p . '.is-h' . $hot . ' .gm-c' . $n . ' *' . $playCd;
                } elseif ($hover) {
                    $x .= (($a['tr'] ?? '') === 'hover' ? $p . ' .gm-e' . $n . ':is(:hover,:focus-visible) .gm-c' . $n . ',' . $p . ' .gm-c' . $n . '.gm-e' . $n . ':is(:hover,:focus-visible)'
                        : $p . ':is(:hover,:focus-within) .gm-c' . $n) . $playCd;
                } elseif ($sc['tr'] === 'load') {
                    $x .= $p . ' .gm-c' . $n . ',' . $p . ' .gm-c' . $n . ' *' . $playCd;
                } else {
                    $x .= $rest($p . '.is-armed:not(.is-play) .gm-c' . $n . ',' . $p . '.is-armed:not(.is-play) .gm-c' . $n . ' *', $dStart);
                    $x .= $p . '.is-play .gm-c' . $n . ',' . $p . '.is-play .gm-c' . $n . ' *' . $playCd;
                }
                $out[] = $media ? '@media (prefers-color-scheme:dark){' . $x . '}' : $x;
            }
        }
        return implode("\n", array_filter($out)) . "\n" . $frames;
    }

    /** Endzustand aller Elemente (reduzierte Bewegung, Design-Schalter aus) – auch für Zeigen-Auslöser */
    private static function endCss(string $S, array $model): string
    {
        $out = '';
        $n = 0;
        foreach ($model['items'] as $it) {
            $n++;
            $a = $it['anim'];
            if (!$a) continue;
            $w = $c = [];
            foreach ($a['k'] as $p => $keys) {
                if (in_array($p, self::COLOR_PROPS, true)) $c[] = self::decl($p, end($keys), false); else $w[] = self::decl($p, end($keys), false);
            }
            if ($w) $out .= $S . ' .gm-a' . $n . '{' . implode(';', $w) . '}';
            if ($c) $out .= $S . ' .gm-c' . $n . '{' . implode(';', $c) . '}';
        }
        return $out;
    }

    /** @keyframes einer Eigenschaft – Verlauf je Abschnitt als animation-timing-function des Schlüsselbilds */
    private static function keyframes(string $name, array $keys, string $prop, float $dur, bool $dark): string
    {
        $f = '@keyframes ' . $name . '{';
        $first = $keys[0];
        if ($first[0] > 0) $f .= '0%{' . self::decl($prop, $first, $dark) . '}';
        $last = count($keys) - 1;
        foreach ($keys as $i => $k) {
            $pct = $dur > 0 ? min(100, $k[0] / $dur * 100) : 0;
            $e = $prop === 'pos' ? $k[3] : $k[2];
            $f .= self::f($pct, 3) . '%{' . self::decl($prop, $k, $dark) . ($i < $last ? ';animation-timing-function:' . $e : '') . '}';
        }
        if (end($keys)[0] < $dur) $f .= '100%{' . self::decl($prop, end($keys), $dark) . '}';
        return $f . "}\n";
    }

    /** Deklaration eines Schlüsselbilds */
    private static function decl(string $prop, array $k, bool $dark): string
    {
        if (!$k) return '';
        return match ($prop) {
            'pos' => 'translate:' . self::f($k[1]) . 'px ' . self::f($k[2]) . 'px',
            's' => 'scale:' . self::f($k[1], 4),
            'r' => 'rotate:' . self::f($k[1]) . 'deg',
            'o' => 'opacity:' . self::f($k[1], 4),
            'w' => 'stroke-dashoffset:' . self::f(1 - $k[1], 4),
            'f' => 'fill:' . ($dark && !empty($k[3]) ? $k[3] : $k[1]),
            'c' => 'stroke:' . ($dark && !empty($k[3]) ? $k[3] : $k[1]),
            default => '',
        };
    }

    /**
     * <style> der Grafik mit Wurzel-Klasse davor: „.a{…}“ → „.gmK .gmK-a{…}“, „#x“ → Klasse, „svg …“ → Wurzel. @media bleibt.
     * @param array<string, string> $idClass ids, die in Selektoren vorkommen (→ Klasse gmK-i-{id})
     */
    private static function scopeSheet(string $css, string $S, string $K, array &$idClass): string
    {
        $out = '';
        $i = 0;
        $len = strlen($css);
        while ($i < $len) {
            $brace = strpos($css, '{', $i);
            if ($brace === false) break;
            $head = trim(substr($css, $i, $brace - $i));
            $depth = 1;
            $j = $brace + 1;
            while ($j < $len && $depth > 0) { if ($css[$j] === '{') $depth++; elseif ($css[$j] === '}') $depth--; $j++; }
            $body = substr($css, $brace + 1, $j - $brace - 2);
            $i = $j;
            if ($head === '') continue;
            if ($head[0] === '@') {
                $inner = self::scopeSheet($body, $S, $K, $idClass);
                if ($inner !== '') $out .= $head . '{' . $inner . '}';
                continue;
            }
            $sels = [];
            foreach (explode(',', $head) as $sel) {
                $sel = trim($sel);
                if ($sel === '') continue;
                $sel = (string) preg_replace_callback('~\.(-?[_a-zA-Z][\w-]*)~', fn($m) => '.' . $K . '-' . $m[1], $sel);
                $sel = (string) preg_replace_callback('~#([\w-]+)~', function ($m) use ($K, &$idClass) { $idClass[$m[1]] = $m[1]; return '.' . $K . '-i-' . $m[1]; }, $sel);
                if (preg_match('~^svg\b~', $sel)) $sel = $S . substr($sel, 3);
                elseif (preg_match('~^(:root|html|body)\b~', $sel)) $sel = $S;
                else $sel = $S . ' ' . $sel;
                $sels[] = $sel;
            }
            if ($sels) $out .= implode(',', $sels) . '{' . trim($body) . '}';
        }
        return $out;
    }

    /** Farbe eines Elements (Attribut oder Eigenschaft im style) */
    private static function propOf(\DOMElement $el, string $a): ?string
    {
        if (preg_match('~(?:^|;)\s*' . preg_quote($a, '~') . '\s*:\s*([^;]+)~', $el->getAttribute('style'), $m)) return trim($m[1]);
        return $el->hasAttribute($a) ? $el->getAttribute($a) : null;
    }

    // ================================================================= Hilfen

    private static function num(mixed $v, float $min, float $max): float
    {
        $f = is_numeric($v) ? (float) $v : $min;
        if (!is_finite($f)) $f = $min;
        return round(max($min, min($max, $f)), 4);
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

    /** Zahl kurz (ohne überflüssige Nullen) */
    public static function f(float|int $n, int $dec = 3): string
    {
        $s = rtrim(rtrim(number_format((float) $n, $dec, '.', ''), '0'), '.');
        return $s === '-0' || $s === '' ? '0' : $s;
    }
}
