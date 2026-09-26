<?php
/**
 * „Ressorts“: großes Menü mit allen Rubriken, ihren Unterseiten und Beschreibungen (nur Desktop; mobil: Menü-Blatt).
 * <details> – funktioniert ohne JavaScript; site.js schließt es bei Escape, Klick daneben und beim Öffnen eines anderen Menüs.
 * @var array $menu
 */
$currentId = (int) (app()->currentPage['id'] ?? 0);
$cur = fn(array $m) => is_int($m['id']) && $m['id'] === $currentId ? ' aria-current="page"' : '';
?>
<details class="ressorts nav__sub">
  <summary class="ressorts__btn"><span class="ressorts__bars" aria-hidden="true"></span><span><?= e(lt('Ressorts')) ?></span></summary>
  <div class="ressorts__panel">
    <div class="wrap ressorts__grid">
      <?php foreach ($menu as $i => $m): $desc = editorial_page_desc($m['id']); ?>
      <div class="ressorts__col">
        <p class="ressorts__num" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></p>
        <a class="ressorts__head" href="<?= e($m['href']) ?>"<?= $cur($m) ?>><?= e($m['label']) ?></a>
        <?php if ($desc !== ''): ?><p class="ressorts__desc"><?= e($desc) ?></p><?php endif; ?>
        <?php if ($m['children']): ?>
        <ul class="ressorts__list">
          <?php foreach ($m['children'] as $c): ?><li><a href="<?= e($c['href']) ?>"<?= $cur($c) ?>><?= e($c['label']) ?></a></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</details>
