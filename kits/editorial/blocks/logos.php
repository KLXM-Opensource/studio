<?php /** Partner & Förderer: Logos oder Namen als Schriftzug in einer Zeile mit Linien. @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?= editorial_head($b) ?>
  <ul class="logos" role="list">
    <?php foreach ((array) $d['items'] as $i => $it):
      $pic = !empty($it['image']) ? img((int) $it['image'], '200px', ['alt' => (string) $it['name']]) : '';
      $inner = $pic !== '' ? '<span class="logos__img">' . $pic . '</span>' : '<span class="logos__name"' . $b->edit("items.$i.name") . '>' . e($it['name']) . '</span>';
      $link = trim((string) ($it['link'] ?? '')); ?>
    <li class="logos__item"><?php if ($link !== ''): ?><a class="logos__link" <?= editorial_link_attrs($link) ?>><?= $inner ?><?= editorial_ext_note(editorial_link($link)) ?></a><?php else: ?><?= $inner ?><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</div>
