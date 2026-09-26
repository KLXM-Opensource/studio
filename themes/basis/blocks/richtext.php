<?php /** Fließtext (z. B. Rechtstexte). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap wrap--text">
  <?= basis_head($b) ?>
  <div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div>
</div>
