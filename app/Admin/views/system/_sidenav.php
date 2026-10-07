<?php
/**
 * Seitenleiste der Grundeinstellungen auf deren Unterseiten mit eigener Adresse (Schriften, Kits): dieselben Bereiche wie die
 * Reiter von /admin/system (Links auf /admin/system#bereich) plus die Unterseiten; schmal ein Auswahlfeld (admin.js, data-secnav).
 * @var string $current  fonts | kits
 */
$ico = ['website' => 'gear-six', 'index' => 'tree-structure', 'app' => 'device-mobile', 'proxy' => 'map-trifold', 'sprachen' => 'translate',
    'mail' => 'envelope-simple', 'spam' => 'shield-check', 'suche' => 'magnifying-glass', 'ki' => 'sparkle', 'keys' => 'lock-key',
    'pools' => 'images', 'shared' => 'share-network', 'umgebung' => 'hard-drives', 'adminpath' => 'link', 'info' => 'info'];
$items = [];
foreach (\Core\SystemSchema::groups() as $g) $items[] = [url('/admin/system#' . $g['id']), $g['label'], $ico[$g['id']] ?? 'gear-six', ''];
$items[] = [url('/admin/system#keys'), 'Verschlüsselung', 'lock-key', ''];
$items[] = [url('/admin/system#pools'), __('Geteilte Medien'), 'images', ''];
if (\Core\Data\Shared::canManage() || \Core\Data\Shared::forSite()) $items[] = [url('/admin/system#shared'), __('Geteilte Daten'), 'share-network', ''];
$items[] = [url('/admin/system#umgebung'), __('Umgebung'), 'hard-drives', environment() !== 'production' ? __('Testumgebung aktiv') : ''];
$items[] = [url('/admin/system#adminpath'), __('Adresse der Verwaltung'), 'link', ''];
$items[] = [url('/admin/system#info'), 'Systeminfo', 'info', ''];
$sub = [];
if (\Core\Fonts::canManage()) $sub['fonts'] = [url('/admin/system/fonts'), __('Schriften'), 'text-t', ''];
if (\Core\KitPackages::canManage()) $sub['kits'] = [url('/admin/system/kits'), __('Kits'), 'package', ''];
?>
  <nav class="adm-tabs adm-secnav" aria-label="<?= e(__('Bereiche')) ?>">
    <?php foreach ($items as [$href, $label, $i, $warn]): ?>
    <a href="<?= e($href) ?>"<?= $warn !== '' ? ' class="is-warn" title="' . e($warn) . '"' : '' ?>><span class="adm-tabs__ico" aria-hidden="true"><?= icon($i) ?></span><span class="adm-tabs__label"><?= e($label) ?></span><?= $warn !== '' ? '<span class="adm-tabs__dot" aria-hidden="true"></span><span class="adm-sr"> – ' . e($warn) . '</span>' : '' ?></a>
    <?php endforeach; ?>
    <?php if ($sub): ?><span class="adm-tabs__sep" role="separator"></span><?php endif; ?>
    <?php foreach ($sub as $k => [$href, $label, $i]): ?>
    <a href="<?= e($href) ?>"<?= $current === $k ? ' aria-current="page"' : '' ?>><span class="adm-tabs__ico" aria-hidden="true"><?= icon($i) ?></span><span class="adm-tabs__label"><?= e($label) ?></span></a>
    <?php endforeach; ?>
  </nav>
