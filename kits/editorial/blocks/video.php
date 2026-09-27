<?php /** Video (YouTube/Vimeo mit Zwei-Klick-Lösung, MP4 mit Untertiteln und Transkript) – breit oder mit Text daneben. @var \Core\Block $b  @var array $d */
$side = $b->variant() === 'text';
$embed = app()->theme->partial('video-embed', ['url' => $d['video_url'], 'file' => $d['video_file'], 'poster' => $d['poster'],
    'ratio' => $d['ratio'] ?: '16-9', 'label' => trim((string) $d['title'])]);
?>
<div class="wrap<?= $side ? ' duo duo--video' : '' ?>">
  <?php if ($side): ?>
  <div class="duo__text">
    <?= editorial_head($b) ?>
    <?php if (trim(strip_tags((string) $d['text'])) !== '' || is_editing()): ?><div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich((string) $d['text']) ?></div><?php endif; ?>
  </div>
  <?php else: ?>
  <?= editorial_head($b) ?>
  <?php endif; ?>
  <figure class="video<?= $side ? ' duo__fig' : '' ?>">
    <?= $embed ?>
    <?php if (trim((string) $d['caption']) !== '' || is_editing()): ?><figcaption class="cap"><span class="cap__text"<?= $b->edit('caption') ?>><?= e($d['caption']) ?></span></figcaption><?php endif; ?>
  </figure>
</div>
