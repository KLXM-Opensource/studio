<?php
/**
 * Live-Galerie (Kern-Block, vom Kit überschreibbar: kits/{name}/blocks/live_gallery.php).
 * Bilder einer Sammlung; neue Bilder kommen per Core\Live (Kanal media:collection:{id}) ohne Neuladen dazu.
 * Markup wie „Bildergalerie“ (cms-gallery), Liste mit data-live-list, Einträge mit data-live-id.
 * @var \Core\Block $b  @var array $d
 */
use Core\Live;
use Core\Media;
use Core\MediaBlocks;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$layout = in_array($d['layout'] ?? '', ['grid', 'masonry'], true) ? $d['layout'] : 'grid';
$cols = in_array((string) ($d['columns'] ?? ''), ['2', '3', '4', '5'], true) ? (string) $d['columns'] : '3';
$ratio = array_key_exists((string) ($d['ratio'] ?? ''), MediaBlocks::RATIOS) ? (string) $d['ratio'] : '4:3';
$lightbox = !empty($d['lightbox']) && !is_editing();
$cid = (int) ($d['collection'] ?? 0);
$items = $cid ? Media::all(['collection' => $cid, 'kind' => 'image']) : [];
$order = (string) ($d['order'] ?? 'newest');
if ($order === 'newest') usort($items, fn($a, $c) => (int) $c['id'] <=> (int) $a['id']);
elseif ($order === 'oldest') usort($items, fn($a, $c) => (int) $a['id'] <=> (int) $c['id']);
$limit = max(0, (int) ($d['limit'] ?? 24));
if ($limit) $items = array_slice($items, 0, $limit);
$sizes = [
    '2' => '(min-width: 1280px) 600px, (min-width: 640px) 50vw, 100vw',
    '3' => '(min-width: 1280px) 400px, (min-width: 640px) 50vw, 100vw',
    '4' => '(min-width: 1280px) 300px, (min-width: 860px) 25vw, 50vw',
    '5' => '(min-width: 1280px) 240px, (min-width: 860px) 20vw, 50vw',
][$cols];
?>
<div class="<?= e($wrap) ?> cms-livegal"<?= $cid ? Live::attrs(['media:collection:' . $cid], Live::blockSrc($b)) : '' ?>>
  <?= MediaBlocks::head($b) ?>
  <?= $cid ? Live::controls() : '' ?>
  <ul class="cms-gallery cms-gallery--<?= e($layout) ?> cols-<?= e($cols) ?>" role="list" data-live-list<?= $lightbox ? ' data-cms-lightbox' : '' ?>>
    <?php foreach ($items as $n => $m):
        $cap = !empty($d['captions']) ? trim(Media::title($m)) : '';
        $alt = Media::alt($m);
        $pic = img((int) $m['id'], $sizes, $layout === 'grid' ? ['ratio' => $ratio] : []);
        $cls = 'cms-gallery__link' . ($layout === 'grid' ? ' r-' . str_replace(':', '-', $ratio) : '');
    ?>
    <li class="cms-gallery__item" data-live-id="<?= (int) $m['id'] ?>">
      <figure class="cms-gallery__fig">
        <?php if ($lightbox): ?>
        <a class="<?= e($cls) ?>"<?= MediaBlocks::lightboxLink($m, trim(Media::title($m))) ?>><?= $pic ?><?php if ($alt === ''): ?><span class="sr-only"><?= e(lt('Bild {n} vergrößern', ['n' => $n + 1])) ?></span><?php endif; ?></a>
        <?php else: ?>
        <div class="<?= e($cls) ?>"><?= $pic ?></div>
        <?php endif; ?>
        <?php if ($cap !== ''): ?><figcaption class="cms-gallery__cap"><?= e($cap) ?></figcaption><?php endif; ?>
      </figure>
    </li>
    <?php endforeach; ?>
    <?php if (!$items): ?><li class="cms-livegal__empty"><?= e($cid ? (string) ($d['empty_text'] ?? '') : (is_editing() ? 'Bitte in der Seitenleiste eine Sammlung wählen.' : '')) ?></li><?php endif; ?>
  </ul>
  <?= $lightbox ? MediaBlocks::lightbox() . MediaBlocks::script() : '' ?>
  <?= $cid ? Live::assets() : '' ?>
</div>
