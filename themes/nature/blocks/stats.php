<?php
/**
 * Kennzahlen: dials („Jahresringe“ – Samenpunkte als Skala, Bahn über 270°, optional Füllstand 0–100, innen feine
 * Ringe wie im Holz) · row (große Zahlen in einer Reihe, optional schmaler Pegel). Die Grafik ist ein Inline-SVG mit Attributen (CSP-fest, keine
 * Inline-Styles); die Zahl selbst ist Text. Mit „Bewegung“ füllt sich die Skala beim Erscheinen einmal.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'row' ? 'row' : 'dials';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['value'] ?? '')) !== '' || is_editing()));
$level = function (array $it): ?float {
    $l = $it['level'] ?? null;
    return is_numeric($l) ? max(0, min(100, (float) $l)) : null;
};
?>
<div class="wrap">
  <?= nature_head($b) ?>
  <?php if ($items): ?>
  <ul class="stats stats--<?= e($v) ?>" role="list">
    <?php foreach ($items as $i => $it): $l = $level($it); ?>
    <li class="stat" data-reveal>
      <?php if ($v === 'dials'): ?>
      <div class="dial">
        <svg class="dial__svg" viewBox="0 0 120 120" aria-hidden="true" focusable="false">
          <circle class="dial__ticks" cx="60" cy="60" r="55" pathLength="100"/>
          <circle class="dial__track" cx="60" cy="60" r="46" pathLength="100"/>
          <?php if ($l !== null && $l > 0): ?><circle class="dial__val" cx="60" cy="60" r="46" pathLength="100" stroke-dasharray="<?= e(rtrim(rtrim(number_format($l * .75, 2, '.', ''), '0'), '.')) ?> 100"/><?php endif; ?>
          <circle class="dial__hub" cx="60" cy="60" r="36"/>
          <path class="dial__rings" d="M60 30.5a29.5 29 0 1 1-.1 0M61 36a24 23.5 0 1 1-.1 0M59.5 41.5a18.5 18 0 1 1-.1 0"/>
        </svg>
        <span class="dial__value"<?= $b->edit("items.$i.value") ?>><?= e((string) $it['value']) ?></span>
      </div>
      <?php else: ?>
      <span class="stat__value"<?= $b->edit("items.$i.value") ?>><?= e((string) $it['value']) ?></span>
      <?php if ($l !== null): ?><svg class="stat__bar" viewBox="0 0 100 4" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect class="stat__track" width="100" height="4" rx="2"/><rect class="stat__fill" width="<?= e((string) round($l, 1)) ?>" height="4" rx="2"/></svg><?php endif; ?>
      <?php endif; ?>
      <p class="stat__label"<?= $b->edit("items.$i.label") ?>><?= e((string) ($it['label'] ?? '')) ?></p>
      <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="stat__text"<?= $b->edit("items.$i.text") ?>><?= e($it['text']) ?></p><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Kennzahlen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
