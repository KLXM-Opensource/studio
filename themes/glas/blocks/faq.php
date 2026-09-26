<?php
/** Fragen & Antworten (details/summary – ohne JavaScript; nur eine offen). split: Kopf neben der Liste · stacked. @var \Core\Block $b  @var array $d */
$v = $b->variant() === 'stacked' ? 'stacked' : 'split';
?>
<div class="wrap faq faq--<?= e($v) ?>">
  <?= glas_head($b, 'faq__head') ?>
  <div class="faq__list">
    <?php foreach ((array) $d['items'] as $i => $it): ?>
    <details class="faq__item" name="<?= e($b->domId()) ?>-faq">
      <summary class="faq__q"><span class="faq__n" aria-hidden="true"><?= glas_num($i + 1) ?></span><span class="faq__qt"<?= $b->edit("items.$i.q") ?>><?= e($it['q']) ?></span><span class="faq__pm" aria-hidden="true"></span></summary>
      <div class="faq__a prose"<?= $b->edit("items.$i.a", 'rich') ?>><?= rich($it['a']) ?></div>
    </details>
    <?php endforeach; ?>
    <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint">Noch keine Fragen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
  </div>
</div>
