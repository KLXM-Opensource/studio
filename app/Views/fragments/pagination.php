<?php
/**
 * Kern-Fragment „pagination“ (überschreibbar: kits/{kit}/fragments/pagination.php): Blättern mit Links – ohne JavaScript.
 * $url = fn(int $seite): string liefert die Adresse einer Seite (Standard: aktuelle Adresse mit ?seite=N).
 * Klassen: .pagination (+ $class) · .pagination__prev / __next · aktuelle Seite aria-current="page" · Lücke .pagination__gap
 * @var int $page  @var int $pages  @var ?callable $url  @var ?string $class
 */
$page = max(1, (int) ($page ?? 1));
$pages = max(1, (int) ($pages ?? 1));
if ($pages < 2) return;
$url ??= function (int $n): string {
    $q = $_GET;
    if ($n > 1) $q['seite'] = $n; else unset($q['seite']);
    return strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') . ($q ? '?' . http_build_query($q) : '');
};
$show = array_unique(array_filter([1, $page - 1, $page, $page + 1, $pages], fn($n) => $n >= 1 && $n <= $pages));
sort($show);
?>
<nav class="pagination<?= !empty($class) ? ' ' . e($class) : '' ?>" aria-label="<?= e(lt('Seiten')) ?>">
  <ul role="list">
    <?php if ($page > 1): ?><li class="pagination__prev"><a href="<?= e($url($page - 1)) ?>" rel="prev"><?= e(lt('Zurück')) ?></a></li><?php endif; ?>
    <?php $prev = 0; foreach ($show as $n): ?>
      <?php if ($n - $prev > 1): ?><li class="pagination__gap" aria-hidden="true">…</li><?php endif; ?>
      <li><?php if ($n === $page): ?><span aria-current="page"><?= $n ?></span><?php else: ?><a href="<?= e($url($n)) ?>"><span class="sr-only"><?= e(lt('Seite')) ?> </span><?= $n ?></a><?php endif; ?></li>
    <?php $prev = $n; endforeach; ?>
    <?php if ($page < $pages): ?><li class="pagination__next"><a href="<?= e($url($page + 1)) ?>" rel="next"><?= e(lt('Weiter')) ?></a></li><?php endif; ?>
  </ul>
</nav>
