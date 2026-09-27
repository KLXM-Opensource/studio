<?php
/**
 * Kern-Fragment „langswitch“ (überschreibbar: kits/{kit}/fragments/langswitch.php): Sprachumschalter – nur bei mehreren
 * aktiven Sprachen aufrufen. Kürzel sichtbar, Sprachname für Screenreader; mit $full die vollen Namen.
 * Klassen: .langswitch (+ $class), aktueller Eintrag aria-current="true".
 * Option des Kits (theme.php → 'fragments' → 'langswitch'): role_list = false lässt role="list" weg (Standard: gesetzt,
 * damit Screenreader die Liste auch ohne Aufzählungszeichen als Liste ansagen).
 * @var array $langs (language_links())  @var ?string $class  @var ?bool $full  @var array $options
 */
$roleList = ($options['role_list'] ?? true) !== false;
?>
<ul class="langswitch<?= !empty($class) ? ' ' . e($class) : '' ?>"<?= $roleList ? ' role="list"' : '' ?> aria-label="<?= e(lt('Sprache')) ?>">
  <?php foreach ($langs ?? [] as $l): ?><li><a href="<?= e($l['url']) ?>" hreflang="<?= e($l['code']) ?>" lang="<?= e($l['code']) ?>"<?= $l['active'] ? ' aria-current="true"' : '' ?>><?php if (!empty($full)): ?><?= e($l['label']) ?><?php else: ?><span aria-hidden="true"><?= e(strtoupper($l['code'])) ?></span><span class="sr-only"><?= e($l['label']) ?></span><?php endif; ?></a></li><?php endforeach; ?>
</ul>
