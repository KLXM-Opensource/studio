<?php /** Schnellkontakt-Karte als eigener Abschnitt. @var \Core\Block $b  @var array $d */ ?>
<div class="wrap split split--center">
  <div>
    <?= praxis_heading($b) ?>
    <?php if ($d['intro']): ?><p class="lead"<?= $b->edit('intro') ?>><?= e($d['intro']) ?></p><?php endif; ?>
  </div>
  <div class="hero__card"><?= app()->theme->partial('contact-card') ?></div>
</div>
