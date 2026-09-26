<?php
/**
 * Video – die Arbeit macht der Kern (siehe partials/video-embed.php); js/video.js lädt nur auf Seiten mit diesem Block
 * (theme.php → conditional_css). JSON-LD VideoObject: 'jsonld' in der Blockdefinition.
 * @var \Core\Block $b  @var array $d
 */
?>
<div class="wrap wrap--text">
  <?= starter_head($b) ?>
  <?= app()->theme->partial('video-embed', ['url' => (string) $d['video_url'], 'file' => $d['video_file'] ?? null, 'poster' => $d['poster'] ?? null, 'label' => (string) $d['title']]) ?>
</div>
