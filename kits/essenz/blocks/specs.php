<?php
/**
 * Datenblatt: Kennwerte als Beschreibungsliste (<dl>), optional in Gruppen und mit Bild daneben.
 * Bezeichnungen in Beschriftungsschrift, Werte mit Tabellenziffern – wie das Typenschild eines Geräts.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'image' ? 'image' : 'table';
$groups = [];
foreach ((array) ($d['items'] ?? []) as $i => $it) {
    if (trim((string) ($it['label'] ?? '')) === '' && !is_editing()) continue;
    $groups[trim((string) ($it['group'] ?? ''))][] = [$i, $it];
}
$pic = $v === 'image' ? essenz_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1080px) 480px, 100vw', (string) ($d['ratio'] ?: '4:5'), 'specs__media') : '';
$note = trim((string) ($d['note'] ?? ''));
?>
<div class="wrap">
  <?= essenz_head($b) ?>
  <div class="specs specs--<?= e($v) ?><?= $pic !== '' ? ' specs--has-media' : '' ?>">
    <?= $pic ?>
    <div class="specs__sheet">
      <?php foreach ($groups as $g => $rows): ?>
      <?php if ($g !== ''): ?><h3 class="specs__group label"><?= essenz_title((string) $g) ?></h3><?php endif; ?>
      <dl class="specs__list">
        <?php foreach ($rows as [$i, $it]): ?><div class="specs__row"><dt<?= $b->edit("items.$i.label") ?>><?= essenz_title((string) ($it['label'] ?? '')) ?></dt><dd<?= $b->edit("items.$i.value") ?>><?= e((string) ($it['value'] ?? '')) ?></dd></div><?php endforeach; ?>
      </dl>
      <?php endforeach; ?>
      <?php if (!$groups && is_editing()): ?><p class="empty-hint">Noch keine Kennwerte – in der Seitenleiste hinzufügen.</p><?php endif; ?>
      <?php if ($note !== '' || is_editing()): ?><p class="specs__note"<?= $b->edit('note') ?>><?= essenz_title($note) ?></p><?php endif; ?>
    </div>
  </div>
</div>
