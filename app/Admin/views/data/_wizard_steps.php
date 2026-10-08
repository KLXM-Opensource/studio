<?php
/**
 * Fortschritt des Assistenten „Neue Tabelle“ (Core\Data\Wizard::STEPS): Schritte mit Nummer, erledigte mit Haken, aktueller mit
 * aria-current="step". Frühere Schritte sind Links (zurückgehen ohne Datenverlust – der Zustand liegt in der Sitzung).
 * @var string $step  aktueller Schritt  @var bool $links  frühere Schritte verlinken (nicht mehr nach dem Anlegen)
 */
$labels = \Core\Data\Wizard::stepLabels();
$keys = array_keys($labels);
$cur = array_search($step, $keys, true);
?>
<nav class="wz-steps" aria-label="<?= e(__('Fortschritt')) ?>">
  <ol>
    <?php foreach ($keys as $i => $k): $state = $i < $cur ? 'done' : ($i === $cur ? 'current' : 'todo'); ?>
    <li class="wz-steps__item is-<?= $state ?>"<?= $state === 'current' ? ' aria-current="step"' : '' ?>>
      <?php if ($state === 'done' && !empty($links)): ?><a href="<?= e(url('/admin/data/new?schritt=' . $k)) ?>"><?php endif; ?>
      <span class="wz-steps__n" aria-hidden="true"><?= $state === 'done' ? '✓' : $i + 1 ?></span>
      <span class="wz-steps__label"><span class="adm-sr"><?= e(__('Schritt {n} von {all}:', ['n' => $i + 1, 'all' => count($keys)])) ?> </span><?= e($labels[$k]) ?><?php if ($state === 'done'): ?><span class="adm-sr"> (<?= e(__('erledigt')) ?>)</span><?php endif; ?></span>
      <?php if ($state === 'done' && !empty($links)): ?></a><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ol>
</nav>
