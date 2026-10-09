<?php /** Rubriken / Themen: Raster mit Spaltenlinien, Register mit großen Nummern, getönte Kästen oder schlicht. @var \Core\Block $b  @var array $d */
$style = in_array($d['style'] ?? '', ['grid', 'index', 'cards', 'plain'], true) ? $d['style'] : 'grid';
$cols = in_array((string) ($d['columns'] ?? ''), ['2', '3', '4'], true) ? (string) $d['columns'] : '3';
$hTag = trim((string) ($d['title'] ?? '')) !== '' ? 'h3' : 'h2';
?>
<div class="wrap">
  <?= editorial_head($b) ?>
  <ul class="feats feats--<?= e($style) ?> cols-<?= e($cols) ?>" role="list">
    <?php foreach ((array) $d['items'] as $i => $it):
      $link = trim((string) ($it['link'] ?? ''));
      $label = trim((string) ($it['link_label'] ?? ''));
      $img = (int) ($it['image'] ?? 0) ?: null;
      $ico = $style !== 'index' && !$img ? editorial_icon($it['icon'] ?? '', 'feat__ico') : ''; ?>
    <li class="feat<?= $link !== '' ? ' feat--link' : '' ?>">
      <?php if ($style === 'index'): ?><span class="feat__num" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span><?php endif; ?>
      <?php if ($img && $style !== 'index'): ?><?= editorial_image($img, '(min-width: 1080px) 400px, 50vw', '3:2', 'feat__media') ?><?php endif; ?>
      <?= $ico ?>
      <<?= $hTag ?> class="feat__title"><?php if ($link !== '' && $label === ''): ?><a class="feat__cover" <?= editorial_link_attrs($link) ?>><span<?= $b->edit("items.$i.title") ?>><?= emphasis((string) $it['title']) ?></span></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= emphasis((string) $it['title']) ?></span><?php endif; ?></<?= $hTag ?>>
      <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="feat__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(emphasis((string) $it['text']), false) ?></p><?php endif; ?>
      <?php if ($link !== '' && $label !== ''): ?><p class="feat__more"><a class="more" <?= editorial_link_attrs($link) ?>><span<?= $b->edit("items.$i.link_label") ?>><?= e($label) ?></span> <span aria-hidden="true">→</span><span class="sr-only">: <?= e(strip_emphasis((string) $it['title'])) ?></span></a></p><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint">Noch keine Einträge – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
