<?php
/**
 * Künstlerinnen und Künstler (Tabelle „Künstler“).
 * list  Typografische Liste; ab 44 rem Blockbreite erscheint beim Zeigen/Fokus ein Bild neben dem Namen (reines CSS,
 *       :hover/:focus-within – ohne JavaScript, bei „Bewegung reduzieren“ ohne Übergang); darunter kleine Vorschaubilder.
 * grid  Porträts im auto-fit-Raster · works  ein Werk je Künstler · az  A–Z-Register mit Sprungmarken und Spaltensatz.
 * Bild in der Liste: Porträt, sonst erstes Werk.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;

$v = $b->variant() ?: 'list';
$t = galerie_table('artists');
if (!$t) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">Die Tabelle „Künstler“ fehlt (Website → Galerie → Datentabellen).</p></div>';
    return;
}
$rows = galerie_artists(($d['selection'] ?? 'all') === 'featured', ($d['order'] ?? 'alpha') === 'manual' ? 'manual' : 'alpha');
$tag = galerie_htag($d);
$meta = fn(array $a) => !empty($d['show_meta']) ? implode(' · ', array_filter([trim((string) ($a['geboren'] ?? '')), trim((string) ($a['lebt'] ?? ''))])) : '';
$firstWork = function (array $a): ?int {
    $w = galerie_works(['artist' => (int) $a['id'], 'limit' => 1])[0] ?? null;
    return $w && !empty($w['bild']) ? (int) $w['bild'] : null;
};
$ratio = (string) ($d['ratio'] ?? '') ?: '3:4';
?>
<div class="wrap">
  <?= galerie_head($b) ?>
  <?php if (!$rows): ?>
    <?php if (is_editing()): ?><p class="empty-hint">Noch keine (hervorgehobenen) Einträge in „<?= e($t['name']) ?>“.</p><?php endif; ?>
  <?php elseif ($v === 'list'): ?>
  <ul class="al" role="list">
    <?php foreach ($rows as $a):
        $href = Entries::href($t, $a);
        $pv = !empty($a['portraet']) ? (int) $a['portraet'] : $firstWork($a);
        $m = $meta($a);
    ?>
    <li class="al__item" data-reveal><?= edit_link('entry:' . $t['handle'] . ':' . $a['id'], Entries::title($t, $a)) ?>
      <<?= $tag ?> class="al__name"><?php if ($href && !is_editing()): ?><a class="al__link" href="<?= e($href) ?>"><?= e(Entries::title($t, $a)) ?></a><?php else: ?><span<?= entry_edit_attr($t, $a, 'name') ?>><?= e(Entries::title($t, $a)) ?></span><?php endif; ?></<?= $tag ?>>
      <?php if ($m !== ''): ?><p class="al__meta"><?= e($m) ?></p><?php endif; ?>
      <?php if ($pv): ?><div class="al__pv" aria-hidden="true"><?= img($pv, '(min-width: 1080px) 320px, 30vw', ['alt' => '']) ?></div><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif ($v === 'az'):
      $by = [];
      foreach ($rows as $a) $by[galerie_initial($a)][] = $a;
      ksort($by);
      $id = $b->domId();
  ?>
  <nav class="az__nav" aria-label="<?= e(lt('Register A–Z')) ?>">
    <ul class="az__letters" role="list">
      <?php foreach (array_merge(range('A', 'Z'), isset($by['#']) ? ['#'] : []) as $L): ?>
      <li><?php if (isset($by[$L])): ?><a href="#<?= e($id . '-' . ($L === '#' ? 'x' : $L)) ?>"><?= e($L) ?></a><?php else: ?><span aria-hidden="true"><?= e($L) ?></span><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <div class="az">
    <?php foreach ($by as $L => $as): ?>
    <section class="az__group" id="<?= e($id . '-' . ($L === '#' ? 'x' : $L)) ?>" aria-labelledby="<?= e($id . '-h-' . ($L === '#' ? 'x' : $L)) ?>">
      <<?= $tag ?> class="az__letter" id="<?= e($id . '-h-' . ($L === '#' ? 'x' : $L)) ?>"><?= e($L) ?></<?= $tag ?>>
      <ul class="az__list" role="list">
        <?php foreach ($as as $a): $href = Entries::href($t, $a); $m = $meta($a); ?>
        <li><?php if ($href): ?><a href="<?= e($href) ?>"><?= e(Entries::title($t, $a)) ?></a><?php else: ?><?= e(Entries::title($t, $a)) ?><?php endif; ?><?php if ($m !== ''): ?> <span class="az__meta"><?= e($m) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <ul class="grid <?= e(galerie_min($d)) ?> ag" role="list">
    <?php foreach ($rows as $a):
        $href = Entries::href($t, $a);
        $imgId = $v === 'works' ? $firstWork($a) : (!empty($a['portraet']) ? (int) $a['portraet'] : null);
        $m = $meta($a);
    ?>
    <li class="ag__item" data-reveal><?= edit_link('entry:' . $t['handle'] . ':' . $a['id'], Entries::title($t, $a)) ?>
      <?= galerie_image($imgId, '(min-width: 1080px) 360px, (min-width: 640px) 45vw, 100vw', $ratio, 'ag__img') ?: '<div class="frame r-' . e(str_replace(':', '-', $ratio)) . ' ag__img ag__img--mono" aria-hidden="true"><span>' . e(mb_substr(Entries::title($t, $a), 0, 1)) . '</span></div>' ?>
      <<?= $tag ?> class="ag__name"><?php if ($href && !is_editing()): ?><a class="cover-link" href="<?= e($href) ?>"><?= e(Entries::title($t, $a)) ?></a><?php else: ?><?= e(Entries::title($t, $a)) ?><?php endif; ?></<?= $tag ?>>
      <?php if ($m !== ''): ?><p class="ag__meta"><?= e($m) ?></p><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
