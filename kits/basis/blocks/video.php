<?php /** Video (YouTube/Vimeo mit Zwei-Klick-Lösung, MP4 direkt) – breit oder mit Text daneben. @var \Core\Block $b  @var array $d */
$side = $b->variant() === 'text';
$embed = app()->theme->partial('video-embed', ['url' => $d['video_url'], 'file' => $d['video_file'], 'poster' => $d['poster'],
    'ratio' => $d['ratio'] ?: '16-9', 'label' => trim((string) $d['title'])]);
?>
<div class="wrap<?= $side ? ' split video-split' : '' ?>">
  <?php if ($side): ?>
  <div class="split__text">
    <?= basis_head($b) ?>
    <?php if (trim(strip_tags((string) $d['text'])) !== '' || is_editing()): ?><div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich((string) $d['text']) ?></div><?php endif; ?>
  </div>
  <?php else: ?>
  <?= basis_head($b) ?>
  <?php endif; ?>
  <figure class="video">
    <?= $embed ?>
    <?php if (trim((string) $d['caption']) !== '' || is_editing()): ?><figcaption class="video__cap"<?= $b->edit('caption') ?>><?= e($d['caption']) ?></figcaption><?php endif; ?>
  </figure>
</div>
