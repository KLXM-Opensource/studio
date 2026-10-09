<?php /** Text + Bild (Bild rechts oder links). @var \Core\Block $b  @var array $d */
$ratio = (string) ($d['ratio'] ?? '') ?: '4:3';
$list = array_values(array_filter(array_map('trim', preg_split('~\R~', (string) $d['list'])), fn($l) => $l !== ''));
$media = basis_image($d['image'] ?? null, '(min-width: 1080px) 560px, 100vw', $ratio, 'split__media');
?>
<div class="wrap split<?= $b->variant() === 'left' ? ' split--left' : '' ?><?= $media === '' ? ' split--solo' : '' ?>">
  <div class="split__text">
    <?= basis_head($b) ?>
    <?php if ($d['text'] !== '' || is_editing()): ?><div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div><?php endif; ?>
    <?php if ($list): ?>
    <ul class="checks">
      <?php foreach ($list as $li): ?><li><?= basis_icon('check', 'checks__icon') ?><span><?= emphasis((string) $li) ?></span></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php if ($d['button_label'] !== '' && $d['button_link'] !== ''): ?>
    <div class="btn-row"><a class="btn btn--secondary" <?= basis_link_attrs($d['button_link']) ?>><span<?= $b->edit('button_label') ?>><?= e($d['button_label']) ?></span></a></div>
    <?php endif; ?>
  </div>
  <?= $media ?>
</div>
