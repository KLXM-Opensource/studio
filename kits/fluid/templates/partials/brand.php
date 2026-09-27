<?php
/** Logo (hell/dunkel) oder Wortmarke mit Monogramm. @var string $href  @var ?string $class */
// Landing-Domain (Core\Landings): eigenes Logo bzw. eigener Name, sonst die der Website
$lp = landing();
$logo = $lp?->logo ?: ((int) setting('logo') ?: null);
$logoDark = $lp?->logo ? null : ((int) setting('logo_dark') ?: null);
$name = $lp?->name ?? fluid_name(true);
$light = $logo ? img($logo, '240px', ['eager' => true, 'alt' => '', 'class' => 'brand__img']) : '';
$dark = $light !== '' && $logoDark ? img($logoDark, '240px', ['eager' => true, 'alt' => '', 'class' => 'brand__img']) : '';
?>
<a class="brand<?= !empty($class) ? ' ' . e($class) : '' ?>" href="<?= e($href) ?>">
  <?php if ($light !== ''): ?>
    <span class="brand__logo<?= $dark !== '' ? ' brand__logo--light' : '' ?>"><?= $light ?></span>
    <?php if ($dark !== ''): ?><span class="brand__logo brand__logo--dark"><?= $dark ?></span><?php endif; ?>
    <span class="sr-only"><?= e($lp?->name ?? fluid_name()) ?></span>
  <?php else: ?>
    <span class="brand__mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
    <span class="brand__name"><?= e($name) ?></span>
  <?php endif; ?>
  <span class="sr-only"> – <?= e(lt('Startseite')) ?></span>
</a>
