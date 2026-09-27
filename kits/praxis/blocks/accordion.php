<?php /** Akkordeon / FAQ (Hinweise). @var \Core\Block $b  @var array $d */
use Core\Forms;
$items = array_filter($d['items'], fn($i) => empty($i['service']) || Forms::enabled($i['service']));
?>
<div class="wrap split split--start">
  <div>
    <?= praxis_heading($b, 'h2', $d['intro'] || $d['show_emergency'] ? 'h2 h2--hinweise' : 'h2 h2--m') ?>
    <?php if ($d['intro'] || is_editing()): ?><div class="prose accordion__intro" data-reveal="up" data-delay="80"<?= $b->edit('intro', 'rich') ?>><?= rich($d['intro']) ?></div><?php endif; ?>
    <?php if ($d['show_emergency']): ?>
    <div class="notice notice--important notice--emergency" role="note" data-reveal="up" data-delay="160"<?= $b->central() ?>>
      <span aria-hidden="true" class="notice__icon">!</span>
      <strong><?= e(setting('notfall_titel')) ?></strong>
      <span class="notice__text"><?= e(setting('notfall_text')) ?></span>
    </div>
    <?php endif; ?>
  </div>
  <div class="accordion">
    <?php foreach ($items as $i => $it): ?>
    <details data-reveal="up">
      <summary><span<?= $b->edit("items.$i.q") ?>><?= e($it['q']) ?></span><span aria-hidden="true" class="accordion__icon">+</span></summary>
      <div class="accordion__body prose"<?= $b->edit("items.$i.a", 'rich') ?>><?= rich(is_editing() ? (string) $it['a'] : str_replace(['[Telefonnummer]'], [e(praxis_phone())], (string) $it['a'])) ?></div>
    </details>
    <?php endforeach; ?>
    <?php if ($d['footer'] || is_editing()): ?><p class="accordion__foot"<?= $b->edit('footer', 'inline') ?>><?= inline($d['footer']) ?></p><?php endif; ?>
  </div>
</div>
