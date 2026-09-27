<?php
declare(strict_types=1);

namespace Core;

/**
 * Favicon- und App-Icon-Generator + Web-App-Manifest (PWA).
 *
 * Erzeugt aus Buchstaben (Theme-Schrift) oder einem Bild der Mediathek alle nötigen Größen:
 *   favicon.ico (16/32/48), icon-32.png, icon-180.png (Apple), icon-192.png, icon-512.png, icon-maskable-512.png
 * Gespeichert unter public/media/icons (beschreibbar). Rendering mit GD, 4-fach überabgetastet für glatte Kanten.
 */
final class AppIcons
{
    public const SIZES = ['16' => 16, '32' => 32, '48' => 48, '180' => 180, '192' => 192, '512' => 512];

    public static function dir(): string
    {
        return site()->mediaDir('icons');
    }

    public static function url(string $file): string
    {
        return site()->mediaUrl('icons/' . $file) . '?v=' . self::version();
    }

    public static function version(): string
    {
        return (string) (app()->settings->get('sys.icon_version') ?: '1');
    }

    /** Einstellungen mit Theme-Vorgaben zusammenführen */
    public static function config(?array $over = null): array
    {
        $t = app()->theme->def['app']['defaults'] ?? [];
        $s = app()->settings;
        $get = fn(string $k, $def) => $over !== null && array_key_exists("sys.$k", $over) ? $over["sys.$k"] : $s->get("sys.$k", $def);
        return [
            'mode' => $get('icon_mode', 'letters') ?: 'letters',
            'text' => mb_substr(trim((string) ($get('icon_text', '') ?: ($t['icon_text'] ?? 'M'))), 0, 2),
            'image' => (int) $get('icon_image', 0),
            'bg' => $get('icon_bg', '') ?: ($t['icon_bg'] ?? '#16201E'),
            'fg' => $get('icon_fg', '') ?: ($t['icon_fg'] ?? '#FFFFFF'),
            'dot' => (bool) $get('icon_dot', $t['icon_dot_enabled'] ?? true),
            'dot_color' => $get('icon_dot_color', '') ?: ($t['icon_dot'] ?? '#F6C9A8'),
            'shape' => $get('icon_shape', 'rounded') ?: 'rounded',
        ];
    }

    /** Name, Kurzname, Kurzbefehle aus dem Theme (Funktion in functions.php) + Einstellungen */
    public static function appInfo(): array
    {
        $fn = app()->theme->def['app']['info'] ?? null;
        $info = is_string($fn) && function_exists($fn) ? $fn() : [];
        $s = app()->settings;
        $name = trim((string) $s->get('sys.pwa_name', '')) ?: ($info['name'] ?? 'Website');
        return [
            'name' => $name,
            'short_name' => trim((string) $s->get('sys.pwa_short_name', '')) ?: ($info['short_name'] ?? mb_substr($name, 0, 12)),
            'description' => $info['description'] ?? '',
            'shortcuts' => $info['shortcuts'] ?? [],
            'theme_color' => $s->get('sys.pwa_theme', '') ?: (app()->theme->def['app']['defaults']['icon_bg'] ?? '#16201E'),
            'background_color' => $s->get('sys.pwa_bg', '') ?: '#FFFFFF',
            'display' => $s->get('sys.pwa_display', 'standalone') ?: 'standalone',
        ];
    }

    // ================================================================= Erzeugen

    /** Alle Dateien erzeugen und Version erhöhen. @return ?string Fehlermeldung */
    public static function generate(): ?string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) {
            return 'GD mit FreeType fehlt – Icons können nicht erzeugt werden.';
        }
        $cfg = self::config();
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return 'Ordner public/media/icons ist nicht beschreibbar.';
        }
        $png = [];
        foreach ([16, 32, 48, 192, 512] as $px) {
            $png[$px] = self::png(self::render($cfg, $px, 'any'));
        }
        file_put_contents("$dir/icon-32.png", $png[32]);
        file_put_contents("$dir/icon-192.png", $png[192]);
        file_put_contents("$dir/icon-512.png", $png[512]);
        file_put_contents("$dir/icon-180.png", self::png(self::render($cfg, 180, 'apple')));
        file_put_contents("$dir/icon-maskable-512.png", self::png(self::render($cfg, 512, 'maskable')));
        file_put_contents("$dir/favicon.ico", self::ico([16 => $png[16], 32 => $png[32], 48 => $png[48]]));
        app()->settings->set('sys.icon_version', substr(md5(json_encode($cfg) . microtime()), 0, 8));
        PageCache::clear();
        return null;
    }

    /** Sicherstellen, dass Icons existieren (Erstinstallation) */
    public static function ensure(): bool
    {
        if (is_file(self::dir() . '/favicon.ico')) {
            return true;
        }
        return self::generate() === null;
    }

    /** Vorschau als data-URIs, ohne zu speichern (für die Grundeinstellungen) */
    public static function preview(array $over): array
    {
        $cfg = self::config($over);
        $uri = fn($img) => 'data:image/png;base64,' . base64_encode(self::png($img));
        return [
            'favicon' => $uri(self::render($cfg, 32, 'any')),
            'favicon16' => $uri(self::render($cfg, 16, 'any')),
            'any' => $uri(self::render($cfg, 192, 'any')),
            'apple' => $uri(self::render($cfg, 180, 'apple')),
            'maskable' => $uri(self::render($cfg, 192, 'maskable')),
        ];
    }

    /**
     * Ein Icon zeichnen.
     * $purpose: any (Form laut Einstellung, transparent außen) · apple (volle Fläche, iOS rundet selbst) · maskable (volle Fläche, Inhalt in 80-%-Schutzzone)
     */
    public static function render(array $cfg, int $size, string $purpose = 'any'): \GdImage
    {
        $ss = $size <= 64 ? 8 : 4;                         // Überabtastung
        $S = min(2048, $size * $ss);
        $img = imagecreatetruecolor($S, $S);
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);
        imagesavealpha($img, true);
        // Transparent nur, wo Plattformen es zeigen: Browser-Tab/Android („any“); iOS und „maskable“ brauchen eine Fläche
        $bg = $cfg['bg'] === 'transparent'
            ? ($purpose === 'any' ? imagecolorallocatealpha($img, 0, 0, 0, 127) : self::color($img, '#FFFFFF'))
            : self::color($img, $cfg['bg']);

        $content = $purpose === 'maskable' ? 0.8 : ($purpose === 'apple' ? 0.9 : 1.0);
        $source = $cfg['mode'] === 'image' && $cfg['image'] ? Media::find($cfg['image']) : null;

        if ($source && str_starts_with($source['mime'], 'image/')) {
            imagefilledrectangle($img, 0, 0, $S, $S, $bg);
            $src = self::loadImage($source);
            if ($src) {
                // Quadratischer Ausschnitt um den Fokuspunkt (oder eigener 1:1-Zuschnitt)
                $crop = Media::crops($source)['1:1'] ?? null;
                $w = imagesx($src); $h = imagesy($src);
                if ($crop) {
                    $sx = (int) round($crop['x'] * $w); $sy = (int) round($crop['y'] * $h); $side = (int) round($crop['w'] * $w);
                } else {
                    $side = min($w, $h);
                    $sx = (int) max(0, min($w - $side, round(($source['focus_x'] ?? 50) / 100 * $w - $side / 2)));
                    $sy = (int) max(0, min($h - $side, round(($source['focus_y'] ?? 50) / 100 * $h - $side / 2)));
                }
                $d = (int) round($S * $content);
                $o = (int) round(($S - $d) / 2);
                imagecopyresampled($img, $src, $o, $o, $sx, $sy, $d, $d, $side, $side);
            }
        } else {
            if ($purpose === 'any') {
                self::shape($img, $S, $cfg['shape'], $bg);
            } else {
                imagefilledrectangle($img, 0, 0, $S, $S, $bg);
            }
            self::letters($img, $S, $cfg, $content * ($purpose === 'any' && $cfg['shape'] === 'circle' ? 0.92 : 1));
        }

        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $S, $S);
        if ($source && $purpose === 'any' && $cfg['shape'] !== 'square') {
            self::mask($out, $size, $cfg['shape']);
        }
        return $out;
    }

    /** Hausschrift des Themes (theme.php → 'fonts' → 'icon'), sonst eine fette Systemschrift */
    private static function font(): ?string
    {
        if ($f = app()->theme->iconFont()) {
            return $f;
        }
        foreach (['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf', '/System/Library/Fonts/Supplemental/Arial Bold.ttf', 'C:/Windows/Fonts/arialbd.ttf'] as $f) {
            if (is_file($f)) return $f;
        }
        return null;
    }

    /** Buchstaben mittig setzen, optional mit Akzentpunkt wie in der Wortmarke */
    private static function letters(\GdImage $img, int $S, array $cfg, float $scale): void
    {
        $font = self::font();
        $text = $cfg['text'] !== '' ? $cfg['text'] : '•';
        if ($font === null) {
            return;
        }
        $len = mb_strlen($text);
        $pt = $S * ($len > 1 ? 0.36 : 0.56) * $scale;
        $box = imagettfbbox($pt, 0, $font, $text);
        $tw = $box[2] - $box[0];
        $capTop = -$box[5];                                // Höhe über der Grundlinie
        $dotR = $cfg['dot'] ? $pt * 0.13 : 0;
        $gap = $cfg['dot'] ? $pt * 0.05 : 0;
        $total = $tw + ($cfg['dot'] ? $gap + 2 * $dotR : 0);
        $x = ($S - $total) / 2 - $box[0];
        $baseline = $S / 2 + $capTop / 2;                  // optisch mittig (Versalhöhe)
        imagettftext($img, $pt, 0, (int) round($x), (int) round($baseline), self::color($img, $cfg['fg']), $font, $text);
        if ($cfg['dot']) {
            $cx = $x + $box[0] + $tw + $gap + $dotR;
            $cy = $baseline - $dotR;
            imagefilledellipse($img, (int) round($cx), (int) round($cy), (int) round($dotR * 2), (int) round($dotR * 2), self::color($img, $cfg['dot_color']));
        }
    }

    /** Hintergrundform: rounded (Radius 22 %), circle, square */
    private static function shape(\GdImage $img, int $S, string $shape, int $col): void
    {
        if ($shape === 'circle') {
            imagefilledellipse($img, intdiv($S, 2), intdiv($S, 2), $S, $S, $col);
            return;
        }
        if ($shape === 'square') {
            imagefilledrectangle($img, 0, 0, $S, $S, $col);
            return;
        }
        $r = (int) round($S * 0.22);
        imagefilledrectangle($img, $r, 0, $S - $r - 1, $S - 1, $col);
        imagefilledrectangle($img, 0, $r, $S - 1, $S - $r - 1, $col);
        foreach ([[$r, $r], [$S - $r - 1, $r], [$r, $S - $r - 1], [$S - $r - 1, $S - $r - 1]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $col);
        }
    }

    /** Runde Ecken / Kreis für Bild-Icons (Kantenglättung per Abdeckung) */
    private static function mask(\GdImage $img, int $S, string $shape): void
    {
        imagealphablending($img, false);
        $r = $shape === 'circle' ? $S / 2 : $S * 0.22;
        for ($y = 0; $y < $S; $y++) {
            for ($x = 0; $x < $S; $x++) {
                $px = $x + 0.5; $py = $y + 0.5;
                $cx = min(max($px, $r), $S - $r); $cy = min(max($py, $r), $S - $r);
                $dist = sqrt(($px - $cx) ** 2 + ($py - $cy) ** 2);
                $cover = max(0.0, min(1.0, $r - $dist + 0.5));
                if ($cover >= 1.0) continue;
                $c = imagecolorat($img, $x, $y);
                $a = ($c >> 24) & 0x7F;
                $na = (int) round(127 - (127 - $a) * $cover);
                imagesetpixel($img, $x, $y, ($na << 24) | ($c & 0xFFFFFF));
            }
        }
    }

    private static function loadImage(array $m): ?\GdImage
    {
        $file = Media::path($m);
        $data = @file_get_contents($file);
        $img = $data !== false ? @imagecreatefromstring($data) : false;
        return $img ?: null;
    }

    private static function color(\GdImage $img, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (!preg_match('~^[0-9a-f]{6}$~i', $hex)) $hex = '000000';
        return imagecolorallocate($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    private static function png(\GdImage $img): string
    {
        ob_start();
        imagepng($img, null, 9);
        return (string) ob_get_clean();
    }

    /** ICO-Container mit PNG-Einträgen (von allen aktuellen Browsern unterstützt) */
    private static function ico(array $pngs): string
    {
        $head = pack('vvv', 0, 1, count($pngs));
        $dir = '';
        $body = '';
        $offset = 6 + 16 * count($pngs);
        foreach ($pngs as $size => $data) {
            $dir .= pack('CCCCvvVV', $size >= 256 ? 0 : $size, $size >= 256 ? 0 : $size, 0, 0, 1, 32, strlen($data), $offset);
            $body .= $data;
            $offset += strlen($data);
        }
        return $head . $dir . $body;
    }

    // ================================================================= Ausgabe

    /** <link>/<meta>-Tags für den <head> */
    public static function headTags(): string
    {
        if (!self::ensure()) {
            return '';
        }
        $s = app()->settings;
        $info = self::appInfo();
        // Landing-Domain (Core\Landings) mit eigenem Favicon: Bild der Mediathek, keine installierbare Web-App der Website
        if (($l = Landings::current()) && ($fav = self::landingIcon($l))) {
            return '<link rel="icon" href="' . e($fav['url']) . '"' . ($fav['mime'] !== '' ? ' type="' . e($fav['mime']) . '"' : '') . '>' . "\n"
                . '<link rel="apple-touch-icon" href="' . e($fav['url']) . '">' . "\n"
                . '<meta name="theme-color" content="' . e($info['theme_color']) . '">' . "\n"
                . '<meta name="apple-mobile-web-app-title" content="' . e((string) ($l->name ?? $info['short_name'])) . '">' . "\n";
        }
        $h = '<link rel="icon" href="' . e(base_path() . '/favicon.ico?v=' . self::version()) . '" sizes="48x48">' . "\n"
            . '<link rel="icon" href="' . e(self::url('icon-192.png')) . '" type="image/png" sizes="192x192">' . "\n"
            . '<link rel="apple-touch-icon" href="' . e(self::url('icon-180.png')) . '">' . "\n"
            . '<meta name="theme-color" content="' . e($info['theme_color']) . '">' . "\n"
            . '<meta name="apple-mobile-web-app-title" content="' . e($info['short_name']) . '">' . "\n";
        if ($s->get('sys.pwa', true)) {
            $h .= '<link rel="manifest" href="' . e(base_path() . '/manifest.webmanifest?v=' . self::version()) . '">' . "\n";
        }
        return $h;
    }

    /** Favicon einer Landingpage: ['url' => …, 'mime' => …] oder null */
    public static function landingIcon(Landing $l): ?array
    {
        $m = $l->favicon ? Media::find($l->favicon) : null;
        if (!$m) return null;
        $mime = (string) ($m['mime'] ?? '');
        return ['url' => Media::url($m), 'mime' => $mime];   // Original (SVG bzw. PNG) – Browser skalieren selbst
    }

    /** Web-App-Manifest */
    public static function manifest(): array
    {
        $i = self::appInfo();
        $base = base_path();
        $m = [
            'id' => $base . '/',
            'name' => $i['name'],
            'short_name' => $i['short_name'],
            'lang' => 'de',
            'dir' => 'ltr',
            'start_url' => $base . '/?pwa=1',
            'scope' => $base . '/',
            'display' => $i['display'],
            'background_color' => $i['background_color'],
            'theme_color' => $i['theme_color'],
            'icons' => [
                ['src' => self::url('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => self::url('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => self::url('icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];
        if ($i['description'] !== '') {
            $m['description'] = $i['description'];
        }
        if ($i['shortcuts']) {
            $m['shortcuts'] = array_map(fn($sc) => $sc + ['icons' => [['src' => self::url('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png']]], $i['shortcuts']);
        }
        return $m;
    }
}
