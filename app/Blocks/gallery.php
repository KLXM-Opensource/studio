<?php
/**
 * Bildergalerie (Kern-Block, vom Theme überschreibbar: kits/{name}/blocks/gallery.php).
 * Raster (einheitliches Format mit Zuschnitt), Mosaik (CSS-Spalten) oder bündige Zeilen; Lightbox über js/media.mjs –
 * ohne JavaScript führen die Bilder als normale Links zur großen Fassung.
 * @var \Core\Block $b  @var array $d
 */
use Core\MediaBlocks;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$layout = in_array($d['layout'] ?? '', ['grid', 'masonry', 'justified'], true) ? $d['layout'] : 'grid';
$cols = in_array((string) ($d['columns'] ?? ''), ['2', '3', '4', '5'], true) ? (string) $d['columns'] : '3';
$ratio = array_key_exists((string) ($d['ratio'] ?? ''), MediaBlocks::RATIOS) ? (string) $d['ratio'] : '4:3';
$lightbox = !empty($d['lightbox']) && !is_editing();
$items = MediaBlocks::images($d);
$sizes = [
    '2' => '(min-width: 1280px) 600px, (min-width: 640px) 50vw, 100vw',
    '3' => '(min-width: 1280px) 400px, (min-width: 640px) 50vw, 100vw',
    '4' => '(min-width: 1280px) 300px, (min-width: 860px) 25vw, 50vw',
    '5' => '(min-width: 1280px) 240px, (min-width: 860px) 20vw, 50vw',
][$cols];
if ($layout === 'justified') $sizes = '(min-width: 860px) 40vw, 100vw';
?>
<div class="<?= e($wrap) ?>">
  <?= MediaBlocks::head($b) ?>
  <?php if ($items): ?>
  <ul class="cms-gallery cms-gallery--<?= e($layout) ?> cols-<?= e($cols) ?>" role="list"<?= $lightbox ? ' data-cms-lightbox' : '' ?>>
    <?php foreach ($items as $n => $it):
        $m = $it['m'];
        $pic = img((int) $m['id'], $sizes, $layout === 'grid' ? ['ratio' => $ratio] : []);
        $cap = !empty($d['captions']) ? $it['caption'] : '';
        $alt = \Core\Media::alt($m);
    ?>
    <li class="cms-gallery__item<?= $layout === 'justified' ? ' ' . MediaBlocks::aspectClass($m) : '' ?>">
      <figure class="cms-gallery__fig">
        <?php if ($lightbox): ?>
        <a class="cms-gallery__link<?= $layout === 'grid' ? ' r-' . e(str_replace(':', '-', $ratio)) : '' ?>"<?= MediaBlocks::lightboxLink($m, $it['caption']) ?>><?= $pic ?><?php if ($alt === ''): ?><span class="sr-only"><?= e(lt('Bild {n} vergrößern', ['n' => $n + 1])) ?></span><?php endif; ?></a>
        <?php else: ?>
        <div class="cms-gallery__link<?= $layout === 'grid' ? ' r-' . e(str_replace(':', '-', $ratio)) : '' ?>"><?= $pic ?></div>
        <?php endif; ?>
        <?php if ($cap !== ''): ?><figcaption class="cms-gallery__cap"<?= $it['path'] ? $b->edit($it['path'] . '.caption') : '' ?>><?= e($cap) ?></figcaption><?php endif; ?>
      </figure>
    </li>
    <?php endforeach; ?>
  </ul>
  <?= $lightbox ? MediaBlocks::lightbox() . MediaBlocks::script() : '' ?>
  <?php elseif (is_editing()): ?>
  <p class="cms-empty-hint">Noch keine Bilder – in der Seitenleiste Bilder oder eine Sammlung wählen.</p>
  <?php endif; ?>
</div>
