<?php
/**
 * Fließtext / Artikel. standard: Lesebreite · article: Lesebreite + Inhaltsverzeichnis als Seitenspalte (steht neben dem
 * Text, sobald Platz ist, sonst darüber) mit Lesezeit · glass: Lesebreite auf einer Glasfläche.
 * Zwischenüberschriften h2–h4, Zitate (blockquote), Listen, Links.
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['article', 'glass'], true) ? $b->variant() : 'standard';
$html = rich((string) $d['text']);
$toc = [];
if ($v === 'article') [$html, $toc] = glas_toc($html, $b->domId());
$showToc = $v === 'article' && !empty($d['toc']) && count(array_filter($toc, fn($t) => $t['level'] === 2)) >= 2;
$meta = trim((string) ($d['meta'] ?? ''));
$prose = '<div class="prose"' . $b->edit('text', 'rich') . '>' . $html . '</div>';
?>
<?php if ($v === 'article'): ?>
<div class="wrap article">
  <?= glas_head($b, 'article__head') ?>
  <?php if ($meta !== '' || trim(strip_tags($html)) !== ''): ?>
  <p class="article__meta label"><?php if ($meta !== ''): ?><span<?= $b->edit('meta') ?>><?= e($meta) ?></span><span aria-hidden="true"> · </span><?php endif; ?><?= e(lt('{n} Min. Lesezeit', ['n' => glas_reading_time($html)])) ?></p>
  <?php endif; ?>
  <div class="article__body<?= $showToc ? ' has-toc' : '' ?>">
    <?php if ($showToc): ?>
    <nav class="toc glass" aria-labelledby="<?= e($b->domId()) ?>-toc">
      <p class="toc__title label" id="<?= e($b->domId()) ?>-toc"><?= e(lt('Inhalt')) ?></p>
      <ol class="toc__list" role="list">
        <?php $n = 0; foreach ($toc as $t): ?><li class="toc__item toc__item--<?= (int) $t['level'] ?>"><a href="#<?= e($t['id']) ?>"><?php if ($t['level'] === 2): ?><span class="toc__n" aria-hidden="true"><?= glas_num(++$n) ?></span><?php endif; ?><?= e($t['label']) ?></a></li><?php endforeach; ?>
      </ol>
    </nav>
    <?php endif; ?>
    <?= $prose ?>
  </div>
</div>
<?php else: ?>
<div class="wrap">
  <?= glas_head($b) ?>
  <div class="rt<?= $v === 'glass' ? ' rt--glass glass' : '' ?>"><?= $prose ?></div>
</div>
<?php endif;
