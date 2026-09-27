<?php /** Text + Video (B03) – YouTube/Vimeo mit Zwei-Klick-Lösung, MP4 direkt. @var \Core\Block $b  @var array $d */
$right = $b->variant() === 'right';
?>
<div class="wrap media-split<?= $right ? '' : ' media-split--left' ?>">
  <div class="media-split__text" data-reveal="up">
    <?= praxis_heading($b, 'h2', 'h2 h2--m') ?>
    <?php if ($d['text'] || is_editing()): ?><div class="prose muted media-split__body"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div><?php endif; ?>
  </div>
  <div class="media-split__video" data-reveal="up" data-delay="120">
    <?= app()->theme->partial('video-embed', ['url' => $d['video_url'], 'file' => $d['video_file'], 'poster' => $d['poster'],
        'ratio' => '16-9', 'label' => '', 'note' => $d['consent_text']]) ?>
  </div>
</div>
