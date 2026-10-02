<?php
/**
 * Administration → Einstellungen (Core\AdminPages, PrefsController): reine Einstellungsseiten von Funktionen und Erweiterungen
 * (kind „settings“) an einem Ort – statt je eines Menüpunkts. Grundeinstellungen und Funktionen & Erweiterungen stehen oben als Links.
 * @var array $groups
 */
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?></p><h1><?= e(__('Einstellungen')) ?></h1>
    <p class="adm-muted"><?= e(__('Einstellungen der Funktionen und Erweiterungen dieser Website. Seiten, die zu einer Datentabelle gehören, finden Sie zusätzlich direkt an der Tabelle.')) ?></p></div>
</header>
<?php $top = array_filter([
    can('system.manage') ? ['/admin/system', __('Grundeinstellungen'), 'system'] : null,
    \Core\Features::canView() ? ['/admin/funktionen', __('Funktionen & Erweiterungen'), 'features'] : null,
]); ?>
<?php if ($top): ?>
<nav class="pf-top" aria-label="<?= e(__('Weitere Einstellungen')) ?>">
  <?php foreach ($top as [$href, $label, $ico]): ?><a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e(url($href)) ?>"><?= \Core\Icons::render($ico) ?> <?= e($label) ?></a><?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if (!$groups): ?>
<div class="adm-card rv-empty"><p><b><?= e(__('Keine weiteren Einstellungen.')) ?></b></p>
  <p class="adm-muted"><?= e(__('Eingeschaltete Funktionen und Erweiterungen mit eigenen Einstellungen erscheinen hier.')) ?></p></div>
<?php else: ?>
<?= \Core\Theme::capture(__DIR__ . '/_cards.php', ['groups' => $groups]) ?>
<?php endif; ?>
