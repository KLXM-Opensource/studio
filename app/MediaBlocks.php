<?php
declare(strict_types=1);

namespace Core;

/**
 * Gemeinsame Helfer der Kern-Blöcke „Bildergalerie“, „Slider“ und „Stapelkarten“ (app/Blocks/*.php).
 * Styles: resources/css/media.css (Theme überschreibt mit public/assets/kits/{name}/css/media.css oder ergänzt per conditional_css),
 * Verhalten (Lightbox, Slider): resources/js/media.mjs – wird einmal je Seite als Modul eingebunden.
 */
final class MediaBlocks
{
    /** Bildformate der Blöcke (Zuschnitte je Format aus der Mediathek) */
    public const RATIOS = ['16:9' => 'Breitbild 16:9', '3:2' => 'Querformat 3:2', '4:3' => 'Querformat 4:3', '1:1' => 'Quadrat 1:1', '3:4' => 'Hochformat 3:4'];

    private static bool $script = false;
    private static bool $lightbox = false;

    /** Dachzeile, Überschrift, Einleitung – wie bei den Theme-Blöcken */
    public static function headFields(): array
    {
        return [
            ['name' => 'eyebrow', 'label' => 'Dachzeile (optional)', 'type' => 'text', 'max' => 60, 'width' => 'half'],
            ['name' => 'title', 'label' => 'Überschrift (optional)', 'type' => 'text', 'max' => 90, 'width' => 'half'],
            ['name' => 'intro', 'label' => 'Einleitung (optional)', 'type' => 'textarea', 'rows' => 2, 'max' => 400],
        ];
    }

    /** Kopf des Abschnitts (Klassen, die beide Themes kennen: eyebrow, h2, lead); *Betonung* in der Überschrift wie im Kit (emphasis()) */
    public static function head(Block $b): string
    {
        $d = $b->data;
        $eyebrow = trim((string) ($d['eyebrow'] ?? ''));
        $title = trim((string) ($d['title'] ?? ''));
        $intro = trim((string) ($d['intro'] ?? ''));
        if ($eyebrow === '' && $title === '' && $intro === '') {
            return '';
        }
        $h = '<header class="cms-head sec-head">';
        if ($eyebrow !== '') $h .= '<p class="eyebrow eyebrow--accent"' . $b->edit('eyebrow') . '>' . emphasis($eyebrow) . '</p>';
        if ($title !== '') $h .= '<h2 id="' . e($b->titleId()) . '" class="h2 h2--m"><span' . $b->edit('title') . '>' . emphasis($title) . '</span></h2>';
        if ($intro !== '') $h .= '<p class="lead"' . $b->edit('intro') . '>' . nl2br(emphasis($intro), false) . '</p>';
        return $h . '</header>';
    }

    /**
     * Bilder aus Sammlung oder Einzelauswahl.
     * @return list<array{m: array, caption: string, path: ?string, i: int}>
     */
    public static function images(array $d, string $list = 'images'): array
    {
        $out = [];
        if (($d['source'] ?? 'manual') === 'collection') {
            if (!empty($d['collection'])) {
                foreach (Media::all(['collection' => (int) $d['collection'], 'kind' => 'image']) as $m) {
                    $out[] = ['m' => $m, 'caption' => trim(Media::title($m)), 'path' => null, 'i' => count($out)];
                }
            }
            return $out;
        }
        foreach ((array) ($d[$list] ?? []) as $i => $it) {
            $m = is_array($it) && !empty($it['image']) ? Media::find((int) $it['image']) : null;
            if ($m && str_starts_with((string) $m['mime'], 'image/')) {
                $out[] = ['m' => $m, 'caption' => trim((string) ($it['caption'] ?? '')), 'path' => "$list.$i", 'i' => (int) $i, 'item' => $it];
            }
        }
        return $out;
    }

    /** Link-Attribute zur großen Fassung (ohne JS: normaler Link; mit JS: Lightbox mit srcset) */
    public static function lightboxLink(array $m, string $caption): string
    {
        $src = Media::sources($m);
        return ' href="' . e(Media::url($m, 1920)) . '"' . ($src['webp'] !== '' ? ' data-srcset="' . e($src['webp']) . '"' : '')
            . ' data-w="' . (int) $m['width'] . '" data-h="' . (int) $m['height'] . '"'
            . ($caption !== '' ? ' data-caption="' . e($caption) . '"' : '');
    }

    /** Lightbox-Dialog (einmal je Seite, von js/media.mjs befüllt) – Beschriftungen in der Sprache der Seite */
    public static function lightbox(): string
    {
        if (self::$lightbox || app()->editing) {
            return '';
        }
        self::$lightbox = true;
        $svg = fn(string $p) => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2"><path d="' . $p . '"/></svg>';
        return '<dialog class="cms-lb" aria-label="' . e(lt('Bildansicht')) . '" data-count="' . e(lt('Bild {n} von {total}')) . '">'
            . '<button type="button" class="cms-lb__btn cms-lb__close" aria-label="' . e(lt('Schließen')) . '">' . $svg('M6 6l12 12M18 6 6 18') . '</button>'
            . '<figure class="cms-lb__fig"><img class="cms-lb__img" alt="" sizes="100vw"><figcaption class="cms-lb__cap" aria-live="polite"><span></span><span class="cms-lb__count"></span></figcaption></figure>'
            . '<button type="button" class="cms-lb__btn cms-lb__prev" data-d="-1" aria-label="' . e(lt('Vorheriges Bild')) . '">' . $svg('M15 5l-7 7 7 7') . '</button>'
            . '<button type="button" class="cms-lb__btn cms-lb__next" data-d="1" aria-label="' . e(lt('Nächstes Bild')) . '">' . $svg('M9 5l7 7-7 7') . '</button></dialog>';
    }

    /** Klasse für „Zeilen“-Layout: Seitenverhältnis × 10, gerundet (CSS: flex-grow je Klasse) */
    public static function aspectClass(array $m): string
    {
        $w = (int) $m['width'];
        $h = (int) $m['height'];
        return 'ar-' . ($w && $h ? max(5, min(24, (int) round($w / $h * 10))) : 13);
    }

    /** Modul-Skript einmal je Seite (nicht im Bearbeiten-Modus) */
    public static function script(): string
    {
        if (self::$script || app()->editing) {
            return '';
        }
        self::$script = true;
        return '<script type="module" src="' . e(asset('js/media.mjs')) . '"></script>';
    }
}
