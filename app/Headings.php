<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Seitenüberschrift: Jede Seite braucht genau eine <h1> (Suchmaschinen, Screenreader). Beginnt eine Seite nicht mit einem
 * Einstieg (Hero), sondern z. B. mit einem Glossar oder einer Datenliste, fehlt sie – dann wird die erste Abschnitts-Überschrift
 * in <main> zur <h1>. Nur Überschriften mit der Klasse „h2“: deren Größe kommt aus der Klasse, nicht aus dem Element – optisch
 * bleibt alles gleich. SiteController::render, nur für Besucher und die Entwurfsansicht, nie im Bearbeiten-Modus.
 */
final class Headings
{
    public static function ensureH1(string $html): string
    {
        if (preg_match('~<h1[\s>]~i', $html)) return $html;
        $main = stripos($html, '<main');
        if ($main === false) return $html;
        // nur die erste Abschnitts-Überschrift – hat sie keine Klasse „h2“, bleibt alles, wie es ist
        if (!preg_match('~<h2(\s[^>]*)?>~i', $html, $m, PREG_OFFSET_CAPTURE, $main)) return $html;
        if (!preg_match('~\bclass="(?:[^"]*\s)?h2(?:\s[^"]*)?"~i', (string) ($m[1][0] ?? ''))) return $html;
        $open = (int) $m[0][1];
        $close = stripos($html, '</h2>', $open);
        if ($close === false) return $html;
        $inner = substr($html, $open + strlen($m[0][0]), $close - $open - strlen($m[0][0]));
        if (stripos($inner, '<h2') !== false) return $html;   // unvollständiges Markup – nichts anfassen
        return substr($html, 0, $open) . '<h1' . ($m[1][0] ?? '') . '>' . $inner . '</h1>' . substr($html, $close + 5);
    }
}
