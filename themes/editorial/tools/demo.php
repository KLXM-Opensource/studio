<?php
/**
 * Demo-Ausgabe (Nachrichten, Termine, Menschen, Magazin, Formulare) in eine bestehende Website mit Theme „editorial“ einspielen.
 *
 *   CMS_SITE=editorial php themes/editorial/tools/demo.php            anlegen (nur wenn noch nicht vorhanden)
 *   CMS_SITE=editorial php themes/editorial/tools/demo.php --force    vorhandene Demo ersetzen
 *   CMS_SITE=editorial php themes/editorial/tools/demo.php --remove   Demo-Seiten, Tabellen ed_* und Demo-Bilder entfernen
 *   … --preset=kulturhaus                                             zusätzlich eine Design-Voreinstellung übernehmen
 *   CMS_SITE=editorial php themes/editorial/tools/demo.php --heroes   nur die Musterseite „Hero-Varianten“ (neu) anlegen
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
if (app()->theme->name !== basename(dirname(__DIR__))) {
    fwrite(STDERR, "Die Website nutzt nicht das Kit „editorial“ (aktiv: " . app()->theme->name . ").\n");
    exit(1);
}
if (in_array('--heroes', $args, true)) {   // nur die Musterseite „Hero-Varianten“ (neu) anlegen
    exit(editorial_demo_heroes($log) ? 0 : 1);
}
if (in_array('--remove', $args, true)) {
    editorial_demo_remove($log);
    \Core\PageCache::clear();
    exit(0);
}
$presetOnly = (bool) array_filter($args, fn($a) => str_starts_with($a, '--preset=')) && !in_array('--force', $args, true) && \Core\Data\Tables::find('ed_news');
if (!$presetOnly) editorial_demo_install(in_array('--force', $args, true), $log);
foreach ($args as $a) {
    if (str_starts_with($a, '--preset=')) {
        $key = substr($a, 9);
        $preset = \Core\Design::def()['presets'][$key] ?? null;
        if (!$preset) { fwrite(STDERR, "Voreinstellung „{$key}“ gibt es nicht (" . implode(', ', array_keys(\Core\Design::def()['presets'])) . ").\n"); exit(1); }
        \Core\Design::save($preset['values'], 'Voreinstellung ' . $preset['label'] . ' (tools/demo.php)', 'cli');
        $log('Design-Voreinstellung „' . $preset['label'] . '“ übernommen.');
    }
}
