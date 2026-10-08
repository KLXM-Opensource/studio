<?php
/**
 * Fließtext / Artikel. standard: Lesebreite · article: Lesebreite + Inhaltsverzeichnis als Seitenspalte (Sidebar-Muster:
 * steht neben dem Text, sobald Platz ist, sonst darüber) · columns: CSS-Spalten mit Mindestbreite (column-width).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'standard';
$html = rich((string) $d['text']);
$toc = [];
if ($v === 'article') [$html, $toc] = fluid_toc($html, $b->domId());
$showToc = $v === 'article' && !empty($d['toc']) && count(array_filter($toc, fn($t) => $t['level'] === 2)) >= 2;
$meta = trim((string) ($d['meta'] ?? ''));
$measure = in_array($d['measure'] ?? 'normal', ['narrow', 'wide', 'full'], true) && $v !== 'columns' ? ' prose--m-' . $d['measure'] : '';
$prose = '<div class="prose' . (!empty($d['dropcap']) ? ' prose--dropcap' : '') . ($v === 'columns' ? ' prose--columns' : '') . $measure . '"' . $b->edit('text', 'rich') . '>' . $html . '</div>';
?>
<?php if ($v === 'article'): ?>
<div class="wrap article">
  <?= fluid_head($b, 'article__head') ?>
  <?php if ($meta !== '' || trim(strip_tags($html)) !== ''): ?>
  <p class="article__meta"><?php if ($meta !== ''): ?><span<?= $b->edit('meta') ?>><?= e($meta) ?></span><span aria-hidden="true"> · </span><?php endif; ?><?= e(lt('{n} Min. Lesezeit', ['n' => fluid_reading_time($html)])) ?></p>
  <?php endif; ?>
  <div class="article__body<?= $showToc ? ' has-toc' : '' ?>">
    <?php if ($showToc): ?>
    <nav class="toc" aria-labelledby="<?= e($b->domId()) ?>-toc">
      <p class="toc__title" id="<?= e($b->domId()) ?>-toc"><?= e(lt('Inhalt')) ?></p>
      <ol class="toc__list" role="list">
        <?php foreach ($toc as $t): ?><li class="toc__item toc__item--<?= (int) $t['level'] ?>"><a href="#<?= e($t['id']) ?>"><?= e($t['label']) ?></a></li><?php endforeach; ?>
      </ol>
    </nav>
    <?php endif; ?>
    <?= $prose ?>
  </div>
</div>
<?php else: ?>
<div class="wrap<?= $v === 'columns' ? '' : ' wrap--text' ?>">
  <?= fluid_head($b) ?>
  <?= $prose ?>
</div>
<?php endif;
