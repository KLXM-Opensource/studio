<?php
/**
 * Ablauf: numbers (Raster, auto-fit) · timeline (senkrechte Linie; ab genug Containerbreite Datum links) ·
 * process (Schritte mit Pfeilen – nebeneinander, wenn Platz ist, sonst untereinander mit Pfeil nach unten).
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['numbers', 'timeline', 'process'], true) ? $b->variant() : 'numbers';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== ''));
$tag = fluid_htag($d);
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <ol class="steps steps--<?= e($v) ?><?= $v === 'numbers' ? ' grid min-s' : '' ?>" role="list">
    <?php foreach ($items as $i => $it): ?>
    <li class="step" data-reveal>
      <span class="step__num" aria-hidden="true"><?= !empty($it['icon']) ? icon((string) $it['icon']) : str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
      <div class="step__body">
        <?php if (trim((string) ($it['meta'] ?? '')) !== ''): ?><p class="step__meta"<?= $b->edit("items.$i.meta") ?>><?= e($it['meta']) ?></p><?php endif; ?>
        <<?= $tag ?> class="step__title"><span class="sr-only"><?= e(lt('Schritt {nr}:', ['nr' => $i + 1])) ?> </span><span<?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></span></<?= $tag ?>>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="step__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Schritte – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
