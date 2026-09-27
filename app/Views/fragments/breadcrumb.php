<?php
/**
 * Kern-Fragment „breadcrumb“ (überschreibbar: kits/{kit}/fragments/breadcrumb.php): Brotkrumen-Navigation.
 * $items = [['label' => …, 'href' => …], …] (letzter Eintrag = aktuelle Seite, ohne Link); ohne $items aus dem
 * Seitenbaum (Startseite › Elternseiten › Seite). Für die strukturierten Daten sorgt Core\StructuredData.
 * Klassen: .breadcrumb (+ $class), aktuelle Seite aria-current="page".
 * @var ?array $items  @var ?array $page  @var ?string $class
 */
use Core\Pages;

if (!isset($items)) {
    $page ??= app()->currentPage;
    $items = [];
    if ($page && empty($page['is_home']) && !empty($page['id'])) {
        if ($home = Pages::home()) $items[] = ['label' => $home['nav_title'] ?? '' ?: $home['title'], 'href' => Pages::url($home)];
        foreach (Pages::ancestors($page) as $a) $items[] = ['label' => $a['nav_title'] ?? '' ?: $a['title'], 'href' => Pages::url($a)];
        $items[] = ['label' => $page['title'], 'href' => null];
    }
}
if (count($items) < 2) return;
$last = array_key_last($items);
?>
<nav class="breadcrumb<?= !empty($class) ? ' ' . e($class) : '' ?>" aria-label="<?= e(lt('Brotkrumen')) ?>">
  <ol role="list">
    <?php foreach ($items as $i => $it): ?><li><?php if ($i === $last || empty($it['href'])): ?><span aria-current="page"><?= e($it['label']) ?></span><?php else: ?><a href="<?= e($it['href']) ?>"><?= e($it['label']) ?></a><?php endif; ?></li><?php endforeach; ?>
  </ol>
</nav>
