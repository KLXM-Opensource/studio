<?php
declare(strict_types=1);

namespace Core;

/**
 * Persönliche Akzentfarbe der Verwaltung (Konto → Darstellung & Sprache).
 *
 * Bewusst begrenzt: nur kuratierte Vorlagen, kein freier Farbwähler. Jede Vorlage ist ein vollständiger Satz Tokens
 * für Hell und Dunkel (resources/css/_accent.css, Werkzeugleiste: editor.shadow.css) und auf WCAG 2.2 AA geprüft
 * (checks(), CLI: accent:check). Gespeichert je Konto in users.ui_prefs (JSON: {"accent": "petrol", "side": true}).
 *
 * Ausgabe: <html class="adm-ui" data-accent="petrol" data-side="tint"> (layout.php) und dieselben Attribute an den
 * Schatten-Hosts auf der Website (Werkzeugleiste Core\Theme::toolbarHost, Ebene _shadow.js, Seitenleiste „Eintrag
 * bearbeiten“ _entry_edit.js) – admin.shadow.css übernimmt die Regeln als :host(.adm-ui[data-accent=…]).
 *
 * Grundeinstellungen → Website: sys.admin_accents (erlaubte Vorlagen, leer = alle), sys.admin_accent_lock (nur Standard).
 */
final class Accent
{
    public const DEFAULT = 'navy';

    /**
     * Vorlagen: Bezeichnung + Kernfarben (hell/dunkel/Seitenleiste) für Vorschau-Muster und Prüfung.
     * a = Akzent (Flächen mit weißer Schrift), ink = Akzent als Text, bg = Akzent-Hintergrund, side = Seitenleiste (mitgefärbt),
     * sa/mark = Symbole und Markierung in der Seitenleiste. Muss zu resources/css/_accent.css passen.
     */
    public const PRESETS = [
        'navy' => ['label' => 'KLXM Navy', 'a' => '#314164', 'ad' => '#243150', 'ink' => '#314164', 'bg' => '#E3E8F2',
            'da' => '#5B73AD', 'dad' => '#4C6399', 'dink' => '#A9BCE8', 'dbg' => '#232C42', 'side' => '#24314B', 'sa' => '#B4C4E8', 'mark' => '#3DD9A0'],
        'petrol' => ['label' => 'Petrol', 'a' => '#0E6470', 'ad' => '#0A4D56', 'ink' => '#0B5A65', 'bg' => '#DDEFF0',
            'da' => '#2D7F8B', 'dad' => '#236A74', 'dink' => '#8FD3DC', 'dbg' => '#17363B', 'side' => '#143C43', 'sa' => '#9ED8DF', 'mark' => '#5FE0CF'],
        'gruen' => ['label' => 'Grün', 'a' => '#2E6B3F', 'ad' => '#235232', 'ink' => '#2A6139', 'bg' => '#E1F0E4',
            'da' => '#408351', 'dad' => '#336B42', 'dink' => '#9ED6AB', 'dbg' => '#1B3322', 'side' => '#1C3D28', 'sa' => '#A9DDB5', 'mark' => '#7FE39A'],
        'violett' => ['label' => 'Violett', 'a' => '#5B3F95', 'ad' => '#48317A', 'ink' => '#553A8C', 'bg' => '#ECE6F7',
            'da' => '#7F67BA', 'dad' => '#6750A3', 'dink' => '#C8B8F0', 'dbg' => '#2C2442', 'side' => '#312651', 'sa' => '#CBBDF2', 'mark' => '#C9A8FF'],
        'bordeaux' => ['label' => 'Bordeaux', 'a' => '#8A2E45', 'ad' => '#6F2337', 'ink' => '#822A41', 'bg' => '#F6E4E8',
            'da' => '#B8556E', 'dad' => '#963D54', 'dink' => '#F0AFC0', 'dbg' => '#3A1F27', 'side' => '#4A1E2B', 'sa' => '#F2B8C6', 'mark' => '#FF9EB5'],
        'orange' => ['label' => 'Orange', 'a' => '#A34A0B', 'ad' => '#853C08', 'ink' => '#963F05', 'bg' => '#FBE9DC',
            'da' => '#B75C1D', 'dad' => '#984A14', 'dink' => '#F6B98A', 'dbg' => '#3B2718', 'side' => '#4A2812', 'sa' => '#F7C39B', 'mark' => '#FFB36B'],
        'graphit' => ['label' => 'Graphit', 'a' => '#3D434D', 'ad' => '#2E333B', 'ink' => '#3D434D', 'bg' => '#E6E8EB',
            'da' => '#6E7684', 'dad' => '#5A606C', 'dink' => '#C3C8D1', 'dbg' => '#2A2D33', 'side' => '#2A2D33', 'sa' => '#C3C8D1', 'mark' => '#9FE0C8'],
    ];

    /** Vorlagen mit übersetzter Bezeichnung [key => label] */
    public static function labels(): array
    {
        $out = [];
        foreach (self::PRESETS as $k => $p) $out[$k] = $k === self::DEFAULT ? $p['label'] : __($p['label']);
        return $out;
    }

    /** Auf dieser Website erlaubte Vorlagen (Grundeinstellungen); Standard ist immer dabei */
    public static function allowed(): array
    {
        if (self::locked()) return [self::DEFAULT];
        $list = array_values(array_filter((array) setting('sys.admin_accents', []), fn($k) => isset(self::PRESETS[(string) $k])));
        if (!$list) return array_keys(self::PRESETS);
        return array_values(array_unique([self::DEFAULT, ...$list]));
    }

    public static function locked(): bool
    {
        return (bool) setting('sys.admin_accent_lock', false);
    }

    /** Gespeicherte Wahl eines Kontos (roh, ungeprüft gegen die Website-Einstellung) */
    public static function prefs(?array $user): array
    {
        $p = json_decode((string) ($user['ui_prefs'] ?? ''), true);
        $p = is_array($p) ? $p : [];
        $accent = (string) ($p['accent'] ?? self::DEFAULT);
        return ['accent' => isset(self::PRESETS[$accent]) ? $accent : self::DEFAULT, 'side' => !array_key_exists('side', $p) || !empty($p['side'])];   // Seitenleiste färbt standardmäßig mit
    }

    /** Wirksame Wahl (nicht erlaubte Vorlagen fallen auf den Standard zurück) */
    public static function current(?array $user = null): array
    {
        $user ??= isset(app()->auth) ? app()->auth->user() : null;
        $p = self::prefs($user);
        if (!in_array($p['accent'], self::allowed(), true)) $p['accent'] = self::DEFAULT;
        if ($p['accent'] === self::DEFAULT) $p['side'] = false;   // Navy ist die Farbe der Seitenleiste
        return $p;
    }

    /** Attribute für <html class="adm-ui"> bzw. Schatten-Hosts: ' data-accent="petrol" data-side="tint"' (Standard: leer) */
    public static function attrs(?array $user = null): string
    {
        $p = self::current($user);
        if ($p['accent'] === self::DEFAULT) return '';
        return ' data-accent="' . e($p['accent']) . '"' . ($p['side'] ? ' data-side="tint"' : '');
    }

    /** Dieselben Werte für JavaScript (Seitenleiste „Eintrag bearbeiten“) */
    public static function client(?array $user = null): array
    {
        $p = self::current($user);
        return $p['accent'] === self::DEFAULT ? ['accent' => '', 'side' => ''] : ['accent' => $p['accent'], 'side' => $p['side'] ? 'tint' : ''];
    }

    /** Wahl speichern (nur erlaubte Vorlagen) */
    public static function save(int $userId, string $accent, bool $side): void
    {
        if (!in_array($accent, self::allowed(), true)) $accent = self::DEFAULT;
        $row = app()->db->fetch('SELECT ui_prefs FROM users WHERE id = ?', [$userId]);
        $p = json_decode((string) ($row['ui_prefs'] ?? ''), true);
        $p = is_array($p) ? $p : [];
        $p['accent'] = $accent;
        // Bei Navy gibt es nichts mitzufärben – dann die Wahl nicht speichern (Standard „mitfärben“ bleibt für später)
        if ($accent === self::DEFAULT) unset($p['side']); else $p['side'] = $side;
        app()->db->update('users', ['ui_prefs' => json_encode($p)], 'id = :id', ['id' => $userId]);
    }

    /**
     * Kontrastprüfung aller Vorlagen (WCAG 2.x): Text 4,5:1, Bedienelemente/Fokus 3:1 – hell, dunkel, Seitenleiste.
     * @return list<array{preset: string, check: string, ratio: float, min: float, ok: bool}>
     */
    public static function checks(): array
    {
        $light = ['#E5E7EF', '#FFFFFF'];
        $dark = ['#15171D', '#1E2129', '#272A34'];
        $out = [];
        foreach (self::PRESETS as $k => $p) {
            $min = fn(string $fg, array $bgs) => min(array_map(fn($b) => Design::contrast($fg, $b), $bgs));
            $rows = [
                ['Hell: Weiß auf Akzent', Design::contrast('#FFFFFF', $p['a']), 4.5],
                ['Hell: Weiß auf Akzent (Hover)', Design::contrast('#FFFFFF', $p['ad']), 4.5],
                ['Hell: Akzent-Text auf Hintergrund/Karte', $min($p['ink'], $light), 4.5],
                ['Hell: Akzent-Text auf Akzent-Hintergrund', Design::contrast($p['ink'], $p['bg']), 4.5],
                ['Hell: Akzent/Fokusring gegen Hintergrund', $min($p['a'], $light), 3.0],
                ['Dunkel: Weiß auf Akzent', Design::contrast('#FFFFFF', $p['da']), 4.5],
                ['Dunkel: Weiß auf Akzent (Hover)', Design::contrast('#FFFFFF', $p['dad']), 4.5],
                ['Dunkel: Akzent-Text auf Hintergrund/Karte', $min($p['dink'], $dark), 4.5],
                ['Dunkel: Akzent-Text auf Akzent-Hintergrund', Design::contrast($p['dink'], $p['dbg']), 4.5],
                ['Dunkel: Akzent/Fokusring gegen Hintergrund', $min($p['da'], $dark), 3.0],
                ['Seitenleiste: Text', $min('#F4F6FA', [$p['side'], '#24314B']), 4.5],
                ['Seitenleiste: gedämpfter Text', $min('#AEB6C8', [$p['side'], '#24314B']), 4.5],
                ['Seitenleiste: Symbole', $min($p['sa'], [$p['side'], '#24314B']), 3.0],
                ['Seitenleiste: Markierung aktiver Punkt', $min($p['mark'], [$p['side'], '#24314B']), 3.0],
            ];
            foreach ($rows as [$label, $ratio, $req]) {
                $out[] = ['preset' => $k, 'check' => $label, 'ratio' => $ratio, 'min' => $req, 'ok' => $ratio >= $req];
            }
        }
        return $out;
    }
}
