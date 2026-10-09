<?php
/**
 * Bento-Raster: 12-Spalten-Raster (grid-auto-flow: dense) aus Kacheln verschiedener Größe und Fläche.
 * Handy: eine Spalte · Tablet: zwei Spalten · ab 64 em: normal = 4, breit/groß = 8, ganz = 12 Spalten; hoch/groß = 2 Zeilen.
 * @var \Core\Block $b  @var array $d
 */
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== '' || is_editing()));
$tag = modern_htag($d);
$tones = ['card', 'tint', 'pop', 'accent', 'dark', 'image'];
?>
<div class="wrap">
  <?= modern_head($b) ?>
  <?php if ($items): ?>
  <ul class="bento" role="list">
    <?php foreach ($items as $i => $it):
        $size = in_array($it['size'] ?? '', ['s', 'wide', 'tall', 'big', 'full'], true) ? $it['size'] : 's';
        $tone = ($it['tone'] ?? '') === 'highlight' ? 'pop' : (in_array($it['tone'] ?? '', $tones, true) ? $it['tone'] : 'card');
        $img = !empty($it['image']) ? img((int) $it['image'], in_array($size, ['big', 'wide', 'full'], true) ? '(min-width: 1080px) 640px, 100vw' : '(min-width: 1080px) 360px, 100vw', ($tone === 'image' ? ['alt' => ''] : [])) : '';
        if ($tone === 'image' && $img === '') $tone = 'dark';
        $link = trim((string) ($it['link'] ?? ''));
        $eyebrow = trim((string) ($it['eyebrow'] ?? ''));
        $big = $eyebrow !== '' && preg_match('#^[\d.,+%€$×x/<>~\s-]{1,8}$#u', $eyebrow);
    ?>
    <li class="tile tile--<?= e($size) ?> tile--<?= e($tone) ?><?= ['tint' => ' bg-tint', 'pop' => ' bg-pop', 'accent' => ' bg-accent', 'dark' => ' bg-dark', 'image' => ' on-media'][$tone] ?? '' ?><?= $img !== '' ? ' tile--has-img' : '' ?>" data-reveal><?= $b->targetEdit($link, (string) ($it['title'] ?? '')) ?>
      <?php if ($img !== ''): ?><div class="tile__img"<?= $tone === 'image' ? ' aria-hidden="true"' : '' ?>><?= $img ?></div><?php endif; ?>
      <div class="tile__body">
        <?php if (!empty($it['icon'])): ?><span class="tile__ico"><?= icon((string) $it['icon']) ?></span><?php endif; ?>
        <?php if ($eyebrow !== ''): ?><p class="<?= $big ? 'tile__num' : 'tile__eyebrow' ?>"<?= $b->edit("items.$i.eyebrow") ?>><?= modern_title($eyebrow) ?></p><?php endif; ?>
        <<?= $tag ?> class="tile__title"><?php if ($link !== '' && !is_editing()): ?><a class="cover-link" <?= modern_link_attrs($link) ?>><?= modern_title((string) $it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= modern_title((string) ($it['title'] ?? '')) ?></span><?php endif; ?></<?= $tag ?>>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="tile__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(modern_title((string) $it['text']), false) ?></p><?php endif; ?>
        <?php if ($link !== ''): ?><span class="tile__go" aria-hidden="true"><?= icon('arrow-up-right') ?></span><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Kacheln – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
