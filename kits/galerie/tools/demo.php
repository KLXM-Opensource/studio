<?php
/**
 * Galerie-Demo (Künstler, Ausstellungen, Werke, Anfragen, Platzhalter-Bilder, Seiten, Detailseiten) in eine bestehende
 * Website mit dem Kit „galerie“ einspielen.
 *
 *   CMS_SITE=demo php kits/galerie/tools/demo.php                   anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=demo php kits/galerie/tools/demo.php --force           vorhandene Demo ersetzen
 *   CMS_SITE=demo php kits/galerie/tools/demo.php --data-only       nur Tabellen, Bilder, Detailseiten (keine Seiten)
 *   CMS_SITE=demo php kits/galerie/tools/demo.php --remove          Demo-Tabellen, Detailseiten, Demo-Seiten und Platzhalter-Bilder entfernen
 *   … --preset=salon                                               zusätzlich eine Design-Vorlage übernehmen
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
    'kit' => 'galerie',
    'actions' => [
        '--remove' => fn(callable $log) => galerie_demo_remove($log, true),
    ],
    'install' => fn(bool $force, callable $log, array $args) => galerie_demo_install($force, $log, !in_array('--data-only', $args, true)),
]));
