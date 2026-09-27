<?php
/**
 * Kern-Fragment „brand“ (überschreibbar: kits/{kit}/fragments/brand.php, project/overrides/kits/{kit}/fragments/brand.php):
 * Logo (hell/dunkel) oder Wortmarke, Link zur Startseite. Landing-Domain (Core\Landings): eigenes Logo bzw. eigener Name.
 * Klassen: .brand (+ $class) · .brand__logo (--light/--dark) · .brand__img · .brand__mark · .brand__dot · .brand__name
 *
 * Optionen des Kits (theme.php → 'fragments' → 'brand'):
 *   mark        'monogram' (Standard: erster Buchstabe) | 'dot' (.brand__mark mit .brand__dot) | 'empty' (leere .brand__mark,
 *               z. B. per CSS gezeichnet) | 'none' (nur der Name)
 *   logo_width  Breite des Logos für srcset/sizes (Standard '240px')
 *
 * @var string $href  @var ?string $class  @var array $options
 */
$o = ($options ?? []) + ['mark' => 'monogram', 'logo_width' => '240px'];
$lp = landing();
$logo = $lp?->logo ?: ((int) setting('logo') ?: null);
$logoDark = $lp?->logo ? null : ((int) setting('logo_dark') ?: null);
$name = $lp?->name ?? org_name(true);
$light = $logo ? img($logo, (string) $o['logo_width'], ['eager' => true, 'alt' => '', 'class' => 'brand__img']) : '';
$dark = $light !== '' && $logoDark ? img($logoDark, (string) $o['logo_width'], ['eager' => true, 'alt' => '', 'class' => 'brand__img']) : '';
?>
<a class="brand<?= !empty($class) ? ' ' . e($class) : '' ?>" href="<?= e($href ?? url('/')) ?>">
  <?php if ($light !== ''): ?>
    <span class="brand__logo<?= $dark !== '' ? ' brand__logo--light' : '' ?>"><?= $light ?></span>
    <?php if ($dark !== ''): ?><span class="brand__logo brand__logo--dark"><?= $dark ?></span><?php endif; ?>
    <span class="sr-only"><?= e($lp?->name ?? org_name()) ?></span>
  <?php else: ?>
    <?php if ($o['mark'] === 'monogram'): ?><span class="brand__mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span>
    <?php elseif ($o['mark'] === 'dot'): ?><span class="brand__mark" aria-hidden="true"><span class="brand__dot"></span></span>
    <?php elseif ($o['mark'] === 'empty'): ?><span class="brand__mark" aria-hidden="true"></span>
    <?php endif; ?>
    <span class="brand__name"><?= e($name) ?></span>
  <?php endif; ?>
  <span class="sr-only"> – <?= e(lt('Startseite')) ?></span>
</a>
