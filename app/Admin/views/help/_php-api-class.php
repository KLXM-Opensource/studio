<?php
/** Eine Klasse der PHP-API-Referenz (Core\ApiReference::build). @var string $cls  @var array $c */
?>
<details class="api-cls" id="api-<?= e(strtolower(str_replace('\\', '-', $cls))) ?>"<?= !empty($open) ? ' open' : '' ?>>
  <summary><code><?= e($cls) ?></code> <small class="adm-muted"><?= e($c['kind']) ?> · <?= count($c['methods']) ?> <?= e(__('Methoden')) ?></small><?php if ($c['public']): ?> <span class="tag tag--read">API</span><?php endif; ?></summary>
  <?php if ($c['doc'] !== ''): ?><p><?= e($c['doc']) ?></p><?php endif; ?>
  <p class="adm-muted"><small><code><?= e($c['file']) ?></code><?php if ($c['consts']): ?> · <?= e(__('Konstanten')) ?>: <?= e(implode(', ', $c['consts'])) ?><?php endif; ?></small></p>
  <?php if ($c['methods']): ?>
  <ul class="api-methods">
    <?php foreach ($c['methods'] as $m): ?><li><code><?= $m['static'] ? '<i>static</i> ' : '' ?><?= e($m['sig']) ?></code><?php if ($m['doc'] !== ''): ?><br><span class="adm-muted"><?= e($m['doc']) ?></span><?php endif; ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
</details>
