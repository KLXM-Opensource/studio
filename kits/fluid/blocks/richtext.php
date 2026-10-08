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
// Einblenden beim Scrollen (Feld reveal): ganzer Block bzw. Absätze nacheinander (site.js setzt data-reveal an die Absätze) – nicht im Editor
$reveal = is_editing() ? 'off' : (string) ($d['reveal'] ?? 'off');
$prose = '<div class="prose' . (!empty($d['dropcap']) ? ' prose--dropcap' : '') . ($v === 'columns' ? ' prose--columns' : '') . $measure . '"' . ($reveal === 'paragraphs' ? ' data-reveal-children' : '') . $b->edit('text', 'rich') . '>' . $html . '</div>';
?>
<?php if ($v === 'article'): ?>
<div class="wrap article"<?= $reveal === 'block' ? ' data-reveal' : '' ?>>
  <?= fluid_head($b, 'article__head') ?>
  <?php if ($meta !== '' || trim(strip_tags($html)) !== ''): ?>
  <p class="article__meta"><?php if ($meta !== ''): ?><span<?= $b->edit('meta') ?>><?= e($meta) ?></span><span aria-hidden="true"> · </span><?php endif; ?><?= e(lt('{n} Min. Lesezeit', ['n' => fluid_reading_time($html)])) ?></p>
  <?php endif; ?>
  <div class="article__body<?= $showToc ? ' has-toc' : '' ?>">
    <?php if ($showToc): ?>
    <?php $spy = in_array($d['scrollspy'] ?? 'off', ['mark', 'progress'], true) ? (string) $d['scrollspy'] : ''; ?>
    <nav class="toc<?= $spy ? ' toc--spy toc--spy-' . e($spy) : '' ?>" aria-labelledby="<?= e($b->domId()) ?>-toc"<?= $spy ? ' data-scrollspy' : '' ?>>
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
<div class="wrap<?= $v === 'columns' ? '' : ' wrap--text' ?>"<?= $reveal === 'block' ? ' data-reveal' : '' ?>>
  <?= fluid_head($b) ?>
  <?= $prose ?>
</div>
<?php endif;
