<?php
/**
 * 404-Vorschläge (Kern-Block, vom Kit überschreibbar: kits/{name}/blocks/not_found.php) – für die Seite „Nicht gefunden (404)“
 * (Core\NotFound). Kopf (Dachzeile, Überschrift als H1, Text), „Vielleicht meinten Sie …“ passend zur aufgerufenen Adresse und
 * Suchfeld (NotFound::boxes – nur bei eingeschalteter Suche), Button zur Startseite und ein optionaler zweiter Button.
 * Aussehen: resources/css/notfound.css (Variablen --nf-*), Klassen des Kits: eyebrow, h1, lead, btn-row, container_class,
 * button_class, button_secondary_class.
 * @var \Core\Block $b  @var array $d
 */
use Core\NotFound;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$btn = app()->theme->def['button_class'] ?? 'btn btn--primary';
$btn2 = app()->theme->def['button_secondary_class'] ?? 'btn btn--secondary';
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));
$title = trim((string) ($d['title'] ?? ''));
$intro = trim((string) ($d['intro'] ?? ''));
$home = trim((string) ($d['home_label'] ?? ''));
$more = trim((string) ($d['link_label'] ?? '')) !== '' && !empty($d['link']) ? link_href((string) $d['link']) : null;
?>
<div class="<?= e(trim($wrap . ' cms-404')) ?>">
  <?php if ($eyebrow !== '' || $title !== '' || $intro !== ''): ?>
  <header class="cms-404__head">
    <?php if ($eyebrow !== ''): ?><p class="eyebrow eyebrow--accent"<?= $b->edit('eyebrow') ?>><?= emphasis($eyebrow) ?></p><?php endif; ?>
    <?php if ($title !== ''): ?><h1 id="<?= e($b->titleId()) ?>" class="h1 cms-404__title"><span<?= $b->edit('title') ?>><?= emphasis($title) ?></span></h1><?php endif; ?>
    <?php if ($intro !== ''): ?><p class="lead cms-404__lead"<?= $b->edit('intro') ?>><?= nl2br(emphasis($intro), false) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>
  <?= NotFound::boxes($b) ?>
  <?php if ($home !== '' || $more): ?>
  <div class="btn-row cms-404__btns">
    <?php if ($home !== ''): ?><a class="<?= e($btn) ?>" href="<?= e(NotFound::homeUrl()) ?>"<?= $b->edit('home_label') ?>><?= e($home) ?></a><?php endif; ?>
    <?php if ($more): ?><a class="<?= e($btn2) ?>" href="<?= e($more) ?>"<?= $b->edit('link_label') ?>><?= e((string) $d['link_label']) ?></a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
