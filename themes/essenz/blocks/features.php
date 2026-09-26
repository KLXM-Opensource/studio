<?php
/**
 * Merkmale: panel (Bedienfeld – alle Einträge in EINEM Paneel, getrennt durch Haarlinien, Symbol im „Knopf“, Nummer
 * oben rechts) · cards (einzelne Paneele) · list (Zeilen mit Symbol). Anzahl nebeneinander aus der Mindestbreite.
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['panel', 'cards', 'list'], true) ? $b->variant() : 'panel';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== '' || is_editing()));
$tag = essenz_htag($d);
?>
<div class="wrap">
  <?= essenz_head($b) ?>
  <?php if ($items): ?>
  <ul class="feats feats--<?= e($v) ?> <?= e(essenz_min($d)) ?><?= $v === 'panel' ? ' panel' : '' ?>" role="list">
    <?php foreach ($items as $i => $it):
        $link = trim((string) ($it['link'] ?? ''));
        $label = trim((string) ($it['link_label'] ?? ''));
        $img = !empty($it['image']) ? essenz_image((int) $it['image'], '(min-width: 1080px) 400px, 100vw', '3:2', 'feat__img') : '';
    ?>
    <li class="feat<?= $v === 'cards' ? ' card' : '' ?>" data-reveal>
      <?php if ($img !== ''): ?><?= $img ?><?php elseif (!empty($it['icon'])): ?><span class="knob feat__ico"><?= icon((string) $it['icon']) ?></span><?php endif; ?>
      <?php if ($v !== 'list'): ?><span class="feat__n" aria-hidden="true"><?= essenz_num($i + 1) ?></span><?php endif; ?>
      <div class="feat__body">
        <<?= $tag ?> class="feat__title"><?php if ($link !== '' && $label === '' && !is_editing()): ?><a class="cover-link" <?= essenz_link_attrs($link) ?>><?= e($it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= e((string) ($it['title'] ?? '')) ?></span><?php endif; ?></<?= $tag ?>>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="feat__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif; ?>
        <?php if ($link !== '' && $label !== ''): ?><a class="more feat__more" <?= essenz_link_attrs($link) ?>><span<?= $b->edit("items.$i.link_label") ?>><?= e($label) ?></span><?= icon('arrow-right', ['class' => 'more__ico']) ?></a><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Einträge – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
