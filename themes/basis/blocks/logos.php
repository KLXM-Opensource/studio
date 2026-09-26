<?php /** Logos von Partnern oder Kunden; ohne Bild als Schriftzug. @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?= basis_head($b, 'sec-head--center') ?>
  <?php if ($d['items']): ?>
  <ul class="logos" role="list">
    <?php foreach ($d['items'] as $i => $it):
        $pic = !empty($it['image']) ? img((int) $it['image'], '200px', ['alt' => (string) $it['name']]) : '';
        $inner = $pic !== '' ? $pic : '<span class="logos__name"' . $b->edit("items.$i.name") . '>' . e($it['name']) . '</span>';
        $link = trim((string) ($it['link'] ?? ''));
    ?>
    <li class="logos__item<?= $pic !== '' ? ' logos__item--img' : '' ?>">
      <?php if ($link !== '' && !is_editing()): ?><a class="logos__link" <?= basis_link_attrs($link) ?>><?= $inner ?></a><?php else: ?><?= $inner ?><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Logos – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
