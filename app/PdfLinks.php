<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * PDF-Links auf der Website: Klick öffnet eine kleine Auswahl „Anzeigen“ (PDF-Viewer) | „Herunterladen“ (Datei).
 * Links auf PDFs der Mediathek (Rich-Text über Core\Sanitizer, Buttons von „Medien + Text“, Downloads-Kombifeld) tragen beide
 * Ziele als data-pdf-view / data-pdf-file; href bleibt das im Linkdialog gewählte Ziel (ohne JavaScript, Neuer Tab, Kopieren).
 * Das Skript (js/pdf-links.mjs, Stile css/pdf-links.css) kommt nur auf Seiten mit solchen Links dazu (inject, vor dem Seiten-Cache).
 */
final class PdfLinks
{
    /** Zusätzliche Attribute für einen Link auf diese Datei – leer, wenn es keine PDF ist */
    public static function attrs(?array $m): string
    {
        if (!$m || ($m['mime'] ?? '') !== 'application/pdf' || !($view = Media::viewerUrl($m))) return '';
        $thumb = self::thumb($m);
        return ' data-pdf-view="' . e($view) . '" data-pdf-file="' . e(Media::url($m)) . '"'
            . ' data-pdf-title="' . e(Media::displayName($m)) . '" data-pdf-size="' . e(Media::humanSize((int) $m['size'])) . '"'
            . ($thumb !== null ? ' data-pdf-thumb="' . e($thumb) . '"' : '');
    }

    /** Vorschau der ersten Seite (Core\VideoThumbs, pdftoppm) – null, solange keine erzeugt ist */
    public static function thumb(array $m, int $width = 480): ?string
    {
        try {
            $p = Media::autoPoster($m);
            return $p ? Media::url($p, $width) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Attribute für einen Verweis „media:ID“ bzw. „media:ID:viewer“ */
    public static function attrsForRef(string $ref): string
    {
        return preg_match('~^media:(\d+)(?::viewer)?$~', $ref, $x) ? self::attrs(Media::find((int) $x[1])) : '';
    }

    /** Skript einfügen, wenn die Seite PDF-Links hat (Beschriftungen in der Sprache der Seite) */
    public static function inject(string $html): string
    {
        if (!str_contains($html, 'data-pdf-view=') || str_contains($html, 'data-cms-pdf-links')) return $html;
        $tag = '<script type="module" src="' . e(asset('js/pdf-links.mjs')) . '" data-cms-pdf-links data-css="' . e(asset('css/pdf-links.css')) . '"'
            . ' data-view="' . e(lt('Ansehen')) . '" data-download="' . e(lt('Herunterladen')) . '" data-type="' . e(lt('Dokument')) . '" data-close="' . e(lt('Schließen')) . '"'
            // Knöpfe im Stil des Kits (theme.php button_class / button_class_secondary)
            . ' data-btn="' . e((string) (app()->theme->def['button_class'] ?? 'btn btn--primary')) . '"'
            . ' data-btn2="' . e((string) (app()->theme->def['button_class_secondary'] ?? str_replace('btn--primary', 'btn--secondary', (string) (app()->theme->def['button_class'] ?? 'btn btn--primary')))) . '"></script>';
        $pos = strripos($html, '</body>');
        return $pos === false ? $html . $tag : substr($html, 0, $pos) . $tag . substr($html, $pos);
    }
}
