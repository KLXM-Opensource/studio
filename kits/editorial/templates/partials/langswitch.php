<?php /** Sprachumschalter (nur bei mehreren aktiven Sprachen). @var array $langs  @var ?string $class */ ?>
<ul class="langswitch<?= !empty($class) ? ' ' . e($class) : '' ?>" aria-label="<?= e(lt('Sprache')) ?>">
  <?php foreach ($langs as $l): ?><li><a href="<?= e($l['url']) ?>" hreflang="<?= e($l['code']) ?>" lang="<?= e($l['code']) ?>"<?= $l['active'] ? ' aria-current="true"' : '' ?>><span aria-hidden="true"><?= e(strtoupper($l['code'])) ?></span><span class="sr-only"><?= e($l['label']) ?></span></a></li><?php endforeach; ?>
</ul>
