<?php /** Sprachumschalter (nur bei mehreren aktiven Sprachen). @var array $langs  @var ?string $class  @var ?bool $full (volle Namen) */ ?>
<ul class="langswitch<?= !empty($class) ? ' ' . e($class) : '' ?>" role="list" aria-label="<?= e(lt('Sprache')) ?>">
  <?php foreach ($langs as $l): ?><li><a href="<?= e($l['url']) ?>" hreflang="<?= e($l['code']) ?>" lang="<?= e($l['code']) ?>"<?= $l['active'] ? ' aria-current="true"' : '' ?>><?php if (!empty($full)): ?><?= e($l['label']) ?><?php else: ?><span aria-hidden="true"><?= e(strtoupper($l['code'])) ?></span><span class="sr-only"><?= e($l['label']) ?></span><?php endif; ?></a></li><?php endforeach; ?>
</ul>
