<?php
/**
 * Serie (Kopf) – Titel (H1), Angaben (Jahr, Ort, Kategorie, Auftrag), Statement, optional Titelbild.
 *   text   nur Text (Titel groß, Angaben als Liste daneben, sobald Platz ist)
 *   cover  Titelbild breit über dem Text
 *   side   Text und Titelbild nebeneinander (Flex-Umbruch)
 * Jahr, Kategorie und Titelbild liest die Serien-Übersicht (foto_series_meta).
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['text', 'cover', 'side'], true) ? $b->variant() : 'text';
$ratio = (string) ($d['ratio'] ?? '3:2');
$facts = [];
foreach (['year' => lt('Jahr'), 'place' => lt('Ort'), 'category' => lt('Kategorie'), 'client' => lt('Auftrag')] as $k => $label) {
    $val = trim((string) ($d[$k] ?? ''));
    if ($val !== '' || (is_editing() && in_array($k, ['year', 'category'], true))) $facts[] = [$k, $label, $val];
}
$statement = trim(strip_tags((string) ($d['statement'] ?? ''))) !== '' || is_editing();
$cover = !empty($d['cover']) ? media((int) $d['cover']) : null;
$coverHtml = $cover ? foto_photo($cover, ['sizes' => $v === 'side' ? '(min-width: 1000px) 50vw, 100vw' : '(min-width: 1500px) 1400px, 100vw', 'ratio' => $ratio, 'class' => 'sh__cover', 'eager' => true, 'path' => 'cover']) : ($v !== 'text' ? foto_photo_empty($ratio, 'Titelbild') : '');
// Link zurück: eigener Link oder übergeordnete Seite
$backLabel = trim((string) ($d['back_label'] ?? ''));
$backHref = '';
if ($backLabel !== '') {
    if (trim((string) ($d['back_link'] ?? '')) !== '') $backHref = foto_link($d['back_link']);
    elseif (!empty(app()->currentPage['parent_id']) && ($pp = \Core\Pages::find((int) app()->currentPage['parent_id']))) $backHref = \Core\Pages::url($pp);
}
?>
<div class="wrap sh sh--<?= e($v) ?>">
  <?php if ($v === 'cover' && $coverHtml !== ''): ?><div class="sh__media sh__media--cover"><?= $coverHtml ?></div><?php endif; ?>
  <div class="sh__main">
    <header class="sh__head">
      <?php if ($backHref !== ''): ?><a class="sh__back" href="<?= e($backHref) ?>"><?= icon('arrow-right', ['class' => 'flip-x']) ?><span<?= $b->edit('back_label') ?>><?= e($backLabel) ?></span></a><?php endif; ?>
      <?php if (trim((string) ($d['eyebrow'] ?? '')) !== ''): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= foto_title((string) $d['eyebrow']) ?></p><?php endif; ?>
      <h1 id="<?= e($b->titleId()) ?>" class="h1 sh__title"<?= $b->edit('title') ?>><?= foto_title((string) ($d['title'] ?? '')) ?></h1>
    </header>
    <?php if ($facts || $statement): ?>
    <div class="sh__body">
      <?php if ($facts): ?>
      <dl class="sh__facts">
        <?php foreach ($facts as [$k, $label, $val]): ?><div><dt><?= e($label) ?></dt><dd<?= $b->edit($k) ?>><?= e($val) ?></dd></div><?php endforeach; ?>
      </dl>
      <?php endif; ?>
      <?php if ($statement): ?><div class="prose sh__statement"<?= $b->edit('statement', 'rich') ?>><?= rich((string) ($d['statement'] ?? '')) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($v === 'side' && $coverHtml !== ''): ?><div class="sh__media"><?= $coverHtml ?></div><?php endif; ?>
  <?= foto_drop_zone('cover', 'single') ?>
</div>
