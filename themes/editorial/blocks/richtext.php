<?php
/**
 * Text (Artikel): Lesetext in Lesebreite (Design → Lesebreite), optional Initiale, Inhaltsverzeichnis und Randnotiz.
 * Die Randspalte steht je nach Seitenraster rechts (klassisch), links (asymmetrisch) oder unter dem Text (zentriert).
 * @var \Core\Block $b  @var array $d
 */
$html = rich((string) ($d['text'] ?? ''));
$toc = [];
if (!empty($d['toc']) && !is_editing()) [$html, $toc] = editorial_toc($html, $b->domId());
$aside = trim((string) ($d['aside'] ?? ''));
$asideTitle = trim((string) ($d['aside_title'] ?? ''));
$tocNav = editorial_toc_nav($toc, $b->domId() . '-toc');
$side = $tocNav !== '' || $aside !== '';
?>
<div class="wrap story<?= $side ? ' story--side' : '' ?>">
  <div class="story__main">
    <?= editorial_head($b, 'story__head') ?>
    <?php if (trim(strip_tags($html)) !== '' || is_editing()): ?>
    <div class="prose<?= !empty($d['dropcap']) ? ' prose--dropcap' : '' ?>"<?= $b->edit('text', 'rich') ?>><?= $html ?></div>
    <?php endif; ?>
  </div>
  <?php if ($side): ?>
  <aside class="story__side" aria-label="<?= e($asideTitle !== '' ? $asideTitle : lt('Ergänzungen')) ?>">
    <?= $tocNav ?>
    <?php if ($aside !== ''): ?>
    <div class="note">
      <?php if ($asideTitle !== ''): ?><p class="note__title"<?= $b->edit('aside_title') ?>><?= e($asideTitle) ?></p><?php endif; ?>
      <p class="note__text"<?= $b->edit('aside') ?>><?= nl2br(e($aside), false) ?></p>
    </div>
    <?php endif; ?>
  </aside>
  <?php endif; ?>
</div>
