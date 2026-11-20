<?php
/**
 * Zitat / Stimmen im Kit „NativeCSS“: „Groß“ als hervorgehobene Kundenstimme (.ncss-testimonial--featured), „Raster“ als Raster
 * gleich hoher Karten mit bündiger Personenzeile (.ncss-testimonial-grid) – figure/blockquote/figcaption, der Name als Text
 * (nicht <cite>). „Wischbares Band“ und „Laufband“ kommen aus dem Kit fluid.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'single';
if (!in_array($v, ['single', 'grid'], true)) {
    require native_parent_view('quote');
    return;
}
native_mark('quote:@testimonial');
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['text'] ?? '')) !== ''));
if ($v === 'single' && count($items) > 1 && !is_editing()) $items = [$items[0]];
$fig = function (array $it, int $i, string $mod) use ($b): string {
    $pic = !empty($it['image']) ? img((int) $it['image'], '48px', ['ratio' => '1:1', 'alt' => '', 'class' => 'nv-quote__avatar']) : '';
    $by = trim(($it['name'] ?? '') . ($it['role'] ?? '')) !== ''
        ? '<figcaption class="ncss-testimonial-author">' . $pic . '<span class="ncss-testimonial-person">'
            . '<span class="ncss-testimonial-name"' . $b->edit("items.$i.name") . '>' . e((string) ($it['name'] ?? '')) . '</span>'
            . (($it['role'] ?? '') !== '' ? '<span class="ncss-testimonial-role"' . $b->edit("items.$i.role") . '>' . e((string) $it['role']) . '</span>' : '')
            . '</span></figcaption>'
        : '';
    return '<figure class="ncss-testimonial' . $mod . '"><blockquote class="ncss-testimonial-quote"><p' . $b->edit("items.$i.text") . '>'
        . nl2br(e((string) $it['text']), false) . '</p></blockquote>' . $by . '</figure>';
};
?>
<div class="wrap nv-quotes">
  <?= fluid_head($b, $v === 'single' ? 'sec-head--center' : '') ?>
  <?php if ($items && $v === 'single'): ?>
  <?= $fig($items[0], 0, ' ncss-testimonial--featured') ?>
  <?php elseif ($items): ?>
  <ul class="ncss-testimonial-grid" role="list">
    <?php foreach ($items as $i => $it): ?><li data-reveal><?= $fig($it, $i, '') ?></li><?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch kein Zitat – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
