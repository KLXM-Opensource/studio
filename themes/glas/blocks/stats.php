<?php
/**
 * Kennzahlen: rings (je Zahl ein Glasring – Bahn über 270°, optional Füllstand 0–100 mit Verlauf in den Feldfarben) ·
 * row (große Zahlen auf Glaskarten, optional ein schmaler Pegel). Die Grafik ist ein Inline-SVG mit Attributen (CSP-fest,
 * keine Inline-Styles); die Zahl selbst ist Text. Mit „Sanfte Bewegung“ füllt sich der Ring beim Erscheinen einmal.
 * Für Skalen mit Einheit, Höchstwert und Hochzählen gibt es den Kern-Block „Kennzahlen mit Skala“ (dials) – im Kit-Stil.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'row' ? 'row' : 'rings';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['value'] ?? '')) !== '' || is_editing()));
$level = function (array $it): ?float {
    $l = $it['level'] ?? null;
    return is_numeric($l) ? max(0, min(100, (float) $l)) : null;
};
$gid = e($b->domId()) . '-grad';
?>
<div class="wrap">
  <?= glas_head($b) ?>
  <?php if ($items): ?>
  <ul class="stats stats--<?= e($v) ?>" role="list">
    <?php foreach ($items as $i => $it): $l = $level($it); ?>
    <li class="stat glass" data-reveal>
      <?php if ($v === 'rings'): ?>
      <div class="ring">
        <svg class="ring__svg" viewBox="0 0 120 120" aria-hidden="true" focusable="false">
          <?php if ($i === 0): ?><defs><linearGradient id="<?= $gid ?>" x1="0" y1="0" x2="1" y2="1"><stop class="ring__s1" offset="0"/><stop class="ring__s2" offset="1"/></linearGradient></defs><?php endif; ?>
          <circle class="ring__track" cx="60" cy="60" r="50" pathLength="100"/>
          <?php if ($l !== null && $l > 0): ?><circle class="ring__val" cx="60" cy="60" r="50" pathLength="100" stroke="url(#<?= $gid ?>)" stroke-dasharray="<?= e(rtrim(rtrim(number_format($l * .75, 2, '.', ''), '0'), '.')) ?> 100"/><?php endif; ?>
        </svg>
        <span class="ring__value"<?= $b->edit("items.$i.value") ?>><?= e((string) $it['value']) ?></span>
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
