<?php
/**
 * Fragen & Antworten im Kit „NativeCSS“: natives details/summary in der Optik .ncss-disclosure (ohne JavaScript; name= schließt
 * die anderen Antworten der Liste). split: Kopf neben der Liste · stacked: Kopf darüber.
 * @var \Core\Block $b  @var array $d
 */
native_mark('@nv-faq');
$v = $b->variant() === 'stacked' ? 'stacked' : 'split';
?>
<div class="wrap nv-faq nv-faq--<?= e($v) ?>">
  <?= fluid_head($b, $v === 'stacked' ? 'sec-head--center' : 'nv-faq__head') ?>
  <div class="nv-faq__list">
    <?php foreach ((array) $d['items'] as $i => $it): ?>
    <details class="ncss-disclosure nv-faq__item" name="<?= e($b->domId()) ?>-faq">
      <summary><span<?= $b->edit("items.$i.q") ?>><?= fluid_title((string) $it['q']) ?></span></summary>
      <div class="prose nv-faq__a"<?= $b->edit("items.$i.a", 'rich') ?>><?= rich($it['a']) ?></div>
    </details>
    <?php endforeach; ?>
    <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint">Noch keine Fragen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
  </div>
</div>
