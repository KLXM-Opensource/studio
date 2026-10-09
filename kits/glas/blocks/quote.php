<?php
/** Zitat / Stimmen: single (große Glasfläche mit leuchtendem Anführungszeichen) · grid (Glaskarten). @var \Core\Block $b  @var array $d */
$v = $b->variant() === 'grid' ? 'grid' : 'single';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['text'] ?? '')) !== '' || is_editing()));
if ($v === 'single') $items = array_slice($items, 0, 1);
$who = function (array $it, int $i) use ($b): string {
    $name = trim((string) ($it['name'] ?? ''));
    $role = trim((string) ($it['role'] ?? ''));
    if ($name === '' && $role === '' && empty($it['image'])) return '';
    $img = !empty($it['image']) ? img((int) $it['image'], '64px', ['ratio' => '1:1', 'alt' => '', 'class' => 'q__img']) : '';
    return '<figcaption class="q__who">' . $img . '<span><span class="q__name"' . $b->edit("items.$i.name") . '>' . glas_title($name) . '</span>'
        . ($role !== '' ? '<span class="q__role"' . $b->edit("items.$i.role") . '>' . glas_title($role) . '</span>' : '') . '</span></figcaption>';
};
?>
<div class="wrap">
  <?= glas_head($b) ?>
  <?php if ($v === 'single' && $items): $it = $items[0]; ?>
  <figure class="q q--single glass glass--strong">
    <blockquote class="q__text"<?= $b->edit('items.0.text') ?>><p><?= nl2br(glas_title((string) $it['text']), false) ?></p></blockquote>
    <?= $who($it, 0) ?>
  </figure>
  <?php elseif ($items): ?>
  <ul class="grid min-m qs" role="list">
    <?php foreach ($items as $i => $it): ?>
    <li class="card" data-reveal><figure class="q q--card">
      <blockquote class="q__text"<?= $b->edit("items.$i.text") ?>><p><?= nl2br(glas_title((string) $it['text']), false) ?></p></blockquote>
      <?= $who($it, $i) ?>
    </figure></li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch kein Zitat – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
