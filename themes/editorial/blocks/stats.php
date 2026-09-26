<?php /** Zahlen & Fakten: große Ziffern in der Überschriftenschrift, Beschriftung in Mono, Spaltenlinien. @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?= editorial_head($b) ?>
  <dl class="nums n-<?= count((array) $d['items']) ?>">
    <?php foreach ((array) $d['items'] as $i => $it): ?>
    <div class="num">
      <dt class="num__label"<?= $b->edit("items.$i.label") ?>><?= e($it['label']) ?></dt>
      <dd class="num__value"<?= $b->edit("items.$i.value") ?>><?= e($it['value']) ?></dd>
      <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><dd class="num__text"<?= $b->edit("items.$i.text") ?>><?= e($it['text']) ?></dd><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </dl>
</div>
