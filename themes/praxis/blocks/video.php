<?php /** Video in voller Breite – YouTube/Vimeo mit Zwei-Klick-Lösung, MP4 direkt. @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?php if ($d['title_strong']): ?><div class="video-block__head"><?= praxis_heading($b, 'h2', 'h2 h2--m') ?></div><?php endif; ?>
  <figure class="video-block" data-reveal="up">
    <?= app()->theme->partial('video-embed', ['url' => $d['video_url'], 'file' => $d['video_file'], 'poster' => $d['poster'],
        'ratio' => $b->variant() ?: '16-9', 'label' => '', 'note' => '']) ?>
    <?php if ($d['caption'] || is_editing()): ?><figcaption<?= $b->edit('caption') ?>><?= e($d['caption']) ?></figcaption><?php endif; ?>
  </figure>
</div>
