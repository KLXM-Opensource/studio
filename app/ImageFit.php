<?php
declare(strict_types=1);

namespace Core;

/**
 * Bild im Rahmen: füllen, einpassen, Originalformat – je Einbindung im Block oder als Standard des Bildes.
 *
 *  - Kits zeigen Bilder meist in Rahmen mit festem Seitenverhältnis und object-fit: cover (Zuschnitt + Fokuspunkt).
 *    Passt das nicht (Hochformat im 16:10-Rahmen, Logo, SVG), wählt die Redaktion je Stelle:
 *      cover     Füllen (zuschneiden) – wie bisher, Verhalten des Kits
 *      contain   Einpassen – ganzes Bild im Rahmen, Hintergrund: transparent, Farbe, Farbe des Kits oder unscharfes Bild
 *      original  Originalformat – der Rahmen übernimmt das Seitenverhältnis des Bildes (kein Zuschnitt)
 *  - Speicherformat (kompakt, kanonisch): „cover“, „original“, „contain“ (= transparent), „contain blur“,
 *    „contain #1e2638“, „contain kit:surface“ (Farb-Token des Kits, Core\Design). Leer = erben.
 *  - Zwei Ebenen wie bei Core\ImageFx: data._fit = {"image": "contain blur", "items.2.image": "original"} je Einbindung,
 *    Spalte media.fit als Standard des Bildes. Ohne beides: SVG und PNG mit transparentem Rand werden eingepasst
 *    (transparent) – außer es gibt für das Bildformat einen eigenen Zuschnitt.
 *  - Ausgabe ohne Kit-Änderung: Media::pictureOf() setzt Klassen am <picture> (img-fit, img-fit--contain|original,
 *    img-fit--blur, img-fit-c-…/img-fit-k-…/img-fit-src-…), lädt beim Einpassen das ganze Bild statt des Zuschnitts.
 *    Kern-Stylesheet resources/css/image-fit.css (Vorrang per !important vor object-fit: cover der Kits). Werte je Bild
 *    (Farbe, Adresse der kleinsten Größe für „unscharf“) stehen – strenge CSP, keine style-Attribute – in einer kleinen
 *    erzeugten CSS-Datei je Satz Regeln (media/fit/fit-<hash>.css), die inject() zusammen mit dem Stylesheet einbindet.
 *  - CSS-Variablen für Kits: --img-fit-bg (Hintergrundfarbe beim Einpassen), --img-fit-src (Bild für „unscharf“),
 *    --img-fit-blur (Stärke, Standard 22px).
 */
final class ImageFit
{
    public const MODES = ['cover', 'contain', 'original'];
    /** Farb-Tokens, die als Hintergrund nicht passen (Schrift, Linien, Knöpfe) */
    private const TOKEN_SKIP = '~(ink|text|muted|line|button|btn|on_|_on|border|focus|link)~';

    /**
     * Stapel der Block-Zuordnungen: ['paths' => [feldpfad => einstellung], 'queue' => [medien-id => [einstellung je Vorkommen]],
     * 'pos' => [medien-id => nächstes Vorkommen]]. Früher [medien-id => einstellung] (erste gewann) – dasselbe Bild ließ sich
     * in einem Block nicht an zwei Stellen verschieden einpassen.
     */
    private static array $stack = [];
    /** Regeln für die erzeugte CSS-Datei: Klasse => Deklaration */
    private static array $rules = [];
    /** Transparenz je Medien-ID (für diese Anfrage) */
    private static array $alpha = [];

    // ================================================================= Format

    /** Zerlegen. @return ?array{mode: string, bg: string} null = ungültig; mode '' = erben */
    public static function parse(?string $v): ?array
    {
        $v = strtolower(trim((string) $v));
        if ($v === '') return ['mode' => '', 'bg' => ''];
        if (strlen($v) > 60) return null;
        $tok = preg_split('~\s+~', $v);
        $mode = array_shift($tok);
        if (!in_array($mode, self::MODES, true) || count($tok) > 1) return null;
        $bg = self::bg($tok[0] ?? '');
        if ($bg === null || ($mode !== 'contain' && $bg !== '')) return null;
        return ['mode' => $mode, 'bg' => $bg];
    }

    /** Hintergrund prüfen: '' (transparent), blur, #rrggbb, kit:token – null = ungültig */
    private static function bg(string $b): ?string
    {
        $b = strtolower(trim($b));
        if ($b === '' || $b === 'transparent') return '';
        if ($b === 'blur') return 'blur';
        if (preg_match('~^#([0-9a-f]{3})$~', $b, $m)) return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        if (preg_match('~^#[0-9a-f]{6}$~', $b)) return $b;
        if (preg_match('~^kit:[a-z][a-z0-9_]{0,40}$~', $b)) return $b;
        return null;
    }

    /**
     * Kanonische Schreibweise (null = ungültig, '' = erben). Nimmt auch {mode, bg} (z. B. aus der API).
     */
    public static function normalize(mixed $in): ?string
    {
        if (is_array($in)) {
            $in = trim((string) ($in['mode'] ?? '') . ' ' . (string) ($in['bg'] ?? $in['background'] ?? ''));
        }
        if ($in !== null && !is_string($in)) return null;
        $a = self::parse($in);
        if ($a === null) return null;
        return trim($a['mode'] . ($a['bg'] !== '' ? ' ' . $a['bg'] : ''));
    }

    /** Lesbare Kurzbeschreibung (Verwaltung), z. B. „Einpassen · unscharf“ */
    public static function label(?string $v): string
    {
        $a = self::parse($v);
        if (!$a || $a['mode'] === '') return '';
        return match ($a['mode']) {
            'cover' => __('Füllen (zuschneiden)'),
            'original' => __('Originalformat'),
            default => __('Einpassen') . ' · ' . self::bgLabel($a['bg']),
        };
    }

    private static function bgLabel(string $bg): string
    {
        if ($bg === '') return __('transparent');
        if ($bg === 'blur') return __('unscharf');
        if (str_starts_with($bg, 'kit:')) {
            $t = Design::tokens()[substr($bg, 4)] ?? null;
            return $t ? (string) ($t['label'] ?? substr($bg, 4)) : substr($bg, 4);
        }
        return strtoupper($bg);
    }

    /** Farben des Kits als Vorschläge: [{name, label, value}] – Farb-Tokens aus theme.php → design (ohne Schrift/Linien) */
    public static function presets(): array
    {
        $out = [];
        $values = Design::enabled() ? Design::values() : [];
        foreach (Design::tokens() as $n => $t) {
            if (($t['type'] ?? 'color') !== 'color' || empty($t['var']) || preg_match(self::TOKEN_SKIP, (string) $n)) continue;
            $out[] = ['name' => (string) $n, 'label' => (string) ($t['label'] ?? $n), 'value' => (string) ($values[$n] ?? $t['default'] ?? '')];
        }
        return $out;
    }

    // ================================================================= Block-Kontext (Einbindung)

    /**
     * Zuordnung eines Blocks setzen (Core\Theme::renderBlock) – wie ImageFx::enter(), mit leave() beenden.
     * Schlüssel ist der Feldpfad (so wird data._fit gespeichert). Bilder ohne Pfadangabe (img() in Kit-Vorlagen) bekommen die
     * Einstellung ihres n-ten Vorkommens im Block: $fields (Schema des Blocks) liefert alle Bild-Felder in Dokument-Reihenfolge
     * (auch in Listen). Ohne $fields (ältere Aufrufer) zählen nur die Pfade aus _fit – wie bisher.
     */
    public static function enter(array $data, array $fields = []): void
    {
        $fit = is_array($data['_fit'] ?? null) ? $data['_fit'] : [];
        if (!$fit) {
            self::$stack[] = ['paths' => [], 'queue' => [], 'pos' => []];
            return;
        }
        $paths = [];
        foreach ($fit as $path => $v) {
            $v = self::normalize($v);
            if ($v !== null && $v !== '') $paths[(string) $path] = $v;
        }
        $order = $fields ? self::mediaPaths($fields, $data) : array_keys($paths);
        if (!$fields) natsort($order);
        $queue = [];
        foreach ($order as $path) {
            $id = ImageFx::valueAt($data, (string) $path);
            if ($id > 0) $queue[$id][] = $paths[$path] ?? '';
        }
        // Bilder, deren Pfad im Schema fehlt (z. B. geänderte Felder), wenigstens über die ID
        foreach ($paths as $path => $v) {
            $id = ImageFx::valueAt($data, (string) $path);
            if ($id > 0 && !isset($queue[$id])) $queue[$id][] = $v;
        }
        self::$stack[] = ['paths' => $paths, 'queue' => $queue, 'pos' => []];
    }

    /** Pfade aller Bild-Felder eines Blocks in Dokument-Reihenfolge (auch in Listen/Gruppen), z. B. image, items.0.image */
    public static function mediaPaths(array $fields, array $data, string $prefix = '', int $depth = 0): array
    {
        $out = [];
        foreach ($fields as $f) {
            $name = (string) ($f['name'] ?? '');
            if ($name === '') continue;
            $type = $f['type'] ?? 'text';
            if ($type === 'media') {
                $out[] = $prefix . $name;
            } elseif (in_array($type, ['repeater', 'group'], true) && $depth < 4 && is_array($data[$name] ?? null)) {
                foreach ($data[$name] as $i => $item) {
                    if (is_array($item) && is_int($i)) array_push($out, ...self::mediaPaths((array) ($f['fields'] ?? []), $item, "$prefix$name.$i.", $depth + 1));
                }
            }
        }
        return $out;
    }

    public static function leave(): void
    {
        array_pop(self::$stack);
    }

    /** Einstellung der Einbindung im aktuellen Block: nach Feldpfad, sonst nach Vorkommen der Medien-ID ('' = keine) */
    private static function bound(int $id, ?string $path): string
    {
        $i = array_key_last(self::$stack);
        if ($i === null || $id <= 0) return '';
        $top = &self::$stack[$i];
        if ($path !== null && $path !== '') {
            return $top['paths'][$path] ?? '';
        }
        $list = $top['queue'][$id] ?? [];
        if (!$list) return '';
        $n = $top['pos'][$id] ?? 0;
        $top['pos'][$id] = $n + 1;
        // Mehr Ausgaben als Vorkommen (Kit gibt ein Bild doppelt aus): wie früher die erste Einstellung
        return $list[$n] ?? $list[0];
    }

    /**
     * Wirksame Einstellung für ein Bild: Einbindung → Standard des Bildes → automatisch (SVG, transparenter Rand).
     * $path: Feldpfad im Block (img(…, ['path' => 'items.2.image'])) – ohne Pfad zählt das Vorkommen der Medien-ID.
     * @return ?array{mode: string, bg: string, auto: bool} null = Verhalten des Kits (füllen)
     */
    public static function resolve(array $m, ?string $ratio = null, ?string $path = null): ?array
    {
        $id = (int) ($m['id'] ?? 0);
        $v = self::bound($id, $path);
        if ($v === '') $v = (string) ($m['fit'] ?? '');
        $auto = false;
        if ($v === '') {
            $v = self::auto($m, $ratio);
            $auto = true;
        }
        $a = self::parse($v);
        if (!$a || $a['mode'] === '' || $a['mode'] === 'cover') return null;
        return $a + ['auto' => $auto];
    }

    /** Automatischer Standard: SVG und Bilder mit transparentem Rand einpassen – nicht bei eigenem Zuschnitt für das Format */
    public static function auto(array $m, ?string $ratio = null): string
    {
        $mime = (string) ($m['mime'] ?? '');
        if ($mime === Svg::MIME) return 'contain';
        if ($ratio !== null && isset(Media::crops($m)[$ratio])) return '';
        return self::hasAlpha($m) ? 'contain' : '';
    }

    /** Klassen am <picture> (und Regeln für die erzeugte CSS-Datei) */
    public static function pictureClass(array $a, array $m): string
    {
        $c = ['img-fit', 'img-fit--' . $a['mode']];
        $bg = $a['mode'] === 'contain' ? $a['bg'] : '';
        if ($bg === 'blur') {
            $src = Media::url($m, 480, 'webp');   // kleinste Größe (SVG: die Datei selbst)
            $key = 'img-fit-src-' . substr(sha1($src), 0, 10);
            self::$rules[$key] = '--img-fit-src:url("' . str_replace(['\\', '"', "\n", "\r"], ['%5C', '%22', '', ''], $src) . '")';
            $c[] = 'img-fit--blur';
            $c[] = $key;
        } elseif (str_starts_with($bg, '#')) {
            $key = 'img-fit-c-' . substr($bg, 1);
            self::$rules[$key] = '--img-fit-bg:' . $bg;
            $c[] = $key;
        } elseif (str_starts_with($bg, 'kit:')) {
            $t = Design::tokens()[substr($bg, 4)] ?? null;
            $var = (string) ($t['var'] ?? '');
            if ($t && preg_match('~^--[a-z0-9-]+$~i', $var)) {
                $key = 'img-fit-k-' . str_replace('_', '-', substr($bg, 4));
                self::$rules[$key] = '--img-fit-bg:var(' . $var . ')';
                $c[] = $key;
            }
        }
        if (!empty($a['auto'])) $c[] = 'img-fit--auto';
        return implode(' ', $c);
    }

    // ================================================================= Transparenz (PNG, GIF)

    /**
     * Hat das Bild einen (teilweise) transparenten Rand? Typisch für Logos und Freisteller. Ergebnis wird in
     * variants_json['alpha'] gemerkt (einmalig je Bild; beim Hochladen gleich gesetzt, siehe Media::process()).
     */
    public static function hasAlpha(array $m, bool $compute = true): bool
    {
        $mime = (string) ($m['mime'] ?? '');
        if (!in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) return false;
        $id = (int) ($m['id'] ?? 0);
        if ($id && isset(self::$alpha[$id])) return self::$alpha[$id];
        $v = json_decode((string) ($m['variants_json'] ?? ''), true) ?: [];
        if (array_key_exists('alpha', $v)) return self::$alpha[$id] = (bool) $v['alpha'];
        if (!$compute) return false;   // Listen in der Verwaltung: nur gemerkte Werte
        $alpha = false;
        try {
            $file = Media::path($m);
            $img = is_file($file) ? match ($mime) {
                'image/png' => @imagecreatefrompng($file), 'image/gif' => @imagecreatefromgif($file), default => @imagecreatefromwebp($file),
            } : false;
            if ($img) {
                $alpha = self::edgeAlpha($img);
                imagedestroy($img);
                // Merken – bei geteilten Medien im Pool (Media::find: _pool, _pool_id)
                $v['alpha'] = $alpha;
                if (!empty($m['_pool']) && !empty($m['_pool_id'])) {
                    MediaPools::db((string) $m['_pool'])->update('media', ['variants_json' => json_encode($v)], 'id = :id', ['id' => (int) $m['_pool_id']]);
                } elseif ($id) {
                    Media::db()->update('media', ['variants_json' => json_encode($v)], 'id = :id', ['id' => $id]);
                }
            }
        } catch (\Throwable $e) {
            error_log('[media] alpha: ' . $e->getMessage());
        }
        return $id ? self::$alpha[$id] = $alpha : $alpha;
    }

    /** Transparenter Rand: mindestens ein Viertel der Randpunkte (verkleinert) überwiegend durchsichtig */
    public static function edgeAlpha(\GdImage $img): bool
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w < 2 || $h < 2) return false;
        if (!imageistruecolor($img)) {
            if (imagecolortransparent($img) < 0) return false;
            imagepalettetotruecolor($img);
        }
        $n = 0;
        $hits = 0;
        $step = max(1, intdiv(min($w, $h), 48));
        for ($x = 0; $x < $w; $x += $step) {
            foreach ([0, $h - 1] as $y) {
                $n++;
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 96) $hits++;
            }
        }
        for ($y = 0; $y < $h; $y += $step) {
            foreach ([0, $w - 1] as $x) {
                $n++;
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 96) $hits++;
            }
        }
        return $n > 0 && $hits * 4 >= $n;
    }

    // ================================================================= Prüfung beim Speichern (Core\Pages::sanitizeBlocks)

    /** Bereinigt data._fit eines Blocks wie ImageFx::sanitize(): nur Bild-Felder des Schemas (auch in Listen) mit Bild */
    public static function sanitize(mixed $fit, array $fields, array $data): array
    {
        $out = [];
        if (!is_array($fit)) return $out;
        foreach ($fit as $path => $v) {
            $path = (string) $path;
            if (count($out) >= 60 || strlen($path) > 120 || !preg_match('~^[a-z_][a-z0-9_]*(\.(\d{1,3}|[a-z_][a-z0-9_]*))*$~i', $path)) continue;
            if (!ImageFx::isMediaPath($fields, $path)) continue;
            $n = self::normalize(is_scalar($v) || is_array($v) ? $v : null);
            $bound = isset($data['_bind'][explode('.', $path)[0]]) || str_starts_with((string) ImageFx::rawAt($data, $path), '@');
            if ($n === null || $n === '' || (!$bound && ImageFx::valueAt($data, $path) <= 0)) continue;
            $out[$path] = $n;
        }
        ksort($out, SORT_NATURAL);
        return $out;
    }

    // ================================================================= Stylesheets einbinden

    public static function cssUrl(): string
    {
        return asset('css/image-fit.css');
    }

    /** Inhalt der erzeugten CSS-Datei für die gesammelten Regeln ('' = keine) */
    public static function css(): string
    {
        if (!self::$rules) return '';
        $r = self::$rules;
        ksort($r);
        $out = '';
        foreach ($r as $cls => $decl) $out .= '.' . $cls . '{' . $decl . "}\n";
        return $out;
    }

    /** Erzeugte CSS-Datei (je Satz Regeln einmal geschrieben, Name = Prüfsumme) → URL, null = keine Regeln */
    public static function rulesUrl(): ?string
    {
        $css = self::css();
        if ($css === '') return null;
        $name = 'fit-' . substr(sha1($css), 0, 12) . '.css';
        $dir = site()->mediaDir('fit');
        if (!is_file("$dir/$name")) {
            @mkdir($dir, 0775, true);
            @file_put_contents("$dir/$name", "/* Bild im Rahmen (Core\\ImageFit) – automatisch erzeugt */\n" . $css, LOCK_EX);
        }
        return site()->mediaUrl('fit/' . $name);
    }

    /** <link> der erzeugten Regeln (Live-Vorschau eines Blocks im Editor) – '' ohne Regeln */
    public static function rulesLink(): string
    {
        $u = self::rulesUrl();
        return $u ? '<link rel="stylesheet" href="' . e($u) . '" data-img-fit-rules>' : '';
    }

    /**
     * Fügt Stylesheet und erzeugte Regeln vor </head> ein, wenn die Ausgabe eingepasste Bilder enthält (oder $force,
     * Bearbeiten-Modus). Kits müssen dafür nichts ändern. Danach sind die Regeln verbraucht.
     */
    public static function inject(string $html, bool $force = false): string
    {
        $need = $force || str_contains($html, 'class="img-fit ');
        $pos = stripos($html, '</head>');
        if (!$need || $pos === false) return $html;
        $link = str_contains($html, 'data-img-fit-css') ? '' : '<link rel="stylesheet" href="' . e(self::cssUrl()) . '" data-img-fit-css>' . "\n";
        $link .= self::rulesLink();
        self::$rules = [];
        return $link === '' ? $html : substr_replace($html, $link . "\n", $pos, 0);
    }

    /** Nur für Selbsttests: gesammelte Regeln verwerfen */
    public static function reset(): void
    {
        self::$rules = [];
    }
}
