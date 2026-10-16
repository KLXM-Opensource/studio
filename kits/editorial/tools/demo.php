<?php
/**
 * Demo-Ausgabe (Nachrichten, Termine, Menschen, Magazin, Formulare) in eine bestehende Website mit Theme „editorial“ einspielen.
 *
 *   CMS_SITE=editorial php kits/editorial/tools/demo.php            anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=editorial php kits/editorial/tools/demo.php --force    vorhandene Demo ersetzen
 *   CMS_SITE=editorial php kits/editorial/tools/demo.php --remove   Demo-Seiten, Tabellen ed_* und Demo-Bilder entfernen
 *   … --preset=kulturhaus                                             zusätzlich eine Design-Voreinstellung übernehmen
 *   CMS_SITE=editorial php kits/editorial/tools/demo.php --heroes   nur die Musterseite „Hero-Varianten“ (neu) anlegen
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
    'kit' => 'editorial',
    'accept' => fn(string $active) => $active === basename(dirname(__DIR__)),
    'actions' => [
        '--heroes' => fn(callable $log) => editorial_demo_heroes($log),   // nur die Musterseite „Hero-Varianten“ (neu) anlegen
        '--remove' => fn(callable $log) => editorial_demo_remove($log),
    ],
    // nur --preset=… auf eine vorhandene Demo: Inhalte bleiben, nur die Voreinstellung wird übernommen
    'install' => fn(bool $force, callable $log, array $args) => array_filter($args, fn($a) => str_starts_with($a, '--preset=')) && !$force && \Core\Data\Tables::find('ed_news')
        ? null : editorial_demo_install($force, $log),
    'preset' => 'Voreinstellung',
    'missing' => "%1\$s „%2\$s“ gibt es nicht (%3\$s).\n",
]));
