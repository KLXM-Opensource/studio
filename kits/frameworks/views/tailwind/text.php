<?php
/** Text (Tailwind) – rich() + Typography-Plugin; $b->edit('text', 'rich') öffnet im Editor die Formatierungsleiste. */
?>
<div class="wrap">
  <div class="max-w-3xl">
    <?php include __DIR__ . '/_head.php'; ?>
    <div class="prose prose-slate max-w-none dark:prose-invert prose-a:text-accent"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div>
  </div>
</div>
