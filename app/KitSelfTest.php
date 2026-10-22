<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * php bin/console kits:selftest – Vererbung von Kits (theme.php → 'extends'): Zusammenführen der Definition (Theme::mergeDefinition),
 * Standardwerte des geerbten Designs (design_defaults), Kette eines Kits ohne Eltern.
 */
final class KitSelfTest
{
    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails);
        $parent = [
            'label' => 'Eltern', 'container_class' => 'wrap',
            'blocks' => ['hero' => ['label' => 'Einstieg'], 'quote' => ['label' => 'Zitat']],
            'conditional_css' => ['css/a.css' => ['hero'], 'css/b.css' => ['quote']],
            'design' => ['groups' => [['id' => 'farben', 'tokens' => [['name' => 'accent', 'type' => 'color', 'default' => '#111111', 'dark' => '#EEEEEE']]]]],
        ];
        $child = [
            'label' => 'Kind', 'extends' => 'eltern',
            'blocks' => ['quote' => null, 'cta' => ['label' => 'Aufruf'], 'hero' => ['label' => 'Bühne']],
            'conditional_css' => ['css/b.css' => null, 'css/c.css' => ['cta']],
            'design_defaults' => ['accent' => ['default' => '#B45309', 'dark' => '#FBBF24', 'var' => '--boese']],
        ];
        $m = Theme::mergeDefinition($parent, $child);
        $eq('Kind ersetzt einfache Werte, erbt den Rest', [$m['label'], $m['container_class'], $m['extends']], ['Kind', 'wrap', 'eltern']);
        $eq('Blöcke je Typ: ersetzt, neu, mit null entfernt', [array_keys($m['blocks']), $m['blocks']['hero']['label']], [['hero', 'cta'], 'Bühne']);
        $eq('conditional_css je Datei zusammengeführt', array_keys($m['conditional_css']), ['css/a.css', 'css/c.css']);
        $tok = $m['design']['groups'][0]['tokens'][0];
        $eq('design_defaults setzt hell und dunkel, sonst nichts', [$tok['default'], $tok['dark'], $tok['var'] ?? null], ['#B45309', '#FBBF24', null]);
        $eq('design_defaults: Design-Datei immer ausgeben, Schlüssel entfernt', [!empty($m['design']['emit_defaults']), isset($m['design_defaults'])], [true, false]);
        $s = Theme::mergeDefinition($parent, ['design_defaults' => ['accent' => '#222222']]);
        $eq('design_defaults als einfacher Wert', $s['design']['groups'][0]['tokens'][0]['default'], '#222222');
        $eq('Kit ohne Eltern: unverändert', Theme::mergeDefinition([], $parent), $parent);
        $any = (string) array_key_first(Kit::all());
        if ($any !== '' && Kit::parent($any) === '') $eq('Kette eines Kits ohne Eltern: nur das Kit', array_keys(Kit::chain($any)), [$any]);
        $eq('Kette eines unbekannten Kits: leer', Kit::chain('gibt-es-nicht'), []);
        return ['ok' => $ok, 'fails' => $fails];
    }
}
