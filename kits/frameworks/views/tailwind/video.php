<?php
/** Video (Tailwind) – Markup aus dem Kern-Fragment video-embed (Zwei-Klick), Aussehen in css/video.css (beide Frameworks). */
?>
<div class="wrap">
  <div class="mx-auto max-w-4xl">
    <?php include __DIR__ . '/_head.php'; ?>
    <?= app()->theme->partial('video-embed', ['url' => (string) $d['video_url'], 'file' => $d['video_file'] ?? null, 'poster' => $d['poster'] ?? null, 'label' => (string) $d['title']]) ?>
  </div>
</div>
