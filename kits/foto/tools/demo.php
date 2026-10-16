<?php
/**
 * Demo-Inhalte des Kits „foto“ (Startseite, „Arbeiten“ mit drei Serien, „Über mich“, „Kontakt“, Platzhalterbilder) in eine
 * bestehende Website einspielen bzw. entfernen.
 *
 *   CMS_SITE=fototest php kits/foto/tools/demo.php                  anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=fototest php kits/foto/tools/demo.php --force          vorhandene Demo ersetzen
 *   CMS_SITE=fototest php kits/foto/tools/demo.php --remove         Demo-Seiten und Platzhalterbilder entfernen
 *   … --preset=dunkelkammer                                          zusätzlich eine Design-Vorlage übernehmen
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
    'kit' => 'foto',
    'actions' => [
        '--remove' => fn(callable $log) => foto_demo_remove($log),
    ],
    'install' => fn(bool $force, callable $log) => foto_demo_install($force, $log),
]));
