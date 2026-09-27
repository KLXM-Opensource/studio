<?php
declare(strict_types=1);

namespace Core;

/**
 * Bild bearbeiten – zerstörungsfrei: Entzerren, Spiegeln, Drehen (90°-Schritte und frei), Ausrichten, Zuschneiden.
 *
 *  - Das Original bleibt unverändert. Die Bearbeitung steht als Parameter in media.edit_json und wird beim Erzeugen der
 *    Größen angewendet (Media::setEdit()): bearbeitete Fassung als {Medienordner}/cache/{zufall}-e.{jpg|png}, daraus die
 *    WebP/AVIF-Größen mit neuem Zufallsnamen (neue Adressen = kein alter Browser- oder Seiten-Cache).
 *  - Reihenfolge (OPS): EXIF-Ausrichtung (schon beim Hochladen, Media::orient) → Entzerren → Spiegeln → 90°-Drehung →
 *    freie Drehung (Zuschnitt aufs größte einbeschriebene Rechteck oder Hintergrundfarbe) → Zuschneiden → Größen →
 *    CSS-Anpassung (Core\ImageFx) wie bisher. Dieselbe Rechnung steckt in resources/js/_imageedit.js (Vorschau).
 *  - Entzerren mit Imagick (distortImage, DISTORTION_PERSPECTIVE), sonst GD mit eigener Projektion (bilinear, begrenzt
 *    auf EDIT_GD_MAX px und ein Zeitlimit). Drehen, Spiegeln und Zuschneiden immer mit GD.
 *  - Speicherformat (kanonisch, normalize()):
 *      quad     [[x,y] ×4] – Ecken oben links, oben rechts, unten rechts, unten links als Anteile (0–1) des Originals
 *      quad_fit „edges“ (Größe aus den Kantenlängen, Standard) | „image“ (Seitenverhältnis des Originals)
 *      flip_h, flip_v  true
 *      rot      90 | 180 | 270 (im Uhrzeigersinn)
 *      angle    −45 … 45 (0,1°-Schritte, im Uhrzeigersinn)
 *      fill     „#rrggbb“ – Ecken füllen statt beschneiden
 *      crop     {x, y, w, h (Anteile des gedrehten Bildes), ratio („free“ oder z. B. „16:9“)}
 *    Leer ([]) = keine Bearbeitung. Die Spalte speichert zusätzlich Originalmaße und die bearbeitete Fassung
 *    ({"ops": {…}, "orig": {"w", "h"}, "master": "cache/…-e.jpg"}).
 *  - Nicht bearbeitbar: SVG, GIF (Animation), Videos und andere Dateien (editable()).
 */
final class ImageEdit
{
    /** Reihenfolge der Schritte (Selbsttest, Entwicklerhandbuch) */
    public const OPS = ['exif', 'perspective', 'flip', 'rotate90', 'rotate', 'crop', 'sizes', 'fx'];
    public const MAX_ANGLE = 45.0;
    /** Seitenverhältnisse des Zuschneiden-Werkzeugs */
    public const RATIOS = ['free', '1:1', '4:3', '3:2', '16:9', '16:10', '4:5', '9:16'];
    /** Längste Kante der Arbeitskopie (Original ist höchstens 3200 px) und des GD-Entzerrens */
    public const EDIT_MAX = 3200;
    public const EDIT_GD_MAX = 2400;

    // ================================================================= Format

    /**
     * Bearbeitung prüfen und kanonisch machen. @return ?array null = ungültig, [] = keine Bearbeitung
     */
    public static function normalize(mixed $in): ?array
    {
        if ($in === null || $in === '' || $in === []) return [];
        if (is_string($in)) $in = json_decode($in, true);
        if (!is_array($in)) return null;
        $out = [];
        if (isset($in['quad'])) {
            $q = $in['quad'];
            if (!is_array($q) || count($q) !== 4) return null;
            $pts = [];
            foreach (array_values($q) as $p) {
                if (!is_array($p) || count($p) < 2) return null;
                [$x, $y] = array_values($p);
                if (!is_numeric($x) || !is_numeric($y)) return null;
                $pts[] = [round(max(0.0, min(1.0, (float) $x)), 5), round(max(0.0, min(1.0, (float) $y)), 5)];
            }
            if (!self::convex($pts) || self::area($pts) < 0.01) return null;
            if ($pts !== [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]]) {
                $out['quad'] = $pts;
                if (($in['quad_fit'] ?? 'edges') === 'image') $out['quad_fit'] = 'image';
            }
        }
        if (!empty($in['flip_h'])) $out['flip_h'] = true;
        if (!empty($in['flip_v'])) $out['flip_v'] = true;
        $rot = (int) ($in['rot'] ?? 0);
        if (!in_array($rot, [0, 90, 180, 270, -90], true)) return null;
        if ($rot === -90) $rot = 270;
        if ($rot) $out['rot'] = $rot;
        if (isset($in['angle'])) {
            if (!is_numeric($in['angle'])) return null;
            $a = round((float) $in['angle'], 1);
            if (abs($a) > self::MAX_ANGLE) return null;
            if ($a != 0.0) $out['angle'] = $a;
        }
        if (isset($in['fill']) && $in['fill'] !== '' && $in['fill'] !== null) {
            if (!is_string($in['fill']) || !preg_match('~^#[0-9a-f]{6}$~i', $in['fill'])) return null;
            if (isset($out['angle'])) $out['fill'] = strtolower($in['fill']);
        }
        if (isset($in['crop'])) {
            $c = $in['crop'];
            if (!is_array($c)) return null;
            $ratio = (string) ($c['ratio'] ?? 'free');
            if (!in_array($ratio, self::RATIOS, true)) return null;
            $f = fn($k) => max(0.0, min(1.0, (float) ($c[$k] ?? 0)));
            $w = $f('w');
            $h = $f('h');
            if ($w < 0.02 || $h < 0.02) return null;
            $x = min($f('x'), 1 - $w);
            $y = min($f('y'), 1 - $h);
            $full = $x < 0.0005 && $y < 0.0005 && $w > 0.9995 && $h > 0.9995;
            if (!$full) $out['crop'] = ['x' => round($x, 5), 'y' => round($y, 5), 'w' => round($w, 5), 'h' => round($h, 5), 'ratio' => $ratio];
        }
        return $out;
    }

    /** Konvexes Viereck ohne Selbstüberschneidung (im Uhrzeigersinn wie TL → TR → BR → BL) */
    private static function convex(array $p): bool
    {
        $sign = 0;
        for ($i = 0; $i < 4; $i++) {
            [$ax, $ay] = $p[$i];
            [$bx, $by] = $p[($i + 1) % 4];
            [$cx, $cy] = $p[($i + 2) % 4];
            $z = ($bx - $ax) * ($cy - $by) - ($by - $ay) * ($cx - $bx);
            if (abs($z) < 1e-9) return false;
            $s = $z > 0 ? 1 : -1;
            if ($sign && $s !== $sign) return false;
            $sign = $s;
        }
        return $sign > 0;   // Bildkoordinaten (y nach unten): im Uhrzeigersinn = positiv
    }

    private static function area(array $p): float
    {
        $a = 0.0;
        for ($i = 0; $i < 4; $i++) $a += $p[$i][0] * $p[($i + 1) % 4][1] - $p[($i + 1) % 4][0] * $p[$i][1];
        return abs($a) / 2;
    }

    /** Bearbeitung aus der Spalte media.edit_json (nur die Schritte) */
    public static function ops(array $m): array
    {
        $e = json_decode((string) ($m['edit_json'] ?? ''), true);
        return is_array($e) ? (self::normalize($e['ops'] ?? []) ?? []) : [];
    }

    /** Gespeicherte Angaben (orig, master) */
    public static function stored(array $m): array
    {
        $e = json_decode((string) ($m['edit_json'] ?? ''), true);
        return is_array($e) ? $e : [];
    }

    /** Kann dieses Medium bearbeitet werden? null = ja, sonst Hinweis */
    public static function editable(array $m): ?string
    {
        $mime = (string) ($m['mime'] ?? '');
        $name = strtolower((string) ($m['original_name'] ?? ''));
        return match (true) {
            str_starts_with($mime, 'video/') => __('Videos lassen sich hier nicht bearbeiten – die Bildwerkzeuge gibt es nur für Fotos (JPG, PNG, WebP).'),
            $mime === 'image/svg+xml' || str_ends_with($name, '.svg') => __('SVG-Grafiken lassen sich nicht bearbeiten (Vektorgrafik).'),
            $mime === 'image/gif' || str_ends_with($name, '.gif') => __('GIF-Dateien (auch Animationen) lassen sich nicht bearbeiten.'),
            !isset(Media::IMAGE_MIMES[$mime]) => __('Die Bildwerkzeuge gibt es nur für Fotos (JPG, PNG, WebP).'),
            default => null,
        };
    }

    /** Womit wird entzerrt? */
    public static function engine(): string
    {
        return class_exists(\Imagick::class) && defined('Imagick::DISTORTION_PERSPECTIVE') ? 'imagick' : 'gd';
    }

    // ================================================================= Rechnung (gleich in _imageedit.js)

    /**
     * Größtes achsparalleles Rechteck in einem um $deg gedrehten Rechteck w×h (zentriert). @return array{0: float, 1: float}
     */
    public static function inscribed(float $w, float $h, float $deg): array
    {
        if ($w <= 0 || $h <= 0) return [0.0, 0.0];
        $a = deg2rad(abs($deg));
        $sin = abs(sin($a));
        $cos = abs(cos($a));
        if ($sin < 1e-12) return [$w, $h];
        $long = max($w, $h);
        $short = min($w, $h);
        if ($short <= 2 * $sin * $cos * $long || abs($sin - $cos) < 1e-10) {
            // Halb eingeschränkt: zwei Ecken berühren die längere Seite
            $x = 0.5 * $short;
            return $w >= $h ? [$x / $sin, $x / $cos] : [$x / $cos, $x / $sin];
        }
        $cos2 = $cos * $cos - $sin * $sin;
        return [($w * $cos - $h * $sin) / $cos2, ($h * $cos - $w * $sin) / $cos2];
    }

    /** Umgebendes Rechteck nach Drehung um $deg. @return array{0: float, 1: float} */
    public static function rotatedBox(float $w, float $h, float $deg): array
    {
        $a = deg2rad($deg);
        $s = abs(sin($a));
        $c = abs(cos($a));
        return [$w * $c + $h * $s, $w * $s + $h * $c];
    }

    /**
     * Ausgabegröße beim Entzerren aus den Ecken in Pixeln (TL, TR, BR, BL).
     * edges: längere der gegenüberliegenden Kanten; image: Breite aus den Kanten, Höhe im Seitenverhältnis $ratio (Original).
     * @return array{0: int, 1: int}
     */
    public static function perspectiveSize(array $q, string $fit = 'edges', float $ratio = 0.0): array
    {
        $d = fn($a, $b) => hypot($b[0] - $a[0], $b[1] - $a[1]);
        $w = max($d($q[0], $q[1]), $d($q[3], $q[2]));
        $h = max($d($q[0], $q[3]), $d($q[1], $q[2]));
        if ($fit === 'image' && $ratio > 0) $h = $w / $ratio;
        return [max(1, (int) round($w)), max(1, (int) round($h))];
    }

    /**
     * Projektive Abbildung (3×3, h8 = 1), die die Punkte $from auf $to abbildet (je 4 Punkte [x, y]).
     * @return float[] 9 Werte oder [] (entartet)
     */
    public static function homography(array $from, array $to): array
    {
        $A = [];
        for ($i = 0; $i < 4; $i++) {
            [$x, $y] = $from[$i];
            [$u, $v] = $to[$i];
            $A[] = [$x, $y, 1, 0, 0, 0, -$u * $x, -$u * $y, $u];
            $A[] = [0, 0, 0, $x, $y, 1, -$v * $x, -$v * $y, $v];
        }
        // Gauß-Elimination mit Spaltenpivot
        for ($c = 0; $c < 8; $c++) {
            $p = $c;
            for ($r = $c + 1; $r < 8; $r++) if (abs($A[$r][$c]) > abs($A[$p][$c])) $p = $r;
            if (abs($A[$p][$c]) < 1e-12) return [];
            [$A[$c], $A[$p]] = [$A[$p], $A[$c]];
            for ($r = 0; $r < 8; $r++) {
                if ($r === $c) continue;
                $f = $A[$r][$c] / $A[$c][$c];
                if ($f == 0.0) continue;
                for ($k = $c; $k < 9; $k++) $A[$r][$k] -= $f * $A[$c][$k];
            }
        }
        $h = [];
        for ($i = 0; $i < 8; $i++) $h[] = $A[$i][8] / $A[$i][$i];
        $h[] = 1.0;
        return $h;
    }

    /** Punkt mit einer Homographie abbilden. @return array{0: float, 1: float} */
    public static function project(array $h, float $x, float $y): array
    {
        $d = $h[6] * $x + $h[7] * $y + $h[8];
        return [($h[0] * $x + $h[1] * $y + $h[2]) / $d, ($h[3] * $x + $h[4] * $y + $h[5]) / $d];
    }

    /**
     * Zuschnitt in Pixeln für ein Bild w×h (Seitenverhältnis wird exakt erzwungen). @return array{x: int, y: int, w: int, h: int}
     */
    public static function cropRect(int $w, int $h, array $c): array
    {
        $pw = max(1, (int) round($c['w'] * $w));
        $ph = max(1, (int) round($c['h'] * $h));
        if (($c['ratio'] ?? 'free') !== 'free') {
            [$a, $b] = array_map('intval', explode(':', $c['ratio']));
            $ar = $a / $b;
            $ph = max(1, (int) round($pw / $ar));
            if ($ph > $h) { $ph = $h; $pw = max(1, (int) round($ph * $ar)); }
            if ($pw > $w) { $pw = $w; $ph = max(1, (int) round($pw / $ar)); }
        }
        $pw = min($pw, $w);
        $ph = min($ph, $h);
        $x = (int) round(min($c['x'] * $w, $w - $pw));
        $y = (int) round(min($c['y'] * $h, $h - $ph));
        return ['x' => max(0, $x), 'y' => max(0, $y), 'w' => $pw, 'h' => $ph];
    }

    /** Maße nach jedem Schritt für ein Bild w×h – Ergebnis [w, h] (für Oberfläche und Selbsttest) */
    public static function outputSize(int $w, int $h, array $e): array
    {
        if (!empty($e['quad'])) {
            $q = array_map(fn($p) => [$p[0] * $w, $p[1] * $h], $e['quad']);
            [$w, $h] = self::perspectiveSize($q, $e['quad_fit'] ?? 'edges', $w / $h);
        }
        if (in_array($e['rot'] ?? 0, [90, 270], true)) [$w, $h] = [$h, $w];
        if (!empty($e['angle'])) {
            [$fw, $fh] = empty($e['fill']) ? self::inscribed($w, $h, $e['angle']) : self::rotatedBox($w, $h, $e['angle']);
            [$w, $h] = empty($e['fill']) ? [max(1, (int) floor($fw) - 2), max(1, (int) floor($fh) - 2)] : [(int) round($fw), (int) round($fh)];
        }
        if (!empty($e['crop'])) {
            $r = self::cropRect($w, $h, $e['crop']);
            [$w, $h] = [$r['w'], $r['h']];
        }
        return [$w, $h];
    }

    /** EXIF-Ausrichtung 1–8 → [spiegeln (h|null), Drehung im Uhrzeigersinn] (erst spiegeln, dann drehen) */
    public static function exifOps(int $o): array
    {
        return match ($o) {
            2 => ['h', 0], 3 => [null, 180], 4 => ['v', 0], 5 => ['h', 270],
            6 => [null, 90], 7 => ['h', 90], 8 => [null, 270], default => [null, 0],
        };
    }

    // ================================================================= Anwenden (GD / Imagick)

    /** EXIF-Ausrichtung anwenden (alle 8 Werte, auch gespiegelte) */
    public static function orient(\GdImage $img, int $o): \GdImage
    {
        [$flip, $rot] = self::exifOps($o);
        if ($flip) imageflip($img, $flip === 'h' ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        return $rot ? self::rot90($img, $rot) : $img;
    }

    /**
     * Bearbeitung auf ein Bild anwenden (Reihenfolge OPS). Große Bilder werden vorher verkleinert (Speicher),
     * Entzerren mit GD hat ein Zeitlimit. @throws \RuntimeException
     */
    public static function apply(\GdImage $img, array $e): \GdImage
    {
        $img = self::fitMemory($img, $e);
        if (!empty($e['quad'])) $img = self::perspective($img, $e['quad'], $e['quad_fit'] ?? 'edges');
        if (!empty($e['flip_h']) && !empty($e['flip_v'])) imageflip($img, IMG_FLIP_BOTH);
        elseif (!empty($e['flip_h'])) imageflip($img, IMG_FLIP_HORIZONTAL);
        elseif (!empty($e['flip_v'])) imageflip($img, IMG_FLIP_VERTICAL);
        if (!empty($e['rot'])) $img = self::rot90($img, (int) $e['rot']);
        if (!empty($e['angle'])) $img = self::rotate($img, (float) $e['angle'], $e['fill'] ?? null);
        if (!empty($e['crop'])) {
            $r = self::cropRect(imagesx($img), imagesy($img), $e['crop']);
            $part = imagecrop($img, ['x' => $r['x'], 'y' => $r['y'], 'width' => $r['w'], 'height' => $r['h']]);
            if (!$part) throw new \RuntimeException(__('Zuschnitt fehlgeschlagen.'));
            $img = $part;
        }
        return $img;
    }

    /** Arbeitskopie so verkleinern, dass sie ins Speicherlimit passt (etwa vier Bildkopien gleichzeitig) */
    private static function fitMemory(\GdImage $img, array $e): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $max = (int) app()->config->get('media.edit_max', self::EDIT_MAX);
        $k = min(1.0, $max / max($w, $h));
        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit > 0) {
            $free = max(16 << 20, $limit - memory_get_usage(true));
            $need = $w * $h * 4 * (empty($e['quad']) ? 4 : 5) * 1.3;
            if ($need * $k * $k > $free * 0.8) $k = min($k, sqrt($free * 0.8 / $need));
        }
        if ($k >= 0.999) return $img;
        $nw = max(1, (int) round($w * $k));
        $nh = max(1, (int) round($h * $k));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    private static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') return -1;
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    }

    /** 90°-Schritte im Uhrzeigersinn (GD dreht gegen den Uhrzeigersinn) */
    private static function rot90(\GdImage $img, int $deg): \GdImage
    {
        $r = imagerotate($img, -$deg, 0);
        if (!$r) throw new \RuntimeException(__('Drehen fehlgeschlagen.'));
        imagealphablending($r, true);
        imagesavealpha($r, true);
        return $r;
    }

    /** Freie Drehung: ohne Füllfarbe aufs größte einbeschriebene Rechteck zuschneiden */
    private static function rotate(\GdImage $img, float $deg, ?string $fill): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        [$r, $g, $b] = $fill ? sscanf($fill, '#%02x%02x%02x') : [0, 0, 0];
        imagesetinterpolation($img, IMG_BILINEAR_FIXED);
        $bg = imagecolorallocate($img, $r, $g, $b);
        $rot = imagerotate($img, -$deg, $bg);
        if (!$rot) throw new \RuntimeException(__('Drehen fehlgeschlagen.'));
        if ($fill) return $rot;
        [$iw, $ih] = self::inscribed($w, $h, $deg);
        $iw = max(1, (int) floor($iw) - 2);   // 1 px Sicherheitsrand je Seite (Kantenglättung)
        $ih = max(1, (int) floor($ih) - 2);
        $x = (int) round((imagesx($rot) - $iw) / 2);
        $y = (int) round((imagesy($rot) - $ih) / 2);
        $part = imagecrop($rot, ['x' => max(0, $x), 'y' => max(0, $y), 'width' => min($iw, imagesx($rot)), 'height' => min($ih, imagesy($rot))]);
        if (!$part) throw new \RuntimeException(__('Drehen fehlgeschlagen.'));
        return $part;
    }

    /** Entzerren: Viereck (Anteile) → Rechteck. Imagick, wenn vorhanden – sonst GD */
    private static function perspective(\GdImage $img, array $quad, string $fit): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $q = array_map(fn($p) => [$p[0] * $w, $p[1] * $h], $quad);
        [$W, $H] = self::perspectiveSize($q, $fit, $w / $h);
        // nicht größer als die Arbeitskopie (Fläche)
        $k = min(1.0, sqrt(($w * $h) / max(1, $W * $H)) * 1.15);
        if (self::engine() === 'imagick') {
            try {
                return self::perspectiveImagick($img, $q, max(1, (int) round($W * $k)), max(1, (int) round($H * $k)));
            } catch (\Throwable $ex) {
                error_log('[image-edit] Imagick: ' . $ex->getMessage() . ' – weiter mit GD');
            }
        }
        $gdMax = (int) app()->config->get('media.edit_gd_max', self::EDIT_GD_MAX);
        $k = min($k, $gdMax / max($W, $H));
        return self::perspectiveGd($img, $q, max(1, (int) round($W * $k)), max(1, (int) round($H * $k)));
    }

    private static function perspectiveImagick(\GdImage $img, array $q, int $W, int $H): \GdImage
    {
        ob_start();
        imagepng($img, null, 1);
        $blob = (string) ob_get_clean();
        $im = new \Imagick();
        $im->readImageBlob($blob);
        $im->setImageVirtualPixelMethod(\Imagick::VIRTUALPIXELMETHOD_EDGE);
        $im->setImageArtifact('distort:viewport', "{$W}x{$H}+0+0");
        $dst = [[0, 0], [$W, 0], [$W, $H], [0, $H]];
        $args = [];
        for ($i = 0; $i < 4; $i++) array_push($args, $q[$i][0], $q[$i][1], $dst[$i][0], $dst[$i][1]);
        $im->distortImage(\Imagick::DISTORTION_PERSPECTIVE, $args, false);
        $im->setImagePage($W, $H, 0, 0);
        $im->setImageFormat('png');
        $out = @imagecreatefromstring($im->getImageBlob());
        $im->clear();
        if (!$out || imagesx($out) !== $W || imagesy($out) !== $H) throw new \RuntimeException('unerwartetes Ergebnis');
        imagealphablending($out, true);
        imagesavealpha($out, true);
        return $out;
    }

    /** GD: für jedes Zielpixel die Stelle im Original (inverse Projektion), bilinear gemischt */
    private static function perspectiveGd(\GdImage $img, array $q, int $W, int $H): \GdImage
    {
        $hm = self::homography([[0, 0], [$W, 0], [$W, $H], [0, $H]], $q);
        if (!$hm) throw new \RuntimeException(__('Die Ecken ergeben kein gültiges Viereck.'));
        $sw = imagesx($img);
        $sh = imagesy($img);
        $mx = $sw - 1;
        $my = $sh - 1;
        $out = imagecreatetruecolor($W, $H);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $limit = (float) app()->config->get('media.edit_timeout', 45);
        $t0 = microtime(true);
        [$h0, $h1, $h2, $h3, $h4, $h5, $h6, $h7, $h8] = $hm;
        for ($y = 0; $y < $H; $y++) {
            if (($y & 31) === 0 && microtime(true) - $t0 > $limit) {
                throw new \RuntimeException(__('Das Entzerren dauert auf diesem Server zu lange. Bitte ein kleineres Bild verwenden.'));
            }
            $yy = $y + 0.5;
            $nu = $h0 * 0.5 + $h1 * $yy + $h2;
            $nv = $h3 * 0.5 + $h4 * $yy + $h5;
            $dd = $h6 * 0.5 + $h7 * $yy + $h8;
            for ($x = 0; $x < $W; $x++) {
                $u = $nu / $dd - 0.5;
                $v = $nv / $dd - 0.5;
                $nu += $h0; $nv += $h3; $dd += $h6;
                $x0 = (int) floor($u);
                $y0 = (int) floor($v);
                $fx = $u - $x0;
                $fy = $v - $y0;
                $x1 = $x0 + 1;
                $y1 = $y0 + 1;
                if ($x0 < 0) { $x0 = 0; if ($x1 < 0) $x1 = 0; } elseif ($x1 > $mx) { $x1 = $mx; if ($x0 > $mx) $x0 = $mx; }
                if ($y0 < 0) { $y0 = 0; if ($y1 < 0) $y1 = 0; } elseif ($y1 > $my) { $y1 = $my; if ($y0 > $my) $y0 = $my; }
                $a = imagecolorat($img, $x0, $y0);
                $b = imagecolorat($img, $x1, $y0);
                $c = imagecolorat($img, $x0, $y1);
                $d = imagecolorat($img, $x1, $y1);
                $w00 = (1 - $fx) * (1 - $fy);
                $w10 = $fx * (1 - $fy);
                $w01 = (1 - $fx) * $fy;
                $w11 = $fx * $fy;
                $al = (int) ((($a >> 24) & 0x7F) * $w00 + (($b >> 24) & 0x7F) * $w10 + (($c >> 24) & 0x7F) * $w01 + (($d >> 24) & 0x7F) * $w11 + 0.5);
                $r = (int) ((($a >> 16) & 0xFF) * $w00 + (($b >> 16) & 0xFF) * $w10 + (($c >> 16) & 0xFF) * $w01 + (($d >> 16) & 0xFF) * $w11 + 0.5);
                $g = (int) ((($a >> 8) & 0xFF) * $w00 + (($b >> 8) & 0xFF) * $w10 + (($c >> 8) & 0xFF) * $w01 + (($d >> 8) & 0xFF) * $w11 + 0.5);
                $bl = (int) (($a & 0xFF) * $w00 + ($b & 0xFF) * $w10 + ($c & 0xFF) * $w01 + ($d & 0xFF) * $w11 + 0.5);
                imagesetpixel($out, $x, $y, ($al << 24) | ($r << 16) | ($g << 8) | $bl);
            }
        }
        imagealphablending($out, true);
        return $out;
    }

    // ================================================================= Selbsttest (php bin/console media:selftest)

    /** @return array{ok: int, fails: string[]} */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $t = function (bool $cond, string $label) use (&$ok, &$fails) { $cond ? $ok++ : $fails[] = $label; };
        $near = fn(float $a, float $b, float $eps = 0.5) => abs($a - $b) <= $eps;

        // Einbeschriebenes Rechteck
        [$w, $h] = self::inscribed(100, 100, 0);
        $t($w == 100.0 && $h == 100.0, 'inscribed: 0° = unverändert');
        [$w, $h] = self::inscribed(100, 100, 45);
        $t($near($w, 70.71) && $near($h, 70.71), 'inscribed: Quadrat 45° → 70,7 × 70,7');
        [$w, $h] = self::inscribed(1500, 1000, 7.5);
        $t($near($w, 1404.5, 1) && $near($h, 823.7, 1), 'inscribed: 1500×1000 bei 7,5° ≈ 1404 × 824');
        $t(self::inscribed(1500, 1000, -7.5) == self::inscribed(1500, 1000, 7.5), 'inscribed: symmetrisch zu ±Winkel');
        foreach ([[1600, 900, 3.3], [900, 1600, -12.7], [1200, 1200, 30], [4000, 500, 20], [800, 600, 44.9]] as [$W, $H, $deg]) {
            [$iw, $ih] = self::inscribed($W, $H, $deg);
            $a = deg2rad($deg);
            $inside = function (float $sw, float $sh) use ($W, $H, $a): bool {
                foreach ([[-1, -1], [1, -1], [1, 1], [-1, 1]] as [$sx, $sy]) {
                    $x = $sx * $sw / 2;
                    $y = $sy * $sh / 2;
                    // zurück ins ungedrehte Bild
                    $ux = $x * cos($a) + $y * sin($a);
                    $uy = -$x * sin($a) + $y * cos($a);
                    if (abs($ux) > $W / 2 + 0.01 || abs($uy) > $H / 2 + 0.01) return false;
                }
                return true;
            };
            $t($inside($iw, $ih), "inscribed: {$W}×{$H} bei {$deg}° liegt im gedrehten Bild");
            $t(!$inside($iw * 1.01, $ih * 1.01), "inscribed: {$W}×{$H} bei {$deg}° ist maximal");
        }
        [$bw, $bh] = self::rotatedBox(100, 50, 90);
        $t($near($bw, 50, 0.01) && $near($bh, 100, 0.01), 'rotatedBox: 90° vertauscht die Seiten');

        // Entzerren: Ausgabegröße und Homographie
        $q = [[10, 20], [410, 60], [400, 360], [20, 320]];
        $t(self::perspectiveSize($q) === [402, 300], 'perspectiveSize: Kanten → 402 × 300');
        $t(self::perspectiveSize($q, 'image', 4 / 3) === [402, 301], 'perspectiveSize: Seitenverhältnis des Bildes 4:3');
        $hm = self::homography([[0, 0], [402, 0], [402, 300], [0, 300]], $q);
        $hit = true;
        foreach ([[0, 0], [402, 0], [402, 300], [0, 300]] as $i => [$x, $y]) {
            [$u, $v] = self::project($hm, $x, $y);
            $hit = $hit && $near($u, $q[$i][0], 1e-6) && $near($v, $q[$i][1], 1e-6);
        }
        $t($hit, 'homography: Ecken landen exakt auf dem Viereck');
        $t(self::homography([[0, 0], [1, 0], [2, 0], [3, 0]], $q) === [], 'homography: entartete Punkte → leer');

        // Format
        $t(self::normalize(null) === [] && self::normalize('') === [], 'normalize: leer = keine Bearbeitung');
        $n = self::normalize(['rot' => -90, 'angle' => '7.54', 'flip_h' => 1, 'fill' => '#FFFFFF', 'crop' => ['x' => 0, 'y' => 0.1, 'w' => 1, 'h' => 0.5, 'ratio' => '16:9']]);
        $t($n === ['flip_h' => true, 'rot' => 270, 'angle' => 7.5, 'fill' => '#ffffff', 'crop' => ['x' => 0.0, 'y' => 0.1, 'w' => 1.0, 'h' => 0.5, 'ratio' => '16:9']], 'normalize: kanonisch');
        $t(self::normalize($n) === $n, 'normalize: idempotent');
        $t(self::normalize(['angle' => 46]) === null, 'normalize: Winkel über 45° abgelehnt');
        $t(self::normalize(['rot' => 45]) === null, 'normalize: nur 90°-Schritte');
        $t(self::normalize(['fill' => 'red; x']) === null, 'normalize: Füllfarbe nur #rrggbb');
        $t(self::normalize(['crop' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'ratio' => '7:5']]) === null, 'normalize: unbekanntes Seitenverhältnis abgelehnt');
        $t(self::normalize(['quad' => [[0, 0], [1, 0], [0, 1], [1, 1]]]) === null, 'normalize: überschlagenes Viereck abgelehnt');
        $t(self::normalize(['quad' => [[0, 0], [1, 0], [1, 1], [0, 1]], 'angle' => 0]) === [], 'normalize: Ausgangslage entfällt');
        $t(!isset(self::normalize(['fill' => '#000000'])['fill']), 'normalize: Füllfarbe nur mit freier Drehung');

        // Reihenfolge der Schritte
        $t(self::OPS === ['exif', 'perspective', 'flip', 'rotate90', 'rotate', 'crop', 'sizes', 'fx'], 'Reihenfolge: EXIF → Entzerren → Spiegeln/Drehen → Zuschnitt → Größen → CSS');
        $t(self::outputSize(1600, 1200, ['rot' => 90]) === [1200, 1600], 'outputSize: 90° vertauscht die Seiten');
        $t(self::outputSize(200, 100, ['rot' => 90, 'crop' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 0.5, 'ratio' => 'free']]) === [100, 100], 'outputSize: Zuschnitt nach der Drehung');
        [$cw, $ch] = self::outputSize(1600, 1200, ['rot' => 90, 'angle' => 7.5, 'crop' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1, 'ratio' => '16:9']]);
        $t(abs($cw / $ch - 16 / 9) < 0.01, 'outputSize: 16:9 nach Drehung exakt');
        $t(self::exifOps(6) === [null, 90] && self::exifOps(5) === ['h', 270] && self::exifOps(7) === ['h', 90], 'EXIF: gespiegelte Ausrichtungen 5 und 7');

        // Anwenden mit GD auf einem kleinen Testbild (links rot, rechts blau)
        if (function_exists('imagecreatetruecolor')) {
            $mk = function () {
                $im = imagecreatetruecolor(200, 100);
                imagefilledrectangle($im, 0, 0, 99, 99, imagecolorallocate($im, 255, 0, 0));
                imagefilledrectangle($im, 100, 0, 199, 99, imagecolorallocate($im, 0, 0, 255));
                return $im;
            };
            $red = fn($im, $x, $y) => ((imagecolorat($im, $x, $y) >> 16) & 0xFF) > 200;
            $im = self::apply($mk(), ['flip_h' => true]);
            $t(!$red($im, 10, 50) && $red($im, 190, 50), 'GD: horizontal spiegeln');
            $im = self::apply($mk(), ['rot' => 90]);
            $t(imagesx($im) === 100 && imagesy($im) === 200 && $red($im, 50, 10), 'GD: 90° im Uhrzeigersinn (links wird oben)');
            $im = self::apply($mk(), ['rot' => 90, 'crop' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 0.5, 'ratio' => 'free']]);
            $t(imagesx($im) === 100 && imagesy($im) === 100 && $red($im, 50, 90), 'GD: Zuschnitt nach der Drehung (nur rote Hälfte)');
            $im = self::apply($mk(), ['angle' => 7.5]);
            $t([imagesx($im), imagesy($im)] === self::outputSize(200, 100, ['angle' => 7.5]), 'GD: freie Drehung mit Zuschnitt = berechnete Größe');
            $px = imagecolorat($im, 0, 0);
            $t((($px >> 16) & 0xFF) > 200 || ($px & 0xFF) > 200, 'GD: freie Drehung ohne leere Ecken');
            $im = self::apply($mk(), ['angle' => -10, 'fill' => '#00ff00']);
            $t(((imagecolorat($im, 0, 0) >> 8) & 0xFF) > 200, 'GD: freie Drehung mit Füllfarbe');
            // Entzerren: schräges Viereck innerhalb der roten Hälfte → nur Rot
            $im = self::perspectiveGd($mk(), [[10, 10], [90, 20], [85, 90], [15, 80]], 60, 50);
            $t(imagesx($im) === 60 && imagesy($im) === 50 && $red($im, 1, 1) && $red($im, 58, 48), 'GD: Entzerren bildet das Viereck aufs Rechteck ab');
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
