<?php
/**
 * Video (YouTube/Vimeo mit Zwei-Klick-Lösung, MP4 direkt mit Untertiteln und Transkript aus der Mediathek):
 * wide · text (Text daneben).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'text' ? 'text' : 'wide';
$embed = app()->theme->partial('video-embed', ['url' => $d['video_url'], 'file' => $d['video_file'], 'poster' => $d['poster'],
    'ratio' => $d['ratio'] ?: '16-9', 'label' => strip_emphasis(trim((string) $d['title']))]);
$fig = '<figure class="video">' . $embed
    . ((trim((string) $d['caption']) !== '' || is_editing()) ? '<figcaption class="video__cap"' . $b->edit('caption') . '>' . glas_title((string) $d['caption']) . '</figcaption>' : '')
    . '</figure>';
?>
<?php if ($v === 'text'): ?>
<div class="wrap video-split">
  <div class="video-split__text">
    <?= glas_head($b) ?>
    <?php if (trim(strip_tags((string) $d['text'])) !== '' || is_editing()): ?><div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich((string) $d['text']) ?></div><?php endif; ?>
  </div>
  <?= $fig ?>
</div>
<?php else: ?>
<div class="wrap">
  <?= glas_head($b) ?>
  <?= $fig ?>
</div>
<?php endif;
