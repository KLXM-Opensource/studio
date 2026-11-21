<?php
/**
 * Unterseiten (Kern-Block, vom Kit überschreibbar: kits/{name}/blocks/subpages.php). Die Kinder einer Seite – Standard: die
 * aufgerufene Seite, sonst die per Linkauswahl gewählte (page:ID) – als Karten, Liste oder Kacheln, automatisch aus dem Seitenbaum
 * (Core\Blocks\SubpageList): Titel, Beschreibung, Bild des Seitenkopfs bzw. Vorschaubild. Jede Karte ist ein Link (Titel), die
 * Liste bleibt eine echte Liste mit Links. Aussehen: resources/css/subpages.css (--kit-*, Variablen --sp-*).
 * @var \Core\Block $b  @var array $d
 */
use Core\Blocks\SubpageList;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$layout = in_array($d['layout'] ?? '', ['cards', 'list', 'tiles'], true) ? $d['layout'] : 'cards';
$cols = in_array((string) ($d['columns'] ?? ''), ['2', '3', '4'], true) ? (string) $d['columns'] : '3';
$ratio = in_array($d['ratio'] ?? '', ['16:9', '16:10', '4:3', '3:2', '1:1'], true) ? (string) $d['ratio'] : '16:10';
$tag = in_array($d['heading'] ?? '', ['h2', 'h3', 'h4'], true) ? (string) $d['heading'] : (!empty($d['title']) ? 'h3' : 'h2');
$showImage = $layout === 'cards' && ($d['show_image'] ?? true);
$showText = (bool) ($d['show_text'] ?? true);
$more = trim((string) ($d['more_label'] ?? '')) ?: lt('Mehr erfahren');
$items = SubpageList::items($d);
$sizes = ['2' => '(min-width: 1080px) 520px, (min-width: 640px) 45vw, 90vw', '3' => '(min-width: 1080px) 360px, (min-width: 640px) 45vw, 90vw',
    '4' => '(min-width: 1080px) 270px, (min-width: 640px) 45vw, 90vw'][$cols];
?>
<div class="<?= e($wrap) ?> cms-subpages cms-subpages--<?= e($layout) ?> cms-subpages--c<?= e($cols) ?>">
  <?php if (!empty($d['eyebrow']) || !empty($d['title']) || !empty($d['intro'])): ?>
  <header class="cms-subpages__head">
    <?php if (!empty($d['eyebrow'])): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($d['title'])): ?><h2 class="h2" id="<?= e($b->titleId()) ?>"<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></h2><?php endif; ?>
    <?php if (!empty($d['intro'])): ?><p class="cms-subpages__intro"<?= $b->edit('intro') ?>><?= emphasis((string) $d['intro']) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>
  <?php if ($items): ?>
  <ul class="cms-subpages__list" role="list"<?= !empty($d['title']) ? ' aria-labelledby="' . e($b->titleId()) . '"' : '' ?>>
    <?php foreach ($items as $it): $pic = $showImage && $it['image'] ? img($it['image'], $sizes, ['ratio' => $ratio, 'alt' => '']) : ''; ?>
    <li class="cms-subpage<?= $showImage ? ($pic !== '' ? ' cms-subpage--img' : ' cms-subpage--noimg') : '' ?>">
      <?php if ($pic !== ''): ?><div class="cms-subpage__img cms-subpage__img--r<?= e(str_replace(':', '-', $ratio)) ?>"><?= $pic ?></div><?php endif; ?>
      <div class="cms-subpage__body">
        <<?= $tag ?> class="cms-subpage__title"><a href="<?= e($it['href']) ?>"><?= e($it['title']) ?></a></<?= $tag ?>>
        <?php if ($showText && $it['text'] !== ''): ?><p class="cms-subpage__text"><?= e($it['text']) ?></p><?php endif; ?>
        <?php if ($layout === 'tiles'): ?><span class="cms-subpage__more" aria-hidden="true"><?= e($more) ?><?= icon('arrow-right') ?></span><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= e(__('Keine veröffentlichten Unterseiten – legen Sie unter dieser Seite Seiten an oder wählen Sie eine andere Elternseite.')) ?></p><?php endif; ?>
</div>
