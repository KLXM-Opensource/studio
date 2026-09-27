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
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';

$args = array_slice($argv, 1);
$log = function (string $m): void { echo $m, "\n"; };
if (app()->theme->name !== 'modern' && !function_exists(app()->theme->name . '_demo_install')) {
    fwrite(STDERR, "Die Website nutzt nicht das Kit „modern“ (aktiv: " . app()->theme->name . ").\n");
    exit(1);
}
if (in_array('--heroes', $args, true)) {   // nur die Musterseite „Hero-Varianten“ (neu) anlegen
    exit(modern_demo_heroes($log) ? 0 : 1);
}
if (in_array('--remove', $args, true)) {
    modern_demo_remove($log);
    \Core\PageCache::clear();
    exit(0);
}
modern_demo_install(in_array('--force', $args, true), $log);
foreach ($args as $a) {
    if (str_starts_with($a, '--preset=')) {
        $key = substr($a, 9);
        $preset = \Core\Design::def()['presets'][$key] ?? null;
        if (!$preset) { fwrite(STDERR, "Vorlage „{$key}“ gibt es nicht. Verfügbar: " . implode(', ', array_keys(\Core\Design::def()['presets'])) . "\n"); exit(1); }
        \Core\Design::save($preset['values'], 'Vorlage ' . $preset['label'] . ' (tools/demo.php)', 'cli');
        $log('Design-Vorlage „' . $preset['label'] . '“ übernommen.');
    }
}
