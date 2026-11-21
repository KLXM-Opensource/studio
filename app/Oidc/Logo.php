<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Oidc;

use Core\Http\Fetch;
use Core\Media;
use Core\Svg;

/**
 * Logo des Anbieters für den dezenten Anmelde-Link (Anmeldeseite, Core\Oidc\Providers::logoHtml).
 *
 *  - Quelle 1 (Vorrang): in den Einstellungen gewähltes Bild aus der Mediathek (sys.oidc → logo_media).
 *  - Quelle 2: optionales Feld „logo_uri“ im Discovery-Dokument des Anbieters (https, SVG oder PNG). Es wird beim Speichern
 *    der Einstellungen bzw. beim (stündlichen) Abruf des Discovery-Dokuments geladen – nur wenn sich die Adresse geändert
 *    hat oder die Datei fehlt –, über Core\Http\Fetch (SSRF-Schutz, höchstens MAX_BYTES). SVG wird mit Core\Svg::clean
 *    bereinigt und für die Einbettung vorbereitet (ohne <style>, title/desc, Rollen; ids mit Präfix), PNG geprüft
 *    (Signatur, Maße ≤ 512 px). Ablage installationsweit in storage/oidc-logos/ (eine Datei je Issuer, Websites eines
 *    Netzwerks teilen sie).
 *  - Ausgabe immer lokal: SVG eingebettet (folgt mit currentColor der Textfarbe), PNG als data:-Adresse – nie ein Verweis
 *    auf den Server des Anbieters (CSP img-src 'self' data:, keine Drittanfrage beim Seitenaufruf).
 *  - Ohne Logo: neutrales Symbol (shield-check).
 */
final class Logo
{
    public const MAX_BYTES = 64 * 1024;
    public const MAX_PX = 512;

    /** @var null|callable(string $url): array{status: int, body: string} Selbsttest */
    public static $transport = null;
    /** Ablage (Selbsttest: eigener Ordner) */
    public static ?string $dir = null;

    public static function dir(): string
    {
        return self::$dir ?? ROOT . '/storage/oidc-logos';
    }

    private static function base(string $issuer): string
    {
        return self::dir() . '/logo-' . substr(hash('sha256', rtrim($issuer, '/')), 0, 16);
    }

    /** Gespeichertes Logo eines Issuers → ['type' => 'svg'|'png', 'data' => …] oder null */
    public static function stored(string $issuer): ?array
    {
        if ($issuer === '') return null;
        $b = self::base($issuer);
        if (is_file("$b.svg")) return ['type' => 'svg', 'data' => (string) file_get_contents("$b.svg")];
        if (is_file("$b.png")) return ['type' => 'png', 'data' => (string) file_get_contents("$b.png")];
        return null;
    }

    /**
     * Abgleich mit dem Discovery-Dokument: logo_uri neu oder geändert → laden; fehlt das Feld → gespeichertes Logo entfernen.
     * $force (Speichern der Einstellungen): auch bei gleicher Adresse neu laden. Fehler (zu groß, kein SVG/PNG, nicht erreichbar) lassen die Anmeldung unberührt – nur Protokoll, kein Logo.
     * @return ?string Fehlertext (null = in Ordnung oder nichts zu tun)
     */
    public static function sync(string $issuer, array $disc, bool $force = false): ?string
    {
        $issuer = rtrim($issuer, '/');
        if ($issuer === '') return null;
        $src = trim((string) ($disc['logo_uri'] ?? ''));
        $b = self::base($issuer);
        $meta = json_decode((string) @file_get_contents("$b.json"), true);
        if ($src === '') {
            if (is_array($meta)) self::remove($issuer);
            return null;
        }
        if (!$force && is_array($meta) && ($meta['src'] ?? '') === $src && (self::stored($issuer) !== null || !empty($meta['error']))) return null;
        $err = self::download($issuer, $src);
        if ($err !== null) {
            self::remove($issuer);
            self::writeMeta($b, ['src' => $src, 'error' => $err, 'at' => time()]);
            error_log('[oidc] Logo ' . $src . ': ' . $err);
        }
        return $err;
    }

    /** Logo laden, prüfen und ablegen @return ?string Fehlertext */
    public static function download(string $issuer, string $src): ?string
    {
        if (!preg_match('~^https://[^\s/?#]+/~i', $src) && !(environment() === 'development' && preg_match('~^http://[^\s/?#]+/~i', $src))) {
            return __('Die Logo-Adresse des Anbieters muss mit https:// beginnen.');
        }
        try {
            $r = self::$transport ? (self::$transport)($src) : Fetch::get($src, ['timeout' => 8, 'max' => self::MAX_BYTES, 'headers' => ['Accept: image/svg+xml, image/png']]);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
        if (($r['status'] ?? 0) !== 200) return __('Der Server antwortete mit HTTP {status}.', ['status' => (string) ($r['status'] ?? '–')]);
        $body = (string) $r['body'];
        if (strlen($body) > self::MAX_BYTES) return __('Das Logo des Anbieters ist zu groß (höchstens {kb} KB).', ['kb' => self::MAX_BYTES / 1024]);
        $prepared = self::prepare($body);
        if (is_string($prepared)) return $prepared;
        self::remove($issuer);
        $b = self::base($issuer);
        @mkdir(self::dir(), 0775, true);
        if (@file_put_contents($b . '.' . $prepared['type'], $prepared['data'], LOCK_EX) === false) return __('Das Logo konnte nicht gespeichert werden.');
        self::writeMeta($b, ['src' => $src, 'type' => $prepared['type'], 'bytes' => strlen($prepared['data']), 'at' => time()]);
        return null;
    }

    /**
     * Inhalt prüfen: PNG (Signatur, Maße) oder SVG (bereinigt, einbettbar). @return array{type: string, data: string}|string Fehlertext
     */
    public static function prepare(string $body): array|string
    {
        if (strlen($body) > self::MAX_BYTES) return __('Das Logo des Anbieters ist zu groß (höchstens {kb} KB).', ['kb' => self::MAX_BYTES / 1024]);
        if (str_starts_with($body, "\x89PNG\r\n\x1a\n")) {
            $i = @getimagesizefromstring($body);
            if (!$i || $i[2] !== IMAGETYPE_PNG || $i[0] < 1 || $i[1] < 1 || $i[0] > self::MAX_PX || $i[1] > self::MAX_PX) {
                return __('Das PNG-Logo ist beschädigt oder größer als {px} Pixel.', ['px' => self::MAX_PX]);
            }
            return ['type' => 'png', 'data' => $body];
        }
        if (preg_match('~<svg[\s>]~i', substr($body, 0, 4096))) {
            try {
                $svg = Svg::clean($body)['svg'];
            } catch (\Throwable $e) {
                return $e->getMessage();
            }
            $svg = self::inline($svg);
            return $svg !== '' ? ['type' => 'svg', 'data' => $svg] : __('Das SVG-Logo enthält keine Grafik.');
        }
        return __('Das Logo des Anbieters muss eine SVG- oder PNG-Datei sein.');
    }

    /**
     * Bereinigtes SVG zum Einbetten in die Seite: <style> (wirkte auf die ganze Seite), title/desc und Rollen/Beschriftungen
     * entfernt (das Logo ist dekorativ), ids samt Verweisen mit Präfix (keine Kollision mit ids der Seite), Wurzel mit
     * aria-hidden und focusable="false".
     */
    public static function inline(string $svg): string
    {
        $svg = (string) preg_replace('~<\?xml.*?\?>~s', '', $svg);
        $svg = (string) preg_replace('~<(style|title|desc)\b[^>]*>.*?</\1>|<(style|title|desc)\b[^>]*/>~si', '', $svg);
        $svg = (string) preg_replace('~\s(role|aria-[a-z]+|class|width|height|tabindex)="[^"]*"~i', '', $svg);
        $svg = (string) preg_replace('~\sid="([^"]+)"~', ' id="oidc-logo-$1"', $svg);
        $svg = (string) preg_replace('~url\(#([^)]+)\)~', 'url(#oidc-logo-$1)', $svg);
        $svg = (string) preg_replace('~(\s(?:xlink:)?href)="#([^"]+)"~', '$1="#oidc-logo-$2"', $svg);
        $svg = trim($svg);
        if (!preg_match('~^<svg\b~i', $svg) || !preg_match('~<(path|rect|circle|ellipse|line|polyline|polygon|text|use)\b~i', $svg)) return '';
        // Wurzel ohne style/overflow/x/y: sonst überschreibt ein Anbieter-Logo die Größe aus dem CSS (z. B. style="width:100vw;
        // height:100vh") und überdeckt die Anmeldeseite mit eigenem Inhalt (Text). Der Rest bleibt in der 20-px-Fläche (overflow hidden).
        $svg = (string) preg_replace_callback('~^<svg\b[^>]*>~i', fn($m) => (string) preg_replace('~\s(style|overflow|x|y|transform)="[^"]*"~i', '', $m[0]), $svg, 1);
        return (string) preg_replace('~^<svg\b~i', '<svg class="auth-alt__logo" aria-hidden="true" focusable="false"', $svg, 1);
    }

    public static function remove(string $issuer): void
    {
        $b = self::base($issuer);
        foreach (['svg', 'png', 'json'] as $x) @unlink("$b.$x");
    }

    private static function writeMeta(string $b, array $meta): void
    {
        @mkdir(dirname($b), 0775, true);
        @file_put_contents("$b.json", json_encode($meta, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /** Hinweis für die Einstellungen: Herkunft bzw. letzter Fehler des Logos aus dem Discovery-Dokument */
    public static function status(string $issuer): ?array
    {
        if ($issuer === '') return null;
        $m = json_decode((string) @file_get_contents(self::base($issuer) . '.json'), true);
        return is_array($m) ? $m : null;
    }

    /**
     * HTML für den Anmelde-Link: Bild aus der Mediathek, sonst Logo aus der Discovery, sonst neutrales Symbol. Immer dekorativ.
     */
    public static function html(array $p): string
    {
        $mid = (int) ($p['logo_media'] ?? 0);
        if ($mid > 0 && ($p['site'] ?? '') === site()->key && ($m = Media::find($mid))) {
            $path = Media::path($m);
            if (str_ends_with(strtolower((string) $m['file']), '.svg') && is_file($path) && filesize($path) <= Svg::MAX_BYTES) {
                $svg = self::inline((string) file_get_contents($path));   // beim Hochladen schon bereinigt
                if ($svg !== '') return $svg;
            } elseif (str_starts_with((string) ($m['mime'] ?? ''), 'image/')) {
                return '<img class="auth-alt__logo" src="' . e(Media::url($m, 160)) . '" alt="" width="20" height="20">';
            }
        }
        $s = self::stored((string) ($p['issuer'] ?? ''));
        if ($s && $s['type'] === 'svg') return $s['data'];
        if ($s && $s['type'] === 'png') return '<img class="auth-alt__logo" src="data:image/png;base64,' . base64_encode($s['data']) . '" alt="" width="20" height="20">';
        return icon('shield-check', ['class' => 'auth-alt__logo auth-alt__logo--icon']);
    }
}
