<?php
/**
 * Logos im Kit „NativeCSS“: „Laufband“ mit .ncss-marquee von NativeCSS – weiche Ränder, hält bei Maus/Fokus an, steht bei
 * „Bewegung reduzieren“ still (dann horizontal scrollbar) und hat eine sichtbare Schaltfläche „Laufband anhalten“
 * (WCAG 2.2.2; js/marquee.js verdoppelt die Liste für die Schleife, aria-hidden). Beim Bearbeiten und mit „Dezente
 * Animationen“ aus: ruhiges Raster. „Raster“ kommt von fluid.
 * @var \Core\Block $b  @var array $d
 */
if ($b->variant() !== 'marquee' || !native_motion()) {
    require native_parent_view('logos');
    return;
}
native_mark('logos:@marquee');
$items = array_values((array) ($d['items'] ?? []));
$id = $b->domId() . '-marquee';
$render = function () use ($items): string {
    $h = '';
    foreach ($items as $it) {
        $pic = !empty($it['image']) ? img((int) $it['image'], '200px', ['alt' => (string) $it['name']]) : '';
        $inner = $pic !== '' ? $pic : '<span class="logos__name">' . e((string) $it['name']) . '</span>';
        $link = trim((string) ($it['link'] ?? ''));
        $h .= '<li class="logos__item' . ($pic !== '' ? ' logos__item--img' : '') . '">' . ($link !== '' ? '<a class="logos__link" ' . fluid_link_attrs($link) . '>' . $inner . '</a>' : $inner) . '</li>';
    }
    return $h;
};
?>
<div class="wrap nv-logos">
  <?= fluid_head($b, 'sec-head--center') ?>
  <?php if ($items): ?>
  <div class="ncss-marquee ncss-marquee--fade nv-logos__marquee" id="<?= e($id) ?>" data-ncss-marquee>
    <ul class="ncss-marquee-group" role="list"><?= $render() ?></ul>
  </div>
  <p class="nv-logos__pause"><button type="button" class="btn btn--secondary nv-logos__toggle" data-ncss-marquee-toggle aria-controls="<?= e($id) ?>" aria-pressed="false"><?= e(lt('Laufband anhalten')) ?></button></p>
  <?php endif; ?>
</div>
