<?php /** Zitat: ein Eintrag = Randzitat im Magazinstil, zwei bis drei = Stimmen nebeneinander. @var \Core\Block $b  @var array $d */
$items = array_values((array) $d['items']);
$single = count($items) === 1;
?>
<div class="wrap">
  <?= editorial_head($b) ?>
  <div class="quotes<?= $single ? ' quotes--single' : ' cols-' . count($items) ?>">
    <?php foreach ($items as $i => $q): $img = (int) ($q['image'] ?? 0) ?: null; ?>
    <figure class="pq">
      <blockquote class="pq__text"<?= $b->edit("items.$i.text") ?>><p><?= nl2br(e($q['text']), false) ?></p></blockquote>
      <?php if (trim((string) ($q['name'] ?? '')) !== '' || trim((string) ($q['role'] ?? '')) !== ''): ?>
      <figcaption class="pq__who">
        <?php if ($img): ?><span class="pq__img"><?= img($img, '96px', ['ratio' => '1:1', 'alt' => '']) ?></span><?php endif; ?>
        <span><?php if (trim((string) $q['name']) !== ''): ?><span class="pq__name"<?= $b->edit("items.$i.name") ?>><?= e($q['name']) ?></span><?php endif; ?>
        <?php if (trim((string) ($q['role'] ?? '')) !== ''): ?><span class="pq__role"<?= $b->edit("items.$i.role") ?>><?= e($q['role']) ?></span><?php endif; ?></span>
      </figcaption>
      <?php endif; ?>
    </figure>
    <?php endforeach; ?>
  </div>
</div>
