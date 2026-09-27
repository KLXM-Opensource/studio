<?php
declare(strict_types=1);

namespace Core\Network;

use Core\Site;
use Core\Sites;

/**
 * App-Icon einer Website für die Netzwerk-Verwaltung (Übersicht, Website-Umschalter).
 *
 * Quelle sind die Dateien, die Core\AppIcons im Medienordner der Website erzeugt (icons/icon-192.png, icon-32.png).
 * Alle Websites einer Installation teilen sich public/ – die Adresse ist deshalb relativ zur aktuellen Domain gültig
 * (/media/icons/… bzw. /sites/{key}/media/icons/…), ohne Weiterleitung oder fremde Domain. Cache-Busting über
 * sys.icon_version der Website (aus den Kennzahlen, Core\Network\Stats), sonst über das Änderungsdatum der Datei.
 * Ohne Icon-Dateien: Buchstaben-Kachel in der Markenfarbe (sys.icon_bg bzw. Vorgabe des Kits) als SVG-data-URI –
 * keine Inline-Styles nötig.
 */
final class SiteIcon
{
    /** Ersatzfarben, wenn weder Website noch Kit eine Farbe haben (alle mit Weiß ≥ 4,5:1) */
    private const PALETTE = ['#314164', '#0F6E68', '#7A1F35', '#4338CA', '#6D28D9', '#1F5BC4', '#8A4B08', '#1F2B22'];

    /**
     * @param ?array $stats Kennzahlen der Website (Stats::site/Stats::cached) – für Version, Farbe, Buchstaben
     * @return array{src:string, fallback:bool}
     */
    public static function for(string $key, ?array $stats = null): array
    {
        $cfg = Sites::all()[$key] ?? [];
        $site = new Site($key, $cfg);
        foreach (['icon-192.png', 'icon-32.png'] as $file) {
            $path = $site->mediaDir('icons/' . $file);
            if (!is_file($path)) continue;
            $v = (string) ($stats['icon_version'] ?? '');
            if ($v === '' && $key === site()->key) $v = (string) app()->settings->get('sys.icon_version', '');
            if ($v === '') $v = (string) @filemtime($path);
            return ['src' => $site->mediaUrl('icons/' . $file) . '?v=' . rawurlencode($v), 'fallback' => false];
        }
        $label = (string) ($stats['label'] ?? $site->label());
        return ['src' => self::letters($key, $label, (string) ($stats['icon_text'] ?? ''), (string) ($stats['icon_bg'] ?? '')), 'fallback' => true];
    }

    /** Buchstaben-Kachel als SVG (data-URI) */
    public static function letters(string $key, string $label, string $text = '', string $bg = ''): string
    {
        // Buchstaben der App-Icon-Einstellung (bis zu 2), sonst der erste Buchstabe des Namens
        $text = trim($text) !== '' ? mb_substr(trim($text), 0, 2) : mb_substr(preg_replace('~[^\p{L}\p{N}]~u', '', $label) ?: $key, 0, 1);
        $text = mb_strtoupper($text);
        if (!preg_match('~^#[0-9a-f]{6}$~i', $bg)) $bg = self::PALETTE[crc32($key) % count(self::PALETTE)];
        $fg = self::luminance($bg) > 0.18 ? '#1F2430' : '#FFFFFF';
        $size = mb_strlen($text) > 1 ? 36 : 46;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96"><rect width="96" height="96" rx="22" fill="' . $bg . '"/>'
            . '<text x="48" y="50" dy=".35em" text-anchor="middle" font-family="Lato,Segoe UI,Helvetica,Arial,sans-serif" font-weight="700" font-size="' . $size . '" fill="' . $fg . '">'
            . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** Relative Helligkeit (WCAG) einer #rrggbb-Farbe */
    private static function luminance(string $hex): float
    {
        $c = array_map(function (string $h): float {
            $v = hexdec($h) / 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }
}
