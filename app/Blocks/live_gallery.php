<?php
/**
 * Live-Galerie (Kern-Block, vom Kit überschreibbar: kits/{name}/blocks/live_gallery.php).
 * Bilder einer Sammlung (Kanal media:collection:{id}, sofort) oder einzeln ausgewählt (Kanal page:{id}, beim Veröffentlichen);
 * neue Bilder kommen per Core\Live ohne Neuladen dazu.
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
$manual = ($d['source'] ?? 'collection') === 'manual';
$cid = $manual ? 0 : (int) ($d['collection'] ?? 0);
$order = (string) ($d['order'] ?? 'newest');
// Einträge: ['m' => Medium, 'caption' => Bildunterschrift, 'path' => Pfad fürs Bearbeiten (nur einzelne Bilder)]
if ($manual) {
    $items = MediaBlocks::images(['source' => 'manual'] + $d);   // Reihenfolge der Liste – zuletzt hinzugefügt = unten
    if ($order === 'newest') $items = array_reverse($items);
} else {
    $items = array_map(fn($m) => ['m' => $m, 'caption' => trim(Media::title($m)), 'path' => null], $cid ? Media::all(['collection' => $cid, 'kind' => 'image']) : []);
    if ($order === 'newest') usort($items, fn($a, $c) => (int) $c['m']['id'] <=> (int) $a['m']['id']);
    elseif ($order === 'oldest') usort($items, fn($a, $c) => (int) $a['m']['id'] <=> (int) $c['m']['id']);
}
// Kanal: Sammlung (Änderungen sofort) bzw. Seite (beim Veröffentlichen)
$page = app()->currentPage;
$channels = $manual ? (!empty($page['id']) ? ['page:' . (int) $page['id']] : []) : ($cid ? ['media:collection:' . $cid] : []);
$limit = max(0, (int) ($d['limit'] ?? 24));
if ($limit) $items = array_slice($items, 0, $limit);
$sizes = [
    '2' => '(min-width: 1280px) 600px, (min-width: 640px) 50vw, 100vw',
    '3' => '(min-width: 1280px) 400px, (min-width: 640px) 50vw, 100vw',
    '4' => '(min-width: 1280px) 300px, (min-width: 860px) 25vw, 50vw',
    '5' => '(min-width: 1280px) 240px, (min-width: 860px) 20vw, 50vw',
][$cols];
?>
<div class="<?= e($wrap) ?> cms-livegal"<?= Live::attrs($channels, Live::blockSrc($b)) ?>>
  <?= MediaBlocks::head($b) ?>
  <?= $channels ? Live::controls() : '' ?>
  <ul class="cms-gallery cms-gallery--<?= e($layout) ?> cols-<?= e($cols) ?>" role="list" data-live-list<?= $lightbox ? ' data-cms-lightbox' : '' ?>>
    <?php foreach ($items as $n => $it):
        $m = $it['m'];
        $cap = !empty($d['captions']) ? $it['caption'] : '';
        $alt = Media::alt($m);
        $pic = img((int) $m['id'], $sizes, $layout === 'grid' ? ['ratio' => $ratio] : []);
        $cls = 'cms-gallery__link' . ($layout === 'grid' ? ' r-' . str_replace(':', '-', $ratio) : '');
    ?>
    <li class="cms-gallery__item" data-live-id="<?= (int) $m['id'] ?>">
      <figure class="cms-gallery__fig">
        <?php if ($lightbox): ?>
        <a class="<?= e($cls) ?>"<?= MediaBlocks::lightboxLink($m, $it['caption']) ?>><?= $pic ?><?php if ($alt === ''): ?><span class="sr-only"><?= e(lt('Bild {n} vergrößern', ['n' => $n + 1])) ?></span><?php endif; ?></a>
        <?php else: ?>
        <div class="<?= e($cls) ?>"><?= $pic ?></div>
        <?php endif; ?>
        <?php if ($cap !== ''): ?><figcaption class="cms-gallery__cap"<?= $it['path'] ? $b->edit($it['path'] . '.caption') : '' ?>><?= emphasis($cap) ?></figcaption><?php endif; ?>
      </figure>
    </li>
    <?php endforeach; ?>
    <?php if (!$items): ?><li class="cms-livegal__empty"><?= e(is_editing() ? ($manual ? 'Noch keine Bilder – in der Seitenleiste Bilder auswählen.' : ($cid ? (string) ($d['empty_text'] ?? '') : 'Bitte in der Seitenleiste eine Sammlung wählen.')) : (string) ($d['empty_text'] ?? '')) ?></li><?php endif; ?>
  </ul>
  <?= $lightbox ? MediaBlocks::lightbox() . MediaBlocks::script() : '' ?>
  <?= $channels ? Live::assets() : '' ?>
</div>
