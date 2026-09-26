<?php
/**
 * Baukasten (Showcase aller Blöcke und Funktionen) in eine bestehende Website mit Theme „basis“ einspielen.
 *
 *   CMS_SITE=demo php themes/basis/tools/demo.php            anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=demo php themes/basis/tools/demo.php --force    vorhandenen Baukasten ersetzen
 *   CMS_SITE=demo php themes/basis/tools/demo.php --remove   Baukasten, Demo-Tabellen (demo_*) und Demo-Bilder entfernen
 *   … --preset=mint                                           zusätzlich eine Design-Voreinstellung übernehmen
 *   CMS_SITE=demo php themes/basis/tools/demo.php --heroes   nur die Musterseite „Hero-Varianten“ (neu) anlegen
 *
 * Neue Websites erhalten den Baukasten automatisch (seed.php → 'after').
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';

$args = array_slice($argv, 1);
$log = function (string $m): void { echo $m, "\n"; };
if (!function_exists('basis_demo_pages') || empty(\Core\Design::def()['presets'])) {
    fwrite(STDERR, "Die Website nutzt kein Kit mit Baukasten (aktiv: " . app()->theme->name . ").\n");
    exit(1);
}
if (in_array('--heroes', $args, true)) {   // nur die Musterseite „Hero-Varianten“ (neu) anlegen
    exit(basis_demo_heroes($log) ? 0 : 1);
}
if (in_array('--remove', $args, true)) {
    basis_demo_remove($log);
    \Core\PageCache::clear();
    exit(0);
}
basis_demo_install(in_array('--force', $args, true), $log);
foreach ($args as $a) {
    if (str_starts_with($a, '--preset=')) {
        $key = substr($a, 9);
        $preset = \Core\Design::def()['presets'][$key] ?? null;
        if (!$preset) { fwrite(STDERR, "Voreinstellung „{$key}“ gibt es nicht.\n"); exit(1); }
        \Core\Design::save($preset['values'], 'Voreinstellung ' . $preset['label'] . ' (tools/demo.php)', 'cli');
        $log('Design-Voreinstellung „' . $preset['label'] . '“ übernommen.');
    }
}
