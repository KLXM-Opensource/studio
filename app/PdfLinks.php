<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * PDF-Links auf der Website: Klick öffnet eine kleine Auswahl „Ansehen“ (PDF-Viewer im Modal) | „Herunterladen“ (Datei).
 * Links auf PDFs der Mediathek tragen beide Ziele als data-pdf-view / data-pdf-file; href bleibt das im Linkdialog gewählte Ziel
 * (ohne JavaScript, Neuer Tab, Kopieren). Rich-Text (Core\Sanitizer), „Medien + Text“ und Downloads setzen die Attribute selbst;
 * alle übrigen Links auf eine PDF der Website (/media/….pdf oder /pdf/ID, z. B. Buttons in Kits) ergänzt enhance() beim Ausliefern.
 * In Slidern (data-cms-slider) und Bereichen mit data-pdf-direct öffnet der Klick gleich den Viewer – ohne Karte.
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

    /**
     * Links auf PDFs der Website ohne data-pdf-view ergänzen: href „{Medienordner}/….pdf“ (Datei) oder „/pdf/ID“ (Viewer).
     * Nur Dateien dieser Website (keine Pools, keine fremden Hosts); Anfrage, Fragment und Attribute bleiben unverändert.
     */
    public static function enhance(string $html): string
    {
        if (!str_contains($html, '.pdf') && !str_contains($html, '/pdf/')) return $html;
        $media = site()->mediaUrl('/');
        $viewer = url('/pdf/');
        $byFile = [];
        return preg_replace_callback('~<a\s(?![^>]*\bdata-pdf-view=)[^>]*?\bhref="([^"#?]+)[^"]*"[^>]*>~i', function (array $x) use ($media, $viewer, &$byFile): string {
            $href = html_entity_decode($x[1], ENT_QUOTES);
            $m = null;
            if (str_starts_with($href, $media) && str_ends_with(strtolower($href), '.pdf')) {
                $file = rawurldecode(substr($href, strlen($media)));
                $m = $byFile[$file] ??= (Media::db()->fetch("SELECT * FROM media WHERE file = ? AND mime = 'application/pdf' AND deleted_at IS NULL", [$file]) ?: false);
            } elseif (str_starts_with($href, $viewer) && ctype_digit($id = substr($href, strlen($viewer)))) {
                $m = Media::find((int) $id);
            }
            $attrs = $m ? self::attrs($m) : '';
            return $attrs === '' ? $x[0] : substr($x[0], 0, -1) . $attrs . '>';
        }, $html) ?? $html;
    }

    /** Skript einfügen, wenn die Seite PDF-Links hat (Beschriftungen in der Sprache der Seite) */
    public static function inject(string $html): string
    {
        $html = self::enhance($html);
        if (!str_contains($html, 'data-pdf-view=') || str_contains($html, 'data-cms-pdf-links')) return $html;
        $tag = '<script type="module" src="' . e(asset('js/pdf-links.mjs')) . '" data-cms-pdf-links data-css="' . e(asset('css/pdf-links.css')) . '"'
            . ' data-view="' . e(lt('Ansehen')) . '" data-download="' . e(lt('Herunterladen')) . '" data-type="' . e(lt('Dokument')) . '" data-close="' . e(lt('Schließen')) . '"'
            . ' data-newtab="' . e(lt('In neuem Tab öffnen')) . '" data-copy="' . e(lt('Link kopieren')) . '" data-copied="' . e(lt('Link kopiert')) . '"'
            // Knöpfe im Stil des Kits (theme.php button_class / button_class_secondary)
            . ' data-btn="' . e((string) (app()->theme->def['button_class'] ?? 'btn btn--primary')) . '"'
            . ' data-btn2="' . e((string) (app()->theme->def['button_class_secondary'] ?? str_replace('btn--primary', 'btn--secondary', (string) (app()->theme->def['button_class'] ?? 'btn btn--primary')))) . '"></script>';
        $pos = strripos($html, '</body>');
        return $pos === false ? $html . $tag : substr($html, 0, $pos) . $tag . substr($html, $pos);
    }
}
