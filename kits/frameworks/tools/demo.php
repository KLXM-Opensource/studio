<?php
// SPDX-License-Identifier: MIT
/**
 * Demo-Inhalte des Kits „frameworks“ in eine bestehende Website einspielen (neue Websites bekommen sie über seed.php).
 *
 *   CMS_SITE=<website> php kits/frameworks/tools/demo.php            anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=<website> php kits/frameworks/tools/demo.php --force    Demo-Seiten, -Tabellen, -Bilder und -Begriffe ersetzen
 * Ablauf und Schalter: Core\Kit\Demo::cli().
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';
exit(\Core\Kit\Demo::cli($argv, [
    'kit' => 'frameworks',
    'accept' => fn(string $active) => $active === 'frameworks',
    'install' => fn(bool $force, callable $log) => frameworks_demo_install($force, $log) ? 0 : 1,
]));
