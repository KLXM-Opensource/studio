<?php
declare(strict_types=1);

namespace Core;

/**
 * Bildschirme vor der Anmeldung (Anmelden, Passkey, Zwei-Faktor, Einladung, Links aus E-Mails, Einrichtung):
 * bewegter Farbhimmel hinter einer Karte aus Milchglas, der sich mit der Tageszeit ändert (resources/css/auth.css).
 *
 *  - Tageszeit serverseitig in der Zeitzone der Website (config 'timezone'): morning 5–10 Uhr, day 11–16, evening 17–21,
 *    night 22–4 → Klasse am <body> (auth--morning|day|evening|night, dazu is-weekend Sa/So). Kein JavaScript, kein Flackern.
 *    Nachts immer dunkel (data-theme="dark" am <html>), sonst folgt der Himmel prefers-color-scheme.
 *  - Markenfarbe: Designfarbe der App (Grundeinstellungen → App, sys.pwa_theme; sonst Standard des Kits). Farbton und
 *    Sättigung fließen als CSS-Variablen (--auth-h, --auth-h2, --auth-s, --auth-brand) in die Farbflächen – über eine kleine
 *    erzeugte CSS-Datei (media/auth/auth-<hash>.css, strenge CSP ohne style-Attribute; Muster wie Core\ImageFit).
 *    Graue, fast schwarze oder fast weiße Farben → KLXM-Studio-Palette (Navy 222° + Mint 158°, Standard in auth.css).
 *  - Tageszeit zum Testen erzwingen: ?tod=morning|day|evening|night (&weekend=1) – nur mit config 'debug',
 *    environment ≠ production oder auf localhost / *.localhost.
 */
final class AuthScreen
{
    public const PHASES = ['morning', 'day', 'evening', 'night'];

    private static ?array $state = null;

    /** @return array{phase: string, weekend: bool} */
    public static function state(): array
    {
        if (self::$state !== null) return self::$state;
        $now = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));
        $h = (int) $now->format('G');
        $phase = match (true) {
            $h >= 5 && $h < 11 => 'morning',
            $h >= 11 && $h < 17 => 'day',
            $h >= 17 && $h < 22 => 'evening',
            default => 'night',
        };
        $weekend = (int) $now->format('N') >= 6;
        if (self::debugAllowed() && ($r = app()->request)) {
            $q = strtolower($r->str('tod'));
            if (in_array($q, self::PHASES, true)) $phase = $q;
            if ($r->str('weekend') !== '') $weekend = $r->str('weekend') === '1';
        }
        return self::$state = ['phase' => $phase, 'weekend' => $weekend];
    }

    /** Klassen für <body> */
    public static function bodyClass(): string
    {
        $s = self::state();
        return 'auth-screen auth--' . $s['phase'] . ($s['weekend'] ? ' is-weekend' : '');
    }

    /** Nachts immer dunkel */
    public static function forceDark(): bool
    {
        return self::state()['phase'] === 'night';
    }

    /** Kurzer Gruß zur Tageszeit (Anmeldeseite) */
    public static function greeting(): string
    {
        $s = self::state();
        return match ($s['phase']) {
            'morning' => __('Guten Morgen'),
            'day' => $s['weekend'] ? __('Schönes Wochenende') : __('Guten Tag'),
            'evening' => __('Schönen Abend'),
            default => __('Hallo, Nachteule'),
        };
    }

    /**
     * Markenfarbe → [Farbton, zweiter Farbton, Sättigung %, Hex] oder null (KLXM-Palette aus auth.css).
     * @return ?array{0: int, 1: int, 2: int, 3: string}
     */
    public static function palette(?string $hex = null): ?array
    {
        $hex ??= (string) (AppIcons::appInfo()['theme_color'] ?? '');
        if (!preg_match('~^#?([0-9a-f]{3}|[0-9a-f]{6})$~i', trim($hex), $m)) return null;
        $x = strlen($m[1]) === 3 ? preg_replace('~(.)~', '$1$1', $m[1]) : $m[1];
        [$r, $g, $b] = array_map(fn($p) => hexdec($p) / 255, str_split(strtolower($x), 2));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        $s = $d == 0 ? 0 : $d / (1 - abs(2 * $l - 1));
        // Grau, fast schwarz oder fast weiß: kein sinnvoller Farbton
        if ($s < .18 || $l < .08 || $l > .94) return null;
        if (strtolower($x) === '314164') return null;   // KLXM Navy selbst → Standard mit Mint als zweitem Farbton
        $h = match ($max) {
            $r => fmod(($g - $b) / $d + 6, 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        } * 60;
        $h = (int) round($h) % 360;
        return [$h, ($h + 40) % 360, (int) round(min(85, max(55, $s * 100))), '#' . strtolower($x)];
    }

    /** Erzeugte CSS-Datei mit den Farbvariablen der Website → URL, null = KLXM-Palette */
    public static function cssUrl(): ?string
    {
        $p = self::palette();
        if ($p === null) return null;
        $css = sprintf(".auth-screen{--auth-h:%d;--auth-h2:%d;--auth-s:%d%%;--auth-brand:%s}\n", ...$p);
        $name = 'auth-' . substr(sha1($css), 0, 12) . '.css';
        $dir = site()->mediaDir('auth');
        if (!is_file("$dir/$name")) {
            @mkdir($dir, 0775, true);
            if (@file_put_contents("$dir/$name", "/* Anmeldung: Markenfarbe (Core\\AuthScreen) – automatisch erzeugt */\n" . $css, LOCK_EX) === false) return null;
        }
        return site()->mediaUrl('auth/' . $name);
    }

    /** Stylesheets für <head> */
    public static function head(): string
    {
        $out = '<link rel="stylesheet" href="' . e(asset('css/auth.css')) . '">';
        if ($u = self::cssUrl()) $out .= "\n" . '<link rel="stylesheet" href="' . e($u) . '">';
        return $out;
    }

    /** Hintergrund (dekorativ) direkt nach <body> */
    public static function sky(): string
    {
        return '<div class="auth-sky" aria-hidden="true"><span class="auth-blob auth-blob--1"></span><span class="auth-blob auth-blob--2"></span><span class="auth-blob auth-blob--3"></span></div>';
    }

    private static function debugAllowed(): bool
    {
        if (app()->config->get('debug', false) || environment() === 'development') return true;
        $host = strtolower((string) (app()->request?->host() ?? ''));
        $host = preg_replace('~:\d+$~', '', $host);
        return $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.localhost');
    }
}
