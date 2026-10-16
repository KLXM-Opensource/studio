<?php
/**
 * Demo-Inhalte „Hofgut Wiesengrund“ (Hofladen, Termine, Gruppen-Formular, „Erleben“ mit allen Blöcken) des Kits
 * „nature“ in eine bestehende Website einspielen.
 *
 *   CMS_SITE=demo php kits/nature/tools/demo.php            anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=demo php kits/nature/tools/demo.php --force    vorhandene Demo-Inhalte ersetzen
 *   CMS_SITE=demo php kits/nature/tools/demo.php --remove   Demo-Seiten, Demo-Tabellen (hof_*) und Illustrationen entfernen
 *   … --preset=herbst                                         zusätzlich eine Design-Vorlage übernehmen
 *   CMS_SITE=demo php kits/nature/tools/demo.php --heroes   nur die Musterseite „Erleben → Hero-Varianten“ (neu) anlegen
 *
 * Neue Websites erhalten die Demo-Inhalte automatisch (seed.php → 'after').
 * Ablauf und Schalter: Core\Kit\Demo::cli().
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';

exit(\Core\Kit\Demo::cli($argv, [
    'kit' => 'nature',
    'actions' => [
        '--heroes' => fn(callable $log) => nature_demo_heroes($log),   // nur die Musterseite „Hero-Varianten“ (neu) anlegen
        '--remove' => fn(callable $log) => nature_demo_remove($log),
    ],
    'install' => fn(bool $force, callable $log) => nature_demo_install($force, $log),
]));
