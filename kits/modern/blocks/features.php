<?php
/**
 * Merkmale / Leistungen. Raster ohne Spaltenzahl: repeat(auto-fit, minmax(min(100%, Mindestbreite), 1fr)).
 * Jede Karte ist ein Container und stellt sich auf ihre eigene Breite ein (Liste: Symbol neben dem Text, wenn Platz ist).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'cards';
$items = array_values((array) ($d['items'] ?? []));
$tag = modern_htag($d);
?>
<div class="wrap">
  <?= modern_head($b) ?>
  <?php if ($items): ?>
  <ul class="grid <?= e(modern_min($d)) ?> feats feats--<?= e($v) ?>" role="list">
    <?php foreach ($items as $i => $it):
        $link = trim((string) ($it['link'] ?? ''));
        $label = trim((string) ($it['link_label'] ?? ''));
        $cover = $link !== '' && $label === '' && !is_editing();
        $pic = !empty($it['image']) ? modern_image((int) $it['image'], '(min-width: 1080px) 400px, (min-width: 640px) 50vw, 100vw', '3:2', 'feat__media') : '';
    ?>
    <li class="feat<?= $link !== '' ? ' feat--link' : '' ?><?= $v === 'cards' ? ' card' : '' ?>" data-reveal>
      <?php if ($pic !== ''): ?><?= $pic ?>
      <?php elseif ($v === 'numbered'): ?><span class="feat__num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
      <?php elseif (!empty($it['icon'])): ?><span class="feat__ico"><?= icon((string) $it['icon']) ?></span><?php endif; ?>
      <div class="feat__body">
        <<?= $tag ?> class="feat__title"><?php if ($cover): ?><a class="cover-link" <?= modern_link_attrs($link) ?>><?= modern_title((string) $it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= modern_title((string) $it['title']) ?></span><?php endif; ?></<?= $tag ?>>
        <?php if (($it['text'] ?? '') !== ''): ?><p class="feat__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(modern_title((string) $it['text']), false) ?></p><?php endif; ?>
        <?php if ($link !== '' && $label !== ''): ?><a class="more" <?= modern_link_attrs($link) ?>><span<?= $b->edit("items.$i.link_label") ?>><?= e($label) ?></span><?= icon('arrow-right', ['class' => 'more__ico']) ?><span class="sr-only">: <?= e(strip_emphasis($it['title'])) ?></span></a><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Einträge – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
