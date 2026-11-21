<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Blocks;

use Core\PageAccess;
use Core\Pages;

/**
 * Kern-Block „Unterseiten“ (Renderer app/Blocks/subpages.php, Stile resources/css/subpages.css): die Kinder einer Seite als Karten,
 * Liste oder Kacheln – automatisch, ohne Pflege an zweiter Stelle.
 *
 *  - Elternseite: Feld parent (Linkauswahl, page:ID), leer = die aufgerufene Seite.
 *  - Nur veröffentlichte Seiten vom Typ „Seite“; für Besucher keine geschützten (Core\PageAccess); optional nur „im Menü“.
 *  - Reihenfolge wie im Menü (sort, id), wahlweise Titel A–Z oder neueste zuerst; Höchstzahl limit (0 = alle).
 *  - Je Seite: Titel (Menütitel, sonst Titel), Beschreibung (Meta-Beschreibung, sonst Text des Seitenkopfs), Bild (Bild des
 *    Seitenkopfs, sonst Vorschaubild der Seite og_image), Adresse.
 */
final class SubpageList
{
    /** Elternseite: page:ID aus dem Feld, sonst die aufgerufene Seite. */
    public static function parent(array $d): ?array
    {
        if (preg_match('~^page:(\d+)~', (string) ($d['parent'] ?? ''), $m)) return Pages::find((int) $m[1]);
        return app()->currentPage ?: null;
    }

    /**
     * Unterseiten für die Ausgabe.
     * @return list<array{id: int, title: string, text: string, image: int, href: string, page: array}>
     */
    public static function items(array $d, ?array $parent = null): array
    {
        $parent ??= self::parent($d);
        if (!$parent || empty($parent['id'])) return [];
        $rows = Pages::db()->fetchAll("SELECT * FROM pages WHERE parent_id = ? AND type = 'page' AND status = 'published' ORDER BY sort, id", [(int) $parent['id']]);
        if (!empty($d['only_menu'])) $rows = array_values(array_filter($rows, fn($p) => (int) $p['menu'] === 1));
        $rows = PageAccess::visible($rows);
        // Zeitsteuerung: „online ab/bis“ (Seiten außerhalb ihres Zeitraums erscheinen nicht)
        $now = now();
        $rows = array_values(array_filter($rows, fn($p) => (empty($p['online_from']) || (string) $p['online_from'] <= $now) && (empty($p['online_until']) || (string) $p['online_until'] > $now)));
        $sort = (string) ($d['sort'] ?? 'menu');
        if ($sort === 'title') usort($rows, fn($a, $b) => strnatcasecmp((string) ($a['nav_title'] ?: $a['title']), (string) ($b['nav_title'] ?: $b['title'])));
        elseif ($sort === 'newest') usort($rows, fn($a, $b) => strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? '')) ?: ((int) $b['id'] <=> (int) $a['id']));
        $limit = max(0, min(60, (int) ($d['limit'] ?? 0)));
        if ($limit) $rows = array_slice($rows, 0, $limit);
        return array_map(fn(array $p) => self::item($p), $rows);
    }

    /** Angaben einer Seite: Titel, Beschreibung, Bild, Adresse */
    public static function item(array $p): array
    {
        $hero = self::hero($p);
        $text = trim((string) ($p['meta_description'] ?? '')) ?: fmt()->excerpt((string) ($hero['text'] ?? ''), 180);
        return [
            'id' => (int) $p['id'], 'title' => (string) (($p['nav_title'] ?? '') ?: $p['title']), 'text' => $text,
            'image' => (int) ($hero['image'] ?? 0) ?: (int) ($p['og_image'] ?? 0), 'href' => Pages::url($p), 'page' => $p,
        ];
    }

    /** Daten des ersten Seitenkopfs (Block „hero“) der veröffentlichten Fassung – Bild (Mediathek-ID) und Text */
    public static function hero(array $p): array
    {
        foreach (Pages::blocks($p) as $b) {
            if (($b['type'] ?? '') !== 'hero') continue;
            $data = (array) ($b['data'] ?? []);
            $img = $data['image'] ?? null;
            return ['image' => is_numeric($img) ? (int) $img : 0, 'text' => (string) ($data['text'] ?? $data['lead'] ?? '')];
        }
        return [];
    }
}
