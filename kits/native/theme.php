<?php
/*
 * Kit „NativeCSS“ – Kind-Kit von fluid (Vererbung über 'extends', Core\Kit::chain).
 *
 * Eigener Seitenrahmen (templates/layout.php: Kopfleiste mit <dialog>-Menü, Fußbereich im Raster) und eigene Blöcke mit den
 * Komponenten von NativeCSS (https://github.com/skerbis/nativecss, MIT, © Thomas Skerbis / KLXM Crossmedia): Einstieg, Karten,
 * Text + Bild (mit „Morphing beim Scrollen“), Handlungsaufruf, Fragen & Antworten, Kennzahlen (hochzählen), Logos (Laufband),
 * Scrollytelling, Zeitleiste, Kundenstimmen, Pakete, Video (Player, Morphing) und Vorher/Nachher. Alle übrigen Blöcke und
 * Varianten kommen von fluid und übernehmen über die Token-Brücke (assets/css/native.css) Farben, Schrift, Radien und Schatten.
 *
 * NativeCSS liegt unverändert unter vendor/ncss/ – nur die verwendeten Dateien (Stand: vendor/ncss/VERSION.txt, aktualisieren mit
 * php kits/native/tools/ncss-update.php). Geladen wird nur, was die Seite braucht: der Rahmen immer (css/site.css), jede Komponente
 * über conditional_css je Block, Variante oder Merkmal (Theme::markUsed, z. B. 'stats:@count' beim Hochzählen).
 *
 * Cascade Layers (css/site.css): tokens < reset < base (NativeCSS) < fluid (Grund-CSS von fluid) < helpers < components
 * (NativeCSS) < native (Brücke, Rahmen, Blöcke dieses Kits). CSS ohne Layer – Design-Werte, CSS je Block von fluid,
 * Design-Optionen – liegt darüber.
 */

require_once __DIR__ . '/functions.php';

// ------------------------------------------------------------------ Design: das von fluid, angepasst
// Standardwerte im Stil von NativeCSS: tiefes Blau, Navy, blaugetönte Flächen, Systemschrift (kein Download).
// Hell und dunkel nach WCAG 2.2 AA geprüft (Akzent/Grund 8,9:1, Schrift/Grund 15:1, Nebentext/Fläche 6:1).
$colorKeys = ['accent', 'accent_strong', 'on_accent', 'highlight', 'ink', 'text', 'muted', 'background', 'surface', 'line', 'dark_section'];
$light = ['#154A86', '#0F3766', '#FFFFFF', '#DCEBFF', '#0D1726', '#1F2633', '#535B68', '#FFFFFF', '#EAF3FF', '#D7E3F3', '#0B1C33'];
$dark = ['#8DB6EE', '#B5D0F6', '#0A1628', '#1E3A5F', '#EEF2F8', '#D3DAE5', '#A3AFC0', '#0B1626', '#13233A', '#2A3B57', '#132B4A'];
$defaults = array_combine($colorKeys, $light) + [
    'secondary' => '#314164', 'on_secondary' => '#FFFFFF',
    'font_body' => 'system', 'font_head' => 'system', 'heading_weight' => 700, 'heading_tracking' => -1,
    'radius' => 12, 'buttons' => 'solid', 'cards' => 'outlined', 'shadow' => 'soft', 'eyebrow' => 'plain',
    // Kopfleiste ruhig: nur die Lupe (öffnet das Suchfeld), kein Handlungsaufruf – beides im Design änderbar
    'ha_search' => 'popover', 'ha_cta_style' => 'none',
];
$darkDefaults = array_combine($colorKeys, $dark) + ['secondary' => '#9DB0D8', 'on_secondary' => '#0A1628'];

$design = require \Core\Kit::dir('fluid') . '/design.php';
// „Kopf & Fuß“ gehört zum Seitenrahmen von fluid – der Rahmen dieses Kits kommt aus NativeCSS. Es bleiben die Seiten-Optionen
// (Seitenhintergrund, Abschnitte, Lesefortschritt, „Nach oben“, Seitenwechsel, Zurück-Link) als Gruppe „Seite“.
$keep = ['pagebg', 'sections', 'progress', 'totop', 'transitions', 'back_nav'];
foreach ($design['groups'] as &$group) {
    if (($group['id'] ?? '') === 'navigation') {
        $group['label'] = 'Seite';
        $group['tokens'] = array_values(array_filter($group['tokens'], static fn(array $t) => in_array($t['name'] ?? '', $keep, true)));
    }
    foreach ($group['tokens'] as &$token) {
        $name = (string) ($token['name'] ?? '');
        if (array_key_exists($name, $defaults)) $token['default'] = $defaults[$name];
        if (array_key_exists($name, $darkDefaults) && ($token['type'] ?? '') === 'color') $token['dark'] = $darkDefaults[$name];
    }
    unset($token);
    // Darstellungs-Knopf für Besucher: Schalter „visitor_prefs“ kommt von fluid (fluid_prefs()); dieses Kit bietet zusätzlich
    // Lese-Schriften an (components/preferences-fonts.css von NativeCSS) – direkt hinter dem Schalter von fluid
    if (($group['id'] ?? '') === 'modus') {
        $at = array_search('visitor_prefs', array_column($group['tokens'], 'name'), true);
        array_splice($group['tokens'], $at === false ? count($group['tokens']) : (int) $at + 1, 0, [[
            'name' => 'visitor_prefs_fonts', 'markup' => true, 'label' => 'Lese-Schriften im Darstellungs-Knopf anbieten', 'type' => 'bool', 'default' => true,
            'help' => 'Nur mit „Darstellungs-Knopf für Besucher“: Besucher wählen zusätzlich Atkinson Hyperlegible, Lexend oder OpenDyslexic (SIL Open Font License). Die Schriften laden erst, wenn jemand sie wählt.']]);
    }
}
unset($group);
$values = static function (array $l, array $d, array $rest) use ($colorKeys): array {
    $v = array_combine($colorKeys, $l);
    foreach ($colorKeys as $i => $k) $v[$k . '@dark'] = $d[$i];
    return $v + $rest;
};
$design['presets'] = [
    'native' => ['label' => 'NativeCSS', 'description' => 'Tiefes Blau, Navy, blaugetönte Flächen und die Systemschrift des Geräts – wie die NativeCSS-Website.',
        'values' => $values($light, $dark, ['secondary' => '#314164', 'on_secondary' => '#FFFFFF', 'secondary@dark' => '#9DB0D8', 'on_secondary@dark' => '#0A1628',
            'font_body' => 'system', 'font_head' => 'system', 'heading_weight' => 700, 'heading_tracking' => -1, 'radius' => 12, 'eyebrow' => 'plain'])],
    'native-graphite' => ['label' => 'NativeCSS · Graphit', 'description' => 'Gedämpftes Grau als Marke, Navy als Zweitfarbe, Inter – sachlich und ruhig.',
        'values' => $values(
            ['#4A4A49', '#2F2F2E', '#FFFFFF', '#E7ECF3', '#111111', '#1F1F1F', '#55595F', '#FFFFFF', '#F2F4F7', '#DDE2E8', '#1B2233'],
            ['#C9C9C8', '#E2E2E1', '#111111', '#2E3440', '#F2F3F5', '#D6D8DC', '#A7ABB2', '#121417', '#1B1E23', '#2C3038', '#20283A'],
            ['secondary' => '#314164', 'on_secondary' => '#FFFFFF', 'secondary@dark' => '#9DB0D8', 'on_secondary@dark' => '#0A1628',
                'font_body' => 'inter', 'font_head' => 'inter', 'heading_weight' => 650, 'heading_tracking' => -2, 'radius' => 8, 'eyebrow' => 'plain'])],
] + $design['presets'];
// Standardwerte weichen vom CSS des Eltern-Kits ab → Design-Datei immer ausgeben (Core\Design::head)
$design['emit_defaults'] = true;

// Morphing beim Scrollen: zusätzliche Darstellung nur in diesem Kit (im Kit fluid erscheint der Block dann wie „Bild rechts“
// bzw. „Mit Text daneben“)
$morphHelp = 'Das Bild startet groß und rückt beim Scrollen zur Seite, der Text erscheint daneben. Bei „Bewegung reduzieren“ stehen Bild und Text einfach nebeneinander. Beim Bearbeiten wie „Bild rechts“.';

return [
    'extends' => 'fluid',
    'label' => 'NativeCSS',
    'description' => 'Kopfleiste, Menü und Blöcke mit NativeCSS: native Elemente (dialog, details), Cascade Layers, Einblend-Effekte, Morphing, Video-Player – alles Weitere wie Fluid.',
    'description_en' => 'Top bar, menu and blocks built with NativeCSS: native elements (dialog, details), cascade layers, reveal effects, morphing, video player – everything else as in Fluid.',
    'category' => 'general',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',

    'design' => $design,

    // Einblend-Effekte für Abschnitte (Abschnitt → „Einblend-Effekt“, Standard „Keiner“) – .ncss-reveal von NativeCSS, einmal beim
    // Hereinscrollen; „Bewegung reduzieren“ zeigt den Abschnitt sofort (CSS von NativeCSS), „Dezente Animationen“ aus im Design
    // schaltet alle ab (Block::revealOption). Auf Seiten mit Effekt: css/ncss-reveal.css und js/ncss-reveal.js ('@reveal').
    'reveal' => [
        'fade' => ['label' => 'Sanft einblenden', 'class' => 'ncss-reveal ncss-reveal--fade ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
        'rise' => ['label' => 'Von unten', 'class' => 'ncss-reveal ncss-reveal--slide-up ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
        'circle' => ['label' => 'Kreis', 'class' => 'ncss-reveal ncss-reveal--circle ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
        'curtain' => ['label' => 'Vorhang', 'class' => 'ncss-reveal ncss-reveal--split ncss-reveal--once', 'attr' => 'data-ncss-reveal'],
    ],

    'block_extend' => [
        'media_text' => [
            'variants' => ['morph' => 'Morphing beim Scrollen'],
            'variant_help' => ['morph' => $morphHelp],
        ],
        'video' => [
            'variants' => ['morph' => 'Video mit Morphing beim Scrollen'],
            'variant_help' => ['morph' => 'Eigene Videodatei, stumm in Schleife (wie ein Hintergrundvideo, mit Pause-Schalter): startet groß und rückt beim Scrollen zur Seite, der Text daneben erscheint. Bei „Bewegung reduzieren“ nur das Standbild neben dem Text. Beim Bearbeiten wie „Mit Text daneben“.'],
        ],
    ],

    // Stylesheets und Skripte je Block, Variante oder Merkmal. Merkmale (@…) melden die Blöcke dieses Kits beim Rendern
    // (Theme::markUsed): '@nv-hero' = Einstieg in der Darstellung von NativeCSS, '@fluid-hero' = Variante von fluid (z. B. Collage).
    // So lädt eine Seite nur das CSS der Darstellung, die sie wirklich zeigt. Beim Bearbeiten lädt der Editor alle Stylesheets.
    'conditional_css' => [
        // Blöcke und Varianten von fluid, die hier eine eigene Darstellung haben: CSS von fluid nur für dessen Ausgabe
        'css/hero-x.css' => ['@fluid-hero'],
        'css/hx-compare.css' => ['hero:compare'],   // Block „Vorher/Nachher“: eigene Darstellung (ncss-compare), Einstieg „Vorher/Nachher“ von fluid
        'css/b-media-text.css' => ['@fluid-media_text'],
        'css/b-cta.css' => ['@fluid-cta'],
        'css/b-faq.css' => null,
        'css/b-cards.css' => ['@fluid-cards'],
        'css/reel.css' => ['cards:reel', 'quote:reel'],
        'js/reel.js' => ['cards:reel', 'quote:reel'],
        'css/b-quote.css' => ['@fluid-quote'],
        'css/b-steps.css' => ['@fluid-steps'],
        'css/b-pricing.css' => ['@fluid-pricing'],
        'css/b-pricelist.css' => null, 'css/b-audio.css' => null,   // Preisliste und Audio: eigene Darstellung (ncss-leaders, ncss-audio-player)
        'css/b-scrolly.css' => ['@fluid-scrolly'],
        'js/scrolly.js' => ['@fluid-scrolly'],
        // Darstellungen dieses Kits
        'css/nv-hero.css' => ['@nv-hero'],
        'css/nv-cards.css' => ['@nv-cards'],
        'css/nv-media-text.css' => ['@nv-media_text'],
        'css/nv-cta.css' => ['@nv-cta'],
        'css/nv-faq.css' => ['@nv-faq'],
        // Komponenten von NativeCSS (Hüllen mit layer(…) in assets/css bzw. assets/js, Dateien in vendor/ncss)
        'css/ncss-reveal.css' => ['@reveal'], 'js/ncss-reveal.js' => ['@reveal'],
        'css/ncss-morph.css' => ['@morph'],
        'css/ncss-count-up.css' => ['stats:@count'], 'js/ncss-count-up.js' => ['stats:@count'],
        'css/ncss-marquee.css' => ['logos:@marquee'], 'js/ncss-marquee.js' => ['logos:@marquee'],
        'css/ncss-scrolly.css' => ['@nv-scrolly'], 'js/ncss-scrolly.js' => ['@nv-scrolly'],
        'css/ncss-compare.css' => ['@nv-compare'], 'js/ncss-compare.js' => ['@nv-compare'],
        // Gemeinsamer Kern beider Player (NativeCSS ab 1.4.0) – vor den Player-Skripten, damit sie ihn nicht nachladen müssen
        'js/ncss-media-core.js' => ['video:@player', 'video:@audio'],
        'css/ncss-video-player.css' => ['video:@player'], 'js/ncss-video-player.js' => ['video:@player'],
        'css/ncss-audio-player.css' => ['video:@audio'], 'js/ncss-audio-player.js' => ['video:@audio'],
        'css/ncss-video-bg.css' => ['@videobg'], 'js/ncss-video-bg.js' => ['@videobg'],
        'css/ncss-timeline.css' => ['steps:@timeline'],
        'css/ncss-testimonial.css' => ['quote:@testimonial'],
        'css/ncss-pricing.css' => ['pricing:@plans'],
        'css/ncss-leaders.css' => ['pricing:@leaders'],
        // „Filter nach Kategorie“ (Karten): css/filter.css und js/filter.js dieses Kits (Komponente filter von NativeCSS) ersetzen die
        // gleichnamigen Dateien von fluid – Merkmal '@filter' aus fluid_filter(), nichts doppelt.
        // Sortierbare Tabellen ('@tablesort'): Skript und Stylesheet des Kerns (js/table-sort.js, css/table-sort.css) – kein eigenes.
    ],
];
