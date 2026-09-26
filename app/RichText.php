<?php
declare(strict_types=1);

namespace Core;

/**
 * Rich-Text-Stile für die Formatierungsleiste (resources/js/_rte.js): Namen der Stile und die Farbpalette des aktiven Themes.
 *
 * Klassenvertrag (Core\Sanitizer): <p class="t-lead|t-small|t-note">, <span class="c-accent|c-muted|c-success|c-warning|c-danger">,
 * <mark>, <sup>, <sub>. Das Aussehen bestimmt das Theme (CSS, am besten in einer Datei mit conditional_css „@rich“).
 *
 * Palette (nur Vorschau in der Verwaltung und Kontrastprüfung – auf der Website misst die Leiste die echten Theme-Farben):
 *   accent  ← Design-Token „accent_ink“ bzw. „accent“ (hell/dunkel), muted ← „muted“, Hintergrund ← „background“
 *   success/warning/danger: Kern-Werte mit AA-Kontrast auf hellen bzw. dunklen Flächen
 * Themes können jede Farbe überschreiben: theme.php → 'rich_text' => ['colors' => ['success' => ['#1A7F37', '#6FD69A']], 'background' => ['#FFFFFF', '#101010']]
 */
final class RichText
{
    private const DEFAULTS = [
        'accent' => ['#0F6E68', '#5DCABE'],
        'muted' => ['#59616B', '#A3ABB5'],
        'success' => ['#1A7240', '#6FD69A'],
        'warning' => ['#8A4B00', '#F2C063'],
        'danger' => ['#B42318', '#FF9A8F'],
    ];

    /** @return array{colors: list<array{name: string, label: string, light: string, dark: string}>, bg: array{0: string, 1: string}} */
    public static function palette(): array
    {
        $over = (array) ((app()->theme->def['rich_text'] ?? [])['colors'] ?? []);
        $tok = function (array $names): ?array {
            $t = Design::tokens();
            foreach ($names as $n) {
                if (($t[$n]['type'] ?? '') !== 'color') continue;
                $v = Design::values();
                $light = Design::color($v[$n] ?? $t[$n]['default'] ?? null);
                if ($light) return [$light, Design::color($v[$n . '@dark'] ?? $t[$n]['dark'] ?? null) ?? $light];
            }
            return null;
        };
        $auto = ['accent' => $tok(['accent_ink', 'accent']), 'muted' => $tok(['muted'])];
        $labels = self::labels();
        $colors = [];
        foreach (self::DEFAULTS as $name => $def) {
            $pair = isset($over[$name]) ? array_values((array) $over[$name]) : ($auto[$name] ?? $def);
            $light = Design::color($pair[0] ?? null) ?? $def[0];
            $colors[] = ['name' => $name, 'label' => $labels[$name], 'light' => $light, 'dark' => Design::color($pair[1] ?? null) ?? $def[1]];
        }
        $bgOver = (array) ((app()->theme->def['rich_text'] ?? [])['background'] ?? []);
        $bg = $bgOver ? [Design::color($bgOver[0] ?? null) ?? '#FFFFFF', Design::color($bgOver[1] ?? null) ?? '#121212'] : ($tok(['background']) ?? ['#FFFFFF', '#121212']);
        // Ohne dunkles Farbschema des Themes: nur hell prüfen; Dunkel-Werte dienen nur der Vorschau im dunklen Verwaltungs-Look
        $hasDark = !empty(Design::def()['dark']) || !empty((app()->theme->def['rich_text'] ?? [])['dark']);
        if (!$hasDark) {
            foreach ($colors as &$c) if ($c['dark'] === $c['light']) $c['dark'] = self::mix($c['light'], 0.5);
            unset($c);
        }
        return ['colors' => $colors, 'bg' => $bg, 'dark' => $hasDark, 'country' => country_code()];
    }

    /** Farbe mit Weiß mischen (Vorschau im dunklen Verwaltungs-Look) */
    private static function mix(string $hex, float $white): string
    {
        $c = array_map(fn($i) => hexdec(substr($hex, $i, 2)), [1, 3, 5]);
        return sprintf('#%02X%02X%02X', ...array_map(fn($v) => (int) round($v + (255 - $v) * $white), $c));
    }

    /** Bezeichnungen der Farben (Oberfläche) */
    public static function labels(): array
    {
        return ['accent' => __('Akzent'), 'muted' => __('Gedämpft'), 'success' => __('Grün (Erfolg)'), 'warning' => __('Orange (Achtung)'), 'danger' => __('Rot (Warnung)')];
    }

    /** <script type="application/json" id="cms-rich"> für Verwaltung und Werkzeugleiste der Website */
    public static function clientScript(): string
    {
        try {
            $p = self::palette();
        } catch (\Throwable) {
            return '';
        }
        return '<script type="application/json" id="cms-rich">' . json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
}
