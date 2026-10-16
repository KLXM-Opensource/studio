<?php
/**
 * Musterseiten „Werkstatt“ (alle Blöcke, Varianten und Funktionen des Themes „essenz“) in eine bestehende Website einspielen.
 *
 *   CMS_SITE=demo php kits/essenz/tools/demo.php            anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=demo php kits/essenz/tools/demo.php --force    vorhandene Musterseiten ersetzen
 *   CMS_SITE=demo php kits/essenz/tools/demo.php --remove   Musterseiten, Demo-Tabellen (werkstatt_*) und Demo-Bilder entfernen
 *   … --preset=graphit                                        zusätzlich eine Design-Vorlage übernehmen
 *   CMS_SITE=demo php kits/essenz/tools/demo.php --heroes   nur die Musterseite „Hero-Varianten“ (neu) anlegen
 *
 * Neue Websites erhalten die Musterseiten automatisch (seed.php → 'after').
 * Ablauf und Schalter: Core\Kit\Demo::cli().
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';

exit(\Core\Kit\Demo::cli($argv, [
    'kit' => 'essenz',
    'actions' => [
        '--heroes' => fn(callable $log) => essenz_demo_heroes($log),   // nur die Musterseite „Hero-Varianten“ (neu) anlegen
        '--remove' => fn(callable $log) => essenz_demo_remove($log),
    ],
    'install' => fn(bool $force, callable $log) => essenz_demo_install($force, $log),
]));
