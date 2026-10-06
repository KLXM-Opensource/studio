<?php
/**
 * Fotostrecke – Bilder und Videos (MP4 aus der Mediathek, YouTube/Vimeo per Zwei-Klick) in fünf Darstellungen, ohne Breakpoints:
 *   masonry    CSS-Spalten (columns: N Mindestbreite) – Originalformate, Spalten füllen sich von oben
 *   justified  bündige Zeilen: Flex-Umbruch, flex-grow = Seitenverhältnis (Klassen ar-5 … ar-24), gleiche Zeilenhöhe
 *   grid       gleichmäßiges Raster mit Zuschnitt (Format wählbar), höchstens N Spalten (auto-fill + max())
 *   single     große Einzelbilder untereinander (Originalformat, viel Luft)
 *   strip      waagerechtes Band mit scroll-snap, gleiche Höhe; js/reel.js ergänzt Zurück/Weiter
 * Bildabstand, Ecken, Bildunterschriften, Passepartout, Schwarzweiß, Lightbox-Hintergrund: Design → Bilder & Galerien.
 * Lightbox: js/lightbox.js (nur auf Seiten mit diesem Block, theme.php → conditional_css); ohne JavaScript Links zur großen Fassung.
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['masonry', 'justified', 'grid', 'single', 'strip'], true) ? $b->variant() : 'masonry';
$cols = in_array((string) ($d['columns'] ?? ''), ['2', '3', '4'], true) ? (string) $d['columns'] : 'auto';
$ratio = $v === 'grid' ? (string) ($d['ratio'] ?? '3:2') : '';
$rh = in_array($d['row_height'] ?? '', ['s', 'm', 'l'], true) ? $d['row_height'] : 'm';
$lightbox = !empty($d['lightbox']) && !is_editing();
$captions = !empty($d['captions']);
$items = foto_images($d);
$sizes = match ($v) {
    'single' => '(min-width: 1500px) 1400px, 100vw',
    'justified' => '(min-width: 900px) 50vw, 100vw',
    'strip' => '(min-width: 900px) 45vw, 85vw',
    default => match ($cols) {
        '2' => '(min-width: 700px) 50vw, 100vw',
        '4' => '(min-width: 1100px) 25vw, (min-width: 600px) 50vw, 100vw',
        default => '(min-width: 1100px) 33vw, (min-width: 600px) 50vw, 100vw',
    },
};
$id = $b->domId() . '-strip';
$label = trim(strip_emphasis((string) ($d['title'] ?? ''))) ?: lt('Fotostrecke');
$head = foto_head($b);
$collection = ($d['source'] ?? 'manual') === 'collection';
// Bearbeiten-Modus: Ablagefläche (Drag & Drop, Auswahl mehrerer Dateien) und Werkzeuge je Bild (js/editor-photos.js)
$zone = $collection ? foto_drop_zone('images', 'collection', ['collection' => (int) ($d['collection'] ?? 0), 'accept' => 'visual']) : foto_drop_zone('images', 'list', ['allowCollection' => true, 'accept' => 'visual']);
?>
<div class="<?= e(foto_gw($d)) ?>">
<?php if ($head !== '' || $v === 'strip'): ?>
<div class="pg-head<?= $v === 'strip' ? ' pg-head--strip' : '' ?>">
  <?= $head ?>
  <?php if ($v === 'strip' && $items): ?>
  <div class="reel-ctrl pg-ctrl" data-reel-ctrl="<?= e($id) ?>" hidden>
    <button type="button" class="reel-btn" data-reel-prev aria-controls="<?= e($id) ?>"><?= icon('arrow-right', ['class' => 'flip-x']) ?><span class="sr-only"><?= e(lt('Zurück')) ?></span></button>
    <button type="button" class="reel-btn" data-reel-next aria-controls="<?= e($id) ?>"><?= icon('arrow-right') ?><span class="sr-only"><?= e(lt('Weiter')) ?></span></button>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
  <?php if ($items): ?>
  <ul class="pg pg--<?= e($v) ?> cols-<?= e($cols) ?> rh-<?= e($rh) ?>" role="list"<?= $lightbox ? ' data-lb-group' : '' ?><?= $v === 'strip' ? ' id="' . e($id) . '" tabindex="0" aria-label="' . e($label) . '"' : '' ?>>
    <?php foreach ($items as $n => $it):
        $m = $it['m'];
        $cap = $captions ? $it['caption'] : '';
        $capEdit = $captions && $it['path'] ? $b->edit($it['path'] . '.caption') : '';
        // YouTube/Vimeo: Zwei-Klick-Lösung des Kerns (Bild des Eintrags dient als eigenes Vorschaubild); sonst Bild oder Video
        $photo = $it['url'] !== ''
            ? foto_embed($it['url'], ['caption' => $cap, 'capEdit' => $capEdit, 'poster' => $m && str_starts_with((string) $m['mime'], 'image/') ? (int) $m['id'] : null])
            : foto_photo($m, [
                'sizes' => $sizes, 'ratio' => $ratio, 'caption' => $cap, 'lightbox' => $lightbox, 'n' => $n + 1, 'capEdit' => $capEdit,
                'path' => $it['path'] ? $it['path'] . '.image' : null, 'eager' => $b->prev === null && $n < 2,
                'extra' => !$collection ? foto_item_tools('images', (int) $it['i'], $n, count($items)) : '',   // im Bearbeiten-Modus (ohne Lightbox) im Bildrahmen
            ]);
        $ar = $m && $it['url'] === '' ? \Core\MediaBlocks::aspectClass($m) : 'ar-18';
    ?>
    <li class="pg__item<?= $it['video'] ? ' pg__item--video' : '' ?><?= in_array($v, ['justified', 'strip'], true) ? ' ' . e($ar) : '' ?>"<?= in_array($v, ['grid', 'single'], true) ? ' data-reveal' : '' ?>><?= $photo ?><?= !$collection && $it['url'] !== '' ? foto_item_tools('images', (int) $it['i'], $n, count($items)) : '' ?></li>
    <?php endforeach; ?>
  </ul>
  <?= $lightbox ? foto_lightbox() : '' ?>
  <?php endif; ?>
  <?= $zone ?>
</div>
