<?php
/**
 * Karten im Kit „NativeCSS“ (.ncss-card mit Rahmen, Radius, Schatten; die ganze Karte ist klickbar über .ncss-card-link):
 *   image       Bild oben
 *   overlay     Text auf dem Bild
 *   horizontal  quer, sobald die Karte breit genug ist (.ncss-card--horizontal im .ncss-card-container)
 * „reel“ (wischbares Band) kommt aus dem Kit fluid.
 * Felder wie in fluid: „Hervorheben“ je Karte (featured, Etikett badge, Standard „Empfohlen“) und „Filter nach Kategorie“
 * (Feld filter am Block, category je Karte): fluid_filter() von fluid liefert <ncss-filter> samt Texten, Leiste und Kategorie-
 * Nummern je Karte und meldet '@filter' – Stylesheet und Skript dazu sind css/filter.css, js/filter.js dieses Kits (NativeCSS).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'image';
if (!in_array($v, ['image', 'overlay', 'horizontal'], true)) {
    require native_parent_view('cards');
    return;
}
native_mark('@nv-cards');
$ratio = (string) ($d['ratio'] ?? '') ?: '3:2';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== '' || is_editing()));
$tag = fluid_htag($d);
// Filter nach Kategorie (fluid_filter(): leer bei weniger als zwei Kategorien und beim Bearbeiten)
$flt = !empty($d['filter']) && $items ? fluid_filter($b, array_map(fn($it) => (string) ($it['category'] ?? ''), $items)) : ['open' => '', 'bar' => '', 'item' => []];
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <?php if ($flt['bar'] !== ''): ?><?= $flt['open'] . $flt['bar'] ?><?php endif; ?>
  <ul class="grid <?= e(fluid_min($d)) ?> nv-cards nv-cards--<?= e($v) ?>" role="list">
    <?php foreach ($items as $i => $it):
        $link = trim((string) ($it['link'] ?? ''));
        $label = trim((string) ($it['link_label'] ?? ''));
        $pic = fluid_image(!empty($it['image']) ? (int) $it['image'] : null, $v === 'horizontal' ? '(min-width: 1080px) 320px, 100vw' : '(min-width: 1080px) 420px, (min-width: 640px) 50vw, 90vw', $ratio);
        $featured = !empty($it['featured']);
        $badge = $featured ? (trim((string) ($it['badge'] ?? '')) ?: lt('Empfohlen')) : '';
        $card = 'ncss-card' . ($link !== '' ? ' ncss-card--linked' : '')
            . ($v === 'horizontal' ? ' ncss-card--horizontal' : '') . ($v === 'overlay' ? ' nv-card--overlay' : '') . ($featured ? ' ncss-card--featured' : '');
        $cat = $flt['bar'] !== '' ? ($flt['item'][$i] ?? '') : null;
    ?>
    <li class="<?= $v === 'horizontal' ? 'ncss-card-container' : 'nv-cards__item' ?><?= $cat !== null ? ' ncss-filter-item' : '' ?>"<?= $cat !== null ? ' data-category="' . e((string) $cat) . '"' : '' ?> data-reveal><?= $b->targetEdit($link, (string) ($it['title'] ?? '')) ?>
      <article class="<?= e($card) ?>">
        <?php if ($badge !== ''): ?><span class="ncss-card-badge"<?= $b->edit("items.$i.badge") ?>><?= e($badge) ?></span><?php endif; ?>
        <?php if ($pic !== ''): ?><div class="ncss-card-media"><?= $pic ?></div><?php endif; ?>
        <div class="ncss-card-body">
          <?php if (trim((string) ($it['eyebrow'] ?? '')) !== ''): ?><p class="nv-card__eyebrow"<?= $b->edit("items.$i.eyebrow") ?>><?= fluid_title((string) $it['eyebrow']) ?></p><?php endif; ?>
          <<?= $tag ?> class="nv-card__title"><?php if ($link !== '' && !is_editing()): ?><a class="ncss-card-link" <?= fluid_link_attrs($link) ?>><?= fluid_title((string) $it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= fluid_title((string) ($it['title'] ?? '')) ?></span><?php endif; ?></<?= $tag ?>>
          <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="nv-card__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(fluid_title((string) $it['text']), false) ?></p><?php endif; ?>
        </div>
        <?php if ($link !== '' && $label !== ''): ?><div class="ncss-card-footer"><span class="nv-more" aria-hidden="true"><?= e($label) ?><?= icon('arrow-right') ?></span></div><?php endif; ?>
      </article>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($flt['bar'] !== ''): ?></ncss-filter><?php endif; ?>
  <?php if (trim((string) ($d['more_label'] ?? '')) !== '' && trim((string) ($d['more_link'] ?? '')) !== ''): ?>
  <p class="more-row"><a class="btn btn--secondary" <?= fluid_link_attrs($d['more_link']) ?>><span<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></span></a></p>
  <?php endif; ?>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Karten – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
