<?php /** Zitat / Stimmen: ein Eintrag groß, mehrere als Karten. @var \Core\Block $b  @var array $d */
$items = array_values(array_filter($d['items'], fn($i) => trim((string) ($i['text'] ?? '')) !== ''));
$single = count($items) === 1;
$person = function (array $it, int $i) use ($b): string {
    if (trim(($it['name'] ?? '') . ($it['role'] ?? '')) === '') return '';
    $pic = !empty($it['image']) ? img((int) $it['image'], '56px', ['ratio' => '1:1', 'alt' => '']) : '';
    return '<figcaption class="quote__by">' . ($pic !== '' ? '<span class="quote__avatar">' . $pic . '</span>' : '')
        . '<span><span class="quote__name"' . $b->edit("items.$i.name") . '>' . e($it['name'] ?? '') . '</span>'
        . (($it['role'] ?? '') !== '' ? '<span class="quote__role"' . $b->edit("items.$i.role") . '>' . e($it['role']) . '</span>' : '') . '</span></figcaption>';
};
?>
<div class="wrap">
  <?= basis_head($b, $single ? 'sec-head--center' : '') ?>
  <?php if ($single): $it = $items[0]; ?>
  <figure class="quote quote--single">
    <?= basis_icon('quote', 'quote__mark') ?>
    <blockquote class="quote__text"><p<?= $b->edit('items.0.text') ?>><?= nl2br(e($it['text']), false) ?></p></blockquote>
    <?= $person($it, 0) ?>
  </figure>
  <?php elseif ($items): ?>
  <div class="quotes cols-<?= count($items) ?>">
    <?php foreach ($items as $i => $it): ?>
    <figure class="quote quote--card">
      <blockquote class="quote__text"><p<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p></blockquote>
      <?= $person($it, $i) ?>
    </figure>
    <?php endforeach; ?>
  </div>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch kein Zitat – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
