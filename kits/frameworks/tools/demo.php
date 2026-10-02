<?php
// SPDX-License-Identifier: MIT
/**
 * Demo-Inhalte des Kits „frameworks“ in eine bestehende Website einspielen (neue Websites bekommen sie über seed.php).
 *
 *   CMS_SITE=<website> php kits/frameworks/tools/demo.php            anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=<website> php kits/frameworks/tools/demo.php --force    Demo-Seiten, -Tabellen, -Bilder und -Begriffe ersetzen
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';
if (app()->theme->name !== 'frameworks') {
    fwrite(STDERR, 'Die Website nutzt nicht das Kit „frameworks“ (aktiv: ' . app()->theme->name . ").\n");
    exit(1);
}
exit(frameworks_demo_install(in_array('--force', array_slice($argv, 1), true), fn(string $m) => print($m . "\n")) ? 0 : 1);
