<?php
/**
 * Fragen & Antworten – <details>/<summary>: aufklappbar ohne JavaScript, per Tastatur bedienbar.
 * Strukturierte Daten (FAQPage) entstehen im Core aus 'jsonld' der Blockdefinition – hier kein Code dafür.
 * @var \Core\Block $b  @var array $d
 */
?>
<div class="wrap wrap--text">
  <?= starter_head($b) ?>
  <div class="faq">
    <?php foreach ($d['items'] as $i => $it): ?>
    <details class="faq__item">
      <summary<?= $b->edit("items.$i.q") ?>><?= e($it['q']) ?></summary>
      <div class="prose"<?= $b->edit("items.$i.a", 'rich') ?>><?= rich($it['a']) ?></div>
    </details>
    <?php endforeach; ?>
    <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint">Noch keine Fragen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
  </div>
</div>
