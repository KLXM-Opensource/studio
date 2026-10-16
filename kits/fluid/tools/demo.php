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
 * Ablauf und Schalter: Core\Kit\Demo::cli().
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';
require_once __DIR__ . '/demo-network.php';

exit(\Core\Kit\Demo::cli($argv, [
    'kit' => 'fluid',
    'actions' => [
        '--remove' => fn(callable $log) => fluid_demo_remove($log),
        '--heroes' => fn(callable $log) => fluid_demo_heroes($log),   // nur die Musterseite „Hero-Varianten“ (neu) anlegen
        '--network-remove' => fn(callable $log) => fluid_network_remove($log),   // Demo-Set „Netzwerk & Bildung“ entfernen
    ],
    // --network: nur das Demo-Set „Netzwerk & Bildung“ (Showcase bleibt, wie er ist)
    'install' => fn(bool $force, callable $log, array $args) => in_array('--network', $args, true)
        ? (fluid_network_install($log) ? null : 1) : fluid_demo_install($force, $log),
]));
