<?php
/**
 * Logos: grid (umbrechende Reihe, zentriert) · marquee (Laufband in reinem CSS: Liste doppelt, zweite Hälfte aria-hidden;
 * hält bei Maus/Fokus an, steht bei „Bewegung reduzieren“ oder ausgeschalteter Bewegung als umbrechende Reihe still).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'marquee' ? 'marquee' : 'grid';
$items = array_values((array) ($d['items'] ?? []));
$render = function (bool $dup) use ($items, $b): string {
    $h = '';
    foreach ($items as $i => $it) {
        $pic = !empty($it['image']) ? img((int) $it['image'], '200px', ['alt' => $dup ? '' : (string) $it['name']]) : '';
        $inner = $pic !== '' ? $pic : '<span class="logos__name"' . ($dup ? '' : $b->edit("items.$i.name")) . '>' . e($it['name']) . '</span>';
        $link = trim((string) ($it['link'] ?? ''));
        $h .= '<li class="logos__item' . ($pic !== '' ? ' logos__item--img' : '') . '">'
            . ($link !== '' && !is_editing() && !$dup ? '<a class="logos__link" ' . galerie_link_attrs($link) . '>' . $inner . '</a>' : $inner) . '</li>';
    }
    return $h;
};
?>
<div class="wrap">
  <?= galerie_head($b, 'sec-head--center') ?>
  <?php if ($items): ?>
  <?php if ($v === 'marquee'): ?>
  <div class="marquee">
    <ul class="marquee__track" role="list"><?= $render(false) ?></ul>
    <ul class="marquee__track marquee__track--dup" role="list" aria-hidden="true"><?= $render(true) ?></ul>
  </div>
  <?php else: ?>
  <ul class="logos" role="list"><?= $render(false) ?></ul>
  <?php endif; ?>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Logos – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
