<?php
/**
 * Vorher/Nachher im Kit „NativeCSS“ mit <ncss-compare>: echter Regler (input type=range) über beiden Bildern – Tastatur: Tab,
 * Pfeiltasten, Pos1/Ende; ohne JavaScript ein statischer 50/50-Vergleich. „Vorher“ liegt immer links.
 * Felder (Block „Vorher/Nachher“ von fluid, Core\Blocks\Hero::fields('compare')): image_before, image_after, before_label,
 * after_label, ratio, caption, dazu Dachzeile/Überschrift/Einleitung. Seitenverhältnis per Klasse nv-ar-… (keine style-Attribute). CSS/JS: css/ncss-compare.css, js/ncss-compare.js.
 * @var \Core\Block $b  @var array $d
 */
native_mark('@nv-compare');
$ratio = in_array($d['ratio'] ?? '', ['16:9', '3:2', '4:3', '1:1', '4:5', '3:4'], true) ? (string) $d['ratio'] : '16:9';
$sizes = '(min-width: 1080px) 1000px, 100vw';
// Klasse am <picture> (direktes Kind von <ncss-compare>), das Bild darin füllt es (css/ncss-compare.css)
$before = !empty($d['image_before']) ? native_outer_class(img((int) $d['image_before'], $sizes, ['ratio' => $ratio]), 'ncss-compare-before') : '';
$after = !empty($d['image_after']) ? native_outer_class(img((int) $d['image_after'], $sizes, ['ratio' => $ratio]), 'ncss-compare-after') : '';
$lb = trim((string) ($d['before_label'] ?? '')) ?: lt('Vorher');
$la = trim((string) ($d['after_label'] ?? '')) ?: lt('Nachher');
$caption = trim((string) ($d['caption'] ?? ''));
?>
<div class="wrap nv-compare">
  <?= fluid_head($b) ?>
  <?php if ($before !== '' && $after !== ''): ?>
  <figure class="nv-compare__fig">
    <ncss-compare class="nv-ar-<?= e(str_replace(':', '-', $ratio)) ?>">
      <?= $before ?>
      <?= $after ?>
      <span class="ncss-compare-label" aria-hidden="true"><?= e($lb) ?></span>
      <span class="ncss-compare-label ncss-compare-label--end" aria-hidden="true"><?= e($la) ?></span>
      <input class="ncss-compare-range" type="range" min="0" max="100" value="50" aria-label="<?= e(lt('Trennlinie verschieben: links {a}, rechts {b}', ['a' => $lb, 'b' => $la])) ?>">
    </ncss-compare>
    <?php if ($caption !== '' || is_editing()): ?><figcaption class="video__cap"<?= $b->edit('caption') ?>><?= fluid_title($caption) ?></figcaption><?php endif; ?>
  </figure>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Bilder – in der Seitenleiste „Bild vorher“ und „Bild nachher“ wählen.</p><?php endif; ?>
</div>
