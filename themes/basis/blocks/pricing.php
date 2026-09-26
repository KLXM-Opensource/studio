<?php /** Leistungspakete / Preise: 2–4 Karten, eine optional hervorgehoben. @var \Core\Block $b  @var array $d */
$items = array_values(array_filter((array) $d['items'], fn($i) => trim((string) ($i['name'] ?? '')) !== ''));
?>
<div class="wrap">
  <?= basis_head($b, 'sec-head--center') ?>
  <?php if ($items): ?>
  <ul class="plans cols-<?= max(2, min(4, count($items))) ?>" role="list">
    <?php foreach ($items as $i => $it):
        $hl = !empty($it['highlight']);
        $list = array_values(array_filter(array_map('trim', preg_split('~\R~', (string) ($it['features'] ?? ''))), fn($l) => $l !== ''));
        $label = trim((string) ($it['button_label'] ?? ''));
        $link = trim((string) ($it['button_link'] ?? ''));
    ?>
    <li class="plan card<?= $hl ? ' plan--hl' : '' ?>">
      <div class="plan__top">
        <h3 class="plan__name"<?= $b->edit("items.$i.name") ?>><?= e($it['name']) ?></h3>
        <?php if (trim((string) ($it['badge'] ?? '')) !== ''): ?><p class="plan__badge"<?= $b->edit("items.$i.badge") ?>><?= e($it['badge']) ?></p><?php endif; ?>
      </div>
      <?php if (trim((string) ($it['price'] ?? '')) !== ''): ?>
      <p class="plan__price"><span class="plan__amount"<?= $b->edit("items.$i.price") ?>><?= e($it['price']) ?></span>
        <?php if (trim((string) ($it['period'] ?? '')) !== ''): ?><span class="plan__period"<?= $b->edit("items.$i.period") ?>><?= e($it['period']) ?></span><?php endif; ?></p>
      <?php endif; ?>
      <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="plan__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif; ?>
      <?php if ($list): ?>
      <ul class="checks plan__list">
        <?php foreach ($list as $li): ?><li><?= basis_icon('check', 'checks__icon') ?><span><?= e($li) ?></span></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ($label !== '' && $link !== ''): ?>
      <a class="btn <?= $hl ? 'btn--primary' : 'btn--secondary' ?> plan__btn" <?= basis_link_attrs($link) ?>><span<?= $b->edit("items.$i.button_label") ?>><?= e($label) ?></span><span class="sr-only">: <?= e($it['name']) ?></span></a>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if (trim((string) $d['note']) !== ''): ?><p class="plans__note"<?= $b->edit('note') ?>><?= e($d['note']) ?></p><?php endif; ?>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Pakete – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
