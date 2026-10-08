<?php
/**
 * Showcase (alle Blöcke, Varianten und Funktionen des Themes „fluid“) in eine bestehende Website einspielen.
 *
 *   CMS_SITE=demo php kits/fluid/tools/demo.php            anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=demo php kits/fluid/tools/demo.php --force    vorhandenen Showcase ersetzen
 *   CMS_SITE=demo php kits/fluid/tools/demo.php --remove   Showcase, Demo-Tabellen (showcase_*) und Demo-Bilder entfernen
 *   CMS_SITE=demo php kits/fluid/tools/demo.php --heroes   nur die Musterseite „Hero-Varianten“ (/showcase/hero-varianten) neu anlegen
 *   CMS_SITE=demo php kits/fluid/tools/demo.php --network  Demo-Set „Netzwerk & Bildung“ (Mitglieder-Verzeichnis mit Detailseiten,
 *                                                          Seitenbaum „Netzwerk“, Logo) – tools/demo-network.php; --network-remove entfernt es
 *   … --preset=agentur                                        zusätzlich eine Design-Vorlage übernehmen (z. B. --network --preset=netzwerk)
 *
 * Neue Websites erhalten den Showcase automatisch (seed.php → 'after').
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';
require_once __DIR__ . '/demo-network.php';

$args = array_slice($argv, 1);
$log = function (string $m): void { echo $m, "\n"; };
if (app()->theme->name !== 'fluid' && !function_exists(app()->theme->name . '_demo_install')) {
    fwrite(STDERR, "Die Website nutzt nicht das Kit „fluid“ (aktiv: " . app()->theme->name . ").\n");
    exit(1);
}
if (in_array('--remove', $args, true)) {
    fluid_demo_remove($log);
    \Core\PageCache::clear();
    exit(0);
}
if (in_array('--heroes', $args, true)) {   // nur die Musterseite „Hero-Varianten“ (neu) anlegen
    exit(fluid_demo_heroes($log) ? 0 : 1);
}
if (in_array('--network-remove', $args, true)) {   // Demo-Set „Netzwerk & Bildung“ entfernen
    fluid_network_remove($log);
    \Core\PageCache::clear();
    exit(0);
}
if (in_array('--network', $args, true)) {   // nur das Demo-Set „Netzwerk & Bildung“ (Showcase bleibt, wie er ist)
    if (!fluid_network_install($log)) exit(1);
} else {
    fluid_demo_install(in_array('--force', $args, true), $log);
}
foreach ($args as $a) {
    if (str_starts_with($a, '--preset=')) {
        $key = substr($a, 9);
        $preset = \Core\Design::def()['presets'][$key] ?? null;
        if (!$preset) { fwrite(STDERR, "Vorlage „{$key}“ gibt es nicht. Verfügbar: " . implode(', ', array_keys(\Core\Design::def()['presets'])) . "\n"); exit(1); }
        \Core\Design::save($preset['values'], 'Vorlage ' . $preset['label'] . ' (tools/demo.php)', 'cli');
        $log('Design-Vorlage „' . $preset['label'] . '“ übernommen.');
    }
}
