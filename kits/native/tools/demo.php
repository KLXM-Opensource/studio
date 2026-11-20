<?php
/**
 * Seite „Bausteine“ (alle Funktionen des Kits „NativeCSS“) in eine bestehende Website einspielen.
 *
 *   CMS_SITE=demo php kits/native/tools/demo.php            anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=demo php kits/native/tools/demo.php --force    vorhandene Seite ersetzen
 *   CMS_SITE=demo php kits/native/tools/demo.php --remove   Seite und Demo-Medien (Schlagwort „native-demo“) entfernen
 *
 * Den Showcase von fluid (alle Blöcke) legt php kits/fluid/tools/demo.php an. Neue Websites erhalten beides automatisch
 * (seed.php → 'after'). Ablauf und Schalter: Core\Kit\Demo::cli().
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';

exit(\Core\Kit\Demo::cli($argv, [
    'kit' => 'native',
    'actions' => ['--remove' => fn(callable $log) => native_demo_remove($log)],
    'install' => fn(bool $force, callable $log) => native_demo_install($force, $log),
]));
