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
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';

$args = array_slice($argv, 1);
$log = function (string $m): void { echo $m, "\n"; };
if (!function_exists(app()->theme->name . '_demo_install') && app()->theme->name !== 'galerie') {
    fwrite(STDERR, "Die Website nutzt nicht das Kit „galerie“ (aktiv: " . app()->theme->name . ").\n");
    exit(1);
}
if (in_array('--remove', $args, true)) {
    galerie_demo_remove($log, true);
    \Core\PageCache::clear();
    exit(0);
}
galerie_demo_install(in_array('--force', $args, true), $log, !in_array('--data-only', $args, true));
foreach ($args as $a) {
    if (str_starts_with($a, '--preset=')) {
        $key = substr($a, 9);
        $preset = \Core\Design::def()['presets'][$key] ?? null;
        if (!$preset) { fwrite(STDERR, "Vorlage „{$key}“ gibt es nicht. Verfügbar: " . implode(', ', array_keys(\Core\Design::def()['presets'])) . "\n"); exit(1); }
        \Core\Design::save($preset['values'], 'Vorlage ' . $preset['label'] . ' (tools/demo.php)', 'cli');
        $log('Design-Vorlage „' . $preset['label'] . '“ übernommen.');
    }
}
