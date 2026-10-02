<?php /** Text (UIkit) – rich() in .fw-prose (Grundstile von UIkit + Klassenvertrag der Formatierungsleiste, css/uikit.css) */ ?>
<div class="uk-container uk-container-small">
  <?php include __DIR__ . '/_head.php'; ?>
  <div class="fw-prose"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div>
</div>
