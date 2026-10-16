<?php
/**
 * Demo „Studio Nordlicht (fiktiv)“ – alle Blöcke, Varianten und Funktionen des Kits „modern“ – in eine bestehende Website
 * einspielen. Dort entsteht alles unterhalb von /nordlicht; eine vorhandene Startseite bleibt unberührt.
 *
 *   CMS_SITE=demo php kits/modern/tools/demo.php            anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=demo php kits/modern/tools/demo.php --force    vorhandene Demo ersetzen
 *   CMS_SITE=demo php kits/modern/tools/demo.php --remove   Demo-Seiten, Demo-Tabellen (nordlicht_*) und Demo-Bilder entfernen
 *   … --preset=kobalt                                         zusätzlich eine Design-Vorlage übernehmen
 *   CMS_SITE=demo php kits/modern/tools/demo.php --heroes   nur die Musterseite „Hero-Varianten“ (neu) anlegen
 *
 * Neue Websites erhalten die Demo automatisch (seed.php → 'after').
 * Ablauf und Schalter: Core\Kit\Demo::cli().
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';

exit(\Core\Kit\Demo::cli($argv, [
    'kit' => 'modern',
    'actions' => [
        '--heroes' => fn(callable $log) => modern_demo_heroes($log),   // nur die Musterseite „Hero-Varianten“ (neu) anlegen
        '--remove' => fn(callable $log) => modern_demo_remove($log),
    ],
    'install' => fn(bool $force, callable $log) => modern_demo_install($force, $log),
]));
