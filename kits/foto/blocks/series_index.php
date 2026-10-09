<?php
/**
 * Serien-Übersicht – Einträge aus den Unterseiten (foto_series_items: Angaben aus „Serie (Kopf)“) oder von Hand.
 *   grid  Raster der Titelbilder (höchstens N Spalten, auto-fill + max()), Titel und Jahr darunter
 *   list  große typografische Liste; beim Zeigen erscheint das Titelbild (js/series.js: folgt dem Mauszeiger, bei
 *         „Bewegung reduzieren“ fest am rechten Rand). Ohne Maus (Touch) bzw. ohne JavaScript: kleines Bild in der Zeile.
 *   rows  abwechselnde Reihen Bild/Text (Flex-Umbruch statt Breakpoint)
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['grid', 'list', 'rows'], true) ? $b->variant() : 'grid';
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$cols = in_array((string) ($d['columns'] ?? ''), ['2', '3', '4'], true) ? (string) $d['columns'] : 'auto';
$meta = !empty($d['show_meta']);
$items = foto_series_items($d);
$tag = trim((string) ($d['title'] ?? '')) !== '' ? 'h3' : 'h2';
$manual = ($d['source'] ?? 'children') === 'manual';
$ed = fn(array $it, string $k) => $manual && $it['path'] !== '' ? $b->edit($it['path'] . '.' . $k) : '';
$head = foto_head($b);
?>
<div class="<?= e($v === 'list' ? 'wrap' : foto_gw($d)) ?>">
<?= $head ?>
<?php if (!$items): ?>
  <?php if (is_editing()): ?><p class="empty-hint">Noch keine Serien – Unterseiten anlegen (mit „Serie (Kopf)“) oder „Von Hand“ wählen.</p><?php endif; ?>
<?php elseif ($v === 'list'): ?>
  <ul class="sx-list" role="list" data-sx-list>
    <?php foreach ($items as $i => $it):
        $pic = $it['image'] ? img($it['image'], '(min-width: 900px) 28rem, 6rem', ['ratio' => $ratio, 'alt' => '']) : '';
    ?>
    <li class="sx-list__item">
      <?php if ($it['href'] !== '' && !is_editing()): ?><a class="sx-list__link" href="<?= e($it['href']) ?>"><?php else: ?><div class="sx-list__link"><?php endif; ?>
        <span class="sx-list__title"<?= $ed($it, 'title') ?>><?= foto_title($it['title']) ?></span>
        <?php if ($meta && ($it['year'] !== '' || $it['category'] !== '')): ?><span class="sx-list__meta"><?= foto_meta_line([$it['category'], $it['year']]) ?></span><?php endif; ?>
        <?php if ($pic !== ''): ?><span class="sx-list__thumb <?= e(foto_ratio_class($ratio)) ?>" aria-hidden="true"><?= $pic ?></span><?php endif; ?>
      <?php if ($it['href'] !== '' && !is_editing()): ?></a><?php else: ?></div><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <div class="sx-peek" aria-hidden="true" data-sx-peek hidden></div>
<?php elseif ($v === 'rows'): ?>
  <div class="sx-rows">
    <?php foreach ($items as $i => $it): ?>
    <article class="sx-row<?= $i % 2 ? ' sx-row--flip' : '' ?>" data-reveal>
      <?= $it['image'] ? foto_photo(media($it['image']) ?? [], ['sizes' => '(min-width: 1000px) 55vw, 100vw', 'ratio' => $ratio, 'class' => 'sx-row__media', 'alt' => '']) : foto_photo_empty($ratio) ?>
      <div class="sx-row__text">
        <?php if ($meta && ($it['year'] !== '' || $it['category'] !== '')): ?><p class="sx-meta"><?= foto_meta_line([$it['category'], $it['year']]) ?></p><?php endif; ?>
        <<?= $tag ?> class="sx-row__title"><?php if ($it['href'] !== '' && !is_editing()): ?><a class="cover-link" href="<?= e($it['href']) ?>"><?= foto_title($it['title']) ?></a><?php else: ?><span<?= $ed($it, 'title') ?>><?= foto_title($it['title']) ?></span><?php endif; ?></<?= $tag ?>>
        <?php if ($it['place'] !== ''): ?><p class="sx-row__place"><?= e($it['place']) ?></p><?php endif; ?>
        <?php if ($it['href'] !== ''): ?><span class="more" aria-hidden="true"><?= e(lt('Serie ansehen')) ?><?= icon('arrow-right', ['class' => 'more__ico']) ?></span><?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <ul class="sx-grid cols-<?= e($cols) ?>" role="list">
    <?php foreach ($items as $i => $it): ?>
    <li class="sx-card" data-reveal>
      <?= $it['image'] ? foto_photo(media($it['image']) ?? [], ['sizes' => match ($cols) { '2' => '(min-width: 700px) 50vw, 100vw', '4' => '(min-width: 1100px) 25vw, (min-width: 600px) 50vw, 100vw', default => '(min-width: 1100px) 33vw, (min-width: 600px) 50vw, 100vw' }, 'ratio' => $ratio, 'class' => 'sx-card__media', 'alt' => '']) : foto_photo_empty($ratio) ?>
      <div class="sx-card__body">
        <<?= $tag ?> class="sx-card__title"><?php if ($it['href'] !== '' && !is_editing()): ?><a class="cover-link" href="<?= e($it['href']) ?>"><?= foto_title($it['title']) ?></a><?php else: ?><span<?= $ed($it, 'title') ?>><?= foto_title($it['title']) ?></span><?php endif; ?></<?= $tag ?>>
        <?php if ($meta && ($it['year'] !== '' || $it['category'] !== '')): ?><p class="sx-meta"><?= foto_meta_line([$it['category'], $it['year']]) ?></p><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
</div>
