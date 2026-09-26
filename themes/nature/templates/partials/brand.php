<?php
/** Logo (hell/dunkel) oder Wortmarke mit Blatt (zwei gerundete Ecken, feine Mittelrippe). @var string $href  @var ?string $class */
$logo = (int) setting('logo') ?: null;
$logoDark = (int) setting('logo_dark') ?: null;
$name = nature_name(true);
$light = $logo ? img($logo, '240px', ['eager' => true, 'alt' => '', 'class' => 'brand__img']) : '';
$dark = $light !== '' && $logoDark ? img($logoDark, '240px', ['eager' => true, 'alt' => '', 'class' => 'brand__img']) : '';
?>
<a class="brand<?= !empty($class) ? ' ' . e($class) : '' ?>" href="<?= e($href) ?>">
  <?php if ($light !== ''): ?>
    <span class="brand__logo<?= $dark !== '' ? ' brand__logo--light' : '' ?>"><?= $light ?></span>
    <?php if ($dark !== ''): ?><span class="brand__logo brand__logo--dark"><?= $dark ?></span><?php endif; ?>
    <span class="sr-only"><?= e(nature_name()) ?></span>
  <?php else: ?>
    <span class="brand__mark" aria-hidden="true"></span>
    <span class="brand__name"><?= e($name) ?></span>
  <?php endif; ?>
  <span class="sr-only"> – <?= e(lt('Startseite')) ?></span>
</a>
