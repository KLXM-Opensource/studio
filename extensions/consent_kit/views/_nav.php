<?php
/** Kopf und Bereichsnavigation der Erweiterung „consent_kit“. @var string $tab */
$items = [
    'services' => ['/admin/consent', __('Dienste')],
    'settings' => ['/admin/consent/settings', __('Einstellungen')],
    'design' => ['/admin/consent/design', __('Design')],
    'log' => ['/admin/consent/log', __('Protokoll')],
    'io' => ['/admin/consent/io', __('Eigene Vorlagen')],
];
$current = in_array($tab, ['service', 'templates'], true) ? 'services' : ($tab === 'revision' ? 'log' : $tab);
?>
<header class="adm-head ck-head">
  <div><p class="adm-eyebrow"><?= e(__('Erweiterung')) ?> · Consent-Kit</p><h1><?= e(__('Cookie-Einwilligung')) ?></h1></div>
</header>
<nav class="ck-nav" aria-label="<?= e(__('Cookie-Einwilligung')) ?>">
  <?php foreach ($items as $k => [$href, $label]): ?><a href="<?= e(url($href)) ?>"<?= $k === $current ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
  <a class="ck-nav__help" href="<?= e(url('/admin/hilfe/technik#consent')) ?>"><?= e(__('Technik-Hilfe')) ?></a>
</nav>
