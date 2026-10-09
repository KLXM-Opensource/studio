<?php
/**
 * Merkmale: cards (je Eintrag eine Glaskarte, Symbol in einer Glasperle) · panel (alle Einträge auf EINER Glasfläche,
 * getrennt durch Lichtlinien) · list (Zeilen mit Symbol). Anzahl nebeneinander aus der Mindestbreite.
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['cards', 'panel', 'list'], true) ? $b->variant() : 'cards';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== '' || is_editing()));
$tag = glas_htag($d);
?>
<div class="wrap">
  <?= glas_head($b) ?>
  <?php if ($items): ?>
  <ul class="feats feats--<?= e($v) ?> <?= e(glas_min($d)) ?><?= $v === 'panel' ? ' glass' : '' ?>" role="list">
    <?php foreach ($items as $i => $it):
        $link = trim((string) ($it['link'] ?? ''));
        $label = trim((string) ($it['link_label'] ?? ''));
        $img = !empty($it['image']) ? glas_image((int) $it['image'], '(min-width: 1080px) 400px, 100vw', '3:2', 'feat__img') : '';
    ?>
    <li class="feat<?= $v === 'cards' ? ' card glass' : '' ?>" data-reveal>
      <?php if ($img !== ''): ?><?= $img ?><?php elseif (!empty($it['icon'])): ?><span class="orb feat__ico"><?= icon((string) $it['icon']) ?></span><?php endif; ?>
      <div class="feat__body">
        <<?= $tag ?> class="feat__title"><?php if ($link !== '' && $label === '' && !is_editing()): ?><a class="cover-link" <?= glas_link_attrs($link) ?>><?= glas_title((string) $it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= glas_title((string) ($it['title'] ?? '')) ?></span><?php endif; ?></<?= $tag ?>>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="feat__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(glas_title((string) $it['text']), false) ?></p><?php endif; ?>
        <?php if ($link !== '' && $label !== ''): ?><a class="more feat__more" <?= glas_link_attrs($link) ?>><span<?= $b->edit("items.$i.link_label") ?>><?= e($label) ?></span><?= icon('arrow-right', ['class' => 'more__ico']) ?></a><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Einträge – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
