<?php
/**
 * Video – die Arbeit macht der Kern: Kern-Fragment video-embed (app/Views/fragments/video-embed.php, Optionen in
 * theme.php → 'fragments'), Skript resources/js/embed.js lädt es selbst nur auf Seiten mit Video
 * (das Fragment gibt es aus). JSON-LD VideoObject: 'jsonld' in der Blockdefinition.
 * @var \Core\Block $b  @var array $d
 */
?>
<div class="wrap wrap--text">
  <?= starter_head($b) ?>
  <?= app()->theme->partial('video-embed', ['url' => (string) $d['video_url'], 'file' => $d['video_file'] ?? null, 'poster' => $d['poster'] ?? null, 'label' => (string) $d['title']]) ?>
</div>
