<?php
declare(strict_types=1);

namespace Core;

/**
 * Bild anpassen (Effekte, Sättigung, Helligkeit, Kontrast) – zerstörungsfrei per CSS-Filter.
 *
 *  - Original und Größen bleiben unverändert; die Anpassung wirkt beim Anzeigen über Klassen am <img>
 *    (strenge CSP: keine style-Attribute). Stylesheet: resources/css/image-fx.css → public/assets/css/image-fx.css,
 *    wird nur in Seiten eingebunden, die angepasste Bilder enthalten (inject()).
 *  - Zwei Ebenen mit denselben Möglichkeiten:
 *      1. global je Bild (Mediathek): Spalte media.adjust
 *      2. je Einbindung im Block: data._fx = {"image": "…", "items.2.image": "…"} (Feldpfad → Anpassung)
 *    Die Einbindung ersetzt die globale Einstellung vollständig (kein Addieren). „none“ = an dieser Stelle ohne Anpassung.
 *  - Speicherformat (kompakt, kanonisch): Effekt zuerst, dann s/b/c in Prozent, Standardwerte (100 %) entfallen,
 *    z. B. „sepia s120 c110“, „s80“, „gray“. Leer = keine Anpassung.
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
    /** Ausdrücklich ohne Anpassung (nur für Einbindungen sinnvoll: hebt die globale Einstellung auf) */
    public const NONE = 'none';

    /** Stapel der Block-Zuordnungen [Medien-ID => Anpassung] (verschachtelte Blöcke) */
    private static array $stack = [];

    /**
     * Anpassung zerlegen. @return ?array{p: ?string, s: int, b: int, c: int, none: bool} null = ungültig
     */
    public static function parse(?string $v): ?array
    {
        $out = ['p' => null, 's' => 100, 'b' => 100, 'c' => 100, 'none' => false];
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
     * Nimmt auch ein Array {p|preset, s|saturation, b|brightness, c|contrast} (z. B. aus der API).
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
        return implode(' · ', $out);
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
     */
    public static function inject(string $html, bool $force = false): string
    {
        $need = $force || preg_match('~<img\b[^>]*\bclass="[^"]*\bifx\b~', $html);
        if (!$need || str_contains($html, 'data-ifx-css')) return $html;
        $link = '<link rel="stylesheet" href="' . e(self::cssUrl()) . '" data-ifx-css>';
        $pos = stripos($html, '</head>');
        return $pos === false ? $html : substr_replace($html, $link . "\n", $pos, 0);
    }
}
