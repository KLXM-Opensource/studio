<?php
/**
 * Diagramm (Kern-Block, vom Kit überschreibbar: kits/{name}/blocks/chart.php) – Core\Charts mit austauschbarer Engine.
 * @var \Core\Block $b  @var array $d
 */
$wrap = app()->theme->def['container_class'] ?? 'wrap';
$spec = \Core\Charts::fromBlock($d);
$spec['title'] = '';   // Überschrift steht im Kopf des Abschnitts
if (trim((string) ($spec['description'] ?? '')) === '') $spec['description'] = trim((string) ($d['title'] ?? ''));   // Screenreader: sonst die Überschrift
?>
<div class="<?= e($wrap) ?>">
  <?= \Core\MediaBlocks::head($b) ?>
  <?= \Core\Charts::render($spec, ['table' => \Core\Charts::tableOption($d)]) ?>
</div>
