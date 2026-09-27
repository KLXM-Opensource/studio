<?php /** Merkmale / Leistungen als Karten. @var \Core\Block $b  @var array $d */
$cols = in_array((string) $d['columns'], ['2', '3', '4'], true) ? (string) $d['columns'] : '3';
$style = in_array($d['style'] ?? '', ['grid', 'cards', 'plain'], true) ? $d['style'] : 'grid';
?>
<div class="wrap">
  <?= basis_head($b) ?>
  <?php if ($d['items']): ?>
  <ul class="features features--<?= $style ?> cols-<?= $cols ?>" role="list">
    <?php foreach ($d['items'] as $i => $it):
        $link = trim((string) ($it['link'] ?? ''));
        $label = trim((string) ($it['link_label'] ?? ''));
        $pic = !empty($it['image']) ? basis_image((int) $it['image'], '(min-width: 1080px) 400px, (min-width: 640px) 50vw, 100vw', '3:2', 'feature__media') : '';
    ?>
    <li class="feature<?= $link !== '' ? ' feature--link' : '' ?>">
      <?php if ($pic !== ''): ?><?= $pic ?>
      <?php elseif (!empty($it['icon'])): ?><span class="feature__icon"><?= basis_icon((string) $it['icon']) ?></span><?php endif; ?>
      <h3 class="feature__title">
        <?php if ($link !== '' && $label === '' && !is_editing()): ?><a class="feature__cover" <?= basis_link_attrs($link) ?>><?= e($it['title']) ?></a>
        <?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></span><?php endif; ?>
      </h3>
      <?php if (($it['text'] ?? '') !== ''): ?><p class="feature__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif; ?>
      <?php if ($link !== '' && $label !== ''): ?>
      <a class="more feature__more" <?= basis_link_attrs($link) ?>><span<?= $b->edit("items.$i.link_label") ?>><?= e($label) ?></span><?= basis_icon('arrow', 'more__icon') ?><span class="sr-only">: <?= e($it['title']) ?></span></a>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Einträge – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
