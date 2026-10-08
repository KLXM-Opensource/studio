<?php
/**
 * Zitat / Stimmen: single (Pull-Quote, große Schrift in Container-Einheiten) · grid (Mauerwerk mit CSS-Spalten,
 * column-width – keine Spaltenzahl) · reel (wischbares Band, scroll-snap; js/reel.js ergänzt Pfeile) ·
 * marquee (Laufband wie bei Logos: Liste doppelt, zweite Hälfte aria-hidden; hält bei Maus/Fokus; ohne Bewegung umbrechend).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'single';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['text'] ?? '')) !== ''));
if ($v === 'single' && count($items) > 1 && !is_editing()) $items = [$items[0]];
$id = $b->domId() . '-reel';
$person = function (array $it, int $i) use ($b): string {
    if (trim(($it['name'] ?? '') . ($it['role'] ?? '')) === '') return '';
    $pic = !empty($it['image']) ? img((int) $it['image'], '56px', ['ratio' => '1:1', 'alt' => '']) : '';
    return '<figcaption class="quote__by">' . ($pic !== '' ? '<span class="quote__avatar">' . $pic . '</span>' : '')
        . '<span><span class="quote__name"' . $b->edit("items.$i.name") . '>' . e($it['name'] ?? '') . '</span>'
        . (($it['role'] ?? '') !== '' ? '<span class="quote__role"' . $b->edit("items.$i.role") . '>' . e($it['role']) . '</span>' : '') . '</span></figcaption>';
};
?>
<div class="wrap">
  <?php if ($v === 'reel'): ?>
  <div class="reel-head">
    <?= fluid_head($b) ?>
    <div class="reel-ctrl" data-reel-ctrl="<?= e($id) ?>" hidden>
      <button type="button" class="reel-btn" data-reel-prev aria-controls="<?= e($id) ?>"><?= icon('arrow-right', ['class' => 'flip-x']) ?><span class="sr-only"><?= e(lt('Zurück')) ?></span></button>
      <button type="button" class="reel-btn" data-reel-next aria-controls="<?= e($id) ?>"><?= icon('arrow-right') ?><span class="sr-only"><?= e(lt('Weiter')) ?></span></button>
    </div>
  </div>
  <?php else: ?><?= fluid_head($b, $v === 'single' ? 'sec-head--center' : '') ?><?php endif; ?>
  <?php if ($v === 'single' && $items): $it = $items[0]; ?>
  <figure class="pull">
    <?= icon('quotes', ['class' => 'pull__mark']) ?>
    <blockquote class="pull__text"><p<?= $b->edit('items.0.text') ?>><?= nl2br(e($it['text']), false) ?></p></blockquote>
    <?= $person($it, 0) ?>
  </figure>
  <?php elseif ($items && $v === 'marquee' && !is_editing()): ?>
  <div class="marquee quotes-marquee" role="region" aria-label="<?= e(trim((string) ($d['title'] ?? '')) ?: lt('Stimmen')) ?>" tabindex="0">
    <?php foreach ([false, true] as $dup): ?>
    <ul class="marquee__track quotes--marquee<?= $dup ? ' marquee__track--dup' : '' ?>" role="list"<?= $dup ? ' aria-hidden="true"' : '' ?>>
      <?php foreach ($items as $i => $it): ?>
      <li class="quote card"><figure>
        <?= icon('quotes', ['class' => 'quote__mark']) ?>
        <blockquote class="quote__text"><p><?= nl2br(e($it['text']), false) ?></p></blockquote>
        <?= $dup ? preg_replace('~ data-cms-edit="[^"]*"~', '', $person($it, $i)) : $person($it, $i) ?>
      </figure></li>
      <?php endforeach; ?>
    </ul>
    <?php endforeach; ?>
  </div>
  <?php elseif ($items): ?>
  <ul class="<?= $v === 'reel' ? 'reel quotes--reel' : 'quotes' ?>" role="list"<?= $v === 'reel' ? ' id="' . e($id) . '" tabindex="0" aria-label="' . e(trim((string) $d['title']) ?: lt('Stimmen')) . '"' : '' ?>>
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
