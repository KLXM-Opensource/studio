<?php
/**
 * Ablauf: steps (nummerierte Glasperlen nebeneinander, verbunden durch eine leuchtende Linie) ·
 * timeline (senkrechte Zeitleiste, Phase/Datum links, sobald Platz ist). Geordnete Liste (<ol>).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'timeline' ? 'timeline' : 'steps';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== '' || is_editing()));
$tag = glas_htag($d);
?>
<div class="wrap">
  <?= glas_head($b) ?>
  <?php if ($items): ?>
  <ol class="st st--<?= e($v) ?>" role="list">
    <?php foreach ($items as $i => $it): $meta = trim((string) ($it['meta'] ?? '')); ?>
    <li class="st__item" data-reveal>
      <span class="st__node" aria-hidden="true"><span class="st__n"><?= glas_num($i + 1) ?></span></span>
      <?php if ($meta !== '' || is_editing()): ?><p class="st__meta label"<?= $b->edit("items.$i.meta") ?>><?= e($meta) ?></p><?php endif; ?>
      <div class="st__body">
        <<?= $tag ?> class="st__title"<?= $b->edit("items.$i.title") ?>><?= glas_title((string) ($it['title'] ?? '')) ?></<?= $tag ?>>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="st__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(glas_title((string) $it['text']), false) ?></p><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Schritte – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
