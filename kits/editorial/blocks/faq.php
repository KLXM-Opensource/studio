<?php /** Fragen & Antworten (details/summary – ohne JavaScript; schema.org FAQPage über theme.php). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap faq">
  <?= editorial_head($b, 'faq__head') ?>
  <div class="faq__list">
    <?php foreach ((array) $d['items'] as $i => $it): ?>
    <details class="faq__item">
      <summary class="faq__q"><span<?= $b->edit("items.$i.q") ?>><?= emphasis((string) $it['q']) ?></span><span class="faq__icon" aria-hidden="true"></span></summary>
      <div class="faq__a prose"<?= $b->edit("items.$i.a", 'rich') ?>><?= rich($it['a']) ?></div>
    </details>
    <?php endforeach; ?>
    <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint">Noch keine Fragen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
  </div>
</div>
