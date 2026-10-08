<?php
/**
 * Text + Bild. auto: wechselt die Seite, wenn der vorige Block auch „Text + Bild“ (abwechselnd) war · right/left ·
 * split: randlos 50/50 (Switcher: nebeneinander, solange beide Hälften ≥ 22 rem breit sind) · overlap: Karte über großem Bild.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'auto';
$ratio = (string) ($d['ratio'] ?? '') ?: '4:3';
$side = $v;
// Abwechselnd („auto“, ebenso „split“): Position in einer Folge gleichartiger Blöcke zählen
$n = 0;
for ($p = $b->prev; $p && $p->type === 'media_text' && ($p->data['variant'] ?? 'auto') === $v; $p = $p->prev) $n++;
if ($v === 'auto') $side = $n % 2 ? 'left' : 'right';
$list = fluid_lines($d['list'] ?? '');
$body = fluid_head($b, 'mt__head')
    . ((trim(strip_tags((string) $d['text'])) !== '' || is_editing()) ? '<div class="prose"' . $b->edit('text', 'rich') . '>' . rich((string) $d['text']) . '</div>' : '')
    . fluid_checks($list)
    . fluid_buttons($b);
?>
<?php if ($v === 'split'): ?>
<div class="mt-split<?= $b->tune('height') === 'screen' ? ' mt-split--screen' : '' ?><?= $n % 2 ? ' mt-split--flip' : '' ?>">
  <?= fluid_image($d['image'] ?? null, '(min-width: 900px) 50vw, 100vw', '', 'mt-split__media', ['eager' => $b->prev === null]) ?>
  <div class="mt-split__text"><div class="mt-split__inner"><?= $body ?></div></div>
</div>
<?php elseif ($v === 'overlap'): ?>
<div class="wrap mt-over">
  <?= fluid_image($d['image'] ?? null, '(min-width: 1280px) 1280px, 100vw', '16:9', 'mt-over__media') ?>
  <div class="mt-over__card"><?= $body ?></div>
</div>
<?php else: ?>
<div class="wrap mt mt--<?= e($side) ?><?= in_array($d['split'] ?? 'even', ['text', 'media', 'mediaxl'], true) ? ' mt--s-' . e($d['split']) : '' ?>">
  <div class="mt__text"><?= $body ?></div>
  <?= fluid_image($d['image'] ?? null, '(min-width: 1080px) 600px, 100vw', $ratio, 'mt__media') ?>
</div>
<?php endif;
