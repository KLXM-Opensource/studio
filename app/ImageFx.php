<?php
declare(strict_types=1);

namespace Core;

/**
 * Bild anpassen (Effekte, Sättigung, Helligkeit, Kontrast, Schärfe/Unschärfe) – zerstörungsfrei per CSS-Filter.
 *
 *  - Original und Größen bleiben unverändert; die Anpassung wirkt beim Anzeigen über Klassen am <img>
 *    (strenge CSP: keine style-Attribute). Stylesheet: resources/css/image-fx.css → public/assets/css/image-fx.css,
 *    wird nur in Seiten eingebunden, die angepasste Bilder enthalten (inject()).
 *  - Zwei Ebenen mit denselben Möglichkeiten:
 *      1. global je Bild (Mediathek): Spalte media.adjust
 *      2. je Einbindung im Block: data._fx = {"image": "…", "items.2.image": "…"} (Feldpfad → Anpassung)
 *    Die Einbindung ersetzt die globale Einstellung vollständig (kein Addieren). „none“ = an dieser Stelle ohne Anpassung.
 *  - Speicherformat (kompakt, kanonisch): Effekt zuerst, dann s/b/c in Prozent, zuletzt die Schärfe „sharp{-100…100}“
 *    (negativ = weichzeichnen, positiv = schärfen), Standardwerte (100 % bzw. Schärfe 0) entfallen,
 *    z. B. „sepia s120 c110“, „s80 sharp40“, „gray sharp-60“. Leer = keine Anpassung.
 *  - Schärfe/Unschärfe: SVG-Filter (feGaussianBlur bzw. feConvolveMatrix) in einem versteckten <svg> im Dokument (defs()),
 *    per filter:url(#ifx-sharp-p4) am Ende derselben Filterkette. Bewusst kein externes „fx-filters.svg#…“ (Safari/WebKit
 *    und Chrome ignorieren externe Filter-Verweise) und kein CSS-blur(): dessen Rand läuft halbtransparent aus bzw. wirkt in
 *    WebKit fleckig; der SVG-Filter setzt die Deckkraft wieder auf 1 und bleibt im Bildrahmen (Filterbereich = Bild).
 *    Das <svg> ist Markup, kein Stil – CSP-konform. Nur in Seiten, die es brauchen (inject()).
 *  - Blöcke kennen keine Feldpfade: Core\Theme::renderBlock() setzt je Block eine Zuordnung Medien-ID → Anpassung
 *    (enter()/leave()), Media::pictureOf() fragt sie ab (classFor()). Kommt dasselbe Bild in einem Block mehrfach vor,
 *    gilt an allen Stellen dieses Blocks die Anpassung des ersten Pfads (sortiert: „image“ vor „items.0.image“).
 */
final class ImageFx
{
    /** Effekte (Voreinstellungen) → Bezeichnung */
    public const PRESETS = [
        'gray' => 'S/W', 'sepia' => 'Sepia', 'warm' => 'Warm', 'cool' => 'Kühl',
        'muted' => 'Entsättigt', 'vivid' => 'Kräftig', 'contrast' => 'Kontrast',
    ];
    /** Regler in Prozent: [min, max], Schrittweite STEP */
    public const RANGES = ['s' => [0, 200], 'b' => [50, 150], 'c' => [50, 150]];
    public const STEP = 10;
    /** Schärfe: -100 (weich) … 0 … +100 (scharf), Schrittweite STEP → Klassen ifx-sharp-m1…m10 / ifx-sharp-p1…p10 */
    public const SHARP = [-100, 100];
    /** Weichzeichnen je Stufe in px (Stufe 10 = 4 px) und Schärfen je Stufe (Gewicht der Nachbarn im 3×3-Kern) */
    public const BLUR_PX = 0.4;
    public const SHARPEN = 0.06;
    /** Ausdrücklich ohne Anpassung (nur für Einbindungen sinnvoll: hebt die globale Einstellung auf) */
    public const NONE = 'none';

    /** Stapel der Block-Zuordnungen [Medien-ID => Anpassung] (verschachtelte Blöcke) */
    private static array $stack = [];

    /**
     * Anpassung zerlegen. @return ?array{p: ?string, s: int, b: int, c: int, k: int, none: bool} null = ungültig
     * (k = Schärfe, -100 … 100)
     */
    public static function parse(?string $v): ?array
    {
        $out = ['p' => null, 's' => 100, 'b' => 100, 'c' => 100, 'k' => 0, 'none' => false];
        $v = trim((string) $v);
        if ($v === '') return $out;
        if (strlen($v) > 60) return null;
        $seen = [];
        foreach (preg_split('~[\s,;]+~', strtolower($v)) as $tok) {
            if ($tok === '') continue;
            if ($tok === self::NONE) {
                $key = 'none';
            } elseif (isset(self::PRESETS[$tok])) {
                $key = 'p';
            } elseif (preg_match('~^([sbc])(\d{1,3})$~', $tok, $m)) {
                $key = $m[1];
                $n = (int) $m[2];
                [$min, $max] = self::RANGES[$key];
                if ($n < $min || $n > $max || $n % self::STEP !== 0) return null;
            } elseif (preg_match('~^sharp([+-]?\d{1,3})$~', $tok, $m)) {
                $key = 'k';
                $n = (int) $m[1];
                if ($n < self::SHARP[0] || $n > self::SHARP[1] || $n % self::STEP !== 0) return null;
            } else {
                return null;
            }
            if (isset($seen[$key])) return null;   // doppelt, z. B. „sepia gray“
            $seen[$key] = true;
            if ($key === 'none') $out['none'] = true;
            elseif ($key === 'p') $out['p'] = $tok;
            else $out[$key] = $n;
        }
        // „none“ steht allein
        if ($out['none'] && count($seen) > 1) return null;
        return $out;
    }

    /**
     * Kanonische Schreibweise (oder null = ungültig). Leer = keine Anpassung.
     * Nimmt auch ein Array {p|preset, s|saturation, b|brightness, c|contrast, k|sharpness} (z. B. aus der API).
     */
    public static function normalize(mixed $in): ?string
    {
        if (is_array($in)) {
            $parts = [];
            $p = (string) ($in['p'] ?? $in['preset'] ?? '');
            if ($p !== '' && $p !== 'original') $parts[] = $p;
            foreach (['s' => 'saturation', 'b' => 'brightness', 'c' => 'contrast'] as $k => $long) {
                $n = $in[$k] ?? $in[$long] ?? null;
                if ($n !== null && $n !== '') $parts[] = $k . (int) $n;
            }
            $n = $in['k'] ?? $in['sharpness'] ?? $in['sharp'] ?? null;
            if ($n !== null && $n !== '') $parts[] = 'sharp' . (int) $n;
            $in = implode(' ', $parts);
        }
        if ($in !== null && !is_string($in)) return null;
        $a = self::parse($in);
        if ($a === null) return null;
        if ($a['none']) return self::NONE;
        $parts = $a['p'] !== null ? [$a['p']] : [];
        foreach (['s', 'b', 'c'] as $k) {
            if ($a[$k] !== 100) $parts[] = $k . $a[$k];
        }
        if ($a['k'] !== 0) $parts[] = 'sharp' . $a['k'];
        return implode(' ', $parts);
    }

    /** CSS-Klassen einer Anpassung („ifx ifx-sepia ifx-s12 …“), leer = keine */
    public static function classes(?string $v): string
    {
        $a = self::parse($v);
        if (!$a || $a['none']) return '';
        $c = [];
        if ($a['p'] !== null) $c[] = 'ifx-' . $a['p'];
        foreach (['s', 'b', 'c'] as $k) {
            if ($a[$k] !== 100) $c[] = 'ifx-' . $k . intdiv($a[$k], self::STEP);
        }
        if ($a['k'] !== 0) $c[] = self::sharpClass($a['k']);
        return $c ? 'ifx ' . implode(' ', $c) : '';
    }

    /** Lesbare Kurzbeschreibung (Verwaltung), z. B. „Sepia · Sättigung 120 %“ */
    public static function label(?string $v): string
    {
        $a = self::parse($v);
        if (!$a) return '';
        if ($a['none']) return __('Ohne Anpassung');
        $out = $a['p'] !== null ? [self::presetLabel($a['p'])] : [];
        foreach (['s' => __('Sättigung'), 'b' => __('Helligkeit'), 'c' => __('Kontrast')] as $k => $l) {
            if ($a[$k] !== 100) $out[] = $l . ' ' . $a[$k] . ' %';
        }
        if ($a['k'] !== 0) $out[] = __('Schärfe') . ' ' . ($a['k'] > 0 ? '+' : '−') . abs($a['k']);
        return implode(' · ', $out);
    }

    /** Klasse der Schärfe-Stufe: 40 → „ifx-sharp-p4“, -60 → „ifx-sharp-m6“ (zugleich id des SVG-Filters) */
    public static function sharpClass(int $k): string
    {
        return 'ifx-sharp-' . ($k < 0 ? 'm' : 'p') . intdiv(abs($k), self::STEP);
    }

    /** Bezeichnung eines Effekts in der Sprache der Verwaltung */
    public static function presetLabel(string $p): string
    {
        return match ($p) {
            'gray' => __('S/W'), 'sepia' => __('Sepia'), 'warm' => __('Warm'), 'cool' => __('Kühl'),
            'muted' => __('Entsättigt'), 'vivid' => __('Kräftig'), 'contrast' => __('Kontrast'),
            default => $p,
        };
    }

    // ================================================================= Block-Kontext (Einbindung)

    /**
     * Zuordnung eines Blocks setzen (Core\Theme::renderBlock). Ohne eigene Einträge erbt ein verschachtelter Block die
     * Zuordnung des äußeren. Muss mit leave() beendet werden.
     */
    public static function enter(array $data): void
    {
        $fx = is_array($data['_fx'] ?? null) ? $data['_fx'] : [];
        if (!$fx) {
            self::$stack[] = end(self::$stack) ?: [];
            return;
        }
        $keys = array_keys($fx);
        natsort($keys);
        $map = [];
        foreach ($keys as $path) {
            $id = self::valueAt($data, (string) $path);
            $v = self::normalize($fx[$path]);
            if ($id > 0 && $v !== null && $v !== '' && !isset($map[$id])) $map[$id] = $v;
        }
        self::$stack[] = $map;
    }

    public static function leave(): void
    {
        array_pop(self::$stack);
    }

    /** Medien-ID am Feldpfad („image“, „items.2.image“) – 0, wenn keine (auch für Core\ImageFit) */
    public static function valueAt(array $data, string $path): int
    {
        $v = self::rawAt($data, $path);
        return is_int($v) || (is_string($v) && ctype_digit($v)) ? (int) $v : 0;
    }

    public static function rawAt(array $data, string $path): mixed
    {
        $v = $data;
        foreach (explode('.', $path) as $k) {
            if (!is_array($v) || !array_key_exists($k, $v)) return null;
            $v = $v[$k];
        }
        return is_array($v) ? null : $v;
    }

    /** Klassen für ein Bild: Einbindung im aktuellen Block vor globaler Einstellung des Mediums */
    public static function classFor(array $m): string
    {
        $id = (int) ($m['id'] ?? 0);
        $map = end(self::$stack) ?: [];
        $v = $id > 0 && isset($map[$id]) ? $map[$id] : (string) ($m['adjust'] ?? '');
        return $v !== '' ? self::classes($v) : '';
    }

    // ================================================================= Prüfung beim Speichern (Core\Pages::sanitizeBlocks)

    /**
     * Bereinigt data._fx eines Blocks: Schlüssel müssen auf ein Medien-Feld (Typ „media“) des Block-Schemas zeigen –
     * auch in Listen („items.2.image“, Wiederholungen/Gruppen) – und dort muss ein Bild gewählt sein; Werte müssen
     * gültige Anpassungen sein. Leere Werte entfallen.
     */
    public static function sanitize(mixed $fx, array $fields, array $data): array
    {
        $out = [];
        if (!is_array($fx)) return $out;
        foreach ($fx as $path => $v) {
            $path = (string) $path;
            if (count($out) >= 60 || strlen($path) > 120 || !preg_match('~^[a-z_][a-z0-9_]*(\.(\d{1,3}|[a-z_][a-z0-9_]*))*$~i', $path)) continue;
            if (!self::isMediaPath($fields, $path)) continue;
            $n = self::normalize(is_scalar($v) ? (string) $v : null);
            // Bild gewählt – oder auf Detailseiten-Vorlagen an den Datensatz gebunden („@feld“ bzw. data._bind)
            $bound = isset($data['_bind'][explode('.', $path)[0]]) || str_starts_with((string) self::rawAt($data, $path), '@');
            if ($n === null || $n === '' || (!$bound && self::valueAt($data, $path) <= 0)) continue;
            $out[$path] = $n;
        }
        ksort($out, SORT_NATURAL);
        return $out;
    }

    /** Zeigt der Pfad im Schema auf ein Bild-Feld? Listen (repeater/group) verlangen danach eine Position. */
    public static function isMediaPath(array $fields, string $path): bool
    {
        $seg = explode('.', $path);
        $list = $fields;
        for ($i = 0, $n = count($seg); $i < $n; $i++) {
            $f = null;
            foreach ($list as $x) {
                if (($x['name'] ?? null) === $seg[$i]) { $f = $x; break; }
            }
            if (!$f) return false;
            $type = $f['type'] ?? 'text';
            if ($i === $n - 1) return $type === 'media';
            if (!in_array($type, ['repeater', 'group'], true) || !ctype_digit($seg[$i + 1] ?? '')) return false;
            $i++;   // Position überspringen
            $list = $f['fields'] ?? [];
            if ($i === $n - 1) return false;   // Pfad endet auf einer Position
        }
        return false;
    }

    // ================================================================= Stylesheet einbinden

    /** Adresse des Stylesheets */
    public static function cssUrl(): string
    {
        return asset('css/image-fx.css');
    }

    /**
     * Fügt das Stylesheet vor </head> ein, wenn die Ausgabe angepasste Bilder enthält (oder $force, z. B. im
     * Bearbeiten-Modus, wo Anpassungen live dazukommen). Kits müssen dafür nichts ändern.
     * Schärfe/Unschärfe: die SVG-Filter (defs()) kommen vor </body> – nur wenn ein Bild sie nutzt (oder $force).
     */
    public static function inject(string $html, bool $force = false): string
    {
        if (($force || preg_match('~<img\b[^>]*\bclass="[^"]*\bifx\b~', $html)) && !str_contains($html, 'data-ifx-css')) {
            $pos = stripos($html, '</head>');
            if ($pos !== false) {
                $html = substr_replace($html, '<link rel="stylesheet" href="' . e(self::cssUrl()) . '" data-ifx-css>' . "\n", $pos, 0);
            }
        }
        if (($force || preg_match('~<img\b[^>]*\bclass="[^"]*\bifx-sharp-[mp]~', $html)) && !str_contains($html, 'id="ifx-defs"')) {
            $pos = strripos($html, '</body>');
            if ($pos !== false) $html = substr_replace($html, self::defs() . "\n", $pos, 0);
        }
        return $html;
    }

    /**
     * Verstecktes <svg> mit den Filtern für Schärfe/Unschärfe (ids = Klassen, ifx-sharp-m1…m10, ifx-sharp-p1…p10).
     * Gleiches Markup erzeugt resources/js/_imagefx.js (fxDefs) für die Vorschau in Schatten-Bäumen der Verwaltung.
     *  - Weich: Gauß-Unschärfe; Farben mit Deckkraft 1 (feFuncA – am Bildrand = Mittelwert der Bildpunkte im Bild, kein
     *    dunkler oder durchscheinender Saum), Deckkraft aus Original ∪ Unschärfe (feMerge): Fotos bleiben randscharf
     *    deckend, transparente PNG/SVG behalten ihre Freifläche, ihre Kontur wird weich.
     *  - Scharf: 3×3-Kern (Mitte 1 + 4a, Nachbarn −a), Rand dupliziert, Alpha bleibt.
     *  - Filterbereich = Bild (x/y 0, 100 %), sRGB (wie die übrigen CSS-Filter).
     */
    public static function defs(): string
    {
        $f = '';
        for ($i = 1, $n = intdiv(self::SHARP[1], self::STEP); $i <= $n; $i++) {
            $px = rtrim(rtrim(number_format($i * self::BLUR_PX, 2, '.', ''), '0'), '.');
            $f .= '<filter id="ifx-sharp-m' . $i . '" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">'
                . '<feGaussianBlur stdDeviation="' . $px . '" result="b"/><feComponentTransfer in="b" result="o"><feFuncA type="table" tableValues="1 1"/></feComponentTransfer>'
                . '<feMerge result="a"><feMergeNode in="SourceAlpha"/><feMergeNode in="b"/></feMerge><feComposite in="o" in2="a" operator="in"/></filter>';
            $a = round($i * self::SHARPEN, 2);
            $c = round(1 + 4 * $a, 2);
            $f .= '<filter id="ifx-sharp-p' . $i . '" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB">'
                . '<feConvolveMatrix order="3" kernelMatrix="0 -' . $a . ' 0 -' . $a . ' ' . $c . ' -' . $a . ' 0 -' . $a . ' 0" edgeMode="duplicate" preserveAlpha="true"/></filter>';
        }
        return '<svg id="ifx-defs" class="ifx-defs" width="0" height="0" aria-hidden="true" focusable="false"><defs>' . $f . '</defs></svg>';
    }
}
