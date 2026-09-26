<?php
/**
 * Zitat / Stimmen: single (großes Zitat mit Anführungszeichen in Blockfarbe) · grid (Mauerwerk mit CSS-Spalten).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'single';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['text'] ?? '')) !== ''));
if ($v === 'single' && count($items) > 1 && !is_editing()) $items = [$items[0]];
$person = function (array $it, int $i) use ($b): string {
    if (trim(($it['name'] ?? '') . ($it['role'] ?? '')) === '') return '';
    $pic = !empty($it['image']) ? img((int) $it['image'], '56px', ['ratio' => '1:1', 'alt' => '']) : '';
    return '<figcaption class="quote__by">' . ($pic !== '' ? '<span class="quote__avatar">' . $pic . '</span>' : '')
        . '<span><span class="quote__name"' . $b->edit("items.$i.name") . '>' . e($it['name'] ?? '') . '</span>'
        . (($it['role'] ?? '') !== '' ? '<span class="quote__role"' . $b->edit("items.$i.role") . '>' . e($it['role']) . '</span>' : '') . '</span></figcaption>';
};
?>
<div class="wrap">
  <?= modern_head($b, $v === 'single' ? 'sec-head--center' : '') ?>
  <?php if ($v === 'single' && $items): $it = $items[0]; ?>
  <figure class="pull">
    <?= icon('quotes', ['class' => 'pull__mark']) ?>
    <blockquote class="pull__text"><p<?= $b->edit('items.0.text') ?>><?= nl2br(e($it['text']), false) ?></p></blockquote>
    <?= $person($it, 0) ?>
  </figure>
  <?php elseif ($items): ?>
  <ul class="quotes" role="list">
    <?php foreach ($items as $i => $it): ?>
    <li class="quote card" data-reveal><figure>
      <?= icon('quotes', ['class' => 'quote__mark']) ?>
      <blockquote class="quote__text"><p<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p></blockquote>
      <?= $person($it, $i) ?>
    </figure></li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch kein Zitat – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
