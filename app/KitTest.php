<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * php bin/console kit:test [--kit=name] [--types=hero,tabs] [--bg=accent,dark] [--site=key] – Kit im Browser prüfen.
 *
 * Testseite: je Blocktyp, der auf veröffentlichten Seiten der Website vorkommt, eine echte Instanz als Beispiel – gerendert in
 * jeder Variante des Kits und auf jedem Hintergrund des Kits (theme.php → backgrounds) als eigener Abschnitt
 * (id b-kt-{typ}-{variante}-{hintergrund}). tools/kit-test.mjs öffnet sie hell und dunkel, auf Desktop und Telefon, und meldet
 * zu schwache Kontraste (Text unter 4,5:1, große Schrift unter 3:1), Überläufe in die Breite und Fehler in der Konsole –
 * mit Bildern der betroffenen Abschnitte und einem Bericht (storage/kit-test/{kit}/report.html).
 * Prüft nur das gerenderte Ergebnis: funktioniert mit jedem CSS (eigenes, Tailwind, UIkit, Bootstrap).
 */
final class KitTest
{
    /** Typen, die als Abschnitt nichts Eigenes zeigen oder einen Eintrag brauchen */
    private const SKIP = ['entry_nav', 'entry_blocks', 'data_fields', 'not_found'];

    /**
     * Beispiel-Blöcke für das Kit: ['blocks' => [...], 'untested' => [Typen des Kits ohne Beispiel auf der Website]]
     * $o: types (nur diese), bg (nur diese Hintergründe)
     */
    public static function blocks(Theme $kit, array $o = []): array
    {
        $samples = [];
        foreach (Pages::published() as $p) {
            foreach (Pages::blocks($p) as $b) {
                $t = (string) ($b['type'] ?? '');
                if ($t === '' || isset($samples[$t]) || in_array($t, self::SKIP, true) || !$kit->block($t)) continue;
                $samples[$t] = $b;
            }
        }
        $only = array_filter((array) ($o['types'] ?? []));
        if ($only) $samples = array_intersect_key($samples, array_flip($only));
        $bgs = array_keys($kit->backgrounds());
        if ($want = array_filter((array) ($o['bg'] ?? []))) $bgs = array_values(array_intersect($bgs, $want));
        $out = [];
        foreach ($samples as $t => $b) {
            $variants = array_keys((array) ($kit->block($t)['variants'] ?? [])) ?: [(string) ($b['data']['variant'] ?? '')];
            foreach ($variants as $v) {
                foreach ($bgs as $bg) {
                    $x = $b;
                    $x['id'] = 'kt-' . self::slug($t) . ($v !== '' ? '-' . self::slug($v) : '') . '-' . self::slug($bg);
                    if ($v !== '') $x['data']['variant'] = $v;
                    $x['tunes']['section'] = array_replace((array) ($x['tunes']['section'] ?? []), ['background' => $bg, 'anchor' => '', 'visible' => true, 'bgImage' => null, 'overlay' => 'none']);
                    $out[] = $x;
                }
            }
        }
        $untested = array_values(array_diff(array_keys((array) ($kit->def['blocks'] ?? [])), array_keys($samples), self::SKIP));
        return ['blocks' => $out, 'untested' => $only ? [] : $untested];
    }

    /** HTML der Testseite (Layout des Kits, ohne Seiten-Cache, noindex) */
    public static function page(array $blocks): string
    {
        $page = Pages::home() ?? (Pages::published()[0] ?? null);
        if (!$page) throw new \RuntimeException('Die Website hat keine veröffentlichte Seite.');
        $page['content_published'] = json_encode(['blocks' => $blocks]);
        return (new Http\Controllers\SiteController())->previewHtml($page);
    }

    private static function slug(string $s): string
    {
        return trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower($s)), '-') ?: 'x';
    }
}
