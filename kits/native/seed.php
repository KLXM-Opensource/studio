<?php
/*
 * Startinhalte des Kits „NativeCSS“ – die von fluid (kits/fluid/seed.php: Seiten, Einstellungen, Showcase), angepasst:
 *   - Startseite mit Einblend-Effekten je Abschnitt, Zeitleiste und Kundenstimmen in der Darstellung von NativeCSS
 *   - zusätzlich die Seite „Bausteine“ (tools/demo-content.php): jede Funktion des Kits mit Beispielinhalten und freien Medien
 *     (Kurzfilm und Schleifen aus der Demo von NativeCSS, MIT; Foto von Curtis Adams, Pexels-Lizenz – siehe demo/media/README.md)
 * Alle Inhalte sind frei erfunden und als Demo gekennzeichnet. Entfernen: php kits/native/tools/demo.php --remove (nur „Bausteine“).
 */

$seed = require \Core\Kit::dir('fluid') . '/seed.php';

$seed['settings']['footer_text'] = 'Demo-Inhalte des Kits „NativeCSS“: Namen, Zahlen und Zitate sind frei erfunden und dienen nur als Beispiel.';
$seed['settings']['default_meta_description'] = 'Demo-Website des fiktiven Studio Beispiel im Kit „NativeCSS“: native Elemente, Cascade Layers, Einblend-Effekte und Morphing.';

// Startseite: Einblend-Effekte (Abschnitt → „Einblend-Effekt“), Zeitleiste und Kundenstimmen von NativeCSS, Link zu „Bausteine“
$reveal = ['features' => 'rise', 'bento' => 'fade', 'stats' => 'rise', 'steps' => 'curtain', 'quote' => 'fade'];
foreach ($seed['pages'] as &$page) {
    if (empty($page['is_home'])) continue;
    foreach ($page['blocks'] as &$block) {
        $type = (string) ($block['type'] ?? '');
        if (isset($reveal[$type])) $block['tunes'] = ['reveal' => $reveal[$type]] + (array) ($block['tunes'] ?? []);
        if ($type === 'hero') {
            $block['data']['button2_label'] = 'Bausteine ansehen';
            $block['data']['button2_link'] = '/bausteine';
        }
        if ($type === 'steps') $block['data']['variant'] = 'timeline';
        if ($type === 'quote') $block['data']['variant'] = 'grid';
    }
    unset($block);
}
unset($page);

// Nach den Seiten und dem Showcase von fluid: Seite „Bausteine“ mit allen Funktionen dieses Kits
$fluidAfter = $seed['after'] ?? null;
$seed['after'] = function ($app = null) use ($fluidAfter) {
    if (is_callable($fluidAfter)) $fluidAfter($app);
    require_once __DIR__ . '/tools/demo-content.php';
    native_demo_install();
};

return $seed;
