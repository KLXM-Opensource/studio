<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Feldtyp „Medien + Text“ auf der Website (Core\Data\Entries::html): formatierter Text und mehrere Bilder/Videos – über dem Text
 * oder links/rechts daneben (Breite ⅓ oder ½, wahlweise mit Textumlauf), als Liste untereinander, Raster mit Großansicht oder Slider.
 * Großansicht und Slider nutzen die Kern-Bausteine der Bildergalerie (Core\MediaBlocks, js/media.mjs, css/media.css) –
 * ohne JavaScript: Raster-Bilder sind Links zur großen Fassung, der Slider ist ein wischbarer Streifen.
 * Medien landen nie im Text selbst (Core\Sanitizer); auf schmalen Bildschirmen stehen sie immer über dem Text (css/mediatext.css).
 */
final class MediaText
{
    /** Alle Abschnitte eines Feldes – mit Zwischenüberschrift (h3), sonst nur Inhalt */
    public static function sections(array $sections, array $o = []): string
    {
        $h = '';
        foreach ($sections as $i => $s) {
            $title = trim((string) ($s['title'] ?? ''));
            $body = self::html($s, $o + ($title !== '' ? ['label' => $title] : []));
            if ($title === '' && $body === '') continue;
            $h .= count($sections) > 1 || $title !== '' ? '<section class="mt-part" data-mt-index="' . (int) $i . '">' . ($title !== '' ? '<h3 class="mt-part__h">' . emphasis($title) . '</h3>' : '') . $body . '</section>' : $body;
        }
        return $h;
    }

    /** @param array $v ein Abschnitt (Core\Fields::mediaText)  @param array $o label = Name für Slider/Raster (z. B. Titel des Eintrags) */
    public static function html(array $v, array $o = []): string
    {
        $v = Fields::mediaText($v);
        $text = $v['text'] !== '' ? '<div class="mt__text prose">' . emphasis_rich($v['text']) . '</div>' : '';
        $items = array_values(array_filter(array_map(fn($id) => Media::find((int) $id), $v['media'])));
        if (!$items) return $text;
        $side = $v['pos'] !== 'top';
        $sizes = !$side ? '(min-width: 1080px) 800px, 100vw' : ($v['width'] === 'half' ? '(min-width: 900px) 420px, 100vw' : '(min-width: 900px) 300px, 100vw');
        $media = match ($v['view']) {
            'grid' => self::grid($items, $o),
            'slider' => count($items) > 1 ? self::slider($items, $sizes, $o) : self::stack($items, $sizes),
            default => self::stack($items, $sizes),
        };
        $cls = 'mt mt--' . $v['pos'] . ($side ? ' mt--w-' . $v['width'] . ($v['wrap'] ? ' mt--wrap' : ' mt--cols') : '') . ' mt--' . $v['view'] . ($text === '' ? ' mt--only' : '');
        return '<div class="' . $cls . '"><div class="mt__media">' . $media . '</div>' . $text . '</div>';
    }

    /** Ein Medium: Bild (Bildunterschrift = Fotonachweis wie in der Mediathek) oder Video mit Player (Untertitel, Transkript) */
    private static function one(array $m, string $sizes, array $opt = []): string
    {
        if (str_starts_with((string) $m['mime'], 'video/')) return MediaTracks::player($m, ['class' => 'mt__video']);
        return Media::pictureOf($m, $sizes, $opt);
    }

    private static function stack(array $items, string $sizes): string
    {
        $h = '';
        foreach ($items as $m) {
            $cap = trim((string) ($m['credit'] ?? ''));
            $h .= '<figure class="mt__fig">' . self::one($m, $sizes) . ($cap !== '' ? '<figcaption class="mt__cap">' . e($cap) . '</figcaption>' : '') . '</figure>';
        }
        return $h;
    }

    /** Raster: Bilder quadratisch zugeschnitten, Klick öffnet die Großansicht (Lightbox der Bildergalerie); Videos als Player in der Zelle */
    private static function grid(array $items, array $o): string
    {
        $h = '<div class="mt__grid" data-cms-lightbox role="list" aria-label="' . e(($o['label'] ?? '') !== '' ? lt('Bilder: {title}', ['title' => strip_emphasis((string) $o['label'])]) : lt('Bilder')) . '">';
        foreach ($items as $m) {
            if (str_starts_with((string) $m['mime'], 'video/')) {
                $h .= '<div class="mt__cell mt__cell--video" role="listitem">' . self::one($m, '300px') . '</div>';
                continue;
            }
            $cap = trim((string) (Media::alt($m) ?: ''));
            $h .= '<a class="mt__cell" role="listitem"' . MediaBlocks::lightboxLink($m, $cap) . '>' . Media::pictureOf($m, '(min-width: 900px) 240px, 50vw', ['ratio' => '1:1']) . '</a>';
        }
        return $h . '</div>' . MediaBlocks::lightbox() . MediaBlocks::script();
    }

    /** Slider der Bildergalerie (Scroll-Snap, js/media.mjs ergänzt Pfeile und Punkte) */
    private static function slider(array $items, string $sizes, array $o): string
    {
        static $n = 0;
        $id = 'mt-slides-' . (++$n);
        $total = count($items);
        $icon = fn(string $p) => '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="' . $p . '"/></svg>';
        $cfg = ['autoplay' => 0, 'loop' => false, 'pause' => lt('Automatisches Weiterblättern anhalten'), 'play' => lt('Automatisches Weiterblättern starten')];
        $h = '<div class="cms-slider cms-slider--slide cms-slider--h-4-3 mt__slider" role="region" aria-roledescription="' . e(lt('Karussell')) . '" aria-label="'
            . e(($o['label'] ?? '') !== '' ? lt('Bildfolge: {title}', ['title' => strip_emphasis((string) $o['label'])]) : lt('Bilder')) . '"'
            . (is_editing() ? '' : ' data-cms-slider="' . e((string) json_encode($cfg, JSON_UNESCAPED_UNICODE)) . '"') . '>'
            . '<div class="cms-slider__track" id="' . $id . '" tabindex="0">';
        foreach ($items as $i => $m) {
            $h .= '<div class="cms-slide" id="' . $id . '-' . ($i + 1) . '" role="group" aria-roledescription="' . e(lt('Folie')) . '" aria-label="' . e(lt('{n} von {total}', ['n' => $i + 1, 'total' => $total])) . '">'
                . '<div class="cms-slide__media">' . self::one($m, $sizes) . '</div></div>';
        }
        $h .= '</div><div class="cms-slider__nav" hidden>'
            . '<button type="button" class="cms-slider__arrow" data-dir="-1" aria-controls="' . $id . '" aria-label="' . e(lt('Vorherige Folie')) . '">' . $icon('M15.4 5.4 14 4l-8 8 8 8 1.4-1.4L8.8 12z') . '</button><div class="cms-slider__dots">';
        for ($k = 1; $k <= $total; $k++) {
            $h .= '<button type="button" class="cms-slider__dot" aria-controls="' . $id . '" aria-label="' . e(lt('Folie {n}', ['n' => $k])) . '"' . ($k === 1 ? ' aria-current="true"' : '') . '></button>';
        }
        return $h . '</div><button type="button" class="cms-slider__arrow" data-dir="1" aria-controls="' . $id . '" aria-label="' . e(lt('Nächste Folie')) . '">' . $icon('M8.6 5.4 10 4l8 8-8 8-1.4-1.4 6.6-6.6z') . '</button></div></div>'
            . MediaBlocks::script();
    }
}
