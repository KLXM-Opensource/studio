<?php
/**
 * Karten – Raster ohne feste Spaltenzahl (auto-fill in blocks.css). Listenfelder bearbeiten: Pfad „items.{i}.feld“.
 * Die ganze Karte ist klickbar (::after am Link), bleibt aber ein einzelner, sinnvoll beschrifteter Link.
 * icon(): Symbol aus dem Sprite des Cores (Phosphor), dekorativ = aria-hidden.
 * @var \Core\Block $b  @var array $d
 */
$h = filled($d['title']) ? 'h3' : 'h2';   // Überschriften-Hierarchie ohne Sprünge
?>
<div class="wrap">
  <?= starter_head($b) ?>
  <ul class="cards" role="list">
    <?php foreach ($d['items'] as $i => $it): $href = trim((string) ($it['link'] ?? '')) !== '' ? starter_link($it['link']) : ''; ?>
    <li class="card">
      <?php if (!empty($it['image'])): ?><div class="media r-3-2"><?= img((int) $it['image'], '(min-width: 1100px) 360px, 100vw', ['ratio' => '3:2', 'alt' => '']) ?></div>
      <?php elseif (filled($it['icon'] ?? '')): ?><span class="card__icon"><?= icon($it['icon']) ?></span><?php endif; ?>
      <<?= $h ?> class="card__title"><?php if ($href !== ''): ?><a href="<?= e($href) ?>"<?= ext_attrs($href) ?><?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></span><?php endif; ?></<?= $h ?>>
      <?php if (filled($it['text'] ?? '')): ?><p<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint">Noch keine Karten – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
