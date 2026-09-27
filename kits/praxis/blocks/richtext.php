<?php /** Fließtext (z. B. Rechtstexte). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap wrap--text">
  <?php if ($d['title_strong']): ?><?= praxis_heading($b, 'h2', 'h2 h2--m richtext__title') ?><?php endif; ?>
  <div class="prose prose--long"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div>
</div>
