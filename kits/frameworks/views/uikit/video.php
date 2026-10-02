<?php /** Video (UIkit) – Kern-Fragment video-embed, Aussehen in css/video.css */ ?>
<div class="uk-container uk-container-small">
  <?php include __DIR__ . '/_head.php'; ?>
  <?= app()->theme->partial('video-embed', ['url' => (string) $d['video_url'], 'file' => $d['video_file'] ?? null, 'poster' => $d['poster'] ?? null, 'label' => (string) $d['title']]) ?>
</div>
