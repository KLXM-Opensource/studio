<?php
/** Fragen & Antworten (details/summary – ohne JavaScript). split: Kopf neben der Liste (Sidebar-Muster) · stacked. @var \Core\Block $b  @var array $d */
$v = $b->variant() === 'stacked' ? 'stacked' : 'split';
?>
<div class="wrap faq faq--<?= e($v) ?>">
  <?= fluid_head($b, $v === 'stacked' ? 'sec-head--center' : 'faq__head') ?>
  <div class="faq__list">
    <?php foreach ((array) $d['items'] as $i => $it): ?>
    <details class="faq__item" name="<?= e($b->domId()) ?>-faq">
      <summary class="faq__q"><span<?= $b->edit("items.$i.q") ?>><?= fluid_title((string) $it['q']) ?></span><span class="faq__icon" aria-hidden="true"></span></summary>
      <div class="faq__a prose"<?= $b->edit("items.$i.a", 'rich') ?>><?= rich($it['a']) ?></div>
    </details>
    <?php endforeach; ?>
    <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint">Noch keine Fragen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
  </div>
</div>
