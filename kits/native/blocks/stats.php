<?php
/**
 * Kennzahlen im Kit „NativeCSS“ – wie bei fluid (row · cards · split), dazu zählen Zahlen beim Hereinscrollen hoch
 * (.ncss-count-up von NativeCSS, native_count()): „25+“, „98 %“, „12.500“, „€ 40“ zählen, Jahreszahlen und Werte wie „24/7“
 * oder „3,5“ nicht. Die echte Zahl bleibt als Text stehen (Screenreader, ohne Skript, „Bewegung reduzieren“); aus mit
 * „Dezente Animationen“ aus im Design und beim Bearbeiten. Merkmal 'stats:@count' lädt CSS und Skript nur dann.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'row';
$items = array_values((array) ($d['items'] ?? []));
$counting = false;
?>
<div class="wrap<?= $v === 'split' ? ' stats-split' : '' ?>">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <dl class="grid stats stats--<?= e($v) ?>">
    <?php foreach ($items as $i => $it): [$val, $c] = native_count((string) ($it['value'] ?? '')); $counting = $counting || $c; ?>
    <div class="stat<?= $v === 'cards' ? ' card' : '' ?>" data-reveal>
      <dt class="stat__label"<?= $b->edit("items.$i.label") ?>><?= fluid_title((string) $it['label']) ?></dt>
      <dd class="stat__value"<?= $b->edit("items.$i.value") ?>><?= $val ?></dd>
      <?php if (($it['text'] ?? '') !== ''): ?><dd class="stat__text"<?= $b->edit("items.$i.text") ?>><?= fluid_title((string) $it['text']) ?></dd><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </dl>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Kennzahlen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
<?php if ($counting) native_mark('stats:@count');
