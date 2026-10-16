<?php
/**
 * Musterseiten (Showcase aller Blöcke und Funktionen) in eine bestehende Website mit Theme „basis“ einspielen.
 *
 *   CMS_SITE=demo php kits/basis/tools/demo.php            anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=demo php kits/basis/tools/demo.php --force    vorhandene Musterseiten ersetzen
 *   CMS_SITE=demo php kits/basis/tools/demo.php --remove   Musterseiten, Demo-Tabellen (demo_*) und Demo-Bilder entfernen
 *   … --preset=mint                                           zusätzlich eine Design-Voreinstellung übernehmen
 *   CMS_SITE=demo php kits/basis/tools/demo.php --heroes   nur die Musterseite „Hero-Varianten“ (neu) anlegen
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
    'accept' => fn() => function_exists('basis_demo_pages') && !empty(\Core\Design::def()['presets']),
    'reject' => "Die Website nutzt kein Kit mit Musterseiten (aktiv: %s).\n",
    'actions' => [
        '--heroes' => fn(callable $log) => basis_demo_heroes($log),   // nur die Musterseite „Hero-Varianten“ (neu) anlegen
        '--remove' => fn(callable $log) => basis_demo_remove($log),
    ],
    'install' => fn(bool $force, callable $log) => basis_demo_install($force, $log),
    'preset' => 'Voreinstellung',
    'missing' => "%1\$s „%2\$s“ gibt es nicht.\n",
]));
