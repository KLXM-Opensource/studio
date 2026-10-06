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
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 3);
chdir($root);
$app = require $root . '/app/bootstrap.php';
require_once __DIR__ . '/demo-content.php';

$args = array_slice($argv, 1);
$log = function (string $m): void { echo $m, "\n"; };
if (app()->theme->name !== 'foto' && !function_exists(app()->theme->name . '_demo_install')) {
    fwrite(STDERR, "Die Website nutzt nicht das Kit „foto“ (aktiv: " . app()->theme->name . ").\n");
    exit(1);
}
if (in_array('--remove', $args, true)) {
    foto_demo_remove($log);
    \Core\PageCache::clear();
    exit(0);
}
foto_demo_install(in_array('--force', $args, true), $log);
foreach ($args as $a) {
    if (str_starts_with($a, '--preset=')) {
        $key = substr($a, 9);
        $preset = \Core\Design::def()['presets'][$key] ?? null;
        if (!$preset) { fwrite(STDERR, "Vorlage „{$key}“ gibt es nicht. Verfügbar: " . implode(', ', array_keys(\Core\Design::def()['presets'])) . "\n"); exit(1); }
        \Core\Design::save($preset['values'], 'Vorlage ' . $preset['label'] . ' (tools/demo.php)', 'cli');
        $log('Design-Vorlage „' . $preset['label'] . '“ übernommen.');
    }
}
